<?php
/**
 * EducaTudo - Controller de Jogos
 * Gerencia a página principal de jogos
 */

require_once __DIR__ . '/../../Core/LayoutHelper.php';
require_once __DIR__ . '/../../Core/GamesAccessSchedule.php';

if (!class_exists('GameController')) {
class GameController extends BaseController
{
    private const TOKENS_TABLE_UNIFIED = 'validacao_tokens_apps';
    // Quando true, força bloqueio total do Games para alunos.
    private const GAMES_BLOQUEADO_ALUNO = false;

    private $authManager;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        
        // Verifica se está logado
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
        
        // Verifica se é aluno
        $user = $this->authManager->getUser();
        if ($user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }
    
    /**
     * Página principal de jogos
     */
    public function index()
    {
        return $this->access();
    }

    /**
     * Acesso externo ao Games com token
     */
    public function access()
    {
        $user = $this->authManager->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->redirect('/');
        }

        if (self::GAMES_BLOQUEADO_ALUNO) {
            $this->setFlashMessage('Games está temporariamente desabilitado para alunos.', 'error');
            $this->redirect('/dashboard');
            return;
        }

        if (!GamesAccessSchedule::isAccessAllowedNow()) {
            $this->setFlashMessage(GamesAccessSchedule::studentBlockedMessage(), 'error');
            $this->redirect('/dashboard');
            return;
        }

        $token = $this->generateAccessToken();
        $this->db->insert(
            "INSERT INTO jogos_tokens_externos (token, user_id, user_name, created_at, expires_at, used)
             VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)",
            [$token, $user['id'], $user['nome'] ?? '']
        );

        // Compatibilidade com validação unificada (master/external-apps/validate-token).
        $this->mirrorTokenToUnifiedTable($token, $user);

        $url = $this->buildExternalAccessUrl($token);
        header('Location: ' . $url);
        exit;
    }

    public function validateToken()
    {
        $token = trim($_GET['token'] ?? '');
        if ($token === '') {
            $this->json(['error' => 'Token não fornecido'], 400);
            return;
        }

        $row = $this->db->fetch(
            "SELECT t.user_id, t.user_name
             FROM jogos_tokens_externos t
             WHERE t.token = ? AND t.expires_at > NOW()",
            [$token]
        );

        if (!$row) {
            $this->writeValidationFailLog('games', 'validate.fail', [
                'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
                'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            ]);
            $this->json(['error' => 'Token inválido ou expirado'], 401);
            return;
        }

        // Não marcar used=1: permite refresh / reabrir no mesmo link (igual Notes).
        // Token válido até expires_at (5 min); usado só no logout.

        $this->json([
            'userId' => (string) $row['user_id'],
            'userName' => $row['user_name']
        ]);
    }

    public function logoutToken()
    {
        $token = trim($_POST['token'] ?? '');
        if ($token === '') {
            $this->json(['error' => 'Token não fornecido'], 400);
            return;
        }

        $this->db->delete("DELETE FROM jogos_tokens_externos WHERE token = ?", [$token]);
        $this->json(['success' => true]);
    }

    private function generateAccessToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Monta a URL do jogo com apenas token e slug.
     * O app já sabe que deve validar em master.educatudo.com/external-apps/validate-token.
     */
    private function buildExternalAccessUrl(string $token): string
    {
        $baseUrl = trim((string) LayoutHelper::get('games_external_url', 'https://games.educatudo.com'));
        if ($baseUrl === '') {
            $baseUrl = 'https://games.educatudo.com';
        }
        $slug = $this->getSlugForExternalLink();

        if (strpos($baseUrl, '{token}') !== false || strpos($baseUrl, '{slug}') !== false) {
            $url = str_replace('{token}', urlencode($token), $baseUrl);
            $url = str_replace('{slug}', urlencode($slug), $url);
            return $url;
        }

        $parts = parse_url($baseUrl);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['token'] = $token;
        if ($slug !== '') {
            $query['slug'] = $slug;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $base = $host !== '' ? ($scheme . '://' . $host . $port . $path) : $baseUrl;
        $url = $base . '?' . http_build_query($query);
        if (!empty($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }
        return $url;
    }

    private function mirrorTokenToUnifiedTable(string $token, array $user): void
    {
        try {
            $this->ensureValidacaoTokensAppsTableExists();
            $slug = $this->getSlugForExternalLink();
            $nickname = $this->fetchStudentNickname((int) ($user['id'] ?? 0));
            $this->db->insert(
                "INSERT INTO " . self::TOKENS_TABLE_UNIFIED . " (token, app, user_id, user_name, user_nickname, tenant_slug, created_at, expires_at, used)
                 VALUES (?, 'games', ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)",
                [$token, (int) ($user['id'] ?? 0), (string) ($user['nome'] ?? ''), $nickname, $slug]
            );
        } catch (Throwable $e) {
            error_log('[GameController] Falha ao espelhar token em validacao_tokens_apps: ' . $e->getMessage());
        }
    }

    private function ensureValidacaoTokensAppsTableExists(): void
    {
        if ($this->db->tableExists(self::TOKENS_TABLE_UNIFIED)) {
            return;
        }
        try {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS " . self::TOKENS_TABLE_UNIFIED . " (
                    id INT NOT NULL AUTO_INCREMENT,
                    token VARCHAR(128) NOT NULL,
                    app VARCHAR(100) DEFAULT NULL,
                    user_id INT NOT NULL,
                    user_name VARCHAR(100) NOT NULL,
                    user_nickname VARCHAR(100) DEFAULT NULL,
                    tenant_slug VARCHAR(120) DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used TINYINT(1) DEFAULT 0,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_token (token),
                    KEY idx_token (token),
                    KEY idx_expires_at (expires_at),
                    KEY idx_tenant_slug (tenant_slug),
                    KEY idx_app (app)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            error_log('[GameController] Falha ao criar validacao_tokens_apps: ' . $e->getMessage());
        }
    }

    private function fetchStudentNickname(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        try {
            $row = $this->db->fetch("SELECT nickname FROM alunos WHERE id = ? LIMIT 1", [$userId]);
            return trim((string) ($row['nickname'] ?? ''));
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Slug a enviar no link: prioriza o campo "Slug da escola" (config); senão detectTenantSlug().
     */
    private function getSlugForExternalLink(): string
    {
        $slugFromConfig = trim((string) LayoutHelper::get('external_institution_id', ''));
        $slug = ($slugFromConfig !== '' && !ctype_digit($slugFromConfig))
            ? strtolower($slugFromConfig)
            : $this->detectTenantSlug();
        return $this->slugForExternalLink($slug, $slugFromConfig);
    }

    private function detectTenantSlug(): string
    {
        $hostSlug = $this->getSlugFromHost();
        $fromSession = trim((string) ($_SESSION['tenant_slug'] ?? $_SESSION['school_slug'] ?? $_SESSION['escola_slug'] ?? ''));
        $fromConfig = trim((string) ($this->config['tenant']['slug'] ?? $this->config['school']['code'] ?? ''));
        $fromLayout = trim((string) LayoutHelper::get('tenant_slug', ''));

        if ($hostSlug !== '' && !ctype_digit($hostSlug)) {
            return strtolower($hostSlug);
        }
        if ($fromSession !== '' && !ctype_digit($fromSession)) {
            return strtolower($fromSession);
        }
        if ($fromConfig !== '' && !ctype_digit($fromConfig)) {
            return strtolower($fromConfig);
        }
        if ($fromLayout !== '' && !ctype_digit($fromLayout)) {
            return strtolower($fromLayout);
        }
        if ($hostSlug !== '') {
            return strtolower($hostSlug);
        }
        if ($fromSession !== '') {
            return strtolower($fromSession);
        }
        if ($fromConfig !== '') {
            return strtolower($fromConfig);
        }
        if ($fromLayout !== '') {
            return strtolower($fromLayout);
        }
        return '';
    }

    private function getSlugFromHost(): string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        if ($host === '') {
            return '';
        }
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            return strtolower(trim((string) $parts[0]));
        }
        return '';
    }

    /**
     * Slug a ser enviado no link externo. Nunca envia slug puramente numérico (evita ID/inst como slug).
     * Se o único valor disponível for numérico, retorna vazio; o app pode enviar inst na validação.
     */
    private function slugForExternalLink(string $detectedSlug, string $instId): string
    {
        $s = strtolower(trim($detectedSlug));
        if ($s !== '' && ctype_digit($s)) {
            return '';
        }
        return $s;
    }

    /** Grava falha na tabela do tenant para o Master ver em Apps Externos. */
    private function writeValidationFailLog(string $app, string $evento, array $detalhes = []): void
    {
        try {
            $json = json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->db->insert(
                "INSERT INTO log_validacao_apps_externos (app, evento, detalhes) VALUES (?, ?, ?)",
                [$app, $evento, $json]
            );
        } catch (Throwable $e) {
            error_log('[GameController][writeValidationFailLog] ' . $e->getMessage());
        }
    }
}
}
