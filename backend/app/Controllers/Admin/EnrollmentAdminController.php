<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Models/Enrollment/Enrollment.php';
require_once __DIR__ . '/../../Services/EnrollmentService.php';
require_once __DIR__ . '/../../Services/EnrollmentScoreService.php';

use App\Models\Enrollment\Enrollment;
use App\Services\EnrollmentService;
use App\Services\EnrollmentScoreService;

if (!class_exists('EnrollmentAdminController')) {
class EnrollmentAdminController extends BaseController
{
    private $auth;
    private $db;
    private EnrollmentService $service;
    private Enrollment $model;

    public function __construct()
    {
        parent::__construct();
        $this->auth    = new AuthManager();
        $this->db      = Database::getInstance();
        $this->service = new EnrollmentService($this->db);
        $this->model   = $this->service->getModel();

        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin');
        }
    }

    // ── Listagem ─────────────────────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->model->schemaReady()) {
            $this->renderNotReady();
            return;
        }

        $perPage = 25;
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'status'       => $_GET['status'] ?? '',
            'tipo'         => $_GET['tipo'] ?? '',
            'ano_letivo_id'=> (int) ($_GET['ano_letivo_id'] ?? 0),
            'q'            => trim($_GET['q'] ?? ''),
        ];

        $total = $this->model->count($filters);
        $list  = $this->model->list($filters, $perPage, ($page - 1) * $perPage);
        $counts = $this->model->countsByStatus();

        $this->viewWithLayout('admin', 'admin/enrollment/index', [
            'title'         => 'Matrículas — EducaTudo',
            'current_page'  => 'enrollment',
            'user'          => $this->auth->getUser(),
            'list'          => $list,
            'total'         => $total,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_pages'   => (int) ceil($total / $perPage),
            'filters'       => $filters,
            'counts'        => $counts,
            'anos_letivos'  => $this->service->getAnosLetivos(),
            'status_message'=> $_GET['msg'] ?? '',
            'status_type'   => $_GET['status'] ?? '',
        ]);
    }

    // ── Criação ───────────────────────────────────────────────────────────────

    public function create(): void
    {
        if (!$this->model->schemaReady()) { $this->renderNotReady(); return; }

        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        $tipo    = $_GET['tipo'] ?? 'nova';
        $prefill = [];

        $responsaveis = [];
        if ($alunoId > 0) {
            $prefill = $this->service->prefillFromAluno($alunoId);
            if ($tipo === '' || $tipo === 'nova') $tipo = 'rematricula';
            $responsaveis = $this->db->fetchAll(
                "SELECT r.id, r.nome, r.cpf, r.email, r.telefone, ar.tipo_vinculo, ar.is_financeiro
                 FROM responsaveis r
                 INNER JOIN alunos_responsaveis ar ON ar.responsavel_id = r.id
                 WHERE ar.aluno_id = ? AND ar.ativo = 1
                 ORDER BY ar.is_financeiro DESC, r.nome ASC",
                [$alunoId]
            ) ?: [];
        }

        $this->viewWithLayout('admin', 'admin/enrollment/create', [
            'title'        => 'Nova Matrícula — EducaTudo',
            'current_page' => 'enrollment',
            'user'         => $this->auth->getUser(),
            'prefill'      => $prefill,
            'tipo'         => $tipo,
            'anos_letivos' => $this->service->getAnosLetivos(),
            'turmas'       => $this->service->getTurmas(),
            'responsaveis' => $responsaveis,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    public function store(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/create', 'Token inválido.', 'error');
            return;
        }

        $user = $this->auth->getUser();
        $data = [
            'tipo'            => in_array($_POST['tipo'] ?? '', ['nova','rematricula','transferencia']) ? $_POST['tipo'] : 'nova',
            'status'          => 'rascunho',
            'aluno_id'        => (int)($_POST['aluno_id'] ?? 0) ?: null,
            'ano_letivo_id'   => (int)($_POST['ano_letivo_id'] ?? 0) ?: null,
            'turma_id'        => (int)($_POST['turma_id'] ?? 0) ?: null,
            'serie_id'        => (int)($_POST['serie_id'] ?? 0) ?: null,
            'aluno_nome'      => trim($_POST['aluno_nome'] ?? ''),
            'aluno_cpf'       => trim($_POST['aluno_cpf'] ?? '') ?: null,
            'aluno_data_nasc' => trim($_POST['aluno_data_nasc'] ?? '') ?: null,
            'aluno_genero'    => trim($_POST['aluno_genero'] ?? '') ?: null,
            'aluno_email'     => trim($_POST['aluno_email'] ?? '') ?: null,
            'aluno_telefone'  => trim($_POST['aluno_telefone'] ?? '') ?: null,
            'resp_nome'       => trim($_POST['resp_nome'] ?? ''),
            'resp_cpf'        => trim($_POST['resp_cpf'] ?? '') ?: null,
            'resp_email'      => trim($_POST['resp_email'] ?? '') ?: null,
            'resp_telefone'   => trim($_POST['resp_telefone'] ?? '') ?: null,
            'resp_parentesco' => trim($_POST['resp_parentesco'] ?? '') ?: null,
            'resp_endereco'   => trim($_POST['resp_endereco'] ?? '') ?: null,
            'origem'          => in_array($_POST['origem'] ?? '', ['interno','site','whatsapp','indicacao','evento']) ? $_POST['origem'] : 'interno',
            'observacoes'     => trim($_POST['observacoes'] ?? '') ?: null,
            'expira_em'       => !empty($_POST['expira_em']) ? $_POST['expira_em'] . ' 23:59:59' : null,
            'criado_por'      => $user['id'] ?? null,
        ];

        if ($data['aluno_nome'] === '') {
            $this->redirectWithMsg('/admin/enrollment/create', 'Nome do aluno é obrigatório.', 'error');
            return;
        }

        $id = $this->model->create($data);
        $this->model->transition($id, 'rascunho', $user, 'criacao');

        $this->redirectWithMsg('/admin/enrollment/' . $id, 'Matrícula criada com sucesso.', 'success');
    }

    // ── Visualizar / Editar ───────────────────────────────────────────────────

    public function show(int $id): void
    {
        $enrollment = $this->model->findById($id);
        if (!$enrollment) { $this->redirect('/admin/enrollment'); return; }

        $whatsappUrl = '';
        if (!empty($enrollment['resp_telefone']) && !empty($enrollment['contrato_token'])) {
            $whatsappUrl = $this->service->buildWhatsAppLink($enrollment, URL);
        }

        // Busca contrato financeiro vinculado a esta matrícula (se existir)
        $financeContract = null;
        $financeInstallments = [];
        try {
            $financeContract = $this->db->fetch(
                "SELECT * FROM finance_contracts WHERE enrollment_id = ? ORDER BY id DESC LIMIT 1",
                [$id]
            ) ?: null;
            if ($financeContract) {
                $financeInstallments = $this->db->fetchAll(
                    "SELECT * FROM finance_installments WHERE contract_id = ? ORDER BY data_vencimento",
                    [$financeContract['id']]
                ) ?: [];
            }
        } catch (\Throwable $e) {
            // Tabela pode não existir ainda (migration pendente)
        }

        $this->viewWithLayout('admin', 'admin/enrollment/show', [
            'title'        => 'Matrícula #' . $id . ' — EducaTudo',
            'current_page' => 'enrollment',
            'user'         => $this->auth->getUser(),
            'enrollment'   => $enrollment,
            'audit'        => $this->model->getAuditTrail($id),
            'whatsapp_url' => $whatsappUrl,
            'csrf_token'   => $this->generateCsrfToken(),
            'status_message'=> $_GET['msg'] ?? '',
            'status_type'   => $_GET['status_type'] ?? '',
            'finance_contract'             => $financeContract,
            'finance_contract_installments'=> $financeInstallments,
        ]);
    }

    public function edit(int $id): void
    {
        $enrollment = $this->model->findById($id);
        if (!$enrollment) { $this->redirect('/admin/enrollment'); return; }

        $this->viewWithLayout('admin', 'admin/enrollment/edit', [
            'title'        => 'Editar Matrícula #' . $id,
            'current_page' => 'enrollment',
            'user'         => $this->auth->getUser(),
            'enrollment'   => $enrollment,
            'anos_letivos' => $this->service->getAnosLetivos(),
            'turmas'       => $this->service->getTurmas(),
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    public function update(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '/edit', 'Token inválido.', 'error');
            return;
        }
        $enrollment = $this->model->findById($id);
        if (!$enrollment) { $this->redirect('/admin/enrollment'); return; }

        $this->model->update($id, [
            'ano_letivo_id'   => (int)($_POST['ano_letivo_id'] ?? 0) ?: null,
            'turma_id'        => (int)($_POST['turma_id'] ?? 0) ?: null,
            'aluno_nome'      => trim($_POST['aluno_nome'] ?? ''),
            'aluno_cpf'       => trim($_POST['aluno_cpf'] ?? '') ?: null,
            'aluno_data_nasc' => trim($_POST['aluno_data_nasc'] ?? '') ?: null,
            'aluno_email'     => trim($_POST['aluno_email'] ?? '') ?: null,
            'aluno_telefone'  => trim($_POST['aluno_telefone'] ?? '') ?: null,
            'resp_nome'       => trim($_POST['resp_nome'] ?? ''),
            'resp_cpf'        => trim($_POST['resp_cpf'] ?? '') ?: null,
            'resp_email'      => trim($_POST['resp_email'] ?? '') ?: null,
            'resp_telefone'   => trim($_POST['resp_telefone'] ?? '') ?: null,
            'resp_parentesco' => trim($_POST['resp_parentesco'] ?? '') ?: null,
            'resp_endereco'   => trim($_POST['resp_endereco'] ?? '') ?: null,
            'observacoes'     => trim($_POST['observacoes'] ?? '') ?: null,
            'expira_em'       => !empty($_POST['expira_em']) ? $_POST['expira_em'] . ' 23:59:59' : null,
        ]);

        $this->redirectWithMsg('/admin/enrollment/' . $id, 'Matrícula atualizada.', 'success');
    }

    // ── Gerar contrato PDF ────────────────────────────────────────────────────

    public function gerarContrato(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Token inválido.', 'error');
            return;
        }

        $enrollment = $this->model->findById($id);
        if (!$enrollment) { $this->redirect('/admin/enrollment'); return; }

        if (empty($enrollment['contrato_token'])) {
            $token = $this->service->generateContratoToken($id);
        } else {
            $token = $enrollment['contrato_token'];
        }

        $enrollment = $this->model->findById($id); // refetch após token
        $escola     = $this->service->getEscola();
        $this->service->gerarContratoPDF($enrollment, $escola);

        $user = $this->auth->getUser();
        $this->model->transition($id, 'aguardando_assinatura', $user, 'contrato_gerado');

        $this->redirectWithMsg('/admin/enrollment/' . $id, 'Contrato gerado. Envie o link ao responsável.', 'success');
    }

    // ── Download do PDF ────────────────────────────────────────────────────────

    public function downloadContrato(int $id): void
    {
        $enrollment = $this->model->findById($id);
        if (!$enrollment || empty($enrollment['contrato_pdf_path'])) {
            $this->redirect('/admin/enrollment/' . $id);
            return;
        }
        $path = __DIR__ . '/../../../' . $enrollment['contrato_pdf_path'];
        if (!file_exists($path)) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Arquivo não encontrado.', 'error');
            return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="contrato_' . $id . '.pdf"');
        readfile($path);
        exit;
    }

    // ── Mudar status manualmente ──────────────────────────────────────────────

    public function transicionar(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Token inválido.', 'error');
            return;
        }

        $allowed  = ['rascunho','aguardando_contrato','aguardando_assinatura','confirmada','enturmada','abandonada','cancelada','lista_espera'];
        $novoStatus = $_POST['novo_status'] ?? '';
        if (!in_array($novoStatus, $allowed, true)) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Status inválido.', 'error');
            return;
        }

        $user = $this->auth->getUser();
        $this->model->transition($id, $novoStatus, $user, $_POST['acao'] ?? 'alteracao_manual');
        $this->redirectWithMsg('/admin/enrollment/' . $id, 'Status atualizado para: ' . $novoStatus, 'success');
    }

    // ── Cancelar ──────────────────────────────────────────────────────────────

    public function cancelar(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Token inválido.', 'error');
            return;
        }
        $user = $this->auth->getUser();
        $this->model->transition($id, 'cancelada', $user, 'cancelamento_secretaria');
        $this->redirectWithMsg('/admin/enrollment/' . $id, 'Matrícula cancelada.', 'success');
    }

    // ── Painel de score / risco de rematrícula ────────────────────────────────

    public function scorePanel(): void
    {
        $ciclo         = (int) ($_GET['ciclo'] ?? date('Y') + 1);
        $faixaFiltro   = $_GET['faixa'] ?? '';
        $scoreService  = new EnrollmentScoreService($this->db);

        $alunos = $scoreService->listarPorFaixa($ciclo, $faixaFiltro);
        $resumo = [
            'verde'    => count(array_filter($alunos, fn($a) => $a['faixa'] === 'verde')),
            'amarelo'  => count(array_filter($alunos, fn($a) => $a['faixa'] === 'amarelo')),
            'vermelho' => count(array_filter($alunos, fn($a) => $a['faixa'] === 'vermelho')),
        ];

        $this->viewWithLayout('admin', 'admin/enrollment/score', [
            'title'        => 'Score de Rematrícula — EducaTudo',
            'current_page' => 'enrollment',
            'user'         => $this->auth->getUser(),
            'alunos'       => $alunos,
            'resumo'       => $resumo,
            'ciclo'        => $ciclo,
            'faixa_filtro' => $faixaFiltro,
        ]);
    }

    public function recalcularScores(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/enrollment/score');
            return;
        }

        $ciclo        = (int) ($_POST['ciclo'] ?? date('Y') + 1);
        $anoLetivoId  = (int) ($_POST['ano_letivo_id'] ?? 0) ?: null;
        $scoreService = new EnrollmentScoreService($this->db);

        $alunoIds = array_column(
            $this->db->fetchAll("SELECT id FROM alunos WHERE ativo = 1 LIMIT 500") ?: [],
            'id'
        );

        $scoreService->calcularLote($alunoIds, $ciclo, $anoLetivoId);

        $this->redirect('/admin/enrollment/score?ciclo=' . $ciclo . '&msg=Scores+recalculados');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function redirectWithMsg(string $url, string $msg, string $type = 'success'): void
    {
        $sep = str_contains($url, '?') ? '&' : '?';
        $this->redirect($url . $sep . 'msg=' . rawurlencode($msg) . '&status_type=' . $type);
    }

    private function renderNotReady(): void
    {
        $this->viewWithLayout('admin', 'admin/enrollment/not_ready', [
            'title'       => 'Matrículas',
            'current_page'=> 'enrollment',
            'user'        => $this->auth->getUser(),
        ]);
    }
}
}
