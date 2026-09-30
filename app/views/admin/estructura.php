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
                                        <span class="bg-background text-on-surface-variant text-xs font-bold px-2.5 py-1 rounded-full"><?= count($unidades) ?> registradas</span>
                                    </div>
                                    <p class="text-xs text-on-surface-variant mt-1">Consulta del inventario habitacional, alícuotas y administración diaria de propietarios e inquilinos.</p>
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
                                        <input type="text" id="buscadorVisualizacion" oninput="filtrarTablaVisualizacion(this.value)" placeholder="Buscar unidad o habitante..."
                                               class="w-full pl-9 pr-3 py-2 bg-background border border-outline-variant rounded-xl text-xs font-medium focus:outline-none focus:border-primary focus:bg-white transition-all">
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($unidades)): ?>
                                <div class="text-center py-12 bg-background/50 rounded-2xl border border-dashed border-outline-variant">
                                    <span class="material-symbols-outlined text-on-surface-variant/40 text-4xl mb-2">home</span>
                                    <p class="text-on-surface-variant font-semibold">No se encontraron unidades registradas en esta vista.</p>
                                    <p class="text-xs text-on-surface-variant/70 mt-1 max-w-md mx-auto">
                                        La creación de unidades se realiza durante la parametrización inicial del sistema.
                                    </p>
                                    <button type="button" onclick="document.getElementById('tab-configuracion-btn').click()" class="mt-3 inline-flex items-center gap-1.5 text-primary text-xs font-bold hover:underline bg-primary/10 px-4 py-2 rounded-xl transition-all">
                                        <span class="material-symbols-outlined text-sm">settings_suggest</span>
                                        <span>Ir a Configuración del Sistema para dar de alta unidades</span>
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table id="tablaVisualizacionUnidades" class="w-full text-left text-sm border-collapse">
                                        <thead>
                                            <tr class="border-b border-background bg-background/50 text-on-surface-variant font-bold text-xs uppercase tracking-wider">
                                                <th class="p-3.5">Código / Unidad</th>
                                                <th class="p-3.5">Edificio / Torre</th>
                                                <th class="p-3.5">Cuota Mensual</th>
                                                <th class="p-3.5">Residentes</th>
                                                <th class="p-3.5">Estado</th>
                                                <th class="p-3.5 text-right">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-background">
                                            <?php foreach ($unidades as $u): ?>
                                                <tr class="fila-unidad hover:bg-background/40 transition-colors" data-busqueda="<?= strtolower(e($u['numero'] . ' ' . ($u['edificio_nombre'] ?? '') . ' ' . implode(' ', array_column($u['residentes'] ?? [], 'nombre')) . ' ' . implode(' ', array_column($u['residentes'] ?? [], 'apellido')) . ' ' . implode(' ', array_column($u['residentes'] ?? [], 'cedula')))) ?>">
                                                    <td class="p-3.5 font-bold text-on-surface"><?= e($u['numero']) ?></td>
                                                    <td class="p-3.5 font-medium text-on-surface">
                                                        <span class="inline-flex items-center gap-1">
                                                            <span class="material-symbols-outlined text-on-surface-variant/70 text-base">domain</span>
                                                            <?= e($u['edificio_nombre'] ?? 'Sin asignar') ?>
                                                        </span>
                                                    </td>
                                                    <td class="p-3.5 font-bold text-primary"><?= e(formatearMoneda($u['cuota_mensual'])) ?></td>
                                                    <td class="p-3.5">
                                                        <button type="button" 
                                                                data-unidad-id="<?= e($u['id']) ?>" 
                                                                data-unidad-numero="<?= e($u['numero']) ?>" 
                                                                data-edificio-nombre="<?= e($u['edificio_nombre'] ?? 'Sin asignar') ?>" 
                                                                data-propietario-id="<?= e($u['propietario_id'] ?? 0) ?>" 
                                                                data-residentes="<?= htmlspecialchars(json_encode($u['residentes'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
                                                                onclick="openModalGestionResidentes(this)" 
                                                                class="bg-background hover:bg-slate-200 text-on-surface font-bold text-xs px-2.5 py-1 rounded-full border border-outline-variant inline-flex items-center gap-1.5 transition-all shadow-sm group cursor-pointer"
                                                                title="Gestionar Residentes de <?= e($u['numero']) ?>">
                                                            <span class="material-symbols-outlined text-[15px] text-primary group-hover:scale-110 transition-transform">group</span>
                                                            <span><?= intval($u['total_residentes'] ?? 0) ?> res.</span>
                                                        </button>
                                                    </td>
                                                    <td class="p-3.5">
                                                        <?php if ($u['estado'] == 1): ?>
                                                            <span class="bg-green-50 text-green-700 text-xs font-bold px-2 py-0.5 rounded-md">Activa</span>
                                                        <?php else: ?>
                                                            <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-md">Inactiva</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="p-3.5 text-right">
                                                        <div class="inline-flex items-center gap-1">
                                                            <button type="button" 
                                                                    data-unidad-id="<?= e($u['id']) ?>" 
                                                                    data-unidad-numero="<?= e($u['numero']) ?>" 
                                                                    data-edificio-nombre="<?= e($u['edificio_nombre'] ?? 'Sin asignar') ?>" 
                                                                    data-propietario-id="<?= e($u['propietario_id'] ?? 0) ?>" 
                                                                    data-residentes="<?= htmlspecialchars(json_encode($u['residentes'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
                                                                    onclick="openModalGestionResidentes(this)" 
                                                                    class="p-1.5 text-on-surface-variant hover:text-green-700 hover:bg-green-50 rounded-lg transition-colors" 
                                                                    title="Gestionar Residentes">
                                                                <span class="material-symbols-outlined text-lg">person_add</span>
                                                            </button>
                                                            <button type="button" 
                                                                    data-id="<?= e($u['id']) ?>" 
                                                                    data-numero="<?= e($u['numero']) ?>" 
                                                                    data-edificio-id="<?= e($u['edificio_id']) ?>" 
                                                                    data-cuota="<?= e($u['cuota_mensual']) ?>" 
                                                                    onclick="editUnidad(this.dataset.id, this.dataset.numero, this.dataset.edificioId, this.dataset.cuota, 'visualizacion')" 
                                                                    class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-background rounded-lg transition-colors" 
                                                                    title="Editar Datos de Unidad">
                                                                <span class="material-symbols-outlined text-lg">edit</span>
                                                            </button>
                                                            <form method="POST" action="/admin/estructura/unidad/toggle" style="display:inline">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="tab" value="visualizacion">
                                                                <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                                                <button type="submit" class="p-1.5 text-on-surface-variant hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Cambiar Estado">
                                                                    <span class="material-symbols-outlined text-lg">power_settings_new</span>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div id="sinResultadosBusqueda" class="hidden text-center py-8 text-on-surface-variant text-xs font-semibold">
                                        No se encontraron unidades que coincidan con la búsqueda.
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
                                    <p class="text-xs text-on-surface-variant mt-1">Crea los apartamentos o locales para cada torre y establece su cuota mensual base.</p>
                                </div>
                                <div>
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

                            <!-- Tabla de Unidades Creadas en Configuración -->
                            <?php if (empty($unidades)): ?>
                                <div class="text-center py-10 bg-background/50 rounded-2xl border border-dashed border-outline-variant">
                                    <span class="material-symbols-outlined text-on-surface-variant/40 text-4xl mb-2">roofing</span>
                                    <p class="text-on-surface-variant font-semibold">Aún no se han creado unidades en el conjunto.</p>
                                    <?php if (!empty($edificios)): ?>
                                        <button onclick="openModalUnidad('configuracion')" class="mt-2 text-primary text-xs font-bold hover:underline">Comenzar a crear unidades</button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm border-collapse">
                                        <thead>
                                            <tr class="border-b border-background bg-background/50 text-on-surface-variant font-bold text-xs uppercase tracking-wider">
                                                <th class="p-3.5">Código / Unidad</th>
                                                <th class="p-3.5">Edificio / Torre</th>
                                                <th class="p-3.5">Cuota Mensual Base</th>
                                                <th class="p-3.5">Estado</th>
                                                <th class="p-3.5 text-right">Acciones de Configuración</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-background">
                                            <?php foreach ($unidades as $u): ?>
                                                <tr class="hover:bg-background/40 transition-colors">
                                                    <td class="p-3.5 font-bold text-on-surface"><?= e($u['numero']) ?></td>
                                                    <td class="p-3.5 font-medium text-on-surface">
                                                        <span class="inline-flex items-center gap-1">
                                                            <span class="material-symbols-outlined text-on-surface-variant/70 text-base">domain</span>
                                                            <?= e($u['edificio_nombre'] ?? 'Sin asignar') ?>
                                                        </span>
                                                    </td>
                                                    <td class="p-3.5 font-bold text-primary"><?= e(formatearMoneda($u['cuota_mensual'])) ?></td>
                                                    <td class="p-3.5">
                                                        <?php if ($u['estado'] == 1): ?>
                                                            <span class="bg-green-50 text-green-700 text-xs font-bold px-2 py-0.5 rounded-md">Activa</span>
                                                        <?php else: ?>
                                                            <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-md">Inactiva</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="p-3.5 text-right">
                                                        <div class="inline-flex items-center gap-1">
                                                            <button type="button" 
                                                                    data-id="<?= e($u['id']) ?>" 
                                                                    data-numero="<?= e($u['numero']) ?>" 
                                                                    data-edificio-id="<?= e($u['edificio_id']) ?>" 
                                                                    data-cuota="<?= e($u['cuota_mensual']) ?>" 
                                                                    onclick="editUnidad(this.dataset.id, this.dataset.numero, this.dataset.edificioId, this.dataset.cuota, 'configuracion')" 
                                                                    class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-background rounded-lg transition-colors" 
                                                                    title="Editar Unidad">
                                                                <span class="material-symbols-outlined text-lg">edit</span>
                                                            </button>
                                                            <form method="POST" action="/admin/estructura/unidad/toggle" style="display:inline">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="tab" value="configuracion">
                                                                <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                                                <button type="submit" class="p-1.5 text-on-surface-variant hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Cambiar Estado">
                                                                    <span class="material-symbols-outlined text-lg">power_settings_new</span>
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

            <div class="flex flex-col gap-1">
                <label for="unidad_cuota_mensual" class="text-xs font-bold text-on-surface-variant uppercase">Cuota Mensual (Bs) *</label>
                <input type="number" step="0.01" min="0" id="unidad_cuota_mensual" name="cuota_mensual" required placeholder="Ej: 150.00"
                       class="w-full px-3.5 py-2.5 bg-background border border-outline-variant rounded-xl text-on-surface font-medium focus:outline-none focus:border-primary focus:bg-white text-sm">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-background">
                <button type="button" onclick="closeModalUnidad()" class="px-4 py-2 rounded-xl text-xs font-bold text-on-surface-variant hover:bg-background transition-colors">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-primary hover:bg-primary-hover text-white shadow-sm transition-colors">Guardar Unidad</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: GESTIÓN DE RESIDENTES (PROPIETARIOS E INQUILINOS) -->
<div id="modalGestionResidentes" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-6 md:p-8 space-y-6 transform transition-all border border-outline-variant my-8 max-h-[90vh] flex flex-col">
        <!-- Cabecera del Modal -->
        <div class="flex items-start justify-between border-b border-background pb-4 shrink-0">
            <div>
                <div class="inline-flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider mb-1">
                    <span class="material-symbols-outlined text-[18px]">apartment</span>
                    <span id="modalResidenteEdificioTexto">Torre</span>
                </div>
                <h3 class="text-xl font-black text-on-surface flex items-center gap-2">
                    <span>Residentes de</span>
                    <span id="modalResidenteUnidadNumero" class="text-primary font-black bg-primary/10 px-2.5 py-0.5 rounded-xl">Apto</span>
                </h3>
            </div>
            <button type="button" onclick="closeModalGestionResidentes()" class="text-on-surface-variant hover:text-on-surface p-1 rounded-xl hover:bg-background transition-colors">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
        </div>

        <div class="overflow-y-auto pr-1 space-y-6 flex-1">
            <!-- Sección 1: Lista de Residentes Actuales -->
            <div>
                <h4 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-3 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-primary">groups</span>
                    <span>Habitantes Registrados en esta Unidad</span>
                </h4>

                <div id="contenedorListaResidentes" class="space-y-2.5">
                    <!-- Se llena dinámicamente con JS -->
                </div>
            </div>

            <!-- Sección 2: Formulario de Registro / Edición -->
            <div class="bg-background/60 rounded-2xl p-5 border border-outline-variant">
                <div class="flex items-center justify-between mb-4 border-b border-outline-variant/50 pb-2.5">
                    <h4 id="formularioResidenteTitulo" class="text-sm font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[18px]">person_add</span>
                        <span>Registrar Nuevo Residente</span>
                    </h4>
                    <button type="button" id="btnCancelarEdicionResidente" onclick="resetFormularioResidente()" class="hidden text-xs text-on-surface-variant hover:text-primary font-bold transition-colors">
                        + Registrar Nuevo
                    </button>
                </div>

                <form method="POST" action="/admin/estructura/residente/guardar" id="formResidente" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" id="residente_tab_input" name="tab" value="visualizacion">
                    <input type="hidden" id="residente_id_input" name="id" value="0">
                    <input type="hidden" id="residente_unidad_id_input" name="unidad_id" value="0">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <!-- Cédula de Identidad con Selector de Tipo -->
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-bold text-on-surface-variant uppercase">Cédula de Identidad *</label>
                            <div class="flex items-center gap-1.5">
                                <select id="residente_cedula_tipo" name="cedula_tipo"
                                        class="w-20 px-2.5 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-black text-sm focus:outline-none focus:border-primary cursor-pointer shrink-0 text-center">
                                    <option value="V" selected>V</option>
                                    <option value="E">E</option>
                                    <option value="J">J</option>
                                    <option value="G">G</option>
                                </select>
                                <input type="text" id="residente_cedula_numero" name="cedula_numero" required
                                       inputmode="numeric" pattern="[0-9]{5,8}" minlength="5" maxlength="8" placeholder="12345678"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8)"
                                       class="w-full px-3.5 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-medium focus:outline-none focus:border-primary text-sm tracking-wider">
                            </div>
                        </div>

                        <!-- Tipo de Residente -->
                        <div class="flex flex-col gap-1">
                            <label for="residente_tipo" class="text-xs font-bold text-on-surface-variant uppercase">Tipo de Residente *</label>
                            <select id="residente_tipo" name="tipo" required
                                    class="w-full px-3.5 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-medium focus:outline-none focus:border-primary text-sm cursor-pointer">
                                <option value="propietario">Propietario</option>
                                <option value="inquilino">Inquilino</option>
                                <option value="ambos">Ambos (Propietario / Residente)</option>
                            </select>
                        </div>

                        <!-- Nombre -->
                        <div class="flex flex-col gap-1">
                            <label for="residente_nombre" class="text-xs font-bold text-on-surface-variant uppercase">Nombre *</label>
                            <input type="text" id="residente_nombre" name="nombre" required placeholder="Ej: Carlos"
                                   class="w-full px-3.5 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-medium focus:outline-none focus:border-primary text-sm">
                        </div>

                        <!-- Apellido -->
                        <div class="flex flex-col gap-1">
                            <label for="residente_apellido" class="text-xs font-bold text-on-surface-variant uppercase">Apellido *</label>
                            <input type="text" id="residente_apellido" name="apellido" required placeholder="Ej: Mendoza"
                                   class="w-full px-3.5 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-medium focus:outline-none focus:border-primary text-sm">
                        </div>

                        <!-- Teléfono Móvil con Selector de Operadora -->
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-bold text-on-surface-variant uppercase">Teléfono Móvil (Opcional)</label>
                            <div class="flex items-center gap-1.5">
                                <select id="residente_telefono_codigo" name="telefono_codigo"
                                        class="w-28 px-2.5 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-semibold text-xs focus:outline-none focus:border-primary cursor-pointer shrink-0">
                                    <option value="0412">0412</option>
                                    <option value="0422">0422</option>
                                    <option value="0414">0414</option>
                                    <option value="0424">0424</option>
                                    <option value="0416">0416</option>
                                    <option value="0426">0426</option>
                                </select>
                                <input type="text" id="residente_telefono_numero" name="telefono_numero" maxlength="7" placeholder="1234567"
                                       inputmode="numeric" pattern="[0-9]{7}"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 7)"
                                       class="w-full px-3.5 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-medium focus:outline-none focus:border-primary text-sm tracking-wider">
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="flex flex-col gap-1">
                            <label for="residente_email" class="text-xs font-bold text-on-surface-variant uppercase">Correo Electrónico (Opcional)</label>
                            <input type="email" id="residente_email" name="email" placeholder="Ej: habitante@correo.com"
                                   class="w-full px-3.5 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-medium focus:outline-none focus:border-primary text-sm">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-outline-variant/50">
                        <button type="submit" id="btnSubmitResidente" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-primary hover:bg-primary-hover text-white shadow-sm transition-all flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">how_to_reg</span>
                            <span id="btnSubmitResidenteTexto">Guardar Residente</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentModalResidentesData = [];

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

function openModalUnidad(tab = 'configuracion') {
    document.getElementById('unidad_id_input').value = '0';
    document.getElementById('unidad_tab_input').value = tab;
    document.getElementById('unidad_numero').value = '';
    document.getElementById('unidad_edificio_id').value = '';
    document.getElementById('unidad_cuota_mensual').value = '';
    document.getElementById('modalUnidadTitle').innerText = 'Agregar Unidad';
    const m = document.getElementById('modalUnidad');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function editUnidad(id, numero, edificioId, cuotaMensual, tab = 'configuracion') {
    document.getElementById('unidad_id_input').value = id;
    document.getElementById('unidad_tab_input').value = tab;
    document.getElementById('unidad_numero').value = numero || '';
    document.getElementById('unidad_edificio_id').value = edificioId || '';
    document.getElementById('unidad_cuota_mensual').value = cuotaMensual || '';
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

// GESTIÓN DE RESIDENTES
function openModalGestionResidentes(btn) {
    const unidadId = btn.dataset.unidadId;
    const unidadNumero = btn.dataset.unidadNumero;
    const edificioNombre = btn.dataset.edificioNombre;
    const propietarioId = parseInt(btn.dataset.propietarioId || '0');
    
    let residentes = [];
    try {
        residentes = JSON.parse(btn.dataset.residentes || '[]');
    } catch(e) {
        residentes = [];
    }
    currentModalResidentesData = residentes;

    document.getElementById('modalResidenteEdificioTexto').innerText = edificioNombre || 'Edificio';
    document.getElementById('modalResidenteUnidadNumero').innerText = unidadNumero || 'Unidad';
    document.getElementById('residente_unidad_id_input').value = unidadId;

    renderListaResidentes(residentes, unidadId, propietarioId);
    resetFormularioResidente();

    const m = document.getElementById('modalGestionResidentes');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function renderListaResidentes(residentes, unidadId, propietarioId) {
    const container = document.getElementById('contenedorListaResidentes');
    if (!residentes || residentes.length === 0) {
        container.innerHTML = `
            <div class="text-center py-6 bg-background rounded-xl border border-dashed border-outline-variant">
                <span class="material-symbols-outlined text-on-surface-variant/40 text-3xl mb-1">person_off</span>
                <p class="text-xs font-semibold text-on-surface-variant">Esta unidad no tiene residentes registrados aún.</p>
                <p class="text-[11px] text-on-surface-variant/70">Completa el formulario inferior para registrar a su propietario o inquilino.</p>
            </div>
        `;
        return;
    }

    let html = '';
    residentes.forEach((r, idx) => {
        const esTitular = r.es_titular || (parseInt(r.id) === propietarioId);
        let badgeRol = '';

        if (esTitular) {
            badgeRol = `<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-black px-2 py-0.5 rounded-full inline-flex items-center gap-0.5">
                <span class="material-symbols-outlined text-[12px]">verified_user</span> Propietario Titular
            </span>`;
        } else if (r.tipo === 'propietario') {
            badgeRol = `<span class="bg-green-50 text-green-700 border border-green-200 text-[10px] font-bold px-2 py-0.5 rounded-full inline-flex items-center gap-0.5">
                <span class="material-symbols-outlined text-[12px]">group</span> Co-Propietario
            </span>`;
        } else if (r.tipo === 'ambos') {
            badgeRol = `<span class="bg-purple-50 text-purple-700 border border-purple-200 text-[10px] font-bold px-2 py-0.5 rounded-full inline-flex items-center gap-0.5">
                <span class="material-symbols-outlined text-[12px]">home</span> Titular Residente
            </span>`;
        } else {
            badgeRol = `<span class="bg-blue-50 text-blue-700 border border-blue-200 text-[10px] font-bold px-2 py-0.5 rounded-full inline-flex items-center gap-0.5">
                <span class="material-symbols-outlined text-[12px]">badge</span> Inquilino
            </span>`;
        }

        const telefonoHtml = r.telefono ? `<span class="inline-flex items-center gap-0.5 text-xs text-on-surface-variant"><span class="material-symbols-outlined text-[13px]">phone</span>${escapeHtml(r.telefono)}</span>` : '';
        const emailHtml = r.email ? `<span class="inline-flex items-center gap-0.5 text-xs text-on-surface-variant"><span class="material-symbols-outlined text-[13px]">mail</span>${escapeHtml(r.email)}</span>` : '';

        html += `
            <div class="bg-white p-3.5 rounded-xl border border-outline-variant shadow-sm flex items-center justify-between gap-3 hover:border-primary/40 transition-all">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-sm text-on-surface">${escapeHtml(r.nombre)} ${escapeHtml(r.apellido)}</span>
                        <span class="text-xs font-semibold text-on-surface-variant bg-background px-1.5 py-0.5 rounded">${escapeHtml(r.cedula)}</span>
                        ${badgeRol}
                    </div>
                    <div class="flex items-center gap-3 text-xs text-on-surface-variant flex-wrap">
                        ${telefonoHtml}
                        ${emailHtml}
                    </div>
                </div>
                <div class="inline-flex items-center gap-1 shrink-0">
                    <button type="button" onclick="cargarFormularioEdicionResidente(${idx})" class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-background rounded-lg transition-colors" title="Editar Residente">
                        <span class="material-symbols-outlined text-lg">edit</span>
                    </button>
                    <form method="POST" action="/admin/estructura/residente/desvincular" style="display:inline" onsubmit="return confirm('¿Está seguro de desvincular a este residente de la unidad?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="tab" value="visualizacion">
                        <input type="hidden" name="persona_id" value="${escapeHtml(r.id)}">
                        <input type="hidden" name="unidad_id" value="${escapeHtml(unidadId)}">
                        <button type="submit" class="p-1.5 text-on-surface-variant hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Desvincular Residente">
                            <span class="material-symbols-outlined text-lg">person_remove</span>
                        </button>
                    </form>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function cargarFormularioEdicionResidente(idx) {
    const r = currentModalResidentesData[idx];
    if (!r) return;

    document.getElementById('residente_id_input').value = r.id;
    
    // Normalizar y separar Cédula (Tipo V/E/J/G y 5-8 dígitos)
    let rawCed = String(r.cedula || '').toUpperCase().replace(/[^VEJG0-9]/g, '');
    let tipoDoc = 'V';
    let numDoc = rawCed;
    if (rawCed.startsWith('V') || rawCed.startsWith('E') || rawCed.startsWith('J') || rawCed.startsWith('G')) {
        tipoDoc = rawCed.charAt(0);
        numDoc = rawCed.substring(1);
    }
    document.getElementById('residente_cedula_tipo').value = tipoDoc;
    document.getElementById('residente_cedula_numero').value = numDoc;

    document.getElementById('residente_nombre').value = r.nombre || '';
    document.getElementById('residente_apellido').value = r.apellido || '';
    document.getElementById('residente_tipo').value = r.tipo || 'propietario';
    
    // Separar operadora (4 dígitos) y número (7 dígitos)
    let tel = r.telefono ? String(r.telefono).replace(/[^0-9]/g, '') : '';
    if (tel.length >= 11) {
        let code = tel.substring(0, 4);
        let num = tel.substring(4, 11);
        const sel = document.getElementById('residente_telefono_codigo');
        if (Array.from(sel.options).some(o => o.value === code)) {
            sel.value = code;
        }
        document.getElementById('residente_telefono_numero').value = num;
    } else if (tel.length > 0) {
        document.getElementById('residente_telefono_numero').value = tel.slice(-7);
    } else {
        document.getElementById('residente_telefono_codigo').value = '0412';
        document.getElementById('residente_telefono_numero').value = '';
    }

    document.getElementById('residente_email').value = r.email || '';

    document.getElementById('formularioResidenteTitulo').innerHTML = `
        <span class="material-symbols-outlined text-primary text-[18px]">edit</span>
        <span>Editar Datos del Residente (${escapeHtml(r.nombre)} ${escapeHtml(r.apellido)})</span>
    `;
    document.getElementById('btnSubmitResidenteTexto').innerText = 'Actualizar Residente';
    document.getElementById('btnCancelarEdicionResidente').classList.remove('hidden');
}

function resetFormularioResidente() {
    document.getElementById('residente_id_input').value = '0';
    document.getElementById('residente_cedula_tipo').value = 'V';
    document.getElementById('residente_cedula_numero').value = '';
    document.getElementById('residente_nombre').value = '';
    document.getElementById('residente_apellido').value = '';
    document.getElementById('residente_tipo').value = 'propietario';
    document.getElementById('residente_telefono_codigo').value = '0412';
    document.getElementById('residente_telefono_numero').value = '';
    document.getElementById('residente_email').value = '';

    document.getElementById('formularioResidenteTitulo').innerHTML = `
        <span class="material-symbols-outlined text-primary text-[18px]">person_add</span>
        <span>Registrar Nuevo Residente</span>
    `;
    document.getElementById('btnSubmitResidenteTexto').innerText = 'Guardar Residente';
    document.getElementById('btnCancelarEdicionResidente').classList.add('hidden');
}

function closeModalGestionResidentes() {
    const m = document.getElementById('modalGestionResidentes');
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

// BÚSQUEDA Y FILTRADO EN VIVO EN VISUALIZACIÓN
function filtrarTablaVisualizacion(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#tablaVisualizacionUnidades tbody tr.fila-unidad');
    let visibles = 0;
    rows.forEach(row => {
        const texto = (row.dataset.busqueda || row.innerText || '').toLowerCase();
        if (!q || texto.includes(q)) {
            row.style.display = '';
            visibles++;
        } else {
            row.style.display = 'none';
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
