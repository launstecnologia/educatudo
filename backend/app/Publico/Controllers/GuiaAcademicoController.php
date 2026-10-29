<?php

namespace App\Publico\Controllers;

/**
 * Manual público da implantação acadêmica.
 * Não lê cadastro da escola: o progresso fica só no painel, depois do login.
 */

use App\Core\BaseController;
use App\Core\LayoutHelper;
use App\Dominio\Escola\Services\ImplantacaoAcademicaService;

if (!class_exists(__NAMESPACE__ . '\\GuiaAcademicoController', false)) {
class GuiaAcademicoController extends BaseController
{
    public function index(): void
    {
        $diag = (new ImplantacaoAcademicaService())->guiaPublico();
        $grupos = is_array($diag['grupos'] ?? null) ? $diag['grupos'] : [];
        foreach ($grupos as &$grupo) {
            $passos = is_array($grupo['passos'] ?? null) ? $grupo['passos'] : [];
            foreach ($passos as &$passo) {
                $passo['ok'] = false;
                $passo['atual'] = false;
                $passo['detalhe'] = '';
            }
            unset($passo);
            $grupo['passos'] = $passos;
        }
        unset($grupo);
        $diag['grupos'] = $grupos;
        $diag['proximo'] = null;

        $escola = 'EducaTudo';
        $cssTema = '';
        try {
            $titulo = trim((string) LayoutHelper::getSystemTitle());
            if ($titulo !== '') {
                $escola = $titulo;
            }
            $cssTema = (string) LayoutHelper::generateCustomCSS();
        } catch (\Throwable $e) {
            $cssTema = '';
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $viewData = [
            'diag' => $diag,
            'implantar_publico' => true,
            'escola' => $escola,
            'css_tema' => $cssTema,
        ];
        extract($viewData, EXTR_SKIP);
        require __DIR__ . '/../../Views/public/guia_academico.php';
    }
}
}

if (!class_exists('GuiaAcademicoController', false) && class_exists(__NAMESPACE__ . '\\GuiaAcademicoController', false)) {
    class_alias(__NAMESPACE__ . '\\GuiaAcademicoController', 'GuiaAcademicoController');
}
