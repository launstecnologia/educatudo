<?php

namespace App\Services;

use App\Models\Finance\FinanceLedger;
use App\Models\Finance\FinanceConfig;
use Database;

class FinanceLedgerService
{
    private FinanceLedger $ledger;
    private FinanceConfig $config;

    public function __construct(?Database $db = null)
    {
        $this->ledger = new FinanceLedger($db);
        $this->config = new FinanceConfig($db);
    }

    /**
     * Lança débito automático quando uma parcela vence (dia 1 do mês ou data_vencimento).
     * Idempotente — verifica se já existe lançamento para esta parcela.
     */
    public function lancarDebitoParcela(array $installment, array $contract): void
    {
        if ($this->ledger->jaLancouDebito((int)$installment['id'])) return;

        $cfg = $this->config->get();
        $categoria = $installment['categoria'] ?? 'mensalidade';

        $this->ledger->lancar([
            'aluno_id'        => (int)$contract['aluno_id'],
            'tipo'            => 'debito',
            'categoria'       => $categoria,
            'descricao'       => sprintf(
                '%s %d/%d — Parcela #%d',
                ucfirst(str_replace('_', ' ', $categoria)),
                $installment['numero_parcela'] ?? 1,
                $installment['total_parcelas'] ?? 1,
                $installment['id']
            ),
            'valor'           => $installment['valor_liquido'] ?? $installment['valor'],
            'data_lancamento' => $installment['data_vencimento'],
            'referencia_tipo' => 'installment',
            'referencia_id'   => (int)$installment['id'],
            'contract_id'     => (int)$installment['contract_id'],
            'gerado_auto'     => true,
        ]);
    }

    /**
     * Lança crédito quando o pagamento é registrado.
     * Idempotente — verifica se já existe lançamento para este pagamento.
     */
    public function lancarCreditoPagamento(array $payment, array $installment, array $contract): void
    {
        if ($this->ledger->jaLancouCredito((int)$payment['id'])) return;

        $this->ledger->lancar([
            'aluno_id'        => (int)$contract['aluno_id'],
            'tipo'            => 'credito',
            'categoria'       => $installment['categoria'] ?? 'mensalidade',
            'descricao'       => sprintf(
                'Pagamento parcela #%d — %s',
                $installment['numero_parcela'] ?? 1,
                ucfirst($payment['forma_pagamento'] ?? 'dinheiro')
            ),
            'valor'           => $payment['valor_pago'],
            'data_lancamento' => $payment['data_pagamento'],
            'referencia_tipo' => 'payment',
            'referencia_id'   => (int)$payment['id'],
            'contract_id'     => (int)$installment['contract_id'],
            'gerado_auto'     => true,
        ]);
    }

    /**
     * Lança débito para cobrança avulsa.
     */
    public function lancarDebitoAvulso(array $charge): void
    {
        $this->ledger->lancar([
            'aluno_id'        => (int)$charge['aluno_id'],
            'tipo'            => 'debito',
            'categoria'       => $charge['categoria'] ?? 'outros',
            'descricao'       => $charge['descricao'],
            'valor'           => $charge['valor'],
            'data_lancamento' => $charge['data_vencimento'],
            'referencia_tipo' => 'charge',
            'referencia_id'   => (int)$charge['id'],
            'gerado_auto'     => true,
        ]);
    }

    /**
     * Processa todas as parcelas pendentes cujo vencimento é hoje (chamado pelo cron).
     * Só lança se gerar_debito_auto = 1 na config.
     */
    public function processarDebitosHoje(): void
    {
        $cfg = $this->config->get();
        if (empty($cfg['gerar_debito_auto'])) return;

        $db = Database::getInstance();
        $parcelas = $db->fetchAll(
            "SELECT fi.*, fc.aluno_id
             FROM finance_installments fi
             JOIN finance_contracts fc ON fc.id = fi.contract_id
             WHERE fi.status IN ('pendente','vencido')
               AND fi.data_vencimento = CURDATE()",
            []
        ) ?: [];

        foreach ($parcelas as $parcela) {
            try {
                $contract = $db->fetch("SELECT * FROM finance_contracts WHERE id = ?", [$parcela['contract_id']]);
                if ($contract) {
                    $this->lancarDebitoParcela($parcela, $contract);
                }
            } catch (\Throwable $e) {
                // silent — continua as demais
            }
        }
    }

    public function getExtrato(int $alunoId, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        return $this->ledger->getExtrato($alunoId, $dataInicio, $dataFim);
    }

    public function getSaldo(int $alunoId): float
    {
        return $this->ledger->getSaldo($alunoId);
    }

    public function estornar(int $ledgerId, string $motivo, ?int $userId): void
    {
        $this->ledger->estornar($ledgerId, $motivo, $userId);
    }
}
