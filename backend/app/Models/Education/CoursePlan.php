<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - CoursePlan (Plano de Curso)
 * Plano por série/matéria com conteúdo previsto, carga horária, avaliações e
 * habilidades da BNCC vinculadas (com marcação de "trabalhada").
 */
class CoursePlan
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tableExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'plano_curso'");
            $cache = $row !== false && !empty($row);
        } catch (Throwable $e) {
            $cache = false;
        }
        return $cache;
    }

    /** @return list<array<string,mixed>> */
    public function listar(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT pc.*, m.nome AS materia_nome, s.nome AS serie_nome, al.ano AS ano_letivo,
                    (SELECT COUNT(*) FROM plano_curso_habilidades h WHERE h.plano_curso_id = pc.id) AS total_habilidades,
                    (SELECT COUNT(*) FROM plano_curso_habilidades h WHERE h.plano_curso_id = pc.id AND h.trabalhada = 1) AS habilidades_trabalhadas
             FROM plano_curso pc
             LEFT JOIN materias m ON m.id = pc.materia_id
             LEFT JOIN serie s ON s.id = pc.serie_id
             LEFT JOIN ano_letivo al ON al.id = pc.ano_letivo_id
             ORDER BY al.ano DESC, s.nome ASC, m.nome ASC"
        ) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists()) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT pc.*, m.nome AS materia_nome, s.nome AS serie_nome, al.ano AS ano_letivo
             FROM plano_curso pc
             LEFT JOIN materias m ON m.id = pc.materia_id
             LEFT JOIN serie s ON s.id = pc.serie_id
             LEFT JOIN ano_letivo al ON al.id = pc.ano_letivo_id
             WHERE pc.id = :id LIMIT 1",
            ['id' => $id]
        );
        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function salvar(array $data, ?int $id = null): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $params = [
            'ano_letivo_id' => (int) ($data['ano_letivo_id'] ?? 0) ?: null,
            'serie_id' => (int) ($data['serie_id'] ?? 0) ?: null,
            'materia_id' => (int) ($data['materia_id'] ?? 0),
            'carga_horaria_prevista' => (int) ($data['carga_horaria_prevista'] ?? 0),
            'avaliacoes_previstas' => (int) ($data['avaliacoes_previstas'] ?? 0),
            'conteudo_previsto' => trim((string) ($data['conteudo_previsto'] ?? '')) ?: null,
            'objetivos' => trim((string) ($data['objetivos'] ?? '')) ?: null,
            'metodologia' => trim((string) ($data['metodologia'] ?? '')) ?: null,
            'status' => in_array(($data['status'] ?? ''), ['rascunho', 'aprovado'], true) ? $data['status'] : 'rascunho',
        ];
        if ($id !== null && $id > 0) {
            $params['id'] = $id;
            $this->db->update(
                "UPDATE plano_curso SET ano_letivo_id = :ano_letivo_id, serie_id = :serie_id, materia_id = :materia_id,
                    carga_horaria_prevista = :carga_horaria_prevista, avaliacoes_previstas = :avaliacoes_previstas,
                    conteudo_previsto = :conteudo_previsto, objetivos = :objetivos, metodologia = :metodologia,
                    status = :status, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id",
                $params
            );
            return $id;
        }
        $params['created_by'] = (int) ($data['created_by'] ?? 0) ?: null;
        return (int) $this->db->insert(
            "INSERT INTO plano_curso (ano_letivo_id, serie_id, materia_id, carga_horaria_prevista, avaliacoes_previstas,
                conteudo_previsto, objetivos, metodologia, status, created_by)
             VALUES (:ano_letivo_id, :serie_id, :materia_id, :carga_horaria_prevista, :avaliacoes_previstas,
                :conteudo_previsto, :objetivos, :metodologia, :status, :created_by)",
            $params
        );
    }

    public function excluir(int $id): void
    {
        if ($id <= 0 || !$this->tableExists()) {
            return;
        }
        $this->db->query("DELETE FROM plano_curso WHERE id = :id", ['id' => $id]);
    }

    /** Substitui as habilidades vinculadas, preservando a marcação "trabalhada" das que permanecem. */
    public function definirHabilidades(int $planoId, array $habilidadeIds): void
    {
        if ($planoId <= 0 || !$this->tableExists()) {
            return;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $habilidadeIds), static fn($v) => $v > 0)));
        $atuais = $this->db->fetchAll("SELECT habilidade_id FROM plano_curso_habilidades WHERE plano_curso_id = :id", ['id' => $planoId]) ?: [];
        $atuaisIds = array_map(static fn($r) => (int) $r['habilidade_id'], $atuais);

        $remover = array_diff($atuaisIds, $ids);
        foreach ($remover as $rid) {
            $this->db->query("DELETE FROM plano_curso_habilidades WHERE plano_curso_id = :p AND habilidade_id = :h", ['p' => $planoId, 'h' => $rid]);
        }
        foreach ($ids as $hid) {
            $this->db->query(
                "INSERT IGNORE INTO plano_curso_habilidades (plano_curso_id, habilidade_id) VALUES (:p, :h)",
                ['p' => $planoId, 'h' => $hid]
            );
        }
    }

    /** @return list<int> */
    public function habilidadeIds(int $planoId): array
    {
        if ($planoId <= 0 || !$this->tableExists()) {
            return [];
        }
        $rows = $this->db->fetchAll("SELECT habilidade_id FROM plano_curso_habilidades WHERE plano_curso_id = :id", ['id' => $planoId]) ?: [];
        return array_map(static fn($r) => (int) $r['habilidade_id'], $rows);
    }

    /** @return list<array<string,mixed>> habilidades vinculadas com flag trabalhada */
    public function habilidadesDetalhadas(int $planoId): array
    {
        if ($planoId <= 0 || !$this->tableExists()) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT h.id, h.codigo, h.descricao, h.componente, pch.trabalhada
             FROM plano_curso_habilidades pch
             INNER JOIN bncc_habilidades h ON h.id = pch.habilidade_id
             WHERE pch.plano_curso_id = :id
             ORDER BY h.codigo",
            ['id' => $planoId]
        ) ?: [];
    }

    public function marcarTrabalhada(int $planoId, int $habilidadeId, bool $trabalhada): void
    {
        if ($planoId <= 0 || $habilidadeId <= 0 || !$this->tableExists()) {
            return;
        }
        $this->db->update(
            "UPDATE plano_curso_habilidades SET trabalhada = :t, trabalhada_em = :em
             WHERE plano_curso_id = :p AND habilidade_id = :h",
            ['t' => $trabalhada ? 1 : 0, 'em' => $trabalhada ? date('Y-m-d H:i:s') : null, 'p' => $planoId, 'h' => $habilidadeId]
        );
    }
}
