<?php
/**
 * EducaTudo - Serviço JWT para API
 * Geração e validação de tokens para autenticação da API dos Pais
 */

class JWTService
{
    private $secret;
    private $ttl;
    private $algorithm = 'HS256';

    public function __construct()
    {
        $secret = trim((string) env('JWT_SECRET', ''));
        if (strlen($secret) < 32) {
            throw new RuntimeException(
                'JWT_SECRET não configurado ou fraco. Defina um valor aleatório com pelo menos 32 caracteres no .env (ex.: openssl rand -hex 32).'
            );
        }
        $this->secret = $secret;
        $this->ttl = (int) (env('JWT_TTL', 86400)); // 24h default
    }

    /**
     * Codifica payload em Base64URL
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodifica Base64URL
     */
    private function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Gera JWT com user_id, role e exp
     */
    public function encode(array $payload): string
    {
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $this->ttl;

        $header = ['typ' => 'JWT', 'alg' => $this->algorithm];
        $headerEnc = $this->base64UrlEncode(json_encode($header));
        $payloadEnc = $this->base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $headerEnc . '.' . $payloadEnc, $this->secret, true);
        $signatureEnc = $this->base64UrlEncode($signature);

        return $headerEnc . '.' . $payloadEnc . '.' . $signatureEnc;
    }

    /**
     * Valida e decodifica JWT. Retorna payload ou null se inválido/expirado
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEnc, $payloadEnc, $signatureEnc] = $parts;
        $signature = hash_hmac('sha256', $headerEnc . '.' . $payloadEnc, $this->secret, true);
        $expectedEnc = $this->base64UrlEncode($signature);
        if (!hash_equals($expectedEnc, $signatureEnc)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payloadEnc), true);
        if (!is_array($payload) || empty($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }
}
