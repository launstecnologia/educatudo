<?php
/**
 * API REST - Notificações manuais (API externa).
 * POST /api/notificacoes (requer X-API-KEY)
 * GET /api/notificacoes (lista notificações criadas)
 */
require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Models/NotificacaoApi.php';

class NotificacoesApiController extends BaseController
{
    private $notificacaoApiModel;

    public function __construct()
    {
        parent::__construct();
        $this->notificacaoApiModel = new NotificacaoApi();
    }

    /**
     * GET /api/notificacoes - Lista notificações.
     */
    public function index()
    {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $lista = $this->notificacaoApiModel->listar($limit);
        $this->json($lista);
    }

    /**
     * POST /api/notificacoes - Cria notificação manual. Requer header X-API-KEY.
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'error' => 'Método não permitido'], 405);
            return;
        }

        if (!$this->validarApiKey()) {
            $this->json(['success' => false, 'error' => 'Chave de API inválida ou ausente'], 401);
            return;
        }

        $input = $this->getJsonInput();
        $titulo = isset($input['titulo']) ? trim((string) $input['titulo']) : '';
        $mensagem = isset($input['mensagem']) ? trim((string) $input['mensagem']) : '';
        $imagem = isset($input['imagem']) ? trim((string) $input['imagem']) : null;
        $categoria = isset($input['categoria']) ? trim((string) $input['categoria']) : null;

        if ($titulo === '') {
            $this->json(['success' => false, 'error' => 'Campo titulo é obrigatório'], 400);
            return;
        }
        if ($mensagem === '') {
            $this->json(['success' => false, 'error' => 'Campo mensagem é obrigatório'], 400);
            return;
        }

        try {
            $id = $this->notificacaoApiModel->criar($titulo, $mensagem, $imagem, $categoria);
            $this->json([
                'success' => true,
                'id' => (int) $id,
                'message' => 'Notificação criada com sucesso.',
            ], 201);
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => 'Erro ao salvar notificação.',
            ], 500);
        }
    }

    private function validarApiKey()
    {
        $config = require __DIR__ . '/../../../config/api.php';
        $chaveEsperada = isset($config['api_key']) ? $config['api_key'] : '';
        $headerName = isset($config['api_key_header']) ? $config['api_key_header'] : 'X-API-KEY';

        if ($chaveEsperada === '') {
            return false;
        }

        $chaveEnviada = '';
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $k => $v) {
                if (strcasecmp($k, $headerName) === 0) {
                    $chaveEnviada = trim($v);
                    break;
                }
            }
        }
        if ($chaveEnviada === '' && isset($_SERVER['HTTP_X_API_KEY'])) {
            $chaveEnviada = trim($_SERVER['HTTP_X_API_KEY']);
        }

        return $chaveEnviada !== '' && hash_equals($chaveEsperada, $chaveEnviada);
    }

    private function getJsonInput()
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
