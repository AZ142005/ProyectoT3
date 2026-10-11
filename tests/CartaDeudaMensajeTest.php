<?php
namespace Tests;

use App\Controllers\ReporteController;
use App\Core\Database;

/**
 * Verifica el mensaje predefinido del enlace WhatsApp de la carta de deuda:
 * membrete institucional, fecha, destinatario, monto en letras y detalle
 * mensual agrupado (sin días por factura), en espejo con el formato oficial
 * de la carta impresa.
 *
 * El fixture se crea en la base de datos de desarrollo y se elimina en el
 * bloque finally; el controlador se ejecuta con sesión de administrador.
 */
class CartaDeudaMensajeTest extends TestCase {

    public function testMensajeWhatsAppAlineadoAlFormatoOficial(): void {
        $db = Database::getConnection();
        $sufijo = strtolower(substr(uniqid('', true), -8));
        $personaId = null;
        $unidadId = null;

        try {
            // Fixture: persona propietaria con teléfono móvil y email propios.
            $db->prepare(
                "INSERT INTO personas (cedula, nombre, apellido, telefono, email, tipo, numero_residentes)
                 VALUES (:cedula, :nombre, :apellido, :telefono, :email, 'propietario', 1)"
            )->execute([
                'cedula'   => 'V-12345678',
                'nombre'   => 'Juan Pérez',
                'apellido' => '',
                'telefono' => '04147778899',
                'email'    => 'test.cm.' . $sufijo . '@example.com',
            ]);
            $personaId = intval($db->lastInsertId());

            // Fixture: unidad exclusiva del test, asignada a la persona.
            $db->prepare("INSERT INTO unidades (propietario_id, numero, cuota_mensual) VALUES (:propietario_id, :numero, 0.00)")
                ->execute(['propietario_id' => $personaId, 'numero' => 'TEST-CM-' . $sufijo]);
            $unidadId = intval($db->lastInsertId());

            // Fixture: dos facturas vencidas en meses distintos con saldo pendiente.
            $vencA = date('Y-m-d', strtotime('-60 days'));
            $vencB = date('Y-m-d', strtotime('-30 days'));
            $facturas = [
                ['numero' => 'TEST-CM-' . $sufijo . '-1', 'venc' => $vencA, 'emision' => date('Y-m-d', strtotime('-75 days')), 'saldo' => 5000.00],
                ['numero' => 'TEST-CM-' . $sufijo . '-2', 'venc' => $vencB, 'emision' => date('Y-m-d', strtotime('-45 days')), 'saldo' => 107196.84],
            ];
            $stmtFactura = $db->prepare(
                "INSERT INTO facturas (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, saldo, estado)
                 VALUES (:numero, :unidad, :mes, :anio, :emision, :venc, :monto, :saldo, 'vencida')"
            );
            foreach ($facturas as $f) {
                $stmtFactura->execute([
                    'numero'  => $f['numero'],
                    'unidad'  => $unidadId,
                    'mes'     => intval(date('n', strtotime($f['venc']))),
                    'anio'    => intval(date('Y', strtotime($f['venc']))),
                    'emision' => $f['emision'],
                    'venc'    => $f['venc'],
                    'monto'   => $f['saldo'],
                    'saldo'   => $f['saldo'],
                ]);
            }

            $totalEsperado = 5000.00 + 107196.84;
            $mesA = intval(date('n', strtotime($vencA)));
            $anioA = intval(date('Y', strtotime($vencA)));

            // Renderiza la acción del controlador con sesión de administrador.
            $_SESSION = ['auth_user' => ['id' => 1, 'role' => 'admin', 'name' => 'Administrador Test']];
            $html = $this->renderizarCarta($unidadId);

            $texto = $this->extraerMensajeWhatsApp($html);
            $this->assertNotNull($texto, 'La carta debe incluir un enlace de WhatsApp con mensaje predefinido');

            if ($texto === null) {
                return;
            }

            // Membrete institucional y fecha con la ciudad.
            $this->assertStringContains('CONDOMINIO CONJUNTO LAS MESETAS', $texto,
                'El mensaje debe abrir con el nombre institucional');
            $this->assertStringContains('RIF. J-400577870', $texto,
                'El mensaje debe incluir el RIF institucional');
            $this->assertStringContains('0271-2259170', $texto,
                'El mensaje debe incluir el teléfono institucional');
            $this->assertStringContains(
                'Valera, ' . date('j') . ' de ' . nombreMes(date('n')) . ' de ' . date('Y'),
                $texto,
                'El mensaje debe fecharse en Valera con el mismo formato de la carta'
            );

            // Destinatario y cuerpo con monto en letras.
            $this->assertStringContains('JUAN PÉREZ', $texto,
                'El mensaje debe saludar al propietario en mayúsculas');
            $this->assertStringContains('morosidad de 2 meses vencidos', $texto,
                'El conteo de meses debe reflejar los 2 grupos mes/año del fixture');
            $this->assertStringContains(montoEnLetras($totalEsperado), $texto,
                'El mensaje debe expresar el total en letras');
            $this->assertStringContains(formatearMoneda($totalEsperado), $texto,
                'El mensaje debe expresar el total en cifras con formato venezolano');

            // Detalle mensual: primera fila "Saldo de {Mes}-{año}" y cierre TOTAL.
            $this->assertStringContains('Saldo de ' . nombreMes($mesA) . '-' . $anioA . ': Bs.', $texto,
                'La primera fila debe rotularse "Saldo de {Mes}-{año}: Bs."');
            $this->assertStringContains('TOTAL: Bs.', $texto,
                'El detalle debe cerrar con la fila TOTAL');

            // Medios de pago y firma institucional.
            $this->assertStringContains('0102-0373-2200-0010-2432', $texto,
                'El mensaje debe incluir la cuenta corriente del Banco de Venezuela');
            $this->assertStringContains('Ninoska Moncada', $texto,
                'El mensaje debe cerrar con la firma de la administradora');

            // Sin días por factura: el formato anterior imprimía "| 0 d" y el nuevo no.
            // El límite de palabra evita confundir el día de la fecha (p. ej. "10 de").
            $this->assertFalse((bool)preg_match('/\b0 d\b/', $texto),
                'El mensaje no debe mostrar días vencidos por factura (caso "0 d")');
            $this->assertFalse(str_contains($texto, 'AVISO OFICIAL'),
                'El mensaje no debe conservar el rótulo AVISO OFICIAL del formato anterior');
        } finally {
            if ($unidadId !== null) {
                $db->prepare("DELETE FROM facturas WHERE unidad_id = :uid")->execute(['uid' => $unidadId]);
                $db->prepare("DELETE FROM unidades WHERE id = :id")->execute(['id' => $unidadId]);
            }
            if ($personaId !== null) {
                $db->prepare("DELETE FROM personas WHERE id = :id")->execute(['id' => $personaId]);
            }
            $_SESSION = [];
        }
    }

    /**
     * Renderiza la acción del controlador capturando el HTML en búfer.
     */
    private function renderizarCarta(int $unidadId): string {
        $nivelBase = ob_get_level();
        ob_start();
        try {
            (new ReporteController())->generarCartaDeuda($unidadId);
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $nivelBase) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    /**
     * Extrae el parámetro "text" del enlace WhatsApp presente en el HTML.
     */
    private function extraerMensajeWhatsApp(string $html): ?string {
        if (!preg_match('/href="([^"]*api\.whatsapp\.com\/send[^"]*)"/', $html, $m)) {
            return null;
        }

        $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        $query = parse_url($url, PHP_URL_QUERY);
        if (!is_string($query)) {
            return null;
        }

        parse_str($query, $parametros);
        return isset($parametros['text']) ? (string)$parametros['text'] : null;
    }
}
