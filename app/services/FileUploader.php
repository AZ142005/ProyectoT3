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

    /**
     * Valida y almacena un archivo subido vía $_FILES.
     *
     * @param array $file Elemento individual de $_FILES (ej. $_FILES['comprobante'])
     * @return string|false Retorna el nombre de archivo generado o false si la validación falla
     */
    public function upload(array $file) {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        if ($file['size'] > $this->maxSize) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

        if (!in_array($mime, $this->allowedMimes, true) || !in_array($ext, $this->allowedExtensions, true)) {
            return false;
        }

        $uniqueName = bin2hex(random_bytes(16)) . ($ext ? ".{$ext}" : '');
        $destination = $this->uploadPath . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return false;
        }

        return $uniqueName;
    }
}
