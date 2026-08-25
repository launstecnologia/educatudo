<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardFiltro;
use FrequencyService;

require_once dirname(__DIR__, 3) . '/Services/FrequencyService.php';
require_once dirname(__DIR__, 3) . '/Models/Education/ResultadoAcademico.php';

class AtencaoPedagogicaWidget implements WidgetDashboard
{
    public function chave(): string
    {
        return 'atencao_pedagogica';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        $linhas = [];
        if (!$filtro->semTurmas) {
            $freq = new FrequencyService();
            foreach ($freq->abaixoDoMinimoGeral($filtro->inicio, $filtro->fim, $filtro->turmaIds, FrequencyService::MINIMO_LEGAL, 12) as $aluno) {
                $pct = (float) ($aluno['percentual'] ?? 0);
                $linhas[] = [
                    'aluno' => (string) $aluno['nome'],
                    'turma' => (string) $aluno['turma_nome'],
                    'indicador' => 'Frequência abaixo de ' . (int) FrequencyService::MINIMO_LEGAL . '%',
                    'situacao' => $pct < 50 ? 'Crítico' : 'Atenção',
                    'badge' => $pct < 50 ? 'critico' : 'atencao',
                ];
            }
        }

        $model = new \ResultadoAcademico();
        if ($model->schemaPronto() && count($linhas) < 12) {
            $linhas = array_merge($linhas, $this->componentesAbaixo($filtro, 12 - count($linhas)));
        }

        return [
            'ok' => true,
            'disponivel' => true,
            'linhas' => $linhas,
            'nota' => 'Não exibe ocorrências. Frequência: mínimo legal 75%. Componentes: situação homologada (recuperação/reprovação).',
        ];
    }

    /**
     * @return list<array<string,string>>
     */
    private function componentesAbaixo(DashboardFiltro $filtro, int $limite): array
    {
        if ($limite <= 0) {
            return [];
        }
        $db = \Database::getInstance();
        $where = [
            "r.status = 'homologado'",
            'r.ano_letivo = :ano',
            "r.periodo_tipo = 'bimestre'",
            'r.periodo_numero = :bim',
            "r.situacao IN ('recuperacao','exame_final','reprovado_rendimento','progressao_parcial','dependencia','resultado_pendente')",
        ];
        $params = ['ano' => $filtro->anoCivil, 'bim' => $filtro->bimestre];
        $turmaSql = '';
        if ($filtro->turmaIds !== []) {
            $ph = [];
            foreach ($filtro->turmaIds as $i => $id) {
                $k = 'at_turma_' . $i;
                $ph[] = ':' . $k;
                $params[$k] = (int) $id;
            }
            $turmaSql = ' AND r.turma_id IN (' . implode(',', $ph) . ')';
        }
        $limite = (int) $limite;
        $rows = $db->fetchAll(
            "SELECT a.nome AS aluno, t.nome AS turma, r.situacao, r.rotulo
             FROM resultado_academico r
             INNER JOIN alunos a ON a.id = r.aluno_id
             INNER JOIN turmas t ON t.id = r.turma_id
             WHERE " . implode(' AND ', $where) . "{$turmaSql}
             ORDER BY r.situacao ASC, a.nome ASC
             LIMIT {$limite}",
            $params
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $sit = (string) ($row['situacao'] ?? '');
            $critico = in_array($sit, ['reprovado_rendimento'], true);
            $out[] = [
                'aluno' => (string) $row['aluno'],
                'turma' => (string) $row['turma'],
                'indicador' => 'Componentes abaixo do critério',
                'situacao' => (string) ($row['rotulo'] ?? 'Atenção'),
                'badge' => $critico ? 'critico' : 'atencao',
            ];
        }
        return $out;
    }
}
