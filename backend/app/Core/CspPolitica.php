<?php

namespace App\Core;

/**
 * SEC-02 — Geração centralizada de Content-Security-Policy.
 *
 * Enforcement (baseline): allowlist completa + unsafe-inline/unsafe-eval
 * (compatível com MathJax, scripts inline e handlers on* ainda não migrados).
 *
 * Report-Only (candidata): sem unsafe-eval; com nonce e sem unsafe-inline em
 * script-src para coletar violações. Não promover sem autorização explícita.
 */
final class CspPolitica
{
    private static ?string $nonce = null;

    /** Hosts de script usados pela plataforma (CDNs + Jitsi + push + acessibilidade). */
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

    /** Origens explícitas para a candidata (Onda 4) — sem wildcards https:/wss:. */
    private const CONNECT_HOSTS_CANDIDATA = [
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

    private const FRAME_HOSTS_CANDIDATA = [
        "'self'",
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

    /** Atributo HTML pronto: nonce="..." */
    public static function attrNonce(): string
    {
        return 'nonce="' . htmlspecialchars(self::nonce(), ENT_QUOTES, 'UTF-8') . '"';
    }

    /**
     * Insere nonce em tags <script> sem nonce (útil para $additional_js).
     */
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

    public static function politicaEnforcement(): string
    {
        $scriptHosts = implode(' ', self::SCRIPT_HOSTS);
        $styleHosts = implode(' ', self::STYLE_HOSTS);
        $fontHosts = implode(' ', self::FONT_HOSTS);
        // NÃO incluir nonce no enforcement enquanto houver scripts/on* sem nonce:
        // em CSP2+, a presença de nonce/hash invalida 'unsafe-inline'.
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "worker-src 'self' blob: https://cdnjs.cloudflare.com",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$scriptHosts}",
            "style-src 'self' 'unsafe-inline' {$styleHosts}",
            "font-src 'self' data: {$fontHosts}",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https: wss:",
            "frame-src 'self' https:",
            "media-src 'self' blob: https:",
        ]);
    }

    /**
     * Candidata Report-Only: sem unsafe-eval; script-src com nonce (sem unsafe-inline).
     * Violações alimentam o endurecimento; não bloqueiam a UI.
     */
    public static function politicaCandidataReportOnly(): string
    {
        $scriptHosts = implode(' ', self::SCRIPT_HOSTS);
        $styleHosts = implode(' ', self::STYLE_HOSTS);
        $fontHosts = implode(' ', self::FONT_HOSTS);
        $connect = implode(' ', self::CONNECT_HOSTS_CANDIDATA);
        $frames = implode(' ', self::FRAME_HOSTS_CANDIDATA);
        $nonce = self::nonce();

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "worker-src 'self' blob: https://cdnjs.cloudflare.com",
            "script-src 'self' 'nonce-{$nonce}' {$scriptHosts}",
            "style-src 'self' 'unsafe-inline' {$styleHosts}",
            "font-src 'self' data: {$fontHosts}",
            "img-src 'self' data: blob: https:",
            "connect-src {$connect}",
            "frame-src {$frames}",
            "media-src 'self' blob: https:",
            'report-uri /api/csp-report',
        ]);
    }

    public static function emitirCabecalhos(): void
    {
        if (headers_sent()) {
            return;
        }
        header('Permissions-Policy: geolocation=(), payment=(), usb=()');
        header('Content-Security-Policy: ' . self::politicaEnforcement());
        header('Content-Security-Policy-Report-Only: ' . self::politicaCandidataReportOnly());
    }

    /** Exposto para testes — não usar em produção para forçar nonce. */
    public static function reiniciarParaTeste(): void
    {
        self::$nonce = null;
    }
}
