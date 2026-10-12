<?php

require_once dirname(__DIR__) . '/app/Modulos/grupos-regras-notas/Models/GrupoRegrasNotas.php';

use PHPUnit\Framework\TestCase;

class GrupoRegrasNotasNormalizarTest extends TestCase
{
    public function testCriterioInvalidoViraUltima(): void
    {
        $this->assertSame('ultima', GrupoRegrasNotas::normalizarCriterio('xyz'));
        $this->assertSame('media', GrupoRegrasNotas::normalizarCriterio('MEDIA'));
        $this->assertSame('soma', GrupoRegrasNotas::normalizarCriterio('soma'));
    }

    public function testModoSoAceitaSimplesOuBlocos(): void
    {
        $this->assertSame('simples', GrupoRegrasNotas::normalizarModo(''));
        $this->assertSame('simples', GrupoRegrasNotas::normalizarModo('marca'));
        $this->assertSame('blocos', GrupoRegrasNotas::normalizarModo('blocos'));
    }

    public function testEscalaFicaEntre1E100(): void
    {
        $this->assertSame(10.0, GrupoRegrasNotas::normalizarEscala(0));
        $this->assertSame(100.0, GrupoRegrasNotas::normalizarEscala(250));
        $this->assertSame(7.5, GrupoRegrasNotas::normalizarEscala('7,5'));
    }

    public function testCodigoColunaSanitiza(): void
    {
        $this->assertSame('media_sem', GrupoRegrasNotas::normalizarCodigoColuna(''));
        $this->assertSame('media_final', GrupoRegrasNotas::normalizarCodigoColuna('media-final'));
        $this->assertNotSame('', GrupoRegrasNotas::normalizarCodigoColuna('Média'));
    }
}
