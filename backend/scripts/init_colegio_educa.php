<?php
/**
 * Provisiona a escola local "Colégio Educa" a partir do schema do Colag.
 *
 * Uso (container PHP):
 *   php scripts/init_colegio_educa.php
 *   php scripts/init_colegio_educa.php --force
 *
 * --force recria o banco educatudo_educa (só em host local).
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
require_once $basePath . '/app/Core/MasterSecretVault.php';

$force = in_array('--force', array_slice($argv, 1), true);

const SCHOOL_SLUG = 'educa';
const SCHOOL_NAME = 'Colégio Educa';
const SCHOOL_DOMAIN = 'educa.localhost';
const TENANT_DB = 'educatudo_educa';
const SOURCE_DB = 'educatudo_colag';
const SOURCE_SLUG = 'colag';
const TENANT_USER = 'root';
const TENANT_PASS = 'root';

const CATALOG_TABLES = [
    'materias',
    'bncc_habilidades',
    'ocorrencias_categorias',
    'admin_perfis_permissao',
    'secretaria_modelos_documentos',
    'secretaria_declaracoes_layouts',
    'matricula_contrato_regras',
    'resultado_documento_layouts',
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

println('== EducaTudo — provisionar Colégio Educa ==');

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$masterDb = validateDbIdentifier((string) env('DB_NAME', 'educatudo_master'), 'DB_NAME');
$tenantDb = validateDbIdentifier(TENANT_DB, 'TENANT_DB');
$sourceDb = validateDbIdentifier(SOURCE_DB, 'SOURCE_DB');
$adminUser = (string) env('DB_ADMIN_USER', env('DB_USER', 'root'));
$adminPass = (string) env('DB_ADMIN_PASS', env('DB_PASS', 'root'));

$hostsLocais = ['mysql', 'localhost', '127.0.0.1', '::1'];
if (!in_array($host, $hostsLocais, true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}
if ($force && !in_array($host, $hostsLocais, true)) {
    fail("Refusing --force: DB_HOST={$host} não parece ambiente local.");
}

$rootPdo = connectPdo($host, $port, 'mysql', $adminUser, $adminPass);
$srcExists = $rootPdo->query(
    'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $rootPdo->quote($sourceDb)
)->fetchColumn();
if (!$srcExists) {
    fail("Banco origem {$sourceDb} não encontrado. Suba o Colag local antes.");
}

$masterPdo = connectPdo($host, $port, $masterDb, $adminUser, $adminPass);

$colag = $masterPdo->prepare('SELECT id FROM escolas WHERE slug = ? LIMIT 1');
$colag->execute([SOURCE_SLUG]);
$colagId = (int) ($colag->fetchColumn() ?: 0);
if ($colagId <= 0) {
    fail("Escola slug '" . SOURCE_SLUG . "' não encontrada no master.");
}

$existing = $masterPdo->prepare('SELECT id FROM escolas WHERE slug = ? LIMIT 1');
$existing->execute([SCHOOL_SLUG]);
$escolaId = (int) ($existing->fetchColumn() ?: 0);

if ($escolaId > 0) {
    $masterPdo->prepare(
        'UPDATE escolas SET nome = ?, dominio = ?, ativo = 1, dns_status = ? WHERE id = ?'
    )->execute([SCHOOL_NAME, SCHOOL_DOMAIN, 'wildcard_ok', $escolaId]);
    println("→ Escola existente id={$escolaId} atualizada (slug=" . SCHOOL_SLUG . ').');
} else {
    $masterPdo->prepare(
        'INSERT INTO escolas (nome, slug, dominio, ativo, dns_status, ssl_status)
         VALUES (?, ?, ?, 1, ?, ?)'
    )->execute([SCHOOL_NAME, SCHOOL_SLUG, SCHOOL_DOMAIN, 'wildcard_ok', 'nao_verificado']);
    $escolaId = (int) $masterPdo->lastInsertId();
    println("→ Escola criada: id={$escolaId}, slug=" . SCHOOL_SLUG . ', dominio=' . SCHOOL_DOMAIN);
}

$clonouSchema = false;
$tenantJaExiste = (bool) $rootPdo->query(
    'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $rootPdo->quote($tenantDb)
)->fetchColumn();
$tabelasExistentes = 0;
if ($tenantJaExiste) {
    $tabelasExistentes = (int) $rootPdo->query(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ' . $rootPdo->quote($tenantDb)
    )->fetchColumn();
}

if ($tenantJaExiste && !$force && $tabelasExistentes > 0) {
    println("→ Banco {$tenantDb} já existe ({$tabelasExistentes} tabelas). Use --force para recriar o schema.");
} else {
    $clonouSchema = true;
    if ($tenantJaExiste && $force) {
        $rootPdo->exec('DROP DATABASE ' . quoteDbName($tenantDb));
        println("→ Banco {$tenantDb} removido (--force).");
    }
    if (!$tenantJaExiste || $force || $tabelasExistentes === 0) {
        $rootPdo->exec(
            'CREATE DATABASE IF NOT EXISTS ' . quoteDbName($tenantDb) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }
    println("→ Banco {$tenantDb} criado. Clonando schema de {$sourceDb}...");

    $srcPdo = connectPdo($host, $port, $sourceDb, $adminUser, $adminPass);
    $dstPdo = connectPdo($host, $port, $tenantDb, $adminUser, $adminPass);
    $dstPdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $dstPdo->exec('SET UNIQUE_CHECKS=0');

    $objetos = $srcPdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) ?: [];
    $tabelas = [];
    $views = [];
    foreach ($objetos as $obj) {
        $nome = (string) ($obj[0] ?? '');
        $tipo = strtoupper((string) ($obj[1] ?? 'BASE TABLE'));
        if ($nome === '') {
            continue;
        }
        if ($tipo === 'VIEW') {
            $views[] = $nome;
        } else {
            $tabelas[] = $nome;
        }
    }

    $criadas = 0;
    foreach ($tabelas as $tabela) {
        $row = $srcPdo->query('SHOW CREATE TABLE ' . quoteDbName($tabela))->fetch(PDO::FETCH_ASSOC);
        $ddl = (string) ($row['Create Table'] ?? '');
        if ($ddl === '') {
            println("   AVISO: sem DDL para {$tabela}");
            continue;
        }
        $ddl = preg_replace('/AUTO_INCREMENT=\d+/i', 'AUTO_INCREMENT=1', $ddl) ?? $ddl;
        $dstPdo->exec($ddl);
        $criadas++;
    }
    println("   Tabelas: {$criadas}");

    $viewsOk = 0;
    foreach ($views as $view) {
        try {
            $row = $srcPdo->query('SHOW CREATE VIEW ' . quoteDbName($view))->fetch(PDO::FETCH_ASSOC);
            $ddl = (string) ($row['Create View'] ?? '');
            if ($ddl === '') {
                continue;
            }
            $ddl = preg_replace('/\b' . preg_quote($sourceDb, '/') . '\b/', $tenantDb, $ddl) ?? $ddl;
            $ddl = preg_replace('/^CREATE ALGORITHM/i', 'CREATE ALGORITHM', $ddl) ?? $ddl;
            $ddl = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/', '', $ddl) ?? $ddl;
            $dstPdo->exec($ddl);
            $viewsOk++;
        } catch (Throwable $e) {
            println('   AVISO view ' . $view . ': ' . $e->getMessage());
        }
    }
    if ($views !== []) {
        println("   Views: {$viewsOk}/" . count($views));
    }

    foreach (CATALOG_TABLES as $catalogo) {
        $existe = $srcPdo->query('SHOW TABLES LIKE ' . $srcPdo->quote($catalogo))->fetchColumn();
        if (!$existe) {
            println("   catálogo {$catalogo}: ausente na origem, ignorado");
            continue;
        }
        $n = (int) $rootPdo->exec(
            'INSERT INTO ' . quoteDbName($tenantDb) . '.' . quoteDbName($catalogo)
            . ' SELECT * FROM ' . quoteDbName($sourceDb) . '.' . quoteDbName($catalogo)
        );
        println("   catálogo {$catalogo}: {$n} linha(s)");
    }

    $dstPdo->exec('SET UNIQUE_CHECKS=1');
    $dstPdo->exec('SET FOREIGN_KEY_CHECKS=1');
    println('→ Clone de schema concluído.');
}

$senhaCifrada = MasterSecretVault::encryptDbPassword(TENANT_PASS);
$cfg = $masterPdo->prepare(
    'INSERT INTO config_escolas_banco (escola_id, host, porta, nome_banco, usuario, senha_criptografada)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE host=VALUES(host), porta=VALUES(porta), nome_banco=VALUES(nome_banco),
     usuario=VALUES(usuario), senha_criptografada=VALUES(senha_criptografada)'
);
$cfg->execute([$escolaId, $host, $port, $tenantDb, TENANT_USER, $senhaCifrada]);
println('→ Config de banco registrada no master.');

$masterPdo->prepare('DELETE FROM config_escolas_layout WHERE escola_id = ?')->execute([$escolaId]);
$masterPdo->prepare(
    'INSERT INTO config_escolas_layout (escola_id, config_key, config_value)
     SELECT ?, config_key, config_value FROM config_escolas_layout WHERE escola_id = ?'
)->execute([$escolaId, $colagId]);
$branding = [
    'school_name' => SCHOOL_NAME,
    'escola_nome' => SCHOOL_NAME,
    'site_name' => SCHOOL_NAME,
];
$updLayout = $masterPdo->prepare(
    'INSERT INTO config_escolas_layout (escola_id, config_key, config_value) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)'
);
foreach ($branding as $k => $v) {
    $updLayout->execute([$escolaId, $k, $v]);
}
println('→ Layout/módulos copiados do Colag.');

if ($clonouSchema) {
    try {
        $masterPdo->prepare('DELETE FROM migrations_escolas WHERE escola_id = ?')->execute([$escolaId]);
        $masterPdo->prepare(
            'INSERT INTO migrations_escolas (escola_id, migration_name, executed_at)
             SELECT ?, migration_name, executed_at FROM migrations_escolas WHERE escola_id = ?'
        )->execute([$escolaId, $colagId]);
        println('→ Registro de migrations tenant copiado do Colag.');
    } catch (PDOException $e) {
        println('   AVISO migrations_escolas: ' . $e->getMessage());
    }
}

$tenantPdo = connectPdo($host, $port, $tenantDb, TENANT_USER, TENANT_PASS);
$temConfigLayout = $tenantPdo->query("SHOW TABLES LIKE 'config_layout'")->fetchColumn();
if ($temConfigLayout) {
    $tenantPdo->exec('DELETE FROM config_layout');
    $linhas = $masterPdo->prepare(
        'SELECT config_key, config_value FROM config_escolas_layout WHERE escola_id = ?'
    );
    $linhas->execute([$escolaId]);
    $ins = $tenantPdo->prepare(
        'INSERT INTO config_layout (config_key, config_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)'
    );
    $n = 0;
    while ($row = $linhas->fetch(PDO::FETCH_ASSOC)) {
        $ins->execute([(string) $row['config_key'], (string) ($row['config_value'] ?? '')]);
        $n++;
    }
    println("→ config_layout do tenant sincronizado ({$n} chaves).");
}

println('');
println('Pronto. Escola: ' . SCHOOL_NAME);
println('URL:    http://' . SCHOOL_DOMAIN);
println('Banco:  ' . $tenantDb);
println('');
println('Próximo passo:');
println('  php scripts/popular_colegio_educa.php');
