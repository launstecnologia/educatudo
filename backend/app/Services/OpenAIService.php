<?php
/**
 * EducaTudo - Serviço de Integração com OpenAI
 * Gerencia todas as interações com a API da OpenAI
 */

namespace App\Services;

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;

// Logger está no namespace global
require_once __DIR__ . '/../Core/Logger.php';

class OpenAIService
{
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1';
    private $visionCredentialsPath;
    
    public function __construct()
    {
        // Definir caminho das credenciais do Google Vision
        $this->visionCredentialsPath = __DIR__ . '/../../storage/educatudo-ai-476501-d0f26dcde160.json';
        
        $apiKey = null;
        
        // PRIORIDADE 1: Buscar do banco de dados (configuração via admin)
        try {
            // Garantir que a classe Database está carregada
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            
            // Usar \Database para forçar namespace global (a classe Database não tem namespace)
            if (class_exists('Database', false)) {
                $db = \Database::getInstance();
                $config = $db->fetch(
                    "SELECT config_value FROM config_layout WHERE config_key = ?",
                    ['openai_api_key']
                );
                if ($config && !empty($config['config_value'])) {
                    $apiKey = trim($config['config_value']);
                }
            }
        } catch (\Exception $e) {
            // Se falhar ao buscar do banco, continuar com outras fontes
            error_log("Erro ao buscar OpenAI key do banco: " . $e->getMessage());
        }
        
        // PRIORIDADE 2: Tentar carregar do config/app.php
        if (empty($apiKey)) {
            try {
                $config = require __DIR__ . '/../../config/app.php';
                $apiKey = $config['ai']['openai_api_key'] ?? null;
            } catch (\Exception $e) {
                $apiKey = null;
            }
        }
        
        // PRIORIDADE 3: Carregar .env diretamente
        if (empty($apiKey)) {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $content = file_get_contents($envPath);
                
                // Remover BOM se presente
                if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                    $content = substr($content, 3);
                }
                
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line, " \t\r\n");
                    if (strpos($line, 'OPENAI_API_KEY=') === 0) {
                        $apiKey = trim(substr($line, strlen('OPENAI_API_KEY=')), " \t\r\n\"'");
                        break;
                    }
                }
            }
        }
        
        // PRIORIDADE 4: Tentar getenv() diretamente
        if (empty($apiKey)) {
            $apiKey = getenv('OPENAI_API_KEY');
        }
        
        // PRIORIDADE 5: Tentar $_ENV
        if (empty($apiKey)) {
            $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        }
        
        if (empty($apiKey)) {
            throw new \Exception('OPENAI_API_KEY não configurada. Configure via Admin > Dev Settings > Chaves de API ou no arquivo .env');
        }
        $this->apiKey = $apiKey;
    }
    
    /**
     * Retorna a chave da API OpenAI
     */
    public function getOpenAIApiKey()
    {
        return $this->apiKey;
    }
    
    /**
     * Gera exercício usando IA baseado no conteúdo da aula
     */
    public function gerarExercicio($contextoCompleto, $tipoExercicio = 'múltipla_escolha', $quantidade = 5)
    {
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            error_log("=== INÍCIO OpenAIService::gerarExercicio ===");
        }
        error_log("Contexto: " . $contextoCompleto);
        error_log("Tipo: " . $tipoExercicio);
        error_log("Quantidade: " . $quantidade);
        
        try {
            $prompt = $this->criarPromptExercicio($contextoCompleto, $tipoExercicio, $quantidade);
            error_log("Prompt criado: " . $prompt);
            
            $response = $this->fazerRequisicao([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um professor especialista em criar exercícios educacionais. Sempre retorne respostas em formato JSON válido.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'response_format' => [
                    'type' => 'json_object'
                ]
            ], 3, 0, 'exercicios');
            
            error_log("Resposta da OpenAI: " . print_r($response, true));
            
            $resultado = $this->processarRespostaExercicio($response);
            error_log("Resultado processado: " . print_r($resultado, true));
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== FIM OpenAIService::gerarExercicio ===");
            }
            
            return $resultado;
            
        } catch (\Exception $e) {
            error_log("ERRO em OpenAIService::gerarExercicio: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            throw $e;
        }
    }

    /**
     * Gera exercícios para jornada usando prompt configurável (Dev Settings)
     */
    public function gerarExerciciosJornadaIA($contextoCompleto, $tipoExercicio = 'múltipla_escolha', $quantidade = 5)
    {
        try {
            $template = $this->getPromptConfig('prompt_gerar_exercicios_jornada');
            if (empty($template)) {
                return $this->gerarExercicio($contextoCompleto, $tipoExercicio, $quantidade);
            }

            $vars = $this->buildContextVariables($contextoCompleto, $tipoExercicio, $quantidade);
            $prompt = $this->applyPromptTemplate($template, $vars);
            $prompt = $this->ensureContextInPrompt($prompt, $vars['contexto'] ?? '');

            $response = $this->fazerRequisicao([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um professor especialista em criar exercícios educacionais para uma plataforma que utiliza MathJax. Use \\( \\) ou \\[ \\] para fórmulas matemáticas; nunca use $. Em comandos LaTeX use SEMPRE a barra invertida: \\ce{} para química, \\rightarrow para seta, \\frac{}{}, \\sqrt{}, etc. Nunca escreva ightarrow (deve ser \\rightarrow) nem ce{ (deve ser \\ce{). Sempre retorne respostas em formato JSON válido.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7
            ], 3, 0, 'exercicios');

            return $this->processarRespostaExercicio($response);
        } catch (\Exception $e) {
            error_log("ERRO em OpenAIService::gerarExerciciosJornadaIA: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Gera questões no estilo ENEM: enunciado contextual + imagem (imagem_prompt) + pergunta + alternativas A-E.
     * Retorno: ['exercicios' => [ ['titulo','enunciado','imagem_prompt','pergunta','alternativas'=>['A'..'E'],'correta','explicacao'], ... ]]
     */
    public function gerarExerciciosEstiloENEM($contextoCompleto, $quantidade = 3)
    {
        try {
            $prompt = $this->getPromptEstiloENEM($contextoCompleto, $quantidade);
            $response = $this->fazerRequisicao([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um professor especialista em criar questões no estilo ENEM. Cada questão tem texto contextual (enunciado), uma imagem (descrita em imagem_prompt para geração posterior), a pergunta e 5 alternativas. Use \\( \\) ou \\[ \\] para fórmulas; nunca use $. Retorne APENAS JSON válido, sem markdown.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7
            ], 3, 0, 'exercicios');

            return $this->processarRespostaExercicio($response);
        } catch (\Exception $e) {
            error_log("ERRO em OpenAIService::gerarExerciciosEstiloENEM: " . $e->getMessage());
            throw $e;
        }
    }

    private function getPromptEstiloENEM($contextoCompleto, $quantidade)
    {
        return "Crie EXATAMENTE {$quantidade} questões no estilo ENEM (imagem integrada ao enunciado).

CONTEXTO EDUCACIONAL:
{$contextoCompleto}

IMPORTANTE - Tipo de gráfico/diagrama: Se o professor informou o tipo de gráfico ou diagrama desejado (ex.: gráfico de barras, de pizza, diagrama de ciclo, esquema), use EXATAMENTE esse tipo no campo imagem_prompt. NÃO use sempre gráfico de linha; varie conforme o pedido: barras, pizza, linha, diagrama, esquema científico, mapa, reação química. O professor define o que quer.

Cada questão deve ter:
1. **enunciado**: texto contextual (cenário, gráfico descrito, situação) que introduz o tema.
2. **imagem_prompt**: descrição objetiva para gerar UMA imagem no TIPO pedido pelo professor (gráfico de barras, pizza, linha, diagrama, esquema, mapa ou reação química). Estilo educacional, simples, fundo branco. NÃO inclua texto de pergunta nem alternativas no prompt da imagem.
3. **pergunta**: a pergunta em si, que o aluno responde com base no enunciado e na imagem.
4. **alternativas**: exatamente 5 opções (A, B, C, D, E); apenas uma correta.
5. **correta**: letra da alternativa correta (A, B, C, D ou E).
6. **explicacao**: justificativa da resposta correta.

REGRAS:
- Fórmulas: use \\( \\) ou \\[ \\] para LaTeX; nunca use $.
- alternativas devem usar chaves em MAIÚSCULAS: \"A\", \"B\", \"C\", \"D\", \"E\".
- Retorne APENAS o JSON, sem texto antes ou depois e sem ```json.

FORMATO OBRIGATÓRIO:
{
  \"questoes\": [
    {
      \"titulo\": \"Título da questão\",
      \"enunciado\": \"Texto contextual que introduz o tema e a situação.\",
      \"imagem_prompt\": \"Descrição para gerar apenas a figura: ex. Gráfico de função quadrática com eixos x e y, parábola com vértice marcado, fundo branco, estilo educacional.\",
      \"pergunta\": \"Pergunta que o aluno deve responder com base no enunciado e na imagem.\",
      \"alternativas\": {
        \"A\": \"\",
        \"B\": \"\",
        \"C\": \"\",
        \"D\": \"\",
        \"E\": \"\"
      },
      \"correta\": \"A\",
      \"explicacao\": \"Explicação da resposta correta.\"
    }
  ]
}

Crie EXATAMENTE {$quantidade} objetos no array \"questoes\".";
    }

    /**
     * Gera questões de prova usando prompt configurável (Dev Settings)
     */
    public function gerarProvaIA($tema, $materia, $quantidade, $nivel, $contextoAdicional = '', $tipo = 'alternativas', $serie = '', $comImagens = false)
    {
        try {
            $vars = [
                'tema' => $tema,
                'materia' => $materia,
                'serie' => $serie ?: 'Não especificada',
                'quantidade_questoes' => $quantidade,
                'nivel_dificuldade' => is_array($nivel) ? implode(', ', $nivel) : $nivel,
                'tipo_questao' => $tipo,
                'contexto' => $contextoAdicional ?: $tema,
            ];

            $templateDb = $this->getPromptConfig('prompt_gerar_prova');
            $promptBase = !empty($templateDb) ? $templateDb : $this->getDefaultPromptBase();

            if ($comImagens) {
                $secaoImagensDb = $this->getPromptConfig('prompt_prova_imagens');
                $secaoImagens = !empty($secaoImagensDb) ? $secaoImagensDb : $this->getDefaultSecaoImagens();

                $prompt = $promptBase . "\n\n" . $secaoImagens;
                $prompt .= $this->getSecaoTiposQuestao();
                $prompt .= $this->getSecaoTituloInstrucoes(true);
                $prompt .= $this->getSecaoFormatoSaidaComImagens();

                $systemMsg = 'Você é um especialista em avaliação educacional. Sua ÚNICA resposta deve ser um JSON válido, sem nenhum texto antes ou depois, sem markdown (sem ```json). O JSON deve conter: titulo, instrucoes, questoes (array). Cada questão deve ter: numero, tipo, enunciado, imagem (objeto ou null), alternativas (objeto a-e ou null), resposta_correta, explicacao. Para imagens, use o formato com tipo/descricao/prompt_imagem/dados_grafico conforme instruído.';
            } else {
                $prompt = $promptBase . "\n\n" . $this->getSecaoBloqueioVisual();
                $prompt .= $this->getSecaoTiposQuestao();
                $prompt .= $this->getSecaoTituloInstrucoes(false);
                $prompt .= $this->getSecaoFormatoSaidaSemImagens();

                $systemMsg = 'Você é um especialista em avaliação educacional. Sua ÚNICA resposta deve ser um JSON válido, sem nenhum texto antes ou depois, sem markdown (sem ```json). O JSON deve conter: titulo, instrucoes, questoes (array). Cada questão deve ter: numero, tipo, enunciado, alternativas (objeto a-e ou null), resposta_correta, explicacao. NÃO inclua campo "imagem".';
            }

            $prompt = $this->applyPromptTemplate($prompt, $vars);
            $prompt = $this->ensureContextInPrompt($prompt, $contextoAdicional);

            $requestData = [
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemMsg
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.6
            ];

            $this->logProvaIAInput($tema, $materia, $quantidade, $nivel, $contextoAdicional, $tipo, $vars, $prompt, $requestData);

            $response = $this->fazerRequisicao($requestData, 3, 0, 'prova');

            $this->logProvaIAOutput($response);

            return $this->processarRespostaProvaIA($response);
        } catch (\Exception $e) {
            error_log("ERRO em OpenAIService::gerarProvaIA: " . $e->getMessage());
            throw $e;
        }
    }

    private function getDefaultPromptBase(): string
    {
        return 'Você é um especialista em avaliação educacional e design instrucional da plataforma EducaTudo.

Sua tarefa é criar uma avaliação rigorosa, original e pedagogicamente alinhada aos parâmetros abaixo.

══════════════════════════════════════
 PARÂMETROS DA AVALIAÇÃO
══════════════════════════════════════

- Disciplina: {materia}
- Série/Ano: {serie}
- Quantidade de questões: {quantidade_questoes}
- Nível de dificuldade: {nivel_dificuldade}
- Tipo de questões: {tipo_questao}

══════════════════════════════════════
 O QUE O PROFESSOR PEDIU
══════════════════════════════════════

"{contexto}"

COMO INTERPRETAR:
1. Identifique os tópicos, conceitos e recortes mencionados.
2. Se a descrição for curta, expanda usando o currículo padrão de "{materia}" para "{serie}".
3. Se for detalhada, respeite os limites — não extrapole.
4. Se o professor diz o que NÃO quer, obedeça rigorosamente.
5. Todas as questões devem ser respondíveis dentro do currículo de "{materia}" para "{serie}".

══════════════════════════════════════
 ADAPTAÇÃO À SÉRIE
══════════════════════════════════════

- Fundamental II (6º ao 9º ano): linguagem acessível, enunciados objetivos, cotidiano do adolescente.
- Ensino Médio (1ª a 3ª série): linguagem formal, enunciados elaborados, interdisciplinaridade possível.

══════════════════════════════════════
 NÍVEIS DE DIFICULDADE (Taxonomia de Bloom)
══════════════════════════════════════

"Fácil"
→ LEMBRAR e COMPREENDER. Enunciados diretos. Resposta claramente distinguível.
→ Distratores: erros conceituais comuns ou confusões de definição.

"Médio"
→ APLICAR. Situações-problema com transferência de conhecimento.
→ Distratores: erros procedimentais típicos (troca de sinal, inversão de causa/efeito).

"Difícil"
→ ANALISAR e AVALIAR. Cruzamento de múltiplos conceitos. Enunciados longos.
→ Distratores: altamente plausíveis, raciocínio coerente porém incorreto.

"Desafio"
→ Nível VESTIBULAR (ENEM, FUVEST, ITA). Densos e interdisciplinares.
→ Distratores sofisticados testando nuances conceituais.

══════════════════════════════════════
 REGRAS DE CONSTRUÇÃO
══════════════════════════════════════

- Nenhuma questão pode revelar ou depender da resposta de outra.
- Distribua cobertura sem repetir conceitos.
- Varie posição da alternativa correta e formatos de enunciado.
- Distratores sempre plausíveis. Proibido "todas/nenhuma das anteriores".
- Fórmulas em cifrões duplos: $$...$$';
    }

    private function getDefaultSecaoImagens(): string
    {
        return '══════════════════════════════════════
 IMAGENS NAS QUESTÕES
══════════════════════════════════════

O professor ATIVOU imagens. Inclua quando o conteúdo se beneficiar.

USAR: gráficos de funções, figuras geométricas, diagramas científicos, infográficos, ilustrações, mapas.
NÃO USAR: questões puramente conceituais, imagens decorativas. Não force em todas.

Campo "imagem" quando necessário:
{
  "imagem": {
    "tipo": "grafico | geometria | diagrama | ilustracao | infografico | mapa",
    "descricao": "Texto alternativo",
    "prompt_imagem": "Descrição técnica detalhada (educacional, fundo branco, estilo livro didático)",
    "dados_grafico": { "chart_type": "line", "titulo_grafico": "...", "eixo_x": {...}, "eixo_y": {...}, "datasets": [...] }
  }
}

- "dados_grafico" APENAS para tipo "grafico" e "infografico".
- Sem imagem: "imagem" = null.';
    }

    private function getSecaoBloqueioVisual(): string
    {
        return '══════════════════════════════════════
 BLOQUEIO VISUAL
══════════════════════════════════════

NUNCA crie questões que dependam de imagens, gráficos, tabelas, figuras ou mapas.
Se um cenário exigir dados visuais, descreva-os textualmente no enunciado.';
    }

    private function getSecaoTiposQuestao(): string
    {
        return '

══════════════════════════════════════
 REGRAS POR TIPO DE QUESTÃO
══════════════════════════════════════

MÚLTIPLA ESCOLHA: 5 alternativas "a" a "e". "resposta_correta" = letra.
DISSERTATIVA: "alternativas" = null. "resposta_correta" = espelho de correção.
VERDADEIRO OU FALSO: "alternativas" = { "a": "Verdadeiro", "b": "Falso" }.
MISTO: Agrupe por tipo. Campo "tipo" obrigatório em cada.';
    }

    private function getSecaoTituloInstrucoes(bool $comImagens): string
    {
        $extra = $comImagens
            ? "\nSe houver imagens: \"Algumas questões possuem imagens de apoio. Analise-as com atenção.\""
            : '';

        return '

══════════════════════════════════════
 TÍTULO E INSTRUÇÕES
══════════════════════════════════════

TÍTULO: Descritivo e inteligente baseado no conteúdo.
INSTRUÇÕES: Curtas. Tipos de questão, orientações, notação.' . $extra;
    }

    private function getSecaoFormatoSaidaComImagens(): string
    {
        return '

══════════════════════════════════════
 FORMATO DE SAÍDA
══════════════════════════════════════

Responda APENAS com JSON válido. Sem texto antes, sem texto depois, sem markdown.

{
  "titulo": "...",
  "instrucoes": "...",
  "questoes": [
    {
      "numero": 1,
      "tipo": "multipla_escolha",
      "enunciado": "...",
      "imagem": { "tipo": "...", "descricao": "...", "prompt_imagem": "...", "dados_grafico": {...} },
      "alternativas": { "a": "...", "b": "...", "c": "...", "d": "...", "e": "..." },
      "resposta_correta": "b",
      "explicacao": "..."
    }
  ]
}

- "numero" sequencial. "tipo" e "explicacao" obrigatórios em TODAS.
- "imagem" obrigatório em TODAS — null quando não há.
- "dados_grafico" obrigatório para tipo "grafico"/"infografico".
- Sem "gabarito" separado. JSON válido. Sem trailing commas.';
    }

    private function getSecaoFormatoSaidaSemImagens(): string
    {
        return '

══════════════════════════════════════
 FORMATO DE SAÍDA
══════════════════════════════════════

Responda APENAS com JSON válido. Sem texto antes, sem texto depois, sem markdown.

{
  "titulo": "...",
  "instrucoes": "...",
  "questoes": [
    {
      "numero": 1,
      "tipo": "multipla_escolha",
      "enunciado": "...",
      "alternativas": { "a": "...", "b": "...", "c": "...", "d": "...", "e": "..." },
      "resposta_correta": "b",
      "explicacao": "..."
    }
  ]
}

- "numero" sequencial. "tipo" e "explicacao" obrigatórios em TODAS.
- Sem "gabarito" separado. JSON válido. Sem trailing commas.';
    }

    /**
     * Compara resumos do professor e aluno, gerando feedback inteligente
     */
    public function compararResumos($resumoProfessor, $resumoAluno, $materia)
    {
        $prompt = $this->criarPromptComparacaoResumos($resumoProfessor, $resumoAluno, $materia);
        
        $response = $this->fazerRequisicao([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Você é um tutor educacional especialista em análise de aprendizado. Sempre retorne respostas em formato JSON válido.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.5
        ], 3, 0, 'correcao_redacao');
        
        return $this->processarRespostaComparacao($response);
    }
    
    /**
     * Gera explicações complementares baseadas nas lacunas identificadas
     */
    public function gerarExplicacaoComplementar($lacunasIdentificadas, $materia)
    {
        $prompt = $this->criarPromptExplicacaoComplementar($lacunasIdentificadas, $materia);
        
        $response = $this->fazerRequisicao([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Você é um tutor especialista em explicações educacionais claras e didáticas.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.6
        ], 3, 0, 'explicacao');
        
        return $this->processarRespostaExplicacao($response);
    }
    
    /**
     * Cria prompt para geração de exercícios
     */
    private function criarPromptExercicio($contextoCompleto, $tipoExercicio, $quantidade)
    {
        $tipoDescricao = '';
        if ($tipoExercicio === 'alternativas') {
            $tipoDescricao = 'múltipla escolha com 4 ou 5 alternativas (A, B, C, D, E)';
        } elseif ($tipoExercicio === 'verdadeiro_falso') {
            $tipoDescricao = 'verdadeiro ou falso';
        } elseif ($tipoExercicio === 'dissertativa') {
            $tipoDescricao = 'dissertativa (resposta aberta)';
        } else {
            $tipoDescricao = $tipoExercicio;
        }
        
        return "Você é um professor especialista em criar exercícios educacionais para uma plataforma que utiliza MathJax para renderização matemática.

TAREFA: Crie EXATAMENTE {$quantidade} exercícios do tipo {$tipoDescricao}.

CONTEXTO EDUCACIONAL:
{$contextoCompleto}

REGRAS OBRIGATÓRIAS PARA FÓRMULAS MATEMÁTICAS (MathJax):
- Toda expressão matemática deve estar dentro de \\( ... \\) (inline) ou \\[ ... \\] (display).
- É proibido utilizar \$...\$.
- Nunca escreva LaTeX fora dos delimitadores.
- Utilize apenas comandos simples: \\frac, \\sqrt e expoentes.
- Não utilize ambientes complexos (align, cases, array).

FORMATO DE RESPOSTA (JSON OBRIGATÓRIO):
{
    \"questoes\": [
        {
            \"titulo\": \"Título do exercício\",
            \"enunciado\": \"Pergunta ou problema (use \\( \\) ou \\[ \\] para fórmulas)\",
            \"alternativas\": {
                \"a\": \"Alternativa A\",
                \"b\": \"Alternativa B\", 
                \"c\": \"Alternativa C\",
                \"d\": \"Alternativa D\",
                \"e\": \"Alternativa E (opcional)\"
            },
            \"resposta_correta\": \"a\",
            \"explicacao\": \"Explicação detalhada da resposta correta\",
            \"dificuldade\": \"fácil|médio|difícil\"
        }
    ]
}

REGRAS OBRIGATÓRIAS:
1. Crie EXATAMENTE {$quantidade} questões no array \"questoes\"
2. Cada questão deve ser única e diferente das outras
3. Varie a dificuldade entre as questões
4. Use linguagem clara e adequada ao nível educacional
5. As alternativas devem ser plausíveis e bem elaboradas
6. Para exercícios de múltipla escolha, inclua pelo menos 4 alternativas
7. Retorne APENAS o JSON válido, sem texto adicional antes ou depois
8. NÃO inclua markdown code blocks (```json), apenas o JSON puro
9. Em enunciado e alternativas, use \\( \\) ou \\[ \\] para fórmulas; nunca use \$
10. Escreva o enunciado e as alternativas em texto contínuo, sem quebras de linha desnecessárias (evite \\n entre frases curtas; deixe tudo em um fluxo natural de leitura).

IMPORTANTE: O array \"questoes\" deve conter EXATAMENTE {$quantidade} objetos. Não retorne menos que isso.";
    }

    /**
     * Busca prompt configurável no config_layout
     */
    private function getPromptConfig($key)
    {
        try {
            if (!class_exists('\\Database')) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            $db = \Database::getInstance();
            $row = $db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                [$key]
            );
            return trim($row['config_value'] ?? '');
        } catch (\Exception $e) {
            error_log("Erro ao buscar prompt {$key}: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Monta variáveis básicas a partir do contexto textual
     */
    private function buildContextVariables($contextoCompleto, $tipoExercicio, $quantidade)
    {
        $tipoDescricao = '';
        if ($tipoExercicio === 'alternativas' || $tipoExercicio === 'múltipla_escolha') {
            $tipoDescricao = 'múltipla escolha com 4 ou 5 alternativas (A, B, C, D, E)';
        } elseif ($tipoExercicio === 'verdadeiro_falso') {
            $tipoDescricao = 'verdadeiro ou falso';
        } elseif ($tipoExercicio === 'dissertativa') {
            $tipoDescricao = 'dissertativa (resposta aberta)';
        } else {
            $tipoDescricao = $tipoExercicio;
        }

        $vars = [
            'tema' => $this->extractContextValue($contextoCompleto, 'Tema')
                ?: $this->extractContextValue($contextoCompleto, 'Título da aula')
                ?: $this->extractContextValue($contextoCompleto, 'Jornada')
                ?: 'tema informado pelo professor',
            'materia' => $this->extractContextValue($contextoCompleto, 'Matéria') ?: '',
            'nivel_dificuldade' => $this->extractContextValue($contextoCompleto, 'Nível')
                ?: $this->extractContextValue($contextoCompleto, 'Níveis de dificuldade')
                ?: '',
            'tipo_exercicio' => $tipoExercicio,
            'tipoDescricao' => $tipoDescricao,
            'quantidade_exercicios' => $quantidade,
            'quantidade' => $quantidade,
            'contexto' => $contextoCompleto,
            'contextoCompleto' => $contextoCompleto
        ];

        return $vars;
    }

    /**
     * Extrai valor de uma linha do contexto (ex: "Matéria: História")
     */
    private function extractContextValue($context, $label)
    {
        $pattern = '/^' . preg_quote($label, '/') . '\s*:\s*(.+)$/mi';
        if (preg_match($pattern, $context, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Aplica variáveis em template do prompt
     */
    private function applyPromptTemplate($template, array $vars)
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }
        return $template;
    }

    /**
     * Garante que o contexto do professor seja incluído no prompt final
     */
    private function ensureContextInPrompt($prompt, $contexto)
    {
        $contexto = trim((string) $contexto);
        if ($contexto === '') {
            return $prompt;
        }
        if (stripos($prompt, $contexto) !== false) {
            return $prompt;
        }
        return $prompt . "\n\nCONTEXTO DO PROFESSOR (OBRIGATÓRIO USAR):\n" . $contexto;
    }
    
    /**
     * Cria prompt para comparação de resumos
     */
    private function criarPromptComparacaoResumos($resumoProfessor, $resumoAluno, $materia)
    {
        return "Analise os seguintes resumos da matéria {$materia} e identifique lacunas no aprendizado do aluno:

RESUMO DO PROFESSOR (oficial):
{$resumoProfessor}

RESUMO DO ALUNO:
{$resumoAluno}

Retorne um JSON com a seguinte estrutura:
{
    \"pontos_acertados\": [\"Lista de pontos que o aluno compreendeu corretamente\"],
    \"lacunas_identificadas\": [\"Lista de lacunas no aprendizado do aluno\"],
    \"nivel_compreensao\": \"básico|intermediário|avançado\",
    \"sugestoes_melhoria\": [\"Sugestões para melhorar o aprendizado\"],
    \"pontuacao\": \"Nota de 0 a 10\"
}";
    }
    
    /**
     * Cria prompt para explicação complementar
     */
    private function criarPromptExplicacaoComplementar($lacunasIdentificadas, $materia)
    {
        return "Com base nas seguintes lacunas identificadas na matéria {$materia}, crie explicações complementares claras e didáticas:

LACUNAS IDENTIFICADAS:
{$lacunasIdentificadas}

Forneça explicações detalhadas, exemplos práticos e analogias para ajudar o aluno a compreender melhor esses conceitos.";
    }

    /**
     * Gera explicação didática do conteúdo de um flashcard (pergunta + resposta).
     * Se o aluno já recebeu explicações e ainda não entendeu, gera uma versão mais simples.
     *
     * @param string $pergunta Conteúdo da pergunta do cartão
     * @param string $resposta Resposta do cartão
     * @param array $explicacoesAnteriores Lista de explicações já dadas (para simplificar ainda mais)
     * @return string Texto da explicação
     */
    public function explicarFlashcard($pergunta, $resposta, array $explicacoesAnteriores = [])
    {
        $system = 'Você é a Tudinha, tutora educacional do EducaTudo. Sua tarefa é explicar o conteúdo de um flashcard de forma clara e acessível para o aluno. Use linguagem simples, exemplos do dia a dia quando ajudar e evite jargões desnecessários. Responda apenas com o texto da explicação, sem títulos ou marcadores desnecessários.';
        if (empty($explicacoesAnteriores)) {
            $user = "O aluno marcou que não entendeu este flashcard. Gere uma explicação didática e fácil de compreender.\n\nPERGUNTA DO CARTÃO:\n{$pergunta}\n\nRESPOSTA DO CARTÃO:\n{$resposta}";
        } else {
            $historico = implode("\n\n--- Tentativa anterior ---\n\n", $explicacoesAnteriores);
            $user = "O aluno ainda não entendeu após as explicações anteriores. Gere uma NOVA explicação ainda mais simples e fácil de entender. Use palavras mais simples, mais exemplos ou uma analogia diferente. Não repita a mesma explicação.\n\nPERGUNTA DO CARTÃO:\n{$pergunta}\n\nRESPOSTA DO CARTÃO:\n{$resposta}\n\nEXPLICAÇÕES JÁ DADAS (para não repetir e para simplificar ainda mais):\n{$historico}";
        }
        $response = $this->fazerRequisicao([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user]
            ],
            'temperature' => 0.6
        ], 3, 0, 'explicacao');
        return $this->processarRespostaExplicacao($response);
    }

    /**
     * Modera conteúdo do fórum (pergunta ou resposta): detecta fora de contexto ou inadequado.
     * Retorna ['apropriado' => bool, 'motivo' => string]. Se apropriado=false, motivo explica o que a IA detectou.
     */
    public function moderarConteudoForum($texto, $ehPergunta = true)
    {
        $tipo = $ehPergunta ? 'pergunta' : 'resposta';
        $system = 'Você é um moderador do fórum educacional EducaTudo. Avalie se o conteúdo da ' . $tipo . ' está APROPRIADO para publicação.

CONTEXTO: É um fórum de escola onde alunos e professores trocam dúvidas, explicações e opiniões sobre matérias. Linguagem informal e positiva é normal.

SEMPRE APROPRIADO (não bloquear): dúvidas sobre matérias; explicações; agradecimentos ("obrigado", "muito bom", "ajudou muito"); elogios ("ótima explicação", "muito bom para o ser humano", "concordo"); comentários curtos de apoio; discussão educacional mesmo informal; perguntas e respostas sobre estudos.

BLOQUEAR APENAS SE for claramente: insulto ou ofensa direta a alguém; bullying; spam ou propaganda; discurso de ódio; conteúdo ilegal; divulgação de dados pessoais de terceiros; ou texto totalmente sem relação com ambiente escolar (ex.: apenas anúncio comercial).

Em caso de DÚVIDA, considere APROPRIADO (true). Só marque apropriado: false quando houver violação clara.

Responda APENAS com um JSON válido, sem texto antes ou depois: {"apropriado": true ou false, "motivo": "breve explicação em português"}';
        $user = "Avalie o seguinte conteúdo de " . $tipo . " do fórum:\n\n" . $texto;
        try {
            $response = $this->fazerRequisicao([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user]
                ],
                'temperature' => 0.2,
                'max_tokens' => 200
            ], 2, 0, 'general');
            $content = $response['choices'][0]['message']['content'] ?? '';
            $content = trim($content);
            $json = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $json = $this->extrairJsonDaResposta($content);
            }
            if (!$json || !isset($json['apropriado'])) {
                return ['apropriado' => true, 'motivo' => 'Não foi possível analisar; conteúdo permitido.'];
            }
            return [
                'apropriado' => (bool) $json['apropriado'],
                'motivo' => isset($json['motivo']) ? trim((string) $json['motivo']) : ''
            ];
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                \Logger::warning('OpenAIService::moderarConteudoForum falhou', ['exception' => $e], 'forum');
            }
            return ['apropriado' => true, 'motivo' => ''];
        }
    }
    
    /**
     * Faz requisição para a API da OpenAI com retry automático e backoff exponencial
     *
     * @param array $data Dados da requisição
     * @param int $maxRetries Número máximo de tentativas (padrão: 3)
     * @param int $retryCount Contador de tentativas (interno)
     * @param string $usageType Tipo de uso (chat, correcao_redacao, exercicios, etc.)
     * @return array Resposta decodificada da API
     */
    private function fazerRequisicao($data, $maxRetries = 3, $retryCount = 0, $usageType = 'general')
    {
        $startTime = microtime(true);
        $curl = curl_init();

        // Verificar se está em ambiente de desenvolvimento
        $isDevelopment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
                         strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                         (defined('ENVIRONMENT') && ENVIRONMENT === 'development'));

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->baseUrl . '/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 120, // Aumentado de 30 para 120 segundos
            CURLOPT_CONNECTTIMEOUT => 10, // Timeout de conexão
            CURLOPT_HEADER => true, // Incluir headers na resposta para ler rate limit
            CURLOPT_SSL_VERIFYPEER => !$isDevelopment, // Ignorar verificação SSL em desenvolvimento
            CURLOPT_SSL_VERIFYHOST => $isDevelopment ? 0 : 2 // Ignorar verificação de host em desenvolvimento
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        // Separar headers do body
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        curl_close($curl);
        
        if ($error) {
            // Logar erro no sistema de logs
            if (!class_exists('\Logger')) {
                require_once __DIR__ . '/../Core/Logger.php';
            }
            \Logger::openaiError(
                "Erro cURL na requisição OpenAI: " . $error,
                [
                    'http_code' => $httpCode,
                    'retry_count' => $retryCount,
                    'max_retries' => $maxRetries,
                    'data_size' => strlen(json_encode($data))
                ]
            );
            
            error_log("Erro cURL ao fazer requisição para OpenAI: " . $error);
            error_log("HTTP Code: " . $httpCode);
            error_log("Dados enviados: " . json_encode($data));
            
            // Retry em caso de erro de conexão (se ainda houver tentativas)
            if ($retryCount < $maxRetries) {
                $delay = $this->calcularBackoffDelay($retryCount);
                $tentativaAtual = $retryCount + 1;
                error_log("🔁 Retry {$tentativaAtual}/{$maxRetries} após {$delay}s devido a erro cURL");
                sleep($delay);
                return $this->fazerRequisicao($data, $maxRetries, $retryCount + 1);
            }
            
            throw new \Exception("Erro cURL: " . $error);
        }
        
        if ($httpCode !== 200) {
            // Logar erro HTTP
            if (!class_exists('\Logger')) {
                require_once __DIR__ . '/../Core/Logger.php';
            }
            \Logger::openaiError(
                "Erro HTTP {$httpCode} na requisição OpenAI",
                [
                    'http_code' => $httpCode,
                    'response_body' => substr($body, 0, 1000),
                    'retry_count' => $retryCount
                ]
            );
            
            error_log("Erro HTTP {$httpCode} ao fazer requisição para OpenAI");
            error_log("Resposta: " . $body);
            
            // Tratar erro 429 (rate limit) com retry e backoff
            if ($httpCode === 429) {
                $decoded = json_decode($body, true);
                
                // Se for quota excedida (insufficient_quota), não fazer retry
                if (isset($decoded['error']['type']) && $decoded['error']['type'] === 'insufficient_quota') {
                    \Logger::openaiError(
                        "Quota da API OpenAI excedida",
                        [
                            'error_type' => 'insufficient_quota',
                            'error_message' => $decoded['error']['message'] ?? 'N/A'
                        ]
                    );
                    throw new \Exception("Quota da API OpenAI excedida. Por favor, verifique o plano e detalhes de cobrança. Tente novamente mais tarde.");
                }
                
                // Rate limit - fazer retry com backoff exponencial
                if ($retryCount < $maxRetries) {
                    // Tentar extrair retry-after do header ou usar delay calculado
                    $retryAfter = $this->extrairRetryAfter($headers);
                    $delay = $retryAfter ?: $this->calcularBackoffDelay($retryCount);
                    $tentativaAtual = $retryCount + 1;
                    
                    error_log("🔁 Rate limit detectado - Retry {$tentativaAtual}/{$maxRetries} após {$delay}s");
                    sleep($delay);
                    return $this->fazerRequisicao($data, $maxRetries, $retryCount + 1);
                }
                
                throw new \Exception("Limite de requisições da API OpenAI atingido após {$maxRetries} tentativas. Tente novamente em alguns instantes.");
            }
            
            // Retry para outros erros 5xx (erros do servidor)
            if ($httpCode >= 500 && $httpCode < 600 && $retryCount < $maxRetries) {
                $delay = $this->calcularBackoffDelay($retryCount);
                $tentativaAtual = $retryCount + 1;
                error_log("🔁 Retry {$tentativaAtual}/{$maxRetries} após {$delay}s devido a erro {$httpCode}");
                sleep($delay);
                return $this->fazerRequisicao($data, $maxRetries, $retryCount + 1);
            }
            
            // Tentar extrair mensagem de erro mais específica
            $decoded = json_decode($body, true);
            if (isset($decoded['error']['message'])) {
                $errorMessage = $decoded['error']['message'];
                // Verificar se é erro de quota/limite
                if (isset($decoded['error']['type']) && $decoded['error']['type'] === 'insufficient_quota') {
                    throw new \Exception("Quota da API OpenAI excedida. Por favor, verifique o plano e detalhes de cobrança. Tente novamente mais tarde.");
                }
                throw new \Exception("Erro HTTP {$httpCode}: " . $errorMessage);
            }
            throw new \Exception("Erro HTTP {$httpCode}: " . substr($body, 0, 500));
        }
        
        $decoded = json_decode($body, true, 512, JSON_UNESCAPED_UNICODE);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Erro ao decodificar resposta JSON da OpenAI: " . json_last_error_msg());
            error_log("Resposta recebida: " . substr($body, 0, 500));
            
            // Retry em caso de JSON inválido (pode ser resposta corrompida)
            if ($retryCount < $maxRetries) {
                $delay = $this->calcularBackoffDelay($retryCount);
                $tentativaAtual = $retryCount + 1;
                error_log("🔁 Retry {$tentativaAtual}/{$maxRetries} após {$delay}s devido a JSON inválido");
                sleep($delay);
                return $this->fazerRequisicao($data, $maxRetries, $retryCount + 1);
            }
            
            throw new \Exception("Erro ao decodificar resposta JSON");
        }
        
        // Log de sucesso se foi retry
        if ($retryCount > 0) {
            error_log("✅ Requisição bem-sucedida após {$retryCount} tentativa(s)");
        }

        // Capturar métricas de uso da IA
        $this->recordMetrics($decoded, $startTime, $usageType, $httpCode, $error);

        return $decoded;
    }
    
    /**
     * Calcula delay de backoff exponencial com jitter
     * 
     * @param int $retryCount Número da tentativa (0 = primeira tentativa)
     * @return int Delay em segundos
     */
    private function calcularBackoffDelay($retryCount)
    {
        // Backoff exponencial: 2^retryCount segundos
        // Base: 1s, 2s, 4s, 8s, etc.
        $baseDelay = pow(2, $retryCount);
        
        // Adicionar jitter aleatório (0-1s) para evitar thundering herd
        $jitter = rand(0, 1000) / 1000;
        
        // Limitar delay máximo a 60 segundos
        $delay = min($baseDelay + $jitter, 60);
        
        return (int)ceil($delay);
    }
    
    /**
     * Extrai valor do header Retry-After (se presente)
     * 
     * @param string $headers Headers da resposta HTTP
     * @return int|null Segundos para aguardar ou null se não encontrado
     */
    private function extrairRetryAfter($headers)
    {
        if (preg_match('/retry-after:\s*(\d+)/i', $headers, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }
    
    /**
     * Processa resposta da geração de exercícios
     */
    private function processarRespostaExercicio($response)
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \Exception('Resposta inválida da OpenAI');
        }
        
        $rawContent = (string) $response['choices'][0]['message']['content'];
        $content = trim($rawContent);

        // Remove cerca markdown e tenta isolar objeto/array JSON.
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $content, $m) && !empty($m[1])) {
            $content = trim((string) $m[1]);
        }
        $jsonStartObj = strpos($content, '{');
        $jsonEndObj = strrpos($content, '}');
        $jsonStartArr = strpos($content, '[');
        $jsonEndArr = strrpos($content, ']');
        if ($jsonStartObj !== false && $jsonEndObj !== false && $jsonEndObj > $jsonStartObj) {
            $content = substr($content, $jsonStartObj, ($jsonEndObj - $jsonStartObj) + 1);
        } elseif ($jsonStartArr !== false && $jsonEndArr !== false && $jsonEndArr > $jsonStartArr) {
            $content = substr($content, $jsonStartArr, ($jsonEndArr - $jsonStartArr) + 1);
        }

        // Tenta parse em etapas para evitar corromper JSON já válido.
        $candidateBase = (string) $content;
        $candidates = [];
        $candidates[] = $candidateBase;

        // Limpeza leve (sem alterar barras invertidas).
        $candidateSanitized = str_replace(["\xEF\xBB\xBF", "“", "”", "‘", "’"], ['','"','"',"'", "'"], $candidateBase);
        $candidateSanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $candidateSanitized);
        $candidates[] = (string) $candidateSanitized;

        // Remove vírgulas finais comuns.
        $candidateNoTrailingCommas = preg_replace('/,\s*([}\]])/', '$1', (string) $candidateSanitized);
        $candidates[] = (string) $candidateNoTrailingCommas;

        // Escapa apenas barras invertidas soltas (não mexe em "\\", \" etc já válidos).
        $candidateEscapedLoneBackslash = preg_replace('/(?<!\\\\)\\\\(?!["\\\\\/bfnrtu])/', '\\\\\\\\', (string) $candidateNoTrailingCommas);
        $candidates[] = (string) $candidateEscapedLoneBackslash;

        $exercicio = null;
        foreach ($candidates as $candidate) {
            $exercicio = json_decode((string) $candidate, true, 512, JSON_UNESCAPED_UNICODE);
            if (json_last_error() === JSON_ERROR_NONE && $exercicio !== null) {
                $content = $candidate;
                break;
            }
        }
        
        if (json_last_error() === JSON_ERROR_NONE && $exercicio !== null) {
            // Se a resposta tem um array "questoes", retorna ele diretamente
            if (isset($exercicio['questoes']) && is_array($exercicio['questoes'])) {
                $result = ['exercicios' => $exercicio['questoes']];
                if (isset($exercicio['titulo'])) $result['titulo'] = $exercicio['titulo'];
                if (isset($exercicio['instrucoes'])) $result['instrucoes'] = $exercicio['instrucoes'];
                return $result;
            }
            // Se a resposta tem um array "exercicios", retorna ele diretamente
            if (isset($exercicio['exercicios']) && is_array($exercicio['exercicios'])) {
                $result = ['exercicios' => $exercicio['exercicios']];
                if (isset($exercicio['titulo'])) $result['titulo'] = $exercicio['titulo'];
                if (isset($exercicio['instrucoes'])) $result['instrucoes'] = $exercicio['instrucoes'];
                return $result;
            }
            // Se já é um array de exercícios, retorna como está
            if (isset($exercicio[0])) {
                return [
                    'exercicios' => $exercicio
                ];
            }
            // Se é um único exercício, transforma em array
            return [
                'exercicios' => [$exercicio]
            ];
        }

        if (json_last_error() === JSON_ERROR_NONE && $exercicio === null) {
            $rawTrim = trim((string)$rawContent);
            if ($rawTrim === '' || strtolower($rawTrim) === 'null') {
                throw new \Exception('A IA retornou uma resposta vazia. Tente novamente.');
            }
            throw new \Exception('Não foi possível processar o exercício gerado pela IA: resposta sem estrutura JSON válida.');
        }
        
        error_log("Erro ao decodificar JSON: " . json_last_error_msg());
        error_log("Conteúdo recebido: " . substr($content, 0, 500));
        if (class_exists('\Logger')) {
            \Logger::openaiError(
                'Falha ao processar JSON retornado pela OpenAI (geração de exercícios)',
                [
                    'json_error' => json_last_error_msg(),
                    'content_excerpt' => mb_substr((string) $rawContent, 0, 1500),
                    'content_sanitized_excerpt' => mb_substr((string) $content, 0, 1500),
                ]
            );
        }
        throw new \Exception('Não foi possível processar o exercício gerado pela IA: ' . json_last_error_msg());
    }

    /**
     * Processa resposta da geração de prova (formato titulo, instrucoes, questoes, gabarito)
     * Normaliza para o formato esperado pelo ExamController: exercicios com enunciado, alternativas, correta
     */
    private function processarRespostaProvaIA($response)
    {
        $raw = $this->processarRespostaExercicio($response);

        // O novo prompt v3 retorna "questoes" ao invés de "exercicios"
        $exercicios = $raw['exercicios'] ?? $raw['questoes'] ?? [];
        if (empty($exercicios) || !is_array($exercicios)) {
            return $raw;
        }

        $normalizados = [];
        foreach ($exercicios as $ex) {
            $item = $ex;

            // Normalizar campo de resposta correta
            if (isset($ex['resposta_correta']) && !isset($item['correta'])) {
                $item['correta'] = is_string($ex['resposta_correta']) ? trim($ex['resposta_correta']) : $ex['resposta_correta'];
            }

            // Normalizar campo de enunciado
            if (isset($ex['pergunta']) && !isset($item['enunciado'])) {
                $item['enunciado'] = $ex['pergunta'];
            }

            // Preservar metadados de imagem do prompt v3
            if (isset($ex['imagem']) && is_array($ex['imagem'])) {
                $item['imagem'] = $ex['imagem'];
            } elseif (!isset($item['imagem'])) {
                $item['imagem'] = null;
            }

            // Preservar explicação
            if (isset($ex['explicacao'])) {
                $item['explicacao'] = $ex['explicacao'];
            }

            // Normalizar tipo de questão
            if (isset($ex['tipo'])) {
                $tipoMap = [
                    'alternativas' => 'multipla_escolha',
                    'multipla_escolha' => 'multipla_escolha',
                    'verdadeiro_falso' => 'verdadeiro_falso',
                    'dissertativa' => 'dissertativa',
                ];
                $item['tipo_questao'] = $tipoMap[$ex['tipo']] ?? $ex['tipo'];
            }

            $normalizados[] = $item;
        }

        $result = ['exercicios' => $normalizados];

        // Preservar titulo e instrucoes se vierem no JSON
        if (isset($raw['titulo'])) $result['titulo'] = $raw['titulo'];
        if (isset($raw['instrucoes'])) $result['instrucoes'] = $raw['instrucoes'];

        return $result;
    }

    /**
     * Log de INPUT da geração de prova por IA (o que entrou na requisição).
     */
    private function logProvaIAInput($tema, $materia, $quantidade, $nivel, $contextoAdicional, $tipo, $vars, $prompt, $requestData)
    {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/prova_ia.log';
        $ts = date('Y-m-d H:i:s');
        $input = [
            'timestamp' => $ts,
            'params' => [
                'tema' => $tema,
                'materia' => $materia,
                'quantidade' => $quantidade,
                'nivel' => $nivel,
                'contexto_adicional' => $contextoAdicional,
                'tipo' => $tipo,
            ],
            'vars_template' => $vars,
            'prompt_final_user' => $prompt,
            'request_api' => [
                'model' => $requestData['model'] ?? null,
                'temperature' => $requestData['temperature'] ?? null,
                'system_message' => isset($requestData['messages'][0]['content']) ? $requestData['messages'][0]['content'] : null,
            ],
        ];
        $line = "\n" . str_repeat('=', 80) . "\n";
        $line .= "[PROVA IA - INPUT] {$ts}\n";
        $line .= str_repeat('=', 80) . "\n";
        $line .= json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log de OUTPUT da geração de prova por IA (resposta bruta da API).
     */
    private function logProvaIAOutput($response)
    {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/prova_ia.log';
        $ts = date('Y-m-d H:i:s');
        $content = isset($response['choices'][0]['message']['content'])
            ? $response['choices'][0]['message']['content']
            : (is_string($response) ? $response : json_encode($response));
        $output = [
            'timestamp' => $ts,
            'raw_content' => $content,
            'full_response_keys' => is_array($response) ? array_keys($response) : [],
            'usage' => $response['usage'] ?? null,
        ];
        $line = "\n" . str_repeat('-', 80) . "\n";
        $line .= "[PROVA IA - OUTPUT] {$ts}\n";
        $line .= str_repeat('-', 80) . "\n";
        $line .= json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Processa resposta da comparação de resumos
     */
    private function processarRespostaComparacao($response)
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \Exception('Resposta inválida da OpenAI');
        }
        
        $content = $response['choices'][0]['message']['content'];
        
        // Tenta extrair JSON da resposta
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}') + 1;
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonContent = substr($content, $jsonStart, $jsonEnd - $jsonStart);
            $comparacao = json_decode($jsonContent, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $comparacao;
            }
        }
        
        throw new \Exception('Não foi possível processar a comparação gerada pela IA');
    }
    
    /**
     * Processa resposta da explicação complementar
     */
    private function processarRespostaExplicacao($response)
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \Exception('Resposta inválida da OpenAI');
        }
        
        return $response['choices'][0]['message']['content'];
    }
    
    /**
     * Gera texto usando IA (método genérico)
     * 
     * @param string $prompt Prompt para a IA
     * @param array $options Opções adicionais (max_tokens, temperature, etc.)
     * @return string Resposta da IA
     */
    public function generateText($prompt, $options = [])
    {
        try {
            // Verificar se a chave da API está configurada
            if (empty($this->apiKey) || $this->apiKey === 'sk-test-key-not-configured') {
                return $this->gerarRespostaSimulada($prompt);
            }
            
            // Configurar parâmetros padrão (model can be overridden via options for flashcards, etc.)
            $defaultOptions = [
                'model' => 'gpt-4o',
                'max_tokens' => 4000,  // Limite padrão para respostas completas
                'temperature' => 0.7
            ];
            
            // Mesclar opções fornecidas com padrões
            $config = array_merge($defaultOptions, $options);
            
            $response = $this->fazerRequisicao([
                'model' => $config['model'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um assistente especializado em educação brasileira. Sempre retorne respostas em formato JSON válido quando solicitado.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => $config['max_tokens'],
                'temperature' => $config['temperature']
            ], 3, 0, 'gerar_tema');
            
            if (!isset($response['choices'][0]['message']['content'])) {
                throw new \Exception('Resposta inválida da OpenAI');
            }
            
            $content = $response['choices'][0]['message']['content'];
            
            // Tentar extrair JSON da resposta se estiver em markdown
            if (strpos($content, '```json') !== false) {
                $jsonStart = strpos($content, '```json') + 7;
                $jsonEnd = strpos($content, '```', $jsonStart);
                if ($jsonEnd !== false) {
                    $content = substr($content, $jsonStart, $jsonEnd - $jsonStart);
                }
            }
            
            return $content;
            
        } catch (\Exception $e) {
            // Logar erro no sistema de logs
            if (!class_exists('\Logger')) {
                require_once __DIR__ . '/../Core/Logger.php';
            }
            \Logger::openaiError(
                "Erro em generateText: " . $e->getMessage(),
                [
                    'exception' => $e,
                    'prompt_length' => strlen($prompt),
                    'options' => $options
                ]
            );
            
            error_log("Erro em OpenAIService::generateText: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            // Em caso de erro, retornar resposta simulada
            return $this->gerarRespostaSimulada($prompt);
        }
    }
    
    /**
     * Gera resposta simulada para teste
     */
    private function gerarRespostaSimulada($prompt)
    {
        // Tentar buscar prompt personalizado do banco de dados
        try {
            // Remover namespace para usar classes globais
            $dbClass = 'Database';
            if (class_exists($dbClass)) {
                $db = $dbClass::getInstance();
                
                // Verificar se é prompt de tema ou correção
                if (strpos($prompt, 'themeRequest') !== false || strpos($prompt, "tema sobre:") !== false) {
                    // Buscar prompt de gerar tema
                    $promptConfig = $db->fetch(
                        "SELECT config_value FROM config_layout WHERE config_key = ?",
                        ['prompt_gerar_tema_redacao']
                    );
                } else if (strpos($prompt, 'competencia') !== false || strpos($prompt, 'COMPETÊNCIA') !== false) {
                    // Buscar prompt de correção
                    $promptConfig = $db->fetch(
                        "SELECT config_value FROM config_layout WHERE config_key = ?",
                        ['prompt_corrigir_redacao']
                    );
                } else {
                    $promptConfig = null;
                }
                
                // Se encontrou prompt personalizado, usar estrutura similar
                if ($promptConfig && !empty($promptConfig['config_value'])) {
                    // Extrair o tema solicitado do prompt
                    if (preg_match("/'([^']+)'/", $prompt, $matches)) {
                        $tema = $matches[1];
                    } else {
                        $tema = "tema solicitado";
                    }
                    
                    // Retornar estrutura mínima simulada
                    return json_encode([
                        'titulo' => "Redação sobre {$tema}",
                        'descricao' => "Desenvolva uma redação dissertativa-argumentativa sobre {$tema}, considerando a realidade brasileira atual.",
                        'proposta_intervencao' => 'Desenvolva uma proposta de intervenção social que respeite os direitos humanos.',
                        'contexto' => 'Considere a realidade brasileira atual ao desenvolver sua argumentação.'
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Em caso de erro, continuar com fallback padrão
        }
        
        // Fallback: extrair o tema solicitado do prompt
        if (preg_match("/'([^']+)'/", $prompt, $matches)) {
            $tema = $matches[1];
        } else {
            $tema = "tema solicitado";
        }
        
        return json_encode([
            'titulo' => "Redação sobre {$tema}",
            'descricao' => "Desenvolva uma redação dissertativa-argumentativa sobre {$tema}, considerando a realidade brasileira atual. Apresente argumentos consistentes e uma proposta de intervenção social.",
            'proposta_intervencao' => 'Desenvolva uma proposta de intervenção social que respeite os direitos humanos e seja viável na realidade brasileira.',
            'contexto' => 'Considere a realidade brasileira atual ao desenvolver sua argumentação, apresentando dados e exemplos relevantes.'
        ]);
    }
    
    /**
     * Retorna URL e API key do Supabase Transcribe (variáveis de ambiente).
     *
     * @return array{url: string, api_key: string}|null null se não configurado
     */
    private function getSupabaseTranscribeConfig()
    {
        $apiKey = getenv('SUPABASE_TRANSCRIBE_API_KEY');
        if ($apiKey === false || $apiKey === '') {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $content = @file_get_contents($envPath);
                if ($content !== false && preg_match('/SUPABASE_TRANSCRIBE_API_KEY\s*=\s*["\']?([^"\s\r\n]+)/', $content, $m)) {
                    $apiKey = trim($m[1], "\"'");
                }
            }
        }
        if (empty($apiKey)) {
            return null;
        }
        $url = getenv('SUPABASE_TRANSCRIBE_URL');
        if ($url === false || $url === '') {
            $url = 'https://bgumiziiyqfhinlfxwrf.supabase.co/functions/v1/api-transcribe';
        }
        return ['url' => $url, 'api_key' => $apiKey];
    }

    /**
     * Transcrição via API Supabase (Edge Function api-transcribe).
     *
     * @param string $imageBase64 Conteúdo em base64 (pode ter prefixo data:image/...;base64,)
     * @param string|null $mimeType Ex.: image/jpeg, image/png. Se null, infere do prefixo ou usa image/jpeg
     * @return string Texto transcrito
     */
    public function transcreverComSupabase($imageBase64, $mimeType = null)
    {
        $config = $this->getSupabaseTranscribeConfig();
        if ($config === null) {
            throw new \Exception('SUPABASE_TRANSCRIBE_API_KEY não configurada.');
        }
        $raw = preg_replace('/^data:image\/\w+;base64,/', '', $imageBase64);
        if ($raw === '') {
            throw new \Exception('Imagem base64 inválida.');
        }
        if ($mimeType === null || $mimeType === '') {
            if (preg_match('/^data:(image\/\w+);base64,/', $imageBase64, $m)) {
                $mimeType = $m[1];
            } else {
                $mimeType = 'image/jpeg';
            }
        }
        $payload = json_encode([
            'image_base64' => $raw,
            'mime_type' => $mimeType,
        ]);
        $ch = curl_init($config['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $config['api_key'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 120,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            throw new \Exception('Erro ao chamar API de transcrição: ' . $err);
        }
        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            $msg = isset($data['error']) ? $data['error'] : ($response ?: "HTTP {$httpCode}");
            throw new \Exception('Transcrição Supabase: ' . $msg);
        }
        if (!isset($data['text']) || !is_string($data['text'])) {
            throw new \Exception('Resposta da API de transcrição sem campo "text".');
        }
        return trim($data['text']);
    }

    /**
     * Etapa 1: Transcrição com Google Cloud Vision (OCR Puro)
     * 
     * @param string $imageData Dados base64 da imagem
     * @param bool $returnEmptyIfNoText Se true, retorna '' quando a imagem não tiver texto (em vez de lançar exceção)
     * @return string Texto bruto extraído da imagem
     */
    public function transcreverComGoogleVision($imageData, $returnEmptyIfNoText = false)
    {
        $logFile = __DIR__ . '/../../storage/logs/transcricao_' . date('Y-m-d') . '.log';
        $log = function($message) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] {$message}\n";
            error_log($logMessage, 3, $logFile);
            error_log($logMessage);
        };
        
        try {
            $log("transcreverComGoogleVision: Iniciando");
            $log("transcreverComGoogleVision: Tamanho da imagem base64: " . strlen($imageData) . " caracteres");
            
            // Verificar se está em ambiente de desenvolvimento
            $isDevelopment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                             strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                             (defined('ENVIRONMENT') && ENVIRONMENT === 'development'));
            
            $log("transcreverComGoogleVision: Ambiente de desenvolvimento: " . ($isDevelopment ? 'SIM' : 'NÃO'));
            
            // Em desenvolvimento, configurar variáveis de ambiente para SSL (se putenv não estiver desabilitado)
            if ($isDevelopment && function_exists('putenv')) {
                $log("transcreverComGoogleVision: Configurando SSL para desenvolvimento...");
                try {
                    \putenv('CURLOPT_SSL_VERIFYPEER=0');
                    \putenv('CURLOPT_SSL_VERIFYHOST=0');
                    $log("transcreverComGoogleVision: Variáveis de ambiente configuradas");
                } catch (\Exception $e) {
                    $log("transcreverComGoogleVision: Aviso ao configurar SSL: " . $e->getMessage());
                }
            }
            
            // Forçar carregamento do autoloader do Composer
            $log("transcreverComGoogleVision: Verificando autoloader do Composer...");
            $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
            $log("transcreverComGoogleVision: Caminho do autoload: {$autoloadPath}");
            $log("transcreverComGoogleVision: Autoload existe: " . (file_exists($autoloadPath) ? 'SIM' : 'NÃO'));
            $log("transcreverComGoogleVision: Classe ImageAnnotatorClient existe: " . (class_exists('Google\Cloud\Vision\V1\Client\ImageAnnotatorClient') ? 'SIM' : 'NÃO'));
            
            if (file_exists($autoloadPath) && !class_exists('Google\Cloud\Vision\V1\Client\ImageAnnotatorClient')) {
                $log("transcreverComGoogleVision: Carregando autoloader...");
                require_once $autoloadPath;
                $log("transcreverComGoogleVision: Autoloader carregado");
                $log("transcreverComGoogleVision: Classe ImageAnnotatorClient existe após autoload: " . (class_exists('Google\Cloud\Vision\V1\Client\ImageAnnotatorClient') ? 'SIM' : 'NÃO'));
            }
            
            // Se ainda não funcionou, tentar carregar manualmente
            if (!class_exists('Google\Cloud\Vision\V1\Client\ImageAnnotatorClient')) {
                $log("transcreverComGoogleVision: CRITICAL - Tentando carregar Google Vision classes manualmente");
                error_log("CRITICAL: Tentando carregar Google Vision classes manualmente");
                
                // Carregar TODOS os arquivos necessários de uma vez
                $baseDir = __DIR__ . '/../../vendor';
                $log("transcreverComGoogleVision: Base dir: {$baseDir}");
                
                // Load ApiCore (necessário para traits)
                $gaxPath = $baseDir . '/google/gax/src';
                $log("transcreverComGoogleVision: Carregando GAX de: {$gaxPath}");
                if (is_dir($gaxPath)) {
                    $gaxFiles = glob($gaxPath . '/**/*.php');
                    $log("transcreverComGoogleVision: Encontrados " . count($gaxFiles) . " arquivos GAX");
                    foreach ($gaxFiles as $file) {
                        if (file_exists($file)) {
                            require_once $file;
                        }
                    }
                } else {
                    $log("transcreverComGoogleVision: ERRO - Diretório GAX não existe!");
                }
                
                // Load Cloud Vision
                $visionPath = $baseDir . '/google/cloud-vision/src';
                $log("transcreverComGoogleVision: Carregando Cloud Vision de: {$visionPath}");
                if (is_dir($visionPath)) {
                    $visionFiles = glob($visionPath . '/**/*.php');
                    $log("transcreverComGoogleVision: Encontrados " . count($visionFiles) . " arquivos Cloud Vision");
                    foreach ($visionFiles as $file) {
                        if (file_exists($file)) {
                            require_once $file;
                        }
                    }
                } else {
                    $log("transcreverComGoogleVision: ERRO - Diretório Cloud Vision não existe!");
                }
                
                $log("transcreverComGoogleVision: Classe ImageAnnotatorClient existe após carregamento manual: " . (class_exists('Google\Cloud\Vision\V1\Client\ImageAnnotatorClient') ? 'SIM' : 'NÃO'));
            } else {
                $log("transcreverComGoogleVision: Classe ImageAnnotatorClient já disponível");
            }
            
            // Definir o caminho das credenciais
            $credentialsPath = $this->visionCredentialsPath;
            $log("transcreverComGoogleVision: Caminho das credenciais: {$credentialsPath}");
            
            // Verificar se o arquivo de credenciais existe
            if (!file_exists($credentialsPath)) {
                $log("transcreverComGoogleVision: ERRO - Arquivo de credenciais não encontrado!");
                throw new \Exception("Arquivo de credenciais do Google Vision não encontrado: {$credentialsPath}");
            }
            
            $log("transcreverComGoogleVision: Arquivo de credenciais encontrado");
            
            // Configurar o cliente com as credenciais
            $clientOptions = ['credentials' => $credentialsPath];
            
            // Em desenvolvimento, tentar configurar transport customizado
            if ($isDevelopment) {
                $log("transcreverComGoogleVision: Configurando transport customizado para desenvolvimento...");
                try {
                    // Verificar se GuzzleHttp\Promise está disponível
                    if (!class_exists('\GuzzleHttp\Promise\Promise')) {
                        $log("transcreverComGoogleVision: GuzzleHttp\Promise não disponível, tentando carregar...");
                        $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
                        if (file_exists($autoloadPath)) {
                            require_once $autoloadPath;
                        }
                    }
                    
                    // Criar handler HTTP customizado que retorna Promise
                    // O Google GAX espera uma Promise, não uma Response direta
                    $httpHandler = new class {
                        public function __invoke($request, $options = []) {
                            $client = new \GuzzleHttp\Client([
                                'verify' => false,
                                'curl' => [
                                    CURLOPT_SSL_VERIFYPEER => false,
                                    CURLOPT_SSL_VERIFYHOST => false,
                                    CURLOPT_TIMEOUT => 120,
                                    CURLOPT_CONNECTTIMEOUT => 30
                                ]
                            ]);
                            
                            // sendAsync retorna uma Promise, que é o que o Google GAX espera
                            return $client->sendAsync($request, $options);
                        }
                    };
                    
                    // Configurar transport
                    $clientOptions['transport'] = 'rest';
                    $clientOptions['transportConfig'] = [
                        'rest' => [
                            'httpHandler' => $httpHandler
                        ]
                    ];
                    $log("transcreverComGoogleVision: Transport customizado configurado");
                } catch (\Exception $e) {
                    $log("transcreverComGoogleVision: Aviso ao configurar transport: " . $e->getMessage());
                    error_log("Aviso ao configurar transport: " . $e->getMessage());
                    // Continuar sem handler customizado
                }
            }
            
            $log("transcreverComGoogleVision: Criando ImageAnnotatorClient...");
            $log("transcreverComGoogleVision: Opções do cliente: " . json_encode(array_keys($clientOptions)));
            
            // Aumentar timeout do PHP antes de criar o cliente
            set_time_limit(300);
            ini_set('max_execution_time', 300);
            
            try {
                $inicioCliente = microtime(true);
                $imageAnnotator = new ImageAnnotatorClient($clientOptions);
                $tempoCliente = microtime(true) - $inicioCliente;
                $log("transcreverComGoogleVision: ImageAnnotatorClient criado com sucesso em " . round($tempoCliente, 2) . " segundos");
            } catch (\Exception $e) {
                $log("transcreverComGoogleVision: ERRO ao criar ImageAnnotatorClient: " . $e->getMessage());
                $log("transcreverComGoogleVision: Stack trace: " . $e->getTraceAsString());
                throw $e;
            }

            // Decodificar a imagem base64 para bytes
            $log("transcreverComGoogleVision: Decodificando imagem base64...");
            $imageBytes = base64_decode($imageData);
            
            if ($imageBytes === false) {
                $log("transcreverComGoogleVision: ERRO - Falha ao decodificar base64");
                throw new \Exception('Falha ao decodificar imagem base64');
            }
            
            $log("transcreverComGoogleVision: Imagem decodificada - Tamanho: " . strlen($imageBytes) . " bytes");

            // Criar request
            $log("transcreverComGoogleVision: Criando request para Google Vision...");
            $image = new Image();
            $image->setContent($imageBytes);
            
            $feature = new Feature();
            $feature->setType(Type::DOCUMENT_TEXT_DETECTION);
            
            $request = new AnnotateImageRequest();
            $request->setImage($image);
            $request->setFeatures([$feature]);
            
            $batchRequest = new BatchAnnotateImagesRequest();
            $batchRequest->setRequests([$request]);
            
            $log("transcreverComGoogleVision: Enviando requisição para Google Vision API...");
            $inicioRequisicao = microtime(true);
            $response = $imageAnnotator->batchAnnotateImages($batchRequest);
            $tempoRequisicao = microtime(true) - $inicioRequisicao;
            $log("transcreverComGoogleVision: Resposta recebida em " . round($tempoRequisicao, 2) . " segundos");
            
            // Obter a transcrição completa
            $log("transcreverComGoogleVision: Processando resposta...");
            $responses = $response->getResponses();
            if (empty($responses)) {
                $log("transcreverComGoogleVision: ERRO - Nenhuma resposta retornada");
                throw new \Exception('Google Vision não retornou respostas.');
            }
            
            $log("transcreverComGoogleVision: " . count($responses) . " resposta(s) recebida(s)");
            $fullTextAnnotation = $responses[0]->getFullTextAnnotation();
            
            if (!$fullTextAnnotation) {
                $log("transcreverComGoogleVision: FullTextAnnotation vazio (imagem sem texto ou ilegível)");
                if ($returnEmptyIfNoText) {
                    $imageAnnotator->close();
                    return '';
                }
                throw new \Exception('Google Vision não retornou texto. A imagem pode estar vazia ou ilegível.');
            }

            $fullText = $fullTextAnnotation->getText();
            $log("transcreverComGoogleVision: Texto extraído - " . strlen($fullText) . " caracteres");

            // Fechar o cliente
            $imageAnnotator->close();
            $log("transcreverComGoogleVision: Cliente fechado");

            if (empty($fullText)) {
                $log("transcreverComGoogleVision: Texto vazio");
                if ($returnEmptyIfNoText) {
                    return '';
                }
                throw new \Exception('Google Vision não retornou texto.');
            }

            $log("transcreverComGoogleVision: Sucesso! Retornando " . strlen($fullText) . " caracteres");
            error_log("Google Vision retornou " . strlen($fullText) . " caracteres");
            
            return $fullText;

        } catch (\Exception $e) {
            $mensagemErro = $e->getMessage();
            $mensagemLower = strtolower($mensagemErro);
            
            // Identificar tipo de erro específico do Google Vision
            $tipoErro = 'geral';
            $mensagemUsuario = $mensagemErro;
            
            // Verificar erros de credenciais
            if (strpos($mensagemLower, 'credentials') !== false || 
                strpos($mensagemLower, 'authentication') !== false ||
                strpos($mensagemLower, 'unauthorized') !== false ||
                strpos($mensagemLower, 'permission denied') !== false) {
                $tipoErro = 'credenciais';
                $mensagemUsuario = 'Erro de autenticação com Google Vision. As credenciais podem estar inválidas ou expiradas.';
            }
            // Verificar erros de quota/billing
            elseif (strpos($mensagemLower, 'quota') !== false || 
                    strpos($mensagemLower, 'billing') !== false ||
                    strpos($mensagemLower, 'payment') !== false ||
                    strpos($mensagemLower, 'credit') !== false ||
                    strpos($mensagemLower, 'limit exceeded') !== false ||
                    strpos($mensagemLower, 'resource exhausted') !== false) {
                $tipoErro = 'quota';
                $mensagemUsuario = 'A cota de uso do Google Vision foi excedida ou o limite de pagamento foi atingido. Verifique sua conta Google Cloud.';
            }
            // Verificar erros de tamanho de imagem
            elseif (strpos($mensagemLower, 'image size') !== false || 
                    strpos($mensagemLower, 'too large') !== false ||
                    strpos($mensagemLower, 'max size') !== false) {
                $tipoErro = 'imagem_grande';
                $mensagemUsuario = 'A imagem é muito grande para processamento. Tente com uma imagem menor (máximo 20MB).';
            }
            // Verificar erros de formato
            elseif (strpos($mensagemLower, 'invalid image') !== false || 
                    strpos($mensagemLower, 'unsupported format') !== false ||
                    strpos($mensagemLower, 'image format') !== false) {
                $tipoErro = 'formato_invalido';
                $mensagemUsuario = 'Formato de imagem inválido. Use PNG, JPG ou JPEG.';
            }
            // Verificar erros de timeout/conexão
            elseif (strpos($mensagemLower, 'timeout') !== false || 
                    strpos($mensagemLower, 'connection') !== false ||
                    strpos($mensagemLower, 'network') !== false ||
                    strpos($mensagemLower, 'deadline exceeded') !== false) {
                $tipoErro = 'timeout';
                $mensagemUsuario = 'Tempo de processamento expirado. Tente novamente com uma imagem menor ou mais clara.';
            }
            // Verificar erros de API do Google (500, 503, etc)
            elseif (strpos($mensagemLower, 'internal error') !== false || 
                    strpos($mensagemLower, 'service unavailable') !== false ||
                    strpos($mensagemLower, '500') !== false ||
                    strpos($mensagemLower, '503') !== false) {
                $tipoErro = 'erro_servidor_google';
                $mensagemUsuario = 'Erro temporário no serviço Google Vision. Tente novamente em alguns instantes.';
            }
            // Verificar se arquivo de credenciais não existe
            elseif (strpos($mensagemLower, 'not found') !== false || 
                    strpos($mensagemLower, 'file not found') !== false) {
                $tipoErro = 'credenciais_ausentes';
                $mensagemUsuario = 'Arquivo de credenciais do Google Vision não encontrado. Contate o administrador.';
            }
            
            // Log detalhado
            error_log("Erro em transcreverComGoogleVision [Tipo: {$tipoErro}]: " . $mensagemErro);
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            
            // Criar exceção com mensagem melhorada
            $excecaoMelhorada = new \Exception($mensagemUsuario, $e->getCode(), $e);
            // Adicionar tipo de erro como propriedade (via reflection ou criar classe customizada)
            // Por enquanto, vamos incluir na mensagem
            throw new \Exception($mensagemUsuario . " [Tipo: {$tipoErro}]", $e->getCode(), $e);
        }
    }

    /**
     * ETAPA 2 (determinística): Limpeza e formatação do texto OCR sem alterar palavras.
     * Apenas regex/manipulação de string. Não usa modelo de linguagem.
     * Permite: juntar hífen no fim da linha, remover quebras no meio de frases, padronizar espaços, organizar parágrafos.
     *
     * @param string $textoOriginal Texto bruto do OCR (saída da etapa 1)
     * @return string Texto formatado (conteúdo lexical preservado)
     */
    public function formatOCRText($textoOriginal)
    {
        $t = $textoOriginal;
        if ($t === '') {
            return '';
        }
        // 0) Remover linhas que são apenas números (artefato comum de OCR: página, linha)
        $t = preg_replace('/^\d+\s*$/m', '', $t);
        // 1) Junção de hífen no fim da linha: "palavra-\nmento" -> "palavramento" (remove hífen e quebra)
        $t = preg_replace('/-\s*\n\s*/u', '', $t);
        // 2) Quebra no meio de frase: linha termina com letra minúscula, próxima começa com letra -> une com espaço
        $t = preg_replace('/([a-záéíóúàèìòùãõâêîôûç])\s*\n\s*([a-zA-Záéíóúàèìòùãõâêîôûç])/u', '$1 $2', $t);
        // 3) Múltiplos espaços ou tabs -> um espaço
        $t = preg_replace('/[ \t]+/u', ' ', $t);
        // 4) Múltiplas quebras de linha -> no máximo duas (parágrafo)
        $t = preg_replace('/\n{3,}/u', "\n\n", $t);
        $t = trim($t);
        // 5) Recuo de 4 espaços no início de cada parágrafo (após \n\n)
        $t = preg_replace('/\n\n([^\n])/u', "\n\n    $1", $t);
        if (strlen($t) > 0 && $t[0] !== ' ') {
            $t = '    ' . $t;
        }
        return $t;
    }

    /**
     * Remove o cabeçalho típico da redação (NOME:, REDAÇÃO X DA QUESTÃO, TEMA:, ANO:, NOTA:, nome do aluno, etc.)
     * para retornar apenas o corpo do texto. Conservador: só remove do início até a primeira linha que pareça início de parágrafo.
     *
     * @param string $texto Texto formatado (pode ter recuos)
     * @return string Texto sem o bloco de cabeçalho
     */
    public function removerCabecalhoRedacao($texto)
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }
        $linhas = preg_split('/\r\n|\n|\r/u', $texto);
        $headerLabels = '/^\s*(NOME|TEMA|ANO|NOTA)\s*:\s*$/ui';
        $redacaoQuestao = '/^\s*REDAÇÃO\s+.*QUESTÃO\s*$/ui';
        $limiteLinhas = 20;
        $minCharsCorpo = 50;
        $i = 0;
        $n = count($linhas);
        while ($i < $n) {
            $linha = $linhas[$i];
            $trimmed = trim($linha);
            if ($trimmed === '') {
                $i++;
                continue;
            }
            if (preg_match($headerLabels, $trimmed) || preg_match($redacaoQuestao, $trimmed)) {
                $i++;
                continue;
            }
            if ($i < $limiteLinhas && mb_strlen($trimmed) < $minCharsCorpo) {
                $i++;
                continue;
            }
            break;
        }
        $resto = array_slice($linhas, $i);
        return trim(implode("\n", $resto));
    }

    /**
     * Etapa 2 (legado): Pós-processamento com GPT-4o. Preferir formatOCRText() para transcrição literal.
     *
     * @param string $textoBruto Texto vindo do Google Vision
     * @return string Texto final formatado
     */
    public function posProcessarTexto($textoBruto)
    {
        try {
            $systemPrompt = $this->getPromptConfig('prompt_ocr_formatacao');
            if ($systemPrompt === '') {
                $systemPrompt = 'Você é um assistente de formatação de texto. Sua única tarefa é pegar um texto bruto de OCR e formatá-lo como uma redação dissertativa.

<REGRAS_DE_FORMATAÇÃO>
1. **Cabeçalho:** NÃO inclua no resultado o cabeçalho da folha (NOME:, REDAÇÃO X DA QUESTÃO, nome do aluno, TEMA:, ANO:, NOTA:, etc.). Retorne APENAS o corpo do texto da redação.
2. **Junção de Hífen:** Junte palavras separadas por hífen no fim da linha (ex: "questiona-" e "mento" vira "questionamento").
3. **Parágrafos:** Identifique e estruture o texto em parágrafos corretos. Use um recuo de 4 espaços no início de cada parágrafo.
4. **Limpeza:** Remova quaisquer artefatos de OCR estranhos ou quebras de linha desnecessárias no meio das frases.
5. **Literalidade:** NÃO CORRIJA erros de ortografia ou gramática do aluno. Mantenha o texto original, apenas formate-o.
6. **Palavras duvidosas:** Se alguma palavra no texto bruto estiver ilegível ou você não tiver certeza do que está escrito, substitua APENAS essa palavra por [Não Entendi]. Não invente palavras. Não use contexto para adivinhar.
</REGRAS_DE_FORMATAÇÃO>';
            }

            // User prompt com o texto bruto
            $userPrompt = 'Formate o seguinte texto bruto de OCR, seguindo todas as regras de formatação:

--- TEXTO BRUTO ---
' . $textoBruto . '
--- FIM DO TEXTO BRUTO ---';

            // Fazer requisição para OpenAI
            $response = $this->fazerRequisicao([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt
                    ]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.1 // Temperatura baixa para formatação precisa
            ], 3, 0, 'ocr_formatacao');
            
            if (!isset($response['choices'][0]['message']['content'])) {
                throw new \Exception('Resposta inválida da OpenAI no pós-processamento');
            }
            
            error_log("GPT-4o retornou texto formatado com " . strlen($response['choices'][0]['message']['content']) . " caracteres");
            
            return $response['choices'][0]['message']['content'];

        } catch (\Exception $e) {
            // Logar erro no sistema de logs
            if (!class_exists('\Logger')) {
                require_once __DIR__ . '/../Core/Logger.php';
            }
            \Logger::openaiError(
                "Erro em posProcessarTexto: " . $e->getMessage(),
                [
                    'exception' => $e,
                    'texto_bruto_length' => strlen($textoBruto)
                ]
            );
            
            error_log("Erro em posProcessarTexto: " . $e->getMessage());
            
            // Se a quota foi excedida, retornar texto bruto formatado manualmente
            if (strpos($e->getMessage(), 'quota') !== false || strpos($e->getMessage(), 'Quota') !== false) {
                \Logger::warning("Quota excedida - usando formatação manual como fallback", [], 'openai');
                error_log("Quota excedida - retornando texto bruto formatado manualmente");
                // Formatação básica manual como fallback
                $textoFormatado = $textoBruto;
                // Remove quebras de linha múltiplas
                $textoFormatado = preg_replace('/\n{3,}/', "\n\n", $textoFormatado);
                // Adiciona recuo de 4 espaços no início de parágrafos
                $textoFormatado = preg_replace('/\n([^\n])/', "\n    $1", $textoFormatado);
                return $textoFormatado;
            }
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                error_log("Stack trace: " . $e->getTraceAsString());
            
            }
            throw $e;
        }
    }

    /**
     * Gera exercícios personalizados baseado em tema e matéria
     * 
     * @param string $tema Tema dos exercícios (ex: "Reforma Protestante")
     * @param string $materia Matéria (ex: "História")
     * @param int $quantidade Quantidade de exercícios
     * @param array $niveis Array de níveis (['Fácil', 'Médio', 'Difícil'])
     * @param string $contextoAdicional Contexto adicional (ex: conteúdo do plano de aula)
     * @return array Array de exercícios gerados
     */
    public function gerarExerciciosPersonalizados($tema, $materia, $quantidade, $niveis, $contextoAdicional = '', array $detalhesPrompt = [])
    {
        try {
            // Verificar se a API key está configurada
            if (empty($this->apiKey) || $this->apiKey === 'sk-test-key-not-configured') {
                error_log("OpenAI API Key não configurada corretamente");
                throw new \Exception('Chave da API não configurada');
            }
            
            $niveisValidos = array_values(array_filter($niveis, function ($nivel) {
                return in_array($nivel, ['Fácil', 'Médio', 'Difícil'], true);
            }));
            if (empty($niveisValidos)) {
                $niveisValidos = ['Médio'];
            }
            $niveisStr = implode(', ', $niveisValidos);

            $distribuicaoNiveis = is_array($detalhesPrompt['distribuicao_niveis'] ?? null)
                ? $detalhesPrompt['distribuicao_niveis']
                : [];

            $linhasDistribuicao = [];
            foreach (['Fácil', 'Médio', 'Difícil'] as $nivelBase) {
                $qtdNivel = max(0, (int)($distribuicaoNiveis[$nivelBase] ?? 0));
                if ($qtdNivel > 0) {
                    $linhasDistribuicao[] = "- {$nivelBase}: {$qtdNivel} questão(ões)";
                }
            }
            if (empty($linhasDistribuicao)) {
                $linhasDistribuicao[] = "- Médio: {$quantidade} questão(ões)";
            }
            $blocoDistribuicao = implode("\n", $linhasDistribuicao);

            $exercicios = [];
            foreach (['Fácil', 'Médio', 'Difícil'] as $nivel) {
                $qtdNivel = max(0, (int)($distribuicaoNiveis[$nivel] ?? 0));
                if ($qtdNivel <= 0) {
                    continue;
                }

                $jsonNivel = $this->gerarExerciciosPersonalizadosPorNivel(
                    $tema,
                    $materia,
                    $qtdNivel,
                    $nivel,
                    $contextoAdicional
                );

                if (isset($jsonNivel['exercicios']) && is_array($jsonNivel['exercicios'])) {
                    $lote = $jsonNivel['exercicios'];
                } elseif (isset($jsonNivel['questoes']) && is_array($jsonNivel['questoes'])) {
                    $lote = $this->normalizarQuestoesParaExercicios($jsonNivel['questoes']);
                } else {
                    throw new \Exception("Formato inválido ao gerar lote {$nivel}");
                }

                foreach ($lote as &$item) {
                    $item['nivel'] = $nivel;
                }
                unset($item);
                $exercicios = array_merge($exercicios, $lote);
            }

            // Última garantia de distribuição/ordem conforme solicitado.
            $exercicios = $this->aplicarDistribuicaoNiveisSolicitada($exercicios, $distribuicaoNiveis);
            
            error_log("JSON decodificado com sucesso. Total de exercícios: " . count($exercicios));
            
            return ['exercicios' => $exercicios];
            
        } catch (\Exception $e) {
            error_log("ERRO ao gerar exercícios personalizados: " . $e->getMessage());
            throw $e;
        }
    }

    private function getSystemPromptGeracaoQuestoesPersonalizadas(): string
    {
        return 'Você é um professor conteudista especialista em avaliação educacional de alto nível.

Siga estritamente as instruções do prompt do usuário.
Regras fixas:
- Produza apenas questão de múltipla escolha com alternativas A, B, C, D e E.
- Tenha apenas uma alternativa correta.
- Mantenha rigor conceitual e objetividade.
- A resposta correta deve ser absoluta e sem ambiguidade.
- Retorne SOMENTE JSON válido, sem markdown e sem texto fora do JSON.';
    }

    private function getNivelInstrucaoDocx(string $nivel): string
    {
        if ($nivel === 'Fácil') {
            return "• FÁCIL: Exige memória e compreensão básica. Pode ter alternativas curtas (uma ou duas palavras). 1 etapa de raciocínio.";
        }
        if ($nivel === 'Médio') {
            return "• MÉDIA: Exige aplicação. O aluno precisa conectar dois conceitos. Proibido alternativas de uma única palavra. As alternativas devem ser frases explicativas.";
        }
        if ($nivel === 'Difícil') {
            return "• DIFÍCIL (ATENÇÃO ESPECIAL): Exige síntese e previsão. PROIBIDO alternativas de uma única palavra. O enunciado DEVE apresentar um Cenário de Falha (toxina, mutação, doença específica ou experimento de laboratório que bloqueou um processo). O aluno deve deduzir o impacto sistêmico disso. Exige 3 etapas de raciocínio.";
        }
        return "• MÉDIA: Exige aplicação. O aluno precisa conectar dois conceitos. Proibido alternativas de uma única palavra. As alternativas devem ser frases explicativas.";
    }

    private function getUserPromptGeracaoQuestoesDocx(string $tema, string $materia, int $quantidade, string $nivel, string $contextoAdicional = ''): string
    {
        $nivelInstr = $this->getNivelInstrucaoDocx($nivel);
        $subtema = $tema;
        $microtema = $tema;
        $habilidade = "Aplicar conceitos de {$tema} em contextos de {$materia}";
        $ano = "Ensino Médio";

        $prompt = "Você é um professor conteudista especialista em avaliação educacional de alto nível.
Sua tarefa é criar EXATAMENTE {$quantidade} questão(ões) de múltipla escolha (A a E) de {$materia}, tema \"{$tema}\", subtema \"{$subtema}\", microtema \"{$microtema}\", focando na habilidade \"{$habilidade}\", para o {$ano}.

Nível de dificuldade exigido: {$nivel}
{$nivelInstr}

### REGRAS CRÍTICAS DE DIVERSIDADE (ANTI-VÍCIO):
1. RANDOMIZAÇÃO DO GABARITO: A resposta correta DEVE ser distribuída de forma imprevisível entre as letras A, B, C, D e E. É ESTRITAMENTE PROIBIDO gerar o gabarito sempre na letra A.
2. FUJA DO ÓBVIO: Explore toda a amplitude do tema. Não se limite aos exemplos mais comuns, a menos que o microtema exija foco específico.

### DIRETRIZES DE DIFICULDADE E COGNIÇÃO:
Adapte a estrutura da questão estritamente ao nível de dificuldade exigido:
{$nivelInstr}

### REGRA CRÍTICA DE ESTRUTURA E OBJETIVIDADE (ZERO SUBJETIVIDADE):
1. Template do Enunciado: [Contextualização Breve e Real/Cenário de Falha] -> [Dados Específicos] -> [Restrição] -> [Comando Direto e Conclusivo].
2. O Comando Direto DEVE exigir uma resposta única e objetiva. Proibido perguntas indiretas ou subjetivas.
3. A resposta correta deve ser ABSOLUTA e irrefutável cientificamente/teoricamente, sem margem para duplas interpretações.

### ENGENHARIA DAS ALTERNATIVAS (A até E):
- Apenas UMA alternativa é correta (lembre-se de randomizar a posição).
- DISTRATOR PRINCIPAL: Crie obrigatoriamente 1 (um) distrator muito forte, com conceitos verdadeiros aplicados de forma errada à restrição do texto.
- As outras alternativas devem conter erros plausíveis.
- Mantenha o paralelismo sintático (mesmo tamanho e estrutura). Proibido \"Todas as anteriores\".

### FORMATO DA SAÍDA OBRIGATÓRIA:
- Retorne em JSON válido no formato abaixo, sem markdown.
{
  \"questoes\": [
    {
      \"textosApoio\": [\"Texto de apoio opcional\"],
      \"comando\": \"Texto do enunciado e comando direto\",
      \"alternativas\": {
        \"A\": \"...\",
        \"B\": \"...\",
        \"C\": \"...\",
        \"D\": \"...\",
        \"E\": \"...\"
      },
      \"correta\": \"A\",
      \"explicacao\": \"Resumo do raciocínio; análise das alternativas explicando o erro conceitual das incorretas e destacando o Distrator Principal; Gabarito: [Letra Correta - diferente de A na maioria das vezes]\",
      \"nivel\": \"{$nivel}\"
    }
  ]
}";

        if (trim($contextoAdicional) !== '') {
            $prompt .= "\n\nCONTEXTO ADICIONAL:\n" . trim($contextoAdicional);
        }

        return $prompt;
    }

    private function gerarExerciciosPersonalizadosPorNivel(string $tema, string $materia, int $quantidade, string $nivel, string $contextoAdicional = ''): array
    {
        $prompt = $this->getUserPromptGeracaoQuestoesDocx($tema, $materia, $quantidade, $nivel, $contextoAdicional);
        $response = $this->fazerRequisicao([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $this->getSystemPromptGeracaoQuestoesPersonalizadas()],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7
        ], 3, 0, 'exercicios_personalizados');

        $content = $response['choices'][0]['message']['content'] ?? '';
        $content = preg_replace('/^```json\s*/m', '', $content);
        $content = preg_replace('/^```\s*/m', '', $content);
        $content = preg_replace('/\s*```$/m', '', $content);
        $content = trim($content);
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $content = $matches[0];
        }
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        $json = json_decode(trim($content), true, 512, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $content = preg_replace('/,\s*}/', '}', $content);
            $content = preg_replace('/,\s*]/', ']', $content);
            $json = json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Erro ao decodificar JSON do lote por nível: ' . json_last_error_msg());
            }
        }
        return $json;
    }

    private function getNivelInstrucaoPromptPersonalizado(array $niveis): string
    {
        $blocos = [];
        foreach ($niveis as $nivel) {
            if ($nivel === 'Fácil') {
                $blocos[] = '- Fácil: questão simples, sem textos longos, enunciado curto e linguagem acessível.';
            } elseif ($nivel === 'Médio') {
                $blocos[] = '- Médio: contexto prático com uma condição/restrição leve e raciocínio intermediário.';
            } elseif ($nivel === 'Difícil') {
                $blocos[] = '- Difícil: questão complexa com análise, comparação de cenários e distratores sofisticados.';
            }
        }
        if (empty($blocos)) {
            return '- Médio: contexto prático com uma condição/restrição leve e raciocínio intermediário.';
        }
        return implode("\n", $blocos);
    }

    private function aplicarDistribuicaoNiveisSolicitada(array $exercicios, array $distribuicaoNiveis): array
    {
        $ordemDesejada = [];
        foreach (['Fácil', 'Médio', 'Difícil'] as $nivel) {
            $qtd = max(0, (int)($distribuicaoNiveis[$nivel] ?? 0));
            for ($i = 0; $i < $qtd; $i++) {
                $ordemDesejada[] = $nivel;
            }
        }

        if (empty($ordemDesejada)) {
            return $exercicios;
        }

        $limite = min(count($exercicios), count($ordemDesejada));
        for ($i = 0; $i < $limite; $i++) {
            $exercicios[$i]['nivel'] = $ordemDesejada[$i];
        }

        return $exercicios;
    }

    /**
     * Converte array no formato "questoes" (prova real) para "exercicios" (Minhas Listas).
     * Aceita: enunciado, titulo, alternativas a-e, resposta_correta, dificuldade.
     */
    private function normalizarQuestoesParaExercicios(array $questoes): array
    {
        $exercicios = [];
        $mapNivel = ['fácil' => 'Fácil', 'facil' => 'Fácil', 'médio' => 'Médio', 'medio' => 'Médio', 'difícil' => 'Difícil', 'dificil' => 'Difícil', 'desafio' => 'Desafio'];
        foreach ($questoes as $i => $q) {
            $alt = $q['alternativas'] ?? [];
            $alternativas = [
                'A' => $alt['A'] ?? $alt['a'] ?? '',
                'B' => $alt['B'] ?? $alt['b'] ?? '',
                'C' => $alt['C'] ?? $alt['c'] ?? '',
                'D' => $alt['D'] ?? $alt['d'] ?? '',
                'E' => $alt['E'] ?? $alt['e'] ?? ''
            ];
            $resposta = $q['resposta_correta'] ?? $q['correta'] ?? 'A';
            $resposta = is_string($resposta) ? strtoupper($resposta[0]) : 'A';
            $dificuldade = $q['dificuldade'] ?? $q['nivel'] ?? 'Médio';
            $dificuldade = is_string($dificuldade) ? ($mapNivel[mb_strtolower($dificuldade)] ?? ucfirst($dificuldade)) : 'Médio';
            $textosApoio = $q['textosApoio'] ?? [];
            if (!is_array($textosApoio)) {
                $textosApoio = [];
            }
            $textoApoioStr = trim(implode("\n\n", array_filter(array_map(function ($t) {
                return is_string($t) ? trim($t) : '';
            }, $textosApoio))));
            $comando = trim((string)($q['comando'] ?? ''));

            $pergunta = $q['enunciado'] ?? $q['pergunta'] ?? '';
            if ($pergunta === '' && ($textoApoioStr !== '' || $comando !== '')) {
                $pergunta = trim($textoApoioStr . "\n\n" . $comando);
            }
            if ($pergunta === '' && !empty($q['titulo'])) {
                $pergunta = $q['titulo'];
            }
            $exercicios[] = [
                'ordem' => $i + 1,
                'pergunta' => $pergunta,
                'alternativas' => $alternativas,
                'correta' => $resposta,
                'explicacao' => $q['explicacao'] ?? '',
                'nivel' => $dificuldade
            ];
        }
        return $exercicios;
    }
    
    /**
     * Pipeline OCR: ETAPA 1 Google Vision (OCR) + ETAPA 2 formatação apenas determinística (formatOCRText).
     * Não usa GPT na transcrição para evitar que o modelo resuma, parafrase ou crie contexto — só cópia literal.
     *
     * @param string $imageData Dados base64 da imagem
     * @param string $prompt Mantido para compatibilidade; não usado quando Google Vision está ativo
     * @return string Texto formatado (parágrafos, recuos; conteúdo idêntico ao escrito, sem reescrita)
     */
    public function analyzeImage($imageData, $prompt)
    {
        error_log("[ANALYZE_IMAGE] Pipeline OCR literal (sem GPT) - " . date('Y-m-d H:i:s'));
        
        $logFile = __DIR__ . '/../../storage/logs/transcricao_' . date('Y-m-d') . '.log';
        $log = function($message) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] {$message}\n";
            error_log($logMessage, 3, $logFile);
            error_log($logMessage);
        };
        
        try {
            $log("analyzeImage: Etapa 1 (OCR) + Etapa 2 (formatação determinística, sem GPT)");
            set_time_limit(300);
            
            $textoOriginal = null;
            $fonteOcr = '';

            // ETAPA 1a: Tentar Supabase primeiro (se configurado)
            $supabaseConfig = $this->getSupabaseTranscribeConfig();
            if ($supabaseConfig !== null) {
                try {
                    $log("=== ETAPA 1a: Transcrição com Supabase (api-transcribe) ===");
                    $textoOriginal = $this->transcreverComSupabase($imageData, null);
                    if ($textoOriginal !== null && trim($textoOriginal) !== '') {
                        $fonteOcr = 'Supabase';
                        $log("Supabase retornou: " . strlen($textoOriginal) . " caracteres");
                    }
                } catch (\Throwable $eSupabase) {
                    $log("Supabase falhou (" . $eSupabase->getMessage() . "); tentando Google Vision.");
                }
            }

            // ETAPA 1b: Fallback para Google Vision
            if ($textoOriginal === null || trim($textoOriginal) === '') {
                if (!extension_loaded('bcmath')) {
                    $log("analyzeImage: BCMath não disponível; Google Vision requer BCMath.");
                    throw new \Exception('OCR requer Google Vision (extensão PHP BCMath necessária) ou configure SUPABASE_TRANSCRIBE_API_KEY. Digite o texto manualmente ou ative BCMath no servidor.');
                }
                $log("=== ETAPA 1b: Transcrição com Google Vision OCR ===");
                $textoOriginal = $this->transcreverComGoogleVision($imageData);
                $fonteOcr = 'Google Vision';
                $log("Google Vision retornou: " . strlen($textoOriginal) . " caracteres");
            }

            if ($textoOriginal === null || trim($textoOriginal) === '') {
                throw new \Exception('Nenhum texto foi identificado na imagem.');
            }

            // ETAPA 2: Formatação determinística (regex). Sem GPT para não resumir/parafrasear/criar contexto.
            $log("=== ETAPA 2: Formatação determinística (formatOCRText) ===");
            $textoFormatado = $this->formatOCRText($textoOriginal);
            $log("Texto formatado: " . strlen($textoFormatado) . " caracteres");
            
            // Remover cabeçalho da redação (NOME:, REDAÇÃO X DA QUESTÃO, TEMA:, ANO:, NOTA:, etc.)
            $textoFormatado = $this->removerCabecalhoRedacao($textoFormatado);
            $log("Após remoção de cabeçalho: " . strlen($textoFormatado) . " caracteres");
            
            $log("analyzeImage: Pipeline concluído (transcrição literal, fonte: " . $fonteOcr . ")");
            return $textoFormatado;
            
        } catch (\Throwable $e) {
            $log("ERRO em OpenAIService::analyzeImage: " . $e->getMessage());
            error_log("ERRO em OpenAIService::analyzeImage: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * MÉTODO TEMPORÁRIO: Usa GPT-4o Vision quando Google Vision está desabilitado
     */
    public function extrairTextoImagemOpenAI($imageData, $prompt, string $mimeType = 'image/jpeg')
    {
        try {
            error_log("=== Usando OpenAI Vision direto para extracao de texto ===");
            
            $systemPromptV3 = $this->getPromptConfig('prompt_ocr_vision_system');
            if ($systemPromptV3 === '') {
                $systemPromptV3 = '[PROTOCOLO DE TRANSCRIÇÃO OCR - NÍVEL DE MÁXIMA LITERALIDADE (V3)]

<DIRETRIZ_PRINCIPAL>
Você é um bot de OCR. Sua única função é transcrever EXATAMENTE o que está escrito. Transcreva apenas se tiver certeza. Não infira, não corrija, não adivinhe.
</DIRETRIZ_PRINCIPAL>

<REGRA_DE_OURO_OBRIGATÓRIA>
Se você NÃO consegue ler uma palavra com 100% de certeza, use a tag: [Não Entendi].
É PROIBIDO inventar palavras. Inventar palavras (ex: "Jay licitas gune" no lugar do que está escrito) é FALHA CRÍTICA.
</REGRA_DE_OURO_OBRIGATÓRIA>

<CABEÇALHO>
NÃO inclua no resultado o cabeçalho da folha (NOME:, REDAÇÃO X DA QUESTÃO, nome do aluno, TEMA:, ANO:, NOTA:). Retorne APENAS o corpo do texto da redação.
</CABEÇALHO>

<REGRAS_DE_FORMATAÇÃO>
1. *Junção de Hífen:* Junte palavras separadas por hífen no fim da linha.
2. *Literalidade Total:* Mantenha TODOS os erros de ortografia e gramática do aluno.
3. *Diagramação:* Parágrafos com recuo de 4 espaços no início de cada um.
</REGRAS_DE_FORMATAÇÃO>

<VERIFICACAO_FINAL>
Revise: você inventou alguma palavra? Se sim, substitua por [Não Entendi].
</VERIFICACAO_FINAL>';
            }

            $response = $this->fazerRequisicao([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPromptV3
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => 'data:' . $mimeType . ';base64,' . $imageData
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 4000,
                'temperature' => 0.0
            ]);
            
            if (!isset($response['choices'][0]['message']['content'])) {
                throw new \Exception('Resposta inválida da OpenAI');
            }
            
            error_log("OpenAI Vision retornou texto com sucesso (" . strlen($response['choices'][0]['message']['content']) . " caracteres)");
            
            return $response['choices'][0]['message']['content'];
            
        } catch (\Exception $e) {
            error_log("ERRO em OpenAIService::extrairTextoImagemOpenAI: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            throw $e; 
        }
    }

    public function extrairGradeHorariaImagemOpenAI($imageData, string $mimeType = 'image/png')
    {
        try {
            $systemPrompt = 'Você é um extrator OCR de grades horárias escolares. Leia a imagem com cuidado, preserve dias, horários, turmas e células, e retorne somente JSON válido.';
            $prompt = "Extraia TODAS as aulas desta grade horária escolar.\n\n"
                . "A imagem normalmente é uma tabela onde as colunas são TURMAS e as linhas combinam DIA DA SEMANA + HORÁRIO. Cada célula contém MATÉRIA - PROFESSOR.\n"
                . "Retorne APENAS JSON válido no formato: {\"aulas\":[{\"dia_semana\":1,\"horario_de\":\"07:10\",\"horario_ate\":\"08:00\",\"turma\":\"1º A\",\"professor\":\"PETERSON\",\"materia\":\"FÍSICA\",\"periodo\":\"manha\"}]}\n"
                . "Regras obrigatórias:\n"
                . "- Crie uma aula para cada célula útil da grade.\n"
                . "- dia_semana: 1=Segunda, 2=Terça, 3=Quarta, 4=Quinta, 5=Sexta, 6=Sábado, 7=Domingo.\n"
                . "- Converta horários como 07:10/08:00 para horario_de=07:10 e horario_ate=08:00.\n"
                . "- Se o título/cabeçalho indicar MANHÃ, use periodo=manha; se indicar TARDE, use periodo=tarde.\n"
                . "- Ignore cabeçalhos, intervalos, células vazias e títulos gerais.\n"
                . "- Preserve nomes de matérias, professores e turmas como aparecem, sem inventar.";

            $response = $this->fazerRequisicao([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => 'data:' . $mimeType . ';base64,' . $imageData,
                                ],
                            ],
                        ],
                    ],
                ],
                'max_tokens' => 12000,
                'temperature' => 0.0,
            ]);

            if (!isset($response['choices'][0]['message']['content'])) {
                throw new \Exception('Resposta inválida da OpenAI');
            }

            return $response['choices'][0]['message']['content'];
        } catch (\Exception $e) {
            error_log("ERRO em OpenAIService::extrairGradeHorariaImagemOpenAI: " . $e->getMessage());
            throw $e;
        }
    }

    private function usarGPT4VisionDireto($imageData, $prompt)
    {
        return $this->extrairTextoImagemOpenAI($imageData, $prompt, 'image/jpeg');
    }
    
    /**
     * Testa conexão com a API
     */
    public function testarConexao()
    {
        try {
            $response = $this->fazerRequisicao([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Teste de conexão'
                    ]
                ],
                'max_tokens' => 10
            ], 3, 0, 'teste_conexao');
            
            return [
                'sucesso' => true,
                'mensagem' => 'Conexão com OpenAI estabelecida com sucesso'
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro na conexão: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Transcreve áudio usando Whisper API da OpenAI
     * @param string|resource $audioData Caminho do arquivo ou dados de áudio
     * @param string $mimeType Tipo MIME do áudio
     * @param string|null $language Código do idioma (ex: 'pt' português, 'en' inglês). Padrão 'pt' para transcrição em português (chat Tudinha).
     */
    public function transcreverAudio($audioData, $mimeType = 'audio/webm', $language = 'pt')
    {
        try {
            $curl = curl_init();
            
            $isDevelopment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                             strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                             (defined('ENVIRONMENT') && ENVIRONMENT === 'development'));
            
            // Detectar extensão baseada no mime type
            $extension = 'webm';
            $mimeToExt = [
                'audio/webm' => 'webm',
                'audio/mp3' => 'mp3',
                'audio/mpeg' => 'mp3',
                'audio/wav' => 'wav',
                'audio/ogg' => 'ogg',
                'audio/oga' => 'oga',
                'audio/flac' => 'flac',
                'audio/m4a' => 'm4a',
                'audio/mp4' => 'm4a',
                'audio/mpga' => 'mpga'
            ];
            
            if (isset($mimeToExt[$mimeType])) {
                $extension = $mimeToExt[$mimeType];
            }
            
            // Se audioData for um caminho de arquivo (string), usar diretamente
            $isFilePath = (is_string($audioData) && file_exists($audioData));
            $shouldDeleteTempFile = false;
            
            if ($isFilePath) {
                $tempFile = $audioData;
                // Detectar extensão do arquivo real
                $realExt = strtolower(pathinfo($audioData, PATHINFO_EXTENSION));
                if (in_array($realExt, ['webm', 'mp3', 'wav', 'ogg', 'oga', 'flac', 'm4a', 'mpga'])) {
                    $extension = $realExt;
                }
                $mimeType = $this->detectMimeType($audioData);
            } else {
                // Se for base64, decodificar e salvar
                $tempFile = tempnam(sys_get_temp_dir(), 'audio_') . '.' . $extension;
                file_put_contents($tempFile, base64_decode($audioData));
                $shouldDeleteTempFile = true;
            }
            
            $postFields = [
                'file' => new \CURLFile($tempFile, $mimeType, 'audio.' . $extension),
                'model' => 'whisper-1',
                'language' => $language // 'pt' = português (chat Tudinha); 'en' = inglês (módulo de inglês)
            ];
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->baseUrl . '/audio/transcriptions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey],
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => !$isDevelopment,
                CURLOPT_SSL_VERIFYHOST => $isDevelopment ? 0 : 2
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            
            // Remover arquivo temporário apenas se foi criado por nós
            if ($shouldDeleteTempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            
            if ($error) {
                throw new \Exception('Erro cURL: ' . $error);
            }
            
            if ($httpCode !== 200) {
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['error']['message'] ?? 'Erro desconhecido';
                throw new \Exception('Erro na API: ' . $errorMsg);
            }
            
            $data = json_decode($response, true);
            if (!isset($data['text'])) {
                throw new \Exception('Resposta inválida da API');
            }
            
            return $data['text'];
        } catch (\Exception $e) {
            error_log("Erro ao transcrever áudio: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Detecta o MIME type de um arquivo de áudio
     */
    private function detectMimeType($filePath)
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $extToMime = [
            'webm' => 'audio/webm',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'oga' => 'audio/ogg',
            'flac' => 'audio/flac',
            'm4a' => 'audio/m4a',
            'mp4' => 'audio/mp4',
            'mpga' => 'audio/mpeg'
        ];
        
        if (isset($extToMime[$extension])) {
            return $extToMime[$extension];
        }
        
        // Fallback: tentar detectar usando mime_content_type
        if (function_exists('mime_content_type')) {
            $detected = mime_content_type($filePath);
            if ($detected && strpos($detected, 'audio/') === 0) {
                return $detected;
            }
        }
        
        // Último fallback
        return 'audio/webm';
    }
    
    /**
     * Conversa em inglês com a IA (legado)
     */
    public function conversarIngles($historico, $systemPrompt)
    {
        try {
            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            
            foreach ($historico as $msg) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
            
            $response = $this->fazerRequisicao([
                'model' => 'gpt-4o',
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.7
            ], 3, 0, 'chat_ingles');
            
            if (!isset($response['choices'][0]['message']['content'])) {
                throw new \Exception('Resposta inválida da OpenAI');
            }
            
            return $response['choices'][0]['message']['content'];
        } catch (\Exception $e) {
            error_log("Erro na conversa de inglês: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Módulo de treino de conversação em inglês: retorna JSON estruturado
     * { "original_sentence", "corrected_sentence", "tip", "natural_response" }
     */
    public function conversarInglesTreino($textoAluno, array $historicoMensagens)
    {
        $systemPrompt = "You are an English conversation teacher. Your name is Jude.

Rules:
- You must always use the name Jude when referring to yourself (e.g. \"I'm Jude\", \"It's Jude here\"). Never use another name or just \"the teacher\".
- Always correct the student's sentence.
- Show the corrected sentence clearly.
- Explain the mistake briefly and simply.
- Respond naturally to continue the conversation.
- Encourage the student to keep speaking.
- If the student speaks Portuguese, politely ask them to speak in English.
- Do NOT answer questions outside English learning.
- Do NOT give long grammar explanations.
- Keep explanations short, friendly, and practical.

You MUST respond with a valid JSON object only, no other text, with exactly these keys:
- original_sentence: the student's sentence as they said it
- corrected_sentence: the corrected version (or same if no errors)
- tip: brief explanation of the mistake (or empty string if no mistake)
- natural_response: your short, natural reply to continue the conversation
- tts_script: (optional) a short phrase to be READ ALOUD to the student in English. If there was a mistake, say something like: \"You said it a little wrong. The correct way is: [say the correct word or phrase clearly]. [Then say the correct full sentence]. [Then your natural reply].\" If no mistake, just repeat natural_response. Keep tts_script concise and natural for listening (one or two sentences).";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        
        foreach ($historicoMensagens as $msg) {
            $content = $msg['content'];
            if (is_array($content) && isset($content['natural_response'])) {
                $content = $content['natural_response'];
            }
            $messages[] = ['role' => $msg['role'], 'content' => $content];
        }
        
        $messages[] = ['role' => 'user', 'content' => $textoAluno];

        $response = $this->fazerRequisicao([
            'model' => 'gpt-4o',
            'messages' => $messages,
            'max_tokens' => 400,
            'temperature' => 0.6
        ], 3, 0, 'chat_ingles_treino');

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \Exception('Resposta inválida da OpenAI');
        }

        $raw = trim($response['choices'][0]['message']['content']);
        $json = json_decode($raw, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
            $json = $this->extrairJsonDaResposta($raw);
        }
        
        if (!$json || !isset($json['natural_response'])) {
            $json = [
                'original_sentence' => $textoAluno,
                'corrected_sentence' => $textoAluno,
                'tip' => '',
                'natural_response' => 'Good try! Keep speaking in English.',
                'tts_script' => ''
            ];
        }
        
        $ttsScript = trim($json['tts_script'] ?? '');
        if ($ttsScript === '') {
            $ttsScript = $json['natural_response'] ?? 'Keep going!';
        }
        
        return [
            'original_sentence' => $json['original_sentence'] ?? $textoAluno,
            'corrected_sentence' => $json['corrected_sentence'] ?? $textoAluno,
            'tip' => $json['tip'] ?? '',
            'natural_response' => $json['natural_response'] ?? 'Keep going!',
            'tts_script' => $ttsScript
        ];
    }
    
    /**
     * Traduz texto em inglês para português brasileiro (para o módulo de inglês do aluno)
     */
    public function traduzirParaPortugues($texto)
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }
        $prompt = "Translate the following text from English to Brazilian Portuguese. Reply with ONLY the translation, no explanation or extra text.\n\n" . $texto;
        $response = $this->fazerRequisicao([
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 500,
            'temperature' => 0.3
        ], 2, 0, 'traducao');
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \Exception('Resposta inválida na tradução');
        }
        return trim($response['choices'][0]['message']['content']);
    }

    private function extrairJsonDaResposta($text)
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i', $text, $m)) {
            $decoded = json_decode(trim($m[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        if (preg_match('/(\{[\s\S]*\})/', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }
    
    /**
     * Método genérico para chat completions com parâmetros customizáveis
     * 
     * @param array $mensagens Array de mensagens no formato [['role' => 'user', 'content' => '...'], ...]
     * @param string|null $systemPrompt Prompt do sistema (opcional)
     * @param string $modelo Modelo a usar (padrão: gpt-4o-mini)
     * @param float $temperatura Temperatura (0-2, padrão: 0.7)
     * @param int $maxTokens Máximo de tokens (padrão: 2000)
     * @return array ['resposta' => string, 'tokens_usados' => int]
     */
    public function chatCompletion($mensagens, $systemPrompt = null, $modelo = 'gpt-4o-mini', $temperatura = 0.7, $maxTokens = 2000, $stream = false)
    {
        try {
            $messages = [];
            
            // Adiciona system prompt se fornecido
            if ($systemPrompt) {
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            }
            
            // Adiciona mensagens do histórico
            foreach ($mensagens as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    // Suportar mensagens com imagens (formato OpenAI Vision)
                    if (isset($msg['content']) && is_array($msg['content'])) {
                        $messages[] = [
                            'role' => $msg['role'],
                            'content' => $msg['content']
                        ];
                    } else {
                        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
                    }
                }
            }
            
            $requestData = [
                'model' => $modelo,
                'messages' => $messages,
            ];
            // Modelos mais novos (GPT-5.x, o1/o3) rejeitam 'max_tokens' com HTTP 400
            // ("Unsupported parameter") — exigem 'max_completion_tokens' no lugar.
            // Também rejeitam qualquer 'temperature' diferente do default (1).
            // gpt-4o/gpt-4o-mini e afins continuam usando os dois parâmetros normalmente.
            if (!class_exists('App\\AI\\ModeloIA', false)) {
                require_once __DIR__ . '/../AI/ModeloIA.php';
            }
            if (\App\AI\ModeloIA::exigeMaxCompletionTokens($modelo)) {
                // Orçamento com folga: tokens de raciocínio saem do mesmo limite.
                $requestData['max_completion_tokens'] = \App\AI\ModeloIA::orcamentoTokens($modelo, $maxTokens);
            } else {
                $requestData['max_tokens'] = $maxTokens;
            }
            if (\App\AI\ModeloIA::aceitaTemperaturaCustomizada($modelo)) {
                $requestData['temperature'] = $temperatura;
            }

            // Se streaming, usar método específico (exige callback — ver chatCompletionStream())
            if ($stream) {
                throw new \Exception('chatCompletion($stream=true) não suporta mais streaming sem callback; use chatCompletionStream($requestData, $onChunk) diretamente.');
            }
            try {
                $response = $this->fazerRequisicao($requestData, 3, 0, 'chat_completion');
            } catch (\Exception $e) {
                // HTTP 401 "insufficient permissions" = conta/organização sem
                // acesso liberado a este modelo específico (não é erro
                // transitório, retentar o mesmo modelo não resolve) — cai
                // pro modelo de fallback definido em ModeloIA, se houver.
                $modeloFallback = \App\AI\ModeloIA::modeloFallback($modelo);
                if ($modeloFallback !== null && str_contains($e->getMessage(), 'Erro HTTP 401')) {
                    error_log("OpenAIService: {$modelo} sem permissão (401), tentando fallback {$modeloFallback}");
                    // Todo modelo com fallback definido hoje é da mesma família
                    // gpt-5.x (mesmas regras de max_completion_tokens/temperature
                    // já aplicadas acima) — só troca o nome do modelo.
                    $requestData['model'] = $modeloFallback;
                    $response = $this->fazerRequisicao($requestData, 3, 0, 'chat_completion');
                } else {
                    throw $e;
                }
            }
            if (!isset($response['choices'][0]['message'])) {
                throw new \Exception('Resposta inválida da OpenAI');
            }

            $conteudo = (string) ($response['choices'][0]['message']['content'] ?? '');
            $tokensDescartados = 0;

            // HTTP 200 com conteúdo vazio: quase sempre é o modelo de raciocínio
            // estourando o limite pensando (finish_reason 'length'). Repete uma
            // única vez com o orçamento ampliado antes de desistir.
            if (trim($conteudo) === '') {
                $finishReason = (string) ($response['choices'][0]['finish_reason'] ?? '');
                $recusa = (string) ($response['choices'][0]['message']['refusal'] ?? '');
                $tokensRaciocinio = (int) ($response['usage']['completion_tokens_details']['reasoning_tokens'] ?? 0);
                $orcamentoAtual = (int) ($requestData['max_completion_tokens'] ?? 0);

                if ($finishReason === 'length' && $orcamentoAtual > 0 && $orcamentoAtual < \App\AI\ModeloIA::ORCAMENTO_RACIOCINIO_MAXIMO) {
                    $orcamentoAmpliado = min(\App\AI\ModeloIA::ORCAMENTO_RACIOCINIO_MAXIMO, $orcamentoAtual * 2);
                    error_log("OpenAIService: conteúdo vazio com finish_reason=length (raciocínio usou {$tokensRaciocinio} tokens); repetindo com max_completion_tokens={$orcamentoAmpliado}");
                    // A tentativa perdida também foi cobrada pela OpenAI — soma no total.
                    $tokensDescartados = (int) ($response['usage']['total_tokens'] ?? 0);
                    $requestData['max_completion_tokens'] = $orcamentoAmpliado;
                    $response = $this->fazerRequisicao($requestData, 3, 0, 'chat_completion');
                    $conteudo = (string) ($response['choices'][0]['message']['content'] ?? '');
                }

                if (trim($conteudo) === '') {
                    $detalhe = "finish_reason={$finishReason}, reasoning_tokens={$tokensRaciocinio}";
                    if ($recusa !== '') {
                        $detalhe .= ", refusal={$recusa}";
                    }
                    throw new \Exception("OpenAI devolveu conteúdo vazio ({$detalhe}).");
                }
            }

            $tokensUsados = ($response['usage']['total_tokens'] ?? 0) + $tokensDescartados;

            return [
                'resposta' => $conteudo,
                'tokens_usados' => $tokensUsados
            ];
        } catch (\Exception $e) {
            error_log("Erro no chat completion: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Chat completion com streaming (Server-Sent Events).
     *
     * Não é um generator: CURLOPT_WRITEFUNCTION é uma callback síncrona chamada
     * pelo próprio cURL a cada pedaço de dado recebido na conexão — colocar
     * `yield` dentro dela transforma a própria closure num generator (que só
     * roda quando iterado) em vez de executar o corpo na hora, e o cURL recebe
     * de volta um objeto Generator em vez do inteiro esperado como "bytes
     * escritos", o que ele interpreta como escrita incompleta e aborta com
     * CURLE_WRITE_ERROR ("Failure writing output to destination") — bug real
     * encontrado ao ligar o streaming de verdade no chat do aluno. Corrigido
     * invocando $onChunk diretamente dentro da callback, em tempo real.
     *
     * @param array $requestData Dados da requisição
     * @param callable $onChunk Chamado com cada pedaço de texto assim que chega (ex.: echo + flush)
     */
    public function chatCompletionStream($requestData, callable $onChunk)
    {
        $apiKey = $this->getOpenAIApiKey();
        if (empty($apiKey)) {
            throw new \Exception('OPENAI_API_KEY não configurada');
        }

        $requestData['stream'] = true;

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$buffer, $onChunk) {
                $buffer .= $data;
                $lines = explode("\n", $buffer);
                $buffer = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || $line === 'data: [DONE]') {
                        continue;
                    }

                    if (strpos($line, 'data: ') === 0) {
                        $json = substr($line, 6);
                        $parsed = json_decode($json, true);

                        if (isset($parsed['choices'][0]['delta']['content'])) {
                            $onChunk($parsed['choices'][0]['delta']['content']);
                        }
                    }
                }

                return strlen($data);
            },
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Erro cURL: " . $error);
        }

        if ($httpCode !== 200) {
            throw new \Exception("Erro HTTP {$httpCode}");
        }
    }

    /**
     * Gera uma imagem via gpt-image-1 (modelo de imagem nativo da OpenAI) e salva
     * no storage de chat do aluno, devolvendo a URL servida — mesmo formato de
     * retorno já usado por NanoBananaService::gerarImagem() (chave 'image_url'),
     * pra poder ser usado como alternativa dropin no chat da Tudinha.
     *
     * @param string $prompt
     * @param int $alunoId Dono do arquivo salvo (mesma convenção de MediaStorageService::userKey)
     * @param array $config Config global da app (mesmo array que o controller passa pra MediaStorageService)
     * @param string $size '1024x1024', '1024x1536' ou '1536x1024'
     * @return array{image_url: string}
     */
    public function generateImage(string $prompt, int $alunoId, array $config, string $size = '1536x1024', string $quality = 'medium')
    {
        $apiKey = $this->getOpenAIApiKey();
        if (empty($apiKey)) {
            throw new \Exception('OPENAI_API_KEY não configurada');
        }

        $payload = [
            'model' => 'gpt-image-2',
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'quality' => $quality,
        ];

        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception('Erro de conexão ao gerar imagem: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            $message = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : 'Falha ao gerar imagem (gpt-image-1)';
            throw new \Exception($message);
        }

        $b64 = $data['data'][0]['b64_json'] ?? null;
        if (empty($b64)) {
            throw new \Exception('Resposta da OpenAI sem imagem (gpt-image-1)');
        }

        $imageBinary = base64_decode($b64, true);
        if ($imageBinary === false || strlen($imageBinary) === 0) {
            throw new \Exception('Falha ao decodificar imagem gerada (gpt-image-1)');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'gptimg_');
        if ($tmpPath === false || file_put_contents($tmpPath, $imageBinary) === false) {
            throw new \Exception('Falha ao gravar imagem gerada em arquivo temporário');
        }

        try {
            require_once __DIR__ . '/MediaStorageService.php';
            $filename = 'chat_gerada_' . $alunoId . '_' . time() . '_' . uniqid() . '.png';
            $key = \MediaStorageService::userKey('student', $alunoId, $filename);
            $media = new \MediaStorageService($config);
            if (!$media->put('chat', $key, $tmpPath, 'image/png')) {
                throw new \Exception('Erro ao salvar imagem gerada');
            }
        } finally {
            @unlink($tmpPath);
        }

        $path = (defined('FOLDER') && FOLDER !== '') ? rtrim(FOLDER, '/') : '';
        $path .= '/media/serve?type=chat&key=' . rawurlencode($key);
        if ($path !== '' && strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        return ['image_url' => $path];
    }

    /**
     * Minta uma sessão efêmera da Realtime API (voz em tempo real) — o client
     * secret retornado tem vida curta e é o único valor que deve ir pro
     * navegador; a chave mestra da OpenAI nunca sai do servidor. O navegador
     * usa esse secret pra abrir a conexão WebRTC direto com a OpenAI (sem
     * passar pelo nosso servidor, que não tem infra de WebSocket/streaming
     * de áudio).
     *
     * @param string $instructions System prompt da sessão (ex.: prompt da Tudinha + contexto do aluno)
     * @param string $model Nome do modelo de voz em tempo real
     * @param string $voice Voz da IA (ex.: 'alloy')
     * @return array{client_secret: string, expires_at: int|null, model: string}
     */
    public function criarSessaoRealtime(string $instructions, string $model = 'gpt-realtime-2.1', string $voice = 'marin', float $speed = 1.15)
    {
        $apiKey = $this->getOpenAIApiKey();
        if (empty($apiKey)) {
            throw new \Exception('OPENAI_API_KEY não configurada');
        }

        $payload = [
            // Expira o client secret em 10 min a partir da criação — janela pra
            // ESTABELECER a conexão, não um limite de duração da chamada em si
            // (a Realtime API não expõe um "encerra a chamada sozinha depois de
            // N minutos" simples). O limite de duração da chamada em andamento
            // continua sendo o timer do lado do cliente em chat.php — trava
            // adicional aqui reduz a janela de replay/uso indevido do secret,
            // mas não substitui a necessidade de revisar custo real via
            // dashboard da OpenAI enquanto a cobrança for fixa por sessão.
            'expires_after' => ['anchor' => 'created_at', 'seconds' => 600],
            'session' => [
                'type' => 'realtime',
                'model' => $model,
                'instructions' => $instructions,
                // 'marin' é a voz nova lançada junto com a família gpt-realtime — mais
                // natural/expressiva que as vozes antigas (alloy/shimmer/echo etc.), tom
                // feminino. Não existe seleção de sotaque/idioma da voz em si — o
                // "brasileira" vem do idioma da conversa, já fixado em português no
                // prompt da Tudinha. 'speed' > 1 fala um pouco mais rápido que o padrão.
                'audio' => ['output' => ['voice' => $voice, 'speed' => $speed]],
            ],
        ];

        $ch = curl_init('https://api.openai.com/v1/realtime/client_secrets');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception('Erro de conexão ao iniciar sessão de voz: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            $message = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : 'Falha ao iniciar sessão de voz';
            throw new \Exception($message);
        }

        $clientSecret = $data['value'] ?? null;
        if (empty($clientSecret)) {
            throw new \Exception('Resposta da OpenAI sem client secret (Realtime API)');
        }

        return [
            'client_secret' => $clientSecret,
            'expires_at' => $data['expires_at'] ?? null,
            'model' => $model,
        ];
    }

    /**
     * Gera áudio usando ElevenLabs API
     *
     * @param string $texto Texto para converter em áudio
     * @param string $voiceId ID da voz (padrão: voz feminina em inglês)
     * @return string URL ou dados do áudio em base64
     */
    public function gerarAudioElevenLabs($texto, $voiceId = '21m00Tcm4TlvDq8ikWAM')
    {
        try {
            // Buscar API key do ElevenLabs
            $apiKey = $this->getElevenLabsApiKey();
            
            if (empty($apiKey)) {
                throw new \Exception('ElevenLabs API key não configurada');
            }
            
            $curl = curl_init();
            
            // Verificar se está em ambiente de desenvolvimento
            $isDevelopment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                             strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                             (defined('ENVIRONMENT') && ENVIRONMENT === 'development'));
            
            $postData = json_encode([
                'text' => $texto,
                'model_id' => 'eleven_multilingual_v2',
                'voice_settings' => [
                    'stability' => 0.5,
                    'similarity_boost' => 0.75,
                    'style' => 0.0,
                    'use_speaker_boost' => true
                ]
            ]);
            
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: audio/mpeg',
                    'Content-Type: application/json',
                    'xi-api-key: ' . $apiKey
                ],
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => !$isDevelopment,
                CURLOPT_SSL_VERIFYHOST => $isDevelopment ? 0 : 2
            ]);
            
            $audioData = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            
            if ($error) {
                throw new \Exception('Erro cURL: ' . $error);
            }
            
            if ($httpCode !== 200) {
                $errorData = json_decode($audioData, true);
                $errorMsg = $errorData['detail']['message'] ?? 'Erro desconhecido na geração de áudio';
                throw new \Exception('Erro na API ElevenLabs: ' . $errorMsg);
            }
            
            // Retornar áudio em base64
            return base64_encode($audioData);
            
        } catch (\Exception $e) {
            error_log("Erro ao gerar áudio ElevenLabs: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Gera áudio usando OpenAI TTS API (fallback quando ElevenLabs não está disponível)
     * Vozes: alloy, ash, ballad, coral, echo, fable, onyx, nova, sage, shimmer, verse, marin, cedar
     *
     * @param string $texto Texto para converter em áudio
     * @param string $voice Nome da voz (padrão: nova, boa para inglês conversacional)
     * @return string Áudio em base64 (mp3)
     */
    public function gerarAudioOpenAI($texto, $voice = 'nova')
    {
        $texto = trim($texto);
        if ($texto === '') {
            throw new \Exception('Texto vazio para TTS');
        }
        $apiKey = $this->getOpenAIApiKey();
        if (empty($apiKey)) {
            throw new \Exception('OPENAI_API_KEY não configurada');
        }
        $isDevelopment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
                         strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                         (defined('ENVIRONMENT') && ENVIRONMENT === 'development'));
        $postData = json_encode([
            'model' => 'tts-1',
            'input' => $texto,
            'voice' => $voice,
            'response_format' => 'mp3'
        ]);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->baseUrl . '/audio/speech',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => !$isDevelopment,
            CURLOPT_SSL_VERIFYHOST => $isDevelopment ? 0 : 2
        ]);
        $audioData = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($error) {
            throw new \Exception('Erro cURL OpenAI TTS: ' . $error);
        }
        if ($httpCode !== 200) {
            $errBody = json_decode($audioData, true);
            $msg = $errBody['error']['message'] ?? 'Erro ' . $httpCode;
            throw new \Exception('Erro na API OpenAI TTS: ' . $msg);
        }
        return base64_encode($audioData);
    }

    /**
     * Texto para voz (TTS) — salva em storage/audio e retorna URL e filename.
     * Usado pelo chat do aluno em substituição ao ElevenLabs.
     * Vozes OpenAI: alloy, ash, ballad, coral, echo, fable, onyx, nova, sage, shimmer, verse, marin, cedar.
     *
     * @param string $texto Texto para converter em áudio
     * @param string|null $voiceId Ignorado (compatibilidade com ElevenLabs); use $options['voice']
     * @param array $options ['voice' => 'nova'|'alloy'|...]
     * @return array { url, filename, file_path }
     */
    public function textoParaVoz($texto, $voiceId = null, $options = [])
    {
        $voice = $options['voice'] ?? 'nova';
        $audioB64 = $this->gerarAudioOpenAI($texto, $voice);
        $audioData = base64_decode($audioB64, true);
        if ($audioData === false) {
            throw new \Exception('Erro ao decodificar áudio gerado');
        }
        $audioDir = __DIR__ . '/../../storage/audio/';
        if (!is_dir($audioDir)) {
            @mkdir($audioDir, 0755, true);
        }
        $filename = 'tts_' . time() . '_' . uniqid() . '.mp3';
        $filepath = $audioDir . $filename;
        if (file_put_contents($filepath, $audioData) === false) {
            throw new \Exception('Erro ao salvar arquivo de áudio');
        }
        $baseUrl = defined('URL') ? rtrim(URL, '/') : '';
        return [
            'file_path' => $filepath,
            'url' => $baseUrl . '/storage/audio/' . $filename,
            'filename' => $filename
        ];
    }
    
    /**
     * Busca API key do ElevenLabs
     */
    private function getElevenLabsApiKey()
    {
        $apiKey = null;
        
        // PRIORIDADE 1: Buscar do banco de dados
        try {
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            
            if (class_exists('Database', false)) {
                $db = \Database::getInstance();
                $config = $db->fetch(
                    "SELECT config_value FROM config_layout WHERE config_key = ?",
                    ['elevenlabs_api_key']
                );
                if ($config && !empty($config['config_value'])) {
                    $apiKey = trim($config['config_value']);
                }
            }
        } catch (\Exception $e) {
            error_log("Erro ao buscar ElevenLabs key do banco: " . $e->getMessage());
        }
        
        // PRIORIDADE 2: Tentar carregar do config/app.php
        if (empty($apiKey)) {
            try {
                $config = require __DIR__ . '/../../config/app.php';
                $apiKey = $config['ai']['elevenlabs_api_key'] ?? null;
            } catch (\Exception $e) {
                $apiKey = null;
            }
        }
        
        // PRIORIDADE 3: Carregar .env diretamente
        if (empty($apiKey)) {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $content = file_get_contents($envPath);
                if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                    $content = substr($content, 3);
                }
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'ELEVENLABS_API_KEY=') === 0) {
                        $apiKey = substr($line, strlen('ELEVENLABS_API_KEY='));
                        break;
                    }
                }
            }
        }
        
        // PRIORIDADE 4: Tentar getenv()
        if (empty($apiKey)) {
            $apiKey = getenv('ELEVENLABS_API_KEY');
        }
        
        // PRIORIDADE 5: Tentar $_ENV
        if (empty($apiKey)) {
            $apiKey = $_ENV['ELEVENLABS_API_KEY'] ?? null;
        }
        
        return $apiKey;
    }

    /**
     * Registra métricas de uso da IA
     *
     * @param array $response Resposta da OpenAI
     * @param float $startTime Tempo inicial da requisição
     * @param string $usageType Tipo de uso
     * @param int $httpCode Código HTTP
     * @param string $error Mensagem de erro (se houver)
     */
    private function recordMetrics($response, $startTime, $usageType, $httpCode, $error = '')
    {
        try {
            // Calcular tempo de resposta
            $requestTime = microtime(true) - $startTime;

            // Extrair tokens da resposta
            $promptTokens = $response['usage']['prompt_tokens'] ?? 0;
            $completionTokens = $response['usage']['completion_tokens'] ?? 0;
            $totalTokens = $response['usage']['total_tokens'] ?? 0;

            // Obter modelo usado
            $model = $response['model'] ?? 'unknown';

            // Calcular custo estimado
            $cost = $this->calculateCost($model, $promptTokens, $completionTokens);

            // Registrar métricas (JSON/cache)
            \App\Services\MetricsService::recordAIMetrics([
                'usage_type' => $usageType,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'cost' => $cost,
                'request_time' => $requestTime,
                'http_code' => $httpCode,
                'error' => !empty($error) ? substr($error, 0, 255) : null
            ]);

            // Persistir no banco para contabilidade (relatório Custos LLM)
            $this->logUsageToDatabase($model, $promptTokens, $completionTokens, $totalTokens, $cost, $usageType);

        } catch (\Exception $e) {
            // Não interromper execução em caso de erro no registro de métricas
            error_log("Erro ao registrar métricas OpenAI: " . $e->getMessage());
        }
    }

    /**
     * Grava uso de IA na tabela logs_uso_llm (banco) para relatório de custos.
     * Chamado por recordMetrics e pode ser chamado após streaming com tokens estimados.
     */
    public function logUsageToDatabase($model, $promptTokens, $completionTokens, $totalTokens, $costUsd, $usageType = 'general')
    {
        try {
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            $db = \Database::getInstance();
            $db->insert(
                "INSERT INTO logs_uso_llm (model, prompt_tokens, completion_tokens, total_tokens, cost_usd, usage_type) VALUES (?, ?, ?, ?, ?, ?)",
                [$model, (int)$promptTokens, (int)$completionTokens, (int)$totalTokens, (float)$costUsd, substr((string)$usageType, 0, 60)]
            );
        } catch (\Exception $e) {
            error_log("Erro ao gravar logs_uso_llm: " . $e->getMessage());
        }
    }

    /**
     * Calcula custo estimado baseado no modelo e tokens (método estático para uso em backfill/relatórios).
     *
     * @param string $model Modelo usado
     * @param int $promptTokens Tokens de prompt
     * @param int $completionTokens Tokens de completion
     * @return float Custo em USD
     */
    public static function calculateCostEstimate($model, $promptTokens, $completionTokens)
    {
        // Preferência: config/llm_precos.php (USD / 1M tokens). Fallback legado por 1K.
        $path = dirname(__DIR__, 2) . '/config/llm_precos.php';
        if (is_file($path)) {
            $cfg = require $path;
            $modelos = (is_array($cfg) && !empty($cfg['modelos']) && is_array($cfg['modelos']))
                ? $cfg['modelos']
                : [];
            if ($modelos !== []) {
                $m = strtolower(trim((string) $model));
                $key = 'gpt-4o-mini';
                if (isset($modelos[$m])) {
                    $key = $m;
                } else {
                    $known = array_keys($modelos);
                    usort($known, static fn ($a, $b) => strlen($b) <=> strlen($a));
                    foreach ($known as $k) {
                        if ($m !== '' && str_starts_with($m, $k)) {
                            $key = $k;
                            break;
                        }
                    }
                }
                $p = $modelos[$key] ?? ['input' => 0.15, 'output' => 0.60];
                $cost = ((int) $promptTokens / 1_000_000) * (float) $p['input']
                    + ((int) $completionTokens / 1_000_000) * (float) $p['output'];
                return round($cost, 6);
            }
        }

        $prices = [
            'gpt-4o' => ['prompt' => 0.0025, 'completion' => 0.01],
            'gpt-4o-mini' => ['prompt' => 0.00015, 'completion' => 0.0006],
            'gpt-4-turbo' => ['prompt' => 0.01, 'completion' => 0.03],
            'gpt-4' => ['prompt' => 0.03, 'completion' => 0.06],
            'gpt-3.5-turbo' => ['prompt' => 0.0005, 'completion' => 0.0015],
        ];
        $modelPrices = $prices[$model] ?? $prices['gpt-4o-mini'];
        $promptCost = ((int) $promptTokens / 1000) * $modelPrices['prompt'];
        $completionCost = ((int) $completionTokens / 1000) * $modelPrices['completion'];
        return round($promptCost + $completionCost, 6);
    }

    /**
     * Calcula custo estimado baseado no modelo e tokens (instância).
     */
    private function calculateCost($model, $promptTokens, $completionTokens)
    {
        return self::calculateCostEstimate($model, $promptTokens, $completionTokens);
    }

    public function gerarPlanoAulaCopiloto(string $promptProfessor, string $referencias, array $contexto = []): array
    {
        $materiaNome = '';
        if (!empty($contexto['materia']) && is_array($contexto['materia'])) {
            $materiaNome = (string) ($contexto['materia']['nome'] ?? '');
        }
        $turmas = [];
        foreach (($contexto['turmas'] ?? []) as $turma) {
            if (is_array($turma) && !empty($turma['nome'])) {
                $turmas[] = (string) $turma['nome'];
            }
        }

        $system = 'Você é um copiloto pedagógico da EducaTudo. Gere um rascunho de plano de aula claro, prático e editável para professores. Retorne somente JSON válido.';
        $userPrompt = "Monte um rascunho de plano de aula em português do Brasil.\n\n"
            . "CONTEXTO:\n"
            . "- Professor: " . (string) ($contexto['professor_nome'] ?? '') . "\n"
            . "- Matéria: {$materiaNome}\n"
            . "- Turmas: " . implode(', ', $turmas) . "\n"
            . "- Datas: " . (string) ($contexto['datas_aula'] ?? '') . "\n"
            . "- Título já digitado: " . (string) ($contexto['titulo_atual'] ?? '') . "\n\n"
            . "- Módulo/Apostila já digitado: " . (string) ($contexto['modulo_atual'] ?? '') . "\n"
            . "- Aula Nº já digitada: " . (string) ($contexto['aula_num_atual'] ?? '') . "\n"
            . "- Páginas já digitadas: " . (string) ($contexto['paginas_atual'] ?? '') . "\n\n"
            . "PEDIDO DO PROFESSOR:\n{$promptProfessor}\n\n"
            . "MATERIAL ENVIADO:\n{$referencias}\n\n"
            . "PADRÃO DE PREENCHIMENTO:\n"
            . "- Considere TODO o material enviado, inclusive todos os PDFs, imagens e prints. Não use apenas o primeiro trecho reconhecido.\n"
            . "- modulo significa número da apostila/volume/módulo do material, normalmente de 1 a 8. Capítulo NÃO é módulo. Nunca preencha modulo com \"Capítulo 25\", \"Capítulo 26\" ou título de capítulo.\n"
            . "- Só preencha modulo quando houver referência explícita a Apostila, Módulo ou Volume no material/pedido. Se aparecer apenas Capítulo, deixe modulo vazio.\n"
            . "- aula_num deve reunir TODAS as linhas identificadas como Aula, por exemplo \"Aula 73\" e \"Aula 74\" viram \"73 e 74\". Não considere linhas de Capítulo como aula.\n"
            . "- paginas deve usar SOMENTE a página das linhas de Aula. Em sumários, ignore a página do Capítulo. Exemplo: \"Capítulo 25 ... 5\", \"Aula 73 ... 7\", \"Aula 74 ... 25\" => paginas = \"7 e 25\".\n"
            . "- O campo conteudo deve incluir os temas/títulos de TODAS as aulas reconhecidas, não apenas a primeira aula. Para o exemplo anterior, inclua tanto o conteúdo da Aula 73 quanto o da Aula 74.\n"
            . "- Se não encontrar modulo, aula_num ou paginas com segurança seguindo as regras acima, deixe a chave correspondente como string vazia. Não invente números.\n"
            . "- O campo conteudo deve ser uma lista curta dos conteúdos/temas que serão ministrados, como nos exemplos: \"Plantas: briófitas e pteridófitas\", \"Ciclo celular\", \"Divisão celular (I): mitose\".\n"
            . "- O campo objetivos deve começar por capacidades do aluno, preferencialmente com verbos como Reconhecer, Identificar, Comparar, Classificar, Caracterizar, Relacionar, Compreender, Resolver, Interpretar ou Aplicar.\n"
            . "- Não coloque textos longos no conteúdo. Conteúdo é tópico; objetivos explicam o que o aluno deverá ser capaz de fazer.\n"
            . "- O campo contexto_llm deve ser um texto bem completo, em Markdown, criado a partir do material enviado. Ele será usado depois por outra LLM para gerar jornadas, atividades e exercícios.\n"
            . "- Em contexto_llm, escreva com bastante detalhe e organize exatamente nestas seções quando houver informação suficiente: Visão geral da aula; Conteúdo estruturado; Conceitos essenciais; Explicação detalhada; Relações entre os temas; Vocabulário importante; Objetivos pedagógicos ampliados; Possíveis atividades; Pontos de atenção para o professor; Dados reconhecidos do material.\n"
            . "- Em Dados reconhecidos do material, cite modulo, aula_num e paginas somente quando reconhecidos com segurança. Se não houver segurança, escreva que não foi identificado.\n"
            . "- Não invente trechos que não estejam no material ou no pedido. Quando precisar completar pedagogicamente, deixe claro que é uma sugestão didática.\n"
            . "- Use HTML simples em conteudo, objetivos e recursos. Preferencialmente retorne conteudo e objetivos como <ul><li>...</li></ul>. Use <strong> apenas para destacar termos centrais dentro do item.\n"
            . "- O título deve ser objetivo e compatível com a matéria/turma/material, mas não precisa repetir todos os tópicos.\n\n"
            . "Responda com as chaves: titulo, modulo, aula_num, paginas, conteudo, objetivos, recursos, recursos_lista, observacoes, contexto_llm. "
            . "recursos_lista deve ser array com itens entre: Quadro, Projetor, Computador, Livro, Apostila, Vídeo, Áudio, EducaColag.";

        $response = $this->fazerRequisicao([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.4,
            'response_format' => ['type' => 'json_object'],
        ], 3, 0, 'plano_aula_copiloto');

        $content = (string) ($response['choices'][0]['message']['content'] ?? '');
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            $content = trim(preg_replace('/^```(?:json)?|```$/m', '', $content));
            $decoded = json_decode($content, true);
        }
        if (!is_array($decoded)) {
            throw new \Exception('O Copiloto não retornou um rascunho válido.');
        }

        $permitidos = ['Quadro', 'Projetor', 'Computador', 'Livro', 'Apostila', 'Vídeo', 'Áudio', 'EducaColag'];
        $recursosLista = $decoded['recursos_lista'] ?? [];
        if (!is_array($recursosLista)) {
            $recursosLista = [];
        }
        $recursosLista = array_values(array_intersect($permitidos, array_map('strval', $recursosLista)));

        return [
            'titulo' => $this->normalizarCampoTextoCopiloto($decoded['titulo'] ?? '', false),
            'modulo' => $this->normalizarCampoTextoCopiloto($decoded['modulo'] ?? '', false),
            'aula_num' => $this->normalizarCampoTextoCopiloto($decoded['aula_num'] ?? '', false),
            'paginas' => $this->normalizarCampoTextoCopiloto($decoded['paginas'] ?? '', false),
            'conteudo' => $this->normalizarCampoTextoCopiloto($decoded['conteudo'] ?? '', true),
            'objetivos' => $this->normalizarCampoTextoCopiloto($decoded['objetivos'] ?? '', true),
            'recursos' => $this->normalizarCampoTextoCopiloto($decoded['recursos'] ?? '', true),
            'recursos_lista' => $recursosLista,
            'observacoes' => $this->normalizarCampoTextoCopiloto($decoded['observacoes'] ?? '', false),
            'contexto_llm' => $this->normalizarCampoTextoCopiloto($decoded['contexto_llm'] ?? '', false),
        ];
    }

    private function normalizarCampoTextoCopiloto($value, bool $htmlList = false): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            $text = trim((string) $value);
            if ($htmlList && $this->contemHtmlCopiloto($text)) {
                return $this->sanitizarHtmlCopiloto($text);
            }
            return $htmlList ? $text : trim(strip_tags($text));
        }

        if (!is_array($value)) {
            return '';
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            $items = [];
            foreach ($value as $item) {
                $text = $this->normalizarCampoTextoCopiloto($item, false);
                if ($text !== '') {
                    $items[] = $text;
                }
            }

            if ($htmlList) {
                $lis = [];
                foreach ($items as $item) {
                    $itemTemHtml = false;
                    if ($this->contemHtmlCopiloto($item)) {
                        $itemTemHtml = true;
                        $html = $this->sanitizarHtmlCopiloto($item);
                        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $matches)) {
                            foreach ($matches[1] as $li) {
                                $li = trim($li);
                                if ($li !== '') {
                                    $lis[] = '<li>' . $li . '</li>';
                                }
                            }
                            continue;
                        }

                        $item = trim(strip_tags($html, '<strong><b><em><i><br>'));
                    }

                    if ($item !== '') {
                        $lis[] = '<li>' . ($itemTemHtml ? $item : htmlspecialchars($item, ENT_QUOTES, 'UTF-8')) . '</li>';
                    }
                }
                return $lis ? '<ul>' . implode('', $lis) . '</ul>' : '';
            }

            return implode("\n", array_map(static fn($item) => '- ' . $item, $items));
        }

        $lines = [];
        foreach ($value as $key => $item) {
            $text = $this->normalizarCampoTextoCopiloto($item, false);
            if ($text !== '') {
                $lines[] = trim((string) $key) . ': ' . $text;
            }
        }

        return implode("\n", $lines);
    }

    private function contemHtmlCopiloto(string $value): bool
    {
        return preg_match('/<\/?[a-z][\s\S]*>/i', $value) === 1
            || preg_match('/&lt;\/?[a-z][\s\S]*?&gt;/i', $value) === 1;
    }

    private function sanitizarHtmlCopiloto(string $value): string
    {
        $html = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/^\s*[•\-*]\s*(?=<(?:ul|ol|li|p|div|strong|b|em|i|br)\b)/i', '', $html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/\s+on\w+=(["\']).*?\1/is', '', $html);
        $html = preg_replace('/\s+href=(["\'])\s*javascript:.*?\1/is', '', $html);
        $html = strip_tags((string) $html, '<ul><ol><li><p><br><strong><b><em><i>');
        $html = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', (string) $html);

        return trim((string) $html);
    }

}
