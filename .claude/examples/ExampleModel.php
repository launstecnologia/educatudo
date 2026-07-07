<?php
/**
 * EducaTudo - Example Model (arquivo de referência de estilo — não é carregado pela aplicação)
 *
 * Padrões demonstrados: Database::getInstance() no construtor, prepared statements
 * com parâmetros nomeados, cast explícito de ids, métodos find*/create/update.
 */

class ExampleItem
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT i.*, a.nome as student_name
             FROM example_items i
             JOIN alunos a ON i.student_id = a.id
             WHERE i.id = :id",
            ['id' => (int) $id]
        );
    }

    public function findByStudent($studentId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM example_items WHERE student_id = :student_id ORDER BY created_at DESC",
            ['student_id' => (int) $studentId]
        );
    }

    public function create($data)
    {
        return $this->db->insert(
            "INSERT INTO example_items (student_id, title, status) VALUES (:student_id, :title, :status)",
            [
                'student_id' => (int) $data['student_id'],
                'title' => $data['title'],
                'status' => $data['status'] ?? 'draft',
            ]
        );
    }

    public function updateStatus($id, $status)
    {
        return $this->db->execute(
            "UPDATE example_items SET status = :status, updated_at = NOW() WHERE id = :id",
            ['status' => $status, 'id' => (int) $id]
        );
    }
}
