<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Models/Education/ClassDiary.php';
require_once __DIR__ . '/../../Models/Education/SchoolAbsence.php';
require_once __DIR__ . '/../../Models/User/Teacher.php';

class ClassDiaryController extends BaseController
{
    private $auth;
    private $diary;
    private $absence;
    private $teacherModel;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->diary = new ClassDiary();
        $this->absence = new SchoolAbsence();
        $this->teacherModel = new Teacher();
        if (!$this->auth->isLoggedIn() || (($this->auth->getUser()['tipo'] ?? '') !== 'professor')) {
            $this->redirect('/');
            exit;
        }
        $this->diary->ensureSchema();
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

    private function dataValida(string $data): string
    {
        $dt = DateTime::createFromFormat('Y-m-d', $data);
        return ($dt && $dt->format('Y-m-d') === $data) ? $data : date('Y-m-d');
    }

    public function index(): void
    {
        $user = $this->auth->getUser();
        try {
            $professor = $this->professor();
            $dataFiltro = $this->dataValida((string) ($_GET['data'] ?? date('Y-m-d')));
            $aulas = $this->diary->aulasProfessorNoDia((int) $professor['id'], $dataFiltro);
            $this->viewWithLayout('professor', 'teacher/diario/index', [
                'title' => 'Diário de Classe - EducaTudo', 'user' => $user,
                'current_page' => 'diario_classe', 'data_filtro' => $dataFiltro, 'aulas' => $aulas,
            ]);
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/professor/dashboard');
        }
    }

    public function abrir(): void
    {
        $user = $this->auth->getUser();
        $gradeId = (int) ($_GET['grade_id'] ?? 0);
        $data = $this->dataValida((string) ($_GET['data'] ?? date('Y-m-d')));
        try {
            $professor = $this->professor();
            $grade = $this->diary->getGradeDoProfessor($gradeId, (int) $professor['id']);
            if (!$grade || (int) date('N', strtotime($data)) !== (int) $grade['dia_semana']) {
                throw new RuntimeException('Aula não encontrada na grade para essa data.');
            }
            $plano = $this->diary->findPlanoParaAula((int) $professor['id'], (int) $grade['turma_id'], (int) $grade['materia_id'], $data);
            $aula = $this->diary->getOrCreateAula($grade, $data, $plano ? (int) $plano['id'] : null);
            $aula = $this->diary->getAula((int) $aula['id']);
            $ano = (int) ($grade['ano_letivo'] ?? date('Y'));
            $alunos = $this->absence->listAlunosByTurmas([(int) $grade['turma_id']], $ano);
            $this->viewWithLayout('professor', 'teacher/diario/chamada', [
                'title' => 'Chamada - Diário de Classe', 'user' => $user,
                'current_page' => 'diario_classe', 'aula' => $aula, 'alunos' => $alunos,
                'frequencias' => $this->diary->frequenciasMap((int) $aula['id']),
                'csrf_token' => $this->generateCsrfToken(),
            ]);
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/professor/diario?data=' . urlencode($data));
        }
    }

    public function salvar(): void
    {
        $token = (string) ($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrf($token)) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/professor/diario');
            return;
        }
        $aulaId = (int) ($_POST['aula_id'] ?? 0);
        try {
            $professor = $this->professor();
            $aula = $this->diary->getAula($aulaId);
            if (!$aula || (int) $aula['professor_id'] !== (int) $professor['id']) {
                throw new RuntimeException('Aula inválida ou sem permissão.');
            }
            $execucao = (string) ($_POST['execucao'] ?? 'conforme_planejado');
            if (!in_array($execucao, ['conforme_planejado', 'parcial', 'alterado', 'nao_realizada'], true)) {
                $execucao = 'conforme_planejado';
            }
            $alunos = $this->absence->listAlunosByTurmas([(int) $aula['turma_id']], (int) date('Y', strtotime($aula['data_aula'])));
            $permitidos = array_fill_keys(array_map(static fn($a) => (int) $a['id'], $alunos), true);
            $frequencias = [];
            foreach ((array) ($_POST['frequencias'] ?? []) as $alunoId => $payload) {
                if (isset($permitidos[(int) $alunoId])) $frequencias[(int) $alunoId] = $payload;
            }
            $finalizar = (string) ($_POST['acao'] ?? 'rascunho') === 'finalizar';
            $this->diary->salvar($aulaId, $execucao, trim((string) ($_POST['conteudo_realizado'] ?? '')),
                trim((string) ($_POST['observacoes'] ?? '')), $frequencias, $finalizar);
            $this->setFlashMessage($finalizar ? 'Chamada finalizada com sucesso.' : 'Rascunho salvo.', 'success');
            $this->redirect('/professor/diario?data=' . urlencode((string) $aula['data_aula']));
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/professor/diario');
        }
    }
}
