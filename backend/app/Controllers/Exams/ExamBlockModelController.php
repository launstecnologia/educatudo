<?php
/**
 * Controller para gerenciar Blocos Modelo
 * Acesso: Coordenação
 */

// BaseController, AuthManager e Database são carregados automaticamente pelo App.php
// Mas incluímos aqui para garantir compatibilidade quando o controller é chamado diretamente
// Caminho correto: de app/Controllers/Exams/ para app/Core/ = ../../Core/
if (!class_exists('BaseController')) {
    require_once __DIR__ . '/../../Core/BaseController.php';
}
if (!class_exists('AuthManager')) {
    require_once __DIR__ . '/../../Core/AuthManager.php';
}
if (!class_exists('Database')) {
    require_once __DIR__ . '/../../Core/Database.php';
}
require_once __DIR__ . '/../../Models/Exams/ExamBlockModel.php';

if (!class_exists('ExamBlockModelController')) {
class ExamBlockModelController extends BaseController
{
    private $authManager;
    private $db;
    private $modeloModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $this->modeloModel = new ExamBlockModel();
        
        // Verifica se está logado
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        
        // Verifica permissão (apenas admin/coordenação)
        $user = $this->authManager->getUser();
        if (!$user || ($user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola')) {
            $this->redirect('/admin/dashboard');
            return;
        }
        
        if (!class_exists('AdminSecretariaAccess')) {
            require_once __DIR__ . '/../../Core/AdminSecretariaAccess.php';
        }
        // Verifica perfil_admin se for admin_escola
        if ($user['tipo'] === 'admin_escola' && !in_array($user['perfil_admin'] ?? '', AdminSecretariaAccess::perfisAdminEscolaGestaoPedagogica(), true)) {
            $this->redirect('/admin/dashboard');
            return;
        }
    }
    
    /**
     * Lista todos os blocos modelo
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        
        $modelos = $this->modeloModel->getAll();
        
        // Adiciona informações adicionais
        foreach ($modelos as &$modelo) {
            $professores = $this->modeloModel->getProfessores($modelo['id']);
            $modelo['total_professores'] = count($professores);
            $modelo['criado_por_nome'] = $user['nome'] ?? 'Sistema';
        }
        
        $this->viewWithLayout('admin', 'admin/blocos-modelo/index', [
            'title' => 'Blocos Modelo - EducaTudo',
            'user' => $user,
            'modelos' => $modelos
        ]);
    }
    
    /**
     * Exibe formulário de criação
     */
    public function criar()
    {
        $user = $this->authManager->getUser();
        
        // Busca professores
        $professores = $this->db->fetchAll(
            "SELECT * FROM professores WHERE ativo = 1 ORDER BY nome ASC"
        );
        
        // Busca matérias (SEM filtro ativo, pois a tabela não tem essa coluna)
        $materias = $this->db->fetchAll(
            "SELECT * FROM materias ORDER BY nome ASC"
        );
        
        // Processa professores para incluir matérias como array de nomes (igual ao criar prova)
        // O campo materias do professor contém nomes (strings) em JSON, não IDs
        foreach ($professores as &$prof) {
            $materiasJson = $prof['materias'] ?? '[]';
            $materiasNomes = json_decode($materiasJson, true) ?: [];
            
            // O campo materias já contém nomes (strings), usa diretamente
            $prof['materias'] = is_array($materiasNomes) ? $materiasNomes : [];
        }
        
        $data = [
            'title' => 'Criar Bloco Modelo - EducaTudo',
            'user' => $user,
            'professores' => $professores,
            'materias' => $materias
        ];
        
        $this->viewWithLayout('admin', 'admin/blocos-modelo/criar', $data);
    }
    
    /**
     * Salva novo bloco modelo
     */
    public function salvar()
    {
        $user = $this->authManager->getUser();
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['nome'])) {
                throw new Exception('Nome é obrigatório');
            }
            
            if (empty($input['professores']) || !is_array($input['professores'])) {
                throw new Exception('Adicione pelo menos um professor');
            }
            
            $modeloId = $this->modeloModel->create([
                'nome' => $input['nome'],
                'descricao' => $input['descricao'] ?? null,
                'professores' => $input['professores'],
                'criado_por' => $user['id'] ?? null
            ]);
            
            $this->json([
                'success' => true,
                'message' => 'Bloco modelo criado com sucesso!',
                'id' => $modeloId
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao criar bloco modelo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exibe formulário de edição
     */
    public function editar($id)
    {
        $user = $this->authManager->getUser();
        
        $modelo = $this->modeloModel->findById($id);
        if (!$modelo) {
            header('Location: ' . URL . '/admin/blocos-modelo');
            exit;
        }
        
        $modelo['professores'] = $this->modeloModel->getProfessores($id);
        
        // Busca professores
        $professores = $this->db->fetchAll(
            "SELECT * FROM professores WHERE ativo = 1 ORDER BY nome ASC"
        );
        
        // Busca matérias (SEM filtro ativo)
        $materias = $this->db->fetchAll(
            "SELECT * FROM materias ORDER BY nome ASC"
        );
        
        // Processa professores para incluir matérias como array de nomes (igual ao criar prova)
        // O campo materias do professor contém nomes (strings) em JSON, não IDs
        foreach ($professores as &$prof) {
            $materiasJson = $prof['materias'] ?? '[]';
            $materiasNomes = json_decode($materiasJson, true) ?: [];
            
            // O campo materias já contém nomes (strings), usa diretamente
            $prof['materias'] = is_array($materiasNomes) ? $materiasNomes : [];
        }
        
        $this->viewWithLayout('admin', 'admin/blocos-modelo/editar', [
            'title' => 'Editar Bloco Modelo - EducaTudo',
            'user' => $user,
            'modelo' => $modelo,
            'professores' => $professores,
            'materias' => $materias
        ]);
    }
    
    /**
     * Atualiza bloco modelo
     */
    public function atualizar($id)
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['nome'])) {
                throw new Exception('Nome é obrigatório');
            }
            
            if (empty($input['professores']) || !is_array($input['professores'])) {
                throw new Exception('Adicione pelo menos um professor');
            }
            
            $this->modeloModel->update($id, [
                'nome' => $input['nome'],
                'descricao' => $input['descricao'] ?? null,
                'professores' => $input['professores']
            ]);
            
            $this->json([
                'success' => true,
                'message' => 'Bloco modelo atualizado com sucesso!'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar bloco modelo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exclui bloco modelo
     */
    public function excluir($id)
    {
        try {
            $this->modeloModel->delete($id);
            
            $this->json([
                'success' => true,
                'message' => 'Bloco modelo excluído com sucesso!'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao excluir bloco modelo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Retorna dados do modelo (para AJAX)
     */
    public function dados($id)
    {
        $modelo = $this->modeloModel->findById($id);
        if (!$modelo) {
            $this->json(['error' => 'Modelo não encontrado'], 404);
            return;
        }
        
        $modelo['professores'] = $this->modeloModel->getProfessores($id);
        
        $this->json([
            'success' => true,
            'modelo' => $modelo
        ]);
    }
}
}
