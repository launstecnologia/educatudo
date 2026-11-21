<?php

use PHPUnit\Framework\TestCase;

/** Etapa 8 — rotas migradas, código morto e preservação. */
final class Etapa8ConsolidacaoControllersTest extends TestCase
{
    public function testCalendarioAlunoNoControllerDedicado(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/config/routes/student.php');
        $this->assertStringContainsString('AlunoCalendarioController@index', $routes);
        $this->assertStringNotContainsString('StudentController@calendarioLetivo', $routes);
        $this->assertFileExists(
            dirname(__DIR__, 2) . '/app/Painel/User/Controllers/AlunoCalendarioController.php'
        );
    }

    public function testStudentControllerSemHelpersMortosDeChatELimite(): void
    {
        $src = file_get_contents(
            dirname(__DIR__, 2) . '/app/Painel/User/Controllers/StudentController.php'
        );
        $this->assertStringNotContainsString('function generateAIResponse', $src);
        $this->assertStringNotContainsString('function podeExecutarAcao', $src);
        $this->assertStringNotContainsString('function getLimitesDiarios', $src);
        $this->assertStringNotContainsString('function calendarioLetivo', $src);
        $this->assertStringContainsString('AlunoLimiteDiarioService', $src);
        $this->assertStringContainsString('function vozParaTexto', $src);
        $this->assertStringContainsString('function carteira', $src);
        $this->assertStringContainsString('function salvarRedacaoJornada', $src);
    }

    public function testChatSafetyMonitorRemovido(): void
    {
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 2) . '/app/Infra/Services/ChatSafetyMonitorService.php'
        );
        $map = file_get_contents(dirname(__DIR__, 2) . '/app/Compat/services_map.php');
        $this->assertStringNotContainsString('ChatSafetyMonitorService', $map);
    }

    public function testTudinhaServiceEChatFormatterPreservados(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/app/Infra/Services/TudinhaService.php');
        $this->assertFileExists(dirname(__DIR__, 2) . '/app/Utils/ChatFormatter.php');
        $routes = file_get_contents(dirname(__DIR__, 2) . '/config/routes/student.php');
        $this->assertStringContainsString('ChatTudinhaDescontinuadoController', $routes);
        $this->assertStringContainsString('chat-professor', $routes);
    }

    public function testRedacoesIaAindaNoModulo(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/app/Modulos/redacoes/routes.php');
        $this->assertStringContainsString('AlunoRedacaoLegadoController@gerarTema', $routes);
        $this->assertStringContainsString('AlunoRedacaoLegadoController@corrigir', $routes);
    }

    public function testServiceWorkerSemRotaChatExclusiva(): void
    {
        $sw = file_get_contents(dirname(__DIR__, 2) . '/public/service-worker.js');
        $this->assertStringNotContainsString("'/chat'", $sw);
        $this->assertStringNotContainsString('"/chat"', $sw);
    }

    public function testFeatureGateChatPreservadoParaJornadas(): void
    {
        $fg = file_get_contents(dirname(__DIR__, 2) . '/app/Core/FeatureGate.php');
        $this->assertStringContainsString("'/chat' => 'chat'", $fg);
    }
}
