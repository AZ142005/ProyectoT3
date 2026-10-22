<?php
namespace App\Core;

/**
 * Métodos de pago y requisitos de campos compartidos por todos los flujos.
 *
 * Única fuente de verdad para saber qué campos exige cada método. Las vistas
 * reflejan la regla vía atributos data-* y el servidor siempre la valida.
 */
class MetodoPago {
    public const TRANSFERENCIA = 'transferencia';
    public const PAGO_MOVIL = 'pago_movil';

    /**
     * Bancos donde el método no exige número de referencia.
     * @var array<string, string[]>
     */
    private const BANCOS_SIN_REFERENCIA = [
        self::PAGO_MOVIL => ['BBVA Provincial'],
    ];

    public static function all(): array {
        return [self::TRANSFERENCIA, self::PAGO_MOVIL];
    }

    public static function etiquetas(): array {
        return [
            self::TRANSFERENCIA => 'Transferencia Bancaria',
            self::PAGO_MOVIL => 'Pago Móvil',
        ];
    }

    public static function normalizar(?string $metodo): string {
        $metodo = mb_strtolower(trim((string) $metodo), 'UTF-8');
        $metodo = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', ' ', '-'],
            ['a', 'e', 'i', 'o', 'u', 'u', '_', '_'],
            $metodo
        );
        return $metodo;
    }

    public static function esValido(?string $metodo): bool {
        return in_array(self::normalizar($metodo), self::all(), true);
    }

    public static function etiqueta(?string $metodo): string {
        $metodo = self::normalizar($metodo);
        return self::etiquetas()[$metodo] ?? $metodo;
    }

    /**
     * @return string[]
     */
    public static function bancosSinReferencia(?string $metodo): array {
        return self::BANCOS_SIN_REFERENCIA[self::normalizar($metodo)] ?? [];
    }

    /**
     * @return array<string, string[]> Mapa método => bancos sin referencia.
     */
    public static function excepcionesReferencia(): array {
        return self::BANCOS_SIN_REFERENCIA;
    }

    public static function requiereReferencia(?string $metodo, ?string $banco): bool {
        $metodo = self::normalizar($metodo);
        if (!in_array($metodo, self::all(), true)) {
            return true; // Fail closed: método desconocido exige referencia.
        }

        $bancos = self::bancosSinReferencia($metodo);
        if ($bancos === []) {
            return true;
        }

        $bancoNorm = self::normalizarBanco($banco);
        if ($bancoNorm === '') {
            return true;
        }

        foreach ($bancos as $bancoExento) {
            if (self::normalizarBanco($bancoExento) === $bancoNorm) {
                return false;
            }
        }

        return true;
    }

    public static function normalizarBanco(?string $banco): string {
        $banco = mb_strtolower(trim((string) $banco), 'UTF-8');
        $banco = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'u'],
            $banco
        );
        return preg_replace('/\s+/', ' ', $banco) ?? '';
    }
}
