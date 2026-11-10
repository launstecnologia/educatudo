<?php
/**
 * Completa a regra 29 (impressão do zero) no print Santa Maria completo:
 * I–IV bim (PP/PU/CH/PE/MU/FAL) + REC/MS + MA/PF/MF/situação.
 *
 * Uso: docker exec php_app_educatudo php scripts/completa_regra_29_sm.php
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
const REGRA_ID = 29;

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');

$pdo = new PDO(
    'mysql:host=' . $host . ';port=' . $port . ';dbname=' . TENANT_DB . ';charset=utf8mb4',
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
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

$atual = $model->getRuleById(REGRA_ID);
if (!is_array($atual)) {
    fwrite(STDERR, "Regra 29 não encontrada.\n");
    exit(1);
}
$extrasAtuais = json_decode((string) ($atual['extras_json'] ?? ''), true);
if (!is_array($extrasAtuais)) {
    $extrasAtuais = [];
}

$estado = [
    'modo' => 'editar',
    'regra_id' => REGRA_ID,
    'origem' => 'santa-maria',
    'modelo_santa_maria' => 1,
    'exibir_em' => 'boletim',
    'ano_letivo' => 2024,
    'nome' => 'Aprovação Santa Maria 2024 — impressão (do zero)',
    'codigo' => (string) ($atual['codigo'] ?? ''),
    'fontes_bimestres' => [1 => 18, 2 => 19, 3 => 20, 4 => 21],
    'fontes_faltas' => [1 => 1, 2 => 3, 3 => 2, 4 => 4],
    'eventos_avaliativos_ids' => [51, 52, 53],
    'linhas_layout' => [],
    'cabecalhos_manuais' => 0,
    'quadro_livre' => 0,
    'round_mode' => 'half',
    'decimal_places' => 1,
    'nota_minima_aprovacao' => (float) ($atual['nota_minima_aprovacao'] ?? 7),
];

$out = $wizard->montar($estado);
if (empty($out['ok']) || !is_array($out['rascunho'] ?? null)) {
    fwrite(STDERR, "Montagem falhou:\n" . json_encode($out['erros'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    exit(1);
}

$rascunho = $out['rascunho'];
$comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
echo 'comps=' . count($comps) . ' round=' . ($rascunho['round_mode'] ?? '') . "\n";
foreach ($comps as $c) {
    $cod = (string) ($c['codigo'] ?? '');
    $src = (string) ($c['source_type'] ?? '');
    $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
    $extra = '';
    if ($src === 'calculado') {
        $extra = ' expr=' . ($cfg['expressao'] ?? '');
    } elseif ($src === 'evento_boletim') {
        $extra = ' ref=' . ($cfg['regra_codigo'] ?? '') . '.' . ($cfg['componente_codigo'] ?? '');
    } elseif ($src === 'faltas_evento') {
        $extra = ' fal=' . (int) ($cfg['faltas_evento_id'] ?? 0);
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
    $compsSave[] = [
        'codigo' => (string) ($c['codigo'] ?? ''),
        'nome' => (string) ($c['nome'] ?? ''),
        'source_type' => $src,
        'calc_type' => (string) ($c['calc_type'] ?? 'media'),
        'peso' => (float) ($c['peso'] ?? 1),
        'filtro_titulo' => (string) ($c['filtro_titulo'] ?? ''),
        'blocos_ids' => $c['blocos_ids'] ?? null,
        'config_json' => $methodEncode->invoke($ctrl, $src, $c),
        'materias_ids' => is_array($c['materias_ids'] ?? null)
            ? json_encode($c['materias_ids'], JSON_UNESCAPED_UNICODE)
            : ($c['materias_ids'] ?? null),
        'usar_percentual' => !empty($c['usar_percentual']) ? 1 : 0,
        'escala_max' => (float) ($c['escala_max'] ?? 10),
        'obrigatorio' => !empty($c['obrigatorio']) ? 1 : 0,
    ];
}

$linhas = is_array($rascunho['linhas_layout'] ?? null) ? $rascunho['linhas_layout'] : [];
$colunasOrdem = [];
foreach ($linhas as $lin) {
    foreach ((array) ($lin['colunas'] ?? []) as $cod) {
        $cod = strtolower(trim((string) $cod));
        if ($cod !== '' && !in_array($cod, $colunasOrdem, true)) {
            $colunasOrdem[] = $cod;
        }
    }
}

$extras = array_merge($extrasAtuais, [
    'layout_boletim_id' => (int) ($extrasAtuais['layout_boletim_id'] ?? 23),
    'quadro_livre' => 0,
    'origem' => 'santa-maria',
    'modelo_santa_maria' => 1,
    'fontes_bimestres' => [1 => 18, 2 => 19, 3 => 20, 4 => 21],
    'fontes_faltas' => [1 => 1, 2 => 3, 3 => 2, 4 => 4],
    'linhas_layout' => $linhas,
    'cabecalhos_manuais' => 0,
    'quadro_chips_manuais' => 0,
    'colunas_ordem' => $colunasOrdem,
    'eventos_avaliativos_ids' => [51, 52, 53],
]);

$saved = $model->saveRule(
    (string) $rascunho['nome'],
    (string) ($rascunho['formula_final'] ?? 'mf'),
    $compsSave,
    REGRA_ID,
    null,
    null,
    null,
    (string) ($rascunho['codigo'] ?? $atual['codigo'] ?? ''),
    null,
    null,
    'boletim',
    2024,
    null,
    1,
    1,
    1,
    'half',
    1,
    null,
    null,
    (float) ($rascunho['nota_minima_aprovacao'] ?? 7),
    1,
    json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    'oficial'
);

$regra = $model->getRuleById(REGRA_ID);
$n = count((array) ($regra['componentes'] ?? []));
$ex = json_decode((string) ($regra['extras_json'] ?? ''), true) ?: [];
echo "saved_id={$saved} comps={$n} round=" . ($regra['round_mode'] ?? '') . "\n";
echo 'linhas=' . json_encode(array_map(
    static fn ($l) => ['nome' => $l['nome'] ?? '', 'cols' => $l['colunas'] ?? []],
    (array) ($ex['linhas_layout'] ?? [])
), JSON_UNESCAPED_UNICODE) . "\n";
echo 'eventos=' . json_encode($ex['eventos_avaliativos_ids'] ?? []) . "\n";
echo 'modelo_sm=' . (int) ($ex['modelo_santa_maria'] ?? 0) . "\n";

$esperados = [
    'b1_pp', 'b1_pu', 'b1_ch', 'b1_pe', 'b1_mu', 'b1_fal',
    'b2_pp', 'b2_pu', 'b2_ch', 'b2_pe', 'b2_mu', 'b2_fal',
    's1_rec', 's1_ms',
    'b3_pp', 'b3_pu', 'b3_ch', 'b3_pe', 'b3_mu', 'b3_fal',
    'b4_pp', 'b4_pu', 'b4_ch', 'b4_pe', 'b4_mu', 'b4_fal',
    's2_rec', 's2_ms',
    'ma', 'pf', 'mf', 'situacao',
];
$tem = [];
foreach ((array) ($regra['componentes'] ?? []) as $c) {
    $tem[strtolower((string) ($c['codigo'] ?? ''))] = true;
}
$faltam = array_values(array_filter($esperados, static fn ($c) => empty($tem[$c])));
echo 'completo=' . ($faltam === [] ? 'OK' : 'FALTA ' . implode(',', $faltam)) . "\n";
exit($faltam === [] ? 0 : 2);
