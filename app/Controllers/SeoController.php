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
     * Publica robots.txt con las directivas de rastreo (Disallow: /admin) y la referencia Sitemap:
     */
    public function robots(): void {
        header('Content-Type: text/plain; charset=UTF-8');
        $sitemapUrl = app_url('/sitemap.xml');
        require VIEWS_PATH . '/seo/robots.php';
        exit;
    }

    /**
     * Publica sitemap.xml con el contenedor urlset para la página pública principal.
     */
    public function sitemap(): void {
        header('Content-Type: application/xml; charset=UTF-8');

        // Escapado XML de la URL absoluta (ENT_XML1) para un documento válido.
        $homeUrl = htmlspecialchars(app_url('/'), ENT_QUOTES | ENT_XML1, 'UTF-8');
        require VIEWS_PATH . '/seo/sitemap.php';
        exit;
    }
}
