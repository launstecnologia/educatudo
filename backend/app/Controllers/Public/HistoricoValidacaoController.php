<?php
/**
 * Validação pública do Histórico Escolar (sem login).
 * Exibe autenticidade, nome, escola e data — sem notas.
 */

require_once __DIR__ . '/../../Services/HistoricoEscolarService.php';
require_once __DIR__ . '/../../Core/LayoutHelper.php';

use App\Services\HistoricoEscolarService;

if (!class_exists('HistoricoValidacaoController')) {
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
