<?php
/**
 * EducaTudo - Autenticação do painel admin master (multi-tenant)
 * Login e primeiro acesso usando tabela usuarios_master no banco master.
 */

if (!class_exists('MasterAuthController')) {

class MasterAuthController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';
    private const SESSION_MASTER_USER_EMAIL = 'master_user_email';
    private const SESSION_MASTER_USER_NOME = 'master_user_nome';
    private const SESSION_MASTER_USER_AVATAR = 'master_user_avatar';
    private const COOKIE_MASTER_AUTH = 'master_auth';
    private const COOKIE_MASTER_LIFETIME = 86400 * 7; // 7 dias

    public function __construct()
    {
        parent::__construct();
    }

    private function logMaster(string $message, array $context = []): void
    {
        $line = date('Y-m-d H:i:s') . ' [master_auth] ' . $message;
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        $line .= "\n";
        error_log(trim($line));
        $logDir = defined('ENV_FILE_PATH') ? dirname(ENV_FILE_PATH) . '/storage/logs' : (__DIR__ . '/../../../storage/logs');
        if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
            @file_put_contents($logDir . '/master_auth.log', $line, FILE_APPEND | LOCK_EX);
        }
    }

    private function getMasterAuthSecret(): string
    {
        $envPath = defined('ENV_FILE_PATH') ? ENV_FILE_PATH : (__DIR__ . '/../../../.env');
        $paths = [$envPath, __DIR__ . '/../../../.env'];
        foreach ($paths as $p) {
            if (!is_file($p)) {
                continue;
            }
            $lines = @file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (stripos($line, 'SCHOOL_SECRET_KEY=') === 0 && preg_match('/SCHOOL_SECRET_KEY\s*=\s*(.+)$/i', $line, $m)) {
                    $v = trim($m[1]);
                    if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
                        $v = substr($v, 1, -1);
                    }
                    return trim($v) ?: 'master-default-secret-change-me';
                }
            }
            break;
        }
        return 'master-default-secret-change-me';
    }

    private function setMasterAuthCookie(int $userId): void
    {
        $secret = $this->getMasterAuthSecret();
        $payload = $userId . '.' . hash_hmac('sha256', (string) $userId, $secret);
        $value = base64_encode($payload);
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(self::COOKIE_MASTER_AUTH, $value, [
            'expires' => time() + self::COOKIE_MASTER_LIFETIME,
            'path' => defined('FOLDER') && FOLDER !== '' ? FOLDER : '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearMasterAuthCookie(): void
    {
        setcookie(self::COOKIE_MASTER_AUTH, '', [
            'expires' => time() - 3600,
            'path' => defined('FOLDER') && FOLDER !== '' ? FOLDER : '/',
            'domain' => '',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** Restaura sessão master a partir do cookie (quando sessão PHP não persiste). */
    private function restoreMasterSessionFromCookie(): bool
    {
        $raw = $_COOKIE[self::COOKIE_MASTER_AUTH] ?? '';
        if ($raw === '') {
            $this->logMaster('restoreMasterSessionFromCookie: cookie vazio');
            return false;
        }
        $payload = base64_decode($raw, true);
        if ($payload === false) {
            $this->logMaster('restoreMasterSessionFromCookie: base64_decode falhou');
            return false;
        }
        $parts = explode('.', $payload);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            $this->logMaster('restoreMasterSessionFromCookie: payload inválido', ['parts_count' => count($parts)]);
            return false;
        }
        $userId = (int) $parts[0];
        $secret = $this->getMasterAuthSecret();
        $expected = hash_hmac('sha256', (string) $userId, $secret);
        if (!hash_equals($expected, $parts[1])) {
            $this->logMaster('restoreMasterSessionFromCookie: assinatura inválida (cookie de outro ambiente?)');
            return false;
        }
        $db = Database::getInstance();
        $stmt = $db->query("SELECT id, email, nome, avatar_url FROM usuarios_master WHERE id = ? AND ativo = 1", [$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $this->logMaster('restoreMasterSessionFromCookie: usuário não encontrado no banco', ['user_id' => $userId]);
            return false;
        }
        $this->setMasterSession((int) $user['id'], $user['email'], $user['nome'], $user['avatar_url'] ?? null);
        $this->logMaster('restoreMasterSessionFromCookie: ok', ['user_id' => $userId]);
        return true;
    }

    /**
     * Exibe tela de login ou de primeiro acesso (criar primeiro admin) se não houver usuários.
     */
    public function index()
    {
        $this->logMaster('index() GET /master', ['session_id' => session_id(), 'has_master_session' => !empty($_SESSION[self::SESSION_MASTER_USER_ID]), 'has_cookie' => isset($_COOKIE[self::COOKIE_MASTER_AUTH])]);
        $this->ensureMultiTenant();
        $this->redirectIfMasterLoggedIn();

        $db = Database::getInstance();
        $hasUsers = (int) $db->query("SELECT COUNT(*) FROM usuarios_master WHERE ativo = 1")->fetchColumn() > 0;

        if (!$hasUsers) {
            $this->view('master/setup', [
                'title' => 'Criar administrador master - EducaTudo',
                'flash' => $this->getFlashMessage(),
            ]);
            return;
        }

        $this->disableCache();
        $token = $this->generateCsrfToken();
        $this->view('master/login', [
            'title' => 'Login Admin Master - EducaTudo',
            'csrf_token' => $token,
            'flash' => $this->getFlashMessage(),
        ]);
    }

    /**
     * POST: criar primeiro usuário master (quando tabela está vazia).
     */
    public function setup()
    {
        $this->ensureMultiTenant();
        $this->redirectIfMasterLoggedIn();

        $db = Database::getInstance();
        $hasUsers = (int) $db->query("SELECT COUNT(*) FROM usuarios_master WHERE ativo = 1")->fetchColumn() > 0;
        if ($hasUsers) {
            $this->setFlashMessage('O primeiro administrador já foi criado. Use o login.', 'info');
            header('Location: ' . URL . '/master');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $nome = trim($_POST['nome'] ?? 'Admin Master');

        if ($email === '' || strlen($senha) < 6) {
            $this->setFlashMessage('Preencha e-mail e senha (mínimo 6 caracteres).', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->setFlashMessage('E-mail inválido.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        $stmt = $db->query("SELECT id FROM usuarios_master WHERE email = ?", [$email]);
        if ($stmt->fetch()) {
            $this->setFlashMessage('Este e-mail já está cadastrado.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $id = (int) $db->insert(
            "INSERT INTO usuarios_master (email, senha_hash, nome, ativo) VALUES (?, ?, ?, 1)",
            [$email, $senhaHash, $nome ?: 'Admin Master']
        );
        $this->setMasterSession($id, $email, $nome ?: 'Admin Master');
        $this->setMasterAuthCookie($id);
        $this->setFlashMessage('Conta criada com sucesso.', 'success');
        if (function_exists('session_write_close')) {
            session_write_close();
        }
        header('Location: ' . URL . '/master/dashboard');
        exit;
    }

    /**
     * POST: login master (email + senha).
     */
    public function autenticar()
    {
        $this->logMaster('autenticar() POST /master/login', ['session_id' => session_id(), 'post_email' => trim($_POST['email'] ?? '') ? '(preenchido)' : '(vazio)']);
        $this->ensureMultiTenant();

        $token = $_POST['csrf_token'] ?? '';
        if ($token === '' || !$this->verifyCsrfToken($token)) {
            $this->logMaster('autenticar() CSRF inválido');
            $this->setFlashMessage('Requisição inválida. Tente novamente.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if ($email === '' || $senha === '') {
            $this->logMaster('autenticar() email ou senha vazios');
            $this->setFlashMessage('Preencha e-mail e senha.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->query("SELECT id, email, senha_hash, nome, avatar_url FROM usuarios_master WHERE email = ? AND ativo = 1", [$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $pwOk = $user && password_verify($senha, $user['senha_hash']);
        $this->logMaster('autenticar() user_found=' . ($user ? 'sim' : 'nao') . ' password_verify=' . ($pwOk ? 'ok' : 'falha'));

        if (!$user || !$pwOk) {
            $this->setFlashMessage('E-mail ou senha incorretos.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        $this->setMasterSession((int) $user['id'], $user['email'], $user['nome'], $user['avatar_url'] ?? null);
        $this->setMasterAuthCookie((int) $user['id']);
        $this->logMaster('autenticar() login ok', ['user_id' => $user['id'], 'cookie_path' => defined('FOLDER') && FOLDER !== '' ? FOLDER : '/', 'redirect' => 'dashboard']);
        $this->setFlashMessage('Login realizado.', 'success');
        if (function_exists('session_write_close')) {
            session_write_close();
        }
        header('Location: ' . URL . '/master/dashboard');
        exit;
    }

    /**
     * Dashboard do painel master (protegido por sessão).
     */
    public function dashboard()
    {
        $this->logMaster('dashboard() GET', ['session_id' => session_id(), 'has_master_session' => !empty($_SESSION[self::SESSION_MASTER_USER_ID]), 'has_cookie' => isset($_COOKIE[self::COOKIE_MASTER_AUTH]), 'cookie_length' => isset($_COOKIE[self::COOKIE_MASTER_AUTH]) ? strlen($_COOKIE[self::COOKIE_MASTER_AUTH]) : 0]);
        $this->ensureMultiTenant();
        $this->requireMasterAuth();
        require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';

        $db = Database::getInstance();
        $totalEscolas = (int) $db->query("SELECT COUNT(*) FROM escolas")->fetchColumn();
        $escolasAtivas = (int) $db->query("SELECT COUNT(*) FROM escolas WHERE ativo = 1")->fetchColumn();
        $escolasComBanco = (int) $db->query("SELECT COUNT(*) FROM escolas e INNER JOIN config_escolas_banco b ON b.escola_id = e.id")->fetchColumn();
        $escolasRecent = $db->query(
            "SELECT e.id, e.nome, e.slug, e.dominio, e.ativo FROM escolas e ORDER BY e.id DESC LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);

        $todasEscolas = $db->query(
            "SELECT e.id, e.nome, e.slug, e.dominio, e.ativo,
             (SELECT config_value FROM config_escolas_layout c WHERE c.escola_id = e.id AND c.config_key = 'logo_1x1_url' LIMIT 1) AS logo_1x1_url
             FROM escolas e WHERE e.ativo = 1 ORDER BY e.nome"
        )->fetchAll(PDO::FETCH_ASSOC);

        $totalCreditosConsumidos = 0.0;
        $masterPdo = $GLOBALS['_educatudo_master_pdo'] ?? null;
        if ($masterPdo) {
            if (!class_exists('DatabaseManager', false)) {
                require_once __DIR__ . '/../../Core/DatabaseManager.php';
            }
            $manager = new DatabaseManager($masterPdo);
            $escolasComBancoIds = $db->query(
                "SELECT e.id FROM escolas e INNER JOIN config_escolas_banco b ON b.escola_id = e.id WHERE e.ativo = 1"
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($escolasComBancoIds as $escolaId) {
                $escolaId = (int) $escolaId;
                try {
                    $tenantDb = $manager->getConnectionForTenant($escolaId);
                    $totalConsumidoTenant = $tenantDb->fetch(
                        "SELECT COALESCE(SUM(ABS(valor)), 0) AS total
                         FROM carteira_movimentacoes
                         WHERE tipo = 'consumo' AND user_type IN ('aluno', 'professor')"
                    );
                    $totalCreditosConsumidos += CreditosDecimalHelper::fromScalar($totalConsumidoTenant['total'] ?? 0, 0.0);
                } catch (Throwable $e) {
                    // tenant sem carteira_movimentacoes ou conexão falhou
                }
            }
        }

        $kpis = [
            'total_logins_sucesso' => 0,
            'total_jornadas' => 0,
            'total_provas' => 0,
            'modulos' => [],
            'gerado_em' => null,
            'disponivel' => false,
        ];
        try {
            $rowKpi = $db->query(
                "SELECT total_logins_sucesso, total_jornadas, total_provas, modulos_json, gerado_em
                 FROM master_dashboard_kpis WHERE id = 1 LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            if ($rowKpi) {
                $modulos = json_decode((string) ($rowKpi['modulos_json'] ?? '[]'), true);
                if (!is_array($modulos)) {
                    $modulos = [];
                }
                $kpis = [
                    'total_logins_sucesso' => (int) ($rowKpi['total_logins_sucesso'] ?? 0),
                    'total_jornadas' => (int) ($rowKpi['total_jornadas'] ?? 0),
                    'total_provas' => (int) ($rowKpi['total_provas'] ?? 0),
                    'modulos' => $modulos,
                    'gerado_em' => $rowKpi['gerado_em'] ?? null,
                    'disponivel' => true,
                ];
            }
        } catch (Throwable $e) {
            // tabela ainda não migrada
        }

        $this->viewWithLayout('master', 'master/dashboard', [
            'title' => 'Painel Admin Master - EducaTudo',
            'page_title' => 'Dashboard Admin',
            'current_page' => 'dashboard',
            'master_nome' => $_SESSION[self::SESSION_MASTER_USER_NOME] ?? 'Admin',
            'stats' => [
                'total_escolas' => $totalEscolas,
                'escolas_ativas' => $escolasAtivas,
                'escolas_com_banco' => $escolasComBanco,
                'creditos_consumidos_total' => $totalCreditosConsumidos,
            ],
            'kpis' => $kpis,
            'escolas_recentes' => $escolasRecent,
            'todas_escolas' => $todasEscolas,
            'flash' => $this->getFlashMessage(),
        ]);
    }

    /**
     * Logout master.
     */
    public function logout()
    {
        $this->clearMasterSession();
        $this->setFlashMessage('Você saiu do painel master.', 'info');
        header('Location: ' . URL . '/master');
        exit;
    }

    private function ensureMultiTenant(): void
    {
        if (defined('MULTI_TENANT_ACTIVE') && MULTI_TENANT_ACTIVE) {
            return;
        }
        // Fallback: ler .env diretamente (bootstrap pode não ter definido a constante em alguns ambientes)
        $envPath = defined('ENV_FILE_PATH') ? ENV_FILE_PATH : (__DIR__ . '/../../../.env');
        $paths = [$envPath, __DIR__ . '/../../../.env', __DIR__ . '/../../../../.env'];
        foreach ($paths as $p) {
            $resolved = is_file($p) ? $p : (realpath($p) ?: $p);
            if (!is_file($resolved)) {
                continue;
            }
            $lines = @file($resolved, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (stripos($line, 'MULTI_TENANT') === 0 && preg_match('/MULTI_TENANT\s*=\s*(.+)$/i', $line, $m)) {
                    $v = trim($m[1]);
                    if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
                        $v = substr($v, 1, -1);
                    }
                    if (strtolower(trim($v)) === 'true') {
                        return;
                    }
                    break 2;
                }
            }
            break;
        }
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Painel Master</title></head><body><p>O painel master está disponível apenas quando MULTI_TENANT=true no .env.</p></body></html>';
        exit;
    }

    private function redirectIfMasterLoggedIn(): void
    {
        if (!empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            $this->logMaster('redirectIfMasterLoggedIn: já tem sessão, redirecionando');
            header('Location: ' . URL . '/master/dashboard');
            exit;
        }
        if ($this->restoreMasterSessionFromCookie()) {
            $this->logMaster('redirectIfMasterLoggedIn: restaurado por cookie, redirecionando');
            header('Location: ' . URL . '/master/dashboard');
            exit;
        }
    }

    private function requireMasterAuth(): void
    {
        if (!empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            $this->logMaster('requireMasterAuth: ok sessão');
            return;
        }
        $this->logMaster('requireMasterAuth: sessão vazia, tentando cookie');
        if ($this->restoreMasterSessionFromCookie()) {
            $this->logMaster('requireMasterAuth: ok restaurado por cookie');
            return;
        }
        $this->logMaster('requireMasterAuth: falha - redirecionando para login', ['cookie_present' => isset($_COOKIE[self::COOKIE_MASTER_AUTH])]);
        $this->setFlashMessage('Faça login para acessar o painel master.', 'error');
        header('Location: ' . URL . '/master');
        exit;
    }

    private function setMasterSession(int $id, string $email, string $nome, ?string $avatarUrl = null): void
    {
        $_SESSION[self::SESSION_MASTER_USER_ID] = $id;
        $_SESSION[self::SESSION_MASTER_USER_EMAIL] = $email;
        $_SESSION[self::SESSION_MASTER_USER_NOME] = $nome;
        $_SESSION[self::SESSION_MASTER_USER_AVATAR] = $avatarUrl ?? '';
        $this->generateCsrfToken();
    }

    private function clearMasterSession(): void
    {
        unset(
            $_SESSION[self::SESSION_MASTER_USER_ID],
            $_SESSION[self::SESSION_MASTER_USER_EMAIL],
            $_SESSION[self::SESSION_MASTER_USER_NOME],
            $_SESSION[self::SESSION_MASTER_USER_AVATAR]
        );
        $this->clearMasterAuthCookie();
    }
}

}
