<?php
/**
 * EducaTudo - Middleware de Trilha de Auditoria
 * Registra automaticamente ações sensíveis dos usuários
 */

class AuditMiddleware
{
    private $authManager;
    
    // Rotas GET críticas que devem ser registradas
    private $criticalGetRoutes = [
        '/aluno/provas/realizar',
        '/aluno/provas/resultado',
        '/professor/provas/visualizar',
        '/admin/provas/visualizar',
        '/admin/students',
        '/admin/teachers',
        '/admin/reports',
        '/professor/student',
        '/professor/relatorios',
        '/admin/relatorios'
    ];
    
    public function __construct()
    {
        if (!class_exists('AuthManager')) {
            require_once __DIR__ . '/../Core/AuthManager.php';
        }
        $this->authManager = new AuthManager();
    }
    
    /**
     * Executa o middleware de auditoria
     */
    public function handle()
    {
        try {
            // Carregar Logger se necessário
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../Core/Logger.php';
            }
            
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            
            // Remover query string da URI para comparação
            $uriPath = parse_url($uri, PHP_URL_PATH);
            
            // Determinar se deve registrar esta requisição
            $shouldLog = $this->shouldLogRequest($method, $uriPath);
            
            if ($shouldLog) {
                // Obter informações do usuário
                $user = $this->authManager->getUser();
                $userId = $user ? $user['id'] : null;
                $userRole = $user ? $user['tipo'] : null;
                
                // Determinar ação baseada no método HTTP e URI
                $action = $this->determineAction($method, $uriPath);
                
                // Obter payload da requisição (apenas para POST, PUT, DELETE)
                $requestPayload = [];
                if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
                    $requestPayload = $_POST;
                    
                    // Se for PUT ou DELETE, pode ter dados em php://input
                    if (in_array($method, ['PUT', 'DELETE']) && empty($requestPayload)) {
                        $input = file_get_contents('php://input');
                        if (!empty($input)) {
                            $parsed = json_decode($input, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $requestPayload = $parsed;
                            }
                        }
                    }
                }
                
                // Registrar na trilha de auditoria
                Logger::logAudit(
                    $action,
                    $uriPath,
                    $requestPayload,
                    $userId,
                    $userRole,
                    null // IP será obtido automaticamente pelo Logger
                );
            }
            
        } catch (Exception $e) {
            // Não interromper a requisição se houver erro no middleware
            error_log("Erro no AuditMiddleware: " . $e->getMessage());
        } catch (Throwable $e) {
            error_log("Erro fatal no AuditMiddleware: " . $e->getMessage());
        }
    }
    
    /**
     * Determina se a requisição deve ser registrada
     * 
     * @param string $method Método HTTP
     * @param string $uriPath Caminho da URI
     * @return bool
     */
    private function shouldLogRequest($method, $uriPath)
    {
        // Sempre registrar POST, PUT, DELETE
        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            return true;
        }
        
        // Para GET, verificar se é rota crítica
        if ($method === 'GET') {
            foreach ($this->criticalGetRoutes as $criticalRoute) {
                if (strpos($uriPath, $criticalRoute) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Determina a ação baseada no método HTTP e URI
     * 
     * @param string $method Método HTTP
     * @param string $uriPath Caminho da URI
     * @return string Ação formatada
     */
    private function determineAction($method, $uriPath)
    {
        // Mapear métodos HTTP para ações
        $methodActions = [
            'POST' => 'CREATE',
            'PUT' => 'UPDATE',
            'DELETE' => 'DELETE',
            'GET' => 'VIEW'
        ];
        
        $baseAction = $methodActions[$method] ?? 'ACCESS';
        
        // Extrair contexto da URI para ação mais específica
        $action = $this->extractActionFromUri($uriPath, $baseAction);
        
        return $action;
    }
    
    /**
     * Extrai ação específica da URI
     * 
     * @param string $uriPath Caminho da URI
     * @param string $baseAction Ação base
     * @return string Ação específica
     */
    private function extractActionFromUri($uriPath, $baseAction)
    {
        // Mapear padrões de URI para ações específicas
        $uriPatterns = [
            '/login' => 'LOGIN',
            '/logout' => 'LOGOUT',
            '/provas/realizar' => 'START_EXAM',
            '/provas/finalizar' => 'SUBMIT_EXAM',
            '/provas/resultado' => 'VIEW_EXAM_RESULT',
            '/provas/criar' => 'CREATE_EXAM',
            '/provas/editar' => 'EDIT_EXAM',
            '/provas/excluir' => 'DELETE_EXAM',
            '/students/create' => 'CREATE_STUDENT',
            '/students/{id}/edit' => 'EDIT_STUDENT',
            '/students/{id}' => 'VIEW_STUDENT',
            '/teachers/create' => 'CREATE_TEACHER',
            '/teachers/{id}/edit' => 'EDIT_TEACHER',
            '/redacoes/corrigir' => 'GRADE_ESSAY',
            '/redacoes/criar' => 'CREATE_ESSAY',
            '/exercicios/responder' => 'SUBMIT_EXERCISE',
            '/exercicios/criar' => 'CREATE_EXERCISE',
            '/jornadas/criar' => 'CREATE_JOURNEY',
            '/jornadas/{id}/editar' => 'EDIT_JOURNEY',
            '/reports' => 'VIEW_REPORT',
            '/relatorios' => 'VIEW_REPORT',
            '/notifications/create' => 'CREATE_NOTIFICATION',
            '/settings' => 'UPDATE_SETTINGS',
            '/configuracoes' => 'UPDATE_SETTINGS'
        ];
        
        // Verificar padrões específicos
        foreach ($uriPatterns as $pattern => $action) {
            // Substituir {id} por regex
            $regexPattern = str_replace('{id}', '[^/]+', preg_quote($pattern, '/'));
            if (preg_match('/' . $regexPattern . '/', $uriPath)) {
                return $action;
            }
        }
        
        // Se não encontrou padrão específico, usar ação base com contexto
        $parts = explode('/', trim($uriPath, '/'));
        if (count($parts) > 0) {
            $resource = strtoupper($parts[0]);
            return $baseAction . '_' . $resource;
        }
        
        return $baseAction;
    }
}

