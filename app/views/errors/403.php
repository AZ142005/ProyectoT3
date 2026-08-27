<?php
$title = "Acceso Prohibido - 403";
require_once VIEWS_PATH . '/layouts/header.php';
?>

<div class="flex-1 flex flex-col justify-center items-center py-20 px-4 text-center">
    <span class="material-symbols-outlined text-8xl text-red-600 mb-4" style="font-size: 96px;">gpp_maybe</span>
    <h1 class="text-5xl font-bold text-on-surface mb-2">Error 403</h1>
    <p class="text-on-surface-variant text-lg mb-8 max-w-md">Acceso denegado. No dispone de los permisos requeridos para realizar esta acción o la firma de seguridad (Token CSRF) ha expirado.</p>
    <a href="/" class="bg-primary hover:bg-primary-hover text-white font-bold px-6 py-3 rounded-lg shadow-md transition-all duration-200 active:scale-95 flex items-center gap-2">
        <span class="material-symbols-outlined">home</span>
        Volver al Inicio
    </a>
</div>

<?php
require_once VIEWS_PATH . '/layouts/footer.php';
?>
