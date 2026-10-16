<?php

require_once dirname(__DIR__) . '/app/Modulos/boletins/Services/GeradorAvaliacoesAnualService.php';

use App\Modulos\Boletins\Services\GeradorAvaliacoesAnualService;
use PHPUnit\Framework\TestCase;

class GeradorAvaliacoesAnualServiceTest extends TestCase
{
    public function testPeriodosPadraoTemQuatroBimestres(): void
    {
        $periodos = GeradorAvaliacoesAnualService::periodosPadrao(2026);
        $this->assertCount(4, $periodos);
        $this->assertSame('2026-02-01', $periodos[0]['inicio']);
        $this->assertSame('2026-12-15', $periodos[3]['fim']);
    }

    public function testCalendarioPreencheBimestresEClipaNoAnoLetivo(): void
    {
        $eventos = [
            ['tipo' => 'avaliacao', 'descricao' => '1º bimestre', 'data_inicio' => '2026-01-10', 'data_fim' => '2026-03-20'],
            ['tipo' => 'avaliacao', 'descricao' => 'B2 provas', 'data_inicio' => '2026-04-01', 'data_fim' => '2026-06-10'],
        ];
        $periodos = GeradorAvaliacoesAnualService::periodosPrevistos(2026, $eventos, '2026-02-01', '2026-11-30');
        $this->assertCount(4, $periodos);
        $this->assertSame(1, $periodos[0]['bimestre']);
        $this->assertSame('2026-02-01', $periodos[0]['inicio']);
        $this->assertSame('calendario', $periodos[0]['origem']);
        $this->assertSame(2, $periodos[1]['bimestre']);
        $this->assertSame('padrao', $periodos[2]['origem']);
        $this->assertSame('2026-11-30', $periodos[3]['fim']);
    }

    public function testBimestreDaDescricao(): void
    {
        $this->assertSame(3, GeradorAvaliacoesAnualService::bimestreDaDescricao('Provas do 3º bimestre', 1));
        $this->assertSame(4, GeradorAvaliacoesAnualService::bimestreDaDescricao('b4 final', 1));
        $this->assertSame(2, GeradorAvaliacoesAnualService::bimestreDaDescricao('sem pista', 2));
        $this->assertSame(1, GeradorAvaliacoesAnualService::bimestreDaDescricao('1º trimestre', 3));
        $this->assertSame(2, GeradorAvaliacoesAnualService::bimestreDaDescricao('2º semestre', 1));
    }

    public function testRecorteDoAnoLetivoFicaExplicito(): void
    {
        $eventos = [
            ['descricao' => '1º trimestre', 'data_inicio' => '2026-01-10', 'data_fim' => '2026-04-20'],
        ];
        $periodos = GeradorAvaliacoesAnualService::periodosPrevistos(2026, $eventos, '2026-02-01', '2026-11-30');
        $this->assertNotEmpty($periodos);
        $this->assertTrue(!empty($periodos[0]['recortado']));
        $this->assertSame('2026-02-01', $periodos[0]['inicio']);
        $this->assertSame('2026-01-10', $periodos[0]['inicio_original']);
        $avisos = GeradorAvaliacoesAnualService::avisosDeDatas($periodos, true, '2026-02-01', '2026-11-30', count($periodos));
        $this->assertNotEmpty($avisos);
    }
}
