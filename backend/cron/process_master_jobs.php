<?php
/**
 * Processa a fila de jobs longos do Master (ex.: clonar escola).
 * Rodar a cada minuto via crontab:
 *   * * * * * php /caminho/projeto/backend/cron/process_master_jobs.php >> /caminho/projeto/backend/storage/logs/master_jobs_cron.log 2>&1
 *
 * Também é disparado em background ao enfileirar um job no painel.
 */

$basePath = dirname(__DIR__);
date_default_timezone_set('America/Sao_Paulo');

if (!defined('ENV_FILE_PATH')) {
    define('ENV_FILE_PATH', $basePath . '/.env');
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $basePath);
}
if (!defined('FOLDER')) {
    define('FOLDER', '');
}

$autoload = $basePath . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Services/MasterFilaService.php';

if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}
if (function_exists('ignore_user_abort')) {
    @ignore_user_abort(true);
}

$lockFile = $basePath . '/storage/tmp/process_master_jobs.lock';
$lockDir = dirname($lockFile);
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$lockFp = @fopen($lockFile, 'c');
if ($lockFp === false || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo date('Y-m-d H:i:s') . " outro worker já está processando a fila master\n";
    exit(0);
}

try {
    $processed = 0;
    $maxPerRun = 3;
    for ($i = 0; $i < $maxPerRun; $i++) {
        if (!MasterFilaService::processNext()) {
            break;
        }
        $processed++;
    }
    if ($processed > 0) {
        echo date('Y-m-d H:i:s') . " {$processed} job(s) do Master processado(s)\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, date('Y-m-d H:i:s') . ' ERRO fatal process_master_jobs: ' . $e->getMessage() . "\n");
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}

flock($lockFp, LOCK_UN);
fclose($lockFp);
exit(0);
