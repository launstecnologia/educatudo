<?php
/**
 * Admin — cadastro de Quadro de Notas.
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Services/GrupoRegrasNotasService.php';

if (!class_exists('GrupoRegrasNotasAdminController')) {
class GrupoRegrasNotasAdminController extends AdminBaseController
{
    /** @var GrupoRegrasNotasService */
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new GrupoRegrasNotasService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('grupos_regras_notas', 'visualizar', false)) {
            return;
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/grupos-regras-notas/index', [
            'title' => 'Quadro de Notas — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'grupos-regras-notas',
            'itens' => $this->service->model()->listar(),
            'schema_pronto' => $this->service->model()->tabelasProntas(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function novo(): void
    {
        if (!$this->enforceAdminPermissionKey('grupos_regras_notas', 'cadastrar', false)) {
            return;
        }
        $this->renderFormulario(null);
    }

    public function editar($id): void
    {
        if (!$this->enforceAdminPermissionKey('grupos_regras_notas', 'alterar', false)) {
            return;
        }
        $item = $this->service->carregarCompleto((int) $id);
        if ($item === null) {
            $this->setFlashMessage('Quadro não encontrado.', 'error');
            $this->redirect('/admin/grupos-regras-notas');
            return;
        }
        $item['modo'] = $this->service->modoDoGrupo($item);
        $this->renderFormulario($item);
    }

    public function salvar(): void
    {
        if (!$this->enforceAdminPermissionKey('grupos_regras_notas', 'cadastrar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/grupos-regras-notas/novo');
            return;
        }
        $result = $this->service->salvar($_POST, null);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível salvar.', 'error');
            $this->redirect('/admin/grupos-regras-notas/novo');
            return;
        }
        $this->redirecionarAposSalvar(true);
    }

    public function atualizar($id): void
    {
        if (!$this->enforceAdminPermissionKey('grupos_regras_notas', 'alterar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/grupos-regras-notas/' . (int) $id . '/editar');
            return;
        }
        $result = $this->service->salvar($_POST, (int) $id);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível atualizar.', 'error');
            $this->redirect('/admin/grupos-regras-notas/' . (int) $id . '/editar');
            return;
        }
        $this->redirecionarAposSalvar(false);
    }

    public function excluir($id): void
    {
        if (!$this->enforceAdminPermissionKey('grupos_regras_notas', 'excluir', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/grupos-regras-notas');
            return;
        }
        if ($this->service->model()->findById((int) $id) === null) {
            $this->setFlashMessage('Quadro não encontrado.', 'error');
            $this->redirect('/admin/grupos-regras-notas');
            return;
        }
        $result = $this->service->excluirGrupo((int) $id);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível excluir.', 'error');
            $this->redirect('/admin/grupos-regras-notas');
            return;
        }
        $this->setFlashMessage('Quadro de notas excluído.', 'success');
        $this->redirect('/admin/grupos-regras-notas');
    }

    public function dadosJson($id): void
    {
        $user = $this->auth->getUser();
        if (!class_exists('AdminPermissionMatrix')) {
            require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
        }
        $permissions = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);
        $pode = AdminPermissionMatrix::can($permissions, 'grupos_regras_notas', 'visualizar')
            || AdminPermissionMatrix::can($permissions, 'configuracao_boletim', 'visualizar');
        if (!$pode) {
            $this->json(['ok' => false, 'error' => 'Sem permissão para esta ação.'], 403);
            return;
        }
        $payload = $this->service->payloadPublico((int) $id);
        if ($payload === null) {
            $this->json(['ok' => false, 'error' => 'Grupo não encontrado.'], 404);
            return;
        }
        $this->json(['ok' => true, 'grupo' => $payload]);
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    private function listarTiposNota(): array
    {
        try {
            require_once dirname(__DIR__, 3) . '/Models/Exams/ExamEvaluationType.php';
            $rows = (new ExamEvaluationType())->getAllActive();
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = ['id' => $id, 'nome' => (string) ($r['nome'] ?? '')];
        }
        return $out;
    }

    /**
     * @param array<string,mixed>|null $item
     */
    private function renderFormulario(?array $item): void
    {
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/grupos-regras-notas/form', [
            'title' => ($item ? 'Editar' : 'Novo') . ' quadro de notas — EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'grupos-regras-notas',
            'item' => $item,
            'materias' => $this->service->model()->listarMaterias(),
            'schema_pronto' => $this->service->model()->tabelasProntas(),
            'numero_max' => GrupoRegrasNotas::NUMERO_MAX,
            'tipos_nota' => $this->listarTiposNota(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    private function redirecionarAposSalvar(bool $ehNovo): void
    {
        $this->setFlashMessage($ehNovo ? 'Quadro de notas cadastrado.' : 'Quadro de notas atualizado.', 'success');
        $this->redirect('/admin/grupos-regras-notas');
    }
}
}
