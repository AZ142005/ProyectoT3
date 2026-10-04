<?php
namespace Tests;

use App\Controllers\AuditorController;

/**
 * Regresión del módulo Auditor (landing posterior al login):
 * el dashboard consultaba gastos_comunes con una columna inexistente
 * ("activo") y lanzaba PDOException al entrar como auditor.
 */
class AuditorDashboardTest extends TestCase {

    /**
     * Renderiza una acción del AuditorController con sesión de auditor y
     * devuelve el HTML; si la acción lanza, lo reporta como fallo con detalle.
     */
    private function renderizarComoAuditor(callable $accion): string {
        $_SESSION['auth_user'] = [
            'id'    => 1,
            'role'  => 'auditor',
            'name'  => 'Auditor Test',
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
        $html = $this->renderizarComoAuditor(fn($ctrl) => $ctrl->dashboard());

        $this->assertStringContains('Panel de Fiscalización', $html,
            'El dashboard del auditor debe mostrar el panel de fiscalización');
        $this->assertStringContains('Total Gastos Auditados', $html,
            'El dashboard debe incluir la métrica de gastos (regresión: gastos_comunes.activo no existe)');
        $this->assertStringContains('Últimos Eventos de Auditoría Registrados', $html,
            'El dashboard debe incluir la tabla de últimos eventos');
    }

    public function testLogTransaccionesRenderiza(): void {
        $html = $this->renderizarComoAuditor(fn($ctrl) => $ctrl->logTransacciones());

        $this->assertStringContains('Log de Auditoría', $html,
            'La página del log debe renderizar su encabezado');
        $this->assertStringContains('Eventos de Auditoría', $html,
            'La página del log debe renderizar la tabla de eventos');
    }
}
