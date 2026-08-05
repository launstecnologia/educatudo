<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Avaliação da disciplina (vínculo com uma prova existente).
 *
 * Liga uma `prova` (módulo Provas) a uma `ava_disciplinas`, liberando-a para o
 * aluno apenas quando o progresso na disciplina atinge `requisito_progresso_pct`.
 */
class DisciplineEvaluation
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tableExists(): bool
    {
        try {
            $this->db->fetch("SELECT 1 FROM ava_disciplina_avaliacoes LIMIT 1");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->db->fetch("SELECT * FROM ava_disciplina_avaliacoes WHERE id = :id", ['id' => $id]);
        return $row ?: null;
    }

    /**
     * Avaliações de uma disciplina, com dados da prova (título/valor).
     * @return list<array<string,mixed>>
     */
    public function byDiscipline(int $disciplinaId): array
    {
        if ($disciplinaId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT av.*, p.titulo AS prova_titulo, p.valor_total AS prova_valor,
                    p.liberada AS prova_liberada, p.ativo AS prova_ativo
             FROM ava_disciplina_avaliacoes av
             LEFT JOIN provas p ON p.id = av.prova_id
             WHERE av.disciplina_id = :d
             ORDER BY av.ordem ASC, av.id ASC",
            ['d' => $disciplinaId]
        ) ?: [];
    }

    /** Disciplinas/avaliações que referenciam uma prova (para o gate da prova). @return list<array<string,mixed>> */
    public function byProva(int $provaId): array
    {
        if ($provaId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM ava_disciplina_avaliacoes WHERE prova_id = :p",
            ['p' => $provaId]
        ) ?: [];
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO ava_disciplina_avaliacoes
                (disciplina_id, prova_id, titulo, requisito_progresso_pct, obrigatoria, peso, ordem)
             VALUES (:disciplina_id, :prova_id, :titulo, :req, :obrig, :peso, :ordem)",
            [
                'disciplina_id' => (int) ($data['disciplina_id'] ?? 0),
                'prova_id' => (int) ($data['prova_id'] ?? 0),
                'titulo' => ($data['titulo'] ?? null) ?: null,
                'req' => max(0, min(100, (float) ($data['requisito_progresso_pct'] ?? 80))),
                'obrig' => !empty($data['obrigatoria']) ? 1 : 0,
                'peso' => max(0, (float) ($data['peso'] ?? 1)),
                'ordem' => (int) ($data['ordem'] ?? 0),
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->update("DELETE FROM ava_disciplina_avaliacoes WHERE id = :id", ['id' => $id]);
    }
}
