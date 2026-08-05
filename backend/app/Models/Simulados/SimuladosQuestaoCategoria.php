<?php
/**
 * EducaTudo - Model Simulados Questão-Categoria (vínculo N:N - catálogo master)
 */

class SimuladosQuestaoCategoria
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getCategoriaIdsByQuestao($questaoId)
    {
        $rows = $this->db->fetchAll("SELECT categoria_id FROM simulados_questoes_categorias WHERE questao_id = :questao_id", ['questao_id' => (int) $questaoId]);
        return array_map(function ($r) { return (int) $r['categoria_id']; }, $rows);
    }

    public function setCategoriasForQuestao($questaoId, array $categoriaIds)
    {
        $this->db->query("DELETE FROM simulados_questoes_categorias WHERE questao_id = :questao_id", ['questao_id' => (int) $questaoId]);
        foreach ($categoriaIds as $catId) {
            $catId = (int) $catId;
            if ($catId <= 0) continue;
            $this->db->insert(
                "INSERT INTO simulados_questoes_categorias (questao_id, categoria_id) VALUES (:questao_id, :categoria_id)",
                ['questao_id' => (int) $questaoId, 'categoria_id' => $catId]
            );
        }
        return true;
    }

    public function listByQuestao($questaoId)
    {
        return $this->db->fetchAll(
            "SELECT qc.*, c.nome as categoria_nome, c.tipo as categoria_tipo FROM simulados_questoes_categorias qc JOIN simulados_categorias c ON c.id = qc.categoria_id WHERE qc.questao_id = :questao_id ORDER BY c.tipo, c.nome",
            ['questao_id' => (int) $questaoId]
        );
    }
}
