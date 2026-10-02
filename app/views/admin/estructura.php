<div class="flex flex-1 min-h-screen w-full">
    <?php $activeRoute = 'estructura'; require VIEWS_PATH . '/layouts/admin_sidebar.php'; ?>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra superior -->
        <header class="bg-white border-b border-outline-variant h-16 px-6 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-600 hover:bg-background rounded-lg flex items-center justify-center">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="text-xl font-bold text-on-surface">Estructura del Conjunto</h1>
            </div>
            <a href="/admin/logout" onclick="return confirmarCierreSesion(event, this.href);" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold p-2.5 rounded-lg border border-red-200 transition-colors flex items-center justify-center" title="Cerrar Sesión">
                <span class="material-symbols-outlined text-[18px]">logout</span>
            </a>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="max-w-7xl mx-auto space-y-8">

                <!-- Mensajes Flash -->
                <?php $mensaje = \App\Core\Flash::get('success'); $error = \App\Core\Flash::get('danger'); ?>
                <?php if (!empty($mensaje)): ?>
                    <div class="bg-green-50 text-green-700 border border-green-200 p-4 rounded-xl flex items-center gap-2 text-sm font-medium">
                        <span class="material-symbols-outlined text-green-600 text-[20px]">check_circle</span>
                        <span><?= e($mensaje) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 text-red-700 border border-red-200 p-4 rounded-xl flex items-center gap-2 text-sm font-medium">
                        <span class="material-symbols-outlined text-red-600 text-[20px]">error</span>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- ESTILOS ESPECÍFICOS PARA PESTAÑAS Y NAVEGACIÓN -->
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
                .fila-edificio {
                    transition: background-color 0.2s ease-in-out;
                }
                .fila-unidades-collapse,
                .fila-unidades-collapse.collapse,
                .fila-unidades-collapse.show,
                .fila-unidades-collapse * {
                    visibility: visible !important;
                }
                tr.fila-unidades-contenedor > td {
                    padding: 0 !important;
                    border: none !important;
                }
                </style>

                <!-- NAVEGACIÓN EN DOS PARTES: VISUALIZACIÓN VS CONFIGURACIÓN INICIAL -->
                <div class="bg-white rounded-2xl border border-outline-variant p-2 shadow-sm">
                    <ul class="nav nav-pills nav-fill gap-2" id="estructuraTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($tabActual === 'visualizacion') ? 'active' : '' ?> py-3 px-4 rounded-xl font-bold flex items-center justify-center gap-2" 
                                    id="tab-visualizacion-btn" 
                                    data-bs-toggle="pill" 
                                    data-bs-target="#tab-visualizacion" 
                                    type="button" 
                                    role="tab" 
                                    aria-controls="tab-visualizacion" 
                                    aria-selected="<?= ($tabActual === 'visualizacion') ? 'true' : 'false' ?>">
                                <span class="material-symbols-outlined text-[20px]">apartment</span>
                                <span>Visualización de Unidades y Residentes</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($tabActual === 'configuracion') ? 'active' : '' ?> py-3 px-4 rounded-xl font-bold flex items-center justify-center gap-2" 
                                    id="tab-configuracion-btn" 
                                    data-bs-toggle="pill" 
                                    data-bs-target="#tab-configuracion" 
                                    type="button" 
                                    role="tab" 
                                    aria-controls="tab-configuracion" 
                                    aria-selected="<?= ($tabActual === 'configuracion') ? 'true' : 'false' ?>">
                                <span class="material-symbols-outlined text-[20px]">settings_suggest</span>
                                <span>Configuración del Sistema (Creación de Unidades)</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="estructuraTabsContent">
                    <!-- ========================================================================= -->
                    <!-- PARTE 1: VISUALIZACIÓN Y DIRECTORIO DE UNIDADES (CONSULTA Y RESIDENTES)    -->
                    <!-- ========================================================================= -->
                    <div class="tab-pane fade <?= ($tabActual === 'visualizacion') ? 'show active' : '' ?> space-y-6" id="tab-visualizacion" role="tabpanel" aria-labelledby="tab-visualizacion-btn">
                        <section class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm space-y-6">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-background pb-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary text-2xl">apartment</span>
                                        <h3 class="text-base font-bold text-on-surface">Directorio y Visualización de Unidades</h3>
                                        <span class="bg-background text-on-surface-variant text-xs font-bold px-2.5 py-1 rounded-full"><?= count($edificios) ?> edificios</span>
                                    </div>
                                    <p class="text-xs text-on-surface-variant mt-1">Directorio organizado por edificios. Haz clic sobre cualquier edificio para consultar sus unidades y residentes.</p>
                                </div>

                                <!-- Filtro por Edificio y Buscador en Vivo -->
                                <div class="flex items-center gap-3 flex-wrap">
                                    <form method="GET" action="/admin/estructura" class="flex items-center gap-2 w-full sm:w-auto">
                                        <input type="hidden" name="tab" value="visualizacion">
                                        <label for="filtro_edificio" class="text-xs font-bold text-on-surface-variant whitespace-nowrap">Filtrar por Edificio:</label>
                                        <select id="filtro_edificio" name="edificio_id" onchange="this.form.submit()" class="w-full sm:w-auto bg-background border border-outline-variant text-on-surface text-xs font-bold rounded-xl px-3 py-2 focus:outline-none focus:border-primary">
                                            <option value="0">Todos los Edificios</option>
                                            <?php foreach ($edificios as $ed): ?>
                                                <option value="<?= e($ed['id']) ?>" <?= $filtroEdificio == $ed['id'] ? 'selected' : '' ?>>
                                                    <?= e($ed['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <div class="relative w-full sm:w-64">
                                        <span class="material-symbols-outlined absolute left-3 top-2.5 text-on-surface-variant/60 text-sm">search</span>
                                        <input type="text" id="buscadorVisualizacion" oninput="filtrarTablaVisualizacion(this.value)" placeholder="Buscar edificio, unidad o residente..."
                                               class="w-full pl-9 pr-3 py-2 bg-background border border-outline-variant rounded-xl text-xs font-medium focus:outline-none focus:border-primary focus:bg-white transition-all">
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($edificios)): ?>
                                <div class="text-center py-12 bg-background/50 rounded-2xl border border-dashed border-outline-variant">
                                    <span class="material-symbols-outlined text-on-surface-variant/40 text-4xl mb-2">location_city</span>
                                    <p class="text-on-surface-variant font-semibold">No se encontraron edificios registrados en el conjunto.</p>
                                    <p class="text-xs text-on-surface-variant/70 mt-1 max-w-md mx-auto">
                                        La creación de edificios y unidades se realiza durante la configuración inicial del sistema.
                                    </p>
                                    <button type="button" onclick="document.getElementById('tab-configuracion-btn').click()" class="mt-3 inline-flex items-center gap-1.5 text-primary text-xs font-bold hover:underline bg-primary/10 px-4 py-2 rounded-xl transition-all">
                                        <span class="material-symbols-outlined text-sm">settings_suggest</span>
                                        <span>Ir a Configuración del Sistema para registrar edificios</span>
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table id="tablaDirectorioEdificios" class="w-full text-left text-sm border-collapse" style="table-layout: fixed; width: 100%;">
                                        <thead>
                                            <tr class="border-b border-background bg-background/50 text-on-surface-variant font-bold text-xs uppercase tracking-wider">
                                                <th class="p-3.5" style="width: 40%;">Edificio</th>
                                                <th class="p-3.5" style="width: 20%;">Unidades</th>
                                                <th class="p-3.5" style="width: 20%;">Residentes</th>
                                                <th class="p-3.5 text-right pe-4" style="width: 20%;">Detalle</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-background">
                                            <?php foreach ($edificios as $ed): ?>
                                                <?php 
                                                $unidadesEdificio = $ed['unidades_list'] ?? [];
                                                $totalUnidades = count($unidadesEdificio);
                                                $totalRes = $ed['total_residentes'] ?? 0;
                                                $estaAbierto = ($filtroEdificio === (int)$ed['id']);
                                                ?>
                                                <tr class="fila-edificio hover:bg-background/40 transition-colors cursor-pointer" 
                                                    data-collapse-target="#collapse-edificio-<?= e($ed['id']) ?>" 
                                                    aria-expanded="<?= $estaAbierto ? 'true' : 'false' ?>"
                                                    data-busqueda="<?= strtolower(e($ed['nombre'] . ' ' . ($ed['descripcion'] ?? '') . ' ' . implode(' ', array_column($unidadesEdificio, 'numero')))) ?>">
                                                    <td class="p-3.5 font-bold text-on-surface">
                                                        <div class="flex items-center gap-2.5">
                                                            <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                                                <span class="material-symbols-outlined text-lg">domain</span>
                                                            </div>
                                                            <div>
                                                                <span class="text-sm font-bold text-on-surface d-block"><?= e($ed['nombre']) ?></span>
                                                                <?php if (!empty($ed['descripcion'])): ?>
                                                                    <span class="text-xs text-on-surface-variant font-normal"><?= e($ed['descripcion']) ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="p-3.5">
                                                        <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-800 text-xs font-bold px-2.5 py-1 rounded-full border border-slate-200">
                                                            <span class="material-symbols-outlined text-[15px]">apartment</span>
                                                            <span><?= e($totalUnidades) ?> unidades</span>
                                                        </span>
                                                    </td>
                                                    <td class="p-3.5">
                                                        <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-bold px-2.5 py-1 rounded-full border border-blue-200">
                                                            <span class="material-symbols-outlined text-[15px]">groups</span>
                                                            <span><?= e($totalRes) ?> residentes</span>
                                                        </span>
                                                    </td>
                                                    <td class="p-3.5 text-right pe-4">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 text-xs font-bold rounded-xl btn-toggle-unidades"
                                                                data-bs-toggle="collapse" 
                                                                data-bs-target="#collapse-edificio-<?= e($ed['id']) ?>"
                                                                aria-expanded="<?= $estaAbierto ? 'true' : 'false' ?>"
                                                                title="Ver unidades de <?= e($ed['nombre']) ?>">
                                                            <span class="btn-text"><?= $estaAbierto ? 'Ocultar' : 'Ver Unidades' ?></span>
                                                            <span class="material-symbols-outlined text-sm chevron-icon"><?= $estaAbierto ? 'expand_less' : 'expand_more' ?></span>
                                                        </button>
                                                    </td>
                                                </tr>

                                                <!-- DESPLIEGUE DRILL-DOWN: DETALLE DE UNIDADES DEL EDIFICIO SELECCIONADO -->
                                                <tr class="fila-unidades-contenedor bg-slate-50/70">
                                                    <td colspan="4" class="p-0 border-0">
                                                        <div class="collapse <?= $estaAbierto ? 'show' : '' ?> fila-unidades-collapse" id="collapse-edificio-<?= e($ed['id']) ?>">
                                                            <div class="p-4">
                                                                <div class="bg-white rounded-xl border border-outline-variant p-4 shadow-xs space-y-3">
                                                            <div class="flex items-center justify-between border-b border-background pb-2.5 flex-wrap gap-2">
                                                                <div class="flex items-center gap-2">
                                                                    <span class="material-symbols-outlined text-primary text-base">roofing</span>
                                                                    <h5 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-0">
                                                                        Unidades pertenecientes a <?= e($ed['nombre']) ?>
                                                                    </h5>
                                                                    <span class="badge bg-slate-100 text-slate-700 border border-slate-200 text-[11px]"><?= e($totalUnidades) ?> unidades</span>
                                                                </div>
                                                                <span class="text-xs text-on-surface-variant font-medium">Directorio habitacional</span>
                                                            </div>

                                                            <?php if (empty($unidadesEdificio)): ?>
                                                                <div class="text-center py-6 text-on-surface-variant">
                                                                    <span class="material-symbols-outlined text-3xl text-slate-300 mb-1">home</span>
                                                                    <p class="text-xs font-semibold mb-0">No hay unidades registradas en este edificio.</p>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="overflow-x-auto">
                                                                    <table class="w-full text-left text-xs border-collapse">
                                                                        <thead>
                                                                            <tr class="border-b border-background bg-background/50 text-on-surface-variant font-bold text-[11px] uppercase tracking-wider">
                                                                                <th class="py-2.5 px-3">Unidad</th>
                                                                                <th class="py-2.5 px-3">Propietario / Residente Principal</th>
                                                                                <th class="py-2.5 px-3">Cédula</th>
                                                                                <th class="py-2.5 px-3">Contacto</th>
                                                                                <th class="py-2.5 px-3 text-center">Total Habitantes</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody class="divide-y divide-background">
                                                                            <?php foreach ($unidadesEdificio as $u): ?>
                                                                                <?php 
                                                                                $residentes = $u['residentes'] ?? [];
                                                                                $titular = null;
                                                                                foreach ($residentes as $r) {
                                                                                    if (!empty($r['es_titular'])) {
                                                                                        $titular = $r;
                                                                                        break;
                                                                                    }
                                                                                }
                                                                                if (!$titular && !empty($residentes)) {
                                                                                    $titular = $residentes[0];
                                                                                }
                                                                                ?>
                                                                                <tr class="hover:bg-slate-50 transition-colors">
                                                                                    <td class="py-2.5 px-3 font-bold text-on-surface">
                                                                                        <span class="inline-flex items-center gap-1 bg-background px-2.5 py-1 rounded-lg border border-outline-variant font-bold text-xs">
                                                                                            <span class="material-symbols-outlined text-[14px] text-primary">door_front</span>
                                                                                            <?= e($u['numero']) ?>
                                                                                        </span>
                                                                                    </td>
                                                                                    <td class="py-2.5 px-3 font-semibold text-on-surface">
                                                                                        <?php if ($titular): ?>
                                                                                            <span><?= e($titular['nombre'] . ' ' . $titular['apellido']) ?></span>
                                                                                            <?php if (!empty($titular['es_titular'])): ?>
                                                                                                <span class="text-[10px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded font-bold ms-1">Titular</span>
                                                                                            <?php endif; ?>
                                                                                        <?php else: ?>
                                                                                            <span class="text-slate-400 italic">Sin habitante asignado</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                    <td class="py-2.5 px-3 font-mono text-on-surface-variant">
                                                                                        <?= e($titular['cedula'] ?? '—') ?>
                                                                                    </td>
                                                                                    <td class="py-2.5 px-3 text-on-surface-variant">
                                                                                        <?php if (!empty($titular['telefono'])): ?>
                                                                                            <div><span class="material-symbols-outlined text-[12px] align-middle">phone</span> <?= e($titular['telefono']) ?></div>
                                                                                        <?php endif; ?>
                                                                                        <?php if (!empty($titular['email'])): ?>
                                                                                            <div><span class="material-symbols-outlined text-[12px] align-middle">mail</span> <?= e($titular['email']) ?></div>
                                                                                        <?php endif; ?>
                                                                                        <?php if (empty($titular['telefono']) && empty($titular['email'])): ?>
                                                                                            <span class="text-slate-400">—</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                    <td class="py-2.5 px-3 text-center">
                                                                                        <span class="badge bg-light text-dark border border-secondary-subtle rounded-pill px-2.5 py-1 font-bold">
                                                                                            <?= count($residentes) ?> res.
                                                                                        </span>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div id="sinResultadosBusqueda" class="hidden text-center py-8 text-on-surface-variant text-xs font-semibold">
                                        No se encontraron edificios o unidades que coincidan con la búsqueda.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>

                    <!-- ========================================================================= -->
                    <!-- PARTE 2: CONFIGURACIÓN INICIAL DEL SISTEMA (CREACIÓN DE UNIDADES Y TORRES) -->
                    <!-- ========================================================================= -->
                    <div class="tab-pane fade <?= ($tabActual === 'configuracion') ? 'show active' : '' ?> space-y-6" id="tab-configuracion" role="tabpanel" aria-labelledby="tab-configuracion-btn">
                        
                        <!-- Banner Informativo de Configuración Inicial -->
                        <div class="bg-blue-50/80 border border-blue-200 rounded-2xl p-5 shadow-sm flex items-start gap-4">
                            <div class="bg-blue-100 text-blue-700 p-2.5 rounded-xl shrink-0">
                                <span class="material-symbols-outlined text-2xl">settings_suggest</span>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-blue-950 mb-1">Módulo de Configuración Inicial del Sistema</h4>
                                <p class="text-xs text-blue-800 leading-relaxed">
                                    Este entorno está destinado a la <strong>parametrización estructural inicial del condominio</strong>. 
                                    Aquí se registran los <strong>Edificios o Torres</strong> y se realiza la <strong>creación y alta de unidades habitacionales</strong> (apartamentos, penthouses, locales) con sus cuotas base. 
                                    Una vez configurada la estructura física, las operaciones habituales (asignación de inquilinos, propietarios y consulta) se realizan en la pestaña de <strong>Visualización de Unidades</strong>.
                                </p>
                            </div>
                        </div>

                        <!-- SECCIÓN 1: EDIFICIOS / TORRES -->
                        <section class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm space-y-6">
                            <div class="flex items-center justify-between border-b border-background pb-4 flex-wrap gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary text-xl">corporate_fare</span>
                                        <h3 class="text-base font-bold text-on-surface">Paso 1: Edificios / Torres</h3>
                                        <span class="bg-background text-on-surface-variant text-xs font-bold px-2.5 py-1 rounded-full"><?= count($edificios) ?></span>
                                    </div>
                                    <p class="text-xs text-on-surface-variant mt-1">Registra los bloques o torres que componen el condominio.</p>
                                </div>
                                <button onclick="openModalEdificio('configuracion')" class="inline-flex items-center gap-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl transition-all shadow-sm">
                                    <span class="material-symbols-outlined text-[16px]">add_business</span>
                                    <span>Agregar Edificio</span>
                                </button>
                            </div>

                            <?php if (empty($edificios)): ?>
                                <div class="text-center py-10 bg-background/50 rounded-2xl border border-dashed border-outline-variant">
                                    <span class="material-symbols-outlined text-on-surface-variant/40 text-4xl mb-2">location_city</span>
                                    <p class="text-on-surface-variant font-semibold">No hay edificios registrados en el sistema.</p>
                                    <button onclick="openModalEdificio('configuracion')" class="mt-2 text-primary text-xs font-bold hover:underline">Registrar el primer edificio</button>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php foreach ($edificios as $edificio): ?>
                                        <div class="border border-outline-variant rounded-xl p-5 hover:border-primary/40 transition-all bg-white flex flex-col justify-between shadow-sm">
                                            <div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <h4 class="text-sm font-bold text-on-surface leading-tight"><?= e($edificio['nombre']) ?></h4>
                                                    <?php if ($edificio['estado'] == 1): ?>
                                                        <span class="bg-green-50 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-md">Activo</span>
                                                    <?php else: ?>
                                                        <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-md">Inactivo</span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-on-surface-variant text-xs mt-2 min-h-[32px] leading-relaxed">
                                                    <?= !empty($edificio['descripcion']) ? e($edificio['descripcion']) : 'Sin descripción detallada' ?>
                                                </p>
                                            </div>

                                            <div class="mt-4 pt-3 border-t border-background flex items-center justify-between">
                                                <div class="flex items-center gap-1 text-on-surface-variant text-xs">
                                                    <span class="material-symbols-outlined text-base">roofing</span>
                                                    <span><?= intval($edificio['total_unidades']) ?> unidades asignadas</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <button type="button" 
                                                            data-id="<?= e($edificio['id']) ?>" 
                                                            data-nombre="<?= e($edificio['nombre']) ?>" 
                                                            data-descripcion="<?= e($edificio['descripcion'] ?? '') ?>" 
                                                            onclick="editEdificio(this.dataset.id, this.dataset.nombre, this.dataset.descripcion, 'configuracion')" 
                                                            class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-background rounded-lg transition-colors" 
                                                            title="Editar Edificio">
                                                        <span class="material-symbols-outlined text-lg">edit</span>
                                                    </button>
                                                    <form method="POST" action="/admin/estructura/edificio/toggle" style="display:inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="tab" value="configuracion">
                                                        <input type="hidden" name="id" value="<?= e($edificio['id']) ?>">
                                                        <button type="submit" class="p-1.5 text-on-surface-variant hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Cambiar Estado">
                                                            <span class="material-symbols-outlined text-lg">power_settings_new</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>

                        <!-- SECCIÓN 2: CREACIÓN Y ALTA DE UNIDADES -->
                        <section class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm space-y-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-background pb-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary text-xl">add_home_work</span>
                                        <h3 class="text-base font-bold text-on-surface">Paso 2: Creación y Alta de Unidades</h3>
                                        <span class="bg-background text-on-surface-variant text-xs font-bold px-2.5 py-1 rounded-full"><?= count($unidades) ?> creadas</span>
                                    </div>
                                    <p class="text-xs text-on-surface-variant mt-1">Crea los apartamentos o locales para cada torre del conjunto residencial.</p>
                                </div>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <div class="relative w-full sm:w-64">
                                        <span class="material-symbols-outlined absolute left-3 top-2.5 text-on-surface-variant/60 text-sm">search</span>
                                        <input type="text" id="buscadorConfiguracion" oninput="filtrarTablaConfiguracion(this.value)" placeholder="Buscar edificio o unidad..."
                                               class="w-full pl-9 pr-3 py-2 bg-background border border-outline-variant rounded-xl text-xs font-medium focus:outline-none focus:border-primary focus:bg-white transition-all">
                                    </div>
                                    <?php if (empty($edificios)): ?>
                                        <button disabled class="inline-flex items-center gap-1.5 bg-slate-300 text-slate-500 font-bold text-xs px-4 py-2.5 rounded-xl cursor-not-allowed shadow-sm" title="Debes registrar al menos un edificio primero">
                                            <span class="material-symbols-outlined text-[16px]">add_home</span>
                                            <span>Crear Unidad (Requiere Edificio)</span>
                                        </button>
                                    <?php else: ?>
                                        <button onclick="openModalUnidad('configuracion')" class="inline-flex items-center gap-1.5 bg-primary hover:bg-primary-hover text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-sm">
                                            <span class="material-symbols-outlined text-[16px]">add_home</span>
                                            <span>+ Crear Nueva Unidad</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Tabla de Desglose de Unidades por Edificio en Configuración -->
                            <?php if (empty($edificios)): ?>
                                <div class="text-center py-10 bg-background/50 rounded-2xl border border-dashed border-outline-variant">
                                    <span class="material-symbols-outlined text-on-surface-variant/40 text-4xl mb-2">roofing</span>
                                    <p class="text-on-surface-variant font-semibold">Primero debes registrar al menos un edificio en el Paso 1.</p>
                                    <button onclick="openModalEdificio('configuracion')" class="mt-2 text-primary text-xs font-bold hover:underline">+ Crear primer edificio</button>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table id="tablaConfiguracionEdificios" class="w-full text-left text-sm border-collapse" style="table-layout: fixed; width: 100%;">
                                        <thead>
                                            <tr class="border-b border-background bg-background/50 text-on-surface-variant font-bold text-xs uppercase tracking-wider">
                                                <th class="p-3.5" style="width: 45%;">Edificio</th>
                                                <th class="p-3.5" style="width: 25%;">Unidades Registradas</th>
                                                <th class="p-3.5 text-right pe-4" style="width: 30%;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-background">
                                            <?php foreach ($edificios as $ed): ?>
                                                <?php 
                                                $unidadesEdificio = $ed['unidades_list'] ?? [];
                                                $totalUnidades = count($unidadesEdificio);
                                                ?>
                                                <tr class="fila-edificio hover:bg-background/40 transition-colors cursor-pointer" 
                                                    data-collapse-target="#collapse-config-edificio-<?= e($ed['id']) ?>" 
                                                    aria-expanded="false"
                                                    data-busqueda="<?= strtolower(e($ed['nombre'] . ' ' . ($ed['descripcion'] ?? '') . ' ' . implode(' ', array_column($unidadesEdificio, 'numero')))) ?>">
                                                    <td class="p-3.5 font-bold text-on-surface">
                                                        <div class="flex items-center gap-2.5">
                                                            <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                                                <span class="material-symbols-outlined text-lg">domain</span>
                                                            </div>
                                                            <div>
                                                                <span class="text-sm font-bold text-on-surface d-block"><?= e($ed['nombre']) ?></span>
                                                                <?php if (!empty($ed['descripcion'])): ?>
                                                                    <span class="text-xs text-on-surface-variant font-normal"><?= e($ed['descripcion']) ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="p-3.5">
                                                        <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-800 text-xs font-bold px-2.5 py-1 rounded-full border border-slate-200">
                                                            <span class="material-symbols-outlined text-[15px]">apartment</span>
                                                            <span><?= e($totalUnidades) ?> unidades</span>
                                                        </span>
                                                    </td>
                                                    <td class="p-3.5 text-right pe-4">
                                                        <div class="inline-flex items-center gap-2">
                                                            <button type="button" 
                                                                    onclick="openModalUnidad('configuracion', '<?= e($ed['id']) ?>')" 
                                                                    class="btn btn-sm btn-light border border-outline-variant d-inline-flex align-items-center gap-1 text-xs font-bold rounded-xl text-primary hover:bg-primary/5"
                                                                    title="Agregar unidad a <?= e($ed['nombre']) ?>">
                                                                <span class="material-symbols-outlined text-sm">add_home</span>
                                                                <span>+ Unidad</span>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 text-xs font-bold rounded-xl btn-toggle-unidades"
                                                                    data-bs-toggle="collapse" 
                                                                    data-bs-target="#collapse-config-edificio-<?= e($ed['id']) ?>" 
                                                                    aria-expanded="false"
                                                                    title="Ver unidades de <?= e($ed['nombre']) ?>">
                                                                <span class="btn-text">Ver Unidades</span>
                                                                <span class="material-symbols-outlined text-sm chevron-icon">expand_more</span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>

                                                <!-- DESPLIEGUE DRILL-DOWN: DETALLE DE CONFIGURACIÓN DE UNIDADES DEL EDIFICIO -->
                                                <tr class="fila-unidades-contenedor bg-slate-50/70">
                                                    <td colspan="3" class="p-0 border-0">
                                                        <div class="collapse fila-unidades-collapse" id="collapse-config-edificio-<?= e($ed['id']) ?>">
                                                            <div class="p-4">
                                                                <div class="bg-white rounded-xl border border-outline-variant p-4 shadow-xs space-y-3">
                                                                    <div class="flex items-center justify-between border-b border-background pb-2.5 flex-wrap gap-2">
                                                                        <div class="flex items-center gap-2">
                                                                            <span class="material-symbols-outlined text-primary text-base">roofing</span>
                                                                            <h5 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-0">
                                                                                Unidades pertenecientes a <?= e($ed['nombre']) ?>
                                                                            </h5>
                                                                            <span class="badge bg-slate-100 text-slate-700 border border-slate-200 text-[11px]"><?= e($totalUnidades) ?> registradas</span>
                                                                        </div>
                                                                        <button type="button" onclick="openModalUnidad('configuracion', '<?= e($ed['id']) ?>')" class="text-xs font-bold text-primary hover:underline inline-flex items-center gap-1">
                                                                            <span class="material-symbols-outlined text-sm">add</span>
                                                                            <span>Crear unidad en este edificio</span>
                                                                        </button>
                                                                    </div>

                                                                    <?php if (empty($unidadesEdificio)): ?>
                                                                        <div class="text-center py-6 text-on-surface-variant">
                                                                            <span class="material-symbols-outlined text-3xl text-slate-300 mb-1">home</span>
                                                                            <p class="text-xs font-semibold mb-2">No hay unidades registradas en este edificio.</p>
                                                                            <button type="button" onclick="openModalUnidad('configuracion', '<?= e($ed['id']) ?>')" class="inline-flex items-center gap-1 bg-primary text-white text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-primary-hover transition-colors shadow-xs">
                                                                                <span class="material-symbols-outlined text-sm">add_home</span>
                                                                                <span>+ Crear primera unidad en <?= e($ed['nombre']) ?></span>
                                                                            </button>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <div class="overflow-x-auto">
                                                                            <table class="w-full text-left text-xs border-collapse">
                                                                                <thead>
                                                                                    <tr class="border-b border-background bg-background/50 text-on-surface-variant font-bold text-[11px] uppercase tracking-wider">
                                                                                        <th class="py-2.5 px-3">Código / Unidad</th>
                                                                                        <th class="py-2.5 px-3">Cuota Mensual</th>
                                                                                        <th class="py-2.5 px-3">Estado</th>
                                                                                        <th class="py-2.5 px-3 text-right">Acciones de Configuración</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody class="divide-y divide-background">
                                                                                    <?php foreach ($unidadesEdificio as $u): ?>
                                                                                        <tr class="hover:bg-slate-50 transition-colors">
                                                                                            <td class="py-2.5 px-3 font-bold text-on-surface">
                                                                                                <span class="inline-flex items-center gap-1 bg-background px-2.5 py-1 rounded-lg border border-outline-variant font-bold text-xs">
                                                                                                    <span class="material-symbols-outlined text-[14px] text-primary">door_front</span>
                                                                                                    <?= e($u['numero']) ?>
                                                                                                </span>
                                                                                            </td>
                                                                                            <td class="py-2.5 px-3 font-medium text-on-surface">
                                                                                                <?= !empty($u['cuota_mensual']) ? e(formatearMoneda($u['cuota_mensual'])) : 'Bs. 0,00' ?>
                                                                                            </td>
                                                                                            <td class="py-2.5 px-3">
                                                                                                <?php if ($u['estado'] == 1): ?>
                                                                                                    <span class="bg-green-50 text-green-700 text-xs font-bold px-2 py-0.5 rounded-md">Activa</span>
                                                                                                <?php else: ?>
                                                                                                    <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-md">Inactiva</span>
                                                                                                <?php endif; ?>
                                                                                            </td>
                                                                                            <td class="py-2.5 px-3 text-right">
                                                                                                <div class="inline-flex items-center gap-1">
                                                                                                    <button type="button" 
                                                                                                            data-id="<?= e($u['id']) ?>" 
                                                                                                            data-numero="<?= e($u['numero']) ?>" 
                                                                                                            data-edificio-id="<?= e($u['edificio_id']) ?>" 
                                                                                                            onclick="editUnidad(this.dataset.id, this.dataset.numero, this.dataset.edificioId, 'configuracion')" 
                                                                                                            class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-background rounded-lg transition-colors" 
                                                                                                            title="Editar Unidad">
                                                                                                        <span class="material-symbols-outlined text-base">edit</span>
                                                                                                    </button>
                                                                                                    <form method="POST" action="/admin/estructura/unidad/toggle" style="display:inline">
                                                                                                        <?= csrf_field() ?>
                                                                                                        <input type="hidden" name="tab" value="configuracion">
                                                                                                        <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                                                                                        <button type="submit" class="p-1.5 text-on-surface-variant hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Cambiar Estado">
                                                                                                            <span class="material-symbols-outlined text-base">power_settings_new</span>
                                                                                                        </button>
                                                                                                    </form>
                                                                                                </div>
                                                                                            </td>
                                                                                        </tr>
                                                                                    <?php endforeach; ?>
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div id="sinResultadosConfiguracion" class="hidden text-center py-8 text-on-surface-variant text-xs font-semibold">
                                        No se encontraron edificios o unidades que coincidan con la búsqueda.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- MODAL: AGREGAR / EDITAR EDIFICIO -->
<div id="modalEdificio" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-5 transform transition-all border border-outline-variant">
        <div class="flex items-center justify-between border-b border-background pb-3">
            <h3 id="modalEdificioTitle" class="text-base font-bold text-on-surface">Agregar Edificio</h3>
            <button onclick="closeModalEdificio()" class="text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="/admin/estructura/edificio/guardar" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" id="edificio_tab_input" name="tab" value="configuracion">
            <input type="hidden" id="edificio_id_input" name="id" value="0">

            <div class="flex flex-col gap-1">
                <label for="edificio_nombre" class="text-xs font-bold text-on-surface-variant uppercase">Nombre / Identificador *</label>
                <input type="text" id="edificio_nombre" name="nombre" required placeholder="Ej: Torre A"
                       class="w-full px-3.5 py-2.5 bg-background border border-outline-variant rounded-xl text-on-surface font-medium focus:outline-none focus:border-primary focus:bg-white text-sm">
            </div>

            <div class="flex flex-col gap-1">
                <label for="edificio_descripcion" class="text-xs font-bold text-on-surface-variant uppercase">Descripción (Opcional)</label>
                <textarea id="edificio_descripcion" name="descripcion" rows="3" placeholder="Ej: Edificio residencial de 10 pisos"
                          class="w-full px-3.5 py-2.5 bg-background border border-outline-variant rounded-xl text-on-surface font-medium focus:outline-none focus:border-primary focus:bg-white text-sm"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-background">
                <button type="button" onclick="closeModalEdificio()" class="px-4 py-2 rounded-xl text-xs font-bold text-on-surface-variant hover:bg-background transition-colors">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-primary hover:bg-primary-hover text-white shadow-sm transition-colors">Guardar Edificio</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: AGREGAR / EDITAR UNIDAD -->
<div id="modalUnidad" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-5 transform transition-all border border-outline-variant">
        <div class="flex items-center justify-between border-b border-background pb-3">
            <h3 id="modalUnidadTitle" class="text-base font-bold text-on-surface">Agregar Unidad</h3>
            <button onclick="closeModalUnidad()" class="text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="/admin/estructura/unidad/guardar" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" id="unidad_tab_input" name="tab" value="configuracion">
            <input type="hidden" id="unidad_id_input" name="id" value="0">

            <div class="flex flex-col gap-1">
                <label for="unidad_numero" class="text-xs font-bold text-on-surface-variant uppercase">Código / Número *</label>
                <input type="text" id="unidad_numero" name="numero" required placeholder="Ej: A-101"
                       class="w-full px-3.5 py-2.5 bg-background border border-outline-variant rounded-xl text-on-surface font-medium focus:outline-none focus:border-primary focus:bg-white text-sm">
            </div>

            <div class="flex flex-col gap-1">
                <label for="unidad_edificio_id" class="text-xs font-bold text-on-surface-variant uppercase">Edificio / Torre *</label>
                <select id="unidad_edificio_id" name="edificio_id" required
                        class="w-full px-3.5 py-2.5 bg-background border border-outline-variant rounded-xl text-on-surface font-medium focus:outline-none focus:border-primary focus:bg-white text-sm cursor-pointer">
                    <option value="">Seleccione un edificio...</option>
                    <?php foreach ($edificios as $ed): ?>
                        <option value="<?= e($ed['id']) ?>"><?= e($ed['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Asignación automática de puesto de estacionamiento -->
            <div id="contenedor_auto_estacionamiento" class="pt-2 border-t border-background">
                <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-bold text-on-surface-variant uppercase select-none">
                    <input type="checkbox" id="asignar_estacionamiento" name="asignar_estacionamiento" value="1"
                           class="w-4 h-4 rounded text-primary focus:ring-primary/20 border-outline-variant cursor-pointer" checked>
                    <span>Asignar puesto de estacionamiento automáticamente</span>
                </label>
                <p class="text-xs text-on-surface-variant mt-1 pl-6 normal-case font-normal">
                    Se creará y vinculará un puesto con nomenclatura <strong>Puesto - [Número]</strong> a esta unidad.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-background">
                <button type="button" onclick="closeModalUnidad()" class="px-4 py-2 rounded-xl text-xs font-bold text-on-surface-variant hover:bg-background transition-colors">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-primary hover:bg-primary-hover text-white shadow-sm transition-colors">Guardar Unidad</button>
            </div>
        </form>
    </div>
</div>

<script>

function openModalEdificio(tab = 'configuracion') {
    document.getElementById('edificio_id_input').value = '0';
    document.getElementById('edificio_tab_input').value = tab;
    document.getElementById('edificio_nombre').value = '';
    document.getElementById('edificio_descripcion').value = '';
    document.getElementById('modalEdificioTitle').innerText = 'Agregar Edificio';
    const m = document.getElementById('modalEdificio');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function editEdificio(id, nombre, descripcion, tab = 'configuracion') {
    document.getElementById('edificio_id_input').value = id;
    document.getElementById('edificio_tab_input').value = tab;
    document.getElementById('edificio_nombre').value = nombre || '';
    document.getElementById('edificio_descripcion').value = descripcion || '';
    document.getElementById('modalEdificioTitle').innerText = 'Editar Edificio';
    const m = document.getElementById('modalEdificio');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeModalEdificio() {
    const m = document.getElementById('modalEdificio');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

function openModalUnidad(tab = 'configuracion', edificioId = '') {
    document.getElementById('unidad_id_input').value = '0';
    document.getElementById('unidad_tab_input').value = tab;
    document.getElementById('unidad_numero').value = '';
    document.getElementById('unidad_edificio_id').value = edificioId ? String(edificioId) : '';
    const autoBox = document.getElementById('contenedor_auto_estacionamiento');
    if (autoBox) autoBox.classList.remove('hidden');
    const chk = document.getElementById('asignar_estacionamiento');
    if (chk) chk.checked = true;
    document.getElementById('modalUnidadTitle').innerText = 'Agregar Unidad';
    const m = document.getElementById('modalUnidad');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function editUnidad(id, numero, edificioId, tab = 'configuracion') {
    document.getElementById('unidad_id_input').value = id;
    document.getElementById('unidad_tab_input').value = tab;
    document.getElementById('unidad_numero').value = numero || '';
    document.getElementById('unidad_edificio_id').value = edificioId || '';
    const autoBox = document.getElementById('contenedor_auto_estacionamiento');
    if (autoBox) autoBox.classList.add('hidden');
    const chk = document.getElementById('asignar_estacionamiento');
    if (chk) chk.checked = false;
    document.getElementById('modalUnidadTitle').innerText = 'Editar Unidad';
    const m = document.getElementById('modalUnidad');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeModalUnidad() {
    const m = document.getElementById('modalUnidad');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// BÚSQUEDA Y FILTRADO EN VIVO EN DIRECTORIO DE EDIFICIOS Y UNIDADES
function filtrarTablaVisualizacion(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#tablaDirectorioEdificios tbody tr.fila-edificio');
    let visibles = 0;
    rows.forEach(row => {
        const targetId = row.getAttribute('data-collapse-target') || row.getAttribute('data-bs-target');
        const collapseEl = targetId ? document.querySelector(targetId) : null;
        const containerRow = collapseEl ? collapseEl.closest('tr.fila-unidades-contenedor') : null;
        const textoEdificio = (row.dataset.busqueda || row.innerText || '').toLowerCase();
        const textoUnidades = collapseEl ? (collapseEl.innerText || '').toLowerCase() : '';

        if (!q || textoEdificio.includes(q) || textoUnidades.includes(q)) {
            row.style.display = '';
            visibles++;
            if (containerRow) {
                containerRow.style.display = '';
            }
            if (q && textoUnidades.includes(q) && collapseEl) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                } else {
                    collapseEl.classList.add('show');
                }
            }
        } else {
            row.style.display = 'none';
            if (collapseEl) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).hide();
                } else {
                    collapseEl.classList.remove('show');
                }
            }
            if (containerRow) {
                containerRow.style.display = 'none';
            }
        }
    });

    const sinResultados = document.getElementById('sinResultadosBusqueda');
    if (sinResultados) {
        if (visibles === 0 && rows.length > 0) {
            sinResultados.classList.remove('hidden');
        } else {
            sinResultados.classList.add('hidden');
        }
    }
}

// BÚSQUEDA Y FILTRADO EN VIVO EN PASO 2 (CONFIGURACIÓN DE UNIDADES)
function filtrarTablaConfiguracion(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#tablaConfiguracionEdificios tbody tr.fila-edificio');
    let visibles = 0;
    rows.forEach(row => {
        const targetId = row.getAttribute('data-collapse-target') || row.getAttribute('data-bs-target');
        const collapseEl = targetId ? document.querySelector(targetId) : null;
        const containerRow = collapseEl ? collapseEl.closest('tr.fila-unidades-contenedor') : null;
        const textoEdificio = (row.dataset.busqueda || row.innerText || '').toLowerCase();
        const textoUnidades = collapseEl ? (collapseEl.innerText || '').toLowerCase() : '';

        if (!q || textoEdificio.includes(q) || textoUnidades.includes(q)) {
            row.style.display = '';
            visibles++;
            if (containerRow) {
                containerRow.style.display = '';
            }
            if (q && textoUnidades.includes(q) && collapseEl) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                } else {
                    collapseEl.classList.add('show');
                }
            }
        } else {
            row.style.display = 'none';
            if (collapseEl) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).hide();
                } else {
                    collapseEl.classList.remove('show');
                }
            }
            if (containerRow) {
                containerRow.style.display = 'none';
            }
        }
    });

    const sinResultados = document.getElementById('sinResultadosConfiguracion');
    if (sinResultados) {
        if (visibles === 0 && rows.length > 0) {
            sinResultados.classList.remove('hidden');
        } else {
            sinResultados.classList.add('hidden');
        }
    }
}

// CONTROL DEL COLAPSO Y EXPANSIÓN DE UNIDADES POR EDIFICIO
document.addEventListener('DOMContentLoaded', () => {
    // 1. Manejo del clic en la fila del edificio (sin conflicto con el botón)
    const filasEdificio = document.querySelectorAll('tr.fila-edificio');
    filasEdificio.forEach(row => {
        row.addEventListener('click', (e) => {
            // Si el clic fue directamente en el botón o en un enlace/input, dejar que actúe su propio evento
            if (e.target.closest('button, a, input, select, .btn')) {
                return;
            }
            const targetSelector = row.getAttribute('data-collapse-target');
            if (targetSelector) {
                const targetEl = document.querySelector(targetSelector);
                if (targetEl && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    bootstrap.Collapse.getOrCreateInstance(targetEl).toggle();
                }
            }
        });
    });

    // 2. Eventos show y hide para sincronizar icono y texto del botón
    const collapseElements = document.querySelectorAll('.fila-unidades-collapse');
    collapseElements.forEach(collapseEl => {
        collapseEl.addEventListener('show.bs.collapse', () => {
            const targetId = '#' + collapseEl.id;
            const triggerBtns = document.querySelectorAll(`[data-bs-target="${targetId}"], [data-collapse-target="${targetId}"]`);
            triggerBtns.forEach(btn => {
                const icon = btn.querySelector('.chevron-icon');
                if (icon) icon.textContent = 'expand_less';
                const textSpan = btn.querySelector('.btn-text');
                if (textSpan) textSpan.textContent = 'Ocultar';
                btn.setAttribute('aria-expanded', 'true');
            });
        });

        collapseEl.addEventListener('hide.bs.collapse', () => {
            const targetId = '#' + collapseEl.id;
            const triggerBtns = document.querySelectorAll(`[data-bs-target="${targetId}"], [data-collapse-target="${targetId}"]`);
            triggerBtns.forEach(btn => {
                const icon = btn.querySelector('.chevron-icon');
                if (icon) icon.textContent = 'expand_more';
                const textSpan = btn.querySelector('.btn-text');
                if (textSpan) textSpan.textContent = 'Ver Unidades';
                btn.setAttribute('aria-expanded', 'false');
            });
        });
    });
});

// SINCRONIZACIÓN Y PERSISTENCIA DE PESTAÑAS (TABS)
document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('#estructuraTabs button[data-bs-toggle="pill"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', (e) => {
            const targetId = e.target.getAttribute('data-bs-target');
            const tabName = targetId === '#tab-configuracion' ? 'configuracion' : 'visualizacion';
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        });
    });

    // Detectar hash o parámetro de tab inicial
    const params = new URLSearchParams(window.location.search);
    const activeTabParam = params.get('tab') || (window.location.hash === '#configuracion' ? 'configuracion' : '');
    if (activeTabParam === 'configuracion') {
        const configBtn = document.getElementById('tab-configuracion-btn');
        if (configBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance(configBtn).show();
        }
    }
});
</script>
