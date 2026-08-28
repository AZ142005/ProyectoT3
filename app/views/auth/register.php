<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-green-50/30 to-slate-100 px-4 py-12">
    <div class="w-full max-w-lg">
        <!-- Logo / Encabezado -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-primary to-primary-hover shadow-lg shadow-primary/20 mb-5">
                <span class="material-symbols-outlined text-white" style="font-size:40px;">person_add</span>
            </div>
            <h1 class="text-3xl font-black text-on-surface tracking-tight">Crear Cuenta</h1>
            <p class="text-on-surface-variant mt-2 text-lg">Regístrate como residente del condominio</p>
        </div>

        <!-- Tarjeta del formulario -->
        <div class="bg-white rounded-3xl border border-outline-variant shadow-xl shadow-slate-200/50 p-8 md:p-10">
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-2xl p-4 mb-6 flex items-start gap-3 text-lg">
                    <span class="material-symbols-outlined text-[22px] shrink-0 mt-0.5">error</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-50 text-green-700 border border-green-200 rounded-2xl p-4 mb-6 flex items-start gap-3 text-lg">
                    <span class="material-symbols-outlined text-[22px] shrink-0 mt-0.5">check_circle</span>
                    <div>
                        <span><?= e($success) ?></span>
                        <a href="/auth/login" class="block mt-2 font-bold text-primary hover:underline text-base">Ir al Login →</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Nota informativa -->
            <div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-2xl p-4 mb-8 flex items-start gap-3">
                <span class="material-symbols-outlined text-[22px] shrink-0 mt-0.5">info</span>
                <p class="text-sm leading-relaxed">Para crear tu cuenta, necesitas la <strong>cédula de identidad</strong> que la administración del condominio tiene registrada. Si no estás registrado, contacta a la administración.</p>
            </div>

            <form method="POST" action="/auth/register" class="flex flex-col gap-6" novalidate>
                <?= csrf_field() ?>

                <!-- Campo Cédula -->
                <?php
                    $postCedulaRaw = normalizarCedula($_POST['cedula'] ?? '');
                    $postTipo = strtoupper($_POST['cedula_tipo'] ?? (in_array(substr($postCedulaRaw, 0, 1), ['V', 'E']) ? substr($postCedulaRaw, 0, 1) : 'V'));
                    $postNum  = $_POST['cedula_numero'] ?? (in_array(substr($postCedulaRaw, 0, 1), ['V', 'E']) ? substr($postCedulaRaw, 1) : $postCedulaRaw);
                ?>
                <div class="flex flex-col gap-2">
                    <label class="text-base font-bold text-on-surface">Cédula de Identidad</label>
                    <div class="flex items-center gap-2">
                        <select name="cedula_tipo" id="cedula_tipo"
                                class="w-24 py-4 text-lg bg-background border-2 border-outline-variant rounded-2xl text-on-surface font-black text-center focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all cursor-pointer shrink-0">
                            <option value="V" <?= $postTipo === 'V' ? 'selected' : '' ?>>V</option>
                            <option value="E" <?= $postTipo === 'E' ? 'selected' : '' ?>>E</option>
                        </select>
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[22px]">badge</span>
                            <input type="text" id="cedula_numero" name="cedula_numero" required autocomplete="off"
                                   inputmode="numeric" pattern="[0-9]{5,8}" minlength="5" maxlength="8"
                                   placeholder="12345678"
                                   value="<?= e($postNum) ?>"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8)"
                                   class="w-full pl-12 pr-5 py-4 text-lg bg-background border-2 border-outline-variant rounded-2xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all tracking-wider font-medium">
                        </div>
                    </div>
                </div>

                <!-- Campo Email -->
                <div class="flex flex-col gap-2">
                    <label for="email" class="text-base font-bold text-on-surface">Correo Electrónico</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[22px]">mail</span>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               placeholder="tu@correo.com"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               class="w-full pl-12 pr-5 py-4 text-lg bg-background border-2 border-outline-variant rounded-2xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all">
                    </div>
                </div>

                <!-- Campo Contraseña -->
                <div class="flex flex-col gap-2">
                    <label for="password" class="text-base font-bold text-on-surface">Contraseña</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[22px]">lock</span>
                        <input type="password" id="password" name="password" required
                               placeholder="Mínimo 6 caracteres"
                               class="w-full pl-12 pr-14 py-4 text-lg bg-background border-2 border-outline-variant rounded-2xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all">
                        <button type="button" onclick="togglePassword('password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/60 hover:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[22px]">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Campo Confirmar Contraseña -->
                <div class="flex flex-col gap-2">
                    <label for="password_confirm" class="text-base font-bold text-on-surface">Confirmar Contraseña</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[22px]">lock_reset</span>
                        <input type="password" id="password_confirm" name="password_confirm" required
                               placeholder="Repite tu contraseña"
                               class="w-full pl-12 pr-14 py-4 text-lg bg-background border-2 border-outline-variant rounded-2xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all">
                        <button type="button" onclick="togglePassword('password_confirm', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/60 hover:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[22px]">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Botón de Registro -->
                <button type="submit"
                        class="w-full bg-gradient-to-r from-primary to-primary-hover hover:from-primary-hover hover:to-primary text-white font-bold text-lg py-4 rounded-2xl shadow-lg shadow-primary/20 transition-all duration-300 active:scale-[0.98] flex items-center justify-center gap-2 mt-2">
                    <span class="material-symbols-outlined">how_to_reg</span>
                    Crear Mi Cuenta
                </button>
            </form>

            <!-- Separador -->
            <div class="flex items-center gap-4 my-8">
                <div class="flex-1 h-px bg-outline-variant"></div>
                <span class="text-sm text-on-surface-variant font-medium">¿Ya tienes cuenta?</span>
                <div class="flex-1 h-px bg-outline-variant"></div>
            </div>

            <!-- Enlace a Login -->
            <a href="/auth/login"
               class="w-full flex items-center justify-center gap-2 bg-background hover:bg-slate-100 text-primary font-bold text-lg py-4 rounded-2xl border-2 border-outline-variant hover:border-primary/30 transition-all duration-300 active:scale-[0.98]">
                <span class="material-symbols-outlined">login</span>
                Iniciar Sesión
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
