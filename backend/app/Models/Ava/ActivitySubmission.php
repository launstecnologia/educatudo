<?php

require_once __DIR__ . '/../../Core/Database.php';

/**
 * EducaTudo - AVA: Entrega de atividade pelo aluno (+ arquivos).
 */
class ActivitySubmission
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array<string,mixed>|null */
    public function find(int $atividadeId, int $alunoId): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM ava_atividade_entregas WHERE atividade_id = :a AND aluno_id = :al",
            ['a' => $atividadeId, 'al' => $alunoId]
        );
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT e.*, a.titulo AS atividade_titulo, a.nota_maxima, a.rubrica_id, a.disciplina_id,
                    al.nome AS aluno_nome, al.email AS aluno_email
             FROM ava_atividade_entregas e
             INNER JOIN ava_atividades a ON a.id = e.atividade_id
             LEFT JOIN alunos al ON al.id = e.aluno_id
             WHERE e.id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    /** Cria (se necessário) e retorna o id da entrega do aluno. */
    public function ensure(int $atividadeId, int $alunoId): int
    {
        $existing = $this->find($atividadeId, $alunoId);
        if ($existing) {
            return (int) $existing['id'];
        }
        return (int) $this->db->insert(
            "INSERT INTO ava_atividade_entregas (atividade_id, aluno_id, status) VALUES (:a, :al, 'rascunho')",
            ['a' => $atividadeId, 'al' => $alunoId]
        );
    }

    /** Registra/atualiza o envio do aluno (texto/link + status enviada). */
    public function submit(int $atividadeId, int $alunoId, ?string $texto, ?string $link, bool $atrasada): int
    {
        $id = $this->ensure($atividadeId, $alunoId);
        $this->db->update(
            "UPDATE ava_atividade_entregas
             SET texto = :t, link = :l, status = 'enviada', atrasada = :atr, enviada_em = NOW(), updated_at = NOW()
             WHERE id = :id",
            ['t' => $texto, 'l' => $link, 'atr' => $atrasada ? 1 : 0, 'id' => $id]
        );
        return $id;
    }

    /** Lança nota/feedback do professor. @param array<string,mixed>|null $rubricaResultado */
    public function grade(int $entregaId, float $nota, ?string $feedback, ?array $rubricaResultado, int $professorId, bool $reenviar = false): void
    {
        $status = $reenviar ? 'reenviar' : 'avaliada';
        $this->db->update(
            "UPDATE ava_atividade_entregas
             SET nota = :n, feedback = :f, rubrica_resultado_json = :r, status = :s,
                 avaliada_em = NOW(), avaliada_por = :p, updated_at = NOW()
             WHERE id = :id",
            [
                'n' => $nota,
                'f' => $feedback,
                'r' => $rubricaResultado !== null ? json_encode($rubricaResultado, JSON_UNESCAPED_UNICODE) : null,
                's' => $status,
                'p' => $professorId ?: null,
                'id' => $entregaId,
            ]
        );
    }

    /** Entregas de uma atividade (todos os alunos que enviaram). @return list<array<string,mixed>> */
    public function byActivity(int $atividadeId): array
    {
        if ($atividadeId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT e.*, al.nome AS aluno_nome, al.email AS aluno_email,
                    (SELECT COUNT(*) FROM ava_atividade_entrega_arquivos f WHERE f.entrega_id = e.id) AS total_arquivos
             FROM ava_atividade_entregas e
             LEFT JOIN alunos al ON al.id = e.aluno_id
             WHERE e.atividade_id = :a AND e.status <> 'rascunho'
             ORDER BY al.nome ASC",
            ['a' => $atividadeId]
        ) ?: [];
    }

    /** Mapa atividade_id => entrega do aluno (para listagem). @return array<int,array<string,mixed>> */
    public function mapByStudentForActivities(int $alunoId, array $atividadeIds): array
    {
        $atividadeIds = array_values(array_filter(array_map('intval', $atividadeIds)));
        if ($alunoId <= 0 || empty($atividadeIds)) {
            return [];
        }
        $place = implode(',', array_fill(0, count($atividadeIds), '?'));
        $params = $atividadeIds;
        $params[] = $alunoId;
        $rows = $this->db->fetchAll(
            "SELECT * FROM ava_atividade_entregas WHERE atividade_id IN ($place) AND aluno_id = ?",
            $params
        ) ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['atividade_id']] = $r;
        }
        return $map;
    }

    // ---- Arquivos ---------------------------------------------------------

    /** @return list<array<string,mixed>> */
    public function files(int $entregaId): array
    {
        if ($entregaId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM ava_atividade_entrega_arquivos WHERE entrega_id = :e ORDER BY id ASC",
            ['e' => $entregaId]
        ) ?: [];
    }

    /** @param array<string,mixed> $data */
    public function addFile(int $entregaId, array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO ava_atividade_entrega_arquivos (entrega_id, arquivo_key, nome, mime, tamanho)
             VALUES (:e, :key, :nome, :mime, :tam)",
            [
                'e' => $entregaId,
                'key' => $data['arquivo_key'] ?? '',
                'nome' => $data['nome'] ?? null,
                'mime' => $data['mime'] ?? null,
                'tam' => isset($data['tamanho']) ? (int) $data['tamanho'] : null,
            ]
        );
    }

    /** @return array<string,mixed>|null */
    public function findFile(int $fileId): ?array
    {
        if ($fileId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT f.*, e.aluno_id, e.atividade_id, a.disciplina_id
             FROM ava_atividade_entrega_arquivos f
             INNER JOIN ava_atividade_entregas e ON e.id = f.entrega_id
             INNER JOIN ava_atividades a ON a.id = e.atividade_id
             WHERE f.id = :id",
            ['id' => $fileId]
        );
        return $row ?: null;
    }

    public function deleteFile(int $fileId): void
    {
        if ($fileId > 0) {
            $this->db->query("DELETE FROM ava_atividade_entrega_arquivos WHERE id = :id", ['id' => $fileId]);
        }
    }
}
