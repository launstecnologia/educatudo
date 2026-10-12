<?php
/**
 * Testes da máquina de estados do fechamento (sem banco).
 */

require_once dirname(__DIR__) . '/app/Modulos/fechamento/Services/FechamentoMaquinaEstados.php';

use PHPUnit\Framework\TestCase;

class FechamentoMaquinaEstadosTest extends TestCase
{
    public function testHomologadoNaoVoltaParaAberto(): void
    {
        $this->assertFalse(
            FechamentoMaquinaEstados::podeTransitar(
                FechamentoMaquinaEstados::HOMOLOGADO,
                FechamentoMaquinaEstados::ABERTO
            )
        );
        $this->assertFalse(
            FechamentoMaquinaEstados::podeTransitar(
                FechamentoMaquinaEstados::HOMOLOGADO,
                FechamentoMaquinaEstados::EM_FECHAMENTO
            )
        );
    }

    public function testHomologadoSoVaiParaRetificado(): void
    {
        $this->assertTrue(
            FechamentoMaquinaEstados::podeTransitar(
                FechamentoMaquinaEstados::HOMOLOGADO,
                FechamentoMaquinaEstados::RETIFICADO
            )
        );
        $this->assertTrue(
            FechamentoMaquinaEstados::exigeJustificativa(
                FechamentoMaquinaEstados::HOMOLOGADO,
                FechamentoMaquinaEstados::RETIFICADO
            )
        );
    }

    public function testHomologadoEstaTravado(): void
    {
        $this->assertTrue(FechamentoMaquinaEstados::estaTravado(FechamentoMaquinaEstados::HOMOLOGADO));
        $this->assertFalse(FechamentoMaquinaEstados::estaTravado(FechamentoMaquinaEstados::RETIFICADO));
        $this->assertFalse(FechamentoMaquinaEstados::estaTravado(FechamentoMaquinaEstados::ABERTO));
    }

    public function testPeriodoRef(): void
    {
        $this->assertSame('2026-ANO', FechamentoMaquinaEstados::periodoRef(2026, 'ano', 0));
        $this->assertSame('2026-B2', FechamentoMaquinaEstados::periodoRef(2026, 'bimestre', 2));
    }

    public function testMensagemBloqueiaReabertura(): void
    {
        $msg = FechamentoMaquinaEstados::mensagemTransicaoInvalida(
            FechamentoMaquinaEstados::HOMOLOGADO,
            FechamentoMaquinaEstados::ABERTO
        );
        $this->assertStringContainsString('retificação', $msg);
    }
}
