<?php
/**
 * Model para Notificações Push (OneSignal)
 * Tabelas: notificacoes_push, notificacoes_push_envios
 */
require_once __DIR__ . '/../../Core/Database.php';

class PushNotification
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Cria uma nova notificação push
     */
    public function create($titulo, $mensagem, $url, $tipoDestino, $destinoId, $criadoPor)
    {
        $sql = "INSERT INTO notificacoes_push (titulo, mensagem, url, tipo_destino, destino_id, criado_por)
                VALUES (:titulo, :mensagem, :url, :tipo_destino, :destino_id, :criado_por)";
        return (int) $this->db->insert($sql, [
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'url' => $url ?: null,
            'tipo_destino' => $tipoDestino,
            'destino_id' => $destinoId ?: null,
            'criado_por' => $criadoPor
        ]);
    }

    /**
     * Atualiza onesignal_id após envio
     */
    public function updateOnesignalId($id, $onesignalId)
    {
        $sql = "UPDATE notificacoes_push SET onesignal_id = :oid WHERE id = :id";
        return $this->db->update($sql, ['oid' => $onesignalId, 'id' => $id]);
    }

    /**
     * Lista notificações com totais
     */
    public function getAll($limit = 50)
    {
        $sql = "SELECT pn.*,
                (SELECT COUNT(*) FROM notificacoes_push_envios WHERE notificacao_id = pn.id) AS total_envios,
                (SELECT COUNT(*) FROM notificacoes_push_envios WHERE notificacao_id = pn.id AND entregue = 1) AS total_entregues,
                (SELECT COUNT(*) FROM notificacoes_push_envios WHERE notificacao_id = pn.id AND visualizado = 1) AS total_visualizados,
                (SELECT COUNT(*) FROM notificacoes_push_envios WHERE notificacao_id = pn.id AND clicado = 1) AS total_clicados
                FROM notificacoes_push pn
                ORDER BY pn.created_at DESC
                LIMIT " . (int) $limit;
        return $this->db->fetchAll($sql);
    }

    /**
     * Busca por ID
     */
    public function getById($id)
    {
        return $this->db->fetch("SELECT * FROM notificacoes_push WHERE id = :id", ['id' => $id]);
    }

    /**
     * Adiciona um destinatário ao envio (para tracking)
     */
    public function addEnvio($notificacaoId, $userId, $role)
    {
        $token = bin2hex(random_bytes(24));
        $sql = "INSERT INTO notificacoes_push_envios (notificacao_id, user_id, role, tracking_token)
                VALUES (:nid, :uid, :role, :token)
                ON DUPLICATE KEY UPDATE tracking_token = VALUES(tracking_token)";
        try {
            $this->db->query($sql, [
                'nid' => $notificacaoId,
                'uid' => $userId,
                'role' => $role,
                'token' => $token
            ]);
        } catch (Exception $e) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT id, tracking_token FROM notificacoes_push_envios WHERE notificacao_id = :nid AND role = :role AND user_id = :uid",
            ['nid' => $notificacaoId, 'role' => $role, 'uid' => $userId]
        );
        return $row ? $row['tracking_token'] : $token;
    }

    /**
     * Retorna envios com tracking_token para montar payload (por notificacao_id)
     */
    public function getEnviosComToken($notificacaoId)
    {
        return $this->db->fetchAll(
            "SELECT user_id, role, tracking_token FROM notificacoes_push_envios WHERE notificacao_id = :id",
            ['id' => $notificacaoId]
        );
    }

    /**
     * Marca entregue
     */
    public function marcarEntregue($notificacaoId)
    {
        $sql = "UPDATE notificacoes_push_envios SET entregue = 1, entregue_em = NOW() WHERE notificacao_id = :id";
        return $this->db->update($sql, ['id' => $notificacaoId]);
    }

    public function marcarEntregueDestinatario($notificacaoId, $userId, $role)
    {
        return $this->db->update(
            "UPDATE notificacoes_push_envios
             SET entregue = 1, entregue_em = COALESCE(entregue_em, NOW())
             WHERE notificacao_id = :notificacao_id AND user_id = :user_id AND role = :role",
            ['notificacao_id' => $notificacaoId, 'user_id' => $userId, 'role' => $role]
        );
    }

    /**
     * Marca visualizado por token
     */
    public function marcarVisualizadoPorToken($token)
    {
        $sql = "UPDATE notificacoes_push_envios SET visualizado = 1, visualizado_em = NOW() WHERE tracking_token = :token AND visualizado = 0";
        return $this->db->update($sql, ['token' => $token]);
    }

    /**
     * Marca clicado por token e retorna a URL da notificação
     */
    public function marcarClicadoPorToken($token)
    {
        $row = $this->db->fetch(
            "SELECT e.id, e.notificacao_id, p.url FROM notificacoes_push_envios e
             JOIN notificacoes_push p ON p.id = e.notificacao_id
             WHERE e.tracking_token = :token",
            ['token' => $token]
        );
        if (!$row) {
            return null;
        }
        $this->db->update(
            "UPDATE notificacoes_push_envios SET clicado = 1, clicado_em = NOW() WHERE tracking_token = :token AND clicado = 0",
            ['token' => $token]
        );
        return $row['url'];
    }

    /**
     * Lista envios de uma notificação (relatório). user_id = id da tabela do perfil (alunos/pais/professores/usuarios).
     */
    public function getEnviosByNotificacao($notificacaoId)
    {
        $sql = "SELECT e.*,
                CASE e.role
                    WHEN 'aluno' THEN (SELECT nome FROM alunos WHERE id = e.user_id LIMIT 1)
                    WHEN 'pai' THEN (SELECT nome FROM responsaveis WHERE id = e.user_id LIMIT 1)
                    WHEN 'professor' THEN (SELECT nome FROM professores WHERE id = e.user_id LIMIT 1)
                    ELSE (SELECT nome FROM usuarios WHERE id = e.user_id LIMIT 1)
                END AS user_nome,
                CASE e.role
                    WHEN 'aluno' THEN (SELECT COALESCE(email, '') FROM alunos WHERE id = e.user_id LIMIT 1)
                    WHEN 'pai' THEN (SELECT COALESCE(email, '') FROM responsaveis WHERE id = e.user_id LIMIT 1)
                    WHEN 'professor' THEN (SELECT COALESCE(email, '') FROM professores WHERE id = e.user_id LIMIT 1)
                    ELSE (SELECT COALESCE(email, '') FROM usuarios WHERE id = e.user_id LIMIT 1)
                END AS user_email
                FROM notificacoes_push_envios e
                WHERE e.notificacao_id = :id
                ORDER BY e.created_at ASC";
        return $this->db->fetchAll($sql, ['id' => $notificacaoId]);
    }
}
