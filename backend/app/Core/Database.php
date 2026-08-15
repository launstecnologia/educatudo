<?php
/**
 * EducaTudo - Classe de Conexão com Banco de Dados
 * Gerencia conexões PDO com configurações de segurança
 */

class Database
{
    private static $instance = null;

    /** Evita tentar conectar de novo na mesma requisição quando já falhou (previne estouro de memória) */
    private static $connectionFailed = false;

    private $pdo;
    private $config;

    /**
     * Retorna configuração DB_* do .env (para uso pelo bootstrap multi-tenant).
     * Carrega apenas DB_*, SEM require app.php.
     */
    public static function getConfigFromEnv(): array
    {
        return self::loadDbConfigFromEnv();
    }

    /**
     * Timeout de handshake MySQL (segundos), limitado entre 1 e 60.
     */
    public static function resolveConnectTimeout(): int
    {
        $raw = getenv('DB_CONNECT_TIMEOUT');
        if ($raw === false || $raw === null || $raw === '') {
            return 15;
        }
        $value = (int) $raw;
        if ($value < 1) {
            return 15;
        }
        if ($value > 60) {
            return 60;
        }
        return $value;
    }

    /**
     * Abre PDO do banco MASTER a partir do .env (DB_*).
     * Usado pelo bootstrap no path /master e por lazy-load quando o fast-path Redis
     * não abre o MASTER na requisição.
     */
    public static function createMasterPdo(): PDO
    {
        $masterConfig = self::getConfigFromEnv();
        $required = ['host', 'name', 'user', 'pass'];
        foreach ($required as $key) {
            if (empty($masterConfig[$key]) && $masterConfig[$key] !== '0') {
                throw new Exception('Multi-tenant ativo mas configuração do banco master incompleta no .env (DB_HOST, DB_NAME, DB_USER, DB_PASS).');
            }
        }
        $port = isset($masterConfig['port']) && $masterConfig['port'] !== '' ? (int) $masterConfig['port'] : 3306;
        $connectTimeout = self::resolveConnectTimeout();
        $dsn = "mysql:host={$masterConfig['host']};port={$port};dbname={$masterConfig['name']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Conexão não-persistente evita "Packets out of order" quando o MySQL fecha conexões idle
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => $connectTimeout,
        ];
        $masterPdo = new PDO($dsn, (string) $masterConfig['user'], (string) $masterConfig['pass'], $options);
        $masterPdo->exec("SET time_zone = '-03:00'");
        $masterPdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        return $masterPdo;
    }

    /**
     * Carrega apenas DB_* do .env, SEM require app.php (evita recursão e Fatal memory exhausted no servidor).
     */
    private static function loadDbConfigFromEnv()
    {
        $envPath = defined('ENV_FILE_PATH') ? ENV_FILE_PATH : (__DIR__ . '/../../.env');
        $paths = [$envPath, __DIR__ . '/../../.env', __DIR__ . '/../../../.env'];
        $env = [];
        foreach ($paths as $p) {
            $resolved = is_file($p) ? $p : (realpath($p) ?: $p);
            if (!is_file($resolved)) {
                continue;
            }
            $lines = @file($resolved, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $name = trim(substr($line, 0, $pos));
                if (strpos($name, 'DB_') !== 0) {
                    continue;
                }
                $value = trim(substr($line, $pos + 1));
                if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                    $value = substr($value, 1, -1);
                }
                $env[$name] = $value;
            }
            if (!empty($env)) {
                break;
            }
        }
        $get = function ($key, $default = '') use ($env) {
            $v = $env[$key] ?? getenv($key);
            return ($v !== false && $v !== null && $v !== '') ? (string) $v : $default;
        };
        $port = $get('DB_PORT', '3306');
        return [
            'host' => $get('DB_HOST'),
            'name' => $get('DB_NAME'),
            'user' => $get('DB_USER'),
            'pass' => $get('DB_PASS'),
            'port' => $port !== '' ? (int) $port : 3306,
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // true: evita SQLSTATE[HY093] com driver MySQL + placeholders nomeados/longos em prepares nativos
                PDO::ATTR_EMULATE_PREPARES => true,
            ],
        ];
    }

    /** Instância injetada quando MULTI_TENANT=true (conexão do tenant atual) */
    private static $currentInstance = null;

    /** Buffer de métricas por request (evita loadMetrics em toda query) */
    private static $metricsBuffer = [
        'db_queries' => 0,
        'db_query_time' => 0.0,
        'slow_queries' => 0,
        'db_errors' => 0,
        'db_query_times' => [],
    ];

    private function __construct($configOrPdo = null)
    {
        if ($configOrPdo instanceof PDO) {
            $this->pdo = $configOrPdo;
            $this->config = ['database' => []];
            return;
        }
        if (is_array($configOrPdo) && isset($configOrPdo['pdo']) && $configOrPdo['pdo'] instanceof PDO) {
            $this->pdo = $configOrPdo['pdo'];
            $this->config = ['database' => $configOrPdo['database'] ?? []];
            return;
        }
        $this->config = ['database' => $configOrPdo ?? self::loadDbConfigFromEnv()];
        $this->connect();
    }

    /**
     * Cria uma instância de Database a partir de um PDO existente (multi-tenant).
     * Usado pelo DatabaseManager para conexões por escola.
     */
    public static function createFromPdo(PDO $pdo, ?array $databaseConfig = null): self
    {
        return new self([
            'pdo' => $pdo,
            'database' => $databaseConfig ?? [],
        ]);
    }

    /**
     * Define a instância atual retornada por getInstance() (multi-tenant).
     * Quando não null, getInstance() retorna esta instância em vez do singleton .env.
     * Chamado pelo bootstrap quando MULTI_TENANT=true.
     */
    public static function setCurrentInstance(?self $instance): void
    {
        self::$currentInstance = $instance;
    }

    /**
     * Singleton - retorna instância única.
     * Se MULTI_TENANT ativo e conexão do tenant foi registrada, retorna essa; senão usa .env.
     * Se a conexão já falhou nesta requisição, lança imediatamente (evita loop e Fatal memory exhausted).
     */
    public static function getInstance()
    {
        if (self::$currentInstance !== null) {
            return self::$currentInstance;
        }
        if (self::$connectionFailed) {
            throw new Exception(
                'ERRO NO BANCO DE DADOS: conexão indisponível ou configuração incorreta. No servidor, verifique o arquivo .env e preencha corretamente DB_HOST, DB_NAME, DB_USER e DB_PASS (e DB_PORT se necessário).'
            );
        }
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Conecta ao banco de dados
     */
    private function connect()
    {
        try {
            if (!isset($this->config['database'])) {
                self::$connectionFailed = true;
                throw new Exception("ERRO NO BANCO DE DADOS: configuração não encontrada. Verifique no servidor o arquivo .env e as variáveis DB_*.");
            }

            $dbConfig = $this->config['database'];
            $required = ['host', 'name', 'user', 'pass'];
            foreach ($required as $key) {
                if (empty($dbConfig[$key]) && $dbConfig[$key] !== '0') {
                    self::$connectionFailed = true;
                    throw new Exception(
                        "ERRO NO BANCO DE DADOS: falta a variável DB_" . strtoupper($key) . " no arquivo .env do servidor. Preencha todas: DB_HOST, DB_NAME, DB_USER, DB_PASS."
                    );
                }
            }
            $port = isset($dbConfig['port']) && $dbConfig['port'] !== '' ? (int) $dbConfig['port'] : 3306;
            $dsn = "mysql:host={$dbConfig['host']};port={$port};dbname={$dbConfig['name']};charset=utf8mb4";
            
            $options = isset($dbConfig['options']) ? $dbConfig['options'] : [];
            // Conexão não-persistente evita "Packets out of order" quando o MySQL fecha conexões idle
            $options[PDO::ATTR_PERSISTENT] = false;
            // Garantir opções UTF-8
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
            // Garantir que retorna arrays associativos por padrão
            $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
            $this->pdo = new PDO($dsn, (string) $dbConfig['user'], (string) $dbConfig['pass'], $options);
            // Reforço: prepares emulados evitam HY093 com MySQL em vários ambientes
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            
            // Configurar timezone do MySQL para horário de Brasília
            $this->pdo->exec("SET time_zone = '-03:00'");
            // Garantir charset UTF-8
            $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            // Garantir que retorna arrays associativos
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            self::$connectionFailed = true;
            $pdoMessage = $e->getMessage();
            error_log("Erro de conexão com banco de dados: " . $pdoMessage . " [host=" . ($dbConfig['host'] ?? '') . "]");
            try {
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/Logger.php';
                }
                Logger::databaseError(
                    "Erro de conexão com banco de dados: " . $pdoMessage,
                    [
                        'exception' => $e,
                        'host' => $dbConfig['host'] ?? 'N/A',
                        'database' => $dbConfig['name'] ?? 'N/A'
                    ]
                );
            } catch (Throwable $logEx) {
                // Não relançar: evitar qualquer recursão ao tratar erro de DB
            }
            throw new Exception(
                "ERRO NO BANCO DE DADOS: não foi possível conectar ao MySQL no servidor. Verifique no .env: DB_HOST (servidor correto?), DB_NAME, DB_USER, DB_PASS e DB_PORT. No localhost pode funcionar e no servidor falhar se as credenciais ou o host estiverem errados.",
                0,
                $e
            );
        } catch (Exception $e) {
            self::$connectionFailed = true;
            throw $e;
        }
    }

    private function canReconnect(): bool
    {
        $dbConfig = $this->config['database'] ?? [];

        return !empty($dbConfig['host'])
            && !empty($dbConfig['name'])
            && array_key_exists('user', $dbConfig)
            && array_key_exists('pass', $dbConfig);
    }

    private function isGoneAwayException(PDOException $e): bool
    {
        $message = strtolower($e->getMessage());
        $sqlState = (string) $e->getCode();
        $errorInfo = $e->errorInfo ?? [];
        $mysqlCode = isset($errorInfo[1]) ? (int) $errorInfo[1] : 0;

        return in_array($mysqlCode, [2006, 2013], true)
            || in_array($sqlState, ['2006', '2013'], true)
            || strpos($message, 'server has gone away') !== false
            || strpos($message, 'lost connection') !== false;
    }

    private function reconnectIfPossible(): bool
    {
        if (!$this->canReconnect()) {
            return false;
        }

        try {
            $this->connect();
            return true;
        } catch (Throwable $e) {
            error_log('Falha ao reconectar banco: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retorna instância PDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }
    
    /**
     * Com PDO MySQL e ATTR_EMULATE_PREPARES false, passar chaves a mais que placeholders nomeados
     * em execute() provoca SQLSTATE[HY093]. Mantém só os binds presentes no SQL.
     * Não altera arrays posicionais (?) nem SQL que mistura ? com :nome.
     */
    private function normalizeNamedParamsForSql($sql, array $params)
    {
        if ($params === []) {
            return $params;
        }
        if ($this->isPositionalParamArray($params)) {
            return $params;
        }
        if (strpos($sql, '?') !== false) {
            return $params;
        }
        if (!preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $m)) {
            return $params;
        }
        $filtered = [];
        foreach ($m[1] as $name) {
            if (!array_key_exists($name, $params)) {
                throw new Exception('Parâmetro ausente para a consulta: :' . $name);
            }
            $filtered[$name] = $params[$name];
        }
        return $filtered;
    }

    /**
     * @param array $params
     */
    private function isPositionalParamArray($params)
    {
        if (function_exists('array_is_list')) {
            return array_is_list($params);
        }
        $i = 0;
        foreach ($params as $k => $_) {
            if ($k !== $i++) {
                return false;
            }
        }
        return true;
    }

    /**
     * Mensagem legível para admin (JSON) quando PDO falha — HY093 ganha diagnóstico sem expor valores.
     */
    private function buildQueryExceptionMessage(PDOException $e, $sql, array $params)
    {
        $pdoMsg = $e->getMessage();
        if (stripos($pdoMsg, 'HY093') === false) {
            return 'Erro na execução da consulta: ' . $pdoMsg;
        }
        $sqlStr = (string) $sql;
        $qMarks = substr_count($sqlStr, '?');
        $namedOcc = 0;
        if (preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $sqlStr, $m)) {
            $namedOcc = count($m[1]);
        }
        $paramCount = count($params);
        $keys = array_keys($params);
        $keyList = count($keys) <= 16 ? implode(', ', $keys) : (string) count($keys) . ' chaves';

        return 'Erro na execução da consulta: ' . $pdoMsg
            . ' [HY093 — diagnóstico: ' . $qMarks . ' placeholder(s) ?, '
            . $namedOcc . ' nome(s) de parâmetro no SQL, '
            . $paramCount . ' valor(es) enviado(s). '
            . 'Causas frequentes: repetir o mesmo nome no SQL (ex.: :ra em dois lugares — use :ra1 e :ra2), '
            . 'ou quantidade de valores diferente dos placeholders. '
            . 'Chaves recebidas: ' . $keyList . ']';
    }

    /**
     * Faz bind de cada parâmetro com o tipo PDO correto (INT/STR/BOOL/NULL) em
     * vez de deixar $stmt->execute($params) tratar tudo como string.
     *
     * Importante para ATTR_EMULATE_PREPARES=true: nesse modo o PDO substitui
     * os valores no SQL client-side usando o tipo declarado do bind — com
     * execute($params) (atalho de array), todo valor vira PDO::PARAM_STR
     * independente do tipo PHP original, então um inteiro em LIMIT/OFFSET
     * sai como "LIMIT '5'" (com aspas) e o MySQL rejeita com erro de sintaxe.
     * Fazendo bindValue() explícito por parâmetro, o tipo correto é preservado.
     */
    private function bindParamsComTipo(PDOStatement $stmt, array $params)
    {
        $posicional = $this->isPositionalParamArray($params);
        $indice = 1;
        foreach ($params as $chave => $valor) {
            $tipo = $this->resolvePdoParamType($valor);
            if ($posicional) {
                $stmt->bindValue($indice, $valor, $tipo);
                $indice++;
            } else {
                $nome = (is_string($chave) && $chave !== '' && $chave[0] === ':') ? $chave : ':' . $chave;
                $stmt->bindValue($nome, $valor, $tipo);
            }
        }
    }

    /**
     * @param mixed $valor
     * @return int Constante PDO::PARAM_*
     */
    private function resolvePdoParamType($valor)
    {
        if ($valor === null) {
            return PDO::PARAM_NULL;
        }
        if (is_bool($valor)) {
            return PDO::PARAM_BOOL;
        }
        if (is_int($valor)) {
            return PDO::PARAM_INT;
        }
        return PDO::PARAM_STR;
    }

    /**
     * Executa query preparada
     */
    public function query($sql, $params = [])
    {
        $startTime = microtime(true);
        try {
            $params = $this->normalizeNamedParamsForSql($sql, $params);
            // Logar queries de modificação (UPDATE, INSERT, DELETE)
            $this->logModificationQuery($sql, $params);

            $stmt = $this->pdo->prepare($sql);
            $this->bindParamsComTipo($stmt, $params);
            $stmt->execute();
            $this->recordMetrics($startTime, null, $sql, $params, $stmt);
            return $stmt;
        } catch (PDOException $e) {
            if ($this->isGoneAwayException($e) && !$this->inTransaction() && $this->reconnectIfPossible()) {
                try {
                    $stmt = $this->pdo->prepare($sql);
                    $this->bindParamsComTipo($stmt, $params);
                    $stmt->execute();
                    $this->recordMetrics($startTime, null, $sql, $params, $stmt);
                    return $stmt;
                } catch (PDOException $retryException) {
                    $e = $retryException;
                }
            }
            $this->recordMetrics($startTime, $e->getMessage(), $sql, $params);
            // Logar erro no sistema de logs
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/Logger.php';
            }
            Logger::databaseError(
                "Erro na execução da query: " . $e->getMessage(),
                [
                    'exception' => $e,
                    'sql' => substr($sql, 0, 500), // Limitar tamanho do SQL no log
                    'params_count' => count($params)
                ]
            );
            
            $errorMsg = $e->getMessage();
            error_log("Erro na query: " . $errorMsg . " SQL: " . $sql . " Params: " . json_encode($params));
            throw new Exception($this->buildQueryExceptionMessage($e, $sql, $params));
        }
    }
    
    /**
     * Busca um registro
     */
    public function fetch($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca todos os registros
     */
    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insere registro e retorna ID
     */
    public function insert($sql, $params = [])
    {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }
    
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Atualiza registro
     */
    public function update($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Deleta registro
     */
    public function delete($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Inicia transação
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Confirma transação
     */
    public function commit()
    {
        return $this->pdo->commit();
    }
    
    /**
     * Desfaz transação
     */
    public function rollback()
    {
        return $this->pdo->rollback();
    }
    
    /**
     * Verifica se está em transação
     */
    public function inTransaction()
    {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Executa script SQL
     */
    public function executeScript($sqlFile)
    {
        try {
            $sql = file_get_contents($sqlFile);
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao executar script SQL: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se tabela existe
     */
    public function tableExists($tableName)
    {
        try {
            $sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
            $stmt = $this->query($sql, [$tableName]);
            $result = $stmt->fetch();
            return $result['COUNT(*)'] > 0;
        } catch (Exception $e) {
            // Fallback: tentar executar uma query simples na tabela
            try {
                $this->query("SELECT 1 FROM `{$tableName}` LIMIT 1");
                return true;
            } catch (Exception $e2) {
                return false;
            }
        }
    }
    
    /**
     * Retorna informações da conexão (sem dados sensíveis)
     */
    public function getConnectionInfo()
    {
        return [
            'host' => $this->config['database']['host'],
            'port' => $this->config['database']['port'],
            'database' => $this->config['database']['name'],
            'charset' => $this->config['database']['charset'],
            'status' => $this->pdo ? 'connected' : 'disconnected'
        ];
    }

    /**
     * Acumula métricas em buffer por request; flush no shutdown evita I/O em toda query.
     */
    private function recordMetrics($startTime, $error = null, $sql = null, $params = [], ?PDOStatement $stmt = null)
    {
        $queryTime = microtime(true) - $startTime;
        self::$metricsBuffer['db_queries']++;
        self::$metricsBuffer['db_query_time'] += $queryTime;

        $slowThresholdSec = 0.5;
        if (!class_exists('PerfLogger', false)) {
            require_once __DIR__ . '/PerfLogger.php';
        }
        $slowThresholdSec = PerfLogger::queryThresholdSec();

        if ($queryTime > $slowThresholdSec) {
            self::$metricsBuffer['slow_queries']++;
        }

        // Performance Profiler (app/Performance/*): coleta TODA query (não só as
        // lentas) para detectar N+1 e montar o relatório por página. 100% opt-in
        // via APP_DEBUG=true; \App\Performance\Profiler::isEnabled() é a única
        // checagem feita quando desligado (overhead desprezível).
        if ($sql !== null && is_string($sql) && class_exists(\App\Performance\Profiler::class) && \App\Performance\Profiler::isEnabled()) {
            $rowCount = null;
            if ($stmt !== null) {
                try {
                    $rowCount = $stmt->rowCount();
                } catch (\Throwable $e) {
                    $rowCount = null;
                }
            }
            \App\Performance\QueryCollector::record($sql, is_array($params) ? $params : [], $queryTime, $rowCount, $error);
        }

        if ($error !== null) {
            self::$metricsBuffer['db_errors']++;
            return;
        }
        if ($sql !== null && is_string($sql) && PerfLogger::isEnabled()) {
            PerfLogger::logSlowQuery($queryTime, $sql, is_array($params) ? $params : []);
        }
        self::$metricsBuffer['db_query_times'][] = $queryTime;
        if (count(self::$metricsBuffer['db_query_times']) > 1000) {
            array_shift(self::$metricsBuffer['db_query_times']);
        }
    }

    /**
     * Buffer de métricas do request (para PerfLogger no shutdown).
     *
     * @return array{db_queries:int,db_query_time:float,slow_queries:int,db_errors:int,db_query_times:array}
     */
    public static function getMetricsBuffer(): array
    {
        return self::$metricsBuffer;
    }

    /**
     * Envia métricas acumuladas ao MetricsService (uma única chamada por request).
     * Chamado no shutdown em index.php.
     */
    public static function flushMetricsToService(): void
    {
        if (self::$metricsBuffer['db_queries'] === 0) {
            return;
        }
        try {
            \App\Services\MetricsService::recordDatabaseMetricsAggregated(self::$metricsBuffer);
        } catch (Throwable $e) {
            error_log("Erro ao enviar métricas DB: " . $e->getMessage());
        }
    }
    
    /**
     * Registra queries de modificação (UPDATE, INSERT, DELETE) em arquivo de log.
     * Só grava se config/app.php logs.log_sql_modifications estiver true (ou LOG_SQL_MODIFICATIONS=true no .env).
     */
    private function logModificationQuery($sql, $params = [])
    {
        static $enabled = null;
        if ($enabled === null) {
            $config = @include __DIR__ . '/../../config/app.php';
            $enabled = !empty($config['logs']['log_sql_modifications']);
        }
        if (!$enabled) {
            return;
        }
        $sqlTrimmed = trim(strtoupper($sql));
        $isModification = preg_match('/^\s*(UPDATE|INSERT|DELETE|ALTER|CREATE|DROP|TRUNCATE)/i', $sqlTrimmed);
        if ($isModification) {
            try {
                $logDir = __DIR__ . '/../../storage/logs';
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0755, true);
                }
                
                $date = date('Y-m-d');
                $logFile = $logDir . '/sql_modifications_' . $date . '.log';
                
                $timestamp = date('Y-m-d H:i:s');
                $queryType = strtoupper(trim(explode(' ', $sqlTrimmed)[0]));
                
                // Substituir parâmetros no SQL para visualização (limitado a 1000 caracteres)
                $sqlWithParams = $this->interpolateQuery($sql, $params);
                $sqlDisplay = strlen($sqlWithParams) > 1000 ? substr($sqlWithParams, 0, 1000) . '...' : $sqlWithParams;
                
                $logEntry = "[{$timestamp}] [{$queryType}]\n";
                $logEntry .= "SQL: {$sqlDisplay}\n";
                if (!empty($params)) {
                    $paramsDisplay = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (strlen($paramsDisplay) > 500) {
                        $paramsDisplay = substr($paramsDisplay, 0, 500) . '...';
                    }
                    $logEntry .= "Params: {$paramsDisplay}\n";
                }
                
                // Capturar informações da requisição se disponível
                if (isset($_SERVER['REQUEST_URI'])) {
                    $logEntry .= "URI: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']}\n";
                }
                if (isset($_SESSION['user_id'])) {
                    $logEntry .= "User ID: {$_SESSION['user_id']}\n";
                }
                if (isset($_SESSION['user_type'])) {
                    $logEntry .= "User Type: {$_SESSION['user_type']}\n";
                }
                
                $logEntry .= str_repeat('-', 80) . "\n\n";
                
                file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            } catch (Exception $e) {
                // Silenciosamente falhar no logging para não quebrar a aplicação
                error_log("Erro ao registrar query de modificação: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Interpola parâmetros na query SQL para visualização (apenas para logging)
     */
    private function interpolateQuery($sql, $params)
    {
        if (empty($params)) {
            return $sql;
        }
        
        $keys = [];
        $values = [];
        
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $keys[] = '/:' . preg_quote($key, '/') . '/';
            } else {
                $keys[] = '/[?]/';
            }
            
            if (is_string($value)) {
                $values[] = "'" . addslashes($value) . "'";
            } elseif (is_null($value)) {
                $values[] = 'NULL';
            } elseif (is_bool($value)) {
                $values[] = $value ? '1' : '0';
            } else {
                $values[] = $value;
            }
        }
        
        return preg_replace($keys, $values, $sql, 1);
    }
}
