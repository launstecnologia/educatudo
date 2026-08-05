<?php
/**
 * EducaTudo - Modelo de Professores
 * Gerencia operações de banco de dados para professores
 */

class Teacher
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Busca todos os professores com informações do usuário
     */
    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT * FROM professores ORDER BY nome ASC"
        );
    }
    
    /**
     * Busca professores ativos
     */
    public function getActive()
    {
        return $this->db->fetchAll(
            "SELECT * FROM professores WHERE ativo = 1 ORDER BY nome ASC"
        );
    }
    
    /**
     * Busca professor por ID
     */
    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT * FROM professores WHERE id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Cria novo professor
     */
    public function create($data)
    {
        $sql = "INSERT INTO professores (nome, email, senha_hash, codigo_prof, materias, turmas, ativo, pagante) 
                VALUES (:nome, :email, :senha_hash, :codigo_prof, :materias, :turmas, :ativo, :pagante)";
        
        return $this->db->insert($sql, [
            'nome' => $data['nome'],
            'email' => $data['email'] ?: null,
            'senha_hash' => password_hash($data['senha'], PASSWORD_DEFAULT),
            'codigo_prof' => $data['codigo_prof'],
            'materias' => $data['materias'] ? json_encode($data['materias']) : null,
            'turmas' => $data['turmas'] ? json_encode($data['turmas']) : null,
            'ativo' => $data['ativo'] ?? 1,
            'pagante' => $data['pagante'] ?? 1
        ]);
    }
    
    /**
     * Atualiza professor
     */
    public function update($id, $data)
    {
        $sql = "UPDATE professores SET nome = :nome, email = :email, codigo_prof = :codigo_prof, 
                materias = :materias, turmas = :turmas, ativo = :ativo, pagante = :pagante";
        
        $params = [
            'nome' => $data['nome'],
            'email' => $data['email'] ?: null,
            'codigo_prof' => $data['codigo_prof'],
            'materias' => $data['materias'] ? json_encode($data['materias']) : null,
            'turmas' => $data['turmas'] ? json_encode($data['turmas']) : null,
            'ativo' => $data['ativo'] ?? 1,
            'pagante' => $data['pagante'] ?? 1,
            'id' => $id
        ];
        
        // Se senha foi fornecida, atualizar
        if (!empty($data['senha'])) {
            $sql .= ", senha_hash = :senha_hash";
            $params['senha_hash'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        }
        
        $sql .= " WHERE id = :id";
        
        return $this->db->update($sql, $params);
    }
    
    /**
     * Exclui professor
     */
    public function delete($id)
    {
        return $this->db->delete("DELETE FROM professores WHERE id = :id", ['id' => $id]);
    }
    
    /**
     * Verifica se professor existe
     */
    public function exists($id)
    {
        $result = $this->db->fetch("SELECT id FROM professores WHERE id = :id", ['id' => $id]);
        return $result !== false;
    }
    
    /**
     * Verifica se código do professor já existe
     */
    public function codeExists($codigo, $excludeId = null)
    {
        $sql = "SELECT id FROM professores WHERE codigo_prof = :codigo";
        $params = ['codigo' => $codigo];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }
    
    /**
     * Verifica se email já existe
     */
    public function emailExists($email, $excludeId = null)
    {
        $sql = "SELECT id FROM professores WHERE email = :email";
        $params = ['email' => $email];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }
    
    /**
     * Alterna status do professor
     */
    public function toggleStatus($id)
    {
        $professor = $this->findById($id);
        if (!$professor) {
            throw new Exception('Professor não encontrado');
        }
        
        $novoStatus = $professor['ativo'] ? 0 : 1;
        
        return $this->db->update(
            "UPDATE professores SET ativo = :ativo WHERE id = :id",
            ['ativo' => $novoStatus, 'id' => $id]
        );
    }
    
    /**
     * Conta total de professores
     */
    public function count()
    {
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM professores");
        return $result['total'];
    }
    
    /**
     * Conta professores ativos
     */
    public function countActive()
    {
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM professores WHERE ativo = 1");
        return $result['total'];
    }
    
    /**
     * Busca matérias do professor
     */
    public function getSubjects($id)
    {
        $professor = $this->findById($id);
        if (!$professor) {
            return [];
        }
        
        $materias = json_decode($professor['materias'], true);
        return $materias ?: [];
    }
    
    /**
     * Atualiza matérias do professor
     */
    public function updateSubjects($id, $materias)
    {
        return $this->db->update(
            "UPDATE professores SET materias = :materias WHERE id = :id",
            [
                'materias' => json_encode($materias),
                'id' => $id
            ]
        );
    }
    
    /**
     * Busca professores por matéria
     */
    public function getBySubject($materia)
    {
        return $this->db->fetchAll(
            "SELECT * FROM professores 
             WHERE ativo = 1 AND JSON_CONTAINS(materias, :materia)
             ORDER BY nome ASC",
            ['materia' => json_encode($materia)]
        );
    }
    
    /**
     * Busca todas as matérias disponíveis no banco
     */
    public function getAllAvailableSubjects()
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT nome FROM materias ORDER BY nome ASC"
        );
    }
    
    /**
     * Busca matérias disponíveis formatadas para formulários
     */
    public function getSubjectsForForm()
    {
        $materias = $this->getAllAvailableSubjects();
        return array_column($materias, 'nome');
    }
    
    /**
     * Gera código único para professor
     */
    public function generateCode()
    {
        do {
            $codigo = 'PROF' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->codeExists($codigo));
        
        return $codigo;
    }
    
    /**
     * Autentica professor
     */
    public function authenticate($codigo, $senha)
    {
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE codigo_prof = :codigo",
            ['codigo' => $codigo]
        );
        
        if ($professor && password_verify($senha, $professor['senha_hash'])) {
            return $professor;
        }
        
        return false;
    }
}
