<?php
/**
 * Drive EducaTudo - Espaço de arquivos e pastas para aluno e professor.
 * Listar, criar pasta, upload, renomear, excluir, compartilhar, download.
 */
require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Models/Drive/DriveItem.php';
require_once __DIR__ . '/../../Models/Drive/DriveShare.php';
require_once __DIR__ . '/../../Services/DriveStorageService.php';

class DriveController extends BaseController
{
    private $auth;
    private $driveItem;
    private $driveShare;
    private $storageDir;
    /** @var DriveStorageService */
    private $driveStorage;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->driveItem = new DriveItem();
        $this->driveShare = new DriveShare();
        $baseStorage = __DIR__ . '/../../storage';
        $this->storageDir = $baseStorage . DIRECTORY_SEPARATOR . 'drive';
        $this->ensureStorageDir($baseStorage, 0755);
        $this->ensureStorageDir($this->storageDir, 0755);
        $this->driveStorage = new DriveStorageService($this->config);
    }

    /** Cria o diretório se não existir; tenta 0777 se 0755 falhar (útil em XAMPP/dev) */
    private function ensureStorageDir($path, $mode = 0755)
    {
        if (is_dir($path)) {
            return;
        }
        if (!@mkdir($path, $mode, true) && $mode === 0755) {
            @mkdir($path, 0777, true);
        }
    }

    /** Mensagem amigável para códigos de erro de upload do PHP */
    private function uploadErrorMessage($code)
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o limite permitido no servidor (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo é maior que o permitido.',
            UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado apenas em parte. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado. Selecione um arquivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Erro no servidor: pasta temporária não encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Erro no servidor: não foi possível gravar o arquivo.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por uma extensão do servidor.',
        ];
        return $messages[$code] ?? 'Erro ao enviar o arquivo. Tente outro arquivo ou contate o suporte.';
    }

    private function requireAuth()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            header('Location: ' . URL . '/login');
            exit;
        }
        $tipo = $user['tipo'] ?? '';
        if ($tipo !== 'aluno' && $tipo !== 'professor') {
            header('Location: ' . URL . ($tipo === 'admin_escola' || $tipo === 'admin' ? '/admin/dashboard' : '/dashboard'));
            exit;
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ($tipo === 'aluno' && strpos($uri, '/professor/drive') !== false) {
            header('Location: ' . URL . '/drive');
            exit;
        }
        if ($tipo === 'professor' && preg_match('#/drive(?:/|$|\?)#', $uri) && strpos($uri, '/professor/drive') === false) {
            header('Location: ' . URL . '/professor/drive');
            exit;
        }
        return $user;
    }

    /** Base URL do Drive conforme perfil (aluno: /drive, professor: /professor/drive) */
    private function baseUrl($user)
    {
        return $user['tipo'] === 'professor' ? (URL . '/professor/drive') : (URL . '/drive');
    }

    private function layoutAndViewFolder($tipo)
    {
        if ($tipo === 'professor') {
            return ['layout' => 'teacher', 'viewFolder' => 'teacher'];
        }
        return ['layout' => 'student', 'viewFolder' => 'student'];
    }

    private function ownerType($tipo)
    {
        return $tipo === 'aluno' ? 'student' : 'teacher';
    }

    /**
     * Lista raiz do drive (meus itens + compartilhados comigo)
     */
    public function index()
    {
        $user = $this->requireAuth();
        $this->generateCsrfToken();
        $baseUrl = $this->baseUrl($user);
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];

        $items = $this->driveItem->listChildren($userId, $tipo, null);
        $shared = $this->driveShare->listSharedWithMe($userId, $tipo);

        $lv = $this->layoutAndViewFolder($tipo);
        $this->viewWithLayout($lv['layout'], $lv['viewFolder'] . '/drive/index', [
            'title' => 'Meu Drive - EducaTudo',
            'user' => $user,
            'baseUrl' => $baseUrl,
            'items' => $items,
            'shared' => $shared,
            'breadcrumb' => [['id' => null, 'name' => 'Meu Drive']],
            'currentFolderId' => null,
            'canEdit' => true,
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    /**
     * Abre uma pasta (própria ou compartilhada)
     */
    public function folder($id)
    {
        $user = $this->requireAuth();
        $this->generateCsrfToken();
        $baseUrl = $this->baseUrl($user);
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];
        $id = (int) $id;

        $folder = $this->driveItem->getById($id);
        if (!$folder || $folder['type'] !== 'folder') {
            $this->setFlashMessage('Pasta não encontrada.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        if (!$this->driveShare->canAccess($id, $userId, $tipo)) {
            $this->setFlashMessage('Você não tem acesso a esta pasta.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        $isOwner = (int) $folder['owner_id'] === $userId && $folder['owner_type'] === $this->ownerType($tipo);
        if ($isOwner) {
            $items = $this->driveItem->listChildren($userId, $tipo, $id);
        } else {
            $items = $this->driveItem->listChildrenByParentId($id);
        }

        $breadcrumb = $this->driveItem->getBreadcrumb($id);
        $canEdit = $this->driveShare->canEdit($id, $userId, $tipo);

        $lv = $this->layoutAndViewFolder($tipo);
        $this->viewWithLayout($lv['layout'], $lv['viewFolder'] . '/drive/index', [
            'title' => $folder['name'] . ' - Drive EducaTudo',
            'user' => $user,
            'baseUrl' => $baseUrl,
            'items' => $items,
            'shared' => [],
            'breadcrumb' => $breadcrumb,
            'currentFolderId' => $id,
            'canEdit' => $canEdit,
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function createFolder()
    {
        $user = $this->requireAuth();
        $baseUrl = $this->baseUrl($user);
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $baseUrl);
            exit;
        }
        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página (F5) e tente novamente.', 'error');
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;
            $this->redirectToFolder($baseUrl, $parentId);
            exit;
        }

        $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $this->setFlashMessage('Nome da pasta é obrigatório.', 'error');
            $this->redirectToFolder($baseUrl, $parentId);
            exit;
        }

        if ($parentId) {
            $parent = $this->driveItem->getById($parentId);
            if (!$parent || $parent['type'] !== 'folder') {
                $this->setFlashMessage('Pasta inválida.', 'error');
                $this->redirectToFolder($baseUrl, $parentId);
                exit;
            }
            if (!$this->driveShare->canEdit($parentId, $userId, $tipo)) {
                $this->setFlashMessage('Sem permissão para criar pasta aqui.', 'error');
                $this->redirectToFolder($baseUrl, $parentId);
                exit;
            }
        }

        if ($this->driveItem->nameExistsInFolder($userId, $tipo, $parentId, $name)) {
            $this->setFlashMessage('Já existe uma pasta ou arquivo com esse nome.', 'error');
            $this->redirectToFolder($baseUrl, $parentId);
            exit;
        }

        $this->driveItem->createFolder($userId, $tipo, $parentId, $name);
        $this->setFlashMessage('Pasta criada com sucesso.', 'success');
        $this->redirectToFolder($baseUrl, $parentId);
    }

    public function upload()
    {
        $user = $this->requireAuth();
        $baseUrl = $this->baseUrl($user);
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $baseUrl);
            exit;
        }
        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página (F5) e tente novamente.', 'error');
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;
            $this->redirectToFolder($baseUrl, $parentId);
            exit;
        }

        $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;
        if ($parentId) {
            $parent = $this->driveItem->getById($parentId);
            if (!$parent || $parent['type'] !== 'folder') {
                $this->setFlashMessage('Pasta inválida.', 'error');
                $this->redirectToFolder($baseUrl, $parentId);
                exit;
            }
            if (!$this->driveShare->canEdit($parentId, $userId, $tipo)) {
                $this->setFlashMessage('Sem permissão para enviar arquivos aqui.', 'error');
                $this->redirectToFolder($baseUrl, $parentId);
                exit;
            }
        }

        if (!isset($_FILES['file'])) {
            $this->setFlashMessage('Selecione um arquivo para enviar.', 'error');
            $this->redirectToFolder($baseUrl, $parentId);
            exit;
        }

        $upload = $_FILES['file'];
        if (is_array($upload['error'])) {
            $idx = 0;
            $file = [
                'name' => $upload['name'][$idx] ?? '',
                'type' => $upload['type'][$idx] ?? '',
                'tmp_name' => $upload['tmp_name'][$idx] ?? '',
                'error' => (int) ($upload['error'][$idx] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($upload['size'][$idx] ?? 0),
            ];
        } else {
            $file = $upload;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->setFlashMessage($this->uploadErrorMessage($file['error']), 'error');
            $this->redirectToFolder($baseUrl, $parentId);
            exit;
        }

        $name = trim($file['name']);
        if ($name === '') {
            $this->setFlashMessage('Nome do arquivo inválido.', 'error');
            $this->redirectToFolder($baseUrl, $parentId);
            exit;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $ext = pathinfo($safeName, PATHINFO_EXTENSION);
        $schoolCode = trim((string) ($this->config['school']['code'] ?? ''));
        if ($schoolCode === '' || $schoolCode === 'default') {
            $schoolCode = getenv('SCHOOL_CODE') !== false ? trim((string) getenv('SCHOOL_CODE')) : env('SCHOOL_CODE', 'default');
        }
        $schoolCode = $schoolCode !== '' ? $schoolCode : 'default';
        $role = $tipo === 'aluno' ? 'student' : 'teacher';
        $uniqueName = date('YmdHis') . '_' . uniqid() . ($ext ? '.' . $ext : '');
        $key = $schoolCode . '/' . $role . '/' . $userId . '/' . $uniqueName;
        // Detect real MIME type via finfo — never trust the browser-supplied type field
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']) ?: ($file['type'] ?? 'application/octet-stream');

        if (!$this->driveStorage->put($key, $file['tmp_name'], $mimeType)) {
            error_log('Drive upload failed: key=' . $key . ', isS3=' . ($this->driveStorage->isS3() ? '1' : '0'));
            $this->setFlashMessage(
                $this->driveStorage->isS3()
                    ? 'Erro ao enviar o arquivo para a nuvem. Verifique as credenciais AWS.'
                    : 'Erro ao salvar o arquivo. Verifique se a pasta storage/drive existe e tem permissão de escrita.',
                'error'
            );
            $this->redirectToFolder($baseUrl, $parentId);
            exit;
        }

        $fileSize = (int) $file['size'];
        if ($this->driveItem->nameExistsInFolder($userId, $tipo, $parentId, $name)) {
            $name = pathinfo($name, PATHINFO_FILENAME) . '_' . date('His') . ($ext ? '.' . $ext : '');
        }
        $this->driveItem->createFile($userId, $tipo, $parentId, $name, str_replace('\\', '/', $key), $fileSize, $mimeType);
        $this->setFlashMessage('Arquivo enviado com sucesso.', 'success');
        $this->redirectToFolder($baseUrl, $parentId);
    }

    public function rename()
    {
        $user = $this->requireAuth();
        $baseUrl = $this->baseUrl($user);
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $baseUrl);
            exit;
        }
        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página (F5) e tente novamente.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        $itemId = (int) ($_POST['item_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($itemId < 1 || $name === '') {
            $this->setFlashMessage('Dados inválidos.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        if (!$this->driveShare->canEdit($itemId, $userId, $tipo)) {
            $this->setFlashMessage('Sem permissão para renomear.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        $item = $this->driveItem->getById($itemId);
        if (!$item) {
            $this->setFlashMessage('Item não encontrado.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        $parentId = $item['parent_id'];
        $ownerId = (int) $item['owner_id'];
        $ownerType = $item['owner_type'] === 'student' ? 'aluno' : 'professor';
        if ($this->driveItem->nameExistsInFolder($ownerId, $ownerType, $parentId, $name, $itemId)) {
            $this->setFlashMessage('Já existe um item com esse nome nesta pasta.', 'error');
            $this->redirectToFolder($baseUrl, $parentId);
            exit;
        }

        $this->driveItem->updateName($itemId, $name);
        $this->setFlashMessage('Renomeado com sucesso.', 'success');
        $this->redirectToFolder($baseUrl, $parentId);
    }

    public function delete()
    {
        $user = $this->requireAuth();
        $baseUrl = $this->baseUrl($user);
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $baseUrl);
            exit;
        }
        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página (F5) e tente novamente.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId < 1) {
            header('Location: ' . $baseUrl);
            exit;
        }

        if (!$this->driveShare->canEdit($itemId, $userId, $tipo)) {
            $this->setFlashMessage('Sem permissão para excluir.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        $item = $this->driveItem->getById($itemId);
        $parentId = $item ? $item['parent_id'] : null;

        foreach ($this->driveItem->getDescendantFileKeys($itemId) as $fileKey) {
            $this->driveStorage->delete($fileKey);
        }
        $this->driveItem->delete($itemId);
        $this->setFlashMessage('Excluído com sucesso.', 'success');
        $this->redirectToFolder($baseUrl, $parentId);
    }

    public function share()
    {
        $user = $this->requireAuth();
        $baseUrl = $this->baseUrl($user);
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $baseUrl);
            exit;
        }
        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página (F5) e tente novamente.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        $itemId = (int) ($_POST['item_id'] ?? 0);
        $sharedWithId = (int) ($_POST['shared_with_id'] ?? 0);
        $sharedWithType = $_POST['shared_with_type'] ?? '';
        $permission = ($_POST['permission'] ?? 'view') === 'edit' ? 'edit' : 'view';

        if ($itemId < 1 || $sharedWithId < 1 || !in_array($sharedWithType, ['aluno', 'professor'], true)) {
            $this->setFlashMessage('Dados inválidos.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        if (!$this->driveShare->canEdit($itemId, $userId, $tipo)) {
            $this->setFlashMessage('Sem permissão para compartilhar este item.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        if ($sharedWithId === $userId && $sharedWithType === $tipo) {
            $this->setFlashMessage('Você não pode compartilhar consigo mesmo.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        $this->driveShare->add($itemId, $sharedWithId, $sharedWithType, $permission);
        $this->setFlashMessage('Compartilhado com sucesso.', 'success');
        $item = $this->driveItem->getById($itemId);
        $this->redirectToFolder($baseUrl, $item ? $item['parent_id'] : null);
    }

    public function unshare()
    {
        $user = $this->requireAuth();
        $baseUrl = $this->baseUrl($user);
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $baseUrl);
            exit;
        }
        $csrf = $_POST['_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $csrf)) {
            header('Location: ' . $baseUrl);
            exit;
        }

        $itemId = (int) ($_POST['item_id'] ?? 0);
        $sharedWithId = (int) ($_POST['shared_with_id'] ?? 0);
        $sharedWithType = $_POST['shared_with_type'] ?? '';
        if ($itemId < 1 || $sharedWithId < 1 || !in_array($sharedWithType, ['aluno', 'professor'], true)) {
            header('Location: ' . $baseUrl);
            exit;
        }
        if (!$this->driveShare->canEdit($itemId, $userId, $tipo)) {
            $this->setFlashMessage('Sem permissão.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }
        $this->driveShare->remove($itemId, $sharedWithId, $sharedWithType);
        $this->setFlashMessage('Compartilhamento removido.', 'success');
        $item = $this->driveItem->getById($itemId);
        $this->redirectToFolder($baseUrl, $item ? $item['parent_id'] : null);
    }

    public function download($id)
    {
        $user = $this->requireAuth();
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];
        $id = (int) $id;

        $item = $this->driveItem->getById($id);
        if (!$item || $item['type'] !== 'file') {
            $this->setFlashMessage('Arquivo não encontrado.', 'error');
            header('Location: ' . $this->baseUrl($user));
            exit;
        }
        if (!$this->driveShare->canAccess($id, $userId, $tipo)) {
            $this->setFlashMessage('Sem permissão para baixar.', 'error');
            header('Location: ' . $this->baseUrl($user));
            exit;
        }

        $name = $item['name'];
        $key = $item['file_path'];

        if ($this->driveStorage->isS3()) {
            $url = $this->driveStorage->getDownloadUrl($key, $name);
            if ($url) {
                header('Location: ' . $url);
                exit;
            }
            $this->setFlashMessage('Arquivo temporariamente indisponível. Tente novamente.', 'error');
            header('Location: ' . $this->baseUrl($user));
            exit;
        }

        $path = $this->driveStorage->getLocalPath($key);
        if (!$path || !file_exists($path) || !is_readable($path)) {
            $this->setFlashMessage('Arquivo não encontrado no servidor.', 'error');
            header('Location: ' . $this->baseUrl($user));
            exit;
        }

        header('Content-Type: ' . ($item['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
        header('Content-Length: ' . (int) $item['file_size']);
        readfile($path);
        exit;
    }

    /**
     * Serve o arquivo para exibição inline (iframe/embed). Usado pelo visualizador.
     */
    public function serve($id)
    {
        $user = $this->requireAuth();
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];
        $id = (int) $id;

        $item = $this->driveItem->getById($id);
        if (!$item || $item['type'] !== 'file') {
            http_response_code(404);
            exit;
        }
        if (!$this->driveShare->canAccess($id, $userId, $tipo)) {
            http_response_code(403);
            exit;
        }

        $name = $item['name'];
        $key = $item['file_path'];
        $mime = $item['mime_type'] ?: 'application/octet-stream';

        if ($this->driveStorage->isS3()) {
            if ($this->driveStorage->streamInline($key, $name, $mime)) {
                exit;
            }
            http_response_code(502);
            exit;
        }

        $path = $this->driveStorage->getLocalPath($key);
        if (!$path || !file_exists($path) || !is_readable($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . addslashes($name) . '"');
        header('Content-Length: ' . (int) $item['file_size']);
        readfile($path);
        exit;
    }

    /**
     * Exibe o arquivo no painel (visualizador) com opção de baixar.
     */
    public function viewFile($id)
    {
        $user = $this->requireAuth();
        $this->generateCsrfToken();
        $baseUrl = $this->baseUrl($user);
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];
        $id = (int) $id;

        $item = $this->driveItem->getById($id);
        if (!$item || $item['type'] !== 'file') {
            $this->setFlashMessage('Arquivo não encontrado.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }
        if (!$this->driveShare->canAccess($id, $userId, $tipo)) {
            $this->setFlashMessage('Sem permissão para visualizar.', 'error');
            header('Location: ' . $baseUrl);
            exit;
        }

        $parentId = $item['parent_id'];
        $breadcrumb = $this->driveItem->getBreadcrumb($id);
        $mime = $item['mime_type'] ?? '';
        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
        $viewable = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)
            || strpos($mime, 'pdf') !== false
            || strpos($mime, 'image/') === 0;

        $lv = $this->layoutAndViewFolder($tipo);
        $this->viewWithLayout($lv['layout'], $lv['viewFolder'] . '/drive/view', [
            'title' => $item['name'] . ' - Drive EducaTudo',
            'user' => $user,
            'baseUrl' => $baseUrl,
            'item' => $item,
            'parentId' => $parentId,
            'breadcrumb' => $breadcrumb,
            'viewable' => $viewable,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    /** AJAX: busca usuários para compartilhar (alunos e professores) */
    public function searchUsers()
    {
        $user = $this->requireAuth();
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            echo json_encode(['users' => []]);
            exit;
        }
        $db = Database::getInstance();
        $term = '%' . $q . '%';
        $alunos = $db->fetchAll(
            'SELECT id, nome, email FROM alunos WHERE ativo = 1 AND (nome LIKE :q OR email LIKE :q2) AND id != :me LIMIT 15',
            ['q' => $term, 'q2' => $term, 'me' => (int) $user['id']]
        );
        $professores = $db->fetchAll(
            'SELECT id, nome, email FROM professores WHERE ativo = 1 AND (nome LIKE :q OR email LIKE :q2) AND id != :me LIMIT 15',
            ['q' => $term, 'q2' => $term, 'me' => (int) $user['id']]
        );
        foreach ($alunos as &$a) {
            $a['tipo'] = 'aluno';
            $a['label'] = $a['nome'] . ' (Aluno)';
        }
        foreach ($professores as &$p) {
            $p['tipo'] = 'professor';
            $p['label'] = $p['nome'] . ' (Professor)';
        }
        echo json_encode(['users' => array_merge($alunos, $professores)]);
        exit;
    }

    /** Retorna lista de compartilhamentos de um item (para modal) */
    public function shareList($id)
    {
        $user = $this->requireAuth();
        $baseUrl = $this->baseUrl($user);
        $userId = (int) $user['id'];
        $tipo = $user['tipo'];
        $id = (int) $id;

        if (!$this->driveShare->canEdit($id, $userId, $tipo)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Sem permissão']);
            exit;
        }
        $list = $this->driveShare->listByItem($id);
        $db = Database::getInstance();
        foreach ($list as &$s) {
            if ($s['shared_with_type'] === 'student') {
                $row = $db->fetch('SELECT nome, email FROM alunos WHERE id = :id', ['id' => $s['shared_with_id']]);
            } else {
                $row = $db->fetch('SELECT nome, email FROM professores WHERE id = :id', ['id' => $s['shared_with_id']]);
            }
            $s['nome'] = $row ? $row['nome'] : 'Usuário';
            $s['shared_with_type_label'] = $s['shared_with_type'] === 'student' ? 'aluno' : 'professor';
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['shares' => $list]);
        exit;
    }

    private function redirectToFolder($baseUrl, $parentId)
    {
        if ($parentId) {
            header('Location: ' . $baseUrl . '/folder/' . $parentId);
        } else {
            header('Location: ' . $baseUrl);
        }
        exit;
    }
}
