<?php
/**
 * EducaTudo - Módulo Apostilas (Professor)
 */

if (!class_exists('TeacherApostilaController')) {
class TeacherApostilaController extends BaseController
{
    private $auth;
    private $db;

    private const MODULE_KEY = 'module_professor_apostilas';

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
        if (!$user || ($user['tipo'] ?? '') !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
            return;
        }
    }

    private function ensureModuleEnabledOrRedirect(): bool
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get(self::MODULE_KEY, '1') !== '1') {
            $this->setFlashMessage('O módulo Minha Apostila está desabilitado.', 'error');
            $this->redirect('/professor/dashboard');
            return false;
        }

        return true;
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

    private function resolveMediaKeyCandidates(array $item): array
    {
        $candidates = [];
        $caminho = trim((string)($item['caminho'] ?? ''));

        if ($caminho !== '') {
            $raw = str_replace('\\', '/', $caminho);
            $urlParts = @parse_url($raw);
            if (is_array($urlParts)) {
                if (!empty($urlParts['query'])) {
                    parse_str((string)$urlParts['query'], $query);
                    if (!empty($query['key'])) {
                        $candidates[] = trim((string)$query['key'], '/');
                    }
                }
                if (!empty($urlParts['path'])) {
                    $raw = (string)$urlParts['path'];
                }
            }

            $raw = ltrim($raw, '/');
            $candidates[] = $raw;
            $candidates[] = $this->anexoPathToMediaKey($raw);

            if (preg_match('#^[^/]+/apostilas/(.+)$#', $raw, $m)) {
                $candidates[] = trim((string)$m[1], '/');
            }
            if (strpos($raw, '/apostilas/') !== false) {
                $parts = explode('/apostilas/', $raw, 2);
                if (!empty($parts[1])) {
                    $candidates[] = trim((string)$parts[1], '/');
                }
            }

            $base = basename($raw);
            if ($base !== '' && $base !== 'apostilas') {
                $candidates[] = $base;
            }
        }

        $nomeOriginal = trim((string)($item['nome_original'] ?? ''));
        if ($nomeOriginal !== '') {
            $candidates[] = basename($nomeOriginal);
        }

        $final = [];
        foreach ($candidates as $candidate) {
            $candidate = trim(str_replace('\\', '/', (string)$candidate), '/');
            if ($candidate === '') {
                continue;
            }
            $final[$candidate] = true;
        }

        return array_keys($final);
    }

    private function readItems(): array
    {
        return $this->db->fetchAll(
            "SELECT ma.id, ma.titulo, ma.descricao, ma.visibilidade, ma.created_at,
                    aa.caminho, aa.nome_original, aa.extensao, aa.mime_type, aa.tamanho
             FROM modulos_apostilas ma
             INNER JOIN modulos_apostilas_anexos aa
                     ON aa.modulo_apostila_id = ma.id
             WHERE ma.visibilidade IN ('professor', 'ambos')
             ORDER BY ma.created_at DESC, ma.id DESC"
        );
    }

    private function findItemById(int $id): ?array
    {
        $item = $this->db->fetch(
            "SELECT ma.id, ma.titulo, ma.descricao, ma.visibilidade, ma.created_at,
                    aa.caminho, aa.nome_original, aa.extensao, aa.mime_type, aa.tamanho
             FROM modulos_apostilas ma
             INNER JOIN modulos_apostilas_anexos aa
                     ON aa.modulo_apostila_id = ma.id
             WHERE ma.id = :id
               AND ma.visibilidade IN ('professor', 'ambos')
             LIMIT 1",
            ['id' => $id]
        );
        return $item ?: null;
    }

    public function index()
    {
        if (!$this->ensureModuleEnabledOrRedirect()) {
            return;
        }

        $this->viewWithLayout('professor', 'teacher/apostilas/index', [
            'title' => 'Apostilas - EducaTudo',
            'page_title' => 'Apostilas da Escola',
            'user' => $this->auth->getUser(),
            'items' => $this->readItems(),
            'current_page' => 'apostilas',
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function abrir($id = null)
    {
        if (!$this->ensureModuleEnabledOrRedirect()) {
            return;
        }

        $id = (int)($id ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/professor/apostilas');
            return;
        }

        $item = $this->findItemById($id);
        if (!$item) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/professor/apostilas');
            return;
        }

        $mime = strtolower((string)($item['mime_type'] ?? ''));
        $ext = strtolower((string)($item['extensao'] ?? ''));
        $podeEmbed = ($mime === 'application/pdf' || strpos($mime, 'image/') === 0 || in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true));

        $this->viewWithLayout('professor', 'teacher/apostilas/abrir', [
            'title' => (string)($item['titulo'] ?? $item['nome_original']) . ' - Apostila',
            'page_title' => 'Apostilas da Escola',
            'user' => $this->auth->getUser(),
            'item' => $item,
            'url_visualizar' => URL . '/professor/apostilas/visualizar/' . rawurlencode((string)$id),
            'pode_embed' => $podeEmbed,
            'current_page' => 'apostilas',
        ]);
    }

    public function visualizar($id = null)
    {
        if (!$this->ensureModuleEnabledOrRedirect()) {
            return;
        }

        $id = (int)($id ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            echo 'Apostila não encontrada';
            exit;
        }

        $item = $this->findItemById($id);
        if (!$item) {
            http_response_code(404);
            echo 'Apostila não encontrada';
            exit;
        }

        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $mime = (string)($item['mime_type'] ?? 'application/octet-stream');
        $mediaKeys = $this->resolveMediaKeyCandidates($item);

        if ($media->isS3()) {
            foreach ($mediaKeys as $mediaKey) {
                if (!$media->exists('apostilas', $mediaKey)) {
                    continue;
                }
                if ($media->streamInline('apostilas', $mediaKey, (string)$item['nome_original'], $mime)) {
                    exit;
                }
            }
            http_response_code(404);
            echo 'Apostila não encontrada';
            exit;
        }

        $path = null;
        foreach ($mediaKeys as $mediaKey) {
            $candidatePath = (string)$media->getLocalPath('apostilas', $mediaKey);
            if ($candidatePath !== '' && file_exists($candidatePath) && is_readable($candidatePath)) {
                $path = $candidatePath;
                break;
            }
        }
        if ($path === null) {
            http_response_code(404);
            echo 'Apostila não encontrada';
            exit;
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . str_replace('"', '\\"', (string)$item['nome_original']) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }
}
}

