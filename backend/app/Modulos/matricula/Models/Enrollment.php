<?php

namespace App\Modulos\Matricula\Models;

/**
 * Alias legado: Enrollment → MatriculaProcesso (sem pasta Models/ global).
 */
require_once __DIR__ . '/MatriculaProcesso.php';

if (!class_exists(\App\Models\Enrollment\Enrollment::class, false)) {
    class_alias(
        \App\Modulos\Matricula\Models\MatriculaProcesso::class,
        \App\Models\Enrollment\Enrollment::class
    );
}
