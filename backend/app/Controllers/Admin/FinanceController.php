<?php

require_once __DIR__ . '/AdminBaseController.php';

use App\Models\Finance\FinanceContract;
use App\Models\Finance\FinanceInstallment;
use App\Models\Finance\FinanceConfig;
use App\Models\Finance\FinanceLedger;
use App\Services\FinanceContractService;
use App\Services\FinanceBoletoService;
use App\Services\FinanceBillingService;
use App\Services\FinanceLedgerService;
use App\Services\FinancePlanService;

if (!class_exists('FinanceController')) {
class FinanceController extends AdminBaseController
{
    private FinanceContract $contracts;
    private FinanceInstallment $installments;
    private FinanceContractService $svc;
    private FinanceBoletoService $boleto;
    private FinanceBillingService $billing;
    private FinanceLedgerService $ledgerSvc;
    private FinancePlanService $planSvc;
    private FinanceConfig $configModel;

    public function __construct()
    {
        parent::__construct();
        $this->contracts    = new FinanceContract($this->db);
        $this->installments = new FinanceInstallment($this->db);
        $this->svc          = new FinanceContractService($this->db);
        $this->boleto       = new FinanceBoletoService($this->db);
        $this->billing      = new FinanceBillingService($this->db);
        $this->ledgerSvc    = new FinanceLedgerService($this->db);
        $this->planSvc      = new FinancePlanService($this->db);
        $this->configModel  = new FinanceConfig($this->db);
    }

    private function schemaGuard(): bool
    {
        if (!$this->contracts->schemaReady()) {
            $this->viewWithLayout('admin', 'admin/finance/not_ready', [
                'title'        => 'Financeiro',
                'page_title'   => 'Financeiro Escolar',
                'user'         => $this->auth->getUser(),
                'current_page' => 'finance',
            ]);
            return false;
        }
        return true;
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): void
    {
        if (!$this->schemaGuard()) return;

        $user        = $this->auth->getUser();
        $anoLetivoId = (int)($_GET['ano_letivo_id'] ?? 0);

        $kpis        = $this->installments->kpis($anoLetivoId ?: null);
        $vencendo    = $this->installments->list(['status' => 'pendente', 'vencimento_ate' => date('Y-m-d', strtotime('+7 days'))], 10);
        $vencidas    = $this->installments->list(['status' => 'vencido'], 10);
        $contratos   = $this->contracts->countsByStatus();
        $anosLetivos = $this->db->fetchAll("SELECT id, ano FROM ano_letivo ORDER BY ano DESC") ?: [];

        // Taxa de inadimplência
        $total = (int)($kpis['qtd_total'] ?? 0);
        $txInad = $total > 0
            ? round(((int)($kpis['qtd_vencidas'] ?? 0) / $total) * 100, 1)
            : 0;

        $this->viewWithLayout('admin', 'admin/finance/dashboard', [
            'title'        => 'Financeiro — EducaTudo',
            'page_title'   => 'Financeiro Escolar',
            'user'         => $user,
            'current_page' => 'finance',
            'kpis'         => $kpis,
            'tx_inadimplencia' => $txInad,
            'vencendo'     => $vencendo,
            'vencidas'     => $vencidas,
            'contratos'    => $contratos,
            'anos_letivos' => $anosLetivos,
            'ano_letivo_id'=> $anoLetivoId,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    // ── Listagem de contratos ────────────────────────────────────────────────

    public function contractIndex(): void
    {
        if (!$this->schemaGuard()) return;

        $filters = [
            'status'        => $_GET['status'] ?? '',
            'ano_letivo_id' => $_GET['ano_letivo_id'] ?? '',
            'q'             => $_GET['q'] ?? '',
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $limit   = 30;
        $offset  = ($page - 1) * $limit;

        $list    = $this->contracts->list($filters, $limit, $offset);
        $total   = $this->contracts->count($filters);
        $anos    = $this->db->fetchAll("SELECT id, ano FROM ano_letivo ORDER BY ano DESC") ?: [];

        $this->viewWithLayout('admin', 'admin/finance/contracts/index', [
            'title'        => 'Contratos Financeiros',
            'page_title'   => 'Contratos',
            'user'         => $this->auth->getUser(),
            'current_page' => 'finance',
            'contracts'    => $list,
            'total'        => $total,
            'page'         => $page,
            'limit'        => $limit,
            'filters'      => $filters,
            'anos_letivos' => $anos,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    // ── Criar contrato (GET) ─────────────────────────────────────────────────

    public function contractCreate(): void
    {
        if (!$this->schemaGuard()) return;

        $alunoId      = (int)($_GET['aluno_id'] ?? 0);
        $enrollmentId = (int)($_GET['enrollment_id'] ?? 0);
        $anoLetivoId  = (int)($_GET['ano_letivo_id'] ?? 0);

        $prefill = $alunoId ? $this->svc->prefillFromAluno($alunoId) : [];

        // Se veio da matrícula, usa os dados da query string como fallback
        if ($enrollmentId && empty($prefill['responsavel'])) {
            $prefill['responsavel'] = [
                'nome'     => $_GET['resp_nome']    ?? '',
                'email'    => $_GET['resp_email']   ?? '',
                'telefone' => $_GET['resp_telefone'] ?? '',
            ];
        }

        // Detecta irmãos usando o ano letivo da matrícula
        $anoLetivoRef = (int)($prefill['ano_letivo']['id'] ?? $anoLetivoId);
        $irmaos = $alunoId && $anoLetivoRef
            ? $this->svc->detectarIrmaos($alunoId, $anoLetivoRef)
            : [];

        $anos      = $this->db->fetchAll("SELECT id, ano FROM ano_letivo ORDER BY ano DESC") ?: [];
        $turmas    = $this->db->fetchAll("SELECT id, nome, serie FROM turmas WHERE ativo = 1 ORDER BY nome") ?: [];
        $discRules = $this->db->fetchAll("SELECT * FROM finance_discount_rules WHERE ativo = 1 ORDER BY tipo, nome") ?: [];
        $cfg       = $this->configModel->get();

        // Planos disponíveis para o ano letivo
        $plans = [];
        try {
            $plans = $this->db->fetchAll(
                "SELECT fp.*, al.ano AS ano_letivo_nome FROM finance_plans fp
                 LEFT JOIN ano_letivo al ON al.id = fp.ano_letivo_id
                 WHERE fp.ativo = 1" . ($anoLetivoRef ? " AND fp.ano_letivo_id = {$anoLetivoRef}" : '') . "
                 ORDER BY fp.nome"
            ) ?: [];
            foreach ($plans as &$p) {
                $p['items'] = $this->db->fetchAll(
                    "SELECT * FROM finance_plan_items WHERE plan_id = ? ORDER BY ordem", [$p['id']]
                ) ?: [];
                $p['total'] = array_sum(array_column($p['items'], 'valor_base'));
            }
        } catch (\Throwable $e) {}

        $this->viewWithLayout('admin', 'admin/finance/contracts/create', [
            'title'          => 'Novo Contrato Financeiro',
            'page_title'     => 'Novo Contrato',
            'user'           => $this->auth->getUser(),
            'current_page'   => 'finance_contracts',
            'prefill'        => $prefill,
            'irmaos'         => $irmaos,
            'anos_letivos'   => $anos,
            'turmas'         => $turmas,
            'discount_rules' => $discRules,
            'plans'          => $plans,
            'config'         => $cfg,
            'aluno_id'       => $alunoId,
            'enrollment_id'  => $enrollmentId,
            'ano_letivo_id'  => $anoLetivoRef,
            'csrf_token'     => $this->generateCsrfToken(),
        ]);
    }

    // ── Salvar novo contrato (POST) ──────────────────────────────────────────

    public function contractStore(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/finance/contracts/create');
            return;
        }

        $user = $this->auth->getUser();

        $planId      = (int)($_POST['plan_id'] ?? 0);
        $enrollmentId= (int)($_POST['enrollment_id'] ?? 0);
        $cfg         = $this->configModel->get();
        $diaVencPad  = (int)($cfg['dia_vencimento_padrao'] ?? 10);

        // Se veio de um plano, calcula o valor bruto a partir dos itens
        $valorBruto = (float)str_replace(',', '.', $_POST['valor_bruto'] ?? '0');
        if ($planId) {
            try {
                $planItems = $this->db->fetchAll("SELECT * FROM finance_plan_items WHERE plan_id = ?", [$planId]) ?: [];
                if ($planItems) $valorBruto = array_sum(array_column($planItems, 'valor_base'));
            } catch (\Throwable $e) {}
        }

        $data = [
            'aluno_id'            => (int)($_POST['aluno_id'] ?? 0),
            'ano_letivo_id'       => (int)($_POST['ano_letivo_id'] ?? 0),
            'enrollment_id'       => $enrollmentId ?: null,
            'plan_id'             => $planId ?: null,
            'responsavel_nome'    => trim($_POST['responsavel_nome'] ?? ''),
            'responsavel_cpf'     => trim($_POST['responsavel_cpf'] ?? '') ?: null,
            'responsavel_email'   => trim($_POST['responsavel_email'] ?? '') ?: null,
            'responsavel_telefone'=> trim($_POST['responsavel_telefone'] ?? '') ?: null,
            'valor_bruto'         => $valorBruto,
            'valor_desconto'      => 0,
            'valor_liquido'       => 0,
            'plano_pagamento'     => 'mensal',
            'num_parcelas'        => (int)($_POST['num_parcelas'] ?? 12),
            'dia_vencimento'      => (int)($_POST['dia_vencimento'] ?? $diaVencPad),
            'mes_inicio'          => (int)($_POST['mes_inicio'] ?? 1),
            'mes_fim'             => (int)($_POST['mes_fim'] ?? 12),
            'observacoes'         => trim($_POST['observacoes'] ?? '') ?: null,
            'status'              => 'ativo',
            'criado_por'          => $user['id'] ?? null,
        ];

        if (!$data['aluno_id'] || !$data['ano_letivo_id']) {
            $this->setFlashMessage('Aluno e Ano Letivo são obrigatórios.', 'error');
            $this->redirect('/admin/finance/contracts/create');
            return;
        }

        // Descontos selecionados
        $descontos  = [];
        $ruleIds    = $_POST['discount_rule_ids'] ?? [];
        if (!empty($ruleIds)) {
            foreach ((array)$ruleIds as $rid) {
                $rule = $this->db->fetch("SELECT * FROM finance_discount_rules WHERE id = ? AND ativo = 1", [(int)$rid]);
                if ($rule) {
                    $va = $rule['calculo'] === 'percentual'
                        ? round($data['valor_bruto'] * ($rule['valor'] / 100), 2)
                        : (float)$rule['valor'];
                    $descontos[] = [
                        'discount_rule_id' => $rule['id'],
                        'tipo'             => $rule['tipo'],
                        'descricao'        => $rule['nome'],
                        'calculo'          => $rule['calculo'],
                        'valor'            => $rule['valor'],
                        'valor_aplicado'   => $va,
                        'status'           => $rule['requer_aprovacao'] ? 'pendente' : 'aprovado',
                        'aprovado_por'     => $rule['requer_aprovacao'] ? null : $user['id'],
                        'aprovado_em'      => $rule['requer_aprovacao'] ? null : date('Y-m-d H:i:s'),
                    ];
                }
            }
        }

        // Desconto de irmãos manual (se informado)
        if (!empty($_POST['desconto_irmaos_ativo'])) {
            $irmaoId  = (int)($_POST['irmao_aluno_id'] ?? 0);
            $posicao  = (int)($_POST['irmao_posicao'] ?? 2);
            $calc     = $this->svc->calcularDescontoIrmaos($posicao, $data['valor_bruto']);
            if ($calc['percentual'] > 0) {
                $descontos[] = [
                    'discount_rule_id' => null,
                    'tipo'             => 'irmaos',
                    'descricao'        => $calc['descricao'],
                    'calculo'          => 'percentual',
                    'valor'            => $calc['percentual'],
                    'valor_aplicado'   => $calc['valor'],
                    'irmao_aluno_id'   => $irmaoId ?: null,
                    'status'           => 'pendente',
                    'aprovado_por'     => null,
                    'aprovado_em'      => null,
                ];
            }
        }

        $totais              = $this->svc->calcularTotais($data['valor_bruto'], $descontos);
        $data['valor_desconto'] = $totais['valor_desconto'];
        $data['valor_liquido']  = $totais['valor_liquido'];

        $contractId = $this->contracts->create($data);

        foreach ($descontos as $d) {
            $d['contract_id'] = $contractId;
            $this->contracts->addDiscount($d);
        }

        // Se veio de um plano, cria os contract_items e gera parcelas por item
        if ($planId) {
            try {
                $pctDesconto = count(array_filter($descontos, fn($d) => $d['status'] === 'aprovado')) > 0
                    ? round(($data['valor_desconto'] / max(1, $valorBruto)) * 100, 2)
                    : 0.0;
                $this->planSvc->aplicarPlanoNoContrato($planId, $contractId, $pctDesconto);
                // Gera parcelas a partir dos contract_items
                $this->gerarParcelasDeItems($contractId, $data['dia_vencimento']);
            } catch (\Throwable $e) {
                // fallback: gera parcelas simples
                $this->svc->gerarParcelas($contractId);
            }
        } else {
            $this->svc->gerarParcelas($contractId);
        }

        $this->svc->audit('contract', $contractId, 'criado', null, $data, $user);

        $qtdParcelas = count($this->installments->byContract($contractId));
        $this->setFlashMessage("Contrato criado com {$qtdParcelas} parcela(s) gerada(s).", 'success');
        $this->redirect("/admin/finance/contracts/{$contractId}");
    }

    // ── Exibir contrato ──────────────────────────────────────────────────────

    public function contractShow(int $id): void
    {
        if (!$this->schemaGuard()) return;

        $contract = $this->contracts->findById($id);
        if (!$contract) {
            $this->setFlashMessage('Contrato não encontrado.', 'error');
            $this->redirect('/admin/finance/contracts');
            return;
        }

        $discounts   = $this->contracts->getDiscounts($id);
        $installments= $this->installments->byContract($id);
        $summary     = $this->contracts->getInstallmentsSummary($id);
        $discRules   = $this->db->fetchAll("SELECT * FROM finance_discount_rules WHERE ativo = 1") ?: [];
        $audit       = $this->db->fetchAll("SELECT * FROM finance_audit WHERE entidade = 'contract' AND entidade_id = ? ORDER BY created_at DESC LIMIT 20", [$id]) ?: [];

        // Encargos por parcela
        foreach ($installments as &$inst) {
            $inst['encargos'] = $this->svc->calcularEncargos($inst);
        }

        $this->viewWithLayout('admin', 'admin/finance/contracts/show', [
            'title'          => 'Contrato #' . $id,
            'page_title'     => 'Contrato Financeiro #' . $id,
            'user'           => $this->auth->getUser(),
            'current_page'   => 'finance',
            'contract'       => $contract,
            'discounts'      => $discounts,
            'installments'   => $installments,
            'summary'        => $summary,
            'discount_rules' => $discRules,
            'audit'          => $audit,
            'csrf_token'     => $this->generateCsrfToken(),
        ]);
    }

    // ── Ativar/cancelar contrato ─────────────────────────────────────────────

    public function contractActivate(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect("/admin/finance/contracts/{$id}"); return; }
        $before = $this->contracts->findById($id);
        $this->contracts->update($id, ['status' => 'ativo']);
        $this->svc->audit('contract', $id, 'ativado', $before, ['status' => 'ativo'], $this->auth->getUser());
        $this->setFlashMessage('Contrato ativado.', 'success');
        $this->redirect("/admin/finance/contracts/{$id}");
    }

    public function contractCancel(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect("/admin/finance/contracts/{$id}"); return; }
        $before = $this->contracts->findById($id);
        $this->contracts->update($id, ['status' => 'cancelado']);
        $this->svc->audit('contract', $id, 'cancelado', $before, ['status' => 'cancelado'], $this->auth->getUser());
        $this->setFlashMessage('Contrato cancelado.', 'success');
        $this->redirect("/admin/finance/contracts/{$id}");
    }

    // ── Desconto: adicionar avulso ───────────────────────────────────────────

    public function discountAdd(int $contractId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect("/admin/finance/contracts/{$contractId}"); return; }

        $user = $this->auth->getUser();
        $ruleId = (int)($_POST['discount_rule_id'] ?? 0);
        $contract = $this->contracts->findById($contractId);
        if (!$contract) { $this->redirect('/admin/finance/contracts'); return; }

        if ($ruleId) {
            $rule = $this->db->fetch("SELECT * FROM finance_discount_rules WHERE id = ? AND ativo = 1", [$ruleId]);
            if ($rule) {
                $va = $rule['calculo'] === 'percentual'
                    ? round((float)$contract['valor_bruto'] * ($rule['valor'] / 100), 2)
                    : (float)$rule['valor'];
                $this->contracts->addDiscount([
                    'contract_id'      => $contractId,
                    'discount_rule_id' => $rule['id'],
                    'tipo'             => $rule['tipo'],
                    'descricao'        => $rule['nome'],
                    'calculo'          => $rule['calculo'],
                    'valor'            => $rule['valor'],
                    'valor_aplicado'   => $va,
                    'status'           => $rule['requer_aprovacao'] ? 'pendente' : 'aprovado',
                    'aprovado_por'     => $rule['requer_aprovacao'] ? null : $user['id'],
                    'aprovado_em'      => $rule['requer_aprovacao'] ? null : date('Y-m-d H:i:s'),
                ]);
            }
        } else {
            // desconto avulso
            if (!in_array($user['perfil_admin'] ?? '', ['coordenacao','direcao','dev'], true)) {
                $this->setFlashMessage('Desconto avulso requer coordenação/direção.', 'error');
                $this->redirect("/admin/finance/contracts/{$contractId}"); return;
            }
            $calculo = $_POST['calculo'] ?? 'percentual';
            $valor   = (float)str_replace(',', '.', $_POST['valor'] ?? '0');
            $va      = $calculo === 'percentual'
                ? round((float)$contract['valor_bruto'] * ($valor / 100), 2)
                : $valor;
            $this->contracts->addDiscount([
                'contract_id'    => $contractId,
                'discount_rule_id'=> null,
                'tipo'           => $_POST['tipo'] ?? 'manual',
                'descricao'      => trim($_POST['descricao'] ?? 'Desconto manual'),
                'calculo'        => $calculo,
                'valor'          => $valor,
                'valor_aplicado' => $va,
                'status'         => 'aprovado',
                'aprovado_por'   => $user['id'],
                'aprovado_em'    => date('Y-m-d H:i:s'),
            ]);
        }

        $this->svc->recalcularContrato($contractId);
        $this->svc->audit('discount', $contractId, 'desconto_adicionado', null, null, $user);
        $this->setFlashMessage('Desconto adicionado.', 'success');
        $this->redirect("/admin/finance/contracts/{$contractId}");
    }

    // ── Desconto: aprovar / rejeitar / remover ───────────────────────────────

    public function discountApprove(int $contractId, int $discountId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect("/admin/finance/contracts/{$contractId}"); return; }
        $user = $this->auth->getUser();
        if (!in_array($user['perfil_admin'] ?? '', ['coordenacao','direcao','dev'], true)) {
            $this->setFlashMessage('Sem permissão para aprovar descontos.', 'error');
            $this->redirect("/admin/finance/contracts/{$contractId}"); return;
        }
        $this->contracts->approveDiscount($discountId, $user['id']);
        $this->svc->recalcularContrato($contractId);
        $this->svc->audit('discount', $discountId, 'aprovado', null, null, $user);
        $this->setFlashMessage('Desconto aprovado e contrato recalculado.', 'success');
        $this->redirect("/admin/finance/contracts/{$contractId}");
    }

    public function discountReject(int $contractId, int $discountId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect("/admin/finance/contracts/{$contractId}"); return; }
        $user = $this->auth->getUser();
        if (!in_array($user['perfil_admin'] ?? '', ['coordenacao','direcao','dev'], true)) {
            $this->setFlashMessage('Sem permissão.', 'error');
            $this->redirect("/admin/finance/contracts/{$contractId}"); return;
        }
        $this->contracts->rejectDiscount($discountId);
        $this->svc->recalcularContrato($contractId);
        $this->svc->audit('discount', $discountId, 'rejeitado', null, null, $user);
        $this->setFlashMessage('Desconto rejeitado.', 'success');
        $this->redirect("/admin/finance/contracts/{$contractId}");
    }

    public function discountRemove(int $contractId, int $discountId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect("/admin/finance/contracts/{$contractId}"); return; }
        $user = $this->auth->getUser();
        if (!in_array($user['perfil_admin'] ?? '', ['coordenacao','direcao','dev'], true)) {
            $this->setFlashMessage('Sem permissão.', 'error');
            $this->redirect("/admin/finance/contracts/{$contractId}"); return;
        }
        $this->contracts->removeDiscount($discountId);
        $this->svc->recalcularContrato($contractId);
        $this->svc->audit('discount', $discountId, 'removido', null, null, $user);
        $this->setFlashMessage('Desconto removido.', 'success');
        $this->redirect("/admin/finance/contracts/{$contractId}");
    }

    // ── Parcela: visualizar ──────────────────────────────────────────────────

    public function installmentShow(int $id): void
    {
        if (!$this->schemaGuard()) return;

        $inst = $this->installments->findById($id);
        if (!$inst) { $this->setFlashMessage('Parcela não encontrada.', 'error'); $this->redirect('/admin/finance'); return; }

        $encargos = $this->svc->calcularEncargos($inst);
        $boleto   = $inst['boleto_codigo']
            ? $this->boleto->formatarLinhaDigitavel($inst['boleto_codigo'])
            : null;

        $this->viewWithLayout('admin', 'admin/finance/installment_show', [
            'title'        => 'Parcela #' . $id,
            'page_title'   => 'Parcela — ' . $inst['descricao'],
            'user'         => $this->auth->getUser(),
            'current_page' => 'finance',
            'installment'  => $inst,
            'encargos'     => $encargos,
            'boleto_linha' => $boleto,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    // ── Parcela: registrar baixa (POST) ──────────────────────────────────────

    public function installmentPay(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect("/admin/finance/installments/{$id}"); return; }

        $inst = $this->installments->findById($id);
        if (!$inst || $inst['status'] === 'pago') {
            $this->setFlashMessage('Parcela não encontrada ou já paga.', 'error');
            $this->redirect("/admin/finance/installments/{$id}"); return;
        }

        $user         = $this->auth->getUser();
        $valor        = (float)str_replace(',', '.', $_POST['valor_pago'] ?? $inst['valor_cobrado']);
        $data         = $_POST['data_pagamento'] ?? date('Y-m-d');
        $forma        = $_POST['forma_pagamento'] ?? 'outro';
        $obs          = trim($_POST['observacoes'] ?? '') ?: null;

        $this->installments->markPaid($id, $valor, $data);

        $this->db->insert(
            "INSERT INTO finance_payments (installment_id, valor, data_pagamento, forma_pagamento, observacoes, registrado_por) VALUES (?,?,?,?,?,?)",
            [$id, $valor, $data, $forma, $obs, $user['id'] ?? null]
        );

        $this->svc->audit('payment', $id, 'baixa_manual', ['status' => $inst['status']], ['status' => 'pago', 'valor' => $valor], $user);
        $this->setFlashMessage('Pagamento registrado com sucesso.', 'success');
        $this->redirect("/admin/finance/contracts/{$inst['contract_id']}");
    }

    // ── Gerar boleto simulado ────────────────────────────────────────────────

    public function installmentBoleto(int $id): void
    {
        $inst = $this->installments->findById($id);
        if (!$inst) { $this->setFlashMessage('Parcela não encontrada.', 'error'); $this->redirect('/admin/finance'); return; }

        $encargos = $this->svc->calcularEncargos($inst);
        $total    = $encargos['total'];

        $codigo   = $inst['boleto_codigo'] ?: $this->boleto->gerarEPersistir($id, $total, $inst['data_vencimento']);
        $linha    = $this->boleto->formatarLinhaDigitavel($codigo);

        $this->viewWithLayout('admin', 'admin/finance/boleto', [
            'title'        => 'Boleto — ' . $inst['descricao'],
            'page_title'   => 'Boleto Simulado',
            'user'         => $this->auth->getUser(),
            'current_page' => 'finance',
            'installment'  => $inst,
            'encargos'     => $encargos,
            'boleto_codigo'=> $codigo,
            'boleto_linha' => $linha,
        ]);
    }

    // ── Tabela de preços ─────────────────────────────────────────────────────

    public function priceTable(): void
    {
        if (!$this->schemaGuard()) return;

        $anos  = $this->db->fetchAll("SELECT id, ano FROM ano_letivo ORDER BY ano DESC") ?: [];
        $aid   = (int)($_GET['ano_letivo_id'] ?? ($anos[0]['id'] ?? 0));
        $items = $aid
            ? $this->db->fetchAll("SELECT fpt.*, s.nome AS serie_nome FROM finance_price_table fpt LEFT JOIN serie s ON s.id = fpt.serie_id WHERE fpt.ano_letivo_id = ? ORDER BY fpt.categoria, fpt.serie_id", [$aid]) ?: []
            : [];

        $series = $this->db->fetchAll("SELECT id, nome FROM serie ORDER BY nome") ?: [];

        $this->viewWithLayout('admin', 'admin/finance/price_table', [
            'title'         => 'Tabela de Preços',
            'page_title'    => 'Tabela de Preços',
            'user'          => $this->auth->getUser(),
            'current_page'  => 'finance',
            'anos_letivos'  => $anos,
            'ano_letivo_id' => $aid,
            'items'         => $items,
            'series'        => $series,
            'csrf_token'    => $this->generateCsrfToken(),
        ]);
    }

    public function priceTableStore(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance/price-table'); return; }

        $this->db->insert(
            "INSERT INTO finance_price_table (ano_letivo_id, serie_id, categoria, descricao, valor_base) VALUES (?,?,?,?,?)",
            [
                (int)($_POST['ano_letivo_id'] ?? 0),
                (int)($_POST['serie_id'] ?? 0) ?: null,
                $_POST['categoria'] ?? 'mensalidade',
                trim($_POST['descricao'] ?? 'Sem descrição'),
                (float)str_replace(',', '.', $_POST['valor_base'] ?? '0'),
            ]
        );
        $this->setFlashMessage('Item adicionado.', 'success');
        $this->redirect('/admin/finance/price-table?ano_letivo_id=' . ($_POST['ano_letivo_id'] ?? ''));
    }

    public function priceTableDelete(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance/price-table'); return; }
        $this->db->update("DELETE FROM finance_price_table WHERE id = ?", [$id]);
        $this->setFlashMessage('Item removido.', 'success');
        $this->redirect('/admin/finance/price-table');
    }

    // ── Regras de desconto ───────────────────────────────────────────────────

    public function discountRules(): void
    {
        if (!$this->schemaGuard()) return;

        $rules = $this->db->fetchAll("SELECT * FROM finance_discount_rules ORDER BY tipo, nome") ?: [];

        $this->viewWithLayout('admin', 'admin/finance/discount_rules', [
            'title'        => 'Regras de Desconto',
            'page_title'   => 'Regras de Desconto',
            'user'         => $this->auth->getUser(),
            'current_page' => 'finance_discounts',
            'rules'        => $rules,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    public function discountRuleStore(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance/discount-rules'); return; }
        $user = $this->auth->getUser();
        if (!in_array($user['perfil_admin'] ?? '', ['coordenacao','direcao','dev'], true)) {
            $this->setFlashMessage('Sem permissão.', 'error'); $this->redirect('/admin/finance/discount-rules'); return;
        }

        $this->db->insert(
            "INSERT INTO finance_discount_rules (nome, tipo, calculo, valor, acumulavel, requer_aprovacao) VALUES (?,?,?,?,?,?)",
            [
                trim($_POST['nome'] ?? 'Desconto'),
                $_POST['tipo'] ?? 'manual',
                $_POST['calculo'] ?? 'percentual',
                (float)str_replace(',', '.', $_POST['valor'] ?? '0'),
                !empty($_POST['acumulavel']) ? 1 : 0,
                !empty($_POST['requer_aprovacao']) ? 1 : 0,
            ]
        );
        $this->setFlashMessage('Regra criada.', 'success');
        $this->redirect('/admin/finance/discount-rules');
    }

    public function discountRuleToggle(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance/discount-rules'); return; }
        $user = $this->auth->getUser();
        if (!in_array($user['perfil_admin'] ?? '', ['coordenacao','direcao','dev'], true)) {
            $this->setFlashMessage('Sem permissão.', 'error'); $this->redirect('/admin/finance/discount-rules'); return;
        }
        $this->db->update("UPDATE finance_discount_rules SET ativo = NOT ativo WHERE id = ?", [$id]);
        $this->setFlashMessage('Regra atualizada.', 'success');
        $this->redirect('/admin/finance/discount-rules');
    }

    // ── Relatório inadimplência ──────────────────────────────────────────────

    public function reportInadimplencia(): void
    {
        if (!$this->schemaGuard()) return;

        $anoLetivoId = (int)($_GET['ano_letivo_id'] ?? 0);
        $inadimplentes = $this->db->fetchAll(
            "SELECT a.id AS aluno_id, a.nome AS aluno_nome, a.ra,
                    COUNT(fi.id) AS qtd_vencidas,
                    SUM(fi.valor_cobrado) AS total_devido,
                    MAX(fi.data_vencimento) AS ultimo_vencimento,
                    fc.responsavel_nome, fc.responsavel_telefone, fc.responsavel_email
             FROM finance_installments fi
             JOIN finance_contracts fc ON fc.id = fi.contract_id
             LEFT JOIN alunos a ON a.id = fc.aluno_id
             WHERE fi.status = 'vencido'
               " . ($anoLetivoId ? " AND fc.ano_letivo_id = $anoLetivoId" : "") . "
             GROUP BY a.id, a.nome, a.ra, fc.responsavel_nome, fc.responsavel_telefone, fc.responsavel_email
             ORDER BY total_devido DESC",
            []
        ) ?: [];

        $anos = $this->db->fetchAll("SELECT id, ano FROM ano_letivo ORDER BY ano DESC") ?: [];

        $this->viewWithLayout('admin', 'admin/finance/report_inadimplencia', [
            'title'          => 'Relatório de Inadimplência',
            'page_title'     => 'Inadimplência',
            'user'           => $this->auth->getUser(),
            'current_page'   => 'finance',
            'inadimplentes'  => $inadimplentes,
            'anos_letivos'   => $anos,
            'ano_letivo_id'  => $anoLetivoId,
            'csrf_token'     => $this->generateCsrfToken(),
        ]);
    }

    // ── Disparar régua manualmente ───────────────────────────────────────────

    public function dispararRegua(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance'); return; }
        $this->billing->marcarVencidas();
        $enviados = $this->billing->dispararRegua();
        $this->setFlashMessage('Régua disparada: ' . count($enviados) . ' mensagem(ns) enviada(s).', 'success');
        $this->redirect('/admin/finance');
    }

    // ── Gera parcelas a partir dos finance_contract_items ────────────────────

    private function gerarParcelasDeItems(int $contractId, int $diaVencimento): void
    {
        $items = $this->db->fetchAll(
            "SELECT * FROM finance_contract_items WHERE contract_id = ? AND status = 'ativo' ORDER BY categoria, id",
            [$contractId]
        ) ?: [];

        $contract  = $this->contracts->findById($contractId);
        $anoLetivo = $this->db->fetch("SELECT * FROM ano_letivo WHERE id = ?", [$contract['ano_letivo_id'] ?? 0]);
        $anoRef    = (int)($anoLetivo['ano'] ?? date('Y'));
        $cfg       = $this->configModel->get();
        $diaVenc   = $diaVencimento ?: (int)($cfg['dia_vencimento_padrao'] ?? 10);

        foreach ($items as $item) {
            $numParcelas = max(1, (int)$item['num_parcelas']);
            $mesInicio   = (int)($item['mes_inicio'] ?? 1);
            $diaItem     = (int)($item['dia_vencimento'] ?? $diaVenc);
            $valorParcela= round((float)$item['valor_liquido'] / $numParcelas, 2);
            $categoria   = $item['categoria'];
            $descBase    = $item['descricao'];

            for ($i = 0; $i < $numParcelas; $i++) {
                $mes = (($mesInicio - 1 + $i) % 12) + 1;
                $ano = $anoRef + (int)floor(($mesInicio - 1 + $i) / 12);
                $ultimoDia = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
                $dia = min($diaItem, $ultimoDia);
                $venc = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);

                $descricao = $numParcelas === 1
                    ? $descBase
                    : "{$descBase} " . ($i + 1) . "/{$numParcelas}";

                $this->db->insert(
                    "INSERT INTO finance_installments
                     (contract_id, contract_item_id, descricao, numero_parcela, total_parcelas,
                      valor, valor_cobrado, data_vencimento, status, categoria)
                     VALUES (?,?,?,?,?,?,?,?,'pendente',?)",
                    [
                        $contractId,
                        $item['id'],
                        $descricao,
                        $i + 1,
                        $numParcelas,
                        $valorParcela,
                        $valorParcela,
                        $venc,
                        $categoria,
                    ]
                );
            }
        }
    }

    // ── Busca de alunos (JSON) ────────────────────────────────────────────────

    public function alunoSearch(): void
    {
        header('Content-Type: application/json');
        $q       = trim($_GET['q'] ?? '');
        $turmaId = (int)($_GET['turma_id'] ?? 0);
        $limit   = min((int)($_GET['limit'] ?? 20), 200);

        if ($turmaId) {
            // tabela é 'matricula' (singular), status 'ativa' — sem filtro de status para pegar todos
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT a.id, a.nome, a.ra
                 FROM alunos a
                 INNER JOIN matricula m ON m.aluno_id = a.id AND m.turma_id = ?
                 WHERE a.ativo = 1
                 ORDER BY a.nome
                 LIMIT ?",
                [$turmaId, $limit]
            ) ?: [];
        } elseif (strlen($q) >= 2) {
            $like = '%' . $q . '%';
            $rows = $this->db->fetchAll(
                "SELECT a.id, a.nome, a.ra,
                        COALESCE(t.nome,'') AS turma,
                        COALESCE(al.ano,'') AS ano_letivo
                 FROM alunos a
                 LEFT JOIN matricula m ON m.aluno_id = a.id AND m.status = 'ativa'
                 LEFT JOIN turmas t ON t.id = m.turma_id
                 LEFT JOIN ano_letivo al ON al.id = m.ano_letivo_id
                 WHERE a.nome LIKE ? OR a.ra LIKE ? OR a.email LIKE ?
                 ORDER BY a.nome LIMIT ?",
                [$like, $like, $like, $limit]
            ) ?: [];
        } else {
            $rows = [];
        }
        echo json_encode($rows);
    }

    // ── Planos ───────────────────────────────────────────────────────────────

    public function plansIndex(): void
    {
        if (!$this->schemaGuard()) return;

        $anoLetivoId = (int)($_GET['ano_letivo_id'] ?? 0);
        $anos        = $this->db->fetchAll("SELECT id, ano FROM ano_letivo ORDER BY ano DESC") ?: [];
        $plans       = $this->planSvc->all($anoLetivoId ?: null);

        $this->viewWithLayout('admin', 'admin/finance/plans/index', [
            'title'         => 'Planos Financeiros',
            'page_title'    => 'Planos',
            'user'          => $this->auth->getUser(),
            'current_page'  => 'finance_plans',
            'plans'         => $plans,
            'anos_letivos'  => $anos,
            'ano_letivo_id' => $anoLetivoId,
            'csrf_token'    => $this->generateCsrfToken(),
            'status_message' => $_GET['msg'] ?? '',
            'status_type' => $_GET['status_type'] ?? '',
        ]);
    }

    public function planCreate(): void
    {
        if (!$this->schemaGuard()) return;
        $anos   = $this->db->fetchAll("SELECT id, ano FROM ano_letivo ORDER BY ano DESC") ?: [];
        $series = $this->db->fetchAll("SELECT id, nome FROM serie ORDER BY nome") ?: [];
        $this->viewWithLayout('admin', 'admin/finance/plans/create', [
            'title'        => 'Novo Plano',
            'page_title'   => 'Novo Plano',
            'user'         => $this->auth->getUser(),
            'current_page' => 'finance_plans',
            'anos_letivos' => $anos,
            'series'       => $series,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    public function planStore(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance/plans'); return; }
        $id = $this->planSvc->create([
            'nome'          => trim($_POST['nome'] ?? ''),
            'descricao'     => trim($_POST['descricao'] ?? '') ?: null,
            'ano_letivo_id' => (int)($_POST['ano_letivo_id'] ?? 0),
            'serie_id'      => (int)($_POST['serie_id'] ?? 0) ?: null,
            'ativo'         => 1,
        ]);
        $this->setFlashMessage('Plano criado.', 'success');
        $this->redirect("/admin/finance/plans/{$id}");
    }

    public function planShow(int $id): void
    {
        if (!$this->schemaGuard()) return;
        $plan = $this->planSvc->findById($id);
        if (!$plan) { $this->setFlashMessage('Plano não encontrado.', 'error'); $this->redirect('/admin/finance/plans'); return; }
        // unidades table may not exist in all tenants; guard with information_schema
        $hasUnidades = $this->db->fetch(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unidades'"
        );
        $unidades = $hasUnidades
            ? ($this->db->fetchAll("SELECT id, nome, razao_social FROM unidades WHERE ativo = 1 ORDER BY tipo DESC, nome ASC") ?: [])
            : [];
        $this->viewWithLayout('admin', 'admin/finance/plans/show', [
            'title'        => 'Plano: ' . $plan['nome'],
            'page_title'   => $plan['nome'],
            'user'         => $this->auth->getUser(),
            'current_page' => 'finance_plans',
            'plan'         => $plan,
            'unidades'     => $unidades,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    public function alunoResponsaveis(int $alunoId): void
    {
        header('Content-Type: application/json');
        $rows = $this->db->fetchAll(
            "SELECT r.id, r.nome, r.cpf, r.email, r.telefone, ar.tipo_vinculo, ar.is_financeiro
             FROM responsaveis r
             INNER JOIN alunos_responsaveis ar ON ar.responsavel_id = r.id
             WHERE ar.aluno_id = ? AND ar.ativo = 1
             ORDER BY ar.is_financeiro DESC, r.nome ASC",
            [$alunoId]
        ) ?: [];
        echo json_encode($rows);
        exit;
    }

    public function planItemStore(int $planId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect("/admin/finance/plans/{$planId}"); return; }
        $this->planSvc->addItem($planId, $_POST);
        $this->setFlashMessage('Item adicionado.', 'success');
        $this->redirect("/admin/finance/plans/{$planId}");
    }

    public function planItemDelete(int $planId, int $itemId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect("/admin/finance/plans/{$planId}"); return; }
        $this->planSvc->removeItem($itemId);
        $this->setFlashMessage('Item removido.', 'success');
        $this->redirect("/admin/finance/plans/{$planId}");
    }

    public function planToggle(int $id): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance/plans'); return; }
        $this->planSvc->toggle($id);
        $this->redirect('/admin/finance/plans');
    }

    // ── Extrato do aluno ─────────────────────────────────────────────────────

    public function alunoParcelasVencidas(int $alunoId): void
    {
        header('Content-Type: application/json');
        if (!$this->schemaGuard()) { echo json_encode([]); return; }

        $rows = $this->db->fetchAll(
            "SELECT fi.id, fi.num_parcela, fi.descricao, fi.valor_cobrado AS valor_total, fi.data_vencimento
             FROM finance_installments fi
             INNER JOIN finance_contracts fc ON fc.id = fi.contract_id
             WHERE fc.aluno_id = ? AND fi.status = 'vencido'
             ORDER BY fi.data_vencimento ASC",
            [$alunoId]
        ) ?: [];

        echo json_encode($rows);
    }

    public function alunoResumoJson(int $alunoId): void
    {
        header('Content-Type: application/json');
        if (!$this->schemaGuard()) { echo json_encode(['error' => 'schema']); return; }

        $aluno = $this->db->fetch("SELECT id, nome, ra FROM alunos WHERE id = ?", [$alunoId]);
        if (!$aluno) { echo json_encode(['error' => 'not_found']); return; }

        $saldo = $this->ledgerSvc->getSaldo($alunoId);

        $catLabel = [
            'mensalidade'=>'Mensalidade','matricula'=>'Matrícula','material'=>'Material',
            'uniforme'=>'Uniforme','passeio'=>'Passeio','ingresso'=>'Ingresso',
            'evento'=>'Evento','outros'=>'Outros',
        ];

        // Parcelas do contrato
        $parcelas = $this->db->fetchAll(
            "SELECT fi.id, fi.descricao, fi.valor_cobrado AS valor_total, fi.data_vencimento,
                    fi.data_pagamento, fi.status, fi.forma_pagamento,
                    'parcela' AS tipo, NULL AS categoria
             FROM finance_installments fi
             INNER JOIN finance_contracts fc ON fc.id = fi.contract_id
             WHERE fc.aluno_id = ? AND fi.status <> 'cancelado'
             ORDER BY fi.data_vencimento ASC",
            [$alunoId]
        ) ?: [];

        // Cobranças avulsas
        $cobranças = [];
        $hasCh = $this->db->fetch("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_charges'");
        if ($hasCh) {
            $cobranças = $this->db->fetchAll(
                "SELECT id, descricao, valor AS valor_total, data_vencimento,
                        data_pagamento, status, forma_pagamento,
                        'cobrança' AS tipo, categoria
                 FROM finance_charges
                 WHERE aluno_id = ? AND status <> 'cancelado'
                 ORDER BY data_vencimento ASC",
                [$alunoId]
            ) ?: [];
        }

        $faturas = array_merge($parcelas, $cobranças);
        usort($faturas, fn($a, $b) => strcmp($a['data_vencimento'] ?? '', $b['data_vencimento'] ?? ''));

        foreach ($faturas as &$f) {
            $f['categoria_label'] = $catLabel[$f['categoria'] ?? ''] ?? ucfirst((string)($f['categoria'] ?? ''));
        }
        unset($f);

        echo json_encode([
            'id'     => (int)$aluno['id'],
            'nome'   => $aluno['nome'],
            'ra'     => $aluno['ra'],
            'saldo'  => (float)$saldo,
            'faturas'=> $faturas,
        ]);
    }

    public function alunoExtrato(int $alunoId): void
    {
        if (!$this->schemaGuard()) return;

        $aluno = $this->db->fetch("SELECT * FROM alunos WHERE id = ?", [$alunoId]);
        if (!$aluno) { $this->setFlashMessage('Aluno não encontrado.', 'error'); $this->redirect('/admin/finance'); return; }

        $dataInicio = $_GET['data_inicio'] ?? null;
        $dataFim    = $_GET['data_fim']    ?? null;
        $extrato    = $this->ledgerSvc->getExtrato($alunoId, $dataInicio, $dataFim);
        $saldo      = $this->ledgerSvc->getSaldo($alunoId);

        $this->viewWithLayout('admin', 'admin/finance/aluno_extrato', [
            'title'        => 'Extrato — ' . $aluno['nome'],
            'page_title'   => 'Extrato Financeiro',
            'user'         => $this->auth->getUser(),
            'current_page' => 'finance',
            'aluno'        => $aluno,
            'extrato'      => $extrato,
            'saldo'        => $saldo,
            'data_inicio'  => $dataInicio,
            'data_fim'     => $dataFim,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    public function ledgerEstornar(int $ledgerId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance'); return; }
        $user = $this->auth->getUser();
        if (!in_array($user['perfil_admin'] ?? '', ['coordenacao','direcao','dev'], true)) {
            $this->setFlashMessage('Sem permissão para estornar.', 'error');
            $this->redirect('/admin/finance'); return;
        }
        $entry  = $this->db->fetch("SELECT * FROM finance_ledger WHERE id = ?", [$ledgerId]);
        $motivo = trim($_POST['motivo'] ?? 'Estorno manual');
        $this->ledgerSvc->estornar($ledgerId, $motivo, $user['id']);
        $this->setFlashMessage('Estorno lançado.', 'success');
        $alunoId = $entry['aluno_id'] ?? 0;
        $this->redirect("/admin/finance/aluno/{$alunoId}/extrato");
    }

    // ── Cobranças avulsas ────────────────────────────────────────────────────

    public function chargesList(): void
    {
        if (!$this->schemaGuard()) return;

        $status    = $_GET['status']     ?? '';
        $categoria = $_GET['categoria']  ?? '';
        $turmaId   = (int)($_GET['turma_id']   ?? 0);
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim    = $_GET['data_fim']    ?? '';

        $where  = ['fc.status <> ?'];
        $params = ['cancelado'];

        if ($status)    { $where[] = 'fc.status = ?';              $params[] = $status; }
        if ($categoria) { $where[] = 'fc.categoria = ?';           $params[] = $categoria; }
        if ($dataInicio){ $where[] = 'fc.data_vencimento >= ?';    $params[] = $dataInicio; }
        if ($dataFim)   { $where[] = 'fc.data_vencimento <= ?';    $params[] = $dataFim; }
        if ($turmaId)   { $where[] = 'EXISTS (SELECT 1 FROM matricula m WHERE m.aluno_id = fc.aluno_id AND m.turma_id = ?)'; $params[] = $turmaId; }

        $charges = $this->db->fetchAll(
            "SELECT fc.*, a.nome AS aluno_nome,
                    (SELECT t.nome FROM matricula m2 INNER JOIN turmas t ON t.id = m2.turma_id
                     WHERE m2.aluno_id = fc.aluno_id ORDER BY m2.id DESC LIMIT 1) AS turma_nome
             FROM finance_charges fc
             INNER JOIN alunos a ON a.id = fc.aluno_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY fc.data_vencimento ASC, a.nome ASC",
            $params
        ) ?: [];

        $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome") ?: [];

        $this->viewWithLayout('admin', 'admin/finance/charges_list', [
            'title'        => 'Cobranças Avulsas',
            'page_title'   => 'Cobranças Avulsas',
            'user'         => $this->auth->getUser(),
            'current_page' => 'finance_charge_batch',
            'charges'      => $charges,
            'turmas'       => $turmas,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    public function chargePay(int $chargeId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/finance/charges'); return;
        }

        $charge = $this->db->fetch("SELECT * FROM finance_charges WHERE id = ?", [$chargeId]);
        if (!$charge || !in_array($charge['status'], ['pendente', 'vencido'])) {
            $this->setFlashMessage('Cobrança não encontrada ou já processada.', 'error');
            $this->redirect('/admin/finance/charges'); return;
        }

        $valorPago      = (float) str_replace(',', '.', $_POST['valor_pago'] ?? $charge['valor']);
        $dataPagamento  = $_POST['data_pagamento'] ?? date('Y-m-d');
        $formaPagamento = $_POST['forma_pagamento'] ?? 'outros';

        $this->db->update(
            "UPDATE finance_charges SET status = 'pago', valor_pago = ?, data_pagamento = ?, forma_pagamento = ? WHERE id = ?",
            [$valorPago, $dataPagamento, $formaPagamento, $chargeId]
        );

        // Lança crédito no ledger
        $this->ledgerSvc->lancar([
            'aluno_id'       => (int)$charge['aluno_id'],
            'tipo'           => 'credito',
            'categoria'      => $charge['categoria'],
            'descricao'      => 'Pagamento: ' . $charge['descricao'],
            'valor'          => $valorPago,
            'data_lancamento'=> $dataPagamento,
            'referencia_tipo'=> 'charge',
            'referencia_id'  => $chargeId,
            'observacoes'    => 'Via ' . $formaPagamento,
            'gerado_auto'    => 0,
        ]);

        $this->setFlashMessage('Pagamento registrado com sucesso.', 'success');
        $this->redirect('/admin/finance/charges');
    }

    public function chargeCreate(int $alunoId): void
    {
        if (!$this->schemaGuard()) return;
        $aluno = $this->db->fetch("SELECT * FROM alunos WHERE id = ?", [$alunoId]);
        if (!$aluno) { $this->redirect('/admin/finance'); return; }
        $anos  = $this->db->fetchAll("SELECT id, ano FROM ano_letivo ORDER BY ano DESC") ?: [];
        $this->viewWithLayout('admin', 'admin/finance/charge_create', [
            'title'        => 'Nova Cobrança — ' . $aluno['nome'],
            'page_title'   => 'Nova Cobrança Avulsa',
            'user'         => $this->auth->getUser(),
            'current_page' => 'finance',
            'aluno'        => $aluno,
            'anos_letivos' => $anos,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    public function chargeStore(int $alunoId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect("/admin/finance/aluno/{$alunoId}/charge"); return; }
        $user = $this->auth->getUser();

        $this->db->insert(
            "INSERT INTO finance_charges (aluno_id, ano_letivo_id, categoria, descricao, valor, data_vencimento, observacoes, criado_por, status)
             VALUES (?,?,?,?,?,?,?,'pendente')",
            [
                $alunoId,
                (int)($_POST['ano_letivo_id'] ?? 0) ?: null,
                $_POST['categoria'] ?? 'outros',
                trim($_POST['descricao'] ?? 'Cobrança'),
                (float)str_replace(',', '.', $_POST['valor'] ?? '0'),
                $_POST['data_vencimento'],
                trim($_POST['observacoes'] ?? '') ?: null,
                $user['id'] ?? null,
            ]
        );
        $chargeId = (int)$this->db->lastInsertId();

        // Lança no extrato
        $charge = $this->db->fetch("SELECT * FROM finance_charges WHERE id = ?", [$chargeId]);
        if ($charge) $this->ledgerSvc->lancarDebitoAvulso($charge);

        $this->setFlashMessage('Cobrança avulsa criada.', 'success');
        $this->redirect("/admin/finance/aluno/{$alunoId}/extrato");
    }

    // ── Cobranças em lote ────────────────────────────────────────────────────

    public function chargeBatch(): void
    {
        if (!$this->schemaGuard()) return;

        $anos   = $this->db->fetchAll("SELECT id, ano, ativo FROM ano_letivo ORDER BY ano DESC") ?: [];
        $turmas = $this->db->fetchAll(
            "SELECT t.id, t.nome, s.nome AS serie
             FROM turmas t
             LEFT JOIN serie s ON s.id = t.serie_id
             WHERE t.ativo = 1
             ORDER BY t.nome"
        ) ?: [];

        $hasUnidades = $this->db->fetch(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unidades'"
        );
        $unidades = $hasUnidades
            ? ($this->db->fetchAll("SELECT id, nome, razao_social FROM unidades WHERE ativo = 1 ORDER BY nome") ?: [])
            : [];

        $this->viewWithLayout('admin', 'admin/finance/charge_batch', [
            'title'        => 'Cobrança em Lote',
            'page_title'   => 'Cobrança em Lote',
            'user'         => $this->auth->getUser(),
            'current_page' => 'finance_charge_batch',
            'turmas'       => $turmas,
            'anos_letivos' => $anos,
            'unidades'     => $unidades,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    public function chargeBatchStore(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/finance/charges/batch');
            return;
        }

        $user        = $this->auth->getUser();
        $modo        = $_POST['modo'] ?? 'turma';
        $categoria   = $_POST['categoria']       ?? 'outros';
        $descricao   = trim($_POST['descricao']  ?? 'Cobrança');
        $valor       = (float)str_replace(['.', ','], ['', '.'], $_POST['valor'] ?? '0');
        $vencimento  = $_POST['data_vencimento'] ?? date('Y-m-d');
        $anoLetivoId = (int)($_POST['ano_letivo_id'] ?? 0) ?: null;
        $unidadeId   = (int)($_POST['unidade_id'] ?? 0) ?: null;
        $obs         = trim($_POST['observacoes'] ?? '') ?: null;
        $batchId     = bin2hex(random_bytes(16)); // UUID-like para agrupar o lote

        // Resolve lista de aluno_ids
        if ($modo === 'individual') {
            $raw = trim($_POST['aluno_ids_raw'] ?? '');
            $alunoIds = array_filter(array_map('intval', explode(',', $raw)));
        } else {
            $turmaId  = (int)($_POST['turma_id'] ?? 0);
            $checked  = array_map('intval', $_POST['aluno_ids'] ?? []);
            $alunoIds = array_filter($checked);
        }

        if (empty($alunoIds) || $valor <= 0) {
            $this->setFlashMessage('Nenhum aluno selecionado ou valor inválido.', 'error');
            $this->redirect('/admin/finance/charges/batch');
            return;
        }

        // Verifica se unidade_id e batch_id existem na tabela
        $hasBatch = $this->db->fetch(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_charges' AND COLUMN_NAME = 'batch_id'"
        );

        $count = 0;
        foreach ($alunoIds as $alunoId) {
            if ($hasBatch) {
                $chargeId = (int)$this->db->insert(
                    "INSERT INTO finance_charges
                        (aluno_id, ano_letivo_id, categoria, descricao, valor, data_vencimento,
                         unidade_id, batch_id, observacoes, criado_por, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                    [$alunoId, $anoLetivoId, $categoria, $descricao, $valor, $vencimento,
                     $unidadeId, $batchId, $obs, $user['id'] ?? null, 'pendente']
                );
            } else {
                $chargeId = (int)$this->db->insert(
                    "INSERT INTO finance_charges
                        (aluno_id, ano_letivo_id, categoria, descricao, valor, data_vencimento,
                         observacoes, criado_por, status)
                     VALUES (?,?,?,?,?,?,?,?,?)",
                    [$alunoId, $anoLetivoId, $categoria, $descricao, $valor, $vencimento,
                     $obs, $user['id'] ?? null, 'pendente']
                );
            }
            $charge   = $this->db->fetch("SELECT * FROM finance_charges WHERE id = ?", [$chargeId]);
            if ($charge) $this->ledgerSvc->lancarDebitoAvulso($charge);
            $count++;
        }

        $this->setFlashMessage(
            "{$count} cobrança(s) de R$ " . number_format($valor, 2, ',', '.') . " criada(s) com sucesso.",
            'success'
        );
        $this->redirect('/admin/finance');
    }

    // ── Busca alunos por turma (para charge batch) ────────────────────────────

    public function alunoSearchByTurma(): void
    {
        header('Content-Type: application/json');
        $turmaId = (int)($_GET['turma_id'] ?? 0);
        $q       = trim($_GET['q'] ?? '');
        $limit   = min((int)($_GET['limit'] ?? 50), 200);

        if ($turmaId) {
            $rows = $this->db->fetchAll(
                "SELECT a.id, a.nome, a.ra
                 FROM alunos a
                 INNER JOIN turma_alunos ta ON ta.aluno_id = a.id
                 WHERE ta.turma_id = ? AND a.ativo = 1
                 ORDER BY a.nome
                 LIMIT ?",
                [$turmaId, $limit]
            ) ?: [];
        } elseif ($q !== '') {
            $rows = $this->db->fetchAll(
                "SELECT id, nome, ra FROM alunos WHERE ativo = 1 AND (nome LIKE ? OR ra LIKE ?) ORDER BY nome LIMIT ?",
                ["%{$q}%", "%{$q}%", $limit]
            ) ?: [];
        } else {
            $rows = [];
        }

        echo json_encode($rows);
        exit;
    }

    // ── Configurações financeiras ─────────────────────────────────────────────

    public function settings(): void
    {
        $config = $this->configModel->get();
        $this->viewWithLayout('admin', 'admin/finance/settings', [
            'title'        => 'Configurações Financeiras',
            'page_title'   => 'Configurações',
            'user'         => $this->auth->getUser(),
            'current_page' => 'finance_settings',
            'config'       => $config,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    public function settingsSave(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect('/admin/finance/settings'); return; }
        $user = $this->auth->getUser();
        if (!in_array($user['perfil_admin'] ?? '', ['coordenacao','direcao','dev'], true)) {
            $this->setFlashMessage('Sem permissão.', 'error'); $this->redirect('/admin/finance/settings'); return;
        }
        $this->configModel->save($_POST);
        $this->setFlashMessage('Configurações salvas.', 'success');
        $this->redirect('/admin/finance/settings');
    }

    // ── Renegociação ─────────────────────────────────────────────────────────

    public function renegotiationCreate(int $contractId): void
    {
        if (!$this->schemaGuard()) return;
        $user     = $this->auth->getUser();
        if (!in_array($user['perfil_admin'] ?? '', ['coordenacao','direcao','dev'], true)) {
            $this->setFlashMessage('Sem permissão para renegociar.', 'error');
            $this->redirect("/admin/finance/contracts/{$contractId}"); return;
        }
        $contract = $this->contracts->findById($contractId);
        if (!$contract) { $this->redirect('/admin/finance/contracts'); return; }

        $vencidas = $this->installments->list(['status' => 'vencido', 'contract_id' => $contractId], 100);
        $totalDivida = array_sum(array_column($vencidas, 'valor_cobrado'));

        $this->viewWithLayout('admin', 'admin/finance/renegotiation_create', [
            'title'        => 'Renegociação — Contrato #' . $contractId,
            'page_title'   => 'Renegociação',
            'user'         => $user,
            'current_page' => 'finance',
            'contract'     => $contract,
            'vencidas'     => $vencidas,
            'total_divida' => $totalDivida,
            'csrf_token'   => $this->generateCsrfToken(),
        ]);
    }

    public function renegotiationStore(int $contractId): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) { $this->redirect("/admin/finance/contracts/{$contractId}"); return; }
        $user = $this->auth->getUser();
        if (!in_array($user['perfil_admin'] ?? '', ['coordenacao','direcao','dev'], true)) {
            $this->setFlashMessage('Sem permissão.', 'error'); $this->redirect("/admin/finance/contracts/{$contractId}"); return;
        }
        $contract = $this->contracts->findById($contractId);

        $totalDivida    = (float)str_replace(',', '.', $_POST['valor_total_divida'] ?? '0');
        $entrada        = (float)str_replace(',', '.', $_POST['valor_entrada'] ?? '0');
        $parcelado      = round($totalDivida - $entrada, 2);
        $numParcelas    = max(1, (int)($_POST['num_parcelas'] ?? 1));
        $diaVenc        = (int)($_POST['dia_vencimento'] ?? 10);
        $mesInicio      = (int)($_POST['mes_inicio'] ?? date('n'));
        $anoInicio      = (int)($_POST['ano_inicio'] ?? date('Y'));

        $this->db->insert(
            "INSERT INTO finance_renegotiations
             (aluno_id, contract_id, valor_total_divida, valor_entrada, valor_parcelado, num_parcelas, dia_vencimento, mes_inicio, ano_inicio, observacoes, criado_por, aprovado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $contract['aluno_id'], $contractId, $totalDivida, $entrada, $parcelado,
                $numParcelas, $diaVenc, $mesInicio, $anoInicio,
                trim($_POST['observacoes'] ?? '') ?: null,
                $user['id'], $user['id'],
            ]
        );

        // Gera novas parcelas para o valor parcelado
        $valorParcela = round($parcelado / $numParcelas, 2);
        for ($i = 0; $i < $numParcelas; $i++) {
            $mes = (($mesInicio - 1 + $i) % 12) + 1;
            $ano = $anoInicio + (int)floor(($mesInicio - 1 + $i) / 12);
            $venc = sprintf('%04d-%02d-%02d', $ano, $mes, $diaVenc);
            $this->db->insert(
                "INSERT INTO finance_installments (contract_id, descricao, numero_parcela, total_parcelas, valor, valor_cobrado, data_vencimento, status, categoria)
                 VALUES (?,?,?,?,?,?,?,'pendente','outros')",
                [$contractId, 'Renegociação ' . ($i + 1) . '/' . $numParcelas, $i + 1, $numParcelas, $valorParcela, $valorParcela, $venc]
            );
        }

        $this->svc->audit('renegotiation', $contractId, 'renegociado', null, ['valor' => $totalDivida], $user);
        $this->setFlashMessage('Renegociação criada. ' . $numParcelas . ' nova(s) parcela(s) gerada(s).', 'success');
        $this->redirect("/admin/finance/contracts/{$contractId}");
    }
}
}
