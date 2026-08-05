<?php
/**
 * Redireciona alunos ao portal EducaHits externo.
 */

if (!class_exists('EducaHitsPortalController')) {

require_once __DIR__ . '/../../Core/LayoutHelper.php';
require_once __DIR__ . '/../../Core/EducaHitsConfig.php';

class EducaHitsPortalController extends BaseController
{
    private $auth;
    private $db;
    private const TOKENS_TABLE_UNIFIED = 'validacao_tokens_apps';

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $this->ensureModule();
    }

    private function ensureModule(): void
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('module_educa_hits', '0') !== '1') {
            $this->setFlashMessage('O módulo EducaHits está desabilitado.', 'error');
            $this->redirect('/dashboard');
            exit;
        }
    }

    /**
     * Tenta resolver o app EducaHits em external_apps_links para abrir via token,
     * no mesmo fluxo do Games/External Apps.
     */
    private function resolveEducaHitsExternalRoute(): string
    {
        $raw = (string) LayoutHelper::get('external_apps_links', '[]');
        $links = json_decode($raw, true);
        if (!is_array($links)) {
            return '';
        }
        foreach ($links as $idx => $link) {
            if (!is_array($link) || empty($link['aluno'])) {
                continue;
            }
            $id = trim((string) ($link['id'] ?? $idx));
            if ($id === '') {
                continue;
            }
            $name = strtolower(trim((string) ($link['nome'] ?? $link['label'] ?? '')));
            $key = strtolower(trim((string) ($link['app'] ?? $link['id'] ?? '')));
            $isEducaHits = (
                strpos($name, 'educahits') !== false
                || strpos($name, 'educa hits') !== false
                || strpos($key, 'educahits') !== false
                || strpos($key, 'educa_hits') !== false
            );
            if ($isEducaHits) {
                return rtrim(URL, '/') . '/external-apps/abrir/' . rawurlencode($id);
            }
        }

        return '';
    }

    private function generateAccessToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function ensureValidacaoTokensAppsTableExists(): void
    {
        if ($this->db->tableExists(self::TOKENS_TABLE_UNIFIED)) {
            return;
        }
        require_once __DIR__ . '/../../Core/Logger.php';
        Logger::warning(
            'Tabela ' . self::TOKENS_TABLE_UNIFIED . ' não existe neste tenant — criando via fallback. '
                . 'Execute a migration 004_validacao_tokens_apps.sql para este tenant.',
            ['tenant_slug' => $this->detectTenantSlug()],
            'educahits'
        );
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
            // segue fluxo mesmo se não conseguir criar aqui (ambiente pode estar read-only no tenant)
        }
    }

    private function detectTenantSlug(): string
    {
        $fromSession = trim((string) ($_SESSION['tenant_slug'] ?? $_SESSION['school_slug'] ?? $_SESSION['escola_slug'] ?? ''));
        if ($fromSession !== '') {
            return strtolower($fromSession);
        }
        $fromConfig = trim((string) ($this->config['tenant']['slug'] ?? $this->config['school']['code'] ?? ''));
        if ($fromConfig !== '') {
            return strtolower($fromConfig);
        }
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $parts = explode('.', $host);
            if (count($parts) > 2) {
                return trim((string) $parts[0]);
            }
        }

        return '';
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

    private function mirrorTokenToUnifiedTable(string $token, array $user): void
    {
        try {
            $this->ensureValidacaoTokensAppsTableExists();
            $slug = $this->detectTenantSlug();
            $nickname = $this->fetchStudentNickname((int) ($user['id'] ?? 0));
            $this->db->insert(
                "INSERT INTO " . self::TOKENS_TABLE_UNIFIED . " (token, app, user_id, user_name, user_nickname, tenant_slug, created_at, expires_at, used)
                 VALUES (?, 'educahits', ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)",
                [$token, (int) ($user['id'] ?? 0), (string) ($user['nome'] ?? ''), $nickname, $slug]
            );
        } catch (Throwable $e) {
            // se não espelhar token, fallback mantém comportamento legado de URL estática
        }
    }

    private function buildPortalUrlWithDynamicToken(string $baseUrl, string $token): string
    {
        $parts = parse_url($baseUrl);
        if (!is_array($parts)) {
            return $baseUrl;
        }
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }
        $query['token'] = $token;
        $slug = $this->detectTenantSlug();
        if ($slug !== '') {
            $query['slug'] = $slug;
        }
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        if ($host === '') {
            return $baseUrl;
        }
        $url = $scheme . '://' . $host . $port . $path . '?' . http_build_query($query);
        if (!empty($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }

    /** GET /hits, /hits/demo-player, /hits/my-requests, /hits/track/{id} */
    public function toPortal(): void
    {
        $tokenRoute = $this->resolveEducaHitsExternalRoute();
        if ($tokenRoute !== '') {
            header('Location: ' . $tokenRoute, true, 302);
            exit;
        }
        $user = $this->auth->getUser();
        if (!is_array($user) || empty($user['id'])) {
            $this->setFlashMessage('Não foi possível identificar o aluno para gerar o token do EducaHits.', 'error');
            $this->redirect('/dashboard');
            return;
        }

        $token = $this->generateAccessToken();
        if (!is_string($token) || trim($token) === '') {
            $this->setFlashMessage('Não foi possível gerar o token do EducaHits. Tente novamente.', 'error');
            $this->redirect('/dashboard');
            return;
        }

        $this->mirrorTokenToUnifiedTable($token, $user);
        $dynamicUrl = $this->buildPortalUrlWithDynamicToken(EducaHitsConfig::portalLoginUrl(), $token);

        $query = [];
        $rawQuery = (string) (parse_url($dynamicUrl, PHP_URL_QUERY) ?? '');
        if ($rawQuery !== '') {
            parse_str($rawQuery, $query);
        }
        if (trim((string) ($query['token'] ?? '')) === '') {
            $this->setFlashMessage('Token do EducaHits não encontrado no redirecionamento. Verifique a configuração do portal.', 'error');
            $this->redirect('/dashboard');
            return;
        }

        header('Location: ' . $dynamicUrl, true, 302);
        exit;
    }

    /**
     * Recupera dados básicos do aluno logado para preencher e salvar pedido.
     *
     * @return array<string,mixed>|null
     */
    private function getStudentProfile(): ?array
    {
        $user = $this->auth->getUser();
        if (!$user || (string) ($user['tipo'] ?? '') !== 'aluno') {
            return null;
        }
        try {
            $row = $this->db->fetch(
                "SELECT a.id, a.nome, a.turma_id, a.serie, t.nome AS turma_nome
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE a.id = :id
                 LIMIT 1",
                ['id' => (int) $user['id']]
            );
            if (!is_array($row)) {
                return null;
            }

            return $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Lista matérias para preencher o select no formulário.
     *
     * @return array<int,string>
     */
    private function getSubjectOptions(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT nome FROM materias WHERE nome IS NOT NULL AND nome <> '' ORDER BY nome ASC"
            );
            $subjects = [];
            foreach ((array) $rows as $row) {
                $name = trim((string) ($row['nome'] ?? ''));
                if ($name !== '') {
                    $subjects[] = $name;
                }
            }
            if (!empty($subjects)) {
                return array_values(array_unique($subjects));
            }
        } catch (Throwable $e) {
            // fallback para lista padrão abaixo
        }

        return [
            'Português',
            'Matemática',
            'Ciências',
            'História',
            'Geografia',
            'Inglês',
            'Artes',
            'Educação Física',
            'Física',
            'Química',
            'Biologia',
            'Filosofia',
            'Sociologia',
        ];
    }

    /**
     * GET /hits/request, /hits/solicitar
     * Exibe formulário local de pedido.
     */
    public function toSolicitar(): void
    {
        $user = $this->auth->getUser();
        $aluno = $this->getStudentProfile();
        if (!$user || !$aluno) {
            $this->setFlashMessage('Não foi possível carregar os dados do aluno para pedir música.', 'error');
            $this->redirect('/dashboard');
            return;
        }
        $subjectOptions = $this->getSubjectOptions();

        $recentRequests = [];
        try {
            $recentRequests = $this->db->fetchAll(
                "SELECT id, subject, topic, music_style, status, created_at
                 FROM educa_hits_requests
                 WHERE user_id = :uid
                 ORDER BY created_at DESC
                 LIMIT 12",
                ['uid' => (int) $aluno['id']]
            );
        } catch (Throwable $e) {
            $recentRequests = [];
        }

        $this->viewWithLayout('student', 'student/educa-hits/request', [
            'title' => 'Pedir música - EducaHits',
            'page_title' => 'EducaHits — pedir músicas',
            'current_page' => 'dashboard',
            'user' => $user,
            'aluno' => $aluno,
            'flash' => $this->getFlashMessage(),
            'csrf_token' => $this->generateCsrfToken(),
            'recent_requests' => $recentRequests,
            'subject_options' => $subjectOptions,
        ]);
    }

    /**
     * POST /hits/request
     * Salva pedido local na tabela educa_hits_requests.
     */
    public function submitRequest(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('/hits/request');
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Atualize a página e tente novamente.', 'error');
            $this->redirect('/hits/request');
            return;
        }

        $aluno = $this->getStudentProfile();
        if (!$aluno) {
            $this->setFlashMessage('Não foi possível identificar o aluno para salvar o pedido.', 'error');
            $this->redirect('/hits/request');
            return;
        }

        $subject = trim((string) ($_POST['subject'] ?? ''));
        $topic = trim((string) ($_POST['topic'] ?? ''));
        $musicStyle = trim((string) ($_POST['music_style'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($subject === '' || $topic === '') {
            $this->setFlashMessage('Preencha ao menos matéria e tema para pedir a música.', 'error');
            $this->redirect('/hits/request');
            return;
        }

        try {
            $this->db->query(
                "INSERT INTO educa_hits_requests
                 (user_id, class_id, grade, subject, topic, music_style, description, status)
                 VALUES
                 (:user_id, :class_id, :grade, :subject, :topic, :music_style, :description, 'pending')",
                [
                    'user_id' => (int) $aluno['id'],
                    'class_id' => isset($aluno['turma_id']) ? (int) $aluno['turma_id'] : null,
                    'grade' => trim((string) ($aluno['serie'] ?? $aluno['turma_nome'] ?? '')),
                    'subject' => $subject,
                    'topic' => $topic,
                    'music_style' => $musicStyle !== '' ? $musicStyle : null,
                    'description' => $description !== '' ? $description : null,
                ]
            );
            $this->setFlashMessage('Pedido enviado com sucesso! Nossa equipe vai preparar sua música.', 'success');
        } catch (Throwable $e) {
            $this->setFlashMessage('Não foi possível salvar o pedido agora. Verifique se a tabela de pedidos está criada.', 'error');
        }

        $this->redirect('/hits/request');
    }
}
}
