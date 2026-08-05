<?php
/**
 * Controller para Sistema de Tickets de Suporte
 * Permite que alunos criem e gerenciem tickets de suporte
 */

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Core/Database.php';

if (!class_exists('SupportTicketController')) {
class SupportTicketController extends BaseController
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
        }
        
        // Verifica se é aluno
        $user = $this->authManager->getUser();
        if ($user['tipo'] !== 'aluno') {
            $this->redirect('/logout');
        }
    }
    
    /**
     * Listar tickets do aluno
     */
    public function index()
    {
        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome 
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id 
                 WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            // Buscar tickets do aluno
            $tickets = $this->db->fetchAll(
                "SELECT t.*,
                        (SELECT COUNT(*) FROM suporte_tickets_mensagens tm WHERE tm.ticket_id = t.id) as total_mensagens,
                        (SELECT COUNT(*) FROM suporte_tickets_mensagens tm2 
                         WHERE tm2.ticket_id = t.id 
                         AND tm2.remetente_tipo = 'admin' 
                         AND tm2.lida = 0) as respostas_nao_lidas,
                        (SELECT MAX(tm.criado_em) FROM suporte_tickets_mensagens tm WHERE tm.ticket_id = t.id) as ultima_mensagem
                 FROM suporte_tickets t
                 WHERE t.aluno_id = :aluno_id
                 ORDER BY t.criado_em DESC",
                ['aluno_id' => $aluno['id']]
            );

            $data = [
                'title' => 'Tickets de Suporte',
                'user' => $user,
                'aluno' => $aluno,
                'tickets' => $tickets,
                'current_page' => 'tickets',
                'csrf_token' => $this->generateCsrfToken()
            ];

            $this->viewWithLayout('student', 'student/tickets/index', $data);
            
        } catch (Exception $e) {
            error_log("Erro em TicketController::index: " . $e->getMessage());
            $this->redirect('/dashboard?error=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Visualizar ticket específico
     * Segurança IDOR (V-01): verificação explícita de propriedade no servidor.
     * Nunca confiar apenas no ID da URL; garantir que ticket pertence ao usuário autenticado.
     */
    public function visualizar()
    {
        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome 
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id 
                 WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $ticketId = $_GET['id'] ?? '';
            
            if (empty($ticketId)) {
                $this->redirect('/tickets');
                return;
            }

            // IDOR: Buscar ticket apenas pelo ID primeiro para verificar propriedade
            $ticket = $this->db->fetch(
                "SELECT * FROM suporte_tickets WHERE id = :ticket_id",
                ['ticket_id' => $ticketId]
            );

            if (!$ticket) {
                $this->redirect('/tickets');
                return;
            }

            // IDOR: Validar que o ticket pertence ao usuário autenticado (ticket.aluno_id === aluno.id)
            if ((int) $ticket['aluno_id'] !== (int) $aluno['id']) {
                http_response_code(403);
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Acesso negado</title></head><body><h1>403 Forbidden</h1><p>Acesso negado a este recurso.</p><p><a href="' . htmlspecialchars(URL . '/tickets') . '">Voltar aos tickets</a></p></body></html>';
                exit;
            }

            // Buscar mensagens
            $mensagens = $this->db->fetchAll(
                "SELECT tm.*,
                        CASE 
                            WHEN tm.remetente_tipo = 'aluno' THEN 'Você'
                            ELSE 'Suporte'
                        END as remetente_nome
                 FROM suporte_tickets_mensagens tm
                 WHERE tm.ticket_id = :ticket_id
                 ORDER BY tm.criado_em ASC",
                ['ticket_id' => $ticketId]
            );

            // Marca mensagens do admin como lidas
            $this->db->update(
                "UPDATE suporte_tickets_mensagens SET lida = 1 WHERE ticket_id = :ticket_id AND remetente_tipo = 'admin' AND lida = 0",
                ['ticket_id' => $ticketId]
            );

            $data = [
                'title' => 'Ticket #' . $ticket['id'],
                'user' => $user,
                'aluno' => $aluno,
                'ticket' => $ticket,
                'mensagens' => $mensagens,
                'current_page' => 'tickets',
                'csrf_token' => $this->generateCsrfToken()
            ];

            $this->viewWithLayout('student', 'student/tickets/visualizar', $data);
            
        } catch (Exception $e) {
            error_log("Erro em TicketController::visualizar: " . $e->getMessage());
            $this->redirect('/tickets?error=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Criar novo ticket
     */
    public function criar()
    {
        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome 
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id 
                 WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $data = [
                'title' => 'Criar Novo Ticket',
                'user' => $user,
                'aluno' => $aluno,
                'current_page' => 'tickets',
                'csrf_token' => $this->generateCsrfToken()
            ];

            $this->viewWithLayout('student', 'student/tickets/criar', $data);
            
        } catch (Exception $e) {
            error_log("Erro em TicketController::criar: " . $e->getMessage());
            $this->redirect('/tickets?error=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Processar criação de ticket
     */
    public function processarCriar()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $assunto = trim($_POST['assunto'] ?? '');
            $categoria = $_POST['categoria'] ?? 'geral';
            $mensagem = trim($_POST['mensagem'] ?? '');
            $modulo = trim($_POST['modulo'] ?? '');

            if (empty($assunto) || empty($mensagem)) {
                throw new Exception('Assunto e mensagem são obrigatórios');
            }

            $categoriasValidas = ['geral', 'tecnico', 'conteudo', 'conta', 'problema', 'outro'];
            if (!in_array($categoria, $categoriasValidas)) {
                $categoria = 'geral';
            }

            if ($categoria !== 'problema') {
                $modulo = '';
            }

            $ticketId = $this->db->insert(
                "INSERT INTO suporte_tickets (aluno_id, assunto, categoria, modulo, status, prioridade) 
                 VALUES (:aluno_id, :assunto, :categoria, :modulo, 'aberto', 'normal')",
                [
                    'aluno_id' => $aluno['id'],
                    'assunto' => $assunto,
                    'categoria' => $categoria,
                    'modulo' => $modulo ?: null
                ]
            );

            // Adicionar primeira mensagem
            $this->db->insert(
                "INSERT INTO suporte_tickets_mensagens (ticket_id, remetente_tipo, remetente_id, mensagem, lida) 
                 VALUES (:ticket_id, 'aluno', :remetente_id, :mensagem, 0)",
                [
                    'ticket_id' => $ticketId,
                    'remetente_id' => $aluno['id'],
                    'mensagem' => $mensagem
                ]
            );

            $this->json([
                'success' => true,
                'ticket_id' => $ticketId,
                'message' => 'Ticket criado com sucesso!'
            ]);

        } catch (Exception $e) {
            error_log("Erro em TicketController::processarCriar: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Upload de arquivos do ticket (imagem, documento, etc)
     */
    public function uploadArquivo()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $user = $this->authManager->getUser();
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $file = $_FILES['arquivo'] ?? $_FILES['imagem'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload do arquivo');
            }

            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
                'application/zip',
                'application/x-zip-compressed',
                'application/rar',
                'application/x-rar-compressed',
                'application/octet-stream'
            ];

            $extension = strtolower((string) pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            $allowedExtensions = [
                'jpg', 'jpeg', 'png', 'gif', 'webp',
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                'txt', 'csv', 'zip', 'rar'
            ];

            if (!in_array((string) ($file['type'] ?? ''), $allowedTypes, true) && !in_array($extension, $allowedExtensions, true)) {
                throw new Exception('Tipo de arquivo não permitido. Use imagens, PDF, Word, Excel, PowerPoint, TXT, CSV, ZIP ou RAR.');
            }

            if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande (máximo 15MB)');
            }

            $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo((string) $file['name'], PATHINFO_FILENAME));
            $safeBaseName = trim((string) $safeBaseName, '-');
            if ($safeBaseName === '') {
                $safeBaseName = 'arquivo-ticket';
            }

            $filename = 'ticket_' . $aluno['id'] . '_' . time() . '_' . uniqid() . '_' . $safeBaseName . ($extension ? '.' . $extension : '');

            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $key = MediaStorageService::userKey('student', $aluno['id'], $filename);
            $media = new MediaStorageService($this->config);

            if (!$media->put('tickets', $key, $file['tmp_name'], (string) ($file['type'] ?? 'application/octet-stream'))) {
                throw new Exception('Erro ao salvar arquivo');
            }

            $path = (defined('FOLDER') && FOLDER !== '') ? rtrim(FOLDER, '/') : '';
            $path .= '/media/serve?type=tickets&key=' . rawurlencode($key);
            if ($path !== '' && strpos($path, '/') !== 0) {
                $path = '/' . $path;
            }

            $mimeType = (string) ($file['type'] ?? '');
            $isImage = strpos($mimeType, 'image/') === 0 || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);

            $this->json([
                'success' => true,
                'url' => $path,
                'name' => (string) ($file['name'] ?? 'arquivo'),
                'type' => $mimeType ?: 'application/octet-stream',
                'is_image' => $isImage,
            ]);
        } catch (Exception $e) {
            error_log("Erro em TicketController::uploadArquivo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Compatibilidade com o upload antigo do editor.
     */
    public function uploadImagem()
    {
        $this->uploadArquivo();
    }

    /**
     * Enviar mensagem em um ticket
     */
    public function enviarMensagem()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $ticketId = intval($_POST['ticket_id'] ?? 0);
            $mensagem = trim($_POST['mensagem'] ?? '');

            if (empty($mensagem)) {
                throw new Exception('Mensagem não pode estar vazia');
            }

            if ($ticketId <= 0) {
                throw new Exception('Ticket ID inválido');
            }

            // Verificar se o ticket pertence ao aluno
            $ticket = $this->db->fetch(
                "SELECT * FROM suporte_tickets WHERE id = :ticket_id AND aluno_id = :aluno_id",
                ['ticket_id' => $ticketId, 'aluno_id' => $aluno['id']]
            );

            if (!$ticket) {
                throw new Exception('Ticket não encontrado');
            }

            if ($ticket['status'] === 'fechado') {
                throw new Exception('Este ticket está fechado e não aceita novas mensagens');
            }

            // Inserir mensagem
            $this->db->insert(
                "INSERT INTO suporte_tickets_mensagens (ticket_id, remetente_tipo, remetente_id, mensagem, lida) 
                 VALUES (:ticket_id, 'aluno', :remetente_id, :mensagem, 0)",
                [
                    'ticket_id' => $ticketId,
                    'remetente_id' => $aluno['id'],
                    'mensagem' => $mensagem
                ]
            );

            // Atualizar status do ticket
            $this->db->update(
                "UPDATE suporte_tickets SET status = 'aberto', atualizado_em = NOW() WHERE id = :ticket_id",
                ['ticket_id' => $ticketId]
            );

            $this->json([
                'success' => true,
                'message' => 'Mensagem enviada com sucesso!'
            ]);

        } catch (Exception $e) {
            error_log("Erro em TicketController::enviarMensagem: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
}
