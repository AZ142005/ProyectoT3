<?php
session_start();

if (isset($_SESSION['admin_usuario'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($usuario) || empty($password)) {
        $error = "Ingrese usuario y contraseña";
    } else {
        $user = getRecord(
            "SELECT * FROM usuarios WHERE usuario = ? AND estado = 1",
            [$usuario],
            "s"
        );
        
        if ($user && $user['password'] === $password) {
            $_SESSION['admin_usuario'] = $user['usuario'];
            $_SESSION['admin_nombre'] = $user['nombre_completo'];
            $_SESSION['admin_rol'] = $user['rol'];
            $_SESSION['admin_id'] = $user['id'];
            
            executeNonQuery(
                "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?",
                [$user['id']],
                "i"
            );
            
            header('Location: index.php');
            exit;
        } else {
            $error = "Usuario o contraseña incorrectos";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrador</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f7f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-header {
            background: linear-gradient(135deg, #1a7a3a, #2ecc71);
            color: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            width: 100%;
        }
        
        .main-header .logo h1 {
            font-size: 24px;
            font-weight: 700;
        }
        .main-header .logo h1 span {
            font-weight: 300;
            color: #f1c40f;
        }
        .main-header .logo small {
            display: block;
            font-size: 11px;
            opacity: 0.8;
            font-weight: 300;
        }
        
        .main-header .header-actions .btn-inicio {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 8px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.3s;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .main-header .header-actions .btn-inicio:hover {
            background: rgba(255,255,255,0.25);
        }

        .hero-section {
            background: linear-gradient(135deg, #1a7a3a, #27ae60);
            color: white;
            padding: 40px 40px;
            text-align: center;
        }
        .hero-section .hero-content h2 {
            font-size: 28px;
            font-weight: 700;
        }
        .hero-section .hero-content h2 span {
            color: #f1c40f;
        }
        .hero-section .hero-content p {
            font-size: 16px;
            opacity: 0.9;
            max-width: 500px;
            margin: 0 auto;
            line-height: 1.5;
        }

        .login-section {
            padding: 40px 20px;
            max-width: 420px;
            margin: 0 auto;
            width: 100%;
            flex: 1;
        }

        .login-card {
            background: white;
            border-radius: 12px;
            padding: 35px 30px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid #d6eaf8;
        }

        .login-card .badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            background: #2c3e50;
            color: white;
        }

        .login-card h4 {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .login-card .desc {
            font-size: 14px;
            color: #5a7a6a;
            margin-bottom: 22px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .form-group .input-wrapper {
            position: relative;
        }

        .form-group .input-wrapper input {
            width: 100%;
            padding: 10px 40px 10px 14px;
            border: 2px solid #e8f5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.3s;
            outline: none;
            background: #fafffa;
        }

        .form-group .input-wrapper input:focus {
            border-color: #27ae60;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.08);
        }

        .form-group .input-wrapper input::placeholder {
            color: #b8c8be;
        }

        .form-group .input-wrapper .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #b8c8be;
            padding: 4px;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-group .input-wrapper .toggle-password:hover {
            color: #2c3e50;
        }

        .form-group .input-wrapper .toggle-password:focus {
            outline: none;
        }

        .btn-login {
            width: 100%;
            padding: 11px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-login:hover {
            background: #1a252f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44, 62, 80, 0.2);
        }

        .error-message {
            background: #fde8e8;
            color: #c0392b;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            border: 1px solid #f5c6c6;
        }

        .main-footer {
            background: #1a252f;
            color: rgba(255,255,255,0.5);
            padding: 16px 40px;
            text-align: center;
            font-size: 13px;
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .main-footer a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            transition: 0.3s;
        }
        .main-footer a:hover {
            color: #f1c40f;
        }
        .main-footer .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .main-footer .footer-links a {
            font-size: 12px;
        }
        .main-footer .separator {
            opacity: 0.2;
            margin: 0 5px;
        }

        @media (max-width: 768px) {
            .main-header {
                padding: 15px 20px;
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            .main-header .logo h1 {
                font-size: 20px;
            }
            .hero-section {
                padding: 30px 20px;
            }
            .hero-section .hero-content h2 {
                font-size: 24px;
            }
            .login-section {
                padding: 30px 20px;
            }
            .login-card {
                padding: 28px 22px;
            }
            .main-footer {
                padding: 14px 20px;
            }
            .main-footer .footer-links {
                flex-direction: column;
                gap: 8px;
            }
        }

        @media (max-width: 480px) {
            .main-header .logo h1 {
                font-size: 18px;
            }
            .main-header .logo small {
                font-size: 10px;
            }
            .hero-section .hero-content h2 {
                font-size: 20px;
            }
            .hero-section .hero-content p {
                font-size: 14px;
            }
            .login-card h4 {
                font-size: 18px;
            }
            .form-group .input-wrapper input {
                font-size: 13px;
                padding: 9px 38px 9px 12px;
            }
            .form-group .input-wrapper .toggle-password {
                font-size: 16px;
                right: 10px;
            }
            .btn-login {
                font-size: 14px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="logo">
            <h1>Condominio <span>Digital</span></h1>
            <small>Sistema de Cobranzas y Gestion de Pagos</small>
        </div>
        <div class="header-actions">
            <a href="../index.php" class="btn-inicio">Inicio</a>
        </div>
    </header>

    <section class="hero-section">
        <div class="hero-content">
            <h2>Panel de <span>Control</span></h2>
            <p>Accede al panel de administracion para gestionar residentes, verificar pagos y aprobar comprobantes.</p>
        </div>
    </section>

    <section class="login-section">
        <div class="login-card">
            <span class="badge">Administrador</span>
            <h4>Iniciar Sesión</h4>
            <p class="desc">Ingresa tus credenciales para acceder al panel de control.</p>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <div class="input-wrapper">
                        <input type="text" id="usuario" name="usuario" placeholder="Ingrese su usuario" required autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Ingrese su contraseña" required>
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Mostrar contraseña">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-login">Iniciar Sesión</button>
            </form>
        </div>
    </section>

    <footer class="main-footer">
        <div class="footer-links">
            <a href="../index.php">Volver al inicio</a>
            <span class="separator">|</span>
            <a href="#">Terminos y Condiciones</a>
        </div>
        <p>&copy; <?= date('Y') ?> Condominio Digital - Sistema de Cobranzas. Todos los derechos reservados.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Cambiar el icono
                const svg = this.querySelector('svg');
                if (type === 'text') {
                    svg.innerHTML = `
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    `;
                    this.setAttribute('aria-label', 'Ocultar contraseña');
                } else {
                    svg.innerHTML = `
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    `;
                    this.setAttribute('aria-label', 'Mostrar contraseña');
                }
            });
        });
    </script>

</body>
</html>