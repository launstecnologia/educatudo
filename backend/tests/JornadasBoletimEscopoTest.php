<?php

use App\Core\PeriodoLetivo;
use App\Modulos\Boletins\Controllers\BoletimConfigController;
use App\Modulos\Boletins\Models\JourneyBoletimLancamento;
use PHPUnit\Framework\TestCase;

class JornadasBoletimEscopoTest extends TestCase
{
    private array $cachePeriodo = [];

    protected function setUp(): void
    {
        foreach (['cachePorAno', 'intervalosPorAno'] as $nome) {
            $prop = new ReflectionProperty(PeriodoLetivo::class, $nome);
            $this->cachePeriodo[$nome] = $prop->getValue();
            $prop->setValue(null, $nome === 'cachePorAno'
                ? [2026 => ['tipo' => 'bimestre', 'quantidade' => 4]]
                : [2026 => [
                    1 => ['inicio' => '2026-02-02', 'fim' => '2026-04-24'],
                    2 => ['inicio' => '2026-04-25', 'fim' => '2026-07-10'],
                    3 => ['inicio' => '2026-07-11', 'fim' => '2026-09-30'],
                    4 => ['inicio' => '2026-10-01', 'fim' => '2026-12-18'],
                ]]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->cachePeriodo as $nome => $valor) {
            $prop = new ReflectionProperty(PeriodoLetivo::class, $nome);
            $prop->setValue(null, $valor);
        }
    }

    public function testEscopoVazioNaoBuscaOutrasJornadas(): void
    {
        $modelo = $this->modelo([]);
        $resultado = $modelo->notasPorMateriaAluno(10, 1, [], '2026-02-02', '2026-04-24', 10, true, 'media', [], true, null, true);
        $this->assertSame([], $resultado['por_materia']);
        $this->assertSame(0, $resultado['total_jornadas_escopo']);
        $this->assertNull($resultado['nota_unica_valor_padrao']);
    }

    public function testIngressoNoTerceiroExcluiJornadasDoPrimeiroESegundo(): void
    {
        foreach ([1, 2] as $bimestre) {
            $modelo = $this->modelo([['id' => 50, 'turma_id' => 1, 'ano_letivo' => 2026, 'bimestre' => $bimestre]]);
            $resultado = $modelo->notasPorMateriaAluno(10, 1, [50], '2026-01-01', '2026-12-31', 10, true, 'media', [], true, null, true);
            $this->assertSame(1, $resultado['excluidas_por_matricula']);
            $this->assertSame([], $resultado['por_materia']);
            $this->assertNull($resultado['nota_unica_valor_padrao']);
        }
        $this->assertFalse(JourneyBoletimLancamento::jornadaAnteriorAMatricula(['bimestre' => 3], '2026-07-15', 2026));
    }

    public function testPreviaEGeracaoUsamMesmoCalendarioEscolar(): void
    {
        $ref = new ReflectionClass(BoletimConfigController::class);
        $motor = $ref->newInstanceWithoutConstructor();
        $previa = $ref->getMethod('periodoToRange');
        $datas = $ref->getMethod('datasDoPeriodoSimulacao');
        $periodo = App\Modulos\Boletins\Services\SimulacaoBoletimConsulta::resolverPeriodo(['ano_letivo' => 2026, 'bimestre' => 1], null, null);
        $geracao = $datas->invoke($motor, $periodo);
        $intervalo = $previa->invoke($motor, '2026-B1');
        $this->assertSame('2026-02-02', $geracao['inicio']);
        $this->assertSame('2026-04-24', $geracao['fim']);
        $this->assertSame($geracao['inicio'] . ' 00:00:00', $intervalo['inicio']);
        $this->assertSame($geracao['fim'] . ' 23:59:59', $intervalo['fim']);
    }

    private function modelo(array $jornadas): JourneyBoletimLancamento
    {
        $modelo = new class($jornadas) extends JourneyBoletimLancamento {
            private array $jornadasTeste;
            public function __construct(array $jornadas) { $this->jornadasTeste = $jornadas; }
            public function buscarJornadasPorIdsEscopoTurma(array $turmas, array $ids): array { return $this->jornadasTeste; }
            public function listarJornadasCandidatas(array $turmas, ?string $inicio, ?string $fim): array
            {
                throw new RuntimeException('Não deve ampliar um escopo explícito.');
            }
        };
        $db = new class {
            public function tableExists(string $tabela): bool { return in_array($tabela, ['matricula', 'ano_letivo'], true); }
            public function fetch(string $sql, array $params): array { return ['data_entrada' => '2026-07-15']; }
        };
        $prop = new ReflectionProperty(JourneyBoletimLancamento::class, 'db');
        $prop->setValue($modelo, $db);
        return $modelo;
    }
}
