# ODD task: Nuevo logo de marca + fondo del login

**Estado**: COMPLETADO Y VERIFICADO (2026-10-11) — verificador independiente PASS (7/7); commits locales en `main`, sin push.
**Fecha**: 2026-10-11
**Ruta**: direct-inline — intercambio de assets + ediciones menores en 1 vista (login) + iconos generados + 1 test nuevo pequeño (sin disparador de delegación: un solo archivo no trivial).

## Objetivo (pedido del usuario)

1. Usar el **nuevo logo** (`nuevo_logo-removebg-preview.png`, 570×437, untracked en la raíz) y reemplazar el logo actual donde haga falta: `public/img/logo_condominio.png` (6 usos: login, sidebars admin/auditor, imprimibles de morosidad y estado de cuenta, pago directo) + los derivados del logo viejo (favicon.png, favicon.ico, apple-touch-icon.png).
2. **Fondo del login**: usar `imagen para login.jpg` (foto del condominio de noche, 1170×1463) en `.login-visual-side`, en reemplazo de la foto externa de Unsplash.

## Diseño y decisiones

- Se reemplaza el ARCHIVO `public/img/logo_condominio.png` (mismo nombre → los 6 usos actualizan solos).
- Login: el `<img>` del logo usaba attrs cuadrados 423×423 y un hack CSS (`width:136.61%`, márgenes negativos, transform) pensado para el logo viejo cuadrado. Con el logo nuevo (landscape) se normaliza: attrs proporcionales (340×261) y CSS limpio (width 100%, máx. 340, centrado) para evitar distorsión/desbordes.
- Fondo: copia local `public/img/fondo_login.jpg` y `url('/img/fondo_login.jpg')` en el CSS; se conserva el degradado oscuro sobre la foto (legibilidad de la tarjeta).
- Iconos: regenerados con GD desde el logo nuevo (favicon.png 32×32, apple-touch-icon.png 180×180, favicon.ico 32×32 empaquetando PNG). `favicon.svg` es un ícono vectorial abstracto (no el logo) → no se toca.
- Los archivos originales de la raíz quedan intactos (solo se copian a `public/img/`).
- Sin cambios de copy/alt (solo assets).

## Tareas

- [x] **T1 — Assets**: copiar logo nuevo → `public/img/logo_condominio.png`; foto → `public/img/fondo_login.jpg`.
- [x] **T2 — login.php**: img del logo (attrs proporcionales), CSS `.login-brand-logo img` normalizado y URL del fondo local (sin Unsplash).
- [x] **T3 — Iconos**: regenerar favicon.png / apple-touch-icon.png / favicon.ico desde el logo nuevo (GD).
- [x] **T4 — Test**: `tests/MarcaAssetsTest.php` (fondo local, sin Unsplash, assets en disco y legibles).
- [x] **T5 — Verificación**: `php -l`, filtros de tests, HTTP (login 200 con fondo local; imágenes 200) y screenshot del login.

## Criterios de aceptación

- El login muestra el nuevo logo sin distorsión y la foto local como fondo (sin Unsplash).
- Los 6 usos del logo sirven la nueva imagen; favicon/apple-touch/ico regenerados.
- Tests en verde; sin cambios en el resto de la UI.

## Checks

- `C:/xampp/php/php.exe -l app/views/auth/login.php`; `tests/run.php --filter=Marca` / `--filter=Auth` / `--filter=Router`.
- HTTP: GET `/auth/login` (página pública), `/img/logo_condominio.png`, `/img/fondo_login.jpg`, `/favicon.png`, `/favicon.ico`, `/apple-touch-icon.png`.
- Screenshot del login (verificación visual).
- Suite completa: falla preexistente conocida (`SecurityTest`) — no tocar.

## Progreso y evidencia

### T1–T4 — Implementación (COMPLETADO)

- Assets: `public/img/logo_condominio.png` = copia byte-idéntica del logo nuevo (570×437, SHA256 `6AF38B03…`); `public/img/fondo_login.jpg` = copia byte-idéntica de "imagen para login.jpg" (1170×1463, SHA256 `46E7C875…`). Los originales de la raíz quedaron intactos (untracked).
- `login.php`: attrs del `<img>` 570×437; **regla heredada del usuario recalculada** (el texto "Las Mesetas" = 52.11% del PNG → `width: 191.92%`, márgenes `−45.96%`, `translateX(0.18%)`, con comentario explicativo); fondo `url('/img/fondo_login.jpg')` (sin Unsplash). Sin cambios en lógica PHP.
- Iconos regenerados con GD ajustando el bbox de contenido sobre blanco: favicon.png 32×32 (1.309 B), apple-touch-icon.png 180×180 (19.227 B), favicon.ico (PNG embebido en offset 22, 1.331 B). `favicon.svg` (vector abstracto) intacto.
- `tests/MarcaAssetsTest.php` nuevo (fondo local, sin Unsplash, assets válidos y logo 570×437).
- Diff del commit: 7 archivos, +47/−6.

### T5 — Verificación (COMPLETADO)

- `php -l` limpio; tests: Marca 2/14, Auth 37/318, Router 23/44 — verde.
- HTTP real (página pública): `/auth/login` 200 con fondo local y sin Unsplash; los 5 assets 200 con los bytes exactos (130.221 / 282.515 / 1.309 / 1.331 / 19.227).
- Screenshot: N/A — el navegador de escritorio no está conectado a la sesión; se sustituyó por medición de píxeles + evidencia HTTP.
- Riesgo RDD: `assess --base-ref 4a4e519 --committed-only` → `risk: high` (`hot_path`: login), `review_due: true`; OpenCode no habilita la revisión inmutable v2 → verificador independiente por el gate.
- **Verificador independiente (agente fresco, read-only): PASS — 7/7**: integridad (7 archivos, padre exacto, sin atribución IA); identidad SHA256 de ambos assets; diff del login solo presentacional (sin lógica PHP); **re-medición independiente del texto** (297 px → 191.92% / −45.96% / 0.18%, Δ ≤ 0.005); iconos válidos; tests + lint; HTTP con tamaños exactos.
- Observaciones: (a) el conteo correcto de usos del logo en vistas es **6** (login, sidebars admin/auditor, pago directo y 2 imprimibles); el mensaje del commit ya creado conserva "7 usos" (corregirlo implicaría reescribir el hash verificado); (b) el ICO tiene un solo tamaño (32×32); (c) los originales de la raíz quedan untracked a propósito.

## Cierre

- Commits (locales en `main`, sin push, sin atribución IA):

  | Hash | Mensaje |
  |------|---------|
  | `6b954ff` | feat(ui): nuevo logo de marca y foto del condominio como fondo del login |
  | `(cierre documental)` | docs(odd): cierra el nuevo logo y el fondo del login |

- Para ver los cambios: refrescar con Ctrl+F5 (caché de imágenes del navegador).
- Pendiente del usuario: push/PR.
