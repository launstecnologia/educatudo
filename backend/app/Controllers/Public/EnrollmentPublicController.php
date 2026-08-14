<?php
/**
 * Shim legado → módulo Matrícula.
 * @deprecated Use Modulos/matricula/MatriculaPublicoController
 */
require_once dirname(__DIR__, 2) . '/Modulos/matricula/Controllers/MatriculaPublicoController.php';

if (!class_exists('EnrollmentPublicController') && class_exists('MatriculaPublicoController')) {
    class_alias('MatriculaPublicoController', 'EnrollmentPublicController');
}
