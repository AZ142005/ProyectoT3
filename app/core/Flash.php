<?php
namespace App\Core;

class Flash {
    private static $key = 'flash';

    public static function success($message) {
        $_SESSION[self::$key]['success'] = $message;
    }

    public static function error($message) {
        $_SESSION[self::$key]['error'] = $message;
    }

    public static function get($type) {
        $message = $_SESSION[self::$key][$type] ?? '';
        unset($_SESSION[self::$key][$type]);
        return $message;
    }

    public static function all() {
        $messages = $_SESSION[self::$key] ?? [];
        unset($_SESSION[self::$key]);
        return $messages;
    }
}