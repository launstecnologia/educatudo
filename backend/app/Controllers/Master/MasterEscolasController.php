<?php
/**
 * EducaTudo - CRUD de escolas (tenants) no painel admin master.
 * Cria escolas, configura banco, URL, layout (cor, imagem), módulos, links, apps externos, e-mail, prompts e chaves de API.
 */

if (!class_exists('MasterEscolasController')) {

class MasterEscolasController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';
    private const MODULE_VALID_VALUES = ['1', '0', '2'];

    /** Chaves de apps externos em config_layout */
    private static $APPS_EXTERNOS_KEYS = [
        'educalabs_external_url',
        'games_external_url',
        'notes_external_url',
        'external_institution_id',
        'external_apps_validate_url',
    ];

    /** Chaves de e-mail em config_layout */
    private static $EMAIL_KEYS = [
        'email_smtp_host', 'email_smtp_port', 'email_smtp_secure', 'email_smtp_username', 'email_smtp_password',
        'email_from_email', 'email_from_name', 'email_reply_to',
    ];

    /** Chaves de prompts de IA em config_layout */
    private static $PROMPT_KEYS = [
        'prompt_tema', 'prompt_correcao', 'prompt_tudinha_chat', 'prompt_ocr', 'prompt_prova',
        'prompt_prova_imagens', 'prompt_exercicios_jornada', 'prompt_exercicios_personalizados',
    ];

    /** Chaves de API em config_layout (em branco = não alterar) */
    private static $API_KEYS = ['openai_api_key', 'gamma_api_key', 'nanobanana_api_key'];

    private function getModulosCatalogo(): array
    {
        if (!class_exists('ModuloCatalogo', false)) {
            require_once __DIR__ . '/../../Core/ModuloCatalogo.php';
        }
        return ModuloCatalogo::todos();
    }

    public function __construct()
    {
        parent::__construct();
    }

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    private function getDominioEscolaService(): DominioEscolaService
    {
        require_once __DIR__ . '/../../Services/DominioEscolaService.php';
        return new DominioEscolaService();
    }

    /** @return array<string, mixed> */
    private function getDominioViewConfig(): array
    {
        return $this->getDominioEscolaService()->configuracaoParaView();
    }

    private function getLayoutConfig(int $escolaId): array
    {
        $db = Database::getInstance();
        $rows = $db->query(
            "SELECT config_key, config_value FROM config_escolas_layout WHERE escola_id = ?",
            [$escolaId]
        )->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['config_key']] = $r['config_value'];
        }
        return $out;
    }

    private function setLayoutConfig(int $escolaId, array $config): void
    {
        $db = Database::getInstance();
        foreach ($config as $key => $value) {
            if ($key === '' || strlen($key) > 128) continue;
            $db->query(
                "INSERT INTO config_escolas_layout (escola_id, config_key, config_value) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)",
                [$escolaId, $key, (string) $value]
            );
        }
    }

    private function getEscolaSlug(int $escolaId): string
    {
        $db = Database::getInstance();
        $row = $db->query("SELECT slug FROM escolas WHERE id = ? LIMIT 1", [$escolaId])->fetch(PDO::FETCH_ASSOC);
        return strtolower(trim((string) ($row['slug'] ?? '')));
    }

    private function generateEducaProfToken(string $schoolSlug, string $schoolName): array
    {
        $baseUrl = rtrim((string) (function_exists('env') ? env('EDUCAPROF_API_BASE_URL', '') : ''), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://zjgnpluinswcttozhbsa.supabase.co/functions/v1/api';
        }

        $payload = json_encode([
            'school_slug' => $schoolSlug,
            'name' => 'Master - ' . $schoolName,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($baseUrl . '/generate_key');
        if ($ch === false) {
            throw new Exception('Falha ao iniciar cliente para gerar token EducaProf.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload === false ? '{}' : $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            throw new Exception('Erro de conexão ao gerar token EducaProf: ' . $error);
        }

        $decoded = is_string($response) ? json_decode($response, true) : null;
        if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['api_key'])) {
            $message = is_array($decoded) ? (string) ($decoded['error'] ?? $decoded['message'] ?? 'Falha ao gerar token') : 'Falha ao gerar token';
            throw new Exception($message . " (HTTP {$httpCode})");
        }

        return [
            'api_key' => (string) $decoded['api_key'],
            'base_url' => $baseUrl,
            'school_slug' => $schoolSlug,
        ];
    }

    private function ensureEducaProfTokenForSchool(int $escolaId, string $schoolSlug, string $schoolName): void
    {
        if ($escolaId <= 0 || $schoolSlug === '') {
            return;
        }

        $currentConfig = $this->getLayoutConfig($escolaId);
        $currentToken = trim((string) ($currentConfig['educaprof_api_key'] ?? ''));
        $currentSlug = trim((string) ($currentConfig['educaprof_school_slug'] ?? ''));

        if ($currentToken !== '' && $currentSlug === $schoolSlug) {
            return;
        }

        $generated = $this->generateEducaProfToken($schoolSlug, $schoolName);
        $this->setLayoutConfig($escolaId, [
            'educaprof_api_key' => $generated['api_key'],
            'educaprof_api_base_url' => $generated['base_url'],
            'educaprof_school_slug' => $generated['school_slug'],
        ]);
    }

    /**
     * Sincroniza config_escolas_layout para o config_layout do banco da escola (tenant).
     * Se a escola não tem banco próprio (config_escolas_banco vazio), escreve no banco atual (mesmo DB do professor).
     */
    private function syncLayoutToTenant(int $escolaId): void
    {
        $db = Database::getInstance();
        $layout = $this->getLayoutConfig($escolaId);
        if (empty($layout)) return;

        $banco = $db->query(
            "SELECT host, porta, nome_banco, usuario, senha_criptografada FROM config_escolas_banco WHERE escola_id = ?",
            [$escolaId]
        )->fetch(PDO::FETCH_ASSOC);

        if ($banco) {
            $port = (int) ($banco['porta'] ?? 3306);
            $dsn = "mysql:host={$banco['host']};port={$port};dbname={$banco['nome_banco']};charset=utf8mb4";
            try {
                $pdo = new PDO($dsn, $banco['usuario'], MasterSecretVault::decryptDbPassword($banco['senha_criptografada'] ?? ''), [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                return;
            }
        } else {
            // Escola sem banco próprio: professor usa o mesmo DB (master/global). Escrever em config_layout do banco atual.
            $pdo = $db->getPdo();
            if (!$pdo) return;
        }

        foreach ($layout as $config_key => $config_value) {
            try {
                $pdo->prepare(
                    "INSERT INTO config_layout (config_key, config_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)"
                )->execute([$config_key, $config_value]);
            } catch (PDOException $e) {
                continue;
            }
        }
        require_once __DIR__ . '/../../Core/RedisCache.php';
        RedisCache::delete('config_layout_' . $escolaId);
        RedisCache::delete('config_layout');
    }

    /**
     * Aplica educa_core.sql (se o banco estiver vazio) e as migrations tenant no banco da escola.
     */
    private function provisionarSchemaTenant(
        PDO $masterPdo,
        string $host,
        int $porta,
        string $nomeBanco,
        string $usuario,
        string $senha,
        int $escolaId
    ): void {
        require_once __DIR__ . '/../../Core/MysqlProvisioningService.php';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $nomeBanco) || strlen($nomeBanco) > 64) {
            throw new Exception('Nome do banco inválido.');
        }
        $dsn = 'mysql:host=' . str_replace([':', ';', ' '], '', $host) . ';port=' . $porta
            . ';dbname=' . $nomeBanco . ';charset=utf8mb4';
        $tenantPdo = new PDO($dsn, $usuario, $senha, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $tenantPdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        MysqlProvisioningService::provisionarBancoEscola($masterPdo, $tenantPdo, $escolaId);
    }

    private function setMaintenanceMode(int $escolaId, bool $enabled): void
    {
        require_once __DIR__ . '/../../Core/MasterTenantConnection.php';
        $tenant = MasterTenantConnection::getPdoAndEscola($escolaId);
        if (!$tenant || empty($tenant['pdo']) || !$tenant['pdo'] instanceof PDO) {
            throw new Exception('Não foi possível conectar ao banco da escola para alterar a manutenção.');
        }

        $value = $enabled ? '1' : '0';
        $pdo = $tenant['pdo'];
        $pdo->prepare(
            "INSERT INTO config_layout (config_key, config_value, config_type, description, created_at, updated_at)
             VALUES ('maintenance_mode', ?, 'text', 'Modo manutenção da escola', NOW(), NOW())
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()"
        )->execute([$value]);

        $this->setLayoutConfig($escolaId, ['maintenance_mode' => $value]);

        require_once __DIR__ . '/../../Core/RedisCache.php';
        RedisCache::delete('config_layout_' . $escolaId);
        RedisCache::delete('config_layout');
    }

    private function getTenantMaintenanceMode(int $escolaId): ?string
    {
        require_once __DIR__ . '/../../Core/MasterTenantConnection.php';
        $tenant = MasterTenantConnection::getPdoAndEscola($escolaId);
        if (!$tenant || empty($tenant['pdo']) || !$tenant['pdo'] instanceof PDO) {
            return null;
        }

        $stmt = $tenant['pdo']->prepare(
            "SELECT config_value FROM config_layout WHERE config_key = 'maintenance_mode' LIMIT 1"
        );
        $stmt->execute();
        $value = $stmt->fetchColumn();

        return $value === false ? '0' : (string) $value;
    }

    private function syncMaintenanceStatusFromTenants(array $escolas): array
    {
        foreach ($escolas as &$escola) {
            if (empty($escola['tem_banco'])) {
                continue;
            }

            $tenantValue = $this->getTenantMaintenanceMode((int) $escola['id']);
            if ($tenantValue === null) {
                continue;
            }

            $normalized = $tenantValue === '1' ? '1' : '0';
            if ((string) ($escola['maintenance_mode'] ?? '0') !== $normalized) {
                $this->setLayoutConfig((int) $escola['id'], ['maintenance_mode' => $normalized]);
            }
            $escola['maintenance_mode'] = $normalized;
        }
        unset($escola);

        return $escolas;
    }

    public function toggleManutencao(): void
    {
        $this->requireMaster();
        $id = (int) ($_POST['id'] ?? 0);
        $enabled = (string) ($_POST['enabled'] ?? '') === '1';

        if ($id <= 0) {
            $this->setFlashMessage('Escola inválida.', 'error');
            header('Location: ' . URL . '/master/escolas');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas');
            exit;
        }

        try {
            $this->setMaintenanceMode($id, $enabled);
            $this->setFlashMessage(
                $enabled ? 'Escola colocada em manutenção.' : 'Escola retirada da manutenção.',
                'success'
            );
        } catch (Exception $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        }

        header('Location: ' . URL . '/master/escolas');
        exit;
    }

    public function index()
    {
        $this->requireMaster();
        $db = Database::getInstance();

        $perPage = 10;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = (int) ($db->fetch("SELECT COUNT(*) AS total FROM escolas")['total'] ?? 0);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $escolas = $db->query(
            "SELECT e.id, e.nome, e.slug, e.dominio, e.ativo, e.created_at,
                    e.dns_status, e.ssl_status, e.ssl_expira_em,
                    COALESCE(ml.config_value, '0') AS maintenance_mode,
                    (SELECT COUNT(*) FROM config_escolas_banco c WHERE c.escola_id = e.id) AS tem_banco
             FROM escolas e
             LEFT JOIN config_escolas_layout ml
               ON ml.escola_id = e.id AND ml.config_key = 'maintenance_mode'
             ORDER BY e.nome
             LIMIT {$perPage} OFFSET {$offset}"
        )->fetchAll(PDO::FETCH_ASSOC);
        $escolas = $this->syncMaintenanceStatusFromTenants($escolas);

        $this->viewWithLayout('master', 'master/escolas/index', [
            'title' => 'Escolas - Painel Master',
            'page_title' => 'Escolas',
            'current_page' => 'escolas',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'escolas' => $escolas,
            'csrf_token' => $this->generateCsrfToken(),
            'dominio_config' => $this->getDominioViewConfig(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function create()
    {
        $this->requireMaster();
        require_once __DIR__ . '/../../Core/MysqlProvisioningService.php';
        $this->viewWithLayout('master', 'master/escolas/form', [
            'title' => 'Nova Escola - Painel Master',
            'page_title' => 'Nova escola',
            'current_page' => 'escolas',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'escola' => null,
            'banco' => null,
            'layout' => [],
            'modulos_catalogo' => $this->getModulosCatalogo(),
            'criar_banco_disponivel' => MysqlProvisioningService::isAvailable(),
            'dominio_config' => $this->getDominioViewConfig(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function store()
    {
        $this->requireMaster();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/criar');
            exit;
        }
        $nome = trim($_POST['nome'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $dominio = trim($_POST['dominio'] ?? '');
        $ativo = isset($_POST['ativo']) && $_POST['ativo'] === '1' ? 1 : 0;

        if ($nome === '' || $slug === '') {
            $this->setFlashMessage('Preencha nome e slug da escola.', 'error');
            header('Location: ' . URL . '/master/escolas/criar');
            exit;
        }

        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
        if ($slug === '') {
            $this->setFlashMessage('Slug inválido (use apenas letras, números e hífen).', 'error');
            header('Location: ' . URL . '/master/escolas/criar');
            exit;
        }

        $dominioSvc = $this->getDominioEscolaService();
        $domRes = $dominioSvc->normalizarDominioParaSalvar($slug, $dominio);
        if (!empty($domRes['erro'])) {
            $this->setFlashMessage($domRes['erro'], 'error');
            header('Location: ' . URL . '/master/escolas/criar');
            exit;
        }
        $dominio = (string) $domRes['dominio'];
        $dnsStatus = (string) ($domRes['dns_status'] ?? DominioEscolaService::DNS_NAO_CONFIGURADO);

        $db = Database::getInstance();
        if (!$dominioSvc->dominioDisponivel($db, $dominio)) {
            $this->setFlashMessage('Já existe uma escola com este domínio.', 'error');
            header('Location: ' . URL . '/master/escolas/criar');
            exit;
        }
        $stmt = $db->query("SELECT id FROM escolas WHERE slug = ?", [$slug]);
        if ($stmt->fetch()) {
            $this->setFlashMessage('Já existe uma escola com este slug.', 'error');
            header('Location: ' . URL . '/master/escolas/criar');
            exit;
        }

        $escolaId = (int) $db->insert(
            "INSERT INTO escolas (nome, slug, dominio, ativo, dns_status, ssl_status) VALUES (?, ?, ?, ?, ?, ?)",
            [$nome, $slug, $dominio, $ativo, $dnsStatus, DominioEscolaService::SSL_NAO_VERIFICADO]
        );

        $host = trim($_POST['db_host'] ?? 'localhost');
        $porta = (int) ($_POST['db_porta'] ?? 3306);
        $nomeBanco = trim($_POST['db_nome_banco'] ?? '');
        $usuario = trim($_POST['db_usuario'] ?? '');
        $senha = trim($_POST['db_senha'] ?? '');
        $criarBancoAuto = !empty($_POST['criar_banco_automaticamente']);

        if ($nomeBanco !== '' && $usuario !== '') {
            $erroProvisionamento = '';
            if ($criarBancoAuto && $senha !== '') {
                require_once __DIR__ . '/../../Core/MysqlProvisioningService.php';
                if (MysqlProvisioningService::isAvailable()) {
                    try {
                        $admin = MysqlProvisioningService::getAdminConnectionParams();
                        $adminUser = function_exists('env') ? env('DB_ADMIN_USER', '') : '';
                        $adminPass = function_exists('env') ? env('DB_ADMIN_PASS', '') : '';
                        $adminHost = (string) ($admin['host'] ?? $host);
                        $adminPorta = (int) ($admin['port'] ?? $porta);
                        MysqlProvisioningService::createDatabaseAndUser(
                            $adminHost,
                            $adminPorta,
                            $adminUser,
                            $adminPass,
                            $nomeBanco,
                            $usuario,
                            $senha
                        );
                        $host = $adminHost;
                        $porta = $adminPorta;
                    } catch (Exception $e) {
                        $adminHostMsg = isset($adminHost) ? $adminHost : $host;
                        $adminUserMsg = function_exists('env') ? (string) env('DB_ADMIN_USER', 'root') : 'root';
                        $erroProvisionamento = MysqlProvisioningService::formatarErroAdminMysql($e, $adminUserMsg, $adminHostMsg);
                    }
                }
            }
            if ($senha !== '') {
                try {
                    $this->provisionarSchemaTenant($db->getPdo(), $host, $porta, $nomeBanco, $usuario, $senha, $escolaId);
                } catch (Exception $e) {
                    $msgSchema = $e->getMessage();
                    $this->setFlashMessage(
                        $erroProvisionamento !== ''
                            ? $erroProvisionamento
                            : ('Erro ao aplicar schema/migrations no banco da escola: ' . $msgSchema),
                        'error'
                    );
                    header('Location: ' . URL . '/master/escolas/editar?id=' . $escolaId);
                    exit;
                }
            }
            $db->query(
                "INSERT INTO config_escolas_banco (escola_id, host, porta, nome_banco, usuario, senha_criptografada) VALUES (?, ?, ?, ?, ?, ?)",
                [$escolaId, $host, $porta, $nomeBanco, $usuario, MasterSecretVault::encryptDbPassword($senha)]
            );
            if ($erroProvisionamento !== '') {
                $this->setFlashMessage($erroProvisionamento, 'error');
                header('Location: ' . URL . '/master/escolas/editar?id=' . $escolaId);
                exit;
            }
        }

        $this->saveLayoutFromPost($escolaId);
        try {
            $this->ensureEducaProfTokenForSchool($escolaId, $slug, $nome);
        } catch (Exception $e) {
            error_log('MasterEscolasController store: falha ao gerar token EducaProf para escola ' . $escolaId . ': ' . $e->getMessage());
        }

        try {
            require_once __DIR__ . '/../../Services/MasterCreditosCatalogoSyncService.php';
            $resPadrao = \App\Services\MasterCreditosCatalogoSyncService::aplicarTabelaPadraoNaEscolaNova($escolaId);
            if (!empty($resPadrao['layout_patch'])) {
                $this->setLayoutConfig($escolaId, $resPadrao['layout_patch']);
            }
        } catch (Throwable $e) {
            error_log('MasterEscolasController store: falha ao aplicar tabela de preço padrão: ' . $e->getMessage());
        }

        // Sempre envia layout e módulos para o banco da escola quando a conexão está configurada
        if ($nomeBanco !== '' && $usuario !== '' && !isset($_POST['nao_sincronizar'])) {
            $this->syncLayoutToTenant($escolaId);
        }

        $this->setFlashMessage('Escola criada com sucesso.', 'success');
        header('Location: ' . URL . '/master/escolas');
        exit;
    }

    public function edit()
    {
        $this->requireMaster();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . URL . '/master/escolas');
            exit;
        }
        $db = Database::getInstance();
        $escola = $db->query("SELECT * FROM escolas WHERE id = ?", [$id])->fetch(PDO::FETCH_ASSOC);
        if (!$escola) {
            $this->setFlashMessage('Escola não encontrada.', 'error');
            header('Location: ' . URL . '/master/escolas');
            exit;
        }
        $banco = $db->query("SELECT * FROM config_escolas_banco WHERE escola_id = ?", [$id])->fetch(PDO::FETCH_ASSOC);
        $layout = $this->getLayoutConfig($id);

        require_once __DIR__ . '/../../Core/MysqlProvisioningService.php';
        $this->viewWithLayout('master', 'master/escolas/form', [
            'title' => 'Editar Escola - Painel Master',
            'page_title' => 'Editar escola',
            'current_page' => 'escolas',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'escola' => $escola,
            'banco' => $banco,
            'layout' => $layout,
            'modulos_catalogo' => $this->getModulosCatalogo(),
            'criar_banco_disponivel' => MysqlProvisioningService::isAvailable(),
            'dominio_config' => $this->getDominioViewConfig(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function update()
    {
        $this->requireMaster();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . URL . '/master/escolas');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/editar?id=' . $id);
            exit;
        }
        $db = Database::getInstance();
        $escola = $db->query("SELECT id FROM escolas WHERE id = ?", [$id])->fetch(PDO::FETCH_ASSOC);
        if (!$escola) {
            $this->setFlashMessage('Escola não encontrada.', 'error');
            header('Location: ' . URL . '/master/escolas');
            exit;
        }

        $nome = trim($_POST['nome'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $dominio = trim($_POST['dominio'] ?? '');
        $ativo = isset($_POST['ativo']) && $_POST['ativo'] === '1' ? 1 : 0;
        if ($nome === '' || $slug === '') {
            $this->setFlashMessage('Preencha nome e slug.', 'error');
            header('Location: ' . URL . '/master/escolas/editar?id=' . $id);
            exit;
        }
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
        if ($slug === '') {
            $this->setFlashMessage('Slug inválido.', 'error');
            header('Location: ' . URL . '/master/escolas/editar?id=' . $id);
            exit;
        }

        $dominioSvc = $this->getDominioEscolaService();
        $domRes = $dominioSvc->normalizarDominioParaSalvar($slug, $dominio);
        if (!empty($domRes['erro'])) {
            $this->setFlashMessage($domRes['erro'], 'error');
            header('Location: ' . URL . '/master/escolas/editar?id=' . $id);
            exit;
        }
        $dominio = (string) $domRes['dominio'];
        $dnsStatus = (string) ($domRes['dns_status'] ?? DominioEscolaService::DNS_NAO_CONFIGURADO);

        if (!$dominioSvc->dominioDisponivel($db, $dominio, $id)) {
            $this->setFlashMessage('Já existe outra escola com este domínio.', 'error');
            header('Location: ' . URL . '/master/escolas/editar?id=' . $id);
            exit;
        }

        $db->query(
            "UPDATE escolas SET nome = ?, slug = ?, dominio = ?, ativo = ?, dns_status = ? WHERE id = ?",
            [$nome, $slug, $dominio, $ativo, $dnsStatus, $id]
        );

        $host = trim($_POST['db_host'] ?? 'localhost');
        $porta = (int) ($_POST['db_porta'] ?? 3306);
        $nomeBanco = trim($_POST['db_nome_banco'] ?? '');
        $usuario = trim($_POST['db_usuario'] ?? '');
        $senha = trim($_POST['db_senha'] ?? '');
        $criarBancoAuto = !empty($_POST['criar_banco_automaticamente']);
        $banco = $db->query("SELECT id, senha_criptografada FROM config_escolas_banco WHERE escola_id = ?", [$id])->fetch(PDO::FETCH_ASSOC);
        $erroAdmin = '';
        if ($nomeBanco !== '' && $usuario !== '') {
            if (!$banco && $criarBancoAuto && $senha !== '') {
                require_once __DIR__ . '/../../Core/MysqlProvisioningService.php';
                if (MysqlProvisioningService::isAvailable()) {
                    try {
                        $admin = MysqlProvisioningService::getAdminConnectionParams();
                        $adminUser = function_exists('env') ? env('DB_ADMIN_USER', '') : '';
                        $adminPass = function_exists('env') ? env('DB_ADMIN_PASS', '') : '';
                        $adminHost = (string) ($admin['host'] ?? $host);
                        $adminPorta = (int) ($admin['port'] ?? $porta);
                        MysqlProvisioningService::createDatabaseAndUser(
                            $adminHost,
                            $adminPorta,
                            $adminUser,
                            $adminPass,
                            $nomeBanco,
                            $usuario,
                            $senha
                        );
                        $host = $adminHost;
                        $porta = $adminPorta;
                    } catch (Exception $e) {
                        $adminUserMsg = function_exists('env') ? (string) env('DB_ADMIN_USER', 'root') : 'root';
                        $erroAdmin = MysqlProvisioningService::formatarErroAdminMysql(
                            $e,
                            $adminUserMsg,
                            (string) ($admin['host'] ?? $host)
                        );
                    }
                }
            }
            $senhaProvisionar = $senha;
            if ($senhaProvisionar === '' && $banco) {
                $senhaProvisionar = MasterSecretVault::decryptDbPassword($banco['senha_criptografada'] ?? '');
            }
            $deveProvisionar = !$banco || $criarBancoAuto;
            if ($deveProvisionar && $senhaProvisionar !== '') {
                try {
                    $this->provisionarSchemaTenant($db->getPdo(), $host, $porta, $nomeBanco, $usuario, $senhaProvisionar, $id);
                } catch (Exception $e) {
                    $this->setFlashMessage(
                        $erroAdmin !== '' ? $erroAdmin : ('Erro ao aplicar schema/migrations no banco da escola: ' . $e->getMessage()),
                        'error'
                    );
                    header('Location: ' . URL . '/master/escolas/editar?id=' . $id);
                    exit;
                }
            }
            if ($banco) {
                if ($senha !== '') {
                    $db->query(
                        "UPDATE config_escolas_banco SET host = ?, porta = ?, nome_banco = ?, usuario = ?, senha_criptografada = ? WHERE escola_id = ?",
                        [$host, $porta, $nomeBanco, $usuario, MasterSecretVault::encryptDbPassword($senha), $id]
                    );
                } else {
                    $db->query(
                        "UPDATE config_escolas_banco SET host = ?, porta = ?, nome_banco = ?, usuario = ? WHERE escola_id = ?",
                        [$host, $porta, $nomeBanco, $usuario, $id]
                    );
                }
            } else {
                $db->query(
                    "INSERT INTO config_escolas_banco (escola_id, host, porta, nome_banco, usuario, senha_criptografada) VALUES (?, ?, ?, ?, ?, ?)",
                    [$id, $host, $porta, $nomeBanco, $usuario, MasterSecretVault::encryptDbPassword($senha)]
                );
            }
            if ($erroAdmin !== '') {
                $this->setFlashMessage($erroAdmin, 'error');
                header('Location: ' . URL . '/master/escolas/editar?id=' . $id);
                exit;
            }
        }

        $this->saveLayoutFromPost($id);
        try {
            $this->ensureEducaProfTokenForSchool($id, $slug, $nome);
        } catch (Exception $e) {
            error_log('MasterEscolasController update: falha ao gerar/vincular token EducaProf para escola ' . $id . ': ' . $e->getMessage());
        }
        // Sempre envia layout e módulos para o banco da escola quando a conexão está configurada
        if ($nomeBanco !== '' && $usuario !== '' && !isset($_POST['nao_sincronizar'])) {
            $this->syncLayoutToTenant($id);
        }

        $this->setFlashMessage('Escola atualizada com sucesso.', 'success');
        header('Location: ' . URL . '/master/escolas');
        exit;
    }

    /**
     * Faz upload de imagem de layout (logo ou capa) para storage (local ou S3).
     * Retorna a URL a ser salva no config ou null em caso de erro.
     */
    private function uploadLayoutImage(int $escolaId, string $fieldName): ?string
    {
        $map = [
            'layout_logo_upload' => ['key_prefix' => 'logo', 'config_key' => 'logo_url'],
            'layout_logo_1x1_upload' => ['key_prefix' => 'logo_1x1', 'config_key' => 'logo_1x1_url'],
            'layout_login_cover_upload' => ['key_prefix' => 'login_cover', 'config_key' => 'login_cover_url'],
            'layout_ia_avatar_upload' => ['key_prefix' => 'ia_avatar', 'config_key' => 'ia_avatar_url'],
        ];
        if (!isset($map[$fieldName])) {
            return null;
        }
        $file = $_FILES[$fieldName] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            return null;
        }
        $maxSize = 5 * 1024 * 1024; // 5MB
        if (($file['size'] ?? 0) > $maxSize) {
            return null;
        }
        // Valida o MIME real do arquivo (não confia na extensão nem no
        // Content-Type enviado pelo navegador, ambos falsificáveis).
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!$mimeReal || !in_array($mimeReal, $allowedMimes, true)) {
            return null;
        }
        $key = 'escola_' . $escolaId . '_' . $map[$fieldName]['key_prefix'] . '_' . time() . '.' . $ext;
        $contentType = $file['type'] ?? null;
        if (!class_exists('MediaStorageService')) {
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
        }
        $config = $this->config;
        $slug = $this->getEscolaSlug($escolaId);
        if ($slug !== '') {
            $config['school'] = array_merge($config['school'] ?? [], ['code' => $slug]);
            $config['tenant'] = array_merge($config['tenant'] ?? [], ['slug' => $slug]);
            $config['media'] = array_merge($config['media'] ?? [], ['tenant_prefix' => true]);
        }
        // Preferir domínio da escola na URL pública (login anônimo não usa master).
        $db = Database::getInstance();
        $escolaRow = $db->query('SELECT dominio FROM escolas WHERE id = ? LIMIT 1', [$escolaId])->fetch(PDO::FETCH_ASSOC);
        $dominio = trim((string) ($escolaRow['dominio'] ?? ''));
        if ($dominio !== '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                || ((string) ($_SERVER['REQUEST_SCHEME'] ?? '') === 'https');
            $protocol = $https ? 'https' : 'http';
            $folder = defined('FOLDER') ? (string) FOLDER : '';
            $config['app'] = array_merge($config['app'] ?? [], [
                'url' => rtrim($protocol . '://' . $dominio . $folder, '/'),
            ]);
        }
        $media = new MediaStorageService($config);
        if (!$media->put('layout', $key, $file['tmp_name'], $contentType)) {
            $this->setFlashMessage(
                'Falha ao enviar imagem para o storage. Para usar S3: defina MEDIA_STORAGE=s3 e AWS_BUCKET, AWS_ACCESS_KEY, AWS_SECRET_KEY no .env do servidor.',
                'error'
            );
            return null;
        }
        $baseUrl = rtrim((string) ($config['app']['url'] ?? (defined('URL') ? URL : '')), '/');
        return $media->isS3()
            ? $baseUrl . '/media/serve?type=layout&key=' . rawurlencode($key) . ($slug !== '' ? '&tenant=' . rawurlencode($slug) : '')
            : $media->getDisplayUrl('layout', $key);
    }

    private function saveLayoutFromPost(int $escolaId): void
    {
        $config = [];

        // Upload de imagens de layout (logo, logo 1x1, capa, ícone da IA) — sobrescrevem o campo de URL se enviados
        foreach (['layout_logo_upload', 'layout_logo_1x1_upload', 'layout_login_cover_upload', 'layout_ia_avatar_upload'] as $field) {
            $url = $this->uploadLayoutImage($escolaId, $field);
            if ($url !== null) {
                if ($field === 'layout_logo_upload') {
                    $config['logo_url'] = $url;
                    $config['logo_horizontal_url'] = $url;
                    $config['logo_white_url'] = $url;
                    $config['logo_horizontal_white_url'] = $url;
                } elseif ($field === 'layout_logo_1x1_upload') {
                    $config['logo_1x1_url'] = $url;
                } elseif ($field === 'layout_ia_avatar_upload') {
                    $config['ia_avatar_url'] = $url;
                } else {
                    $config['login_cover_url'] = $url;
                }
            }
        }

        // Layout (cor, logo, título, nome/ícone da IA, config primeiro acesso) — URLs por texto só entram se não tiveram upload
        $layoutKeys = ['primary_color', 'primary_text_color', 'logo_url', 'logo_1x1_url', 'login_cover_url', 'system_title', 'system_subtitle', 'ia_name', 'ia_avatar_url', 'primeiro_acesso_turma_obrigatoria'];
        foreach ($layoutKeys as $key) {
            $v = trim((string) ($_POST['layout_' . $key] ?? ''));
            if ($v !== '' && !isset($config[$key])) {
                $config[$key] = $v;
            }
        }

        $modules = $_POST['modules'] ?? [];
        if (!class_exists('ModuloCatalogo', false)) {
            require_once __DIR__ . '/../../Core/ModuloCatalogo.php';
        }
        $config = array_merge(
            $config,
            ModuloCatalogo::configFromPost(is_array($modules) ? $modules : [], self::MODULE_VALID_VALUES)
        );

        // Links no submenu (JSON)
        $menuLinksRaw = trim((string) ($_POST['layout_menu_links_submenu'] ?? ''));
        if ($menuLinksRaw !== '') {
            $decoded = json_decode($menuLinksRaw, true);
            $config['menu_links_submenu'] = is_array($decoded) ? json_encode($decoded) : $menuLinksRaw;
        }

        // Apps externos
        foreach (self::$APPS_EXTERNOS_KEYS as $key) {
            $v = trim((string) ($_POST[$key] ?? ''));
            $config[$key] = $v;
        }

        $externalAppsLinksRaw = trim((string) ($_POST['layout_external_apps_links'] ?? ''));
        if ($externalAppsLinksRaw !== '') {
            $decoded = json_decode($externalAppsLinksRaw, true);
            $config['external_apps_links'] = is_array($decoded) ? json_encode($decoded) : $externalAppsLinksRaw;
        }

        // E-mail (senha em branco = não alterar)
        $existing = $this->getLayoutConfig($escolaId);
        foreach (self::$EMAIL_KEYS as $key) {
            if ($key === 'email_smtp_password') {
                $v = $_POST[$key] ?? '';
                if ($v !== '') {
                    $config[$key] = $v;
                } elseif (isset($existing[$key]) && $existing[$key] !== '') {
                    $config[$key] = $existing[$key];
                }
                continue;
            }
            $v = trim((string) ($_POST[$key] ?? ''));
            $config[$key] = $v;
        }

        // Prompts de IA
        foreach (self::$PROMPT_KEYS as $key) {
            $v = trim((string) ($_POST[$key] ?? ''));
            $config[$key] = $v;
        }

        // Compatibilidade com chaves legadas de prompts no tenant
        $legacyPromptMap = [
            'prompt_tema' => 'prompt_gerar_tema_redacao',
            'prompt_correcao' => 'prompt_corrigir_redacao',
            'prompt_ocr' => 'prompt_transcrever_imagem',
            'prompt_prova' => 'prompt_gerar_prova',
            'prompt_exercicios_jornada' => 'prompt_gerar_exercicios_jornada',
            'prompt_exercicios_personalizados' => 'prompt_gerar_exercicios_personalizados',
        ];
        foreach ($legacyPromptMap as $modernKey => $legacyKey) {
            $v = trim((string) ($config[$modernKey] ?? ''));
            if ($v !== '') {
                $config[$legacyKey] = $v;
            }
        }

        // Chaves de API (em branco = não alterar)
        foreach (self::$API_KEYS as $key) {
            $v = trim((string) ($_POST[$key] ?? ''));
            if ($v !== '') {
                $config[$key] = $v;
            } elseif (isset($existing[$key]) && $existing[$key] !== '') {
                $config[$key] = $existing[$key];
            }
        }

        $this->setLayoutConfig($escolaId, $config);
    }

    /**
     * Verifica HTTPS do domínio da escola (POST com CSRF).
     */
    public function verificarDominio(): void
    {
        $this->requireMaster();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlashMessage('Escola inválida.', 'error');
            header('Location: ' . URL . '/master/escolas');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/escolas/editar?id=' . $id);
            exit;
        }

        $db = Database::getInstance();
        $svc = $this->getDominioEscolaService();
        $res = $svc->verificarEscola($db, $id);
        if (empty($res['success'])) {
            $this->setFlashMessage((string) ($res['error'] ?? 'Falha ao verificar domínio.'), 'error');
        } elseif (($res['ssl_status'] ?? '') === DominioEscolaService::SSL_OK) {
            $this->setFlashMessage('Domínio verificado: HTTPS OK.', 'success');
        } else {
            $msg = (string) ($res['erro'] ?? 'HTTPS ainda não disponível.');
            $this->setFlashMessage('Verificação concluída: ' . $msg . ' Consulte docs/DEPLOY-DOMINIOS.md se o certificado wildcard ainda não foi emitido.', 'error');
        }
        header('Location: ' . URL . '/master/escolas/editar?id=' . $id);
        exit;
    }

    /**
     * Cron HTTP: verifica domínios pendentes ou com certificado a expirar.
     * GET ?key=DOMINIO_VERIFICAR_CRON_KEY
     */
    public function verificarDominiosCron(): void
    {
        $expected = function_exists('env') ? trim((string) env('DOMINIO_VERIFICAR_CRON_KEY', '')) : '';
        $got = trim((string) ($_GET['key'] ?? ''));
        if ($expected === '' || !hash_equals($expected, $got)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        require_once __DIR__ . '/../../Services/DominioEscolaService.php';
        $db = Database::getInstance();
        $svc = new DominioEscolaService();
        $limite = isset($_GET['limite']) && is_numeric($_GET['limite']) ? max(1, (int) $_GET['limite']) : 50;
        $result = $svc->verificarEscolasPendentes($db, $limite);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true] + $result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

}
