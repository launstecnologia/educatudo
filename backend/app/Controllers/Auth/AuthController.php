<?php
/**
 * EducaTudo - Controller de Autenticação
 * Gerencia login, logout e recuperação de senha
 */

if (!class_exists('AuthController')) {
    class AuthController extends BaseController
{
    private $authManager;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
    }
    
    /** Nome do cookie CSRF de login (double-submit: enviado no POST mesmo se sessão mudar) */
    const LOGIN_CSRF_COOKIE = 'login_csrf';

    /**
     * Exibe a tela de login (fluxo GET exclusivo).
     * - disableCache()
     * - generateCsrf() (só cria token se não existir; nunca altera existente)
     * - passa token para a view
     * GET nunca altera token existente.
     */
    private function showLogin($tipo)
    {
        $this->disableCache();
        $token = $this->generateCsrfToken();
        $this->setLoginCsrfCookie($token);
        // Só exibir flash se vier de redirect nosso (query t=); senão limpar flash antigo para não persistir ao sair/voltar
        $hasCacheBust = isset($_GET['t']) && is_numeric($_GET['t']);
        if (!$hasCacheBust && isset($_SESSION['flash_message'])) {
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        }

        $titles = [
            'aluno' => 'Login Aluno - EducaTudo',
            'admin' => 'Login Administração - EducaTudo',
            'professor' => 'Login Professor - EducaTudo',
            'pais' => 'Login Pais - EducaTudo',
            'monitor' => 'Login Monitor - EducaTudo',
        ];
        $viewName = 'auth/login-' . $tipo;

        $data = [
            'title' => $titles[$tipo] ?? 'Login - EducaTudo',
            'csrf_token' => $token,
            'flash' => $this->getFlashMessage(),
        ];
        $this->view($viewName, $data);
    }

    /**
     * Token CSRF para tela de login (double-submit cookie). Usado apenas por showLogin().
     */
    private function getLoginCsrfToken()
    {
        $token = $this->generateCsrfToken();
        $this->setLoginCsrfCookie($token);
        return $token;
    }

    private function isSecureCookieRequest(): bool
    {
        if (function_exists('educatudoDetectHttps')) {
            return educatudoDetectHttps();
        }
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    private function setLoginCsrfCookie($token)
    {
        if (php_sapi_name() === 'cli') {
            return;
        }
        $opts = [
            'expires' => time() + 600,
            'path' => '/',
            'secure' => $this->isSecureCookieRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie(self::LOGIN_CSRF_COOKIE, $token, $opts);
    }

    private function clearLoginCsrfCookie()
    {
        if (php_sapi_name() === 'cli') {
            return;
        }
        $opts = [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->isSecureCookieRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie(self::LOGIN_CSRF_COOKIE, '', $opts);
    }

    /**
     * Valida CSRF do login: sessão OU cookie (double-submit). Cookie é aceito quando a sessão
     * mudou entre GET e POST (outra aba, load balancer). Cookie é de uso único (limpo após validar).
     */
    private function verifyLoginCsrfToken($postToken)
    {
        if ($postToken === '' || strlen($postToken) !== 64) {
            return false;
        }
        if ($this->verifyCsrfToken($postToken)) {
            return true;
        }
        $cookieToken = $_COOKIE[self::LOGIN_CSRF_COOKIE] ?? '';
        if ($cookieToken !== '' && hash_equals($cookieToken, $postToken)) {
            $this->clearLoginCsrfCookie();
            return true;
        }
        return false;
    }

    /**
     * Determina o motivo exato da falha de CSRF no login (para log).
     */
    private function getLoginCsrfFailureReason($postToken)
    {
        $tipo = trim($_POST['tipo'] ?? '');
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sid = session_id();
        $sidMask = $sid !== '' ? substr($sid, 0, 4) . '...' . substr($sid, -4) : 'vazio';

        if ($postToken === '') {
            return [
                'motivo' => 'token_nao_enviado',
                'descricao' => 'Campo _token vazio ou não enviado no POST',
                'tipo' => $tipo,
                'uri' => $uri,
                'ip' => $ip,
                'user_agent' => $ua,
                'session_id_masked' => $sidMask,
                'sessao_tem_csrf' => isset($_SESSION['csrf_token']),
            ];
        }
        if (!isset($_SESSION['csrf_token'])) {
            return [
                'motivo' => 'sessao_sem_csrf',
                'descricao' => 'Sessão não possui csrf_token (sessão nova/vazia ou cookie não enviado)',
                'tipo' => $tipo,
                'uri' => $uri,
                'ip' => $ip,
                'user_agent' => $ua,
                'session_id_masked' => $sidMask,
                'token_enviado_len' => strlen($postToken),
            ];
        }
        return [
            'motivo' => 'token_nao_confere',
            'descricao' => 'Token enviado não confere com o da sessão (página antiga, outra aba ou sessão trocada)',
            'tipo' => $tipo,
            'uri' => $uri,
            'ip' => $ip,
            'user_agent' => $ua,
            'session_id_masked' => $sidMask,
            'token_enviado_len' => strlen($postToken),
            'token_sessao_len' => strlen($_SESSION['csrf_token']),
        ];
    }

    /**
     * Grava log de falha CSRF no login em storage/logs/login_csrf.log
     * e dispara notificação no WhatsApp (via Logger, categoria auth).
     */
    private function writeLoginCsrfLog(array $data)
    {
        $dir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/login_csrf.log';
        $line = date('Y-m-d H:i:s') . ' [LOGIN CSRF] ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        if (class_exists('Logger')) {
            $msg = 'Falha CSRF no login: ' . ($data['descricao'] ?? $data['motivo'] ?? 'token inválido');
            Logger::warning($msg, [
                'tipo' => $data['tipo'] ?? '',
                'uri' => $data['uri'] ?? '',
                'ip' => $data['ip'] ?? '',
                'motivo' => $data['motivo'] ?? '',
            ], 'auth');
        }
    }

    /**
     * API: retorna o token CSRF atual do formulário de login (não gera novo; evita invalidar outras abas).
     */
    public function getLoginCsrfTokenJson()
    {
        if (php_sapi_name() !== 'cli') {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        $this->json(['csrf_token' => $this->generateCsrfToken()]);
    }

    /**
     * Exibe página de login do aluno
     */
    public function loginAluno()
    {
        if ($this->authManager->isLoggedIn()) {
            $this->authManager->logout();
            $this->redirect('/');
            return;
        }
        $this->showLogin('aluno');
    }

    /**
     * Primeira tela do primeiro acesso.
     * GET = exibe formulário. POST = valida na mesma URL (submit clássico, sem fetch).
     */
    public function primeiroAcesso()
    {
        $db = Database::getInstance();
        $turmas = $db->fetchAll("SELECT id, nome, tipo_ensino FROM turmas ORDER BY nome ASC");
        $csrf_token = $this->generateCsrfToken();
        $error = '';
        $matches = [];
        $turma_id_preselect = (int)($_GET['turma_id'] ?? 0);
        $aluno_nome_preselect = trim((string)($_GET['aluno_nome'] ?? ''));

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $result = $this->validarPrimeiroAcessoResult();
            if (isset($result['redirect'])) {
                $this->redirect($result['redirect']);
                return;
            }
            if (isset($result['error'])) {
                $error = $result['error'];
                $turma_id_preselect = (int)($_POST['turma_id'] ?? 0);
                $aluno_nome_preselect = trim((string)($_POST['aluno_nome'] ?? ''));
            }
            if (isset($result['matches'])) {
                $matches = $result['matches'];
                $turma_id_preselect = (int)($_POST['turma_id'] ?? 0);
                $aluno_nome_preselect = trim((string)($_POST['aluno_nome'] ?? ''));
            }
        }

        $this->view('auth/primeiro-acesso', [
            'title' => 'Primeiro acesso - EducaTudo',
            'csrf_token' => $csrf_token,
            'turmas' => $turmas,
            'error' => $error,
            'matches' => $matches,
            'turma_id_preselect' => $turma_id_preselect,
            'aluno_nome_preselect' => $aluno_nome_preselect,
        ]);
    }

    /**
     * Lógica de validação do primeiro acesso (retorna array; não envia JSON).
     */
    private function validarPrimeiroAcessoResult()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            return ['error' => 'Token inválido. Recarregue a página e tente de novo.'];
        }

        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $turmaObrigatoria = LayoutHelper::isPrimeiroAcessoTurmaObrigatoria();
        
        $turmaId = (int)($_POST['turma_id'] ?? 0);
        $alunoId = (int)($_POST['aluno_id'] ?? 0);
        $alunoNome = trim($_POST['aluno_nome'] ?? '');

        if ($turmaObrigatoria && $turmaId <= 0) {
            return ['error' => 'Turma e nome completo são obrigatórios.'];
        }
        if ($alunoId <= 0 && $alunoNome === '') {
            return ['error' => 'Nome completo é obrigatório.'];
        }

        $db = Database::getInstance();
        if ($alunoId > 0) {
            if ($turmaObrigatoria && $turmaId > 0) {
                $aluno = $db->fetch(
                    "SELECT id, nome, nickname, senha_hash, primeiro_acesso 
                     FROM alunos 
                     WHERE id = :id AND turma_id = :turma_id",
                    ['id' => $alunoId, 'turma_id' => $turmaId]
                );
            } else {
                $aluno = $db->fetch(
                    "SELECT id, nome, nickname, senha_hash, primeiro_acesso 
                     FROM alunos 
                     WHERE id = :id",
                    ['id' => $alunoId]
                );
            }
        } else {
            if ($turmaObrigatoria && $turmaId > 0) {
                $alunos = $db->fetchAll(
                    "SELECT id, nome, nickname, senha_hash, primeiro_acesso 
                     FROM alunos 
                     WHERE turma_id = :turma_id AND LOWER(TRIM(nome)) = LOWER(:nome)",
                    ['turma_id' => $turmaId, 'nome' => $alunoNome]
                );
            } else {
                $alunos = $db->fetchAll(
                    "SELECT id, nome, nickname, senha_hash, primeiro_acesso 
                     FROM alunos 
                     WHERE LOWER(TRIM(nome)) = LOWER(:nome)",
                    ['nome' => $alunoNome]
                );
            }
            if (count($alunos) > 1) {
                return [
                    'matches' => array_map(function ($a) {
                        return ['id' => $a['id'], 'nome' => $a['nome']];
                    }, $alunos),
                ];
            }
            $aluno = $alunos[0] ?? null;
        }

        if (!$aluno) {
            return ['error' => $turmaObrigatoria ? 'Aluno não encontrado na turma informada.' : 'Aluno não encontrado com esse nome.'];
        }

        $primeiroAcesso = array_key_exists('primeiro_acesso', $aluno) ? $aluno['primeiro_acesso'] : 1;
        $primeiroAcesso = ($primeiroAcesso === null) ? 1 : (int)$primeiroAcesso;
        $hasNickname = trim($aluno['nickname'] ?? '') !== '';
        $hasSenha = !empty($aluno['senha_hash']);
        if (($hasSenha || $primeiroAcesso === 0) && $hasNickname) {
            return ['error' => 'Acesso já criado. Use login normal.'];
        }

        $_SESSION['primeiro_acesso_aluno_id'] = $aluno['id'];
        $_SESSION['primeiro_acesso_aluno_nome'] = $aluno['nome'];

        return ['redirect' => URL . '/primeiro-acesso/criar'];
    }

    /**
     * GET em /primeiro-acesso/validar → redireciona para o formulário (evita "rota não encontrada")
     */
    public function redirectPrimeiroAcesso()
    {
        $this->redirect('/primeiro-acesso');
    }

    /**
     * API: listar alunos por turma (apenas sem acesso criado)
     */
    public function buscarAlunosPorTurma()
    {
        header('Content-Type: application/json');
        $turmaId = (int)($_GET['turma_id'] ?? 0);
        if ($turmaId <= 0) {
            $this->json(['alunos' => []]);
            return;
        }

        $db = Database::getInstance();
        $alunos = $db->fetchAll(
            "SELECT id, nome 
             FROM alunos 
             WHERE turma_id = :turma_id 
               AND (senha_hash IS NULL OR senha_hash = '') 
               AND (primeiro_acesso IS NULL OR primeiro_acesso = 1)
             ORDER BY nome ASC",
            ['turma_id' => $turmaId]
        );
        $this->json(['alunos' => $alunos]);
    }

    /**
     * Valida primeiro acesso (API JSON; usado se algum cliente ainda usar fetch).
     */
    public function validarPrimeiroAcesso()
    {
        $result = $this->validarPrimeiroAcessoResult();
        if (isset($result['redirect'])) {
            $this->json(['success' => true, 'redirect' => $result['redirect']]);
            return;
        }
        if (isset($result['error'])) {
            $code = (strpos($result['error'], 'não encontrado') !== false) ? 404 : 400;
            $this->json(['error' => $result['error']], $code);
            return;
        }
        if (isset($result['matches'])) {
            $this->json(['multiple' => true, 'matches' => $result['matches']]);
            return;
        }
        $this->json(['error' => 'Erro inesperado'], 500);
    }

    /**
     * Tela para criar acesso
     */
    public function criarAcesso()
    {
        $alunoId = $_SESSION['primeiro_acesso_aluno_id'] ?? null;
        $alunoNome = $_SESSION['primeiro_acesso_aluno_nome'] ?? '';
        if (!$alunoId) {
            $this->redirect('/primeiro-acesso');
            return;
        }

        $perguntas = [
            'Qual é o nome do seu primeiro professor(a)?',
            'Qual é o nome do seu animal de estimação?',
            'Qual é o nome da sua escola anterior?',
            'Qual é o nome da sua cidade natal?',
            'Qual é a sua comida favorita?'
        ];

        $this->view('auth/criar-acesso', [
            'title' => 'Criar acesso - EducaTudo',
            'csrf_token' => $this->generateCsrfToken(),
            'aluno_nome' => $alunoNome,
            'perguntas' => $perguntas
        ]);
    }

    /**
     * Salva o primeiro acesso
     */
    public function salvarPrimeiroAcesso()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        $alunoId = $_SESSION['primeiro_acesso_aluno_id'] ?? null;
        if (!$alunoId) {
            $this->json(['error' => 'Sessão expirada. Refaça o primeiro acesso.'], 400);
        }

        $nickname = trim($_POST['nickname'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $confirmar = $_POST['confirmar_senha'] ?? '';
        $pergunta = trim($_POST['pergunta'] ?? '');
        $resposta = trim($_POST['resposta'] ?? '');

        if ($nickname === '' || $senha === '' || $confirmar === '' || $pergunta === '' || $resposta === '') {
            $this->json(['error' => 'Preencha todos os campos'], 400);
        }

        if ($senha !== $confirmar) {
            $this->json(['error' => 'Senhas não conferem'], 400);
        }

        $senhaMin = 6;
        $temMaiuscula = preg_match('/[A-Z]/', $senha);
        $temMinuscula = preg_match('/[a-z]/', $senha);
        $temNumero = preg_match('/[0-9]/', $senha);
        $temEspecial = preg_match('/[^A-Za-z0-9]/', $senha);
        if (strlen($senha) < $senhaMin || !$temMaiuscula || !$temMinuscula || !$temNumero || !$temEspecial) {
            $this->json(['error' => 'A senha deve ter no mínimo 6 caracteres, com letra maiúscula, minúscula, número e símbolo.'], 400);
        }

        $senhaMin = 6;
        $temMaiuscula = preg_match('/[A-Z]/', $senha);
        $temMinuscula = preg_match('/[a-z]/', $senha);
        $temNumero = preg_match('/[0-9]/', $senha);
        $temEspecial = preg_match('/[^A-Za-z0-9]/', $senha);
        if (strlen($senha) < $senhaMin || !$temMaiuscula || !$temMinuscula || !$temNumero || !$temEspecial) {
            $this->json(['error' => 'A senha deve ter no mínimo 6 caracteres, com letra maiúscula, minúscula, número e símbolo.'], 400);
        }

        $db = Database::getInstance();
        $exists = $db->fetch(
            "SELECT id FROM alunos WHERE nickname = :nickname AND id != :id",
            ['nickname' => $nickname, 'id' => $alunoId]
        );
        if ($exists) {
            $this->json(['error' => 'Nickname já está em uso'], 400);
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $respostaHash = password_hash(mb_strtolower($resposta), PASSWORD_DEFAULT);

        $db->update(
            "UPDATE alunos 
             SET nickname = :nickname, senha_hash = :senha_hash, primeiro_acesso = 0 
             WHERE id = :id",
            ['nickname' => $nickname, 'senha_hash' => $senhaHash, 'id' => $alunoId]
        );

        $db->query(
            "INSERT INTO alunos_seguranca (aluno_id, pergunta, resposta_hash)
             VALUES (:aluno_id, :pergunta, :resposta_hash)
             ON DUPLICATE KEY UPDATE pergunta = VALUES(pergunta), resposta_hash = VALUES(resposta_hash)",
            ['aluno_id' => $alunoId, 'pergunta' => $pergunta, 'resposta_hash' => $respostaHash]
        );

        unset($_SESSION['primeiro_acesso_aluno_id'], $_SESSION['primeiro_acesso_aluno_nome']);

        $this->json(['success' => true, 'redirect' => URL . '/primeiro-acesso/sucesso']);
    }

    /**
     * Tela de sucesso do primeiro acesso
     */
    public function sucessoPrimeiroAcesso()
    {
        $this->view('auth/acesso-criado', [
            'title' => 'Acesso criado - EducaTudo'
        ]);
    }

    /**
     * Recuperação de senha do aluno (sem e-mail)
     */
    public function recuperarSenhaAluno()
    {
        $this->view('auth/recuperar-aluno', [
            'title' => 'Recuperar senha - Aluno',
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /**
     * Recuperação de senha do aluno - etapa da pergunta de segurança.
     * V-04 User Enumeration: nunca revelar se o nickname existe ou não.
     * Sempre retornar mensagem genérica quando não for possível continuar.
     */
    public function recuperarSenhaAlunoPergunta()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        $nickname = trim($_POST['nickname'] ?? '');
        if ($nickname === '') {
            $this->json(['error' => 'Nickname é obrigatório'], 400);
            return;
        }

        $db = Database::getInstance();
        $aluno = $db->fetch("SELECT id FROM alunos WHERE nickname = :nickname", ['nickname' => $nickname]);

        // V-04: Nunca indicar se o nickname existe. Se não existir ou não tiver pergunta, resposta neutra.
        if (!$aluno) {
            $this->json([
                'success' => true,
                'message' => 'Se o nickname informado estiver correto, o processo de recuperação continuará.'
            ]);
            return;
        }

        $seg = $db->fetch("SELECT pergunta FROM alunos_seguranca WHERE aluno_id = :aluno_id", ['aluno_id' => $aluno['id']]);

        // V-04: Mesma mensagem genérica quando não há pergunta (não revelar que o nickname existe).
        if (!$seg) {
            $this->json([
                'success' => true,
                'message' => 'Se o nickname informado estiver correto, o processo de recuperação continuará.'
            ]);
            return;
        }

        $_SESSION['recuperacao_aluno_id'] = $aluno['id'];
        $this->json(['success' => true, 'pergunta' => $seg['pergunta']]);
    }

    public function recuperarSenhaAlunoReset()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $alunoId = $_SESSION['recuperacao_aluno_id'] ?? null;
        if (!$alunoId) {
            $this->json(['error' => 'Sessão expirada'], 400);
        }

        $resposta = trim($_POST['resposta'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $confirmar = $_POST['confirmar_senha'] ?? '';
        if ($resposta === '' || $senha === '' || $confirmar === '') {
            $this->json(['error' => 'Preencha todos os campos'], 400);
        }
        if ($senha !== $confirmar) {
            $this->json(['error' => 'Senhas não conferem'], 400);
        }

        $db = Database::getInstance();
        $seg = $db->fetch("SELECT resposta_hash FROM alunos_seguranca WHERE aluno_id = :aluno_id", ['aluno_id' => $alunoId]);
        if (!$seg || !password_verify(mb_strtolower($resposta), $seg['resposta_hash'])) {
            $this->json(['error' => 'Resposta incorreta'], 400);
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $db->update(
            "UPDATE alunos SET senha_hash = :senha_hash, primeiro_acesso = 0 WHERE id = :id",
            ['senha_hash' => $senhaHash, 'id' => $alunoId]
        );

        unset($_SESSION['recuperacao_aluno_id']);
        $this->json(['success' => true, 'redirect' => URL . '/']);
    }
    
    /**
     * Exibe página de login da administração.
     * Se já estiver logado, faz logout e mostra o formulário (sem redirect para evitar loop).
     */
    public function loginAdmin()
    {
        if ($this->authManager->isLoggedIn()) {
            $this->authManager->logout();
        }
        $this->showLogin('admin');
    }
    
    /**
     * Exibe página de login do professor
     */
    public function loginProfessor()
    {
        if ($this->authManager->isLoggedIn()) {
            $this->authManager->logout();
        }
        $this->showLogin('professor');
    }
    
    /**
     * Exibe página de login dos pais.
     * Se já estiver logado, faz logout e mostra o formulário (sem redirect para evitar loop).
     */
    public function loginPais()
    {
        if ($this->authManager->isLoggedIn()) {
            $this->authManager->logout();
        }
        $this->showLogin('pais');
    }

    /**
     * Exibe página de login do monitor de sala
     */
    public function loginMonitor()
    {
        if ($this->authManager->isLoggedIn()) {
            $this->authManager->logout();
        }
        $this->showLogin('monitor');
    }

    /**
     * Termos de uso (página pública)
     */
    public function termosDeUso()
    {
        $this->view('legal/terms', [
            'title' => 'Termos de Uso - EducaTudo'
        ]);
    }

    /**
     * Política de privacidade (página pública)
     */
    public function politicaPrivacidade()
    {
        $this->view('legal/privacy', [
            'title' => 'Política de Privacidade - EducaTudo'
        ]);
    }

    /**
     * Política de retenção (página pública)
     */
    public function politicaRetencao()
    {
        $this->view('legal/retention', [
            'title' => 'Política de Retenção de Dados - EducaTudo'
        ]);
    }

    /**
     * Tela de aceite obrigatório (usuário logado)
     */
    public function consent()
    {
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
            return;
        }

        $user = $this->authManager->getUser();
        $data = [
            'title' => 'Aceite de Termos - EducaTudo',
            'csrf_token' => $this->generateCsrfToken(),
            'user_type' => $user['tipo']
        ];

        $this->view('auth/consent', $data);
    }

    /**
     * Salvar aceite obrigatório (usuário logado)
     */
    public function acceptConsent()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        if (!$this->authManager->isLoggedIn()) {
            $this->json(['error' => 'Sessão expirada'], 401);
            return;
        }

        try {
            $user = $this->authManager->getUser();
            $this->storeUserConsents($user);
            $this->json(['success' => true, 'redirect' => $this->getDashboardUrl($user['tipo'])]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Indica se a requisição espera resposta JSON (fetch/AJAX). Se false, é envio tradicional de formulário.
     */
    private function wantsJson()
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return (stripos($accept, 'application/json') !== false)
            || (strtolower($xhr) === 'xmlhttprequest');
    }

    /**
     * URL da tela de login conforme o tipo (para redirect em erro).
     */
    private function getLoginUrlByTipo($tipo)
    {
        $t = $tipo === 'admin_escola' ? 'admin' : trim((string) $tipo);
        switch ($t) {
            case 'admin': return URL . '/admin';
            case 'professor': return URL . '/professor';
            case 'pai': return URL . '/pais';
            case 'monitor': return URL . '/monitor';
            default: return URL . '/';
        }
    }

    /**
     * URL da tela de login com parâmetro para evitar cache (usar após setFlashMessage para a mensagem aparecer na hora).
     */
    private function getLoginUrlWithCacheBust($tipo)
    {
        $base = $this->getLoginUrlByTipo($tipo);
        $sep = (strpos($base, '?') !== false) ? '&' : '?';
        return $base . $sep . 't=' . time();
    }

    /**
     * Processa login (fluxo POST exclusivo).
     * - NUNCA chama generateCsrf() / generateCsrfToken()
     * - 1) Validar CSRF
     * - 2) Validar credenciais
     * - 3) session_regenerate_id(true) só dentro de createSession() após login válido
     */
    public function autenticar()
    {
        $formSubmit = !$this->wantsJson();
        $tipo = trim(isset($_POST['tipo']) ? $_POST['tipo'] : '');

        // Honeypot: campo oculto que bots costumam preencher; se vier preenchido, rejeitar em silêncio
        $siteUrl = isset($_POST['site_url']) ? trim($_POST['site_url']) : '';
        if ($siteUrl !== '') {
            if ($formSubmit) {
                $this->setFlashMessage('Credenciais inválidas.', 'error');
                $this->redirect($this->getLoginUrlWithCacheBust($tipo));
                return;
            }
            $this->json(['error' => 'Credenciais inválidas.'], 400);
            return;
        }

        // 1) Validar CSRF (sessão ou double-submit cookie)
        $postToken = isset($_POST['_token']) ? (string) $_POST['_token'] : '';
        if (!$this->verifyLoginCsrfToken($postToken)) {
            $this->writeLoginCsrfLog($this->getLoginCsrfFailureReason($postToken));
            if ($formSubmit) {
                $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
                $this->redirect($this->getLoginUrlWithCacheBust($tipo));
                return;
            }
            $this->json(['error' => 'Token CSRF inválido. Recarregue a página e tente novamente.'], 403);
            return;
        }

        // Limite de tentativas por IP (MAX_LOGIN_ATTEMPTS / LOCKOUT_DURATION do .env)
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        if ($ip !== '' && !$this->checkLoginRateLimit($ip)) {
            $minutos = $this->getLockoutMinutes();
            if ($formSubmit) {
                $this->setFlashMessage("Muitas tentativas de login. Aguarde {$minutos} minutos e tente novamente.", 'error');
                $this->redirect($this->getLoginUrlWithCacheBust($tipo));
                return;
            }
            $this->json(['error' => "Muitas tentativas de login. Aguarde {$minutos} minutos e tente novamente."], 429);
            return;
        }

        try {
            $login = trim($_POST['login'] ?? '');
            $senha = $_POST['senha'] ?? '';
            
            if (empty($login) || empty($senha)) {
                throw new Exception('Login e senha são obrigatórios');
            }

            // 2) Validar credenciais; createSession() chama session_regenerate_id(true) só após login válido
            $user = $this->authManager->authenticate($login, $senha, $tipo);

            // Aluno ou professor com senha padrão 123456: redirecionar para tela de alterar senha obrigatória
            if ($user['tipo'] === 'aluno' && isset($user['is_default_password']) && $user['is_default_password']) {
                if ($formSubmit) {
                    $this->clearLoginCsrfCookie();
                    $this->redirect('/aluno/alterar-senha-obrigatoria');
                    return;
                }
                $this->json([
                    'success' => true,
                    'needs_password_change' => true,
                    'redirect' => URL . '/aluno/alterar-senha-obrigatoria',
                    'user_email' => isset($user['email']) ? $user['email'] : $login,
                    'user_type' => 'aluno'
                ]);
                return;
            }
            if ($user['tipo'] === 'professor' && isset($user['is_default_password']) && $user['is_default_password']) {
                if ($formSubmit) {
                    $this->clearLoginCsrfCookie();
                    $this->redirect('/professor/alterar-senha-obrigatoria');
                    return;
                }
                $this->json([
                    'success' => true,
                    'needs_password_change' => true,
                    'redirect' => URL . '/professor/alterar-senha-obrigatoria',
                    'user_email' => isset($user['email']) ? $user['email'] : $login,
                    'user_type' => 'professor'
                ]);
                return;
            }
            if ($user['tipo'] === 'monitor' && isset($user['is_default_password']) && $user['is_default_password']) {
                if ($formSubmit) {
                    $this->clearLoginCsrfCookie();
                    $this->redirect('/monitor/alterar-senha-obrigatoria');
                    return;
                }
                $this->json([
                    'success' => true,
                    'needs_password_change' => true,
                    'redirect' => URL . '/monitor/alterar-senha-obrigatoria',
                    'user_email' => $user['email'] ?? $login,
                    'user_type' => 'monitor'
                ]);
                return;
            }
            if ($user['tipo'] === 'pai' && !empty($user['must_change_password'])) {
                if ($formSubmit) {
                    $this->clearLoginCsrfCookie();
                    $this->redirect('/pais/alterar-senha-obrigatoria');
                    return;
                }
                $this->json([
                    'success' => true,
                    'needs_password_change' => true,
                    'redirect' => URL . '/pais/alterar-senha-obrigatoria',
                    'user_email' => isset($user['email']) ? $user['email'] : $login,
                    'user_type' => 'pai'
                ]);
                return;
            }
            
            // Login com sucesso
            $this->clearLoginCsrfCookie();
            if (in_array($user['tipo'], ['admin', 'professor', 'pai'], true) && class_exists('Logger')) {
                Logger::logAudit('LOGIN', '/login', [], (int) $user['id'], $user['tipo'], null);
            }
            if ($formSubmit) {
                $this->redirect($this->getDashboardUrl($user['tipo']));
                return;
            }
            $this->json(['success' => true, 'redirect' => $this->getDashboardUrl($user['tipo'])]);
            
        } catch (Exception $e) {
            $this->recordLoginRateLimitAttempt($ip);
            error_log("Erro no login: " . $e->getMessage());
            if ($formSubmit) {
                $this->setFlashMessage($e->getMessage(), 'error');
                $this->redirect($this->getLoginUrlWithCacheBust($tipo));
                return;
            }
            $this->json(['error' => $e->getMessage()], 401);
        }
    }

    private function getSecurityConfig()
    {
        $configPath = defined('CONFIG_PATH') ? CONFIG_PATH : (dirname(__DIR__, 3) . '/config/app.php');
        $config = is_file($configPath) ? require $configPath : [];
        return $config['security'] ?? [];
    }

    private function getLockoutMinutes()
    {
        $sec = $this->getSecurityConfig();
        $duration = (int) ($sec['lockout_duration'] ?? 900);
        return max(1, (int) ceil($duration / 60));
    }

    /** Limite por IP: só bloqueia após muitas tentativas (MAX_LOGIN_ATTEMPTS_IP), para não afetar outros usuários no mesmo IP. */
    private function checkLoginRateLimit($ip)
    {
        if ($ip === '') {
            return true;
        }
        $sec = $this->getSecurityConfig();
        $window = (int) ($sec['lockout_duration'] ?? 900);
        $max = (int) ($sec['max_login_attempts_ip'] ?? 50);
        $dir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/login_rate_limit.json';
        $now = time();
        $data = [];
        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            if ($raw !== false) {
                $data = @json_decode($raw, true) ?: [];
            }
        }
        foreach ($data as $key => $entry) {
            if (is_array($entry) && isset($entry['t']) && ($now - (int)$entry['t']) > $window) {
                unset($data[$key]);
            }
        }
        $entry = isset($data[$ip]) ? $data[$ip] : array('c' => 0, 't' => $now);
        if (($now - (int)$entry['t']) > $window) {
            $entry = array('c' => 0, 't' => $now);
        }
        return (int)$entry['c'] < $max;
    }

    private function recordLoginRateLimitAttempt($ip)
    {
        if ($ip === '') {
            return;
        }
        $sec = $this->getSecurityConfig();
        $window = (int) ($sec['lockout_duration'] ?? 900);
        $dir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/login_rate_limit.json';
        $now = time();
        $data = [];
        $fp = @fopen($file, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            $raw = stream_get_contents($fp);
            $data = $raw !== false && $raw !== '' ? (@json_decode($raw, true) ?: []) : [];
            foreach ($data as $key => $entry) {
                if (is_array($entry) && isset($entry['t']) && ($now - (int)$entry['t']) > $window) {
                    unset($data[$key]);
                }
            }
            $entry = isset($data[$ip]) ? $data[$ip] : array('c' => 0, 't' => $now);
            if (($now - (int)$entry['t']) > $window) {
                $entry = array('c' => 0, 't' => $now);
            }
            $entry['c'] = (int)$entry['c'] + 1;
            $data[$ip] = $entry;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function assertConsentAccepted(array $payload, $userType)
    {
        $accepted = $this->isConsentAcceptedPayload($payload);
        if (!$accepted) {
            throw new Exception('Você precisa aceitar os termos para continuar');
        }

        $docs = $this->getRequiredConsentDocs($userType);
        if (empty($docs)) {
            throw new Exception('Tipo de usuário inválido');
        }
    }

    private function isConsentAcceptedPayload(array $payload)
    {
        return (isset($payload['consent_accept']) && in_array($payload['consent_accept'], ['1', 'on', 'true', 'yes'], true))
            || (isset($payload['consent_accept_checkbox']) && in_array($payload['consent_accept_checkbox'], ['1', 'on', 'true', 'yes'], true));
    }

    private function hasRequiredConsents(array $user)
    {
        $docs = $this->getRequiredConsentDocs($user['tipo'] ?? '');
        if (empty($docs)) {
            return true;
        }
        $userRole = $this->mapUserRole($user['tipo'] ?? '');
        $version = 'v1.0';
        $db = Database::getInstance();

        foreach ($docs as $docType) {
            $row = $db->fetch(
                "SELECT id FROM usuarios_consentimentos
                 WHERE user_id = :user_id
                   AND user_role = :user_role
                   AND document_type = :document_type
                   AND document_version = :document_version
                 LIMIT 1",
                [
                    'user_id' => $user['id'],
                    'user_role' => $userRole,
                    'document_type' => $docType,
                    'document_version' => $version
                ]
            );
            if (!$row) {
                return false;
            }
        }

        return true;
    }

    private function storeUserConsents(array $user)
    {
        $docs = $this->getRequiredConsentDocs($user['tipo']);
        $userRole = $this->mapUserRole($user['tipo']);
        $version = 'v1.0';
        $ip = $this->getIpAddress();
        $db = Database::getInstance();

        foreach ($docs as $docType) {
            $db->insert(
                "INSERT IGNORE INTO usuarios_consentimentos (user_id, user_role, document_type, document_version, accepted_at, ip)
                 VALUES (:user_id, :user_role, :document_type, :document_version, NOW(), :ip)",
                [
                    'user_id' => $user['id'],
                    'user_role' => $userRole,
                    'document_type' => $docType,
                    'document_version' => $version,
                    'ip' => $ip
                ]
            );
        }
    }

    private function getRequiredConsentDocs($userType)
    {
        switch ($userType) {
            case 'aluno':
                return ['terms', 'privacy', 'retention'];
            case 'pai':
                return ['terms', 'privacy', 'retention'];
            case 'professor':
                return ['terms', 'privacy'];
            case 'admin':
            case 'admin_escola':
                return ['terms', 'privacy'];
            default:
                return [];
        }
    }

    private function mapUserRole($userType)
    {
        switch ($userType) {
            case 'aluno':
                return 'student';
            case 'pai':
                return 'parent';
            case 'professor':
                return 'teacher';
            case 'admin':
            case 'admin_escola':
                return 'admin';
            default:
                return 'unknown';
        }
    }

    private function getIpAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Retorna o caminho da tela de login para onde redirecionar após logout.
     * Admin → /admin | Professor → /professor | Pais → /pais | Aluno → /
     */
    private function getLoginPathForLogout($userType)
    {
        $t = $userType === 'admin_escola' ? 'admin' : trim((string) $userType);
        switch ($t) {
            case 'admin':
                return '/admin';
            case 'professor':
                return '/professor';
            case 'pai':
                return '/pais';
            case 'aluno':
                return '/';
            default:
                return '/';
        }
    }

    /**
     * Faz logout e redireciona para a tela de login do mesmo portal.
     * Prioridade: ?portal= na URL > sessão > Referer.
     * Admin → /admin | Professor → /professor | Pais → /pais | Aluno → /
     */
    /**
     * Entrar como: valida token emitido pelo painel master e cria sessão do usuário (admin, professor ou aluno).
     * Só funciona em ambiente multi-tenant; o token deve ser usado no domínio da escola.
     */
    public function entrarComo()
    {
        if (!defined('MULTI_TENANT_ACTIVE') || !MULTI_TENANT_ACTIVE || !defined('TENANT_ID')) {
            header('Location: ' . (defined('URL') ? URL : '') . '/login');
            exit;
        }

        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            header('Location: ' . (defined('URL') ? URL : '') . '/login');
            exit;
        }

        $secret = $this->config['security']['entra_como_secret'] ?? '';
        if ($secret === '') {
            header('Location: ' . (defined('URL') ? URL : '') . '/login');
            exit;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            header('Location: ' . (defined('URL') ? URL : '') . '/login');
            exit;
        }

        $payloadRaw = strtr($parts[0], '-_', '+/');
        $payloadRaw = base64_decode($payloadRaw, true);
        if ($payloadRaw === false) {
            header('Location: ' . (defined('URL') ? URL : '') . '/login');
            exit;
        }

        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload) || !isset($payload['escola_id'], $payload['tipo'], $payload['user_id'], $payload['exp'])) {
            header('Location: ' . (defined('URL') ? URL : '') . '/login');
            exit;
        }

        $sig = hash_hmac('sha256', $payloadRaw, $secret, true);
        $expectedSig = base64_encode($sig);
        $receivedSig = strtr($parts[1], '-_', '+/');
        $receivedSig = base64_decode($receivedSig, true);
        if ($receivedSig === false || !hash_equals($sig, $receivedSig)) {
            header('Location: ' . (defined('URL') ? URL : '') . '/login');
            exit;
        }

        if ((int) $payload['escola_id'] !== (int) TENANT_ID) {
            header('Location: ' . (defined('URL') ? URL : '') . '/login');
            exit;
        }

        if (time() > (int) $payload['exp']) {
            header('Location: ' . (defined('URL') ? URL : '') . '/login');
            exit;
        }

        // Anti-replay: token é de uso único. Se o jti já foi consumido (Redis), rejeita.
        // Fail-open se o Redis estiver indisponível (HMAC + expiração ainda protegem).
        $jti = isset($payload['jti']) ? (string) $payload['jti'] : '';
        if ($jti !== '') {
            if (!class_exists('RedisCache')) {
                require_once __DIR__ . '/../../Core/RedisCache.php';
            }
            $nonceKey = 'entrar_como_jti_' . $jti;
            if (RedisCache::get($nonceKey) !== null) {
                if (!class_exists('Logger')) {
                    require_once __DIR__ . '/../../Core/Logger.php';
                }
                Logger::warning('Token "Entrar como" reutilizado (replay bloqueado)', [
                    'escola_id' => (int) $payload['escola_id'],
                    'jti' => $jti,
                ], 'seguranca');
                header('Location: ' . (defined('URL') ? URL : '') . '/login');
                exit;
            }
            RedisCache::set($nonceKey, '1', max(1, (int) $payload['exp'] - time()));
        }

        $tipo = $payload['tipo'];
        $userId = (int) $payload['user_id'];
        $db = Database::getInstance();

        $user = null;
        if ($tipo === 'admin') {
            $row = $db->fetch("SELECT id, nome, email, perfil_admin, avatar_url FROM usuarios WHERE id = ? AND tipo = 'admin_escola' LIMIT 1", [$userId]);
            if ($row) {
                $user = ['id' => (int) $row['id'], 'nome' => $row['nome'], 'email' => $row['email'], 'perfil_admin' => $row['perfil_admin'] ?? 'admin', 'avatar_url' => $row['avatar_url'] ?? null, 'tipo' => 'admin'];
            }
        } elseif ($tipo === 'professor') {
            $row = $db->fetch("SELECT id, nome, email FROM professores WHERE id = ? AND ativo = 1 LIMIT 1", [$userId]);
            if ($row) {
                $user = ['id' => (int) $row['id'], 'nome' => $row['nome'], 'email' => $row['email'], 'tipo' => 'professor'];
            }
        } elseif ($tipo === 'aluno') {
            $row = $db->fetch("SELECT a.id, a.nome, a.email, a.ra, a.turma_id, a.serie, t.nome AS turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = ? AND a.ativo = 1 LIMIT 1", [$userId]);
            if ($row) {
                $user = ['id' => (int) $row['id'], 'nome' => $row['nome'], 'email' => $row['email'], 'ra' => $row['ra'] ?? '', 'turma_id' => $row['turma_id'], 'turma_nome' => $row['turma_nome'] ?? '', 'serie' => $row['serie'] ?? '', 'tipo' => 'aluno'];
            }
        } elseif ($tipo === 'pai') {
            $row = $db->fetch("SELECT id, nome, email, cpf, telefone FROM responsaveis WHERE id = ? AND ativo = 1 LIMIT 1", [$userId]);
            if ($row) {
                $user = [
                    'id' => (int) $row['id'],
                    'nome' => $row['nome'],
                    'email' => $row['email'] ?? '',
                    'cpf' => $row['cpf'] ?? '',
                    'telefone' => $row['telefone'] ?? '',
                    'tipo' => 'pai'
                ];
            }
        }

        if (!$user) {
            header('Location: ' . (defined('URL') ? URL : '') . '/login');
            exit;
        }

        if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION = [];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['tipo'];
        $_SESSION['user_name'] = $user['nome'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['impersonated_by_master'] = true;

        if ($user['tipo'] === 'admin') {
            $_SESSION['perfil_admin'] = $user['perfil_admin'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['avatar_url'] = $user['avatar_url'] ?? null;
        } elseif ($user['tipo'] === 'professor') {
            $_SESSION['email'] = $user['email'];
            $_SESSION['avatar_url'] = null;
        } elseif ($user['tipo'] === 'aluno') {
            $_SESSION['ra'] = $user['ra'];
            $_SESSION['turma_id'] = $user['turma_id'];
            $_SESSION['turma_nome'] = $user['turma_nome'];
            $_SESSION['serie'] = $user['serie'];
        } elseif ($user['tipo'] === 'pai') {
            $_SESSION['email'] = $user['email'];
            $_SESSION['cpf'] = $user['cpf'];
            $_SESSION['telefone'] = $user['telefone'];
        }

        // Auditoria: registra o início da sessão via "Entrar como" (no banco do tenant).
        if (!class_exists('Logger')) {
            require_once __DIR__ . '/../../Core/Logger.php';
        }
        Logger::logAudit(
            'entrar_como_master',
            $_SERVER['REQUEST_URI'] ?? '/auth/entrar-como',
            [
                'escola_id' => (int) $payload['escola_id'],
                'tipo' => $user['tipo'],
                'user_id_alvo' => $user['id'],
                'jti' => $jti,
            ],
            (int) $user['id'],
            $user['tipo']
        );
        Logger::warning('Sessão iniciada via "Entrar como" do Master', [
            'escola_id' => (int) $payload['escola_id'],
            'tipo' => $user['tipo'],
            'user_id' => $user['id'],
            'jti' => $jti,
        ], 'seguranca');

        $url = defined('URL') ? URL : '';
        if ($user['tipo'] === 'admin') {
            header('Location: ' . $url . '/admin/dashboard');
        } elseif ($user['tipo'] === 'professor') {
            header('Location: ' . $url . '/professor/dashboard');
        } elseif ($user['tipo'] === 'pai') {
            header('Location: ' . $url . '/pais/dashboard');
        } else {
            header('Location: ' . $url . '/dashboard');
        }
        exit;
    }

    public function logout()
    {
        if (php_sapi_name() !== 'cli') {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        $userType = null;
        $portal = trim($_GET['portal'] ?? '');
        if ($portal !== '' && in_array($portal, ['admin', 'admin_escola', 'professor', 'pai', 'aluno'], true)) {
            $userType = $portal === 'admin_escola' ? 'admin' : $portal;
        }
        if ($userType === null) {
            $userType = $_SESSION['user_type'] ?? null;
        }
        if ($userType === null || $userType === '') {
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, '/admin') !== false) {
                $userType = 'admin';
            } elseif (strpos($referer, '/professor') !== false) {
                $userType = 'professor';
            } elseif (strpos($referer, '/pais') !== false) {
                $userType = 'pai';
            } else {
                $userType = 'aluno';
            }
            error_log('[Logout] user_type vazio; usou Referer: ' . $referer . ' -> tipo=' . $userType);
        }
        
        $this->authManager->logout();
        $this->redirect($this->getLoginPathForLogout($userType));
    }
    
    /**
     * Exibe página de recuperação de senha
     */
    public function recuperarSenha()
    {
        $tipo = $_GET['tipo'] ?? null;
        
        $data = [
            'title' => 'Recuperar Senha - EducaTudo',
            'csrf_token' => $this->generateCsrfToken(),
            'tipo_pre_selecionado' => $tipo
        ];
        
        $this->view('auth/recuperar-senha', $data);
    }
    
    /**
     * Envia email de recuperação
     */
    public function enviarRecuperacao()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $email = trim($_POST['email'] ?? '');
            $tipo = $_POST['tipo'] ?? null;
            
            if (empty($email)) {
                throw new Exception('Email é obrigatório');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
            
            // Busca usuário por email
            $db = Database::getInstance();
            
            // Buscar em diferentes tabelas baseado no tipo
            $user = null;
            $userType = null;
            
            if ($tipo === 'professor') {
                $professor = $db->fetch(
                    "SELECT * FROM professores WHERE email = :email",
                    ['email' => $email]
                );
                if ($professor) {
                    $user = $professor;
                    $userType = 'professor';
                }
            } else {
                // Admin, coordenador, direção
                $usuario = $db->fetch(
                    "SELECT * FROM usuarios WHERE email = :email AND tipo IN ('admin', 'admin_escola')",
                    ['email' => $email]
                );
                if ($usuario) {
                    $user = $usuario;
                    $userType = $usuario['tipo'];
                }
            }
            
            if (!$user) {
                // Não revelar se o email existe ou não por segurança
                $this->json(['message' => 'Se o email estiver cadastrado, você receberá instruções de recuperação']);
                return;
            }
            
            // Gera token de recuperação
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hora
            
            // Salva token no banco
            $db->query(
                "INSERT INTO redefinicoes_senha (email, token, user_type, expires_at) 
                 VALUES (:email, :token, :user_type, :expires_at)
                 ON DUPLICATE KEY UPDATE 
                 token = VALUES(token), expires_at = VALUES(expires_at), used = 0",
                [
                    'email' => $email,
                    'token' => $token,
                    'user_type' => $userType,
                    'expires_at' => $expiresAt
                ]
            );
            
            // Envia e-mail
            require_once __DIR__ . '/../../Services/EmailService.php';
            require_once __DIR__ . '/../../Core/Logger.php';
            $emailService = new EmailService();
            
            $resetUrl = URL . "/recuperar-senha/reset?token={$token}&email=" . urlencode($email);
            
            try {
                $emailService->sendPasswordReset(
                    $email,
                    $user['nome'],
                    $token,
                    $resetUrl
                );
            } catch (Exception $emailException) {
                // Logar erro de envio de email
                Logger::error(
                    "Erro ao enviar email de recuperação de senha",
                    [
                        'exception' => $emailException,
                        'email' => $email,
                        'user_type' => $userType,
                        'user_name' => $user['nome'] ?? 'N/A',
                        'reset_url' => $resetUrl
                    ],
                    'email'
                );
                throw $emailException; // Re-lançar para ser capturado pelo catch externo
            }
            
            $this->json(['message' => 'Instruções de recuperação enviadas para seu email']);
            
        } catch (Exception $e) {
            // Logar erro completo no app.data.log
            require_once __DIR__ . '/../../Core/Logger.php';
            Logger::error(
                "Erro ao processar recuperação de senha",
                [
                    'exception' => $e,
                    'email' => $email ?? 'N/A',
                    'tipo' => $tipo ?? 'N/A',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ],
                'email'
            );
            // Não revelar erro específico por segurança
            $this->json(['message' => 'Se o email estiver cadastrado, você receberá instruções de recuperação']);
        }
    }
    
    /**
     * Exibe página de redefinição de senha
     */
    public function resetPassword()
    {
        $token = $_GET['token'] ?? '';
        $email = $_GET['email'] ?? '';
        
        if (empty($token) || empty($email)) {
            $this->redirect('/recuperar-senha');
        }
        
        // Verifica se token é válido
        $db = Database::getInstance();
        $reset = $db->fetch(
            "SELECT * FROM redefinicoes_senha 
             WHERE email = :email AND token = :token AND used = 0 AND expires_at > NOW()",
            ['email' => $email, 'token' => $token]
        );
        
        if (!$reset) {
            $data = [
                'title' => 'Token Inválido - EducaTudo',
                'error' => 'Token inválido ou expirado. Solicite uma nova recuperação de senha.',
                'csrf_token' => $this->generateCsrfToken()
            ];
            $this->view('auth/reset-password-error', $data);
            return;
        }
        
        $data = [
            'title' => 'Redefinir Senha - EducaTudo',
            'token' => $token,
            'email' => $email,
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->view('auth/reset-password', $data);
    }
    
    /**
     * Processa redefinição de senha
     */
    public function processarReset()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $token = $_POST['token'] ?? '';
            $email = $_POST['email'] ?? '';
            $senha = $_POST['senha'] ?? '';
            $confirmarSenha = $_POST['confirmar_senha'] ?? '';
            
            if (empty($token) || empty($email) || empty($senha)) {
                throw new Exception('Todos os campos são obrigatórios');
            }
            
            if ($senha !== $confirmarSenha) {
                throw new Exception('Senhas não coincidem');
            }
            
            // Verifica se token é válido
            $db = Database::getInstance();
            $reset = $db->fetch(
                "SELECT * FROM redefinicoes_senha 
                 WHERE email = :email AND token = :token AND used = 0 AND expires_at > NOW()",
                ['email' => $email, 'token' => $token]
            );
            
            if (!$reset) {
                throw new Exception('Token inválido ou expirado');
            }
            
            // Valida senha forte
            $auth = new Auth();
            $passwordValidation = $auth->validateStrongPassword($senha);
            if ($passwordValidation !== true) {
                throw new Exception($passwordValidation);
            }
            
            // Atualiza senha
            if ($reset['user_type'] === 'professor') {
                $db->query(
                    "UPDATE professores SET senha_hash = :senha_hash WHERE email = :email",
                    [
                        'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                        'email' => $email
                    ]
                );
            } else {
                $db->query(
                    "UPDATE usuarios SET senha_hash = :senha_hash WHERE email = :email",
                    [
                        'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                        'email' => $email
                    ]
                );
            }
            
            // Marca token como usado
            $db->query(
                "UPDATE redefinicoes_senha SET used = 1 WHERE token = :token",
                ['token' => $token]
            );
            
            $this->json(['success' => true, 'message' => 'Senha redefinida com sucesso!']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Altera senha obrigatória diretamente na tela de login (sem estar autenticado)
     */
    public function alterarSenhaObrigatoriaLogin()
    {
        // Limpar buffer de saída se existir
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            error_log('CSRF token inválido na alteração de senha obrigatória');
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $email = trim($_POST['email'] ?? '');
            $senhaAtual = $_POST['senha_atual'] ?? '';
            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmarSenha = $_POST['confirmar_senha'] ?? '';
            $tipo = $_POST['tipo'] ?? 'professor';
            
            error_log("Alteração de senha obrigatória - Email: $email, Tipo: $tipo");
            
            // Validações
            if (empty($email) || empty($senhaAtual) || empty($novaSenha) || empty($confirmarSenha)) {
                throw new Exception('Todos os campos são obrigatórios');
            }
            
            if ($novaSenha !== $confirmarSenha) {
                throw new Exception('As senhas não coincidem');
            }
            
            if ($novaSenha === '123456') {
                throw new Exception('A nova senha não pode ser a senha padrão');
            }
            
            // Valida senha forte
            $auth = new Auth();
            $passwordValidation = $auth->validateStrongPassword($novaSenha);
            if ($passwordValidation !== true) {
                throw new Exception($passwordValidation);
            }
            
            $db = Database::getInstance();
            
            // Verificar credenciais atuais
            if ($tipo === 'professor') {
                $professor = $db->fetch(
                    "SELECT * FROM professores WHERE email = :email AND ativo = 1",
                    ['email' => $email]
                );
                
                if (!$professor) {
                    error_log("Professor não encontrado: $email");
                    throw new Exception('Email não encontrado ou conta inativa');
                }
                
                // Verificar senha atual
                if (!password_verify($senhaAtual, $professor['senha_hash'])) {
                    error_log("Senha atual incorreta para professor: $email");
                    throw new Exception('Senha atual incorreta');
                }
                
                // Verificar se realmente é senha padrão
                if (!password_verify('123456', $professor['senha_hash'])) {
                    error_log("Professor não tem senha padrão: $email");
                    throw new Exception('Esta conta não precisa alterar a senha');
                }
                
                // Atualizar senha
                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $stmt = $db->query(
                    "UPDATE professores SET senha_hash = :senha_hash WHERE id = :id",
                    ['senha_hash' => $senhaHash, 'id' => $professor['id']]
                );
                
                $rowsAffected = $stmt->rowCount();
                if ($rowsAffected === 0) {
                    error_log("Nenhuma linha atualizada para professor: $email (ID: {$professor['id']})");
                    throw new Exception('Erro ao atualizar senha. Nenhuma alteração foi realizada.');
                }
                
                error_log("Senha atualizada - Linhas afetadas: $rowsAffected para professor: $email");
                
                // Pequeno delay para garantir commit no banco
                usleep(100000); // 0.1 segundo
                
                // Fazer login automaticamente após alterar senha
                try {
                    $user = $this->authManager->authenticate($email, $novaSenha, 'professor');
                    error_log("Login automático realizado após alteração de senha: $email");
                } catch (Exception $e) {
                    error_log("Erro no login automático após alteração: " . $e->getMessage());
                    // Mesmo com erro no login, a senha foi alterada - retorna sucesso mas pede para fazer login
                    $this->json([
                        'success' => true, 
                        'message' => 'Senha alterada com sucesso! Faça login novamente.',
                        'redirect' => URL . '/professor'
                    ]);
                    return;
                }
                
                $this->json([
                    'success' => true, 
                    'message' => 'Senha alterada com sucesso!',
                    'redirect' => URL . '/professor/dashboard'
                ]);
            } elseif ($tipo === 'pai') {
                $responsavel = $db->fetch(
                    "SELECT * FROM responsaveis WHERE email = :email AND ativo = 1",
                    ['email' => $email]
                );
                if (!$responsavel) {
                    throw new Exception('Email não encontrado ou conta inativa');
                }
                if (!password_verify($senhaAtual, $responsavel['senha_hash'])) {
                    throw new Exception('Senha atual incorreta');
                }
                if ((int)($responsavel['force_password_change'] ?? 0) !== 1) {
                    throw new Exception('Esta conta não precisa alterar a senha');
                }

                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $db->query(
                    "UPDATE responsaveis
                     SET senha_hash = :senha_hash,
                         force_password_change = 0
                     WHERE id = :id",
                    ['senha_hash' => $senhaHash, 'id' => $responsavel['id']]
                );

                try {
                    $this->authManager->authenticate($email, $novaSenha, 'pai');
                } catch (Exception $e) {
                    $this->json([
                        'success' => true,
                        'message' => 'Senha alterada com sucesso! Faça login novamente.',
                        'redirect' => URL . '/pais'
                    ]);
                    return;
                }

                $this->json([
                    'success' => true,
                    'message' => 'Senha alterada com sucesso!',
                    'redirect' => URL . '/pais/dashboard'
                ]);
            } else {
                throw new Exception('Tipo de usuário não suportado');
            }
            
        } catch (Exception $e) {
            error_log("Erro na alteração de senha obrigatória: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            
            // Garantir que não há output antes de enviar JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (Throwable $e) {
            error_log("Erro fatal na alteração de senha obrigatória: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            
            // Garantir que não há output antes de enviar JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Erro interno do servidor. Tente novamente.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    
    /**
     * Exibe página de recuperação de senha específica para professores
     */
    public function recuperarSenhaProfessor()
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        
        $logo_url = LayoutHelper::getLogoUrl();
        $logo_horizontal_url = LayoutHelper::getLogoHorizontalUrl();
        $login_cover_url = LayoutHelper::getLoginCoverUrl();
        $system_title = LayoutHelper::getSystemTitle();
        $system_subtitle = LayoutHelper::getSystemSubtitle();
        
        $data = [
            'title' => 'Recuperar Senha - Portal do Professor',
            'csrf_token' => $this->generateCsrfToken(),
            'logo_url' => $logo_url,
            'logo_horizontal_url' => $logo_horizontal_url,
            'login_cover_url' => $login_cover_url,
            'system_title' => $system_title,
            'system_subtitle' => $system_subtitle
        ];
        
        $this->view('auth/recuperar-senha-professor', $data);
    }
    
    /**
     * Envia email de recuperação específico para professores
     */
    public function enviarRecuperacaoProfessor()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $email = trim($_POST['email'] ?? '');
            
            if (empty($email)) {
                throw new Exception('Email é obrigatório');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
            
            // Busca professor por email
            $db = Database::getInstance();
            $professor = $db->fetch(
                "SELECT * FROM professores WHERE email = :email",
                ['email' => $email]
            );
            
            if (!$professor) {
                // Não revelar se o email existe ou não por segurança
                $this->json(['message' => 'Se o email estiver cadastrado, você receberá instruções de recuperação']);
                return;
            }
            
            // Gera token de recuperação
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hora
            
            // Salva token no banco
            $db->query(
                "INSERT INTO redefinicoes_senha (email, token, user_type, expires_at) 
                 VALUES (:email, :token, :user_type, :expires_at)
                 ON DUPLICATE KEY UPDATE 
                 token = VALUES(token), expires_at = VALUES(expires_at), used = 0",
                [
                    'email' => $email,
                    'token' => $token,
                    'user_type' => 'professor',
                    'expires_at' => $expiresAt
                ]
            );
            
            // Envia e-mail
            require_once __DIR__ . '/../../Services/EmailService.php';
            require_once __DIR__ . '/../../Core/Logger.php';
            $emailService = new EmailService();
            
            $resetUrl = URL . "/professor/recuperar-senha/reset?token={$token}&email=" . urlencode($email);
            
            try {
                $emailService->sendPasswordReset(
                    $email,
                    $professor['nome'],
                    $token,
                    $resetUrl
                );
            } catch (Exception $emailException) {
                // Logar erro de envio de email
                Logger::error(
                    "Erro ao enviar email de recuperação de senha do professor",
                    [
                        'exception' => $emailException,
                        'email' => $email,
                        'user_type' => 'professor',
                        'user_name' => $professor['nome'] ?? 'N/A',
                        'professor_id' => $professor['id'] ?? 'N/A',
                        'reset_url' => $resetUrl
                    ],
                    'email'
                );
                throw $emailException; // Re-lançar para ser capturado pelo catch externo
            }
            
            $this->json(['message' => 'Instruções de recuperação enviadas para seu email']);
            
        } catch (Exception $e) {
            // Logar erro completo no app.data.log
            require_once __DIR__ . '/../../Core/Logger.php';
            Logger::error(
                "Erro ao processar recuperação de senha do professor",
                [
                    'exception' => $e,
                    'email' => $email ?? 'N/A',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ],
                'email'
            );
            // Não revelar erro específico por segurança
            $this->json(['message' => 'Se o email estiver cadastrado, você receberá instruções de recuperação']);
        }
    }
    
    /**
     * Exibe página de redefinição de senha específica para professores
     */
    public function resetPasswordProfessor()
    {
        $token = $_GET['token'] ?? '';
        $email = $_GET['email'] ?? '';
        
        if (empty($token) || empty($email)) {
            $this->redirect('/professor/recuperar-senha');
        }
        
        // Verifica se token é válido
        $db = Database::getInstance();
        $reset = $db->fetch(
            "SELECT * FROM redefinicoes_senha 
             WHERE email = :email AND token = :token AND used = 0 AND expires_at > NOW() AND user_type = 'professor'",
            ['email' => $email, 'token' => $token]
        );
        
        if (!$reset) {
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
            
            $data = [
                'title' => 'Token Inválido - Portal do Professor',
                'error' => 'Token inválido ou expirado. Solicite uma nova recuperação de senha.',
                'csrf_token' => $this->generateCsrfToken()
            ];
            $this->view('auth/reset-password-professor-error', $data);
            return;
        }
        
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        
        $logo_url = LayoutHelper::getLogoUrl();
        $logo_horizontal_url = LayoutHelper::getLogoHorizontalUrl();
        $login_cover_url = LayoutHelper::getLoginCoverUrl();
        $system_title = LayoutHelper::getSystemTitle();
        $system_subtitle = LayoutHelper::getSystemSubtitle();
        
        $data = [
            'title' => 'Redefinir Senha - Portal do Professor',
            'token' => $token,
            'email' => $email,
            'csrf_token' => $this->generateCsrfToken(),
            'logo_url' => $logo_url,
            'logo_horizontal_url' => $logo_horizontal_url,
            'login_cover_url' => $login_cover_url,
            'system_title' => $system_title,
            'system_subtitle' => $system_subtitle
        ];
        
        $this->view('auth/reset-password-professor', $data);
    }
    
    /**
     * Processa redefinição de senha específica para professores
     */
    public function processarResetProfessor()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $token = $_POST['token'] ?? '';
            $email = $_POST['email'] ?? '';
            $senha = $_POST['senha'] ?? '';
            $confirmarSenha = $_POST['confirmar_senha'] ?? '';
            
            if (empty($token) || empty($email) || empty($senha)) {
                throw new Exception('Todos os campos são obrigatórios');
            }
            
            if ($senha !== $confirmarSenha) {
                throw new Exception('Senhas não coincidem');
            }
            
            // Verifica se token é válido
            $db = Database::getInstance();
            $reset = $db->fetch(
                "SELECT * FROM redefinicoes_senha 
                 WHERE email = :email AND token = :token AND used = 0 AND expires_at > NOW() AND user_type = 'professor'",
                ['email' => $email, 'token' => $token]
            );
            
            if (!$reset) {
                throw new Exception('Token inválido ou expirado');
            }
            
            // Valida senha forte
            $auth = new Auth();
            $passwordValidation = $auth->validateStrongPassword($senha);
            if ($passwordValidation !== true) {
                throw new Exception($passwordValidation);
            }
            
            // Atualiza senha do professor
            $db->query(
                "UPDATE professores SET senha_hash = :senha_hash WHERE email = :email",
                [
                    'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                    'email' => $email
                ]
            );
            
            // Marca token como usado
            $db->query(
                "UPDATE redefinicoes_senha SET used = 1 WHERE token = :token",
                ['token' => $token]
            );
            
            $this->json(['success' => true, 'message' => 'Senha redefinida com sucesso!', 'redirect' => URL . '/professor']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Redireciona para dashboard baseado no tipo de usuário
     */
    private function redirectToDashboard()
    {
        $user = $this->authManager->getUser();
        
        // Verifica se o usuário está logado
        if (!$user || !isset($user['tipo'])) {
            $this->redirect('/');
            return;
        }
        
        switch ($user['tipo']) {
            case 'admin':
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/');
        }
    }
    
    /**
     * Retorna URL do dashboard baseado no tipo de usuário
     */
    private function getDashboardUrl($tipo)
    {
        switch ($tipo) {
            case 'admin':
            case 'admin_escola':
                return URL . '/admin/dashboard';
            case 'professor':
                return URL . '/professor/dashboard';
            case 'aluno':
                return URL . '/dashboard';
            case 'pai':
                return URL . '/pais/dashboard';
            case 'monitor':
                return URL . '/monitor/dashboard';
            default:
                return URL . '/';
        }
    }
}
}
