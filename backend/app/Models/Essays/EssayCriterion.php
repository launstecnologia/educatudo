<?php
/**
 * EducaTudo - Essay Criterion Model (Critério de correção)
 * Redação Configurável
 */

class EssayCriterion
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByTextType($textTypeId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM redacoes_orientadas_criterios WHERE text_type_id = :text_type_id ORDER BY order_position ASC, name ASC",
            ['text_type_id' => (int) $textTypeId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT c.*, t.name as text_type_name, t.board_id FROM redacoes_orientadas_criterios c
             JOIN redacoes_orientadas_tipos_texto t ON c.text_type_id = t.id
             WHERE c.id = :id",
            ['id' => (int) $id]
        );
    }

    public function create($data)
    {
        $order = (int) ($data['order_position'] ?? 0);
        return $this->db->insert(
            "INSERT INTO redacoes_orientadas_criterios (text_type_id, name, slug, description, max_score, order_position) VALUES (:text_type_id, :name, :slug, :description, :max_score, :order_position)",
            [
                'text_type_id' => (int) $data['text_type_id'],
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'max_score' => isset($data['max_score']) ? (float) $data['max_score'] : 200.00,
                'order_position' => $order
            ]
        );
    }

    public function update($id, $data)
    {
        $this->db->update(
            "UPDATE redacoes_orientadas_criterios SET text_type_id = :text_type_id, name = :name, slug = :slug, description = :description, max_score = :max_score, order_position = :order_position, updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'text_type_id' => (int) $data['text_type_id'],
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'max_score' => isset($data['max_score']) ? (float) $data['max_score'] : 200.00,
                'order_position' => (int) ($data['order_position'] ?? 0)
            ]
        );
    }

    public function delete($id)
    {
        $this->db->delete("DELETE FROM redacoes_orientadas_criterios WHERE id = :id", ['id' => (int) $id]);
    }
}
