<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardConsulta;
use App\Modulos\DashboardGestao\Services\DashboardFiltro;
use FrequencyService;

require_once dirname(__DIR__, 3) . '/Services/FrequencyService.php';
require_once dirname(__DIR__, 3) . '/Models/Education/ResultadoAcademico.php';

/**
 * Evolução por bimestre: frequência do Diário (sempre) e média homologada (se houver).
 * Metodologia da média: AVG(media_final) do snapshot homologado em resultado_academico,
 * sem recálculo a partir de notas brutas.
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
        $freqSvc = new FrequencyService();
        $labels = ['1º bim.', '2º bim.', '3º bim.', '4º bim.'];
        $frequencia = [];
        $media = [];
        $temMedia = false;

        if ($filtro->semTurmas) {
            return [
                'ok' => true,
                'disponivel' => true,
                'labels' => $labels,
                'series' => [
                    ['label' => 'Frequência (%)', 'data' => [null, null, null, null], 'fonte' => 'FrequencyService'],
                    ['label' => 'Média homologada', 'data' => [null, null, null, null], 'ocultar' => true],
                ],
                'nota' => 'Nenhuma turma neste recorte.',
            ];
        }

        $model = new \ResultadoAcademico();
        $schemaResultado = $model->schemaPronto();
        $db = \Database::getInstance();
        [$turmaSql, $turmaParams] = $this->consulta->sqlInTurmas($filtro->turmaIds, 'r.turma_id');

        for ($b = 1; $b <= 4; $b++) {
            $periodo = DashboardFiltro::periodoDoBimestre($filtro->anoCivil, $b);
            $frequencia[] = $freqSvc->percentualGeral($periodo['inicio'], $periodo['fim'], $filtro->turmaIds);
            $mediaBim = null;
            if ($schemaResultado) {
                $row = $db->fetch(
                    "SELECT AVG(r.media_final) AS media
                     FROM resultado_academico r
                     WHERE r.status = 'homologado'
                       AND r.ano_letivo = :ano
                       AND r.periodo_tipo = 'bimestre'
                       AND r.periodo_numero = :bim
                       AND r.media_final IS NOT NULL{$turmaSql}",
                    array_merge(['ano' => $filtro->anoCivil, 'bim' => $b], $turmaParams)
                );
                if ($row && $row['media'] !== null) {
                    $mediaBim = round((float) $row['media'], 1);
                    $temMedia = true;
                }
            }
            $media[] = $mediaBim;
        }

        return [
            'ok' => true,
            'disponivel' => true,
            'labels' => $labels,
            'series' => [
                [
                    'label' => 'Frequência (%)',
                    'data' => $frequencia,
                    'fonte' => 'FrequencyService (diário, aulas finalizadas)',
                ],
                [
                    'label' => 'Média homologada',
                    'data' => $media,
                    'fonte' => 'AVG(media_final) do snapshot homologado em resultado_academico. Sem recálculo.',
                    'ocultar' => !$temMedia,
                ],
            ],
            'nota' => 'Frequência: presente+atraso sobre registros do diário. Média: somente resultados homologados.',
        ];
    }
}
