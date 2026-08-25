<?php
/**
 * Shim — implementação em App\Modulos\Diario\Services\ClassDiaryReportService
 */
require_once dirname(__DIR__) . '/Modulos/diario/Services/ClassDiaryReportService.php';

if (!class_exists('ClassDiaryReportService', false)) {
    class_alias(\App\Modulos\Diario\Services\ClassDiaryReportService::class, 'ClassDiaryReportService');
}
