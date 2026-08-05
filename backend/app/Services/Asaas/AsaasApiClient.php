<?php
/**
 * Cliente HTTP mínimo para API Asaas v3 (cobrança centralizada no Master).
 */
namespace App\Services\Asaas;

class AsaasApiClient
{
    private string $apiKey;
    private bool $sandbox;

    public function __construct(string $apiKey, bool $sandbox = true)
    {
        $this->apiKey = $apiKey;
        $this->sandbox = $sandbox;
    }

    public function baseUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://api.asaas.com/v3';
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>|null
     */
    public function request(string $method, string $path, ?array $body = null, ?array $query = null): ?array
    {
        $url = rtrim($this->baseUrl(), '/') . '/' . ltrim($path, '/');
        if ($query !== null && $query !== []) {
            $url .= '?' . http_build_query($query);
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        $headers = [
            'access_token: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: EducaTudo/1.0 (+https://master.educatudo.com)',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);
        if ($body !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        // DELETE (e alguns endpoints) podem retornar corpo vazio com 2xx/404 — não tratar como erro.
        if ($resp === false) {
            return ['_http_error' => true, '_http_code' => $code, '_curl_error' => $curlError];
        }
        if ($resp === '') {
            $okEmpty = ($code >= 200 && $code < 300) || $code === 404;
            return [
                '_http_code' => $code,
                '_http_error' => !$okEmpty,
                '_curl_error' => $curlError,
            ];
        }
        $decoded = json_decode($resp, true);
        if (!is_array($decoded)) {
            return ['_raw' => $resp, '_http_code' => $code, '_curl_error' => $curlError];
        }
        $decoded['_http_code'] = $code;
        if ($curlError !== '') {
            $decoded['_curl_error'] = $curlError;
        }
        return $decoded;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function createPayment(array $data): ?array
    {
        return $this->request('POST', 'payments', $data);
    }

    public function createCustomer(array $data): ?array
    {
        return $this->request('POST', 'customers', $data);
    }

    public function updateCustomer(string $id, array $data): ?array
    {
        return $this->request('POST', 'customers/' . rawurlencode($id), $data);
    }

    public function listCustomers(array $query): ?array
    {
        return $this->request('GET', 'customers', null, $query);
    }

    public function getPayment(string $paymentId): ?array
    {
        return $this->request('GET', 'payments/' . rawurlencode($paymentId), null);
    }

    public function getPixQrCode(string $paymentId): ?array
    {
        return $this->request('GET', 'payments/' . rawurlencode($paymentId) . '/pixQrCode', null);
    }

    /**
     * Cancela cobrança pendente no Asaas (DELETE /v3/payments/{id}).
     * HTTP 200 = cancelado; 404 = já inexistente (tratar como ok no job).
     */
    public function deletePayment(string $paymentId): ?array
    {
        return $this->request('DELETE', 'payments/' . rawurlencode($paymentId), null);
    }
}
