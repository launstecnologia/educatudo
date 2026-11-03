<?php

namespace App\Modulos\LeitorGabarito\Controllers;

use App\Core\AuthManager;

if (!class_exists(__NAMESPACE__ . '\\LeitorGabaritoAdminController', false)) {
class LeitorGabaritoAdminController extends \App\Core\BaseController
{
    use AcoesLeitorGabarito;

    private AuthManager $auth;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
    }

    protected function contexto(): array
    {
        return [
            'professor_id' => null,
            'base' => '/admin/leitor-gabarito',
            'layout' => 'admin',
            'view_perfil' => 'admin',
            'titulo_sufixo' => 'Admin',
        ];
    }

    protected function exigirAcesso(): void
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin/dashboard');
        }
    }
}
}
