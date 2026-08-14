<?php
/**
 * Shim legado → módulo Matrícula.
 * @deprecated Use App\Modulos\Matricula\Services\MatriculaProcessoService
 */
require_once dirname(__DIR__) . '/Modulos/matricula/Services/MatriculaProcessoService.php';

if (!class_exists(\App\Services\EnrollmentService::class, false)) {
    class_alias(
        \App\Modulos\Matricula\Services\MatriculaProcessoService::class,
        \App\Services\EnrollmentService::class
    );
}
