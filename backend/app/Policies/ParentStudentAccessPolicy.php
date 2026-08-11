<?php
require_once __DIR__ . '/../Core/Database.php';

/** Regra única de autorização responsável -> aluno para toda a API mobile. */
class ParentStudentAccessPolicy
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function canAccess(int $parentId, int $studentId): bool
    {
        if ($parentId <= 0 || $studentId <= 0) {
            return false;
        }

        return (bool) $this->db->fetch(
            "SELECT a.id
             FROM alunos a
             WHERE a.id = :student_id
               AND a.ativo = 1
               AND (
                    a.responsavel_id = :legacy_parent_id
                    OR EXISTS (
                        SELECT 1 FROM alunos_responsaveis ar
                        WHERE ar.aluno_id = a.id
                          AND ar.responsavel_id = :linked_parent_id
                          AND ar.ativo = 1
                    )
               )
             LIMIT 1",
            [
                'student_id' => $studentId,
                'legacy_parent_id' => $parentId,
                'linked_parent_id' => $parentId,
            ]
        );
    }

    public function studentsFor(int $parentId): array
    {
        if ($parentId <= 0) {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT DISTINCT a.id, a.nome, a.foto_url, a.turma_id,
                    t.nome AS class_name, t.serie AS class_grade
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             LEFT JOIN alunos_responsaveis ar
                    ON ar.aluno_id = a.id
                   AND ar.responsavel_id = :linked_parent_id
                   AND ar.ativo = 1
             WHERE a.ativo = 1
               AND (a.responsavel_id = :legacy_parent_id OR ar.responsavel_id IS NOT NULL)
             ORDER BY a.nome ASC",
            ['linked_parent_id' => $parentId, 'legacy_parent_id' => $parentId]
        );
    }
}
