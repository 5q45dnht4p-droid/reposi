<?php
// Router para PHP built-in server
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Se for arquivo estático, servir diretamente
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Todas as requisições vão para api_proxy.php
$_SERVER['SCRIPT_NAME'] = '/api_proxy.php';
require __DIR__ . '/api_proxy.php';
