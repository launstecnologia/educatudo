<?php
/**
 * Shim — implementação em App\Modulos\Diario\Services\ClassDiaryService
 */
require_once dirname(__DIR__) . '/Modulos/diario/Services/ClassDiaryService.php';

if (!class_exists('ClassDiaryService', false)) {
    class_alias(\App\Modulos\Diario\Services\ClassDiaryService::class, 'ClassDiaryService');
}
