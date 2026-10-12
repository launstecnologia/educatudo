<?php

namespace App\Publico\Controllers;
/**
 * Validação pública do Histórico Escolar (sem login).
 * Exibe autenticidade, nome, escola e data — sem notas.
 */


use App\Services\HistoricoEscolarService;

use App\Core\BaseController;
use App\Core\LayoutHelper;

if (!class_exists(__NAMESPACE__ . '\\HistoricoValidacaoController', false)) {
class HistoricoValidacaoController extends BaseController
{
    public function validar(string $hash): void
    {
        $hash = trim((string) $hash);
        $svc = new HistoricoEscolarService();
        $info = $svc->schemaPronto()
            ? $svc->validarPublico($hash)
            : ['encontrado' => false, 'valido' => false];

        $escolaNome = (string) LayoutHelper::getSystemTitle();
        if ($escolaNome === '') {
            $escolaNome = 'EducaTudo';
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $viewData = [
            'hash' => $hash,
            'info' => $info,
            'escola_sistema' => $escolaNome,
        ];
        extract($viewData, EXTR_SKIP);
        require __DIR__ . '/../../Views/public/historico_validar.php';
    }
}
}

if (!class_exists('HistoricoValidacaoController', false) && class_exists(__NAMESPACE__ . '\\HistoricoValidacaoController', false)) {
    class_alias(__NAMESPACE__ . '\\HistoricoValidacaoController', 'HistoricoValidacaoController');
}
