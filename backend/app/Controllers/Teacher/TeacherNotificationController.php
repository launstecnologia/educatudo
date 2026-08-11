<?php
/**
 * Controller para Notificações do Professor
 */
if (!class_exists('TeacherNotificationController')) {
class TeacherNotificationController extends BaseController
{
    private $notificacaoModel;
    private $destinatarioModel;
    private $auth;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        require_once __DIR__ . '/../../Models/Notifications/Notification.php';
        require_once __DIR__ . '/../../Models/Notifications/NotificationRecipient.php';
        $this->notificacaoModel = new Notification();
        $this->destinatarioModel = new NotificationRecipient();
        $this->db = Database::getInstance();
        
        // Verificar se é professor
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        if ($user && $user['tipo'] !== 'professor') {
            $this->redirect('/teacher/login');
        }
    }
    
    /**
     * Listar notificações enviadas pelo professor
     */
    public function index()
    {
        $professor = $this->auth->getUser();
        $notificacoes = $this->getNotificacoesProfessor($professor['id']);
        
        $this->viewWithLayout('professor', 'teacher/notifications/index', [
            'notificacoes' => $notificacoes,
            'title' => 'Minhas Notificações',
            'page_title' => 'Minhas Notificações',
            'user' => $professor
        ]);
    }
    
    /**
     * Formulário para criar notificação para turma
     */
    public function create()
    {
        $professor = $this->auth->getUser();
        $turmas = $this->getTurmasProfessor($professor['id']);
        
        $this->viewWithLayout('professor', 'teacher/notifications/create', [
            'turmas' => $turmas,
            'title' => 'Nova Notificação para Turma',
            'page_title' => 'Nova Notificação para Turma',
            'user' => $professor
        ]);
    }
    
    /**
     * Salvar notificação para turma
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/teacher/notifications/create');
        }
        
        try {
            // Validar dados
            $dados = $this->validarDados($_POST);
            
            // Upload de arquivo se necessário
            if ($dados['tipo_conteudo'] === 'video' && isset($_FILES['arquivo'])) {
                $arquivo = $this->uploadArquivo($_FILES['arquivo']);
                if ($arquivo) {
                    $dados['arquivo_url'] = $arquivo['url'];
                    $dados['arquivo_nome'] = $arquivo['nome'];
                    $dados['arquivo_tamanho'] = $arquivo['tamanho'];
                }
            }
            
            // Dados do remetente
            $professor = $this->auth->getUser();
            $dados['enviado_por'] = $professor['id'];
            $dados['tipo_enviador'] = 'professor';
            $dados['perfil_enviador'] = 'professor';
            
            // Criar notificação
            $notificacaoId = $this->notificacaoModel->create($dados);
            
            if ($notificacaoId) {
                // Adicionar destinatários da turma
                $destinatarios = [
                    ['tipo' => 'turma', 'turma_id' => $dados['turma_id']]
                ];
                
                $this->destinatarioModel->addDestinatarios($notificacaoId, $destinatarios);
                
                // Log da ação
                $this->logAcao($notificacaoId, 'enviada');
                
                $this->setFlashMessage('Notificação enviada para a turma com sucesso!', 'success');
                $this->redirect('/teacher/notifications');
            } else {
                throw new Exception('Erro ao criar notificação');
            }
            
        } catch (Exception $e) {
            $this->setFlashMessage('Erro: ' . $e->getMessage(), 'error');
            $this->redirect('/teacher/notifications/create');
        }
    }
    
    /**
     * Visualizar notificação
     */
    public function show($id)
    {
        $professor = $this->auth->getUser();
        $notificacao = $this->getNotificacaoProfessor($id, $professor['id']);
        
        if (!$notificacao) {
            $this->setFlashMessage('Notificação não encontrada', 'error');
            $this->redirect('/teacher/notifications');
        }
        
        $destinatarios = $this->destinatarioModel->getByNotificacao($id);
        $estatisticas = $this->destinatarioModel->getEstatisticasEntrega($id);
        
        $this->view('teacher/notifications/show', [
            'notificacao' => $notificacao,
            'destinatarios' => $destinatarios,
            'estatisticas' => $estatisticas,
            'title' => 'Detalhes da Notificação'
        ]);
    }
    
    /**
     * Excluir notificação
     */
    public function delete($id)
    {
        $professor = $this->auth->getUser();
        $notificacao = $this->getNotificacaoProfessor($id, $professor['id']);
        
        if (!$notificacao) {
            $this->setFlashMessage('Notificação não encontrada', 'error');
            $this->redirect('/teacher/notifications');
        }
        
        if ($this->notificacaoModel->delete($id)) {
            $this->logAcao($id, 'excluida');
            $this->setFlashMessage('Notificação excluída com sucesso!', 'success');
        } else {
            $this->setFlashMessage('Erro ao excluir notificação', 'error');
        }
        
        $this->redirect('/teacher/notifications');
    }
    
    /**
     * Buscar notificações do professor
     */
    private function getNotificacoesProfessor($professorId)
    {
        $sql = "SELECT n.*, 
                       COUNT(nd.id) as total_destinatarios,
                       COUNT(CASE WHEN nd.lida = 1 THEN 1 END) as total_lidas
                FROM notificacoes n
                LEFT JOIN notificacoes_destinatarios nd ON n.id = nd.notificacao_id
                WHERE n.enviado_por = ? AND n.tipo_enviador = 'professor' AND n.ativo = 1
                GROUP BY n.id
                ORDER BY n.created_at DESC";
        
        return $this->db->fetchAll($sql, [$professorId]);
    }
    
    /**
     * Buscar notificação específica do professor
     */
    private function getNotificacaoProfessor($notificacaoId, $professorId)
    {
        $sql = "SELECT * FROM notificacoes 
                WHERE id = ? AND enviado_por = ? AND tipo_enviador = 'professor' AND ativo = 1";
        
        return $this->db->fetch($sql, [$notificacaoId, $professorId]);
    }
    
    /**
     * Buscar turmas do professor
     */
    private function getTurmasProfessor($professorId)
    {
        $sql = "SELECT p.turmas FROM professores p WHERE p.id = ?";
        $professor = $this->db->fetch($sql, [$professorId]);
        
        if (empty($professor['turmas'])) {
            return [];
        }
        
        $turmasIds = json_decode($professor['turmas'], true);
        if (empty($turmasIds)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($turmasIds) - 1) . '?';
        $sql = "SELECT id, nome, serie FROM turmas WHERE id IN ($placeholders) AND ativo = 1 ORDER BY serie, nome";
        
        return $this->db->fetchAll($sql, $turmasIds);
    }
    
    /**
     * Validar dados do formulário
     */
    private function validarDados($dados)
    {
        $errors = [];
        
        if (empty($dados['titulo'])) {
            $errors[] = 'Título é obrigatório';
        }
        
        if (empty($dados['conteudo'])) {
            $errors[] = 'Conteúdo é obrigatório';
        }
        
        if (empty($dados['tipo_conteudo'])) {
            $errors[] = 'Tipo de conteúdo é obrigatório';
        }
        
        if (empty($dados['turma_id'])) {
            $errors[] = 'Turma é obrigatória';
        }
        
        if ($dados['tipo_conteudo'] === 'video' && empty($_FILES['arquivo']['name'])) {
            $errors[] = 'Arquivo de vídeo é obrigatório';
        }
        
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        return [
            'titulo' => trim($dados['titulo']),
            'conteudo' => trim($dados['conteudo']),
            'tipo_conteudo' => $dados['tipo_conteudo'],
            'turma_id' => $dados['turma_id'],
            'prioridade' => $dados['prioridade'] ?? 'normal',
            'data_expiracao' => !empty($dados['data_expiracao']) ? $dados['data_expiracao'] : null
        ];
    }
    
    /**
     * Upload de arquivo
     */
    private function uploadArquivo($arquivo)
    {
        $uploadDir = __DIR__ . '/../../public/uploads/notifications/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['video/mp4', 'video/avi', 'video/mov', 'video/wmv'];
        $maxSize = 50 * 1024 * 1024; // 50MB
        
        if (!in_array($arquivo['type'], $allowedTypes)) {
            throw new Exception('Tipo de arquivo não permitido');
        }
        
        if ($arquivo['size'] > $maxSize) {
            throw new Exception('Arquivo muito grande (máximo 50MB)');
        }
        
        $extension = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $filename = 'notification_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($arquivo['tmp_name'], $filepath)) {
            return [
                'url' => '/public/uploads/notifications/' . $filename,
                'nome' => $arquivo['name'],
                'tamanho' => $arquivo['size']
            ];
        }
        
        throw new Exception('Erro ao fazer upload do arquivo');
    }
    
    /**
     * Log de ação
     */
    private function logAcao($notificacaoId, $acao)
    {
        $sql = "INSERT INTO notificacoes_historico 
                (notificacao_id, usuario_id, tipo_usuario, acao, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $professor = $this->auth->getUser();
        $params = [
            $notificacaoId,
            $professor['id'],
            'professor',
            $acao,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $this->db->insert($sql, $params);
    }
}
}
