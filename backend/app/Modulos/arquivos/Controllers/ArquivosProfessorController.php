<?php
/**
 * EducaTudo - Módulo de Arquivos (Professor)
 */

require_once __DIR__ . '/../Services/ArquivosService.php';

if (!class_exists('ArquivosProfessorController')) {
class ArquivosProfessorController extends BaseController
{
    private $auth;
    private ArquivosService $arquivosService;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->arquivosService = new ArquivosService();
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $user = $this->auth->getUser();
        if ($user && !in_array($user['tipo'], ['professor', 'admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
        if ($user && $user['tipo'] === 'professor') {
            require_once __DIR__ . '/../../../Core/LayoutHelper.php';
            if (LayoutHelper::get('module_professor_arquivos', '1') !== '1') {
                $this->setFlashMessage('O módulo Arquivos está desabilitado para o professor.', 'error');
                $this->redirect('/professor/dashboard');
                exit;
            }
        }
    }

    private function redirectToCorrectDashboard($tipo): void
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'admin':
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/professor/dashboard');
        }
    }

    private function getProfessor(): array
    {
        $user = $this->auth->getUser();
        $prof = $this->arquivosService->getProfessor((int) $user['id']);
        if (!$prof) {
            $this->setFlashMessage('Professor não encontrado', 'error');
            $this->redirect('/professor/dashboard');
            exit;
        }
        return $prof;
    }

    public function index()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $professor = $this->getProfessor();
        $pastaAtualId = isset($_GET['pasta_id']) && $_GET['pasta_id'] !== '' ? (int) $_GET['pasta_id'] : null;
        $resultado = $this->arquivosService->listarParaProfessor((int) $professor['id'], $pastaAtualId);

        $this->viewWithLayout('professor', 'professor/arquivos/index', [
            'title' => 'Módulo de Arquivos - EducaTudo',
            'user' => $this->auth->getUser(),
            'lista' => $resultado['lista'],
            'pastas' => $resultado['pastas'],
            'pasta_atual' => $resultado['pasta_atual'],
            'pasta_atual_id' => $resultado['pasta_atual_id'],
            'current_page' => 'arquivos',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
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
        $pasta = $this->arquivosService->criarPastaProfessor((int) $professor['id'], $nome, $cor);
        $this->json(['success' => true] + $pasta);
    }

    public function renameFolder()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 403);
            return;
        }
        $professor = $this->getProfessor();
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if (!$id || $nome === '') {
            $this->json(['error' => 'Dados inválidos'], 400);
            return;
        }
        if (!$this->arquivosService->renomearPastaProfessor((int) $professor['id'], $id, $nome)) {
            $this->json(['error' => 'Pasta não encontrada'], 404);
            return;
        }
        $this->json(['success' => true]);
    }

    public function deleteFolder()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 403);
            return;
        }
        $professor = $this->getProfessor();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        if (!$this->arquivosService->excluirPastaProfessor((int) $professor['id'], $id)) {
            $this->json(['error' => 'Pasta não encontrada'], 404);
            return;
        }
        $this->json(['success' => true]);
    }

    public function moveToFolder()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 403);
            return;
        }
        $professor = $this->getProfessor();
        $arquivoId = (int) ($_POST['arquivo_id'] ?? 0);
        $pastaId = isset($_POST['pasta_id']) && $_POST['pasta_id'] !== '' ? (int) $_POST['pasta_id'] : null;
        $erro = $this->arquivosService->moverArquivoParaPastaProfessor((int) $professor['id'], $arquivoId, $pastaId);
        if ($erro !== null) {
            $this->json(['error' => $erro], 404);
            return;
        }
        $this->json(['success' => true]);
    }

    public function create()
    {
        $professor = $this->getProfessor();
        $this->viewWithLayout('professor', 'professor/arquivos/create', [
            'title' => 'Novo arquivo - EducaTudo',
            'user' => $this->auth->getUser(),
            'turmas' => $this->arquivosService->getTurmasProfessor($professor),
            'materias' => $this->arquivosService->getMateriasProfessor($professor),
            'current_page' => 'arquivos',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function alunosPorTurma()
    {
        $professor = $this->getProfessor();
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $alunos = $this->arquivosService->alunosPorTurma($professor, $turmaId);
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
        $result = $this->arquivosService->criarPublicacaoProfessor(
            (int) $professor['id'],
            $_POST,
            $_FILES,
            $this->config
        );
        if (!$result['ok']) {
            $this->setFlashMessage($result['error'], 'error');
            $this->redirect('/professor/arquivos/criar');
            return;
        }
        $this->setFlashMessage('Arquivo criado com sucesso.', 'success');
        $this->redirect('/professor/arquivos');
    }

    public function edit($id = null)
    {
        $id = (int) ($id ?? $_GET['id'] ?? 0);
        if (!$id && preg_match('#/editar/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int) $m[1];
        }
        if (!$id) {
            $this->setFlashMessage('Registro não encontrado.', 'error');
            $this->redirect('/professor/arquivos');
            return;
        }
        $professor = $this->getProfessor();
        $dados = $this->arquivosService->dadosEdicaoProfessor((int) $professor['id'], $id);
        if (!$dados) {
            $this->setFlashMessage('Arquivo não encontrado.', 'error');
            $this->redirect('/professor/arquivos');
            return;
        }
        $this->viewWithLayout('professor', 'professor/arquivos/edit', [
            'title' => 'Editar arquivo - EducaTudo',
            'user' => $this->auth->getUser(),
            'item' => $dados['item'],
            'aluno_atual' => $dados['aluno_atual'],
            'item_turma_ids' => $dados['item_turma_ids'],
            'anexos' => $dados['anexos'],
            'videos' => $dados['videos'],
            'turmas' => $this->arquivosService->getTurmasProfessor($professor),
            'materias' => $this->arquivosService->getMateriasProfessor($professor),
            'current_page' => 'arquivos',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function update()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido', 'error');
            $this->redirect('/professor/arquivos');
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->redirect('/professor/arquivos');
            return;
        }
        $professor = $this->getProfessor();
        $result = $this->arquivosService->atualizarPublicacaoProfessor(
            (int) $professor['id'],
            $id,
            $_POST,
            $_FILES,
            $this->config
        );
        if (!$result['ok']) {
            $this->setFlashMessage($result['error'], 'error');
            $this->redirect('/professor/arquivos/editar/' . $id);
            return;
        }
        $this->setFlashMessage('Arquivo atualizado com sucesso.', 'success');
        $this->redirect('/professor/arquivos');
    }

    public function delete()
    {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        if (preg_match('#/excluir/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int) $m[1];
        }
        if (!$id) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['error' => 'Token inválido'], 403);
                return;
            }
            $this->setFlashMessage('Token inválido', 'error');
            $this->redirect('/professor/arquivos');
            return;
        }
        $professor = $this->getProfessor();
        $result = $this->arquivosService->excluirPublicacaoProfessor((int) $professor['id'], $id, $this->config);
        if (!$result['ok']) {
            $this->json(['error' => $result['error']], 404);
            return;
        }
        $this->setFlashMessage('Arquivo excluído.', 'success');
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->json(['success' => true]);
            return;
        }
        $this->redirect('/professor/arquivos');
    }

    public function preview($id = null)
    {
        if (preg_match('#/preview/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int) $m[1];
        }
        $id = (int) ($id ?? $_GET['id'] ?? 0);
        if (!$id) {
            echo 'Publicação não encontrada.';
            return;
        }
        $professor = $this->getProfessor();
        $dados = $this->arquivosService->previewProfessor((int) $professor['id'], $id);
        if (!$dados) {
            echo 'Publicação não encontrada.';
            return;
        }
        $data = [
            'pub' => $dados['pub'],
            'anexos' => $dados['anexos'],
            'videos' => $dados['videos'],
            'url_base' => rtrim(URL, '/'),
            'iframe' => !empty($_GET['iframe']),
        ];
        if (!empty($_GET['iframe'])) {
            $this->view('professor/arquivos/preview', $data);
            return;
        }
        $this->viewWithLayout('professor', 'professor/arquivos/preview', $data);
    }

    public function verAnexo($id = null)
    {
        if (preg_match('#/ver-anexo/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int) $m[1];
        }
        $id = (int) ($id ?? $_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            exit;
        }
        $professor = $this->getProfessor();
        $anexo = $this->arquivosService->professorPodeVerAnexo($id, (int) $professor['id']);
        if (!$anexo) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            exit;
        }
        $this->streamAnexoInline($anexo);
        exit;
    }

    private function streamAnexoInline(array $anexo): void
    {
        $ext = strtolower((string) $anexo['extensao']);
        $contentType = ArquivosService::mimePorExtensao($ext);
        require_once __DIR__ . '/../../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $mediaKey = ArquivosService::anexoPathToMediaKey((string) $anexo['caminho']);
        if ($media->isS3() && $mediaKey !== '') {
            $signedUrl = $media->getViewUrl('arquivos', $mediaKey, (string) $anexo['nome_original'], 900);
            if (!empty($signedUrl) && $this->streamSignedUrlInline($signedUrl, $contentType, (string) $anexo['nome_original'])) {
                return;
            }
            if ($media->streamInline('arquivos', $mediaKey, (string) $anexo['nome_original'], $contentType)) {
                return;
            }
        }
        $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../../');
        $fullPath = $basePath . ltrim((string) $anexo['caminho'], '/');
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
        if (empty($signedUrl)) {
            return false;
        }
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
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return $ok !== false && $code >= 200 && $code < 300;
            }
        }
        return false;
    }
}
}
