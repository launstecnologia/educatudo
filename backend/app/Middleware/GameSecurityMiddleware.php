<?php
/**
 * Middleware de Segurança para Jogos
 * Previne trapaças e abusos no sistema de jogos
 */

class GameSecurityMiddleware
{
    private $db;
    private $alunoId;
    private $partidaId;
    
    // Tempo mínimo entre ações (em segundos)
    private const MIN_TIME_BETWEEN_ACTIONS = 2;
    
    // Tempo máximo de inatividade (em segundos)
    private const MAX_INACTIVITY_TIME = 300; // 5 minutos
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Valida se uma ação de jogo é legítima
     */
    public function validateGameAction($alunoId, $partidaId, $action)
    {
        // 1. Verificar se a partida existe e pertence ao aluno
        $partida = $this->db->fetch(
            "SELECT * FROM jogos_milhao_partidas WHERE id = :partida_id AND aluno_id = :aluno_id AND status = 'em_andamento'",
            ['partida_id' => $partidaId, 'aluno_id' => $alunoId]
        );
        
        if (!$partida) {
            error_log("SECURITY: Partida inválida para aluno $alunoId, partida $partidaId");
            return ['valid' => false, 'error' => 'Partida inválida ou finalizada'];
        }
        
        // 2. Verificar tempo entre ações
        $lastAction = $this->getLastActionTime($partidaId, $alunoId);
        if ($lastAction && (time() - $lastAction) < self::MIN_TIME_BETWEEN_ACTIONS) {
            error_log("SECURITY: Ação muito rápida detectada para aluno $alunoId");
            return ['valid' => false, 'error' => 'Ação muito rápida. Aguarde alguns segundos.'];
        }
        
        // 3. Registrar hora da última ação
        $this->registerAction($partidaId, $alunoId, $action);
        
        // 4. Verificar inatividade
        if (!$this->checkInactivity($partidaId)) {
            error_log("SECURITY: Inatividade detectada para partida $partidaId");
            return ['valid' => false, 'error' => 'Partida pausada devido a inatividade'];
        }
        
        // 5. Atualizar timestamp de atividade
        $this->updateActivityTimestamp($partidaId);
        
        return ['valid' => true];
    }
    
    /**
     * Gera token de segurança para a sessão de jogo
     */
    public function generateSessionToken($alunoId, $partidaId)
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+2 hours'));
        
        $this->db->insert(
            "INSERT INTO jogos_sessoes (aluno_id, partida_id, token, expires_at, created_at) 
             VALUES (:aluno_id, :partida_id, :token, :expires, NOW())
             ON DUPLICATE KEY UPDATE token = :token, expires_at = :expires",
            [
                'aluno_id' => $alunoId,
                'partida_id' => $partidaId,
                'token' => $token,
                'expires' => $expires
            ]
        );
        
        return $token;
    }
    
    /**
     * Valida token de sessão
     */
    public function validateSessionToken($token)
    {
        $session = $this->db->fetch(
            "SELECT * FROM jogos_sessoes WHERE token = :token AND expires_at > NOW()",
            ['token' => $token]
        );
        
        if (!$session) {
            return ['valid' => false, 'error' => 'Token de sessão inválido ou expirado'];
        }
        
        return ['valid' => true, 'session' => $session];
    }
    
    /**
     * Bloqueia múltiplas instâncias do jogo
     */
    public function checkMultipleInstances($alunoId)
    {
        $activeGames = $this->db->fetchAll(
            "SELECT id FROM jogos_milhao_partidas 
             WHERE aluno_id = :aluno_id AND status = 'em_andamento'",
            ['aluno_id' => $alunoId]
        );
        
        if (count($activeGames) > 1) {
            error_log("SECURITY: Múltiplas instâncias detectadas para aluno $alunoId");
            // Fechar instâncias antigas, manter apenas a mais recente
            $this->db->query(
                "UPDATE jogos_milhao_partidas 
                 SET status = 'abandonado' 
                 WHERE aluno_id = :aluno_id AND status = 'em_andamento' AND id != (
                     SELECT id FROM (
                         SELECT id FROM jogos_milhao_partidas 
                         WHERE aluno_id = :aluno_id AND status = 'em_andamento' 
                         ORDER BY data_inicio DESC LIMIT 1
                     ) AS subquery
                 )",
                ['aluno_id' => $alunoId]
            );
        }
    }
    
    /**
     * Verifica tentativa de resposta duplicada
     */
    public function checkDuplicateAnswer($partidaId, $perguntaId)
    {
        $respostaExistente = $this->db->fetch(
            "SELECT * FROM jogos_milhao_respostas 
             WHERE partida_id = :partida_id AND pergunta_id = :pergunta_id 
             LIMIT 1",
            ['partida_id' => $partidaId, 'pergunta_id' => $perguntaId]
        );
        
        return $respostaExistente !== false;
    }
    
    /**
     * Obtém hora da última ação
     */
    private function getLastActionTime($partidaId, $alunoId)
    {
        $action = $this->db->fetch(
            "SELECT timestamp FROM jogos_acoes 
             WHERE partida_id = :partida_id AND aluno_id = :aluno_id 
             ORDER BY timestamp DESC LIMIT 1",
            ['partida_id' => $partidaId, 'aluno_id' => $alunoId]
        );
        
        return $action ? strtotime($action['timestamp']) : null;
    }
    
    /**
     * Registra ação do jogador
     */
    private function registerAction($partidaId, $alunoId, $action)
    {
        try {
            $this->db->insert(
                "INSERT INTO jogos_acoes (partida_id, aluno_id, action, timestamp) 
                 VALUES (:partida_id, :aluno_id, :action, NOW())",
                [
                    'partida_id' => $partidaId,
                    'aluno_id' => $alunoId,
                    'action' => $action
                ]
            );
        } catch (Exception $e) {
            // Tabela pode não existir ainda
            error_log("Erro ao registrar ação: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica inatividade
     */
    private function checkInactivity($partidaId)
    {
        $partida = $this->db->fetch(
            "SELECT last_activity FROM jogos_milhao_partidas WHERE id = :partida_id",
            ['partida_id' => $partidaId]
        );
        
        if (!$partida || !$partida['last_activity']) {
            return true;
        }
        
        $timeSinceActivity = time() - strtotime($partida['last_activity']);
        return $timeSinceActivity < self::MAX_INACTIVITY_TIME;
    }
    
    /**
     * Atualiza timestamp de atividade
     */
    private function updateActivityTimestamp($partidaId)
    {
        $this->db->update(
            "UPDATE jogos_milhao_partidas SET last_activity = NOW() WHERE id = :partida_id",
            ['partida_id' => $partidaId]
        );
    }
    
    /**
     * Valida pontuação enviada pelo cliente
     */
    public function validateScore($partidaId, $pontuacaoEnviada)
    {
        $partida = $this->db->fetch(
            "SELECT pontuacao_atual FROM jogos_milhao_partidas WHERE id = :partida_id",
            ['partida_id' => $partidaId]
        );
        
        if (!$partida) {
            return ['valid' => false, 'error' => 'Partida não encontrada'];
        }
        
        // Pontuação deve ser calculada no servidor, não aceitamos do cliente
        if ($partida['pontuacao_atual'] != $pontuacaoEnviada) {
            error_log("SECURITY: Pontuação alterada pelo cliente! Enviada: $pontuacaoEnviada, Real: {$partida['pontuacao_atual']}");
            return [
                'valid' => false, 
                'error' => 'Pontuação inválida',
                'correct_score' => $partida['pontuacao_atual']
            ];
        }
        
        return ['valid' => true];
    }
}

