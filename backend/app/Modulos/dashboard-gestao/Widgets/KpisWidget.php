<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardConsulta;
use App\Modulos\DashboardGestao\Services\DashboardFiltro;
use FrequencyService;

require_once dirname(__DIR__, 3) . '/Services/FrequencyService.php';

class KpisWidget implements WidgetDashboard
{
    private DashboardConsulta $consulta;

    public function __construct(DashboardConsulta $consulta)
    {
        $this->consulta = $consulta;
    }

    public function chave(): string
    {
        return 'kpis';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        $qs = http_build_query($filtro->queryModulo());
        $freq = $filtro->semTurmas
            ? null
            : (new FrequencyService())->percentualGeral($filtro->inicio, $filtro->fim, $filtro->turmaIds);
        $freqHref = defined('URL') ? URL . '/admin/presenca' : '/admin/presenca';
        if (!class_exists('LayoutHelper', false) || !\LayoutHelper::isModuleEnabled('presenca')) {
            $freqHref = defined('URL') ? URL . '/admin/diario' : '/admin/diario';
        }

        return [
            'ok' => true,
            'disponivel' => true,
            'cards' => [
                [
                    'label' => 'Alunos matriculados',
                    'valor' => $filtro->semTurmas ? 0 : $this->consulta->contarAlunosMatriculados($filtro),
                    'href' => '/admin/students',
                    'icon' => 'fa-user-graduate',
                    'valueClass' => 'text-gray-900',
                    'iconClass' => 'bg-slate-100 text-slate-600',
                ],
                [
                    'label' => 'Turmas ativas',
                    'valor' => $this->consulta->contarTurmasAtivas($filtro),
                    'href' => '/admin/turmas',
                    'icon' => 'fa-school',
                    'valueClass' => 'text-gray-900',
                    'iconClass' => 'bg-blue-50 text-blue-600',
                ],
                [
                    'label' => 'Professores ativos',
                    'valor' => $this->consulta->contarProfessoresAtivos($filtro),
                    'href' => '/admin/teachers',
                    'icon' => 'fa-user-tie',
                    'valueClass' => 'text-gray-900',
                    'iconClass' => 'bg-violet-50 text-violet-600',
                ],
                [
                    'label' => 'Frequência geral',
                    'valor' => $freq,
                    'sufixo' => $freq === null ? '' : '%',
                    'vazio' => $freq === null ? 'Sem chamada no período' : null,
                    'href' => $freqHref . ($qs !== '' ? '?' . $qs : ''),
                    'icon' => 'fa-clipboard-check',
                    'valueClass' => 'text-green-700',
                    'iconClass' => 'bg-green-50 text-green-600',
                    'nota' => 'Fonte: Diário de Classe (aulas finalizadas). Presente + atraso sobre o total de registros.',
                ],
            ],
        ];
    }
}
