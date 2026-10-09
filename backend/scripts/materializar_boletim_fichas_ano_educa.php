<?php
/**
 * Materializa fichas oficiais da Vida Escolar (boletim) para um ano letivo.
 *
 * O seed/fechamento 2025 gera notas e homologação, mas só reescreve fichas
 * que já existem. Notas da Coordenação (Exibir = Boletim) precisa das fichas
 * em boletim_fichas — este script cria/sincroniza uma por aluno com turma.
 *
 * Uso (container PHP, banco da escola Educa):
 *   php scripts/materializar_boletim_fichas_ano_educa.php
 *   php scripts/materializar_boletim_fichas_ano_educa.php 2025
 *   php scripts/materializar_boletim_fichas_ano_educa.php 2025 --et25
 *   php scripts/materializar_boletim_fichas_ano_educa.php 2025 --db=educatudo_educa
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

ini_set('memory_limit', '1024M');
set_time_limit(0);

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');
if (!defined('TENANT_SLUG')) {
    define('TENANT_SLUG', 'educa');
}

require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Modulos/vida-escolar/Services/VidaEscolarService.php';

use App\Modulos\VidaEscolar\Services\VidaEscolarService;

$tenantDb = 'educatudo_educa';
$ano = 2025;
$somenteEt25 = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--et25') {
        $somenteEt25 = true;
        continue;
    }
    if (str_starts_with($arg, '--db=')) {
        $tenantDb = preg_replace('/[^a-zA-Z0-9_]/', '', substr($arg, 5)) ?: $tenantDb;
        continue;
    }
    if (ctype_digit($arg)) {
        $ano = (int) $arg;
    }
}

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

try {
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $tenantDb . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    Database::setCurrentInstance(Database::createFromPdo($pdo, [
        'host' => $host,
        'port' => $port,
        'name' => $tenantDb,
        'user' => $dbUser,
        'pass' => $dbPass,
    ]));
} catch (Throwable $e) {
    fail('Não conectou em ' . $tenantDb . '. ' . $e->getMessage());
}

$db = Database::getInstance();
$admin = $db->fetch(
    "SELECT id, nome, tipo FROM usuarios
     WHERE email IN ('admin@educateste.local', 'admin@educa.local')
        OR tipo = 'admin_escola'
     ORDER BY CASE WHEN email LIKE 'admin@%' THEN 0 ELSE 1 END, id ASC
     LIMIT 1"
);
$adminUser = [
    'id' => (int) ($admin['id'] ?? 0),
    'nome' => (string) ($admin['nome'] ?? 'Admin'),
    'tipo' => (string) ($admin['tipo'] ?? 'admin_escola'),
];

$params = ['ano' => $ano];
$filtroEt25 = '';
if ($somenteEt25) {
    $filtroEt25 = " AND t.observacoes LIKE 'ET25 %'";
}

$alunos = $db->fetchAll(
    "SELECT DISTINCT a.id, a.nome, a.turma_id, t.nome AS turma_nome
     FROM alunos a
     INNER JOIN turmas t ON t.id = a.turma_id
     WHERE t.ativo = 1 AND t.ano_letivo = :ano
       AND (a.ativo = 1 OR a.ativo IS NULL)
       {$filtroEt25}
     ORDER BY t.nome ASC, a.nome ASC",
    $params
) ?: [];

if ($alunos === [] && $db->fetch("SHOW TABLES LIKE 'matricula'")) {
    $alunos = $db->fetchAll(
        "SELECT DISTINCT a.id, a.nome, m.turma_id, t.nome AS turma_nome
         FROM matricula m
         INNER JOIN alunos a ON a.id = m.aluno_id
         INNER JOIN turmas t ON t.id = m.turma_id
         INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
         WHERE al.ano = :ano AND t.ano_letivo = :ano
           AND m.status IN ('ativa', 'concluido', 'transferido')
           {$filtroEt25}
         ORDER BY t.nome ASC, a.nome ASC",
        $params
    ) ?: [];
}

println('== Materializar boletim (Vida Escolar) — ' . $tenantDb . ' · ano ' . $ano . ($somenteEt25 ? ' · só ET25' : '') . ' ==');
println('Alunos candidatos: ' . count($alunos));

if ($alunos === []) {
    fail('Nenhum aluno encontrado. Rode antes o seed/import do ano.');
}

$vida = new VidaEscolarService();
if (!$vida->model()->schemaPronto()) {
    fail('Schema boletim_fichas ausente. Rode a migration 2026_08_25_vida_escolar.sql.');
}

$criadas = 0;
$atualizadas = 0;
$puladas = 0;
$erros = 0;

foreach ($alunos as $i => $row) {
    $alunoId = (int) ($row['id'] ?? 0);
    if ($alunoId <= 0) {
        continue;
    }
    try {
        $res = $vida->materializarFichaOficialSeFaltar($alunoId, $adminUser);
        if (empty($res['success'])) {
            $puladas++;
            continue;
        }
        if ((int) ($res['id'] ?? 0) <= 0) {
            $puladas++;
            continue;
        }
        if (!empty($res['criada'])) {
            $criadas++;
        } else {
            $atualizadas++;
        }
    } catch (Throwable $e) {
        $erros++;
        error_log('materializar boletim aluno #' . $alunoId . ': ' . $e->getMessage());
    }
    if (($i + 1) % 50 === 0) {
        println('  … ' . ($i + 1) . '/' . count($alunos));
    }
}

$fichasAno = $db->fetch(
    'SELECT COUNT(*) AS n FROM boletim_fichas WHERE ano_letivo = :ano',
    ['ano' => $ano]
);

println('');
println('Criadas:      ' . $criadas);
println('Atualizadas:  ' . $atualizadas);
println('Puladas:      ' . $puladas);
println('Erros:        ' . $erros);
println('Fichas no ano: ' . (int) ($fichasAno['n'] ?? 0));
println('');
println('Depois: Admin → Relatórios → Notas da Coordenação → Exibir: Boletim → ano ' . $ano);
exit($erros > 0 ? 2 : 0);
