<?php
/**
 * EducaTudo - SchoolUnitsAdminController
 * CRUD das unidades da escola (matriz/filial) e seus dados institucionais,
 * usados no cabeçalho das declarações oficiais.
 */

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Models/Education/SchoolUnit.php';

if (!class_exists('SchoolUnitsAdminController')) {
class SchoolUnitsAdminController extends AdminBaseController
{
    private function model(): SchoolUnit
    {
        return new SchoolUnit();
    }

    private function auditarUnidade(string $action, ?int $unidadeId, array $extra = []): void
    {
        try {
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../../Core/Logger.php';
            }
            $user = $this->auth->getUser();
            \Logger::logAudit(
                $action,
                '/admin/unidades/' . ($unidadeId !== null ? $unidadeId : ''),
                array_merge(['unidade_id' => $unidadeId], $extra),
                $user['id'] ?? null,
                $user['tipo'] ?? null
            );
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('unidades', 'visualizar', false)) {
            return;
        }

        $model = $this->model();
        $unidades = $model->getAll();
        foreach ($unidades as &$u) {
            $u['total_alunos'] = $model->countStudents((int) $u['id']);
        }
        unset($u);

        $flash = $this->getFlashMessage();
        $data = [
            'title' => 'Unidades da Escola - EducaTudo',
            'units' => $unidades,
            'schema_ready' => $model->tableExists(),
            'user' => $this->auth->getUser(),
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'unidades',
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
        ];

        $this->viewWithLayout('admin', 'admin/units/index', $data);
    }

    public function store(): void
    {
        if (!$this->enforceAdminPermissionKey('unidades', 'cadastrar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        try {
            $dados = $this->extractPost();
            $id = $this->model()->create($dados);
            $this->auditarUnidade('CREATE_UNIT', (int) $id, ['nome' => $dados['nome'] ?? null]);
            $this->json(['success' => true, 'message' => 'Unidade cadastrada com sucesso', 'id' => $id]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function dados($id): void
    {
        if (!$this->enforceAdminPermissionKey('unidades', 'visualizar', true)) {
            return;
        }
        $unit = $this->model()->findById((int) $id);
        if (!$unit) {
            $this->json(['error' => 'Unidade não encontrada'], 404);
            return;
        }
        $this->json(['success' => true, 'unit' => $unit]);
    }

    public function update($id): void
    {
        if (!$this->enforceAdminPermissionKey('unidades', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        try {
            $unit = $this->model()->findById((int) $id);
            if (!$unit) {
                throw new \RuntimeException('Unidade não encontrada');
            }
            $this->model()->update((int) $id, $this->extractPost());
            $this->auditarUnidade('UPDATE_UNIT', (int) $id);
            $this->json(['success' => true, 'message' => 'Unidade atualizada com sucesso']);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function delete($id): void
    {
        if (!$this->enforceAdminPermissionKey('unidades', 'excluir', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        try {
            $model = $this->model();
            $unit = $model->findById((int) $id);
            if (!$unit) {
                throw new \RuntimeException('Unidade não encontrada');
            }
            $vinculados = $model->countStudents((int) $id);
            if ($vinculados > 0) {
                throw new \RuntimeException("Não é possível excluir: {$vinculados} aluno(s) vinculado(s) a esta unidade.");
            }
            $model->delete((int) $id);
            $this->auditarUnidade('DELETE_UNIT', (int) $id, ['nome' => $unit['nome'] ?? null]);
            $this->json(['success' => true, 'message' => 'Unidade excluída com sucesso']);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPost(): array
    {
        $fields = [
            'nome', 'tipo', 'razao_social', 'cnpj', 'inep', 'dependencia_administrativa',
            'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'telefone',
            'email', 'diretor_nome', 'secretario_nome',
            'ato_autorizacao', 'ato_credenciamento', 'ato_reconhecimento',
            'diretor_registro', 'secretario_registro',
            'logo_url',
        ];
        $out = [];
        foreach ($fields as $f) {
            $out[$f] = trim((string) ($_POST[$f] ?? ''));
        }
        $out['ativo'] = isset($_POST['ativo']) ? 1 : 0;
        return $out;
    }
}
}
