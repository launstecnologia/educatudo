<?php

use App\Modulos\Boletins\Controllers\BoletimConfigController;
use App\Modulos\Boletins\Models\BoletimConfig;
use PHPUnit\Framework\TestCase;

class BoletimGrupoSemNotasTest extends TestCase
{
    public function testLinhaPortuguesSemNotasPermaneceIdentificadaAtePersistencia(): void
    {
        $motor = (new ReflectionClass(BoletimConfigController::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(BoletimConfigController::class, 'resultadoAcademicoSvc'))->setValue(
            $motor, (new ReflectionClass(App\Services\ResultadoAcademicoService::class))->newInstanceWithoutConstructor()
        );
        (new ReflectionProperty(BoletimConfigController::class, 'agrupamentoCadastroCache'))->setValue($motor, [
            1 => ['id' => 1, 'nome' => 'Língua Portuguesa', 'materias_ids' => [10, 11]],
        ]);
        $regra = [
            'id' => 6, 'exibir_em' => 'notas', 'round_mode' => 'none',
            'componentes' => [
                [
                    'codigo' => 'jornada', 'source_type' => 'jornadas',
                    'config' => ['group_line' => [
                        'enabled' => true, 'key' => 'portugues', 'label' => 'Língua Portuguesa',
                        'agrupamento_id' => 1, 'materias_ids' => [10, 11], 'ocultar_filhas' => true,
                    ]],
                ],
                ['codigo' => 'resultado_final', 'source_type' => 'calculado', 'config' => ['expressao' => '(prova_bim + jornada) / 3']],
            ],
        ];
        $simulacao = ['matriz_materias' => [
            'colunas' => [['codigo' => 'resultado_final', 'nome' => 'Resultado Final']],
            'linhas' => [
                ['materia_id' => 0, 'materia_nome' => '—', 'notas' => ['resultado_final' => 0]],
                ['materia_id' => 2, 'materia_nome' => 'Arte', 'notas' => ['resultado_final' => 0]],
            ],
        ]];
        $matriz = $motor->montarMatrizDemonstrativoComGrupoHierarquico($simulacao, $regra)['matriz_materias'];
        $pais = array_values(array_filter($matriz['linhas'], static fn (array $linha): bool => $linha['materia_nome'] === 'Língua Portuguesa'));
        $this->assertCount(1, $pais, 'A fonte gravada deve conter Português mesmo sem notas nas matérias filhas.');
        $this->assertSame(0.0, $pais[0]['notas']['resultado_final']);

        $modelo = (new ReflectionClass(BoletimConfig::class))->newInstanceWithoutConstructor();
        $registros = (new ReflectionMethod(BoletimConfig::class, 'montarRegistrosResultadoAluno'))->invoke(
            $modelo, 6, 636, '2026-B1', '2026-01-01', '2026-03-31',
            $matriz['colunas'], $matriz['linhas'], false, 1, 1, true, true
        );
        $portugues = array_values(array_filter($registros, static fn (array $registro): bool => $registro['materia_nome'] === 'Língua Portuguesa'));
        $this->assertCount(1, $portugues);
        $this->assertSame(0.0, (float) json_decode($portugues[0]['notas_json'], true)['resultado_final']);
        $this->assertSame(1, $portugues[0]['vigente']);
    }
}
