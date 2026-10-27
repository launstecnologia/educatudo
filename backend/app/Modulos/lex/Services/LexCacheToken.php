<?php

namespace App\Modulos\Lex\Services;

interface LexCacheToken
{
    /**
     * @return array{token:string,expira_em:int}|null
     */
    public function ler(string $chave): ?array;

    public function gravar(string $chave, string $token, int $expiraEm): void;

    public function apagar(string $chave): void;
}
