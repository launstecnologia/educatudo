<?php

namespace App\Modulos\Lex\Services;

class LexCacheTokenMemoria implements LexCacheToken
{
    /** @var array<string, array{token:string,expira_em:int}> */
    private array $itens = [];

    public function ler(string $chave): ?array
    {
        $item = $this->itens[$chave] ?? null;
        if ($item === null || $item['expira_em'] <= time()) {
            return null;
        }
        return $item;
    }

    public function gravar(string $chave, string $token, int $expiraEm): void
    {
        $this->itens[$chave] = [
            'token' => $token,
            'expira_em' => $expiraEm,
        ];
    }

    public function apagar(string $chave): void
    {
        unset($this->itens[$chave]);
    }
}
