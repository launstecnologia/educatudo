<?php
/**
 * EducaTudo - MatrizCurricularAdminController
 * CRUD da Matriz Curricular: o que cada série de um curso deve cursar
 * (Componentes Curriculares vinculados, aulas/semana, obrigatoriedade).
 *
 * Criar/editar em offcanvas único no index (decisão de produto — a tabela
 * dinâmica de Componentes cabe no painel lateral); listagem/filtro/exclusão
 * seguem como antes.
 */

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Models/Education/MatrizCurricular.php';
require_once __DIR__ . '/../../Models/Education/ClassRoom.php';
require_once __DIR__ . '/../../Models/Education/ComponenteCurricular.php';
require_once __DIR__ . '/../../Services/MatrizCurricularService.php';

if (!class_exists('MatrizCurricularAdminController')) {
class MatrizCurricularAdminController extends AdminBaseController
{
    private function model(): MatrizCurricular
    {
        return new MatrizCurricular();
    }

    private function service(): MatrizCurricularService
    {
        return new MatrizCurricularService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('matriz_curricular', 'visualizar', false)) {
            return;
        }

        $filtros = [
            'curso_id' => $_GET['curso_id'] ?? '',
            'serie_id' => $_GET['serie_id'] ?? '',
        ];

        $classRoom = new ClassRoom();
        $formulario = $this->dadosDoFormulario();

        $data = array_merge($formulario, [
            'title' => 'Matriz Curricular - EducaTudo',
            'current_page' => 'matriz-curricular',
            'itens' => $this->model()->getAll(array_filter($filtros, fn ($v) => $v !== '')),
            'cursos' => $classRoom->getCursosNovo(),
            'series' => $classRoom->getSeriesNovo(),
            'filtros' => $filtros,
            'status' => $_GET['status'] ?? '',
            'message' => $_GET['message'] ?? '',
        ]);

        $this->viewWithLayout('admin', 'admin/matrizes-curriculares/index', $data);
    }

    /**
     * Dados de uma Matriz Curricular (JSON) para popular o offcanvas de edição
     */
    public function dados($id): void
    {
        if (!$this->enforceAdminPermissionKey('matriz_curricular', 'alterar', true)) {
            return;
        }

        $item = $this->model()->findById((int) $id);
        if (!$item) {
            $this->json(['error' => 'Matriz Curricular não encontrada.'], 404);
            return;
        }

        $componentes = array_map(function ($c) {
            return [
                'materia_id' => (int) $c['materia_id'],
                'aulas_semana' => (int) $c['aulas_semana'],
                'obrigatorio' => (int) $c['obrigatorio'],
                'ordem_boletim' => (int) $c['ordem_boletim'],
            ];
        }, $this->model()->getComponentes((int) $id));

        $this->json(['success' => true, 'item' => $item, 'componentes' => $componentes]);
    }

    public function store(): void
    {
        if (!$this->enforceAdminPermissionKey('matriz_curricular', 'cadastrar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }

        $result = $this->service()->criar($_POST);
        if (!$result['success']) {
            $this->json(['error' => $result['error']], 400);
            return;
        }

        $this->json(['success' => true, 'message' => 'Matriz Curricular cadastrada com sucesso.']);
    }

    public function update($id): void
    {
        if (!$this->enforceAdminPermissionKey('matriz_curricular', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }

        $result = $this->service()->atualizar((int) $id, $_POST);
        if (!$result['success']) {
            $this->json(['error' => $result['error']], 400);
            return;
        }

        $this->json(['success' => true, 'message' => 'Matriz Curricular atualizada com sucesso.']);
    }

    public function delete($id): void
    {
        if (!$this->enforceAdminPermissionKey('matriz_curricular', 'excluir', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        $result = $this->service()->excluir((int) $id);
        if (!$result['success']) {
            $this->json(['error' => $result['error']], 400);
            return;
        }

        $this->json(['success' => true, 'message' => 'Matriz Curricular excluída com sucesso']);
    }

    /**
     * Dados comuns aos formulários de criar/editar (dropdowns).
     */
    private function dadosDoFormulario(): array
    {
        $classRoom = new ClassRoom();
        $componenteCurricular = new ComponenteCurricular();

        $series = $classRoom->getSeriesNovo();
        $seriesPorCurso = [];
        foreach ($series as $s) {
            $seriesPorCurso[(int) $s['curso_id']][] = $s;
        }

        return [
            'title' => 'Cadastrar Matriz Curricular - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'matriz-curricular',
            'cursos' => $classRoom->getCursosNovo(),
            'series' => $series,
            'series_por_curso' => $seriesPorCurso,
            'componentes_disponiveis' => $componenteCurricular->getAll(true),
            'csrf_token' => $this->generateCsrfToken(),
            'status' => $_GET['status'] ?? '',
            'message' => $_GET['message'] ?? '',
        ];
    }
}
}
