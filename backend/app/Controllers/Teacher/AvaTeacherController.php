<?php

require_once __DIR__ . '/../../Services/AvaCourseService.php';
require_once __DIR__ . '/../../Services/AvaProgressService.php';
require_once __DIR__ . '/../../Models/Ava/LessonComment.php';

/**
 * EducaTudo - AVA: área do professor (gestão do conteúdo das próprias disciplinas).
 * Reaproveita as views de admin/ava com base_url '/professor/ava'.
 */
if (!class_exists('AvaTeacherController')) {
class AvaTeacherController extends BaseController
{
    private const EXTENSOES = ['pdf', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'mp3', 'mp4'];
    private const BASE = '/professor/ava';

    private $authManager;
    private $db;
    private AvaCourseService $service;
    private AvaProgressService $progress;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->redirect('/professor');
        }
        $this->service = new AvaCourseService();
        $this->progress = new AvaProgressService();
    }

    private function professorId(): int
    {
        $user = $this->authManager->getUser();
        return (int) ($user['id'] ?? 0);
    }

    private function ensureOwnsDiscipline(int $disciplinaId): bool
    {
        return $this->service->disciplinesModel()->isOwnedByTeacher($disciplinaId, $this->professorId());
    }

    public function index(): void
    {
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('professor', 'teacher/ava/index', [
            'title' => 'Minhas Disciplinas (AVA) - EducaTudo',
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'disciplinas' => $this->service->disciplinesModel()->byTeacher($this->professorId()),
            'base_url' => self::BASE,
        ]);
    }

    public function show(string $id): void
    {
        if (!$this->ensureOwnsDiscipline((int) $id)) {
            $this->setFlashMessage('Você não tem acesso a esta disciplina.', 'error');
            $this->redirect(self::BASE);
            return;
        }
        $disc = $this->service->disciplinesModel()->find((int) $id);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('professor', 'admin/ava/disciplina', [
            'title' => htmlspecialchars($disc['nome']) . ' - AVA',
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'disciplina' => $disc,
            'outline' => $this->service->disciplineOutline((int) $id),
            'resumo' => $this->progress->teacherDisciplineSummary((int) $id),
            'tipos_aula' => Lesson::TIPOS,
            'base_url' => self::BASE,
            'somente_conteudo' => true,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    // ---- Módulos ----------------------------------------------------------

    public function storeModulo(): void
    {
        $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
        if (!$this->csrfOk() || !$this->ensureOwnsDiscipline($disciplinaId)) {
            $this->redirect(self::BASE . '/disciplinas/' . $disciplinaId);
            return;
        }
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        if ($titulo !== '') {
            $this->service->modulesModel()->save(
                $disciplinaId,
                $titulo,
                trim((string) ($_POST['descricao'] ?? '')) ?: null,
                (int) ($_POST['ordem'] ?? 0),
                (string) ($_POST['status'] ?? 'publicado')
            );
            $this->setFlashMessage('Módulo salvo.', 'success');
        }
        $this->redirect(self::BASE . '/disciplinas/' . $disciplinaId);
    }

    public function updateModulo(string $id): void
    {
        $modulo = $this->service->modulesModel()->find((int) $id);
        $disciplinaId = (int) ($modulo['disciplina_id'] ?? 0);
        if (!$this->csrfOk() || !$this->ensureOwnsDiscipline($disciplinaId)) {
            $this->redirect(self::BASE . '/disciplinas/' . $disciplinaId);
            return;
        }
        $this->service->modulesModel()->save(
            $disciplinaId,
            trim((string) ($_POST['titulo'] ?? ($modulo['titulo'] ?? ''))),
            trim((string) ($_POST['descricao'] ?? '')) ?: null,
            (int) ($_POST['ordem'] ?? 0),
            (string) ($_POST['status'] ?? 'publicado'),
            (int) $id
        );
        $this->setFlashMessage('Módulo atualizado.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . $disciplinaId);
    }

    public function deleteModulo(string $id): void
    {
        $modulo = $this->service->modulesModel()->find((int) $id);
        $disciplinaId = (int) ($modulo['disciplina_id'] ?? 0);
        if (!$this->csrfOk() || !$this->ensureOwnsDiscipline($disciplinaId)) {
            $this->redirect(self::BASE . '/disciplinas/' . $disciplinaId);
            return;
        }
        $this->service->modulesModel()->delete((int) $id);
        $this->setFlashMessage('Módulo removido.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . $disciplinaId);
    }

    // ---- Aulas ------------------------------------------------------------

    public function createAula(string $moduloId): void
    {
        $modulo = $this->service->modulesModel()->find((int) $moduloId);
        if (!$modulo || !$this->ensureOwnsDiscipline((int) $modulo['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $this->renderAulaForm($modulo, null);
    }

    public function editAula(string $id): void
    {
        $aula = $this->service->lessonsModel()->find((int) $id);
        if (!$aula || !$this->ensureOwnsDiscipline((int) $aula['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $modulo = $this->service->modulesModel()->find((int) $aula['modulo_id']);
        $this->renderAulaForm($modulo, $aula);
    }

    private function renderAulaForm(?array $modulo, ?array $aula): void
    {
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('professor', 'admin/ava/aula_form', [
            'title' => ($aula ? 'Editar Aula' : 'Nova Aula') . ' - AVA',
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'modulo' => $modulo,
            'aula' => $aula,
            'anexos' => $aula ? $this->service->lessonsModel()->attachments((int) $aula['id']) : [],
            'comentarios' => $aula ? (new LessonComment())->byLesson((int) $aula['id']) : [],
            'comentarios_user_id' => $this->professorId(),
            'tipos' => Lesson::TIPOS,
            'providers' => Lesson::PROVIDERS,
            'base_url' => self::BASE,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function storeAula(string $moduloId): void
    {
        $modulo = $this->service->modulesModel()->find((int) $moduloId);
        if (!$this->csrfOk() || !$modulo || !$this->ensureOwnsDiscipline((int) $modulo['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $data = $_POST;
        $data['modulo_id'] = (int) $moduloId;
        $data['professor_id'] = $this->professorId();
        $id = $this->service->lessonsModel()->save($data);
        $this->setFlashMessage('Aula criada.', 'success');
        $this->redirect(self::BASE . '/aulas/' . $id . '/editar');
    }

    public function updateAula(string $id): void
    {
        $aula = $this->service->lessonsModel()->find((int) $id);
        if (!$this->csrfOk() || !$aula || !$this->ensureOwnsDiscipline((int) $aula['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $data = $_POST;
        $data['modulo_id'] = (int) $aula['modulo_id'];
        $this->service->lessonsModel()->save($data, (int) $id);
        $this->setFlashMessage('Aula atualizada.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . (int) $aula['disciplina_id']);
    }

    public function deleteAula(string $id): void
    {
        $aula = $this->service->lessonsModel()->find((int) $id);
        if (!$this->csrfOk() || !$aula || !$this->ensureOwnsDiscipline((int) $aula['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $disciplinaId = (int) $aula['disciplina_id'];
        $this->service->lessonsModel()->delete((int) $id);
        $this->setFlashMessage('Aula removida.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . $disciplinaId);
    }

    // ---- Anexos -----------------------------------------------------------

    public function uploadAnexo(string $aulaId): void
    {
        $aula = $this->service->lessonsModel()->find((int) $aulaId);
        if (!$this->csrfOk() || !$aula || !$this->ensureOwnsDiscipline((int) $aula['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $url = trim((string) ($_POST['url'] ?? ''));
        if ($url !== '') {
            $this->service->lessonsModel()->addAttachment((int) $aulaId, [
                'tipo' => 'link',
                'nome' => trim((string) ($_POST['nome'] ?? '')) ?: $url,
                'url' => $url,
            ]);
            $this->setFlashMessage('Link adicionado.', 'success');
        } elseif (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
            try {
                $arq = $this->processarUpload($_FILES['arquivo']);
                $this->service->lessonsModel()->addAttachment((int) $aulaId, [
                    'tipo' => 'arquivo',
                    'arquivo_key' => $arq['key'],
                    'nome' => $arq['nome'],
                    'mime' => $arq['mime'],
                    'tamanho' => $arq['tamanho'],
                ]);
                $this->setFlashMessage('Anexo enviado.', 'success');
            } catch (Throwable $e) {
                $this->setFlashMessage($e->getMessage(), 'error');
            }
        }
        $this->redirect(self::BASE . '/aulas/' . (int) $aulaId . '/editar');
    }

    public function deleteAnexo(string $id): void
    {
        $anexo = $this->service->lessonsModel()->findAttachment((int) $id);
        $aulaId = (int) ($anexo['aula_id'] ?? 0);
        $aula = $aulaId > 0 ? $this->service->lessonsModel()->find($aulaId) : null;
        if (!$this->csrfOk() || !$aula || !$this->ensureOwnsDiscipline((int) $aula['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        if ($anexo && !empty($anexo['arquivo_key'])) {
            try {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                (new MediaStorageService($this->config))->delete('arquivos', (string) $anexo['arquivo_key']);
            } catch (Throwable $e) {
                // best-effort
            }
        }
        $this->service->lessonsModel()->deleteAttachment((int) $id);
        $this->setFlashMessage('Anexo removido.', 'success');
        $this->redirect(self::BASE . '/aulas/' . $aulaId . '/editar');
    }

    // ---- Helpers ----------------------------------------------------------

    private function csrfOk(): bool
    {
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            return false;
        }
        return true;
    }

    /** @return array{key:string,nome:string,mime:string,tamanho:int} */
    private function processarUpload(array $file): array
    {
        $ext = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSOES, true)) {
            throw new Exception('Tipo de arquivo não permitido.');
        }
        if ($file['size'] > 100 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Máximo 100MB.');
        }
        $mimeReal = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if ($detected) {
                    $mimeReal = $detected;
                }
            }
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $slug = trim((string) ($this->config['tenant']['slug'] ?? $this->config['school']['code'] ?? '')) ?: 'default';
        $nomeUnico = 'ava_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $key = $slug . '/ava/anexos/' . $nomeUnico;
        if (!$media->put('arquivos', $key, $file['tmp_name'], $mimeReal)) {
            throw new Exception('Falha ao armazenar o arquivo.');
        }
        return [
            'key' => $key,
            'nome' => substr((string) $file['name'], 0, 255),
            'mime' => $mimeReal,
            'tamanho' => (int) $file['size'],
        ];
    }
}
}
