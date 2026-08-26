# Reglas de Seguridad OWASP y Protección de Datos

## 1. Prevención de Inyección SQL (SQL Injection - A03:2021)
- **Mandatorio**: Todas las consultas SQL que reciban parámetros externos DEBEN utilizar consultas preparadas PDO (`prepare()` y `execute()`) con placeholders (`:parametro` o `?`).
- **PROHIBIDO**: Concatenar o interpolar variables directamente en cadenas SQL:
  ```php
  // ❌ NUNCA HACER ESTO:
  $db->query("SELECT * FROM personas WHERE cedula = '$cedula'");

  // ✅ CORRECTO:
  $stmt = $db->prepare("SELECT * FROM personas WHERE cedula = :cedula");
  $stmt->execute(['cedula' => $cedula]);
  ```

---

## 2. Protección Contra Falsificación de Petición en Sitios Cruzados (CSRF - A01:2021)
- **Formularios**: Todo formulario con método `POST` DEBE incluir el campo oculto con el token CSRF usando el helper:
  ```html
  <form method="POST" action="/admin/estructura/edificio/guardar">
      <?= csrf_field() ?>
      <!-- campos del formulario -->
  </form>
  ```
- **Validación**: El Front Controller (`public/index.php`) ejecuta automáticamente `App\Core\Security::validateCSRF()` en cada petición `POST`. Si el token falta o no coincide con `$_SESSION['csrf_token']`, se aborta la petición con HTTP 403.

---

## 3. Prevención de Cross-Site Scripting (XSS - A03:2021)
- **Escapado en Vistas**: Toda salida de datos dinámicos inyectados o proporcionados por el usuario DEBE imprimirse utilizando la función helper `e()` (que aplica `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`):
  ```php
  // ❌ NUNCA HACER ESTO:
  <h1>Bienvenido <?= $usuario['nombre'] ?></h1>

  // ✅ CORRECTO:
  <h1>Bienvenido <?= e($usuario['nombre']) ?></h1>
  ```

---

## 4. Almacenamiento Seguro de Contraseñas (A02:2021 - Cryptographic Failures)
- **Algoritmo**: Utilizar `password_hash($plainPassword, PASSWORD_BCRYPT)` para registrar o actualizar contraseñas.
- **Verificación**: Utilizar `password_verify($inputPassword, $hashedPassword)`.
- **PROHIBIDO**: Almacenar contraseñas en texto plano, MD5 o SHA1.

---

## 5. Seguridad en Carga de Archivos (Uploads)
- **Extensiones permitidas**: Validar estrictamente la extensión del archivo (`jpg`, `jpeg`, `png`, `pdf`).
- **Renombrado**: Generar un nombre único de archivo aleatorio/basado en timestamp para evitar sobreescritura o ejecución arbitraria (`comp_YYYYMMDD_HHMMSS_residenteID.ext`).
- **Almacenamiento**: Guardar los archivos fuera de la raíz pública ejecutable o en `uploads/comprobantes/` con permisos controlados.
