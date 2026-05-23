<?php
require_once __DIR__ . '/Database.php';

function handleGetProducts(): void
{
    try {
        $pdo = Database::getInstance()->getConnection();
        echo json_encode(['success' => true, 'message' => 'Conexion exitosa']);
    } catch (\Exception $e) {
        echo json_encode([
            'success'    => false,
            'error_real' => $e->getMessage(),
            'host'       => getenv('DB_HOST'),
            'port'       => getenv('DB_PORT'),
            'user'       => getenv('DB_USER'),
        ]);
    }
}
