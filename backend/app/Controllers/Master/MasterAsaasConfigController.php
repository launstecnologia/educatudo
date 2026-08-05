<?php
/**
 * Configuração Asaas no Master + reconciliação manual.
 */

if (!class_exists('MasterAsaasConfigController')) {

class MasterAsaasConfigController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';

    private function getPublicBaseUrl(): string
    {
        $configured = rtrim((defined('URL') ? (string) URL : ''), '/');
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return $configured;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((string) ($_SERVER['REQUEST_SCHEME'] ?? '') === 'https');

        $scheme = $https ? 'https' : 'http';
        return $scheme . '://' . $host;
    }

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    public function index()
    {
        $this->requireMaster();
        $db = Database::getInstance();
        $row = null;
        $erro = null;
        try {
            $row = $db->fetch('SELECT environment, webhook_token, updated_at FROM asaas_master_config WHERE id = 1');
        } catch (\Throwable $e) {
            $erro = 'Execute a migration 050_asaas_integracao_master.sql no banco master.';
        }
        $webhookPath = '/master/webhooks/asaas';
        $publicBaseUrl = $this->getPublicBaseUrl();
        $webhookFull = rtrim($publicBaseUrl, '/') . $webhookPath;

        $escolas = [];
        try {
            $escolas = $db->fetchAll(
                'SELECT e.id, e.nome FROM escolas e
                 INNER JOIN config_escolas_banco b ON b.escola_id = e.id
                 ORDER BY e.nome'
            );
        } catch (\Throwable $e) {
            $escolas = [];
        }

        $this->viewWithLayout('master', 'master/asaas/index', [
            'title'          => 'Asaas — Master',
            'page_title'     => 'Integração Asaas (créditos)',
            'current_page'   => 'asaas',
            'master_nome'    => $_SESSION['master_user_nome'] ?? 'Admin',
            'cfg'            => $row,
            'erro_tabela'    => $erro,
            'public_base_url'=> $publicBaseUrl,
            'webhook_url'    => $webhookFull,
            'flash'          => $this->getFlashMessage(),
            'escolas'        => $escolas,
            'csrf_token'     => $this->generateCsrfToken(),
        ]);
    }

    public function salvar()
    {
        $this->requireMaster();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ' . URL . '/master/asaas');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/asaas');
            exit;
        }
        require_once __DIR__ . '/../../Core/MasterSecretVault.php';
        $db = Database::getInstance();
        $env = trim((string) ($_POST['environment'] ?? 'sandbox'));
        if (!in_array($env, ['sandbox', 'production'], true)) {
            $env = 'sandbox';
        }
        $webhookToken = trim((string) ($_POST['webhook_token'] ?? ''));
        $apiKeyPlain = trim((string) ($_POST['api_key'] ?? ''));

        try {
            if ($apiKeyPlain !== '') {
                $enc = MasterSecretVault::encrypt($apiKeyPlain);
                $db->query(
                    'UPDATE asaas_master_config SET api_key_encrypted = ?, environment = ?, webhook_token = ?, updated_at = NOW() WHERE id = 1',
                    [$enc, $env, $webhookToken]
                );
            } else {
                $db->query(
                    'UPDATE asaas_master_config SET environment = ?, webhook_token = ?, updated_at = NOW() WHERE id = 1',
                    [$env, $webhookToken]
                );
            }
        } catch (\Throwable $e) {
            $this->setFlashMessage('Erro ao salvar: ' . $e->getMessage(), 'error');
            header('Location: ' . URL . '/master/asaas');
            exit;
        }
        $this->setFlashMessage('Configuração Asaas salva.', 'success');
        header('Location: ' . URL . '/master/asaas');
        exit;
    }

    public function reconciliar()
    {
        $this->requireMaster();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ' . URL . '/master/asaas');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/asaas');
            exit;
        }
        require_once __DIR__ . '/../../Services/CreditosAsaasReconcileService.php';
        $only = isset($_POST['escola_id']) ? (int) $_POST['escola_id'] : 0;
        $onlyEscola = $only > 0 ? $only : null;
        $res = \App\Services\CreditosAsaasReconcileService::run($onlyEscola);
        $msg = sprintf(
            'Reconciliação: verificadas %d cobranças, creditadas %d, ignoradas %d.',
            $res['checked'],
            $res['fulfilled'],
            $res['skipped']
        );
        if (!empty($res['errors'])) {
            $msg .= ' Avisos: ' . implode(' | ', array_slice($res['errors'], 0, 5));
            $this->setFlashMessage($msg, 'error');
        } else {
            $this->setFlashMessage($msg, 'success');
        }
        header('Location: ' . URL . '/master/asaas');
        exit;
    }

    /**
     * Job/cron: GET com ASAAS_RECONCILE_KEY no .env igual ao parâmetro ?key=
     */
    public function reconciliarCron()
    {
        $expected = function_exists('env') ? trim((string) env('ASAAS_RECONCILE_KEY', '')) : '';
        $got = trim((string) ($_GET['key'] ?? ''));
        if ($expected === '' || !hash_equals($expected, $got)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        require_once __DIR__ . '/../../Services/CreditosAsaasReconcileService.php';
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(\App\Services\CreditosAsaasReconcileService::run(null), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Job/cron: cancela pending > N minutos (default 60).
     * GET ?key=ASAAS_RECONCILE_KEY&minutos=60
     */
    public function cancelarPendentesCron()
    {
        $expected = function_exists('env') ? trim((string) env('ASAAS_RECONCILE_KEY', '')) : '';
        $got = trim((string) ($_GET['key'] ?? ''));
        if ($expected === '' || !hash_equals($expected, $got)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        require_once __DIR__ . '/../../Services/CreditosAsaasCancelarPendentesService.php';
        $minutos = isset($_GET['minutos']) && is_numeric($_GET['minutos'])
            ? max(1, (int) $_GET['minutos'])
            : \App\Services\CreditosAsaasCancelarPendentesService::MINUTOS_PADRAO;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            \App\Services\CreditosAsaasCancelarPendentesService::run($minutos),
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }
}

}
