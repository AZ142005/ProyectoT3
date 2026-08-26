---
name: bootstrap-ui-components
description: >-
  Guía y patrones de componentes UI con Bootstrap 5, HTML5 y CSS3 nativo para el sistema Condominio Digital.
  Úsalo cuando el usuario pida diseñar, maquetar o estilizar pantallas, tablas, modales, tarjetas o botones sin usar Tailwind.
---

# Skill: Componentes UI con Bootstrap 5, HTML5 y CSS3

Esta habilidad contiene plantillas de diseño y patrones de componentes listos para usar basados en **Bootstrap 5 y CSS nativo**.

---

## 1. Tarjeta de Estadísticas / KPI (Bootstrap 5)
```html
<div class="card shadow-sm border-0 rounded-3 mb-4">
    <div class="card-body d-flex justify-content-between align-items-center p-4">
        <div>
            <span class="text-uppercase text-muted fw-bold small">Total Deuda</span>
            <h2 class="display-6 fw-bold text-danger mb-0 mt-1">$1,450.00</h2>
        </div>
        <div class="bg-danger-subtle text-danger p-3 rounded-circle">
            <span class="material-symbols-outlined fs-1">account_balance_wallet</span>
        </div>
    </div>
</div>
```

---

## 2. Tabla Administrativa con Acciones y Badges
```html
<div class="card shadow-sm border-0 rounded-3">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
        <h5 class="mb-0 fw-bold text-dark">Listado de Unidades</h5>
        <span class="badge bg-light text-dark border">Total: <?= count($items) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="py-3 px-3">Código</th>
                        <th class="py-3 px-3">Edificio / Torre</th>
                        <th class="py-3 px-3">Cuota Mensual</th>
                        <th class="py-3 px-3">Estado</th>
                        <th class="py-3 px-3 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="px-3 fw-bold"><?= e($item['codigo']) ?></td>
                        <td class="px-3"><?= e($item['edificio']) ?></td>
                        <td class="px-3 fw-bold text-success">$<?= number_format($item['cuota'], 2) ?></td>
                        <td class="px-3">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Activo</span>
                        </td>
                        <td class="px-3 text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal">
                                <span class="material-symbols-outlined fs-6 align-middle">edit</span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

---

## 3. Modal Estándar de Bootstrap 5 con Formulario CSRF
```html
<div class="modal fade" id="modalEdificio" tabindex="-1" aria-labelledby="modalEdificioLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-3">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="modalEdificioLabel">Agregar Edificio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/admin/estructura/edificio/guardar">
                <div class="modal-body p-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="0">

                    <div class="mb-3">
                        <label for="nombre" class="form-label fw-semibold small text-muted text-uppercase">Nombre de la Torre / Edificio *</label>
                        <input type="text" class="form-control form-control-lg fs-6" id="nombre" name="nombre" required placeholder="Ej: Torre A">
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label fw-semibold small text-muted text-uppercase">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Detalles de la torre..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Guardar Edificio</button>
                </div>
            </form>
        </div>
    </div>
</div>
```
