<?php

require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Core/AuthManager.php';
require_once __DIR__ . '/../Models/ClassDiary.php';
require_once __DIR__ . '/../../../Models/Education/SchoolAbsence.php';
require_once __DIR__ . '/../../../Models/User/Teacher.php';
require_once __DIR__ . '/../Services/ClassDiaryService.php';
require_once __DIR__ . '/../../../Services/FrequencyService.php';

use App\Modulos\Diario\Models\ClassDiary;
use App\Modulos\Diario\Services\ClassDiaryService;

if (!class_exists('DiarioProfessorController')) {
class DiarioProfessorController extends BaseController
{
    private $auth;
    private $diary;
    private $absence;
    private $teacherModel;
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->diary = new ClassDiary();
        $this->absence = new SchoolAbsence();
        $this->teacherModel = new Teacher();
        $this->service = new ClassDiaryService($this->diary);
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
        $origem = (string) ($_GET['origem'] ?? '');
        $voltarAgenda = '/professor/diario?data=' . urlencode($data);
        $grade = null;
        try {
            $professor = $this->professor();
            $grade = $this->diary->getGradeDoProfessor($gradeId, (int) $professor['id']);
            if (!$grade || (int) date('N', strtotime($data)) !== (int) $grade['dia_semana']) {
                throw new RuntimeException('Aula não encontrada na grade para essa data.');
            }
            $plano = $this->diary->findPlanoParaAula((int) $professor['id'], (int) $grade['turma_id'], (int) $grade['materia_id'], $data);
            $aulaCriada = $this->diary->getOrCreateAula($grade, $data, $plano ? (int) $plano['id'] : null);
            $dados = $this->service->abrirAulaExistente((int) $aulaCriada['id']);
            $this->viewWithLayout('professor', 'teacher/diario/chamada', array_merge($dados, [
                'title' => 'Chamada - Diário de Classe', 'user' => $user,
                'current_page' => $origem === 'diario' ? 'diarios_classe' : 'diario_classe',
                'csrf_token' => $this->generateCsrfToken(),
                'origem' => $origem,
            ]));
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            if ($origem === 'diario' && is_array($grade) && !empty($grade['turma_id']) && !empty($grade['materia_id'])) {
                $this->redirect($this->urlAbrirDiario((int) $grade['turma_id'], (int) $grade['materia_id'], 'aulas'));
                return;
            }
            $this->redirect($voltarAgenda);
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
            $this->service->salvarLancamento(
                $aulaId,
                $execucao,
                trim((string) ($_POST['conteudo_realizado'] ?? '')),
                trim((string) ($_POST['observacoes'] ?? '')),
                $frequencias,
                $finalizar,
                ClassDiaryService::extrasDoPost($_POST)
            );
            $this->setFlashMessage($finalizar ? 'Chamada finalizada com sucesso.' : 'Rascunho salvo.', 'success');
            if ((string) ($_POST['origem'] ?? '') === 'diario') {
                $this->redirect($this->urlAbrirDiario((int) $aula['turma_id'], (int) $aula['materia_id'], 'aulas'));
                return;
            }
            $this->redirect('/professor/diario?data=' . urlencode((string) $aula['data_aula']));
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/professor/diario');
        }
    }

    // ── Diários de Classe (visão agregada, Fase 1 da reestruturação) ───────

    public function listar(): void
    {
        $user = $this->auth->getUser();
        try {
            $professor = $this->professor();
            $professorId = (int) $professor['id'];
            $filtros = [
                'professor_id' => $professorId,
                'inicio' => $this->dataValida((string) ($_GET['inicio'] ?? date('Y-01-01'))),
                'fim' => $this->dataValida((string) ($_GET['fim'] ?? date('Y-12-31'))),
                'ano_letivo' => (int) ($_GET['ano_letivo'] ?? 0),
                'turma_id' => (int) ($_GET['turma_id'] ?? 0),
                'materia_id' => (int) ($_GET['materia_id'] ?? 0),
                'situacao' => (string) ($_GET['situacao'] ?? ''),
            ];
            $this->viewWithLayout('professor', 'teacher/diario/diarios', [
                'title' => 'Diários de Classe - EducaTudo', 'user' => $user,
                'current_page' => 'diarios_classe',
                'diarios' => $this->service->diarios($filtros),
                'filtros' => $filtros,
                'turmas' => $this->service->turmasDoProfessor($professorId),
                'materias' => $this->service->materiasDoProfessor($professorId),
                'anos_letivos' => $this->service->anosLetivosDoProfessor($professorId),
            ]);
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/professor/dashboard');
        }
    }

    public function abrirDiario(): void
    {
        $user = $this->auth->getUser();
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $materiaId = (int) ($_GET['materia_id'] ?? 0);
        $aba = (string) ($_GET['aba'] ?? 'resumo');
        if (!in_array($aba, ['resumo', 'aulas', 'frequencia', 'planejamento', 'notas', 'fechamento'], true)) {
            $aba = 'resumo';
        }
        try {
            $professor = $this->professor();
            $professorId = (int) $professor['id'];
            if (!$this->service->professorLecionaDiario($turmaId, $materiaId, $professorId)) {
                throw new RuntimeException('Diário não encontrado ou sem permissão.');
            }
            $info = $this->diary->infoDiario($turmaId, $materiaId, $professorId);
            $anoLetivo = (int) ($info['ano_letivo'] ?? date('Y'));
            $inicio = $this->dataValida((string) ($_GET['inicio'] ?? $anoLetivo . '-01-01'));
            $fim = $this->dataValida((string) ($_GET['fim'] ?? $anoLetivo . '-12-31'));

            $dadosAba = [];
            switch ($aba) {
                case 'aulas':
                    $dadosAba['aulas'] = $this->service->aulasDoDiario($turmaId, $materiaId, $professorId, $inicio, $fim);
                    break;
                case 'frequencia':
                    $freq = new FrequencyService();
                    $dadosAba['frequencia_turma'] = $freq->turmaPercentual($turmaId, $inicio, $fim, $materiaId, $professorId);
                    $dadosAba['frequencia_alunos'] = $freq->alunosPercentual($turmaId, $inicio, $fim, $materiaId, $professorId);
                    break;
                case 'planejamento':
                    $dadosAba = $this->service->planejamentoDoDiario($turmaId, $materiaId, $professorId, $inicio, $fim);
                    break;
                case 'notas':
                    $dadosAba['eventos'] = $this->service->eventosNotaComVinculos($turmaId, $materiaId, $professorId, $inicio, $fim);
                    break;
                case 'fechamento':
                    // Cada bimestre é validado com o período que o próprio fechamento vai
                    // usar no servidor (ClassDiary::periodoDoBimestre) — não o inicio/fim
                    // do filtro da tela, pra não mostrar uma pendência diferente da que
                    // realmente bloqueia (ou libera) o fechamento.
                    $bimestres = [];
                    for ($b = 1; $b <= 4; $b++) {
                        $periodo = $this->diary->periodoDoBimestre($anoLetivo, $b);
                        $resumoBimestre = $this->service->resumoDiario($turmaId, $materiaId, $professorId, $periodo['inicio'], $periodo['fim']);
                        $bimestres[$b] = [
                            'periodo' => $periodo,
                            'resumo' => $resumoBimestre['resumo'],
                            'pendencias' => $resumoBimestre['pendencias'],
                            'fechamento' => $this->service->getFechamento($turmaId, $materiaId, $professorId, $anoLetivo, $b),
                        ];
                    }
                    $dadosAba['bimestres'] = $bimestres;
                    break;
                default:
                    $dadosAba = $this->service->resumoDiario($turmaId, $materiaId, $professorId, $inicio, $fim);
            }

            $this->viewWithLayout('professor', 'teacher/diario/abrir', array_merge([
                'title' => 'Diário de Classe - EducaTudo', 'user' => $user,
                'current_page' => 'diarios_classe',
                'aba' => $aba, 'info' => $info, 'inicio' => $inicio, 'fim' => $fim,
                'ano_letivo' => $anoLetivo,
                'csrf_token' => $this->generateCsrfToken(),
            ], $dadosAba));
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/professor/diarios');
        }
    }

    public function fecharPeriodo(): void
    {
        $token = (string) ($_POST['csrf_token'] ?? '');
        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        $materiaId = (int) ($_POST['materia_id'] ?? 0);
        $voltar = '/professor/diarios/abrir?turma_id=' . $turmaId . '&materia_id=' . $materiaId . '&aba=fechamento';
        if (!$this->validateCsrf($token)) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect($voltar);
            return;
        }
        try {
            $user = $this->auth->getUser();
            $professor = $this->professor();
            $professorId = (int) $professor['id'];
            if (!$this->service->professorLecionaDiario($turmaId, $materiaId, $professorId)) {
                throw new RuntimeException('Diário não encontrado ou sem permissão.');
            }
            $info = $this->diary->infoDiario($turmaId, $materiaId, $professorId);
            $anoLetivo = (int) ($info['ano_letivo'] ?? 0);
            $bimestre = (int) ($_POST['bimestre'] ?? 0);
            if ($bimestre < 1 || $bimestre > 4) {
                throw new RuntimeException('Selecione um bimestre válido (1 a 4).');
            }
            $this->service->fechar($turmaId, $materiaId, $professorId, $anoLetivo, $bimestre, (int) ($user['id'] ?? 0));
            $this->setFlashMessage('Bimestre fechado com sucesso.', 'success');
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        }
        $this->redirect($voltar);
    }

    public function vincularPlano(): void
    {
        $token = (string) ($_POST['csrf_token'] ?? '');
        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        $materiaId = (int) ($_POST['materia_id'] ?? 0);
        $inicio = $this->dataValida((string) ($_POST['inicio'] ?? date('Y-m-d')));
        $fim = $this->dataValida((string) ($_POST['fim'] ?? date('Y-m-d')));
        $voltar = $this->urlAbrirDiario($turmaId, $materiaId, 'planejamento', $inicio, $fim);
        if (!$this->validateCsrf($token)) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect($voltar);
            return;
        }
        try {
            $professor = $this->professor();
            $alvo = explode('|', (string) ($_POST['aula_alvo'] ?? ''), 2);
            $gradeId = (int) ($alvo[0] ?? ($_POST['grade_horaria_id'] ?? 0));
            $dataAula = $this->dataValida((string) ($alvo[1] ?? ($_POST['data_aula'] ?? '')));
            $this->service->vincularPlanoAula(
                (int) $professor['id'],
                $turmaId,
                $materiaId,
                (int) ($_POST['plano_aula_id'] ?? 0),
                $gradeId,
                $dataAula
            );
            $this->setFlashMessage('Plano vinculado à aula.', 'success');
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        }
        $this->redirect($voltar);
    }

    private function urlAbrirDiario(int $turmaId, int $materiaId, string $aba, ?string $inicio = null, ?string $fim = null): string
    {
        $query = [
            'turma_id' => $turmaId,
            'materia_id' => $materiaId,
            'aba' => $aba,
        ];
        if ($inicio !== null && $inicio !== '') {
            $query['inicio'] = $inicio;
        }
        if ($fim !== null && $fim !== '') {
            $query['fim'] = $fim;
        }
        return '/professor/diarios/abrir?' . http_build_query($query);
    }
}
}
