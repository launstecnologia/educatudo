<?php
/**
 * Forum topic (question/post).
 */
require_once __DIR__ . '/../../Core/Database.php';

class ForumTopic
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create($title, $content, $authorId, $authorRole, $subjectId = null, $turmaId = null)
    {
        $this->db->query(
            'INSERT INTO forum_topicos (title, content, author_id, author_role, subject_id, turma_id) VALUES (:title, :content, :aid, :role, :sid, :tid)',
            [
                'title' => $title,
                'content' => $content,
                'aid' => (int) $authorId,
                'role' => $authorRole,
                'sid' => $subjectId ? (int) $subjectId : null,
                'tid' => $turmaId ? (int) $turmaId : null,
            ]
        );
        return (int) $this->db->getPdo()->lastInsertId();
    }

    /**
     * Associa um tópico a várias turmas (forum_topicos_turmas).
     * Se $turmaIds for vazio, o tópico fica visível para todas (não insere linhas).
     */
    public function setTopicTurmas($topicId, array $turmaIds)
    {
        $topicId = (int) $topicId;
        $this->db->query('DELETE FROM forum_topicos_turmas WHERE topic_id = :tid', ['tid' => $topicId]);
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        foreach ($turmaIds as $tid) {
            $this->db->query(
                'INSERT INTO forum_topicos_turmas (topic_id, turma_id) VALUES (:topic_id, :turma_id)',
                ['topic_id' => $topicId, 'turma_id' => $tid]
            );
        }
    }

    public function getById($id)
    {
        return $this->db->fetch('SELECT * FROM forum_topicos WHERE id = :id LIMIT 1', ['id' => (int) $id]);
    }

    public function listPaginated($filters = [], $limit = 20, $offset = 0)
    {
        $where = ['1=1'];
        $params = [];
        if (isset($filters['is_resolved']) && $filters['is_resolved'] !== '') {
            $where[] = 't.is_resolved = :resolved';
            $params['resolved'] = (int) $filters['is_resolved'];
        }
        if (isset($filters['turma_ids']) && is_array($filters['turma_ids']) && !empty($filters['turma_ids'])) {
            $ids = array_values(array_unique(array_map('intval', array_filter($filters['turma_ids']))));
            if (!empty($ids)) {
                $ph = [];
                $ph2 = [];
                foreach ($ids as $i => $id) {
                    $key = 'tid' . $i;
                    $key2 = 'tid2_' . $i;
                    $ph[] = ':' . $key;
                    $ph2[] = ':' . $key2;
                    $params[$key] = $id;
                    $params[$key2] = $id;
                }
                $inList = implode(',', $ph);
                $inList2 = implode(',', $ph2);
                $where[] = "(t.turma_id IN ($inList) OR t.turma_id IS NULL OR EXISTS (SELECT 1 FROM forum_topicos_turmas ftt WHERE ftt.topic_id = t.id AND ftt.turma_id IN ($inList2)))";
            }
        } elseif (!empty($filters['turma_id'])) {
            $where[] = '(t.turma_id = :turma_id OR t.turma_id IS NULL OR EXISTS (SELECT 1 FROM forum_topicos_turmas ftt WHERE ftt.topic_id = t.id AND ftt.turma_id = :turma_id2))';
            $params['turma_id'] = (int) $filters['turma_id'];
            $params['turma_id2'] = (int) $filters['turma_id'];
        }
        if (!empty($filters['subject_id'])) {
            $where[] = 't.subject_id = :subject_id';
            $params['subject_id'] = (int) $filters['subject_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(t.title LIKE :q OR t.content LIKE :q2)';
            $term = '%' . trim($filters['q']) . '%';
            $params['q'] = $term;
            $params['q2'] = $term;
        }
        $whereSql = implode(' AND ', $where);
        $order = 'ORDER BY t.created_at DESC';
        if (!empty($filters['order']) && $filters['order'] === 'recent') {
            $order = 'ORDER BY t.updated_at DESC';
        }
        $limit = (int) $limit;
        $offset = (int) $offset;
        $sql = "SELECT t.*, 
                (SELECT COUNT(*) FROM forum_respostas r WHERE r.topic_id = t.id) AS replies_count
                FROM forum_topicos t 
                WHERE {$whereSql} {$order} LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    public function countAll($filters = [])
    {
        $where = ['1=1'];
        $params = [];
        if (isset($filters['is_resolved']) && $filters['is_resolved'] !== '') {
            $where[] = 'is_resolved = :resolved';
            $params['resolved'] = (int) $filters['is_resolved'];
        }
        if (isset($filters['turma_ids']) && is_array($filters['turma_ids']) && !empty($filters['turma_ids'])) {
            $ids = array_values(array_unique(array_map('intval', array_filter($filters['turma_ids']))));
            if (!empty($ids)) {
                $ph = [];
                $ph2 = [];
                foreach ($ids as $i => $id) {
                    $key = 'tid' . $i;
                    $key2 = 'tid2_' . $i;
                    $ph[] = ':' . $key;
                    $ph2[] = ':' . $key2;
                    $params[$key] = $id;
                    $params[$key2] = $id;
                }
                $inList = implode(',', $ph);
                $inList2 = implode(',', $ph2);
                $where[] = "(turma_id IN ($inList) OR turma_id IS NULL OR id IN (SELECT topic_id FROM forum_topicos_turmas WHERE turma_id IN ($inList2)))";
            }
        } elseif (!empty($filters['turma_id'])) {
            $where[] = '(turma_id = :turma_id OR turma_id IS NULL OR id IN (SELECT topic_id FROM forum_topicos_turmas WHERE turma_id = :turma_id2))';
            $params['turma_id'] = (int) $filters['turma_id'];
            $params['turma_id2'] = (int) $filters['turma_id'];
        }
        if (!empty($filters['subject_id'])) {
            $where[] = 'subject_id = :subject_id';
            $params['subject_id'] = (int) $filters['subject_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(title LIKE :q OR content LIKE :q2)';
            $term = '%' . trim($filters['q']) . '%';
            $params['q'] = $term;
            $params['q2'] = $term;
        }
        $whereSql = implode(' AND ', $where);
        $row = $this->db->fetch("SELECT COUNT(*) AS cnt FROM forum_topicos WHERE {$whereSql}", $params);
        return (int) ($row['cnt'] ?? 0);
    }

    public function markResolved($topicId)
    {
        $this->db->query('UPDATE forum_topicos SET is_resolved = 1, updated_at = NOW() WHERE id = :id', ['id' => (int) $topicId]);
    }

    public function delete($id)
    {
        $this->db->query('DELETE FROM forum_topicos WHERE id = :id', ['id' => (int) $id]);
    }
}
