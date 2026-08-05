<?php

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Services/AvaCourseService.php';
require_once __DIR__ . '/../../Services/AvaEnrollmentService.php';
require_once __DIR__ . '/../../Services/AvaProgressService.php';
require_once __DIR__ . '/../../Services/AvaEvaluationService.php';

/**
 * EducaTudo - AVA: gestão de disciplinas e matrículas (admin).
 */
if (!class_exists('AvaDisciplineAdminController')) {
class AvaDisciplineAdminController extends AdminBaseController
{
    private AvaCourseService $service;
    private AvaEnrollmentService $enrollment;
    private AvaProgressService $progress;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AvaCourseService();
        $this->enrollment = new AvaEnrollmentService();
        $this->progress = new AvaProgressService();
    }

    public function store(): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'cadastrar', false)) {
            return;
        }
        $cursoId = (int) ($_POST['curso_id'] ?? 0);
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/ava/cursos/' . $cursoId);
            return;
        }
        $nome = trim((string) ($_POST['nome'] ?? ''));
        if ($nome === '' || $cursoId <= 0) {
            $this->setFlashMessage('Informe o nome da disciplina.', 'error');
            $this->redirect('/admin/ava/cursos/' . $cursoId);
            return;
        }
        $id = $this->service->disciplinesModel()->save($_POST);
        $this->setFlashMessage('Disciplina criada.', 'success');
        $this->redirect('/admin/ava/disciplinas/' . $id);
    }

    /** Página própria de edição dos dados da disciplina (um cadastro por tela). */
    public function edit(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'alterar', false)) {
            return;
        }
        $disc = $this->service->disciplinesModel()->find((int) $id);
        if (!$disc) {
            $this->setFlashMessage('Disciplina não encontrada.', 'error');
            $this->redirect('/admin/ava');
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ava/disciplina_form', [
            'title' => 'Editar disciplina - ' . htmlspecialchars($disc['nome']),
            'user' => $this->auth->getUser(),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'disciplina' => $disc,
            'professores' => $this->lista('SELECT id, nome FROM professores WHERE ativo = 1 OR ativo IS NULL ORDER BY nome'),
            'turmas' => $this->lista('SELECT id, nome FROM turmas ORDER BY nome'),
            'semestres' => $this->service->semestersModel()->byCourse((int) $disc['curso_id']),
            'status_opcoes' => Discipline::STATUS,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function update(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/ava/disciplinas/' . (int) $id);
            return;
        }
        $this->service->disciplinesModel()->save($_POST, (int) $id);
        $this->setFlashMessage('Disciplina atualizada.', 'success');
        $this->redirect('/admin/ava/disciplinas/' . (int) $id);
    }

    public function delete(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'excluir', false)) {
            return;
        }
        $disc = $this->service->disciplinesModel()->find((int) $id);
        $cursoId = (int) ($disc['curso_id'] ?? 0);
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->redirect('/admin/ava/disciplinas/' . (int) $id);
            return;
        }
        $this->service->disciplinesModel()->delete((int) $id);
        $this->setFlashMessage('Disciplina excluída.', 'success');
        $this->redirect('/admin/ava/cursos/' . $cursoId);
    }

    /** Página de gestão da disciplina: módulos/aulas + alunos. */
    public function show(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'visualizar', false)) {
            return;
        }
        $disc = $this->service->disciplinesModel()->find((int) $id);
        if (!$disc) {
            $this->setFlashMessage('Disciplina não encontrada.', 'error');
            $this->redirect('/admin/ava');
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ava/disciplina', [
            'title' => htmlspecialchars($disc['nome']) . ' - AVA',
            'user' => $this->auth->getUser(),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'disciplina' => $disc,
            'outline' => $this->service->disciplineOutline((int) $id),
            'resumo' => $this->progress->teacherDisciplineSummary((int) $id),
            'tipos_aula' => Lesson::TIPOS,
            'professores' => $this->lista('SELECT id, nome FROM professores WHERE ativo = 1 OR ativo IS NULL ORDER BY nome'),
            'turmas' => $this->lista('SELECT id, nome FROM turmas ORDER BY nome'),
            'semestres' => $this->service->semestersModel()->byCourse((int) $disc['curso_id']),
            'status_opcoes' => Discipline::STATUS,
            'base_url' => '/admin/ava',
            'is_admin' => true,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    // ---- Avaliações (vínculo com prova) -----------------------------------

    /** Página própria de avaliações da disciplina (vincular prova + liberar por progresso). */
    public function avaliacoes(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'visualizar', false)) {
            return;
        }
        $disc = $this->service->disciplinesModel()->find((int) $id);
        if (!$disc) {
            $this->setFlashMessage('Disciplina não encontrada.', 'error');
            $this->redirect('/admin/ava');
            return;
        }
        $svc = new AvaEvaluationService();
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ava/avaliacoes', [
            'title' => 'Avaliações - ' . htmlspecialchars($disc['nome']),
            'user' => $this->auth->getUser(),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'disciplina' => $disc,
            'avaliacoes' => $svc->listForDiscipline((int) $id),
            'provas_disponiveis' => $svc->availableProvas((int) $id),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function storeAvaliacao(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/ava/disciplinas/' . (int) $id . '/avaliacoes');
            return;
        }
        $provaId = (int) ($_POST['prova_id'] ?? 0);
        if ($provaId <= 0) {
            $this->setFlashMessage('Selecione uma prova para vincular.', 'error');
            $this->redirect('/admin/ava/disciplinas/' . (int) $id . '/avaliacoes');
            return;
        }
        (new AvaEvaluationService())->attach([
            'disciplina_id' => (int) $id,
            'prova_id' => $provaId,
            'titulo' => trim((string) ($_POST['titulo'] ?? '')),
            'requisito_progresso_pct' => (float) ($_POST['requisito_progresso_pct'] ?? 80),
            'obrigatoria' => isset($_POST['obrigatoria']) ? 1 : 0,
            'peso' => (float) ($_POST['peso'] ?? 1),
        ]);
        $this->setFlashMessage('Avaliação vinculada à disciplina.', 'success');
        $this->redirect('/admin/ava/disciplinas/' . (int) $id . '/avaliacoes');
    }

    public function deleteAvaliacao(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'excluir', false)) {
            return;
        }
        $disciplinaId = (int) ($_POST['disciplina_id'] ?? 0);
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->redirect('/admin/ava/disciplinas/' . $disciplinaId . '/avaliacoes');
            return;
        }
        (new AvaEvaluationService())->detach((int) $id);
        $this->setFlashMessage('Avaliação removida.', 'success');
        $this->redirect('/admin/ava/disciplinas/' . $disciplinaId . '/avaliacoes');
    }

    // ---- Matrículas -------------------------------------------------------

    public function syncTurma(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->redirect('/admin/ava/disciplinas/' . (int) $id);
            return;
        }
        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        $n = $turmaId > 0
            ? $this->enrollment->syncFromTurma((int) $id, $turmaId)
            : $this->enrollment->syncDisciplineFromErp((int) $id);
        $this->setFlashMessage($n > 0 ? "$n aluno(s) matriculado(s)." : 'Nenhum aluno encontrado para matricular.', $n > 0 ? 'success' : 'info');
        $this->redirect('/admin/ava/disciplinas/' . (int) $id);
    }

    public function unenroll(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'excluir', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->redirect('/admin/ava/disciplinas/' . (int) $id);
            return;
        }
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        if ($alunoId > 0) {
            $this->enrollment->unenroll($alunoId, (int) $id);
            $this->setFlashMessage('Matrícula cancelada.', 'success');
        }
        $this->redirect('/admin/ava/disciplinas/' . (int) $id);
    }

    /** @return list<array<string,mixed>> */
    private function lista(string $sql): array
    {
        try {
            return $this->db->fetchAll($sql) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
}
