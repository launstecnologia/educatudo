<?php

namespace App\Models\Finance;

use Database;

class FinanceContract
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function schemaReady(): bool
    {
        try {
            $r = $this->db->fetch(
                "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                ['finance_contracts']
            );
            return !empty($r);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT fc.*,
                    a.nome AS aluno_nome, a.ra AS aluno_ra,
                    t.nome AS turma_nome, t.serie AS aluno_serie,
                    al.ano AS ano_letivo_nome
             FROM finance_contracts fc
             LEFT JOIN alunos a       ON a.id  = fc.aluno_id
             LEFT JOIN turmas t       ON t.id  = (SELECT turma_id FROM matricula WHERE aluno_id = fc.aluno_id AND ano_letivo_id = fc.ano_letivo_id ORDER BY id DESC LIMIT 1)
             LEFT JOIN ano_letivo al  ON al.id = fc.ano_letivo_id
             WHERE fc.id = ?",
            [$id]
        ) ?: null;
    }

    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'fc.status = ?'; $params[] = $filters['status'];
        }
        if (!empty($filters['ano_letivo_id'])) {
            $where[] = 'fc.ano_letivo_id = ?'; $params[] = (int)$filters['ano_letivo_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(a.nome LIKE ? OR a.ra LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like; $params[] = $like;
        }

        $sql = "SELECT fc.*, a.nome AS aluno_nome, a.ra AS aluno_ra, al.ano AS ano_letivo_nome
                FROM finance_contracts fc
                LEFT JOIN alunos a      ON a.id  = fc.aluno_id
                LEFT JOIN ano_letivo al ON al.id = fc.ano_letivo_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY fc.created_at DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    public function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) { $where[] = 'fc.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['ano_letivo_id'])) { $where[] = 'fc.ano_letivo_id = ?'; $params[] = (int)$filters['ano_letivo_id']; }
        if (!empty($filters['q'])) {
            $where[] = '(a.nome LIKE ? OR a.ra LIKE ?)';
            $like = '%' . $filters['q'] . '%'; $params[] = $like; $params[] = $like;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM finance_contracts fc LEFT JOIN alunos a ON a.id = fc.aluno_id WHERE " . implode(' AND ', $where),
            $params
        );
        return (int)($row['total'] ?? 0);
    }

    public function create(array $d): int
    {
        $this->db->insert(
            "INSERT INTO finance_contracts
             (aluno_id, matricula_id, enrollment_id, plan_id, ano_letivo_id,
              responsavel_id, responsavel_nome, responsavel_cpf, responsavel_email, responsavel_telefone,
              valor_bruto, valor_desconto, valor_liquido,
              status, plano_pagamento, num_parcelas, dia_vencimento, mes_inicio, mes_fim,
              observacoes, criado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $d['aluno_id'],       $d['matricula_id'] ?? null,    $d['enrollment_id'] ?? null,
                $d['plan_id'] ?? null, $d['ano_letivo_id'],  $d['responsavel_id'] ?? null,
                $d['responsavel_nome'] ?? '', $d['responsavel_cpf'] ?? null,
                $d['responsavel_email'] ?? null, $d['responsavel_telefone'] ?? null,
                $d['valor_bruto'] ?? 0, $d['valor_desconto'] ?? 0, $d['valor_liquido'] ?? 0,
                $d['status'] ?? 'rascunho', $d['plano_pagamento'] ?? 'mensal',
                $d['num_parcelas'] ?? 12, $d['dia_vencimento'] ?? 10,
                $d['mes_inicio'] ?? 1, $d['mes_fim'] ?? 12,
                $d['observacoes'] ?? null, $d['criado_por'] ?? null,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $allowed = [
            'status','responsavel_nome','responsavel_cpf','responsavel_email','responsavel_telefone',
            'valor_bruto','valor_desconto','valor_liquido',
            'plano_pagamento','num_parcelas','dia_vencimento','mes_inicio','mes_fim','observacoes',
        ];
        $sets = []; $params = [];
        foreach ($d as $k => $v) {
            if (in_array($k, $allowed, true)) { $sets[] = "`$k` = ?"; $params[] = $v; }
        }
        if (empty($sets)) return;
        $params[] = $id;
        $this->db->update("UPDATE finance_contracts SET " . implode(', ', $sets) . " WHERE id = ?", $params);
    }

    public function getDiscounts(int $contractId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM finance_contract_discounts WHERE contract_id = ? ORDER BY id",
            [$contractId]
        ) ?: [];
    }

    public function addDiscount(array $d): int
    {
        $this->db->insert(
            "INSERT INTO finance_contract_discounts
             (contract_id, discount_rule_id, tipo, descricao, calculo, valor, valor_aplicado, irmao_aluno_id, aprovado_por, aprovado_em, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $d['contract_id'], $d['discount_rule_id'] ?? null, $d['tipo'],
                $d['descricao'], $d['calculo'], $d['valor'], $d['valor_aplicado'],
                $d['irmao_aluno_id'] ?? null, $d['aprovado_por'] ?? null,
                $d['aprovado_em'] ?? null, $d['status'] ?? 'aprovado',
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function removeDiscount(int $discountId): void
    {
        $this->db->update("DELETE FROM finance_contract_discounts WHERE id = ?", [$discountId]);
    }

    public function approveDiscount(int $discountId, int $userId): void
    {
        $this->db->update(
            "UPDATE finance_contract_discounts SET status = 'aprovado', aprovado_por = ?, aprovado_em = NOW() WHERE id = ?",
            [$userId, $discountId]
        );
    }

    public function rejectDiscount(int $discountId): void
    {
        $this->db->update(
            "UPDATE finance_contract_discounts SET status = 'rejeitado' WHERE id = ?",
            [$discountId]
        );
    }

    public function countsByStatus(): array
    {
        $rows = $this->db->fetchAll("SELECT status, COUNT(*) AS total FROM finance_contracts GROUP BY status") ?: [];
        $out = [];
        foreach ($rows as $r) { $out[$r['status']] = (int)$r['total']; }
        return $out;
    }

    public function getInstallmentsSummary(int $contractId): array
    {
        return $this->db->fetch(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) AS pagas,
                SUM(CASE WHEN status = 'vencido' THEN 1 ELSE 0 END) AS vencidas,
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
                SUM(valor_cobrado) AS total_cobrado,
                SUM(CASE WHEN status = 'pago' THEN valor_pago ELSE 0 END) AS total_pago
             FROM finance_installments WHERE contract_id = ?",
            [$contractId]
        ) ?: [];
    }
}
