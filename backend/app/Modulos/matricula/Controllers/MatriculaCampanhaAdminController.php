<?php

require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../Services/MatriculaCampanhaService.php';
require_once __DIR__ . '/../Services/MatriculaProcessoService.php';
require_once __DIR__ . '/../../../Services/FinancePlanService.php';

use App\Modulos\Matricula\Services\MatriculaCampanhaService;
use App\Modulos\Matricula\Services\MatriculaProcessoService;
use App\Services\FinancePlanService;

if (!class_exists('MatriculaCampanhaAdminController')) {
class MatriculaCampanhaAdminController extends BaseController
{
    private $auth;
    private $db;
    private MatriculaCampanhaService $service;
    private MatriculaProcessoService $processoService;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->service = new MatriculaCampanhaService($this->db);
        $this->processoService = new MatriculaProcessoService($this->db);
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin');
        }
    }

    public function index(): void
    {
        if (!$this->service->schemaReady()) {
            $this->redirectWithMsg('/admin/enrollment', 'Rode a migration 2026_08_15_matricula_secretaria_ciclo no Master.', 'error');
            return;
        }
        $this->viewWithLayout('admin', 'admin/matricula/campanhas/index', [
            'title' => 'Campanhas de rematrícula — EducaTudo',
            'current_page' => 'enrollment',
            'user' => $this->auth->getUser(),
            'campanhas' => $this->service->listar(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? '',
        ]);
    }

    public function create(): void
    {
        $this->viewWithLayout('admin', 'admin/matricula/campanhas/form', [
            'title' => 'Nova campanha — EducaTudo',
            'current_page' => 'enrollment',
            'user' => $this->auth->getUser(),
            'campanha' => null,
            'anos_letivos' => $this->processoService->getAnosLetivos(),
            'planos' => $this->processoService->listarPlanosFinanceiros(),
            'csrf_token' => $this->generateCsrfToken(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? '',
        ]);
    }

    public function store(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/campanhas/create', 'Token inválido.', 'error');
            return;
        }
        try {
            $id = $this->service->criar($_POST, (int) ($this->auth->getUser()['id'] ?? 0) ?: null);
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, 'Campanha criada.', 'success');
        } catch (\Throwable $e) {
            $this->redirectWithMsg('/admin/enrollment/campanhas/create', $this->msgErro($e), 'error');
        }
    }

    public function show(int $id): void
    {
        $campanha = $this->service->findById($id);
        if (!$campanha) {
            $this->redirect('/admin/enrollment/campanhas');
            return;
        }
        $this->viewWithLayout('admin', 'admin/matricula/campanhas/show', [
            'title' => 'Campanha — EducaTudo',
            'current_page' => 'enrollment',
            'user' => $this->auth->getUser(),
            'campanha' => $campanha,
            'mapa_planos' => $this->service->listarMapaPlanos($id),
            'processos' => $this->service->listarProcessos($id),
            'anos_letivos' => $this->processoService->getAnosLetivos(),
            'planos_origem' => $this->processoService->listarPlanosFinanceiros((int) $campanha['ano_origem_id']),
            'planos_destino' => $this->processoService->listarPlanosFinanceiros((int) $campanha['ano_destino_id']),
            'series' => $this->db->fetchAll('SELECT id, nome FROM serie WHERE ativo = 1 ORDER BY ordem, nome') ?: [],
            'csrf_token' => $this->generateCsrfToken(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? '',
        ]);
    }

    public function update(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, 'Token inválido.', 'error');
            return;
        }
        try {
            $this->service->atualizar($id, $_POST);
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, 'Campanha atualizada.', 'success');
        } catch (\Throwable $e) {
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, $this->msgErro($e), 'error');
        }
    }

    public function status(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, 'Token inválido.', 'error');
            return;
        }
        try {
            $this->service->alterarStatus($id, (string) ($_POST['status'] ?? ''));
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, 'Status atualizado.', 'success');
        } catch (\Throwable $e) {
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, $this->msgErro($e), 'error');
        }
    }

    public function salvarMapa(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, 'Token inválido.', 'error');
            return;
        }
        $linhas = $_POST['mapa'] ?? [];
        $this->service->salvarMapaPlanos($id, is_array($linhas) ? $linhas : []);
        $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, 'Mapa de preços salvo.', 'success');
    }

    public function gerar(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, 'Token inválido.', 'error');
            return;
        }
        try {
            $r = $this->service->gerarProcessos($id, $this->auth->getUser());
            $msg = "Processos gerados: {$r['criados']}";
            if ($r['pulados'] > 0) {
                $msg .= ", {$r['pulados']} ignorado(s)";
            }
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, $msg . '.', 'success');
        } catch (\Throwable $e) {
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, $this->msgErro($e), 'error');
        }
    }

    public function aplicarPlano(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, 'Token inválido.', 'error');
            return;
        }
        $enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);
        $planoId = (int) ($_POST['plano_id'] ?? 0);
        try {
            $this->service->aplicarPlanoNoProcesso($enrollmentId, $planoId, $id);
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, 'Plano aplicado no processo.', 'success');
        } catch (\Throwable $e) {
            $this->redirectWithMsg('/admin/enrollment/campanhas/' . $id, $this->msgErro($e), 'error');
        }
    }

    public function clonarPlanos(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/finance/plans', 'Token inválido.', 'error');
            return;
        }
        try {
            $r = (new FinancePlanService())->clonarParaAno(
                (int) ($_POST['ano_origem_id'] ?? 0),
                (int) ($_POST['ano_destino_id'] ?? 0),
                (float) ($_POST['reajuste_pct'] ?? 0)
            );
            $msg = "Planos clonados: {$r['clonados']}";
            if ($r['pulados'] > 0) {
                $msg .= ", {$r['pulados']} já existiam";
            }
            $dest = (int) ($_POST['ano_destino_id'] ?? 0);
            $this->redirectWithMsg('/admin/finance/plans?ano_letivo_id=' . $dest, $msg . '.', 'success');
        } catch (\Throwable $e) {
            $this->redirectWithMsg('/admin/finance/plans', $this->msgErro($e), 'error');
        }
    }

    private function redirectWithMsg(string $url, string $msg, string $type = 'success'): void
    {
        $sep = str_contains($url, '?') ? '&' : '?';
        $this->redirect($url . $sep . 'msg=' . rawurlencode($msg) . '&status_type=' . $type);
    }

    private function msgErro(\Throwable $e): string
    {
        if ($e instanceof \InvalidArgumentException) {
            return $e->getMessage();
        }
        error_log('MatriculaCampanhaAdminController: ' . $e->getMessage());
        return 'Não foi possível concluir. Tente de novo.';
    }
}
}
