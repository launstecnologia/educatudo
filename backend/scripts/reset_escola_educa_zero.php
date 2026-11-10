<?php
/**
 * Zera o Colégio Educa local e deixa só a estrutura de 2025:
 * turmas, professores, alunos e matrículas novos.
 * Catálogo (matérias, séries, layout) e o admin permanecem.
 *
 * Uso (container PHP): php scripts/reset_escola_educa_zero.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

ini_set('memory_limit', '512M');
set_time_limit(0);

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';

const TENANT_DB = 'educatudo_educa';
const ANO = 2025;
const SENHA = 'Teste@123';
const ALUNOS_POR_TURMA = 15;

const MANTER = [
    'materias',
    'serie',
    'config_layout',
    'bncc_habilidades',
    'ocorrencias_categorias',
    'admin_perfis_permissao',
    'secretaria_modelos_documentos',
    'secretaria_declaracoes_layouts',
    'matricula_contrato_regras',
    'resultado_documento_layouts',
    'calendario_letivo_tipos',
    'usuarios',
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
$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

$admin = $pdo->query(
    "SELECT id, email FROM usuarios WHERE tipo = 'admin_escola' AND ativo = 1 ORDER BY id ASC LIMIT 1"
)->fetch();
if (!$admin) {
    fail('Nenhum administrador ativo para preservar o login.');
}
$adminId = (int) $admin['id'];
println('Admin preservado: ' . $admin['email'] . ' (#' . $adminId . ').');

$tabelas = $pdo->query(
    "SELECT TABLE_NAME FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
)->fetchAll(PDO::FETCH_COLUMN);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$limpas = 0;
foreach ($tabelas as $tabela) {
    $tabela = (string) $tabela;
    if ($tabela === '' || in_array($tabela, MANTER, true)) {
        continue;
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabela)) {
        continue;
    }
    $pdo->exec('DELETE FROM `' . $tabela . '`');
    $pdo->exec('ALTER TABLE `' . $tabela . '` AUTO_INCREMENT = 1');
    $limpas++;
}
$pdo->prepare('DELETE FROM usuarios WHERE id <> :id')->execute(['id' => $adminId]);
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
println('Tabelas esvaziadas: ' . $limpas . '.');

$pdo->prepare(
    'INSERT INTO ano_letivo (ano, data_inicio, data_fim, periodo_tipo, ativo)
     VALUES (:ano, :inicio, :fim, :tipo, 1)'
)->execute([
    'ano' => ANO,
    'inicio' => '2025-02-03',
    'fim' => '2025-12-19',
    'tipo' => 'bimestre',
]);
$anoId = (int) $pdo->lastInsertId();

$series = $pdo->query(
    'SELECT id, nome FROM serie WHERE id IN (5, 6, 7) ORDER BY id'
)->fetchAll();
if (count($series) !== 3) {
    fail('As séries do Ensino Médio não estão no catálogo.');
}

$turmasPlano = [
    ['nome' => '1A', 'serie' => $series[0]],
    ['nome' => '1B', 'serie' => $series[0]],
    ['nome' => '2A', 'serie' => $series[1]],
    ['nome' => '2B', 'serie' => $series[1]],
    ['nome' => '3A', 'serie' => $series[2]],
    ['nome' => '3B', 'serie' => $series[2]],
];
$insTurma = $pdo->prepare(
    'INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, ativo, turno, vagas)
     VALUES (:nome, :ano, :ano_id, :serie, :serie_id, 1, :turno, 40)'
);
$turmaIds = [];
foreach ($turmasPlano as $plano) {
    $insTurma->execute([
        'nome' => $plano['nome'],
        'ano' => ANO,
        'ano_id' => $anoId,
        'serie' => $plano['serie']['nome'],
        'serie_id' => (int) $plano['serie']['id'],
        'turno' => 'manha',
    ]);
    $turmaIds[$plano['nome']] = (int) $pdo->lastInsertId();
}
println('Turmas: ' . implode(', ', array_keys($turmaIds)) . '.');

$materias = $pdo->query(
    "SELECT id, nome FROM materias
      WHERE ativo = 1 AND permite_avaliacao = 1
        AND nome <> 'Tecnologia Educacional - Treinamento'
        AND id NOT IN (
            SELECT DISTINCT pai_id FROM materias
             WHERE pai_id IS NOT NULL AND pai_id > 0 AND ativo = 1
        )
      ORDER BY nome"
)->fetchAll();
if ($materias === []) {
    fail('Nenhuma matéria avaliável no catálogo.');
}

$hash = password_hash(SENHA, PASSWORD_DEFAULT);
$insProf = $pdo->prepare(
    'INSERT INTO professores (nome, email, senha_hash, codigo_prof, materias, turmas, ativo, pagante, password)
     VALUES (:nome, :email, :senha, :codigo, :materias, :turmas, 1, 1, :password)'
);
$nomesProf = [
    'Ana Lima', 'Bruno Carvalho', 'Carla Mendes', 'Diego Alves', 'Elena Rocha',
    'Felipe Nunes', 'Gabriela Souza', 'Henrique Dias', 'Irene Castro', 'João Pires',
    'Larissa Melo', 'Marcelo Teixeira', 'Natália Borges', 'Otávio Ramos', 'Patrícia Luz',
];
$turmasJson = json_encode(array_keys($turmaIds), JSON_UNESCAPED_UNICODE);
foreach ($materias as $i => $materia) {
    $nomeProf = $nomesProf[$i] ?? ('Professor ' . ($i + 1));
    $slug = 'prof' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
    $insProf->execute([
        'nome' => $nomeProf,
        'email' => $slug . '@educa.local',
        'senha' => $hash,
        'codigo' => 'EDUCA-' . strtoupper($slug),
        'materias' => json_encode([$materia['nome']], JSON_UNESCAPED_UNICODE),
        'turmas' => $turmasJson,
        'password' => '',
    ]);
}
println(count($materias) . ' professores, um por matéria.');

$primeiros = ['Alice', 'Benício', 'Cecília', 'Davi', 'Elisa', 'Francisco', 'Helena', 'Igor', 'Júlia', 'Kaique', 'Laura', 'Miguel', 'Nina', 'Otto', 'Pietra'];
$sobrenomes = ['Almeida', 'Barbosa', 'Cardoso', 'Duarte', 'Esteves', 'Farias', 'Gomes', 'Hoffmann', 'Ibrahim', 'Junqueira', 'Klein', 'Lopes', 'Martins', 'Nogueira', 'Oliveira'];
$insAluno = $pdo->prepare(
    'INSERT INTO alunos (nome, nickname, email, senha_hash, ra, turma_id, serie, data_nasc, ativo, status, pagante, password, primeiro_acesso)
     VALUES (:nome, :nickname, :email, :senha, :ra, :turma, :serie, :nasc, 1, :status, 1, :password, 0)'
);
$insMat = $pdo->prepare(
    'INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status, resultado_ano)
     VALUES (:aluno, :turma, :ano, :entrada, :status, :resultado)'
);
$totalAlunos = 0;
foreach ($turmasPlano as $plano) {
    $turmaId = $turmaIds[$plano['nome']];
    $serieNome = (string) $plano['serie']['nome'];
    for ($n = 1; $n <= ALUNOS_POR_TURMA; $n++) {
        $nome = $primeiros[$n - 1] . ' ' . $sobrenomes[($n + strlen($plano['nome'])) % count($sobrenomes)];
        $nick = 'educa.' . strtolower($plano['nome']) . '.' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
        $insAluno->execute([
            'nome' => $nome . ' ' . $plano['nome'],
            'nickname' => $nick,
            'email' => $nick . '@educa.local',
            'senha' => $hash,
            'ra' => strtoupper(str_replace('.', '', $nick)),
            'turma' => $turmaId,
            'serie' => $serieNome,
            'nasc' => sprintf('2008-%02d-%02d', (($n % 12) + 1), min(28, $n)),
            'status' => 'ACTIVE',
            'password' => '',
        ]);
        $insMat->execute([
            'aluno' => (int) $pdo->lastInsertId(),
            'turma' => $turmaId,
            'ano' => $anoId,
            'entrada' => '2025-02-03',
            'status' => 'ativa',
            'resultado' => 'nao_lancado',
        ]);
        $totalAlunos++;
    }
}
println($totalAlunos . ' alunos matriculados em 2025.');

$pdo->prepare(
    'INSERT INTO regras_academicas (id, nome, ano_letivo, periodo_tipo, media_minima, ativo)
     VALUES (4, :nome, :ano, :tipo, 6.00, 1)'
)->execute([
    'nome' => 'Ensino Médio 2025',
    'ano' => ANO,
    'tipo' => 'bimestre',
]);
println('Regra acadêmica Ensino Médio 2025 criada.');
println('Pronto para popular as avaliações de 2025.');
