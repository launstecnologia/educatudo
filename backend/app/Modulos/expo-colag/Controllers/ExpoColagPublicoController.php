<?php
/**
 * Expo Colag — páginas públicas (QR do stand, sem login).
 */

require_once __DIR__ . '/../Services/ExpoColagExecucaoService.php';

if (!class_exists('ExpoColagPublicoController')) {
class ExpoColagPublicoController extends BaseController
{
    private ExpoColagExecucaoService $execucao;

    public function __construct()
    {
        parent::__construct();
        $this->execucao = new ExpoColagExecucaoService();
    }

    public function stand($token): void
    {
        $dados = $this->execucao->dadosStandPublico((string) $token);
        $escolaNome = '';
        if (class_exists('LayoutHelper')) {
            $escolaNome = (string) LayoutHelper::getSystemTitle();
        }
        if ($escolaNome === '') {
            $escolaNome = 'Expo Colag';
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $viewFile = dirname(__DIR__) . '/Views/publico/stand.php';
        $stand = $dados['stand'] ?? null;
        $erro = $dados['success'] ? null : ($dados['error'] ?? 'Stand não encontrado.');
        $cancelado = !empty($dados['cancelado']);
        $escola_sistema = $escolaNome;
        require $viewFile;
    }
}
}
