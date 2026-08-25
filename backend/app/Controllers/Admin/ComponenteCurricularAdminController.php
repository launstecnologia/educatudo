<?php
/**
 * EducaTudo - ComponenteCurricularAdminController
 * CRUD dos componentes curriculares da escola (antigo módulo "Matérias").
 *
 * A tabela física continua `materias` — só a camada de aplicação foi
 * renomeada (ver .claude/docs/nomenclatura.md e a migration
 * 2026_08_18_componentes_curriculares_campos.sql).
 */

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Models/Education/ComponenteCurricular.php';
require_once __DIR__ . '/../../Services/ComponenteCurricularService.php';

if (!class_exists('ComponenteCurricularAdminController')) {
class ComponenteCurricularAdminController extends AdminBaseController
{
    private function model(): ComponenteCurricular
    {
        return new ComponenteCurricular();
    }

    private function service(): ComponenteCurricularService
    {
        return new ComponenteCurricularService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('materias', 'visualizar', false)) {
            return;
        }

        $data = [
            'title' => 'Componentes Curriculares - EducaTudo',
            'items' => $this->model()->getAll(),
            'areas_conhecimento' => ComponenteCurricular::AREAS_CONHECIMENTO,
            'tipos' => ComponenteCurricular::TIPOS,
            'etapas' => ComponenteCurricular::ETAPAS,
            'user' => $this->auth->getUser(),
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'componentes-curriculares',
        ];

        $this->viewWithLayout('admin', 'admin/componentes-curriculares/index', $data);
    }

    public function dados($id): void
    {
        if (!$this->enforceAdminPermissionKey('materias', 'visualizar', true)) {
            return;
        }

        $item = $this->model()->findById((int) $id);
        if (!$item) {
            $this->json(['error' => 'Componente curricular não encontrado'], 404);
            return;
        }

        $this->json(['success' => true, 'item' => $item]);
    }

    public function store(): void
    {
        if (!$this->enforceAdminPermissionKey('materias', 'cadastrar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        $result = $this->service()->criar($_POST);
        if (!$result['success']) {
            $this->json(['error' => $result['error']], 400);
            return;
        }

        $this->json(['success' => true, 'message' => 'Componente curricular cadastrado com sucesso', 'id' => $result['id']]);
    }

    public function update($id): void
    {
        if (!$this->enforceAdminPermissionKey('materias', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        $result = $this->service()->atualizar((int) $id, $_POST);
        if (!$result['success']) {
            $this->json(['error' => $result['error']], 400);
            return;
        }

        $this->json(['success' => true, 'message' => 'Componente curricular atualizado com sucesso']);
    }

    public function delete($id): void
    {
        if (!$this->enforceAdminPermissionKey('materias', 'excluir', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $model = $this->model();
            if (!$model->exists((int) $id)) {
                throw new \RuntimeException('Componente curricular não encontrado');
            }

            $vinculos = $this->countVinculos((int) $id);
            if ($vinculos > 0) {
                throw new \RuntimeException(
                    "Não é possível excluir: há {$vinculos} registro(s) vinculado(s) (provas, grade horária ou planos de aula). " .
                    'Marque como "Inativo" em vez de excluir.'
                );
            }

            $model->delete((int) $id);
            $this->json(['success' => true, 'message' => 'Componente curricular excluído com sucesso']);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Conta registros dependentes nas tabelas core que fazem CASCADE em `materia_id`
     * (excluir apagaria os registros abaixo em cascata, silenciosamente, via FK do MySQL).
     * Lista precisa ficar em sincronia com as constraints `ON DELETE CASCADE` de
     * `materia_id` em database/migrations/educatudo.sql — não inclui as que são
     * `ON DELETE SET NULL` (cadernos_aluno, provas_blocos), pois essas não perdem dado.
     */
    private function countVinculos(int $id): int
    {
        $tabelas = [
            'provas',
            'provas_professores',
            'provas_blocos_professores',
            'provas_blocos_modelos_professores',
            'grade_horaria',
            'planos_aula',
            'modulos_arquivos',
            'matrizes_curriculares_componentes',
        ];
        $total = 0;

        foreach ($tabelas as $tabela) {
            try {
                $result = $this->db->fetch(
                    "SELECT COUNT(*) as total FROM {$tabela} WHERE materia_id = :id",
                    ['id' => $id]
                );
                $total += (int) ($result['total'] ?? 0);
            } catch (\Throwable $e) {
                // Tabela pode não existir em instâncias antigas — ignora nesse caso.
            }
        }

        return $total;
    }
}
}
