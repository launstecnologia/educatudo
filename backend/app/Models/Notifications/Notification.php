<?php
/**
 * Model para Notificações
 */
require_once __DIR__ . '/../../Core/Database.php';

class Notification
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Buscar notificação por ID
     */
    public function getById($id)
    {
        $sql = "SELECT n.*, 
                       CASE 
                           WHEN n.tipo_enviador = 'admin' THEN u.nome
                           WHEN n.tipo_enviador = 'professor' THEN p.nome
                       END as nome_enviador
                FROM notificacoes n
                LEFT JOIN usuarios u ON n.enviado_por = u.id AND n.tipo_enviador = 'admin'
                LEFT JOIN professores p ON n.enviado_por = p.id AND n.tipo_enviador = 'professor'
                WHERE n.id = ? AND n.ativo = 1";
        
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * Criar nova notificação
     */
    public function create($data)
    {
        $sql = "INSERT INTO notificacoes (
            tipo_conteudo, titulo, conteudo, arquivo_url, arquivo_nome, arquivo_tamanho,
            enviado_por, tipo_enviador, perfil_enviador, prioridade, data_envio, data_expiracao,
            tipos_conteudo, is_update, video_url
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['tipo_conteudo'],
            $data['titulo'],
            $data['conteudo'],
            $data['arquivo_url'] ?? null,
            $data['arquivo_nome'] ?? null,
            $data['arquivo_tamanho'] ?? null,
            $data['enviado_por'],
            $data['tipo_enviador'],
            $data['perfil_enviador'],
            $data['prioridade'] ?? 'normal',
            $data['data_envio'] ?? date('Y-m-d H:i:s'),
            $data['data_expiracao'] ?? null,
            $data['tipos_conteudo'] ?? null,
            $data['is_update'] ?? 0,
            $data['video_url'] ?? null
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Buscar notificações por destinatário
     */
    public function getByDestinatario($usuarioId, $tipoUsuario, $limit = 10)
    {
        $sql = "SELECT DISTINCT n.*, nd.lida, nd.lida_em, nd.visualizada_em,
                       CASE 
                           WHEN n.tipo_enviador = 'admin' THEN u.nome
                           WHEN n.tipo_enviador = 'professor' THEN p.nome
                       END as nome_enviador
                FROM notificacoes n
                LEFT JOIN notificacoes_destinatarios nd ON n.id = nd.notificacao_id
                LEFT JOIN usuarios u ON n.enviado_por = u.id AND n.tipo_enviador = 'admin'
                LEFT JOIN professores p ON n.enviado_por = p.id AND n.tipo_enviador = 'professor'
                WHERE n.ativo = 1 
                AND (n.data_expiracao IS NULL OR n.data_expiracao > NOW())
                AND (
                    nd.tipo_destinatario = 'todos' OR
                    (nd.tipo_destinatario = ? AND nd.destinatario_id = ?) OR
                    (nd.tipo_destinatario = 'turma' AND nd.turma_id IN (
                        SELECT turma_id FROM alunos WHERE id = ?
                    ))
                )
                ORDER BY n.prioridade DESC, n.created_at DESC
                LIMIT ?";
        
        $params = [$tipoUsuario, $usuarioId, $usuarioId, $limit];
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Buscar notificações não lidas
     */
    public function getNaoLidas($usuarioId, $tipoUsuario)
    {
        // Mapear tipo de usuário para tipo de destinatário
        $tipoDestinatario = $this->mapearTipoUsuario($tipoUsuario);
        
        $sql = "SELECT COUNT(DISTINCT n.id) as total
                FROM notificacoes n
                LEFT JOIN notificacoes_destinatarios nd ON n.id = nd.notificacao_id
                WHERE n.ativo = 1 
                AND (n.data_expiracao IS NULL OR n.data_expiracao > NOW())
                AND nd.lida = 0
                AND (
                    nd.tipo_destinatario = 'todos' OR
                    (nd.tipo_destinatario = ? AND nd.destinatario_id = ?) OR
                    (nd.tipo_destinatario = 'turma' AND nd.turma_id IN (
                        SELECT turma_id FROM alunos WHERE id = ?
                    ))
                )";
        
        $params = [$tipoDestinatario, $usuarioId, $usuarioId];
        $result = $this->db->fetch($sql, $params);
        return $result['total'] ?? 0;
    }
    
    /**
     * Marcar notificação como lida
     */
public function marcarComoLida($notificacaoId, $usuarioId, $tipoUsuario)
    {
        // Primeiro, verificar se a notificação existe
        $sql = "SELECT id FROM notificacoes WHERE id = ? AND ativo = 1";
        $notificacao = $this->db->fetch($sql, [$notificacaoId]);
        
        if (!$notificacao) {
            error_log("Notificação ID {$notificacaoId} não encontrada ou inativa");
            return false;
        }
        
        // Mapear tipo de usuário para tipo de destinatário
        $tipoDestinatario = $this->mapearTipoUsuario($tipoUsuario);
        
        // Primeiro, verificar se já existe um registro específico para este usuário
        $sql = "SELECT id FROM notificacoes_destinatarios 
                WHERE notificacao_id = ? 
                AND destinatario_id = ? 
                AND tipo_destinatario = ?";
        
        $params = [$notificacaoId, $usuarioId, $tipoDestinatario];
        $existing = $this->db->fetch($sql, $params);
        
        if ($existing) {
            // Atualizar registro existente
            $sql = "UPDATE notificacoes_destinatarios 
                    SET lida = 1, lida_em = NOW() 
                    WHERE notificacao_id = ? 
                    AND destinatario_id = ? 
                    AND tipo_destinatario = ?";
            
            $params = [$notificacaoId, $usuarioId, $tipoDestinatario];
            return $this->db->update($sql, $params);
        } else {
            // Criar novo registro específico para este usuário
            $sql = "INSERT INTO notificacoes_destinatarios 
                    (notificacao_id, tipo_destinatario, destinatario_id, lida, lida_em) 
                    VALUES (?, ?, ?, 1, NOW())";
            
            $params = [$notificacaoId, $tipoDestinatario, $usuarioId];
            return $this->db->insert($sql, $params);
        }
    }
    
    /**
     * Marcar notificação como visualizada
     */
    public function marcarComoVisualizada($notificacaoId, $usuarioId, $tipoUsuario)
    {
        // Primeiro, verificar se a notificação existe
        $sql = "SELECT id FROM notificacoes WHERE id = ? AND ativo = 1";
        $notificacao = $this->db->fetch($sql, [$notificacaoId]);
        
        if (!$notificacao) {
            error_log("Notificação ID {$notificacaoId} não encontrada ou inativa");
            return false;
        }
        
        // Mapear tipo de usuário para tipo de destinatário
        $tipoDestinatario = $this->mapearTipoUsuario($tipoUsuario);
        
        // Primeiro, verificar se já existe um registro específico para este usuário
        $sql = "SELECT id FROM notificacoes_destinatarios 
                WHERE notificacao_id = ? 
                AND destinatario_id = ? 
                AND tipo_destinatario = ?";
        
        $params = [$notificacaoId, $usuarioId, $tipoDestinatario];
        $existing = $this->db->fetch($sql, $params);
        
        if ($existing) {
            // Atualizar registro existente
            $sql = "UPDATE notificacoes_destinatarios 
                    SET visualizada_em = NOW() 
                    WHERE notificacao_id = ? 
                    AND destinatario_id = ? 
                    AND tipo_destinatario = ?";
            
            $params = [$notificacaoId, $usuarioId, $tipoDestinatario];
            return $this->db->update($sql, $params);
        } else {
            // Criar novo registro específico para este usuário
            $sql = "INSERT INTO notificacoes_destinatarios 
                    (notificacao_id, tipo_destinatario, destinatario_id, visualizada_em) 
                    VALUES (?, ?, ?, NOW())";
            
            $params = [$notificacaoId, $tipoDestinatario, $usuarioId];
            return $this->db->insert($sql, $params);
        }
    }
    
    /**
     * Buscar histórico de notificações
     */
    public function getHistorico($usuarioId, $tipoUsuario, $limit = 20)
    {
        // Mapear tipo de usuário para tipo de destinatário
        $tipoDestinatario = $this->mapearTipoUsuario($tipoUsuario);
        
        $sql = "SELECT DISTINCT n.*, nd.lida, nd.lida_em, nd.visualizada_em,
                       CASE 
                           WHEN n.tipo_enviador = 'admin' THEN u.nome
                           WHEN n.tipo_enviador = 'professor' THEN p.nome
                       END as nome_enviador
                FROM notificacoes n
                LEFT JOIN notificacoes_destinatarios nd ON n.id = nd.notificacao_id
                LEFT JOIN usuarios u ON n.enviado_por = u.id AND n.tipo_enviador = 'admin'
                LEFT JOIN professores p ON n.enviado_por = p.id AND n.tipo_enviador = 'professor'
                WHERE n.ativo = 1 
                AND (
                    nd.tipo_destinatario = 'todos' OR
                    (nd.tipo_destinatario = ? AND nd.destinatario_id = ?) OR
                    (nd.tipo_destinatario = 'turma' AND nd.turma_id IN (
                        SELECT turma_id FROM alunos WHERE id = ?
                    ))
                )
                ORDER BY n.created_at DESC
                LIMIT ?";
        
        $params = [$tipoDestinatario, $usuarioId, $usuarioId, $limit];
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Atualizar notificação
     */
    public function update($id, $data)
    {
        $sql = "UPDATE notificacoes SET 
                titulo = ?, conteudo = ?, arquivo_url = ?, arquivo_nome = ?, arquivo_tamanho = ?,
                prioridade = ?, data_expiracao = ?, ativo = ?, video_url = ?, updated_at = NOW()
                WHERE id = ?";
        
        $params = [
            $data['titulo'],
            $data['conteudo'],
            $data['arquivo_url'] ?? null,
            $data['arquivo_nome'] ?? null,
            $data['arquivo_tamanho'] ?? null,
            $data['prioridade'] ?? 'normal',
            $data['data_expiracao'] ?? null,
            $data['ativo'] ?? 1,
            $data['video_url'] ?? null,
            $id
        ];
        
        return $this->db->update($sql, $params);
    }
    
    /**
     * Buscar todas as notificações (admin)
     */
    public function getAll($limit = 50)
    {
        $sql = "SELECT n.*, 
                       CASE 
                           WHEN n.tipo_enviador = 'admin' THEN u.nome
                           WHEN n.tipo_enviador = 'professor' THEN p.nome
                       END as nome_enviador,
                       COUNT(nd.id) as total_destinatarios,
                       COUNT(CASE WHEN nd.lida = 1 THEN 1 END) as total_lidas
                FROM notificacoes n
                LEFT JOIN usuarios u ON n.enviado_por = u.id AND n.tipo_enviador = 'admin'
                LEFT JOIN professores p ON n.enviado_por = p.id AND n.tipo_enviador = 'professor'
                LEFT JOIN notificacoes_destinatarios nd ON n.id = nd.notificacao_id
                WHERE n.ativo = 1
                GROUP BY n.id
                ORDER BY n.created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Excluir notificação
     */
    public function delete($id)
    {
        $sql = "UPDATE notificacoes SET ativo = 0 WHERE id = ?";
        return $this->db->update($sql, [$id]);
    }
    
    /**
     * Mapear tipo de usuário do sistema de auth para tipo de destinatário do ENUM
     */
    public function mapearTipoUsuario($tipoUsuario)
    {
        switch ($tipoUsuario) {
            case 'aluno':
                return 'alunos';
            case 'professor':
                return 'professores';
            case 'pai':
                return 'pais';
            case 'admin':
            case 'admin_escola':
                return 'usuarios';
            default:
                return 'alunos'; // Default
        }
    }
}
