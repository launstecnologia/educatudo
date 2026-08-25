<?php

require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Core/AuthManager.php';
require_once __DIR__ . '/../../../Core/AdminSecretariaAccess.php';
require_once __DIR__ . '/../Services/ClassDiaryService.php';
require_once __DIR__ . '/../Services/ClassDiaryReportService.php';
require_once __DIR__ . '/../../../Services/FrequencyService.php';

use App\Modulos\Diario\Services\ClassDiaryReportService;
use App\Modulos\Diario\Services\ClassDiaryService;

if (!class_exists('DiarioAdminController')) {
class DiarioAdminController extends BaseController
{
    private $auth;
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        $allowed = $user && (($user['tipo'] ?? '') === 'admin' ||
            (($user['tipo'] ?? '') === 'admin_escola' && in_array((string) ($user['perfil_admin'] ?? ''), AdminSecretariaAccess::perfisAdminEscolaGestaoPedagogica(), true)));
        if (!$allowed) { $this->redirect('/admin'); exit; }
        $this->service = new ClassDiaryService();
    }

    public function index(): void
    {
        [$inicio, $fim, $professorId, $status, $turmaId] = $this->filtros();
        $aba = (string) ($_GET['aba'] ?? 'pendentes');
        if ($aba !== 'fechados') {
            $aba = 'pendentes';
        }
        $perPage = 10;
        $fechamentos = $this->service->fechamentosFechados($professorId, $turmaId);
        $totalFechados = count($fechamentos);
        if ($aba === 'fechados') {
            $total = $totalFechados;
            $totalPages = max(1, (int) ceil(max($total, 1) / $perPage));
            $page = max(1, min((int) ($_GET['page'] ?? 1), $totalPages));
            $aulasPagina = [];
            $fechamentosPagina = array_slice($fechamentos, ($page - 1) * $perPage, $perPage);
        } else {
            $aulas = $this->service->acompanhamento($inicio, $fim, $professorId, $status, $turmaId);
            $total = count($aulas);
            $totalPages = max(1, (int) ceil(max($total, 1) / $perPage));
            $page = max(1, min((int) ($_GET['page'] ?? 1), $totalPages));
            $aulasPagina = array_slice($aulas, ($page - 1) * $perPage, $perPage);
            $fechamentosPagina = [];
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/diario/index', [
            'title' => 'Acompanhamento do Diário - EducaTudo', 'user' => $this->auth->getUser(),
            'current_page' => 'diario_classe', 'inicio' => $inicio, 'fim' => $fim,
            'professor_id' => $professorId, 'status_filtro' => $status, 'turma_id' => $turmaId,
            'aba' => $aba,
            'aulas' => $aulasPagina,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'page' => $page,
                'total_pages' => $totalPages,
            ],
            'professores' => $this->service->professoresAtivos(),
            'turmas' => $this->service->turmasAtivas(),
            'fechamentos_fechados' => $fechamentosPagina,
            'total_fechados' => $totalFechados,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => (string) ($flash['message'] ?? ''),
        ]);
    }

    public function indicadores(): void
    {
        [$inicio, $fim, $professorId] = $this->filtros();
        $indicadores = $this->service->indicadores($inicio, $fim, $professorId);
        $this->viewWithLayout('admin', 'admin/diario/indicadores', [
            'title' => 'Indicadores do Diário - EducaTudo', 'user' => $this->auth->getUser(),
            'current_page' => 'diario_classe', 'inicio' => $inicio, 'fim' => $fim,
            'professor_id' => $professorId,
            'indicadores' => $indicadores,
            'resumo' => $this->service->resumoIndicadores($indicadores),
            'professores' => $this->service->professoresAtivos(),
        ]);
    }

    public function aula(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $aula = $id > 0 ? $this->service->detalheAula($id) : null;
        if (!$aula) {
            $this->setFlashMessage('Aula não encontrada.', 'error');
            $this->redirect('/admin/diario');
            return;
        }
        $this->viewWithLayout('admin', 'admin/diario/aula', [
            'title' => 'Detalhe da Aula - Diário de Classe', 'user' => $this->auth->getUser(),
            'current_page' => 'diario_classe', 'aula' => $aula,
            'fechamento_id' => (int) ($_GET['fechamento_id'] ?? 0),
        ]);
    }

    /**
     * Visualização somente leitura de um bimestre já fechado (sem reabrir).
     */
    public function fechado(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $aba = (string) ($_GET['aba'] ?? 'resumo');
        if (!in_array($aba, ['resumo', 'aulas', 'frequencia'], true)) {
            $aba = 'resumo';
        }
        $detalhe = $id > 0 ? $this->service->detalheFechamento($id) : null;
        if (!$detalhe) {
            $this->setFlashMessage('Diário fechado não encontrado.', 'error');
            $this->redirect('/admin/diario?aba=fechados');
            return;
        }
        $fechamento = $detalhe['fechamento'];
        $periodo = $detalhe['periodo'];
        $turmaId = (int) $fechamento['turma_id'];
        $materiaId = (int) $fechamento['materia_id'];
        $professorId = (int) $fechamento['professor_id'];
        $dadosAba = [];
        switch ($aba) {
            case 'aulas':
                $dadosAba['aulas'] = $this->service->aulasDoDiario($turmaId, $materiaId, $professorId, $periodo['inicio'], $periodo['fim']);
                break;
            case 'frequencia':
                $freq = new FrequencyService();
                $dadosAba['frequencia_turma'] = $freq->turmaPercentual($turmaId, $periodo['inicio'], $periodo['fim'], $materiaId, $professorId);
                $dadosAba['frequencia_alunos'] = $freq->alunosPercentual($turmaId, $periodo['inicio'], $periodo['fim'], $materiaId, $professorId);
                break;
            default:
                $dadosAba = $this->service->resumoDiario($turmaId, $materiaId, $professorId, $periodo['inicio'], $periodo['fim']);
        }
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/diario/fechado', array_merge([
            'title' => 'Diário fechado - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'diario_classe',
            'aba' => $aba,
            'fechamento' => $fechamento,
            'info' => $detalhe['info'],
            'periodo' => $periodo,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => (string) ($flash['message'] ?? ''),
        ], $dadosAba));
    }

    public function lancar(): void
    {
        $aulaId = (int) ($_GET['aula_id'] ?? 0);
        $gradeId = (int) ($_GET['grade_id'] ?? 0);
        $data = (string) ($_GET['data'] ?? date('Y-m-d'));
        try {
            $dados = $aulaId > 0
                ? $this->service->abrirAulaExistente($aulaId)
                : $this->service->abrirParaLancamento($gradeId, $this->data($data, date('Y-m-d')));
            $this->viewWithLayout('admin', 'admin/diario/chamada', array_merge($dados, [
                'title' => 'Lançar Chamada - Diário de Classe', 'user' => $this->auth->getUser(),
                'current_page' => 'diario_classe',
                'csrf_token' => $this->generateCsrfToken(),
            ]));
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/admin/diario');
        }
    }

    public function salvar(): void
    {
        $token = (string) ($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrf($token)) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/diario');
            return;
        }
        $aulaId = (int) ($_POST['aula_id'] ?? 0);
        try {
            $execucao = (string) ($_POST['execucao'] ?? 'conforme_planejado');
            if (!in_array($execucao, ['conforme_planejado', 'parcial', 'alterado', 'nao_realizada'], true)) {
                $execucao = 'conforme_planejado';
            }
            $finalizar = (string) ($_POST['acao'] ?? 'rascunho') === 'finalizar';
            $this->service->salvarLancamento(
                $aulaId, $execucao,
                trim((string) ($_POST['conteudo_realizado'] ?? '')),
                trim((string) ($_POST['observacoes'] ?? '')),
                (array) ($_POST['frequencias'] ?? []),
                $finalizar,
                ClassDiaryService::extrasDoPost($_POST)
            );
            $this->setFlashMessage($finalizar ? 'Chamada finalizada com sucesso.' : 'Rascunho salvo.', 'success');
            $this->redirect('/admin/diario');
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/admin/diario');
        }
    }

    /**
     * Reabre um diário fechado (Fase 1 da reestruturação — a coordenação pode
     * liberar edição de um bimestre já fechado pelo professor).
     */
    public function reabrirPeriodo(): void
    {
        $token = (string) ($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrf($token)) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/diario?aba=fechados');
            return;
        }
        $fechamentoId = (int) ($_POST['fechamento_id'] ?? 0);
        try {
            if ($fechamentoId <= 0) {
                throw new RuntimeException('Fechamento inválido.');
            }
            $user = $this->auth->getUser();
            $this->service->reabrir($fechamentoId, (int) ($user['id'] ?? 0));
            $this->setFlashMessage('Diário reaberto com sucesso.', 'success');
        } catch (Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        }
        $this->redirect('/admin/diario?aba=fechados');
    }

    public function relatorioExcel(): void
    {
        [$inicio, $fim, $professorId] = $this->filtros();
        $report = new ClassDiaryReportService();
        $dados = $report->dados($inicio, $fim, $professorId);
        $csv = $report->indicadoresCsv($dados['indicadores']);
        $filename = 'relatorio_diario_' . $inicio . '_a_' . $fim . '.csv';
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $csv;
        exit;
    }

    public function relatorioPdf(): void
    {
        [$inicio, $fim, $professorId] = $this->filtros();
        $report = new ClassDiaryReportService();
        $dados = $report->dados($inicio, $fim, $professorId);
        $professorNome = '';
        if ($professorId > 0) {
            foreach ($this->service->professoresAtivos() as $p) {
                if ((int) $p['id'] === $professorId) { $professorNome = (string) $p['nome']; break; }
            }
        }
        $html = $this->renderTemplate('admin/diario/_relatorio_pdf', [
            'dados' => $dados,
            'professor_nome' => $professorNome,
            'gerado_em' => date('d/m/Y H:i'),
        ]);
        $this->outputPdf($html, 'relatorio_diario_' . $inicio . '_a_' . $fim . '.pdf');
    }

    /**
     * @return array{0:string,1:string,2:int,3:string,4:int}
     */
    private function filtros(): array
    {
        $inicio = $this->data((string) ($_GET['inicio'] ?? date('Y-m-01')), date('Y-m-01'));
        $fim = $this->data((string) ($_GET['fim'] ?? date('Y-m-d')), date('Y-m-d'));
        if ($inicio > $fim) { [$inicio, $fim] = [$fim, $inicio]; }
        $professorId = (int) ($_GET['professor_id'] ?? 0);
        $status = (string) ($_GET['status'] ?? '');
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        return [$inicio, $fim, $professorId, $status, $turmaId];
    }

    private function data(string $value, string $fallback): string
    {
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : $fallback;
    }

    private function renderTemplate(string $view, array $viewData): string
    {
        $templateFile = $this->resolveViewPath($view);
        if ($templateFile === null) {
            throw new RuntimeException('View não encontrada: ' . $view);
        }
        ob_start();
        extract($viewData, EXTR_SKIP);
        require $templateFile;
        return (string) ob_get_clean();
    }

    private function outputPdf(string $html, string $filename): void
    {
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) { ob_end_clean(); }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }
}
}
