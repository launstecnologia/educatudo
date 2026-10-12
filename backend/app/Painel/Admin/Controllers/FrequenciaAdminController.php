<?php

namespace App\Painel\Admin\Controllers;
/**
 * Hub de Frequência: faltas da sala ou presença da portaria.
 */


use App\Core\AdminPermissionMatrix;
use App\Core\AuthManager;
use App\Core\BaseController;
use App\Core\Database;
use App\Modulos\Presenca\Controllers\PresencaAdminController;
use App\Modulos\VidaEscolar\Controllers\SchoolAbsenceController;

if (!class_exists(__NAMESPACE__ . '\\FrequenciaAdminController', false)) {
class FrequenciaAdminController extends BaseController
{
    public function index(): void
    {
        if (!class_exists('AdminPermissionMatrix')) {
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

if (!class_exists('FrequenciaAdminController', false) && class_exists(__NAMESPACE__ . '\\FrequenciaAdminController', false)) {
    class_alias(__NAMESPACE__ . '\\FrequenciaAdminController', 'FrequenciaAdminController');
}
