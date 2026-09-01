<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardFiltro;
use App\Modulos\Diario\Services\ClassDiaryService;
use FrequencyService;

require_once dirname(__DIR__, 3) . '/Services/FrequencyService.php';
require_once dirname(__DIR__, 3) . '/Services/ClassDiaryService.php';

class FrequenciaHojeWidget implements WidgetDashboard
{
    public function chave(): string
    {
        return 'frequencia_hoje';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        if ($filtro->semTurmas) {
            return [
                'ok' => true,
                'disponivel' => true,
                'vazio' => true,
                'resumo' => [
                    'percentual' => null,
                    'presentes' => 0,
                    'ausentes' => 0,
                    'justificadas' => 0,
                    'total' => 0,
                    'aulas_finalizadas' => 0,
                    'chamadas_pendentes' => 0,
                ],
                'links' => [
                    'presenca' => (class_exists('LayoutHelper', false) && \LayoutHelper::isModuleEnabled('presenca')) ? '/admin/presenca' : null,
                    'diario' => '/admin/diario',
                    'chamadas' => '/admin/diario',
                ],
            ];
        }

        $freq = new FrequencyService();
        $resumo = $freq->resumoDoDia($filtro->hoje, $filtro->turmaIds);
        $diario = new ClassDiaryService();
        $pendentes = $diario->acompanhamento($filtro->hoje, $filtro->hoje, 0, 'pendente', $filtro->turmaId);
        if ($filtro->turmaIds !== [] && $filtro->turmaId <= 0) {
            $permitidas = array_flip($filtro->turmaIds);
            $pendentes = array_values(array_filter(
                $pendentes,
                static fn ($a) => isset($permitidas[(int) ($a['turma_id'] ?? 0)])
            ));
        }
        $resumo['chamadas_pendentes'] = count($pendentes);

        $qs = http_build_query(array_merge($filtro->queryModulo(), ['status' => 'pendente']));
        $presencaOn = class_exists('LayoutHelper', false) && \LayoutHelper::isModuleEnabled('presenca');

        return [
            'ok' => true,
            'disponivel' => true,
            'vazio' => ((int) ($resumo['aulas_finalizadas'] ?? 0) === 0),
            'resumo' => $resumo,
            'links' => [
                'presenca' => $presencaOn ? '/admin/presenca' : null,
                'diario' => '/admin/diario?' . $qs,
                'chamadas' => '/admin/diario?' . $qs,
            ],
        ];
    }
}
