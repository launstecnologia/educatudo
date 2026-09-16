<?php
/**
 * Completa o diário das turmas 2025 já homologadas no Colégio Educa.
 *
 * A carga original só lançou alguns dias de chamada; o painel de Fechamento
 * acusava centenas de aulas da grade sem diário. Este script materializa as
 * aulas vencidas como finalizadas (sem reabrir o período).
 *
 * Uso (container PHP):
 *   php scripts/corrigir_diario_2025_educa.php
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

require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Models/Education/ClassDiary.php';

const TENANT_DB = 'educatudo_educa';
const ANO = 2025;

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
    fail('Abortado: DB_HOST=' . $host . ' não parece local.');
}

$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . TENANT_DB . ';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fail('Não conectou em ' . TENANT_DB . '. ' . $e->getMessage());
}

Database::setCurrentInstance(Database::createFromPdo($pdo, [
    'host' => $host,
    'port' => $port,
    'name' => TENANT_DB,
    'user' => $dbUser,
    'pass' => $dbPass,
]));

$db = Database::getInstance();
$diario = new ClassDiary();
$turmas = $db->fetchAll(
    'SELECT id, nome FROM turmas WHERE ano_letivo = :ano ORDER BY nome ASC',
    ['ano' => ANO]
) ?: [];

println('== Completar diário 2025 — Colégio Educa ==');
println('turmas=' . count($turmas));

$total = 0;
foreach ($turmas as $turma) {
    $tid = (int) ($turma['id'] ?? 0);
    if ($tid <= 0) {
        continue;
    }
    $antes = $diario->contarChamadasVencidas($tid, ANO . '-01-01', ANO . '-12-31');
    $criadas = $diario->completarSlotsVencidos($tid, ANO . '-01-01', ANO . '-12-31');
    $depois = $diario->contarChamadasVencidas($tid, ANO . '-01-01', ANO . '-12-31');
    $total += $criadas;
    println(sprintf(
        '  %s  pendentes %d → %d  (criadas=%d)',
        (string) ($turma['nome'] ?? $tid),
        $antes,
        $depois,
        $criadas
    ));
}

println('total aulas criadas=' . $total);
println('Pronto. No Fechamento 2025 a frequência homologada aparece Encerrado.');
exit(0);
