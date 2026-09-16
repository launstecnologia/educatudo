<?php
/**
 * Seed Colégio Educa: eventos de lançamento de nota no 3º bimestre
 * (S1–S4 × Grupo A / Grupo B) para testar o evento de notas.
 *
 * Uso (container PHP):
 *   php scripts/seed_eventos_semanais_b3_educa.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');

require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Models/Exams/ExamBlock.php';
require_once $basePath . '/app/Models/Exams/ExamBlockManualGrade.php';

const TENANT_DB = 'educatudo_educa';
const PREFIXO = 'SEED GRN B3';
const ANO = 2026;
const BIMESTRE = 3;

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

function grupoPorNome($db, string $nome): array
{
    $row = $db->fetch(
        'SELECT * FROM grupos_regras_notas WHERE nome = :n AND ativo = 1 LIMIT 1',
        ['n' => $nome]
    );
    if (!$row) {
        fail('Grupo de regras "' . $nome . '" não encontrado. Cadastre em Acadêmico → Grupos de regras de notas.');
    }
    return $row;
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
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
$blocos = new ExamBlock();
$notas = new ExamBlockManualGrade();

println('== Seed eventos S1–S4 × Grupo A/B (3º bimestre ' . ANO . ') ==');

$semanais = grupoPorNome($db, 'Quadros Semanais');
$grupos = grupoPorNome($db, 'Grupos');
$semanaisId = (int) $semanais['id'];
$gruposId = (int) $grupos['id'];

$marcas = $db->fetchAll(
    'SELECT id, nome, codigo, numero FROM grupos_regras_notas_marcas
      WHERE grupo_id = :id AND numero BETWEEN 1 AND 4
      ORDER BY numero ASC',
    ['id' => $semanaisId]
) ?: [];
if (count($marcas) < 4) {
    fail('Quadros Semanais precisa das colunas S1–S4.');
}

$tipos = $db->fetchAll(
    'SELECT id, nome, codigo FROM grupos_regras_notas_tipos
      WHERE grupo_id = :id ORDER BY ordem ASC, id ASC',
    ['id' => $gruposId]
) ?: [];
$tipoA = null;
$tipoB = null;
foreach ($tipos as $t) {
    $cod = strtolower((string) ($t['codigo'] ?? ''));
    $nome = mb_strtolower((string) ($t['nome'] ?? ''));
    if ($tipoA === null && ($cod === 'a' || str_contains($nome, 'grupo a'))) {
        $tipoA = $t;
    }
    if ($tipoB === null && ($cod === 'b' || str_contains($nome, 'grupo b'))) {
        $tipoB = $t;
    }
}
if ($tipoA === null || $tipoB === null) {
    fail('Grupo "Grupos" precisa das tabelas Grupo A e Grupo B.');
}

$materiasA = [];
$materiasB = [];
foreach ($db->fetchAll(
    'SELECT tm.tipo_id, m.id, m.nome
       FROM grupos_regras_notas_tipo_materias tm
       INNER JOIN materias m ON m.id = tm.materia_id
      WHERE tm.tipo_id IN (:a, :b)
      ORDER BY m.nome',
    ['a' => (int) $tipoA['id'], 'b' => (int) $tipoB['id']]
) ?: [] as $row) {
    $item = ['id' => (int) $row['id'], 'nome' => (string) $row['nome']];
    if ((int) $row['tipo_id'] === (int) $tipoA['id']) {
        $materiasA[] = $item;
    } else {
        $materiasB[] = $item;
    }
}
if ($materiasA === [] || $materiasB === []) {
    fail('Marque matérias nas tabelas Grupo A e Grupo B.');
}

$tipoAvaliacao = $db->fetch(
    "SELECT id FROM provas_tipos_avaliacao
      WHERE ativo = 1 AND (chave_quadro = 'semanal' OR nome LIKE '%Semanal%')
      ORDER BY (chave_quadro = 'semanal') DESC, id ASC
      LIMIT 1"
);
$tipoAvaliacaoId = (int) ($tipoAvaliacao['id'] ?? 0);
if ($tipoAvaliacaoId <= 0) {
    fail('Tipo de avaliação "Prova Semanal" não encontrado.');
}

$admin = $db->fetch('SELECT id FROM usuarios WHERE id = 1 LIMIT 1');
$adminId = (int) ($admin['id'] ?? 0);
if ($adminId <= 0) {
    fail('Usuário admin (id=1) não encontrado.');
}

$turmas = $db->fetchAll(
    "SELECT id, nome FROM turmas
      WHERE ano_letivo = :ano AND ativo = 1 AND serie LIKE '%1%Série EM%'
      ORDER BY nome ASC",
    ['ano' => ANO]
) ?: [];
if ($turmas === []) {
    $turmas = $db->fetchAll(
        "SELECT id, nome FROM turmas
          WHERE ano_letivo = :ano AND ativo = 1 AND nome LIKE '%1%EM%'
          ORDER BY nome ASC",
        ['ano' => ANO]
    ) ?: [];
}
if ($turmas === []) {
    fail('Nenhuma turma de 1ª EM 2026 encontrada.');
}

$turmaIds = array_map(static fn ($t) => (int) $t['id'], $turmas);
$phTurmas = implode(',', array_fill(0, count($turmaIds), '?'));

$alunosPorTurma = [];
foreach ($db->fetchAll(
    "SELECT m.turma_id, a.id AS aluno_id
       FROM matricula m
       INNER JOIN alunos a ON a.id = m.aluno_id
      WHERE m.status = 'ativa' AND m.turma_id IN ({$phTurmas})
      ORDER BY a.nome",
    $turmaIds
) ?: [] as $row) {
    $alunosPorTurma[(int) $row['turma_id']][] = (int) $row['aluno_id'];
}

$todasMaterias = array_merge($materiasA, $materiasB);
$idsMat = array_values(array_unique(array_map(static fn ($m) => (int) $m['id'], $todasMaterias)));
$phMat = implode(',', array_fill(0, count($idsMat), '?'));
$grade = $db->fetchAll(
    "SELECT turma_id, materia_id, professor_id
       FROM grade_horaria
      WHERE turma_id IN ({$phTurmas}) AND materia_id IN ({$phMat})
      GROUP BY turma_id, materia_id, professor_id",
    array_merge($turmaIds, $idsMat)
) ?: [];
$profPorTurmaMateria = [];
foreach ($grade as $g) {
    $profPorTurmaMateria[(int) $g['turma_id'] . ':' . (int) $g['materia_id']] = (int) $g['professor_id'];
}

$datas = [
    1 => ANO . '-08-04',
    2 => ANO . '-08-11',
    3 => ANO . '-08-18',
    4 => ANO . '-08-25',
];

$lados = [
    ['letra' => 'A', 'tipo' => $tipoA, 'materias' => $materiasA],
    ['letra' => 'B', 'tipo' => $tipoB, 'materias' => $materiasB],
];

println('Turmas: ' . implode(', ', array_column($turmas, 'nome')));
println('Grupo A (' . $tipoA['nome'] . '): ' . implode(', ', array_column($materiasA, 'nome')));
println('Grupo B (' . $tipoB['nome'] . '): ' . implode(', ', array_column($materiasB, 'nome')));

$criados = 0;
$reusados = 0;
$notasQtd = 0;

foreach ($marcas as $marca) {
    $num = (int) $marca['numero'];
    $marcaId = (int) $marca['id'];
    $dataProva = $datas[$num] ?? (ANO . '-08-04');
    foreach ($lados as $lado) {
        $tipoId = (int) $lado['tipo']['id'];
        $titulo = sprintf('%s — S%d Grupo %s', PREFIXO, $num, $lado['letra']);
        $profsMap = [];
        foreach ($lado['materias'] as $mat) {
            foreach ($turmaIds as $tid) {
                $pid = $profPorTurmaMateria[$tid . ':' . $mat['id']] ?? 0;
                if ($pid <= 0) {
                    continue;
                }
                $chave = $pid . ':' . $mat['id'];
                if (!isset($profsMap[$chave])) {
                    $profsMap[$chave] = [
                        'professor_id' => $pid,
                        'materia_id' => $mat['id'],
                        'quantidade_questoes' => 1,
                        'turmas' => [],
                    ];
                }
                $profsMap[$chave]['turmas'][] = $tid;
            }
        }
        $professores = array_values($profsMap);
        if ($professores === []) {
            fail('Sem professor na grade para as matérias do Grupo ' . $lado['letra'] . '.');
        }

        $vinculos = [
            ['grupo_id' => $semanaisId, 'tipo_id' => null, 'marca_id' => $marcaId],
            ['grupo_id' => $gruposId, 'tipo_id' => $tipoId, 'marca_id' => null],
        ];

        $exist = $db->fetch(
            'SELECT id FROM provas_blocos WHERE titulo = :t AND deleted_at IS NULL LIMIT 1',
            ['t' => $titulo]
        );
        if ($exist) {
            $blocoId = (int) $exist['id'];
            $reusados++;
            $db->query(
                'UPDATE provas_blocos
                    SET grupo_regras_notas_id = :gid,
                        grupo_regras_tipo_id = :tid,
                        grupo_regras_marca_id = :mid,
                        semana = :sem,
                        formato_evento = :fmt,
                        configuracao_nota = :cfg,
                        bimestre = :bim,
                        ano_letivo = :ano,
                        tipo_avaliacao_id = :tav,
                        data_prova = :data,
                        liberado = 1,
                        ativo = 1,
                        status = \'liberado\'
                  WHERE id = :id',
                [
                    'gid' => $semanaisId,
                    'tid' => $tipoId,
                    'mid' => $marcaId,
                    'sem' => $num,
                    'fmt' => 'lancamento_nota',
                    'cfg' => 'coordenacao_calcula',
                    'bim' => BIMESTRE,
                    'ano' => ANO,
                    'tav' => $tipoAvaliacaoId,
                    'data' => $dataProva,
                    'id' => $blocoId,
                ]
            );
            $blocos->substituirVinculosGrupoRegras($blocoId, $vinculos);
        } else {
            $blocoId = (int) $blocos->create([
                'titulo' => $titulo,
                'descricao' => 'Seed para testar evento de notas (S' . $num . ' + Grupo ' . $lado['letra'] . ')',
                'data_prova' => $dataProva,
                'hora_inicio' => '08:00:00',
                'hora_fim' => '09:30:00',
                'criado_por' => $adminId,
                'tipo_prova' => 'original',
                'configuracao_nota' => 'coordenacao_calcula',
                'formato_evento' => 'lancamento_nota',
                'ano_letivo' => ANO,
                'bimestre' => BIMESTRE,
                'tipo_avaliacao_id' => $tipoAvaliacaoId,
                'semana' => $num,
                'grupo_regras_notas_id' => $semanaisId,
                'grupo_regras_tipo_id' => $tipoId,
                'grupo_regras_marca_id' => $marcaId,
                'grupos_regras_vinculos' => $vinculos,
                'liberado' => 1,
                'ativo' => 1,
                'visivel_no_portal_aluno' => 1,
                'nota_unica_todas_materias' => 0,
                'turmas' => $turmaIds,
                'professores' => $professores,
            ]);
            $criados++;
        }

        foreach ($lado['materias'] as $mat) {
            foreach ($turmaIds as $tid) {
                $pid = $profPorTurmaMateria[$tid . ':' . $mat['id']] ?? 0;
                if ($pid <= 0) {
                    continue;
                }
                $linhas = [];
                foreach ($alunosPorTurma[$tid] ?? [] as $aid) {
                    $base = 5.0 + ((($aid + $num + (int) $mat['id']) % 50) / 10);
                    $linhas[] = [
                        'turma_id' => $tid,
                        'aluno_id' => $aid,
                        'nota' => round($base, 1),
                    ];
                }
                if ($linhas === []) {
                    continue;
                }
                $notas->upsertLinhas($blocoId, $pid, (int) $mat['id'], $linhas);
                $notasQtd += count($linhas);
            }
        }
        println('  #' . $blocoId . ' ' . $titulo);
    }
}

println('');
println('Eventos novos: ' . $criados . ' | reaproveitados: ' . $reusados . ' | notas: ' . $notasQtd);
println('Próximo passo: Admin → Evento de notas → 3º bimestre 2026');
println('  1) escolha o grupo "Quadros Semanais" (colunas S1–S4)');
println('  2) ou o grupo "Grupos" (tabelas A e B)');
println('Turmas do seed: 1ª EM A/B/C 2026.');
