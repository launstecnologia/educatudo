<?php

require_once dirname(__DIR__, 2) . '/app/Core/TenantResolver.php';

use App\Core\TenantResolver;
use PHPUnit\Framework\TestCase;

/**
 * Validação fail-closed do cache Redis de tenant (escola desativada / corrompido).
 */
final class TenantCacheValidacaoTest extends TestCase
{
    public function testCacheAtivoComDominioCoerenteAceita(): void
    {
        $tenant = TenantResolver::validarTenantCacheado(
            ['id' => 1, 'slug' => 'colag', 'dominio' => 'colag.localhost', 'ativo' => 1],
            'colag.localhost',
            null
        );
        $this->assertNotNull($tenant);
        $this->assertSame(1, $tenant['id']);
        $this->assertSame(1, $tenant['ativo']);
    }

    public function testCacheSemAtivoEDescartado(): void
    {
        $tenant = TenantResolver::validarTenantCacheado(
            ['id' => 1, 'slug' => 'colag', 'dominio' => 'colag.localhost'],
            'colag.localhost',
            null
        );
        $this->assertNull($tenant);
    }

    public function testCacheInativoEDescartado(): void
    {
        $tenant = TenantResolver::validarTenantCacheado(
            ['id' => 1, 'slug' => 'colag', 'dominio' => 'colag.localhost', 'ativo' => 0],
            'colag.localhost',
            null
        );
        $this->assertNull($tenant);
    }

    public function testDominioDivergenteNaoSelecionaOutroTenant(): void
    {
        $tenant = TenantResolver::validarTenantCacheado(
            ['id' => 2, 'slug' => 'escola2', 'dominio' => 'escola2.localhost', 'ativo' => 1],
            'colag.localhost',
            null
        );
        $this->assertNull($tenant);
    }

    public function testPayloadCorrompidoFalhaSeguro(): void
    {
        $this->assertNull(TenantResolver::validarTenantCacheado(null, 'colag.localhost', null));
        $this->assertNull(TenantResolver::validarTenantCacheado(['slug' => 'x'], 'colag.localhost', null));
        $this->assertNull(TenantResolver::validarTenantCacheado(['id' => 0, 'slug' => 'x', 'ativo' => 1], 'h', null));
    }

    public function testSlugHeaderDeveBaterComCache(): void
    {
        $ok = TenantResolver::validarTenantCacheado(
            ['id' => 1, 'slug' => 'colag', 'dominio' => 'colag.localhost', 'ativo' => 1],
            'qualquer',
            'colag'
        );
        $this->assertNotNull($ok);

        $fail = TenantResolver::validarTenantCacheado(
            ['id' => 1, 'slug' => 'colag', 'dominio' => 'colag.localhost', 'ativo' => 1],
            'qualquer',
            'escola2'
        );
        $this->assertNull($fail);
    }

    public function testConfigBancoIncompletaNaoEValida(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Core/DatabaseManager.php';
        $this->assertFalse(\App\Core\DatabaseManager::configTenantValida([]));
        $this->assertFalse(\App\Core\DatabaseManager::configTenantValida([
            'host' => 'mysql',
            'nome_banco' => '',
            'usuario' => 'u',
        ]));
        $this->assertTrue(\App\Core\DatabaseManager::configTenantValida([
            'host' => 'mysql',
            'nome_banco' => 'educatudo_colag',
            'usuario' => 'u',
        ]));
    }
}
