<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardConsulta;
use App\Modulos\DashboardGestao\Services\DashboardFiltro;
use App\Services\FrequencyService;
/**
 * Evolução mensal da frequência no ano letivo (Diário, aulas finalizadas).
 * Série da escola e, quando houver mais de um curso, as maiores turmas de curso.
 */
class EvolucaoWidget implements WidgetDashboard
{
    private DashboardConsulta $consulta;

    public function __construct(DashboardConsulta $consulta)
    {
        $this->consulta = $consulta;
    }

    public function chave(): string
    {
        return 'evolucao';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        $labels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        if ($filtro->semTurmas) {
            return [
                'ok' => true,
                'disponivel' => true,
                'labels' => $labels,
                'series' => [
                    ['label' => 'Escola', 'data' => array_fill(0, 12, null)],
                ],
                'nota' => 'Nenhuma turma neste recorte.',
            ];
        }

        $evo = (new FrequencyService())->evolucaoMensal($filtro->anoCivil, $filtro->turmaIds);
        if ($this->temPonto($evo['series'])) {
            return [
                'ok' => true,
                'disponivel' => true,
                'modo' => 'frequencia',
                'labels' => $evo['labels'],
                'series' => $evo['series'],
                'nota' => 'Ano letivo inteiro. Presente e atraso sobre aulas finalizadas do diário.',
            ];
        }

        return $this->evolucaoNotas($filtro);
    }

    /**
     * @param list<array{data?:list<mixed>}> $series
     */
    private function temPonto(array $series): bool
    {
        foreach ($series as $serie) {
            foreach ($serie['data'] ?? [] as $valor) {
                if ($valor !== null) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private function evolucaoNotas(DashboardFiltro $filtro): array
    {
        $info = \App\Core\PeriodoLetivo::doAno($filtro->anoCivil);
        $quantidade = max(1, (int) ($info['quantidade'] ?? 4));
        $labels = [];
        $data = [];
        $tem = false;
        for ($periodo = 1; $periodo <= $quantidade; $periodo++) {
            $labels[] = \App\Core\PeriodoLetivo::rotulo($filtro->anoCivil, $periodo);
            $media = $this->consulta->mediaNotasFicha($filtro, $periodo);
            $data[] = $media;
            if ($media !== null) {
                $tem = true;
            }
        }
        if (!$tem) {
            return [
                'ok' => true,
                'disponivel' => true,
                'modo' => 'frequencia',
                'labels' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                'series' => [['label' => 'Escola', 'data' => array_fill(0, 12, null)]],
                'nota' => 'Sem chamada no diário e sem nota calculada neste ano.',
            ];
        }

        return [
            'ok' => true,
            'disponivel' => true,
            'modo' => 'notas',
            'labels' => $labels,
            'series' => [['label' => 'Média', 'data' => $data]],
            'nota' => 'Média das notas calculadas na ficha, por período. Não há chamada no diário neste ano.',
        ];
    }
}

if (!class_exists('EvolucaoWidget', false) && class_exists(__NAMESPACE__ . '\\EvolucaoWidget', false)) {
    class_alias(__NAMESPACE__ . '\\EvolucaoWidget', 'EvolucaoWidget');
}
