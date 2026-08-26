---
name: git-workflow-conventions
description: >-
  Playbook de seguridad en Git, convenciones de commits semánticos y sincronización de equipo para prevenir pérdida de código.
  Úsalo cuando el usuario pida gestionar ramas, hacer commits seguros o sincronizar con repositorios remotos.
---

# Skill: Flujo de Trabajo Seguro en Git y Commits Semánticos

Esta habilidad previene pérdidas accidentales de código y estandariza la colaboración en equipo.

---

## 1. Protocolo de Seguridad Antes de Sincronizar (`pull` o `push`)

1. **Verificar estado local**:
   ```bash
   git status
   ```
2. **Guardar cambios pendientes (Commit o Stash)**:
   - Si los cambios están listos: `git add . && git commit -m "feat: ..."`.
   - Si son temporales: `git stash push -m "wip-cambios-locales"`.
3. **Actualizar sin sobreescribir destructivamente**:
   ```bash
   git pull --rebase origin main
   ```
4. **Verificar historial**:
   ```bash
   git log --oneline -5
   ```

---

## 2. Convención de Commits Semánticos (Conventional Commits)
- `feat:` Nuevas funcionalidades (ej. `feat(pagos): agregar extracción de comprobante`).
- `fix:` Corrección de errores (ej. `fix(auth): corregir hash bcrypt en login`).
- `refactor:` Mejoras de código sin cambiar funcionalidad (ej. `refactor(mvc): aislar consultas en PagoModel`).
- `style:` Cambios en Bootstrap/CSS sin tocar lógica (ej. `style(dashboard): migrar tablas a Bootstrap 5`).
- `test:` Creación o modificación de pruebas (ej. `test(seguridad): añadir test de validación CSRF`).
- `docs:` Cambios en documentación o reglas (ej. `docs: actualizar AGENTS.md con skills`).
