<?php
/**
 * Processa a fila de jobs de IA em todos os tenants.
 * Rodar a cada minuto via crontab:
 *   * * * * * php /www/wwwroot/master.educatudo.com/cron/process_ai_jobs.php >> /www/wwwroot/master.educatudo.com/storage/logs/ai_jobs_cron.log 2>&1
 *
 * Cada execução é registrada em cron_execucoes (banco master) — ver painel Master → Fila IA.
 */

// Composer PSR-4 (App\AI\AgenteIAInterface etc.) — necessário no CLI/cron,
// onde o index.php da web não roda e o autoload não está registrado.
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "ERRO: vendor/autoload.php não encontrado. Rode composer install.\n");
    exit(1);
}
require_once $autoload;

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/cron_multi_tenant_helper.php';
require_once __DIR__ . '/../app/Services/AIJobService.php';
require_once __DIR__ . '/../app/Services/CronExecucaoService.php';
require_once __DIR__ . '/../app/Services/MetricsService.php';
require_once __DIR__ . '/../app/Services/OpenAIService.php';
require_once __DIR__ . '/../app/Services/EssayAIService.php';
require_once __DIR__ . '/../app/Services/FlashcardService.php';
require_once __DIR__ . '/../app/Services/CustomExerciseImportService.php';
require_once __DIR__ . '/../app/Helpers/EssayTextStructureHelper.php';

date_default_timezone_set('America/Sao_Paulo');

$execucaoId = \App\Services\CronExecucaoService::iniciar(
    \App\Services\CronExecucaoService::SCRIPT_PROCESS_AI_JOBS
);

try {
    // forceIterateTenants=true garante que iteramos os bancos dos tenants
    // mesmo quando MULTI_TENANT=false no .env (cenário single-tenant com banco separado por escola)
    $report = CronMultiTenantHelper::runWithReport(function (?int $escolaId) {
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

        // Resolve listas de Exercícios Personalizados presas em 'gerando' — importa as que
        // já têm job pronto/falho, ou marca erro por timeout. Não depende do navegador do
        // aluno estar aberto (ver CustomExerciseImportService).
        try {
            $resolvidas = \CustomExerciseImportService::processarPendencias();
            if ($resolvidas > 0) {
                echo date('Y-m-d H:i:s') . " [escola={$escolaId}] {$resolvidas} lista(s) de exercícios resolvida(s)\n";
            }
        } catch (\Throwable $eImport) {
            echo date('Y-m-d H:i:s') . " [escola={$escolaId}] erro ao resolver exercícios: " . $eImport->getMessage() . "\n";
        }

        \App\Services\AIJobService::cleanup();

        return $processed;
    }, true);

    foreach ($report['detalhes'] as $detalhe) {
        \App\Services\CronExecucaoService::registrarEscola($execucaoId, $detalhe);
    }

    \App\Services\CronExecucaoService::finalizar($execucaoId, [
        'escolas_total' => (int) ($report['escolas_total'] ?? 0),
        'escolas_ok' => (int) ($report['escolas_ok'] ?? 0),
        'escolas_erro' => (int) ($report['escolas_erro'] ?? 0),
        'escolas_puladas' => (int) ($report['escolas_puladas'] ?? 0),
        'jobs_processados' => (int) ($report['jobs_processados'] ?? 0),
    ]);

    $jobs = (int) ($report['jobs_processados'] ?? 0);
    $erros = (int) ($report['escolas_erro'] ?? 0);
    if ($jobs > 0 || $erros > 0) {
        echo date('Y-m-d H:i:s')
            . " [resumo] jobs={$jobs} escolas_ok={$report['escolas_ok']} escolas_erro={$erros}\n";
    }
} catch (Throwable $e) {
    \App\Services\CronExecucaoService::falhar($execucaoId, $e->getMessage());
    fwrite(STDERR, date('Y-m-d H:i:s') . ' ERRO fatal process_ai_jobs: ' . $e->getMessage() . "\n");
    exit(1);
}
