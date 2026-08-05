<?php
/**
 * Admin — carteira institucional TudiCoins (compra Asaas para a escola).
 */

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Core/LayoutHelper.php';
require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
require_once __DIR__ . '/../../Services/CreditosService.php';
require_once __DIR__ . '/../../Services/TenantCreditsCheckoutService.php';
require_once __DIR__ . '/../../Services/CreditosTenantCheckoutHelper.php';

if (!class_exists('AdminCreditosController')) {
class AdminCreditosController extends AdminBaseController
{
    private function requireTudiCoinsEscolaCompra(): void
    {
        if (LayoutHelper::get('creditos_habilitado', '0') !== '1') {
            $this->setFlashMessage('TudiCoins não está habilitado para esta escola.', 'info');
            $this->redirect('/admin/dashboard');
            exit;
        }
        if (LayoutHelper::get('creditos_liberar_escola_comprar', '0') !== '1') {
            $this->setFlashMessage('A compra de TudiCoins pela escola não está liberada. Solicite ao Master.', 'info');
            $this->redirect('/admin/dashboard');
            exit;
        }
    }

    public function carteiraEscola(): void
    {
        $this->requireTudiCoinsEscolaCompra();
        $user = $this->auth->getUser();
        $svc = new \App\Services\CreditosService();
        $wallet = $svc->getWalletSaldos('escola', \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID);
        $movimentacoes = $svc->getMovimentacoesFiltradas(
            'escola',
            \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID,
            40,
            0
        );
        try {
            $pacotes = $this->db->fetchAll(
                "SELECT id, nome, creditos, valor_centavos FROM pacotes_creditos WHERE ativo = 1 ORDER BY creditos ASC"
            );
        } catch (Exception $e) {
            $pacotes = [];
        }

        $this->viewWithLayout('admin', 'admin/creditos/carteira_escola', [
            'title' => 'TudiCoins da Escola - EducaTudo',
            'user' => $user,
            'wallet' => $wallet,
            'movimentacoes' => $movimentacoes,
            'pacotes' => $pacotes,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'tudicoins_escola',
            'modo_pool' => LayoutHelper::creditosModoPoolEscola(),
        ]);
    }

    public function comprar(): void
    {
        $this->requireTudiCoinsEscolaCompra();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/tudicoins');
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/tudicoins');
            return;
        }
        $pacoteId = (int) ($_POST['pacote_id'] ?? 0);
        $pacote = $this->db->fetch(
            "SELECT id, creditos, valor_centavos, nome FROM pacotes_creditos WHERE id = ? AND ativo = 1",
            [$pacoteId]
        );
        if (!$pacote) {
            $this->setFlashMessage('Pacote inválido.', 'error');
            $this->redirect('/admin/tudicoins');
            return;
        }
        $compraId = (int) $this->db->insert(
            "INSERT INTO compras_creditos (user_type, user_id, pacote_id, valor_centavos, status)
             VALUES ('escola', ?, ?, ?, 'pending')",
            [
                \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID,
                $pacoteId,
                (int) $pacote['valor_centavos'],
            ]
        );
        $this->redirect('/admin/tudicoins/aguardando/' . $compraId);
    }

    public function aguardando($compraId): void
    {
        $this->requireTudiCoinsEscolaCompra();
        $user = $this->auth->getUser();
        $compraId = (int) $compraId;
        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos, p.nome AS pacote_nome
             FROM compras_creditos c
             JOIN pacotes_creditos p ON p.id = c.pacote_id
             WHERE c.id = ? AND c.user_type = 'escola' AND c.user_id = ?",
            [$compraId, \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID]
        );
        if (!$compra) {
            $this->setFlashMessage('Compra não encontrada.', 'error');
            $this->redirect('/admin/tudicoins');
            return;
        }
        if (($compra['status'] ?? '') === 'paid') {
            $this->setFlashMessage('Pagamento confirmado. TudiCoins creditados na carteira da escola.', 'success');
            $this->redirect('/admin/tudicoins');
            return;
        }

        $this->viewWithLayout('admin', 'admin/creditos/aguardando', [
            'title' => 'Aguardando pagamento - EducaTudo',
            'user' => $user,
            'compra' => $compra,
            'current_page' => 'tudicoins_escola',
            'pagar_action' => URL . '/admin/tudicoins/pagar/' . $compraId,
            'verificar_action' => URL . '/admin/tudicoins/verificar/' . $compraId,
            'status_action' => URL . '/admin/tudicoins/status/' . $compraId,
            'csrf_token' => $this->generateCsrfToken(),
            'pix_checkout' => null,
            'erro_pagamento' => null,
            'pagador' => [
                'nome' => (string) ($user['nome'] ?? 'Escola'),
                'email' => (string) ($user['email'] ?? ''),
                'cpf_cnpj' => '',
                'phone' => '',
            ],
        ]);
    }

    public function pagar($compraId): void
    {
        $this->requireTudiCoinsEscolaCompra();
        $user = $this->auth->getUser();
        $compraId = (int) $compraId;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/tudicoins/aguardando/' . $compraId);
            return;
        }
        $billingType = strtoupper(trim((string) ($_POST['billing_type'] ?? 'PIX')));
        if (!in_array($billingType, ['PIX', 'CREDIT_CARD'], true)) {
            $billingType = 'PIX';
        }
        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos, p.nome AS pacote_nome
             FROM compras_creditos c
             JOIN pacotes_creditos p ON p.id = c.pacote_id
             WHERE c.id = ? AND c.user_type = 'escola' AND c.user_id = ?",
            [$compraId, \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID]
        );
        if (!$compra || ($compra['status'] ?? '') !== 'pending') {
            $this->setFlashMessage('Compra não encontrada ou já processada.', 'error');
            $this->redirect('/admin/tudicoins');
            return;
        }

        $escolaId = \App\Services\CreditosTenantCheckoutHelper::escolaIdFromConfig($this->config);
        $result = \App\Services\TenantCreditsCheckoutService::createOrReuse(
            $this->db->getPdo(),
            $escolaId,
            $compra,
            $billingType,
            [
                'nome' => trim((string) ($_POST['payer_nome'] ?? ($user['nome'] ?? 'Escola'))),
                'email' => trim((string) ($_POST['payer_email'] ?? ($user['email'] ?? 'sem-email@educatudo.local'))),
                'cpf_cnpj' => trim((string) ($_POST['payer_cpf_cnpj'] ?? '')),
                'phone' => trim((string) ($_POST['payer_phone'] ?? '')),
            ]
        );

        $pagador = [
            'nome' => trim((string) ($_POST['payer_nome'] ?? ($user['nome'] ?? ''))),
            'email' => trim((string) ($_POST['payer_email'] ?? ($user['email'] ?? ''))),
            'cpf_cnpj' => trim((string) ($_POST['payer_cpf_cnpj'] ?? '')),
            'phone' => trim((string) ($_POST['payer_phone'] ?? '')),
        ];

        if (!($result['ok'] ?? false)) {
            $this->viewWithLayout('admin', 'admin/creditos/aguardando', [
                'title' => 'Aguardando pagamento - EducaTudo',
                'user' => $user,
                'compra' => $compra,
                'current_page' => 'tudicoins_escola',
                'pagar_action' => URL . '/admin/tudicoins/pagar/' . $compraId,
                'verificar_action' => URL . '/admin/tudicoins/verificar/' . $compraId,
                'status_action' => URL . '/admin/tudicoins/status/' . $compraId,
                'csrf_token' => $this->generateCsrfToken(),
                'pix_checkout' => null,
                'erro_pagamento' => $result['message'] ?? 'Falha ao iniciar pagamento.',
                'pagador' => $pagador,
            ]);
            return;
        }

        if ($billingType === 'PIX' && !empty($result['pix'])) {
            $this->viewWithLayout('admin', 'admin/creditos/aguardando', [
                'title' => 'Aguardando pagamento - EducaTudo',
                'user' => $user,
                'compra' => $compra,
                'current_page' => 'tudicoins_escola',
                'pagar_action' => URL . '/admin/tudicoins/pagar/' . $compraId,
                'verificar_action' => URL . '/admin/tudicoins/verificar/' . $compraId,
                'status_action' => URL . '/admin/tudicoins/status/' . $compraId,
                'csrf_token' => $this->generateCsrfToken(),
                'pix_checkout' => $result['pix'],
                'erro_pagamento' => null,
                'pagador' => $pagador,
            ]);
            return;
        }

        if (!empty($result['checkout_url'])) {
            header('Location: ' . $result['checkout_url']);
            exit;
        }

        $this->redirect('/admin/tudicoins/aguardando/' . $compraId);
    }

    public function verificar($compraId): void
    {
        $this->requireTudiCoinsEscolaCompra();
        $compraId = (int) $compraId;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/tudicoins/aguardando/' . $compraId);
            return;
        }
        $compra = $this->db->fetch(
            "SELECT c.* FROM compras_creditos c
             WHERE c.id = ? AND c.user_type = 'escola' AND c.user_id = ?",
            [$compraId, \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID]
        );
        if (!$compra) {
            $this->setFlashMessage('Compra não encontrada.', 'error');
            $this->redirect('/admin/tudicoins');
            return;
        }
        $escolaId = \App\Services\CreditosTenantCheckoutHelper::escolaIdFromConfig($this->config);
        $result = \App\Services\TenantCreditsCheckoutService::verifyAndFulfill(
            $this->db->getPdo(),
            $escolaId,
            $compra
        );
        if ($result['ok'] ?? false) {
            $this->setFlashMessage($result['message'] ?? 'Pagamento confirmado.', 'success');
            $this->redirect('/admin/tudicoins');
            return;
        }
        $this->setFlashMessage($result['message'] ?? 'Pagamento ainda não confirmado.', 'info');
        $this->redirect('/admin/tudicoins/aguardando/' . $compraId);
    }

    public function status($compraId): void
    {
        $this->requireTudiCoinsEscolaCompra();
        header('Content-Type: application/json; charset=utf-8');
        $compraId = (int) $compraId;
        $compra = $this->db->fetch(
            "SELECT id, status FROM compras_creditos
             WHERE id = ? AND user_type = 'escola' AND user_id = ?",
            [$compraId, \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID]
        );
        echo json_encode([
            'ok' => (bool) $compra,
            'status' => $compra['status'] ?? null,
        ]);
        exit;
    }
}
}
