<?php
namespace Tests;

/**
 * Verifica los assets de marca: el fondo local del login (sin Unsplash), el
 * nuevo logo en su ruta canónica y los iconos regenerados.
 */
class MarcaAssetsTest extends TestCase {

    private function readFile(string $relativePath): string {
        return (string)file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    }

    public function testLoginUsaFondoLocalYLogoCanonico(): void {
        $login = $this->readFile('app/views/auth/login.php');

        $this->assertStringContains("url('/img/fondo_login.jpg')", $login,
            "El fondo del login debe ser la imagen local /img/fondo_login.jpg");
        $this->assertFalse(str_contains($login, 'unsplash'),
            "El login no debe depender de la foto externa de Unsplash");
        $this->assertStringContains('/img/logo_condominio.png', $login,
            "El login debe usar el logo canónico /img/logo_condominio.png");
    }

    public function testAssetsDeMarcaValidosEnDisco(): void {
        $raiz = dirname(__DIR__);

        foreach (['public/img/logo_condominio.png', 'public/img/fondo_login.jpg', 'public/favicon.png', 'public/favicon.ico', 'public/apple-touch-icon.png'] as $archivo) {
            $ruta = $raiz . '/' . $archivo;
            $this->assertTrue(file_exists($ruta), "El asset {$archivo} debe existir");
            $this->assertTrue(is_array(@getimagesize($ruta)), "El asset {$archivo} debe ser una imagen válida");
        }

        $logo = getimagesize($raiz . '/public/img/logo_condominio.png');
        $this->assertTrue($logo[0] === 570 && $logo[1] === 437,
            "El logo canónico debe ser el nuevo (570x437)");
    }
}
