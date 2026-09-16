<?php

namespace App\Modulos\AgrupamentosComponentes\Models;

use Database;
use Throwable;

/**
 * Cadastro reutilizável de agrupamento de componentes (linha única).
 */
class AgrupamentoComponente
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'agrupamentos_componentes'
                 LIMIT 1"
            );
            $ok = !empty($row['ok']);
        } catch (Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(bool $apenasAtivos = false): array
    {
        if (!$this->tabelasProntas()) {
            return [];
        }
        $sql = "SELECT a.*, m.nome AS materia_rotulo_nome,
                       CASE
                         WHEN a.materia_rotulo_id IS NOT NULL AND a.materia_rotulo_id > 0
                         THEN (SELECT COUNT(*) FROM materias mf WHERE mf.pai_id = a.materia_rotulo_id AND mf.ativo = 1)
                         ELSE (SELECT COUNT(*) FROM agrupamentos_componentes_itens i WHERE i.agrupamento_id = a.id)
                       END AS itens_qtd
                FROM agrupamentos_componentes a
                LEFT JOIN materias m ON m.id = a.materia_rotulo_id";
        if ($apenasAtivos) {
            $sql .= " WHERE a.ativo = 1";
        }
        $sql .= " ORDER BY a.nome ASC";
        return $this->db->fetchAll($sql) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        if (!$this->tabelasProntas() || $id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT a.*, m.nome AS materia_rotulo_nome
             FROM agrupamentos_componentes a
             LEFT JOIN materias m ON m.id = a.materia_rotulo_id
             WHERE a.id = :id",
            ['id' => $id]
        );
        return is_array($row) ? $row : null;
    }

    /**
     * @return list<int>
     */
    public function listarMateriaIds(int $agrupamentoId): array
    {
        if (!$this->tabelasProntas() || $agrupamentoId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT materia_id FROM agrupamentos_componentes_itens WHERE agrupamento_id = :id ORDER BY materia_id ASC",
            ['id' => $agrupamentoId]
        ) ?: [];
        $ids = [];
        foreach ($rows as $r) {
            $mid = (int) ($r['materia_id'] ?? 0);
            if ($mid > 0) {
                $ids[] = $mid;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function carregarCompleto(int $id): ?array
    {
        $row = $this->findById($id);
        if ($row === null) {
            return null;
        }
        $row['materias_ids'] = $this->listarMateriaIds($id);
        return $row;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarCompletos(bool $apenasAtivos = true): array
    {
        $out = [];
        foreach ($this->listar($apenasAtivos) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $row['materias_ids'] = $this->listarMateriaIds($id);
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @param list<int> $materiaIds
     */
    public function criar(array $data, array $materiaIds): int
    {
        $id = (int) $this->db->insert(
            "INSERT INTO agrupamentos_componentes (nome, modo, aplicar_em, divisor, materia_rotulo_id, ativo)
             VALUES (:nome, :modo, :aplicar_em, :divisor, :materia_rotulo_id, :ativo)",
            $this->paramsCabecalho($data)
        );
        $this->salvarItens($id, $materiaIds);
        return $id;
    }

    /**
     * @param list<int> $materiaIds
     */
    public function atualizar(int $id, array $data, array $materiaIds): void
    {
        $params = $this->paramsCabecalho($data);
        $params['id'] = $id;
        $this->db->update(
            "UPDATE agrupamentos_componentes
             SET nome = :nome, modo = :modo, aplicar_em = :aplicar_em,
                 divisor = :divisor, materia_rotulo_id = :materia_rotulo_id, ativo = :ativo
             WHERE id = :id",
            $params
        );
        $this->salvarItens($id, $materiaIds);
    }

    public function excluir(int $id): void
    {
        $this->db->delete(
            "DELETE FROM agrupamentos_componentes WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * @param list<int> $materiaIds
     */
    private function salvarItens(int $agrupamentoId, array $materiaIds): void
    {
        $this->db->delete(
            "DELETE FROM agrupamentos_componentes_itens WHERE agrupamento_id = :id",
            ['id' => $agrupamentoId]
        );
        $seen = [];
        foreach ($materiaIds as $mid) {
            $mid = (int) $mid;
            if ($mid <= 0 || isset($seen[$mid])) {
                continue;
            }
            $seen[$mid] = true;
            $this->db->insert(
                "INSERT INTO agrupamentos_componentes_itens (agrupamento_id, materia_id)
                 VALUES (:agrupamento_id, :materia_id)",
                ['agrupamento_id' => $agrupamentoId, 'materia_id' => $mid]
            );
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function paramsCabecalho(array $data): array
    {
        $modo = strtolower(trim((string) ($data['modo'] ?? 'media')));
        $aplicar = strtolower(trim((string) ($data['aplicar_em'] ?? 'boletim')));
        $divisor = isset($data['divisor']) && $data['divisor'] !== '' && $data['divisor'] !== null
            ? (float) $data['divisor']
            : null;
        $rotulo = (int) ($data['materia_rotulo_id'] ?? 0);
        return [
            'nome' => (string) ($data['nome'] ?? ''),
            'modo' => $modo === 'soma' ? 'soma' : 'media',
            'aplicar_em' => $aplicar === 'ambos' ? 'ambos' : 'boletim',
            'divisor' => ($divisor !== null && $divisor > 0) ? $divisor : null,
            'materia_rotulo_id' => $rotulo > 0 ? $rotulo : null,
            'ativo' => !empty($data['ativo']) ? 1 : 0,
        ];
    }
}
