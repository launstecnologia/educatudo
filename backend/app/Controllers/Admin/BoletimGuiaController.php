<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';

/**
 * Hub modular + tutorial para coordenação montar o boletim (jornadas, provas, fórmula).
 */
class BoletimGuiaController extends BaseController
{
    private $auth;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        if (!$this->auth->isLoggedIn() || !$this->usuarioPodeConfigurarBoletim($user)) {
            $this->redirect(URL . '/admin');
            exit;
        }
        if (($user['perfil_admin'] ?? '') === 'financeiro') {
            $this->redirect(URL . '/admin/dashboard');
            exit;
        }
    }

    private function usuarioPodeConfigurarBoletim(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        if (($user['tipo'] ?? '') === 'admin') {
            return true;
        }
        if (($user['tipo'] ?? '') === 'admin_escola'
            && in_array($user['perfil_admin'] ?? '', ['dev', 'diretor', 'coordenador'], true)) {
            return true;
        }

        return false;
    }

    public function index()
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/boletim_guia/index', [
            'title' => 'Guia do Boletim (coordenação) - EducaTudo',
            'user' => $user,
            'current_page' => 'boletim_guia',
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }
}
