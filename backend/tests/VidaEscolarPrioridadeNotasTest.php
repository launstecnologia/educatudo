<?php

use App\Modulos\VidaEscolar\Models\VidaEscolar;
use App\Modulos\VidaEscolar\Services\VidaEscolarService;
use PHPUnit\Framework\TestCase;

class VidaEscolarPrioridadeNotasTest extends TestCase
{
    private function servico(): VidaEscolarService
    {
        return (new ReflectionClass(VidaEscolarService::class))->newInstanceWithoutConstructor();
    }

    private function prioridade(array $row, array $valores, int $periodo = 1): array
    {
        return (new ReflectionMethod(VidaEscolarService::class, 'prioridadeNotaGerada'))
            ->invoke($this->servico(), $row, $valores, $periodo);
    }

    public function testResultadoFinalZeroDaFonteVenceTresAntigoDoDocumentoAnual(): void
    {
        $fonte = $this->prioridade(
            ['exibir_em' => 'notas', 'bimestre' => 1, 'media_final' => 0],
            ['nota' => 0, 'qualidade' => 4]
        );
        $copiaAnual = $this->prioridade(
            ['exibir_em' => 'boletim', 'bimestre' => null, 'media_final' => 3, 'periodo_ref' => '2026'],
            ['nota' => 3, 'qualidade' => 4, 'diverge_media' => true]
        );
        $this->assertGreaterThan($copiaAnual['prio'], $fonte['prio']);
    }

    public function testMediaFinalAtualVenceResultadoMaisAntigoComBonificacao(): void
    {
        $fonte = $this->prioridade(
            ['exibir_em' => 'notas', 'bimestre' => 1, 'media_final' => 0],
            ['nota' => 0, 'qualidade' => 3]
        );
        $copia = $this->prioridade(
            ['exibir_em' => 'boletim', 'bimestre' => 1, 'media_final' => 3],
            ['nota' => 3, 'qualidade' => 4, 'diverge_media' => true]
        );
        $this->assertGreaterThan($copia['prio'], $fonte['prio']);
    }

    public function testNotaParcialOuAusenteNaoSubstituiResultadoFinal(): void
    {
        $anual = $this->prioridade(
            ['exibir_em' => 'boletim', 'media_final' => 8],
            ['nota' => 8, 'qualidade' => 4]
        );
        foreach ([['nota' => 0, 'qualidade' => 2], ['nota' => null, 'qualidade' => 4]] as $valores) {
            $fonte = $this->prioridade(['exibir_em' => 'notas', 'bimestre' => 1], $valores);
            $this->assertLessThan($anual['prio'], $fonte['prio']);
        }
    }

    public function testOutroBimestreNaoRecebePreferencia(): void
    {
        $fonte = $this->prioridade(
            ['exibir_em' => 'notas', 'bimestre' => 3, 'media_final' => 8.5],
            ['nota' => 8.5, 'qualidade' => 4],
            1
        );
        $this->assertLessThan(1000, $fonte['prio']);
    }

    public function testExtracaoDoResultadoFinalPreservaZero(): void
    {
        $row = [
            'exibir_em' => 'notas', 'bimestre' => 1, 'media_final' => 0,
            'notas_json' => json_encode(['media' => 0, 'resultado_final' => 0]),
            'colunas_json' => json_encode([
                ['codigo' => 'media', 'nome' => 'Média', 'layout_type' => 'media'],
                ['codigo' => 'resultado_final', 'nome' => 'Resultado Final', 'layout_type' => 'resultado'],
            ]),
        ];
        $periodos = (new ReflectionMethod(VidaEscolarService::class, 'periodosDaLinhaGerada'))
            ->invoke($this->servico(), $row);
        $this->assertSame(0.0, $periodos[1]['nota']);
        $this->assertSame(4, $periodos[1]['qualidade']);
    }

    public function testSincronizacaoAtualizaZeroSemAlterarExternaOuHomologada(): void
    {
        foreach ([['calculada', 'aberta', true], ['calculada', 'fechada', true], ['externa', 'aberta', false], ['calculada', 'homologada', false]] as [$origem, $status, $esperado]) {
            $modelo = new class($origem, $status) extends VidaEscolar {
                public array $alteracoes = [];
                private array $celula;
                public function __construct(string $origem, string $status)
                {
                    $this->celula = ['id' => 10, 'origem' => $origem, 'status' => $status, 'nota' => 3, 'faltas' => 0];
                }
                public function findCelulaLinhaPeriodo(int $linhaId, int $periodo): ?array { return $this->celula; }
                public function atualizarCelula(int $id, array $campos): void { $this->alteracoes = $campos; }
            };
            $servico = $this->servico();
            (new ReflectionProperty(VidaEscolarService::class, 'model'))->setValue($servico, $modelo);
            $alterou = (new ReflectionMethod(VidaEscolarService::class, 'aplicarCelulaCalculada'))
                ->invoke($servico, 1, 1, 0.0, 0, true, true);
            $this->assertSame($esperado, $alterou);
            if ($esperado) {
                $this->assertSame(0.0, $modelo->alteracoes['nota']);
            } else {
                $this->assertSame([], $modelo->alteracoes);
            }
        }
    }
}
