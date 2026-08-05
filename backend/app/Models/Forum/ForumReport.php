<?php
/**
 * Forum report (moderation - report topic or reply).
 */
require_once __DIR__ . '/../../Core/Database.php';

class ForumReport
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create($topicId, $replyId, $reporterId, $reporterRole, $reason)
    {
        $this->db->query(
            'INSERT INTO forum_denuncias (topic_id, reply_id, reporter_id, reporter_role, reason) VALUES (:tid, :rid, :uid, :role, :reason)',
            [
                'tid' => $topicId ? (int) $topicId : null,
                'rid' => $replyId ? (int) $replyId : null,
                'uid' => (int) $reporterId,
                'role' => $reporterRole,
                'reason' => $reason,
            ]
        );
        return (int) $this->db->getPdo()->lastInsertId();
    }

    public function getById($id)
    {
        return $this->db->fetch('SELECT * FROM forum_denuncias WHERE id = :id LIMIT 1', ['id' => (int) $id]);
    }

    public function listPending($limit = 50)
    {
        return $this->db->fetchAll('SELECT * FROM forum_denuncias WHERE status = "pending" ORDER BY created_at DESC LIMIT ' . (int) $limit);
    }

    public function updateStatus($id, $status)
    {
        $this->db->query('UPDATE forum_denuncias SET status = :status WHERE id = :id', ['status' => $status, 'id' => (int) $id]);
    }
}
