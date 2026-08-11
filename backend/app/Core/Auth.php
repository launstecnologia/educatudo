<?php
/**
 * EducaTudo - Sistema de Autenticação
 * Gerencia login, sessões e segurança
 */

class Auth
{
    private $db;
    private $config;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        
        $configFile = __DIR__ . '/../../config/app.php';
        if (!file_exists($configFile)) {
            throw new Exception("Arquivo de configuração não encontrado: {$configFile}");
        }
        
        $this->config = require $configFile;
        
        if (!$this->config || !is_array($this->config)) {
            throw new Exception("Configuração inválida");
        }
        
        $this->startSession();
    }
    
    /**
     * Inicia sessão com configurações seguras
     */
    private function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!isset($this->config['session'])) {
                throw new Exception("Configuração de sessão não encontrada");
            }
            
            $sessionConfig = $this->config['session'];
            
            session_name($sessionConfig['name'] ?? 'EDUCATUDO_SESSION');
            
            $options = [
                'cookie_lifetime' => $sessionConfig['lifetime'] ?? 3600,
                'cookie_secure' => $sessionConfig['secure'] ?? false,
                'cookie_httponly' => $sessionConfig['httponly'] ?? true,
                'cookie_samesite' => $sessionConfig['samesite'] ?? 'Lax',
                'use_strict_mode' => true,
                'sid_length' => 48,
                'sid_bits_per_character' => 6
            ];
            
            session_start($options);
            
            // Não regenera ID aqui para evitar problemas
            // A regeneração será feita apenas no login
        }
    }
    
    /**
     * Autentica usuário
     */
    public function login($login, $senha, $tipo = null)
    {
        try {
            // Verifica tentativas de login
            if (!$this->canAttemptLogin($login)) {
                throw new Exception("Muitas tentativas de login. Tente novamente em " . $this->getLockoutTime() . " minutos.");
            }
            
            // Busca usuário baseado no tipo
            $user = $this->findUserByLogin($login, $tipo);
            
            if (!$user) {
                $this->recordLoginAttempt($login, false);
                throw new Exception("Credenciais inválidas");
            }
            
            // Verifica senha
            if (!password_verify($senha, $user['senha_hash'])) {
                $this->recordLoginAttempt($login, false);
                throw new Exception("Credenciais inválidas");
            }
            
            // Verifica se usuário está ativo
            if (!$this->isUserActive($user)) {
                throw new Exception("Usuário inativo");
            }
            
            // Registra tentativa bem-sucedida
            $this->recordLoginAttempt($login, true);
            
            // Cria sessão
            $this->createSession($user);
            
            return $user;
            
        } catch (Exception $e) {
            error_log("Erro de login: " . $e->getMessage() . " - Login: " . $login);
            throw $e;
        }
    }
    
    /**
     * Busca usuário por login baseado no tipo
     */
    private function findUserByLogin($login, $tipo = null)
    {
        if ($tipo === 'aluno') {
            // Aluno usa RA
            $sql = "SELECT u.*, a.ra, a.turma_id, a.serie 
                    FROM usuarios u 
                    JOIN alunos a ON u.id = a.usuario_id 
                    WHERE a.ra = :login AND u.tipo = 'aluno'";
        } elseif ($tipo === 'professor') {
            // Professor usa código
            $sql = "SELECT u.*, p.codigo_prof, p.materias 
                    FROM usuarios u 
                    JOIN professores p ON u.id = p.usuario_id 
                    WHERE p.codigo_prof = :login AND u.tipo = 'professor'";
        } else {
            // Admin e pais usam email
            $sql = "SELECT u.* FROM usuarios u WHERE u.email = :login";
            if ($tipo) {
                $sql .= " AND u.tipo = :tipo";
            }
        }
        
        $params = ['login' => $login];
        if ($tipo && $tipo !== 'aluno' && $tipo !== 'professor') {
            $params['tipo'] = $tipo;
        }
        
        return $this->db->fetch($sql, $params);
    }
    
    /**
     * Verifica se usuário está ativo
     */
    private function isUserActive($user)
    {
        switch ($user['tipo']) {
            case 'aluno':
                $sql = "SELECT ativo FROM alunos WHERE usuario_id = :id";
                break;
            case 'professor':
                $sql = "SELECT ativo FROM professores WHERE usuario_id = :id";
                break;
            case 'pai':
                $sql = "SELECT ativo FROM responsaveis WHERE usuario_id = :id";
                break;
            case 'admin_escola':
                return true; // Admin sempre ativo
            default:
                return false;
        }
        
        $result = $this->db->fetch($sql, ['id' => $user['id']]);
        return $result ? $result['ativo'] : false;
    }
    
    /**
     * Cria sessão do usuário
     */
    private function createSession($user)
    {
        $preservedCsrf = $_SESSION['csrf_token'] ?? null;
        $_SESSION = [];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['tipo'];
        $_SESSION['user_name'] = $user['nome'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        if ($preservedCsrf !== null) {
            $_SESSION['csrf_token'] = $preservedCsrf;
        }
        // Dados específicos por tipo
        if ($user['tipo'] === 'admin_escola') {
            $_SESSION['perfil_admin'] = $user['perfil_admin'];
        }
        
        // Regenera ID da sessão ANTES de registrar no banco (apenas se não estivermos em CLI)
        if (php_sapi_name() !== 'cli') {
            session_regenerate_id(true);
        }
        
        // Registra sessão no banco com o novo ID
        $this->registerSession($user['id']);
    }
    
    /**
     * Registra sessão no banco de dados
     */
    private function registerSession($userId)
    {
        $sessionId = session_id();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['session']['lifetime']);
        
        $sql = "INSERT INTO sessoes (id, usuario_id, ip_address, user_agent, expires_at) 
                VALUES (:id, :user_id, :ip, :user_agent, :expires_at)
                ON DUPLICATE KEY UPDATE 
                ip_address = VALUES(ip_address), user_agent = VALUES(user_agent), expires_at = VALUES(expires_at)";
        
        $this->db->query($sql, [
            'id' => $sessionId,
            'user_id' => $userId,
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt
        ]);
    }
    
    /**
     * Verifica se usuário está logado
     */
    public function isLoggedIn()
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Verifica se sessão expirou
        if (time() - $_SESSION['last_activity'] > $this->config['session']['lifetime']) {
            $this->logout();
            return false;
        }
        
        // Atualiza última atividade
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Retorna dados do usuário logado
     */
    public function getUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'tipo' => $_SESSION['user_type'],
            'nome' => $_SESSION['user_name'],
            'perfil_admin' => $_SESSION['perfil_admin'] ?? null
        ];
    }
    
    /**
     * Verifica se usuário tem permissão
     */
    public function hasPermission($permission)
    {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }
        
        // Admin escola tem todas as permissões
        if ($user['tipo'] === 'admin_escola') {
            return true;
        }
        
        // Outras verificações de permissão podem ser adicionadas aqui
        return true;
    }
    
    /**
     * Faz logout
     */
    public function logout()
    {
        if (isset($_SESSION['user_id'])) {
            // Remove sessão do banco
            $this->db->query("DELETE FROM sessoes WHERE id = :id", ['id' => session_id()]);
        }
        
        // Limpa sessão
        $_SESSION = [];
        session_destroy();
        
        // Inicia nova sessão
        session_start();
    }
    
    /**
     * Verifica se pode tentar login
     */
    private function canAttemptLogin($login)
    {
        $securityConfig = $this->config['security'];
        $maxAttempts = $securityConfig['max_login_attempts'];
        $lockoutDuration = $securityConfig['lockout_duration'];
        
        $sql = "SELECT COUNT(*) as attempts 
                FROM tentativas_login 
                WHERE email = :login 
                AND success = 0 
                AND created_at > DATE_SUB(NOW(), INTERVAL :duration SECOND)";
        
        $result = $this->db->fetch($sql, [
            'login' => $login,
            'duration' => $lockoutDuration
        ]);
        
        return $result['attempts'] < $maxAttempts;
    }
    
    /**
     * Registra tentativa de login
     */
    private function recordLoginAttempt($login, $success)
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $sql = "INSERT INTO tentativas_login (email, ip_address, success) VALUES (:email, :ip, :success)";
        $this->db->query($sql, [
            'email' => $login,
            'ip' => $ipAddress,
            'success' => $success ? 1 : 0
        ]);
    }
    
    /**
     * Retorna tempo de bloqueio restante
     */
    private function getLockoutTime()
    {
        $lockoutDuration = $this->config['security']['lockout_duration'];
        return ceil($lockoutDuration / 60);
    }
    
    /**
     * Gera hash de senha
     */
    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Valida força da senha (padrão - para alunos)
     */
    public function validatePassword($password)
    {
        $minLength = $this->config['security']['password_min_length'];
        
        if (strlen($password) < $minLength) {
            return "Senha deve ter pelo menos {$minLength} caracteres";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return "Senha deve conter pelo menos uma letra maiúscula";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return "Senha deve conter pelo menos uma letra minúscula";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return "Senha deve conter pelo menos um número";
        }
        
        return true;
    }
    
    /**
     * Valida senha forte (para professores e admins)
     * Requer: maiúscula, minúscula, número e caractere especial
     */
    public function validateStrongPassword($password)
    {
        $minLength = $this->config['security']['password_min_length'];
        
        if (strlen($password) < $minLength) {
            return "Senha deve ter pelo menos {$minLength} caracteres";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return "Senha deve conter pelo menos uma letra maiúscula";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return "Senha deve conter pelo menos uma letra minúscula";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return "Senha deve conter pelo menos um número";
        }
        
        // Caractere especial: !@#$%^&*()_+-=[]{}|;:,.<>?
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/', $password)) {
            return "Senha deve conter pelo menos um caractere especial (!@#$%^&*()_+-=[]{}|;:,.<>?)";
        }
        
        return true;
    }
}
