<?php
/**
 * EducaTudo — log de performance por request (queries e páginas lentas).
 * Ativado via PERF_LOG_SLOW_QUERIES=true no .env do servidor.
 * Grava JSONL em storage/logs/performance/ (somente leitura posterior; não altera dados).
 */

class PerfLogger
{
    private static ?bool $enabled = null;
    private static ?float $queryThresholdSec = null;
    private static ?float $requestThresholdSec = null;
    private static float $requestStart = 0.0;
    /** @var list<array<string,mixed>> */
    private static array $pendingSlowQueries = [];

    public static function markRequestStart(): void
    {
        if (self::$requestStart <= 0) {
            self::$requestStart = microtime(true);
        }
    }

    public static function isEnabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }
        $flag = self::envFlag('PERF_LOG_SLOW_QUERIES', false);
        self::$enabled = $flag;
        return self::$enabled;
    }

    public static function queryThresholdSec(): float
    {
        if (self::$queryThresholdSec === null) {
            $ms = (float) self::envValue('PERF_SLOW_QUERY_MS', '500');
            self::$queryThresholdSec = max(0.05, $ms / 1000);
        }
        return self::$queryThresholdSec;
    }

    public static function requestThresholdSec(): float
    {
        if (self::$requestThresholdSec === null) {
            $ms = (float) self::envValue('PERF_SLOW_REQUEST_MS', '2000');
            self::$requestThresholdSec = max(0.1, $ms / 1000);
        }
        return self::$requestThresholdSec;
    }

    /**
     * Registra query lenta com contexto da requisição HTTP (se houver).
     */
    public static function logSlowQuery(float $durationSec, string $sql, array $params = []): void
    {
        if (!self::isEnabled() || $durationSec < self::queryThresholdSec()) {
            return;
        }

        self::$pendingSlowQueries[] = [
            'type' => 'slow_query',
            'duration_ms' => round($durationSec * 1000, 2),
            'sql' => self::sanitizeSql($sql),
            'sql_fingerprint' => self::fingerprintSql($sql),
            'params_count' => count($params),
            'context' => self::requestContext(),
        ];
    }

    /**
     * Grava queries lentas acumuladas no request (uma escrita por arquivo).
     */
    public static function flushSlowQueries(): void
    {
        if (!self::isEnabled() || self::$pendingSlowQueries === []) {
            return;
        }
        foreach (self::$pendingSlowQueries as $entry) {
            self::appendJsonLine('slow_queries', $entry);
        }
        self::$pendingSlowQueries = [];
    }

    /**
     * Resumo do request no shutdown — só grava se passou do limiar ou teve query lenta.
     */
    public static function flushRequestSummary(array $dbBuffer): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $start = self::$requestStart > 0 ? self::$requestStart : ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $durationSec = microtime(true) - $start;
        $slowQueries = (int) ($dbBuffer['slow_queries'] ?? 0);
        $dbQueries = (int) ($dbBuffer['db_queries'] ?? 0);
        $dbTimeSec = (float) ($dbBuffer['db_query_time'] ?? 0);

        if ($durationSec < self::requestThresholdSec() && $slowQueries === 0) {
            return;
        }

        self::appendJsonLine('slow_requests', [
            'type' => 'slow_request',
            'duration_ms' => round($durationSec * 1000, 2),
            'db_queries' => $dbQueries,
            'db_time_ms' => round($dbTimeSec * 1000, 2),
            'slow_queries' => $slowQueries,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'context' => self::requestContext(),
        ]);
    }

    private static function requestContext(): array
    {
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        if (is_string($uri) && $uri !== '') {
            $path = parse_url($uri, PHP_URL_PATH);
            $uri = is_string($path) && $path !== '' ? $path : '/';
        }

        $ctx = [
            'tenant_slug' => defined('TENANT_SLUG') ? (string) TENANT_SLUG : '',
            'tenant_id' => defined('TENANT_ID') ? (int) TENANT_ID : null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'uri' => $uri,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        if (isset($_SESSION['user_id'])) {
            $ctx['user_id'] = (int) $_SESSION['user_id'];
        }
        if (isset($_SESSION['user_type'])) {
            $ctx['user_type'] = (string) $_SESSION['user_type'];
        }

        return $ctx;
    }

    private static function sanitizeSql(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
        if (strlen($sql) > 2000) {
            return substr($sql, 0, 2000) . '…';
        }
        return $sql;
    }

    /**
     * Normaliza SQL para agrupar no relatório (remove literais e ids).
     */
    public static function fingerprintSql(string $sql): string
    {
        $s = strtolower(preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql));
        $s = preg_replace("/'[^']*'/", '?', $s) ?? $s;
        $s = preg_replace('/\b\d+\b/', '?', $s) ?? $s;
        if (strlen($s) > 500) {
            $s = substr($s, 0, 500);
        }
        return $s;
    }

    private static function appendJsonLine(string $prefix, array $payload): void
    {
        try {
            $dir = __DIR__ . '/../../storage/logs/performance';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $file = $dir . '/' . $prefix . '_' . date('Y-m-d') . '.jsonl';
            $payload['ts'] = date('c');
            $payload['host'] = gethostname() ?: 'unknown';
            @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            error_log('PerfLogger: ' . $e->getMessage());
        }
    }

    private static function envFlag(string $key, bool $default): bool
    {
        $v = self::envValue($key, $default ? 'true' : 'false');
        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function envValue(string $key, $default)
    {
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return $v;
        }
        foreach (self::loadPerfEnvFile() as $name => $value) {
            if ($name === $key) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * Lê apenas chaves PERF_* do .env (PHP-FPM não exporta o arquivo para getenv).
     *
     * @return array<string, string>
     */
    private static function loadPerfEnvFile(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $path = defined('ENV_FILE_PATH') ? ENV_FILE_PATH : (__DIR__ . '/../../.env');
        if (!is_readable($path)) {
            return $cache;
        }
        foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            if (strpos($name, 'PERF_') !== 0) {
                continue;
            }
            $cache[$name] = trim($value, " \t\"'");
        }
        return $cache;
    }
}
