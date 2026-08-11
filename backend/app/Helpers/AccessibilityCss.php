<?php

require_once __DIR__ . '/CatalogoRegraMascara.php';
require_once __DIR__ . '/../Services/MascaraResolver.php';

if (!class_exists('AccessibilityCss')) {
/**
 * EducaInclui — gera o CSS de acessibilidade visual a partir das regras da
 * Máscara de Acessibilidade ativa de um aluno.
 *
 * Abordagem "controlada": escala apenas as classes de texto do Tailwind
 * (.text-*) e a tipografia/contraste, SEM alterar a raiz (html) — o que mantém
 * os contêineres/espaçamentos do layout estáveis. É o mesmo CSS usado na
 * realização da prova, reaproveitado para valer no sistema inteiro do aluno.
 */
class AccessibilityCss
{
    /**
     * Resolve a máscara ativa do aluno e devolve [styleHtml, wrapperClass].
     * Seguro quando o módulo ainda não foi migrado (retorna vazio).
     *
     * @return array{0:string,1:string}
     */
    public static function buildForAluno(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return ['', ''];
        }
        $mask = MascaraResolver::resolveForAluno($alunoId);
        if (empty($mask['active']) || empty($mask['rules'])) {
            return ['', ''];
        }
        return self::build($mask['rules']);
    }

    /**
     * Monta o CSS de acessibilidade e a classe do wrapper a partir das regras.
     *
     * @param array<string,string> $rules
     * @return array{0:string,1:string} [styleHtml, wrapperClass]
     */
    public static function build(array $rules): array
    {
        $classes = ['ei-accessible'];
        $css = '';
        $imports = '';

        $fontFamilyRule = self::rule($rules, 'visual_font_family', MascaraResolver::fontFamily($rules));
        $famCss = CatalogoRegraMascara::fontFamilyCss($fontFamilyRule);
        if ($famCss !== '') {
            $classes[] = 'ei-font-custom';
            $imports .= "@import url('https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=Lexend:wght@400;500;600;700&display=swap');";
            $css .= "@font-face{font-family:'OpenDyslexic';src:url('https://cdn.jsdelivr.net/npm/@fontsource/opendyslexic@5.0.0/files/opendyslexic-latin-400-normal.woff2') format('woff2');font-weight:400;font-display:swap}"
                . "@font-face{font-family:'OpenDyslexic';src:url('https://cdn.jsdelivr.net/npm/@fontsource/opendyslexic@5.0.0/files/opendyslexic-latin-700-normal.woff2') format('woff2');font-weight:700;font-display:swap}"
                . '.ei-font-custom,.ei-font-custom p,.ei-font-custom span,.ei-font-custom label,.ei-font-custom li,.ei-font-custom div,.ei-font-custom button{font-family:' . $famCss . '!important}';
        }

        $fontSizeRule = self::rule($rules, 'visual_font_size', MascaraResolver::fontSize($rules));
        $factor = CatalogoRegraMascara::fontScaleFactor($fontSizeRule);
        if (abs($factor - 1.0) > 0.001) {
            $f = number_format($factor, 2, '.', '');
            $css .= '.ei-accessible .text-sm{font-size:calc(0.875rem * ' . $f . ')!important}'
                . '.ei-accessible .text-base{font-size:calc(1rem * ' . $f . ')!important}'
                . '.ei-accessible .text-lg{font-size:calc(1.125rem * ' . $f . ')!important}'
                . '.ei-accessible .text-xl{font-size:calc(1.25rem * ' . $f . ')!important}'
                . '.ei-accessible .text-2xl{font-size:calc(1.5rem * ' . $f . ')!important}'
                . '.ei-accessible p,.ei-accessible label,.ei-accessible li{line-height:1.6!important}';
        }

        $textSpacing = self::rule($rules, 'visual_text_spacing', CatalogoRegraMascara::VISUAL_SPACING_NORMAL);
        if ($textSpacing === CatalogoRegraMascara::VISUAL_SPACING_MEDIUM) {
            $classes[] = 'ei-text-spacing-medium';
            $css .= '.ei-text-spacing-medium p,.ei-text-spacing-medium label,.ei-text-spacing-medium li,.ei-text-spacing-medium .text-sm,.ei-text-spacing-medium .text-base{line-height:1.75!important;letter-spacing:.01em!important}';
        } elseif ($textSpacing === CatalogoRegraMascara::VISUAL_SPACING_LARGE) {
            $classes[] = 'ei-text-spacing-large';
            $css .= '.ei-text-spacing-large p,.ei-text-spacing-large label,.ei-text-spacing-large li,.ei-text-spacing-large .text-sm,.ei-text-spacing-large .text-base{line-height:2!important;letter-spacing:.025em!important}';
        }

        $elementSpacing = self::rule($rules, 'visual_element_spacing', CatalogoRegraMascara::VISUAL_SPACING_NORMAL);
        if ($elementSpacing === CatalogoRegraMascara::VISUAL_SPACING_MEDIUM) {
            $classes[] = 'ei-element-spacing-medium';
            $css .= '.ei-element-spacing-medium .space-y-3>*+*{margin-top:1rem!important}.ei-element-spacing-medium .space-y-4>*+*{margin-top:1.35rem!important}.ei-element-spacing-medium .gap-2{gap:.75rem!important}.ei-element-spacing-medium .gap-3{gap:1rem!important}.ei-element-spacing-medium .questao-container{padding:2rem!important}';
        } elseif ($elementSpacing === CatalogoRegraMascara::VISUAL_SPACING_LARGE) {
            $classes[] = 'ei-element-spacing-large';
            $css .= '.ei-element-spacing-large .space-y-3>*+*{margin-top:1.35rem!important}.ei-element-spacing-large .space-y-4>*+*{margin-top:1.75rem!important}.ei-element-spacing-large .gap-2{gap:1rem!important}.ei-element-spacing-large .gap-3{gap:1.25rem!important}.ei-element-spacing-large .questao-container{padding:2.5rem!important}';
        }

        $buttonSize = self::rule($rules, 'visual_button_size', CatalogoRegraMascara::VISUAL_BUTTON_NORMAL);
        if ($buttonSize === CatalogoRegraMascara::VISUAL_BUTTON_LARGE) {
            $classes[] = 'ei-button-large';
            $css .= '.ei-button-large button,.ei-button-large a.inline-flex,.ei-button-large input[type=submit]{min-height:3rem!important;padding:.75rem 1.1rem!important;font-size:1rem!important}';
        } elseif ($buttonSize === CatalogoRegraMascara::VISUAL_BUTTON_XLARGE) {
            $classes[] = 'ei-button-xlarge';
            $css .= '.ei-button-xlarge button,.ei-button-xlarge a.inline-flex,.ei-button-xlarge input[type=submit]{min-height:3.5rem!important;padding:1rem 1.35rem!important;font-size:1.125rem!important}';
        }

        if (self::isOnAny($rules, ['visual_highlight_buttons'])) {
            $classes[] = 'ei-highlight-buttons';
            $css .= '.ei-highlight-buttons button,.ei-highlight-buttons a.inline-flex,.ei-highlight-buttons input[type=submit]{border-width:2px!important;box-shadow:0 0 0 2px rgba(37,99,235,.12)!important}.ei-highlight-buttons button:hover,.ei-highlight-buttons a.inline-flex:hover{filter:contrast(1.08)!important}';
        }

        if (self::isOnAny($rules, ['visual_highlight_focus'])) {
            $classes[] = 'ei-highlight-focus';
            $css .= '.ei-highlight-focus :focus,.ei-highlight-focus :focus-visible{outline:4px solid #f59e0b!important;outline-offset:3px!important;box-shadow:0 0 0 6px rgba(245,158,11,.18)!important}';
        }

        $contrast = self::rule($rules, 'visual_contrast', MascaraResolver::isOn($rules, 'high_contrast') ? CatalogoRegraMascara::VISUAL_CONTRAST_HIGH : CatalogoRegraMascara::VISUAL_CONTRAST_DEFAULT);
        if ($contrast === CatalogoRegraMascara::VISUAL_CONTRAST_HIGH) {
            $classes[] = 'ei-contrast';
            $css .= '.ei-contrast,.ei-contrast .bg-white,.ei-contrast .bg-gray-50,.ei-contrast .bg-blue-50{background:#000!important;color:#fff!important}'
                . '.ei-contrast .text-gray-900,.ei-contrast .text-gray-800,.ei-contrast .text-gray-700,.ei-contrast .text-gray-600,.ei-contrast .text-gray-500,.ei-contrast .text-blue-800{color:#fff!important}'
                . '.ei-contrast .border,.ei-contrast .border-gray-200,.ei-contrast .border-gray-100,.ei-contrast .border-blue-200{border-color:#fff!important}'
                . '.ei-contrast a{color:#7dd3fc!important}';
        } elseif ($contrast === CatalogoRegraMascara::VISUAL_CONTRAST_INVERTED) {
            $classes[] = 'ei-contrast-inverted';
            $css .= '.ei-contrast-inverted{filter:invert(1) hue-rotate(180deg)!important;background:#000!important}.ei-contrast-inverted img,.ei-contrast-inverted video,.ei-contrast-inverted iframe{filter:invert(1) hue-rotate(180deg)!important}';
        } elseif ($contrast === CatalogoRegraMascara::VISUAL_CONTRAST_GRAYSCALE) {
            $classes[] = 'ei-grayscale';
            $css .= '.ei-grayscale{filter:grayscale(1)!important}';
        }

        if (MascaraResolver::isOn($rules, 'hide_timer')) {
            $classes[] = 'ei-hide-timer';
            $css .= '.ei-hide-timer #timer,.ei-hide-timer #timer + p{display:none!important}';
        }

        if (MascaraResolver::isOn($rules, 'highlight_keywords')) {
            $classes[] = 'ei-highlight';
            $css .= '.ei-highlight mark.ei-kw{background:#fde68a;color:#1f2937;padding:0 .12em;border-radius:.18em;font-weight:600}'
                . '.ei-contrast mark.ei-kw{background:#fde047;color:#000}';
        }

        if (self::isOnAny($rules, ['visual_focus_mode'])) {
            $classes[] = 'ei-focus-mode';
            $css .= '.ei-focus-mode{background:#fff!important}.ei-focus-mode .sidebar-user-info,.ei-focus-mode .fixed.bottom-6.right-6,.ei-focus-mode [data-secondary-widget]{display:none!important}.ei-focus-mode main{background:#fff!important}';
        }

        if (self::isOnAny($rules, ['visual_reduce_motion'])) {
            $classes[] = 'ei-reduce-motion';
            $css .= '.ei-reduce-motion *,.ei-reduce-motion *::before,.ei-reduce-motion *::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}';
        }

        if (self::isOnAny($rules, ['visual_highlight_cursor'])) {
            $classes[] = 'ei-highlight-cursor';
            $css .= '.ei-highlight-cursor,.ei-highlight-cursor *{cursor:url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2732%27 height=%2732%27 viewBox=%270 0 32 32%27%3E%3Ccircle cx=%2710%27 cy=%2710%27 r=%278%27 fill=%27none%27 stroke=%27%23f59e0b%27 stroke-width=%274%27/%3E%3Cpath d=%27M10 10 L26 26%27 stroke=%27%23000%27 stroke-width=%273%27/%3E%3C/svg%3E") 10 10,auto!important}';
        }

        $style = ($imports !== '' || $css !== '') ? ('<style id="ei-accessibility">' . $imports . $css . '</style>') : '';
        return [$style, implode(' ', $classes)];
    }

    /**
     * @param array<string,string> $rules
     */
    private static function rule(array $rules, string $key, string $default = ''): string
    {
        $value = trim((string) ($rules[$key] ?? ''));
        return $value !== '' ? $value : $default;
    }

    /**
     * @param array<string,string> $rules
     * @param list<string> $keys
     */
    private static function isOnAny(array $rules, array $keys): bool
    {
        foreach ($keys as $key) {
            if (MascaraResolver::isOn($rules, $key)) {
                return true;
            }
        }
        return false;
    }
}
}
