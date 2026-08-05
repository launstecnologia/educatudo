<?php
/**
 * EducaTudo - Serviço RAG (Retrieval-Augmented Generation)
 * Busca contexto relevante e gera respostas usando OpenAI
 */

/**
 * Serviço RAG (Retrieval-Augmented Generation)
 * Busca contexto relevante e gera respostas usando OpenAI
 */
class RAGService
{
    private $embeddingService;
    private $openAIService;
    private $db;
    
    public function __construct()
    {
        require_once __DIR__ . '/EmbeddingService.php';
        require_once __DIR__ . '/OpenAIService.php';
        
        $this->embeddingService = new EmbeddingService();
        $this->openAIService = new \App\Services\OpenAIService();
        
        require_once __DIR__ . '/../Core/Database.php';
        $this->db = \Database::getInstance();
    }
    
    /**
     * Busca chunks relevantes para uma pergunta usando busca vetorial
     */
    public function buscarChunksRelevantes($agenteId, $pergunta, $limite = 5)
    {
        // Gera embedding da pergunta
        $embeddingPergunta = $this->embeddingService->gerarEmbedding($pergunta);
        
        // Busca todos os chunks do agente com embeddings
        $chunks = $this->db->fetchAll(
            "SELECT id, texto, embedding, metadata, documento_id
             FROM professor_ai_documento_chunks
             WHERE agente_id = :agente_id
             AND embedding IS NOT NULL
             AND JSON_VALID(embedding) = 1",
            ['agente_id' => $agenteId]
        );
        
        if (empty($chunks)) {
            return [];
        }
        
        // Calcula similaridade para cada chunk
        $chunksComSimilaridade = [];
        foreach ($chunks as $chunk) {
            $embeddingChunk = json_decode($chunk['embedding'], true);
            
            if (!$embeddingChunk || !is_array($embeddingChunk)) {
                continue;
            }
            
            $similaridade = $this->embeddingService->similaridadeCosseno(
                $embeddingPergunta,
                $embeddingChunk
            );
            
            $chunksComSimilaridade[] = [
                'chunk' => $chunk,
                'similaridade' => $similaridade
            ];
        }
        
        // Ordena por similaridade (maior primeiro)
        usort($chunksComSimilaridade, function($a, $b) {
            return $b['similaridade'] <=> $a['similaridade'];
        });
        
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
            error_log("DEBUG RAG buscarChunksRelevantes: Total de chunks com similaridade calculada: " . count($chunksComSimilaridade));
        
        }
        if (!empty($chunksComSimilaridade)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("DEBUG RAG: Maior similaridade: " . $chunksComSimilaridade[0]['similaridade']);
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("DEBUG RAG: Menor similaridade: " . end($chunksComSimilaridade)['similaridade']);
            }
        }
        
        // Retorna os top N chunks (mesmo com baixa similaridade, para ter contexto)
        $chunksRelevantes = array_slice($chunksComSimilaridade, 0, $limite);
        
        return array_map(function($item) {
            return [
                'id' => $item['chunk']['id'],
                'texto' => $item['chunk']['texto'],
                'similaridade' => $item['similaridade'],
                'metadata' => json_decode($item['chunk']['metadata'] ?? '{}', true),
                'documento_id' => $item['chunk']['documento_id']
            ];
        }, $chunksRelevantes);
    }
    
    /**
     * Gera resposta usando RAG: busca contexto + LLM
     * 
     * NOTA: Para fins de teste, o uso de documentos foi desabilitado.
     * O LLM responde livremente sem usar o contexto dos documentos anexados.
     * A interface visual de upload de documentos permanece ativa.
     */
    public function gerarResposta($agenteId, $pergunta, $historico = [])
    {
        // DESABILITADO PARA TESTE: Não busca chunks dos documentos
        // $chunksRelevantes = $this->buscarChunksRelevantes($agenteId, $pergunta, 10);
        
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
            error_log("DEBUG RAG: Agente ID {$agenteId} - Pergunta: '{$pergunta}'");
        
        }
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            error_log("DEBUG RAG: Modo teste ativo - documentos desvinculados do chat");
        }
        
        // DESABILITADO PARA TESTE: Não monta contexto dos documentos
        $contexto = '';
        $chunkIds = [];
        
        // Código comentado para referência futura:
        /*
        foreach ($chunksRelevantes as $chunk) {
            $similaridade = $chunk['similaridade'] ?? 0;
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("DEBUG RAG: Chunk ID {$chunk['id']} - Similaridade: {$similaridade} - Texto: " . substr($chunk['texto'], 0, 100));
            }
            
            if ($similaridade >= 0.05 || count($chunksRelevantes) <= 3) {
                $contexto .= "\n\n---\n\n" . $chunk['texto'];
                $chunkIds[] = $chunk['id'];
            } else {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("DEBUG RAG: Chunk ID {$chunk['id']} descartado por baixa similaridade: {$similaridade}");
                }
            }
        }
        
        if (empty($contexto)) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("DEBUG RAG: Nenhum chunk relevante encontrado. Buscando todos os chunks como fallback...");
            }
            $todosChunks = $this->db->fetchAll(
                "SELECT id, texto FROM professor_ai_documento_chunks
                 WHERE agente_id = :agente_id
                 AND texto IS NOT NULL
                 AND texto != ''
                 ORDER BY id ASC
                 LIMIT 5",
                ['agente_id' => $agenteId]
            );
            
            foreach ($todosChunks as $chunk) {
                $contexto .= "\n\n---\n\n" . $chunk['texto'];
                $chunkIds[] = $chunk['id'];
            }
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                error_log("DEBUG RAG: Fallback - Chunks encontrados: " . count($todosChunks));
            
            }
        }
        */
        
        // Busca informações do agente
        $agente = $this->db->fetch(
            "SELECT nome, system_prompt, instrucoes_sistema, modelo_ia, temperatura, max_tokens
             FROM professor_ai_agents
             WHERE id = :agente_id AND ativo = 1",
            ['agente_id' => $agenteId]
        );
        
        if (!$agente) {
            throw new Exception('Agente não encontrado ou inativo');
        }
        
        // Monta prompt do sistema
        $systemPrompt = $this->montarSystemPrompt($agente, $contexto);
        
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
            error_log("DEBUG RAG: System prompt montado (tamanho: " . strlen($systemPrompt) . " caracteres)");
        
        }
        
        // Monta histórico de mensagens (sem system prompt, pois será passado separadamente)
        $mensagens = [];
        
        // Adiciona histórico (últimas 10 mensagens para não exceder tokens)
        $historicoLimitado = array_slice($historico, -10);
        foreach ($historicoLimitado as $msg) {
            // Suporta tanto 'conteudo' (do banco) quanto 'content' (formato padrão)
            $conteudo = $msg['conteudo'] ?? $msg['content'] ?? '';
            if (!empty($conteudo)) {
                $mensagens[] = [
                    'role' => $msg['role'] ?? 'user',
                    'content' => $conteudo
                ];
            }
        }
        
        // Adiciona pergunta atual
        $mensagens[] = ['role' => 'user', 'content' => $pergunta];
        
        // Gera resposta usando OpenAI
        try {
            $resposta = $this->openAIService->chatCompletion(
                $mensagens,
                $systemPrompt,
                $agente['modelo_ia'] ?? 'gpt-4o-mini',
                (float)($agente['temperatura'] ?? 0.7),
                (int)($agente['max_tokens'] ?? 2000)
            );
            
            return [
                'resposta' => $resposta['resposta'] ?? '',
                'chunks_usados' => $chunkIds,
                'tokens_usados' => $resposta['tokens_usados'] ?? 0
            ];
        } catch (Exception $e) {
            error_log("Erro ao gerar resposta RAG: " . $e->getMessage());
            throw new Exception('Erro ao gerar resposta: ' . $e->getMessage());
        }
    }
    
    /**
     * Monta prompt do sistema com contexto
     * 
     * NOTA: Para fins de teste, o contexto dos documentos não é incluído.
     * O LLM responde livremente baseado apenas nas instruções do agente.
     */
    private function montarSystemPrompt($agente, $contexto)
    {
        // Usa system_prompt se existir (novo formato), senão usa instrucoes_sistema (compatibilidade)
        $instrucoes = !empty($agente['system_prompt']) ? $agente['system_prompt'] : ($agente['instrucoes_sistema'] ?? '');
        
        $prompt = "Você é um assistente de IA especializado chamado '{$agente['nome']}'.\n\n";
        
        if (!empty($instrucoes)) {
            $prompt .= "Instruções específicas:\n$instrucoes\n\n";
        }
        
        // DESABILITADO PARA TESTE: Não inclui contexto dos documentos
        // O LLM responde livremente sem usar documentos anexados
        /*
        if (!empty($contexto)) {
            $prompt .= "Você tem acesso ao seguinte contexto extraído de documentos fornecidos pelo usuário:\n";
            $prompt .= $contexto;
            $prompt .= "\n\n";
            $prompt .= "IMPORTANTE:\n";
            $prompt .= "- Use as informações do contexto fornecido acima para responder às perguntas do usuário.\n";
            $prompt .= "- Analise cuidadosamente o contexto antes de responder.\n";
            $prompt .= "- Se a pergunta estiver relacionada ao contexto, responda com base nas informações fornecidas.\n";
            $prompt .= "- Se a pergunta não puder ser respondida com base no contexto, diga claramente que não tem essa informação específica.\n";
            $prompt .= "- Cite as fontes quando relevante.\n";
            $prompt .= "- Seja preciso e objetivo.\n";
        } else {
            $prompt .= "ATENÇÃO: Nenhum documento foi fornecido ainda ou os documentos não contêm informações relevantes.\n";
            $prompt .= "Se o usuário fizer perguntas sobre documentos, informe que os documentos precisam ser processados primeiro.\n";
        }
        */
        
        $prompt .= "- Responda em português brasileiro, a menos que o usuário solicite outro idioma.\n";
        $prompt .= "- Use seu conhecimento geral para responder às perguntas do usuário.\n";
        
        return $prompt;
    }
}

