<div class="min-h-screen bg-slate-50 p-6 md:p-10">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <div>
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-3xl">domain</span>
                    Estructura del Conjunto
                </h1>
                <p class="text-slate-500 text-sm mt-1">Gestión de Edificios (Torres) y Unidades (Apartamentos)</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="openModalEdificio()" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-900 text-white font-bold text-sm px-4 py-2.5 rounded-xl transition-all shadow-sm">
                    <span class="material-symbols-outlined text-lg">add_business</span>
                    Agregar Edificio
                </button>
                <button onclick="openModalUnidad()" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white font-bold text-sm px-4 py-2.5 rounded-xl transition-all shadow-sm">
                    <span class="material-symbols-outlined text-lg">add_home</span>
                    Agregar Unidad
                </button>
            </div>
        </div>

        <!-- Mensajes Flash -->
        <?php if (!empty($mensaje)): ?>
            <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 p-4 rounded-xl flex items-center gap-3 text-sm font-medium">
                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                <span><?= e($mensaje) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-rose-50 text-rose-800 border border-rose-200 p-4 rounded-xl flex items-center gap-3 text-sm font-medium">
                <span class="material-symbols-outlined text-rose-600">error</span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- SECCIÓN SUPERIOR: EDIFICIOS / TORRES -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-slate-700">corporate_fare</span>
                    <h2 class="text-xl font-bold text-slate-800">Edificios / Torres</h2>
                    <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2.5 py-1 rounded-full"><?= count($edificios) ?></span>
                </div>
            </div>

            <?php if (empty($edificios)): ?>
                <div class="text-center py-10 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                    <span class="material-symbols-outlined text-slate-400 text-4xl mb-2">location_city</span>
                    <p class="text-slate-600 font-medium">No hay edificios registrados aún.</p>
                    <button onclick="openModalEdificio()" class="mt-3 text-primary text-sm font-bold hover:underline">Registrar el primer edificio</button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($edificios as $edificio): ?>
                        <div class="border border-slate-200 rounded-xl p-5 hover:border-primary/40 transition-all bg-gradient-to-br from-white to-slate-50/50 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-lg font-black text-slate-900"><?= e($edificio['nombre']) ?></h3>
                                    <?php if ($edificio['estado'] == 1): ?>
                                        <span class="bg-emerald-100 text-emerald-800 text-xs font-bold px-2 py-0.5 rounded-md">Activo</span>
                                    <?php else: ?>
                                        <span class="bg-slate-200 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-md">Inactivo</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-slate-500 text-xs mt-1 min-h-[32px]">
                                    <?= !empty($edificio['descripcion']) ? e($edificio['descripcion']) : 'Sin descripción' ?>
                                </p>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                                <div class="flex items-center gap-1.5 text-slate-600 text-xs font-medium">
                                    <span class="material-symbols-outlined text-base">roofing</span>
                                    <span><?= intval($edificio['total_unidades']) ?> unidades</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick='editEdificio(<?= json_encode($edificio) ?>)' class="p-1.5 text-slate-600 hover:text-primary hover:bg-slate-100 rounded-lg transition-colors" title="Editar">
                                        <span class="material-symbols-outlined text-lg">edit</span>
                                    </button>
                                    <a href="/admin/estructura/edificio/toggle?id=<?= $edificio['id'] ?>" class="p-1.5 text-slate-600 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Cambiar Estado">
                                        <span class="material-symbols-outlined text-lg">power_settings_new</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- SECCIÓN INFERIOR: UNIDADES / APARTAMENTOS -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-slate-700">apartment</span>
                    <h2 class="text-xl font-bold text-slate-800">Unidades / Apartamentos</h2>
                    <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2.5 py-1 rounded-full"><?= count($unidades) ?></span>
                </div>

                <!-- Filtro por Edificio -->
                <form method="GET" action="/admin/estructura" class="flex items-center gap-2">
                    <label for="filtro_edificio" class="text-xs font-bold text-slate-500 whitespace-nowrap">Filtrar por Edificio:</label>
                    <select id="filtro_edificio" name="edificio_id" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 text-slate-800 text-xs font-semibold rounded-xl px-3 py-2 focus:outline-none focus:border-primary">
                        <option value="0">Todos los Edificios</option>
                        <?php foreach ($edificios as $ed): ?>
                            <option value="<?= $ed['id'] ?>" <?= $filtroEdificio == $ed['id'] ? 'selected' : '' ?>>
                                <?= e($ed['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if (empty($unidades)): ?>
                <div class="text-center py-10 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                    <span class="material-symbols-outlined text-slate-400 text-4xl mb-2">home</span>
                    <p class="text-slate-600 font-medium">No se encontraron unidades registradas.</p>
                    <button onclick="openModalUnidad()" class="mt-3 text-primary text-sm font-bold hover:underline">Registrar nueva unidad</button>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/70 text-slate-600 font-bold text-xs uppercase tracking-wider">
                                <th class="p-3.5">Código / Unidad</th>
                                <th class="p-3.5">Edificio / Torre</th>
                                <th class="p-3.5">Cuota Mensual ($)</th>
                                <th class="p-3.5">Residentes</th>
                                <th class="p-3.5">Estado</th>
                                <th class="p-3.5 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($unidades as $u): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="p-3.5 font-bold text-slate-900"><?= e($u['numero']) ?></td>
                                    <td class="p-3.5 font-medium text-slate-700">
                                        <span class="inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-slate-400 text-base">domain</span>
                                            <?= e($u['edificio_nombre'] ?? 'Sin asignar') ?>
                                        </span>
                                    </td>
                                    <td class="p-3.5 font-bold text-slate-900">$<?= number_format($u['cuota_mensual'], 2) ?></td>
                                    <td class="p-3.5">
                                        <span class="bg-slate-100 text-slate-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                                            <?= intval($u['total_residentes'] ?? 0) ?> res.
                                        </span>
                                    </td>
                                    <td class="p-3.5">
                                        <?php if ($u['estado'] == 1): ?>
                                            <span class="bg-emerald-100 text-emerald-800 text-xs font-bold px-2 py-0.5 rounded-md">Activa</span>
                                        <?php else: ?>
                                            <span class="bg-slate-200 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-md">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3.5 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <button onclick='editUnidad(<?= json_encode($u) ?>)' class="p-1.5 text-slate-600 hover:text-primary hover:bg-slate-100 rounded-lg transition-colors" title="Editar">
                                                <span class="material-symbols-outlined text-lg">edit</span>
                                            </button>
                                            <a href="/admin/estructura/unidad/toggle?id=<?= $u['id'] ?>" class="p-1.5 text-slate-600 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Cambiar Estado">
                                                <span class="material-symbols-outlined text-lg">power_settings_new</span>
                                            </a>
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

<!-- MODAL: AGREGAR / EDITAR EDIFICIO -->
<div id="modalEdificio" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-5 transform transition-all">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 id="modalEdificioTitle" class="text-lg font-black text-slate-900">Agregar Edificio</h3>
            <button onclick="closeModalEdificio()" class="text-slate-400 hover:text-slate-600">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="/admin/estructura/edificio/guardar" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" id="edificio_id_input" name="id" value="0">

            <div>
                <label for="edificio_nombre" class="block text-xs font-bold text-slate-700 uppercase mb-1">Nombre / Identificador *</label>
                <input type="text" id="edificio_nombre" name="nombre" required placeholder="Ej: Torre A"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 font-medium focus:outline-none focus:border-primary focus:bg-white text-sm">
            </div>

            <div>
                <label for="edificio_descripcion" class="block text-xs font-bold text-slate-700 uppercase mb-1">Descripción (Opcional)</label>
                <textarea id="edificio_descripcion" name="descripcion" rows="3" placeholder="Ej: Edificio residencial de 10 pisos"
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 font-medium focus:outline-none focus:border-primary focus:bg-white text-sm"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="closeModalEdificio()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-primary hover:bg-primary-hover text-white shadow-sm">Guardar Edificio</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: AGREGAR / EDITAR UNIDAD -->
<div id="modalUnidad" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-5 transform transition-all">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 id="modalUnidadTitle" class="text-lg font-black text-slate-900">Agregar Unidad</h3>
            <button onclick="closeModalUnidad()" class="text-slate-400 hover:text-slate-600">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="/admin/estructura/unidad/guardar" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" id="unidad_id_input" name="id" value="0">

            <div>
                <label for="unidad_numero" class="block text-xs font-bold text-slate-700 uppercase mb-1">Código / Número *</label>
                <input type="text" id="unidad_numero" name="numero" required placeholder="Ej: A-101"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 font-medium focus:outline-none focus:border-primary focus:bg-white text-sm">
            </div>

            <div>
                <label for="unidad_edificio_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">Edificio / Torre *</label>
                <select id="unidad_edificio_id" name="edificio_id" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 font-medium focus:outline-none focus:border-primary focus:bg-white text-sm">
                    <option value="">Seleccione un edificio...</option>
                    <?php foreach ($edificios as $ed): ?>
                        <option value="<?= $ed['id'] ?>"><?= e($ed['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="unidad_cuota_mensual" class="block text-xs font-bold text-slate-700 uppercase mb-1">Cuota Mensual ($) *</label>
                <input type="number" step="0.01" min="0" id="unidad_cuota_mensual" name="cuota_mensual" required placeholder="Ej: 150.00"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 font-medium focus:outline-none focus:border-primary focus:bg-white text-sm">
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="closeModalUnidad()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-primary hover:bg-primary-hover text-white shadow-sm">Guardar Unidad</button>
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
