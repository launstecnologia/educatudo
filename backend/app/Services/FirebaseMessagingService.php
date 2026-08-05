<?php
/** Envio de push Android pela API HTTP v1 do Firebase Cloud Messaging. */
require_once __DIR__ . '/../Core/Logger.php';

class FirebaseMessagingService
{
    private string $projectId;
    private string $credentialsPath;
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function __construct()
    {
        $this->projectId = trim((string) $this->config('FIREBASE_PROJECT_ID', ''));
        $this->credentialsPath = trim((string) $this->config('FIREBASE_SERVICE_ACCOUNT_PATH', ''));
        if ($this->credentialsPath !== '' && $this->credentialsPath[0] !== '/') {
            $this->credentialsPath = dirname(__DIR__, 2) . '/' . ltrim($this->credentialsPath, '/');
        }
    }

    public function isConfigured(): bool
    {
        return $this->projectId !== ''
            && $this->credentialsPath !== ''
            && is_readable($this->credentialsPath)
            && class_exists('Google\\Client');
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'invalid_token' => false, 'error' => 'Firebase não configurado'];
        }

        try {
            $accessToken = $this->getAccessToken();
            $stringData = [];
            foreach ($data as $key => $value) {
                if ($value !== null) {
                    $stringData[(string) $key] = (string) $value;
                }
            }
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'educatudo_updates',
                            'sound' => 'default',
                        ],
                    ],
                ],
            ];
            if ($stringData !== []) {
                $payload['message']['data'] = $stringData;
            }

            $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($this->projectId) . '/messages:send';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json; charset=utf-8',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError !== '') {
                return ['success' => false, 'invalid_token' => false, 'error' => $curlError];
            }
            $decoded = json_decode((string) $response, true);
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'invalid_token' => false, 'message_id' => $decoded['name'] ?? null];
            }

            $status = (string) ($decoded['error']['status'] ?? '');
            $message = (string) ($decoded['error']['message'] ?? ('FCM HTTP ' . $httpCode));
            $errorDetails = json_encode($decoded['error']['details'] ?? [], JSON_UNESCAPED_UNICODE);
            $invalid = in_array($status, ['NOT_FOUND'], true)
                || stripos((string) $errorDetails, 'UNREGISTERED') !== false
                || stripos((string) $errorDetails, 'SENDER_ID_MISMATCH') !== false
                || stripos($message, 'UNREGISTERED') !== false
                || stripos($message, 'registration token is not a valid') !== false;
            Logger::warning('FCM: envio recusado', ['http_code' => $httpCode, 'status' => $status, 'error' => $message], 'push');
            return ['success' => false, 'invalid_token' => $invalid, 'error' => $message];
        } catch (Throwable $e) {
            Logger::error('FCM: exceção no envio', ['exception' => $e], 'push');
            return ['success' => false, 'invalid_token' => false, 'error' => $e->getMessage()];
        }
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null && $this->accessTokenExpiresAt > time() + 60) {
            return $this->accessToken;
        }
        $client = new Google\Client();
        $client->setAuthConfig($this->credentialsPath);
        $client->setScopes(['https://www.googleapis.com/auth/firebase.messaging']);
        $result = $client->fetchAccessTokenWithAssertion();
        if (!empty($result['error']) || empty($result['access_token'])) {
            throw new RuntimeException((string) ($result['error_description'] ?? $result['error'] ?? 'Falha ao autenticar no Firebase'));
        }
        $this->accessToken = (string) $result['access_token'];
        $this->accessTokenExpiresAt = time() + (int) ($result['expires_in'] ?? 3600);
        return $this->accessToken;
    }

    private function config(string $key, string $default = '')
    {
        $value = function_exists('env') ? env($key, null) : null;
        return ($value !== null && $value !== '') ? $value : (getenv($key) ?: $default);
    }
}
