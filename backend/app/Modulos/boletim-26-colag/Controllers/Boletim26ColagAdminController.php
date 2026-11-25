<?php

namespace App\Modulos\Boletim26Colag\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Modulos\Boletim26Colag\Services\Boletim26ColagService;

/**
 * Tela do demonstrativo fixo do Colag. Escola que não é o Colag volta ao painel.
 */
class Boletim26ColagAdminController extends BaseController
{
    private AuthManager $auth;

    private Boletim26ColagService $servico;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->servico = new Boletim26ColagService();
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            exit;
        }
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin/dashboard');
            exit;
        }
        if (!Boletim26ColagService::ativo()) {
            $this->redirect('/admin/dashboard');
            exit;
        }
    }

    public function index(): void
    {
        $turmaId = (int) ($_GET['turma_id'] ?? 0);
        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        $ano = (int) ($_GET['ano'] ?? 0);
        $painel = $this->servico->montarPainel($turmaId, $alunoId, $ano);

        $this->viewWithLayout('admin', 'admin/boletim-26-colag/index', [
            'title' => 'Boletim - colag',
            'current_page' => 'boletim-26-colag',
            'user' => $this->auth->getUser(),
            'painel' => $painel,
        ]);
    }
}
