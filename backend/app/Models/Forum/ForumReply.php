<?php
/**
 * Forum reply (answer to a topic).
 */
require_once __DIR__ . '/../../Core/Database.php';

class ForumReply
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create($topicId, $content, $authorId, $authorRole)
    {
        $this->db->query(
            'INSERT INTO forum_respostas (topic_id, content, author_id, author_role) VALUES (:tid, :content, :aid, :role)',
            [
                'tid' => (int) $topicId,
                'content' => $content,
                'aid' => (int) $authorId,
                'role' => $authorRole,
            ]
        );
        return (int) $this->db->getPdo()->lastInsertId();
    }

    public function getById($id)
    {
        return $this->db->fetch('SELECT * FROM forum_respostas WHERE id = :id LIMIT 1', ['id' => (int) $id]);
    }

    public function listByTopicId($topicId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM forum_respostas WHERE topic_id = :tid ORDER BY is_best_answer DESC, created_at ASC',
            ['tid' => (int) $topicId]
        );
    }

    public function setBestAnswer($replyId, $topicId)
    {
        $this->db->query('UPDATE forum_respostas SET is_best_answer = 0 WHERE topic_id = :tid', ['tid' => (int) $topicId]);
        $this->db->query('UPDATE forum_respostas SET is_best_answer = 1 WHERE id = :id AND topic_id = :tid', ['id' => (int) $replyId, 'tid' => (int) $topicId]);
    }

    public function delete($id)
    {
        $this->db->query('DELETE FROM forum_respostas WHERE id = :id', ['id' => (int) $id]);
    }
}
