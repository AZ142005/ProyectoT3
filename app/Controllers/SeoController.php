<?php
namespace App\Controllers;

use App\Core\Controller;

/**
 * Controlador SEO público: robots.txt y sitemap.xml.
 *
 * Expone las directivas de rastreo y el mapa del sitio con la URL absoluta
 * derivada de APP_URL (o del host de la petición como respaldo).
 */
class SeoController extends Controller {

    /**
     * Publica robots.txt con las directivas de rastreo y la referencia al sitemap.
     */
    public function robots(): void {
        header('Content-Type: text/plain; charset=UTF-8');

        echo "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /admin\n"
            . "Disallow: /residente\n"
            . "Disallow: /auditor\n"
            . "Disallow: /pagos\n"
            . "Disallow: /perfil\n"
            . "Disallow: /api\n"
            . "Disallow: /auth/verificar-2fa\n"
            . "Disallow: /auth/reenviar-otp\n"
            . "Disallow: /auth/registro-exitoso\n"
            . "Disallow: /auth/logout\n"
            . "Disallow: /logout\n"
            . "Disallow: /comprobante-proxy.php\n"
            . "Disallow: /soporte-proxy.php\n"
            . "Disallow: /cron-notificaciones.php\n"
            . "\n"
            . "Sitemap: " . app_url('/sitemap.xml') . "\n";
    }

    /**
     * Publica sitemap.xml con la página pública principal.
     */
    public function sitemap(): void {
        header('Content-Type: application/xml; charset=UTF-8');

        // Escapado XML de la URL absoluta (ENT_XML1) para un documento válido.
        $homeUrl = htmlspecialchars(app_url('/'), ENT_QUOTES | ENT_XML1, 'UTF-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . '  <url>' . "\n"
            . '    <loc>' . $homeUrl . '</loc>' . "\n"
            . '    <changefreq>monthly</changefreq>' . "\n"
            . '    <priority>1.0</priority>' . "\n"
            . '  </url>' . "\n"
            . '</urlset>' . "\n";
    }
}
