<?php
session_start();
if (!isset($_SESSION['admin_usuario'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

$mensaje = '';
$error = '';

$unidades = getRecords("
    SELECT id, numero, cuota_mensual 
    FROM unidades 
    WHERE estado = 1
");

$mes = date('n');
$anio = date('Y');

$facturas_existentes = getRecord(
    "SELECT COUNT(*) as total FROM facturas WHERE mes = ? AND anio = ?",
    [$mes, $anio],
    "ii"
)['total'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar'])) {
    
    if ($facturas_existentes > 0) {
        $error = "Ya existen facturas para el mes " . nombreMes($mes) . " de " . $anio;
    } else {
        $generadas = 0;
        $con_saldo_favor = 0;
        $total_saldo_favor_usado = 0;
        
        foreach ($unidades as $unidad) {
            // Obtener el saldo a favor de la unidad (suma de todas las facturas con saldo negativo)
            $saldo_favor = getRecord(
                "SELECT SUM(saldo) as total FROM facturas WHERE unidad_id = ? AND saldo < 0",
                [$unidad['id']],
                "i"
            )['total'] ?? 0;
            
            $monto_factura = $unidad['cuota_mensual'];
            $monto_a_pagar = $monto_factura;
            $saldo_restante = 0;
            $estado = 'pendiente';
            
            if ($saldo_favor < 0) {
                $saldo_favor_abs = abs($saldo_favor);
                $con_saldo_favor++;
                $total_saldo_favor_usado += min($saldo_favor_abs, $monto_factura);
                
                if ($saldo_favor_abs >= $monto_factura) {
                    // El saldo a favor cubre toda la factura
                    $monto_a_pagar = 0;
                    $saldo_restante = 0; // No queda saldo pendiente
                    $estado = 'pagada';
                    
                    // Actualizar las facturas anteriores: poner saldo = 0
                    executeNonQuery(
                        "UPDATE facturas SET saldo = 0 WHERE unidad_id = ? AND saldo < 0",
                        [$unidad['id']],
                        "i"
                    );
                    
                } else {
                    // El saldo a favor cubre parte de la factura
                    $monto_a_pagar = $monto_factura - $saldo_favor_abs;
                    $saldo_restante = $monto_a_pagar;
                    $estado = 'pendiente';
                    
                    // Actualizar las facturas anteriores: poner saldo = 0
                    executeNonQuery(
                        "UPDATE facturas SET saldo = 0 WHERE unidad_id = ? AND saldo < 0",
                        [$unidad['id']],
                        "i"
                    );
                }
            } else {
                $saldo_restante = $monto_a_pagar;
            }
            
            // Si el saldo restante es 0, la factura está pagada
            if ($saldo_restante <= 0) {
                $estado = 'pagada';
                $saldo_restante = 0;
            }
            
            $numero_factura = 'FAC-' . $anio . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-' . str_pad($unidad['id'], 4, '0', STR_PAD_LEFT);
            
            $fecha_emision = date('Y-m-d');
            $fecha_vencimiento = date('Y-m-d', strtotime('+15 days'));
            
            $sql = "INSERT INTO facturas 
                    (numero_factura, unidad_id, mes, anio, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, saldo, estado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $result = executeNonQuery(
                $sql,
                [
                    $numero_factura, 
                    $unidad['id'], 
                    $mes, 
                    $anio, 
                    $fecha_emision, 
                    $fecha_vencimiento, 
                    $monto_factura, 
                    ($monto_factura - $monto_a_pagar), 
                    $saldo_restante, 
                    $estado
                ],
                "siiisdddss"
            );
            
            if ($result) {
                $generadas++;
            }
        }
        
        $mensaje = "Se generaron $generadas facturas para el mes " . nombreMes($mes) . " de $anio.";
        if ($con_saldo_favor > 0) {
            $mensaje .= " Se usó saldo a favor en $con_saldo_favor unidades (total usado: " . formatearMoneda($total_saldo_favor_usado) . ").";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Facturas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f7f0;
            color: #1a3a2a;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 220px;
            background: linear-gradient(180deg, #1a7a3a, #1a252f);
            color: white;
            padding: 25px 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            transition: transform 0.3s ease;
        }

        .sidebar .logo {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 20px;
        }

        .sidebar .logo h2 {
            font-size: 18px;
            font-weight: 700;
        }

        .sidebar .logo h2 span {
            color: #f1c40f;
        }

        .sidebar .logo small {
            font-size: 11px;
            opacity: 0.6;
            display: block;
            margin-top: 2px;
        }

        .sidebar .menu {
            flex: 1;
            padding: 0 15px;
        }

        .sidebar .menu a {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            border-radius: 8px;
            transition: 0.3s;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .sidebar .menu a:hover {
            background: rgba(255,255,255,0.08);
            color: white;
        }

        .sidebar .menu a.active {
            background: rgba(39, 174, 96, 0.3);
            color: white;
        }

        .sidebar .user-info-sidebar {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar .user-info-sidebar .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            color: white;
        }

        .sidebar .user-info-sidebar .user-name {
            font-size: 13px;
            font-weight: 500;
        }

        .sidebar .user-info-sidebar .user-name small {
            display: block;
            font-size: 11px;
            opacity: 0.5;
            font-weight: 400;
        }

        .main-content {
            margin-left: 220px;
            flex: 1;
            padding: 25px 30px;
        }

        .header-admin {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #e8f5e9;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-admin h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1a3a2a;
        }

        .header-admin h1 span {
            color: #1a7a3a;
        }

        .header-admin .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-admin .header-actions .btn-logout {
            background: #e74c3c;
            color: white;
            padding: 8px 18px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.3s;
        }

        .header-admin .header-actions .btn-logout:hover {
            background: #c0392b;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 22px 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(39, 174, 96, 0.06);
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #e8f5e9;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .card-header h3 {
            font-size: 17px;
            color: #1a3a2a;
            font-weight: 700;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d5f5e3;
            color: #1a7a3a;
            border-color: #27ae60;
        }

        .alert-danger {
            background: #fde8e8;
            color: #922b21;
            border-color: #e74c3c;
        }

        .btn {
            display: inline-block;
            padding: 12px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-generar {
            background: #27ae60;
            color: white;
        }
        .btn-generar:hover {
            background: #1e8449;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.2);
        }

        .btn-volver {
            background: #95a5a6;
            color: white;
            padding: 12px 30px;
        }
        .btn-volver:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .info-box {
            background: #f8fbf8;
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid #e8f5e9;
            margin-top: 15px;
        }

        .info-box .item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eef5ee;
        }

        .info-box .item:last-child {
            border-bottom: none;
        }

        .info-box .label {
            color: #5a7a6a;
        }

        .info-box .value {
            font-weight: 600;
        }

        .hamburger {
            display: none;
            flex-direction: column;
            gap: 4px;
            cursor: pointer;
            padding: 5px;
            background: none;
            border: none;
        }

        .hamburger span {
            width: 25px;
            height: 3px;
            background: #1a3a2a;
            border-radius: 3px;
            transition: 0.3s;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 90;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .overlay.active {
                display: block;
            }

            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .hamburger {
                display: flex;
            }

            .header-admin h1 {
                font-size: 18px;
            }
        }

        @media (max-width: 576px) {
            .card {
                padding: 14px 14px;
            }

            .card-header h3 {
                font-size: 15px;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <h2>Condominio <span>Digital</span></h2>
            <small>Panel de Administrador</small>
        </div>

        <div class="menu">
            <a href="../../index.php">Dashboard</a>
            <a href="generar_facturas.php" class="active">Generar Facturas</a>
        </div>

        <div class="user-info-sidebar">
            <div class="avatar"><?= strtoupper(substr($_SESSION['admin_usuario'], 0, 1)) ?></div>
            <div class="user-name">
                <?= htmlspecialchars($_SESSION['admin_nombre'] ?? $_SESSION['admin_usuario']) ?>
                <small>Administrador</small>
            </div>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>

    <div class="main-content">
        <div class="header-admin">
            <div style="display:flex;align-items:center;gap:15px;">
                <button class="hamburger" id="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <h1>Generar <span>Facturas</span></h1>
            </div>
            <div class="header-actions">
                <a href="../../logout.php" class="btn-logout">Cerrar Sesion</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= $mensaje ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Generar Facturas del Mes</h3>
            </div>

            <div class="info-box">
                <div class="item">
                    <span class="label">Mes a generar</span>
                    <span class="value"><?= nombreMes($mes) ?> <?= $anio ?></span>
                </div>
                <div class="item">
                    <span class="label">Unidades activas</span>
                    <span class="value"><?= count($unidades) ?></span>
                </div>
                <div class="item">
                    <span class="label">Facturas existentes este mes</span>
                    <span class="value"><?= $facturas_existentes ?></span>
                </div>
            </div>

            <?php if ($facturas_existentes > 0): ?>
                <div style="margin-top:20px;padding:15px;background:#fef9e7;border-radius:8px;border-left:4px solid #f1c40f;">
                    <p style="color:#856404;">Ya existen facturas para el mes <?= nombreMes($mes) ?> de <?= $anio ?>. Si genera nuevamente, se crearán facturas duplicadas.</p>
                </div>
            <?php endif; ?>

            <form method="POST" style="margin-top:20px;">
                <div style="display:flex;gap:15px;flex-wrap:wrap;">
                    <button type="submit" name="generar" value="1" class="btn btn-generar">
                        <?= $facturas_existentes > 0 ? 'Generar de nuevo' : 'Generar Facturas' ?>
                    </button>
                    <a href="../../index.php" class="btn btn-volver">Volver al Dashboard</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const hamburger = document.getElementById('hamburger');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        hamburger.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }
        });
    </script>
</body>
</html>