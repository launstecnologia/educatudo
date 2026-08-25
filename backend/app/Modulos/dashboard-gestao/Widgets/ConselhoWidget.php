<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\ConselhoClasse\Services\ConselhoService;
use App\Modulos\DashboardGestao\Services\DashboardFiltro;

require_once dirname(__DIR__, 3) . '/Services/ConselhoService.php';

class ConselhoWidget implements WidgetDashboard
{
    public function chave(): string
    {
        return 'conselho';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        if (class_exists('LayoutHelper', false) && !\LayoutHelper::isModuleEnabled('conselho_classe')) {
            return [
                'ok' => true,
                'disponivel' => false,
                'motivo' => 'Módulo Conselho de Classe desabilitado nesta escola.',
            ];
        }

        $svc = new ConselhoService();
        if (!$svc->model()->schemaPronto()) {
            return [
                'ok' => true,
                'disponivel' => false,
                'motivo' => 'Execute a migration do Conselho de Classe para ver este bloco.',
                'href' => '/admin/conselhos',
            ];
        }

        $linhas = $svc->painel($filtro->anoCivil, $filtro->bimestre, $filtro->turmaId);
        if ($filtro->turmaIds !== [] && $filtro->turmaId <= 0) {
            $permitidas = array_flip($filtro->turmaIds);
            $linhas = array_values(array_filter(
                $linhas,
                static fn ($l) => isset($permitidas[(int) ($l['turma_id'] ?? 0)])
            ));
        }

        $prontas = 0;
        $pendencias = 0;
        $realizados = 0;
        $aguardando = 0;
        foreach ($linhas as $linha) {
            $status = (string) ($linha['status_exibicao'] ?? 'nao_iniciado');
            $pend = (int) (($linha['pendencias']['total'] ?? 0));
            if ($status === 'finalizado') {
                $realizados++;
            } elseif ($status === 'nao_iniciado' || $status === 'em_preparacao') {
                $aguardando++;
            }
            if ($pend > 0) {
                $pendencias++;
            } elseif (in_array($status, ['em_andamento', 'em_preparacao', 'nao_iniciado'], true)) {
                $prontas++;
            }
        }

        $qs = http_build_query($filtro->queryModulo());
        return [
            'ok' => true,
            'disponivel' => true,
            'turmas_prontas' => $prontas,
            'turmas_com_pendencias' => $pendencias,
            'realizados' => $realizados,
            'aguardando' => $aguardando,
            'href' => '/admin/conselhos?' . $qs,
        ];
    }
}
