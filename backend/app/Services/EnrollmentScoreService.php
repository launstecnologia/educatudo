<?php
/**
 * Shim legado → módulo Matrícula.
 * @deprecated Use App\Modulos\Matricula\Services\MatriculaScoreService
 */
require_once dirname(__DIR__) . '/Modulos/matricula/Services/MatriculaScoreService.php';

if (!class_exists(\App\Services\EnrollmentScoreService::class, false)) {
    class_alias(
        \App\Modulos\Matricula\Services\MatriculaScoreService::class,
        \App\Services\EnrollmentScoreService::class
    );
}
