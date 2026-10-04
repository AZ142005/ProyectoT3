<?php
$role = \App\Core\Auth::role();
$isAdmin = ($role === 'admin');
$isAuditor = ($role === 'auditor');
$twoFaEnabled = !empty($usuarioAdmin['two_factor_enabled'] ?? $persona['two_factor_enabled'] ?? null);
?>
<?php if ($isAdmin || $isAuditor): ?>
<div class="flex flex-1 min-h-screen w-full">
    <?php 
    $activeRoute = 'perfil'; 
    if ($isAuditor) {
        require VIEWS_PATH . '/layouts/auditor_sidebar.php';
    } else {
        require VIEWS_PATH . '/layouts/admin_sidebar.php';
    }
    ?>
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="text-xl font-bold text-on-surface">Mi Perfil de Usuario</h1>
            </div>
            <a href="<?= $isAuditor ? '/auth/logout' : '/admin/logout' ?>" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="max-w-4xl mx-auto">
                <!-- Mensajes Flash -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>
<?php else: ?>
<div class="max-w-4xl mx-auto px-4 py-8 flex-1 w-full">
    <!-- Encabezado Residente -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-2xl font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">account_circle</span>
                Mi Perfil de Usuario
            </h2>
            <p class="text-sm text-on-surface-variant mt-1">Consulte su información registrada y solicite actualizaciones de datos.</p>
        </div>
        <a href="/residente/dashboard" class="bg-slate-100 hover:bg-slate-200 text-on-surface text-xs font-bold px-4 py-2.5 rounded-xl transition-colors inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">arrow_back</span> Volver al Panel
        </a>
    </div>

    <!-- Mensajes Flash -->
    <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>
<?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Tarjeta de Datos Actuales -->
        <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm md:col-span-1">
            <div class="flex flex-col items-center text-center border-b border-background pb-6 mb-6">
                <div class="w-20 h-20 bg-primary/10 text-primary rounded-full flex items-center justify-center font-bold text-3xl mb-3">
                    <?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?>
                </div>
                <h3 class="font-bold text-on-surface text-lg"><?= e($persona ? ($persona['nombre'] . ' ' . $persona['apellido']) : $user['name']) ?></h3>
                <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-full uppercase mt-1">
                    <?= e(ucfirst($user['role'] ?? 'residente')) ?>
                </span>
            </div>

            <div class="flex flex-col gap-4 text-sm">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase d-block">Cédula de Identidad</span>
                    <span class="font-semibold text-on-surface"><?= e(($isAdmin || $isAuditor) ? (!empty($usuarioAdmin['cedula']) ? $usuarioAdmin['cedula'] : 'No registrada') : (!empty($persona['cedula']) ? $persona['cedula'] : 'N/A')) ?></span>
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase d-block">Correo Electrónico</span>
                    <span class="font-semibold text-on-surface"><?= e(($isAdmin || $isAuditor) ? (!empty($usuarioAdmin['email']) ? $usuarioAdmin['email'] : ($user['email'] ?? '')) : (!empty($persona['email']) ? $persona['email'] : ($user['email'] ?? ''))) ?></span>
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase d-block">Teléfono Móvil</span>
                    <span class="font-semibold text-on-surface"><?= e(($isAdmin || $isAuditor) ? (!empty($usuarioAdmin['telefono']) ? $usuarioAdmin['telefono'] : 'No registrado') : (!empty($persona['telefono']) ? $persona['telefono'] : 'No registrado')) ?></span>
                </div>
            </div>
        </div>

        <?php if ($isAdmin): ?>
            <!-- Formulario para Administrador: Actualización Directa -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm md:col-span-2">
                <h3 class="font-bold text-on-surface text-base mb-1 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">manage_accounts</span>
                    Actualizar Datos del Administrador
                </h3>
                <p class="text-xs text-on-surface-variant mb-6">Actualice su nombre institucional, cédula, teléfono, correo electrónico o contraseña de acceso.</p>

                <form method="POST" action="/perfil/solicitar-cambio" class="flex flex-col gap-4">
                    <?= csrf_field() ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide d-block mb-1">Nombre Completo</label>
                            <input type="text" name="nombre" value="<?= e($usuarioAdmin['nombre_completo'] ?? $user['name'] ?? '') ?>" required
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-outline-variant rounded-xl text-sm focus:bg-white focus:border-primary focus:outline-none">
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide d-block mb-1">Correo Electrónico</label>
                            <input type="email" name="email" value="<?= e($usuarioAdmin['email'] ?? $user['email'] ?? '') ?>" required
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-outline-variant rounded-xl text-sm focus:bg-white focus:border-primary focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide d-block mb-1">Número de Cédula</label>
                            <input type="text" name="cedula" id="admin_cedula" value="<?= e($usuarioAdmin['cedula'] ?? '') ?>" placeholder="Ej: V-12345678"
                                   pattern="^[VEJPGvejpg]?-?[0-9]{5,8}$"
                                   title="Ingrese una cédula válida (ej: V-12345678 o 12345678)"
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-outline-variant rounded-xl text-sm focus:bg-white focus:border-primary focus:outline-none">
                            <small class="text-slate-400 text-xs mt-1 block">Opcional. Formato: V-12345678 o 5 a 8 dígitos.</small>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide d-block mb-1">Número Telefónico</label>
                            <input type="tel" name="telefono" id="admin_telefono" value="<?= e($usuarioAdmin['telefono'] ?? '') ?>" placeholder="Ej: 04121234567" maxlength="11"
                                   pattern="^(0412|0414|0424|0416|0426)[0-9]{7}$"
                                   title="Ingrese un número telefónico venezolano de 11 dígitos (ej: 04121234567)"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-outline-variant rounded-xl text-sm focus:bg-white focus:border-primary focus:outline-none">
                            <small class="text-slate-400 text-xs mt-1 block">Opcional. 11 dígitos (0412, 0414, 0424, 0416, 0426).</small>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wide d-block mb-1">Nueva Contraseña (Opcional)</label>
                        <div class="relative">
                            <input type="password" id="admin_password" name="password" minlength="8" placeholder="Mínimo 8 caracteres (letras y números), o dejar en blanco"
                                   class="w-full pl-4 pr-11 py-2.5 bg-slate-50 border border-outline-variant rounded-xl text-sm focus:bg-white focus:border-primary focus:outline-none">
                            <button type="button" id="btnTogglePassword" onclick="togglePassword('admin_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary transition-colors flex items-center justify-center p-1" title="Mostrar/Ocultar contraseña">
                                <span id="iconTogglePassword" class="material-symbols-outlined text-[20px]">visibility</span>
                            </button>
                        </div>
                        <small class="text-slate-400 text-xs mt-1 block">Debe contener al menos 8 caracteres con letras y números.</small>
                    </div>

                    <div class="flex justify-end mt-2">
                        <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            <span>Guardar Cambios</span>
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($isAuditor): ?>
            <!-- Información para Auditor -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm md:col-span-2 flex flex-col justify-center">
                <div class="p-6 bg-slate-50 rounded-xl border border-slate-200 text-center">
                    <span class="material-symbols-outlined text-4xl text-slate-400 mb-2">policy</span>
                    <h4 class="font-bold text-dark text-base mb-1">Perfil de Auditoría y Fiscalización</h4>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">Este perfil cuenta con facultades de fiscalización y gestión sobre los libros y transacciones del condominio.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Formulario de Solicitud de Cambio para Residentes -->
            <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm md:col-span-2">
                <h3 class="font-bold text-on-surface text-base mb-1 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">edit_note</span>
                    Solicitar Actualización de Datos
                </h3>
                <p class="text-xs text-on-surface-variant mb-3">Los cambios serán revisados y aprobados por la administración antes de ser aplicados.</p>

                <div class="flex items-start gap-2 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-xl px-3 py-2.5 mb-6">
                    <span class="material-symbols-outlined text-[16px]">schedule</span>
                    <span>Solo se conservan las solicitudes aprobadas: las pendientes se eliminan 24 horas después de su envío y las rechazadas 24 horas después del rechazo.</span>
                </div>

                <form method="POST" action="/perfil/solicitar-cambio" class="flex flex-col gap-4">
                    <?= csrf_field() ?>

                    <?php
                        $telRaw = preg_replace('/[^0-9]/', '', $persona['telefono'] ?? '');
                        $telPrefix = strlen($telRaw) >= 4 ? substr($telRaw, 0, 4) : '0412';
                        $telNum = strlen($telRaw) >= 11 ? substr($telRaw, 4, 7) : (strlen($telRaw) > 4 ? substr($telRaw, 4) : '');
                    ?>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wide d-block mb-1">Nuevo Teléfono Móvil</label>
                        <div class="flex items-center gap-2">
                            <select name="telefono_codigo" id="perfil_telefono_codigo"
                                    class="w-28 px-3 py-2.5 bg-slate-50 border border-outline-variant rounded-xl text-sm font-semibold focus:bg-white focus:border-primary focus:outline-none cursor-pointer shrink-0">
                                <option value="0412" <?= $telPrefix === '0412' ? 'selected' : '' ?>>0412</option>
                                <option value="0422" <?= $telPrefix === '0422' ? 'selected' : '' ?>>0422</option>
                                <option value="0414" <?= $telPrefix === '0414' ? 'selected' : '' ?>>0414</option>
                                <option value="0424" <?= $telPrefix === '0424' ? 'selected' : '' ?>>0424</option>
                                <option value="0416" <?= $telPrefix === '0416' ? 'selected' : '' ?>>0416</option>
                                <option value="0426" <?= $telPrefix === '0426' ? 'selected' : '' ?>>0426</option>
                            </select>
                            <input type="text" name="telefono_numero" id="perfil_telefono_numero" maxlength="7" placeholder="1234567"
                                   value="<?= e($telNum) ?>"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 7)"
                                   class="w-full px-4 py-2.5 bg-slate-50 border border-outline-variant rounded-xl text-sm focus:bg-white focus:border-primary focus:outline-none tracking-wider">
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wide d-block mb-1">Nuevo Correo Electrónico</label>
                        <input type="email" name="email" value="<?= e($persona['email'] ?? $user['email']) ?>" placeholder="usuario@ejemplo.com"
                               class="w-full px-4 py-2.5 bg-slate-50 border border-outline-variant rounded-xl text-sm focus:bg-white focus:border-primary focus:outline-none">
                    </div>

                    <div class="flex justify-end mt-2">
                        <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px]">send</span>
                            <span>Enviar Solicitud</span>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$isAdmin && !$isAuditor): ?>
        <!-- Historial de Solicitudes para Residentes -->
        <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
            <h3 class="font-bold text-on-surface text-base mb-4">Historial de Solicitudes Enviadas</h3>

            <?php if (empty($solicitudes)): ?>
                <p class="text-sm text-slate-500 text-center py-6">No ha realizado solicitudes de cambio de datos recientemente.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-background text-xs font-bold text-slate-400 uppercase">
                                <th class="py-3 px-2">Fecha</th>
                                <th class="py-3 px-2">Datos Solicitados</th>
                                <th class="py-3 px-2 text-center">Estado</th>
                                <th class="py-3 px-2">Observaciones Admin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-background">
                            <?php foreach ($solicitudes as $s): ?>
                                <?php $json = json_decode($s['datos_nuevos_json'], true); ?>
                                <tr>
                                    <td class="py-3 px-2 text-xs text-slate-500"><?= date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) ?></td>
                                    <td class="py-3 px-2">
                                        <?php if (is_array($json)): ?>
                                            <?php foreach ($json as $k => $v): ?>
                                                <span class="inline-block bg-slate-100 text-slate-700 text-xs px-2 py-0.5 rounded me-1">
                                                    <strong><?= e(ucfirst($k)) ?>:</strong> <?= e($v) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-2 text-center">
                                        <?php if ($s['estado'] === 'aprobado'): ?>
                                            <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full">Aprobado</span>
                                        <?php elseif ($s['estado'] === 'rechazado'): ?>
                                            <span class="bg-red-100 text-red-700 text-xs font-bold px-2.5 py-1 rounded-full">No Aprobado</span>
                                        <?php else: ?>
                                            <span class="bg-amber-100 text-amber-800 text-xs font-bold px-2.5 py-1 rounded-full">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-2 text-xs italic text-slate-500"><?= e($s['motivo_admin'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Verificación en Dos Pasos (2FA) -->
    <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm<?= (!$isAdmin && !$isAuditor) ? ' mt-8' : '' ?>">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div class="flex-1">
                <h3 class="font-bold text-on-surface text-base mb-1 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">security</span>
                    Verificación en Dos Pasos (2FA)
                </h3>
                <p class="text-xs text-on-surface-variant max-w-xl mb-3">
                    Añade una capa adicional de seguridad: al iniciar sesión, además de su contraseña, el sistema le pedirá un código de 6 dígitos enviado a su correo electrónico.
                </p>
                <?php if ($twoFaEnabled): ?>
                    <span class="inline-flex items-center gap-1.5 bg-emerald-100 text-emerald-700 text-xs font-bold px-3 py-1 rounded-full">
                        <span class="material-symbols-outlined text-[14px]">check_circle</span>
                        2FA activada en su cuenta
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1 rounded-full">
                        <span class="material-symbols-outlined text-[14px]">error</span>
                        2FA desactivada en su cuenta
                    </span>
                <?php endif; ?>
            </div>

            <form method="POST" action="/perfil/2fa/toggle" class="flex flex-col gap-3 w-full lg:w-80 shrink-0">
                <?= csrf_field() ?>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wide d-block mb-1">Contraseña Actual</label>
                    <div class="relative">
                        <input type="password" id="perfil_2fa_password" name="password" required autocomplete="current-password" placeholder="Ingrese su contraseña"
                               class="w-full pl-4 pr-11 py-2.5 bg-slate-50 border border-outline-variant rounded-xl text-sm focus:bg-white focus:border-primary focus:outline-none">
                        <button type="button" onclick="togglePassword('perfil_2fa_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary transition-colors flex items-center justify-center p-1" title="Mostrar/Ocultar contraseña">
                            <span class="material-symbols-outlined text-[20px]">visibility</span>
                        </button>
                    </div>
                    <small class="text-slate-400 text-xs mt-1 block">Por seguridad, confirme su contraseña para cambiar esta opción.</small>
                </div>
                <?php if ($twoFaEnabled): ?>
                    <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-bold text-sm px-4 py-2.5 rounded-xl transition-colors flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px]">lock_open</span>
                        <span>Desactivar 2FA</span>
                    </button>
                <?php else: ?>
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold text-sm px-4 py-2.5 rounded-xl shadow-sm transition-all flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px]">lock</span>
                        <span>Activar 2FA</span>
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
<?php if ($isAdmin || $isAuditor): ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>

<style>
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
    display: none !important;
}
</style>
<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn ? (btn.querySelector ? (btn.querySelector('.material-symbols-outlined') || btn) : document.getElementById(btn)) : null;
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        if (icon) icon.textContent = 'visibility';
    }
}
function togglePasswordVisibility(inputId, iconId) {
    togglePassword(inputId, iconId);
}
</script>

