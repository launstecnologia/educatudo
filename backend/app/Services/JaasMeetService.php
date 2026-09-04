<?php

require_once __DIR__ . '/../Models/System/DevSetting.php';

if (!class_exists('JaasMeetService')) {
class JaasMeetService
{
    // Padrão: Jitsi self-hosted da Launs em modo aberto (sem JWT).
    // Para usar o JaaS (8x8) com JWT por usuário, basta configurar
    // jaas_app_id / jaas_api_key_id / jaas_private_key via Dev Settings ou env.
    private const DEFAULT_APP_ID = '';
    private const DEFAULT_API_KEY_ID = '';
    private const DEFAULT_BASE_URL = 'https://8x8.vc';
    private const DEFAULT_PUBLIC_BASE_URL = 'https://meet.launs.com.br';
    private const PRIVATE_KEY_FILE = __DIR__ . '/../../storage/keys/jaas_private_key.pk';

    private $appId;
    private $apiKeyId;
    private $privateKey;
    private $baseUrl;
    private $publicBaseUrl;

    public function __construct()
    {
        $this->appId = $this->resolveValue('jaas_app_id', 'JAAS_APP_ID', self::DEFAULT_APP_ID);
        $this->apiKeyId = $this->resolveValue('jaas_api_key_id', 'JAAS_API_KEY_ID', self::DEFAULT_API_KEY_ID);
        $this->privateKey = $this->normalizePrivateKey($this->resolveValue('jaas_private_key', 'JAAS_PRIVATE_KEY', ''));
        if ($this->privateKey === '') {
            $this->privateKey = $this->loadPrivateKeyFile();
        }
        $this->baseUrl = rtrim($this->resolveValue('jaas_base_url', 'JAAS_BASE_URL', self::DEFAULT_BASE_URL), '/');
        $this->publicBaseUrl = rtrim($this->resolveValue('jitsi_public_base_url', 'JITSI_PUBLIC_BASE_URL', self::DEFAULT_PUBLIC_BASE_URL), '/');
    }

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->apiKeyId !== '' && $this->privateKey !== '';
    }

    public function nomeSala(int $aulaId, string $title = '', string $roomKind = 'aula'): string
    {
        if ($aulaId <= 0) {
            return '';
        }

        return $this->roomName($aulaId, $title, $roomKind);
    }

    public function urlApiGravacoes(): string
    {
        return $this->publicBaseUrl . '/api/gravacoes.json';
    }

    public function baseMeetingUrl(int $aulaId, string $title = '', string $roomKind = 'aula'): string
    {
        if ($aulaId <= 0) {
            return '';
        }

        $roomName = $this->roomName($aulaId, $title, $roomKind);
        if ($this->appId !== '') {
            return $this->baseUrl . '/' . rawurlencode($this->appId) . '/' . rawurlencode($roomName);
        }

        return $this->publicBaseUrl . '/' . rawurlencode($roomName);
    }

    public function meetingUrl(int $aulaId, string $title, array $user, bool $moderator = false, string $roomKind = 'aula'): string
    {
        $base = $this->baseMeetingUrl($aulaId, $title, $roomKind);
        if ($base === '' || !$this->isConfigured()) {
            return $base;
        }

        $token = $this->jwt($aulaId, $title, $user, $moderator, $roomKind);
        return $base . '?jwt=' . rawurlencode($token);
    }

    private function jwt(int $aulaId, string $title, array $user, bool $moderator, string $roomKind = 'aula'): string
    {
        $now = time();
        $roomName = $this->roomName($aulaId, $title, $roomKind);
        $userName = trim((string) ($user['nome'] ?? $user['name'] ?? 'Usuário EducaTudo'));
        $userId = trim((string) ($user['id'] ?? ''));
        $email = trim((string) ($user['email'] ?? ''));

        if ($userName === '') {
            $userName = 'Usuário EducaTudo';
        }
        if ($userId === '') {
            $userId = 'educatudo-' . $aulaId . '-' . substr(sha1($userName . $email), 0, 12);
        }

        $payload = [
            'aud' => 'jitsi',
            'iss' => 'chat',
            'sub' => $this->appId,
            'room' => $roomName,
            'nbf' => $now - 60,
            'exp' => $now + 12 * 60 * 60,
            'context' => [
                'user' => [
                    'id' => $userId,
                    'name' => $userName,
                    'email' => $email,
                    'moderator' => $moderator,
                ],
                'features' => [
                    'recording' => $moderator,
                    'livestreaming' => false,
                    'transcription' => false,
                    'outbound-call' => false,
                ],
            ],
        ];

        return $this->encodeJwt(
            ['alg' => 'RS256', 'kid' => $this->apiKeyId, 'typ' => 'JWT'],
            $payload
        );
    }

    private function encodeJwt(array $header, array $payload): string
    {
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);
        $signature = '';

        if (!function_exists('openssl_sign') || !openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Não foi possível assinar o token JaaS. Verifique a Private Key.');
        }

        $segments[] = $this->base64UrlEncode($signature);
        return implode('.', $segments);
    }

    private function roomName(int $aulaId, string $title, string $roomKind = 'aula'): string
    {
        // $roomKind permite namespaces de sala distintos (ex.: 'avalive' para o AVA),
        // evitando colisao com o webhook de gravacao que resolve 'educatudo-aula-(\d+)'.
        $kind = preg_replace('/[^a-z0-9]+/', '', strtolower($roomKind)) ?: 'aula';
        $slug = $this->slug($title);
        return 'educatudo-' . $kind . '-' . $aulaId . ($slug !== '' ? '-' . $slug : '');
    }

    private function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string) $value, '-');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function normalizePrivateKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return str_replace('\\n', "\n", $value);
    }

    private function resolveValue(string $devKey, string $envKey, string $default): string
    {
        $devValue = $this->resolveFromDevSettings($devKey);
        if ($devValue !== '') {
            return $devValue;
        }

        $envValue = trim((string) (function_exists('env') ? env($envKey, '') : ''));
        if ($envValue !== '') {
            return $envValue;
        }

        $raw = getenv($envKey);
        if ($raw !== false && trim((string) $raw) !== '') {
            return trim((string) $raw);
        }

        return $default;
    }

    private function loadPrivateKeyFile(): string
    {
        $path = self::PRIVATE_KEY_FILE;
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        return $this->normalizePrivateKey((string) file_get_contents($path));
    }

    private function resolveFromDevSettings(string $key): string
    {
        try {
            $model = new DevSetting();
            return trim((string) ($model->get($key) ?? ''));
        } catch (Throwable $e) {
            return '';
        }
    }
}
}
