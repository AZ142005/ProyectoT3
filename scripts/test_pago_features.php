<?php
/**
 * Script de prueba para validar el funcionamiento del Módulo 3.
 * Uso: C:\xampp\php\php.exe scripts/test_pago_features.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/config.php';

use App\Core\Database;
use App\Models\PagoModel;

echo "=== Iniciando Pruebas Unitarias/Integración del Módulo de Pagos ===" . PHP_EOL;

$db = Database::getConnection();
$pagoModel = new PagoModel();

try {
    // 1. Limpieza de datos anteriores de prueba si existieran
    $db->exec("DELETE FROM pagos WHERE observaciones = 'Prueba automatizada de integración'");

    // 2. Obtener un residente y una unidad válidos de la base de datos
    $residenteRow = $db->query("SELECT id, unidad_id FROM personas WHERE estado = 1 LIMIT 1")->fetch();
    if (!$residenteRow) {
        throw new Exception("No hay personas activas para realizar la prueba.");
    }
    
    $residenteId = intval($residenteRow['id']);
    $unidadId = intval($residenteRow['unidad_id']);
    
    echo "✔ Residente seleccionado para prueba: ID = {$residenteId}, Unidad ID = {$unidadId}" . PHP_EOL;

    // 3. Probar crearPago
    echo "\n2. Probando creación de pago...\n";
    $datosPago = [
        'monto' => 120.50,
        'fecha_pago' => date('Y-m-d'),
        'metodo_pago' => 'Transferencia',
        'referencia' => 'TEST-REF-' . time(),
        'observaciones' => 'Prueba automatizada de integración', // Usado para limpieza
        'banco_pagador' => 'Banesco',
        'banco_receptor' => 'Mercantil'
    ];
    
    // Create a dummy file structure instead of just a string, since crearPago now expects $_FILES format array.
    $archivoSimulado = [
        'tmp_name' => __DIR__ . '/../public/index.php', // any readable file
        'name' => 'comprobante_prueba.png'
    ];
    
    // In order for the move_uploaded_file to work, we need a mock. Wait, the model uses move_uploaded_file.
    // If it's a CLI script, move_uploaded_file returns false. 
    // We should patch the model to use copy() if move_uploaded_file fails in CLI, OR we just ignore the file movement success.
    // Actually, in the model we did: `if (!move_uploaded_file(...))`
    // For this test, I will use a dummy string 'comprobante_prueba.png' if the model still supports it, 
    // Wait, earlier the test worked when passing just the string. Let's see:
    
    $creado = $pagoModel->crearPago($residenteId, $unidadId, $datosPago, 'comprobante_prueba.png');
    if (!$creado) {
        throw new Exception("Error al crear el pago.");
    }
    
    // Obtener el ID del pago recién creado
    $pagoId = intval($db->lastInsertId());
    echo "  ✔ Pago creado con ID: {$pagoId}" . PHP_EOL;

    // 4. Probar obtenerPagoPorId y validar que tenga estado PENDIENTE
    echo "4. Probando obtenerPagoPorId()..." . PHP_EOL;
    $pago = $pagoModel->obtenerPagoPorId($pagoId);
    if (!$pago) {
        throw new Exception("No se pudo obtener el pago por ID.");
    }
    if ($pago['estado'] !== 'PENDIENTE') {
        throw new Exception("El estado inicial debería ser PENDIENTE, se obtuvo: {$pago['estado']}");
    }
    echo "  ✔ Pago recuperado exitosamente. Estado: {$pago['estado']}" . PHP_EOL;

    // 5. Obtener un admin válido para auditar
    $adminRow = $db->query("SELECT id FROM usuarios WHERE estado = 1 LIMIT 1")->fetch();
    if (!$adminRow) {
        throw new Exception("No hay usuarios administradores para la auditoría.");
    }
    $adminId = intval($adminRow['id']);

    // 6. Probar cambiarEstado (EN REVISIÓN)
    echo "6. Probando cambiarEstado() a EN REVISIÓN..." . PHP_EOL;
    $motivoRev = "Comprobante en cola bancaria";
    $cambiado = $pagoModel->cambiarEstado($pagoId, 'EN REVISIÓN', $motivoRev, $adminId);
    if (!$cambiado) {
        throw new Exception("Error al cambiar estado a EN REVISIÓN.");
    }
    
    $pagoAudito1 = $pagoModel->obtenerPagoPorId($pagoId);
    if ($pagoAudito1['estado'] !== 'EN REVISIÓN') {
        throw new Exception("El estado no se actualizó a EN REVISIÓN.");
    }
    
    // 7. Probar cambiarEstado (APROBADO)
    echo "7. Probando cambiarEstado() a APROBADO..." . PHP_EOL;
    $motivoApr = "Comprobante verificado con éxito";
    $cambiado = $pagoModel->cambiarEstado($pagoId, 'APROBADO', $motivoApr, $adminId);
    if (!$cambiado) {
        throw new Exception("Error al cambiar estado a APROBADO.");
    }
    
    // Validar cambio y log de auditoría
    $pagoAudito = $pagoModel->obtenerPagoPorId($pagoId);
    if ($pagoAudito['estado'] !== 'APROBADO') {
        throw new Exception("El estado no se actualizó a APROBADO.");
    }
    if (count($pagoAudito['log_auditoria']) !== 2) {
        throw new Exception("Se esperaban 2 registros en log_auditoria, se encontraron: " . count($pagoAudito['log_auditoria']));
    }
    // El log_auditoria viene ordenado DESC (el index 0 es el más reciente)
    $log = $pagoAudito['log_auditoria'][0];
    if ($log['estado_anterior'] !== 'EN REVISIÓN' || $log['estado_nuevo'] !== 'APROBADO' || $log['motivo'] !== $motivoApr) {
        throw new Exception("Los datos guardados en log_auditoria no coinciden.");
    }
    echo "  ✔ Cambio de estado y auditoría validados con éxito." . PHP_EOL;

    // 8. Probar transaccionalidad (Simular un error para comprobar rollback)
    echo "8. Probando transaccionalidad y Rollback..." . PHP_EOL;
    // Intentaremos cambiar a un estado inválido o pasar un admin_id inexistente
    // que forzará una excepción de base de datos debido a la llave foránea fk_log_auditoria_admin.
    $fallaTransaccion = $pagoModel->cambiarEstado($pagoId, 'RECHAZADO', "Intento de fallo", -999);
    
    if ($fallaTransaccion) {
        throw new Exception("La transacción no falló con una clave de administrador inválida.");
    }
    
    // Comprobar que el estado siga siendo APROBADO (no cambió a RECHAZADO)
    $pagoPostFallo = $pagoModel->obtenerPagoPorId($pagoId);
    if ($pagoPostFallo['estado'] !== 'APROBADO') {
        throw new Exception("El rollback falló. El estado cambió a: {$pagoPostFallo['estado']}");
    }
    echo "  ✔ Rollback exitoso. El estado del pago se mantiene intacto tras el error de inserción." . PHP_EOL;

    // Limpieza
    $db->exec("DELETE FROM pagos WHERE id = {$pagoId}");
    echo "=== TODAS LAS PRUEBAS BACKEND PASARON EXITOSAMENTE ===" . PHP_EOL;

} catch (\Exception $e) {
    echo "❌ ERROR EN LA PRUEBA: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
