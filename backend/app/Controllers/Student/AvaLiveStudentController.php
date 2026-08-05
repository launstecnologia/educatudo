<?php

require_once __DIR__ . '/../../Services/AvaCourseService.php';
require_once __DIR__ . '/../../Services/AvaLiveClassService.php';
require_once __DIR__ . '/../../Models/Ava/DisciplineEnrollment.php';

/**
 * EducaTudo - AVA: aulas ao vivo na área do aluno.
 */
if (!class_exists('AvaLiveStudentController')) {
class AvaLiveStudentController extends BaseController
{
    private $authManager;
    private AvaCourseService $service;
    private AvaLiveClassService $live;
    private DisciplineEnrollment $enrollments;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            $this->redirect('/');
        }
        $this->service = new AvaCourseService();
        $this->live = new AvaLiveClassService();
        $this->enrollments = new DisciplineEnrollment();
    }

    private function alunoId(): int
    {
        $user = $this->authManager->getUser();
        return (int) ($user['id'] ?? 0);
    }

    public function list(string $id): void
    {
        $disciplinaId = (int) $id;
        if (!$this->enrollments->isEnrolled($this->alunoId(), $disciplinaId)) {
            $this->setFlashMessage('Você não está matriculado nesta disciplina.', 'error');
            $this->redirect('/cursos');
            return;
        }
        $disc = $this->service->disciplinesModel()->find($disciplinaId);
        $aulas = $this->live->model()->byDiscipline($disciplinaId);
        foreach ($aulas as &$a) {
            $a = $this->live->syncPandaRecordingIfNeeded($a);
            $a['estado'] = $this->live->computedState($a);
            $a['tem_gravacao'] = $this->live->hasRecording($a);
        }
        unset($a);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('student', 'student/ava/ao_vivo', [
            'title' => 'Aulas ao vivo - ' . htmlspecialchars((string) $disc['nome']),
            'current_page' => 'cursos',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'disciplina' => $disc,
            'aulas' => $aulas,
        ]);
    }

    public function room(string $id): void
    {
        $live = $this->live->model()->find((int) $id);
        if (!$live || !$this->enrollments->isEnrolled($this->alunoId(), (int) $live['disciplina_id'])) {
            $this->setFlashMessage('Aula ao vivo indisponível.', 'error');
            $this->redirect('/cursos');
            return;
        }
        $live = $this->live->syncPandaRecordingIfNeeded($live);
        $estado = $this->live->computedState($live);
        $user = $this->authManager->getUser();
        $joinUrl = $this->live->joinUrl($live, [
            'id' => (int) ($user['id'] ?? 0),
            'nome' => (string) ($user['nome'] ?? 'Aluno'),
            'email' => (string) ($user['email'] ?? ''),
        ], false);

        $this->viewWithLayout('student', 'student/ava/ao_vivo_sala', [
            'title' => htmlspecialchars((string) $live['titulo']) . ' - Ao vivo',
            'current_page' => 'cursos',
            'aula' => $live,
            'estado' => $estado,
            'join_url' => $joinUrl,
            'pode_embed' => $this->live->canEmbed($live) && $estado === 'ao_vivo',
            'gravacao_url' => $this->live->recordingUrl($live),
        ]);
    }
}
}
