<?php

namespace App\Utils;

/**
 * Sanitizes HTML from rich text editors (CKEditor + MathLive/LaTeX).
 * Whitelist: p, strong, em, ul, ol, li, span, br. Allows $$ ... $$ math content.
 * Disallows: script, iframe, on* attributes. Prevents XSS.
 */
class HtmlSanitizer
{
    private static ?\HTMLPurifier $purifier = null;

    /** Purifier que permite img (enunciados de exercícios com imagens na jornada). */
    private static ?\HTMLPurifier $purifierWithImg = null;

    private static function getPurifier(): ?\HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }
        if (!class_exists(\HTMLPurifier::class, false)) {
            return null;
        }

        $config = \HTMLPurifier_Config::createDefault();
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/htmlpurifier';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $config->set('Cache.SerializerPath', $cacheDir);
        }
        $config->set('HTML.Allowed', 'p,strong,em,ul,ol,li,span,br');
        $config->set('URI.AllowedSchemes', []);
        $config->set('HTML.Nofollow', true);
        $config->set('HTML.TargetBlank', false);
        $config->set('Attr.AllowedFrameTargets', []);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('HTML.DefinitionID', 'educatudo-exercise-html-v2');
        $config->set('HTML.DefinitionRev', 2);

        if ($def = $config->getHTMLDefinition(true)) {
            $def->addAttribute('span', 'class', 'Text');
        }

        self::$purifier = new \HTMLPurifier($config);
        return self::$purifier;
    }

    /**
     * Purifier para enunciados que podem conter imagens (ex.: jornadas).
     * Permite img com src (https/http e caminhos relativos) e alt.
     */
    private static function getPurifierWithImages(): ?\HTMLPurifier
    {
        if (self::$purifierWithImg !== null) {
            return self::$purifierWithImg;
        }
        if (!class_exists(\HTMLPurifier::class, false)) {
            return null;
        }
        $config = \HTMLPurifier_Config::createDefault();
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/htmlpurifier';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $config->set('Cache.SerializerPath', $cacheDir);
        }
        $config->set('HTML.Allowed', 'p,strong,b,em,i,u,s,sub,sup,ul,ol,li,span[class|style|data-type|data-latex],br,h1,h2,h3,h4,h5,h6,blockquote,div[class|style|data-type|data-latex],mark,table,thead,tbody,tfoot,tr,th,td,colgroup,col,img[src|alt|class|style|width|height],video[src|controls|title|width|height|preload]');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'data' => true]);
        // data:image;base64 pode ser grande; sem isso o Purifier remove o src no aluno.
        // Obs.: URI.SafeDataURI não existe no HTMLPurifier 4.19 (gera Warning).
        $config->set('URI.MaxLength', 5 * 1024 * 1024);
        $config->set('CSS.AllowedProperties', [
            'width', 'height', 'max-width', 'min-width',
            'border', 'border-collapse', 'border-spacing',
            'padding', 'margin',
            'text-align', 'vertical-align',
            'background-color', 'color',
            'font-size', 'font-weight', 'font-family', 'font-style',
            'text-decoration', 'line-height', 'letter-spacing'
        ]);
        $config->set('HTML.Nofollow', true);
        $config->set('HTML.TargetBlank', false);
        $config->set('AutoFormat.RemoveEmpty', false);
        $config->set('HTML.DefinitionID', 'educatudo-exercise-html-with-img-v5');
        $config->set('HTML.DefinitionRev', 5);
        if ($def = $config->getHTMLDefinition(true)) {
            $def->addAttribute('span', 'class', 'Text');
            $def->addAttribute('span', 'data-type', 'Text');
            $def->addAttribute('span', 'data-latex', 'Text');
            $def->addAttribute('div', 'class', 'Text');
            $def->addAttribute('div', 'data-type', 'Text');
            $def->addAttribute('div', 'data-latex', 'Text');
            $def->addElement('video', 'Block', 'Empty', 'Common', [
                'src' => 'URI',
                'controls' => 'Bool',
                'title' => 'Text',
                'width' => 'Number',
                'height' => 'Number',
                'preload' => 'Enum#auto,metadata,none',
            ]);
        }
        self::$purifierWithImg = new \HTMLPurifier($config);
        return self::$purifierWithImg;
    }

    /**
     * Sanitize HTML from exercise statement or alternatives.
     * Preserves $$ ... $$ LaTeX blocks. Removes script, iframe, on* attributes.
     *
     * @param string|null $html Raw HTML from editor
     * @return string Sanitized HTML safe for storage and display
     */
    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $purifier = self::getPurifier();
        if ($purifier !== null) {
            $cleaned = $purifier->purify($html);
        } else {
            $allowed = '<p><strong><em><ul><ol><li><span><br>';
            $cleaned = strip_tags($html, $allowed);
        }

        $cleaned = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $cleaned);
        $cleaned = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $cleaned);
        $cleaned = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $cleaned);
        $cleaned = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $cleaned);

        return trim($cleaned);
    }

    /**
     * Sanitize HTML de enunciado de exercício permitindo imagens (ex.: jornadas – professor coloca img no enunciado).
     * Mantém tags img com src seguro (https, http, caminhos relativos como /media/serve).
     */
    public static function cleanEnunciadoWithImages(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }
        $purifier = null;
        try {
            $purifier = self::getPurifierWithImages();
        } catch (\Throwable $e) {
            $purifier = null;
        }
        if ($purifier !== null) {
            try {
                $cleaned = $purifier->purify($html);
            } catch (\Throwable $e) {
                // Fallback: não derruba a importação por HTML “sujo” do banco externo.
                $allowed = '<p><strong><b><em><i><u><s><sub><sup><ul><ol><li><span><br><h1><h2><h3><h4><h5><h6><blockquote><div><mark><img><video><table><thead><tbody><tr><th><td>';
                $cleaned = strip_tags($html, $allowed);
            }
        } else {
            $allowed = '<p><strong><b><em><i><u><s><sub><sup><ul><ol><li><span><br><h1><h2><h3><h4><h5><h6><blockquote><div><mark><img><video><table><thead><tbody><tr><th><td>';
            $cleaned = strip_tags($html, $allowed);
        }
        if (!is_string($cleaned)) {
            $cleaned = '';
        }
        $cleaned = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $cleaned) ?? $cleaned;
        // Compatibilidade: caminho legado de uploads em alguns enunciados
        $cleaned = str_replace('/public/uploads/', '/uploads/', $cleaned);
        // Segurança extra: se usar data URI em <img>, aceita apenas formatos raster base64
        $filtered = preg_replace_callback('/<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i', function ($m) {
            $src = html_entity_decode((string)($m[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (stripos($src, 'data:') === 0) {
                // Evita regex catastrófica em data URI gigante: valida só o prefixo/mime.
                if (!preg_match('#^data:image/(png|jpe?g|webp|gif);base64,#i', $src)) {
                    return '';
                }
            }
            return $m[0];
        }, $cleaned);
        if (is_string($filtered)) {
            $cleaned = $filtered;
        }
        $cleaned = self::converterMathLaunsParaLatex($cleaned);
        return trim($cleaned);
    }

    /**
     * Converte nós de matemática do Launs Editor (data-latex) para delimitadores
     * que o MathJax do aluno já renderiza: \( ... \) e $$ ... $$.
     */
    private static function converterMathLaunsParaLatex(string $html): string
    {
        if ($html === '' || (stripos($html, 'inline-math') === false && stripos($html, 'block-math') === false)) {
            return $html;
        }

        $converted = preg_replace_callback(
            '/<(span|div)\b([^>]*)>(.*?)<\/\1>/is',
            static function ($m) {
                $attrs = $m[2] ?? '';
                if (!preg_match('/data-type\s*=\s*["\'](inline-math|block-math)["\']/i', $attrs, $tm)) {
                    return $m[0];
                }
                $latex = '';
                if (preg_match('/data-latex\s*=\s*["\']([^"\']*)["\']/i', $attrs, $lm)) {
                    $latex = html_entity_decode((string) $lm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $latex = htmlspecialchars($latex, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                if (($tm[1] ?? '') === 'block-math') {
                    return '$$' . $latex . '$$';
                }
                return '\\(' . $latex . '\\)';
            },
            $html
        );

        return is_string($converted) ? $converted : $html;
    }

    /**
     * Exibe texto de forma segura: se contiver HTML (ex.: Summernote), sanitiza e retorna HTML;
     * senão escapa e converte quebras de linha em <br>.
     * Use para exibir resumo do aluno e resposta dissertativa na visão do professor (texto ou HTML já salvo).
     *
     * @param string|null $text Texto ou HTML do aluno
     * @return string HTML seguro para exibição
     */
    public static function displaySafe(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        if (strpos($text, '<') !== false) {
            return self::clean($text);
        }
        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }
}
