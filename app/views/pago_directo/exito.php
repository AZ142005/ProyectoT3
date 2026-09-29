<div class="max-w-3xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado de la página -->
    <div class="bg-gradient-to-r from-primary to-primary-hover text-white rounded-2xl p-6 mb-8 shadow-md flex justify-between items-center flex-wrap gap-4 border-b-4 border-institutional-brown">
        <div>
            <h2 class="text-2xl font-bold">Pago registrado</h2>
            <p class="text-sm opacity-90 mt-1">Comprobante recibido</p>
        </div>
    </div>

    <!-- Mensajes de Alerta -->
    <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

    <div class="bg-white rounded-2xl border border-outline-variant shadow-sm p-8 text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 text-primary mb-5">
            <span class="material-symbols-outlined" style="font-size:44px;">check_circle</span>
        </div>

        <h1 class="text-2xl font-black text-on-surface mb-3">¡Pago registrado!</h1>
        <p class="text-on-surface-variant leading-relaxed mb-8">
            La administración verificará la información de su pago. Una vez aprobado, se aplicará a las facturas pendientes de la unidad.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="/"
               class="w-full sm:w-auto bg-primary hover:bg-primary-hover text-white font-bold px-6 py-3 rounded-xl shadow-md transition-all duration-200 active:scale-95 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">home</span>
                Volver al inicio
            </a>
            <a href="/pago-directo"
               class="w-full sm:w-auto bg-background hover:bg-slate-100 text-primary font-bold px-6 py-3 rounded-xl border-2 border-outline-variant hover:border-primary/30 transition-all duration-300 active:scale-[0.98] flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">add_card</span>
                Reportar otro pago
            </a>
        </div>
    </div>

    <p class="text-center text-sm text-on-surface-variant mt-8">
        Sistema de Gestión de Cobranzas &copy; <?= date('Y') ?>
    </p>
</div>
