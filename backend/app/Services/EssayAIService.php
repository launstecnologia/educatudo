<?php
/**
 * EducaTudo - Essay Correction AI Service
 * Redação Configurável - correção por IA usando prompt configurável
 */

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Logger.php';
require_once __DIR__ . '/OpenAIService.php';
require_once __DIR__ . '/../Helpers/EssayTextStructureHelper.php';

class EssayAIService
{
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1';
    private $envCache = null;

    public function __construct()
    {
        $apiKey = null;
        if (class_exists('Database', false)) {
            try {
                $db = Database::getInstance();
                $config = $db->fetch(
                    "SELECT config_value FROM config_layout WHERE config_key = ?",
                    ['openai_api_key']
                );
                if ($config && !empty(trim($config['config_value'] ?? ''))) {
                    $apiKey = trim($config['config_value']);
                }
            } catch (Exception $e) {
                // ignore
            }
        }
        if (empty($apiKey)) {
            $config = @require __DIR__ . '/../../config/app.php';
            $apiKey = $config['ai']['openai_api_key'] ?? null;
        }
        if (empty($apiKey)) {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    if (strpos(trim($line), 'OPENAI_API_KEY=') === 0) {
                        $apiKey = trim(substr($line, strlen('OPENAI_API_KEY=')), " \t\"'");
                        break;
                    }
                }
            }
        }
        $this->apiKey = $apiKey;
    }

    /**
     * Correct essay using configured prompt and return structured feedback (ENEM-style).
     * Returns per-competency score + feedback, general comments, and suggestions for improvement.
     *
     * @param string $essayText Raw essay text
     * @param string $promptText System/user prompt from essay_prompts
     * @param array $criteria List of criterion rows (name, slug, max_score, description)
     * @return array ['feedback_text' => string, 'suggestions_text' => string, 'grades' => [slug => score|array], 'grades_json' => [slug => {score, feedback}], 'total_score' => float, 'raw_response' => string]
     */
    public function correctEssay($essayText, $promptText, array $criteria = [])
    {
        $criteriaDescription = $this->formatCriteriaForPrompt($criteria);
        $userContent = $promptText . "\n\n---\nCritérios de correção (nome, slug, pontuação máxima):\n" . $criteriaDescription . "\n\n---\nRedação do aluno:\n\n" . $essayText;
        $userContent .= "\n\n---\nRetorne um ÚNICO JSON válido, sem markdown, com as chaves:\n";
        $userContent .= "- \"feedback\": texto de comentários gerais ao aluno (Comentários Gerais).\n";
        $userContent .= "- \"suggestions\": texto com sugestões de melhoria (Sugestões de Melhoria).\n";
        $userContent .= "- \"competencies\": objeto em que cada chave é o slug do critério e o valor é { \"score\": número (0 até máx do critério), \"feedback\": \"explicação detalhada para esta competência\" }.\n";
        $userContent .= "- \"total_score\": soma das notas de todas as competências.\n";
        $userContent .= "Nada além do JSON.";

        $payload = [
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'Você é um corretor de redações. Para cada critério informado, atribua nota (0 até o máximo do critério) e escreva um feedback detalhado. Retorne apenas JSON válido, sem markdown ou texto extra.'],
                ['role' => 'user', 'content' => $userContent]
            ],
            'temperature' => 0.3
        ];

        $response = $this->request($payload);
        $rawContent = $response['content'] ?? '';
        $parsed = $this->parseCorrectionResponse($rawContent, $criteria);
        $parsed['raw_response'] = $rawContent;
        return $parsed;
    }

    public function correctStructuredEssay(array $textStructure, array $criteria = [], ?string $title = null, ?string $fallbackPromptText = null)
    {
        $promptText = trim((string) ($fallbackPromptText ?? ''));
        if ($promptText === '') {
            throw new Exception('Prompt de correção não configurado para esta banca.');
        }

        $plainText = EssayTextStructureHelper::toPlainText($textStructure);
        if ($title !== null && trim($title) !== '') {
            $plainText = 'Tema/proposta: ' . trim($title) . "\n\n" . $plainText;
        }

        if ($this->shouldUseExternalCorrectionApi()) {
            $config = $this->getStructuredCorrectionConfig();
            if ($config !== null) {
                Logger::info('EssayAIService using external structured correction API.', [
                    'url' => (string) ($config['url'] ?? ''),
                    'has_title' => $title !== null && trim($title) !== '',
                    'paragraphs' => count($textStructure),
                ], 'essays');

                $payload = ['text_structure' => $textStructure];
                if ($title !== null && trim($title) !== '') {
                    $payload['title'] = trim($title);
                }

                $response = $this->requestStructuredCorrection($config, $payload);
                return $this->parseStructuredCorrectionResponse($response, $criteria);
            }
        }

        Logger::info('EssayAIService using local OpenAI correction with configured prompt.', [
            'paragraphs' => count($textStructure),
            'criteria_count' => count($criteria),
        ], 'essays');

        return $this->correctEssay($plainText, $promptText, $criteria);
    }

    private function shouldUseExternalCorrectionApi(): bool
    {
        $flag = $this->getEnvValue(['ESSAY_CORRECTION_USE_EXTERNAL_API']);
        if ($flag === null || $flag === '') {
            return false;
        }

        return in_array(strtolower(trim((string) $flag)), ['1', 'true', 'yes', 'on'], true);
    }

    private function formatCriteriaForPrompt(array $criteria)
    {
        $lines = [];
        foreach ($criteria as $c) {
            $name = $c['name'] ?? '';
            $slug = $c['slug'] ?? '';
            $max = $c['max_score'] ?? 200;
            $lines[] = "- {$name} (slug: {$slug}, máx: {$max})";
        }
        return implode("\n", $lines);
    }

    private function parseCorrectionResponse($rawContent, array $criteria)
    {
        $feedback = '';
        $suggestions = '';
        $grades = [];
        $gradesJson = [];
        $totalScore = null;
        $text = trim($rawContent);
        if (preg_match('/\{(.*)\}/s', $text, $m)) {
            $json = $m[0];
            $decoded = @json_decode($json, true);
            if (is_array($decoded)) {
                $feedback = $decoded['feedback'] ?? $decoded['feedback_text'] ?? $decoded['comentarios_gerais'] ?? '';
                $suggestions = $decoded['suggestions'] ?? $decoded['suggestions_text'] ?? $decoded['sugestoes_melhoria'] ?? '';
                $competencies = $decoded['competencies'] ?? $decoded['grades'] ?? [];
                $totalScore = isset($decoded['total_score']) ? (float) $decoded['total_score'] : null;

                foreach ($criteria as $c) {
                    $slug = $c['slug'] ?? '';
                    $max = (float)($c['max_score'] ?? 200);
                    $val = $competencies[$slug] ?? null;
                    if (is_array($val)) {
                        $score = isset($val['score']) ? (float) $val['score'] : (float)($val['nota'] ?? 0);
                        $fb = $val['feedback'] ?? $val['explicacao'] ?? '';
                        $grades[$slug] = $score;
                        $gradesJson[$slug] = ['score' => $score, 'feedback' => $fb];
                    } elseif (is_numeric($val)) {
                        $grades[$slug] = (float) $val;
                        $gradesJson[$slug] = ['score' => (float) $val, 'feedback' => ''];
                    }
                }
                // Se não há critérios cadastrados, usar todas as competências retornadas pela IA
                if (empty($gradesJson) && !empty($competencies) && is_array($competencies)) {
                    foreach ($competencies as $slug => $val) {
                        if (!is_string($slug)) continue;
                        $max = 200;
                        if (is_array($val)) {
                            $score = isset($val['score']) ? (float) $val['score'] : (float)($val['nota'] ?? 0);
                            $fb = $val['feedback'] ?? $val['explicacao'] ?? '';
                            $grades[$slug] = $score;
                            $gradesJson[$slug] = ['score' => $score, 'feedback' => $fb];
                        } elseif (is_numeric($val)) {
                            $grades[$slug] = (float) $val;
                            $gradesJson[$slug] = ['score' => (float) $val, 'feedback' => ''];
                        }
                    }
                }
            }
        }
        if ($totalScore === null && !empty($grades)) {
            $totalScore = array_sum(array_values($grades));
        }
        return [
            'feedback_text' => $feedback,
            'suggestions_text' => $suggestions,
            'grades' => $grades,
            'grades_json' => $gradesJson,
            'total_score' => $totalScore
        ];
    }

    private function parseStructuredCorrectionResponse(array $response, array $criteria): array
    {
        $scores = is_array($response['scores'] ?? null) ? $response['scores'] : [];
        $grades = [];
        $gradesJson = [];

        foreach ($criteria as $index => $criterion) {
            $slug = (string) ($criterion['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $scoreItem = $scores[$index] ?? null;
            if (!is_array($scoreItem)) {
                continue;
            }

            $scoreValue = isset($scoreItem['score']) ? (float) $scoreItem['score'] : 0.0;
            $feedback = (string) ($scoreItem['feedback'] ?? '');
            $grades[$slug] = $scoreValue;
            $gradesJson[$slug] = [
                'score' => $scoreValue,
                'feedback' => $feedback,
            ];
        }

        if (empty($gradesJson) && !empty($scores)) {
            foreach ($scores as $index => $scoreItem) {
                if (!is_array($scoreItem)) {
                    continue;
                }
                $slug = 'competencia_' . ($index + 1);
                $scoreValue = isset($scoreItem['score']) ? (float) $scoreItem['score'] : 0.0;
                $feedback = (string) ($scoreItem['feedback'] ?? '');
                $grades[$slug] = $scoreValue;
                $gradesJson[$slug] = [
                    'score' => $scoreValue,
                    'feedback' => $feedback,
                ];
            }
        }

        return [
            'feedback_text' => (string) ($response['general_comment'] ?? ''),
            'suggestions_text' => '',
            'grades' => $grades,
            'grades_json' => $gradesJson,
            'total_score' => isset($response['total_score']) ? (float) $response['total_score'] : array_sum($grades),
            'raw_response' => $response,
        ];
    }

    private function getStructuredCorrectionConfig(): ?array
    {
        $apiKey = $this->getEnvValue(['ESSAY_CORRECTION_API_KEY', 'ESSAY_TRANSCRIBE_API_KEY', 'SUPABASE_TRANSCRIBE_API_KEY']);
        if (empty($apiKey)) {
            return null;
        }

        $url = $this->getEnvValue(['ESSAY_CORRECTION_API_URL']);
        if ($url === false || $url === '') {
            $baseUrl = $this->getEnvValue(['ESSAY_CORRECTION_API_BASE_URL']);
            if ($baseUrl === false || $baseUrl === '') {
                $baseUrl = $this->getEnvValue(['ESSAY_TRANSCRIBE_API_BASE_URL']);
            }
            if ($baseUrl === false || $baseUrl === '') {
                $transcribeUrl = $this->getEnvValue(['ESSAY_TRANSCRIBE_API_URL']);
                if ($transcribeUrl === false || $transcribeUrl === '') {
                    $transcribeUrl = $this->getEnvValue(['SUPABASE_TRANSCRIBE_URL']);
                }
                if ($transcribeUrl) {
                    $baseUrl = preg_replace('#/api-transcribe/?$#', '', $transcribeUrl);
                }
            }
            if ($baseUrl) {
                $url = rtrim($baseUrl, '/') . '/api-correct';
            }
        }
        if ($url === false || $url === '') {
            return null;
        }

        $url = $this->normalizeSupabaseFunctionUrl((string) $url, 'api-correct');

        return [
            'url' => $url,
            'api_key' => $apiKey,
        ];
    }

    private function requestStructuredCorrection(array $config, array $payload): array
    {
        $attempt = 0;
        $maxAttempts = 1;
        $lastError = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            $ch = curl_init($config['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $config['api_key'],
                    'User-Agent: EducaTudo-EssayCorrection/1.0',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_TIMEOUT => 180,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception('Falha ao chamar API de correção: ' . $error);
            }

            if ($httpCode >= 400) {
                $decodedError = json_decode((string) $response, true);
                $apiMessage = is_array($decodedError) ? (string) (($decodedError['error']['message'] ?? $decodedError['message'] ?? '')) : '';
                if ($httpCode === 429) {
                    Logger::error('EssayAIService external correction API returned HTTP 429.', [
                        'url' => (string) ($config['url'] ?? ''),
                        'attempts' => $attempt,
                        'body' => (string) $response,
                        'api_message' => $apiMessage,
                    ], 'essays');
                }
                $lastError = new Exception('API de correção retornou HTTP ' . $httpCode . ': ' . substr((string) $response, 0, 300));
                break;
            }

            $decoded = json_decode((string) $response, true);
            if (!is_array($decoded) || empty($decoded['success'])) {
                throw new Exception('Resposta inválida da API de correção.');
            }

            return $decoded;
        }

        if ($lastError instanceof Exception) {
            throw $lastError;
        }

        throw new Exception('Falha desconhecida ao chamar API de correção.');
    }

    private function request(array $payload)
    {
        if (empty($this->apiKey)) {
            throw new Exception('OPENAI_API_KEY não configurada para correção local de redação.');
        }
        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            Logger::error('EssayAIService request failed: ' . $err, ['payload_keys' => array_keys($payload)], 'essays');
            throw new Exception('Erro de conexão com o serviço de correção: ' . $err);
        }
        if ($httpCode >= 400) {
            Logger::error('EssayAIService HTTP error: ' . $httpCode, ['body' => substr($response, 0, 500)], 'essays');
            throw new Exception('Serviço de correção retornou erro HTTP ' . $httpCode);
        }
        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        // Registrar uso em llm_usage_log para relatório de custos
        $this->logUsageToLlmUsageLog($payload, $data);
        return ['content' => $content];
    }

    /**
     * Grava uso da correção de redação em llm_usage_log (custos LLM).
     */
    private function logUsageToLlmUsageLog(array $payload, array $data)
    {
        $usage = $data['usage'] ?? null;
        if (!$usage || !isset($usage['total_tokens'])) {
            return;
        }
        $model = $payload['model'] ?? 'gpt-4o';
        $promptTokens = (int)($usage['prompt_tokens'] ?? 0);
        $completionTokens = (int)($usage['completion_tokens'] ?? 0);
        $totalTokens = (int)($usage['total_tokens'] ?? $promptTokens + $completionTokens);
        if (!class_exists('App\Services\OpenAIService', false)) {
            require_once __DIR__ . '/OpenAIService.php';
        }
        $costUsd = \App\Services\OpenAIService::calculateCostEstimate($model, $promptTokens, $completionTokens);
        try {
            $openai = new \App\Services\OpenAIService();
            $openai->logUsageToDatabase($model, $promptTokens, $completionTokens, $totalTokens, $costUsd, 'correcao_redacao');
        } catch (\Exception $e) {
            Logger::error('EssayAIService logUsageToLlmUsageLog failed: ' . $e->getMessage(), [], 'essays');
        }
    }

    private function getEnvValue(array $keys)
    {
        $envValues = $this->readEnvFile();
        foreach ($keys as $key) {
            if (isset($envValues[$key]) && $envValues[$key] !== '') {
                return $envValues[$key];
            }
        }

        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function readEnvFile(): array
    {
        if (is_array($this->envCache)) {
            return $this->envCache;
        }

        $this->envCache = [];
        $envPath = __DIR__ . '/../../.env';
        if (!file_exists($envPath)) {
            return $this->envCache;
        }

        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $this->envCache[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $this->envCache;
    }

    private function normalizeSupabaseFunctionUrl(string $url, string $functionName): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        $pattern = '#^https://([a-z0-9-]+)\.supabase\.co/functions/v1/' . preg_quote($functionName, '#') . '/?$#i';
        if (preg_match($pattern, $url, $matches)) {
            return 'https://' . $matches[1] . '.functions.supabase.co/' . $functionName;
        }

        return $url;
    }
}
