<?php

require_once __DIR__ . '/../../Models/Ava/Certificate.php';
require_once __DIR__ . '/../../Core/LayoutHelper.php';

/**
 * EducaTudo - AVA: validação pública de certificado por código (sem login).
 */
if (!class_exists('CertificateValidationController')) {
class CertificateValidationController extends BaseController
{
    public function validate(string $codigo): void
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
        require __DIR__ . '/../../Views/ava/certificado_validar.php';
    }
}
}
