<?php
/**
 * Carta Oficial de Deuda — notificación impresa por unidad.
 *
 * Replica el formato institucional de la notificación oficial: membrete con logo,
 * fecha a la derecha, destinatario, cuerpo con meses de mora y monto en letras,
 * tabla mensual agrupada, párrafo legal, medios de pago, firma de la administradora
 * y caja "Recibido Por".
 *
 * Variables entregadas por ReporteController::generarCartaDeuda():
 * $unidad, $facturas, $totalDeuda, $enlaceWhatsapp, $analisisTel, $ultimoEnvio,
 * $enviadoHoy, $title.
 */

// Normaliza la entrada para que la vista quede libre de warnings.
$facturas = $facturas ?? [];
$totalDeuda = $totalDeuda ?? 0;
$enviadoHoy = $enviadoHoy ?? false;
$ultimoEnvio = $ultimoEnvio ?? null;

// Agrupa las facturas por mes/año sumando saldos; conserva el orden cronológico de llegada.
$gruposFacturas = agruparFacturasPorMes($facturas);

$cantidadMeses = count($gruposFacturas);
$textoMesesVencidos = ($cantidadMeses === 1) ? '1 mes vencido' : $cantidadMeses . ' meses vencidos';
$montoEnLetrasTotal = montoEnLetras($totalDeuda);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'Carta Oficial de Deuda') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', Arial, sans-serif; color: #111; background: #f8fafc; }
        .carta-paper { background: #fff; max-width: 800px; margin: 30px auto; padding: 40px 48px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; font-size: 14px; line-height: 1.6; }

        /* Membrete institucional */
        .membrete { display: flex; align-items: center; gap: 18px; }
        .membrete-logo { height: 60px; width: auto; }
        .membrete-texto { flex: 1; text-align: center; }
        .membrete-texto p { margin: 0; }
        .membrete-titulo, .membrete-rif { font-weight: 700; }
        .linea-fina { border-top: 1px solid #000; margin-top: 14px; }
        .linea-gruesa { border-top: 3px solid #000; margin-top: 2px; }

        .fecha { text-align: right; margin: 24px 0 18px; }
        .destinatario p { margin: 0; }
        .cuerpo { text-align: justify; margin: 14px 0; }

        /* Tabla mensual de la deuda */
        .tabla-montos { width: 65%; margin: 18px auto; border-collapse: collapse; }
        .tabla-montos th, .tabla-montos td { border: 1px solid #000; padding: 5px 10px; }
        .tabla-montos th { text-align: center; font-weight: 700; }
        .tabla-montos .col-monto { text-align: right; }
        .tabla-montos .fila-total { font-weight: 700; }

        .medios-pago { list-style: none; padding-left: 0; margin: 10px 0 16px; }
        .medios-pago li { margin: 2px 0; }

        /* Pie: firma de la administradora y caja de recibo */
        .pie { display: flex; justify-content: space-between; align-items: center; gap: 30px; margin-top: 44px; }
        .firma p { margin: 0; }
        .tabla-recibido { width: 50%; border-collapse: collapse; }
        .tabla-recibido td { border: 1px solid #000; height: 30px; vertical-align: top; padding: 3px 6px; }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .carta-paper { box-shadow: none; border: none; border-radius: 0; padding: 0; margin: 0; max-width: 100%; }
        }
        /* Botones de barra superior alineados al estilo de Conciliación (R1/R2) */
        .no-print .btn { border-radius: .75rem; font-size: .75rem; line-height: 1rem; font-weight: 700; padding: .625rem 1.25rem; display: inline-flex; align-items: center; gap: .375rem; transition: all .15s ease; }
        .no-print .btn-primary { background-color: #27ae60; border-color: #27ae60; color: #fff; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
        .no-print .btn-primary:hover { background-color: #1e8449; border-color: #1e8449; color: #fff; }
        .no-print .btn-outline-secondary { background-color: #f1f5f9; border-color: #e2e8f0; color: #334155; }
        .no-print .btn-outline-secondary:hover { background-color: #e2e8f0; border-color: #e2e8f0; color: #334155; }
        .no-print .btn-warning { background-color: #fbbf24; border-color: #fbbf24; color: #451a03; }
        .no-print .btn-warning:hover { background-color: #f59e0b; border-color: #f59e0b; color: #451a03; }
        .no-print .btn-success { background-color: #16a34a; border-color: #16a34a; color: #fff; }
        .no-print .btn-success:hover { background-color: #15803d; border-color: #15803d; color: #fff; }
        .no-print .btn:disabled, .no-print .btn.disabled { background-color: #f1f5f9; border-color: #e2e8f0; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Barra de Acciones (No Imprimible) -->
        <div class="no-print d-flex justify-content-between align-items-center mb-4 max-w-800 mx-auto bg-white p-3 border rounded-3 shadow-sm" style="max-width: 800px;">
            <a href="/admin/reportes/morosidad" class="btn btn-outline-secondary btn-sm fw-bold">
                &larr; Volver al Reporte
            </a>
            <div class="d-flex gap-2 align-items-center">
                <?php if ($enviadoHoy): ?>
                    <?php $fechaEnvioHoy = !empty($ultimoEnvio['created_at']) ? date('d/m/Y H:i', strtotime($ultimoEnvio['created_at'])) : ''; ?>
                    <button type="button" class="btn btn-warning btn-sm fw-bold d-inline-flex align-items-center gap-1" disabled title="Ya se envió hoy. Podrá reenviarse a partir de mañana.">
                        <span class="material-symbols-outlined fs-6">mark_email_read</span> Carta enviada<?= $fechaEnvioHoy !== '' ? ' ' . e($fechaEnvioHoy) : '' ?>
                    </button>
                <?php else: ?>
                    <form method="POST" action="/admin/reportes/enviar-aviso-cobro" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="unidad_id" value="<?= e($unidad['unidad_id'] ?? '') ?>">
                        <button type="submit" class="btn btn-warning btn-sm fw-bold d-inline-flex align-items-center gap-1">
                            <span class="material-symbols-outlined fs-6">mail</span> Enviar por Email
                        </button>
                    </form>
                    <?php if ($ultimoEnvio): ?>
                        <span class="text-muted small">Último envío: <?= e(!empty($ultimoEnvio['created_at']) ? date('d/m/Y', strtotime($ultimoEnvio['created_at'])) : '') ?></span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($enlaceWhatsapp)): ?>
                    <a href="<?= e($enlaceWhatsapp) ?>" target="_blank" class="btn btn-success btn-sm fw-bold d-inline-flex align-items-center gap-1">
                        <span class="material-symbols-outlined fs-6">chat</span> WhatsApp (1-Clic)
                    </a>
                <?php else: ?>
                    <button class="btn btn-outline-secondary btn-sm fw-bold" disabled title="<?= e($analisisTel['mensaje'] ?? 'Número no móvil') ?>">
                        🚫 WhatsApp Incompatible
                    </button>
                <?php endif; ?>

                <button onclick="window.print()" class="btn btn-primary btn-sm fw-bold d-inline-flex align-items-center gap-1">
                    <span class="material-symbols-outlined fs-6">print</span> Imprimir / PDF
                </button>
            </div>
        </div>

        <?php include VIEWS_PATH . '/components/flash_messages.php'; ?>

        <!-- Documento Papel Carta -->
        <div class="carta-paper">
            <!-- Membrete -->
            <div class="membrete">
                <img src="/img/logo_condominio.png" alt="Logo" class="membrete-logo">
                <div class="membrete-texto">
                    <p class="membrete-titulo">CONDOMINIO CONJUNTO LAS MESETAS</p>
                    <p class="membrete-rif">RIF. J-400577870</p>
                    <p>Av. Ppal. de Morón. Oficina Administrativa</p>
                    <p>Urb. Las Mesetas. Teléf. 0271-2259170 - Valera, Edo. Trujillo</p>
                </div>
            </div>
            <div class="linea-fina"></div>
            <div class="linea-gruesa"></div>

            <!-- Fecha -->
            <div class="fecha">Valera, <?= date('j') ?> de <?= nombreMes(date('n')) ?> de <?= date('Y') ?></div>

            <!-- Destinatario -->
            <div class="destinatario">
                <p>Ciudadana (o):</p>
                <p><strong><?= e(mb_strtoupper(($unidad['propietario_nombre'] ?? '') ?: 'Propietario / Residente', 'UTF-8')) ?></strong></p>
                <p>Propietario(a) del Apto. <?= e($unidad['unidad_numero'] ?? '') ?>. Del Edificio "<?= e($unidad['edificio_nombre'] ?? '') ?>".</p>
                <p><strong>PRESENTE.-</strong></p>
            </div>

            <?php if (empty($gruposFacturas)): ?>
                <!-- Sin facturas vencidas -->
                <p class="cuerpo">Esta unidad no posee facturas vencidas registradas.</p>
            <?php else: ?>
                <!-- Cuerpo -->
                <p class="cuerpo">
                    Nos es grato dirigirnos a usted, para saludarle y a la vez notificarle que a la fecha, tiene una morosidad de <?= e($textoMesesVencidos) ?> de condominio, que corresponden a gastos comunes (pago de servicios básicos, vigilancia, mantenimiento de equipos y áreas comunes de nuestra urbanización y salario de trabajadores, pues han sido consumidos y disfrutados en su totalidad), por un monto de <?= e($montoEnLetrasTotal) ?> (<?= e(formatearMoneda($totalDeuda)) ?>). Monto total se detalla a continuación:
                </p>

                <!-- Tabla mensual -->
                <table class="tabla-montos">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Monto Bs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $esPrimeraFila = true; ?>
                        <?php foreach ($gruposFacturas as $grupo): ?>
                            <tr>
                                <td><?= e(($esPrimeraFila ? 'Saldo de ' : '') . nombreMes($grupo['mes']) . '-' . $grupo['anio']) ?></td>
                                <td class="col-monto"><?= e(formatearMonto($grupo['total'])) ?></td>
                            </tr>
                            <?php $esPrimeraFila = false; ?>
                        <?php endforeach; ?>
                        <tr class="fila-total">
                            <td>Total</td>
                            <td class="col-monto"><?= e(formatearMonto($totalDeuda)) ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Párrafo legal -->
            <p class="cuerpo">
                Nos gustaría que usted gentilmente cancele lo más pronto posible la totalidad de la deuda indicada en el párrafo anterior; de lo contrario, nos veremos en la necesidad de pasar a consultoría jurídica su deuda y/o a competencia de las Juezas y Jueces de Paz Comunal. Artículo 8, Literal 2 y el gasto de honorarios profesionales correrá por su cuenta.
            </p>

            <!-- Medios de pago -->
            <p>Los medios establecidos para su pago son los siguientes:</p>
            <ul class="medios-pago">
                <li>✓ Cuenta Corriente Banco de Venezuela Nº. 0102-0373-2200-0010-2432</li>
                <li>✓ Divisa (Tasa del BCV del día de cancelación)</li>
                <li>✓ Enviar su pago al correo electrónico: pagosjdclm@gmail.com</li>
            </ul>

            <p>Sírvase dar recibo al encargado de practicar esta Notificación.</p>

            <!-- Pie: firma y caja de recibo -->
            <div class="pie">
                <div class="firma">
                    <p>Ninoska Moncada</p>
                    <p>Administradora</p>
                </div>
                <table class="tabla-recibido">
                    <tbody>
                        <tr><td colspan="2">Recibido Por:</td></tr>
                        <tr><td colspan="2">Nombres y Apellidos:</td></tr>
                        <tr><td colspan="2">Cédula de Identidad Nº</td></tr>
                        <tr><td>Fecha:</td><td>Hora:</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
