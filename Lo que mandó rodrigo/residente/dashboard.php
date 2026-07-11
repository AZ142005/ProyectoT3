<?php
session_start();

if (!isset($_SESSION['residente_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../admin/config/database.php';

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

function diasHastaVencimiento($fechaVencimiento) {
    if (empty($fechaVencimiento)) return null;
    
    $hoy = new DateTime();
    $hoy->setTime(0, 0, 0);
    
    $vencimiento = new DateTime($fechaVencimiento);
    $vencimiento->setTime(0, 0, 0);
    
    $diff = $hoy->diff($vencimiento);
    return intval($diff->format('%r%a'));
}

$residente_id = $_SESSION['residente_id'];

$residente = getRecord(
    "SELECT p.*, u.numero as unidad_numero, u.torre 
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

$facturas_pendientes = getRecords("
    SELECT f.*, 
           (SELECT COUNT(*) FROM comprobantes_pago WHERE factura_id = f.id AND estado = 'pendiente') as tiene_pendiente
    FROM facturas f
    WHERE f.unidad_id = ? AND f.saldo > 0
    ORDER BY f.fecha_vencimiento ASC
", [$unidad_id], "i");

$comprobantes = getRecords("
    SELECT c.*, f.numero_factura 
    FROM comprobantes_pago c
    INNER JOIN facturas f ON c.factura_id = f.id
    WHERE c.residente_id = ?
    ORDER BY c.fecha_envio DESC
    LIMIT 10
", [$residente_id], "i");

$total_deuda = getRecord(
    "SELECT SUM(saldo) as total FROM facturas WHERE unidad_id = ? AND saldo > 0",
    [$unidad_id],
    "i"
)['total'] ?? 0;

$saldo_a_favor = getRecord(
    "SELECT SUM(saldo) as total FROM facturas WHERE unidad_id = ? AND saldo < 0",
    [$unidad_id],
    "i"
)['total'] ?? 0;

$saldo_a_favor_mostrar = abs($saldo_a_favor);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de Cuenta - Residente</title>
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
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .deuda-destacada {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid rgba(39, 174, 96, 0.06);
            border-left: 5px solid #e74c3c;
        }

        .deuda-destacada .label {
            font-size: 15px;
            color: #5a7a6a;
            font-weight: 500;
        }

        .deuda-destacada .monto {
            font-size: 30px;
            font-weight: 800;
            color: #e74c3c;
        }

        .deuda-destacada .monto.cero {
            color: #27ae60;
        }

        .saldo-favor {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(39, 174, 96, 0.06);
            border-left: 5px solid #27ae60;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .saldo-favor .label {
            font-size: 15px;
            color: #5a7a6a;
            font-weight: 500;
        }

        .saldo-favor .monto {
            font-size: 30px;
            font-weight: 800;
            color: #27ae60;
        }

        .seccion {
            background: white;
            border-radius: 12px;
            padding: 22px 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 25px;
            border: 1px solid rgba(39, 174, 96, 0.06);
        }

        .seccion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid #e8f5e9;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .seccion-header h3 {
            font-size: 17px;
            color: #1a3a2a;
            font-weight: 700;
        }

        .seccion-header .contador {
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
            font-size: 14px;
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
        .badge-verificado { background: #d6eaf8; color: #1a5276; }
        .badge-aprobado { background: #d5f5e3; color: #1a7a3a; }
        .badge-rechazado { background: #fde8e8; color: #922b21; }
        .badge-vencida { background: #fde8e8; color: #922b21; }

        .btn-enviar {
            background: #27ae60;
            color: white;
            padding: 5px 14px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: 0.3s;
        }

        .btn-enviar:hover { 
            background: #1e8449; 
            transform: translateY(-2px);
        }

        .btn-ver {
            background: #3498db;
            color: white;
            padding: 5px 14px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: 0.3s;
        }

        .btn-ver:hover { 
            background: #2e86c1; 
            transform: translateY(-2px);
        }

        .texto-moroso { color: #e74c3c; font-weight: 600; }

        .vacio {
            text-align: center;
            padding: 25px;
            color: #5a7a6a;
        }

        .vacio p {
            font-size: 16px;
            font-weight: 600;
            color: #27ae60;
        }

        .vacio .sub {
            font-weight: 400;
            font-size: 14px;
            color: #5a7a6a;
            margin-top: 2px;
        }

        .comprobante-thumb {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #e8f5e9;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fbf8;
        }

        .comprobante-thumb:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .comprobante-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .comprobante-thumb .sin-img {
            font-size: 11px;
            color: #95a5a6;
            text-align: center;
            padding: 4px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            align-items: center;
            justify-content: center;
            padding: 30px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }

        .modal-close {
            position: fixed;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            font-weight: 300;
            cursor: pointer;
            transition: 0.3s;
            background: none;
            border: none;
            z-index: 1000;
        }

        .modal-close:hover {
            transform: rotate(90deg);
        }

        @media (max-width: 768px) {
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

            .deuda-destacada {
                padding: 18px 20px;
                flex-direction: column;
                text-align: center;
            }

            .deuda-destacada .monto {
                font-size: 26px;
            }

            .saldo-favor {
                padding: 18px 20px;
                flex-direction: column;
                text-align: center;
            }

            .saldo-favor .monto {
                font-size: 26px;
            }

            .seccion {
                padding: 16px 16px;
            }

            .comprobante-thumb {
                width: 45px;
                height: 45px;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            table td, table th {
                padding: 5px 8px;
            }

            .header-residente .info h2 {
                font-size: 19px;
            }

            .header-residente .info small {
                font-size: 12px;
            }

            .header-residente .acciones a {
                font-size: 12px;
                padding: 5px 12px;
            }

            .seccion {
                padding: 14px 12px;
            }

            .seccion-header h3 {
                font-size: 15px;
            }

            .deuda-destacada .monto {
                font-size: 22px;
            }

            .saldo-favor .monto {
                font-size: 22px;
            }

            .btn-enviar, .btn-ver {
                font-size: 11px;
                padding: 4px 10px;
            }

            .comprobante-thumb {
                width: 35px;
                height: 35px;
            }

            .comprobante-thumb .sin-img {
                font-size: 9px;
            }
        }
    </style>
</head>
<body>
    <div class="header-residente">
        <div class="info">
            <h2><?= htmlspecialchars($residente['nombre'] . ' ' . $residente['apellido']) ?></h2>
            <small>
                Unidad: <?= htmlspecialchars($residente['unidad_numero'] ?? 'N/A') ?> - Torre <?= htmlspecialchars($residente['torre'] ?? 'N/A') ?> | 
                Cedula: <?= htmlspecialchars($residente['cedula'] ?? 'N/A') ?>
            </small>
        </div>
        <div class="acciones">
            <a href="enviar_pago.php">Enviar Comprobante</a>
            <a href="historial.php">Historial</a>
            <a href="logout.php">Salir</a>
        </div>
    </div>

    <div class="main-content">
        <div class="stats-grid">
            <div class="deuda-destacada">
                <span class="label">Deuda Total</span>
                <span class="monto <?= $total_deuda == 0 ? 'cero' : '' ?>">
                    <?= formatearMoneda($total_deuda) ?>
                </span>
            </div>

            <div class="saldo-favor">
                <span class="label">Saldo a Favor</span>
                <span class="monto">
                    <?= formatearMoneda($saldo_a_favor_mostrar) ?>
                </span>
            </div>
        </div>

        <div class="seccion">
            <div class="seccion-header">
                <h3>Facturas Pendientes</h3>
                <span class="contador"><?= count($facturas_pendientes) ?></span>
            </div>

            <?php if (empty($facturas_pendientes)): ?>
                <div class="vacio">
                    <p>No tiene facturas pendientes</p>
                    <div class="sub">Su estado de cuenta esta al dia</div>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Periodo</th>
                                <th>Monto</th>
                                <th>Saldo</th>
                                <th>Vencimiento</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($facturas_pendientes as $f): 
                                $dias = diasHastaVencimiento($f['fecha_vencimiento']);
                                $moroso = $dias !== null && $dias < 0;
                            ?>
                            <tr>
                                <td><?= nombreMes($f['mes']) ?> <?= $f['anio'] ?></td>
                                <td><?= formatearMoneda($f['monto_total']) ?></td>
                                <td><strong style="color:<?= $moroso ? '#e74c3c' : '#f39c12' ?>;"><?= formatearMoneda($f['saldo']) ?></strong></td>
                                <td>
                                    <?= date('d/m/Y', strtotime($f['fecha_vencimiento'])) ?>
                                    <?php if ($moroso): ?>
                                        <br><span class="texto-moroso">Vencida hace <?= abs($dias) ?>d</span>
                                    <?php elseif ($dias !== null && $dias <= 5 && $dias >= 0): ?>
                                        <br><span style="color:#f39c12;font-size:11px;">Vence en <?= $dias ?>d</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($moroso): ?>
                                        <span class="badge badge-vencida">Vencida</span>
                                    <?php elseif ($f['tiene_pendiente'] > 0): ?>
                                        <span class="badge badge-pendiente">Enviado</span>
                                    <?php else: ?>
                                        <span class="badge badge-pendiente">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$f['tiene_pendiente']): ?>
                                        <a href="enviar_pago.php?factura=<?= $f['id'] ?>" class="btn-enviar">Pagar</a>
                                    <?php else: ?>
                                        <span style="color:#5a7a6a;font-size:11px;">Esperando</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="seccion">
            <div class="seccion-header">
                <h3>Comprobantes Recientes</h3>
                <a href="historial.php" class="btn-ver">Ver todos</a>
            </div>

            <?php if (empty($comprobantes)): ?>
                <div class="vacio">
                    <p style="color:#5a7a6a;">No ha enviado comprobantes</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Monto</th>
                                <th>Metodo</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Comprobante</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comprobantes as $c): ?>
                            <tr>
                                <td><?= formatearMoneda($c['monto']) ?></td>
                                <td><?= ucfirst(str_replace('_', ' ', $c['metodo_pago'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_pago'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $c['estado'] ?>">
                                        <?= ucfirst($c['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($c['archivo']): ?>
                                        <div class="comprobante-thumb" onclick="abrirModal('../uploads/comprobantes/<?= htmlspecialchars($c['archivo']) ?>')">
                                            <img src="../uploads/comprobantes/<?= htmlspecialchars($c['archivo']) ?>" alt="Comprobante">
                                        </div>
                                    <?php else: ?>
                                        <div class="comprobante-thumb">
                                            <span class="sin-img">Sin<br>imagen</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal" id="modalComprobante" onclick="cerrarModal(event)">
        <button class="modal-close" onclick="cerrarModalForce()">X</button>
        <img class="modal-content" id="modalImg" src="">
    </div>

    <script>
        function abrirModal(src) {
            document.getElementById('modalImg').src = src;
            document.getElementById('modalComprobante').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal(event) {
            if (event.target === event.currentTarget) {
                document.getElementById('modalComprobante').classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        function cerrarModalForce() {
            document.getElementById('modalComprobante').classList.remove('active');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalForce();
            }
        });
    </script>
</body>
</html>