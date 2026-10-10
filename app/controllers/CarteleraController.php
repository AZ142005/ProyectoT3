<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Models\CarteleraModel;
use App\Services\CarteleraParserService;
use App\Services\FileUploader;

/**
 * Carga e importación del Reporte de Cartelera (deuda migrada por unidad).
 *
 * Flujo: GET index (subida + previsualización) → POST parsear (PDF → presesión en
 * sesión; JSON si es AJAX) → POST importar (confirmar y persistir el lote).
 */
class CarteleraController extends Controller
{
    /** Tamaño máximo del PDF de cartelera (10 MB). */
    private const MAX_PDF_SIZE = 10485760;

    /**
     * Muestra la interfaz de carga del reporte y la previsualización pendiente.
     */
    public function index()
    {
        Auth::requireRole(['admin', 'auditor']);

        // Descartar la previsualización pendiente a solicitud del usuario
        if (isset($_GET['descartar'])) {
            unset($_SESSION['cartelera_preview'], $_SESSION['cartelera_meta']);
            $this->redirect('/admin/cartelera');
            return;
        }

        $this->render('admin/cartelera/cargar', [
            'filas' => $_SESSION['cartelera_preview'] ?? [],
            'meta'  => $_SESSION['cartelera_meta'] ?? null,
            'layout' => 'admin',
            'title' => 'Carga del Reporte de Cartelera',
        ]);
    }

    /**
     * Sube y parsea el PDF del reporte; guarda la previsualización en sesión.
     */
    public function parsear()
    {
        Auth::requireRole(['admin', 'auditor']);

        $isAjax = $this->isAjax() || !empty($_POST['ajax']);

        if (empty($_FILES['pdf_cartelera']) || $_FILES['pdf_cartelera']['error'] !== UPLOAD_ERR_OK) {
            $this->respuestaParseo($isAjax, false, ['Debe adjuntar el PDF del Reporte de Cartelera.'], null, null);
            return;
        }

        try {
            $uploader = new FileUploader(
                UPLOADS_PATH . '/soportes',
                ['application/pdf'],
                ['pdf'],
                self::MAX_PDF_SIZE
            );
            $nombreArchivo = $uploader->upload($_FILES['pdf_cartelera']);
        } catch (\Throwable $e) {
            error_log('[CARTELERA] Error al procesar el PDF: ' . $e->getMessage());
            $nombreArchivo = false;
        }

        if (!$nombreArchivo) {
            $this->respuestaParseo($isAjax, false, ['Archivo no válido. Solo se aceptan PDFs de hasta 10MB.'], null, null);
            return;
        }

        $rutaCompleta = UPLOADS_PATH . '/soportes/' . $nombreArchivo;
        $resultado = (new CarteleraParserService())->parsear($rutaCompleta);

        if (!$resultado['ok']) {
            $this->respuestaParseo($isAjax, false, $resultado['errores'], $nombreArchivo, null);
            return;
        }

        $hash = @hash_file('sha256', $rutaCompleta);

        $_SESSION['cartelera_preview'] = $resultado['filas'];
        $_SESSION['cartelera_meta'] = [
            'archivo'             => $nombreArchivo,
            'hash'                => $hash !== false ? $hash : null,
            'fecha_emision'       => $resultado['fecha_emision'],
            'anio'                => $resultado['anio'],
            'meses'               => $resultado['meses'],
            'totales'             => $resultado['totales'],
            'conteo_por_edificio' => $resultado['conteo_por_edificio'],
            'paginas'             => $resultado['paginas'],
        ];

        $this->respuestaParseo($isAjax, true, [], $nombreArchivo, $resultado);
    }

    /**
     * Confirma e importa la previsualización guardada en sesión.
     */
    public function importar()
    {
        Auth::requireRole(['admin', 'auditor']);

        $filas = $_SESSION['cartelera_preview'] ?? null;
        $meta = $_SESSION['cartelera_meta'] ?? null;

        if (empty($filas) || empty($meta['fecha_emision'])) {
            Flash::set('danger', 'No hay un reporte previsualizado para importar. Cargue el PDF primero.');
            $this->redirect('/admin/cartelera');
            return;
        }

        try {
            $resultado = (new CarteleraModel())->importarLote(
                $filas,
                (string)$meta['fecha_emision'],
                Auth::id() ?? 1,
                (string)($meta['archivo'] ?? ''),
                $meta['hash'] ?? null
            );

            unset($_SESSION['cartelera_preview'], $_SESSION['cartelera_meta']);

            $mensaje = sprintf(
                'Reporte de Cartelera importado: %d creadas, %d actualizadas, %d omitidas. Edificios creados: %d; unidades creadas: %d.',
                $resultado['facturas_creadas'],
                $resultado['facturas_actualizadas'],
                $resultado['facturas_omitidas'],
                $resultado['edificios_creados'],
                $resultado['unidades_creadas']
            );
            Flash::set($resultado['facturas_creadas'] > 0 ? 'success' : 'info', $mensaje);
        } catch (\Throwable $e) {
            error_log('[CARTELERA] Error al importar el lote: ' . $e->getMessage());
            Flash::set('danger', 'Ocurrió un error al importar el reporte. No se guardó ningún cambio.');
        }

        $this->redirect('/admin/cartelera');
    }

    /**
     * Responde el resultado del parseo (JSON para AJAX; flash + redirect en formulario normal).
     *
     * @param bool        $isAjax    Petición asíncrona.
     * @param bool        $ok        Parseo válido.
     * @param string[]    $errores   Detalle de errores de validación.
     * @param string|null $archivo   Nombre del PDF almacenado.
     * @param array|null  $resultado Salida completa del parser (solo si $ok).
     */
    private function respuestaParseo(bool $isAjax, bool $ok, array $errores, ?string $archivo, ?array $resultado): void
    {
        if ($isAjax) {
            $this->json([
                'success'    => $ok,
                'message'    => $ok
                    ? sprintf('Se detectaron %d unidades con deuda.', count($resultado['filas'] ?? []))
                    : implode(' ', $errores),
                'errores'    => $errores,
                'archivo'    => $archivo,
                'csrf_token' => csrf_token(), // el token rota tras cada POST válido
            ]);
            return;
        }

        if ($ok) {
            Flash::set('info', sprintf(
                'Se detectaron %d unidades con deuda. Revise la previsualización y confirme antes de importar.',
                count($resultado['filas'] ?? [])
            ));
        } else {
            Flash::set('danger', 'No se pudo procesar el PDF: ' . implode(' ', $errores));
        }

        $this->redirect('/admin/cartelera');
    }
}
