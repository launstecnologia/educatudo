<?php
/**
 * Programação pública da edição (Expo Colag S4).
 */

class ExpoColagProgramacao
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listarPorEdicao(int $edicaoId): array
    {
        return $this->db->fetchAll(
            'SELECT p.*, s.nome AS setor_nome
             FROM expo_colag_programacao p
             LEFT JOIN expo_colag_setores s ON s.id = p.setor_id
             WHERE p.edicao_id = :edicao_id
             ORDER BY p.hora_inicio ASC, p.ordem ASC, p.id ASC',
            ['edicao_id' => $edicaoId]
        ) ?: [];
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM expo_colag_programacao WHERE id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO expo_colag_programacao
                (edicao_id, titulo, descricao, tipo, hora_inicio, hora_fim, local, setor_id, ordem)
             VALUES
                (:edicao_id, :titulo, :descricao, :tipo, :hora_inicio, :hora_fim, :local, :setor_id, :ordem)',
            [
                'edicao_id' => (int) $data['edicao_id'],
                'titulo' => mb_substr(trim((string) $data['titulo']), 0, 255),
                'descricao' => trim((string) ($data['descricao'] ?? '')) ?: null,
                'tipo' => mb_substr(trim((string) ($data['tipo'] ?? 'Geral')), 0, 60) ?: 'Geral',
                'hora_inicio' => $data['hora_inicio'],
                'hora_fim' => $data['hora_fim'] ?? null,
                'local' => trim((string) ($data['local'] ?? '')) ?: null,
                'setor_id' => !empty($data['setor_id']) ? (int) $data['setor_id'] : null,
                'ordem' => max(1, (int) ($data['ordem'] ?? 1)),
            ]
        );
    }

    public function excluir(int $id): void
    {
        $this->db->query('DELETE FROM expo_colag_programacao WHERE id = :id', ['id' => $id]);
    }

    public function listarSetores(int $edicaoId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM expo_colag_setores WHERE edicao_id = :edicao_id ORDER BY ordem ASC, nome ASC',
            ['edicao_id' => $edicaoId]
        ) ?: [];
    }

    public function criarSetor(int $edicaoId, string $nome, ?string $cor = null): int
    {
        $ordemRow = $this->db->fetch(
            'SELECT COALESCE(MAX(ordem), 0) + 1 AS prox FROM expo_colag_setores WHERE edicao_id = :e',
            ['e' => $edicaoId]
        );
        return (int) $this->db->insert(
            'INSERT INTO expo_colag_setores (edicao_id, nome, cor, ordem)
             VALUES (:edicao_id, :nome, :cor, :ordem)',
            [
                'edicao_id' => $edicaoId,
                'nome' => mb_substr(trim($nome), 0, 120),
                'cor' => $cor,
                'ordem' => (int) ($ordemRow['prox'] ?? 1),
            ]
        );
    }
}
