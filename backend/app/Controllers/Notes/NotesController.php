<?php
/**
 * EducaTudo - Controller Notes (notes.educatudo.com)
 * Igual ao Games: gera token, redireciona para a URL externa com ?token=...
 * Validação e logout em NotesTokenController (rotas públicas).
 */

require_once __DIR__ . '/../../Core/LayoutHelper.php';

if (!class_exists('NotesController')) {
class NotesController extends BaseController
{
    private $authManager;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();

        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }

        $user = $this->authManager->getUser();
        if (($user['tipo'] ?? '') !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }

    /**
     * Redireciona para notes.educatudo.com?token=... (usado quando abrir na mesma guia).
     */
    public function access()
    {
        $url = $this->getNotesUrlWithToken();
        if ($url === '') {
            $this->redirect('/');
            return;
        }
        header('Location: ' . $url);
        exit;
    }

    /**
     * Retorna a URL do Notes com token (JSON). Para o link abrir notes.educatudo.com em outra página.
     */
    public function accessUrl()
    {
        $url = $this->getNotesUrlWithToken();
        if ($url === '') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        $this->json(['url' => $url]);
    }

    /** Grava em storage/logs/validate_token.log (diagnóstico). */
    private static function writeTokenLog(string $source, string $message): void
    {
        $dir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/validate_token.log';
        $line = '[' . date('Y-m-d H:i:s') . '] [' . $source . '] ' . $message . "\n";
        @file_put_contents($file, $line, FILE_APPEND);
    }

    private function getNotesUrlWithToken(): string
    {
        $user = $this->authManager->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            return '';
        }

        $token = $this->generateAccessToken();
        try {
            $this->db->insert(
                "INSERT INTO notes_tokens (token, user_id, user_name, created_at, expires_at, used)
                 VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)",
                [$token, $user['id'], $user['nome'] ?? '']
            );
        } catch (Exception $e) {
            $msg = '[Notes access] Falha ao inserir token: ' . $e->getMessage() . ' user_id=' . ($user['id'] ?? '');
            error_log($msg);
            self::writeTokenLog('notes', $msg);
            return '';
        }
        $fp = substr($token, 0, 8) . '...' . substr($token, -8);
        $expiresAt = $this->db->fetch("SELECT expires_at FROM notes_tokens WHERE token = ?", [$token]);
        $expStr = $expiresAt['expires_at'] ?? '?';
        self::writeTokenLog('notes', 'CRIADO user_id=' . $user['id'] . ' fingerprint=' . $fp . ' expires_at=' . $expStr);
        $this->mirrorTokenToUnifiedTable($token, $user);
        return $this->buildExternalAccessUrl($token);
    }

    /** Espelha o token em validacao_tokens_apps para validação unificada (apenas essa tabela). */
    private function mirrorTokenToUnifiedTable(string $token, array $user): void
    {
        try {
            $this->ensureValidacaoTokensAppsTableExists();
            $slug = $this->getSlugForExternalLink();
            $nickname = $this->fetchStudentNickname((int) ($user['id'] ?? 0));
            $this->db->insert(
                "INSERT INTO validacao_tokens_apps (token, app, user_id, user_name, user_nickname, tenant_slug, created_at, expires_at, used)
                 VALUES (?, 'notes', ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)",
                [$token, (int) ($user['id'] ?? 0), (string) ($user['nome'] ?? ''), $nickname, $slug]
            );
        } catch (Throwable $e) {
            error_log('[NotesController] Falha ao espelhar token em validacao_tokens_apps: ' . $e->getMessage());
        }
    }

    private function ensureValidacaoTokensAppsTableExists(): void
    {
        if ($this->db->tableExists('validacao_tokens_apps')) {
            return;
        }
        try {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS validacao_tokens_apps (
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
            error_log('[NotesController] Falha ao criar validacao_tokens_apps: ' . $e->getMessage());
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

    private function generateAccessToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Monta a URL do Notes com apenas token e slug.
     * O app já sabe que deve validar em master.educatudo.com/external-apps/validate-token.
     */
    private function buildExternalAccessUrl(string $token): string
    {
        $baseUrl = trim((string) LayoutHelper::get('notes_external_url', 'https://notes.educatudo.com'));
        if ($baseUrl === '') {
            $baseUrl = 'https://notes.educatudo.com';
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
        $host   = $parts['host'] ?? '';
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path   = $parts['path'] ?? '/';
        $base   = $host !== '' ? ($scheme . '://' . $host . $port . $path) : $baseUrl;
        $url    = $base . '?' . http_build_query($query);
        if (!empty($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }
        return $url;
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
     * Nunca envia slug puramente numérico no link (evita ID como slug). App pode enviar inst na validação.
     */
    private function slugForExternalLink(string $detectedSlug, string $instId): string
    {
        $s = strtolower(trim($detectedSlug));
        if ($s !== '' && ctype_digit($s)) {
            return '';
        }
        return $s;
    }
}
}
