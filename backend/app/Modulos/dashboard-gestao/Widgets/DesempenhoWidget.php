<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardConsulta;
use App\Modulos\DashboardGestao\Services\DashboardFiltro;

require_once dirname(__DIR__, 3) . '/Models/Education/ResultadoAcademico.php';

/**
 * Desempenho a partir do snapshot homologado (ResultadoAcademico).
 * Não aplica média hardcoded — só agrupa situações oficiais do motor.
 */
class DesempenhoWidget implements WidgetDashboard
{
    private DashboardConsulta $consulta;

    public function __construct(DashboardConsulta $consulta)
    {
        $this->consulta = $consulta;
    }

    public function chave(): string
    {
        return 'desempenho';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        $model = new \ResultadoAcademico();
        if (!$model->schemaPronto()) {
            return [
                'ok' => true,
                'disponivel' => false,
                'motivo' => 'Resultados finais ainda não estão disponíveis nesta escola.',
                'href' => '/admin/resultados-finais',
                'link_rotulo' => 'Abrir Resultados Finais',
            ];
        }

        if ($filtro->semTurmas) {
            return [
                'ok' => true,
                'disponivel' => false,
                'motivo' => 'Nenhuma turma neste recorte.',
                'href' => '/admin/resultados-finais',
                'link_rotulo' => 'Abrir Resultados Finais',
                'buckets' => [
                    'dentro_criterio' => 0,
                    'atencao' => 0,
                    'recuperacao' => 0,
                    'risco' => 0,
                ],
            ];
        }

        $db = \Database::getInstance();
        $where = ['r.status = :status', 'r.ano_letivo = :ano', 'r.periodo_tipo = :periodo_tipo', 'r.periodo_numero = :periodo_numero'];
        $params = [
            'status' => 'homologado',
            'ano' => $filtro->anoCivil,
            'periodo_tipo' => 'bimestre',
            'periodo_numero' => $filtro->bimestre,
        ];
        [$turmaSql, $turmaParams] = $this->consulta->sqlInTurmas($filtro->turmaIds, 'r.turma_id');

        $rows = $db->fetchAll(
            'SELECT r.situacao, COUNT(*) AS n
             FROM resultado_academico r
             WHERE ' . implode(' AND ', $where) . $turmaSql . '
             GROUP BY r.situacao',
            array_merge($params, $turmaParams)
        ) ?: [];

        $buckets = [
            'dentro_criterio' => 0,
            'atencao' => 0,
            'recuperacao' => 0,
            'risco' => 0,
        ];
        $total = 0;
        foreach ($rows as $row) {
            $n = (int) ($row['n'] ?? 0);
            $total += $n;
            $sit = (string) ($row['situacao'] ?? '');
            $buckets[$this->bucket($sit)] += $n;
        }

        if ($total === 0) {
            return [
                'ok' => true,
                'disponivel' => false,
                'motivo' => 'Ainda não há resultado homologado neste período.',
                'href' => '/admin/resultados-finais?' . http_build_query($filtro->queryModulo()),
                'link_rotulo' => 'Abrir Resultados Finais',
                'buckets' => $buckets,
            ];
        }

        return [
            'ok' => true,
            'disponivel' => true,
            'total' => $total,
            'buckets' => $buckets,
            'href' => '/admin/resultados-finais?' . http_build_query($filtro->queryModulo()),
            'rotulos' => [
                'dentro_criterio' => 'Dentro do critério',
                'atencao' => 'Em atenção',
                'recuperacao' => 'Recuperação',
                'risco' => 'Risco acadêmico',
            ],
        ];
    }

    private function bucket(string $situacao): string
    {
        if (in_array($situacao, ['aprovado', 'aprovado_recuperacao', 'aprovado_conselho'], true)) {
            return 'dentro_criterio';
        }
        if (in_array($situacao, ['recuperacao', 'exame_final'], true)) {
            return 'recuperacao';
        }
        if (in_array($situacao, ['reprovado_rendimento', 'reprovado_frequencia'], true)) {
            return 'risco';
        }
        return 'atencao';
    }
}
