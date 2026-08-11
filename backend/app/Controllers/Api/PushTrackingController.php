<?php
/**
 * API de tracking de notificações push (visualizado / clicado).
 * Chamado pelo service worker - sem autenticação (sem cookie de sessão).
 */
require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Models/PushNotifications/PushNotification.php';

class PushTrackingController extends BaseController
{
    private $db;
    private $pushModel;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->pushModel = new PushNotification();
    }

    /**
     * POST /api/notificacoes/visualizado
     * Body JSON: { "token": "tracking_token" }
     */
    public function visualizado()
    {
        $this->jsonResponse();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método não permitido'], 405);
            return;
        }
        $input = $this->getJsonInput();
        $token = isset($input['token']) ? trim((string) $input['token']) : '';
        if ($token === '') {
            $this->json(['ok' => false, 'error' => 'token obrigatório'], 400);
            return;
        }
        $this->pushModel->marcarVisualizadoPorToken($token);
        $this->json(['ok' => true]);
    }

    /**
     * POST /api/notificacoes/clicado
     * Body JSON: { "token": "tracking_token" }
     * Retorna: { "ok": true, "url": "..." } para o SW abrir a URL.
     */
    public function clicado()
    {
        $this->jsonResponse();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método não permitido'], 405);
            return;
        }
        $input = $this->getJsonInput();
        $token = isset($input['token']) ? trim((string) $input['token']) : '';
        if ($token === '') {
            $this->json(['ok' => false, 'error' => 'token obrigatório'], 400);
            return;
        }
        $url = $this->pushModel->marcarClicadoPorToken($token);
        $baseUrl = rtrim(defined('URL') ? URL : '', '/');
        if ($url !== null && $url !== '') {
            $url = preg_match('#^https?://#', $url) ? $url : $baseUrl . '/' . ltrim($url, '/');
        }
        $this->json(['ok' => true, 'url' => $url]);
    }

    private function getJsonInput()
    {
        $raw = file_get_contents('php://input');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function jsonResponse()
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    private function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}
