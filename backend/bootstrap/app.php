<?php
/**
 * EducaTudo — Bootstrap da aplicação (não acessível via web).
 * Carregado por public/index.php após definir BASE_PATH.
 */

if (!defined('BASE_PATH')) {
    throw new RuntimeException('BASE_PATH não definido. Use public/index.php como entrada.');
}

// Suprimir notices antes de qualquer output (polyfill-mbstring emite iconv notices em UTF-8 incompleto)
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 0);

if (!defined('ENV_FILE_PATH')) {
    define('ENV_FILE_PATH', BASE_PATH . '/.env');
}

// Evitar Fatal "Allowed memory size exhausted" (métricas, logs, queries pesadas)
if (ini_get('memory_limit') !== '-1') {
    $current = (int) ini_get('memory_limit');
    if ($current > 0 && $current < 1024) {
        @ini_set('memory_limit', '1024M');
    }
}

/**
 * Converte shorthand do PHP (ex.: 12M, 1G) para bytes.
 */
if (!function_exists('phpIniToBytes')) {
    function phpIniToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $last = strtolower(substr($value, -1));
        $num = (float) $value;
        return match ($last) {
            'g' => (int) ($num * 1024 * 1024 * 1024),
            'm' => (int) ($num * 1024 * 1024),
            'k' => (int) ($num * 1024),
            default => (int) $num,
        };
    }
}

// Falha amigável para payload acima de post_max_size (evita cascata de warnings de sessão/headers).
$isPost = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
$postMaxBytes = phpIniToBytes((string) ini_get('post_max_size'));
if ($isPost && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
    if (!headers_sent()) {
        http_response_code(413);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Arquivo muito grande</title></head><body style="font-family:Arial,sans-serif;padding:24px">';
    echo '<h1 style="margin-top:0">Arquivo muito grande para o servidor</h1>';
    echo '<p>O envio excedeu o limite permitido no PHP (<strong>post_max_size</strong>).</p>';
    echo '<p>Reduza o arquivo ou aumente os limites <code>upload_max_filesize</code> e <code>post_max_size</code> no servidor.</p>';
    echo '</body></html>';
    exit;
}

// Carregar autoload do Composer
if (!file_exists(BASE_PATH . '/vendor/autoload.php')) {
    die('ERRO CRÍTICO: Diretório vendor não encontrado! Execute "composer install" no servidor.');
}

require_once BASE_PATH . '/vendor/autoload.php';

// Helper de sanitização de rich text (rich_text_render / rich_text) disponível em todas as views.
require_once BASE_PATH . '/app/Helpers/RichTextHelper.php';

// ─── Helpers de ambiente (antes de session_start / URL) ───
if (!function_exists('educatudoDetectHttps')) {
    /**
     * Detecta HTTPS real, inclusive atrás de proxy/CDN (X-Forwarded-Proto, Cloudflare).
     */
    function educatudoDetectHttps(): bool
    {
        $httpsOn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $forwardedProto = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwardedProto = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        }
        $forwardedSsl = !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on';
        $serverPort443 = !empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
        $cfVisitorHttps = false;
        if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
            $cf = json_decode((string) $_SERVER['HTTP_CF_VISITOR'], true);
            $cfVisitorHttps = is_array($cf) && (($cf['scheme'] ?? '') === 'https');
        }
        return $httpsOn || $forwardedProto === 'https' || $forwardedSsl || $serverPort443 || $cfVisitorHttps;
    }
}

// Ler chaves do .env usadas antes do config/app.php (SESSION_*, APP_ENV)
$bootstrapEnv = [
    'SESSION_DOMAIN' => '',
    'SESSION_SECURE' => null,
    'APP_ENV' => null,
];
if (file_exists(BASE_PATH . '/.env')) {
    $envLines = @file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$envName, $envValue] = explode('=', $line, 2);
        $envName = trim($envName);
        $envValue = trim($envValue, " \t\"'");
        if (array_key_exists($envName, $bootstrapEnv)) {
            $bootstrapEnv[$envName] = $envValue;
        }
    }
}

// ─── Configuração segura de sessão (antes de session_start) ───
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        $sessionDomain = (string) ($bootstrapEnv['SESSION_DOMAIN'] ?? '');
        // SESSION_SECURE explícito no .env tem prioridade; senão detecta HTTPS (proxy-aware).
        if ($bootstrapEnv['SESSION_SECURE'] !== null && $bootstrapEnv['SESSION_SECURE'] !== '') {
            $isSecure = strtolower((string) $bootstrapEnv['SESSION_SECURE']) === 'true'
                || $bootstrapEnv['SESSION_SECURE'] === '1';
        } else {
            $isSecure = educatudoDetectHttps();
        }
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => $sessionDomain,
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(0, '/; samesite=Lax', $sessionDomain, $isSecure, true);
        }
    }
    @session_start();
    // Token CSRF é criado apenas em BaseController::generateCsrf() (nunca aqui, para não sobrescrever).
}

// Contabilizar erros fatais (E_ERROR/E_PARSE etc.) nas métricas.
// Importante: fatal errors podem acontecer antes do handler de exceções rodar.
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) {
        return;
    }

    try {
        // Autoload via Composer (não usar class_exists(..., false), senão não conta)
        \App\Services\MetricsService::recordHttp500Error();
    } catch (\Throwable $t) {
        // Nunca bloquear a finalização por falha ao registrar métrica
    }
});

// Flush métricas de DB acumuladas no request (uma única escrita ao fim)
register_shutdown_function(function () {
    if (class_exists('Database', false)) {
        Database::flushMetricsToService();
        if (!class_exists('PerfLogger', false)) {
            require_once BASE_PATH . '/app/Core/PerfLogger.php';
        }
        if (PerfLogger::isEnabled()) {
            PerfLogger::flushSlowQueries();
            PerfLogger::flushRequestSummary(Database::getMetricsBuffer());
        }
    }
});

// Performance Profiler (app/Performance/*) — diagnóstico de queries/páginas
// lentas, N+1, EXPLAIN automático e índices sugeridos. Só roda quando
// APP_DEBUG=true (ver App\Performance\Profiler::isEnabled()); em produção com
// APP_DEBUG=false (padrão) isso não gera nenhum overhead além de 1 bool check.
if (class_exists(\App\Performance\Profiler::class) && \App\Performance\Profiler::isEnabled()) {
    \App\Performance\RequestProfiler::start();
    register_shutdown_function(function () {
        try {
            \App\Performance\RequestProfiler::finish();
        } catch (\Throwable $e) {
            error_log('RequestProfiler::finish() falhou: ' . $e->getMessage());
        }
    });
}

// Configuração da pasta base dinâmica (detecta automaticamente)
$scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$folder = ($scriptPath === '/' || $scriptPath === '') ? '' : $scriptPath;
define('FOLDER', $folder);

// Detectar URL base automaticamente (mesma lógica de educatudoDetectHttps — proxy-aware)
$protocol = educatudoDetectHttps() ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('URL', $protocol . '://' . $host . FOLDER);

// Ambiente: lê APP_ENV do .env; default fail-safe = production (não vaza erros se esquecer de setar)
$appEnvRaw = strtolower(trim((string) ($bootstrapEnv['APP_ENV'] ?? '')));
if ($appEnvRaw === '') {
    $appEnvRaw = 'production';
}
// Aceita aliases comuns
if (in_array($appEnvRaw, ['prod', 'production'], true)) {
    $appEnvRaw = 'production';
} elseif (in_array($appEnvRaw, ['dev', 'development', 'local'], true)) {
    $appEnvRaw = 'development';
} else {
    $appEnvRaw = 'production';
}
define('ENVIRONMENT', $appEnvRaw);
define('DEBUG', ENVIRONMENT === 'development');

// Configuração de fuso horário (Brasil - Horário de Brasília)
date_default_timezone_set('America/Sao_Paulo');

// Configuração de segurança
if (ENVIRONMENT === 'production') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', BASE_PATH . '/storage/logs/error.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Carregar configurações de monitoramento
$monitoringConfig = [];
try {
    $monitoringConfigFile = BASE_PATH . '/config/monitoring.php';
    if (file_exists($monitoringConfigFile)) {
        $monitoringConfig = require $monitoringConfigFile;
    }
} catch (Exception $e) {
    error_log("Erro ao carregar configuração de monitoramento: " . $e->getMessage());
}

// Autoloader simples com suporte ao DatabaseWrapper
spl_autoload_register(function ($class) use ($monitoringConfig) {
    // Interceptar classe Database e redirecionar para DatabaseWrapper se configurado
    if ($class === 'Database' && !empty($monitoringConfig['use_database_wrapper'])) {
        $wrapperPath = BASE_PATH . '/app/Services/DatabaseWrapper.php';
        if (file_exists($wrapperPath)) {
            require_once $wrapperPath;
            // Criar alias para compatibilidade
            class_alias('DatabaseWrapper', 'Database');
            return;
        }
    }

    // Remove namespace se existir
    $class = str_replace('\\', '/', $class);

    // Tenta diferentes caminhos
    $paths = [
        BASE_PATH . '/app/' . $class . '.php',
        BASE_PATH . '/app/Core/' . $class . '.php',
        BASE_PATH . '/app/Controllers/' . $class . '.php',
        BASE_PATH . '/app/Models/' . $class . '.php',
        BASE_PATH . '/app/Models/Simulados/' . $class . '.php',
        BASE_PATH . '/app/Middleware/' . $class . '.php'
    ];

    // PSR-4 App\* → app\* (ex.: App\Performance\RequestProfiler → app/Performance/RequestProfiler.php)
    if (strncmp($class, 'App/', 4) === 0) {
        array_unshift($paths, BASE_PATH . '/app/' . substr($class, 4) . '.php');
    }

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

if (!function_exists('inferSchoolForErrorLog')) {
    function inferSchoolForErrorLog(): string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sessionCandidates = [
                $_SESSION['tenant_slug'] ?? null,
                $_SESSION['school_slug'] ?? null,
                $_SESSION['school_code'] ?? null,
                $_SESSION['escola_slug'] ?? null,
                $_SESSION['escola_codigo'] ?? null,
            ];
            foreach ($sessionCandidates as $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        $envCandidates = [
            getenv('TENANT_SLUG') ?: '',
            getenv('SCHOOL_CODE') ?: '',
            getenv('APP_SCHOOL_SLUG') ?: '',
        ];
        foreach ($envCandidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && strtolower($candidate) !== 'default') {
                return $candidate;
            }
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $host = strtolower(trim(preg_replace('/:\d+$/', '', $host)));
        if ($host === '') {
            return 'desconhecida';
        }
        $parts = array_values(array_filter(explode('.', $host)));
        if (count($parts) >= 3) {
            return $parts[0];
        }
        return $host;
    }
}

if (!function_exists('inferUserTypeForErrorLog')) {
    function inferUserTypeForErrorLog(): string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $type = trim((string) ($_SESSION['user_type'] ?? ''));
            if ($type !== '') {
                return $type;
            }
            if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                $type = trim((string) ($_SESSION['user']['tipo'] ?? ''));
                if ($type !== '') {
                    return $type;
                }
            }
        }
        return 'guest';
    }
}

if (!function_exists('buildStructuredErrorLog')) {
    function buildStructuredErrorLog(string $errorText): string
    {
        $baseUrl = defined('URL') ? URL : '';
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $fullUrl = $baseUrl !== '' ? rtrim($baseUrl, '/') . $uri : $uri;
        if ($fullUrl === '') {
            $fullUrl = 'N/A';
        }

        return "Escola: " . inferSchoolForErrorLog()
            . " | Tipo: " . inferUserTypeForErrorLog()
            . " | URL: " . $fullUrl
            . " | Error: " . $errorText;
    }
}

// Configurar handler de erros fatais do PHP
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
        // SEMPRE logar no error_log primeiro (garantido)
        $errorMsg = "ERRO FATAL PHP [{$error['type']}]: {$error['message']} em {$error['file']} linha {$error['line']}";
        $errorMsg .= " | URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A');
        $errorMsg .= " | Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A');
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            $errorMsg .= " | User: " . ($_SESSION['user_id'] ?? 'N/A');
        }
        error_log($errorMsg);
        error_log(buildStructuredErrorLog($error['message'] . ' em ' . $error['file'] . ' linha ' . $error['line']));
        
        // Tentar usar o Logger se disponível
        try {
            if (file_exists(BASE_PATH . '/app/Core/Logger.php')) {
                require_once BASE_PATH . '/app/Core/Logger.php';
                
                $context = [
                    'type' => $error['type'],
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line'],
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
                    'folder' => defined('FOLDER') ? FOLDER : 'N/A',
                    'url' => defined('URL') ? URL : 'N/A',
                    'school' => inferSchoolForErrorLog(),
                    'user_type' => inferUserTypeForErrorLog(),
                    'structured_log' => buildStructuredErrorLog($error['message'] . ' em ' . $error['file'] . ' linha ' . $error['line']),
                ];
                
                if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) {
                    $context['session'] = [
                        'user_id' => $_SESSION['user_id'] ?? null,
                        'user_type' => $_SESSION['user_type'] ?? null,
                    ];
                }
                
                Logger::error(
                    "Erro fatal do PHP: " . $error['message'],
                    $context,
                    'general'
                );
            }
        } catch (Exception $e) {
            error_log("Erro ao usar Logger: " . $e->getMessage());
        } catch (Throwable $e) {
            error_log("Erro fatal ao usar Logger: " . $e->getMessage());
        }
    }
});

// Monitoramento (métricas): desativado no bootstrap para evitar Fatal memory exhausted.
// Defina DISABLE_BOOTSTRAP_METRICS=0 no .env para reativar (pode causar estouro em ambientes com pouca memória).
$envPath = BASE_PATH . '/.env';
$disableBootstrapMetrics = true;
if (file_exists($envPath)) {
    $envLines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($envLines as $line) {
        $line = trim($line);
        if (strpos($line, 'DISABLE_BOOTSTRAP_METRICS=') === 0) {
            $val = trim(substr($line, 24), " \t\"'");
            $disableBootstrapMetrics = ($val !== '0' && $val !== 'false');
            break;
        }
    }
}
if (!$disableBootstrapMetrics) {
    try {
        $cachePath = BASE_PATH . '/storage/metrics_cache.json';
        if (file_exists($cachePath) && @filesize($cachePath) > 100 * 1024) {
            @rename($cachePath, $cachePath . '.bak.' . date('Y-m-d_His'));
        }
        if (function_exists('memory_get_usage') && memory_get_usage(true) < 150 * 1024 * 1024) {
            App\Services\MetricsService::recordMemoryUsage();
            $userType = session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_type']) ? ($_SESSION['user_type'] ?: 'guest') : 'guest';
            App\Services\MetricsService::recordPlatformAccess($userType, $_SERVER['REQUEST_URI'] ?? 'unknown', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'referer' => $_SERVER['HTTP_REFERER'] ?? null
            ]);
        }
    } catch (Throwable $e) {
        error_log("Monitoramento (métricas): " . $e->getMessage());
    }
}

// Configurar handler de exceções não capturadas
set_exception_handler(function($e) {
    // SEMPRE logar no error_log primeiro (garantido)
    $errorMsg = "EXCEÇÃO NÃO CAPTURADA: {$e->getMessage()} em {$e->getFile()} linha {$e->getLine()}";
    $errorMsg .= " | URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A');
    $errorMsg .= " | Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A');
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
        $errorMsg .= " | User: " . ($_SESSION['user_id'] ?? 'N/A');
    }
    error_log($errorMsg);
    error_log(buildStructuredErrorLog($e->getMessage() . ' em ' . $e->getFile() . ' linha ' . $e->getLine()));
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Tentar usar o Logger se disponível
    try {
        if (file_exists(BASE_PATH . '/app/Core/Logger.php')) {
            require_once BASE_PATH . '/app/Core/Logger.php';
            
            $context = [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
                'folder' => defined('FOLDER') ? FOLDER : 'N/A',
                'url' => defined('URL') ? URL : 'N/A',
                'school' => inferSchoolForErrorLog(),
                'user_type' => inferUserTypeForErrorLog(),
                'structured_log' => buildStructuredErrorLog($e->getMessage() . ' em ' . $e->getFile() . ' linha ' . $e->getLine()),
            ];
            
            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) {
                $context['session'] = [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'user_type' => $_SESSION['user_type'] ?? null,
                ];
            }
            
                Logger::error(
                    "Exceção não capturada: " . $e->getMessage(),
                    $context,
                    'general'
                );
            }
        } catch (Exception $logError) {
            error_log("Erro ao usar Logger: " . $logError->getMessage());
        } catch (Throwable $logError) {
            error_log("Erro fatal ao usar Logger: " . $logError->getMessage());
        }

        // Registrar erro 500 no sistema de métricas
        try {
            // Autoload via Composer (não depender de include manual/ordem de carregamento)
            \App\Services\MetricsService::recordHttp500Error();
        } catch (Throwable $metricsError) {
            error_log("Erro ao registrar métrica 500: " . $metricsError->getMessage());
        }
    
    $debug = defined('DEBUG') && DEBUG;
    $errorMessage = 'Ocorreu um erro inesperado. Tente novamente mais tarde.';
    $errorDetails = '';
    $isDatabaseError = $e && (stripos($e->getMessage(), 'ERRO NO BANCO') !== false || stripos($e->getMessage(), 'banco de dados') !== false);

    if (($debug || $isDatabaseError) && $e) {
        $errorMessage = htmlspecialchars($e->getMessage());
        $errorDetails = '<div class="mt-4 text-left bg-gray-50 p-4 rounded border border-gray-200">
            <p class="font-semibold text-sm text-gray-700 mb-2">Detalhes do Erro:</p>
            <p class="text-xs text-gray-600 mb-1"><strong>Arquivo:</strong> ' . htmlspecialchars($e->getFile()) . '</p>
            <p class="text-xs text-gray-600 mb-1"><strong>Linha:</strong> ' . $e->getLine() . '</p>
            <details class="mt-2">
                <summary class="cursor-pointer text-xs text-blue-600 hover:text-blue-800">Ver Stack Trace</summary>
                <pre class="mt-2 text-xs bg-gray-800 text-green-400 p-3 rounded overflow-auto max-h-96">' . htmlspecialchars($e->getTraceAsString()) . '</pre>
            </details>
        </div>';
    }
    
    // Verifica se é uma requisição AJAX/JSON
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $acceptsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    $isPostJson = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
    
    if ($isAjax || $acceptsJson || $isPostJson) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => ($debug || $isDatabaseError) && $e ? $e->getMessage() : 'Ocorreu um erro inesperado. Tente novamente mais tarde.',
            'debug' => $debug && $e ? [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ] : null
        ]);
        return;
    }
    
    http_response_code(500);
    $pageTitle = $isDatabaseError ? 'Erro no banco de dados - EducaTudo' : 'Erro - EducaTudo';
    $heading = $isDatabaseError ? 'Erro no banco de dados' : 'Erro Interno';
    $boxClass = $isDatabaseError ? 'border-l-4 border-red-500' : '';
    echo '<!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($pageTitle) . '</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
        <div class="bg-white p-8 rounded-lg shadow-lg max-w-4xl w-full ' . $boxClass . '">
            <h1 class="text-2xl font-bold text-red-600 mb-4">' . htmlspecialchars($heading) . '</h1>
            <p class="text-gray-700 mb-4">' . $errorMessage . '</p>
            ' . ($isDatabaseError ? '<p class="text-sm text-gray-500 mt-2">Corrija o arquivo <strong>.env</strong> no servidor (DB_HOST, DB_NAME, DB_USER, DB_PASS) e tente novamente.</p>' : '') . '
            ' . $errorDetails . '
        </div>
    </body>
    </html>';
});

// Servir arquivos estáticos comuns (evita "rota não encontrada" no log)
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestUri = $requestUri === '' ? '/' : $requestUri;
$path = $requestUri;
if (defined('FOLDER') && FOLDER !== '' && strpos($path, FOLDER) === 0) {
    $path = substr($path, strlen(FOLDER)) ?: '/';
}
$path = trim($path, '/');
if ($path === 'service-worker.js') {
    $file = BASE_PATH . '/public/service-worker.js';
    if (file_exists($file)) {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        readfile($file);
        exit;
    }
}
if ($path === 'favicon.ico') {
    http_response_code(204);
    header('Content-Length: 0');
    exit;
}
// Safari/iOS pede ícones ao adicionar à tela inicial (evita "rota não encontrada" no log)
if (strpos($path, 'apple-touch-icon') === 0 && substr($path, -4) === '.png') {
    http_response_code(204);
    header('Content-Length: 0');
    exit;
}
// favicon em PNG (ex.: okhttp/Android)
if ($path === 'favicon.png') {
    http_response_code(204);
    header('Content-Length: 0');
    exit;
}
// robots.txt (Bing, Google, OpenAI SearchBot etc.) — arquivo em public/ ou padrão mínimo
if ($path === 'robots.txt') {
    $robotsFile = BASE_PATH . '/public/robots.txt';
    if (file_exists($robotsFile) && is_file($robotsFile)) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        readfile($robotsFile);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo "User-agent: *\nAllow: /\n";
    exit;
}
// Google/Android pede assetlinks.json para App Links (evita "rota não encontrada" no log)
// REQUEST_URI pode vir como /.well-known/... então $path fica ".well-known/assetlinks.json"
$pathWellKnown = ltrim($path, '.');
if ($pathWellKnown === 'well-known/assetlinks.json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo '[]';
    exit;
}
// .well-known/security.txt (scanners/Censys) — responde 404 sem passar pelo router
if ($pathWellKnown === 'well-known/security.txt') {
    http_response_code(404);
    header('Content-Length: 0');
    exit;
}
// Bloqueio explícito a .git (varredura de segurança; nunca expor o repositório)
if ($path === 'git/config' || strpos($path, 'git/') === 0) {
    http_response_code(404);
    header('Content-Length: 0');
    exit;
}
// Servir /static/* (dashboard de monitoramento)
if (strpos($path, 'static/') === 0) {
    $staticFile = BASE_PATH . '/public/' . $path;
    if (file_exists($staticFile) && is_file($staticFile)) {
        $ext = pathinfo($staticFile, PATHINFO_EXTENSION);
        $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', 'json' => 'application/json'];
        if (isset($types[$ext])) {
            header('Content-Type: ' . $types[$ext] . '; charset=' . ($ext === 'js' || $ext === 'css' ? 'utf-8' : ''));
        }
        header('Cache-Control: public, max-age=3600');
        readfile($staticFile);
        exit;
    }
}
// Servir /public/uploads/* (layout, capa login, logos etc. — evita "rota não encontrada" no log)
if (strpos($path, 'public/uploads/') === 0) {
    $uploadsFile = BASE_PATH . '/' . $path;
    if (file_exists($uploadsFile) && is_file($uploadsFile)) {
        $realPath = realpath($uploadsFile);
        $realDir = realpath(BASE_PATH . '/public/uploads');
        if ($realDir !== false && $realPath !== false && strpos($realPath, $realDir) === 0) {
            $ext = strtolower(pathinfo($uploadsFile, PATHINFO_EXTENSION));
            $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon'];
            if (isset($mimes[$ext])) {
                header('Content-Type: ' . $mimes[$ext]);
            }
            header('Cache-Control: public, max-age=86400');
            readfile($uploadsFile);
            exit;
        }
    }
}
// Servir /storage/chat/* quando o arquivo existe (localhost e produção; evita "rota não encontrada" para imagens do chat)
$pathForStorage = ltrim($path, '/');
if (strpos($pathForStorage, 'storage/chat/') === 0) {
    $relFile = $pathForStorage; // ex: storage/chat/chat_3_xxx.jpg
    $filename = substr($relFile, strlen('storage/chat/'));
    // Mesmo caminho que StudentController usa no upload (BASE_PATH = src ao rodar index.php)
    $baseDirs = [
        BASE_PATH . '/storage/chat',
        BASE_PATH . '/storage/chat',
        BASE_PATH . '/app/Controllers/User/../../storage/chat'
    ];
    foreach ($baseDirs as $storageChatDir) {
        $storageChatFile = $storageChatDir . '/' . $filename;
        if (is_file($storageChatFile)) {
            $realPath = realpath($storageChatFile);
            $realDir = realpath($storageChatDir);
            if ($realDir !== false && $realPath !== false && strpos($realPath, $realDir) === 0) {
                $ext = strtolower(pathinfo($storageChatFile, PATHINFO_EXTENSION));
                $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
                if (isset($mimes[$ext])) {
                    header('Content-Type: ' . $mimes[$ext]);
                }
                header('Cache-Control: public, max-age=86400');
                readfile($storageChatFile);
                exit;
            }
        }
    }
}
// Servir /storage/images/* (imagens geradas pela API de geração educacional)
if (strpos($pathForStorage, 'storage/images/') === 0) {
    $filename = substr($pathForStorage, strlen('storage/images/'));
    $baseDirs = [
        BASE_PATH . '/storage/images',
        BASE_PATH . '/storage/images'
    ];
    foreach ($baseDirs as $storageImagesDir) {
        $storageImagesFile = $storageImagesDir . '/' . $filename;
        if (is_file($storageImagesFile)) {
            $realPath = realpath($storageImagesFile);
            $realDir = realpath($storageImagesDir);
            if ($realDir !== false && $realPath !== false && strpos($realPath, $realDir) === 0) {
                $ext = strtolower(pathinfo($storageImagesFile, PATHINFO_EXTENSION));
                $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
                if (isset($mimes[$ext])) {
                    header('Content-Type: ' . $mimes[$ext]);
                }
                header('Cache-Control: public, max-age=86400');
                readfile($storageImagesFile);
                exit;
            }
        }
    }
}

// Fallback: servidor pode não repassar POST /path ao index.php; ?__path= permite usar index.php como entrada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['__path'])) {
    $allowedPathFallbacks = ['primeiro-acesso/validar'];
    $p = trim((string) $_GET['__path'], '/');
    if (in_array($p, $allowedPathFallbacks, true)) {
        $_SERVER['REQUEST_URI'] = '/' . $p;
        unset($_GET['__path']);
    }
}

// Bootstrap multi-tenant (se MULTI_TENANT=true no .env): resolve escola e registra conexão do tenant
require_once BASE_PATH . '/app/Core/bootstrap_multi_tenant.php';

// Inicializar aplicação
try {
    require_once BASE_PATH . '/app/Core/App.php';
    $app = new App();
    $app->run();
} catch (Exception $e) {
    // O exception handler acima já vai capturar e logar
    throw $e;
}