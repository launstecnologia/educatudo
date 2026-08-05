<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Módulo (unidade dentro da disciplina).
 */
class Module
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
            "SELECT m.*, (SELECT COUNT(*) FROM ava_aulas a WHERE a.modulo_id = m.id) AS total_aulas
             FROM ava_modulos m WHERE m.disciplina_id = :d ORDER BY m.ordem ASC, m.id ASC",
            ['d' => $disciplinaId]
        ) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT m.*, d.curso_id, d.nome AS disciplina_nome FROM ava_modulos m
             INNER JOIN ava_disciplinas d ON d.id = m.disciplina_id WHERE m.id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function save(int $disciplinaId, string $titulo, ?string $descricao, int $ordem = 0, string $status = 'publicado', ?int $id = null): int
    {
        $status = in_array($status, ['rascunho', 'publicado'], true) ? $status : 'publicado';
        if ($id !== null && $id > 0) {
            $this->db->update(
                "UPDATE ava_modulos SET titulo = :t, descricao = :d, ordem = :o, status = :s, updated_at = NOW() WHERE id = :id",
                ['t' => $titulo, 'd' => $descricao, 'o' => $ordem, 's' => $status, 'id' => $id]
            );
            return $id;
        }
        return (int) $this->db->insert(
            "INSERT INTO ava_modulos (disciplina_id, titulo, descricao, ordem, status) VALUES (:disc, :t, :d, :o, :s)",
            ['disc' => $disciplinaId, 't' => $titulo, 'd' => $descricao, 'o' => $ordem, 's' => $status]
        );
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->db->query("DELETE FROM ava_modulos WHERE id = :id", ['id' => $id]);
        }
    }
}
