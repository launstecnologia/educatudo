<?php
/**
 * Corrige a grade do Colégio Educa: um professor não pode estar em duas
 * turmas no mesmo horário. Cada professor fica vinculado a uma turma/área.
 *
 * Uso (container PHP):
 *   php scripts/corrigir_professores_colegio_educa.php
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
require_once $basePath . '/app/Models/User/Teacher.php';

const TENANT_DB = 'educatudo_educa';
const SENHA = 'Teste@123';

const AREAS = [
    'mat' => ['Matemática', 'Física'],
    'ling' => ['Língua Portuguesa', 'Língua Inglesa', 'Arte'],
    'hum' => ['História', 'Geografia', 'Filosofia', 'Sociologia', 'Ensino Religioso'],
    'nat' => ['Ciências', 'Biologia', 'Química'],
    'edf' => ['Educação Física'],
];

const SERIE_POR_NOME = [
    '6º Ano' => 'fund6',
    '7º Ano' => 'fund7',
    '8º Ano' => 'fund8',
    '9º Ano' => 'fund9',
    '1ª Série EM' => 'em1',
    '2ª Série EM' => 'em2',
    '3ª Série EM' => 'em3',
];

const NOMES = [
    'Ana', 'Bruno', 'Carla', 'Diego', 'Elisa', 'Felipe', 'Gabriela', 'Henrique',
    'Isabela', 'João', 'Karina', 'Lucas', 'Marina', 'Nicolas', 'Olivia', 'Pedro',
    'Rafaela', 'Samuel', 'Talita', 'Vitor', 'Yasmin', 'Caio', 'Beatriz', 'Eduardo',
];
const SOBRENOMES = [
    'Almeida', 'Barbosa', 'Cardoso', 'Dias', 'Fernandes', 'Gomes', 'Lima', 'Mendes',
    'Nogueira', 'Oliveira', 'Pereira', 'Rocha', 'Silva', 'Teixeira', 'Vieira', 'Castro',
];

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

function codigoSerie(string $serie): string
{
    return match ($serie) {
        'fund6' => '6', 'fund7' => '7', 'fund8' => '8', 'fund9' => '9',
        'em1' => '1', 'em2' => '2', 'em3' => '3',
        default => 'x',
    };
}

function emailProfessor(string $area, string $serie, string $letra): string
{
    if ($serie === 'fund6' && $letra === 'A') {
        return $area . '@educa.local';
    }
    return $area . '.' . codigoSerie($serie) . strtolower($letra) . '@educa.local';
}

function codigoProfessor(string $area, string $serie, string $letra): string
{
    return 'EDU-' . strtoupper($area) . '-' . codigoSerie($serie) . $letra;
}

function nomeProfessor(string $area, string $serie, string $letra, string $serieNome): string
{
    $fixos = [
        'mat:fund6:A' => 'Marcos Tavares',
        'ling:fund6:A' => 'Lúcia Prado',
        'hum:fund6:A' => 'Hugo Sampaio',
        'nat:fund6:A' => 'Natália Correia',
        'edf:fund6:A' => 'Érica Fontes',
    ];
    $chave = $area . ':' . $serie . ':' . $letra;
    if (isset($fixos[$chave])) {
        return $fixos[$chave];
    }
    $series = array_values(SERIE_POR_NOME);
    $letras = ['A', 'B', 'C'];
    $areas = array_keys(AREAS);
    $serieIdx = array_search($serie, $series, true);
    $letraIdx = array_search($letra, $letras, true);
    $areaIdx = array_search($area, $areas, true);
    $n = ((int) $serieIdx * 15) + ((int) $letraIdx * 5) + (int) $areaIdx;
    return NOMES[$n % count(NOMES)] . ' ' . SOBRENOMES[($n * 3) % count(SOBRENOMES)];
}

function serieKeyFromNome(string $nome): ?string
{
    if (isset(SERIE_POR_NOME[$nome])) {
        return SERIE_POR_NOME[$nome];
    }
    if (str_contains($nome, '6')) {
        return 'fund6';
    }
    if (str_contains($nome, '7')) {
        return 'fund7';
    }
    if (str_contains($nome, '8')) {
        return 'fund8';
    }
    if (str_contains($nome, '9')) {
        return 'fund9';
    }
    if (str_contains($nome, '1')) {
        return 'em1';
    }
    if (str_contains($nome, '2')) {
        return 'em2';
    }
    if (str_contains($nome, '3')) {
        return 'em3';
    }
    return null;
}

function letraDaTurma(string $nome): ?string
{
    if (preg_match('/\s([ABC])\s+20\d{2}$/', $nome, $m)) {
        return $m[1];
    }
    return null;
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
    'host' => $host, 'port' => $port, 'name' => TENANT_DB, 'user' => $dbUser, 'pass' => $dbPass,
]));
$db = Database::getInstance();
$teachers = new Teacher();

println('== Corrigir professores — Colégio Educa ==');

$materiasPorNome = [];
foreach ($db->fetchAll('SELECT id, nome FROM materias') ?: [] as $row) {
    $materiasPorNome[(string) $row['nome']] = (int) $row['id'];
}
$materiaIdsPorArea = [];
foreach (AREAS as $area => $nomes) {
    $ids = [];
    foreach ($nomes as $nome) {
        if (isset($materiasPorNome[$nome])) {
            $ids[] = $materiasPorNome[$nome];
        }
    }
    $materiaIdsPorArea[$area] = $ids;
}

$turmas = $db->fetchAll(
    'SELECT t.id, t.nome, t.ano_letivo, s.nome AS serie_nome
     FROM turmas t
     INNER JOIN serie s ON s.id = t.serie_id
     ORDER BY t.ano_letivo, t.id'
) ?: [];

$profs = [];
$turmaMeta = [];
foreach ($turmas as $turma) {
    $serieNome = (string) $turma['serie_nome'];
    $serie = serieKeyFromNome($serieNome);
    $letra = letraDaTurma((string) $turma['nome']);
    if ($serie === null || $letra === null) {
        println('  AVISO: turma não mapeada id=' . $turma['id'] . ' ' . $turma['nome']);
        continue;
    }
    $turmaMeta[(int) $turma['id']] = ['serie' => $serie, 'letra' => $letra, 'serie_nome' => $serieNome];
}

println('  turmas mapeadas: ' . count($turmaMeta));

foreach ($turmaMeta as $meta) {
    foreach (array_keys(AREAS) as $area) {
        $chave = $area . ':' . $meta['serie'] . ':' . $meta['letra'];
        if (isset($profs[$chave])) {
            continue;
        }
        $email = emailProfessor($area, $meta['serie'], $meta['letra']);
        $exist = $db->fetch('SELECT id FROM professores WHERE email = :e LIMIT 1', ['e' => $email]);
        if ($exist) {
            $pid = (int) $exist['id'];
            $db->query(
                'UPDATE professores SET nome = :n, codigo_prof = :c, materias = :m, ativo = 1 WHERE id = :id',
                [
                    'n' => nomeProfessor($area, $meta['serie'], $meta['letra'], $meta['serie_nome']),
                    'c' => codigoProfessor($area, $meta['serie'], $meta['letra']),
                    'm' => json_encode(AREAS[$area], JSON_UNESCAPED_UNICODE),
                    'id' => $pid,
                ]
            );
        } else {
            $pid = (int) $teachers->create([
                'nome' => nomeProfessor($area, $meta['serie'], $meta['letra'], $meta['serie_nome']),
                'email' => $email,
                'senha' => SENHA,
                'codigo_prof' => codigoProfessor($area, $meta['serie'], $meta['letra']),
                'materias' => AREAS[$area],
                'turmas' => [],
                'ativo' => 1,
                'pagante' => 1,
            ]);
        }
        $profs[$chave] = $pid;
    }
}
println('  professores: ' . count($profs));

$areaPorMateriaId = [];
foreach ($materiaIdsPorArea as $area => $ids) {
    foreach ($ids as $mid) {
        $areaPorMateriaId[$mid] = $area;
    }
}

$atualizados = ['grade' => 0, 'aulas' => 0, 'fechamentos' => 0, 'planos' => 0, 'notas' => 0, 'blocos_prof' => 0, 'conselho' => 0];
$turmasPorProf = [];

foreach ($turmaMeta as $turmaId => $meta) {
    foreach (AREAS as $area => $nomes) {
        $pid = $profs[$area . ':' . $meta['serie'] . ':' . $meta['letra']];
        $ids = $materiaIdsPorArea[$area];
        if ($ids === []) {
            continue;
        }
        $in = implode(',', array_map('intval', $ids));
        $atualizados['grade'] += $db->query(
            "UPDATE grade_horaria SET professor_id = :p WHERE turma_id = :t AND materia_id IN ({$in})",
            ['p' => $pid, 't' => $turmaId]
        ) ? 1 : 0;
        $db->query(
            "UPDATE diario_aulas SET professor_id = :p WHERE turma_id = :t AND materia_id IN ({$in})",
            ['p' => $pid, 't' => $turmaId]
        );
        $db->query(
            "UPDATE diario_fechamentos SET professor_id = :p WHERE turma_id = :t AND materia_id IN ({$in})",
            ['p' => $pid, 't' => $turmaId]
        );
        $db->query(
            "UPDATE planos_aula SET professor_id = :p WHERE turma_id = :t AND materia_id IN ({$in})",
            ['p' => $pid, 't' => $turmaId]
        );
        $db->query(
            "UPDATE provas_blocos_notas_lancadas SET professor_id = :p WHERE turma_id = :t AND materia_id IN ({$in})",
            ['p' => $pid, 't' => $turmaId]
        );
        $db->query(
            "UPDATE provas_blocos_professores pbp
             INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
             SET pbp.professor_id = :p
             WHERE pbpt.turma_id = :t AND pbp.materia_id IN ({$in})",
            ['p' => $pid, 't' => $turmaId]
        );
        $turmasPorProf[$pid][] = $turmaId;
    }
}

foreach ($turmasPorProf as $pid => $ids) {
    $ids = array_values(array_unique($ids));
    $db->query(
        'UPDATE professores SET turmas = :t WHERE id = :id',
        ['t' => json_encode($ids), 'id' => $pid]
    );
}

$sessoes = $db->fetchAll(
    'SELECT id, turma_id FROM conselho_sessoes'
) ?: [];
foreach ($sessoes as $sessao) {
    $turmaId = (int) $sessao['turma_id'];
    if (!isset($turmaMeta[$turmaId])) {
        continue;
    }
    $meta = $turmaMeta[$turmaId];
    $db->query('DELETE FROM conselho_participantes WHERE sessao_id = :s AND cargo = :c', [
        's' => (int) $sessao['id'],
        'c' => 'professor',
    ]);
    foreach (array_keys(AREAS) as $area) {
        $pid = $profs[$area . ':' . $meta['serie'] . ':' . $meta['letra']];
        $nome = (string) ($db->fetch('SELECT nome FROM professores WHERE id = :id', ['id' => $pid])['nome'] ?? $area);
        $db->insert(
            'INSERT INTO conselho_participantes (sessao_id, professor_id, nome, cargo, presente)
             VALUES (:s, :p, :n, :c, 1)',
            ['s' => (int) $sessao['id'], 'p' => $pid, 'n' => $nome, 'c' => 'professor']
        );
        $atualizados['conselho']++;
    }
}

$conflitos = $db->fetch(
    "SELECT COUNT(*) AS c FROM (
        SELECT g.professor_id, g.dia_semana, g.horario_de, t.ano_letivo, COUNT(DISTINCT g.turma_id) n
        FROM grade_horaria g
        INNER JOIN turmas t ON t.id = g.turma_id
        GROUP BY g.professor_id, g.dia_semana, g.horario_de, t.ano_letivo
        HAVING n > 1
     ) x"
);
$nConflitos = (int) ($conflitos['c'] ?? 0);
$nProfs = (int) ($db->fetch('SELECT COUNT(*) c FROM professores WHERE ativo = 1')['c'] ?? 0);

println('  conflitos de horário no mesmo ano: ' . $nConflitos);
println('  professores ativos: ' . $nProfs);
if ($nConflitos > 0) {
    fail('Ainda há choque de horário. Verifique a grade.');
}

println('');
println('Pronto. Cada professor leciona em uma turma (pode repetir a mesma série/letra nos anos seguintes).');
println('Login 6º A: mat@educa.local / ' . SENHA);
println('Outros: mat.7a@educa.local, ling.1b@educa.local, edf.3c@educa.local ...');
exit(0);
