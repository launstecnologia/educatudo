<?php

namespace App\Painel\Master\Controllers;
/**
 * EducaTudo - Precificação TudiCoins (legado: custos globais).
 * UI removida — redireciona para Tabelas de preço.
 */


use App\Core\BaseController;
if (!class_exists(__NAMESPACE__ . '\\MasterPrecificacaoController', false)) {

class MasterPrecificacaoController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';

    public function __construct()
    {
        parent::__construct();
    }

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    private function redirectTabelas(): void
    {
        header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
        exit;
    }

    public function index()
    {
        $this->requireMaster();
        $this->redirectTabelas();
    }

    public function salvar()
    {
        $this->requireMaster();
        $this->redirectTabelas();
    }
}

}

if (!class_exists('MasterPrecificacaoController', false) && class_exists(__NAMESPACE__ . '\\MasterPrecificacaoController', false)) {
    class_alias(__NAMESPACE__ . '\\MasterPrecificacaoController', 'MasterPrecificacaoController');
}
