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
            <div class="flex items-center gap-3">
                <button onclick="openModalEdificio()" class="inline-flex items-center gap-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3 py-2.5 rounded-xl border border-outline-variant transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">add_business</span>
                    <span class="hidden sm:inline">Agregar Edificio</span>
                </button>
                <button onclick="openModalUnidad()" class="inline-flex items-center gap-1 bg-primary hover:bg-primary-hover text-white font-bold text-xs px-3 py-2.5 rounded-xl transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">add_home</span>
                    <span class="hidden sm:inline">Agregar Unidad</span>
                </button>
                <div class="w-px h-6 bg-outline-variant mx-1"></div>
                <a href="/admin/logout" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs px-4 py-2.5 rounded-lg border border-red-200 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">logout</span>
                    <span class="hidden sm:inline">Cerrar Sesión</span>
                </a>
            </div>
        </header>

        <!-- Contenido principal scrollable -->
        <div class="flex-grow p-6 overflow-y-auto">
            <div class="max-w-7xl mx-auto space-y-8">

                <!-- Mensajes Flash -->
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

                <!-- SECCIÓN SUPERIOR: EDIFICIOS / TORRES -->
                <section class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm space-y-6">
                    <div class="flex items-center justify-between border-b border-background pb-4">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-on-surface">corporate_fare</span>
                            <h3 class="text-base font-bold text-on-surface">Edificios / Torres</h3>
                            <span class="bg-background text-on-surface-variant text-xs font-bold px-2.5 py-1 rounded-full"><?= count($edificios) ?></span>
                        </div>
                    </div>

                    <?php if (empty($edificios)): ?>
                        <div class="text-center py-12 bg-background/50 rounded-2xl border border-dashed border-outline-variant">
                            <span class="material-symbols-outlined text-on-surface-variant/40 text-4xl mb-2">location_city</span>
                            <p class="text-on-surface-variant font-semibold">No hay edificios registrados aún.</p>
                            <button onclick="openModalEdificio()" class="mt-2 text-primary text-xs font-bold hover:underline">Registrar el primer edificio</button>
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
                                            <span><?= intval($edificio['total_unidades']) ?> unidades</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button onclick="editEdificio(<?= json_encode($edificio, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)" class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-background rounded-lg transition-colors" title="Editar">
                                                <span class="material-symbols-outlined text-lg">edit</span>
                                            </button>
                                            <form method="POST" action="/admin/estructura/edificio/toggle" style="display:inline">
                                                <?= csrf_field() ?>
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

                <!-- SECCIÓN INFERIOR: UNIDADES / APARTAMENTOS -->
                <section class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-background pb-4">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-on-surface">apartment</span>
                            <h3 class="text-base font-bold text-on-surface">Unidades / Apartamentos</h3>
                            <span class="bg-background text-on-surface-variant text-xs font-bold px-2.5 py-1 rounded-full"><?= count($unidades) ?></span>
                        </div>

                        <!-- Filtro por Edificio -->
                        <form method="GET" action="/admin/estructura" class="flex items-center gap-2 w-full sm:w-auto">
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
                    </div>

                    <?php if (empty($unidades)): ?>
                        <div class="text-center py-12 bg-background/50 rounded-2xl border border-dashed border-outline-variant">
                            <span class="material-symbols-outlined text-on-surface-variant/40 text-4xl mb-2">home</span>
                            <p class="text-on-surface-variant font-semibold">No se encontraron unidades registradas.</p>
                            <button onclick="openModalUnidad()" class="mt-2 text-primary text-xs font-bold hover:underline">Registrar nueva unidad</button>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm border-collapse">
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
                                                <span class="bg-background text-on-surface font-bold text-xs px-2.5 py-1 rounded-full border border-outline-variant">
                                                    <?= intval($u['total_residentes'] ?? 0) ?> res.
                                                </span>
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
                                                    <button onclick="editUnidad(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)" class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-background rounded-lg transition-colors" title="Editar">
                                                        <span class="material-symbols-outlined text-lg">edit</span>
                                                    </button>
                                                    <form method="POST" action="/admin/estructura/unidad/toggle" style="display:inline">
                                                        <?= csrf_field() ?>
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

<!-- MODAL: AGREGAR / EDITAR EDIFICIO -->
<div id="modalEdificio" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-5 transform transition-all border border-outline-variant">
        <div class="flex items-center justify-between border-b border-background pb-3">
            <h3 id="modalEdificioTitle" class="text-base font-bold text-on-surface">Agregar Edificio</h3>
            <button onclick="closeModalEdificio()" class="text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="/admin/estructura/edificio/guardar" class="space-y-4">
            <?= csrf_field() ?>
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
<div id="modalUnidad" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-5 transform transition-all border border-outline-variant">
        <div class="flex items-center justify-between border-b border-background pb-3">
            <h3 id="modalUnidadTitle" class="text-base font-bold text-on-surface">Agregar Unidad</h3>
            <button onclick="closeModalUnidad()" class="text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="/admin/estructura/unidad/guardar" class="space-y-4">
            <?= csrf_field() ?>
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
                <label for="unidad_cuota_mensual" class="text-xs font-bold text-on-surface-variant uppercase">Cuota Mensual ($) *</label>
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

<script>
function openModalEdificio() {
    document.getElementById('edificio_id_input').value = '0';
    document.getElementById('edificio_nombre').value = '';
    document.getElementById('edificio_descripcion').value = '';
    document.getElementById('modalEdificioTitle').innerText = 'Agregar Edificio';
    document.getElementById('modalEdificio').classList.remove('hidden');
}

function editEdificio(ed) {
    document.getElementById('edificio_id_input').value = ed.id;
    document.getElementById('edificio_nombre').value = ed.nombre;
    document.getElementById('edificio_descripcion').value = ed.descripcion || '';
    document.getElementById('modalEdificioTitle').innerText = 'Editar Edificio';
    document.getElementById('modalEdificio').classList.remove('hidden');
}

function closeModalEdificio() {
    document.getElementById('modalEdificio').classList.add('hidden');
}

function openModalUnidad() {
    document.getElementById('unidad_id_input').value = '0';
    document.getElementById('unidad_numero').value = '';
    document.getElementById('unidad_edificio_id').value = '';
    document.getElementById('unidad_cuota_mensual').value = '';
    document.getElementById('modalUnidadTitle').innerText = 'Agregar Unidad';
    document.getElementById('modalUnidad').classList.remove('hidden');
}

function editUnidad(u) {
    document.getElementById('unidad_id_input').value = u.id;
    document.getElementById('unidad_numero').value = u.numero;
    document.getElementById('unidad_edificio_id').value = u.edificio_id;
    document.getElementById('unidad_cuota_mensual').value = u.cuota_mensual;
    document.getElementById('modalUnidadTitle').innerText = 'Editar Unidad';
    document.getElementById('modalUnidad').classList.remove('hidden');
}

function closeModalUnidad() {
    document.getElementById('modalUnidad').classList.add('hidden');
}
</script>
