<?php

require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Core/AuthManager.php';
require_once __DIR__ . '/../../../Models/User/Teacher.php';
require_once __DIR__ . '/../../../Models/Education/ClassDiary.php';
require_once __DIR__ . '/../Services/OcorrenciaService.php';

use App\Modulos\Ocorrencias\Services\OcorrenciaService;

if (!class_exists('OcorrenciaProfessorController')) {
class OcorrenciaProfessorController extends BaseController
{
    private $auth;
    private Teacher $teacherModel;
    private OcorrenciaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->teacherModel = new Teacher();
        $this->service = new OcorrenciaService();
        if (!$this->auth->isLoggedIn() || (($this->auth->getUser()['tipo'] ?? '') !== 'professor')) {
            $this->redirect('/');
            exit;
        }
    }

    public function nova(): void
    {
        $professor = $this->professor();
        $aulaId = (int) ($_GET['aula_id'] ?? 0);
        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        $aula = $this->service->aulaDoProfessor($aulaId, (int) $professor['id']);
        if (!$aula) {
            $this->setFlashMessage('Aula não encontrada ou sem permissão.', 'error');
            $this->redirect('/professor/diario');
            return;
        }
        $aluno = $alunoId > 0 ? $this->service->model()->dadosAluno($alunoId) : null;
        if (!$aluno || (int) ($aluno['turma_id'] ?? 0) !== (int) $aula['turma_id']) {
            $this->setFlashMessage('Aluno inválido para esta aula.', 'error');
            $this->redirect($this->urlVoltarAula($aula));
            return;
        }

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('professor', 'professor/ocorrencias/form', [
            'title' => 'Registrar ocorrência',
            'user' => $this->auth->getUser(),
            'current_page' => 'diario_classe',
            'aula' => $aula,
            'aluno' => $aluno,
            'categorias' => $this->service->model()->categorias(true),
            'schema_estendido' => $this->service->model()->schemaEstendido(),
            'voltar_url' => $this->urlVoltarAula($aula),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => ($flash['type'] ?? '') === 'error' ? 'error' : (($flash['message'] ?? null) ? 'success' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function salvar(): void
    {
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/professor/diario');
            return;
        }

        $professor = $this->professor();
        $aulaId = (int) ($_POST['diario_aula_id'] ?? 0);
        $aula = $this->service->aulaDoProfessor($aulaId, (int) $professor['id']);
        if (!$aula) {
            $this->setFlashMessage('Aula inválida ou sem permissão.', 'error');
            $this->redirect('/professor/diario');
            return;
        }

        $result = $this->service->criar($_POST, (int) ($this->auth->getUser()['id'] ?? 0), 'professor', (int) $professor['id']);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível salvar', 'error');
            $alunoId = (int) ($_POST['aluno_id'] ?? 0);
            $this->redirect('/professor/ocorrencias/nova?aula_id=' . $aulaId . '&aluno_id=' . $alunoId);
            return;
        }

        $this->setFlashMessage('Ocorrência registrada no cadastro do aluno.', 'success');
        $this->redirect($this->urlVoltarAula($aula));
    }

    private function professor(): array
    {
        $user = $this->auth->getUser();
        $professor = $this->teacherModel->findById((int) ($user['id'] ?? 0));
        if (!$professor) {
            throw new RuntimeException('Cadastro de professor não encontrado.');
        }
        return $professor;
    }

    /**
     * @param array<string,mixed> $aula
     */
    private function urlVoltarAula(array $aula): string
    {
        $origem = (string) ($_POST['origem'] ?? $_GET['origem'] ?? '');
        if ($origem === 'diario') {
            return '/professor/diario/abrir?grade_id=' . (int) ($aula['grade_horaria_id'] ?? 0)
                . '&data=' . urlencode((string) ($aula['data_aula'] ?? ''))
                . '&origem=diario';
        }
        return '/professor/diario/abrir?grade_id=' . (int) ($aula['grade_horaria_id'] ?? 0)
            . '&data=' . urlencode((string) ($aula['data_aula'] ?? ''));
    }
}
}
