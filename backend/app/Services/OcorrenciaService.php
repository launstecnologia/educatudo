<?php
/**
 * Shim — Service de Ocorrência vive em app/Modulos/ocorrencias/.
 */
require_once __DIR__ . '/../Modulos/ocorrencias/Services/OcorrenciaService.php';

if (!class_exists('OcorrenciaService', false)) {
    class_alias(\App\Modulos\Ocorrencias\Services\OcorrenciaService::class, 'OcorrenciaService');
}
