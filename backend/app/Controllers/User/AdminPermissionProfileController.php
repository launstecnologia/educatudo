<?php

require_once __DIR__ . '/../../Core/AdminPermissionMatrix.php';

if (!class_exists('AdminPermissionProfileController')) {
class AdminPermissionProfileController extends BaseController
{
    private $db;
    private $auth;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->auth = new AuthManager();
    }

    public function index()
    {
        $user = $this->auth->getUser();
        if (!$this->canManage($user)) {
            $this->redirect('/admin/dashboard');
        }

        $perfis = [];
        $adminPerfisTableMissing = !AdminPermissionMatrix::adminPerfisPermissaoTableExists($this->db);
        if (!$adminPerfisTableMissing) {
            try {
                $perfis = $this->db->fetchAll(
                    "SELECT p.*, u.nome AS criado_por_nome
                     FROM admin_perfis_permissao p
                     LEFT JOIN usuarios u ON u.id = p.criado_por
                     ORDER BY p.nome ASC"
                );
            } catch (\Throwable $e) {
                $perfis = [];
                $adminPerfisTableMissing = true;
            }
        }

        $data = [
            'title' => 'Perfis de Permissão - EducaTudo',
            'user' => $user,
            'current_page' => 'usuarios',
            'perfis' => $perfis,
            'admin_perfis_table_missing' => $adminPerfisTableMissing,
            'permissions_catalog' => AdminPermissionMatrix::moduleCatalog(),
            'permissions_sections' => AdminPermissionMatrix::permissionSections(),
            'permissions_action_labels' => AdminPermissionMatrix::actionLabels(),
            'permissions_defaults_by_type' => $this->defaultsByType(),
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('admin', 'admin/permissoes-perfis/index', $data);
    }

    public function dados($id)
    {
        $user = $this->auth->getUser();
        if (!$this->canManage($user)) {
            $this->json(['error' => 'Acesso negado'], 403);
            return;
        }

        $perfil = $this->db->fetch('SELECT * FROM admin_perfis_permissao WHERE id = ? LIMIT 1', [$id]);
        if (!$perfil) {
            $this->json(['error' => 'Perfil não encontrado.'], 404);
            return;
        }

        $permissoes = [];
        if (!empty($perfil['permissoes_json'])) {
            $decoded = json_decode((string) $perfil['permissoes_json'], true);
            $permissoes = AdminPermissionMatrix::sanitizePermissions($decoded);
        }

        $this->json([
            'success' => true,
            'perfil' => [
                'id' => (int) $perfil['id'],
                'nome' => $perfil['nome'],
                'tipo_base' => $perfil['tipo_base'],
                'descricao' => $perfil['descricao'],
                'ativo' => (int) ($perfil['ativo'] ?? 0) === 1,
            ],
            'permissoes' => $permissoes,
        ]);
    }

    public function store()
    {
        $user = $this->auth->getUser();
        if (!$this->canManage($user)) {
            $this->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $tipoBase = trim((string) ($_POST['tipo_base'] ?? ''));
            $descricao = trim((string) ($_POST['descricao'] ?? ''));
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($nome === '' || $tipoBase === '') {
                throw new Exception('Nome e tipo base são obrigatórios.');
            }

            $allowedTypes = ['dev', 'diretor', 'coordenador', 'financeiro', 'secretaria'];
            if (!in_array($tipoBase, $allowedTypes, true)) {
                throw new Exception('Tipo base inválido.');
            }

            $permissions = $this->extractPermissions($tipoBase);

            $this->db->query(
                "INSERT INTO admin_perfis_permissao (nome, tipo_base, descricao, ativo, permissoes_json, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$nome, $tipoBase, $descricao !== '' ? $descricao : null, $ativo, json_encode($permissions, JSON_UNESCAPED_UNICODE), (int) ($user['id'] ?? 0)]
            );

            $this->json(['success' => true, 'message' => 'Perfil cadastrado com sucesso.']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update($id)
    {
        $user = $this->auth->getUser();
        if (!$this->canManage($user)) {
            $this->json(['error' => 'Acesso negado'], 403);
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $perfil = $this->db->fetch('SELECT id FROM admin_perfis_permissao WHERE id = ? LIMIT 1', [$id]);
            if (!$perfil) {
                throw new Exception('Perfil não encontrado.');
            }

            $nome = trim((string) ($_POST['nome'] ?? ''));
            $tipoBase = trim((string) ($_POST['tipo_base'] ?? ''));
            $descricao = trim((string) ($_POST['descricao'] ?? ''));
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($nome === '' || $tipoBase === '') {
                throw new Exception('Nome e tipo base são obrigatórios.');
            }

            $allowedTypes = ['dev', 'diretor', 'coordenador', 'financeiro', 'secretaria'];
            if (!in_array($tipoBase, $allowedTypes, true)) {
                throw new Exception('Tipo base inválido.');
            }

            $permissions = $this->extractPermissions($tipoBase);

            $this->db->query(
                "UPDATE admin_perfis_permissao
                 SET nome = ?, tipo_base = ?, descricao = ?, ativo = ?, permissoes_json = ?
                 WHERE id = ?",
                [$nome, $tipoBase, $descricao !== '' ? $descricao : null, $ativo, json_encode($permissions, JSON_UNESCAPED_UNICODE), (int) $id]
            );

            $this->json(['success' => true, 'message' => 'Perfil atualizado com sucesso.']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    private function canManage(?array $user): bool
    {
        return is_array($user) && in_array(($user['perfil_admin'] ?? ''), ['dev', 'diretor', 'coordenador'], true);
    }

    private function defaultsByType(): array
    {
        $types = ['dev', 'diretor', 'coordenador', 'financeiro', 'secretaria'];
        $result = [];
        foreach ($types as $type) {
            $result[$type] = AdminPermissionMatrix::defaultPermissionsByProfile($type);
        }
        return $result;
    }

    private function extractPermissions(string $tipoBase): array
    {
        $raw = $_POST['permissions'] ?? [];
        if (!is_array($raw)) {
            return AdminPermissionMatrix::defaultPermissionsByProfile($tipoBase);
        }

        $hasAny = false;
        foreach ($raw as $row) {
            if (is_array($row)) {
                $hasAny = true;
                break;
            }
        }
        if (!$hasAny) {
            return AdminPermissionMatrix::defaultPermissionsByProfile($tipoBase);
        }

        $sanitized = AdminPermissionMatrix::sanitizePermissions($raw);
        return $sanitized === [] ? AdminPermissionMatrix::defaultPermissionsByProfile($tipoBase) : $sanitized;
    }
}
}
