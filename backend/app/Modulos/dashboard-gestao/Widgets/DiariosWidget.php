<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardFiltro;
use App\Modulos\Diario\Services\ClassDiaryService;

require_once dirname(__DIR__, 3) . '/Services/ClassDiaryService.php';

class DiariosWidget implements WidgetDashboard
{
    public function chave(): string
    {
        return 'diarios';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        if (class_exists('LayoutHelper', false) && !\LayoutHelper::isModuleEnabled('diario_classe')) {
            return [
                'ok' => true,
                'disponivel' => false,
                'motivo' => 'Módulo Diário de Classe desabilitado nesta escola.',
            ];
        }

        if ($filtro->semTurmas) {
            return [
                'ok' => true,
                'disponivel' => true,
                'percentual' => null,
                'completos' => 0,
                'com_pendencias' => 0,
                'sem_atualizacao' => 0,
                'aulas_sem_conteudo' => 0,
                'chamadas_nao_realizadas' => 0,
                'href' => '/admin/diario/indicadores',
            ];
        }

        $diario = new ClassDiaryService();
        $indicadores = $diario->indicadores($filtro->inicio, $filtro->fim);
        if ($filtro->turmaIds !== []) {
            $permitidas = array_flip($filtro->turmaIds);
            $indicadores = array_values(array_filter(
                $indicadores,
                static fn ($i) => isset($permitidas[(int) ($i['turma_id'] ?? 0)])
            ));
        }
        $resumo = $diario->resumoIndicadores($indicadores);
        $pendentes = $diario->acompanhamento($filtro->inicio, $filtro->fim, 0, 'pendente', $filtro->turmaId);
        if ($filtro->turmaIds !== [] && $filtro->turmaId <= 0) {
            $permitidas = array_flip($filtro->turmaIds);
            $pendentes = array_values(array_filter(
                $pendentes,
                static fn ($a) => isset($permitidas[(int) ($a['turma_id'] ?? 0)])
            ));
        }
        $chamadas = 0;
        $conteudo = 0;
        foreach ($pendentes as $aula) {
            if (((string) ($aula['tipo_pendencia'] ?? 'chamada')) === 'conteudo') {
                $conteudo++;
            } else {
                $chamadas++;
            }
        }

        $qs = http_build_query($filtro->queryModulo());
        return [
            'ok' => true,
            'disponivel' => true,
            'percentual' => $resumo['cobertura_media'],
            'completos' => (int) ($resumo['em_dia'] ?? 0),
            'com_pendencias' => (int) ($resumo['atencao'] ?? 0) + (int) ($resumo['atraso'] ?? 0),
            'sem_atualizacao' => (int) ($resumo['atraso'] ?? 0),
            'aulas_sem_conteudo' => $conteudo,
            'chamadas_nao_realizadas' => $chamadas,
            'href' => '/admin/diario/indicadores?' . $qs,
        ];
    }
}
