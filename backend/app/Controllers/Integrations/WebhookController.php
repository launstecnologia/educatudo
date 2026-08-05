<?php
/**
 * EducaTudo - Admin Webhook Controller
 * Gerencia webhooks no painel administrativo
 */

if (!class_exists('WebhookController')) {
class WebhookController extends BaseController
{
    private $auth;
    private $db;
    private $webhookManager;
    
    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
        $this->db = Database::getInstance();
        $this->webhookManager = new WebhookManager();
        
        // Verifica se está logado
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
        }
        
        // Verifica se é admin
        $user = $this->auth->getUser();
        if ($user['tipo'] !== 'admin_escola') {
            $this->redirect('/');
        }
    }
    
    public function index()
    {
        try {
            $webhooks = $this->webhookManager->getAllWebhooks();
            
            // Se for uma requisição AJAX, retorna JSON
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['success' => true, 'webhooks' => $webhooks]);
                return;
            }
            
            $data = [
                'title' => 'Configuração de Webhooks - EducaTudo',
                'webhooks' => $webhooks,
                'current_page' => 'dev'
            ];
            
            $this->viewWithLayout('admin', 'admin/webhooks/index', $data);
            
        } catch (Exception $e) {
            // Se for uma requisição AJAX, retorna JSON
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['success' => false, 'error' => $e->getMessage()], 400);
                return;
            }
            
            $this->viewWithLayout('admin', 'admin/webhooks/index', [
                'title' => 'Configuração de Webhooks - EducaTudo',
                'webhooks' => [],
                'error' => $e->getMessage(),
                'current_page' => 'dev'
            ]);
        }
    }
    
    public function create()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $nome = $_POST['nome'] ?? '';
            $endpoint = $_POST['endpoint'] ?? '';
            $tipo = $_POST['tipo'] ?? '';
            $escola_id = !empty($_POST['escola_id']) ? $_POST['escola_id'] : null;
            
            if (empty($nome) || empty($endpoint) || empty($tipo)) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            // Validar URL
            if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
                throw new Exception('URL do endpoint inválida');
            }
            
            $webhookId = $this->webhookManager->createWebhook($nome, $endpoint, $tipo, $escola_id);
            
            $this->json(['success' => true, 'webhook_id' => $webhookId, 'message' => 'Webhook criado com sucesso!']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function update()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $id = $_POST['id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $endpoint = $_POST['endpoint'] ?? '';
            $tipo = $_POST['tipo'] ?? '';
            $escola_id = !empty($_POST['escola_id']) ? $_POST['escola_id'] : null;
            $ativo = isset($_POST['ativo']) ? (bool)$_POST['ativo'] : true;
            
            if (empty($id) || empty($nome) || empty($endpoint) || empty($tipo)) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            // Validar URL
            if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
                throw new Exception('URL do endpoint inválida');
            }
            
            $this->webhookManager->updateWebhook($id, $nome, $endpoint, $tipo, $escola_id, null, $ativo);
            
            $this->json(['success' => true, 'message' => 'Webhook atualizado com sucesso!']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function delete()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID do webhook é obrigatório');
            }
            
            $this->webhookManager->deleteWebhook($id);
            
            $this->json(['success' => true, 'message' => 'Webhook excluído com sucesso!']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function test()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID do webhook é obrigatório');
            }
            
            $result = $this->webhookManager->testWebhook($id);
            
            $this->json($result);
            
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    
    public function testById($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            if (empty($id)) {
                throw new Exception('ID do webhook é obrigatório');
            }
            
            $result = $this->webhookManager->testWebhook($id);
            
            $this->json($result);
            
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
}
