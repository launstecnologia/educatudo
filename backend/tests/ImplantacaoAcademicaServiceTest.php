<?php

require_once dirname(__DIR__) . '/app/Dominio/Escola/Services/ImplantacaoAcademicaService.php';

use PHPUnit\Framework\TestCase;

class ImplantacaoAcademicaServiceTest extends TestCase
{
    public function testMontarPassosMarcaPendenciasDaAvaliacao(): void
    {
        $passos = ImplantacaoAcademicaService::montarPassos([
            'regras_aprovacao' => 2,
            'eventos' => 0,
            'boletins' => 0,
            'boletim_layouts' => 0,
        ]);
        $porChave = [];
        foreach ($passos as $p) {
            $porChave[$p['chave']] = $p;
        }
        $this->assertTrue($porChave['regra']['ok']);
        $this->assertFalse($porChave['eventos']['ok']);
        $this->assertFalse($porChave['demonstrativo']['ok']);
        $this->assertFalse($porChave['boletim']['ok']);
        $this->assertFalse($porChave['relatorio']['ok']);
        $this->assertSame('/admin/eventos-avaliativos', $porChave['eventos']['href']);
        $this->assertSame('/admin/boletins', $porChave['demonstrativo']['href']);
        $this->assertSame('/admin/eventos-avaliativos/boletim', $porChave['boletim']['href']);
        $this->assertArrayNotHasKey('quadro', $porChave);
        $this->assertArrayNotHasKey('tipos_nota', $porChave);
        $this->assertArrayNotHasKey('modelo', $porChave);
    }

    public function testRelatorioFicaProntoComDemonstrativoOuBoletim(): void
    {
        $soDemonstrativo = ImplantacaoAcademicaService::montarPassos([
            'boletins' => 1,
            'boletim_layouts' => 0,
        ]);
        $soBoletim = ImplantacaoAcademicaService::montarPassos([
            'boletins' => 0,
            'boletim_layouts' => 1,
        ]);
        $porChave = static function (array $passos): array {
            $mapa = [];
            foreach ($passos as $p) {
                $mapa[$p['chave']] = $p;
            }
            return $mapa;
        };
        $this->assertTrue($porChave($soDemonstrativo)['relatorio']['ok']);
        $this->assertFalse($porChave($soDemonstrativo)['boletim']['ok']);
        $this->assertTrue($porChave($soBoletim)['relatorio']['ok']);
        $this->assertFalse($porChave($soBoletim)['demonstrativo']['ok']);
    }

    public function testGuiaPublicoNaoMarcaPassoComoPronto(): void
    {
        $guia = (new ImplantacaoAcademicaService())->guiaPublico();
        $chaves = [];
        foreach ($guia['passos'] as $passo) {
            $chaves[] = $passo['chave'];
            $this->assertFalse($passo['ok'], $passo['chave']);
        }
        $this->assertContains('eventos', $chaves);
        $this->assertContains('demonstrativo', $chaves);
        $this->assertContains('boletim', $chaves);
        $this->assertContains('relatorio', $chaves);
        $this->assertNotContains('quadro', $chaves);
        $this->assertNotContains('tipos_nota', $chaves);
    }
}
