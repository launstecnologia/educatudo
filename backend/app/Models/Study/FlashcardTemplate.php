<?php
/**
 * Cache of flashcard sets by topic+grade+quantity for reuse across students (saves IA calls).
 */
require_once __DIR__ . '/../../Core/Database.php';

class FlashcardTemplate
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find template by normalized topic, grade, quantity. Returns template row or null.
     */
    public function findByTopicGradeQuantity($topicNormalized, $grade, $quantity)
    {
        return $this->db->fetch(
            'SELECT id FROM flashcards_modelos WHERE topic_normalized = :t AND grade = :g AND quantity = :q LIMIT 1',
            [
                't' => $topicNormalized,
                'g' => $grade,
                'q' => (int) $quantity,
            ]
        );
    }

    /**
     * Get all cards for a template (ordered by position).
     */
    public function getCards($templateId)
    {
        return $this->db->fetchAll(
            'SELECT question, answer, position FROM flashcards_modelos_cartas WHERE template_id = :id ORDER BY position, id',
            ['id' => (int) $templateId]
        );
    }

    /**
     * Create template and its cards; return template id.
     */
    public function createWithCards($topicNormalized, $grade, $quantity, array $items)
    {
        $this->db->query(
            'INSERT INTO flashcards_modelos (topic_normalized, grade, quantity) VALUES (:t, :g, :q)',
            [
                't' => $topicNormalized,
                'g' => $grade,
                'q' => (int) $quantity,
            ]
        );
        $templateId = (int) $this->db->getPdo()->lastInsertId();
        $position = 0;
        foreach ($items as $item) {
            $question = isset($item['question']) ? trim((string) $item['question']) : '';
            $answer = isset($item['answer']) ? trim((string) $item['answer']) : '';
            if ($question !== '' || $answer !== '') {
                $this->db->query(
                    'INSERT INTO flashcards_modelos_cartas (template_id, question, answer, position) VALUES (:tid, :q, :a, :pos)',
                    [
                        'tid' => $templateId,
                        'q' => $question,
                        'a' => $answer,
                        'pos' => $position++,
                    ]
                );
            }
        }
        return $templateId;
    }
}
