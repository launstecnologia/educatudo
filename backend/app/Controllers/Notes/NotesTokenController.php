<?php
/**
 * EducaTudo - Validação e logout de token Notes (rotas públicas).
 * Chamado pelo app notes.educatudo.com; não exige sessão.
 */

if (!class_exists('NotesTokenController')) {
class NotesTokenController extends BaseController
{
    private $db;

    /** Grava em storage/logs/validate_token.log para diagnóstico (independente do error_log do PHP). */
    private static function writeTokenLog(string $source, string $message): void
    {
        $dir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/validate_token.log';
        $line = '[' . date('Y-m-d H:i:s') . '] [' . $source . '] ' . $message . "\n";
        @file_put_contents($file, $line, FILE_APPEND);
    }

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }

    /**
     * Valida token. URL do Notes: https://notes.educatudo.com?token=TOKEN&validate_base=URL_PLATAFORMA
     * O app Notes deve chamar: GET {validate_base}/notes/validate-token?token=... (recomendado para Notes).
     * Opcional: NOTES_TEST_TOKEN no .env (ex.: TESTE_TUDINHA_2025) para testar sem gerar token real.
     * IMPORTANTE: Endpoint público (FeatureGate); nunca redirecionar — sempre retornar JSON.
     */
    public function validateToken()
    {
        header('Content-Type: application/json; charset=utf-8');
        $token = trim($_GET['token'] ?? '');
        if ($token === '') {
            self::writeTokenLog('notes', 'VALIDAR -> FALHA: token não fornecido na URL (esperado ?token=...)');
            $this->json(['error' => 'Token não fornecido'], 400);
            return;
        }

        $fp = strlen($token) >= 16 ? (substr($token, 0, 8) . '...' . substr($token, -8)) : '(token curto)';
        self::writeTokenLog('notes', 'VALIDAR fingerprint=' . $fp . ' len=' . strlen($token));

        $testToken = function_exists('env') ? trim((string) env('NOTES_TEST_TOKEN', '')) : '';
        if ($testToken !== '' && $token === $testToken) {
            $testUserId   = function_exists('env') ? (string) env('NOTES_TEST_USER_ID', '0') : '0';
            $testUserName = function_exists('env') ? trim((string) env('NOTES_TEST_USER_NAME', 'Usuário teste')) : 'Usuário teste';
            self::writeTokenLog('notes', 'VALIDAR fingerprint=' . $fp . ' -> OK (token de teste)');
            $this->json(['userId' => $testUserId, 'userName' => $testUserName]);
            return;
        }

        $row = $this->db->fetch(
            "SELECT t.user_id, t.user_name
             FROM notes_tokens t
             WHERE t.token = ? AND t.expires_at > NOW()",
            [$token]
        );

        if (!$row) {
            // Diagnóstico: saber exatamente por que falhou (não encontrado ou expirado)
            $diag = $this->db->fetch(
                "SELECT expires_at, used FROM notes_tokens WHERE token = ?",
                [$token]
            );
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (!$diag) {
                $msg = 'VALIDAR fingerprint=' . $fp . ' -> FALHA: token não encontrado na tabela. uri=' . $uri;
            } elseif ((int) $diag['used'] === 1) {
                $msg = 'VALIDAR fingerprint=' . $fp . ' -> FALHA: token já utilizado (used=1). uri=' . $uri;
            } else {
                $exp = $diag['expires_at'] ?? null;
                $msg = 'VALIDAR fingerprint=' . $fp . ' -> FALHA: expirado (expires_at=' . ($exp ?: 'null') . ', NOW=' . date('Y-m-d H:i:s') . '). uri=' . $uri;
            }
            error_log('[Notes validateToken] ' . $msg);
            self::writeTokenLog('notes', $msg);
            $validateBase = defined('URL') ? rtrim(URL, '/') : '';
            $this->json([
                'error' => 'Token inválido ou expirado',
                'endpoint_expected' => 'notes/validate-token',
                'validate_base' => $validateBase,
                'hint' => 'Chame GET {validate_base}/notes/validate-token?token=... (não use /educalabs/validate-token)',
            ], 401);
            return;
        }

        // Não marcar token como usado aqui: permite "retornar para confirmar" / múltiplas validações na mesma sessão.
        // O token fica válido até expires_at (5 min); used=1 só no logout (logoutToken) ou por limpeza.

        self::writeTokenLog('notes', 'VALIDAR fingerprint=' . $fp . ' -> OK user_id=' . $row['user_id']);

        $this->json([
            'userId'   => (string) $row['user_id'],
            'userName' => $row['user_name'],
        ]);
    }

    public function logoutToken()
    {
        $token = trim($_POST['token'] ?? '');
        if ($token === '') {
            $this->json(['error' => 'Token não fornecido'], 400);
            return;
        }

        $testToken = function_exists('env') ? trim((string) env('NOTES_TEST_TOKEN', '')) : '';
        if ($testToken !== '' && $token === $testToken) {
            $this->json(['success' => true]);
            return;
        }

        $this->db->delete("DELETE FROM notes_tokens WHERE token = ?", [$token]);
        $this->json(['success' => true]);
    }
}
}
