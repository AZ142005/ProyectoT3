<?php
session_start();

if (!isset($_SESSION['residente_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../admin/config/database.php';

$residente_id = $_SESSION['residente_id'];

$residente = getRecord(
    "SELECT p.*, u.numero as unidad_numero 
     FROM personas p
     INNER JOIN unidades u ON p.unidad_id = u.id 
     WHERE p.id = ?",
    [$residente_id],
    "i"
);

if (!$residente) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$unidad_id = $residente['unidad_id'];
$unidad_numero = $residente['unidad_numero'];
$residente_nombre = $residente['nombre'] . ' ' . $residente['apellido'];

$factura_id = $_GET['factura'] ?? 0;
$mensaje = '';
$error = '';

function formatearMoneda($valor) {
    if (is_null($valor) || $valor === '') {
        return 'Bs. 0,00';
    }
    return 'Bs. ' . number_format(floatval($valor), 2, ',', '.');
}

function nombreMes($numero) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[intval($numero)] ?? '';
}

$factura = null;
if ($factura_id > 0) {
    $factura = getRecord(
        "SELECT * FROM facturas WHERE id = ? AND unidad_id = ? AND saldo > 0",
        [$factura_id, $unidad_id],
        "ii"
    );
}

$facturas_pendientes = getRecords(
    "SELECT * FROM facturas WHERE unidad_id = ? AND saldo > 0 ORDER BY fecha_vencimiento ASC",
    [$unidad_id],
    "i"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $factura_id = $_POST['factura_id'] ?? 0;
    $monto = floatval($_POST['monto'] ?? 0);
    $metodo_pago = $_POST['metodo_pago'] ?? '';
    $referencia = trim($_POST['referencia'] ?? '');
    $fecha_pago = $_POST['fecha_pago'] ?? date('Y-m-d');
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    if ($factura_id <= 0) {
        $error = "Seleccione una factura";
    } elseif ($monto <= 0) {
        $error = "Ingrese un monto valido";
    } elseif (empty($metodo_pago)) {
        $error = "Seleccione un metodo de pago";
    } else {
        $factura = getRecord(
            "SELECT * FROM facturas WHERE id = ? AND unidad_id = ?",
            [$factura_id, $unidad_id],
            "ii"
        );
        
        if (!$factura) {
            $error = "Factura no valida";
        } else {
            $archivo = '';
            if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
                $extension = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
                $archivo = 'comp_' . date('Ymd_His') . '_' . $residente_id . '.' . $extension;
                $ruta = '../uploads/comprobantes/' . $archivo;
                
                if (!is_dir('../uploads/comprobantes')) {
                    mkdir('../uploads/comprobantes', 0777, true);
                }
                
                if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $ruta)) {
                    $error = "Error al guardar el comprobante";
                }
            }
            
            if (empty($error)) {
                $sql = "INSERT INTO comprobantes_pago 
                        (residente_id, factura_id, monto, metodo_pago, referencia, fecha_pago, archivo, observaciones) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $result = executeNonQuery(
                    $sql,
                    [$residente_id, $factura_id, $monto, $metodo_pago, $referencia, $fecha_pago, $archivo, $observaciones],
                    "iidsssss"
                );
                
                if ($result) {
                    $mensaje = "Comprobante enviado exitosamente. Su pago sera verificado por la administracion.";
                    $factura_id = 0;
                } else {
                    $error = "Error al enviar el comprobante";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Comprobante de Pago</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f7f0;
            color: #1a3a2a;
        }

        .header-residente {
            background: linear-gradient(135deg, #1a7a3a, #27ae60);
            color: white;
            padding: 20px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .header-residente .info h2 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 3px;
        }
        .header-residente .info small {
            opacity: 0.8;
            font-size: 13px;
        }
        
        .header-residente .acciones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .header-residente .acciones a {
            color: white;
            text-decoration: none;
            padding: 8px 20px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            transition: 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        .header-residente .acciones a:hover {
            background: rgba(255,255,255,0.12);
            border-color: rgba(255,255,255,0.4);
            transform: translateY(-2px);
        }

        .main-content {
            max-width: 700px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 28px 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(39, 174, 96, 0.06);
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #e8f5e9;
            margin-bottom: 20px;
        }

        .card-header h3 {
            font-size: 18px;
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

        .form-group label .required {
            color: #e74c3c;
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

        select.form-control {
            appearance: auto;
            cursor: pointer;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .help-text {
            font-size: 12px;
            color: #5a7a6a;
            margin-top: 4px;
        }

        .archivo-area {
            background: #f8fbf8;
            padding: 20px;
            border-radius: 8px;
            border: 2px dashed #d5f5e3;
            text-align: center;
            transition: 0.3s;
        }

        .archivo-area:hover {
            border-color: #27ae60;
            background: #f0f7f0;
        }

        .archivo-area input[type="file"] {
            display: block;
            margin: 10px auto;
            font-size: 13px;
        }

        .archivo-area .formato {
            font-size: 12px;
            color: #5a7a6a;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn-enviar {
            background: #27ae60;
            color: white;
            padding: 12px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-enviar:hover {
            background: #1e8449;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.2);
        }

        .btn-cancelar {
            background: #95a5a6;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: 0.3s;
        }

        .btn-cancelar:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .btn-volver {
            display: inline-block;
            padding: 8px 20px;
            background: #3498db;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-volver:hover {
            background: #2e86c1;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: #1a3a2a;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #5a7a6a;
            font-size: 15px;
        }

        @media (max-width: 600px) {
            .header-residente {
                padding: 15px 20px;
                flex-direction: column;
                text-align: center;
            }

            .header-residente .acciones {
                justify-content: center;
            }

            .header-residente .acciones a {
                font-size: 13px;
                padding: 6px 14px;
            }

            .main-content {
                padding: 20px 15px;
            }

            .card {
                padding: 20px 18px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn-enviar, .btn-cancelar {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header-residente">
        <div class="info">
            <h2>Enviar Comprobante de Pago</h2>
            <small><?= htmlspecialchars($residente_nombre) ?> - Unidad <?= htmlspecialchars($unidad_numero) ?></small>
        </div>
        <div class="acciones">
            <a href="dashboard.php">Volver al Dashboard</a>
        </div>
    </div>

    <div class="main-content">
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= $mensaje ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <?php if (!empty($facturas_pendientes)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Datos del Pago</h3>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Factura a Pagar <span class="required">*</span></label>
                        <select name="factura_id" class="form-control" required>
                            <option value="">Seleccione una factura...</option>
                            <?php foreach ($facturas_pendientes as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= ($factura_id == $f['id']) ? 'selected' : '' ?>>
                                    <?= $f['numero_factura'] ?> - <?= nombreMes($f['mes']) ?> <?= $f['anio'] ?> 
                                    (Saldo: <?= formatearMoneda($f['saldo']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Seleccione la factura que desea pagar</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Monto a Pagar <span class="required">*</span></label>
                            <input type="number" name="monto" class="form-control" 
                                   step="0.01" min="0.01" required
                                   placeholder="0.00">
                            <div class="help-text">Ingrese el monto que esta pagando</div>
                        </div>
                        <div class="form-group">
                            <label>Fecha de Pago <span class="required">*</span></label>
                            <input type="date" name="fecha_pago" class="form-control" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Metodo de Pago <span class="required">*</span></label>
                        <select name="metodo_pago" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <option value="transferencia">Transferencia Bancaria</option>
                            <option value="pago_movil">Pago Movil</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Referencia</label>
                        <input type="text" name="referencia" class="form-control" 
                               placeholder="Numero de referencia o comprobante">
                        <div class="help-text">Numero de operacion, referencia bancaria, etc.</div>
                    </div>

                    <div class="form-group">
                        <label>Comprobante de Pago</label>
                        <div class="archivo-area">
                            <input type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf">
                            <div class="formato">Formatos permitidos: JPG, PNG, PDF (Max 5MB)</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2" 
                                  placeholder="Informacion adicional sobre el pago..."></textarea>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-enviar">Enviar Comprobante</button>
                        <a href="dashboard.php" class="btn-cancelar">Cancelar</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <h3>No tiene facturas pendientes</h3>
                    <p>Su estado de cuenta esta al dia. No necesita realizar pagos en este momento.</p>
                    <br>
                    <a href="dashboard.php" class="btn-volver">Volver al Dashboard</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>