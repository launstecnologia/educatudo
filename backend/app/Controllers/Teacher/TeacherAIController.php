<?php
/**
 * EducaTudo - Controller de Agentes de IA do Professor
 * Gerencia agentes de IA, documentos e chat RAG
 */

if (!class_exists('TeacherAIController')) {
class TeacherAIController extends BaseController
{
    private $docProcessor;
    private $embeddingService;
    private $ragService;
    private $authManager;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        
        // Verifica se está logado
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/professor');
            return;
        }
        
        // Verifica se é professor
        $user = $this->authManager->getUser();
        if ($user && $user['tipo'] !== 'professor') {
            $this->redirect('/' . ($user['tipo'] === 'aluno' ? 'dashboard' : ($user['tipo'] === 'admin_escola' ? 'admin/dashboard' : 'teacher/dashboard')));
            return;
        }
        
        // Inicializa serviços (sem namespace)
        require_once __DIR__ . '/../../Services/DocumentProcessorService.php';
        require_once __DIR__ . '/../../Services/EmbeddingService.php';
        require_once __DIR__ . '/../../Services/RAGService.php';
        
        $this->docProcessor = new DocumentProcessorService();
        $this->embeddingService = new EmbeddingService();
        $this->ragService = new RAGService();
    }
    
    /**
     * Lista todos os agentes do professor
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->redirect('/login');
            return;
        }
        
        // Buscar dados completos do professor (igual ao show)
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        if ($professor) {
            // Mesclar dados do professor com dados do usuário
            $user = array_merge($user, [
                'avatar_url' => $professor['avatar_url'] ?? $user['avatar_url'] ?? null,
                'nome' => $professor['nome'] ?? $user['nome']
            ]);
        }
        
        $agentes = [];
        $erro = null;
        
        try {
            // Verifica se a tabela existe
            if (!$this->db->tableExists('professores_ia_agentes')) {
                $this->viewWithLayout('professor', 'teacher/ai-agent/index', [
                    'title' => 'Agentes de IA - EducaTudo',
                    'agentes' => [],
                    'erro_setup' => 'As tabelas do sistema de Agentes de IA ainda não foram criadas. Execute o script SQL: database/create_ai_agents.sql',
                    'user' => $user
                ]);
                return;
            }
            
            $agentes = $this->db->fetchAll(
                "SELECT a.*, 
                        COUNT(DISTINCT d.id) as total_documentos,
                        COUNT(DISTINCT c.id) as total_conversas
                 FROM professores_ia_agentes a
                 LEFT JOIN professores_ia_documentos d ON d.agente_id = a.id
                 LEFT JOIN professores_ia_conversas c ON c.agente_id = a.id
                 WHERE a.professor_id = :prof_id
                 GROUP BY a.id
                 ORDER BY a.created_at DESC",
                ['prof_id' => $user['id']]
            );
        } catch (Exception $e) {
            error_log("Erro ao listar agentes: " . $e->getMessage());
            $erro = [
                'mensagem' => $e->getMessage(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            $agentes = [];
        }
        
        $this->viewWithLayout('professor', 'teacher/ai-agent/index', [
            'title' => 'Agentes de IA - EducaTudo',
            'agentes' => $agentes,
            'erro' => $erro,
            'user' => $user
        ]);
    }
    
    /**
     * Exibe formulário de criação de agente
     */
    public function criar()
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->redirect('/login');
            return;
        }
        
        // Buscar dados completos do professor (incluindo avatar)
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        if ($professor) {
            // Mesclar dados do professor com dados do usuário
            $user = array_merge($user, [
                'avatar_url' => $professor['avatar_url'] ?? $user['avatar_url'] ?? null,
                'nome' => $professor['nome'] ?? $user['nome']
            ]);
        }
        
        $csrf_token = $this->generateCsrfToken();
        
        $this->viewWithLayout('professor', 'teacher/ai-agent/criar', [
            'title' => 'Criar Agente de IA - EducaTudo',
            'csrf_token' => $csrf_token,
            'user' => $user
        ]);
    }
    
    /**
     * Gera o prompt do sistema a partir das configurações do agente
     */
    private function gerarPromptDoAgente($config)
    {
        $prompt = "Você é um agente educacional da plataforma EducaTudo.\n\n";
        
        // Estilo de Linguagem
        if (!empty($config['linguagem'])) {
            $estilos = [];
            foreach ($config['linguagem'] as $estilo) {
                switch ($estilo) {
                    case 'ludico':
                        $estilos[] = 'lúdica';
                        break;
                    case 'didatico':
                        $estilos[] = 'didática';
                        break;
                    case 'conversacional':
                        $estilos[] = 'conversacional';
                        break;
                    case 'tecnico':
                        $estilos[] = 'técnica';
                        break;
                    case 'descontraido':
                        $estilos[] = 'descontraída';
                        break;
                    case 'formal':
                        $estilos[] = 'formal';
                        break;
                }
            }
            if (!empty($estilos)) {
                $prompt .= "Atue como um professor virtual com linguagem " . implode(' e ', $estilos) . ".\n\n";
            }
        }
        
        // Forma de Explicar
        if (!empty($config['explicacao'])) {
            $explicacoes = [];
            foreach ($config['explicacao'] as $exp) {
                switch ($exp) {
                    case 'respostas_rapidas':
                        $explicacoes[] = 'forneça respostas rápidas e objetivas';
                        break;
                    case 'passo_a_passo':
                        $explicacoes[] = 'explique sempre passo a passo';
                        break;
                    case 'explicacao_completa':
                        $explicacoes[] = 'forneça explicações completas e detalhadas';
                        break;
                    case 'uso_de_exemplos':
                        $explicacoes[] = 'utilize exemplos práticos';
                        break;
                    case 'analogias_do_cotidiano':
                        $explicacoes[] = 'use analogias do cotidiano para facilitar o entendimento';
                        break;
                }
            }
            if (!empty($explicacoes)) {
                $prompt .= "Explique os conteúdos " . implode(', ', $explicacoes) . ".\n\n";
            }
        }
        
        // Metodologia
        if (!empty($config['metodologia'])) {
            $metodologias = [];
            foreach ($config['metodologia'] as $met) {
                switch ($met) {
                    case 'metodo_socratico':
                        $metodologias[] = 'utilize o método socrático, fazendo perguntas que guiem o aluno ao raciocínio';
                        break;
                    case 'perguntas_antes_da_resposta':
                        $metodologias[] = 'faça perguntas antes de fornecer a resposta completa';
                        break;
                    case 'exercicios_guiados':
                        $metodologias[] = 'proponha exercícios guiados para fixação do conteúdo';
                        break;
                    case 'reforco_positivo':
                        $metodologias[] = 'sempre ofereça reforço positivo ao aluno';
                        break;
                }
            }
            if (!empty($metodologias)) {
                $prompt .= implode("\n", $metodologias) . ".\n\n";
            }
        }
        
        // Postura Emocional
        if (!empty($config['postura'])) {
            $posturas = [];
            foreach ($config['postura'] as $pos) {
                switch ($pos) {
                    case 'empatico':
                        $posturas[] = 'empática';
                        break;
                    case 'paciente':
                        $posturas[] = 'paciente';
                        break;
                    case 'motivador':
                        $posturas[] = 'motivadora';
                        break;
                    case 'inspirador':
                        $posturas[] = 'inspiradora';
                        break;
                }
            }
            if (!empty($posturas)) {
                $prompt .= "Tenha uma postura " . implode(', ', $posturas) . ".\n\n";
            }
        }
        
        // Regras do Agente
        if (!empty($config['regras'])) {
            $regras = [];
            foreach ($config['regras'] as $regra) {
                switch ($regra) {
                    case 'usar_apenas_material_fornecido':
                        $regras[] = 'Utilize exclusivamente os materiais fornecidos pelo professor.';
                        break;
                    case 'avisar_quando_nao_souber':
                        $regras[] = 'Caso não encontre a informação nos documentos, informe claramente que não sabe.';
                        break;
                    case 'nao_inventar_conteudo':
                        $regras[] = 'Nunca invente respostas ou informações que não estejam nos materiais fornecidos.';
                        break;
                    case 'manter_nivel_do_aluno':
                        $regras[] = 'Mantenha o nível adequado ao aluno, adaptando a linguagem e complexidade.';
                        break;
                    case 'nao_fugir_do_tema':
                        $regras[] = 'Mantenha o foco no tema da pergunta, sem divagações desnecessárias.';
                        break;
                }
            }
            if (!empty($regras)) {
                $prompt .= implode("\n", $regras) . "\n";
            }
        }
        
        return trim($prompt);
    }
    
    /**
     * Salva novo agente
     */
    public function salvar()
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 403);
            return;
        }
        
        // Lê dados do JSON (quando Content-Type é application/json)
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Se não veio JSON, tenta $_POST
        if (!$data) {
            $data = $_POST;
        }
        
        // Valida CSRF
        if (!$this->verifyCsrfToken($data['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }
        
        $nome = trim($data['nome'] ?? '');
        $descricao = trim($data['descricao'] ?? '');
        
        // Nova estrutura: recebe config em JSON
        $config = $data['config'] ?? [];
        
        // Valores fixos (não exibidos para o professor)
        $modelo = 'gpt-4o-mini';
        $temperatura = 0.7;
        $maxTokens = 2000;
        
        if (empty($nome)) {
            $this->json(['success' => false, 'error' => 'Nome do agente é obrigatório']);
            return;
        }
        
        // Valida se pelo menos uma categoria foi selecionada
        if (empty($config) || (
            empty($config['linguagem']) && 
            empty($config['explicacao']) && 
            empty($config['metodologia']) && 
            empty($config['postura']) && 
            empty($config['regras'])
        )) {
            $this->json(['success' => false, 'error' => 'Selecione pelo menos uma opção para configurar o agente']);
            return;
        }
        
        try {
            // Gera o prompt automaticamente a partir da config
            $systemPrompt = $this->gerarPromptDoAgente($config);
            
            // Prepara config_json para salvar
            $configJson = json_encode([
                'linguagem' => $config['linguagem'] ?? [],
                'explicacao' => $config['explicacao'] ?? [],
                'metodologia' => $config['metodologia'] ?? [],
                'postura' => $config['postura'] ?? [],
                'regras' => $config['regras'] ?? []
            ]);
            
            $agenteId = $this->db->insert(
                "INSERT INTO professores_ia_agentes 
                 (professor_id, nome, descricao, config_json, system_prompt, instrucoes_sistema, modelo_ia, temperatura, max_tokens)
                 VALUES (:prof_id, :nome, :descricao, :config_json, :system_prompt, :instrucoes, :modelo, :temp, :max_tokens)",
                [
                    'prof_id' => $user['id'],
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'config_json' => $configJson,
                    'system_prompt' => $systemPrompt,
                    'instrucoes' => $systemPrompt, // Mantém compatibilidade com campo antigo
                    'modelo' => $modelo,
                    'temp' => $temperatura,
                    'max_tokens' => $maxTokens
                ]
            );
            
            $this->json([
                'success' => true,
                'message' => 'Agente criado com sucesso',
                'agente_id' => $agenteId
            ]);
        } catch (Exception $e) {
            error_log("Erro ao criar agente: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao criar agente']);
        }
    }
    
    /**
     * Exibe formulário de edição
     */
    public function editar($id)
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->redirect('/login');
            return;
        }
        
        // Buscar dados completos do professor (incluindo avatar)
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        if ($professor) {
            // Mesclar dados do professor com dados do usuário
            $user = array_merge($user, [
                'avatar_url' => $professor['avatar_url'] ?? $user['avatar_url'] ?? null,
                'nome' => $professor['nome'] ?? $user['nome']
            ]);
        }
        
        $agente = $this->db->fetch(
            "SELECT * FROM professores_ia_agentes 
             WHERE id = :id AND professor_id = :prof_id",
            ['id' => $id, 'prof_id' => $user['id']]
        );
        
        if (!$agente) {
            $_SESSION['error_message'] = 'Agente não encontrado';
            $this->redirect('/teacher/ai-agents');
            return;
        }
        
        // Decodifica config_json se existir
        $config = [];
        if (!empty($agente['config_json'])) {
            $config = json_decode($agente['config_json'], true) ?? [];
        }
        
        $csrf_token = $this->generateCsrfToken();
        
        $this->viewWithLayout('professor', 'teacher/ai-agent/editar', [
            'title' => 'Editar Agente de IA - EducaTudo',
            'agente' => $agente,
            'config' => $config,
            'csrf_token' => $csrf_token,
            'user' => $user
        ]);
    }
    
    /**
     * Atualiza agente
     */
    public function atualizar($id)
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 403);
            return;
        }
        
        // Lê dados do JSON (quando Content-Type é application/json)
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Se não veio JSON, tenta $_POST
        if (!$data) {
            $data = $_POST;
        }
        
        // Valida CSRF
        if (!$this->verifyCsrfToken($data['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }
        
        // Verifica se o agente pertence ao professor
        $agente = $this->db->fetch(
            "SELECT id FROM professores_ia_agentes 
             WHERE id = :id AND professor_id = :prof_id",
            ['id' => $id, 'prof_id' => $user['id']]
        );
        
        if (!$agente) {
            $this->json(['success' => false, 'error' => 'Agente não encontrado'], 404);
            return;
        }
        
        $nome = trim($data['nome'] ?? '');
        $descricao = trim($data['descricao'] ?? '');
        
        // Nova estrutura: recebe config em JSON
        $config = $data['config'] ?? [];
        
        // Valores fixos (não exibidos para o professor)
        $modelo = 'gpt-4o-mini';
        $temperatura = 0.7;
        $maxTokens = 2000;
        $ativo = isset($data['ativo']) ? 1 : 0;
        
        if (empty($nome)) {
            $this->json(['success' => false, 'error' => 'Nome do agente é obrigatório']);
            return;
        }
        
        // Valida se pelo menos uma categoria foi selecionada
        if (empty($config) || (
            empty($config['linguagem']) && 
            empty($config['explicacao']) && 
            empty($config['metodologia']) && 
            empty($config['postura']) && 
            empty($config['regras'])
        )) {
            $this->json(['success' => false, 'error' => 'Selecione pelo menos uma opção para configurar o agente']);
            return;
        }
        
        try {
            // Gera o prompt automaticamente a partir da config
            $systemPrompt = $this->gerarPromptDoAgente($config);
            
            // Prepara config_json para salvar
            $configJson = json_encode([
                'linguagem' => $config['linguagem'] ?? [],
                'explicacao' => $config['explicacao'] ?? [],
                'metodologia' => $config['metodologia'] ?? [],
                'postura' => $config['postura'] ?? [],
                'regras' => $config['regras'] ?? []
            ]);
            
            $this->db->update(
                "UPDATE professores_ia_agentes 
                 SET nome = :nome, descricao = :descricao, config_json = :config_json, 
                     system_prompt = :system_prompt, instrucoes_sistema = :instrucoes,
                     modelo_ia = :modelo, temperatura = :temp, max_tokens = :max_tokens, ativo = :ativo
                 WHERE id = :id",
                [
                    'id' => $id,
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'config_json' => $configJson,
                    'system_prompt' => $systemPrompt,
                    'instrucoes' => $systemPrompt, // Mantém compatibilidade
                    'modelo' => $modelo,
                    'temp' => $temperatura,
                    'max_tokens' => $maxTokens,
                    'ativo' => $ativo
                ]
            );
            
            $this->json(['success' => true, 'message' => 'Agente atualizado com sucesso']);
        } catch (Exception $e) {
            error_log("Erro ao atualizar agente: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao atualizar agente']);
        }
    }
    
    /**
     * Exibe página do agente com documentos e chat
     */
    public function show($id)
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->redirect('/login');
            return;
        }
        
        // Buscar dados completos do professor (igual ao dashboard)
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        if (!$professor) {
            $_SESSION['error_message'] = 'Professor não encontrado';
            $this->redirect('/teacher/dashboard');
            return;
        }
        
        // Mesclar dados do professor com dados do usuário
        $user = array_merge($user, [
            'avatar_url' => $professor['avatar_url'] ?? $user['avatar_url'] ?? null,
            'nome' => $professor['nome'] ?? $user['nome']
        ]);
        
        $agente = $this->db->fetch(
            "SELECT * FROM professores_ia_agentes 
             WHERE id = :id AND professor_id = :prof_id",
            ['id' => $id, 'prof_id' => $user['id']]
        );
        
        if (!$agente) {
            $_SESSION['error_message'] = 'Agente não encontrado';
            $this->redirect('/teacher/ai-agents');
            return;
        }
        
        // Busca documentos
        $documentos = $this->db->fetchAll(
            "SELECT * FROM professores_ia_documentos 
             WHERE agente_id = :agente_id 
             ORDER BY created_at DESC",
            ['agente_id' => $id]
        );
        
        // Busca conversas recentes
        $conversas = $this->db->fetchAll(
            "SELECT * FROM professores_ia_conversas 
             WHERE agente_id = :agente_id 
             ORDER BY updated_at DESC 
             LIMIT 10",
            ['agente_id' => $id]
        );
        
        // Busca a conversa mais recente e suas mensagens
        $conversaAtual = null;
        $mensagens = [];
        
        if (!empty($conversas)) {
            $conversaAtual = $conversas[0]; // Mais recente
            
            // Busca mensagens da conversa atual
            $mensagens = $this->db->fetchAll(
                "SELECT role, conteudo, created_at 
                 FROM professores_ia_mensagens 
                 WHERE conversa_id = :conversa_id 
                 ORDER BY created_at ASC",
                ['conversa_id' => $conversaAtual['id']]
            );
            
            // Formatar mensagens da IA usando ChatFormatter
            require_once __DIR__ . '/../../Utils/ChatFormatter.php';
            foreach ($mensagens as &$msg) {
                if ($msg['role'] === 'assistant') {
                    $msg['conteudo_formatado'] = ChatFormatter::formatMessageWithClasses($msg['conteudo'], true);
                }
            }
            unset($msg); // Remove referência
        }
        
        $csrf_token = $this->generateCsrfToken();
        
        $this->viewWithLayout('professor', 'teacher/ai-agent/show', [
            'title' => $agente['nome'] . ' - EducaTudo',
            'agente' => $agente,
            'documentos' => $documentos,
            'conversas' => $conversas,
            'conversa_atual' => $conversaAtual,
            'mensagens' => $mensagens,
            'csrf_token' => $csrf_token,
            'user' => $user
        ]);
    }
    
    /**
     * Upload de documento
     */
    public function uploadDocumento($agenteId)
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 403);
            return;
        }
        
        // Valida CSRF
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }
        
        // Verifica se o agente pertence ao professor
        $agente = $this->db->fetch(
            "SELECT id FROM professores_ia_agentes 
             WHERE id = :id AND professor_id = :prof_id",
            ['id' => $agenteId, 'prof_id' => $user['id']]
        );
        
        if (!$agente) {
            $this->json(['success' => false, 'error' => 'Agente não encontrado'], 404);
            return;
        }
        
        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'error' => 'Erro no upload do arquivo']);
            return;
        }
        
        try {
            // Salva arquivo
            $infoArquivo = $this->docProcessor->salvarArquivo(
                $_FILES['arquivo'],
                $user['id'],
                $agenteId
            );
            
            // Registra no banco
            $documentoId = $this->db->insert(
                "INSERT INTO professores_ia_documentos 
                 (agente_id, professor_id, nome_arquivo, nome_original, tipo_mime, tamanho_bytes, caminho_arquivo, status_processamento)
                 VALUES (:agente_id, :prof_id, :nome_arquivo, :nome_original, :tipo_mime, :tamanho, :caminho, 'pendente')",
                [
                    'agente_id' => $agenteId,
                    'prof_id' => $user['id'],
                    'nome_arquivo' => $infoArquivo['nome_arquivo'],
                    'nome_original' => $infoArquivo['nome_original'],
                    'tipo_mime' => $infoArquivo['tipo_mime'],
                    'tamanho' => $infoArquivo['tamanho_bytes'],
                    'caminho' => $infoArquivo['caminho_completo']
                ]
            );
            
            // Processa em background (ou pode ser síncrono para arquivos pequenos)
            $this->processarDocumento($documentoId);
            
            $this->json([
                'success' => true,
                'message' => 'Documento enviado com sucesso. Processamento em andamento...',
                'documento_id' => $documentoId
            ]);
        } catch (Exception $e) {
            error_log("Erro ao fazer upload: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao fazer upload: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Processa documento: extrai texto, divide em chunks e gera embeddings
     */
    private function processarDocumento($documentoId)
    {
        try {
            // Atualiza status
            $this->db->update(
                "UPDATE professores_ia_documentos SET status_processamento = 'processando' WHERE id = :id",
                ['id' => $documentoId]
            );
            
            // Busca documento
            $documento = $this->db->fetch(
                "SELECT * FROM professores_ia_documentos WHERE id = :id",
                ['id' => $documentoId]
            );
            
            if (!$documento) {
                throw new Exception('Documento não encontrado');
            }
            
            // Extrai texto
            $texto = $this->docProcessor->extrairTexto($documento['caminho_arquivo'], $documento['tipo_mime']);
            
            // Salva texto extraído
            $this->db->update(
                "UPDATE professores_ia_documentos 
                 SET texto_extraido = :texto 
                 WHERE id = :id",
                ['id' => $documentoId, 'texto' => $texto]
            );
            
            // Divide em chunks
            $chunks = $this->docProcessor->dividirEmChunks($texto, 1000, 200);
            
            $totalChunks = count($chunks);
            
            // Insere chunks no banco
            foreach ($chunks as $index => $chunk) {
                $chunkId = $this->db->insert(
                    "INSERT INTO professores_ia_documentos_chunks 
                     (documento_id, agente_id, chunk_index, texto, tokens)
                     VALUES (:doc_id, :agente_id, :index, :texto, :tokens)",
                    [
                        'doc_id' => $documentoId,
                        'agente_id' => $documento['agente_id'],
                        'index' => $index,
                        'texto' => $chunk['texto'],
                        'tokens' => (int)(mb_strlen($chunk['texto']) / 4) // Estimativa aproximada
                    ]
                );
                
                // Gera embedding
                try {
                    $embedding = $this->embeddingService->gerarEmbedding($chunk['texto']);
                    $this->embeddingService->salvarEmbedding($chunkId, $embedding);
                } catch (Exception $e) {
                    error_log("Erro ao gerar embedding para chunk $chunkId: " . $e->getMessage());
                    // Continua com os próximos chunks mesmo se um falhar
                }
            }
            
            // Atualiza status e total de chunks
            $this->db->update(
                "UPDATE professores_ia_documentos 
                 SET status_processamento = 'concluido', total_chunks = :total 
                 WHERE id = :id",
                ['id' => $documentoId, 'total' => $totalChunks]
            );
            
        } catch (Exception $e) {
            error_log("Erro ao processar documento $documentoId: " . $e->getMessage());
            
            // Atualiza status para erro
            try {
                $this->db->update(
                    "UPDATE professores_ia_documentos 
                     SET status_processamento = 'erro', erro_processamento = :erro 
                     WHERE id = :id",
                    ['id' => $documentoId, 'erro' => $e->getMessage()]
                );
            } catch (Exception $e2) {
                error_log("Erro ao atualizar status de erro: " . $e2->getMessage());
            }
        }
    }
    
    /**
     * API: Envia mensagem no chat
     */
    public function enviarMensagem($agenteId)
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 403);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Valida CSRF
        if (!$this->verifyCsrfToken($data['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }
        
        $pergunta = trim($data['mensagem'] ?? '');
        $conversaId = $data['conversa_id'] ?? null;
        
        if (empty($pergunta)) {
            $this->json(['success' => false, 'error' => 'Mensagem não pode ser vazia']);
            return;
        }
        
        // Verifica se o agente pertence ao professor
        $agente = $this->db->fetch(
            "SELECT id FROM professores_ia_agentes 
             WHERE id = :id AND professor_id = :prof_id AND ativo = 1",
            ['id' => $agenteId, 'prof_id' => $user['id']]
        );
        
        if (!$agente) {
            $this->json(['success' => false, 'error' => 'Agente não encontrado ou inativo'], 404);
            return;
        }
        
        try {
            // Inicializa serviços se necessário
            if (!$this->ragService) {
                require_once __DIR__ . '/../Services/RAGService.php';
                $this->ragService = new RAGService();
            }
            
            // Cria ou busca conversa
            if (!$conversaId) {
                $conversaId = $this->db->insert(
                    "INSERT INTO professores_ia_conversas (agente_id, professor_id, titulo)
                     VALUES (:agente_id, :prof_id, :titulo)",
                    [
                        'agente_id' => $agenteId,
                        'prof_id' => $user['id'],
                        'titulo' => mb_substr($pergunta, 0, 100)
                    ]
                );
            }
            
            // Salva mensagem do usuário
            $this->db->insert(
                "INSERT INTO professores_ia_mensagens (conversa_id, role, conteudo)
                 VALUES (:conversa_id, 'user', :conteudo)",
                ['conversa_id' => $conversaId, 'conteudo' => $pergunta]
            );
            
            // Busca histórico da conversa
            $historico = $this->db->fetchAll(
                "SELECT role, conteudo FROM professores_ia_mensagens 
                 WHERE conversa_id = :conversa_id 
                 ORDER BY created_at ASC",
                ['conversa_id' => $conversaId]
            );
            
            // Gera resposta usando RAG
            $respostaRAG = $this->ragService->gerarResposta($agenteId, $pergunta, $historico);
            
            // Salva resposta da IA
            $this->db->insert(
                "INSERT INTO professores_ia_mensagens (conversa_id, role, conteudo, chunks_usados, tokens_usados)
                 VALUES (:conversa_id, 'assistant', :conteudo, :chunks, :tokens)",
                [
                    'conversa_id' => $conversaId,
                    'conteudo' => $respostaRAG['resposta'],
                    'chunks' => json_encode($respostaRAG['chunks_usados']),
                    'tokens' => $respostaRAG['tokens_usados']
                ]
            );
            
            // Atualiza timestamp da conversa
            $this->db->update(
                "UPDATE professores_ia_conversas SET updated_at = NOW() WHERE id = :id",
                ['id' => $conversaId]
            );
            
            // Formatar resposta usando ChatFormatter
            require_once __DIR__ . '/../../Utils/ChatFormatter.php';
            $respostaFormatada = ChatFormatter::formatMessageWithClasses($respostaRAG['resposta'], true);
            
            $this->json([
                'success' => true,
                'resposta' => $respostaRAG['resposta'],
                'resposta_formatada' => $respostaFormatada,
                'conversa_id' => $conversaId
            ]);
        } catch (Exception $e) {
            error_log("Erro ao enviar mensagem: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            $errorMessage = DEBUG ? $e->getMessage() : 'Erro ao processar mensagem. Tente novamente.';
            $this->json(['success' => false, 'error' => $errorMessage], 500);
        } catch (Throwable $e) {
            error_log("Erro fatal ao enviar mensagem: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            $errorMessage = DEBUG ? $e->getMessage() : 'Erro ao processar mensagem. Tente novamente.';
            $this->json(['success' => false, 'error' => $errorMessage], 500);
        }
    }
    
    /**
     * API: Busca histórico de uma conversa
     */
    public function historicoConversa($conversaId)
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 403);
            return;
        }
        
        // Verifica se a conversa pertence ao professor
        $conversa = $this->db->fetch(
            "SELECT c.* FROM professores_ia_conversas c
             WHERE c.id = :id AND c.professor_id = :prof_id",
            ['id' => $conversaId, 'prof_id' => $user['id']]
        );
        
        if (!$conversa) {
            $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
            return;
        }
        
        $mensagens = $this->db->fetchAll(
            "SELECT role, conteudo, created_at 
             FROM professores_ia_mensagens 
             WHERE conversa_id = :conversa_id 
             ORDER BY created_at ASC",
            ['conversa_id' => $conversaId]
        );
        
        $this->json([
            'success' => true,
            'mensagens' => $mensagens
        ]);
    }
    
    /**
     * Exclui documento
     */
    public function excluirDocumento($documentoId)
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 403);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Valida CSRF
        if (!$this->verifyCsrfToken($data['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }
        
        try {
            // Busca documento e verifica se pertence ao professor
            $documento = $this->db->fetch(
                "SELECT d.*, a.professor_id 
                 FROM professores_ia_documentos d
                 JOIN professores_ia_agentes a ON d.agente_id = a.id
                 WHERE d.id = :doc_id AND a.professor_id = :prof_id",
                ['doc_id' => $documentoId, 'prof_id' => $user['id']]
            );
            
            if (!$documento) {
                $this->json(['success' => false, 'error' => 'Documento não encontrado'], 404);
                return;
            }
            
            // Exclui arquivo físico se existir
            if (!empty($documento['caminho_arquivo']) && file_exists($documento['caminho_arquivo'])) {
                @unlink($documento['caminho_arquivo']);
            }
            
            // Exclui chunks associados
            $this->db->delete(
                "DELETE FROM professores_ia_documentos_chunks WHERE documento_id = :doc_id",
                ['doc_id' => $documentoId]
            );
            
            // Exclui documento
            $this->db->delete(
                "DELETE FROM professores_ia_documentos WHERE id = :doc_id",
                ['doc_id' => $documentoId]
            );
            
            $this->json(['success' => true, 'message' => 'Documento excluído com sucesso']);
        } catch (Exception $e) {
            error_log("Erro ao excluir documento: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao excluir documento: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Exclui agente
     */
    public function excluir($id)
    {
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 403);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Valida CSRF
        if (!$this->verifyCsrfToken($data['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }
        
        // Verifica se o agente pertence ao professor
        $agente = $this->db->fetch(
            "SELECT id FROM professores_ia_agentes 
             WHERE id = :id AND professor_id = :prof_id",
            ['id' => $id, 'prof_id' => $user['id']]
        );
        
        if (!$agente) {
            $this->json(['success' => false, 'error' => 'Agente não encontrado'], 404);
            return;
        }
        
        try {
            // Busca documentos do agente para excluir arquivos físicos
            $documentos = $this->db->fetchAll(
                "SELECT caminho_arquivo FROM professores_ia_documentos WHERE agente_id = :agente_id",
                ['agente_id' => $id]
            );
            
            // Exclui arquivos físicos
            foreach ($documentos as $doc) {
                if (!empty($doc['caminho_arquivo']) && file_exists($doc['caminho_arquivo'])) {
                    @unlink($doc['caminho_arquivo']);
                }
            }
            
            // Exclui chunks associados (se não houver cascade)
            $this->db->delete(
                "DELETE FROM professores_ia_documentos_chunks WHERE agente_id = :agente_id",
                ['agente_id' => $id]
            );
            
            // Exclui documentos
            $this->db->delete(
                "DELETE FROM professores_ia_documentos WHERE agente_id = :agente_id",
                ['agente_id' => $id]
            );
            
            // Exclui mensagens das conversas
            $conversas = $this->db->fetchAll(
                "SELECT id FROM professores_ia_conversas WHERE agente_id = :agente_id",
                ['agente_id' => $id]
            );
            
            foreach ($conversas as $conv) {
                $this->db->delete(
                    "DELETE FROM professores_ia_mensagens WHERE conversa_id = :conversa_id",
                    ['conversa_id' => $conv['id']]
                );
            }
            
            // Exclui conversas
            $this->db->delete(
                "DELETE FROM professores_ia_conversas WHERE agente_id = :agente_id",
                ['agente_id' => $id]
            );
            
            // Exclui agente
            $this->db->delete(
                "DELETE FROM professores_ia_agentes WHERE id = :id",
                ['id' => $id]
            );
            
            $this->json(['success' => true, 'message' => 'Agente excluído com sucesso']);
        } catch (Exception $e) {
            error_log("Erro ao excluir agente: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao excluir agente']);
        }
    }
}
}

