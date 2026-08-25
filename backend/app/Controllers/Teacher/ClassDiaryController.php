<?php
/**
 * Shim de compatibilidade — o controller vive em app/Modulos/diario/Controllers/DiarioProfessorController.php.
 */
require_once dirname(__DIR__, 2) . '/Modulos/diario/Controllers/DiarioProfessorController.php';
if (!class_exists('ClassDiaryController', false)) {
    class_alias('DiarioProfessorController', 'ClassDiaryController');
}
