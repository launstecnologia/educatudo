<?php
/**
 * Processa a fila de jobs de IA em todos os tenants.
 * Rodar a cada minuto via crontab:
 *   * * * * * php /www/wwwroot/master.educatudo.com/cron/process_ai_jobs.php >> /www/wwwroot/master.educatudo.com/storage/logs/ai_jobs_cron.log 2>&1
 */

// Composer PSR-4 (App\AI\AgenteIAInterface etc.) — necessário no CLI/cron,
// onde o index.php da web não roda e o autoload não está registrado.
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "ERRO: vendor/autoload.php não encontrado. Rode composer install.\n");
    exit(1);
}
require_once $autoload;

require_once __DIR__ . '/../app/Core/cron_multi_tenant_helper.php';
require_once __DIR__ . '/../app/Services/AIJobService.php';
require_once __DIR__ . '/../app/Services/MetricsService.php';
require_once __DIR__ . '/../app/Services/OpenAIService.php';
require_once __DIR__ . '/../app/Services/EssayAIService.php';
require_once __DIR__ . '/../app/Services/FlashcardService.php';
require_once __DIR__ . '/../app/Helpers/EssayTextStructureHelper.php';

// forceIterateTenants=true garante que iteramos os bancos dos tenants
// mesmo quando MULTI_TENANT=false no .env (cenário single-tenant com banco separado por escola)
CronMultiTenantHelper::run(function (?int $escolaId) {
    $processed = 0;
    $maxPerRun = 5;

    for ($i = 0; $i < $maxPerRun; $i++) {
        if (!\App\Services\AIJobService::processNext()) {
            break;
        }
        $processed++;
    }

    if ($processed > 0) {
        echo date('Y-m-d H:i:s') . " [escola={$escolaId}] {$processed} job(s) processado(s)\n";
    }

    \App\Services\AIJobService::cleanup();
}, true);
