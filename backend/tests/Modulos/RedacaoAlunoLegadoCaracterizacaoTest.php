<?php

use App\Modulos\Redacoes\Services\RedacaoAlunoLegadoService;
use PHPUnit\Framework\TestCase;

/**
 * Caracterização do fluxo legado /redacoes (ownership aluno_id).
 */
final class RedacaoAlunoLegadoCaracterizacaoTest extends TestCase
{
    public function testHistoricoFiltraPorAluno(): void
    {
        $db = new class {
            public string $sql = '';
            public array $params = [];
            public function fetchAll(string $sql = '', array $params = [])
            {
                $this->sql = $sql;
                $this->params = $params;
                return [['id' => 1]];
            }
            public function fetch()
            {
                return null;
            }
        };
        $rows = (new RedacaoAlunoLegadoService($db))->listarHistorico(10);
        $this->assertStringContainsString('r.aluno_id = :aluno_id', $db->sql);
        $this->assertSame(10, $db->params['aluno_id']);
        $this->assertCount(1, $rows);
    }

    public function testBuscarDoAlunoExigeOwnership(): void
    {
        $db = new class {
            public string $sql = '';
            public array $params = [];
            public function fetch(string $sql = '', array $params = [])
            {
                $this->sql = $sql;
                $this->params = $params;
                return ['id' => 5, 'aluno_id' => 10];
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $row = (new RedacaoAlunoLegadoService($db))->buscarDoAluno(5, 10);
        $this->assertStringContainsString('r.aluno_id = :aluno_id', $db->sql);
        $this->assertSame(5, $db->params['id']);
        $this->assertSame(10, $db->params['aluno_id']);
        $this->assertNotNull($row);
    }

    public function testBuscarDoAlunoIdInvalido(): void
    {
        $db = new class {
            public int $calls = 0;
            public function fetch()
            {
                $this->calls++;
                return null;
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $this->assertNull((new RedacaoAlunoLegadoService($db))->buscarDoAluno(0, 10));
        $this->assertSame(0, $db->calls);
    }

    public function testOcultarNegaSemOwnership(): void
    {
        $db = new class {
            public int $updates = 0;
            public function fetch()
            {
                return null;
            }
            public function update()
            {
                $this->updates++;
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $ok = (new RedacaoAlunoLegadoService($db))->ocultarDoAluno(99, 10);
        $this->assertFalse($ok);
        $this->assertSame(0, $db->updates);
    }

    public function testOcultarAtualizaComAlunoId(): void
    {
        $db = new class {
            public string $updateSql = '';
            public array $updateParams = [];
            public function fetch()
            {
                return ['id' => 7];
            }
            public function update(string $sql = '', array $params = [])
            {
                $this->updateSql = $sql;
                $this->updateParams = $params;
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $ok = (new RedacaoAlunoLegadoService($db))->ocultarDoAluno(7, 10);
        $this->assertTrue($ok);
        $this->assertStringContainsString('aluno_id = :aluno_id', $db->updateSql);
        $this->assertSame(10, $db->updateParams['aluno_id']);
    }

    public function testExcluirRascunhoComFlag(): void
    {
        $db = new class {
            public string $deleteSql = '';
            public function fetch()
            {
                return ['id' => 3];
            }
            public function delete(string $sql = '', array $params = [])
            {
                $this->deleteSql = $sql;
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $svc = new RedacaoAlunoLegadoService($db);
        $this->assertNotNull($svc->buscarRascunhoParaExcluir(3, 10, true));
        $svc->excluirRascunho(3, 10, true);
        $this->assertStringContainsString('eh_rascunho = 1', $db->deleteSql);
        $this->assertStringContainsString('aluno_id = :aluno_id', $db->deleteSql);
    }

    public function testRotasLegadoApontamModulo(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/app/Modulos/redacoes/routes.php');
        $this->assertStringContainsString('AlunoRedacaoLegadoController@hub', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@historico', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@ver', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@salvarRascunho', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@ocultar', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@corrigir', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@gerarTema', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@criar', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@transcreverImagem', $routes);
        $this->assertStringNotContainsString('StudentController@essays', $routes);
        $this->assertStringNotContainsString('StudentController@salvarRascunho', $routes);
        $this->assertStringNotContainsString('StudentController@corrigirRedacao', $routes);
        $this->assertStringNotContainsString('StudentController@historicoRedacoes', $routes);
        $this->assertStringNotContainsString('StudentController@verRedacao', $routes);
    }

    public function testSalvarRascunhoInsertUsaAlunoId(): void
    {
        $db = new class {
            public string $insertSql = '';
            public array $insertParams = [];
            public function fetch(string $sql = '', array $params = [])
            {
                if (str_contains($sql, "SHOW COLUMNS") && str_contains($sql, 'eh_rascunho')) {
                    return ['Field' => 'eh_rascunho'];
                }
                if (str_contains($sql, "SHOW COLUMNS") && str_contains($sql, 'tempo_escrita')) {
                    return null;
                }
                return null;
            }
            public function insert(string $sql = '', array $params = [])
            {
                $this->insertSql = $sql;
                $this->insertParams = $params;
                return 42;
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $out = (new RedacaoAlunoLegadoService($db))->salvarRascunho(10, [
            'titulo' => 'Novo',
            'conteudo' => 'corpo',
        ]);
        $this->assertTrue($out['success']);
        $this->assertSame(42, $out['rascunho_id']);
        $this->assertStringContainsString('aluno_id', $db->insertSql);
        $this->assertSame(10, $db->insertParams['aluno_id']);
    }
}
