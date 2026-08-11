<?php

namespace App\Models\Finance;

use Database;

class FinanceContractItem
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function byContract(int $contractId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM finance_contract_items WHERE contract_id = ? AND status = 'ativo' ORDER BY categoria, id",
            [$contractId]
        ) ?: [];
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM finance_contract_items WHERE id = ?", [$id]) ?: null;
    }

    public function create(int $contractId, array $d): int
    {
        $unitario = (float)str_replace(',', '.', $d['valor_unitario'] ?? '0');
        $qtd      = (float)($d['quantidade'] ?? 1);
        $total    = round($unitario * $qtd, 2);
        $desconto = (float)($d['valor_desconto'] ?? 0);
        $liquido  = max(0, round($total - $desconto, 2));

        $this->db->insert(
            "INSERT INTO finance_contract_items
             (contract_id, plan_item_id, price_table_id, categoria, descricao,
              valor_unitario, quantidade, valor_total, valor_desconto, valor_liquido,
              num_parcelas, mes_inicio, mes_fim, dia_vencimento,
              fornecedor_externo, nome_instituicao, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'ativo')",
            [
                $contractId,
                (int)($d['plan_item_id'] ?? 0) ?: null,
                (int)($d['price_table_id'] ?? 0) ?: null,
                $d['categoria'],
                $d['descricao'],
                $unitario,
                $qtd,
                $total,
                $desconto,
                $liquido,
                (int)($d['num_parcelas'] ?? 1),
                (int)($d['mes_inicio'] ?? 1),
                (int)($d['mes_fim'] ?? $d['mes_inicio'] ?? 1) ?: null,
                (int)($d['dia_vencimento'] ?? 0) ?: null,
                !empty($d['fornecedor_externo']) ? 1 : 0,
                trim($d['nome_instituicao'] ?? '') ?: null,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function cancel(int $id): void
    {
        $this->db->update("UPDATE finance_contract_items SET status = 'cancelado' WHERE id = ?", [$id]);
    }

    public function totalLiquido(int $contractId): float
    {
        $r = $this->db->fetch(
            "SELECT SUM(valor_liquido) AS total FROM finance_contract_items WHERE contract_id = ? AND status = 'ativo'",
            [$contractId]
        );
        return (float)($r['total'] ?? 0);
    }
}
