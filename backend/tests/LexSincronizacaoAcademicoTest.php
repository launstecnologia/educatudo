<?php

use App\Modulos\Lex\Services\LexClassificadorAcademico;
use App\Modulos\Lex\Services\LexClassificadorUsuarios;
use App\Modulos\Lex\Services\LexPlanoAcademico;
use App\Modulos\Lex\Services\LexPlanoUsuarios;
use PHPUnit\Framework\TestCase;

class LexSincronizacaoAcademicoTest extends TestCase
{
    public function testCriaCursoSerieTurmaEMatricula(): void
    {
        $plano = $this->plano($this->snapshot(), $this->estado());
        $academico = $plano['academico'];

        $this->assertSame(1, $academico['contagens']['curso']['criar']);
        $this->assertSame(1, $academico['contagens']['serie']['criar']);
        $this->assertSame(1, $academico['contagens']['turma']['criar']);
        $this->assertSame(1, $academico['contagens']['matricula']['criar']);
        $this->assertSame([2026], $academico['anos']);

        $turma = $this->acao($academico['criar'], 'turma', 'g-1');
        $this->assertSame('6A', $turma['nome']);
        $this->assertSame('6º ano', $turma['serie_nome']);
        $this->assertSame('Manhã', $turma['turno']);
        $this->assertSame('gr-1', $turma['serie_lex']);
        $this->assertSame('seg-1', $turma['curso_lex']);

        $this->assertSame('g-1', $academico['alunos'][0]['turma_lex']);
        $this->assertSame('6º ano', $academico['alunos'][0]['serie']);
        $this->assertSame(['g-1'], $academico['professores'][0]['turmas_lex']);
        $this->assertSame([], $academico['inativar']);
    }

    public function testRepeticaoAtualizaENaoCria(): void
    {
        $estado = $this->estado();
        $estado['vinculos']['curso']['seg-1'] = ['id_local' => 3, 'status' => 'ativo'];
        $estado['vinculos']['serie']['gr-1'] = ['id_local' => 4, 'status' => 'ativo'];
        $estado['vinculos']['turma']['g-1'] = ['id_local' => 5, 'status' => 'ativo'];
        $estado['vinculos']['matricula']['u-aluno|g-1'] = ['id_local' => 6, 'status' => 'ativo'];
        $estado['anos'][2026] = 8;
        $estado['matriculas']['1|5|8'] = 6;

        $academico = $this->plano($this->snapshot(), $estado)['academico'];
        $this->assertSame([], array_values(array_filter(
            $academico['criar'],
            static fn (array $item): bool => in_array($item['tipo'], ['curso', 'serie', 'turma', 'matricula'], true)
        )));
        $this->assertSame(1, $academico['contagens']['turma']['atualizar']);
        $this->assertSame(1, $academico['contagens']['matricula']['atualizar']);
    }

    public function testMatriculaAusenteEntraNaInativacao(): void
    {
        $estado = $this->estado();
        $estado['vinculos']['matricula']['u-aluno|g-antiga'] = ['id_local' => 9, 'status' => 'ativo'];
        $academico = $this->plano($this->snapshot(), $estado)['academico'];
        $this->assertTrue($academico['pode_inativar']);
        $this->assertSame('u-aluno|g-antiga', $academico['inativar_matriculas'][0]['id_lex']);
        $this->assertSame(9, $academico['inativar_matriculas'][0]['id_local']);
    }

    public function testSerieJaExistenteNoCursoBloqueiaESuspendeTudo(): void
    {
        $estado = $this->estado();
        $estado['vinculos']['curso']['seg-1'] = ['id_local' => 3, 'status' => 'ativo'];
        $estado['serie_nomes']['3|6º ano'] = 99;
        $estado['vinculos']['aluno']['sumiu'] = ['id_local' => 7, 'status' => 'ativo'];

        $plano = $this->plano($this->snapshot(), $estado);
        $this->assertFalse($plano['pode_inativar']);
        $this->assertSame([], $plano['inativar']);
        $this->assertSame([], $plano['academico']['inativar']);
        $this->assertGreaterThan(0, $plano['inativacao_suspensa']);
        $mensagens = array_column($plano['conflitos'], 'mensagem');
        $this->assertContains('Série já existe neste curso.', $mensagens);
    }

    public function testGrupoDeOutraEscolaEGrupoQueNaoEhTurmaFicamDeFora(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['payload']['schools'][0]['groups'][] = [
            'id' => 'g-projeto',
            'type' => 'project',
            'name' => 'Feira',
            'schoolYear' => 2026,
            'users' => [],
        ];
        $classificacao = LexClassificadorAcademico::classificar($snapshot, 'tenant-1', 'school-a', 'ext-a');
        $this->assertArrayNotHasKey('g-2', $classificacao['turmas']);
        $this->assertArrayNotHasKey('g-projeto', $classificacao['turmas']);
        $this->assertSame('Grupo não importado nesta etapa.', $classificacao['ignorados'][0]['mensagem']);
    }

    public function testSerieSemSegmentoNaoCriaTurma(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['payload']['schools'][0]['structures'] = [
            ['id' => 'gr-1', 'name' => '6º ano', 'type' => 'grade'],
        ];
        $plano = $this->plano($snapshot, $this->estado());
        $this->assertSame([], array_values(array_filter($plano['academico']['criar'], static fn (array $item): bool => $item['tipo'] === 'turma')));
        $this->assertFalse($plano['academico']['pode_inativar']);
        $mensagens = array_column($plano['conflitos'], 'mensagem');
        $this->assertContains('Série sem segmento.', $mensagens);
    }

    public function testDuasTurmasEscolheADoAnoMaior(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['payload']['schools'][0]['structures'][0]['structures'][] = [
            'id' => 'gr-2',
            'name' => '7º ano',
            'type' => 'grade',
        ];
        $snapshot['payload']['schools'][0]['groups'][] = [
            'id' => 'g-2a',
            'type' => 'class',
            'name' => '7A',
            'schoolYear' => 2027,
            'structures' => ['gr-2'],
            'users' => [
                ['userId' => 'u-aluno', 'profileId' => 'p-stu', 'subjects' => []],
            ],
        ];
        $academico = $this->plano($snapshot, $this->estado())['academico'];
        $this->assertSame(2, $academico['contagens']['matricula']['criar']);
        $this->assertSame('g-2a', $academico['alunos'][0]['turma_lex']);
        $this->assertSame('7º ano', $academico['alunos'][0]['serie']);
    }

    public function testProfessorNaoImportadoNaoImpedeATurma(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['payload']['users'] = array_values(array_filter(
            $snapshot['payload']['users'],
            static fn (array $usuario): bool => $usuario['id'] !== 'u-prof'
        ));
        $plano = $this->plano($snapshot, $this->estado());
        $this->assertSame(1, $plano['academico']['contagens']['turma']['criar']);
        $this->assertTrue($plano['academico']['pode_inativar']);
        $mensagens = array_column($plano['conflitos'], 'mensagem');
        $this->assertContains('Professor da turma não foi importado.', $mensagens);
    }

    /**
     * @param array<string,mixed> $snapshot
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function plano(array $snapshot, array $estado): array
    {
        $pessoas = LexClassificadorUsuarios::classificar($snapshot, 'tenant-1', 'school-a', 'ext-a');
        $planoPessoas = LexPlanoUsuarios::montar($pessoas, $estado);
        $academico = LexClassificadorAcademico::classificar($snapshot, 'tenant-1', 'school-a', 'ext-a');
        return LexPlanoAcademico::combinar(
            $planoPessoas,
            LexPlanoAcademico::montar($academico, $estado, $planoPessoas)
        );
    }

    /**
     * @param list<array<string,mixed>> $acoes
     * @return array<string,mixed>
     */
    private function acao(array $acoes, string $tipo, string $idLex): array
    {
        foreach ($acoes as $acao) {
            if (($acao['tipo'] ?? '') === $tipo && ($acao['id_lex'] ?? '') === $idLex) {
                return $acao;
            }
        }
        $this->fail('Ação não encontrada: ' . $tipo . ' ' . $idLex);
    }

    /**
     * @return array<string,mixed>
     */
    private function estado(): array
    {
        return [
            'vinculos' => [
                'aluno' => [],
                'professor' => [],
                'responsavel' => [],
                'usuario' => [],
                'aluno_responsavel' => [],
                'curso' => [],
                'serie' => [],
                'turma' => [],
                'matricula' => [],
            ],
            'ra' => [],
            'nickname' => [],
            'codigo_prof' => [],
            'email_usuario' => [],
            'anos' => [],
            'serie_nomes' => [],
            'matriculas' => [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshot(): array
    {
        return [
            'header' => ['syncId' => 'sync-1', 'syncDate' => '2026-10-26T19:47:08Z'],
            'payload' => [
                'tenantId' => 'tenant-1',
                'profiles' => [
                    ['id' => 'p-stu', 'name' => 'Aluno', 'type' => 'student'],
                    ['id' => 'p-tea', 'name' => 'Professor', 'type' => 'teacher'],
                    ['id' => 'p-spo', 'name' => 'Responsável', 'type' => 'sponsor'],
                    ['id' => 'p-man', 'name' => 'Gestor', 'type' => 'manager'],
                ],
                'users' => [
                    ['id' => 'u-aluno', 'accountId' => 'acc-aluno', 'name' => 'Ana', 'login' => 'aluno.login', 'sponsorsId' => ['u-pai']],
                    ['id' => 'u-pai', 'accountId' => 'acc-pai', 'name' => 'Paulo', 'login' => 'paulo.login'],
                    ['id' => 'u-prof', 'accountId' => 'acc-prof', 'name' => 'Helena', 'email' => 'helena@escola.test', 'login' => 'prof.login'],
                    ['id' => 'u-dir', 'accountId' => 'acc-dir', 'name' => 'Diego', 'email' => 'diretor@escola.test', 'login' => 'diego.login'],
                    ['id' => 'u-outra', 'accountId' => 'acc-outra', 'name' => 'Outra', 'login' => 'outra.login'],
                ],
                'schools' => [
                    [
                        'id' => 'school-a',
                        'name' => 'Escola A',
                        'externalId' => 'ext-a',
                        'subjects' => [],
                        'structures' => [
                            ['id' => 'seg-1', 'name' => 'Fundamental', 'type' => 'segment', 'structures' => [
                                ['id' => 'gr-1', 'name' => '6º ano', 'type' => 'grade'],
                            ]],
                            ['id' => 'per-1', 'name' => 'Manhã', 'type' => 'period'],
                        ],
                        'groups' => [[
                            'id' => 'g-1',
                            'type' => 'class',
                            'name' => '6A',
                            'schoolYear' => 2026,
                            'structures' => ['gr-1', 'per-1'],
                            'users' => [
                                ['userId' => 'u-aluno', 'profileId' => 'p-stu', 'subjects' => []],
                                ['userId' => 'u-prof', 'profileId' => 'p-tea', 'subjects' => []],
                                ['userId' => 'u-dir', 'profileId' => 'p-man', 'subjects' => []],
                            ],
                        ]],
                    ],
                    [
                        'id' => 'school-b',
                        'externalId' => 'ext-b',
                        'groups' => [[
                            'id' => 'g-2',
                            'type' => 'class',
                            'name' => 'Outra',
                            'schoolYear' => 2026,
                            'users' => [
                                ['userId' => 'u-outra', 'profileId' => 'p-stu', 'subjects' => []],
                            ],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
