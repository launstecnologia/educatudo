<?php
/**
 * Flashcard deck (topic + grade + student). Contains many Flashcards.
 */
require_once __DIR__ . '/../../Core/Database.php';

class FlashcardDeck
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create deck and return its id.
     */
    public function create($alunoId, $topic, $grade, $quantity)
    {
        $this->db->query(
            'INSERT INTO flashcards_baralhos (aluno_id, topic, grade, quantity) VALUES (:aid, :topic, :grade, :qty)',
            [
                'aid' => (int) $alunoId,
                'topic' => $topic,
                'grade' => $grade,
                'qty' => (int) $quantity,
            ]
        );
        return (int) $this->db->getPdo()->lastInsertId();
    }

    /**
     * Get deck by id (must belong to aluno for security).
     */
    public function getByIdAndAluno($id, $alunoId)
    {
        return $this->db->fetch(
            'SELECT * FROM flashcards_baralhos WHERE id = :id AND aluno_id = :aid LIMIT 1',
            ['id' => (int) $id, 'aid' => (int) $alunoId]
        );
    }

    /**
     * List decks for student.
     */
    public function listByAluno($alunoId, $limit = 50)
    {
        return $this->db->fetchAll(
            'SELECT * FROM flashcards_baralhos WHERE aluno_id = :aid ORDER BY created_at DESC LIMIT ' . (int) $limit,
            ['aid' => (int) $alunoId]
        );
    }
}
