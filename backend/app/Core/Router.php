<?php
/**
 * EducaTudo - Sistema de Roteamento
 * Gerencia as rotas da aplicação com suporte a pasta base dinâmica
 */

class Router
{
    private $routes = [];
    private $prefix = '';
    private $middleware = [];
    
    /**
     * Define o prefixo das rotas (pasta base dinâmica)
     */
    public function setPrefix($prefix)
    {
        $this->prefix = rtrim($prefix, '/');
    }
    
    /**
     * Adiciona uma rota GET
     */
    public function get($path, $handler)
    {
        $this->addRoute('GET', $path, $handler);
    }
    
    /**
     * Adiciona uma rota POST
     */
    public function post($path, $handler)
    {
        $this->addRoute('POST', $path, $handler);
    }
    
    /**
     * Adiciona uma rota PUT
     */
    public function put($path, $handler)
    {
        $this->addRoute('PUT', $path, $handler);
    }
    
    /**
     * Adiciona uma rota DELETE
     */
    public function delete($path, $handler)
    {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    /**
     * Adiciona uma rota com middleware
     */
    public function middleware($middleware, $callback)
    {
        $this->middleware[] = $middleware;
        $callback($this);
        array_pop($this->middleware);
    }
    
    /**
     * Adiciona uma rota ao sistema
     */
    private function addRoute($method, $path, $handler)
    {
        $fullPath = $this->prefix . $path;
        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'pattern' => $this->pathToPattern($fullPath),
            'handler' => $handler,
            'middleware' => $this->middleware
        ];
    }
    
    /**
     * Converte path em padrão regex
     * Rotas que terminam em /professor/jornadas/{id} usam (\d+) para não capturar "modulos"
     * (evita que /professor/jornadas/modulos/152/dica-professor seja tratada como show("modulos"))
     */
    private function pathToPattern($path)
    {
        // ID de jornada deve ser numérico: /professor/jornadas/{id} não pode capturar "modulos"
        if (preg_match('#/professor/jornadas/\{id\}$#', $path)) {
            $pattern = preg_replace('#\{id\}#', '(\d+)', $path);
            return '#^' . $pattern . '$#';
        }
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    /**
     * Ordena rotas por especificidade (mais específicas primeiro)
     */
    private function sortRoutesBySpecificity()
    {
        usort($this->routes, function($a, $b) {
            // Primeiro ordena por número de segmentos (mais segmentos = mais específica)
            $segmentsA = substr_count($a['path'], '/');
            $segmentsB = substr_count($b['path'], '/');
            if ($segmentsA !== $segmentsB) {
                return $segmentsB <=> $segmentsA;
            }
            // Se tiverem o mesmo número de segmentos, prioriza a que tem menos parâmetros
            $paramsA = substr_count($a['path'], '{');
            $paramsB = substr_count($b['path'], '{');
            return $paramsA <=> $paramsB;
        });
    }
    
    /**
     * Processa a requisição e executa a rota correspondente
     */
    public function dispatch()
    {
        // Ordena rotas uma vez antes de processar
        $this->sortRoutesBySpecificity();
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Suporte para métodos PUT e DELETE via JavaScript
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
        
        $rawUri = $_SERVER['REQUEST_URI'] ?? '/';
        // Normaliza // no início para / (ex: //admin -> /admin), evitando parse_url retornar null no path
        if (strpos($rawUri, '//') === 0) {
            $rawUri = '/' . ltrim($rawUri, '/');
        }
        $uri = parse_url($rawUri, PHP_URL_PATH);
        $uri = ($uri !== null && $uri !== '') ? $uri : '/';
        // Remove barra final (exceto para "/") para casar rotas sem trailing slash
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }
        
        // Log de debug para rotas de jornadas
        if (strpos($uri, 'jornadas') !== false && strpos($uri, 'exercicios') !== false) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            }
            // Lista as primeiras 10 rotas relacionadas a jornadas
            $jornadasRoutes = array_filter($this->routes, function($r) {
                return strpos($r['path'], 'jornadas') !== false && $r['method'] === 'GET';
            });
            $count = 0;
            foreach ($jornadasRoutes as $r) {
                if ($count++ < 10) {
                }
            }
        }
        
        // Log de debug para transcrição
        if (strpos($uri, 'transcrever-imagem') !== false) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            }
        }

        // Modo manutenção global (com exceções técnicas para controle)
        if ($this->isMaintenanceEnabled()) {
            $uriWithoutPrefix = $uri;
            if ($this->prefix !== '' && strpos($uriWithoutPrefix, $this->prefix) === 0) {
                $uriWithoutPrefix = substr($uriWithoutPrefix, strlen($this->prefix));
                if ($uriWithoutPrefix === false || $uriWithoutPrefix === '') {
                    $uriWithoutPrefix = '/';
                }
            }
            $uriWithoutPrefix = '/' . ltrim($uriWithoutPrefix, '/');

            if (!$this->isMaintenanceBypassRoute($uriWithoutPrefix)) {
                http_response_code(503);
                header('Retry-After: 1800');
                echo $this->renderMaintenancePage();
                exit;
            }
        }

        // Feature gating por módulo (bloqueio antes de casar rotas)
        if (!class_exists('FeatureGate')) {
            require_once __DIR__ . '/FeatureGate.php';
        }
        $redirectUrl = FeatureGate::getRedirectIfBlocked($uri);
        if ($redirectUrl !== null) {
            http_response_code(302);
            header('Location: ' . $redirectUrl);
            exit;
        }
        
        // Log todas as rotas de jornadas antes de processar
        if (strpos($uri, 'jornadas') !== false && strpos($uri, 'exercicios') !== false) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            }
            
            // Lista as primeiras 15 rotas relacionadas a jornadas
            $jornadasRoutes = array_filter($this->routes, function($r) use ($method) {
                return strpos($r['path'], 'jornadas') !== false && $r['method'] === $method;
            });
            $count = 0;
            foreach ($jornadasRoutes as $r) {
                if ($count++ < 15) {
                    $matchesTest = [];
                    $matches = preg_match($r['pattern'], $uri, $matchesTest);
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    }
                    if ($matches) {
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        }
                    }
                }
            }
        }
        
        $urisToTry = [$uri];
        if (defined('FOLDER') && FOLDER !== '' && $uri !== '/' && strpos($uri, $this->prefix) !== 0) {
            $urisToTry[] = rtrim($this->prefix, '/') . ($uri === '/' ? '' : $uri);
        }
        foreach ($urisToTry as $uriToTry) {
            foreach ($this->routes as $index => $route) {
                if ($route['method'] === $method && preg_match($route['pattern'], $uriToTry, $matches)) {
                    // Log de debug para rotas de jornadas
                    if (strpos($uriToTry, 'jornadas') !== false) {
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        }
                    }
                    
                    // Executa middlewares
                    foreach ($route['middleware'] as $middleware) {
                        $this->executeMiddleware($middleware);
                    }
                    
                    // Executa middleware de auditoria (após autenticação, se houver)
                    $this->executeAuditMiddleware();
                    
                    // Executa o handler
                    $this->executeHandler($route['handler'], array_slice($matches, 1));
                    return;
                }
            }
        }
        
        // Log de debug se rota não encontrada
        if (strpos($uri, 'transcrever-imagem') !== false) {
            foreach ($this->routes as $route) {
                if ($route['method'] === $method && strpos($route['path'], 'transcrever') !== false) {
                }
            }
        }
        
        // Rota não encontrada — não logar nem notificar para arquivos estáticos (fontes, CSS, etc.)
        $pathNorm = ltrim(parse_url($uri, PHP_URL_PATH) ?: $uri, '/');
        $ext = strtolower(pathinfo($pathNorm, PATHINFO_EXTENSION));
        $extensoesEstaticas = ['woff2', 'woff', 'ttf', 'otf', 'eot', 'css', 'js', 'ico', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'map'];
        $ehEstatico = (strpos($pathNorm, 'public/static/') !== false || strpos($pathNorm, 'static/') === 0 || in_array($ext, $extensoesEstaticas, true));
        if (!$ehEstatico) {
            $uriForLog = $uri;
            if (defined('FOLDER') && FOLDER !== '' && strpos($uri, FOLDER) === 0) {
                $uriForLog = substr($uri, strlen(FOLDER)) ?: '/';
            }
        }
        if (strpos($uri, 'iniciar') !== false) {
            try {
                require_once __DIR__ . '/Logger.php';
                Logger::warning('[ROUTER] Rota iniciar prova NÃO encontrada', ['method' => $method, 'uri' => $uri], 'provas');
            } catch (Throwable $e) { /* não falhar */ }
        }
        if (strpos($uri, 'primeiro-acesso') !== false) {
            try {
                require_once __DIR__ . '/Logger.php';
                $primeiroAcessoRoutes = array_filter($this->routes, function ($r) {
                    return strpos($r['path'], 'primeiro-acesso') !== false && $r['method'] === $method;
                });
                Logger::warning(
                    '[ROUTER] Rota primeiro-acesso não encontrada (404)',
                    [
                        'method' => $method,
                        'uri' => $uri,
                        'folder' => defined('FOLDER') ? FOLDER : 'N/A',
                        'prefix' => $this->prefix,
                        'registered_primeiro_acesso_routes' => array_map(function ($r) {
                            return $r['method'] . ' ' . $r['path'];
                        }, array_values($primeiroAcessoRoutes)),
                    ],
                    'auth'
                );
            } catch (Throwable $e) { /* não falhar */ }
        }
        $this->handleNotFound();
    }
    
    /**
     * Executa middleware de auditoria
     */
    private function executeAuditMiddleware()
    {
        try {
            if (!class_exists('AuditMiddleware')) {
                require_once __DIR__ . '/../Middleware/AuditMiddleware.php';
            }
            $auditMiddleware = new AuditMiddleware();
            $auditMiddleware->handle();
        } catch (Exception $e) {
            // Não interromper requisição se houver erro no audit middleware
        } catch (Throwable $e) {
        }
    }
    
    /**
     * Executa middleware
     */
    private function executeMiddleware($middleware)
    {
        if (is_string($middleware)) {
            // Tenta diferentes namespaces para o middleware
            $middlewareClasses = [
                "{$middleware}Middleware",
                "Middleware\\{$middleware}",
                "{$middleware}",
                "App\\Middleware\\{$middleware}"
            ];
            
            foreach ($middlewareClasses as $middlewareClass) {
                if (class_exists($middlewareClass)) {
                    $instance = new $middlewareClass();
                    $instance->handle();
                    return;
                }
            }
            
            throw new Exception("Middleware não encontrado: {$middleware}");
        } elseif (is_callable($middleware)) {
            $middleware();
        }
    }
    
    /**
     * Executa o handler da rota
     */
    private function executeHandler($handler, $params = [])
    {
        try {
            if (is_string($handler)) {
                // Formato: Controller@method
                if (strpos($handler, '@') !== false) {
                    list($controller, $method) = explode('@', $handler);
                    
                    // Tenta diferentes namespaces e carrega o arquivo
                    // Suporta controllers em subpastas (ex: Auth/AuthController, User/StudentController)
                    $controllerParts = explode('/', $controller);
                    $controllerName = end($controllerParts);
                    $subfolder = count($controllerParts) > 1 ? implode('/', array_slice($controllerParts, 0, -1)) . '/' : '';
                    
                    $controllerClasses = [
                        "Controllers\\" . str_replace('/', '\\', $controller),
                        str_replace('/', '\\', $controller),
                        "App\\Controllers\\" . str_replace('/', '\\', $controller),
                        $controllerName
                    ];
                    
                    $basePath = __DIR__ . "/../Controllers/";
                    $controllerPaths = [
                        $basePath . $controller . ".php",
                        $basePath . $subfolder . $controllerName . ".php",
                        __DIR__ . "/../../app/Controllers/{$controller}.php",
                        __DIR__ . "/../../app/Controllers/{$subfolder}{$controllerName}.php"
                    ];

                    // Módulos físicos: Modulos/<chave>/<Controller>
                    if (!class_exists('ModuloRegistry', false)) {
                        require_once __DIR__ . '/ModuloRegistry.php';
                    }
                    $moduloControllerPath = ModuloRegistry::resolveControllerPath($controller);
                    if ($moduloControllerPath !== null) {
                        array_unshift($controllerPaths, $moduloControllerPath);
                        array_unshift($controllerClasses, $controllerName);
                    }
                    
                    foreach ($controllerClasses as $index => $controllerClass) {
                        // Tentar carregar o arquivo primeiro
                        if (isset($controllerPaths[$index]) && file_exists($controllerPaths[$index])) {
                            require_once $controllerPaths[$index];
                            
                            // Se a classe especificada não existe, tentar encontrar classes no arquivo
                            if (!class_exists($controllerClass)) {
                                // Caso especial: UserController.php contém UsuarioController
                                if ($controllerName === 'UserController') {
                                    if (class_exists('UsuarioController')) {
                                        $controllerClass = 'UsuarioController';
                                    }
                                }
                                
                                // Tentar outras variações comuns
                                if (!class_exists($controllerClass)) {
                                    $variations = [
                                        $controllerName . 'Controller',
                                        str_replace('Controller', '', $controllerName) . 'Controller'
                                    ];
                                    
                                    foreach ($variations as $variation) {
                                        if (class_exists($variation)) {
                                            $controllerClass = $variation;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        
                        if (class_exists($controllerClass)) {
                            $instance = new $controllerClass();
                            if (method_exists($instance, $method)) {
                                call_user_func_array([$instance, $method], $params);
                                return;
                            }
                        }
                    }
                    
                    throw new Exception("Controller não encontrado: {$controller} ou método não existe: {$method}");
                }
            } elseif (is_callable($handler)) {
                call_user_func_array($handler, $params);
                return;
            }
            
            throw new Exception("Handler inválido: " . print_r($handler, true));
        } catch (Exception $e) {
            $this->logRouterError($e, $handler, $params);
            throw $e;
        } catch (Throwable $e) {
            $this->logRouterError($e, $handler, $params);
            throw $e;
        }
    }
    
    /**
     * Registra erro do router no log
     */
    private function logRouterError($e, $handler, $params = [])
    {
        try {
            require_once __DIR__ . '/Logger.php';
            
            $context = [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'handler' => is_string($handler) ? $handler : gettype($handler),
                'params' => $params,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            ];
            
            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) {
                $context['session'] = [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'user_type' => $_SESSION['user_type'] ?? null,
                ];
            }
            
            Logger::error(
                "Erro no router ao executar handler: " . $e->getMessage(),
                $context,
                'general'
            );
        } catch (Exception $logError) {
        }
    }
    
    /**
     * Trata rota não encontrada
     */
    private function handleNotFound()
    {
        // Logar rota não encontrada
        $this->logNotFound();
        
        http_response_code(404);
        echo $this->renderNotFoundPage();
    }
    
    /**
     * Registra rota não encontrada no log
     */
    private function logNotFound()
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'N/A';
        $path = parse_url($requestUri, PHP_URL_PATH);
        $pathNorm = $path !== null && $path !== false ? ltrim($path, '/') : '';
        // Não logar como "rota não encontrada" pedidos que geram ruído (estáticos, WebSocket, imagens do chat, uploads, etc.)
        if (strpos($pathNorm, 'storage/chat/') !== false || strpos($pathNorm, 'storage/') === 0) {
            return;
        }
        if (strpos($pathNorm, 'public/uploads/') === 0 || strpos($pathNorm, 'uploads/') === 0) {
            return;
        }
        if ($pathNorm === 'ws' || strpos($pathNorm, 'ws/') === 0) {
            return; // tentativas de WebSocket (navegador/extensões) — app não usa /ws
        }
        if (strpos($pathNorm, '.well-known/') === 0 || strpos($pathNorm, '.well-known') !== false) {
            return; // Chrome DevTools e outros pedidos .well-known
        }
        // Não logar 404 de arquivos estáticos (fontes, CSS, JS, imagens) — evita ruído e notificações (ex.: KaTeX .woff2)
        if (strpos($pathNorm, 'public/static/') !== false || strpos($pathNorm, 'static/') === 0) {
            return;
        }
        $ext = strtolower(pathinfo($pathNorm, PATHINFO_EXTENSION));
        $extensoesEstaticas = ['woff2', 'woff', 'ttf', 'otf', 'eot', 'css', 'js', 'ico', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'map'];
        if (in_array($ext, $extensoesEstaticas, true)) {
            return;
        }
        try {
            require_once __DIR__ . '/Logger.php';
            
            $context = [
                'request_uri' => $requestUri,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
                'folder' => defined('FOLDER') ? FOLDER : 'N/A',
                'url' => defined('URL') ? URL : 'N/A',
            ];
            
            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) {
                $context['session'] = [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'user_type' => $_SESSION['user_type'] ?? null,
                ];
            }
            
            Logger::warning(
                "Rota não encontrada: " . $requestUri,
                $context,
                'rotas_nao_encontrada'
            );
        } catch (Exception $e) {
            // Silenciosamente falhar no logging
        }
    }
    
    /**
     * Renderiza página 404
     */
    private function renderNotFoundPage()
    {
        return '<!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Página não encontrada - EducaTudo</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-100 flex items-center justify-center min-h-screen">
            <div class="bg-white p-8 rounded-lg shadow-lg text-center">
                <h1 class="text-6xl font-bold text-primary-600 mb-4">404</h1>
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Página não encontrada</h2>
                <p class="text-gray-600 mb-6">A página que você está procurando não existe.</p>
                <a href="' . URL . '" class="bg-primary-600 text-white px-6 py-3 rounded-lg hover:bg-primary-700 transition-colors">
                    Voltar ao início
                </a>
            </div>
        </body>
        </html>';
    }

    private function isMaintenanceEnabled(): bool
    {
        try {
            if (!class_exists('LayoutHelper')) {
                require_once __DIR__ . '/LayoutHelper.php';
            }
            return LayoutHelper::get('maintenance_mode', '0') === '1';
        } catch (Throwable $e) {
            return false;
        }
    }

    private function isMaintenanceBypassRoute(string $uri): bool
    {
        if ($uri === '/run_migrate_colag_to_s3.php') {
            return true;
        }

        // Polling de jobs de IA durante correção assíncrona
        if (strpos($uri, '/ai-job/') === 0) {
            return true;
        }
        if (strpos($uri, '/professor/ai-job/') === 0 || strpos($uri, '/admin/ai-job/') === 0) {
            return true;
        }
        if (strpos($uri, '/professor/jornadas/ai-job/') === 0 || strpos($uri, '/admin/jornadas/ai-job/') === 0) {
            return true;
        }
        if (strpos($uri, '/professor/jornadas/corrigir-redacao-ia/status/') === 0) {
            return true;
        }
        return false;
    }

    private function renderMaintenancePage(): string
    {
        $baseUrl = defined('URL') ? URL : '';
        return '<!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Em Manutenção - EducaTudo</title>
            <style>
                :root {
                    --bg1: #111827;
                    --bg2: #1f2937;
                    --card: rgba(255, 255, 255, 0.08);
                    --text: #f9fafb;
                    --muted: #d1d5db;
                    --accent: #22d3ee;
                    --accent2: #a78bfa;
                }
                * { box-sizing: border-box; }
                body {
                    margin: 0;
                    min-height: 100vh;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
                    color: var(--text);
                    background: radial-gradient(circle at 20% 20%, #0f172a, var(--bg1) 45%, var(--bg2) 100%);
                    display: grid;
                    place-items: center;
                    overflow: hidden;
                }
                .orb {
                    position: fixed;
                    width: 40vmin;
                    height: 40vmin;
                    border-radius: 999px;
                    filter: blur(40px);
                    opacity: 0.35;
                    animation: float 8s ease-in-out infinite;
                }
                .orb.one { background: var(--accent); top: -10vmin; left: -10vmin; }
                .orb.two { background: var(--accent2); bottom: -15vmin; right: -8vmin; animation-delay: 1.6s; }
                @keyframes float {
                    0%, 100% { transform: translateY(0) translateX(0) scale(1); }
                    50% { transform: translateY(-12px) translateX(8px) scale(1.04); }
                }
                .card {
                    width: min(92vw, 740px);
                    background: var(--card);
                    border: 1px solid rgba(255,255,255,0.18);
                    border-radius: 24px;
                    padding: 36px 28px;
                    backdrop-filter: blur(8px);
                    box-shadow: 0 20px 45px rgba(0,0,0,0.35);
                    text-align: center;
                }
                .gear-wrap {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 14px;
                    margin-bottom: 14px;
                }
                .gear {
                    width: 56px;
                    height: 56px;
                    border: 4px dashed rgba(255,255,255,0.75);
                    border-radius: 50%;
                    animation: spin 3.2s linear infinite;
                }
                .gear.small {
                    width: 36px;
                    height: 36px;
                    border-color: rgba(34,211,238,0.95);
                    animation-direction: reverse;
                    animation-duration: 2.1s;
                }
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                h1 {
                    margin: 10px 0 8px;
                    font-size: clamp(1.6rem, 2vw + 1rem, 2.3rem);
                    line-height: 1.2;
                }
                p {
                    margin: 0 auto;
                    color: var(--muted);
                    max-width: 62ch;
                    line-height: 1.55;
                }
                .pulse {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    margin-top: 16px;
                    font-size: 0.92rem;
                    color: #c4b5fd;
                }
                .dot {
                    width: 9px;
                    height: 9px;
                    border-radius: 999px;
                    background: #22d3ee;
                    animation: pulse 1.4s ease-in-out infinite;
                }
                @keyframes pulse {
                    0%, 100% { transform: scale(1); opacity: 0.65; }
                    50% { transform: scale(1.35); opacity: 1; }
                }
            </style>
        </head>
        <body>
            <div class="orb one"></div>
            <div class="orb two"></div>
            <main class="card">
                <div class="gear-wrap">
                    <div class="gear"></div>
                    <div class="gear small"></div>
                </div>
                <h1>Estamos melhorando tudo por aqui</h1>
                <p>
                    O sistema está em manutenção para ficar mais rápido, estável e com novidades.
                    Em alguns minutos você poderá voltar e continuar normalmente.
                </p>
                <div class="pulse"><span class="dot"></span>Atualizando recursos em tempo real...</div>
            </main>
        </body>
        </html>';
    }
}
