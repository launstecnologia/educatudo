<?php
/**
 * Gates de Avaliação / geração oficial (sem banco).
 */

require_once dirname(__DIR__) . '/app/Modulos/fechamento/Services/FechamentoGates.php';

use PHPUnit\Framework\TestCase;

class FechamentoGatesTest extends TestCase
{
    public function testSalvarAvaliacaoSemModeloBloqueia(): void
    {
        $erros = FechamentoGates::errosSalvarAvaliacao(0, 12);
        $this->assertNotEmpty($erros);
        $this->assertStringContainsString('Modelo de Boletim', $erros[0]);
    }

    public function testSalvarAvaliacaoSemQuadroBloqueia(): void
    {
        $erros = FechamentoGates::errosSalvarAvaliacao(7, 0);
        $this->assertNotEmpty($erros);
        $this->assertStringContainsString('Quadro de Notas', $erros[0]);
    }

    public function testSalvarAvaliacaoComModeloEQuadroPassa(): void
    {
        $this->assertSame([], FechamentoGates::errosSalvarAvaliacao(7, 12));
    }

    public function testGerarBoletimSemRegraDeAprovacaoBloqueia(): void
    {
        $diag = FechamentoGates::diagnosticarGeracao(7, 12, 0, true, true);
        $this->assertFalse($diag['ok']);
        $this->assertNotEmpty($diag['bloqueios']);
        $this->assertStringContainsString('Regra de Aprovação', $diag['bloqueios'][0]);
    }

    public function testGerarBoletimSemModeloBloqueia(): void
    {
        $diag = FechamentoGates::diagnosticarGeracao(0, 12, 3, true, true);
        $this->assertFalse($diag['ok']);
        $this->assertStringContainsString('Modelo de Boletim', $diag['bloqueios'][0]);
    }

    public function testGerarBoletimSemQuadroBloqueia(): void
    {
        $diag = FechamentoGates::diagnosticarGeracao(7, 0, 3, true, true);
        $this->assertFalse($diag['ok']);
        $this->assertTrue(
            (bool) array_filter(
                $diag['bloqueios'],
                static fn (string $msg): bool => str_contains($msg, 'Quadro de Notas')
            )
        );
    }

    public function testGerarBoletimCompletoPassa(): void
    {
        $diag = FechamentoGates::diagnosticarGeracao(7, 12, 3, true, true);
        $this->assertTrue($diag['ok']);
        $this->assertSame([], $diag['bloqueios']);
    }

    public function testHomologacaoNaoAceitaRecuperacaoNemExame(): void
    {
        $this->assertFalse(FechamentoGates::situacaoPermiteHomologar('recuperacao'));
        $this->assertFalse(FechamentoGates::situacaoPermiteHomologar('exame_final'));
        $this->assertFalse(FechamentoGates::situacaoPermiteHomologar('resultado_pendente'));
        $this->assertFalse(FechamentoGates::situacaoPermiteHomologar(''));
    }

    public function testHomologacaoAceitaResultadoDefinitivo(): void
    {
        $this->assertTrue(FechamentoGates::situacaoPermiteHomologar('aprovado'));
        $this->assertTrue(FechamentoGates::situacaoPermiteHomologar('aprovado_recuperacao'));
        $this->assertTrue(FechamentoGates::situacaoPermiteHomologar('reprovado_rendimento'));
        $this->assertTrue(FechamentoGates::situacaoPermiteHomologar('reprovado_frequencia'));
        $this->assertTrue(FechamentoGates::situacaoPermiteHomologar('transferido'));
    }

    public function testMensagemAlunosEmRecuperacao(): void
    {
        $this->assertStringContainsString('aprovado ou reprovado', FechamentoGates::mensagemAlunosEmRecuperacao(1));
        $this->assertStringContainsString('3 alunos', FechamentoGates::mensagemAlunosEmRecuperacao(3));
    }
}
