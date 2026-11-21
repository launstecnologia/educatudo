<?php

use PHPUnit\Framework\TestCase;

/**
 * Evidência estática: seleção da conexão master ≠ privilégio.
 * Privilegio master exige master_user_id (controllers) e, em produção,
 * caminho /master apenas no MASTER_DOMAIN (bootstrap_multi_tenant).
 */
final class MasterAcessoCodigoTest extends TestCase
{
    public function testBootstrapRestringeMasterPathEmProducao(): void
    {
        $bootstrap = file_get_contents(dirname(__DIR__, 2) . '/app/Core/bootstrap_multi_tenant.php');
        $this->assertNotFalse($bootstrap);
        $this->assertStringContainsString('$isProducaoBootstrap', $bootstrap);
        $this->assertStringContainsString('MASTER_DOMAIN', $bootstrap);
        $this->assertStringContainsString('educatudo_responder_host_desconhecido', $bootstrap);
        $this->assertStringContainsString('$isMasterPath', $bootstrap);
        $this->assertStringContainsString('$isMasterDomain', $bootstrap);
        $this->assertStringContainsString('$masterDomainConfigurado', $bootstrap);
        // Fail-closed: produção sem domínio master não libera /master em host escolar.
        $this->assertStringContainsString('$isProducaoBootstrap || $masterDomainConfigurado', $bootstrap);
    }

    public function testMasterAuthNaoRestauraCookiePersistente(): void
    {
        $auth = file_get_contents(dirname(__DIR__, 2) . '/app/Painel/Master/Controllers/MasterAuthController.php');
        $this->assertNotFalse($auth);
        $this->assertStringNotContainsString('restoreMasterSessionFromCookie', $auth);
        $this->assertStringNotContainsString('setMasterAuthCookie', $auth);
        $this->assertStringNotContainsString('master-default-secret-change-me', $auth);
        $this->assertStringContainsString('clearMasterAuthCookie', $auth);
    }

    public function testControllersMasterExigemSessaoMaster(): void
    {
        $auth = file_get_contents(dirname(__DIR__, 2) . '/app/Painel/Master/Controllers/MasterAuthController.php');
        $escolas = file_get_contents(dirname(__DIR__, 2) . '/app/Painel/Master/Controllers/MasterEscolasController.php');
        $this->assertNotFalse($auth);
        $this->assertNotFalse($escolas);
        $this->assertStringContainsString("SESSION_MASTER_USER_ID = 'master_user_id'", $auth);
        $this->assertStringContainsString('requireMasterAuth', $auth);
        $this->assertStringContainsString("SESSION_MASTER_USER_ID = 'master_user_id'", $escolas);
        $this->assertStringContainsString('requireMaster()', $escolas);
    }

    public function testSessaoEscolaNaoUsaChaveMaster(): void
    {
        $authManager = file_get_contents(dirname(__DIR__, 2) . '/app/Core/AuthManager.php');
        $this->assertNotFalse($authManager);
        $this->assertStringContainsString("\$_SESSION['user_id']", $authManager);
        $this->assertStringContainsString("\$_SESSION['user_type']", $authManager);
        $this->assertStringNotContainsString("\$_SESSION['master_user_id']", $authManager);
    }

    public function testXTenantIgnoradoEmProducao(): void
    {
        $resolver = file_get_contents(dirname(__DIR__, 2) . '/app/Core/TenantResolver.php');
        $this->assertNotFalse($resolver);
        $this->assertStringContainsString("ENVIRONMENT === 'production'", $resolver);
        $this->assertStringContainsString('slugFromHeader', $resolver);
    }
}
