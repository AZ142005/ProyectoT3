<?php
namespace App\Core;

class Flash {
    private static $key = 'flash';

    public static function set(string $type, string $message): void {
        $_SESSION[self::$key][$type] = $message;
    }

    public static function success(string $message): void {
        self::set('success', $message);
    }

    public static function error(string $message): void {
        self::set('error', $message);
    }

    public static function info(string $message): void {
        self::set('info', $message);
    }

    public static function danger(string $message): void {
        self::set('error', $message);
    }

    public static function get(string $type): string {
        $message = $_SESSION[self::$key][$type] ?? '';
        unset($_SESSION[self::$key][$type]);
        return $message;
    }

    public static function all(): array {
        $messages = $_SESSION[self::$key] ?? [];
        unset($_SESSION[self::$key]);
        return $messages;
    }
}