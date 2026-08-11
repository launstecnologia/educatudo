<?php
/**
 * EducaTudo — diagnóstico MySQL (master + tenants).
 *
 * Uso (no servidor, dentro de src/ ou deploy):
 *   php scripts/diagnostico_mysql.php
 *   php scripts/diagnostico_mysql.php --all-tenants
 *   php scripts/diagnostico_mysql.php --escola-id=42
 *   php scripts/diagnostico_mysql.php --check-schema
 *
 * Somente leitura — não altera banco nem arquivos.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado via CLI.\n");
}

$args = array_slice($argv, 1);
$allTenants = in_array('--all-tenants', $args, true);
$checkSchema = in_array('--check-schema', $args, true) || $allTenants;
$escolaIdFilter = null;
foreach ($args as $arg) {
    if (preg_match('/^--escola-id=(\d+)$/', $arg, $m)) {
        $escolaIdFilter = (int) $m[1];
    }
}

$basePath = dirname(__DIR__);
require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Core/MasterSecretVault.php';

function out(string $label, string $value = ''): void
{
    if ($value === '') {
        echo "\n── {$label} " . str_repeat('─', max(0, 52 - strlen($label))) . "\n";
        return;
    }
    echo str_pad($label . ':', 28) . $value . "\n";
}

function connectPdo(string $host, int $port, string $db, string $user, string $pass, int $timeout = 10): array
{
    $start = microtime(true);
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $timeout,
        ]);
        $ms = round((microtime(true) - $start) * 1000, 1);
        return ['ok' => true, 'pdo' => $pdo, 'ms' => $ms, 'error' => null];
    } catch (PDOException $e) {
        $ms = round((microtime(true) - $start) * 1000, 1);
        return ['ok' => false, 'pdo' => null, 'ms' => $ms, 'error' => $e->getMessage()];
    }
}

function printMysqlStatus(PDO $pdo): void
{
    $vars = [
        'Threads_connected',
        'Threads_running',
        'Slow_queries',
        'Questions',
        'Uptime',
        'Max_used_connections',
        'Aborted_connects',
        'Innodb_buffer_pool_read_requests',
        'Innodb_buffer_pool_reads',
    ];
    $placeholders = implode(',', array_fill(0, count($vars), '?'));
    $stmt = $pdo->prepare(
        "SHOW GLOBAL STATUS WHERE Variable_name IN ({$placeholders})"
    );
    $stmt->execute($vars);
    foreach ($stmt->fetchAll() as $row) {
        out($row['Variable_name'], (string) $row['Value']);
    }

    $slowLog = $pdo->query("SHOW VARIABLES LIKE 'slow_query_log'")->fetch();
    $longQuery = $pdo->query("SHOW VARIABLES LIKE 'long_query_time'")->fetch();
    if ($slowLog) {
        out('slow_query_log', $slowLog['Value'] ?? '?');
    }
    if ($longQuery) {
        out('long_query_time', ($longQuery['Value'] ?? '?') . 's');
    }

    out('Processlist (queries > 2s ou não Sleep)');
    $procs = $pdo->query('SHOW FULL PROCESSLIST')->fetchAll();
    $shown = 0;
    foreach ($procs as $p) {
        $time = (int) ($p['Time'] ?? 0);
        $cmd = (string) ($p['Command'] ?? '');
        if ($cmd === 'Sleep' && $time < 5) {
            continue;
        }
        if ($time >= 2 || $cmd !== 'Sleep') {
            $info = substr((string) ($p['Info'] ?? ''), 0, 120);
            printf(
                "  [id=%s user=%s time=%ss state=%s] %s\n",
                $p['Id'] ?? '?',
                $p['User'] ?? '?',
                $time,
                $p['State'] ?? '',
                $info
            );
            $shown++;
            if ($shown >= 15) {
                break;
            }
        }
    }
    if ($shown === 0) {
        echo "  (nenhuma query longa no momento)\n";
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 AS ok FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return !empty($stmt->fetch()['ok']);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 AS ok FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table]);
    return !empty($stmt->fetch()['ok']);
}

function checkSchemaDrift(PDO $pdo, string $label): void
{
    out("Schema drift — {$label}");
    $checks = [
        ['admin_perfis_permissao', 'ativo', 'Migration 2026_05_13_admin_perfis_permissao.sql'],
        ['professores', 'ativo', 'Coluna padrão em educatudo.sql'],
        ['usuarios', 'perfil_permissao_id', 'Migration 2026_05_13_admin_perfis_permissao.sql'],
    ];
    foreach ($checks as [$table, $column, $hint]) {
        if (!tableExists($pdo, $table)) {
            echo "  · {$table}.{$column}: tabela ausente (ok se feature não usada)\n";
            continue;
        }
        if (columnExists($pdo, $table, $column)) {
            echo "  ✓ {$table}.{$column}\n";
        } else {
            echo "  ✗ FALTA {$table}.{$column} — causa erro \"Unknown column 'p.ativo'\" ({$hint})\n";
        }
    }
}

function printTopTables(PDO $pdo): void
{
    out('Maiores tabelas (MB aprox.)');
    $rows = $pdo->query(
        "SELECT table_name,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                table_rows
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
         ORDER BY (data_length + index_length) DESC
         LIMIT 10"
    )->fetchAll();
    foreach ($rows as $r) {
        printf(
            "  %-40s %8s MB  ~%s linhas\n",
            $r['table_name'],
            $r['size_mb'],
            number_format((int) $r['table_rows'], 0, ',', '.')
        );
    }
}

// ── Master ──────────────────────────────────────────────────────────────────
out('EducaTudo — diagnóstico MySQL', date('Y-m-d H:i:s'));

$cfg = Database::getConfigFromEnv();
$port = !empty($cfg['port']) ? (int) $cfg['port'] : 3306;
out('Master', "{$cfg['name']} @ {$cfg['host']}:{$port}");

$master = connectPdo($cfg['host'], $port, $cfg['name'], (string) $cfg['user'], (string) ($cfg['pass'] ?? ''));
if (!$master['ok']) {
    echo "\n✗ FALHA ao conectar no master em {$master['ms']}ms\n";
    echo "  Erro: {$master['error']}\n";
    echo "\nIsso corresponde ao erro \"Connection timed out\" em bootstrap_multi_tenant.php.\n";
    echo "Verifique: MySQL rodando, firewall, DB_HOST no .env, max_connections, carga do servidor.\n";
    exit(1);
}

out('Conexão master OK', $master['ms'] . ' ms');
/** @var PDO $masterPdo */
$masterPdo = $master['pdo'];
$ver = $masterPdo->query('SELECT VERSION() AS v')->fetch();
out('MySQL version', $ver['v'] ?? '?');

printMysqlStatus($masterPdo);

$escolas = $masterPdo->query(
    'SELECT e.id, e.nome, e.slug, e.ativo,
            b.host, b.porta, b.nome_banco, b.usuario, b.senha_criptografada
     FROM escolas e
     INNER JOIN config_escolas_banco b ON b.escola_id = e.id
     ORDER BY e.id'
)->fetchAll();

out('Escolas cadastradas', (string) count($escolas));

if (!$allTenants && $escolaIdFilter === null) {
    echo "\nDica: use --all-tenants para testar conexão de cada escola";
    echo " ou --escola-id=N para uma escola específica.\n";
}

$tenantsToTest = [];
foreach ($escolas as $row) {
    $id = (int) $row['id'];
    if ($escolaIdFilter !== null && $id !== $escolaIdFilter) {
        continue;
    }
    if (!$allTenants && $escolaIdFilter === null) {
        continue;
    }
    $tenantsToTest[] = $row;
}

if ($tenantsToTest !== []) {
    out('Teste de conexão por tenant');
    $slow = [];
    $failed = [];

    foreach ($tenantsToTest as $row) {
        $id = (int) $row['id'];
        $slug = (string) ($row['slug'] ?? '');
        $host = (string) ($row['host'] ?? '127.0.0.1');
        $tPort = (int) ($row['porta'] ?? 3306);
        $dbName = (string) ($row['nome_banco'] ?? '');
        $user = (string) ($row['usuario'] ?? '');
        try {
            $pass = MasterSecretVault::decryptDbPassword($row['senha_criptografada'] ?? '');
        } catch (Throwable $e) {
            $failed[] = [$id, $slug, 'senha não decifra: ' . $e->getMessage()];
            printf("  ✗ escola %d (%s): senha tenant não decifra\n", $id, $slug);
            continue;
        }

        $conn = connectPdo($host, $tPort, $dbName, $user, $pass);
        if (!$conn['ok']) {
            $failed[] = [$id, $slug, $conn['error']];
            printf("  ✗ escola %d (%s) %s@%s:%d — %s ms — %s\n", $id, $slug, $dbName, $host, $tPort, $conn['ms'], $conn['error']);
            continue;
        }

        $flag = $conn['ms'] >= 500 ? ' LENTO' : '';
        printf("  ✓ escola %d (%s) — %s ms%s\n", $id, $slug, $conn['ms'], $flag);
        if ($conn['ms'] >= 500) {
            $slow[] = [$id, $slug, $conn['ms']];
        }

        if ($checkSchema) {
            checkSchemaDrift($conn['pdo'], "escola {$id} ({$slug})");
        }

        if ($allTenants && count($tenantsToTest) <= 5) {
            printTopTables($conn['pdo']);
        }

        $conn['pdo'] = null;
    }

    if ($failed !== []) {
        out('Tenants com falha de conexão', (string) count($failed));
        foreach ($failed as [$id, $slug, $err]) {
            echo "  · escola {$id} ({$slug}): {$err}\n";
        }
    }
    if ($slow !== []) {
        out('Tenants lentos (>500ms só para conectar)', (string) count($slow));
        foreach ($slow as [$id, $slug, $ms]) {
            echo "  · escola {$id} ({$slug}): {$ms} ms\n";
        }
    }
}

// Migrations pendentes (master)
if (tableExists($masterPdo, 'migrations_escolas')) {
    out('Migrations — amostra por escola');
    $pending = $masterPdo->query(
        'SELECT escola_id, COUNT(*) AS total
         FROM migrations_escolas
         GROUP BY escola_id
         ORDER BY total ASC
         LIMIT 5'
    )->fetchAll();
    foreach ($pending as $p) {
        echo "  escola {$p['escola_id']}: {$p['total']} migrations registradas\n";
    }
    echo "  (compare escolas com total muito menor — provável migration pendente)\n";
}

echo "\nConcluído.\n";
