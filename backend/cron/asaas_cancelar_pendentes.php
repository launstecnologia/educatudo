<?php
/**
 * Cron: cancela cobranças Asaas pendentes há mais de 1 hora e marca compras_creditos como cancelled.
 * O aluno precisa comprar de novo (novo QR/fatura).
 *
 * Frequência recomendada: a cada 15 minutos
 * */15 * * * * /usr/bin/php /caminho/projeto/src/cron/asaas_cancelar_pendentes.php >> /caminho/projeto/src/storage/logs/cron_asaas_cancelar_pendentes.log 2>&1
 *
 * CLI opcional: php asaas_cancelar_pendentes.php [minutos]
 * Ex.: php asaas_cancelar_pendentes.php 60
 */

$basePath = dirname(__DIR__);
date_default_timezone_set('America/Sao_Paulo');

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Core/MasterSecretVault.php';
require_once $basePath . '/app/Core/MasterTenantConnection.php';
require_once $basePath . '/app/Services/CreditosAsaasCancelarPendentesService.php';

function logAsaasCancelar(string $msg, string $basePath): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($basePath . '/storage/logs/cron_asaas_cancelar_pendentes.log', $line, FILE_APPEND);
    echo $line;
}

$minutos = 60;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $minutos = max(1, (int) $argv[1]);
}

logAsaasCancelar("Início — cancelar pending com mais de {$minutos} min", $basePath);

try {
    $result = \App\Services\CreditosAsaasCancelarPendentesService::run($minutos);
    logAsaasCancelar(
        sprintf(
            'Fim — checked=%d cancelled=%d skipped=%d errors=%d',
            (int) ($result['checked'] ?? 0),
            (int) ($result['cancelled'] ?? 0),
            (int) ($result['skipped'] ?? 0),
            count($result['errors'] ?? [])
        ),
        $basePath
    );
    foreach (($result['errors'] ?? []) as $err) {
        logAsaasCancelar('ERR: ' . $err, $basePath);
    }
} catch (Throwable $e) {
    logAsaasCancelar('FATAL: ' . $e->getMessage(), $basePath);
    exit(1);
}

exit(0);
