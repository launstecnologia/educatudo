<?php

require_once __DIR__ . '/../../Models/Ava/Lesson.php';
require_once __DIR__ . '/../../Models/Ava/LessonComment.php';
require_once __DIR__ . '/../../Models/Ava/Discipline.php';
require_once __DIR__ . '/../../Models/Ava/DisciplineEnrollment.php';

/**
 * EducaTudo - AVA: comentários/dúvidas por aula (aluno e professor).
 * Não duplica o Fórum: é uma discussão inline, restrita à aula/disciplina.
 */
if (!class_exists('AvaCommentController')) {
class AvaCommentController extends BaseController
{
    private $authManager;
    private Lesson $lessons;
    private LessonComment $comments;
    private Discipline $disciplines;
    private DisciplineEnrollment $enrollments;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->lessons = new Lesson();
        $this->comments = new LessonComment();
        $this->disciplines = new Discipline();
        $this->enrollments = new DisciplineEnrollment();
    }

    /** @return array{id:int,tipo:string,nome:string}|null */
    private function user(): ?array
    {
        $u = $this->authManager->getUser();
        if (!$u || !in_array($u['tipo'] ?? '', ['aluno', 'professor'], true)) {
            return null;
        }
        return ['id' => (int) ($u['id'] ?? 0), 'tipo' => (string) $u['tipo'], 'nome' => (string) ($u['nome'] ?? '')];
    }

    private function backToLesson(int $aulaId, string $tipo): void
    {
        if ($tipo === 'professor') {
            $this->redirect('/professor/ava/aulas/' . $aulaId . '/editar');
            return;
        }
        $this->redirect('/cursos/aula/' . $aulaId);
    }

    /** Verifica se o usuário pode interagir com a aula (matriculado ou dono da disciplina). */
    private function hasAccess(array $aula, array $user): bool
    {
        $disciplinaId = (int) $aula['disciplina_id'];
        if ($user['tipo'] === 'professor') {
            return $this->disciplines->isOwnedByTeacher($disciplinaId, $user['id']);
        }
        return $this->enrollments->isEnrolled($user['id'], $disciplinaId);
    }

    public function store(string $aulaId): void
    {
        $user = $this->user();
        $aula = $this->lessons->find((int) $aulaId);
        if (!$user || !$aula || !$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->redirect('/');
            return;
        }
        if (!$this->hasAccess($aula, $user)) {
            $this->backToLesson((int) $aulaId, $user['tipo']);
            return;
        }
        if (empty($aula['permite_comentarios'])) {
            $this->setFlashMessage('Os comentários estão desativados nesta aula.', 'info');
            $this->backToLesson((int) $aulaId, $user['tipo']);
            return;
        }
        $conteudo = trim((string) ($_POST['conteudo'] ?? ''));
        $parentId = (int) ($_POST['parent_id'] ?? 0) ?: null;
        if ($conteudo !== '') {
            if ($parentId) {
                $pai = $this->comments->find($parentId);
                if (!$pai || (int) $pai['aula_id'] !== (int) $aula['id']) {
                    $parentId = null;
                }
            }
            $this->comments->create((int) $aula['id'], $user['tipo'], $user['id'], $user['nome'], $conteudo, $parentId);
            $this->setFlashMessage('Comentário publicado.', 'success');
        }
        $this->backToLesson((int) $aulaId, $user['tipo']);
    }

    public function delete(string $id): void
    {
        $user = $this->user();
        $comentario = $this->comments->find((int) $id);
        if (!$user || !$comentario || !$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->redirect('/');
            return;
        }
        $aula = $this->lessons->find((int) $comentario['aula_id']);
        if (!$aula) {
            $this->redirect('/');
            return;
        }
        $ehAutor = (int) $comentario['autor_id'] === $user['id'] && $comentario['autor_tipo'] === $user['tipo'];
        $ehDono = $user['tipo'] === 'professor' && $this->disciplines->isOwnedByTeacher((int) $aula['disciplina_id'], $user['id']);
        if (!$ehAutor && !$ehDono) {
            $this->backToLesson((int) $aula['id'], $user['tipo']);
            return;
        }
        $this->comments->softDelete((int) $id);
        $this->setFlashMessage('Comentário removido.', 'success');
        $this->backToLesson((int) $aula['id'], $user['tipo']);
    }

    public function pin(string $id): void
    {
        $user = $this->user();
        $comentario = $this->comments->find((int) $id);
        if (!$user || $user['tipo'] !== 'professor' || !$comentario || !$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->redirect('/');
            return;
        }
        $aula = $this->lessons->find((int) $comentario['aula_id']);
        if (!$aula || !$this->disciplines->isOwnedByTeacher((int) $aula['disciplina_id'], $user['id'])) {
            $this->redirect('/professor/ava');
            return;
        }
        $this->comments->togglePin((int) $id, empty($comentario['fixado']));
        $this->backToLesson((int) $aula['id'], 'professor');
    }
}
}
