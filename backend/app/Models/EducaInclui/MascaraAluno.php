<?php

if (!class_exists('MascaraAluno')) {
class MascaraAluno
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT sa.*, a.nome AS aluno_nome
             FROM mascaras_alunos sa
             LEFT JOIN alunos a ON a.id = sa.aluno_id
             WHERE sa.id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    /**
     * Máscara mais relevante de um aluno (prioriza ativa).
     */
    public function getByAluno(int $alunoId): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM mascaras_alunos
             WHERE aluno_id = :aluno_id
             ORDER BY (status = 'ativa') DESC, updated_at DESC, id DESC
             LIMIT 1",
            ['aluno_id' => $alunoId]
        );
        return $row ?: null;
    }

    /**
     * Máscara ATIVA e vigente (dentro do período) de um aluno.
     */
    public function getActiveByAluno(int $alunoId): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM mascaras_alunos
             WHERE aluno_id = :aluno_id
               AND status = 'ativa'
               AND (data_inicio IS NULL OR data_inicio <= CURDATE())
               AND (data_fim IS NULL OR data_fim >= CURDATE())
             ORDER BY updated_at DESC, id DESC
             LIMIT 1",
            ['aluno_id' => $alunoId]
        );
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listForAdmin(string $search = '', int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $params = [];
        $where = '';
        if (trim($search) !== '') {
            $where = "WHERE a.nome LIKE :search";
            $params['search'] = '%' . trim($search) . '%';
        }
        return $this->db->fetchAll(
            "SELECT sa.*, a.nome AS aluno_nome,
                    (SELECT COUNT(*) FROM regras_mascara r WHERE r.mascara_id = sa.id) AS total_regras
             FROM mascaras_alunos sa
             LEFT JOIN alunos a ON a.id = sa.aluno_id
             {$where}
             ORDER BY (sa.status = 'ativa') DESC, sa.updated_at DESC, sa.id DESC
             LIMIT " . (int) $limit,
            $params
        );
    }

    public function create(array $data, int $criadoPor): int
    {
        $id = $this->db->insert(
            "INSERT INTO mascaras_alunos
             (aluno_id, status, tipo_adaptacao, data_inicio, data_fim, base_legal, ref_consentimento, observacoes, criado_por, created_at)
             VALUES (:aluno_id, :status, :tipo_adaptacao, :data_inicio, :data_fim, :base_legal, :ref_consentimento, :observacoes, :criado_por, NOW())",
            [
                'aluno_id' => (int) $data['aluno_id'],
                'status' => $data['status'] ?? 'rascunho',
                'tipo_adaptacao' => $data['tipo_adaptacao'] ?? 'acesso',
                'data_inicio' => $data['data_inicio'] ?: null,
                'data_fim' => $data['data_fim'] ?: null,
                'base_legal' => $data['base_legal'] ?: null,
                'ref_consentimento' => $data['ref_consentimento'] ?: null,
                'observacoes' => $data['observacoes'] ?: null,
                'criado_por' => $criadoPor ?: null,
            ]
        );
        return (int) $id;
    }

    public function update(int $id, array $data): void
    {
        $this->db->update(
            "UPDATE mascaras_alunos SET
                tipo_adaptacao = :tipo_adaptacao,
                data_inicio = :data_inicio,
                data_fim = :data_fim,
                base_legal = :base_legal,
                ref_consentimento = :ref_consentimento,
                observacoes = :observacoes,
                updated_at = NOW()
             WHERE id = :id",
            [
                'id' => $id,
                'tipo_adaptacao' => $data['tipo_adaptacao'] ?? 'acesso',
                'data_inicio' => $data['data_inicio'] ?: null,
                'data_fim' => $data['data_fim'] ?: null,
                'base_legal' => $data['base_legal'] ?: null,
                'ref_consentimento' => $data['ref_consentimento'] ?: null,
                'observacoes' => $data['observacoes'] ?: null,
            ]
        );
    }

    public function setStatus(int $id, string $status, ?int $aprovadoPor = null): void
    {
        if ($status === 'ativa') {
            $this->db->update(
                "UPDATE mascaras_alunos
                 SET status = :status, aprovado_por = :aprovado_por, aprovado_em = NOW(), updated_at = NOW()
                 WHERE id = :id",
                ['id' => $id, 'status' => $status, 'aprovado_por' => $aprovadoPor ?: null]
            );
            return;
        }
        $this->db->update(
            "UPDATE mascaras_alunos SET status = :status, updated_at = NOW() WHERE id = :id",
            ['id' => $id, 'status' => $status]
        );
    }
}
}
