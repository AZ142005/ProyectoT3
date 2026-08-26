<?php
namespace App\Core;

class UserRole {
    public const ADMIN = 'admin';
    public const RESIDENTE = 'residente';
    public const AUDITOR = 'auditor';

    public static function all(): array {
        return [
            self::ADMIN,
            self::RESIDENTE,
            self::AUDITOR,
        ];
    }
}
