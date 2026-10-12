<?php
declare(strict_types=1);

$root = dirname(__DIR__) . '/backend';
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === '#' || strpos($line, '=') === false) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}

// Em multi-tenant, credenciais do tenant colag vêm do master
$masterHost = $env['MASTER_DB_HOST'] ?? $env['DB_HOST'] ?? '127.0.0.1';
$masterPort = (int) ($env['MASTER_DB_PORT'] ?? $env['DB_PORT'] ?? 3306);
$masterName = $env['MASTER_DB_NAME'] ?? $env['DB_NAME'] ?? '';
$masterUser = $env['MASTER_DB_USER'] ?? $env['DB_USER'] ?? '';
$masterPass = $env['MASTER_DB_PASS'] ?? $env['DB_PASS'] ?? '';

// Do host: mysql docker costuma ser 127.0.0.1:3307
if ($masterHost === 'mysql' || $masterHost === 'mysql_db_educatudo') {
    $masterHost = '127.0.0.1';
    $masterPort = (int) ($env['DB_PORT_HOST'] ?? 3307);
}

echo "Master connect {$masterHost}:{$masterPort}/{$masterName}\n";

try {
    $pdo = new PDO(
        "mysql:host={$masterHost};port={$masterPort};dbname={$masterName};charset=utf8mb4",
        $masterUser,
        $masterPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    echo "Master fail: " . $e->getMessage() . "\n";
    // tenta DB direto do .env (single)
    $host = $env['DB_HOST'] === 'mysql' ? '127.0.0.1' : $env['DB_HOST'];
    $port = (int) ($env['DB_PORT'] ?? 3306);
    if ($host === '127.0.0.1' && $port == 3306) {
        $port = 3307;
    }
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$env['DB_NAME']};charset=utf8mb4",
        $env['DB_USER'],
        $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Fallback tenant DB ok\n";
    listUsers($pdo);
    exit(0);
}

$st = $pdo->query("SHOW TABLES");
$tables = $st->fetchAll(PDO::FETCH_COLUMN);
echo "Master tables sample: " . implode(', ', array_slice($tables, 0, 15)) . "\n";

$escolaTable = null;
foreach (['escolas', 'schools', 'tenants'] as $t) {
    if (in_array($t, $tables, true)) {
        $escolaTable = $t;
        break;
    }
}
if (!$escolaTable) {
    echo "No escola table\n";
    exit(1);
}

$cols = $pdo->query("DESCRIBE `{$escolaTable}`")->fetchAll(PDO::FETCH_COLUMN);
echo "Escola cols: " . implode(',', $cols) . "\n";

$rows = $pdo->query("SELECT * FROM `{$escolaTable}` LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $slug = $r['slug'] ?? $r['subdomain'] ?? $r['dominio'] ?? '';
    $nome = $r['nome'] ?? $r['name'] ?? '';
    if (stripos((string) $slug, 'colag') !== false || stripos((string) $nome, 'colag') !== false) {
        echo "COLAG ROW: " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        // try decrypt or direct db fields
        $dbHost = $r['db_host'] ?? $r['database_host'] ?? null;
        $dbName = $r['db_name'] ?? $r['database_name'] ?? $r['db_database'] ?? null;
        $dbUser = $r['db_user'] ?? $r['database_user'] ?? null;
        $dbPass = $r['db_pass'] ?? $r['database_password'] ?? $r['db_password'] ?? null;
        $dbPort = (int) ($r['db_port'] ?? $r['database_port'] ?? 3306);
        if ($dbName && $dbUser !== null) {
            if ($dbHost === 'mysql' || $dbHost === 'mysql_db_colag' || !$dbHost) {
                // container name from host
                $dbHost = '127.0.0.1';
                // try ports
            }
            foreach ([3307, 3306, $dbPort] as $tryPort) {
                try {
                    $tpdo = new PDO(
                        "mysql:host=127.0.0.1;port={$tryPort};dbname={$dbName};charset=utf8mb4",
                        $dbUser,
                        (string) $dbPass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    echo "Tenant connected via 127.0.0.1:{$tryPort}/{$dbName}\n";
                    listUsers($tpdo);
                    exit(0);
                } catch (Throwable $e) {
                    echo "try {$tryPort}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}

function listUsers(PDO $pdo): void
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $userTable = null;
    foreach (['usuarios', 'users'] as $t) {
        if (in_array($t, $tables, true)) {
            $userTable = $t;
            break;
        }
    }
    if (!$userTable) {
        echo "No users table. Tables: " . implode(',', array_slice($tables, 0, 30)) . "\n";
        return;
    }
    $cols = $pdo->query("DESCRIBE `{$userTable}`")->fetchAll(PDO::FETCH_COLUMN);
    echo "User cols: " . implode(',', $cols) . "\n";
    $tipoCol = in_array('tipo', $cols, true) ? 'tipo' : (in_array('perfil', $cols, true) ? 'perfil' : 'role');
    $sql = "SELECT id, email, {$tipoCol} AS tipo FROM `{$userTable}` WHERE {$tipoCol} LIKE '%admin%' OR email LIKE '%admin%' LIMIT 30";
    foreach ($pdo->query($sql) as $u) {
        echo "USER {$u['id']} {$u['email']} tipo={$u['tipo']}\n";
    }
}
