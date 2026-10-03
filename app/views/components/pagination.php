<?php
// $paginacion debe estar definido: ['total', 'pagina', 'totalPaginas', 'porPagina']
// $filtros se preserva para mantener los filtros en la paginación
if (!isset($paginacion) || $paginacion['totalPaginas'] <= 1) return;

$pageParamName = $pageParam ?? 'page';
$path = $paginationPath ?? (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ($_SERVER['PHP_SELF'] ?? ''));

$filtrosBase = array_filter([
    'estado'      => $filtros['estado'] ?? '',
    'edificio'    => $filtros['edificio'] ?? '',
    'edificio_id' => $filtros['edificio_id'] ?? '',
    'unidad'      => $filtros['unidad'] ?? '',
    'buscar'      => $filtros['buscar'] ?? '',
    'fecha'       => $filtros['fecha'] ?? '',
    'fecha_desde' => $filtros['fecha_desde'] ?? '',
    'fecha_hasta' => $filtros['fecha_hasta'] ?? '',
    'tab'         => $filtros['tab'] ?? '',
    'rol'         => $filtros['rol'] ?? '',
    'mes'         => $filtros['mes'] ?? '',
    'anio'        => $filtros['anio'] ?? '',
    'categoria_id'=> $filtros['categoria_id'] ?? '',
    'tipo_gasto'  => $filtros['tipo_gasto'] ?? '',
    'lote'        => $filtros['lote'] ?? '',
    'page_facturas' => $filtros['page_facturas'] ?? '',
    'page_comprobantes' => $filtros['page_comprobantes'] ?? '',
]);

if (isset($filtros) && is_array($filtros)) {
    foreach ($filtros as $k => $v) {
        if (!isset($filtrosBase[$k]) && $v !== '' && $v !== null && !is_array($v)) {
            $filtrosBase[$k] = $v;
        }
    }
}

$queryStr = http_build_query($filtrosBase);
$baseUrl = e($path . ($queryStr !== '' ? '?' . $queryStr : ''));
$separator = str_contains($baseUrl, '?') ? '&' : '?';
$pagina = (int) $paginacion['pagina'];
$totalPaginas = (int) $paginacion['totalPaginas'];
$porPagina = (int) $paginacion['porPagina'];
$total = (int) $paginacion['total'];
$desde = ($pagina - 1) * $porPagina + 1;
$hasta = min($pagina * $porPagina, $total);
?>
<nav class="flex items-center justify-between mt-6">
    <span class="text-sm text-gray-600">
        Mostrando <?= e($desde) ?>-<?= e($hasta) ?> de <?= e($total) ?> registros
    </span>
    <div class="flex gap-1">
        <?php if ($pagina > 1): ?>
            <a href="<?= $baseUrl . $separator ?><?= e($pageParamName) ?>=<?= e($pagina - 1) ?>" 
               class="px-3 py-1 border rounded">Anterior</a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $pagina - 2); $i <= min($totalPaginas, $pagina + 2); $i++): ?>
            <a href="<?= $baseUrl . $separator ?><?= e($pageParamName) ?>=<?= e($i) ?>" 
               class="px-3 py-1 border rounded <?= $i === $pagina ? 'bg-green-600 text-white' : '' ?>">
                <?= e($i) ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($pagina < $totalPaginas): ?>
            <a href="<?= $baseUrl . $separator ?><?= e($pageParamName) ?>=<?= e($pagina + 1) ?>" 
               class="px-3 py-1 border rounded">Siguiente</a>
        <?php endif; ?>
    </div>
</nav>