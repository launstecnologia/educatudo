<?php
/**
 * EducaTudo - Módulo Arquivos (Aluno)
 * Controllers em app/Modulos/arquivos/Controllers/
 */

require_once __DIR__ . '/../Services/ArquivosService.php';

if (!class_exists('ArquivosAlunoController')) {
class ArquivosAlunoController extends BaseController
{
    private $auth;
    private $db;
    private ArquivosService $arquivosService;

    private function streamSignedUrl(string $signedUrl, string $contentType, string $filename, string $disposition = 'inline'): bool
    {
        if ($signedUrl === '') {
            return false;
        }

        if (!headers_sent()) {
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '\\"', $filename) . '"');
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
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) {
                    echo $chunk;
                    return strlen($chunk);
                });
                $ok = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return $ok !== false && $httpCode >= 200 && $httpCode < 300;
            }
        }

        return false;
    }

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->arquivosService = new ArquivosService();
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $user = $this->auth->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
    }

    private function redirectToCorrectDashboard($tipo): void
    {
        switch ($tipo) {
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'admin':
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/dashboard');
        }
    }

    private function ensureModuleEnabled(bool $somenteRecuperacao = false): bool
    {
        require_once __DIR__ . '/../../../Core/LayoutHelper.php';
        if ($somenteRecuperacao) {
            if (!LayoutHelper::isModuleEnabled('aluno_recuperacao')) {
                $this->setFlashMessage('O módulo Recuperação está desabilitado.', 'error');
                $this->redirect('/dashboard');
                return false;
            }
            return true;
        }
        if (!LayoutHelper::isModuleEnabled('aluno_arquivos')) {
            $this->setFlashMessage('O módulo Arquivos está desabilitado.', 'error');
            $this->redirect('/dashboard');
            return false;
        }
        return true;
    }

    /** Detalhe/abrir anexo: libera se Arquivos ou Recuperação estiver on. */
    private function ensureAcessoArquivosOuRecuperacao(): bool
    {
        require_once __DIR__ . '/../../../Core/LayoutHelper.php';
        if (LayoutHelper::isModuleEnabled('aluno_arquivos') || LayoutHelper::isModuleEnabled('aluno_recuperacao')) {
            return true;
        }
        $this->setFlashMessage('O módulo Arquivos está desabilitado.', 'error');
        $this->redirect('/dashboard');
        return false;
    }

    private function getAluno()
    {
        $user = $this->auth->getUser();
        $aluno = $this->db->fetch(
            'SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON a.turma_id = t.id WHERE a.id = :id',
            ['id' => $user['id']]
        );
        if (!$aluno || empty($aluno['turma_id'])) {
            $this->setFlashMessage('Você não está vinculado a uma turma.', 'error');
            $this->redirect('/dashboard');
            exit;
        }
        return $aluno;
    }

    public function index()
    {
        $this->listarArquivos(false);
    }

    public function recuperacao()
    {
        $this->listarArquivos(true);
    }

    private function listarArquivos(bool $somenteRecuperacao): void
    {
        if (!$this->ensureModuleEnabled($somenteRecuperacao)) {
            return;
        }
        $aluno = $this->getAluno();
        $resultado = $this->arquivosService->listarParaAluno(
            (int) $aluno['turma_id'],
            (int) $aluno['id'],
            [
                'materia_id' => $_GET['materia_id'] ?? null,
                'professor_id' => $_GET['professor_id'] ?? null,
                'titulo' => $_GET['titulo'] ?? '',
                'pasta_id' => array_key_exists('pasta_id', $_GET) ? $_GET['pasta_id'] : null,
                'page' => $_GET['page'] ?? 1,
            ],
            $somenteRecuperacao
        );

        $basePath = $somenteRecuperacao ? '/aluno/recuperacao' : '/aluno/arquivos';
        $this->viewWithLayout('student', 'aluno/arquivos/index', [
            'title' => ($somenteRecuperacao ? 'Recuperação' : 'Arquivos') . ' - EducaTudo',
            'user' => $this->auth->getUser(),
            'lista' => $resultado['lista'],
            'pastas' => $resultado['pastas'],
            'pasta_atual' => $resultado['pasta_atual'],
            'filtro_pasta_id' => $resultado['filtro_pasta_id'],
            'current_page' => $somenteRecuperacao ? 'recuperacao' : 'arquivos',
            'modo_recuperacao' => $somenteRecuperacao,
            'base_path' => $basePath,
            'filtro_materia_id' => $resultado['filtro_materia_id'],
            'filtro_professor_id' => $resultado['filtro_professor_id'],
            'filtro_titulo' => $resultado['filtro_titulo'],
            'materias' => $resultado['materias'],
            'professores' => $resultado['professores'],
            'total' => $resultado['total'],
            'page' => $resultado['page'],
            'per_page' => $resultado['per_page'],
            'total_pages' => $resultado['total_pages'],
        ]);
    }

    public function ver()
    {
        if (!$this->ensureAcessoArquivosOuRecuperacao()) {
            return;
        }
        $id = (int) ($_GET['id'] ?? 0);
        if (preg_match('#/arquivos/ver/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int) $m[1];
        }
        if (!$id) {
            $this->setFlashMessage('Publicação não encontrada.', 'error');
            $this->redirect('/aluno/arquivos');
            return;
        }
        $aluno = $this->getAluno();
        $pub = $this->arquivosService->arquivos()->findVisivelParaAluno(
            $id,
            (int) $aluno['turma_id'],
            (int) $aluno['id']
        );
        if (!$pub) {
            $this->setFlashMessage('Publicação não encontrada.', 'error');
            $this->redirect('/aluno/arquivos');
            return;
        }
        $anexos = $this->arquivosService->anexos()->listByModuloArquivo($id);
        $videos = $this->arquivosService->videosComEmbed($id);
        $modoRecuperacao = !empty($pub['recuperacao']);
        $this->viewWithLayout('student', 'aluno/arquivos/ver', [
            'title' => $pub['titulo'] . ' - ' . ($modoRecuperacao ? 'Recuperação' : 'Arquivos'),
            'user' => $this->auth->getUser(),
            'pub' => $pub,
            'anexos' => $anexos,
            'videos' => $videos,
            'modo_recuperacao' => $modoRecuperacao,
            'current_page' => $modoRecuperacao ? 'recuperacao' : 'arquivos',
        ]);
    }

    public static function urlParaEmbed(string $url): string
    {
        return ArquivosService::urlParaEmbed($url);
    }

    public function visualizarAnexo()
    {
        $id = (int) ($_GET['id'] ?? 0);
        if (preg_match('#/arquivos/visualizar/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int) $m[1];
        }
        if (!$id) {
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

        $embedTokenValid = false;
        $embed = $_GET['embed'] ?? '';
        $expires = isset($_GET['expires']) ? (int) $_GET['expires'] : 0;
        $token = (string) ($_GET['token'] ?? '');
        if ($embed === '1' && $expires > time() && $token !== '') {
            $secret = $this->config['security']['entra_como_secret'] ?? hash('sha256', 'embed');
            $expected = hash_hmac('sha256', $id . '.' . $expires, $secret);
            if (hash_equals($expected, $token)) {
                $embedTokenValid = true;
            }
        }

        if (!$embedTokenValid) {
            $aluno = $this->auth->getUser();
            if (!$aluno || $aluno['tipo'] !== 'aluno') {
                http_response_code(403);
                exit;
            }
            $alunoRow = $this->db->fetch('SELECT id, turma_id FROM alunos WHERE id = :id', ['id' => $aluno['id']]);
            if (!$alunoRow || empty($alunoRow['turma_id'])) {
                http_response_code(403);
                exit;
            }
            if (!$this->arquivosService->arquivos()->alunoPodeVer(
                (int) $anexo['modulo_arquivo_id'],
                (int) $alunoRow['turma_id'],
                (int) $alunoRow['id']
            )) {
                http_response_code(404);
                echo 'Anexo não encontrado';
                exit;
            }
        }

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
        $ext = strtolower((string) $anexo['extensao']);
        $contentType = $mimes[$ext] ?? 'application/octet-stream';
        $download = (!empty($_GET['download']) && $_GET['download'] === '1');
        $disposition = $download ? 'attachment' : 'inline';

        require_once __DIR__ . '/../../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $mediaKey = ArquivosService::anexoPathToMediaKey((string) $anexo['caminho']);
        if ($media->isS3() && $mediaKey !== '') {
            $signedUrl = $media->getViewUrl('arquivos', $mediaKey, (string) $anexo['nome_original'], 900);
            if (!empty($signedUrl) && $this->streamSignedUrl($signedUrl, $contentType, (string) $anexo['nome_original'], $disposition)) {
                exit;
            }
            if (!$download && $media->streamInline('arquivos', $mediaKey, (string) $anexo['nome_original'], $contentType)) {
                exit;
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
            exit;
        }
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '\\"', $anexo['nome_original']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=3600');
        readfile($fullPath);
        exit;
    }

    public function abrir()
    {
        if (!$this->ensureAcessoArquivosOuRecuperacao()) {
            return;
        }
        $id = (int) ($_GET['id'] ?? 0);
        if (preg_match('#/arquivos/abrir/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            $id = (int) $m[1];
        }
        if (!$id) {
            $this->setFlashMessage('Anexo não encontrado.', 'error');
            $this->redirect('/aluno/arquivos');
            return;
        }
        $aluno = $this->getAluno();
        $anexo = $this->arquivosService->anexos()->findById($id);
        if (!$anexo) {
            $this->setFlashMessage('Anexo não encontrado.', 'error');
            $this->redirect('/aluno/arquivos');
            return;
        }
        if (!$this->arquivosService->arquivos()->alunoPodeVer(
            (int) $anexo['modulo_arquivo_id'],
            (int) $aluno['turma_id'],
            (int) $aluno['id']
        )) {
            $this->setFlashMessage('Anexo não encontrado.', 'error');
            $this->redirect('/aluno/arquivos');
            return;
        }
        $urlVisualizar = URL . '/aluno/arquivos/visualizar/' . $id;
        $urlDownload = $urlVisualizar . '?download=1';
        $ext = strtolower($anexo['extensao']);
        $ehVideo = in_array($ext, ['mp4', 'webm', 'ogg']);
        $podeEmbed = $ehVideo || in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
        $ehOffice = in_array($ext, ['ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx']);
        $urlEmbedPublic = '';
        if ($ehOffice) {
            $expires = time() + 600;
            $secret = $this->config['security']['entra_como_secret'] ?? hash('sha256', 'embed');
            $token = hash_hmac('sha256', $id . '.' . $expires, $secret);
            $urlEmbedPublic = URL . '/aluno/arquivos/visualizar/' . $id . '?embed=1&expires=' . $expires . '&token=' . urlencode($token);
        }
        $this->viewWithLayout('student', 'aluno/arquivos/abrir', [
            'title' => $anexo['nome_original'] . ' - Visualizar',
            'user' => $this->auth->getUser(),
            'anexo' => $anexo,
            'url_visualizar' => $urlVisualizar,
            'url_download' => $urlDownload,
            'url_embed_public' => $urlEmbedPublic,
            'pode_embed' => $podeEmbed,
            'eh_video' => $ehVideo,
            'eh_office' => $ehOffice,
            'current_page' => 'arquivos',
        ]);
    }
}
}
