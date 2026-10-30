<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardFiltro;
use App\Services\FrequencyService;
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
                    'indicador' => 'Frequência',
                    'valor' => number_format($pct, 0, ',', '.') . '%',
                    'situacao' => 'Em risco',
                    'badge' => $pct < 50 ? 'critico' : 'atencao',
                ];
            }
        }

        $model = new \App\Models\Education\ResultadoAcademico();
        if ($model->schemaPronto() && count($linhas) < 12) {
            $linhas = array_merge($linhas, $this->componentesAbaixo($filtro, 12 - count($linhas)));
        }
        if (count($linhas) < 5) {
            $consulta = new \App\Modulos\DashboardGestao\Services\DashboardConsulta(\App\Core\Database::getInstance());
            $linhas = array_merge($linhas, $consulta->atencaoNotasFicha($filtro, 5 - count($linhas)));
        }

        return [
            'ok' => true,
            'disponivel' => true,
            'linhas' => $linhas,
            'href' => '/admin/students',
            'nota' => 'Frequência abaixo de 75% ou componente em recuperação/reprovação.',
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
        $db = \App\Core\Database::getInstance();
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
            "SELECT a.nome AS aluno, t.nome AS turma, r.situacao, r.rotulo, r.media_final
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
            $critico = in_array($sit, ['reprovado_rendimento', 'reprovado_frequencia'], true);
            $recuperacao = in_array($sit, ['recuperacao', 'exame_final'], true);
            $media = $row['media_final'] ?? null;
            $out[] = [
                'aluno' => (string) $row['aluno'],
                'turma' => (string) $row['turma'],
                'indicador' => 'Notas',
                'valor' => $media === null ? '—' : number_format((float) $media, 1, ',', '.'),
                'situacao' => $critico ? 'Em risco' : ($recuperacao ? 'Recuperação' : 'Acompanhamento'),
                'badge' => $critico ? 'critico' : ($recuperacao ? 'recuperacao' : 'atencao'),
            ];
        }
        return $out;
    }
}

if (!class_exists('AtencaoPedagogicaWidget', false) && class_exists(__NAMESPACE__ . '\\AtencaoPedagogicaWidget', false)) {
    class_alias(__NAMESPACE__ . '\\AtencaoPedagogicaWidget', 'AtencaoPedagogicaWidget');
}
