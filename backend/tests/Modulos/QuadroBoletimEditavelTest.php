<?php

use App\Modulos\ModelosDocumentos\Services\DocumentoRenderer;
use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;
use App\Modulos\ModelosDocumentos\Services\QuadroBoletimEditavelService;
use PHPUnit\Framework\TestCase;

final class QuadroBoletimEditavelTest extends TestCase
{
    private function quadro(string $nota, bool $invertido = false): string
    {
        $matematica = '<tr data-boletim-materia="17"><td>Matemática</td><td data-boletim-coluna="b1_pp"><span class="boletim-cell-valor">' . $nota . '</span></td><td data-boletim-coluna="b1_fal">2</td></tr>';
        $portugues = '<tr data-boletim-materia="20"><td>Língua Portuguesa</td><td data-boletim-coluna="b1_pp">9,0</td><td data-boletim-coluna="b1_fal">0</td></tr>';
        return '<style>.boletim-quadro-tabela{color:#123456}</style><table class="boletim-quadro-tabela"><thead><tr><th rowspan="2">Matéria</th><th colspan="2">I Bimestre</th></tr><tr><th>PP</th><th>FAL</th></tr></thead><tbody>' . ($invertido ? $portugues . $matematica : $matematica . $portugues) . '</tbody></table>';
    }

    public function testTabelaCompletaNaoFixaNotasDoAlunoUsadoParaExpor(): void
    {
        $exposto = QuadroBoletimEditavelService::expor($this->quadro('7,5'));
        $this->assertSame(4, $exposto['celulas']);
        $this->assertStringContainsString('<thead>', $exposto['html']);
        $this->assertStringContainsString('<tbody>', $exposto['html']);
        $this->assertStringContainsString('{{boletim_celula_t1_m17_b1_pp}}', $exposto['html']);
        $this->assertStringNotContainsString('>7,5<', $exposto['html']);
        $this->assertSame('7,5', $exposto['variaveis']['boletim_celula_t1_m17_b1_pp']);
        $this->assertSame('0', $exposto['variaveis']['boletim_celula_t1_m20_b1_fal']);
    }

    public function testMesmoModeloRecebeNotasDeOutroAlunoMesmoReordenandoMaterias(): void
    {
        $modelo = QuadroBoletimEditavelService::expor($this->quadro('7,5'))['html'];
        $modelo = str_replace('Matemática', 'Matemática personalizada pelo TI', $modelo);
        $svc = (new ReflectionClass(ModeloDocumentoService::class))->newInstanceWithoutConstructor();
        $html = $svc->aplicarPlaceholders($modelo, ['quadro_notas_html' => $this->quadro('4,0', true)]);
        $this->assertStringContainsString('Matemática personalizada pelo TI', $html);
        $this->assertStringContainsString('>4,0</span>', $html);
        $this->assertStringNotContainsString('7,5', $html);
        $this->assertStringNotContainsString('{{boletim_celula_', $html);
    }

    public function testCampoAusenteNaoReutilizaNotaAntigaNemMostraTokenNoPdf(): void
    {
        $svc = (new ReflectionClass(ModeloDocumentoService::class))->newInstanceWithoutConstructor();
        $html = $svc->aplicarPlaceholders('<td>{{ boletim_celula_t1_m99_b1_pp }}</td>', ['quadro_notas_html' => $this->quadro('3,0')]);
        $this->assertSame('<td>—</td>', $html);
    }

    public function testCabecalhosAgrupadosFuncionamSemMetadadosLegados(): void
    {
        $html = '<table><thead><tr><th rowspan="2">Matéria</th><th colspan="2">I Bimestre</th><th colspan="2">II Bimestre</th></tr><tr><th>PP</th><th>PU</th><th>PP</th><th>PU</th></tr></thead><tbody><tr><td>Arte</td><td>1</td><td>2</td><td>3</td><td>4</td></tr></tbody></table>';
        $mapa = QuadroBoletimEditavelService::variaveis($html);
        $this->assertSame('1', $mapa['boletim_celula_t1_arte_i_bimestre_pp']);
        $this->assertSame('3', $mapa['boletim_celula_t1_arte_ii_bimestre_pp']);
    }

    public function testTextoDeNotaEhEscapadoENaoViraHtmlExecutavel(): void
    {
        $mapa = QuadroBoletimEditavelService::variaveis($this->quadro('&lt;img src=x onerror=alert(1)&gt;'));
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $mapa['boletim_celula_t1_m17_b1_pp']);
    }

    public function testRendererPreservaCssDoTiERemoveScript(): void
    {
        $estrutura = ModeloDocumentoService::estruturaVazia('a4', 'paisagem', 8);
        $secao = ModeloDocumentoService::secaoPadrao([100]);
        $secao['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura('boletim', ['html' => '<style onload="alert(1)">.minha-tabela{color:red}</style><script>alert(1)</script><table class="minha-tabela"><tr><td>{{boletim_celula_t1_m17_b1_pp}}</td></tr></table>']);
        $estrutura['body']['sections'] = [$secao];
        $partes = (new DocumentoRenderer())->renderizarPartes($estrutura, []);
        $this->assertStringContainsString('<style type="text/css">.minha-tabela{color:red}</style>', $partes['corpo']);
        $this->assertStringNotContainsString('<script', $partes['corpo']);
        $this->assertStringNotContainsString('onload', $partes['corpo']);
        $this->assertStringContainsString('{{boletim_celula_t1_m17_b1_pp}}', $partes['corpo']);
    }
}
