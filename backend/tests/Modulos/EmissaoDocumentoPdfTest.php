<?php

use App\Modulos\ModelosDocumentos\Services\EmissaoCampoVariavelService;
use App\Modulos\ModelosDocumentos\Services\GeradorPdfFolhaService;
use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;
use PHPUnit\Framework\TestCase;

final class EmissaoDocumentoPdfTest extends TestCase
{
    private function servico($motor, $modelos): EmissaoCampoVariavelService
    {
        $ref = new ReflectionClass(EmissaoCampoVariavelService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('geradorFolha')->setValue($svc, $motor);
        $ref->getProperty('modelos')->setValue($svc, $modelos);
        return $svc;
    }

    public function testEmissaoEntregaMesmoBinarioDoMotorDaPreviaSemReformatarHtml(): void
    {
        $motor = $this->createMock(GeradorPdfFolhaService::class);
        $html = '<html><head><style>@page{margin:15mm 20mm}</style></head><body><table class="doc-linha"><tr><td>Declaração</td></tr></table></body></html>';
        $motor->expects($this->once())->method('gerarSeFolhaOficial')->with($html)->willReturn('%PDF-TESTE-PREVIA');
        $modelos = $this->getMockBuilder(ModeloDocumentoService::class)->disableOriginalConstructor()->onlyMethods(['aplicarPapelDompdf'])->getMock();
        $modelos->expects($this->never())->method('aplicarPapelDompdf');
        $this->assertSame('%PDF-TESTE-PREVIA', $this->servico($motor, $modelos)->binarioPdf($html, ['id' => 9]));
    }

    public function testSemChromeMantemFallbackEPapelDoModelo(): void
    {
        $motor = $this->createMock(GeradorPdfFolhaService::class);
        $motor->method('gerarSeFolhaOficial')->willReturn(null);
        $modelo = ['id' => 9, 'formato_papel' => 'a5', 'orientacao' => 'paisagem'];
        $modelos = $this->getMockBuilder(ModeloDocumentoService::class)->disableOriginalConstructor()->onlyMethods(['aplicarPapelDompdf'])->getMock();
        $modelos->expects($this->once())->method('aplicarPapelDompdf')->with($this->isInstanceOf(\Dompdf\Dompdf::class), $modelo)->willReturnCallback(fn($pdf) => $pdf->setPaper('A5', 'landscape'));
        $binario = $this->servico($motor, $modelos)->binarioPdf('<html><body><p>Declaração preenchida</p></body></html>', $modelo);
        $this->assertStringStartsWith('%PDF-', $binario);
        $this->assertStringContainsString('/MediaBox [0.000 0.000 595.280 419.530]', $binario);
    }

    public function testLotePreservaCssDeMargensEClassesDosDocumentos(): void
    {
        $svc = (new ReflectionClass(EmissaoCampoVariavelService::class))->newInstanceWithoutConstructor();
        $juntar = new ReflectionMethod($svc, 'juntarHtml');
        $html = '<html><head><style>@page{margin:15mm 20mm}body.folha-impressa p{line-height:1.5}</style></head><body class="folha-impressa"><p>Aluno 1</p></body></html>';
        $out = $juntar->invoke($svc, [$html, str_replace('Aluno 1', 'Aluno 2', $html)]);
        $this->assertStringContainsString('@page{margin:15mm 20mm}', $out);
        $this->assertStringContainsString('.folha-impressa p{line-height:1.5}', $out);
        $this->assertSame(2, substr_count($out, 'class="folha-lote folha-impressa"'));
        $this->assertStringContainsString('break-after:page', $out);
        $this->assertSame($html, $juntar->invoke($svc, [$html]));
    }
}
