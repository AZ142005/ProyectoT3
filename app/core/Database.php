<?php
namespace App\Core;

use PDO;
use PDOException;
use Exception;

class Database {
    private static $instance = null;
    
    // Deshabilitar instanciación externa
    private function __construct() {}
    private function __clone() {}
    
    /**
     * Retorna la única instancia de la conexión PDO (Singleton).
     *
     * @return PDO
     * @throws Exception
     */
    public static function getConnection() {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false, // Usar consultas preparadas reales de MySQL
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Manejo de errores según entorno
                if (ENVIRONMENT === 'development') {
                    throw new Exception("Error de Conexión Base de Datos: " . $e->getMessage());
                } else {
                    error_log("Error de Conexión BD: " . $e->getMessage());
                    throw new Exception("Error interno: No se pudo conectar a la base de datos.");
                }
            }
        }
        return self::$instance;
    }
}
