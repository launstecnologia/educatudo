<?php
/**
 * Corrige snapshots 2025 do Colégio Educa em que o aluno foi homologado
 * ainda em recuperação/exame. O fechamento oficial só admite resultado
 * definitivo (aprovado ou reprovado). No demo, esse aluno fez recuperação
 * e passou → aprovado_recuperacao.
 *
 * Uso (container PHP):
 *   php scripts/corrigir_homologacao_recuperacao_2025_educa.php
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

const TENANT_DB = 'educatudo_educa';
const ANO = 2025;
const SIT_NOVA = 'aprovado_recuperacao';
const ROTULO_NOVO = 'Aprovado após recuperação';

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

$rows = $db->fetchAll(
    "SELECT id, aluno_id, turma_id, situacao, snapshot_json
       FROM resultado_academico
      WHERE ano_letivo = :ano AND periodo_tipo = 'ano'
        AND status = 'homologado'
        AND situacao IN ('recuperacao', 'exame_final')",
    ['ano' => ANO]
) ?: [];

println('== Corrigir homologação em recuperação 2025 — Colégio Educa ==');
println('registros=' . count($rows));

$ok = 0;
foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $snap = json_decode((string) ($row['snapshot_json'] ?? ''), true);
    if (!is_array($snap)) {
        $snap = [];
    }
    $snap['situacao'] = SIT_NOVA;
    $snap['rotulo'] = ROTULO_NOVO;
    if (isset($snap['avaliado']) && is_array($snap['avaliado'])) {
        $snap['avaliado']['situacao'] = SIT_NOVA;
        $snap['avaliado']['rotulo'] = ROTULO_NOVO;
    }
    $json = json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $db->query(
        'UPDATE resultado_academico
            SET situacao = :sit, rotulo = :rotulo, snapshot_json = :snap
          WHERE id = :id',
        [
            'sit' => SIT_NOVA,
            'rotulo' => ROTULO_NOVO,
            'snap' => $json,
            'id' => $id,
        ]
    );
    $db->query(
        "UPDATE resultado_academico_itens
            SET situacao = :sit, rotulo = :rotulo
          WHERE resultado_id = :id AND situacao IN ('recuperacao', 'exame_final')",
        [
            'sit' => SIT_NOVA,
            'rotulo' => ROTULO_NOVO,
            'id' => $id,
        ]
    );
    $ok++;
    println('  aluno #' . (int) ($row['aluno_id'] ?? 0) . ' turma #' . (int) ($row['turma_id'] ?? 0)
        . ' ' . (string) ($row['situacao'] ?? '') . ' → ' . SIT_NOVA);
}

println('atualizados=' . $ok);
println('ok');
