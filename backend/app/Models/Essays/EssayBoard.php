<?php
/**
 * EducaTudo - Essay Board Model (Banca)
 * Redação Configurável
 */

class EssayBoard
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all($activeOnly = false)
    {
        $sql = "SELECT * FROM redacoes_orientadas_quadros";
        $params = [];
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY name ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM redacoes_orientadas_quadros WHERE id = :id", ['id' => (int) $id]);
    }

    public function findBySlug($slug)
    {
        return $this->db->fetch("SELECT * FROM redacoes_orientadas_quadros WHERE slug = :slug", ['slug' => $slug]);
    }

    public function create($data)
    {
        return $this->db->insert(
            "INSERT INTO redacoes_orientadas_quadros (name, slug, is_active) VALUES (:name, :slug, :is_active)",
            [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1
            ]
        );
    }

    public function update($id, $data)
    {
        $this->db->update(
            "UPDATE redacoes_orientadas_quadros SET name = :name, slug = :slug, is_active = :is_active, updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1
            ]
        );
    }

    public function delete($id)
    {
        $this->db->delete("DELETE FROM redacoes_orientadas_quadros WHERE id = :id", ['id' => (int) $id]);
    }

    public function slugExists($slug, $excludeId = null)
    {
        $sql = "SELECT 1 FROM redacoes_orientadas_quadros WHERE slug = :slug";
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = (int) $excludeId;
        }
        return $this->db->fetch($sql, $params) !== false;
    }
}
