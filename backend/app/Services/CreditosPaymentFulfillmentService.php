<?php
/**
 * Credita carteira no tenant após pagamento Asaas confirmado (idempotente).
 */
namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/CreditosService.php';

class CreditosPaymentFulfillmentService
{
    public const EXTERNAL_REF_PREFIX = 'educatudo|';

    private static function hasColumn(\PDO $tenantPdo, string $column): bool
    {
        $stmt = $tenantPdo->prepare("SHOW COLUMNS FROM compras_creditos LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private static function ensureComprasCreditosAsaasColumns(\PDO $tenantPdo): void
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
    }

    public static function externalReference(int $escolaId, int $compraId): string
    {
        return self::EXTERNAL_REF_PREFIX . $escolaId . '|' . $compraId;
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public static function parseExternalReference(string $ref): array
    {
        $ref = trim($ref);
        if (strpos($ref, self::EXTERNAL_REF_PREFIX) !== 0) {
            return ['ok' => false, 'message' => 'externalReference inválido'];
        }
        $rest = substr($ref, strlen(self::EXTERNAL_REF_PREFIX));
        $parts = explode('|', $rest, 2);
        if (count($parts) !== 2) {
            return ['ok' => false, 'message' => 'externalReference malformado'];
        }
        $e = (int) $parts[0];
        $c = (int) $parts[1];
        if ($e <= 0 || $c <= 0) {
            return ['ok' => false, 'message' => 'ids inválidos'];
        }
        return ['ok' => true, 'escola_id' => $e, 'compra_id' => $c];
    }

    /**
     * Aplica crédito da compra (transação no tenant). Idempotente se compra já paid.
     *
     * @return array{ok:bool,message:string}
     */
    public static function fulfillPurchase(\PDO $tenantPdo, int $compraId, string $paymentId): array
    {
        $tenantPdo->beginTransaction();
        try {
            self::ensureComprasCreditosAsaasColumns($tenantPdo);
            $st = $tenantPdo->prepare(
                'SELECT c.id, c.status, c.user_type, c.user_id, c.pacote_id, p.creditos 
                 FROM compras_creditos c 
                 INNER JOIN pacotes_creditos p ON p.id = c.pacote_id 
                 WHERE c.id = ? FOR UPDATE'
            );
            $st->execute([$compraId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                $tenantPdo->rollBack();
                return ['ok' => false, 'message' => 'Compra não encontrada'];
            }
            if (($row['status'] ?? '') === 'paid') {
                $tenantPdo->commit();
                return ['ok' => true, 'message' => 'Já pago (idempotente)'];
            }
            if (($row['status'] ?? '') !== 'pending') {
                $tenantPdo->rollBack();
                return ['ok' => false, 'message' => 'Compra não está pendente'];
            }

            $userType = (string) $row['user_type'];
            $userId = (int) $row['user_id'];
            $creditos = (float) $row['creditos'];

            $prev = \Database::getInstance();
            $dbWrap = \Database::createFromPdo($tenantPdo);
            \Database::setCurrentInstance($dbWrap);
            try {
                $svc = new CreditosService();
                $svc->adicionarCreditos($userType, $userId, $creditos, 'compra', null, (string) $compraId);
            } finally {
                \Database::setCurrentInstance($prev);
            }

            $up = $tenantPdo->prepare(
                'UPDATE compras_creditos SET status = ?, asaas_payment_id = COALESCE(asaas_payment_id, ?), paid_at = COALESCE(paid_at, NOW()), updated_at = NOW() WHERE id = ?'
            );
            $up->execute(['paid', $paymentId, $compraId]);

            $tenantPdo->commit();
            return ['ok' => true, 'message' => 'Créditos creditados'];
        } catch (\Throwable $e) {
            $tenantPdo->rollBack();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Estorno: debita saldo se possível (valor em créditos do pacote).
     *
     * @return array{ok:bool,message:string}
     */
    public static function refundPurchase(\PDO $tenantPdo, int $compraId, float $creditosValor): array
    {
        $tenantPdo->beginTransaction();
        try {
            $st = $tenantPdo->prepare(
                'SELECT c.id, c.status, c.user_type, c.user_id FROM compras_creditos c WHERE c.id = ? FOR UPDATE'
            );
            $st->execute([$compraId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$row || ($row['status'] ?? '') !== 'paid') {
                $tenantPdo->rollBack();
                return ['ok' => false, 'message' => 'Compra não paga para estorno'];
            }
            $userType = (string) $row['user_type'];
            $userId = (int) $row['user_id'];

            $prev = \Database::getInstance();
            $dbWrap = \Database::createFromPdo($tenantPdo);
            \Database::setCurrentInstance($dbWrap);
            try {
                $svc = new CreditosService();
                $wallet = $svc->getWalletSaldos($userType, $userId);
            } finally {
                \Database::setCurrentInstance($prev);
            }

            $saldoComprado = (float) ($wallet['saldo_comprado'] ?? 0.0);
            if (round($saldoComprado, 4) + 1e-9 < round($creditosValor, 4)) {
                $tenantPdo->rollBack();
                return ['ok' => false, 'message' => 'Saldo comprado insuficiente para estorno automático'];
            }

            $saldoEscola = (float) ($wallet['saldo_escola'] ?? 0.0);
            $novoSaldoComprado = round($saldoComprado - $creditosValor, 4);
            $novoSaldoTotal = round($saldoEscola + $novoSaldoComprado, 4);
            $tenantPdo->prepare(
                'UPDATE carteira_usuarios
                 SET saldo = ?, saldo_escola = ?, saldo_comprado = ?, updated_at = NOW()
                 WHERE user_type = ? AND user_id = ?'
            )->execute([$novoSaldoTotal, $saldoEscola, $novoSaldoComprado, $userType, $userId]);
            $tenantPdo->prepare(
                'INSERT INTO carteira_movimentacoes (user_type, user_id, tipo, saldo_origem, valor, modulo_key, referencia_id)
                 VALUES (?, ?, \'estorno\', \'comprado\', ?, NULL, ?)'
            )->execute([$userType, $userId, -abs($creditosValor), (string) $compraId]);

            $tenantPdo->prepare('UPDATE compras_creditos SET status = ?, updated_at = NOW() WHERE id = ?')->execute(['refunded', $compraId]);
            $tenantPdo->commit();
            return ['ok' => true, 'message' => 'Estorno aplicado'];
        } catch (\Throwable $e) {
            $tenantPdo->rollBack();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
