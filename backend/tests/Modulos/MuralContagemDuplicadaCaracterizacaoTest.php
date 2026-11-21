<?php

use App\Dominio\Pessoas\Services\DashboardAlunoService;
use App\Modulos\Mural\Services\MuralRecadoService;
use PHPUnit\Framework\TestCase;

/**
 * Caracterização histórica: SQL legado do dashboard × regra oficial do portal.
 *
 * Após Opção A a produção usa só a regra oficial; este arquivo preserva a divergência
 * documentada (legado vs oficial) para regressão e baseline de impacto.
 */
final class MuralContagemDuplicadaCaracterizacaoTest extends TestCase
{
    private const HOJE = '2026-11-20';

    /**
     * Semântica DashboardAlunoService::sqlMuralBase (cópia fiel para fixture).
     * Não filtra publico nem aluno_id individual.
     *
     * @param array<string,mixed> $r
     * @param array<int,int> $turmasDoRecado
     * @param array<int,bool> $vistosPorAluno mapa recadoId => true se visto
     */
    private function dashboardInclui(
        array $r,
        int $alunoId,
        int $turmaId,
        array $turmasDoRecado,
        array $vistosPorAluno,
        bool $apenasNaoVistos
    ): bool {
        if (($r['data_sai_mural'] ?? '') < self::HOJE) {
            return false;
        }
        $enviarTodos = (int) ($r['enviar_para_todos'] ?? 0) === 1;
        $naTurma = $turmaId > 0 && in_array($turmaId, $turmasDoRecado, true);
        if (!($enviarTodos || $naTurma)) {
            return false;
        }
        if ($apenasNaoVistos && !empty($vistosPorAluno[(int) $r['id']])) {
            return false;
        }
        return true;
    }

    /**
     * Semântica MuralRecadoService::sqlBaseVisibilidadeAluno (cópia fiel).
     *
     * @param array<string,mixed> $r
     * @param array<int,int> $turmasDoRecado
     * @param array<int,bool> $vistosPorAluno
     */
    private function muralSvcInclui(
        array $r,
        int $alunoId,
        int $turmaId,
        array $turmasDoRecado,
        array $vistosPorAluno,
        bool $apenasNaoVistos
    ): bool {
        $publico = $r['publico'] ?? 'alunos';
        if ($publico === null || $publico === '') {
            $publico = 'alunos';
        }
        if ($publico !== 'alunos') {
            return false;
        }
        if (($r['data_sai_mural'] ?? '') < self::HOJE) {
            return false;
        }
        $destinoAluno = isset($r['aluno_id']) && $r['aluno_id'] !== null && $r['aluno_id'] !== ''
            ? (int) $r['aluno_id']
            : null;
        if ($destinoAluno !== null) {
            if ($destinoAluno !== $alunoId) {
                return false;
            }
        } else {
            $enviarTodos = (int) ($r['enviar_para_todos'] ?? 0) === 1;
            $naTurma = $turmaId > 0 && in_array($turmaId, $turmasDoRecado, true);
            if (!($enviarTodos || $naTurma)) {
                return false;
            }
        }
        if ($apenasNaoVistos && !empty($vistosPorAluno[(int) $r['id']])) {
            return false;
        }
        return true;
    }

    /**
     * @param list<array{recado:array<string,mixed>,turmas:array<int,int>}> $universo
     * @param array<int,bool> $vistos
     * @return array{dashboard:int,mural:int}
     */
    private function contarAmbos(
        array $universo,
        int $alunoId,
        int $turmaId,
        array $vistos,
        bool $apenasNaoVistos
    ): array {
        $d = 0;
        $m = 0;
        foreach ($universo as $item) {
            $r = $item['recado'];
            $turmas = $item['turmas'];
            if ($this->dashboardInclui($r, $alunoId, $turmaId, $turmas, $vistos, $apenasNaoVistos)) {
                $d++;
            }
            if ($this->muralSvcInclui($r, $alunoId, $turmaId, $turmas, $vistos, $apenasNaoVistos)) {
                $m++;
            }
        }
        return ['dashboard' => $d, 'mural' => $m];
    }

    /** Fixture base: aluno 10 turma 24. */
    private function universoPadrao(): array
    {
        return [
            [
                'recado' => [
                    'id' => 1,
                    'publico' => 'alunos',
                    'aluno_id' => 10,
                    'enviar_para_todos' => 0,
                    'data_sai_mural' => '2099-01-01',
                ],
                'turmas' => [],
            ],
            [
                'recado' => [
                    'id' => 2,
                    'publico' => 'alunos',
                    'aluno_id' => null,
                    'enviar_para_todos' => 0,
                    'data_sai_mural' => '2099-01-01',
                ],
                'turmas' => [24],
            ],
            [
                'recado' => [
                    'id' => 3,
                    'publico' => 'alunos',
                    'aluno_id' => null,
                    'enviar_para_todos' => 1,
                    'data_sai_mural' => '2099-01-01',
                ],
                'turmas' => [],
            ],
            [
                'recado' => [
                    'id' => 4,
                    'publico' => 'alunos',
                    'aluno_id' => null,
                    'enviar_para_todos' => 0,
                    'data_sai_mural' => '2099-01-01',
                ],
                'turmas' => [99],
            ],
            [
                'recado' => [
                    'id' => 5,
                    'publico' => 'alunos',
                    'aluno_id' => 99,
                    'enviar_para_todos' => 0,
                    'data_sai_mural' => '2099-01-01',
                ],
                'turmas' => [],
            ],
            [
                'recado' => [
                    'id' => 6,
                    'publico' => 'professores',
                    'aluno_id' => null,
                    'enviar_para_todos' => 1,
                    'data_sai_mural' => '2099-01-01',
                ],
                'turmas' => [],
            ],
            [
                'recado' => [
                    'id' => 7,
                    'publico' => 'alunos',
                    'aluno_id' => null,
                    'enviar_para_todos' => 1,
                    'data_sai_mural' => '2020-01-01',
                ],
                'turmas' => [],
            ],
        ];
    }

    public function testRecadoIndividualContaNoMuralNaoNoDashboard(): void
    {
        $c = $this->contarAmbos($this->universoPadrao(), 10, 24, [], false);
        // id=1 individual → só mural
        // id=2 turma 24 → ambos
        // id=3 todos → ambos
        // id=6 professores+todos → só dashboard (não filtra publico)
        $this->assertSame(3, $c['mural'], 'Mural: individual+turma+todos');
        $this->assertSame(3, $c['dashboard'], 'Dashboard: turma+todos+professores(todos)');
        $this->assertNotSame(
            $this->muralSvcInclui($this->universoPadrao()[0]['recado'], 10, 24, [], [], false),
            $this->dashboardInclui($this->universoPadrao()[0]['recado'], 10, 24, [], [], false)
        );
        $this->assertTrue($this->muralSvcInclui($this->universoPadrao()[0]['recado'], 10, 24, [], [], false));
        $this->assertFalse($this->dashboardInclui($this->universoPadrao()[0]['recado'], 10, 24, [], [], false));
    }

    public function testRecadoTurmaContaNosDois(): void
    {
        $r = $this->universoPadrao()[1];
        $this->assertTrue($this->muralSvcInclui($r['recado'], 10, 24, $r['turmas'], [], false));
        $this->assertTrue($this->dashboardInclui($r['recado'], 10, 24, $r['turmas'], [], false));
    }

    public function testRecadoPublicoTodosContaNosDoisParaAlunos(): void
    {
        $r = $this->universoPadrao()[2];
        $this->assertTrue($this->muralSvcInclui($r['recado'], 10, 24, $r['turmas'], [], false));
        $this->assertTrue($this->dashboardInclui($r['recado'], 10, 24, $r['turmas'], [], false));
    }

    public function testRecadoPublicoProfessoresSoDashboard(): void
    {
        $r = $this->universoPadrao()[5];
        $this->assertFalse($this->muralSvcInclui($r['recado'], 10, 24, $r['turmas'], [], false));
        $this->assertTrue($this->dashboardInclui($r['recado'], 10, 24, $r['turmas'], [], false));
    }

    public function testJaVisualizadoExcluiQuandoApenasNaoVistos(): void
    {
        $r = $this->universoPadrao()[2];
        $vistos = [3 => true];
        $this->assertFalse($this->muralSvcInclui($r['recado'], 10, 24, $r['turmas'], $vistos, true));
        $this->assertFalse($this->dashboardInclui($r['recado'], 10, 24, $r['turmas'], $vistos, true));
        $this->assertTrue($this->muralSvcInclui($r['recado'], 10, 24, $r['turmas'], $vistos, false));
    }

    public function testNaoVisualizadoContaNosDoisQuandoElegivel(): void
    {
        $r = $this->universoPadrao()[2];
        $this->assertTrue($this->muralSvcInclui($r['recado'], 10, 24, $r['turmas'], [], true));
        $this->assertTrue($this->dashboardInclui($r['recado'], 10, 24, $r['turmas'], [], true));
    }

    public function testAlunoSemRecadosRelevantes(): void
    {
        $c = $this->contarAmbos([
            [
                'recado' => [
                    'id' => 50,
                    'publico' => 'alunos',
                    'aluno_id' => 99,
                    'enviar_para_todos' => 0,
                    'data_sai_mural' => '2099-01-01',
                ],
                'turmas' => [88],
            ],
        ], 10, 24, [], false);
        $this->assertSame(0, $c['mural']);
        $this->assertSame(0, $c['dashboard']);
    }

    public function testRecadoDeOutroAlunoNaoContaNoMural(): void
    {
        $r = $this->universoPadrao()[4];
        $this->assertFalse($this->muralSvcInclui($r['recado'], 10, 24, $r['turmas'], [], false));
        $this->assertFalse($this->dashboardInclui($r['recado'], 10, 24, $r['turmas'], [], false));
    }

    public function testRecadoDeOutraTurmaNaoConta(): void
    {
        $r = $this->universoPadrao()[3];
        $this->assertFalse($this->muralSvcInclui($r['recado'], 10, 24, $r['turmas'], [], false));
        $this->assertFalse($this->dashboardInclui($r['recado'], 10, 24, $r['turmas'], [], false));
    }

    public function testExpiradoNaoConta(): void
    {
        $r = $this->universoPadrao()[6];
        $this->assertFalse($this->muralSvcInclui($r['recado'], 10, 24, $r['turmas'], [], false));
        $this->assertFalse($this->dashboardInclui($r['recado'], 10, 24, $r['turmas'], [], false));
    }

    public function testSqlMuralServiceTemPublicoEAlunoDestino(): void
    {
        $svc = new MuralRecadoService(new class {
            public function fetch() { return null; }
            public function fetchAll() { return []; }
        });
        $ref = new ReflectionClass($svc);
        $sql = $ref->getMethod('sqlBaseVisibilidadeAluno')->invoke($svc, 24, true);
        $params = $ref->getMethod('paramsBaseVisibilidadeAluno')->invoke($svc, 10, 24, true);

        $this->assertStringContainsString("COALESCE(r.publico, 'alunos') = 'alunos'", $sql);
        $this->assertStringContainsString('r.aluno_id = :aluno_destino', $sql);
        $this->assertSame(10, $params['aluno_destino']);
        $this->assertSame(10, $params['aluno_id']);
        $this->assertSame(24, $params['aluno_turma_id']);
    }

    public function testDashboardProducaoUsaRegraOficial(): void
    {
        $captured = new class {
            /** @var list<string> */
            public array $sqls = [];
            public function fetch(string $sql = '', array $params = [])
            {
                $this->sqls[] = $sql;
                return ['total' => 0];
            }
            public function fetchAll()
            {
                return [];
            }
        };
        (new DashboardAlunoService($captured))->muralResumo(10, 24);
        $this->assertNotEmpty($captured->sqls);
        foreach ($captured->sqls as $sql) {
            $this->assertStringContainsString("COALESCE(r.publico, 'alunos') = 'alunos'", $sql);
            $this->assertStringContainsString('aluno_destino', $sql);
        }
        $ref = new ReflectionClass(DashboardAlunoService::class);
        $this->assertFalse($ref->hasMethod('sqlMuralBase'));
    }

    public function testTotaisUniversoPadraoDivergemHistorico(): void
    {
        $c = $this->contarAmbos($this->universoPadrao(), 10, 24, [], false);
        $this->assertSame(3, $c['mural']);
        $this->assertSame(3, $c['dashboard']);
        // Baseline histórico (legado ≠ oficial) — mural={1,2,3} legado={2,3,6}
        $muralIds = [];
        $dashIds = [];
        foreach ($this->universoPadrao() as $item) {
            $id = (int) $item['recado']['id'];
            if ($this->muralSvcInclui($item['recado'], 10, 24, $item['turmas'], [], false)) {
                $muralIds[] = $id;
            }
            if ($this->dashboardInclui($item['recado'], 10, 24, $item['turmas'], [], false)) {
                $dashIds[] = $id;
            }
        }
        $this->assertSame([1, 2, 3], $muralIds);
        $this->assertSame([2, 3, 6], $dashIds);
        $this->assertNotEquals($muralIds, $dashIds, 'Documenta impacto da consolidação A');
    }
}
