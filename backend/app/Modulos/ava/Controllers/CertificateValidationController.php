<?php

namespace App\Modulos\Ava\Controllers;


use App\Core\BaseController;
use App\Models\Ava\Certificate;
use App\Core\LayoutHelper;

/**
 * EducaTudo - AVA: validação pública de certificado por código (sem login).
 */
if (!class_exists(__NAMESPACE__ . '\\CertificateValidationController', false)) {
class CertificateValidationController extends BaseController
{
    public function validarCodigo(string $codigo): void
    {
        $codigo = trim((string) $codigo);
        $cert = (new Certificate())->findByCode($codigo);

        $valido = false;
        $disciplinaNome = '';
        if ($cert) {
            $valido = true;
            $disciplinaNome = (string) ($cert['titulo'] ?? '');
        }

        $escola = (string) LayoutHelper::getSystemTitle();
        $escola = $escola !== '' ? $escola : 'EducaTudo';

        $viewData = [
            'codigo' => $codigo,
            'valido' => $valido,
            'cert' => $cert,
            'disciplina_nome' => $disciplinaNome,
            'escola' => $escola,
        ];

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        extract($viewData, EXTR_SKIP);
        require dirname(__DIR__) . '/Views/public/certificado_validar.php';
    }
}
}

if (!class_exists('CertificateValidationController', false) && class_exists(__NAMESPACE__ . '\\CertificateValidationController', false)) {
    class_alias(__NAMESPACE__ . '\\CertificateValidationController', 'CertificateValidationController');
}
