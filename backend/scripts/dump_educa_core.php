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

$tabelas = $pdo->query(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
     ORDER BY TABLE_NAME"
)->fetchAll(PDO::FETCH_COLUMN);

$views = $pdo->query(
    "SELECT TABLE_NAME FROM information_schema.VIEWS
     WHERE TABLE_SCHEMA = DATABASE()
     ORDER BY TABLE_NAME"
)->fetchAll(PDO::FETCH_COLUMN);

$ignorar = ['migrations_escolas' => true];
$tabelas = array_values(array_filter($tabelas, static function ($nome) use ($ignorar) {
    $nome = (string) $nome;
    if (isset($ignorar[$nome])) {
        return false;
    }
    if (strpos($nome, 'backup_') === 0) {
        return false;
    }
    return true;
}));

println('→ Tabelas: ' . count($tabelas) . ' | Views: ' . count($views));

$linhas = [];
$linhas[] = '-- EducaTudo — schema base do tenant (somente estrutura, sem dados)';
$linhas[] = '-- Gerado em ' . date('Y-m-d H:i:s');
$linhas[] = '-- Usado ao criar escola no Master (MysqlProvisioningService::aplicarSchemaBaseTenant).';
$linhas[] = '';
$linhas[] = 'SET NAMES utf8mb4;';
$linhas[] = "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';";
$linhas[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$linhas[] = 'SET UNIQUE_CHECKS = 0;';
$linhas[] = '';

$quote = static function (string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
};

foreach ($tabelas as $tabela) {
    $row = $pdo->query('SHOW CREATE TABLE ' . $quote((string) $tabela))->fetch(PDO::FETCH_ASSOC);
    $ddl = (string) ($row['Create Table'] ?? '');
    if ($ddl === '') {
        println("   AVISO: sem DDL para {$tabela}");
        continue;
    }
    $ddl = preg_replace('/AUTO_INCREMENT=\d+\s*/i', '', $ddl) ?? $ddl;
    $ddl = preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $ddl) ?? $ddl;
    $linhas[] = '--';
    $linhas[] = '-- ' . $tabela;
    $linhas[] = '--';
    $linhas[] = $ddl . ';';
    $linhas[] = '';
}

foreach ($views as $view) {
    $row = $pdo->query('SHOW CREATE VIEW ' . $quote((string) $view))->fetch(PDO::FETCH_ASSOC);
    $ddl = (string) ($row['Create View'] ?? '');
    if ($ddl === '') {
        continue;
    }
    $ddl = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/i', '', $ddl) ?? $ddl;
    $ddl = preg_replace('/^CREATE /i', 'CREATE OR REPLACE ', $ddl) ?? $ddl;
    $linhas[] = '-- view ' . $view;
    $linhas[] = $ddl . ';';
    $linhas[] = '';
}

$triggers = $pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC);
if ($triggers) {
    $linhas[] = 'DELIMITER $$';
    foreach ($triggers as $trg) {
        $nome = (string) ($trg['Trigger'] ?? '');
        $timing = (string) ($trg['Timing'] ?? '');
        $event = (string) ($trg['Event'] ?? '');
        $table = (string) ($trg['Table'] ?? '');
        $stmt = (string) ($trg['Statement'] ?? '');
        if ($nome === '' || $stmt === '') {
            continue;
        }
        $linhas[] = 'DROP TRIGGER IF EXISTS ' . $quote($nome) . '$$';
        $linhas[] = 'CREATE TRIGGER ' . $quote($nome) . " {$timing} {$event} ON " . $quote($table)
            . ' FOR EACH ROW ' . rtrim($stmt, '; ') . '$$';
        $linhas[] = '';
    }
    $linhas[] = 'DELIMITER ;';
    $linhas[] = '';
}

$linhas[] = 'SET UNIQUE_CHECKS = 1;';
$linhas[] = 'SET FOREIGN_KEY_CHECKS = 1;';
$linhas[] = '';

$conteudo = implode("\n", $linhas);
if (file_put_contents($outFile, $conteudo) === false) {
    fwrite(STDERR, "Falha ao escrever {$outFile}\n");
    exit(1);
}

println('→ Gravado: ' . $outFile . ' (' . number_format(strlen($conteudo)) . ' bytes)');
println('Pronto.');
