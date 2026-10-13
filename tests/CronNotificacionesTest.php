<?php
namespace Tests;

/**
 * Web-cron de correos (T6 deploy InfinityFree): el worker de la cola vive en
 * un servicio compartido y se dispara por CLI (local) o por endpoint web con
 * token (hostings sin cron). Verificación estática del cableado.
 */
class CronNotificacionesTest extends TestCase {

    public function testEndpointExisteYProtegeConToken(): void {
        $path = dirname(__DIR__) . '/public/cron-notificaciones.php';
        $this->assertFileExists($path, 'cron-notificaciones.php debe existir en public/');

        $contenido = (string)file_get_contents($path);
        $this->assertStringContains('CRON_TOKEN', $contenido,
            'El endpoint debe exigir el token CRON_TOKEN');
        $this->assertStringContains('hash_equals', $contenido,
            'La comparación del token debe ser timing-safe (hash_equals)');
    }

    public function testServicioCompartidoConservaLockYCola(): void {
        $path = dirname(__DIR__) . '/app/Services/NotificationQueueWorker.php';
        $this->assertFileExists($path, 'NotificationQueueWorker debe existir en app/Services');

        $contenido = (string)file_get_contents($path);
        $this->assertStringContains('notificaciones_cola', $contenido,
            'El worker debe procesar la cola de notificaciones');
        $this->assertStringContains('notifications.lock', $contenido,
            'El worker debe conservar el lockfile con stale recovery');
        $this->assertStringContains("estado = 'enviado'", $contenido,
            'El worker debe marcar los correos enviados');
        $this->assertStringContains('SKIP LOCKED', $contenido,
            'El worker debe conservar el SELECT principal con SKIP LOCKED');
    }

    public function testCliDelegaEnElServicioCompartido(): void {
        $path = dirname(__DIR__) . '/scripts/process_notifications.php';
        $this->assertFileExists($path, 'process_notifications.php debe existir');

        $contenido = (string)file_get_contents($path);
        $this->assertStringContains('NotificationQueueWorker', $contenido,
            'El CLI debe delegar en el servicio compartido (sin lógica duplicada)');
    }
}
