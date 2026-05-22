<?php
// ============================================================
//  Database.php — Singleton PDO para PostgreSQL (Supabase)
// ============================================================

require_once __DIR__ . '/config.php';

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'errors'  => ['db' => 'No se pudo conectar a la base de datos. Intente nuevamente.']
            ]);
            // No exponer detalles de la excepción al cliente
            error_log('[OpitaNoir DB] ' . $e->getMessage());
            exit;
        }
    }

    // Evitar clonación y deserialización
    private function __clone() {}
    public function __wakeup() {}

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}
