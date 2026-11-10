<?php

use App\Modulos\ModelosDocumentos\Services\CamposAcademicosDocumentoService;
use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;
use PHPUnit\Framework\TestCase;

final class CamposDocumentoEditorTest extends TestCase
{
    private function quadro(): string
    {
        return '<table><tbody><tr><td>Matemática</td><td data-boletim-coluna="b1_pp">4,0</td><td data-boletim-coluna="b1_mu">5,0</td><td data-boletim-coluna="b1_fal">0</td><td data-boletim-coluna="ma">5,5</td><td data-boletim-coluna="mf">6,7</td></tr></tbody></table>';
    }

    public function testMediaFinalEhDoBoletimSemRecalculoEFaltaZeroEhPreservada(): void
    {
        $out = CamposAcademicosDocumentoService::doQuadro($this->quadro());
        $this->assertSame('6,7', $out['vars']['media_final_matematica']);
        $this->assertSame('5,5', $out['vars']['media_anual_matematica']);
        $this->assertSame('5,0', $out['vars']['nota_matematica_1']);
        $this->assertSame('0', $out['vars']['falta_matematica_1']);
        $this->assertSame('4,0', $out['vars']['evento_matematica_b1_pp']);
        $this->assertCount(5, $out['componentes'][0]['eventos']);
    }

    public function testPdfUsaMediaOficialMesmoRecebendoMediaGenerica(): void
    {
        $svc = (new ReflectionClass(ModeloDocumentoService::class))->newInstanceWithoutConstructor();
        $html = $svc->aplicarPlaceholders('{{media_final_matematica}} / {{evento_matematica_b1_pp}}', ['quadro_notas_html' => $this->quadro(), 'media_final_matematica' => '9,9']);
        $this->assertSame('6,7 / 4,0', $html);
    }

    public function testQuadroSemMediaFinalNaoInventaMediaDosBimestres(): void
    {
        $html = str_replace('<td data-boletim-coluna="mf">6,7</td>', '', $this->quadro());
        $this->assertSame('—', CamposAcademicosDocumentoService::doQuadro($html)['vars']['media_final_matematica']);
    }

    public function testPresetsDeAutorizacaoSaoCamposValidosEFormatadosNaEmissao(): void
    {
        $campos = ModeloDocumentoService::camposAutorizacao();
        $this->assertSame($campos, ModeloDocumentoService::camposVariaveisValidos($campos));
        $campo = array_values(array_filter($campos, fn($c) => $c['chave'] === 'cv_data_evento'));
        $svc = (new ReflectionClass(ModeloDocumentoService::class))->newInstanceWithoutConstructor();
        $vars = $svc->mesclarCamposVariaveis([], $campo, ['cv_data_evento' => '2026-11-13']);
        $this->assertSame('13/11/2026', $vars['cv_data_evento']);
        $this->expectException(InvalidArgumentException::class);
        $svc->mesclarCamposVariaveis([], $campo, []);
    }

    public function testResponsaveisIndividuaisNaoReaproveitamDadosDeOutroAluno(): void
    {
        $vars = ModeloDocumentoService::varsResponsaveis([['nome' => 'Mãe & Filha', 'cpf' => '123', 'is_financeiro' => 1], ['nome' => 'Pai', 'rg' => 'RG2']]);
        $this->assertSame('Mãe &amp; Filha', $vars['resp_nome']);
        $this->assertSame('RG2', $vars['resp2_rg']);
        $this->assertSame('123', $vars['resp_fin_cpf']);
        $outro = ModeloDocumentoService::varsResponsaveis([]);
        $this->assertSame('', $outro['se_resp2']);
        $this->assertSame('—', $outro['resp2_nome']);
    }

    public function testAutorizacaoLegadaTambemPedeValorNaEmissao(): void
    {
        $svc = (new ReflectionClass(ModeloDocumentoService::class))->newInstanceWithoutConstructor();
        $estrutura = ModeloDocumentoService::estruturaVazia();
        $estrutura['body']['sections'][0]['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura('texto', ['html' => '<p>{{aut_local}}</p>']);
        $campos = $svc->camposDoModelo(['codigo' => 'modelo_personalizado', 'estrutura_json' => json_encode($estrutura)]);
        $this->assertSame(['cv_aut_local'], array_column($campos, 'chave'));
        $vars = $svc->mesclarCamposVariaveis([], $campos, ['cv_aut_local' => 'Museu & Escola']);
        $this->assertSame('Museu &amp; Escola', $svc->aplicarPlaceholders('{{aut_local}}', $vars));
    }

    public function testCatalogoDaSecretariaExibeCampoDoHtmlLegado(): void
    {
        $db = $this->getMockBuilder(\App\Core\Database::class)->disableOriginalConstructor()->onlyMethods(['fetchAll'])->getMock();
        $db->expects($this->once())->method('fetchAll')->with($this->callback(fn($sql) => str_contains($sql, 'cabecalho_html, corpo_html, rodape_html')))->willReturn([['id' => 24, 'codigo' => 'modelo_personalizado', 'nome' => 'Autorização', 'estrutura_json' => '', 'corpo_html' => '<p>{{aut_local}}</p>']]);
        $modelos = $this->getMockBuilder(ModeloDocumentoService::class)->disableOriginalConstructor()->onlyMethods(['schemaReady'])->getMock();
        $modelos->method('schemaReady')->willReturn(true);
        $ref = new ReflectionClass(\App\Modulos\ModelosDocumentos\Services\ArquivoEmissaoDocumentoService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('db')->setValue($svc, $db);
        $ref->getProperty('modelos')->setValue($svc, $modelos);
        $this->assertSame('cv_aut_local', $svc->catalogo()[0]['campos'][0]['chave']);
    }

    public function testCadastroDeCursoESerieUsaTurmaDaMatricula(): void
    {
        $db = $this->getMockBuilder(\App\Core\Database::class)->disableOriginalConstructor()->onlyMethods(['fetch'])->getMock();
        $db->method('fetch')->willReturnCallback(function ($sql, $params) {
            if (str_contains($sql, 'FROM turmas')) { $this->assertSame([136], $params); return ['curso_novo_id' => 3, 'serie_id' => 1, 'turno' => 'Manhã']; }
            if (str_contains($sql, 'FROM curso')) return ['nome' => 'Ensino Médio'];
            return ['nome' => '1ª Série'];
        });
        $svc = new \App\Modulos\ModelosDocumentos\Services\DeclarationService($db);
        $metodo = new ReflectionMethod($svc, 'completarDadosTurma');
        $matricula = $metodo->invoke($svc, ['id' => 8, 'turma_id' => 136]);
        $this->assertSame('Ensino Médio', $matricula['curso_nome']);
        $this->assertSame('1ª Série', $matricula['turma_serie']);
        $this->assertSame('Manhã', $matricula['turno']);
    }

    public function testFotoDoTenantViraImagemEmbutidaNoPdf(): void
    {
        $dir = sys_get_temp_dir() . '/foto_documento_' . bin2hex(random_bytes(4));
        $pasta = $dir . '/files/escola_a/avatars';
        mkdir($pasta, 0700, true);
        $bin = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII=');
        file_put_contents($pasta . '/foto.png', $bin);
        try {
            $svc = (new ReflectionClass(ModeloDocumentoService::class))->newInstanceWithoutConstructor();
            $html = $svc->fotoAlunoHtml(['foto_url' => '/media/serve?type=avatars&key=foto.png&tenant=escola_a'], ['tenant' => ['slug' => 'escola_a'], 'media' => ['storage' => 'local', 'local_base' => $dir, 'files_base' => 'files']]);
            $this->assertStringContainsString('src="data:image/png;base64,', $html);
            $this->assertStringContainsString('alt="Foto do aluno"', $html);
        } finally {
            unlink($pasta . '/foto.png');
            rmdir($pasta);
            rmdir(dirname($pasta));
            rmdir($dir . '/files');
            rmdir($dir);
        }
    }

    public function testFotoDeOutroTenantOuUrlExternaNaoEhCarregada(): void
    {
        $svc = (new ReflectionClass(ModeloDocumentoService::class))->newInstanceWithoutConstructor();
        $config = ['tenant' => ['slug' => 'escola_a']];
        $this->assertSame('', $svc->fotoAlunoHtml(['foto_url' => '/media/serve?type=avatars&key=foto.jpg&tenant=escola_b'], $config));
        $this->assertSame('', $svc->fotoAlunoHtml(['foto_url' => 'https://exemplo.com/foto.jpg'], $config));
        $this->assertSame('', $svc->fotoAlunoHtml(['foto_url' => '/media/serve?type=avatars&key=..%2Ffoto.jpg'], $config));
        $this->assertSame('', $svc->fotoAlunoHtml([], $config));
    }
}
