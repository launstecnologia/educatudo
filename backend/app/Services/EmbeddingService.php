<?php
/**
 * EducaTudo - Serviço de Embeddings
 * Gera embeddings usando OpenAI
 */

/**
 * Serviço para gerar embeddings usando OpenAI
 */
class EmbeddingService
{
    private $openAIService;
    private $model = 'text-embedding-3-small'; // 1536 dimensões, mais barato
    private $db;
    
    public function __construct()
    {
        require_once __DIR__ . '/OpenAIService.php';
        $this->openAIService = new \App\Services\OpenAIService();
        
        // Inicializa conexão com banco
        require_once __DIR__ . '/../Core/Database.php';
        $this->db = \Database::getInstance();
    }
    
    /**
     * Gera embedding para um texto usando OpenAI
     */
    public function gerarEmbedding($texto)
    {
        if (empty(trim($texto))) {
            throw new Exception('Texto não pode ser vazio');
        }
        
        // Limita tamanho do texto (OpenAI tem limite de tokens)
        $texto = mb_substr($texto, 0, 8000); // ~2000 tokens aproximadamente
        
        try {
            $response = $this->fazerRequisicaoEmbedding($texto);
            
            if (!isset($response['data'][0]['embedding'])) {
                throw new Exception('Resposta da API inválida');
            }
            
            return $response['data'][0]['embedding'];
        } catch (Exception $e) {
            error_log("Erro ao gerar embedding: " . $e->getMessage());
            throw new Exception('Erro ao gerar embedding: ' . $e->getMessage());
        }
    }
    
    /**
     * Gera embeddings em lote (mais eficiente)
     */
    public function gerarEmbeddingsBatch($textos)
    {
        if (empty($textos)) {
            return [];
        }
        
        // Limita batch size (OpenAI permite até 2048 itens, mas vamos usar 100 para segurança)
        $batches = array_chunk($textos, 100);
        $embeddings = [];
        
        foreach ($batches as $batch) {
            try {
                $response = $this->fazerRequisicaoEmbedding($batch);
                
                if (isset($response['data']) && is_array($response['data'])) {
                    foreach ($response['data'] as $item) {
                        if (isset($item['embedding'])) {
                            $embeddings[] = $item['embedding'];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Erro ao gerar embeddings em lote: " . $e->getMessage());
                // Continua com os próximos batches mesmo se um falhar
            }
        }
        
        return $embeddings;
    }
    
    /**
     * Faz requisição para API de embeddings da OpenAI
     */
    private function fazerRequisicaoEmbedding($textoOuArray)
    {
        $apiKey = $this->openAIService->getOpenAIApiKey();
        
        if (!$apiKey) {
            throw new Exception('Chave API OpenAI não configurada');
        }
        
        $url = 'https://api.openai.com/v1/embeddings';
        
        // Prepara dados
        $data = [
            'model' => $this->model
        ];
        
        // Se for array, é batch
        if (is_array($textoOuArray)) {
            $data['input'] = $textoOuArray;
        } else {
            $data['input'] = $textoOuArray;
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Erro cURL: ' . $error);
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? "HTTP $httpCode";
            throw new Exception('Erro da API: ' . $errorMsg);
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erro ao decodificar resposta JSON');
        }
        
        return $result;
    }
    
    /**
     * Salva embedding no banco de dados
     */
    public function salvarEmbedding($chunkId, $embedding)
    {
        try {
            $this->db->update(
                "UPDATE professor_ai_documento_chunks 
                 SET embedding = :embedding 
                 WHERE id = :chunk_id",
                [
                    'chunk_id' => $chunkId,
                    'embedding' => json_encode($embedding)
                ]
            );
        } catch (Exception $e) {
            error_log("Erro ao salvar embedding: " . $e->getMessage());
            throw new Exception('Erro ao salvar embedding no banco');
        }
    }
    
    /**
     * Calcula similaridade cosseno entre dois vetores
     */
    public function similaridadeCosseno($vetor1, $vetor2)
    {
        if (count($vetor1) !== count($vetor2)) {
            throw new Exception('Vetores devem ter a mesma dimensão');
        }
        
        $produtoEscalar = 0;
        $norma1 = 0;
        $norma2 = 0;
        
        for ($i = 0; $i < count($vetor1); $i++) {
            $produtoEscalar += $vetor1[$i] * $vetor2[$i];
            $norma1 += $vetor1[$i] * $vetor1[$i];
            $norma2 += $vetor2[$i] * $vetor2[$i];
        }
        
        $norma1 = sqrt($norma1);
        $norma2 = sqrt($norma2);
        
        if ($norma1 == 0 || $norma2 == 0) {
            return 0;
        }
        
        return $produtoEscalar / ($norma1 * $norma2);
    }
}

