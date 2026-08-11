<?php
/**
 * Single flashcard (question/answer) inside a FlashcardDeck.
 */
require_once __DIR__ . '/../../Core/Database.php';

class Flashcard
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Add one card to a deck.
     */
    public function add($deckId, $question, $answer, $position)
    {
        $this->db->query(
            'INSERT INTO flashcards_cartas (deck_id, question, answer, position) VALUES (:did, :q, :a, :pos)',
            [
                'did' => (int) $deckId,
                'q' => $question,
                'a' => $answer,
                'pos' => (int) $position,
            ]
        );
    }

    /**
     * Get all cards of a deck (ordered by position).
     */
    public function listByDeck($deckId)
    {
        return $this->db->fetchAll(
            'SELECT id, question, answer, position FROM flashcards_cartas WHERE deck_id = :did ORDER BY position, id',
            ['did' => (int) $deckId]
        );
    }

    /**
     * Count cards per deck (for list view). Returns [deck_id => count].
     */
    public function countByDeckIds(array $deckIds)
    {
        if (empty($deckIds)) {
            return [];
        }
        $ids = array_map('intval', $deckIds);
        $placeholders = implode(',', $ids);
        $rows = $this->db->fetchAll(
            "SELECT deck_id, COUNT(*) as cnt FROM flashcards_cartas WHERE deck_id IN ($placeholders) GROUP BY deck_id"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['deck_id']] = (int) $r['cnt'];
        }
        return $out;
    }
}
