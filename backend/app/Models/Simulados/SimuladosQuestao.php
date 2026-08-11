<?php
/**
 * EducaTudo - Model Simulados Questão (catálogo master)
 */

class SimuladosQuestao
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Lista questões com filtros pedagógicos (banca, prova, categoria, tipo, idioma, dificuldade)
     */
    public function listWithFilters($filters = [])
    {
        $sql = "SELECT q.*, p.titulo as prova_titulo, p.ano as prova_ano, b.nome as banca_nome
                FROM simulados_questoes q
                JOIN simulados_provas p ON p.id = q.prova_id
                JOIN simulados_bancas b ON b.id = p.banca_id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['banca_id'])) {
            $sql .= " AND p.banca_id = :banca_id";
            $params['banca_id'] = (int) $filters['banca_id'];
        }
        if (!empty($filters['prova_id'])) {
            $sql .= " AND q.prova_id = :prova_id";
            $params['prova_id'] = (int) $filters['prova_id'];
        }
        if (!empty($filters['categoria_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM simulados_questoes_categorias qc WHERE qc.questao_id = q.id AND qc.categoria_id = :categoria_id)";
            $params['categoria_id'] = (int) $filters['categoria_id'];
        }
        if (!empty($filters['tipo_questao'])) {
            $sql .= " AND q.tipo_questao = :tipo_questao";
            $params['tipo_questao'] = $filters['tipo_questao'];
        }
        if (!empty($filters['idioma'])) {
            $sql .= " AND q.idioma = :idioma";
            $params['idioma'] = $filters['idioma'];
        }
        if (!empty($filters['dificuldade'])) {
            $sql .= " AND q.dificuldade = :dificuldade";
            $params['dificuldade'] = $filters['dificuldade'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND q.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['ano'])) {
            $sql .= " AND p.ano = :ano";
            $params['ano'] = (int) $filters['ano'];
        }
        $sql .= " ORDER BY p.ano DESC, q.prova_id, q.indice, q.id";
        return $this->db->fetchAll($sql, $params);
    }

    public function listByProva($provaId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM simulados_questoes WHERE prova_id = :prova_id ORDER BY indice, id",
            ['prova_id' => (int) $provaId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT q.*, p.titulo as prova_titulo, p.ano as prova_ano, p.banca_id, b.nome as banca_nome
             FROM simulados_questoes q
             JOIN simulados_provas p ON p.id = q.prova_id
             JOIN simulados_bancas b ON b.id = p.banca_id
             WHERE q.id = :id",
            ['id' => (int) $id]
        );
    }

    public function create($data)
    {
        return (int) $this->db->insert(
            "INSERT INTO simulados_questoes (prova_id, indice, titulo, contexto, enunciado, tipo_questao, idioma, gabarito, comentario_resolucao, dificuldade, status, materias_path_json) VALUES (:prova_id, :indice, :titulo, :contexto, :enunciado, :tipo_questao, :idioma, :gabarito, :comentario_resolucao, :dificuldade, :status, :materias_path_json)",
            [
                'prova_id' => (int) ($data['prova_id'] ?? 0),
                'indice' => (int) ($data['indice'] ?? 0),
                'titulo' => $data['titulo'] ?? null,
                'contexto' => $data['contexto'] ?? null,
                'enunciado' => $data['enunciado'] ?? null,
                'tipo_questao' => $data['tipo_questao'] ?? 'objetiva',
                'idioma' => $data['idioma'] ?? 'pt',
                'gabarito' => $data['gabarito'] ?? null,
                'comentario_resolucao' => $data['comentario_resolucao'] ?? null,
                'dificuldade' => $data['dificuldade'] ?? null,
                'status' => $data['status'] ?? 'published',
                'materias_path_json' => $data['materias_path_json'] ?? null,
            ]
        );
    }

    public function update($id, $data)
    {
        $this->db->query(
            "UPDATE simulados_questoes SET prova_id = :prova_id, indice = :indice, titulo = :titulo, contexto = :contexto, enunciado = :enunciado, tipo_questao = :tipo_questao, idioma = :idioma, gabarito = :gabarito, comentario_resolucao = :comentario_resolucao, dificuldade = :dificuldade, status = :status, materias_path_json = :materias_path_json, updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'prova_id' => (int) ($data['prova_id'] ?? 0),
                'indice' => (int) ($data['indice'] ?? 0),
                'titulo' => $data['titulo'] ?? null,
                'contexto' => $data['contexto'] ?? null,
                'enunciado' => $data['enunciado'] ?? null,
                'tipo_questao' => $data['tipo_questao'] ?? 'objetiva',
                'idioma' => $data['idioma'] ?? 'pt',
                'gabarito' => $data['gabarito'] ?? null,
                'comentario_resolucao' => $data['comentario_resolucao'] ?? null,
                'dificuldade' => $data['dificuldade'] ?? null,
                'status' => $data['status'] ?? 'published',
                'materias_path_json' => $data['materias_path_json'] ?? null,
            ]
        );
        return true;
    }

    public function delete($id)
    {
        $this->db->query("DELETE FROM simulados_questoes WHERE id = :id", ['id' => (int) $id]);
        return true;
    }
}
