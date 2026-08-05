<?php
/**
 * Forum vote (upvote/downvote on reply).
 */
require_once __DIR__ . '/../../Core/Database.php';

class ForumVote
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function setVote($replyId, $voterId, $voterRole, $voteType)
    {
        $this->db->query(
            'INSERT INTO forum_votos (reply_id, voter_id, voter_role, vote_type) VALUES (:rid, :vid, :vrole, :vtype)
             ON DUPLICATE KEY UPDATE vote_type = :vtype2',
            [
                'rid' => (int) $replyId,
                'vid' => (int) $voterId,
                'vrole' => $voterRole,
                'vtype' => $voteType,
                'vtype2' => $voteType,
            ]
        );
    }

    public function getScoreByReplyId($replyId)
    {
        $row = $this->db->fetch(
            'SELECT SUM(CASE WHEN vote_type = "upvote" THEN 1 ELSE -1 END) AS score FROM forum_votos WHERE reply_id = :rid',
            ['rid' => (int) $replyId]
        );
        return (int) ($row['score'] ?? 0);
    }

    public function getScoresByReplyIds(array $replyIds)
    {
        if (empty($replyIds)) {
            return [];
        }
        $ids = array_map('intval', $replyIds);
        $placeholders = implode(',', $ids);
        $rows = $this->db->fetchAll(
            "SELECT reply_id, SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE -1 END) AS score FROM forum_votos WHERE reply_id IN ($placeholders) GROUP BY reply_id"
        );
        $out = [];
        foreach ($replyIds as $id) {
            $out[(int) $id] = 0;
        }
        foreach ($rows as $r) {
            $out[(int) $r['reply_id']] = (int) $r['score'];
        }
        return $out;
    }
}
