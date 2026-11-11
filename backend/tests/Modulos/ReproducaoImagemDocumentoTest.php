<?php

use App\Modulos\ModelosDocumentos\Services\ReprodutorLayoutDocumentoService;
use PHPUnit\Framework\TestCase;

final class ReproducaoImagemDocumentoTest extends TestCase
{
    public function testPreservaGradeDaImagemEmVezDeSubstituirPeloCatalogo(): void
    {
        $html = '<table style="width:180mm;table-layout:fixed"><colgroup><col style="width:35%"><col style="width:65%"></colgroup><tbody><tr><th colspan="2">HISTÓRICO ESCOLAR PERSONALIZADO</th></tr><tr><td rowspan="2" class="edoc-vert">Base Nacional Comum</td><td>Disciplina da imagem</td></tr><tr><td></td></tr></tbody></table>';
        $estrutura = ReprodutorLayoutDocumentoService::estruturaDaResposta(json_encode([
            'orientacao' => 'retrato', 'layout_chave' => 'historico_sao_paulo', 'page' => ['margin' => ['left' => 15, 'right' => 15]], 'html' => $html,
        ]));
        $out = $estrutura['body']['sections'][0]['columns'][0]['elements'][0]['props']['html'];
        foreach (['HISTÓRICO ESCOLAR PERSONALIZADO', 'Disciplina da imagem', 'rowspan="2"', 'colspan="2"', 'width:35%', 'edoc-vert', 'width:180mm'] as $trecho) {
            $this->assertStringContainsString($trecho, $out);
        }
        $this->assertSame('portrait', $estrutura['page']['orientation']);
        $this->assertSame(15, $estrutura['page']['margin']['left']);
    }

    public function testDeclaracaoSemTabelaPreservaTextoEEliminaConteudoAtivo(): void
    {
        $estrutura = ReprodutorLayoutDocumentoService::estruturaDaResposta("```html\n<h1>Declaração</h1><p onclick=alert(1) style=\"background:url(https://externo.test/img)\">Texto da imagem</p><p style=\"background-image:image-set('https://externo.test/pixel' 1x)\">Outro texto</p><script>alert(1)</script>\n```");
        $html = $estrutura['body']['sections'][0]['columns'][0]['elements'][0]['props']['html'];
        $this->assertStringContainsString('<h1>Declaração</h1>', $html);
        $this->assertStringContainsString('Texto da imagem', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('externo.test', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function testRespostaEstruturadaTambemLimpaCssRemoto(): void
    {
        $dados = ['body' => ['sections' => [['columns' => [['width' => 100, 'elements' => [
            ['type' => 'html', 'props' => ['html' => '<p style="background-image:image-set(&quot;https://externo.test/pixel&quot; 1x)">Parte 1</p>']],
            ['type' => 'html', 'props' => ['html' => '<p>Parte 2</p>']],
        ]]]]]]];
        $estrutura = ReprodutorLayoutDocumentoService::estruturaDaResposta(json_encode($dados));
        $elementos = $estrutura['body']['sections'][0]['columns'][0]['elements'];
        $this->assertCount(2, $elementos);
        $this->assertSame('<p>Parte 1</p>', $elementos[0]['props']['html']);
    }

    public function testRespostaSomenteComCodigoDeCatalogoNaoTrocaLayout(): void
    {
        $this->expectException(RuntimeException::class);
        ReprodutorLayoutDocumentoService::estruturaDaResposta('{"layout_chave":"historico_sao_paulo","campos":{"escola_nome":"Escola"}}');
    }
}
