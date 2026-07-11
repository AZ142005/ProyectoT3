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

// Obtener datos del pago
$pago = getRecord("
    SELECT p.*, f.numero_factura, u.numero as unidad, 
           CONCAT(pr.nombre, ' ', pr.apellido) as propietario,
           f.monto_total as factura_monto,
           f.saldo as saldo_actual
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

// Si ya fue aprobado o rechazado, redirigir
if ($pago['estado'] != 'pendiente') {
    header('Location: historial.php');
    exit;
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    if ($accion === 'aprobar') {
        // Iniciar transaccion
        $conn = getConnection();
        $conn->begin_transaction();
        
        try {
            // Actualizar pago
            $sql_pago = "UPDATE pagos SET 
                         estado = 'aprobado', 
                         observaciones_verificacion = ?, 
                         verificador_id = ?, 
                         fecha_verificacion = NOW() 
                         WHERE id = ?";
            $stmt = $conn->prepare($sql_pago);
            $verificador_id = $_SESSION['user_id'];
            $stmt->bind_param("sii", $observaciones, $verificador_id, $id);
            $stmt->execute();
            
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
                
                $sql_factura = "UPDATE facturas SET 
                                monto_pagado = ?, 
                                saldo = ?, 
                                estado = ? 
                                WHERE id = ?";
                $stmt = $conn->prepare($sql_factura);
                $stmt->bind_param("ddsi", $nuevo_pagado, $nuevo_saldo, $estado_factura, $pago['factura_id']);
                $stmt->execute();
            }
            
            $conn->commit();
            $mensaje = "Pago aprobado exitosamente";
            
            // Redirigir con mensaje
            header('Location: listar.php?mensaje=' . urlencode($mensaje));
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error al aprobar el pago: " . $e->getMessage();
        }
        $conn->close();
        
    } elseif ($accion === 'rechazar') {
        // Actualizar pago como rechazado
        $sql = "UPDATE pagos SET 
                estado = 'rechazado', 
                observaciones_verificacion = ?, 
                verificador_id = ?, 
                fecha_verificacion = NOW() 
                WHERE id = ?";
        $result = executeNonQuery($sql, [$observaciones, $_SESSION['user_id'], $id], "sii");
        
        if ($result !== false) {
            $mensaje = "Pago rechazado";
            header('Location: listar.php?mensaje=' . urlencode($mensaje));
            exit;
        } else {
            $error = "Error al rechazar el pago";
        }
    } else {
        $error = "Seleccione una accion valida";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprobar Pago - Sistema de Verificacion</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .pago-detalle {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .pago-detalle .item .label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
        }
        .pago-detalle .item .value {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        .pago-detalle .item .value.monto { color: #27ae60; }
        .pago-detalle .item .value.saldo { color: #e74c3c; }
        
        .acciones-box {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn-aprobar {
            background: #27ae60;
            color: white;
            padding: 12px 40px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-aprobar:hover { background: #219a52; }
        
        .btn-rechazar {
            background: #e74c3c;
            color: white;
            padding: 12px 40px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-rechazar:hover { background: #c0392b; }
        
        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid;
        }
        .alert-success { background: #d4edda; color: #155724; border-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-color: #dc3545; }
        
        .info-extra {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #7f8c8d;
        }
        
        @media (max-width: 600px) {
            .pago-detalle {
                grid-template-columns: 1fr;
            }
            .acciones-box {
                flex-direction: column;
            }
            .btn-aprobar, .btn-rechazar {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <h2>Sistema de Pagos</h2>
            <small>Verificacion y Aprobacion</small>
        </div>
        <nav>
            <ul>
                <li><a href="../../index.php">[D] Dashboard</a></li>
                <li><a href="listar.php" class="active">[P] Pagos Pendientes</a></li>
                <li><a href="historial.php">[H] Historial</a></li>
                <li><a href="../../logout.php">[S] Cerrar Sesion</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Contenido Principal -->
    <main class="main-content">
        <!-- Header -->
        <header class="top-header">
            <h1>Aprobar / Rechazar Pago</h1>
            <div class="user-info">
                <span><?= $_SESSION['usuario'] ?></span>
                <div class="avatar"><?= strtoupper(substr($_SESSION['usuario'], 0, 1)) ?></div>
            </div>
        </header>

        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= $mensaje ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- Detalle del Pago -->
        <div class="card">
            <div class="card-header">
                <h3>Detalle del Pago</h3>
                <a href="listar.php" class="btn btn-info btn-sm">Volver a la lista</a>
            </div>

            <div class="pago-detalle">
                <div class="item">
                    <div class="label">Factura</div>
                    <div class="value"><?= $pago['numero_factura'] ?></div>
                </div>
                <div class="item">
                    <div class="label">Unidad</div>
                    <div class="value"><?= $pago['unidad'] ?></div>
                </div>
                <div class="item">
                    <div class="label">Propietario</div>
                    <div class="value"><?= $pago['propietario'] ?? 'Sin propietario' ?></div>
                </div>
                <div class="item">
                    <div class="label">Metodo de Pago</div>
                    <div class="value"><?= ucfirst(str_replace('_', ' ', $pago['metodo_pago'])) ?></div>
                </div>
                <div class="item">
                    <div class="label">Referencia</div>
                    <div class="value"><?= $pago['referencia'] ?? '-' ?></div>
                </div>
                <div class="item">
                    <div class="label">Fecha de Pago</div>
                    <div class="value"><?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?></div>
                </div>
                <div class="item">
                    <div class="label">Monto Pagado</div>
                    <div class="value monto"><?= formatearMoneda($pago['monto']) ?></div>
                </div>
                <div class="item">
                    <div class="label">Saldo Pendiente</div>
                    <div class="value saldo"><?= formatearMoneda($pago['saldo_actual'] ?? 0) ?></div>
                </div>
            </div>

            <?php if ($pago['observaciones']): ?>
                <div class="info-extra">
                    <strong>Observaciones del pago:</strong><br>
                    <?= nl2br(htmlspecialchars($pago['observaciones'])) ?>
                </div>
            <?php endif; ?>

            <!-- Formulario de Aprobacion -->
            <form method="POST" action="" id="form-aprobar">
                <div class="form-group">
                    <label>Observaciones de Verificacion</label>
                    <textarea name="observaciones" class="form-control" rows="3" 
                              placeholder="Ingrese observaciones sobre la verificacion del pago..."></textarea>
                </div>

                <div class="acciones-box">
                    <button type="submit" name="accion" value="aprobar" class="btn-aprobar" 
                            onclick="return confirm('¿Esta seguro de aprobar este pago?')">
                        Aprobar Pago
                    </button>
                    <button type="submit" name="accion" value="rechazar" class="btn-rechazar"
                            onclick="return confirm('¿Esta seguro de rechazar este pago?')">
                        Rechazar Pago
                    </button>
                    <a href="listar.php" class="btn btn-secondary" style="padding:12px 30px;">Cancelar</a>
                </div>
            </form>
        </div>

        <!-- Informacion Adicional -->
        <div class="card" style="background:#f8f9fa;">
            <div style="font-size:13px;color:#7f8c8d;">
                <strong>Nota:</strong> Al aprobar un pago, se actualizara automaticamente el saldo de la factura.
                Si el saldo queda en cero, la factura se marcara como "aprobada".
            </div>
        </div>
    </main>

    <script src="../../assets/js/main.js"></script>
</body>
</html>