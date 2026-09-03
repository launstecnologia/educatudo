<?php
/**
 * Mensagens do grupo do projeto (Expo Colag).
 */

class ExpoColagMensagem
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return list<array> */
    public function listarPorProjeto(int $projetoId, int $limite = 100): array
    {
        $rows = $this->db->fetchAll(
            "SELECT m.*,
                    CASE
                        WHEN m.autor_tipo = 'professor' THEN pr.nome
                        ELSE al.nome
                    END AS autor_nome
             FROM expo_colag_mensagens m
             LEFT JOIN professores pr ON m.autor_tipo = 'professor' AND pr.id = m.autor_id
             LEFT JOIN alunos al ON m.autor_tipo = 'aluno' AND al.id = m.autor_id
             WHERE m.projeto_id = :projeto_id
             ORDER BY m.id ASC",
            ['projeto_id' => $projetoId]
        ) ?: [];
        $limite = max(1, min(200, $limite));
        if (count($rows) > $limite) {
            $rows = array_slice($rows, -$limite);
        }
        return $rows;
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO expo_colag_mensagens (projeto_id, autor_tipo, autor_id, mensagem)
             VALUES (:projeto_id, :autor_tipo, :autor_id, :mensagem)',
            [
                'projeto_id' => (int) $data['projeto_id'],
                'autor_tipo' => $data['autor_tipo'] === 'aluno' ? 'aluno' : 'professor',
                'autor_id' => (int) $data['autor_id'],
                'mensagem' => mb_substr(trim((string) $data['mensagem']), 0, 2000),
            ]
        );
    }
}
