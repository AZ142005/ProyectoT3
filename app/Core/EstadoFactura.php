<?php
namespace App\Core;

class EstadoFactura {
    public const PENDIENTE = 'pendiente';
    public const VENCIDA = 'vencida';
    public const PAGADA = 'pagada';
    public const ANULADA = 'anulada';

    public static function all(): array {
        return [
            self::PENDIENTE,
            self::VENCIDA,
            self::PAGADA,
            self::ANULADA,
        ];
    }
}
