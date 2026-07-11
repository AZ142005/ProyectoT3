<?php
session_start();

if (isset($_SESSION['admin_usuario'])) {
    header('Location: admin/index.php');
    exit;
}

if (isset($_SESSION['residente_id'])) {
    header('Location: residente/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cedula'])) {
    $cedula = trim($_POST['cedula']);
    
    if (empty($cedula)) {
        $error = 'Por favor, ingresa tu cedula.';
    } else {
        require_once 'admin/config/database.php';
        
        $residente = getRecord(
            "SELECT id FROM personas WHERE cedula = ? AND estado = 1",
            [$cedula],
            "s"
        );
        
        if ($residente) {
            $_SESSION['residente_id'] = $residente['id'];
            header('Location: residente/dashboard.php');
            exit;
        } else {
            $error = 'La cedula ingresada no existe en el sistema.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Cobranzas - Condominio</title>
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
            flex-wrap: nowrap;
        }
        
        .main-header .logo {
            flex: 0 1 auto;
        }
        
        .main-header .logo h1 {
            font-size: 24px;
            font-weight: 700;
            white-space: nowrap;
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
            white-space: nowrap;
        }
        
        .main-header .header-actions {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
        }
        
        .main-header .header-actions .btn-admin {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 8px 25px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
            border: 1px solid rgba(255,255,255,0.2);
            white-space: nowrap;
            display: inline-block;
        }
        .main-header .header-actions .btn-admin:hover {
            background: rgba(255,255,255,0.25);
        }

        .hero-section {
            background: linear-gradient(135deg, #1a7a3a, #27ae60);
            color: white;
            padding: 60px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            width: 100%;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
        }
        .hero-section .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }
        .hero-section .hero-content h2 {
            font-size: 34px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .hero-section .hero-content h2 span {
            color: #f1c40f;
        }
        .hero-section .hero-content p {
            font-size: 17px;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .acceso-section {
            padding: 50px 40px;
            max-width: 500px;
            margin: 0 auto;
            width: 100%;
            flex: 1;
        }

        .acceso-card {
            background: white;
            border-radius: 16px;
            padding: 40px 35px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 2px solid #d5f5e3;
            width: 100%;
        }

        .acceso-card .card-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            background: #27ae60;
            color: white;
        }

        .acceso-card h4 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .acceso-card .card-desc {
            font-size: 14px;
            color: #5a7a6a;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #d5f5e3;
            border-radius: 8px;
            font-size: 16px;
            transition: 0.3s;
            outline: none;
        }

        .form-group input:focus {
            border-color: #27ae60;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }

        .btn-ingresar {
            width: 100%;
            padding: 12px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-ingresar:hover {
            background: #1e8449;
            transform: scale(1.02);
        }

        .error-message {
            background: #fde8e8;
            color: #c0392b;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            border: 1px solid #f5c6c6;
        }

        .main-footer {
            background: #1a252f;
            color: rgba(255,255,255,0.5);
            padding: 20px 40px;
            text-align: center;
            font-size: 13px;
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.05);
            width: 100%;
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
            margin-bottom: 10px;
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
                white-space: normal;
            }
            .main-header .logo small {
                white-space: normal;
            }
            
            .main-header .header-actions {
                width: 100%;
                justify-content: center;
            }
            
            .main-header .header-actions .btn-admin {
                font-size: 14px;
                padding: 10px 30px;
                width: auto;
                min-width: 120px;
            }
            
            .hero-section {
                padding: 40px 20px;
            }
            .hero-section .hero-content h2 {
                font-size: 26px;
            }
            
            .acceso-section {
                padding: 30px 20px;
            }
            .acceso-card {
                padding: 30px 25px;
            }
            .acceso-card h4 {
                font-size: 19px;
            }
            
            .main-footer {
                padding: 15px 20px;
            }
            .main-footer .footer-links {
                gap: 10px;
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .main-header {
                padding: 12px 15px;
                gap: 8px;
            }
            
            .main-header .logo h1 {
                font-size: 18px;
            }
            .main-header .logo small {
                font-size: 10px;
            }
            
            .main-header .header-actions .btn-admin {
                font-size: 14px;
                padding: 8px 25px;
                min-width: 100px;
            }
            
            .hero-section {
                padding: 30px 15px;
            }
            .hero-section .hero-content h2 {
                font-size: 22px;
            }
            .hero-section .hero-content p {
                font-size: 15px;
            }
            
            .acceso-section {
                padding: 20px 15px;
            }
            .acceso-card {
                padding: 25px 20px;
            }
            .acceso-card h4 {
                font-size: 18px;
            }
            .acceso-card .card-desc {
                font-size: 13px;
            }
            
            .form-group input {
                font-size: 14px;
                padding: 10px 12px;
            }
            .btn-ingresar {
                font-size: 14px;
                padding: 10px;
            }
            
            .main-footer {
                padding: 12px 15px;
                font-size: 11px;
            }
            .main-footer .footer-links a {
                font-size: 11px;
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
            <a href="admin/login.php" class="btn-admin">
                Admin
            </a>
        </div>
    </header>

    <section class="hero-section">
        <div class="hero-content">
            <h2>Bienvenido al <span>Portal de Pagos</span></h2>
            <p>
                Gestiona tus pagos de condominio de forma rapida y segura. 
                Envia comprobantes, consulta tu estado de cuenta y recibe 
                confirmacion en tiempo real.
            </p>
        </div>
    </section>

    <section class="acceso-section">
        <div class="acceso-card">
            <span class="card-badge">Residente</span>
            <h4>Portal del Residente</h4>
            <p class="card-desc">
                Ingresa tu numero de cedula para consultar tu estado de cuenta,
                enviar comprobantes de pago y hacer seguimiento.
            </p>

            <?php if ($error): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="cedula">Numero de Cedula</label>
                    <input type="text" id="cedula" name="cedula" placeholder="V12345678" required autofocus>
                </div>
                <button type="submit" class="btn-ingresar">
                    Ingresar al Portal
                </button>
            </form>
        </div>
    </section>

    <footer class="main-footer">
        <div class="footer-links">
            <a href="admin/login.php">Administrador</a>
            <span class="separator">|</span>
            <a href="#">Terminos y Condiciones</a>
        </div>
        <p>
            &copy; <?= date('Y') ?> Condominio Digital - Sistema de Cobranzas. Todos los derechos reservados.
        </p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const card = document.querySelector('.acceso-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 200);
        });
    </script>

</body>
</html>