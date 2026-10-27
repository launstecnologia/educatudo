<?php

use App\Modulos\Lex\Services\LexAutenticacaoService;
use App\Modulos\Lex\Services\LexCacheTokenMemoria;
use App\Modulos\Lex\Services\LexClassificadorUsuarios;
use App\Modulos\Lex\Services\LexClienteContrato;
use App\Modulos\Lex\Services\LexExcecaoHttp;
use App\Modulos\Lex\Services\LexPlanoUsuarios;
use App\Modulos\Lex\Services\LexSincronizacaoService;
use PHPUnit\Framework\TestCase;

class LexSincronizacaoUsuariosTest extends TestCase
{
    public function testClassificaAlunoProfessorResponsavelEUsuarioDaEscola(): void
    {
        $resultado = LexClassificadorUsuarios::classificar($this->snapshot(), 'tenant-1', 'school-a', 'ext-a');
        $this->assertTrue($resultado['ok']);
        $this->assertArrayHasKey('u-aluno', $resultado['pessoas']);
        $this->assertContains('student', $resultado['pessoas']['u-aluno']['papeis']);
        $this->assertContains('teacher', $resultado['pessoas']['u-prof']['papeis']);
        $this->assertContains('sponsor', $resultado['pessoas']['u-pai']['papeis']);
        $this->assertContains('sponsor', $resultado['pessoas']['u-mae']['papeis']);
        $this->assertSame('diretor', $resultado['pessoas']['u-dir']['perfil_admin']);
        $this->assertSame(['u-pai', 'u-mae'], $resultado['pessoas']['u-aluno']['sponsors']);
        $this->assertArrayNotHasKey('u-outra', $resultado['pessoas']);
    }

    public function testAssistantFicaIgnoradoEDisciplinaVaziaNaoEhErro(): void
    {
        $resultado = LexClassificadorUsuarios::classificar($this->snapshot(), 'tenant-1', 'school-a', '');
        $this->assertTrue($resultado['ok']);
        $mensagens = array_column($resultado['ignorados'], 'mensagem', 'id_lex');
        $this->assertSame('Perfil não importado nesta etapa.', $mensagens['u-asst']);
        $this->assertArrayNotHasKey('u-asst', $resultado['pessoas']);
    }

    public function testEscolaNaoVinculadaNaoImporta(): void
    {
        $resultado = LexClassificadorUsuarios::classificar($this->snapshot(), 'tenant-1', 'school-inexistente', 'ext-inexistente');
        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('não veio no snapshot', (string) $resultado['erro']);
    }

    public function testTenantDiferenteRecusaOSnapshot(): void
    {
        $resultado = LexClassificadorUsuarios::classificar($this->snapshot(), 'outro-tenant', 'school-a', 'ext-a');
        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('tenant', (string) $resultado['erro']);
    }

    public function testPlanoCriaOsQuatroCadastrosEDoisVinculos(): void
    {
        $classificacao = LexClassificadorUsuarios::classificar($this->snapshot(), 'tenant-1', 'school-a', 'ext-a');
        $plano = LexPlanoUsuarios::montar($classificacao, $this->estado());
        $this->assertSame(1, $plano['contagens']['aluno']['criar']);
        $this->assertSame(1, $plano['contagens']['professor']['criar']);
        $this->assertSame(2, $plano['contagens']['responsavel']['criar']);
        $this->assertSame(1, $plano['contagens']['usuario']['criar']);
        $aluno = array_values(array_filter($plano['criar'], static fn (array $item): bool => $item['tipo'] === 'aluno'))[0];
        $this->assertSame('aluno.login', $aluno['nickname']);
        $this->assertSame('aluno.login', $aluno['login_local']);
        $this->assertCount(2, $plano['vincular']);
        $this->assertSame([], $plano['inativar']);
    }

    public function testSincronizacaoRepetidaNaoCriaDeNovo(): void
    {
        $classificacao = LexClassificadorUsuarios::classificar($this->snapshot(), 'tenant-1', 'school-a', 'ext-a');
        $estado = $this->estado();
        foreach (['aluno' => 'u-aluno', 'professor' => 'u-prof', 'responsavel' => 'u-pai', 'usuario' => 'u-dir'] as $tipo => $idLex) {
            $estado['vinculos'][$tipo][$idLex] = ['id_local' => 10, 'status' => 'ativo'];
        }
        $estado['vinculos']['responsavel']['u-mae'] = ['id_local' => 11, 'status' => 'ativo'];
        $estado['ra']['aluno.login'] = 10;
        $estado['nickname']['aluno.login'] = 10;
        $estado['codigo_prof']['prof.login'] = 10;
        $estado['email_usuario']['diretor@escola.test'] = 10;

        $plano = LexPlanoUsuarios::montar($classificacao, $estado);
        $this->assertSame([], $plano['criar']);
        $this->assertSame(1, $plano['contagens']['aluno']['atualizar']);
        $this->assertSame(1, $plano['contagens']['professor']['atualizar']);
    }

    public function testRaEmUsoBloqueiaCriacaoESuspendeInativacao(): void
    {
        $classificacao = LexClassificadorUsuarios::classificar($this->snapshot(), 'tenant-1', 'school-a', 'ext-a');
        $estado = $this->estado();
        $estado['ra']['aluno.login'] = 99;
        $estado['vinculos']['aluno']['sumiu'] = ['id_local' => 7, 'status' => 'ativo'];
        $plano = LexPlanoUsuarios::montar($classificacao, $estado);
        $this->assertFalse($plano['pode_inativar']);
        $this->assertSame([], $plano['inativar']);
        $this->assertGreaterThan(0, $plano['inativacao_suspensa']);
        $this->assertTrue($plano['conflitos'][0]['bloqueia']);
    }

    public function testNicknameEmUsoBloqueiaOAluno(): void
    {
        $classificacao = LexClassificadorUsuarios::classificar($this->snapshot(), 'tenant-1', 'school-a', 'ext-a');
        $estado = $this->estado();
        $estado['nickname']['aluno.login'] = 99;
        $plano = LexPlanoUsuarios::montar($classificacao, $estado);
        $this->assertFalse($plano['pode_inativar']);
        $alunos = array_values(array_filter($plano['criar'], static fn (array $item): bool => $item['tipo'] === 'aluno'));
        $this->assertSame([], $alunos);
    }

    public function testAlunoAusenteNoSnapshotCompletoEhInativado(): void
    {
        $classificacao = LexClassificadorUsuarios::classificar($this->snapshot(), 'tenant-1', 'school-a', 'ext-a');
        $estado = $this->estado();
        $estado['vinculos']['aluno']['sumiu'] = ['id_local' => 7, 'status' => 'ativo'];
        $plano = LexPlanoUsuarios::montar($classificacao, $estado);
        $this->assertTrue($plano['pode_inativar']);
        $this->assertSame('sumiu', $plano['inativar'][0]['id_lex']);
        $this->assertSame(7, $plano['inativar'][0]['id_local']);
    }

    public function testResponsavelDeDoisAlunosNaoDuplicaOCadastro(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['payload']['users'][] = [
            'id' => 'u-irmao',
            'accountId' => 'acc-irmao',
            'name' => 'Irmão',
            'email' => 'irmao@escola.test',
            'login' => 'irmao.login',
            'sponsorsId' => ['u-pai'],
        ];
        $snapshot['payload']['schools'][0]['groups'][0]['users'][] = [
            'userId' => 'u-irmao',
            'profileId' => 'p-stu',
            'subjects' => [],
        ];
        $classificacao = LexClassificadorUsuarios::classificar($snapshot, 'tenant-1', 'school-a', 'ext-a');
        $plano = LexPlanoUsuarios::montar($classificacao, $this->estado());
        $responsaveis = array_values(array_filter($plano['criar'], static fn (array $item): bool => $item['tipo'] === 'responsavel' && $item['id_lex'] === 'u-pai'));
        $this->assertCount(1, $responsaveis);
        $linksDoPai = array_values(array_filter($plano['vincular'], static fn (array $item): bool => $item['responsavel_lex'] === 'u-pai'));
        $this->assertCount(2, $linksDoPai);
    }

    public function testTokenValidoEhReutilizado(): void
    {
        $cache = new LexCacheTokenMemoria();
        $cache->gravar('lex_jwt_4', 'token-antigo', time() + 600);
        $cliente = new LexClienteFalso();
        $auth = new LexAutenticacaoService($cliente, $cache, sys_get_temp_dir());
        $token = $auth->obterToken($this->config());
        $this->assertTrue($token['reutilizado']);
        $this->assertSame('token-antigo', $token['access_token']);
        $this->assertSame(0, $cliente->tokens);
    }

    public function testTokenExpiradoEhRenovado(): void
    {
        $cache = new LexCacheTokenMemoria();
        $cache->gravar('lex_jwt_4', 'token-velho', time() + 10);
        $cliente = new LexClienteFalso();
        $auth = new LexAutenticacaoService($cliente, $cache, sys_get_temp_dir());
        $token = $auth->obterToken($this->config());
        $this->assertFalse($token['reutilizado']);
        $this->assertSame('novo', $token['access_token']);
        $this->assertSame(1, $cliente->tokens);
    }

    public function testColetaRenovaUmaVezDepoisDe401(): void
    {
        $cliente = new LexClienteFalso();
        $cliente->statusGet = 401;
        $cliente->tenants = [['id' => 'tenant-1']];
        $cliente->full = $this->snapshot();
        $auth = new LexAutenticacaoService($cliente, new LexCacheTokenMemoria(), sys_get_temp_dir());
        $snapshot = LexSincronizacaoService::coletar($cliente, $auth, $this->config(), 'token-inicial');
        $this->assertSame('sync-1', $snapshot['header']['syncId']);
        $this->assertSame(1, $cliente->tokens);
        $this->assertSame(3, $cliente->gets);
    }

    public function testColetaNaoRenovaEm403(): void
    {
        $cliente = new LexClienteFalso();
        $cliente->statusGet = 403;
        $auth = new LexAutenticacaoService($cliente, new LexCacheTokenMemoria(), sys_get_temp_dir());
        $this->expectException(LexExcecaoHttp::class);
        try {
            LexSincronizacaoService::coletar($cliente, $auth, $this->config(), 'token-inicial');
        } finally {
            $this->assertSame(0, $cliente->tokens);
            $this->assertSame(1, $cliente->gets);
        }
    }

    public function testTenantForaDaListaAutorizadaFalha(): void
    {
        $cliente = new LexClienteFalso();
        $cliente->tenants = [['id' => 'outro']];
        $auth = new LexAutenticacaoService($cliente, new LexCacheTokenMemoria(), sys_get_temp_dir());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('não está entre os tenants autorizados');
        LexSincronizacaoService::coletar($cliente, $auth, $this->config(), 'token-inicial');
    }

    /**
     * @return array<string,mixed>
     */
    private function config(): array
    {
        return [
            'escola_id' => 4,
            'ambiente' => 'homologacao',
            'client_id' => 'cliente',
            'client_secret' => 'segredo',
            'tenant_id' => 'tenant-1',
        ];
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
            ],
            'ra' => [],
            'nickname' => [],
            'codigo_prof' => [],
            'email_usuario' => [],
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
                'tenantName' => 'EducaTudo_Tenant',
                'profiles' => [
                    ['id' => 'p-stu', 'name' => 'Aluno', 'type' => 'student'],
                    ['id' => 'p-tea', 'name' => 'Professor', 'type' => 'teacher'],
                    ['id' => 'p-spo', 'name' => 'Responsável', 'type' => 'sponsor'],
                    ['id' => 'p-man', 'name' => 'Gestor', 'type' => 'manager'],
                    ['id' => 'p-ass', 'name' => 'Assistente', 'type' => 'assistant'],
                ],
                'users' => [
                    ['id' => 'u-aluno', 'accountId' => 'acc-aluno', 'name' => 'Ana', 'email' => 'ana@escola.test', 'login' => 'aluno.login', 'birthDate' => '2014-03-02', 'documentType' => 'cpf', 'document' => '123.456.789-09', 'sponsorsId' => ['u-pai', 'u-mae']],
                    ['id' => 'u-pai', 'accountId' => 'acc-pai', 'name' => 'Paulo', 'email' => 'paulo@escola.test', 'login' => 'paulo.login'],
                    ['id' => 'u-mae', 'accountId' => 'acc-mae', 'name' => 'Marta', 'email' => 'marta@escola.test', 'login' => 'marta.login'],
                    ['id' => 'u-prof', 'accountId' => 'acc-prof', 'name' => 'Helena', 'email' => 'helena@escola.test', 'login' => 'prof.login'],
                    ['id' => 'u-dir', 'accountId' => 'acc-dir', 'name' => 'Diego', 'email' => 'diretor@escola.test', 'login' => 'diego.login'],
                    ['id' => 'u-asst', 'accountId' => 'acc-asst', 'name' => 'Aline', 'email' => 'aline@escola.test', 'login' => 'aline.login'],
                    ['id' => 'u-outra', 'accountId' => 'acc-outra', 'name' => 'Outra Escola', 'email' => 'outra@escola.test', 'login' => 'outra.login', 'sponsorsId' => ['u-pai']],
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
                        ],
                        'groups' => [[
                            'id' => 'g-1',
                            'type' => 'class',
                            'name' => '6A',
                            'schoolYear' => 2026,
                            'users' => [
                                ['userId' => 'u-aluno', 'profileId' => 'p-stu', 'subjects' => []],
                                ['userId' => 'u-prof', 'profileId' => 'p-tea', 'subjects' => []],
                                ['userId' => 'u-dir', 'profileId' => 'p-man', 'subjects' => []],
                                ['userId' => 'u-asst', 'profileId' => 'p-ass', 'subjects' => []],
                            ],
                        ]],
                    ],
                    [
                        'id' => 'school-b',
                        'name' => 'Escola B',
                        'externalId' => 'ext-b',
                        'groups' => [[
                            'id' => 'g-2',
                            'type' => 'class',
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

class LexClienteFalso implements LexClienteContrato
{
    public int $tokens = 0;
    public int $gets = 0;
    public int $statusGet = 200;

    /** @var array<string,mixed> */
    public array $tenants = [];

    /** @var array<string,mixed> */
    public array $full = [];

    public function solicitarToken(string $urlBase, string $clientId, string $clientSecret): array
    {
        $this->tokens++;
        return ['access_token' => 'novo', 'expires_in' => 3600, 'token_type' => 'Bearer'];
    }

    public function getJson(string $url, string $token, int $timeout = 30): array
    {
        $this->gets++;
        if ($this->statusGet === 401 && $this->gets === 1) {
            throw new LexExcecaoHttp('A LEX recusou a autenticação.', 401, 'autenticacao');
        }
        if ($this->statusGet === 403) {
            throw new LexExcecaoHttp('A LEX recusou o acesso.', 403, 'proibido');
        }
        if (strpos($url, '/tenants') !== false) {
            return $this->tenants;
        }
        return $this->full;
    }

    public function enviarJson(string $url, string $token, array $corpo, string $metodo = 'POST'): array
    {
        return [];
    }
}
