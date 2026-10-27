<?php

namespace App\Modulos\Lex\Services;

use App\Core\Database;

/**
 * Inicia o login da pessoa na LEX (authorization code + PKCE).
 * O token client_credentials da sincronização não vira sessão.
 */
class LexLoginService
{
    public const ESCOPOS = 'openid profile offline_access';

    /** @var array<string, string> */
    private const PERFIS = [
        'aluno' => 'aluno',
        'admin' => 'usuario',
        'professor' => 'professor',
        'pais' => 'responsavel',
    ];

    public static function perfilValido(string $perfil): bool
    {
        return isset(self::PERFIS[$perfil]);
    }

    public static function disponivel(): bool
    {
        if (!defined('TENANT_ID') || (int) TENANT_ID < 1) {
            return false;
        }
        try {
            $config = self::configuracao();
        } catch (\Throwable $e) {
            return false;
        }
        return $config !== null && $config['ativo'] && $config['client_id'] !== '' && $config['tem_segredo'];
    }

    /**
     * @return array{url:string,state:string,verifier:string,nonce:string}
     */
    public static function iniciar(string $perfil, string $redirectUri): array
    {
        if (!self::perfilValido($perfil)) {
            throw new \RuntimeException('Painel de login inválido.');
        }
        $config = self::configuracao();
        if ($config === null || !$config['ativo'] || $config['client_id'] === '') {
            throw new \RuntimeException('A integração LEX não está ativa nesta escola.');
        }

        $verifier = self::aleatorio(64);
        $state = self::aleatorio(32);
        $nonce = self::aleatorio(32);
        $parametros = [
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirectUri,
            'code_challenge_method' => 'S256',
            'code_challenge' => self::desafio($verifier),
            'scope' => self::ESCOPOS,
            'state' => $state,
            'nonce' => $nonce,
        ];

        return [
            'url' => LexAutenticacaoService::urlBase($config['ambiente']) . '/sso/connect/authorize?' . http_build_query($parametros, '', '&', PHP_QUERY_RFC3986),
            'state' => $state,
            'verifier' => $verifier,
            'nonce' => $nonce,
        ];
    }

    /**
     * @param array{state:string,verifier:string,nonce:string,perfil:string,expira:int} $sessao
     * @return array{tipo:string,id_local:int,id_token:string}
     */
    public static function concluir(array $sessao, string $code, string $state, string $redirectUri): array
    {
        if (!hash_equals((string) ($sessao['state'] ?? ''), $state)) {
            throw new \RuntimeException('O retorno da LEX não confere com o início do login.');
        }
        if ((int) ($sessao['expira'] ?? 0) < time()) {
            throw new \RuntimeException('O login da LEX expirou. Tente de novo.');
        }
        $perfil = (string) ($sessao['perfil'] ?? '');
        if (!self::perfilValido($perfil)) {
            throw new \RuntimeException('Painel de login inválido.');
        }
        $config = self::configuracao();
        if ($config === null || !$config['ativo'] || !$config['tem_segredo'] || $config['client_id'] === '') {
            throw new \RuntimeException('A integração LEX não está ativa nesta escola.');
        }

        $cliente = new LexClienteHttp();
        $base = LexAutenticacaoService::urlBase($config['ambiente']);
        $tokens = $cliente->trocarCodigoAutorizacao(
            $base,
            $config['client_id'],
            $config['client_secret'],
            $code,
            $redirectUri,
            (string) $sessao['verifier']
        );
        $claims = self::claims(
            $tokens['id_token'],
            (string) $sessao['nonce'],
            $config['client_id'],
            $base . '/sso'
        );
        $info = $cliente->getJson($base . '/sso/connect/userinfo', $tokens['access_token'], 20);
        $claims = self::completarClaims($claims, $info);

        $pdo = Database::getInstance()->getPdo();
        $vinculo = self::localizar($pdo, $claims, self::PERFIS[$perfil]);
        if ($vinculo === null) {
            $claims = self::mesclarConta($claims, self::contaDaPessoa($cliente, $config, $claims));
            $vinculo = self::localizar($pdo, $claims, self::PERFIS[$perfil]);
        }
        if ($vinculo === null) {
            error_log('[lex] login sem vinculo chaves=' . implode(',', array_keys($claims)));
            throw new \RuntimeException('Esta conta LEX ainda não foi sincronizada neste painel.');
        }
        $vinculo['id_token'] = trim((string) ($tokens['id_token'] ?? ''));
        return $vinculo;
    }

    /**
     * Encerra a sessão na LEX. Sem o token do login, o EducaTudo sai só localmente.
     */
    public static function urlEncerrarSessao(string $ambiente, string $idToken, string $retorno): string
    {
        $idToken = trim($idToken);
        $retorno = trim($retorno);
        if ($idToken === '' || $retorno === '' || preg_match('/[\s\r\n]/', $idToken) === 1) {
            return '';
        }
        $parametros = [
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $retorno,
        ];
        return LexAutenticacaoService::urlBase($ambiente)
            . '/sso/connect/full-endsession?'
            . http_build_query($parametros, '', '&', PHP_QUERY_RFC3986);
    }

    public static function urlSaida(string $idToken, string $retorno): string
    {
        $host = parse_url((string) (defined('URL') ? URL : ''), PHP_URL_HOST);
        $config = self::configuracao();
        if ($config === null || !$config['ativo'] || !is_string($host) || !self::retornoPermitido($retorno, $host)) {
            return '';
        }
        return self::urlEncerrarSessao((string) $config['ambiente'], $idToken, $retorno);
    }

    public static function retornoPermitido(string $retorno, string $hostEsperado): bool
    {
        $partes = parse_url($retorno);
        if (!is_array($partes)) {
            return false;
        }
        $scheme = strtolower((string) ($partes['scheme'] ?? ''));
        $host = strtolower((string) ($partes['host'] ?? ''));
        $hostEsperado = strtolower($hostEsperado);
        $local = in_array($host, ['localhost', '127.0.0.1'], true);
        if ($host === '' || $host !== $hostEsperado || isset($partes['user']) || isset($partes['query']) || isset($partes['fragment'])) {
            return false;
        }
        if ($scheme !== 'https' && !($scheme === 'http' && $local)) {
            return false;
        }
        return ($partes['path'] ?? '') === '/lex/saida';
    }

    public static function desafio(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    /**
     * @param array<string,mixed> $claims
     * @return array{tipo:string,id_local:int}|null
     */
    public static function localizar(\PDO $tenant, array $claims, string $tipoEntidade): ?array
    {
        if (self::tipoSessao($tipoEntidade) === '') {
            return null;
        }

        $encontrados = [];
        $porIdLex = $tenant->prepare(
            "SELECT id_local FROM lex_vinculos
              WHERE tipo_entidade = :tipo AND status = 'ativo' AND id_lex = :id"
        );
        foreach (['sub', 'user_id', 'userId'] as $chave) {
            $valor = trim((string) ($claims[$chave] ?? ''));
            if ($valor === '') {
                continue;
            }
            $porIdLex->execute(['tipo' => $tipoEntidade, 'id' => $valor]);
            foreach ($porIdLex->fetchAll(\PDO::FETCH_COLUMN) as $local) {
                if ((int) $local > 0) {
                    $encontrados[(int) $local] = true;
                }
            }
        }

        $porConta = $tenant->prepare(
            "SELECT id_local FROM lex_vinculos
              WHERE tipo_entidade = :tipo AND status = 'ativo' AND account_id = :account"
        );
        foreach (['account_id', 'accountId'] as $chave) {
            $valor = trim((string) ($claims[$chave] ?? ''));
            if ($valor === '') {
                continue;
            }
            $porConta->execute(['tipo' => $tipoEntidade, 'account' => $valor]);
            foreach ($porConta->fetchAll(\PDO::FETCH_COLUMN) as $local) {
                if ((int) $local > 0) {
                    $encontrados[(int) $local] = true;
                }
            }
        }

        foreach (['email', 'academic_email', 'academicEmail'] as $chave) {
            $email = strtolower(trim((string) ($claims[$chave] ?? '')));
            if ($email === '') {
                continue;
            }
            foreach (self::locaisPorEmail($tenant, $tipoEntidade, $email) as $local) {
                $encontrados[$local] = true;
            }
        }
        if ($tipoEntidade === 'aluno') {
            foreach (['login', 'preferred_username', 'preferredUsername'] as $chave) {
                $login = strtolower(trim((string) ($claims[$chave] ?? '')));
                if ($login === '') {
                    continue;
                }
                foreach (self::locaisPorRa($tenant, $login) as $local) {
                    $encontrados[$local] = true;
                }
            }
        }

        if (count($encontrados) !== 1) {
            return null;
        }

        return ['tipo' => self::tipoSessao($tipoEntidade), 'id_local' => (int) array_key_first($encontrados)];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function configuracao(): ?array
    {
        if (!defined('TENANT_ID') || (int) TENANT_ID < 1) {
            return null;
        }
        return LexSincronizacaoService::lerConfig(Database::createMasterPdo(), (int) TENANT_ID);
    }

    /**
     * O id_token prova o login. O userinfo traz user_id, account_id e e-mail.
     *
     * @param array<string,mixed> $claims
     * @param array<string,mixed> $info
     * @return array<string,mixed>
     */
    private static function completarClaims(array $claims, array $info): array
    {
        $subToken = trim((string) ($claims['sub'] ?? ''));
        $subInfo = trim((string) ($info['sub'] ?? ''));
        if ($subToken !== '' && $subInfo !== '' && !hash_equals($subToken, $subInfo)) {
            error_log('[lex] userinfo com sub diferente do token');
            throw new \RuntimeException('O retorno da LEX não confere com o início do login.');
        }
        if ($claims === []) {
            return $info;
        }
        foreach (['user_id', 'userId', 'account_id', 'accountId', 'email', 'academic_email', 'academicEmail', 'login', 'preferred_username', 'preferredUsername'] as $chave) {
            $valor = trim((string) ($info[$chave] ?? ''));
            if ($valor !== '' && trim((string) ($claims[$chave] ?? '')) === '') {
                $claims[$chave] = $valor;
            }
        }
        return $claims;
    }

    /**
     * A conta do sync não é o token. O id dela pode ter outro nome e não invalida o login.
     *
     * @param array<string,mixed> $claims
     * @param array<string,mixed> $conta
     * @return array<string,mixed>
     */
    private static function mesclarConta(array $claims, array $conta): array
    {
        if ($conta === []) {
            return $claims;
        }
        $idEscola = trim((string) ($conta['user_id'] ?? $conta['userId'] ?? $conta['id'] ?? ''));
        if ($idEscola !== '' && trim((string) ($claims['user_id'] ?? '')) === '') {
            $claims['user_id'] = $idEscola;
        }
        foreach (['account_id', 'accountId', 'email', 'academic_email', 'academicEmail', 'login', 'preferred_username', 'preferredUsername'] as $chave) {
            $valor = trim((string) ($conta[$chave] ?? ''));
            if ($valor !== '' && trim((string) ($claims[$chave] ?? '')) === '') {
                $claims[$chave] = $valor;
            }
        }
        return $claims;
    }

    /**
     * @return array<string,mixed>
     */
    private static function claims(string $idToken, string $nonce, string $clientId, string $issuer): array
    {
        if ($idToken === '') {
            return [];
        }
        $partes = explode('.', $idToken);
        if (count($partes) !== 3) {
            throw new \RuntimeException('A LEX devolveu um identificador inválido.');
        }
        $json = base64_decode(strtr($partes[1], '-_', '+/'), true);
        $claims = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($claims)) {
            throw new \RuntimeException('A LEX devolveu um identificador inválido.');
        }
        $nonceToken = trim((string) ($claims['nonce'] ?? ''));
        if ($nonceToken === '' || !hash_equals($nonce, $nonceToken)) {
            throw new \RuntimeException('O retorno da LEX não confere com o início do login.');
        }
        if (!isset($claims['exp']) || !is_numeric($claims['exp']) || (int) $claims['exp'] < time()) {
            throw new \RuntimeException('O login da LEX expirou. Tente de novo.');
        }
        if (!self::audienciaConfere($claims['aud'] ?? null, $clientId)) {
            throw new \RuntimeException('A LEX devolveu um identificador inválido.');
        }
        $iss = rtrim(trim((string) ($claims['iss'] ?? '')), '/');
        if ($iss === '' || !hash_equals(rtrim($issuer, '/'), $iss)) {
            throw new \RuntimeException('A LEX devolveu um identificador inválido.');
        }
        return $claims;
    }

    private static function audienciaConfere(mixed $aud, string $clientId): bool
    {
        if (is_string($aud)) {
            return $aud !== '' && hash_equals($clientId, $aud);
        }
        if (!is_array($aud)) {
            return false;
        }
        foreach ($aud as $item) {
            if (is_string($item) && hash_equals($clientId, $item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * O login devolve um id diferente do sync. A conta traz o e-mail que já está no aluno.
     *
     * @param array<string,mixed> $config
     * @param array<string,mixed> $claims
     * @return array<string,mixed>
     */
    private static function contaDaPessoa(LexClienteHttp $cliente, array $config, array $claims): array
    {
        $tenant = trim((string) ($config['tenant_id'] ?? ''));
        if ($tenant === '') {
            return [];
        }
        $ids = [];
        foreach (['account_id', 'accountId', 'user_id', 'userId', 'sub'] as $chave) {
            $valor = trim((string) ($claims[$chave] ?? ''));
            if ($valor !== '' && preg_match('/^[0-9a-fA-F-]{32,40}$/', $valor) === 1) {
                $ids[$valor] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        try {
            $auth = new LexAutenticacaoService($cliente, new LexCacheTokenRedis(), dirname(__DIR__, 4) . '/storage/tmp');
            $token = $auth->obterToken($config);
        } catch (\Throwable $e) {
            return [];
        }

        $base = LexAutenticacaoService::urlBase((string) ($config['ambiente'] ?? ''));
        foreach (array_keys($ids) as $id) {
            $url = $base . '/sync/v1/accounts/' . rawurlencode($id) . '?tenant_id=' . rawurlencode($tenant);
            try {
                $conta = $cliente->getJson($url, (string) ($token['access_token'] ?? ''), 20);
            } catch (LexExcecaoHttp $e) {
                continue;
            }
            if ($conta !== []) {
                return $conta;
            }
        }
        return [];
    }

    /**
     * @return list<int>
     */
    private static function locaisPorRa(\PDO $tenant, string $login): array
    {
        $st = $tenant->prepare(
            "SELECT vinculo.id_local
               FROM lex_vinculos vinculo
               INNER JOIN alunos pessoa ON pessoa.id = vinculo.id_local AND pessoa.ativo = 1
              WHERE vinculo.tipo_entidade = 'aluno'
                AND vinculo.status = 'ativo'
                AND (LOWER(pessoa.ra) = :login OR LOWER(pessoa.nickname) = :login)"
        );
        $st->execute(['login' => $login]);
        $locais = [];
        foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $local) {
            if ((int) $local > 0) {
                $locais[] = (int) $local;
            }
        }
        return $locais;
    }

    /**
     * O id do sync e o id da tela da LEX nem sempre são o mesmo. O e-mail é o elo.
     *
     * @return list<int>
     */
    private static function locaisPorEmail(\PDO $tenant, string $tipoEntidade, string $email): array
    {
        $tabelas = [
            'aluno' => 'alunos',
            'professor' => 'professores',
            'responsavel' => 'responsaveis',
            'usuario' => 'usuarios',
        ];
        $tabela = $tabelas[$tipoEntidade] ?? '';
        if ($tabela === '') {
            return [];
        }

        $filtroTipo = $tipoEntidade === 'usuario' ? " AND pessoa.tipo = 'admin_escola'" : '';
        $st = $tenant->prepare(
            "SELECT vinculo.id_local
               FROM lex_vinculos vinculo
               INNER JOIN {$tabela} pessoa ON pessoa.id = vinculo.id_local AND pessoa.ativo = 1
              WHERE vinculo.tipo_entidade = :tipo
                AND vinculo.status = 'ativo'
                AND LOWER(pessoa.email) = :email{$filtroTipo}"
        );
        $st->execute(['tipo' => $tipoEntidade, 'email' => $email]);
        $locais = [];
        foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $local) {
            if ((int) $local > 0) {
                $locais[] = (int) $local;
            }
        }
        return $locais;
    }

    private static function tipoSessao(string $tipoEntidade): string
    {
        return [
            'aluno' => 'aluno',
            'usuario' => 'admin',
            'professor' => 'professor',
            'responsavel' => 'pai',
        ][$tipoEntidade] ?? '';
    }

    private static function aleatorio(int $bytes): string
    {
        return self::base64Url(random_bytes($bytes));
    }

    private static function base64Url(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }
}
