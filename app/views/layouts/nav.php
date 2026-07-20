<header class="bg-gradient-to-r from-primary to-primary-hover text-white px-8 py-4 flex justify-between items-center shadow-md w-full">
    <div class="logo">
        <h1 class="text-2xl font-bold whitespace-nowrap">Condominio <span class="text-primary-container font-light">Digital</span></h1>
        <small class="block text-xs opacity-80 font-light whitespace-nowrap">Sistema de Cobranzas y Gestión de Pagos</small>
    </div>
    <div class="flex items-center gap-4">
        <?php if (isset($_SESSION['residente_id'])): ?>
            <a href="/residente/dashboard" class="text-sm font-semibold hover:text-primary-container transition-colors">Mi Cuenta</a>
            <a href="/logout" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-bold transition-transform active:scale-95">Salir</a>
        <?php elseif (isset($_SESSION['admin_usuario'])): ?>
            <a href="/admin/dashboard" class="text-sm font-semibold hover:text-primary-container transition-colors">Consola Admin</a>
            <a href="/logout" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-bold transition-transform active:scale-95">Salir</a>
        <?php else: ?>
            <a href="/admin/login" class="bg-white/15 hover:bg-white/25 text-white px-6 py-2 rounded-md text-sm font-semibold border border-white/20 transition-colors">Admin</a>
        <?php endif; ?>
    </div>
</header>
