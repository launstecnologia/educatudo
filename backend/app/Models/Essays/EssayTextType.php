<?php
/**
 * EducaTudo - Essay Text Type Model (Tipo Textual)
 * Redação Configurável
 */

class EssayTextType
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByBoard($boardId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM redacoes_orientadas_tipos_texto WHERE board_id = :board_id ORDER BY name ASC",
            ['board_id' => (int) $boardId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT t.*, b.name as board_name FROM redacoes_orientadas_tipos_texto t
             JOIN redacoes_orientadas_quadros b ON t.board_id = b.id
             WHERE t.id = :id",
            ['id' => (int) $id]
        );
    }

    public function create($data)
    {
        return $this->db->insert(
            "INSERT INTO redacoes_orientadas_tipos_texto (board_id, name, slug, description) VALUES (:board_id, :name, :slug, :description)",
            [
                'board_id' => (int) $data['board_id'],
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null
            ]
        );
    }

    public function update($id, $data)
    {
        $this->db->update(
            "UPDATE redacoes_orientadas_tipos_texto SET board_id = :board_id, name = :name, slug = :slug, description = :description, updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'board_id' => (int) $data['board_id'],
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null
            ]
        );
    }

    public function delete($id)
    {
        $this->db->delete("DELETE FROM redacoes_orientadas_tipos_texto WHERE id = :id", ['id' => (int) $id]);
    }

    public function slugExistsForBoard($boardId, $slug, $excludeId = null)
    {
        $sql = "SELECT 1 FROM redacoes_orientadas_tipos_texto WHERE board_id = :board_id AND slug = :slug";
        $params = ['board_id' => (int) $boardId, 'slug' => $slug];
        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = (int) $excludeId;
        }
        return $this->db->fetch($sql, $params) !== false;
    }
}
