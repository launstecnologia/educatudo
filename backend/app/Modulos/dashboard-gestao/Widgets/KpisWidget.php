<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardConsulta;
use App\Modulos\DashboardGestao\Services\DashboardFiltro;
use App\Services\FrequencyService;
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
        $freqSvc = new FrequencyService();
        $freq = $filtro->semTurmas
            ? null
            : $freqSvc->percentualGeral($filtro->inicio, $filtro->fim, $filtro->turmaIds);
        $freqHref = defined('URL') ? URL . '/admin/presenca' : '/admin/presenca';
        if (!class_exists('LayoutHelper', false) || !\App\Core\LayoutHelper::isModuleEnabled('presenca')) {
            $freqHref = defined('URL') ? URL . '/admin/diario' : '/admin/diario';
        }

        $alunos = $filtro->semTurmas ? 0 : $this->consulta->contarAlunosMatriculados($filtro);
        $deltaAlunos = $this->variacaoAlunos($filtro, $alunos);
        $ativas = $this->consulta->contarTurmasAtivas($filtro);
        $cadastradas = $this->consulta->contarTurmasCadastradas($filtro);
        if ($cadastradas < $ativas) {
            $cadastradas = $ativas;
        }

        $freqAnterior = null;
        if (!$filtro->semTurmas && $filtro->bimestre > 1) {
            $periodoAnterior = DashboardFiltro::periodoDoBimestre($filtro->anoCivil, $filtro->bimestre - 1);
            $freqAnterior = $freqSvc->percentualGeral($periodoAnterior['inicio'], $periodoAnterior['fim'], $filtro->turmaIds);
        }
        $deltaFreq = ($freq !== null && $freqAnterior !== null) ? round($freq - $freqAnterior, 1) : null;

        $media = $this->mediaHomologada($filtro, $filtro->bimestre);
        $mediaDaFicha = false;
        if ($media === null) {
            $media = $this->consulta->mediaNotasFicha($filtro);
            $mediaDaFicha = $media !== null;
        }
        $mediaAnterior = $filtro->bimestre > 1 ? $this->mediaHomologada($filtro, $filtro->bimestre - 1) : null;
        if ($mediaAnterior === null && $filtro->bimestre > 1) {
            $mediaAnterior = $this->consulta->mediaNotasFicha($filtro, $filtro->bimestre - 1);
        }
        $deltaMedia = ($media !== null && $mediaAnterior !== null) ? round($media - $mediaAnterior, 1) : null;

        return [
            'ok' => true,
            'disponivel' => true,
            'cards' => [
                [
                    'label' => 'Alunos matriculados',
                    'valor' => $alunos,
                    'href' => '/admin/students',
                    'icon' => 'fa-user-graduate',
                    'iconClass' => 'bg-emerald-50 text-emerald-600',
                    'delta_texto' => $deltaAlunos === null || abs($deltaAlunos) < 0.05 ? null : $this->numero($deltaAlunos) . '%',
                    'tom' => $this->tom($deltaAlunos),
                    'detalhe' => $deltaAlunos === null ? null : 'em relação ao bimestre anterior',
                ],
                [
                    'label' => 'Turmas ativas',
                    'valor' => $ativas,
                    'href' => '/admin/turmas',
                    'icon' => 'fa-chalkboard-user',
                    'iconClass' => 'bg-indigo-50 text-indigo-600',
                    'detalhe' => 'de ' . $cadastradas . ' turmas cadastradas',
                ],
                [
                    'label' => 'Frequência geral',
                    'valor' => $freq,
                    'sufixo' => $freq === null ? '' : '%',
                    'vazio' => $freq === null ? 'Sem chamada' : null,
                    'href' => $freqHref . ($qs !== '' ? '?' . $qs : ''),
                    'icon' => 'fa-calendar-check',
                    'iconClass' => 'bg-sky-50 text-sky-600',
                    'delta_texto' => $deltaFreq === null || abs($deltaFreq) < 0.05 ? null : $this->numero($deltaFreq) . '%',
                    'tom' => $this->tom($deltaFreq),
                    'detalhe' => $deltaFreq === null ? 'média no período' : 'em relação ao bimestre anterior',
                ],
                [
                    'label' => 'Média de notas',
                    'valor' => $media,
                    'vazio' => $media === null ? 'Sem resultado' : null,
                    'href' => '/admin/resultados-finais' . ($qs !== '' ? '?' . $qs : ''),
                    'icon' => 'fa-chart-line',
                    'iconClass' => 'bg-teal-50 text-teal-600',
                    'delta_texto' => $deltaMedia === null || abs($deltaMedia) < 0.05 ? null : $this->numero($deltaMedia),
                    'tom' => $this->tom($deltaMedia),
                    'detalhe' => ($deltaMedia !== null && abs($deltaMedia) >= 0.05)
                        ? 'em relação ao período anterior'
                        : ($mediaDaFicha ? 'média das notas calculadas' : 'média geral no período'),
                ],
            ],
        ];
    }

    private function variacaoAlunos(DashboardFiltro $filtro, int $atual): ?float
    {
        if ($filtro->bimestre <= 1 || $filtro->semTurmas || $atual < 0 || !$this->consulta->tabelaExiste('matricula')) {
            return null;
        }
        $periodo = DashboardFiltro::periodoDoBimestre($filtro->anoCivil, $filtro->bimestre - 1);
        $params = ['fim' => $periodo['fim']];
        $anoSql = '';
        if ($filtro->anoLetivoId > 0) {
            $anoSql = ' AND m.ano_letivo_id = :ano_letivo_id';
            $params['ano_letivo_id'] = $filtro->anoLetivoId;
        }
        [$turmaSql, $turmaParams] = $this->consulta->sqlInTurmas($filtro->turmaIds, 'm.turma_id');
        try {
            $row = \App\Core\Database::getInstance()->fetch(
                "SELECT COUNT(DISTINCT m.aluno_id) AS n
                 FROM matricula m
                 INNER JOIN alunos a ON a.id = m.aluno_id AND a.ativo = 1
                 WHERE COALESCE(m.data_entrada, DATE(m.created_at)) <= :fim
                   AND (m.data_saida IS NULL OR m.data_saida > :fim_saida){$anoSql}{$turmaSql}",
                array_merge($params, ['fim_saida' => $periodo['fim']], $turmaParams)
            );
        } catch (\Throwable $e) {
            return null;
        }
        $antes = (int) ($row['n'] ?? 0);
        if ($antes <= 0) {
            return null;
        }
        return round((($atual - $antes) / $antes) * 100, 1);
    }

    private function mediaHomologada(DashboardFiltro $filtro, int $bimestre): ?float
    {
        if ($filtro->semTurmas || $bimestre <= 0) {
            return null;
        }
        $model = new \App\Models\Education\ResultadoAcademico();
        if (!$model->schemaPronto()) {
            return null;
        }
        [$turmaSql, $turmaParams] = $this->consulta->sqlInTurmas($filtro->turmaIds, 'r.turma_id');
        try {
            $row = \App\Core\Database::getInstance()->fetch(
                "SELECT AVG(r.media_final) AS media
                 FROM resultado_academico r
                 WHERE r.status = 'homologado'
                   AND r.ano_letivo = :ano
                   AND r.periodo_tipo = 'bimestre'
                   AND r.periodo_numero = :bim
                   AND r.media_final IS NOT NULL{$turmaSql}",
                array_merge(['ano' => $filtro->anoCivil, 'bim' => $bimestre], $turmaParams)
            );
        } catch (\Throwable $e) {
            return null;
        }
        if (!$row || $row['media'] === null) {
            return null;
        }
        return round((float) $row['media'], 1);
    }

    private function numero(float $n): string
    {
        $abs = abs($n);
        $casas = abs($abs - round($abs)) < 0.05 ? 0 : 1;
        return number_format($abs, $casas, ',', '.');
    }

    private function tom(?float $delta): string
    {
        if ($delta === null || abs($delta) < 0.05) {
            return 'neutro';
        }
        return $delta > 0 ? 'positivo' : 'negativo';
    }
}

if (!class_exists('KpisWidget', false) && class_exists(__NAMESPACE__ . '\\KpisWidget', false)) {
    class_alias(__NAMESPACE__ . '\\KpisWidget', 'KpisWidget');
}
