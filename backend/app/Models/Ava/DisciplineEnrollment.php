<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Matrícula do aluno em disciplina.
 */
class DisciplineEnrollment
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array<string,mixed>|null */
    public function find(int $alunoId, int $disciplinaId): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM ava_matriculas_disciplina WHERE aluno_id = :a AND disciplina_id = :d",
            ['a' => $alunoId, 'd' => $disciplinaId]
        );
        return $row ?: null;
    }

    public function isEnrolled(int $alunoId, int $disciplinaId): bool
    {
        $row = $this->find($alunoId, $disciplinaId);
        return $row !== null && in_array($row['status'] ?? '', ['ativa', 'concluida'], true);
    }

    public function enroll(int $alunoId, int $disciplinaId, string $origem = 'manual'): int
    {
        $origem = in_array($origem, ['erp', 'manual'], true) ? $origem : 'manual';
        $existing = $this->find($alunoId, $disciplinaId);
        if ($existing) {
            if (($existing['status'] ?? '') === 'cancelada') {
                $this->db->update(
                    "UPDATE ava_matriculas_disciplina SET status = 'ativa' WHERE id = :id",
                    ['id' => $existing['id']]
                );
            }
            return (int) $existing['id'];
        }
        return (int) $this->db->insert(
            "INSERT INTO ava_matriculas_disciplina (aluno_id, disciplina_id, origem, status)
             VALUES (:a, :d, :o, 'ativa')",
            ['a' => $alunoId, 'd' => $disciplinaId, 'o' => $origem]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['ativa', 'concluida', 'trancada', 'cancelada'], true)) {
            return;
        }
        $concluida = $status === 'concluida' ? 'NOW()' : 'NULL';
        $this->db->update(
            "UPDATE ava_matriculas_disciplina SET status = :s, concluida_em = " . $concluida . " WHERE id = :id",
            ['s' => $status, 'id' => $id]
        );
    }

    public function updateProgress(int $alunoId, int $disciplinaId, float $pct): void
    {
        $pct = max(0, min(100, $pct));
        $this->db->update(
            "UPDATE ava_matriculas_disciplina SET progresso_pct = :p WHERE aluno_id = :a AND disciplina_id = :d",
            ['p' => $pct, 'a' => $alunoId, 'd' => $disciplinaId]
        );
    }

    /** Atualiza a nota final da matrícula (oriunda da avaliação do AVA). */
    public function setNotaFinal(int $alunoId, int $disciplinaId, ?float $nota): void
    {
        $this->db->update(
            "UPDATE ava_matriculas_disciplina SET nota_final = :n WHERE aluno_id = :a AND disciplina_id = :d",
            ['n' => $nota, 'a' => $alunoId, 'd' => $disciplinaId]
        );
    }

    /** Disciplinas em que o aluno está matriculado (com dados do curso). @return list<array<string,mixed>> */
    public function byStudent(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT mm.*, d.nome AS disciplina_nome, d.codigo AS disciplina_codigo, d.carga_horaria,
                    c.id AS curso_id, c.nome AS curso_nome, c.modalidade, c.imagem_key AS curso_imagem,
                    p.nome AS professor_nome
             FROM ava_matriculas_disciplina mm
             INNER JOIN ava_disciplinas d ON d.id = mm.disciplina_id
             INNER JOIN ava_cursos c ON c.id = d.curso_id
             LEFT JOIN professores p ON p.id = d.professor_id
             WHERE mm.aluno_id = :a AND mm.status IN ('ativa','concluida')
             ORDER BY c.nome ASC, d.ordem ASC",
            ['a' => $alunoId]
        ) ?: [];
    }

    /** Alunos matriculados em uma disciplina. @return list<array<string,mixed>> */
    public function byDiscipline(int $disciplinaId): array
    {
        if ($disciplinaId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT mm.*, a.nome AS aluno_nome, a.email AS aluno_email
             FROM ava_matriculas_disciplina mm
             INNER JOIN alunos a ON a.id = mm.aluno_id
             WHERE mm.disciplina_id = :d AND mm.status IN ('ativa','concluida')
             ORDER BY a.nome ASC",
            ['d' => $disciplinaId]
        ) ?: [];
    }

    public function countByDiscipline(int $disciplinaId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM ava_matriculas_disciplina WHERE disciplina_id = :d AND status IN ('ativa','concluida')",
            ['d' => $disciplinaId]
        );
        return (int) ($row['total'] ?? 0);
    }
}
