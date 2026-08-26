<?php
namespace App\Core;

class EstadoPago {
    public const PENDIENTE = 'PENDIENTE';
    public const EN_REVISION = 'EN REVISIÓN';
    public const APROBADO = 'APROBADO';
    public const RECHAZADO = 'RECHAZADO';

    public static function all(): array {
        return [
            self::PENDIENTE,
            self::EN_REVISION,
            self::APROBADO,
            self::RECHAZADO,
        ];
    }
}
