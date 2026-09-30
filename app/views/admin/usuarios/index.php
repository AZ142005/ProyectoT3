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
                    <span>Gestión de Usuarios y Credenciales</span>
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
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <form method="GET" action="/admin/usuarios" class="row g-3 align-items-end">
                            <div class="col-md-7 col-lg-8">
                                <label for="buscar" class="form-label fw-bold text-secondary small">Buscar Usuario</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted">
                                        <span class="material-symbols-outlined fs-6">search</span>
                                    </span>
                                    <input type="text" id="buscar" name="buscar" value="<?= e($buscar) ?>" 
                                           class="form-control bg-light border-start-0 ps-0" 
                                           placeholder="Buscar por cédula, nombre, apellido o correo electrónico...">
                                </div>
                            </div>

                            <div class="col-md-3 col-lg-2">
                                <label for="rol" class="form-label fw-bold text-secondary small">Tipo de Cuenta</label>
                                <select id="rol" name="rol" class="form-select bg-light">
                                    <option value="" <?= empty($rol) ? 'selected' : '' ?>>Todos los roles</option>
                                    <option value="residente" <?= $rol === 'residente' ? 'selected' : '' ?>>Residentes</option>
                                    <option value="admin" <?= $rol === 'admin' ? 'selected' : '' ?>>Administradores</option>
                                    <option value="auditor" <?= $rol === 'auditor' ? 'selected' : '' ?>>Auditores</option>
                                </select>
                            </div>

                            <div class="col-md-2 col-lg-2 d-flex gap-2">
                                <button type="submit" class="btn btn-primary fw-bold w-100 d-inline-flex align-items-center justify-center gap-1 shadow-sm">
                                    <span class="material-symbols-outlined fs-6">filter_alt</span>
                                    <span>Filtrar</span>
                                </button>
                                <?php if (!empty($buscar) || !empty($rol)): ?>
                                    <a href="/admin/usuarios" class="btn btn-outline-secondary fw-semibold d-inline-flex align-items-center justify-center" title="Limpiar filtros">
                                        <span class="material-symbols-outlined fs-6">close</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tarjeta con Listado de Usuarios -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <h2 class="fs-6 fw-bold mb-0 text-dark">Listado de Usuarios Registrados</h2>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill px-2.5">
                                <?= e($paginacion['total']) ?> en total
                            </span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary small text-uppercase fw-bold">
                                <tr>
                                    <th class="ps-4" style="width: 140px;">Cédula</th>
                                    <th>Usuario / Nombre</th>
                                    <th>Contacto</th>
                                    <th>Rol / Ubicación</th>
                                    <th>Estado</th>
                                    <th class="text-end pe-4" style="width: 180px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <span class="material-symbols-outlined fs-1 text-slate-400 d-block mb-2">person_search</span>
                                            <p class="mb-0 fw-semibold">No se encontraron usuarios registrados que coincidan con la búsqueda.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $u): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark">
                                                <span class="badge bg-light text-dark border px-2 py-1.5 font-monospace">
                                                    <?= e($u['cedula']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= e($u['nombre_completo']) ?></div>
                                                <div class="small text-muted font-monospace"><?= e($u['tipo_entidad'] === 'usuario' ? 'Cuenta de Sistema' : 'Residente') ?></div>
                                            </td>
                                            <td>
                                                <?php if (!empty($u['email'])): ?>
                                                    <div class="small text-truncate" style="max-width: 220px;" title="<?= e($u['email']) ?>">
                                                        <span class="material-symbols-outlined fs-6 align-middle text-muted me-1">mail</span>
                                                        <?= e($u['email']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small fst-italic">Sin correo</span>
                                                <?php endif; ?>
                                                <?php if (!empty($u['telefono'])): ?>
                                                    <div class="small text-muted">
                                                        <span class="material-symbols-outlined fs-6 align-middle text-muted me-1">phone</span>
                                                        <?= e($u['telefono']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
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
                                                <small class="text-muted d-block mt-0.5"><?= e($u['detalle_ubicacion']) ?></small>
                                            </td>
                                            <td>
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
                                            <td class="text-end pe-4">
                                                <?php 
                                                $esAdminCuenta = ($u['rol_clave'] === 'admin');
                                                $esMismoAdmin  = ($u['tipo_entidad'] === 'usuario' && intval($u['id']) === intval(\App\Core\Auth::id()));
                                                ?>
                                                <?php if ($esAdminCuenta || $esMismoAdmin): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-light text-muted border d-inline-flex align-items-center gap-1 opacity-75 shadow-none"
                                                            disabled
                                                            title="<?= e($esMismoAdmin ? 'No puede reiniciar su propia contraseña desde este panel' : 'No se permite reiniciar contraseñas de cuentas de administrador') ?>">
                                                        <span class="material-symbols-outlined fs-6 text-muted">lock</span>
                                                        <span>Reiniciar</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-warning text-dark fw-bold d-inline-flex align-items-center gap-1 shadow-sm"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modalReiniciarPassword"
                                                            data-id="<?= e($u['id']) ?>"
                                                            data-tipo="<?= e($u['tipo_entidad']) ?>"
                                                            data-nombre="<?= e($u['nombre_completo']) ?>"
                                                            data-cedula="<?= e($u['cedula']) ?>"
                                                            data-rol="<?= e($u['rol_texto']) ?>"
                                                            onclick="configurarModalReinicio(this)">
                                                        <span class="material-symbols-outlined fs-6">lock_reset</span>
                                                        <span>Reiniciar</span>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($paginacion['totalPaginas'] > 1): ?>
                        <div class="card-footer bg-white border-top py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <small class="text-muted">
                                Mostrando página <strong><?= e($paginacion['pagina']) ?></strong> de <strong><?= e($paginacion['totalPaginas']) ?></strong> (Total: <?= e($paginacion['total']) ?> registros)
                            </small>
                            <nav aria-label="Navegación de páginas">
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $queryParams = [];
                                    if (!empty($buscar)) $queryParams['buscar'] = $buscar;
                                    if (!empty($rol)) $queryParams['rol'] = $rol;
                                    $buildPageUrl = function($p) use ($queryParams) {
                                        return '/admin/usuarios?' . http_build_query(array_merge($queryParams, ['page' => $p]));
                                    };
                                    ?>
                                    <!-- Anterior -->
                                    <li class="page-item <?= ($paginacion['pagina'] <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $buildPageUrl($paginacion['pagina'] - 1) ?>">
                                            <span class="material-symbols-outlined fs-6 align-middle">chevron_left</span>
                                        </a>
                                    </li>

                                    <!-- Páginas numéricas -->
                                    <?php 
                                    $startPage = max(1, $paginacion['pagina'] - 2);
                                    $endPage = min($paginacion['totalPaginas'], $paginacion['pagina'] + 2);
                                    for ($i = $startPage; $i <= $endPage; $i++): 
                                    ?>
                                        <li class="page-item <?= ($paginacion['pagina'] === $i) ? 'active font-bold' : '' ?>">
                                            <a class="page-link" href="<?= $buildPageUrl($i) ?>"><?= e($i) ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <!-- Siguiente -->
                                    <li class="page-item <?= ($paginacion['pagina'] >= $paginacion['totalPaginas']) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $buildPageUrl($paginacion['pagina'] + 1) ?>">
                                            <span class="material-symbols-outlined fs-6 align-middle">chevron_right</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
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
</script>
