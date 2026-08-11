<?php
/**
 * EducaTudo - API de Autenticação (Pais)
 * POST /api/auth/login - Login exclusivo para role parent, retorna JWT
 */

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Services/JWTService.php';

class AuthController extends BaseController
{
    private $db;
    private $jwt;
    private $maxLoginAttempts = 10;
    private $rateLimitWindow = 60; // segundos

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

    /**
     * POST /api/auth/login
     * Body JSON: { "email": "...", "password": "..." }
     */
    public function login()
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Método não permitido'], 405);
            return;
        }

        if ($this->jwt === null) {
            $this->json([
                'success' => false,
                'message' => 'API temporariamente indisponível (JWT_SECRET não configurado).'
            ], 503);
            return;
        }

        $this->applyRateLimit();

        $input = $this->getJsonInput();
        $email = isset($input['email']) ? trim((string) $input['email']) : '';
        $password = isset($input['password']) ? (string) $input['password'] : '';

        if ($email === '' || $password === '') {
            $this->json(['success' => false, 'message' => 'Email e senha são obrigatórios'], 400);
            return;
        }

        $pai = $this->db->fetch(
            "SELECT id, nome, email, senha_hash FROM responsaveis WHERE email = :email AND ativo = 1",
            ['email' => $email]
        );

        if (!$pai || !password_verify($password, $pai['senha_hash'] ?? '')) {
            $this->logInvalidLogin($email);
            $this->json(['success' => false, 'message' => 'Credenciais inválidas'], 401);
            return;
        }

        $payload = [
            'user_id' => (int) $pai['id'],
            'role' => 'parent',
        ];
        $token = $this->jwt->encode($payload);

        $this->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'name' => $pai['nome'],
            ],
        ]);
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Rate limit básico por IP para /api/auth/login
     */
    private function applyRateLimit(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'api_parent_login_' . md5($ip);
        $dir = sys_get_temp_dir() . '/educatudo_api_rate';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . $key . '.txt';
        $now = time();
        $attempts = [];

        if (file_exists($file)) {
            $content = file_get_contents($file);
            $attempts = array_filter(array_map('intval', explode(',', $content)), function ($t) use ($now) {
                return $t > $now - $this->rateLimitWindow;
            });
        }

        if (count($attempts) >= $this->maxLoginAttempts) {
            $this->json(['success' => false, 'message' => 'Muitas tentativas. Tente novamente em alguns minutos.'], 429);
            exit;
        }

        $attempts[] = $now;
        @file_put_contents($file, implode(',', $attempts));
    }

    private function logInvalidLogin(string $email): void
    {
        try {
            if (class_exists('Logger')) {
                Logger::authError('API Parents: login falhou - credenciais inválidas', [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            }
        } catch (Throwable $e) {
            error_log('API AuthController log: ' . $e->getMessage());
        }
    }
}
