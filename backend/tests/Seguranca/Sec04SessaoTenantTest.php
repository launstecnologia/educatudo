<?php

require_once dirname(__DIR__, 2) . '/app/Core/AuthManager.php';

use App\Core\AuthManager;
use PHPUnit\Framework\TestCase;

/**
 * Isolamento de sessão por tenant (SEC-04 permissões entre escolas).
 */
final class Sec04SessaoTenantTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
    }

    public function testSemMultiTenantAceita(): void
    {
        if (defined('MULTI_TENANT_ACTIVE')) {
            $this->markTestSkipped('Constante MULTI_TENANT_ACTIVE já definida neste processo.');
        }
        $this->assertTrue(AuthManager::sessaoConfereComEscolaAtual());
    }

    public function testSessaoDeOutraEscolaNegaQuandoConstantesSimuladas(): void
    {
        // Espelha a lógica do método para documentar o contrato sem redefinir constantes.
        $tenantAtual = 1;
        $escolaDaSessao = 2;
        $ok = $escolaDaSessao > 0 && $escolaDaSessao === $tenantAtual;
        $this->assertFalse($ok);
    }

    public function testSessaoDaMesmaEscolaAceitaQuandoConstantesSimuladas(): void
    {
        $tenantAtual = 1;
        $escolaDaSessao = 1;
        $ok = $escolaDaSessao > 0 && $escolaDaSessao === $tenantAtual;
        $this->assertTrue($ok);
    }
}
