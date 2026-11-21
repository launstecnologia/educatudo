<?php

use App\Modulos\Redacoes\Services\RedacaoAlunoIaService;
use PHPUnit\Framework\TestCase;

/**
 * Etapa 6 — caracterização dos fluxos de IA/créditos do legado /redacoes.
 * Mocks: zero rede OpenAI, zero cobrança real.
 */
final class RedacaoAlunoIaCaracterizacaoTest extends TestCase
{
    private function dbStub(array $overrides = []): object
    {
        return new class ($overrides) {
            public array $overrides;
            public string $lastInsertSql = '';
            public array $lastInsertParams = [];
            public string $lastUpdateSql = '';
            public array $lastUpdateParams = [];
            public int $insertsAcao = 0;
            public function __construct(array $overrides)
            {
                $this->overrides = $overrides;
            }
            public function fetch(string $sql = '', array $params = [])
            {
                if (isset($this->overrides['fetch'])) {
                    return ($this->overrides['fetch'])($sql, $params);
                }
                if (str_contains($sql, 'alunos_acoes_diarias') && str_contains($sql, 'COUNT')) {
                    return ['total' => $this->overrides['uso_diario'] ?? 0];
                }
                if (str_contains($sql, 'config_layout')) {
                    return null;
                }
                if (str_contains($sql, "SHOW COLUMNS")) {
                    return $this->overrides['coluna_eh_rascunho'] ?? null;
                }
                if (str_contains($sql, 'FROM redacoes')) {
                    return $this->overrides['redacao'] ?? null;
                }
                return null;
            }
            public function insert(string $sql = '', array $params = [])
            {
                $this->lastInsertSql = $sql;
                $this->lastInsertParams = $params;
                if (str_contains($sql, 'alunos_acoes_diarias')) {
                    $this->insertsAcao++;
                    return 1;
                }
                return $this->overrides['insert_id'] ?? 77;
            }
            public function update(string $sql = '', array $params = [])
            {
                $this->lastUpdateSql = $sql;
                $this->lastUpdateParams = $params;
            }
            public function fetchAll()
            {
                return [];
            }
        };
    }

    private function openaiStub(?string $generateText = null, ?\Throwable $throw = null, ?string $analyze = null): object
    {
        return new class ($generateText, $throw, $analyze) {
            public int $generateCalls = 0;
            public int $analyzeCalls = 0;
            public int $visionCalls = 0;
            private $text;
            private $throw;
            private $analyze;
            public function __construct($text, $throw, $analyze)
            {
                $this->text = $text;
                $this->throw = $throw;
                $this->analyze = $analyze;
            }
            public function generateText($prompt, $opts = [])
            {
                $this->generateCalls++;
                if ($this->throw) {
                    throw $this->throw;
                }
                return $this->text ?? '{}';
            }
            public function analyzeImage($data, $prompt)
            {
                $this->analyzeCalls++;
                if ($this->throw) {
                    throw $this->throw;
                }
                return $this->analyze ?? 'texto';
            }
            public function transcreverComGoogleVision($data)
            {
                $this->visionCalls++;
                throw new \RuntimeException('vision offline');
            }
        };
    }

    private function creditosStub(?\Throwable $onConsumir = null): object
    {
        return new class ($onConsumir) {
            public int $consumirCalls = 0;
            public int $estornoCalls = 0;
            public array $refs = [];
            private $onConsumir;
            public function __construct($onConsumir)
            {
                $this->onConsumir = $onConsumir;
            }
            public function consumir($tipo, $id, $chave, $ref)
            {
                $this->consumirCalls++;
                $this->refs[] = [$chave, $ref];
                if ($this->onConsumir) {
                    throw $this->onConsumir;
                }
            }
            public function estornarPorReferencia($chave, $ref)
            {
                $this->estornoCalls++;
            }
        };
    }

    public function testCriarRedacaoInsereComAlunoId(): void
    {
        $db = $this->dbStub(['insert_id' => 15]);
        $openai = $this->openaiStub(json_encode([
            'competencia_1' => ['nota' => 160, 'explicacao' => 'ok'],
            'competencia_2' => ['nota' => 160, 'explicacao' => 'ok'],
            'competencia_3' => ['nota' => 160, 'explicacao' => 'ok'],
            'competencia_4' => ['nota' => 160, 'explicacao' => 'ok'],
            'competencia_5' => ['nota' => 160, 'explicacao' => 'ok'],
            'nota_final' => 800,
            'comentarios_gerais' => 'ok',
            'sugestoes_melhoria' => 'ok',
        ]));
        $creditos = $this->creditosStub();
        $svc = new RedacaoAlunoIaService($db, $openai, $creditos, fn () => 0);
        $db->overrides['redacao'] = ['id' => 15, 'titulo' => 'T', 'conteudo' => 'C', 'aluno_id' => 10];
        $out = $svc->criarRedacao(10, ['titulo' => 'T', 'conteudo' => 'C']);
        $this->assertTrue($out['success']);
        $this->assertSame(15, $out['redacao_id']);
        $this->assertSame(10, $db->lastInsertParams['aluno_id']);
        $this->assertSame(0, $creditos->consumirCalls);
    }

    public function testGerarTemaSucessoDebitaERegistraAcao(): void
    {
        $db = $this->dbStub();
        $openai = $this->openaiStub(json_encode([
            'titulo' => 'Tema X',
            'descricao' => 'D',
            'proposta_intervencao' => 'P',
            'contexto' => 'C',
        ]));
        $creditos = $this->creditosStub();
        $svc = new RedacaoAlunoIaService($db, $openai, $creditos, fn () => 5);
        $out = $svc->gerarTema(10, 'meio ambiente', 'ref-fixa');
        $this->assertTrue($out['ok']);
        $this->assertSame('Tema X', $out['tema']['titulo']);
        $this->assertSame(1, $creditos->consumirCalls);
        $this->assertSame(0, $creditos->estornoCalls);
        $this->assertSame(1, $db->insertsAcao);
        $this->assertSame(1, $openai->generateCalls);
    }

    public function testGerarTemaFalhaIaEstornaCredito(): void
    {
        $db = $this->dbStub();
        $openai = $this->openaiStub(null, new \RuntimeException('timeout openai'));
        $creditos = $this->creditosStub();
        $svc = new RedacaoAlunoIaService($db, $openai, $creditos, fn () => 5);
        $out = $svc->gerarTema(10, 'tema', 'ref-fail');
        $this->assertFalse($out['ok']);
        $this->assertSame('ia', $out['codigo']);
        $this->assertSame(1, $creditos->consumirCalls);
        $this->assertSame(1, $creditos->estornoCalls);
        $this->assertSame(0, $db->insertsAcao);
    }

    public function testGerarTemaJsonInvalidoUsaFallbackSemEstorno(): void
    {
        $db = $this->dbStub();
        $openai = $this->openaiStub('texto livre não json');
        $creditos = $this->creditosStub();
        $svc = new RedacaoAlunoIaService($db, $openai, $creditos, fn () => 5);
        $out = $svc->gerarTema(10, 'educação', 'ref-json');
        $this->assertTrue($out['ok']);
        $this->assertStringContainsString('educação', $out['tema']['titulo']);
        $this->assertSame(0, $creditos->estornoCalls);
        $this->assertSame(1, $db->insertsAcao);
    }

    public function testGerarTemaSaldoInsuficienteNaoChamaIa(): void
    {
        $db = $this->dbStub();
        $openai = $this->openaiStub('{}');
        $creditos = $this->creditosStub(new \RuntimeException('TudiCoins insuficientes'));
        $svc = new RedacaoAlunoIaService($db, $openai, $creditos, fn () => 5);
        $out = $svc->gerarTema(10, 'tema', 'ref-saldo');
        $this->assertFalse($out['ok']);
        $this->assertSame('creditos', $out['codigo']);
        $this->assertSame(0, $openai->generateCalls);
        $this->assertSame(0, $creditos->estornoCalls);
    }

    public function testGerarTemaLimiteDiario(): void
    {
        $db = $this->dbStub(['uso_diario' => 3]);
        $openai = $this->openaiStub('{}');
        $creditos = $this->creditosStub();
        $svc = new RedacaoAlunoIaService($db, $openai, $creditos, fn () => 3);
        $out = $svc->gerarTema(10, 'tema');
        $this->assertFalse($out['ok']);
        $this->assertSame('limite', $out['codigo']);
        $this->assertSame(0, $creditos->consumirCalls);
        $this->assertSame(0, $openai->generateCalls);
    }

    public function testGerarTemaRequisicaoRepetidaGeraNovaReferenciaENovoDebito(): void
    {
        $db = $this->dbStub();
        $openai = $this->openaiStub(json_encode(['titulo' => 'A', 'descricao' => 'd', 'proposta_intervencao' => 'p', 'contexto' => 'c']));
        $creditos = $this->creditosStub();
        $svc = new RedacaoAlunoIaService($db, $openai, $creditos, fn () => 10);
        $a = $svc->gerarTema(10, 'tema', 'ref-1');
        $b = $svc->gerarTema(10, 'tema', 'ref-2');
        $this->assertTrue($a['ok'] && $b['ok']);
        $this->assertSame(2, $creditos->consumirCalls);
        $this->assertSame(2, $openai->generateCalls);
        // Ausência de idempotência caracterizada (comportamento legado).
    }

    public function testCorrigirSucessoComOwnershipNoUpdate(): void
    {
        $correcao = [
            'competencia_1' => ['nota' => 180, 'explicacao' => 'a'],
            'competencia_2' => ['nota' => 180, 'explicacao' => 'a'],
            'competencia_3' => ['nota' => 180, 'explicacao' => 'a'],
            'competencia_4' => ['nota' => 180, 'explicacao' => 'a'],
            'competencia_5' => ['nota' => 180, 'explicacao' => 'a'],
            'nota_final' => 900,
            'comentarios_gerais' => 'bom',
            'sugestoes_melhoria' => 'ok',
        ];
        $db = $this->dbStub([
            'coluna_eh_rascunho' => ['Field' => 'eh_rascunho'],
            'redacao' => ['id' => 5, 'titulo' => 'T', 'conteudo' => 'texto longo', 'aluno_id' => 10, 'eh_rascunho' => 1, 'tema_texto' => null],
            'insert_id' => 5,
        ]);
        $openai = $this->openaiStub(json_encode($correcao));
        $creditos = $this->creditosStub();
        $svc = new RedacaoAlunoIaService($db, $openai, $creditos, fn () => 0);
        $out = $svc->corrigir(10, ['redacao_id' => 5, 'conteudo' => 'texto longo', 'titulo' => 'T']);
        $this->assertTrue($out['ok']);
        $this->assertStringContainsString('aluno_id = :aluno_id', $db->lastUpdateSql);
        $this->assertSame(10, $db->lastUpdateParams['aluno_id']);
        $this->assertSame(0, $creditos->consumirCalls); // legado: sem TudiCoins em corrigirRedacao
        $this->assertSame(0, $db->insertsAcao); // legado: não registra ação diária
    }

    public function testCorrigirNegaRedacaoDeOutroAluno(): void
    {
        $db = $this->dbStub([
            'coluna_eh_rascunho' => ['Field' => 'eh_rascunho'],
            'fetch' => function (string $sql, array $params) {
                if (str_contains($sql, "SHOW COLUMNS")) {
                    return ['Field' => 'eh_rascunho'];
                }
                if (str_contains($sql, 'config_layout')) {
                    return null;
                }
                // Ownership: não encontra rascunho do aluno
                return null;
            },
        ]);
        $openai = $this->openaiStub('{}');
        $svc = new RedacaoAlunoIaService($db, $openai, $this->creditosStub(), fn () => 0);
        $out = $svc->corrigir(10, ['redacao_id' => 99, 'conteudo' => 'x']);
        $this->assertFalse($out['ok']);
        $this->assertContains($out['codigo'], ['ownership', 'persistencia']);
        $this->assertSame(0, $openai->generateCalls);
    }

    public function testCorrigirRespostaInvalidaDaIa(): void
    {
        $db = $this->dbStub([
            'coluna_eh_rascunho' => null,
            'redacao' => ['id' => 8, 'titulo' => 'T', 'conteudo' => 'corpo', 'aluno_id' => 10],
            'insert_id' => 8,
        ]);
        // fetch for insert path then for ownership select
        $db = new class {
            public string $lastUpdateSql = '';
            public int $updates = 0;
            public function fetch(string $sql = '', array $params = [])
            {
                if (str_contains($sql, "SHOW COLUMNS")) {
                    return null;
                }
                if (str_contains($sql, 'config_layout')) {
                    return null;
                }
                if (str_contains($sql, 'FROM redacoes')) {
                    return ['id' => 8, 'titulo' => 'T', 'conteudo' => 'corpo', 'aluno_id' => 10];
                }
                return null;
            }
            public function insert(string $sql = '', array $params = [])
            {
                return 8;
            }
            public function update(string $sql = '', array $params = [])
            {
                $this->updates++;
                $this->lastUpdateSql = $sql;
            }
        };
        $openai = $this->openaiStub('nao-e-json');
        $svc = new RedacaoAlunoIaService($db, $openai, $this->creditosStub(), fn () => 0);
        $out = $svc->corrigir(10, ['conteudo' => 'corpo', 'titulo' => 'T']);
        $this->assertFalse($out['ok']);
        $this->assertSame('ia', $out['codigo']);
        // Persistência do texto pode ter ocorrido; feedback final não.
        $this->assertStringNotContainsString('feedback_ia', $db->lastUpdateSql);
    }

    public function testCorrigirTimeoutTratadoComoFalhaIa(): void
    {
        $db = new class {
            public function fetch(string $sql = '', array $params = [])
            {
                if (str_contains($sql, "SHOW COLUMNS")) {
                    return null;
                }
                if (str_contains($sql, 'config_layout')) {
                    return null;
                }
                if (str_contains($sql, 'FROM redacoes')) {
                    return ['id' => 8, 'titulo' => 'T', 'conteudo' => 'corpo', 'aluno_id' => 10];
                }
                return null;
            }
            public function insert()
            {
                return 8;
            }
            public function update()
            {
            }
        };
        $openai = $this->openaiStub(null, new \RuntimeException('timed out'));
        $svc = new RedacaoAlunoIaService($db, $openai, $this->creditosStub(), fn () => 0);
        $out = $svc->corrigir(10, ['conteudo' => 'corpo']);
        $this->assertFalse($out['ok']);
        $this->assertSame('ia', $out['codigo']);
    }

    public function testTranscreverSucessoSemCreditos(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'fake-image-bytes');
        $db = $this->dbStub();
        $openai = $this->openaiStub(null, null, 'Texto da redação');
        $creditos = $this->creditosStub();
        $svc = new RedacaoAlunoIaService($db, $openai, $creditos, fn () => 0);
        $out = $svc->transcreverImagem(10, [
            'tmp_name' => $tmp,
            'name' => 'a.jpg',
            'type' => 'image/jpeg',
            'size' => 100,
            'error' => UPLOAD_ERR_OK,
        ], null);
        @unlink($tmp);
        $this->assertTrue($out['ok']);
        $this->assertSame('Texto da redação', $out['texto_transcrito']);
        $this->assertSame(0, $creditos->consumirCalls);
    }

    public function testTranscreverTimeout(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'x');
        $openai = $this->openaiStub(null, new \RuntimeException('timeout [tipo: timeout]'));
        $svc = new RedacaoAlunoIaService($this->dbStub(), $openai, $this->creditosStub(), fn () => 0);
        $out = $svc->transcreverImagem(10, [
            'tmp_name' => $tmp,
            'name' => 'a.jpg',
            'type' => 'image/jpeg',
            'size' => 10,
            'error' => UPLOAD_ERR_OK,
        ], null);
        @unlink($tmp);
        $this->assertFalse($out['ok']);
        $this->assertSame('timeout', $out['tipo_erro']);
    }

    public function testIsolamentoTenantSemEscolaIdNasQueries(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/app/Modulos/redacoes/Services/RedacaoAlunoIaService.php');
        $this->assertStringNotContainsString('escola_id', $src);
        $this->assertStringContainsString('aluno_id', $src);
    }

    public function testChaveCreditoTemaPreservada(): void
    {
        $this->assertSame('redacao_gerar_tema_aluno', RedacaoAlunoIaService::CHAVE_CREDITO_TEMA);
    }
}
