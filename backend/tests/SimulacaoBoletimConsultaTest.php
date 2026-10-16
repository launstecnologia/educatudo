<?php

require_once dirname(__DIR__) . '/app/Modulos/boletins/Services/SimulacaoBoletimConsulta.php';

use App\Modulos\Boletins\Services\SimulacaoBoletimConsulta;
use PHPUnit\Framework\TestCase;

class SimulacaoBoletimConsultaTest extends TestCase
{
    public function testPeriodoDoBimestreNaoUsaAnoCivilAtual(): void
    {
        $periodo = SimulacaoBoletimConsulta::resolverPeriodo([
            'ano_letivo' => 2022,
            'bimestre' => 1,
            'default_data_inicio' => null,
            'default_data_fim' => null,
        ], null, null);

        $this->assertSame('bimestre', $periodo['tipo']);
        $this->assertSame(2022, $periodo['ano']);
        $this->assertSame(1, $periodo['bimestre']);
        $this->assertNotSame((int) date('Y'), $periodo['ano']);
    }

    public function testDatasDoEventoVencemOBimestre(): void
    {
        $periodo = SimulacaoBoletimConsulta::resolverPeriodo([
            'ano_letivo' => 2022,
            'bimestre' => 1,
            'default_data_inicio' => '2022-02-01',
            'default_data_fim' => '2022-04-15',
        ], '', '');

        $this->assertSame('datas', $periodo['tipo']);
        $this->assertSame('2022-02-01', $periodo['inicio']);
        $this->assertSame('2022-04-15', $periodo['fim']);
    }

    public function testDatasExplicitasVencemOCadastro(): void
    {
        $periodo = SimulacaoBoletimConsulta::resolverPeriodo([
            'ano_letivo' => 2022,
            'bimestre' => 1,
            'default_data_inicio' => '2022-01-01',
            'default_data_fim' => '2022-03-31',
        ], '2022-02-10', '2022-02-20');

        $this->assertSame('2022-02-10', $periodo['inicio']);
        $this->assertSame('2022-02-20', $periodo['fim']);
    }

    public function testIntervaloInvertidoNaoETrocadoEmSilencio(): void
    {
        $periodo = SimulacaoBoletimConsulta::resolverPeriodo([
            'ano_letivo' => 2022,
            'bimestre' => 1,
        ], '2022-04-01', '2022-01-01');

        $this->assertSame('indefinido', $periodo['tipo']);
        $this->assertStringContainsString('anterior', $periodo['mensagem']);
    }

    public function testAvaliacaoDeOutroModeloEAnoDivergente(): void
    {
        $this->assertSame(
            'Selecione uma avaliação para simular.',
            SimulacaoBoletimConsulta::inconsistencia(['id' => 193, 'ano_letivo' => 2022], null)
        );
        $this->assertSame(
            'Esta avaliação não pertence a este modelo de boletim.',
            SimulacaoBoletimConsulta::inconsistencia(
                ['id' => 193, 'ano_letivo' => 2022],
                ['id' => 298, 'boletim_id' => 50, 'ano_letivo' => 2022]
            )
        );
        $this->assertSame(
            'O ano da avaliação (2023) não é o ano deste modelo (2022).',
            SimulacaoBoletimConsulta::inconsistencia(
                ['id' => 193, 'ano_letivo' => 2022],
                ['id' => 298, 'boletim_id' => 193, 'ano_letivo' => 2023]
            )
        );
        $this->assertSame(
            'Esta avaliação não pertence a este modelo de boletim.',
            SimulacaoBoletimConsulta::inconsistencia(
                ['id' => 193, 'ano_letivo' => 2022],
                ['id' => 298, 'boletim_id' => 0, 'ano_letivo' => 2022]
            )
        );
        $this->assertNull(SimulacaoBoletimConsulta::inconsistencia(
            ['id' => 193, 'ano_letivo' => 2022],
            ['id' => 298, 'boletim_id' => 193, 'ano_letivo' => 2022]
        ));
        $this->assertNull(SimulacaoBoletimConsulta::inconsistencia(
            null,
            ['id' => 21, 'boletim_id' => 6, 'ano_letivo' => 2025]
        ));
    }
}
