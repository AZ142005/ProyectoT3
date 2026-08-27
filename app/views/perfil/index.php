<?php
$role = \App\Core\Auth::role();
$isAdmin = ($role === 'admin');
$isAuditor = ($role === 'auditor');
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
            <div class="flex items-center gap-3">
                <a href="<?= $isAuditor ? '/auth/logout' : '/admin/logout' ?>" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                </a>
            </div>
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
                    <span class="font-semibold text-on-surface"><?= e($persona['cedula'] ?? 'N/A') ?></span>
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase d-block">Correo Electrónico</span>
                    <span class="font-semibold text-on-surface"><?= e($persona['email'] ?? $user['email']) ?></span>
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase d-block">Teléfono Móvil</span>
                    <span class="font-semibold text-on-surface"><?= e($persona['telefono'] ?? 'No registrado') ?></span>
                </div>
            </div>
        </div>

        <!-- Formulario de Solicitud de Cambio -->
        <div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm md:col-span-2">
            <h3 class="font-bold text-on-surface text-base mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">edit_note</span>
                Solicitar Actualización de Datos
            </h3>
            <p class="text-xs text-on-surface-variant mb-6">Los cambios serán revisados y aprobados por la administración antes de ser aplicados.</p>

            <form method="POST" action="/perfil/solicitar-cambio" class="flex flex-col gap-4">
                <?= csrf_field() ?>

                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wide d-block mb-1">Nuevo Teléfono Móvil</label>
                    <input type="text" name="telefono" value="<?= e($persona['telefono'] ?? '') ?>" placeholder="Ej: 0412-1234567"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-outline-variant rounded-xl text-sm focus:bg-white focus:border-primary focus:outline-none">
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wide d-block mb-1">Nuevo Correo Electrónico</label>
                    <input type="email" name="email" value="<?= e($persona['email'] ?? $user['email']) ?>" placeholder="usuario@ejemplo.com"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-outline-variant rounded-xl text-sm focus:bg-white focus:border-primary focus:outline-none">
                </div>

                <div class="flex justify-end mt-2">
                    <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold text-sm px-6 py-2.5 rounded-xl shadow-sm transition-all">
                        Enviar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Historial de Solicitudes -->
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
                                        <span class="bg-red-100 text-red-700 text-xs font-bold px-2.5 py-1 rounded-full">Rechazado</span>
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
<?php if ($isAdmin || $isAuditor): ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>
