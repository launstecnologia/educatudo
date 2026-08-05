<?php
/**
 * EducaTudo - API de geração de imagens educacionais (OpenAI Images API)
 * POST /api/generate-image - Gera imagem a partir de prompt e salva em storage/images/
 */

require_once __DIR__ . '/../../Core/BaseController.php';

class GenerateImageController extends BaseController
{
    /**
     * POST /api/generate-image
     * Body JSON: { "prompt": "Educational diagram of ..." }
     * Returns: { "success": true, "image_url": "https://..." } ou { "success": false, "error": "..." }
     */
    public function generate()
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'error' => 'Método não permitido'], 405);
            return;
        }

        $input = $this->getJsonInput();
        $prompt = isset($input['prompt']) ? trim((string) $input['prompt']) : '';

        if ($prompt === '') {
            $this->json(['success' => false, 'error' => 'Campo "prompt" é obrigatório e não pode estar vazio'], 400);
            return;
        }

        $apiKey = $this->getOpenAiApiKey();
        if ($apiKey === '') {
            $this->json(['success' => false, 'error' => 'OPENAI_API_KEY não configurada'], 500);
            return;
        }

        $storageDir = $this->getStorageImagesPath();
        if (!is_dir($storageDir)) {
            if (!@mkdir($storageDir, 0755, true)) {
                $this->json(['success' => false, 'error' => 'Não foi possível criar o diretório de imagens'], 500);
                return;
            }
        }
        if (!is_writable($storageDir)) {
            $this->json(['success' => false, 'error' => 'Diretório de imagens não é gravável'], 500);
            return;
        }

        // gpt-image-1 retorna base64 por padrão; response_format não é suportado nesse modelo
        $payload = [
            'model' => 'gpt-image-1',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
        ];

        $ch = curl_init('https://api.openai.com/v1/images/generations');
        if ($ch === false) {
            $this->json(['success' => false, 'error' => 'Falha ao inicializar cURL'], 500);
            return;
        }

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
            $this->json(['success' => false, 'error' => 'Erro de conexão: ' . $curlError], 500);
            return;
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $message = 'Image generation failed';
            if (is_array($data) && isset($data['error']['message'])) {
                $message = $data['error']['message'];
            }
            $this->json(['success' => false, 'error' => $message], $httpCode >= 400 ? $httpCode : 500);
            return;
        }

        $b64 = null;
        if (isset($data['data'][0]['b64_json'])) {
            $b64 = $data['data'][0]['b64_json'];
        }

        if ($b64 === null || $b64 === '') {
            $this->json(['success' => false, 'error' => 'Resposta da API sem imagem base64'], 500);
            return;
        }

        $imageBinary = base64_decode($b64, true);
        if ($imageBinary === false || strlen($imageBinary) === 0) {
            $this->json(['success' => false, 'error' => 'Falha ao decodificar imagem base64'], 500);
            return;
        }

        $filename = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
        $filepath = $storageDir . '/' . $filename;

        if (file_put_contents($filepath, $imageBinary) === false) {
            $this->json(['success' => false, 'error' => 'Falha ao salvar imagem no disco'], 500);
            return;
        }

        $baseUrl = defined('URL') ? rtrim(URL, '/') : '';
        $imageUrl = $baseUrl . '/storage/images/' . $filename;

        $this->json([
            'success' => true,
            'image_url' => $imageUrl,
        ]);
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getOpenAiApiKey(): string
    {
        $key = getenv('OPENAI_API_KEY');
        if ($key !== false && $key !== null && $key !== '') {
            return trim((string) $key);
        }

        $envPath = defined('ENV_FILE_PATH') ? ENV_FILE_PATH : (__DIR__ . '/../../../.env');
        $paths = [$envPath, __DIR__ . '/../../../.env'];
        foreach ($paths as $p) {
            if (!is_file($p)) {
                continue;
            }
            $lines = @file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, 'OPENAI_API_KEY=') !== 0) {
                    continue;
                }
                $value = trim(substr($line, strlen('OPENAI_API_KEY=')));
                if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                    $value = substr($value, 1, -1);
                }
                return $value !== '' ? $value : '';
            }
        }
        return '';
    }

    private function getStorageImagesPath(): string
    {
        return __DIR__ . '/../../../storage/images';
    }
}
