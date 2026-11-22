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
    private ?string $allowBackup = null;

    protected function setUp(): void
    {
        CspPolitica::reiniciarParaTeste();
        $this->envBackup = getenv('CSP_STRICT_ENFORCEMENT') !== false
            ? (string) getenv('CSP_STRICT_ENFORCEMENT')
            : null;
        $this->allowBackup = getenv('CSP_STRICT_ALLOW') !== false
            ? (string) getenv('CSP_STRICT_ALLOW')
            : null;
        putenv('CSP_STRICT_ENFORCEMENT');
        $_ENV['CSP_STRICT_ENFORCEMENT'] = '';
        putenv('CSP_STRICT_ALLOW');
        $_ENV['CSP_STRICT_ALLOW'] = '';
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
        if ($this->allowBackup === null) {
            putenv('CSP_STRICT_ALLOW');
            unset($_ENV['CSP_STRICT_ALLOW']);
        } else {
            putenv('CSP_STRICT_ALLOW=' . $this->allowBackup);
            $_ENV['CSP_STRICT_ALLOW'] = $this->allowBackup;
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

    public function testMigrarOnRemoveAtributosEGeraScriptComNonce(): void
    {
        $html = '<!DOCTYPE html><html><body>'
            . '<button onclick="closeFilterDrawer()">Fechar</button>'
            . '<form onsubmit="return confirm(\'Excluir?\')"><button>Ok</button></form>'
            . '<img src="/x.png" onerror="this.style.display=\'none\'">'
            . '</body></html>';
        $out = CspPolitica::prepararHtmlCsp($html);
        $this->assertSame(0, preg_match_all('/\son[a-z]+\s*=\s*["\']/i', $out));
        // cada handler: 1x no HTML + 1x no querySelector do script nonce
        $this->assertSame(6, substr_count($out, 'data-csp-on-id='));
        $this->assertStringContainsString('addEventListener', $out);
        $this->assertStringContainsString('closeFilterDrawer()', $out);
        $this->assertMatchesRegularExpression('/<script[^>]*nonce="/', $out);
    }

    public function testPrepararHtmlCspNaoQuebraSemOn(): void
    {
        $html = '<html><body><script src="/a.js"></script><p>ok</p></body></html>';
        $out = CspPolitica::prepararHtmlCsp($html);
        $this->assertStringContainsString('nonce="', $out);
        $this->assertStringContainsString('<p>ok</p>', $out);
    }

    public function testMigrarOnNaoAlteraOnclickDentroDeScript(): void
    {
        $html = '<!DOCTYPE html><html><body>'
            . '<button onclick="fecharModalNotificacoes()">Fechar</button>'
            . '<script>'
            . "html += '<button onclick=\"removerModulo(1)\">X</button>';"
            . 'function abrirModalGerarIA(){ return true; }'
            . '</script>'
            . '</body></html>';
        $out = CspPolitica::prepararHtmlCsp($html);
        $this->assertStringContainsString('data-csp-on-id=', $out);
        $this->assertStringContainsString("html += '<button onclick=\"removerModulo(1)\">X</button>';", $out);
        $this->assertStringContainsString('function abrirModalGerarIA(){ return true; }', $out);
        $this->assertStringNotContainsString('data-csp-on-id=removerModulo', $out);
    }

    public function testMigrarOnIgnoraFechamentoDoBodyDentroDeScript(): void
    {
        $scriptOriginal = <<<'HTML'
<script>
function imprimir() {
    win.document.write('</body></html>');
    win.document.close();
}
</script>
HTML;
        $fragmento = '<button onclick="imprimir()">Imprimir</button>' . $scriptOriginal;
        $fechamento = '</body></html>';
        $pagina = '<!DOCTYPE html><html><body>' . $fragmento . $fechamento;

        $saidaFragmento = CspPolitica::migrarAtributosOnParaScriptNonce($fragmento);
        $saidaPagina = CspPolitica::migrarAtributosOnParaScriptNonce($pagina);

        $this->assertStringContainsString($scriptOriginal . '<script ' . CspPolitica::attrNonce() . '>', $saidaFragmento);
        $this->assertStringEndsWith('</script>', $saidaFragmento);
        $this->assertSame('<!DOCTYPE html><html><body>' . $saidaFragmento . $fechamento, $saidaPagina);
    }

    public function testUrlAssetEstaticoUsaStaticSemPublicEComCacheBust(): void
    {
        $url = CspPolitica::urlAssetEstatico('js/csp-event-delegation.js');
        $this->assertStringContainsString('/static/js/csp-event-delegation.js?v=', $url);
        $this->assertStringNotContainsString('/public/static/', $url);
    }

    public function testTagDelegacaoEventosTemNonceDeferEDataCspClickScript(): void
    {
        $tag = CspPolitica::tagDelegacaoEventos();
        $this->assertStringContainsString('csp-event-delegation.js?v=', $tag);
        $this->assertStringContainsString('nonce="', $tag);
        $this->assertStringContainsString(' defer', $tag);
        $this->assertStringNotContainsString('/public/static/', $tag);
    }

    public function testTagCsrfFetchSemDeferComCacheBust(): void
    {
        $tag = CspPolitica::tagCsrfFetch();
        $this->assertStringContainsString('csrf-fetch.js?v=', $tag);
        $this->assertStringContainsString('nonce="', $tag);
        $this->assertStringNotContainsString(' defer', $tag);
        $this->assertStringNotContainsString('/public/static/', $tag);
    }

    public function testCsrfFetchJsCobreFetchEXHR(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/static/js/csrf-fetch.js');
        $this->assertStringContainsString('X-CSRF-TOKEN', $js);
        $this->assertStringContainsString('window.fetch', $js);
        $this->assertStringContainsString('XMLHttpRequest', $js);
        $this->assertStringContainsString('FormData', $js);
    }

    public function testStrictNoHeaderExigeDoisFlags(): void
    {
        putenv('CSP_STRICT_ENFORCEMENT=true');
        $_ENV['CSP_STRICT_ENFORCEMENT'] = 'true';
        putenv('CSP_STRICT_ALLOW');
        $_ENV['CSP_STRICT_ALLOW'] = '';
        $this->assertTrue(CspPolitica::modoRestritoAtivo());
        $this->assertFalse(CspPolitica::allowStrictNoHeader());

        putenv('CSP_STRICT_ALLOW=true');
        $_ENV['CSP_STRICT_ALLOW'] = 'true';
        $this->assertTrue(CspPolitica::allowStrictNoHeader());
    }
}
