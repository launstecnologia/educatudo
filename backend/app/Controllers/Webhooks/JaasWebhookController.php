<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Models/Education/OnlineClass.php';
require_once __DIR__ . '/../../Models/System/DevSetting.php';

if (!class_exists('JaasWebhookController')) {
class JaasWebhookController extends BaseController
{
    // No default token: if JAAS_WEBHOOK_SECRET is not configured the endpoint rejects all requests.

    private $onlineClass;

    public function __construct()
    {
        parent::__construct();
        $this->onlineClass = new OnlineClass();
    }

    public function recording(): void
    {
        $rawBody = (string) file_get_contents('php://input');
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->json(['success' => false, 'error' => 'JSON inválido'], 400);
            return;
        }

        if (!$this->isAuthorized($rawBody)) {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 401);
            return;
        }

        $eventType = (string) ($payload['eventType'] ?? '');
        if ($eventType !== 'RECORDING_UPLOADED') {
            $this->json(['success' => true, 'ignored' => true, 'eventType' => $eventType]);
            return;
        }

        $aulaId = $this->onlineClass->extractAulaIdFromJaasPayload($payload);
        if ($aulaId <= 0) {
            $this->json(['success' => false, 'error' => 'Aula não identificada no payload'], 422);
            return;
        }

        $preAuthenticatedLink = trim((string) ($payload['data']['preAuthenticatedLink'] ?? $payload['preAuthenticatedLink'] ?? ''));
        if ($preAuthenticatedLink === '' || filter_var($preAuthenticatedLink, FILTER_VALIDATE_URL) === false) {
            $this->json(['success' => false, 'error' => 'Link de gravação ausente'], 422);
            return;
        }

        try {
            $relativePath = $this->downloadRecording($aulaId, $preAuthenticatedLink, (string) ($payload['sessionId'] ?? ''));
            $this->onlineClass->updateJaasRecording($aulaId, [
                'url' => $preAuthenticatedLink,
                'path' => $relativePath,
                'session_id' => (string) ($payload['sessionId'] ?? ''),
                'raw' => $payload,
            ]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => 'Falha ao salvar gravação: ' . $e->getMessage()], 500);
            return;
        }

        $this->json(['success' => true, 'aula_id' => $aulaId]);
    }

    private function isAuthorized(string $rawBody): bool
    {
        $expected = $this->resolveWebhookSecret();
        if ($expected === '') {
            return true;
        }

        $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if ($authorization !== '') {
            $received = preg_replace('/^Bearer\s+/i', '', $authorization);
            if (hash_equals($expected, (string) $received) || hash_equals('Bearer ' . $expected, $authorization)) {
                return true;
            }
        }

        $signatureHeader = trim((string) ($_SERVER['HTTP_X_JAAS_SIGNATURE'] ?? ''));
        $signingSecret = $this->resolveSigningSecret();
        if ($signatureHeader !== '' && $signingSecret !== '') {
            return $this->verifyJaasSignature($signatureHeader, $rawBody, $signingSecret);
        }

        return false;
    }

    private function verifyJaasSignature(string $header, string $rawBody, string $secret): bool
    {
        $timestamp = '';
        $signature = '';
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (strpos($part, 't=') === 0) {
                $timestamp = substr($part, 2);
            } elseif (strpos($part, 'v1=') === 0) {
                $signature = substr($part, 3);
            }
        }

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        $signedPayload = $timestamp . '.' . $rawBody;
        $computed = base64_encode(hash_hmac('sha256', $signedPayload, $secret, true));
        return hash_equals($computed, $signature);
    }

    private function downloadRecording(int $aulaId, string $url, string $sessionId): string
    {
        $sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sessionId);
        if ($sessionId === '') {
            $sessionId = date('YmdHis');
        }

        $relativeDir = 'aulas_online/jaas_recordings/' . $aulaId;
        $absoluteDir = __DIR__ . '/../../../storage/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Não foi possível criar pasta de gravação.');
        }

        $relativePath = $relativeDir . '/jaas_recording_' . $sessionId . '.mp4';
        $absolutePath = __DIR__ . '/../../../storage/' . $relativePath;

        $write = @fopen($absolutePath, 'wb');
        if (!$write) {
            throw new RuntimeException('Não foi possível criar o arquivo local da gravação.');
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $write,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 600,
                CURLOPT_FAILONERROR => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $ok = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            fclose($write);
            if (!$ok) {
                @unlink($absolutePath);
                throw new RuntimeException('Download via cURL falhou: ' . $error);
            }
        } else {
            $read = @fopen($url, 'rb');
            if (!$read) {
                fclose($write);
                throw new RuntimeException('Não foi possível abrir o link temporário da JaaS.');
            }
            stream_copy_to_stream($read, $write);
            fclose($read);
            fclose($write);
        }

        if (!is_file($absolutePath) || filesize($absolutePath) <= 0) {
            @unlink($absolutePath);
            throw new RuntimeException('Download da gravação retornou arquivo vazio.');
        }

        return $relativePath;
    }

    private function resolveWebhookSecret(): string
    {
        $value = $this->resolveDevSetting('jaas_webhook_secret');
        if ($value !== '') {
            return $value;
        }

        $env = trim((string) (function_exists('env') ? env('JAAS_WEBHOOK_SECRET', '') : ''));
        if ($env !== '') {
            return $env;
        }

        $raw = getenv('JAAS_WEBHOOK_SECRET');
        if ($raw !== false && trim((string) $raw) !== '') {
            return trim((string) $raw);
        }

        return '';
    }

    private function resolveSigningSecret(): string
    {
        $value = $this->resolveDevSetting('jaas_webhook_signing_secret');
        if ($value !== '') {
            return $value;
        }

        $env = trim((string) (function_exists('env') ? env('JAAS_WEBHOOK_SIGNING_SECRET', '') : ''));
        if ($env !== '') {
            return $env;
        }

        $raw = getenv('JAAS_WEBHOOK_SIGNING_SECRET');
        return $raw !== false ? trim((string) $raw) : '';
    }

    private function resolveDevSetting(string $key): string
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
