<?php
/**
 * Resolve escola (tenant) e URL pública do Master para checkout Asaas.
 */
namespace App\Services;

require_once __DIR__ . '/CreditosCheckoutToken.php';

class CreditosTenantCheckoutHelper
{
    private static function currentScheme(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((string) ($_SERVER['REQUEST_SCHEME'] ?? '') === 'https');

        return $https ? 'https' : 'http';
    }

    private static function currentHost(): string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && strpos($host, ':') !== false) {
            $host = explode(':', $host, 2)[0];
        }
        return $host;
    }

    private static function currentRequestBaseUrl(): string
    {
        $host = self::currentHost();
        if ($host === '') {
            return '';
        }
        return self::currentScheme() . '://' . $host;
    }

    private static function inferredMasterBaseUrl(): string
    {
        if (defined('MASTER_DOMAIN') && trim((string) MASTER_DOMAIN) !== '') {
            return self::currentScheme() . '://' . trim((string) MASTER_DOMAIN);
        }

        $host = self::currentHost();
        if ($host === '') {
            return '';
        }

        if (preg_match('/^[^.]+\.(.+)$/', $host, $matches)) {
            return self::currentScheme() . '://master.' . $matches[1];
        }

        return '';
    }

    /**
     * ID da escola no banco master (multi-tenant: TENANT_ID; single: ESCOLA_ID no .env).
     */
    public static function escolaIdFromConfig(array $config): int
    {
        $id = (int) ($config['tenant']['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
        if (function_exists('env')) {
            $e = (int) env('ESCOLA_ID', 0);
            if ($e > 0) {
                return $e;
            }
        }
        return 0;
    }

    /**
     * Base URL do app Master (ex.: https://master.seudominio.com ou mesma URL em dev).
     */
    public static function masterBaseUrl(): string
    {
        if (function_exists('env')) {
            $base = trim((string) env('MASTER_PUBLIC_URL', ''));
            if ($base !== '') {
                return rtrim($base, '/');
            }
        }
        $inferredMaster = self::inferredMasterBaseUrl();
        if ($inferredMaster !== '') {
            return rtrim($inferredMaster, '/');
        }
        $current = self::currentRequestBaseUrl();
        if ($current !== '') {
            return rtrim($current, '/');
        }
        if (defined('URL')) {
            return rtrim((string) URL, '/');
        }
        return '';
    }

    public static function buildCheckoutActionUrl(): ?string
    {
        $base = self::masterBaseUrl();
        if ($base === '') {
            return null;
        }
        if (function_exists('env')) {
            $configured = trim((string) env('MASTER_PUBLIC_URL', ''));
            if ($configured !== '') {
                return rtrim($configured, '/') . '/master/creditos/asaas/checkout';
            }
        }
        return $base . '/creditos/asaas/checkout';
    }

    public static function buildCheckoutToken(
        int $escolaId,
        int $compraId,
        string $userType,
        int $userId
    ): ?string {
        if ($escolaId <= 0) {
            return null;
        }
        return CreditosCheckoutToken::sign($escolaId, $compraId, $userType, $userId);
    }

    /**
     * URL completa para iniciar checkout (null se faltar escola ou base).
     */
    public static function buildCheckoutRedirectUrl(
        int $escolaId,
        int $compraId,
        string $userType,
        int $userId,
        string $billingType = 'PIX'
    ): ?string {
        if ($escolaId <= 0) {
            return null;
        }
        $base = self::masterBaseUrl();
        if ($base === '') {
            return null;
        }
        $bt = strtoupper(trim($billingType));
        if (!in_array($bt, ['PIX', 'CREDIT_CARD', 'BOLETO'], true)) {
            $bt = 'PIX';
        }
        $token = self::buildCheckoutToken($escolaId, $compraId, $userType, $userId);
        if ($token === null) {
            return null;
        }
        if (function_exists('env')) {
            $configured = trim((string) env('MASTER_PUBLIC_URL', ''));
            if ($configured !== '') {
                return rtrim($configured, '/') . '/master/creditos/asaas/checkout?t=' . rawurlencode($token) . '&bt=' . rawurlencode($bt);
            }
        }
        return $base . '/creditos/asaas/checkout?t=' . rawurlencode($token) . '&bt=' . rawurlencode($bt);
    }
}
