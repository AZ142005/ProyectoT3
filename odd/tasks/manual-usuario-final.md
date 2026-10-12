# Manual de Usuario Final — plan y estado

**Objetivo:** `docs/manual de usuario final.docx` (español) del Sistema de Cobranzas — Conjunto Residencial "Las Mesetas de Morón" (Condominio Digital): guía completa (PARTE I administrador/fiscalización, PARTE II residente) con capturas embebidas y pendientes marcadas; regenerable con `scripts/manual/build_manual.ps1` desde `scripts/manual/contenido_manual.json`.

## Estado (2026-10-12)

- **v2 generada y validada**: 24 capítulos numerados en dos partes + portada/control/índice; **28 figuras** = 22 embebidas (logo + 21 capturas) + **7 placeholders** "CAPTURA PENDIENTE"; ~50 páginas; docx 3,37 MB / PDF 2,16 MB; builds idempotentes (~33 s); sin procesos WINWORD residuales.
- **Estructura final**: 1 Introducción · 2 Acceso · **PARTE I** (caps. 3–14: Dashboard, Historial/verificación, Conciliación, Cuentas, Carga y Emisión, Estructura, Estacionamientos, Comunicados, Usuarios, Carta de Deuda, Respaldos, Rol Auditor [fusionado]) · **PARTE II** (caps. 15–20: Portal público, Panel, Registrar pago, Mis Pagos, Notificaciones, Cartelera) · 21 Mi Perfil · 22 FAQ · 23 Glosario · 24 Anexos.
- **Decisiones aplicadas (pedido del usuario, 12/10)**: registro en 2 figuras (`02-registro-residente(1).png` / `(2).png`); eliminadas secciones y capturas de estado de cuenta (23) y rendición (26) — más la FAQ asociada y el término del glosario; Notificaciones y Cartelera sin captura propia (el panel, fig. 21, ya las cubre); auditor = mismas vistas que el admin en lectura, fusionado en el cap. 14.
- **Hallazgo**: `/residente/historial` es código muerto (redirige a `/pagos`). La sección 18 describe "Mis Pagos" real (columnas verificadas contra `pagos/residente/lista.php` y `pagos/detalle.php`).
- **Datos de prueba (captura 22)**: `residente.prueba@conjunto.com` (persona 207, unidad A-101) con 5 pagos variados (2 aprobados, en revisión, pendiente, rechazado con motivo; 3 con comprobante) sembrados por `scripts/seed_pagos_residente_prueba.php` (idempotente; facturas intactas; deuda del dashboard sigue en Bs. 450,00).

## Capturas

**Embebidas (21):** 01, 02(1), 02(2), 04, 05, 06, 07, 08, 09, 11, 12, 13, 14, 15, 16, 17, 19, 21, 24, 25, 29.

**Pendientes (7):**
- [ ] `18-respaldos.png` (esencial)
- [ ] `20-pago-directo-exito.png` (esencial)
- [ ] `22-residente-historial.png` — capturar "Mis Pagos" (`/pagos`) con `residente.prueba@conjunto.com` (esencial)
- [ ] `30-auditor-vista-lectura.png` (esencial)
- [ ] `03-verificacion-2fa.png`, `10-cartelera-cargar.png`, `31-auditor-log.png` (opcionales)

**Eliminadas del plan:** 23, 26, 27, 28 (esta ronda) + sus figuras.

## Checklist

- [x] T1 — Exploración + plan + capturas base (v1).
- [x] T2/T3 — Contenido v1 + builder Word COM + build (delegado).
- [x] T4 — Validación v1 (gatekeeper).
- [x] T5 — Plan de capturas entregado al usuario (decisión: las toma el usuario).
- [x] T6a — Reestructura en dos partes + ajustes (2 figuras de registro; −23/26/27/28; auditor fusionado; sección "Mis Pagos" real; índice; nodo `part`).
- [x] T6b — Datos de prueba sembrados para la captura 22 (seed idempotente).
- [ ] T6c — Incorporar las 7 capturas restantes (18, 20, 22, 30 + opcionales) y regenerar.
- [ ] T7 — Cierre: commit final + resumen en Engram.

## Regenerar

`powershell -NoProfile -ExecutionPolicy Bypass -File scripts\manual\build_manual.ps1` → `docs/manual de usuario final.docx` + `.pdf`. Los placeholders se sustituyen solos al existir el PNG con el nombre exacto en `docs/manual-capturas/`.
