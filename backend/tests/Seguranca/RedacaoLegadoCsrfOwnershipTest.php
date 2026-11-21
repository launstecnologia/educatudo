<?php

use App\Core\BaseController;
use App\Modulos\Redacoes\Services\RedacaoAlunoLegadoService;
use PHPUnit\Framework\TestCase;

/**
 * Etapa 5.1 — CSRF localizado + ownership nas escritas do legado /redacoes.
 * Não depende de CSRF_MODE global (audit ≠ bloqueio efetivo).
 */
final class RedacaoLegadoCsrfOwnershipTest extends TestCase
{
    /** @var array<string,mixed>|null */
    private ?array $sessionBackup = null;

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if ($this->sessionBackup !== null) {
            $_SESSION = $this->sessionBackup;
        }
    }

    public function testVerifyCsrfTokenValidoAusenteInvalido(): void
    {
        $ctrl = new class extends BaseController {
            public function check(?string $token): bool
            {
                return $this->verifyCsrfToken($token ?? '');
            }
        };
        $_SESSION['csrf_token'] = 'token-valido-abc';
        $this->assertTrue($ctrl->check('token-valido-abc'));
        $this->assertFalse($ctrl->check(''));
        $this->assertFalse($ctrl->check(null));
        $this->assertFalse($ctrl->check('token-errado'));
    }

    public function testEscritasLegadoExigemVerifyCsrfNoCodigo(): void
    {
        $src = file_get_contents(
            dirname(__DIR__, 2) . '/app/Modulos/redacoes/Controllers/AlunoRedacaoLegadoController.php'
        );
        $this->assertNotFalse($src);
        foreach (['function salvarRascunho', 'function excluirRascunho', 'function ocultar'] as $metodo) {
            $pos = strpos($src, $metodo);
            $this->assertNotFalse($pos, $metodo);
            $trecho = substr($src, $pos, 400);
            $this->assertStringContainsString('verifyCsrfToken', $trecho, $metodo);
        }
    }

    public function testViewsEnviamTokenNasEscritas(): void
    {
        $base = dirname(__DIR__, 2) . '/app/Modulos/redacoes/Views/aluno';
        $essays = file_get_contents($base . '/essays.php');
        $this->assertStringContainsString("formData.append('_token'", $essays);
        $escrever = file_get_contents($base . '/escrever-redacao.php');
        $this->assertStringContainsString('/redacoes/salvar-rascunho', $escrever);
        $this->assertStringContainsString("formData.append('_token'", $escrever);
        $livre = file_get_contents($base . '/escrever-redacao-livre.php');
        $this->assertStringContainsString("formData.append('_token'", $livre);
    }

    public function testSalvarRascunhoUpdateExigeOwnership(): void
    {
        $db = new class {
            public string $updateSql = '';
            public array $updateParams = [];
            public function fetch(string $sql = '', array $params = [])
            {
                if (str_contains($sql, "SHOW COLUMNS") && str_contains($sql, 'eh_rascunho')) {
                    return ['Field' => 'eh_rascunho'];
                }
                if (str_contains($sql, "SHOW COLUMNS") && str_contains($sql, 'tempo_escrita')) {
                    return null;
                }
                if (str_contains($sql, 'FROM redacoes') || str_contains($sql, 'eh_rascunho')) {
                    return ['id' => 5, 'aluno_id' => 10];
                }
                return null;
            }
            public function update(string $sql = '', array $params = [])
            {
                $this->updateSql = $sql;
                $this->updateParams = $params;
            }
            public function insert()
            {
                return 0;
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $out = (new RedacaoAlunoLegadoService($db))->salvarRascunho(10, [
            'rascunho_id' => 5,
            'titulo' => 'T',
            'conteudo' => 'texto',
        ]);
        $this->assertTrue($out['success']);
        $this->assertStringContainsString('aluno_id = :aluno_id', $db->updateSql);
        $this->assertSame(10, $db->updateParams['aluno_id']);
    }

    public function testSalvarRascunhoNegaRascunhoDeOutroAluno(): void
    {
        $db = new class {
            public int $updates = 0;
            public int $inserts = 0;
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
            public function update()
            {
                $this->updates++;
            }
            public function insert()
            {
                $this->inserts++;
                return 0;
            }
            public function fetchAll()
            {
                return [];
            }
        };
        try {
            (new RedacaoAlunoLegadoService($db))->salvarRascunho(10, [
                'rascunho_id' => 99,
                'titulo' => 'T',
                'conteudo' => 'texto',
            ]);
            $this->fail('Esperava RuntimeException por ownership');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('não encontrado', $e->getMessage());
        }
        $this->assertSame(0, $db->updates);
        $this->assertSame(0, $db->inserts);
    }

    public function testRotasHubESalvarNoModulo(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/app/Modulos/redacoes/routes.php');
        $this->assertStringContainsString('AlunoRedacaoLegadoController@hub', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@salvarRascunho', $routes);
        $this->assertStringNotContainsString('StudentController@essays', $routes);
        $this->assertStringNotContainsString('StudentController@salvarRascunho', $routes);
    }
}
