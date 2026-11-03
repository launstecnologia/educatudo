<?php

/**
 * Lê scans de gabarito que ficaram na fila.
 * A tela também lê na hora do envio. Este cron cobre o que sobrou.
 *
 * Exemplo, a cada minuto:
 *   * * * * * php /caminho/backend/cron/processar_leitor_gabarito.php
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/../app/Core/cron_multi_tenant_helper.php';

CronMultiTenantHelper::run(function (?int $escolaId) {
    $db = \App\Core\Database::getInstance();
    if (!$db->tableExists('leitor_gabarito_leituras')) {
        return 0;
    }
    try {
        // O helper de layout guarda a config da escola anterior. Sem isso, o
        // módulo ligado na primeira escola vale para as seguintes.
        \App\Core\LayoutHelper::invalidateCache();
        if (!\App\Core\LayoutHelper::isModuleEnabled('leitor_gabarito')) {
            return 0;
        }
    } catch (Throwable $e) {
        return 0;
    }

    $servico = new \App\Modulos\LeitorGabarito\Services\LeitorGabaritoService();
    return $servico->processarFila(5);
});
