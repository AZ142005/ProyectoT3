<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-green-50/30 to-slate-100 px-4 py-12">
    <div class="w-full max-w-2xl">
        <!-- Logo / Encabezado -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-primary to-primary-hover shadow-lg shadow-primary/20 mb-4">
                <span class="material-symbols-outlined text-white" style="font-size:40px;">person_add</span>
            </div>
            <h1 class="text-3xl font-black text-on-surface tracking-tight">Solicitud de Registro</h1>
            <p class="text-on-surface-variant mt-2 text-base">Completa tus datos para solicitar acceso como residente</p>
        </div>

        <!-- Tarjeta del formulario -->
        <div class="bg-white rounded-3xl border border-outline-variant shadow-xl shadow-slate-200/50 p-6 md:p-10">
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-2xl p-4 mb-6 flex items-start gap-3 text-base">
                    <span class="material-symbols-outlined text-[22px] shrink-0 mt-0.5">error</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>


            <!-- Nota informativa de flujo con aprobación -->
            <div class="bg-blue-50 text-blue-800 border border-blue-200 rounded-2xl p-4 mb-6 flex items-start gap-3">
                <span class="material-symbols-outlined text-[22px] shrink-0 mt-0.5 text-blue-600">verified_user</span>
                <div class="text-sm leading-relaxed">
                    <strong>Proceso de Verificación Administrativa:</strong> Al enviar este formulario, tu registro quedará en estado <em>Pendiente de Aprobación</em>. La junta o administrador validará tu identidad y la asignación del apartamento antes de habilitar el acceso.
                </div>
            </div>

            <form id="formRegistro" method="POST" action="/auth/register" class="flex flex-col gap-5" novalidate>
                <?= csrf_field() ?>

                <!-- Fila: Nombre y Apellido -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="nombre" class="text-sm font-bold text-on-surface">Nombre *</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[20px]">badge</span>
                            <input type="text" id="nombre" name="nombre" required autocomplete="given-name"
                                   placeholder="Ej. Carlos"
                                   value="<?= e($_POST['nombre'] ?? '') ?>"
                                   class="w-full pl-11 pr-4 py-3 bg-background border-2 border-outline-variant rounded-xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-medium">
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="apellido" class="text-sm font-bold text-on-surface">Apellido *</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[20px]">badge</span>
                            <input type="text" id="apellido" name="apellido" required autocomplete="family-name"
                                   placeholder="Ej. Pérez"
                                   value="<?= e($_POST['apellido'] ?? '') ?>"
                                   class="w-full pl-11 pr-4 py-3 bg-background border-2 border-outline-variant rounded-xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-medium">
                        </div>
                    </div>
                </div>

                <!-- Fila: Cédula de Identidad y Número de Residentes -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php
                        $postCedulaRaw = normalizarCedula($_POST['cedula'] ?? '');
                        $postTipo = strtoupper($_POST['cedula_tipo'] ?? (in_array(substr($postCedulaRaw, 0, 1), ['V', 'E']) ? substr($postCedulaRaw, 0, 1) : 'V'));
                        $postNum  = $_POST['cedula_numero'] ?? (in_array(substr($postCedulaRaw, 0, 1), ['V', 'E']) ? substr($postCedulaRaw, 1) : $postCedulaRaw);
                    ?>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-bold text-on-surface">Cédula de Identidad *</label>
                        <div class="flex items-center gap-2">
                            <select name="cedula_tipo" id="cedula_tipo"
                                    class="w-20 py-3 text-base bg-background border-2 border-outline-variant rounded-xl text-on-surface font-black text-center focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all cursor-pointer shrink-0">
                                <option value="V" <?= $postTipo === 'V' ? 'selected' : '' ?>>V</option>
                                <option value="E" <?= $postTipo === 'E' ? 'selected' : '' ?>>E</option>
                            </select>
                            <div class="relative flex-1">
                                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[20px]">id_card</span>
                                <input type="text" id="cedula_numero" name="cedula_numero" required autocomplete="off"
                                       inputmode="numeric" pattern="[0-9]{5,8}" minlength="5" maxlength="8"
                                       placeholder="12345678"
                                       value="<?= e($postNum) ?>"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8)"
                                       class="w-full pl-11 pr-4 py-3 bg-background border-2 border-outline-variant rounded-xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all tracking-wider font-medium">
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="numero_residentes" class="text-sm font-bold text-on-surface">Nro. de Habitantes en el Hogar *</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[20px]">groups</span>
                            <input type="number" id="numero_residentes" name="numero_residentes" required
                                   min="1" max="20" step="1"
                                   placeholder="1"
                                   value="<?= e($_POST['numero_residentes'] ?? '1') ?>"
                                   class="w-full pl-11 pr-4 py-3 bg-background border-2 border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-medium">
                        </div>
                    </div>
                </div>

                <!-- Fila: Teléfono de Contacto -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-bold text-on-surface">Número Telefónico *</label>
                    <div class="flex items-center gap-2">
                        <?php
                            $telCodigo = $_POST['telefono_codigo'] ?? '0412';
                            $telNumero = $_POST['telefono_numero'] ?? '';
                        ?>
                        <select name="telefono_codigo" id="telefono_codigo"
                                class="w-28 py-3 text-base bg-background border-2 border-outline-variant rounded-xl text-on-surface font-bold text-center focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all cursor-pointer shrink-0">
                            <option value="0412" <?= $telCodigo === '0412' ? 'selected' : '' ?>>0412</option>
                            <option value="0414" <?= $telCodigo === '0414' ? 'selected' : '' ?>>0414</option>
                            <option value="0424" <?= $telCodigo === '0424' ? 'selected' : '' ?>>0424</option>
                            <option value="0416" <?= $telCodigo === '0416' ? 'selected' : '' ?>>0416</option>
                            <option value="0426" <?= $telCodigo === '0426' ? 'selected' : '' ?>>0426</option>
                        </select>
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[20px]">call</span>
                            <input type="tel" id="telefono_numero" name="telefono_numero" required
                                   inputmode="numeric" pattern="[0-9]{7}" minlength="7" maxlength="7"
                                   placeholder="1234567"
                                   value="<?= e($telNumero) ?>"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 7)"
                                   class="w-full pl-11 pr-4 py-3 bg-background border-2 border-outline-variant rounded-xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-medium tracking-wider">
                        </div>
                    </div>
                </div>

                <!-- Selector Dinámico de Apartamentos Disponibles -->
                <div class="flex flex-col gap-1.5">
                    <label for="unidad_id" class="text-sm font-bold text-on-surface">Apartamento / Unidad *</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[20px]">apartment</span>
                        <select id="unidad_id" name="unidad_id" required
                                class="w-full pl-11 pr-10 py-3 bg-background border-2 border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-medium appearance-none cursor-pointer">
                            <option value="">-- Selecciona el apartamento que vas a habitar --</option>
                            <?php if (!empty($apartamentosDisponibles)): ?>
                                <?php foreach ($apartamentosDisponibles as $apto): ?>
                                    <option value="<?= e($apto['id']) ?>" <?= ((int)($_POST['unidad_id'] ?? 0) === (int)$apto['id']) ? 'selected' : '' ?>>
                                        <?= e($apto['edificio_nombre']) ?> — Apto. <?= e($apto['numero']) ?><?= !empty($apto['cuota_mensual']) ? ' · Cuota: $' . e(number_format((float)$apto['cuota_mensual'], 2)) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No hay apartamentos disponibles actualmente para registro</option>
                            <?php endif; ?>
                        </select>
                        <span class="material-symbols-outlined absolute right-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 pointer-events-none text-[20px]">expand_more</span>
                    </div>
                    <?php if (empty($apartamentosDisponibles)): ?>
                        <p class="text-xs text-amber-700 mt-1 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">warning</span>
                            Todos los apartamentos se encuentran ocupados o con solicitudes en trámite. Contacta a administración.
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-on-surface-variant/80 mt-0.5">Solo se muestran los inmuebles vacantes sin solicitudes previas en curso.</p>
                    <?php endif; ?>
                </div>

                <!-- Campo Email -->
                <div class="flex flex-col gap-1.5">
                    <label for="email" class="text-sm font-bold text-on-surface">Correo Electrónico *</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[20px]">mail</span>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               placeholder="tu@correo.com"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               class="w-full pl-11 pr-4 py-3 bg-background border-2 border-outline-variant rounded-xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-medium">
                    </div>
                </div>

                <!-- Fila: Contraseñas -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-sm font-bold text-on-surface">Contraseña *</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[20px]">lock</span>
                            <input type="password" id="password" name="password" required
                                   placeholder="Mín. 8 car. (letras y núm.)" minlength="8"
                                   class="w-full pl-11 pr-11 py-3 bg-background border-2 border-outline-variant rounded-xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-medium">
                            <button type="button" onclick="togglePassword('password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/60 hover:text-primary transition-colors">
                                <span class="material-symbols-outlined text-[20px]">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password_confirm" class="text-sm font-bold text-on-surface">Confirmar Contraseña *</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 text-[20px]">lock_reset</span>
                            <input type="password" id="password_confirm" name="password_confirm" required
                                   placeholder="Repite la contraseña" minlength="8"
                                   class="w-full pl-11 pr-11 py-3 bg-background border-2 border-outline-variant rounded-xl text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-medium">
                            <button type="button" onclick="togglePassword('password_confirm', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/60 hover:text-primary transition-colors">
                                <span class="material-symbols-outlined text-[20px]">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Panel Responsive: Cumplimiento de Seguridad y Coincidencia en Tiempo Real -->
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col gap-3 transition-all" id="password_feedback_panel">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-primary text-[18px]">verified_user</span>
                            Requisitos de Seguridad
                        </span>
                        <span id="password_strength_badge" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-600 transition-all">
                            <span class="material-symbols-outlined text-[14px]" id="password_strength_icon">info</span>
                            <span id="password_strength_text">Pendiente</span>
                        </span>
                    </div>

                    <!-- Checklist de requisitos mínimos: responsive (1 col en móvil estrecho, 3 cols en tablet/desktop) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-2 border-t border-slate-200/80">
                        <div id="rule_length" class="flex items-center gap-1.5 text-xs text-slate-500 transition-colors">
                            <span class="material-symbols-outlined text-[16px] text-slate-400 rule-icon">radio_button_unchecked</span>
                            <span>Mín. 8 caracteres</span>
                        </div>
                        <div id="rule_letter" class="flex items-center gap-1.5 text-xs text-slate-500 transition-colors">
                            <span class="material-symbols-outlined text-[16px] text-slate-400 rule-icon">radio_button_unchecked</span>
                            <span>Al menos 1 letra</span>
                        </div>
                        <div id="rule_number" class="flex items-center gap-1.5 text-xs text-slate-500 transition-colors">
                            <span class="material-symbols-outlined text-[16px] text-slate-400 rule-icon">radio_button_unchecked</span>
                            <span>Al menos 1 número</span>
                        </div>
                    </div>

                    <!-- Coincidencia de Contraseñas Responsive -->
                    <div class="pt-2 border-t border-slate-200/80 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5 text-xs text-slate-600">
                            <span class="material-symbols-outlined text-[16px] text-slate-500">lock_reset</span>
                            <span class="font-medium">Coincidencia de clave y confirmación:</span>
                        </div>
                        <span id="password_match_badge" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-600 transition-all" aria-live="polite">
                            <span class="material-symbols-outlined text-[14px]" id="password_match_icon">pending</span>
                            <span id="password_match_text">Esperando confirmación</span>
                        </span>
                    </div>
                </div>

                <!-- Botón de Envío -->
                <button type="submit"
                        class="w-full bg-gradient-to-r from-primary to-primary-hover hover:from-primary-hover hover:to-primary text-white font-bold text-base py-3.5 rounded-xl shadow-lg shadow-primary/20 transition-all duration-300 active:scale-[0.98] flex items-center justify-center gap-2 mt-3">
                    <span class="material-symbols-outlined">send</span>
                    Enviar Solicitud de Registro
                </button>
            </form>

            <!-- Separador -->
            <div class="flex items-center gap-4 my-6">
                <div class="flex-1 h-px bg-outline-variant"></div>
                <span class="text-xs text-on-surface-variant font-medium">¿Ya posees una cuenta aprobada?</span>
                <div class="flex-1 h-px bg-outline-variant"></div>
            </div>

            <!-- Enlace a Login -->
            <a href="/auth/login"
               class="w-full flex items-center justify-center gap-2 bg-background hover:bg-slate-100 text-primary font-bold text-base py-3 rounded-xl border-2 border-outline-variant hover:border-primary/30 transition-all duration-300 active:scale-[0.98]">
                <span class="material-symbols-outlined">login</span>
                Iniciar Sesión
            </a>
        </div>

        <p class="text-center text-xs text-on-surface-variant mt-6">
            Sistema de Gestión de Cobranzas &copy; <?= date('Y') ?>
        </p>
    </div>
</div>

<style>
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
    display: none !important;
}
</style>
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

document.addEventListener('DOMContentLoaded', function () {
    const passInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirm');
    const ruleLength = document.getElementById('rule_length');
    const ruleLetter = document.getElementById('rule_letter');
    const ruleNumber = document.getElementById('rule_number');
    const strengthBadge = document.getElementById('password_strength_badge');
    const strengthIcon = document.getElementById('password_strength_icon');
    const strengthText = document.getElementById('password_strength_text');
    const matchBadge = document.getElementById('password_match_badge');
    const matchIcon = document.getElementById('password_match_icon');
    const matchText = document.getElementById('password_match_text');
    const form = document.getElementById('formRegistro');

    function updateRule(element, isValid, isTyping) {
        if (!element) return;
        const icon = element.querySelector('.rule-icon');
        if (!isTyping) {
            element.className = 'flex items-center gap-1.5 text-xs text-slate-500 transition-colors';
            if (icon) {
                icon.textContent = 'radio_button_unchecked';
                icon.className = 'material-symbols-outlined text-[16px] text-slate-400 rule-icon';
            }
        } else if (isValid) {
            element.className = 'flex items-center gap-1.5 text-xs text-emerald-700 font-semibold transition-colors';
            if (icon) {
                icon.textContent = 'check_circle';
                icon.className = 'material-symbols-outlined text-[16px] text-emerald-600 rule-icon';
            }
        } else {
            element.className = 'flex items-center gap-1.5 text-xs text-rose-600 font-medium transition-colors';
            if (icon) {
                icon.textContent = 'cancel';
                icon.className = 'material-symbols-outlined text-[16px] text-rose-500 rule-icon';
            }
        }
    }

    function updateInputBorders(input, state) {
        if (!input) return;
        input.classList.remove('border-emerald-500', 'border-rose-500', 'border-amber-400', 'border-outline-variant');
        if (state === 'valid') {
            input.classList.add('border-emerald-500');
        } else if (state === 'invalid') {
            input.classList.add('border-rose-500');
        } else if (state === 'warning') {
            input.classList.add('border-amber-400');
        } else {
            input.classList.add('border-outline-variant');
        }
    }

    function validate() {
        const pass = passInput ? passInput.value : '';
        const confirm = confirmInput ? confirmInput.value : '';
        const isTypingPass = pass.length > 0;
        const isTypingConfirm = confirm.length > 0;

        // Reglas de seguridad: mín 8 caracteres, al menos 1 letra, al menos 1 número
        const okLength = pass.length >= 8;
        const okLetter = /[a-zA-Z]/.test(pass);
        const okNumber = /[0-9]/.test(pass);
        const totalOk = (okLength ? 1 : 0) + (okLetter ? 1 : 0) + (okNumber ? 1 : 0);
        const isSecurityValid = (totalOk === 3);

        // Actualizar checklist visual
        updateRule(ruleLength, okLength, isTypingPass);
        updateRule(ruleLetter, okLetter, isTypingPass);
        updateRule(ruleNumber, okNumber, isTypingPass);

        // Actualizar badge de seguridad y borde del input principal
        if (!isTypingPass) {
            strengthBadge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-600 transition-all';
            strengthIcon.textContent = 'info';
            strengthText.textContent = 'Pendiente';
            updateInputBorders(passInput, 'default');
        } else if (isSecurityValid) {
            strengthBadge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300 transition-all';
            strengthIcon.textContent = 'check_circle';
            strengthText.textContent = 'Segura';
            updateInputBorders(passInput, 'valid');
        } else {
            strengthBadge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300 transition-all';
            strengthIcon.textContent = 'warning';
            strengthText.textContent = 'Incompleta (' + totalOk + '/3)';
            updateInputBorders(passInput, 'warning');
        }

        // Validar coincidencia de clave con confirmación
        if (!isTypingConfirm) {
            matchBadge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-600 transition-all';
            matchIcon.textContent = 'pending';
            matchText.textContent = 'Esperando confirmación';
            updateInputBorders(confirmInput, 'default');
        } else if (pass === confirm) {
            matchBadge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300 transition-all';
            matchIcon.textContent = 'check_circle';
            matchText.textContent = '¡Coinciden!';
            updateInputBorders(confirmInput, 'valid');
        } else {
            matchBadge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-300 transition-all';
            matchIcon.textContent = 'cancel';
            matchText.textContent = 'No coinciden';
            updateInputBorders(confirmInput, 'invalid');
        }

        return {
            isSecurityValid,
            isMatch: isTypingConfirm && pass === confirm
        };
    }

    if (passInput) {
        passInput.addEventListener('input', validate);
    }
    if (confirmInput) {
        confirmInput.addEventListener('input', validate);
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            const { isSecurityValid, isMatch } = validate();
            const passVal = passInput ? passInput.value : '';
            const confirmVal = confirmInput ? confirmInput.value : '';

            if (!isSecurityValid) {
                e.preventDefault();
                if (passInput) {
                    passInput.focus();
                    passInput.classList.add('ring-4', 'ring-rose-500/20');
                    setTimeout(() => passInput.classList.remove('ring-4', 'ring-rose-500/20'), 1500);
                }
                return false;
            }

            if (!isMatch || passVal !== confirmVal) {
                e.preventDefault();
                if (confirmInput) {
                    confirmInput.focus();
                    confirmInput.classList.add('ring-4', 'ring-rose-500/20');
                    setTimeout(() => confirmInput.classList.remove('ring-4', 'ring-rose-500/20'), 1500);
                }
                return false;
            }
        });
    }
});
</script>
