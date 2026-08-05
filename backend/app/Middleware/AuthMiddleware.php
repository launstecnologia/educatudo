<?php
/**
 * EducaTudo - Middleware de Autenticação
 * Verifica se usuário está logado
 */

class AuthMiddleware
{
    private $auth;
    private $db;
    
    public function __construct()
    {
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
    }
    
    /**
     * Executa middleware
     */
    public function handle()
    {
        if (!$this->auth->isLoggedIn()) {
            // Limpar sessão corrompida antes de redirecionar
            try {
                $this->auth->logout();
            } catch (Exception $e) {
                // Logar erro no sistema de logs
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../Core/Logger.php';
                }
                Logger::authError(
                    "Erro ao limpar sessão no middleware: " . $e->getMessage(),
                    ['exception' => $e]
                );
                
                error_log("Erro ao limpar sessão no middleware: " . $e->getMessage());
                // Limpar sessão manualmente
                session_unset();
                session_destroy();
                session_start();
            }
            // Requisições AJAX/fetch que esperam JSON: retornar 401 JSON em vez de redirect (evita "HTML em vez de JSON" no envio de resumo/jornadas)
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            $acceptsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
            if ($isAjax || $acceptsJson) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $this->redirectToLogin();
        }

        $user = $this->auth->getUser();
        $appliedCustomPermissions = false;
        if ($user && in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $appliedCustomPermissions = $this->applyCustomAdminPermissions($user);
        }

        if (
            !$appliedCustomPermissions
            && $user
            && in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)
            && ($user['perfil_admin'] ?? '') === 'financeiro'
        ) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
            $allowedPrefix = '/admin/financeiro';
            if (strpos($path, $allowedPrefix) !== 0 && $path !== '/logout') {
                http_response_code(302);
                header('Location: ' . URL . $allowedPrefix);
                exit;
            }
        }

        if (!class_exists('AdminSecretariaAccess')) {
            require_once __DIR__ . '/../Core/AdminSecretariaAccess.php';
        }
        if (AdminSecretariaAccess::isSecretaria($user)) {
            $reqUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            if (!AdminSecretariaAccess::requestPathIsAllowed($reqUri)) {
                http_response_code(302);
                header('Location: ' . URL . AdminSecretariaAccess::homePath());
                exit;
            }
        }

        // Aceite é tratado no dashboard com modal bloqueante
    }
    
    /**
     * Redireciona para login baseado na URL atual
     */
    private function redirectToLogin()
    {
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        
        // Determina qual página de login usar baseado na URL
        if (strpos($currentPath, '/admin') !== false) {
            $loginUrl = URL . '/admin';
        } elseif (strpos($currentPath, '/professor') !== false) {
            $loginUrl = URL . '/professor';
        } elseif (strpos($currentPath, '/pais') !== false) {
            $loginUrl = URL . '/pais';
        } else {
            $loginUrl = URL . '/'; // Login do aluno
        }
        
        http_response_code(302);
        header('Location: ' . $loginUrl);
        exit;
    }

    private function applyCustomAdminPermissions(array $user): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        if (!class_exists('AdminPermissionMatrix')) {
            require_once __DIR__ . '/../Core/AdminPermissionMatrix.php';
        }

        try {
            $hasPerfis = AdminPermissionMatrix::adminPerfisPermissaoTableExists($this->db);
            if ($hasPerfis) {
                $row = $this->db->fetch(
                    'SELECT u.permissoes_admin_json, u.perfil_permissao_id, p.permissoes_json AS perfil_permissoes_json
                     FROM usuarios u
                     LEFT JOIN admin_perfis_permissao p ON p.id = u.perfil_permissao_id AND p.ativo = 1
                     WHERE u.id = ? LIMIT 1',
                    [$userId]
                );
            } else {
                $row = $this->db->fetch(
                    'SELECT u.permissoes_admin_json, u.perfil_permissao_id, NULL AS perfil_permissoes_json
                     FROM usuarios u
                     WHERE u.id = ? LIMIT 1',
                    [$userId]
                );
            }
        } catch (\Throwable $e) {
            return false;
        }

        if (!is_array($row)) {
            return false;
        }
        $rawJson = trim((string) ($row['permissoes_admin_json'] ?? ''));
        if ($rawJson === '') {
            $rawJson = trim((string) ($row['perfil_permissoes_json'] ?? ''));
        }
        if ($rawJson === '') {
            return false;
        }

        $decoded = json_decode($rawJson, true);
        $permissions = AdminPermissionMatrix::sanitizePermissions($decoded);
        if ($permissions === []) {
            return false;
        }

        $resolved = AdminPermissionMatrix::resolveModuleAndAction((string) ($_SERVER['REQUEST_URI'] ?? ''), (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $moduleKey = $resolved['module_key'] ?? null;
        $actionKey = $resolved['action_key'] ?? null;
        if ($moduleKey === null || $actionKey === null) {
            return true;
        }

        if (!empty($permissions[$moduleKey][$actionKey])) {
            return true;
        }

        http_response_code(302);
        header('Location: ' . URL . '/admin/dashboard');
        exit;
    }

}
