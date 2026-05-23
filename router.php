<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

if ($uri === '' || $uri === 'index.php') {
    require __DIR__ . '/index.php';
} elseif (file_exists(__DIR__ . '/' . $uri)) {
    return false; // sirve el archivo tal cual
} else {
    require __DIR__ . '/index.php';
}