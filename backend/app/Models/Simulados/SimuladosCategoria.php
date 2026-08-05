<?php
/**
 * EducaTudo - Model Simulados Categoria (área, disciplina, tema, subtema - catálogo master)
 */

class SimuladosCategoria
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listAll($parentId = null, $tipo = null, $apenasAtivos = false)
    {
        $sql = "SELECT c.*, p.nome as parent_nome FROM simulados_categorias c LEFT JOIN simulados_categorias p ON p.id = c.parent_id WHERE 1=1";
        $params = [];
        if ($parentId !== null) {
            if ($parentId === 0 || $parentId === '0') {
                $sql .= " AND c.parent_id IS NULL";
            } else {
                $sql .= " AND c.parent_id = :parent_id";
                $params['parent_id'] = (int) $parentId;
            }
        }
        if ($tipo !== null && $tipo !== '') {
            $sql .= " AND c.tipo = :tipo";
            $params['tipo'] = $tipo;
        }
        if ($apenasAtivos) {
            $sql .= " AND c.ativo = 1";
        }
        $sql .= " ORDER BY c.ordem, c.nome";
        return $this->db->fetchAll($sql, $params);
    }

    /** Árvore: lista categorias com filhos (por tipo ou todas) */
    public function listTree($tipo = null, $apenasAtivos = false)
    {
        $all = $this->listAll(null, $tipo, $apenasAtivos);
        $byParent = [];
        foreach ($all as $row) {
            $pid = $row['parent_id'] ?? 0;
            if (!isset($byParent[$pid])) {
                $byParent[$pid] = [];
            }
            $byParent[$pid][] = $row;
        }
        return $byParent;
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT c.*, p.nome as parent_nome FROM simulados_categorias c LEFT JOIN simulados_categorias p ON p.id = c.parent_id WHERE c.id = :id",
            ['id' => (int) $id]
        );
    }

    public function create($data)
    {
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' && (int) $data['parent_id'] > 0 ? (int) $data['parent_id'] : null;
        return (int) $this->db->insert(
            "INSERT INTO simulados_categorias (parent_id, tipo, nome, slug, descricao, ativo, ordem) VALUES (:parent_id, :tipo, :nome, :slug, :descricao, :ativo, :ordem)",
            [
                'parent_id' => $parentId,
                'tipo' => $data['tipo'] ?? 'area',
                'nome' => $data['nome'] ?? '',
                'slug' => $data['slug'] ?? '',
                'descricao' => $data['descricao'] ?? null,
                'ativo' => isset($data['ativo']) ? (int) $data['ativo'] : 1,
                'ordem' => (int) ($data['ordem'] ?? 0),
            ]
        );
    }

    public function update($id, $data)
    {
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' && (int) $data['parent_id'] > 0 ? (int) $data['parent_id'] : null;
        $this->db->query(
            "UPDATE simulados_categorias SET parent_id = :parent_id, tipo = :tipo, nome = :nome, slug = :slug, descricao = :descricao, ativo = :ativo, ordem = :ordem, updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'parent_id' => $parentId,
                'tipo' => $data['tipo'] ?? 'area',
                'nome' => $data['nome'] ?? '',
                'slug' => $data['slug'] ?? '',
                'descricao' => $data['descricao'] ?? null,
                'ativo' => isset($data['ativo']) ? (int) $data['ativo'] : 1,
                'ordem' => (int) ($data['ordem'] ?? 0),
            ]
        );
        return true;
    }

    public function delete($id)
    {
        $this->db->query("DELETE FROM simulados_categorias WHERE id = :id", ['id' => (int) $id]);
        return true;
    }
}
