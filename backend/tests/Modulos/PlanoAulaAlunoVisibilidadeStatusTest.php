<?php

use App\Modulos\Jornadas\Services\PlanoAulaAlunoService;
use PHPUnit\Framework\TestCase;

/**
 * Etapa 4.1 — rascunhos não são visíveis ao aluno (decisão de produto).
 * Status liberado: apenas `aprovado`.
 */
final class PlanoAulaAlunoVisibilidadeStatusTest extends TestCase
{
    public function testStatusLiberadosSomenteAprovado(): void
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
        $this->assertTrue($svc->statusLiberadoParaAluno('aprovado'));
        $this->assertFalse($svc->statusLiberadoParaAluno('rascunho'));
        $this->assertFalse($svc->statusLiberadoParaAluno('rejeitado'));
        $this->assertFalse($svc->statusLiberadoParaAluno(null));
        $this->assertFalse($svc->statusLiberadoParaAluno('publicado'));
        $this->assertFalse($svc->statusLiberadoParaAluno(''));
    }

    public function testListagemSqlExigeAprovado(): void
    {
        $db = new class {
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
        (new PlanoAulaAlunoService($db))->listarParaTurma(24, 2026);
        $this->assertStringContainsString('pa.status IN', $db->sql);
        $this->assertContains('aprovado', $db->params);
        $this->assertStringContainsString('YEAR(pa.created_at)', $db->sql);
        $this->assertSame(2026, $db->params['ano_atual']);
        $this->assertStringNotContainsString('rascunho', (string) json_encode($db->params));
    }

    public function testListagemSemFiltroAnoPreservaContratoAcademicRead(): void
    {
        $db = new class {
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
        (new PlanoAulaAlunoService($db))->listarParaTurma(24, false);
        $this->assertStringContainsString('pa.status IN', $db->sql);
        $this->assertStringNotContainsString('YEAR(pa.created_at)', $db->sql);
        $this->assertArrayNotHasKey('ano_atual', $db->params);
    }

    public function testBuscarRascunhoPorIdRetornaNull(): void
    {
        $db = new class {
            public string $sql = '';
            public function fetch(string $sql = '', array $params = [])
            {
                $this->sql = $sql;
                // Simula banco: se SQL exige aprovado, não acha rascunho
                if (str_contains($sql, 'status IN') || str_contains($sql, "status = 'aprovado'")) {
                    return null;
                }
                return ['id' => 9, 'status' => 'rascunho', 'turma_id' => 24];
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $this->assertNull((new PlanoAulaAlunoService($db))->buscarNaoDeletado(9));
        $this->assertStringContainsString('status IN', $db->sql);
    }

    public function testAlunoPodeVisualizarNegaRascunhoMesmaTurma(): void
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
        $this->assertFalse($svc->alunoPodeVisualizar(
            ['turma_id' => 24],
            ['turma_id' => 24, 'status' => 'rascunho', 'deleted_at' => null]
        ));
        $this->assertTrue($svc->alunoPodeVisualizar(
            ['turma_id' => 24],
            ['turma_id' => 24, 'status' => 'aprovado', 'deleted_at' => null]
        ));
    }

    public function testAlunoPodeVisualizarNegaRejeitado(): void
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
        $this->assertFalse($svc->alunoPodeVisualizar(
            ['turma_id' => 24],
            ['turma_id' => 24, 'status' => 'rejeitado']
        ));
    }

    public function testListagemStubSoRetornaAprovados(): void
    {
        $universo = [
            ['id' => 1, 'status' => 'aprovado', 'turma_id' => 24, 'deleted_at' => null, 'ano' => 2026],
            ['id' => 2, 'status' => 'rascunho', 'turma_id' => 24, 'deleted_at' => null, 'ano' => 2026],
            ['id' => 3, 'status' => 'rejeitado', 'turma_id' => 24, 'deleted_at' => null, 'ano' => 2026],
            ['id' => 4, 'status' => 'aprovado', 'turma_id' => 99, 'deleted_at' => null, 'ano' => 2026],
            ['id' => 5, 'status' => 'aprovado', 'turma_id' => 24, 'deleted_at' => '2026-01-01', 'ano' => 2026],
        ];
        $db = new class ($universo) {
            private array $universo;
            public function __construct(array $universo)
            {
                $this->universo = $universo;
            }
            public function fetchAll(string $sql = '', array $params = [])
            {
                $out = [];
                foreach ($this->universo as $p) {
                    if ((int) $p['turma_id'] !== (int) $params['turma_id']) {
                        continue;
                    }
                    if ($p['deleted_at'] !== null) {
                        continue;
                    }
                    if ((int) $p['ano'] !== (int) $params['ano_atual']) {
                        continue;
                    }
                    if ($p['status'] !== 'aprovado') {
                        continue;
                    }
                    $out[] = $p;
                }
                return $out;
            }
            public function fetch()
            {
                return null;
            }
        };
        // Stub acima aplica a regra; o service envia SQL — validamos params + allowlist
        $svc = new PlanoAulaAlunoService($db);
        $rows = $svc->listarParaTurma(24, 2026);
        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
        $this->assertSame([1], $ids);
    }

    public function testSerieAnoAindaExigeAprovado(): void
    {
        $db = new class {
            public function fetch(string $sql = '', array $params = [])
            {
                if (str_contains($sql, 'FROM turmas')) {
                    return ['serie' => '7º', 'ano_letivo' => 2026];
                }
                return null;
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $svc = new PlanoAulaAlunoService($db);
        $this->assertFalse($svc->alunoPodeVisualizar(
            ['turma_id' => 24],
            ['turma_id' => 99, 'status' => 'rascunho']
        ));
        $this->assertTrue($svc->alunoPodeVisualizar(
            ['turma_id' => 24],
            ['turma_id' => 99, 'status' => 'aprovado']
        ));
    }
}
