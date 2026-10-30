# Carga de propietarios por número de apartamento

## Objetivo

Cargar en la base de datos el nombre y el correo electrónico de cada propietario,
asociados al número de apartamento del documento `condomites y numero de apto-.pdf`
(CONDOMINIO CONJUNTO LAS MESETAS, Listado de Apartamentos, 252 inmuebles).

## Problema y por qué

Las 252 unidades (`1-01` … `6-66`) y las 6 torres ya existen en la base de datos
(importadas desde el Reporte de Cartelera), pero `unidades.propietario_id` está en
NULL y la tabla `personas` solo contiene registros de prueba. El sistema no puede
emitir cartas de deuda, notificaciones ni mostrar el propietario en reportes.

## Alcance

- Leer y normalizar el listado del PDF en un dataset reproducible
  (`scripts/propietarios_filas.json`).
- Crear/actualizar una persona `tipo = propietario` por apartamento y enlazarla con
  `unidades.propietario_id`.
- No se tocan apartamentos `SIN REGISTRO`.

## Restricciones y decisiones

- `personas.email` es `NOT NULL UNIQUE` y `personas.cedula` es `NOT NULL UNIQUE`.
- El documento no trae cédulas → se usa `SIN-<unidad>` como marcador único y trazable.
- Correos faltantes o inválidos → `sin-correo-<unidad>@condominio.local`, misma
  convención que `scripts/migrate_auth_email.php`.
- Correos repetidos en dos apartamentos → la primera unidad conserva el correo real y
  la siguiente usa `dup-<unidad>-<correo>` para respetar `uq_personas_email`.
- Nombres compuestos o copropietarios se guardan completos en `personas.nombre`;
  `apellido` queda vacío para no inventar una separación que el documento no define.
- La carga es idempotente: por defecto no sobrescribe un propietario ya cargado.

## Tareas

- [x] T1. Extraer y auditar las 252 filas del PDF (parser + normalización de artefactos).
- [x] T2. Generar `scripts/propietarios_filas.json` con unidad, nombre, correo y cédula.
- [x] T3. Implementar `scripts/importar_propietarios_por_unidad.php` con dry-run y `--apply`.
- [x] T4. Ejecutar la simulación y validar conteos.
- [x] T5. Aplicar la carga en la base de datos.
- [x] T6. Verificar en BD: propietarios por unidad y distribución por torre.
- [x] T7. Ejecutar `check_purity`, `audit_security` y la suite de tests.

## Criterios de aceptación

- 221 apartamentos con propietario asignado (252 menos 31 `SIN REGISTRO`).
- `unidades.propietario_id` no nulo en esas 221 unidades.
- 152 correos reales del documento, 66 placeholders y 3 correos desambiguados.
- Ningún error de unicidad (`cedula` o `email`).

## Verificación

- `php scripts/importar_propietarios_por_unidad.php` (dry-run sin cambios).
- `php scripts/importar_propietarios_por_unidad.php --apply`.
- Consultas de control contra `personas` y `unidades`.
- `php scripts/check_purity.php`, `php scripts/audit_security.php`, `php tests/run.php`.

## Progreso

- T1/T2 completados: 252 filas, 221 importables, 31 `SIN REGISTRO`, 152 correos
  reales, 66 placeholders, 3 duplicados desambiguados, 2 correos inválidos en el
  documento (`2-56`, `6-45`).
- T3/T4: dry-run sin errores (221 creados simulados, 0 conflictos).
- T5: `--apply` ejecutado. 221 propietarios creados y 221 unidades vinculadas.
- T6: 221 personas propietarias activas con unidad; 221 unidades con
  `propietario_id`; 31 sin propietario; 0 vínculos inconsistentes; 0 duplicados de
  email o cédula. Distribución por torre: T1 37, T2 34, T3 35, T4 36, T5 39, T6 40.
- Segunda ejecución de `--apply`: 0 creados, 221 ya cargados (idempotente).
- T7: `check_purity` ✅, `audit_security` ✅, `tests/run.php` 778 tests / 4086
  aserciones / 0 fallos / 5 omitidos.
- Ruta: directa inline (un único script de migración, sin cambios en app/).
- Los archivos `scripts/importar_propietarios_por_unidad.php` y
  `scripts/propietarios_filas.json` quedan sin commitear (árbol de trabajo con
  cambios ajenos y datos personales en el dataset).

## Próximo paso

Revisar los 66 correos placeholder y los 2 correos inválidos del documento cuando el
administrador disponga de los datos reales; luego ejecutar con `--actualizar`.
