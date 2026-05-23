<?php
require_once __DIR__ . '/Database.php';

function handleGetProducts(): void
{
    $pdo = Database::getInstance()->getConnection();

    $sql = "
        SELECT
            id_producto,
            nombre,
            categoria,
            MIN(precio)       AS precio,
            descripcion,
            drive_folder_id,
            array_agg(DISTINCT talla ORDER BY talla) AS tallas_disponibles
        FROM productos
        WHERE activo = true
        GROUP BY id_producto, nombre, categoria, descripcion, drive_folder_id
        ORDER BY nombre ASC
    ";

    try {
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['precio'] = (float) $row['precio'];
            $raw = $row['tallas_disponibles'];
            if ($raw && $raw !== '{}') {
                $cleaned = trim($raw, '{}');
                $row['tallas_disponibles'] = array_map('trim', explode(',', $cleaned));
            } else {
                $row['tallas_disponibles'] = [];
            }
        }
        unset($row);

        echo json_encode([
            'success'  => true,
            'products' => $rows
        ]);

    } catch (PDOException $e) {
        error_log('[OpitaNoir products] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'errors'  => ['query' => 'Error al obtener los productos.']
        ]);
    }
}