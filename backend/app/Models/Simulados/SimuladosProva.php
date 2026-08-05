<?php
/**
 * EducaTudo - Model Simulados Prova (catálogo master)
 */

class SimuladosProva
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listAll($bancaId = null, $apenasAtivos = false)
    {
        $sql = "SELECT p.*, b.nome as banca_nome, b.slug as banca_slug FROM simulados_provas p JOIN simulados_bancas b ON b.id = p.banca_id WHERE 1=1";
        $params = [];
        if ($bancaId !== null && $bancaId > 0) {
            $sql .= " AND p.banca_id = :banca_id";
            $params['banca_id'] = (int) $bancaId;
        }
        if ($apenasAtivos) {
            $sql .= " AND p.ativo = 1 AND b.ativo = 1";
        }
        $sql .= " ORDER BY b.nome, p.ano DESC, p.titulo";
        return $this->db->fetchAll($sql, $params);
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT p.*, b.nome as banca_nome, b.slug as banca_slug FROM simulados_provas p JOIN simulados_bancas b ON b.id = p.banca_id WHERE p.id = :id",
            ['id' => (int) $id]
        );
    }

    public function create($data)
    {
        return (int) $this->db->insert(
            "INSERT INTO simulados_provas (banca_id, titulo, ano, fase, tipo, descricao, ativo) VALUES (:banca_id, :titulo, :ano, :fase, :tipo, :descricao, :ativo)",
            [
                'banca_id' => (int) ($data['banca_id'] ?? 0),
                'titulo' => $data['titulo'] ?? '',
                'ano' => (int) ($data['ano'] ?? date('Y')),
                'fase' => $data['fase'] ?? null,
                'tipo' => $data['tipo'] ?? null,
                'descricao' => $data['descricao'] ?? null,
                'ativo' => isset($data['ativo']) ? (int) $data['ativo'] : 1,
            ]
        );
    }

    public function update($id, $data)
    {
        $this->db->query(
            "UPDATE simulados_provas SET banca_id = :banca_id, titulo = :titulo, ano = :ano, fase = :fase, tipo = :tipo, descricao = :descricao, ativo = :ativo, updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'banca_id' => (int) ($data['banca_id'] ?? 0),
                'titulo' => $data['titulo'] ?? '',
                'ano' => (int) ($data['ano'] ?? date('Y')),
                'fase' => $data['fase'] ?? null,
                'tipo' => $data['tipo'] ?? null,
                'descricao' => $data['descricao'] ?? null,
                'ativo' => isset($data['ativo']) ? (int) $data['ativo'] : 1,
            ]
        );
        return true;
    }

    public function delete($id)
    {
        $this->db->query("DELETE FROM simulados_provas WHERE id = :id", ['id' => (int) $id]);
        return true;
    }
}
