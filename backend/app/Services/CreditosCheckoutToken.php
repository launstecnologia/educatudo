<?php
/**
 * Token assinado (HMAC) para redirecionar aluno/professor ao checkout Asaas no Master.
 * Mesmo segredo deve existir no .env do tenant e do master (MASTER_CREDITOS_CHECKOUT_SECRET).
 */
namespace App\Services;

class CreditosCheckoutToken
{
    private static function secret(): string
    {
        if (function_exists('env')) {
            $s = (string) env('MASTER_CREDITOS_CHECKOUT_SECRET', '');
            if ($s !== '') {
                return $s;
            }
        }
        return 'dev-only-change-MASTER_CREDITOS_CHECKOUT_SECRET';
    }

    /**
     * @return string Token URL-safe
     */
    public static function sign(int $escolaId, int $compraId, string $userType, int $userId, int $ttlSeconds = 1800): string
    {
        $exp = time() + $ttlSeconds;
        $payload = json_encode([
            'e' => $escolaId,
            'c' => $compraId,
            't' => $userType,
            'u' => $userId,
            'x' => $exp,
        ], JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $payload, self::secret(), true);
        return rtrim(strtr(base64_encode($payload . '::' . $sig), '+/', '-_'), '=');
    }

    /**
     * @return array{e:int,c:int,t:string,u:int,x:int}|null
     */
    public static function verify(string $token): ?array
    {
        $token = strtr($token, '-_', '+/');
        $pad = strlen($token) % 4;
        if ($pad) {
            $token .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($token, true);
        if ($raw === false || strpos($raw, '::') === false) {
            return null;
        }
        [$payload, $sigBin] = explode('::', $raw, 2);
        $expected = hash_hmac('sha256', $payload, self::secret(), true);
        if (!hash_equals($expected, $sigBin)) {
            return null;
        }
        $data = json_decode($payload, true);
        if (!is_array($data) || !isset($data['e'], $data['c'], $data['t'], $data['u'], $data['x'])) {
            return null;
        }
        if ((int) $data['x'] < time()) {
            return null;
        }
        if (!in_array($data['t'], ['aluno', 'professor', 'escola'], true)) {
            return null;
        }
        return [
            'e' => (int) $data['e'],
            'c' => (int) $data['c'],
            't' => (string) $data['t'],
            'u' => (int) $data['u'],
            'x' => (int) $data['x'],
        ];
    }
}
