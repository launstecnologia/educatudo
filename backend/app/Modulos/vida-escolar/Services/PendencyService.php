<?php

namespace App\Modulos\VidaEscolar\Services;

require_once __DIR__ . '/../Modulos/conformidade/Services/PendencyService.php';
if (!class_exists('PendencyService', false)) {
    class_alias(\App\Modulos\Conformidade\Services\PendencyService::class, 'PendencyService');
}
