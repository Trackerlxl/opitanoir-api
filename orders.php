<?php
// ============================================================
//  orders.php — POST /api/orders
//  Valida, inserta en ventas y notifica al webhook de n8n
// ============================================================

require_once __DIR__ . '/Database.php';

function handlePostOrder(): void
{
    // --- 1. Leer y decodificar el cuerpo JSON --------------------------------
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'errors'  => ['body' => 'El cuerpo de la petición debe ser JSON válido.']
        ]);
        return;
    }

    // --- 2. Validaciones del lado del servidor --------------------------------
    $errors = [];

    $nombre_cliente = trim($data['nombre_cliente'] ?? '');
    $telefono       = trim($data['telefono']       ?? '');
    $direccion      = trim($data['direccion']      ?? '');
    $id_producto    = trim($data['id_producto']    ?? '');
    $talla          = trim($data['talla']          ?? '');
    $cantidad       = isset($data['cantidad'])    ? (int) $data['cantidad']    : 0;
    $precio_total   = isset($data['precio_total']) ? (float) $data['precio_total'] : 0;

    if ($nombre_cliente === '') {
        $errors['nombre_cliente'] = 'El nombre completo es requerido.';
    }

    if ($telefono === '') {
        $errors['telefono'] = 'El teléfono de contacto es requerido.';
    } elseif (!preg_match('/^[0-9]+$/', $telefono)) {
        $errors['telefono'] = 'El teléfono debe contener únicamente números.';
    } elseif (strlen($telefono) < 10) {
        $errors['telefono'] = 'El teléfono debe tener un mínimo de 10 dígitos.';
    }

    if ($direccion === '') {
        $errors['direccion'] = 'La dirección de entrega es requerida.';
    }

    if ($id_producto === '') {
        $errors['id_producto'] = 'El ID de producto es requerido.';
    }

    if ($talla === '') {
        $errors['talla'] = 'La talla es requerida.';
    }

    if ($cantidad < 1) {
        $errors['cantidad'] = 'La cantidad debe ser al menos 1.';
    }

    if ($precio_total <= 0) {
        $errors['precio_total'] = 'El precio total debe ser mayor a 0.';
    }

    // Devolver errores de validación básica antes de tocar la BD
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => $errors]);
        return;
    }

    // --- 3. Verificar existencia y stock en la BD ----------------------------
    $pdo = Database::getInstance()->getConnection();

    try {
        $stmtCheck = $pdo->prepare("
            SELECT stock
            FROM productos
            WHERE id_producto = :id_producto
              AND talla       = :talla
              AND activo      = true
            LIMIT 1
        ");
        $stmtCheck->execute([
            ':id_producto' => $id_producto,
            ':talla'       => $talla,
        ]);
        $row = $stmtCheck->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'errors'  => ['id_producto' => "No existe el producto '$id_producto' con talla '$talla'."]
            ]);
            return;
        }

        if ((int) $row['stock'] < $cantidad) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'errors'  => ['cantidad' => "Stock insuficiente. Solo quedan {$row['stock']} unidades en talla $talla."]
            ]);
            return;
        }

        // --- 4. Insertar en ventas -------------------------------------------
        $stmtInsert = $pdo->prepare("
            INSERT INTO ventas
                (canal, id_producto, talla, cantidad, precio_cop,
                 nombre_cliente, telefono, direccion, estado, creado_en)
            VALUES
                ('web', :id_producto, :talla, :cantidad, :precio_cop,
                 :nombre_cliente, :telefono, :direccion, 'Pendiente', NOW())
            RETURNING id
        ");
        $stmtInsert->execute([
            ':id_producto'    => $id_producto,
            ':talla'          => $talla,
            ':cantidad'       => $cantidad,
            ':precio_cop'     => $precio_total,
            ':nombre_cliente' => $nombre_cliente,
            ':telefono'       => $telefono,
            ':direccion'      => $direccion,
        ]);

        $inserted  = $stmtInsert->fetch();
        $venta_id  = (int) $inserted['id'];

        // --- 5. Notificar al webhook de n8n ----------------------------------
        $payload = [
            'venta_id'       => $venta_id,
            'canal'          => 'web',
            'id_producto'    => $id_producto,
            'talla'          => $talla,
            'cantidad'       => $cantidad,
            'precio_cop'     => $precio_total,
            'nombre_cliente' => $nombre_cliente,
            'telefono'       => $telefono,
            'direccion'      => $direccion,
            'estado'         => 'Pendiente',
        ];

        notifyN8n($payload);

        // --- 6. Respuesta exitosa --------------------------------------------
        http_response_code(201);
        echo json_encode([
            'success'  => true,
            'venta_id' => $venta_id,
            'message'  => 'Pedido registrado'
        ]);

    } catch (PDOException $e) {
        error_log('[OpitaNoir orders] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'errors'  => ['db' => 'Error interno al registrar el pedido. Intente nuevamente.']
        ]);
    }
}

// ---------------------------------------------------------------------------
//  Función auxiliar: dispara el webhook de n8n de forma asíncrona con cURL
// ---------------------------------------------------------------------------
function notifyN8n(array $payload): void
{
    $url = N8N_WEBHOOK_URL;
    if (empty($url)) {
        error_log('[OpitaNoir n8n] N8N_WEBHOOK_URL no configurada.');
        return;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 8,          // No bloqueamos la respuesta al cliente más de 8s
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response   = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError  = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("[OpitaNoir n8n] cURL error: $curlError");
    } elseif ($httpStatus < 200 || $httpStatus >= 300) {
        error_log("[OpitaNoir n8n] HTTP $httpStatus — Response: $response");
    }
}
