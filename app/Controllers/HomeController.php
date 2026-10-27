<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

/**
 * Controlador de la página pública principal (landing informativa del conjunto).
 *
 * Expone los datos oficiales NAP (nombre, dirección, teléfono), los metadatos
 * SEO y el JSON-LD (Organization, ApartmentComplex, WebSite y FAQPage) que
 * permiten posicionar las búsquedas de marca del Condominio Conjunto
 * Las Mesetas de Morón y facilitar el acceso de los residentes al portal de pagos.
 */
class HomeController extends Controller {

    /**
     * Renderiza la landing pública con metadatos SEO y datos estructurados.
     */
    public function index(): void {
        $siteName = 'Condominio Conjunto Las Mesetas de Morón';
        $addressLine = 'Av. Principal de Morón, Urb. Las Mesetas, Parroquia Mercedes Díaz';
        $cityLine = 'Valera, Estado Trujillo, Venezuela';
        $phone = '0271-2259170';
        $phoneHref = '+582712259170';
        $email = 'pagosjdclm@gmail.com';
        $rif = 'J-400577870';
        $instagramUrl = 'https://www.instagram.com/conjunto.lasmesetas/';
        $facebookUrl = 'https://www.facebook.com/lasmesetasdmoron/';
        $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=Condominio+Conjunto+Las+Mesetas+de+Mor%C3%B3n%2C+Valera%2C+Trujillo%2C+Venezuela';

        // Preguntas frecuentes: alimentan la sección visible y el JSON-LD FAQPage.
        $faqs = [
            [
                'pregunta' => '¿El portal es oficial?',
                'respuesta' => 'Sí. Es el portal de pagos de la administración del Condominio Conjunto Las Mesetas de Morón, ubicado en Valera, Estado Trujillo, Venezuela.',
            ],
            [
                'pregunta' => '¿Cómo reporto mi pago?',
                'respuesta' => 'Ingresa a la sección Pagar sin iniciar sesión, selecciona tu unidad, indica el monto y adjunta una foto o PDF de tu comprobante. La administración lo verificará.',
            ],
            [
                'pregunta' => '¿Necesito una cuenta para pagar?',
                'respuesta' => 'No. Puedes reportar tu pago sin iniciar sesión. Si eres residente registrado, inicia sesión para consultar tu estado de cuenta y tu historial.',
            ],
            [
                'pregunta' => '¿Dónde está ubicado el conjunto?',
                'respuesta' => 'Av. Principal de Morón, Urb. Las Mesetas, Parroquia Mercedes Díaz, Valera, Estado Trujillo, Venezuela.',
            ],
            [
                'pregunta' => '¿Cómo sé si mi pago fue aprobado?',
                'respuesta' => 'La administración verifica el comprobante y te notifica el resultado por correo electrónico.',
            ],
            [
                'pregunta' => '¿Qué hago si no puedo iniciar sesión?',
                'respuesta' => 'Comunícate con la administración al 0271-2259170 o al correo pagosjdclm@gmail.com.',
            ],
        ];

        // Enlace del panel según la sesión activa (null para visitantes).
        $panelUrl = null;
        if (Auth::check()) {
            $panelUrl = Auth::hasRole('residente') ? '/residente/dashboard' : '/admin/dashboard';
        }

        // Dirección postal reutilizada por Organization y ApartmentComplex.
        $postalAddress = [
            '@type' => 'PostalAddress',
            'streetAddress' => $addressLine,
            'addressLocality' => 'Valera',
            'addressRegion' => 'Trujillo',
            'addressCountry' => 'VE',
        ];

        $homeUrl = app_url('/');
        $logoUrl = app_url('/img/logo_condominio.png');

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $homeUrl . '#organizacion',
                    'name' => $siteName,
                    'url' => $homeUrl,
                    'logo' => $logoUrl,
                    'telephone' => '+58-271-2259170',
                    'email' => $email,
                    'taxID' => $rif,
                    'address' => $postalAddress,
                    'sameAs' => [$instagramUrl, $facebookUrl],
                ],
                [
                    '@type' => 'ApartmentComplex',
                    '@id' => $homeUrl . '#conjunto',
                    'name' => $siteName,
                    'url' => $homeUrl,
                    'logo' => $logoUrl,
                    'image' => $logoUrl,
                    'telephone' => '+58-271-2259170',
                    'email' => $email,
                    'address' => $postalAddress,
                    'hasMap' => $mapsUrl,
                    'sameAs' => [$instagramUrl, $facebookUrl],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $homeUrl . '#website',
                    'url' => $homeUrl,
                    'name' => $siteName,
                    'inLanguage' => 'es-VE',
                    'publisher' => ['@id' => $homeUrl . '#organizacion'],
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => array_map(function (array $faq): array {
                        return [
                            '@type' => 'Question',
                            'name' => $faq['pregunta'],
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => $faq['respuesta'],
                            ],
                        ];
                    }, $faqs),
                ],
            ],
        ];

        $this->render('home/index', [
            'title' => 'Conjunto Las Mesetas de Morón | Pago de Condominio – Valera',
            'description' => 'Portal oficial del Condominio Conjunto Las Mesetas de Morón, Valera, Trujillo. Consulta tu deuda y reporta tu pago en línea.',
            'canonical' => '/',
            'robots' => 'index,follow',
            'ogImage' => app_url('/img/logo_condominio.png'),
            'ogImageWidth' => 570,
            'ogImageHeight' => 437,
            'ogType' => 'website',
            'jsonLd' => $jsonLd,
            'hideFooter' => true,
            'siteName' => $siteName,
            'addressLine' => $addressLine,
            'cityLine' => $cityLine,
            'phone' => $phone,
            'phoneHref' => $phoneHref,
            'email' => $email,
            'rif' => $rif,
            'instagramUrl' => $instagramUrl,
            'facebookUrl' => $facebookUrl,
            'mapsUrl' => $mapsUrl,
            'panelUrl' => $panelUrl,
            'faqs' => $faqs,
        ]);
    }
}
