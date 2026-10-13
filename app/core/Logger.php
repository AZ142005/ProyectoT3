<?php
namespace App\Core;

/**
 * Structured Logger
 * Emite logs estructurados en formato JSON de una sola línea vía error_log.
 */
class Logger {
    private static ?string $requestId = null;

    public static function getRequestId(): string {
        if (self::$requestId === null) {
            try {
                self::$requestId = bin2hex(random_bytes(8));
            } catch (\Throwable $e) {
                self::$requestId = substr(md5(uniqid((string)mt_rand(), true)), 0, 16);
            }
        }
        return self::$requestId;
    }

    public static function info(string $action, string $message, array $context = []): void {
        self::log('info', $action, $message, $context);
    }

    public static function warning(string $action, string $message, array $context = []): void {
        self::log('warning', $action, $message, $context);
    }

    public static function error(string $action, string $message, array $context = []): void {
        self::log('error', $action, $message, $context);
    }

    public static function log(string $level, string $action, string $message, array $context = []): void {
        $entry = [
            'timestamp'  => date('c'),
            'level'      => $level,
            'request_id' => self::getRequestId(),
            'user_id'    => $_SESSION['auth_user']['id'] ?? null,
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'uri'        => $_SERVER['REQUEST_URI'] ?? 'cli',
            'action'     => $action,
            'message'    => $message,
            'context'    => $context,
        ];

        error_log('[STRUCTURED_LOG] ' . json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
