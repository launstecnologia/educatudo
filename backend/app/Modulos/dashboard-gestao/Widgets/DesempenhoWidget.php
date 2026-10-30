<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardConsulta;
use App\Modulos\DashboardGestao\Services\DashboardFiltro;


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
        $model = new \App\Models\Education\ResultadoAcademico();
        if (!$model->schemaPronto()) {
            $ficha = $this->montarDaFicha($filtro);
            if ($ficha !== null) {
                return $ficha;
            }
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

        $db = \App\Core\Database::getInstance();
        $where = ['r.status = :status', 'r.ano_letivo = :ano', 'r.periodo_tipo = :periodo_tipo', 'r.periodo_numero = :periodo_numero'];
        $params = [
            'status' => 'homologado',
            'ano' => $filtro->anoCivil,
            'periodo_tipo' => $this->tipoPeriodo($filtro->anoCivil),
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
            $ficha = $this->montarDaFicha($filtro);
            if ($ficha !== null) {
                return $ficha;
            }
            return [
                'ok' => true,
                'disponivel' => false,
                'motivo' => 'Ainda não há resultado homologado neste período.',
                'href' => '/admin/resultados-finais?' . http_build_query($filtro->queryModulo()),
                'link_rotulo' => 'Abrir Resultados Finais',
                'buckets' => $buckets,
            ];
        }

        $qs = http_build_query($filtro->queryModulo());

        return [
            'ok' => true,
            'disponivel' => true,
            'total' => $total,
            'buckets' => $buckets,
            'disciplinas' => $this->disciplinas($filtro, $turmaSql, $turmaParams),
            'distribuicao' => $this->distribuicao($filtro, $turmaSql, $turmaParams),
            'periodo_rotulo' => \App\Core\PeriodoLetivo::rotulo($filtro->anoCivil, $filtro->bimestre),
            'href' => '/admin/resultados-finais?' . $qs,
            'rotulos' => [
                'dentro_criterio' => 'Aprovados',
                'recuperacao' => 'Em recuperação',
                'atencao' => 'Em atenção',
                'risco' => 'Reprovados',
            ],
        ];
    }

    /**
     * @param array<string,int> $turmaParams
     * @return list<array{nome:string,media:float}>
     */
    private function disciplinas(DashboardFiltro $filtro, string $turmaSql, array $turmaParams): array
    {
        if (!$this->consulta->tabelaExiste('resultado_academico_itens')) {
            return [];
        }
        $db = \App\Core\Database::getInstance();
        try {
            $rows = $db->fetchAll(
                "SELECT i.materia_nome AS nome, AVG(i.media_final) AS media
                 FROM resultado_academico_itens i
                 INNER JOIN resultado_academico r ON r.id = i.resultado_id
                 WHERE r.status = 'homologado'
                   AND r.ano_letivo = :ano
                   AND r.periodo_tipo = 'bimestre'
                   AND r.periodo_numero = :bim
                   AND i.media_final IS NOT NULL{$turmaSql}
                 GROUP BY i.materia_nome
                 ORDER BY media DESC
                 LIMIT 8",
                array_merge(['ano' => $filtro->anoCivil, 'bim' => $filtro->bimestre], $turmaParams)
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'nome' => (string) ($row['nome'] ?? 'Componente'),
                'media' => round((float) $row['media'], 1),
            ];
        }
        return $out;
    }

    /**
     * @param array<string,int> $turmaParams
     * @return list<array{faixa:string,total:int}>
     */
    private function distribuicao(DashboardFiltro $filtro, string $turmaSql, array $turmaParams): array
    {
        $faixas = [
            ['faixa' => '0–3', 'total' => 0],
            ['faixa' => '3,1–5', 'total' => 0],
            ['faixa' => '5,1–7', 'total' => 0],
            ['faixa' => '7,1–9', 'total' => 0],
            ['faixa' => '9,1–10', 'total' => 0],
        ];
        $db = \App\Core\Database::getInstance();
        try {
            $row = $db->fetch(
                "SELECT
                    SUM(CASE WHEN r.media_final <= 3 THEN 1 ELSE 0 END) AS f1,
                    SUM(CASE WHEN r.media_final > 3 AND r.media_final <= 5 THEN 1 ELSE 0 END) AS f2,
                    SUM(CASE WHEN r.media_final > 5 AND r.media_final <= 7 THEN 1 ELSE 0 END) AS f3,
                    SUM(CASE WHEN r.media_final > 7 AND r.media_final <= 9 THEN 1 ELSE 0 END) AS f4,
                    SUM(CASE WHEN r.media_final > 9 THEN 1 ELSE 0 END) AS f5
                 FROM resultado_academico r
                 WHERE r.status = 'homologado'
                   AND r.ano_letivo = :ano
                   AND r.periodo_tipo = 'bimestre'
                   AND r.periodo_numero = :bim
                   AND r.media_final IS NOT NULL{$turmaSql}",
                array_merge(['ano' => $filtro->anoCivil, 'bim' => $filtro->bimestre], $turmaParams)
            );
        } catch (\Throwable $e) {
            return $faixas;
        }
        if (!$row) {
            return $faixas;
        }
        $faixas[0]['total'] = (int) ($row['f1'] ?? 0);
        $faixas[1]['total'] = (int) ($row['f2'] ?? 0);
        $faixas[2]['total'] = (int) ($row['f3'] ?? 0);
        $faixas[3]['total'] = (int) ($row['f4'] ?? 0);
        $faixas[4]['total'] = (int) ($row['f5'] ?? 0);
        return $faixas;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function montarDaFicha(DashboardFiltro $filtro): ?array
    {
        $medias = $this->consulta->mediasPorAlunoFicha($filtro);
        if ($medias === []) {
            return null;
        }
        $minima = $this->consulta->notaMinimaRegra($filtro);
        $acima = 0;
        $abaixo = 0;
        if ($minima !== null) {
            foreach ($medias as $media) {
                if ($media + 0.00001 >= $minima) {
                    $acima++;
                } else {
                    $abaixo++;
                }
            }
        }
        $minTxt = $minima !== null ? number_format($minima, 1, ',', '.') : '';

        return [
            'ok' => true,
            'disponivel' => true,
            'fonte' => 'ficha',
            'total' => count($medias),
            'buckets' => [
                'dentro_criterio' => $acima,
                'atencao' => $abaixo,
                'recuperacao' => 0,
                'risco' => 0,
            ],
            'chaves' => $minima !== null ? ['dentro_criterio', 'atencao'] : [],
            'disciplinas' => $this->consulta->disciplinasFicha($filtro),
            'distribuicao' => $this->faixasDeMedias($medias),
            'periodo_rotulo' => \App\Core\PeriodoLetivo::rotulo($filtro->anoCivil, $filtro->bimestre),
            'href' => '/admin/boletim',
            'rotulos' => [
                'dentro_criterio' => 'A partir de ' . $minTxt,
                'atencao' => 'Abaixo de ' . $minTxt,
            ],
            'nota' => 'Notas já calculadas na ficha. Aprovação e reprovação só entram depois da homologação.',
        ];
    }

    /**
     * @param list<float> $medias
     * @return list<array{faixa:string,total:int}>
     */
    private function faixasDeMedias(array $medias): array
    {
        $totais = [0, 0, 0, 0, 0];
        foreach ($medias as $media) {
            if ($media <= 3) {
                $totais[0]++;
            } elseif ($media <= 5) {
                $totais[1]++;
            } elseif ($media <= 7) {
                $totais[2]++;
            } elseif ($media <= 9) {
                $totais[3]++;
            } else {
                $totais[4]++;
            }
        }
        $faixas = ['0–3', '3,1–5', '5,1–7', '7,1–9', '9,1–10'];
        $out = [];
        foreach ($faixas as $i => $faixa) {
            $out[] = ['faixa' => $faixa, 'total' => $totais[$i]];
        }
        return $out;
    }

    private function tipoPeriodo(int $ano): string
    {
        $tipo = (string) (\App\Core\PeriodoLetivo::doAno($ano)['tipo'] ?? 'bimestre');
        return in_array($tipo, ['bimestre', 'trimestre', 'semestre', 'ano'], true) ? $tipo : 'bimestre';
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

if (!class_exists('DesempenhoWidget', false) && class_exists(__NAMESPACE__ . '\\DesempenhoWidget', false)) {
    class_alias(__NAMESPACE__ . '\\DesempenhoWidget', 'DesempenhoWidget');
}
