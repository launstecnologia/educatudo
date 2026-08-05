<?php
// Cron: atualiza notícias RSS (G1). Agendar: a cada 15 min = 0,15,30,45 * * * * php /caminho/cron/rss_update.php

$isCli = (php_sapi_name() === 'cli');
$basePath = dirname(__DIR__);

date_default_timezone_set('America/Sao_Paulo');
set_time_limit(120);

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>RSS Cron</title><style>body{font-family:system-ui,sans-serif;max-width:480px;margin:2rem auto;padding:1.5rem;}</style></head><body><h1>Atualização RSS</h1><p>Executando atualização das notícias...</p>';
    if (function_exists('ob_get_level')) {
        while (ob_get_level()) {
            ob_end_flush();
        }
    }
    flush();
}

if (!defined('FOLDER')) {
    define('FOLDER', '');
}
if (!defined('URL')) {
    define('URL', 'http://localhost');
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'production');
}
if (!defined('DEBUG')) {
    define('DEBUG', ENVIRONMENT === 'development');
}

$logDir = $basePath . '/storage/logs';
$logFile = $logDir . '/rss_import_' . date('Y-m-d') . '.log';

function logRss($msg) {
    global $logFile, $logDir, $isCli;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    if ($isCli) {
        echo $line;
    }
}

if (!file_exists($basePath . '/config/app.php')) {
    logRss('ERRO: config/app.php não encontrado.');
    if (!$isCli) {
        echo '</p><p><strong>Erro:</strong> config não encontrado. Ver storage/logs.</p></body></html>';
    }
    exit(1);
}
require $basePath . '/config/app.php';

if (!file_exists($basePath . '/app/Core/Database.php')) {
    logRss('ERRO: app/Core/Database.php não encontrado.');
    if (!$isCli) {
        echo '</p><p><strong>Erro:</strong> Database não encontrado. Ver storage/logs.</p></body></html>';
    }
    exit(1);
}
require_once $basePath . '/app/Core/Database.php';
if (file_exists($basePath . '/app/Core/cron_multi_tenant_helper.php')) {
    require_once $basePath . '/app/Core/cron_multi_tenant_helper.php';
}
if (!defined('ENV_FILE_PATH')) {
    define('ENV_FILE_PATH', $basePath . '/.env');
}

if (!file_exists($basePath . '/app/Services/RssService.php')) {
    logRss('ERRO: app/Services/RssService.php não encontrado.');
    if (!$isCli) {
        echo '</p><p><strong>Erro:</strong> RssService não encontrado. Ver storage/logs.</p></body></html>';
    }
    exit(1);
}
require_once $basePath . '/app/Services/RssService.php';

try {
    $runRss = function (?int $escolaId) {
        $service = new RssService();
        return $service->importarTodos();
    };
    if (class_exists('CronMultiTenantHelper')) {
        CronMultiTenantHelper::run(function (?int $escolaId) use ($runRss) {
            $runRss($escolaId);
        });
        logRss('OK: RSS processado para todas as escolas.');
    } else {
        $result = $runRss(null);
        logRss('OK: ' . $result['inseridas'] . ' notícia(s) inserida(s).');
        if (!empty($result['erros'])) {
            foreach ($result['erros'] as $feed => $erro) {
                logRss("Erro feed {$feed}: {$erro}");
            }
        }
    }
} catch (Exception $e) {
    logRss('ERRO: ' . $e->getMessage());
    if (!$isCli) {
        echo '</p><p><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</p><p>Ver storage/logs/rss_import_*.log</p></body></html>';
    }
    exit(1);
}

if (!$isCli) {
    $inseridas = isset($result) ? $result['inseridas'] : 0;
    $temErros = !empty($result['erros']);
    echo '<p><strong>Concluído.</strong> <strong>' . (int) $inseridas . '</strong> notícia(s) inserida(s).</p>';
    if ($temErros) {
        echo '<p>Alguns feeds tiveram erro (ver <code>storage/logs/rss_import_*.log</code>).</p>';
    }
    echo '<p><small>Recomendado: executar via cron (a cada 15 min), não pelo navegador.</small></p>';
    echo '<p><a href="' . (isset($_SERVER['REQUEST_URI']) ? dirname($_SERVER['REQUEST_URI']) . '/' : '') . '">Voltar aos scripts de cron</a></p>';
    echo '</body></html>';
}
