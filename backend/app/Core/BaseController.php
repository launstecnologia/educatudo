<?php
/**
 * EducaTudo - Controller Base
 * Classe base para todos os controllers
 */

abstract class BaseController
{
    protected $config;

    public function __construct()
    {
        // Define constantes se não existirem (para requisições AJAX diretas)
        if (!defined('FOLDER')) {
            // Detecta automaticamente o FOLDER baseado no caminho do script
            $scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $folder = ($scriptPath === '/' || $scriptPath === '') ? '' : $scriptPath;
            define('FOLDER', $folder);
        }
        if (!defined('URL')) {
            // Detecta URL automaticamente
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            define('URL', $protocol . '://' . $host . FOLDER);
        }
        if (!defined('ENVIRONMENT')) {
            define('ENVIRONMENT', 'development');
        }
        if (!defined('DEBUG')) {
            define('DEBUG', ENVIRONMENT === 'development');
        }
        
        // Configurar error reporting
        if (ENVIRONMENT === 'production') {
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
            ini_set('error_log', __DIR__ . '/../../storage/logs/error.log');
        } else {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        }

        // Carrega config a cada requisição para refletir o .env atual (ex.: SCHOOL_CODE)
        $loaded = require __DIR__ . '/../../config/app.php';
        $this->config = is_array($loaded) ? $loaded : [];
    }
    
    /**
     * Renderiza uma view
     */
    protected function view($view, $data = [])
    {
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        
        if (!file_exists($viewFile)) {
            throw new Exception("View não encontrada: {$view}");
        }
        
        // Extrai as variáveis para o escopo da view
        extract($data);
        
        // Inclui a view
        include $viewFile;
    }
    
    /**
     * Renderiza uma view com layout
     */
    protected function viewWithLayout($layout, $view, $data = [])
    {
        // Evita cache do navegador e do Service Worker: após logout, outro usuário não deve ver dados do anterior
        $this->disableCache();

        // Obter todos os argumentos para verificar o que foi realmente passado
        $args = func_get_args();
        
        // Garantir que $data é sempre um array
        if (!is_array($data)) {
            error_log("BaseController::viewWithLayout - \$data não é array");
            $data = [];
        }
        
        $layoutFile = __DIR__ . '/../Views/layouts/' . $layout . '.php';
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        
        if (!file_exists($layoutFile)) {
            throw new Exception("Layout não encontrado: {$layout}");
        }
        
        if (!file_exists($viewFile)) {
            throw new Exception("View não encontrada: {$view}");
        }
        
        // Limpa qualquer output anterior
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Preserva o array $data original ANTES de qualquer extract
        // Faz uma cópia profunda para garantir que não será modificado
        $dataForLayout = is_array($data) ? unserialize(serialize($data)) : [];
        
        // Captura o conteúdo da view
        ob_start();
        try {
            // Cria uma cópia para o extract da view também
            $dataForView = is_array($data) ? unserialize(serialize($data)) : [];
            extract($dataForView);
            include $viewFile;
            $content = ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            require_once __DIR__ . '/../Core/Logger.php';
            Logger::error("Erro ao renderizar view {$view}: " . $e->getMessage(), [
                'exception' => $e,
                'view' => $view,
                'layout' => $layout,
                'data_type' => gettype($data),
                'data_keys' => is_array($data) ? array_keys($data) : []
            ], 'general');
            throw $e;
        }
        
        ob_start();
        try {
            // Verifica novamente antes de fazer extract
            if (!is_array($dataForLayout)) {
                error_log("BaseController::viewWithLayout - \$dataForLayout não é array na linha 116. Tipo: " . gettype($dataForLayout));
                error_log("BaseController::viewWithLayout - Conteúdo: " . var_export($dataForLayout, true));
                $dataForLayout = [];
            }
            extract($dataForLayout);
            include $layoutFile;
            ob_end_flush();
        } catch (Throwable $e) {
            ob_end_clean();
            require_once __DIR__ . '/../Core/Logger.php';
            Logger::error("Erro ao renderizar layout {$layout}: " . $e->getMessage(), [
                'exception' => $e,
                'view' => $view,
                'layout' => $layout,
                'data_type' => gettype($dataForLayout),
                'data_keys' => is_array($dataForLayout) ? array_keys($dataForLayout) : []
            ], 'general');
            throw $e;
        }
    }
    
    /**
     * Retorna JSON
     */
    protected function json($data, $statusCode = 200)
    {
        // Descarta todo output já gerado (evita misturar erros PHP/HTML com JSON)
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            Logger::error('Falha ao serializar resposta JSON no controller.', [
                'status_code' => $statusCode,
                'json_error' => json_last_error_msg(),
                'data_type' => gettype($data),
            ], 'general');
            $json = json_encode([
                'error' => 'Falha interna ao serializar a resposta do servidor.',
                'json_error' => json_last_error_msg(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        echo $json;
        exit;
    }
    
    /**
     * Desabilita cache do navegador (útil para telas de login e formulários sensíveis).
     * Também envia o ID do usuário autenticado para o HTMX detectar troca de sessão entre abas.
     */
    protected function disableCache()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: no-cache, private');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Vary: Cookie');

        $authUserId = (int) ($_SESSION['user_id'] ?? 0);
        $authUserType = (string) ($_SESSION['user_type'] ?? '');
        if ($authUserId > 0 && $authUserType !== '') {
            header('X-Educatudo-Auth-User: ' . $authUserType . ':' . $authUserId);
        }
    }

    /**
     * Redireciona para uma URL
     */
    protected function redirect($url, $statusCode = 302)
    {
        // Garantir que a URL seja absoluta
        if (strpos($url, 'http') === 0) {
            $redirectUrl = $url;
        } else {
            $redirectUrl = URL . $url;
        }
        
        http_response_code($statusCode);
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    /**
     * Valida dados de entrada
     */
    protected function validate($data, $rules)
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            if (strpos($rule, 'required') !== false && empty($value)) {
                $errors[$field] = "O campo {$field} é obrigatório";
            }
            
            if (strpos($rule, 'email') !== false && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "O campo {$field} deve ser um email válido";
            }
            
            if (strpos($rule, 'min:') !== false) {
                $min = (int) substr($rule, strpos($rule, 'min:') + 4);
                if (strlen($value) < $min) {
                    $errors[$field] = "O campo {$field} deve ter pelo menos {$min} caracteres";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Gera token CSRF. NÃO gera novo a cada request; NÃO sobrescreve token existente.
     * Token persiste durante a sessão (único por sessão, suporta múltiplas abas).
     */
    protected function generateCsrf()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Alias para compatibilidade com código existente.
     */
    protected function generateCsrfToken()
    {
        return $this->generateCsrf();
    }

    /**
     * Gera um novo token CSRF (sobrescreve o anterior). Usado quando o cliente envia token inválido
     * (ex.: sessão renovada noutra aba) para que a resposta devolva o token atual e o front atualize.
     */
    protected function refreshCsrfToken()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida token CSRF de forma segura (hash_equals).
     */
    protected function validateCsrf($token)
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Alias para compatibilidade com código existente.
     */
    protected function verifyCsrfToken($token)
    {
        return $this->validateCsrf($token);
    }
    
    /**
     * Define mensagem flash
     *
     * @param array<string,mixed>|null $meta Opcional: dados extras (ex.: debug para console no browser)
     */
    protected function setFlashMessage($message, $type = 'info', $meta = null)
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        if (is_array($meta)) {
            $_SESSION['flash_meta'] = $meta;
        } else {
            unset($_SESSION['flash_meta']);
        }
    }
    
    /**
     * Retorna mensagem flash
     *
     * @return array{message: ?string, type: string, meta: ?array}
     */
    protected function getFlashMessage()
    {
        $message = $_SESSION['flash_message'] ?? null;
        $type = $_SESSION['flash_type'] ?? 'info';
        $meta = $_SESSION['flash_meta'] ?? null;

        if ($message) {
            unset($_SESSION['flash_message'], $_SESSION['flash_type'], $_SESSION['flash_meta']);

            return [
                'message' => $message,
                'type' => $type,
                'meta' => is_array($meta) ? $meta : null,
            ];
        }

        if (is_array($meta)) {
            unset($_SESSION['flash_meta']);
        }

        return ['message' => null, 'type' => 'info', 'meta' => null];
    }
    
}
