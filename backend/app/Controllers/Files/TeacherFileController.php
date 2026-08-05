<?php
/**
 * EducaTudo - Módulo de Arquivos (Professor)
 * Professor disponibiliza arquivos para turma/disciplina. Aluno pode baixar os anexos.
 */

if (!class_exists('TeacherFileController')) {
class TeacherFileController extends BaseController
{
    private $auth;
    private $db;

    private static $EXTENSOES_PERMITIDAS = [
        'pdf',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'mp4', 'webm', 'ogg',
        'mp3', 'wav',
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'txt', 'csv',
    ];

    private static $UPLOAD_DIR = 'public/uploads/arquivos/';

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
        if ($user && !in_array($user['tipo'], ['professor', 'admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
        if ($user && $user['tipo'] === 'professor') {
            require_once __DIR__ . '/../../Core/LayoutHelper.php';
            if (LayoutHelper::get('module_professor_arquivos', '1') !== '1') {
                $this->setFlashMessage('O módulo Arquivos está desabilitado para o professor.', 'error');
                $this->redirect('/professor/dashboard');
                exit;
            }
        }
    }

    private function getProfessor()
    {
        $user = $this->auth->getUser();
        $prof = $this->db->fetch("SELECT * FROM professores WHERE id = :id", ['id' => $user['id']]);
        if (!$prof) {
            $this->setFlashMessage('Professor não encontrado', 'error');
            $this->redirect('/professor/dashboard');
            exit;
        }
        return $prof;
    }

    private function getTurmasProfessor($professor)
    {
        $turmasIds = json_decode($professor['turmas'] ?? '[]', true);
        if (empty($turmasIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($turmasIds), '?'));
        return $this->db->fetchAll("SELECT * FROM turmas WHERE id IN ($placeholders) AND ativo = 1 ORDER BY nome", $turmasIds);
    }

    private function getMateriasProfessor($professor)
    {
        $nomes = json_decode($professor['materias'] ?? '[]', true);
        if (empty($nomes)) {
            return $this->db->fetchAll("SELECT * FROM materias ORDER BY nome");
        }
        $placeholders = implode(',', array_fill(0, count($nomes), '?'));
        return $this->db->fetchAll("SELECT * FROM materias WHERE nome IN ($placeholders) ORDER BY nome", $nomes);
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

    private function detectMimeType(string $tmpPath): ?string
    {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($tmpPath);
            if (!empty($mime)) {
                return $mime;
            }
        }

        if (class_exists('finfo')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $tmpPath);
                @finfo_close($finfo);
                if (!empty($mime)) {
                    return $mime;
                }
            }
        }

        return null;
    }

    public function index()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $professor = $this->getProfessor();
        $pastaAtualId = isset($_GET['pasta_id']) && $_GET['pasta_id'] !== '' ? (int)$_GET['pasta_id'] : null;

        // Pastas do professor
        $pastas = $this->db->fetchAll(
            "SELECT p.*, (SELECT COUNT(*) FROM modulos_arquivos ma WHERE ma.pasta_id = p.id AND ma.professor_id = :prof_id2) AS total_arquivos
             FROM modulos_arquivos_pastas p
             WHERE p.professor_id = :prof_id AND p.criado_por_tipo = 'professor'
             ORDER BY p.ordem ASC, p.nome ASC",
            ['prof_id' => $professor['id'], 'prof_id2' => $professor['id']]
        );

        $pastaAtual = null;
        if ($pastaAtualId !== null) {
            foreach ($pastas as $p) {
                if ((int)$p['id'] === $pastaAtualId) {
                    $pastaAtual = $p;
                    break;
                }
            }
        }

        $whereExtra = $pastaAtualId !== null
            ? ' AND ma.pasta_id = ' . (int)$pastaAtualId
            : '';

        $lista = $this->db->fetchAll(
            "SELECT ma.*,
             COALESCE(
               (SELECT GROUP_CONCAT(t2.nome ORDER BY t2.nome) FROM modulos_arquivos_turmas mat JOIN turmas t2 ON mat.turma_id = t2.id WHERE mat.modulo_arquivo_id = ma.id),
               t.nome
             ) as turma_nome,
             m.nome as materia_nome,
             (SELECT COUNT(*) FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = ma.id) as total_anexos,
             (SELECT nome FROM alunos WHERE id = ma.aluno_id LIMIT 1) as aluno_nome
             FROM modulos_arquivos ma
             LEFT JOIN turmas t ON ma.turma_id = t.id
             LEFT JOIN materias m ON ma.materia_id = m.id
             WHERE ma.professor_id = :prof_id{$whereExtra}
             ORDER BY ma.created_at DESC",
            ['prof_id' => $professor['id']]
        );

        $data = [
            'title' => 'Módulo de Arquivos - EducaTudo',
            'user' => $this->auth->getUser(),
            'lista' => $lista,
            'pastas' => $pastas,
            'pasta_atual' => $pastaAtual,
            'pasta_atual_id' => $pastaAtualId,
            'current_page' => 'arquivos',
            'csrf_token' => $this->generateCsrfToken()
        ];
        $this->viewWithLayout('professor', 'teacher/arquivos/index', $data);
    }

    public function createFolder()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 403);
            return;
        }
        $professor = $this->getProfessor();
        $nome = trim($_POST['nome'] ?? '');
        $cor = trim($_POST['cor'] ?? '#6366f1');
        if ($nome === '') {
            $this->json(['error' => 'Nome obrigatório'], 400);
            return;
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
            $cor = '#6366f1';
        }
        $id = $this->db->insert(
            "INSERT INTO modulos_arquivos_pastas (nome, cor, professor_id, criado_por_tipo) VALUES (:nome, :cor, :prof_id, 'professor')",
            ['nome' => $nome, 'cor' => $cor, 'prof_id' => $professor['id']]
        );
        $this->json(['success' => true, 'id' => $id, 'nome' => $nome, 'cor' => $cor]);
    }

    public function renameFolder()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 403);
            return;
        }
        $professor = $this->getProfessor();
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if (!$id || $nome === '') {
            $this->json(['error' => 'Dados inválidos'], 400);
            return;
        }
        $pasta = $this->db->fetch(
            "SELECT id FROM modulos_arquivos_pastas WHERE id = :id AND professor_id = :prof_id",
            ['id' => $id, 'prof_id' => $professor['id']]
        );
        if (!$pasta) {
            $this->json(['error' => 'Pasta não encontrada'], 404);
            return;
        }
        $this->db->query(
            "UPDATE modulos_arquivos_pastas SET nome = :nome WHERE id = :id",
            ['nome' => $nome, 'id' => $id]
        );
        $this->json(['success' => true]);
    }

    public function deleteFolder()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 403);
            return;
        }
        $professor = $this->getProfessor();
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        $pasta = $this->db->fetch(
            "SELECT id FROM modulos_arquivos_pastas WHERE id = :id AND professor_id = :prof_id",
            ['id' => $id, 'prof_id' => $professor['id']]
        );
        if (!$pasta) {
            $this->json(['error' => 'Pasta não encontrada'], 404);
            return;
        }
        // Remove vínculo dos arquivos antes de deletar a pasta
        $this->db->query("UPDATE modulos_arquivos SET pasta_id = NULL WHERE pasta_id = :id AND professor_id = :prof_id", ['id' => $id, 'prof_id' => $professor['id']]);
        $this->db->query("DELETE FROM modulos_arquivos_pastas WHERE id = :id", ['id' => $id]);
        $this->json(['success' => true]);
    }

    public function moveToFolder()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 403);
            return;
        }
        $professor = $this->getProfessor();
        $arquivoId = (int)($_POST['arquivo_id'] ?? 0);
        $pastaId = isset($_POST['pasta_id']) && $_POST['pasta_id'] !== '' ? (int)$_POST['pasta_id'] : null;

        $arquivo = $this->db->fetch(
            "SELECT id FROM modulos_arquivos WHERE id = :id AND professor_id = :prof_id",
            ['id' => $arquivoId, 'prof_id' => $professor['id']]
        );
        if (!$arquivo) {
            $this->json(['error' => 'Arquivo não encontrado'], 404);
            return;
        }
        if ($pastaId !== null) {
            $pasta = $this->db->fetch(
                "SELECT id FROM modulos_arquivos_pastas WHERE id = :id AND professor_id = :prof_id",
                ['id' => $pastaId, 'prof_id' => $professor['id']]
            );
            if (!$pasta) {
                $this->json(['error' => 'Pasta não encontrada'], 404);
                return;
            }
        }
        $this->db->query(
            "UPDATE modulos_arquivos SET pasta_id = :pasta_id WHERE id = :id",
            ['pasta_id' => $pastaId, 'id' => $arquivoId]
        );
        $this->json(['success' => true]);
    }

    public function create()
    {
        $professor = $this->getProfessor();
        $turmas = $this->getTurmasProfessor($professor);
        $materias = $this->getMateriasProfessor($professor);
        $data = [
            'title' => 'Novo arquivo - EducaTudo',
            'user' => $this->auth->getUser(),
            'turmas' => $turmas,
            'materias' => $materias,
            'current_page' => 'arquivos',
            'csrf_token' => $this->generateCsrfToken()
        ];
        $this->viewWithLayout('professor', 'teacher/arquivos/create', $data);
    }

    /**
     * Retorna alunos de uma turma (JSON) para o select "enviar para aluno específico".
     */
    public function alunosPorTurma()
    {
        $professor = $this->getProfessor();
        $turmaId = (int)($_GET['turma_id'] ?? 0);
        if (!$turmaId) {
            $this->json(['alunos' => []]);
            return;
        }
        $turmasPermitidas = json_decode($professor['turmas'] ?? '[]', true) ?: [];
        if (!in_array($turmaId, $turmasPermitidas)) {
            $this->json(['alunos' => []]);
            return;
        }
        $alunos = $this->db->fetchAll(
            "SELECT id, nome FROM alunos WHERE turma_id = :tid AND ativo = 1 ORDER BY nome",
            ['tid' => $turmaId]
        );
        $this->json(['alunos' => $alunos]);
    }

    public function store()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido', 'error');
            $this->redirect('/professor/arquivos');
            return;
        }
        $professor = $this->getProfessor();
        $turmasPermitidas = json_decode($professor['turmas'] ?? '[]', true) ?: [];
        $aluno_id = isset($_POST['aluno_id']) && (int)$_POST['aluno_id'] > 0 ? (int)$_POST['aluno_id'] : null;

        if ($aluno_id) {
            $aluno = $this->db->fetch("SELECT id, turma_id FROM alunos WHERE id = :id AND ativo = 1", ['id' => $aluno_id]);
            if (!$aluno || !in_array((int)$aluno['turma_id'], $turmasPermitidas)) {
                $this->setFlashMessage('Aluno não encontrado ou não pertence às suas turmas.', 'error');
                $this->redirect('/professor/arquivos/criar');
                return;
            }
            $turma_ids = [(int)$aluno['turma_id']];
        } else {
            $turmaIdsRaw = $_POST['turma_ids'] ?? [];
            if (!is_array($turmaIdsRaw)) {
                $turmaIdsRaw = [];
            }
            $turma_ids = array_values(array_unique(array_map('intval', array_filter($turmaIdsRaw))));
            if (isset($_POST['turma_id']) && (int)$_POST['turma_id'] > 0 && empty($turma_ids)) {
                $turma_ids = [(int)$_POST['turma_id']];
            }
            if (empty($turma_ids)) {
                $this->setFlashMessage('Selecione ao menos uma turma ou um aluno específico.', 'error');
                $this->redirect('/professor/arquivos/criar');
                return;
            }
            foreach ($turma_ids as $tid) {
                if (!in_array($tid, $turmasPermitidas)) {
                    $this->setFlashMessage('Uma das turmas selecionadas não é permitida.', 'error');
                    $this->redirect('/professor/arquivos/criar');
                    return;
                }
            }
        }

        $materia_id = (int)($_POST['materia_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $recuperacao = !empty($_POST['recuperacao']) ? 1 : 0;
        if (!$materia_id || $titulo === '') {
            $this->setFlashMessage('Preencha disciplina e título.', 'error');
            $this->redirect('/professor/arquivos/criar');
            return;
        }

        $pastaId = isset($_POST['pasta_id']) && (int)$_POST['pasta_id'] > 0 ? (int)$_POST['pasta_id'] : null;
        if ($pastaId !== null) {
            $pastaOk = $this->db->fetch(
                "SELECT id FROM modulos_arquivos_pastas WHERE id = :id AND professor_id = :prof_id",
                ['id' => $pastaId, 'prof_id' => $professor['id']]
            );
            if (!$pastaOk) {
                $pastaId = null;
            }
        }

        $primeiraTurma = $turma_ids[0];
        $moduloId = $this->db->insert(
            "INSERT INTO modulos_arquivos (turma_id, materia_id, professor_id, aluno_id, pasta_id, titulo, descricao, recuperacao) VALUES (:turma_id, :materia_id, :prof_id, :aluno_id, :pasta_id, :titulo, :descricao, :recuperacao)",
            [
                'turma_id' => $primeiraTurma,
                'materia_id' => $materia_id,
                'prof_id' => $professor['id'],
                'aluno_id' => $aluno_id,
                'pasta_id' => $pastaId,
                'titulo' => $titulo,
                'descricao' => $descricao,
                'recuperacao' => $recuperacao,
            ]
        );
        foreach ($turma_ids as $tid) {
            $this->db->insert(
                "INSERT INTO modulos_arquivos_turmas (modulo_arquivo_id, turma_id) VALUES (:ma_id, :turma_id)",
                ['ma_id' => $moduloId, 'turma_id' => $tid]
            );
        }
        $this->processarUploads($moduloId);
        $this->processarVideoUrls($moduloId);
        $this->setFlashMessage('Arquivo criado com sucesso.', 'success');
        $this->redirect('/professor/arquivos');
    }

    private function processarVideoUrls($moduloArquivoId)
    {
        $raw = trim((string) ($_POST['video_urls'] ?? ''));
        if ($raw === '') {
            return;
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $ordem = 0;
        foreach ($lines as $line) {
            $url = trim($line);
            if ($url === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            if (strlen($url) > 1024) {
                continue;
            }
            $ordem++;
            $this->db->insert(
                "INSERT INTO modulos_arquivos_videos (modulo_arquivo_id, url, titulo, ordem) VALUES (:ma_id, :url, NULL, :ordem)",
                ['ma_id' => $moduloArquivoId, 'url' => $url, 'ordem' => $ordem]
            );
        }
    }

    private function processarUploads($moduloArquivoId)
    {
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $ordem = 0;
        foreach (['anexos', 'anexo'] as $field) {
            if (!isset($_FILES[$field])) {
                continue;
            }
            $files = $_FILES[$field];
            $multiple = is_array($files['name']);
            $names = $multiple ? $files['name'] : [$files['name']];
            $tmpNames = $multiple ? $files['tmp_name'] : [$files['tmp_name']];
            $errors = $multiple ? $files['error'] : [$files['error']];
            $sizes = $multiple ? $files['size'] : [$files['size']];
            for ($i = 0; $i < count($names); $i++) {
                if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($names[$i])) {
                    continue;
                }
                $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
                if (!in_array($ext, self::$EXTENSOES_PERMITIDAS)) {
                    continue;
                }
                $nomeSalvo = 'arq_' . $moduloArquivoId . '_' . time() . '_' . $i . '.' . $ext;
                $caminhoRel = 'arquivos/' . $nomeSalvo;
                $contentType = $this->detectMimeType((string)$tmpNames[$i]);
                if ($media->put('arquivos', $nomeSalvo, $tmpNames[$i], $contentType)) {
                    $ordem++;
                    $this->db->insert(
                        "INSERT INTO modulos_arquivos_anexos (modulo_arquivo_id, caminho, nome_original, extensao, tamanho, ordem) VALUES (:ma_id, :caminho, :nome_original, :extensao, :tamanho, :ordem)",
                        [
                            'ma_id' => $moduloArquivoId,
                            'caminho' => $caminhoRel,
                            'nome_original' => $names[$i],
                            'extensao' => $ext,
                            'tamanho' => (int)($sizes[$i] ?? 0),
                            'ordem' => $ordem
                        ]
                    );
                } else {
                    error_log('TeacherFileController: falha ao salvar anexo em storage (arquivos/' . $nomeSalvo . ')');
                }
            }
        }
    }

    public function edit($id = null)
    {
        $id = (int)($id ?? $_GET['id'] ?? 0);
        if (!$id && preg_match('#/editar/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int)$m[1];
        }
        if (!$id) {
            $this->setFlashMessage('Registro não encontrado.', 'error');
            $this->redirect('/professor/arquivos');
            return;
        }
        $professor = $this->getProfessor();
        $item = $this->db->fetch("SELECT * FROM modulos_arquivos WHERE id = :id AND professor_id = :prof_id", ['id' => $id, 'prof_id' => $professor['id']]);
        if (!$item) {
            $this->setFlashMessage('Arquivo não encontrado.', 'error');
            $this->redirect('/professor/arquivos');
            return;
        }
        $anexos = $this->db->fetchAll("SELECT * FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = :id ORDER BY ordem", ['id' => $id]);
        $videos = [];
        try {
            $videos = $this->db->fetchAll("SELECT * FROM modulos_arquivos_videos WHERE modulo_arquivo_id = :id ORDER BY ordem", ['id' => $id]);
        } catch (\Throwable $e) {
        }
        $turmas = $this->getTurmasProfessor($professor);
        $materias = $this->getMateriasProfessor($professor);
        $itemTurmaIds = $this->db->fetchAll("SELECT turma_id FROM modulos_arquivos_turmas WHERE modulo_arquivo_id = :id", ['id' => $id]);
        $item_turma_ids = array_column($itemTurmaIds, 'turma_id');
        if (empty($item_turma_ids)) {
            $item_turma_ids = [(int)$item['turma_id']];
        }
        $aluno_atual = null;
        if (!empty($item['aluno_id'])) {
            $aluno_atual = $this->db->fetch("SELECT id, nome, turma_id FROM alunos WHERE id = :id", ['id' => $item['aluno_id']]);
        }
        $data = [
            'title' => 'Editar arquivo - EducaTudo',
            'user' => $this->auth->getUser(),
            'item' => $item,
            'aluno_atual' => $aluno_atual,
            'item_turma_ids' => $item_turma_ids,
            'anexos' => $anexos,
            'videos' => $videos,
            'turmas' => $turmas,
            'materias' => $materias,
            'current_page' => 'arquivos',
            'csrf_token' => $this->generateCsrfToken()
        ];
        $this->viewWithLayout('professor', 'teacher/arquivos/edit', $data);
    }

    public function update()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido', 'error');
            $this->redirect('/professor/arquivos');
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            $this->redirect('/professor/arquivos');
            return;
        }
        $professor = $this->getProfessor();
        $item = $this->db->fetch("SELECT * FROM modulos_arquivos WHERE id = :id AND professor_id = :prof_id", ['id' => $id, 'prof_id' => $professor['id']]);
        if (!$item) {
            $this->setFlashMessage('Arquivo não encontrado.', 'error');
            $this->redirect('/professor/arquivos');
            return;
        }
        $turmasPermitidas = json_decode($professor['turmas'] ?? '[]', true) ?: [];
        $aluno_id = isset($_POST['aluno_id']) && (int)$_POST['aluno_id'] > 0 ? (int)$_POST['aluno_id'] : null;

        if ($aluno_id) {
            $aluno = $this->db->fetch("SELECT id, turma_id FROM alunos WHERE id = :id AND ativo = 1", ['id' => $aluno_id]);
            if (!$aluno || !in_array((int)$aluno['turma_id'], $turmasPermitidas)) {
                $this->setFlashMessage('Aluno não encontrado ou não pertence às suas turmas.', 'error');
                $this->redirect('/professor/arquivos/editar/' . $id);
                return;
            }
            $turma_ids = [(int)$aluno['turma_id']];
        } else {
            $turmaIdsRaw = $_POST['turma_ids'] ?? [];
            if (!is_array($turmaIdsRaw)) {
                $turmaIdsRaw = [];
            }
            $turma_ids = array_values(array_unique(array_map('intval', array_filter($turmaIdsRaw))));
            if (isset($_POST['turma_id']) && (int)$_POST['turma_id'] > 0 && empty($turma_ids)) {
                $turma_ids = [(int)$_POST['turma_id']];
            }
            if (empty($turma_ids)) {
                $this->setFlashMessage('Selecione ao menos uma turma ou um aluno específico.', 'error');
                $this->redirect('/professor/arquivos/editar/' . $id);
                return;
            }
            foreach ($turma_ids as $tid) {
                if (!in_array($tid, $turmasPermitidas)) {
                    $this->setFlashMessage('Uma das turmas selecionadas não é permitida.', 'error');
                    $this->redirect('/professor/arquivos/editar/' . $id);
                    return;
                }
            }
        }

        $materia_id = (int)($_POST['materia_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $recuperacao = !empty($_POST['recuperacao']) ? 1 : 0;
        if (!$materia_id || $titulo === '') {
            $this->setFlashMessage('Preencha disciplina e título.', 'error');
            $this->redirect('/professor/arquivos/editar/' . $id);
            return;
        }
        $primeiraTurma = $turma_ids[0];
        $this->db->query(
            "UPDATE modulos_arquivos SET turma_id = :turma_id, materia_id = :materia_id, aluno_id = :aluno_id, titulo = :titulo, descricao = :descricao, recuperacao = :recuperacao WHERE id = :id",
            ['turma_id' => $primeiraTurma, 'materia_id' => $materia_id, 'aluno_id' => $aluno_id, 'titulo' => $titulo, 'descricao' => $descricao, 'recuperacao' => $recuperacao, 'id' => $id]
        );
        $this->db->query("DELETE FROM modulos_arquivos_turmas WHERE modulo_arquivo_id = :id", ['id' => $id]);
        foreach ($turma_ids as $tid) {
            $this->db->insert(
                "INSERT INTO modulos_arquivos_turmas (modulo_arquivo_id, turma_id) VALUES (:ma_id, :turma_id)",
                ['ma_id' => $id, 'turma_id' => $tid]
            );
        }
        $anexosManter = array_map('intval', array_filter($_POST['anexos_manter'] ?? [], 'strlen'));
        $anexosAtuais = $this->db->fetchAll("SELECT id, caminho FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = :id", ['id' => $id]);
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../');
        foreach ($anexosAtuais as $a) {
            if (!in_array((int)$a['id'], $anexosManter, true)) {
                $key = $this->anexoPathToMediaKey((string)$a['caminho']);
                if ($key !== '') {
                    $media->delete('arquivos', $key);
                }
                $full = $basePath . ltrim((string)$a['caminho'], '/');
                if (file_exists($full) && is_writable($full)) {
                    @unlink($full);
                }
                $this->db->query("DELETE FROM modulos_arquivos_anexos WHERE id = :aid", ['aid' => $a['id']]);
            }
        }
        $this->processarUploads($id);
        try {
            $this->db->query("DELETE FROM modulos_arquivos_videos WHERE modulo_arquivo_id = :id", ['id' => $id]);
            $this->processarVideoUrls($id);
        } catch (\Throwable $e) {
        }
        $this->setFlashMessage('Arquivo atualizado com sucesso.', 'success');
        $this->redirect('/professor/arquivos');
    }

    public function delete()
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if (preg_match('#/excluir/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int)$m[1];
        }
        if (!$id) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        $professor = $this->getProfessor();
        $item = $this->db->fetch("SELECT * FROM modulos_arquivos WHERE id = :id AND professor_id = :prof_id", ['id' => $id, 'prof_id' => $professor['id']]);
        if (!$item) {
            $this->json(['error' => 'Arquivo não encontrado'], 404);
            return;
        }
        $anexos = $this->db->fetchAll("SELECT * FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = :id", ['id' => $id]);
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../');
        foreach ($anexos as $a) {
            $key = $this->anexoPathToMediaKey((string)$a['caminho']);
            if ($key !== '') {
                $media->delete('arquivos', $key);
            }
            $full = $basePath . ltrim((string)$a['caminho'], '/');
            if (file_exists($full) && is_writable($full)) {
                @unlink($full);
            }
        }
        try {
            $this->db->query("DELETE FROM modulos_arquivos_videos WHERE modulo_arquivo_id = :id", ['id' => $id]);
        } catch (\Throwable $e) {
        }
        $this->db->query("DELETE FROM modulos_arquivos WHERE id = :id", ['id' => $id]);
        $this->setFlashMessage('Arquivo excluído.', 'success');
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->json(['success' => true]);
            return;
        }
        $this->redirect('/professor/arquivos');
    }

    /**
     * Preview: como o aluno vê a publicação (para exibir no modal do professor).
     */
    public function preview($id = null)
    {
        if (preg_match('#/preview/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int)$m[1];
        }
        $id = (int)($id ?? $_GET['id'] ?? 0);
        if (!$id) {
            echo 'Publicação não encontrada.';
            return;
        }
        $professor = $this->getProfessor();
        $pub = $this->db->fetch(
            "SELECT ma.*, m.nome as materia_nome, p.nome as professor_nome FROM modulos_arquivos ma LEFT JOIN materias m ON ma.materia_id = m.id LEFT JOIN professores p ON ma.professor_id = p.id WHERE ma.id = :id AND ma.professor_id = :prof_id",
            ['id' => $id, 'prof_id' => $professor['id']]
        );
        if (!$pub) {
            echo 'Publicação não encontrada.';
            return;
        }
        $anexos = $this->db->fetchAll("SELECT * FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = :id ORDER BY ordem", ['id' => $id]);
        $videos = [];
        try {
            $videos = $this->db->fetchAll("SELECT * FROM modulos_arquivos_videos WHERE modulo_arquivo_id = :id ORDER BY ordem", ['id' => $id]);
            foreach ($videos as &$v) {
                $v['embed_url'] = $this->urlParaEmbed((string)$v['url']);
            }
            unset($v);
        } catch (\Throwable $e) {
        }
        $data = [
            'pub' => $pub,
            'anexos' => $anexos,
            'videos' => $videos,
            'url_base' => rtrim(URL, '/'),
            'iframe' => !empty($_GET['iframe']),
        ];
        if (!empty($_GET['iframe'])) {
            $this->view('teacher/arquivos/preview', $data);
            return;
        }
        $this->viewWithLayout('professor', 'teacher/arquivos/preview', $data);
    }

    private function urlParaEmbed(string $url): string
    {
        $url = trim($url);
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([a-zA-Z0-9_-]+)#', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return $url;
    }

    /**
     * Stream do anexo para o professor (preview) — mesmo comportamento do aluno ao abrir.
     */
    public function verAnexo($id = null)
    {
        if (preg_match('#/ver-anexo/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int)$m[1];
        }
        $id = (int)($id ?? $_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            exit;
        }
        $professor = $this->getProfessor();
        $anexo = $this->db->fetch(
            "SELECT aa.*, aa.modulo_arquivo_id FROM modulos_arquivos_anexos aa WHERE aa.id = :id",
            ['id' => $id]
        );
        if (!$anexo) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            exit;
        }
        $podeVer = $this->db->fetch(
            "SELECT 1 FROM modulos_arquivos ma WHERE ma.id = :id AND ma.professor_id = :prof_id",
            ['id' => $anexo['modulo_arquivo_id'], 'prof_id' => $professor['id']]
        );
        if (!$podeVer) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            exit;
        }
        $this->streamAnexoInline($anexo);
        exit;
    }

    private function streamAnexoInline(array $anexo): void
    {
        $mimes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
            'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain', 'csv' => 'text/csv'
        ];
        $ext = strtolower((string)$anexo['extensao']);
        $contentType = $mimes[$ext] ?? 'application/octet-stream';
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $mediaKey = $this->anexoPathToMediaKey((string)$anexo['caminho']);
        if ($media->isS3() && $mediaKey !== '') {
            $signedUrl = $media->getViewUrl('arquivos', $mediaKey, (string)$anexo['nome_original'], 900);
            if (!empty($signedUrl) && $this->streamSignedUrlInline($signedUrl, $contentType, (string)$anexo['nome_original'])) {
                return;
            }
            if ($media->streamInline('arquivos', $mediaKey, (string)$anexo['nome_original'], $contentType)) {
                return;
            }
        }
        $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../');
        $fullPath = $basePath . ltrim((string)$anexo['caminho'], '/');
        if ((!file_exists($fullPath) || !is_readable($fullPath)) && $mediaKey !== '') {
            $fullPath = $basePath . 'public/uploads/arquivos/' . $mediaKey;
        }
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: inline; filename="' . str_replace('"', '\\"', $anexo['nome_original']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=3600');
        readfile($fullPath);
    }

    private function streamSignedUrlInline(string $signedUrl, string $contentType, string $filename): bool
    {
        if (empty($signedUrl)) return false;
        if (!headers_sent()) {
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: inline; filename="' . str_replace('"', '\\"', $filename) . '"');
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
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) {
                    echo $chunk;
                    return strlen($chunk);
                });
                $ok = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return $ok !== false && $code >= 200 && $code < 300;
            }
        }
        return false;
    }
}
}
