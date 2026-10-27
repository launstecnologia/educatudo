<?php

namespace App\Modulos\Lex\Services;

use App\Core\RedisCache;

class LexCacheTokenRedis implements LexCacheToken
{
    public function ler(string $chave): ?array
    {
        $bruto = RedisCache::get($chave);
        if ($bruto === null || $bruto === '') {
            return null;
        }
        $json = json_decode($bruto, true);
        if (!is_array($json) || trim((string) ($json['token'] ?? '')) === '') {
            return null;
        }
        $expira = (int) ($json['expira_em'] ?? 0);
        if ($expira <= time()) {
            return null;
        }
        return [
            'token' => (string) $json['token'],
            'expira_em' => $expira,
        ];
    }

    public function gravar(string $chave, string $token, int $expiraEm): void
    {
        $ttl = max(1, $expiraEm - time());
        $json = json_encode([
            'token' => $token,
            'expira_em' => $expiraEm,
        ], JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }
        RedisCache::set($chave, $json, $ttl);
    }

    public function apagar(string $chave): void
    {
        RedisCache::delete($chave);
    }
}
