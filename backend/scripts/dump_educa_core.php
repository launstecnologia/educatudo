<?php
/**
 * Gera backend/database/educa_core.sql (somente estrutura) a partir de um tenant já migrado.
 *
 * Uso:
 *   php scripts/dump_educa_core.php
 *   php scripts/dump_educa_core.php --aplicar-migrations
 *
 * Variáveis (opcional): DUMP_DB_HOST, DUMP_DB_PORT, DUMP_DB_SOCKET, DUMP_DB_USER, DUMP_DB_PASS, DUMP_DB_NAME
 * Default local: socket /tmp/mysql.sock, user root, banco educatudo_schema_tmp
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);
require_once $basePath . '/app/Core/MysqlProvisioningService.php';
require_once $basePath . '/app/Painel/Master/Services/SchemaTenantService.php';

$aplicarMigrations = in_array('--aplicar-migrations', array_slice($argv, 1), true);

$host = getenv('DUMP_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DUMP_DB_PORT') ?: '3306');
$socket = getenv('DUMP_DB_SOCKET') ?: '/tmp/mysql.sock';
$user = getenv('DUMP_DB_USER') ?: 'root';
$pass = getenv('DUMP_DB_PASS') !== false ? (string) getenv('DUMP_DB_PASS') : '';
$dbName = getenv('DUMP_DB_NAME') ?: 'educatudo_schema_tmp';
$outFile = $basePath . '/database/educa_core.sql';

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function connectDumpPdo(string $host, int $port, string $socket, string $db, string $user, string $pass): PDO
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if ($socket !== '' && is_file($socket)) {
        $dsn = "mysql:unix_socket={$socket};dbname={$db};charset=utf8mb4";
        return new PDO($dsn, $user, $pass, $options);
    }
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, $options);
}

println("== Dump schema tenant → educa_core.sql ==");
$pdo = connectDumpPdo($host, $port, $socket, $dbName, $user, $pass);
$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

if ($aplicarMigrations) {
    println('→ Aplicando migrations tenant (ignorando conflitos de schema)...');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations_escolas (
            escola_id INT NOT NULL,
            migration_name VARCHAR(255) NOT NULL,
            PRIMARY KEY (escola_id, migration_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    MysqlProvisioningService::runTenantMigrations($pdo, $pdo, 1, true);
    println('   Migrations concluídas.');
}

$conteudo = \App\Painel\Master\Services\SchemaTenantService::gerar($pdo, $dbName);
println('→ Schema lido de ' . $dbName);
if (file_put_contents($outFile, $conteudo) === false) {
    fwrite(STDERR, "Falha ao escrever {$outFile}\n");
    exit(1);
}

println('→ Gravado: ' . $outFile . ' (' . number_format(strlen($conteudo)) . ' bytes)');
println('Pronto.');
