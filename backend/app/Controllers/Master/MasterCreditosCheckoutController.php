<?php
/**
 * Checkout público (token assinado): cria cobrança Asaas e redireciona ao link de pagamento.
 */

if (!class_exists('MasterCreditosCheckoutController')) {

class MasterCreditosCheckoutController extends BaseController
{
    private function renderCheckoutException(\Throwable $e): void
    {
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Erro no checkout</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f8fafc;padding:32px;color:#111827}';
        echo '.box{max-width:980px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.06)}';
        echo 'h1{margin:0 0 12px;color:#b91c1c}p{line-height:1.5}.muted{color:#6b7280}.code{font-family:monospace;background:#f3f4f6;padding:2px 6px;border-radius:6px}';
        echo 'table{width:100%;border-collapse:collapse;margin-top:16px}td{padding:10px;border-top:1px solid #e5e7eb;vertical-align:top}td:first-child{width:220px;font-weight:700;color:#374151}';
        echo '</style></head><body><div class="box">';
        echo '<h1>Erro ao iniciar checkout</h1>';
        echo '<p class="muted">A aplicação capturou a exceção abaixo para facilitar o diagnóstico.</p>';
        echo '<table>';
        echo '<tr><td>Mensagem</td><td><span class="code">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</span></td></tr>';
        echo '<tr><td>Arquivo</td><td><span class="code">' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . '</span></td></tr>';
        echo '<tr><td>Linha</td><td><span class="code">' . (int) $e->getLine() . '</span></td></tr>';
        echo '<tr><td>Tipo</td><td><span class="code">' . htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8') . '</span></td></tr>';
        echo '</table>';
        echo '</div></body></html>';
        exit;
    }

    private function getRequestParam(string $key): string
    {
        $value = $_GET[$key] ?? $_POST[$key] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($requestUri !== '' && strpos($requestUri, '?') !== false) {
            $query = (string) parse_url($requestUri, PHP_URL_QUERY);
            if ($query !== '') {
                parse_str($query, $params);
                $fallback = $params[$key] ?? null;
                if (is_string($fallback) && trim($fallback) !== '') {
                    return trim($fallback);
                }
            }
        }

        return '';
    }

    private function renderInvalidLink(string $message, array $context = []): void
    {
        http_response_code(400);
        $requestUri = htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8');
        $queryString = htmlspecialchars((string) ($_SERVER['QUERY_STRING'] ?? ''), ENT_QUOTES, 'UTF-8');
        $host = htmlspecialchars((string) ($_SERVER['HTTP_HOST'] ?? ''), ENT_QUOTES, 'UTF-8');
        $hasToken = $this->getRequestParam('t') !== '' ? 'sim' : 'nao';

        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Checkout inválido</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f8fafc;padding:32px;color:#111827}';
        echo '.box{max-width:900px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.06)}';
        echo 'h1{margin:0 0 12px;color:#b91c1c}p{line-height:1.5}.muted{color:#6b7280}.code{font-family:monospace;background:#f3f4f6;padding:2px 6px;border-radius:6px}';
        echo 'table{width:100%;border-collapse:collapse;margin-top:16px}td{padding:10px;border-top:1px solid #e5e7eb;vertical-align:top}td:first-child{width:220px;font-weight:700;color:#374151}';
        echo '</style></head><body><div class="box">';
        echo '<h1>Link inválido para checkout</h1>';
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p class="muted">Esse erro normalmente acontece quando a URL de checkout foi aberta sem o parâmetro <span class="code">t</span>, que carrega o token assinado da compra.</p>';
        echo '<table>';
        echo '<tr><td>Host atual</td><td>' . $host . '</td></tr>';
        echo '<tr><td>Request URI</td><td><span class="code">' . $requestUri . '</span></td></tr>';
        echo '<tr><td>Query string</td><td><span class="code">' . ($queryString !== '' ? $queryString : '(vazia)') . '</span></td></tr>';
        echo '<tr><td>Parâmetro t presente?</td><td>' . $hasToken . '</td></tr>';
        foreach ($context as $label => $value) {
            echo '<tr><td>' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</td><td><span class="code">' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</span></td></tr>';
        }
        echo '</table>';
        echo '<p class="muted" style="margin-top:16px">Abra o checkout a partir do botão <strong>Pagar agora</strong> da compra pendente. Se essa tela apareceu ao clicar no botão, então o link está sendo gerado sem o token e o problema está na URL de redirecionamento do tenant.</p>';
        echo '</div></body></html>';
        exit;
    }

    private function renderPixCheckout(array $compra, array $pixData): void
    {
        $payload = (string) ($pixData['payload'] ?? '');
        $encodedImage = (string) ($pixData['encodedImage'] ?? '');
        $expiration = (string) ($pixData['expirationDate'] ?? '');
        $pacoteNome = htmlspecialchars((string) ($compra['pacote_nome'] ?? 'Pacote'), ENT_QUOTES, 'UTF-8');
        $valorCentavos = (int) ($compra['valor_centavos'] ?? 0);
        $valorFormatado = number_format($valorCentavos / 100, 2, ',', '.');
        $creditos = htmlspecialchars((string) ($compra['creditos'] ?? ''), ENT_QUOTES, 'UTF-8');

        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Pagamento PIX</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f8fafc;padding:32px;color:#111827}.box{max-width:760px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.06)}';
        echo '.pill{display:inline-block;background:#eef2ff;color:#4338ca;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700}.qr{display:flex;justify-content:center;margin:24px 0}.copy{width:100%;min-height:120px;border:1px solid #d1d5db;border-radius:12px;padding:12px;font-family:monospace;font-size:13px;background:#f9fafb}.btn{display:inline-block;background:#4f46e5;color:#fff;border:none;border-radius:12px;padding:12px 18px;font-weight:700;cursor:pointer}.muted{color:#6b7280}';
        echo '</style></head><body><div class="box">';
        echo '<span class="pill">PIX transparente</span>';
        echo '<h1 style="margin:16px 0 8px">Concluir pagamento por PIX</h1>';
        echo '<p class="muted">Compra única de <strong>' . $pacoteNome . '</strong> com <strong>' . $creditos . ' créditos</strong> no valor de <strong>R$ ' . $valorFormatado . '</strong>.</p>';
        if ($expiration !== '') {
            echo '<p class="muted">Expiração do QR Code: <strong>' . htmlspecialchars($expiration, ENT_QUOTES, 'UTF-8') . '</strong></p>';
        }
        if ($encodedImage !== '') {
            echo '<div class="qr"><img alt="QR Code PIX" style="max-width:280px;width:100%;height:auto;border:1px solid #e5e7eb;border-radius:16px;padding:16px;background:#fff" src="data:image/png;base64,' . htmlspecialchars($encodedImage, ENT_QUOTES, 'UTF-8') . '"></div>';
        }
        echo '<p style="font-weight:700;margin-bottom:8px">Copia e cola PIX</p>';
        echo '<textarea id="pixPayload" readonly class="copy">' . htmlspecialchars($payload, ENT_QUOTES, 'UTF-8') . '</textarea>';
        echo '<div style="margin-top:16px"><button class="btn" type="button" onclick="navigator.clipboard.writeText(document.getElementById(\'pixPayload\').value).then(function(){alert(\'Codigo PIX copiado.\');});">Copiar código PIX</button></div>';
        echo '<p class="muted" style="margin-top:20px">Após o pagamento, o webhook do Asaas confirma a compra e libera os créditos automaticamente.</p>';
        echo '</div></body></html>';
        exit;
    }

    private function ensureComprasCreditosAsaasColumns(PDO $pdo): void
    {
        $paymentCol = $pdo->query("SHOW COLUMNS FROM compras_creditos LIKE 'asaas_payment_id'")->fetch(PDO::FETCH_ASSOC);
        if (!$paymentCol) {
            $pdo->exec("ALTER TABLE compras_creditos ADD COLUMN asaas_payment_id VARCHAR(64) NULL DEFAULT NULL AFTER gateway_id");
        }

        $checkoutCol = $pdo->query("SHOW COLUMNS FROM compras_creditos LIKE 'checkout_url'")->fetch(PDO::FETCH_ASSOC);
        if (!$checkoutCol) {
            $pdo->exec("ALTER TABLE compras_creditos ADD COLUMN checkout_url VARCHAR(1024) NULL DEFAULT NULL AFTER asaas_payment_id");
        }

        $idxCol = $pdo->query("SHOW INDEX FROM compras_creditos WHERE Key_name = 'uk_compras_asaas_payment'")->fetch(PDO::FETCH_ASSOC);
        if (!$idxCol) {
            try {
                $pdo->exec("ALTER TABLE compras_creditos ADD UNIQUE KEY uk_compras_asaas_payment (asaas_payment_id)");
            } catch (Throwable $e) {
            }
        }
    }

    private function getMasterPdo(): ?PDO
    {
        $masterPdo = $GLOBALS['_educatudo_master_pdo'] ?? null;
        if ($masterPdo instanceof PDO) {
            return $masterPdo;
        }
        return null;
    }

    public function iniciar()
    {
        require_once __DIR__ . '/../../Services/CreditosCheckoutToken.php';
        require_once __DIR__ . '/../../Services/Asaas/AsaasApiClient.php';
        require_once __DIR__ . '/../../Services/CreditosPaymentFulfillmentService.php';
        require_once __DIR__ . '/../../Core/MasterSecretVault.php';
        require_once __DIR__ . '/../../Core/MasterTenantConnection.php';
        require_once __DIR__ . '/../../Core/Database.php';

        try {
            $token = $this->getRequestParam('t');
            $billingType = strtoupper(trim($this->getRequestParam('bt') ?: 'PIX'));
            $transparent = $this->getRequestParam('transparent') === '1';
            if (!in_array($billingType, ['PIX', 'CREDIT_CARD', 'BOLETO'], true)) {
                $billingType = 'PIX';
            }

            if ($token === '') {
                $this->renderInvalidLink('Token ausente na URL do checkout.', [
                    'Exemplo esperado' => '/creditos/asaas/checkout?t=TOKEN&bt=PIX',
                ]);
            }

            $parsed = \App\Services\CreditosCheckoutToken::verify($token);
            if ($parsed === null) {
                $this->renderInvalidLink('O token recebido está inválido ou expirado.', [
                    'Tamanho do token' => (string) strlen($token),
                ]);
            }

            $escolaId = $parsed['e'];
            $compraId = $parsed['c'];
            $userType = $parsed['t'];
            $userId = $parsed['u'];

        $conn = MasterTenantConnection::getPdoAndEscola($escolaId);
        if ($conn === null) {
            http_response_code(500);
            echo '<!DOCTYPE html><html><body><p>Não foi possível conectar à escola.</p></body></html>';
            exit;
        }
        $pdo = $conn['pdo'];
        $escola = $conn['escola'];
        $this->ensureComprasCreditosAsaasColumns($pdo);

        $st = $pdo->prepare(
            'SELECT c.id, c.status, c.user_type, c.user_id, c.valor_centavos, c.asaas_payment_id, c.checkout_url,
                    p.nome AS pacote_nome, p.creditos
             FROM compras_creditos c
             INNER JOIN pacotes_creditos p ON p.id = c.pacote_id
             WHERE c.id = ?'
        );
        $st->execute([$compraId]);
        $compra = $st->fetch(PDO::FETCH_ASSOC);
        if (!$compra || $compra['user_type'] !== $userType || (int) $compra['user_id'] !== $userId) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><body><p>Compra não confere com o link.</p></body></html>';
            exit;
        }
        if (($compra['status'] ?? '') === 'paid') {
            echo '<!DOCTYPE html><html><body><p>Esta compra já foi paga.</p></body></html>';
            exit;
        }
        if (($compra['status'] ?? '') !== 'pending') {
            http_response_code(400);
            echo '<!DOCTYPE html><html><body><p>Compra não está disponível para pagamento.</p></body></html>';
            exit;
        }

        $masterPdo = $this->getMasterPdo();
        if (!$masterPdo) {
            http_response_code(503);
            echo '<!DOCTYPE html><html><body><p>Pagamento indisponível: conexão com banco master não encontrada.</p></body></html>';
            exit;
        }
        $cfgStmt = $masterPdo->prepare('SELECT api_key_encrypted, environment FROM asaas_master_config WHERE id = 1');
        $cfgStmt->execute();
        $cfgRow = $cfgStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $apiKey = $cfgRow ? MasterSecretVault::decrypt($cfgRow['api_key_encrypted'] ?? null) : null;
        if ($apiKey === null || $apiKey === '') {
            http_response_code(503);
            echo '<!DOCTYPE html><html><body><p>Pagamento indisponível: configure a API Asaas no Master.</p></body></html>';
            exit;
        }
        $sandbox = ($cfgRow['environment'] ?? 'sandbox') !== 'production';
        $client = new \App\Services\Asaas\AsaasApiClient($apiKey, $sandbox);

        if (!empty($compra['checkout_url']) && !empty($compra['asaas_payment_id'])) {
            if ($transparent && $billingType === 'PIX') {
                $pixData = $client->getPixQrCode((string) $compra['asaas_payment_id']);
                if (is_array($pixData) && empty($pixData['errors']) && !empty($pixData['payload'])) {
                    $this->renderPixCheckout($compra, $pixData);
                }
            }
            header('Location: ' . $compra['checkout_url']);
            exit;
        }

        $nome = 'Usuário';
        $email = 'sem-email@educatudo.local';
        if ($userType === 'aluno') {
            $u = $pdo->prepare('SELECT nome, email FROM alunos WHERE id = ? LIMIT 1');
        } else {
            $u = $pdo->prepare('SELECT nome, email FROM professores WHERE id = ? LIMIT 1');
        }
        $u->execute([$userId]);
        $ur = $u->fetch(PDO::FETCH_ASSOC);
        if ($ur) {
            $nome = trim((string) ($ur['nome'] ?? 'Usuário')) ?: 'Usuário';
            $em = trim((string) ($ur['email'] ?? ''));
            if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
                $email = $em;
            }
        }

        $valorCentavos = (int) ($compra['valor_centavos'] ?? 0);
        $valorReais = round(max(0.01, $valorCentavos / 100), 2);
        $due = date('Y-m-d', strtotime('+5 days'));
        $ext = \App\Services\CreditosPaymentFulfillmentService::externalReference($escolaId, $compraId);

        $payload = [
            'billingType' => $billingType,
            'value' => $valorReais,
            'dueDate' => $due,
            'description' => 'Créditos: ' . ($compra['pacote_nome'] ?? 'Pacote'),
            'externalReference' => $ext,
            'customerInfo' => [
                'name' => mb_substr($nome, 0, 100),
                'email' => $email,
                'cpfCnpj' => '24971563792',
                'phone' => '47999999999',
            ],
        ];

        $resp = $client->createPayment($payload);
        if ($resp === null || ($resp['_http_code'] ?? 0) >= 400 || !empty($resp['errors'])) {
            $msg = isset($resp['errors'][0]['description']) ? $resp['errors'][0]['description'] : 'Falha ao criar cobrança.';
            http_response_code(502);
            echo '<!DOCTYPE html><html><body><p>' . htmlspecialchars($msg) . '</p></body></html>';
            exit;
        }

        $paymentId = (string) ($resp['id'] ?? '');
        $checkoutUrl = (string) ($resp['invoiceUrl'] ?? $resp['bankSlipUrl'] ?? '');
        if ($paymentId === '' || $checkoutUrl === '') {
            http_response_code(502);
            echo '<!DOCTYPE html><html><body><p>Resposta do gateway incompleta.</p></body></html>';
            exit;
        }

        $pdo->prepare(
            'UPDATE compras_creditos SET asaas_payment_id = ?, checkout_url = ?, updated_at = NOW() WHERE id = ? AND status = ?'
        )->execute([$paymentId, $checkoutUrl, $compraId, 'pending']);

        if ($transparent && $billingType === 'PIX') {
            $pixData = $client->getPixQrCode($paymentId);
            if (is_array($pixData) && empty($pixData['errors']) && !empty($pixData['payload'])) {
                $compra['asaas_payment_id'] = $paymentId;
                $compra['checkout_url'] = $checkoutUrl;
                $this->renderPixCheckout($compra, $pixData);
            }
        }

            header('Location: ' . $checkoutUrl);
            exit;
        } catch (\Throwable $e) {
            $this->renderCheckoutException($e);
        }
    }
}

}
