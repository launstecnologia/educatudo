<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Certificado de conclusão emitido (para validação por código).
 */
class Certificate
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM ava_certificados WHERE id = :id", ['id' => $id]);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findByCode(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM ava_certificados WHERE codigo = :c", ['c' => $codigo]);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findForStudentDiscipline(int $alunoId, int $disciplinaId): ?array
    {
        if ($alunoId <= 0 || $disciplinaId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM ava_certificados WHERE aluno_id = :a AND disciplina_id = :d",
            ['a' => $alunoId, 'd' => $disciplinaId]
        );
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function listForStudent(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT c.*, d.nome AS disciplina_nome
             FROM ava_certificados c
             LEFT JOIN ava_disciplinas d ON d.id = c.disciplina_id
             WHERE c.aluno_id = :a
             ORDER BY c.emitido_em DESC",
            ['a' => $alunoId]
        ) ?: [];
    }

    public function codeExists(string $codigo): bool
    {
        $row = $this->db->fetch("SELECT id FROM ava_certificados WHERE codigo = :c", ['c' => $codigo]);
        return !empty($row);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO ava_certificados (aluno_id, disciplina_id, curso_id, tipo, codigo, aluno_nome, titulo, carga_horaria, nota_final, emitido_em)
             VALUES (:aluno_id, :disciplina_id, :curso_id, :tipo, :codigo, :aluno_nome, :titulo, :carga_horaria, :nota_final, NOW())",
            [
                'aluno_id' => (int) ($data['aluno_id'] ?? 0),
                'disciplina_id' => (int) ($data['disciplina_id'] ?? 0) ?: null,
                'curso_id' => (int) ($data['curso_id'] ?? 0) ?: null,
                'tipo' => in_array($data['tipo'] ?? '', ['disciplina', 'curso'], true) ? $data['tipo'] : 'disciplina',
                'codigo' => (string) ($data['codigo'] ?? ''),
                'aluno_nome' => isset($data['aluno_nome']) ? substr((string) $data['aluno_nome'], 0, 255) : null,
                'titulo' => isset($data['titulo']) ? substr((string) $data['titulo'], 0, 255) : null,
                'carga_horaria' => (int) ($data['carga_horaria'] ?? 0),
                'nota_final' => isset($data['nota_final']) && $data['nota_final'] !== null ? (float) $data['nota_final'] : null,
            ]
        );
    }
}
