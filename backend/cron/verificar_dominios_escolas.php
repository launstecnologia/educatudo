<?php
/**
 * Cron: verifica HTTPS dos domínios das escolas (wildcard + certificado na origem).
 *
 * Frequência recomendada: a cada 6 horas
 * 0 0,6,12,18 * * * /usr/bin/php /caminho/projeto/backend/cron/verificar_dominios_escolas.php >> /caminho/projeto/backend/storage/logs/cron_verificar_dominios.log 2>&1
 *
 * HTTP alternativo:
 * GET /master/escolas/verificar-dominios-cron?key=DOMINIO_VERIFICAR_CRON_KEY
 */

$basePath = dirname(__DIR__);
date_default_timezone_set('America/Sao_Paulo');

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Services/DominioEscolaService.php';

function logVerificarDominios(string $msg, string $basePath): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($basePath . '/storage/logs/cron_verificar_dominios.log', $line, FILE_APPEND);
    echo $line;
}

$limite = 50;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $limite = max(1, (int) $argv[1]);
}

logVerificarDominios('Início — verificar domínios (limite=' . $limite . ')', $basePath);

try {
    $db = Database::getInstance();
    $svc = new DominioEscolaService();
    $result = $svc->verificarEscolasPendentes($db, $limite);
    logVerificarDominios(
        sprintf(
            'Fim — processadas=%d ok=%d erros=%d',
            (int) ($result['processadas'] ?? 0),
            (int) ($result['ok'] ?? 0),
            (int) ($result['erros'] ?? 0)
        ),
        $basePath
    );
} catch (Throwable $e) {
    logVerificarDominios('FATAL: ' . $e->getMessage(), $basePath);
    exit(1);
}

exit(0);
