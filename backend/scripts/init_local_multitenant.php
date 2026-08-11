<?php
/**
 * Setup local multi-tenant: banco master + escola teste + migrations.
 *
 * Uso (dentro do container PHP):
 *   php scripts/init_local_multitenant.php
 *
 * Opções:
 *   --skip-tenant-migrations   só master + cadastro da escola (sem rodar migrations tenant)
 *   --skip-master-migrations   não roda *_master.sql (só schema base multi_tenant_master)
 *   --tenant-migrations-only   só migrations tenant (master + escola já existem)
 *   --force-school             recria cadastro da escola teste se slug já existir
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
require_once $basePath . '/app/Core/MysqlProvisioningService.php';
require_once $basePath . '/app/Core/MasterSecretVault.php';

$args = array_slice($argv, 1);
$skipTenantMigrations = in_array('--skip-tenant-migrations', $args, true);
$skipMasterMigrations = in_array('--skip-master-migrations', $args, true);
$tenantMigrationsOnly = in_array('--tenant-migrations-only', $args, true);
$forceSchool = in_array('--force-school', $args, true);

const SCHOOL_SLUG = 'colag';
const SCHOOL_NAME = 'Colag';
const SCHOOL_DOMAIN = 'colag.localhost';
const TENANT_DB = 'educatudo_colag';
const TENANT_USER = 'root';
const TENANT_PASS = 'root';

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function validateDbIdentifier(string $value, string $label): string
{
    if ($value === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $value) || strlen($value) > 64) {
        throw new InvalidArgumentException("Identificador MySQL inválido ({$label}): {$value}");
    }
    return $value;
}

function quoteDbName(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function connectPdo(string $host, int $port, string $db, string $user, string $pass): PDO
{
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    return $pdo;
}

function execSqlFile(PDO $pdo, string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException("Arquivo SQL não encontrado: {$path}");
    }
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        return;
    }
    if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
        $pdo->setAttribute(PDO::MYSQL_ATTR_MULTI_STATEMENTS, true);
    }
    $pdo->exec($sql);
}

function ensureTenantBaseSchema(PDO $tenantPdo, string $basePath): void
{
    $check = $tenantPdo->query("SHOW TABLES LIKE 'alunos'");
    if ($check && $check->rowCount() > 0) {
        return;
    }

    fwrite(STDERR, "Schema tenant ausente (tabela alunos). Importe educa_core.sql via mysql CLI.\n");
    fwrite(STDERR, "Ex.: docker compose exec -T mysql sh -c \"mysql -uroot -proot educatudo_colag\" < backend/database/educa_core.sql\n");
    exit(1);
}

function seedDadosMinimosTenant(PDO $tenantPdo): void
{
    $count = (int) $tenantPdo->query('SELECT COUNT(*) FROM ano_letivo WHERE ativo = 1')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $tenantPdo->exec(
        'INSERT INTO ano_letivo (ano, data_inicio, data_fim, ativo) VALUES (2026, "2026-02-01", "2026-12-15", 1)'
    );
    println('→ Ano letivo 2026 criado (seed local).');
}

function resolveEscolaId(PDO $masterPdo): int
{
    $existing = $masterPdo->prepare('SELECT id FROM escolas WHERE slug = ? LIMIT 1');
    $existing->execute([SCHOOL_SLUG]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        fwrite(STDERR, "Escola slug '" . SCHOOL_SLUG . "' não encontrada no master.\n");
        exit(1);
    }
    return (int) $row['id'];
}

function resetTenantDatabase(PDO $rootPdo, string $tenantDb): void
{
    $rootPdo->exec('DROP DATABASE IF EXISTS ' . quoteDbName($tenantDb));
    $rootPdo->exec(
        'CREATE DATABASE ' . quoteDbName($tenantDb) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
}

function clearTenantMigrationRegistry(PDO $masterPdo, int $escolaId): void
{
    try {
        $masterPdo->prepare('DELETE FROM migrations_escolas WHERE escola_id = ?')->execute([$escolaId]);
    } catch (PDOException $e) {
        // tabela pode não existir ainda
    }
}

function ensureMultiTenantBase(PDO $masterPdo, string $migrationsDir): void
{
    $baseFile = $migrationsDir . '/multi_tenant_master.sql';
    println('→ Aplicando schema base master (multi_tenant_master.sql)...');
    execSqlFile($masterPdo, $baseFile);

    $masterPdo->exec("
        CREATE TABLE IF NOT EXISTS migrations_master (
            migration_name VARCHAR(255) NOT NULL PRIMARY KEY,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    $stmt = $masterPdo->prepare('INSERT IGNORE INTO migrations_master (migration_name) VALUES (?)');
    $stmt->execute(['multi_tenant_master.sql']);
}

function ensureMasterSchemaMinimo(PDO $masterPdo, string $migrationsDir): void
{
    $patches = [
        '2026_07_09_usuarios_master_avatar_url_master.sql',
    ];

    foreach ($patches as $file) {
        $path = $migrationsDir . '/' . $file;
        if (!is_file($path)) {
            continue;
        }
        $check = $masterPdo->prepare('SELECT 1 FROM migrations_master WHERE migration_name = ? LIMIT 1');
        $check->execute([$file]);
        if ($check->fetch()) {
            continue;
        }
        execSqlFile($masterPdo, $path);
        $masterPdo->prepare('INSERT IGNORE INTO migrations_master (migration_name) VALUES (?)')->execute([$file]);
        println('→ Patch master aplicado: ' . $file);
    }
}

println('== EducaTudo — init local multi-tenant ==');

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$masterDb = validateDbIdentifier((string) env('DB_NAME', 'educatudo_master'), 'DB_NAME');
$tenantDb = validateDbIdentifier(TENANT_DB, 'TENANT_DB');
$adminUser = (string) env('DB_ADMIN_USER', env('DB_USER', 'root'));
$adminPass = (string) env('DB_ADMIN_PASS', env('DB_PASS', 'root'));

if ($masterDb === '') {
    fwrite(STDERR, "DB_NAME vazio no .env\n");
    exit(1);
}

$hostsLocaisPermitidos = ['mysql', 'localhost', '127.0.0.1', '::1'];
if ($forceSchool && !in_array($host, $hostsLocaisPermitidos, true)) {
    fwrite(STDERR, "Refusing --force-school: DB_HOST={$host} não parece ambiente local.\n");
    exit(1);
}

println("MySQL: {$host}:{$port} | master={$masterDb}");

$rootPdo = connectPdo($host, $port, 'mysql', $adminUser, $adminPass);
$rootPdo->exec(
    'CREATE DATABASE IF NOT EXISTS ' . quoteDbName($masterDb) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
);
$rootPdo->exec(
    'CREATE DATABASE IF NOT EXISTS ' . quoteDbName($tenantDb) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
);

$masterPdo = connectPdo($host, $port, $masterDb, $adminUser, $adminPass);
$migrationsDir = $basePath . '/database/migrations';

if ($tenantMigrationsOnly) {
    $escolaId = resolveEscolaId($masterPdo);
    println("→ Migrations tenant apenas (escola id={$escolaId}).");
} else {
    ensureMultiTenantBase($masterPdo, $migrationsDir);
    ensureMasterSchemaMinimo($masterPdo, $migrationsDir);

    if (!$skipMasterMigrations) {
        println('→ Rodando migrations master pendentes...');
        try {
            $masterExecuted = MysqlProvisioningService::runMasterMigrations($masterPdo);
            println('   Master: ' . count($masterExecuted) . ' migration(s) aplicada(s).');
        } catch (Throwable $e) {
            println('   AVISO: migrations master parciais — ' . $e->getMessage());
            println('   Continue pelo painel http://master.localhost/master/migrations');
        }
    } else {
        println('→ Migrations master extras ignoradas (--skip-master-migrations).');
    }

    // Escola teste
    $existing = $masterPdo->prepare('SELECT id FROM escolas WHERE slug = ? LIMIT 1');
    $existing->execute([SCHOOL_SLUG]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    if ($row && !$forceSchool) {
        $escolaId = (int) $row['id'];
        $domRow = $masterPdo->prepare('SELECT dominio FROM escolas WHERE id = ?');
        $domRow->execute([$escolaId]);
        $domAtual = (string) ($domRow->fetchColumn() ?: '');
        if ($domAtual !== SCHOOL_DOMAIN) {
            $masterPdo->prepare('UPDATE escolas SET dominio = ?, nome = ? WHERE id = ?')
                ->execute([SCHOOL_DOMAIN, SCHOOL_NAME, $escolaId]);
            println("→ Domínio atualizado para " . SCHOOL_DOMAIN);
        }
        println("→ Escola slug '" . SCHOOL_SLUG . "' já existe (id={$escolaId}). Use --force-school para recriar config de banco.");
    } else {
        if ($row && $forceSchool) {
            $escolaId = (int) $row['id'];
            $masterPdo->prepare('UPDATE escolas SET dominio = ?, nome = ? WHERE id = ?')
                ->execute([SCHOOL_DOMAIN, SCHOOL_NAME, $escolaId]);
            $masterPdo->prepare('DELETE FROM config_escolas_banco WHERE escola_id = ?')->execute([$escolaId]);
            resetTenantDatabase($rootPdo, $tenantDb);
            clearTenantMigrationRegistry($masterPdo, $escolaId);
            println("→ Escola existente id={$escolaId} — tenant recriado do zero.");
        } else {
            $ins = $masterPdo->prepare(
                'INSERT INTO escolas (nome, slug, dominio, ativo) VALUES (?, ?, ?, 1)'
            );
            $ins->execute([SCHOOL_NAME, SCHOOL_SLUG, SCHOOL_DOMAIN]);
            $escolaId = (int) $masterPdo->lastInsertId();
            println("→ Escola criada: id={$escolaId}, slug=" . SCHOOL_SLUG);
        }

        $senhaCifrada = MasterSecretVault::encryptDbPassword(TENANT_PASS);
        $cfg = $masterPdo->prepare(
            'INSERT INTO config_escolas_banco (escola_id, host, porta, nome_banco, usuario, senha_criptografada)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE host=VALUES(host), porta=VALUES(porta), nome_banco=VALUES(nome_banco),
             usuario=VALUES(usuario), senha_criptografada=VALUES(senha_criptografada)'
        );
        $cfg->execute([$escolaId, $host, $port, $tenantDb, TENANT_USER, $senhaCifrada]);
        println('→ Config de banco da escola registrada no master.');
    }
}

if (!$skipTenantMigrations) {
    println('→ Rodando migrations tenant (pode levar alguns minutos)...');
    $tenantPdo = connectPdo($host, $port, $tenantDb, TENANT_USER, TENANT_PASS);
    ensureTenantBaseSchema($tenantPdo, $basePath);
    if ($tenantMigrationsOnly) {
        seedDadosMinimosTenant($tenantPdo);
    }
    try {
        MysqlProvisioningService::runTenantMigrations(
            $masterPdo,
            $tenantPdo,
            $escolaId,
            $tenantMigrationsOnly
        );
        println('   Migrations tenant concluídas.');
    } catch (Throwable $e) {
        println('   AVISO: migrations tenant parciais — ' . $e->getMessage());
        println('   Continue pelo painel http://master.localhost/master/migrations');
    }
}

println('');
println('Pronto!');
println('');
println('Master:  http://master.localhost');
println('         (redireciona / → /master — criar admin na 1ª visita)');
println('');
println('Colag:   http://colag.localhost/');
println('         slug=' . SCHOOL_SLUG . ' | dominio=' . SCHOOL_DOMAIN);
println('');
println('MySQL:   127.0.0.1:3307');
println('         master=' . $masterDb . ' | tenant=' . $tenantDb . ' | user=root | pass=root');
