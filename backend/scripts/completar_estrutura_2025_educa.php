<?php
/**
 * Completa a estrutura do Colégio Educa local (2025), sem apagar alunos nem notas.
 * Curso, séries, salas, ambientes e grade horária.
 *
 * Uso (container PHP): php scripts/completar_estrutura_2025_educa.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';

const TENANT_DB = 'educatudo_educa';

const SLOTS = [
    ['07:30:00', '08:20:00'],
    ['08:20:00', '09:10:00'],
    ['09:30:00', '10:20:00'],
    ['10:20:00', '11:10:00'],
    ['11:10:00', '12:00:00'],
];

const GRADE = [
    1 => ['Gramática', 'Gramática', 'Matemática', 'Matemática', 'História'],
    2 => ['Literatura', 'Matemática', 'Física', 'Química', 'Geografia'],
    3 => ['Interpretação de texto', 'Matemática', 'Biologia', 'Língua Inglesa', 'Filosofia'],
    4 => ['Língua Inglesa', 'História', 'Geografia', 'Física', 'Sociologia'],
    5 => ['Biologia', 'Química', 'Arte', 'Educação Física', 'Educação Física'],
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

$pdo = new PDO(
    'mysql:host=' . $host . ';port=' . $port . ';dbname=' . TENANT_DB . ';charset=utf8mb4',
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

$curso = $pdo->query("SELECT id FROM curso WHERE nome = 'Ensino Médio' LIMIT 1")->fetch();
if (!$curso) {
    $pdo->prepare(
        'INSERT INTO curso (id, nome, tipo, possui_serie, descricao, ativo, ordem)
         VALUES (2, :nome, :tipo, 1, :descricao, 1, 1)'
    )->execute([
        'nome' => 'Ensino Médio',
        'tipo' => 'regular',
        'descricao' => 'Ensino Médio — Colégio Educa',
    ]);
    $cursoId = 2;
} else {
    $cursoId = (int) $curso['id'];
}
$pdo->prepare('UPDATE serie SET curso_id = :curso, ativo = 1 WHERE id IN (5, 6, 7)')->execute(['curso' => $cursoId]);
$pdo->prepare('UPDATE serie SET curso_id = :curso, ativo = 0 WHERE id = 8')->execute(['curso' => $cursoId]);
println('Curso Ensino Médio #' . $cursoId . ' ligado às séries 1ª, 2ª e 3ª.');

$tipos = [
    ['Ensino Médio', 'medio', 20],
];
foreach ($tipos as $tipo) {
    $pdo->prepare(
        'INSERT INTO tipos_curso (nome, slug, ordem, ativo)
         VALUES (:nome, :slug, :ordem, 1)
         ON DUPLICATE KEY UPDATE ativo = 1'
    )->execute(['nome' => $tipo[0], 'slug' => $tipo[1], 'ordem' => $tipo[2]]);
}
$tipoId = (int) $pdo->query("SELECT id FROM tipos_curso WHERE slug = 'medio' LIMIT 1")->fetchColumn();
$anosCurso = [
    5 => ['1º Ano', 'medio-1-ano', 10],
    6 => ['2º Ano', 'medio-2-ano', 20],
    7 => ['3º Ano', 'medio-3-ano', 30],
];
$cursoPorSerie = [];
$insCurso = $pdo->prepare(
    'INSERT INTO cursos (tipo_curso_id, nome, slug, ordem, ativo)
     VALUES (:tipo, :nome, :slug, :ordem, 1)
     ON DUPLICATE KEY UPDATE ativo = 1'
);
foreach ($anosCurso as $serieId => $meta) {
    $insCurso->execute([
        'tipo' => $tipoId,
        'nome' => $meta[0],
        'slug' => $meta[1],
        'ordem' => $meta[2],
    ]);
    $id = (int) $pdo->query('SELECT id FROM cursos WHERE slug = ' . $pdo->quote($meta[1]))->fetchColumn();
    $cursoPorSerie[$serieId] = $id;
}
println('Catálogo de curso/série do Ensino Médio preenchido.');

$ambientes = [
    ['EDUCA-LAB-01', 'Laboratório de Ciências', 'laboratorio', 30, 'B', '1'],
    ['EDUCA-LAB-02', 'Laboratório de Informática', 'laboratorio', 24, 'B', '1'],
    ['EDUCA-QUADRA', 'Quadra Poliesportiva', 'quadra', 80, 'C', 'Térreo'],
    ['EDUCA-BIBLIO', 'Biblioteca', 'biblioteca', 40, 'A', '1'],
    ['EDUCA-SECRET', 'Secretaria', 'secretaria', 8, 'A', 'Térreo'],
    ['EDUCA-CANTINA', 'Cantina', 'cantina', 60, 'C', 'Térreo'],
];
$insSala = $pdo->prepare(
    'INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, responsavel_nome, ativo)
     VALUES (:codigo, :nome, :tipo, :capacidade, :bloco, :andar, :responsavel, 1)
     ON DUPLICATE KEY UPDATE nome = VALUES(nome), tipo = VALUES(tipo), capacidade = VALUES(capacidade), ativo = 1'
);
foreach ($ambientes as $item) {
    $insSala->execute([
        'codigo' => $item[0],
        'nome' => $item[1],
        'tipo' => $item[2],
        'capacidade' => $item[3],
        'bloco' => $item[4],
        'andar' => $item[5],
        'responsavel' => 'Coordenação',
    ]);
}

$turmas = $pdo->query(
    'SELECT id, nome, serie_id FROM turmas WHERE ativo = 1 ORDER BY nome'
)->fetchAll();
if ($turmas === []) {
    fail('Nenhuma turma ativa.');
}
$updTurma = $pdo->prepare(
    'UPDATE turmas
        SET curso_novo_id = :curso, curso_id = :curso_serie, sala_padrao_id = :sala
      WHERE id = :id'
);
$n = 1;
foreach ($turmas as $turma) {
    $codigo = sprintf('EDUCA-SALA-%02d', $n);
    $insSala->execute([
        'codigo' => $codigo,
        'nome' => 'Sala ' . $turma['nome'],
        'tipo' => 'sala',
        'capacidade' => 40,
        'bloco' => 'A',
        'andar' => '1',
        'responsavel' => 'Coordenação',
    ]);
    $salaId = (int) $pdo->query('SELECT id FROM school_locations WHERE codigo = ' . $pdo->quote($codigo))->fetchColumn();
    $serieId = (int) $turma['serie_id'];
    $updTurma->execute([
        'curso' => $cursoId,
        'curso_serie' => $cursoPorSerie[$serieId] ?? null,
        'sala' => $salaId,
        'id' => (int) $turma['id'],
    ]);
    $n++;
}
println(count($turmas) . ' salas de aula e 6 ambientes ligados às turmas.');

$profs = $pdo->query('SELECT id, materias FROM professores WHERE ativo = 1')->fetchAll();
$profPorMateria = [];
foreach ($profs as $prof) {
    $nomes = json_decode((string) ($prof['materias'] ?? '[]'), true);
    if (!is_array($nomes)) {
        continue;
    }
    foreach ($nomes as $nome) {
        $chave = mb_strtolower(trim((string) $nome));
        if ($chave !== '') {
            $profPorMateria[$chave] = (int) $prof['id'];
        }
    }
}
$mats = $pdo->query('SELECT id, nome FROM materias WHERE ativo = 1')->fetchAll();
$materiaId = [];
foreach ($mats as $mat) {
    $materiaId[mb_strtolower(trim((string) $mat['nome']))] = (int) $mat['id'];
}

$insGrade = $pdo->prepare(
    'INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
     VALUES (:dia, :de, :ate, :turma, :prof, :mat, :periodo)'
);
$aulas = 0;
foreach ($turmas as $turma) {
    $turmaId = (int) $turma['id'];
    $pdo->prepare('DELETE FROM grade_horaria WHERE turma_id = :id')->execute(['id' => $turmaId]);
    foreach (GRADE as $dia => $nomes) {
        foreach ($nomes as $i => $nome) {
            $chave = mb_strtolower($nome);
            $mid = $materiaId[$chave] ?? 0;
            $pid = $profPorMateria[$chave] ?? 0;
            if ($mid <= 0 || $pid <= 0) {
                fail('Grade sem professor ou matéria: ' . $nome);
            }
            $insGrade->execute([
                'dia' => $dia,
                'de' => SLOTS[$i][0],
                'ate' => SLOTS[$i][1],
                'turma' => $turmaId,
                'prof' => $pid,
                'mat' => $mid,
                'periodo' => 'manha',
            ]);
            $aulas++;
        }
    }
}
println('Grade horária: ' . $aulas . ' aulas, de segunda a sexta, 7h30 às 12h.');
println('Estrutura de 2025 completa.');
