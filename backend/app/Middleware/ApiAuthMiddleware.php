<?php
/**
 * EducaTudo - Middleware de autenticação da API (Pais)
 * Valida Bearer JWT, expiração e role parent; injeta parent_id na requisição
 */

class ApiAuthMiddleware
{
    /**
     * ID do pai logado (disponível após handle para os controllers)
     */
    public static $parentId = null;

    public function handle()
    {
        self::$parentId = null;
        header('Content-Type: application/json; charset=utf-8');

        $secret = trim((string) env('JWT_SECRET', ''));
        if (strlen($secret) < 32) {
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'message' => 'API temporariamente indisponível (JWT_SECRET não configurado).'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)) {
            $token = trim($m[1]);
        } else {
            $this->unauthorized('Token não informado ou inválido');
            return;
        }

        require_once __DIR__ . '/../Services/JWTService.php';
        try {
            $jwt = new JWTService();
        } catch (RuntimeException $e) {
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'message' => 'API temporariamente indisponível (JWT_SECRET não configurado).'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $payload = $jwt->decode($token);

        if (!$payload) {
            $this->logInvalidAttempt();
            $this->unauthorized('Token inválido ou expirado');
            return;
        }

        if (($payload['role'] ?? '') !== 'parent') {
            $this->logInvalidAttempt();
            $this->unauthorized('Acesso restrito ao perfil de responsável');
            return;
        }

        $parentId = isset($payload['user_id']) ? (int) $payload['user_id'] : 0;
        if ($parentId <= 0) {
            $this->unauthorized('Token inválido');
            return;
        }

        self::$parentId = $parentId;
        $_SERVER['API_PARENT_ID'] = $parentId;
    }

    private function unauthorized(string $message)
    {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function logInvalidAttempt()
    {
        try {
            if (class_exists('Logger')) {
                Logger::warning('API Parents: tentativa de acesso com token inválido ou expirado', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'uri' => $_SERVER['REQUEST_URI'] ?? null,
                ], 'general');
            }
        } catch (Throwable $e) {
            error_log('ApiAuthMiddleware log: ' . $e->getMessage());
        }
    }
}
