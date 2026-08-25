<?php
/**
 * Shim de compatibilidade — o controller vive em app/Modulos/diario/Controllers/DiarioAdminController.php.
 */
require_once dirname(__DIR__, 2) . '/Modulos/diario/Controllers/DiarioAdminController.php';
if (!class_exists('ClassDiaryAdminController', false)) {
    class_alias('DiarioAdminController', 'ClassDiaryAdminController');
}
