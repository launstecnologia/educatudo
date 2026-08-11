<?php
/**
 * EducaTudo - Classe principal da aplicação
 * Responsável pelo roteamento e inicialização do sistema
 */

// Carregar classes necessárias
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AuthManager.php';
require_once __DIR__ . '/BaseController.php';

class App
{
    private $router;
    private $config;
    
    public function __construct()
    {
        $this->loadConfig();
        $this->initRouter();
    }
    
    /**
     * Carrega as configurações do sistema
     */
    private function loadConfig()
    {
        $configPath = __DIR__ . '/../../config/app.php'; // config/app.php (obrigatório na pasta config/)
        if (!is_file($configPath)) {
            throw new \RuntimeException(
                'config/app.php não encontrado em: ' . realpath(dirname($configPath)) . '. '
                . 'O commit 38e9598 não inclui este arquivo. Use um commit mais recente (ex.: branch dev) ou copie config/app.php para a pasta config/ no servidor.'
            );
        }
        $this->config = require_once $configPath;
    }
    
    /**
     * Inicializa o sistema de roteamento
     */
    private function initRouter()
    {
        $this->router = new Router();
        $this->router->setPrefix(FOLDER);
        $this->loadRoutes();
    }
    
    /**
     * Carrega as rotas definidas no arquivo de configuração
     */
    private function loadRoutes()
    {
        // Passa a instância do router para o arquivo de rotas
        $router = $this->router;
        require_once __DIR__ . '/../../config/routes.php';
    }
    
    /**
     * Executa a aplicação
     */
    public function run()
    {
        try {
            $this->handleMobileApiCors();
            $this->handleEducaProfCors();
            $this->router->dispatch();
        } catch (Exception $e) {
            $this->handleError($e);
        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }
    
    /**
     * Trata erros da aplicação
     */
    private function handleError($e)
    {
        // Registrar erro no log
        $this->logError($e);
        $this->applyMobileApiCorsHeaders();
        $this->applyEducaProfCorsHeaders();
        
        // Verifica se é uma requisição AJAX/JSON
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $acceptsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        $isPostJson = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
        
        if ($isAjax || $acceptsJson || $isPostJson) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => defined('DEBUG') && DEBUG ? $e->getMessage() : 'Ocorreu um erro inesperado. Tente novamente mais tarde.',
                'debug' => defined('DEBUG') && DEBUG ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ] : null
            ]);
            return;
        }
        
        http_response_code(500);
        echo $this->renderErrorPage($e);
    }
    
    /**
     * Registra erro detalhado no log
     */
    private function logError($e)
    {
        try {
            require_once __DIR__ . '/Logger.php';
            
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
            ];
            
            // Adicionar informações de sessão se disponível
            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) {
                $context['session'] = [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'user_type' => $_SESSION['user_type'] ?? null,
                ];
            }
            
            // Adicionar POST data (limitado para não expor senhas)
            if (!empty($_POST)) {
                $postData = $_POST;
                // Remover dados sensíveis
                unset($postData['password'], $postData['senha'], $postData['_token']);
                $context['post_data'] = $postData;
            }
            // Incluir causa real quando for exceção encadeada (ex.: falha de conexão com o banco)
            $prev = $e->getPrevious();
            if ($prev !== null) {
                $context['previous_exception'] = [
                    'message' => $prev->getMessage(),
                    'file' => $prev->getFile(),
                    'line' => $prev->getLine(),
                ];
            }
            
            Logger::error(
                "Erro fatal na aplicação: " . $e->getMessage(),
                $context,
                'general'
            );
        } catch (Exception $logError) {
            // Se falhar ao logar, usar error_log padrão
            error_log("Erro ao registrar log: " . $logError->getMessage());
            error_log("Erro original: " . $e->getMessage() . " em " . $e->getFile() . " linha " . $e->getLine());
        }
    }
    
    /**
     * Renderiza página de erro
     */
    private function renderErrorPage($e = null)
    {
        $debug = defined('DEBUG') && DEBUG;
        $errorMessage = 'Ocorreu um erro inesperado. Tente novamente mais tarde.';
        $errorDetails = '';
        
        if ($debug && $e) {
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
        
        return '<!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Erro - EducaTudo</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
            <div class="bg-white p-8 rounded-lg shadow-lg max-w-4xl w-full">
                <h1 class="text-2xl font-bold text-red-600 mb-4">Erro Interno</h1>
                <p class="text-gray-600 mb-4">' . $errorMessage . '</p>
                ' . $errorDetails . '
            </div>
        </body>
        </html>';
    }
    
    /**
     * Retorna instância do router
     */
    public function getRouter()
    {
        return $this->router;
    }
    
    /**
     * Retorna configurações
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * CORS da API mobile v1, incluindo preflight para o Flutter Web.
     * A API usa Bearer token e não habilita credenciais/cookies cross-origin.
     */
    private function handleMobileApiCors(): void
    {
        if (!$this->isMobileApiRequest()) {
            return;
        }

        $this->applyMobileApiCorsHeaders();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    private function applyMobileApiCorsHeaders(): void
    {
        if (!$this->isMobileApiRequest()) {
            return;
        }

        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '') {
            return;
        }

        $allowedOrigins = $this->getMobileApiAllowedOrigins();
        $allowAnyOrigin = in_array('*', $allowedOrigins, true);
        if (!$allowAnyOrigin && !in_array($origin, $allowedOrigins, true)) {
            return;
        }

        if ($allowAnyOrigin) {
            header('Access-Control-Allow-Origin: *');
        } else {
            header('Vary: Origin');
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, X-Request-ID, X-Device-ID, X-Device-Token');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 86400');
    }

    private function isMobileApiRequest(): bool
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $prefix = defined('FOLDER') ? rtrim((string) FOLDER, '/') : '';
        if ($prefix !== '' && strpos($path, $prefix) === 0) {
            $path = (string) substr($path, strlen($prefix));
        }

        return $path === '/api/v1' || strpos($path, '/api/v1/') === 0;
    }

    private function getMobileApiAllowedOrigins(): array
    {
        // Aplicação oficial do leitor facial. A API usa Bearer token e não cookies
        // cross-origin; manter a origem explícita evita liberar dados para qualquer site.
        $officialOrigins = [
            'https://facial.launs.com.br',
            // Flutter Web usado no desenvolvimento e homologação local do app.
            'http://127.0.0.1:8091',
            'http://localhost:8091',
        ];
        $raw = function_exists('env')
            ? (string) env('MOBILE_API_CORS_ORIGINS', '')
            : (string) (getenv('MOBILE_API_CORS_ORIGINS') ?: '');
        if (trim($raw) === '') {
            return $officialOrigins;
        }

        $configuredOrigins = array_values(array_filter(array_map('trim', explode(',', $raw)), static function ($origin) {
            return $origin !== '';
        }));
        if (in_array('*', $configuredOrigins, true)) {
            return ['*'];
        }
        return array_values(array_unique(array_merge($officialOrigins, $configuredOrigins)));
    }

    /**
     * CORS específico dos endpoints de jornada via EducaProf.
     * Suporta preflight OPTIONS sem exigir autenticação.
     */
    private function handleEducaProfCors()
    {
        $this->applyEducaProfCorsHeaders();

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'OPTIONS' && $this->isEducaProfJornadaApiRequest()) {
            http_response_code(204);
            exit;
        }
    }

    private function applyEducaProfCorsHeaders()
    {
        if (!$this->isEducaProfJornadaApiRequest()) {
            return;
        }

        $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '') {
            return;
        }

        $allowedOrigins = $this->getEducaProfAllowedOrigins();
        if (!in_array($origin, $allowedOrigins, true)) {
            return;
        }

        header('Vary: Origin');
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, Accept');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Max-Age: 86400');
    }

    private function isEducaProfJornadaApiRequest(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)parse_url($uri, PHP_URL_PATH);
        if ($path === '') {
            return false;
        }

        $prefix = defined('FOLDER') ? (string)FOLDER : '';
        if ($prefix !== '' && strpos($path, $prefix) === 0) {
            $path = substr($path, strlen($prefix));
            if ($path === false || $path === '') {
                $path = '/';
            }
        }

        return strpos($path, '/professor/api/educaprof/jornadas/') === 0;
    }

    private function getEducaProfAllowedOrigins(): array
    {
        $raw = getenv('EDUCAPROF_CORS_ORIGINS');
        if (is_string($raw) && trim($raw) !== '') {
            $parts = array_map('trim', explode(',', $raw));
            return array_values(array_filter($parts, static function ($item) {
                return $item !== '';
            }));
        }

        return [
            'https://educaprof.educatudo.com',
            'https://preview--educaprof.educatudo.com',
            'https://zeducaprof.lovable.app',
            'https://id-preview--7f1f23e7-7362-4734-89dd-c2aadc5ce35e.lovable.app',
        ];
    }
}
