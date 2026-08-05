<?php

/**
 * EducaTudo - RichTextHelper
 *
 * Sanitiza e renderiza HTML produzido pelo editor WYSIWYG (components/wysiwyg.php).
 * Permite apenas um subconjunto seguro de tags e remove atributos perigosos
 * (eventos on*, javascript: em href/src). Conteúdo é autoria de admin/professor,
 * mas o saneamento protege contra payloads colados.
 */
if (!function_exists('rich_text_render')) {

    /** Tags de formatação permitidas (usado pelo fallback e pela allowlist do HTMLPurifier). */
    function rich_text_allowed_tags(): string
    {
        return '<p><br><b><strong><i><em><u><s><ul><ol><li><a><h2><h3><h4><blockquote><span><div>';
    }

    /**
     * Instância única de HTMLPurifier (ou null se a lib não estiver disponível).
     * Configurada com uma allowlist estreita de tags/atributos de formatação.
     */
    function rich_text_purifier(): ?\HTMLPurifier
    {
        static $purifier = null;
        static $resolved = false;
        if ($resolved) {
            return $purifier;
        }
        $resolved = true;

        if (!class_exists('HTMLPurifier')) {
            return null; // sem a lib: cai no fallback por regex
        }

        $config = \HTMLPurifier_Config::createDefault();
        // Allowlist de tags. class/style são liberados só onde o editor (Quill) usa,
        // e o conteúdo de ambos é filtrado abaixo (CSS.AllowedProperties + Attr.AllowedClasses).
        $config->set(
            'HTML.Allowed',
            'p[style|class],br,b,strong,i,em,u,s,'
            . 'ul,ol,li[class],a[href|title|target|rel],'
            . 'h2[style|class],h3[style|class],h4[style|class],'
            . 'blockquote,span[style|class],div[style|class]'
        );
        // Só estas propriedades CSS inline são aceitas (cor, alinhamento, ênfase, tamanho).
        // Qualquer valor perigoso (expression(), url(javascript:)...) é removido pelo parser de CSS.
        $config->set('CSS.AllowedProperties', [
            'color', 'background-color', 'text-align',
            'font-weight', 'font-style', 'text-decoration',
            'font-size',
        ]);
        // Só estas classes são aceitas (alinhamento, tamanho e recuo do Quill). O resto é removido,
        // impedindo abuso de classes do CSS global.
        $config->set('Attr.AllowedClasses', [
            'ql-align-left', 'ql-align-center', 'ql-align-right', 'ql-align-justify',
            'ql-size-small', 'ql-size-large', 'ql-size-huge',
            'ql-indent-1', 'ql-indent-2', 'ql-indent-3', 'ql-indent-4',
            'ql-indent-5', 'ql-indent-6', 'ql-indent-7', 'ql-indent-8',
        ]);
        // Apenas esquemas seguros em href.
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        // Links externos abrem em nova aba com rel seguro.
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Output.FlashCompat', false);

        // Cache de definições em storage/ (fallback: desabilita cache em disco se não for gravável).
        $cacheDir = __DIR__ . '/../../storage/cache/htmlpurifier';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $config->set('Cache.SerializerPath', $cacheDir);
        } else {
            $config->set('Cache.DefinitionImpl', null);
        }

        $purifier = new \HTMLPurifier($config);
        return $purifier;
    }

    /**
     * Sanitiza o HTML para exibição. Usa HTMLPurifier quando disponível
     * (remove scripts, atributos on*, esquemas perigosos e atributos não permitidos).
     * Se a lib não existir, cai num fallback por regex (menos robusto, mas melhor que nada).
     */
    function rich_text_render(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $purifier = rich_text_purifier();
        if ($purifier !== null) {
            return $purifier->purify($html);
        }

        // Fallback (HTMLPurifier indisponível): strip_tags + remoção de on*/javascript:.
        $clean = strip_tags($html, rich_text_allowed_tags());
        $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);
        $clean = preg_replace('/(href|src)\s*=\s*("|\')\s*(javascript|data|vbscript)\s*:[^"\']*\2/i', '$1=$2#$2', $clean);

        return (string) $clean;
    }

    /** Estilos do conteúdo renderizado (emitido apenas uma vez por página). */
    function rich_text_styles(): void
    {
        if (!empty($GLOBALS['__rich_text_styles_done'])) {
            return;
        }
        $GLOBALS['__rich_text_styles_done'] = true;
        echo '<style>'
            . '.rich-content{color:#374151;line-height:1.6;}'
            . '.rich-content p{margin:0 0 .5rem;}'
            . '.rich-content ul{list-style:disc;padding-left:1.5rem;margin:0 0 .5rem;}'
            . '.rich-content ol{list-style:decimal;padding-left:1.5rem;margin:0 0 .5rem;}'
            . '.rich-content h2,.rich-content h3,.rich-content h4{font-weight:700;margin:.25rem 0 .5rem;}'
            . '.rich-content h2{font-size:1.15rem;}'
            . '.rich-content h3{font-size:1.05rem;}'
            . '.rich-content a{color:#15803d;text-decoration:underline;}'
            . '.rich-content blockquote{border-left:3px solid #d1d5db;padding-left:.75rem;color:#6b7280;margin:0 0 .5rem;}'
            . '.rich-content:empty{display:none;}'
            . '</style>';
    }

    /**
     * Renderiza (echo) o conteúdo rico sanitizado dentro de um wrapper estilizado.
     * Usar nos pontos de exibição que antes faziam htmlspecialchars de textareas.
     */
    function rich_text(?string $html, string $extraClass = ''): void
    {
        rich_text_styles();
        $cls = trim('rich-content ' . $extraClass);
        echo '<div class="' . htmlspecialchars($cls) . '">' . rich_text_render($html) . '</div>';
    }

    /**
     * Texto puro (sem HTML) a partir do conteúdo rico — útil para resumos/listas.
     */
    function rich_text_plain(?string $html, int $limit = 0): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        if ($limit > 0 && function_exists('mb_strlen') && mb_strlen($text) > $limit) {
            return mb_substr($text, 0, $limit - 1) . '…';
        }
        return $text;
    }

    /**
     * Renderiza mensagem de ticket de suporte (aluno usa Quill → HTML).
     * - Desfaz double-escape (&lt;p&gt; → <p>) quando o conteúdo veio escapado no banco
     * - Remove artefato do Quill (span.ql-cursor)
     * - Com <img>: sanitiza permitindo imagem; sem img: rich_text_render (formatação Quill)
     * - Texto puro antigo: nl2br + escape
     */
    function ticket_message_html(?string $mensagem): string
    {
        $raw = trim((string) $mensagem);
        if ($raw === '') {
            return '';
        }

        // Conteúdo gravado com entidades (&lt;p&gt;...) — inclusive misturado com tags reais.
        // Sem isso, o HTMLPurifier deixa &lt;p&gt; como texto e a tela mostra as tags cruas.
        if (preg_match('/&(lt|gt|quot|#0*60|#0*62|#x0*3c|#x0*3e);/i', $raw)) {
            $flags = defined('ENT_HTML5') ? (ENT_QUOTES | ENT_HTML5) : ENT_QUOTES;
            for ($i = 0; $i < 3; $i++) {
                $decoded = html_entity_decode($raw, $flags, 'UTF-8');
                if ($decoded === $raw) {
                    break;
                }
                $raw = $decoded;
            }
        }

        // Cursor fantasma do Quill colado no meio do texto
        $raw = preg_replace('/<span\b[^>]*class=["\'][^"\']*\bql-cursor\b[^"\']*["\'][^>]*>\s*<\/span>/i', '', $raw) ?? $raw;
        $raw = preg_replace('/<span\b[^>]*class=["\'][^"\']*\bql-cursor\b[^"\']*["\'][^>]*\/>/i', '', $raw) ?? $raw;

        if (strpos($raw, '<') === false) {
            return nl2br(htmlspecialchars($raw, ENT_QUOTES, 'UTF-8'));
        }

        // Imagens anexadas no ticket precisam do sanitizer com <img>
        if (stripos($raw, '<img') !== false) {
            if (!class_exists(\App\Utils\HtmlSanitizer::class, false)) {
                $sanitizerPath = __DIR__ . '/../Utils/HtmlSanitizer.php';
                if (is_file($sanitizerPath)) {
                    require_once $sanitizerPath;
                }
            }
            if (class_exists(\App\Utils\HtmlSanitizer::class)) {
                return \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($raw);
            }
        }

        return rich_text_render($raw);
    }
}
