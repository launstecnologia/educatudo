<?php

namespace App\Modulos\Lex\Services;

use App\Core\MasterSecretVault;

class LexSincronizacaoService
{
    /**
     * @return array<string,mixed>|null
     */
    public static function lerConfig(\PDO $master, int $escolaId): ?array
    {
        if (!self::tabelaExiste($master, 'lex_escolas_config')) {
            return null;
        }
        $st = $master->prepare('SELECT * FROM lex_escolas_config WHERE escola_id = :id LIMIT 1');
        $st->execute(['id' => $escolaId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $segredo = MasterSecretVault::decrypt($row['client_secret_cifrado'] ?? null);

        return [
            'escola_id' => (int) $row['escola_id'],
            'ativo' => (int) ($row['ativo'] ?? 0) === 1,
            'ambiente' => (string) ($row['ambiente'] ?? 'homologacao'),
            'client_id' => (string) ($row['client_id'] ?? ''),
            'client_secret' => (string) ($segredo ?? ''),
            'tenant_id' => (string) ($row['tenant_id'] ?? ''),
            'escola_lex_id' => (string) ($row['escola_lex_id'] ?? ''),
            'external_id' => (string) ($row['external_id'] ?? ''),
            'tem_segredo' => $segredo !== null && $segredo !== '',
            'ultima_sincronizacao' => $row['ultima_sincronizacao'] ?? null,
            'ultimo_resultado' => $row['ultimo_resultado'] ?? null,
            'ultimo_sync_id' => $row['ultimo_sync_id'] ?? null,
        ];
    }

    /**
     * @return array{ok:bool,expira_em:int,tipo_token:string,reutilizado:bool,tenants:int,tenant_autorizado:bool}
     */
    public static function testar(\PDO $master, int $escolaId): array
    {
        $config = self::exigirCredenciais(self::lerConfig($master, $escolaId));
        $cliente = new LexClienteHttp();
        $auth = self::autenticacao($cliente);
        $token = $auth->obterToken($config);
        $url = LexAutenticacaoService::urlBase($config['ambiente']) . '/sync/v1/tenants';
        try {
            $lista = $cliente->getJson($url, $token['access_token'], 30);
        } catch (LexExcecaoHttp $e) {
            if ($e->httpStatus !== 401) {
                throw $e;
            }
            $auth->invalidar($escolaId);
            $token = $auth->obterToken($config);
            $lista = $cliente->getJson($url, $token['access_token'], 30);
        }
        $ids = self::idsTenants($lista);

        return [
            'ok' => true,
            'expira_em' => (int) $token['expires_in'],
            'tipo_token' => (string) $token['token_type'],
            'reutilizado' => (bool) $token['reutilizado'],
            'tenants' => count($ids),
            'tenant_autorizado' => self::contemTenant($ids, $config['tenant_id']),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function executar(\PDO $master, int $escolaId, string $modo): array
    {
        if (!in_array($modo, ['simulacao', 'real'], true)) {
            throw new \RuntimeException('Modo de sincronização inválido.');
        }
        $config = self::exigirCredenciais(self::lerConfig($master, $escolaId));
        if (!$config['ativo']) {
            throw new \RuntimeException('Ative a integração LEX desta escola antes de sincronizar.');
        }

        $cliente = new LexClienteHttp();
        $auth = self::autenticacao($cliente);
        $token = $auth->obterToken($config);
        $snapshot = self::coletar($cliente, $auth, $config, $token['access_token']);
        $classificacao = LexClassificadorUsuarios::classificar(
            $snapshot,
            $config['tenant_id'],
            $config['escola_lex_id'],
            $config['external_id']
        );

        $tenant = self::abrirTenant($master, $escolaId);
        if (!$classificacao['ok']) {
            $mensagem = (string) ($classificacao['erro'] ?? 'Snapshot inválido.');
            self::confirmar($cliente, $auth, $config, (string) ($classificacao['sync_id'] ?? ''), 'failure');
            self::registrar($tenant, $classificacao, null, $modo, 'falha', $mensagem);
            self::marcarMaster($master, $escolaId, 'falha', (string) ($classificacao['sync_id'] ?? ''));
            throw new \RuntimeException($mensagem);
        }

        $estado = self::carregarEstado($tenant);
        $planoPessoas = LexPlanoUsuarios::montar($classificacao, $estado);
        $plano = LexPlanoAcademico::combinar(
            $planoPessoas,
            LexPlanoAcademico::montar(
                LexClassificadorAcademico::classificar(
                    $snapshot,
                    $config['tenant_id'],
                    $config['escola_lex_id'],
                    $config['external_id']
                ),
                $estado,
                $planoPessoas
            )
        );
        $aviso = null;
        if ($modo === 'real') {
            $tenant->beginTransaction();
            try {
                self::aplicar($tenant, $plano);
                $tenant->commit();
            } catch (\Throwable $e) {
                if ($tenant->inTransaction()) {
                    $tenant->rollBack();
                }
                error_log('[lex] escola=' . $escolaId . ' importação revertida');
                self::confirmar($cliente, $auth, $config, (string) $classificacao['sync_id'], 'failure');
                self::registrar($tenant, $classificacao, $plano, $modo, 'falha', 'A importação foi revertida.');
                self::marcarMaster($master, $escolaId, 'falha', (string) $classificacao['sync_id']);
                throw new \RuntimeException('A importação foi revertida por uma falha ao gravar.');
            }
            $aviso = self::confirmar($cliente, $auth, $config, (string) $classificacao['sync_id'], 'success');
        }

        $mensagem = $modo === 'simulacao'
            ? 'Simulação concluída. Nenhum cadastro foi alterado.'
            : 'Sincronização concluída.';
        if ($aviso !== null) {
            $mensagem .= ' ' . $aviso;
        }
        self::registrar($tenant, $classificacao, $plano, $modo, 'sucesso', $mensagem);
        self::marcarMaster($master, $escolaId, $modo === 'simulacao' ? 'simulacao' : 'sucesso', (string) $classificacao['sync_id']);

        return [
            'ok' => true,
            'modo' => $modo,
            'mensagem' => $mensagem,
            'percentual' => 100,
            'sync_id' => $classificacao['sync_id'],
            'contagens' => $plano['contagens'],
            'inativacao_suspensa' => $plano['inativacao_suspensa'],
        ];
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public static function coletar(
        LexClienteContrato $cliente,
        LexAutenticacaoService $auth,
        array $config,
        string $token
    ): array {
        $baixar = function (string $acesso) use ($cliente, $config): array {
            $base = LexAutenticacaoService::urlBase((string) $config['ambiente']);
            $tenants = $cliente->getJson($base . '/sync/v1/tenants', $acesso, 30);
            $ids = self::idsTenants($tenants);
            if (!self::contemTenant($ids, (string) $config['tenant_id'])) {
                throw new \RuntimeException('O tenant configurado não está entre os tenants autorizados.');
            }
            return $cliente->getJson(
                $base . '/sync/v1/full?tenantId=' . rawurlencode((string) $config['tenant_id']),
                $acesso,
                120
            );
        };

        try {
            return $baixar($token);
        } catch (LexExcecaoHttp $e) {
            if ($e->httpStatus !== 401) {
                throw $e;
            }
            $auth->invalidar((int) $config['escola_id']);
            $novo = $auth->obterToken($config);
            return $baixar($novo['access_token']);
        }
    }

    /**
     * @param array<string,mixed> $resposta
     * @return list<string>
     */
    public static function idsTenants(array $resposta): array
    {
        $lista = $resposta;
        if (isset($resposta['payload']) && is_array($resposta['payload'])) {
            $lista = isset($resposta['payload']['tenants']) && is_array($resposta['payload']['tenants'])
                ? $resposta['payload']['tenants']
                : $resposta['payload'];
        } elseif (isset($resposta['tenants']) && is_array($resposta['tenants'])) {
            $lista = $resposta['tenants'];
        } elseif (isset($resposta['data']) && is_array($resposta['data'])) {
            $lista = $resposta['data'];
        }
        if (isset($lista['id']) || isset($lista['tenantId'])) {
            $lista = [$lista];
        }

        $ids = [];
        foreach ($lista as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = trim((string) ($item['tenantId'] ?? $item['id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    public static function abrirTenant(\PDO $master, int $escolaId): \PDO
    {
        $st = $master->prepare(
            'SELECT host, porta, nome_banco, usuario, senha_criptografada
               FROM config_escolas_banco WHERE escola_id = :id LIMIT 1'
        );
        $st->execute(['id' => $escolaId]);
        $banco = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$banco || trim((string) ($banco['nome_banco'] ?? '')) === '') {
            throw new \RuntimeException('A escola não tem banco configurado.');
        }

        $host = (string) ($banco['host'] ?? 'localhost');
        $porta = (int) ($banco['porta'] ?? 3306);
        $nome = (string) $banco['nome_banco'];
        $usuario = (string) ($banco['usuario'] ?? '');
        $senha = MasterSecretVault::decryptDbPassword((string) ($banco['senha_criptografada'] ?? ''));
        $pdo = new \PDO(
            'mysql:host=' . $host . ';port=' . $porta . ';dbname=' . $nome . ';charset=utf8mb4',
            $usuario,
            $senha,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        return $pdo;
    }

    /**
     * @return array{sincronizacao:?array,eventos:list<array>,erro:?string}
     */
    public static function historico(\PDO $tenant): array
    {
        if (!self::tabelaExiste($tenant, 'lex_sincronizacoes')) {
            return [
                'sincronizacao' => null,
                'eventos' => [],
                'erro' => 'Rode a migration 2026_10_26_lex_integracao.sql no banco desta escola.',
            ];
        }
        $sync = $tenant->query('SELECT * FROM lex_sincronizacoes ORDER BY id DESC LIMIT 1')->fetch(\PDO::FETCH_ASSOC) ?: null;
        $eventos = [];
        if ($sync) {
            $st = $tenant->prepare(
                'SELECT nivel, tipo_entidade, id_lex, mensagem, created_at
                   FROM lex_sincronizacao_eventos
                  WHERE sincronizacao_id = :id
                  ORDER BY id ASC
                  LIMIT 40'
            );
            $st->execute(['id' => (int) $sync['id']]);
            $eventos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $sync['contagens'] = json_decode((string) ($sync['contagens_json'] ?? ''), true) ?: [];
        }
        return [
            'sincronizacao' => $sync ?: null,
            'eventos' => $eventos,
            'erro' => null,
        ];
    }

    /**
     * @param array<string,mixed>|null $config
     * @return array<string,mixed>
     */
    private static function exigirCredenciais(?array $config): array
    {
        if ($config === null) {
            throw new \RuntimeException('Salve a configuração LEX desta escola. Se a tabela não existir, rode a migration do master.');
        }
        if (trim($config['client_id']) === '' || !$config['tem_segredo']) {
            throw new \RuntimeException('Informe o client id e o client secret da LEX.');
        }
        if (trim($config['tenant_id']) === '') {
            throw new \RuntimeException('Informe o tenant da LEX.');
        }
        if (trim($config['escola_lex_id']) === '' && trim($config['external_id']) === '') {
            throw new \RuntimeException('Informe o id da escola na LEX ou o externalId.');
        }
        if (!in_array($config['ambiente'], ['homologacao', 'producao'], true)) {
            throw new \RuntimeException('Ambiente da LEX inválido.');
        }
        return $config;
    }

    private static function autenticacao(LexClienteContrato $cliente): LexAutenticacaoService
    {
        $dir = dirname(__DIR__, 4) . '/storage/tmp';
        return new LexAutenticacaoService($cliente, new LexCacheTokenRedis(), $dir);
    }

    /**
     * @param list<string> $ids
     */
    private static function contemTenant(array $ids, string $tenantId): bool
    {
        foreach ($ids as $id) {
            if (strcasecmp($id, $tenantId) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $config
     */
    private static function confirmar(
        LexClienteContrato $cliente,
        LexAutenticacaoService $auth,
        array $config,
        string $syncId,
        string $status
    ): ?string {
        if ($syncId === '' || !$cliente instanceof LexClienteHttp) {
            return null;
        }
        try {
            $token = $auth->obterToken($config);
            $cliente->confirmar(
                LexAutenticacaoService::urlBase((string) $config['ambiente']),
                $token['access_token'],
                $syncId,
                $status
            );
            return null;
        } catch (LexExcecaoHttp $e) {
            if ($e->httpStatus === 401) {
                try {
                    $auth->invalidar((int) $config['escola_id']);
                    $token = $auth->obterToken($config);
                    $cliente->confirmar(
                        LexAutenticacaoService::urlBase((string) $config['ambiente']),
                        $token['access_token'],
                        $syncId,
                        $status
                    );
                    return null;
                } catch (\Throwable $e2) {
                    return 'A LEX não confirmou o resultado.';
                }
            }
            return 'A LEX não confirmou o resultado.';
        } catch (\Throwable $e) {
            return 'A LEX não confirmou o resultado.';
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function carregarEstado(\PDO $tenant): array
    {
        if (!self::tabelaExiste($tenant, 'lex_vinculos')) {
            throw new \RuntimeException('Rode a migration 2026_10_26_lex_integracao.sql no banco desta escola.');
        }
        $estado = [
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
        foreach ($tenant->query('SELECT tipo_entidade, id_local, id_lex, status FROM lex_vinculos') as $row) {
            $tipo = (string) $row['tipo_entidade'];
            if (!isset($estado['vinculos'][$tipo])) {
                continue;
            }
            $estado['vinculos'][$tipo][(string) $row['id_lex']] = [
                'id_local' => (int) $row['id_local'],
                'status' => (string) ($row['status'] ?? 'ativo'),
            ];
        }
        foreach ($tenant->query('SELECT id, ra, nickname FROM alunos') as $row) {
            $ra = strtolower(trim((string) ($row['ra'] ?? '')));
            if ($ra !== '') {
                $estado['ra'][$ra] = (int) $row['id'];
            }
            $nickname = strtolower(trim((string) ($row['nickname'] ?? '')));
            if ($nickname !== '') {
                $estado['nickname'][$nickname] = (int) $row['id'];
            }
        }
        foreach ($tenant->query('SELECT id, codigo_prof FROM professores') as $row) {
            $codigo = strtolower(trim((string) ($row['codigo_prof'] ?? '')));
            if ($codigo !== '') {
                $estado['codigo_prof'][$codigo] = (int) $row['id'];
            }
        }
        foreach ($tenant->query('SELECT id, email FROM usuarios') as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email !== '') {
                $estado['email_usuario'][$email] = (int) $row['id'];
            }
        }
        if (self::tabelaExiste($tenant, 'ano_letivo')) {
            foreach ($tenant->query('SELECT id, ano FROM ano_letivo') as $row) {
                $estado['anos'][(int) $row['ano']] = (int) $row['id'];
            }
        }
        if (self::tabelaExiste($tenant, 'serie')) {
            foreach ($tenant->query('SELECT id, curso_id, nome FROM serie') as $row) {
                $nome = function_exists('mb_strtolower')
                    ? mb_strtolower(trim((string) ($row['nome'] ?? '')))
                    : strtolower(trim((string) ($row['nome'] ?? '')));
                if ($nome !== '') {
                    $estado['serie_nomes'][(int) $row['curso_id'] . '|' . $nome] = (int) $row['id'];
                }
            }
        }
        if (self::tabelaExiste($tenant, 'matricula')) {
            foreach ($tenant->query('SELECT id, aluno_id, turma_id, ano_letivo_id FROM matricula') as $row) {
                $chave = (int) $row['aluno_id'] . '|' . (int) $row['turma_id'] . '|' . (int) $row['ano_letivo_id'];
                $estado['matriculas'][$chave] = (int) $row['id'];
            }
        }
        return $estado;
    }

    /**
     * @param array<string,mixed> $plano
     */
    private static function aplicar(\PDO $tenant, array $plano): void
    {
        $colunas = [
            'alunos' => self::colunas($tenant, 'alunos'),
            'professores' => self::colunas($tenant, 'professores'),
            'responsaveis' => self::colunas($tenant, 'responsaveis'),
            'usuarios' => self::colunas($tenant, 'usuarios'),
            'alunos_responsaveis' => self::colunas($tenant, 'alunos_responsaveis'),
        ];
        $locais = [
            'aluno' => [],
            'professor' => [],
            'responsavel' => [],
            'usuario' => [],
        ];

        foreach ($plano['criar'] as $acao) {
            $id = self::criarPessoa($tenant, $colunas, $acao);
            $locais[$acao['tipo']][$acao['id_lex']] = $id;
            self::gravarVinculo($tenant, (string) $acao['tipo'], $id, (string) $acao['id_lex'], $acao['account_id'] ?? null);
        }
        foreach ($plano['atualizar'] as $acao) {
            self::atualizarPessoa($tenant, $colunas, $acao);
            $locais[$acao['tipo']][$acao['id_lex']] = (int) $acao['id_local'];
            self::ativarVinculo($tenant, (string) $acao['tipo'], (string) $acao['id_lex'], $acao['account_id'] ?? null);
        }

        $primeiroResponsavel = [];
        foreach ($plano['vincular'] as $item) {
            $alunoId = $locais['aluno'][$item['aluno_lex']] ?? null;
            $responsavelId = $locais['responsavel'][$item['responsavel_lex']] ?? null;
            if (!$alunoId || !$responsavelId) {
                continue;
            }
            if (!isset($primeiroResponsavel[$alunoId])) {
                $primeiroResponsavel[$alunoId] = $responsavelId;
            }
            if (!empty($item['existe'])) {
                $idLocal = self::idVinculo($tenant, 'aluno_responsavel', (string) $item['id_lex']);
                if ($idLocal > 0 && isset($colunas['alunos_responsaveis']['ativo'])) {
                    $st = $tenant->prepare('UPDATE alunos_responsaveis SET ativo = 1 WHERE id = :id');
                    $st->execute(['id' => $idLocal]);
                }
                self::ativarVinculo($tenant, 'aluno_responsavel', (string) $item['id_lex'], null);
                continue;
            }
            $idLink = self::criarLink($tenant, $colunas['alunos_responsaveis'], $alunoId, $responsavelId);
            self::gravarVinculo($tenant, 'aluno_responsavel', $idLink, (string) $item['id_lex'], null);
        }

        if (isset($colunas['alunos']['responsavel_id'])) {
            $st = $tenant->prepare(
                'UPDATE alunos SET responsavel_id = :responsavel WHERE id = :id AND responsavel_id IS NULL'
            );
            foreach ($primeiroResponsavel as $alunoId => $responsavelId) {
                $st->execute(['responsavel' => $responsavelId, 'id' => $alunoId]);
            }
        }

        self::aplicarAcademico($tenant, is_array($plano['academico'] ?? null) ? $plano['academico'] : [], $locais);

        $academico = is_array($plano['academico'] ?? null) ? $plano['academico'] : [];
        foreach (array_merge($plano['inativar'], $plano['inativar_vinculos'], $academico['inativar'] ?? []) as $item) {
            self::inativar($tenant, (string) $item['tipo'], (int) $item['id_local'], (string) $item['id_lex']);
        }
        foreach ($academico['inativar_matriculas'] ?? [] as $item) {
            self::fecharMatricula($tenant, (int) $item['id_local'], (string) $item['id_lex']);
        }
    }

    /**
     * @param array<string, array<string, bool>> $colunas
     * @param array<string,mixed> $acao
     */
    private static function criarPessoa(\PDO $tenant, array $colunas, array $acao): int
    {
        $senha = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $tipo = (string) $acao['tipo'];
        if ($tipo === 'aluno') {
            $dados = self::filtrar($colunas['alunos'], [
                'nome' => $acao['nome'],
                'email' => $acao['email'],
                'cpf' => $acao['cpf'],
                'senha_hash' => $senha,
                'ra' => $acao['login_local'],
                'nickname' => $acao['nickname'],
                'serie' => 'A definir',
                'data_nasc' => $acao['nascimento'],
                'ativo' => 1,
                'status' => 'ACTIVE',
                'password' => '',
                'primeiro_acesso' => 0,
                'pagante' => 1,
            ]);
            self::exigirColunas($dados, ['nome', 'senha_hash', 'ra', 'serie'], 'alunos');
            return self::inserir($tenant, 'alunos', $dados);
        }
        if ($tipo === 'professor') {
            $dados = self::filtrar($colunas['professores'], [
                'nome' => $acao['nome'],
                'email' => $acao['email'],
                'senha_hash' => $senha,
                'codigo_prof' => $acao['login_local'],
                'turmas' => '[]',
                'ativo' => 1,
                'password' => '',
                'pagante' => 1,
            ]);
            self::exigirColunas($dados, ['nome', 'senha_hash', 'codigo_prof'], 'professores');
            return self::inserir($tenant, 'professores', $dados);
        }
        if ($tipo === 'responsavel') {
            $dados = self::filtrar($colunas['responsaveis'], [
                'nome' => $acao['nome'],
                'email' => $acao['email'],
                'senha_hash' => $senha,
                'cpf' => $acao['cpf'],
                'data_nascimento' => $acao['nascimento'],
                'ativo' => 1,
                'password' => '',
            ]);
            self::exigirColunas($dados, ['nome', 'senha_hash'], 'responsaveis');
            return self::inserir($tenant, 'responsaveis', $dados);
        }

        $dados = self::filtrar($colunas['usuarios'], [
            'tipo' => 'admin_escola',
            'perfil_admin' => $acao['perfil_admin'],
            'nome' => $acao['nome'],
            'email' => $acao['email'],
            'senha_hash' => $senha,
            'ativo' => 1,
        ]);
        self::exigirColunas($dados, ['tipo', 'nome', 'senha_hash', 'perfil_admin'], 'usuarios');
        return self::inserir($tenant, 'usuarios', $dados);
    }

    /**
     * @param array<string, array<string, bool>> $colunas
     * @param array<string,mixed> $acao
     */
    private static function atualizarPessoa(\PDO $tenant, array $colunas, array $acao): void
    {
        $tipo = (string) $acao['tipo'];
        $tabela = [
            'aluno' => 'alunos',
            'professor' => 'professores',
            'responsavel' => 'responsaveis',
            'usuario' => 'usuarios',
        ][$tipo] ?? '';
        if ($tabela === '') {
            return;
        }
        $nascimento = $tipo === 'responsavel' ? 'data_nascimento' : 'data_nasc';
        $dados = [
            'nome' => $acao['nome'],
            'ativo' => 1,
        ];
        if ($acao['email'] !== null && $acao['email'] !== '') {
            $dados['email'] = $acao['email'];
        }
        if ($tipo === 'aluno' || $tipo === 'responsavel') {
            if ($acao['cpf'] !== null) {
                $dados['cpf'] = $acao['cpf'];
            }
            if ($acao['nascimento'] !== null) {
                $dados[$nascimento] = $acao['nascimento'];
            }
        }
        if ($tipo === 'usuario' && $acao['perfil_admin'] !== null) {
            $dados['perfil_admin'] = $acao['perfil_admin'];
        }
        $dados = self::filtrar($colunas[$tabela], $dados);
        if ($dados === []) {
            return;
        }
        $sets = [];
        foreach (array_keys($dados) as $coluna) {
            $sets[] = '`' . $coluna . '` = :' . $coluna;
        }
        $dados['id'] = (int) $acao['id_local'];
        $sql = 'UPDATE `' . $tabela . '` SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $st = $tenant->prepare($sql);
        $st->execute($dados);
    }

    /**
     * @param array<string, bool> $colunas
     */
    private static function criarLink(\PDO $tenant, array $colunas, int $alunoId, int $responsavelId): int
    {
        $dados = self::filtrar($colunas, [
            'aluno_id' => $alunoId,
            'responsavel_id' => $responsavelId,
            'tipo_vinculo' => 'responsavel',
            'is_financeiro' => 0,
            'ativo' => 1,
        ]);
        return self::inserir($tenant, 'alunos_responsaveis', $dados);
    }

    /**
     * @param array<string,mixed> $academico
     * @param array<string, array<string, int>> $locais
     */
    private static function aplicarAcademico(\PDO $tenant, array $academico, array $locais): void
    {
        if ($academico === []) {
            return;
        }
        $colunas = [
            'curso' => self::colunas($tenant, 'curso'),
            'serie' => self::colunas($tenant, 'serie'),
            'turmas' => self::colunas($tenant, 'turmas'),
            'matricula' => self::colunas($tenant, 'matricula'),
            'ano_letivo' => self::colunas($tenant, 'ano_letivo'),
            'alunos' => self::colunas($tenant, 'alunos'),
            'professores' => self::colunas($tenant, 'professores'),
        ];

        $anos = [];
        foreach ($academico['anos'] ?? [] as $ano) {
            $ano = (int) $ano;
            $anos[$ano] = self::garantirAno($tenant, $colunas['ano_letivo'], $ano);
        }

        $cursos = [];
        $series = [];
        $turmas = [];
        foreach ($academico['atualizar'] ?? [] as $acao) {
            $tipo = (string) ($acao['tipo'] ?? '');
            $idLocal = (int) ($acao['id_local'] ?? 0);
            if ($idLocal < 1) {
                continue;
            }
            if ($tipo === 'curso') {
                $cursos[(string) $acao['id_lex']] = $idLocal;
            } elseif ($tipo === 'serie') {
                $series[(string) $acao['id_lex']] = $idLocal;
            } elseif ($tipo === 'turma') {
                $turmas[(string) $acao['id_lex']] = $idLocal;
            }
        }
        $dataEntrada = (string) ($academico['data_entrada'] ?? date('Y-m-d'));

        foreach ($academico['criar'] ?? [] as $acao) {
            $tipo = (string) ($acao['tipo'] ?? '');
            if ($tipo === 'curso') {
                $id = self::inserir($tenant, 'curso', self::filtrar($colunas['curso'], [
                    'nome' => $acao['nome'],
                    'ativo' => 1,
                    'ordem' => 0,
                    'tipo' => 'regular',
                    'possui_serie' => 1,
                ]));
                $cursos[$acao['id_lex']] = $id;
                self::gravarVinculo($tenant, 'curso', $id, (string) $acao['id_lex'], null);
            } elseif ($tipo === 'serie') {
                $cursoId = $cursos[$acao['curso_lex']] ?? 0;
                $dados = self::filtrar($colunas['serie'], [
                    'curso_id' => $cursoId,
                    'nome' => $acao['nome'],
                    'ordem' => 0,
                    'ativo' => 1,
                ]);
                self::exigirColunas($dados, ['curso_id', 'nome'], 'serie');
                $id = self::inserir($tenant, 'serie', $dados);
                $series[$acao['id_lex']] = $id;
                self::gravarVinculo($tenant, 'serie', $id, (string) $acao['id_lex'], null);
            } elseif ($tipo === 'turma') {
                $id = self::gravarTurma($tenant, $colunas['turmas'], $acao, $cursos, $series, $anos, 0);
                $turmas[$acao['id_lex']] = $id;
                self::gravarVinculo($tenant, 'turma', $id, (string) $acao['id_lex'], null);
            } elseif ($tipo === 'matricula') {
                $alunoId = (int) ($locais['aluno'][$acao['aluno_lex']] ?? 0);
                $turmaId = (int) ($turmas[$acao['turma_lex']] ?? 0);
                $anoId = (int) ($anos[(int) $acao['ano']] ?? 0);
                if ($alunoId < 1 || $turmaId < 1 || $anoId < 1) {
                    throw new \RuntimeException('Matrícula sem aluno, turma ou ano letivo.');
                }
                $dados = self::filtrar($colunas['matricula'], [
                    'aluno_id' => $alunoId,
                    'turma_id' => $turmaId,
                    'ano_letivo_id' => $anoId,
                    'data_entrada' => $dataEntrada,
                    'status' => 'ativa',
                ]);
                self::exigirColunas($dados, ['aluno_id', 'turma_id', 'ano_letivo_id', 'data_entrada', 'status'], 'matricula');
                $id = self::inserir($tenant, 'matricula', $dados);
                self::gravarVinculo($tenant, 'matricula', $id, (string) $acao['id_lex'], null);
            }
        }

        foreach ($academico['atualizar'] ?? [] as $acao) {
            $tipo = (string) ($acao['tipo'] ?? '');
            $idLocal = (int) ($acao['id_local'] ?? 0);
            if ($tipo === 'curso') {
                self::atualizarLinha($tenant, 'curso', $colunas['curso'], $idLocal, ['nome' => $acao['nome'], 'ativo' => 1]);
                $cursos[$acao['id_lex']] = $idLocal;
                self::ativarVinculo($tenant, 'curso', (string) $acao['id_lex'], null);
            } elseif ($tipo === 'serie') {
                $cursoId = $cursos[$acao['curso_lex']] ?? 0;
                self::atualizarLinha($tenant, 'serie', $colunas['serie'], $idLocal, [
                    'nome' => $acao['nome'],
                    'curso_id' => $cursoId,
                    'ativo' => 1,
                ]);
                $series[$acao['id_lex']] = $idLocal;
                self::ativarVinculo($tenant, 'serie', (string) $acao['id_lex'], null);
            } elseif ($tipo === 'turma') {
                self::gravarTurma($tenant, $colunas['turmas'], $acao, $cursos, $series, $anos, $idLocal);
                $turmas[$acao['id_lex']] = $idLocal;
                self::ativarVinculo($tenant, 'turma', (string) $acao['id_lex'], null);
            } elseif ($tipo === 'matricula') {
                $anoId = (int) ($anos[(int) ($acao['ano'] ?? 0)] ?? 0);
                $dadosMatricula = ['status' => 'ativa', 'data_saida' => null];
                if ($anoId > 0) {
                    $dadosMatricula['ano_letivo_id'] = $anoId;
                }
                self::atualizarLinha($tenant, 'matricula', $colunas['matricula'], $idLocal, $dadosMatricula);
                self::ativarVinculo($tenant, 'matricula', (string) $acao['id_lex'], null);
            }
        }

        $podeLimpar = (bool) ($academico['pode_inativar'] ?? false);
        self::atualizarAlunos($tenant, $colunas['alunos'], $academico['alunos'] ?? [], $locais, $turmas, $podeLimpar);
        self::atualizarProfessores($tenant, $colunas['professores'], $academico['professores'] ?? [], $locais, $turmas, $podeLimpar);
    }

    /**
     * @param array<string, bool> $colunas
     */
    private static function garantirAno(\PDO $tenant, array $colunas, int $ano): int
    {
        $st = $tenant->prepare('SELECT id FROM ano_letivo WHERE ano = :ano LIMIT 1');
        $st->execute(['ano' => $ano]);
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id > 0) {
            if (isset($colunas['ativo'])) {
                $upd = $tenant->prepare('UPDATE ano_letivo SET ativo = 1 WHERE id = :id');
                $upd->execute(['id' => $id]);
            }
            return $id;
        }
        $dados = self::filtrar($colunas, ['ano' => $ano, 'ativo' => 1]);
        self::exigirColunas($dados, ['ano'], 'ano_letivo');
        return self::inserir($tenant, 'ano_letivo', $dados);
    }

    /**
     * @param array<string, bool> $colunas
     * @param array<string,mixed> $acao
     * @param array<string, int> $cursos
     * @param array<string, int> $series
     * @param array<int, int> $anos
     */
    private static function gravarTurma(
        \PDO $tenant,
        array $colunas,
        array $acao,
        array $cursos,
        array $series,
        array $anos,
        int $idLocal
    ): int {
        $cursoLex = (string) ($acao['curso_lex'] ?? '');
        $serieLex = (string) ($acao['serie_lex'] ?? '');
        $dados = self::filtrar($colunas, [
            'nome' => $acao['nome'],
            'ano_letivo' => (int) $acao['ano'],
            'serie' => (string) ($acao['serie_nome'] ?? 'A definir'),
            'ativo' => 1,
            'curso_novo_id' => $cursoLex !== '' ? ($cursos[$cursoLex] ?? null) : null,
            'serie_id' => $serieLex !== '' ? ($series[$serieLex] ?? null) : null,
            'ano_letivo_id' => $anos[(int) $acao['ano']] ?? null,
            'turno' => $acao['turno'] ?? null,
        ]);
        self::exigirColunas($dados, ['nome', 'ano_letivo', 'serie'], 'turmas');
        if ($idLocal > 0) {
            self::atualizarLinha($tenant, 'turmas', $colunas, $idLocal, $dados);
            return $idLocal;
        }
        return self::inserir($tenant, 'turmas', $dados);
    }

    /**
     * @param array<string, bool> $colunas
     * @param array<string, mixed> $dados
     */
    private static function atualizarLinha(\PDO $tenant, string $tabela, array $colunas, int $id, array $dados): void
    {
        $dados = self::filtrar($colunas, $dados);
        if ($dados === [] || $id < 1) {
            return;
        }
        $sets = [];
        foreach (array_keys($dados) as $coluna) {
            $sets[] = '`' . $coluna . '` = :' . $coluna;
        }
        $dados['id'] = $id;
        $st = $tenant->prepare('UPDATE `' . $tabela . '` SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $st->execute($dados);
    }

    /**
     * @param array<string, bool> $colunas
     * @param list<array<string,mixed>> $alunos
     * @param array<string, array<string, int>> $locais
     * @param array<string, int> $turmas
     */
    private static function atualizarAlunos(\PDO $tenant, array $colunas, array $alunos, array $locais, array $turmas, bool $podeLimpar): void
    {
        if (!isset($colunas['turma_id']) && !isset($colunas['serie'])) {
            return;
        }
        $lexTurmas = self::idsLocais($tenant, 'turma');
        $comTurma = $tenant->prepare('UPDATE alunos SET turma_id = :turma, serie = :serie WHERE id = :id');
        foreach ($alunos as $aluno) {
            $alunoId = (int) ($locais['aluno'][$aluno['id_lex']] ?? 0);
            if ($alunoId < 1) {
                continue;
            }
            $turmaLex = (string) ($aluno['turma_lex'] ?? '');
            if ($turmaLex !== '' && isset($turmas[$turmaLex]) && isset($colunas['turma_id'], $colunas['serie'])) {
                $comTurma->execute([
                    'turma' => $turmas[$turmaLex],
                    'serie' => (string) ($aluno['serie'] ?? 'A definir'),
                    'id' => $alunoId,
                ]);
                continue;
            }
            if ($podeLimpar && $turmaLex === '' && $lexTurmas !== [] && isset($colunas['turma_id'])) {
                $marcas = [];
                $params = ['id' => $alunoId];
                foreach (array_values($lexTurmas) as $i => $turmaId) {
                    $marcas[] = ':t' . $i;
                    $params['t' . $i] = $turmaId;
                }
                $st = $tenant->prepare(
                    'UPDATE alunos SET turma_id = NULL WHERE id = :id AND turma_id IN (' . implode(',', $marcas) . ')'
                );
                $st->execute($params);
            }
        }
    }

    /**
     * @param array<string, bool> $colunas
     * @param list<array<string,mixed>> $professores
     * @param array<string, array<string, int>> $locais
     * @param array<string, int> $turmas
     */
    private static function atualizarProfessores(\PDO $tenant, array $colunas, array $professores, array $locais, array $turmas, bool $podeLimpar): void
    {
        if (!isset($colunas['turmas']) || $professores === []) {
            return;
        }
        $lexTurmas = array_fill_keys(self::idsLocais($tenant, 'turma'), true);
        $ler = $tenant->prepare('SELECT turmas FROM professores WHERE id = :id LIMIT 1');
        $gravar = $tenant->prepare('UPDATE professores SET turmas = :turmas WHERE id = :id');
        foreach ($professores as $professor) {
            $professorId = (int) ($locais['professor'][$professor['id_lex']] ?? 0);
            if ($professorId < 1) {
                continue;
            }
            $ler->execute(['id' => $professorId]);
            $atuais = json_decode((string) ($ler->fetchColumn() ?: '[]'), true);
            if (!is_array($atuais)) {
                $atuais = [];
            }
            $mantidos = [];
            foreach ($atuais as $turmaId) {
                $turmaId = (int) $turmaId;
                if ($turmaId < 1) {
                    continue;
                }
                if ($podeLimpar && isset($lexTurmas[$turmaId])) {
                    continue;
                }
                $mantidos[] = $turmaId;
            }
            foreach ($professor['turmas_lex'] ?? [] as $turmaLex) {
                if (isset($turmas[(string) $turmaLex])) {
                    $mantidos[] = $turmas[(string) $turmaLex];
                }
            }
            $mantidos = array_values(array_unique($mantidos));
            $gravar->execute([
                'turmas' => json_encode($mantidos),
                'id' => $professorId,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private static function idsLocais(\PDO $tenant, string $tipo): array
    {
        $st = $tenant->prepare('SELECT id_local FROM lex_vinculos WHERE tipo_entidade = :tipo');
        $st->execute(['tipo' => $tipo]);
        $ids = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $id = (int) ($row['id_local'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private static function fecharMatricula(\PDO $tenant, int $idLocal, string $idLex): void
    {
        if ($idLocal > 0) {
            $st = $tenant->prepare(
                "UPDATE matricula
                    SET status = 'transferido', data_saida = CURDATE()
                  WHERE id = :id AND status = 'ativa'"
            );
            $st->execute(['id' => $idLocal]);
        }
        $st = $tenant->prepare(
            "UPDATE lex_vinculos SET status = 'inativo'
              WHERE tipo_entidade = 'matricula' AND id_lex = :lex"
        );
        $st->execute(['lex' => $idLex]);
    }

    private static function inativar(\PDO $tenant, string $tipo, int $idLocal, string $idLex): void
    {
        $tabela = [
            'aluno' => 'alunos',
            'professor' => 'professores',
            'responsavel' => 'responsaveis',
            'usuario' => 'usuarios',
            'aluno_responsavel' => 'alunos_responsaveis',
            'curso' => 'curso',
            'serie' => 'serie',
            'turma' => 'turmas',
        ][$tipo] ?? '';
        if ($tabela !== '' && $idLocal > 0) {
            $st = $tenant->prepare('UPDATE `' . $tabela . '` SET ativo = 0 WHERE id = :id');
            $st->execute(['id' => $idLocal]);
        }
        $st = $tenant->prepare(
            "UPDATE lex_vinculos SET status = 'inativo'
              WHERE tipo_entidade = :tipo AND id_lex = :lex"
        );
        $st->execute(['tipo' => $tipo, 'lex' => $idLex]);
    }

    private static function gravarVinculo(\PDO $tenant, string $tipo, int $idLocal, string $idLex, ?string $accountId): void
    {
        $st = $tenant->prepare(
            'INSERT INTO lex_vinculos (tipo_entidade, id_local, id_lex, account_id, origem, status)
             VALUES (:tipo, :local, :lex, :account, :origem, :status)'
        );
        $st->execute([
            'tipo' => $tipo,
            'local' => $idLocal,
            'lex' => $idLex,
            'account' => $accountId,
            'origem' => 'lex',
            'status' => 'ativo',
        ]);
    }

    private static function ativarVinculo(\PDO $tenant, string $tipo, string $idLex, ?string $accountId): void
    {
        $st = $tenant->prepare(
            "UPDATE lex_vinculos
                SET status = 'ativo', account_id = COALESCE(:account, account_id)
              WHERE tipo_entidade = :tipo AND id_lex = :lex"
        );
        $st->execute([
            'account' => $accountId,
            'tipo' => $tipo,
            'lex' => $idLex,
        ]);
    }

    private static function idVinculo(\PDO $tenant, string $tipo, string $idLex): int
    {
        $st = $tenant->prepare(
            'SELECT id_local FROM lex_vinculos WHERE tipo_entidade = :tipo AND id_lex = :lex LIMIT 1'
        );
        $st->execute(['tipo' => $tipo, 'lex' => $idLex]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int) $row['id_local'] : 0;
    }

    /**
     * @param array<string,mixed> $classificacao
     * @param array<string,mixed>|null $plano
     */
    private static function registrar(
        \PDO $tenant,
        array $classificacao,
        ?array $plano,
        string $modo,
        string $status,
        string $mensagem
    ): void {
        if (!self::tabelaExiste($tenant, 'lex_sincronizacoes')) {
            return;
        }
        $contagens = $plano['contagens'] ?? [];
        $st = $tenant->prepare(
            'INSERT INTO lex_sincronizacoes (sync_id, sync_date, modo, status, contagens_json, mensagem)
             VALUES (:sync_id, :sync_date, :modo, :status, :contagens, :mensagem)'
        );
        $st->execute([
            'sync_id' => $classificacao['sync_id'] ?? null,
            'sync_date' => $classificacao['sync_date'] ?? null,
            'modo' => $modo,
            'status' => $status,
            'contagens' => json_encode($contagens, JSON_UNESCAPED_UNICODE),
            'mensagem' => substr($mensagem, 0, 500),
        ]);
        $syncId = (int) $tenant->lastInsertId();
        if ($plano === null || $syncId < 1) {
            return;
        }
        $eventos = [];
        foreach ($plano['conflitos'] as $conflito) {
            $eventos[] = ['conflito', $conflito['tipo'] ?? null, $conflito['id_lex'] ?? null, $conflito['mensagem']];
        }
        foreach ($plano['ignorados'] as $ignorado) {
            $eventos[] = ['ignorado', null, $ignorado['id_lex'] ?? null, $ignorado['mensagem']];
        }
        if ((int) ($plano['inativacao_suspensa'] ?? 0) > 0) {
            $eventos[] = ['info', null, null, 'Inativação suspensa porque houve conflito nesta execução.'];
        }
        $ins = $tenant->prepare(
            'INSERT INTO lex_sincronizacao_eventos (sincronizacao_id, nivel, tipo_entidade, id_lex, mensagem)
             VALUES (:sync, :nivel, :tipo, :lex, :mensagem)'
        );
        $gravados = 0;
        foreach ($eventos as $evento) {
            if ($gravados >= 100) {
                break;
            }
            $ins->execute([
                'sync' => $syncId,
                'nivel' => $evento[0],
                'tipo' => $evento[1],
                'lex' => $evento[2],
                'mensagem' => substr((string) $evento[3], 0, 255),
            ]);
            $gravados++;
        }
    }

    private static function marcarMaster(\PDO $master, int $escolaId, string $resultado, string $syncId): void
    {
        if (!self::tabelaExiste($master, 'lex_escolas_config')) {
            return;
        }
        $st = $master->prepare(
            'UPDATE lex_escolas_config
                SET ultima_sincronizacao = NOW(), ultimo_resultado = :resultado, ultimo_sync_id = :sync
              WHERE escola_id = :id'
        );
        $st->execute([
            'resultado' => substr($resultado, 0, 40),
            'sync' => $syncId !== '' ? $syncId : null,
            'id' => $escolaId,
        ]);
    }

    /**
     * @param array<string, bool> $colunas
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    private static function filtrar(array $colunas, array $dados): array
    {
        $saida = [];
        foreach ($dados as $coluna => $valor) {
            if (isset($colunas[$coluna])) {
                $saida[$coluna] = $valor;
            }
        }
        return $saida;
    }

    /**
     * @param array<string, mixed> $dados
     * @param list<string> $obrigatorias
     */
    private static function exigirColunas(array $dados, array $obrigatorias, string $tabela): void
    {
        foreach ($obrigatorias as $coluna) {
            if (!array_key_exists($coluna, $dados)) {
                throw new \RuntimeException('A tabela ' . $tabela . ' não tem a coluna ' . $coluna . '.');
            }
        }
    }

    /**
     * @param array<string, mixed> $dados
     */
    private static function inserir(\PDO $pdo, string $tabela, array $dados): int
    {
        $colunas = array_keys($dados);
        $sql = 'INSERT INTO `' . $tabela . '` (`' . implode('`,`', $colunas) . '`) VALUES ('
            . implode(',', array_map(static fn (string $c): string => ':' . $c, $colunas)) . ')';
        $st = $pdo->prepare($sql);
        $st->execute($dados);
        return (int) $pdo->lastInsertId();
    }

    /**
     * @return array<string, bool>
     */
    private static function colunas(\PDO $pdo, string $tabela): array
    {
        $permitidas = [
            'alunos', 'professores', 'responsaveis', 'usuarios', 'alunos_responsaveis',
            'curso', 'serie', 'turmas', 'matricula', 'ano_letivo',
        ];
        if (!in_array($tabela, $permitidas, true)) {
            return [];
        }
        $mapa = [];
        foreach ($pdo->query('SHOW COLUMNS FROM `' . $tabela . '`') as $row) {
            $mapa[strtolower((string) $row['Field'])] = true;
        }
        return $mapa;
    }

    private static function tabelaExiste(\PDO $pdo, string $tabela): bool
    {
        $permitidas = [
            'lex_escolas_config', 'lex_vinculos', 'lex_sincronizacoes', 'lex_sincronizacao_eventos',
            'ano_letivo', 'serie', 'matricula',
        ];
        if (!in_array($tabela, $permitidas, true)) {
            return false;
        }
        $st = $pdo->query("SHOW TABLES LIKE '" . $tabela . "'");
        return $st !== false && $st->fetchColumn() !== false;
    }
}
