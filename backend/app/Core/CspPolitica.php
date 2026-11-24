<?php

namespace App\Core;

/**
 * SEC-02 — Geração centralizada de Content-Security-Policy.
 *
 * Modo padrão (produção): enforcement baseline com unsafe-inline/unsafe-eval
 * + Report-Only candidata restritiva (nonce, sem unsafe-* em script-src).
 *
 * Modo restrito (CSP_STRICT_ENFORCEMENT=true|1): enforcement = política
 * candidata (sem unsafe-inline/unsafe-eval; com nonce). Não ativar em
 * produção sem validação manual — flag existe para homologação.
 */
final class CspPolitica
{
    private static ?string $nonce = null;

    private const SCRIPT_HOSTS = [
        'https://cdnjs.cloudflare.com',
        'https://unpkg.com',
        'https://cdn.jsdelivr.net',
        'https://8x8.vc',
        'https://meet.launs.com.br',
        'https://cdn.quilljs.com',
        'https://cdn.ckeditor.com',
        'https://cdn.tiny.cloud',
        'https://cdn.onesignal.com',
        'https://vlibras.gov.br',
        'https://esm.sh',
        'https://cdn.tailwindcss.com',
        'https://polyfill.io',
    ];

    private const STYLE_HOSTS = [
        'https://cdnjs.cloudflare.com',
        'https://cdn.jsdelivr.net',
        'https://fonts.googleapis.com',
        'https://cdn.quilljs.com',
        'https://esm.sh',
    ];

    private const FONT_HOSTS = [
        'https://cdnjs.cloudflare.com',
        'https://cdn.jsdelivr.net',
        'https://fonts.gstatic.com',
    ];

    private const CONNECT_HOSTS_RESTRITA = [
        "'self'",
        'https:',
        'wss://ws.educatudo.com',
        'wss://8x8.vc',
        'wss://*.8x8.vc',
        'wss://meet.launs.com.br',
        'https://cdn.onesignal.com',
        'https://onesignal.com',
        'https://vlibras.gov.br',
    ];

    private const FRAME_HOSTS_RESTRITA = [
        "'self'",
        'blob:',
        'https://8x8.vc',
        'https://meet.launs.com.br',
        'https://www.youtube.com',
        'https://youtube.com',
        'https://www.youtube-nocookie.com',
        'https://player.vimeo.com',
        'https://docs.google.com',
        'https://vlibras.gov.br',
        'https:',
    ];

    public static function nonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = base64_encode(random_bytes(16));
        }
        return self::$nonce;
    }

    public static function attrNonce(): string
    {
        return 'nonce="' . htmlspecialchars(self::nonce(), ENT_QUOTES, 'UTF-8') . '"';
    }

    /**
     * Lê flag booleana de env (env() ou getenv).
     */
    private static function envFlag(string $key): bool
    {
        $raw = null;
        if (function_exists('env')) {
            $raw = env($key, null);
        }
        if ($raw === null || $raw === '') {
            $fromEnv = getenv($key);
            $raw = ($fromEnv !== false) ? $fromEnv : '';
        }
        $v = strtolower(trim((string) $raw));
        return $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
    }

    /**
     * CSP_STRICT_ENFORCEMENT=true|1 marca desejo de enforcement restritivo.
     * Padrão: false (produção segura até validação manual).
     */
    public static function modoRestritoAtivo(): bool
    {
        return self::envFlag('CSP_STRICT_ENFORCEMENT');
    }

    /**
     * Segundo cadeado: enforcement restritivo no header só com CSP_STRICT_ALLOW=true.
     * Evita que CSP_STRICT_ENFORCEMENT sozinho quebre JS em produção (data-csp-click etc.).
     */
    public static function allowStrictNoHeader(): bool
    {
        return self::envFlag('CSP_STRICT_ALLOW');
    }

    /**
     * URL canônica de asset em public/static/ (sem /public/ no path — docroot já é public/).
     * Inclui ?v=filemtime para furar cache CDN (Cloudflare) após deploy.
     */
    public static function urlAssetEstatico(string $pathRelativo): string
    {
        $path = ltrim(str_replace('\\', '/', $pathRelativo), '/');
        if (str_starts_with($path, 'public/static/')) {
            $path = substr($path, strlen('public/static/'));
        } elseif (str_starts_with($path, 'static/')) {
            $path = substr($path, strlen('static/'));
        }
        $fs = dirname(__DIR__, 2) . '/public/static/' . $path;
        $v = is_file($fs) ? (string) filemtime($fs) : (string) time();
        $base = defined('URL') ? rtrim((string) URL, '/') : '';
        return $base . '/static/' . $path . '?v=' . rawurlencode($v);
    }

    /**
     * Tag <script src> com nonce + path canônico /static/... + cache-bust.
     * Ponto único para csp-event-delegation e demais helpers CSP.
     *
     * @param array<string, string> $extraAttrs
     */
    public static function tagScriptEstatico(string $pathRelativo, bool $defer = true, array $extraAttrs = []): string
    {
        $src = htmlspecialchars(self::urlAssetEstatico($pathRelativo), ENT_QUOTES, 'UTF-8');
        $parts = ['src="' . $src . '"', self::attrNonce()];
        if ($defer) {
            $parts[] = 'defer';
        }
        foreach ($extraAttrs as $name => $value) {
            $n = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $name);
            if ($n === '') {
                continue;
            }
            $parts[] = $n . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
        }
        return '<script ' . implode(' ', $parts) . '></script>';
    }

    /** Script de delegação data-csp-click / data-csp-action (obrigatório em todo layout). */
    public static function tagDelegacaoEventos(): string
    {
        return self::tagScriptEstatico('js/csp-event-delegation.js', true);
    }

    /**
     * Patch global de fetch para enviar X-CSRF-TOKEN (JSON) e _token (FormData).
     * Carregar no <head> sem defer, depois do meta csrf-token.
     */
    public static function tagCsrfFetch(): string
    {
        return self::tagScriptEstatico('js/csrf-fetch.js', false);
    }

    public static function aplicarNonceEmHtml(string $html): string
    {
        $attr = self::attrNonce();
        return (string) preg_replace_callback(
            '/<script(\s[^>]*)?>/i',
            static function (array $m) use ($attr): string {
                $rest = $m[1] ?? '';
                if (stripos($rest, 'nonce=') !== false) {
                    return $m[0];
                }
                return '<script' . $rest . ' ' . $attr . '>';
            },
            $html
        );
    }

    /**
     * SEC-02 — remove atributos on* do HTML e recria como addEventListener
     * dentro de um <script nonce> (compatível com CSP sem unsafe-inline).
     *
     * O corpo do handler já vem renderizado (<?= ... ?> expandido). Mantém `this`
     * e trata `return false` como preventDefault.
     */
    public static function migrarAtributosOnParaScriptNonce(string $html): string
    {
        $handlers = [];
        $seq = 0;

        // NÃO reescrever on* dentro de <script>/<style>/<textarea>/<noscript>.
        // Caso contrário, strings JS tipo html += '<button onclick=...>' quebram o parse
        // e funções globais (abrirModal*, fecharModal*) deixam de existir — botões mortos.
        $slots = [];
        $protegido = preg_replace_callback(
            '/<(script|style|textarea|noscript)\b[^>]*>.*?<\/\1>/is',
            static function (array $m) use (&$slots): string {
                $key = '<!--CSP_PROTECT_' . count($slots) . '-->';
                $slots[$key] = $m[0];
                return $key;
            },
            $html
        );
        if (!is_string($protegido)) {
            $protegido = $html;
        }

        $protegido = (string) preg_replace_callback(
            '/\s(on[a-z]+)\s*=\s*(["\'])(.*?)\2/is',
            static function (array $m) use (&$handlers, &$seq): string {
                $attr = strtolower($m[1]);
                $event = substr($attr, 2);
                if ($event === '' || !preg_match('/^[a-z]+$/', $event)) {
                    return $m[0];
                }
                $body = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $body = trim($body);
                if ($body === '') {
                    return '';
                }
                $seq++;
                $id = 'csp_on_' . $seq . '_' . substr(hash('sha256', $event . '|' . $body . '|' . $seq), 0, 10);
                $handlers[] = [
                    'id' => $id,
                    'event' => $event,
                    'body' => $body,
                ];
                return ' data-csp-on-id="' . $id . '"';
            },
            $protegido
        );

        if ($handlers === []) {
            return strtr($protegido, $slots);
        }

        $jsParts = ["(function(){"];
        foreach ($handlers as $h) {
            $id = $h['id'];
            $event = $h['event'];
            // Evita quebrar o HTML se o corpo tiver literal </script>
            $body = preg_replace('/<\/script/i', '<\\/script', $h['body']);
            $jsParts[] = "var __e=document.querySelector('[data-csp-on-id=\"{$id}\"]');";
            $jsParts[] = "if(__e){__e.addEventListener('{$event}',function(event){";
            $jsParts[] = "try{var __r=(function(event){{$body}}).call(this,event);";
            $jsParts[] = "if(__r===false){if(event&&event.preventDefault)event.preventDefault();if(event&&event.stopPropagation)event.stopPropagation();}";
            $jsParts[] = "}catch(__err){console.error('CSP on-handler',__err);}";
            $jsParts[] = "});}";
        }
        $jsParts[] = "})();";
        $script = '<script ' . self::attrNonce() . '>' . implode('', $jsParts) . '</script>';

        // Procura o fechamento do body antes de restaurar os blocos protegidos,
        // que podem conter HTML em strings JavaScript (por exemplo, para impressão).
        $fimBody = stripos($protegido, '</body>');
        $protegido = $fimBody === false
            ? $protegido . $script
            : substr_replace($protegido, $script, $fimBody, 0);

        return strtr($protegido, $slots);
    }

    /** Pipeline completo para HTML de views (nonce em <script> + migração on*). */
    public static function prepararHtmlCsp(string $html): string
    {
        $html = self::migrarAtributosOnParaScriptNonce($html);
        return self::aplicarNonceEmHtml($html);
    }

    public static function politicaEnforcementBaseline(): string
    {
        $scriptHosts = implode(' ', self::SCRIPT_HOSTS);
        $styleHosts = implode(' ', self::STYLE_HOSTS);
        $fontHosts = implode(' ', self::FONT_HOSTS);

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            // blob: no visualizador nativo de PDF (prévia do boletim e do editor).
            // Sem 'self': <object>/<embed> da origem continuam bloqueados.
            "object-src blob:",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "worker-src 'self' blob: https://cdnjs.cloudflare.com",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$scriptHosts}",
            "style-src 'self' 'unsafe-inline' {$styleHosts}",
            "font-src 'self' data: {$fontHosts}",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https: wss:",
            "frame-src 'self' blob: https:",
            "media-src 'self' blob: https:",
        ]);
    }

    /**
     * Política restritiva: sem unsafe-inline/unsafe-eval em script-src; com nonce.
     * Usada como Report-Only (padrão) e como Enforcement quando CSP_STRICT_ENFORCEMENT=true.
     */
    public static function politicaRestrita(bool $comReportUri = true): string
    {
        $scriptHosts = implode(' ', self::SCRIPT_HOSTS);
        $styleHosts = implode(' ', self::STYLE_HOSTS);
        $fontHosts = implode(' ', self::FONT_HOSTS);
        $connect = implode(' ', self::CONNECT_HOSTS_RESTRITA);
        $frames = implode(' ', self::FRAME_HOSTS_RESTRITA);
        $nonce = self::nonce();

        $parts = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src blob:",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "worker-src 'self' blob: https://cdnjs.cloudflare.com",
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' {$scriptHosts}",
            "style-src 'self' 'unsafe-inline' {$styleHosts}",
            "font-src 'self' data: {$fontHosts}",
            "img-src 'self' data: blob: https:",
            "connect-src {$connect}",
            "frame-src {$frames}",
            "media-src 'self' blob: https:",
        ];
        if ($comReportUri) {
            $parts[] = 'report-uri /api/csp-report';
        }
        return implode('; ', $parts);
    }

    /** Alias legado dos testes / docs. */
    public static function politicaEnforcement(): string
    {
        return self::modoRestritoAtivo()
            ? self::politicaRestrita(false)
            : self::politicaEnforcementBaseline();
    }

    public static function politicaCandidataReportOnly(): string
    {
        return self::politicaRestrita(true);
    }

    public static function emitirCabecalhos(): void
    {
        if (headers_sent()) {
            return;
        }
        header('Permissions-Policy: geolocation=(), payment=(), usb=()');

        // Enforcement restritivo exige DOIS flags (STRICT + ALLOW).
        // Só CSP_STRICT_ENFORCEMENT=true em produção derrubava data-csp-click
        // (script legado em cache + strict-dynamic sem unsafe-inline).
        if (self::modoRestritoAtivo() && self::allowStrictNoHeader()) {
            header('Content-Security-Policy: ' . self::politicaRestrita(true));
            return;
        }

        header('Content-Security-Policy: ' . self::politicaEnforcementBaseline());
        header('Content-Security-Policy-Report-Only: ' . self::politicaCandidataReportOnly());
    }

    public static function reiniciarParaTeste(): void
    {
        self::$nonce = null;
    }
}
