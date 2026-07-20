<?php
// Carga del encabezado común (meta, styles, CDN Tailwind)
require_once VIEWS_PATH . '/layouts/header.php';
?>

<!-- Contenedor principal de la aplicación -->
<main class="flex-1 flex flex-col">
    <?= $content ?>
</main>

<?php
// Carga del pie de página común (cierre de body/html, footer content)
require_once VIEWS_PATH . '/layouts/footer.php';
?>
