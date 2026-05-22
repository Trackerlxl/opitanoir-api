<?php
// ============================================================
//  index.php — Router principal de la API Opita Noir
//
//  Rutas soportadas:
//    GET  /api/products  → products.php → handleGetProducts()
//    POST /api/orders    → orders.php   → handlePostOrder()
// ============================================================

require_once __DIR__ . '/config.php';   // Cabeceras CORS incluidas aquí

$method = $_SERVER['REQUEST_METHOD'];

// Extraer la ruta limpia, sin el prefijo /api/
// Funciona tanto con mod_rewrite como con php -S
$requestUri  = $_SERVER['REQUEST_URI'];
$scriptDir   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$path        = str_replace($scriptDir, '', parse_url($requestUri, PHP_URL_PATH));
$path        = strtolower(trim($path, '/'));

// Eliminar prefijo "api/" si lo contiene (cuando se sirve desde /api/)
$path = preg_replace('#^api/?#', '', $path);

// --- Despacho de rutas -------------------------------------------------------

switch (true) {

    // GET /api/products
    case $path === 'products' && $method === 'GET':
        require_once __DIR__ . '/products.php';
        handleGetProducts();
        break;

    // POST /api/orders
    case $path === 'orders' && $method === 'POST':
        require_once __DIR__ . '/orders.php';
        handlePostOrder();
        break;

    // Ruta no encontrada
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'errors'  => ['route' => "Ruta '$path' no encontrada con método $method."]
        ]);
        break;
}
