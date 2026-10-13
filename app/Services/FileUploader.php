<?php
namespace App\Services;

/**
 * Servicio unificado de validación y carga segura de archivos.
 */
class FileUploader {
    private array $allowedMimes;
    private array $allowedExtensions;
    private int $maxSize;
    private string $uploadPath;

    public function __construct(
        ?string $uploadPath = null,
        array $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'],
        array $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'],
        int $maxSize = 5242880 // 5 MB
    ) {
        $this->uploadPath = $uploadPath ?: UPLOADS_PATH . '/comprobantes';
        $this->allowedMimes = $allowedMimes;
        $this->allowedExtensions = $allowedExtensions;
        $this->maxSize = $maxSize;

        $this->ensureDirectoryExists();
    }

    /**
     * Garantiza que el directorio de destino exista y sea escribible.
     */
    private function ensureDirectoryExists(): void {
        if (!file_exists($this->uploadPath)) {
            if (!mkdir($this->uploadPath, 0755, true) && !is_dir($this->uploadPath)) {
                throw new \RuntimeException("No se pudo crear el directorio de subidas: {$this->uploadPath}");
            }
        }

        if (!is_writable($this->uploadPath)) {
            throw new \RuntimeException("El directorio de subidas no tiene permisos de escritura: {$this->uploadPath}");
        }
    }

    private ?string $lastFileHash = null;

    /**
     * Valida y almacena un archivo subido vía $_FILES.
     *
     * @param array $file Elemento individual de $_FILES (ej. $_FILES['comprobante'])
     * @return string|false Retorna el nombre de archivo generado o false si la validación falla
     */
    public function upload(array $file) {
        $this->lastFileHash = null;

        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        if ($file['size'] > $this->maxSize) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        if (\PHP_VERSION_ID < 80500 && \is_resource($finfo)) {
            @finfo_close($finfo);
        }
        unset($finfo);

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

        if (!in_array($mime, $this->allowedMimes, true) || !in_array($ext, $this->allowedExtensions, true)) {
            return false;
        }

        // Compresión de imágenes JPEG/PNG (orientación EXIF, máx. 1600 px, JPEG q82)
        // ANTES de calcular el hash: el hash debe corresponder al archivo persistido.
        // Si GD no está disponible o falla, comprimirImagen() devuelve false y la
        // subida continúa con el archivo original.
        $esImagen = in_array($mime, ['image/jpeg', 'image/png'], true);
        if ($esImagen) {
            $infoImagen = @getimagesize($file['tmp_name']);
            $ladoMayor = $infoImagen ? max((int)$infoImagen[0], (int)$infoImagen[1]) : 0;

            if (($ladoMayor > 1600 || $file['size'] > 262144) && self::comprimirImagen($file['tmp_name'])) {
                $ext = 'jpg'; // El PNG/JPEG recomprimido se guarda como JPEG
            }
        }

        // Calcular hash SHA-256 después de comprimir (debe coincidir con el archivo persistido)
        $this->lastFileHash = hash_file('sha256', $file['tmp_name']) ?: null;

        $uniqueName = bin2hex(random_bytes(16)) . ($ext ? ".{$ext}" : '');
        $destination = $this->uploadPath . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->lastFileHash = null;
            return false;
        }

        return $uniqueName;
    }

    /**
     * Comprime una imagen en disco (JPEG/PNG): corrige la orientación EXIF,
     * reduce el lado mayor a 1600 px y la recomprime a JPEG (calidad 82),
     * aplanando la transparencia sobre fondo blanco.
     *
     * El reemplazo es seguro: se codifica a un temporal hermano `*.conv` y el
     * original se aparta a `*.bak` hasta que la versión comprimida quede en su
     * lugar (con restauración si algo falla). Cualquier fallo (o ausencia de GD)
     * deja el archivo original byte-idéntico y sin residuos temporales.
     *
     * @param string $ruta Ruta absoluta del archivo a comprimir (se modifica in-place)
     * @return bool true solo si el archivo fue reemplazado por la versión comprimida
     */
    public static function comprimirImagen(string $ruta): bool {
        if (!function_exists('imagecreatefromstring')) {
            return false; // GD no disponible: los uploads nunca se rompen
        }

        // Anti-bomba: validar dimensiones por cabecera (getimagesize no decodifica)
        // antes de tocar GD. Un archivo manipulado puede declarar dimensiones
        // enormes y agotar la memoria en imagecreatefromstring; con más de 50 MP
        // o un lado mayor a 15000 px se omite la compresión y el original se
        // conserva (los comprobantes reales están muy por debajo de estos límites).
        $info = @getimagesize($ruta);
        if ($info === false) {
            return false; // No es una imagen reconocible: el original queda intacto
        }
        [$anchoReal, $altoReal] = [(int)$info[0], (int)$info[1]];
        if ($anchoReal < 1 || $altoReal < 1 || $anchoReal > 15000 || $altoReal > 15000 || ($anchoReal * $altoReal) > 50000000) {
            return false; // Dimensiones absurdas (>50 MP o lado >15000): se omite compresión, el original se conserva
        }

        $data = @file_get_contents($ruta);
        if ($data === false) {
            return false;
        }

        $img = @imagecreatefromstring($data);
        if ($img === false) {
            return false; // No decodificable: el archivo queda intacto
        }

        // PNG de paleta: convertir a truecolor para conservar la transparencia
        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }

        // Orientación EXIF (solo JPEG): rotaciones 3 (180°), 6 (90° horario) y
        // 8 (90° antihorario). Los espejos 2/4/5/7 se omiten (no aplican flip).
        if (function_exists('exif_read_data') && function_exists('exif_imagetype')
            && @exif_imagetype($ruta) === IMAGETYPE_JPEG) {
            $exif = @exif_read_data($ruta);
            if (is_array($exif) && !empty($exif['Orientation'])) {
                $img = self::aplicarOrientacion($img, (int)$exif['Orientation']);
            }
        }

        // Reducción proporcional: lado mayor = 1600 px (nunca menos de 1 px)
        $ancho = imagesx($img);
        $alto = imagesy($img);
        if (max($ancho, $alto) > 1600) {
            $escala = 1600 / max($ancho, $alto);
            $nuevoAncho = max(1, (int)round($ancho * $escala));
            $nuevoAlto = max(1, (int)round($alto * $escala));
            $redimensionada = @imagescale($img, $nuevoAncho, $nuevoAlto, IMG_BICUBIC);
            if ($redimensionada !== false) {
                imagedestroy($img);
                $img = $redimensionada;
            }
        }

        // Aplanar transparencia sobre blanco (JPEG no soporta canal alfa)
        $ancho = imagesx($img);
        $alto = imagesy($img);
        $lienzo = imagecreatetruecolor($ancho, $alto);
        if ($lienzo !== false) {
            imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
            imagealphablending($lienzo, true);
            imagecopy($lienzo, $img, 0, 0, 0, 0, $ancho, $alto);
            imagedestroy($img);
            $img = $lienzo;
        }

        // Codificar a temporal hermano y reemplazar solo si todo tuvo éxito
        $tmpConv = $ruta . '.conv';
        $codificada = @imagejpeg($img, $tmpConv, 82);
        imagedestroy($img);

        if (!$codificada || !is_file($tmpConv) || @filesize($tmpConv) <= 0) {
            @unlink($tmpConv);
            return false; // El original queda byte-idéntico
        }

        // Reemplazo con respaldo: el original se aparta a `*.bak` hasta que la
        // versión comprimida quede en su lugar; si algo falla se restaura. El
        // respaldo se elimina al final (solo permanecería si la restauración
        // también fallara, preservando los datos).
        $respaldo = $ruta . '.bak';
        if (file_exists($respaldo)) {
            @unlink($respaldo); // Respaldo huérfano de una ejecución interrumpida
        }

        if (!@rename($ruta, $respaldo)) {
            @unlink($tmpConv);
            return false; // No se pudo apartar el original: queda intacto
        }

        if (!@rename($tmpConv, $ruta)) {
            @rename($respaldo, $ruta); // Restaurar el original
            @unlink($tmpConv);
            return false;
        }

        @unlink($respaldo);
        return true;
    }

    /**
     * Aplica la orientación EXIF a una imagen GD con rotaciones puras.
     * Devuelve la misma imagen si la orientación no requiere rotación
     * (incluye 1 y los espejos 2/4/5/7) o si imagerotate falla.
     *
     * @param \GdImage $img Imagen GD a rotar
     * @param int $orientacion Valor EXIF Orientation (1-8)
     * @return \GdImage Imagen rotada (o la original)
     */
    private static function aplicarOrientacion($img, int $orientacion) {
        switch ($orientacion) {
            case 3:
                $rotada = @imagerotate($img, 180, 0);
                break;
            case 6:
                $rotada = @imagerotate($img, -90, 0); // 90° a favor de las agujas del reloj
                break;
            case 8:
                $rotada = @imagerotate($img, 90, 0); // 90° en contra de las agujas del reloj
                break;
            default:
                return $img;
        }

        if ($rotada === false) {
            return $img;
        }

        imagedestroy($img);
        return $rotada;
    }

    /**
     * Retorna el hash SHA-256 del último archivo procesado exitosamente.
     *
     * @return string|null
     */
    public function getLastFileHash(): ?string {
        return $this->lastFileHash;
    }
}
