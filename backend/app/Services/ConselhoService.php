<?php
/**
 * Shim — implementação em App\Modulos\ConselhoClasse\Services\ConselhoService
 */
require_once dirname(__DIR__) . '/Modulos/conselho-classe/Services/ConselhoService.php';

if (!class_exists('ConselhoService', false)) {
    class_alias(\App\Modulos\ConselhoClasse\Services\ConselhoService::class, 'ConselhoService');
}
