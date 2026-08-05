<?php
/**
 * EducaTudo - Controller de Chat Global
 * Gerencia chat global entre aluno e professor
 */

if (!class_exists('ChatController')) {
class ChatController extends BaseController
{
    private $authManager;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        
        // Verifica se está logado
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
    }
    
    /**
     * Lista de conversas do professor
     */
    public function indexProfessor()
    {
        $user = $this->authManager->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        // Busca todas as conversas do professor
        $conversas = $this->db->fetchAll(
            "SELECT cpa.*, 
                    a.nome as aluno_nome,
                    a.ra as aluno_ra,
                    t.nome as turma_nome,
                    (SELECT COUNT(*) 
                     FROM chat_professores_alunos_mensagens cpm 
                     WHERE cpm.chat_id = cpa.id 
                     AND cpm.remetente_tipo = 'aluno' 
                     AND cpm.lida = 0) as mensagens_nao_lidas,
                    (SELECT cpm.mensagem 
                     FROM chat_professores_alunos_mensagens cpm 
                     WHERE cpm.chat_id = cpa.id 
                     ORDER BY cpm.created_at DESC 
                     LIMIT 1) as ultima_mensagem,
                    (SELECT cpm.created_at 
                     FROM chat_professores_alunos_mensagens cpm 
                     WHERE cpm.chat_id = cpa.id 
                     ORDER BY cpm.created_at DESC 
                     LIMIT 1) as ultima_mensagem_data
             FROM chat_professores_alunos cpa
             JOIN alunos a ON cpa.aluno_id = a.id
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE cpa.professor_id = :prof_id
             ORDER BY cpa.ultima_atividade DESC",
            ['prof_id' => $professor['id']]
        );
        
        $data = [
            'title' => 'Chat com Alunos - EducaTudo',
            'professor' => $professor,
            'conversas' => $conversas,
            'user' => $user,
            'current_page' => 'chat'
        ];
        
        // Verifica se a view existe antes de renderizar
        $viewPath = __DIR__ . '/../../Views/teacher/chat/index.php';
        if (file_exists($viewPath)) {
            $this->viewWithLayout('professor', 'teacher/chat/index', $data);
        } else {
            // View ainda não criada, redireciona para dashboard
            $_SESSION['info_message'] = 'Funcionalidade de chat em desenvolvimento';
            $this->redirect('/teacher/dashboard');
        }
    }
    
    /**
     * Chat do professor com um aluno específico
     */
    public function chatProfessor($aluno_id)
    {
        try {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor CHAMADO - aluno_id: $aluno_id ===");
            }
            $user = $this->authManager->getUser();
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor - user tipo: " . ($user['tipo'] ?? 'N/A') . " ===");
            }
            
            if ($user['tipo'] !== 'professor') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("=== chatProfessor - Redirecionando para dashboard (tipo incorreto) ===");
                }
                $this->redirectToCorrectDashboard($user['tipo']);
                return;
            }
            
            $professor = $this->db->fetch(
                "SELECT * FROM professores WHERE id = :user_id",
                ['user_id' => $user['id']]
            );
            
            if (!$professor) {
                $_SESSION['error_message'] = 'Professor não encontrado';
                $this->redirect('/professor/chat');
                return;
            }
            
            // Busca dados do aluno
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome 
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id 
                 WHERE a.id = :aluno_id",
                ['aluno_id' => $aluno_id]
            );
            
            if (!$aluno) {
                $_SESSION['error_message'] = 'Aluno não encontrado';
                $this->redirect('/professor/chat');
                return;
            }
            
            // Busca ou cria conversa
            $chat = $this->db->fetch(
                "SELECT * FROM chat_professores_alunos 
                 WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
                ['aluno_id' => $aluno_id, 'prof_id' => $professor['id']]
            );
            
            if (!$chat) {
                $chat_id = $this->db->insert(
                    "INSERT INTO chat_professores_alunos (aluno_id, professor_id) 
                     VALUES (:aluno_id, :prof_id)",
                    ['aluno_id' => $aluno_id, 'prof_id' => $professor['id']]
                );
                $chat = $this->db->fetch(
                    "SELECT * FROM chat_professores_alunos WHERE id = :chat_id",
                    ['chat_id' => $chat_id]
                );
            }
            
            // Busca mensagens
            $mensagens = $this->db->fetchAll(
                "SELECT cpm.*, 
                        a.nome as aluno_nome,
                        p.nome as professor_nome
                 FROM chat_professores_alunos_mensagens cpm
                 LEFT JOIN alunos a ON cpm.remetente_tipo = 'aluno' AND cpm.remetente_id = a.id
                 LEFT JOIN professores p ON cpm.remetente_tipo = 'professor' AND cpm.remetente_id = p.id
                 WHERE cpm.chat_id = :chat_id
                 ORDER BY cpm.created_at ASC",
                ['chat_id' => $chat['id']]
            );
            
            // Busca anexos das mensagens
            foreach ($mensagens as &$mensagem) {
                $mensagem['anexos'] = $this->db->fetchAll(
                    "SELECT * FROM chat_professores_alunos_anexos 
                     WHERE mensagem_id = :mensagem_id
                     ORDER BY created_at ASC",
                    ['mensagem_id' => $mensagem['id']]
                );
            }
            
            // Marca mensagens como lidas
            $this->db->update(
                "UPDATE chat_professores_alunos_mensagens 
                 SET lida = 1, lida_em = NOW() 
                 WHERE chat_id = :chat_id 
                 AND remetente_tipo = 'aluno' 
                 AND lida = 0",
                ['chat_id' => $chat['id']]
            );
            
            $data = [
                'title' => 'Chat com ' . $aluno['nome'] . ' - EducaTudo',
                'page_title' => 'Chat com ' . $aluno['nome'],
                'professor' => $professor,
                'aluno' => $aluno,
                'chat' => $chat,
                'mensagens' => $mensagens,
                'user' => $user,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'chat'
            ];
            
            // Verifica se a view existe antes de renderizar
            $viewPath = __DIR__ . '/../../Views/teacher/chat/chat.php';
            $layoutPath = __DIR__ . '/../../Views/layouts/professor.php';
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                error_log("=== chatProfessor - Verificando view: $viewPath ===");
            
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor - View existe: " . (file_exists($viewPath) ? 'SIM' : 'NÃO') . " ===");
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor - Layout existe: " . (file_exists($layoutPath) ? 'SIM' : 'NÃO') . " ===");
            }
            
            if (!file_exists($viewPath)) {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("=== chatProfessor - View não encontrada, redirecionando ===");
                }
                $_SESSION['info_message'] = 'Funcionalidade de chat em desenvolvimento';
                $this->redirect('/professor/chat');
                return;
            }
            
            if (!file_exists($layoutPath)) {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("=== chatProfessor - Layout não encontrado, redirecionando ===");
                }
                $_SESSION['error_message'] = 'Layout não encontrado';
                $this->redirect('/professor/chat');
                return;
            }
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                error_log("=== chatProfessor - Renderizando view ===");
            
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor - Layout: professor ===");
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor - View: teacher/chat/chat ===");
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor - Data keys: " . implode(', ', array_keys($data)) . " ===");
            }
            
            // Renderiza a view diretamente sem try-catch interno para que erros sejam capturados pelo catch externo
            $this->viewWithLayout('professor', 'teacher/chat/chat', $data);
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor - View renderizada com sucesso ===");
            }
            return; // Importante: retorna após renderizar para não continuar
        } catch (Exception $e) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor - EXCEÇÃO CAPTURADA: " . $e->getMessage() . " ===");
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor - Arquivo: " . $e->getFile() . " Linha: " . $e->getLine() . " ===");
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("=== chatProfessor - Stack trace: " . $e->getTraceAsString() . " ===");
                }
            }
            $_SESSION['error_message'] = 'Erro ao carregar chat: ' . $e->getMessage();
            $this->redirect('/professor/chat');
            return;
        } catch (Throwable $e) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor - THROWABLE CAPTURADO: " . $e->getMessage() . " ===");
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== chatProfessor - Arquivo: " . $e->getFile() . " Linha: " . $e->getLine() . " ===");
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("=== chatProfessor - Stack trace: " . $e->getTraceAsString() . " ===");
                }
            }
            $_SESSION['error_message'] = 'Erro ao carregar chat: ' . $e->getMessage();
            $this->redirect('/professor/chat');
            return;
        }
    }
    
    /**
     * Enviar mensagem (professor)
     */
    public function enviarMensagemProfessor()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'professor') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            
            $aluno_id = $_POST['aluno_id'] ?? null;
            $mensagem = trim($_POST['mensagem'] ?? '');
            
            if (empty($aluno_id) || empty($mensagem)) {
                throw new Exception('Aluno e mensagem são obrigatórios');
            }
            
            $professor = $this->db->fetch(
                "SELECT * FROM professores WHERE id = :user_id",
                ['user_id' => $user['id']]
            );
            
            // Busca ou cria conversa
            $chat = $this->db->fetch(
                "SELECT * FROM chat_professores_alunos 
                 WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
                ['aluno_id' => $aluno_id, 'prof_id' => $professor['id']]
            );
            
            if (!$chat) {
                $chat_id = $this->db->insert(
                    "INSERT INTO chat_professores_alunos (aluno_id, professor_id) 
                     VALUES (:aluno_id, :prof_id)",
                    ['aluno_id' => $aluno_id, 'prof_id' => $professor['id']]
                );
            } else {
                $chat_id = $chat['id'];
            }
            
            // Insere mensagem
            $mensagem_id = $this->db->insert(
                "INSERT INTO chat_professores_alunos_mensagens 
                 (chat_id, remetente_tipo, remetente_id, mensagem) 
                 VALUES (:chat_id, 'professor', :prof_id, :mensagem)",
                [
                    'chat_id' => $chat_id,
                    'prof_id' => $professor['id'],
                    'mensagem' => $mensagem
                ]
            );
            
            // Atualiza última atividade
            $this->db->update(
                "UPDATE chat_professores_alunos 
                 SET ultima_atividade = NOW() 
                 WHERE id = :chat_id",
                ['chat_id' => $chat_id]
            );
            
            // Busca mensagem criada
            $mensagem_criada = $this->db->fetch(
                "SELECT cpm.*, p.nome as professor_nome
                 FROM chat_professores_alunos_mensagens cpm
                 LEFT JOIN professores p ON cpm.remetente_id = p.id
                 WHERE cpm.id = :mensagem_id",
                ['mensagem_id' => $mensagem_id]
            );
            
            $mensagem_criada['anexos'] = [];
            
            $this->json(['success' => true, 'mensagem' => $mensagem_criada]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Buscar novas mensagens (professor)
     */
    public function buscarMensagensProfessor()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'professor') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            
            $aluno_id = $_GET['aluno_id'] ?? null;
            $ultima_mensagem_id = $_GET['ultima_mensagem_id'] ?? 0;
            
            if (empty($aluno_id)) {
                throw new Exception('Aluno é obrigatório');
            }
            
            $professor = $this->db->fetch(
                "SELECT * FROM professores WHERE id = :user_id",
                ['user_id' => $user['id']]
            );
            
            $chat = $this->db->fetch(
                "SELECT * FROM chat_professores_alunos 
                 WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
                ['aluno_id' => $aluno_id, 'prof_id' => $professor['id']]
            );
            
            if (!$chat) {
                $this->json(['success' => true, 'mensagens' => []]);
                return;
            }
            
            // Busca mensagens novas
            $mensagens = $this->db->fetchAll(
                "SELECT cpm.*, 
                        a.nome as aluno_nome,
                        p.nome as professor_nome
                 FROM chat_professores_alunos_mensagens cpm
                 LEFT JOIN alunos a ON cpm.remetente_tipo = 'aluno' AND cpm.remetente_id = a.id
                 LEFT JOIN professores p ON cpm.remetente_tipo = 'professor' AND cpm.remetente_id = p.id
                 WHERE cpm.chat_id = :chat_id 
                   AND cpm.id > :ultima_mensagem_id
                 ORDER BY cpm.created_at ASC",
                [
                    'chat_id' => $chat['id'],
                    'ultima_mensagem_id' => $ultima_mensagem_id
                ]
            );
            
            // Busca anexos das mensagens
            foreach ($mensagens as &$mensagem) {
                $mensagem['anexos'] = $this->db->fetchAll(
                    "SELECT * FROM chat_professores_alunos_anexos 
                     WHERE mensagem_id = :mensagem_id
                     ORDER BY created_at ASC",
                    ['mensagem_id' => $mensagem['id']]
                );
            }
            
            // Marca mensagens como lidas
            if (!empty($mensagens)) {
                $this->db->update(
                    "UPDATE chat_professores_alunos_mensagens 
                     SET lida = 1, lida_em = NOW() 
                     WHERE chat_id = :chat_id 
                     AND remetente_tipo = 'aluno' 
                     AND lida = 0",
                    ['chat_id' => $chat['id']]
                );
            }
            
            $this->json(['success' => true, 'mensagens' => $mensagens]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Lista de conversas do aluno
     */
    public function indexAluno()
    {
        $user = $this->authManager->getUser();
        
        if ($user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }
        
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome 
             FROM alunos a 
             LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );
        
        // Busca professores que têm conversas com o aluno
        $conversas = $this->db->fetchAll(
            "SELECT cpa.*, 
                    p.nome as professor_nome,
                    p.codigo_prof as professor_codigo,
                    (SELECT COUNT(*) 
                     FROM chat_professores_alunos_mensagens cpm 
                     WHERE cpm.chat_id = cpa.id 
                     AND cpm.remetente_tipo = 'professor' 
                     AND cpm.lida = 0) as mensagens_nao_lidas,
                    (SELECT cpm.mensagem 
                     FROM chat_professores_alunos_mensagens cpm 
                     WHERE cpm.chat_id = cpa.id 
                     ORDER BY cpm.created_at DESC 
                     LIMIT 1) as ultima_mensagem,
                    (SELECT cpm.created_at 
                     FROM chat_professores_alunos_mensagens cpm 
                     WHERE cpm.chat_id = cpa.id 
                     ORDER BY cpm.created_at DESC 
                     LIMIT 1) as ultima_mensagem_data
             FROM chat_professores_alunos cpa
             JOIN professores p ON cpa.professor_id = p.id
             WHERE cpa.aluno_id = :aluno_id
             ORDER BY cpa.ultima_atividade DESC",
            ['aluno_id' => $aluno['id']]
        );
        
        // Busca professores da turma do aluno
        $professores_turma = $this->db->fetchAll(
            "SELECT DISTINCT p.*
             FROM professores p
             JOIN jornadas j ON j.professor_id = p.id
             WHERE j.turma_id = :turma_id
             ORDER BY p.nome ASC",
            ['turma_id' => $aluno['turma_id']]
        );
        
        $data = [
            'title' => 'Chat com Professor - EducaTudo',
            'aluno' => $aluno,
            'conversas' => $conversas,
            'professores_turma' => $professores_turma,
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'chat'
        ];
        
        // Verifica se a view existe antes de renderizar
        $viewPath = __DIR__ . '/../../Views/student/chat/index.php';
        if (file_exists($viewPath)) {
            $this->viewWithLayout('student', 'student/chat/index', $data);
        } else {
            // View ainda não criada, redireciona para dashboard
            $_SESSION['info_message'] = 'Funcionalidade de chat em desenvolvimento';
            $this->redirect('/dashboard');
        }
    }
    
    /**
     * Chat do aluno com um professor específico
     */
    public function chatAluno($professor_id)
    {
        $user = $this->authManager->getUser();
        
        if ($user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }
        
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome 
             FROM alunos a 
             LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );
        
        // Busca dados do professor
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :prof_id",
            ['prof_id' => $professor_id]
        );
        
        if (!$professor) {
            $_SESSION['error_message'] = 'Professor não encontrado';
            $this->redirect('/chat-professor');
            return;
        }
        
        // Busca ou cria conversa
        $chat = $this->db->fetch(
            "SELECT * FROM chat_professores_alunos 
             WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
            ['aluno_id' => $aluno['id'], 'prof_id' => $professor_id]
        );
        
        if (!$chat) {
            $chat_id = $this->db->insert(
                "INSERT INTO chat_professores_alunos (aluno_id, professor_id) 
                 VALUES (:aluno_id, :prof_id)",
                ['aluno_id' => $aluno['id'], 'prof_id' => $professor_id]
            );
            $chat = $this->db->fetch(
                "SELECT * FROM chat_professores_alunos WHERE id = :chat_id",
                ['chat_id' => $chat_id]
            );
        }
        
        // Busca mensagens
        $mensagens = $this->db->fetchAll(
            "SELECT cpm.*, 
                    a.nome as aluno_nome,
                    p.nome as professor_nome
             FROM chat_professores_alunos_mensagens cpm
             LEFT JOIN alunos a ON cpm.remetente_tipo = 'aluno' AND cpm.remetente_id = a.id
             LEFT JOIN professores p ON cpm.remetente_tipo = 'professor' AND cpm.remetente_id = p.id
             WHERE cpm.chat_id = :chat_id
             ORDER BY cpm.created_at ASC",
            ['chat_id' => $chat['id']]
        );
        
        // Busca anexos das mensagens
        foreach ($mensagens as &$mensagem) {
            $mensagem['anexos'] = $this->db->fetchAll(
                "SELECT * FROM chat_professores_alunos_anexos 
                 WHERE mensagem_id = :mensagem_id
                 ORDER BY created_at ASC",
                ['mensagem_id' => $mensagem['id']]
            );
        }
        
        // Marca mensagens como lidas
        $this->db->update(
            "UPDATE chat_professores_alunos_mensagens 
             SET lida = 1, lida_em = NOW() 
             WHERE chat_id = :chat_id 
             AND remetente_tipo = 'professor' 
             AND lida = 0",
            ['chat_id' => $chat['id']]
        );
        
        $data = [
            'title' => 'Chat com ' . $professor['nome'] . ' - EducaTudo',
            'aluno' => $aluno,
            'professor' => $professor,
            'chat' => $chat,
            'mensagens' => $mensagens,
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'chat'
        ];
        
        // Verifica se a view existe antes de renderizar
        $viewPath = __DIR__ . '/../../Views/student/chat/chat.php';
        if (file_exists($viewPath)) {
            $this->viewWithLayout('student', 'student/chat/chat', $data);
        } else {
            // View ainda não criada, redireciona para dashboard
            $_SESSION['info_message'] = 'Funcionalidade de chat em desenvolvimento';
            $this->redirect('/dashboard');
        }
    }
    
    /**
     * Enviar mensagem (aluno)
     */
    public function enviarMensagemAluno()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            
            $professor_id = $_POST['professor_id'] ?? null;
            $mensagem = trim($_POST['mensagem'] ?? '');
            
            if (empty($professor_id) || empty($mensagem)) {
                throw new Exception('Professor e mensagem são obrigatórios');
            }
            
            $aluno = $this->db->fetch(
                "SELECT * FROM alunos WHERE id = :user_id",
                ['user_id' => $user['id']]
            );
            
            // Busca ou cria conversa
            $chat = $this->db->fetch(
                "SELECT * FROM chat_professores_alunos 
                 WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
                ['aluno_id' => $aluno['id'], 'prof_id' => $professor_id]
            );
            
            if (!$chat) {
                $chat_id = $this->db->insert(
                    "INSERT INTO chat_professores_alunos (aluno_id, professor_id) 
                     VALUES (:aluno_id, :prof_id)",
                    ['aluno_id' => $aluno['id'], 'prof_id' => $professor_id]
                );
            } else {
                $chat_id = $chat['id'];
            }
            
            // Insere mensagem
            $mensagem_id = $this->db->insert(
                "INSERT INTO chat_professores_alunos_mensagens 
                 (chat_id, remetente_tipo, remetente_id, mensagem) 
                 VALUES (:chat_id, 'aluno', :aluno_id, :mensagem)",
                [
                    'chat_id' => $chat_id,
                    'aluno_id' => $aluno['id'],
                    'mensagem' => $mensagem
                ]
            );
            
            // Atualiza última atividade
            $this->db->update(
                "UPDATE chat_professores_alunos 
                 SET ultima_atividade = NOW() 
                 WHERE id = :chat_id",
                ['chat_id' => $chat_id]
            );
            
            // Busca mensagem criada
            $mensagem_criada = $this->db->fetch(
                "SELECT cpm.*, a.nome as aluno_nome
                 FROM chat_professores_alunos_mensagens cpm
                 LEFT JOIN alunos a ON cpm.remetente_id = a.id
                 WHERE cpm.id = :mensagem_id",
                ['mensagem_id' => $mensagem_id]
            );
            
            $mensagem_criada['anexos'] = [];
            
            $this->json(['success' => true, 'mensagem' => $mensagem_criada]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Buscar novas mensagens (aluno)
     */
    public function buscarMensagensAluno()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            
            $professor_id = $_GET['professor_id'] ?? null;
            $ultima_mensagem_id = $_GET['ultima_mensagem_id'] ?? 0;
            
            if (empty($professor_id)) {
                throw new Exception('Professor é obrigatório');
            }
            
            $aluno = $this->db->fetch(
                "SELECT * FROM alunos WHERE id = :user_id",
                ['user_id' => $user['id']]
            );
            
            $chat = $this->db->fetch(
                "SELECT * FROM chat_professores_alunos 
                 WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
                ['aluno_id' => $aluno['id'], 'prof_id' => $professor_id]
            );
            
            if (!$chat) {
                $this->json(['success' => true, 'mensagens' => []]);
                return;
            }
            
            // Busca mensagens novas
            $mensagens = $this->db->fetchAll(
                "SELECT cpm.*, 
                        a.nome as aluno_nome,
                        p.nome as professor_nome
                 FROM chat_professores_alunos_mensagens cpm
                 LEFT JOIN alunos a ON cpm.remetente_tipo = 'aluno' AND cpm.remetente_id = a.id
                 LEFT JOIN professores p ON cpm.remetente_tipo = 'professor' AND cpm.remetente_id = p.id
                 WHERE cpm.chat_id = :chat_id 
                   AND cpm.id > :ultima_mensagem_id
                 ORDER BY cpm.created_at ASC",
                [
                    'chat_id' => $chat['id'],
                    'ultima_mensagem_id' => $ultima_mensagem_id
                ]
            );
            
            // Busca anexos das mensagens
            foreach ($mensagens as &$mensagem) {
                $mensagem['anexos'] = $this->db->fetchAll(
                    "SELECT * FROM chat_professores_alunos_anexos 
                     WHERE mensagem_id = :mensagem_id
                     ORDER BY created_at ASC",
                    ['mensagem_id' => $mensagem['id']]
                );
            }
            
            // Marca mensagens como lidas
            if (!empty($mensagens)) {
                $this->db->update(
                    "UPDATE chat_professores_alunos_mensagens 
                     SET lida = 1, lida_em = NOW() 
                     WHERE chat_id = :chat_id 
                     AND remetente_tipo = 'professor' 
                     AND lida = 0",
                    ['chat_id' => $chat['id']]
                );
            }
            
            $this->json(['success' => true, 'mensagens' => $mensagens]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Redireciona para o dashboard correto baseado no tipo
     */
    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'professor':
                $this->redirect('/teacher/dashboard');
                break;
            case 'admin_escola':
            case 'admin':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/');
        }
    }
}
}

