<?php
/**
 * Remonta o print Santa Maria (regra 25) e simula Língua Portuguesa da Ana vs oficial.
 *
 * Uso: docker exec php_app_educatudo php scripts/remonta_simula_sm_ana.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';

use App\Core\Database;
use App\Models\System\BoletimConfig;
use App\Modulos\Boletins\Controllers\BoletimConfigController;
use App\Modulos\Boletins\Services\BoletimAssistenteFerramentas;
use App\Modulos\Boletins\Services\BoletimAssistenteWizard;

const TENANT_DB = 'educatudo_educa';
const REGRA_ID = 25;
const ALUNO_ID = 361;
const MATERIA_PT = 78;

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');

$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . TENANT_DB . ';charset=utf8mb4';
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
Database::setCurrentInstance(Database::createFromPdo($pdo, [
    'host' => $host,
    'port' => $port,
    'name' => TENANT_DB,
    'user' => $dbUser,
    'pass' => $dbPass,
]));

$ferramentas = new BoletimAssistenteFerramentas();
$wizard = new BoletimAssistenteWizard($ferramentas);
$model = new BoletimConfig();

$estado = [
    'modo' => 'editar',
    'regra_id' => REGRA_ID,
    'origem' => 'santa-maria',
    'modelo_santa_maria' => 1,
    'exibir_em' => 'boletim',
    'ano_letivo' => 2024,
    'nome' => 'Aprovação Santa Maria 2024 — print Santa Maria',
    'fontes_bimestres' => [1 => 18, 2 => 19, 3 => 20, 4 => 21],
    'fontes_faltas' => [],
    // REC 1º / REC 2º / PF (bimestre 81/82/90 no seed)
    'eventos_avaliativos_ids' => [51, 52, 53],
    'linhas_layout' => [],
    'cabecalhos_manuais' => 0,
    'round_mode' => 'half',
    'decimal_places' => 1,
];

$out = $wizard->montar($estado);
if (empty($out['ok']) || !is_array($out['rascunho'] ?? null)) {
    fwrite(STDERR, "Montagem falhou:\n" . json_encode($out['erros'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

$rascunho = $out['rascunho'];
$comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
echo 'comps=' . count($comps) . ' round=' . ($rascunho['round_mode'] ?? '') . ' dec=' . ($rascunho['decimal_places'] ?? '') . "\n";
foreach ($comps as $c) {
    $cod = (string) ($c['codigo'] ?? '');
    $src = (string) ($c['source_type'] ?? '');
    $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
    $extra = '';
    if ($src === 'calculado') {
        $extra = ' expr=' . ($cfg['expressao'] ?? '') . ' rm=' . ($cfg['round_mode_override'] ?? 'herdar');
    } elseif ($src === 'evento_boletim') {
        $extra = ' ref=' . ($cfg['regra_codigo'] ?? '') . '.' . ($cfg['componente_codigo'] ?? '');
    } elseif (!empty($cfg['evento_avaliativo_id'])) {
        $extra = ' ev=' . (int) $cfg['evento_avaliativo_id'];
    }
    echo "  {$cod}:{$src}{$extra}\n";
}

$ctrl = new BoletimConfigController(true);
$refEncode = new ReflectionClass($ctrl);
$methodEncode = $refEncode->getMethod('encodeComponenteConfigJsonForSave');
$methodEncode->setAccessible(true);

$compsSave = [];
foreach ($comps as $c) {
    if (!is_array($c)) {
        continue;
    }
    $src = (string) ($c['source_type'] ?? 'nenhuma');
    $cfgJson = $methodEncode->invoke($ctrl, $src, $c);
    $compsSave[] = [
        'codigo' => (string) ($c['codigo'] ?? ''),
        'nome' => (string) ($c['nome'] ?? ''),
        'source_type' => $src,
        'calc_type' => (string) ($c['calc_type'] ?? 'media'),
        'peso' => (float) ($c['peso'] ?? 1),
        'filtro_titulo' => (string) ($c['filtro_titulo'] ?? ''),
        'blocos_ids' => $c['blocos_ids'] ?? null,
        'config_json' => $cfgJson,
        'materias_ids' => is_array($c['materias_ids'] ?? null)
            ? json_encode($c['materias_ids'], JSON_UNESCAPED_UNICODE)
            : ($c['materias_ids'] ?? null),
        'usar_percentual' => !empty($c['usar_percentual']) ? 1 : 0,
        'escala_max' => (float) ($c['escala_max'] ?? 10),
        'obrigatorio' => !empty($c['obrigatorio']) ? 1 : 0,
    ];
}

$extras = [
    'layout_boletim_id' => 19,
    'quadro_livre' => 0,
    'origem' => 'santa-maria',
    'modelo_santa_maria' => 1,
    'fontes_bimestres' => [1 => 18, 2 => 19, 3 => 20, 4 => 21],
    'linhas_layout' => $rascunho['linhas_layout'] ?? [],
    'eventos_avaliativos_ids' => [51, 52, 53],
];

$saved = $model->saveRule(
    (string) $rascunho['nome'],
    (string) ($rascunho['formula_final'] ?? 'mf'),
    $compsSave,
    REGRA_ID,
    null,
    null,
    null,
    (string) ($rascunho['codigo'] ?? ''),
    null,
    null,
    'boletim',
    2024,
    null,
    1,
    1,
    1,
    (string) ($rascunho['round_mode'] ?? 'half'),
    (int) ($rascunho['decimal_places'] ?? 1),
    null,
    null,
    (float) ($rascunho['nota_minima_aprovacao'] ?? 7),
    1,
    json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    'oficial'
);
echo "saved_id={$saved}\n";

$regra = $model->getRuleById(REGRA_ID);
if (!is_array($regra)) {
    fwrite(STDERR, "Regra 25 não encontrada após save.\n");
    exit(1);
}
echo 'persistidos=' . count((array) ($regra['componentes'] ?? [])) . ' round=' . ($regra['round_mode'] ?? '') . "\n";
$extrasDb = json_decode((string) ($regra['extras_json'] ?? ''), true);
$evIds = is_array($extrasDb['eventos_avaliativos_ids'] ?? null) ? $extrasDb['eventos_avaliativos_ids'] : [];
echo 'eventos_ids=' . json_encode($evIds) . "\n";
$recOk = in_array(51, $evIds, true) && in_array(52, $evIds, true) && in_array(53, $evIds, true);
echo 'rec_pf_marcados=' . ($recOk ? 'OK' : 'FAIL') . "\n";

$slotsRec = ['s1_rec' => false, 's2_rec' => false, 'pf' => false];
foreach ((array) ($regra['componentes'] ?? []) as $c) {
    $cod = strtolower((string) ($c['codigo'] ?? ''));
    if (!isset($slotsRec[$cod])) {
        continue;
    }
    $cfg = json_decode((string) ($c['config_json'] ?? ''), true);
    $eid = (int) ($cfg['evento_avaliativo_id'] ?? 0);
    $slotsRec[$cod] = $eid > 0 ? $eid : false;
}
echo 'slots_rec=' . json_encode($slotsRec) . "\n";

$sim = $ctrl->simularRegraAluno($regra, ALUNO_ID, 'RANGE:2024-01-01:2024-12-31');
$matriz = is_array($sim['matriz_materias'] ?? null) ? $sim['matriz_materias'] : [];
$notas = [];
$nomePt = '';
foreach ((array) ($matriz['linhas'] ?? []) as $lin) {
    if (!is_array($lin)) {
        continue;
    }
    $mid = (int) ($lin['materia_id'] ?? 0);
    $nome = (string) ($lin['materia_nome'] ?? '');
    if ($mid === MATERIA_PT || $mid === 31 || stripos($nome, 'Portugu') !== false) {
        $nomePt = $nome . ' #' . $mid;
        $raw = is_array($lin['notas'] ?? null) ? $lin['notas'] : [];
        foreach ($raw as $cod => $v) {
            if (is_numeric($v)) {
                $notas[(string) $cod] = (float) $v;
            }
        }
        break;
    }
}
echo 'pt_linha=' . ($nomePt !== '' ? $nomePt : 'NAO_ACHADA') . "\n";
if ($notas === []) {
    foreach ((array) ($matriz['linhas'] ?? []) as $lin) {
        echo 'mid=' . (int) ($lin['materia_id'] ?? 0) . ' nome=' . (string) ($lin['materia_nome'] ?? '') . "\n";
    }
}
echo "\n=== Língua Portuguesa Ana (simulação) ===\n";
$chaves = [
    'b1_pp', 'b1_pu', 'b1_pe', 'b1_mu',
    'b2_pp', 'b2_pu', 'b2_pe', 'b2_mu',
    's1_rec', 's1_ms',
    'b3_pp', 'b3_pu', 'b3_pe', 'b3_mu',
    'b4_pp', 'b4_pu', 'b4_pe', 'b4_mu',
    's2_rec', 's2_ms', 'ma', 'pf', 'mf',
];
foreach ($chaves as $k) {
    $v = $notas[$k] ?? null;
    echo sprintf("%-8s %s\n", $k, $v === null ? 'null' : (string) $v);
}

// Oficial (manual + boletim anexo): PP/PU sem half; MU sem half; MS com half.
$esperado = [
    'b1_pp' => 8.5,
    'b1_pu' => 7.5,
    'b1_mu' => 8.0,
    'b2_pp' => 7.0,
    'b2_pu' => 8.8,
    'b2_mu' => 7.9,
    's1_ms' => 8.0,
    'b3_pp' => 8.5,
    'b3_pu' => 7.1,
    'b3_mu' => 7.8,
];
echo "\n=== Conferência vs oficial ===\n";
$fail = 0;
foreach ($esperado as $k => $esp) {
    $v = $notas[$k] ?? null;
    $num = is_numeric($v) ? (float) $v : null;
    // Tolerância estreita: 8.8≠9 e 7.95≠8.0 precisam falhar se half/none errarem.
    $ok = $num !== null && abs($num - $esp) < 0.011;
    if (!$ok) {
        $fail++;
    }
    echo sprintf("%-8s obtido=%s esperado=%s %s\n", $k, $num === null ? 'null' : (string) $num, (string) $esp, $ok ? 'OK' : 'FAIL');
}
exit($fail > 0 ? 2 : 0);
