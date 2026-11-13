<?php

require_once dirname(__DIR__) . '/app/Modulos/provas/Services/Assistente/AssistenteResolvedorAluno.php';

use App\Modulos\Provas\Services\Assistente\AssistenteResolvedorAluno;
use PHPUnit\Framework\TestCase;

class AssistenteResolvedorAlunoTest extends TestCase
{
    /** @var list<array<string,mixed>> */
    private array $alunos = [
        ['id' => 1, 'nome' => 'Maria Clara Souza', 'ra' => '24254', 'turma_nome' => '2ª B'],
        ['id' => 2, 'nome' => 'Maria Eduarda Lima', 'ra' => 'SM3001', 'turma_nome' => '1ª A'],
        ['id' => 3, 'nome' => 'Ana Luiza Prado', 'ra' => '24999', 'turma_nome' => '2ª B'],
        ['id' => 4, 'nome' => 'João Silva', 'ra' => '11111', 'turma_nome' => '1ª A'],
    ];

    /** @var list<array{0:string,1:?string}> */
    private array $buscas = [];

    private function resolvedor(): AssistenteResolvedorAluno
    {
        $this->buscas = [];
        return new AssistenteResolvedorAluno(function (string $termo, ?string $turma): array {
            $this->buscas[] = [$termo, $turma];
            if (preg_match('/^ra\s+(\S+)$/i', $termo, $m) === 1) {
                return array_values(array_filter(
                    $this->alunos,
                    static fn(array $a) => stripos((string) $a['ra'], $m[1]) !== false
                ));
            }
            $tokens = preg_split('/\s+/', AssistenteResolvedorAluno::normalizar($termo)) ?: [];
            return array_values(array_filter($this->alunos, static function (array $a) use ($tokens, $turma): bool {
                $nome = AssistenteResolvedorAluno::normalizar((string) $a['nome']);
                foreach ($tokens as $t) {
                    if (!str_contains($nome, $t)) {
                        return false;
                    }
                }
                return $turma === null || str_contains(
                    AssistenteResolvedorAluno::normalizar((string) $a['turma_nome']),
                    AssistenteResolvedorAluno::normalizar($turma)
                );
            }));
        });
    }

    public function testRaSemDigitoViraNome(): void
    {
        $r = $this->resolvedor()->resolver(['ra' => 'Maria'], null);
        $this->assertSame('candidatos', $r['status']);
        $this->assertSame(['Maria', null], $this->buscas[0]);
        $this->assertCount(2, $r['candidatos']);
    }

    public function testRaAlfanumericoComDigito(): void
    {
        $r = $this->resolvedor()->resolver(['ra' => 'SM3001'], null);
        $this->assertSame('ok', $r['status']);
        $this->assertSame(2, $r['aluno']['id']);
    }

    public function testNomeExplicitoGanhaDoAlunoDaConversa(): void
    {
        $ativo = ['id' => 3, 'nome' => 'Ana Luiza Prado'];
        $r = $this->resolvedor()->resolver(['aluno_nome' => 'João', 'aluno_da_conversa' => true], $ativo);
        $this->assertSame('ok', $r['status']);
        $this->assertSame(4, $r['aluno']['id']);
    }

    public function testNomeAmbiguoNaoChutaAlunoDaConversaDiferente(): void
    {
        $ativo = ['id' => 3, 'nome' => 'Ana Luiza Prado'];
        $r = $this->resolvedor()->resolver(['aluno_nome' => 'Maria'], $ativo);
        $this->assertSame('candidatos', $r['status']);
        $ids = array_map(static fn(array $a) => $a['id'], $r['candidatos']);
        $this->assertNotContains(3, $ids);
    }

    public function testNomeParcialDoAlunoEmFocoUsaEle(): void
    {
        $ativo = ['id' => 1, 'nome' => 'Maria Clara Souza'];
        $r = $this->resolvedor()->resolver(['aluno_nome' => 'Maria'], $ativo);
        $this->assertSame('ok', $r['status']);
        $this->assertSame(1, $r['aluno']['id']);
    }

    public function testPronomeUsaAlunoDaConversa(): void
    {
        $ativo = ['id' => 3, 'nome' => 'Ana Luiza Prado'];
        $r = $this->resolvedor()->resolver(['aluno_nome' => 'dela'], $ativo);
        $this->assertSame('ok', $r['status']);
        $this->assertSame('conversa', $r['origem']);
        $this->assertSame([], $this->buscas);
    }

    public function testSemAlunoNemConversaPedeNome(): void
    {
        $r = $this->resolvedor()->resolver(['aluno_da_conversa' => true], null);
        $this->assertSame('sem_aluno', $r['status']);
    }

    public function testNomeInexistenteNaoUsaConversa(): void
    {
        $ativo = ['id' => 3, 'nome' => 'Ana Luiza Prado'];
        $r = $this->resolvedor()->resolver(['aluno_nome' => 'Zuleica'], $ativo);
        $this->assertSame('nenhum', $r['status']);
        $this->assertStringContainsString('Zuleica', $r['mensagem']);
    }

    public function testNomeCompletoExatoDesempata(): void
    {
        $this->alunos[] = ['id' => 5, 'nome' => 'João Silva Neto', 'ra' => '55555', 'turma_nome' => '3ª C'];
        $r = $this->resolvedor()->resolver(['aluno_nome' => 'joão silva'], null);
        $this->assertSame('ok', $r['status']);
        $this->assertSame(4, $r['aluno']['id']);
    }

    public function testTurmaSemResultadoTentaSemTurma(): void
    {
        $r = $this->resolvedor()->resolver(['aluno_nome' => 'Ana Luiza', 'turma' => '3ª C'], null);
        $this->assertSame('ok', $r['status']);
        $this->assertSame(3, $r['aluno']['id']);
        $this->assertSame(['Ana Luiza', null], $this->buscas[1]);
    }

    public function testCodigoNoCampoNomeViraRa(): void
    {
        $r = $this->resolvedor()->resolver(['aluno_nome' => '24254'], null);
        $this->assertSame('ok', $r['status']);
        $this->assertSame('ra', $r['origem']);
    }

    public function testRaValido(): void
    {
        $this->assertTrue(AssistenteResolvedorAluno::raValido('24254'));
        $this->assertTrue(AssistenteResolvedorAluno::raValido('SM24254'));
        $this->assertFalse(AssistenteResolvedorAluno::raValido('MARIA'));
        $this->assertFalse(AssistenteResolvedorAluno::raValido('12'));
    }
}
