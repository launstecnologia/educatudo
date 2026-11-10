<?php

use App\Core\Database;
use App\Modulos\ModelosDocumentos\Services\ArquivoEmissaoDocumentoService;
use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;
use PHPUnit\Framework\TestCase;

final class CatalogoDocumentosSecretariaTest extends TestCase
{
    /** @dataProvider modelos */
    public function testClassificacaoConsideraVinculoOficialMesmoComCodigoPersonalizado(array $modelo, bool $oficial): void
    {
        $this->assertSame($oficial, ModeloDocumentoService::modeloEhDocumentoOficial($modelo));
    }

    public function modelos(): array
    {
        $casos = [
            'declaracao' => [['codigo' => 'declaracao_matricula'], false],
            'autorizacao personalizada' => [['codigo' => 'modelo_2026', 'nome' => 'DECLARAÇÃO DE PASSEIO', 'estrutura_json' => '{"body":{"sections":[]}}'], false],
            'contrato' => [['codigo' => 'contrato_servicos'], false],
            'legado oficial' => [['codigo' => 'vida_escolar_historico'], true],
            'resultado oficial' => [['codigo' => 'resultado_ata'], true],
            'boletim com codigo proprio' => [['codigo' => 'boletim_lucas'], true],
            'bloco boletim' => [['codigo' => 'modelo_21', 'estrutura_json' => '{"body":{"sections":[{"columns":[{"elements":[{"type":"boletim"}]}]}]}}'], true],
            'bloco historico' => [['codigo' => 'modelo_20', 'estrutura' => ['body' => ['sections' => [['columns' => [['elements' => [['type' => 'historico']]]]]]]]], true],
            'fonte boletim' => [['codigo' => 'modelo_19', 'estrutura_json' => '{"fonte_boletim_id":12}'], true],
            'sem vinculo' => [['codigo' => 'modelo_24', 'estrutura_json' => '{"emissao":{"tipo":""}}'], false],
        ];
        foreach (['boletim', 'historico', 'ficha_individual', 'ata_resultados', 'relatorio'] as $tipo) {
            $casos['vinculo ' . $tipo] = [['codigo' => 'modelo_personalizado', 'estrutura_json' => json_encode(['emissao' => ['tipo' => $tipo]])], true];
        }
        return $casos;
    }

    public function testCatalogoRetornaDeclaracaoEExcluiOficialPersonalizado(): void
    {
        $db = $this->getMockBuilder(Database::class)->disableOriginalConstructor()->onlyMethods(['fetchAll'])->getMock();
        $db->method('fetchAll')->willReturn([
            ['id' => 23, 'codigo' => 'modelo_23', 'nome' => 'BOLETIM - LUCAS', 'estrutura_json' => '{"emissao":{"tipo":"boletim"}}'],
            ['id' => 24, 'codigo' => 'modelo_24', 'nome' => 'DECLARAÇÃO DE PASSEIO', 'estrutura_json' => '{}'],
        ]);
        $modelos = $this->getMockBuilder(ModeloDocumentoService::class)->disableOriginalConstructor()->onlyMethods(['schemaReady'])->getMock();
        $modelos->method('schemaReady')->willReturn(true);
        $svc = $this->servico($db, $modelos);
        $this->assertSame([24], array_column($svc->catalogo(), 'id'));
    }

    public function testPostDiretoNaoEmiteDocumentoOficialExcluidoDoCatalogo(): void
    {
        $db = $this->getMockBuilder(Database::class)->disableOriginalConstructor()->onlyMethods(['fetch'])->getMock();
        $db->method('fetch')->willReturn(['ok' => 1]);
        $modelos = $this->getMockBuilder(ModeloDocumentoService::class)->disableOriginalConstructor()->onlyMethods(['findById'])->getMock();
        $modelos->method('findById')->with(23)->willReturn(['codigo' => 'modelo_23', 'estrutura_json' => '{"emissao":{"tipo":"boletim"}}']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('área própria');
        $this->servico($db, $modelos)->gerar(23, ['ano' => 2024], [], null, null);
    }

    private function servico($db, $modelos): ArquivoEmissaoDocumentoService
    {
        $ref = new ReflectionClass(ArquivoEmissaoDocumentoService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('db')->setValue($svc, $db);
        $ref->getProperty('modelos')->setValue($svc, $modelos);
        return $svc;
    }
}
