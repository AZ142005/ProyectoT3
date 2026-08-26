<?php
// $paginacion debe estar definido: ['total', 'pagina', 'totalPaginas', 'porPagina']
// $filtros se preserva para mantener los filtros en la paginación
if (!isset($paginacion) || $paginacion['totalPaginas'] <= 1) return;

$baseUrl = e($_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter([
    'estado'   => $filtros['estado'] ?? '',
    'edificio' => $filtros['edificio'] ?? '',
    'buscar'   => $filtros['buscar'] ?? '',
    'fecha'    => $filtros['fecha'] ?? '',
])));
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
            <a href="<?= $baseUrl . $separator ?>page=<?= e($pagina - 1) ?>" 
               class="px-3 py-1 border rounded">Anterior</a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $pagina - 2); $i <= min($totalPaginas, $pagina + 2); $i++): ?>
            <a href="<?= $baseUrl . $separator ?>page=<?= e($i) ?>" 
               class="px-3 py-1 border rounded <?= $i === $pagina ? 'bg-green-600 text-white' : '' ?>">
                <?= e($i) ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($pagina < $totalPaginas): ?>
            <a href="<?= $baseUrl . $separator ?>page=<?= e($pagina + 1) ?>" 
               class="px-3 py-1 border rounded">Siguiente</a>
        <?php endif; ?>
    </div>
</nav>