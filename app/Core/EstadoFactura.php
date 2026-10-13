<?php
namespace App\Core;

class EstadoFactura {
    public const PENDIENTE = 'pendiente';
    public const PAGADA = 'pagada';
    public const ANULADA = 'anulada';

    public static function all(): array {
        return [
            self::PENDIENTE,
            self::PAGADA,
            self::ANULADA,
        ];
    }
}
