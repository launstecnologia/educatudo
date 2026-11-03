<?php

namespace App\Modulos\Carteirinha\Controllers;

use App\Core\BaseController;
use App\Modulos\Carteirinha\Services\CarteirinhaService;

if (!class_exists(__NAMESPACE__ . '\\CarteirinhaPublicoController', false)) {
class CarteirinhaPublicoController extends BaseController
{
    public function validar(string $token): void
    {
        $info = (new CarteirinhaService())->validarPublico($token);
        $escola = (string) ($info['escola'] ?? 'EducaTudo');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        require dirname(__DIR__) . '/Views/publico/validar.php';
    }
}
}
