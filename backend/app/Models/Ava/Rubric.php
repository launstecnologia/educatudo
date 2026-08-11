<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Rubrica de correção (critérios ponderados).
 */
class Rubric
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return list<array<string,mixed>> */
    public function byDiscipline(int $disciplinaId): array
    {
        if ($disciplinaId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT r.*, (SELECT COUNT(*) FROM ava_rubrica_criterios c WHERE c.rubrica_id = r.id) AS total_criterios
             FROM ava_rubricas r WHERE r.disciplina_id = :d ORDER BY r.titulo ASC",
            ['d' => $disciplinaId]
        ) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM ava_rubricas WHERE id = :id", ['id' => $id]);
        return $row ?: null;
    }

    /** Rubrica com os critérios já carregados. @return array<string,mixed>|null */
    public function findWithCriteria(int $id): ?array
    {
        $rubrica = $this->find($id);
        if (!$rubrica) {
            return null;
        }
        $rubrica['criterios'] = $this->criteria($id);
        return $rubrica;
    }

    /** @return list<array<string,mixed>> */
    public function criteria(int $rubricaId): array
    {
        if ($rubricaId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM ava_rubrica_criterios WHERE rubrica_id = :r ORDER BY ordem ASC, id ASC",
            ['r' => $rubricaId]
        ) ?: [];
    }

    public function save(int $disciplinaId, string $titulo, ?string $descricao, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $this->db->update(
                "UPDATE ava_rubricas SET titulo = :t, descricao = :d, updated_at = NOW() WHERE id = :id",
                ['t' => $titulo, 'd' => $descricao, 'id' => $id]
            );
            return $id;
        }
        return (int) $this->db->insert(
            "INSERT INTO ava_rubricas (disciplina_id, titulo, descricao) VALUES (:disc, :t, :d)",
            ['disc' => $disciplinaId ?: null, 't' => $titulo, 'd' => $descricao]
        );
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->db->query("DELETE FROM ava_rubricas WHERE id = :id", ['id' => $id]);
        }
    }

    /**
     * Substitui os critérios da rubrica pelos informados.
     * @param list<array{titulo:string,descricao?:string,peso?:float,pontuacao_max?:float}> $criterios
     */
    public function replaceCriteria(int $rubricaId, array $criterios): void
    {
        if ($rubricaId <= 0) {
            return;
        }
        $this->db->query("DELETE FROM ava_rubrica_criterios WHERE rubrica_id = :r", ['r' => $rubricaId]);
        $ordem = 0;
        foreach ($criterios as $c) {
            $titulo = trim((string) ($c['titulo'] ?? ''));
            if ($titulo === '') {
                continue;
            }
            $this->db->insert(
                "INSERT INTO ava_rubrica_criterios (rubrica_id, titulo, descricao, peso, pontuacao_max, ordem)
                 VALUES (:r, :t, :d, :peso, :pmax, :o)",
                [
                    'r' => $rubricaId,
                    't' => $titulo,
                    'd' => trim((string) ($c['descricao'] ?? '')) ?: null,
                    'peso' => (float) ($c['peso'] ?? 1),
                    'pmax' => (float) ($c['pontuacao_max'] ?? 10),
                    'o' => $ordem++,
                ]
            );
        }
    }
}
