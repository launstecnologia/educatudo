<?php
/**
 * EducaTudo - Gestão de Apostilas (Admin)
 */

if (!class_exists('AdminApostilaController')) {
class AdminApostilaController extends BaseController
{
    private $auth;
    private $db;

    private const MODULE_KEY_ALUNO = 'module_aluno_apostilas';
    private const MODULE_KEY_PROFESSOR = 'module_professor_apostilas';
    private const EXTENSOES_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'];

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

    private function canManageApostilas(): bool
    {
        $user = $this->auth->getUser();
        $perfil = trim((string)($user['perfil_admin'] ?? ''));

        // Compatível com contas antigas sem perfil_admin definido.
        if ($perfil === '') {
            return true;
        }

        return in_array($perfil, ['diretor', 'coordenador', 'dev'], true);
    }

    private function ensureCanManageOrRedirect(): bool
    {
        if ($this->canManageApostilas()) {
            return true;
        }

        $this->setFlashMessage('Apenas diretor ou coordenador podem gerenciar apostilas.', 'error');
        $this->redirect('/admin/dashboard');
        return false;
    }

    private function upsertConfigLayout(string $key, string $value): void
    {
        $exists = $this->db->fetch(
            "SELECT config_key FROM config_layout WHERE config_key = :config_key LIMIT 1",
            ['config_key' => $key]
        );

        if ($exists) {
            $this->db->query(
                "UPDATE config_layout SET config_value = :config_value WHERE config_key = :config_key",
                ['config_key' => $key, 'config_value' => $value]
            );
            return;
        }

        $this->db->insert(
            "INSERT INTO config_layout (config_key, config_value) VALUES (:config_key, :config_value)",
            ['config_key' => $key, 'config_value' => $value]
        );
    }

    private function ensureModuleConfigDefaults(): void
    {
        $moduleAlunoExists = $this->db->fetch(
            "SELECT config_key FROM config_layout WHERE config_key = :config_key LIMIT 1",
            ['config_key' => self::MODULE_KEY_ALUNO]
        );
        if (!$moduleAlunoExists) {
            $this->upsertConfigLayout(self::MODULE_KEY_ALUNO, '1');
        }

        $moduleProfessorExists = $this->db->fetch(
            "SELECT config_key FROM config_layout WHERE config_key = :config_key LIMIT 1",
            ['config_key' => self::MODULE_KEY_PROFESSOR]
        );
        if (!$moduleProfessorExists) {
            $this->upsertConfigLayout(self::MODULE_KEY_PROFESSOR, '1');
        }
    }

    private function anexoPathToMediaKey(string $caminho): string
    {
        $path = ltrim(str_replace('\\', '/', $caminho), '/');
        if (strpos($path, 'public/uploads/apostilas/') === 0) {
            return substr($path, strlen('public/uploads/apostilas/'));
        }
        if (strpos($path, 'apostilas/') === 0) {
            return substr($path, strlen('apostilas/'));
        }
        return basename($path);
    }

    private function getTurmasAtivas(): array
    {
        return $this->db->fetchAll(
            "SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC"
        );
    }

    private function ensureMetadataColumns(): void
    {
        $checks = [
            ['column' => 'curso_id', 'sql' => "ALTER TABLE modulos_apostilas ADD COLUMN curso_id INT NULL AFTER turma_id"],
            ['column' => 'materia_id', 'sql' => "ALTER TABLE modulos_apostilas ADD COLUMN materia_id INT NULL AFTER curso_id"],
            ['column' => 'professor_id', 'sql' => "ALTER TABLE modulos_apostilas ADD COLUMN professor_id INT NULL AFTER materia_id"],
        ];

        foreach ($checks as $check) {
            try {
                $exists = $this->db->fetch(
                    "SELECT COLUMN_NAME
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'modulos_apostilas'
                       AND COLUMN_NAME = :column
                     LIMIT 1",
                    ['column' => $check['column']]
                );
                if (!$exists) {
                    $this->db->query($check['sql']);
                }
            } catch (\Throwable $e) {
                // Compatibilidade: não interrompe em ambiente sem DDL.
            }
        }
    }

    private function getCursosAtivos(): array
    {
        return $this->db->fetchAll("SELECT id, nome FROM cursos WHERE ativo = 1 ORDER BY nome ASC");
    }

    private function getMateriasAtivas(): array
    {
        return $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome ASC");
    }

    private function getProfessoresAtivos(): array
    {
        return $this->db->fetchAll("SELECT id, nome, email FROM professores WHERE ativo = 1 ORDER BY nome ASC");
    }

    private function readItems(): array
    {
        return $this->db->fetchAll(
            "SELECT ma.id, ma.titulo, ma.descricao, ma.visibilidade, ma.created_at,
                    ma.curso_id, ma.materia_id, ma.professor_id,
                    aa.caminho, aa.nome_original, aa.extensao, aa.mime_type, aa.tamanho,
                    c.nome AS curso_nome, m.nome AS materia_nome, p.nome AS professor_nome,
                    (SELECT GROUP_CONCAT(mat.turma_id ORDER BY mat.turma_id SEPARATOR ',')
                       FROM modulos_apostilas_turmas mat
                      WHERE mat.modulo_apostila_id = ma.id) AS turma_ids_csv,
                    COALESCE(
                      (SELECT GROUP_CONCAT(t.nome ORDER BY t.nome SEPARATOR ', ')
                         FROM modulos_apostilas_turmas mat
                         JOIN turmas t ON t.id = mat.turma_id
                        WHERE mat.modulo_apostila_id = ma.id),
                      tpadrao.nome
                    ) AS turmas_nomes
             FROM modulos_apostilas ma
             INNER JOIN modulos_apostilas_anexos aa
                     ON aa.modulo_apostila_id = ma.id
             LEFT JOIN cursos c ON c.id = ma.curso_id
             LEFT JOIN materias m ON m.id = ma.materia_id
             LEFT JOIN professores p ON p.id = ma.professor_id
             LEFT JOIN turmas tpadrao ON tpadrao.id = ma.turma_id
             ORDER BY ma.created_at DESC, ma.id DESC"
        );
    }

    private function detectMimeType(string $tmpPath, string $fallback = ''): string
    {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($tmpPath);
            if (!empty($mime)) {
                return (string)$mime;
            }
        }

        if (class_exists('finfo')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $tmpPath);
                @finfo_close($finfo);
                if (!empty($mime)) {
                    return (string)$mime;
                }
            }
        }

        return $fallback !== '' ? $fallback : 'application/octet-stream';
    }

    public function index()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }

        $this->ensureModuleConfigDefaults();
        $this->ensureMetadataColumns();
        $items = $this->readItems();

        $this->viewWithLayout('admin', 'admin/apostilas/index', [
            'title' => 'Apostilas - Admin',
            'page_title' => 'Apostilas da Escola',
            'user' => $this->auth->getUser(),
            'items' => $items,
            'turmas' => $this->getTurmasAtivas(),
            'cursos' => $this->getCursosAtivos(),
            'materias' => $this->getMateriasAtivas(),
            'professores' => $this->getProfessoresAtivos(),
            'current_page' => 'apostilas',
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function upload()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        if (!isset($_FILES['arquivo']) || (int)($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->setFlashMessage('Selecione um arquivo válido para enviar.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        $file = $_FILES['arquivo'];
        $originalName = trim((string)($file['name'] ?? ''));
        $tmpPath = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $visibilidade = trim((string)($_POST['visibilidade'] ?? 'ambos'));
        $cursoId = (int)($_POST['curso_id'] ?? 0);
        $materiaId = (int)($_POST['materia_id'] ?? 0);
        $professorId = (int)($_POST['professor_id'] ?? 0);
        $turmaIdsRaw = $_POST['turma_ids'] ?? [];
        if (!is_array($turmaIdsRaw)) {
            $turmaIdsRaw = [];
        }
        $turmaIds = array_values(array_unique(array_map('intval', array_filter($turmaIdsRaw))));

        if (!in_array($visibilidade, ['aluno', 'professor', 'ambos'], true)) {
            $visibilidade = 'ambos';
        }

        if ($originalName === '' || $tmpPath === '' || !in_array($ext, self::EXTENSOES_PERMITIDAS, true)) {
            $this->setFlashMessage('Formato não permitido. Use PDF ou imagem (JPG, PNG, WEBP, GIF).', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        if (empty($turmaIds)) {
            $this->setFlashMessage('Selecione pelo menos uma turma para disponibilizar a apostila.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        if ($cursoId > 0) {
            $curso = $this->db->fetch("SELECT id FROM cursos WHERE id = :id LIMIT 1", ['id' => $cursoId]);
            if (!$curso) {
                $this->setFlashMessage('Curso selecionado é inválido.', 'error');
                $this->redirect('/admin/apostilas');
                return;
            }
        }
        if ($materiaId > 0) {
            $materia = $this->db->fetch("SELECT id FROM materias WHERE id = :id LIMIT 1", ['id' => $materiaId]);
            if (!$materia) {
                $this->setFlashMessage('Matéria selecionada é inválida.', 'error');
                $this->redirect('/admin/apostilas');
                return;
            }
        }
        if ($professorId > 0) {
            $professor = $this->db->fetch("SELECT id FROM professores WHERE id = :id AND ativo = 1 LIMIT 1", ['id' => $professorId]);
            if (!$professor) {
                $this->setFlashMessage('Professor selecionado é inválido.', 'error');
                $this->redirect('/admin/apostilas');
                return;
            }
        }

        $turmasValidas = $this->db->fetchAll(
            "SELECT id FROM turmas WHERE ativo = 1"
        );
        $turmasValidasMap = [];
        foreach ($turmasValidas as $turmaValida) {
            $turmasValidasMap[(int)$turmaValida['id']] = true;
        }
        foreach ($turmaIds as $turmaId) {
            if (!isset($turmasValidasMap[$turmaId])) {
                $this->setFlashMessage('Uma das turmas selecionadas é inválida.', 'error');
                $this->redirect('/admin/apostilas');
                return;
            }
        }

        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $safeKey = 'apostila_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $mime = $this->detectMimeType($tmpPath, (string)($file['type'] ?? ''));

        if (!$media->put('apostilas', $safeKey, $tmpPath, $mime)) {
            $this->setFlashMessage('Falha ao salvar a apostila no storage.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        $tituloFinal = $titulo !== '' ? $titulo : $originalName;
        $moduloId = $this->db->insert(
            "INSERT INTO modulos_apostilas (turma_id, curso_id, materia_id, professor_id, titulo, descricao, visibilidade, created_by)
             VALUES (:turma_id, :curso_id, :materia_id, :professor_id, :titulo, :descricao, :visibilidade, :created_by)",
            [
                'turma_id' => (int)$turmaIds[0],
                'curso_id' => $cursoId > 0 ? $cursoId : null,
                'materia_id' => $materiaId > 0 ? $materiaId : null,
                'professor_id' => $professorId > 0 ? $professorId : null,
                'titulo' => $tituloFinal,
                'descricao' => $descricao,
                'visibilidade' => $visibilidade,
                'created_by' => (int)($this->auth->getUser()['id'] ?? 0),
            ]
        );

        foreach ($turmaIds as $turmaId) {
            $this->db->insert(
                "INSERT INTO modulos_apostilas_turmas (modulo_apostila_id, turma_id)
                 VALUES (:modulo_apostila_id, :turma_id)",
                ['modulo_apostila_id' => $moduloId, 'turma_id' => $turmaId]
            );
        }

        $this->db->insert(
            "INSERT INTO modulos_apostilas_anexos
                (modulo_apostila_id, caminho, nome_original, extensao, mime_type, tamanho, ordem)
             VALUES
                (:modulo_apostila_id, :caminho, :nome_original, :extensao, :mime_type, :tamanho, :ordem)",
            [
                'modulo_apostila_id' => $moduloId,
                'caminho' => 'apostilas/' . $safeKey,
                'nome_original' => $originalName,
                'extensao' => $ext,
                'mime_type' => $mime,
                'tamanho' => $size,
                'ordem' => 1,
            ]
        );

        $this->ensureModuleConfigDefaults();

        $this->setFlashMessage('Apostila enviada com sucesso.', 'success');
        $this->redirect('/admin/apostilas');
    }

    public function update()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $visibilidade = trim((string)($_POST['visibilidade'] ?? 'ambos'));
        $cursoId = (int)($_POST['curso_id'] ?? 0);
        $materiaId = (int)($_POST['materia_id'] ?? 0);
        $professorId = (int)($_POST['professor_id'] ?? 0);
        $turmaIdsRaw = $_POST['turma_ids'] ?? [];
        if (!is_array($turmaIdsRaw)) {
            $turmaIdsRaw = [];
        }
        $turmaIds = array_values(array_unique(array_map('intval', array_filter($turmaIdsRaw))));

        if ($id <= 0) {
            $this->setFlashMessage('Apostila inválida para edição.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        if ($titulo === '') {
            $this->setFlashMessage('O título da apostila é obrigatório.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        if (!in_array($visibilidade, ['aluno', 'professor', 'ambos'], true)) {
            $visibilidade = 'ambos';
        }

        if (empty($turmaIds)) {
            $this->setFlashMessage('Selecione pelo menos uma turma para disponibilizar a apostila.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        if ($cursoId > 0) {
            $curso = $this->db->fetch("SELECT id FROM cursos WHERE id = :id LIMIT 1", ['id' => $cursoId]);
            if (!$curso) {
                $this->setFlashMessage('Curso selecionado é inválido.', 'error');
                $this->redirect('/admin/apostilas');
                return;
            }
        }
        if ($materiaId > 0) {
            $materia = $this->db->fetch("SELECT id FROM materias WHERE id = :id LIMIT 1", ['id' => $materiaId]);
            if (!$materia) {
                $this->setFlashMessage('Matéria selecionada é inválida.', 'error');
                $this->redirect('/admin/apostilas');
                return;
            }
        }
        if ($professorId > 0) {
            $professor = $this->db->fetch("SELECT id FROM professores WHERE id = :id AND ativo = 1 LIMIT 1", ['id' => $professorId]);
            if (!$professor) {
                $this->setFlashMessage('Professor selecionado é inválido.', 'error');
                $this->redirect('/admin/apostilas');
                return;
            }
        }

        $target = $this->db->fetch(
            "SELECT id FROM modulos_apostilas WHERE id = :id LIMIT 1",
            ['id' => $id]
        );
        if (!$target) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        $turmasValidas = $this->db->fetchAll(
            "SELECT id FROM turmas WHERE ativo = 1"
        );
        $turmasValidasMap = [];
        foreach ($turmasValidas as $turmaValida) {
            $turmasValidasMap[(int)$turmaValida['id']] = true;
        }
        foreach ($turmaIds as $turmaId) {
            if (!isset($turmasValidasMap[$turmaId])) {
                $this->setFlashMessage('Uma das turmas selecionadas é inválida.', 'error');
                $this->redirect('/admin/apostilas');
                return;
            }
        }

        $this->db->query(
            "UPDATE modulos_apostilas
             SET titulo = :titulo,
                 descricao = :descricao,
                 visibilidade = :visibilidade,
                 curso_id = :curso_id,
                 materia_id = :materia_id,
                 professor_id = :professor_id,
                 turma_id = :turma_id
             WHERE id = :id",
            [
                'id' => $id,
                'titulo' => $titulo,
                'descricao' => $descricao,
                'visibilidade' => $visibilidade,
                'curso_id' => $cursoId > 0 ? $cursoId : null,
                'materia_id' => $materiaId > 0 ? $materiaId : null,
                'professor_id' => $professorId > 0 ? $professorId : null,
                'turma_id' => (int)$turmaIds[0],
            ]
        );

        $this->db->query(
            "DELETE FROM modulos_apostilas_turmas WHERE modulo_apostila_id = :id",
            ['id' => $id]
        );
        foreach ($turmaIds as $turmaId) {
            $this->db->insert(
                "INSERT INTO modulos_apostilas_turmas (modulo_apostila_id, turma_id)
                 VALUES (:modulo_apostila_id, :turma_id)",
                ['modulo_apostila_id' => $id, 'turma_id' => $turmaId]
            );
        }

        $this->setFlashMessage('Apostila atualizada com sucesso.', 'success');
        $this->redirect('/admin/apostilas');
    }

    public function delete()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        $target = $this->db->fetch(
            "SELECT ma.id, aa.caminho
             FROM modulos_apostilas ma
             LEFT JOIN modulos_apostilas_anexos aa ON aa.modulo_apostila_id = ma.id
             WHERE ma.id = :id
             LIMIT 1",
            ['id' => $id]
        );

        if ($target === null) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/admin/apostilas');
            return;
        }

        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        if (!empty($target['caminho'])) {
            $mediaKey = $this->anexoPathToMediaKey((string)$target['caminho']);
            if ($mediaKey !== '') {
                $media->delete('apostilas', $mediaKey);
            }
        }

        $this->db->query("DELETE FROM modulos_apostilas WHERE id = :id", ['id' => $id]);
        $this->setFlashMessage('Apostila removida com sucesso.', 'success');
        $this->redirect('/admin/apostilas');
    }
}
}
