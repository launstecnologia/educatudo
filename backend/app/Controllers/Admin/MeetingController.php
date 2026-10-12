<?php
/**
 * Shim — controller vive em app/Modulos/comunicacao/.
 */
require_once dirname(__DIR__, 2) . '/Modulos/comunicacao/Controllers/MeetingController.php';
if (!class_exists('MeetingController', false)) {
    class_alias(\App\Modulos\Comunicacao\Controllers\MeetingController::class, 'MeetingController');
}
