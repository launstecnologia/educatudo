<?php

use App\Modulos\ModelosDocumentos\Services\AtaCompletaTurmaService;
use PHPUnit\Framework\TestCase;

final class AtaCompletaTurmaServiceTest extends TestCase
{
    public function testTurmaPequenaCabeNumaFolhaSemDuplicarAluno(): void
    {
        $html = (new AtaCompletaTurmaService())->html($this->contexto($this->alunos(5), 3));

        $this->assertSame(1, substr_count($html, 'class="folha'));
        $this->assertStringContainsString('Página 1 de 1', $html);
        $this->assertSame(1, substr_count($html, 'class="assinaturas"'));
        $this->assertSame(1, substr_count($html, '>Ana 1<'));
        $this->assertStringNotContainsString('cada aluno ocupa uma página', $html);
    }

    public function testTrintaECemAlunosQuebramPaginaENaoOmitemNinguem(): void
    {
        $svc = new AtaCompletaTurmaService();
        foreach ([30, 50, 100] as $quantidade) {
            $html = $svc->html($this->contexto($this->alunos($quantidade), 2));
            $this->assertGreaterThan(1, substr_count($html, 'class="folha'), (string) $quantidade);
            $this->assertSame($quantidade, substr_count($html, 'td class="nome"'), (string) $quantidade);
            $this->assertSame(1, substr_count($html, 'class="assinaturas"'), (string) $quantidade);
            $this->assertStringContainsString('Página 1 de', $html);
        }
    }

    public function testMuitasDisciplinasQuebramNaHorizontalERepetemOAluno(): void
    {
        $linhas = [];
        for ($i = 1; $i <= 5; $i++) {
            $linhas[] = [
                'aluno' => ['id' => $i, 'nome' => 'Ana ' . $i, 'ra' => (string) $i],
                'situacao' => 'aprovado',
                'rotulo' => 'Aprovado',
                'frequencia' => ['percentual' => 90],
            ];
        }
        $html = (new AtaCompletaTurmaService())->html($this->contexto($linhas, 12));

        $this->assertSame(2, substr_count($html, 'class="folha'));
        $this->assertSame(2, substr_count($html, '>Ana 1<'));
        $this->assertStringContainsString('>PORT<', $html);
        $this->assertStringContainsString('Página 1 de 2', $html);
        $this->assertStringContainsString('Página 2 de 2', $html);
        $this->assertSame(5, substr_count($html, 'td class="freq"'));
        $this->assertStringContainsString('Língua Portuguesa', $html);
        $partes = explode('class="folha', $html);
        $this->assertStringNotContainsString('class="freq"', $partes[1]);
        $this->assertStringContainsString('class="freq"', $partes[2]);
    }

    public function testNotaConceitualNumericaETotaisSemContarDuasVezes(): void
    {
        $linhas = [
            $this->linha(1, 'Bia', 'aprovado', 'Aprovado', 9.5, null),
            $this->linha(2, 'Caio', 'aprovado_conselho', 'Aprovado pelo Conselho', null, 'B'),
            $this->linha(3, 'Davi', 'reprovado_rendimento', 'Reprovado por rendimento', 4.0, null),
            $this->linha(4, 'Eva', 'aprovado', 'Aprovado', 8.0, null, true),
            $this->linha(5, 'Ana', 'progressao_parcial', 'Progressão parcial/Dependência', 6.0, null),
            $this->linha(5, 'Ana duplicada', 'aprovado', 'Aprovado', 10, null),
            $this->linha(6, 'Fabio', 'desistente', 'Desistente/Cancelado', null, null),
        ];
        $svc = new AtaCompletaTurmaService();
        $html = $svc->html($this->contexto($linhas, 1));
        $totais = $svc->totais($linhas);

        $this->assertSame(6, $totais['total']);
        $this->assertSame(2, $totais['aprovados']);
        $this->assertSame(1, $totais['retidos']);
        $this->assertSame(1, $totais['transferidos']);
        $this->assertSame(1, $totais['progressao']);
        $this->assertSame(1, $totais['cancelados']);
        $this->assertStringContainsString('>9,5<', $html);
        $this->assertStringContainsString('>B<', $html);
        $this->assertStringNotContainsString('Ana duplicada', $html);
        $this->assertSame(1, substr_count($html, 'class="assinaturas"'));
    }

    public function testSiglaDoCadastroDoGlossarioEDoNome(): void
    {
        $linhas = [[
            'aluno' => ['id' => 1, 'nome' => 'Ana', 'ra' => '1'],
            'situacao' => 'aprovado',
            'rotulo' => 'Aprovado',
            'frequencia' => ['percentual' => 90],
            'componentes' => [
                ['materia_id' => 10, 'materia_nome' => 'Língua Portuguesa', 'media_final' => 8, 'ordem' => 1],
                ['materia_id' => 11, 'materia_nome' => 'Geografia', 'media_final' => 7, 'ordem' => 2],
                ['materia_id' => 12, 'materia_nome' => 'Educação Física', 'media_final' => 10, 'ordem' => 3],
            ],
        ]];
        $html = (new AtaCompletaTurmaService())->html([
            'linhas' => $linhas,
            'ano_letivo' => '2025',
            'escola_nome' => 'Escola Teste',
            'turma_nome' => '1º A',
            'siglas_cadastro' => [10 => 'PORT'],
            'glossario' => [['slug' => 'geografia', 'nome' => 'Geografia', 'sigla' => 'GEO']],
        ]);

        $this->assertStringContainsString('>PORT<', $html);
        $this->assertStringContainsString('>GEO<', $html);
        $this->assertStringContainsString('>EF<', $html);
        $this->assertStringContainsString('PORT — Língua Portuguesa', $html);
    }

    public function testJanelasCobremTodosOsAlunosSemSobrepor(): void
    {
        foreach ([5, 9, 30, 50, 100] as $total) {
            $visto = [];
            foreach (AtaCompletaTurmaService::janelas($total) as $janela) {
                for ($i = $janela[0]; $i < $janela[0] + $janela[1]; $i++) {
                    $this->assertArrayNotHasKey($i, $visto, (string) $total);
                    $visto[$i] = true;
                }
            }
            $this->assertCount($total, $visto, (string) $total);
        }
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return array<string,mixed>
     */
    private function contexto(array $linhas, int $disciplinas): array
    {
        if ($disciplinas > 0) {
            foreach ($linhas as $i => $linha) {
                if (!empty($linha['componentes'])) {
                    continue;
                }
                $comps = [];
                for ($d = 1; $d <= $disciplinas; $d++) {
                    $comps[] = [
                        'materia_id' => $d,
                        'materia_nome' => $d === 1 ? 'Língua Portuguesa' : 'Disciplina ' . $d,
                        'media_final' => 7.5,
                        'ordem' => $d,
                    ];
                }
                $linhas[$i]['componentes'] = $comps;
            }
        }

        return [
            'linhas' => $linhas,
            'ano_letivo' => '2025',
            'escola_nome' => 'Escola Teste',
            'escola_endereco' => 'Rua A, 10',
            'escola_docs' => 'CNPJ 00.000.000/0001-00 | Código INEP 12345678',
            'etapa' => 'Ensino Fundamental',
            'serie' => '5º ano',
            'turma_nome' => '5º A',
            'turno' => 'Manhã',
            'organizacao' => 'Ano letivo',
            'cidade_data' => 'Ribeirão Preto, 16 de novembro de 2026',
            'diretor_nome' => 'Diretora Ana',
            'secretario_nome' => 'Secretário João',
            'siglas_cadastro' => [1 => 'PORT'],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function alunos(int $quantidade): array
    {
        $linhas = [];
        for ($i = 1; $i <= $quantidade; $i++) {
            $linhas[] = $this->linha($i, 'Ana ' . $i, 'aprovado', 'Aprovado', 8, null);
        }

        return $linhas;
    }

    /**
     * @return array<string,mixed>
     */
    private function linha(
        int $id,
        string $nome,
        string $situacao,
        string $rotulo,
        ?float $media,
        ?string $conceito,
        bool $transferido = false
    ): array {
        $comp = [
            'materia_id' => 1,
            'materia_nome' => 'Língua Portuguesa',
            'media_final' => $media,
            'ordem' => 1,
        ];
        if ($conceito !== null) {
            $comp['conceito'] = $conceito;
        }

        return [
            'aluno' => ['id' => $id, 'nome' => $nome, 'ra' => (string) $id, 'transferido' => $transferido ? 1 : 0],
            'situacao' => $transferido ? 'transferido' : $situacao,
            'rotulo' => $transferido ? 'Transferido' : $rotulo,
            'frequencia' => ['percentual' => 92.5],
            'componentes' => [$comp],
        ];
    }
}
