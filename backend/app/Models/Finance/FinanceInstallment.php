<?php

namespace App\Models\Finance;

use Database;

class FinanceInstallment
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT fi.*, fc.aluno_id, fc.responsavel_id, fc.responsavel_nome,
                    fc.responsavel_email, fc.responsavel_telefone,
                    a.nome AS aluno_nome, a.ra AS aluno_ra, al.ano AS ano_letivo_nome
             FROM finance_installments fi
             JOIN finance_contracts fc ON fc.id = fi.contract_id
             LEFT JOIN alunos a        ON a.id  = fc.aluno_id
             LEFT JOIN ano_letivo al   ON al.id = fc.ano_letivo_id
             WHERE fi.id = ?",
            [$id]
        ) ?: null;
    }

    public function byContract(int $contractId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM finance_installments WHERE contract_id = ? ORDER BY data_vencimento ASC, num_parcela ASC",
            [$contractId]
        ) ?: [];
    }

    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) { $where[] = 'fi.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['vencimento_de'])) { $where[] = 'fi.data_vencimento >= ?'; $params[] = $filters['vencimento_de']; }
        if (!empty($filters['vencimento_ate'])) { $where[] = 'fi.data_vencimento <= ?'; $params[] = $filters['vencimento_ate']; }
        if (!empty($filters['ano_letivo_id'])) { $where[] = 'fc.ano_letivo_id = ?'; $params[] = (int)$filters['ano_letivo_id']; }
        if (!empty($filters['q'])) {
            $where[] = '(a.nome LIKE ? OR a.ra LIKE ?)';
            $like = '%' . $filters['q'] . '%'; $params[] = $like; $params[] = $like;
        }

        $sql = "SELECT fi.*, a.nome AS aluno_nome, a.ra AS aluno_ra, fc.responsavel_nome
                FROM finance_installments fi
                JOIN finance_contracts fc ON fc.id = fi.contract_id
                LEFT JOIN alunos a        ON a.id  = fc.aluno_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY fi.data_vencimento ASC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    public function insert(array $d): int
    {
        $this->db->insert(
            "INSERT INTO finance_installments
             (contract_id, num_parcela, categoria, descricao,
              valor_nominal, valor_desconto, valor_cobrado, data_vencimento, status)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [
                $d['contract_id'], $d['num_parcela'], $d['categoria'], $d['descricao'],
                $d['valor_nominal'], $d['valor_desconto'] ?? 0, $d['valor_cobrado'],
                $d['data_vencimento'], $d['status'] ?? 'pendente',
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function markPaid(int $id, float $valorPago, string $dataPagamento): void
    {
        $this->db->update(
            "UPDATE finance_installments
             SET status = 'pago', valor_pago = ?, data_pagamento = ?, updated_at = NOW()
             WHERE id = ?",
            [$valorPago, $dataPagamento, $id]
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->update(
            "UPDATE finance_installments SET status = ?, updated_at = NOW() WHERE id = ?",
            [$status, $id]
        );
    }

    public function setBoleto(int $id, string $codigo): void
    {
        $this->db->update(
            "UPDATE finance_installments SET boleto_codigo = ?, boleto_gerado_em = NOW() WHERE id = ?",
            [$codigo, $id]
        );
    }

    public function updateLateCharges(int $id, float $juros, float $multa): void
    {
        $this->db->update(
            "UPDATE finance_installments SET juros_aplicado = ?, multa_aplicada = ?, updated_at = NOW() WHERE id = ?",
            [$juros, $multa, $id]
        );
    }

    public function deleteByContract(int $contractId): void
    {
        $this->db->update("DELETE FROM finance_installments WHERE contract_id = ?", [$contractId]);
    }

    public function getOverdue(): array
    {
        return $this->db->fetchAll(
            "SELECT fi.*, fc.aluno_id, fc.responsavel_id, fc.responsavel_email, fc.responsavel_telefone,
                    a.nome AS aluno_nome
             FROM finance_installments fi
             JOIN finance_contracts fc ON fc.id = fi.contract_id
             LEFT JOIN alunos a ON a.id = fc.aluno_id
             WHERE fi.status = 'pendente' AND fi.data_vencimento < CURDATE()",
            []
        ) ?: [];
    }

    public function kpis(?int $anoLetivoId = null): array
    {
        $where = $anoLetivoId ? "WHERE fc.ano_letivo_id = $anoLetivoId" : '';
        return $this->db->fetch(
            "SELECT
                SUM(fi.valor_cobrado) AS receita_prevista,
                SUM(CASE WHEN fi.status = 'pago' THEN fi.valor_pago ELSE 0 END) AS receita_realizada,
                COUNT(CASE WHEN fi.status = 'vencido' THEN 1 END) AS qtd_vencidas,
                COUNT(CASE WHEN fi.status = 'pendente' THEN 1 END) AS qtd_pendentes,
                COUNT(CASE WHEN fi.status = 'pago' THEN 1 END) AS qtd_pagas,
                COUNT(*) AS qtd_total
             FROM finance_installments fi
             JOIN finance_contracts fc ON fc.id = fi.contract_id
             $where",
            []
        ) ?: [];
    }
}
