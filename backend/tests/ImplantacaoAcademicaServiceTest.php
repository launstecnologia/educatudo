<?php

require_once dirname(__DIR__) . '/app/Services/ImplantacaoAcademicaService.php';

use PHPUnit\Framework\TestCase;

class ImplantacaoAcademicaServiceTest extends TestCase
{
    public function testMontarPassosMarcaPendencias(): void
    {
        $passos = ImplantacaoAcademicaService::montarPassos([
            'matrizes' => 1,
            'quadros' => 0,
            'regras_aprovacao' => 2,
            'boletins' => 0,
            'avaliacoes' => 0,
            'calendario' => true,
            'fechamento_schema' => false,
        ]);
        $porChave = [];
        foreach ($passos as $p) {
            $porChave[$p['chave']] = $p;
        }
        $this->assertTrue($porChave['matriz']['ok']);
        $this->assertFalse($porChave['quadro']['ok']);
        $this->assertTrue($porChave['regra']['ok']);
        $this->assertFalse($porChave['modelo']['ok']);
        $this->assertTrue($porChave['calendario']['ok']);
        $this->assertFalse($porChave['fechamento']['ok']);
        $this->assertSame('/admin/quadros-notas', $porChave['quadro']['href']);
    }

    public function testTrilhaCompletaComTudoPronto(): void
    {
        $passos = ImplantacaoAcademicaService::montarPassos([
            'matrizes' => 3,
            'quadros' => 1,
            'regras_aprovacao' => 1,
            'boletins' => 1,
            'avaliacoes' => 4,
            'calendario' => 1,
            'fechamento_schema' => 1,
        ]);
        foreach ($passos as $p) {
            $this->assertTrue($p['ok'], $p['chave']);
        }
    }
}
