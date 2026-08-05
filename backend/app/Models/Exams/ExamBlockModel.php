<?php
/**
 * EducaTudo - Modelo de Bloco Modelo
 * Gerencia operações de banco de dados para blocos modelo (templates)
 */

class ExamBlockModel
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Busca todos os blocos modelo
     */
    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT * FROM provas_blocos_modelos 
             WHERE deleted_at IS NULL 
             ORDER BY id DESC"
        );
    }
    
    /**
     * Busca bloco modelo por ID
     */
    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT * FROM provas_blocos_modelos 
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
    }
    
    /**
     * Busca professores de um modelo
     */
    public function getProfessores($modeloId)
    {
        return $this->db->fetchAll(
            "SELECT bmp.*, 
                    p.nome as professor_nome,
                    m.nome as materia_nome
             FROM provas_blocos_modelos_professores bmp
             LEFT JOIN professores p ON bmp.professor_id = p.id
             LEFT JOIN materias m ON bmp.materia_id = m.id
             WHERE bmp.modelo_id = :modelo_id
             ORDER BY bmp.ordem ASC, bmp.id ASC",
            ['modelo_id' => $modeloId]
        );
    }
    
    /**
     * Cria novo bloco modelo
     */
    public function create($data)
    {
        $this->db->beginTransaction();
        
        try {
            // Insere o modelo
            // Verifica se a tabela tem coluna criado_por
            $temCriadoPor = false;
            try {
                $colunas = $this->db->fetchAll("SHOW COLUMNS FROM provas_blocos_modelos LIKE 'criado_por'");
                $temCriadoPor = !empty($colunas);
            } catch (Exception $e) {
                // Ignora erro
            }
            
            if ($temCriadoPor && !empty($data['criado_por'])) {
                $modeloId = $this->db->insert(
                    "INSERT INTO provas_blocos_modelos (nome, descricao, criado_por) 
                     VALUES (:nome, :descricao, :criado_por)",
                    [
                        'nome' => $data['nome'],
                        'descricao' => $data['descricao'] ?? null,
                        'criado_por' => $data['criado_por']
                    ]
                );
            } else {
                $modeloId = $this->db->insert(
                    "INSERT INTO provas_blocos_modelos (nome, descricao) 
                     VALUES (:nome, :descricao)",
                    [
                        'nome' => $data['nome'],
                        'descricao' => $data['descricao'] ?? null
                    ]
                );
            }
            
            // Insere professores
            if (!empty($data['professores']) && is_array($data['professores'])) {
                foreach ($data['professores'] as $index => $professorData) {
                    $this->db->insert(
                        "INSERT INTO provas_blocos_modelos_professores (modelo_id, professor_id, materia_id, numero_questoes, ordem) 
                         VALUES (:modelo_id, :professor_id, :materia_id, :numero_questoes, :ordem)",
                        [
                            'modelo_id' => $modeloId,
                            'professor_id' => $professorData['professor_id'],
                            'materia_id' => $professorData['materia_id'],
                            'numero_questoes' => $professorData['numero_questoes'] ?? 5,
                            'ordem' => $index
                        ]
                    );
                }
            }
            
            $this->db->commit();
            return $modeloId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Atualiza bloco modelo
     */
    public function update($id, $data)
    {
        $this->db->beginTransaction();
        
        try {
            // Atualiza o modelo
            $this->db->query(
                "UPDATE provas_blocos_modelos 
                 SET nome = :nome, descricao = :descricao 
                 WHERE id = :id",
                [
                    'nome' => $data['nome'],
                    'descricao' => $data['descricao'] ?? null,
                    'id' => $id
                ]
            );
            
            // Remove professores antigos
            $this->db->query(
                "DELETE FROM provas_blocos_modelos_professores WHERE modelo_id = :modelo_id",
                ['modelo_id' => $id]
            );
            
            // Insere novos professores
            if (!empty($data['professores']) && is_array($data['professores'])) {
                foreach ($data['professores'] as $index => $professorData) {
                    $this->db->insert(
                        "INSERT INTO provas_blocos_modelos_professores (modelo_id, professor_id, materia_id, numero_questoes, ordem) 
                         VALUES (:modelo_id, :professor_id, :materia_id, :numero_questoes, :ordem)",
                        [
                            'modelo_id' => $id,
                            'professor_id' => $professorData['professor_id'],
                            'materia_id' => $professorData['materia_id'],
                            'numero_questoes' => $professorData['numero_questoes'] ?? 5,
                            'ordem' => $index
                        ]
                    );
                }
            }
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Exclui bloco modelo (soft delete)
     */
    public function delete($id)
    {
        return $this->db->query(
            "UPDATE provas_blocos_modelos 
             SET deleted_at = NOW() 
             WHERE id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Clona configuração do modelo para um bloco de prova
     */
    public function clonarParaBloco($modeloId, $blocoId)
    {
        $professores = $this->getProfessores($modeloId);
        
        if (empty($professores)) {
            return [];
        }
        
        $provasProfessores = [];
        
        foreach ($professores as $prof) {
            $provasProfessores[] = [
                'bloco_id' => $blocoId,
                'professor_id' => $prof['professor_id'],
                'materia_id' => $prof['materia_id'],
                'numero_questoes' => $prof['numero_questoes'],
                'status' => 'em_andamento'
            ];
        }
        
        return $provasProfessores;
    }
}

