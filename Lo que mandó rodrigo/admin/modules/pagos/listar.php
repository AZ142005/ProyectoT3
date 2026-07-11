<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../../login.php');
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/funciones.php';

$pagos = getRecords("
    SELECT p.*, f.numero_factura, u.numero as unidad, 
           CONCAT(pr.nombre, ' ', pr.apellido) as propietario
    FROM pagos p
    INNER JOIN facturas f ON p.factura_id = f.id
    INNER JOIN unidades u ON f.unidad_id = u.id
    LEFT JOIN propietarios pr ON u.propietario_id = pr.id
    WHERE p.estado = 'pendiente'
    ORDER BY p.fecha_registro DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos Pendientes</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .badge-pendiente { background: #fff3cd; color: #856404; }
        .acciones { display: flex; gap: 5px; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo"><h2>Sistema de Pagos</h2></div>
        <nav>
            <ul>
                <li><a href="../../index.php">[D] Dashboard</a></li>
                <li><a href="listar.php" class="active">[P] Pagos Pendientes</a></li>
                <li><a href="historial.php">[H] Historial</a></li>
                <li><a href="../../logout.php">[S] Cerrar Sesion</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <h1>Pagos Pendientes de Verificar</h1>
            <div class="user-info">
                <span><?= $_SESSION['usuario'] ?></span>
                <div class="avatar"><?= strtoupper(substr($_SESSION['usuario'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="card">
            <div class="card-header">
                <h3>Lista de Pagos</h3>
                <span>Total: <?= count($pagos) ?></span>
            </div>
            <?php if (empty($pagos)): ?>
                <p style="text-align:center;padding:30px;color:#7f8c8d;">No hay pagos pendientes</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Unidad</th>
                            <th>Propietario</th>
                            <th>Monto</th>
                            <th>Metodo</th>
                            <th>Referencia</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $p): ?>
                        <tr>
                            <td><?= $p['numero_factura'] ?></td>
                            <td><?= $p['unidad'] ?></td>
                            <td><?= $p['propietario'] ?? 'Sin propietario' ?></td>
                            <td><?= formatearMoneda($p['monto']) ?></td>
                            <td><?= ucfirst(str_replace('_', ' ', $p['metodo_pago'])) ?></td>
                            <td><?= $p['referencia'] ?? '-' ?></td>
                            <td><?= date('d/m/Y', strtotime($p['fecha_pago'])) ?></td>
                            <td>
                                <a href="verificar.php?id=<?= $p['id'] ?>" class="btn btn-info btn-xs">Verificar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>