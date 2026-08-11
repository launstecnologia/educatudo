<?php

namespace App\Models\Finance;

use Database;

class FinancePlan
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function all(?int $anoLetivoId = null, bool $apenasAtivos = true): array
    {
        $where = $apenasAtivos ? ['fp.ativo = 1'] : ['1=1'];
        $params = [];
        if ($anoLetivoId) { $where[] = 'fp.ano_letivo_id = ?'; $params[] = $anoLetivoId; }
        return $this->db->fetchAll(
            "SELECT fp.*, al.ano AS ano_letivo_nome, s.nome AS serie_nome
             FROM finance_plans fp
             LEFT JOIN ano_letivo al ON al.id = fp.ano_letivo_id
             LEFT JOIN serie s       ON s.id  = fp.serie_id
             WHERE " . implode(' AND ', $where) . " ORDER BY fp.nome",
            $params
        ) ?: [];
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT fp.*, al.ano AS ano_letivo_nome, s.nome AS serie_nome
             FROM finance_plans fp
             LEFT JOIN ano_letivo al ON al.id = fp.ano_letivo_id
             LEFT JOIN serie s       ON s.id  = fp.serie_id
             WHERE fp.id = ?",
            [$id]
        ) ?: null;
    }

    public function getItems(int $planId): array
    {
        // LEFT JOIN unidades only when table exists (not all tenants have it)
        $hasUnidades = $this->db->fetch(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unidades'"
        );
        if ($hasUnidades) {
            return $this->db->fetchAll(
                "SELECT fpi.*, u.nome AS unidade_nome, u.razao_social AS unidade_razao, u.cnpj AS unidade_cnpj
                 FROM finance_plan_items fpi
                 LEFT JOIN unidades u ON u.id = fpi.unidade_id
                 WHERE fpi.plan_id = ?
                 ORDER BY fpi.ordem, fpi.id",
                [$planId]
            ) ?: [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM finance_plan_items WHERE plan_id = ? ORDER BY ordem, id",
            [$planId]
        ) ?: [];
    }

    public function create(array $d): int
    {
        $this->db->insert(
            "INSERT INTO finance_plans (nome, descricao, ano_letivo_id, serie_id, ativo) VALUES (?,?,?,?,?)",
            [
                $d['nome'], $d['descricao'] ?? null,
                (int)$d['ano_letivo_id'], (int)($d['serie_id'] ?? 0) ?: null,
                !empty($d['ativo']) ? 1 : 1,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function addItem(int $planId, array $d): int
    {
        $this->db->insert(
            "INSERT INTO finance_plan_items
             (plan_id, categoria, descricao, valor_base, num_parcelas, mes_inicio, mes_fim, dia_vencimento, fornecedor_externo, nome_instituicao, unidade_id, ordem)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $planId, $d['categoria'], $d['descricao'],
                (float)str_replace(',', '.', $d['valor_base'] ?? '0'),
                (int)($d['num_parcelas'] ?? 1),
                (int)($d['mes_inicio'] ?? 1),
                (int)($d['mes_fim'] ?? $d['mes_inicio'] ?? 1) ?: null,
                (int)($d['dia_vencimento'] ?? 0) ?: null,
                !empty($d['fornecedor_externo']) ? 1 : 0,
                trim($d['nome_instituicao'] ?? '') ?: null,
                (int)($d['unidade_id'] ?? 0) ?: null,
                (int)($d['ordem'] ?? 0),
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function removeItem(int $itemId): void
    {
        $this->db->update("DELETE FROM finance_plan_items WHERE id = ?", [$itemId]);
    }

    public function toggle(int $id): void
    {
        $this->db->update("UPDATE finance_plans SET ativo = NOT ativo WHERE id = ?", [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->update("DELETE FROM finance_plan_items WHERE plan_id = ?", [$id]);
        $this->db->update("DELETE FROM finance_plans WHERE id = ?", [$id]);
    }

    public function totalPlan(int $planId): float
    {
        $items = $this->getItems($planId);
        return array_sum(array_column($items, 'valor_base'));
    }
}
