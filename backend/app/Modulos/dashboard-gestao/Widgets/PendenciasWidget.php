<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardFiltro;
use App\Modulos\Diario\Services\ClassDiaryService;
use FrequencyService;

require_once dirname(__DIR__, 3) . '/Services/FrequencyService.php';
require_once dirname(__DIR__, 3) . '/Services/ClassDiaryService.php';
require_once dirname(__DIR__, 3) . '/Models/Exams/ExamBlock.php';

class PendenciasWidget implements WidgetDashboard
{
    public function chave(): string
    {
        return 'pendencias';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        $qs = http_build_query(array_merge($filtro->queryModulo(), ['status' => 'pendente']));
        $diario = new ClassDiaryService();
        $turmaId = $filtro->turmaId;
        $pendentes = $diario->acompanhamento($filtro->inicio, $filtro->fim, 0, 'pendente', $turmaId);
        if ($filtro->turmaIds !== [] && $turmaId <= 0) {
            $permitidas = array_flip($filtro->turmaIds);
            $pendentes = array_values(array_filter(
                $pendentes,
                static fn ($a) => isset($permitidas[(int) ($a['turma_id'] ?? 0)])
            ));
        }
        $chamadas = 0;
        $conteudo = 0;
        foreach ($pendentes as $aula) {
            $tipo = (string) ($aula['tipo_pendencia'] ?? 'chamada');
            if ($tipo === 'conteudo') {
                $conteudo++;
            } else {
                $chamadas++;
            }
        }

        $indicadores = $diario->indicadores($filtro->inicio, $filtro->fim);
        if ($filtro->turmaIds !== []) {
            $permitidas = array_flip($filtro->turmaIds);
            $indicadores = array_values(array_filter(
                $indicadores,
                static fn ($i) => isset($permitidas[(int) ($i['turma_id'] ?? 0)])
            ));
        }
        $resumo = $diario->resumoIndicadores($indicadores);
        $diariosPendentes = (int) ($resumo['atencao'] ?? 0) + (int) ($resumo['atraso'] ?? 0);

        $notasPendentes = $this->contarNotasPendentes($filtro);

        $alunosAtencao = $filtro->semTurmas
            ? 0
            : (new FrequencyService())->contarAbaixoDoMinimo($filtro->inicio, $filtro->fim, $filtro->turmaIds);

        return [
            'ok' => true,
            'disponivel' => true,
            'cards' => [
                [
                    'label' => 'Diários pendentes',
                    'valor' => $diariosPendentes,
                    'href' => '/admin/diario/indicadores?' . $qs,
                    'icon' => 'fa-book-open',
                    'valueClass' => $diariosPendentes > 0 ? 'text-amber-700' : 'text-green-700',
                    'iconClass' => 'bg-amber-50 text-amber-600',
                ],
                [
                    'label' => 'Chamadas pendentes',
                    'valor' => $chamadas,
                    'href' => '/admin/diario?' . $qs,
                    'icon' => 'fa-clipboard-list',
                    'valueClass' => $chamadas > 0 ? 'text-amber-700' : 'text-green-700',
                    'iconClass' => 'bg-amber-50 text-amber-600',
                ],
                [
                    'label' => 'Notas pendentes',
                    'valor' => $notasPendentes,
                    'href' => '/admin/provas',
                    'icon' => 'fa-pen-to-square',
                    'valueClass' => $notasPendentes > 0 ? 'text-amber-700' : 'text-green-700',
                    'iconClass' => 'bg-amber-50 text-amber-600',
                ],
                [
                    'label' => 'Alunos em atenção',
                    'valor' => $alunosAtencao,
                    'href' => '/admin/diario/indicadores?' . http_build_query($filtro->queryModulo()),
                    'icon' => 'fa-triangle-exclamation',
                    'valueClass' => $alunosAtencao > 0 ? 'text-red-700' : 'text-green-700',
                    'iconClass' => 'bg-red-50 text-red-600',
                    'nota' => 'Critério objetivo: frequência abaixo do mínimo legal (75%) no período, via FrequencyService.',
                ],
            ],
            'extra' => [
                'aulas_sem_conteudo' => $conteudo,
            ],
        ];
    }

    private function contarNotasPendentes(DashboardFiltro $filtro): int
    {
        try {
            $blocos = new \ExamBlock();
        } catch (\Throwable $e) {
            return 0;
        }
        $filters = ['status' => 'aguardando', 'bimestre' => $filtro->bimestre];
        if ($filtro->turmaId > 0) {
            $filters['turma_id'] = $filtro->turmaId;
        }
        return $blocos->getCountFiltered($filters);
    }
}
