<?php
/**
 * Cron: agrega KPIs do dashboard Master (logins, jornadas, provas, uso por módulo).
 * Recomendado: diariamente à meia-noite (America/Sao_Paulo)
 * 0 0 * * * /usr/bin/php /caminho/projeto/src/cron/master_dashboard_kpis.php >> /caminho/projeto/src/storage/logs/cron_master_dashboard_kpis.log 2>&1
 */

$isCli = php_sapi_name() === 'cli';
$basePath = dirname(__DIR__);

date_default_timezone_set('America/Sao_Paulo');

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';

$cronMultiTenantPath = $basePath . '/app/Core/cron_multi_tenant_helper.php';
if (file_exists($cronMultiTenantPath)) {
    require_once $cronMultiTenantPath;
}

require_once $basePath . '/app/Services/MasterDashboardKpisService.php';

function logMasterKpis(string $msg, string $basePath): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($basePath . '/storage/logs/cron_master_dashboard_kpis.log', $line, FILE_APPEND);
    echo $line;
}

$service = new MasterDashboardKpisService();
$porEscola = [];

$runner = function (?int $escolaId) use (&$porEscola, $service, $basePath): void {
    if ($escolaId === null || $escolaId < 1) {
        return;
    }
    try {
        $db = Database::getInstance();
        $dados = $service->agregarTenant($db);
        $dados['escola_id'] = $escolaId;
        $porEscola[] = $dados;
        logMasterKpis(
            "escola_id={$escolaId} logins={$dados['total_logins_sucesso']} jornadas={$dados['total_jornadas']} provas={$dados['total_provas']}",
            $basePath
        );
    } catch (Throwable $e) {
        logMasterKpis("Erro escola_id={$escolaId}: " . $e->getMessage(), $basePath);
    }
};

logMasterKpis('Início agregação KPIs Master', $basePath);

if (class_exists('CronMultiTenantHelper')) {
    CronMultiTenantHelper::run($runner, true);
} else {
    $runner(null);
}

try {
    $masterConfig = Database::getConfigFromEnv();
    $host = $masterConfig['host'] ?? '127.0.0.1';
    $port = (int) ($masterConfig['port'] ?? 3306);
    $name = $masterConfig['name'] ?? '';
    $user = $masterConfig['user'] ?? '';
    $pass = $masterConfig['pass'] ?? '';
    if ($name === '' || $user === '') {
        throw new RuntimeException('Configuração do banco master incompleta.');
    }
    $masterPdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $service->salvarNoMaster($masterPdo, $porEscola);
    logMasterKpis('Snapshot salvo no master (' . count($porEscola) . ' escolas)', $basePath);
} catch (Throwable $e) {
    logMasterKpis('Falha ao salvar no master: ' . $e->getMessage(), $basePath);
    if ($isCli) {
        exit(1);
    }
}

logMasterKpis('Fim agregação KPIs Master', $basePath);
