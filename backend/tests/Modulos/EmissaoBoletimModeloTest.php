<?php

use App\Core\Database;
use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;
use App\Modulos\VidaEscolar\Services\VidaEscolarPdfService;
use PHPUnit\Framework\TestCase;

final class EmissaoBoletimModeloTest extends TestCase
{
    private function servico($db, $modelos): VidaEscolarPdfService
    {
        $ref = new ReflectionClass(VidaEscolarPdfService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('db')->setValue($svc, $db);
        $ref->getProperty('modelos')->setValue($svc, $modelos);
        return $svc;
    }

    public function testModeloEscolhidoPreservaQuadroOficialERecebeAnoDaFicha(): void
    {
        $db = $this->getMockBuilder(Database::class)->disableOriginalConstructor()->onlyMethods(['fetch'])->getMock();
        $db->method('fetch')->willReturn(['id' => 136, 'curso_novo_id' => 3, 'serie_id' => 1]);
        $modelos = $this->getMockBuilder(ModeloDocumentoService::class)->disableOriginalConstructor()->onlyMethods(['findById', 'varsFromDeclaracao', 'aplicarFonteBoletimDoModelo', 'renderHtml'])->getMock();
        $modelo = ['id' => 21, 'ativo' => 1, 'codigo' => 'meu_boletim', 'estrutura_json' => '{}'];
        $modelos->expects($this->once())->method('findById')->with(21)->willReturn($modelo);
        $modelos->method('varsFromDeclaracao')->willReturn([]);
        $modelos->expects($this->once())->method('aplicarFonteBoletimDoModelo')->with($modelo, $this->callback(fn($vars) => str_contains($vars['quadro_notas_html'], 'QUADRO OFICIAL')), 8, 136, 2024)->willReturnCallback(fn($modelo, $vars) => $vars);
        $modelos->expects($this->once())->method('renderHtml')->with($modelo, $this->anything(), $this->anything(), null)->willReturn('<html>MODELO ESCOLHIDO</html>');
        $out = $this->servico($db, $modelos)->htmlBoletim(['aluno' => ['id' => 8], 'quadro' => ['ficha' => ['ano_letivo' => 2024, 'turma_id' => 136]], 'quadro_impressao_html' => '<table><tr><td>QUADRO OFICIAL</td></tr></table>', 'boletim_observacao' => 'Observação'], [], null, 21);
        $this->assertSame($modelo, $out['modelo']);
        $this->assertSame('<html>MODELO ESCOLHIDO</html>', $out['html']);
    }

    public function testAutomaticoResolveCursoESerieDaTurmaHistorica(): void
    {
        $db = $this->getMockBuilder(Database::class)->disableOriginalConstructor()->onlyMethods(['fetch'])->getMock();
        $db->expects($this->once())->method('fetch')->with($this->stringContains('FROM turmas'), ['id' => 136])->willReturn(['id' => 136, 'curso_novo_id' => 3, 'serie_id' => 1]);
        $modelos = $this->getMockBuilder(ModeloDocumentoService::class)->disableOriginalConstructor()->onlyMethods(['codigoParaEmissao', 'findByCodigo'])->getMock();
        $modelos->expects($this->once())->method('codigoParaEmissao')->with('boletim', 3, 1)->willReturn('boletim_medio');
        $modelos->method('findByCodigo')->with('boletim_medio')->willReturn(['id' => 21]);
        $svc = $this->servico($db, $modelos);
        $metodo = new ReflectionMethod($svc, 'resolverModeloBoletim');
        $this->assertSame(['id' => 21], $metodo->invoke($svc, ['aluno' => ['turma_id' => 999], 'quadro' => ['ficha' => ['turma_id' => 136]]], 0));
    }

    public function testModeloInativoNaoCaiSilenciosamenteNoPadrao(): void
    {
        $db = $this->getMockBuilder(Database::class)->disableOriginalConstructor()->getMock();
        $modelos = $this->getMockBuilder(ModeloDocumentoService::class)->disableOriginalConstructor()->onlyMethods(['findById'])->getMock();
        $modelos->method('findById')->willReturn(['id' => 21, 'ativo' => 0, 'codigo' => 'boletim']);
        $this->expectException(RuntimeException::class);
        $this->servico($db, $modelos)->htmlBoletim([], [], null, 21);
    }

    public function testFonteDeOutroAnoEhRecusadaAntesDeBuscarNotas(): void
    {
        $db = $this->getMockBuilder(Database::class)->disableOriginalConstructor()->onlyMethods(['fetch'])->getMock();
        $db->method('fetch')->willReturnCallback(function ($sql) {
            if (str_contains($sql, 'information_schema')) return ['ok' => 1];
            if (str_contains($sql, 'SELECT ano_letivo FROM boletim_layouts')) return ['ano_letivo' => 2026];
            $this->fail('Consulta de notas não deveria ocorrer: ' . $sql);
        });
        $svc = new ModeloDocumentoService($db);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('a ficha emitida é de 2024');
        $svc->aplicarFonteBoletimDoModelo(['estrutura_json' => json_encode(['body' => ['sections' => []], 'fonte_boletim_id' => 12])], [], 8, 136, 2024);
    }
}
