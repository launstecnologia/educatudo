<?php

use App\Modulos\Mural\Services\MuralRecadoService;
use PHPUnit\Framework\TestCase;

/**
 * Caracterização do mural do aluno (listagem, contagem, marcar visto com ownership).
 * Stub PDO-like — sem MySQL; isola a regra já presente em marcarVistoAluno.
 */
final class MuralRecadoAlunoServiceTest extends TestCase
{
    /** @return object{fetch:callable,fetchAll:callable,query:callable,insert:callable} */
    private function stubDb(callable $fetch, ?callable $fetchAll = null, ?callable $query = null): object
    {
        return new class ($fetch, $fetchAll, $query) {
            private $fetchFn;
            private $fetchAllFn;
            private $queryFn;
            /** @var list<array{0:string,1:array}> */
            public array $queries = [];

            public function __construct(callable $fetch, ?callable $fetchAll, ?callable $query)
            {
                $this->fetchFn = $fetch;
                $this->fetchAllFn = $fetchAll ?? static fn () => [];
                $this->queryFn = $query;
            }

            public function fetch(string $sql, array $params = [])
            {
                return ($this->fetchFn)($sql, $params);
            }

            public function fetchAll(string $sql, array $params = []): array
            {
                return ($this->fetchAllFn)($sql, $params);
            }

            public function query(string $sql, array $params = [])
            {
                $this->queries[] = [$sql, $params];
                if ($this->queryFn) {
                    return ($this->queryFn)($sql, $params);
                }
                return true;
            }

            public function insert(string $sql, array $params = [])
            {
                return 1;
            }
        };
    }

    public function testListarParaAlunoRepassaFiltrosETurma(): void
    {
        $capturado = null;
        $db = $this->stubDb(
            static fn () => null,
            function (string $sql, array $params) use (&$capturado) {
                $capturado = ['sql' => $sql, 'params' => $params];
                return [
                    ['id' => 1, 'titulo' => 'Oi', 'conteudo' => 'x', 'data_publicacao' => '2026-01-01', 'data_sai_mural' => '2099-01-01', 'autor_tipo' => 'admin', 'autor_id' => 1, 'autor_nome' => 'Admin'],
                ];
            }
        );

        $svc = new MuralRecadoService($db);
        $rows = $svc->listarParaAluno(10, 24, [
            'professor_id' => 5,
            'materia_id' => 3,
            'data_de' => '2026-01-01',
            'data_ate' => '2026-12-31',
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(10, $capturado['params']['aluno_destino']);
        $this->assertSame(24, $capturado['params']['aluno_turma_id']);
        $this->assertSame(5, $capturado['params']['filtro_professor_id']);
        $this->assertSame(3, $capturado['params']['filtro_materia_id']);
        $this->assertSame('2026-01-01', $capturado['params']['filtro_data_de']);
        $this->assertSame('2026-12-31', $capturado['params']['filtro_data_ate']);
        $this->assertStringContainsString("COALESCE(r.publico, 'alunos') = 'alunos'", $capturado['sql']);
        $this->assertStringContainsString('r.autor_tipo = \'professor\' AND r.autor_id = :filtro_professor_id', $capturado['sql']);
    }

    public function testContarParaAlunoNaoLidosIncluiParametroAluno(): void
    {
        $capturado = null;
        $db = $this->stubDb(function (string $sql, array $params) use (&$capturado) {
            $capturado = ['sql' => $sql, 'params' => $params];
            return ['total' => 2];
        });

        $svc = new MuralRecadoService($db);
        $total = $svc->contarParaAluno(10, 24, true);

        $this->assertSame(2, $total);
        $this->assertSame(10, $capturado['params']['aluno_destino']);
        $this->assertSame(10, $capturado['params']['aluno_id']);
        $this->assertSame(24, $capturado['params']['aluno_turma_id']);
        $this->assertStringContainsString('mural_recados_vistos', $capturado['sql']);
    }

    public function testListarNaoVistosResumoLimita(): void
    {
        $capturado = null;
        $db = $this->stubDb(
            static fn () => null,
            function (string $sql, array $params) use (&$capturado) {
                $capturado = ['sql' => $sql, 'params' => $params];
                return [];
            }
        );

        $svc = new MuralRecadoService($db);
        $svc->listarNaoVistosResumoParaAluno(7, 0, 10);

        $this->assertArrayNotHasKey('aluno_turma_id', $capturado['params']);
        $this->assertSame(7, $capturado['params']['aluno_id']);
        $this->assertStringContainsString('LIMIT 10', $capturado['sql']);
        $this->assertStringNotContainsString('aluno_turma_id', $capturado['sql']);
    }

    public function testMarcarVistoSemAcessoNaoInsere(): void
    {
        $db = $this->stubDb(static function (string $sql, array $params) {
            // alunoPodeVer → vazio
            return null;
        });

        $svc = new MuralRecadoService($db);
        $svc->marcarVistoAluno(99, 10);

        $this->assertSame([], $db->queries, 'Sem acesso: não deve INSERT em mural_recados_vistos');
    }

    public function testMarcarVistoComAcessoInsere(): void
    {
        $db = $this->stubDb(static function (string $sql, array $params) {
            if (strpos($sql, 'FROM mural_recados r') !== false) {
                return ['id' => 99];
            }
            return null;
        });

        $svc = new MuralRecadoService($db);
        $svc->marcarVistoAluno(99, 10);

        $this->assertNotEmpty($db->queries);
        $this->assertStringContainsString('mural_recados_vistos', $db->queries[0][0]);
        $this->assertSame(99, $db->queries[0][1]['rid']);
        $this->assertSame(10, $db->queries[0][1]['aid']);
    }

    public function testAlunoPodeVerRecadoPublico(): void
    {
        $db = $this->stubDb(static function (string $sql, array $params) {
            return ['id' => (int) $params['rid']];
        });
        $svc = new MuralRecadoService($db);
        $this->assertTrue($svc->alunoPodeVerRecado(5, 1));
    }

    public function testAlunoNaoPodeVerRecadoAlheio(): void
    {
        $db = $this->stubDb(static fn () => null);
        $svc = new MuralRecadoService($db);
        $this->assertFalse($svc->alunoPodeVerRecado(5, 1));
    }
}
