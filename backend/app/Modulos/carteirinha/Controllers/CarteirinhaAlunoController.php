<?php

namespace App\Modulos\Carteirinha\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Modulos\Carteirinha\Services\CarteirinhaService;

if (!class_exists(__NAMESPACE__ . '\\CarteirinhaAlunoController', false)) {
class CarteirinhaAlunoController extends BaseController
{
    private AuthManager $auth;
    private CarteirinhaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->service = new CarteirinhaService();
        $user = $this->auth->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
            $this->redirect('/dashboard');
        }
    }

    public function index(): void
    {
        $alunoId = $this->alunoId();
        $cartao = null;
        if ($this->service->tabelasProntas() && $this->service->habilitada()) {
            $cartao = $this->service->cartaoDoAluno($alunoId);
        }
        $this->viewWithLayout('student', 'student/carteirinha/index', [
            'title' => 'Carteirinha - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'carteirinha',
            'habilitada' => $this->service->tabelasProntas() && $this->service->habilitada(),
            'schema_pronto' => $this->service->tabelasProntas(),
            'cartao' => $cartao,
        ]);
    }

    public function imprimir(): void
    {
        if (!$this->service->tabelasProntas() || !$this->service->habilitada()) {
            $this->redirect('/aluno/carteirinha');
            return;
        }
        $cartao = $this->service->cartaoDoAluno($this->alunoId());
        if (!$cartao) {
            $this->redirect('/aluno/carteirinha');
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $cartoes = [$cartao];
        $escola = (string) ($cartao['escola'] ?? 'EducaTudo');
        require dirname(__DIR__) . '/Views/impressao.php';
    }

    private function alunoId(): int
    {
        return (int) ($this->auth->getUser()['id'] ?? 0);
    }
}
}
