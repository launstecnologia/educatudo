<?php

use PHPUnit\Framework\TestCase;

/**
 * Etapa 7 — Chat Tudinha descontinuado: rotas, UI e preservação de compartilhados.
 */
final class ChatTudinhaDescontinuacaoTest extends TestCase
{
    private function studentRoutes(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/config/routes/student.php');
    }

    public function testRotasChatApontamDescontinuado(): void
    {
        $routes = $this->studentRoutes();
        $this->assertStringContainsString('ChatTudinhaDescontinuadoController@indisponivel', $routes);
        foreach ([
            "'/chat'",
            "'/chat/conversa'",
            "'/chat/mensagem'",
            "'/chat/mensagem-stream'",
            "'/chat/upload-imagem'",
            "'/chat/gerar-imagem'",
            "'/chat/voz-realtime/iniciar'",
            "'/chat/mensagens'",
            "'/chat/conversa/excluir'",
        ] as $uri) {
            $this->assertStringContainsString($uri, $routes);
        }
        $this->assertStringNotContainsString('StudentController@chat', $routes);
        $this->assertStringNotContainsString('StudentController@sendMessage', $routes);
        $this->assertStringNotContainsString('StudentController@sendMessageStream', $routes);
        $this->assertStringNotContainsString('StudentController@createConversation', $routes);
    }

    public function testChatProfessorPreservado(): void
    {
        $routes = $this->studentRoutes();
        $this->assertStringContainsString("'/chat-professor'", $routes);
        $this->assertStringContainsString('Integrations/ChatController@indexAluno', $routes);
    }

    public function testVozParaTextoProvasPreservado(): void
    {
        $routes = $this->studentRoutes();
        $this->assertStringContainsString("'/aluno/provas/voz-para-texto'", $routes);
        $this->assertStringContainsString('StudentController@vozParaTexto', $routes);
        $src = file_get_contents(dirname(__DIR__, 2) . '/app/Painel/User/Controllers/StudentController.php');
        $this->assertStringContainsString('function vozParaTexto()', $src);
        $this->assertStringNotContainsString('function sendMessage()', $src);
        $this->assertStringNotContainsString('function chat()', $src);
    }

    public function testControllerRetorna410SemConsumirCreditos(): void
    {
        $src = file_get_contents(
            dirname(__DIR__, 2) . '/app/Painel/User/Controllers/ChatTudinhaDescontinuadoController.php'
        );
        $this->assertStringContainsString('410', $src);
        $this->assertStringContainsString('chat_tudinha_descontinuado', $src);
        $this->assertStringNotContainsString('CreditosService', $src);
        $this->assertStringNotContainsString('OpenAI', $src);
        $this->assertStringNotContainsString('TudinhaChatService', $src);
        $this->assertStringNotContainsString('tudinha_conversas', $src);
        $this->assertStringNotContainsString('tudinha_mensagens', $src);
    }

    public function testSidebarSemLinkChat(): void
    {
        $sidebar = file_get_contents(
            dirname(__DIR__, 2) . '/app/Views/layouts/components/student_sidebar.php'
        );
        $this->assertStringNotContainsString('URL ?>/chat"', $sidebar);
        $this->assertStringNotContainsString("URL ?>/chat'", $sidebar);
    }

    public function testRedacoesIaRotasIntactas(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/app/Modulos/redacoes/routes.php');
        $this->assertStringContainsString('AlunoRedacaoLegadoController@gerarTema', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@corrigir', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@transcreverImagem', $routes);
    }

    public function testTudinhaServicePreservadoParaJornadas(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/app/Infra/Services/TudinhaService.php');
        $jornada = file_get_contents(
            dirname(__DIR__, 2) . '/app/Modulos/jornadas/Controllers/JourneyController.php'
        );
        $this->assertStringContainsString('TudinhaService', $jornada);
        $this->assertStringContainsString('gerarExplicacaoSemHistorico', $jornada);
    }

    public function testHistoricoTabelasNaoDropadasNoCodigo(): void
    {
        // Sem migration de DROP nesta etapa — inventário estático.
        $migrations = glob(dirname(__DIR__, 2) . '/database/migrations/*tudinha*') ?: [];
        foreach ($migrations as $file) {
            $sql = file_get_contents($file);
            $this->assertStringNotContainsString('DROP TABLE tudinha_conversas', $sql);
            $this->assertStringNotContainsString('DROP TABLE tudinha_mensagens', $sql);
        }
        $this->assertTrue(true);
    }

    public function testCreditosRegistryChatNaoRemovido(): void
    {
        // Chaves permanecem no catálogo (ledger histórico); endpoints não debitam mais.
        $reg = file_get_contents(dirname(__DIR__, 2) . '/app/Core/CreditosModuleRegistry.php');
        $this->assertStringContainsString("'tudinha_mensagem'", $reg);
        $this->assertStringContainsString("'tudinha_chat_imagem'", $reg);
        $this->assertStringContainsString("'tudinha_voz_realtime'", $reg);
    }

    public function testIsolamentoTenantSemEscolaIdNoHandler(): void
    {
        $src = file_get_contents(
            dirname(__DIR__, 2) . '/app/Painel/User/Controllers/ChatTudinhaDescontinuadoController.php'
        );
        $this->assertStringNotContainsString('escola_id', $src);
    }
}
