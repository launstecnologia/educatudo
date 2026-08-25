<?php
/**
 * Shim — classe em App\Modulos\RegrasAcademicas\Models\RegraAcademica
 */
require_once dirname(__DIR__, 2) . '/Modulos/regras-academicas/Models/RegraAcademica.php';

if (!class_exists('RegraAcademica', false)) {
    class_alias(\App\Modulos\RegrasAcademicas\Models\RegraAcademica::class, 'RegraAcademica');
}
