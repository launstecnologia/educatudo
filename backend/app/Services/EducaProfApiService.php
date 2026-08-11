<?php
/**
 * Serviço de integração com a API EducaProf.
 */

if (!class_exists('EducaProfApiService')) {
class EducaProfApiService
{
    private string $baseUrl;
    private string $apiKey;
    private string $schoolSlug;

    public function __construct()
    {
        $this->baseUrl = rtrim($this->resolveValue('educaprof_api_base_url', 'EDUCAPROF_API_BASE_URL', 'https://zjgnpluinswcttozhbsa.supabase.co/functions/v1/api'), '/');
        $this->apiKey = $this->resolveValue('educaprof_api_key', 'EDUCAPROF_API_KEY', '');
        $this->schoolSlug = $this->resolveSchoolSlug();
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    public function getSchoolSlug(): string
    {
        return $this->schoolSlug;
    }

    public function listAgents(): array
    {
        return $this->request('GET', '/list_agents');
    }

    public function createAgent(array $payload): array
    {
        return $this->request('POST', '/create_agent', $payload);
    }

    public function updateAgent(array $payload): array
    {
        return $this->request('POST', '/update_agent', $payload);
    }

    public function ingest(array $payload): array
    {
        return $this->request('POST', '/ingest', $payload);
    }

    public function chat(array $payload): array
    {
        return $this->request('POST', '/chat', $payload);
    }

    public function usage(int $limit = 50): array
    {
        return $this->request('GET', '/usage', ['limit' => max(1, min(1000, $limit))]);
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('EducaProf não configurado para esta escola.');
        }

        $url = $this->baseUrl . $endpoint;
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new Exception('Falha ao inicializar conexão com EducaProf.');
        }

        $headers = [
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($method !== 'GET') {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            $opts[CURLOPT_POSTFIELDS] = $json === false ? '{}' : $json;
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            throw new Exception('Falha de conexão com EducaProf: ' . $error);
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = is_array($decoded) ? (string)($decoded['error'] ?? $decoded['message'] ?? 'Erro na API EducaProf') : 'Erro na API EducaProf';
            throw new Exception($msg . " (HTTP {$httpCode})");
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveSchoolSlug(): string
    {
        $slug = trim($this->resolveValue('educaprof_school_slug', 'EDUCAPROF_SCHOOL_SLUG', ''));
        if ($slug !== '') {
            return $slug;
        }
        return trim((string)(function_exists('env') ? env('SCHOOL_CODE', '') : ''));
    }

    private function resolveValue(string $layoutKey, string $envKey, string $default): string
    {
        $layout = $this->resolveFromLayout($layoutKey);
        if ($layout !== '') {
            return $layout;
        }

        $master = $this->resolveFromMasterLayout($layoutKey);
        if ($master !== '') {
            return $master;
        }

        $env = trim((string)(function_exists('env') ? env($envKey, '') : ''));
        if ($env !== '') {
            return $env;
        }

        return $default;
    }

    private function resolveFromLayout(string $key): string
    {
        try {
            if (!class_exists('LayoutHelper')) {
                require_once __DIR__ . '/../Core/LayoutHelper.php';
            }
            return trim((string) LayoutHelper::get($key, ''));
        } catch (Throwable $e) {
            return '';
        }
    }

    private function resolveFromMasterLayout(string $key): string
    {
        try {
            if (!class_exists('Database')) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            $schoolSlug = trim((string)(function_exists('env') ? env('SCHOOL_CODE', '') : ''));
            if ($schoolSlug === '') {
                return '';
            }

            $db = Database::getInstance();
            $escola = $db->fetch("SELECT id FROM escolas WHERE slug = :slug LIMIT 1", ['slug' => $schoolSlug]);
            if (!$escola || empty($escola['id'])) {
                return '';
            }

            $cfg = $db->fetch(
                "SELECT config_value FROM config_escolas_layout WHERE escola_id = :escola_id AND config_key = :config_key LIMIT 1",
                ['escola_id' => (int)$escola['id'], 'config_key' => $key]
            );
            return trim((string)($cfg['config_value'] ?? ''));
        } catch (Throwable $e) {
            return '';
        }
    }
}
}

