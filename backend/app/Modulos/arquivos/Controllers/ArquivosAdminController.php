<?php
/**
 * EducaTudo - Módulo de Arquivos (Admin)
 */

require_once __DIR__ . '/../Services/ArquivosService.php';

if (!class_exists('ArquivosAdminController')) {
class ArquivosAdminController extends BaseController
{
    private ArquivosService $arquivosService;
    private $auth;

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

    private function formViewData(?array $item, array $turmaIds = []): array
    {
        return [
            'title' => ($item ? 'Editar arquivo' : 'Novo arquivo') . ' - Admin',
            'page_title' => $item ? 'Editar arquivo' : 'Novo arquivo',
            'user' => $this->auth->getUser(),
            'item' => $item,
            'turma_ids' => $turmaIds,
            'anexos' => $item ? $this->arquivosService->getAnexosAdmin((int) $item['id']) : [],
            'turmas' => $this->arquivosService->getTurmasAtivas(),
            'materias' => $this->arquivosService->getMateriasAtivas(),
            'professores' => $this->arquivosService->getProfessoresAtivos(),
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
        $item = $this->arquivosService->getItemAdmin($id);
        if (!$item) {
            $this->setFlashMessage('Arquivo não encontrado.', 'error');
            $this->redirect('/admin/arquivos');
            return;
        }
        $this->viewWithLayout(
            'admin',
            'admin/arquivos/form',
            $this->formViewData($item, $this->arquivosService->getTurmaIdsAdmin($id))
        );
    }

    public function index()
    {
        if (!$this->ensureCanManageOrRedirect()) {
            return;
        }

        $filtros = $this->parseFiltrosFromRequest();
        $pastaAtualId = isset($_GET['pasta_id']) && $_GET['pasta_id'] !== '' ? (int) $_GET['pasta_id'] : null;
        $resultado = $this->arquivosService->listarAdmin($filtros, $pastaAtualId);

        $this->viewWithLayout('admin', 'admin/arquivos/index', [
            'title' => 'Arquivos - Admin',
            'page_title' => 'Arquivos da Escola',
            'user' => $this->auth->getUser(),
            'items' => $resultado['items'],
            'pagination' => $resultado['pagination'],
            'filtros' => $filtros,
            'filter_query' => ArquivosService::buildFilterQuery($filtros),
            'turmas' => $this->arquivosService->getTurmasAtivas(),
            'materias' => $this->arquivosService->getMateriasAtivas(),
            'professores' => $this->arquivosService->getProfessoresAtivos(),
            'pastas' => $resultado['pastas'],
            'todas_pastas' => $resultado['todas_pastas'],
            'pasta_atual' => $resultado['pasta_atual'],
            'pasta_atual_id' => $pastaAtualId,
            'breadcrumb' => $resultado['breadcrumb'],
            'has_parent_col' => $resultado['has_parent_col'],
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
        $parentId = isset($_POST['parent_id']) && (int) $_POST['parent_id'] > 0 ? (int) $_POST['parent_id'] : null;
        if (!$this->arquivosService->temColunaParentPasta()) {
            $parentId = null;
        }
        $pasta = $this->arquivosService->criarPastaAdmin($nome, $cor, $parentId);
        $this->json(['success' => true] + $pasta);
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
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if (!$id || $nome === '') {
            $this->json(['error' => 'Dados inválidos'], 400);
            return;
        }
        if (!$this->arquivosService->renomearPastaAdmin($id, $nome)) {
            $this->json(['error' => 'Pasta não encontrada'], 404);
            return;
        }
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
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        if (!$this->arquivosService->excluirPastaAdmin($id)) {
            $this->json(['error' => 'Pasta não encontrada'], 404);
            return;
        }
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
        $arquivoId = (int) ($_POST['arquivo_id'] ?? 0);
        $pastaId = isset($_POST['pasta_id']) && $_POST['pasta_id'] !== '' ? (int) $_POST['pasta_id'] : null;
        $erro = $this->arquivosService->moverArquivoParaPastaAdmin($arquivoId, $pastaId);
        if ($erro !== null) {
            $this->json(['error' => $erro], 404);
            return;
        }
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
        $result = $this->arquivosService->criarAdmin($_POST, $_FILES['arquivo'] ?? [], $this->config);
        if (!$result['ok']) {
            $this->setFlashMessage($result['error'], 'error');
            $this->redirect('/admin/arquivos/criar');
            return;
        }
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
        $result = $this->arquivosService->atualizarAdmin($id, $_POST);
        if (!$result['ok']) {
            $this->setFlashMessage($result['error'], 'error');
            $this->redirect('/admin/arquivos/editar?id=' . max(1, $id));
            return;
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
        $result = $this->arquivosService->excluirAdmin($id, $this->config);
        if (!$result['ok']) {
            $this->setFlashMessage($result['error'], 'error');
            $this->redirect('/admin/arquivos');
            return;
        }
        $this->setFlashMessage('Arquivo removido com sucesso.', 'success');
        $this->redirect('/admin/arquivos');
    }

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
        $anexo = $this->arquivosService->anexos()->findById($id);
        if (!$anexo) {
            http_response_code(404);
            echo 'Anexo não encontrado';
            exit;
        }
        $this->streamAnexoDownload($anexo);
        exit;
    }

    private function streamAnexoDownload(array $anexo): void
    {
        $contentType = ArquivosService::mimePorExtensao((string) ($anexo['extensao'] ?? ''));
        $filename = (string) ($anexo['nome_original'] ?? 'arquivo');
        require_once __DIR__ . '/../../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $mediaKey = ArquivosService::anexoPathToMediaKey((string) ($anexo['caminho'] ?? ''));
        if ($media->isS3() && $mediaKey !== '') {
            $signedUrl = $media->getViewUrl('arquivos', $mediaKey, $filename, 900);
            if (!empty($signedUrl) && $this->streamSignedUrlDownload($signedUrl, $contentType, $filename)) {
                return;
            }
        }
        $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../../');
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
}
}
