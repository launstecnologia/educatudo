<?php

use PHPUnit\Framework\TestCase;

/**
 * Evidência estática SEC-10 F01–F05 (código pós-remediação).
 */
final class Sec10F01F05Test extends TestCase
{
    private function path(string $relativo): string
    {
        return dirname(__DIR__, 2) . '/' . ltrim($relativo, '/');
    }

    public function testF04LoginCsrfTemDeadlineNoServidor(): void
    {
        $src = file_get_contents($this->path('app/Painel/Auth/Controllers/AuthController.php'));
        $this->assertNotFalse($src);
        $this->assertStringContainsString('LOGIN_CSRF_TTL = 600', $src);
        $this->assertStringContainsString('login_csrf_expires_at', $src);
        $this->assertStringContainsString('empacotarLoginCsrfCookie', $src);
        $this->assertStringContainsString('desempacotarLoginCsrfCookie', $src);
    }

    public function testF05GetsChatNaoGravamEstado(): void
    {
        $src = file_get_contents($this->path('app/Painel/Integrations/Controllers/ChatController.php'));
        $this->assertNotFalse($src);

        $this->assertStringContainsString('function chatProfessor', $src);
        $this->assertStringContainsString('function chatAluno', $src);
        $this->assertStringContainsString('function buscarMensagensProfessor', $src);
        $this->assertStringContainsString('function buscarMensagensAluno', $src);
        $this->assertStringContainsString('function marcarMensagensLidasProfessor', $src);
        $this->assertStringContainsString('function marcarMensagensLidasAluno', $src);

        // Trechos de escrita residual só nos POSTs (enviar / marcar-lidas).
        $this->assertMatchesRegularExpression(
            '/function marcarMensagensLidasProfessor\(\)[\s\S]*SET lida = 1/',
            $src
        );
        $this->assertMatchesRegularExpression(
            '/function marcarMensagensLidasAluno\(\)[\s\S]*SET lida = 1/',
            $src
        );

        // Abertura GET não cria conversa.
        $this->assertStringContainsString('GET só lê: conversa inexistente', $src);
    }

    public function testF05RotasMarcarLidasExistem(): void
    {
        $teacher = file_get_contents($this->path('config/routes/teacher.php'));
        $student = file_get_contents($this->path('config/routes/student.php'));
        $this->assertNotFalse($teacher);
        $this->assertNotFalse($student);
        $this->assertStringContainsString("/professor/chat/marcar-lidas", $teacher);
        $this->assertStringContainsString("/chat-professor/marcar-lidas", $student);
    }

    public function testF03CsrfExcecoesSomenteSendBeaconJustificados(): void
    {
        $lista = require $this->path('config/csrf_excecoes.php');
        $this->assertIsArray($lista);
        // sendBeacon/beforeunload não enviam X-CSRF-TOKEN — lista curta e justificada
        $this->assertContains('/aluno/provas/bloco/', $lista);
        $this->assertContains('/aluno/provas/log-evento', $lista);
        $this->assertContains('/jogo-milhao/abandonar', $lista);
        $this->assertLessThanOrEqual(5, count($lista));
    }

    public function testF01HostMasterNuncaResolveComoEscola(): void
    {
        $bootstrap = file_get_contents($this->path('app/Core/bootstrap_multi_tenant.php'));
        $resolver = file_get_contents($this->path('app/Core/TenantResolver.php'));
        $this->assertNotFalse($bootstrap);
        $this->assertNotFalse($resolver);
        $this->assertStringContainsString('$hostReservadoMaster', $bootstrap);
        $this->assertStringContainsString('hostEhReservadoMaster', $resolver);
    }
}
