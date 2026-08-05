<?php
/**
 * Model para Destinatários de Notificações
 */
class NotificationRecipient
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Adicionar destinatário à notificação
     */
    public function addDestinatario($notificacaoId, $tipoDestinatario, $destinatarioId = null, $turmaId = null)
    {
        $sql = "INSERT INTO notificacoes_destinatarios 
                (notificacao_id, tipo_destinatario, destinatario_id, turma_id) 
                VALUES (?, ?, ?, ?)";
        
        $params = [$notificacaoId, $tipoDestinatario, $destinatarioId, $turmaId];
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Adicionar múltiplos destinatários
     */
    public function addDestinatarios($notificacaoId, $destinatarios)
    {
        $sql = "INSERT INTO notificacoes_destinatarios 
                (notificacao_id, tipo_destinatario, destinatario_id, turma_id) 
                VALUES (?, ?, ?, ?)";
        
        $this->db->beginTransaction();
        
        try {
            foreach ($destinatarios as $destinatario) {
                $params = [
                    $notificacaoId,
                    $destinatario['tipo_destinatario'],
                    $destinatario['destinatario_id'] ?? null,
                    $destinatario['turma_id'] ?? null
                ];
                $this->db->insert($sql, $params);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * Buscar destinatários de uma notificação (apenas individuais)
     */
    public function getByNotificacao($notificacaoId)
    {
        $sql = "SELECT nd.*, 
                       CASE 
                           WHEN nd.tipo_destinatario = 'usuarios' THEN u.nome
                           WHEN nd.tipo_destinatario = 'professores' THEN p.nome
                           WHEN nd.tipo_destinatario = 'alunos' THEN a.nome
                           WHEN nd.tipo_destinatario = 'pais' THEN pai.nome
                           WHEN nd.tipo_destinatario = 'turma' THEN t.nome
                           ELSE 'Todos'
                       END as nome_destinatario
                FROM notificacoes_destinatarios nd
                LEFT JOIN usuarios u ON nd.destinatario_id = u.id AND nd.tipo_destinatario = 'usuarios'
                LEFT JOIN professores p ON nd.destinatario_id = p.id AND nd.tipo_destinatario = 'professores'
                LEFT JOIN alunos a ON nd.destinatario_id = a.id AND nd.tipo_destinatario = 'alunos'
                LEFT JOIN responsaveis pai ON nd.destinatario_id = pai.id AND nd.tipo_destinatario = 'pais'
                LEFT JOIN turmas t ON nd.turma_id = t.id AND nd.tipo_destinatario = 'turma'
                WHERE nd.notificacao_id = ?
                AND nd.destinatario_id IS NOT NULL
                AND nd.tipo_destinatario NOT IN ('todos', 'todos_alunos', 'todos_professores', 'todos_admins', 'todos_pais')
                ORDER BY nd.tipo_destinatario, nd.destinatario_id";
        
        return $this->db->fetchAll($sql, [$notificacaoId]);
    }
    
    /**
     * Processar destinatários baseado na seleção
     */
    public function processarDestinatarios($dados)
    {
        $destinatarios = [];
        
        // Todos os usuários
        if (isset($dados['destinatario_todos']) && $dados['destinatario_todos']) {
            $destinatarios[] = ['tipo_destinatario' => 'todos'];
        }
        
        // Usuários específicos
        if (isset($dados['usuarios']) && is_array($dados['usuarios'])) {
            foreach ($dados['usuarios'] as $usuarioId) {
                $destinatarios[] = [
                    'tipo_destinatario' => 'usuarios',
                    'destinatario_id' => $usuarioId
                ];
            }
        }
        
        // Professores específicos
        if (isset($dados['professores']) && is_array($dados['professores'])) {
            foreach ($dados['professores'] as $professorId) {
                $destinatarios[] = [
                    'tipo_destinatario' => 'professores',
                    'destinatario_id' => $professorId
                ];
            }
        }
        
        // Alunos específicos
        if (isset($dados['alunos']) && is_array($dados['alunos'])) {
            foreach ($dados['alunos'] as $alunoId) {
                $destinatarios[] = [
                    'tipo_destinatario' => 'alunos',
                    'destinatario_id' => $alunoId
                ];
            }
        }
        
        // Pais específicos
        if (isset($dados['pais']) && is_array($dados['pais'])) {
            foreach ($dados['pais'] as $paiId) {
                $destinatarios[] = [
                    'tipo_destinatario' => 'pais',
                    'destinatario_id' => $paiId
                ];
            }
        }
        
        // Turma específica
        if (isset($dados['turma_id']) && $dados['turma_id']) {
            $destinatarios[] = [
                'tipo_destinatario' => 'turma',
                'turma_id' => $dados['turma_id']
            ];
        }
        
        return $destinatarios;
    }
    
    /**
     * Buscar estatísticas de entrega
     */
    public function getEstatisticasEntrega($notificacaoId)
    {
        $sql = "SELECT 
                    COUNT(*) as total_destinatarios,
                    COUNT(CASE WHEN lida = 1 THEN 1 END) as total_lidas,
                    COUNT(CASE WHEN visualizada_em IS NOT NULL THEN 1 END) as total_visualizadas,
                    ROUND((COUNT(CASE WHEN lida = 1 THEN 1 END) / COUNT(*)) * 100, 2) as percentual_lidas
                FROM notificacoes_destinatarios 
                WHERE notificacao_id = ?";
        
        return $this->db->fetch($sql, [$notificacaoId]);
    }
}
