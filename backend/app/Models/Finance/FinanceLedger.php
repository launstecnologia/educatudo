<?php

namespace App\Models\Finance;

use Database;

class FinanceLedger
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function getSaldo(int $alunoId): float
    {
        $row = $this->db->fetch(
            "SELECT saldo_acumulado FROM finance_ledger
             WHERE aluno_id = ? ORDER BY data_lancamento DESC, id DESC LIMIT 1",
            [$alunoId]
        );
        return $row ? (float)$row['saldo_acumulado'] : 0.0;
    }

    public function getExtrato(int $alunoId, ?string $dataInicio = null, ?string $dataFim = null, int $limit = 100): array
    {
        $where = ['aluno_id = ?'];
        $params = [$alunoId];
        if ($dataInicio) { $where[] = 'data_lancamento >= ?'; $params[] = $dataInicio; }
        if ($dataFim)    { $where[] = 'data_lancamento <= ?'; $params[] = $dataFim; }
        return $this->db->fetchAll(
            "SELECT * FROM finance_ledger WHERE " . implode(' AND ', $where) .
            " ORDER BY data_lancamento DESC, id DESC LIMIT " . (int)$limit,
            $params
        ) ?: [];
    }

    public function lancar(array $d): int
    {
        // Calcula saldo acumulado
        $saldoAtual = $this->getSaldo((int)$d['aluno_id']);
        $valor      = abs((float)$d['valor']);
        $novoSaldo  = $d['tipo'] === 'credito'
            ? round($saldoAtual + $valor, 2)   // crédito diminui a dívida (saldo vai pra 0 ou positivo)
            : round($saldoAtual - $valor, 2);   // débito aumenta a dívida (saldo fica negativo)

        $this->db->insert(
            "INSERT INTO finance_ledger
             (aluno_id, tipo, categoria, descricao, valor, saldo_acumulado,
              data_lancamento, referencia_tipo, referencia_id, contract_id, observacoes, gerado_auto)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                (int)$d['aluno_id'],
                $d['tipo'],
                $d['categoria'] ?? 'outros',
                $d['descricao'],
                $valor,
                $novoSaldo,
                $d['data_lancamento'] ?? date('Y-m-d'),
                $d['referencia_tipo'] ?? null,
                $d['referencia_id']   ?? null,
                $d['contract_id']     ?? null,
                $d['observacoes']     ?? null,
                !empty($d['gerado_auto']) ? 1 : 0,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function jaLancouDebito(int $installmentId): bool
    {
        $r = $this->db->fetch(
            "SELECT id FROM finance_ledger WHERE referencia_tipo = 'installment' AND referencia_id = ? AND tipo = 'debito' LIMIT 1",
            [$installmentId]
        );
        return !empty($r);
    }

    public function jaLancouCredito(int $paymentId): bool
    {
        $r = $this->db->fetch(
            "SELECT id FROM finance_ledger WHERE referencia_tipo = 'payment' AND referencia_id = ? AND tipo = 'credito' LIMIT 1",
            [$paymentId]
        );
        return !empty($r);
    }

    public function estornar(int $ledgerId, string $motivo, ?int $userId): void
    {
        $entry = $this->db->fetch("SELECT * FROM finance_ledger WHERE id = ?", [$ledgerId]);
        if (!$entry) return;
        $tipoEstorno = $entry['tipo'] === 'debito' ? 'credito' : 'debito';
        $this->lancar([
            'aluno_id'       => $entry['aluno_id'],
            'tipo'           => $tipoEstorno,
            'categoria'      => $entry['categoria'],
            'descricao'      => 'Estorno: ' . $entry['descricao'],
            'valor'          => $entry['valor'],
            'data_lancamento'=> date('Y-m-d'),
            'referencia_tipo'=> 'estorno',
            'referencia_id'  => $ledgerId,
            'contract_id'    => $entry['contract_id'],
            'observacoes'    => $motivo,
        ]);
    }

    public function kpisByAluno(int $alunoId): array
    {
        return $this->db->fetch(
            "SELECT
                SUM(CASE WHEN tipo = 'debito'  THEN valor ELSE 0 END) AS total_debitado,
                SUM(CASE WHEN tipo = 'credito' THEN valor ELSE 0 END) AS total_creditado,
                (SELECT saldo_acumulado FROM finance_ledger WHERE aluno_id = ? ORDER BY data_lancamento DESC, id DESC LIMIT 1) AS saldo_atual
             FROM finance_ledger WHERE aluno_id = ?",
            [$alunoId, $alunoId]
        ) ?: ['total_debitado' => 0, 'total_creditado' => 0, 'saldo_atual' => 0];
    }
}
