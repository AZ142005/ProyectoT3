# Manual de Usuario Final — plan y estado

**Objetivo:** Redactar y generar `docs/manual de usuario final.docx` (manual de usuario final completo, en español) para el Sistema de Cobranzas y Gestión de Pagos del Conjunto Residencial "Las Mesetas de Morón" (Condominio Digital), cubriendo los tres roles (Administrador, Residente, Auditor) y el portal público de pago; dejar lista la lista de capturas de pantalla faltantes para que el usuario las tome y, cuando lleguen, insertarlas y regenerar el documento.

## Contexto

- El archivo `docs/manual de usuario final.docx` estaba **vacío (0 bytes)** — se construye desde cero.
- Documento de salida: Word nativo generado por script (máquina del proyecto tiene Office16: `C:\Program Files\Microsoft Office\root\Office16\WINWORD.EXE`; sin Python; PHP 8.2 disponible).
- Estrategia: contenido en `scripts/manual/contenido_manual.json` + constructor `scripts/manual/build_manual.ps1` (Word COM, invisible) → genera `docs/manual de usuario final.docx` + copia PDF para revisión.
- Capturas en `docs/manual-capturas/` con nombres `NN-descripcion.png`; las faltantes se muestran como párrafo "CAPTURA PENDIENTE" (rojo, cursiva) y se embeben automáticamente al existir el archivo y regenerar.
- La app corre local en `http://localhost:8000` (login: "Iniciar Sesión - Condominio Digital"). XAMPP en :80 (no es la app). MySQL activo.
- Patrón del repo (memoria de sesiones previas): commits locales en `main`, sin push.

## Mapa de capturas (31 previstas; 5 ya disponibles)

| Archivo (`docs/manual-capturas/`) | Pantalla | Estado |
| --- | --- | --- |
| 01-login.png | Inicio de sesión (público) | ❌ pedir |
| 02-registro-residente.png | Auto-registro de residente | ❌ pedir |
| 03-verificacion-2fa.png | Verificación 2FA (opcional) | ❌ pedir (opcional) |
| 04-dashboard-admin.png | Dashboard administrador | ✅ (Captura 1) |
| 05-historial-pagos-admin.png | Historial de Pagos admin | ✅ (Captura 2) |
| 06-verificar-comprobante.png | Detalle/verificación de comprobante | ❌ pedir |
| 07-conciliacion-admin.png | Conciliación bancaria admin | ✅ (Captura 6) |
| 08-cuentas-bancarias.png | Cuentas Bancarias | ❌ pedir |
| 09-carga-emision.png | Carga y Emisión (PDF maestro) | ❌ pedir |
| 10-cartelera-cargar.png | Cargar reporte de cartelera (opcional) | ❌ pedir (opcional) |
| 11-estructura.png | Estructura (edificios/unidades) | ❌ pedir |
| 12-estacionamientos.png | Estacionamientos | ❌ pedir |
| 13-comunicados.png | Comunicados | ❌ pedir |
| 14-usuarios.png | Usuarios | ❌ pedir |
| 15-usuarios-solicitudes.png | Solicitudes (opcional) | ❌ pedir (opcional) |
| 16-carta-deuda.png | Carta de Deuda / morosidad | ❌ pedir |
| 17-carta-deuda-ejemplo.png | Ejemplo de carta (anexo) | ❌ pedir (opcional) |
| 18-respaldos.png | Respaldos | ❌ pedir |
| 19-pago-directo.png | Portal público de pago | ❌ pedir |
| 20-pago-directo-exito.png | Confirmación portal público | ❌ pedir |
| 21-residente-dashboard.png | Panel del residente | ❌ pedir |
| 22-residente-historial.png | Historial del residente | ❌ pedir |
| 23-residente-estado-cuenta.png | Estado de cuenta | ❌ pedir |
| 24-residente-nuevo-pago-paso1.png | Registrar pago — Paso 1 | ✅ (Captura 4) |
| 25-residente-nuevo-pago-paso2.png | Registrar pago — Paso 2 | ✅ (Captura 5) |
| 26-residente-gastos.png | Rendición de cuentas | ❌ pedir |
| 27-residente-notificaciones.png | Notificaciones | ❌ pedir |
| 28-residente-cartelera.png | Cartelera de comunicados | ❌ pedir |
| 29-perfil.png | Mi perfil | ❌ pedir |
| 30-auditor-log.png | Rol auditor: log de transacciones | ❌ pedir |
| 31-auditor-dashboard.png | Rol auditor: panel (opcional) | ❌ pedir (opcional) |

## Checklist

- [x] T1 — Exploración: rutas, roles, vistas, SRS, capturas existentes; copiar las 5 capturas a `docs/manual-capturas/`. (Evidencia: este archivo; comm del repo; capturas en `docs/manual-capturas/`.)
- [x] T2 — Inventario de pantallas + estructura + redacción del contenido (`scripts/manual/contenido_manual.json`, 336 nodos, 10 capítulos, FAQ + glosario + anexos). **Delegado** (escritor `general`; skill `cognitive-doc-design` inyectada).
- [x] T3 — Constructor Word COM (`scripts/manual/build_manual.ps1`) + build v1 (`docs/manual de usuario final.docx` + PDF de revisión). **Delegado**. Evidencia: BUILD OK ×2 (rea-run idempotente, 38 s c/u); docx 632.750 B; PDF 898.677 B.
- [x] T4 — Validación v1: zip válido; `word/media` = 6 (logo + 5 capturas); `CAPTURA PENDIENTE` = 26; Figuras 1–31 presentes; sin procesos WINWORD residuales. **Inline (gatekeeper)**. Commit local `docs(manual): ...` en `main` (sin push).
- [ ] T5 — Entregar al usuario: plan + lista de capturas faltantes + decisión de quién las toma (usuario o agente vía navegador con credenciales de prueba). Pregunta única, esperar.
- [ ] T6 — Insertar capturas recibidas + regenerar docx/PDF + validar conteos.
- [ ] T7 — Cierre: commit local en `main` (sin push) + resumen en Engram.

## Criterios de aceptación

- `docs/manual de usuario final.docx` abre en Word sin reparaciones y contiene contenido completo de los 3 roles + portal público.
- Toda captura disponible queda embebida con pie de figura ("Figura N — …").
- Cada captura faltante tiene un marcador visible con el nombre de archivo exacto esperado.
- Reejecutar `scripts/manual/build_manual.ps1` reconstruye el documento de forma idempotente.

## Próximo paso

T2/T3 delegados a un escritor con: TOC aprobado, mapa de capturas, datos del SRS, rutas de vistas y skill `cognitive-doc-design`.
