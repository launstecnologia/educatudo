<?php
/**
 * EducaTudo - Conselho de Classe (professor)
 * Somente leitura das turmas da grade + observação pedagógica.
 */

require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Core/AuthManager.php';
require_once __DIR__ . '/../../../Models/User/Teacher.php';
require_once __DIR__ . '/../Services/ConselhoService.php';

use App\Modulos\ConselhoClasse\Services\ConselhoService;

if (!class_exists('ConselhoProfessorController', false)) {
class ConselhoProfessorController extends BaseController
{
    private $auth;
    private Teacher $teacherModel;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->teacherModel = new Teacher();
        if (!$this->auth->isLoggedIn() || (($this->auth->getUser()['tipo'] ?? '') !== 'professor')) {
            $this->redirect('/');
            exit;
        }
    }

    public function index(): void
    {
        try {
            $professor = $this->professor();
            $svc = new ConselhoService();
            $anos = $svc->model()->anosLetivosTurmas();
            $anoLetivo = (int) ($_GET['ano_letivo'] ?? ($anos[0] ?? date('Y')));
            if (!in_array($anoLetivo, $anos, true)) {
                $anoLetivo = (int) ($anos[0] ?? date('Y'));
            }
            $bimestre = max(1, min(4, (int) ($_GET['bimestre'] ?? (int) ceil(((int) date('n')) / 3))));
            $turmaIds = $svc->model()->turmaIdsDoProfessor((int) $professor['id']);
            $linhas = [];
            foreach ($turmaIds as $tid) {
                foreach ($svc->painel($anoLetivo, $bimestre, $tid) as $linha) {
                    $linhas[] = $linha;
                }
            }

            $flash = $this->getFlashMessage();
            $this->viewWithLayout('professor', 'teacher/conselho-classe/index', [
                'title' => 'Conselho de Classe - EducaTudo',
                'user' => $this->auth->getUser(),
                'current_page' => 'conselho_classe',
                'anos' => $anos,
                'ano_letivo' => $anoLetivo,
                'bimestre' => $bimestre,
                'linhas' => $linhas,
                'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
                'flash_message' => $flash['message'] ?? '',
            ]);
        } catch (Throwable $e) {
            error_log('ConselhoProfessorController: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível abrir o Conselho de Classe.', 'error');
            $this->redirect('/professor/dashboard');
        }
    }

    public function show($id): void
    {
        try {
            $professor = $this->professor();
            $svc = new ConselhoService();
            $sessao = $svc->model()->findById((int) $id);
            if (!$sessao || !$svc->model()->professorTemTurma((int) $professor['id'], (int) $sessao['turma_id'])) {
                $this->setFlashMessage('Conselho não encontrado ou fora das suas turmas.', 'error');
                $this->redirect('/professor/conselhos');
                return;
            }

            $flash = $this->getFlashMessage();
            $this->viewWithLayout('professor', 'teacher/conselho-classe/show', [
                'title' => 'Conselho de Classe - EducaTudo',
                'user' => $this->auth->getUser(),
                'current_page' => 'conselho_classe',
                'sessao' => $sessao,
                'matriz' => $svc->matriz($sessao),
                'professor_id' => (int) $professor['id'],
                'csrf_token' => $this->generateCsrfToken(),
                'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
                'flash_message' => $flash['message'] ?? '',
            ]);
        } catch (Throwable $e) {
            error_log('ConselhoProfessorController: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível abrir este Conselho.', 'error');
            $this->redirect('/professor/conselhos');
        }
    }

    public function observacao($id): void
    {
        $voltar = '/professor/conselhos/' . (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect($voltar);
            return;
        }
        try {
            $professor = $this->professor();
            $svc = new ConselhoService();
            $sessao = $svc->model()->findById((int) $id);
            if (!$sessao || !$svc->model()->professorTemTurma((int) $professor['id'], (int) $sessao['turma_id'])) {
                $this->setFlashMessage('Conselho não encontrado ou fora das suas turmas.', 'error');
                $this->redirect('/professor/conselhos');
                return;
            }
            $result = $svc->registrarObservacao(
                (int) $id,
                (int) ($_POST['aluno_id'] ?? 0),
                (int) $professor['id'],
                (string) ($_POST['texto'] ?? '')
            );
            $this->setFlashMessage(
                $result['success'] ? 'Observação registrada. A nota original não foi alterada.' : ($result['error'] ?? 'Não foi possível salvar'),
                $result['success'] ? 'success' : 'error'
            );
            $this->redirect($voltar);
        } catch (Throwable $e) {
            error_log('ConselhoProfessorController: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível salvar a observação.', 'error');
            $this->redirect($voltar);
        }
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
}
}
