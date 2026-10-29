<?php

require_once dirname(__DIR__) . '/app/Core/PeriodoLetivo.php';

use App\Core\PeriodoLetivo;
use PHPUnit\Framework\TestCase;

class PeriodoLetivoRotuloTest extends TestCase
{
    public function testTrimestreUsaTresPeriodosComEsseNome(): void
    {
        $this->assertSame('Trimestre', PeriodoLetivo::rotuloCampo('trimestre'));
        $rotulos = PeriodoLetivo::rotulosDoTipo('trimestre');

        $this->assertCount(3, $rotulos);
        $this->assertSame('1º Trimestre', $rotulos[1]);
        $this->assertSame('3º Trimestre', $rotulos[3]);
        $this->assertArrayNotHasKey(4, $rotulos);
    }

    public function testBimestreContinuaComQuatroPeriodos(): void
    {
        $this->assertSame('Bimestre', PeriodoLetivo::rotuloCampo('bimestre'));
        $this->assertCount(4, PeriodoLetivo::rotulosDoTipo('bimestre'));
    }
}
