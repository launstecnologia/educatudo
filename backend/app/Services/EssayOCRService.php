<?php
/**
 * EducaTudo - Essay OCR Service
 * Redação Configurável - transcrição de imagem manuscrita para texto.
 * Pipeline: Supabase (api-transcribe) se configurado, senão Google Vision; depois formatação determinística + remoção de cabeçalho.
 */

require_once __DIR__ . '/../Core/Logger.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/OpenAIService.php';
require_once __DIR__ . '/../Helpers/EssayTextStructureHelper.php';

class EssayOCRService
{
    private $openAIService;

    public function __construct()
    {
        $this->openAIService = new \App\Services\OpenAIService();
    }

    public function extractStructuredFromImage($imageBase64, $mimeType = null): array
    {
        $imageBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $imageBase64);
        if (empty($imageBase64)) {
            throw new Exception('Imagem inválida ou vazia.');
        }

        $config = $this->getStructuredTranscribeConfig();
        if ($mimeType === null || $mimeType === '') {
            $mimeType = 'image/jpeg';
        }

        if ($config !== null) {
            try {
                $result = $this->requestStructuredTranscription($config, $imageBase64, $mimeType);
                $normalizedResult = $this->normalizeStructuredResult($result);
                if ($normalizedResult['raw_text'] !== '' || !empty($normalizedResult['text_structure'])) {
                    return $normalizedResult;
                }
            } catch (\Throwable $e) {
                Logger::warning('EssayOCRService structured transcription fallback: ' . $e->getMessage(), [], 'essays');
            }
        }

        $plainText = $this->extractPlainTextFromImage($imageBase64);
        $textStructure = EssayTextStructureHelper::buildFromPlainText($plainText);

        return $this->normalizeStructuredResult([
            'layout' => [
                'paper_style' => 'ruled',
                'paragraph_indent' => 28,
                'line_height' => 32,
            ],
            'text_structure' => $textStructure,
            'raw_text' => trim($plainText),
            'has_uncertain_words' => false,
        ]);
    }

    /**
     * @param string $imageBase64 Base64-encoded image content
     * @return string
     */
    public function extractTextFromImage($imageBase64)
    {
        $result = $this->extractStructuredFromImage($imageBase64);
        return (string) ($result['raw_text'] ?? '');
    }

    private function extractPlainTextFromImage($imageBase64)
    {
        $prompt = $this->getTranscriptionPrompt();

        try {
            set_time_limit(300);
            $text = $this->openAIService->analyzeImage($imageBase64, $prompt);
            if ($text === null || trim($text) === '') {
                throw new Exception('Nenhum texto foi identificado na imagem. Verifique se a imagem está legível.');
            }
            return $text;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $isQuota = (stripos($msg, 'quota') !== false || stripos($msg, 'billing') !== false || stripos($msg, 'limit') !== false);
            if ($isQuota) {
                Logger::error('EssayOCRService analyzeImage quota/limit: ' . $msg, ['exception' => $e], 'essays');
                throw $e;
            }
            Logger::warning('EssayOCRService analyzeImage failed, fallback: Vision + formatOCRText: ' . $msg, [], 'essays');
            try {
                $textoOriginal = $this->openAIService->transcreverComGoogleVision($imageBase64);
                if ($textoOriginal !== null && trim($textoOriginal) !== '') {
                    return $this->openAIService->formatOCRText($textoOriginal);
                }
            } catch (Exception $e2) {
                Logger::error('EssayOCRService extractTextFromImage fallback failed: ' . $e2->getMessage(), ['exception' => $e2], 'essays');
            }
            throw $e;
        }
    }

    private function getStructuredTranscribeConfig(): ?array
    {
        $apiKey = getenv('ESSAY_TRANSCRIBE_API_KEY');
        if ($apiKey === false || $apiKey === '') {
            $apiKey = getenv('SUPABASE_TRANSCRIBE_API_KEY');
        }
        if ($apiKey === false || $apiKey === '') {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $content = @file_get_contents($envPath);
                if ($content !== false) {
                    if (preg_match('/ESSAY_TRANSCRIBE_API_KEY\s*=\s*["\']?([^"\s\r\n]+)/', $content, $m)) {
                        $apiKey = trim($m[1], "\"'");
                    } elseif (preg_match('/SUPABASE_TRANSCRIBE_API_KEY\s*=\s*["\']?([^"\s\r\n]+)/', $content, $m)) {
                        $apiKey = trim($m[1], "\"'");
                    }
                }
            }
        }
        if (empty($apiKey)) {
            return null;
        }

        $url = getenv('ESSAY_TRANSCRIBE_API_URL');
        if ($url === false || $url === '') {
            $url = getenv('SUPABASE_TRANSCRIBE_URL');
        }
        if ($url === false || $url === '') {
            $url = 'https://bgumiziiyqfhinlfxwrf.supabase.co/functions/v1/api-transcribe';
        }

        return [
            'url' => $url,
            'api_key' => $apiKey,
        ];
    }

    private function requestStructuredTranscription(array $config, string $imageBase64, string $mimeType): array
    {
        $payload = json_encode([
            'image_base64' => $imageBase64,
            'mime_type' => $mimeType,
        ], JSON_UNESCAPED_SLASHES);

        $ch = curl_init($config['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'x-api-key: ' . $config['api_key'],
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 180,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('Falha ao chamar API de transcrição: ' . $error);
        }
        if ($httpCode >= 400) {
            throw new Exception('API de transcrição retornou HTTP ' . $httpCode . ': ' . substr((string) $response, 0, 300));
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new Exception('Resposta inválida da API de transcrição.');
        }

        if (array_key_exists('success', $decoded) && empty($decoded['success'])) {
            $message = (string) ($decoded['error'] ?? $decoded['message'] ?? 'API retornou falha na transcrição');
            throw new Exception($message);
        }

        $payload = $decoded;
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $payload = $decoded['data'];
        }

        $hasStructuredPayload = isset($payload['text_structure']) || isset($payload['raw_text']) || isset($payload['text']);
        if (!$hasStructuredPayload) {
            throw new Exception('Resposta inválida da API de transcrição.');
        }

        return $payload;
    }

    private function normalizeStructuredResult(array $result): array
    {
        $layout = is_array($result['layout'] ?? null) ? $result['layout'] : [];
        if (!isset($layout['paper_style'])) {
            $layout['paper_style'] = 'ruled';
        }
        if (!isset($layout['paragraph_indent'])) {
            $layout['paragraph_indent'] = 28;
        }
        if (!isset($layout['line_height'])) {
            $layout['line_height'] = 32;
        }

        $rawText = trim((string) ($result['raw_text'] ?? $result['text'] ?? ''));
        $textStructure = !empty($result['text_structure']) && is_array($result['text_structure'])
            ? EssayTextStructureHelper::normalizeStructure($result)
            : EssayTextStructureHelper::buildFromPlainText($rawText);

        if ($rawText === '' && !empty($textStructure)) {
            $rawText = trim(EssayTextStructureHelper::toPlainText($textStructure));
        }

        $numberedText = $this->buildNumberedTextFromStructure($textStructure);
        $textHtml = $this->buildTextHtmlFromStructure($textStructure, (int) $layout['paragraph_indent']);

        return [
            'success' => true,
            'layout' => $layout,
            'text_structure' => $textStructure,
            'raw_text' => $rawText,
            'numbered_text' => $numberedText,
            'text_html' => $textHtml,
            'has_uncertain_words' => !empty($result['has_uncertain_words']),
            'total_lines' => EssayTextStructureHelper::countLines($textStructure),
            'total_paragraphs' => count($textStructure),
        ];
    }

    private function buildNumberedTextFromStructure(array $textStructure): string
    {
        if (empty($textStructure)) {
            return '';
        }

        $numbered = [];
        foreach ($textStructure as $paragraphIndex => $paragraph) {
            $lines = (array) ($paragraph['lines'] ?? []);
            foreach ($lines as $line) {
                $lineNumber = (int) ($line['line_number'] ?? 0);
                $lineText = trim((string) ($line['text'] ?? ''));
                if ($lineText === '') {
                    continue;
                }
                $prefix = str_pad((string) max(1, $lineNumber), 2, ' ', STR_PAD_LEFT);
                $numbered[] = $prefix . '  ' . $lineText;
            }

            $isLastParagraph = $paragraphIndex === (count($textStructure) - 1);
            if (!$isLastParagraph) {
                $numbered[] = '';
            }
        }

        while (!empty($numbered) && trim((string) end($numbered)) === '') {
            array_pop($numbered);
        }

        return implode("\n", $numbered);
    }

    private function buildTextHtmlFromStructure(array $textStructure, int $paragraphIndent): string
    {
        if (empty($textStructure)) {
            return '';
        }

        $paragraphIndent = max(0, $paragraphIndent);
        $htmlParts = [];
        foreach ($textStructure as $paragraph) {
            $lines = (array) ($paragraph['lines'] ?? []);
            $lineTexts = [];
            foreach ($lines as $line) {
                $lineText = trim((string) ($line['text'] ?? ''));
                if ($lineText !== '') {
                    $lineTexts[] = $lineText;
                }
            }

            if (empty($lineTexts)) {
                continue;
            }

            $startLine = (int) ($paragraph['start_line'] ?? ($lines[0]['line_number'] ?? 0));
            $paragraphText = implode(' ', $lineTexts);
            $paragraphText = preg_replace("/[ \t]{2,}/u", " ", $paragraphText);

            $htmlParts[] = '<p data-start-line="' . max(1, $startLine) . '" style="margin:0 0 1em 0;text-indent:' . $paragraphIndent . 'px;text-align:justify;text-align-last:left;white-space:normal;">'
                . htmlspecialchars(trim((string) $paragraphText), ENT_QUOTES, 'UTF-8')
                . '</p>';
        }

        return implode('', $htmlParts);
    }

    /**
     * Get transcription prompt from config_layout (same key as standard essay).
     */
    private function getTranscriptionPrompt()
    {
        $default = 'Transcreva todo o texto presente nesta imagem. Retorne apenas o texto transcrito, sem comentários adicionais.';
        try {
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            $db = \Database::getInstance();
            $row = $db->fetch(
                'SELECT config_value FROM config_layout WHERE config_key = ?',
                ['prompt_transcrever_imagem']
            );
            if ($row && !empty(trim($row['config_value']))) {
                return trim($row['config_value']);
            }
        } catch (Exception $e) {
            // ignore
        }
        return $default;
    }

    /**
     * Extract text from image file path.
     *
     * @param string $filePath Absolute path to image file
     * @return string Extracted text
     */
    public function extractTextFromFilePath($filePath)
    {
        if (!is_readable($filePath)) {
            throw new Exception('Arquivo de imagem não encontrado ou não legível.');
        }
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception('Falha ao ler o arquivo de imagem.');
        }
        $base64 = base64_encode($content);
        return $this->extractTextFromImage($base64);
    }
}
