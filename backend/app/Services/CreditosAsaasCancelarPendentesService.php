<?php
/**
 * Cancela cobranças Asaas pendentes há mais de N minutos e marca compras_creditos como cancelled.
 * O aluno precisa gerar um novo QR/fatura para comprar de novo.
 */
namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/MasterSecretVault.php';
require_once __DIR__ . '/../Core/MasterTenantConnection.php';
require_once __DIR__ . '/Asaas/AsaasApiClient.php';

use App\Services\Asaas\AsaasApiClient;

class CreditosAsaasCancelarPendentesService
{
    /** Minutos sem pagamento antes de cancelar (padrão: 60 = 1 hora). */
    public const MINUTOS_PADRAO = 60;

    /** Status Asaas em que a cobrança já foi quitada — não cancelar. */
    private const STATUS_PAGO = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'];

    /** Status Asaas em que é seguro pedir DELETE. */
    private const STATUS_CANCELAVEL = ['PENDING', 'OVERDUE', 'AWAITING_RISK_ANALYSIS'];

    /** Status Asaas já encerrado — só alinhar o banco. */
    private const STATUS_JA_ENCERRADO = ['DELETED', 'REFUNDED', 'REFUND_REQUESTED'];

    /**
     * @return array{checked:int, cancelled:int, skipped:int, errors:list<string>}
     */
    public static function run(int $minutos = self::MINUTOS_PADRAO, ?int $onlyEscolaId = null): array
    {
        $out = ['checked' => 0, 'cancelled' => 0, 'skipped' => 0, 'errors' => []];
        $minutos = max(1, $minutos);

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
                     INNER JOIN config_escolas_banco b ON b.escola_id = e.id
                     WHERE e.ativo = 1'
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
            /** @var \PDO $pdo */
            $pdo = $conn['pdo'];
            try {
                // minutos já é int >= 1 (seguro interpolar no INTERVAL)
                $st = $pdo->query(
                    "SELECT id, asaas_payment_id, created_at
                     FROM compras_creditos
                     WHERE status = 'pending'
                       AND created_at <= (NOW() - INTERVAL {$minutos} MINUTE)"
                );
                $pendentes = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
            } catch (\Throwable $ex) {
                $out['errors'][] = "Escola {$eid}: " . $ex->getMessage();
                continue;
            }

            foreach ($pendentes as $p) {
                $out['checked']++;
                $compraId = (int) ($p['id'] ?? 0);
                $pid = trim((string) ($p['asaas_payment_id'] ?? ''));
                if ($compraId <= 0) {
                    $out['skipped']++;
                    continue;
                }

                // Sem payment_id: só marca cancelled no banco (QR nunca gerado / falha na criação).
                if ($pid === '') {
                    if (self::marcarCancelled($pdo, $compraId)) {
                        $out['cancelled']++;
                    } else {
                        $out['skipped']++;
                    }
                    continue;
                }

                // Fail-closed: só DELETE se o GET confirmar status cancelável.
                $pay = $client->getPayment($pid);
                if ($pay === null || !empty($pay['_http_error'])) {
                    $http = (int) ($pay['_http_code'] ?? 0);
                    // 404: cobrança já não existe no Asaas → alinhar banco
                    if ($http === 404) {
                        if (self::marcarCancelled($pdo, $compraId)) {
                            $out['cancelled']++;
                        } else {
                            $out['skipped']++;
                        }
                        continue;
                    }
                    $out['errors'][] = "Escola {$eid} compra {$compraId} ({$pid}): falha ao consultar Asaas (HTTP {$http}).";
                    $out['skipped']++;
                    continue;
                }

                $status = strtoupper((string) ($pay['status'] ?? ''));
                if (in_array($status, self::STATUS_PAGO, true)) {
                    // Já pago — deixa o reconcile/webhook creditar
                    $out['skipped']++;
                    continue;
                }
                if (in_array($status, self::STATUS_JA_ENCERRADO, true)) {
                    if (self::marcarCancelled($pdo, $compraId)) {
                        $out['cancelled']++;
                    } else {
                        $out['skipped']++;
                    }
                    continue;
                }
                if (!in_array($status, self::STATUS_CANCELAVEL, true)) {
                    $out['errors'][] = "Escola {$eid} compra {$compraId} ({$pid}): status Asaas não cancelável ({$status}).";
                    $out['skipped']++;
                    continue;
                }

                $del = $client->deletePayment($pid);
                $http = (int) ($del['_http_code'] ?? 0);
                // 200/204 = ok; 404 = já não existe — ambos ok para marcar cancelled
                if ($http === 200 || $http === 204 || $http === 404) {
                    if (self::marcarCancelled($pdo, $compraId)) {
                        $out['cancelled']++;
                    } else {
                        $out['skipped']++;
                    }
                    continue;
                }

                // Asaas às vezes devolve erro se já pago no meio do caminho
                $errMsg = (string) ($del['errors'][0]['description'] ?? $del['_curl_error'] ?? "HTTP {$http}");
                $out['errors'][] = "Escola {$eid} compra {$compraId} ({$pid}): {$errMsg}";
            }
        }

        return $out;
    }

    private static function marcarCancelled(\PDO $pdo, int $compraId): bool
    {
        try {
            $st = $pdo->prepare(
                "UPDATE compras_creditos
                 SET status = 'cancelled', updated_at = NOW()
                 WHERE id = :id AND status = 'pending'"
            );
            $st->execute(['id' => $compraId]);
            return $st->rowCount() > 0;
        } catch (\Throwable $e) {
            // updated_at pode não existir em schema antigo
            try {
                $st = $pdo->prepare(
                    "UPDATE compras_creditos SET status = 'cancelled' WHERE id = :id AND status = 'pending'"
                );
                $st->execute(['id' => $compraId]);
                return $st->rowCount() > 0;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }
}
