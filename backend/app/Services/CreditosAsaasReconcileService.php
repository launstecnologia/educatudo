<?php
/**
 * Consulta Asaas para compras pendentes com payment_id e aplica o mesmo fulfill idempotente do webhook.
 */
namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/MasterSecretVault.php';
require_once __DIR__ . '/../Core/MasterTenantConnection.php';
require_once __DIR__ . '/Asaas/AsaasApiClient.php';

use App\Services\Asaas\AsaasApiClient;
require_once __DIR__ . '/CreditosPaymentFulfillmentService.php';

class CreditosAsaasReconcileService
{
    private static function ensureComprasCreditosAsaasColumns(\PDO $pdo): void
    {
        $paymentCol = $pdo->query("SHOW COLUMNS FROM compras_creditos LIKE 'asaas_payment_id'")->fetch(\PDO::FETCH_ASSOC);
        if (!$paymentCol) {
            $pdo->exec("ALTER TABLE compras_creditos ADD COLUMN asaas_payment_id VARCHAR(64) NULL DEFAULT NULL AFTER gateway_id");
        }

        $checkoutCol = $pdo->query("SHOW COLUMNS FROM compras_creditos LIKE 'checkout_url'")->fetch(\PDO::FETCH_ASSOC);
        if (!$checkoutCol) {
            $pdo->exec("ALTER TABLE compras_creditos ADD COLUMN checkout_url VARCHAR(1024) NULL DEFAULT NULL AFTER asaas_payment_id");
        }
    }

    /**
     * @return array{checked:int, fulfilled:int, skipped:int, errors:list<string>}
     */
    public static function run(?int $onlyEscolaId = null): array
    {
        $out = ['checked' => 0, 'fulfilled' => 0, 'skipped' => 0, 'errors' => []];
        $masterDb = \Database::getInstance();
        $cfgRow = $masterDb->fetch('SELECT api_key_encrypted, environment FROM asaas_master_config WHERE id = 1');
        $apiKey = $cfgRow ? \MasterSecretVault::decrypt($cfgRow['api_key_encrypted'] ?? null) : null;
        if ($apiKey === null || $apiKey === '') {
            $out['errors'][] = 'API Asaas não configurada no Master.';
            return $out;
        }
        $sandbox = ($cfgRow['environment'] ?? 'sandbox') !== 'production';
        $client = new AsaasApiClient($apiKey, $sandbox);

        if ($onlyEscolaId !== null && $onlyEscolaId > 0) {
            $escolas = [['id' => $onlyEscolaId]];
        } else {
            try {
                $escolas = $masterDb->fetchAll(
                    'SELECT DISTINCT e.id FROM escolas e
                     INNER JOIN config_escolas_banco b ON b.escola_id = e.id'
                );
            } catch (\Throwable $e) {
                $out['errors'][] = 'Lista de escolas: ' . $e->getMessage();
                return $out;
            }
        }

        foreach ($escolas as $row) {
            $eid = (int) ($row['id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            $conn = \MasterTenantConnection::getPdoAndEscola($eid);
            if ($conn === null) {
                $out['errors'][] = "Escola {$eid}: sem conexão tenant.";
                continue;
            }
            $pdo = $conn['pdo'];
            try {
                self::ensureComprasCreditosAsaasColumns($pdo);
                $pendentes = $pdo->query(
                    "SELECT id, asaas_payment_id FROM compras_creditos
                     WHERE status = 'pending'
                       AND asaas_payment_id IS NOT NULL
                       AND TRIM(asaas_payment_id) <> ''"
                )->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $ex) {
                $out['errors'][] = "Escola {$eid}: " . $ex->getMessage();
                continue;
            }

            foreach ($pendentes as $p) {
                $out['checked']++;
                $pid = trim((string) ($p['asaas_payment_id'] ?? ''));
                $compraId = (int) ($p['id'] ?? 0);
                if ($pid === '' || $compraId <= 0) {
                    $out['skipped']++;
                    continue;
                }
                $pay = $client->getPayment($pid);
                if ($pay === null || !empty($pay['_http_error'])) {
                    $out['errors'][] = "Compra {$compraId} ({$pid}): falha HTTP Asaas.";
                    continue;
                }
                $status = strtoupper((string) ($pay['status'] ?? ''));
                if (!in_array($status, ['RECEIVED', 'CONFIRMED'], true)) {
                    $out['skipped']++;
                    continue;
                }
                $exists = $masterDb->fetch(
                    'SELECT id FROM asaas_payment_processed WHERE payment_id = ? AND event_kind = ?',
                    [$pid, 'credit_applied']
                );
                if ($exists) {
                    $out['skipped']++;
                    continue;
                }
                $res = CreditosPaymentFulfillmentService::fulfillPurchase($pdo, $compraId, $pid);
                if ($res['ok']) {
                    try {
                        $ref = CreditosPaymentFulfillmentService::externalReference($eid, $compraId);
                        $masterDb->insert(
                            'INSERT INTO asaas_payment_processed (payment_id, event_kind, external_reference) VALUES (?, ?, ?)',
                            [$pid, 'credit_applied', $ref]
                        );
                    } catch (\Throwable $t) {
                        // concorrente / duplicado
                    }
                    $out['fulfilled']++;
                } else {
                    $out['errors'][] = "Compra {$compraId}: " . ($res['message'] ?? 'fulfill falhou');
                }
            }
        }

        return $out;
    }
}
