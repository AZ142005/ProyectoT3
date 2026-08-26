# Guía de Agente y Reglas del Proyecto (ProyectoT3)

Este repositorio contiene un sistema de cobranzas y gestión de condominios desarrollado en **PHP Vanilla** con arquitectura **MVC Pura** (sin frameworks pesados), **Bootstrap 5, HTML5 y CSS3 nativo** para la interfaz visual, y **MySQL** para la base de datos.

---

## 🎨 Directriz de Frameworks de Frontend
- **Prioridad 1 (Principal)**: La capa de presentación principal y nuevos módulos deben utilizar **Bootstrap 5, HTML5 semántico y CSS3 nativo**.
- **Prioridad 2 (Secundaria / Soporte)**: **Tailwind CSS** se mantiene en segundo plano exclusivamente para mantenimiento de vistas existentes y utilidades auxiliares.

---

## Estructura del Sistema

- `app/controllers/`: Controladores delgados que orquestan modelos y renderizan vistas.
- `app/models/`: Modelos con sentencias preparadas PDO (`Database::getConnection()`).
- `app/views/`: Vistas de presentación en PHP maquetadas con Bootstrap 5 y HTML5 (escapadas con `e()`).
- `app/core/`: Componentes base del sistema (`Controller`, `Database`, `Security`, `Auth`, `helpers.php`).
- `public/`: Punto de entrada único (`index.php` Front Controller) y recursos estáticos.
- `scripts/`: Scripts ejecutables por CLI para migraciones, seeders y verificación de pureza/seguridad.
- `tests/`: Suite de pruebas unitarias y de integración (`php tests/run.php`).
- `.agents/rules/`: Reglas obligatorias de arquitectura, seguridad OWASP, Bootstrap UI y base de datos.
- `.agents/skills/`: Habilidades especializadas y runbooks de procedimientos para el agente.

---

## Reglas Obligatorias en Desarrollo

1. **Separación de Responsabilidades**: Prohibido emitir HTML o ejecutar SQL dentro de Controladores o Modelos.
2. **Seguridad OWASP**: Todas las consultas a BD usan `prepare()`. Todos los formularios POST llevan `csrf_field()`. Toda salida en vistas usa `e()`.
3. **Estilos y Maquetación**: Priorizar componentes de Bootstrap 5 (`.card`, `.table`, `.modal`, `.btn-primary`, `.badge`, `.alert`, grid `.row .col-*`).
4. **Verificación y Pruebas**: Antes de finalizar cualquier tarea, ejecutar:
   ```bash
   php scripts/check_purity.php
   php scripts/audit_security.php
   php tests/run.php
   ```

---

## Habilidades Disponibles (`.agents/skills/` y `skills/`)
- `bootstrap-ui-components`: (Prioridad 1) Patrones de componentes UI con Bootstrap 5, HTML5 y CSS3.
- `tailwind-utility-styling`: (Prioridad 2) Utilidades complementarias de Tailwind CSS para mantenimiento de vistas heredadas.
- `php-mvc-architect`: Creación y refactorización de módulos MVC puros.
- `security-audit-tool`: Auditoría estática de vulnerabilidades OWASP.
- `db-migrator-seeder`: Scripts de migración y seeders de base de datos.
- `automated-testing-runner`: Ejecución y creación de pruebas unitarias con `Tests\TestCase`.
- `api-json-endpoints`: Construcción y consumo de endpoints AJAX / JSON seguros.
- `performance-query-optimizer`: Optimización de consultas SQL, índices y paginación.
- `git-workflow-conventions`: Protocolos de sincronización segura y commits semánticos en Git.
