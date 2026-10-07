<?php
/**
 * Adaptador hacia la vista canónica unificada de detalle de pago.
 * Unifica la presentación visual de comprobantes de facturas con la pantalla maestra app/views/pagos/detalle.php.
 * Soporta descarga activa con download=1, botón "Descargar Comprobante", y discriminación de formato con isPDF.
 */

$saldo_restante = $saldo_restante ?? (($comprobante['saldo'] ?? 0) - ($comprobante['monto'] ?? 0));
$archivo = $comprobante['archivo'] ?? '';
$isPDF = !empty($archivo) && (strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) === 'pdf');

// Normalización al esquema canónico de pago
$pago = [
    'id'                   => $comprobante['id'] ?? 0,
    'monto'                => $comprobante['monto'] ?? 0,
    'estado'               => strtoupper($comprobante['estado'] ?? 'pendiente'),
    'residente_nombre'     => $comprobante['residente'] ?? 'Residente',
    'residente_cedula'     => $comprobante['cedula'] ?? '',
    'unidad_numero'        => $comprobante['unidad'] ?? '',
    'edificio_nombre'      => 'Conjunto',
    'numero_factura'       => $comprobante['numero_factura'] ?? '',
    'factura_id'           => $comprobante['factura_id'] ?? null,
    'saldo_factura'        => $comprobante['saldo'] ?? 0,
    'monto_total'          => $comprobante['monto_total'] ?? $comprobante['saldo'] ?? 0,
    'saldo_restante'       => $saldo_restante,
    'metodo_pago'          => $comprobante['metodo_pago'] ?? 'transferencia',
    'referencia'           => $comprobante['referencia'] ?? '',
    'fecha_pago'           => $comprobante['fecha_pago'] ?? date('Y-m-d'),
    'fecha_registro'       => $comprobante['fecha_envio'] ?? null,
    'archivo'              => $archivo,
    'observaciones'        => $comprobante['observaciones'] ?? '',
    'banco_pagador'        => $comprobante['banco_pagador'] ?? '',
    'banco_receptor'       => $comprobante['banco_receptor'] ?? '',
    'cuenta_bancaria_id'   => $comprobante['cuenta_bancaria_id'] ?? null,
    'tipo_origen'          => 'comprobante',
    'action_url'           => '/admin/comprobante/verificar?id=' . ($comprobante['id'] ?? 0) . (!empty($_GET['from']) ? '&from=' . urlencode($_GET['from']) : ''),
    'log_auditoria'        => [],
    'observaciones_admin'  => $comprobante['observaciones'] ?? '',
];

// Delegación a la vista canónica unificada
require VIEWS_PATH . '/pagos/detalle.php';
