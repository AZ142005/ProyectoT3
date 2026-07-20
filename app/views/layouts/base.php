<?php
// Carga del encabezado común (meta, styles, CDN Tailwind)
require_once VIEWS_PATH . '/layouts/header.php';

// Carga opcional de la barra de navegación (por defecto se muestra, a menos que se defina lo contrario)
if (!isset($showNav) || $showNav === true) {
    require_once VIEWS_PATH . '/layouts/nav.php';
}
?>

<!-- Contenedor principal de la aplicación -->
<main class="flex-1 flex flex-col">
    <?= $content ?>
</main>

<?php
// Carga del pie de página común (cierre de body/html, footer content)
require_once VIEWS_PATH . '/layouts/footer.php';
?>
