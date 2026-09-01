<?php

namespace App\Modulos\ModelosDocumentos\Services;

/**
 * Transforma o JSON do editor visual em HTML (tabelas — compatível com Dompdf).
 */
class DocumentoRenderer
{
    /** @var array<string,string> */
    private array $vars;

    /**
     * @param array<string,mixed> $estrutura
     * @param array<string,string> $vars
     * @return array{cabecalho:string,corpo:string,rodape:string}
     */
    public function renderizarPartes(array $estrutura, array $vars): array
    {
        $this->vars = $vars;
        return [
            'cabecalho' => $this->renderizarArea($estrutura['header']['sections'] ?? []),
            'corpo' => $this->renderizarArea($estrutura['body']['sections'] ?? []),
            'rodape' => $this->renderizarArea($estrutura['footer']['sections'] ?? []),
        ];
    }

    /**
     * @param list<array<string,mixed>> $secoes
     */
    private function renderizarArea(array $secoes): string
    {
        $html = '';
        foreach ($secoes as $secao) {
            if (!is_array($secao) || $this->secaoSemConteudo($secao)) {
                continue;
            }
            $html .= $this->renderizarSecao($secao);
        }
        return $html;
    }

    /**
     * @param array<string,mixed> $secao
     */
    private function secaoSemConteudo(array $secao): bool
    {
        foreach ($secao['columns'] ?? [] as $col) {
            if (!is_array($col)) {
                continue;
            }
            $els = $col['elements'] ?? [];
            if (is_array($els) && $els !== []) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string,mixed> $secao
     */
    private function renderizarSecao(array $secao): string
    {
        $cols = is_array($secao['columns'] ?? null) ? $secao['columns'] : [];
        if ($cols === []) {
            $cols = [['width' => 100, 'elements' => []]];
        }
        $est = is_array($secao['style'] ?? null) ? $secao['style'] : [];
        $quebraAntes = !empty($secao['pageBreakBefore']);
        $quebraDentro = !empty($secao['avoidBreak']);
        $wrapStyle = $this->cssCaixa($est);
        if ($quebraAntes) {
            $wrapStyle .= 'page-break-before:always;';
        }
        if ($quebraDentro) {
            $wrapStyle .= 'page-break-inside:avoid;';
        }

        $tds = [];
        $colgroup = [];
        $n = count($cols);
        foreach (array_values($cols) as $i => $col) {
            if (!is_array($col)) {
                continue;
            }
            $w = $this->larguraColuna($col, $n);
            $inner = '';
            $els = is_array($col['elements'] ?? null) ? $col['elements'] : [];
            foreach ($els as $el) {
                if (is_array($el)) {
                    $inner .= $this->renderizarElemento($el);
                }
            }
            if ($inner === '') {
                $inner = '<p style="margin:0;">&nbsp;</p>';
            }
            $colStyle = $this->cssCaixa(is_array($col['style'] ?? null) ? $col['style'] : []);
            $valign = $this->valign((string) ($col['vAlign'] ?? $est['vAlign'] ?? 'top'));
            $colgroup[] = '<col style="width:' . $w . '%">';
            $tds[] = '<td class="doc-col" style="width:' . $w . '%;vertical-align:' . $valign
                . ';border:none;' . $colStyle . '">' . $inner . '</td>';
        }

        return '<div class="doc-secao" style="' . $wrapStyle . '">'
            . '<table class="doc-linha" width="100%" border="0" style="width:100%;border-collapse:collapse;table-layout:fixed;border:none;">'
            . '<colgroup>' . implode('', $colgroup) . '</colgroup>'
            . '<tbody><tr>' . implode('', $tds) . '</tr></tbody></table>'
            . '</div>';
    }

    /**
     * @param array<string,mixed> $col
     */
    private function larguraColuna(array $col, int $total): int
    {
        $w = (int) ($col['width'] ?? 0);
        if ($w < 1) {
            $w = $total > 0 ? (int) floor(100 / $total) : 100;
        }
        return max(10, min(100, $w));
    }

    /**
     * @param array<string,mixed> $el
     */
    private function renderizarElemento(array $el): string
    {
        $tipo = (string) ($el['type'] ?? 'texto');
        $props = is_array($el['props'] ?? null) ? $el['props'] : [];
        $style = is_array($el['style'] ?? null) ? $el['style'] : [];
        $css = $this->cssCaixa($style) . $this->cssTipo($style);
        $isMedia = $tipo === 'logo' || $tipo === 'imagem';
        $align = $this->align((string) ($style['textAlign'] ?? $props['align'] ?? ''));
        if ($align !== '' && !$isMedia) {
            $css .= 'text-align:' . $align . ';';
        }
        $ocultarVazio = !empty($el['hideIfEmpty']);
        $html = match ($tipo) {
            'titulo' => $this->blocoTitulo($props, $css),
            'texto', 'texto_rico' => $this->blocoTexto($props, $css),
            'html' => $this->blocoHtmlLivre($this->blocoHtml($props), $css),
            'logo' => $this->blocoLogo($props, $css),
            'imagem' => $this->blocoImagem($props, $css),
            'linha' => '<hr style="border:none;border-top:1px solid #d1d5db;margin:8px 0;">',
            'espacador' => '<div style="height:' . $this->px((int) ($props['height'] ?? 16)) . 'px;"></div>',
            'quebra_pagina' => '<div style="page-break-after:always;height:1px;"></div>',
            'pagina' => '<p style="' . $css . '">Página {{pagina}} de {{total_paginas}}</p>',
            'dados_escola' => $this->tabelaChaveValor([
                'Escola' => '{{escola_nome}}',
                'CNPJ' => '{{escola_cnpj_numero}}',
                'Endereço' => '{{escola_endereco}}',
                'Contato' => '{{escola_docs}}',
            ], $css),
            'dados_aluno' => $this->tabelaChaveValor([
                'Aluno(a)' => '{{aluno_nome}}',
                'Matrícula / RA' => '{{aluno_codigo}}',
                'CPF' => '{{aluno_cpf}}',
                'Nascimento' => '{{aluno_data_nasc}}',
                'Turma' => '{{turma_nome}}',
                'Série / Curso' => '{{serie}} · {{curso_nome}}',
            ], $css),
            'dados_responsavel' => $this->tabelaChaveValor([
                'Responsável' => '{{resp_nome}}',
                'CPF' => '{{resp_cpf}}',
                'Parentesco' => '{{resp_parentesco}}',
                'Telefone' => '{{resp_telefone}}',
                'E-mail' => '{{resp_email}}',
            ], $css),
            'dados_turma' => $this->tabelaChaveValor([
                'Turma' => '{{turma_nome}}',
                'Série' => '{{serie}}',
                'Curso' => '{{curso_nome}}',
                'Ano letivo' => '{{ano_letivo}}',
                'Período' => '{{periodo_label}}',
            ], $css),
            'tabela_aluno' => $this->tabelaChaveValor([
                'Aluno(a)' => '{{aluno_nome}}',
                'CPF' => '{{aluno_cpf}}',
                'Turma' => '{{turma_nome}}',
                'Série' => '{{serie}}',
                'Ano' => '{{ano_letivo}}',
            ], $css),
            'tabela_notas' => $this->blocoHtmlLivre(
                '<div class="quadro-notas-wrap">{{quadro_notas_html}}</div>',
                $css
            ),
            'tabela_frequencia' => $this->blocoHtmlLivre('{{frequencia_html}}', $css),
            'historico' => $this->blocoHtmlLivre('{{historico_html}}', $css),
            'resultado_final' => $this->tabelaChaveValor([
                'Situação final' => '{{situacao_final}}',
                'Frequência' => '{{frequencia_percentual}}',
            ], $css),
            'frequencia' => $this->blocoHtmlLivre(
                '<p style="margin:0 0 6px;font-weight:bold;">Frequência</p>'
                . '<p style="margin:0;">{{frequencia_percentual}}</p>{{frequencia_html}}',
                $css
            ),
            'observacoes' => $this->blocoHtmlLivre(
                '<p style="margin:0 0 6px;font-weight:bold;">Observações</p>'
                . '<p style="margin:0;">{{observacoes}}</p>',
                $css
            ),
            'assinaturas' => $this->blocoAssinaturas($props, $css),
            'qrcode' => '<p style="' . $css . 'font-size:8pt;color:#666;">QR Code na emissão (quando disponível)</p>',
            default => $this->blocoTexto($props, $css),
        };

        if ($ocultarVazio && $this->htmlVisualmenteVazio($html)) {
            return '';
        }
        return $html;
    }

    /**
     * @param array<string,mixed> $props
     */
    private function textoComPlaceholders(string $txt): string
    {
        $parts = preg_split('/(\{\{\s*[a-z0-9_]+\s*\}\})/i', $txt, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return htmlspecialchars($txt, ENT_QUOTES, 'UTF-8');
        }
        $out = '';
        foreach ($parts as $p) {
            if (preg_match('/^\{\{\s*([a-z0-9_]+)\s*\}\}$/i', $p, $m)) {
                $out .= '{{' . strtolower($m[1]) . '}}';
            } else {
                $out .= htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
            }
        }
        return $out;
    }

    public static function htmlPermitido(string $html): string
    {
        $html = preg_replace('#<(script|iframe|object|embed|link|meta|form|svg)(\s[^>]*)?>.*?</\1>#is', '', $html) ?? $html;
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><span><div><table><thead><tbody><tr><td><th><colgroup><col><h1><h2><h3><ul><ol><li><hr><img>');
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript:/i', '', $html) ?? $html;
        $html = (string) preg_replace('/page-break[^;\"\']*;?/i', '', $html);
        $html = (string) preg_replace('/break-(before|after|inside)\s*:[^;\"\']*;?/i', '', $html);
        $html = (string) preg_replace('/min-height\s*:\s*[^;\"\']+;?/i', '', $html);
        $html = (string) preg_replace('/(?:^|[;\"\'])\s*height\s*:\s*100%\s*;?/i', '', $html);
        $html = preg_replace_callback('/<img\b([^>]*)>/i', static function (array $m): string {
            $attrs = $m[1] ?? '';
            $src = '';
            if (preg_match('/src\s*=\s*("|\')([^"\']+)\1/i', $attrs, $sm)) {
                $src = $sm[2];
            }
            if (!preg_match('#^data:image/(png|jpeg|jpg|gif|webp);base64,[a-z0-9+/]+=*$#i', $src)) {
                return '';
            }
            if (strlen($src) > 400000) {
                return '';
            }
            return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="">';
        }, $html) ?? $html;
        return $html;
    }

    private function srcImagemPermitido(string $src): string
    {
        $src = trim($src);
        if (preg_match('#^data:image/(png|jpeg|jpg|gif|webp);base64,[a-z0-9+/]+=*$#i', $src) !== 1) {
            return '';
        }
        if (strlen($src) > 400000) {
            return '';
        }
        return $src;
    }

    /**
     * @param array<string,mixed> $props
     */
    private function blocoTitulo(array $props, string $css): string
    {
        $txt = $this->textoComPlaceholders((string) ($props['text'] ?? 'TÍTULO'));
        $tag = in_array((string) ($props['tag'] ?? 'h1'), ['h1', 'h2', 'h3'], true) ? (string) $props['tag'] : 'h1';
        $size = match ($tag) {
            'h2' => '13pt',
            'h3' => '11pt',
            default => '16pt',
        };
        return '<' . $tag . ' style="margin:0 0 8px;font-size:' . $size . ';' . $css . '">'
            . $txt . '</' . $tag . '>';
    }

    /**
     * @param array<string,mixed> $props
     */
    private function blocoTexto(array $props, string $css): string
    {
        $raw = (string) ($props['html'] ?? $props['text'] ?? '');
        if ($raw === '') {
            $raw = '<p>&nbsp;</p>';
        }
        if (str_contains($raw, '<')) {
            $txt = self::htmlPermitido($raw);
        } else {
            $txt = '<p>' . nl2br($this->textoComPlaceholders($raw), false) . '</p>';
        }
        return '<div style="' . $css . '">' . $txt . '</div>';
    }

    /**
     * @param array<string,mixed> $props
     */
    private function blocoHtml(array $props): string
    {
        return self::htmlPermitido((string) ($props['html'] ?? ''));
    }

    /**
     * @param array<string,mixed> $props
     */
    private function blocoLogo(array $props, string $css): string
    {
        $w = (int) ($props['width'] ?? 120);
        $w = max(24, min(400, $w));
        $inner = '<span class="doc-logo-el" style="display:inline-block;max-width:' . $w . 'px;">{{logo_html}}</span>';

        return $this->blocoPosicionado($inner, $props, $css);
    }

    /**
     * @param array<string,mixed> $props
     */
    private function blocoImagem(array $props, string $css): string
    {
        $src = $this->srcImagemPermitido((string) ($props['src'] ?? ''));
        if ($src === '') {
            return '<p style="' . $css . 'color:#9ca3af;font-size:9pt;">[imagem]</p>';
        }
        $w = (int) ($props['width'] ?? 180);
        $w = max(24, min(700, $w));
        $inner = '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
            . '" alt="" style="max-width:' . $w . 'px;width:auto;height:auto;">';

        return $this->blocoPosicionado($inner, $props, $css);
    }

    /**
     * Centraliza logo/imagem na célula (tabela — Dompdf não honra flex).
     *
     * @param array<string,mixed> $props
     */
    private function blocoPosicionado(string $inner, array $props, string $css): string
    {
        $h = $this->align((string) ($props['align'] ?? 'center'));
        if ($h === '' || $h === 'justify') {
            $h = 'center';
        }

        return '<p style="margin:0;text-align:' . $h . ';' . $css . '">' . $inner . '</p>';
    }

    /**
     * @param array<string,mixed> $props
     */
    private function blocoAssinaturas(array $props, string $css): string
    {
        $qtd = (int) ($props['quantidade'] ?? 2);
        $qtd = max(1, min(3, $qtd));
        $celulas = [
            '<p style="text-align:center;margin:36px 0 0;">________________________</p>'
            . '<p class="nome" style="text-align:center;margin:4px 0 0;font-weight:bold;">{{resp_nome}}</p>'
            . '<p class="cargo" style="text-align:center;margin:0;">Responsável</p>',
            '<p style="text-align:center;margin:36px 0 0;">________________________</p>'
            . '<p class="nome" style="text-align:center;margin:4px 0 0;font-weight:bold;">{{assinante_nome}}</p>'
            . '<p class="cargo" style="text-align:center;margin:0;">{{assinante_cargo}}</p>',
            '<p style="text-align:center;margin:36px 0 0;">________________________</p>'
            . '<p class="nome" style="text-align:center;margin:4px 0 0;font-weight:bold;">{{secretario_nome}}</p>'
            . '<p class="cargo" style="text-align:center;margin:0;">Secretaria</p>',
        ];
        $larguras = $qtd === 1 ? [100] : ($qtd === 2 ? [50, 50] : [34, 33, 33]);
        $tds = [];
        $cols = [];
        for ($i = 0; $i < $qtd; $i++) {
            $w = $larguras[$i];
            $cols[] = '<col style="width:' . $w . '%">';
            $tds[] = '<td style="width:' . $w . '%;vertical-align:top;border:none;">' . $celulas[$i] . '</td>';
        }
        return '<div style="' . $css . '"><table width="100%" style="width:100%;border-collapse:collapse;table-layout:fixed;">'
            . '<colgroup>' . implode('', $cols) . '</colgroup><tr>' . implode('', $tds) . '</tr></table></div>';
    }

    /**
     * @param array<string,string> $linhas
     */
    private function tabelaChaveValor(array $linhas, string $css): string
    {
        $rows = '';
        foreach ($linhas as $label => $valor) {
            $rows .= '<tr><td class="label" style="width:32%;font-weight:bold;padding:4px 8px;border:1px solid #ccc;">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                . '</td><td style="padding:4px 8px;border:1px solid #ccc;">' . $valor . '</td></tr>';
        }
        return '<table class="dados" width="100%" style="width:100%;border-collapse:collapse;margin:0 0 10px;'
            . $css . '">' . $rows . '</table>';
    }

    private function blocoHtmlLivre(string $inner, string $css): string
    {
        return '<div style="' . $css . '">' . $inner . '</div>';
    }

    /**
     * @param array<string,mixed> $est
     */
    private function cssCaixa(array $est): string
    {
        $out = '';
        foreach (['margin', 'padding'] as $box) {
            $v = $est[$box] ?? null;
            if (!is_array($v)) {
                continue;
            }
            $t = $this->num($v['top'] ?? 0);
            $r = $this->num($v['right'] ?? 0);
            $b = $this->num($v['bottom'] ?? 0);
            $l = $this->num($v['left'] ?? 0);
            if ($t + $r + $b + $l > 0) {
                $out .= $box . ':' . $t . 'px ' . $r . 'px ' . $b . 'px ' . $l . 'px;';
            }
        }
        $bg = trim((string) ($est['background'] ?? ''));
        if ($bg !== '' && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $bg) === 1) {
            $out .= 'background:' . $bg . ';';
        }
        $border = strtolower((string) ($est['borderStyle'] ?? 'none'));
        if (in_array($border, ['solid', 'dashed', 'dotted'], true)) {
            $bw = max(0, min(8, (int) ($est['borderWidth'] ?? 1)));
            $bc = (string) ($est['borderColor'] ?? '#e5e7eb');
            if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $bc) !== 1) {
                $bc = '#e5e7eb';
            }
            $out .= 'border:' . $bw . 'px ' . $border . ' ' . $bc . ';';
        }
        $radius = (int) ($est['borderRadius'] ?? 0);
        if ($radius > 0) {
            $out .= 'border-radius:' . min(40, $radius) . 'px;';
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $est
     */
    private function cssTipo(array $est): string
    {
        $out = '';
        $size = (int) ($est['fontSize'] ?? 0);
        if ($size >= 8 && $size <= 48) {
            $out .= 'font-size:' . $size . 'pt;';
        }
        $weight = (string) ($est['fontWeight'] ?? '');
        if (in_array($weight, ['normal', 'bold', '600', '700'], true)) {
            $out .= 'font-weight:' . $weight . ';';
        }
        $color = (string) ($est['color'] ?? '');
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color) === 1) {
            $out .= 'color:' . $color . ';';
        }
        $lh = (float) ($est['lineHeight'] ?? 0);
        if ($lh >= 1 && $lh <= 3) {
            $out .= 'line-height:' . $lh . ';';
        }
        if (!empty($est['italic'])) {
            $out .= 'font-style:italic;';
        }
        if (!empty($est['underline'])) {
            $out .= 'text-decoration:underline;';
        }
        return $out;
    }

    private function align(string $v): string
    {
        $v = strtolower(trim($v));
        return in_array($v, ['left', 'center', 'right', 'justify'], true) ? $v : '';
    }

    private function valign(string $v): string
    {
        return match (strtolower(trim($v))) {
            'middle', 'meio' => 'middle',
            'bottom', 'base' => 'bottom',
            default => 'top',
        };
    }

    private function num(mixed $v): int
    {
        return max(0, min(80, (int) $v));
    }

    private function px(int $v): int
    {
        return max(4, min(120, $v));
    }

    private function htmlVisualmenteVazio(string $html): bool
    {
        $txt = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $txt === '' || $txt === '—';
    }
}
