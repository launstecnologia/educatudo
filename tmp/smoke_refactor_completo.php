<?php
/**
 * Smoke CLI: classes + ControllerLocator após refactor Opção A / Agentes / API / PWA.
 */
declare(strict_types=1);

$root = dirname(__DIR__) . '/backend';
require $root . '/vendor/autoload.php';
require_once $root . '/app/Core/ControllerLocator.php';

$fail = 0;
$ok = 0;

function check(bool $cond, string $label): void
{
    global $fail, $ok;
    if ($cond) {
        echo "OK   $label\n";
        $ok++;
    } else {
        echo "FAIL $label\n";
        $fail++;
    }
}

echo "=== Classes (novas + Compat) ===\n";
$classes = [
    'App\\Agentes\\AgenteIAInterface',
    'App\\Agentes\\TudinhaChatService',
    'App\\Agentes\\ExecutorPipeline',
    'App\\Agentes\\ModeloIA',
    'App\\Agentes\\InterpretadorPedidoFlashcardAgent',
    'App\\Agentes\\Questoes\\GeradorQuestaoAgent',
    'App\\Agentes\\Tudinha\\MontadorContextoTudinhaAgent',
    'App\\Agentes\\Suporte\\CarregadorPromptTudinha',
    'App\\AI\\TudinhaChatService',
    'App\\AI\\Agentes\\InterpretadorPedidoFlashcardAgent',
    'App\\API\\Controllers\\AuthController',
    'App\\API\\Controllers\\V1\\ApiStatusController',
    'App\\Api\\Controllers\\AuthController',
    'App\\PWA\\Controllers\\PwaController',
    'App\\Pwa\\Controllers\\PwaController',
    'App\\Infra\\Services\\AIJobService',
    'App\\Infra\\Services\\OpenAIService',
    'App\\Infra\\Services\\TudinhaService',
    'App\\Services\\AIJobService',
    'App\\Services\\OpenAIService',
    'App\\Publico\\Controllers\\HistoricoValidacaoController',
    'App\\Painel\\Admin\\Controllers\\AdminBaseController',
    'App\\Painel\\Auth\\Controllers\\AuthController',
    'App\\Painel\\User\\Controllers\\StudentController',
    'App\\Dominio\\Pessoas\\Controllers\\StudentAdminController',
    'App\\Modulos\\EducaHits\\Controllers\\EducaHitsPortalController',
    'App\\Modulos\\EducaLabs\\Controllers\\EducaLabsController',
    'App\\Modulos\\ExternalApps\\Controllers\\ExternalAppsController',
    'App\\Modulos\\Jogos\\Controllers\\GameController',
    'App\\Modulos\\Notes\\Controllers\\NotesController',
];

foreach ($classes as $c) {
    $exists = class_exists($c) || interface_exists($c) || trait_exists($c);
    check($exists, $c);
}

echo "\n=== Pipeline Tudinha ===\n";
try {
    $pipe = App\Agentes\TudinhaChatPipeline::agentesChat();
    check(count($pipe) === 3, 'agentesChat count=3');
    check(get_class($pipe[0]) === 'App\\Agentes\\Tudinha\\MontadorContextoTudinhaAgent', 'primeiro agente Tudinha');
} catch (Throwable $e) {
    check(false, 'pipeline: ' . $e->getMessage());
}

echo "\n=== ControllerLocator ===\n";
$handlers = [
    'Api/AuthController',
    'Api/V1/ApiStatusController',
    'API/AuthController',
    'Pwa/PwaController',
    'PWA/PwaController',
    'Public/HistoricoValidacaoController',
    'Admin/StudentAdminController',
    'Admin/AdminCreditosController',
    'Auth/AuthController',
    'User/StudentController',
    'EducaHits/EducaHitsPortalController',
    'EducaLabs/EducaLabsController',
    'ExternalApps/ExternalAppsController',
    'Games/GameController',
    'Games/MillionGameController',
    'Notes/NotesController',
    'Notes/NotesTokenController',
    'AIJobController',
    'Admin/EnrollmentAdminController',
    'Public/EnrollmentPublicController',
    'Admin/FinanceController',
    'Admin/ComplianceController',
];

foreach ($handlers as $h) {
    $r = App\Core\ControllerLocator::resolve($h);
    $label = $h . ' → ' . ($r['fqcn'] ?? 'NULL');
    check($r !== null && is_file($r['path']), $label);
}

echo "\n=== Pastas legadas ausentes ===\n";
foreach (['Controllers', 'Models', 'Services', 'AI', 'Api', 'Pwa'] as $dir) {
    // case-sensitive check via readdir
    $found = false;
    foreach (scandir($root . '/app') as $entry) {
        if ($entry === $dir) {
            $found = true;
            break;
        }
    }
    check(!$found, "app/$dir NÃO existe");
}

echo "\n=== Pastas novas presentes ===\n";
foreach (['API', 'PWA', 'Agentes', 'Publico', 'Dominio', 'Modulos', 'Painel', 'Infra', 'Compat'] as $dir) {
    check(is_dir($root . '/app/' . $dir), "app/$dir existe");
}

echo "\nResumo CLI: OK=$ok FAIL=$fail\n";
exit($fail > 0 ? 1 : 0);
