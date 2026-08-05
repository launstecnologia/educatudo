<?php
/**
 * Controller para Notificações (Admin)
 */
class NotificationController extends BaseController
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
        
        // Verificar se é admin
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        if ($user && $user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola') {
            $this->redirect('/admin/login');
        }
    }
    
    /**
     * Listar todas as notificações
     */
    public function index()
    {
        $notificacoes = $this->notificacaoModel->getAll();
        
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/notifications/index', [
            'notificacoes' => $notificacoes,
            'title' => 'Gerenciar Notificações',
            'page_title' => 'Gerenciar Notificações',
            'user' => $this->auth->getUser(),
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type']
        ]);
    }
    
    /**
     * Formulário para criar notificação
     */
    public function create()
    {
        // Buscar dados para o formulário
        $usuarios = $this->getUsuarios();
        $professores = $this->getProfessores();
        $alunos = $this->getAlunos();
        $pais = $this->getPais();
        $turmas = $this->getTurmas();
        
        $this->viewWithLayout('admin', 'admin/notifications/create', [
            'usuarios' => $usuarios,
            'professores' => $professores,
            'alunos' => $alunos,
            'pais' => $pais,
            'turmas' => $turmas,
            'title' => 'Nova Notificação',
            'page_title' => 'Nova Notificação',
            'user' => $this->auth->getUser()
        ]);
    }
    
    /**
     * Salvar nova notificação
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/notifications/create');
        }
        
        try {
            // Validar dados
            $dados = $this->validarDados($_POST);
            
            // Upload de arquivos se necessário
            $tiposConteudo = $_POST['tipos_conteudo'] ?? [];
            
            // Upload de imagem
            if (in_array('imagem', $tiposConteudo) && !empty($_FILES['arquivo_imagem']['name'])) {
                $imagem = $this->uploadImagem($_FILES['arquivo_imagem']);
                if ($imagem) {
                    $dados['arquivo_url'] = $imagem['url'];
                    $dados['arquivo_nome'] = $imagem['nome'];
                    $dados['arquivo_tamanho'] = $imagem['tamanho'];
                }
            }
            
            // Upload de vídeo
            if (in_array('video', $tiposConteudo) && !empty($_FILES['arquivo_video']['name'])) {
                $video = $this->uploadVideo($_FILES['arquivo_video']);
                if ($video) {
                    $dados['arquivo_url'] = $video['url'];
                    $dados['arquivo_nome'] = $video['nome'];
                    $dados['arquivo_tamanho'] = $video['tamanho'];
                }
            }
            
            // Dados do remetente
            $user = $this->auth->getUser();
            $dados['enviado_por'] = $user['id'];
            $dados['tipo_enviador'] = 'admin';
            $dados['perfil_enviador'] = $user['perfil_admin'] ?? 'dev';
            
            // Criar notificação
            $notificacaoId = $this->notificacaoModel->create($dados);
            
            if ($notificacaoId) {
                // Processar destinatários
                $destinatarios = $this->processarDestinatarios($_POST);
                if (empty($destinatarios)) {
                    throw new Exception('Selecione pelo menos um destinatário');
                }
                
                $this->destinatarioModel->addDestinatarios($notificacaoId, $destinatarios);
                
                // Log da ação
                $this->logAcao($notificacaoId, 'enviada');
                
                $this->setFlashMessage('Notificação enviada com sucesso!', 'success');
                $this->redirect('/admin/notifications');
            } else {
                throw new Exception('Erro ao criar notificação');
            }
            
        } catch (Exception $e) {
            $this->setFlashMessage('Erro: ' . $e->getMessage(), 'error');
            $this->redirect('/admin/notifications/create');
        }
    }
    
    /**
     * Visualizar notificação
     */
    public function show($id)
    {
        $notificacao = $this->notificacaoModel->getById($id);
        $destinatarios = $this->destinatarioModel->getByNotificacao($id);
        $estatisticas = $this->destinatarioModel->getEstatisticasEntrega($id);
        
        $this->viewWithLayout('admin', 'admin/notifications/show', [
            'notificacao' => $notificacao,
            'destinatarios' => $destinatarios,
            'estatisticas' => $estatisticas,
            'title' => 'Detalhes da Notificação',
            'page_title' => 'Detalhes da Notificação',
            'user' => $this->auth->getUser()
        ]);
    }
    
    /**
     * Formulário para editar notificação
     */
    public function edit($id)
    {
        $notificacao = $this->notificacaoModel->getById($id);
        
        if (!$notificacao) {
            $this->setFlashMessage('Notificação não encontrada', 'error');
            $this->redirect('/admin/notifications');
        }
        
        $this->viewWithLayout('admin', 'admin/notifications/edit', [
            'notificacao' => $notificacao,
            'title' => 'Editar Notificação',
            'page_title' => 'Editar Notificação',
            'user' => $this->auth->getUser()
        ]);
    }
    
    /**
     * Atualizar notificação
     */
    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/notifications/' . $id . '/edit');
        }
        
        try {
            $notificacao = $this->notificacaoModel->getById($id);
            
            if (!$notificacao) {
                throw new Exception('Notificação não encontrada');
            }
            
            // Validar dados
            $dados = $this->validarDadosUpdate($_POST);
            
            // Upload de nova imagem se enviada
            if (!empty($_FILES['arquivo_imagem']['name'])) {
                $imagem = $this->uploadImagem($_FILES['arquivo_imagem']);
                if ($imagem) {
                    $dados['arquivo_url'] = $imagem['url'];
                    $dados['arquivo_nome'] = $imagem['nome'];
                    $dados['arquivo_tamanho'] = $imagem['tamanho'];
                }
            }
            
            // Atualizar notificação
            if ($this->notificacaoModel->update($id, $dados)) {
                $this->logAcao($id, 'atualizada');
                $this->setFlashMessage('Notificação atualizada com sucesso!', 'success');
                $this->redirect('/admin/notifications/' . $id);
            } else {
                throw new Exception('Erro ao atualizar notificação');
            }
            
        } catch (Exception $e) {
            $this->setFlashMessage('Erro: ' . $e->getMessage(), 'error');
            $this->redirect('/admin/notifications/' . $id . '/edit');
        }
    }
    
    /**
     * Excluir notificação
     */
    public function delete($id)
    {
        if ($this->notificacaoModel->delete($id)) {
            $this->logAcao($id, 'excluida');
            $this->setFlashMessage('Notificação excluída com sucesso!', 'success');
        } else {
            $this->setFlashMessage('Erro ao excluir notificação', 'error');
        }
        
        $this->redirect('/admin/notifications');
    }
    
    /**
     * API para buscar notificações não lidas
     */
    public function apiNaoLidas()
    {
        header('Content-Type: application/json');
        
        $user = $this->auth->getUser();
        $tipoUsuario = $user['tipo'] === 'admin' || $user['tipo'] === 'admin_escola' ? 'admin' : 'admin';
        
        $total = $this->notificacaoModel->getNaoLidas($user['id'], $tipoUsuario);
        
        echo json_encode(['total' => $total]);
        exit;
    }
    
    /**
     * API para marcar como lida
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
        
        $user = $this->auth->getUser();
        $tipoUsuario = $user['tipo'] === 'admin' || $user['tipo'] === 'admin_escola' ? 'admin' : 'admin';
        
        if ($this->notificacaoModel->marcarComoLida($notificacaoId, $user['id'], $tipoUsuario)) {
            echo json_encode(['success' => true, 'message' => 'Marcada como lida']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao marcar como lida']);
        }
        
        exit;
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
        
        // Validar tipos de conteúdo (novo sistema)
        $tiposConteudo = $dados['tipos_conteudo'] ?? [];
        if (empty($tiposConteudo)) {
            $errors[] = 'Selecione pelo menos um tipo de conteúdo';
        }
        
        $temTexto = in_array('texto', $tiposConteudo);
        if ($temTexto && empty($dados['conteudo'])) {
            $errors[] = 'Conteúdo é obrigatório';
        }
        
        // Validar arquivos baseado nos tipos selecionados
        if (in_array('imagem', $tiposConteudo) && !empty($_FILES['arquivo_imagem']['name'])) {
            // Validar imagem se foi enviada
        }
        
        if (in_array('video', $tiposConteudo)) {
            $temVideoUrl = !empty($dados['video_url']);
            $temVideoArquivo = !empty($_FILES['arquivo_video']['name']);
            
            if (!$temVideoUrl && !$temVideoArquivo) {
                $errors[] = 'Para vídeo, informe uma URL ou faça upload de arquivo';
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        // Determinar tipo principal baseado na seleção
        $tipoPrincipal = 'texto'; // padrão
        if (in_array('video', $tiposConteudo)) {
            $tipoPrincipal = 'video';
        } elseif (in_array('imagem', $tiposConteudo)) {
            $tipoPrincipal = 'mensagem';
        }
        
        return [
            'titulo' => trim($dados['titulo']),
            'conteudo' => trim($dados['conteudo']),
            'tipo_conteudo' => $tipoPrincipal,
            'tipos_conteudo' => implode(',', $tiposConteudo), // Salvar tipos selecionados
            'prioridade' => $dados['prioridade'] ?? 'normal',
            'data_expiracao' => !empty($dados['data_expiracao']) ? $dados['data_expiracao'] : null,
            'is_update' => isset($dados['is_update']) ? 1 : 0,
            'video_url' => $dados['video_url'] ?? null
        ];
    }
    
    /**
     * Upload de imagem
     */
    private function uploadImagem($arquivo)
    {
        $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        $uploadDir = $docRoot ? $docRoot . '/uploads/notifications/' : __DIR__ . '/../../public/uploads/notifications/';
        $webPath = '/uploads/notifications/';
        if (!is_dir($uploadDir) && $docRoot && is_dir($docRoot . '/public/uploads/notifications/')) {
            $uploadDir = $docRoot . '/public/uploads/notifications/';
            $webPath = '/public/uploads/notifications/';
        }
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($arquivo['type'], $allowedTypes)) {
            throw new Exception('Tipo de imagem não permitido');
        }
        
        if ($arquivo['size'] > $maxSize) {
            throw new Exception('Imagem muito grande (máximo 10MB)');
        }
        
        $extension = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $filename = 'notification_img_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($arquivo['tmp_name'], $filepath)) {
            return [
                'url' => $webPath . $filename,
                'nome' => $arquivo['name'],
                'tamanho' => $arquivo['size']
            ];
        }
        
        throw new Exception('Erro ao fazer upload da imagem');
    }
    
    /**
     * Upload de vídeo
     */
    private function uploadVideo($arquivo)
    {
        $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        $uploadDir = $docRoot ? $docRoot . '/uploads/notifications/' : __DIR__ . '/../../public/uploads/notifications/';
        $webPath = '/uploads/notifications/';
        if (!is_dir($uploadDir) && $docRoot && is_dir($docRoot . '/public/uploads/notifications/')) {
            $uploadDir = $docRoot . '/public/uploads/notifications/';
            $webPath = '/public/uploads/notifications/';
        }
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['video/mp4', 'video/avi', 'video/mov', 'video/wmv'];
        $maxSize = 50 * 1024 * 1024; // 50MB
        
        if (!in_array($arquivo['type'], $allowedTypes)) {
            throw new Exception('Tipo de vídeo não permitido');
        }
        
        if ($arquivo['size'] > $maxSize) {
            throw new Exception('Vídeo muito grande (máximo 50MB)');
        }
        
        $extension = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $filename = 'notification_video_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($arquivo['tmp_name'], $filepath)) {
            return [
                'url' => $webPath . $filename,
                'nome' => $arquivo['name'],
                'tamanho' => $arquivo['size']
            ];
        }
        
        throw new Exception('Erro ao fazer upload do vídeo');
    }
    
    /**
     * Validar dados para atualização
     */
    private function validarDadosUpdate($dados)
    {
        $errors = [];
        
        if (empty($dados['titulo'])) {
            $errors[] = 'Título é obrigatório';
        }
        
        if (empty($dados['conteudo'])) {
            $errors[] = 'Conteúdo é obrigatório';
        }
        
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        return [
            'titulo' => trim($dados['titulo']),
            'conteudo' => trim($dados['conteudo']),
            'prioridade' => $dados['prioridade'] ?? 'normal',
            'data_expiracao' => !empty($dados['data_expiracao']) ? $dados['data_expiracao'] : null,
            'ativo' => isset($dados['ativo']) ? 1 : 0,
            'video_url' => $dados['video_url'] ?? null
        ];
    }
    
    /**
     * Processar destinatários
     */
    private function processarDestinatarios($dados)
    {
        $destinatarios = [];
        
        // Todos os usuários
        if (isset($dados['todos_usuarios']) && $dados['todos_usuarios']) {
            // Buscar todos os usuários da tabela usuarios (admins)
            $sql = "SELECT id, tipo FROM usuarios WHERE ativo = 1";
            $usuarios = $this->db->fetchAll($sql);
            
            foreach ($usuarios as $usuario) {
                $destinatarios[] = [
                    'tipo_destinatario' => 'usuarios',
                    'destinatario_id' => $usuario['id']
                ];
            }
            
            // Buscar todos os alunos
            $sql = "SELECT id FROM alunos WHERE ativo = 1";
            $alunos = $this->db->fetchAll($sql);
            
            foreach ($alunos as $aluno) {
                $destinatarios[] = [
                    'tipo_destinatario' => 'alunos',
                    'destinatario_id' => $aluno['id']
                ];
            }
            
            // Buscar todos os professores
            $sql = "SELECT id FROM professores WHERE ativo = 1";
            $professores = $this->db->fetchAll($sql);
            
            foreach ($professores as $professor) {
                $destinatarios[] = [
                    'tipo_destinatario' => 'professores',
                    'destinatario_id' => $professor['id']
                ];
            }
            
            // Buscar todos os pais
            $sql = "SELECT id FROM responsaveis WHERE ativo = 1";
            $pais = $this->db->fetchAll($sql);
            
            foreach ($pais as $pai) {
                $destinatarios[] = [
                    'tipo_destinatario' => 'pais',
                    'destinatario_id' => $pai['id']
                ];
            }
        }
        
        // Categorias específicas
        $categorias = $dados['categorias'] ?? [];
        foreach ($categorias as $categoria) {
            if ($categoria === 'todos_alunos') {
                // Buscar todos os alunos
                $sql = "SELECT id FROM alunos WHERE ativo = 1";
                $alunos = $this->db->fetchAll($sql);
                
                foreach ($alunos as $aluno) {
                    $destinatarios[] = [
                        'tipo_destinatario' => 'alunos',
                        'destinatario_id' => $aluno['id']
                    ];
                }
            } elseif ($categoria === 'todos_professores') {
                // Buscar todos os professores
                $sql = "SELECT id FROM professores WHERE ativo = 1";
                $professores = $this->db->fetchAll($sql);
                
                foreach ($professores as $professor) {
                    $destinatarios[] = [
                        'tipo_destinatario' => 'professores',
                        'destinatario_id' => $professor['id']
                    ];
                }
            } elseif ($categoria === 'todos_admins') {
                // Buscar todos os admins
                $sql = "SELECT id FROM usuarios WHERE tipo IN ('admin', 'admin_escola') AND ativo = 1";
                $admins = $this->db->fetchAll($sql);
                
                foreach ($admins as $admin) {
                    $destinatarios[] = [
                        'tipo_destinatario' => 'usuarios',
                        'destinatario_id' => $admin['id']
                    ];
                }
            } elseif ($categoria === 'todos_pais') {
                // Buscar todos os pais
                $sql = "SELECT id FROM responsaveis WHERE ativo = 1";
                $pais = $this->db->fetchAll($sql);
                
                foreach ($pais as $pai) {
                    $destinatarios[] = [
                        'tipo_destinatario' => 'pais',
                        'destinatario_id' => $pai['id']
                    ];
                }
            } else {
                // Categoria genérica (manter comportamento antigo)
                $destinatarios[] = [
                    'tipo_destinatario' => $categoria,
                    'destinatario_id' => null
                ];
            }
        }
        
        // Usuários específicos
        $usuariosEspecificos = $dados['destinatarios'] ?? [];
        foreach ($usuariosEspecificos as $destinatario) {
            if ($destinatario === 'todos') continue;
            
            $parts = explode('_', $destinatario);
            if (count($parts) === 2) {
                $destinatarios[] = [
                    'tipo_destinatario' => $parts[0],
                    'destinatario_id' => $parts[1]
                ];
            }
        }
        
        return $destinatarios;
    }
    
    /**
     * Buscar usuários
     */
    private function getUsuarios()
    {
        $sql = "SELECT id, nome, email FROM usuarios WHERE ativo = 1 ORDER BY nome";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Buscar professores
     */
    private function getProfessores()
    {
        $sql = "SELECT id, nome, email FROM professores WHERE ativo = 1 ORDER BY nome";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Buscar alunos
     */
    private function getAlunos()
    {
        $sql = "SELECT id, nome, email FROM alunos WHERE ativo = 1 ORDER BY nome";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Buscar pais
     */
    private function getPais()
    {
        $sql = "SELECT id, nome, email FROM responsaveis WHERE ativo = 1 ORDER BY nome";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Buscar turmas
     */
    private function getTurmas()
    {
        $sql = "SELECT id, nome, serie FROM turmas WHERE ativo = 1 ORDER BY serie, nome";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Log de ação
     */
    private function logAcao($notificacaoId, $acao)
    {
        $sql = "INSERT INTO notificacoes_historico 
                (notificacao_id, usuario_id, tipo_usuario, acao, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $user = $this->auth->getUser();
        $params = [
            $notificacaoId,
            $user['id'],
            'admin',
            $acao,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $this->db->insert($sql, $params);
    }
}
