<?php

namespace App\Modulos\Provas\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Modulos\Provas\Services\LayoutProvaImpressaService;

if (!class_exists(__NAMESPACE__ . '\\LayoutProvaImpressaController', false)) {
class LayoutProvaImpressaController extends BaseController
{
    private AuthManager $auth;
    private LayoutProvaImpressaService $layout;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->layout = new LayoutProvaImpressaService();
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
        }
        $user = $this->auth->getUser();
        if (!in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin/dashboard');
        }
    }

    public function abrir($blocoId): void
    {
        $pacote = $this->exigirProva((int) $blocoId);
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/exams/blocks/layout', [
            'title' => 'Layout da prova impressa',
            'user' => $this->auth->getUser(),
            'csrf_token' => $this->generateCsrfToken(),
            'pacote' => $pacote,
            'flash_message' => (string) ($flash['message'] ?? ''),
            'flash_type' => (string) ($flash['type'] ?? ''),
        ]);
    }

    public function salvar($blocoId): void
    {
        $blocoId = (int) $blocoId;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página.', 'error');
            $this->redirect('/admin/provas/blocos/' . $blocoId . '/layout');
        }
        try {
            $this->layout->salvar($blocoId, $_POST);
        } catch (\Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/admin/provas/blocos/' . $blocoId . '/layout');
        }
        $verImpressao = (string) ($_POST['depois'] ?? '') === 'imprimir';
        if (!$verImpressao) {
            $this->setFlashMessage('Layout da prova salvo.', 'success');
        }
        $destino = $verImpressao
            ? '/admin/provas/blocos/' . $blocoId . '/layout/imprimir'
            : '/admin/provas/blocos/' . $blocoId . '/layout';
        $this->redirect($destino);
    }

    public function imprimir($blocoId): void
    {
        $pacote = $this->exigirProva((int) $blocoId);
        $this->viewWithLayout('partial', 'admin/exams/blocks/imprimir-layout', [
            'pacote' => $pacote,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exigirProva(int $blocoId): array
    {
        $pacote = $this->layout->carregar($blocoId);
        if ($pacote === null) {
            $this->setFlashMessage('Esta prova não é uma avaliação impressa.', 'error');
            $this->redirect('/admin/provas');
        }
        return $pacote;
    }
}
}
