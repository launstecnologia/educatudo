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

    public function testDompdfPosicionaRodapeNaMargemInferiorDaFolha(): void
    {
        $modelos = new class extends ModeloDocumentoService {
            public function __construct() {}
            public function aplicarLayoutPadraoNoModelo(array $modelo): array { return $modelo; }
        };
        $estrutura = ModeloDocumentoService::estruturaVazia('a5', 'paisagem');
        $estrutura['page']['margin']['bottom'] = 18;
        $estrutura['page']['rodapeNoFim'] = true;
        foreach (['body' => 'Declaração', 'footer' => 'Contato da escola'] as $area => $texto) {
            $secao = ModeloDocumentoService::secaoPadrao([100], $area);
            $secao['columns'][0]['elements'][] = ['type' => 'texto', 'props' => ['text' => $texto]];
            $estrutura[$area]['sections'] = [$secao];
        }
        $modelo = ['codigo' => 'teste_rodape', 'estrutura_json' => json_encode($estrutura)];
        $html = $modelos->renderHtml($modelo, [
            'logo_html' => '<span></span>',
            'assinante_nome' => 'Direção',
            'assinante_cargo' => 'Direção',
        ]);
        $posicoes = [];
        $pdf = new \Dompdf\Dompdf();
        $pdf->loadHtml($html);
        $pdf->setCallbacks([[
            'event' => 'begin_frame',
            'f' => static function ($quadro, $canvas) use (&$posicoes): void {
                $no = $quadro->get_node();
                if ($no instanceof \DOMElement && $no->hasAttribute('data-doc-rodape-fim')) {
                    $posicoes[] = $canvas->get_height() - $quadro->get_position('y') - $quadro->get_margin_height();
                }
            },
        ]]);
        $modelos->aplicarPapelDompdf($pdf, $modelo);
        $pdf->render();

        $this->assertSame(1, $pdf->getCanvas()->get_page_count());
        $this->assertCount(1, $posicoes);
        $this->assertEqualsWithDelta(18 * 72 / 25.4, $posicoes[0], 0.1);
    }
}
