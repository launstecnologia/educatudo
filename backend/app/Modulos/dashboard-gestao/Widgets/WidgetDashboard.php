<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardFiltro;

interface WidgetDashboard
{
    public function chave(): string;

    /**
     * @return array<string,mixed>
     */
    public function montar(DashboardFiltro $filtro): array;
}

if (!class_exists('WidgetDashboard', false) && class_exists(__NAMESPACE__ . '\\WidgetDashboard', false)) {
    class_alias(__NAMESPACE__ . '\\WidgetDashboard', 'WidgetDashboard');
}
