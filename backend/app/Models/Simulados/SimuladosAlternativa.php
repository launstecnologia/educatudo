<?php
/**
 * EducaTudo - Model Simulados Alternativa (catálogo master)
 */

class SimuladosAlternativa
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listByQuestao($questaoId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM simulados_alternativas WHERE questao_id = :questao_id ORDER BY ordem, letra, id",
            ['questao_id' => (int) $questaoId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM simulados_alternativas WHERE id = :id", ['id' => (int) $id]);
    }

    public function create($data)
    {
        return (int) $this->db->insert(
            "INSERT INTO simulados_alternativas (questao_id, letra, texto, arquivo, is_correta, ordem) VALUES (:questao_id, :letra, :texto, :arquivo, :is_correta, :ordem)",
            [
                'questao_id' => (int) ($data['questao_id'] ?? 0),
                'letra' => $data['letra'] ?? 'A',
                'texto' => $data['texto'] ?? null,
                'arquivo' => $data['arquivo'] ?? null,
                'is_correta' => isset($data['is_correta']) ? (int) $data['is_correta'] : 0,
                'ordem' => (int) ($data['ordem'] ?? 0),
            ]
        );
    }

    public function update($id, $data)
    {
        $this->db->query(
            "UPDATE simulados_alternativas SET questao_id = :questao_id, letra = :letra, texto = :texto, arquivo = :arquivo, is_correta = :is_correta, ordem = :ordem, updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'questao_id' => (int) ($data['questao_id'] ?? 0),
                'letra' => $data['letra'] ?? 'A',
                'texto' => $data['texto'] ?? null,
                'arquivo' => $data['arquivo'] ?? null,
                'is_correta' => isset($data['is_correta']) ? (int) $data['is_correta'] : 0,
                'ordem' => (int) ($data['ordem'] ?? 0),
            ]
        );
        return true;
    }

    public function delete($id)
    {
        $this->db->query("DELETE FROM simulados_alternativas WHERE id = :id", ['id' => (int) $id]);
        return true;
    }

    public function deleteByQuestao($questaoId)
    {
        $this->db->query("DELETE FROM simulados_alternativas WHERE questao_id = :questao_id", ['questao_id' => (int) $questaoId]);
        return true;
    }
}
