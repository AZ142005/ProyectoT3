<?php
$title = "Página No Encontrada - 404";
require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="flex-1 flex flex-col justify-center items-center py-20 px-4 text-center">
    <span class="material-symbols-outlined text-8xl text-primary mb-4" style="font-size: 96px;">search_off</span>
    <h1 class="text-5xl font-bold text-on-surface mb-2">Error 404</h1>
    <p class="text-on-surface-variant text-lg mb-8 max-w-md">Lo sentimos, la página que buscas no existe o ha sido movida.</p>
    <a href="/" class="bg-primary hover:bg-primary-hover text-white font-bold px-6 py-3 rounded-lg shadow-md transition-all duration-200 active:scale-95 flex items-center gap-2">
        <span class="material-symbols-outlined">home</span>
        Ir al Inicio
    </a>
</div>

<?php
require_once VIEWS_PATH . '/layouts/footer.php';
?>
