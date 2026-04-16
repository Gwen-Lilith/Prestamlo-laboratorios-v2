<?php
/**
 * Conexión PDO singleton a MySQL
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        } catch (PDOException $e) {
            // No exponer detalles de conexión al usuario
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'      => false,
                'data'    => null,
                'mensaje' => 'Error de conexión a la base de datos.'
            ]);
            exit;
        }
    }

    /**
     * Obtener la instancia única de la conexión PDO
     * @return PDO
     */
    public static function getConnection() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    // Evitar clonación y deserialización
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception("No se puede deserializar un singleton");
    }
}
