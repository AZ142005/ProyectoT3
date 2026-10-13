<?php
namespace Tests;

use App\Services\FileUploader;

/**
 * Compresión de imágenes de comprobantes (ODD compresion-retencion-comprobantes, T1).
 *
 * Pruebas de comportamiento real sobre FileUploader::comprimirImagen() con fixtures
 * generados con GD en un directorio temporal propio (limpiado al final de cada
 * prueba): reducción a 1600 px, PNG con alfa → JPEG aplanado sobre blanco,
 * orientación EXIF (3/6/8 y espejos omitidos), archivos corruptos byte-idénticos
 * y ausencia de residuos `*.conv`.
 *
 * Alcance: no es una prueba E2E de upload(); move_uploaded_file() rechaza en CLI
 * cualquier archivo que no provenga de un POST HTTP real (misma limitación
 * documentada en PagoUploadHuerfanoTest). El cableado de upload() se verifica de
 * forma estática.
 */
class FileUploaderCompresionTest extends TestCase {

    /** Crea un directorio temporal exclusivo para la prueba. */
    private function crearDirTemporal(): string {
        $dir = sys_get_temp_dir() . '/proyectot3_compresion_' . bin2hex(random_bytes(6));
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear el directorio temporal: {$dir}");
        }
        return $dir;
    }

    /** Elimina el directorio temporal y todo su contenido. */
    private function limpiarDir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $nombre) {
            if ($nombre !== '.' && $nombre !== '..') {
                @unlink($dir . '/' . $nombre);
            }
        }
        @rmdir($dir);
    }

    /** @return string[] Nombres de archivo presentes en el directorio, ordenados. */
    private function archivosEn(string $dir): array {
        $nombres = [];
        foreach (scandir($dir) ?: [] as $nombre) {
            if ($nombre !== '.' && $nombre !== '..' && is_file($dir . '/' . $nombre)) {
                $nombres[] = $nombre;
            }
        }
        sort($nombres);
        return $nombres;
    }

    /** @return array{int,int,int} Canal RGB del píxel indicado, o [-1,-1,-1] si no decodifica. */
    private function colorEn(string $ruta, int $x, int $y): array {
        $img = @imagecreatefromstring((string)file_get_contents($ruta));
        if ($img === false) {
            return [-1, -1, -1];
        }
        $px = imagecolorat($img, $x, $y);
        imagedestroy($img);
        return [($px >> 16) & 0xFF, ($px >> 8) & 0xFF, $px & 0xFF];
    }

    /**
     * JPEG ruidoso (rectángulos aleatorios) para que recomprimir reduzca peso
     * de forma observable.
     */
    private function crearJpegRuidoso(string $ruta, int $ancho, int $alto, int $rectangulos = 9000): void {
        $img = imagecreatetruecolor($ancho, $alto);
        for ($i = 0; $i < $rectangulos; $i++) {
            $color = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            $x = random_int(0, $ancho - 2);
            $y = random_int(0, $alto - 2);
            imagefilledrectangle($img, $x, $y, $x + random_int(1, 50), $y + random_int(1, 50), $color);
        }
        imagejpeg($img, $ruta, 100);
        imagedestroy($img);
    }

    /**
     * PNG con borde transparente y ruido RGB interior. El ruido se construye
     * desde un BMP generado con bytes aleatorios (alta entropía, rápido) para
     * garantizar que el PNG pese más que su versión recomprimida a JPEG.
     */
    private function crearPngConTransparencia(string $ruta, int $ancho, int $alto): void {
        $rgb = random_bytes($ancho * $alto * 3);
        $filaBytes = (int)(floor(($ancho * 3 + 3) / 4) * 4);
        $datos = $filaBytes * $alto;
        // BMP 24 bits: cabecera + filas de abajo hacia arriba con padding a múltiplo de 4
        $bmp = "BM" . pack('V', 54 + $datos) . pack('v', 0) . pack('v', 0) . pack('V', 54)
             . pack('V', 40) . pack('V', $ancho) . pack('V', $alto) . pack('v', 1) . pack('v', 24)
             . pack('V', 0) . pack('V', $datos) . pack('V', 2835) . pack('V', 2835) . pack('V', 0) . pack('V', 0);
        $relleno = str_repeat("\x00", $filaBytes - $ancho * 3);
        for ($y = $alto - 1; $y >= 0; $y--) {
            $bmp .= substr($rgb, $y * $ancho * 3, $ancho * 3) . $relleno;
        }

        $img = imagecreatefromstring($bmp);
        if ($img === false) {
            throw new \RuntimeException('No se pudo construir el fixture PNG ruidoso');
        }

        // Borde transparente (40 px) para verificar el aplanado sobre blanco
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparente = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefilledrectangle($img, 0, 0, $ancho - 1, 40, $transparente);
        imagefilledrectangle($img, 0, 0, 40, $alto - 1, $transparente);
        imagepng($img, $ruta);
        imagedestroy($img);
    }

    /**
     * JPEG asimétrico (mitad izquierda roja, mitad derecha azul) con un segmento
     * APP1 EXIF mínimo que declara la orientación indicada.
     */
    private function crearJpegConOrientacion(string $ruta, int $orientacion): void {
        $img = imagecreatetruecolor(32, 16);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 0, 0, 15, 15, imagecolorallocate($img, 255, 0, 0));
        imagefilledrectangle($img, 16, 0, 31, 15, imagecolorallocate($img, 0, 0, 255));
        imagejpeg($img, $ruta, 90);
        imagedestroy($img);

        // APP1 EXIF mínimo (TIFF little-endian) con solo el tag Orientation (0x0112)
        $tiff = "II\x2A\x00" . pack('V', 8);
        $ifd = pack('v', 1)
             . pack('v', 0x0112) . pack('v', 3) . pack('V', 1) . pack('v', $orientacion) . "\x00\x00"
             . pack('V', 0);
        $payload = "Exif\x00\x00" . $tiff . $ifd;
        $app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

        // Insertar después del SOI, conservando el resto del JPEG
        $jpeg = (string)file_get_contents($ruta);
        file_put_contents($ruta, substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2));
    }

    /**
     * PNG mínimo (firma + IHDR + IEND) cuyo IHDR declara dimensiones gigantes.
     * getimagesize solo lee la cabecera: no verifica CRC ni decodifica píxeles.
     */
    private function crearPngBomba(string $ruta, int $ancho, int $alto): void {
        $ihdr = pack('N', $ancho) . pack('N', $alto) . "\x08\x02\x00\x00\x00";
        $png = "\x89PNG\r\n\x1a\n"
             . pack('N', strlen($ihdr)) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr))
             . pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));
        file_put_contents($ruta, $png);
    }

    public function testJpegGrandeSeReduceA1600PxYQuedaValido(): void {
        if (!function_exists('imagecreatefromstring')) {
            $this->skip('GD no disponible: compresión omitida');
            return;
        }

        $dir = $this->crearDirTemporal();
        try {
            $ruta = $dir . '/grande.jpg';
            $this->crearJpegRuidoso($ruta, 2500, 1800);
            $pesoAntes = filesize($ruta);
            $archivosAntes = $this->archivosEn($dir);

            $this->assertTrue(FileUploader::comprimirImagen($ruta),
                'La imagen JPEG grande debe comprimirse');

            $info = getimagesize($ruta);
            $this->assertEquals(1600, max($info[0], $info[1]),
                'El lado mayor debe quedar en 1600 px');
            $this->assertEquals(IMAGETYPE_JPEG, $info[2],
                'El resultado debe seguir siendo un JPEG válido');

            $redecodificada = @imagecreatefromstring((string)file_get_contents($ruta));
            $this->assertTrue($redecodificada !== false,
                'El JPEG resultante debe decodificarse sin errores');
            if ($redecodificada !== false) {
                imagedestroy($redecodificada);
            }

            $this->assertTrue(filesize($ruta) < $pesoAntes,
                'El archivo comprimido debe pesar menos que el original');
            $this->assertEquals($archivosAntes, $this->archivosEn($dir),
                'No deben quedar archivos extra ni residuos *.conv');
        } finally {
            $this->limpiarDir($dir);
        }
    }

    public function testPngConTransparenciaSeConvierteAJpegYAplanadoSobreBlanco(): void {
        if (!function_exists('imagecreatefromstring')) {
            $this->skip('GD no disponible: compresión omitida');
            return;
        }

        $dir = $this->crearDirTemporal();
        try {
            $ruta = $dir . '/con_alpha.png';
            $this->crearPngConTransparencia($ruta, 2000, 1500);
            $pesoAntes = filesize($ruta);
            $archivosAntes = $this->archivosEn($dir);

            $this->assertTrue(FileUploader::comprimirImagen($ruta),
                'El PNG con transparencia debe comprimirse');

            $info = getimagesize($ruta);
            $this->assertEquals(IMAGETYPE_JPEG, $info[2],
                'El PNG recomprimido debe guardarse como JPEG');
            $this->assertEquals(1600, max($info[0], $info[1]),
                'El lado mayor debe quedar en 1600 px');
            $this->assertTrue(filesize($ruta) < $pesoAntes,
                'El JPEG resultante debe pesar menos que el PNG original');

            [$r, $g, $b] = $this->colorEn($ruta, 5, 5);
            $this->assertTrue($r >= 230 && $g >= 230 && $b >= 230,
                "La transparencia debe aplanarse sobre blanco (esquina leída: {$r},{$g},{$b})");
            $this->assertEquals($archivosAntes, $this->archivosEn($dir),
                'No deben quedar archivos extra ni residuos *.conv');
        } finally {
            $this->limpiarDir($dir);
        }
    }

    public function testJpegConOrientacionExifSeRotaSegunElTag(): void {
        if (!function_exists('imagecreatefromstring')) {
            $this->skip('GD no disponible: compresión omitida');
            return;
        }
        if (!function_exists('exif_read_data')) {
            $this->skip('ext-exif no disponible: orientación omitida');
            return;
        }

        // orientación => [ancho, alto, x/y del bloque rojo, x/y del bloque azul]
        $esperados = [
            3 => [32, 16, 24, 8, 8, 8],  // 180°: rojo a la derecha
            6 => [16, 32, 8, 8, 8, 24],  // 90° horario: rojo arriba
            8 => [16, 32, 8, 24, 8, 8],  // 90° antihorario: rojo abajo
            5 => [32, 16, 8, 8, 24, 8],  // espejo: se omite (sin rotación)
        ];

        $dir = $this->crearDirTemporal();
        try {
            foreach ($esperados as $orientacion => [$ancho, $alto, $xRojo, $yRojo, $xAzul, $yAzul]) {
                $ruta = $dir . "/orientacion{$orientacion}.jpg";
                $this->crearJpegConOrientacion($ruta, $orientacion);

                $this->assertTrue(FileUploader::comprimirImagen($ruta),
                    "Orientación {$orientacion}: la imagen debe comprimirse");

                $info = getimagesize($ruta);
                $this->assertEquals($ancho, $info[0], "Orientación {$orientacion}: ancho esperado");
                $this->assertEquals($alto, $info[1], "Orientación {$orientacion}: alto esperado");

                [$r, $g, $b] = $this->colorEn($ruta, $xRojo, $yRojo);
                $this->assertTrue($r > 180 && $g < 90 && $b < 90,
                    "Orientación {$orientacion}: el bloque rojo debe quedar en ({$xRojo},{$yRojo}); leído {$r},{$g},{$b}");

                [$r, $g, $b] = $this->colorEn($ruta, $xAzul, $yAzul);
                $this->assertTrue($b > 180 && $r < 90 && $g < 90,
                    "Orientación {$orientacion}: el bloque azul debe quedar en ({$xAzul},{$yAzul}); leído {$r},{$g},{$b}");
            }

            $this->assertEquals(
                ['orientacion3.jpg', 'orientacion5.jpg', 'orientacion6.jpg', 'orientacion8.jpg'],
                $this->archivosEn($dir),
                'No deben quedar archivos extra ni residuos *.conv'
            );
        } finally {
            $this->limpiarDir($dir);
        }
    }

    public function testArchivoCorruptoQuedaByteIdenticoYSinResiduos(): void {
        if (!function_exists('imagecreatefromstring')) {
            $this->skip('GD no disponible: compresión omitida');
            return;
        }

        $dir = $this->crearDirTemporal();
        try {
            $ruta = $dir . '/corrupto.jpg';
            // Bytes fijos y deterministas: random_bytes puede decodificar como
            // formato válido por detección laxa, o disparar reservas enormes de GD.
            file_put_contents($ruta, 'esto no es una imagen' . str_repeat("\x00", 64));

            // Precondición: getimagesize (solo cabecera) no debe reconocerlo.
            $this->assertTrue(@getimagesize($ruta) === false,
                'El fixture corrupto no debe ser reconocible como imagen');

            $hashAntes = md5_file($ruta);

            // El guard anti-bomba rechaza el archivo en getimagesize, sin decodificar.
            $this->assertFalse(FileUploader::comprimirImagen($ruta),
                'Un archivo corrupto no debe "comprimirse"');
            $this->assertEquals($hashAntes, md5_file($ruta),
                'El archivo corrupto debe quedar byte-idéntico');
            $this->assertEquals(['corrupto.jpg'], $this->archivosEn($dir),
                'No deben quedar archivos extra ni residuos *.conv');
        } finally {
            $this->limpiarDir($dir);
        }
    }

    public function testSinResiduosConvTrasExitoYFallo(): void {
        if (!function_exists('imagecreatefromstring')) {
            $this->skip('GD no disponible: compresión omitida');
            return;
        }

        $dir = $this->crearDirTemporal();
        try {
            $jpeg = $dir . '/a.jpg';
            $this->crearJpegRuidoso($jpeg, 400, 300, 500);
            $this->assertEquals(['a.jpg'], $this->archivosEn($dir), 'Estado inicial del JPEG');
            $this->assertTrue(FileUploader::comprimirImagen($jpeg), 'El JPEG debe comprimirse');
            $this->assertEquals(['a.jpg'], $this->archivosEn($dir),
                'Tras comprimir un JPEG el directorio debe conservar solo el archivo esperado');

            $png = $dir . '/b.png';
            $this->crearPngConTransparencia($png, 300, 200);
            $this->assertEquals(['a.jpg', 'b.png'], $this->archivosEn($dir), 'Estado inicial del PNG');
            $this->assertTrue(FileUploader::comprimirImagen($png), 'El PNG debe comprimirse');
            $this->assertEquals(['a.jpg', 'b.png'], $this->archivosEn($dir),
                'Tras convertir un PNG el directorio debe conservar solo los archivos esperados');

            $corrupto = $dir . '/c.bin';
            // Bytes fijos y deterministas (nunca random_bytes en fixtures corruptos)
            file_put_contents($corrupto, 'esto no es una imagen' . str_repeat("\x00", 64));
            $this->assertTrue(@getimagesize($corrupto) === false,
                'El fixture corrupto no debe ser reconocible como imagen');
            $this->assertFalse(FileUploader::comprimirImagen($corrupto),
                'El archivo corrupto no debe comprimirse');
            $this->assertEquals(['a.jpg', 'b.png', 'c.bin'], $this->archivosEn($dir),
                'Tras un fallo de decodificación no debe quedar ningún residuo');

            $this->assertEquals([], (array)glob($dir . '/*.conv'),
                'Nunca debe quedar un archivo *.conv en el directorio');
            $this->assertEquals([], (array)glob($dir . '/*.bak'),
                'Nunca debe quedar un archivo *.bak en el directorio');
        } finally {
            $this->limpiarDir($dir);
        }
    }

    public function testBombaDeDimensionesSeOmiteSinDecodificar(): void {
        if (!function_exists('imagecreatefromstring')) {
            $this->skip('GD no disponible: compresión omitida');
            return;
        }

        $dir = $this->crearDirTemporal();
        try {
            $ruta = $dir . '/bomba.png';
            $this->crearPngBomba($ruta, 50000, 50000);

            // Precondición: getimagesize solo lee la cabecera y reporta las
            // dimensiones declaradas, sin asignar memoria de píxeles.
            $info = @getimagesize($ruta);
            $this->assertTrue($info !== false && (int)$info[0] === 50000 && (int)$info[1] === 50000,
                'El fixture debe declarar dimensiones 50000x50000 en su IHDR');
            if ($info === false || (int)$info[0] !== 50000 || (int)$info[1] !== 50000) {
                return; // Fixture no reconocido: la precondición ya falló
            }

            $hashAntes = md5_file($ruta);
            $this->assertFalse(FileUploader::comprimirImagen($ruta),
                'Una imagen que declara dimensiones absurdas debe omitirse sin decodificar');
            $this->assertEquals($hashAntes, md5_file($ruta),
                'La bomba de dimensiones debe quedar byte-idéntica');
            $this->assertEquals(['bomba.png'], $this->archivosEn($dir),
                'No deben quedar archivos extra ni residuos *.conv');
        } finally {
            $this->limpiarDir($dir);
        }
    }

    public function testCableadoDeLaCompresionEnUploadEsAnteriorAlHash(): void {
        $contenido = (string)file_get_contents(dirname(__DIR__) . '/app/Services/FileUploader.php');

        $posValidacionMime = strpos($contenido, '!in_array($mime, $this->allowedMimes, true)');
        $posComprimir = strpos($contenido, "self::comprimirImagen(\$file['tmp_name'])");
        $posHash = strpos($contenido, "hash_file('sha256'");
        $posExtensionJpg = strpos($contenido, "\$ext = 'jpg';");

        $this->assertTrue(
            $posValidacionMime !== false && $posComprimir !== false
                && $posHash !== false && $posExtensionJpg !== false,
            'upload() debe validar mime/extensión, comprimir imágenes, asignar extensión jpg y calcular el hash'
        );

        if ($posValidacionMime !== false && $posComprimir !== false
            && $posHash !== false && $posExtensionJpg !== false) {
            $this->assertTrue($posValidacionMime < $posComprimir,
                'La compresión debe ejecutarse después de validar mime/extensión');
            $this->assertTrue($posComprimir < $posHash,
                'El hash SHA-256 debe calcularse DESPUÉS de comprimir (debe coincidir con el archivo guardado)');
            $this->assertTrue($posComprimir < $posExtensionJpg,
                'La extensión jpg debe asignarse en la misma rama que comprime');
        }

        // Condición de necesidad: solo imágenes con lado > 1600 px o más de 256 KB
        $this->assertStringContains("in_array(\$mime, ['image/jpeg', 'image/png'], true)", $contenido,
            'Solo JPEG/PNG deben pasar por compresión (los PDF no se tocan)');
        $this->assertStringContains('$ladoMayor > 1600', $contenido,
            'La compresión debe activarse con lado mayor a 1600 px');
        $this->assertStringContains("\$file['size'] > 262144", $contenido,
            'La compresión debe activarse con archivos mayores a 256 KB');
        $this->assertStringContains('public static function comprimirImagen(string $ruta): bool', $contenido,
            'comprimirImagen() debe ser un método público estático');
        $this->assertStringContains("function_exists('imagecreatefromstring')", $contenido,
            'Debe existir el fallback silencioso cuando GD no está disponible');
    }
}
