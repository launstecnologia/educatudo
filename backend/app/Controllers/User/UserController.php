<?php
require_once __DIR__ . '/../../Core/AdminPermissionMatrix.php';

if (!class_exists('UsuarioController')) {
class UsuarioController extends BaseController
{
    private $db;
    private $auth;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->auth = new AuthManager();
    }

    /**
     * Listar usuários com controle de permissões, filtros e paginação
     */
    public function index()
    {
        $user = $this->auth->getUser();

        // Verificar permissões
        if (!$this->canManageUsers($user)) {
            $this->redirect('/admin/dashboard');
        }

        // Tipos visíveis na listagem (diferente da regra de criação/edição de perfil)
        $visibleTypes = $this->getVisibleUserTypes($user);
        $allowedTypes = $this->getAllowedUserTypes($user);
        $typesForQuery = $visibleTypes ?: $allowedTypes;

        $filters = [
            'tipo' => trim($_GET['tipo'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'busca' => trim($_GET['busca'] ?? ''),
        ];
        if ($filters['tipo'] !== '' && !in_array($filters['tipo'], $typesForQuery, true)) {
            $filters['tipo'] = '';
        }
        if (!in_array($filters['status'], ['0', '1'], true)) {
            $filters['status'] = '';
        }

        $where = ['u.perfil_admin IN (' . implode(',', array_fill(0, count($typesForQuery), '?')) . ')'];
        $params = $typesForQuery;

        if ($filters['tipo'] !== '') {
            $where[] = 'u.perfil_admin = ?';
            $params[] = $filters['tipo'];
        }
        if ($filters['status'] !== '') {
            $where[] = 'u.ativo = ?';
            $params[] = (int) $filters['status'];
        }
        if ($filters['busca'] !== '') {
            $where[] = '(u.nome LIKE ? OR u.email LIKE ?)';
            $params[] = '%' . $filters['busca'] . '%';
            $params[] = '%' . $filters['busca'] . '%';
        }
        $whereSql = implode(' AND ', $where);

        $perPage = 10;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $hasPerfis = AdminPermissionMatrix::adminPerfisPermissaoTableExists($this->db);
        $perfilJoin = $hasPerfis
            ? 'LEFT JOIN admin_perfis_permissao p ON p.id = u.perfil_permissao_id'
            : '';
        $perfilSelect = $hasPerfis ? 'p.nome as perfil_permissao_nome,' : 'NULL as perfil_permissao_nome,';

        $total = (int) ($this->db->fetch(
            "SELECT COUNT(*) as total FROM usuarios u WHERE {$whereSql}",
            $params
        )['total'] ?? 0);

        $usuarios = $this->db->fetchAll(
            "SELECT u.*,
                {$perfilSelect}
                c.nome as criado_por_nome
             FROM usuarios u
             {$perfilJoin}
             LEFT JOIN usuarios c ON u.criado_por = c.id
             WHERE {$whereSql}
             ORDER BY u.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        require_once __DIR__ . '/../../Helpers/AvatarUrlHelper.php';
        foreach ($usuarios as &$row) {
            $row = $this->normalizeUsuarioAvatarRow($row);
        }
        unset($row);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $data = [
            'title' => 'Gerenciar Usuários - EducaTudo',
            'user' => $user,
            'current_page' => 'usuarios',
            'usuarios' => $usuarios,
            'allowed_types' => $allowedTypes,
            'filters' => $filters,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, $totalPages),
            ],
            'permissions_catalog' => AdminPermissionMatrix::moduleCatalog(),
            'permissions_sections' => AdminPermissionMatrix::permissionSections(),
            'permissions_action_labels' => AdminPermissionMatrix::actionLabels(),
            'permissions_defaults_by_type' => $this->buildDefaultsByType($allowedTypes),
            'permission_profiles' => $this->fetchPermissionProfiles(),
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('admin', 'admin/usuarios/index', $data);
    }

    /**
     * Salvar novo usuário
     */
    public function store()
    {
        $user = $this->auth->getUser();
        
        if (!$this->canManageUsers($user)) {
            $this->json(['error' => 'Acesso negado'], 403);
        }

        // Verificar CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';
            $tipo = $_POST['tipo'] ?? '';
            $confirmarSenha = $_POST['confirmar_senha'] ?? '';
            $perfilPermissaoId = $this->resolvePermissionProfileIdFromPost();
            $permissoesAdmin = $this->extractPermissionsFromPost($tipo, $perfilPermissaoId);

            // Validações
            if (empty($nome) || empty($email) || empty($senha) || empty($tipo)) {
                throw new Exception('Todos os campos são obrigatórios');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }

            if ($senha !== $confirmarSenha) {
                throw new Exception('Senhas não coincidem');
            }

            // Validação de senha forte para admins/coordenadores/direção
            $auth = new Auth();
            $passwordValidation = $auth->validateStrongPassword($senha);
            if ($passwordValidation !== true) {
                throw new Exception($passwordValidation);
            }

            $allowedTypes = $this->getAllowedUserTypes($user);
            if (!in_array($tipo, $allowedTypes)) {
                throw new Exception('Tipo de usuário não permitido');
            }

            // Verificar se email já existe
            $existingUser = $this->db->fetch("SELECT id FROM usuarios WHERE email = ?", [$email]);
            if ($existingUser) {
                throw new Exception('Email já está em uso');
            }

            // Criar usuário
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            
            $this->db->query(
                "INSERT INTO usuarios (nome, email, senha_hash, tipo, perfil_admin, permissoes_admin_json, perfil_permissao_id, criado_por, ativo) 
                 VALUES (?, ?, ?, 'admin_escola', ?, ?, ?, ?, TRUE)",
                [$nome, $email, $senhaHash, $tipo, $this->permissionsToJson($permissoesAdmin), $perfilPermissaoId, $user['id']]
            );

            $this->json(['success' => true, 'message' => 'Usuário criado com sucesso!']);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Dados de um usuário (JSON) para popular o offcanvas de edição
     */
    public function dados($id)
    {
        $user = $this->auth->getUser();

        if (!$this->canManageUsers($user)) {
            $this->json(['error' => 'Acesso negado'], 403);
        }

        $usuario = $this->db->fetch("SELECT * FROM usuarios WHERE id = ?", [$id]);
        if (!$usuario) {
            $this->json(['error' => 'Usuário não encontrado'], 404);
        }

        if (!$this->canEditUser($user, $usuario)) {
            $this->json(['error' => 'Sem permissão para editar este usuário'], 403);
        }

        $usuario = $this->normalizeUsuarioAvatarRow($usuario);

        $this->json([
            'success' => true,
            'usuario' => [
                'id' => (int) $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'tipo' => $usuario['perfil_admin'],
                'perfil_permissao_id' => $usuario['perfil_permissao_id'],
                'ativo' => (bool) $usuario['ativo'],
                'avatar_url' => $usuario['avatar_url'],
                'created_at' => $usuario['created_at'],
                'updated_at' => $usuario['updated_at'],
            ],
            'selected_permissions' => $this->resolveStoredPermissions($usuario),
        ]);
    }

    /**
     * Atualizar usuário
     */
    public function update($id)
    {
        $user = $this->auth->getUser();
        
        if (!$this->canManageUsers($user)) {
            $this->json(['error' => 'Acesso negado'], 403);
        }

        // Verificar CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $usuario = $this->db->fetch("SELECT * FROM usuarios WHERE id = ?", [$id]);
            if (!$usuario) {
                throw new Exception('Usuário não encontrado');
            }

            if (!$this->canEditUser($user, $usuario)) {
                throw new Exception('Sem permissão para editar este usuário');
            }

            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $tipo = $_POST['tipo'] ?? '';
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $perfilPermissaoId = $this->resolvePermissionProfileIdFromPost();
            $permissoesAdmin = $this->extractPermissionsFromPost($tipo, $perfilPermissaoId);

            // Validações
            if (empty($nome) || empty($email) || empty($tipo)) {
                throw new Exception('Todos os campos são obrigatórios');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }

            $allowedTypes = $this->getAllowedUserTypes($user);
            if (!in_array($tipo, $allowedTypes)) {
                throw new Exception('Tipo de usuário não permitido');
            }

            // Verificar se email já existe (exceto para o próprio usuário)
            $existingUser = $this->db->fetch("SELECT id FROM usuarios WHERE email = ? AND id != ?", [$email, $id]);
            if ($existingUser) {
                throw new Exception('Email já está em uso');
            }

            // Atualizar usuário
            $this->db->query(
                "UPDATE usuarios SET nome = ?, email = ?, perfil_admin = ?, permissoes_admin_json = ?, perfil_permissao_id = ?, ativo = ? WHERE id = ?",
                [$nome, $email, $tipo, $this->permissionsToJson($permissoesAdmin), $perfilPermissaoId, $ativo, $id]
            );

            $this->json(['success' => true, 'message' => 'Usuário atualizado com sucesso!']);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Upload de avatar
     */
    public function uploadAvatar($id)
    {
        $user = $this->auth->getUser();
        
        if (!$this->canManageUsers($user)) {
            $this->json(['error' => 'Acesso negado'], 403);
        }

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? $_SERVER['HTTP_CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
            $this->json(['error' => 'Arquivo muito grande. Use uma imagem de até 2MB.'], 400);
        }

        $csrfToken = $this->tokenCsrfDaRequisicao();
        if ($csrfToken === '' || !$this->verifyCsrfToken($csrfToken)) {
            $this->json([
                'error' => 'Token inválido. Recarregue a página e tente novamente.',
                'csrf_token' => $this->generateCsrfToken(),
            ], 400);
        }

        try {
            $usuario = $this->db->fetch("SELECT * FROM usuarios WHERE id = ?", [$id]);
            if (!$usuario) {
                throw new Exception('Usuário não encontrado');
            }

            if (!$this->canEditUser($user, $usuario)) {
                throw new Exception('Sem permissão para editar este usuário');
            }

            $arquivoAvatar = $this->resolverArquivoAvatar();
            $tmpPath = $arquivoAvatar['path'];
            $nomeOriginal = $arquivoAvatar['name'];
            $tamanho = $arquivoAvatar['size'];
            $apagarTmp = $arquivoAvatar['unlink'];

            try {
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $fileExtension = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

                if ($tamanho > 2 * 1024 * 1024) {
                    throw new Exception('Arquivo muito grande. Máximo 2MB');
                }

                $mimePermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $mimeReal = '';
                if (function_exists('finfo_open') && is_file($tmpPath)) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $detected = finfo_file($finfo, $tmpPath);
                        finfo_close($finfo);
                        if (is_string($detected) && $detected !== '') {
                            $mimeReal = $detected;
                        }
                    }
                }
                if (in_array($mimeReal, ['image/heic', 'image/heif'], true)) {
                    throw new Exception('Este formato (HEIC) não é suportado. Salve a foto como JPG ou PNG e envie de novo.');
                }
                $extPorMime = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                ];
                if (!in_array($fileExtension, $allowedExtensions, true)) {
                    if (isset($extPorMime[$mimeReal])) {
                        $fileExtension = $extPorMime[$mimeReal];
                    } else {
                        throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP.');
                    }
                }
                if (!in_array($mimeReal, $mimePermitidos, true)) {
                    throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP.');
                }

                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                $media = new MediaStorageService($this->config);

                $fileName = 'avatar_' . $id . '_' . time() . '.' . $fileExtension;
                if (!$media->put('avatars', $fileName, $tmpPath, $mimeReal)) {
                    throw new Exception('Erro ao salvar arquivo');
                }
            } finally {
                if ($apagarTmp && is_file($tmpPath)) {
                    @unlink($tmpPath);
                }
            }

            // Remover avatar anterior se existir
            if (!empty($usuario['avatar_url'])) {
                $this->removerAvatarAnteriorStorage($media, $usuario['avatar_url']);
            }

            require_once __DIR__ . '/../../Helpers/AvatarUrlHelper.php';
            $slug = trim((string) ($this->config['tenant']['slug'] ?? $this->config['school']['code'] ?? ''));
            $storedPath = AvatarUrlHelper::buildStoredAvatarPath($fileName, $slug !== '' ? $slug : null);
            $displayUrl = AvatarUrlHelper::normalizeAdminAvatarUrl($storedPath) ?? $storedPath;

            $this->db->query(
                "UPDATE usuarios SET avatar_url = ? WHERE id = ?",
                [$storedPath, $id]
            );

            $currentUser = $this->auth->getUser();
            if ($currentUser && (int) ($currentUser['id'] ?? 0) === (int) $id) {
                $_SESSION['avatar_url'] = $displayUrl;
            }

            $this->json([
                'success' => true, 
                'message' => 'Avatar enviado com sucesso!',
                'url' => $displayUrl
            ]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @return array{path: string, name: string, size: int, unlink: bool}
     */
    private function resolverArquivoAvatar(): array
    {
        if (isset($_FILES['avatar']) && (int) $_FILES['avatar']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            return [
                'path' => (string) $_FILES['avatar']['tmp_name'],
                'name' => (string) $_FILES['avatar']['name'],
                'size' => (int) $_FILES['avatar']['size'],
                'unlink' => false,
            ];
        }

        $dataUrl = trim((string) ($_POST['avatar_base64'] ?? ''));
        if ($dataUrl === '' || strncmp($dataUrl, 'data:', 5) !== 0 || strpos($dataUrl, 'base64,') === false) {
            $uploadError = (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE);
            throw new Exception($this->mensagemErroUploadAvatar($uploadError));
        }
        if (preg_match('#^data:image/(heic|heif)#i', $dataUrl)) {
            throw new Exception('Este formato (HEIC) não é suportado. Salve a foto como JPG ou PNG e envie de novo.');
        }
        if (strlen($dataUrl) > 4 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Máximo 2MB');
        }

        $encoded = substr($dataUrl, (int) strpos($dataUrl, ',') + 1);
        $bin = base64_decode($encoded, true);
        if ($bin === false || $bin === '') {
            throw new Exception('Nenhuma imagem foi recebida. Tente novamente.');
        }
        if (strlen($bin) > 2 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Máximo 2MB');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'avatar_');
        if ($tmp === false) {
            throw new Exception('Não foi possível salvar a imagem no servidor.');
        }
        if (file_put_contents($tmp, $bin) === false) {
            @unlink($tmp);
            throw new Exception('Não foi possível salvar a imagem no servidor.');
        }

        return [
            'path' => $tmp,
            'name' => (string) ($_POST['avatar_nome'] ?? 'avatar.jpg'),
            'size' => strlen($bin),
            'unlink' => true,
        ];
    }

    private function tokenCsrfDaRequisicao(): string
    {
        $candidatos = [
            $_POST['_token'] ?? '',
            $_POST['csrf_token'] ?? '',
            $_GET['_token'] ?? '',
            $_GET['csrf_token'] ?? '',
        ];
        foreach ($candidatos as $candidato) {
            $token = trim((string) $candidato);
            if ($token !== '') {
                return $token;
            }
        }
        if (function_exists('getallheaders')) {
            $headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
            $token = trim((string) ($headers['x-csrf-token'] ?? ''));
            if ($token !== '') {
                return $token;
            }
        }
        return trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? ''));
    }

    private function mensagemErroUploadAvatar(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Arquivo muito grande. Use uma imagem de até 2MB.';
            case UPLOAD_ERR_PARTIAL:
                return 'O envio da imagem foi interrompido. Tente novamente.';
            case UPLOAD_ERR_NO_FILE:
                return 'Nenhuma imagem foi recebida. Tente novamente.';
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
                return 'Não foi possível salvar a imagem no servidor.';
            default:
                return 'Erro no upload da imagem.';
        }
    }

    /**
     * Trocar senha do usuário
     */
    public function changePassword($id)
    {
        $user = $this->auth->getUser();
        
        if (!$this->canManageUsers($user)) {
            $this->json(['error' => 'Acesso negado'], 403);
        }

        if (!$this->verifyCsrfToken($this->tokenCsrfDaRequisicao())) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $usuario = $this->db->fetch("SELECT * FROM usuarios WHERE id = ?", [$id]);
            if (!$usuario) {
                throw new Exception('Usuário não encontrado');
            }

            if (!$this->canEditUser($user, $usuario)) {
                throw new Exception('Sem permissão para alterar senha deste usuário');
            }

            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmarSenha = $_POST['confirmar_senha'] ?? '';
            $motivo = trim($_POST['motivo'] ?? '');

            // Validações
            if (empty($novaSenha) || empty($confirmarSenha)) {
                throw new Exception('Todos os campos são obrigatórios');
            }

            if ($novaSenha !== $confirmarSenha) {
                throw new Exception('Senhas não coincidem');
            }

            if (strlen($novaSenha) < 6) {
                throw new Exception('Senha deve ter pelo menos 6 caracteres');
            }

            // Atualizar senha
            $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            
            $this->db->query(
                "UPDATE usuarios SET senha_hash = ? WHERE id = ?",
                [$senhaHash, $id]
            );

            // Log da alteração
            $this->db->query(
                "INSERT INTO logs_senhas (usuario_id, alterado_por, motivo) VALUES (?, ?, ?)",
                [$id, $user['id'], $motivo]
            );

            $this->json(['success' => true, 'message' => 'Senha alterada com sucesso!']);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    private function normalizeUsuarioAvatarRow(array $row): array
    {
        if (!class_exists('AvatarUrlHelper', false)) {
            require_once __DIR__ . '/../../Helpers/AvatarUrlHelper.php';
        }
        $normalized = AvatarUrlHelper::normalizeAdminAvatarUrl($row['avatar_url'] ?? null);
        if ($normalized !== null) {
            $row['avatar_url'] = $normalized;
        }

        return $row;
    }

    /**
     * Verificar se usuário pode gerenciar outros usuários
     */
    private function canManageUsers($user)
    {
        return in_array($user['perfil_admin'] ?? '', ['dev', 'diretor', 'coordenador']);
    }

    /**
     * Obter tipos de usuário que o usuário atual pode criar/editar
     * 
     * Regras de Permissão:
     * - Dev: pode criar Dev, Diretor e Coordenador
     * - Diretor: pode criar outro Diretor e Coordenador  
     * - Coordenador: pode criar outro Coordenador
     * - Coordenador NÃO pode criar Diretor nem Dev
     * - Diretor NÃO pode criar Dev
     */
    private function getAllowedUserTypes($user)
    {
        $perfil = $user['perfil_admin'] ?? '';
        
        switch ($perfil) {
            case 'dev':
                return ['dev', 'diretor', 'coordenador', 'aee', 'financeiro', 'secretaria'];
            case 'diretor':
                return ['diretor', 'coordenador', 'aee', 'financeiro', 'secretaria'];
            case 'coordenador':
                return ['coordenador', 'aee', 'secretaria'];
            default:
                return [];
        }
    }

    /**
     * Tipos que aparecem na listagem de usuários.
     * Regra solicitada:
     * - Dev vê todos
     * - Diretor vê todos
     * - Coordenador vê todos
     */
    private function getVisibleUserTypes($user)
    {
        $perfil = $user['perfil_admin'] ?? '';
        if (in_array($perfil, ['dev', 'diretor', 'coordenador'], true)) {
            return ['dev', 'diretor', 'coordenador', 'aee', 'financeiro', 'secretaria'];
        }
        return [];
    }

    /**
     * Verificar se pode editar um usuário específico
     */
    private function canEditUser($currentUser, $targetUser)
    {
        $currentPerfil = $currentUser['perfil_admin'] ?? '';
        $targetPerfil = $targetUser['perfil_admin'] ?? '';

        // Dev pode editar todos
        if ($currentPerfil === 'dev') {
            return true;
        }

        // Diretor edita todos, exceto Dev.
        if ($currentPerfil === 'diretor' && $targetPerfil !== 'dev') {
            return true;
        }

        // Coordenador edita todos, exceto Dev e Diretor.
        if ($currentPerfil === 'coordenador' && !in_array($targetPerfil, ['dev', 'diretor'], true)) {
            return true;
        }

        // Não pode editar usuários de nível igual ou superior
        return false;
    }

    /**
     * @param list<string> $allowedTypes
     * @return array<string, array<string, array<string, bool>>>
     */
    private function buildDefaultsByType(array $allowedTypes): array
    {
        $defaults = [];
        foreach ($allowedTypes as $type) {
            $defaults[$type] = AdminPermissionMatrix::defaultPermissionsByProfile((string) $type);
        }
        return $defaults;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function extractPermissionsFromPost(string $tipo, ?int $perfilPermissaoId = null): array
    {
        $base = $this->resolveBasePermissions($tipo, $perfilPermissaoId);
        $raw = $_POST['permissions'] ?? [];
        if (!is_array($raw)) {
            return $base;
        }

        $hasAnyPermissionKey = false;
        foreach ($raw as $moduleData) {
            if (is_array($moduleData)) {
                $hasAnyPermissionKey = true;
                break;
            }
        }
        if (!$hasAnyPermissionKey) {
            return $base;
        }

        $sanitized = AdminPermissionMatrix::sanitizePermissions($raw);
        return $sanitized === [] ? $base : $sanitized;
    }

    private function permissionsToJson(array $permissions): ?string
    {
        if ($permissions === []) {
            return null;
        }
        $encoded = json_encode($permissions, JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : null;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function resolveStoredPermissions(array $usuario): array
    {
        $raw = $usuario['permissoes_admin_json'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $sanitized = AdminPermissionMatrix::sanitizePermissions($decoded);
            if ($sanitized !== []) {
                return $sanitized;
            }
        }

        $perfil = (string) ($usuario['perfil_admin'] ?? '');
        return AdminPermissionMatrix::defaultPermissionsByProfile($perfil);
    }

    private function resolveBasePermissions(string $tipo, ?int $perfilPermissaoId): array
    {
        if ($perfilPermissaoId !== null && AdminPermissionMatrix::adminPerfisPermissaoTableExists($this->db)) {
            try {
                $profile = $this->db->fetch(
                    "SELECT permissoes_json FROM admin_perfis_permissao WHERE id = ? AND ativo = 1 LIMIT 1",
                    [$perfilPermissaoId]
                );
            } catch (\Throwable $e) {
                $profile = null;
            }
            if ($profile && !empty($profile['permissoes_json'])) {
                $decoded = json_decode((string) $profile['permissoes_json'], true);
                $sanitized = AdminPermissionMatrix::sanitizePermissions($decoded);
                if ($sanitized !== []) {
                    return $sanitized;
                }
            }
        }

        return AdminPermissionMatrix::defaultPermissionsByProfile($tipo);
    }

    /**
     * @return list<array{id:int,nome:string,tipo_base:string,descricao:?string,ativo:int,permissoes_json:string}>
     */
    private function fetchPermissionProfiles(): array
    {
        if (!AdminPermissionMatrix::adminPerfisPermissaoTableExists($this->db)) {
            return [];
        }
        try {
            return $this->db->fetchAll(
                "SELECT id, nome, tipo_base, descricao, ativo, permissoes_json
             FROM admin_perfis_permissao
             ORDER BY nome ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function resolvePermissionProfileIdFromPost(): ?int
    {
        $raw = $_POST['perfil_permissao_id'] ?? '';
        $id = (int) $raw;
        return $id > 0 ? $id : null;
    }

    /**
     * Resolve URL/caminho salvo do avatar para caminho absoluto no filesystem.
     */
    private function resolverCaminhoArquivoAvatar($avatarRef)
    {
        $ref = trim((string) $avatarRef);
        if ($ref === '') {
            return null;
        }

        $path = parse_url($ref, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $ref;
        }

        $path = str_replace('\\', '/', $path);
        if (strpos($path, '/public/uploads/') !== false) {
            $path = substr($path, strpos($path, '/public/uploads/'));
            return dirname(__DIR__, 3) . $path;
        }

        if (strpos($path, '/uploads/') !== false) {
            $path = substr($path, strpos($path, '/uploads/'));
            return dirname(__DIR__, 3) . '/public' . $path;
        }

        return null;
    }

    /**
     * Remove avatar anterior no storage atual (S3/local).
     */
    private function removerAvatarAnteriorStorage(MediaStorageService $media, $avatarRef): void
    {
        $mediaRef = $this->extrairMediaTypeKeyDeUrl($avatarRef);
        if ($mediaRef !== null) {
            $tenantSlug = $mediaRef['tenant'] ?? '';
            if ($tenantSlug !== '') {
                $config = $this->config;
                $config['tenant'] = array_merge($config['tenant'] ?? [], ['slug' => $tenantSlug]);
                $config['school'] = array_merge($config['school'] ?? [], ['code' => $tenantSlug]);
                $config['media'] = array_merge($config['media'] ?? [], ['tenant_prefix' => true]);
                $media = new MediaStorageService($config);
            }
            $media->delete($mediaRef['type'], $mediaRef['key']);
            return;
        }

        $oldPath = $this->resolverCaminhoArquivoAvatar($avatarRef);
        if ($oldPath && file_exists($oldPath)) {
            @unlink($oldPath);
        }
    }

    /**
     * Extrai type/key/tenant de URLs no formato /media/serve?type=...&key=...&tenant=...
     */
    private function extrairMediaTypeKeyDeUrl($avatarRef): ?array
    {
        $ref = trim((string) $avatarRef);
        if ($ref === '' || strpos($ref, '/media/serve') === false) {
            return null;
        }
        $query = parse_url($ref, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return null;
        }
        parse_str($query, $params);
        $type = isset($params['type']) ? trim((string) $params['type']) : '';
        $key = isset($params['key']) ? trim((string) $params['key']) : '';
        if ($type === '' || $key === '') {
            return null;
        }
        $result = ['type' => $type, 'key' => $key];
        if (isset($params['tenant']) && trim((string) $params['tenant']) !== '') {
            $result['tenant'] = trim((string) $params['tenant']);
        }
        return $result;
    }
}
}
