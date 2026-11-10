<?php
/**
 * Colégio Educa (local) — demonstrativo de notas + layout de boletim
 * no formato Santa Maria.
 *
 * Demonstrativo (exibir_em=notas): só monta colunas PP / PU / CH / PE.
 * Sem fórmula, sem MU, sem agrupamento/cálculo de linha.
 *
 * Pré-requisito: php scripts/seed_boletim_santa_maria_1a_2024.php
 *
 * Uso (container PHP):
 *   php scripts/configurar_demonstrativo_boletim_sm24_2024.php
 *   php scripts/configurar_demonstrativo_boletim_sm24_2024.php --somente-demonstrativo
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

ini_set('memory_limit', '512M');
set_time_limit(0);

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';

use App\Core\Database;
use App\Modulos\Boletins\Controllers\BoletimConfigController;
use App\Modulos\Boletins\Models\BoletimConfig;
use App\Modulos\Boletins\Services\BoletimCadastroService;
use App\Modulos\EventosAvaliativos\Models\BoletimLayout;
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;
use App\Modulos\RegrasAcademicas\Services\RegraAcademicaService;

const TENANT_DB = 'educatudo_educa';
const ANO = 2024;
const PREFIXO = 'SM24';
const TURMA_NOME = '1A Santa Maria';
const MEDIA_MINIMA = 7.0;
const FORMULA_FINAL = '(m1 + m2 + m3 + m4) / 4';

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

/**
 * @return array<string,mixed>
 */
function componenteProva(
    string $codigo,
    string $nome,
    string $calc,
    int $blocoId,
    int $tipoId,
    int $bimestre,
    string $linha = ''
): array {
    $cfg = [
        'prova_bimestres' => [$bimestre],
        'blocos_ids_manual' => 1,
        'tipo_avaliacao_id' => $tipoId,
    ];
    if ($linha !== '') {
        $cfg['linha_cabecalho'] = $linha;
        $cfg['papel_wizard'] = 'exibe';
    }

    return [
        'codigo' => $codigo,
        'nome' => $nome,
        'source_type' => 'provas_sistema',
        'calc_type' => $calc,
        'peso' => 1,
        'filtro_titulo' => '',
        'bloco_id' => $blocoId,
        'blocos_ids' => (string) $blocoId,
        'config_json' => json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'materia_unica' => 0,
        'materia_unica_modo' => 'soma',
        'usar_percentual' => 0,
        'escala_max' => 10,
        'obrigatorio' => in_array($codigo, ['PE', 'CH'], true) ? 0 : 1,
    ];
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}

try {
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
} catch (Throwable $e) {
    fail('Não conectou em ' . TENANT_DB . '. ' . $e->getMessage());
}

$db = Database::getInstance();
$config = new BoletimConfig();
$config->ensureSchema();

$turma = $db->fetch(
    'SELECT id, serie_id FROM turmas WHERE nome = :n AND ano_letivo = :a LIMIT 1',
    ['n' => TURMA_NOME, 'a' => ANO]
);
if (!$turma) {
    fail('Turma ' . TURMA_NOME . ' não encontrada. Rode o seed antes.');
}
$turmaId = (int) $turma['id'];
$serieId = (int) ($turma['serie_id'] ?? 0);

$aluno = $db->fetch(
    "SELECT id FROM alunos WHERE nickname = 'sm24.analuiza' OR ra = '24254' ORDER BY id ASC LIMIT 1"
);
$alunoId = (int) ($aluno['id'] ?? 0);
if ($alunoId <= 0) {
    fail('Aluna Ana Luiza não encontrada. Rode o seed antes.');
}

$blocos = $db->fetchAll(
    "SELECT e.id AS evento_id, e.bimestre, e.nome, e.sigla, e.tipo_avaliacao_id,
            pb.id AS bloco_id, pb.data_prova
       FROM eventos_avaliativos e
       INNER JOIN provas_blocos pb ON pb.evento_avaliativo_id = e.id AND pb.deleted_at IS NULL
      WHERE e.deleted_at IS NULL AND e.ano_letivo = :ano AND e.nome LIKE :p
      ORDER BY e.bimestre ASC, e.id ASC",
    ['ano' => ANO, 'p' => PREFIXO . ' %']
) ?: [];
if ($blocos === []) {
    fail('Nenhum evento SM24 em 2024.');
}

/** @var array<int, array<string, array<string,mixed>>> */
$porBim = [];
foreach ($blocos as $b) {
    $bim = (int) $b['bimestre'];
    $sigla = strtoupper(trim((string) ($b['sigla'] ?? '')));
    if ($sigla === '') {
        $nomeEv = (string) ($b['nome'] ?? '');
        if (str_contains($nomeEv, 'Parcial')) {
            $sigla = 'PP';
        } elseif (str_contains($nomeEv, 'Unidade')) {
            $sigla = 'PU';
        } elseif (str_contains($nomeEv, 'Chamada') || str_contains(strtolower($nomeEv), '2a')) {
            $sigla = 'CH';
        } elseif (str_contains($nomeEv, 'Ponto Extra')) {
            $sigla = 'PE';
        } else {
            $sigla = 'REC';
        }
    }
    $porBim[$bim][$sigla] = $b;
}

$materiasIds = [];
// Só matérias SM24 lançáveis nos eventos SM24 (mesmo materia_id das notas).
$matRows = $db->fetchAll(
    "SELECT id FROM materias
      WHERE ativo = 1 AND codigo LIKE 'SM24-%' AND permite_avaliacao = 1
      ORDER BY id ASC"
) ?: [];
foreach ($matRows as $m) {
    $materiasIds[] = (int) $m['id'];
}
if ($materiasIds === []) {
    fail('Nenhuma matéria SM24-*. Rode o seed antes.');
}
$materiasJson = json_encode(array_values(array_unique($materiasIds)), JSON_UNESCAPED_UNICODE);
$seriesJson = $serieId > 0 ? json_encode([$serieId]) : '[]';
$turmasJson = json_encode([$turmaId]);

$ref = new ReflectionClass(BoletimConfigController::class);
$ctrl = $ref->newInstanceWithoutConstructor();
$prop = $ref->getProperty('boletimConfig');
$prop->setAccessible(true);
$prop->setValue($ctrl, $config);

$somenteDemonstrativo = in_array('--somente-demonstrativo', $argv ?? [], true);
$ordinais = [1 => '1º', 2 => '2º', 3 => '3º', 4 => '4º'];
println('== Demonstrativo Santa Maria 2024 (só colunas) ==');

$idsNotasGerados = [];
foreach ([1, 2, 3, 4] as $bim) {
    $pp = $porBim[$bim]['PP'] ?? null;
    $pu = $porBim[$bim]['PU'] ?? null;
    $ch = $porBim[$bim]['CH'] ?? null;
    $pe = $porBim[$bim]['PE'] ?? null;
    if ($pp === null || $pu === null || $pe === null) {
        fail('Faltam PP/PU/PE no ' . $bim . 'º bimestre.');
    }
    $componentes = [
        componenteProva('PP', 'Prova Parcial', 'ultima', (int) $pp['bloco_id'], (int) $pp['tipo_avaliacao_id'], $bim, 'Avaliações'),
        componenteProva('PU', 'Prova de Unidade', 'ultima', (int) $pu['bloco_id'], (int) $pu['tipo_avaliacao_id'], $bim, 'Avaliações'),
    ];
    if ($ch !== null) {
        $componentes[] = componenteProva('CH', '2ª Chamada', 'ultima', (int) $ch['bloco_id'], (int) $ch['tipo_avaliacao_id'], $bim, 'Avaliações');
    }
    $componentes[] = componenteProva('PE', 'Ponto Extra', 'ultima', (int) $pe['bloco_id'], (int) $pe['tipo_avaliacao_id'], $bim, 'Avaliações');

    $datas = array_filter([
        substr((string) ($pp['data_prova'] ?? ''), 0, 10),
        substr((string) ($pu['data_prova'] ?? ''), 0, 10),
        substr((string) ($ch['data_prova'] ?? ''), 0, 10),
        substr((string) ($pe['data_prova'] ?? ''), 0, 10),
    ]);
    sort($datas);
    $ini = $datas[0] ?? (ANO . '-02-01');
    $fim = $datas[count($datas) - 1] ?? (ANO . '-12-20');

    $codigo = 'sm24-notas-' . $bim . '-bimestre-2024';
    $existente = $config->getRuleByCode($codigo);
    $colunasLayout = $ch !== null ? ['PP', 'PU', 'CH', 'PE'] : ['PP', 'PU', 'PE'];
    $rotulos = [
        'PP' => 'PP',
        'PU' => 'PU',
        'PE' => 'PE',
    ];
    if ($ch !== null) {
        $rotulos['CH'] = 'CH';
    }
    $extras = json_encode([
        'linhas_layout' => [[
            'id' => 'linsm24b' . $bim,
            'nome' => 'Avaliações',
            'colunas' => $colunasLayout,
        ]],
        'rotulos_colunas' => $rotulos,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $regraId = $config->saveRule(
        'Demonstrativo Santa Maria — ' . $ordinais[$bim] . ' bimestre 2024',
        '',
        $componentes,
        $existente ? (int) $existente['id'] : null,
        'Colunas do demonstrativo: PP, PU, CH e PE. Sem fórmula.',
        $materiasJson,
        null,
        $codigo,
        $seriesJson,
        $turmasJson,
        'notas',
        ANO,
        $bim,
        1,
        1,
        1,
        'none',
        1,
        $ini,
        $fim,
        MEDIA_MINIMA,
        0,
        $extras,
        'oficial'
    );
    $idsNotasGerados[] = $regraId;
    println('  demonstrativo B' . $bim . ' #' . $regraId . ' · colunas=' . implode(',', $colunasLayout));

    $regra = $config->getRuleById($regraId);
    $periodoRef = ANO . '-B' . $bim;
    try {
        $sim = $ctrl->simularRegraAluno($regra, $alunoId, $periodoRef, $ini, $fim);
        $matriz = $sim['matriz_materias'] ?? null;
        $colunas = is_array($matriz) && is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
        $linhas = is_array($matriz) && is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
        $config->replaceGeneratedResultsForAluno(
            $regraId,
            $alunoId,
            $periodoRef,
            $ini,
            $fim,
            $colunas,
            $linhas,
            false
        );
        println('  demonstrativo B' . $bim . ' #' . $regraId . ' · linhas=' . count($linhas));
    } catch (Throwable $e) {
        println('  demonstrativo B' . $bim . ' salvo (#' . $regraId . '), geração: ' . $e->getMessage());
    }
}

// Recuperação semestral (R1/R2) + Prova Final (RF) — mesmos códigos dos Eventos Avaliativos.
$slotsRec = [
    81 => ['sigla' => 'REC', 'codigo' => 'sm24-notas-r1-2024', 'nome' => 'Demonstrativo Santa Maria — Recuperação 1º semestre 2024', 'rotulo' => 'R1'],
    82 => ['sigla' => 'REC', 'codigo' => 'sm24-notas-r2-2024', 'nome' => 'Demonstrativo Santa Maria — Recuperação 2º semestre 2024', 'rotulo' => 'R2'],
    90 => ['sigla' => 'PF', 'codigo' => 'sm24-notas-rf-2024', 'nome' => 'Demonstrativo Santa Maria — Prova Final 2024', 'rotulo' => 'RF'],
];
foreach ($slotsRec as $codigoPeriodo => $metaRec) {
    $evRec = $porBim[$codigoPeriodo]['REC']
        ?? $porBim[$codigoPeriodo]['PF']
        ?? $porBim[$codigoPeriodo][$metaRec['sigla']]
        ?? null;
    if ($evRec === null) {
        foreach ($porBim[$codigoPeriodo] ?? [] as $candidato) {
            $evRec = $candidato;
            break;
        }
    }
    if ($evRec === null) {
        println('  demonstrativo ' . $metaRec['rotulo'] . ': evento SM24 não encontrado, pulou');
        continue;
    }
    $siglaColuna = $metaRec['sigla'];
    $componentesRec = [
        componenteProva(
            $siglaColuna,
            $siglaColuna === 'PF' ? 'Prova Final' : 'Recuperação',
            'ultima',
            (int) $evRec['bloco_id'],
            (int) $evRec['tipo_avaliacao_id'],
            (int) $codigoPeriodo,
            'Recuperação'
        ),
    ];
    $iniRec = substr((string) ($evRec['data_prova'] ?? ''), 0, 10) ?: (ANO . '-06-01');
    $fimRec = $iniRec;
    $existenteRec = $config->getRuleByCode($metaRec['codigo']);
    $extrasRec = json_encode([
        'linhas_layout' => [[
            'id' => 'linsm24' . strtolower($metaRec['rotulo']),
            'nome' => 'Recuperação',
            'colunas' => [$siglaColuna],
        ]],
        'rotulos_colunas' => [$siglaColuna => $siglaColuna],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $regraRecId = $config->saveRule(
        $metaRec['nome'],
        '',
        $componentesRec,
        $existenteRec ? (int) $existenteRec['id'] : null,
        'Coluna de recuperação do demonstrativo. Sem fórmula.',
        $materiasJson,
        null,
        $metaRec['codigo'],
        $seriesJson,
        $turmasJson,
        'notas',
        ANO,
        (int) $codigoPeriodo,
        1,
        1,
        1,
        'none',
        1,
        $iniRec,
        $fimRec,
        MEDIA_MINIMA,
        0,
        $extrasRec,
        'oficial'
    );
    $idsNotasGerados[] = $regraRecId;
    println('  demonstrativo ' . $metaRec['rotulo'] . ' #' . $regraRecId . ' · coluna=' . $siglaColuna);
}

// Mantém vínculo com o modelo já existente, sem recriar boletim/layout.
$modeloExistente = $db->fetch(
    "SELECT id FROM boletins
      WHERE ano_letivo = :a AND nome = :n AND excluido_em IS NULL
      ORDER BY id DESC LIMIT 1",
    ['a' => ANO, 'n' => 'Santa Maria — Ensino Médio 2024']
);
$boletimModeloExistenteId = (int) ($modeloExistente['id'] ?? 0);
if ($boletimModeloExistenteId > 0) {
    foreach ($idsNotasGerados as $ridLink) {
        $config->setBoletimId((int) $ridLink, $boletimModeloExistenteId);
    }
    println('  vínculo modelo #' . $boletimModeloExistenteId . ' · eventos=' . implode(',', $idsNotasGerados));
}

if ($somenteDemonstrativo) {
    println('');
    println('Pronto (somente demonstrativo).');
    println('  Demonstrativo: http://educa.localhost/admin/boletim?exibir_em=notas&ano=' . ANO);
    exit(0);
}

// Regra de aprovação 2024 (obrigatória para criar boletim_layouts).
$regraSvc = new RegraAcademicaService();
$regraExist = $db->fetch(
    "SELECT id FROM regras_academicas
      WHERE (codigo = :c OR (nome = :n AND ano_letivo = :a))
      ORDER BY CASE WHEN codigo = :c2 THEN 0 ELSE 1 END, id ASC
      LIMIT 1",
    [
        'c' => 'sm24-aprovacao-2024',
        'c2' => 'sm24-aprovacao-2024',
        'n' => 'Aprovação Santa Maria 2024',
        'a' => ANO,
    ]
);
$payloadRegra = [
    'nome' => 'Aprovação Santa Maria 2024',
    'codigo' => 'sm24-aprovacao-2024',
    'ano_letivo' => ANO,
    'serie_id' => $serieId > 0 ? $serieId : null,
    'periodo_tipo' => 'bimestre',
    'media_minima' => MEDIA_MINIMA,
    'escala' => '10',
    'frequencia_minima' => 75,
    'usar_frequencia' => 0,
    'round_mode' => 'none',
    'decimal_places' => 1,
    'formula_media' => null,
    'formula_final' => FORMULA_FINAL,
    'recuperacao_tipo' => 'periodo',
    'recuperacao_composicao' => 'maior_nota',
    'finalidade' => 'oficial',
    'vis_aluno' => 1,
    'vis_pais' => 1,
    'ativo' => 1,
    'materias_ids' => $materiasIds,
    'series_ids' => $serieId > 0 ? [$serieId] : [],
];
if ($regraExist) {
    $regraAcademicaId = (int) $regraExist['id'];
    $updRegra = $regraSvc->atualizar($regraAcademicaId, $payloadRegra);
    if (empty($updRegra['success'])) {
        fail('Atualizar regra: ' . (string) ($updRegra['error'] ?? 'falha'));
    }
} else {
    $criRegra = $regraSvc->criar($payloadRegra);
    if (empty($criRegra['success'])) {
        fail('Criar regra: ' . (string) ($criRegra['error'] ?? 'falha'));
    }
    $regraAcademicaId = (int) $criRegra['id'];
}
// Duplicatas do seed/config — mantém só a canônica.
$db->query(
    "UPDATE regras_academicas
        SET ativo = 0
      WHERE ano_letivo = :a
        AND nome = :n
        AND id <> :id",
    ['a' => ANO, 'n' => 'Aprovação Santa Maria 2024', 'id' => $regraAcademicaId]
);
$db->query(
    'UPDATE boletins SET regra_academica_id = :r WHERE ano_letivo = :a AND nome LIKE :n',
    ['r' => $regraAcademicaId, 'a' => ANO, 'n' => '%Santa Maria%']
);
$db->query(
    'UPDATE boletim_layouts SET regra_academica_id = :r WHERE ano_letivo = :a AND nome LIKE :n',
    ['r' => $regraAcademicaId, 'a' => ANO, 'n' => '%Santa Maria%']
);
println('  regra de aprovação #' . $regraAcademicaId);

// Recuperações do boletim: semestral (MS < 7) + prova final (PF).
$recModel = new RecuperacaoConfig();
$desejadas = [
    [
        'nome' => 'Recuperação semestral',
        'quando' => 'par',
        'aplica_em' => 'par',
        'nota_minima' => MEDIA_MINIMA,
        'nota_maxima' => 10.0,
        'efeito' => 'maior_nota',
        'formula' => null,
        'permite_aumentar' => 0,
    ],
    [
        'nome' => 'Prova Final',
        'quando' => 'final',
        'aplica_em' => null,
        'nota_minima' => MEDIA_MINIMA,
        'nota_maxima' => 10.0,
        'efeito' => 'maior_nota',
        'formula' => null,
        'permite_aumentar' => 0,
    ],
];
$jaTem = [];
foreach ($recModel->listarDaRegra($regraAcademicaId) as $recLinha) {
    $jaTem[(string) ($recLinha['quando'] ?? '')] = (int) ($recLinha['id'] ?? 0);
}
foreach ($desejadas as $recPayload) {
    $quando = (string) $recPayload['quando'];
    $payload = array_merge($recPayload, [
        'ano_letivo' => ANO,
        'regra_id' => $regraAcademicaId,
    ]);
    if (!empty($jaTem[$quando])) {
        $recModel->atualizar((int) $jaTem[$quando], $payload);
        println('  recuperação "' . $recPayload['nome'] . '" atualizada (#' . $jaTem[$quando] . ')');
        continue;
    }
    // Reaproveita órfã do seed (regra_id NULL) se existir.
    $orfa = $db->fetch(
        "SELECT id FROM recuperacoes
          WHERE ano_letivo = :a AND quando = :q AND deleted_at IS NULL
            AND (regra_id IS NULL OR regra_id = 0)
          ORDER BY id ASC LIMIT 1",
        ['a' => ANO, 'q' => $quando]
    );
    if ($orfa) {
        $rid = (int) $orfa['id'];
        $db->query(
            'UPDATE recuperacoes SET regra_id = :r, nome = :n, nota_minima = :min, nota_maxima = :max,
                    efeito = :e, aplica_em = :ap, formula = :f, permite_aumentar = :pa
              WHERE id = :id',
            [
                'r' => $regraAcademicaId,
                'n' => $recPayload['nome'],
                'min' => $recPayload['nota_minima'],
                'max' => $recPayload['nota_maxima'],
                'e' => $recPayload['efeito'],
                'ap' => $recPayload['aplica_em'],
                'f' => $recPayload['formula'],
                'pa' => (int) $recPayload['permite_aumentar'],
                'id' => $rid,
            ]
        );
        println('  recuperação "' . $recPayload['nome'] . '" vinculada (#' . $rid . ')');
        continue;
    }
    $novoRecId = $recModel->criar($payload);
    println('  recuperação "' . $recPayload['nome'] . '" criada (#' . $novoRecId . ')');
}

// Layout oficial do boletim (eventos-avaliativos), espelhando os grupos do papel.
$layoutModel = new BoletimLayout();
if (!$layoutModel->identidadePronta()) {
    fail('Tabela boletim_layouts sem identidade. Rode a migration 2026_10_28_boletim_layouts_varios.sql.');
}

$linhasLayout = [
    ['nome' => 'I Bimestre', 'colunas' => ['1_media', '1_faltas', '1_situacao']],
    ['nome' => 'II Bimestre', 'colunas' => ['2_media', '2_faltas', '2_situacao']],
    ['nome' => '1º Semestre', 'colunas' => ['81_rec']],
    ['nome' => 'III Bimestre', 'colunas' => ['3_media', '3_faltas', '3_situacao']],
    ['nome' => 'IV Bimestre', 'colunas' => ['4_media', '4_faltas', '4_situacao']],
    ['nome' => '2º Semestre', 'colunas' => ['82_rec']],
    ['nome' => 'Resultado Final', 'colunas' => ['final_media', 'final_situacao']],
];

$existLayout = $db->fetch(
    "SELECT id FROM boletim_layouts WHERE ano_letivo = :a AND (nome LIKE :n OR regra_academica_id = :r) ORDER BY id DESC LIMIT 1",
    ['a' => ANO, 'n' => '%Santa Maria%', 'r' => $regraAcademicaId]
);
$cadastro = [
    'nome' => 'Santa Maria — Ensino Médio ' . ANO,
    'ano_letivo' => ANO,
    'finalidade' => 'oficial',
    'vis_aluno' => 1,
    'vis_pais' => 1,
    'ativo' => 1,
    'regra_academica_id' => $regraAcademicaId,
    'materias_ids' => $materiasIds,
    'series_ids' => $serieId > 0 ? [$serieId] : [],
    'turmas_ids' => [$turmaId],
];

if ($existLayout) {
    $layoutId = (int) $existLayout['id'];
    $upd = $layoutModel->atualizarCadastro($layoutId, $cadastro);
    if (!$upd['ok']) {
        fail('Atualizar layout: ' . $upd['erro']);
    }
} else {
    $criado = $layoutModel->criar($cadastro);
    if (!$criado['ok']) {
        fail('Criar layout: ' . $criado['erro']);
    }
    $layoutId = (int) $criado['id'];
}

// pacoteBoletim zera turmas — garante a 1A Santa Maria.
$db->query(
    'UPDATE boletim_layouts SET nome = :nome, turmas_ids = :turmas, ativo = 1 WHERE id = :id',
    [
        'nome' => 'Santa Maria — Ensino Médio ' . ANO,
        'turmas' => $turmasJson,
        'id' => $layoutId,
    ]
);

$filtradas = $layoutModel->filtrar(ANO, $linhasLayout);
if ($filtradas === []) {
    $filtradas = $layoutModel->filtrar(ANO, [
        ['nome' => 'I Bimestre', 'colunas' => ['1_media', '1_faltas', '1_situacao']],
        ['nome' => 'II Bimestre', 'colunas' => ['2_media', '2_faltas', '2_situacao']],
        ['nome' => 'III Bimestre', 'colunas' => ['3_media', '3_faltas', '3_situacao']],
        ['nome' => 'IV Bimestre', 'colunas' => ['4_media', '4_faltas', '4_situacao']],
        ['nome' => 'Resultado Final', 'colunas' => ['final_media', 'final_situacao']],
    ]);
}
if (!$layoutModel->salvarDoBoletim($layoutId, $filtradas, FORMULA_FINAL)) {
    fail('Não gravou as linhas do layout.');
}

$item = $layoutModel->find($layoutId);
$qtdLinhas = is_array($item) ? count($item['linhas'] ?? []) : 0;
println('  layout boletim #' . $layoutId . ' · linhas=' . $qtdLinhas);

// Modelo da aba "Modelos" (tabela boletins) + vínculo dos demonstrativos (boletim_id).
$cadastroSvc = new BoletimCadastroService();
$nomeModelo = 'Santa Maria — Ensino Médio 2024';
$existModelo = $db->fetch(
    "SELECT id FROM boletins
      WHERE ano_letivo = :a AND nome = :n AND excluido_em IS NULL
      ORDER BY id DESC LIMIT 1",
    ['a' => ANO, 'n' => $nomeModelo]
);
$payloadModelo = [
    'nome' => $nomeModelo,
    'finalidade' => 'oficial',
    'ano_letivo' => ANO,
    'regra_academica_id' => $regraAcademicaId,
    'materias_ids' => $materiasIds,
    'series_ids' => $serieId > 0 ? [$serieId] : [],
    'turmas_ids' => [$turmaId],
    'vis_aluno' => 1,
    'vis_pais' => 1,
    'vis_coordenacao' => 1,
    'ativo' => 1,
];
if ($existModelo) {
    $boletimModeloId = (int) $existModelo['id'];
    $salvoModelo = $cadastroSvc->salvar($payloadModelo, $boletimModeloId);
} else {
    $salvoModelo = $cadastroSvc->salvar($payloadModelo);
    $boletimModeloId = (int) ($salvoModelo['id'] ?? 0);
}
if (empty($salvoModelo['success']) || $boletimModeloId <= 0) {
    fail('Criar modelo: ' . (string) ($salvoModelo['error'] ?? 'falha'));
}

// pacoteBoletim pode limpar turmas — garante 1A Santa Maria.
$db->query(
    'UPDATE boletins SET nome = :nome, turmas_ids = :turmas, series_ids = :series, materias_ids = :mats, ativo = 1 WHERE id = :id',
    [
        'nome' => $nomeModelo,
        'turmas' => $turmasJson,
        'series' => $seriesJson,
        'mats' => $materiasJson,
        'id' => $boletimModeloId,
    ]
);

$idsNotas = [];
$codigosLink = [
    'sm24-notas-1-bimestre-2024',
    'sm24-notas-2-bimestre-2024',
    'sm24-notas-3-bimestre-2024',
    'sm24-notas-4-bimestre-2024',
    'sm24-notas-r1-2024',
    'sm24-notas-r2-2024',
    'sm24-notas-rf-2024',
];
foreach ($codigosLink as $codigoLink) {
    $regraLink = $config->getRuleByCode($codigoLink);
    if (!is_array($regraLink)) {
        continue;
    }
    $ridLink = (int) ($regraLink['id'] ?? 0);
    if ($ridLink <= 0) {
        continue;
    }
    $config->setBoletimId($ridLink, $boletimModeloId);
    $idsNotas[] = $ridLink;
}
println('  modelo demonstrativo #' . $boletimModeloId . ' · eventos notas=' . implode(',', $idsNotas));

println('');
println('Pronto.');
println('  Modelos:       http://educa.localhost/admin/boletins');
println('  Demonstrativo: http://educa.localhost/admin/boletim?exibir_em=notas&ano=' . ANO);
println('  Layout:        http://educa.localhost/admin/eventos-avaliativos/boletim/' . $layoutId);
println('  Coordenação:   http://educa.localhost/admin/reports/boletim-coordenacao');
println('  Aluna: Ana Luiza · turma ' . TURMA_NOME . ' (#' . $turmaId . ')');
