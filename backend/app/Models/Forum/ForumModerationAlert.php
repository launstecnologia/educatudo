<?php
/**
 * Alerta de moderação: conteúdo de pergunta ou resposta bloqueado pela IA.
 */
require_once __DIR__ . '/../../Core/Database.php';

class ForumModerationAlert
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create($tipo, $topicId, $replyId, $authorId, $authorRole, $contentPreview, $motivoIa)
    {
        $preview = mb_substr(trim($contentPreview), 0, 500, 'UTF-8');
        $this->db->query(
            'INSERT INTO forum_moderacao_alertas (tipo, topic_id, reply_id, author_id, author_role, content_preview, motivo_ia) VALUES (:tipo, :tid, :rid, :aid, :role, :preview, :motivo)',
            [
                'tipo' => $tipo === 'reply' ? 'reply' : 'topic',
                'tid' => $topicId ? (int) $topicId : null,
                'rid' => $replyId ? (int) $replyId : null,
                'aid' => (int) $authorId,
                'role' => $authorRole,
                'preview' => $preview,
                'motivo' => $motivoIa,
            ]
        );
        return (int) $this->db->getPdo()->lastInsertId();
    }

    public function listPending()
    {
        return $this->db->fetchAll(
            'SELECT * FROM forum_moderacao_alertas WHERE status = :status ORDER BY created_at DESC',
            ['status' => 'pending']
        );
    }

    public function markAsVisto($id)
    {
        $this->db->query('UPDATE forum_moderacao_alertas SET status = \'visto\' WHERE id = :id', ['id' => (int) $id]);
    }
}
