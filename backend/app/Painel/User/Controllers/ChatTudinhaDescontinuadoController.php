<?php

namespace App\Painel\User\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;

/**
 * Etapa 7 — Chat Tudinha descontinuado.
 * Todas as rotas exclusivas /chat* apontam aqui (HTTP 410).
 * Não executa IA, créditos, streaming nem escrita em tudinha_*.
 */
if (!class_exists(__NAMESPACE__ . '\\ChatTudinhaDescontinuadoController', false)) {
class ChatTudinhaDescontinuadoController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $auth = new AuthManager();
        if (!$auth->isLoggedIn()) {
            $this->redirect('/');
            return;
        }
        $user = $auth->getUser();
        if (($user['tipo'] ?? '') !== 'aluno') {
            $this->redirect('/dashboard');
        }
    }

    /** Qualquer verbo/rota exclusiva do chat Tudinha. */
    public function indisponivel(): void
    {
        $payload = [
            'error' => 'O chat da Tudinha foi descontinuado e não está mais disponível.',
            'code' => 'chat_tudinha_descontinuado',
            'descontinuado' => true,
        ];

        if ($this->clienteEsperaJson()) {
            $this->json($payload, 410);
            return;
        }

        http_response_code(410);
        header('Content-Type: text/html; charset=UTF-8');
        $msg = htmlspecialchars($payload['error'], ENT_QUOTES, 'UTF-8');
        $dash = htmlspecialchars((defined('URL') ? URL : '') . '/dashboard', ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Chat descontinuado</title></head><body style="font-family:system-ui,sans-serif;padding:2rem;max-width:40rem;margin:auto">'
            . '<h1>Chat da Tudinha descontinuado</h1>'
            . '<p>' . $msg . '</p>'
            . '<p><a href="' . $dash . '">Voltar ao dashboard</a></p>'
            . '</body></html>';
        exit;
    }

    private function clienteEsperaJson(): bool
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        // Stream SSE e endpoints AJAX do chat
        if (str_contains($uri, 'mensagem-stream') || str_contains($uri, '/chat/')) {
            $xhr = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
            if (strcasecmp($xhr, 'XMLHttpRequest') === 0) {
                return true;
            }
        }
        return false;
    }
}
}

if (!class_exists('ChatTudinhaDescontinuadoController', false) && class_exists(__NAMESPACE__ . '\\ChatTudinhaDescontinuadoController', false)) {
    class_alias(__NAMESPACE__ . '\\ChatTudinhaDescontinuadoController', 'ChatTudinhaDescontinuadoController');
}
