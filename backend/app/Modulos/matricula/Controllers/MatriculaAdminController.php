<?php
/**
 * Admin — processos de matrícula/rematrícula.
 */

require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Core/AuthManager.php';
require_once __DIR__ . '/../../../Core/Database.php';
require_once __DIR__ . '/../Models/MatriculaProcesso.php';
require_once __DIR__ . '/../Services/MatriculaProcessoService.php';
require_once __DIR__ . '/../Services/MatriculaScoreService.php';
require_once __DIR__ . '/../Services/ZapSignService.php';
require_once __DIR__ . '/../Services/MatriculaVagaService.php';
require_once __DIR__ . '/../Services/MatriculaChecklistService.php';

use App\Modulos\Matricula\Models\MatriculaProcesso;
use App\Modulos\Matricula\Services\MatriculaProcessoService;
use App\Modulos\Matricula\Services\MatriculaScoreService;
use App\Modulos\Matricula\Services\ZapSignService;
use App\Modulos\Matricula\Services\MatriculaVagaService;
use App\Modulos\Matricula\Services\MatriculaChecklistService;

if (!class_exists('MatriculaAdminController')) {
class MatriculaAdminController extends BaseController
{
    private $auth;
    private $db;
    private MatriculaProcessoService $service;
    private MatriculaProcesso $model;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
        $this->service = new MatriculaProcessoService($this->db);
        $this->model = $this->service->getModel();

        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin');
        }
    }

    public function index(): void
    {
        if (!$this->model->schemaReady()) {
            $this->renderNotReady();
            return;
        }

        $perPage = 25;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'status' => $_GET['status'] ?? '',
            'tipo' => $_GET['tipo'] ?? '',
            'ano_letivo_id' => (int) ($_GET['ano_letivo_id'] ?? 0),
            'q' => trim($_GET['q'] ?? ''),
        ];

        $total = $this->model->count($filters);
        $list = $this->model->list($filters, $perPage, ($page - 1) * $perPage);

        $this->viewWithLayout('admin', 'admin/matricula/index', [
            'title' => 'Matrículas — EducaTudo',
            'current_page' => 'enrollment',
            'user' => $this->auth->getUser(),
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / max(1, $perPage)),
            'filters' => $filters,
            'counts' => $this->model->countsByStatus(),
            'anos_letivos' => $this->service->getAnosLetivos(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? ($_GET['status'] ?? ''),
        ]);
    }

    public function configForm(): void
    {
        if (!$this->model->schemaReady()) {
            $this->renderNotReady();
            return;
        }

        $documentosAssinatura = MatriculaProcessoService::DOCUMENTOS_ASSINATURA;
        $svcPath = dirname(__DIR__, 2) . '/modelos-documentos/Services/ModeloDocumentoService.php';
        if (is_file($svcPath)) {
            require_once $svcPath;
            $modeloSvc = new \App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService($this->db);
            if ($modeloSvc->schemaReady()) {
                $fromDb = [];
                foreach ($modeloSvc->listarExcetoDeclaracoes(true) as $m) {
                    $codigo = (string) ($m['codigo'] ?? '');
                    if ($codigo === '') {
                        continue;
                    }
                    $fromDb[$codigo] = (string) ($m['nome'] ?? $codigo);
                }
                if ($fromDb !== []) {
                    $documentosAssinatura = $fromDb;
                }
            }
        }

        $this->viewWithLayout('admin', 'admin/matricula/config', [
            'title' => 'Configuração de Matrícula — EducaTudo',
            'current_page' => 'enrollment_config',
            'user' => $this->auth->getUser(),
            'config' => $this->service->getConfigAssinaturaEscola(),
            'documentos_assinatura' => $documentosAssinatura,
            'pagante_modos' => MatriculaProcessoService::PAGANTE_MODOS,
            'tipos_contrato' => MatriculaProcessoService::TIPOS_CONTRATO,
            'regras_contrato' => $this->service->listarRegrasContrato(false),
            'schema_regras_ok' => $this->service->schemaContratoRegrasReady(),
            'checklist_itens' => (new MatriculaChecklistService($this->db))->listarTodos(false),
            'csrf_token' => $this->generateCsrfToken(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? '',
        ]);
    }

    public function configStore(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/config', 'Token inválido.', 'error');
            return;
        }

        $assinarContrato = !empty($_POST['assinar_contrato']);
        $assinarFicha = !empty($_POST['assinar_ficha']);
        if (!$assinarContrato && !$assinarFicha) {
            $this->redirectWithMsg('/admin/enrollment/config', 'Marque pelo menos um documento para assinar.', 'error');
            return;
        }

        $documentoCodigo = trim((string) ($_POST['documento_codigo'] ?? 'contrato_matricula'));
        $codigosPermitidos = array_keys(MatriculaProcessoService::DOCUMENTOS_ASSINATURA);
        $svcPath = dirname(__DIR__, 2) . '/modelos-documentos/Services/ModeloDocumentoService.php';
        if (is_file($svcPath)) {
            require_once $svcPath;
            $modeloSvc = new \App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService($this->db);
            if ($modeloSvc->schemaReady()) {
                foreach ($modeloSvc->listarExcetoDeclaracoes(true) as $m) {
                    $c = (string) ($m['codigo'] ?? '');
                    if ($c !== '') {
                        $codigosPermitidos[] = $c;
                    }
                }
            }
        }
        $codigosPermitidos = array_values(array_unique($codigosPermitidos));
        if ($documentoCodigo === '' || !in_array($documentoCodigo, $codigosPermitidos, true)) {
            $documentoCodigo = 'contrato_matricula';
        }

        $this->service->salvarConfigAssinaturaEscola([
            'documento_codigo' => $documentoCodigo,
            'pagante_modo' => $_POST['pagante_modo'] ?? 'um',
            'pagante1_pct' => $_POST['pagante1_pct'] ?? 100,
            'pagante2_pct' => $_POST['pagante2_pct'] ?? 0,
            'pagante3_pct' => $_POST['pagante3_pct'] ?? 0,
            'contrato_com_valores' => ($_POST['contrato_com_valores'] ?? '0') === '1',
            'assinar_contrato' => $assinarContrato,
            'assinar_ficha' => $assinarFicha,
        ]);

        // Regras multi-contrato (modelo × tipo)
        if ($this->service->schemaContratoRegrasReady()) {
            try {
                $regrasPost = $_POST['regras'] ?? [];
                if (!is_array($regrasPost)) {
                    $regrasPost = [];
                }
                $this->service->salvarRegrasContrato($regrasPost, $codigosPermitidos);
            } catch (\Throwable $e) {
                error_log('[MatriculaAdminController::configStore] regras: ' . $e->getMessage());
                $this->redirectWithMsg('/admin/enrollment/config', 'Config salva, mas falha ao gravar regras de contrato: ' . mb_substr($e->getMessage(), 0, 120), 'error');
                return;
            }
        }

        $checklist = new MatriculaChecklistService($this->db);
        if ($checklist->schemaReady()) {
            $itensPost = $_POST['checklist'] ?? [];
            if (is_array($itensPost)) {
                $checklist->salvarLote($itensPost);
            }
        }

        $this->redirectWithMsg('/admin/enrollment/config', 'Configuração salva.', 'success');
    }

    public function assinaturaDigitalForm(): void
    {
        if (!$this->model->schemaReady()) {
            $this->renderNotReady();
            return;
        }
        $zs = new ZapSignService($this->db);
        $this->viewWithLayout('admin', 'admin/matricula/assinatura_digital', [
            'title' => 'Assinatura Digital — EducaTudo',
            'current_page' => 'assinatura_digital',
            'user' => $this->auth->getUser(),
            'zapsign' => $zs->obterConfigPublica(),
            'csrf_token' => $this->generateCsrfToken(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? '',
        ]);
    }

    public function assinaturaDigitalStore(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/configuracao/assinatura-digital', 'Token inválido.', 'error');
            return;
        }

        try {
            $zs = new ZapSignService($this->db);
            $zs->salvarConfig([
                'ativo' => !empty($_POST['zapsign_ativo']),
                'ambiente' => $_POST['zapsign_ambiente'] ?? 'sandbox',
                'api_token' => trim((string) ($_POST['zapsign_api_token'] ?? '')),
                'webhook_base_url' => trim((string) ($_POST['zapsign_webhook_base_url'] ?? '')),
                'enviar_email' => !empty($_POST['zapsign_enviar_email']),
                'regenerar_webhook' => !empty($_POST['zapsign_regenerar_webhook']),
            ]);
        } catch (\Throwable $e) {
            $this->redirectWithMsg('/admin/configuracao/assinatura-digital', 'Falha ao salvar: ' . $e->getMessage(), 'error');
            return;
        }

        $this->redirectWithMsg('/admin/configuracao/assinatura-digital', 'Assinatura digital salva.', 'success');
    }

    public function create(): void
    {
        if (!$this->model->schemaReady()) {
            $this->renderNotReady();
            return;
        }

        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        $tipo = $_GET['tipo'] ?? 'nova';
        $prefill = [];
        $responsaveis = [];
        if ($alunoId > 0) {
            $prefill = $this->service->prefillFromAluno($alunoId);
            if ($tipo === '' || $tipo === 'nova') {
                $tipo = 'rematricula';
            }
            $responsaveis = $this->db->fetchAll(
                "SELECT r.id, r.nome, r.cpf, r.email, r.telefone,
                        ar.tipo_vinculo, ar.parentesco, ar.is_financeiro,
                        ar.responsavel_pedagogico AS is_pedagogico,
                        ar.profissao, ar.empresa
                 FROM responsaveis r
                 INNER JOIN alunos_responsaveis ar ON ar.responsavel_id = r.id
                 WHERE ar.aluno_id = ? AND ar.ativo = 1
                 ORDER BY ar.is_financeiro DESC, r.nome ASC",
                [$alunoId]
            ) ?: [];
        }

        $this->viewWithLayout('admin', 'admin/matricula/create', [
            'title' => 'Nova Matrícula — EducaTudo',
            'current_page' => 'enrollment',
            'user' => $this->auth->getUser(),
            'prefill' => $prefill,
            'tipo' => $tipo,
            'anos_letivos' => $this->service->getAnosLetivos(),
            'turmas' => $this->service->getTurmas(),
            'planos_financeiros' => $this->service->listarPlanosFinanceiros(),
            'descontos' => $this->service->listarRegrasDesconto(),
            'config' => $this->service->getConfigAssinaturaEscola(),
            'responsaveis' => $responsaveis,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function store(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/create', 'Token inválido.', 'error');
            return;
        }

        if (!$this->model->schemaReady()) {
            $this->redirectWithMsg('/admin/enrollment/create', 'Módulo de matrícula ainda não está pronto. Rode as migrations no Master.', 'error');
            return;
        }

        $user = $this->auth->getUser();
        $data = $this->payloadFromPost($user);
        if ($data['aluno_nome'] === '') {
            $this->redirectWithMsg('/admin/enrollment/create', 'Nome do aluno é obrigatório.', 'error');
            return;
        }

        try {
            $id = $this->model->create($data);
            $this->model->transition($id, 'rascunho', $user, 'criacao');
            $this->service->sincronizarResponsaveisEProdutos($id, $_POST);
        } catch (\Throwable $e) {
            error_log('MatriculaAdminController store: ' . $e->getMessage());
            $this->redirectWithMsg('/admin/enrollment/create', 'Não foi possível salvar a matrícula. Tente de novo.', 'error');
            return;
        }

        $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'Processo criado. Continue para o contrato.', 'success');
    }

    public function show(int $id): void
    {
        $enrollment = $this->model->findById($id);
        if (!$enrollment) {
            $this->redirect('/admin/enrollment');
            return;
        }

        $whatsappUrl = '';
        if (!empty($enrollment['resp_telefone']) && !empty($enrollment['contrato_token'])) {
            $whatsappUrl = $this->service->buildWhatsAppLink($enrollment, URL);
        }

        $financeContract = null;
        $financeInstallments = [];
        try {
            $financeContract = $this->db->fetch(
                'SELECT * FROM finance_contracts WHERE enrollment_id = ? ORDER BY id DESC LIMIT 1',
                [$id]
            ) ?: null;
            if ($financeContract) {
                $financeInstallments = $this->db->fetchAll(
                    'SELECT * FROM finance_installments WHERE contract_id = ? ORDER BY data_vencimento',
                    [$financeContract['id']]
                ) ?: [];
            }
        } catch (\Throwable $e) {
            // ok
        }

        $produtos = $this->model->listarProdutos($id);
        $documentos = $this->model->listarDocumentos($id);
        $zs = new ZapSignService($this->db);
        $focoContrato = ($_GET['foco'] ?? '') === 'contrato'
            || !in_array((string) ($enrollment['status'] ?? ''), ['enturmada', 'cancelada'], true);

        $checklistSvc = new MatriculaChecklistService($this->db);
        $tipoProc = (string) ($enrollment['tipo'] ?? 'nova');
        $checklistItens = $checklistSvc->listarPorTipo($tipoProc);
        $checklistFaltando = $checklistSvc->faltandoObrigatorios($tipoProc, $documentos);
        $vagasResumo = null;
        $turmaId = (int) ($enrollment['turma_id'] ?? 0);
        if ($turmaId > 0) {
            $vagasResumo = (new MatriculaVagaService($this->db))->resumo($turmaId);
        }

        $this->viewWithLayout('admin', 'admin/matricula/show', [
            'title' => 'Matrícula #' . $id . ' — EducaTudo',
            'current_page' => 'enrollment',
            'user' => $this->auth->getUser(),
            'enrollment' => $enrollment,
            'audit' => $this->model->getAuditTrail($id),
            'whatsapp_url' => $whatsappUrl,
            'foco_contrato' => $focoContrato,
            'produtos' => $produtos,
            'documentos' => $documentos,
            'zapsign_ativo' => $zs->estaAtivo(),
            'contratos_processo' => $this->service->listarContratosDoProcesso($id),
            'faltando_enturmar' => $this->service->camposFaltandoParaEfetivar($enrollment),
            'checklist_itens' => $checklistItens,
            'checklist_faltando' => $checklistFaltando,
            'vagas_resumo' => $vagasResumo,
            'csrf_token' => $this->generateCsrfToken(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? '',
            'finance_contract' => $financeContract,
            'finance_contract_installments' => $financeInstallments,
            'planos_financeiros' => $this->service->listarPlanosFinanceiros((int) ($enrollment['ano_letivo_id'] ?? 0) ?: null),
        ]);
    }

    public function edit(int $id): void
    {
        $enrollment = $this->model->findById($id);
        if (!$enrollment) {
            $this->redirect('/admin/enrollment');
            return;
        }
        if (in_array($enrollment['status'] ?? '', ['enturmada', 'cancelada'], true)) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Não é possível editar neste status.', 'error');
            return;
        }

        $prefill = $enrollment;
        if (!empty($prefill['aluno_data_nasc'])) {
            $prefill['aluno_data_nasc'] = substr((string) $prefill['aluno_data_nasc'], 0, 10);
        }

        $responsaveis = $this->model->listarResponsaveis($id);
        if ($responsaveis === [] && trim((string) ($enrollment['resp_nome'] ?? '')) !== '') {
            $responsaveis[] = [
                'nome' => $enrollment['resp_nome'],
                'cpf' => $enrollment['resp_cpf'] ?? '',
                'email' => $enrollment['resp_email'] ?? '',
                'telefone' => $enrollment['resp_telefone'] ?? '',
                'tipo_vinculo' => $enrollment['resp_parentesco'] ?? '',
                'endereco' => $enrollment['resp_endereco'] ?? '',
                'is_financeiro' => 1,
                'is_pedagogico' => 1,
            ];
        } else {
            foreach ($responsaveis as &$r) {
                if (empty($r['cpf']) && !empty($r['documento'])) {
                    $r['cpf'] = $r['documento'];
                }
            }
            unset($r);
        }

        $faltando = $this->service->camposFaltandoParaEfetivar($enrollment);
        $passo = (int) ($_GET['passo'] ?? 0);
        if ($passo < 1 || $passo > 5) {
            $passo = $this->passoWizardDosCamposFaltando($faltando);
        }

        $this->viewWithLayout('admin', 'admin/matricula/create', [
            'title' => 'Editar Matrícula #' . $id . ' — EducaTudo',
            'current_page' => 'enrollment',
            'user' => $this->auth->getUser(),
            'prefill' => $prefill,
            'tipo' => $enrollment['tipo'] ?? 'nova',
            'anos_letivos' => $this->service->getAnosLetivos(),
            'turmas' => $this->service->getTurmas(),
            'planos_financeiros' => $this->service->listarPlanosFinanceiros((int) ($enrollment['ano_letivo_id'] ?? 0) ?: null),
            'descontos' => $this->service->listarRegrasDesconto(),
            'config' => $this->service->getConfigAssinaturaEscola(),
            'responsaveis' => $responsaveis,
            'modo_edicao' => true,
            'enrollment_id' => $id,
            'faltando_enturmar' => $faltando,
            'passo_inicial' => $passo,
            'csrf_token' => $this->generateCsrfToken(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? '',
        ]);
    }

    public function update(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '/edit', 'Token inválido.', 'error');
            return;
        }
        $enrollment = $this->model->findById($id);
        if (!$enrollment) {
            $this->redirect('/admin/enrollment');
            return;
        }
        if (in_array($enrollment['status'] ?? '', ['enturmada', 'cancelada'], true)) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Não é possível editar neste status.', 'error');
            return;
        }

        $user = $this->auth->getUser();
        $data = $this->payloadFromPost($user);
        unset($data['status'], $data['criado_por'], $data['expira_em'], $data['documento_assinatura_codigo']);
        if ($data['aluno_nome'] === '') {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '/edit', 'Nome do aluno é obrigatório.', 'error');
            return;
        }

        try {
            $this->model->update($id, $data);
            $this->service->sincronizarResponsaveisEProdutos($id, $_POST);
        } catch (\Throwable $e) {
            error_log('MatriculaAdminController update: ' . $e->getMessage());
            $this->redirectWithMsg('/admin/enrollment/' . $id . '/edit', 'Não foi possível salvar as alterações. Tente de novo.', 'error');
            return;
        }

        $this->redirectWithMsg('/admin/enrollment/' . $id, 'Processo atualizado.', 'success');
    }

    public function gerarContrato(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Token inválido.', 'error');
            return;
        }

        $enrollment = $this->model->findById($id);
        if (!$enrollment) {
            $this->redirect('/admin/enrollment');
            return;
        }

        $statusAtual = (string) ($enrollment['status'] ?? '');
        if (in_array($statusAtual, ['enturmada', 'cancelada'], true)) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Não é possível gerar contrato neste status.', 'error');
            return;
        }
        if (!$this->service->podeTransicionar($statusAtual, 'aguardando_assinatura')
            && $statusAtual !== 'aguardando_assinatura') {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Transição não permitida a partir de "' . $statusAtual . '".', 'error');
            return;
        }

        $check = $this->service->validarContratoFinanceiroAntesAssinatura($enrollment);
        if (empty($check['ok'])) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, (string) ($check['mensagem'] ?? 'Plano financeiro obrigatório.'), 'error');
            return;
        }

        if (empty($enrollment['contrato_token'])) {
            $this->service->generateContratoToken($id);
        }

        $enrollment = $this->model->findById($id);
        $escola = $this->service->getEscola();
        $pdfPath = $this->service->gerarContratoPDF($enrollment, $escola);

        $user = $this->auth->getUser();
        $this->model->transition($id, 'aguardando_assinatura', $user, 'contrato_gerado');

        $cfg = $this->service->resolverConfigAssinatura($enrollment);
        $msg = $cfg['documento_rotulo'] . ' gerado. Envie o link ao responsável.';

        try {
            $zs = new ZapSignService($this->db);
            if ($zs->estaAtivo()) {
                $enrollment = $this->model->findById($id) ?: $enrollment;
                $zsResult = $zs->enviarContratoMatricula($enrollment, $pdfPath);
                if (!empty($zsResult['ok'])) {
                    $msg = $cfg['documento_rotulo'] . ' gerado e enviado à ZapSign.';
                    $this->model->transition($id, 'aguardando_assinatura', $user, 'zapsign_enviado');
                } else {
                    $msg .= ' (ZapSign: ' . mb_substr((string) ($zsResult['message'] ?? 'falha'), 0, 120) . ')';
                }
            }
        } catch (\Throwable $e) {
            error_log('[MatriculaAdminController::gerarContrato] ZapSign: ' . $e->getMessage());
            $msg .= ' (ZapSign indisponível)';
        }

        $this->redirectWithMsg('/admin/enrollment/' . $id, $msg, 'success');
    }

    public function gerarContratoRegra(int $id, int $regraId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'Token inválido.', 'error');
            return;
        }

        $enrollment = $this->model->findById($id);
        if (!$enrollment) {
            $this->redirect('/admin/enrollment');
            return;
        }

        $statusAtual = (string) ($enrollment['status'] ?? '');
        if (in_array($statusAtual, ['enturmada', 'cancelada'], true)) {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'Não é possível gerar contrato neste status.', 'error');
            return;
        }

        $check = $this->service->validarContratoFinanceiroAntesAssinatura($enrollment);
        if (empty($check['ok'])) {
            $this->redirectWithMsg(
                '/admin/enrollment/' . $id . '?foco=contrato',
                (string) ($check['mensagem'] ?? 'Plano financeiro obrigatório.'),
                'error'
            );
            return;
        }

        if (empty($enrollment['contrato_token'])) {
            $this->service->generateContratoToken($id);
            $enrollment = $this->model->findById($id) ?: $enrollment;
        }

        $enviarZap = !empty($_POST['enviar_zapsign']);
        $escola = $this->service->getEscola();
        try {
            $result = $this->service->gerarContratoPorRegra($enrollment, $escola, $regraId, $enviarZap);
        } catch (\Throwable $e) {
            error_log('[MatriculaAdminController::gerarContratoRegra] ' . $e->getMessage());
            $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'Falha ao gerar contrato.', 'error');
            return;
        }

        $user = $this->auth->getUser();
        $tipoRegra = '';
        foreach ($this->service->listarRegrasContrato(false) as $r) {
            if ((int) ($r['id'] ?? 0) === $regraId) {
                $tipoRegra = (string) ($r['tipo'] ?? '');
                break;
            }
        }
        if (!empty($result['ok'])
            && $tipoRegra === 'matricula'
            && $this->service->podeTransicionar($statusAtual, 'aguardando_assinatura')) {
            $this->model->transition($id, 'aguardando_assinatura', $user, $enviarZap ? 'zapsign_enviado' : 'contrato_gerado');
        }

        $this->redirectWithMsg(
            '/admin/enrollment/' . $id . '?foco=contrato',
            (string) ($result['message'] ?? 'Contrato processado.'),
            !empty($result['ok']) ? 'success' : 'error'
        );
    }

    public function downloadContratoRegra(int $id, int $contratoId): void
    {
        $enrollment = $this->model->findById($id);
        if (!$enrollment) {
            $this->redirect('/admin/enrollment');
            return;
        }
        try {
            $row = $this->db->fetch(
                'SELECT * FROM matricula_processos_contratos WHERE id = ? AND enrollment_id = ? LIMIT 1',
                [$contratoId, $id]
            );
        } catch (\Throwable $e) {
            $row = null;
        }
        if (!$row || empty($row['pdf_path'])) {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'PDF não encontrado.', 'error');
            return;
        }
        $path = $this->resolverPathStorageSeguro((string) $row['pdf_path']);
        if ($path === null) {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'Arquivo não encontrado.', 'error');
            return;
        }
        $slug = preg_replace('/[^a-z0-9_]+/i', '_', (string) ($row['tipo'] ?? 'contrato'));
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="contrato_' . $slug . '_' . $id . '.pdf"');
        readfile($path);
        exit;
    }

    public function downloadContrato(int $id): void
    {
        $enrollment = $this->model->findById($id);
        if (!$enrollment || empty($enrollment['contrato_pdf_path'])) {
            $this->redirect('/admin/enrollment/' . $id);
            return;
        }
        $path = $this->resolverPathStorageSeguro((string) $enrollment['contrato_pdf_path']);
        if ($path === null) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Arquivo não encontrado.', 'error');
            return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="contrato_' . $id . '.pdf"');
        readfile($path);
        exit;
    }

    public function sincronizarZapSign(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Token inválido.', 'error');
            return;
        }
        $enrollment = $this->model->findById($id);
        if (!$enrollment) {
            $this->redirect('/admin/enrollment');
            return;
        }
        $zs = new ZapSignService($this->db);
        $result = $zs->sincronizarDocumentoMatricula($enrollment);
        $type = !empty($result['ok']) ? 'success' : 'error';
        $this->redirectWithMsg('/admin/enrollment/' . $id, (string) ($result['message'] ?? 'Sync ZapSign'), $type);
    }

    public function transicionar(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Token inválido.', 'error');
            return;
        }

        $novoStatus = (string) ($_POST['novo_status'] ?? '');
        $enrollment = $this->model->findById($id);
        if (!$enrollment) {
            $this->redirect('/admin/enrollment');
            return;
        }

        $user = $this->auth->getUser();
        $statusAtual = (string) ($enrollment['status'] ?? '');

        // Atalhos de enturmação
        if ($novoStatus === 'enturmada' || ($_POST['acao'] ?? '') === 'enturmar_apos_assinatura' || ($_POST['acao'] ?? '') === 'efetivar_sem_assinatura') {
            try {
                $result = $this->service->enturmarProcesso($id, $user, $statusAtual);
                $this->redirectWithMsg('/admin/enrollment/' . $id, $result['mensagem'] ?? 'Enturmado.', 'success');
            } catch (\Throwable $e) {
                $this->redirectWithMsg('/admin/enrollment/' . $id, $e->getMessage(), 'error');
            }
            return;
        }

        if (!$this->service->podeTransicionar($statusAtual, $novoStatus)) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Transição não permitida.', 'error');
            return;
        }

        $this->model->transition($id, $novoStatus, $user, $_POST['acao'] ?? 'alteracao_manual');
        $this->redirectWithMsg('/admin/enrollment/' . $id, 'Status atualizado para: ' . $novoStatus, 'success');
    }

    public function cancelar(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Token inválido.', 'error');
            return;
        }
        $proc = $this->model->findById($id);
        if (!$proc) {
            $this->redirect('/admin/enrollment');
            return;
        }
        $user = $this->auth->getUser();
        $this->model->transition($id, 'cancelada', $user, 'cancelamento_secretaria');
        $turmaId = (int) ($proc['turma_id'] ?? 0);
        if ($turmaId > 0) {
            (new MatriculaVagaService($this->db))->aoLiberarVaga($turmaId, $user);
        }
        $this->redirectWithMsg('/admin/enrollment/' . $id, 'Processo cancelado.', 'success');
    }

    public function oferecerVaga(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Token inválido.', 'error');
            return;
        }
        $proc = $this->model->findById($id);
        if (!$proc) {
            $this->redirect('/admin/enrollment');
            return;
        }
        try {
            $vaga = new MatriculaVagaService($this->db);
            $vaga->oferecerVaga((int) ($proc['turma_id'] ?? 0), $id, $this->auth->getUser());
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Vaga oferecida. O processo saiu da fila.', 'success');
        } catch (\InvalidArgumentException $e) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, $e->getMessage(), 'error');
        } catch (\Throwable $e) {
            error_log('MatriculaAdminController oferecerVaga: ' . $e->getMessage());
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Não foi possível oferecer a vaga.', 'error');
        }
    }

    public function rematriculaLoteForm(): void
    {
        if (!$this->model->schemaReady()) {
            $this->renderNotReady();
            return;
        }
        $turmaOrigemId = (int) ($_GET['turma_origem_id'] ?? 0);
        $alunos = $turmaOrigemId > 0 ? $this->service->listarAlunosPorTurma($turmaOrigemId) : [];

        $this->viewWithLayout('admin', 'admin/matricula/rematricula-lote', [
            'title' => 'Rematrícula em lote — EducaTudo',
            'current_page' => 'enrollment',
            'user' => $this->auth->getUser(),
            'anos_letivos' => $this->service->getAnosLetivos(),
            'turmas' => $this->service->getTurmas(),
            'turma_origem_id' => $turmaOrigemId,
            'alunos' => $alunos,
            'planos_financeiros' => $this->service->listarPlanosFinanceiros(),
            'csrf_token' => $this->generateCsrfToken(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? '',
        ]);
    }

    public function rematriculaLoteStore(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/rematricula-lote', 'Token inválido.', 'error');
            return;
        }

        $turmaOrigemId = (int) ($_POST['turma_origem_id'] ?? 0);
        $anoDestinoId = (int) ($_POST['ano_letivo_id'] ?? 0);
        $turmaDestinoId = (int) ($_POST['turma_destino_id'] ?? 0) ?: null;
        $planId = (int) ($_POST['finance_plan_id'] ?? 0) ?: null;
        $alunoIds = array_values(array_filter(array_map('intval', (array) ($_POST['aluno_ids'] ?? [])), static fn ($id) => $id > 0));

        if ($turmaOrigemId <= 0 || $anoDestinoId <= 0 || $alunoIds === []) {
            $this->redirectWithMsg('/admin/enrollment/rematricula-lote?turma_origem_id=' . $turmaOrigemId, 'Selecione turma, ano e alunos.', 'error');
            return;
        }

        $permitidos = [];
        foreach ($this->service->listarAlunosPorTurma($turmaOrigemId) as $row) {
            $permitidos[(int) $row['id']] = true;
        }
        $alunoIds = array_values(array_filter($alunoIds, static fn ($id) => isset($permitidos[$id])));

        $user = $this->auth->getUser();
        $criados = 0;
        $pulados = 0;

        foreach ($alunoIds as $alunoId) {
            $existe = $this->db->fetch(
                "SELECT id FROM matricula_processos
                 WHERE aluno_id = ? AND tipo = 'rematricula' AND ano_letivo_id = ?
                   AND status NOT IN ('cancelada','abandonada') LIMIT 1",
                [$alunoId, $anoDestinoId]
            );
            if ($existe) {
                $pulados++;
                continue;
            }

            $prefill = $this->service->prefillFromAluno($alunoId);
            if (empty($prefill['aluno_nome'])) {
                $pulados++;
                continue;
            }

            $payload = [
                'tipo' => 'rematricula',
                'status' => 'rascunho',
                'origem' => 'interno',
                'aluno_id' => $alunoId,
                'ano_letivo_id' => $anoDestinoId,
                'turma_id' => $turmaDestinoId,
                'aluno_nome' => $prefill['aluno_nome'],
                'aluno_cpf' => $prefill['aluno_cpf'] ?? null,
                'aluno_data_nasc' => $prefill['aluno_data_nasc'] ?? null,
                'aluno_email' => $prefill['aluno_email'] ?? null,
                'aluno_telefone' => $prefill['aluno_telefone'] ?? null,
                'resp_nome' => $prefill['resp_nome'] ?? '',
                'resp_cpf' => $prefill['resp_cpf'] ?? null,
                'resp_email' => $prefill['resp_email'] ?? null,
                'resp_telefone' => $prefill['resp_telefone'] ?? null,
                'resp_parentesco' => $prefill['resp_parentesco'] ?? null,
                'resp_endereco' => $prefill['resp_endereco'] ?? null,
                'criado_por' => $user['id'] ?? null,
            ];
            if ($planId) {
                $payload['finance_plan_id'] = $planId;
                $payload['finance_cobrancas'] = json_encode([
                    ['tipo' => 'mensalidade', 'plan_id' => $planId, 'desconto_rule_ids' => []],
                ], JSON_UNESCAPED_UNICODE);
            }

            $newId = $this->model->create($payload);
            $this->model->transition($newId, 'rascunho', $user, 'rematricula_lote');
            if ($planId) {
                $this->service->sincronizarResponsaveisEProdutos($newId, [
                    'finance_plan_id' => $planId,
                    'resp_nome' => $payload['resp_nome'],
                    'resp_cpf' => $payload['resp_cpf'],
                    'resp_email' => $payload['resp_email'],
                    'resp_telefone' => $payload['resp_telefone'],
                    'resp_parentesco' => $payload['resp_parentesco'],
                    'resp_endereco' => $payload['resp_endereco'],
                ]);
            }
            $criados++;
        }

        $msg = "Rematrícula em lote: {$criados} processo(s) criado(s)";
        if ($pulados > 0) {
            $msg .= ", {$pulados} ignorado(s)";
        }
        $this->redirectWithMsg('/admin/enrollment?tipo=rematricula&ano_letivo_id=' . $anoDestinoId, $msg . '.', 'success');
    }

    public function uploadDocumento(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Token inválido.', 'error');
            return;
        }
        $tipoDoc = trim((string) ($_POST['tipo_documento'] ?? 'outro')) ?: 'outro';
        $tiposOk = ['rg', 'cpf', 'comprovante_residencia', 'historico', 'certidao', 'declaracao_transferencia', 'contrato_assinado', 'outro'];
        if (!in_array($tipoDoc, $tiposOk, true)) {
            $tipoDoc = 'outro';
        }
        if ($tipoDoc === 'contrato_assinado') {
            $this->uploadContratoAssinado($id);
            return;
        }
        if (!$this->model->temTabelaDocumentos() || empty($_FILES['documento']['tmp_name'])) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Upload inválido.', 'error');
            return;
        }

        $tmp = $_FILES['documento']['tmp_name'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Tipo de arquivo não permitido.', 'error');
            return;
        }
        if ((int) ($_FILES['documento']['size'] ?? 0) > 10 * 1024 * 1024) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Arquivo maior que 10MB.', 'error');
            return;
        }

        $tenant = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '_', TENANT_SLUG) : 'escola';
        $ext = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $relDir = 'storage/enrollments/' . $tenant . '/docs/';
        $absDir = dirname(__DIR__, 4) . '/' . $relDir;
        if (!is_dir($absDir)) {
            mkdir($absDir, 0775, true);
        }
        $filename = 'doc_' . $id . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($tmp, $absDir . $filename)) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Falha ao salvar arquivo.', 'error');
            return;
        }

        $user = $this->auth->getUser();
        $this->model->adicionarDocumento($id, [
            'tipo' => $tipoDoc,
            'nome_original' => (string) ($_FILES['documento']['name'] ?? $filename),
            'path' => $relDir . $filename,
            'mime' => $mime,
            'tamanho' => (int) ($_FILES['documento']['size'] ?? 0),
            'criado_por' => $user['id'] ?? null,
        ]);

        $alunoIdProc = (int) ($this->model->findById($id)['aluno_id'] ?? 0);
        if ($alunoIdProc > 0) {
            require_once dirname(__DIR__, 2) . '/vida-escolar/Services/ProntuarioVidaEscolarService.php';
            (new \App\Modulos\VidaEscolar\Services\ProntuarioVidaEscolarService())->reconhecerEntregasExternas($alunoIdProc);
        }

        $this->redirectWithMsg('/admin/enrollment/' . $id, 'Documento anexado.', 'success');
    }

    public function removerDocumento(int $id, int $docId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id, 'Token inválido.', 'error');
            return;
        }
        $doc = $this->model->removerDocumento($id, $docId);
        if ($doc && !empty($doc['path'])) {
            $abs = dirname(__DIR__, 4) . '/' . ltrim((string) $doc['path'], '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        $this->redirectWithMsg('/admin/enrollment/' . $id, 'Documento removido.', 'success');
    }

    public function uploadContratoAssinado(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'Token inválido.', 'error');
            return;
        }
        $enrollment = $this->model->findById($id);
        if (!$enrollment) {
            $this->redirect('/admin/enrollment');
            return;
        }

        $statusAtual = (string) ($enrollment['status'] ?? '');
        if (in_array($statusAtual, ['cancelada', 'abandonada', 'enturmada'], true)) {
            $this->redirectWithMsg(
                '/admin/enrollment/' . $id . '?foco=contrato',
                'Não é possível registrar assinatura neste status (' . $statusAtual . ').',
                'error'
            );
            return;
        }
        if (!$this->service->podeTransicionar($statusAtual, 'confirmada')) {
            $this->redirectWithMsg(
                '/admin/enrollment/' . $id . '?foco=contrato',
                'Transição de status inválida para confirmar assinatura.',
                'error'
            );
            return;
        }

        if (empty($_FILES['documento']['tmp_name'])) {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'Selecione o arquivo do contrato assinado.', 'error');
            return;
        }

        $tmp = $_FILES['documento']['tmp_name'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'Tipo de arquivo não permitido.', 'error');
            return;
        }
        if ((int) ($_FILES['documento']['size'] ?? 0) > 10 * 1024 * 1024) {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'Arquivo maior que 10MB.', 'error');
            return;
        }

        $tenant = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '_', TENANT_SLUG) : 'escola';
        $ext = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $relDir = 'storage/enrollments/' . $tenant . '/docs/';
        $absDir = dirname(__DIR__, 4) . '/' . $relDir;
        if (!is_dir($absDir)) {
            mkdir($absDir, 0775, true);
        }
        $filename = 'contrato_assinado_' . $id . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($tmp, $absDir . $filename)) {
            $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'Falha ao salvar arquivo.', 'error');
            return;
        }

        $relPath = $relDir . $filename;
        $user = $this->auth->getUser();

        if ($this->model->temTabelaDocumentos()) {
            $this->model->adicionarDocumento($id, [
                'tipo' => 'contrato_assinado',
                'nome_original' => (string) ($_FILES['documento']['name'] ?? $filename),
                'path' => $relPath,
                'mime' => $mime,
                'tamanho' => (int) ($_FILES['documento']['size'] ?? 0),
                'criado_por' => $user['id'] ?? null,
            ]);
        }

        $upd = [
            'assinado_em' => date('Y-m-d H:i:s'),
            'assinante_nome' => trim((string) ($_POST['assinante_nome'] ?? $enrollment['resp_nome'] ?? 'Assinatura manual')) ?: 'Assinatura manual',
            'assinante_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];
        if ($this->model->temColuna('contrato_assinado_path')) {
            $upd['contrato_assinado_path'] = $relPath;
        }
        $this->model->update($id, $upd);

        if ($statusAtual !== 'confirmada') {
            $this->model->transition($id, 'confirmada', $user, 'assinatura_manual');
        }

        $this->redirectWithMsg('/admin/enrollment/' . $id . '?foco=contrato', 'Assinatura manual registrada.', 'success');
    }

    public function planoItens(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = $this->service->listarItensPlano($id);
        if (empty($data['plano'])) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Plano não encontrado.', 'itens' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode([
            'ok' => true,
            'plano' => [
                'id' => (int) $data['plano']['id'],
                'nome' => (string) ($data['plano']['nome'] ?? ''),
            ],
            'itens' => $data['itens'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function scorePanel(): void
    {
        $ciclo = (int) ($_GET['ciclo'] ?? date('Y') + 1);
        $faixaFiltro = $_GET['faixa'] ?? '';
        $scoreService = new MatriculaScoreService($this->db);
        $alunos = $scoreService->listarPorFaixa($ciclo, $faixaFiltro);
        $resumo = [
            'verde' => count(array_filter($alunos, static fn ($a) => ($a['faixa'] ?? '') === 'verde')),
            'amarelo' => count(array_filter($alunos, static fn ($a) => ($a['faixa'] ?? '') === 'amarelo')),
            'vermelho' => count(array_filter($alunos, static fn ($a) => ($a['faixa'] ?? '') === 'vermelho')),
        ];

        $this->viewWithLayout('admin', 'admin/matricula/score', [
            'title' => 'Score de Rematrícula — EducaTudo',
            'current_page' => 'enrollment',
            'user' => $this->auth->getUser(),
            'alunos' => $alunos,
            'resumo' => $resumo,
            'ciclo' => $ciclo,
            'faixa_filtro' => $faixaFiltro,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function recalcularScores(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/enrollment/score');
            return;
        }
        $ciclo = (int) ($_POST['ciclo'] ?? date('Y') + 1);
        $anoLetivoId = (int) ($_POST['ano_letivo_id'] ?? 0) ?: null;
        $scoreService = new MatriculaScoreService($this->db);
        $alunoIds = array_column(
            $this->db->fetchAll('SELECT id FROM alunos WHERE ativo = 1 LIMIT 500') ?: [],
            'id'
        );
        $scoreService->calcularLote($alunoIds, $ciclo, $anoLetivoId);
        $this->redirect('/admin/enrollment/score?ciclo=' . $ciclo . '&msg=Scores+recalculados');
    }

    /** @return array<string,mixed> */
    private function payloadFromPost(array $user): array
    {
        $origens = ['interno', 'site', 'whatsapp', 'indicacao', 'evento', 'rede_social', 'outros'];
        $origem = in_array($_POST['origem'] ?? '', $origens, true) ? $_POST['origem'] : 'interno';

        $alunoEndereco = trim((string) ($_POST['aluno_endereco'] ?? ''));
        $endParts = [
            'aluno_endereco' => $alunoEndereco,
            'aluno_end_numero' => trim((string) ($_POST['aluno_end_numero'] ?? '')),
            'aluno_end_complemento' => trim((string) ($_POST['aluno_end_complemento'] ?? '')),
            'aluno_end_bairro' => trim((string) ($_POST['aluno_end_bairro'] ?? '')),
            'aluno_end_cidade' => trim((string) ($_POST['aluno_end_cidade'] ?? '')),
            'aluno_end_uf' => strtoupper(trim((string) ($_POST['aluno_end_uf'] ?? ''))),
            'aluno_end_cep' => trim((string) ($_POST['aluno_end_cep'] ?? '')),
        ];
        if ($alunoEndereco === '') {
            $montado = $this->service->montarEnderecoAluno($endParts);
            if ($montado !== '') {
                $alunoEndereco = $montado;
            }
        }

        $respNome = trim((string) ($_POST['resp_nome'] ?? ''));
        $respCpf = trim((string) ($_POST['resp_cpf'] ?? '')) ?: null;
        $respEmail = trim((string) ($_POST['resp_email'] ?? '')) ?: null;
        $respTelefone = trim((string) ($_POST['resp_telefone'] ?? '')) ?: null;
        $respParentesco = trim((string) ($_POST['resp_parentesco'] ?? '')) ?: null;
        $respEndereco = trim((string) ($_POST['resp_endereco'] ?? '')) ?: null;

        $listaResp = $this->service->extrairResponsaveisDoPost($_POST);
        if ($listaResp !== []) {
            $primario = null;
            foreach ($listaResp as $r) {
                if (!empty($r['is_pedagogico'])) {
                    $primario = $r;
                    break;
                }
            }
            if ($primario === null) {
                $primario = $listaResp[0];
            }
            $respNome = trim((string) ($primario['nome'] ?? '')) ?: $respNome;
            $respCpf = trim((string) ($primario['documento'] ?? $primario['cpf'] ?? '')) ?: $respCpf;
            $respEmail = trim((string) ($primario['email'] ?? '')) ?: $respEmail;
            $respTelefone = trim((string) ($primario['telefone'] ?? '')) ?: $respTelefone;
            $respParentesco = trim((string) ($primario['tipo_vinculo'] ?? '')) ?: $respParentesco;
            $respEndereco = trim((string) ($primario['endereco'] ?? '')) ?: $respEndereco;
        }

        return [
            'tipo' => in_array($_POST['tipo'] ?? '', ['nova', 'rematricula', 'transferencia'], true) ? $_POST['tipo'] : 'nova',
            'status' => 'rascunho',
            'aluno_id' => (int) ($_POST['aluno_id'] ?? 0) ?: null,
            'ano_letivo_id' => (int) ($_POST['ano_letivo_id'] ?? 0) ?: null,
            'turma_id' => (int) ($_POST['turma_id'] ?? 0) ?: null,
            'serie_id' => (int) ($_POST['serie_id'] ?? 0) ?: null,
            'aluno_nome' => trim($_POST['aluno_nome'] ?? ''),
            'aluno_cpf' => trim($_POST['aluno_cpf'] ?? '') ?: null,
            'aluno_rg' => trim($_POST['aluno_rg'] ?? '') ?: null,
            'aluno_data_nasc' => trim($_POST['aluno_data_nasc'] ?? '') ?: null,
            'aluno_genero' => trim($_POST['aluno_genero'] ?? '') ?: null,
            'aluno_email' => trim($_POST['aluno_email'] ?? '') ?: null,
            'aluno_telefone' => trim($_POST['aluno_telefone'] ?? '') ?: null,
            'aluno_endereco' => $alunoEndereco !== '' ? $alunoEndereco : null,
            'aluno_end_numero' => $endParts['aluno_end_numero'] !== '' ? $endParts['aluno_end_numero'] : null,
            'aluno_end_complemento' => $endParts['aluno_end_complemento'] !== '' ? $endParts['aluno_end_complemento'] : null,
            'aluno_end_bairro' => $endParts['aluno_end_bairro'] !== '' ? $endParts['aluno_end_bairro'] : null,
            'aluno_end_cidade' => $endParts['aluno_end_cidade'] !== '' ? $endParts['aluno_end_cidade'] : null,
            'aluno_end_uf' => $endParts['aluno_end_uf'] !== '' ? substr($endParts['aluno_end_uf'], 0, 2) : null,
            'aluno_end_cep' => $endParts['aluno_end_cep'] !== '' ? $endParts['aluno_end_cep'] : null,
            'aluno_escola_anterior' => trim((string) ($_POST['aluno_escola_anterior'] ?? '')) ?: null,
            'resp_nome' => $respNome,
            'resp_cpf' => $respCpf,
            'resp_email' => $respEmail,
            'resp_telefone' => $respTelefone,
            'resp_parentesco' => $respParentesco,
            'resp_endereco' => $respEndereco,
            'origem' => $origem,
            'observacoes' => trim($_POST['observacoes'] ?? '') ?: null,
            'expira_em' => !empty($_POST['expira_em']) ? $_POST['expira_em'] . ' 23:59:59' : null,
            'criado_por' => $user['id'] ?? null,
            'documento_assinatura_codigo' => trim((string) ($_POST['documento_assinatura_codigo'] ?? '')) ?: null,
            'aluno_nome_mae' => trim((string) ($_POST['aluno_nome_mae'] ?? '')) ?: null,
            'aluno_nome_pai' => trim((string) ($_POST['aluno_nome_pai'] ?? '')) ?: null,
            'aluno_codigo_inep' => trim((string) ($_POST['aluno_codigo_inep'] ?? '')) ?: null,
            'aluno_cor_raca' => trim((string) ($_POST['aluno_cor_raca'] ?? '')) ?: null,
            'aluno_nacionalidade' => trim((string) ($_POST['aluno_nacionalidade'] ?? '')) ?: null,
        ];
    }

    /** @param list<string> $faltando */
    private function passoWizardDosCamposFaltando(array $faltando): int
    {
        foreach ($faltando as $campo) {
            $campo = (string) $campo;
            if ($campo === 'Ano letivo' || $campo === 'Turma') {
                return 1;
            }
            if (str_starts_with($campo, 'Aluno:')) {
                return 2;
            }
            if (str_starts_with($campo, 'Responsável:')) {
                return 3;
            }
        }
        return 1;
    }

    private function redirectWithMsg(string $url, string $msg, string $type = 'success'): void
    {
        $sep = str_contains($url, '?') ? '&' : '?';
        $this->redirect($url . $sep . 'msg=' . rawurlencode($msg) . '&status_type=' . $type);
    }

    private function renderNotReady(): void
    {
        $this->viewWithLayout('admin', 'admin/matricula/not_ready', [
            'title' => 'Matrículas',
            'current_page' => 'enrollment',
            'user' => $this->auth->getUser(),
        ]);
    }

    private function resolverPathStorageSeguro(string $relative): ?string
    {
        $relative = ltrim(str_replace(['..', '\\'], '', $relative), '/');
        if ($relative === '' || !str_starts_with($relative, 'storage/')) {
            return null;
        }
        $base = realpath(dirname(__DIR__, 4) . '/storage');
        $abs = realpath(dirname(__DIR__, 4) . '/' . $relative);
        if ($base === false || $abs === false || !str_starts_with($abs, $base) || !is_file($abs)) {
            return null;
        }
        return $abs;
    }
}
}
