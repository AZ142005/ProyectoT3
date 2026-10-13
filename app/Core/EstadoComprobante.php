<?php
namespace App\Core;

class EstadoComprobante {
    public const PENDIENTE = 'pendiente';
    public const APROBADO = 'aprobado';
    public const RECHAZADO = 'rechazado';

    public static function all(): array {
        return [
            self::PENDIENTE,
            self::APROBADO,
            self::RECHAZADO,
        ];
    }
}
