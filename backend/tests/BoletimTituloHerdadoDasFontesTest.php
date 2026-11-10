<?php

use App\Modulos\Boletins\Controllers\BoletimConfigController;
use PHPUnit\Framework\TestCase;

class BoletimTituloHerdadoDasFontesTest extends TestCase
{
    public function testBoletimHerdaTituloDoDemonstrativoDeOrigem(): void
    {
        $motor = (new ReflectionClass(BoletimConfigController::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(BoletimConfigController::class, 'resultadoAcademicoSvc'))->setValue(
            $motor,
            (new ReflectionClass(App\Services\ResultadoAcademicoService::class))->newInstanceWithoutConstructor()
        );
        (new ReflectionProperty(BoletimConfigController::class, 'agrupamentoCadastroCache'))->setValue($motor, []);

        $boletimConfig = new class {
            public function getRuleByCode(string $codigo): ?array
            {
                if ($codigo !== 'sm24_b1') {
                    return null;
                }

                return [
                    'id' => 10,
                    'codigo' => 'sm24_b1',
                    'componentes' => [
                        [
                            'codigo' => 'grp_historia',
                            'source_type' => 'nenhuma',
                            'config_json' => json_encode([
                                'titulo_boletim' => 1,
                                'group_line' => [
                                    'enabled' => true,
                                    'key' => 'historia',
                                    'label' => 'História',
                                    'mode' => 'soma',
                                    'materias_ids' => [101, 102],
                                    'aplicar_em' => 'boletim',
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ];
            }
        };
        (new ReflectionProperty(BoletimConfigController::class, 'boletimConfig'))->setValue($motor, $boletimConfig);

        $regra = [
            'id' => 20,
            'exibir_em' => 'boletim',
            'round_mode' => 'none',
            'componentes' => [
                [
                    'codigo' => 'pp',
                    'source_type' => 'evento_boletim',
                    'config' => [
                        'regra_codigo' => 'sm24_b1',
                        'componente_codigo' => 'pp',
                    ],
                ],
            ],
        ];
        $simulacao = [
            'matriz_materias' => [
                'colunas' => [['codigo' => 'pp', 'nome' => 'PP']],
                'linhas' => [
                    ['materia_id' => 101, 'materia_nome' => 'História 1', 'notas' => ['pp' => 4.0]],
                    ['materia_id' => 102, 'materia_nome' => 'História 2', 'notas' => ['pp' => 5.0]],
                    ['materia_id' => 200, 'materia_nome' => 'Geografia', 'notas' => ['pp' => 7.0]],
                ],
            ],
        ];

        $matriz = $motor->montarMatrizDemonstrativoComGrupoHierarquico($simulacao, $regra)['matriz_materias'];
        $nomes = array_map(
            static fn (array $linha): string => (string) ($linha['materia_nome'] ?? ''),
            $matriz['linhas']
        );

        $this->assertContains('História', $nomes, 'O título marcado no Demonstrativo deve aparecer no boletim.');
        $this->assertContains('História 1', $nomes);
        $this->assertContains('História 2', $nomes);

        $pai = null;
        $filhos = [];
        foreach ($matriz['linhas'] as $linha) {
            if (!empty($linha['eh_grupo_pai']) && ($linha['materia_nome'] ?? '') === 'História') {
                $pai = $linha;
            }
            if (!empty($linha['eh_grupo_filho'])) {
                $filhos[] = (string) ($linha['materia_nome'] ?? '');
            }
        }
        $this->assertNotNull($pai);
        $this->assertSame(['História 1', 'História 2'], $filhos);
        $this->assertSame(9.0, (float) ($pai['notas']['pp'] ?? 0), 'Soma das filhas na linha-mãe.');
    }
}
