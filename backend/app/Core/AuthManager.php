<?php
/**
 * EducaTudo - Gerenciador de Autenticação
 * Gerencia autenticação para diferentes tipos de usuários usando suas tabelas específicas
 */

class AuthManager
{
    private $db;
    private $config;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $configPath = defined('CONFIG_PATH') ? CONFIG_PATH : (dirname(__DIR__, 2) . '/config/app.php');
        $this->config = is_file($configPath) ? require $configPath : [];
        // Sessão é iniciada uma única vez em index.php; não iniciar aqui para evitar múltiplos starts.
    }

    /**
     * Retorna limites de tentativas e bloqueio por tipo.
     * Aluno: por nickname (5 tentativas, 5 min) para não afetar outros no mesmo IP da escola.
     * Outros: MAX_LOGIN_ATTEMPTS e LOCKOUT_DURATION do .env ou config.
     */
    private function getSecurityForType($tipo)
    {
        $sec = $this->config['security'] ?? [];
        $isAluno = ($tipo === 'aluno');
        if ($isAluno) {
            $max = (int) ($sec['max_login_attempts_aluno'] ?? $this->env('MAX_LOGIN_ATTEMPTS_ALUNO', '5'));
            $duration = (int) ($sec['lockout_duration_aluno'] ?? $this->env('LOCKOUT_DURATION_ALUNO', '300'));
        } else {
            $max = (int) ($sec['max_login_attempts'] ?? $this->env('MAX_LOGIN_ATTEMPTS', '30'));
            $duration = (int) ($sec['lockout_duration'] ?? $this->env('LOCKOUT_DURATION', '300'));
        }
        return ['max_login_attempts' => max(1, $max), 'lockout_duration' => max(60, $duration)];
    }

    private function env($key, $default = '')
    {
        $v = getenv($key);
        if ($v !== false && $v !== null && $v !== '') {
            return (string) $v;
        }
        $envPath = defined('ENV_FILE_PATH') ? ENV_FILE_PATH : (dirname(__DIR__, 2) . '/.env');
        $paths = [$envPath, dirname(__DIR__, 2) . '/.env', dirname(__DIR__, 3) . '/.env'];
        foreach ($paths as $p) {
            if (!is_file($p)) {
                continue;
            }
            $lines = @file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, $key . '=') !== 0) {
                    continue;
                }
                $value = trim(substr($line, strlen($key) + 1));
                if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                    $value = substr($value, 1, -1);
                }
                return $value !== '' ? $value : $default;
            }
        }
        return $default;
    }
    
    /**
     * Autenticar usuário baseado no tipo
     * Bloqueio por login/nickname (não por IP): evita que um aluno bloqueie outros no mesmo IP da escola.
     */
    public function authenticate($login, $senha, $tipo)
    {
        try {
            $tipoLogin = $tipo === 'admin' || $tipo === 'admin_escola' ? 'admin_escola' : $tipo;
            $security = $this->getSecurityForType($tipoLogin);
            $count = $this->getFailedAttemptsCount($login, $tipoLogin, $security['lockout_duration']);
            $allowUntil = max(1, $security['max_login_attempts'] - 1);
            if ($count >= $allowUntil) {
                $minutos = max(1, (int) ceil($security['lockout_duration'] / 60));
                throw new Exception("Muitas tentativas de login. Tente novamente em {$minutos} minutos.");
            }

            $user = null;
            
            switch ($tipo) {
                case 'admin':
                case 'admin_escola':
                    $user = $this->authenticateAdmin($login, $senha);
                    break;
                case 'aluno':
                    $user = $this->authenticateAluno($login, $senha);
                    break;
                case 'professor':
                    $user = $this->authenticateProfessor($login, $senha);
                    break;
                case 'pai':
                    $user = $this->authenticatePai($login, $senha);
                    break;
                case 'monitor':
                    $user = $this->authenticateMonitor($login, $senha);
                    break;
                default:
                    throw new Exception('Tipo de usuário inválido');
            }
            
            if (!$user) {
                throw new Exception('Credenciais inválidas');
            }
            
            // Sempre criar sessão (inclusive senha padrão 123456) para poder redirecionar à tela de alterar senha
            $this->createSession($user, $user['tipo']);
            
            return $user;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Autenticar admin (usa tabela usuarios)
     */
    private function authenticateAdmin($login, $senha)
    {
        $user = $this->db->fetch(
            "SELECT * FROM usuarios WHERE email = :login AND tipo = 'admin_escola'",
            ['login' => $login]
        );
        
        if (!$user) {
            $this->recordLoginAttempt($login, false, 'admin_escola', 'nickname_invalido');
            return null;
        }
        if (!password_verify($senha, $user['senha_hash'])) {
            $this->recordLoginAttempt($login, false, 'admin_escola', 'senha_invalida');
            return null;
        }
        
        return [
            'id' => $user['id'],
            'nome' => $user['nome'],
            'email' => $user['email'],
            'perfil_admin' => $user['perfil_admin'],
            'avatar_url' => $user['avatar_url'],
            'tipo' => 'admin'
        ];
    }
    
    /**
     * Autenticar aluno (usa tabela alunos). Bloqueio por nickname: 5 tentativas falhas = bloqueio por LOCKOUT_DURATION.
     */
    private function authenticateAluno($nickname, $senha)
    {
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome
             FROM alunos a 
             LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.nickname = :nickname AND a.ativo = 1",
            ['nickname' => $nickname]
        );
        
        if (!$aluno) {
            $this->recordLoginAttempt($nickname, false, 'aluno', 'nickname_invalido');
            return null;
        }
        if (!empty($aluno['primeiro_acesso'])) {
            throw new Exception('Primeiro acesso pendente. Use o fluxo de primeiro acesso.');
        }
        if (empty($aluno['senha_hash'])) {
            throw new Exception('Acesso não criado. Use o fluxo de primeiro acesso.');
        }
        if (!password_verify($senha, $aluno['senha_hash'])) {
            $this->recordLoginAttempt($nickname, false, 'aluno', 'senha_invalida');
            return null;
        }

        return [
            'id' => $aluno['id'],
            'nome' => $aluno['nome'],
            'email' => $aluno['email'],
            'ra' => $aluno['ra'],
            'turma_id' => $aluno['turma_id'],
            'turma_nome' => $aluno['turma_nome'],
            'serie' => $aluno['serie'],
            'data_nasc' => $aluno['data_nasc'],
            'tipo' => 'aluno'
        ];
    }
    
    /**
     * Autenticar professor (usa tabela professores)
     */
    private function authenticateProfessor($login, $senha)
    {
        $professor = $this->db->fetch(
            "SELECT * FROM professores 
             WHERE email = :login AND ativo = 1",
            ['login' => $login]
        );
        
        if (!$professor) {
            $this->recordLoginAttempt($login, false, 'professor', 'nickname_invalido');
            return null;
        }
        if (!password_verify($senha, $professor['senha_hash'])) {
            $this->recordLoginAttempt($login, false, 'professor', 'senha_invalida');
            return null;
        }
        $isDefaultPassword = password_verify('123456', $professor['senha_hash']);
        return [
            'id' => $professor['id'],
            'nome' => $professor['nome'],
            'email' => $professor['email'],
            'codigo_prof' => $professor['codigo_prof'],
            'materias' => $professor['materias'],
            'tipo' => 'professor',
            'is_default_password' => $isDefaultPassword
        ];
    }

    /**
     * Autenticar monitor de sala (tabela monitores)
     */
    private function authenticateMonitor($login, $senha)
    {
        $monitor = $this->db->fetch(
            "SELECT * FROM monitores WHERE email = :login AND ativo = 1",
            ['login' => $login]
        );

        if (!$monitor) {
            $this->recordLoginAttempt($login, false, 'monitor', 'nickname_invalido');
            return null;
        }
        if (!password_verify($senha, $monitor['senha_hash'])) {
            $this->recordLoginAttempt($login, false, 'monitor', 'senha_invalida');
            return null;
        }

        $isDefaultPassword = password_verify('123456', $monitor['senha_hash']);
        return [
            'id' => $monitor['id'],
            'nome' => $monitor['nome'],
            'email' => $monitor['email'],
            'turmas' => $monitor['turmas'],
            'tipo' => 'monitor',
            'is_default_password' => $isDefaultPassword,
        ];
    }
    
    /**
     * Autenticar pai (usa tabela responsaveis)
     */
    private function authenticatePai($login, $senha)
    {
        $cpfDigits = preg_replace('/\D+/', '', (string) $login);
        if ($cpfDigits === '') {
            $this->recordLoginAttempt($login, false, 'pai', 'cpf_invalido');
            return null;
        }

        $pai = $this->db->fetch(
            "SELECT * FROM responsaveis 
             WHERE REPLACE(REPLACE(REPLACE(COALESCE(cpf, ''), '.', ''), '-', ''), '/', '') = :cpf
               AND ativo = 1
             LIMIT 1",
            ['cpf' => $cpfDigits]
        );
        
        if (!$pai) {
            $this->recordLoginAttempt($login, false, 'pai', 'nickname_invalido');
            return null;
        }
        if (!password_verify($senha, $pai['senha_hash'])) {
            $this->recordLoginAttempt($login, false, 'pai', 'senha_invalida');
            return null;
        }
        return [
            'id' => $pai['id'],
            'nome' => $pai['nome'],
            'email' => $pai['email'],
            'cpf' => $pai['cpf'],
            'telefone' => $pai['telefone'],
            'tipo' => 'pai',
            'must_change_password' => (int)($pai['force_password_change'] ?? 0) === 1
        ];
    }
    
    /**
     * Criar sessão do usuário.
     * Regenera o ID da sessão primeiro para evitar qualquer dado de sessão anterior (troca de usuário).
     */
    private function createSession($user, $tipo)
    {
        if (php_sapi_name() !== 'cli') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
        }
        $_SESSION = [];

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $tipo;
        $_SESSION['user_name'] = $user['nome'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        switch ($tipo) {
            case 'admin':
                $_SESSION['perfil_admin'] = $user['perfil_admin'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['avatar_url'] = $user['avatar_url'] ?? null;
                break;
            case 'aluno':
                $_SESSION['ra'] = $user['ra'];
                $_SESSION['turma_id'] = $user['turma_id'];
                $_SESSION['turma_nome'] = $user['turma_nome'];
                $_SESSION['serie'] = $user['serie'];
                $this->registrarLoginAluno($user['id']);
                if (!class_exists('ContextoAluno')) {
                    require_once __DIR__ . '/ContextoAluno.php';
                }
                ContextoAluno::gravarSessaoNoLogin($this->db, (int) $user['id']);
                break;
            case 'professor':
                $_SESSION['email'] = $user['email'];
                $_SESSION['avatar_url'] = null;
                break;
            case 'pai':
                $_SESSION['email'] = $user['email'];
                $_SESSION['telefone'] = $user['telefone'];
                $_SESSION['must_change_password'] = !empty($user['must_change_password']) ? 1 : 0;
                break;
            case 'monitor':
                $_SESSION['email'] = $user['email'];
                $_SESSION['turmas'] = $user['turmas'] ?? null;
                break;
        }
    }
    
    /**
     * Registrar login do aluno
     */
    private function registrarLoginAluno($aluno_id)
    {
        try {
            $session_id = session_id();
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Finalizar sessões anteriores ativas do mesmo aluno
            $this->db->query(
                "UPDATE alunos_sessoes_acesso 
                 SET status = 'expirado', logout_at = NOW(), 
                     tempo_uso_segundos = TIMESTAMPDIFF(SECOND, login_at, NOW())
                 WHERE aluno_id = :aluno_id AND status = 'ativo'",
                ['aluno_id' => $aluno_id]
            );
            
            // Registrar nova sessão (ultima_atividade_at alinha monitor com presença online)
            $this->db->query(
                "INSERT INTO alunos_sessoes_acesso (aluno_id, session_id, ip_address, user_agent, status, ultima_atividade_at)
                 VALUES (:aluno_id, :session_id, :ip_address, :user_agent, 'ativo', NOW())",
                [
                    'aluno_id' => $aluno_id,
                    'session_id' => $session_id,
                    'ip_address' => $ip_address,
                    'user_agent' => $user_agent
                ]
            );
        } catch (Exception $e) {
        }
    }
    
    /**
     * Verificar se usuário está logado
     */
    public function isLoggedIn()
    {
        // Verificar se sessão está ativa
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !isset($_SESSION['user_name'])) {
            return false;
        }
        
        // Verificar se os valores da sessão são válidos
        if (empty($_SESSION['user_id']) || empty($_SESSION['user_type'])) {
            return false;
        }
        
        // Verificar se sessão expirou
        if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $this->config['session']['lifetime']) {
            $this->logout();
            return false;
        }
        
        // Atualizar última atividade
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Obter dados do usuário logado
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
            'perfil_admin' => $_SESSION['perfil_admin'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'avatar_url' => $_SESSION['avatar_url'] ?? null,
            'ra' => $_SESSION['ra'] ?? null,
            'turma_id' => $_SESSION['turma_id'] ?? null,
            'turma_nome' => $_SESSION['turma_nome'] ?? null,
            'serie' => $_SESSION['serie'] ?? null,
            'especialidade' => $_SESSION['especialidade'] ?? null,
            'telefone' => $_SESSION['telefone'] ?? null,
            'must_change_password' => $_SESSION['must_change_password'] ?? 0,
            'turmas' => $_SESSION['turmas'] ?? null,
        ];
    }
    
    /**
     * Fazer logout
     * Limpa sessão, destrói e remove o cookie de sessão para que a próxima página
     * (ex.: login de outro tipo) tenha sessão nova e CSRF válido.
     */
    public function logout()
    {
        // Registrar logout se for aluno
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'aluno' && isset($_SESSION['user_id'])) {
            $this->registrarLogoutAluno($_SESSION['user_id']);
        }

        if (!class_exists('ContextoAluno')) {
            require_once __DIR__ . '/ContextoAluno.php';
        }
        ContextoAluno::limparSessao();
        
        $_SESSION = [];
        
        if (php_sapi_name() !== 'cli') {
            session_destroy();
            // Remove o cookie de sessão para forçar nova sessão na próxima requisição.
            // Evita "Token inválido" ao trocar de portal (ex.: sair do admin e logar como aluno).
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                // Usar path '/' para garantir que o cookie seja removido em todo o site
                // (evita cookie restante em /admin que faria Ctrl+F5 voltar ao dashboard)
                $path = ($params['path'] ?? '') !== '' ? $params['path'] : '/';
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $path,
                    $params['domain'] ?? '',
                    (bool) ($params['secure'] ?? false),
                    (bool) ($params['httponly'] ?? true)
                );
            }
        }
    }
    
    /**
     * Registrar logout do aluno e calcular tempo de uso
     */
    private function registrarLogoutAluno($aluno_id)
    {
        try {
            $session_id = session_id();
            
            // Primeiro, tentar atualizar pela session_id atual (mais específico)
            $rows_updated = $this->db->query(
                "UPDATE alunos_sessoes_acesso 
                 SET status = 'finalizado', 
                     logout_at = NOW(),
                     tempo_uso_segundos = TIMESTAMPDIFF(SECOND, login_at, NOW())
                 WHERE aluno_id = :aluno_id AND session_id = :session_id AND status = 'ativo'",
                [
                    'aluno_id' => $aluno_id,
                    'session_id' => $session_id
                ]
            );
            
            // Se não encontrou pela session_id, atualizar todas as sessões ativas do aluno
            // (isso cobre casos onde a sessão pode ter sido regenerada ou há múltiplas sessões)
            $this->db->query(
                "UPDATE alunos_sessoes_acesso 
                 SET status = 'finalizado', 
                     logout_at = NOW(),
                     tempo_uso_segundos = TIMESTAMPDIFF(SECOND, login_at, NOW())
                 WHERE aluno_id = :aluno_id AND status = 'ativo' AND logout_at IS NULL",
                [
                    'aluno_id' => $aluno_id
                ]
            );
        } catch (Exception $e) {
        }
    }
    
    /**
     * Buscar alunos online (sessão ativa com atividade nos últimos 30 minutos).
     * @param array|null $turmaIds Se informado, filtra por turmas
     */
    public function getAlunosOnline(?array $turmaIds = null)
    {
        try {
            $turmaIds = $this->normalizeTurmaIdsFilter($turmaIds);
            $cacheKey = $turmaIds === null ? 'all' : implode(',', $turmaIds);
            static $cached = [];
            static $lastFetch = [];
            static $lastCleanup = 0;

            $now = time();
            if (isset($cached[$cacheKey]) && isset($lastFetch[$cacheKey]) && ($now - $lastFetch[$cacheKey]) < 10) {
                return $cached[$cacheKey];
            }

            if (($now - $lastCleanup) >= 120) {
                $this->db->query(
                    "UPDATE alunos_sessoes_acesso 
                     SET status = 'expirado', 
                         logout_at = DATE_ADD(login_at, INTERVAL 30 MINUTE),
                         tempo_uso_segundos = TIMESTAMPDIFF(SECOND, login_at, DATE_ADD(login_at, INTERVAL 30 MINUTE))
                     WHERE status = 'ativo' 
                     AND logout_at IS NULL
                     AND login_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
                );
                $lastCleanup = $now;
            }

            $params = [];
            $turmaFilter = '';
            if ($turmaIds !== null && !empty($turmaIds)) {
                $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
                $turmaFilter = " AND a.turma_id IN ($placeholders)";
                $params = $turmaIds;
            }

            $data = $this->db->fetchAll(
                "SELECT s.*, a.nome, a.ra, a.turma_id, t.nome as turma_nome,
                        TIMESTAMPDIFF(SECOND, s.login_at, NOW()) as tempo_online_segundos
                 FROM alunos_sessoes_acesso s
                 INNER JOIN alunos a ON s.aluno_id = a.id
                 LEFT JOIN turmas t ON a.turma_id = t.id
                 WHERE s.status = 'ativo' 
                 AND s.logout_at IS NULL
                 AND COALESCE(s.ultima_atividade_at, s.login_at) >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                 {$turmaFilter}
                 ORDER BY COALESCE(s.ultima_atividade_at, s.login_at) DESC",
                $params
            );

            $data = $this->deduplicarAlunosOnlinePorAluno($data);

            $cached[$cacheKey] = $data;
            $lastFetch[$cacheKey] = $now;
            return $data;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Normaliza lista de turmas para filtro (ordem estável para cache).
     *
     * @param array|null $turmaIds
     * @return array|null null = sem filtro de turma
     */
    private function normalizeTurmaIdsFilter(?array $turmaIds): ?array
    {
        if ($turmaIds === null) {
            return null;
        }
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        sort($turmaIds);
        return $turmaIds;
    }

    /**
     * Mantém apenas a sessão mais recente de cada aluno.
     */
    private function deduplicarAlunosOnlinePorAluno(array $rows): array
    {
        $byAluno = [];
        foreach ($rows as $row) {
            $alunoId = (int) ($row['aluno_id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            $atividade = strtotime((string) ($row['ultima_atividade_at'] ?? $row['login_at'] ?? '')) ?: 0;
            if (!isset($byAluno[$alunoId]) || $atividade > ($byAluno[$alunoId]['_atividade_ts'] ?? 0)) {
                $row['_atividade_ts'] = $atividade;
                $byAluno[$alunoId] = $row;
            }
        }

        $result = array_values($byAluno);
        foreach ($result as &$item) {
            unset($item['_atividade_ts']);
        }
        unset($item);

        return $result;
    }

    /**
     * Atualiza heartbeat e contexto de atividade do aluno na sessão ativa.
     */
    public function atualizarPresencaAluno($alunoId, array $context = [])
    {
        try {
            $sessionId = session_id();
            $tipo = isset($context['contexto_tipo']) ? trim((string) $context['contexto_tipo']) : null;
            $contextoId = isset($context['contexto_id']) ? (int) $context['contexto_id'] : null;
            $label = isset($context['contexto_label']) ? trim((string) $context['contexto_label']) : null;
            if ($label !== null && strlen($label) > 500) {
                $label = substr($label, 0, 500);
            }

            $this->db->query(
                "UPDATE alunos_sessoes_acesso
                 SET ultima_atividade_at = NOW(),
                     contexto_tipo = :contexto_tipo,
                     contexto_id = :contexto_id,
                     contexto_label = :contexto_label
                 WHERE aluno_id = :aluno_id
                 AND status = 'ativo'
                 AND logout_at IS NULL
                 AND (session_id = :session_id OR session_id IS NOT NULL)",
                [
                    'aluno_id' => (int) $alunoId,
                    'session_id' => $sessionId,
                    'contexto_tipo' => $tipo !== '' ? $tipo : null,
                    'contexto_id' => $contextoId > 0 ? $contextoId : null,
                    'contexto_label' => $label !== '' ? $label : null,
                ]
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verificar token CSRF
     */
    public function verifyCsrfToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Retorna a quantidade de falhas do login na janela de bloqueio (por nickname/login, não por IP).
     * Em erro (ex.: tabela inexistente), retorna 999 para bloquear em vez de permitir.
     */
    private function getFailedAttemptsCount($login, $tipo, $lockoutDuration = null)
    {
        if ($lockoutDuration === null) {
            $lockoutDuration = $this->getSecurityForType($tipo)['lockout_duration'];
        }
        $duration = (int) $lockoutDuration;
        try {
            $sql = "SELECT COUNT(*) as attempts FROM tentativas_login 
                    WHERE email = :login AND success = 0 
                    AND created_at > DATE_SUB(NOW(), INTERVAL :duration SECOND)";
            $params = ['login' => $login, 'duration' => $duration];
            if ($this->hasTentativasLoginTipoColumn()) {
                $sql = "SELECT COUNT(*) as attempts FROM tentativas_login 
                        WHERE email = :login AND success = 0 AND (tipo = :tipo OR tipo IS NULL)
                        AND created_at > DATE_SUB(NOW(), INTERVAL :duration SECOND)";
                $params['tipo'] = $tipo;
            }
            $result = $this->db->fetch($sql, $params);
            return (int) ($result['attempts'] ?? 0);
        } catch (Exception $e) {
            return 999;
        }
    }

    /**
     * Verifica se o login/nickname pode tentar (por identificador digitado, não por IP).
     */
    private function canAttemptLogin($login, $tipo)
    {
        $security = $this->getSecurityForType($tipo);
        $allowUntil = max(1, $security['max_login_attempts'] - 1);
        return $this->getFailedAttemptsCount($login, $tipo, $security['lockout_duration']) < $allowUntil;
    }

    private function hasTentativasLoginTipoColumn()
    {
        static $has = null;
        if ($has === null) {
            try {
                $cols = $this->db->fetchAll("SHOW COLUMNS FROM tentativas_login LIKE 'tipo'");
                $has = !empty($cols);
            } catch (Exception $e) {
                $has = false;
            }
        }
        return $has;
    }

    /**
     * Registra tentativa de login (sucesso ou falha com motivo).
     * Bloqueio por nickname/login: nickname errado também conta e bloqueia só aquele nickname (não o IP).
     */
    private function recordLoginAttempt($login, $success, $tipo, $motivoFalha = null)
    {
        $security = $this->getSecurityForType($tipo);
        $maxAttempts = $security['max_login_attempts'];
        $lockoutDuration = $security['lockout_duration'];
        $allowUntil = max(1, $maxAttempts - 1);

        if (!$success) {
            $count = $this->getFailedAttemptsCount($login, $tipo, $lockoutDuration);
            if ($count >= $allowUntil) {
                $minutos = max(1, (int) ceil($lockoutDuration / 60));
                throw new Exception("Muitas tentativas de login. Tente novamente em {$minutos} minutos.");
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        try {
            if ($this->hasTentativasLoginTipoColumn()) {
                $this->db->query(
                    "INSERT INTO tentativas_login (email, ip_address, success, tipo, motivo_falha) VALUES (:email, :ip, :success, :tipo, :motivo)",
                    [
                        'email' => $login,
                        'ip' => $ip,
                        'success' => $success ? 1 : 0,
                        'tipo' => $tipo,
                        'motivo' => $motivoFalha
                    ]
                );
            } else {
                $this->db->query(
                    "INSERT INTO tentativas_login (email, ip_address, success) VALUES (:email, :ip, :success)",
                    ['email' => $login, 'ip' => $ip, 'success' => $success ? 1 : 0]
                );
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Muitas tentativas') !== false) {
                throw $e;
            }
        }
    }
}
