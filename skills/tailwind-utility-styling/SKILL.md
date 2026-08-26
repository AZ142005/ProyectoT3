---
name: tailwind-utility-styling
description: >-
  Guía secundaria de utilidades Tailwind CSS para mantenimiento de vistas existentes y utilidades de soporte.
  NOTA DE PRIORIDAD: La prioridad principal de diseño en este proyecto es Bootstrap 5. Usa esta habilidad en segundo plano para soporte legacy o ajustes puntuales.
---

# Skill: Utilidades Tailwind CSS (Uso Secundario / Soporte Legacy)

> [!IMPORTANT]
> **Jerarquía de Diseño del Proyecto**:
> 1. **Prioridad 1 (Principal)**: **Bootstrap 5, HTML5 y CSS3 nativo** (ver habilidad `bootstrap-ui-components`).
> 2. **Prioridad 2 (Secundaria)**: **Tailwind CSS** (utilidades rápidas y mantenimiento de vistas existentes).

---

## 1. Cuándo Utilizar Tailwind en este Proyecto
- Mantenimiento y corrección de pantallas que ya están maquetadas con clases de Tailwind (ej. módulos de pagos o panel de residentes).
- Utilidades auxiliares puntuales de espaciado o flexbox (`gap-*`, `tracking-wider`, `whitespace-nowrap`) donde complementen armónicamente la maquetación.
- Para **nuevos módulos y componentes principales**, utiliza siempre **Bootstrap 5** (`.card`, `.table`, `.modal`, `.btn`, `.badge`).

---

## 2. Tokens y Paleta Temática Configurada en el Proyecto
El archivo `app/views/layouts/header.php` tiene configurada la siguiente paleta de Tailwind en coexistencia:
- `primary`: `#27ae60` (Verde institucional)
- `primary-hover`: `#1e8449`
- `primary-container`: `#facc15` (Amarillo de resaltado)
- `background`: `#f0f7f0` (Fondo claro institucional)
- `on-surface`: `#2c3e50`
- `on-surface-variant`: `#5a7a6a`
- `outline-variant`: `#d5f5e3`

---

## 3. Patrones de Utilidades Comunes (Vistas Heredadas)

### Tarjeta / Contenedor con Tailwind
```html
<div class="bg-white rounded-2xl border border-outline-variant p-6 shadow-sm">
    <h3 class="text-lg font-bold text-on-surface">Título</h3>
    <p class="text-xs text-on-surface-variant mt-1">Descripción</p>
</div>
```

### Tabla con Tailwind
```html
<div class="overflow-x-auto">
    <table class="w-full text-left text-sm border-collapse">
        <thead>
            <tr class="text-xs uppercase text-slate-500 font-bold border-b border-outline-variant bg-slate-50">
                <th class="py-3 px-4">Columna 1</th>
                <th class="py-3 px-4">Columna 2</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-background">
            <tr>
                <td class="py-4 px-4 font-medium text-on-surface"><?= e($dato) ?></td>
                <td class="py-4 px-4"><?= e($otroDato) ?></td>
            </tr>
        </tbody>
    </table>
</div>
```

---

## 4. Regla de Coexistencia con Bootstrap 5
- No anular selectores de componentes de Bootstrap (`.modal`, `.form-control`, `.btn`) con utilidades contradictorias.
- Todas las salidas dinámicas deben estar siempre escapadas con `<?= e($var) ?>`.
