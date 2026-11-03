<?php

namespace App\Modulos\LeitorGabarito\Controllers;

use App\Core\AuthManager;

if (!class_exists(__NAMESPACE__ . '\\LeitorGabaritoProfessorController', false)) {
class LeitorGabaritoProfessorController extends \App\Core\BaseController
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
        $user = $this->auth->getUser();
        return [
            'professor_id' => (int) ($user['id'] ?? 0),
            'base' => '/professor/leitor-gabarito',
            'layout' => 'professor',
            'view_perfil' => 'professor',
            'titulo_sufixo' => 'Professor',
        ];
    }

    protected function exigirAcesso(): void
    {
        $user = $this->auth->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'professor') {
            $this->redirect('/professor/dashboard');
        }
    }
}
}
