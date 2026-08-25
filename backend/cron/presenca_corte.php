<?php

/**
 * Gestão de Presença — corte "sem entrada" e consolidação do boletim.
 * Recomendado a cada 15 minutos no horário escolar:
 *   a cada 15 min, 6h-20h, segunda a sábado: php /path/to/backend/cron/presenca_corte.php
 */

require_once __DIR__ . '/../app/Core/cron_multi_tenant_helper.php';

CronMultiTenantHelper::run(function (?int $escolaId) {
    $db = \Database::getInstance();
    $existe = $db->fetch(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
        ['presenca_eventos']
    );
    if (!$existe) {
        return;
    }

    require_once __DIR__ . '/../app/Core/LayoutHelper.php';
    if (!LayoutHelper::isModuleEnabled('presenca')) {
        return;
    }

    require_once __DIR__ . '/../app/Modulos/presenca/Services/PresencaAplicacaoService.php';
    require_once __DIR__ . '/../app/Modulos/presenca/Services/PresencaConsolidacaoService.php';

    $hoje = date('Y-m-d');
    try {
        (new PresencaAplicacaoService($db))->processarCorteDoDia($hoje);
    } catch (Throwable $e) {
        error_log('presenca_corte aplicar: ' . $e->getMessage());
    }
    try {
        (new PresencaConsolidacaoService($db))->consolidarPendentes($hoje);
    } catch (Throwable $e) {
        error_log('presenca_corte consolidar: ' . $e->getMessage());
    }
});
