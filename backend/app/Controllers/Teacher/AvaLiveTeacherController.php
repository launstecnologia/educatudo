<?php

require_once __DIR__ . '/../../Services/AvaCourseService.php';
require_once __DIR__ . '/../../Services/AvaLiveClassService.php';

/**
 * EducaTudo - AVA: aulas ao vivo na área do professor.
 */
if (!class_exists('AvaLiveTeacherController')) {
class AvaLiveTeacherController extends BaseController
{
    private const BASE = '/professor/ava';

    private $authManager;
    private AvaCourseService $service;
    private AvaLiveClassService $live;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->redirect('/professor');
        }
        $this->service = new AvaCourseService();
        $this->live = new AvaLiveClassService();
    }

    private function professorId(): int
    {
        $user = $this->authManager->getUser();
        return (int) ($user['id'] ?? 0);
    }

    private function ownsDiscipline(int $disciplinaId): bool
    {
        return $this->service->disciplinesModel()->isOwnedByTeacher($disciplinaId, $this->professorId());
    }

    private function csrfOk(): bool
    {
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            return false;
        }
        return true;
    }

    public function list(string $disciplinaId): void
    {
        $did = (int) $disciplinaId;
        if (!$this->ownsDiscipline($did)) {
            $this->redirect(self::BASE);
            return;
        }
        $disc = $this->service->disciplinesModel()->find($did);
        $aulas = $this->live->model()->byDiscipline($did);
        foreach ($aulas as &$a) {
            $a['estado'] = $this->live->computedState($a);
            $a['tem_gravacao'] = $this->live->hasRecording($a);
        }
        unset($a);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('professor', 'admin/ava/ao_vivo', [
            'title' => 'Aulas ao vivo - ' . htmlspecialchars((string) $disc['nome']),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'disciplina' => $disc,
            'aulas' => $aulas,
            'base_url' => self::BASE,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function create(string $disciplinaId): void
    {
        $did = (int) $disciplinaId;
        if (!$this->ownsDiscipline($did)) {
            $this->redirect(self::BASE);
            return;
        }
        $this->renderForm($did, null);
    }

    public function edit(string $id): void
    {
        $live = $this->live->model()->find((int) $id);
        if (!$live || !$this->ownsDiscipline((int) $live['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $this->renderForm((int) $live['disciplina_id'], $live);
    }

    private function renderForm(int $disciplinaId, ?array $live): void
    {
        $disc = $this->service->disciplinesModel()->find($disciplinaId);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('professor', 'admin/ava/ao_vivo_form', [
            'title' => ($live ? 'Editar Aula ao vivo' : 'Nova Aula ao vivo') . ' - AVA',
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'disciplina' => $disc,
            'aula' => $live,
            'modulos' => $this->service->modulesModel()->byDiscipline($disciplinaId),
            'plataformas' => LiveClass::PLATAFORMAS,
            'base_url' => self::BASE,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function store(string $disciplinaId): void
    {
        $did = (int) $disciplinaId;
        if (!$this->csrfOk() || !$this->ownsDiscipline($did)) {
            $this->redirect(self::BASE . '/disciplinas/' . $did . '/ao-vivo');
            return;
        }
        if (trim((string) ($_POST['titulo'] ?? '')) === '') {
            $this->setFlashMessage('Informe o título da aula.', 'error');
            $this->redirect(self::BASE . '/disciplinas/' . $did . '/ao-vivo/nova');
            return;
        }
        $data = $_POST;
        $data['disciplina_id'] = $did;
        $data['professor_id'] = $this->professorId();
        $res = $this->live->create($data);
        $this->setFlashMessage($res['warning'] !== '' ? $res['warning'] : 'Aula ao vivo criada.', $res['warning'] !== '' ? 'info' : 'success');
        $this->redirect(self::BASE . '/disciplinas/' . $did . '/ao-vivo');
    }

    public function update(string $id): void
    {
        $live = $this->live->model()->find((int) $id);
        if (!$this->csrfOk() || !$live || !$this->ownsDiscipline((int) $live['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $data = $_POST;
        $data['disciplina_id'] = (int) $live['disciplina_id'];
        $this->live->model()->save($data, (int) $id);
        $this->setFlashMessage('Aula ao vivo atualizada.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . (int) $live['disciplina_id'] . '/ao-vivo');
    }

    public function delete(string $id): void
    {
        $live = $this->live->model()->find((int) $id);
        if (!$this->csrfOk() || !$live || !$this->ownsDiscipline((int) $live['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $did = (int) $live['disciplina_id'];
        $this->live->model()->delete((int) $id);
        $this->setFlashMessage('Aula ao vivo removida.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . $did . '/ao-vivo');
    }

    public function enter(string $id): void
    {
        $live = $this->live->model()->find((int) $id);
        if (!$live || !$this->ownsDiscipline((int) $live['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $user = $this->authManager->getUser();
        $url = $this->live->joinUrl($live, [
            'id' => (int) ($user['id'] ?? 0),
            'nome' => (string) ($user['nome'] ?? 'Professor'),
            'email' => (string) ($user['email'] ?? ''),
        ], true);
        if ($url === '') {
            $this->setFlashMessage('Sala indisponível. Verifique a configuração da plataforma ou o link externo.', 'error');
            $this->redirect(self::BASE . '/disciplinas/' . (int) $live['disciplina_id'] . '/ao-vivo');
            return;
        }
        if (($live['status'] ?? '') === 'agendada') {
            $this->live->model()->setStatus((int) $id, 'ao_vivo');
        }
        $this->redirect($url);
    }

    public function setStatus(string $id): void
    {
        $live = $this->live->model()->find((int) $id);
        if (!$this->csrfOk() || !$live || !$this->ownsDiscipline((int) $live['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $status = (string) ($_POST['status'] ?? '');
        if (isset(LiveClass::STATUS[$status])) {
            $this->live->model()->setStatus((int) $id, $status);
            $this->setFlashMessage('Status atualizado.', 'success');
        }
        $this->redirect(self::BASE . '/disciplinas/' . (int) $live['disciplina_id'] . '/ao-vivo');
    }

    public function setRecording(string $id): void
    {
        $live = $this->live->model()->find((int) $id);
        if (!$this->csrfOk() || !$live || !$this->ownsDiscipline((int) $live['disciplina_id'])) {
            $this->redirect(self::BASE);
            return;
        }
        $url = trim((string) ($_POST['gravacao_url'] ?? ''));
        $this->live->model()->setRecordingUrl((int) $id, $url ?: null);
        $this->setFlashMessage('Link da gravação atualizado.', 'success');
        $this->redirect(self::BASE . '/disciplinas/' . (int) $live['disciplina_id'] . '/ao-vivo');
    }
}
}
