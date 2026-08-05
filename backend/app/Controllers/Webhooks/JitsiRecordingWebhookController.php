<?php
/**
 * Webhook do Jibri (Jitsi self-hosted) para gravações automáticas.
 *
 * Payload esperado:
 *   { "sala": "educatudo-aula-10-aula-teste", "url": "https://meet.launs.com.br/...", "token": "..." }
 *
 * O token é configurado em: Admin → Dev Settings → jitsi_webhook_token
 * URL por escola:  POST https://{tenant}.educatudo.com/api/webhook/gravacao-jitsi
 */

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Models/Education/OnlineClass.php';
require_once __DIR__ . '/../../Models/System/DevSetting.php';

if (!class_exists('JitsiRecordingWebhookController')) {
class JitsiRecordingWebhookController extends BaseController
{
    private OnlineClass $onlineClass;

    public function __construct()
    {
        parent::__construct();
        $this->onlineClass = new OnlineClass();
    }

    public function handle(): void
    {
        $rawBody = (string) file_get_contents('php://input');
        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            $this->json(['success' => false, 'error' => 'JSON inválido'], 400);
            return;
        }

        $sala  = trim((string) ($payload['sala']  ?? ''));
        $url   = trim((string) ($payload['url']   ?? ''));
        $token = trim((string) ($payload['token'] ?? ''));

        if ($sala === '' || $url === '' || $token === '') {
            $this->json(['success' => false, 'error' => 'Campos obrigatórios ausentes: sala, url, token'], 400);
            return;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->json(['success' => false, 'error' => 'URL de gravação inválida'], 400);
            return;
        }

        if (!$this->isTokenValid($token)) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 401);
            return;
        }

        $aulaId = $this->onlineClass->findAulaBySalaName($sala);
        if ($aulaId <= 0) {
            $this->json(['success' => false, 'error' => 'Aula não encontrada para a sala: ' . $sala], 404);
            return;
        }

        $this->onlineClass->updateJitsiRecording($aulaId, $url);

        $this->json(['success' => true, 'aula_id' => $aulaId]);
    }

    private function isTokenValid(string $received): bool
    {
        $expected = $this->resolveToken();
        if ($expected === '') {
            return false;
        }
        return hash_equals($expected, $received);
    }

    private function resolveToken(): string
    {
        try {
            $setting = new DevSetting();
            $val = trim((string) ($setting->get('jitsi_webhook_token') ?? ''));
            if ($val !== '') {
                return $val;
            }
        } catch (\Throwable $e) {}

        $env = trim((string) (getenv('JITSI_WEBHOOK_TOKEN') ?: ''));
        return $env;
    }
}
}
