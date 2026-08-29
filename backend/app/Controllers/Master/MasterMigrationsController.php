<?php
/**
 * EducaTudo - Gerenciamento de Migrations no painel Master.
 * Visualiza status por escola e executa migrations pendentes em todos os tenants.
 */

require_once __DIR__ . '/../../Core/MysqlProvisioningService.php';

if (!class_exists('MasterMigrationsController')) {

class MasterMigrationsController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';

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

    /**
     * Lista migrations e status por escola.
     */
    public function index()
    {
        $this->requireMaster();
        $db = Database::getInstance();

        $migrationFiles = $this->getMigrationFiles();
        $cargasFiles = [];
        $schemaFiles = [];
        foreach ($migrationFiles as $f) {
            if (stripos($f, 'importar_dados_') !== false) {
                $cargasFiles[] = $f;
            } else {
                $schemaFiles[] = $f;
            }
        }
        $masterMigrationFiles = $this->getMasterMigrationFiles();

        $masterExecutadas = [];
        try {
            $st = $db->query("SELECT migration_name FROM migrations_master");
            if ($st) {
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $masterExecutadas[] = $row['migration_name'];
                }
            }
        } catch (Throwable $e) {
            // Tabela migrations_master pode não existir ainda
        }
        $masterPendentes = array_values(array_diff($masterMigrationFiles, $masterExecutadas));

        $escolas = $db->query("
            SELECT e.id, e.nome, e.slug, e.ativo,
                   b.nome_banco
            FROM escolas e
            INNER JOIN config_escolas_banco b ON b.escola_id = e.id
            ORDER BY e.nome
        ")->fetchAll(PDO::FETCH_ASSOC);

        $executadasRaw = $db->query(
            "SELECT escola_id, migration_name FROM migrations_escolas"
        )->fetchAll(PDO::FETCH_ASSOC);

        $executadasMap = [];
        foreach ($executadasRaw as $r) {
            $executadasMap[$r['escola_id']][] = $r['migration_name'];
        }

        $escolasData = [];
        $totalPendentes = 0;
        foreach ($escolas as $e) {
            $exec = $executadasMap[$e['id']] ?? [];
            $pendentesSchema = array_values(array_diff($schemaFiles, $exec));
            $cargasPendentes = array_values(array_diff($cargasFiles, $exec));
            $escolherLista = array_values(array_merge($pendentesSchema, $cargasPendentes));
            $totalPendentes += count($pendentesSchema);
            $escolasData[] = [
                'id' => $e['id'],
                'nome' => $e['nome'],
                'slug' => $e['slug'],
                'ativo' => $e['ativo'],
                'nome_banco' => $e['nome_banco'],
                'executadas' => count(array_intersect($exec, $schemaFiles)),
                'pendentes' => count($pendentesSchema),
                'pendentes_lista' => $pendentesSchema,
                'cargas_pendentes' => count($cargasPendentes),
                'escolher_lista' => $escolherLista,
            ];
        }

        $this->viewWithLayout('master', 'master/migrations/index', [
            'title' => 'Migrations - Painel Master',
            'page_title' => 'Migrations',
            'current_page' => 'migrations',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'migration_files' => $migrationFiles,
            'cargas_files' => $cargasFiles,
            'total_migrations' => count($schemaFiles),
            'escolas' => $escolasData,
            'total_escolas' => count($escolasData),
            'total_pendentes' => $totalPendentes,
            'master_migration_files' => $masterMigrationFiles,
            'master_executadas' => $masterExecutadas,
            'master_pendentes' => $masterPendentes,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Executa migrations pendentes no banco master (arquivos *_master.sql).
     */
    public function executarMaster()
    {
        $this->requireMaster();
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido. Recarregue a página.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $db = Database::getInstance();
            $executed = MysqlProvisioningService::runMasterMigrations($db->getPdo());
            echo json_encode([
                'success' => true,
                'message' => count($executed) > 0
                    ? count($executed) . ' migration(s) executada(s) no banco master.'
                    : 'Nenhuma migration pendente no master.',
                'executadas' => $executed,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[MasterMigrations] executarMaster: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * Executa apenas as migrations do master selecionadas. POST: migrations[] (nomes dos arquivos).
     */
    public function executarMasterSelecionadas()
    {
        $this->requireMaster();
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido. Recarregue a página.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $migrations = isset($_POST['migrations']) && is_array($_POST['migrations'])
            ? array_values(array_filter(array_map('trim', $_POST['migrations'])))
            : [];
        if (empty($migrations)) {
            echo json_encode(['success' => false, 'error' => 'Selecione ao menos uma migration'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $masterFiles = $this->getMasterMigrationFiles();
        $validSet = array_flip($masterFiles);
        $selected = array_values(array_filter($migrations, function ($f) use ($validSet) {
            return isset($validSet[$f]);
        }));
        if (empty($selected)) {
            echo json_encode(['success' => false, 'error' => 'Nenhuma migration válida selecionada'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $db = Database::getInstance();
            $executed = MysqlProvisioningService::runMasterMigrationsSelected($db->getPdo(), $selected);
            echo json_encode([
                'success' => true,
                'message' => count($executed) . ' migration(s) executada(s) no banco master.',
                'executadas' => $executed,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[MasterMigrations] executarMasterSelecionadas: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /**
     * Executa migrations pendentes em todas as escolas ativas com banco configurado.
     */
    public function executarTodas()
    {
        $this->requireMaster();
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido. Recarregue a página.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $db = Database::getInstance();
        $masterPdo = $db->getPdo();

        $escolas = $db->query("
            SELECT e.id, e.nome,
                   b.host, b.porta, b.nome_banco, b.usuario, b.senha_criptografada
            FROM escolas e
            INNER JOIN config_escolas_banco b ON b.escola_id = e.id
            WHERE e.ativo = 1
            ORDER BY e.nome
        ")->fetchAll(PDO::FETCH_ASSOC);

        $resultados = [];
        foreach ($escolas as $e) {
            $resultados[] = $this->executarMigrationsEscola($masterPdo, $e);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'resultados' => $resultados], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Executa migrations pendentes em uma escola especifica (todas as pendentes).
     */
    public function executarEscola()
    {
        $this->requireMaster();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido. Recarregue a página.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $escolaId = (int) ($_POST['escola_id'] ?? 0);
        if ($escolaId <= 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'escola_id inválido'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = Database::getInstance();
        $masterPdo = $db->getPdo();

        $escola = $db->query("
            SELECT e.id, e.nome,
                   b.host, b.porta, b.nome_banco, b.usuario, b.senha_criptografada
            FROM escolas e
            INNER JOIN config_escolas_banco b ON b.escola_id = e.id
            WHERE e.id = ?
        ", [$escolaId])->fetch(PDO::FETCH_ASSOC);

        if (!$escola) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Escola não encontrada ou sem banco configurado'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $resultado = $this->executarMigrationsEscola($masterPdo, $escola);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'resultado' => $resultado], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Executa apenas as migrations selecionadas em uma escola.
     * POST: escola_id, migrations[] (nomes dos arquivos, ex: 009_modulos_arquivos_videos.sql)
     */
    public function executarEscolaSelecionadas()
    {
        $this->requireMaster();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido. Recarregue a página.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $escolaId = (int) ($_POST['escola_id'] ?? 0);
        $migrations = isset($_POST['migrations']) && is_array($_POST['migrations'])
            ? array_values(array_filter(array_map('trim', $_POST['migrations'])))
            : [];

        if ($escolaId <= 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'escola_id inválido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (empty($migrations)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Selecione ao menos uma migration'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $migrationFiles = $this->getMigrationFiles();
        $validSet = array_flip($migrationFiles);
        $selected = array_values(array_filter($migrations, function ($f) use ($validSet) {
            return isset($validSet[$f]);
        }));
        if (empty($selected)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Nenhuma migration válida selecionada'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = Database::getInstance();
        $masterPdo = $db->getPdo();
        $escola = $db->query("
            SELECT e.id, e.nome,
                   b.host, b.porta, b.nome_banco, b.usuario, b.senha_criptografada
            FROM escolas e
            INNER JOIN config_escolas_banco b ON b.escola_id = e.id
            WHERE e.id = ?
        ", [$escolaId])->fetch(PDO::FETCH_ASSOC);

        if (!$escola) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Escola não encontrada ou sem banco configurado'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $host = $escola['host'] ?? 'localhost';
            $port = (int) ($escola['porta'] ?? 3306);
            $dbName = $escola['nome_banco'] ?? '';
            $user = $escola['usuario'] ?? '';
            $pass = MasterSecretVault::decryptDbPassword($escola['senha_criptografada'] ?? '');
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $tenantPdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
            $tenantPdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            MysqlProvisioningService::runTenantMigrationsSelected($masterPdo, $tenantPdo, (int) $escola['id'], $selected);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'resultado' => [
                    'escola_id' => (int) $escola['id'],
                    'nome' => $escola['nome'],
                    'status' => 'ok',
                    'message' => count($selected) . ' migration(s) executada(s) com sucesso',
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable $e) {
            error_log('[MasterMigrations] executarEscolaSelecionadas escola ' . $escolaId . ': ' . $e->getMessage());
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'resultado' => [
                    'escola_id' => (int) $escola['id'],
                    'nome' => $escola['nome'],
                    'status' => 'erro',
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * Marca todas as migrations pendentes de uma escola como executadas (sem rodar SQL).
     * Útil para escolas que já tinham banco antes do sistema de migrations.
     */
    public function marcarExecutadas()
    {
        $this->requireMaster();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido. Recarregue a página.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $escolaId = (int) ($_POST['escola_id'] ?? 0);
        if ($escolaId <= 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'escola_id inválido'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = Database::getInstance();
        $migrationFiles = $this->getMigrationFiles();

        $executadas = [];
        try {
            $st = $db->query("SELECT migration_name FROM migrations_escolas WHERE escola_id = ?", [$escolaId]);
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $executadas[] = $row['migration_name'];
            }
        } catch (PDOException $e) {
            // tabela pode não existir
        }

        $pendentes = array_diff($migrationFiles, $executadas);
        $marcadas = 0;

        $stmt = $db->getPdo()->prepare(
            "INSERT IGNORE INTO migrations_escolas (escola_id, migration_name) VALUES (?, ?)"
        );
        foreach ($pendentes as $file) {
            $stmt->execute([$escolaId, $file]);
            $marcadas++;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'marcadas' => $marcadas,
            'message' => "$marcadas migration(s) marcada(s) como executada(s)",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function executarMigrationsEscola(PDO $masterPdo, array $escola): array
    {
        $host = $escola['host'] ?? 'localhost';
        $port = (int) ($escola['porta'] ?? 3306);
        $dbName = $escola['nome_banco'] ?? '';
        $user = $escola['usuario'] ?? '';
        $pass = MasterSecretVault::decryptDbPassword($escola['senha_criptografada'] ?? '');

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $tenantPdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
            $tenantPdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            MysqlProvisioningService::runTenantMigrations($masterPdo, $tenantPdo, (int) $escola['id']);

            return [
                'escola_id' => (int) $escola['id'],
                'nome' => $escola['nome'],
                'status' => 'ok',
                'message' => 'Migrations executadas com sucesso',
            ];
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            error_log('[MasterMigrations] Erro ao executar migrations da escola ' . (int) $escola['id'] . ': ' . $errorMessage);
            return [
                'escola_id' => (int) $escola['id'],
                'nome' => $escola['nome'],
                'status' => 'erro',
                'message' => $errorMessage,
                'error_type' => get_class($e),
                'db_name' => (string) ($dbName ?? ''),
                'db_host' => (string) ($host ?? ''),
                'db_port' => (int) ($port ?? 0),
            ];
        }
    }

    private function getMigrationFiles(): array
    {
        $dir = dirname(dirname(dirname(__DIR__))) . '/database/migrations';
        if (!is_dir($dir)) {
            return [];
        }
        $files = scandir($dir);
        $list = [];
        foreach ($files as $f) {
            if (pathinfo($f, PATHINFO_EXTENSION) !== 'sql') continue;
            if (stripos($f, '_rollback') !== false) continue;
            if (stripos($f, 'master') !== false) continue;
            $list[] = $f;
        }
        sort($list);
        return $list;
    }

    private function getMasterMigrationFiles(): array
    {
        $dir = dirname(dirname(dirname(__DIR__))) . '/database/migrations';
        if (!is_dir($dir)) {
            return [];
        }
        $files = scandir($dir);
        $list = [];
        foreach ($files as $f) {
            if (pathinfo($f, PATHINFO_EXTENSION) !== 'sql') continue;
            if (stripos($f, '_rollback') !== false) continue;
            if (stripos($f, 'master') === false) continue;
            $list[] = $f;
        }
        sort($list);
        return $list;
    }
}

}
