<?php
/**
 * EducaTudo - Dashboard de Monitoramento (admin only)
 * Rotas: /monitoramento, /api/infra, /api/openai-usage, /api/users-stats, /api/system-health, /api/monitoramento/stream
 */

class MonitoramentoController extends BaseController
{
    private $auth;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->ensureAdmin();
    }

    private function ensureAdmin()
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            header('Location: ' . URL . '/admin');
            exit;
        }
    }

    /**
     * Página do dashboard (modo dark, real-time).
     * Dados iniciais são injetados no HTML para exibir imediatamente (evita falha de fetch/cookies no browser).
     */
    public function index()
    {
        $initialData = [
            'infra' => $this->getInfraData(),
            'users' => $this->getUsersStatsData(),
            'db' => $this->getDbData(),
        ];
        $this->view('admin/monitoramento/dashboard', [
            'url' => defined('URL') ? URL : '',
            'initialData' => $initialData,
        ]);
    }

    /**
     * GET /api/infra - CPU, RAM, Disco, Rede (%)
     */
    public function apiInfra()
    {
        $this->json($this->getInfraData());
    }

    /**
     * GET /api/openai-usage - Tokens e custo em % do limite/orçamento
     */
    public function apiOpenaiUsage()
    {
        $this->json($this->getOpenaiUsageData());
    }

    /**
     * GET /api/users-stats - Total e distribuição por tipo, % online
     */
    public function apiUsersStats()
    {
        $this->json($this->getUsersStatsData());
    }

    /**
     * GET /api/db-stats - Requisições ao banco (hoje): total, lentas, erros, tempo médio
     */
    public function apiDbStats()
    {
        $this->json($this->getDbData());
    }

    /**
     * GET /api/system-health - Taxa de erro e saúde geral
     */
    public function apiSystemHealth()
    {
        $this->json($this->getSystemHealthData());
    }

    /**
     * GET /api/monitoramento/stream - Server-Sent Events (atualização a cada 1s)
     * Frontend usa EventSource para real-time (equivalente a WebSocket para este uso).
     */
    public function apiStream()
    {
        @set_time_limit(0);
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 0);

        while (true) {
            $payload = [
                'infra' => $this->getInfraData(),
                'users' => $this->getUsersStatsData(),
                'db' => $this->getDbData(),
                'ts' => time(),
            ];
            echo "data: " . json_encode($payload) . "\n\n";
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
            if (connection_aborted()) {
                break;
            }
            sleep(1);
        }
        exit;
    }

    private function getInfraData()
    {
        try {
            $cpu = $this->getServerCpuPercent();
            $ramPercent = $this->getServerRamPercent();

            $diskPath = defined('FOLDER') && FOLDER ? dirname(__DIR__, 2) : __DIR__;
            $total = @disk_total_space($diskPath) ?: 1;
            $free = @disk_free_space($diskPath) ?: 0;
            $diskPercent = $total > 0 ? min(100, (int) round((($total - $free) / $total) * 100)) : 0;

            return [
                'cpu_percent' => (int) $cpu,
                'ram_percent' => (int) $ramPercent,
                'disk_percent' => (int) $diskPercent,
            ];
        } catch (\Throwable $e) {
            return [
                'cpu_percent' => 0,
                'ram_percent' => 0,
                'disk_percent' => 0,
            ];
        }
    }

    /**
     * CPU % real do servidor (Linux /proc/stat). Usa cache entre requisições para amostrar em 2 momentos.
     */
    private function getServerCpuPercent()
    {
        if (!@is_readable('/proc/stat')) {
            return $this->getServerCpuFallback();
        }
        $line = @file_get_contents('/proc/stat');
        if ($line === false) {
            return $this->getServerCpuFallback();
        }
        $first = strpos($line, "\n");
        $cpuLine = $first !== false ? substr($line, 0, $first) : $line;
        if (strpos($cpuLine, 'cpu ') !== 0) {
            return $this->getServerCpuFallback();
        }
        $parts = preg_split('/\s+/', trim($cpuLine), 6);
        if (count($parts) < 5) {
            return $this->getServerCpuFallback();
        }
        $user = (int) ($parts[1] ?? 0);
        $nice = (int) ($parts[2] ?? 0);
        $sys = (int) ($parts[3] ?? 0);
        $idle = (int) ($parts[4] ?? 0);
        $total = $user + $nice + $sys + $idle;

        $cacheDir = sys_get_temp_dir() . '/educatudo_monitor';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $cacheFile = $cacheDir . '/cpu_stat.json';
        $now = time();
        $cache = null;
        if (is_readable($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if ($raw !== false) {
                $cache = @json_decode($raw, true);
            }
        }
        if (is_array($cache) && isset($cache['total'], $cache['idle'], $cache['ts']) && ($now - (int) $cache['ts']) <= 15) {
            $dTotal = $total - (int) $cache['total'];
            $dIdle = $idle - (int) $cache['idle'];
            $pct = ($dTotal > 0) ? min(100, (int) round((1 - $dIdle / $dTotal) * 100)) : 0;
        } else {
            $pct = 0;
        }
        @file_put_contents($cacheFile, json_encode(['total' => $total, 'idle' => $idle, 'ts' => $now]), LOCK_EX);
        return $pct;
    }

    private function getServerCpuFallback()
    {
        if (function_exists('sys_getloadavg')) {
            $load = @sys_getloadavg();
            if ($load && isset($load[0])) {
                $cores = 1;
                if (function_exists('shell_exec')) {
                    $nproc = @shell_exec('nproc 2>/dev/null');
                    if ($nproc !== null && $nproc !== '') {
                        $cores = max(1, (int) trim($nproc));
                    }
                }
                return min(100, (int) round(($load[0] / $cores) * 100));
            }
        }
        return 0;
    }

    /**
     * RAM % real do servidor (Linux /proc/meminfo). MemTotal e MemAvailable (ou MemFree).
     */
    private function getServerRamPercent()
    {
        if (!@is_readable('/proc/meminfo')) {
            return $this->getServerRamFallback();
        }
        $content = @file_get_contents('/proc/meminfo');
        if ($content === false) {
            return $this->getServerRamFallback();
        }
        $total = 0;
        $available = 0;
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^MemTotal:\s+(\d+)\s+kB/i', $line, $m)) {
                $total = (int) $m[1];
            }
            if (preg_match('/^Mem(Available|Free):\s+(\d+)\s+kB/i', $line, $m)) {
                $available = (int) $m[2];
            }
        }
        if ($total > 0) {
            $used = $total - $available;
            return min(100, (int) round(($used / $total) * 100));
        }
        return $this->getServerRamFallback();
    }

    private function getServerRamFallback()
    {
        $memLimit = ini_get('memory_limit');
        $memLimitBytes = $this->parseMemoryLimit($memLimit);
        $ramUsed = memory_get_peak_usage(true);
        return $memLimitBytes > 0 ? min(100, (int) round(($ramUsed / $memLimitBytes) * 100)) : 0;
    }

    private function parseMemoryLimit($value)
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 128 * 1024 * 1024;
        }
        $unit = strtoupper(substr($value, -1));
        $num = (float) substr($value, 0, -1);
        switch ($unit) {
            case 'G':
                return (int) ($num * 1024 * 1024 * 1024);
            case 'M':
                return (int) ($num * 1024 * 1024);
            case 'K':
                return (int) ($num * 1024);
            default:
                return (int) $value;
        }
    }

    private function getOpenaiUsageData()
    {
        $dailyTokenLimit = (int) (getenv('OPENAI_DAILY_TOKEN_LIMIT') ?: 1000000);
        $monthlyTokenLimit = (int) (getenv('OPENAI_MONTHLY_TOKEN_LIMIT') ?: 30000000);
        $dailyBudget = (float) (getenv('OPENAI_DAILY_BUDGET') ?: 10.0);
        $monthlyBudget = (float) (getenv('OPENAI_MONTHLY_BUDGET') ?: 200.0);

        $tokensToday = 0;
        $costToday = 0.0;
        $tokensMonth = 0;
        $costMonth = 0.0;
        $openaiSource = 'internal';

        $fromApi = $this->fetchOpenaiUsageFromApi();
        if ($fromApi !== null) {
            $tokensToday = $fromApi['tokens_today'];
            $tokensMonth = $fromApi['tokens_month'];
            $costToday = $fromApi['cost_today_usd'];
            $costMonth = $fromApi['cost_month_usd'];
            $openaiSource = 'api';
        }

        if ($openaiSource === 'internal' && class_exists('App\Services\MetricsService')) {
            $metrics = \App\Services\MetricsService::getMetrics();
            $m = $metrics['metrics'] ?? [];
            $tokensToday = (int) ($m['tokens_today'] ?? 0);
            $costToday = (float) ($m['cost_today'] ?? 0);
            $today = date('Y-m-d');
            $firstDayMonth = date('Y-m-01');
            $tokensMonth = $tokensToday;
            $costMonth = $costToday;
            $dailyHistory = $m['daily_history'] ?? [];
            foreach ($dailyHistory as $day => $data) {
                if (strcmp($day, $firstDayMonth) >= 0 && $day < $today) {
                    $tokensMonth += (int) ($data['tokens_today'] ?? 0);
                    $costMonth += (float) ($data['cost_today'] ?? 0);
                }
            }
        }

        $pTokenDay = $dailyTokenLimit > 0 ? min(100, round(($tokensToday / $dailyTokenLimit) * 100, 1)) : 0;
        $pTokenMonth = $monthlyTokenLimit > 0 ? min(100, round(($tokensMonth / $monthlyTokenLimit) * 100, 1)) : 0;
        $pCostDay = $dailyBudget > 0 ? min(100, round(($costToday / $dailyBudget) * 100, 1)) : 0;
        $pCostMonth = $monthlyBudget > 0 ? min(100, round(($costMonth / $monthlyBudget) * 100, 1)) : 0;

        return [
            'tokens_today' => $tokensToday,
            'tokens_month' => $tokensMonth,
            'cost_today_usd' => round($costToday, 4),
            'cost_month_usd' => round($costMonth, 4),
            'tokens_today_percent_limit' => $pTokenDay,
            'tokens_month_percent_limit' => $pTokenMonth,
            'cost_today_percent_budget' => $pCostDay,
            'cost_month_percent_budget' => $pCostMonth,
            'openai_source' => $openaiSource,
        ];
    }

    /**
     * Uso e custo da organização via API oficial OpenAI.
     * GET /v1/organization/usage/completions e GET /v1/organization/costs.
     * Usa OPENAI_ADMIN_KEY ou OPENAI_API_KEY (Admin Key pode ser necessário para usage por organização).
     * @return array{tokens_today, tokens_month, cost_today_usd, cost_month_usd}|null
     */
    private function fetchOpenaiUsageFromApi()
    {
        $apiKey = getenv('OPENAI_ADMIN_KEY') ?: getenv('OPENAI_API_KEY');
        if (!$apiKey || !function_exists('curl_init')) {
            return null;
        }
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $monthStart = strtotime(date('Y-m-01 00:00:00'));
        $now = time();

        $tokensToday = 0;
        $tokensMonth = 0;
        $usageData = $this->openaiApiGet('https://api.openai.com/v1/organization/usage/completions', $apiKey, [
            'start_time' => $monthStart,
            'end_time' => $now,
            'bucket_width' => '1d',
            'limit' => 31,
        ]);
        if ($usageData === null) {
            return null;
        }
        foreach ($usageData as $bucket) {
            $start = (int) ($bucket['start_time'] ?? 0);
            $results = $bucket['results'] ?? [];
            $bucketTotal = 0;
            foreach ($results as $r) {
                $bucketTotal += (int) ($r['input_tokens'] ?? 0) + (int) ($r['output_tokens'] ?? 0);
            }
            $tokensMonth += $bucketTotal;
            if ($start >= $todayStart) {
                $tokensToday += $bucketTotal;
            }
        }

        $costToday = 0.0;
        $costMonth = 0.0;
        $costsData = $this->openaiApiGet('https://api.openai.com/v1/organization/costs', $apiKey, [
            'start_time' => $monthStart,
            'end_time' => $now,
        ]);
        if ($costsData !== null) {
            foreach ($costsData as $bucket) {
                $start = (int) ($bucket['start_time'] ?? 0);
                foreach ($bucket['results'] ?? [] as $r) {
                    $amount = $r['amount'] ?? null;
                    $value = is_array($amount) ? (float) ($amount['value'] ?? 0) : 0;
                    $costMonth += $value;
                    if ($start >= $todayStart) {
                        $costToday += $value;
                    }
                }
            }
        }

        return [
            'tokens_today' => $tokensToday,
            'tokens_month' => $tokensMonth,
            'cost_today_usd' => $costToday,
            'cost_month_usd' => $costMonth,
        ];
    }

    private function openaiApiGet($url, $apiKey, array $params)
    {
        $query = http_build_query($params);
        $fullUrl = $url . ($query !== '' ? '?' . $query : '');
        $ch = curl_init($fullUrl);
        if (!$ch) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || $body === false) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['data'])) {
            return null;
        }
        $all = $data['data'];
        $nextPage = $data['next_page'] ?? null;
        while ($nextPage) {
            $ch2 = curl_init($url . '?page=' . urlencode($nextPage));
            if (!$ch2) {
                break;
            }
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
            ]);
            $body2 = curl_exec($ch2);
            $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            if ($code2 !== 200 || $body2 === false) {
                break;
            }
            $data2 = json_decode($body2, true);
            if (!is_array($data2) || !isset($data2['data'])) {
                break;
            }
            $all = array_merge($all, $data2['data']);
            $nextPage = $data2['next_page'] ?? null;
        }
        return $all;
    }

    private function getUsersStatsData()
    {
        $totalAlunos = (int) $this->db->fetch("SELECT COUNT(*) AS c FROM alunos WHERE ativo = 1")['c'];
        $totalProfessores = (int) $this->db->fetch("SELECT COUNT(*) AS c FROM professores WHERE ativo = 1")['c'];
        $totalAdmins = (int) $this->db->fetch("SELECT COUNT(*) AS c FROM usuarios WHERE tipo IN ('admin','admin_escola') AND ativo = 1")['c'];
        $total = $totalAlunos + $totalProfessores + $totalAdmins;
        if ($total === 0) {
            $total = 1;
        }
        $alunosPercent = round(($totalAlunos / $total) * 100);
        $professoresPercent = round(($totalProfessores / $total) * 100);
        $adminsPercent = round(($totalAdmins / $total) * 100);

        $onlinePercent = 0;
        $hasLastActivity = false;
        try {
            $cols = $this->db->fetch("SHOW COLUMNS FROM alunos LIKE 'ultimo_acesso'");
            $hasLastActivity = !empty($cols);
        } catch (Exception $e) {
        }
        if ($hasLastActivity) {
            $fiveMinAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
            $online = (int) $this->db->fetch(
                "SELECT COUNT(*) AS c FROM alunos WHERE ativo = 1 AND ultimo_acesso >= ?",
                [$fiveMinAgo]
            )['c'];
            $onlinePercent = $totalAlunos > 0 ? min(100, round(($online / $totalAlunos) * 100)) : 0;
        }

        $acessosHojeAlunos = 0;
        $acessosHojeProfessores = 0;
        $acessosHojeAdmins = 0;
        try {
            $acessosHojeAlunos = (int) $this->db->fetch(
                "SELECT COUNT(*) AS c FROM alunos_sessoes_acesso WHERE DATE(login_at) = CURDATE()"
            )['c'];
        } catch (\Throwable $e) {
        }
        try {
            $acessosHojeProfessores = (int) $this->db->fetch(
                "SELECT COUNT(*) AS c FROM logs_auditoria WHERE action = 'LOGIN' AND user_role = 'professor' AND DATE(created_at) = CURDATE()"
            )['c'];
        } catch (\Throwable $e) {
        }
        try {
            $acessosHojeAdmins = (int) $this->db->fetch(
                "SELECT COUNT(*) AS c FROM logs_auditoria WHERE action = 'LOGIN' AND user_role IN ('admin','admin_escola') AND DATE(created_at) = CURDATE()"
            )['c'];
        } catch (\Throwable $e) {
        }
        $acessosHojeTotal = $acessosHojeAlunos + $acessosHojeProfessores + $acessosHojeAdmins;

        return [
            'total_users' => $totalAlunos + $totalProfessores + $totalAdmins,
            'total_alunos' => $totalAlunos,
            'total_professores' => $totalProfessores,
            'total_admins' => $totalAdmins,
            'alunos_percent' => $alunosPercent,
            'professores_percent' => $professoresPercent,
            'admins_percent' => $adminsPercent,
            'online_percent' => $onlinePercent,
            'acessos_hoje_alunos' => $acessosHojeAlunos,
            'acessos_hoje_professores' => $acessosHojeProfessores,
            'acessos_hoje_admins' => $acessosHojeAdmins,
            'acessos_hoje_total' => $acessosHojeTotal,
        ];
    }

    /**
     * Métricas de banco (hoje): requisições, lentas, erros, tempo médio (MetricsService).
     */
    private function getDbData()
    {
        $queries = 0;
        $slowQueries = 0;
        $errors = 0;
        $avgTimeMs = 0.0;
        if (class_exists('App\Services\MetricsService')) {
            $metrics = \App\Services\MetricsService::getMetrics();
            $m = $metrics['metrics'] ?? [];
            $queries = (int) ($m['db_queries'] ?? 0);
            $slowQueries = (int) ($m['slow_queries'] ?? 0);
            $errors = (int) ($m['db_errors'] ?? 0);
            $avgTimeMs = round(($m['avg_db_time'] ?? 0) * 1000, 1);
        }
        return [
            'db_queries' => $queries,
            'slow_queries' => $slowQueries,
            'db_errors' => $errors,
            'avg_db_time_ms' => $avgTimeMs,
        ];
    }

    private function getSystemHealthData()
    {
        $errorRate = 0;
        $systemStatusPercent = 100;

        if (class_exists('App\Services\MetricsService')) {
            $metrics = \App\Services\MetricsService::getMetrics();
            $m = $metrics['metrics'] ?? [];
            $errors500 = (int) ($m['errors_500'] ?? 0);
            $accessesTotal = (int) ($m['accesses_total'] ?? 1);
            if ($accessesTotal > 0) {
                $errorRate = min(100, round(($errors500 / $accessesTotal) * 100));
            }
            $systemStatusPercent = max(0, 100 - $errorRate);
        }

        return [
            'error_rate_percent' => $errorRate,
            'system_status_percent' => $systemStatusPercent,
        ];
    }

}
