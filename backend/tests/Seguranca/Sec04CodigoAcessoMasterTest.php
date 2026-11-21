<?php

require_once dirname(__DIR__, 2) . '/app/Painel/Master/Services/CodigoAcessoMasterService.php';

use App\Painel\Master\Services\CodigoAcessoMasterService;
use PHPUnit\Framework\TestCase;

/**
 * OTP Master — limites estáticos e mascaramento (SEC-04).
 * Confirmação com banco real fica no harness HTTP/DB.
 */
final class Sec04CodigoAcessoMasterTest extends TestCase
{
    public function testLimitesDoSegundoFator(): void
    {
        $this->assertSame(10, CodigoAcessoMasterService::VALIDADE_MINUTOS);
        $this->assertSame(5, CodigoAcessoMasterService::MAX_TENTATIVAS);
        $this->assertSame(60, CodigoAcessoMasterService::REENVIO_SEGUNDOS);
    }

    public function testMascaraEmail(): void
    {
        $ref = new ReflectionClass(CodigoAcessoMasterService::class);
        /** @var CodigoAcessoMasterService $svc */
        $svc = $ref->newInstanceWithoutConstructor();
        $this->assertSame('a***@escola.local', $svc->mascararEmail('admin@escola.local'));
        $this->assertSame('seu e-mail', $svc->mascararEmail('invalido'));
    }
}
