<?php

require_once dirname(__DIR__) . '/app/Dominio/Escola/Services/SchoolCalendarService.php';

use App\Dominio\Escola\Services\SchoolCalendarService;
use PHPUnit\Framework\TestCase;

class CalendarioLetivoDatasEventoTest extends TestCase
{
    public function testLimitesDeAnoEDiaUnico(): void
    {
        $minimo = SchoolCalendarService::validarDatasEvento('2000-01-01', '');
        $this->assertTrue($minimo['ok']);
        $this->assertSame('2000-01-01', $minimo['inicio']);
        $this->assertSame('2000-01-01', $minimo['fim']);

        $maximo = SchoolCalendarService::validarDatasEvento('2100-12-31', '2100-12-31');
        $this->assertTrue($maximo['ok']);
        $this->assertSame('2100-12-31', $maximo['fim']);

        $ano1999 = SchoolCalendarService::validarDatasEvento('1999-12-31', '1999-12-31');
        $this->assertFalse($ano1999['ok']);
        $this->assertSame('O ano deve estar entre 2000 e 2100.', $ano1999['erros']['data_inicio']);
        $this->assertSame('', $ano1999['inicio']);

        $ano2101 = SchoolCalendarService::validarDatasEvento('2100-12-31', '2101-01-01');
        $this->assertFalse($ano2101['ok']);
        $this->assertSame('O ano deve estar entre 2000 e 2100.', $ano2101['erros']['data_fim']);
        $this->assertSame('', $ano2101['fim']);
    }

    public function testDatasImpossiveisNaoSaoConvertidas(): void
    {
        $fevereiro = SchoolCalendarService::validarDatasEvento('2024-02-31', '2024-02-31');
        $this->assertFalse($fevereiro['ok']);
        $this->assertSame('Informe uma data válida.', $fevereiro['erros']['data_inicio']);
        $this->assertSame('', $fevereiro['inicio']);

        $naoBissexto = SchoolCalendarService::validarDatasEvento('2023-02-29', '');
        $this->assertFalse($naoBissexto['ok']);
        $this->assertSame('Informe uma data válida.', $naoBissexto['erros']['data_inicio']);

        $bissexto = SchoolCalendarService::validarDatasEvento('2024-02-29', '');
        $this->assertTrue($bissexto['ok']);
        $this->assertSame('2024-02-29', $bissexto['fim']);

        $ano2000 = SchoolCalendarService::validarDatasEvento('2000-02-29', '2000-02-29');
        $this->assertTrue($ano2000['ok']);

        $ano2100 = SchoolCalendarService::validarDatasEvento('2100-02-29', '');
        $this->assertFalse($ano2100['ok']);
        $this->assertSame('Informe uma data válida.', $ano2100['erros']['data_inicio']);
    }

    public function testFimAnteriorAoInicioERejeitado(): void
    {
        $intervalo = SchoolCalendarService::validarDatasEvento('2026-10-16', '2026-10-15');
        $this->assertFalse($intervalo['ok']);
        $this->assertSame(
            'A data final deve ser igual ou posterior à data inicial.',
            $intervalo['erros']['data_fim']
        );
        $this->assertSame('', $intervalo['inicio']);
        $this->assertSame('', $intervalo['fim']);
    }

    public function testAnoIncompletoEFormatoTrocadoSaoInvalidos(): void
    {
        $parcial = SchoolCalendarService::validarDatasEvento('0002-10-16', '');
        $this->assertFalse($parcial['ok']);
        $this->assertSame('O ano deve estar entre 2000 e 2100.', $parcial['erros']['data_inicio']);

        $brasileiro = SchoolCalendarService::validarDatasEvento('16/10/2026', '16/10/2026');
        $this->assertFalse($brasileiro['ok']);
        $this->assertSame('Informe uma data válida.', $brasileiro['erros']['data_inicio']);
    }
}
