<?php
namespace App\Core;

use Exception;

/**
 * Excepción especializada para errores de negocio y concurrencia en conciliación bancaria.
 */
class ConciliacionException extends Exception {

    protected string $codigoNegocio;
    protected int $statusHttp;

    public function __construct(
        string $message,
        int $statusHttp = 409,
        string $codigoNegocio = 'ABONO_YA_UTILIZADO',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusHttp, $previous);
        $this->statusHttp = $statusHttp;
        $this->codigoNegocio = $codigoNegocio;
    }

    public function getCodigoNegocio(): string {
        return $this->codigoNegocio;
    }

    public function getStatusHttp(): int {
        return $this->statusHttp;
    }
}
