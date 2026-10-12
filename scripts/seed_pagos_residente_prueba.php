<?php
/**
 * Script de Seed: Pagos de demostración para el "Historial de Pagos" del residente de prueba.
 *
 * Inserta 5 pagos variados (estados, montos, fechas y métodos) para el residente
 * residente.prueba@conjunto.com (persona id=207) de la unidad A-101 (unidad id=1, Torre A),
 * de modo que /pagos ("Mis Pagos") muestre una lista demostrativa.
 *
 * Los comprobantes de ejemplo se generan como imágenes JPEG en uploads/comprobantes/
 * siguiendo la convención de FileUploader: nombre de 32 hex + extensión y hash SHA-256
 * del archivo persistido (igual que PagoController::subir).
 *
 * Es idempotente: elimina por referencia las filas de este seed antes de reinsertarlas.
 *
 * Uso: php scripts/seed_pagos_residente_prueba.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;
use App\Models\PagoModel;

const SEED_RESIDENTE_ID = 207;
const SEED_UNIDAD_ID    = 1;

echo "=== Seed de Pagos de Demostración — Residente Prueba (unidad A-101) ===" . PHP_EOL . PHP_EOL;

$db = Database::getConnection();

/**
 * Datos de los pagos a sembrar. Las cadenas de estado y método respetan
 * exactamente los valores del código (App\Core\EstadoPago y PagoDirectoController).
 */
$pagosSeed = [
    [
        'fecha_pago'         => '2026-07-05',
        'fecha_registro'     => '2026-07-05 18:41:07',
        'monto'              => 150.00,
        'metodo_pago'        => 'transferencia',
        'referencia'         => '0591236604471',
        'estado'             => 'APROBADO',
        'con_comprobante'    => true,
        'banco_pagador'      => 'Banesco',
        'banco_receptor'     => 'Banco de Venezuela',
        'cuenta_bancaria_id' => 1,
        'observaciones'      => null,
    ],
    [
        'fecha_pago'         => '2026-08-08',
        'fecha_registro'     => '2026-08-09 09:05:22',
        'monto'              => 150.00,
        'metodo_pago'        => 'pago_movil',
        'referencia'         => '04127896325',
        'estado'             => 'APROBADO',
        'con_comprobante'    => false,
        'banco_pagador'      => 'Banco de Venezuela',
        'banco_receptor'     => 'Banco de Venezuela',
        'cuenta_bancaria_id' => 1,
        'observaciones'      => null,
    ],
    [
        'fecha_pago'         => '2026-09-15',
        'fecha_registro'     => '2026-09-15 21:17:55',
        'monto'              => 150.00,
        'metodo_pago'        => 'pago_movil',
        'referencia'         => '07341624589',
        'estado'             => 'EN REVISIÓN',
        'con_comprobante'    => true,
        'banco_pagador'      => 'Mercantil',
        'banco_receptor'     => 'Banco de Venezuela',
        'cuenta_bancaria_id' => 1,
        'observaciones'      => null,
    ],
    [
        'fecha_pago'         => '2026-10-03',
        'fecha_registro'     => '2026-10-03 12:48:10',
        'monto'              => 300.00,
        'metodo_pago'        => 'transferencia',
        'referencia'         => '102123456789',
        'estado'             => 'PENDIENTE',
        'con_comprobante'    => false,
        'banco_pagador'      => 'BBVA Provincial',
        'banco_receptor'     => 'Banco de Venezuela',
        'cuenta_bancaria_id' => 1,
        'observaciones'      => null,
    ],
    [
        'fecha_pago'         => '2026-09-28',
        'fecha_registro'     => '2026-09-28 16:02:31',
        'monto'              => 450.00,
        'metodo_pago'        => 'transferencia',
        'referencia'         => '0801234567890',
        'estado'             => 'RECHAZADO',
        'con_comprobante'    => true,
        'banco_pagador'      => 'Banesco',
        'banco_receptor'     => 'Banco de Venezuela',
        'cuenta_bancaria_id' => 1,
        'observaciones'      => 'Comprobante ilegible: reenvíe una foto más clara.',
    ],
];

/**
 * Genera una imagen JPEG que simula un comprobante bancario con los datos del pago.
 * El archivo se sobrescribe en cada ejecución (idempotente).
 *
 * @param string $rutaDestino Ruta absoluta del archivo a crear
 * @param array  $pago        Datos del pago a representar
 * @return bool true si el archivo quedó generado en disco
 */
function generarComprobanteJpg(string $rutaDestino, array $pago): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $ancho = 620;
    $alto  = 880;

    $img = imagecreatetruecolor($ancho, $alto);
    if (!$img) {
        return false;
    }

    $blanco    = imagecolorallocate($img, 255, 255, 255);
    $borde     = imagecolorallocate($img, 208, 208, 208);
    $azul      = imagecolorallocate($img, 20, 52, 120);
    $azulClaro = imagecolorallocate($img, 220, 229, 248);
    $fondoFila = imagecolorallocate($img, 244, 246, 250);
    $negro     = imagecolorallocate($img, 33, 37, 41);
    $gris      = imagecolorallocate($img, 110, 116, 124);
    $verde     = imagecolorallocate($img, 25, 135, 84);

    imagefilledrectangle($img, 0, 0, $ancho, $alto, $blanco);
    imagerectangle($img, 0, 0, $ancho - 1, $alto - 1, $borde);

    // Encabezado
    imagefilledrectangle($img, 0, 0, $ancho, 150, $azul);
    imagestring($img, 5, 30, 26, 'CONDOMINIO DIGITAL', $blanco);
    $metodoLegible = ($pago['metodo_pago'] === 'pago_movil') ? 'PAGO MOVIL' : 'TRANSFERENCIA';
    imagestring($img, 3, 30, 56, 'COMPROBANTE DE ' . $metodoLegible, $azulClaro);
    imagestring($img, 5, 30, 98, 'Bs. ' . number_format((float)$pago['monto'], 2, ',', '.'), $blanco);

    // Cuerpo con los datos del pago
    $filas = [
        'Fecha de pago'   => date('d/m/Y', strtotime($pago['fecha_pago'])),
        'Hora de reporte' => date('h:i a', strtotime($pago['fecha_registro'])),
        'Referencia'      => (string)$pago['referencia'],
        'Banco pagador'   => (string)$pago['banco_pagador'],
        'Banco receptor'  => (string)$pago['banco_receptor'],
        'Cuenta destino'  => '0102-0000-00-0000000000',
        'Beneficiario'    => 'Condominio Digital',
        'Concepto'        => 'Cuota de condominio - Torre A / A-101',
    ];

    $y = 190;
    $indice = 0;
    foreach ($filas as $etiqueta => $valor) {
        if ($indice % 2 === 0) {
            imagefilledrectangle($img, 30, $y - 8, $ancho - 30, $y + 52, $fondoFila);
        }
        imagestring($img, 3, 45, $y, strtoupper($etiqueta), $gris);
        imagestring($img, 5, 45, $y + 20, $valor, $negro);
        $y += 68;
        $indice++;
    }

    // Píldora de estatus
    imagefilledrectangle($img, 45, $y + 4, 285, $y + 48, $verde);
    imagestring($img, 5, 60, $y + 18, 'OPERACION EXITOSA', $blanco);

    // Pie de página
    imagestring($img, 3, 45, $alto - 70, 'Documento generado con fines de demostracion.', $gris);
    imagestring($img, 3, 45, $alto - 46, 'No constituye un comprobante bancario real.', $gris);

    $ok = imagejpeg($img, $rutaDestino, 90);
    imagedestroy($img);

    return $ok && is_file($rutaDestino) && filesize($rutaDestino) > 0;
}

/**
 * Copia un PDF existente de uploads/soportes como respaldo cuando GD no está
 * disponible. Mantiene la convención de nombre del flujo real (32 hex + .pdf).
 *
 * @param string $rutaDestino Ruta absoluta destino (con extensión .pdf)
 * @return bool true si el archivo quedó copiado
 */
function copiarComprobantePdf(string $rutaDestino): bool
{
    $origenes = glob(UPLOADS_PATH . '/soportes/*.pdf') ?: [];
    if (empty($origenes)) {
        return false;
    }
    return copy($origenes[0], $rutaDestino);
}

// ---------------------------------------------------------------------------
// 1. Eliminar filas previas de este seed (idempotencia por referencia)
// ---------------------------------------------------------------------------
$referencias = array_column($pagosSeed, 'referencia');
$placeholders = implode(', ', array_fill(0, count($referencias), '?'));

$del = $db->prepare("DELETE FROM pagos WHERE residente_id = ? AND referencia IN ({$placeholders})");
$del->execute(array_merge([SEED_RESIDENTE_ID], $referencias));
$eliminados = $del->rowCount();
echo ($eliminados > 0
    ? "  ℹ Filas previas del seed eliminadas: {$eliminados}" . PHP_EOL
    : "  ℹ No había filas previas del seed." . PHP_EOL) . PHP_EOL;

// ---------------------------------------------------------------------------
// 2. Insertar los pagos (y generar sus comprobantes)
// ---------------------------------------------------------------------------
$carpetaComprobantes = UPLOADS_PATH . '/comprobantes';
if (!is_dir($carpetaComprobantes)) {
    mkdir($carpetaComprobantes, 0755, true);
}

$sql = "INSERT INTO pagos
        (residente_id, unidad_id, monto, fecha_pago, metodo_pago, banco_pagador, banco_receptor,
         cuenta_bancaria_id, referencia, referencia_norm, archivo, archivo_hash, observaciones,
         estado, fecha_registro)
        VALUES
        (:residente_id, :unidad_id, :monto, :fecha_pago, :metodo_pago, :banco_pagador, :banco_receptor,
         :cuenta_bancaria_id, :referencia, :referencia_norm, :archivo, :archivo_hash, :observaciones,
         :estado, :fecha_registro)";

$insert = $db->prepare($sql);

$db->beginTransaction();
try {
    foreach ($pagosSeed as $pago) {
        // Referencia normalizada exactamente como lo hace la aplicación
        $refNorm = PagoModel::normalizarReferenciaPago($pago['referencia']);

        // Comprobante opcional (nombre 32 hex + extensión, igual que FileUploader)
        $archivo = '';
        $archivoHash = null;
        if ($pago['con_comprobante']) {
            $nombreJpg = md5('seed_pagos_residente_prueba|' . $pago['referencia']) . '.jpg';
            $ruta = $carpetaComprobantes . '/' . $nombreJpg;

            if (generarComprobanteJpg($ruta, $pago)) {
                $archivo = $nombreJpg;
            } else {
                // Respaldo sin GD: copiar un PDF existente de uploads/soportes
                $nombrePdf = md5('seed_pagos_residente_prueba|' . $pago['referencia']) . '.pdf';
                $ruta = $carpetaComprobantes . '/' . $nombrePdf;
                if (copiarComprobantePdf($ruta)) {
                    $archivo = $nombrePdf;
                } else {
                    echo "  ⚠ No se pudo generar el comprobante para la referencia {$pago['referencia']}; se insertará sin archivo." . PHP_EOL;
                }
            }

            if ($archivo !== '') {
                $archivoHash = hash_file('sha256', $carpetaComprobantes . '/' . $archivo) ?: null;
            }
        }

        $insert->execute([
            'residente_id'       => SEED_RESIDENTE_ID,
            'unidad_id'          => SEED_UNIDAD_ID,
            'monto'              => $pago['monto'],
            'fecha_pago'         => $pago['fecha_pago'],
            'metodo_pago'        => $pago['metodo_pago'],
            'banco_pagador'      => $pago['banco_pagador'],
            'banco_receptor'     => $pago['banco_receptor'],
            'cuenta_bancaria_id' => $pago['cuenta_bancaria_id'],
            'referencia'         => $pago['referencia'],
            'referencia_norm'    => $refNorm,
            'archivo'            => $archivo,
            'archivo_hash'       => $archivoHash,
            'observaciones'      => $pago['observaciones'],
            'estado'             => $pago['estado'],
            'fecha_registro'     => $pago['fecha_registro'],
        ]);

        echo "  ✔ Pago insertado: {$pago['fecha_pago']} | Bs. " . number_format((float)$pago['monto'], 2, ',', '.')
            . " | {$pago['metodo_pago']} | Ref {$pago['referencia']} | {$pago['estado']}"
            . " | comprobante: " . ($archivo !== '' ? $archivo : 'sin archivo') . PHP_EOL;
    }

    $db->commit();
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo PHP_EOL . "❌ Error al insertar los pagos: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// ---------------------------------------------------------------------------
// 3. Verificación: filas resultantes del seed
// ---------------------------------------------------------------------------
echo PHP_EOL . "Pagos del residente " . SEED_RESIDENTE_ID . " en la unidad " . SEED_UNIDAD_ID . ":" . PHP_EOL;
$sel = $db->prepare("SELECT id, estado, monto, metodo_pago, fecha_pago, referencia, archivo
                     FROM pagos
                     WHERE residente_id = :rid AND unidad_id = :uid AND deleted_at IS NULL
                     ORDER BY fecha_registro DESC");
$sel->execute(['rid' => SEED_RESIDENTE_ID, 'uid' => SEED_UNIDAD_ID]);
foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $fila) {
    echo sprintf(
        "  #%d | %-11s | Bs. %8s | %-14s | %s | Ref %-14s | archivo: %s" . PHP_EOL,
        $fila['id'],
        $fila['estado'],
        number_format((float)$fila['monto'], 2, ',', '.'),
        $fila['metodo_pago'],
        $fila['fecha_pago'],
        $fila['referencia'],
        $fila['archivo'] !== '' ? $fila['archivo'] : '(vacío)'
    );
}

echo PHP_EOL . "✅ Seed de pagos de demostración completado." . PHP_EOL;
echo "   La vista /pagos (\"Mis Pagos\") lista estos pagos por unidad_id = " . SEED_UNIDAD_ID . "." . PHP_EOL;
