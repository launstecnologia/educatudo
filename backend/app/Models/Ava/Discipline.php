<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Disciplina (dentro de um curso).
 */
class Discipline
{
    private $db;

    public const STATUS = ['rascunho' => 'Rascunho', 'ativo' => 'Ativo', 'arquivado' => 'Arquivado'];

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
            "SELECT d.*, p.nome AS professor_nome, t.nome AS tutor_nome, s.nome AS semestre_nome,
                    (SELECT COUNT(*) FROM ava_modulos m WHERE m.disciplina_id = d.id) AS total_modulos
             FROM ava_disciplinas d
             LEFT JOIN professores p ON p.id = d.professor_id
             LEFT JOIN professores t ON t.id = d.tutor_id
             LEFT JOIN ava_semestres s ON s.id = d.semestre_id
             WHERE d.curso_id = :c
             ORDER BY d.ordem ASC, d.id ASC",
            ['c' => $cursoId]
        ) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT d.*, c.nome AS curso_nome, c.modalidade AS curso_modalidade,
                    p.nome AS professor_nome, t.nome AS tutor_nome
             FROM ava_disciplinas d
             INNER JOIN ava_cursos c ON c.id = d.curso_id
             LEFT JOIN professores p ON p.id = d.professor_id
             LEFT JOIN professores t ON t.id = d.tutor_id
             WHERE d.id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    /** Disciplinas de um professor (titular ou tutor). @return list<array<string,mixed>> */
    public function byTeacher(int $professorId): array
    {
        if ($professorId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT d.*, c.nome AS curso_nome,
                    (SELECT COUNT(*) FROM ava_matriculas_disciplina mm WHERE mm.disciplina_id = d.id AND mm.status = 'ativa') AS total_alunos
             FROM ava_disciplinas d
             INNER JOIN ava_cursos c ON c.id = d.curso_id
             WHERE d.professor_id = :prof OR d.tutor_id = :tutor
             ORDER BY c.nome ASC, d.ordem ASC",
            ['prof' => $professorId, 'tutor' => $professorId]
        ) ?: [];
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, ?int $id = null): int
    {
        $params = [
            'curso_id' => (int) ($data['curso_id'] ?? 0),
            'semestre_id' => (int) ($data['semestre_id'] ?? 0) ?: null,
            'nome' => trim((string) ($data['nome'] ?? '')),
            'codigo' => trim((string) ($data['codigo'] ?? '')) ?: null,
            'professor_id' => (int) ($data['professor_id'] ?? 0) ?: null,
            'tutor_id' => (int) ($data['tutor_id'] ?? 0) ?: null,
            'ordem' => (int) ($data['ordem'] ?? 0),
            'carga_horaria' => (int) ($data['carga_horaria'] ?? 0),
            'horas_ead' => (int) ($data['horas_ead'] ?? 0),
            'horas_presenciais' => (int) ($data['horas_presenciais'] ?? 0),
            'ementa' => trim((string) ($data['ementa'] ?? '')) ?: null,
            'objetivos' => trim((string) ($data['objetivos'] ?? '')) ?: null,
            'competencias' => trim((string) ($data['competencias'] ?? '')) ?: null,
            'materia_id' => (int) ($data['materia_id'] ?? 0) ?: null,
            'turma_id' => (int) ($data['turma_id'] ?? 0) ?: null,
            'status' => isset(self::STATUS[$data['status'] ?? '']) ? $data['status'] : 'ativo',
        ];
        if ($id !== null && $id > 0) {
            $params['id'] = $id;
            $this->db->update(
                "UPDATE ava_disciplinas SET curso_id=:curso_id, semestre_id=:semestre_id, nome=:nome, codigo=:codigo,
                    professor_id=:professor_id, tutor_id=:tutor_id, ordem=:ordem, carga_horaria=:carga_horaria,
                    horas_ead=:horas_ead, horas_presenciais=:horas_presenciais, ementa=:ementa, objetivos=:objetivos,
                    competencias=:competencias, materia_id=:materia_id, turma_id=:turma_id, status=:status, updated_at=NOW()
                 WHERE id=:id",
                $params
            );
            return $id;
        }
        return (int) $this->db->insert(
            "INSERT INTO ava_disciplinas (curso_id, semestre_id, nome, codigo, professor_id, tutor_id, ordem,
                carga_horaria, horas_ead, horas_presenciais, ementa, objetivos, competencias, materia_id, turma_id, status)
             VALUES (:curso_id, :semestre_id, :nome, :codigo, :professor_id, :tutor_id, :ordem, :carga_horaria,
                :horas_ead, :horas_presenciais, :ementa, :objetivos, :competencias, :materia_id, :turma_id, :status)",
            $params
        );
    }

    public function delete(int $id): void
    {
        if ($id > 0) {
            $this->db->query("DELETE FROM ava_disciplinas WHERE id = :id", ['id' => $id]);
        }
    }

    /** Verifica se o professor é titular ou tutor da disciplina. */
    public function isOwnedByTeacher(int $disciplinaId, int $professorId): bool
    {
        if ($disciplinaId <= 0 || $professorId <= 0) {
            return false;
        }
        $row = $this->db->fetch(
            "SELECT id FROM ava_disciplinas WHERE id = :d AND (professor_id = :prof OR tutor_id = :tutor)",
            ['d' => $disciplinaId, 'prof' => $professorId, 'tutor' => $professorId]
        );
        return $row !== false && !empty($row);
    }
}
