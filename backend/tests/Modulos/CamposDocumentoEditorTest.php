<?php

use App\Modulos\ModelosDocumentos\Services\CamposAcademicosDocumentoService;
use App\Modulos\ModelosDocumentos\Services\DemonstracaoDocumentoService;
use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;
use PHPUnit\Framework\TestCase;

final class CamposDocumentoEditorTest extends TestCase
{
    private function quadro(): string
    {
        return '<table><tbody><tr><td>Matemática</td><td data-boletim-coluna="b1_pp">4,0</td><td data-boletim-coluna="b1_mu">5,0</td><td data-boletim-coluna="b1_fal">0</td><td data-boletim-coluna="ma">5,5</td><td data-boletim-coluna="mf">6,7</td></tr></tbody></table>';
    }

    public function testFiliacaoNaturalidadeENacionalidadeFicamNoCadastroNaoNoAcademico(): void
    {
        $grupos = ModeloDocumentoService::gruposPlaceholders();
        $this->assertContains('aluno_filiacao', $grupos['aluno']['chaves']);
        $this->assertContains('aluno_naturalidade', $grupos['aluno']['chaves']);
        $this->assertContains('aluno_nacionalidade', $grupos['aluno']['chaves']);
        $this->assertNotContains('aluno_filiacao', $grupos['academico']['chaves']);
        $this->assertNotContains('aluno_naturalidade', $grupos['academico']['chaves']);
        $this->assertNotContains('aluno_nacionalidade', $grupos['academico']['chaves']);
        $this->assertSame('Demonstrativo de Notas — todos os períodos (HTML)', ModeloDocumentoService::PLACEHOLDERS['quadro_notas_html']);
        $this->assertArrayHasKey('boletim_html', ModeloDocumentoService::PLACEHOLDERS);
        $this->assertArrayHasKey('quadro_notas_b1_html', ModeloDocumentoService::PLACEHOLDERS);
        $this->assertContains('quadro_notas_html', $grupos['academico']['chaves']);
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

    public function testCamposDoQuadroUsamNomeEGrupoDoCabecalho(): void
    {
        $html = '<table><thead><tr>'
            . '<th rowspan="2">Componente</th>'
            . '<th colspan="3">I BIMESTRE</th>'
            . '<th colspan="2">RESULTADO FINAL</th>'
            . '</tr><tr><th>PP</th><th>MU</th><th>FAL</th><th>MA</th><th>MF</th></tr></thead>'
            . '<tbody><tr><td>Arte</td>'
            . '<td data-boletim-coluna="b1_pp">8,0</td>'
            . '<td data-boletim-coluna="b1_mu">7,5</td>'
            . '<td data-boletim-coluna="b1_fal">1</td>'
            . '<td data-boletim-coluna="ma">7,8</td>'
            . '<td data-boletim-coluna="mf">8,1</td>'
            . '</tr></tbody></table>';
        $out = CamposAcademicosDocumentoService::doQuadro($html);
        $eventos = $out['componentes'][0]['eventos'];
        $this->assertSame(['I BIMESTRE', 'RESULTADO FINAL'], $out['grupos']);
        $this->assertSame('PP', $eventos[0]['rotulo']);
        $this->assertSame('I BIMESTRE', $eventos[0]['grupo']);
        $this->assertSame('MU', $eventos[1]['rotulo']);
        $this->assertSame('MA', $eventos[3]['rotulo']);
        $this->assertSame('RESULTADO FINAL', $eventos[3]['grupo']);
        $this->assertSame('MF', $eventos[4]['rotulo']);
        $this->assertSame('8,1', $out['vars']['media_final_arte']);
        $this->assertSame('7,8', $out['vars']['media_anual_arte']);
    }

    public function testCodigoRepetidoEmOutroBimestreNaoSobrescreveEvento(): void
    {
        $html = '<table><thead><tr><th rowspan="2">Comp</th><th colspan="1">I BIMESTRE</th><th colspan="1">II BIMESTRE</th></tr><tr><th>PP</th><th>PP</th></tr></thead>'
            . '<tbody><tr><td>Matemática</td><td data-boletim-coluna="pp">4,0</td><td data-boletim-coluna="pp">9,0</td></tr></tbody></table>';
        $out = CamposAcademicosDocumentoService::doQuadro($html);
        $this->assertSame('4,0', $out['vars']['evento_matematica_pp']);
        $this->assertSame('9,0', $out['vars']['evento_matematica_ii_bimestre_pp']);
        $this->assertSame('PP', $out['componentes'][0]['eventos'][0]['rotulo']);
        $this->assertSame('PP', $out['componentes'][0]['eventos'][1]['rotulo']);
        $this->assertSame('II BIMESTRE', $out['componentes'][0]['eventos'][1]['grupo']);
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

    public function testAutorizacaoSalvaComRotulosImprimeOsValoresDaEmissao(): void
    {
        $svc = new class extends ModeloDocumentoService {
            public function __construct() {}
            public function aplicarLayoutPadraoNoModelo(array $modelo): array { return $modelo; }
        };
        $valores = [
            'cv_aut_finalidade' => 'Visita pedagógica',
            'cv_aut_local' => 'Museu & Escola',
            'cv_data_evento' => '2026-11-13',
            'cv_aut_hora_saida' => '08:45',
            'cv_aut_hora_retorno' => '14:30',
        ];
        $estrutura = ModeloDocumentoService::estruturaVazia();
        $estrutura['campos_variaveis'] = array_values(array_filter(
            ModeloDocumentoService::camposAutorizacao(),
            static fn (array $campo): bool => isset($valores[$campo['chave']])
        ));
        $corpo = '<p><strong>Finalidade do passeio:</strong> Finalidade</p>'
            . '<table><tr><td><strong>Local:</strong> Local</td><td><strong>Data:</strong> Data do evento (autorização)</td></tr>'
            . '<tr><td><strong>Hora de saída:</strong> Hora saída</td><td><strong>Hora de retorno:</strong> {{cv_aut_hora_retorno}}</td></tr></table>'
            . '<p>A finalidade consta no planejamento.</p>';
        $estrutura['body']['sections'][0]['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura('html', ['html' => $corpo]);
        $modelo = ['codigo' => 'autorizacao_personalizada', 'estrutura_json' => json_encode($estrutura)];
        $vars = $svc->mesclarCamposVariaveis([
            'assinante_nome' => 'Direção',
            'assinante_cargo' => 'Direção',
            'logo_html' => '<span></span>',
        ], $svc->camposDoModelo($modelo), $valores);
        $html = $svc->renderHtml($modelo, $vars);

        foreach (['Visita pedagógica', 'Museu &amp; Escola', '13/11/2026', '08:45', '14:30', 'A finalidade consta no planejamento.'] as $trecho) {
            self::assertStringContainsString($trecho, $html);
        }
        self::assertStringNotContainsString('Local:</strong> Local', $html);
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

    public function testSecretariaExibeCamposDeAutorizacaoUsadosNoModeloSemDefinicoesSalvas(): void
    {
        $estrutura = ModeloDocumentoService::estruturaVazia();
        $estrutura['body']['sections'][0]['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura('html', [
            'html' => '<table><tr><td>{{cv_aut_local}}</td><td>{{ CV_DATA_EVENTO }}</td>'
                . '<td>{{cv_aut_hora_saida}}</td><td>{{cv_aut_hora_retorno}}</td></tr></table><p>{{cv_aut_finalidade}}</p>',
        ]);
        $modelo = ['id' => 40, 'nome' => 'Autorização de passeio', 'codigo' => 'modelo_passeio', 'estrutura_json' => json_encode($estrutura)];
        $db = $this->getMockBuilder(\App\Core\Database::class)->disableOriginalConstructor()->onlyMethods(['fetchAll'])->getMock();
        $db->method('fetchAll')->willReturn([$modelo]);
        $modelos = $this->getMockBuilder(ModeloDocumentoService::class)->disableOriginalConstructor()->onlyMethods(['schemaReady'])->getMock();
        $modelos->method('schemaReady')->willReturn(true);
        $ref = new ReflectionClass(\App\Modulos\ModelosDocumentos\Services\ArquivoEmissaoDocumentoService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('db')->setValue($svc, $db);
        $ref->getProperty('modelos')->setValue($svc, $modelos);

        $campos_variaveis = $svc->catalogo()[0]['campos'];
        self::assertSame([
            'cv_data_evento' => 'data',
            'cv_aut_local' => 'texto',
            'cv_aut_hora_saida' => 'hora',
            'cv_aut_hora_retorno' => 'hora',
            'cv_aut_finalidade' => 'texto',
        ], array_column($campos_variaveis, 'mascara', 'chave'));
        ob_start();
        require dirname(__DIR__, 2) . '/app/Views/admin/_partials/campos_variaveis_documento.php';
        $formulario = ob_get_clean();
        foreach ($campos_variaveis as $campo) {
            self::assertStringContainsString('name="c[' . $campo['chave'] . ']"', $formulario);
        }
        $valores = [
            'cv_aut_local' => 'Museu & Escola',
            'cv_data_evento' => '2026-11-13',
            'cv_aut_hora_saida' => '08:45',
            'cv_aut_hora_retorno' => '14:30',
            'cv_aut_finalidade' => 'Visita pedagógica',
        ];
        $vars = $modelos->mesclarCamposVariaveis([], $campos_variaveis, $valores);
        $html = $modelos->aplicarPlaceholders($estrutura['body']['sections'][0]['columns'][0]['elements'][0]['props']['html'], $vars);
        self::assertSame('<table><tr><td>Museu &amp; Escola</td><td>13/11/2026</td><td>08:45</td><td>14:30</td></tr></table><p>Visita pedagógica</p>', $html);
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

    public function testDetectaQuadroDeUmBimestreVersusDois(): void
    {
        $svc = (new ReflectionClass(DemonstracaoDocumentoService::class))->newInstanceWithoutConstructor();
        $metodo = (new ReflectionClass(DemonstracaoDocumentoService::class))->getMethod('quadroTemApenasUmBimestre');

        $soUm = '<table><thead><tr><th>MATÉRIAS</th><th>PP</th><th>PU</th><th>CH</th><th>PE</th><th>FAL</th></tr></thead></table>';
        $dois = '<table><thead><tr><th>I BIMESTRE</th><th colspan="6">II BIMESTRE</th></tr>'
            . '<tr><th>PP</th><th>PU</th><th>PP</th><th>PU</th></tr></thead></table>';
        $doisCodigo = '<td data-boletim-coluna="b1_pp">4</td><td data-boletim-coluna="b2_pp">7</td>';

        $this->assertTrue($metodo->invoke($svc, $soUm));
        $this->assertTrue($metodo->invoke($svc, ''));
        $this->assertFalse($metodo->invoke($svc, $dois));
        $this->assertFalse($metodo->invoke($svc, $doisCodigo));
    }
}
