<?php
require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Core/Database.php';
require_once __DIR__ . '/../../../Middleware/MobileApiAuthMiddleware.php';
require_once __DIR__ . '/../../../Policies/ParentStudentAccessPolicy.php';
require_once __DIR__ . '/../../../Services/FinanceBoletoService.php';

class MobileFinanceController extends BaseController
{
    private $db;
    private $policy;
    private $boleto;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->policy = new ParentStudentAccessPolicy($this->db);
        $this->boleto = new \App\Services\FinanceBoletoService($this->db);
    }

    public function student($id): void
    {
        $studentId = $this->authorizedStudentId($id);

        $contracts = $this->contracts($studentId);
        $installments = $this->installments($studentId);
        $charges = $this->charges($studentId);
        $enrollments = $this->enrollments($studentId);
        $invoices = array_values(array_merge($installments, $charges));

        $pendingAmount = 0.0;
        $overdueAmount = 0.0;
        foreach ($invoices as $invoice) {
            if (in_array($invoice['status'], ['pendente', 'vencido'], true)) {
                $pendingAmount += (float) $invoice['amount'];
            }
            if ($invoice['status'] === 'vencido') {
                $overdueAmount += (float) $invoice['amount'];
            }
        }

        $this->json([
            'data' => [
                'summary' => [
                    'pending_count' => count(array_filter($invoices, static fn ($i) => in_array($i['status'], ['pendente', 'vencido'], true))),
                    'pending_amount' => round($pendingAmount, 2),
                    'overdue_amount' => round($overdueAmount, 2),
                    'contracts_count' => count($contracts),
                ],
                'invoices' => $invoices,
                'contracts' => $contracts,
                'enrollments' => $enrollments,
            ],
        ]);
    }

    private function contracts(int $studentId): array
    {
        if (!$this->hasTable('finance_contracts')) {
            return [];
        }

        $enrollmentJoin = $this->hasTable('enrollment')
            ? "LEFT JOIN enrollment e ON (
                    e.id = fc.enrollment_id
                    OR (
                        fc.enrollment_id IS NULL
                        AND e.id = (
                            SELECT e2.id
                            FROM enrollment e2
                            WHERE e2.aluno_id = fc.aluno_id
                              AND (e2.ano_letivo_id = fc.ano_letivo_id OR e2.ano_letivo_id IS NULL)
                              AND e2.contrato_token IS NOT NULL
                            ORDER BY e2.created_at DESC, e2.id DESC
                            LIMIT 1
                        )
                    )
                )"
            : "";
        $enrollmentCols = $this->hasTable('enrollment')
            ? ", e.tipo AS enrollment_type, e.status AS enrollment_status, e.contrato_token, e.contrato_pdf_path, e.assinado_em"
            : ", NULL AS enrollment_type, NULL AS enrollment_status, NULL AS contrato_token, NULL AS contrato_pdf_path, NULL AS assinado_em";

        $rows = $this->db->fetchAll(
            "SELECT fc.id, fc.status, fc.plano_pagamento, fc.valor_liquido, fc.valor_bruto,
                    fc.valor_desconto, fc.num_parcelas, fc.created_at, al.ano AS school_year
                    {$enrollmentCols}
             FROM finance_contracts fc
             LEFT JOIN ano_letivo al ON al.id = fc.ano_letivo_id
             {$enrollmentJoin}
             WHERE fc.aluno_id = :student_id
             ORDER BY fc.created_at DESC, fc.id DESC",
            ['student_id' => $studentId]
        ) ?: [];

        $itemsByContract = $this->contractItems(array_map(static fn ($row) => (int) $row['id'], $rows));

        return array_map(function (array $row) use ($itemsByContract): array {
            $token = trim((string) ($row['contrato_token'] ?? ''));
            $contractId = (int) $row['id'];
            return [
                'id' => $contractId,
                'status' => (string) ($row['status'] ?? ''),
                'payment_plan' => (string) ($row['plano_pagamento'] ?? ''),
                'school_year' => $row['school_year'] !== null ? (string) $row['school_year'] : null,
                'gross_amount' => (float) ($row['valor_bruto'] ?? 0),
                'discount_amount' => (float) ($row['valor_desconto'] ?? 0),
                'net_amount' => (float) ($row['valor_liquido'] ?? 0),
                'installments_count' => (int) ($row['num_parcelas'] ?? 0),
                'enrollment_type' => $row['enrollment_type'] !== null ? (string) $row['enrollment_type'] : null,
                'enrollment_status' => $row['enrollment_status'] !== null ? (string) $row['enrollment_status'] : null,
                'signed_at' => $row['assinado_em'] !== null ? (string) $row['assinado_em'] : null,
                'contract_url' => $token !== '' ? $this->absoluteUrl('/matricula/contrato/' . rawurlencode($token)) : null,
                'pdf_url' => !empty($row['contrato_pdf_path']) ? $this->absoluteUrl((string) $row['contrato_pdf_path']) : null,
                'items' => $itemsByContract[$contractId] ?? [],
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);
    }

    private function contractItems(array $contractIds): array
    {
        $contractIds = array_values(array_unique(array_filter(array_map('intval', $contractIds))));
        if ($contractIds === [] || !$this->hasTable('finance_contract_items')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($contractIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id, contract_id, categoria, descricao, valor_unitario, quantidade,
                    valor_total, valor_desconto, valor_liquido, num_parcelas,
                    mes_inicio, mes_fim, dia_vencimento, fornecedor_externo,
                    nome_instituicao, status
             FROM finance_contract_items
             WHERE contract_id IN ({$placeholders})
             ORDER BY contract_id, categoria, id",
            $contractIds
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $contractId = (int) $row['contract_id'];
            $out[$contractId][] = [
                'id' => (int) $row['id'],
                'category' => (string) ($row['categoria'] ?? ''),
                'description' => (string) ($row['descricao'] ?? ''),
                'unit_amount' => (float) ($row['valor_unitario'] ?? 0),
                'quantity' => (float) ($row['quantidade'] ?? 0),
                'total_amount' => (float) ($row['valor_total'] ?? 0),
                'discount_amount' => (float) ($row['valor_desconto'] ?? 0),
                'net_amount' => (float) ($row['valor_liquido'] ?? 0),
                'installments_count' => (int) ($row['num_parcelas'] ?? 0),
                'month_start' => $row['mes_inicio'] !== null ? (int) $row['mes_inicio'] : null,
                'month_end' => $row['mes_fim'] !== null ? (int) $row['mes_fim'] : null,
                'due_day' => $row['dia_vencimento'] !== null ? (int) $row['dia_vencimento'] : null,
                'external_provider' => !empty($row['fornecedor_externo']),
                'institution_name' => $row['nome_instituicao'] !== null ? (string) $row['nome_instituicao'] : null,
                'status' => (string) ($row['status'] ?? ''),
            ];
        }
        return $out;
    }

    private function installments(int $studentId): array
    {
        if (!$this->hasTable('finance_installments') || !$this->hasTable('finance_contracts')) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT fi.id, fi.contract_id, fi.num_parcela, fi.categoria, fi.descricao,
                    fi.valor_cobrado, fi.valor_pago, fi.data_vencimento, fi.data_pagamento,
                    fi.status, fi.boleto_codigo, fi.observacoes
             FROM finance_installments fi
             INNER JOIN finance_contracts fc ON fc.id = fi.contract_id
             WHERE fc.aluno_id = :student_id
               AND fi.status <> 'cancelado'
             ORDER BY fi.data_vencimento ASC, fi.id ASC",
            ['student_id' => $studentId]
        ) ?: [];

        return array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'source' => 'contract_installment',
                'contract_id' => (int) $row['contract_id'],
                'installment_number' => (int) ($row['num_parcela'] ?? 0),
                'category' => (string) ($row['categoria'] ?? ''),
                'description' => (string) ($row['descricao'] ?? 'Fatura'),
                'amount' => (float) ($row['valor_cobrado'] ?? 0),
                'paid_amount' => $row['valor_pago'] !== null ? (float) $row['valor_pago'] : null,
                'due_date' => (string) ($row['data_vencimento'] ?? ''),
                'paid_at' => $row['data_pagamento'] !== null ? (string) $row['data_pagamento'] : null,
                'status' => (string) ($row['status'] ?? ''),
                'barcode' => $row['boleto_codigo'] !== null ? (string) $row['boleto_codigo'] : null,
                'payment_url' => $this->absoluteUrl('/api/v1/students/' . (int) $studentId . '/finance/invoices/contract_installment/' . (int) $row['id'] . '/payment'),
                'notes' => $row['observacoes'] !== null ? (string) $row['observacoes'] : null,
            ];
        }, $rows);
    }

    private function charges(int $studentId): array
    {
        if (!$this->hasTable('finance_charges')) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT id, categoria, descricao, valor, valor_pago, data_vencimento, data_pagamento,
                    status, boleto_codigo, observacoes
             FROM finance_charges
             WHERE aluno_id = :student_id
               AND status <> 'cancelado'
             ORDER BY data_vencimento ASC, id ASC",
            ['student_id' => $studentId]
        ) ?: [];

        return array_map(function (array $row) use ($studentId): array {
            return [
                'id' => (int) $row['id'],
                'source' => 'charge',
                'contract_id' => null,
                'installment_number' => null,
                'category' => (string) ($row['categoria'] ?? ''),
                'description' => (string) ($row['descricao'] ?? 'Cobrança'),
                'amount' => (float) ($row['valor'] ?? 0),
                'paid_amount' => $row['valor_pago'] !== null ? (float) $row['valor_pago'] : null,
                'due_date' => (string) ($row['data_vencimento'] ?? ''),
                'paid_at' => $row['data_pagamento'] !== null ? (string) $row['data_pagamento'] : null,
                'status' => (string) ($row['status'] ?? ''),
                'barcode' => $row['boleto_codigo'] !== null ? (string) $row['boleto_codigo'] : null,
                'payment_url' => $this->absoluteUrl('/api/v1/students/' . (int) $studentId . '/finance/invoices/charge/' . (int) $row['id'] . '/payment'),
                'notes' => $row['observacoes'] !== null ? (string) $row['observacoes'] : null,
            ];
        }, $rows);
    }

    public function payment($id, $source, $invoiceId): void
    {
        $studentId = $this->authorizedStudentId($id);
        $invoiceId = filter_var($invoiceId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $source = trim((string) $source);

        if ($invoiceId === false || !in_array($source, ['contract_installment', 'charge'], true)) {
            $this->json(['error' => ['code' => 'invoice_not_found', 'message' => 'Fatura não encontrada.']], 404);
            return;
        }

        $invoice = $source === 'contract_installment'
            ? $this->findInstallmentPayment($studentId, (int) $invoiceId)
            : $this->findChargePayment($studentId, (int) $invoiceId);

        if (!$invoice) {
            $this->json(['error' => ['code' => 'invoice_not_found', 'message' => 'Fatura não encontrada.']], 404);
            return;
        }

        $amount = (float) $invoice['amount'];
        $dueDate = (string) ($invoice['due_date'] ?: date('Y-m-d'));
        $barcode = (string) ($invoice['barcode'] ?? '');

        if ($barcode === '') {
            $barcode = $source === 'contract_installment'
                ? $this->boleto->gerarEPersistir((int) $invoiceId, $amount, $dueDate)
                : $this->boleto->gerarCodigo((int) $invoiceId, $amount, $dueDate);
        }

        $line = $this->boleto->formatarLinhaDigitavel($barcode);
        $pixKey = 'financeiro@educatudo.com';
        $pixPayload = $this->pixPayload($pixKey, $amount, (string) $invoice['description'], (int) $studentId, (int) $invoiceId);

        $this->json([
            'data' => [
                'invoice' => $invoice,
                'payment' => [
                    'simulation' => true,
                    'amount' => $amount,
                    'due_date' => $dueDate,
                    'boleto' => [
                        'barcode' => $barcode,
                        'line' => $line,
                    ],
                    'pix' => [
                        'key' => $pixKey,
                        'copy_paste' => $pixPayload,
                        'qr_payload' => $pixPayload,
                    ],
                    'notice' => 'Pagamento simulado para homologação. Não possui validade bancária.',
                ],
            ],
        ]);
    }

    private function findInstallmentPayment(int $studentId, int $invoiceId): ?array
    {
        if (!$this->hasTable('finance_installments') || !$this->hasTable('finance_contracts')) {
            return null;
        }

        $row = $this->db->fetch(
            "SELECT fi.id, fi.contract_id, fi.num_parcela, fi.categoria, fi.descricao,
                    fi.valor_cobrado, fi.valor_pago, fi.data_vencimento, fi.data_pagamento,
                    fi.status, fi.boleto_codigo
             FROM finance_installments fi
             INNER JOIN finance_contracts fc ON fc.id = fi.contract_id
             WHERE fi.id = :invoice_id
               AND fc.aluno_id = :student_id
               AND fi.status <> 'cancelado'
             LIMIT 1",
            ['invoice_id' => $invoiceId, 'student_id' => $studentId]
        );
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'source' => 'contract_installment',
            'contract_id' => (int) $row['contract_id'],
            'installment_number' => (int) ($row['num_parcela'] ?? 0),
            'category' => (string) ($row['categoria'] ?? ''),
            'description' => (string) ($row['descricao'] ?? 'Fatura'),
            'amount' => (float) ($row['valor_cobrado'] ?? 0),
            'paid_amount' => $row['valor_pago'] !== null ? (float) $row['valor_pago'] : null,
            'due_date' => (string) ($row['data_vencimento'] ?? ''),
            'paid_at' => $row['data_pagamento'] !== null ? (string) $row['data_pagamento'] : null,
            'status' => (string) ($row['status'] ?? ''),
            'barcode' => $row['boleto_codigo'] !== null ? (string) $row['boleto_codigo'] : null,
        ];
    }

    private function findChargePayment(int $studentId, int $invoiceId): ?array
    {
        if (!$this->hasTable('finance_charges')) {
            return null;
        }

        $row = $this->db->fetch(
            "SELECT id, categoria, descricao, valor, valor_pago, data_vencimento, data_pagamento,
                    status, boleto_codigo
             FROM finance_charges
             WHERE id = :invoice_id
               AND aluno_id = :student_id
               AND status <> 'cancelado'
             LIMIT 1",
            ['invoice_id' => $invoiceId, 'student_id' => $studentId]
        );
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'source' => 'charge',
            'contract_id' => null,
            'installment_number' => null,
            'category' => (string) ($row['categoria'] ?? ''),
            'description' => (string) ($row['descricao'] ?? 'Cobrança'),
            'amount' => (float) ($row['valor'] ?? 0),
            'paid_amount' => $row['valor_pago'] !== null ? (float) $row['valor_pago'] : null,
            'due_date' => (string) ($row['data_vencimento'] ?? ''),
            'paid_at' => $row['data_pagamento'] !== null ? (string) $row['data_pagamento'] : null,
            'status' => (string) ($row['status'] ?? ''),
            'barcode' => $row['boleto_codigo'] !== null ? (string) $row['boleto_codigo'] : null,
        ];
    }

    private function enrollments(int $studentId): array
    {
        if (!$this->hasTable('enrollment')) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT e.id, e.tipo, e.status, e.contrato_token, e.contrato_pdf_path,
                    e.assinado_em, e.created_at, al.ano AS school_year, t.nome AS class_name
             FROM enrollment e
             LEFT JOIN ano_letivo al ON al.id = e.ano_letivo_id
             LEFT JOIN turmas t ON t.id = e.turma_id
             WHERE e.aluno_id = :student_id
             ORDER BY e.created_at DESC, e.id DESC",
            ['student_id' => $studentId]
        ) ?: [];

        return array_map(function (array $row): array {
            $token = trim((string) ($row['contrato_token'] ?? ''));
            return [
                'id' => (int) $row['id'],
                'type' => (string) ($row['tipo'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'school_year' => $row['school_year'] !== null ? (string) $row['school_year'] : null,
                'class_name' => $row['class_name'] !== null ? (string) $row['class_name'] : null,
                'signed_at' => $row['assinado_em'] !== null ? (string) $row['assinado_em'] : null,
                'contract_url' => $token !== '' ? $this->absoluteUrl('/matricula/contrato/' . rawurlencode($token)) : null,
                'pdf_url' => !empty($row['contrato_pdf_path']) ? $this->absoluteUrl((string) $row['contrato_pdf_path']) : null,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);
    }

    private function authorizedStudentId($rawId): int
    {
        $studentId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $parentId = (int) MobileApiAuthMiddleware::$parentId;
        if ($studentId === false || !$this->policy->canAccess($parentId, (int) $studentId)) {
            $this->json(['error' => ['code' => 'student_not_found', 'message' => 'Aluno não encontrado.']], 404);
        }
        return (int) $studentId;
    }

    private function pixPayload(string $key, float $amount, string $description, int $studentId, int $invoiceId): string
    {
        $txid = 'EDU' . $studentId . 'F' . $invoiceId;
        $desc = preg_replace('/[^A-Za-z0-9 ]/', '', $description) ?: 'EducaTudo';
        return 'PIX-SIMULADO|' . $key
            . '|VALOR=' . number_format($amount, 2, '.', '')
            . '|TXID=' . substr($txid, 0, 25)
            . '|DESC=' . substr($desc, 0, 40);
    }

    private function hasTable(string $table): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            );
            return !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function absoluteUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $base = rtrim(defined('URL') ? URL : '', '/');
        return $base . '/' . ltrim($path, '/');
    }
}
