<?php
/**
 * Shim — implementação em App\Modulos\Diario\Models\ClassDiary
 */
require_once dirname(__DIR__, 2) . '/Modulos/diario/Models/ClassDiary.php';

if (!class_exists('ClassDiary', false)) {
    class_alias(\App\Modulos\Diario\Models\ClassDiary::class, 'ClassDiary');
}
