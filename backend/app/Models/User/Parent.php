<?php
/**
 * EducaTudo - Modelo de Pais
 * Gerencia operações de banco de dados para responsaveis/responsáveis
 */

class ParentModel
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Busca todos os responsaveis
     */
    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT * FROM responsaveis ORDER BY nome ASC"
        );
    }
    
    /**
     * Busca responsaveis ativos
     */
    public function getActive()
    {
        return $this->db->fetchAll(
            "SELECT * FROM responsaveis WHERE ativo = 1 ORDER BY nome ASC"
        );
    }
    
    /**
     * Busca pai por ID
     */
    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT * FROM responsaveis WHERE id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Busca pai por CPF
     */
    public function findByCPF($cpf)
    {
        return $this->db->fetch(
            "SELECT * FROM responsaveis WHERE cpf = :cpf",
            ['cpf' => $cpf]
        );
    }
    
    /**
     * Cria novo pai
     */
    public function create($data)
    {
        $sql = "INSERT INTO responsaveis (nome, email, senha_hash, force_password_change, cpf, telefone, ativo) 
                VALUES (:nome, :email, :senha_hash, :force_password_change, :cpf, :telefone, :ativo)";
        
        return $this->db->insert($sql, [
            'nome' => $data['nome'],
            'email' => $data['email'] ?: null,
            'senha_hash' => password_hash($data['senha'], PASSWORD_DEFAULT),
            'force_password_change' => isset($data['force_password_change']) ? (int)$data['force_password_change'] : 0,
            'cpf' => $data['cpf'] ?: null,
            'telefone' => $data['telefone'] ?: null,
            'ativo' => $data['ativo'] ?? 1
        ]);
    }
    
    /**
     * Atualiza pai
     */
    public function update($id, $data)
    {
        $sql = "UPDATE responsaveis SET nome = :nome, email = :email, cpf = :cpf, 
                telefone = :telefone, ativo = :ativo";
        
        $params = [
            'nome' => $data['nome'],
            'email' => $data['email'] ?: null,
            'cpf' => $data['cpf'] ?: null,
            'telefone' => $data['telefone'] ?: null,
            'ativo' => $data['ativo'] ?? 1,
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
     * Exclui pai
     */
    public function delete($id)
    {
        return $this->db->delete("DELETE FROM responsaveis WHERE id = :id", ['id' => $id]);
    }
    
    /**
     * Verifica se pai existe
     */
    public function exists($id)
    {
        $result = $this->db->fetch("SELECT id FROM responsaveis WHERE id = :id", ['id' => $id]);
        return $result !== false;
    }
    
    /**
     * Verifica se CPF já existe
     */
    public function cpfExists($cpf, $excludeId = null)
    {
        $sql = "SELECT id FROM responsaveis WHERE cpf = :cpf";
        $params = ['cpf' => $cpf];
        
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
        $sql = "SELECT id FROM responsaveis WHERE email = :email";
        $params = ['email' => $email];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }
    
    /**
     * Alterna status do pai
     */
    public function toggleStatus($id)
    {
        $pai = $this->findById($id);
        if (!$pai) {
            throw new Exception('Pai não encontrado');
        }
        
        $novoStatus = $pai['ativo'] ? 0 : 1;
        
        return $this->db->update(
            "UPDATE responsaveis SET ativo = :ativo WHERE id = :id",
            ['ativo' => $novoStatus, 'id' => $id]
        );
    }
    
    /**
     * Conta total de responsaveis
     */
    public function count()
    {
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM responsaveis");
        return $result['total'];
    }
    
    /**
     * Conta responsaveis ativos
     */
    public function countActive()
    {
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM responsaveis WHERE ativo = 1");
        return $result['total'];
    }
    
    /**
     * Busca filhos do pai
     */
    public function getChildren($paiId)
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT a.*, t.nome as turma_nome, t.serie as turma_serie
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             LEFT JOIN alunos_responsaveis ar ON ar.aluno_id = a.id AND ar.ativo = 1
             WHERE (a.responsavel_id = :pai_id OR ar.responsavel_id = :pai_id) AND a.ativo = 1
             ORDER BY a.nome ASC",
            ['pai_id' => $paiId]
        );
    }
    
    /**
     * Autentica pai
     */
    public function authenticate($email, $senha)
    {
        $pai = $this->db->fetch(
            "SELECT * FROM responsaveis WHERE email = :email",
            ['email' => $email]
        );
        
        if ($pai && password_verify($senha, $pai['senha_hash'])) {
            return $pai;
        }
        
        return false;
    }
}
