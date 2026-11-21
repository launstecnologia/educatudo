<?php

use App\Dominio\Pessoas\Services\DashboardAlunoService;
use App\Modulos\Mural\Services\MuralRecadoService;
use PHPUnit\Framework\TestCase;

/**
 * Após Opção A: DashboardAlunoService::muralResumo delega a MuralRecadoService::contarParaAluno.
 * Stub PDO-like — sem MySQL; valida SQL oficial + contrato JSON de /dashboard/api/montar.
 */
final class MuralDashboardContagemConsolidacaoTest extends TestCase
{
    private const HOJE = '2026-11-20';

    /**
     * @param array<string,mixed> $r
     * @param array<int,int> $turmasDoRecado
     * @param array<int,bool> $vistosPorAluno
     */
    private function muralOficialInclui(
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

    /** @return list<array{recado:array<string,mixed>,turmas:array<int,int>}> */
    private function universo(): array
    {
        return [
            ['recado' => ['id' => 1, 'publico' => 'alunos', 'aluno_id' => 10, 'enviar_para_todos' => 0, 'data_sai_mural' => '2099-01-01'], 'turmas' => []],
            ['recado' => ['id' => 2, 'publico' => 'alunos', 'aluno_id' => null, 'enviar_para_todos' => 0, 'data_sai_mural' => '2099-01-01'], 'turmas' => [24]],
            ['recado' => ['id' => 3, 'publico' => 'alunos', 'aluno_id' => null, 'enviar_para_todos' => 1, 'data_sai_mural' => '2099-01-01'], 'turmas' => []],
            ['recado' => ['id' => 4, 'publico' => 'alunos', 'aluno_id' => null, 'enviar_para_todos' => 0, 'data_sai_mural' => '2099-01-01'], 'turmas' => [99]],
            ['recado' => ['id' => 5, 'publico' => 'alunos', 'aluno_id' => 11, 'enviar_para_todos' => 0, 'data_sai_mural' => '2099-01-01'], 'turmas' => []],
            ['recado' => ['id' => 6, 'publico' => 'professores', 'aluno_id' => null, 'enviar_para_todos' => 1, 'data_sai_mural' => '2099-01-01'], 'turmas' => []],
            ['recado' => ['id' => 7, 'publico' => 'alunos', 'aluno_id' => null, 'enviar_para_todos' => 1, 'data_sai_mural' => '2020-01-01'], 'turmas' => []],
        ];
    }

    /**
     * Stub que aplica a mesma regra SQL do MuralRecadoService sobre o fixture em memória.
     */
    private function stubDbContagem(int $alunoId, int $turmaId, array $vistos): object
    {
        $universo = $this->universo();
        $inclui = function (array $r, array $turmas, bool $apenasNaoVistos) use ($alunoId, $turmaId, $vistos): bool {
            return $this->muralOficialInclui($r, $alunoId, $turmaId, $turmas, $vistos, $apenasNaoVistos);
        };

        return new class ($universo, $inclui) {
            /** @var list<array{recado:array,turmas:array}> */
            private array $universo;
            /** @var callable */
            private $inclui;
            /** @var list<string> */
            public array $sqls = [];

            public function __construct(array $universo, callable $inclui)
            {
                $this->universo = $universo;
                $this->inclui = $inclui;
            }

            public function fetch(string $sql = '', array $params = [])
            {
                $this->sqls[] = $sql;
                $apenasNaoVistos = str_contains($sql, 'mural_recados_vistos');
                $n = 0;
                foreach ($this->universo as $item) {
                    if (($this->inclui)($item['recado'], $item['turmas'], $apenasNaoVistos)) {
                        $n++;
                    }
                }
                return ['total' => $n];
            }

            public function fetchAll()
            {
                return [];
            }
        };
    }

    public function testContratoJsonMuralResumoPreservado(): void
    {
        $db = $this->stubDbContagem(10, 24, []);
        $mural = (new DashboardAlunoService($db))->muralResumo(10, 24);

        // Campos consumidos por /dashboard/api/montar → cards.* e mural.*
        $this->assertSame(
            ['nao_lidos', 'total', 'exibir_popup', 'qtd_popup'],
            array_keys($mural)
        );
        $this->assertIsInt($mural['nao_lidos']);
        $this->assertIsInt($mural['total']);
        $this->assertIsBool($mural['exibir_popup']);
        $this->assertIsInt($mural['qtd_popup']);
        $this->assertSame($mural['nao_lidos'] > 0, $mural['exibir_popup']);
        $this->assertSame(min($mural['nao_lidos'], 10), $mural['qtd_popup']);

        // Espelhamento nos cards (mesmos nomes/tipos da API)
        $cards = [
            'mural_nao_lidos' => (int) $mural['nao_lidos'],
            'mural_total' => (int) $mural['total'],
        ];
        $this->assertIsInt($cards['mural_nao_lidos']);
        $this->assertIsInt($cards['mural_total']);
    }

    public function testDashboardDelegaSqlComPublicoEAlunoDestino(): void
    {
        $db = $this->stubDbContagem(10, 24, []);
        (new DashboardAlunoService($db))->muralResumo(10, 24);

        $this->assertNotEmpty($db->sqls);
        foreach ($db->sqls as $sql) {
            $this->assertStringContainsString("COALESCE(r.publico, 'alunos') = 'alunos'", $sql);
            $this->assertStringContainsString('r.aluno_id = :aluno_destino', $sql);
        }
    }

    public function testParidadeDashboardComPortal(): void
    {
        $db = $this->stubDbContagem(10, 24, []);
        $dash = (new DashboardAlunoService($db))->muralResumo(10, 24);
        $portalNao = (new MuralRecadoService($db))->contarParaAluno(10, 24, true);
        $portalTot = (new MuralRecadoService($db))->contarParaAluno(10, 24, false);

        $this->assertSame($portalNao, $dash['nao_lidos']);
        $this->assertSame($portalTot, $dash['total']);
        $this->assertSame(3, $dash['total']);
        $this->assertSame(3, $dash['nao_lidos']);
    }

    public function testRecadoIndividualConta(): void
    {
        $this->assertContains(1, $this->idsVisiveis(10, 24, [], false));
        $db = $this->stubDbContagem(10, 24, []);
        $dash = (new DashboardAlunoService($db))->muralResumo(10, 24);
        $this->assertGreaterThanOrEqual(1, $dash['total']);
    }

    public function testRecadoTurmaConta(): void
    {
        $this->assertContains(2, $this->idsVisiveis(10, 24, [], false));
    }

    public function testRecadoTodosAlunosConta(): void
    {
        $this->assertContains(3, $this->idsVisiveis(10, 24, [], false));
    }

    public function testRecadoExclusivoProfessoresNaoConta(): void
    {
        $this->assertNotContains(6, $this->idsVisiveis(10, 24, [], false));
    }

    public function testRecadoOutroAlunoNaoConta(): void
    {
        $this->assertNotContains(5, $this->idsVisiveis(10, 24, [], false));
    }

    public function testRecadoOutraTurmaNaoConta(): void
    {
        $this->assertNotContains(4, $this->idsVisiveis(10, 24, [], false));
    }

    public function testRecadoExpiradoNaoConta(): void
    {
        $this->assertNotContains(7, $this->idsVisiveis(10, 24, [], false));
    }

    public function testVisualizadoExcluiDeNaoLidos(): void
    {
        $db = $this->stubDbContagem(10, 24, [2 => true]);
        $dash = (new DashboardAlunoService($db))->muralResumo(10, 24);
        $this->assertSame(2, $dash['nao_lidos']); // 1 e 3
        $this->assertSame(3, $dash['total']);
    }

    public function testNaoVisualizadoContaNosNaoLidos(): void
    {
        $db = $this->stubDbContagem(10, 24, []);
        $dash = (new DashboardAlunoService($db))->muralResumo(10, 24);
        $this->assertSame($dash['total'], $dash['nao_lidos']);
        $this->assertTrue($dash['exibir_popup']);
    }

    public function testAlunoSemRecados(): void
    {
        $db = $this->stubDbContagem(10, 24, []);
        // stub com universo vazio
        $vazio = new class {
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
        $dash = (new DashboardAlunoService($vazio))->muralResumo(10, 24);
        $this->assertSame(0, $dash['nao_lidos']);
        $this->assertSame(0, $dash['total']);
        $this->assertFalse($dash['exibir_popup']);
        $this->assertSame(0, $dash['qtd_popup']);
    }

    public function testCacheKeyIsoladaPorTenantEAluno(): void
    {
        $svc = new DashboardAlunoService(new class {
            public function fetch()
            {
                return ['total' => 0];
            }
            public function fetchAll()
            {
                return [];
            }
        });
        $ref = new ReflectionClass($svc);
        $method = $ref->getMethod('cacheKey');
        $k1 = $method->invoke($svc, 10, 24, false, false);
        $k2 = $method->invoke($svc, 11, 24, false, false);
        $this->assertStringContainsString('dashboard:cards:v2:', $k1);
        $this->assertNotSame($k1, $k2);
        $this->assertMatchesRegularExpression('/dashboard:cards:v2:[^:]+:10:24:0:0/', $k1);
    }

    public function testHelpersSqlLegadosRemovidos(): void
    {
        $ref = new ReflectionClass(DashboardAlunoService::class);
        $this->assertFalse($ref->hasMethod('sqlMuralBase'));
        $this->assertFalse($ref->hasMethod('paramsMuralBase'));
    }

    public function testAntesDepoisConjuntos(): void
    {
        // Legado dashboard (sem publico / sem individual) × oficial pós-consolidação
        $legado = [];
        $oficial = [];
        foreach ($this->universo() as $item) {
            $r = $item['recado'];
            $id = (int) $r['id'];
            if (($r['data_sai_mural'] ?? '') >= self::HOJE) {
                $enviarTodos = (int) ($r['enviar_para_todos'] ?? 0) === 1;
                $naTurma = in_array(24, $item['turmas'], true);
                if ($enviarTodos || $naTurma) {
                    $legado[] = $id;
                }
            }
            if ($this->muralOficialInclui($r, 10, 24, $item['turmas'], [], false)) {
                $oficial[] = $id;
            }
        }
        $this->assertSame([2, 3, 6], $legado, 'antes: broadcast+professores');
        $this->assertSame([1, 2, 3], $oficial, 'depois: individual+turma+todos alunos');
    }

    /** @return list<int> */
    private function idsVisiveis(int $alunoId, int $turmaId, array $vistos, bool $apenasNaoVistos): array
    {
        $ids = [];
        foreach ($this->universo() as $item) {
            if ($this->muralOficialInclui($item['recado'], $alunoId, $turmaId, $item['turmas'], $vistos, $apenasNaoVistos)) {
                $ids[] = (int) $item['recado']['id'];
            }
        }
        return $ids;
    }
}
