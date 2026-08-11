<?php
/**
 * EducaTudo - Modelo de Provas de Professores
 * Gerencia provas individuais criadas por professores dentro de blocos
 */

class TeacherExam
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Cria uma nova prova de professor
     */
    public function create($data)
    {
        $this->db->beginTransaction();
        
        try {
            $sql = "INSERT INTO provas_professores (
                        bloco_id, professor_id, materia_id, numero_questoes, status, travada
                    ) VALUES (
                        :bloco_id, :professor_id, :materia_id, :numero_questoes, :status, :travada
                    )";
            
            $id = $this->db->insert($sql, [
                'bloco_id' => $data['bloco_id'],
                'professor_id' => $data['professor_id'],
                'materia_id' => $data['materia_id'],
                'numero_questoes' => $data['numero_questoes'] ?? 0,
                'status' => $data['status'] ?? 'em_andamento',
                'travada' => $data['travada'] ?? 0
            ]);
            
            $this->db->commit();
            return $id;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Busca prova de professor por ID
     */
    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT pp.*, 
                    p.nome as professor_nome,
                    m.nome as materia_nome,
                    pb.titulo as bloco_titulo,
                    pb.status as bloco_status,
                    pb.prazo_entrega_professor
             FROM provas_professores pp
             LEFT JOIN professores p ON pp.professor_id = p.id
             LEFT JOIN materias m ON pp.materia_id = m.id
             LEFT JOIN provas_blocos pb ON pp.bloco_id = pb.id
             WHERE pp.id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Busca todas as provas de professor de um bloco
     */
    public function findByBloco($blocoId, $filters = [])
    {
        $sql = "SELECT pp.*, 
                    p.nome as professor_nome,
                    m.nome as materia_nome,
                    pr.titulo as prova_titulo,
                    pr.status as prova_status
             FROM provas_professores pp
             LEFT JOIN professores p ON pp.professor_id = p.id
             LEFT JOIN materias m ON pp.materia_id = m.id
             LEFT JOIN provas pr ON pp.prova_id = pr.id
             INNER JOIN provas_blocos pb ON pp.bloco_id = pb.id AND pb.deleted_at IS NULL
             WHERE pp.bloco_id = :bloco_id";
        
        $params = ['bloco_id' => $blocoId];
        
        // Filtros
        if (!empty($filters['status'])) {
            $sql .= " AND pp.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['professor_id'])) {
            $sql .= " AND pp.professor_id = :professor_id";
            $params['professor_id'] = $filters['professor_id'];
        }
        
        $sql .= " ORDER BY m.nome ASC, p.nome ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Busca provas de um professor
     */
    public function findByProfessor($professorId, $filters = [])
    {
        $sql = "SELECT pp.*, 
                    m.nome as materia_nome,
                    pb.titulo as bloco_titulo,
                    pb.status as bloco_status,
                    pb.prazo_entrega_professor,
                    pr.titulo as prova_titulo
             FROM provas_professores pp
             LEFT JOIN materias m ON pp.materia_id = m.id
             LEFT JOIN provas_blocos pb ON pp.bloco_id = pb.id
             LEFT JOIN provas pr ON pp.prova_id = pr.id
             WHERE pp.professor_id = :professor_id
             AND (pb.deleted_at IS NULL)";
        
        $params = ['professor_id' => $professorId];
        
        // Filtros
        if (!empty($filters['status'])) {
            $sql .= " AND pp.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['bloco_id'])) {
            $sql .= " AND pp.bloco_id = :bloco_id";
            $params['bloco_id'] = $filters['bloco_id'];
        }
        
        $sql .= " ORDER BY pb.data_prova DESC, pb.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Atualiza status da prova
     */
    public function updateStatus($id, $status, $data = [])
    {
        $sql = "UPDATE provas_professores SET status = :status";
        $params = ['id' => $id, 'status' => $status];
        
        if (isset($data['data_envio'])) {
            $sql .= ", data_envio = :data_envio";
            $params['data_envio'] = $data['data_envio'];
        }
        
        if (isset($data['travada'])) {
            $sql .= ", travada = :travada";
            $params['travada'] = $data['travada'];
        }
        
        if (isset($data['prova_id'])) {
            $sql .= ", prova_id = :prova_id";
            $params['prova_id'] = $data['prova_id'];
        }
        
        $sql .= " WHERE id = :id";
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Vincula uma prova criada pelo professor
     */
    public function vincularProva($id, $provaId)
    {
        return $this->db->query(
            "UPDATE provas_professores SET prova_id = :prova_id WHERE id = :id",
            ['id' => $id, 'prova_id' => $provaId]
        );
    }
    
    /**
     * Verifica se pode editar
     */
    public function canEdit($id, $professorId)
    {
        $prova = $this->findById($id);
        
        if (!$prova) {
            return false;
        }
        
        // Só pode editar se for o professor dono
        if ($prova['professor_id'] != $professorId) {
            return false;
        }
        
        // Só pode editar se status = em_andamento e não estiver travada
        if ($prova['status'] !== 'em_andamento' || $prova['travada']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verifica prazos expirados e atualiza status
     */
    public function verificarPrazosExpirados()
    {
        $now = date('Y-m-d H:i:s');
        
        // Busca provas em_andamento cujo prazo expirou
        $provasExpiradas = $this->db->fetchAll(
            "SELECT pp.* 
             FROM provas_professores pp
             INNER JOIN provas_blocos pb ON pp.bloco_id = pb.id
             WHERE pp.status = 'em_andamento'
             AND pb.prazo_entrega_professor IS NOT NULL
             AND pb.prazo_entrega_professor < :now",
            ['now' => $now]
        );
        
        $atualizadas = 0;
        
        foreach ($provasExpiradas as $prova) {
            $this->updateStatus($prova['id'], 'nao_enviada', [
                'travada' => 1
            ]);
            $atualizadas++;
        }
        
        return $atualizadas;
    }
    
    /**
     * Conta provas por status em um bloco
     */
    public function contarPorStatus($blocoId)
    {
        $result = $this->db->fetchAll(
            "SELECT status, COUNT(*) as total
             FROM provas_professores
             WHERE bloco_id = :bloco_id
             GROUP BY status",
            ['bloco_id' => $blocoId]
        );
        
        $contagem = [
            'em_andamento' => 0,
            'enviada' => 0,
            'nao_enviada' => 0,
            'aprovada' => 0,
            'reprovada' => 0
        ];
        
        foreach ($result as $row) {
            $contagem[$row['status']] = (int)$row['total'];
        }
        
        return $contagem;
    }
}

