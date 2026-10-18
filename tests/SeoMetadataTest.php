<?php
namespace Tests;

/**
 * Verifica los metadatos SEO del layout, las rutas de rastreo y la
 * desindexación de las páginas utilitarias.
 */
class SeoMetadataTest extends TestCase {

    private function readFile(string $relativePath): string {
        return (string)file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    }

    public function testHeaderExponeMetadatosSeo(): void {
        $header = $this->readFile('app/views/layouts/header.php');

        $this->assertStringContains('name="description"', $header,
            "El header debe exponer la meta description");
        $this->assertStringContains('rel="canonical"', $header,
            "El header debe exponer el enlace canonical");
        $this->assertStringContains('og:title', $header,
            "El header debe exponer Open Graph");
        $this->assertStringContains('application/ld+json', $header,
            "El header debe soportar JSON-LD");
        $this->assertStringContains('lang="es-VE"', $header,
            "El documento debe declarar el idioma es-VE");
    }

    public function testRutasDeRastreoRegistradas(): void {
        $index = $this->readFile('public/index.php');

        $this->assertStringContains("'/robots.txt'", $index,
            "Debe existir la ruta /robots.txt");
        $this->assertStringContains("'/sitemap.xml'", $index,
            "Debe existir la ruta /sitemap.xml");
    }

    public function testPaginasUtilitariasNoSeIndexan(): void {
        $auth = $this->readFile('app/Controllers/AuthController.php');
        $pagoDirecto = $this->readFile('app/Controllers/PagoDirectoController.php');

        $this->assertStringContains('noindex,follow', $auth,
            "Login y registro deben declarar noindex,follow");
        $this->assertStringContains('noindex,nofollow', $auth,
            "La verificación 2FA y el registro exitoso deben declarar noindex,nofollow");
        $this->assertStringContains('noindex,follow', $pagoDirecto,
            "El formulario de pago directo debe declarar noindex,follow");
        $this->assertStringContains('noindex,nofollow', $pagoDirecto,
            "La confirmación de pago registrado debe declarar noindex,nofollow");
    }

    public function testSeoControllerPublicaRobotsYSitemap(): void {
        $seo = $this->readFile('app/Controllers/SeoController.php');

        $this->assertStringContains('Sitemap:', $seo,
            "robots.txt debe referenciar el sitemap");
        $this->assertStringContains('urlset', $seo,
            "sitemap.xml debe declarar el elemento urlset");
        $this->assertStringContains('Disallow: /admin', $seo,
            "robots.txt debe bloquear /admin");
    }
}
