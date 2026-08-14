<?php
/**
 * Shim legado → módulo Matrícula.
 * @deprecated Use App\Modulos\Matricula\Models\MatriculaProcesso
 */
require_once dirname(__DIR__, 2) . '/Modulos/matricula/Models/MatriculaProcesso.php';

if (!class_exists(\App\Models\Enrollment\Enrollment::class, false)) {
    class_alias(
        \App\Modulos\Matricula\Models\MatriculaProcesso::class,
        \App\Models\Enrollment\Enrollment::class
    );
}
