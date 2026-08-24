<?php

/**
 * EducaTudo — Logs da aplicação no painel Master (somente leitura).
 */

require_once dirname(__DIR__, 2) . '/Services/LogsAplicacaoService.php';

if (!class_exists('MasterLogsController')) {

class MasterLogsController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';
    private const SESSION_MASTER_USER_NOME = 'master_user_nome';

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    public function index(): void
    {
        $this->requireMaster();

        $svc = new LogsAplicacaoService();
        $arquivos = $svc->arquivosDisponiveis();
        $arquivo = $svc->normalizarArquivo((string) ($_GET['arquivo'] ?? 'error.log'));
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $limite = (int) ($_GET['limite'] ?? LogsAplicacaoService::LIMITE_PADRAO);
        $resultado = $svc->ultimasEntradas($arquivo, $limite, $busca);

        $this->viewWithLayout('master', 'master/logs/index', [
            'title' => 'Logs do sistema - Painel Master',
            'page_title' => 'Logs do sistema',
            'current_page' => 'logs',
            'master_nome' => $_SESSION[self::SESSION_MASTER_USER_NOME] ?? 'Admin',
            'arquivos' => $arquivos,
            'arquivo' => $resultado['arquivo'],
            'linhas' => $resultado['linhas'],
            'busca' => $busca,
            'limite' => max(1, min(LogsAplicacaoService::LIMITE_MAXIMO, $limite)),
        ]);
    }
}

}
