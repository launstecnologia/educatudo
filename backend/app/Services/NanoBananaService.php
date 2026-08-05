<?php
/**
 * NanoBananaService — Geração de imagens via Google Gemini (Nano Banana 2)
 *
 * Usa o endpoint generateContent com responseModalities: ["TEXT", "IMAGE"]
 * para gerar imagens educacionais a partir de prompts textuais.
 */

class NanoBananaService
{
    private $apiKey;
    private $model;
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private $logFile;

    public function __construct()
    {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $this->logFile = $logDir . '/exam_images.log';

        $this->apiKey = $this->resolveApiKey();
        $this->model = 'gemini-3.1-flash-image-preview';

        $this->imageLog("NanoBanana init | model={$this->model} | key=" . (empty($this->apiKey) ? 'VAZIA' : 'OK (' . substr($this->apiKey, 0, 10) . '...)'));
    }

    private function imageLog(string $msg): void
    {
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($this->logFile, "[{$timestamp}] [NanoBanana] {$msg}\n", FILE_APPEND | LOCK_EX);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Gera uma imagem a partir de um prompt textual.
     * Retorna array com imagem_bytes, mime_type e texto, ou null em erro.
     */
    public function gerarImagem(string $prompt): ?array
    {
        if (!$this->isConfigured()) {
            $this->imageLog("ERRO: API key não configurada");
            return null;
        }

        $this->imageLog("gerarImagem: prompt=" . mb_substr($prompt, 0, 150) . "...");

        $url = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        $startTime = microtime(true);
        $response = $this->request($url, $payload);
        $elapsed = round((microtime(true) - $startTime) * 1000);

        if (!$response) {
            $this->imageLog("ERRO: request falhou após {$elapsed}ms");
            return null;
        }

        $this->imageLog("Resposta recebida em {$elapsed}ms");

        $resultado = [
            'imagem_bytes' => null,
            'mime_type'    => null,
            'texto'        => null,
        ];

        $candidates = $response['candidates'] ?? [];
        $this->imageLog("Candidates: " . count($candidates));

        if (empty($candidates)) {
            $this->imageLog("ERRO: sem candidates na resposta. Response keys: " . implode(', ', array_keys($response)));
            if (isset($response['error'])) {
                $this->imageLog("ERRO API: " . json_encode($response['error'], JSON_UNESCAPED_UNICODE));
            }
            if (isset($response['promptFeedback'])) {
                $this->imageLog("promptFeedback: " . json_encode($response['promptFeedback'], JSON_UNESCAPED_UNICODE));
            }
            return null;
        }

        $parts = $candidates[0]['content']['parts'] ?? [];
        $this->imageLog("Parts na resposta: " . count($parts));

        $finishReason = $candidates[0]['finishReason'] ?? 'desconhecido';
        $this->imageLog("finishReason: {$finishReason}");

        foreach ($parts as $i => $part) {
            if (isset($part['inlineData'])) {
                $resultado['imagem_bytes'] = base64_decode($part['inlineData']['data']);
                $resultado['mime_type']    = $part['inlineData']['mimeType'];
                $this->imageLog("Part[{$i}]: IMAGEM | mime={$resultado['mime_type']} | bytes=" . strlen($resultado['imagem_bytes']));
            }
            if (isset($part['text'])) {
                $resultado['texto'] = $part['text'];
                $this->imageLog("Part[{$i}]: TEXTO | " . mb_substr($part['text'], 0, 150));
            }
        }

        if (empty($resultado['imagem_bytes'])) {
            $this->imageLog("ERRO: resposta sem imagem (nenhum inlineData encontrado)");
            return null;
        }

        $this->imageLog("SUCESSO: imagem gerada | {$resultado['mime_type']} | " . strlen($resultado['imagem_bytes']) . " bytes");
        return $resultado;
    }

    private function request(string $url, array $payload): ?array
    {
        $safeUrl = preg_replace('/key=[^&]+/', 'key=***', $url);
        $this->imageLog("HTTP POST {$safeUrl}");

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->imageLog("CURL ERROR: {$error}");
            return null;
        }

        $this->imageLog("HTTP {$httpCode} | response_size=" . strlen($response) . " bytes");

        if ($httpCode !== 200) {
            $this->imageLog("ERRO HTTP {$httpCode}: " . mb_substr($response, 0, 500));
            return null;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->imageLog("JSON PARSE ERROR: " . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /**
     * Resolve API key: config_layout (DB) → .env NANOBANANA_API_KEY
     */
    private function resolveApiKey(): string
    {
        try {
            if (!class_exists('\\Database')) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            $db = \Database::getInstance();
            $row = $db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['nanobanana_api_key']
            );
            $val = trim($row['config_value'] ?? '');
            if ($val !== '') {
                return $val;
            }
        } catch (\Exception $e) {
            error_log("NanoBanana: erro ao buscar key do DB: " . $e->getMessage());
        }

        $key = getenv('NANOBANANA_API_KEY');
        if ($key !== false && trim($key) !== '' && $key !== 'sua_chave_nanobanana_aqui') {
            return trim($key);
        }

        $envPath = defined('ENV_FILE_PATH') ? ENV_FILE_PATH : (__DIR__ . '/../../.env');
        $paths = [$envPath, __DIR__ . '/../../.env'];
        foreach ($paths as $p) {
            if (!is_file($p)) continue;
            $lines = @file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) continue;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, 'NANOBANANA_API_KEY=') !== 0) continue;
                $value = trim(substr($line, strlen('NANOBANANA_API_KEY=')));
                if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                    $value = substr($value, 1, -1);
                }
                if ($value !== '' && $value !== 'sua_chave_nanobanana_aqui') return $value;
            }
        }

        return '';
    }
}
