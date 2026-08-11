<?php

/** Cliente servidor-servidor da API de reconhecimento facial. */
class FacialRecognitionProviderService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) $this->config('FACIAL_API_BASE_URL', 'https://simple-face-scan.lovable.app'), '/');
        $this->apiKey = trim((string) $this->config('FACIAL_API_KEY', ''));
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    public function registerFace(string $externalKey, array $descriptor): array
    {
        return $this->request('POST', '/api/public/faces', [], [
            'name' => $externalKey,
            'descriptor' => $this->validateDescriptor($descriptor),
        ]);
    }

    public function recognize(array $descriptor): array
    {
        return $this->request('POST', '/api/public/recognize', [], [
            'descriptor' => $this->validateDescriptor($descriptor),
            'threshold' => 0.55,
            'cooldown_ms' => 60000,
            'register' => true,
        ]);
    }

    public function deleteFaces(string $externalKey): array
    {
        return $this->request('DELETE', '/api/public/faces', ['name' => $externalKey]);
    }

    private function validateDescriptor(array $descriptor): array
    {
        if (count($descriptor) !== 128) {
            throw new InvalidArgumentException('O descritor facial precisa ter 128 posições.');
        }
        $clean = [];
        foreach ($descriptor as $value) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException('Descritor facial inválido.');
            }
            $number = (float) $value;
            if (!is_finite($number) || $number < -10 || $number > 10) {
                throw new InvalidArgumentException('Descritor facial fora do intervalo permitido.');
            }
            $clean[] = $number;
        }
        return $clean;
    }

    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('API facial não configurada no servidor.');
        }
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $ch = curl_init($url);
        $headers = ['Accept: application/json', 'x-api-key: ' . $this->apiKey];
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($body !== null) {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $options[CURLOPT_POSTFIELDS] = $encoded;
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new RuntimeException('Não foi possível conectar ao serviço facial.');
        }
        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            $message = is_array($decoded) ? (string) ($decoded['error'] ?? $decoded['message'] ?? '') : '';
            throw new RuntimeException($message !== '' ? mb_substr($message, 0, 240) : 'Serviço facial recusou a solicitação.');
        }
        return $decoded;
    }

    private function config(string $key, string $default = '')
    {
        $value = function_exists('env') ? env($key, null) : null;
        return ($value !== null && $value !== '') ? $value : (getenv($key) ?: $default);
    }
}
