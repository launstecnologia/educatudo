<?php
/**
 * Shim — controller vive em app/Modulos/comunicacao/.
 */
require_once dirname(__DIR__, 2) . '/Modulos/comunicacao/Controllers/SchoolCommunicationController.php';
if (!class_exists('SchoolCommunicationController', false)) {
    class_alias(\App\Modulos\Comunicacao\Controllers\SchoolCommunicationController::class, 'SchoolCommunicationController');
}
