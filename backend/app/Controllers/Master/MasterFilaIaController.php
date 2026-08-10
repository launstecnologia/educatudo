<?php
/**
 * Painel Master — Fila de jobs de IA + histórico de execuções do cron.
 */

if (!class_exists('MasterFilaIaController')) {

class MasterFilaIaController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    public function index()
    {
        $this->requireMaster();
        require_once __DIR__ . '/../../Services/MasterFilaIaService.php';
        require_once __DIR__ . '/../../Services/CronExecucaoService.php';

        $aba = trim((string) ($_GET['aba'] ?? 'fila'));
        if (!in_array($aba, ['fila', 'cron'], true)) {
            $aba = 'fila';
        }

        $escolas = \App\Services\MasterFilaIaService::listarEscolasAtivas();
        $ultimaCron = \App\Services\MasterFilaIaService::ultimaCronExecucao(
            \App\Services\CronExecucaoService::SCRIPT_PROCESS_AI_JOBS
        );

        $filtros = [
            'escola_id' => (int) ($_GET['escola_id'] ?? 0),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'job_type' => trim((string) ($_GET['job_type'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
            'so_travados' => !empty($_GET['so_travados']),
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
            'per_page' => 20,
        ];

        $listaJobs = [
            'kpis' => [
                'pending' => 0,
                'processing' => 0,
                'failed' => 0,
                'done' => 0,
                'travados' => 0,
                'escolas_com_fila' => 0,
            ],
            'jobs' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 20,
            'erros' => [],
        ];
        $listaCron = [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 20,
            'tabela_ok' => false,
        ];

        if ($aba === 'cron') {
            $listaCron = \App\Services\MasterFilaIaService::listarCronExecucoes(
                \App\Services\CronExecucaoService::SCRIPT_PROCESS_AI_JOBS,
                max(1, (int) ($_GET['page'] ?? 1)),
                20
            );
            // Aba cron: não varre ai_jobs de todas as escolas (só histórico master + último cron no topo).
        } else {
            $listaJobs = \App\Services\MasterFilaIaService::listarJobs($filtros);
        }

        $cronTabelaOk = \App\Services\CronExecucaoService::tabelasDisponiveis();

        $this->viewWithLayout('master', 'master/fila_ia/index', [
            'title' => 'Fila IA - Painel Master',
            'page_title' => 'Fila IA',
            'current_page' => 'fila_ia',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'aba' => $aba,
            'escolas' => $escolas,
            'filtros' => $filtros,
            'lista_jobs' => $listaJobs,
            'lista_cron' => $listaCron,
            'ultima_cron' => $ultimaCron,
            'cron_tabela_ok' => $cronTabelaOk,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function destravar()
    {
        $this->requireMaster();
        require_once __DIR__ . '/../../Services/MasterFilaIaService.php';

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Sessão expirada, tente novamente.';
            header('Location: ' . URL . '/master/fila-ia');
            exit;
        }

        $escolaId = (int) ($_POST['escola_id'] ?? 0);
        $resultado = \App\Services\MasterFilaIaService::destravarTravados($escolaId);
        $msg = 'Destravados: '
            . (int) $resultado['reenfileirados'] . ' reenfileirado(s), '
            . (int) $resultado['falhos'] . ' marcado(s) failed.';
        if (!empty($resultado['erros'])) {
            $msg .= ' Avisos: ' . implode('; ', array_slice($resultado['erros'], 0, 3));
        }
        $_SESSION['flash_success'] = $msg;

        $qs = $escolaId > 0 ? ('?escola_id=' . $escolaId . '&so_travados=1') : '?so_travados=1';
        header('Location: ' . URL . '/master/fila-ia' . $qs);
        exit;
    }

    public function reenfileirar()
    {
        $this->requireMaster();
        require_once __DIR__ . '/../../Services/MasterFilaIaService.php';

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Sessão expirada, tente novamente.';
            header('Location: ' . URL . '/master/fila-ia');
            exit;
        }

        $escolaId = (int) ($_POST['escola_id'] ?? 0);
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $resultado = \App\Services\MasterFilaIaService::reenfileirarJob($escolaId, $jobId);
        if (!empty($resultado['ok'])) {
            $_SESSION['flash_success'] = (string) $resultado['mensagem'];
        } else {
            $_SESSION['flash_error'] = (string) ($resultado['mensagem'] ?? 'Falha ao reenfileirar.');
        }

        header('Location: ' . URL . '/master/fila-ia/job?escola_id=' . $escolaId . '&job_id=' . $jobId);
        exit;
    }

    public function job()
    {
        $this->requireMaster();
        require_once __DIR__ . '/../../Services/MasterFilaIaService.php';

        $escolaId = (int) ($_GET['escola_id'] ?? 0);
        $jobId = (int) ($_GET['job_id'] ?? 0);
        $job = \App\Services\MasterFilaIaService::detalheJob($escolaId, $jobId);

        if ($job === null) {
            $_SESSION['flash_error'] = 'Job não encontrado nesta escola.';
            header('Location: ' . URL . '/master/fila-ia');
            exit;
        }

        $this->viewWithLayout('master', 'master/fila_ia/job', [
            'title' => 'Job #' . $jobId . ' - Fila IA',
            'page_title' => 'Detalhe do job',
            'current_page' => 'fila_ia',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'job' => $job,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function cron()
    {
        $this->requireMaster();
        require_once __DIR__ . '/../../Services/MasterFilaIaService.php';

        $id = (int) ($_GET['id'] ?? 0);
        $detalhe = \App\Services\MasterFilaIaService::detalheCronExecucao($id);
        if ($detalhe === null) {
            $_SESSION['flash_error'] = 'Execução de cron não encontrada. Rode a migration master 2026_08_10_cron_execucoes_master.sql.';
            header('Location: ' . URL . '/master/fila-ia?aba=cron');
            exit;
        }

        $this->viewWithLayout('master', 'master/fila_ia/cron', [
            'title' => 'Cron #' . $id . ' - Fila IA',
            'page_title' => 'Execução do cron',
            'current_page' => 'fila_ia',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'execucao' => $detalhe['execucao'],
            'escolas_cron' => $detalhe['escolas'],
        ]);
    }
}

}
