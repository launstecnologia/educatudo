<?php
/**
 * Controller para Notificações do Usuário (Aluno, Professor, Pai)
 */
if (!class_exists('UserNotificationController')) {
class UserNotificationController extends BaseController
{
    private $notificacaoModel;
    private $auth;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        require_once __DIR__ . '/../../Models/Notifications/Notification.php';
        $this->notificacaoModel = new Notification();
        $this->auth = new AuthManager();
        $this->db = Database::getInstance();
    }
    
    /**
     * Listar notificações do usuário
     */
    public function index()
    {
        $usuario = $this->getCurrentUser();
        $tipoUsuario = $this->getTipoUsuario();
        
        $notificacoes = $this->notificacaoModel->getHistorico($usuario['id'], $tipoUsuario);
        
        // Determinar layout baseado no tipo de usuário
        $layout = $tipoUsuario === 'aluno' ? 'student' : 
                 ($tipoUsuario === 'professor' ? 'professor' : 
                 ($tipoUsuario === 'admin' ? 'admin' : 'parent'));
        
        $this->viewWithLayout($layout, 'notifications/index', [
            'notificacoes' => $notificacoes,
            'title' => 'Minhas Notificações',
            'page_title' => 'Portal do Aluno',
            'user' => $usuario
        ]);
    }
    
    /**
     * Visualizar notificação específica
     */
    public function show($id)
    {
        $usuario = $this->getCurrentUser();
        $tipoUsuario = $this->getTipoUsuario();
        
        // Marcar como visualizada
        $this->notificacaoModel->marcarComoVisualizada($id, $usuario['id'], $tipoUsuario);
        
        // Buscar notificação
        $notificacoes = $this->notificacaoModel->getHistorico($usuario['id'], $tipoUsuario, 1);
        $notificacao = null;
        
        foreach ($notificacoes as $notif) {
            if ($notif['id'] == $id) {
                $notificacao = $notif;
                break;
            }
        }
        
        if (!$notificacao) {
            $this->setFlashMessage('Notificação não encontrada', 'error');
            $this->redirect('/notifications');
        }
        
        // Determinar layout baseado no tipo de usuário
        $layout = $tipoUsuario === 'aluno' ? 'student' : 
                 ($tipoUsuario === 'professor' ? 'professor' : 
                 ($tipoUsuario === 'admin' ? 'admin' : 'parent'));
        
        $this->viewWithLayout($layout, 'notifications/show', [
            'notificacao' => $notificacao,
            'title' => 'Notificação',
            'page_title' => 'Notificação',
            'user' => $usuario
        ]);
    }
    
    /**
     * Marcar notificação como lida
     */
    public function marcarLida($id)
    {
        $usuario = $this->getCurrentUser();
        $tipoUsuario = $this->getTipoUsuario();
        
        if ($this->notificacaoModel->marcarComoLida($id, $usuario['id'], $tipoUsuario)) {
            $this->setFlashMessage('Notificação marcada como lida', 'success');
        } else {
            $this->setFlashMessage('Erro ao marcar como lida', 'error');
        }
        
        $this->redirect('/notifications');
    }
    
    /**
     * API para buscar notificações não lidas
     */
    public function apiNaoLidas()
    {
        header('Content-Type: application/json');
        
        $usuario = $this->getCurrentUser();
        $tipoUsuario = $this->getTipoUsuario();
        
        $total = $this->notificacaoModel->getNaoLidas($usuario['id'], $tipoUsuario);
        
        echo json_encode(['total' => $total]);
        exit;
    }

    /**
     * SSE para notificações não lidas (profissional, sem polling no frontend)
     */
    public function apiStream()
    {
        // Headers SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Desativar buffering
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', 0);
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $usuario = $this->getCurrentUser();
        $tipoUsuario = $this->getTipoUsuario();

        // Evitar bloquear a sessão durante o stream
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $lastTotal = null;
        $start = time();
        $lastPing = time();
        $checkIntervalSeconds = 10;

        // Loop curto para manter conexão viva sem sobrecarga
        while (time() - $start < 55) {
            if (connection_aborted()) {
                break;
            }

            $total = $this->notificacaoModel->getNaoLidas($usuario['id'], $tipoUsuario);
            if ($lastTotal === null || $total !== $lastTotal) {
                echo "event: unread\n";
                echo "data: " . json_encode(['total' => $total]) . "\n\n";
                @ob_flush();
                flush();
                $lastTotal = $total;
            }

            // Heartbeat para manter a conexão ativa
            if (time() - $lastPing >= 15) {
                echo "event: ping\n";
                echo "data: {}\n\n";
                @ob_flush();
                flush();
                $lastPing = time();
            }

            usleep($checkIntervalSeconds * 1000000);
        }

        exit;
    }
    
    /**
     * API para marcar como lida via AJAX
     */
    public function apiMarcarLida()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
            exit;
        }
        
        $notificacaoId = $_POST['notificacao_id'] ?? null;
        
        if (!$notificacaoId) {
            echo json_encode(['success' => false, 'message' => 'ID da notificação é obrigatório']);
            exit;
        }
        
        try {
            $usuario = $this->getCurrentUser();
            $tipoUsuario = $this->getTipoUsuario();
            $resultado = $this->notificacaoModel->marcarComoLida($notificacaoId, $usuario['id'], $tipoUsuario);
            
            if ($resultado) {
                echo json_encode(['success' => true, 'message' => 'Marcada como lida']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao marcar como lida']);
            }
        } catch (Exception $e) {
            error_log('Erro na API: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        
        exit;
    }
    
    /**
     * API para buscar notificações recentes
     */
    public function apiRecentes()
    {
        header('Content-Type: application/json');
        
        $usuario = $this->getCurrentUser();
        $tipoUsuario = $this->getTipoUsuario();
        
        $notificacoes = $this->notificacaoModel->getByDestinatario($usuario['id'], $tipoUsuario, 5);
        
        echo json_encode(['notificacoes' => $notificacoes]);
        exit;
    }
    
    /**
     * API para marcar todas as notificações como lidas
     */
    public function apiMarcarTodasLidas()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
            exit;
        }
        
        try {
            $usuario = $this->getCurrentUser();
            $tipoUsuario = $this->getTipoUsuario();
            
            // Buscar todas as notificações não lidas do usuário
            $notificacoes = $this->notificacaoModel->getHistorico($usuario['id'], $tipoUsuario, 100);
            
            $marcadas = 0;
            foreach ($notificacoes as $notificacao) {
                if (!$notificacao['lida']) {
                    $resultado = $this->notificacaoModel->marcarComoLida($notificacao['id'], $usuario['id'], $tipoUsuario);
                    if ($resultado) {
                        $marcadas++;
                    }
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "$marcadas notificações marcadas como lidas",
                'marcadas' => $marcadas
            ]);
            
        } catch (Exception $e) {
            error_log('Erro na API marcar todas: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        
        exit;
    }
    
    /**
     * Botão atualizar (Ctrl+F5)
     */
    public function atualizar()
    {
        $usuario = $this->getCurrentUser();
        $tipoUsuario = $this->getTipoUsuario();
        
        // Marcar todas as notificações não lidas como visualizadas
        $sql = "UPDATE notificacoes_destinatarios 
                SET visualizada_em = NOW() 
                WHERE destinatario_id = ? 
                AND tipo_destinatario = ? 
                AND visualizada_em IS NULL";
        
        $this->db->update($sql, [$usuario['id'], $tipoUsuario]);
        
        $this->setFlashMessage('Sistema atualizado!', 'success');
        
        // Redirecionar para a página de notificações
        $this->redirect('/notifications');
    }
    
    /**
     * Determinar tipo de usuário baseado na sessão
     */
    private function getTipoUsuario()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            throw new Exception('Usuário não logado');
        }
        
        $tipo = $user['tipo'];
        
        // Mapear tipos para o sistema de notificações
        switch ($tipo) {
            case 'admin':
            case 'admin_escola':
                return 'admin';
            case 'professor':
                return 'professor';
            case 'aluno':
                return 'aluno';
            case 'pai':
                return 'pai';
            default:
                return 'aluno'; // Default
        }
    }
    
    /**
     * Buscar usuário atual baseado na sessão
     */
    private function getCurrentUser()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            throw new Exception('Usuário não logado');
        }
        return $user;
    }
}
}
