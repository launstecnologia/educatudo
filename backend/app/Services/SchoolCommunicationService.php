<?php
/**
 * Shim — service vive em app/Modulos/comunicacao/.
 */
require_once dirname(__DIR__) . '/Modulos/comunicacao/Services/SchoolCommunicationService.php';
if (!class_exists('SchoolCommunicationService', false)) {
    class_alias(\App\Modulos\Comunicacao\Services\SchoolCommunicationService::class, 'SchoolCommunicationService');
}
if (!class_exists(\App\Services\SchoolCommunicationService::class, false)) {
    class_alias(\App\Modulos\Comunicacao\Services\SchoolCommunicationService::class, \App\Services\SchoolCommunicationService::class);
}
