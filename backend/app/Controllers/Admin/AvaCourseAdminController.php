<?php

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Services/AvaCourseService.php';

/**
 * EducaTudo - AVA: gestão de cursos, categorias e semestres (admin).
 */
if (!class_exists('AvaCourseAdminController')) {
class AvaCourseAdminController extends AdminBaseController
{
    private AvaCourseService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AvaCourseService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'visualizar', false)) {
            return;
        }
        if (!$this->service->coursesModel()->tableExists()) {
            $this->renderSchemaPendente();
            return;
        }
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ava/index', [
            'title' => 'AVA / EAD - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'cursos' => $this->service->listCourses($busca, $status),
            'categorias' => $this->service->categoriesModel()->all(),
            'modalidades' => Course::MODALIDADES,
            'status_opcoes' => Course::STATUS,
            'busca' => $busca,
            'status' => $status,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function create(): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'cadastrar', false)) {
            return;
        }
        $this->renderForm(null);
    }

    public function edit(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'alterar', false)) {
            return;
        }
        $curso = $this->service->getCourse((int) $id);
        if (!$curso) {
            $this->setFlashMessage('Curso não encontrado.', 'error');
            $this->redirect('/admin/ava');
            return;
        }
        $this->renderForm($curso);
    }

    private function renderForm(?array $curso): void
    {
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ava/curso_form', [
            'title' => ($curso ? 'Editar Curso' : 'Novo Curso') . ' - AVA',
            'user' => $this->auth->getUser(),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'curso' => $curso,
            'categorias' => $this->service->categoriesModel()->all(),
            'modalidades' => Course::MODALIDADES,
            'status_opcoes' => Course::STATUS,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function store(): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/ava/cursos/novo');
            return;
        }
        $nome = trim((string) ($_POST['nome'] ?? ''));
        if ($nome === '') {
            $this->setFlashMessage('Informe o nome do curso.', 'error');
            $this->redirect('/admin/ava/cursos/novo');
            return;
        }
        $user = $this->auth->getUser();
        $data = $_POST;
        $data['created_by'] = (int) ($user['id'] ?? 0);
        $id = $this->service->coursesModel()->save($data);
        $this->setFlashMessage('Curso criado com sucesso.', 'success');
        $this->redirect('/admin/ava/cursos/' . $id);
    }

    public function update(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'alterar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/ava/cursos/' . (int) $id . '/editar');
            return;
        }
        $this->service->coursesModel()->save($_POST, (int) $id);
        $this->setFlashMessage('Curso atualizado.', 'success');
        $this->redirect('/admin/ava/cursos/' . (int) $id);
    }

    public function delete(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'excluir', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/ava');
            return;
        }
        $this->service->coursesModel()->delete((int) $id);
        $this->setFlashMessage('Curso excluído.', 'success');
        $this->redirect('/admin/ava');
    }

    /** Página de gestão do curso: dados, semestres e disciplinas. */
    public function show(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'visualizar', false)) {
            return;
        }
        $curso = $this->service->getCourse((int) $id);
        if (!$curso) {
            $this->setFlashMessage('Curso não encontrado.', 'error');
            $this->redirect('/admin/ava');
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ava/curso', [
            'title' => htmlspecialchars($curso['nome']) . ' - AVA',
            'user' => $this->auth->getUser(),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'curso' => $curso,
            'semestres' => $this->service->semestersModel()->byCourse((int) $id),
            'disciplinas' => $this->service->disciplinesModel()->byCourse((int) $id),
            'professores' => $this->listarProfessores(),
            'turmas' => $this->listarTurmas(),
            'materias' => $this->listarMaterias(),
            'status_opcoes' => Discipline::STATUS,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    /** Página própria de períodos/semestres de um curso (um cadastro por tela). */
    public function periodos(string $cursoId): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'visualizar', false)) {
            return;
        }
        $curso = $this->service->getCourse((int) $cursoId);
        if (!$curso) {
            $this->setFlashMessage('Curso não encontrado.', 'error');
            $this->redirect('/admin/ava');
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ava/periodos', [
            'title' => 'Períodos - ' . htmlspecialchars($curso['nome']),
            'user' => $this->auth->getUser(),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'curso' => $curso,
            'semestres' => $this->service->semestersModel()->byCourse((int) $cursoId),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    // ---- Categorias -------------------------------------------------------

    public function categorias(): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'visualizar', false)) {
            return;
        }
        if (!$this->service->coursesModel()->tableExists()) {
            $this->renderSchemaPendente();
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/ava/categorias', [
            'title' => 'Categorias - AVA',
            'user' => $this->auth->getUser(),
            'current_page' => 'ava',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'categorias' => $this->service->categoriesModel()->all(),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function storeCategoria(): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/ava/categorias');
            return;
        }
        $nome = trim((string) ($_POST['nome'] ?? ''));
        if ($nome !== '') {
            $this->service->categoriesModel()->save($nome, trim((string) ($_POST['descricao'] ?? '')) ?: null);
            $this->setFlashMessage('Categoria salva.', 'success');
        } else {
            $this->setFlashMessage('Informe o nome da categoria.', 'error');
        }
        $this->redirect('/admin/ava/categorias');
    }

    public function deleteCategoria(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'excluir', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/ava/categorias');
            return;
        }
        $this->service->categoriesModel()->delete((int) $id);
        $this->setFlashMessage('Categoria removida.', 'success');
        $this->redirect('/admin/ava/categorias');
    }

    // ---- Semestres --------------------------------------------------------

    public function storeSemestre(string $cursoId): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada.', 'error');
            $this->redirect('/admin/ava/cursos/' . (int) $cursoId . '/periodos');
            return;
        }
        $nome = trim((string) ($_POST['nome'] ?? ''));
        if ($nome !== '') {
            $this->service->semestersModel()->save(
                (int) $cursoId,
                $nome,
                (int) ($_POST['ordem'] ?? 0),
                trim((string) ($_POST['data_inicio'] ?? '')) ?: null,
                trim((string) ($_POST['data_fim'] ?? '')) ?: null
            );
            $this->setFlashMessage('Período salvo.', 'success');
        } else {
            $this->setFlashMessage('Informe o nome do período.', 'error');
        }
        $this->redirect('/admin/ava/cursos/' . (int) $cursoId . '/periodos');
    }

    public function deleteSemestre(string $id): void
    {
        if (!$this->enforceAdminPermissionKey('ava', 'excluir', false)) {
            return;
        }
        $cursoId = (int) ($_POST['curso_id'] ?? 0);
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->redirect('/admin/ava/cursos/' . $cursoId . '/periodos');
            return;
        }
        $this->service->semestersModel()->delete((int) $id);
        $this->setFlashMessage('Período removido.', 'success');
        $this->redirect('/admin/ava/cursos/' . $cursoId . '/periodos');
    }

    // ---- Helpers ----------------------------------------------------------

    /** @return list<array<string,mixed>> */
    private function listarProfessores(): array
    {
        try {
            return $this->db->fetchAll("SELECT id, nome FROM professores WHERE ativo = 1 OR ativo IS NULL ORDER BY nome") ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    private function listarTurmas(): array
    {
        try {
            return $this->db->fetchAll("SELECT id, nome FROM turmas ORDER BY nome") ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    private function listarMaterias(): array
    {
        foreach (['materias', 'subjects'] as $tabela) {
            try {
                $rows = $this->db->fetchAll("SELECT id, nome FROM $tabela ORDER BY nome");
                if ($rows) {
                    return $rows;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
        return [];
    }

    private function renderSchemaPendente(): void
    {
        $this->viewWithLayout('admin', 'admin/ava/schema_pendente', [
            'title' => 'AVA / EAD - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'ava',
        ]);
    }
}
}
