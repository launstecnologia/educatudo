<?php
require_once __DIR__ . '/../../../Core/BaseController.php';
require_once __DIR__ . '/../../../Core/Database.php';
require_once __DIR__ . '/../../../Services/JWTService.php';
require_once __DIR__ . '/../../../Middleware/MobileApiAuthMiddleware.php';

class MobileAuthController extends BaseController
{
    private $db;
    private $jwt;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        try {
            $this->jwt = new JWTService();
        } catch (RuntimeException $e) {
            $this->jwt = null;
        }
    }

    public function login(): void
    {
        MobileApiAuthMiddleware::ensureRequestId();
        if ($this->jwt === null || !MobileApiAuthMiddleware::hasSecureJwtSecret()) {
            $this->apiError('mobile_api_not_configured', 'API mobile temporariamente indisponível.', 503);
        }

        $input = $this->jsonInput();
        $cpf = preg_replace('/\D+/', '', (string) ($input['cpf'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if (strlen($cpf) !== 11 || $password === '') {
            $this->apiError('validation_error', 'CPF e senha são obrigatórios.', 422, [
                'cpf' => strlen($cpf) === 11 ? null : 'Informe um CPF com 11 dígitos.',
                'password' => $password !== '' ? null : 'Informe a senha.',
            ]);
        }

        $this->applyRateLimit($cpf);

        $formattedCpf = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.'
            . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);

        $parents = $this->db->fetchAll(
            "SELECT id, nome, email, cpf, telefone, senha_hash, force_password_change
             FROM responsaveis
             WHERE (cpf = :cpf_digits OR cpf = :cpf_formatted)
               AND ativo = 1
             LIMIT 2",
            ['cpf_digits' => $cpf, 'cpf_formatted' => $formattedCpf]
        );
        $parent = count($parents) === 1 ? $parents[0] : null;

        if (!$parent || !password_verify($password, (string) ($parent['senha_hash'] ?? ''))) {
            $this->apiError('invalid_credentials', 'CPF ou senha inválidos.', 401);
        }

        $this->clearRateLimit($cpf);

        [$sessionId, $refreshToken] = $this->createSession((int) $parent['id']);
        $token = $this->accessToken((int) $parent['id'], $sessionId);

        $this->json([
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => (int) env('JWT_TTL', 86400),
                'refresh_token' => $refreshToken,
                'parent' => $this->serializeParent($parent),
            ],
        ]);
    }

    public function refresh(): void
    {
        MobileApiAuthMiddleware::ensureRequestId();
        if ($this->jwt === null || !MobileApiAuthMiddleware::hasSecureJwtSecret()) {
            $this->apiError('mobile_api_not_configured', 'API mobile temporariamente indisponível.', 503);
        }
        $refreshToken = trim((string) ($this->jsonInput()['refresh_token'] ?? ''));
        $parts = explode('.', $refreshToken, 2);
        if (count($parts) !== 2 || !preg_match('/^[a-f0-9-]{36}$/', $parts[0])) {
            $this->apiError('invalid_refresh_token', 'Refresh token inválido.', 401);
        }
        $session = $this->db->fetch(
            "SELECT s.id, s.parent_id FROM mobile_auth_sessions s
             INNER JOIN responsaveis r ON r.id = s.parent_id AND r.ativo = 1
             WHERE s.id = :id AND s.refresh_token_hash = :token_hash
               AND s.revoked_at IS NULL AND s.expires_at > NOW() LIMIT 1",
            ['id' => $parts[0], 'token_hash' => hash('sha256', $refreshToken)]
        );
        if (!$session) $this->apiError('invalid_refresh_token', 'Refresh token inválido ou expirado.', 401);

        $newRefresh = $session['id'] . '.' . bin2hex(random_bytes(32));
        $this->db->update(
            'UPDATE mobile_auth_sessions SET refresh_token_hash = :hash, last_used_at = NOW() WHERE id = :id',
            ['hash' => hash('sha256', $newRefresh), 'id' => $session['id']]
        );
        $this->json(['data' => [
            'access_token' => $this->accessToken((int) $session['parent_id'], (string) $session['id']),
            'token_type' => 'Bearer', 'expires_in' => (int) env('JWT_TTL', 86400),
            'refresh_token' => $newRefresh,
        ]]);
    }

    public function logout(): void
    {
        MobileApiAuthMiddleware::ensureRequestId();
        $this->db->update(
            'UPDATE mobile_auth_sessions SET revoked_at = NOW() WHERE id = :id AND parent_id = :parent_id AND revoked_at IS NULL',
            ['id' => MobileApiAuthMiddleware::$sessionId, 'parent_id' => MobileApiAuthMiddleware::$parentId]
        );
        http_response_code(204);
        exit;
    }

    private function createSession(int $parentId): array
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $id = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
        $refresh = $id . '.' . bin2hex(random_bytes(32));
        $refreshTtl = max(86400, (int) env('MOBILE_REFRESH_TTL', 2592000));
        $expiresAt = date('Y-m-d H:i:s', time() + $refreshTtl);
        $this->db->query(
            'INSERT INTO mobile_auth_sessions (id, parent_id, refresh_token_hash, expires_at) VALUES (:id, :parent_id, :hash, :expires_at)',
            ['id' => $id, 'parent_id' => $parentId, 'hash' => hash('sha256', $refresh), 'expires_at' => $expiresAt]
        );
        return [$id, $refresh];
    }

    private function accessToken(int $parentId, string $sessionId): string
    {
        return $this->jwt->encode([
            'user_id' => $parentId, 'role' => 'parent', 'api_version' => 'v1',
            'tenant' => MobileApiAuthMiddleware::currentTenantKey(), 'session_id' => $sessionId,
        ]);
    }

    private function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    private function serializeParent(array $parent): array
    {
        return [
            'id' => (int) $parent['id'],
            'name' => (string) $parent['nome'],
            'email' => $parent['email'] !== null ? (string) $parent['email'] : null,
            'cpf' => preg_replace('/\D+/', '', (string) ($parent['cpf'] ?? '')),
            'phone' => $parent['telefone'] !== null ? (string) $parent['telefone'] : null,
            'must_change_password' => !empty($parent['force_password_change']),
        ];
    }

    private function apiError(string $code, string $message, int $status, array $details = []): void
    {
        $error = ['code' => $code, 'message' => $message];
        $details = array_filter($details, static fn ($value) => $value !== null);
        if ($details !== []) {
            $error['details'] = $details;
        }
        $this->json(['error' => $error], $status);
    }

    /**
     * Limite local de contingência. Funciona sem Redis e é isolado por tenant,
     * CPF e IP. Em implantação horizontal deve ser substituído por Redis.
     */
    private function applyRateLimit(string $cpf): void
    {
        $window = max(60, (int) env('MOBILE_LOGIN_RATE_WINDOW', 300));
        $maxAttempts = max(3, (int) env('MOBILE_LOGIN_MAX_ATTEMPTS', 5));
        $file = $this->rateLimitFile($cpf);
        $now = time();
        $attempts = [];

        if (is_file($file)) {
            $decoded = json_decode((string) @file_get_contents($file), true);
            if (is_array($decoded)) {
                $attempts = array_values(array_filter(array_map('intval', $decoded), static function (int $time) use ($now, $window): bool {
                    return $time > ($now - $window);
                }));
            }
        }

        if (count($attempts) >= $maxAttempts) {
            header('Retry-After: ' . $window);
            $this->apiError('too_many_attempts', 'Muitas tentativas. Aguarde e tente novamente.', 429);
        }

        $attempts[] = $now;
        @file_put_contents($file, json_encode($attempts), LOCK_EX);
    }

    private function clearRateLimit(string $cpf): void
    {
        $file = $this->rateLimitFile($cpf);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function rateLimitFile(string $cpf): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = MobileApiAuthMiddleware::currentTenantKey() . '|' . $cpf . '|' . $ip;
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'educatudo_mobile_login_' . hash('sha256', $key) . '.json';
    }
}
