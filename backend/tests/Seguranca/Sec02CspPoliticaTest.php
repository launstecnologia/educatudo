<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\CspPolitica;
use PHPUnit\Framework\TestCase;

/**
 * SEC-02 — políticas CSP (baseline, candidata restritiva, flag CSP_STRICT_ENFORCEMENT).
 */
final class Sec02CspPoliticaTest extends TestCase
{
    private ?string $envBackup = null;

    protected function setUp(): void
    {
        CspPolitica::reiniciarParaTeste();
        $this->envBackup = getenv('CSP_STRICT_ENFORCEMENT') !== false
            ? (string) getenv('CSP_STRICT_ENFORCEMENT')
            : null;
        putenv('CSP_STRICT_ENFORCEMENT');
        $_ENV['CSP_STRICT_ENFORCEMENT'] = '';
    }

    protected function tearDown(): void
    {
        if ($this->envBackup === null) {
            putenv('CSP_STRICT_ENFORCEMENT');
            unset($_ENV['CSP_STRICT_ENFORCEMENT']);
        } else {
            putenv('CSP_STRICT_ENFORCEMENT=' . $this->envBackup);
            $_ENV['CSP_STRICT_ENFORCEMENT'] = $this->envBackup;
        }
        CspPolitica::reiniciarParaTeste();
    }

    public function testNonceDistintoPorReinicio(): void
    {
        $a = CspPolitica::nonce();
        CspPolitica::reiniciarParaTeste();
        $b = CspPolitica::nonce();
        $this->assertNotSame('', $a);
        $this->assertNotSame($a, $b);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $a);
    }

    public function testBaselineMantemUnsafeSemNonce(): void
    {
        $this->assertFalse(CspPolitica::modoRestritoAtivo());
        $p = CspPolitica::politicaEnforcementBaseline();
        $this->assertStringContainsString("'unsafe-inline'", $p);
        $this->assertStringContainsString("'unsafe-eval'", $p);
        $this->assertStringContainsString('https://cdn.quilljs.com', $p);
        $this->assertStringNotContainsString("'nonce-", $p);
    }

    public function testEnforcementPadraoUsaBaseline(): void
    {
        $p = CspPolitica::politicaEnforcement();
        $this->assertStringContainsString("'unsafe-inline'", $p);
        $this->assertStringContainsString("'unsafe-eval'", $p);
    }

    public function testCandidataRestritaSemUnsafeEmScript(): void
    {
        $p = CspPolitica::politicaCandidataReportOnly();
        $this->assertStringNotContainsString("'unsafe-eval'", $p);
        $this->assertMatchesRegularExpression("/script-src[^;]*'nonce-/", $p);
        if (preg_match('/script-src[^;]+/', $p, $m)) {
            $this->assertStringNotContainsString("'unsafe-inline'", $m[0]);
            $this->assertStringContainsString("'strict-dynamic'", $m[0]);
        } else {
            $this->fail('script-src ausente');
        }
        $this->assertStringContainsString('report-uri /api/csp-report', $p);
    }

    public function testModoRestritoAtivaPoliticaSemUnsafe(): void
    {
        putenv('CSP_STRICT_ENFORCEMENT=true');
        $_ENV['CSP_STRICT_ENFORCEMENT'] = 'true';
        $this->assertTrue(CspPolitica::modoRestritoAtivo());
        $p = CspPolitica::politicaEnforcement();
        $this->assertStringNotContainsString("'unsafe-eval'", $p);
        if (preg_match('/script-src[^;]+/', $p, $m)) {
            $this->assertStringNotContainsString("'unsafe-inline'", $m[0]);
            $this->assertStringContainsString("'nonce-", $m[0]);
        } else {
            $this->fail('script-src ausente no modo restrito');
        }
    }

    public function testAplicarNonceEmHtmlIncluiScriptsComSrc(): void
    {
        $html = '<script>alert(1)</script><script src="https://cdn.jsdelivr.net/npm/x.js"></script>';
        $out = CspPolitica::aplicarNonceEmHtml($html);
        $this->assertSame(2, substr_count($out, 'nonce="'));
    }

    public function testAttrNonceFormato(): void
    {
        $this->assertMatchesRegularExpression('/^nonce="[A-Za-z0-9+\/=]+"$/', CspPolitica::attrNonce());
    }

    public function testPoliticaRestritaTemFormActionEWorker(): void
    {
        $p = CspPolitica::politicaRestrita(false);
        $this->assertStringContainsString("form-action 'self'", $p);
        $this->assertStringContainsString('worker-src', $p);
        $this->assertStringContainsString('wss://ws.educatudo.com', $p);
        $this->assertStringNotContainsString('report-uri', $p);
    }
}
