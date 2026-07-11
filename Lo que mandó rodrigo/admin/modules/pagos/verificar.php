<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../../login.php');
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/funciones.php';

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    header('Location: listar.php');
    exit;
}

$pago = getRecord("
    SELECT p.*, f.numero_factura, u.numero as unidad, 
           CONCAT(pr.nombre, ' ', pr.apellido) as propietario,
           f.monto_total as factura_monto
    FROM pagos p
    INNER JOIN facturas f ON p.factura_id = f.id
    INNER JOIN unidades u ON f.unidad_id = u.id
    LEFT JOIN propietarios pr ON u.propietario_id = pr.id
    WHERE p.id = ?
", [$id], "i");

if (!$pago) {
    header('Location: listar.php');
    exit;
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    if ($accion === 'aprobar') {
        $estado = 'aprobado';
        $mensaje = "Pago aprobado correctamente";
        
        // Actualizar factura
        $factura = getRecord("SELECT * FROM facturas WHERE id = ?", [$pago['factura_id']], "i");
        if ($factura) {
            $nuevo_pagado = $factura['monto_pagado'] + $pago['monto'];
            $nuevo_saldo = $factura['saldo'] - $pago['monto'];
            if ($nuevo_saldo <= 0) {
                $estado_factura = 'aprobado';
                $nuevo_saldo = 0;
            } else {
                $estado_factura = 'verificando';
            }
            executeNonQuery(
                "UPDATE facturas SET monto_pagado = ?, saldo = ?, estado = ? WHERE id = ?",
                [$nuevo_pagado, $nuevo_saldo, $estado_factura, $pago['factura_id']],
                "ddsi"
            );
        }
        
    } elseif ($accion === 'rechazar') {
        $estado = 'rechazado';
        $mensaje = "Pago rechazado";
    } else {
        $error = "Seleccione una accion valida";
    }
    
    if (empty($error)) {
        executeNonQuery(
            "UPDATE pagos SET estado = ?, observaciones_verificacion = ?, verificador_id = ?, fecha_verificacion = NOW() WHERE id = ?",
            [$estado, $observaciones, $_SESSION['user_id'], $id],
            "ssii"
        );
        
        header('Location: listar.php?mensaje=' . urlencode($mensaje));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificar Pago</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .info-pago {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-pago .row {
            display: flex;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .info-pago .row .label {
            width: 150px;
            font-weight: 600;
            color: #7f8c8d;
        }
        .info-pago .row .value {
            flex: 1;
        }
        .acciones-pago {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .btn-aprobar { background: #27ae60; color: white; padding: 10px 30px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-aprobar:hover { background: #219a52; }
        .btn-rechazar { background: #e74c3c; color: white; padding: 10px 30px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-rechazar:hover { background: #c0392b; }
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
            <h1>Verificar Pago</h1>
            <div class="user-info">
                <span><?= $_SESSION['usuario'] ?></span>
                <div class="avatar"><?= strtoupper(substr($_SESSION['usuario'], 0, 1)) ?></div>
            </div>
        </header>

        <?php if ($error): ?>
            <div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:4px;margin-bottom:15px;"><?= $error ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>Informacion del Pago</h3>
            <div class="info-pago">
                <div class="row"><span class="label">Factura:</span><span class="value"><?= $pago['numero_factura'] ?></span></div>
                <div class="row"><span class="label">Unidad:</span><span class="value"><?= $pago['unidad'] ?></span></div>
                <div class="row"><span class="label">Propietario:</span><span class="value"><?= $pago['propietario'] ?? 'Sin propietario' ?></span></div>
                <div class="row"><span class="label">Monto Pagado:</span><span class="value"><strong><?= formatearMoneda($pago['monto']) ?></strong></span></div>
                <div class="row"><span class="label">Metodo de Pago:</span><span class="value"><?= ucfirst(str_replace('_', ' ', $pago['metodo_pago'])) ?></span></div>
                <div class="row"><span class="label">Referencia:</span><span class="value"><?= $pago['referencia'] ?? '-' ?></span></div>
                <div class="row"><span class="label">Fecha de Pago:</span><span class="value"><?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?></span></div>
                <div class="row"><span class="label">Observaciones:</span><span class="value"><?= $pago['observaciones'] ?? '-' ?></span></div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Observaciones de Verificacion</label>
                    <textarea name="observaciones" class="form-control" rows="3" placeholder="Observaciones sobre la verificacion del pago..."></textarea>
                </div>

                <div class="acciones-pago">
                    <button type="submit" name="accion" value="aprobar" class="btn-aprobar">Aprobar Pago</button>
                    <button type="submit" name="accion" value="rechazar" class="btn-rechazar">Rechazar Pago</button>
                    <a href="listar.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>