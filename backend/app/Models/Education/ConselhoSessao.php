<?php
/**
 * Shim — implementação em App\Modulos\ConselhoClasse\Models\ConselhoSessao
 */
require_once dirname(__DIR__, 2) . '/Modulos/conselho-classe/Models/ConselhoSessao.php';

if (!class_exists('ConselhoSessao', false)) {
    class_alias(\App\Modulos\ConselhoClasse\Models\ConselhoSessao::class, 'ConselhoSessao');
}
