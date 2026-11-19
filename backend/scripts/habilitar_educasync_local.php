<?php
/**
 * Habilita EducaSync no tenant Colag para testes locais.
 * Uso (no container PHP):
 *   php scripts/habilitar_educasync_local.php
 *
 * - Liga module_educasync em config_layout
 * - Aplica migration tenant se as tabelas ainda não existem
 * - Não toca em .env
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$dbName = getenv('EDUCASYNC_LOCAL_DB') ?: 'educatudo_colag';
$host = getenv('DB_HOST') ?: 'mysql';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';
$port = (int) (getenv('DB_PORT') ?: 3306);

echo "== EducaSync local · banco {$dbName} ==\n";

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec(
    "INSERT INTO config_layout (config_key, config_value) VALUES ('module_educasync', '1')
     ON DUPLICATE KEY UPDATE config_value = '1'"
);
echo "→ module_educasync = 1\n";

$tem = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'educasync_cenarios'")->fetchColumn();
if ($tem === 0) {
    $sqlFile = $root . '/database/migrations/2026_11_12_educasync.sql';
    if (!is_file($sqlFile)) {
        fwrite(STDERR, "Migration não encontrada: {$sqlFile}\n");
        exit(1);
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "Migration vazia.\n");
        exit(1);
    }
    // O arquivo usa PREPARE/EXECUTE — roda como multi-statement.
    $pdo->exec($sql);
    echo "→ migration 2026_11_12_educasync aplicada\n";
} else {
    echo "→ tabelas educasync_* já existem\n";
}

$masterName = getenv('DB_NAME') ?: 'educatudo_master';
try {
    $master = new PDO(
        "mysql:host={$host};port={$port};dbname={$masterName};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $sqlMaster = $root . '/database/migrations/2026_11_12_tudicoins_educasync_master.sql';
    if (is_file($sqlMaster)) {
        $master->exec((string) file_get_contents($sqlMaster));
        echo "→ itens TudiCoins EducaSync no master (idempotente)\n";
    }
} catch (Throwable $e) {
    echo "→ aviso master TudiCoins: " . $e->getMessage() . "\n";
}

echo "OK. Abra /admin/educasync na Colag.\n";
