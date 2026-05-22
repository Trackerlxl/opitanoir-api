<?php
// Lee las variables de entorno que configuras en Railway
define('DB_HOST',     getenv('DB_HOST')     ?: 'aws-1-sa-east-1.pooler.supabase.com');
define('DB_PORT',     getenv('DB_PORT')     ?: '5432');
define('DB_NAME',     getenv('DB_NAME')     ?: 'postgres');
define('DB_USER',     getenv('DB_USER')     ?: 'postgres.gppkwktdipavxznhrikl');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');

define('N8N_WEBHOOK_URL', '');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}