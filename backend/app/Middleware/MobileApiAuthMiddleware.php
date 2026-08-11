<?php
/**
 * Autenticação exclusiva da API mobile v1.
 *
 * Além de validar o JWT, vincula o token ao tenant que o emitiu e revalida o
 * responsável no banco em toda requisição. Isso impede reutilização de token
 * entre escolas e encerra o acesso quando o cadastro é desativado.
 */
class MobileApiAuthMiddleware
{
    public static $parentId = null;
    public static $parent = null;
    public static $sessionId = null;
    public static $requestId = null;

    public function handle(): void
    {
        self::$parentId = null;
        self::$parent = null;
        self::$sessionId = null;
        self::ensureRequestId();
        header('Content-Type: application/json; charset=utf-8');

        if (!self::hasSecureJwtSecret()) {
            $this->serviceUnavailable();
        }

        $header = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            $this->unauthorized('missing_token', 'Token de acesso não informado.');
        }

        require_once __DIR__ . '/../Services/JWTService.php';
        $payload = (new JWTService())->decode(trim($matches[1]));
        if (!is_array($payload)) {
            $this->unauthorized('invalid_token', 'Token inválido ou expirado.');
        }

        if (($payload['role'] ?? '') !== 'parent' || ($payload['api_version'] ?? '') !== 'v1') {
            $this->unauthorized('invalid_token', 'Token incompatível com a API mobile.');
        }

        $expectedTenant = self::currentTenantKey();
        if (!hash_equals($expectedTenant, (string) ($payload['tenant'] ?? ''))) {
            $this->unauthorized('tenant_mismatch', 'Token emitido para outra escola.');
        }

        $parentId = (int) ($payload['user_id'] ?? 0);
        $sessionId = (string) ($payload['session_id'] ?? '');
        if ($parentId <= 0 || !preg_match('/^[a-f0-9-]{36}$/', $sessionId)) {
            $this->unauthorized('invalid_token', 'Token inválido.');
        }

        require_once __DIR__ . '/../Core/Database.php';
        $parent = Database::getInstance()->fetch(
            "SELECT r.id, r.nome, r.email, r.cpf, r.telefone, r.force_password_change
             FROM responsaveis r INNER JOIN mobile_auth_sessions s ON s.parent_id = r.id
             WHERE r.id = :id AND r.ativo = 1 AND s.id = :session_id
               AND s.revoked_at IS NULL AND s.expires_at > NOW() LIMIT 1",
            ['id' => $parentId, 'session_id' => $sessionId]
        );
        if (!$parent) {
            $this->unauthorized('account_inactive', 'Conta inexistente ou inativa.');
        }

        self::$parentId = $parentId;
        self::$parent = $parent;
        self::$sessionId = $sessionId;
        $_SERVER['MOBILE_API_PARENT_ID'] = $parentId;
    }

    public static function currentTenantKey(): string
    {
        if (defined('TENANT_ID')) {
            return 'id:' . (int) TENANT_ID;
        }
        if (defined('TENANT_SLUG') && trim((string) TENANT_SLUG) !== '') {
            return 'slug:' . strtolower(trim((string) TENANT_SLUG));
        }

        $config = require __DIR__ . '/../../config/app.php';
        $schoolCode = strtolower(trim((string) ($config['school']['code'] ?? 'default')));
        return 'school:' . ($schoolCode !== '' ? $schoolCode : 'default');
    }

    public static function hasSecureJwtSecret(): bool
    {
        $secret = trim((string) env('JWT_SECRET', ''));
        return strlen($secret) >= 32;
    }

    public static function ensureRequestId(): string
    {
        if (self::$requestId !== null) return self::$requestId;
        $candidate = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
        self::$requestId = preg_match('/^[A-Za-z0-9._-]{8,80}$/', $candidate) ? $candidate : bin2hex(random_bytes(16));
        header('X-Request-ID: ' . self::$requestId);
        return self::$requestId;
    }

    private function unauthorized(string $code, string $message): void
    {
        http_response_code(401);
        echo json_encode([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function serviceUnavailable(): void
    {
        http_response_code(503);
        echo json_encode([
            'error' => [
                'code' => 'mobile_api_not_configured',
                'message' => 'API mobile temporariamente indisponível.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
