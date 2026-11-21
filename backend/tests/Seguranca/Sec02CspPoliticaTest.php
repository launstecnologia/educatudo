<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\CspPolitica;
use PHPUnit\Framework\TestCase;

/**
 * SEC-02 — políticas CSP centralizadas (enforcement + candidata Report-Only).
 */
final class Sec02CspPoliticaTest extends TestCase
{
    protected function setUp(): void
    {
        CspPolitica::reiniciarParaTeste();
    }

    public function testNonceDistintoPorReinicio(): void
    {
        $a = CspPolitica::nonce();
        CspPolitica::reiniciarParaTeste();
        $b = CspPolitica::nonce();
        $this->assertNotSame('', $a);
        $this->assertNotSame('', $b);
        $this->assertNotSame($a, $b);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $a);
    }

    public function testEnforcementMantemUnsafeParaCompatibilidade(): void
    {
        $p = CspPolitica::politicaEnforcement();
        $this->assertStringContainsString("script-src", $p);
        $this->assertStringContainsString("'unsafe-inline'", $p);
        $this->assertStringContainsString("'unsafe-eval'", $p);
        $this->assertStringContainsString('https://cdn.quilljs.com', $p);
        $this->assertStringContainsString('https://cdn.ckeditor.com', $p);
        $this->assertStringContainsString('https://cdn.onesignal.com', $p);
        $this->assertStringContainsString('https://vlibras.gov.br', $p);
        $this->assertStringContainsString('https://esm.sh', $p);
        $this->assertStringContainsString("form-action 'self'", $p);
        $this->assertStringContainsString('worker-src', $p);
        // Nonce NÃO deve estar no enforcement (quebraria unsafe-inline em CSP2+)
        $this->assertStringNotContainsString("'nonce-", $p);
    }

    public function testCandidataSemUnsafeEvalNemUnsafeInlineEmScript(): void
    {
        $p = CspPolitica::politicaCandidataReportOnly();
        $this->assertStringNotContainsString("'unsafe-eval'", $p);
        // script-src da candidata não deve ter unsafe-inline
        if (preg_match("/script-src[^;]+/", $p, $m)) {
            $this->assertStringNotContainsString("'unsafe-inline'", $m[0]);
            $this->assertStringContainsString("'nonce-", $m[0]);
        } else {
            $this->fail('script-src ausente na candidata');
        }
        $this->assertStringContainsString('report-uri /api/csp-report', $p);
        $this->assertStringContainsString('wss://ws.educatudo.com', $p);
    }

    public function testAplicarNonceEmHtml(): void
    {
        $html = '<script>alert(1)</script><script src="https://cdn.example/x.js"></script>';
        $out = CspPolitica::aplicarNonceEmHtml($html);
        $this->assertStringContainsString('nonce="', $out);
        $this->assertSame(2, substr_count($out, 'nonce="'));
    }

    public function testAttrNonceFormato(): void
    {
        $attr = CspPolitica::attrNonce();
        $this->assertMatchesRegularExpression('/^nonce="[A-Za-z0-9+\/=]+"$/', $attr);
    }
}
