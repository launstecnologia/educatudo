<?php

if (!class_exists('VersaoAdaptada')) {
/**
 * Versão adaptada de uma prova para um aluno.
 *
 * Na Fase 1 (adaptações de acesso) a aplicação é feita direto da máscara, sem
 * clonar a prova; este model fica pronto para a Fase 2/3 (adaptações
 * significativas, que clonam a prova e exigem aprovação reforçada).
 */
class VersaoAdaptada
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getApprovedFor(int $provaId, int $alunoId): ?array
    {
        if ($provaId <= 0 || $alunoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM versoes_adaptadas
             WHERE prova_id = :prova_id
               AND aluno_id = :aluno_id
               AND status_aprovacao = 'aprovada'
             ORDER BY id DESC
             LIMIT 1",
            ['prova_id' => $provaId, 'aluno_id' => $alunoId]
        );
        return $row ?: null;
    }

    /** Última versão (qualquer status) para o par prova/aluno. */
    public function getAnyFor(int $provaId, int $alunoId): ?array
    {
        if ($provaId <= 0 || $alunoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM versoes_adaptadas
             WHERE prova_id = :prova_id AND aluno_id = :aluno_id
             ORDER BY id DESC LIMIT 1",
            ['prova_id' => $provaId, 'aluno_id' => $alunoId]
        );
        return $row ?: null;
    }

    public function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM versoes_adaptadas WHERE id = :id", ['id' => $id]);
        return $row ?: null;
    }

    /** Identifica se uma prova é uma versão adaptada (clone) e de quem. */
    public function getByAdaptedProvaId(int $adaptedProvaId): ?array
    {
        if ($adaptedProvaId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM versoes_adaptadas WHERE adapted_prova_id = :pid ORDER BY id DESC LIMIT 1",
            ['pid' => $adaptedProvaId]
        );
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function listPending(): array
    {
        return $this->db->fetchAll(
            "SELECT v.*, a.nome AS aluno_nome, p.titulo AS prova_titulo
             FROM versoes_adaptadas v
             LEFT JOIN alunos a ON a.id = v.aluno_id
             LEFT JOIN provas p ON p.id = v.prova_id
             WHERE v.status_aprovacao IN ('pendente','aprovada_professor','aprovada_aee')
             ORDER BY v.created_at ASC"
        );
    }

    /**
     * Versões já aprovadas (entregues ao aluno). Continuam listadas para
     * histórico/consulta, mesmo depois de aprovadas.
     *
     * @return list<array<string,mixed>>
     */
    public function listApproved(int $limit = 200): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT v.*, a.nome AS aluno_nome, p.titulo AS prova_titulo
             FROM versoes_adaptadas v
             LEFT JOIN alunos a ON a.id = v.aluno_id
             LEFT JOIN provas p ON p.id = v.prova_id
             WHERE v.status_aprovacao = 'aprovada'
             ORDER BY v.updated_at DESC, v.id DESC
             LIMIT {$limit}"
        );
    }

    public function approve(int $id, int $userId): void
    {
        $this->db->update(
            "UPDATE versoes_adaptadas
             SET status_aprovacao = 'aprovada', aprovado_por_aee = :uid, updated_at = NOW()
             WHERE id = :id",
            ['uid' => $userId, 'id' => $id]
        );
    }

    public function reject(int $id, int $userId): void
    {
        $this->db->update(
            "UPDATE versoes_adaptadas
             SET status_aprovacao = 'rejeitada', aprovado_por_aee = :uid, updated_at = NOW()
             WHERE id = :id",
            ['uid' => $userId, 'id' => $id]
        );
    }

    public function markDrift(int $id): void
    {
        $this->db->update(
            "UPDATE versoes_adaptadas
             SET status_aprovacao = 'invalidada_drift', updated_at = NOW()
             WHERE id = :id",
            ['id' => $id]
        );
    }

    public function create(array $data): int
    {
        $id = $this->db->insert(
            "INSERT INTO versoes_adaptadas
             (prova_id, aluno_id, mascara_id, tipo_versao, hash_prova_origem,
              adapted_prova_id, regras_snapshot_json, status_aprovacao, gerado_de_versao_id, created_at)
             VALUES (:prova_id, :aluno_id, :mascara_id, :tipo_versao, :hash_prova_origem,
              :adapted_prova_id, :regras_snapshot_json, :status_aprovacao, :gerado_de_versao_id, NOW())",
            [
                'prova_id' => (int) $data['prova_id'],
                'aluno_id' => (int) $data['aluno_id'],
                'mascara_id' => (int) $data['mascara_id'],
                'tipo_versao' => $data['tipo_versao'] ?? 'acesso',
                'hash_prova_origem' => (string) $data['hash_prova_origem'],
                'adapted_prova_id' => $data['adapted_prova_id'] ?? null,
                'regras_snapshot_json' => json_encode($data['rules'] ?? [], JSON_UNESCAPED_UNICODE),
                'status_aprovacao' => $data['status_aprovacao'] ?? 'pendente',
                'gerado_de_versao_id' => $data['gerado_de_versao_id'] ?? null,
            ]
        );
        return (int) $id;
    }
}
}
