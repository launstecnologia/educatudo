<?php
/**
 * Hub de Frequência: faltas da sala ou presença da portaria.
 */

require_once __DIR__ . '/SchoolAbsenceController.php';
require_once __DIR__ . '/../../Modulos/presenca/Controllers/PresencaAdminController.php';

if (!class_exists('FrequenciaAdminController')) {
class FrequenciaAdminController extends BaseController
{
    public function index(): void
    {
        if (!class_exists('AdminPermissionMatrix')) {
            require_once __DIR__ . '/../../Core/AdminPermissionMatrix.php';
        }
        $user = (new AuthManager())->getUser();
        $perms = AdminPermissionMatrix::effectivePermissionsForUser(Database::getInstance(), $user ?? []);
        $podeFaltas = !empty($perms['faltas']['visualizar']);
        $podePresenca = !empty($perms['presenca']['visualizar']);
        $modo = strtolower(trim((string) ($_GET['modo'] ?? 'faltas')));
        if ($modo === 'presenca') {
            if (!$podePresenca) {
                $this->redirect($podeFaltas ? '/admin/frequencia?modo=faltas' : '/admin/dashboard');
                return;
            }
            (new PresencaAdminController())->index();
            return;
        }
        if (!$podeFaltas) {
            $this->redirect($podePresenca ? '/admin/frequencia?modo=presenca' : '/admin/dashboard');
            return;
        }
        (new SchoolAbsenceController())->index();
    }
}
}
