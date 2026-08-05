<?php
/**
 * EducaTudo - Model Simulados Banca (catálogo master)
 */

class SimuladosBanca
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listAll($apenasAtivos = false)
    {
        $sql = "SELECT * FROM simulados_bancas";
        if ($apenasAtivos) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY nome";
        return $this->db->fetchAll($sql);
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM simulados_bancas WHERE id = :id", ['id' => (int) $id]);
    }

    public function findBySlug($slug)
    {
        return $this->db->fetch("SELECT * FROM simulados_bancas WHERE slug = :slug", ['slug' => $slug]);
    }

    public function create($data)
    {
        return (int) $this->db->insert(
            "INSERT INTO simulados_bancas (nome, slug, descricao, ativo) VALUES (:nome, :slug, :descricao, :ativo)",
            [
                'nome' => $data['nome'] ?? '',
                'slug' => $data['slug'] ?? '',
                'descricao' => $data['descricao'] ?? null,
                'ativo' => isset($data['ativo']) ? (int) $data['ativo'] : 1,
            ]
        );
    }

    public function update($id, $data)
    {
        $this->db->query(
            "UPDATE simulados_bancas SET nome = :nome, slug = :slug, descricao = :descricao, ativo = :ativo, updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'nome' => $data['nome'] ?? '',
                'slug' => $data['slug'] ?? '',
                'descricao' => $data['descricao'] ?? null,
                'ativo' => isset($data['ativo']) ? (int) $data['ativo'] : 1,
            ]
        );
        return true;
    }

    public function delete($id)
    {
        $this->db->query("DELETE FROM simulados_bancas WHERE id = :id", ['id' => (int) $id]);
        return true;
    }
}
