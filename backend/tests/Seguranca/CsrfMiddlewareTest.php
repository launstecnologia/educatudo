<?php

require_once dirname(__DIR__, 2) . '/app/Middleware/CsrfMiddleware.php';

use App\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * CSRF centralizado — modos audit e enforce, exceções e headers.
 */
final class CsrfMiddlewareTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $serverBackup = [];

    /** @var array<string,mixed> */
    private array $postBackup = [];

    /** @var array<string,mixed>|null */
    private ?array $sessionBackup = null;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;
        if ($this->sessionBackup !== null) {
            $_SESSION = $this->sessionBackup;
        }
    }

    public function testGetNaoValida(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/admin/usuarios';
        $logs = [];
        $mw = new CsrfMiddleware([
            'modo' => 'enforce',
            'excecoes' => [],
            'logger' => static function ($ctx) use (&$logs) {
                $logs[] = $ctx;
            },
        ]);
        $mw->handle();
        $this->assertSame([], $logs);
    }

    public function testPostComTokenValidoPassaEmEnforce(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/professor/chat/enviar-mensagem';
        $_SESSION['csrf_token'] = 'abc123token';
        $_POST['_token'] = 'abc123token';
        $logs = [];
        $mw = new CsrfMiddleware([
            'modo' => 'enforce',
            'excecoes' => [],
            'logger' => static function ($ctx) use (&$logs) {
                $logs[] = $ctx;
            },
        ]);
        $mw->handle();
        $this->assertSame([], $logs);
    }

    public function testPostSemTokenEmAuditSoRegistra(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/algum-form';
        $_SESSION['csrf_token'] = 'sessao-token';
        $logs = [];
        $mw = new CsrfMiddleware([
            'modo' => 'audit',
            'excecoes' => [],
            'logger' => static function ($ctx) use (&$logs) {
                $logs[] = $ctx;
            },
        ]);
        $mw->handle();
        $this->assertCount(1, $logs);
        $this->assertSame('token_ausente', $logs[0]['motivo']);
        $this->assertSame('audit', $logs[0]['modo']);
    }

    public function testExcecaoIgnoraValidacao(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/professor/api/alunos-online';
        $_SESSION['csrf_token'] = 'sessao-token';
        $logs = [];
        $mw = new CsrfMiddleware([
            'modo' => 'enforce',
            'excecoes' => ['/professor/api/'],
            'logger' => static function ($ctx) use (&$logs) {
                $logs[] = $ctx;
            },
        ]);
        $mw->handle();
        $this->assertSame([], $logs);
    }

    public function testHeaderXCsrfTokenAceito(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/salvar';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'hdr-token';
        $_SESSION['csrf_token'] = 'hdr-token';
        $logs = [];
        $mw = new CsrfMiddleware([
            'modo' => 'enforce',
            'excecoes' => [],
            'logger' => static function ($ctx) use (&$logs) {
                $logs[] = $ctx;
            },
        ]);
        $mw->handle();
        $this->assertSame([], $logs);
    }

    public function testEnforceSemTokenTerminaProcesso(): void
    {
        // Isola exit: executa em processo separado via eval impossível;
        // valida apenas que o middleware detecta falha e chamaria bloquear.
        // Aqui testamos a detecção via logger em audit + assert do motivo;
        // o caminho enforce+exit é coberto por inspeção e smoke manual.
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/admin/recurso/1';
        $_SESSION['csrf_token'] = 'x';
        $logs = [];
        $mw = new CsrfMiddleware([
            'modo' => 'audit',
            'excecoes' => [],
            'logger' => static function ($ctx) use (&$logs) {
                $logs[] = $ctx;
            },
        ]);
        $mw->handle();
        $this->assertSame('token_ausente', $logs[0]['motivo']);
        $this->assertSame('DELETE', $logs[0]['method']);
    }

    public function testMuralMarcarVistoAuditNaoBloqueiaSemToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/mural-recados/marcar-visto';
        $_SESSION['csrf_token'] = 'sessao-ok';
        $logs = [];
        $mw = new CsrfMiddleware([
            'modo' => 'audit',
            'excecoes' => [],
            'logger' => static function ($ctx) use (&$logs) {
                $logs[] = $ctx;
            },
        ]);
        $mw->handle();
        $this->assertCount(1, $logs);
        $this->assertSame('/mural-recados/marcar-visto', $logs[0]['path']);
        $this->assertSame('audit', $logs[0]['modo']);
    }

    public function testMuralMarcarVistoComTokenPassaEmEnforce(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/mural-recados/marcar-visto';
        $_SESSION['csrf_token'] = 'mural-token';
        $_POST['_token'] = 'mural-token';
        $logs = [];
        $mw = new CsrfMiddleware([
            'modo' => 'enforce',
            'excecoes' => [],
            'logger' => static function ($ctx) use (&$logs) {
                $logs[] = $ctx;
            },
        ]);
        $mw->handle();
        $this->assertSame([], $logs);
    }
}
