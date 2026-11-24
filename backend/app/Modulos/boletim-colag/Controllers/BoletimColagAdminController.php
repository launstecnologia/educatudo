<?php

namespace App\Modulos\BoletimColag\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Modulos\BoletimColag\Services\BoletimColagModelo;

/**
 * Entrada do módulo Boletim Colag. Escola que não é o Colag volta ao boletim comum.
 */
class BoletimColagAdminController extends BaseController
{
    private AuthManager $auth;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            exit;
        }
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin/dashboard');
            exit;
        }
        if (!BoletimColagModelo::ativo()) {
            $this->redirect('/admin/boletins');
            exit;
        }
    }

    public function index(): void
    {
        $this->viewWithLayout('admin', 'admin/boletim-colag/index', [
            'title' => 'Boletim Colag',
            'current_page' => 'boletim-colag',
            'user' => $this->auth->getUser(),
            'semanas' => BoletimColagModelo::semanas(),
        ]);
    }
}
