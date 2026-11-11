<?php

use App\Core\Database;
use App\Modulos\ModelosDocumentos\Services\AssistenteDocumentoService;
use PHPUnit\Framework\TestCase;

final class AssistenteDocumentoTest extends TestCase
{
    public function testConsultaExigeProprietarioAdministradorComPermissao(): void
    {
        $db = $this->createMock(Database::class);
        $job = ['user_id' => 42, 'user_role' => 'admin'];
        $admin = ['id' => 42, 'tipo' => 'admin_escola', 'perfil_admin' => 'dev'];

        self::assertTrue(AssistenteDocumentoService::podeConsultar($db, $admin, $job));
        self::assertFalse(AssistenteDocumentoService::podeConsultar($db, ['id' => 43] + $admin, $job));
        self::assertFalse(AssistenteDocumentoService::podeConsultar($db, ['tipo' => 'professor'] + $admin, $job));
        self::assertFalse(AssistenteDocumentoService::podeConsultar($db, ['perfil_admin' => 'financeiro'] + $admin, $job));
    }

    public function testMensagemComPapelDeSistemaERejeitada(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AssistenteDocumentoService::mensagensDoPedido(json_encode([
            ['role' => 'system', 'content' => 'Mensagem fora da conversa'],
            ['role' => 'user', 'content' => 'Qual variável insere a foto?'],
        ]));
    }

    public function testDuvidaSemHtmlNaoGeraPropostaDeLayout(): void
    {
        self::assertStringContainsString('{{aluno_foto_html}}', AssistenteDocumentoService::prompt());
        self::assertSame(
            ['mensagem' => 'Use {{aluno_foto_html}}.', 'estrutura' => null],
            AssistenteDocumentoService::interpretarResposta("```json\n{\"mensagem\":\"Use {{aluno_foto_html}}.\",\"html\":\"\"}\n```")
        );
    }

    public function testStreamingExibeSomenteTextoComEscapesCompletos(): void
    {
        self::assertSame('', AssistenteDocumentoService::mensagemParcial('{"mensa'));
        self::assertSame('Olá', AssistenteDocumentoService::mensagemParcial('{"mensagem":"Olá\u00'));
        self::assertSame("Olá\n\"turma\"", AssistenteDocumentoService::mensagemParcial(
            '{"mensagem":"Ol\u00e1\n\"turma\"","html":"<p>rascunho'
        ));
    }
}
