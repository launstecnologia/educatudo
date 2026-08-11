<?php
/**
 * Webhook público Asaas: confirma pagamento e credita tenant (idempotente).
 */

if (!class_exists('MasterAsaasWebhookController')) {

class MasterAsaasWebhookController extends BaseController
{
    public function handle()
    {
        require_once __DIR__ . '/../../Core/Database.php';
        require_once __DIR__ . '/../../Core/MasterTenantConnection.php';
        require_once __DIR__ . '/../../Services/CreditosPaymentFulfillmentService.php';
        require_once __DIR__ . '/../../Services/CreditosPurchaseNotifier.php';
        require_once __DIR__ . '/../../Services/Asaas/AsaasApiClient.php';

        $raw = file_get_contents('php://input');
        $hash = $raw !== false ? hash('sha256', $raw) : '';

        $allowIps = function_exists('env') ? trim((string) env('ASAAS_WEBHOOK_ALLOW_IPS', '')) : '';
        if ($allowIps !== '') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $list = array_map('trim', explode(',', $allowIps));
            if ($ip !== '' && !in_array($ip, $list, true)) {
                http_response_code(403);
                echo json_encode(['ok' => false]);
                exit;
            }
        }

        $db = Database::getInstance();
        $cfg = $db->fetch('SELECT webhook_token FROM asaas_master_config WHERE id = 1');
        $expectedToken = trim((string) ($cfg['webhook_token'] ?? ''));
        $sentToken = '';
        foreach (['HTTP_ASAAS_ACCESS_TOKEN', 'HTTP_Asaas_Access_Token', 'HTTP_ASAAS-ACCESS-TOKEN'] as $h) {
            if ($sentToken === '' && !empty($_SERVER[$h])) {
                $sentToken = trim((string) $_SERVER[$h]);
            }
        }

        $validationOk = ($expectedToken !== '' && hash_equals($expectedToken, $sentToken));
        if (!$validationOk) {
            $this->logWebhookRow($db, null, 'auth_fail', false, 401, $hash, 'token');
            http_response_code(401);
            echo json_encode(['ok' => false]);
            exit;
        }

        $data = json_decode($raw ?: 'null', true);
        if (!is_array($data)) {
            $this->logWebhookRow($db, null, 'invalid_json', false, 400, $hash, '');
            http_response_code(400);
            echo json_encode(['ok' => false]);
            exit;
        }

        $event = (string) ($data['event'] ?? '');
        $payment = null;
        if (!empty($data['payment']) && is_array($data['payment'])) {
            $payment = $data['payment'];
        }

        $paymentId = $payment ? (string) ($payment['id'] ?? '') : '';

        if ($payment && in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            if ($paymentId !== '') {
                $exists = $db->fetch(
                    'SELECT id FROM asaas_payment_processed WHERE payment_id = ? AND event_kind = ?',
                    [$paymentId, 'credit_applied']
                );
                if ($exists) {
                    $this->logWebhookRow($db, $paymentId, $event, true, 200, $hash, 'duplicate_credit');
                    http_response_code(200);
                    echo json_encode(['ok' => true, 'duplicate' => true]);
                    exit;
                }
            }
        }

        if ($payment && in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            $status = strtoupper((string) ($payment['status'] ?? ''));
            if (in_array($status, ['RECEIVED', 'CONFIRMED'], true)) {
                $ref = (string) ($payment['externalReference'] ?? '');
                $parsed = \App\Services\CreditosPaymentFulfillmentService::parseExternalReference($ref);
                if (!$parsed['ok']) {
                    $this->logWebhookRow($db, $paymentId, $event, false, 200, $hash, $parsed['message'] ?? '');
                    http_response_code(200);
                    echo json_encode(['ok' => false, 'message' => 'bad_ref']);
                    exit;
                }
                $conn = MasterTenantConnection::getPdoAndEscola((int) $parsed['escola_id']);
                if ($conn === null) {
                    $this->logWebhookRow($db, $paymentId, $event, false, 500, $hash, 'no_tenant');
                    http_response_code(500);
                    echo json_encode(['ok' => false]);
                    exit;
                }
                $pdo = $conn['pdo'];
                $escolaNome = (string) ($conn['escola']['nome'] ?? '');
                $compraId = (int) $parsed['compra_id'];

                $result = \App\Services\CreditosPaymentFulfillmentService::fulfillPurchase($pdo, $compraId, $paymentId);
                if ($result['ok']) {
                    try {
                        $db->insert(
                            'INSERT INTO asaas_payment_processed (payment_id, event_kind, external_reference) VALUES (?, ?, ?)',
                            [$paymentId, 'credit_applied', $ref]
                        );
                    } catch (Throwable $e) {
                        // idempotência concorrente
                    }
                    try {
                        $st = $pdo->prepare(
                            'SELECT c.user_type, c.user_id, p.creditos, c.email_notified_at FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ?'
                        );
                        $st->execute([$compraId]);
                        $row = $st->fetch(PDO::FETCH_ASSOC);
                        if ($row && empty($row['email_notified_at'])) {
                            $tipo = $row['user_type'];
                            $uid = (int) $row['user_id'];
                            $cred = (float) $row['creditos'];
                            $email = '';
                            $nome = '';
                            if ($tipo === 'aluno') {
                                $u = $pdo->prepare('SELECT nome, email FROM alunos WHERE id = ?');
                                $u->execute([$uid]);
                                $ur = $u->fetch(PDO::FETCH_ASSOC);
                            } elseif ($tipo === 'professor') {
                                $u = $pdo->prepare('SELECT nome, email FROM professores WHERE id = ?');
                                $u->execute([$uid]);
                                $ur = $u->fetch(PDO::FETCH_ASSOC);
                            } else {
                                // Compra institucional: notifica o admin logado via e-mail da escola se houver
                                $ur = null;
                                try {
                                    $u = $pdo->query("SELECT nome, email FROM usuarios WHERE tipo = 'admin_escola' AND ativo = 1 ORDER BY id ASC LIMIT 1");
                                    $ur = $u ? $u->fetch(PDO::FETCH_ASSOC) : null;
                                } catch (Throwable $eAdmin) {
                                    $ur = null;
                                }
                            }
                            if ($ur) {
                                $nome = (string) ($ur['nome'] ?? '');
                                $email = (string) ($ur['email'] ?? '');
                            }
                            if ($email !== '') {
                                \App\Services\CreditosPurchaseNotifier::notifyPaid($email, $nome, $cred, $escolaNome);
                                $pdo->prepare('UPDATE compras_creditos SET email_notified_at = NOW() WHERE id = ?')->execute([$compraId]);
                            }
                        }
                    } catch (Throwable $e) {
                        error_log('[asaas_webhook_notify] ' . $e->getMessage());
                    }
                }

                $this->logWebhookRow($db, $paymentId, $event, true, 200, $hash, $result['message'] ?? '');
                http_response_code(200);
                echo json_encode(['ok' => $result['ok']]);
                exit;
            }
        }

        if ($payment && in_array($event, ['PAYMENT_REFUNDED', 'PAYMENT_CHARGEBACK_DISPUTE', 'PAYMENT_CHARGEBACK_LOST'], true)) {
            if ($paymentId !== '') {
                $existsRef = $db->fetch(
                    'SELECT id FROM asaas_payment_processed WHERE payment_id = ? AND event_kind = ?',
                    [$paymentId, 'refund_debit']
                );
                if ($existsRef) {
                    $this->logWebhookRow($db, $paymentId, $event, true, 200, $hash, 'duplicate_refund');
                    http_response_code(200);
                    echo json_encode(['ok' => true, 'duplicate' => true]);
                    exit;
                }
            }
            $ref = (string) ($payment['externalReference'] ?? '');
            $parsed = \App\Services\CreditosPaymentFulfillmentService::parseExternalReference($ref);
            if ($parsed['ok']) {
                $conn = MasterTenantConnection::getPdoAndEscola((int) $parsed['escola_id']);
                if ($conn === null) {
                    $this->logWebhookRow($db, $paymentId, $event, false, 500, $hash, 'refund_no_tenant');
                    http_response_code(500);
                    echo json_encode(['ok' => false]);
                    exit;
                }
                $pdo = $conn['pdo'];
                $compraId = (int) $parsed['compra_id'];
                $st = $pdo->prepare('SELECT p.creditos FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ?');
                $st->execute([$compraId]);
                $pr = $st->fetch(PDO::FETCH_ASSOC);
                $credVal = $pr ? (float) $pr['creditos'] : 0.0;
                $refund = \App\Services\CreditosPaymentFulfillmentService::refundPurchase($pdo, $compraId, $credVal);
                if ($refund['ok'] && $paymentId !== '') {
                    try {
                        $db->insert(
                            'INSERT INTO asaas_payment_processed (payment_id, event_kind, external_reference) VALUES (?, ?, ?)',
                            [$paymentId, 'refund_debit', $ref]
                        );
                    } catch (Throwable $e) {
                        // concorrente
                    }
                }
                $this->logWebhookRow($db, $paymentId, $event, $refund['ok'], 200, $hash, $refund['message'] ?? '');
                http_response_code(200);
                echo json_encode(['ok' => $refund['ok'], 'message' => $refund['message'] ?? '']);
                exit;
            }
        }

        $this->logWebhookRow($db, $paymentId, $event, true, 200, $hash, 'ignored');
        http_response_code(200);
        echo json_encode(['ok' => true, 'ignored' => true]);
        exit;
    }

    private function logWebhookRow($db, ?string $paymentId, string $event, bool $ok, int $httpStatus, string $hash, string $note): void
    {
        try {
            $db->insert(
                'INSERT INTO asaas_webhook_log (payment_id, event_type, validation_ok, http_status, payload_hash, note) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $paymentId,
                    mb_substr($event, 0, 80),
                    $ok ? 1 : 0,
                    $httpStatus,
                    mb_substr($hash, 0, 64),
                    mb_substr($note, 0, 500),
                ]
            );
        } catch (Throwable $e) {
            error_log('[asaas_webhook_log] ' . $e->getMessage());
        }
    }
}

}
