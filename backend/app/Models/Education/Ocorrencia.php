<?php
/**
 * Shim — Model de Ocorrência vive em app/Modulos/ocorrencias/.
 */
require_once dirname(__DIR__, 2) . '/Modulos/ocorrencias/Models/Ocorrencia.php';

if (!class_exists('Ocorrencia', false)) {
    class_alias(\App\Modulos\Ocorrencias\Models\Ocorrencia::class, 'Ocorrencia');
}
