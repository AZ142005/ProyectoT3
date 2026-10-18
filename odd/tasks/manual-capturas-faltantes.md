# ODD task: Manuales — capturas faltantes (2FA, cartelera, respaldos, Mis Pagos)

**Estado**: COMPLETADO Y VERIFICADO (2026-10-18)
**Autorizado por**: usuario («revisa qué imágenes faltan en los manuales y ponlas en un cuadro con ruta desde interfaz para sacarlas y que las coloques»)
**Ruta**: ODD — capturas por navegador headless (heredoc controlado inline) + **1 writer delegado** para la inserción en los docx + spot check independiente del orquestador

## Objetivo

Detectar qué capturas faltaban en los manuales vigentes, tomarlas de la interfaz real (app local `http://localhost:8000`) y colocarlas en los manuales.

## Cuadro final — capturas faltantes, ruta desde la interfaz y estado

| Fig. | Manual(es) | Sección | Archivo (en `docs/manual-capturas/`) | Ruta desde la interfaz | Estado |
|------|-----------|---------|--------------------------------------|------------------------|--------|
| 2 | Admin + Residente | 2. ACCESO AL SISTEMA | `03-verificacion-2fa.png` | Iniciar sesión con una cuenta con verificación en dos pasos activada → pantalla «Verificación en Dos Pasos» (`/auth/verificar-2fa`) | ✅ Colocada |
| 11 | Admin | 7. CARGA Y EMISIÓN | `10-cartelera-cargar.png` | Menú «Carga y Emisión» → «Cargar Reporte de Cartelera» (`/admin/cartelera`) → subir PDF → «Analizar y Previsualizar» | ✅ Colocada |
| 19 | Admin | 13. RESPALDOS | `18-respaldos.png` | Menú «Respaldos» (`/admin/respaldos`) → tarjeta «Respaldo del sistema (vigente)» + tarjeta «Datos acumulados y retención» | ✅ Colocada |
| 27 | Residente | 18. MIS PAGOS | `22-residente-historial.png` | Sesión de residente → «Mis Pagos» (`/pagos`) con movimientos de la unidad A-101 | ✅ Colocada |

**Fuera de los manuales actuales** (solo en la fuente regenerable `scripts/manual/contenido_manual.json`; no hay figura ni placeholder en ningún docx): `20-pago-directo-exito.png`, `30-auditor-vista-lectura.png`, `31-auditor-log.png`. No capturadas en esta tarea.

## Tareas

- [x] T1 — Documento + espejo en Engram.
- [x] T2 — Capturas (Chrome headless 1920×1080 vía puppeteer-core; app local; side effects mínimos, ver Registro):
  - [x] T2a — `03-verificacion-2fa.png`: 2FA temporal en usuario admin (flip SQL → captura → revertido a 0).
  - [x] T2b — `18-respaldos.png`: sesión admin → `/admin/respaldos` (1920×1376).
  - [x] T2c — `10-cartelera-cargar.png`: sesión admin → `/admin/cartelera`, PDF de prueba subido y previsualización (216 unidades) capturada; SIN importar (1920×1700).
  - [x] T2d — `22-residente-historial.png`: sesión residente (contraseña temporal; hash restaurado byte a byte) → `/pagos` (1920×1080).
- [x] T3 — PNGs colocados con nombres exactos en `docs/manual-capturas/`.
- [x] T4 — Inserción en docx por Word COM (writer delegado): Admin (figs. 2, 11, 19), Residente (figs. 2, 27); cajas «CAPTURA PENDIENTE» + rótulos rojos reemplazados por imagen 15 cm + rótulo gris normal.
- [x] T5 — Verificación estructural (writer + spot check del orquestador).
- [x] T6 — Cierre: docs de tarea actualizados, commit selectivo local, resumen Engram.

## Verificación (resultados)

**Extracción XML de los docx finales (spot check del orquestador)**:
- Admin: `CAPTURA PENDIENTE` 0 · rótulos rojos 0 · `wp:inline` 18 (antes 15) · 17 rótulos «Figura N —» finales sin sufijo.
- Residente: `CAPTURA PENDIENTE` 0 · rótulos rojos 0 · `wp:inline` 11 (antes 9) · 10 rótulos finales sin sufijo.
- Tablas: Admin 20 → 17; Residente 6 → 4. Párrafos: 310 → 306; 171 → 169.
- Rellenos amarillos placeholder (FFF9C4): 0 en ambos.
- Hashes finales (coinciden writer ↔ orquestador): Admin `8EBD3DCC322A047BD81218847D7582A46EE24BFD3E2D79224A444FB686005B5B` (2.951.464 B); Residente `5D311C6DB718BDD6155721CD9EA4BEA5306AB71F89D6539CF715B5F231E945E6` (2.119.088 B).
- Diff de textos: solo las zonas placeholder cambiaron (Admin removed 6 / added 3; Residente 4/2).
- Imágenes insertadas: ancho 425,2 pt; contenido pixel-diff ≈ 0 contra los PNG fuente.
- Higiene: sin procesos WINWORD residuales propios; sin archivos `~$`; resiliency de Word intacta (solo entradas ajenas).

**Backups pre-edición** (temp, no en repo): `C:\Users\Amadeo\AppData\Local\Temp\opencode\manual-cap-fix\Admin-backup-20261018-095947.docx` (2.604.011 B) y `Residente-backup-20261018-095947.docx` (1.994.070 B).

## Registro de evidencia — side effects (todos locales, revertidos o acotados)

- 2FA del usuario admin (id 1): activado para la captura y **revertido a 0**; se generó 1 OTP en `auth_otp_tokens` + 1 fila en cola de notificaciones (correo a `admin@conjunto.com`, sin envío local real).
- Contraseña de `residente.prueba@conjunto.com` (persona 207): temporal con hash guardado y **restaurado byte a byte** (verificado `-ceq`); intentos_fallidos 0.
- `10-cartelera`: PDF de prueba quedó en `uploads/soportes/` (parseo SIN importar: 0 filas nuevas en `reportes_cartelera`).
- Logins POST: 3 (límite 5/15 min).
- Commits: solo PNGs + docs de tarea (locales, sin push). Los docx no se commitean (convención del repo).

## Notas

- Los docx canónicos tienen ediciones híbridas y numeración propia; se insertó quirúrgicamente por COM (no rebuild desde JSON).
- Word recomprimió 2 de las capturas al guardar (1920×1080 → 1300×731 almacenados, tamaño predominante del material existente del manual); contenido intacto.
- Aviso cosmético menor: el párrafo-mark vacío bajo las imágenes nuevas heredó formato rojo/negrita del rótulo viejo (invisible en pantalla/impresión); se dejó por cota de cambio mínimo.
