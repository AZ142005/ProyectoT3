# Directrices de Proyecto - Gemini & Antigravity

Este archivo define las directivas maestras para los agentes de desarrollo en este proyecto.

## 🎨 Jerarquía de Frameworks de Interfaz
- **Prioridad 1 (Principal)**: **Bootstrap 5, HTML5 semántico y CSS3 nativo** para todo el diseño base, componentes estructurales y nuevos desarrollos.
- **Prioridad 2 (Secundaria / Soporte)**: **Tailwind CSS** en segundo plano para utilidades rápidas y soporte a vistas preexistentes.

## Reglas Maestras
1. **PHP Vanilla MVC**: Respetar la arquitectura sin frameworks externos. Cero `echo` o etiquetas HTML en controladores o modelos.
2. **Presentación con Bootstrap 5 (Prioritaria)**:
   - Maquetación con `.container-fluid`, `.row`, `.col-*`.
   - Componentes estándar: `.card`, `.table`, `.modal`, `.alert`, `.badge`, `.btn`.
   - Estilos personalizados en CSS nativo cuando sea necesario.
3. **Seguridad Total (OWASP)**:
   - `PDO::prepare()` en todas las consultas con parámetros externos.
   - `<?= csrf_field() ?>` en todos los formularios POST.
   - `<?= e($variable) ?>` en todas las impresiones dinámicas de vistas.
   - Contraseñas con `password_hash($pass, PASSWORD_BCRYPT)`.
4. **Verificación Continua**:
   - Ejecutar `php scripts/check_purity.php` para validar pureza MVC.
   - Ejecutar `php scripts/audit_security.php` para validar seguridad estática.
   - Ejecutar `php tests/run.php` para validar suite de pruebas.

Para consultar detalles específicos, revisa la carpeta `.agents/rules/` y `.agents/skills/`.
