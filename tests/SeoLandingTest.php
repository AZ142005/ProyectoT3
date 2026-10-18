<?php
namespace Tests;

/**
 * Verifica la landing pública: nombre oficial, NAP, enlaces de acceso y la
 * ruta raíz servida por HomeController. Los metadatos y el rastreo se cubren
 * en SeoMetadataTest.
 */
class SeoLandingTest extends TestCase {

    private function readFile(string $relativePath): string {
        return (string)file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    }

    public function testLandingPublicaUsaNombreOficialYNap(): void {
        $landing = $this->readFile('app/views/home/index.php');

        $this->assertStringContains('Conjunto Residencial Las Mesetas de Morón', $landing,
            "La landing debe usar el nombre oficial del conjunto");
        $this->assertStringContains('Valera', $landing,
            "La landing debe mencionar Valera");
        $this->assertStringContains('Trujillo', $landing,
            "La landing debe mencionar Trujillo");
        $this->assertStringContains('0271-2259170', $landing,
            "La landing debe incluir el teléfono oficial");
        $this->assertStringContains('/pago-directo', $landing,
            "La landing debe enlazar al portal de pago directo");
        $this->assertStringContains('/auth/login', $landing,
            "La landing debe enlazar al inicio de sesión");
    }

    public function testLandingRouteUsesHomeController(): void {
        $index = $this->readFile('public/index.php');

        $this->assertStringContains('HomeController', $index,
            "La portada pública debe registrarse con HomeController");
        $this->assertStringContains("get('/', [HomeController::class, 'index'])", $index,
            "La ruta raíz debe renderizar la landing pública");
    }

    public function testHelperAppUrlYEnvEjemplo(): void {
        $helpers = $this->readFile('app/Core/helpers.php');
        $envExample = $this->readFile('.env.example');

        $this->assertStringContains('function app_url', $helpers,
            "Debe existir el helper app_url");
        $this->assertStringContains('APP_URL=', $envExample,
            "El .env.example debe documentar la variable APP_URL");
    }
}
