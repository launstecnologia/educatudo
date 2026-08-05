<?php
/**
 * Forum attachment (image/file on topic or reply).
 */
require_once __DIR__ . '/../../Core/Database.php';

class ForumAttachment
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function add($topicId, $replyId, $fileName, $filePath, $mimeType = 'application/octet-stream', $fileSize = null)
    {
        $this->db->query(
            'INSERT INTO forum_anexos (topic_id, reply_id, file_name, file_path, mime_type, file_size) VALUES (:tid, :rid, :fname, :fpath, :mime, :fsize)',
            [
                'tid' => $topicId ? (int) $topicId : null,
                'rid' => $replyId ? (int) $replyId : null,
                'fname' => $fileName,
                'fpath' => $filePath,
                'mime' => $mimeType,
                'fsize' => $fileSize ? (int) $fileSize : null,
            ]
        );
        return (int) $this->db->getPdo()->lastInsertId();
    }

    public function listByTopicId($topicId)
    {
        return $this->db->fetchAll('SELECT * FROM forum_anexos WHERE topic_id = :tid ORDER BY id', ['tid' => (int) $topicId]);
    }

    public function listByReplyId($replyId)
    {
        return $this->db->fetchAll('SELECT * FROM forum_anexos WHERE reply_id = :rid ORDER BY id', ['rid' => (int) $replyId]);
    }

    /**
     * Lista anexos de várias respostas de uma vez (evita N+1 na tela do tópico).
     * Retorna [ reply_id => [ att1, att2, ... ], ... ]
     */
    public function listByReplyIds(array $replyIds)
    {
        if (empty($replyIds)) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $replyIds)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll("SELECT * FROM forum_anexos WHERE reply_id IN ($placeholders) ORDER BY reply_id, id", $ids);
        $byReply = [];
        foreach ($rows as $r) {
            $rid = (int) $r['reply_id'];
            if (!isset($byReply[$rid])) {
                $byReply[$rid] = [];
            }
            $byReply[$rid][] = $r;
        }
        return $byReply;
    }
}
