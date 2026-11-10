<?php
/**
 * Grava o demonstrativo de notas dos 4 bimestres de 2025 no Colégio Educa local.
 * Colunas: S1–S4 sob "Prova Semanal", a média da prova semanal, a bimestral e o simulado.
 * Sem média final: a escola atribui a fórmula no fechamento do período.
 * Não gera nem publica boletim.
 *
 * Uso (container PHP): php scripts/configurar_notas_bimestres_2025_educa.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';

use App\Core\Database;
use App\Modulos\Boletins\Models\BoletimConfig;

const TENANT_DB = 'educatudo_educa';
const ANO = 2025;
const BOLETIM_ID = 1;

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}

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

$db = Database::getInstance();
$blocos = $db->fetchAll(
    'SELECT e.bimestre, e.nome AS evento_nome, e.tipo_avaliacao_id,
            pb.id AS bloco_id, pb.titulo, pb.data_prova, pb.semana
     FROM eventos_avaliativos e
     INNER JOIN provas_blocos pb ON pb.evento_avaliativo_id = e.id
     WHERE e.deleted_at IS NULL
       AND e.ano_letivo = :ano
       AND e.bimestre BETWEEN 1 AND 4
     ORDER BY e.bimestre ASC, pb.semana ASC, pb.id ASC',
    ['ano' => ANO]
) ?: [];
if ($blocos === []) {
    fail('Nenhum bloco de 2025 encontrado. Rode o seed de eventos antes.');
}

$porBimestre = [];
foreach ($blocos as $bloco) {
    $bim = (int) ($bloco['bimestre'] ?? 0);
    $porBimestre[$bim][] = $bloco;
}
foreach ([1, 2, 3, 4] as $bim) {
    if (empty($porBimestre[$bim])) {
        fail('Faltam blocos do ' . $bim . 'º bimestre.');
    }
}

$modelo = $db->fetch(
    "SELECT materias_ids, series_ids, nota_minima_aprovacao, round_mode, decimal_places
     FROM boletim_regras
     WHERE id = 2 AND ativo = 1
     LIMIT 1"
);
$materiasJson = is_array($modelo) && trim((string) ($modelo['materias_ids'] ?? '')) !== ''
    ? (string) $modelo['materias_ids']
    : '[]';
$seriesJson = is_array($modelo) && trim((string) ($modelo['series_ids'] ?? '')) !== ''
    ? (string) $modelo['series_ids']
    : '[5,6,7]';
$notaMinima = is_array($modelo) && $modelo['nota_minima_aprovacao'] !== null
    ? (float) $modelo['nota_minima_aprovacao']
    : 6.0;
$roundMode = is_array($modelo) ? (string) ($modelo['round_mode'] ?? 'half') : 'half';
$decimais = is_array($modelo) ? (int) ($modelo['decimal_places'] ?? 2) : 2;

$config = new BoletimConfig();
$ordinais = [1 => '1º', 2 => '2º', 3 => '3º', 4 => '4º'];

foreach ([1, 2, 3, 4] as $bim) {
    $semanas = [];
    $bimestral = null;
    $simulado = null;
    $datas = [];
    foreach ($porBimestre[$bim] as $bloco) {
        $nome = (string) ($bloco['evento_nome'] ?? '');
        $data = substr((string) ($bloco['data_prova'] ?? ''), 0, 10);
        if ($data !== '') {
            $datas[] = $data;
        }
        if ($nome === 'Prova Semanal') {
            $semanas[] = $bloco;
        } elseif ($nome === 'Prova Bimestral') {
            $bimestral = $bloco;
        } elseif ($nome === 'Simulado') {
            $simulado = $bloco;
        }
    }
    if (count($semanas) < 2 || $bimestral === null || $simulado === null) {
        fail('O ' . $bim . 'º bimestre não tem prova semanal, bimestral e simulado.');
    }
    usort($semanas, static function (array $a, array $b): int {
        return ((int) ($a['semana'] ?? 0)) <=> ((int) ($b['semana'] ?? 0))
            ?: ((int) $a['bloco_id'] <=> (int) $b['bloco_id']);
    });
    sort($datas);
    $ini = $datas[0];
    $fim = $datas[count($datas) - 1];

    $idsSemana = [];
    $codigosLinha = [];
    $componentes = [];
    foreach ($semanas as $semana) {
        $blocoId = (int) $semana['bloco_id'];
        $codigo = 'sev' . $blocoId;
        $idsSemana[] = $blocoId;
        $codigosLinha[] = $codigo;
        $componentes[] = componenteProva(
            $codigo,
            (string) ($semana['titulo'] ?? $codigo),
            'ultima',
            $blocoId,
            null,
            (int) ($semana['tipo_avaliacao_id'] ?? 0),
            $bim,
            'Prova Semanal'
        );
    }
    $componentes[] = componenteProva(
        'semanal',
        'Prova Semanal',
        'media',
        null,
        implode(',', $idsSemana),
        (int) ($semanas[0]['tipo_avaliacao_id'] ?? 0),
        $bim,
        ''
    );
    $componentes[] = componenteProva(
        'bimestral',
        'Prova Bimestral',
        'ultima',
        (int) $bimestral['bloco_id'],
        null,
        (int) ($bimestral['tipo_avaliacao_id'] ?? 0),
        $bim,
        ''
    );
    $componentes[] = componenteProva(
        'tipo_3',
        'Simulado',
        'ultima',
        (int) $simulado['bloco_id'],
        null,
        (int) ($simulado['tipo_avaliacao_id'] ?? 0),
        $bim,
        ''
    );

    $codigoRegra = 'notas-bimestrais-' . $bim . '-bimestre-2025';
    $existente = $db->fetch(
        'SELECT id FROM boletim_regras WHERE codigo = :codigo AND ativo = 1 ORDER BY id DESC LIMIT 1',
        ['codigo' => $codigoRegra]
    );
    $regraId = is_array($existente) ? (int) ($existente['id'] ?? 0) : 0;
    $extras = json_encode([
        'linhas_layout' => [[
            'id' => 'linps' . $bim,
            'nome' => 'Prova Semanal',
            'colunas' => $codigosLinha,
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $salvo = $config->saveRule(
        'Notas Bimestrais — ' . $ordinais[$bim] . ' bimestre 2025',
        '',
        $componentes,
        $regraId > 0 ? $regraId : null,
        'Notas lançadas no bimestre. A média final entra no fechamento.',
        $materiasJson,
        null,
        $codigoRegra,
        $seriesJson,
        '[]',
        'notas',
        ANO,
        $bim,
        1,
        1,
        1,
        $roundMode !== '' ? $roundMode : 'half',
        $decimais === 1 ? 1 : 2,
        $ini,
        $fim,
        $notaMinima,
        1,
        $extras,
        'oficial'
    );
    $config->setBoletimId($salvo, BOLETIM_ID);
    println($ordinais[$bim] . ' bimestre salvo na regra #' . $salvo . ' (' . $ini . ' a ' . $fim . ').');
}

println('Quatro demonstrativos prontos. A média final fica para o fechamento de cada bimestre.');

/**
 * @return array<string,mixed>
 */
function componenteProva(
    string $codigo,
    string $nome,
    string $calc,
    ?int $blocoId,
    ?string $blocosIds,
    int $tipoId,
    int $bimestre,
    string $linha
): array {
    $cfg = ['prova_bimestres' => [$bimestre], 'blocos_ids_manual' => 1];
    if ($tipoId > 0) {
        $cfg['tipo_avaliacao_id'] = $tipoId;
    }
    if ($linha !== '') {
        $cfg['linha_cabecalho'] = $linha;
        $cfg['papel_wizard'] = 'exibe';
    }

    return [
        'codigo' => $codigo,
        'nome' => $nome,
        'source_type' => 'provas_sistema',
        'calc_type' => $calc,
        'peso' => $calc === 'media' ? 1 : 1,
        'filtro_titulo' => '',
        'bloco_id' => $blocoId,
        'blocos_ids' => $blocosIds,
        'config_json' => json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'materia_unica' => 0,
        'materia_unica_modo' => 'soma',
        'usar_percentual' => 1,
        'escala_max' => 10,
        'obrigatorio' => 0,
    ];
}
