<?php

use App\Dominio\Pessoas\Services\AlunoLimiteDiarioService;
use PHPUnit\Framework\TestCase;

/** Etapa 8 — limites diários compartilhados (independentes de TudiCoins). */
final class AlunoLimiteDiarioServiceTest extends TestCase
{
    public function testDashboardUsaContagemAgrupada(): void
    {
        $db = new class {
            public function fetchAll()
            {
                return [
                    ['acao' => 'gerar_tema_redacao', 'total' => 2],
                    ['acao' => 'exercicios', 'total' => 4],
                ];
            }
            public function fetch()
            {
                return null;
            }
            public function insert()
            {
                return 1;
            }
        };
        $svc = new AlunoLimiteDiarioService(
            $db,
            static fn (string $k, string $d) => $d,
            static fn (string $k, string $d) => '0'
        );
        $out = $svc->listarParaDashboard(10);
        $this->assertSame(2, $out['tema']['usado']);
        $this->assertSame(4, $out['exercicios']['usado']);
        $this->assertSame(0, $out['chat']['usado']);
        $this->assertSame('3', $out['tema']['limite']);
    }

    public function testPodeExecutarQuandoLimiteZero(): void
    {
        $db = new class {
            public int $fetches = 0;
            public function fetch()
            {
                $this->fetches++;
                return ['total' => 99];
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $svc = new AlunoLimiteDiarioService(
            $db,
            null,
            static fn () => '0'
        );
        $r = $svc->podeExecutarAcao(10, 'gerar_tema_redacao');
        $this->assertTrue($r['pode']);
        $this->assertSame(0, $db->fetches);
    }

    public function testPodeExecutarNegaQuandoAtingeLimite(): void
    {
        $db = new class {
            public function fetch()
            {
                return ['total' => 3];
            }
            public function fetchAll()
            {
                return [];
            }
        };
        $svc = new AlunoLimiteDiarioService(
            $db,
            null,
            static fn () => '3'
        );
        $r = $svc->podeExecutarAcao(10, 'gerar_tema_redacao');
        $this->assertFalse($r['pode']);
        $this->assertStringContainsString('limite diário', $r['mensagem']);
    }

    public function testRegistrarAcaoInsere(): void
    {
        $db = new class {
            public string $sql = '';
            public array $params = [];
            public function insert(string $sql = '', array $params = [])
            {
                $this->sql = $sql;
                $this->params = $params;
                return 1;
            }
            public function fetch()
            {
                return null;
            }
            public function fetchAll()
            {
                return [];
            }
        };
        (new AlunoLimiteDiarioService($db))->registrarAcao(10, 'exercicios');
        $this->assertStringContainsString('alunos_acoes_diarias', $db->sql);
        $this->assertSame(10, $db->params['aluno_id']);
        $this->assertSame('exercicios', $db->params['acao']);
    }

    public function testSemEscolaId(): void
    {
        $src = file_get_contents(
            dirname(__DIR__, 2) . '/app/Dominio/Pessoas/Services/AlunoLimiteDiarioService.php'
        );
        $this->assertStringNotContainsString('escola_id', $src);
    }
}
