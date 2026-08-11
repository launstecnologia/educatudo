<?php
/**
 * EducaTudo - Controller para Gerador de Slides com Gamma API
 * Gerencia a geração de apresentações usando a Gamma API
 */

if (!class_exists('SlidesController')) {
class SlidesController extends BaseController
{
    private $authManager;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
    }
    
    /**
     * API para gerar slides usando Gamma API
     */
    public function gerar()
    {
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== GAMMA SLIDES - INÍCIO ===");
            }
        }
        error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
        error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        
        // Verifica se é professor
        $user = $this->authManager->getUser();
        error_log("User verificado: " . ($user ? "SIM (ID: " . ($user['id'] ?? 'N/A') . ", Tipo: " . ($user['tipo'] ?? 'N/A') . ")" : "NÃO"));
        
        if (!$user || $user['tipo'] !== 'professor') {
            error_log("ERRO: Usuário não autorizado");
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        $rawInput = file_get_contents("php://input");
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("Raw input recebido: " . substr($rawInput, 0, 200) . (strlen($rawInput) > 200 ? '...' : ''));
            }
        }
        
        $input = json_decode($rawInput, true);
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            error_log("JSON decode: " . (json_last_error() === JSON_ERROR_NONE ? "SUCESSO" : "ERRO: " . json_last_error_msg()));
        }
        
        if (!$input) {
            error_log("ERRO: Dados inválidos ou JSON inválido");
            $this->json(['error' => 'Dados inválidos'], 400);
            return;
        }
        
        $conteudo = $input["conteudo"] ?? '';
        $slides = intval($input["slides"] ?? 8);
        $tema = $input["tema"] ?? '';
        $salvarSlide = isset($input["salvarSlide"]) && $input["salvarSlide"] === true;
        
        // Valores padrão para campos removidos da interface
        // Nível de detalhamento: brief, medium, detailed, extensive (valores aceitos pela API)
        $nivelDetalhamento = $input["nivelDetalhamento"] ?? 'medium';
        $estiloImagem = $input["estiloImagem"] ?? '';
        $idioma = 'pt-br';
        $divisaoCards = 'auto';
        $modoTexto = 'generate';
        $themeId = null;
        
        // Validações
        if (empty($conteudo)) {
            $this->json(['error' => 'Conteúdo é obrigatório'], 400);
            return;
        }
        
        if ($slides < 3 || $slides > 30) {
            $this->json(['error' => 'Número de slides deve estar entre 3 e 30'], 400);
            return;
        }

        require_once __DIR__ . '/../../Services/CreditosService.php';
        $creditosService = new \App\Services\CreditosService();
        $moduloCreditoSlides = 'gerar_slides';

        if (!$creditosService->podeConsumir('professor', (int) $user['id'], $moduloCreditoSlides)) {
            $custoSlides = $creditosService->getCustoModulo($moduloCreditoSlides);
            $this->json([
                'error' => 'Créditos insuficientes para gerar slides.',
                'details' => 'Esta ação consome ' . number_format($custoSlides, 1, ',', '.') . ' crédito(s).',
            ], 402);
            return;
        }
        
        // Obter API Key do Gamma (pode ser configurada no .env ou banco de dados)
        error_log("Buscando API Key do Gamma...");
        $apiKey = $this->getGammaApiKey();
        error_log("API Key encontrada: " . (empty($apiKey) ? "NÃO" : "SIM (tamanho: " . strlen($apiKey) . " caracteres)"));
        
        if (empty($apiKey)) {
            error_log("ERRO: API Key do Gamma não configurada");
            $this->json([
                'error' => 'API Key do Gamma não configurada. Entre em contato com o administrador.',
                'help' => 'Configure a chave em: Admin > Dev Settings > Chaves de API > Gamma API Key'
            ], 500);
            return;
        }
        
        // Validação básica da chave (chaves Gamma geralmente têm um formato específico)
        // Não vamos ser muito restritivos, mas podemos avisar se parecer muito curta
        if (strlen($apiKey) < 20) {
            error_log("AVISO: Chave Gamma parece muito curta (" . strlen($apiKey) . " caracteres). Pode estar incorreta.");
        }
        
        $url = "https://public-api.gamma.app/v1.0/generations";
        
        // Validar valores dos parâmetros
        // Valores aceitos pela API do Gamma: brief, medium, detailed, extensive
        $niveisValidos = ['brief', 'medium', 'detailed', 'extensive'];
        if (!in_array($nivelDetalhamento, $niveisValidos)) {
            $nivelDetalhamento = 'medium';
        }
        
        $idiomasValidos = ['pt-br', 'pt-pt', 'en', 'es'];
        if (!in_array($idioma, $idiomasValidos)) {
            $idioma = 'pt-br';
        }
        
        $divisoesValidas = ['auto', 'manual'];
        if (!in_array($divisaoCards, $divisoesValidas)) {
            $divisaoCards = 'auto';
        }
        
        $modosValidos = ['generate', 'refine'];
        if (!in_array($modoTexto, $modosValidos)) {
            $modoTexto = 'generate';
        }
        
        // Validar estilo de imagem
        $estilosValidos = ['illustration', 'photo', 'abstract', '3d', 'line-art', 'custom'];
        if (!empty($estiloImagem) && !in_array($estiloImagem, $estilosValidos)) {
            $estiloImagem = '';
        }
        
        // Montar payload com todos os parâmetros controláveis
        $payload = [
            "inputText" => $conteudo,
            "format" => "presentation",
            "textMode" => $modoTexto,
            "numCards" => $slides,
            "cardSplit" => $divisaoCards,
            "textOptions" => [
                "language" => $idioma,
                "amount" => $nivelDetalhamento
            ]
        ];
        
        // Adicionar themeId se fornecido e não vazio
        if (!empty($themeId) && is_string($themeId)) {
            $payload["themeId"] = trim($themeId);
        }
        
        // Nota: imageStyle não é suportado diretamente pela API do Gamma
        // O estilo de imagem é controlado pelo tema (themeId) ou pode ser incluído nas palavras-chave
        
        // Adicionar estilo de imagem ao inputText se fornecido
        // A API do Gamma não suporta imageStyle como parâmetro separado
        if (!empty($estiloImagem)) {
            $estiloLabels = [
                'illustration' => 'ilustração',
                'photo' => 'foto realista',
                'abstract' => 'arte abstrata',
                '3d' => 'renderização 3D',
                'line-art' => 'arte linear',
                'custom' => 'estilo personalizado'
            ];
            $estiloTexto = $estiloLabels[$estiloImagem] ?? $estiloImagem;
            // Adiciona o estilo ao final do inputText para melhorar o contexto
            $payload["inputText"] = $conteudo . ' Estilo visual: ' . $estiloTexto;
        }
        
        // Se tema foi selecionado (mas não é themeId customizado), pode ser usado para referência
        // Nota: O campo themeId requer IDs específicos válidos da conta Gamma
        // Os valores de tema (clean, modern, etc.) são apenas para referência visual
        
        // Log do payload para debug (sem expor a chave)
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("Gamma API Payload: " . json_encode(array_merge($payload, ['apiKey' => substr($apiKey, 0, 10) . '...'])));
            }
        }
        error_log("URL da API: " . $url);
        
        // Fazer requisição para Gamma API
        error_log("Iniciando requisição cURL para Gamma API...");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-API-KEY: $apiKey",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        
        error_log("cURL executado. HTTP Code: " . $httpCode);
        error_log("cURL Error: " . ($curlError ?: 'Nenhum'));
        error_log("cURL Errno: " . ($curlErrno ?: 'Nenhum'));
        error_log("Response length: " . strlen($response ?? ''));
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("Response preview: " . substr($response ?? '', 0, 500));
            }
        }
        
        if ($curlError) {
            error_log("ERRO cURL: " . $curlError . " (Errno: " . $curlErrno . ")");
            $this->json(['error' => 'Erro ao conectar com Gamma API: ' . $curlError], 500);
            return;
        }
        
        // Gamma API retorna 201 (Created) quando inicia a geração com sucesso
        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorData = json_decode($response, true);
            
            // Log detalhado do erro para debug
            error_log("Gamma API Error - HTTP Code: $httpCode");
            error_log("Gamma API Response: " . substr($response, 0, 1000));
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Gamma API Payload enviado: " . json_encode($payload));
                }
            }
            
            // Mensagens de erro mais específicas
            $errorMsg = 'Erro desconhecido da Gamma API';
            $errorDetails = null;
            
            if ($httpCode === 401 || $httpCode === 403) {
                $errorMsg = 'Chave de API inválida ou não autorizada. Verifique se você está usando a chave correta da Gamma API (não use chaves de outros serviços como Gemini, OpenAI, etc).';
                $errorDetails = 'Verifique se a chave está correta em Admin > Dev Settings > Chaves de API';
            } elseif ($httpCode === 400) {
                // Tentar extrair mensagem de erro mais específica
                if (isset($errorData['message'])) {
                    $errorMsg = $errorData['message'];
                } elseif (isset($errorData['error'])) {
                    $errorMsg = $errorData['error'];
                } elseif (isset($errorData['errors']) && is_array($errorData['errors'])) {
                    $errorMsg = implode(', ', array_column($errorData['errors'], 'message'));
                } else {
                    $errorMsg = 'Requisição inválida. Verifique os parâmetros enviados.';
                }
                $errorDetails = 'Verifique se todos os campos estão preenchidos corretamente';
            } elseif ($httpCode === 429) {
                $errorMsg = 'Limite de requisições excedido. Tente novamente mais tarde.';
            } elseif ($httpCode === 500 || $httpCode === 502 || $httpCode === 503) {
                $errorMsg = 'Erro no servidor da Gamma API. Tente novamente em alguns instantes.';
            } else {
                // Tentar extrair qualquer mensagem de erro disponível
                if (isset($errorData['message'])) {
                    $errorMsg = $errorData['message'];
                } elseif (isset($errorData['error'])) {
                    $errorMsg = $errorData['error'];
                } elseif (!empty($response)) {
                    $errorMsg = 'Erro da Gamma API: ' . substr($response, 0, 200);
                }
            }
            
            $responseData = [
                'error' => $errorMsg,
                'http_code' => $httpCode,
                'details' => $errorDetails
            ];
            
            // Não adicionar informações de debug
            
            $this->json($responseData, $httpCode);
            return;
        }
        
        error_log("Resposta da Gamma API recebida. Decodificando JSON...");
        $data = json_decode($response, true);
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("JSON decode status: " . (json_last_error() === JSON_ERROR_NONE ? "SUCESSO" : "ERRO: " . json_last_error_msg()));
            }
        }
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("Dados recebidos: " . json_encode($data ?? []));
            }
        }
        
        if (!isset($data["generationId"])) {
            error_log("ERRO: generationId não encontrado na resposta");
            $this->json(['error' => 'Resposta inválida da Gamma API'], 500);
            return;
        }
        
        $generationId = $data["generationId"];
        error_log("Generation ID recebido: " . $generationId);
        
        // Consultar status até estar completo
        $statusUrl = "https://public-api.gamma.app/v1.0/generations/$generationId";
        error_log("Iniciando verificação de status. URL: " . $statusUrl);
        $maxTentativas = 30; // Máximo de 30 tentativas (60 segundos)
        $tentativa = 0;
        $status = null;
        
        do {
            sleep(2);
            $tentativa++;
            error_log("Tentativa $tentativa/$maxTentativas - Verificando status...");
            
            $ch = curl_init($statusUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "X-API-KEY: $apiKey"
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            error_log("Status check - HTTP Code: $httpCode, Error: " . ($curlError ?: 'Nenhum'));
            
            if ($httpCode === 200) {
                $status = json_decode($res, true);
                error_log("Status recebido: " . json_encode($status ?? []));
                
                if (isset($status["status"])) {
                    error_log("Status da geração: " . $status["status"]);
                    if ($status["status"] === "completed") {
                        error_log("Geração completada com sucesso!");
                        break;
                    } elseif ($status["status"] === "failed") {
                        error_log("ERRO: Geração falhou na Gamma");
                        $this->json(['error' => 'Falha ao gerar apresentação na Gamma'], 500);
                        return;
                    }
                }
            } else {
                error_log("AVISO: HTTP Code diferente de 200 na verificação de status: $httpCode");
            }
            
        } while ($tentativa < $maxTentativas);
        
        if ($tentativa >= $maxTentativas) {
            error_log("ERRO: Timeout após $maxTentativas tentativas");
            $this->json(['error' => 'Timeout ao aguardar geração da apresentação'], 504);
            return;
        }
        
        if (!isset($status["gammaUrl"])) {
            error_log("ERRO: gammaUrl não encontrado no status final");
            $this->json(['error' => 'URL da apresentação não encontrada'], 500);
            return;
        }
        
        error_log("SUCESSO: Apresentação gerada. URL: " . $status["gammaUrl"]);

        try {
            $creditosService->consumir('professor', (int) $user['id'], $moduloCreditoSlides, (string) $generationId);
        } catch (Exception $e) {
            if (stripos($e->getMessage(), 'TudiCoins') !== false || stripos($e->getMessage(), 'insuficientes') !== false || stripos($e->getMessage(), 'Créditos') !== false) {
                $this->json([
                    'error' => 'Créditos insuficientes para concluir a geração dos slides.',
                    'details' => $e->getMessage(),
                ], 402);
                return;
            }
            throw $e;
        }

        // Extrair informações dos cards individuais se disponíveis
        $cards = [];
        if (isset($status["cards"]) && is_array($status["cards"])) {
            $cards = $status["cards"];
            error_log("Cards encontrados: " . count($cards));
        } elseif (isset($status["slides"]) && is_array($status["slides"])) {
            $cards = $status["slides"];
            error_log("Slides encontrados: " . count($cards));
        } elseif (isset($status["items"]) && is_array($status["items"])) {
            $cards = $status["items"];
            error_log("Items encontrados: " . count($cards));
        } else {
            // Tentar extrair cards de outras estruturas possíveis
            error_log("Estrutura completa do status: " . json_encode($status));
        }
        
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        
                error_log("=== GAMMA SLIDES - FIM ===");
        
            }
        
        }
        
        // Salvar slide no banco se solicitado
        if ($salvarSlide) {
            try {
                // Extrair título do conteúdo (primeiras palavras ou linha)
                $titulo = $this->extrairTitulo($conteudo);
                
                $this->db->insert(
                    "INSERT INTO professores_slides 
                     (professor_id, titulo, conteudo, url_gamma, generation_id, numero_slides, tema)
                     VALUES (:prof_id, :titulo, :conteudo, :url_gamma, :generation_id, :numero_slides, :tema)",
                    [
                        'prof_id' => $user['id'],
                        'titulo' => $titulo,
                        'conteudo' => $conteudo,
                        'url_gamma' => $status["gammaUrl"],
                        'generation_id' => $generationId,
                        'numero_slides' => $slides,
                        'tema' => $tema ?: null
                    ]
                );
                error_log("Slide salvo no banco de dados com sucesso");
            } catch (Exception $e) {
                error_log("Erro ao salvar slide no banco: " . $e->getMessage());
                // Não falhar a requisição se houver erro ao salvar
            }
        }
        
        // Retornar URL da apresentação e cards se disponíveis
        $response = [
            'status' => 'ok',
            'url' => $status["gammaUrl"],
            'generationId' => $generationId,
            'salvo' => $salvarSlide
        ];
        
        if (!empty($cards)) {
            $response['cards'] = $cards;
        }
        
        $this->json($response);
    }
    
    /**
     * Obtém a API Key do Gamma
     * Busca em múltiplas fontes: banco de dados > config/app.php > .env
     */
    private function getGammaApiKey()
    {
        $apiKey = null;
        
        // PRIORIDADE 1: Buscar do banco de dados (configuração via admin)
        try {
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../../Core/Database.php';
            }
            
            if (class_exists('Database', false)) {
                $db = \Database::getInstance();
                $config = $db->fetch(
                    "SELECT config_value FROM config_layout WHERE config_key = ?",
                    ['gamma_api_key']
                );
                if ($config && !empty($config['config_value'])) {
                    $apiKey = trim($config['config_value']);
                }
            }
        } catch (\Exception $e) {
            error_log("Erro ao buscar Gamma key do banco: " . $e->getMessage());
        }
        
        // PRIORIDADE 2: Tentar carregar do config/app.php
        if (empty($apiKey)) {
            try {
                $config = require __DIR__ . '/../../../config/app.php';
                $apiKey = $config['ai']['gamma_api_key'] ?? null;
            } catch (\Exception $e) {
                error_log("Erro ao carregar config/app.php: " . $e->getMessage());
                $apiKey = null;
            }
        }
        
        // PRIORIDADE 3: Carregar .env diretamente
        if (empty($apiKey)) {
            $envPath = __DIR__ . '/../../../.env';
            if (file_exists($envPath)) {
                $content = file_get_contents($envPath);
                
                // Remover BOM se presente
                if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                    $content = substr($content, 3);
                }
                
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Procurar por GAMMA_API_KEY primeiro
                    if (strpos($line, 'GAMMA_API_KEY=') === 0) {
                        $apiKey = substr($line, strlen('GAMMA_API_KEY='));
                        break;
                    }
                    // Fallback: procurar por GEMINI_API_KEY (compatibilidade)
                    if (empty($apiKey) && strpos($line, 'GEMINI_API_KEY=') === 0) {
                        $apiKey = substr($line, strlen('GEMINI_API_KEY='));
                        error_log("AVISO: Usando GEMINI_API_KEY do .env. Recomenda-se renomear para GAMMA_API_KEY.");
                        break;
                    }
                }
            }
        }
        
        // PRIORIDADE 4: Tentar getenv() diretamente
        if (empty($apiKey)) {
            $apiKey = getenv('GAMMA_API_KEY');
        }
        // Fallback: tentar GEMINI_API_KEY também
        if (empty($apiKey)) {
            $apiKey = getenv('GEMINI_API_KEY');
            if ($apiKey) {
                error_log("AVISO: Usando GEMINI_API_KEY de variável de ambiente. Recomenda-se usar GAMMA_API_KEY.");
            }
        }
        
        // PRIORIDADE 5: Tentar $_ENV
        if (empty($apiKey)) {
            $apiKey = $_ENV['GAMMA_API_KEY'] ?? null;
        }
        // Fallback: tentar GEMINI_API_KEY também
        if (empty($apiKey)) {
            $apiKey = $_ENV['GEMINI_API_KEY'] ?? null;
            if ($apiKey) {
                error_log("AVISO: Usando GEMINI_API_KEY de \$_ENV. Recomenda-se usar GAMMA_API_KEY.");
            }
        }
        
        return $apiKey ? trim($apiKey) : '';
    }
    
    /**
     * Extrai um título do conteúdo (primeiras palavras ou primeira linha)
     */
    private function extrairTitulo($conteudo)
    {
        $conteudo = trim($conteudo);
        if (empty($conteudo)) {
            return 'Slide sem título';
        }
        
        // Tenta pegar a primeira linha se tiver quebra de linha
        $linhas = explode("\n", $conteudo);
        $primeiraLinha = trim($linhas[0]);
        
        if (!empty($primeiraLinha) && strlen($primeiraLinha) <= 100) {
            return $primeiraLinha;
        }
        
        // Se a primeira linha for muito longa, pega as primeiras palavras
        $palavras = explode(' ', $conteudo);
        $titulo = '';
        $maxPalavras = 10;
        
        for ($i = 0; $i < min(count($palavras), $maxPalavras); $i++) {
            $titulo .= $palavras[$i] . ' ';
            if (strlen($titulo) > 80) {
                break;
            }
        }
        
        $titulo = trim($titulo);
        if (strlen($titulo) > 100) {
            $titulo = substr($titulo, 0, 97) . '...';
        }
        
        return !empty($titulo) ? $titulo : 'Slide sem título';
    }
}
}
