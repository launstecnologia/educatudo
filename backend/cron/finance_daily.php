<?php

/**
 * Cron diário do módulo financeiro escolar.
 * Rodar 1x por dia, às 00:05:
 *   5 0 * * * php /path/to/src/cron/finance_daily.php >> /path/to/storage/logs/finance_daily.log 2>&1
 */

require_once __DIR__ . '/../app/Core/cron_multi_tenant_helper.php';

CronMultiTenantHelper::run(function (?int $escolaId) {
    $db = \Database::getInstance();

    // Verifica se a migration foi aplicada nesta escola
    $tableCheck = $db->fetch(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
        ['finance_contracts']
    );
    if (!$tableCheck) return;

    // 1) Marca parcelas pendentes como vencidas
    $db->update(
        "UPDATE finance_installments
         SET status = 'vencido'
         WHERE status = 'pendente' AND data_vencimento < CURDATE()",
        []
    );

    // 2) Lança débitos automáticos no extrato (ledger) para vencimentos de hoje
    $ledgerCheck = $db->fetch(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
        ['finance_ledger']
    );
    if ($ledgerCheck) {
        $cfg = $db->fetch("SELECT gerar_debito_auto FROM finance_config WHERE id = 1");
        if (!empty($cfg['gerar_debito_auto'])) {
            $service = new \App\Services\FinanceLedgerService($db);
            $service->processarDebitosHoje();
        }
    }

    // 3) Régua de cobrança
    try {
        $billing = new \App\Services\FinanceBillingService($db);
        $billing->dispararRegua();
    } catch (\Throwable $e) {
        // silent — continua
    }
});
