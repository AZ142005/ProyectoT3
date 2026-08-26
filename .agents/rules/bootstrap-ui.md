# Reglas de Diseño UI y Maquetación (Bootstrap 5, HTML5 & CSS3)

## 1. Stack de Presentación Obligatorio
- **Tecnologías**: HTML5 semántico, CSS3 nativo y **Bootstrap 5** (vía CDN o local).
- **PROHIBIDO**: Utilizar Tailwind CSS, utilidades de Tailwind o frameworks CSS adicionales.

---

## 2. Paleta Institucional y Componentes Bootstrap 5

- **Colores Principales**:
  - Color Primario (Verde Institucional): Usar clase personalizada `.btn-primary`, `.bg-primary` o variables CSS `:root { --bs-primary: #27ae60; --bs-primary-rgb: 39, 174, 96; }`.
  - Fondo de Aplicación: `#f0f7f0` o `.bg-light`.
  - Encabezados y Textos: `.text-dark`, `.text-muted`.

- **Componentes Nativos de Bootstrap**:
  - **Tablas**: `<table class="table table-hover table-striped align-middle">`
  - **Tarjetas**: `<div class="card shadow-sm border-0 rounded-3">`
  - **Botones**: `<button class="btn btn-primary btn-sm rounded-2">`, `.btn-outline-secondary`, `.btn-danger`
  - **Formularios**: `<div class="mb-3"><label class="form-label">...</label><input class="form-control"></div>`
  - **Badges**: `<span class="badge bg-success">`, `<span class="badge bg-warning text-dark">`, `<span class="badge bg-danger">`
  - **Modales**: Usar el componente estándar de Bootstrap 5 (`class="modal fade"` y `data-bs-toggle="modal"`).
  - **Alertas**: `<div class="alert alert-success d-flex align-items-center" role="alert">`

---

## 3. Menú Lateral (Sidebar) y Layout
- **Sidebar Sticky**:
  - En escritorio: Sidebar con `position: sticky; top: 0; height: 100vh;` y fondo oscuro (`bg-dark text-white`).
  - Navegación con lista vertical `.nav flex-column` y enlaces `.nav-link text-white`.
- **Diseño Responsivo (Mobile-First)**:
  - Usar el sistema de cuadrícula (Grid System) nativo de Bootstrap: `.container-fluid`, `.row`, `.col-12.col-md-6.col-lg-4`.
  - Menús desplegables / Offcanvas para dispositivos móviles (`.d-md-none`, `.d-none.d-md-block`).

---

## 4. Accesibilidad (WCAG AA)
- Todos los inputs deben tener `<label for="id">` explícito.
- Botones e íconos interactivos deben tener `aria-label` o texto accesible.
- Iconografía: Usar **Bootstrap Icons** (`<i class="bi bi-house"></i>`) o **Material Symbols**.
