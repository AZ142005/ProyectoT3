<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-green-50/30 to-slate-100 px-4 py-12">
    <div class="w-full max-w-md">
        <!-- Logo / Encabezado -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-primary to-primary-hover shadow-lg shadow-primary/20 mb-5">
                <span class="material-symbols-outlined text-white" style="font-size:40px;">apartment</span>
            </div>
            <h1 class="text-3xl font-black text-on-surface tracking-tight">Condominio Digital</h1>
            <p class="text-on-surface-variant mt-2 text-lg">Ingresa a tu cuenta</p>
        </div>

        <!-- Tarjeta del formulario -->
        <div class="bg-white rounded-3xl border border-outline-variant shadow-xl shadow-slate-200/50 p-8 md:p-10">
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-2xl p-4 mb-6 flex items-start gap-3 text-lg">
                    <span class="material-symbols-outlined text-[22px] shrink-0 mt-0.5">error</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/auth/login" class="flex flex-col gap-6" novalidate>
                <?= csrf_field() ?>

                <!-- Campo Email -->
                <div class="flex flex-col gap-2">
                    <label for="email" class="text-base font-bold text-on-surface">Correo Electrónico o Cédula</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[22px]">mail</span>
                        <input type="text" id="email" name="email" required autocomplete="username"
                               placeholder="tu@correo.com o V12345678"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               class="w-full pl-12 pr-5 py-4 text-lg bg-background border-2 border-outline-variant rounded-2xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all">
                    </div>
                </div>

                <!-- Campo Contraseña -->
                <div class="flex flex-col gap-2">
                    <label for="password" class="text-base font-bold text-on-surface">Contraseña</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[22px]">lock</span>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               placeholder="••••••••"
                               class="w-full pl-12 pr-14 py-4 text-lg bg-background border-2 border-outline-variant rounded-2xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all">
                        <button type="button" onclick="togglePassword('password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/60 hover:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[22px]">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Botón de Login -->
                <button type="submit"
                        class="w-full bg-gradient-to-r from-primary to-primary-hover hover:from-primary-hover hover:to-primary text-white font-bold text-lg py-4 rounded-2xl shadow-lg shadow-primary/20 transition-all duration-300 active:scale-[0.98] flex items-center justify-center gap-2 mt-2">
                    <span class="material-symbols-outlined">login</span>
                    Iniciar Sesión
                </button>
            </form>

            <!-- Separador -->
            <div class="flex items-center gap-4 my-8">
                <div class="flex-1 h-px bg-outline-variant"></div>
                <span class="text-sm text-on-surface-variant font-medium">¿Eres nuevo?</span>
                <div class="flex-1 h-px bg-outline-variant"></div>
            </div>

            <!-- Enlace a Registro -->
            <a href="/auth/register"
               class="w-full flex items-center justify-center gap-2 bg-background hover:bg-slate-100 text-primary font-bold text-lg py-4 rounded-2xl border-2 border-outline-variant hover:border-primary/30 transition-all duration-300 active:scale-[0.98]">
                <span class="material-symbols-outlined">person_add</span>
                Crear Cuenta de Residente
            </a>
        </div>

        <p class="text-center text-sm text-on-surface-variant mt-8">
            Sistema de Gestión de Cobranzas &copy; <?= date('Y') ?>
        </p>
    </div>
</div>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('.material-symbols-outlined');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility';
    }
}
</script>
