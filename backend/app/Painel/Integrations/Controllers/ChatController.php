<?php

namespace App\Painel\Integrations\Controllers;
/**
 * EducaTudo - Controller de Chat Global
 * Gerencia chat global entre aluno e professor
 */


use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;

require_once dirname(__DIR__, 3) . '/Policies/PoliticaAcessoProfessorAluno.php';

if (!class_exists(__NAMESPACE__ . '\\ChatController', false)) {
class ChatController extends BaseController
{
    private $authManager;
    private $db;
    /** @var PoliticaAcessoProfessorAluno|null */
    private $politicaAcesso;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $this->politicaAcesso = new \PoliticaAcessoProfessorAluno($this->db);
        
        // Verifica se está logado
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
    }

    /**
     * Professor autenticado na sessão (nunca confiar em id do request).
     */
    private function professorDaSessao(): ?array
    {
        $user = $this->authManager->getUser();
        if (($user['tipo'] ?? '') !== 'professor') {
            return null;
        }
        $professor = $this->db->fetch(
            'SELECT * FROM professores WHERE id = :user_id LIMIT 1',
            ['user_id' => (int) $user['id']]
        );
        return $professor ?: null;
    }

    private function alunoDaSessao(): ?array
    {
        $user = $this->authManager->getUser();
        if (($user['tipo'] ?? '') !== 'aluno') {
            return null;
        }
        $aluno = $this->db->fetch(
            'SELECT a.*, t.nome as turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.id = :user_id
             LIMIT 1',
            ['user_id' => (int) $user['id']]
        );
        return $aluno ?: null;
    }

    private function professorPodeAluno(int $professorId, int $alunoId): bool
    {
        return $this->politicaAcesso->podeAcessar($professorId, $alunoId);
    }

    /** HTML: redireciona sem vazar dados. JSON: 403 genérico. */
    private function negarAcessoChat(string $redirectPath, bool $json = false): void
    {
        if ($json) {
            $this->json(['error' => 'Acesso negado'], 403);
            return;
        }
        $_SESSION['error_message'] = 'Acesso negado.';
        $this->redirect($redirectPath);
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
        
        // Conversas do professor — só alunos com vínculo escolar ainda válido
        $conversasBrutas = $this->db->fetchAll(
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
        $conversas = [];
        foreach ($conversasBrutas as $conversa) {
            if ($this->professorPodeAluno((int) $professor['id'], (int) ($conversa['aluno_id'] ?? 0))) {
                $conversas[] = $conversa;
            }
        }

        // Alunos vinculados para iniciar conversa (mesmo critério de ownership)
        $alunos_disponiveis = $this->politicaAcesso->listarAlunosVinculados((int) $professor['id']);
        
        $data = [
            'title' => 'Chat com Alunos - EducaTudo',
            'professor' => $professor,
            'conversas' => $conversas,
            'alunos_disponiveis' => $alunos_disponiveis,
            'user' => $user,
            'current_page' => 'chat'
        ];

        // Resolve via BaseController → Modulos/comunicacao/Views/professor/index.php
        $this->viewWithLayout('professor', 'teacher/chat/index', $data);
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
            
            $professor = $this->professorDaSessao();
            if (!$professor) {
                $this->negarAcessoChat('/professor/chat');
                return;
            }

            $alunoId = (int) $aluno_id;
            if ($alunoId <= 0 || !$this->professorPodeAluno((int) $professor['id'], $alunoId)) {
                // Sem vínculo (ou aluno inexistente): não cria registro e não vaza dados.
                $this->negarAcessoChat('/professor/chat');
                return;
            }
            
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome 
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id 
                 WHERE a.id = :aluno_id AND a.ativo = 1",
                ['aluno_id' => $alunoId]
            );
            
            if (!$aluno) {
                $this->negarAcessoChat('/professor/chat');
                return;
            }
            
            // Busca ou cria conversa (só após vínculo autorizado)
            $chat = $this->db->fetch(
                "SELECT * FROM chat_professores_alunos 
                 WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
                ['aluno_id' => $alunoId, 'prof_id' => $professor['id']]
            );
            
            if (!$chat) {
                $chat_id = $this->db->insert(
                    "INSERT INTO chat_professores_alunos (aluno_id, professor_id) 
                     VALUES (:aluno_id, :prof_id)",
                    ['aluno_id' => $alunoId, 'prof_id' => $professor['id']]
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

            // Resolve via BaseController → Modulos/comunicacao/Views/professor/chat.php
            $this->viewWithLayout('professor', 'teacher/chat/chat', $data);
            return;
        } catch (\Exception $e) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log('chatProfessor: ' . $e->getMessage());
            }
            $_SESSION['error_message'] = 'Erro ao carregar chat.';
            $this->redirect('/professor/chat');
            return;
        } catch (\Throwable $e) {
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
            
            $alunoId = (int) ($_POST['aluno_id'] ?? 0);
            $mensagem = trim($_POST['mensagem'] ?? '');
            
            if ($alunoId <= 0 || $mensagem === '') {
                throw new \Exception('Aluno e mensagem são obrigatórios');
            }
            
            $professor = $this->professorDaSessao();
            if (!$professor) {
                $this->negarAcessoChat('/professor/chat', true);
                return;
            }

            if (!$this->professorPodeAluno((int) $professor['id'], $alunoId)) {
                $this->negarAcessoChat('/professor/chat', true);
                return;
            }
            
            // Busca ou cria conversa (somente com vínculo válido)
            $chat = $this->db->fetch(
                "SELECT * FROM chat_professores_alunos 
                 WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
                ['aluno_id' => $alunoId, 'prof_id' => $professor['id']]
            );
            
            if (!$chat) {
                $chat_id = $this->db->insert(
                    "INSERT INTO chat_professores_alunos (aluno_id, professor_id) 
                     VALUES (:aluno_id, :prof_id)",
                    ['aluno_id' => $alunoId, 'prof_id' => $professor['id']]
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
            
        } catch (\Exception $e) {
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
            
            $alunoId = (int) ($_GET['aluno_id'] ?? 0);
            $ultima_mensagem_id = (int) ($_GET['ultima_mensagem_id'] ?? 0);
            
            if ($alunoId <= 0) {
                throw new \Exception('Aluno é obrigatório');
            }
            
            $professor = $this->professorDaSessao();
            if (!$professor) {
                $this->negarAcessoChat('/professor/chat', true);
                return;
            }

            // Conversa antiga sem vínculo atual não libera leitura.
            if (!$this->professorPodeAluno((int) $professor['id'], $alunoId)) {
                $this->negarAcessoChat('/professor/chat', true);
                return;
            }
            
            $chat = $this->db->fetch(
                "SELECT * FROM chat_professores_alunos 
                 WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
                ['aluno_id' => $alunoId, 'prof_id' => $professor['id']]
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
            
        } catch (\Exception $e) {
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
        
        $aluno = $this->alunoDaSessao();
        if (!$aluno) {
            $this->negarAcessoChat('/dashboard');
            return;
        }
        
        // Conversas — só com professores ainda vinculados à turma do aluno
        $conversasBrutas = $this->db->fetchAll(
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
        $conversas = [];
        foreach ($conversasBrutas as $conversa) {
            if ($this->professorPodeAluno((int) ($conversa['professor_id'] ?? 0), (int) $aluno['id'])) {
                $conversas[] = $conversa;
            }
        }
        
        // Professores da turma: mesma regra da política (jornada e/ou JSON turmas)
        $candidatos = $this->db->fetchAll(
            "SELECT p.* FROM professores p
             WHERE (p.ativo = 1 OR p.ativo IS NULL)
             ORDER BY p.nome ASC"
        );
        $professores_turma = [];
        foreach ($candidatos as $candidato) {
            if ($this->professorPodeAluno((int) $candidato['id'], (int) $aluno['id'])) {
                $professores_turma[] = $candidato;
            }
        }
        
        $data = [
            'title' => 'Chat com Professor - EducaTudo',
            'aluno' => $aluno,
            'conversas' => $conversas,
            'professores_turma' => $professores_turma,
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'chat'
        ];

        // Resolve via BaseController → Modulos/comunicacao/Views/aluno/index.php
        $this->viewWithLayout('student', 'student/chat/index', $data);
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
        
        $aluno = $this->alunoDaSessao();
        if (!$aluno) {
            $this->negarAcessoChat('/dashboard');
            return;
        }

        $professorId = (int) $professor_id;
        if ($professorId <= 0 || !$this->professorPodeAluno($professorId, (int) $aluno['id'])) {
            $this->negarAcessoChat('/chat-professor');
            return;
        }
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :prof_id AND (ativo = 1 OR ativo IS NULL)",
            ['prof_id' => $professorId]
        );
        
        if (!$professor) {
            $this->negarAcessoChat('/chat-professor');
            return;
        }
        
        // Busca ou cria conversa (após vínculo)
        $chat = $this->db->fetch(
            "SELECT * FROM chat_professores_alunos 
             WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
            ['aluno_id' => $aluno['id'], 'prof_id' => $professorId]
        );
        
        if (!$chat) {
            $chat_id = $this->db->insert(
                "INSERT INTO chat_professores_alunos (aluno_id, professor_id) 
                 VALUES (:aluno_id, :prof_id)",
                ['aluno_id' => $aluno['id'], 'prof_id' => $professorId]
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

        // Resolve via BaseController → Modulos/comunicacao/Views/aluno/chat.php
        $this->viewWithLayout('student', 'student/chat/chat', $data);
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
            
            $professorId = (int) ($_POST['professor_id'] ?? 0);
            $mensagem = trim($_POST['mensagem'] ?? '');
            
            if ($professorId <= 0 || $mensagem === '') {
                throw new \Exception('Professor e mensagem são obrigatórios');
            }
            
            $aluno = $this->alunoDaSessao();
            if (!$aluno) {
                $this->negarAcessoChat('/chat-professor', true);
                return;
            }

            if (!$this->professorPodeAluno($professorId, (int) $aluno['id'])) {
                $this->negarAcessoChat('/chat-professor', true);
                return;
            }
            
            $chat = $this->db->fetch(
                "SELECT * FROM chat_professores_alunos 
                 WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
                ['aluno_id' => $aluno['id'], 'prof_id' => $professorId]
            );
            
            if (!$chat) {
                $chat_id = $this->db->insert(
                    "INSERT INTO chat_professores_alunos (aluno_id, professor_id) 
                     VALUES (:aluno_id, :prof_id)",
                    ['aluno_id' => $aluno['id'], 'prof_id' => $professorId]
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
            
        } catch (\Exception $e) {
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
            
            $professorId = (int) ($_GET['professor_id'] ?? 0);
            $ultima_mensagem_id = (int) ($_GET['ultima_mensagem_id'] ?? 0);
            
            if ($professorId <= 0) {
                throw new \Exception('Professor é obrigatório');
            }
            
            $aluno = $this->alunoDaSessao();
            if (!$aluno) {
                $this->negarAcessoChat('/chat-professor', true);
                return;
            }

            if (!$this->professorPodeAluno($professorId, (int) $aluno['id'])) {
                $this->negarAcessoChat('/chat-professor', true);
                return;
            }
            
            $chat = $this->db->fetch(
                "SELECT * FROM chat_professores_alunos 
                 WHERE aluno_id = :aluno_id AND professor_id = :prof_id",
                ['aluno_id' => $aluno['id'], 'prof_id' => $professorId]
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
            
        } catch (\Exception $e) {
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
                $this->redirect('/professor/dashboard');
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


if (!class_exists('ChatController', false) && class_exists(__NAMESPACE__ . '\\ChatController', false)) {
    class_alias(__NAMESPACE__ . '\\ChatController', 'ChatController');
}
