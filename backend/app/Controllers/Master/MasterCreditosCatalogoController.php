<?php
/**
 * Catálogos Master: tabelas de custo por módulo, pacotes e planos (mensalidade).
 */

require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';

if (!class_exists('MasterCreditosCatalogoController')) {

class MasterCreditosCatalogoController extends BaseController
{
    private const SESSION_MASTER_USER_ID = 'master_user_id';

    private function requireMaster(): void
    {
        if (empty($_SESSION[self::SESSION_MASTER_USER_ID])) {
            header('Location: ' . URL . '/master');
            exit;
        }
    }

    /* ---------- Tabelas de custo ---------- */

    public function tabelasIndex()
    {
        $this->requireMaster();
        $db = Database::getInstance();
        $lista = [];
        $erro = null;
        try {
            $lista = $db->fetchAll('SELECT id, nome, ativo, padrao, updated_at FROM creditos_tabela_custo ORDER BY padrao DESC, nome');
        } catch (\Throwable $e) {
            try {
                $lista = $db->fetchAll('SELECT id, nome, ativo, updated_at FROM creditos_tabela_custo ORDER BY nome');
                foreach ($lista as &$row) {
                    $row['padrao'] = 0;
                }
                unset($row);
                $erro = 'Rode a migration master 2026_07_09_tudicoins_tabela_preco_padrao_master.sql para habilitar o flag Padrão.';
            } catch (\Throwable $e2) {
                $erro = 'Execute a migration 051_creditos_catalogos_master.sql no banco master.';
            }
        }
        $this->viewWithLayout('master', 'master/creditos_catalogo/tabelas_index', [
            'title'        => 'Tabelas de preço - Master',
            'page_title'   => 'Tabelas de preço (TudiCoins)',
            'current_page' => 'creditos_catalogo_tabelas',
            'master_nome'  => $_SESSION['master_user_nome'] ?? 'Admin',
            'lista'        => $lista,
            'erro_tabela'  => $erro,
            'csrf_token'   => $this->generateCsrfToken(),
            'flash'        => $this->getFlashMessage(),
        ]);
    }

    public function tabelaCriar()
    {
        $this->requireMaster();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
            exit;
        }
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $padrao = !empty($_POST['padrao']) && (string) $_POST['padrao'] === '1';
        if ($nome === '') {
            $this->setFlashMessage('Informe o nome da tabela.', 'error');
            header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
            exit;
        }
        require_once __DIR__ . '/../../Services/MasterCreditosCatalogoSyncService.php';
        $db = Database::getInstance();
        try {
            $id = (int) $db->insert('INSERT INTO creditos_tabela_custo (nome, ativo) VALUES (?, 1)', [$nome]);
            if ($padrao && $id > 0) {
                \App\Services\MasterCreditosCatalogoSyncService::definirTabelaPadrao($id, true);
            }
        } catch (\Throwable $e) {
            $this->setFlashMessage('Erro: ' . $e->getMessage(), 'error');
            header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
            exit;
        }
        $this->setFlashMessage('Tabela criada. Defina os custos dos módulos.', 'success');
        header('Location: ' . URL . '/master/creditos-catalogo/tabelas/' . $id . '/editar');
        exit;
    }

    public function tabelaEditar($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        $db = Database::getInstance();
        try {
            $tab = $db->fetch('SELECT id, nome, ativo, padrao FROM creditos_tabela_custo WHERE id = ?', [$id]);
        } catch (\Throwable $e) {
            $tab = $db->fetch('SELECT id, nome, ativo FROM creditos_tabela_custo WHERE id = ?', [$id]);
            if ($tab) {
                $tab['padrao'] = 0;
            }
        }
        if (!$tab) {
            $this->setFlashMessage('Tabela não encontrada.', 'error');
            header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
            exit;
        }
        $itens = [];
        try {
            $rows = $db->fetchAll(
                'SELECT modulo_key, creditos, cobra, nome_exibicao FROM creditos_tabela_custo_item WHERE tabela_id = ?',
                [$id]
            );
            foreach ($rows as $r) {
                $itens[$r['modulo_key']] = $r;
            }
        } catch (\Throwable $e) {
            $itens = [];
        }
        $labels = CreditosModuleRegistry::getModuleLabels();
        $this->viewWithLayout('master', 'master/creditos_catalogo/tabela_editar', [
            'title'        => 'Editar tabela de preço - Master',
            'page_title'   => 'Tabela: ' . $tab['nome'],
            'current_page' => 'creditos_catalogo_tabelas',
            'master_nome'  => $_SESSION['master_user_nome'] ?? 'Admin',
            'tabela'       => $tab,
            'itens'        => $itens,
            'labels'       => $labels,
            'csrf_token'   => $this->generateCsrfToken(),
            'flash'        => $this->getFlashMessage(),
        ]);
    }

    public function tabelaDefinirPadrao($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
            exit;
        }
        require_once __DIR__ . '/../../Services/MasterCreditosCatalogoSyncService.php';
        $padrao = !empty($_POST['padrao']) && (string) $_POST['padrao'] === '1';
        try {
            $db = Database::getInstance();
            $tab = $db->fetch('SELECT id, ativo FROM creditos_tabela_custo WHERE id = ?', [$id]);
            if (!$tab) {
                $this->setFlashMessage('Tabela não encontrada.', 'error');
                header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
                exit;
            }
            if ($padrao && empty($tab['ativo'])) {
                $this->setFlashMessage('Ative a tabela antes de marcá-la como padrão.', 'error');
                header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
                exit;
            }
            \App\Services\MasterCreditosCatalogoSyncService::definirTabelaPadrao($id, $padrao);
            $this->setFlashMessage(
                $padrao ? 'Tabela marcada como padrão para escolas novas.' : 'Tabela removida do padrão.',
                'success'
            );
        } catch (\Throwable $e) {
            $this->setFlashMessage('Erro: rode a migration da coluna padrao. ' . $e->getMessage(), 'error');
        }
        header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
        exit;
    }

    public function tabelaSalvarItens($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
            exit;
        }
        $db = Database::getInstance();
        $tab = $db->fetch('SELECT id FROM creditos_tabela_custo WHERE id = ?', [$id]);
        if (!$tab) {
            $this->setFlashMessage('Tabela não encontrada.', 'error');
            header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/creditos-catalogo/tabelas/' . $id . '/editar');
            exit;
        }
        try {
            foreach (CreditosModuleRegistry::getModuleKeys() as $mk) {
                $creditos = isset($_POST['creditos'][$mk])
                    ? CreditosDecimalHelper::parsePost($_POST['creditos'][$mk])
                    : 1.0;
                if ($creditos < 0) {
                    $creditos = 0.0;
                }
                $cobra = !empty($_POST['cobra'][$mk]) ? 1 : 0;
                $nomeEx = trim((string) ($_POST['nome_exibicao'][$mk] ?? ''));
                if ($nomeEx === '') {
                    $nomeEx = CreditosModuleRegistry::getLabel($mk);
                }
                $db->query(
                    'INSERT INTO creditos_tabela_custo_item (tabela_id, modulo_key, creditos, cobra, nome_exibicao) VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE creditos = VALUES(creditos), cobra = VALUES(cobra), nome_exibicao = VALUES(nome_exibicao)',
                    [$id, $mk, $creditos, $cobra, $nomeEx]
                );
            }
            $this->setFlashMessage('Tabela de custo salva.', 'success');
        } catch (\Throwable $e) {
            $this->setFlashMessage('Erro ao salvar: ' . $e->getMessage(), 'error');
        }
        header('Location: ' . URL . '/master/creditos-catalogo/tabelas/' . $id . '/editar');
        exit;
    }

    public function tabelaToggle($id)
    {
        $this->requireMaster();
        $id = (int) $id;
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
            exit;
        }
        $db = Database::getInstance();
        $db->query(
            'UPDATE creditos_tabela_custo SET ativo = IF(ativo = 1, 0, 1), updated_at = NOW() WHERE id = ?',
            [$id]
        );
        header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
        exit;
    }

    /* ---------- Pacotes catálogo ---------- */

    public function pacotesIndex()
    {
        $this->requireMaster();
        $db = Database::getInstance();
        $lista = [];
        $erro = null;
        try {
            $lista = $db->fetchAll('SELECT id, nome, creditos, valor_centavos, ativo FROM creditos_pacotes_catalogo ORDER BY nome');
        } catch (\Throwable $e) {
            $erro = 'Execute a migration 051_creditos_catalogos_master.sql no banco master.';
        }
        $this->viewWithLayout('master', 'master/creditos_catalogo/pacotes_index', [
            'title'        => 'Pacotes catálogo - Master',
            'page_title'   => 'Pacotes de crédito (catálogo)',
            'current_page' => 'creditos_catalogo_pacotes',
            'master_nome'  => $_SESSION['master_user_nome'] ?? 'Admin',
            'lista'        => $lista,
            'erro_tabela'  => $erro,
            'csrf_token'   => $this->generateCsrfToken(),
            'flash'        => $this->getFlashMessage(),
        ]);
    }

    public function pacoteDados($id)
    {
        $this->requireMaster();
        header('Content-Type: application/json; charset=utf-8');
        $id = (int) $id;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $row = Database::getInstance()->fetch(
                'SELECT id, nome, creditos, valor_centavos, ativo FROM creditos_pacotes_catalogo WHERE id = ?',
                [$id]
            );
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Catálogo indisponível.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Pacote não encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $creditos = CreditosDecimalHelper::fromScalar($row['creditos'] ?? 0, 0.0);
        echo json_encode([
            'success' => true,
            'item' => [
                'id' => (int) $row['id'],
                'nome' => (string) ($row['nome'] ?? ''),
                'creditos' => CreditosDecimalHelper::formatInput($creditos),
                'valor_reais' => number_format(((int) ($row['valor_centavos'] ?? 0)) / 100, 2, '.', ''),
                'ativo' => !empty($row['ativo']) ? 1 : 0,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function pacoteSalvar()
    {
        $this->requireMaster();
        $wantsJson = $this->wantsJsonResponse();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            if ($wantsJson) {
                $this->jsonOut(['success' => false, 'error' => 'Sessão expirada. Recarregue a página.']);
            }
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/creditos-catalogo/pacotes');
            exit;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $creditos = CreditosDecimalHelper::parsePost($_POST['creditos'] ?? 0);
        $valorReaisRaw = $_POST['valor_reais'] ?? null;
        if ($valorReaisRaw !== null && $valorReaisRaw !== '') {
            $vc = max(0, (int) round(((float) str_replace(',', '.', (string) $valorReaisRaw)) * 100));
        } else {
            $vc = max(0, (int) ($_POST['valor_centavos'] ?? 0));
        }
        $ativo = isset($_POST['ativo']) && (string) $_POST['ativo'] === '1' ? 1 : 0;
        if ($nome === '' || $creditos <= 0 || $vc <= 0) {
            if ($wantsJson) {
                $this->jsonOut(['success' => false, 'error' => 'Nome, TudiCoins (>0) e valor (R$) são obrigatórios.']);
            }
            $this->setFlashMessage('Nome, créditos (>0) e valor são obrigatórios.', 'error');
            header('Location: ' . URL . '/master/creditos-catalogo/pacotes');
            exit;
        }
        try {
            $db = Database::getInstance();
            if ($id > 0) {
                $db->query(
                    'UPDATE creditos_pacotes_catalogo SET nome = ?, creditos = ?, valor_centavos = ?, ativo = ?, updated_at = NOW() WHERE id = ?',
                    [$nome, $creditos, $vc, $ativo, $id]
                );
                $msg = 'Pacote atualizado.';
            } else {
                $db->insert(
                    'INSERT INTO creditos_pacotes_catalogo (nome, creditos, valor_centavos, ativo) VALUES (?, ?, ?, 1)',
                    [$nome, $creditos, $vc]
                );
                $msg = 'Pacote criado no catálogo.';
            }
            if ($wantsJson) {
                $this->jsonOut(['success' => true, 'message' => $msg]);
            }
            $this->setFlashMessage($msg, 'success');
        } catch (\Throwable $e) {
            if ($wantsJson) {
                $this->jsonOut(['success' => false, 'error' => $e->getMessage()]);
            }
            $this->setFlashMessage('Erro: ' . $e->getMessage(), 'error');
        }
        header('Location: ' . URL . '/master/creditos-catalogo/pacotes');
        exit;
    }

    public function pacoteToggle()
    {
        $this->requireMaster();
        $pid = (int) ($_POST['id'] ?? 0);
        $wantsJson = $this->wantsJsonResponse();
        if ($pid <= 0) {
            if ($wantsJson) {
                $this->jsonOut(['success' => false, 'error' => 'ID inválido.']);
            }
            header('Location: ' . URL . '/master/creditos-catalogo/pacotes');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            if ($wantsJson) {
                $this->jsonOut(['success' => false, 'error' => 'Sessão expirada.']);
            }
            $this->setFlashMessage('Sessão expirada. Recarregue a página e tente novamente.', 'error');
            header('Location: ' . URL . '/master/creditos-catalogo/pacotes');
            exit;
        }
        Database::getInstance()->query(
            'UPDATE creditos_pacotes_catalogo SET ativo = IF(ativo = 1, 0, 1), updated_at = NOW() WHERE id = ?',
            [$pid]
        );
        if ($wantsJson) {
            $this->jsonOut(['success' => true]);
        }
        header('Location: ' . URL . '/master/creditos-catalogo/pacotes');
        exit;
    }

    /* ---------- Planos catálogo ---------- */

    public function planosIndex()
    {
        $this->requireMaster();
        header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
        exit;
    }

    public function planoDados($id)
    {
        $this->requireMaster();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Planos do catálogo foram removidos da UI.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function planoSalvar()
    {
        $this->requireMaster();
        header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
        exit;
    }

    public function planoToggle()
    {
        $this->requireMaster();
        header('Location: ' . URL . '/master/creditos-catalogo/tabelas');
        exit;
    }

    private function wantsJsonResponse(): bool
    {
        if (!empty($_POST['ajax']) && (string) $_POST['ajax'] === '1') {
            return true;
        }
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        return stripos($accept, 'application/json') !== false;
    }

    /** @param array<string,mixed> $payload */
    private function jsonOut(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

}
