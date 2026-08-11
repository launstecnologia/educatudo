<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Semestre (período do curso).
 */
class Semester
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return list<array<string,mixed>> */
    public function byCourse(int $cursoId): array
    {
        if ($cursoId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM ava_semestres WHERE curso_id = :id ORDER BY ordem ASC, id ASC",
            ['id' => $cursoId]
        ) ?: [];
    }

    public function save(int $cursoId, string $nome, int $ordem = 0, ?string $inicio = null, ?string $fim = null, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $this->db->update(
                "UPDATE ava_semestres SET nome = :nome, ordem = :ordem, data_inicio = :i, data_fim = :f WHERE id = :id",
                ['nome' => $nome, 'ordem' => $ordem, 'i' => $inicio, 'f' => $fim, 'id' => $id]
            );
            return $id;
        }
        return (int) $this->db->insert(
            "INSERT INTO ava_semestres (curso_id, nome, ordem, data_inicio, data_fim) VALUES (:c, :nome, :ordem, :i, :f)",
            ['c' => $cursoId, 'nome' => $nome, 'ordem' => $ordem, 'i' => $inicio, 'f' => $fim]
        );
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->db->query("DELETE FROM ava_semestres WHERE id = :id", ['id' => $id]);
        }
    }
}
