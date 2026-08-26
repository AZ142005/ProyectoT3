---
name: security-audit-tool
description: >-
  Playbook y herramientas para auditar la seguridad de la aplicación contra el Top 10 de OWASP
  (Inyección SQL, CSRF, XSS, autenticación, control de acceso y subida de archivos).
  Úsalo cuando el usuario pida verificar la seguridad del sistema o antes de desplegar cambios.
---

# Skill: Auditoría de Seguridad OWASP para PHP Vanilla

Esta habilidad provee una lista de verificación y un script de auditoría automatizado para detectar vulnerabilidades en el código fuente.

---

## 1. Verificación Automatizada

Ejecuta el script de auditoría estática de seguridad:
```bash
php scripts/audit_security.php
```

El script inspecciona:
- Consultas SQL directas sin `prepare()`.
- Formularios `<form method="POST">` sin `csrf_field()`.
- Impresiones de variables en vistas sin escapar con `e()`.
- Métodos públicos de controlador sin guardias `Auth::requireLogin()` o `Auth::requireRole()`.

---

## 2. Puntos de Control Manual (Manual Review Checklist)

1. **SQL Injection**:
   - Comprobar que en `app/models/` ninguna consulta use interpolación `"$variable"` o concatenación `. $variable`.
2. **CSRF**:
   - Todo formulario POST contiene `<?= csrf_field() ?>`.
   - `public/index.php` contiene `\App\Core\Security::validateCSRF()`.
3. **XSS**:
   - Toda salida en `app/views/` usa `<?= e($dato) ?>` en lugar de `<?= $dato ?>`.
4. **Control de Acceso**:
   - Todo método en `AdminController`, `EstructuraController`, `PagoController` valida el rol correspondiente.
5. **Carga de Archivos**:
   - Validar extensiones mediante whitelist: `['jpg', 'jpeg', 'png', 'pdf']`.
   - Verificar tamaño máximo de archivo.
   - Usar `move_uploaded_file()` con nombres de archivo desinfectados.
