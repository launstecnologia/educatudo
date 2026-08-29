<?php
/**
 * Expo Colag — páginas públicas (QR do stand, sem login).
 */

require_once __DIR__ . '/../Services/ExpoColagExecucaoService.php';
require_once __DIR__ . '/../Services/ExpoColagService.php';

if (!class_exists('ExpoColagPublicoController')) {
class ExpoColagPublicoController extends BaseController
{
    private ExpoColagExecucaoService $execucao;
    private ExpoColagService $service;

    public function __construct()
    {
        parent::__construct();
        $this->execucao = new ExpoColagExecucaoService();
        $this->service = new ExpoColagService();
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

    /** Serve capa salva em disco (inclui arquivos legados em storage/uploads). */
    public function capa($id): void
    {
        $caminho = $this->service->caminhoArquivoCapa((int) $id);
        if ($caminho === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Capa não encontrada.';
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($caminho) ?: 'application/octet-stream';
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            http_response_code(404);
            exit;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($caminho));
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($caminho);
        exit;
    }
}
}
