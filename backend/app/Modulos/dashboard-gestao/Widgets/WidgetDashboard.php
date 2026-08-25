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
