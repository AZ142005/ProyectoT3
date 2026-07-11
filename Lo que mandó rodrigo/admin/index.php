<?php
session_start();
if (!isset($_SESSION['admin_usuario'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';
require_once 'includes/funciones.php';

$comprobantes_pendientes = getRecords("
    SELECT 
        c.*,
        f.numero_factura,
        u.numero as unidad,
        CONCAT(p.nombre, ' ', p.apellido) as residente,
        p.cedula
    FROM comprobantes_pago c
    INNER JOIN facturas f ON c.factura_id = f.id
    INNER JOIN unidades u ON f.unidad_id = u.id
    INNER JOIN personas p ON c.residente_id = p.id
    WHERE c.estado = 'pendiente'
    ORDER BY c.fecha_envio DESC
    LIMIT 10
");

$ultimos_comprobantes = getRecords("
    SELECT 
        c.*,
        f.numero_factura,
        CONCAT(p.nombre, ' ', p.apellido) as residente
    FROM comprobantes_pago c
    INNER JOIN facturas f ON c.factura_id = f.id
    INNER JOIN personas p ON c.residente_id = p.id
    WHERE c.estado IN ('aprobado', 'rechazado')
    ORDER BY c.fecha_envio DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Administrador</title>
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

        .card-header .contador {
            background: #e8f5e9;
            color: #1a7a3a;
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        table th {
            background: #f8fbf8;
            padding: 9px 12px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: #5a7a6a;
            font-weight: 700;
            letter-spacing: 0.3px;
            border-bottom: 2px solid #e8f5e9;
        }

        table td {
            padding: 9px 12px;
            border-bottom: 1px solid #eef5ee;
            vertical-align: middle;
        }

        table tbody tr:hover {
            background: #f8fbf8;
        }

        .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-pendiente { background: #fef9e7; color: #856404; }
        .badge-aprobado { background: #d5f5e3; color: #1a7a3a; }
        .badge-rechazado { background: #fde8e8; color: #922b21; }

        .btn {
            display: inline-block;
            padding: 4px 14px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-info {
            background: #3498db;
            color: white;
        }
        .btn-info:hover {
            background: #2e86c1;
        }

        .btn-sm { padding: 4px 12px; font-size: 11px; }

        .comprobante-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
        }
        .comprobante-link:hover {
            text-decoration: underline;
            color: #2e86c1;
        }

        .text-muted { color: #95a5a6; }

        .empty-state {
            text-align: center;
            padding: 25px;
            color: #5a7a6a;
        }

        .empty-state p {
            font-size: 16px;
            font-weight: 600;
            color: #27ae60;
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
            .header-admin {
                flex-direction: column;
                align-items: flex-start;
            }

            .card {
                padding: 14px 14px;
            }

            table {
                font-size: 12px;
            }

            table td, table th {
                padding: 5px 8px;
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
            <a href="modules/pagos/listar.php">Pagos Pendientes</a>
            <a href="modules/pagos/listar_comprobantes.php">Todos los Comprobantes</a>
            <a href="modules/facturas/generar_facturas.php">Generar Facturas</a>
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
                <h1>Panel de <span>Control</span></h1>
            </div>
            <div class="header-actions">
                <a href="logout.php" class="btn-logout">Cerrar Sesion</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Comprobantes Pendientes de Verificar</h3>
                <a href="modules/pagos/listar_comprobantes.php" class="btn btn-info btn-sm">Ver todos</a>
            </div>

            <?php if (empty($comprobantes_pendientes)): ?>
                <div class="empty-state">
                    <p>No hay comprobantes pendientes</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Residente</th>
                                <th>Unidad</th>
                                <th>Factura</th>
                                <th>Monto</th>
                                <th>Comprobante</th>
                                <th>Fecha</th>
                                <th>Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comprobantes_pendientes as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['residente']) ?></strong></td>
                                <td><?= htmlspecialchars($c['unidad']) ?></td>
                                <td><?= htmlspecialchars($c['numero_factura']) ?></td>
                                <td><?= formatearMoneda($c['monto']) ?></td>
                                <td>
                                    <?php if ($c['archivo']): ?>
                                        <a href="../uploads/comprobantes/<?= htmlspecialchars($c['archivo']) ?>" target="_blank" class="comprobante-link">Ver</a>
                                    <?php else: ?>
                                        <span class="text-muted">Sin archivo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_envio'])) ?></td>
                                <td>
                                    <a href="modules/pagos/verificar_comprobante.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm">Verificar</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Ultimos Comprobantes Procesados</h3>
                <a href="modules/pagos/historial_comprobantes.php" class="btn btn-info btn-sm">Ver historial</a>
            </div>

            <?php if (empty($ultimos_comprobantes)): ?>
                <div class="empty-state">
                    <p style="color:#5a7a6a;">No hay comprobantes procesados</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Residente</th>
                                <th>Factura</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimos_comprobantes as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['residente']) ?></td>
                                <td><?= htmlspecialchars($c['numero_factura']) ?></td>
                                <td><?= formatearMoneda($c['monto']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $c['estado'] ?>">
                                        <?= ucfirst($c['estado']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_envio'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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