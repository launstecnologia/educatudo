<?php

namespace App\Modulos\EnacColag\Controllers;

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\FeatureGate;
use App\Modulos\Provas\Controllers\TeacherExamController;

/**
 * ENAC Colag — entrada HTTP da importação de pontuação ENAC no lançamento de notas.
 */
class EnacColagAdminController extends BaseController
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
        if (!FeatureGate::isModuleEnabled('enac_colag')) {
            $this->setFlashMessage('O módulo ENAC Colag não está habilitado nesta escola.', 'error');
            $this->redirect('/admin/provas');
            exit;
        }
    }

    /**
     * POST /admin/provas/blocos/{id}/importar-enac-colag
     */
    public function importarNoBloco($blocoId): void
    {
        (new TeacherExamController())->importarEnacColag($blocoId);
    }
}
