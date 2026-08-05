<?php
/**
 * EducaTudo - Script de Envio de Métricas
 * Executado via cron para enviar métricas agregadas para API central
 */

// Carregar autoload do Composer
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    logMessage("ERRO CRÍTICO: Diretório vendor não encontrado! Execute 'composer install' no servidor.", "ERROR");
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

// Configuração de timezone
date_default_timezone_set('America/Sao_Paulo');

// Evitar timeout
set_time_limit(300);
ini_set('max_execution_time', 300);

// Headers para evitar cache
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Função para log
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}\n";

    // Log no arquivo
    $logFile = __DIR__ . '/../storage/logs/send_metrics_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    // Log no output (para debug)
    echo $logEntry;
}

// Verificar se está sendo executado via linha de comando ou HTTP
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    logMessage("Script deve ser executado via linha de comando", "ERROR");
    exit(1);
}

// Garantir constantes esperadas pelo config/app.php quando rodar via CLI
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
    define('FOLDER', '');
}
if (!defined('URL')) {
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    define('URL', 'https://' . $host);
}

// Verificar argumentos da linha de comando
$force = in_array('--force', $argv) || in_array('-f', $argv);

// Carregar configurações
try {
    $configFile = __DIR__ . '/../config/app.php';
    if (!file_exists($configFile)) {
        throw new Exception("Arquivo de configuração não encontrado: {$configFile}");
    }
    $config = require $configFile;
} catch (Exception $e) {
    logMessage("Erro ao carregar configuração: " . $e->getMessage(), "ERROR");
    exit(1);
}

// Verificar se MetricsService está disponível
try {
    if (!class_exists('\\App\\Services\\MetricsService')) {
        logMessage("MetricsService não encontrado", "ERROR");
        exit(1);
    }
} catch (Exception $e) {
    logMessage("Erro ao verificar MetricsService: " . $e->getMessage(), "ERROR");
    exit(1);
}

// Obter métricas (em MULTI_TENANT o MetricsService usa a conexão do .env; crons por escola podem ser estendidos depois)
try {
    $metricsData = \App\Services\MetricsService::getMetrics();
} catch (Exception $e) {
    logMessage("Erro ao obter métricas: " . $e->getMessage(), "ERROR");
    exit(1);
}

// Verificar se há métricas para enviar
$metrics = $metricsData['metrics'] ?? [];
if (empty($metrics) || (!isset($metrics['ai_requests']) && !isset($metrics['db_queries']))) {
    logMessage("Nenhuma métrica para enviar");
    if (!$force) {
        exit(0);
    }
}

// Preparar payload no formato da API de monitoramento
$accessesByType = $metrics['accesses_by_type'] ?? [];
$accessLog = $metrics['access_log'] ?? [];
$accessLogRecent = array_slice($accessLog, -50);
// Filtrar endpoints ruidosos antes de enviar para monitoramento
$ignorePrefixes = [
    '/notifications/api/',
    '/admin/api/alunos-online',
    '/professor/api/alunos-online',
    '/aluno/api/alunos-online',
    '/api/alunos-online',
    '/notifications/api/stream',
    '/admin/api/alunos-online/stream',
    '/professor/api/alunos-online/stream'
];
$accessLogRecent = array_values(array_filter($accessLogRecent, function($entry) use ($ignorePrefixes) {
    $uri = strtolower((string)($entry['uri'] ?? ''));
    foreach ($ignorePrefixes as $prefix) {
        if (strpos($uri, $prefix) === 0) {
            return false;
        }
    }
    return true;
}));

// Garantir timestamps em UTC ISO (evita confusão de fuso na API)
$accessLogRecent = array_map(function($entry) {
    if (isset($entry['timestamp'])) {
        $entry['timestamp_utc'] = gmdate('c', (int) $entry['timestamp']);
    }
    return $entry;
}, $accessLogRecent);
$payload = [
    'requests_today' => $metrics['ai_requests'] ?? 0,
    'tokens_today' => $metrics['tokens_today'] ?? 0,
    'tokens_minute' => $metrics['tokens_minute'] ?? 0,
    'requests_minute' => $metrics['requests_minute'] ?? 0,
    'cost_today' => round($metrics['cost_today'] ?? 0, 4),
    'avg_request_time' => round($metrics['avg_request_time'] ?? 0, 4),
    'errors_ai' => $metrics['ai_errors'] ?? 0,
    'queries_today' => $metrics['db_queries'] ?? 0,
    'avg_db_time' => round($metrics['avg_db_time'] ?? 0, 4),
    'slow_queries' => $metrics['slow_queries'] ?? 0,
    'errors_db' => $metrics['db_errors'] ?? 0,
    'memory_peak' => round($metrics['memory_peak'] ?? 0, 2),
    'errors_500' => $metrics['errors_500'] ?? 0,
    'accesses_total' => $metrics['accesses_total'] ?? 0,
    'accesses_student' => $accessesByType['aluno'] ?? 0,
    'accesses_teacher' => $accessesByType['professor'] ?? 0,
    'accesses_admin' => $accessesByType['admin'] ?? 0,
    'accesses_recent' => $accessLogRecent
];

// Obter chave de autenticação (env do sistema ou .env via helper)
$schoolSecret = getenv('SCHOOL_SECRET_KEY');
if (empty($schoolSecret) && function_exists('env')) {
    $schoolSecret = env('SCHOOL_SECRET_KEY');
}
if (empty($schoolSecret)) {
    $schoolSecret = 'default_secret_key';
}
if (empty($schoolSecret) || $schoolSecret === 'default_secret_key') {
    logMessage("SCHOOL_SECRET_KEY não configurada, usando chave padrão", "WARNING");
}

// Endpoint da API central
$apiUrl = getenv('MONITOR_API_URL') ?: 'https://monitor.educatudo.com/api/metrics';

// Preparar requisição HTTP
$ch = curl_init($apiUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $schoolSecret,
        'User-Agent: EducaTudo-Monitor/1.0.0'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_VERBOSE => false
]);

// Executar requisição
$startTime = microtime(true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$requestTime = microtime(true) - $startTime;

curl_close($ch);

// Verificar resultado
if ($error) {
    exit(1);
}

if ($httpCode >= 200 && $httpCode < 300) {
    exit(0);

} else {
    logMessage("Erro na API (HTTP {$httpCode}): " . substr($response, 0, 500), "ERROR");

    // Se erro 401/403, pode ser problema de autenticação
    if ($httpCode === 401 || $httpCode === 403) {
        logMessage("Erro de autenticação - verificar SCHOOL_SECRET_KEY", "ERROR");
    }

    exit(1);
}
