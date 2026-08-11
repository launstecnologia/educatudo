<?php
namespace App\Services;

require_once __DIR__ . '/Asaas/AsaasApiClient.php';
require_once __DIR__ . '/CreditosPaymentFulfillmentService.php';

class TenantCreditsCheckoutService
{
    private static function digitsOnly(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }

    private static function buildPayerExternalReference(int $escolaId, array $compra): string
    {
        return implode(':', [
            'tenant',
            $escolaId,
            (string) ($compra['user_type'] ?? 'usuario'),
            (string) ($compra['user_id'] ?? 0),
        ]);
    }

    private static function ensureCustomer(
        \App\Services\Asaas\AsaasApiClient $client,
        int $escolaId,
        array $compra,
        array $payer
    ): array {
        $externalReference = self::buildPayerExternalReference($escolaId, $compra);

        $email = trim((string) ($payer['email'] ?? ''));
        $cpfCnpj = self::digitsOnly($payer['cpf_cnpj'] ?? null);
        $phone = self::digitsOnly($payer['phone'] ?? null);

        $existing = $client->listCustomers(['externalReference' => $externalReference]);
        if (is_array($existing) && empty($existing['errors']) && !empty($existing['data'][0]['id'])) {
            $customerId = (string) $existing['data'][0]['id'];
            // O cliente pode ter sido criado numa tentativa anterior sem CPF/telefone
            // (ex.: o aluno não preencheu na hora). Reaproveitar sem atualizar deixava
            // a cobrança permanentemente sem CPF, e o Asaas recusa criar pagamento
            // pra cliente sem CPF/CNPJ ("invalid_object") em todas as tentativas
            // seguintes, mesmo o aluno preenchendo o campo de novo no formulário.
            $temCpfAtual = !empty($existing['data'][0]['cpfCnpj']);
            if (!$temCpfAtual && $cpfCnpj !== '' && in_array(strlen($cpfCnpj), [11, 14], true)) {
                $updatePayload = ['cpfCnpj' => $cpfCnpj];
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $updatePayload['email'] = $email;
                }
                if ($phone !== '' && strlen($phone) >= 10) {
                    $updatePayload['phone'] = substr($phone, 0, 11);
                }
                $updateResp = $client->updateCustomer($customerId, $updatePayload);
                if (!is_array($updateResp) || ($updateResp['_http_code'] ?? 0) >= 400 || !empty($updateResp['errors'])) {
                    return [
                        'ok' => false,
                        'message' => self::extractAsaasErrorMessage($updateResp, 'Falha ao atualizar CPF/CNPJ do cliente no Asaas.'),
                        'raw' => $updateResp,
                    ];
                }
            }
            return ['ok' => true, 'customer_id' => $customerId];
        }

        $payload = [
            'name' => mb_substr(trim((string) ($payer['nome'] ?? 'Usuário')) ?: 'Usuário', 0, 100),
            'externalReference' => $externalReference,
            'notificationDisabled' => false,
        ];

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $payload['email'] = $email;
        }

        if ($cpfCnpj !== '' && in_array(strlen($cpfCnpj), [11, 14], true)) {
            $payload['cpfCnpj'] = $cpfCnpj;
        }

        if ($phone !== '' && strlen($phone) >= 10) {
            $payload['phone'] = substr($phone, 0, 11);
        }

        $resp = $client->createCustomer($payload);
        if (!is_array($resp) || ($resp['_http_code'] ?? 0) >= 400 || !empty($resp['errors']) || empty($resp['id'])) {
            return [
                'ok' => false,
                'message' => self::extractAsaasErrorMessage($resp, 'Falha ao criar cliente no Asaas.'),
                'raw' => $resp,
            ];
        }

        return ['ok' => true, 'customer_id' => (string) $resp['id']];
    }

    private static function extractAsaasErrorMessage($resp, string $fallback): string
    {
        if (!is_array($resp)) {
            return $fallback;
        }

        if (!empty($resp['errors']) && is_array($resp['errors'])) {
            $parts = [];
            foreach ($resp['errors'] as $error) {
                $description = trim((string) ($error['description'] ?? ''));
                $code = trim((string) ($error['code'] ?? ''));
                if ($description !== '' && $code !== '') {
                    $parts[] = $code . ': ' . $description;
                } elseif ($description !== '') {
                    $parts[] = $description;
                }
            }
            if ($parts !== []) {
                return implode(' | ', $parts);
            }
        }

        if (!empty($resp['_curl_error'])) {
            return 'Falha HTTP/CURL no Asaas: ' . $resp['_curl_error'];
        }

        if (!empty($resp['_raw']) && is_string($resp['_raw'])) {
            return 'Resposta inesperada do Asaas: ' . $resp['_raw'];
        }

        if (!empty($resp['_http_code'])) {
            return $fallback . ' HTTP ' . (int) $resp['_http_code'];
        }

        return $fallback;
    }

    private static function getMasterPdo(): ?\PDO
    {
        $masterPdo = $GLOBALS['_educatudo_master_pdo'] ?? null;
        return $masterPdo instanceof \PDO ? $masterPdo : null;
    }

    private static function hasColumn(\PDO $tenantPdo, string $column): bool
    {
        $stmt = $tenantPdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'compras_creditos'
               AND COLUMN_NAME = ?
             LIMIT 1"
        );
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function ensureComprasCreditosAsaasColumns(\PDO $tenantPdo): void
    {
        if (!self::hasColumn($tenantPdo, 'asaas_payment_id')) {
            $tenantPdo->exec("ALTER TABLE compras_creditos ADD COLUMN asaas_payment_id VARCHAR(64) NULL DEFAULT NULL");
        }

        if (!self::hasColumn($tenantPdo, 'checkout_url')) {
            $tenantPdo->exec("ALTER TABLE compras_creditos ADD COLUMN checkout_url VARCHAR(1024) NULL DEFAULT NULL");
        }

        if (!self::hasColumn($tenantPdo, 'billing_type')) {
            $tenantPdo->exec("ALTER TABLE compras_creditos ADD COLUMN billing_type VARCHAR(32) NULL DEFAULT NULL");
        }

        if (!self::hasColumn($tenantPdo, 'email_notified_at')) {
            $tenantPdo->exec("ALTER TABLE compras_creditos ADD COLUMN email_notified_at DATETIME NULL DEFAULT NULL");
        }

        if (!self::hasColumn($tenantPdo, 'paid_at')) {
            $tenantPdo->exec("ALTER TABLE compras_creditos ADD COLUMN paid_at DATETIME NULL DEFAULT NULL");
        }

        $idxCol = $tenantPdo->query("SHOW INDEX FROM compras_creditos WHERE Key_name = 'uk_compras_asaas_payment'")->fetch(\PDO::FETCH_ASSOC);
        if (!$idxCol) {
            try {
                $tenantPdo->exec("ALTER TABLE compras_creditos ADD UNIQUE KEY uk_compras_asaas_payment (asaas_payment_id)");
            } catch (\Throwable $e) {
            }
        }
    }

    public static function createOrReuse(
        \PDO $tenantPdo,
        int $escolaId,
        array $compra,
        string $billingType,
        array $payer
    ): array {
        self::ensureComprasCreditosAsaasColumns($tenantPdo);

        $masterPdo = self::getMasterPdo();
        if (!$masterPdo) {
            return ['ok' => false, 'message' => 'Conexão com banco master não encontrada.'];
        }

        $cfgStmt = $masterPdo->prepare('SELECT api_key_encrypted, environment FROM asaas_master_config WHERE id = 1');
        $cfgStmt->execute();
        $cfgRow = $cfgStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$cfgRow) {
            return ['ok' => false, 'message' => 'Configuração do Asaas não encontrada no master.'];
        }

        require_once __DIR__ . '/../Core/MasterSecretVault.php';
        $apiKey = \MasterSecretVault::decrypt($cfgRow['api_key_encrypted'] ?? null);
        if (!$apiKey) {
            return ['ok' => false, 'message' => 'API key do Asaas não configurada no master.'];
        }

        $sandbox = ($cfgRow['environment'] ?? 'sandbox') !== 'production';
        $client = new \App\Services\Asaas\AsaasApiClient($apiKey, $sandbox);

        $billingType = strtoupper(trim($billingType));
        if (!in_array($billingType, ['PIX', 'CREDIT_CARD', 'BOLETO'], true)) {
            $billingType = 'PIX';
        }

        // Só reaproveita a cobrança existente se a forma de pagamento pedida agora
        // for a MESMA que gerou aquela cobrança no Asaas. Asaas não permite trocar
        // billingType de um pagamento já criado: reaproveitar uma cobrança de cartão
        // pra pedido de PIX faz o QR falhar e cair de volta no link de cartão (o
        // checkout_url antigo), mesmo quem clicou em "Pagar com PIX".
        $billingTypeExistente = strtoupper(trim((string) ($compra['billing_type'] ?? '')));
        if (
            !empty($compra['checkout_url'])
            && !empty($compra['asaas_payment_id'])
            && ($billingTypeExistente === '' || $billingTypeExistente === $billingType)
        ) {
            $tenantPdo->prepare(
                'UPDATE compras_creditos SET billing_type = COALESCE(billing_type, ?), updated_at = NOW() WHERE id = ?'
            )->execute([$billingType, (int) ($compra['id'] ?? 0)]);
            $result = [
                'ok' => true,
                'payment_id' => (string) $compra['asaas_payment_id'],
                'checkout_url' => (string) $compra['checkout_url'],
            ];
            if ($billingType === 'PIX') {
                $pixData = $client->getPixQrCode((string) $compra['asaas_payment_id']);
                if (is_array($pixData) && empty($pixData['errors']) && !empty($pixData['payload'])) {
                    $result['pix'] = $pixData;
                }
            }
            return $result;
        }

        $valorCentavos = (int) ($compra['valor_centavos'] ?? 0);
        $valorReais = round(max(0.01, $valorCentavos / 100), 2);
        $due = date('Y-m-d', strtotime('+5 days'));
        $compraId = (int) ($compra['id'] ?? 0);
        $customer = self::ensureCustomer($client, $escolaId, $compra, $payer);
        if (!($customer['ok'] ?? false)) {
            return $customer;
        }

        $payload = [
            'customer' => (string) $customer['customer_id'],
            'billingType' => $billingType,
            'value' => $valorReais,
            'dueDate' => $due,
            'description' => 'Créditos: ' . ($compra['pacote_nome'] ?? 'Pacote'),
            'externalReference' => \App\Services\CreditosPaymentFulfillmentService::externalReference($escolaId, $compraId),
        ];

        $resp = $client->createPayment($payload);
        if ($resp === null || ($resp['_http_code'] ?? 0) >= 400 || !empty($resp['errors'])) {
            $msg = self::extractAsaasErrorMessage($resp, 'Falha ao criar cobrança no Asaas.');
            return ['ok' => false, 'message' => $msg, 'raw' => $resp];
        }

        $paymentId = (string) ($resp['id'] ?? '');
        $checkoutUrl = (string) ($resp['invoiceUrl'] ?? $resp['bankSlipUrl'] ?? '');
        if ($paymentId === '' || $checkoutUrl === '') {
            return ['ok' => false, 'message' => 'Resposta do Asaas incompleta ao criar cobrança.', 'raw' => $resp];
        }

        $tenantPdo->prepare(
            'UPDATE compras_creditos SET asaas_payment_id = ?, checkout_url = ?, billing_type = ?, updated_at = NOW() WHERE id = ? AND status = ?'
        )->execute([$paymentId, $checkoutUrl, $billingType, $compraId, 'pending']);

        $result = [
            'ok' => true,
            'payment_id' => $paymentId,
            'checkout_url' => $checkoutUrl,
        ];

        if ($billingType === 'PIX') {
            $pixData = $client->getPixQrCode($paymentId);
            if (is_array($pixData) && empty($pixData['errors']) && !empty($pixData['payload'])) {
                $result['pix'] = $pixData;
            }
        }

        return $result;
    }

    public static function verifyAndFulfill(\PDO $tenantPdo, int $escolaId, array $compra): array
    {
        self::ensureComprasCreditosAsaasColumns($tenantPdo);

        $paymentId = trim((string) ($compra['asaas_payment_id'] ?? ''));
        if ($paymentId === '') {
            return ['ok' => false, 'message' => 'Esta compra ainda não possui cobrança Asaas gerada.'];
        }

        $masterPdo = self::getMasterPdo();
        if (!$masterPdo) {
            return ['ok' => false, 'message' => 'Conexão com banco master não encontrada.'];
        }

        $cfgStmt = $masterPdo->prepare('SELECT api_key_encrypted, environment FROM asaas_master_config WHERE id = 1');
        $cfgStmt->execute();
        $cfgRow = $cfgStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$cfgRow) {
            return ['ok' => false, 'message' => 'Configuração do Asaas não encontrada no master.'];
        }

        require_once __DIR__ . '/../Core/MasterSecretVault.php';
        $apiKey = \MasterSecretVault::decrypt($cfgRow['api_key_encrypted'] ?? null);
        if (!$apiKey) {
            return ['ok' => false, 'message' => 'API key do Asaas não configurada no master.'];
        }

        $sandbox = ($cfgRow['environment'] ?? 'sandbox') !== 'production';
        $client = new \App\Services\Asaas\AsaasApiClient($apiKey, $sandbox);
        $resp = $client->getPayment($paymentId);
        if (!is_array($resp) || ($resp['_http_code'] ?? 0) >= 400 || !empty($resp['errors'])) {
            return [
                'ok' => false,
                'message' => self::extractAsaasErrorMessage($resp, 'Falha ao consultar pagamento no Asaas.'),
                'raw' => $resp,
            ];
        }

        $status = strtoupper((string) ($resp['status'] ?? ''));
        if (in_array($status, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
            $result = \App\Services\CreditosPaymentFulfillmentService::fulfillPurchase(
                $tenantPdo,
                (int) ($compra['id'] ?? 0),
                $paymentId
            );
            return [
                'ok' => (bool) ($result['ok'] ?? false),
                'message' => (string) ($result['message'] ?? 'Pagamento confirmado.'),
                'status' => $status,
            ];
        }

        return [
            'ok' => false,
            'message' => 'Pagamento ainda não confirmado no Asaas. Status atual: ' . $status,
            'status' => $status,
        ];
    }
}
