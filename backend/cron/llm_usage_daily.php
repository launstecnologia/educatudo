<?php
/**
 * EducaTudo - Cron: Registro diário de custos de IA (llm_usage_log)
 *
 * Lê do banco as conversas e mensagens de IA do dia anterior, estima tokens e custo,
 * e registra/atualiza em llm_usage_log. Rodar todo dia à meia-noite.
 *
 * Configuração do Cron (todos os dias às 00:00):
 * 0 0 * * * /usr/bin/php /caminho/para/projeto/cron/llm_usage_daily.php
 *
 * Ou via web (para teste): acesse /cron/llm_usage_daily.php (recomenda-se proteger com token ou IP).
 */

$isCli = (php_sapi_name() === 'cli');
$basePath = dirname(__DIR__);

date_default_timezone_set('America/Sao_Paulo');
set_time_limit(120);

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

function logCron($msg) {
    global $isCli, $basePath;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    $logFile = $basePath . '/storage/logs/llm_usage_daily_' . date('Y-m-d') . '.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

// Bootstrap mínimo
if (!file_exists($basePath . '/vendor/autoload.php')) {
    logCron('ERRO: vendor/autoload.php não encontrado.');
    exit(1);
}
require_once $basePath . '/vendor/autoload.php';

if (!defined('FOLDER')) {
    define('FOLDER', '');
}
if (!defined('URL')) {
    define('URL', 'http://localhost');
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'production');
}

if (!file_exists($basePath . '/config/app.php')) {
    logCron('ERRO: config/app.php não encontrado.');
    exit(1);
}
require $basePath . '/config/app.php';

if (!file_exists($basePath . '/app/Core/Database.php')) {
    logCron('ERRO: app/Core/Database.php não encontrado.');
    exit(1);
}
require_once $basePath . '/app/Core/Database.php';
if (file_exists($basePath . '/app/Core/cron_multi_tenant_helper.php')) {
    require_once $basePath . '/app/Core/cron_multi_tenant_helper.php';
}

if (!defined('ENV_FILE_PATH')) {
    define('ENV_FILE_PATH', $basePath . '/.env');
}

// Data a processar: ontem (quando roda à meia-noite, processa o dia que acabou)
$date = date('Y-m-d', strtotime('-1 day'));

// Permitir passar data por argumento (cron) ou query string (web): php llm_usage_daily.php 2026-02-05
if ($isCli && isset($argv[1]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $argv[1])) {
    $date = $argv[1];
} elseif (!$isCli && !empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
    $date = $_GET['date'];
}

logCron("Processando data: {$date}");

try {
    $runLlm = function (?int $escolaId) use ($date) {
        $result = \App\Services\MetricsService::processDateForLLMUsage($date, 'computed');
        return $result;
    };
    if (class_exists('CronMultiTenantHelper')) {
        CronMultiTenantHelper::run(function (?int $escolaId) use ($runLlm) {
            $runLlm($escolaId);
        });
        $result = ['success' => true, 'message' => 'Processado para todas as escolas'];
    } else {
        $result = $runLlm(null);
    }
    if ($result['success']) {
        logCron("OK: " . $result['message']);
    } else {
        logCron("ERRO: " . $result['message']);
        exit(1);
    }
} catch (Throwable $e) {
    logCron("EXCEÇÃO: " . $e->getMessage());
    exit(1);
}
