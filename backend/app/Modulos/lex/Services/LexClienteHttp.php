<?php

namespace App\Modulos\Lex\Services;

class LexClienteHttp implements LexClienteContrato
{
    public function solicitarToken(string $urlBase, string $clientId, string $clientSecret): array
    {
        $url = rtrim($urlBase, '/') . '/sso/connect/token';
        $resposta = $this->executar($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $token = trim((string) ($resposta['access_token'] ?? ''));
        if ($token === '') {
            throw new LexExcecaoHttp('A LEX não devolveu um token de acesso.', 0, 'autenticacao');
        }

        return [
            'access_token' => $token,
            'expires_in' => max(0, (int) ($resposta['expires_in'] ?? 0)),
            'token_type' => trim((string) ($resposta['token_type'] ?? 'Bearer')) ?: 'Bearer',
        ];
    }

    /**
     * Troca o code do login da pessoa. O token de máquina não passa por aqui.
     *
     * @return array{access_token:string,id_token:string,expires_in:int,token_type:string}
     */
    public function trocarCodigoAutorizacao(
        string $urlBase,
        string $clientId,
        string $clientSecret,
        string $code,
        string $redirectUri,
        string $codeVerifier
    ): array {
        $url = rtrim($urlBase, '/') . '/sso/connect/token';
        $resposta = $this->executar($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $codeVerifier,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $token = trim((string) ($resposta['access_token'] ?? ''));
        if ($token === '') {
            throw new LexExcecaoHttp('A LEX não devolveu o acesso da pessoa.', 0, 'autenticacao');
        }

        return [
            'access_token' => $token,
            'id_token' => trim((string) ($resposta['id_token'] ?? '')),
            'expires_in' => max(0, (int) ($resposta['expires_in'] ?? 0)),
            'token_type' => trim((string) ($resposta['token_type'] ?? 'Bearer')) ?: 'Bearer',
        ];
    }

    public function getJson(string $url, string $token, int $timeout = 30): array
    {
        return $this->executar($url, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $this->cabecalhosBearer($token),
            CURLOPT_TIMEOUT => $timeout,
        ]);
    }

    public function enviarJson(string $url, string $token, array $corpo, string $metodo = 'POST'): array
    {
        $metodo = strtoupper($metodo);
        $corpoJson = json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($corpoJson === false) {
            throw new LexExcecaoHttp('Não foi possível montar o corpo da requisição.', 0, 'http');
        }

        return $this->executar($url, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_POSTFIELDS => $corpoJson,
            CURLOPT_HTTPHEADER => array_merge($this->cabecalhosBearer($token), [
                'Content-Type: application/json',
            ]),
            CURLOPT_TIMEOUT => 20,
        ]);
    }

    /**
     * Confirma o sync. Se a LEX recusar POST, tenta PUT uma vez.
     *
     * @return array<string,mixed>
     */
    public function confirmar(string $urlBase, string $token, string $syncId, string $status): array
    {
        $url = rtrim($urlBase, '/') . '/sync/v1/status';
        $corpo = [
            'syncId' => $syncId,
            'status' => $status,
        ];
        try {
            return $this->enviarJson($url, $token, $corpo, 'POST');
        } catch (LexExcecaoHttp $e) {
            if ($e->httpStatus !== 405) {
                throw $e;
            }
            return $this->enviarJson($url, $token, $corpo, 'PUT');
        }
    }

    /**
     * @param array<int,mixed> $opcoes
     * @return array<string,mixed>
     */
    private function executar(string $url, array $opcoes): array
    {
        if (!function_exists('curl_init')) {
            throw new LexExcecaoHttp('A extensão cURL não está disponível.', 0, 'indisponivel');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new LexExcecaoHttp('Não foi possível iniciar a requisição à LEX.', 0, 'indisponivel');
        }

        $base = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        curl_setopt_array($ch, $base + $opcoes);

        $bruto = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $caminho = (string) (parse_url($url, PHP_URL_PATH) ?: '/');

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new LexExcecaoHttp('Tempo esgotado ao contatar a LEX.', 0, 'timeout');
        }
        if ($bruto === false || $errno !== 0) {
            throw new LexExcecaoHttp('A LEX está indisponível.', 0, 'indisponivel');
        }

        if ($status === 401) {
            throw new LexExcecaoHttp('A LEX recusou a autenticação (HTTP 401) em ' . $caminho . '.', 401, 'autenticacao');
        }
        if ($status === 403) {
            throw new LexExcecaoHttp('A LEX recusou o acesso (HTTP 403) em ' . $caminho . '.', 403, 'proibido');
        }
        if ($status === 405) {
            throw new LexExcecaoHttp('A LEX não aceita este método (HTTP 405) em ' . $caminho . '.', 405, 'http');
        }
        if ($status >= 500) {
            throw new LexExcecaoHttp('A LEX está indisponível (HTTP ' . $status . ').', $status, 'indisponivel');
        }
        if ($status < 200 || $status >= 300) {
            throw new LexExcecaoHttp('A LEX respondeu HTTP ' . $status . ' em ' . $caminho . '.', $status, 'http');
        }

        if (trim((string) $bruto) === '') {
            return [];
        }

        $json = json_decode((string) $bruto, true);
        if (!is_array($json)) {
            throw new LexExcecaoHttp('A resposta da LEX não é JSON.', $status, 'http');
        }

        return $json;
    }

    /**
     * @return list<string>
     */
    private function cabecalhosBearer(string $token): array
    {
        return [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];
    }
}
