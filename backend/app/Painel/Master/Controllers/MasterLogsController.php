<?php

namespace App\Painel\Master\Controllers;

/**
 * EducaTudo — Logs da aplicação no painel Master.
 */


use App\Core\BaseController;
use App\Services\LogsAplicacaoService;

if (!class_exists(__NAMESPACE__ . '\\MasterLogsController', false)) {

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
        $arquivo = $svc->normalizarArquivo((string) ($_GET['arquivo'] ?? LogsAplicacaoService::ARQUIVO_TODOS));
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $limite = (int) ($_GET['limite'] ?? LogsAplicacaoService::LIMITE_PADRAO);
        $resultado = $svc->ultimasEntradas($arquivo, $limite, $busca);
        $errosDistintos = $svc->errosDistintos($busca, 50);

        $this->viewWithLayout('master', 'master/logs/index', [
            'title' => 'Logs do sistema - Painel Master',
            'page_title' => 'Logs do sistema',
            'current_page' => 'logs',
            'master_nome' => $_SESSION[self::SESSION_MASTER_USER_NOME] ?? 'Admin',
            'arquivos' => $arquivos,
            'arquivo' => $resultado['arquivo'],
            'linhas' => $resultado['linhas'],
            'erros_distintos' => $errosDistintos,
            'busca' => $busca,
            'limite' => max(1, min(LogsAplicacaoService::LIMITE_MAXIMO, $limite)),
            'diagnostico' => $svc->diagnostico(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function baixar(): void
    {
        $this->requireMaster();

        $alvo = basename(trim((string) ($_GET['arquivo'] ?? '')));
        $path = (new LogsAplicacaoService())->caminhoArquivo($alvo);
        if ($path === '' || !is_readable($path)) {
            $this->setFlashMessage('Não foi possível baixar o arquivo de log.', 'error');
            $this->redirect('/master/logs');
            return;
        }

        $this->enviarArquivo($path, basename($path), 'text/plain; charset=UTF-8', false);
    }

    public function baixarTodos(): void
    {
        $this->requireMaster();

        $zipPath = (new LogsAplicacaoService())->empacotarTodos();
        if ($zipPath === '') {
            $this->setFlashMessage('Não foi possível gerar o arquivo com os logs.', 'error');
            $this->redirect('/master/logs');
            return;
        }

        $this->enviarArquivo($zipPath, 'logs-' . date('Y-m-d-His') . '.zip', 'application/zip', true);
    }

    public function excluir(): void
    {
        $this->requireMaster();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente de novo.', 'error');
            $this->redirect('/master/logs');
            return;
        }

        $svc = new LogsAplicacaoService();
        $alvo = basename(trim((string) ($_POST['arquivo'] ?? '')));

        if ($alvo === LogsAplicacaoService::ARQUIVO_TODOS) {
            $resultado = $svc->excluirTodos();
            if ($resultado['ok'] === 0 && $resultado['falha'] === 0) {
                $this->setFlashMessage('Não havia arquivo de log para excluir.', 'info');
            } elseif ($resultado['falha'] > 0) {
                $this->setFlashMessage(
                    $resultado['ok'] . ' arquivo(s) excluído(s), ' . $resultado['falha'] . ' falha(s).',
                    'error'
                );
            } else {
                $this->setFlashMessage($resultado['ok'] . ' arquivo(s) de log excluído(s).', 'success');
            }
            $this->redirect('/master/logs');
            return;
        }

        if ($alvo === '') {
            $this->setFlashMessage('Informe o arquivo de log a excluir.', 'error');
            $this->redirect('/master/logs');
            return;
        }

        if ($svc->excluirArquivo($alvo)) {
            $this->setFlashMessage('Arquivo ' . $alvo . ' excluído.', 'success');
        } else {
            $this->setFlashMessage('Não foi possível excluir ' . $alvo . '.', 'error');
        }
        $this->redirect('/master/logs');
    }

    private function enviarArquivo(string $path, string $nome, string $contentType, bool $apagarDepois): void
    {
        $nome = str_replace(['"', "\r", "\n"], '', basename($nome));
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        $tamanho = @filesize($path);
        if ($tamanho !== false) {
            header('Content-Length: ' . (string) $tamanho);
        }
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        readfile($path);
        if ($apagarDepois) {
            @unlink($path);
        }
        exit;
    }
}

}

if (!class_exists('MasterLogsController', false) && class_exists(__NAMESPACE__ . '\\MasterLogsController', false)) {
    class_alias(__NAMESPACE__ . '\\MasterLogsController', 'MasterLogsController');
}
