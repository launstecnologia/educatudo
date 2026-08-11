<?php
/**
 * EducaTudo — coleta periódica de métricas (servidor APP + MySQL).
 *
 * Uso (cron a cada 1–5 min no servidor APP):
 *   php scripts/monitor_coleta.php
 *   php scripts/monitor_coleta.php --all-tenants
 *   php scripts/monitor_coleta.php --escola-id=1
 *
 * Grava JSONL em storage/logs/performance/coleta_YYYY-MM-DD.jsonl
 * Somente leitura — não altera banco.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado via CLI.\n");
}

$args = array_slice($argv, 1);
$allTenants = in_array('--all-tenants', $args, true);
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

function monitorLogDir(): string
{
    $dir = dirname(__DIR__) . '/storage/logs/performance';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function appendColeta(array $payload): void
{
    $dir = monitorLogDir();
    $file = $dir . '/coleta_' . date('Y-m-d') . '.jsonl';
    $payload['ts'] = date('c');
    @file_put_contents(
        $file,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

function readState(): array
{
    $path = monitorLogDir() . '/.coleta_state.json';
    if (!is_readable($path)) {
        return [];
    }
    $data = json_decode((string) @file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function writeState(array $state): void
{
    $path = monitorLogDir() . '/.coleta_state.json';
    @file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function collectServerMetrics(): array
{
    $m = [
        'hostname' => gethostname() ?: 'unknown',
        'load_1m' => null,
        'load_5m' => null,
        'load_15m' => null,
        'mem_total_mb' => null,
        'mem_available_mb' => null,
        'disk_use_pct' => null,
        'php_processes' => null,
    ];

    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        if (is_array($load)) {
            $m['load_1m'] = round($load[0], 2);
            $m['load_5m'] = round($load[1], 2);
            $m['load_15m'] = round($load[2], 2);
        }
    }

    if (is_readable('/proc/meminfo')) {
        $info = @file_get_contents('/proc/meminfo');
        if ($info !== false) {
            preg_match('/MemTotal:\s+(\d+)/', $info, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $info, $avail);
            if (!empty($total[1])) {
                $m['mem_total_mb'] = round((int) $total[1] / 1024, 1);
            }
            if (!empty($avail[1])) {
                $m['mem_available_mb'] = round((int) $avail[1] / 1024, 1);
            }
        }
    }

    $root = dirname(__DIR__);
    $free = @disk_free_space($root);
    $total = @disk_total_space($root);
    if ($free !== false && $total !== false && $total > 0) {
        $m['disk_use_pct'] = round(100 - ($free / $total * 100), 1);
    }

    $phpCount = @shell_exec('pgrep -c php 2>/dev/null');
    if ($phpCount !== null && trim($phpCount) !== '') {
        $m['php_processes'] = (int) trim($phpCount);
    }

    return $m;
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
        return ['ok' => true, 'pdo' => $pdo, 'ms' => round((microtime(true) - $start) * 1000, 1), 'error' => null];
    } catch (PDOException $e) {
        return ['ok' => false, 'pdo' => null, 'ms' => round((microtime(true) - $start) * 1000, 1), 'error' => $e->getMessage()];
    }
}

function collectMysqlGlobal(PDO $pdo, array &$state): array
{
    $vars = [
        'Threads_connected', 'Threads_running', 'Slow_queries', 'Questions',
        'Max_used_connections', 'Aborted_connects', 'Uptime',
        'Innodb_buffer_pool_read_requests', 'Innodb_buffer_pool_reads',
    ];
    $placeholders = implode(',', array_fill(0, count($vars), '?'));
    $stmt = $pdo->prepare("SHOW GLOBAL STATUS WHERE Variable_name IN ({$placeholders})");
    $stmt->execute($vars);
    $status = [];
    foreach ($stmt->fetchAll() as $row) {
        $status[$row['Variable_name']] = $row['Value'];
    }

    $slowNow = (int) ($status['Slow_queries'] ?? 0);
    $prevSlow = (int) ($state['slow_queries'] ?? $slowNow);
    $deltaSlow = max(0, $slowNow - $prevSlow);
    $state['slow_queries'] = $slowNow;
    $state['last_collect_ts'] = time();

    $bufferHit = null;
    $reads = (int) ($status['Innodb_buffer_pool_reads'] ?? 0);
    $readReq = (int) ($status['Innodb_buffer_pool_read_requests'] ?? 0);
    if ($readReq > 0) {
        $bufferHit = round(100 * (1 - $reads / $readReq), 2);
    }

    $processlist = [];
    foreach ($pdo->query('SHOW FULL PROCESSLIST')->fetchAll() as $p) {
        $time = (int) ($p['Time'] ?? 0);
        $cmd = (string) ($p['Command'] ?? '');
        if ($cmd === 'Sleep' && $time < 2) {
            continue;
        }
        if ($time >= 1 || $cmd !== 'Sleep') {
            $processlist[] = [
                'id' => $p['Id'] ?? null,
                'user' => $p['User'] ?? null,
                'db' => $p['db'] ?? null,
                'time_s' => $time,
                'state' => $p['State'] ?? '',
            ];
        }
        if (count($processlist) >= 10) {
            break;
        }
    }

    return [
        'status' => $status,
        'slow_queries_delta' => $deltaSlow,
        'innodb_buffer_hit_pct' => $bufferHit,
        'processlist' => $processlist,
    ];
}

function topTables(PDO $pdo, int $limit = 8): array
{
    $rows = $pdo->query(
        "SELECT table_name AS name,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS mb,
                table_rows AS rows_approx
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
         ORDER BY (data_length + index_length) DESC
         LIMIT {$limit}"
    )->fetchAll();
    return $rows ?: [];
}

// ── Execução ────────────────────────────────────────────────────────────────
$state = readState();
$payload = [
    'type' => 'coleta',
    'server' => collectServerMetrics(),
    'mysql' => null,
    'tenants' => [],
];

$cfg = Database::getConfigFromEnv();
$port = !empty($cfg['port']) ? (int) $cfg['port'] : 3306;
$master = connectPdo($cfg['host'], $port, $cfg['name'], (string) $cfg['user'], (string) ($cfg['pass'] ?? ''));

if (!$master['ok']) {
    $payload['mysql'] = ['ok' => false, 'error' => $master['error'], 'connect_ms' => $master['ms']];
    appendColeta($payload);
    writeState($state);
    fwrite(STDERR, "Falha MySQL master: {$master['error']}\n");
    exit(1);
}

/** @var PDO $masterPdo */
$masterPdo = $master['pdo'];
$payload['mysql'] = array_merge(
    ['ok' => true, 'host' => $cfg['host'], 'connect_ms' => $master['ms']],
    collectMysqlGlobal($masterPdo, $state)
);

$escolas = $masterPdo->query(
    'SELECT e.id, e.nome, e.slug, e.ativo,
            b.host, b.porta, b.nome_banco, b.usuario, b.senha_criptografada
     FROM escolas e
     INNER JOIN config_escolas_banco b ON b.escola_id = e.id
     WHERE e.ativo = 1
     ORDER BY e.id'
)->fetchAll();

foreach ($escolas as $row) {
    $id = (int) $row['id'];
    if ($escolaIdFilter !== null && $id !== $escolaIdFilter) {
        continue;
    }
    if (!$allTenants && $escolaIdFilter === null) {
        continue;
    }

    $slug = (string) ($row['slug'] ?? '');
    $host = (string) ($row['host'] ?? '127.0.0.1');
    $tPort = (int) ($row['porta'] ?? 3306);
    $dbName = (string) ($row['nome_banco'] ?? '');
    $user = (string) ($row['usuario'] ?? '');

    try {
        $pass = MasterSecretVault::decryptDbPassword($row['senha_criptografada'] ?? '');
    } catch (Throwable $e) {
        $payload['tenants'][] = ['escola_id' => $id, 'slug' => $slug, 'ok' => false, 'error' => 'senha tenant'];
        continue;
    }

    $conn = connectPdo($host, $tPort, $dbName, $user, $pass);
    if (!$conn['ok']) {
        $payload['tenants'][] = [
            'escola_id' => $id, 'slug' => $slug, 'db' => $dbName,
            'ok' => false, 'connect_ms' => $conn['ms'], 'error' => $conn['error'],
        ];
        continue;
    }

    $tenantEntry = [
        'escola_id' => $id,
        'slug' => $slug,
        'db' => $dbName,
        'ok' => true,
        'connect_ms' => $conn['ms'],
        'top_tables' => topTables($conn['pdo']),
    ];
    $payload['tenants'][] = $tenantEntry;
    $conn['pdo'] = null;
}

appendColeta($payload);
writeState($state);

echo date('Y-m-d H:i:s') . " coleta OK — slow_queries_delta="
    . ($payload['mysql']['slow_queries_delta'] ?? '?')
    . " threads_running=" . ($payload['mysql']['status']['Threads_running'] ?? '?')
    . " tenants=" . count($payload['tenants']) . "\n";
