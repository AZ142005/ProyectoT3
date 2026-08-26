<div class="min-h-screen flex items-center justify-center bg-background px-4 py-12">
    <div class="max-w-md w-full bg-white rounded-3xl border border-outline-variant p-8 shadow-xl">
        <!-- Logo / Icono -->
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl mx-auto flex items-center justify-center mb-3">
                <span class="material-symbols-outlined text-4xl">shield_lock</span>
            </div>
            <h1 class="text-2xl font-bold text-on-surface">Verificación en Dos Pasos</h1>
            <p class="text-xs text-slate-500 mt-1">Protección de identidad y seguridad de su cuenta</p>
        </div>

        <!-- Mensajes Flash -->
        <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 mb-6 text-xs text-emerald-900">
            <p class="mb-1 font-bold flex items-center gap-1">
                <span class="material-symbols-outlined text-sm text-emerald-700">mark_email_read</span>
                Código de 6 dígitos enviado
            </p>
            <p class="mb-0 text-slate-600">
                Hemos enviado un código de verificación a <strong><?= e($email) ?></strong>. Ingréselo a continuación:
            </p>
        </div>

        <!-- Formulario de ingreso de OTP -->
        <form method="POST" action="/auth/verificar-2fa" class="space-y-5">
            <?= csrf_field() ?>

            <div>
                <label for="codigo_otp" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2 text-center">
                    Código de Verificación (6 Dígitos)
                </label>
                <input 
                    type="text" 
                    id="codigo_otp" 
                    name="codigo_otp" 
                    required 
                    maxlength="6" 
                    pattern="[0-9]{6}" 
                    inputmode="numeric" 
                    autocomplete="one-time-code"
                    placeholder="000000"
                    class="w-full text-center text-3xl font-mono tracking-widest font-bold py-3.5 px-4 bg-slate-50 border border-slate-300 rounded-2xl focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
                    autofocus
                >
                <span class="text-[11px] text-slate-400 block text-center mt-1.5">⏱️ Expira en 5 minutos. Máximo 3 intentos.</span>
            </div>

            <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white font-bold py-3.5 rounded-2xl shadow-md transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-xl">check_circle</span>
                Verificar y Acceder
            </button>
        </form>

        <!-- Reenviar Código y Cancelar -->
        <div class="mt-6 pt-6 border-t border-slate-100 flex items-center justify-between text-xs">
            <form method="POST" action="/auth/reenviar-otp" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="text-primary hover:underline font-bold inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">refresh</span> Reenviar Código
                </button>
            </form>

            <a href="/auth/login" class="text-slate-400 hover:text-slate-600">
                Cancelar y Salir
            </a>
        </div>
    </div>
</div>
