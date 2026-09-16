<?php
/**
 * Após o Conselho 2025: materializa ficha/histórico dos alunos deliberados
 * e grava "Aprovado pelo Conselho" na escolarização.
 *
 * Uso: php scripts/ajustar_conselho_frequencia_2025_educa.php
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
require_once $basePath . '/app/Modulos/vida-escolar/Services/VidaEscolarService.php';

const TENANT_DB = 'educatudo_educa';
const ANO = 2025;
$host = (string) env('DB_HOST', 'mysql');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Abortado: DB_HOST={$host}\n");
    exit(1);
}
$pdo = new PDO(
    'mysql:host=' . $host . ';port=' . (int) env('DB_PORT', 3306) . ';dbname=' . TENANT_DB . ';charset=utf8mb4',
    (string) env('DB_USER', 'root'),
    (string) env('DB_PASS', 'root'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
Database::setCurrentInstance(Database::createFromPdo($pdo, [
    'host' => $host, 'port' => (int) env('DB_PORT', 3306), 'name' => TENANT_DB,
    'user' => (string) env('DB_USER', 'root'), 'pass' => (string) env('DB_PASS', 'root'),
]));
$db = Database::getInstance();
$admin = $db->fetch("SELECT id, nome, tipo FROM usuarios WHERE tipo = 'admin_escola' AND ativo = 1 ORDER BY id ASC LIMIT 1") ?: [];
$adminUser = [
    'id' => (int) ($admin['id'] ?? 1),
    'nome' => (string) ($admin['nome'] ?? 'Coordenação'),
    'tipo' => (string) ($admin['tipo'] ?? 'admin_escola'),
];
$vida = new \App\Modulos\VidaEscolar\Services\VidaEscolarService();

$faltando = $db->fetchAll(
    "SELECT ra.aluno_id, ra.turma_id, ra.situacao
     FROM resultado_academico ra
     LEFT JOIN boletim_fichas f
       ON f.aluno_id = ra.aluno_id AND f.turma_id = ra.turma_id AND f.ano_letivo = ra.ano_letivo
     WHERE ra.ano_letivo = :ano AND ra.periodo_tipo = 'ano' AND ra.status = 'homologado'
       AND ra.situacao IN ('aprovado_conselho', 'reprovado_rendimento', 'reprovado_frequencia')
       AND f.id IS NULL",
    ['ano' => ANO]
) ?: [];
echo 'fichas faltando (conselho/retenção): ' . count($faltando) . PHP_EOL;
foreach ($faltando as $row) {
    $aid = (int) $row['aluno_id'];
    $tid = (int) $row['turma_id'];
    $ok = $vida->garantirFicha($aid, $tid, ANO, $adminUser['id']);
    if (empty($ok['success'])) {
        echo "  falha ficha aluno {$aid}: " . (string) ($ok['error'] ?? '') . PHP_EOL;
        continue;
    }
    $fid = (int) $ok['id'];
    $vida->sincronizarDeEventosGerados($aid, $adminUser, null, null, $fid, false, false);
    $vida->homologarFicha($fid, $adminUser);
    echo "  ficha {$fid} aluno {$aid} ({$row['situacao']})" . PHP_EOL;
}

$fichas = $db->fetchAll(
    'SELECT id FROM boletim_fichas WHERE ano_letivo = :ano',
    ['ano' => ANO]
) ?: [];
foreach ($fichas as $f) {
    $vida->sincronizarEscolarizacaoDaFicha((int) $f['id']);
}
echo 'escolarização sincronizada: ' . count($fichas) . ' fichas' . PHP_EOL;

$res = $db->fetchAll(
    "SELECT resultado, COUNT(*) n FROM escolarizacao_anos
     WHERE ano_letivo = :ano AND origem = 'interno'
     GROUP BY resultado ORDER BY n DESC",
    ['ano' => (string) ANO]
) ?: [];
foreach ($res as $r) {
    echo '  escolarização ' . $r['resultado'] . ': ' . $r['n'] . PHP_EOL;
}
