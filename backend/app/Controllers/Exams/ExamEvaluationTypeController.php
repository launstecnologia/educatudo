<?php
/**
 * EducaTudo - CRUD Tipo de Avaliação (Provas Online)
 */

require_once __DIR__ . '/../../Models/Exams/ExamEvaluationType.php';

if (!class_exists('ExamEvaluationTypeController')) {
class ExamEvaluationTypeController extends BaseController
{
    private $authManager;
    private $tipoModel;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->tipoModel = new ExamEvaluationType();

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

    public function index()
    {
        $user = $this->authManager->getUser();
        $tipos = $this->tipoModel->getAll();

        $this->viewWithLayout('admin', 'admin/exams/evaluation-types/index', [
            'title' => 'Tipos de Avaliação - EducaTudo',
            'user' => $user,
            'tipos' => $tipos,
        ]);
    }

    public function criar()
    {
        $user = $this->authManager->getUser();
        $this->viewWithLayout('admin', 'admin/exams/evaluation-types/create', [
            'title' => 'Novo Tipo de Avaliação - EducaTudo',
            'user' => $user,
        ]);
    }

    public function salvar()
    {
        try {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $descricao = trim((string) ($_POST['descricao'] ?? ''));
            $ordem = isset($_POST['ordem']) ? (int) $_POST['ordem'] : 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($nome === '') {
                $this->setFlashMessage('Nome é obrigatório.', 'error');
                $this->redirect('/admin/provas/tipos-avaliacao/criar');
                return;
            }
            if ($this->tipoModel->existsByName($nome)) {
                $this->setFlashMessage('Já existe um tipo de avaliação com este nome.', 'error');
                $this->redirect('/admin/provas/tipos-avaliacao/criar');
                return;
            }

            $this->tipoModel->create([
                'nome' => $nome,
                'descricao' => $descricao !== '' ? $descricao : null,
                'ordem' => $ordem,
                'ativo' => $ativo,
            ]);

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
        $user = $this->authManager->getUser();
        $tipo = $this->tipoModel->findById((int) $id);
        if (!$tipo) {
            $this->setFlashMessage('Tipo de avaliação não encontrado.', 'error');
            $this->redirect('/admin/provas/tipos-avaliacao');
            return;
        }

        $this->viewWithLayout('admin', 'admin/exams/evaluation-types/edit', [
            'title' => 'Editar Tipo de Avaliação - EducaTudo',
            'user' => $user,
            'tipo' => $tipo,
        ]);
    }

    public function atualizar($id)
    {
        $id = (int) $id;
        try {
            $tipo = $this->tipoModel->findById($id);
            if (!$tipo) {
                $this->setFlashMessage('Tipo de avaliação não encontrado.', 'error');
                $this->redirect('/admin/provas/tipos-avaliacao');
                return;
            }

            $nome = trim((string) ($_POST['nome'] ?? ''));
            $descricao = trim((string) ($_POST['descricao'] ?? ''));
            $ordem = isset($_POST['ordem']) ? (int) $_POST['ordem'] : 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($nome === '') {
                $this->setFlashMessage('Nome é obrigatório.', 'error');
                $this->redirect('/admin/provas/tipos-avaliacao/' . $id . '/editar');
                return;
            }
            if ($this->tipoModel->existsByName($nome, $id)) {
                $this->setFlashMessage('Já existe um tipo de avaliação com este nome.', 'error');
                $this->redirect('/admin/provas/tipos-avaliacao/' . $id . '/editar');
                return;
            }

            $this->tipoModel->update($id, [
                'nome' => $nome,
                'descricao' => $descricao !== '' ? $descricao : null,
                'ordem' => $ordem,
                'ativo' => $ativo,
            ]);

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

