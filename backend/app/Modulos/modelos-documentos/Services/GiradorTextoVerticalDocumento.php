<?php

namespace App\Modulos\ModelosDocumentos\Services;

/**
 * O editor gira o texto com writing-mode. O Dompdf ignora isso e a frase
 * sai deitada, vazando da célula. No PDF a frase vai numa caixa do tamanho
 * da célula e gira com transform, que os dois motores desenham.
 */
class GiradorTextoVerticalDocumento
{
    /** Largura útil da folha A4 com margem de 8 mm de cada lado, em mm. */
    private const LARGURA_MM = 190.0;

    /** Soma das linhas que ainda cabe na área útil, com folga para borda e preenchimento. */
    private const ALTURA_UTIL_MM = 248.0;

    public static function aplicar(string $html): string
    {
        if (!str_contains($html, 'edoc-vert') && !str_contains($html, 'edoc-logo')) {
            return $html;
        }

        $dom = new \DOMDocument();
        $erros = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="edoc-raiz">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($erros);
        if (!$ok) {
            return $html;
        }

        $xp = new \DOMXPath($dom);
        $tabelas = $xp->query(
            '//table[contains(concat(" ", normalize-space(@class), " "), " edoc-grade-livre ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " seed-folha ")]'
        );
        if ($tabelas === false) {
            return $html;
        }
        foreach ($tabelas as $tabela) {
            if ($tabela instanceof \DOMElement) {
                self::tabela($tabela, $dom);
            }
        }

        $raiz = $dom->getElementById('edoc-raiz');
        if (!$raiz instanceof \DOMElement) {
            return $html;
        }
        $saida = '';
        foreach ($raiz->childNodes as $filho) {
            $trecho = $dom->saveHTML($filho);
            if (is_string($trecho)) {
                $saida .= $trecho;
            }
        }

        return $saida !== '' ? $saida : $html;
    }

    private static function tabela(\DOMElement $tabela, \DOMDocument $dom): void
    {
        $larguras = self::larguras($tabela);
        $linhas = self::linhas($tabela);
        if ($linhas === []) {
            return;
        }
        $alturas = [];
        foreach ($linhas as $i => $tr) {
            $alturas[$i] = self::alturaMm($tr->getAttribute('style'));
        }
        $alturas = self::esticarTextoVertical($linhas, $alturas);
        $alturas = self::encaixarNaFolha($linhas, $alturas);

        $ocupado = [];
        foreach ($linhas as $ri => $tr) {
            $ci = 0;
            foreach (self::celulas($tr) as $td) {
                while (!empty($ocupado[$ri][$ci])) {
                    $ci++;
                }
                $cs = max(1, (int) $td->getAttribute('colspan'));
                $rs = max(1, (int) $td->getAttribute('rowspan'));
                for ($r = 0; $r < $rs; $r++) {
                    for ($c = 0; $c < $cs; $c++) {
                        $ocupado[$ri + $r][$ci + $c] = true;
                    }
                }
                $classes = ' ' . $td->getAttribute('class') . ' ';
                $altMm = 0.0;
                for ($r = 0; $r < $rs; $r++) {
                    $altMm += $alturas[$ri + $r] ?? 5.5;
                }
                self::travarCelula($td, $altMm);
                if (str_contains($classes, ' edoc-logo ')) {
                    self::limitarLogo($td);
                }
                if (str_contains($classes, ' edoc-vert ') && !self::jaGirou($td)) {
                    $colMm = 0.0;
                    for ($c = 0; $c < $cs; $c++) {
                        $colMm += $larguras[$ci + $c] ?? self::larguraRestante($larguras);
                    }
                    $colMm = ($colMm / 100) * self::LARGURA_MM;
                    self::girar($td, $dom, $colMm, $altMm, self::textoDaCelula($td));
                }
                $ci += $cs;
            }
        }
    }

    /**
     * @return list<float> percentuais
     */
    private static function larguras(\DOMElement $tabela): array
    {
        $out = [];
        foreach ($tabela->getElementsByTagName('col') as $col) {
            if (!$col instanceof \DOMElement) {
                continue;
            }
            if (preg_match('/width\s*:\s*([0-9.]+)\s*%/i', $col->getAttribute('style'), $m) !== 1) {
                continue;
            }
            $out[] = (float) $m[1];
        }

        return $out;
    }

    private static function larguraRestante(array $larguras): float
    {
        if ($larguras === []) {
            return 8.0;
        }
        $usado = array_sum($larguras);

        return max(4.0, (100 - $usado) / 2);
    }

    /**
     * @return list<\DOMElement>
     */
    private static function linhas(\DOMElement $tabela): array
    {
        $linhas = [];
        foreach ($tabela->childNodes as $nodo) {
            if (!$nodo instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($nodo->tagName);
            if ($tag === 'tbody' || $tag === 'thead' || $tag === 'tfoot') {
                foreach ($nodo->childNodes as $tr) {
                    if ($tr instanceof \DOMElement && strtolower($tr->tagName) === 'tr') {
                        $linhas[] = $tr;
                    }
                }
            } elseif ($tag === 'tr') {
                $linhas[] = $nodo;
            }
        }

        return $linhas;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function celulas(\DOMElement $tr): array
    {
        $celulas = [];
        foreach ($tr->childNodes as $nodo) {
            if (!$nodo instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($nodo->tagName);
            if ($tag === 'td' || $tag === 'th') {
                $celulas[] = $nodo;
            }
        }

        return $celulas;
    }

    private static function alturaMm(string $style): float
    {
        if (preg_match('/height\s*:\s*([0-9.]+)\s*mm/i', $style, $m) === 1) {
            $mm = (float) $m[1];
            if ($mm > 0) {
                return $mm;
            }
        }

        return 5.5;
    }

    private static function textoDaCelula(\DOMElement $td): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $td->textContent) ?? '');

        return $texto;
    }

    /** Largura aproximada do texto em mm, fonte DejaVu negrito. */
    private static function larguraTextoMm(string $texto, float $pt): float
    {
        if ($texto === '') {
            return 0.0;
        }

        return mb_strlen($texto) * $pt * 0.62 * 0.3528;
    }

    /**
     * Linha de uma célula só: se o texto vertical não cabe, a linha cresce.
     * O encaixe na folha reduz tudo depois, se a soma passar da página.
     *
     * @param list<\DOMElement> $linhas
     * @param list<float> $alturas
     * @return list<float>
     */
    private static function esticarTextoVertical(array $linhas, array $alturas): array
    {
        $ocupado = [];
        foreach ($linhas as $ri => $tr) {
            $ci = 0;
            foreach (self::celulas($tr) as $td) {
                while (!empty($ocupado[$ri][$ci])) {
                    $ci++;
                }
                $cs = max(1, (int) $td->getAttribute('colspan'));
                $rs = max(1, (int) $td->getAttribute('rowspan'));
                for ($r = 0; $r < $rs; $r++) {
                    for ($c = 0; $c < $cs; $c++) {
                        $ocupado[$ri + $r][$ci + $c] = true;
                    }
                }
                $classes = ' ' . $td->getAttribute('class') . ' ';
                if ($rs === 1 && str_contains($classes, ' edoc-vert ')) {
                    $altMm = $alturas[$ri] ?? 5.5;
                    $precisa = self::larguraTextoMm(self::textoDaCelula($td), 5.5) + 1.2;
                    if ($precisa > $altMm) {
                        $alturas[$ri] = min($precisa, 12.0);
                        $tr->setAttribute('style', self::definirAltura($tr->getAttribute('style'), $alturas[$ri]));
                    }
                }
                $ci += $cs;
            }
        }

        return $alturas;
    }

    /**
     * @param list<\DOMElement> $linhas
     * @param list<float> $alturas
     * @return list<float>
     */
    private static function encaixarNaFolha(array $linhas, array $alturas): array
    {
        $soma = array_sum($alturas);
        if ($soma <= self::ALTURA_UTIL_MM || $soma <= 0) {
            return $alturas;
        }
        $fator = self::ALTURA_UTIL_MM / $soma;
        foreach ($linhas as $i => $tr) {
            $alturas[$i] = max(3.4, ($alturas[$i] ?? 5.5) * $fator);
            $tr->setAttribute('style', self::definirAltura($tr->getAttribute('style'), $alturas[$i]));
        }

        return $alturas;
    }

    private static function definirAltura(string $style, float $mm): string
    {
        $valor = number_format($mm, 2, '.', '') . 'mm';
        if (preg_match('/height\s*:\s*[^;]+/i', $style) === 1) {
            $novo = preg_replace('/height\s*:\s*[^;]+/i', 'height:' . $valor, $style, 1);

            return is_string($novo) ? $novo : 'height:' . $valor;
        }
        $style = rtrim(trim($style), ';');

        return ($style === '' ? '' : $style . ';') . 'height:' . $valor;
    }

    private static function jaGirou(\DOMElement $td): bool
    {
        if (str_contains(' ' . $td->getAttribute('class') . ' ', ' edoc-vert-caixa ')) {
            return true;
        }
        foreach ($td->getElementsByTagName('div') as $div) {
            if ($div instanceof \DOMElement && str_contains(' ' . $div->getAttribute('class') . ' ', ' edoc-giro ')) {
                return true;
            }
        }

        return false;
    }

    private static function girar(\DOMElement $td, \DOMDocument $dom, float $colMm, float $altMm, string $texto): void
    {
        $colMm = max(4.0, $colMm);
        $altMm = max(4.0, $altMm);
        $esp = max(3.0, $colMm - 1.2);
        $comp = max($esp + 2.0, $altMm - 0.6);
        $pt = 6.0;
        $largura = self::larguraTextoMm($texto, $pt);
        if ($largura > $comp && $largura > 0) {
            $pt = max(4.2, $pt * ($comp / $largura));
        }
        $mx = ($colMm - $comp) / 2;
        $my = ($altMm - $esp) / 2;
        $fmt = static fn (float $n): string => number_format($n, 2, '.', '');
        $ptCss = number_format($pt, 2, '.', '');
        $style = 'display:block;width:' . $fmt($comp) . 'mm;height:' . $fmt($esp) . 'mm;'
            . 'margin:' . $fmt($my) . 'mm ' . $fmt($mx) . 'mm;'
            . 'transform:rotate(-90deg);transform-origin:center center;'
            . 'text-align:center;line-height:' . $fmt($esp) . 'mm;white-space:nowrap;'
            . 'font-weight:700;font-size:' . $ptCss . 'pt;letter-spacing:0;overflow:hidden;';

        $div = $dom->createElement('div');
        $div->setAttribute('class', 'edoc-giro');
        $div->setAttribute('style', $style);
        while ($td->firstChild) {
            $div->appendChild($td->firstChild);
        }
        $td->appendChild($div);
        $td->setAttribute('class', trim($td->getAttribute('class') . ' edoc-vert-caixa'));
    }

    /**
     * O Dompdf trata a altura da linha como mínimo. Fonte e preenchimento
     * herdados (11pt, entrelinha 1.5) esticam a grade e o fim sai da folha.
     */
    private static function travarCelula(\DOMElement $td, float $altMm): void
    {
        $classes = ' ' . $td->getAttribute('class') . ' ';
        $pt = '6.5';
        if (str_contains($classes, ' edoc-titulo ')) {
            $pt = '9';
        } elseif (str_contains($classes, ' edoc-sub ')) {
            $pt = '7.5';
        }
        $vertical = str_contains($classes, ' edoc-vert ');
        $pad = ($vertical || str_contains($classes, ' edoc-logo ')) ? '0' : '0.15mm 0.35mm';
        $extra = 'box-sizing:border-box;height:' . number_format(max(3.4, $altMm), 2, '.', '') . 'mm;'
            . 'padding:' . $pad . ';line-height:1;font-size:' . $pt . 'pt;overflow:' . ($vertical ? 'visible' : 'hidden') . ';';
        $style = preg_replace(
            '/(?:^|;)\s*(?:box-sizing|height|padding(?:-(?:top|right|bottom|left))?|line-height|font-size|overflow)\s*:[^;]*/i',
            '',
            $td->getAttribute('style')
        );
        $style = trim(is_string($style) ? $style : '', " \t;");
        $td->setAttribute('style', ($style === '' ? '' : $style . ';') . $extra);
        foreach (['p', 'div'] as $tag) {
            foreach ($td->getElementsByTagName($tag) as $filho) {
                if (!$filho instanceof \DOMElement) {
                    continue;
                }
                if (str_contains(' ' . $filho->getAttribute('class') . ' ', ' edoc-giro ')) {
                    continue;
                }
                $filhoStyle = trim($filho->getAttribute('style'), " \t;");
                if (preg_match('/(?:^|;)\s*margin\s*:/i', $filhoStyle) !== 1) {
                    $filho->setAttribute('style', ($filhoStyle === '' ? '' : $filhoStyle . ';') . 'margin:0;');
                }
            }
        }
    }

    private static function limitarLogo(\DOMElement $td): void
    {
        foreach ($td->getElementsByTagName('img') as $img) {
            if (!$img instanceof \DOMElement) {
                continue;
            }
            $img->setAttribute('width', '60');
            $img->setAttribute('height', '36');
            $img->setAttribute(
                'style',
                'width:16mm;max-width:16mm;max-height:14mm;height:auto;object-fit:contain;display:inline-block;vertical-align:middle;'
            );
        }
    }
}

if (!class_exists('GiradorTextoVerticalDocumento', false) && class_exists(__NAMESPACE__ . '\\GiradorTextoVerticalDocumento', false)) {
    class_alias(__NAMESPACE__ . '\\GiradorTextoVerticalDocumento', 'GiradorTextoVerticalDocumento');
}
