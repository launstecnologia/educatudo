<?php

namespace App\Modulos\DocumentosFamilia\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Modulos\DocumentosFamilia\Services\DocumentoFamiliaService;

class DocumentoFamiliaAdminController extends BaseController
{
    private AuthManager $auth;
    private DocumentoFamiliaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
        }
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->redirectToCorrectDashboard($user['tipo'] ?? '');
        }
        $this->service = new DocumentoFamiliaService();
    }

    public function index(): void
    {
        $flash = $this->getFlashMessage();
        $painel = $this->service->pronto()
            ? $this->service->painel()
            : ['arquivos' => [], 'fichas' => [], 'declaracoes' => [], 'aceites' => []];
        $this->viewWithLayout('admin', 'admin/documentos-familia/index', [
            'title' => 'Documentos da família',
            'page_title' => 'Documentos da família',
            'user' => $this->auth->getUser(),
            'pronto' => $this->service->pronto(),
            'arquivos' => $painel['arquivos'],
            'fichas' => $painel['fichas'],
            'declaracoes' => $painel['declaracoes'],
            'aceites' => $painel['aceites'],
            'current_page' => 'documentos-familia',
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'] ?? '',
            'flash_type' => $flash['type'] ?? '',
        ]);
    }

    public function arquivo($id): void
    {
        $arquivoId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $arquivo = $arquivoId === false ? null : $this->service->caminhoArquivo((int) $arquivoId);
        if ($arquivo === null) {
            $this->setFlashMessage('Arquivo não encontrado.', 'error');
            $this->redirect(URL . '/admin/documentos-familia');
            return;
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        $nome = str_replace(['"', "\r", "\n"], '', $arquivo['nome']);
        header('Content-Type: ' . $arquivo['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . $nome . '"');
        header('Content-Length: ' . (string) filesize($arquivo['path']));
        readfile($arquivo['path']);
        exit;
    }

    public function criarDeclaracao(): void
    {
        $this->csrf();
        if (!$this->service->pronto()) {
            $this->setFlashMessage('Rode a migração de documentos da família antes de publicar uma declaração.', 'error');
            $this->redirect(URL . '/admin/documentos-familia');
        }
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $texto = trim((string) ($_POST['texto'] ?? ''));
        if ($titulo === '' || $texto === '' || mb_strlen($titulo) > 180 || mb_strlen($texto) > 8000) {
            $this->setFlashMessage('Informe o título e o texto da declaração.', 'error');
            $this->redirect(URL . '/admin/documentos-familia');
        }
        $this->service->criarDeclaracao($titulo, $texto);
        $this->setFlashMessage('Declaração publicada para a família.', 'success');
        $this->redirect(URL . '/admin/documentos-familia');
    }

    private function csrf(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect(URL . '/admin/documentos-familia');
        }
    }
}

if (!class_exists('DocumentoFamiliaAdminController', false) && class_exists(__NAMESPACE__ . '\\DocumentoFamiliaAdminController', false)) {
    class_alias(__NAMESPACE__ . '\\DocumentoFamiliaAdminController', 'DocumentoFamiliaAdminController');
}
