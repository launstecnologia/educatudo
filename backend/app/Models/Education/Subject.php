<?php
/**
 * EducaTudo - Modelo de Matérias
 * Gerencia operações de banco de dados para matérias
 */

class Subject
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Busca todas as matérias
     */
    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT * FROM materias ORDER BY nome ASC"
        );
    }
    
    /**
     * Busca matéria por ID
     */
    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT * FROM materias WHERE id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Cria nova matéria
     */
    public function create($data)
    {
        $sql = "INSERT INTO materias (nome) VALUES (:nome)";
        return $this->db->insert($sql, [
            'nome' => $data['nome']
        ]);
    }
    
    /**
     * Atualiza matéria
     */
    public function update($id, $data)
    {
        $sql = "UPDATE materias SET nome = :nome WHERE id = :id";
        return $this->db->update($sql, [
            'nome' => $data['nome'],
            'id' => $id
        ]);
    }
    
    /**
     * Exclui matéria
     */
    public function delete($id)
    {
        return $this->db->delete("DELETE FROM materias WHERE id = :id", ['id' => $id]);
    }
    
    /**
     * Verifica se matéria existe
     */
    public function exists($id)
    {
        $result = $this->db->fetch("SELECT id FROM materias WHERE id = :id", ['id' => $id]);
        return $result !== false;
    }
    
    /**
     * Verifica se nome da matéria já existe
     */
    public function nameExists($nome, $excludeId = null)
    {
        $sql = "SELECT id FROM materias WHERE nome = :nome";
        $params = ['nome' => $nome];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }
    
    /**
     * Busca matérias por professor
     */
    public function getByProfessor($professorId)
    {
        // Como não há mais professor_id na tabela materias, 
        // este método agora retorna todas as matérias
        return $this->getAll();
    }
    
    /**
     * Conta total de matérias
     */
    public function count()
    {
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM materias");
        return $result['total'];
    }
    
    /**
     * Busca professores disponíveis para atribuir matérias
     */
    public function getAvailableProfessors()
    {
        return $this->db->fetchAll(
            "SELECT p.*, u.nome as nome_professor
             FROM professores p
             JOIN usuarios u ON p.usuario_id = u.id
             WHERE p.ativo = 1
             ORDER BY u.nome ASC"
        );
    }
}
