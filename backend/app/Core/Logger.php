<?php

namespace App\Core;
/**
 * EducaTudo - Sistema de Logging
 * Registra problemas e eventos em arquivos de log organizados
 */


use App\Services\EvolutionApiService;
class Logger
{
    private static $logDir;
    private static $initialized = false;
    private static $sendingWhatsApp = false;
    
    /**
     * Inicializa o sistema de logs
     */
    private static function init()
    {
        if (self::$initialized) {
            return;
        }
        
        self::$logDir = __DIR__ . '/../../storage/logs';
        
        // Criar diretório se não existir
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
        
        self::$initialized = true;
    }
    
    /**
     * Registra um erro
     * 
     * @param string $message Mensagem do erro
     * @param array $context Contexto adicional (ex: exception, dados)
     * @param string $category Categoria do erro (openai, database, auth, etc)
     */
    public static function error($message, $context = [], $category = 'general')
    {
        self::init();
        self::write('error', $message, $context, $category);
    }
    
    /**
     * Registra um aviso
     * 
     * @param string $message Mensagem do aviso
     * @param array $context Contexto adicional
     * @param string $category Categoria do aviso
     */
    public static function warning($message, $context = [], $category = 'general')
    {
        self::init();
        self::write('warning', $message, $context, $category);
    }
    
    /**
     * Registra uma informação
     * 
     * @param string $message Mensagem informativa
     * @param array $context Contexto adicional
     * @param string $category Categoria da informação
     */
    public static function info($message, $context = [], $category = 'general')
    {
        self::init();
        self::write('info', $message, $context, $category);
    }
    
    /**
     * Registra debug
     * 
     * @param string $message Mensagem de debug
     * @param array $context Contexto adicional
     * @param string $category Categoria do debug
     */
    public static function debug($message, $context = [], $category = 'general')
    {
        // Evita ruído no log de créditos de apps externos em produção.
        if ($category === 'external_apps_credits') {
            return;
        }

        if (defined('DEBUG') && DEBUG) {
            self::init();
            self::write('debug', $message, $context, $category);
        }
    }
    
    /**
     * Escreve no arquivo de log
     * 
     * @param string $level Nível do log (error, warning, info, debug)
     * @param string $message Mensagem
     * @param array $context Contexto
     * @param string $category Categoria
     */
    private static function write($level, $message, $context, $category)
    {
        try {
            $message = self::mascararSegredosNoTexto((string) $message);
            if (is_array($context)) {
                $context = self::mascararContexto($context);
            }
            $timestamp = date('Y-m-d H:i:s');
            
            if (!is_dir(self::$logDir)) {
                @mkdir(self::$logDir, 0755, true);
            }
            
            if (!is_writable(self::$logDir)) {
                @chmod(self::$logDir, 0755);
            }
            
            $logFile = self::caminhoArquivoUnico();
            self::rotacionarSePreciso($logFile);
            
            $contextData = '';
            $maxMessageBytes = 2048;
            $maxTraceBytes = 8192;
            $maxContextBytes = 8192;
            if (!empty($context)) {
                if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
                    $e = $context['exception'];
                    $contextData .= "\nException: " . get_class($e);
                    $msg = self::mascararSegredosNoTexto($e->getMessage());
                    $jaNaMensagem = $msg !== '' && str_contains((string) $message, $msg);
                    if (!$jaNaMensagem) {
                        $contextData .= "\nMessage: " . (strlen($msg) > $maxMessageBytes ? substr($msg, 0, $maxMessageBytes) . '...[truncated]' : $msg);
                    }
                    $contextData .= "\nFile: " . $e->getFile();
                    $contextData .= "\nLine: " . $e->getLine();
                    $trace = self::mascararSegredosNoTexto($e->getTraceAsString());
                    $contextData .= "\nStack Trace:\n" . (strlen($trace) > $maxTraceBytes ? substr($trace, 0, $maxTraceBytes) . "\n...[truncated]" : $trace);
                }
                $otherContext = array_filter($context, function($key) {
                    return $key !== 'exception';
                }, ARRAY_FILTER_USE_KEY);
                if (!empty($otherContext)) {
                    $otherContext = self::mascararContexto($otherContext);
                    $encoded = json_encode($otherContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    $contextData .= "\nContext: " . (strlen($encoded) > $maxContextBytes ? substr($encoded, 0, $maxContextBytes) . '...[truncated]' : $encoded);
                }
            }
            
            $requestInfo = '';
            if (isset($_SERVER['REQUEST_URI'])) {
                $requestInfo = "\nRequest URI: " . self::mascararUri((string) ($_SERVER['REQUEST_URI'] ?? ''));
                $requestInfo .= "\nRequest Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A');
                $requestInfo .= "\nIP Address: " . self::getRealIpAddress();
                if (isset($_SESSION['user_id'])) {
                    $requestInfo .= "\nUser ID: " . $_SESSION['user_id'];
                    $requestInfo .= "\nUser Type: " . ($_SESSION['user_type'] ?? 'N/A');
                }
            }
            
            $logEntry = strtoupper($level) . " [" . $timestamp . "] [{$category}]\n";
            $logEntry .= "Message: " . (strlen($message) > 8192 ? substr($message, 0, 8192) . '...[truncated]' : $message) . "\n";
            $logEntry .= $contextData;
            $logEntry .= $requestInfo;
            $logEntry .= "\n" . str_repeat('-', 80) . "\n\n";
            
            $written = @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            if ($written === false) {
                error_log("FALHA ao escrever em {$logFile}. Verifique permissões.");
            }

            if ($category === 'rotas_nao_encontrada') {
                return;
            }
            
            // Enviar notificação de log para o grupo WhatsApp (Evolution API) em error, warning e info
            // NUNCA enviar quando:
            // - categoria for 'evolution' (evita loop direto),
            // - categoria for 'database' (EvolutionApiService usa LayoutHelper/DB e pode gerar recursão),
            // - mensagem indicar problema de conexão/banco/config_layout,
            // - já estivermos no meio de um envio de WhatsApp (trava anti-recursão).
            $msgLower = strtolower($message);
            $erroConexaoBanco = (strpos($msgLower, 'conexão indisponível') !== false || strpos($msgLower, 'banco de dados') !== false || strpos($msgLower, 'config_layout') !== false);
            // Não notificar WhatsApp para erros de cota/credits (ElevenLabs, APIs externas) — evita spam
            $erroQuotaOuCredits = (strpos($msgLower, 'exceeds your quota') !== false || strpos($msgLower, 'quota of') !== false
                || strpos($msgLower, 'credits remaining') !== false || strpos($msgLower, 'credits are required') !== false
                || strpos($msgLower, 'insufficient_quota') !== false || strpos($msgLower, 'quota exceeded') !== false);
            // Não notificar para erros de textoParaVoz (TTS) na categoria chat — evita loop quando quota acaba
            $erroTtsChat = ($category === 'chat' && strpos($msgLower, 'textoparavoz') !== false);
            // Rate limit: no máximo 1 notificação por categoria a cada 5 minutos (evita loop)
            $rateLimitFile = self::$logDir . '/.last_whatsapp_notify.json';
            $now = time();
            $cooldownSeconds = 300; // 5 min
            $podeEnviarPorRateLimit = true;
            if (file_exists($rateLimitFile) && is_readable($rateLimitFile)) {
                $json = @file_get_contents($rateLimitFile);
                if ($json !== false) {
                    $last = @json_decode($json, true);
                    if (is_array($last) && isset($last[$category]) && (int) $last[$category] > 0) {
                        if (($now - (int) $last[$category]) < $cooldownSeconds) {
                            $podeEnviarPorRateLimit = false;
                        }
                    }
                }
            }
            // Não notificar WhatsApp para rotas não encontradas (evita poluir grupo de log)
            $ehRotaNaoEncontrada = ($category === 'rotas_nao_encontrada' || strpos($message, 'Rota não encontrada') === 0);
            $enviarWhatsApp = ($level === 'error' || $level === 'warning' || $level === 'info')
                && $category !== 'evolution'
                && $category !== 'database'
                && $category !== 'rotas_nao_encontrada'
                && $category !== 'auditoria'
                && !$erroConexaoBanco
                && !$erroQuotaOuCredits
                && !$erroTtsChat
                && !$ehRotaNaoEncontrada
                && $podeEnviarPorRateLimit
                && !self::$sendingWhatsApp;
            if ($enviarWhatsApp) {
                try {
                    self::$sendingWhatsApp = true;
                    if (!class_exists('EvolutionApiService')) {
                        $servicePath = __DIR__ . '/../Services/EvolutionApiService.php';
                        if (file_exists($servicePath)) {
                            require_once $servicePath;
                        }
                    }
                    if (class_exists('EvolutionApiService')) {
                        $dataHora = date('d/m/Y \à\s H:i');
                        $icone = $level === 'error' ? '❌' : ($level === 'warning' ? '⚠️' : 'ℹ️');
                        $tipo = $level === 'error' ? 'Erro' : ($level === 'warning' ? 'Aviso' : 'Informação');
                        $resumoContexto = '';
                        if (!empty($context) && is_array($context)) {
                            $partes = [];
                            foreach (array_slice($context, 0, 5) as $k => $v) {
                                if ($k === 'exception' && is_string($v) && $v !== '') {
                                    $partes[] = 'Exceção: ' . (strlen($v) <= 120 ? $v : substr($v, 0, 117) . '...');
                                    continue;
                                }
                                if ($k !== 'trace' && !is_object($v) && $v !== null && $v !== '') {
                                    $partes[] = (is_string($v) && strlen($v) <= 80) ? $v : (is_scalar($v) ? (string)$v : json_encode($v));
                                }
                            }
                            if (!empty($partes)) {
                                $resumoContexto = "\n📋 " . implode(' · ', array_slice($partes, 0, 3));
                            }
                        }
                        $msgTruncada = strlen($message) > 400 ? substr($message, 0, 397) . '...' : $message;
                        $whatsappMsg = "🔔 *EducaTudo – Notificação de log*\n"
                            . "📅 " . $dataHora . "\n"
                            . $icone . " *" . $tipo . "* · " . $category . "\n\n"
                            . $msgTruncada
                            . $resumoContexto;
                        EvolutionApiService::sendTextToLogGroup($whatsappMsg, 3);
                        // Atualizar rate limit por categoria (máx 1 a cada 5 min)
                        $rateLimitFile = self::$logDir . '/.last_whatsapp_notify.json';
                        $last = [];
                        if (file_exists($rateLimitFile) && is_readable($rateLimitFile)) {
                            $json = @file_get_contents($rateLimitFile);
                            if ($json !== false) {
                                $dec = @json_decode($json, true);
                                if (is_array($dec)) {
                                    $last = $dec;
                                }
                            }
                        }
                        $last[$category] = time();
                        @file_put_contents($rateLimitFile, json_encode($last), LOCK_EX);
                    }
                } catch (\Throwable $e) {
                    // Não falhar o log por causa do WhatsApp
                } finally {
                    self::$sendingWhatsApp = false;
                }
            }
            
        } catch (\Exception $e) {
            // Se falhar ao escrever log, usar error_log padrão com mais detalhes
            error_log("ERRO ao escrever log: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            error_log("Mensagem original: {$message}");
            error_log("Diretório de log: " . (self::$logDir ?? 'N/A'));
            error_log("Diretório existe: " . (is_dir(self::$logDir ?? '') ? 'SIM' : 'NÃO'));
            error_log("Diretório gravável: " . (is_writable(self::$logDir ?? '') ? 'SIM' : 'NÃO'));
        } catch (\Throwable $e) {
            error_log("ERRO FATAL ao escrever log: " . $e->getMessage());
            error_log("Mensagem original: {$message}");
        }
    }
    
    /**
     * Log de erro da OpenAI
     */
    public static function openaiError($message, $context = [])
    {
        self::error($message, $context, 'openai');
    }
    
    /**
     * Log de erro do banco de dados
     */
    public static function databaseError($message, $context = [])
    {
        self::error($message, $context, 'database');
    }
    
    /**
     * Log de erro de autenticação
     */
    public static function authError($message, $context = [])
    {
        self::error($message, $context, 'auth');
    }
    
    /**
     * Log de erro de API
     */
    public static function apiError($message, $context = [])
    {
        self::error($message, $context, 'api');
    }
    
    /**
     * Limpa logs antigos (mais de X dias)
     * 
     * @param int $days Número de dias para manter (padrão: 30)
     */
    public static function cleanOldLogs($days = 30)
    {
        self::init();
        
        $files = glob(self::$logDir . '/*.log');
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (file_exists($file) && filemtime($file) < $cutoffTime) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Retorna o caminho do diretório de logs
     */
    public static function getLogDir()
    {
        self::init();
        return self::$logDir;
    }
    
    /**
     * Registra ação na trilha de auditoria (Audit Trail)
     * 
     * @param string $action Ação realizada (ex: 'LOGIN', 'VIEW_EXAM', 'UPDATE_GRADE')
     * @param string $resourceAccessed Recurso acessado (URL ou nome da entidade)
     * @param array $requestPayload Dados da requisição (campos sensíveis serão ocultados)
     * @param int|null $userId ID do usuário (null se não autenticado)
     * @param string|null $userRole Papel do usuário (ex: 'admin', 'teacher', 'student')
     * @param string|null $ipAddress Endereço IP do usuário
     */
    public static function logAudit($action, $resourceAccessed = null, $requestPayload = [], $userId = null, $userRole = null, $ipAddress = null)
    {
        try {
            // Carregar Database se necessário
            if (!class_exists('Database')) {
            }
            
            $db = Database::getInstance();
            
            // Obter informações do usuário se não fornecidas
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }
            
            if ($userRole === null && isset($_SESSION['user_type'])) {
                $userRole = $_SESSION['user_type'];
            }
            
            // Obter IP real do usuário
            if ($ipAddress === null) {
                $ipAddress = self::getRealIpAddress();
            }
            
            // Obter resource se não fornecido
            if ($resourceAccessed === null && isset($_SERVER['REQUEST_URI'])) {
                $resourceAccessed = $_SERVER['REQUEST_URI'];
            }
            if (is_string($resourceAccessed)) {
                $resourceAccessed = self::mascararUri($resourceAccessed);
            }
            
            // Filtrar campos sensíveis do payload
            $filteredPayload = self::filterSensitiveData($requestPayload);
            
            // Converter payload para JSON
            $payloadJson = !empty($filteredPayload) ? json_encode($filteredPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            
            // Inserir no banco de dados
            $sql = "INSERT INTO logs_auditoria (user_id, user_role, ip_address, action, resource_accessed, request_payload, created_at)
                    VALUES (:user_id, :user_role, :ip_address, :action, :resource_accessed, :request_payload, NOW())";
            
            $params = [
                'user_id' => $userId,
                'user_role' => $userRole,
                'ip_address' => $ipAddress,
                'action' => $action,
                'resource_accessed' => $resourceAccessed,
                'request_payload' => $payloadJson
            ];
            
            $db->insert($sql, $params);
            
        } catch (\Exception $e) {
            // Logar erro mas não interromper a experiência do usuário
            error_log("Erro ao registrar audit log: " . $e->getMessage());
            // Tentar logar no sistema de logs também
            try {
                self::error(
                    "Falha ao registrar audit log: " . $e->getMessage(),
                    [
                        'exception' => $e,
                        'action' => $action,
                        'resource' => $resourceAccessed
                    ],
                    'audit'
                );
            } catch (\Exception $logError) {
                // Silenciosamente falhar se não conseguir logar
            }
        } catch (\Throwable $e) {
            // Logar erro fatal mas não interromper
            error_log("Erro fatal ao registrar audit log: " . $e->getMessage());
        }
    }
    
    /**
     * Arquivo único da aplicação. A categoria continua no texto da entrada.
     */
    private static function caminhoArquivoUnico(): string
    {
        return self::$logDir . '/app.log';
    }

    /**
     * Quando o arquivo passa de 32 MB, guarda a geração anterior em app.1.log.
     */
    private static function rotacionarSePreciso(string $arquivo): void
    {
        $limite = 32 * 1024 * 1024;
        clearstatcache(true, $arquivo);
        if (!is_file($arquivo) || (int) filesize($arquivo) < $limite) {
            return;
        }
        $backup = self::$logDir . '/app.1.log';
        @unlink($backup);
        @rename($arquivo, $backup);
    }

    /**
     * @param array<string|int, mixed> $contexto
     * @return array<string|int, mixed>
     */
    private static function mascararContexto(array $contexto): array
    {
        $sensiveis = ['token', '_token', 'access_token', 'refresh_token', 'password', 'senha', 'senha_hash', 'password_hash', 'csrf', 'csrf_token', 'authorization', 'api_key', 'secret', 'secret_key'];
        foreach ($contexto as $chave => $valor) {
            $nome = strtolower((string) $chave);
            if (in_array($nome, $sensiveis, true)) {
                $contexto[$chave] = '***';
                continue;
            }
            if (is_string($valor) && in_array($nome, ['request_uri', 'uri', 'url', 'resource', 'resource_accessed'], true)) {
                $contexto[$chave] = self::mascararUri($valor);
                continue;
            }
            if (is_string($valor)) {
                $contexto[$chave] = self::mascararSegredosNoTexto($valor);
            }
            if (is_array($valor)) {
                $contexto[$chave] = self::mascararContexto($valor);
            }
        }
        return $contexto;
    }

    private static function mascararUri(string $uri): string
    {
        $uri = str_replace(["\r", "\n", "\t"], '', $uri);
        if ($uri === '') {
            return '/';
        }
        $partes = explode('?', $uri, 2);
        $path = $partes[0];
        if (!isset($partes[1]) || $partes[1] === '') {
            return strlen($path) > 500 ? substr($path, 0, 500) . '...[truncated]' : $path;
        }
        $query = [];
        parse_str($partes[1], $query);
        $sensiveis = ['token', '_token', 'access_token', 'refresh_token', 'password', 'senha', 'senha_hash', 'password_hash', 'csrf', 'csrf_token', 'authorization', 'api_key', 'secret', 'secret_key'];
        $pares = [];
        foreach ($query as $chave => $valor) {
            $nome = strtolower((string) $chave);
            $valorLog = in_array($nome, $sensiveis, true) ? '***' : rawurlencode(is_scalar($valor) ? (string) $valor : '');
            $pares[] = rawurlencode((string) $chave) . '=' . $valorLog;
        }
        $montada = $path . '?' . implode('&', $pares);
        return strlen($montada) > 500 ? substr($montada, 0, 500) . '...[truncated]' : $montada;
    }

    private static function mascararSegredosNoTexto(string $texto): string
    {
        return (string) preg_replace(
            '/(^|[&?\s])(access_token|refresh_token|password_hash|senha_hash|csrf_token|api_key|secret_key|_token|token|password|senha|csrf|authorization|secret)=([^&\s]+)/i',
            '$1$2=***',
            $texto
        );
    }

    /**
     * Obtém o IP real do usuário (considerando proxies)
     * 
     * @return string IP address
     */
    private static function getRealIpAddress()
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Se for X-Forwarded-For, pegar o primeiro IP (cliente real)
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validar IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback para REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Filtra dados sensíveis do payload (senhas, tokens, etc)
     * 
     * @param array $data Dados a serem filtrados
     * @return array Dados filtrados
     */
    private static function filterSensitiveData($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        
        $sensitiveFields = [
            'password',
            'senha',
            'senha_hash',
            'password_hash',
            'token',
            '_token',
            'csrf_token',
            'api_key',
            'secret',
            'secret_key',
            'access_token',
            'refresh_token',
            'authorization'
        ];
        
        $filtered = [];
        
        foreach ($data as $key => $value) {
            $keyLower = strtolower($key);
            
            // Se for campo sensível, ocultar
            if (in_array($keyLower, $sensitiveFields)) {
                $filtered[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                // Recursivamente filtrar arrays aninhados
                $filtered[$key] = self::filterSensitiveData($value);
            } else {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }
}

if (!class_exists('Logger', false) && class_exists(__NAMESPACE__ . '\\Logger', false)) {
    class_alias(__NAMESPACE__ . '\\Logger', 'Logger');
}
