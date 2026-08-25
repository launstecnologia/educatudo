<?php
/**
 * EducaTudo - Endpoint de Health Check
 * Verifica status do sistema e conectividade com banco de dados
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Configuração inicial
$startTime = microtime(true);
date_default_timezone_set('America/Sao_Paulo');

// Tornar o health check auto-suficiente (não depender do index.php)
// - Carregar autoload do Composer se existir
// - Garantir que constantes esperadas pelo config/app.php existam (sem fatal)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'production');
}
if (!defined('DEBUG')) {
    $debugEnv = getenv('DEBUG');
    if ($debugEnv === false || $debugEnv === null || $debugEnv === '') {
        $debugEnv = (ENVIRONMENT === 'development') ? 'true' : 'false';
    }
    define('DEBUG', strtolower((string)$debugEnv) === 'true');
}
if (!defined('FOLDER')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptPath = str_replace('\\', '/', dirname($scriptName));
    $folder = ($scriptPath === '/' || $scriptPath === '' || $scriptPath === '.') ? '' : $scriptPath;
    define('FOLDER', $folder);
}
if (!defined('URL')) {
    $https = $_SERVER['HTTPS'] ?? '';
    $protocol = (!empty($https) && $https !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    define('URL', $protocol . '://' . $host . FOLDER);
}

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Permitir CORS para monitoramento
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Resposta OPTIONS para preflight
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Função para resposta JSON
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// Função para erro
function sendError($message, $statusCode = 500) {
    $response = [
        'status' => 'error',
        'message' => $message,
        'timestamp' => time(),
        'response_time' => round(microtime(true) - $GLOBALS['startTime'], 4)
    ];
    sendResponse($response, $statusCode);
}

// Verificar método HTTP
if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    sendError('Método não permitido', 405);
}

// Teste básico de conectividade
$healthData = [
    'status' => 'ok',
    'timestamp' => time(),
    'server_time' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    'http_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'forwarded_host' => $_SERVER['HTTP_X_FORWARDED_HOST'] ?? null,
    'forwarded_proto' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
    'request_method' => $requestMethod,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

// Teste de memória
$healthData['memory_usage'] = [
    'current' => round(memory_get_usage() / 1024 / 1024, 2), // MB
    'peak' => round(memory_get_peak_usage() / 1024 / 1024, 2),     // MB
    'limit' => ini_get('memory_limit')
];

// Teste de carregamento de arquivos essenciais
$criticalFiles = [
    'config/app.php',
    'app/Core/Database.php',
    'app/Services/MetricsService.php',
    'composer.json'
];

$healthData['files'] = [];
foreach ($criticalFiles as $file) {
    $filePath = BASE_PATH . '/' . $file;
    $healthData['files'][$file] = [
        'exists' => file_exists($filePath),
        'readable' => is_readable($filePath),
        'size' => file_exists($filePath) ? filesize($filePath) : 0
    ];
}

// Verificar se algum arquivo crítico não existe
$missingFiles = array_filter($healthData['files'], function($file) {
    return !$file['exists'];
});

if (!empty($missingFiles)) {
    $healthData['status'] = 'warning';
    $healthData['warnings'][] = 'Arquivos críticos não encontrados: ' . implode(', ', array_keys($missingFiles));
}

// Teste de banco de dados
$dbStartTime = microtime(true);
$dbStatus = 'unknown';
$dbError = null;
$dbInfo = null;

try {
    // Carregar configuração
    $configFile = BASE_PATH . '/config/app.php';
    if (!file_exists($configFile)) {
        throw new Exception('Arquivo de configuração não encontrado');
    }

    $config = require $configFile;

    // Testar conexão com banco
    if (!isset($config['database'])) {
        throw new Exception('Configuração de banco de dados não encontrada');
    }

    $dbConfig = $config['database'];

    // Criar conexão PDO
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5, // Timeout de 5 segundos
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    // Executar query simples de teste
    $stmt = $pdo->query("SELECT 1 as test, NOW() as server_time, VERSION() as mysql_version");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $dbStatus = 'connected';
    $dbInfo = [
        'server_time' => $result['server_time'],
        'mysql_version' => $result['mysql_version'],
        'connection_time' => round(microtime(true) - $dbStartTime, 4)
    ];

    // Testar algumas tabelas críticas
    $criticalTables = ['usuarios', 'escolas', 'config_layout'];
    $dbInfo['tables'] = [];
    foreach ($criticalTables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            $exists = $stmt->rowCount() > 0;
            $dbInfo['tables'][$table] = $exists ? 'exists' : 'missing';
        } catch (Exception $e) {
            $dbInfo['tables'][$table] = 'error: ' . $e->getMessage();
        }
    }

    $pdo = null; // Fechar conexão

} catch (PDOException $e) {
    $dbStatus = 'error';
    $dbError = $e->getMessage();
} catch (Exception $e) {
    $dbStatus = 'error';
    $dbError = $e->getMessage();
}

$healthData['database'] = [
    'status' => $dbStatus,
    'connection_time' => round(microtime(true) - $dbStartTime, 4),
    'error' => $dbError,
    'info' => $dbInfo
];

// Se banco falhou, marcar como erro crítico
if ($dbStatus === 'error') {
    $healthData['status'] = 'error';
}

// Teste de Redis (crítico em multi-tenant para cache/sessão/config)
$redisStartTime = microtime(true);
$redisStatus = 'unknown';
$redisError = null;
$redisInfo = null;

try {
    $redisHost = function_exists('env') ? (string) env('REDIS_HOST', '127.0.0.1') : (getenv('REDIS_HOST') ?: '127.0.0.1');
    $redisPort = function_exists('env') ? (int) env('REDIS_PORT', 6379) : (int) (getenv('REDIS_PORT') ?: 6379);
    $redisPassword = function_exists('env') ? (string) env('REDIS_PASSWORD', '') : (getenv('REDIS_PASSWORD') ?: '');

    if (!class_exists('Redis')) {
        throw new Exception('Extensão Redis não carregada no PHP');
    }

    $redis = new Redis();
    $ok = @$redis->connect($redisHost, $redisPort, 1.0);
    if ($ok !== true) {
        throw new Exception("Falha ao conectar no Redis em {$redisHost}:{$redisPort}");
    }
    if ($redisPassword !== '' && @$redis->auth($redisPassword) !== true) {
        throw new Exception('Falha ao autenticar no Redis');
    }

    if (defined('Redis::OPT_READ_TIMEOUT')) {
        $redis->setOption(Redis::OPT_READ_TIMEOUT, 1.0);
    }

    $pong = $redis->ping();
    $pongOk = $pong === true || $pong === '+PONG' || $pong === 'PONG';
    if (!$pongOk) {
        throw new Exception('PING Redis retornou resposta inesperada');
    }

    $persistence = $redis->info('persistence') ?: [];
    $memory = $redis->info('memory') ?: [];
    $clients = $redis->info('clients') ?: [];

    $redisStatus = 'connected';
    $redisInfo = [
        'host' => $redisHost,
        'port' => $redisPort,
        'auth_configured' => $redisPassword !== '',
        'connection_time' => round(microtime(true) - $redisStartTime, 4),
        'used_memory_human' => $memory['used_memory_human'] ?? null,
        'connected_clients' => $clients['connected_clients'] ?? null,
        'aof_enabled' => $persistence['aof_enabled'] ?? null,
        'aof_last_write_status' => $persistence['aof_last_write_status'] ?? null,
        'rdb_last_bgsave_status' => $persistence['rdb_last_bgsave_status'] ?? null,
    ];

    $redis->close();
} catch (Throwable $e) {
    $redisStatus = 'error';
    $redisError = $e->getMessage();
}

$healthData['redis'] = [
    'status' => $redisStatus,
    'connection_time' => round(microtime(true) - $redisStartTime, 4),
    'error' => $redisError,
    'info' => $redisInfo
];

if ($redisStatus === 'error' && (($config['multi_tenant'] ?? false) === true || (function_exists('env') && env('MULTI_TENANT', false)))) {
    $healthData['status'] = $healthData['status'] === 'error' ? 'error' : 'warning';
    $healthData['warnings'][] = 'Redis indisponível em ambiente multi-tenant';
}

// Teste de sistema de arquivos (escrita)
$fsTest = BASE_PATH . '/storage/logs/health_test_' . time() . '.tmp';
$fsStatus = 'unknown';

try {
    $testDir = dirname($fsTest);
    if (!is_dir($testDir)) {
        mkdir($testDir, 0755, true);
    }

    $testData = 'Health check test - ' . date('Y-m-d H:i:s');
    $written = file_put_contents($fsTest, $testData);

    if ($written !== false && file_get_contents($fsTest) === $testData) {
        $fsStatus = 'ok';
        unlink($fsTest); // Limpar arquivo de teste
    } else {
        $fsStatus = 'write_error';
    }
} catch (Exception $e) {
    $fsStatus = 'error: ' . $e->getMessage();
}

$healthData['filesystem'] = [
    'status' => $fsStatus,
    'test_path' => str_replace(BASE_PATH, '', $fsTest)
];

// Teste de MetricsService (opcional)
$metricsStatus = 'unknown';
$metricsError = null;

try {
    if (class_exists('\\App\\Services\\MetricsService', false)) {
        $metrics = \App\Services\MetricsService::getMetrics();
        $metricsStatus = 'ok';

        // Adicionar algumas métricas básicas na resposta
        $healthData['metrics'] = [
            'ai_requests' => $metrics['metrics']['ai_requests'] ?? 0,
            'db_queries' => $metrics['metrics']['db_queries'] ?? 0,
            'last_updated' => $metrics['metrics']['last_updated'] ?? null
        ];
    } else {
        $metricsStatus = 'not_available';
    }
} catch (Exception $e) {
    $metricsStatus = 'error';
    $metricsError = $e->getMessage();
}

$healthData['metrics_service'] = [
    'status' => $metricsStatus,
    'error' => $metricsError
];

// Calcular tempo total de resposta
$healthData['response_time'] = round(microtime(true) - $startTime, 4);

// Determinar código HTTP baseado no status
$httpCode = 200;
if ($healthData['status'] === 'error') {
    $httpCode = 503; // Service Unavailable
} elseif ($healthData['status'] === 'warning') {
    $httpCode = 200; // Ainda OK, mas com avisos
}

// Enviar resposta
sendResponse($healthData, $httpCode);
