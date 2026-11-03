<?php

use App\Modulos\Boletins\Controllers\BoletimConfigController;
use PHPUnit\Framework\TestCase;

class BoletimRegravacaoDemonstrativoTest extends TestCase
{
    public function testRegravacaoDaSincronizacaoMantemLinhaGeradaDePortugues(): void
    {
        $motor = new class extends BoletimConfigController {
            public function __construct() {}
            public function simularRegraAluno(array $regra, int $alunoId, string $periodoRef, ?string $rangeInicioOverride = null, ?string $rangeFimOverride = null, array $visitedRuleCodes = [], bool $forcarAgrupamentoLinhas = false, bool $pularAgrupamentoLinhas = false): array
            {
                // Sem dados de origem: a simulação bruta tem só a linha genérica.
                // A composição do demonstrativo é quem inclui a área configurada.
                return ['matriz_materias' => [
                    'colunas' => [['codigo' => 'resultado_final', 'nome' => 'Resultado Final']],
                    'linhas' => [['materia_id' => 0, 'materia_nome' => '—', 'notas' => ['resultado_final' => 0]]],
                ]];
            }
        };
        $regra = ['id' => 6, 'exibir_em' => 'notas', 'round_mode' => 'none', 'componentes' => [
            ['codigo' => 'jornada', 'source_type' => 'jornadas', 'config' => ['group_line' => [
                'enabled' => true, 'key' => 'portugues', 'label' => 'Língua Portuguesa',
                'agrupamento_id' => 1, 'materias_ids' => [10, 11], 'ocultar_filhas' => true,
            ]]],
            ['codigo' => 'resultado_final', 'source_type' => 'calculado', 'config' => ['expressao' => '(prova_bim + jornada) / 3']],
        ]];
        foreach ([
            'resultadoAcademicoSvc' => (new ReflectionClass(App\Services\ResultadoAcademicoService::class))->newInstanceWithoutConstructor(),
            'agrupamentoCadastroCache' => [1 => ['id' => 1, 'nome' => 'Língua Portuguesa', 'materias_ids' => [10, 11]]],
            'expansaoRegraCache' => [6 => ['regra' => $regra, 'componentes' => $regra['componentes']]],
        ] as $nome => $valor) {
            (new ReflectionProperty(BoletimConfigController::class, $nome))->setValue($motor, $valor);
        }
        $modelo = new class {
            public array $linhas = [];
            public function criarGeracao(...$args): ?int { return null; }
            public function replaceGeneratedResultsForAluno(...$args): void { $this->linhas = $args[6]; }
        };
        (new ReflectionProperty(BoletimConfigController::class, 'boletimConfig'))->setValue($motor, $modelo);
        $args = [$regra, 636, '2026-B1', '2026-01-01', '2026-03-31'];
        $gerada = (new ReflectionMethod(BoletimConfigController::class, 'matrizDemonstrativoAtual'))->invoke($motor, ...$args);
        $modelo->linhas = $gerada['linhas'];
        $this->assertContains('Língua Portuguesa', array_column($modelo->linhas, 'materia_nome'));

        // É o caminho chamado por propagarNotaNoBoletimOficial ao sincronizar.
        $ok = (new ReflectionMethod(BoletimConfigController::class, 'regravarResultadoOficialAluno'))->invoke($motor, ...[...$args, []]);
        $this->assertTrue($ok);
        $this->assertSame($gerada['linhas'], $modelo->linhas, 'Sincronizar não pode substituir o demonstrativo por uma simulação bruta sem Português.');
    }
}
