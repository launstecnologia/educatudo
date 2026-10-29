<?php

require_once dirname(__DIR__) . '/app/Modulos/eventos-avaliativos/Services/FormulaMediaFinal.php';

use App\Modulos\EventosAvaliativos\Services\FormulaMediaFinal;
use PHPUnit\Framework\TestCase;

class FormulaMediaFinalTest extends TestCase
{
    public function testTresSeteEMeioNaFormulaResultamEmSeteEMeio(): void
    {
        $media = FormulaMediaFinal::calcular('(m1 + m2 + m3) / 3', [1 => 7.5, 2 => 7.5, 3 => 7.5]);

        $this->assertSame('7,5', FormulaMediaFinal::exibir($media));
    }

    public function testOitoSeisOitoResultamEmSeteVirgulaTres(): void
    {
        $media = FormulaMediaFinal::calcular('(m1 + m2 + m3) / 3', [1 => 8.0, 2 => 6.0, 3 => 8.0]);

        $this->assertSame('7,3', FormulaMediaFinal::exibir($media));
        $this->assertTrue($media >= 7.0);
    }

    public function testPeriodoSemNotaDeixaAFinalEmBranco(): void
    {
        $this->assertNull(FormulaMediaFinal::calcular('(m1 + m2 + m3) / 3', [1 => 8.0, 2 => null, 3 => 8.0]));
        $this->assertSame('—', FormulaMediaFinal::exibir(null));
    }

    public function testTokenMaiorNaoESubstituidoPorParteDoMenor(): void
    {
        $media = FormulaMediaFinal::calcular('m10', [1 => 1.0, 10 => 9.0]);

        $this->assertSame(9.0, $media);
    }
}
