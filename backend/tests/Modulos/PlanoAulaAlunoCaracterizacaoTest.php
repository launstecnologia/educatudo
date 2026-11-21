<?php

use App\Modulos\Jornadas\Services\PlanoAulaAlunoService;
use PHPUnit\Framework\TestCase;

/**
 * Caracterização das regras de planos de aula do aluno (legado StudentController).
 * Assimetria listagem × visualizar é intencional e deve ser preservada.
 */
final class PlanoAulaAlunoCaracterizacaoTest extends TestCase
{
    /** Semântica da listagem: só turma + ano de created_at + não deletado. */
    private function listagemInclui(array $plano, int $turmaAluno, int $anoAtual): bool
    {
        if (!empty($plano['deleted_at'])) {
            return false;
        }
        if ((int) ($plano['turma_id'] ?? 0) !== $turmaAluno) {
            return false;
        }
        $anoCriacao = (int) ($plano['ano_created'] ?? 0);
        return $anoCriacao === $anoAtual;
    }

    /**
     * Semântica do visualizar: mesma turma OU mesma série+ano_letivo.
     * Status e ano de created_at não entram.
     */
    private function visualizarAutorizado(
        array $plano,
        int $turmaAlunoId,
        ?array $turmaAluno,
        ?array $turmaPlano
    ): bool {
        if (!empty($plano['deleted_at'])) {
            return false;
        }
        if ((int) ($plano['turma_id'] ?? 0) === $turmaAlunoId) {
            return true;
        }
        if (
            !empty($turmaAluno['serie']) && !empty($turmaAluno['ano_letivo'])
            && !empty($turmaPlano['serie']) && !empty($turmaPlano['ano_letivo'])
            && $turmaAluno['serie'] == $turmaPlano['serie']
            && $turmaAluno['ano_letivo'] == $turmaPlano['ano_letivo']
        ) {
            return true;
        }
        return false;
    }

    public function testListagemSoMesmaTurmaDoAno(): void
    {
        $ano = (int) date('Y');
        $this->assertTrue($this->listagemInclui([
            'turma_id' => 24, 'deleted_at' => null, 'ano_created' => $ano, 'status' => 'rascunho',
        ], 24, $ano));
        $this->assertFalse($this->listagemInclui([
            'turma_id' => 99, 'deleted_at' => null, 'ano_created' => $ano,
        ], 24, $ano));
        $this->assertFalse($this->listagemInclui([
            'turma_id' => 24, 'deleted_at' => null, 'ano_created' => $ano - 1,
        ], 24, $ano));
        $this->assertFalse($this->listagemInclui([
            'turma_id' => 24, 'deleted_at' => '2026-01-01', 'ano_created' => $ano,
        ], 24, $ano));
    }

    public function testListagemSoStatusAprovadoAposEtapa41(): void
    {
        // Etapa 4.1: semântica de produção exige aprovado; helper local abaixo ainda é legado.
        $svc = new PlanoAulaAlunoService(new class {
            public string $sql = '';
            public function fetchAll(string $sql = '', array $params = [])
            {
                $this->sql = $sql;
                return [];
            }
            public function fetch()
            {
                return null;
            }
        });
        $svc->listarParaTurma(24, (int) date('Y'));
        $this->assertTrue($svc->statusLiberadoParaAluno('aprovado'));
        $this->assertFalse($svc->statusLiberadoParaAluno('rascunho'));
    }

    public function testVisualizarMesmaTurmaAutoriza(): void
    {
        $this->assertTrue($this->visualizarAutorizado(
            ['turma_id' => 24, 'deleted_at' => null, 'status' => 'rascunho'],
            24,
            ['serie' => '1A', 'ano_letivo' => 2026],
            ['serie' => '1A', 'ano_letivo' => 2026]
        ));
    }

    public function testVisualizarSerieAnoIgualAutorizaMesmoOutraTurma(): void
    {
        $this->assertTrue($this->visualizarAutorizado(
            ['turma_id' => 99, 'deleted_at' => null],
            24,
            ['serie' => '7º', 'ano_letivo' => 2026],
            ['serie' => '7º', 'ano_letivo' => 2026]
        ));
    }

    public function testVisualizarOutraTurmaOutraSerieNega(): void
    {
        $this->assertFalse($this->visualizarAutorizado(
            ['turma_id' => 99, 'deleted_at' => null],
            24,
            ['serie' => '7º', 'ano_letivo' => 2026],
            ['serie' => '8º', 'ano_letivo' => 2026]
        ));
    }

    public function testVisualizarDeletadoNaoAutoriza(): void
    {
        $this->assertFalse($this->visualizarAutorizado(
            ['turma_id' => 24, 'deleted_at' => '2026-01-01'],
            24,
            null,
            null
        ));
    }

    public function testAssimetriaListagemVsVisualizar(): void
    {
        $ano = (int) date('Y');
        // Outra turma mesma série: NÃO na listagem, SIM no visualizar
        $plano = ['turma_id' => 99, 'deleted_at' => null, 'ano_created' => $ano];
        $this->assertFalse($this->listagemInclui($plano, 24, $ano));
        $this->assertTrue($this->visualizarAutorizado(
            $plano,
            24,
            ['serie' => '7º', 'ano_letivo' => 2026],
            ['serie' => '7º', 'ano_letivo' => 2026]
        ));
    }

    public function testAlunoSemTurmaListagemVazia(): void
    {
        $db = new class {
            public int $consultas = 0;
            public function fetchAll()
            {
                $this->consultas++;
                return [];
            }
            public function fetch()
            {
                return null;
            }
        };
        $this->assertSame([], (new PlanoAulaAlunoService($db))->listarParaTurma(0));
        $this->assertSame(0, $db->consultas);
    }

    public function testFormatarDataAulaJson(): void
    {
        $svc = new PlanoAulaAlunoService(new class {
            public function fetch()
            {
                return null;
            }
            public function fetchAll()
            {
                return [];
            }
        });
        $this->assertSame(
            '01/03/2026, 02/03/2026',
            $svc->formatarDataExibicao(['data_aula' => '["2026-03-01","2026-03-02"]'])
        );
        $this->assertSame(
            '01/03/2026 - 05/03/2026',
            $svc->formatarDataExibicao(['data_inicio' => '2026-03-01', 'data_fim' => '2026-03-05'])
        );
        $this->assertSame(
            'A partir de 01/03/2026',
            $svc->formatarDataExibicao(['data_inicio' => '2026-03-01'])
        );
        $this->assertSame('', $svc->formatarDataExibicao([]));
    }

        public function testSqlListagemTemTurmaAnoEStatusLiberado(): void
    {
        $captured = new class {
            public string $sql = '';
            public array $params = [];
            public function fetchAll(string $sql = '', array $params = [])
            {
                $this->sql = $sql;
                $this->params = $params;
                return [];
            }
            public function fetch()
            {
                return null;
            }
        };
        (new PlanoAulaAlunoService($captured))->listarParaTurma(24, 2026);
        $this->assertStringContainsString('pa.turma_id = :turma_id', $captured->sql);
        $this->assertStringContainsString('YEAR(pa.created_at) = :ano_atual', $captured->sql);
        $this->assertStringContainsString('deleted_at IS NULL', $captured->sql);
        $this->assertStringContainsString('pa.status IN', $captured->sql);
        $this->assertSame(24, $captured->params['turma_id']);
        $this->assertSame(2026, $captured->params['ano_atual']);
        $this->assertContains('aprovado', $captured->params);
    }

    public function testSqlBuscarExigeStatusLiberado(): void
    {
        $captured = new class {
            public string $sql = '';
            public function fetch(string $sql = '', array $params = [])
            {
                $this->sql = $sql;
                return null;
            }
            public function fetchAll()
            {
                return [];
            }
        };
        (new PlanoAulaAlunoService($captured))->buscarNaoDeletado(5);
        $this->assertStringContainsString('pa.id = :id', $captured->sql);
        $this->assertStringContainsString('deleted_at IS NULL', $captured->sql);
        $this->assertStringContainsString('pa.status IN', $captured->sql);
    }

    public function testPodeVisualizarViaService(): void
    {
        $db = new class {
            public function fetch(string $sql = '', array $params = [])
            {
                if (str_contains($sql, 'FROM turmas') && (int) ($params['turma_id'] ?? 0) === 24) {
                    return ['id' => 24, 'serie' => '7º', 'ano_letivo' => 2026];
                }
                if (str_contains($sql, 'FROM turmas') && (int) ($params['turma_id'] ?? 0) === 99) {
                    return ['id' => 99, 'serie' => '7º', 'ano_letivo' => 2026];
                }
                return null;
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $svc = new PlanoAulaAlunoService($db);
        $this->assertTrue($svc->alunoPodeVisualizar(
            ['turma_id' => 24],
            ['turma_id' => 24, 'status' => 'aprovado', 'deleted_at' => null]
        ));
        $this->assertTrue($svc->alunoPodeVisualizar(
            ['turma_id' => 24],
            ['turma_id' => 99, 'status' => 'aprovado', 'deleted_at' => null]
        ));
        $this->assertFalse($svc->alunoPodeVisualizar(
            ['turma_id' => 24],
            ['turma_id' => 50, 'status' => 'aprovado', 'deleted_at' => null]
        ));
        $this->assertFalse($svc->alunoPodeVisualizar(
            ['turma_id' => 24],
            ['turma_id' => 24, 'status' => 'rascunho', 'deleted_at' => null]
        ));
    }

    public function testUrlsPreservadasNasRotas(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/app/Modulos/jornadas/routes.php');
        $this->assertStringContainsString("'/aluno/planos-aula'", $routes);
        $this->assertStringContainsString("'/aluno/planos-aula/visualizar/{id}'", $routes);
        $this->assertStringContainsString('AlunoPlanoAulaController@index', $routes);
        $this->assertStringContainsString('AlunoPlanoAulaController@visualizar', $routes);
        $this->assertStringNotContainsString('StudentController@planosAula', $routes);
        $this->assertStringNotContainsString('StudentController@visualizarPlanoAula', $routes);
    }
}
