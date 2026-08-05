<?php
/**
 * EducaTudo - Módulo Apostilas (Aluno)
 */

if (!class_exists('StudentApostilaController')) {
class StudentApostilaController extends BaseController
{
    private $auth;
    private $db;

    private const MODULE_KEY = 'module_aluno_apostilas';

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
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
            return;
        }
    }

    private function ensureModuleEnabledOrRedirect(): bool
    {
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get(self::MODULE_KEY, '1') !== '1') {
            $this->setFlashMessage('O módulo Minha Apostila está desabilitado.', 'error');
            $this->redirect('/dashboard');
            return false;
        }

        return true;
    }

    private function getAluno()
    {
        $user = $this->auth->getUser();
        $aluno = $this->db->fetch(
            "SELECT id, turma_id FROM alunos WHERE id = :id LIMIT 1",
            ['id' => $user['id']]
        );
        if (!$aluno || empty($aluno['turma_id'])) {
            return null;
        }
        return $aluno;
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

    private function readItems(int $turmaId): array
    {
        return $this->db->fetchAll(
            "SELECT ma.id, ma.titulo, ma.descricao, ma.visibilidade, ma.created_at,
                    aa.caminho, aa.nome_original, aa.extensao, aa.mime_type, aa.tamanho
             FROM modulos_apostilas ma
             INNER JOIN modulos_apostilas_anexos aa
                     ON aa.modulo_apostila_id = ma.id
             WHERE ma.visibilidade IN ('aluno', 'ambos')
               AND (
                    ma.turma_id = :turma_id
                    OR EXISTS (
                        SELECT 1
                        FROM modulos_apostilas_turmas mat
                        WHERE mat.modulo_apostila_id = ma.id
                          AND mat.turma_id = :turma_id_2
                    )
               )
             ORDER BY ma.created_at DESC, ma.id DESC",
            ['turma_id' => $turmaId, 'turma_id_2' => $turmaId]
        );
    }

    private function findItemById(int $id, int $turmaId): ?array
    {
        $item = $this->db->fetch(
            "SELECT ma.id, ma.titulo, ma.descricao, ma.visibilidade, ma.created_at,
                    aa.caminho, aa.nome_original, aa.extensao, aa.mime_type, aa.tamanho
             FROM modulos_apostilas ma
             INNER JOIN modulos_apostilas_anexos aa
                     ON aa.modulo_apostila_id = ma.id
             WHERE ma.id = :id
               AND ma.visibilidade IN ('aluno', 'ambos')
               AND (
                    ma.turma_id = :turma_id
                    OR EXISTS (
                        SELECT 1
                        FROM modulos_apostilas_turmas mat
                        WHERE mat.modulo_apostila_id = ma.id
                          AND mat.turma_id = :turma_id_2
                    )
               )
             LIMIT 1",
            ['id' => $id, 'turma_id' => $turmaId, 'turma_id_2' => $turmaId]
        );
        return $item ?: null;
    }

    public function index()
    {
        if (!$this->ensureModuleEnabledOrRedirect()) {
            return;
        }
        $aluno = $this->getAluno();
        if (!$aluno) {
            $this->setFlashMessage('Você não está vinculado a uma turma.', 'error');
            $this->redirect('/dashboard');
            return;
        }

        $this->viewWithLayout('student', 'student/apostilas/index', [
            'title' => 'Apostilas - EducaTudo',
            'page_title' => 'Apostilas da Escola',
            'user' => $this->auth->getUser(),
            'items' => $this->readItems((int)$aluno['turma_id']),
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
            $this->redirect('/aluno/apostilas');
            return;
        }
        $aluno = $this->getAluno();
        if (!$aluno) {
            $this->setFlashMessage('Você não está vinculado a uma turma.', 'error');
            $this->redirect('/dashboard');
            return;
        }

        $item = $this->findItemById($id, (int)$aluno['turma_id']);
        if (!$item) {
            $this->setFlashMessage('Apostila não encontrada.', 'error');
            $this->redirect('/aluno/apostilas');
            return;
        }

        $mime = strtolower((string)($item['mime_type'] ?? ''));
        $ext = strtolower((string)($item['extensao'] ?? ''));
        $podeEmbed = ($mime === 'application/pdf' || strpos($mime, 'image/') === 0 || in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true));

        $this->viewWithLayout('student', 'student/apostilas/abrir', [
            'title' => (string)($item['titulo'] ?? $item['nome_original']) . ' - Apostila',
            'page_title' => 'Apostilas da Escola',
            'user' => $this->auth->getUser(),
            'item' => $item,
            'url_visualizar' => URL . '/aluno/apostilas/visualizar/' . rawurlencode((string)$id),
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
        $aluno = $this->getAluno();
        if (!$aluno) {
            http_response_code(403);
            echo 'Acesso negado';
            exit;
        }

        $item = $this->findItemById($id, (int)$aluno['turma_id']);
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
