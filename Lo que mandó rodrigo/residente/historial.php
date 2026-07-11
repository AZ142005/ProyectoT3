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

$unidad_numero = $residente['unidad_numero'];
$residente_nombre = $residente['nombre'] . ' ' . $residente['apellido'];

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

$comprobantes = getRecords("
    SELECT c.*, f.numero_factura, f.mes, f.anio
    FROM comprobantes_pago c
    INNER JOIN facturas f ON c.factura_id = f.id
    WHERE c.residente_id = ?
    ORDER BY c.fecha_envio DESC
", [$residente_id], "i");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Pagos - Residente</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
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

        .comprobante-thumb {
            width: 50px;
            height: 50px;
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
            transform: scale(1.08);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .comprobante-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .comprobante-thumb .sin-img {
            font-size: 10px;
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

        .text-muted { color: #95a5a6; }
        .text-center { text-align: center; }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #5a7a6a;
        }

        .empty-state p {
            font-size: 16px;
            font-weight: 600;
            color: #5a7a6a;
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

            .card {
                padding: 16px 18px;
            }

            .comprobante-thumb {
                width: 40px;
                height: 40px;
            }
        }

        @media (max-width: 576px) {
            table {
                font-size: 12px;
            }

            table td, table th {
                padding: 5px 8px;
            }

            .card {
                padding: 14px 14px;
            }

            .card-header h3 {
                font-size: 15px;
            }

            .comprobante-thumb {
                width: 32px;
                height: 32px;
            }

            .comprobante-thumb .sin-img {
                font-size: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="header-residente">
        <div class="info">
            <h2>Historial de Comprobantes</h2>
            <small><?= htmlspecialchars($residente_nombre) ?> - Unidad <?= htmlspecialchars($unidad_numero) ?></small>
        </div>
        <div class="acciones">
            <a href="dashboard.php">Volver al Dashboard</a>
        </div>
    </div>

    <div class="main-content">
        <div class="card">
            <div class="card-header">
                <h3>Todos los Comprobantes Enviados</h3>
                <span class="contador">Total: <?= count($comprobantes) ?></span>
            </div>

            <?php if (empty($comprobantes)): ?>
                <div class="empty-state">
                    <p>No ha enviado comprobantes de pago</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Periodo</th>
                                <th>Monto</th>
                                <th>Metodo</th>
                                <th>Referencia</th>
                                <th>Fecha Pago</th>
                                <th>Estado</th>
                                <th>Fecha Envio</th>
                                <th>Comprobante</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comprobantes as $c): ?>
                            <tr>
                                <td><?= nombreMes($c['mes']) ?> <?= $c['anio'] ?></td>
                                <td><strong><?= formatearMoneda($c['monto']) ?></strong></td>
                                <td><?= ucfirst(str_replace('_', ' ', $c['metodo_pago'])) ?></td>
                                <td><?= htmlspecialchars($c['referencia'] ?? '-') ?></td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_pago'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $c['estado'] ?>">
                                        <?= ucfirst($c['estado']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($c['fecha_envio'])) ?></td>
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