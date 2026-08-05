<?php
/**
 * EducaTudo - Serviço de Métricas
 * Coleta e armazena métricas de uso da aplicação
 */

namespace App\Services;

class MetricsService
{
    private static $cacheFile;
    private static $initialized = false;

    /**
     * Inicializa o serviço de métricas
     */
    private static function init()
    {
        if (self::$initialized) {
            return;
        }

        self::$cacheFile = __DIR__ . '/../../storage/metrics_cache.json';
        self::$initialized = true;
    }

    /**
     * Registra métricas de uso da IA
     *
     * @param array $metrics Dados das métricas
     */
    public static function recordAIMetrics($metrics)
    {
        self::init();

        try {
            $currentMetrics = self::loadMetrics();
            self::rolloverDailyIfNeeded($currentMetrics);
            $totalTokens = $metrics['total_tokens'] ?? 0;

            // Incrementar contadores
            $currentMetrics['ai_requests'] = ($currentMetrics['ai_requests'] ?? 0) + 1;
            $currentMetrics['tokens_today'] = ($currentMetrics['tokens_today'] ?? 0) + $totalTokens;
            $currentMetrics['cost_today'] = ($currentMetrics['cost_today'] ?? 0) + ($metrics['cost'] ?? 0);

            // Tokens por minuto (contagem automática)
            $currentMinute = (int) floor(time() / 60);
            $lastMinute = $currentMetrics['tokens_minute_last'] ?? null;
            if ($lastMinute === null || $currentMinute !== (int) $lastMinute) {
                $currentMetrics['tokens_minute'] = $currentMetrics['tokens_minute_current'] ?? 0;
                $currentMetrics['tokens_minute_last'] = $currentMinute;
                $currentMetrics['tokens_minute_current'] = 0;
            }
            $currentMetrics['tokens_minute_current'] = ($currentMetrics['tokens_minute_current'] ?? 0) + $totalTokens;
            $currentMetrics['tokens_minute'] = $currentMetrics['tokens_minute_current'];

            // Métricas por tipo de uso
            $usageType = $metrics['usage_type'] ?? 'general';
            $currentMetrics['ai_usage_types'][$usageType] = ($currentMetrics['ai_usage_types'][$usageType] ?? 0) + 1;

            // Tempo médio de resposta
            $currentMetrics['request_times'][] = $metrics['request_time'] ?? 0;
            if (count($currentMetrics['request_times']) > 1000) {
                array_shift($currentMetrics['request_times']); // Manter apenas últimas 1000 medições
            }
            $currentMetrics['avg_request_time'] = array_sum($currentMetrics['request_times']) / count($currentMetrics['request_times']);

            // Erros
            if (!empty($metrics['error']) || ($metrics['http_code'] ?? 200) >= 400) {
                $currentMetrics['ai_errors'] = ($currentMetrics['ai_errors'] ?? 0) + 1;
            }

            // Métricas por modelo
            $model = $metrics['model'] ?? 'unknown';
            if (!isset($currentMetrics['model_usage'][$model])) {
                $currentMetrics['model_usage'][$model] = [
                    'requests' => 0,
                    'tokens' => 0,
                    'cost' => 0
                ];
            }
            $currentMetrics['model_usage'][$model]['requests']++;
            $currentMetrics['model_usage'][$model]['tokens'] += $metrics['total_tokens'] ?? 0;
            $currentMetrics['model_usage'][$model]['cost'] += $metrics['cost'] ?? 0;

            self::saveMetrics($currentMetrics);

        } catch (\Exception $e) {
            // Não interromper execução em caso de erro no registro de métricas
            error_log("Erro ao registrar métricas IA: " . $e->getMessage());
        }
    }

    /**
     * Registra métricas de banco de dados
     *
     * @param array $metrics Dados das métricas
     */
    public static function recordDatabaseMetrics($metrics)
    {
        self::init();

        try {
            $currentMetrics = self::loadMetrics();
            self::rolloverDailyIfNeeded($currentMetrics);

            // Incrementar contadores
            $currentMetrics['db_queries'] = ($currentMetrics['db_queries'] ?? 0) + 1;
            $currentMetrics['db_query_time'] = ($currentMetrics['db_query_time'] ?? 0) + ($metrics['query_time'] ?? 0);

            // Queries lentas
            if (($metrics['query_time'] ?? 0) > 0.5) { // > 500ms
                $currentMetrics['slow_queries'] = ($currentMetrics['slow_queries'] ?? 0) + 1;
            }

            // Erros de banco
            if (!empty($metrics['error'])) {
                $currentMetrics['db_errors'] = ($currentMetrics['db_errors'] ?? 0) + 1;
            }

            // Tempo médio de query
            $currentMetrics['db_query_times'][] = $metrics['query_time'] ?? 0;
            if (count($currentMetrics['db_query_times']) > 1000) {
                array_shift($currentMetrics['db_query_times']);
            }
            if (!empty($currentMetrics['db_query_times'])) {
                $currentMetrics['avg_db_time'] = array_sum($currentMetrics['db_query_times']) / count($currentMetrics['db_query_times']);
            }

            self::saveMetrics($currentMetrics);

        } catch (\Exception $e) {
            error_log("Erro ao registrar métricas DB: " . $e->getMessage());
        }
    }

    /**
     * Registra métricas de DB agregadas por request (uma chamada ao fim do request).
     * Evita loadMetrics/saveMetrics em toda query.
     *
     * @param array $buffer db_queries, db_query_time, slow_queries, db_errors, db_query_times
     */
    public static function recordDatabaseMetricsAggregated(array $buffer): void
    {
        self::init();
        try {
            $currentMetrics = self::loadMetrics();
            self::rolloverDailyIfNeeded($currentMetrics);
            $currentMetrics['db_queries'] = ($currentMetrics['db_queries'] ?? 0) + (int) ($buffer['db_queries'] ?? 0);
            $currentMetrics['db_query_time'] = ($currentMetrics['db_query_time'] ?? 0) + (float) ($buffer['db_query_time'] ?? 0);
            $currentMetrics['slow_queries'] = ($currentMetrics['slow_queries'] ?? 0) + (int) ($buffer['slow_queries'] ?? 0);
            $currentMetrics['db_errors'] = ($currentMetrics['db_errors'] ?? 0) + (int) ($buffer['db_errors'] ?? 0);
            $times = $buffer['db_query_times'] ?? [];
            if (is_array($times)) {
                foreach ($times as $t) {
                    $currentMetrics['db_query_times'][] = (float) $t;
                }
                if (isset($currentMetrics['db_query_times']) && count($currentMetrics['db_query_times']) > 1000) {
                    $currentMetrics['db_query_times'] = array_slice($currentMetrics['db_query_times'], -1000);
                }
                if (!empty($currentMetrics['db_query_times'])) {
                    $currentMetrics['avg_db_time'] = array_sum($currentMetrics['db_query_times']) / count($currentMetrics['db_query_times']);
                }
            }
            $currentMetrics['last_updated'] = time();
            self::saveMetrics($currentMetrics);
        } catch (\Exception $e) {
            error_log("Erro ao registrar métricas DB agregadas: " . $e->getMessage());
        }
    }

    /**
     * Registra métricas gerais do sistema
     *
     * @param array $metrics Dados das métricas
     */
    public static function recordSystemMetrics($metrics)
    {
        self::init();

        try {
            $currentMetrics = self::loadMetrics();

            // Uso de memória
            if (isset($metrics['memory_peak'])) {
                $currentMetrics['memory_peaks'][] = $metrics['memory_peak'];
                if (count($currentMetrics['memory_peaks']) > 100) {
                    array_shift($currentMetrics['memory_peaks']);
                }
                $currentMetrics['memory_peak'] = max($currentMetrics['memory_peaks']);
            }

            // Erros HTTP 500
            if (isset($metrics['http_500'])) {
                $currentMetrics['errors_500'] = ($currentMetrics['errors_500'] ?? 0) + $metrics['http_500'];
            }

            // Requests por minuto
            if (isset($metrics['requests_minute'])) {
                $currentMetrics['requests_minute'] = $metrics['requests_minute'];
                $currentMetrics['requests_history'][] = [
                    'timestamp' => time(),
                    'count' => $metrics['requests_minute']
                ];

                // Manter apenas últimas 60 medições (última hora)
                if (count($currentMetrics['requests_history']) > 60) {
                    array_shift($currentMetrics['requests_history']);
                }
            }

            // Timestamp da última atualização
            $currentMetrics['last_updated'] = time();

            self::saveMetrics($currentMetrics);

        } catch (\Exception $e) {
            error_log("Erro ao registrar métricas sistema: " . $e->getMessage());
        }
    }

    /**
     * Obtém métricas atuais
     *
     * @return array Métricas consolidadas
     */
    public static function getMetrics()
    {
        self::init();

        try {
            $metrics = self::loadMetrics();
            $rolled = self::rolloverDailyIfNeeded($metrics);
            if ($rolled) {
                self::saveMetrics($metrics);
            }

            // Adicionar informações da escola
            $config = require __DIR__ . '/../../config/app.php';
            $schoolId = getenv('SCHOOL_ID') ?: 'default_school';
            $schoolName = $config['school']['name'] ?? 'EducaTudo';

            // Adicionar identificador do servidor
            $serverId = getenv('SERVER_ID') ?: gethostname();

            return [
                'school_id' => $schoolId,
                'school_name' => $schoolName,
                'server_id' => $serverId,
                'timestamp' => time(),
                'metrics' => $metrics
            ];

        } catch (\Exception $e) {
            error_log("Erro ao obter métricas: " . $e->getMessage());
            return [
                'school_id' => 'default_school',
                'school_name' => 'EducaTudo',
                'server_id' => gethostname(),
                'timestamp' => time(),
                'metrics' => []
            ];
        }
    }

    /**
     * Reseta métricas diárias (chamado diariamente)
     */
    public static function resetDailyMetrics()
    {
        self::init();

        try {
            $currentMetrics = self::loadMetrics();

            // Resetar métricas diárias
            $currentMetrics['tokens_today'] = 0;
            $currentMetrics['cost_today'] = 0;
            $currentMetrics['ai_requests'] = 0;
            $currentMetrics['db_queries'] = 0;
            $currentMetrics['db_query_time'] = 0;
            $currentMetrics['slow_queries'] = 0;
            $currentMetrics['ai_errors'] = 0;
            $currentMetrics['db_errors'] = 0;
            $currentMetrics['errors_500'] = 0;
            $currentMetrics['ai_usage_types'] = [];
            $currentMetrics['request_times'] = [];
            $currentMetrics['db_query_times'] = [];
            $currentMetrics['memory_peaks'] = [];
            $currentMetrics['accesses_total'] = 0;
            $currentMetrics['accesses_by_type'] = [];
            $currentMetrics['accesses_by_uri'] = [];
            $currentMetrics['requests_minute'] = 0;
            $currentMetrics['requests_minute_last'] = null;
            $currentMetrics['requests_minute_current'] = 0;
            $currentMetrics['tokens_minute'] = 0;
            $currentMetrics['tokens_minute_last'] = null;
            $currentMetrics['tokens_minute_current'] = 0;
            $currentMetrics['current_day'] = date('Y-m-d');

            // Manter métricas históricas
            $currentMetrics['last_reset'] = time();

            self::saveMetrics($currentMetrics);

        } catch (\Exception $e) {
            error_log("Erro ao resetar métricas diárias: " . $e->getMessage());
        }
    }

    /** Tamanho máximo do arquivo de cache (512 KB) para evitar estouro de memória ao decodificar JSON */
    private static $maxCacheFileBytes = 256 * 1024;

    /**
     * Carrega métricas do cache
     *
     * @return array Métricas carregadas
     */
    private static function loadMetrics()
    {
        $metrics = [];
        if (file_exists(self::$cacheFile)) {
            $size = @filesize(self::$cacheFile);
            if ($size !== false && $size > self::$maxCacheFileBytes) {
                @rename(self::$cacheFile, self::$cacheFile . '.bak.' . date('Y-m-d_His'));
                $metrics = self::getDefaultMetrics();
                self::mergeTodayBackup($metrics);
                return $metrics;
            }
            // Evitar carregar se memória já estiver alta (outra requisição pode ter deixado fragmentada)
            if (function_exists('memory_get_usage') && memory_get_usage(true) > 220 * 1024 * 1024) {
                return self::getDefaultMetrics();
            }
            $data = @file_get_contents(self::$cacheFile);
            if ($data !== false) {
                $metrics = @json_decode($data, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($metrics)) {
                    $metrics = [];
                } else {
                    // Limitar todos os arrays que podem crescer (evita Fatal memory exhausted ao decodificar cache antigo)
                    if (isset($metrics['request_times']) && is_array($metrics['request_times']) && count($metrics['request_times']) > 500) {
                        $metrics['request_times'] = array_slice($metrics['request_times'], -500);
                    }
                    if (isset($metrics['db_query_times']) && is_array($metrics['db_query_times']) && count($metrics['db_query_times']) > 500) {
                        $metrics['db_query_times'] = array_slice($metrics['db_query_times'], -500);
                    }
                    if (isset($metrics['memory_peaks']) && is_array($metrics['memory_peaks']) && count($metrics['memory_peaks']) > 200) {
                        $metrics['memory_peaks'] = array_slice($metrics['memory_peaks'], -200);
                    }
                    if (isset($metrics['access_log']) && is_array($metrics['access_log']) && count($metrics['access_log']) > 100) {
                        $metrics['access_log'] = array_slice($metrics['access_log'], -100);
                    }
                    if (isset($metrics['daily_history']) && is_array($metrics['daily_history']) && count($metrics['daily_history']) > 60) {
                        $metrics['daily_history'] = array_slice($metrics['daily_history'], -60, null, true);
                    }
                    if (isset($metrics['requests_history']) && is_array($metrics['requests_history']) && count($metrics['requests_history']) > 60) {
                        $metrics['requests_history'] = array_slice($metrics['requests_history'], -60);
                    }
                    if (isset($metrics['model_usage']) && is_array($metrics['model_usage']) && count($metrics['model_usage']) > 50) {
                        $metrics['model_usage'] = array_slice($metrics['model_usage'], -50, null, true);
                    }
                }
            }
        }
        if (empty($metrics)) {
            $metrics = self::getDefaultMetrics();
        }
        self::mergeTodayBackup($metrics);
        return $metrics;
    }

    /**
     * Mescla métricas de banco com o backup do dia (contador cumulativo nunca diminui).
     */
    private static function mergeTodayBackup(array &$metrics)
    {
        $path = self::getTodayBackupPath();
        if (!file_exists($path)) {
            return;
        }
        if (@filesize($path) > self::$maxCacheFileBytes) {
            return;
        }
        $data = @file_get_contents($path);
        if ($data === false) {
            return;
        }
        $backup = json_decode($data, true);
        if (!is_array($backup)) {
            return;
        }
        $today = date('Y-m-d');
        if (($backup['date'] ?? '') !== $today) {
            return;
        }
        $metrics['db_queries'] = max((int) ($metrics['db_queries'] ?? 0), (int) ($backup['db_queries'] ?? 0));
        $metrics['slow_queries'] = max((int) ($metrics['slow_queries'] ?? 0), (int) ($backup['slow_queries'] ?? 0));
        $metrics['db_errors'] = max((int) ($metrics['db_errors'] ?? 0), (int) ($backup['db_errors'] ?? 0));
    }

    private static function getTodayBackupPath()
    {
        $dir = dirname(self::$cacheFile);
        return $dir . '/metrics_today_' . date('Y-m-d') . '.json';
    }

    /**
     * Persiste totais do dia (db) para o contador não zerar se o cache principal for perdido.
     */
    private static function saveTodayBackup(array $metrics)
    {
        $path = self::getTodayBackupPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            return;
        }
        $payload = [
            'date' => date('Y-m-d'),
            'db_queries' => (int) ($metrics['db_queries'] ?? 0),
            'slow_queries' => (int) ($metrics['slow_queries'] ?? 0),
            'db_errors' => (int) ($metrics['db_errors'] ?? 0),
        ];
        @file_put_contents($path, json_encode($payload), LOCK_EX);
    }

    /**
     * Salva métricas no cache
     *
     * @param array $metrics Métricas a salvar
     */
    private static function saveMetrics($metrics)
    {
        // Cache em arquivo desabilitado (evita criar metrics_cache.json e problemas de permissão)
        return;
    }

    /**
     * Retorna métricas padrão
     *
     * @return array Métricas padrão
     */
    private static function getDefaultMetrics()
    {
        return [
            'tokens_today' => 0,
            'cost_today' => 0.0,
            'ai_requests' => 0,
            'db_queries' => 0,
            'db_query_time' => 0.0,
            'slow_queries' => 0,
            'ai_errors' => 0,
            'db_errors' => 0,
            'errors_500' => 0,
            'memory_peak' => 0,
            'requests_minute' => 0,
            'avg_request_time' => 0.0,
            'avg_db_time' => 0.0,
            'ai_usage_types' => [],
            'model_usage' => [],
            'request_times' => [],
            'db_query_times' => [],
            'memory_peaks' => [],
            'requests_history' => [],
            'accesses_total' => 0,
            'accesses_by_type' => [],
            'accesses_by_uri' => [],
            'requests_minute_last' => null,
            'requests_minute_current' => 0,
            'tokens_minute_last' => null,
            'tokens_minute_current' => 0,
            'tokens_minute' => 0,
            'current_day' => date('Y-m-d'),
            'daily_history' => [],
            'access_log' => [],
            'last_updated' => time(),
            'last_reset' => time()
        ];
    }

    /**
     * Registra erro HTTP 500
     */
    public static function recordHttp500Error()
    {
        self::recordSystemMetrics(['http_500' => 1]);
    }

    /**
     * Registra uso de memória por request
     */
    public static function recordMemoryUsage()
    {
        $peakUsage = memory_get_peak_usage(true) / 1024 / 1024; // Em MB
        self::recordSystemMetrics(['memory_peak' => $peakUsage]);
    }

    /**
     * Registra requests por minuto
     *
     * @param int $count Número de requests no último minuto
     */
    public static function recordRequestsPerMinute($count)
    {
        self::recordSystemMetrics(['requests_minute' => $count]);
    }

    /**
     * Registra acesso à plataforma (geral e por tipo de usuário)
     *
     * @param string $userType Tipo de usuário (aluno/professor/admin/etc)
     * @param string $requestUri URI acessada
     */
    public static function recordPlatformAccess($userType, $requestUri, $context = [])
    {
        self::init();

        try {
            $currentMetrics = self::loadMetrics();
            self::rolloverDailyIfNeeded($currentMetrics);

            // Total de acessos
            $currentMetrics['accesses_total'] = ($currentMetrics['accesses_total'] ?? 0) + 1;

            // Por tipo de usuário
            $type = self::normalizeUserType($userType);
            if (!isset($currentMetrics['accesses_by_type'][$type])) {
                $currentMetrics['accesses_by_type'][$type] = 0;
            }
            $currentMetrics['accesses_by_type'][$type]++;

            // Por URI (limitar crescimento)
            $uri = $requestUri ?: 'unknown';
            if (!isset($currentMetrics['accesses_by_uri'][$uri])) {
                $currentMetrics['accesses_by_uri'][$uri] = 0;
            }
            $currentMetrics['accesses_by_uri'][$uri]++;
            if (count($currentMetrics['accesses_by_uri']) > 200) {
                arsort($currentMetrics['accesses_by_uri']);
                $currentMetrics['accesses_by_uri'] = array_slice($currentMetrics['accesses_by_uri'], 0, 200, true);
            }

            // Requests por minuto (contagem automática)
            $currentMinute = (int) floor(time() / 60);
            $lastMinute = $currentMetrics['requests_minute_last'] ?? null;

            if ($lastMinute === null || $currentMinute !== (int) $lastMinute) {
                if ($lastMinute !== null) {
                    $currentMetrics['requests_minute'] = $currentMetrics['requests_minute_current'] ?? 0;
                    $currentMetrics['requests_history'][] = [
                        'timestamp' => time(),
                        'count' => $currentMetrics['requests_minute']
                    ];
                    if (count($currentMetrics['requests_history']) > 60) {
                        array_shift($currentMetrics['requests_history']);
                    }
                }

                $currentMetrics['requests_minute_last'] = $currentMinute;
                $currentMetrics['requests_minute_current'] = 0;
            }

            $currentMetrics['requests_minute_current'] = ($currentMetrics['requests_minute_current'] ?? 0) + 1;
            // Exibir contagem do minuto atual
            $currentMetrics['requests_minute'] = $currentMetrics['requests_minute_current'];

            // Log detalhado de acessos (limitado), ignorando endpoints ruidosos
            if (self::shouldLogAccess($uri, $context['method'] ?? 'GET')) {
                $currentMetrics['access_log'] = $currentMetrics['access_log'] ?? [];
            $entry = [
                'timestamp' => time(),
                'timestamp_iso' => date('c'),
                    'user_id' => $context['user_id'] ?? null,
                    'user_type' => $type,
                    'ip' => $context['ip'] ?? null,
                    'method' => $context['method'] ?? null,
                    'uri' => $uri,
                    'referer' => $context['referer'] ?? null,
                    'user_agent' => isset($context['user_agent']) ? substr((string) $context['user_agent'], 0, 255) : null
                ];
                $currentMetrics['access_log'][] = $entry;
                if (count($currentMetrics['access_log']) > 200) {
                    $currentMetrics['access_log'] = array_slice($currentMetrics['access_log'], -200);
                }
            }

            // Timestamp da última atualização
            $currentMetrics['last_updated'] = time();

            self::saveMetrics($currentMetrics);

        } catch (\Exception $e) {
            error_log("Erro ao registrar acesso: " . $e->getMessage());
        }
    }

    /**
     * Retorna métricas de um dia específico (YYYY-MM-DD)
     */
    public static function getMetricsByDate($date)
    {
        self::init();

        try {
            $metrics = self::loadMetrics();
            $rolled = self::rolloverDailyIfNeeded($metrics);
            if ($rolled) {
                self::saveMetrics($metrics);
            }

            $targetDate = $date ?: date('Y-m-d');
            if ($targetDate === date('Y-m-d')) {
                return $metrics;
            }

            $history = $metrics['daily_history'] ?? [];
            return $history[$targetDate] ?? null;
        } catch (\Exception $e) {
            error_log("Erro ao obter métricas por data: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Move métricas do dia anterior para o histórico e reseta contadores diários
     */
    private static function rolloverDailyIfNeeded(&$currentMetrics)
    {
        $today = date('Y-m-d');
        $currentDay = $currentMetrics['current_day'] ?? $today;

        if ($currentDay !== $today) {
            $currentMetrics['daily_history'] = $currentMetrics['daily_history'] ?? [];
            $currentMetrics['daily_history'][$currentDay] = [
                'tokens_today' => $currentMetrics['tokens_today'] ?? 0,
                'cost_today' => $currentMetrics['cost_today'] ?? 0,
                'ai_requests' => $currentMetrics['ai_requests'] ?? 0,
                'ai_errors' => $currentMetrics['ai_errors'] ?? 0,
                'model_usage' => $currentMetrics['model_usage'] ?? [],
                'avg_request_time' => $currentMetrics['avg_request_time'] ?? 0,
                'db_queries' => $currentMetrics['db_queries'] ?? 0,
                'slow_queries' => $currentMetrics['slow_queries'] ?? 0,
                'db_errors' => $currentMetrics['db_errors'] ?? 0,
                'avg_db_time' => $currentMetrics['avg_db_time'] ?? 0,
                'memory_peak' => $currentMetrics['memory_peak'] ?? 0,
                'errors_500' => $currentMetrics['errors_500'] ?? 0,
                'requests_minute' => $currentMetrics['requests_minute'] ?? 0,
                'tokens_minute' => $currentMetrics['tokens_minute'] ?? 0,
                'accesses_total' => $currentMetrics['accesses_total'] ?? 0,
                'accesses_by_type' => $currentMetrics['accesses_by_type'] ?? [],
                'date' => $currentDay
            ];

            // Manter histórico de até 120 dias
            ksort($currentMetrics['daily_history']);
            if (count($currentMetrics['daily_history']) > 120) {
                $currentMetrics['daily_history'] = array_slice($currentMetrics['daily_history'], -120, null, true);
            }

            // Resetar métricas diárias para o novo dia
            $currentMetrics['tokens_today'] = 0;
            $currentMetrics['cost_today'] = 0;
            $currentMetrics['ai_requests'] = 0;
            $currentMetrics['ai_errors'] = 0;
            $currentMetrics['request_times'] = [];
            $currentMetrics['avg_request_time'] = 0;
            $currentMetrics['db_queries'] = 0;
            $currentMetrics['slow_queries'] = 0;
            $currentMetrics['db_errors'] = 0;
            $currentMetrics['db_query_times'] = [];
            $currentMetrics['avg_db_time'] = 0;
            $currentMetrics['memory_peaks'] = [];
            $currentMetrics['memory_peak'] = 0;
            $currentMetrics['errors_500'] = 0;
            $currentMetrics['accesses_total'] = 0;
            $currentMetrics['accesses_by_type'] = [];
            $currentMetrics['accesses_by_uri'] = [];
            $currentMetrics['requests_minute'] = 0;
            $currentMetrics['requests_minute_last'] = null;
            $currentMetrics['requests_minute_current'] = 0;
            $currentMetrics['tokens_minute'] = 0;
            $currentMetrics['tokens_minute_last'] = null;
            $currentMetrics['tokens_minute_current'] = 0;
            $currentMetrics['model_usage'] = [];
            $currentMetrics['current_day'] = $today;
            $currentMetrics['access_log'] = [];
            return true;
        }

        return false;
    }

    /**
     * Retorna custos e tokens de LLM no período (para relatório Dev - Custos LLM).
     * Soma tokens e custo do histórico diário + dia atual se estiver no período.
     *
     * @param string $dateStart Data início (Y-m-d)
     * @param string $dateEnd Data fim (Y-m-d)
     * @return array { total_tokens, total_cost, by_day: [{date, tokens, cost}], by_model: { model: { tokens, cost, requests } } }
     */
    public static function getLLMCostsByDateRange($dateStart, $dateEnd)
    {
        self::init();

        try {
            $metrics = self::loadMetrics();
            $rolled = self::rolloverDailyIfNeeded($metrics);
            if ($rolled) {
                self::saveMetrics($metrics);
            }

            $totalTokens = 0;
            $totalCost = 0.0;
            $byDay = [];
            $byModel = [];

            $start = new \DateTime($dateStart);
            $end = new \DateTime($dateEnd);
            $today = date('Y-m-d');

            for ($d = clone $start; $d <= $end; $d->modify('+1 day')) {
                $dateStr = $d->format('Y-m-d');
                $tokens = 0;
                $cost = 0.0;
                $dayModelUsage = [];

                if ($dateStr === $today) {
                    $tokens = (int) ($metrics['tokens_today'] ?? 0);
                    $cost = (float) ($metrics['cost_today'] ?? 0);
                    $dayModelUsage = $metrics['model_usage'] ?? [];
                } else {
                    $dayData = $metrics['daily_history'][$dateStr] ?? null;
                    if ($dayData !== null) {
                        $tokens = (int) ($dayData['tokens_today'] ?? 0);
                        $cost = (float) ($dayData['cost_today'] ?? 0);
                        $dayModelUsage = $dayData['model_usage'] ?? [];
                    }
                }

                $totalTokens += $tokens;
                $totalCost += $cost;
                $byDay[] = [
                    'date' => $dateStr,
                    'tokens' => $tokens,
                    'cost' => $cost
                ];

                foreach ($dayModelUsage as $model => $data) {
                    if (!isset($byModel[$model])) {
                        $byModel[$model] = ['tokens' => 0, 'cost' => 0.0, 'requests' => 0];
                    }
                    $byModel[$model]['tokens'] += (int) ($data['tokens'] ?? 0);
                    $byModel[$model]['cost'] += (float) ($data['cost'] ?? 0);
                    $byModel[$model]['requests'] += (int) ($data['requests'] ?? 0);
                }
            }

            return [
                'total_tokens' => $totalTokens,
                'total_cost' => round($totalCost, 6),
                'by_day' => $byDay,
                'by_model' => $byModel,
                'date_start' => $dateStart,
                'date_end' => $dateEnd
            ];
        } catch (\Exception $e) {
            error_log("Erro ao obter custos LLM por período: " . $e->getMessage());
            return [
                'total_tokens' => 0,
                'total_cost' => 0.0,
                'by_day' => [],
                'by_model' => [],
                'date_start' => $dateStart,
                'date_end' => $dateEnd
            ];
        }
    }

    /**
     * Retorna custos e tokens de LLM no período a partir do banco (tabela logs_uso_llm).
     * Usado pelo relatório Custos LLM quando a tabela existe e tem dados.
     *
     * @param string $dateStart Data início (Y-m-d)
     * @param string $dateEnd Data fim (Y-m-d)
     * @return array { total_tokens, total_cost, by_day, by_model, by_usage_type, date_start, date_end }
     */
    public static function getLLMCostsFromDatabase($dateStart, $dateEnd)
    {
        $empty = [
            'total_tokens' => 0,
            'total_cost' => 0.0,
            'by_day' => [],
            'by_model' => [],
            'by_usage_type' => [],
            'date_start' => $dateStart,
            'date_end' => $dateEnd
        ];
        try {
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            $db = \Database::getInstance();
            $start = $dateStart . ' 00:00:00';
            $end = $dateEnd . ' 23:59:59';

            $totals = $db->fetch(
                "SELECT COALESCE(SUM(total_tokens), 0) as total_tokens, COALESCE(SUM(cost_usd), 0) as total_cost
                 FROM logs_uso_llm WHERE created_at >= ? AND created_at <= ?",
                [$start, $end]
            );
            $totalTokens = (int)($totals['total_tokens'] ?? 0);
            $totalCost = round((float)($totals['total_cost'] ?? 0), 6);

            $byDayRows = $db->fetchAll(
                "SELECT DATE(created_at) as dt, SUM(total_tokens) as tokens, SUM(cost_usd) as cost
                 FROM logs_uso_llm WHERE created_at >= ? AND created_at <= ?
                 GROUP BY DATE(created_at) ORDER BY dt ASC",
                [$start, $end]
            );
            $byDay = [];
            foreach ($byDayRows as $r) {
                $byDay[] = [
                    'date' => $r['dt'],
                    'tokens' => (int)$r['tokens'],
                    'cost' => (float)$r['cost']
                ];
            }

            $byModelRows = $db->fetchAll(
                "SELECT model, SUM(total_tokens) as tokens, SUM(cost_usd) as cost, COUNT(*) as requests
                 FROM logs_uso_llm WHERE created_at >= ? AND created_at <= ?
                 GROUP BY model ORDER BY cost DESC",
                [$start, $end]
            );
            $byModel = [];
            foreach ($byModelRows as $r) {
                $byModel[$r['model']] = [
                    'tokens' => (int)$r['tokens'],
                    'cost' => (float)$r['cost'],
                    'requests' => (int)$r['requests']
                ];
            }

            $byTypeRows = $db->fetchAll(
                "SELECT usage_type, SUM(total_tokens) as tokens, SUM(cost_usd) as cost, COUNT(*) as requests
                 FROM logs_uso_llm WHERE created_at >= ? AND created_at <= ?
                 GROUP BY usage_type ORDER BY cost DESC",
                [$start, $end]
            );
            $byUsageType = [];
            foreach ($byTypeRows as $r) {
                $byUsageType[$r['usage_type']] = [
                    'tokens' => (int)$r['tokens'],
                    'cost' => (float)$r['cost'],
                    'requests' => (int)$r['requests']
                ];
            }

            return [
                'total_tokens' => $totalTokens,
                'total_cost' => $totalCost,
                'by_day' => $byDay,
                'by_model' => $byModel,
                'by_usage_type' => $byUsageType,
                'date_start' => $dateStart,
                'date_end' => $dateEnd
            ];
        } catch (\Exception $e) {
            error_log("Erro ao obter custos LLM do banco: " . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Importa do banco as conversas e mensagens de IA e registra custos em logs_uso_llm (backfill).
     * - professores_ia_mensagens: tokens_usados por resposta do assistente (chat professor).
     * - tudinha_mensagens: mensagens com is_ia=1 (chat aluno/Tudinha), tokens estimados por tamanho do texto.
     * Requer coluna source em logs_uso_llm (migração 20260205_000003).
     *
     * @return array ['success' => bool, 'message' => string, 'imported_professor' => int, 'imported_chat' => int]
     */
    public static function backfillLLMUsageFromDatabase()
    {
        $result = ['success' => false, 'message' => '', 'imported_professor' => 0, 'imported_chat' => 0];
        try {
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            $db = \Database::getInstance();
            // Verificar se coluna source existe
            $cols = $db->fetchAll("SHOW COLUMNS FROM logs_uso_llm LIKE 'source'");
            if (empty($cols)) {
                $result['message'] = 'Execute a migração 20260205_000003_logs_uso_llm_add_source.sql para adicionar a coluna source antes de importar.';
                return $result;
            }
            require_once __DIR__ . '/OpenAIService.php';

            // Remover apenas registros de backfill anteriores para permitir reimportar
            $db->query("DELETE FROM logs_uso_llm WHERE source = 'backfill'");

            // 1) Professor AI (chat professor) - professores_ia_mensagens tem tokens_usados
            $rows = $db->fetchAll(
                "SELECT id, conteudo, tokens_usados, created_at FROM professores_ia_mensagens WHERE role = 'assistant'"
            );
            $modelProf = 'gpt-4o-mini';
            foreach ($rows as $r) {
                $tokens = (int)($r['tokens_usados'] ?? 0);
                if ($tokens <= 0 && !empty($r['conteudo'])) {
                    $tokens = (int)ceil(mb_strlen($r['conteudo']) / 4);
                }
                if ($tokens <= 0) {
                    continue;
                }
                $prompt = (int)ceil($tokens / 2);
                $completion = $tokens - $prompt;
                $cost = \App\Services\OpenAIService::calculateCostEstimate($modelProf, $prompt, $completion);
                $createdAt = $r['created_at'] ?? date('Y-m-d H:i:s');
                $db->insert(
                    "INSERT INTO logs_uso_llm (model, prompt_tokens, completion_tokens, total_tokens, cost_usd, usage_type, source, created_at) VALUES (?, ?, ?, ?, ?, 'chat_professor', 'backfill', ?)",
                    [$modelProf, $prompt, $completion, $tokens, $cost, $createdAt]
                );
                $result['imported_professor']++;
            }

            // 2) Chat aluno (Tudinha) - tudinha_mensagens com is_ia = 1, estimar tokens pelo tamanho da mensagem
            $rowsChat = $db->fetchAll(
                "SELECT id, mensagem, created_at FROM tudinha_mensagens WHERE is_ia = 1 AND mensagem IS NOT NULL AND mensagem != ''"
            );
            $modelChat = 'gpt-4o';
            $promptEstimate = 500; // contexto médio por troca
            foreach ($rowsChat as $r) {
                $len = mb_strlen($r['mensagem']);
                $completion = max(1, (int)ceil($len / 4));
                $total = $promptEstimate + $completion;
                $cost = \App\Services\OpenAIService::calculateCostEstimate($modelChat, $promptEstimate, $completion);
                $createdAt = $r['created_at'] ?? date('Y-m-d H:i:s');
                $db->insert(
                    "INSERT INTO logs_uso_llm (model, prompt_tokens, completion_tokens, total_tokens, cost_usd, usage_type, source, created_at) VALUES (?, ?, ?, ?, ?, 'chat', 'backfill', ?)",
                    [$modelChat, $promptEstimate, $completion, $total, $cost, $createdAt]
                );
                $result['imported_chat']++;
            }

            $result['success'] = true;
            $result['message'] = 'Importação concluída: ' . $result['imported_professor'] . ' mensagens do chat professor e ' . $result['imported_chat'] . ' mensagens do chat aluno (Tudinha).';
        } catch (\Exception $e) {
            error_log("Erro no backfill LLM: " . $e->getMessage());
            $result['message'] = 'Erro ao importar: ' . $e->getMessage();
        }
        return $result;
    }

    /**
     * Processa uma data específica: lê do banco (conversas professor, chat aluno) e registra/atualiza custos em logs_uso_llm.
     * Usado pelo cron diário (meia-noite = dia anterior) e pelo Dev Settings (data escolhida).
     * Remove registros computed/backfill daquela data e reinsere para evitar duplicar.
     *
     * @param string $date Data no formato Y-m-d
     * @param string $source 'computed' para cron ou manual (registra origem)
     * @return array ['success' => bool, 'message' => string, 'imported_professor' => int, 'imported_chat' => int]
     */
    public static function processDateForLLMUsage($date, $source = 'computed')
    {
        $result = ['success' => false, 'message' => '', 'imported_professor' => 0, 'imported_chat' => 0];
        try {
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            $db = \Database::getInstance();
            $cols = $db->fetchAll("SHOW COLUMNS FROM logs_uso_llm LIKE 'source'");
            if (empty($cols)) {
                $result['message'] = 'Execute a migração 20260205_000003_logs_uso_llm_add_source.sql antes.';
                return $result;
            }
            require_once __DIR__ . '/OpenAIService.php';

            $dateStart = $date . ' 00:00:00';
            $dateEnd = $date . ' 23:59:59';

            // Remove registros computed/backfill dessa data para poder atualizar
            $db->query(
                "DELETE FROM logs_uso_llm WHERE source IN ('backfill', 'computed') AND created_at >= ? AND created_at <= ?",
                [$dateStart, $dateEnd]
            );

            $modelProf = 'gpt-4o-mini';
            $modelChat = 'gpt-4o';
            $promptEstimate = 500;

            // 1) Chat professor - professores_ia_mensagens na data
            $rows = $db->fetchAll(
                "SELECT id, conteudo, tokens_usados, created_at FROM professores_ia_mensagens 
                 WHERE role = 'assistant' AND created_at >= ? AND created_at <= ?",
                [$dateStart, $dateEnd]
            );
            foreach ($rows as $r) {
                $tokens = (int)($r['tokens_usados'] ?? 0);
                if ($tokens <= 0 && !empty($r['conteudo'])) {
                    $tokens = (int)ceil(mb_strlen($r['conteudo']) / 4);
                }
                if ($tokens <= 0) {
                    continue;
                }
                $prompt = (int)ceil($tokens / 2);
                $completion = $tokens - $prompt;
                $cost = \App\Services\OpenAIService::calculateCostEstimate($modelProf, $prompt, $completion);
                $createdAt = $r['created_at'] ?? $dateStart;
                $db->insert(
                    "INSERT INTO logs_uso_llm (model, prompt_tokens, completion_tokens, total_tokens, cost_usd, usage_type, source, created_at) VALUES (?, ?, ?, ?, ?, 'chat_professor', ?, ?)",
                    [$modelProf, $prompt, $completion, $tokens, $cost, $source, $createdAt]
                );
                $result['imported_professor']++;
            }

            // 2) Chat aluno (Tudinha) - tudinha_mensagens is_ia=1 na data
            $rowsChat = $db->fetchAll(
                "SELECT id, mensagem, created_at FROM tudinha_mensagens 
                 WHERE is_ia = 1 AND mensagem IS NOT NULL AND mensagem != '' AND created_at >= ? AND created_at <= ?",
                [$dateStart, $dateEnd]
            );
            foreach ($rowsChat as $r) {
                $len = mb_strlen($r['mensagem']);
                $completion = max(1, (int)ceil($len / 4));
                $total = $promptEstimate + $completion;
                $cost = \App\Services\OpenAIService::calculateCostEstimate($modelChat, $promptEstimate, $completion);
                $createdAt = $r['created_at'] ?? $dateStart;
                $db->insert(
                    "INSERT INTO logs_uso_llm (model, prompt_tokens, completion_tokens, total_tokens, cost_usd, usage_type, source, created_at) VALUES (?, ?, ?, ?, ?, 'chat', ?, ?)",
                    [$modelChat, $promptEstimate, $completion, $total, $cost, $source, $createdAt]
                );
                $result['imported_chat']++;
            }

            $result['success'] = true;
            $result['message'] = "Data {$date}: {$result['imported_professor']} mensagens do chat professor e {$result['imported_chat']} do chat aluno registradas/atualizadas.";
        } catch (\Exception $e) {
            error_log("Erro ao processar data LLM {$date}: " . $e->getMessage());
            $result['message'] = 'Erro: ' . $e->getMessage();
        }
        return $result;
    }

    /**
     * Normaliza o tipo de usuário para chaves consistentes
     */
    private static function normalizeUserType($userType)
    {
        $type = strtolower(trim((string) $userType));
        switch ($type) {
            case 'aluno':
            case 'student':
                return 'aluno';
            case 'professor':
            case 'teacher':
                return 'professor';
            case 'admin':
            case 'administrador':
            case 'diretor':
            case 'dev':
                return 'admin';
            default:
                return 'guest';
        }
    }

    /**
     * Define se o acesso deve entrar no log detalhado
     */
    private static function shouldLogAccess($uri, $method)
    {
        $uri = strtolower((string) $uri);
        $method = strtoupper((string) $method);

        // Ignorar endpoints de polling/ruído
        $skipPrefixes = [
            '/notifications/api/',
            '/admin/api/alunos-online',
            '/professor/api/alunos-online',
            '/aluno/api/alunos-online',
            '/api/alunos-online'
        ];

        foreach ($skipPrefixes as $prefix) {
            if (strpos($uri, $prefix) === 0) {
                return false;
            }
        }

        // Ignorar assets e health checks
        if (preg_match('#\.(js|css|png|jpg|jpeg|gif|svg|ico|webp|woff|woff2|ttf)$#', $uri)) {
            return false;
        }
        if (strpos($uri, '/health.php') === 0) {
            return false;
        }

        // Logar apenas GET/POST por padrão
        return in_array($method, ['GET', 'POST'], true);
    }
}
