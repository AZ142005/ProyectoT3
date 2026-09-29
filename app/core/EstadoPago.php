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

    /**
     * Máquina de estados única del pago (fuente de verdad compartida por
     * PagoModel, PagoController y ConciliacionController).
     *
     * Las claves y los destinos se expresan con las constantes de la clase
     * (no con strings crudos) para mantener una única fuente de verdad.
     *
     * APROBADO y RECHAZADO son estados terminales.
     */
    public static function transicionesValidas(): array {
        return [
            self::PENDIENTE   => [self::EN_REVISION, self::APROBADO, self::RECHAZADO],
            self::EN_REVISION => [self::APROBADO, self::RECHAZADO],
            self::RECHAZADO   => [],
            self::APROBADO    => [],
        ];
    }

    /**
     * Indica si una transición de estado de pago está permitida.
     * Los estados se comparan en mayúsculas y sin espacios externos.
     */
    public static function puedeTransicionar(string $desde, string $hacia): bool {
        $desde = strtoupper(trim($desde));
        $hacia = strtoupper(trim($hacia));

        $transiciones = self::transicionesValidas();
        if (!array_key_exists($desde, $transiciones)) {
            return false;
        }

        return in_array($hacia, $transiciones[$desde], true);
    }
}
