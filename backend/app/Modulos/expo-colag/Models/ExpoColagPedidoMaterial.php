<?php
/**
 * Pedidos de material do aluno ao professor (Expo Colag).
 */

class ExpoColagPedidoMaterial
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM expo_colag_pedidos_materiais WHERE id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    /** @return list<array> */
    public function listarPorAlunoProjeto(int $alunoId, int $projetoId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM expo_colag_pedidos_materiais
             WHERE aluno_id = :aluno_id AND projeto_id = :projeto_id
             ORDER BY id DESC',
            ['aluno_id' => $alunoId, 'projeto_id' => $projetoId]
        ) ?: [];
    }

    /** @return list<array> */
    public function listarPorProjeto(int $projetoId): array
    {
        return $this->db->fetchAll(
            'SELECT p.*, a.nome AS aluno_nome
             FROM expo_colag_pedidos_materiais p
             LEFT JOIN alunos a ON a.id = p.aluno_id
             WHERE p.projeto_id = :projeto_id
             ORDER BY FIELD(p.status, \'Pendente\', \'Aprovado\', \'Recusado\'), p.id DESC',
            ['projeto_id' => $projetoId]
        ) ?: [];
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO expo_colag_pedidos_materiais
                (projeto_id, aluno_id, inscricao_id, titulo, quantidade, observacao, status)
             VALUES
                (:projeto_id, :aluno_id, :inscricao_id, :titulo, :quantidade, :observacao, \'Pendente\')',
            [
                'projeto_id' => (int) $data['projeto_id'],
                'aluno_id' => (int) $data['aluno_id'],
                'inscricao_id' => (int) $data['inscricao_id'],
                'titulo' => mb_substr(trim((string) $data['titulo']), 0, 255),
                'quantidade' => mb_substr(trim((string) ($data['quantidade'] ?? '')), 0, 60) ?: null,
                'observacao' => mb_substr(trim((string) ($data['observacao'] ?? '')), 0, 500) ?: null,
            ]
        );
    }

    public function decidir(int $id, string $status, int $professorId, ?string $resposta): bool
    {
        $stmt = $this->db->query(
            'UPDATE expo_colag_pedidos_materiais
             SET status = :status,
                 resposta_professor = :resposta,
                 decidido_por = :decidido_por,
                 decidido_em = NOW()
             WHERE id = :id AND status = \'Pendente\'',
            [
                'id' => $id,
                'status' => $status,
                'resposta' => $resposta !== null && $resposta !== '' ? mb_substr($resposta, 0, 500) : null,
                'decidido_por' => $professorId,
            ]
        );
        return $stmt && $stmt->rowCount() > 0;
    }
}
