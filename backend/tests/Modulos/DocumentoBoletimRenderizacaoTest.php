<?php

use App\Modulos\ModelosDocumentos\Services\DocumentoRenderer;
use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;
use PHPUnit\Framework\TestCase;

final class DocumentoBoletimRenderizacaoTest extends TestCase
{
    public function testBoletimPreservaFontesPequenasEFracionariasSemHtmlPersonalizado(): void
    {
        foreach ([5.5, 7.5, 8.25, 10] as $tamanho) {
            $estrutura = ModeloDocumentoService::estruturaVazia('a4', 'paisagem', 8);
            $secao = ModeloDocumentoService::secaoPadrao([100]);
            $secao['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura(
                'boletim',
                [],
                ['fontSize' => $tamanho]
            );
            $estrutura['body']['sections'] = [$secao];
            $partes = (new DocumentoRenderer())->renderizarPartes($estrutura, []);

            $this->assertStringContainsString('font-size:' . $tamanho . 'pt;', $partes['corpo']);
            $this->assertStringContainsString('{{quadro_notas_html}}', $partes['corpo']);
        }
    }
}
