<?php

namespace App\Painel\Master\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Core\MasterSecretVault;
use App\Modulos\Lex\Services\LexSincronizacaoService;
use App\Painel\Master\Services\MasterFilaService;

if (!class_exists(__NAMESPACE__ . '\\MasterLexController', false)) {

class MasterLexController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';

    public function index($id): void
    {
        $escolaId = (int) $id;
        $this->render($escolaId);
    }

    public function salvar($id): void
    {
        $escolaId = (int) $id;
        $this->requireMaster();
        $this->exigirCsrf($escolaId);
        $this->escolaExiste($escolaId);

        $db = Database::getInstance();
        if (!$this->tabelaConfig($db->getPdo())) {
            $this->setFlashMessage('Rode a migration 2026_10_26_lex_integracao_master.sql no banco master.', 'error');
            $this->voltar($escolaId);
        }

        $ambiente = (string) ($_POST['ambiente'] ?? 'homologacao');
        if (!in_array($ambiente, ['homologacao', 'producao'], true)) {
            $ambiente = 'homologacao';
        }
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $clientId = substr(trim((string) ($_POST['client_id'] ?? '')), 0, 255);
        $tenantId = substr(trim((string) ($_POST['tenant_id'] ?? '')), 0, 64);
        $escolaLexId = substr(trim((string) ($_POST['escola_lex_id'] ?? '')), 0, 64);
        $externalId = substr(trim((string) ($_POST['external_id'] ?? '')), 0, 128);
        $segredoNovo = trim((string) ($_POST['client_secret'] ?? ''));

        if ($ativo === 1 && ($clientId === '' || $tenantId === '' || ($escolaLexId === '' && $externalId === ''))) {
            $this->setFlashMessage('Para ativar, informe client id, tenant e o id ou externalId da escola na LEX.', 'error');
            $this->voltar($escolaId);
        }

        $atual = $db->fetch('SELECT client_secret_cifrado FROM lex_escolas_config WHERE escola_id = ?', [$escolaId]);
        $segredo = is_array($atual) ? ($atual['client_secret_cifrado'] ?? null) : null;
        if ($segredoNovo !== '') {
            $cifrado = MasterSecretVault::encrypt($segredoNovo);
            if ($cifrado === null) {
                $this->setFlashMessage('Não foi possível proteger o client secret.', 'error');
                $this->voltar($escolaId);
            }
            $segredo = $cifrado;
        }
        if ($ativo === 1 && ($segredo === null || $segredo === '')) {
            $this->setFlashMessage('Informe o client secret para ativar a integração.', 'error');
            $this->voltar($escolaId);
        }

        $st = $db->getPdo()->prepare(
            'INSERT INTO lex_escolas_config
                (escola_id, ativo, ambiente, client_id, client_secret_cifrado, tenant_id, escola_lex_id, external_id, direcao)
             VALUES
                (:escola_id, :ativo, :ambiente, :client_id, :segredo, :tenant_id, :escola_lex_id, :external_id, :direcao)
             ON DUPLICATE KEY UPDATE
                ativo = VALUES(ativo),
                ambiente = VALUES(ambiente),
                client_id = VALUES(client_id),
                client_secret_cifrado = VALUES(client_secret_cifrado),
                tenant_id = VALUES(tenant_id),
                escola_lex_id = VALUES(escola_lex_id),
                external_id = VALUES(external_id)'
        );
        $st->execute([
            'escola_id' => $escolaId,
            'ativo' => $ativo,
            'ambiente' => $ambiente,
            'client_id' => $clientId,
            'segredo' => $segredo,
            'tenant_id' => $tenantId,
            'escola_lex_id' => $escolaLexId,
            'external_id' => $externalId,
            'direcao' => 'lex_para_educatudo',
        ]);

        $this->setFlashMessage('Configuração LEX salva.', 'success');
        $this->voltar($escolaId);
    }

    public function testar($id): void
    {
        $escolaId = (int) $id;
        $this->requireMaster();
        $this->exigirCsrf($escolaId);
        $this->escolaExiste($escolaId);
        try {
            $resultado = LexSincronizacaoService::testar(Database::getInstance()->getPdo(), $escolaId);
            $texto = 'Conexão ok. Token ' . ($resultado['reutilizado'] ? 'reutilizado' : 'novo')
                . ', tipo ' . $resultado['tipo_token']
                . ', expira em ' . (int) $resultado['expira_em'] . 's. '
                . (int) $resultado['tenants'] . ' tenant(s). '
                . ($resultado['tenant_autorizado'] ? 'O tenant configurado está autorizado.' : 'O tenant configurado não está na lista autorizada.');
            $this->setFlashMessage($texto, $resultado['tenant_autorizado'] ? 'success' : 'warning');
        } catch (\Throwable $e) {
            $this->setFlashMessage($e->getMessage(), 'error');
        }
        $this->voltar($escolaId);
    }

    public function simular($id): void
    {
        $this->enfileirar((int) $id, 'simulacao');
    }

    public function sincronizar($id): void
    {
        $this->enfileirar((int) $id, 'real');
    }

    private function enfileirar(int $escolaId, string $modo): void
    {
        $this->requireMaster();
        $this->exigirCsrf($escolaId);
        $this->escolaExiste($escolaId);
        $pdo = Database::getInstance()->getPdo();
        $config = LexSincronizacaoService::lerConfig($pdo, $escolaId);
        if ($config === null || !$config['ativo']) {
            $this->setFlashMessage('Ative e salve a integração LEX desta escola antes de sincronizar.', 'error');
            $this->voltar($escolaId);
        }
        if (!MasterFilaService::tabelaExiste($pdo)) {
            $this->setFlashMessage('A fila do master não está disponível.', 'error');
            $this->voltar($escolaId);
        }
        $st = $pdo->prepare(
            "SELECT id FROM fila_jobs_master
              WHERE tipo = :tipo AND escola_destino_id = :escola AND status IN ('pending','processing')
              LIMIT 1"
        );
        $st->execute(['tipo' => MasterFilaService::TIPO_LEX_SINCRONIZAR, 'escola' => $escolaId]);
        if ($st->fetch(\PDO::FETCH_ASSOC)) {
            $this->setFlashMessage('Já existe uma sincronização LEX em andamento para esta escola.', 'warning');
            $this->voltar($escolaId);
        }

        MasterFilaService::enfileirar(
            MasterFilaService::TIPO_LEX_SINCRONIZAR,
            ['escola_id' => $escolaId, 'modo' => $modo],
            null,
            $escolaId,
            (int) ($_SESSION[self::SESSION_MASTER_USER_ID] ?? 0) ?: null
        );
        $this->setFlashMessage(
            $modo === 'simulacao'
                ? 'Simulação enfileirada. Atualize a página para ver o resultado.'
                : 'Sincronização enfileirada. Atualize a página para ver o resultado.',
            'success'
        );
        $this->voltar($escolaId);
    }

    private function render(int $escolaId): void
    {
        $this->requireMaster();
        $escola = $this->escolaExiste($escolaId);
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $erroTabela = $this->tabelaConfig($pdo)
            ? null
            : 'Rode a migration 2026_10_26_lex_integracao_master.sql no banco master.';
        $config = null;
        $historico = ['sincronizacao' => null, 'eventos' => [], 'erro' => null];
        $job = null;
        if ($erroTabela === null) {
            $config = LexSincronizacaoService::lerConfig($pdo, $escolaId);
            if ($config !== null) {
                unset($config['client_secret']);
            }
            try {
                $tenant = LexSincronizacaoService::abrirTenant($pdo, $escolaId);
                $historico = LexSincronizacaoService::historico($tenant);
            } catch (\Throwable $e) {
                $historico['erro'] = $e->getMessage();
            }
            if (MasterFilaService::tabelaExiste($pdo)) {
                $st = $pdo->prepare(
                    "SELECT id, status, mensagem_erro, resultado, created_at
                       FROM fila_jobs_master
                      WHERE tipo = :tipo AND escola_destino_id = :escola
                      ORDER BY id DESC LIMIT 1"
                );
                $st->execute(['tipo' => MasterFilaService::TIPO_LEX_SINCRONIZAR, 'escola' => $escolaId]);
                $job = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
        }

        $data = [
            'title' => ($escola['nome'] ?? 'Escola') . ' - LEX',
            'page_title' => $escola['nome'] ?? 'Escola',
            'current_page' => 'escolas',
            'current_section' => 'lex',
            'section_view' => 'lex',
            'master_nome' => $_SESSION['master_user_nome'] ?? 'Admin',
            'escola' => $escola,
            'escola_id' => $escolaId,
            'layout_config' => [],
            'flash' => $this->getFlashMessage(),
            'csrf_token' => $this->generateCsrfToken(),
            'config' => $config,
            'erro_tabela' => $erroTabela,
            'historico' => $historico,
            'job' => $job,
        ];

        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fragment') {
            header('Cache-Control: no-store');
            $this->view('master/escolas/detail/lex', $data);
            return;
        }
        $this->viewWithLayout('master', 'master/escolas/detail-layout', $data);
    }

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    private function exigirCsrf(int $escolaId): void
    {
        if ($this->verifyCsrfToken($_POST['_token'] ?? '')) {
            return;
        }
        $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
        $this->voltar($escolaId);
    }

    /**
     * @return array<string,mixed>
     */
    private function escolaExiste(int $escolaId): array
    {
        $escola = Database::getInstance()->fetch('SELECT * FROM escolas WHERE id = ?', [$escolaId]);
        if (!$escola) {
            $this->setFlashMessage('Escola não encontrada.', 'error');
            header('Location: ' . URL . '/master/escolas');
            exit;
        }
        return $escola;
    }

    private function tabelaConfig(\PDO $pdo): bool
    {
        $st = $pdo->query("SHOW TABLES LIKE 'lex_escolas_config'");
        return $st !== false && $st->fetchColumn() !== false;
    }

    private function voltar(int $escolaId): void
    {
        header('Location: ' . URL . '/master/escolas/' . $escolaId . '/lex');
        exit;
    }
}

}

if (!class_exists('MasterLexController', false) && class_exists(__NAMESPACE__ . '\\MasterLexController', false)) {
    class_alias(__NAMESPACE__ . '\\MasterLexController', 'MasterLexController');
}
