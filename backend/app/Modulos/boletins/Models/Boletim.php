<?php

namespace App\Modulos\Boletins\Models;

use Database;
use Throwable;

/**
 * Cadastro de boletim (oficial / extra), fonte da verdade das matérias e da finalidade.
 */
class Boletim
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
                 WHERE table_schema = DATABASE() AND table_name = 'boletins'
                 LIMIT 1"
            );
            $ok = !empty($row['ok']);
        } catch (Throwable $e) {
            $ok = false;
        }
        if ($ok) {
            $this->ensureColunaRegraAcademica();
        }
        return $ok;
    }

    public function temColunaRegraAcademica(): bool
    {
        if (!$this->tabelasProntas()) {
            return false;
        }
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $col = $this->db->fetch("SHOW COLUMNS FROM boletins LIKE 'regra_academica_id'");
            $ok = !empty($col);
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
        $sql = "SELECT b.* FROM boletins b";
        if ($apenasAtivos) {
            $sql .= " WHERE b.ativo = 1";
        }
        $sql .= " ORDER BY b.ano_letivo DESC, b.finalidade ASC, b.nome ASC";
        $rows = $this->db->fetchAll($sql) ?: [];
        foreach ($rows as &$row) {
            $row = $this->hidratar($row);
        }
        unset($row);
        return $rows;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        if (!$this->tabelasProntas() || $id <= 0) {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM boletins WHERE id = :id", ['id' => $id]);
        return is_array($row) ? $this->hidratar($row) : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByRegraId(int $regraId): ?array
    {
        if (!$this->tabelasProntas() || $regraId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM boletins WHERE regra_id = :regra_id LIMIT 1",
            ['regra_id' => $regraId]
        );
        return is_array($row) ? $this->hidratar($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function criar(array $data): int
    {
        $params = $this->paramsPersistencia($data);
        if ($this->temColunaRegraAcademica()) {
            return (int) $this->db->insert(
                "INSERT INTO boletins
                 (nome, finalidade, ano_letivo, materias_ids, series_ids, turmas_ids, nota_minima_aprovacao,
                  vis_aluno, vis_pais, vis_coordenacao, regra_id, regra_academica_id, ativo)
                 VALUES
                 (:nome, :finalidade, :ano_letivo, :materias_ids, :series_ids, :turmas_ids, :nota_minima_aprovacao,
                  :vis_aluno, :vis_pais, :vis_coordenacao, :regra_id, :regra_academica_id, :ativo)",
                $params
            );
        }
        unset($params['regra_academica_id']);
        return (int) $this->db->insert(
            "INSERT INTO boletins
             (nome, finalidade, ano_letivo, materias_ids, series_ids, turmas_ids, nota_minima_aprovacao,
              vis_aluno, vis_pais, vis_coordenacao, regra_id, ativo)
             VALUES
             (:nome, :finalidade, :ano_letivo, :materias_ids, :series_ids, :turmas_ids, :nota_minima_aprovacao,
              :vis_aluno, :vis_pais, :vis_coordenacao, :regra_id, :ativo)",
            $params
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function atualizar(int $id, array $data): void
    {
        $params = $this->paramsPersistencia($data);
        $params['id'] = $id;
        if ($this->temColunaRegraAcademica()) {
            $this->db->update(
                "UPDATE boletins
                 SET nome = :nome, finalidade = :finalidade, ano_letivo = :ano_letivo,
                     materias_ids = :materias_ids, series_ids = :series_ids, turmas_ids = :turmas_ids,
                     nota_minima_aprovacao = :nota_minima_aprovacao, vis_aluno = :vis_aluno,
                     vis_pais = :vis_pais, vis_coordenacao = :vis_coordenacao,
                     regra_id = :regra_id, regra_academica_id = :regra_academica_id, ativo = :ativo
                 WHERE id = :id",
                $params
            );
            return;
        }
        unset($params['regra_academica_id']);
        $this->db->update(
            "UPDATE boletins
             SET nome = :nome, finalidade = :finalidade, ano_letivo = :ano_letivo,
                 materias_ids = :materias_ids, series_ids = :series_ids, turmas_ids = :turmas_ids,
                 nota_minima_aprovacao = :nota_minima_aprovacao, vis_aluno = :vis_aluno,
                 vis_pais = :vis_pais, vis_coordenacao = :vis_coordenacao, regra_id = :regra_id, ativo = :ativo
             WHERE id = :id",
            $params
        );
    }

    public function definirRegraId(int $id, int $regraId): void
    {
        $this->db->update(
            "UPDATE boletins SET regra_id = :regra_id WHERE id = :id",
            ['id' => $id, 'regra_id' => $regraId]
        );
    }

    public function excluir(int $id): void
    {
        $this->db->delete("DELETE FROM boletins WHERE id = :id", ['id' => $id]);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function hidratar(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['finalidade'] = (($row['finalidade'] ?? 'oficial') === 'complementar') ? 'complementar' : 'oficial';
        $row['ano_letivo'] = isset($row['ano_letivo']) ? (int) $row['ano_letivo'] : null;
        $row['materias_ids'] = $this->decodeIds($row['materias_ids'] ?? null);
        $row['series_ids'] = $this->decodeIds($row['series_ids'] ?? null);
        $row['turmas_ids'] = $this->decodeIds($row['turmas_ids'] ?? null);
        $row['nota_minima_aprovacao'] = isset($row['nota_minima_aprovacao']) && $row['nota_minima_aprovacao'] !== null
            ? (float) $row['nota_minima_aprovacao']
            : null;
        $row['vis_aluno'] = (int) ($row['vis_aluno'] ?? 1);
        $row['vis_pais'] = (int) ($row['vis_pais'] ?? 1);
        $row['vis_coordenacao'] = (int) ($row['vis_coordenacao'] ?? 1);
        $row['regra_id'] = isset($row['regra_id']) ? (int) $row['regra_id'] : null;
        $row['regra_academica_id'] = !empty($row['regra_academica_id']) ? (int) $row['regra_academica_id'] : null;
        $row['ativo'] = (int) ($row['ativo'] ?? 1);
        return $row;
    }

    /**
     * @return list<int>
     */
    public function decodeIds($raw): array
    {
        if (is_array($raw)) {
            $ids = $raw;
        } else {
            $txt = trim((string) $raw);
            if ($txt === '') {
                return [];
            }
            $dec = json_decode($txt, true);
            $ids = is_array($dec) ? $dec : [];
        }
        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[$n] = $n;
            }
        }
        return array_values($out);
    }

    /**
     * @param list<int> $ids
     */
    public function encodeIds(array $ids): ?string
    {
        $clean = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $clean[$n] = $n;
            }
        }
        if ($clean === []) {
            return null;
        }
        return json_encode(array_values($clean), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function paramsPersistencia(array $data): array
    {
        $ano = (int) ($data['ano_letivo'] ?? 0);
        return [
            'nome' => trim((string) ($data['nome'] ?? '')),
            'finalidade' => (($data['finalidade'] ?? 'oficial') === 'complementar') ? 'complementar' : 'oficial',
            'ano_letivo' => ($ano >= 2000 && $ano <= 2100) ? $ano : null,
            'materias_ids' => $this->encodeIds(is_array($data['materias_ids'] ?? null) ? $data['materias_ids'] : []),
            'series_ids' => $this->encodeIds(is_array($data['series_ids'] ?? null) ? $data['series_ids'] : []),
            'turmas_ids' => $this->encodeIds(is_array($data['turmas_ids'] ?? null) ? $data['turmas_ids'] : []),
            'nota_minima_aprovacao' => isset($data['nota_minima_aprovacao']) && $data['nota_minima_aprovacao'] !== null
                ? (float) $data['nota_minima_aprovacao']
                : null,
            'vis_aluno' => !empty($data['vis_aluno']) ? 1 : 0,
            'vis_pais' => !empty($data['vis_pais']) ? 1 : 0,
            'vis_coordenacao' => !empty($data['vis_coordenacao']) ? 1 : 0,
            'regra_id' => !empty($data['regra_id']) ? (int) $data['regra_id'] : null,
            'regra_academica_id' => !empty($data['regra_academica_id']) ? (int) $data['regra_academica_id'] : null,
            'ativo' => !isset($data['ativo']) || !empty($data['ativo']) ? 1 : 0,
        ];
    }

    private function ensureColunaRegraAcademica(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $col = $this->db->fetch("SHOW COLUMNS FROM boletins LIKE 'regra_academica_id'");
            if ($col) {
                return;
            }
            $this->db->query(
                "ALTER TABLE boletins ADD COLUMN regra_academica_id INT UNSIGNED NULL DEFAULT NULL AFTER regra_id"
            );
        } catch (Throwable $e) {
            // migration ainda não rodou / sem permissão ALTER
        }
    }
}
