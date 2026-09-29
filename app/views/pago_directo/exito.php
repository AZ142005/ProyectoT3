<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-green-50/30 to-slate-100 px-4 py-12">
    <div class="w-full max-w-lg">
        <div class="bg-white rounded-3xl border border-outline-variant shadow-xl shadow-slate-200/50 p-8 md:p-10 text-center">
            <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 text-primary mb-5">
                <span class="material-symbols-outlined" style="font-size:44px;">check_circle</span>
            </div>

            <h1 class="text-2xl font-black text-on-surface mb-3">¡Pago registrado!</h1>
            <p class="text-on-surface-variant leading-relaxed mb-8">
                La administración verificará la información de su pago. Una vez aprobado, se aplicará a las facturas pendientes de la unidad.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="/"
                   class="w-full sm:w-auto bg-primary hover:bg-primary-hover text-white font-bold px-6 py-3 rounded-2xl shadow-lg shadow-primary/20 transition-all duration-300 active:scale-[0.98] flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">home</span>
                    Volver al inicio
                </a>
                <a href="/pago-directo"
                   class="w-full sm:w-auto bg-white hover:bg-slate-50 text-primary font-bold px-6 py-3 rounded-2xl border-2 border-outline-variant hover:border-primary/30 transition-all duration-300 active:scale-[0.98] flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">add_card</span>
                    Reportar otro pago
                </a>
            </div>
        </div>

        <p class="text-center text-sm text-on-surface-variant mt-8">
            Sistema de Gestión de Cobranzas &copy; <?= date('Y') ?>
        </p>
    </div>
</div>
