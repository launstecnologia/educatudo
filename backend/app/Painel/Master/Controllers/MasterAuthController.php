<?php

namespace App\Painel\Master\Controllers;
/**
 * EducaTudo - Autenticação do painel admin master (multi-tenant)
 * Login e primeiro acesso usando tabela usuarios_master no banco master.
 */


use App\Core\BaseController;
use App\Core\CreditosDecimalHelper;
use App\Core\Database;
use App\Core\DatabaseManager;
use App\Painel\Master\Services\CodigoAcessoMasterService;
if (!class_exists(__NAMESPACE__ . '\\MasterAuthController', false)) {

class MasterAuthController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';
    private const SESSION_MASTER_USER_EMAIL = 'master_user_email';
    private const SESSION_MASTER_USER_NOME = 'master_user_nome';
    private const SESSION_MASTER_USER_AVATAR = 'master_user_avatar';
    /** Cookie legado de “lembrar login” — só limpo no logout; não restaura sessão. */
    private const COOKIE_MASTER_AUTH = 'master_auth';
    private const SESSION_2FA_USUARIO_ID = 'master_2fa_usuario_id';
    private const SESSION_2FA_CODIGO_ID = 'master_2fa_codigo_id';
    private const SESSION_2FA_EMAIL = 'master_2fa_email';

    public function __construct()
    {
        parent::__construct();
    }

    private function logMaster(string $message, array $context = [], bool $registrarComoErro = false): void
    {
        $line = date('Y-m-d H:i:s') . ' [master_auth] ' . $message;
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        $line .= "\n";
        if ($registrarComoErro) {
            error_log(trim($line));
        }
        $logDir = defined('ENV_FILE_PATH') ? dirname(ENV_FILE_PATH) . '/storage/logs' : (__DIR__ . '/../../../storage/logs');
        if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
            @file_put_contents($logDir . '/master_auth.log', $line, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Expira o cookie legado master_auth (não há mais restauração automática).
     */
    private function clearMasterAuthCookie(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }
        $secure = function_exists('educatudoDetectHttps')
            ? educatudoDetectHttps()
            : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE_MASTER_AUTH, '', [
            'expires' => time() - 3600,
            'path' => defined('FOLDER') && FOLDER !== '' ? FOLDER : '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[self::COOKIE_MASTER_AUTH]);
    }

    /**
     * Exibe tela de login ou de primeiro acesso (criar primeiro admin) se não houver usuários.
     */
    public function index()
    {
        $this->ensureMultiTenant();
        $this->redirectIfMasterLoggedIn();
        if ($this->desafioSegundoFator() !== null) {
            header('Location: ' . URL . '/master/verificar');
            exit;
        }

        $db = Database::getInstance();
        $hasUsers = (int) $db->query("SELECT COUNT(*) FROM usuarios_master WHERE ativo = 1")->fetchColumn() > 0;

        if (!$hasUsers) {
            $this->view('master/setup', [
                'title' => 'Criar administrador master - EducaTudo',
                'csrf_token' => $this->generateCsrfToken(),
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

        $token = $_POST['csrf_token'] ?? '';
        if ($token === '' || !$this->verifyCsrfToken($token)) {
            $this->setFlashMessage('Requisição inválida. Tente novamente.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

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

        $codigos = new CodigoAcessoMasterService();
        if (!$codigos->tabelaPronta()) {
            $this->setFlashMessage('O segundo fator ainda não está instalado. Aplique a migration 2026_11_19_usuarios_master_codigos_acesso_master.sql no banco master.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }
        if (!$codigos->emailConfigurado()) {
            $this->setFlashMessage('O envio de e-mail do painel master não está configurado.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $nomeConta = $nome ?: 'Admin Master';
        $id = (int) $db->insert(
            "INSERT INTO usuarios_master (email, senha_hash, nome, ativo) VALUES (?, ?, ?, 1)",
            [$email, $senhaHash, $nomeConta]
        );
        try {
            $this->prepararDesafioSegundoFator([
                'id' => $id,
                'email' => $email,
                'nome' => $nomeConta,
            ]);
        } catch (\RuntimeException $e) {
            $this->logMaster('setup() segundo fator não enviado', ['user_id' => $id, 'motivo' => $e->getMessage()], true);
            $this->setFlashMessage('Conta criada. ' . $e->getMessage(), 'error');
            header('Location: ' . URL . '/master');
            exit;
        }
        header('Location: ' . URL . '/master/verificar');
        exit;
    }

    /**
     * POST: login master (email + senha).
     */
    public function autenticar()
    {
        $this->ensureMultiTenant();

        $token = $_POST['csrf_token'] ?? '';
        if ($token === '' || !$this->verifyCsrfToken($token)) {
            $this->logMaster('autenticar() CSRF inválido', [], true);
            $this->setFlashMessage('Requisição inválida. Tente novamente.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if ($email === '' || $senha === '') {
            $this->setFlashMessage('Preencha e-mail e senha.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $bloqueio = $this->verificarLimiteLoginMaster($email, $ip);
        if ($bloqueio !== null) {
            $this->logMaster('autenticar() bloqueado por rate limit', [
                'motivo' => $bloqueio['motivo'],
                'minutos' => $bloqueio['minutos'],
            ], true);
            $this->setFlashMessage(
                "Muitas tentativas de login. Tente novamente em {$bloqueio['minutos']} minutos.",
                'error'
            );
            header('Location: ' . URL . '/master');
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->query("SELECT id, email, senha_hash, nome, avatar_url FROM usuarios_master WHERE email = ? AND ativo = 1", [$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        $pwOk = $user && password_verify($senha, $user['senha_hash']);

        if (!$user || !$pwOk) {
            $this->registrarFalhaLoginMaster($email, $ip);
            $this->logMaster('autenticar() credenciais inválidas', ['user_found' => $user ? 'sim' : 'nao']);
            $bloqueioApos = $this->verificarLimiteLoginMaster($email, $ip);
            if ($bloqueioApos !== null) {
                $this->logMaster('autenticar() bloqueado após falha', [
                    'motivo' => $bloqueioApos['motivo'],
                    'minutos' => $bloqueioApos['minutos'],
                ], true);
                $this->setFlashMessage(
                    "Muitas tentativas de login. Tente novamente em {$bloqueioApos['minutos']} minutos.",
                    'error'
                );
            } else {
                $this->setFlashMessage('E-mail ou senha incorretos.', 'error');
            }
            header('Location: ' . URL . '/master');
            exit;
        }

        $this->limparFalhaLoginMaster($email);

        try {
            $this->prepararDesafioSegundoFator($user);
        } catch (\RuntimeException $e) {
            $this->logMaster('autenticar() segundo fator não enviado', ['user_id' => (int) $user['id'], 'motivo' => $e->getMessage()], true);
            $this->setFlashMessage($e->getMessage(), 'error');
            header('Location: ' . URL . '/master');
            exit;
        }
        $this->logMaster('autenticar() senha ok, aguardando código', ['user_id' => (int) $user['id']]);
        header('Location: ' . URL . '/master/verificar');
        exit;
    }

    /**
     * Tela do código enviado por e-mail. Ainda não abre o painel.
     */
    public function verificar()
    {
        $this->ensureMultiTenant();
        $this->redirectIfMasterLoggedIn();
        $desafio = $this->desafioSegundoFator();
        if ($desafio === null) {
            header('Location: ' . URL . '/master');
            exit;
        }
        $this->disableCache();
        $this->view('master/verificar', [
            'title' => 'Código de acesso - EducaTudo',
            'csrf_token' => $this->generateCsrfToken(),
            'email_mascarado' => $desafio['email'],
            'flash' => $this->getFlashMessage(),
        ]);
    }

    /**
     * POST: confirma o código e só então abre a sessão master.
     */
    public function confirmarCodigo()
    {
        $this->ensureMultiTenant();
        $this->redirectIfMasterLoggedIn();

        $token = $_POST['csrf_token'] ?? '';
        if ($token === '' || !$this->verifyCsrfToken($token)) {
            $this->setFlashMessage('Requisição inválida. Tente novamente.', 'error');
            header('Location: ' . URL . '/master/verificar');
            exit;
        }

        $desafio = $this->desafioSegundoFator();
        if ($desafio === null) {
            $this->setFlashMessage('Peça um novo código na tela de login.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        $resultado = (new CodigoAcessoMasterService())->confirmar(
            $desafio['usuario_id'],
            $desafio['codigo_id'],
            (string) ($_POST['codigo'] ?? '')
        );
        if ($resultado === 'esgotado') {
            $this->logMaster('confirmarCodigo() tentativas esgotadas', ['user_id' => $desafio['usuario_id']], true);
            $this->setFlashMessage('Código bloqueado depois de várias tentativas. Peça outro.', 'error');
            header('Location: ' . URL . '/master/verificar');
            exit;
        }
        if ($resultado !== 'ok') {
            $this->logMaster('confirmarCodigo() código recusado', ['user_id' => $desafio['usuario_id']]);
            $this->setFlashMessage('Código incorreto ou expirado.', 'error');
            header('Location: ' . URL . '/master/verificar');
            exit;
        }

        $db = Database::getInstance();
        $user = $db->fetch(
            'SELECT id, email, nome, avatar_url FROM usuarios_master WHERE id = :id AND ativo = 1',
            ['id' => $desafio['usuario_id']]
        );
        if (!$user) {
            $this->limparDesafioSegundoFator();
            $this->setFlashMessage('Não foi possível concluir o acesso. Entre de novo.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        $this->limparDesafioSegundoFator();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $this->setMasterSession((int) $user['id'], $user['email'], $user['nome'], $user['avatar_url'] ?? null);
        $this->clearMasterAuthCookie();
        $this->refreshCsrfToken();
        $this->logMaster('confirmarCodigo() login ok', ['user_id' => (int) $user['id']]);
        $this->setFlashMessage('Login realizado.', 'success');
        if (function_exists('session_write_close')) {
            session_write_close();
        }
        header('Location: ' . URL . '/master/dashboard');
        exit;
    }

    /**
     * POST: envia outro código para o mesmo usuário que já passou da senha.
     */
    public function reenviarCodigo()
    {
        $this->ensureMultiTenant();
        $token = $_POST['csrf_token'] ?? '';
        if ($token === '' || !$this->verifyCsrfToken($token)) {
            $this->setFlashMessage('Requisição inválida. Tente novamente.', 'error');
            header('Location: ' . URL . '/master/verificar');
            exit;
        }

        $desafio = $this->desafioSegundoFator();
        if ($desafio === null) {
            header('Location: ' . URL . '/master');
            exit;
        }

        $db = Database::getInstance();
        $user = $db->fetch(
            'SELECT id, email, nome FROM usuarios_master WHERE id = :id AND ativo = 1',
            ['id' => $desafio['usuario_id']]
        );
        if (!$user) {
            $this->limparDesafioSegundoFator();
            $this->setFlashMessage('Não foi possível reenviar o código. Entre de novo.', 'error');
            header('Location: ' . URL . '/master');
            exit;
        }

        try {
            $this->prepararDesafioSegundoFator($user);
        } catch (\RuntimeException $e) {
            $this->logMaster('reenviarCodigo() falhou', ['user_id' => (int) $user['id'], 'motivo' => $e->getMessage()], true);
            $this->setFlashMessage($e->getMessage(), 'error');
        }
        header('Location: ' . URL . '/master/verificar');
        exit;
    }

    /**
     * Abandona o código pendente e volta ao login.
     */
    public function cancelarVerificacao()
    {
        $this->limparDesafioSegundoFator();
        $this->setFlashMessage('Informe e-mail e senha para receber outro código.', 'info');
        header('Location: ' . URL . '/master');
        exit;
    }

    /**
     * Dashboard do painel master (protegido por sessão).
     */
    public function dashboard()
    {
        $this->ensureMultiTenant();
        $this->requireMasterAuth();

        $db = Database::getInstance();
        $totalEscolas = (int) $db->query("SELECT COUNT(*) FROM escolas")->fetchColumn();
        $escolasAtivas = (int) $db->query("SELECT COUNT(*) FROM escolas WHERE ativo = 1")->fetchColumn();
        $escolasComBanco = (int) $db->query("SELECT COUNT(*) FROM escolas e INNER JOIN config_escolas_banco b ON b.escola_id = e.id")->fetchColumn();
        $escolasRecent = $db->query(
            "SELECT e.id, e.nome, e.slug, e.dominio, e.ativo FROM escolas e ORDER BY e.id DESC LIMIT 5"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $todasEscolas = $db->query(
            "SELECT e.id, e.nome, e.slug, e.dominio, e.ativo,
             (SELECT config_value FROM config_escolas_layout c WHERE c.escola_id = e.id AND c.config_key = 'logo_1x1_url' LIMIT 1) AS logo_1x1_url
             FROM escolas e WHERE e.ativo = 1 ORDER BY e.nome"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $totalCreditosConsumidos = 0.0;
        $masterPdo = $GLOBALS['_educatudo_master_pdo'] ?? null;
        if ($masterPdo) {
            if (!class_exists('DatabaseManager', false)) {
            }
            $manager = new DatabaseManager($masterPdo);
            $escolasComBancoIds = $db->query(
                "SELECT e.id FROM escolas e INNER JOIN config_escolas_banco b ON b.escola_id = e.id WHERE e.ativo = 1"
            )->fetchAll(\PDO::FETCH_COLUMN);
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
                } catch (\Throwable $e) {
                    // tenant sem carteira_movimentacoes ou conexão falhou
                }
            }
        }

        $kpis = [
            'total_logins_sucesso' => 0,
            'total_jornadas' => 0,
            'total_provas' => 0,
            'total_exercicios_ia' => 0,
            'total_exercicios' => 0,
            'modulos' => [],
            'gerado_em' => null,
            'disponivel' => false,
        ];
        try {
            $rowKpi = null;
            try {
                $rowKpi = $db->query(
                    "SELECT total_logins_sucesso, total_jornadas, total_provas,
                            total_exercicios_ia, total_exercicios, modulos_json, gerado_em
                     FROM master_dashboard_kpis WHERE id = 1 LIMIT 1"
                )->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $rowKpi = $db->query(
                    "SELECT total_logins_sucesso, total_jornadas, total_provas, modulos_json, gerado_em
                     FROM master_dashboard_kpis WHERE id = 1 LIMIT 1"
                )->fetch(\PDO::FETCH_ASSOC);
            }
            if ($rowKpi) {
                $modulos = json_decode((string) ($rowKpi['modulos_json'] ?? '[]'), true);
                if (!is_array($modulos)) {
                    $modulos = [];
                }
                $kpis = [
                    'total_logins_sucesso' => (int) ($rowKpi['total_logins_sucesso'] ?? 0),
                    'total_jornadas' => (int) ($rowKpi['total_jornadas'] ?? 0),
                    'total_provas' => (int) ($rowKpi['total_provas'] ?? 0),
                    'total_exercicios_ia' => (int) ($rowKpi['total_exercicios_ia'] ?? 0),
                    'total_exercicios' => (int) ($rowKpi['total_exercicios'] ?? 0),
                    'modulos' => $modulos,
                    'gerado_em' => $rowKpi['gerado_em'] ?? null,
                    'disponivel' => true,
                ];
            }
        } catch (\Throwable $e) {
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
            header('Location: ' . URL . '/master/dashboard');
            exit;
        }
        if ($this->desafioSegundoFator() !== null) {
            return;
        }
    }

    private function requireMasterAuth(): void
    {
        if (!empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            return;
        }
        if ($this->desafioSegundoFator() !== null) {
            header('Location: ' . URL . '/master/verificar');
            exit;
        }
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
        $this->limparDesafioSegundoFator();
        $this->clearMasterAuthCookie();
    }

    /**
     * Senha já conferida. Grava só o desafio na sessão; a sessão do painel nasce depois do código.
     *
     * @param array{id: int|string, email: string, nome: string} $user
     */
    private function prepararDesafioSegundoFator(array $user): void
    {
        $desafio = (new CodigoAcessoMasterService())->emitir(
            (int) $user['id'],
            (string) $user['email'],
            (string) $user['nome']
        );
        unset(
            $_SESSION[self::SESSION_MASTER_USER_ID],
            $_SESSION[self::SESSION_MASTER_USER_EMAIL],
            $_SESSION[self::SESSION_MASTER_USER_NOME],
            $_SESSION[self::SESSION_MASTER_USER_AVATAR]
        );
        $this->clearMasterAuthCookie();
        $_SESSION[self::SESSION_2FA_USUARIO_ID] = (int) $user['id'];
        $_SESSION[self::SESSION_2FA_CODIGO_ID] = (int) $desafio['id'];
        $_SESSION[self::SESSION_2FA_EMAIL] = (string) $desafio['email_mascarado'];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $mascara = (string) $desafio['email_mascarado'];
        if (!empty($desafio['reaproveitado'])) {
            $this->setFlashMessage('O código enviado ainda está valendo. Confira ' . $mascara . '.', 'info');
            return;
        }
        $this->setFlashMessage('Enviamos um código de 6 dígitos para ' . $mascara . '.', 'info');
    }

    /**
     * @return array{usuario_id: int, codigo_id: int, email: string}|null
     */
    private function desafioSegundoFator(): ?array
    {
        $usuarioId = (int) ($_SESSION[self::SESSION_2FA_USUARIO_ID] ?? 0);
        $codigoId = (int) ($_SESSION[self::SESSION_2FA_CODIGO_ID] ?? 0);
        if ($usuarioId <= 0 || $codigoId <= 0) {
            return null;
        }

        return [
            'usuario_id' => $usuarioId,
            'codigo_id' => $codigoId,
            'email' => (string) ($_SESSION[self::SESSION_2FA_EMAIL] ?? 'seu e-mail'),
        ];
    }

    private function limparDesafioSegundoFator(): void
    {
        unset(
            $_SESSION[self::SESSION_2FA_USUARIO_ID],
            $_SESSION[self::SESSION_2FA_CODIGO_ID],
            $_SESSION[self::SESSION_2FA_EMAIL]
        );
    }

    /**
     * Mesmos tetos do login admin/monitor: max_login_attempts (5) por e-mail
     * e max_login_attempts_ip por IP, na janela lockout_duration.
     *
     * @return array{motivo: string, minutos: int}|null
     */
    private function verificarLimiteLoginMaster(string $email, string $ip): ?array
    {
        $sec = $this->obterConfigSegurancaMaster();
        $janela = max(60, (int) ($sec['lockout_duration'] ?? 900));
        $maxEmail = max(1, (int) ($sec['max_login_attempts'] ?? 5));
        $maxIp = max(1, (int) ($sec['max_login_attempts_ip'] ?? 50));
        // Alinhado ao AuthManager: bloqueia na tentativa N quando max=N (após N-1 falhas).
        $limiteEmail = max(1, $maxEmail - 1);
        $minutos = max(1, (int) ceil($janela / 60));

        $dados = $this->lerContadoresLoginMaster($janela);
        $chaveEmail = $this->chaveRateLimitEmailMaster($email);
        if ($chaveEmail !== '' && $this->contadorAtivoMaster($dados, $chaveEmail, $janela) >= $limiteEmail) {
            return ['motivo' => 'email', 'minutos' => $minutos];
        }
        $chaveIp = $this->chaveRateLimitIpMaster($ip);
        if ($chaveIp !== '' && $this->contadorAtivoMaster($dados, $chaveIp, $janela) >= $maxIp) {
            return ['motivo' => 'ip', 'minutos' => $minutos];
        }

        return null;
    }

    private function registrarFalhaLoginMaster(string $email, string $ip): void
    {
        $sec = $this->obterConfigSegurancaMaster();
        $janela = max(60, (int) ($sec['lockout_duration'] ?? 900));
        $chaves = array_values(array_filter([
            $this->chaveRateLimitEmailMaster($email),
            $this->chaveRateLimitIpMaster($ip),
        ]));
        if ($chaves === []) {
            return;
        }
        $this->atualizarContadoresLoginMaster($janela, static function (array $dados) use ($chaves, $janela): array {
            $agora = time();
            foreach ($chaves as $chave) {
                $entrada = $dados[$chave] ?? ['c' => 0, 't' => $agora];
                if (!is_array($entrada) || ($agora - (int) ($entrada['t'] ?? $agora)) > $janela) {
                    $entrada = ['c' => 0, 't' => $agora];
                }
                $entrada['c'] = (int) ($entrada['c'] ?? 0) + 1;
                $entrada['t'] = (int) ($entrada['t'] ?? $agora);
                $dados[$chave] = $entrada;
            }
            return $dados;
        });
    }

    /** Sucesso na senha zera só o contador do e-mail; o de IP segue na janela. */
    private function limparFalhaLoginMaster(string $email): void
    {
        $sec = $this->obterConfigSegurancaMaster();
        $janela = max(60, (int) ($sec['lockout_duration'] ?? 900));
        $chave = $this->chaveRateLimitEmailMaster($email);
        if ($chave === '') {
            return;
        }
        $this->atualizarContadoresLoginMaster($janela, static function (array $dados) use ($chave): array {
            unset($dados[$chave]);
            return $dados;
        });
    }

    /** @return array<string, mixed> */
    private function obterConfigSegurancaMaster(): array
    {
        $configPath = defined('CONFIG_PATH')
            ? CONFIG_PATH
            : ((defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4)) . '/config/app.php');
        $config = is_file($configPath) ? require $configPath : [];

        return is_array($config['security'] ?? null) ? $config['security'] : [];
    }

    private function arquivoRateLimitMaster(): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $dir = $base . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . '/master_login_rate_limit.json';
    }

    private function chaveRateLimitEmailMaster(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return '';
        }

        return 'email:' . hash('sha256', $email);
    }

    private function chaveRateLimitIpMaster(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '' || $ip === 'unknown') {
            return '';
        }

        return 'ip:' . $ip;
    }

    /** @return array<string, array{c: int, t: int}> */
    private function lerContadoresLoginMaster(int $janela): array
    {
        $arquivo = $this->arquivoRateLimitMaster();
        if (!is_file($arquivo)) {
            return [];
        }
        $fp = @fopen($arquivo, 'c+');
        if (!$fp || !flock($fp, LOCK_SH)) {
            if ($fp) {
                fclose($fp);
            }
            return [];
        }
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $dados = $raw !== false && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        if (!is_array($dados)) {
            return [];
        }
        $agora = time();
        foreach ($dados as $chave => $entrada) {
            if (!is_array($entrada) || ($agora - (int) ($entrada['t'] ?? 0)) > $janela) {
                unset($dados[$chave]);
            }
        }

        return $dados;
    }

    private function contadorAtivoMaster(array $dados, string $chave, int $janela): int
    {
        $entrada = $dados[$chave] ?? null;
        if (!is_array($entrada)) {
            return 0;
        }
        $agora = time();
        if (($agora - (int) ($entrada['t'] ?? 0)) > $janela) {
            return 0;
        }

        return (int) ($entrada['c'] ?? 0);
    }

    /**
     * @param callable(array): array $mutator
     */
    private function atualizarContadoresLoginMaster(int $janela, callable $mutator): void
    {
        $arquivo = $this->arquivoRateLimitMaster();
        $fp = @fopen($arquivo, 'c+');
        if (!$fp || !flock($fp, LOCK_EX)) {
            if ($fp) {
                fclose($fp);
            }
            return;
        }
        $raw = stream_get_contents($fp);
        $dados = $raw !== false && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        if (!is_array($dados)) {
            $dados = [];
        }
        $agora = time();
        foreach ($dados as $chave => $entrada) {
            if (!is_array($entrada) || ($agora - (int) ($entrada['t'] ?? 0)) > $janela) {
                unset($dados[$chave]);
            }
        }
        $dados = $mutator($dados);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($dados));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

}

if (!class_exists('MasterAuthController', false) && class_exists(__NAMESPACE__ . '\\MasterAuthController', false)) {
    class_alias(__NAMESPACE__ . '\\MasterAuthController', 'MasterAuthController');
}
