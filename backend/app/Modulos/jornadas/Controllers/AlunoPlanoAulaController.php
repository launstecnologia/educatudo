<?php

namespace App\Modulos\Jornadas\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;
use App\Modulos\Jornadas\Services\PlanoAulaAlunoService;

/**
 * Planos de aula — portal do aluno.
 * URLs preservadas: GET /aluno/planos-aula · GET /aluno/planos-aula/visualizar/{id}
 */
if (!class_exists('AlunoPlanoAulaController')) {
class AlunoPlanoAulaController extends BaseController
{
    private $authManager;
    private $db;
    private PlanoAulaAlunoService $servico;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $this->servico = new PlanoAulaAlunoService($this->db);

        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $user = $this->authManager->getUser();
        if (($user['tipo'] ?? '') !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
    }

    private function redirectToCorrectDashboard(string $tipo): void
    {
        switch ($tipo) {
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'admin':
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/dashboard');
        }
    }

    /**
     * GET /aluno/planos-aula
     */
    public function index(): void
    {
        $user = $this->authManager->getUser();
        if (!$user || !is_array($user)) {
            $this->redirect('/logout');
            return;
        }

        $aluno = $this->servico->buscarAlunoComTurma((int) ($user['id'] ?? 0));
        if (!$aluno) {
            $this->redirect('/logout');
            return;
        }

        $turmaId = !empty($aluno['turma_id']) ? (int) $aluno['turma_id'] : 0;
        $planosAula = $this->servico->listarParaTurma($turmaId);

        $this->viewWithLayout('student', 'student/planos-aula/index', [
            'title' => 'Planos de Aula - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'planos_aula' => $planosAula,
            'current_page' => 'planos-aula',
        ]);
    }

    /**
     * GET /aluno/planos-aula/visualizar/{id}
     */
    public function visualizar($id): void
    {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            $this->setFlashMessage('ID do plano de aula inválido', 'error');
            $this->redirect('/aluno/planos-aula');
            return;
        }

        $user = $this->authManager->getUser();
        if (!$user || !is_array($user)) {
            $this->redirect('/logout');
            return;
        }

        $aluno = $this->servico->buscarAlunoComTurma((int) ($user['id'] ?? 0));
        if (!$aluno) {
            $this->redirect('/logout');
            return;
        }

        $planoAula = $this->servico->buscarNaoDeletado((int) $id);
        if (!$planoAula) {
            $this->setFlashMessage('Plano de aula não encontrado', 'error');
            $this->redirect('/aluno/planos-aula');
            return;
        }

        if (!$this->servico->alunoPodeVisualizar($aluno, $planoAula)) {
            $this->setFlashMessage('Você não tem permissão para visualizar este plano de aula', 'error');
            $this->redirect('/aluno/planos-aula');
            return;
        }

        $this->viewWithLayout('student', 'student/planos-aula/visualizar', [
            'title' => 'Plano de Aula - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'plano_aula' => $planoAula,
            'current_page' => 'planos-aula',
        ]);
    }
}
}

if (!class_exists('AlunoPlanoAulaController', false) && class_exists(__NAMESPACE__ . '\\AlunoPlanoAulaController', false)) {
    class_alias(__NAMESPACE__ . '\\AlunoPlanoAulaController', 'AlunoPlanoAulaController');
}
