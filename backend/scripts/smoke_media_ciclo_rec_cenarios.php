<?php
/**
 * Smoke local: opções de recuperação × Média do Ciclo.
 *
 * Cenário A — ano trimestral (2023 / regra 29):
 *   REC a cada trimestre (61–63) + Rec Final (90)
 *
 * Cenário B — ano bimestral (2024 / regra 28):
 *   REC a cada dois bimestres / semestre (81–82) + Rec Final (90)
 *
 * Uso:
 *   php scripts/smoke_media_ciclo_rec_cenarios.php
 *   docker exec php_app_educatudo php /var/www/html/scripts/smoke_media_ciclo_rec_cenarios.php
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
use App\Core\PeriodoLetivo;
use App\Modulos\Boletins\Services\MediaCicloRecuperacaoService;
use App\Modulos\EventosAvaliativos\Services\EventoAvaliativoService;
use App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig;

const TENANT_DB = 'educatudo_educa';
const PREFIXO = 'SMOKE_MC_REC';

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');

if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Abortado: DB_HOST={$host} não parece local.\n");
    exit(1);
}

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

$db = Database::getInstance();
$falhas = 0;
$ok = 0;

function println(string $msg): void
{
    echo $msg . "\n";
}

function assertTrue(bool $cond, string $label): void
{
    global $falhas, $ok;
    if ($cond) {
        $ok++;
        println('  OK  ' . $label);
        return;
    }
    $falhas++;
    println('  FAIL ' . $label);
}

function limparSmokeAnterior(Database $db): void
{
    $regras = $db->fetchAll(
        'SELECT id FROM boletim_regras WHERE nome LIKE :p',
        ['p' => PREFIXO . '%']
    ) ?: [];
    foreach ($regras as $r) {
        $rid = (int) ($r['id'] ?? 0);
        if ($rid <= 0) {
            continue;
        }
        try {
            $db->query('DELETE FROM boletim_resultados_gerados WHERE regra_id = :r', ['r' => $rid]);
        } catch (Throwable $e) {
            // tabela pode variar
        }
        $db->query('DELETE FROM boletim_componentes WHERE regra_id = :r', ['r' => $rid]);
        $db->query('DELETE FROM boletim_regras WHERE id = :r', ['r' => $rid]);
    }
}

/**
 * @param list<array{codigo:string,nome:string,grupo:string,expressao:string}> $blocos
 */
function criarBoletimSmoke(
    Database $db,
    int $ano,
    string $nome,
    array $turmaIds,
    array $blocos
): int {
    $turmasJson = json_encode(array_values($turmaIds), JSON_UNESCAPED_UNICODE);
    $rid = (int) $db->insert(
        'INSERT INTO boletim_regras
            (nome, codigo, formula_final, series_ids, turmas_ids, exibir_em, finalidade, ano_letivo,
             nota_minima_aprovacao, round_mode, decimal_places, ativo, created_at, updated_at)
         VALUES
            (:nome, :codigo, :ff, NULL, :turmas, \'boletim\', \'oficial\', :ano,
             6.0, \'half\', 1, 1, NOW(), NOW())',
        [
            'nome' => $nome,
            'codigo' => strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $nome) ?? 'smoke'),
            'ff' => $blocos[count($blocos) - 1]['codigo'] ?? 'media_final',
            'turmas' => $turmasJson,
            'ano' => $ano,
        ]
    );
    $ordem = 1;
    foreach ($blocos as $b) {
        $cfg = [
            'expressao' => $b['expressao'],
            'formula_mode' => 'single',
            'media_ciclo' => 1,
            'layout' => ['group' => $b['grupo'], 'type' => 'media'],
            'layout_group' => $b['grupo'],
            'layout_type' => 'media',
        ];
        $db->insert(
            'INSERT INTO boletim_componentes
                (regra_id, codigo, nome, source_type, calc_type, config_json, escala_max, obrigatorio, ativo, ordem, created_at, updated_at)
             VALUES
                (:rid, :cod, :nome, \'calculado\', \'media\', :cfg, 10.0, 0, 1, :ord, NOW(), NOW())',
            [
                'rid' => $rid,
                'cod' => $b['codigo'],
                'nome' => $b['nome'],
                'cfg' => json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ord' => $ordem++,
            ]
        );
    }

    return $rid;
}

/**
 * @param list<string> $refs
 */
function semearNotas(
    Database $db,
    int $regraId,
    int $alunoId,
    int $materiaId,
    string $codigo,
    float $nota,
    array $refs
): void {
    $temVigente = false;
    try {
        $row = $db->fetch(
            'SELECT 1 AS ok FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'boletim_resultados_gerados\' AND COLUMN_NAME = \'vigente\' LIMIT 1'
        );
        $temVigente = !empty($row['ok']);
    } catch (Throwable $e) {
        $temVigente = false;
    }
    foreach ($refs as $ref) {
        $cols = 'regra_id, aluno_id, periodo_ref, materia_id, materia_nome, materia_ref, notas_json, media_final, preview, created_at, updated_at';
        $vals = ':rid, :aluno, :ref, :mat, :matn, :matref, :notas, :media, 0, NOW(), NOW()';
        $params = [
            'rid' => $regraId,
            'aluno' => $alunoId,
            'ref' => $ref,
            'mat' => $materiaId,
            'matn' => 'Smoke Matéria',
            'matref' => 'smoke_mat_' . $materiaId,
            'notas' => json_encode([$codigo => $nota], JSON_UNESCAPED_UNICODE),
            'media' => $nota,
        ];
        if ($temVigente) {
            $cols .= ', vigente';
            $vals .= ', 1';
        }
        $db->insert(
            'INSERT INTO boletim_resultados_gerados (' . $cols . ') VALUES (' . $vals . ')',
            $params
        );
    }
}

/**
 * @param list<int> $esperados
 * @param list<array{codigo:int,rotulo:string,grupo:string}> $slots
 */
function assertSlots(array $esperados, array $slots, string $rotulo): void
{
    $codigos = [];
    foreach ($slots as $s) {
        $codigos[] = (int) ($s['codigo'] ?? 0);
        println('    slot ' . (int) ($s['codigo'] ?? 0) . ' · ' . (string) ($s['rotulo'] ?? ''));
    }
    sort($codigos);
    $exp = $esperados;
    sort($exp);
    assertTrue($codigos === $exp, $rotulo . ' slots=' . implode(',', $codigos));
}

PeriodoLetivo::invalidarCache();

println('== Smoke Média do Ciclo × opções de recuperação ==');
println('tenant=' . TENANT_DB);

limparSmokeAnterior($db);

$recuperacao = new RecuperacaoConfig();
$svcEvento = new EventoAvaliativoService();
$svcMedia = new MediaCicloRecuperacaoService();

// ---------------------------------------------------------------------------
// Cenário A — trimestral: REC por trimestre + anual
// ---------------------------------------------------------------------------
println('');
println('## Cenário A · 2023 trimestral · REC por trimestre + Rec Final');
$infoA = PeriodoLetivo::doAno(2023);
assertTrue(($infoA['tipo'] ?? '') === 'trimestre', 'tipo=trimestre');
assertTrue((int) ($infoA['quantidade'] ?? 0) === 3, 'quantidade=3');

$textosA = RecuperacaoConfig::textosDoAno(2023);
assertTrue(isset($textosA['quandos']['bimestre']), 'quando disponível: a cada período');
assertTrue(isset($textosA['quandos']['final']), 'quando disponível: rec final');
assertTrue(!isset($textosA['quandos']['par']), 'trimestral NÃO oferece "a cada dois ciclos"');

$slotsA = $recuperacao->slotsParaTela(2023, [], 29);
assertSlots(
    [
        RecuperacaoConfig::CODIGO_CICLO_BASE + 1,
        RecuperacaoConfig::CODIGO_CICLO_BASE + 2,
        RecuperacaoConfig::CODIGO_CICLO_BASE + 3,
        RecuperacaoConfig::CODIGO_FINAL,
    ],
    $slotsA,
    'A regra29'
);

$turmaA = $db->fetch(
    'SELECT t.id, a.id AS aluno_id
       FROM turmas t
       INNER JOIN alunos a ON a.turma_id = t.id
      WHERE t.ano_letivo = 2023
      ORDER BY t.id ASC, a.id ASC
      LIMIT 1'
);
assertTrue(!empty($turmaA['id']) && !empty($turmaA['aluno_id']), 'turma/aluno 2023');
$turmaIdA = (int) ($turmaA['id'] ?? 0);
$alunoIdA = (int) ($turmaA['aluno_id'] ?? 0);
$materiaId = (int) (($db->fetch('SELECT id FROM materias ORDER BY id ASC LIMIT 1')['id'] ?? 0));
assertTrue($materiaId > 0, 'matéria');

$ridA = criarBoletimSmoke($db, 2023, PREFIXO . ' A 2023 trimestral', [$turmaIdA], [
    ['codigo' => 'mc_t1', 'nome' => 'Média 1º Tri', 'grupo' => 'b1', 'expressao' => '4'],
    ['codigo' => 'mc_t2', 'nome' => 'Média 2º Tri', 'grupo' => 'b2', 'expressao' => '4'],
    ['codigo' => 'mc_t3', 'nome' => 'Média 3º Tri', 'grupo' => 'b3', 'expressao' => '4'],
    ['codigo' => 'mc_final', 'nome' => 'Média Final', 'grupo' => 'final', 'expressao' => '4'],
]);
println('  boletim smoke A #' . $ridA);

$mapA = [
    RecuperacaoConfig::CODIGO_CICLO_BASE + 1 => ['cod' => 'mc_t1', 'refs' => ['2023-T1', '2023-B1']],
    RecuperacaoConfig::CODIGO_CICLO_BASE + 2 => ['cod' => 'mc_t2', 'refs' => ['2023-T2', '2023-B2']],
    RecuperacaoConfig::CODIGO_CICLO_BASE + 3 => ['cod' => 'mc_t3', 'refs' => ['2023-T3', '2023-B3']],
    RecuperacaoConfig::CODIGO_FINAL => ['cod' => 'mc_final', 'refs' => ['2023-T1', '2023-B1']],
];
foreach ($mapA as $meta) {
    semearNotas($db, $ridA, $alunoIdA, $materiaId, $meta['cod'], 4.0, $meta['refs']);
}

foreach ($mapA as $codigoRec => $meta) {
    $resolvido = $svcMedia->resolverNotas(2023, $codigoRec, [$turmaIdA]);
    assertTrue(!empty($resolvido['configurada']), "A REC {$codigoRec} métrica configurada ({$meta['cod']})");
    assertTrue(empty($resolvido['pendente']), "A REC {$codigoRec} notas prontas");
    assertTrue(($resolvido['codigo'] ?? '') === $meta['cod'], "A REC {$codigoRec} codigo={$meta['cod']}");

    $cand = $svcEvento->candidatosRecuperacao(2023, $codigoRec, [$turmaIdA], 29);
    assertTrue(!empty($cand['media_ciclo_configurada']), "A REC {$codigoRec} candidatos.configurada");
    assertTrue(empty($cand['media_ciclo_pendente']), "A REC {$codigoRec} candidatos !pendente");
    $abaixo = 0;
    foreach (($cand['alunos'] ?? []) as $al) {
        if (!empty($al['abaixo'])) {
            $abaixo++;
        }
    }
    assertTrue($abaixo > 0, "A REC {$codigoRec} tem aluno abaixo ({$abaixo})");
}

// ---------------------------------------------------------------------------
// Cenário B — bimestral: REC a cada dois bimestres + anual
// ---------------------------------------------------------------------------
println('');
println('## Cenário B · 2024 bimestral · REC a cada dois ciclos + Rec Final');
PeriodoLetivo::invalidarCache(2024);
$infoB = PeriodoLetivo::doAno(2024);
assertTrue(($infoB['tipo'] ?? '') === 'bimestre', 'tipo=bimestre');
assertTrue((int) ($infoB['quantidade'] ?? 0) === 4, 'quantidade=4');

$textosB = RecuperacaoConfig::textosDoAno(2024);
assertTrue(isset($textosB['quandos']['par']), 'quando disponível: a cada dois ciclos');
assertTrue(isset($textosB['quandos']['final']), 'quando disponível: rec final');

$slotsB = $recuperacao->slotsParaTela(2024, [], 28);
assertSlots(
    [
        RecuperacaoConfig::CODIGO_PAR_BASE + 1,
        RecuperacaoConfig::CODIGO_PAR_BASE + 2,
        RecuperacaoConfig::CODIGO_FINAL,
    ],
    $slotsB,
    'B regra28'
);

$turmaB = $db->fetch(
    'SELECT t.id, a.id AS aluno_id
       FROM turmas t
       INNER JOIN alunos a ON a.turma_id = t.id
      WHERE t.ano_letivo = 2024
      ORDER BY t.id ASC, a.id ASC
      LIMIT 1'
);
assertTrue(!empty($turmaB['id']) && !empty($turmaB['aluno_id']), 'turma/aluno 2024');
$turmaIdB = (int) ($turmaB['id'] ?? 0);
$alunoIdB = (int) ($turmaB['aluno_id'] ?? 0);

$ridB = criarBoletimSmoke($db, 2024, PREFIXO . ' B 2024 bimestral', [$turmaIdB], [
    ['codigo' => 'mc_s1', 'nome' => 'Média 1º Sem', 'grupo' => 's1', 'expressao' => '4'],
    ['codigo' => 'mc_s2', 'nome' => 'Média 2º Sem', 'grupo' => 's2', 'expressao' => '4'],
    ['codigo' => 'mc_final', 'nome' => 'Média Final', 'grupo' => 'final', 'expressao' => '4'],
]);
println('  boletim smoke B #' . $ridB);

$mapB = [
    RecuperacaoConfig::CODIGO_PAR_BASE + 1 => ['cod' => 'mc_s1', 'refs' => ['2024-B1', '2024-B2']],
    RecuperacaoConfig::CODIGO_PAR_BASE + 2 => ['cod' => 'mc_s2', 'refs' => ['2024-B3', '2024-B4']],
    RecuperacaoConfig::CODIGO_FINAL => ['cod' => 'mc_final', 'refs' => ['2024-B1']],
];
foreach ($mapB as $meta) {
    semearNotas($db, $ridB, $alunoIdB, $materiaId, $meta['cod'], 4.0, $meta['refs']);
}

foreach ($mapB as $codigoRec => $meta) {
    $resolvido = $svcMedia->resolverNotas(2024, $codigoRec, [$turmaIdB]);
    assertTrue(!empty($resolvido['configurada']), "B REC {$codigoRec} métrica configurada ({$meta['cod']})");
    assertTrue(empty($resolvido['pendente']), "B REC {$codigoRec} notas prontas");
    assertTrue(($resolvido['codigo'] ?? '') === $meta['cod'], "B REC {$codigoRec} codigo={$meta['cod']}");

    $cand = $svcEvento->candidatosRecuperacao(2024, $codigoRec, [$turmaIdB], 28);
    assertTrue(!empty($cand['media_ciclo_configurada']), "B REC {$codigoRec} candidatos.configurada");
    assertTrue(empty($cand['media_ciclo_pendente']), "B REC {$codigoRec} candidatos !pendente");
    $abaixo = 0;
    foreach (($cand['alunos'] ?? []) as $al) {
        if (!empty($al['abaixo'])) {
            $abaixo++;
        }
    }
    assertTrue($abaixo > 0, "B REC {$codigoRec} tem aluno abaixo ({$abaixo})");
}

println('');
println('## Limpeza');
limparSmokeAnterior($db);
println('  smoke removido');

println('');
println("Resultado: {$ok} ok, {$falhas} falha(s)");
exit($falhas > 0 ? 1 : 0);
