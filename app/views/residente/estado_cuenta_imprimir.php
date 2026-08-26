<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'Estado de Cuenta Oficial') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; color: #1e293b; background: #fff; }
        .paper { max-width: 850px; margin: 20px auto; padding: 20px; }
        @media print {
            .no-print { display: none !important; }
            .paper { padding: 0; margin: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="paper">
        <!-- Barra superior no imprimible -->
        <div class="no-print d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <button onclick="window.history.back()" class="btn btn-outline-secondary btn-sm fw-bold">
                &larr; Volver
            </button>
            <button onclick="window.print()" class="btn btn-primary btn-sm fw-bold">
                🖨️ Imprimir / Guardar en PDF
            </button>
        </div>

        <!-- Membrete -->
        <div class="row border-bottom pb-4 mb-4 align-items-center">
            <div class="col-8">
                <h4 class="fw-bold text-success mb-1">CONJUNTO RESIDENCIAL "LAS MESETAS DE MORÓN"</h4>
                <p class="small text-muted mb-0">Administración General & Junta de Condominio</p>
                <p class="small text-muted mb-0">RIF: J-30948572-0 | Morón, Estado Carabobo</p>
            </div>
            <div class="col-4 text-end">
                <div class="border p-2 rounded bg-light text-center">
                    <small class="text-uppercase text-muted fw-bold d-block">ESTADO DE CUENTA</small>
                    <span class="font-monospace fw-bold">APTO-<?= e($unidad['numero']) ?></span>
                </div>
            </div>
        </div>

        <!-- Datos del Inmueble y Propietario -->
        <div class="row mb-4">
            <div class="col-6">
                <p class="mb-1"><strong>Fecha de Emisión:</strong> <?= date('d/m/Y H:i') ?></p>
                <p class="mb-1"><strong>Propietario:</strong> <?= e($unidad['propietario_nombre'] ?: 'Propietario') ?></p>
                <p class="mb-0"><strong>Cédula:</strong> <?= e($unidad['propietario_cedula'] ?: 'N/A') ?></p>
            </div>
            <div class="col-6 text-end">
                <p class="mb-1"><strong>Edificio / Torre:</strong> <?= e($unidad['edificio_nombre'] ?: 'Sin Torre') ?></p>
                <p class="mb-1"><strong>Apto / Unidad:</strong> <?= e($unidad['numero']) ?></p>
                <p class="mb-0"><strong>Saldo Consolidado:</strong> <span class="font-monospace fw-bold <?= $saldoActual > 0 ? 'text-danger' : 'text-success' ?>">Bs. <?= number_format($saldoActual, 2) ?></span></p>
            </div>
        </div>

        <!-- Tabla del Libro Mayor -->
        <h6 class="fw-bold text-dark mb-3 text-uppercase border-bottom pb-2">Detalle de Movimientos Contables</h6>
        <table class="table table-bordered table-sm align-middle text-sm mb-4">
            <thead class="table-light text-uppercase font-monospace" style="font-size: 11px;">
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th class="text-end">Monto</th>
                    <th class="text-end">Saldo Ant.</th>
                    <th class="text-end">Saldo Post.</th>
                </tr>
            </thead>
            <tbody class="font-monospace" style="font-size: 11px;">
                <?php if (empty($movimientos)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-3 text-muted">No existen movimientos contables registrados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movimientos as $m): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($m['fecha_movimiento'])) ?></td>
                            <td><?= strtoupper($m['tipo']) ?></td>
                            <td class="font-sans"><?= e($m['descripcion']) ?></td>
                            <td class="text-end fw-bold"><?= $m['tipo'] === 'abono_pago' ? '-' : '+' ?>Bs. <?= number_format($m['monto'], 2) ?></td>
                            <td class="text-end text-muted">Bs. <?= number_format($m['saldo_anterior'], 2) ?></td>
                            <td class="text-end fw-bold">Bs. <?= number_format($m['saldo_posterior'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <td colspan="5" class="text-end fw-bold">SALDO FINAL CONSOLIDADO:</td>
                    <td class="text-end font-monospace fw-bold <?= $saldoActual > 0 ? 'text-danger' : 'text-success' ?>">Bs. <?= number_format($saldoActual, 2) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Firma -->
        <div class="row text-center mt-5 pt-4">
            <div class="col-6 offset-3 border-top pt-2">
                <small class="fw-bold text-dark d-block">ADMINISTRACIÓN GENERAL</small>
                <small class="text-muted">Las Mesetas de Morón</small>
            </div>
        </div>
    </div>
</body>
</html>
