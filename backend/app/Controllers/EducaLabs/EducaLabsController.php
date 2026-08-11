<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Core/LayoutHelper.php';

if (!class_exists('EducaLabsController')) {
class EducaLabsController extends BaseController
{
    private $authManager;
    private $storageDir;
    private $publicDir;
    private $db;
    private $externalBaseUrl = 'https://educalabs.educatudo.com/login';

    /** Grava em storage/logs/validate_token.log para diagnóstico (independente do error_log do PHP). */
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
            error_log('[EducaLabs][writeValidationFailLog] ' . $e->getMessage());
        }
    }

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->storageDir = __DIR__ . '/../../storage/educalabs/projects';
        $this->publicDir = __DIR__ . '/../../public/educalabs-projects';
        $this->db = Database::getInstance();
    }

    public function index()
    {
        $user = $this->authManager->getUser();
        $this->ensureAluno($user);

        $projects = $this->listProjects($user['id']);

        $data = [
            'title' => 'EducaLabs - EducaTudo',
            'user' => $user,
            'projects' => $projects,
            'current_page' => 'educalabs',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/educalabs/index', $data);
    }

    /**
     * Inicia acesso ao EducaLabs externo.
     * Igual ao Games: gera token, redireciona para a URL externa com token (e inst) na query string.
     * O app externo recebe a URL e valida o token em GET /educalabs/validate-token.
     */
    public function access()
    {
        $user = $this->authManager->getUser();
        $this->ensureAluno($user);

        $token = $this->generateAccessToken();
        try {
            $this->db->insert(
                "INSERT INTO educalabs_tokens (token, user_id, user_name, created_at, expires_at, used)
                 VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)",
                [$token, $user['id'], $user['nome'] ?? '']
            );
        } catch (Exception $e) {
            $msg = '[EducaLabs access] Falha ao inserir token: ' . $e->getMessage() . ' user_id=' . ($user['id'] ?? '');
            error_log($msg);
            self::writeTokenLog('educalabs', $msg);
            $this->setFlashMessage('Não foi possível gerar o acesso ao EducaLabs. Tente novamente.', 'error');
            $this->redirect('/educalabs');
            return;
        }
        self::writeTokenLog('educalabs', 'Token criado para user_id=' . $user['id'] . ' (prefixo: ' . substr($token, 0, 8) . '...)');
        $this->mirrorTokenToUnifiedTable($token, $user);
        $url = $this->buildExternalAccessUrl($token);
        header('Location: ' . $url);
        exit;
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
                 VALUES (?, 'educalabs', ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)",
                [$token, (int) ($user['id'] ?? 0), (string) ($user['nome'] ?? ''), $nickname, $slug]
            );
        } catch (Throwable $e) {
            error_log('[EducaLabs] Falha ao espelhar token em validacao_tokens_apps: ' . $e->getMessage());
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
            error_log('[EducaLabs] Falha ao criar validacao_tokens_apps: ' . $e->getMessage());
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
     * Valida token do EducaLabs, Games ou Notes.
     * Um único endpoint aceita tokens gerados por qualquer um dos três (app pode chamar sempre /educalabs/validate-token).
     * Retorna userId, userName e inst (instituição). Token válido por 5–15 min conforme origem.
     * IMPORTANTE: Este endpoint é público (FeatureGate); nunca redirecionar — sempre retornar JSON.
     */
    public function validateToken()
    {
        header('Content-Type: application/json; charset=utf-8');
        $token = trim($_GET['token'] ?? '');
        if ($token === '') {
            $this->json(['error' => 'Token não fornecido'], 400);
            return;
        }

        $row = $this->db->fetch(
            "SELECT t.user_id, t.user_name
             FROM educalabs_tokens t
             WHERE t.token = ? AND t.expires_at > NOW() AND t.used = 0",
            [$token]
        );
        $fromTable = $row ? 'educalabs' : null;

        if (!$row) {
            $row = $this->db->fetch(
                "SELECT t.user_id, t.user_name
                 FROM jogos_tokens_externos t
                 WHERE t.token = ? AND t.expires_at > NOW()",
                [$token]
            );
            if ($row) {
                $fromTable = 'games';
            }
        }

        if (!$row) {
            $row = $this->db->fetch(
                "SELECT t.user_id, t.user_name
                 FROM notes_tokens t
                 WHERE t.token = ? AND t.expires_at > NOW()",
                [$token]
            );
            if ($row) {
                $fromTable = 'notes';
            }
        }

        if (!$row) {
            // Diagnóstico: saber exatamente por que falhou (inclui notes_tokens)
            $diagE = $this->db->fetch("SELECT expires_at, used FROM educalabs_tokens WHERE token = ?", [$token]);
            $diagG = $this->db->fetch("SELECT expires_at, used FROM jogos_tokens_externos WHERE token = ?", [$token]);
            $diagN = $this->db->fetch("SELECT expires_at, used FROM notes_tokens WHERE token = ?", [$token]);
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if ($diagE) {
                if ((int) $diagE['used'] === 1) {
                    $msg = '[EducaLabs validateToken] Token em educalabs_tokens já utilizado (used=1). uri=' . $uri;
                } else {
                    $msg = '[EducaLabs validateToken] Token em educalabs_tokens expirado (expires_at=' . ($diagE['expires_at'] ?? 'null') . '). uri=' . $uri;
                }
            } elseif ($diagG) {
                if ((int) $diagG['used'] === 1) {
                    $msg = '[EducaLabs validateToken] Token em jogos_tokens_externos já utilizado (used=1). uri=' . $uri;
                } else {
                    $msg = '[EducaLabs validateToken] Token em jogos_tokens_externos expirado (expires_at=' . ($diagG['expires_at'] ?? 'null') . '). uri=' . $uri;
                }
            } elseif ($diagN) {
                if ((int) $diagN['used'] === 1) {
                    $msg = '[EducaLabs validateToken] Token em notes_tokens já utilizado (used=1). uri=' . $uri;
                } else {
                    $msg = '[EducaLabs validateToken] Token em notes_tokens expirado (expires_at=' . ($diagN['expires_at'] ?? 'null') . '). uri=' . $uri;
                }
            } else {
                $msg = '[EducaLabs validateToken] Token não encontrado em educalabs_tokens, jogos_tokens_externos nem notes_tokens. uri=' . $uri;
            }
            error_log($msg);
            self::writeTokenLog('educalabs', $msg);
            $appLog = $diagE ? 'educalabs' : ($diagG ? 'games' : ($diagN ? 'notes' : 'unknown'));
            $this->writeValidationFailLog($appLog, 'validate.fail', [
                'message' => $msg,
                'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
            ]);
            $instConfigured = trim((string) LayoutHelper::get('external_institution_id', ''));
            $this->json([
                'error' => 'Token inválido ou expirado',
                'inst_configured' => $instConfigured,
                'hint' => 'Verifique: GET .../educalabs/validate-token?token=... ou, para Notes, use .../notes/validate-token?token=... (janela 5 min).',
                'endpoints' => ['/educalabs/validate-token', '/games/validate-token', '/notes/validate-token'],
            ], 401);
            return;
        }

        if ($fromTable === 'educalabs') {
            $this->db->update("UPDATE educalabs_tokens SET used = 1 WHERE token = ?", [$token]);
        }
        // games e notes: não marcar used=1 aqui, para permitir refresh / reabrir no mesmo link

        $instId = trim((string) LayoutHelper::get('external_institution_id', ''));

        $this->json([
            'userId'   => (string) $row['user_id'],
            'userName' => $row['user_name'],
            'inst'     => $instId
        ]);
    }

    public function logoutToken()
    {
        $token = trim($_POST['token'] ?? '');
        if ($token === '') {
            $this->json(['error' => 'Token não fornecido'], 400);
            return;
        }

        $this->db->delete("DELETE FROM educalabs_tokens WHERE token = ?", [$token]);
        $this->json(['success' => true]);
    }

    public function create()
    {
        $user = $this->authManager->getUser();
        $this->ensureAluno($user);

        $data = [
            'title' => 'Novo Projeto - EducaLabs',
            'user' => $user,
            'current_page' => 'educalabs',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/educalabs/create', $data);
    }

    public function store()
    {
        $user = $this->authManager->getUser();
        $this->ensureAluno($user);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/educalabs');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $this->setFlashMessage('Informe o nome do projeto.', 'error');
            $this->redirect('/educalabs/novo');
            return;
        }

        $projectId = $this->generateProjectId();
        $shareId = $this->generateShareId();

        $defaultHtml = '<h1>Seu app vai aparecer aqui</h1><p>Descreva o que você quer criar no EducaLabs.</p>';
        $defaultCss = "body { font-family: Arial, sans-serif; padding: 24px; color: #111827; }\n";
        $defaultJs = "// Scripts do seu app\n";

        $projectDbId = $this->db->insert(
            "INSERT INTO educalabs_projects
             (public_id, share_id, owner_id, name, description, html, css, js, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$projectId, $shareId, $user['id'], $name, $description ?: null, $defaultHtml, $defaultCss, $defaultJs]
        );

        $project = [
            'db_id' => $projectDbId,
            'id' => $projectId,
            'share_id' => $shareId,
            'owner_id' => $user['id'],
            'name' => $name,
            'description' => $description,
            'html' => $defaultHtml,
            'css' => $defaultCss,
            'js' => $defaultJs,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'messages' => [
                [
                    'role' => 'assistant',
                    'content' => 'Olá! Descreva o app que você quer criar e clique em "Gerar".',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ],
            'last_generated_at' => null
        ];

        $this->writeProject($projectId, $project);
        $this->saveMessage($projectDbId, 'assistant', 'Olá! Descreva o app que você quer criar e clique em "Gerar".');
        $this->ensurePublicFiles($projectId);

        $this->redirect('/educalabs/projetos/' . $projectId);
    }

    public function show($id)
    {
        $user = $this->authManager->getUser();
        $this->ensureAluno($user);

        $project = $this->getProject($id, $user['id']);
        if (!$project) {
            $this->setFlashMessage('Projeto não encontrado.', 'error');
            $this->redirect('/educalabs');
            return;
        }

        $project['messages'] = $this->getMessages($project['db_id']);

        $data = [
            'title' => 'EducaLabs - ' . $project['name'],
            'user' => $user,
            'project' => $project,
            'preview_url' => $this->getPreviewUrl($project['id']),
            'share_url' => URL . '/educalabs/public/' . $project['share_id'],
            'current_page' => 'educalabs',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/educalabs/edit', $data);
    }

    public function addMessage($id)
    {
        $user = $this->authManager->getUser();
        $this->ensureAluno($user);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
            return;
        }

        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            $this->json(['success' => false, 'error' => 'Mensagem vazia'], 400);
            return;
        }

        $project = $this->getProject($id, $user['id']);
        if (!$project) {
            $this->json(['success' => false, 'error' => 'Projeto não encontrado'], 404);
            return;
        }

        $this->saveMessage($project['db_id'], 'user', $message);
        $this->db->update(
            "UPDATE educalabs_projects SET updated_at = NOW() WHERE id = ?",
            [$project['db_id']]
        );

        $this->json(['success' => true]);
    }

    public function generate($id)
    {
        $user = $this->authManager->getUser();
        $this->ensureAluno($user);

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
            return;
        }

        $project = $this->getProject($id, $user['id']);
        if (!$project) {
            $this->json(['success' => false, 'error' => 'Projeto não encontrado'], 404);
            return;
        }

        $messages = $this->getMessages($project['db_id']);
        $prompt = $this->buildPrompt($project, $messages);
        $result = $this->generateFrontEnd($prompt);

        $this->writeFrontEndFiles($project['id'], $result['html'], $result['css'], $result['js']);
        $this->db->update(
            "UPDATE educalabs_projects
             SET html = ?, css = ?, js = ?, updated_at = NOW()
             WHERE id = ?",
            [$result['html'], $result['css'], $result['js'], $project['db_id']]
        );
        $this->saveMessage($project['db_id'], 'assistant', $result['summary']);

        $this->json([
            'success' => true,
            'preview_url' => $this->getPreviewUrl($project['id']),
            'summary' => $result['summary'],
            'messages' => $this->getMessages($project['db_id'])
        ]);
    }

    public function publicView($share_id)
    {
        $project = $this->getProjectByShareId($share_id);
        if (!$project) {
            http_response_code(404);
            echo 'Projeto não encontrado.';
            return;
        }

        $this->renderHtmlFromProject($project, $share_id);
    }

    public function publicAsset($share_id)
    {
        $project = $this->getProjectByShareId($share_id);
        if (!$project) {
            http_response_code(404);
            return;
        }

        $file = basename($_SERVER['REQUEST_URI'] ?? '');
        $this->renderAsset($project['id'], $file);
    }

    public function preview($id)
    {
        $user = $this->authManager->getUser();
        $this->ensureAluno($user);

        $project = $this->getProject($id, $user['id']);
        if (!$project) {
            http_response_code(404);
            echo 'Projeto não encontrado.';
            return;
        }

        $this->renderHtmlFromProject($project, null);
    }

    public function previewFile($id, $file)
    {
        $user = $this->authManager->getUser();
        $this->ensureAluno($user);

        $project = $this->getProject($id, $user['id']);
        if (!$project) {
            http_response_code(404);
            return;
        }

        $this->renderAsset($project['id'], $file);
    }

    private function ensureAluno($user)
    {
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->redirect('/logout');
        }
    }

    private function listProjects($ownerId)
    {
        $projects = [];
        $rows = $this->db->fetchAll(
            "SELECT id, public_id, share_id, owner_id, name, description, updated_at
             FROM educalabs_projects
             WHERE owner_id = ?
             ORDER BY updated_at DESC",
            [$ownerId]
        );

        foreach ($rows as $row) {
            $projects[] = [
                'db_id' => $row['id'],
                'id' => $row['public_id'],
                'share_id' => $row['share_id'],
                'owner_id' => $row['owner_id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'updated_at' => $row['updated_at']
            ];
        }

        return $projects;
    }

    private function getProject($id, $ownerId)
    {
        $row = $this->db->fetch(
            "SELECT * FROM educalabs_projects WHERE public_id = ? AND owner_id = ?",
            [$id, $ownerId]
        );
        if ($row) {
            $project = [
                'db_id' => $row['id'],
                'id' => $row['public_id'],
                'share_id' => $row['share_id'],
                'owner_id' => $row['owner_id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'html' => $row['html'],
                'css' => $row['css'],
                'js' => $row['js'],
                'updated_at' => $row['updated_at']
            ];

            return $this->ensureProjectCode($project);
        }

        return $this->loadProjectFromStorage($id, $ownerId);
    }

    private function getProjectByShareId($shareId)
    {
        $row = $this->db->fetch(
            "SELECT * FROM educalabs_projects WHERE share_id = ?",
            [$shareId]
        );
        if ($row) {
            return [
                'db_id' => $row['id'],
                'id' => $row['public_id'],
                'share_id' => $row['share_id'],
                'owner_id' => $row['owner_id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'html' => $row['html'],
                'css' => $row['css'],
                'js' => $row['js']
            ];
        }

        return null;
    }

    private function writeProject($id, array $project)
    {
        $dir = $this->storageDir . '/' . basename($id);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/project.json', json_encode($project, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function saveMessage($projectId, $role, $content)
    {
        $this->db->insert(
            "INSERT INTO educalabs_messages (project_id, role, content, created_at)
             VALUES (?, ?, ?, NOW())",
            [$projectId, $role, $content]
        );
    }

    private function getMessages($projectId)
    {
        return $this->db->fetchAll(
            "SELECT role, content, created_at FROM educalabs_messages WHERE project_id = ? ORDER BY id ASC",
            [$projectId]
        );
    }

    private function ensurePublicFiles($id)
    {
        $dir = $this->publicDir . '/' . basename($id);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $index = $dir . '/index.html';
        $css = $dir . '/styles.css';
        $js = $dir . '/script.js';

        if (!file_exists($index)) {
            file_put_contents($index, $this->wrapHtml('<h1>Seu app vai aparecer aqui</h1><p>Descreva o que você quer criar no EducaLabs.</p>'));
        }
        if (!file_exists($css)) {
            file_put_contents($css, "body { font-family: Arial, sans-serif; padding: 24px; color: #111827; }\n");
        }
        if (!file_exists($js)) {
            file_put_contents($js, "// Scripts do seu app\n");
        }
    }

    private function writeFrontEndFiles($id, $html, $css, $js)
    {
        $dir = $this->publicDir . '/' . basename($id);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir . '/index.html', $this->wrapHtml($html));
        file_put_contents($dir . '/styles.css', $css);
        file_put_contents($dir . '/script.js', $js);
    }

    private function renderHtmlFromProject(array $project, $shareId)
    {
        $file = $this->publicDir . '/' . basename($project['id']) . '/index.html';
        if (!file_exists($file)) {
            $this->ensurePublicFiles($project['id']);
        }

        $html = file_exists($file) ? file_get_contents($file) : '';
        if ($html === '') {
            $html = $this->wrapHtml('<h1>Seu app vai aparecer aqui</h1>');
        }

        $basePath = $shareId
            ? URL . '/educalabs/public/' . $shareId
            : URL . '/educalabs/projetos/' . $project['id'] . '/preview';

        $html = str_replace('styles.css', $basePath . '/styles.css', $html);
        $html = str_replace('script.js', $basePath . '/script.js', $html);
        $html = $this->rewritePreviewLinks($html, $basePath);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    private function rewritePreviewLinks($html, $basePath)
    {
        $loginTarget = $basePath . '#login';
        $patterns = [
            'href="/login"' => 'href="' . $loginTarget . '"',
            "href='/login'" => "href='" . $loginTarget . "'",
            'action="/login"' => 'action="' . $loginTarget . '"',
            "action='/login'" => "action='" . $loginTarget . "'",
            'location.href="/login"' => 'location.href="' . $loginTarget . '"',
            "location.href='/login'" => "location.href='" . $loginTarget . "'",
            'window.location.href="/login"' => 'window.location.href="' . $loginTarget . '"',
            "window.location.href='/login'" => "window.location.href='" . $loginTarget . "'",
            'location.assign("/login")' => 'location.assign("' . $loginTarget . '")',
            "location.assign('/login')" => "location.assign('" . $loginTarget . "')",
            'location.replace("/login")' => 'location.replace("' . $loginTarget . '")',
            "location.replace('/login')" => "location.replace('" . $loginTarget . "')"
        ];

        return str_replace(array_keys($patterns), array_values($patterns), $html);
    }

    private function renderAsset($projectId, $file)
    {
        $file = basename($file);
        if (!in_array($file, ['styles.css', 'script.js'], true)) {
            http_response_code(404);
            return;
        }

        $path = $this->publicDir . '/' . basename($projectId) . '/' . $file;
        if (!file_exists($path)) {
            $this->ensurePublicFiles($projectId);
        }

        if (!file_exists($path)) {
            http_response_code(404);
            return;
        }

        $contentType = $file === 'styles.css' ? 'text/css' : 'application/javascript';
        header('Content-Type: ' . $contentType . '; charset=utf-8');
        echo file_get_contents($path);
    }

    private function wrapHtml($html)
    {
        if (stripos($html, '<html') !== false) {
            return $html;
        }

        return "<!doctype html>\n<html lang=\"pt-BR\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<link rel=\"stylesheet\" href=\"styles.css\">\n<script src=\"https://cdn.tailwindcss.com\"></script>\n<title>EducaLabs</title>\n</head>\n<body>\n{$html}\n<script src=\"script.js\"></script>\n</body>\n</html>";
    }

    private function buildPrompt(array $project, array $messages)
    {
        $history = [];
        foreach ($messages as $msg) {
            $role = strtoupper($msg['role'] ?? 'USER');
            $content = trim($msg['content'] ?? '');
            if ($content === '') {
                continue;
            }
            $history[] = "{$role}: {$content}";
        }

        $historyText = implode("\n", $history);

        $currentHtml = trim($project['html'] ?? '');
        $currentCss = trim($project['css'] ?? '');
        $currentJs = trim($project['js'] ?? '');

        return "Você é um assistente que cria apps front-end (HTML, CSS e JavaScript puros).\n"
            . "Retorne SOMENTE JSON válido com as chaves: html, css, js, summary.\n"
            . "html deve conter apenas o conteúdo do <body> (sem <html>, <head>), css apenas estilos e js apenas scripts.\n"
            . "Se precisar usar Tailwind, use classes Tailwind e NÃO inclua <script> do Tailwind, pois ele será adicionado automaticamente.\n"
            . "Atualize o app existente sem recomeçar do zero. Preserve o que já existe e aplique apenas as alterações pedidas.\n"
            . "Código atual:\n"
            . "HTML:\n{$currentHtml}\n\nCSS:\n{$currentCss}\n\nJS:\n{$currentJs}\n"
            . "summary deve explicar em 1 frase o que foi gerado.\n"
            . "Histórico da conversa:\n{$historyText}";
    }

    private function generateFrontEnd($prompt)
    {
        $summary = 'App atualizado com sucesso.';
        $html = '<h1>Seu app está pronto</h1><p>Atualize os detalhes conforme desejar.</p>';
        $css = "body { font-family: Arial, sans-serif; padding: 24px; color: #111827; }\n";
        $js = "// Scripts do app\n";

        try {
            if (!class_exists('App\Services\OpenAIService')) {
                require_once __DIR__ . '/../../Services/OpenAIService.php';
            }
            $service = new \App\Services\OpenAIService();
            $response = $service->generateText($prompt, [
                'max_tokens' => 3500,
                'temperature' => 0.4
            ]);

            $parsed = $this->parseGeneratedContent($response);
            if ($parsed) {
                $html = trim($parsed['html'] ?? $html);
                $css = trim($parsed['css'] ?? $css);
                $js = trim($parsed['js'] ?? $js);
                $summary = trim($parsed['summary'] ?? $summary);
            }
        } catch (Exception $e) {
            $summary = 'Usei o modelo padrão porque a geração falhou.';
        }

        return [
            'html' => $html,
            'css' => $css,
            'js' => $js,
            'summary' => $summary
        ];
    }

    private function parseGeneratedContent($content)
    {
        $content = trim((string) $content);
        if ($content === '') {
            return null;
        }

        // Tenta JSON direto
        $decoded = json_decode($content, true);
        if (is_array($decoded) && isset($decoded['html'])) {
            return $decoded;
        }

        // Tenta extrair JSON de bloco markdown
        if (strpos($content, '```json') !== false) {
            $jsonStart = strpos($content, '```json') + 7;
            $jsonEnd = strpos($content, '```', $jsonStart);
            if ($jsonEnd !== false) {
                $jsonContent = trim(substr($content, $jsonStart, $jsonEnd - $jsonStart));
                $decoded = json_decode($jsonContent, true);
                if (is_array($decoded) && isset($decoded['html'])) {
                    return $decoded;
                }
            }
        }

        // Tenta extrair JSON entre chaves
        $first = strpos($content, '{');
        $last = strrpos($content, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $jsonContent = substr($content, $first, $last - $first + 1);
            $decoded = json_decode($jsonContent, true);
            if (is_array($decoded) && isset($decoded['html'])) {
                return $decoded;
            }
        }

        // Fallback: extrair blocos de código
        $html = $this->extractCodeBlock($content, 'html');
        $css = $this->extractCodeBlock($content, 'css');
        $js = $this->extractCodeBlock($content, 'javascript');
        if ($js === '') {
            $js = $this->extractCodeBlock($content, 'js');
        }

        if ($html !== '' || $css !== '' || $js !== '') {
            return [
                'html' => $html,
                'css' => $css,
                'js' => $js,
                'summary' => 'App atualizado com sucesso.'
            ];
        }

        return null;
    }

    private function extractCodeBlock($content, $lang)
    {
        $pattern = '/```' . preg_quote($lang, '/') . '\s*(.*?)```/s';
        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    private function getPreviewUrl($projectId)
    {
        return URL . '/educalabs/projetos/' . $projectId . '/preview';
    }

    private function loadProjectFromStorage($id, $ownerId)
    {
        $path = $this->storageDir . '/' . basename($id) . '/project.json';
        if (!file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data) || ($data['owner_id'] ?? null) !== $ownerId) {
            return null;
        }

        $files = $this->readFrontEndFiles($id);
        $projectDbId = $this->db->insert(
            "INSERT INTO educalabs_projects
             (public_id, share_id, owner_id, name, description, html, css, js, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $data['id'],
                $data['share_id'] ?? $this->generateShareId(),
                $ownerId,
                $data['name'] ?? 'Projeto',
                $data['description'] ?? null,
                $files['html'],
                $files['css'],
                $files['js']
            ]
        );

        if (!empty($data['messages']) && is_array($data['messages'])) {
            foreach ($data['messages'] as $msg) {
                $role = $msg['role'] ?? 'assistant';
                $content = $msg['content'] ?? '';
                if ($content !== '') {
                    $this->saveMessage($projectDbId, $role, $content);
                }
            }
        }

        return [
            'db_id' => $projectDbId,
            'id' => $data['id'],
            'share_id' => $data['share_id'] ?? '',
            'owner_id' => $ownerId,
            'name' => $data['name'] ?? 'Projeto',
            'description' => $data['description'] ?? '',
            'html' => $files['html'],
            'css' => $files['css'],
            'js' => $files['js'],
            'updated_at' => $data['updated_at'] ?? date('Y-m-d H:i:s')
        ];
    }

    private function ensureProjectCode(array $project)
    {
        $needsUpdate = empty($project['html']) && empty($project['css']) && empty($project['js']);
        if (!$needsUpdate) {
            return $project;
        }

        $files = $this->readFrontEndFiles($project['id']);
        $project['html'] = $files['html'];
        $project['css'] = $files['css'];
        $project['js'] = $files['js'];

        if (!empty($project['db_id'])) {
            $this->db->update(
                "UPDATE educalabs_projects SET html = ?, css = ?, js = ? WHERE id = ?",
                [$project['html'], $project['css'], $project['js'], $project['db_id']]
            );
        }

        return $project;
    }

    private function readFrontEndFiles($id)
    {
        $dir = $this->publicDir . '/' . basename($id);
        $htmlFile = $dir . '/index.html';
        $cssFile = $dir . '/styles.css';
        $jsFile = $dir . '/script.js';

        $html = file_exists($htmlFile) ? file_get_contents($htmlFile) : '';
        $css = file_exists($cssFile) ? file_get_contents($cssFile) : '';
        $js = file_exists($jsFile) ? file_get_contents($jsFile) : '';

        $html = $this->extractBodyContent($html);

        return [
            'html' => $html,
            'css' => $css,
            'js' => $js
        ];
    }

    private function extractBodyContent($html)
    {
        if ($html === '') {
            return '';
        }

        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            return trim($matches[1]);
        }

        return $html;
    }

    private function generateProjectId()
    {
        return bin2hex(random_bytes(8));
    }

    private function generateShareId()
    {
        return bin2hex(random_bytes(12));
    }

    private function generateAccessToken()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Retorna a URL base do login externo (sem token) para envio via POST.
     * V-03: token não deve aparecer na URL.
     */
    private function getExternalLoginBaseUrl(): string
    {
        $baseUrl = trim((string) LayoutHelper::get('educalabs_external_url', $this->externalBaseUrl));
        if ($baseUrl === '') {
            $baseUrl = $this->externalBaseUrl;
        }
        // Remover placeholders; o token e inst serão enviados no corpo do POST
        $baseUrl = str_replace('{token}', '', $baseUrl);
        $baseUrl = str_replace('{inst}', '', $baseUrl);
        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        if ($host === '') {
            return $baseUrl;
        }
        $url = $scheme . '://' . $host . $port . $path;
        if (!empty($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }
        return $url;
    }

    /**
     * Monta a URL do EducaLabs com apenas token e slug.
     * O app já sabe que deve validar em master.educatudo.com/external-apps/validate-token.
     */
    private function buildExternalAccessUrl(string $token): string
    {
        $baseUrl = trim((string) LayoutHelper::get('educalabs_external_url', $this->externalBaseUrl));
        if ($baseUrl === '') {
            $baseUrl = $this->externalBaseUrl;
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

