<?php
/**
 * Shim legado → módulo Matrícula.
 * @deprecated Use Modulos/matricula/MatriculaAdminController
 */
require_once dirname(__DIR__, 2) . '/Modulos/matricula/Controllers/MatriculaAdminController.php';

if (!class_exists('EnrollmentAdminController', false) && class_exists('MatriculaAdminController', false)) {
    class_alias('MatriculaAdminController', 'EnrollmentAdminController');
}
