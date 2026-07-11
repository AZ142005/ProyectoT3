<?php
session_start();
if (!isset($_SESSION['admin_usuario'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/funciones.php';

$id = $_GET['id'] ?? 0;
$error = '';
$mensaje = '';

if ($id <= 0) {
    header('Location: listar_comprobantes.php');
    exit;
}

$comprobante = getRecord("
    SELECT 
        c.*,
        f.numero_factura,
        f.monto_total,
        f.saldo,
        f.unidad_id,
        CONCAT(p.nombre, ' ', p.apellido) as residente,
        p.cedula,
        u.numero as unidad
    FROM comprobantes_pago c
    INNER JOIN facturas f ON c.factura_id = f.id
    INNER JOIN personas p ON c.residente_id = p.id
    INNER JOIN unidades u ON f.unidad_id = u.id
    WHERE c.id = ?
", [$id], "i");

if (!$comprobante) {
    header('Location: listar_comprobantes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    if ($accion === 'aprobar') {
        $estado = 'aprobado';
        $mensaje = "Comprobante aprobado exitosamente.";
        
        $nuevo_saldo = $comprobante['saldo'] - $comprobante['monto'];
        
        executeNonQuery(
            "UPDATE facturas SET saldo = ?, monto_pagado = monto_pagado + ? WHERE id = ?",
            [$nuevo_saldo, $comprobante['monto'], $comprobante['factura_id']],
            "ddi"
        );
        
        if ($nuevo_saldo <= 0) {
            executeNonQuery(
                "UPDATE facturas SET estado = 'pagada' WHERE id = ?",
                [$comprobante['factura_id']],
                "i"
            );
        }
        
    } elseif ($accion === 'rechazar') {
        $estado = 'rechazado';
        $mensaje = "Comprobante rechazado.";
        
    } else {
        $error = "Accion no valida.";
    }
    
    if (empty($error)) {
        executeNonQuery(
            "UPDATE comprobantes_pago SET estado = ?, observaciones = ? WHERE id = ?",
            [$estado, $observaciones, $id],
            "ssi"
        );
        
        header('Location: listar_comprobantes.php?mensaje=' . urlencode($mensaje));
        exit;
    }
}

$saldo_restante = $comprobante['saldo'] - $comprobante['monto'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Comprobante</title>
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

        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #eef5ee;
        }

        .info-row .label {
            width: 160px;
            font-weight: 600;
            color: #5a7a6a;
        }

        .info-row .value {
            flex: 1;
            color: #1a3a2a;
        }

        .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-pendiente { background: #fef9e7; color: #856404; }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 10px 30px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-aprobar {
            background: #27ae60;
            color: white;
        }
        .btn-aprobar:hover {
            background: #1e8449;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.2);
        }

        .btn-rechazar {
            background: #e74c3c;
            color: white;
        }
        .btn-rechazar:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.2);
        }

        .btn-volver {
            background: #95a5a6;
            color: white;
        }
        .btn-volver:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .comprobante-img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 8px;
            border: 1px solid #e8f5e9;
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #1a3a2a;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e8f5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.3s;
            background: #fafffa;
            color: #1a252f;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-control:focus {
            border-color: #27ae60;
            outline: none;
            box-shadow: 0 0 0 4px rgba(39, 174, 96, 0.06);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-danger {
            background: #fde8e8;
            color: #922b21;
            border-color: #e74c3c;
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

            .info-row {
                flex-direction: column;
            }

            .info-row .label {
                width: 100%;
                margin-bottom: 2px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .card {
                padding: 14px 14px;
            }

            .card-header h3 {
                font-size: 15px;
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
            <a href="listar.php">Pagos Pendientes</a>
            <a href="listar_comprobantes.php" class="active">Todos los Comprobantes</a>
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
                <h1>Verificar <span>Comprobante</span></h1>
            </div>
            <div class="header-actions">
                <a href="../../logout.php" class="btn-logout">Cerrar Sesion</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Detalles del Comprobante</h3>
                <span class="badge badge-pendiente"><?= ucfirst($comprobante['estado']) ?></span>
            </div>

            <div class="info-row">
                <span class="label">Residente</span>
                <span class="value"><?= htmlspecialchars($comprobante['residente']) ?> (<?= htmlspecialchars($comprobante['cedula']) ?>)</span>
            </div>
            <div class="info-row">
                <span class="label">Unidad</span>
                <span class="value"><?= htmlspecialchars($comprobante['unidad']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Factura</span>
                <span class="value"><?= htmlspecialchars($comprobante['numero_factura']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Saldo Actual de la Factura</span>
                <span class="value"><strong><?= formatearMoneda($comprobante['saldo']) ?></strong></span>
            </div>
            <div class="info-row">
                <span class="label">Monto a Pagar</span>
                <span class="value"><strong><?= formatearMoneda($comprobante['monto']) ?></strong></span>
            </div>
            <div class="info-row" style="background:#f8fbf8;border-bottom:2px solid #27ae60;">
                <span class="label" style="font-weight:700;">Saldo Restante</span>
                <span class="value">
                    <strong style="color: <?= $saldo_restante < 0 ? '#27ae60' : ($saldo_restante > 0 ? '#e74c3c' : '#3498db') ?>; font-size:18px;">
                        <?= formatearMoneda($saldo_restante) ?>
                        <?php if ($saldo_restante < 0): ?>
                            <span style="font-size:14px;font-weight:400;color:#27ae60;"> (Saldo a favor de <?= formatearMoneda(abs($saldo_restante)) ?>)</span>
                        <?php endif; ?>
                    </strong>
                </span>
            </div>
            <div class="info-row">
                <span class="label">Metodo de Pago</span>
                <span class="value"><?= ucfirst(str_replace('_', ' ', $comprobante['metodo_pago'])) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Referencia</span>
                <span class="value"><?= htmlspecialchars($comprobante['referencia'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="label">Fecha de Pago</span>
                <span class="value"><?= date('d/m/Y', strtotime($comprobante['fecha_pago'])) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Fecha de Envio</span>
                <span class="value"><?= date('d/m/Y H:i', strtotime($comprobante['fecha_envio'])) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Comprobante</span>
                <span class="value">
                    <?php if ($comprobante['archivo']): ?>
                        <a href="../../../uploads/comprobantes/<?= htmlspecialchars($comprobante['archivo']) ?>" target="_blank" style="color:#3498db;text-decoration:none;font-weight:500;">Ver archivo</a>
                        <br>
                        <img src="../../../uploads/comprobantes/<?= htmlspecialchars($comprobante['archivo']) ?>" class="comprobante-img" alt="Comprobante">
                    <?php else: ?>
                        <span style="color:#95a5a6;">Sin archivo adjunto</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($comprobante['observaciones']): ?>
            <div class="info-row">
                <span class="label">Observaciones del Residente</span>
                <span class="value"><?= nl2br(htmlspecialchars($comprobante['observaciones'])) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($comprobante['estado'] == 'pendiente'): ?>
        <div class="card">
            <div class="card-header">
                <h3>Acciones de Verificacion</h3>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="observaciones">Observaciones (opcional)</label>
                    <textarea name="observaciones" id="observaciones" class="form-control" rows="3" placeholder="Agregue observaciones sobre la verificacion..."></textarea>
                </div>

                <div class="btn-group">
                    <button type="submit" name="accion" value="aprobar" class="btn btn-aprobar">Aprobar Pago</button>
                    <button type="submit" name="accion" value="rechazar" class="btn btn-rechazar">Rechazar Pago</button>
                    <a href="listar_comprobantes.php" class="btn btn-volver">Volver</a>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="card">
            <div style="text-align:center;padding:20px;">
                <p style="font-size:16px;color:#5a7a6a;">Este comprobante ya fue <strong><?= ucfirst($comprobante['estado']) ?></strong></p>
                <br>
                <a href="listar_comprobantes.php" class="btn btn-volver">Volver al listado</a>
            </div>
        </div>
        <?php endif; ?>
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