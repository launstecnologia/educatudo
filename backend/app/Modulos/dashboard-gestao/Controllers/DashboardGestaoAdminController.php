<?php
/**
 * EducaTudo - Dashboard de Gestão Escolar (admin)
 */

require_once __DIR__ . '/../../../Controllers/Admin/AdminBaseController.php';
require_once __DIR__ . '/../Services/DashboardGestaoService.php';
require_once __DIR__ . '/../Services/DashboardFiltro.php';
require_once __DIR__ . '/../Services/DashboardConsulta.php';
require_once __DIR__ . '/../Widgets/WidgetDashboard.php';
require_once __DIR__ . '/../Widgets/KpisWidget.php';
require_once __DIR__ . '/../Widgets/PendenciasWidget.php';
require_once __DIR__ . '/../Widgets/FrequenciaHojeWidget.php';
require_once __DIR__ . '/../Widgets/DesempenhoWidget.php';
require_once __DIR__ . '/../Widgets/EvolucaoWidget.php';
require_once __DIR__ . '/../Widgets/AtencaoPedagogicaWidget.php';
require_once __DIR__ . '/../Widgets/DiariosWidget.php';
require_once __DIR__ . '/../Widgets/AvaliacoesWidget.php';
require_once __DIR__ . '/../Widgets/ConselhoWidget.php';
require_once __DIR__ . '/../Widgets/OcorrenciasWidget.php';
require_once __DIR__ . '/../Widgets/CalendarioWidget.php';
require_once __DIR__ . '/../Widgets/MatriculasWidget.php';

use App\Modulos\DashboardGestao\Services\DashboardGestaoService;

if (!class_exists('DashboardGestaoAdminController', false)) {
class DashboardGestaoAdminController extends AdminBaseController
{
    private function service(): DashboardGestaoService
    {
        return new DashboardGestaoService($this->db);
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('dashboard', 'visualizar', false)) {
            return;
        }

        $user = $this->auth->getUser() ?? [];
        $svc = $this->service();
        try {
            $filtros = $svc->montarFiltros($_GET, $user);
        } catch (Throwable $e) {
            $filtros = [
                'filtro' => [],
                'anos' => [],
                'cursos' => [],
                'series' => [],
                'turmas' => [],
                'turnos' => [],
                'widgets' => $svc->widgetsVisiveis($user),
            ];
        }

        $alunosOnline = 0;
        try {
            $authManager = new AuthManager();
            $online = $authManager->getAlunosOnline();
            $alunosOnline = is_array($online) ? count($online) : 0;
        } catch (Throwable $e) {
            $alunosOnline = 0;
        }

        $alertasNovos = null;
        if ($this->podeVerAlertasSensiveis($user)) {
            try {
                $row = $this->db->fetch("SELECT COUNT(*) AS n FROM alertas_sensiveis WHERE status = 'novo'");
                $alertasNovos = (int) ($row['n'] ?? 0);
            } catch (Throwable $e) {
                $alertasNovos = null;
            }
        }

        $aulasOnline = [];
        if (LayoutHelper::isModuleEnabled('aulas_online')) {
            try {
                require_once __DIR__ . '/../../../Models/Education/OnlineClass.php';
                $aulasOnline = (new OnlineClass())->listLiveAndUpcomingForAdmin(5);
            } catch (Throwable $e) {
                $aulasOnline = [];
            }
        }

        $this->viewWithLayout('admin', 'admin/dashboard-gestao/index', [
            'title' => 'Dashboard Acadêmico - EducaTudo',
            'user' => $user,
            'current_page' => 'dashboard',
            'csrf_token' => $this->generateCsrfToken(),
            'filtros' => $filtros,
            'alunos_online' => $alunosOnline,
            'alertas_novos' => $alertasNovos,
            'aulas_online' => $aulasOnline,
            'widget_url' => URL . '/admin/dashboard/widget',
            'filtros_url' => URL . '/admin/dashboard/filtros',
        ]);
    }

    public function filtros(): void
    {
        if (!$this->enforceAdminPermissionKey('dashboard', 'visualizar', true)) {
            return;
        }
        $user = $this->auth->getUser() ?? [];
        try {
            $this->json($this->service()->montarFiltros($_GET, $user));
        } catch (Throwable $e) {
            $this->json([
                'filtro' => [],
                'anos' => [],
                'cursos' => [],
                'series' => [],
                'turmas' => [],
                'turnos' => [],
                'widgets' => $this->service()->widgetsVisiveis($user),
            ]);
        }
    }

    public function widget($chave): void
    {
        if (!$this->enforceAdminPermissionKey('dashboard', 'visualizar', true)) {
            return;
        }
        $chave = preg_replace('/[^a-z0-9_]/', '', (string) $chave) ?? '';
        $user = $this->auth->getUser() ?? [];
        $this->json($this->service()->widget($chave, $_GET, $user));
    }
}
}
