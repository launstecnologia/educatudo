<?php

use App\Modulos\Lex\Services\LexLoginService;
use PHPUnit\Framework\TestCase;

class LexLoginTest extends TestCase
{
    public function testDesafioPkceUsaS256(): void
    {
        $verifier = 'verifier-de-teste-com-tamanho-suficiente';
        $esperado = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $this->assertSame($esperado, LexLoginService::desafio($verifier));
        $this->assertGreaterThan(40, strlen($esperado));
    }

    public function testLocalizaPeloIdDaEscolaOuPelaConta(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO lex_vinculos (tipo_entidade, id_local, id_lex, account_id, status) VALUES ('aluno', 7, 'user-1', 'acc-1', 'ativo')");

        $porUsuario = LexLoginService::localizar($pdo, ['sub' => 'user-1'], 'aluno');
        $porConta = LexLoginService::localizar($pdo, ['account_id' => 'acc-1'], 'aluno');
        $porUserId = LexLoginService::localizar($pdo, ['user_id' => 'user-1'], 'aluno');
        $outroPainel = LexLoginService::localizar($pdo, ['sub' => 'user-1'], 'professor');
        $subNaContaDeOutro = LexLoginService::localizar($pdo, ['sub' => 'acc-1'], 'aluno');
        $claimIdIgnorado = LexLoginService::localizar($pdo, ['id' => 'user-1'], 'aluno');

        $this->assertSame(7, $porUsuario['id_local']);
        $this->assertSame('aluno', $porUsuario['tipo']);
        $this->assertSame(7, $porConta['id_local']);
        $this->assertSame(7, $porUserId['id_local']);
        $this->assertNull($outroPainel);
        $this->assertNull($subNaContaDeOutro);
        $this->assertNull($claimIdIgnorado);
    }

    public function testLocalizaPeloEmailQuandoOIdDaLexNaoConfere(): void
    {
        $pdo = $this->banco();
        $pdo->exec('CREATE TABLE alunos (id INTEGER, email TEXT, ativo INTEGER)');
        $pdo->exec("INSERT INTO alunos (id, email, ativo) VALUES (7, 'educatudo.familia1.aluno@educa.tudo', 1)");
        $pdo->exec("INSERT INTO lex_vinculos (tipo_entidade, id_local, id_lex, account_id, status) VALUES ('aluno', 7, 'user-1', 'acc-1', 'ativo')");

        $porEmail = LexLoginService::localizar($pdo, [
            'sub' => '82bf3094-5c0f-4a0a-a874-9777d0edc322',
            'email' => 'educatudo.familia1.aluno@educa.tudo',
        ], 'aluno');

        $this->assertSame(7, $porEmail['id_local']);
    }

    public function testLocalizaPeloRaQuandoOLoginConfere(): void
    {
        $pdo = $this->banco();
        $pdo->exec('CREATE TABLE alunos (id INTEGER, email TEXT, ra TEXT, nickname TEXT, ativo INTEGER)');
        $pdo->exec("INSERT INTO alunos (id, email, ra, nickname, ativo) VALUES (7, 'outro@educa.tudo', 'DDD81E91-F055-4F56-9754-937A0793813C', 'DDD81E91-F055-4F56-9754-937A0793813C', 1)");
        $pdo->exec("INSERT INTO lex_vinculos (tipo_entidade, id_local, id_lex, account_id, status) VALUES ('aluno', 7, 'user-1', 'acc-1', 'ativo')");

        $porRa = LexLoginService::localizar($pdo, [
            'sub' => '82bf3094-5c0f-4a0a-a874-9777d0edc322',
            'login' => 'ddd81e91-f055-4f56-9754-937a0793813c',
        ], 'aluno');

        $this->assertSame(7, $porRa['id_local']);
    }

    public function testMontaOEnderecoQueEncerraASessaoNaLex(): void
    {
        $url = LexLoginService::urlEncerrarSessao(
            'homologacao',
            'aaa.bbb.ccc',
            'https://educa-lex.educatudo.com/lex/saida'
        );

        $this->assertSame(
            'https://homolog-api.lex.education/sso/connect/full-endsession?id_token_hint=aaa.bbb.ccc&post_logout_redirect_uri=https%3A%2F%2Feduca-lex.educatudo.com%2Flex%2Fsaida',
            $url
        );
        $this->assertSame('', LexLoginService::urlEncerrarSessao('homologacao', '', 'https://educa-lex.educatudo.com/lex/saida'));
        $this->assertSame('', LexLoginService::urlEncerrarSessao('producao', "aaa\nbbb", 'https://educa-lex.educatudo.com/lex/saida'));
        $this->assertTrue(LexLoginService::retornoPermitido('https://educa-lex.educatudo.com/lex/saida', 'educa-lex.educatudo.com'));
        $this->assertFalse(LexLoginService::retornoPermitido('https://outra.educatudo.com/lex/saida', 'educa-lex.educatudo.com'));
        $this->assertFalse(LexLoginService::retornoPermitido('http://educa-lex.educatudo.com/lex/saida', 'educa-lex.educatudo.com'));
    }

    public function testRecusaQuandoDoisVinculosBatem(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO lex_vinculos (tipo_entidade, id_local, id_lex, account_id, status) VALUES ('aluno', 7, 'user-1', 'acc-1', 'ativo')");
        $pdo->exec("INSERT INTO lex_vinculos (tipo_entidade, id_local, id_lex, account_id, status) VALUES ('aluno', 8, 'user-2', 'acc-1', 'ativo')");
        $pdo->exec("INSERT INTO lex_vinculos (tipo_entidade, id_local, id_lex, account_id, status) VALUES ('aluno', 9, 'user-3', 'acc-3', 'ativo')");

        $this->assertNull(LexLoginService::localizar($pdo, ['account_id' => 'acc-1'], 'aluno'));
        $this->assertNull(LexLoginService::localizar($pdo, ['sub' => 'user-1', 'account_id' => 'acc-3'], 'aluno'));
    }

    public function testIdentificadorExigeNonceExpiracaoAudienciaEEmissor(): void
    {
        $metodo = new ReflectionMethod(LexLoginService::class, 'claims');
        $valido = [
            'sub' => 'sso-1',
            'nonce' => 'nonce-da-sessao',
            'exp' => time() + 300,
            'aud' => 'client-1',
            'iss' => 'https://homolog-api.lex.education/sso',
        ];

        $ok = $metodo->invoke(null, $this->jwt($valido), 'nonce-da-sessao', 'client-1', 'https://homolog-api.lex.education/sso');
        $this->assertSame('sso-1', $ok['sub']);

        $casos = [];
        $semNonce = $valido;
        unset($semNonce['nonce']);
        $casos[] = $semNonce;
        $semExp = $valido;
        unset($semExp['exp']);
        $casos[] = $semExp;
        $audErrada = $valido;
        $audErrada['aud'] = 'outro-client';
        $casos[] = $audErrada;
        $issErrado = $valido;
        $issErrado['iss'] = 'https://outro.example';
        $casos[] = $issErrado;

        foreach ($casos as $claims) {
            try {
                $metodo->invoke(null, $this->jwt($claims), 'nonce-da-sessao', 'client-1', 'https://homolog-api.lex.education/sso');
                $this->fail('O identificador deveria ser recusado.');
            } catch (RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    /**
     * @param array<string,mixed> $claims
     */
    private function jwt(array $claims): string
    {
        $cabecalho = rtrim(strtr(base64_encode('{"alg":"RS256"}'), '+/', '-_'), '=');
        $corpo = rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=');
        return $cabecalho . '.' . $corpo . '.assinatura';
    }

    private function banco(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE lex_vinculos (
                tipo_entidade TEXT,
                id_local INTEGER,
                id_lex TEXT,
                account_id TEXT,
                status TEXT
            )'
        );
        return $pdo;
    }
}
