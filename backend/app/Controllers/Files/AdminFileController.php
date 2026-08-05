<?php
/**
 * EducaTudo - Módulo de Arquivos (Admin)
 */

if (!class_exists('AdminFileController')) {
class AdminFileController extends BaseController
{
    private $auth;
    private $db;

    private const EXTENSOES_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'ogg', 'mp3', 'wav'];

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();

        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }

        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
            return;
        }
    }

    private function redirectToCorrectDashboard($tipo): void
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/admin/dashboard');
        }
    }

    private function canManageArquivos(): bool
    {
        $user = $this->auth->getUser();
        $perfil = trim((string) ($user['perfil_admin'] ?? ''));
        if ($perfil === '') {
            return true;
        }
        return in_array($perfil, ['diretor', 'coordenador', 'dev'], true);
    }

    private function ensureCanManageOrRedirect(): bool
    {
        if ($this->canManageArquivos()) {
            return true;
        }
        $this->setFlashMessage('Apenas diretor ou coordenador podem gerenciar arquivos.', 'error');
        $this->redirect('/admin/dashboard');
        return false;
    }

    private function getTurmasAtivas(): array
    {
        return $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC");
    }

    private function getMateriasAtivas(): array
    {
        return $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome ASC");
    }

    private function getProfessoresAtivos(): array
    {
        return $this->db->fetchAll("SELECT id, nome, email FROM professores WHERE ativo = 1 ORDER BY nome ASC");
    }

    private function detectMimeType(string $tmpPath, string $fallback = ''): string
    {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($tmpPath);
            if (!empty($mime)) {
                return (string) $mime;
            }
        }
        return $fallback !== '' ? $fallback : 'application/octet-stream';
    }

    private function anexoPathToMediaKey(string $caminho): string
    {
        $path = ltrim(str_replace('\\', '/', $caminho), '/');
        if (strpos($path, 'public/uploads/arquivos/') === 0) {
            return substr($path, strlen('public/uploads/arquivos/'));
        }
        if (strpos($path, 'arquivos/') === 0) {
            return substr($path, strlen('arquivos/'));
        }
        return basename($path);
    }

    private function parseFiltrosFromRequest(): array
    {
        return [
            'materia_id' => isset($_GET['materia_id']) && $_GET['materia_id'] !== '' ? (int) $_GET['materia_id'] : 0,
            'professor_id' => isset($_GET['professor_id']) && $_GET['professor_id'] !== '' ? (int) $_GET['professor_id'] : 0,
            'turma_id' => isset($_GET['turma_id']) && $_GET['turma_id'] !== '' ? (int) $_GET['turma_id'] : 0,
            'assunto' => trim((string) ($_GET['assunto'] ?? '')),
            'data_de' => trim((string) ($_GET['data_de'] ?? '')),
            'data_ate' => trim((string) ($_GET['data_ate'] ?? '')),
            'pasta_id' => null,
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
        ];
    }

    private function buildFilterQuery(array $filtros, array $extra = []): string
    {
        $params = array_merge([
            'materia_id' => $filtros['materia_id'] ?? 0,
            'professor_id' => $filtros['professor_id'] ?? 0,
            'turma_id' => $filtros['turma_id'] ?? 0,
            'assunto' => $filtros['assunto'] ?? '',
            'data_de' => $filtros['data_de'] ?? '',
            'data_ate' => $filtros['data_ate'] ?? '',
            'page' => $filtros['page'] ?? 1,
        ], $extra);
        $query = array_filter($params, static function ($value) {
            return $value !== '' && $value !== 0 && $value !== null;
        });
        return empty($query) ? '' : ('?' . http_build_query($query));
    }

    private function readItems(array $filtros): array
    {
        $where = ['1=1'];
        $params = [];
        if (($filtros['materia_id'] ?? 0) > 0) {
            $where[] = 'ma.materia_id = :materia_id';
            $params['materia_id'] = (int) $filtros['materia_id'];
        }
        if (($filtros['professor_id'] ?? 0) > 0) {
            $where[] = 'ma.professor_id = :professor_id';
            $params['professor_id'] = (int) $filtros['professor_id'];
        }
        if (($filtros['turma_id'] ?? 0) > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM modulos_arquivos_turmas mat WHERE mat.modulo_arquivo_id = ma.id AND mat.turma_id = :turma_id)';
            $params['turma_id'] = (int) $filtros['turma_id'];
        }
        if (($filtros['assunto'] ?? '') !== '') {
            $where[] = '(ma.titulo LIKE :assunto OR ma.descricao LIKE :assunto2)';
            $params['assunto'] = '%' . $filtros['assunto'] . '%';
            $params['assunto2'] = '%' . $filtros['assunto'] . '%';
        }
        if (($filtros['data_de'] ?? '') !== '') {
            $where[] = 'DATE(ma.created_at) >= :data_de';
            $params['data_de'] = $filtros['data_de'];
        }
        if (($filtros['data_ate'] ?? '') !== '') {
            $where[] = 'DATE(ma.created_at) <= :data_ate';
            $params['data_ate'] = $filtros['data_ate'];
        }
        if (isset($filtros['pasta_id']) && $filtros['pasta_id'] !== null) {
            $where[] = 'ma.pasta_id = :pasta_id';
            $params['pasta_id'] = (int) $filtros['pasta_id'];
        }
        $whereSql = implode(' AND ', $where);
        $perPage = 15;
        $page = max(1, (int) ($filtros['page'] ?? 1));
        $total = (int) $this->db->fetch(
            "SELECT COUNT(*) AS c FROM modulos_arquivos ma WHERE {$whereSql}",
            $params
        )['c'];
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $items = $this->db->fetchAll(
            "SELECT ma.id, ma.titulo, ma.descricao, ma.created_at,
                    ma.materia_id, ma.professor_id, ma.pasta_id, ma.recuperacao,
                    m.nome AS materia_nome, p.nome AS professor_nome,
                    aa.id AS anexo_id, aa.caminho, aa.nome_original, aa.extensao, aa.tamanho,
                    (SELECT GROUP_CONCAT(mat.turma_id ORDER BY mat.turma_id SEPARATOR ',')
                       FROM modulos_arquivos_turmas mat
                      WHERE mat.modulo_arquivo_id = ma.id) AS turma_ids_csv,
                    (SELECT GROUP_CONCAT(t.nome ORDER BY t.nome SEPARATOR ', ')
                       FROM modulos_arquivos_turmas mat
                       JOIN turmas t ON t.id = mat.turma_id
                      WHERE mat.modulo_arquivo_id = ma.id) AS turmas_nomes
             FROM modulos_arquivos ma
             LEFT JOIN materias m ON m.id = ma.materia_id
             LEFT JOIN professores p ON p.id = ma.professor_id
             LEFT JOIN modulos_arquivos_anexos aa
                    ON aa.id = (
                        SELECT a2.id FROM modulos_arquivos_anexos a2
                        WHERE a2.modulo_arquivo_id = ma.id
                        ORDER BY a2.ordem ASC, a2.id ASC
                        LIMIT 1
                    )
             WHERE {$whereSql}
             ORDER BY ma.created_at DESC, ma.id DESC
             LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        );

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    private function getItemById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $item = $this->db->fetch(
            "SELECT ma.*, m.nome AS materia_nome, p.nome AS professor_nome
             FROM modulos_arquivos ma
             LEFT JOIN materias m ON m.id = ma.materia_id
             LEFT JOIN professores p ON p.id = ma.professor_id
             WHERE ma.id = :id",
            ['id' => $id]
        );
        return $item ?: null;
    }

    private function getTurmaIdsForItem(int $id): array
    {
        $rows = $this->db->fetchAll(
            "SELECT turma_id FROM modulos_arquivos_turmas WHERE modulo_arquivo_id = :id ORDER BY turma_id",
            ['id' => $id]
        );
        $ids = array_map('intval', array_column($rows, 'turma_id'));
        if (!empty($ids)) {
            return $ids;
        }
        $item = $this->getItemById($id);
        if ($item && !empty($item['turma_id'])) {
            return [(int) $item['turma_id']];
        }
        return [];
    }

    private function getAnexosForItem(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT id, nome_original, extensao, tamanho, caminho
             FROM modulos_arquivos_anexos
             WHERE modulo_arquivo_id = :id
             ORDER BY ordem ASC, id ASC",
            ['id' => $id]
        );
    }

    private function sanitizeDescricao(string $descricao): string
    {
        return \App\Utils\HtmlSanitizer::clean($descricao);
    }

    private function formViewData(?array $item, array $turmaIds = []): array
    {
        return [
            'title' => ($item ? 'Editar arquivo' : 'Novo arquivo') . ' - Admin',
            'page_title' => $item ? 'Editar arquivo' : 'Novo arquivo',
            'user' => $this->auth->getUser(),
            'item' => $item,
            'turma_ids' => $turmaIds,
            'anexos' => $item ? $this->getAnexosForItem((int) $item['id']) : [],
            'turmas' => $this->getTurmasAtivas(),
            'materias' => $this->getMateriasAtivas(),
            'professores' => $this->getProfessoresAtivos(),
            'current_page' => 'arquivos',
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ];
    }

    public function criar()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }

        $this->viewWithLayout('admin', 'admin/arquivos/form', $this->formViewData(null));
    }

    public function editar()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);
        $item = $this->getItemById($id);
        if (!$item) {
            $this->setFlashMessage('Arquivo não encontrado.', 'error');
            $this->redirect('/admin/arquivos');
            return;
        }

        $this->viewWithLayout('admin', 'admin/arquivos/form', $this->formViewData($item, $this->getTurmaIdsForItem($id)));
    }

    public function index()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }

        $filtros = $this->parseFiltrosFromRequest();
        $pastaAtualId = isset($_GET['pasta_id']) && $_GET['pasta_id'] !== '' ? (int)$_GET['pasta_id'] : null;

        $hasParentCol = (bool)$this->db->fetch(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'modulos_arquivos_pastas' AND COLUMN_NAME = 'parent_id'"
        );

        // Pastas filhas do nível atual (root = parent_id IS NULL)
        if ($hasParentCol) {
            $parentSql = $pastaAtualId === null ? 'p.parent_id IS NULL' : 'p.parent_id = :parent_id';
            $parentParam = $pastaAtualId === null ? [] : ['parent_id' => $pastaAtualId];
            $pastas = $this->db->fetchAll(
                "SELECT p.*,
                        (SELECT COUNT(*) FROM modulos_arquivos ma WHERE ma.pasta_id = p.id) AS total_arquivos,
                        (SELECT COUNT(*) FROM modulos_arquivos_pastas sub WHERE sub.parent_id = p.id) AS total_subpastas
                 FROM modulos_arquivos_pastas p
                 WHERE p.criado_por_tipo = 'admin' AND {$parentSql}
                 ORDER BY p.ordem ASC, p.nome ASC",
                $parentParam
            );
        } else {
            $pastas = $this->db->fetchAll(
                "SELECT p.*, (SELECT COUNT(*) FROM modulos_arquivos ma WHERE ma.pasta_id = p.id) AS total_arquivos, 0 AS total_subpastas
                 FROM modulos_arquivos_pastas p
                 WHERE p.criado_por_tipo = 'admin'
                 ORDER BY p.ordem ASC, p.nome ASC"
            );
        }

        $pastaAtual = null;
        if ($pastaAtualId !== null) {
            $pastaAtual = $this->db->fetch(
                "SELECT * FROM modulos_arquivos_pastas WHERE id = :id AND criado_por_tipo = 'admin'",
                ['id' => $pastaAtualId]
            );
        }

        // Breadcrumb de ancestrais
        $breadcrumb = [];
        if ($pastaAtual && $hasParentCol) {
            $cur = $pastaAtual;
            while (!empty($cur['parent_id'])) {
                $cur = $this->db->fetch("SELECT * FROM modulos_arquivos_pastas WHERE id = :id", ['id' => $cur['parent_id']]);
                if (!$cur) break;
                array_unshift($breadcrumb, $cur);
            }
        }

        // Filtro: só mostrar arquivos da pasta atual (sem subpastas)
        // Na raiz sem filtros, mostrar apenas arquivos sem pasta
        $temFiltroAtivo = !empty($filtros['materia_id']) || !empty($filtros['professor_id']) || !empty($filtros['turma_id']) || !empty($filtros['titulo']);
        if ($pastaAtualId !== null) {
            $filtros['pasta_id'] = $pastaAtualId;
        } elseif (!$temFiltroAtivo) {
            $filtros['pasta_id'] = null; // raiz: arquivos sem pasta
        }

        $result = $this->readItems($filtros);

        // Todas as pastas para o select "Mover para"
        $todasPastas = $this->db->fetchAll(
            "SELECT id, nome FROM modulos_arquivos_pastas WHERE criado_por_tipo = 'admin' ORDER BY nome ASC"
        );

        $this->viewWithLayout('admin', 'admin/arquivos/index', [
            'title' => 'Arquivos - Admin',
            'page_title' => 'Arquivos da Escola',
            'user' => $this->auth->getUser(),
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'filtros' => $filtros,
            'filter_query' => $this->buildFilterQuery($filtros),
            'turmas' => $this->getTurmasAtivas(),
            'materias' => $this->getMateriasAtivas(),
            'professores' => $this->getProfessoresAtivos(),
            'pastas' => $pastas,
            'todas_pastas' => $todasPastas,
            'pasta_atual' => $pastaAtual,
            'pasta_atual_id' => $pastaAtualId,
            'breadcrumb' => $breadcrumb,
            'has_parent_col' => $hasParentCol,
            'current_page' => 'arquivos',
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function createFolder()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 403);
            return;
        }
        $nome = trim($_POST['nome'] ?? '');
        $cor = trim($_POST['cor'] ?? '#6366f1');
        if ($nome === '') {
            $this->json(['error' => 'Nome obrigatório'], 400);
            return;
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
            $cor = '#6366f1';
        }
        $parentId = isset($_POST['parent_id']) && (int)$_POST['parent_id'] > 0 ? (int)$_POST['parent_id'] : null;

        $hasParentCol = (bool)$this->db->fetch(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'modulos_arquivos_pastas' AND COLUMN_NAME = 'parent_id'"
        );

        if ($hasParentCol) {
            $id = $this->db->insert(
                "INSERT INTO modulos_arquivos_pastas (nome, cor, professor_id, criado_por_tipo, parent_id) VALUES (:nome, :cor, NULL, 'admin', :parent_id)",
                ['nome' => $nome, 'cor' => $cor, 'parent_id' => $parentId]
            );
        } else {
            $id = $this->db->insert(
                "INSERT INTO modulos_arquivos_pastas (nome, cor, professor_id, criado_por_tipo) VALUES (:nome, :cor, NULL, 'admin')",
                ['nome' => $nome, 'cor' => $cor]
            );
        }
        $this->json(['success' => true, 'id' => $id, 'nome' => $nome, 'cor' => $cor, 'parent_id' => $parentId]);
    }

    public function renameFolder()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 403);
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if (!$id || $nome === '') {
            $this->json(['error' => 'Dados inválidos'], 400);
            return;
        }
        $pasta = $this->db->fetch(
            "SELECT id FROM modulos_arquivos_pastas WHERE id = :id AND criado_por_tipo = 'admin'",
            ['id' => $id]
        );
        if (!$pasta) {
            $this->json(['error' => 'Pasta não encontrada'], 404);
            return;
        }
        $this->db->query("UPDATE modulos_arquivos_pastas SET nome = :nome WHERE id = :id", ['nome' => $nome, 'id' => $id]);
        $this->json(['success' => true]);
    }

    public function deleteFolder()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 403);
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        $pasta = $this->db->fetch(
            "SELECT id FROM modulos_arquivos_pastas WHERE id = :id AND criado_por_tipo = 'admin'",
            ['id' => $id]
        );
        if (!$pasta) {
            $this->json(['error' => 'Pasta não encontrada'], 404);
            return;
        }
        $this->db->query("UPDATE modulos_arquivos SET pasta_id = NULL WHERE pasta_id = :id", ['id' => $id]);
        $this->db->query("DELETE FROM modulos_arquivos_pastas WHERE id = :id", ['id' => $id]);
        $this->json(['success' => true]);
    }

    public function moveToFolder()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 403);
            return;
        }
        $arquivoId = (int)($_POST['arquivo_id'] ?? 0);
        $pastaId = isset($_POST['pasta_id']) && $_POST['pasta_id'] !== '' ? (int)$_POST['pasta_id'] : null;

        if (!$this->getItemById($arquivoId)) {
            $this->json(['error' => 'Arquivo não encontrado'], 404);
            return;
        }
        if ($pastaId !== null) {
            $pasta = $this->db->fetch(
                "SELECT id FROM modulos_arquivos_pastas WHERE id = :id AND criado_por_tipo = 'admin'",
                ['id' => $pastaId]
            );
            if (!$pasta) {
                $this->json(['error' => 'Pasta não encontrada'], 404);
                return;
            }
        }
        $this->db->query("UPDATE modulos_arquivos SET pasta_id = :pasta_id WHERE id = :id", ['pasta_id' => $pastaId, 'id' => $arquivoId]);
        $this->json(['success' => true]);
    }

    public function upload()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/arquivos/criar');
            return;
        }
        if (!isset($_FILES['arquivo']) || (int) ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->setFlashMessage('Selecione um arquivo válido para enviar.', 'error');
            $this->redirect('/admin/arquivos/criar');
            return;
        }

        $file = $_FILES['arquivo'];
        $originalName = trim((string) ($file['name'] ?? ''));
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = $this->sanitizeDescricao(trim((string) ($_POST['descricao'] ?? '')));
        $recuperacao = !empty($_POST['recuperacao']) ? 1 : 0;
        $materiaId = (int) ($_POST['materia_id'] ?? 0);
        $professorId = (int) ($_POST['professor_id'] ?? 0);
        $turmaIdsRaw = $_POST['turma_ids'] ?? [];
        if (!is_array($turmaIdsRaw)) {
            $turmaIdsRaw = [];
        }
        $turmaIds = array_values(array_unique(array_map('intval', array_filter($turmaIdsRaw))));

        if ($titulo === '') {
            $this->setFlashMessage('O título do arquivo é obrigatório.', 'error');
            $this->redirect('/admin/arquivos/criar');
            return;
        }
        if ($originalName === '' || $tmpPath === '' || !in_array($ext, self::EXTENSOES_PERMITIDAS, true)) {
            $this->setFlashMessage('Formato não permitido para arquivo.', 'error');
            $this->redirect('/admin/arquivos/criar');
            return;
        }
        if (empty($turmaIds)) {
            $this->setFlashMessage('Selecione pelo menos uma turma.', 'error');
            $this->redirect('/admin/arquivos/criar');
            return;
        }

        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $safeKey = 'arquivo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $mime = $this->detectMimeType($tmpPath, (string) ($file['type'] ?? ''));
        if (!$media->put('arquivos', $safeKey, $tmpPath, $mime)) {
            $this->setFlashMessage('Falha ao salvar arquivo no storage.', 'error');
            $this->redirect('/admin/arquivos/criar');
            return;
        }

        $pastaId = isset($_POST['pasta_id']) && (int)$_POST['pasta_id'] > 0 ? (int)$_POST['pasta_id'] : null;

        $moduloId = $this->db->insert(
            "INSERT INTO modulos_arquivos (turma_id, materia_id, professor_id, pasta_id, titulo, descricao, recuperacao)
             VALUES (:turma_id, :materia_id, :professor_id, :pasta_id, :titulo, :descricao, :recuperacao)",
            [
                'turma_id' => (int) $turmaIds[0],
                'materia_id' => $materiaId > 0 ? $materiaId : null,
                'professor_id' => $professorId > 0 ? $professorId : null,
                'pasta_id' => $pastaId,
                'titulo' => $titulo,
                'descricao' => $descricao,
                'recuperacao' => $recuperacao,
            ]
        );

        foreach ($turmaIds as $turmaId) {
            $this->db->insert(
                "INSERT INTO modulos_arquivos_turmas (modulo_arquivo_id, turma_id) VALUES (:modulo_arquivo_id, :turma_id)",
                ['modulo_arquivo_id' => $moduloId, 'turma_id' => (int) $turmaId]
            );
        }

        $this->db->insert(
            "INSERT INTO modulos_arquivos_anexos
                (modulo_arquivo_id, caminho, nome_original, extensao, tamanho, ordem)
             VALUES
                (:modulo_arquivo_id, :caminho, :nome_original, :extensao, :tamanho, :ordem)",
            [
                'modulo_arquivo_id' => $moduloId,
                'caminho' => 'arquivos/' . $safeKey,
                'nome_original' => $originalName,
                'extensao' => $ext,
                'tamanho' => $size,
                'ordem' => 1,
            ]
        );

        $this->setFlashMessage('Arquivo enviado com sucesso.', 'success');
        $this->redirect('/admin/arquivos');
    }

    public function update()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/arquivos');
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = $this->sanitizeDescricao(trim((string) ($_POST['descricao'] ?? '')));
        $recuperacao = !empty($_POST['recuperacao']) ? 1 : 0;
        $materiaId = (int) ($_POST['materia_id'] ?? 0);
        $professorId = (int) ($_POST['professor_id'] ?? 0);
        $turmaIdsRaw = $_POST['turma_ids'] ?? [];
        if (!is_array($turmaIdsRaw)) {
            $turmaIdsRaw = [];
        }
        $turmaIds = array_values(array_unique(array_map('intval', array_filter($turmaIdsRaw))));

        if ($id <= 0 || $titulo === '' || empty($turmaIds)) {
            $this->setFlashMessage('Dados inválidos para atualização.', 'error');
            $this->redirect('/admin/arquivos/editar?id=' . max(1, $id));
            return;
        }

        if (!$this->getItemById($id)) {
            $this->setFlashMessage('Arquivo não encontrado.', 'error');
            $this->redirect('/admin/arquivos');
            return;
        }

        $this->db->query(
            "UPDATE modulos_arquivos
             SET titulo = :titulo,
                 descricao = :descricao,
                 materia_id = :materia_id,
                 professor_id = :professor_id,
                 turma_id = :turma_id,
                 recuperacao = :recuperacao
             WHERE id = :id",
            [
                'id' => $id,
                'titulo' => $titulo,
                'descricao' => $descricao,
                'materia_id' => $materiaId > 0 ? $materiaId : null,
                'professor_id' => $professorId > 0 ? $professorId : null,
                'turma_id' => (int) $turmaIds[0],
                'recuperacao' => $recuperacao,
            ]
        );
        $this->db->query("DELETE FROM modulos_arquivos_turmas WHERE modulo_arquivo_id = :id", ['id' => $id]);
        foreach ($turmaIds as $turmaId) {
            $this->db->insert(
                "INSERT INTO modulos_arquivos_turmas (modulo_arquivo_id, turma_id) VALUES (:modulo_arquivo_id, :turma_id)",
                ['modulo_arquivo_id' => $id, 'turma_id' => (int) $turmaId]
            );
        }

        $this->setFlashMessage('Arquivo atualizado com sucesso.', 'success');
        $this->redirect('/admin/arquivos');
    }

    public function delete()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/arquivos');
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlashMessage('Arquivo inválido.', 'error');
            $this->redirect('/admin/arquivos');
            return;
        }

        $anexos = $this->db->fetchAll("SELECT caminho FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = :id", ['id' => $id]);
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        foreach ($anexos as $anexo) {
            $key = $this->anexoPathToMediaKey((string) ($anexo['caminho'] ?? ''));
            if ($key !== '') {
                $media->delete('arquivos', $key);
            }
        }

        $this->db->query("DELETE FROM modulos_arquivos WHERE id = :id", ['id' => $id]);
        $this->setFlashMessage('Arquivo removido com sucesso.', 'success');
        $this->redirect('/admin/arquivos');
    }

    /**
     * Download de anexo (admin/coordenador).
     */
    public function baixarAnexo($id = null)
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }
        if (preg_match('#/baixar/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int) $m[1];
        }
        $id = (int) ($id ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            exit;
        }
        $anexo = $this->db->fetch(
            "SELECT aa.*, aa.modulo_arquivo_id FROM modulos_arquivos_anexos aa WHERE aa.id = :id",
            ['id' => $id]
        );
        if (!$anexo) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            exit;
        }
        $this->streamAnexoDownload($anexo);
        exit;
    }

    private function getAnexoMimeType(array $anexo): string
    {
        $mimes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
            'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain', 'csv' => 'text/csv',
        ];
        $ext = strtolower((string) ($anexo['extensao'] ?? ''));
        return $mimes[$ext] ?? 'application/octet-stream';
    }

    private function streamSignedUrlDownload(string $signedUrl, string $contentType, string $filename): bool
    {
        if ($signedUrl === '') {
            return false;
        }
        if (!headers_sent()) {
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $filename) . '"');
            header('Cache-Control: private, max-age=3600');
        }
        $fp = @fopen($signedUrl, 'rb');
        if ($fp !== false) {
            while (!feof($fp)) {
                echo fread($fp, 8192);
            }
            fclose($fp);
            return true;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($signedUrl);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 120);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) {
                    echo $chunk;
                    return strlen($chunk);
                });
                $ok = curl_exec($ch);
                curl_close($ch);
                return $ok !== false;
            }
        }
        return false;
    }

    private function streamAnexoDownload(array $anexo): void
    {
        $contentType = $this->getAnexoMimeType($anexo);
        $filename = (string) ($anexo['nome_original'] ?? 'arquivo');
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $mediaKey = $this->anexoPathToMediaKey((string) ($anexo['caminho'] ?? ''));
        if ($media->isS3() && $mediaKey !== '') {
            $signedUrl = $media->getViewUrl('arquivos', $mediaKey, $filename, 900);
            if (!empty($signedUrl) && $this->streamSignedUrlDownload($signedUrl, $contentType, $filename)) {
                return;
            }
        }
        $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../');
        $fullPath = $basePath . ltrim((string) ($anexo['caminho'] ?? ''), '/');
        if ((!file_exists($fullPath) || !is_readable($fullPath)) && $mediaKey !== '') {
            $fullPath = $basePath . 'public/uploads/arquivos/' . $mediaKey;
        }
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $filename) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=3600');
        readfile($fullPath);
    }
}
}

