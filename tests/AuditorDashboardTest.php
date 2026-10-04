<?php
namespace Tests;

use App\Controllers\AuditorController;

/**
 * Regresión del módulo de auditoría (ahora accesible solo por administradores):
 * el dashboard consultaba gastos_comunes con una columna inexistente
 * ("activo") y lanzaba PDOException al entrar.
 */
class AuditorDashboardTest extends TestCase {

    /**
     * Renderiza una acción del AuditorController con sesión de administrador y
     * devuelve el HTML; si la acción lanza, lo reporta como fallo con detalle.
     */
    private function renderizarComoAdmin(callable $accion): string {
        $_SESSION['auth_user'] = [
            'id'    => 1,
            'role'  => 'admin',
            'name'  => 'Administrador Test',
        ];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $ctrl = new class extends AuditorController {
            protected function redirect($url): void {
                throw new \RuntimeException('redirect inesperado a ' . $url);
            }
        };

        $nivelBase = ob_get_level();
        $error = null;
        $html = '';

        ob_start();
        try {
            $accion($ctrl);
            $html = (string)ob_get_clean();
        } catch (\Throwable $t) {
            $error = get_class($t) . ': ' . $t->getMessage();
            while (ob_get_level() > $nivelBase) {
                ob_end_clean();
            }
        }

        $this->assertTrue($error === null,
            'El render debe completarse sin excepciones' . ($error !== null ? ' — ' . $error : ''));

        return $html;
    }

    public function testDashboardAuditorRenderizaSinErrorDeEsquema(): void {
        $html = $this->renderizarComoAdmin(fn($ctrl) => $ctrl->dashboard());

        $this->assertStringContains('Panel de Fiscalización', $html,
            'El dashboard del auditor debe mostrar el panel de fiscalización');
        $this->assertStringContains('Total Gastos Auditados', $html,
            'El dashboard debe incluir la métrica de gastos (regresión: gastos_comunes.activo no existe)');
        $this->assertStringContains('Últimos Eventos de Auditoría Registrados', $html,
            'El dashboard debe incluir la tabla de últimos eventos');
    }

    public function testLogTransaccionesRenderiza(): void {
        $html = $this->renderizarComoAdmin(fn($ctrl) => $ctrl->logTransacciones());

        $this->assertStringContains('Log de Auditoría', $html,
            'La página del log debe renderizar su encabezado');
        $this->assertStringContains('Eventos de Auditoría', $html,
            'La página del log debe renderizar la tabla de eventos');
    }
}
