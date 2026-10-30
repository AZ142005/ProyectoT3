# Carga de cédulas y claves iniciales

## Objetivo

Cargar la cédula de cada propietario desde el `listado condominios.pdf` (Listado de
Condóminos, 28/10/2026) y asignar como clave inicial la cédula numérica sin el
identificador (`V`, `E`, etc.).

## Problema y por qué

Los 221 propietarios cargados desde el Listado de Apartamentos tienen cédulas
marcadoras `SIN-<unidad>` y no pueden autenticarse. El listado de condóminos trae las
cédulas reales, pero **no trae el número de apartamento**: solo un código secuencial
(001–251). El cruce con las unidades se hace por correo, nombre normalizado, teléfono
y tokens.

## Decisiones

- Cédula normalizada a `V-<digitos>` o `E-<digitos>`, sin puntos, guiones internos ni
  ceros a la izquierda (`V-026264444` → `V-26264444`).
- Clave inicial: dígitos de la cédula, con `password_hash(..., PASSWORD_BCRYPT)`.
- `personas.cedula` es UNIQUE: cuando una misma persona aparece en dos apartamentos
  (misma cédula), la primera unidad conserva la cédula y la segunda queda con
  `SIN-<unidad>`, aunque recibe la misma clave.
- No se modifican nombres ni correos: el documento de condóminos es un día anterior.
- Los registros sin cédula en el documento no reciben clave.

## Tareas

- [x] T1. Extraer y auditar las 251 filas del Listado de Condóminos.
- [x] T2. Cruzar por correo/nombre/teléfono contra los 221 propietarios cargados.
- [x] T3. Normalizar cédulas y detectar duplicados y formatos irregulares.
- [x] T4. Generar `scripts/condominos_filas.json`.
- [x] T5. Implementar `scripts/asignar_cedulas_y_claves.php` con dry-run y `--apply`.
- [x] T6. Ejecutar dry-run y aplicar.
- [x] T7. Verificar cédulas, claves y unicidad en la base de datos.
- [x] T8. Ejecutar `check_purity`, `audit_security` y la suite de tests.

## Resultado esperado

- 214 propietarios con cédula real.
- 217 propietarios con clave inicial (los 214 más las 3 segundas unidades de cédulas
  duplicadas).
- 7 propietarios con `SIN-<unidad>`: 3 sin fila en el documento, 1 sin cédula y 3
  segundas unidades de cédulas duplicadas.
- 2 filas del documento sin apartamento (códigos 083 y 110), informadas como huérfanas.

## Progreso

- Cruce: 218 de 221 unidades con match (144 nombre exacto, 68 correo, 2 prefijo,
  2 tokens, 1 similitud, 1 teléfono).
- Sin match: 1-06 (BIEN COMUN / WUILERMA ARAUJO), 2-06 (JERYNA BERMUDEZ),
  3-43 (ORLANDO BRICEÑO).
- Sin cédula en el documento: 5-65 (PERLA GARCIA SALINAS).
- Cédulas duplicadas: 1-25/4-12, 5-36/5-63, 6-32/6-35.
- Formatos irregulares informados: 1-14 (dos cédulas), 2-05, 4-64, 5-04, 5-54.
- T6 aplicado: 214 cédulas actualizadas, 217 claves asignadas, 0 conflictos,
  0 errores. Segunda ejecución: 0 cambios, 214 cédulas ya presentes y 217 claves ya
  correctas (idempotente).
- T7: 214 propietarios con cédula real, 7 con `SIN-`, 217 con clave, 4 sin clave,
  0 cédulas duplicadas. `password_verify` correcto en 1-01, 1-03, 1-11 (E), 2-05,
  5-63, 6-35 y 6-66.
- T8: `check_purity` ✅, `audit_security` ✅. Suite: 778 tests, 4085 pasaron,
  1 falló (`LiquidacionPagoTest::pagoParcialSinExcedente`). El fallo no lo causa esta
  carga: `app/Services/LiquidacionPagoService.php` tiene un cambio local sin commitear
  (mtime 29/10 11:55) que guarda `vencida` en pagos parciales, mientras el test espera
  `pendiente`. Es trabajo en curso ajeno a esta tarea y no se modificó.

## Próximo paso

1. Resolver los 4 propietarios restantes con `SIN-`: 1-06, 2-06, 3-43 y 5-65.
   4-12, 5-63 y 6-35 quedaron resueltos el 2026-10-30 al quitar el UNIQUE global de
   `personas.cedula`.
2. Asignar las cédulas huérfanas 083 (JESUS ARAUJO) y 110 (THAIS PAREDES) cuando se
   conozca su apartamento.
3. Revisar 6-66: el teléfono coincide pero el nombre del condómino es distinto.
4. Exigir cambio de clave en el primer acceso: la clave inicial es la cédula numérica.

## Actualización 2026-10-30 — cédula sin UNIQUE global

- `scripts/migrate_personas_cedula_no_unique.php` eliminó el índice UNIQUE de
  `personas.cedula` y agregó el índice no único `idx_personas_cedula`.
- Se completaron las cédulas de las segundas unidades:
  - 4-12 → `V-24566338` (misma persona que 1-25).
  - 5-63 → `V-3453682` (misma persona que 5-36).
  - 6-35 → `V-16266526` (misma persona que 6-32).
- El login sigue siendo por correo; una misma cédula puede existir en unidades
  distintas.
- `UsuarioAdminController` solo valida duplicado cuando la cédula cambia, para no
  bloquear guardar una cédula duplicada que ya estaba registrada.
- `database/condominio_cobranzas.sql` actualizado con el índice no único y las tres
  cédulas corregidas.
- Quedan `SIN-` solo donde la fuente no trae cédula: 1-06, 2-06, 3-43 y 5-65.

