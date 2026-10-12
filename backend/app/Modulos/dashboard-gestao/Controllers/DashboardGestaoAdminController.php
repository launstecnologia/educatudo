<?php

namespace App\Modulos\DashboardGestao\Controllers;
/**
 * EducaTudo - Dashboard de Gestão Escolar (admin)
 */


use App\Modulos\DashboardGestao\Services\DashboardGestaoService;

if (!class_exists(__NAMESPACE__ . '\\DashboardGestaoAdminController', false)) {
class DashboardGestaoAdminController extends \App\Controllers\Admin\AdminBaseController
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
        } catch (\Throwable $e) {
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
            $authManager = new \App\Core\AuthManager();
            $online = $authManager->getAlunosOnline();
            $alunosOnline = is_array($online) ? count($online) : 0;
        } catch (\Throwable $e) {
            $alunosOnline = 0;
        }

        $alertasNovos = null;
        if ($this->podeVerAlertasSensiveis($user)) {
            try {
                $row = $this->db->fetch("SELECT COUNT(*) AS n FROM alertas_sensiveis WHERE status = 'novo'");
                $alertasNovos = (int) ($row['n'] ?? 0);
            } catch (\Throwable $e) {
                $alertasNovos = null;
            }
        }

        $aulasOnline = [];
        if (\App\Core\LayoutHelper::isModuleEnabled('aulas_online')) {
            try {
                $aulasOnline = (new \App\Models\Education\OnlineClass())->listLiveAndUpcomingForAdmin(5);
            } catch (\Throwable $e) {
                $aulasOnline = [];
            }
        }

        $this->viewWithLayout('admin', 'admin/dashboard-gestao/index', [
            'title' => 'Dashboard - EducaTudo',
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
        } catch (\Throwable $e) {
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

if (!class_exists('DashboardGestaoAdminController', false) && class_exists(__NAMESPACE__ . '\\DashboardGestaoAdminController', false)) {
    class_alias(__NAMESPACE__ . '\\DashboardGestaoAdminController', 'DashboardGestaoAdminController');
}
