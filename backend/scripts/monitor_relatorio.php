<?php
/**
 * EducaTudo — relatório de performance a partir dos logs de monitoramento.
 *
 * Uso:
 *   php scripts/monitor_relatorio.php
 *   php scripts/monitor_relatorio.php --hours=24
 *   php scripts/monitor_relatorio.php --hours=6 --tenant=colag
 *   php scripts/monitor_relatorio.php --mysql-slow-log=/var/log/mysql/slow.log
 *
 * Lê:
 *   storage/logs/performance/coleta_*.jsonl      (servidor + MySQL periódico)
 *   storage/logs/performance/slow_queries_*.jsonl (queries lentas da app)
 *   storage/logs/performance/slow_requests_*.jsonl (páginas lentas da app)
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado via CLI.\n");
}

require_once dirname(__DIR__) . '/app/Core/PerfLogger.php';

$args = array_slice($argv, 1);
$hours = 24;
$tenantFilter = null;
$mysqlSlowLog = null;
foreach ($args as $arg) {
    if (preg_match('/^--hours=(\d+)$/', $arg, $m)) {
        $hours = max(1, (int) $m[1]);
    } elseif (preg_match('/^--tenant=(.+)$/', $arg, $m)) {
        $tenantFilter = strtolower(trim($m[1]));
    } elseif (preg_match('/^--mysql-slow-log=(.+)$/', $arg, $m)) {
        $mysqlSlowLog = $m[1];
    }
}

$logDir = dirname(__DIR__) . '/storage/logs/performance';
$since = time() - ($hours * 3600);

function section(string $title): void
{
    echo "\n" . str_repeat('─', 60) . "\n▶ {$title}\n" . str_repeat('─', 60) . "\n";
}

function readJsonlSince(string $dir, string $prefix, int $since): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $entries = [];
    $files = glob($dir . '/' . $prefix . '_*.jsonl') ?: [];
    sort($files);
    foreach ($files as $file) {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (!is_array($row)) {
                continue;
            }
            $ts = strtotime($row['ts'] ?? '') ?: 0;
            if ($ts >= $since) {
                $entries[] = $row;
            }
        }
    }
    return $entries;
}

function matchesTenant(array $row, ?string $tenant): bool
{
    if ($tenant === null || $tenant === '') {
        return true;
    }
    $ctx = $row['context'] ?? [];
    $slug = strtolower((string) ($ctx['tenant_slug'] ?? ''));
    return $slug === $tenant || strpos($slug, $tenant) !== false;
}

// ── Coletas periódicas ──────────────────────────────────────────────────────
$coletas = readJsonlSince($logDir, 'coleta', $since);

section("Resumo — últimas {$hours}h");
if ($coletas === []) {
    echo "Nenhuma coleta encontrada em {$logDir}.\n";
    echo "Configure cron: */2 * * * * cd /path/deploy && php scripts/monitor_coleta.php --all-tenants\n";
} else {
    $slowDeltas = [];
    $threadsRunning = [];
    $connectMs = [];
    foreach ($coletas as $c) {
        if (isset($c['mysql']['slow_queries_delta'])) {
            $slowDeltas[] = (int) $c['mysql']['slow_queries_delta'];
        }
        if (isset($c['mysql']['status']['Threads_running'])) {
            $threadsRunning[] = (int) $c['mysql']['status']['Threads_running'];
        }
        if (isset($c['mysql']['connect_ms'])) {
            $connectMs[] = (float) $c['mysql']['connect_ms'];
        }
    }
    echo 'Coletas registradas: ' . count($coletas) . "\n";
    echo 'Slow queries MySQL (soma delta entre coletas): ' . array_sum($slowDeltas) . "\n";
    if ($connectMs !== []) {
        echo 'Conexão master — média: ' . round(array_sum($connectMs) / count($connectMs), 1) . " ms, max: " . round(max($connectMs), 1) . " ms\n";
    }
    if ($threadsRunning !== []) {
        echo 'Threads_running — média: ' . round(array_sum($threadsRunning) / count($threadsRunning), 1) . ', max: ' . max($threadsRunning) . "\n";
    }

    $last = $coletas[count($coletas) - 1];
    if (!empty($last['server'])) {
        $s = $last['server'];
        echo "\nServidor APP (última coleta):\n";
        echo '  load: ' . ($s['load_1m'] ?? '?') . ' / ' . ($s['load_5m'] ?? '?') . ' / ' . ($s['load_15m'] ?? '?') . "\n";
        echo '  RAM livre: ' . ($s['mem_available_mb'] ?? '?') . ' MB de ' . ($s['mem_total_mb'] ?? '?') . " MB\n";
        echo '  Disco usado: ' . ($s['disk_use_pct'] ?? '?') . "%\n";
        echo '  Processos PHP: ' . ($s['php_processes'] ?? '?') . "\n";
    }

    if (!empty($last['mysql']['processlist'])) {
        echo "\nSessões MySQL ativas (última coleta — sem SQL de terceiros):\n";
        foreach ($last['mysql']['processlist'] as $p) {
            if (($p['time_s'] ?? 0) >= 2) {
                printf(
                    "  [%ss db=%s state=%s]\n",
                    $p['time_s'] ?? '?',
                    $p['db'] ?? '?',
                    $p['state'] ?? ''
                );
            }
        }
    }

    // Top tables por tenant (última coleta com tenants)
    for ($i = count($coletas) - 1; $i >= 0; $i--) {
        if (!empty($coletas[$i]['tenants'])) {
            section('Maiores tabelas por escola (última coleta com tenants)');
            foreach ($coletas[$i]['tenants'] as $t) {
                if (empty($t['ok'])) {
                    continue;
                }
                if ($tenantFilter !== null && stripos((string) ($t['slug'] ?? ''), $tenantFilter) === false) {
                    continue;
                }
                echo "\n  {$t['slug']} ({$t['db']}) — conexão {$t['connect_ms']} ms\n";
                foreach ($t['top_tables'] ?? [] as $tbl) {
                    printf("    %-42s %8s MB  ~%s linhas\n", $tbl['name'], $tbl['mb'], number_format((int) ($tbl['rows_approx'] ?? 0), 0, ',', '.'));
                }
            }
            break;
        }
    }
}

// ── Queries lentas da aplicação ─────────────────────────────────────────────
$slowQueries = array_filter(
    readJsonlSince($logDir, 'slow_queries', $since),
    fn ($r) => matchesTenant($r, $tenantFilter)
);

section('Queries lentas na aplicação (PERF_LOG_SLOW_QUERIES)');
if ($slowQueries === []) {
    echo "Nenhum registro. Ative no .env do servidor:\n";
    echo "  PERF_LOG_SLOW_QUERIES=true\n";
    echo "  PERF_SLOW_QUERY_MS=500\n";
    echo "Use a plataforma normalmente; o log preenche em storage/logs/performance/\n";
} else {
    $byFingerprint = [];
    $byUri = [];
    foreach ($slowQueries as $q) {
        $fp = $q['sql_fingerprint'] ?? PerfLogger::fingerprintSql($q['sql'] ?? '');
        if (!isset($byFingerprint[$fp])) {
            $byFingerprint[$fp] = ['count' => 0, 'total_ms' => 0.0, 'max_ms' => 0.0, 'sample_sql' => $q['sql'] ?? ''];
        }
        $byFingerprint[$fp]['count']++;
        $ms = (float) ($q['duration_ms'] ?? 0);
        $byFingerprint[$fp]['total_ms'] += $ms;
        $byFingerprint[$fp]['max_ms'] = max($byFingerprint[$fp]['max_ms'], $ms);

        $uri = (string) (($q['context']['uri'] ?? '') ?: 'cli/cron');
        if (!isset($byUri[$uri])) {
            $byUri[$uri] = 0;
        }
        $byUri[$uri]++;
    }

    uasort($byFingerprint, fn ($a, $b) => $b['total_ms'] <=> $a['total_ms']);
    echo "Total de queries lentas registradas: " . count($slowQueries) . "\n\n";
    echo "Top SQL (por tempo acumulado) — ESTES são os candidatos a otimizar:\n\n";
    $n = 0;
    foreach ($byFingerprint as $fp => $data) {
        if ($n >= 15) {
            break;
        }
        $avg = $data['count'] > 0 ? round($data['total_ms'] / $data['count'], 1) : 0;
        echo ($n + 1) . ". [{$data['count']}x | média {$avg} ms | max {$data['max_ms']} ms]\n";
        echo '   ' . substr($data['sample_sql'], 0, 220) . "\n\n";
        $n++;
    }

    arsort($byUri);
    echo "URLs que mais disparam queries lentas:\n";
    $n = 0;
    foreach ($byUri as $uri => $count) {
        if ($n >= 10) {
            break;
        }
        echo "  {$count}x  {$uri}\n";
        $n++;
    }
}

// ── Requests lentos ─────────────────────────────────────────────────────────
$slowRequests = array_filter(
    readJsonlSince($logDir, 'slow_requests', $since),
    fn ($r) => matchesTenant($r, $tenantFilter)
);

section('Páginas/requisições lentas');
if ($slowRequests === []) {
    echo "Nenhum request lento registrado (limiar padrão: PERF_SLOW_REQUEST_MS=2000).\n";
} else {
    uasort($slowRequests, fn ($a, $b) => ($b['duration_ms'] ?? 0) <=> ($a['duration_ms'] ?? 0));
    echo 'Total: ' . count($slowRequests) . "\n\n";
    $n = 0;
    foreach ($slowRequests as $r) {
        if ($n >= 20) {
            break;
        }
        $ctx = $r['context'] ?? [];
        printf(
            "%s | %s ms | DB: %d queries (%s ms) | %s %s | tenant=%s\n",
            $r['ts'] ?? '?',
            $r['duration_ms'] ?? '?',
            $r['db_queries'] ?? 0,
            $r['db_time_ms'] ?? '?',
            $ctx['method'] ?? '?',
            $ctx['uri'] ?? '?',
            $ctx['tenant_slug'] ?? '?'
        );
        $n++;
    }
}

// ── Slow log nativo MySQL (opcional) ────────────────────────────────────────
if ($mysqlSlowLog !== null && is_readable($mysqlSlowLog)) {
    section('MySQL slow log (tail — queries > long_query_time)');
    $tail = @shell_exec('tail -n 200 ' . escapeshellarg($mysqlSlowLog));
    if ($tail) {
        echo substr($tail, -8000);
    }
} elseif ($mysqlSlowLog !== null) {
    echo "\nArquivo slow log não legível: {$mysqlSlowLog}\n";
    echo "Rode no servidor MySQL ou passe o caminho correto (--mysql-slow-log=...).\n";
}

section('Próximos passos');
echo "1. Cron coleta: */2 * * * * php scripts/monitor_coleta.php --all-tenants\n";
echo "2. Ativar log de queries: PERF_LOG_SLOW_QUERIES=true no .env\n";
echo "3. Reproduzir lentidão (horário de pico) e rodar: php scripts/monitor_relatorio.php --hours=2 --tenant=colag\n";
echo "4. Cruzar 'Top SQL' com 'URLs' → otimizar índice ou SELECT no código daquela rota\n\n";
