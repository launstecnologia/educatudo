<?php

namespace App\Modulos\Comunicacao\Controllers;

/**
 * Hub do menu Comunicação (admin).
 */
class ComunicacaoAdminController extends \App\Controllers\Admin\AdminBaseController
{
    public function index(): void
    {
        $user = $this->auth->getUser();
        $this->viewWithLayout('admin', 'admin/comunicacao/index', [
            'title' => 'Comunicação - EducaTudo',
            'user' => $user,
            'current_page' => 'comunicacao',
        ]);
    }
}
