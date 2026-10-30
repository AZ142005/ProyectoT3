# Deuda manual: checkboxes por edificio con despliegue/contracción

## Objetivo

Recuperar el estilo de checkboxes del modal "Registrar Gasto o Deuda Manual",
manteniendo cada edificio colapsado por defecto y con una flecha para desplegar o
contraer sus departamentos. Así no se muestran las 252 unidades a la vez.

## Solución

- Cada edificio es un grupo (`deuda-grupo`) con:
  - Checkbox "todo el edificio" (`edificios[]`).
  - Botón con flecha (`expand_more` / `expand_less`) que despliega el panel
    `.deuda-grupo-unidades`.
- Los paneles inician ocultos (`style="display: none;"`).
- Dentro del panel están los checkboxes de unidades (`unidades[]`).
- Se restauran "Seleccionar todo", "Limpiar selección" y el buscador.
- El buscador muestra solo las unidades coincidentes, expande los edificios con
  resultados y los colapsa al limpiar el término.
- El contador cuenta unidades marcadas; "Todo el condominio" sigue funcionando.
- El formulario envía `edificios[]` y `unidades[]`; el backend no cambió.

## Verificación

- `DeudaManualUiTest`: 1 test, 9 aserciones, 0 fallos.
- Suite completa: 789 tests, 4468 aserciones, 0 fallos, 0 errores, 5 omitidos.
- `node --check` sobre el JavaScript extraído: OK.
- `check_purity` ✅ y `audit_security` ✅.

## Archivos

- `app/views/admin/gastos/index.php`
- `tests/DeudaManualUiTest.php`
