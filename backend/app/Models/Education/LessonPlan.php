<?php
/**
 * EducaTudo - Modelo de Planos de Aula
 * Gerencia operações de banco de dados para planos de aula
 */

class LessonPlan
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Busca todos os planos de aula (sem soft delete)
     */
    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT pa.*, 
                    p.nome as professor_nome,
                    m.nome as materia_nome,
                    t.nome as turma_nome
             FROM planos_aula pa
             LEFT JOIN professores p ON pa.professor_id = p.id
             LEFT JOIN materias m ON pa.materia_id = m.id
             LEFT JOIN turmas t ON pa.turma_id = t.id
             WHERE pa.deleted_at IS NULL
             ORDER BY pa.created_at DESC"
        );
    }
    
    /**
     * Busca planos de aula por professor
     */
    public function findByProfessor($professorId)
    {
        try {
            $result = $this->db->fetchAll(
                "SELECT pa.*, 
                        m.nome as materia_nome,
                        t.nome as turma_nome
                 FROM planos_aula pa
                 LEFT JOIN materias m ON pa.materia_id = m.id
                 LEFT JOIN turmas t ON pa.turma_id = t.id
                 WHERE pa.professor_id = :professor_id 
                 AND pa.deleted_at IS NULL
                 ORDER BY pa.created_at DESC",
                ['professor_id' => $professorId]
            );
            
            // Garantir que sempre retorna um array
            if (!is_array($result)) {
                error_log("PlanoAula::findByProfessor retornou não-array: " . gettype($result) . " - " . var_export($result, true));
                return [];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Erro em PlanoAula::findByProfessor: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca plano de aula por ID
     */
    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT pa.*, 
                    p.nome as professor_nome,
                    p.id as professor_id,
                    m.nome as materia_nome,
                    m.id as materia_id,
                    t.nome as turma_nome,
                    t.id as turma_id
             FROM planos_aula pa
             LEFT JOIN professores p ON pa.professor_id = p.id
             LEFT JOIN materias m ON pa.materia_id = m.id
             LEFT JOIN turmas t ON pa.turma_id = t.id
             WHERE pa.id = :id 
             AND pa.deleted_at IS NULL",
            ['id' => $id]
        );
    }
    
    /**
     * Busca planos por status
     */
    public function findByStatus($status)
    {
        return $this->db->fetchAll(
            "SELECT pa.*, 
                    p.nome as professor_nome,
                    m.nome as materia_nome,
                    t.nome as turma_nome
             FROM planos_aula pa
             LEFT JOIN professores p ON pa.professor_id = p.id
             LEFT JOIN materias m ON pa.materia_id = m.id
             LEFT JOIN turmas t ON pa.turma_id = t.id
             WHERE pa.status = :status 
             AND pa.deleted_at IS NULL
             ORDER BY pa.created_at DESC",
            ['status' => $status]
        );
    }
    
    /**
     * Busca planos por turma
     */
    public function findByTurma($turmaId)
    {
        return $this->db->fetchAll(
            "SELECT pa.*, 
                    p.nome as professor_nome,
                    m.nome as materia_nome
             FROM planos_aula pa
             LEFT JOIN professores p ON pa.professor_id = p.id
             LEFT JOIN materias m ON pa.materia_id = m.id
             WHERE pa.turma_id = :turma_id 
             AND pa.deleted_at IS NULL
             ORDER BY pa.created_at DESC",
            ['turma_id' => $turmaId]
        );
    }
    
    /**
     * Busca planos por matéria
     */
    public function findByMateria($materiaId)
    {
        return $this->db->fetchAll(
            "SELECT pa.*, 
                    p.nome as professor_nome,
                    t.nome as turma_nome
             FROM planos_aula pa
             LEFT JOIN professores p ON pa.professor_id = p.id
             LEFT JOIN turmas t ON pa.turma_id = t.id
             WHERE pa.materia_id = :materia_id 
             AND pa.deleted_at IS NULL
             ORDER BY pa.created_at DESC",
            ['materia_id' => $materiaId]
        );
    }
    
    /**
     * Cria novo plano de aula
     */
    public function create($data)
    {
        $sql = "INSERT INTO planos_aula (
                    professor_id, materia_id, turma_id, data_aula,
                    titulo, ano_disciplina, modulo, aula_num, paginas,
                    conteudo, conteudo_lista, objetivos, objetivos_lista,
                    metodologia, periodo_tarde_tema, periodo_tarde_exercicios,
                    recursos, recursos_lista, aulas_tarde_oficinas, avaliacao, avaliacao_apostila,
                    avaliacao_conteudo, avaliacao_paginas, observacoes, contexto_llm, status
                ) VALUES (
                    :professor_id, :materia_id, :turma_id, :data_aula,
                    :titulo, :ano_disciplina, :modulo, :aula_num, :paginas,
                    :conteudo, :conteudo_lista, :objetivos, :objetivos_lista,
                    :metodologia, :periodo_tarde_tema, :periodo_tarde_exercicios,
                    :recursos, :recursos_lista, :aulas_tarde_oficinas, :avaliacao, :avaliacao_apostila,
                    :avaliacao_conteudo, :avaliacao_paginas, :observacoes, :contexto_llm, :status
                )";
        
        return $this->db->insert($sql, [
            'professor_id' => $data['professor_id'],
            'materia_id' => $data['materia_id'],
            'turma_id' => $data['turma_id'],
            'data_aula' => $data['data_aula'],
            'titulo' => $data['titulo'],
            'ano_disciplina' => $data['ano_disciplina'] ?? null,
            'modulo' => $data['modulo'] ?? null,
            'aula_num' => $data['aula_num'] ?? null,
            'paginas' => $data['paginas'] ?? null,
            'conteudo' => $data['conteudo'] ?? null,
            'conteudo_lista' => $data['conteudo_lista'] ?? null,
            'objetivos' => $data['objetivos'] ?? null,
            'objetivos_lista' => $data['objetivos_lista'] ?? null,
            'metodologia' => $data['metodologia'] ?? null,
            'periodo_tarde_tema' => $data['periodo_tarde_tema'] ?? null,
            'periodo_tarde_exercicios' => $data['periodo_tarde_exercicios'] ?? null,
            'recursos' => $data['recursos'] ?? null,
            'recursos_lista' => isset($data['recursos_lista']) ? json_encode($data['recursos_lista']) : null,
            'aulas_tarde_oficinas' => $data['aulas_tarde_oficinas'] ?? null,
            'avaliacao' => $data['avaliacao'] ?? null,
            'avaliacao_apostila' => $data['avaliacao_apostila'] ?? null,
            'avaliacao_conteudo' => $data['avaliacao_conteudo'] ?? null,
            'avaliacao_paginas' => $data['avaliacao_paginas'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'contexto_llm' => $data['contexto_llm'] ?? null,
            'status' => $data['status'] ?? 'rascunho'
        ]);
    }
    
    /**
     * Atualiza plano de aula
     */
    public function update($id, $data)
    {
        $sql = "UPDATE planos_aula SET
                    materia_id = :materia_id,
                    turma_id = :turma_id,
                    data_aula = :data_aula,
                    titulo = :titulo,
                    ano_disciplina = :ano_disciplina,
                    modulo = :modulo,
                    aula_num = :aula_num,
                    paginas = :paginas,
                    conteudo = :conteudo,
                    conteudo_lista = :conteudo_lista,
                    objetivos = :objetivos,
                    objetivos_lista = :objetivos_lista,
                    metodologia = :metodologia,
                    periodo_tarde_tema = :periodo_tarde_tema,
                    periodo_tarde_exercicios = :periodo_tarde_exercicios,
                    recursos = :recursos,
                    recursos_lista = :recursos_lista,
                    aulas_tarde_oficinas = :aulas_tarde_oficinas,
                    avaliacao = :avaliacao,
                    avaliacao_apostila = :avaliacao_apostila,
                    avaliacao_conteudo = :avaliacao_conteudo,
                    avaliacao_paginas = :avaliacao_paginas,
                    observacoes = :observacoes,
                    contexto_llm = :contexto_llm,
                    status = :status
                WHERE id = :id 
                AND deleted_at IS NULL";
        
        return $this->db->update($sql, [
            'materia_id' => $data['materia_id'],
            'turma_id' => $data['turma_id'],
            'data_aula' => $data['data_aula'],
            'titulo' => $data['titulo'],
            'ano_disciplina' => $data['ano_disciplina'] ?? null,
            'modulo' => $data['modulo'] ?? null,
            'aula_num' => $data['aula_num'] ?? null,
            'paginas' => $data['paginas'] ?? null,
            'conteudo' => $data['conteudo'] ?? null,
            'conteudo_lista' => $data['conteudo_lista'] ?? null,
            'objetivos' => $data['objetivos'] ?? null,
            'objetivos_lista' => $data['objetivos_lista'] ?? null,
            'metodologia' => $data['metodologia'] ?? null,
            'periodo_tarde_tema' => $data['periodo_tarde_tema'] ?? null,
            'periodo_tarde_exercicios' => $data['periodo_tarde_exercicios'] ?? null,
            'recursos' => $data['recursos'] ?? null,
            'recursos_lista' => isset($data['recursos_lista']) ? json_encode($data['recursos_lista']) : null,
            'aulas_tarde_oficinas' => $data['aulas_tarde_oficinas'] ?? null,
            'avaliacao' => $data['avaliacao'] ?? null,
            'avaliacao_apostila' => $data['avaliacao_apostila'] ?? null,
            'avaliacao_conteudo' => $data['avaliacao_conteudo'] ?? null,
            'avaliacao_paginas' => $data['avaliacao_paginas'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'contexto_llm' => $data['contexto_llm'] ?? null,
            'status' => $data['status'] ?? 'rascunho',
            'id' => $id
        ]);
    }
    
    /**
     * Atualiza status do plano
     */
    public function updateStatus($id, $status)
    {
        return $this->db->update(
            "UPDATE planos_aula SET status = :status WHERE id = :id AND deleted_at IS NULL",
            ['status' => $status, 'id' => $id]
        );
    }
    
    /**
     * Soft delete - marca como excluído
     */
    public function delete($id)
    {
        return $this->db->update(
            "UPDATE planos_aula SET deleted_at = NOW() WHERE id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Restaura plano excluído
     */
    public function restore($id)
    {
        return $this->db->update(
            "UPDATE planos_aula SET deleted_at = NULL WHERE id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Verifica se plano existe
     */
    public function exists($id)
    {
        $result = $this->db->fetch(
            "SELECT id FROM planos_aula WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
        return $result !== false;
    }
    
    /**
     * Verifica se professor pode editar o plano
     */
    public function canEdit($id, $professorId)
    {
        $plano = $this->findById($id);
        if (!$plano) {
            return false;
        }
        
        // Só pode editar se for o dono e estiver em rascunho
        return $plano['professor_id'] == $professorId && $plano['status'] == 'rascunho';
    }
    
    /**
     * Conta total de planos
     */
    public function count()
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total FROM planos_aula WHERE deleted_at IS NULL"
        );
        return $result['total'];
    }
    
    /**
     * Conta planos por status
     */
    public function countByStatus($status)
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total FROM planos_aula WHERE status = :status AND deleted_at IS NULL",
            ['status' => $status]
        );
        return $result['total'];
    }
    
    /**
     * Conta planos por professor
     */
    public function countByProfessor($professorId)
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total FROM planos_aula WHERE professor_id = :professor_id AND deleted_at IS NULL",
            ['professor_id' => $professorId]
        );
        return $result['total'];
    }
}
