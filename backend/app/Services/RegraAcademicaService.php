<?php
/**
 * Shim — classe em App\Modulos\RegrasAcademicas\Services\RegraAcademicaService
 */
require_once dirname(__DIR__) . '/Modulos/regras-academicas/Services/RegraAcademicaService.php';

if (!class_exists('RegraAcademicaService', false)) {
    class_alias(\App\Modulos\RegrasAcademicas\Services\RegraAcademicaService::class, 'RegraAcademicaService');
}
