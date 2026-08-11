<?php

namespace App\Performance;

/**
 * Orquestrador do profiler de um request. Chamado a partir de 4 pontos já
 * existentes no core (nenhuma regra de negócio foi tocada):
 *
 *  - bootstrap/app.php  → start() no início, finish() no shutdown
 *  - Router.php         → setController() + markControllerStart()/End() ao redor
 *                          do call_user_func_array do controller
 *  - BaseController.php → markViewStart()/End() ao redor de view()/viewWithLayout()
 *  - Database.php       → (indiretamente) QueryCollector::record(), chamado de
 *                          dentro de Database::query()
 *
 * Observação sobre granularidade (Service/Repository/Helper individuais):
 * este app não tem uma camada de Service/Repository consistente e instrumentar
 * cada classe exigiria tocar em centenas de arquivos (alto risco, baixo
 * benefício). O que dá pra medir com segurança nos pontos de extensão que já
 * existem é: tempo total de Controller, tempo total em SQL (soma exata, via
 * QueryCollector) e tempo de renderização de View. "Tempo PHP puro" é a
 * diferença (Controller − SQL − View). Para profiling linha-a-linha real,
 * complemente com Xdebug (modo profile) ou Blackfire — este sistema aqui foca
 * em "qual página/qual query", não "qual linha".
 */
final class RequestProfiler
{
    private static float $requestStart = 0.0;
    private static ?float $controllerStart = null;
    private static float $controllerTimeMs = 0.0;
    private static ?float $viewStart = null;
    private static float $viewTimeMs = 0.0;
    private static ?string $controller = null;
    private static ?string $action = null;
    private static int $memoryStartBytes = 0;
    private static bool $finished = false;

    public static function start(): void
    {
        if (!Profiler::isEnabled()) {
            return;
        }
        self::$requestStart = microtime(true);
        self::$memoryStartBytes = memory_get_usage(true);
        QueryCollector::reset();
        ExplainAnalyzer::reset();
    }

    public static function setController(string $controller, string $action): void
    {
        if (!Profiler::isEnabled()) {
            return;
        }
        self::$controller = $controller;
        self::$action = $action;
    }

    public static function markControllerStart(): void
    {
        if (!Profiler::isEnabled()) {
            return;
        }
        self::$controllerStart = microtime(true);
    }

    public static function markControllerEnd(): void
    {
        if (!Profiler::isEnabled() || self::$controllerStart === null) {
            return;
        }
        self::$controllerTimeMs += (microtime(true) - self::$controllerStart) * 1000;
        self::$controllerStart = null;
    }

    public static function markViewStart(): void
    {
        if (!Profiler::isEnabled()) {
            return;
        }
        self::$viewStart = microtime(true);
    }

    public static function markViewEnd(): void
    {
        if (!Profiler::isEnabled() || self::$viewStart === null) {
            return;
        }
        self::$viewTimeMs += (microtime(true) - self::$viewStart) * 1000;
        self::$viewStart = null;
    }

    /**
     * Monta o relatório final do request, roda EXPLAIN/N+1/Index Advisor nas
     * queries lentas e grava o JSONL. Chamado uma única vez, no shutdown.
     */
    public static function finish(): void
    {
        if (!Profiler::isEnabled() || self::$finished) {
            return;
        }
        self::$finished = true;

        // Fecha timers que porventura não tenham sido fechados (ex.: exception no meio).
        self::markControllerEnd();
        self::markViewEnd();

        $queries = QueryCollector::all();
        $totalSqlMs = QueryCollector::totalDurationMs();
        $totalPhpMs = (microtime(true) - (self::$requestStart ?: microtime(true))) * 1000;
        $phpOnlyMs = max(0.0, self::$controllerTimeMs - $totalSqlMs - self::$viewTimeMs);

        $nPlusOne = NPlusOneDetector::detect($queries);

        $explainThresholdMs = Profiler::explainThresholdMs();
        $slowQueries = array_values(array_filter($queries, static fn ($q) => $q['duration_ms'] >= $explainThresholdMs));
        usort($slowQueries, static fn ($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);

        $explainedSlowQueries = [];
        $pdo = self::resolvePdo();
        foreach ($slowQueries as $q) {
            $entry = $q;
            $entry['index_advice'] = $q['is_select'] ? IndexAdvisor::suggest($q['sql']) : null;
            if ($pdo !== null && $q['is_select']) {
                $realParams = QueryCollector::paramsForSeq((int) $q['seq']);
                $entry['explain'] = ExplainAnalyzer::analyze($pdo, $q['sql'], $realParams);
            } else {
                $entry['explain'] = null;
            }
            $explainedSlowQueries[] = $entry;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url((string) $uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        $alerts = self::buildAlerts($queries, $totalPhpMs, $nPlusOne, $explainedSlowQueries);

        $payload = [
            'ts' => date('c'),
            'route' => $path,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'controller' => self::$controller,
            'action' => self::$action,
            'controller_action' => self::$controller !== null ? self::$controller . '@' . self::$action : null,
            'tenant_slug' => defined('TENANT_SLUG') ? (string) TENANT_SLUG : '',
            'tenant_id' => defined('TENANT_ID') ? (int) TENANT_ID : null,
            'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            'user_type' => $_SESSION['user_type'] ?? null,
            'time_php_ms' => round($phpOnlyMs, 2),
            'time_sql_ms' => round($totalSqlMs, 2),
            'time_view_ms' => round(self::$viewTimeMs, 2),
            'time_total_ms' => round($totalPhpMs, 2),
            'queries_count' => count($queries),
            'queries_error_count' => count(array_filter($queries, static fn ($q) => $q['error'] !== null)),
            'slowest_query_ms' => $queries === [] ? 0.0 : max(array_column($queries, 'duration_ms')),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'memory_start_mb' => round(self::$memoryStartBytes / 1024 / 1024, 2),
            'memory_growth_mb' => round((memory_get_peak_usage(true) - self::$memoryStartBytes) / 1024 / 1024, 2),
            'n_plus_one' => $nPlusOne,
            'has_n_plus_one' => $nPlusOne !== [],
            'slow_queries' => array_slice($explainedSlowQueries, 0, 20),
            'alerts' => $alerts,
        ];

        PerformanceLogger::log($payload);

        // Libera memória / evita vazar dado de um request pro próximo em contextos
        // de worker persistente (não é o caso do php-fpm clássico, mas é barato e seguro).
        QueryCollector::reset();
        self::$controller = null;
        self::$action = null;
        self::$controllerTimeMs = 0.0;
        self::$viewTimeMs = 0.0;
        self::$finished = false;
    }

    /**
     * @param list<array<string,mixed>> $queries
     * @param list<array<string,mixed>> $nPlusOne
     * @param list<array<string,mixed>> $slowQueries
     * @return list<array{level:string,message:string}>
     */
    private static function buildAlerts(array $queries, float $totalPhpMs, array $nPlusOne, array $slowQueries): array
    {
        $alerts = [];
        $count = count($queries);

        if ($count > 100) {
            $alerts[] = ['level' => 'critical', 'message' => "{$count} queries num único request (>100)."];
        } elseif ($count > 50) {
            $alerts[] = ['level' => 'warning', 'message' => "{$count} queries num único request (>50)."];
        }

        if ($totalPhpMs > 2000) {
            $alerts[] = ['level' => 'critical', 'message' => 'Request levou mais de 2s (' . round($totalPhpMs) . 'ms).'];
        }

        $memPeakMb = memory_get_peak_usage(true) / 1024 / 1024;
        if ($memPeakMb > 50) {
            $alerts[] = ['level' => 'warning', 'message' => 'Pico de memória acima de 50MB (' . round($memPeakMb, 1) . 'MB).'];
        }

        if ($nPlusOne !== []) {
            $alerts[] = ['level' => 'critical', 'message' => count($nPlusOne) . ' padrão(ões) de N+1 detectado(s).'];
        }

        foreach ($slowQueries as $q) {
            $flags = $q['explain']['flags'] ?? [];
            if (in_array('Full table scan', $flags, true)) {
                $alerts[] = ['level' => 'warning', 'message' => 'Full scan detectado numa query lenta (ver slow_queries).'];
                break;
            }
        }

        return $alerts;
    }

    private static function resolvePdo(): ?\PDO
    {
        try {
            if (class_exists('Database', false)) {
                return \Database::getInstance()->getPdo();
            }
        } catch (\Throwable $e) {
            // Sem conexão disponível — não tenta EXPLAIN.
        }
        return null;
    }
}
