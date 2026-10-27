<?php

namespace App\Modulos\Lex\Services;

interface LexClienteContrato
{
    /**
     * @return array{access_token:string,expires_in:int,token_type:string}
     */
    public function solicitarToken(string $urlBase, string $clientId, string $clientSecret): array;

    /**
     * @return array<string,mixed>
     */
    public function getJson(string $url, string $token, int $timeout = 30): array;

    /**
     * @param array<string,mixed> $corpo
     * @return array<string,mixed>
     */
    public function enviarJson(string $url, string $token, array $corpo, string $metodo = 'POST'): array;
}
