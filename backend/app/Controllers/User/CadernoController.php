<?php
/**
 * EducaTudo - Meu Caderno (aluno)
 * Anotações do aluno: título, matéria, anexos (imagens/documentos)
 */

require_once __DIR__ . '/../../Core/BaseController.php';

if (!class_exists('CadernoController')) {
class CadernoController extends BaseController
{
    private $authManager;
    private $db;
    private $uploadDir = 'public/uploads/cadernos_aluno/';

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            header('Location: ' . URL . '/');
            exit;
        }
    }

    private function getAluno()
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.id = :id",
            ['id' => $user['id']]
        );
        return $aluno;
    }

    private function baseData()
    {
        $aluno = $this->getAluno();
        $user = $this->authManager->getUser();
        return [
            'user' => $user,
            'aluno' => $aluno,
            'current_page' => 'caderno',
        ];
    }

    public function index()
    {
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/dashboard');
            exit;
        }
        $pastas = $this->db->fetchAll(
            "SELECT p.*, (SELECT COUNT(*) FROM cadernos_aluno c WHERE c.pasta_id = p.id) as total_anotacoes
             FROM cadernos_aluno_pastas p
             WHERE p.aluno_id = :aluno_id
             ORDER BY p.ordem ASC, p.nome ASC",
            ['aluno_id' => $aluno['id']]
        );
        $itens = $this->db->fetchAll(
            "SELECT c.*, m.nome as materia_nome, p.nome as pasta_nome
             FROM cadernos_aluno c
             LEFT JOIN materias m ON c.materia_id = m.id
             LEFT JOIN cadernos_aluno_pastas p ON c.pasta_id = p.id
             WHERE c.aluno_id = :aluno_id
             ORDER BY c.updated_at DESC",
            ['aluno_id' => $aluno['id']]
        );
        $porPasta = [];
        $semPasta = [];
        foreach ($itens as $item) {
            if (!empty($item['pasta_id'])) {
                $porPasta[$item['pasta_id']][] = $item;
            } else {
                $semPasta[] = $item;
            }
        }
        $data = array_merge($this->baseData(), [
            'title' => 'Meu Caderno - EducaTudo',
            'page_title' => 'Meu Caderno',
            'pastas' => $pastas,
            'itens' => $itens,
            'porPasta' => $porPasta,
            'semPasta' => $semPasta,
            'csrf_token' => $this->generateCsrfToken(),
            'additional_css' => '<link href="' . URL . '/assets/css/caderno.css" rel="stylesheet">',
        ]);
        $this->viewWithLayout('student', 'student/caderno/index', $data);
    }

    /**
     * Nova anotação: exibe formulário com editor HTML/JS (Fabric.js).
     */
    public function create()
    {
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/dashboard');
            exit;
        }
        $materias = $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome");
        $pastas = $this->db->fetchAll("SELECT id, nome FROM cadernos_aluno_pastas WHERE aluno_id = :aid ORDER BY ordem ASC, nome ASC", ['aid' => $aluno['id']]);
        $data = array_merge($this->baseData(), [
            'title' => 'Nova anotação - Meu Caderno',
            'page_title' => 'Nova anotação',
            'materias' => $materias,
            'pastas' => $pastas,
            'csrf_token' => $this->generateCsrfToken(),
            'additional_css' => '<link href="' . URL . '/assets/css/caderno.css" rel="stylesheet">',
        ]);
        $this->viewWithLayout('student', 'student/caderno/create', $data);
    }

    /**
     * Nova anotação com Excalidraw: cria rascunho e abre o editor em página cheia.
     * GET /caderno/novo-excalidraw
     */
    public function createExcalidraw()
    {
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/dashboard');
            exit;
        }
        $cadernoId = $this->db->insert(
            "INSERT INTO cadernos_aluno (aluno_id, titulo, materia_id, pasta_id, observacao) VALUES (:aluno_id, :titulo, :materia_id, :pasta_id, :observacao)",
            [
                'aluno_id' => $aluno['id'],
                'titulo' => 'Nova anotação',
                'materia_id' => null,
                'pasta_id' => null,
                'observacao' => null,
            ]
        );
        header('Location: ' . URL . '/caderno/' . $cadernoId . '/excalidraw-editor');
        exit;
    }

    public function store()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido.';
            header('Location: ' . URL . '/caderno/novo');
            exit;
        }
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $titulo = trim($_POST['titulo'] ?? '');
        $materia_id = !empty($_POST['materia_id']) ? (int)$_POST['materia_id'] : null;
        $pasta_id = !empty($_POST['pasta_id']) ? (int)$_POST['pasta_id'] : null;
        $observacao = trim($_POST['observacao'] ?? '');
        if ($titulo === '') {
            $_SESSION['error_message'] = 'O título é obrigatório.';
            header('Location: ' . URL . '/caderno/novo');
            exit;
        }
        if ($pasta_id) {
            $pasta = $this->db->fetch("SELECT id FROM cadernos_aluno_pastas WHERE id = :id AND aluno_id = :aid", ['id' => $pasta_id, 'aid' => $aluno['id']]);
            if (!$pasta) {
                $pasta_id = null;
            }
        }
        $cadernoId = $this->db->insert(
            "INSERT INTO cadernos_aluno (aluno_id, titulo, materia_id, pasta_id, observacao) VALUES (:aluno_id, :titulo, :materia_id, :pasta_id, :observacao)",
            [
                'aluno_id' => $aluno['id'],
                'titulo' => $titulo,
                'materia_id' => $materia_id,
                'pasta_id' => $pasta_id,
                'observacao' => $observacao ?: null,
            ]
        );
        $this->processarAnexos($cadernoId);
        $_SESSION['success_message'] = 'Anotação criada com sucesso.';
        header('Location: ' . URL . '/caderno/' . $cadernoId);
        exit;
    }

    public function show($id)
    {
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $caderno = $this->db->fetch(
            "SELECT c.*, m.nome as materia_nome, p.nome as pasta_nome FROM cadernos_aluno c LEFT JOIN materias m ON c.materia_id = m.id LEFT JOIN cadernos_aluno_pastas p ON c.pasta_id = p.id WHERE c.id = :id AND c.aluno_id = :aluno_id",
            ['id' => $id, 'aluno_id' => $aluno['id']]
        );
        if (!$caderno) {
            $_SESSION['error_message'] = 'Anotação não encontrada.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $anexos = $this->db->fetchAll("SELECT * FROM cadernos_aluno_anexos WHERE caderno_id = :caderno_id ORDER BY id", ['caderno_id' => $id]);
        $data = array_merge($this->baseData(), [
            'title' => ($caderno['titulo'] ?? '') . ' - Meu Caderno',
            'page_title' => 'Meu Caderno',
            'caderno' => $caderno,
            'anexos' => $anexos,
            'csrf_token' => $this->generateCsrfToken(),
            'additional_css' => '<link href="' . URL . '/assets/css/caderno.css" rel="stylesheet">',
        ]);
        $this->viewWithLayout('student', 'student/caderno/show', $data);
    }

    public function edit($id)
    {
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $caderno = $this->db->fetch("SELECT * FROM cadernos_aluno WHERE id = :id AND aluno_id = :aluno_id", ['id' => $id, 'aluno_id' => $aluno['id']]);
        if (!$caderno) {
            $_SESSION['error_message'] = 'Anotação não encontrada.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $materias = $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome");
        $pastas = $this->db->fetchAll("SELECT id, nome FROM cadernos_aluno_pastas WHERE aluno_id = :aid ORDER BY ordem ASC, nome ASC", ['aid' => $aluno['id']]);
        $anexos = $this->db->fetchAll("SELECT * FROM cadernos_aluno_anexos WHERE caderno_id = :cid ORDER BY id", ['cid' => $id]);
        $data = array_merge($this->baseData(), [
            'title' => 'Editar anotação - Meu Caderno',
            'page_title' => 'Editar anotação',
            'caderno' => $caderno,
            'materias' => $materias,
            'pastas' => $pastas,
            'anexos' => $anexos,
            'csrf_token' => $this->generateCsrfToken(),
            'additional_css' => '<link href="' . URL . '/assets/css/caderno.css" rel="stylesheet"><link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">',
        ]);
        $this->viewWithLayout('student', 'student/caderno/edit', $data);
    }

    public function update($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido.';
            header('Location: ' . URL . '/caderno/' . $id . '/editar');
            exit;
        }
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $caderno = $this->db->fetch("SELECT id FROM cadernos_aluno WHERE id = :id AND aluno_id = :aluno_id", ['id' => $id, 'aluno_id' => $aluno['id']]);
        if (!$caderno) {
            $_SESSION['error_message'] = 'Anotação não encontrada.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $titulo = trim($_POST['titulo'] ?? '');
        $materia_id = !empty($_POST['materia_id']) ? (int)$_POST['materia_id'] : null;
        $pasta_id = !empty($_POST['pasta_id']) ? (int)$_POST['pasta_id'] : null;
        $usarExcalidraw = !empty($_POST['usar_excalidraw']);
        $observacao = trim($_POST['observacao'] ?? '');
        if ($titulo === '') {
            $_SESSION['error_message'] = 'O título é obrigatório.';
            header('Location: ' . URL . '/caderno/' . $id . '/editar');
            exit;
        }
        if ($pasta_id) {
            $pasta = $this->db->fetch("SELECT id FROM cadernos_aluno_pastas WHERE id = :id AND aluno_id = :aid", ['id' => $pasta_id, 'aid' => $aluno['id']]);
            if (!$pasta) {
                $pasta_id = null;
            }
        }
        if ($usarExcalidraw) {
            // Anotação usa Excalidraw: observação é atualizada apenas pelo autosave do editor (excalidraw-salvar)
            $this->db->query(
                "UPDATE cadernos_aluno SET titulo = :titulo, materia_id = :materia_id, pasta_id = :pasta_id WHERE id = :id",
                ['titulo' => $titulo, 'materia_id' => $materia_id, 'pasta_id' => $pasta_id, 'id' => $id]
            );
        } else {
            $this->db->query(
                "UPDATE cadernos_aluno SET titulo = :titulo, materia_id = :materia_id, pasta_id = :pasta_id, observacao = :observacao WHERE id = :id",
                ['titulo' => $titulo, 'materia_id' => $materia_id, 'pasta_id' => $pasta_id, 'observacao' => $observacao ?: null, 'id' => $id]
            );
        }
        $this->processarAnexos($id);
        $_SESSION['success_message'] = 'Anotação atualizada.';
        header('Location: ' . URL . '/caderno/' . $id);
        exit;
    }

    public function destroy($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $caderno = $this->db->fetch("SELECT id FROM cadernos_aluno WHERE id = :id AND aluno_id = :aluno_id", ['id' => $id, 'aluno_id' => $aluno['id']]);
        if (!$caderno) {
            $_SESSION['error_message'] = 'Anotação não encontrada.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $anexos = $this->db->fetchAll("SELECT caminho_arquivo FROM cadernos_aluno_anexos WHERE caderno_id = :cid", ['cid' => $id]);
        foreach ($anexos as $a) {
            if (!empty($a['caminho_arquivo']) && file_exists($a['caminho_arquivo'])) {
                @unlink($a['caminho_arquivo']);
            }
        }
        $dir = $this->uploadDir . $id;
        if (is_dir($dir)) {
            @array_map('unlink', glob($dir . '/*'));
            @rmdir($dir);
        }
        $this->db->query("DELETE FROM cadernos_aluno WHERE id = :id", ['id' => $id]);
        $_SESSION['success_message'] = 'Anotação excluída.';
        header('Location: ' . URL . '/caderno');
        exit;
    }

    /**
     * Criar pasta de estudo (POST /caderno/pasta)
     */
    public function storePasta()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $nome = trim($_POST['nome_pasta'] ?? '');
        if ($nome === '') {
            $_SESSION['error_message'] = 'Informe o nome da pasta.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $maxOrdem = $this->db->fetch("SELECT COALESCE(MAX(ordem), 0) + 1 as next_ordem FROM cadernos_aluno_pastas WHERE aluno_id = :aid", ['aid' => $aluno['id']]);
        $ordem = (int)($maxOrdem['next_ordem'] ?? 1);
        $this->db->insert(
            "INSERT INTO cadernos_aluno_pastas (aluno_id, nome, ordem) VALUES (:aluno_id, :nome, :ordem)",
            ['aluno_id' => $aluno['id'], 'nome' => $nome, 'ordem' => $ordem]
        );
        $_SESSION['success_message'] = 'Pasta de estudo criada.';
        header('Location: ' . URL . '/caderno');
        exit;
    }

    /**
     * Atualizar pasta de estudo (POST /caderno/pasta/{id})
     */
    public function updatePasta($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $pasta = $this->db->fetch("SELECT id FROM cadernos_aluno_pastas WHERE id = :id AND aluno_id = :aid", ['id' => $id, 'aid' => $aluno['id']]);
        if (!$pasta) {
            $_SESSION['error_message'] = 'Pasta não encontrada.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $nome = trim($_POST['nome_pasta'] ?? '');
        if ($nome === '') {
            $_SESSION['error_message'] = 'Informe o nome da pasta.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $this->db->query("UPDATE cadernos_aluno_pastas SET nome = :nome WHERE id = :id", ['nome' => $nome, 'id' => $id]);
        $_SESSION['success_message'] = 'Pasta atualizada.';
        header('Location: ' . URL . '/caderno');
        exit;
    }

    /**
     * Excluir pasta de estudo (POST /caderno/pasta/{id}/excluir). Anotações ficam sem pasta.
     */
    public function destroyPasta($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $pasta = $this->db->fetch("SELECT id FROM cadernos_aluno_pastas WHERE id = :id AND aluno_id = :aid", ['id' => $id, 'aid' => $aluno['id']]);
        if (!$pasta) {
            $_SESSION['error_message'] = 'Pasta não encontrada.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $this->db->query("UPDATE cadernos_aluno SET pasta_id = NULL WHERE pasta_id = :id", ['id' => $id]);
        $this->db->query("DELETE FROM cadernos_aluno_pastas WHERE id = :id", ['id' => $id]);
        $_SESSION['success_message'] = 'Pasta excluída. As anotações continuam no caderno.';
        header('Location: ' . URL . '/caderno');
        exit;
    }

    private function processarAnexos($cadernoId)
    {
        if (empty($_FILES['anexos']) || $_FILES['anexos']['error'][0] === UPLOAD_ERR_NO_FILE) {
            return;
        }
        $baseDir = $this->uploadDir . $cadernoId;
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        $files = $_FILES['anexos'];
        $count = is_array($files['name']) ? count($files['name']) : 1;
        if (!is_array($files['name'])) {
            $files = [
                'name' => [$files['name']],
                'type' => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error' => [$files['error']],
                'size' => [$files['size']],
            ];
        }
        $allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK || $files['size'][$i] > $maxSize) {
                continue;
            }
            $mime = $files['type'][$i];
            $tipo = in_array($mime, $allowedImages) ? 'imagem' : 'documento';
            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION) ?: 'bin';
            $nomeArquivo = uniqid('anexo_', true) . '.' . $ext;
            $caminho = $baseDir . '/' . $nomeArquivo;
            if (!move_uploaded_file($files['tmp_name'][$i], $caminho)) {
                continue;
            }
            $this->db->insert(
                "INSERT INTO cadernos_aluno_anexos (caderno_id, tipo, nome_original, caminho_arquivo, mime_type, tamanho) VALUES (:caderno_id, :tipo, :nome_original, :caminho_arquivo, :mime_type, :tamanho)",
                [
                    'caderno_id' => $cadernoId,
                    'tipo' => $tipo,
                    'nome_original' => $files['name'][$i],
                    'caminho_arquivo' => $caminho,
                    'mime_type' => $mime,
                    'tamanho' => (int)$files['size'][$i],
                ]
            );
        }
    }

    public function excluirAnexo()
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            return;
        }
        $aluno = $this->getAluno();
        if (!$aluno) {
            echo json_encode(['success' => false, 'error' => 'Não autorizado']);
            return;
        }
        $anexoId = (int)($_POST['anexo_id'] ?? 0);
        if (!$anexoId) {
            echo json_encode(['success' => false, 'error' => 'ID do anexo inválido']);
            return;
        }
        $anexo = $this->db->fetch(
            "SELECT a.* FROM cadernos_aluno_anexos a INNER JOIN cadernos_aluno c ON c.id = a.caderno_id WHERE a.id = :id AND c.aluno_id = :aluno_id",
            ['id' => $anexoId, 'aluno_id' => $aluno['id']]
        );
        if (!$anexo) {
            echo json_encode(['success' => false, 'error' => 'Anexo não encontrado']);
            return;
        }
        if (file_exists($anexo['caminho_arquivo'])) {
            @unlink($anexo['caminho_arquivo']);
        }
        $this->db->query("DELETE FROM cadernos_aluno_anexos WHERE id = :id", ['id' => $anexoId]);
        echo json_encode(['success' => true]);
    }

    /**
     * Servir arquivo do anexo (download ou exibição)
     */
    public function anexo($cadernoId, $anexoId)
    {
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('HTTP/1.0 403 Forbidden');
            exit;
        }
        $anexo = $this->db->fetch(
            "SELECT a.* FROM cadernos_aluno_anexos a INNER JOIN cadernos_aluno c ON c.id = a.caderno_id WHERE a.id = :aid AND a.caderno_id = :cid AND c.aluno_id = :aluno_id",
            ['aid' => $anexoId, 'cid' => $cadernoId, 'aluno_id' => $aluno['id']]
        );
        if (!$anexo || !file_exists($anexo['caminho_arquivo'])) {
            header('HTTP/1.0 404 Not Found');
            exit;
        }
        $mime = $anexo['mime_type'] ?: 'application/octet-stream';
        $disposition = $anexo['tipo'] === 'imagem' ? 'inline' : 'attachment';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '\\"', $anexo['nome_original']) . '"');
        readfile($anexo['caminho_arquivo']);
        exit;
    }

    /**
     * Página para anotar (desenhar, setas, destaque, texto) em cima de imagem ou PDF
     */
    public function anotarAnexo($cadernoId, $anexoId)
    {
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $caderno = $this->db->fetch(
            "SELECT c.* FROM cadernos_aluno c WHERE c.id = :id AND c.aluno_id = :aid",
            ['id' => $cadernoId, 'aid' => $aluno['id']]
        );
        if (!$caderno) {
            $_SESSION['error_message'] = 'Anotação não encontrada.';
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $anexo = $this->db->fetch(
            "SELECT a.* FROM cadernos_aluno_anexos a WHERE a.id = :aid AND a.caderno_id = :cid",
            ['aid' => $anexoId, 'cid' => $cadernoId]
        );
        if (!$anexo || !file_exists($anexo['caminho_arquivo'])) {
            $_SESSION['error_message'] = 'Anexo não encontrado.';
            header('Location: ' . URL . '/caderno/' . $cadernoId);
            exit;
        }
        $mime = $anexo['mime_type'] ?? '';
        $podeAnotar = ($anexo['tipo'] === 'imagem') || ($mime === 'application/pdf');
        if (!$podeAnotar) {
            $_SESSION['error_message'] = 'Só é possível anotar imagens (JPG, PNG, GIF, WEBP) ou PDF.';
            header('Location: ' . URL . '/caderno/' . $cadernoId);
            exit;
        }
        $urlArquivo = URL . '/caderno/' . $cadernoId . '/anexo/' . $anexoId;
        $anotacaoCanvas = $anexo['anotacao_canvas'] ?? null;
        $data = array_merge($this->baseData(), [
            'title' => 'Anotar: ' . ($anexo['nome_original'] ?? '') . ' - Meu Caderno',
            'page_title' => 'Anotar anexo',
            'caderno' => $caderno,
            'anexo' => $anexo,
            'url_arquivo' => $urlArquivo,
            'eh_pdf' => ($mime === 'application/pdf'),
            'anotacao_canvas' => $anotacaoCanvas,
            'csrf_token' => $this->generateCsrfToken(),
            'additional_css' => '<link href="' . URL . '/assets/css/caderno.css" rel="stylesheet">',
        ]);
        $this->viewWithLayout('student', 'student/caderno/anotar-anexo', $data);
    }

    /**
     * Salvar anotações (JSON do canvas) do anexo
     */
    public function salvarAnotacaoAnexo()
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            return;
        }
        $aluno = $this->getAluno();
        if (!$aluno) {
            echo json_encode(['success' => false, 'error' => 'Não autorizado']);
            return;
        }
        $cadernoId = (int)($_POST['caderno_id'] ?? 0);
        $anexoId = (int)($_POST['anexo_id'] ?? 0);
        $canvasJson = $_POST['canvas_json'] ?? '';
        if (!$cadernoId || !$anexoId) {
            echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
            return;
        }
        $anexo = $this->db->fetch(
            "SELECT a.id FROM cadernos_aluno_anexos a INNER JOIN cadernos_aluno c ON c.id = a.caderno_id WHERE a.id = :aid AND a.caderno_id = :cid AND c.aluno_id = :aluno_id",
            ['aid' => $anexoId, 'cid' => $cadernoId, 'aluno_id' => $aluno['id']]
        );
        if (!$anexo) {
            echo json_encode(['success' => false, 'error' => 'Anexo não encontrado']);
            return;
        }
        $this->db->query(
            "UPDATE cadernos_aluno_anexos SET anotacao_canvas = :json WHERE id = :id",
            ['json' => $canvasJson ?: null, 'id' => $anexoId]
        );
        echo json_encode(['success' => true, 'redirect' => URL . '/caderno/' . $cadernoId]);
    }

    /**
     * Editor Excalidraw (página mínima para iframe: carrega React + Excalidraw, autosave, export).
     * GET /caderno/{id}/excalidraw-editor
     */
    public function excalidrawEditor($id)
    {
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $caderno = $this->db->fetch(
            "SELECT id, titulo FROM cadernos_aluno WHERE id = :id AND aluno_id = :aluno_id",
            ['id' => $id, 'aluno_id' => $aluno['id']]
        );
        if (!$caderno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $data = [
            'caderno_id' => (int)$id,
            'caderno_titulo' => $caderno['titulo'] ?? '',
            'url_base' => URL,
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->view('student/caderno/excalidraw-editor', $data);
    }

    /**
     * Visualização somente leitura do Excalidraw (para show ou iframe).
     * GET /caderno/{id}/excalidraw-view
     */
    public function excalidrawView($id)
    {
        $aluno = $this->getAluno();
        if (!$aluno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $caderno = $this->db->fetch(
            "SELECT id, titulo, observacao FROM cadernos_aluno WHERE id = :id AND aluno_id = :aluno_id",
            ['id' => $id, 'aluno_id' => $aluno['id']]
        );
        if (!$caderno) {
            header('Location: ' . URL . '/caderno');
            exit;
        }
        $initialData = $this->parseExcalidrawObservacao($caderno['observacao'] ?? '');
        $data = [
            'caderno_id' => (int)$id,
            'initial_data_json' => json_encode($initialData),
        ];
        $this->view('student/caderno/excalidraw-view', $data);
    }

    /**
     * Carrega JSON do Excalidraw da anotação. GET /caderno/{id}/excalidraw-carregar
     */
    public function excalidrawCarregar($id)
    {
        header('Content-Type: application/json; charset=utf-8');
        $aluno = $this->getAluno();
        if (!$aluno) {
            echo json_encode(['success' => false, 'error' => 'Não autorizado']);
            return;
        }
        $caderno = $this->db->fetch(
            "SELECT observacao FROM cadernos_aluno WHERE id = :id AND aluno_id = :aluno_id",
            ['id' => $id, 'aluno_id' => $aluno['id']]
        );
        if (!$caderno) {
            echo json_encode(['success' => false, 'error' => 'Anotação não encontrada']);
            return;
        }
        $data = $this->parseExcalidrawObservacao($caderno['observacao'] ?? '');
        echo json_encode(['success' => true, 'content' => $data]);
    }

    /**
     * Salva JSON do Excalidraw (autosave). POST /caderno/{id}/excalidraw-salvar
     */
    public function excalidrawSalvar($id)
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            return;
        }
        $aluno = $this->getAluno();
        if (!$aluno) {
            echo json_encode(['success' => false, 'error' => 'Não autorizado']);
            return;
        }
        $caderno = $this->db->fetch(
            "SELECT id FROM cadernos_aluno WHERE id = :id AND aluno_id = :aluno_id",
            ['id' => $id, 'aluno_id' => $aluno['id']]
        );
        if (!$caderno) {
            echo json_encode(['success' => false, 'error' => 'Anotação não encontrada']);
            return;
        }
        $raw = $_POST['content'] ?? '';
        $content = null;
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            $content = is_array($decoded) ? $decoded : null;
        }
        $observacao = json_encode([
            'type' => 'excalidraw',
            'content' => $content ?: ['elements' => [], 'appState' => [], 'files' => []],
        ]);
        $this->db->query(
            "UPDATE cadernos_aluno SET observacao = :observacao, updated_at = NOW() WHERE id = :id",
            ['observacao' => $observacao, 'id' => $id]
        );
        echo json_encode(['success' => true]);
    }

    /**
     * Interpreta observacao e retorna conteúdo Excalidraw (elements, appState, files).
     */
    private function parseExcalidrawObservacao($observacao)
    {
        $observacao = trim($observacao ?? '');
        if ($observacao === '') {
            return ['elements' => [], 'appState' => [], 'files' => []];
        }
        if (strpos($observacao, '{') !== 0) {
            return ['elements' => [], 'appState' => [], 'files' => []];
        }
        $payload = json_decode($observacao, true);
        if (!is_array($payload)) {
            return ['elements' => [], 'appState' => [], 'files' => []];
        }
        if (isset($payload['type']) && $payload['type'] === 'excalidraw' && isset($payload['content']) && is_array($payload['content'])) {
            $c = $payload['content'];
            return [
                'elements' => $c['elements'] ?? [],
                'appState' => $c['appState'] ?? [],
                'files' => $c['files'] ?? [],
            ];
        }
        return ['elements' => [], 'appState' => [], 'files' => []];
    }
}
}
