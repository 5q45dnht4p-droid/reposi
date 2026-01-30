<?php
/**
 * PROXY API - Intermediário para servidor com OpenSSL antigo
 * 
 * Hospede este arquivo em servidor moderno (PHP 7.4+)
 * Exemplos: Render.com, Railway.app, InfinityFree, etc.
 * 
 * URL de acesso: https://seuservidor.com/api_proxy.php
 */

// Configurações de erro e timeout
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros em produção
ini_set('log_errors', 1);
set_time_limit(30);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Tratamento de erro global
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'error' => 'PHP Error',
        'message' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

// Configurações da API Joinner
define('API_BASE_URL', 'https://api.joinner.com.br/api/v1');
define('API_EMAIL', 'ti@multimedsp.com.br');
define('API_PASSWORD', 'api03245');

// Cache de token (válido por 50 minutos)
$tokenCacheFile = __DIR__ . '/proxy_token_cache.json';
$tokenCacheDuration = 3000; // 50 minutos

// Verificar se diretório tem permissão de escrita
if (!is_writable(__DIR__)) {
    http_response_code(500);
    echo json_encode(['error' => 'Directory not writable', 'dir' => __DIR__]);
    exit;
}

function getToken() {
    global $tokenCacheFile, $tokenCacheDuration;
    
    try {
        // Verificar cache
        if (file_exists($tokenCacheFile)) {
            $cache = json_decode(file_get_contents($tokenCacheFile), true);
            if ($cache && isset($cache['timestamp']) && (time() - $cache['timestamp']) < $tokenCacheDuration) {
                return $cache['token'];
            }
        }
        
        // Fazer login
        $ch = curl_init(API_BASE_URL . '/login');
        if (!$ch) {
            return null;
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'email' => API_EMAIL,
            'password' => API_PASSWORD
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['token'])) {
                // Salvar em cache
                @file_put_contents($tokenCacheFile, json_encode([
                    'token' => $data['token'],
                    'timestamp' => time()
                ]));
                return $data['token'];
            }
        }
    } catch (Exception $e) {
        return null;
    }
    
    return null;
}

function proxyRequest($url, $token) {
    try {
        $ch = curl_init($url);
        if (!$ch) {
            return ['status' => 500, 'data' => null, 'error' => 'CURL init failed'];
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'status' => $httpCode ?: 500,
            'data' => ($httpCode === 200 && $response) ? json_decode($response, true) : null,
            'error' => $httpCode !== 200 ? $error : null
        ];
    } catch (Exception $e) {
        return ['status' => 500, 'data' => null, 'error' => $e->getMessage()];
    }
}

// Processar requisição
$action = isset($_GET['action']) ? $_GET['action'] : null;

try {
    if ($action === 'query') {
        $queryId = isset($_GET['query_id']) ? $_GET['query_id'] : null;
        $params = $_GET;
        unset($params['action']);
        
        $token = getToken();
        if (!$token) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to authenticate']);
            exit;
        }
        
        $url = API_BASE_URL . '/queries?' . http_build_query($params);
        $result = proxyRequest($url, $token);
        
        http_response_code($result['status']);
        echo json_encode($result['data'] ? $result['data'] : ['error' => $result['error']]);
        
    } elseif ($action === 'test') {
        echo json_encode([
            'status' => 'ok',
            'php_version' => PHP_VERSION,
            'openssl_version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'N/A',
            'curl_version' => function_exists('curl_version') ? curl_version()['version'] : 'N/A',
            'writable' => is_writable(__DIR__),
            'time' => date('Y-m-d H:i:s'),
            'server' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown'
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid action',
            'usage' => [
                'test' => '?action=test',
                'query' => '?action=query&query_id=301&codigo_cirurgia=130000'
            ]
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Exception',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
