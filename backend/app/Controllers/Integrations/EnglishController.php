<?php

if (!class_exists('EnglishController')) {
class EnglishController extends BaseController
{
    private $authManager;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        
        // Verifica se é aluno
        $user = $this->authManager->getUser();
        if ($user && $user['tipo'] !== 'aluno') {
            $this->redirect('/dashboard');
        }
    }
    
    /**
     * Página principal do módulo de Inglês
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        
        $aluno = $this->db->fetch(
            "SELECT a.* FROM alunos a WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );

        if (!$aluno) {
            $this->redirect('/logout');
        }

        // Buscar histórico de conversas (se a tabela existir)
        $historico = [];
        try {
            if ($this->db->tableExists('ingles_conversas')) {
                $historico = $this->db->fetchAll(
                    "SELECT * FROM ingles_conversas 
                     WHERE aluno_id = :aluno_id 
                     ORDER BY created_at DESC 
                     LIMIT 10",
                    ['aluno_id' => $aluno['id']]
                );
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar histórico de inglês: " . $e->getMessage());
            $historico = [];
        }

        // Avatar do aluno (foto de perfil para o chat)
        $avatarUrl = null;
        try {
            if ($this->db->tableExists('avatares_alunos')) {
                $avatarData = $this->db->fetch(
                    "SELECT avatar_url FROM avatares_alunos WHERE aluno_id = :aluno_id",
                    ['aluno_id' => $aluno['id']]
                );
                $avatarUrl = $avatarData['avatar_url'] ?? null;
            }
        } catch (Exception $e) {
            $avatarUrl = null;
        }

        // Gerar token CSRF
        $this->generateCsrfToken();

        $this->viewWithLayout('student', 'student/ingles/index', [
            'title' => 'Inglês - Speaking - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'historico' => $historico,
            'current_page' => 'ingles',
            'avatar_url' => $avatarUrl
        ]);
    }
    
    /**
     * Transcreve áudio do aluno para texto
     */
    public function transcreverAudio()
    {
        while (ob_get_level()) ob_end_clean();
        ob_start();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar CSRF token
        if (!isset($_POST['_token']) || !$this->verifyCsrfToken($_POST['_token'])) {
            $this->json(['error' => 'Token inválido'], 400);
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

            // Verificar se foi enviado um áudio
            if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Nenhum áudio foi enviado');
            }

            $audio = $_FILES['audio'];

            // Validar tipo de arquivo (alguns navegadores enviam type vazio ou application/octet-stream para WebM)
            $allowedTypes = ['audio/webm', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/mpeg', 'application/octet-stream', ''];
            $ext = strtolower(pathinfo($audio['name'], PATHINFO_EXTENSION));
            $allowedExt = ['webm', 'mp3', 'wav', 'ogg', 'mpga'];
            $typeOk = in_array($audio['type'], $allowedTypes) || ($audio['type'] === '' && in_array($ext, $allowedExt));
            if (!$typeOk) {
                throw new Exception('Tipo de arquivo não permitido. Use WebM, MP3, WAV ou OGG.');
            }
            $mimeType = $audio['type'] ?: (['webm' => 'audio/webm', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'mpga' => 'audio/mpeg'][$ext] ?? 'audio/webm');

            // Validar tamanho (5MB)
            if ($audio['size'] > 5 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande. Máximo 5MB.');
            }

            // Salvar áudio temporariamente
            $uploadDir = 'public/uploads/ingles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = 'audio_' . $aluno['id'] . '_' . time() . '_' . uniqid() . '.' . pathinfo($audio['name'], PATHINFO_EXTENSION);
            $filePath = $uploadDir . $fileName;

            if (!move_uploaded_file($audio['tmp_name'], $filePath)) {
                throw new Exception('Erro ao salvar áudio');
            }

            // Ler áudio como base64
            $audioData = base64_encode(file_get_contents($filePath));

            // Usar OpenAI para transcrever áudio
            if (!class_exists('App\Services\OpenAIService')) {
                require_once __DIR__ . '/../../Services/OpenAIService.php';
            }
            $openaiService = new \App\Services\OpenAIService();
            
            // Transcrever áudio usando Whisper API (idioma inglês no módulo de inglês)
            try {
                $textoTranscrito = $openaiService->transcreverAudio($audioData, $mimeType, 'en');
            } catch (Exception $e) {
                error_log("Erro na transcrição: " . $e->getMessage());
                throw new Exception('Erro ao transcrever áudio: ' . $e->getMessage());
            }

            // Remover arquivo temporário
            @unlink($filePath);

            $this->json(['success' => true, 'texto' => $textoTranscrito]);

        } catch (Exception $e) {
            error_log("Erro na transcrição de áudio: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Processa conversa com a IA (professora de inglês)
     */
    public function conversar()
    {
        while (ob_get_level()) ob_end_clean();
        ob_start();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar CSRF token
        if (!isset($_POST['_token']) || !$this->verifyCsrfToken($_POST['_token'])) {
            $this->json(['error' => 'Token inválido'], 400);
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

            $textoAluno = trim($_POST['texto'] ?? '');
            $conversaId = $_POST['conversa_id'] ?? null;

            if ($textoAluno === '') {
                throw new Exception('Por favor, fale em inglês ou repita o que disse.');
            }

            // Buscar histórico da conversa (últimas mensagens para contexto)
            $historicoParaLLM = [];
            if ($conversaId && $this->db->tableExists('ingles_mensagens')) {
                try {
                    $mensagens = $this->db->fetchAll(
                        "SELECT * FROM ingles_mensagens 
                         WHERE conversa_id = :conversa_id 
                         ORDER BY created_at ASC 
                         LIMIT 20",
                        ['conversa_id' => $conversaId]
                    );
                    foreach ($mensagens as $msg) {
                        $conteudo = $msg['conteudo'];
                        if ($msg['role'] === 'assistant') {
                            $decoded = json_decode($conteudo, true);
                            if (is_array($decoded) && isset($decoded['natural_response'])) {
                                $conteudo = $decoded['natural_response'];
                            }
                        }
                        $historicoParaLLM[] = ['role' => $msg['role'], 'content' => $conteudo];
                    }
                } catch (Exception $e) {
                    error_log("Erro ao buscar mensagens: " . $e->getMessage());
                }
            } else {
                if ($this->db->tableExists('ingles_conversas')) {
                    try {
                        $conversaId = $this->db->insert(
                            "INSERT INTO ingles_conversas (aluno_id, created_at) 
                             VALUES (:aluno_id, NOW())",
                            ['aluno_id' => $aluno['id']]
                        );
                    } catch (Exception $e) {
                        error_log("Erro ao criar conversa: " . $e->getMessage());
                        $conversaId = null;
                    }
                }
            }

            // Salvar mensagem do aluno
            if ($conversaId && $this->db->tableExists('ingles_mensagens')) {
                try {
                    $this->db->insert(
                        "INSERT INTO ingles_mensagens (conversa_id, role, conteudo, created_at) 
                         VALUES (:conversa_id, 'user', :conteudo, NOW())",
                        ['conversa_id' => $conversaId, 'conteudo' => $textoAluno]
                    );
                } catch (Exception $e) {
                    error_log("Erro ao salvar mensagem: " . $e->getMessage());
                }
            }

            // Módulo de treino: LLM retorna JSON (original_sentence, corrected_sentence, tip, natural_response)
            if (!class_exists('App\Services\OpenAIService')) {
                require_once __DIR__ . '/../../Services/OpenAIService.php';
            }
            $openaiService = new \App\Services\OpenAIService();
            $respostaEstruturada = $openaiService->conversarInglesTreino($textoAluno, $historicoParaLLM);

            // TTS: usar tts_script (didático) quando existir, senão natural_response
            $textoParaAudio = !empty($respostaEstruturada['tts_script'])
                ? $respostaEstruturada['tts_script']
                : $respostaEstruturada['natural_response'];
            $audioBase64 = null;
            if (!empty($textoParaAudio)) {
                try {
                    $audioBase64 = $openaiService->gerarAudioElevenLabs($textoParaAudio);
                } catch (Exception $e) {
                    error_log("Erro ao gerar áudio ElevenLabs: " . $e->getMessage());
                    try {
                        $audioBase64 = $openaiService->gerarAudioOpenAI($textoParaAudio, 'nova');
                    } catch (Exception $e2) {
                        error_log("Erro ao gerar áudio OpenAI TTS: " . $e2->getMessage());
                    }
                }
            }

            // Salvar resposta da IA como JSON (inclui tts_script para histórico)
            $conteudoAssistant = json_encode([
                'original_sentence' => $respostaEstruturada['original_sentence'],
                'corrected_sentence' => $respostaEstruturada['corrected_sentence'],
                'tip' => $respostaEstruturada['tip'],
                'natural_response' => $respostaEstruturada['natural_response'],
                'tts_script' => $respostaEstruturada['tts_script'] ?? ''
            ]);
            if ($conversaId && $this->db->tableExists('ingles_mensagens')) {
                try {
                    $this->db->insert(
                        "INSERT INTO ingles_mensagens (conversa_id, role, conteudo, created_at) 
                         VALUES (:conversa_id, 'assistant', :conteudo, NOW())",
                        ['conversa_id' => $conversaId, 'conteudo' => $conteudoAssistant]
                    );
                } catch (Exception $e) {
                    error_log("Erro ao salvar resposta IA: " . $e->getMessage());
                }
            }

            if ($conversaId && $this->db->tableExists('ingles_conversas')) {
                try {
                    $this->db->update(
                        "UPDATE ingles_conversas SET updated_at = NOW() WHERE id = :id",
                        ['id' => $conversaId]
                    );
                } catch (Exception $e) {
                    error_log("Erro ao atualizar conversa: " . $e->getMessage());
                }
            }

            $this->json([
                'success' => true,
                'resposta' => $respostaEstruturada,
                'audio' => $audioBase64,
                'conversa_id' => $conversaId
            ]);

        } catch (Exception $e) {
            error_log("Erro na conversa de inglês: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Traduz texto (inglês) para português - para o aluno entender a mensagem da IA
     */
    public function traduzir()
    {
        while (ob_get_level()) ob_end_clean();
        ob_start();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_POST['_token']) || !$this->verifyCsrfToken($_POST['_token'])) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        try {
            $user = $this->authManager->getUser();
            $aluno = $this->db->fetch("SELECT id FROM alunos WHERE id = :user_id", ['user_id' => $user['id']]);
            if (!$aluno) {
                $this->json(['error' => 'Aluno não encontrado'], 403);
                return;
            }
            $texto = trim($_POST['texto'] ?? '');
            if ($texto === '') {
                $this->json(['error' => 'Texto vazio'], 400);
                return;
            }
            if (!class_exists('App\Services\OpenAIService')) {
                require_once __DIR__ . '/../../Services/OpenAIService.php';
            }
            $openaiService = new \App\Services\OpenAIService();
            $traducao = $openaiService->traduzirParaPortugues($texto);
            $this->json(['success' => true, 'traducao' => $traducao]);
        } catch (Exception $e) {
            error_log("Erro ao traduzir: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Retorna mensagens de uma conversa (para carregar no chat)
     */
    public function conversaMensagens($id)
    {
        $user = $this->authManager->getUser();
        $aluno = $this->db->fetch(
            "SELECT id FROM alunos WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        if (!$aluno) {
            $this->json(['error' => 'Aluno não encontrado'], 403);
            return;
        }
        $conversaId = filter_var($id, FILTER_VALIDATE_INT);
        if (!$conversaId) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        $conversa = $this->db->fetch(
            "SELECT id FROM ingles_conversas WHERE id = :id AND aluno_id = :aluno_id",
            ['id' => $conversaId, 'aluno_id' => $aluno['id']]
        );
        if (!$conversa) {
            $this->json(['error' => 'Conversa não encontrada'], 404);
            return;
        }
        $mensagens = $this->db->fetchAll(
            "SELECT role, conteudo, created_at FROM ingles_mensagens 
             WHERE conversa_id = :conversa_id ORDER BY created_at ASC",
            ['conversa_id' => $conversaId]
        );
        $this->json(['success' => true, 'mensagens' => $mensagens]);
    }
    
    /**
     * Busca histórico de conversas
     */
    public function historico()
    {
        $user = $this->authManager->getUser();
        
        $aluno = $this->db->fetch(
            "SELECT a.* FROM alunos a WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );

        if (!$aluno) {
            $this->json(['error' => 'Aluno não encontrado'], 400);
        }

        $conversas = [];
        try {
            if ($this->db->tableExists('ingles_conversas')) {
                $conversas = $this->db->fetchAll(
                    "SELECT c.*, 
                            (SELECT COUNT(*) FROM ingles_mensagens WHERE conversa_id = c.id) as total_mensagens
                     FROM ingles_conversas c
                     WHERE c.aluno_id = :aluno_id 
                     ORDER BY c.created_at DESC",
                    ['aluno_id' => $aluno['id']]
                );
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar conversas: " . $e->getMessage());
        }

        $this->json(['success' => true, 'conversas' => $conversas]);
    }
}
}
