<div class="flex flex-1 min-h-screen w-full">
    <?php $activeRoute = 'usuarios'; require VIEWS_PATH . '/layouts/admin_sidebar.php'; ?>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="text-xl font-bold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">group</span>
                    <span>Usuarios y Solicitudes</span>
                </h1>
            </div>
            <a href="/admin/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="container-fluid p-0">
                <!-- Mensajes Flash Estándar -->
                <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

                <!-- ESTILOS ESPECÍFICOS PARA PESTAÑAS -->
                <style>
                .nav-pills .nav-link {
                    color: #475569;
                    background-color: #f8fafc;
                    border: 1px solid #e2e8f0;
                    transition: all 0.2s ease-in-out;
                }
                .nav-pills .nav-link:hover:not(.active) {
                    background-color: #f1f5f9;
                    color: #0f172a;
                }
                .nav-pills .nav-link.active {
                    background-color: #27ae60 !important;
                    border-color: #27ae60 !important;
                    color: #ffffff !important;
                    box-shadow: 0 4px 6px -1px rgba(39, 174, 96, 0.25);
                }
                </style>

                <!-- NAVEGACIÓN EN DOS PESTAÑAS: USUARIOS VS SOLICITUDES DE REGISTRO -->
                <div class="bg-white rounded-2xl border border-outline-variant p-2 shadow-sm mb-4">
                    <ul class="nav nav-pills nav-fill gap-2" id="usuariosTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($tabActual === 'usuarios') ? 'active' : '' ?> py-3 px-4 rounded-xl font-bold flex items-center justify-center gap-2"
                                    id="tab-usuarios-btn"
                                    data-bs-toggle="pill"
                                    data-bs-target="#tab-usuarios"
                                    type="button"
                                    role="tab"
                                    aria-controls="tab-usuarios"
                                    aria-selected="<?= ($tabActual === 'usuarios') ? 'true' : 'false' ?>">
                                <span class="material-symbols-outlined text-[20px]">group</span>
                                <span>Usuarios</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($tabActual === 'solicitudes') ? 'active' : '' ?> py-3 px-4 rounded-xl font-bold flex items-center justify-center gap-2"
                                    id="tab-solicitudes-btn"
                                    data-bs-toggle="pill"
                                    data-bs-target="#tab-solicitudes"
                                    type="button"
                                    role="tab"
                                    aria-controls="tab-solicitudes"
                                    aria-selected="<?= ($tabActual === 'solicitudes') ? 'true' : 'false' ?>">
                                <span class="material-symbols-outlined text-[20px]">how_to_reg</span>
                                <span>Solicitudes de Registro</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="usuariosTabsContent">
                    <!-- ========================================================================= -->
                    <!-- PESTAÑA 1: LISTADO UNIFICADO DE USUARIOS Y CREDENCIALES                    -->
                    <!-- ========================================================================= -->
                    <div class="tab-pane fade <?= ($tabActual === 'usuarios') ? 'show active' : '' ?> space-y-6" id="tab-usuarios" role="tabpanel" aria-labelledby="tab-usuarios-btn">

                <!-- Banner Especial de Contraseña Reiniciada -->
                <?php if (!empty($passwordReseteada) && is_array($passwordReseteada)): ?>
                    <div class="card border-0 shadow-sm mb-4 bg-emerald-50 border-start border-4 border-success rounded-3">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start gap-3">
                                <span class="material-symbols-outlined text-success fs-1 shrink-0">key</span>
                                <div class="flex-grow-1">
                                    <h5 class="fw-bold text-success mb-1">¡Contraseña Reiniciada Exitosamente!</h5>
                                    <p class="text-muted mb-3">
                                        Se ha restablecido la clave de acceso para 
                                        <strong><?= e($passwordReseteada['nombre']) ?></strong> 
                                        (Cédula: <strong><?= e($passwordReseteada['cedula']) ?></strong>).
                                        Si la cuenta estaba bloqueada por intentos fallidos, ha sido desbloqueada.
                                    </p>
                                    <div class="d-flex flex-wrap align-items-center gap-3 bg-white p-3 rounded-2 border">
                                        <div class="text-muted small fw-semibold">Nueva Contraseña Temporal:</div>
                                        <code id="tempPasswordVal" class="fs-5 fw-bold text-primary px-3 py-1 bg-light rounded border"><?= e($passwordReseteada['password']) ?></code>
                                        <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 fw-bold" onclick="copiarPassword()">
                                            <span class="material-symbols-outlined fs-6" id="btnCopiarIcon">content_copy</span>
                                            <span id="btnCopiarText">Copiar al portapapeles</span>
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        <span class="material-symbols-outlined fs-6 align-middle text-amber-600">info</span>
                                        Copie y entregue esta contraseña al usuario. Recomiéndele cambiarla en su perfil tras el inicio de sesión.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tarjeta de Filtros y Búsqueda -->
                <div class="bg-white rounded-2xl border border-outline-variant p-4 shadow-sm mb-6">
                    <div class="flex items-center gap-2 mb-3 pb-2 border-b border-slate-100">
                        <span class="material-symbols-outlined text-primary text-sm">filter_alt</span>
                        <h2 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Filtros de Búsqueda</h2>
                    </div>

                    <form method="GET" action="/admin/usuarios" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <input type="hidden" name="tab" value="usuarios">
                        <div class="flex flex-col gap-1 md:col-span-2">
                            <label for="buscar" class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Buscar Usuario</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/70 text-[16px]">search</span>
                                <input type="text" id="buscar" name="buscar" value="<?= e($buscar) ?>"
                                       placeholder="Buscar por cédula, nombre, apellido o correo electrónico..."
                                       class="w-full pl-8 pr-3 py-2 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary text-xs">
                            </div>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label for="rol" class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Tipo de Cuenta</label>
                            <select id="rol" name="rol" class="w-full px-3 py-2 bg-background border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary cursor-pointer text-xs">
                                <option value="" <?= empty($rol) ? 'selected' : '' ?>>Todos los roles</option>
                                <option value="residente" <?= $rol === 'residente' ? 'selected' : '' ?>>Residentes</option>
                                <option value="admin" <?= $rol === 'admin' ? 'selected' : '' ?>>Administradores</option>
                                <option value="auditor" <?= $rol === 'auditor' ? 'selected' : '' ?>>Auditores</option>
                            </select>
                        </div>

                        <div class="col-span-full flex justify-end gap-2">
                            <?php if (!empty($buscar) || !empty($rol)): ?>
                                <a href="/admin/usuarios?tab=usuarios" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2 rounded-xl text-xs transition-all flex items-center justify-center gap-1" title="Limpiar filtros">
                                    <span class="material-symbols-outlined text-[16px]">clear_all</span>
                                    Limpiar Filtros
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold px-5 py-2 rounded-xl shadow-sm text-xs transition-all flex items-center justify-center gap-1 active:scale-95">
                                <span class="material-symbols-outlined text-[16px]">filter_alt</span>
                                Aplicar Filtros
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tarjeta con Listado de Usuarios -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <h2 class="text-lg font-bold text-on-surface mb-0">Listado de Usuarios Registrados</h2>
                            <span class="bg-background text-primary text-xs font-bold px-3 py-1 rounded-full border border-outline-variant">
                                <?= e($paginacion['total']) ?> en total
                            </span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="text-xs uppercase text-on-surface-variant font-bold border-b border-background">
                                    <th class="py-3 px-4" style="width: 140px;">Cédula</th>
                                    <th class="py-3 px-4">Usuario / Nombre</th>
                                    <th class="py-3 px-4">Contacto</th>
                                    <th class="py-3 px-4">Rol / Ubicación</th>
                                    <th class="py-3 px-4">Estado</th>
                                    <th class="py-3 px-4 text-end" style="width: 180px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-background">
                                <?php if (empty($usuarios)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-12 text-on-surface-variant">
                                            <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 d-block mb-2">person_search</span>
                                            <p class="mb-0 fw-semibold">No se encontraron usuarios registrados que coincidan con la búsqueda.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $u): ?>
                                        <tr class="hover:bg-background/40 transition-colors">
                                            <td class="py-4 px-4">
                                                <span class="badge bg-light text-on-surface border px-2 py-1.5 font-mono text-xs">
                                                    <?= e($u['cedula']) ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-4">
                                                <div class="font-semibold text-on-surface"><?= e($u['nombre_completo']) ?></div>
                                                <div class="text-xs text-on-surface-variant font-mono"><?= e($u['tipo_entidad'] === 'usuario' ? 'Cuenta de Sistema' : 'Residente') ?></div>
                                            </td>
                                            <td class="py-4 px-4">
                                                <?php if (!empty($u['email'])): ?>
                                                    <div class="text-xs text-truncate" style="max-width: 220px;" title="<?= e($u['email']) ?>">
                                                        <span class="material-symbols-outlined fs-6 align-middle text-on-surface-variant me-1">mail</span>
                                                        <?= e($u['email']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-on-surface-variant text-xs fst-italic">Sin correo</span>
                                                <?php endif; ?>
                                                <?php if (!empty($u['telefono'])): ?>
                                                    <div class="text-xs text-on-surface-variant">
                                                        <span class="material-symbols-outlined fs-6 align-middle text-on-surface-variant me-1">phone</span>
                                                        <?= e($u['telefono']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4 px-4">
                                                <div>
                                                    <?php 
                                                    $rolBadgeClass = 'bg-primary-subtle text-primary-emphasis';
                                                    if ($u['rol_clave'] === 'admin') {
                                                        $rolBadgeClass = 'bg-danger-subtle text-danger-emphasis';
                                                    } elseif ($u['rol_clave'] === 'auditor') {
                                                        $rolBadgeClass = 'bg-info-subtle text-info-emphasis';
                                                    }
                                                    ?>
                                                    <span class="badge <?= e($rolBadgeClass) ?> fw-semibold px-2 py-1">
                                                        <?= e($u['rol_texto']) ?>
                                                    </span>
                                                </div>
                                                <small class="text-xs text-on-surface-variant d-block mt-0.5"><?= e($u['detalle_ubicacion']) ?></small>
                                            </td>
                                            <td class="py-4 px-4">
                                                <?php if (!empty($u['esta_bloqueado'])): ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" title="Bloqueado por 5 intentos fallidos">
                                                        <span class="material-symbols-outlined fs-6 align-middle">lock</span> Bloqueado
                                                    </span>
                                                <?php elseif ((int)$u['estado'] === 1): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                        <span class="material-symbols-outlined fs-6 align-middle">check_circle</span> Activo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                                        Inactivo
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4 px-4 text-end">
                                                <?php 
                                                $esAdminCuenta = ($u['rol_clave'] === 'admin');
                                                $esMismoAdmin  = ($u['tipo_entidad'] === 'usuario' && intval($u['id']) === intval(\App\Core\Auth::id()));
                                                ?>
                                                <div class="dropdown d-inline-block">
                                                    <button class="btn btn-sm btn-light border shadow-sm rounded-circle p-1.5 d-inline-flex align-items-center justify-center text-secondary" 
                                                            type="button" 
                                                            id="dropdownMenuUser_<?= e($u['tipo_entidad']) ?>_<?= e($u['id']) ?>" 
                                                            data-bs-toggle="dropdown" 
                                                            aria-expanded="false" 
                                                            title="Opciones de usuario">
                                                        <span class="material-symbols-outlined fs-5">more_vert</span>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border rounded-3 py-1 text-start" aria-labelledby="dropdownMenuUser_<?= e($u['tipo_entidad']) ?>_<?= e($u['id']) ?>" style="min-width: 190px;">
                                                        <!-- Opción 1: Reiniciar clave -->
                                                        <li>
                                                            <?php if ($esAdminCuenta || $esMismoAdmin): ?>
                                                                <button type="button" 
                                                                        class="dropdown-item d-flex align-items-center gap-2 py-2 text-muted opacity-50" 
                                                                        disabled 
                                                                        title="<?= e($esMismoAdmin ? 'No puede reiniciar su propia contraseña desde este panel' : 'No se permite reiniciar contraseñas de cuentas de administrador') ?>">
                                                                    <span class="material-symbols-outlined fs-6 text-muted">lock</span>
                                                                    <span>Reiniciar clave</span>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" 
                                                                        class="dropdown-item d-flex align-items-center gap-2 py-2 text-dark" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#modalReiniciarPassword"
                                                                        data-id="<?= e($u['id']) ?>"
                                                                        data-tipo="<?= e($u['tipo_entidad']) ?>"
                                                                        data-nombre="<?= e($u['nombre_completo']) ?>"
                                                                        data-cedula="<?= e($u['cedula']) ?>"
                                                                        data-rol="<?= e($u['rol_texto']) ?>"
                                                                        onclick="configurarModalReinicio(this)">
                                                                    <span class="material-symbols-outlined fs-6 text-warning">lock_reset</span>
                                                                    <span>Reiniciar clave</span>
                                                                </button>
                                                            <?php endif; ?>
                                                        </li>

                                                        <!-- Opción 2: Actualizar datos -->
                                                        <li>
                                                            <?php if ($esAdminCuenta || $u['tipo_entidad'] === 'usuario'): ?>
                                                                <button type="button" 
                                                                        class="dropdown-item d-flex align-items-center gap-2 py-2 text-muted opacity-50" 
                                                                        disabled 
                                                                        title="No se permite modificar datos de cuentas de administrador desde este panel">
                                                                    <span class="material-symbols-outlined fs-6 text-muted">edit_off</span>
                                                                    <span>Actualizar datos</span>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" 
                                                                        class="dropdown-item d-flex align-items-center gap-2 py-2 text-dark" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#modalActualizarDatos"
                                                                        data-id="<?= e($u['id']) ?>"
                                                                        data-tipo="<?= e($u['tipo_entidad']) ?>"
                                                                        data-nombre="<?= e($u['nombre_completo']) ?>"
                                                                        data-cedula="<?= e($u['cedula']) ?>"
                                                                        data-telefono="<?= e($u['telefono'] ?? '') ?>"
                                                                        data-email="<?= e($u['email'] ?? '') ?>"
                                                                        onclick="configurarModalActualizarDatos(this)">
                                                                    <span class="material-symbols-outlined fs-6 text-primary">edit</span>
                                                                    <span>Actualizar datos</span>
                                                                </button>
                                                            <?php endif; ?>
                                                        </li>

                                                        <li><hr class="dropdown-divider my-1"></li>

                                                        <!-- Opción 3: Eliminar -->
                                                        <li>
                                                            <?php if ($esAdminCuenta || $u['tipo_entidad'] === 'usuario'): ?>
                                                                <button type="button" 
                                                                        class="dropdown-item d-flex align-items-center gap-2 py-2 text-muted opacity-50" 
                                                                        disabled 
                                                                        title="No se permite eliminar cuentas administrativas del sistema">
                                                                    <span class="material-symbols-outlined fs-6 text-muted">block</span>
                                                                    <span>Eliminar</span>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" 
                                                                        class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#modalEliminarUsuario"
                                                                        data-id="<?= e($u['id']) ?>"
                                                                        data-tipo="<?= e($u['tipo_entidad']) ?>"
                                                                        data-nombre="<?= e($u['nombre_completo']) ?>"
                                                                        data-cedula="<?= e($u['cedula']) ?>"
                                                                        data-ubicacion="<?= e($u['detalle_ubicacion']) ?>"
                                                                        onclick="configurarModalEliminar(this)">
                                                                    <span class="material-symbols-outlined fs-6 text-danger">delete</span>
                                                                    <span>Eliminar</span>
                                                                </button>
                                                            <?php endif; ?>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($paginacion['totalPaginas'] > 1): ?>
                        <div class="card-footer bg-white border-top py-3 px-4">
                            <?php
                            $queryParams['tab'] = 'usuarios';
                            $filtros = ['tab' => 'usuarios', 'buscar' => $buscar ?? '', 'rol' => $rol ?? ''];
                            include VIEWS_PATH . '/components/pagination.php';
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                    </div>
                    <!-- ========================================================================= -->
                    <!-- PESTAÑA 2: SOLICITUDES DE REGISTRO DE RESIDENTES                          -->
                    <!-- URL de la pestaña: /admin/usuarios?tab=solicitudes                        -->
                    <!-- ========================================================================= -->
                    <div class="tab-pane fade <?= ($tabActual === 'solicitudes') ? 'show active' : '' ?> space-y-6" id="tab-solicitudes" role="tabpanel" aria-labelledby="tab-solicitudes-btn">
                        <?php require VIEWS_PATH . '/admin/solicitudes_registro/index.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Reiniciar Contraseña -->
<div class="modal fade" id="modalReiniciarPassword" tabindex="-1" aria-labelledby="modalReiniciarPasswordTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="/admin/usuarios/reiniciar-password">
                <?= csrf_field() ?>
                <input type="hidden" name="tipo_entidad" id="modalTipoEntidad" value="">
                <input type="hidden" name="id" id="modalId" value="">

                <div class="modal-header bg-warning-subtle text-warning-emphasis py-3 px-4 border-bottom">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalReiniciarPasswordTitle">
                        <span class="material-symbols-outlined text-warning">lock_reset</span>
                        <span>Reiniciar Contraseña de Acceso</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Resumen del Usuario Objetivo -->
                    <div class="bg-light p-3 rounded-3 border mb-4">
                        <div class="small text-muted mb-1">Usuario seleccionado:</div>
                        <div class="fw-bold fs-6 text-dark" id="modalNombreUsuario">-</div>
                        <div class="small text-muted">
                            Cédula: <span class="fw-semibold text-dark" id="modalCedulaUsuario">-</span> | 
                            Rol: <span class="fw-semibold text-dark" id="modalRolUsuario">-</span>
                        </div>
                    </div>

                    <p class="text-secondary small mb-3">
                        Seleccione el método para asignar la nueva contraseña de acceso. Si la cuenta tenía bloqueo temporal, se reactivará de inmediato.
                    </p>

                    <!-- Opciones de Reinicio -->
                    <div class="mb-3">
                        <div class="form-check p-3 rounded-3 border mb-2 cursor-pointer bg-white" onclick="document.getElementById('modoAuto').checked = true; toggleModoPassword();">
                            <input class="form-check-input" type="radio" name="modo_generacion" id="modoAuto" value="auto" checked onchange="toggleModoPassword()">
                            <label class="form-check-label fw-bold text-dark" for="modoAuto">
                                Generar contraseña temporal segura automáticamente
                                <span class="badge bg-success-subtle text-success ms-1 small">Recomendado</span>
                            </label>
                            <small class="text-muted d-block mt-1">El sistema creará una clave aleatoria de 10 caracteres alfanuméricos y la mostrará en pantalla para copiarla.</small>
                        </div>

                        <div class="form-check p-3 rounded-3 border cursor-pointer bg-white" onclick="document.getElementById('modoManual').checked = true; toggleModoPassword();">
                            <input class="form-check-input" type="radio" name="modo_generacion" id="modoManual" value="manual" onchange="toggleModoPassword()">
                            <label class="form-check-label fw-bold text-dark" for="modoManual">
                                Asignar contraseña manual
                            </label>
                            <small class="text-muted d-block mt-1">Escriba una contraseña específica para este usuario.</small>
                        </div>
                    </div>

                    <!-- Campo Contraseña Manual (condicional) -->
                    <div id="campoPasswordManual" class="d-none mt-3 p-3 bg-light rounded-3 border">
                        <label for="password_manual" class="form-label fw-bold small text-dark">Nueva Contraseña Manual *</label>
                        <input type="text" id="password_manual" name="password_manual" 
                               class="form-control" 
                               placeholder="Mínimo 8 caracteres (letras y números)">
                        <small class="text-muted d-block mt-1">Debe contener mínimo 8 caracteres, con al menos una letra y un número.</small>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3 px-4 border-top">
                    <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark d-inline-flex align-items-center gap-1 shadow-sm">
                        <span class="material-symbols-outlined fs-6">check_circle</span>
                        <span>Confirmar Reinicio</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Actualizar Datos de Residente -->
<div class="modal fade" id="modalActualizarDatos" tabindex="-1" aria-labelledby="modalActualizarDatosTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="/admin/usuarios/actualizar-datos">
                <?= csrf_field() ?>
                <input type="hidden" name="tipo_entidad" id="modalActTipoEntidad" value="persona">
                <input type="hidden" name="id" id="modalActId" value="">

                <div class="modal-header bg-primary text-white py-3 px-4 border-bottom">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalActualizarDatosTitle">
                        <span class="material-symbols-outlined text-white">edit</span>
                        <span>Actualizar Datos de Contacto</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="bg-light p-3 rounded-3 border mb-3">
                        <div class="small text-muted mb-1">Residente seleccionado:</div>
                        <div class="fw-bold fs-6 text-dark" id="modalActNombreUsuario">-</div>
                        <div class="small text-muted">
                            Cédula: <span class="fw-semibold text-dark" id="modalActCedulaUsuario">-</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="act_telefono" class="form-label fw-bold small text-dark">Número Telefónico *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">
                                <span class="material-symbols-outlined fs-6">phone</span>
                            </span>
                            <input type="tel" id="act_telefono" name="telefono" 
                                   class="form-control" 
                                   placeholder="Ej: 04121234567" 
                                   maxlength="11" 
                                   required 
                                   pattern="^(0412|0414|0424|0416|0426)[0-9]{7}$"
                                   title="Ingrese un número telefónico venezolano de 11 dígitos (0412, 0414, 0424, 0416, 0426)"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                        </div>
                        <small class="text-muted d-block mt-1">Formato de 11 dígitos (operadoras venezolanas: 0412, 0414, 0424, 0416, 0426).</small>
                    </div>

                    <div class="mb-2">
                        <label for="act_email" class="form-label fw-bold small text-dark">Correo Electrónico *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">
                                <span class="material-symbols-outlined fs-6">mail</span>
                            </span>
                            <input type="email" id="act_email" name="email" 
                                   class="form-control" 
                                   required 
                                   placeholder="residente@ejemplo.com">
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3 px-4 border-top">
                    <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold d-inline-flex align-items-center gap-1 shadow-sm">
                        <span class="material-symbols-outlined fs-6">save</span>
                        <span>Guardar Cambios</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Eliminación con Doble Confirmación y Temporizador de 10s -->
<div class="modal fade" id="modalEliminarUsuario" tabindex="-1" aria-labelledby="modalEliminarUsuarioTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="/admin/usuarios/eliminar" id="formEliminarUsuario">
                <?= csrf_field() ?>
                <input type="hidden" name="tipo_entidad" id="modalElimTipoEntidad" value="persona">
                <input type="hidden" name="id" id="modalElimId" value="">

                <!-- Cabecera Paso 1 -->
                <div id="headerEliminarPaso1" class="modal-header bg-danger-subtle text-danger-emphasis py-3 px-4 border-bottom">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="modalEliminarUsuarioTitle">
                        <span class="material-symbols-outlined text-danger">warning</span>
                        <span>Confirmar Eliminación de Residente (1/2)</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar" onclick="cancelarEliminacion()"></button>
                </div>

                <!-- Cabecera Paso 2 -->
                <div id="headerEliminarPaso2" class="modal-header bg-danger text-white py-3 px-4 border-bottom d-none">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                        <span class="material-symbols-outlined text-white">crisis_alert</span>
                        <span>Advertencia de Seguridad Definitiva (2/2)</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" onclick="cancelarEliminacion()"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Resumen del Residente a Eliminar -->
                    <div class="bg-light p-3 rounded-3 border mb-3">
                        <div class="small text-muted mb-1">Residente a eliminar:</div>
                        <div class="fw-bold fs-6 text-dark" id="modalElimNombreUsuario">-</div>
                        <div class="small text-muted">
                            Cédula: <span class="fw-semibold text-dark" id="modalElimCedulaUsuario">-</span> | 
                            Ubicación: <span class="fw-semibold text-dark" id="modalElimUbicacionUsuario">-</span>
                        </div>
                    </div>

                    <!-- Contenido Paso 1 -->
                    <div id="cuerpoEliminarPaso1">
                        <div class="alert alert-warning border-0 rounded-3 mb-3 d-flex align-items-start gap-2">
                            <span class="material-symbols-outlined text-warning shrink-0 mt-0.5">info</span>
                            <div class="small">
                                <strong>¿Está seguro de que desea eliminar la cuenta de este residente?</strong><br>
                                Esta opción se utiliza en casos de venta del inmueble o cambio de propietario. La unidad quedará disponible para el registro del nuevo propietario.
                            </div>
                        </div>
                        <p class="text-secondary small mb-0">
                            Por integridad legal y contable, los registros históricos de pagos y facturas se conservarán intactos en el historial contable, pero el residente quedará totalmente desligado de la unidad y su acceso revocado.
                        </p>
                    </div>

                    <!-- Contenido Paso 2 (Segunda confirmación con temporizador de 10s) -->
                    <div id="cuerpoEliminarPaso2" class="d-none">
                        <div class="alert alert-danger border-0 rounded-3 mb-3 d-flex align-items-start gap-2">
                            <span class="material-symbols-outlined text-danger shrink-0 mt-0.5">gpp_bad</span>
                            <div class="small">
                                <strong>¡CONFIRMACIÓN FINAL IRREVERSIBLE!</strong><br>
                                Está a punto de ejecutar la eliminación y desvinculación definitiva de este residente. Para evitar accidentes o eliminaciones involuntarias, debe esperar el tiempo de enfriamiento de 10 segundos.
                            </div>
                        </div>

                        <div class="text-center py-3 bg-light rounded-3 border mb-2">
                            <div class="text-muted small mb-1">Tiempo de enfriamiento de seguridad:</div>
                            <div class="display-6 fw-bold text-danger font-monospace" id="contadorCooldown">10s</div>
                            <small class="text-muted" id="cooldownHint">El botón de confirmación se habilitará al agotarse el tiempo...</small>
                        </div>
                    </div>
                </div>

                <!-- Footer Paso 1 -->
                <div id="footerEliminarPaso1" class="modal-footer bg-light py-3 px-4 border-top">
                    <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-outline-danger fw-bold d-inline-flex align-items-center gap-1 shadow-sm" onclick="avanzarEliminarPaso2()">
                        <span>Continuar a la Confirmación Final</span>
                        <span class="material-symbols-outlined fs-6">arrow_forward</span>
                    </button>
                </div>

                <!-- Footer Paso 2 -->
                <div id="footerEliminarPaso2" class="modal-footer bg-light py-3 px-4 border-top d-none">
                    <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal" onclick="cancelarEliminacion()">Cancelar</button>
                    <button type="submit" id="btnConfirmarEliminar" class="btn btn-danger fw-bold d-inline-flex align-items-center gap-1 shadow-sm" disabled>
                        <span class="material-symbols-outlined fs-6">delete_forever</span>
                        <span id="btnConfirmarEliminarTexto">Confirmar Eliminación (10s)</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function configurarModalReinicio(btn) {
    if (!btn || btn.hasAttribute('disabled') || btn.disabled) {
        return;
    }
    const id = btn.getAttribute('data-id');
    const tipo = btn.getAttribute('data-tipo');
    const nombre = btn.getAttribute('data-nombre');
    const cedula = btn.getAttribute('data-cedula');
    const rol = btn.getAttribute('data-rol');

    document.getElementById('modalId').value = id;
    document.getElementById('modalTipoEntidad').value = tipo;
    document.getElementById('modalNombreUsuario').textContent = nombre;
    document.getElementById('modalCedulaUsuario').textContent = cedula;
    document.getElementById('modalRolUsuario').textContent = rol;

    // Resetear a modo auto por defecto
    document.getElementById('modoAuto').checked = true;
    document.getElementById('password_manual').value = '';
    toggleModoPassword();
}

function toggleModoPassword() {
    const modoManual = document.getElementById('modoManual').checked;
    const campoManual = document.getElementById('campoPasswordManual');
    const inputManual = document.getElementById('password_manual');

    if (modoManual) {
        campoManual.classList.remove('d-none');
        inputManual.required = true;
        inputManual.focus();
    } else {
        campoManual.classList.add('d-none');
        inputManual.required = false;
        inputManual.value = '';
    }
}

function copiarPassword() {
    const codeEl = document.getElementById('tempPasswordVal');
    if (!codeEl) return;
    const text = codeEl.innerText.trim();
    
    navigator.clipboard.writeText(text).then(() => {
        const btnText = document.getElementById('btnCopiarText');
        const btnIcon = document.getElementById('btnCopiarIcon');
        const originalText = btnText.textContent;
        
        btnText.textContent = '¡Copiado!';
        btnIcon.textContent = 'done';
        
        setTimeout(() => {
            btnText.textContent = originalText;
            btnIcon.textContent = 'content_copy';
        }, 2500);
    }).catch(err => {
        alert('Contraseña: ' + text);
    });
}

function configurarModalActualizarDatos(btn) {
    if (!btn || btn.hasAttribute('disabled') || btn.disabled) return;
    const id = btn.getAttribute('data-id');
    const tipo = btn.getAttribute('data-tipo');
    const nombre = btn.getAttribute('data-nombre');
    const cedula = btn.getAttribute('data-cedula');
    const telefono = btn.getAttribute('data-telefono') || '';
    const email = btn.getAttribute('data-email') || '';

    document.getElementById('modalActId').value = id;
    document.getElementById('modalActTipoEntidad').value = tipo;
    document.getElementById('modalActNombreUsuario').textContent = nombre;
    document.getElementById('modalActCedulaUsuario').textContent = cedula;
    document.getElementById('act_telefono').value = telefono;
    document.getElementById('act_email').value = email;
}

let cooldownTimer = null;
let cooldownSeconds = 10;

function configurarModalEliminar(btn) {
    if (!btn || btn.hasAttribute('disabled') || btn.disabled) return;
    
    cancelarEliminacion();

    const id = btn.getAttribute('data-id');
    const tipo = btn.getAttribute('data-tipo');
    const nombre = btn.getAttribute('data-nombre');
    const cedula = btn.getAttribute('data-cedula');
    const ubicacion = btn.getAttribute('data-ubicacion');

    document.getElementById('modalElimId').value = id;
    document.getElementById('modalElimTipoEntidad').value = tipo;
    document.getElementById('modalElimNombreUsuario').textContent = nombre;
    document.getElementById('modalElimCedulaUsuario').textContent = cedula;
    document.getElementById('modalElimUbicacionUsuario').textContent = ubicacion;
}

function avanzarEliminarPaso2() {
    document.getElementById('headerEliminarPaso1').classList.add('d-none');
    document.getElementById('cuerpoEliminarPaso1').classList.add('d-none');
    document.getElementById('footerEliminarPaso1').classList.add('d-none');

    document.getElementById('headerEliminarPaso2').classList.remove('d-none');
    document.getElementById('cuerpoEliminarPaso2').classList.remove('d-none');
    document.getElementById('footerEliminarPaso2').classList.remove('d-none');

    iniciarCooldownEliminacion();
}

function iniciarCooldownEliminacion() {
    if (cooldownTimer) clearInterval(cooldownTimer);
    
    cooldownSeconds = 10;
    const contadorEl = document.getElementById('contadorCooldown');
    const btnConfirmar = document.getElementById('btnConfirmarEliminar');
    const btnTexto = document.getElementById('btnConfirmarEliminarTexto');
    const hintEl = document.getElementById('cooldownHint');

    btnConfirmar.disabled = true;
    contadorEl.textContent = cooldownSeconds + 's';
    btnTexto.textContent = `Confirmar Eliminación (${cooldownSeconds}s)`;
    hintEl.textContent = 'Espere mientras se habilita el botón de confirmación...';

    cooldownTimer = setInterval(() => {
        cooldownSeconds--;
        if (cooldownSeconds > 0) {
            contadorEl.textContent = cooldownSeconds + 's';
            btnTexto.textContent = `Confirmar Eliminación (${cooldownSeconds}s)`;
        } else {
            clearInterval(cooldownTimer);
            cooldownTimer = null;
            contadorEl.textContent = 'Listo';
            contadorEl.classList.remove('text-danger');
            contadorEl.classList.add('text-success');
            btnConfirmar.disabled = false;
            btnTexto.textContent = 'Confirmar Eliminación Definitiva';
            hintEl.textContent = 'El botón de confirmación está ahora habilitado.';
            hintEl.classList.remove('text-muted');
            hintEl.classList.add('text-success', 'fw-bold');
        }
    }, 1000);
}

function cancelarEliminacion() {
    if (cooldownTimer) {
        clearInterval(cooldownTimer);
        cooldownTimer = null;
    }

    const p1Header = document.getElementById('headerEliminarPaso1');
    const p1Cuerpo = document.getElementById('cuerpoEliminarPaso1');
    const p1Footer = document.getElementById('footerEliminarPaso1');
    const p2Header = document.getElementById('headerEliminarPaso2');
    const p2Cuerpo = document.getElementById('cuerpoEliminarPaso2');
    const p2Footer = document.getElementById('footerEliminarPaso2');

    if (p1Header) p1Header.classList.remove('d-none');
    if (p1Cuerpo) p1Cuerpo.classList.remove('d-none');
    if (p1Footer) p1Footer.classList.remove('d-none');
    if (p2Header) p2Header.classList.add('d-none');
    if (p2Cuerpo) p2Cuerpo.classList.add('d-none');
    if (p2Footer) p2Footer.classList.add('d-none');

    const contadorEl = document.getElementById('contadorCooldown');
    if (contadorEl) {
        contadorEl.textContent = '10s';
        contadorEl.classList.remove('text-success');
        contadorEl.classList.add('text-danger');
    }
    const btnConfirmar = document.getElementById('btnConfirmarEliminar');
    if (btnConfirmar) btnConfirmar.disabled = true;
    const btnTexto = document.getElementById('btnConfirmarEliminarTexto');
    if (btnTexto) btnTexto.textContent = 'Confirmar Eliminación (10s)';
    const hintEl = document.getElementById('cooldownHint');
    if (hintEl) {
        hintEl.textContent = 'El botón de confirmación se habilitará al agotarse el tiempo...';
        hintEl.classList.remove('text-success', 'fw-bold');
        hintEl.classList.add('text-muted');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const modalElim = document.getElementById('modalEliminarUsuario');
    if (modalElim) {
        modalElim.addEventListener('hidden.bs.modal', function() {
            cancelarEliminacion();
        });
    }
});

// SINCRONIZACIÓN Y PERSISTENCIA DE PESTAÑAS (TABS)
document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('#usuariosTabs button[data-bs-toggle="pill"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', (e) => {
            const targetId = e.target.getAttribute('data-bs-target');
            const tabName = targetId === '#tab-solicitudes' ? 'solicitudes' : 'usuarios';
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        });
    });
});
</script>
