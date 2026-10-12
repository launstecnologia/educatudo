<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardFiltro;
use App\Modulos\Ocorrencias\Services\OcorrenciaService;


class OcorrenciasWidget implements WidgetDashboard
{
    public function chave(): string
    {
        return 'ocorrencias';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        if (class_exists('LayoutHelper', false) && !\App\Core\LayoutHelper::isModuleEnabled('ocorrencias')) {
            return [
                'ok' => true,
                'disponivel' => false,
                'motivo' => 'Módulo Ocorrências desabilitado nesta escola.',
            ];
        }

        $svc = new OcorrenciaService();
        $inicio = date('Y-m-d', strtotime('-14 days'));
        $fim = $filtro->hoje;
        $base = [
            'data_inicio' => $inicio,
            'data_fim' => $fim,
        ];
        if ($filtro->turmaId > 0) {
            $base['turma_id'] = $filtro->turmaId;
        }

        $total = $svc->model()->contar($base);
        $abertas = $svc->model()->schemaEstendido()
            ? $svc->model()->contar(array_merge($base, ['status' => 'aberta']))
            : $total;

        $porCategoria = [];
        foreach ($svc->model()->categorias(true) as $cat) {
            $n = $svc->model()->contar(array_merge($base, ['categoria_id' => (int) $cat['id']]));
            if ($n > 0) {
                $porCategoria[] = [
                    'nome' => (string) ($cat['nome'] ?? ''),
                    'total' => $n,
                ];
            }
        }

        $aguardando = $this->contarSemEncaminhamento($svc, $inicio, $fim, $filtro->turmaId);

        return [
            'ok' => true,
            'disponivel' => true,
            'total' => $total,
            'aguardando_encaminhamento' => $aguardando,
            'abertas' => $abertas,
            'por_categoria' => $porCategoria,
            'href' => '/admin/ocorrencias',
            'nota' => 'Por privacidade, detalhes ficam no módulo de Ocorrências.',
        ];
    }

    private function contarSemEncaminhamento(OcorrenciaService $svc, string $inicio, string $fim, int $turmaId): int
    {
        $model = $svc->model();
        if (!$model->schemaEstendido()) {
            return 0;
        }
        try {
            $db = \App\Core\Database::getInstance();
            $sql = "SELECT COUNT(*) AS n FROM alunos_ocorrencias o
                    WHERE DATE(o.data_ocorrencia) BETWEEN :inicio AND :fim
                      AND (o.encaminhamento IS NULL OR o.encaminhamento = '')
                      AND o.status <> 'encerrada'";
            $params = ['inicio' => $inicio, 'fim' => $fim];
            if ($turmaId > 0) {
                $sql .= ' AND o.turma_id = :turma_id';
                $params['turma_id'] = $turmaId;
            }
            $row = $db->fetch($sql, $params);
            return (int) ($row['n'] ?? 0);
        } catch (\Throwable $e) {
            return $model->contar(['data_inicio' => $inicio, 'data_fim' => $fim, 'status' => 'aberta']);
        }
    }
}

if (!class_exists('OcorrenciasWidget', false) && class_exists(__NAMESPACE__ . '\\OcorrenciasWidget', false)) {
    class_alias(__NAMESPACE__ . '\\OcorrenciasWidget', 'OcorrenciasWidget');
}
