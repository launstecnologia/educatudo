<?php

require_once dirname(__DIR__) . '/app/Modulos/regras-academicas/Services/EscalaNota.php';

use App\Modulos\RegrasAcademicas\Services\EscalaNota;
use PHPUnit\Framework\TestCase;

class EscalaNotaTest extends TestCase
{
    public function testEscalaDezRejeitaForaDoIntervalo(): void
    {
        $menosUm = EscalaNota::ler('-1');
        $onze = EscalaNota::ler('11');
        $this->assertTrue($menosUm['ok']);
        $this->assertTrue($onze['ok']);
        $this->assertSame('A nota precisa estar entre 0 e 10.', EscalaNota::rejeitar($menosUm['nota'], '10'));
        $this->assertSame('A nota precisa estar entre 0 e 10.', EscalaNota::rejeitar($onze['nota'], '10'));
    }

    public function testEscalaDezAceitaExtremosVirgulaECampoVazio(): void
    {
        $zero = EscalaNota::ler('0');
        $dez = EscalaNota::ler('10');
        $virgula = EscalaNota::ler('8,0');
        $vazio = EscalaNota::ler('');
        $this->assertNull(EscalaNota::rejeitar($zero['nota'], '10'));
        $this->assertNull(EscalaNota::rejeitar($dez['nota'], '10'));
        $this->assertSame(0.0, $zero['nota']);
        $this->assertSame(10.0, $dez['nota']);
        $this->assertSame(8.0, $virgula['nota']);
        $this->assertNull(EscalaNota::rejeitar($virgula['nota'], '10'));
        $this->assertTrue($vazio['ok']);
        $this->assertNull($vazio['nota']);
        $this->assertNull(EscalaNota::rejeitar($vazio['nota'], '10'));
    }

    public function testTextoInvalidoNaoViraNotaVazia(): void
    {
        $lida = EscalaNota::ler('abc');
        $this->assertFalse($lida['ok']);
        $this->assertNull($lida['nota']);
        $this->assertNotSame('', $lida['erro']);
    }

    public function testMinimaSeteReprovaSeisEAprovaOito(): void
    {
        $this->assertFalse(EscalaNota::aprovado(6.0, 7.0));
        $this->assertTrue(EscalaNota::aprovado(8.0, 7.0));
        $this->assertNull(EscalaNota::aprovado(null, 7.0));
    }
}
