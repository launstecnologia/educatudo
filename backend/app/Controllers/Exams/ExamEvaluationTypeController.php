<?php
/**
 * EducaTudo - CRUD Tipo de Nota (Provas Online)
 */

require_once __DIR__ . '/../../Models/Exams/ExamEvaluationType.php';
require_once __DIR__ . '/../../Services/TipoNotaRegraService.php';

if (!class_exists('ExamEvaluationTypeController')) {
class ExamEvaluationTypeController extends BaseController
{
    private $authManager;
    private $tipoModel;
    private TipoNotaRegraService $regras;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->tipoModel = new ExamEvaluationType();
        $this->regras = new TipoNotaRegraService();

        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
            return;
        }

        $user = $this->authManager->getUser();
        if (!$user || ($user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola')) {
            $this->redirect('/admin/dashboard');
            return;
        }

        if (!class_exists('AdminSecretariaAccess')) {
            require_once __DIR__ . '/../../Core/AdminSecretariaAccess.php';
        }
        if ($user['tipo'] === 'admin_escola' && !in_array($user['perfil_admin'] ?? '', AdminSecretariaAccess::perfisAdminEscolaGestaoPedagogica(), true)) {
            $this->redirect('/admin/dashboard');
            return;
        }
    }

    private function dadosLayout(array $extra = []): array
    {
        $flash = $this->getFlashMessage();
        return array_merge([
            'user' => $this->authManager->getUser(),
            'current_page' => 'tipos_avaliacao',
            'page_title' => 'Tipos de Nota',
            'csrf_token' => $this->generateCsrfToken(),
            'tem_regras' => $this->tipoModel->temColunasRegras(),
            'origens' => TipoNotaRegraService::ORIGENS,
            'registros' => TipoNotaRegraService::REGISTROS,
            'criterios' => TipoNotaRegraService::CRITERIOS,
            'criterios_professores' => TipoNotaRegraService::CRITERIOS_PROFESSORES,
            'flash_status' => ($flash['type'] ?? '') === 'error' ? 'error' : (($flash['message'] ?? null) ? 'success' : ''),
            'flash_message' => (string) ($flash['message'] ?? ''),
        ], $extra);
    }

    public function index()
    {
        $tipos = $this->tipoModel->getAll();
        foreach ($tipos as $i => $tipo) {
            $tipos[$i]['rotulo_calculo'] = $this->regras->rotuloCalculo(is_array($tipo) ? $tipo : []);
        }
        $this->viewWithLayout('admin', 'admin/exams/evaluation-types/index', $this->dadosLayout([
            'title' => 'Tipos de Nota - EducaTudo',
            'tipos' => $tipos,
        ]));
    }

    public function criar()
    {
        $this->viewWithLayout('admin', 'admin/exams/evaluation-types/create', $this->dadosLayout([
            'title' => 'Novo Tipo de Nota - EducaTudo',
            'page_title' => 'Novo Tipo de Nota',
            'tipo' => [
                'origem' => 'lancamento_direto',
                'registro_evento' => 'nota',
                'criterio_fechamento' => 'ultima',
                'escala_max' => 10,
                'ativo' => 1,
            ],
        ]));
    }

    public function salvar()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/provas/tipos-avaliacao/criar');
            return;
        }
        try {
            $data = $this->regras->normalizar($_POST);
            if ($data['nome'] === '') {
                $this->setFlashMessage('Nome é obrigatório.', 'error');
                $this->redirect('/admin/provas/tipos-avaliacao/criar');
                return;
            }
            if ($this->tipoModel->existsByName($data['nome'])) {
                $this->setFlashMessage('Já existe um tipo de avaliação com este nome.', 'error');
                $this->redirect('/admin/provas/tipos-avaliacao/criar');
                return;
            }

            $this->tipoModel->create($data);
            $this->setFlashMessage('Tipo de avaliação criado com sucesso.', 'success');
            $this->redirect('/admin/provas/tipos-avaliacao');
        } catch (Exception $e) {
            error_log('Erro ao criar tipo de avaliação: ' . $e->getMessage());
            $this->setFlashMessage('Erro ao criar tipo de avaliação.', 'error');
            $this->redirect('/admin/provas/tipos-avaliacao/criar');
        }
    }

    public function editar($id)
    {
        $tipo = $this->tipoModel->findById((int) $id);
        if (!$tipo) {
            $this->setFlashMessage('Tipo de avaliação não encontrado.', 'error');
            $this->redirect('/admin/provas/tipos-avaliacao');
            return;
        }

        $this->viewWithLayout('admin', 'admin/exams/evaluation-types/edit', $this->dadosLayout([
            'title' => 'Editar Tipo de Nota - EducaTudo',
            'page_title' => 'Editar Tipo de Nota',
            'tipo' => $tipo,
        ]));
    }

    public function atualizar($id)
    {
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/provas/tipos-avaliacao/' . $id . '/editar');
            return;
        }
        try {
            $tipo = $this->tipoModel->findById($id);
            if (!$tipo) {
                $this->setFlashMessage('Tipo de avaliação não encontrado.', 'error');
                $this->redirect('/admin/provas/tipos-avaliacao');
                return;
            }

            $data = $this->regras->normalizar($_POST);
            if ($data['nome'] === '') {
                $this->setFlashMessage('Nome é obrigatório.', 'error');
                $this->redirect('/admin/provas/tipos-avaliacao/' . $id . '/editar');
                return;
            }
            if ($this->tipoModel->existsByName($data['nome'], $id)) {
                $this->setFlashMessage('Já existe um tipo de avaliação com este nome.', 'error');
                $this->redirect('/admin/provas/tipos-avaliacao/' . $id . '/editar');
                return;
            }

            $this->tipoModel->update($id, $data);
            $this->setFlashMessage('Tipo de avaliação atualizado com sucesso.', 'success');
            $this->redirect('/admin/provas/tipos-avaliacao');
        } catch (Exception $e) {
            error_log('Erro ao atualizar tipo de avaliação: ' . $e->getMessage());
            $this->setFlashMessage('Erro ao atualizar tipo de avaliação.', 'error');
            $this->redirect('/admin/provas/tipos-avaliacao/' . $id . '/editar');
        }
    }

    public function excluir($id)
    {
        try {
            $ok = $this->tipoModel->softDelete((int) $id);
            $this->json([
                'success' => (bool) $ok,
                'message' => $ok ? 'Tipo de avaliação excluído com sucesso.' : 'Não foi possível excluir.',
            ]);
        } catch (Exception $e) {
            error_log('Erro ao excluir tipo de avaliação: ' . $e->getMessage());
            $this->json(['error' => 'Erro ao excluir tipo de avaliação.'], 400);
        }
    }
}
}
