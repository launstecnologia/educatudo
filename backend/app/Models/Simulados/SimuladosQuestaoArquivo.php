<?php
/**
 * EducaTudo - Model Simulados Questão Arquivo (imagens/arquivos por questão - catálogo master)
 */

class SimuladosQuestaoArquivo
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listByQuestao($questaoId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM simulados_questoes_arquivos WHERE questao_id = :questao_id ORDER BY ordem, id",
            ['questao_id' => (int) $questaoId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM simulados_questoes_arquivos WHERE id = :id", ['id' => (int) $id]);
    }

    public function create($data)
    {
        return (int) $this->db->insert(
            "INSERT INTO simulados_questoes_arquivos (questao_id, arquivo_url, tipo_arquivo, ordem, legenda) VALUES (:questao_id, :arquivo_url, :tipo_arquivo, :ordem, :legenda)",
            [
                'questao_id' => (int) ($data['questao_id'] ?? 0),
                'arquivo_url' => $data['arquivo_url'] ?? '',
                'tipo_arquivo' => $data['tipo_arquivo'] ?? 'imagem',
                'ordem' => (int) ($data['ordem'] ?? 0),
                'legenda' => $data['legenda'] ?? null,
            ]
        );
    }

    public function update($id, $data)
    {
        $this->db->query(
            "UPDATE simulados_questoes_arquivos SET questao_id = :questao_id, arquivo_url = :arquivo_url, tipo_arquivo = :tipo_arquivo, ordem = :ordem, legenda = :legenda WHERE id = :id",
            [
                'id' => (int) $id,
                'questao_id' => (int) ($data['questao_id'] ?? 0),
                'arquivo_url' => $data['arquivo_url'] ?? '',
                'tipo_arquivo' => $data['tipo_arquivo'] ?? 'imagem',
                'ordem' => (int) ($data['ordem'] ?? 0),
                'legenda' => $data['legenda'] ?? null,
            ]
        );
        return true;
    }

    public function delete($id)
    {
        $this->db->query("DELETE FROM simulados_questoes_arquivos WHERE id = :id", ['id' => (int) $id]);
        return true;
    }

    public function deleteByQuestao($questaoId)
    {
        $this->db->query("DELETE FROM simulados_questoes_arquivos WHERE questao_id = :questao_id", ['questao_id' => (int) $questaoId]);
        return true;
    }
}
