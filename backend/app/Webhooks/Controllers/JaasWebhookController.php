<?php

namespace App\Webhooks\Controllers;


use App\Core\BaseController;
use App\Models\System\DevSetting;
use App\Models\Education\OnlineClass;

if (!class_exists(__NAMESPACE__ . '\\JaasWebhookController', false)) {
class JaasWebhookController extends BaseController
{
    // Sem segredo de bearer nem de assinatura, a rota recusa qualquer requisição.

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
        if ($preAuthenticatedLink === '' || !$this->linkDeGravacaoPermitido($preAuthenticatedLink)) {
            $this->json(['success' => false, 'error' => 'Link de gravação não permitido'], 422);
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
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => 'Falha ao salvar gravação: ' . $e->getMessage()], 500);
            return;
        }

        $this->json(['success' => true, 'aula_id' => $aulaId]);
    }

    private function isAuthorized(string $rawBody): bool
    {
        $expected = $this->resolveWebhookSecret();
        $signingSecret = $this->resolveSigningSecret();
        if ($expected === '' && $signingSecret === '') {
            return false;
        }

        if ($expected !== '') {
            $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
            if ($authorization !== '') {
                $received = preg_replace('/^Bearer\s+/i', '', $authorization);
                if (hash_equals($expected, (string) $received) || hash_equals('Bearer ' . $expected, $authorization)) {
                    return true;
                }
            }
        }

        $signatureHeader = trim((string) ($_SERVER['HTTP_X_JAAS_SIGNATURE'] ?? ''));
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

        if ($timestamp === '' || $signature === '' || !preg_match('/^[0-9]{10,13}$/', $timestamp)) {
            return false;
        }

        $momento = (int) $timestamp;
        if ($momento > 9999999999) {
            $momento = intdiv($momento, 1000);
        }
        if (abs(time() - $momento) > 300) {
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
            throw new \RuntimeException('Não foi possível criar pasta de gravação.');
        }

        $relativePath = $relativeDir . '/jaas_recording_' . $sessionId . '.mp4';
        $absolutePath = __DIR__ . '/../../../storage/' . $relativePath;

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL é necessário para baixar a gravação.');
        }

        $urlAtual = $url;
        $baixou = false;
        for ($salto = 0; $salto < 4; $salto++) {
            if (!$this->linkDeGravacaoPermitido($urlAtual)) {
                @unlink($absolutePath);
                throw new \RuntimeException('Link de gravação não permitido.');
            }

            $write = @fopen($absolutePath, 'wb');
            if (!$write) {
                throw new \RuntimeException('Não foi possível criar o arquivo local da gravação.');
            }

            $ch = curl_init($urlAtual);
            $opcoesCurl = [
                CURLOPT_FILE => $write,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 600,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];
            if (defined('CURLPROTO_HTTPS')) {
                $opcoesCurl[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            curl_setopt_array($ch, $opcoesCurl);
            $destino = $this->destinoFixoDoLink($urlAtual);
            if ($destino === null) {
                curl_close($ch);
                fclose($write);
                @unlink($absolutePath);
                throw new \RuntimeException('Link de gravação não permitido.');
            }
            curl_setopt($ch, CURLOPT_RESOLVE, $destino);

            $ok = curl_exec($ch);
            $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $redirecionamento = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
            fclose($write);

            if (!$ok) {
                @unlink($absolutePath);
                throw new \RuntimeException('Download via cURL falhou.');
            }

            if ($codigo >= 300 && $codigo < 400 && $redirecionamento !== '') {
                @unlink($absolutePath);
                $urlAtual = $redirecionamento;
                continue;
            }

            if ($codigo !== 200) {
                @unlink($absolutePath);
                throw new \RuntimeException('Download da gravação recusado pelo servidor de origem.');
            }

            $baixou = true;
            break;
        }

        if (!$baixou) {
            @unlink($absolutePath);
            throw new \RuntimeException('Download da gravação excedeu os redirecionamentos permitidos.');
        }

        if (!is_file($absolutePath) || filesize($absolutePath) <= 0) {
            @unlink($absolutePath);
            throw new \RuntimeException('Download da gravação retornou arquivo vazio.');
        }

        return $relativePath;
    }

    private function linkDeGravacaoPermitido(string $url): bool
    {
        $partes = parse_url($url);
        if (!is_array($partes) || strtolower((string) ($partes['scheme'] ?? '')) !== 'https') {
            return false;
        }
        $host = strtolower((string) ($partes['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }
        if (isset($partes['user']) || isset($partes['pass'])) {
            return false;
        }

        return $this->destinoFixoDoLink($url) !== null;
    }

    /**
     * @return list<string>|null
     */
    private function destinoFixoDoLink(string $url): ?array
    {
        $partes = parse_url($url);
        if (!is_array($partes)) {
            return null;
        }
        $host = trim((string) ($partes['host'] ?? ''), '[]');
        $porta = isset($partes['port']) ? (int) $partes['port'] : 443;
        if ($host === '' || $porta < 1 || $porta > 65535) {
            return null;
        }

        $ips = $this->ipsPublicosDoHost($host);
        if ($ips === []) {
            return null;
        }

        $destino = [];
        foreach ($ips as $ip) {
            $endereco = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
            $destino[] = $host . ':' . $porta . ':' . $endereco;
        }

        return $destino;
    }

    /**
     * @return list<string>
     */
    private function ipsPublicosDoHost(string $host): array
    {
        $host = strtolower(trim($host, '[]'));
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->ipPublico($host) ? [$host] : [];
        }

        $encontrados = [];
        if (function_exists('dns_get_record')) {
            $registros = @dns_get_record($host, DNS_A + DNS_AAAA);
            if (is_array($registros)) {
                foreach ($registros as $registro) {
                    if (!empty($registro['ip'])) {
                        $encontrados[] = (string) $registro['ip'];
                    }
                    if (!empty($registro['ipv6'])) {
                        $encontrados[] = (string) $registro['ipv6'];
                    }
                }
            }
        }
        if ($encontrados === []) {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $encontrados = $v4;
            }
        }
        if ($encontrados === []) {
            return [];
        }

        $publicos = [];
        foreach ($encontrados as $ip) {
            if (!$this->ipPublico((string) $ip)) {
                return [];
            }
            $publicos[] = (string) $ip;
        }

        return array_values(array_unique($publicos));
    }

    private function ipPublico(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
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
        } catch (\Throwable $e) {
            return '';
        }
    }
}
}

if (!class_exists('JaasWebhookController', false) && class_exists(__NAMESPACE__ . '\\JaasWebhookController', false)) {
    class_alias(__NAMESPACE__ . '\\JaasWebhookController', 'JaasWebhookController');
}
