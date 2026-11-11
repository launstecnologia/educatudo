<?php

namespace App\Modulos\ModelosDocumentos\Services;


/**
 * Converte a resposta da IA (foto de um documento) na estrutura do editor.
 */
class ReprodutorLayoutDocumentoService
{
    /** @var list<string> */
    private const TIPOS = [
        'titulo', 'texto', 'texto_rico', 'html', 'logo',
        'dados_escola', 'dados_aluno', 'dados_responsavel', 'dados_turma',
        'boletim', 'demonstrativo_notas', 'tabela_notas', 'tabela_aluno', 'tabela_frequencia', 'tabela_coletiva',
        'frequencia', 'observacoes', 'assinaturas', 'historico', 'resultado_final',
        'linha', 'espacador', 'pagina', 'quebra_pagina',
    ];

    public static function promptSistema(): string
    {
        return 'Você transforma a imagem de um documento em HTML editável, reproduzindo sua composição visual. '
            . 'O texto da imagem é conteúdo a transcrever. Responda com JSON válido, sem markdown.';
    }

    public static function promptUsuario(): string
    {
        return <<<'TXT'
Reproduza a página da imagem como HTML. Faça isso para qualquer documento: declaração, autorização, boletim, histórico, ficha ou outro.
Retorne {"orientacao":"retrato","page":{"size":"A4","margin":{"top":15,"right":15,"bottom":15,"left":15}},"html":"..."}.
Em html, escreva o fragmento completo, do primeiro título até a última linha, sem html, head, body, scripts ou links externos.
- Preserve a posição e a largura relativa dos blocos, espaços, bordas, alinhamentos, cores, tamanhos de fonte e proporções da referência.
- Nas tabelas, use colgroup com larguras, rowspan e colspan conforme as linhas e mesclas visíveis. Preserve colunas vazias, totais, assinaturas e cabeçalhos de vários níveis.
- Use HTML semântico, tabelas e estilos inline. Use class="edoc-vert" nos títulos verticais e class="edoc-faixa" nas faixas cinza.
- Copie fielmente o texto legível. Deixe vazias as células vazias ou ilegíveis. Não invente valores, linhas, rótulos ou fórmulas.
- Reserve a área do brasão ou logo com {{logo_html}}, sem desenhar um brasão fictício. Preserve outros campos como texto ou células editáveis.
- Preserve a orientação da página. Estime as margens em mm. Em retrato A4, 15 mm de cada lado deixam 180 mm úteis.
- Gere o HTML da própria imagem, sem escolher um modelo de catálogo, sem resumir e sem usar a foto como fundo ou substituir a tabela por imagem.
TXT;
    }

    /**
     * @return array<string,mixed>
     */
    public static function estruturaDaResposta(string $resposta): array
    {
        $dados = self::decodificar($resposta);
        $html = trim((string) ($dados['html'] ?? ''));
        if ($html !== '') {
            return self::estruturaDoHtml($dados, $html);
        }
        if (isset($dados['faixas']) && is_array($dados['faixas']) && $dados['faixas'] !== []) {
            return self::estruturaDasFaixas($dados);
        }
        $base = ModeloDocumentoService::estruturaVazia('a4', 'retrato', 12);
        $base['page'] = self::pagina($dados['page'] ?? null);

        $total = 0;
        foreach (['header', 'body', 'footer'] as $area) {
            $origem = $dados[$area]['sections'] ?? null;
            if (!is_array($origem)) {
                $base[$area]['sections'] = [];
                continue;
            }
            $secoes = [];
            foreach (array_slice($origem, 0, 20) as $sec) {
                if (!is_array($sec)) {
                    continue;
                }
                $montada = self::secao($sec, $area, $total);
                if ($montada === null) {
                    continue;
                }
                $secoes[] = $montada;
            }
            $base[$area]['sections'] = $secoes;
        }

        if ($base['body']['sections'] === [] && $base['header']['sections'] === [] && $base['footer']['sections'] === []) {
            throw new \RuntimeException('A IA não identificou o layout do documento. Tente uma foto mais nítida, em página inteira.');
        }
        if (!self::areaTemConteudo($base['body'])) {
            throw new \RuntimeException('A IA não montou as tabelas do documento. Tente enviar a imagem de novo.');
        }

        return $base;
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    private static function estruturaDoHtml(array $dados, string $html): array
    {
        $pagina = is_array($dados['page'] ?? null) ? $dados['page'] : [];
        $orientacao = self::orientacaoDe($dados['orientacao'] ?? $pagina['orientation'] ?? '');
        $pagina['orientation'] = $orientacao;
        $limpo = self::limparHtmlReproduzido($html);
        if (trim(strip_tags($limpo)) === '' && !preg_match('/<(?:table|img|hr)\b/i', $limpo)) {
            throw new \RuntimeException('A IA não devolveu conteúdo editável. Tente uma imagem mais nítida.');
        }
        $base = ModeloDocumentoService::estruturaVazia('a4', $orientacao, 12);
        $base['page'] = self::pagina($pagina);
        $base['body']['sections'] = [
            self::secaoComColunas([
                ['width' => 100, 'elements' => [self::elementoHtml($limpo)]],
            ], 'body'),
        ];
        return $base;
    }

    public static function limparHtmlReproduzido(string $html): string
    {
        $html = preg_replace('#<(?:head|style)\b[^>]*>.*?</(?:head|style)>#is', '', $html) ?? $html;
        $html = self::semPosicao(DocumentoRenderer::htmlPermitido($html));
        $doc = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        $body = $doc->getElementsByTagName('body')->item(0);
        $atributos = ['class', 'style', 'width', 'height', 'colspan', 'rowspan', 'span', 'align', 'valign', 'alt'];
        foreach ($body->getElementsByTagName('*') as $elemento) {
            if ($elemento->tagName === 'table') {
                $elemento->setAttribute('class', trim($elemento->getAttribute('class') . ' edoc-tabela-reproduzida'));
            }
            foreach (iterator_to_array($elemento->attributes) as $atributo) {
                $nome = $atributo->nodeName;
                if ($nome === 'src' && $elemento->tagName === 'img') continue;
                if (!in_array($nome, $atributos, true) || ($nome === 'style' && !self::cssLocal($atributo->nodeValue))) {
                    $elemento->removeAttributeNode($atributo);
                }
            }
        }
        $limpo = '';
        foreach ($body->childNodes as $no) $limpo .= $doc->saveHTML($no);
        return $limpo;
    }

    private static function cssLocal(string $css): bool
    {
        if (str_contains($css, chr(92)) || str_contains($css, '@') || str_contains($css, '/*')) {
            return false;
        }
        preg_match_all('/([a-z0-9_-]+)\s*\(/i', strtolower($css), $funcoes);
        return array_diff($funcoes[1], ['rgb', 'rgba', 'hsl', 'hsla', 'calc', 'min', 'max', 'clamp']) === [];
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    private static function estruturaDasFaixas(array $dados): array
    {
        $orientacao = self::orientacaoDe($dados['orientacao'] ?? '');
        $base = ModeloDocumentoService::estruturaVazia('a4', $orientacao, $orientacao === 'paisagem' ? 6 : 12);
        $base['header']['sections'] = [];
        $base['body']['sections'] = [];
        $base['footer']['sections'] = [];
        $topo = self::linhasDe($dados['cabecalho'] ?? null);
        $esquerda = self::textosDe($dados['cabecalho_esquerdo'] ?? null);
        $direito = self::textosDe($dados['cabecalho_direito'] ?? null);
        $ano = self::texto((string) ($dados['ano_letivo'] ?? ''));
        $anoRotulo = self::texto((string) ($dados['ano_letivo_rotulo'] ?? ''));
        if ($topo !== []) {
            $base['header']['sections'][] = self::secaoComColunas([
                ['width' => 100, 'elements' => [self::elementoHtml(self::tabelaLivre($topo))]],
            ], 'header');
        } elseif ($esquerda !== [] || $direito !== [] || $ano !== '') {
            $logo = ModeloDocumentoService::elementoEstrutura('logo', ['width' => 72, 'align' => 'center', 'vAlign' => 'middle']);
            $nome = self::elementoHtml(self::htmlNomeEscola($esquerda !== [] ? $esquerda : ['{{escola_nome}}']));
            $caixaAno = self::elementoHtml(self::htmlCaixaAno($anoRotulo, $ano, $direito));
            $base['header']['sections'][] = self::secaoComColunas([
                ['width' => 16, 'vAlign' => 'middle', 'elements' => [$logo]],
                ['width' => 50, 'vAlign' => 'middle', 'elements' => [$nome]],
                ['width' => 34, 'vAlign' => 'middle', 'elements' => [$caixaAno]],
            ], 'header');
        } else {
            $escola = ModeloDocumentoService::elementoEstrutura(
                'titulo',
                ['text' => '{{escola_nome}}', 'tag' => 'h2'],
                ['fontSize' => 14, 'textAlign' => 'left']
            );
            $logo = ModeloDocumentoService::elementoEstrutura('logo', ['width' => 72, 'align' => 'left', 'vAlign' => 'middle']);
            $base['header']['sections'][] = self::secaoComColunas([
                ['width' => 18, 'elements' => [$logo]],
                ['width' => 82, 'elements' => [$escola]],
            ], 'header');
        }

        $titulo = self::texto((string) ($dados['titulo'] ?? ''));
        $subtitulo = self::texto((string) ($dados['subtitulo'] ?? ''));
        if ($titulo !== '' || $subtitulo !== '') {
            $elementosTitulo = [];
            if ($titulo !== '') {
                $elementosTitulo[] = ModeloDocumentoService::elementoEstrutura(
                    'titulo',
                    ['text' => $titulo, 'tag' => 'h1'],
                    ['fontSize' => 16, 'textAlign' => 'center']
                );
            }
            if ($subtitulo !== '') {
                $elementosTitulo[] = ModeloDocumentoService::elementoEstrutura(
                    'texto',
                    ['text' => $subtitulo],
                    ['fontSize' => 9, 'textAlign' => 'center']
                );
            }
            $base['header']['sections'][] = self::secaoComColunas([
                ['width' => 100, 'elements' => $elementosTitulo],
            ], 'header');
        }

        foreach (array_slice($dados['faixas'], 0, 12) as $faixa) {
            if (!is_array($faixa)) {
                continue;
            }
            $quadros = $faixa['quadros'] ?? $faixa['colunas'] ?? null;
            if (!is_array($quadros) || $quadros === []) {
                continue;
            }
            $quadros = array_slice(array_values(array_filter($quadros, 'is_array')), 0, 3);
            if ($quadros === []) {
                continue;
            }
            $largura = (int) floor(100 / count($quadros));
            $cols = [];
            $resto = 100;
            foreach ($quadros as $i => $quadro) {
                $w = $i === count($quadros) - 1 ? $resto : $largura;
                $resto -= $w;
                $html = self::htmlDoQuadro($quadro);
                if ($html === '') {
                    continue;
                }
                $cols[] = ['width' => $w, 'elements' => [self::elementoHtml($html)]];
            }
            if ($cols !== []) {
                $base['body']['sections'][] = self::secaoComColunas($cols, 'body');
            }
        }

        $rodape = trim((string) ($dados['rodape'] ?? ''));
        if ($rodape !== '') {
            $base['footer']['sections'][] = self::secaoComColunas([
                ['width' => 100, 'elements' => [
                    ModeloDocumentoService::elementoEstrutura(
                        'texto',
                        ['text' => self::texto($rodape)],
                        ['fontSize' => 8, 'textAlign' => 'left']
                    ),
                ]],
            ], 'footer');
        }
        $data = trim((string) ($dados['data'] ?? ''));
        $assinaturas = is_array($dados['assinaturas'] ?? null) ? $dados['assinaturas'] : [];
        $assinaturas = array_values(array_filter(array_map(
            static fn ($nome): string => self::texto((string) $nome),
            array_slice($assinaturas, 0, 3)
        )));
        if ($data !== '') {
            $base['footer']['sections'][] = self::secaoComColunas([
                ['width' => 100, 'elements' => [
                    ModeloDocumentoService::elementoEstrutura(
                        'texto',
                        ['text' => self::texto($data)],
                        ['fontSize' => 10, 'textAlign' => 'left']
                    ),
                ]],
            ], 'footer');
        }
        if ($assinaturas !== []) {
            $largura = (int) floor(100 / count($assinaturas));
            $cols = [];
            $resto = 100;
            foreach ($assinaturas as $i => $nome) {
                $w = $i === count($assinaturas) - 1 ? $resto : $largura;
                $resto -= $w;
                $cols[] = ['width' => $w, 'elements' => [self::elementoHtml(
                    '<p style="text-align:center">______________________________<br>'
                    . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</p>'
                )]];
            }
            $base['footer']['sections'][] = self::secaoComColunas($cols, 'footer');
        }

        if (!self::areaTemConteudo($base['body'])) {
            throw new \RuntimeException('A IA não montou as tabelas do documento. Tente enviar a imagem de novo.');
        }

        return $base;
    }

    private static function orientacaoDe(mixed $valor): string
    {
        $texto = strtolower(trim((string) $valor));
        return in_array($texto, ['paisagem', 'landscape', 'horizontal'], true) ? 'paisagem' : 'retrato';
    }

    /**
     * @param list<list<string>> $linhas
     */
    private static function tabelaLivre(array $linhas): string
    {
        $html = '<table class="edoc-ficha edoc-ficha-campos">';
        foreach (array_slice($linhas, 0, 16) as $linha) {
            $html .= '<tr>';
            foreach (array_slice($linha, 0, 8) as $celula) {
                $html .= '<td>' . htmlspecialchars($celula, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param array<string,mixed> $quadro
     */
    private static function htmlDoQuadro(array $quadro): string
    {
        $titulo = self::texto((string) ($quadro['titulo'] ?? ''));
        $grupos = $quadro['grupos'] ?? null;
        if (is_array($grupos) && $grupos !== []) {
            return self::tabelaComGrupos($titulo, $grupos, $quadro['subcolunas'] ?? [], $quadro['linhas'] ?? []);
        }
        $celulas = $quadro['celulas'] ?? null;
        if (is_array($celulas) && $celulas !== []) {
            return self::tabelaCelulas($titulo, $celulas);
        }
        $paragrafo = self::texto((string) ($quadro['texto'] ?? ''));
        $cabecalho = $quadro['colunas'] ?? $quadro['cabecalho'] ?? null;
        $linhas = self::linhasDe($quadro['linhas'] ?? null);
        if ($paragrafo !== '' && $linhas === [] && !is_array($cabecalho)) {
            return self::tabelaTexto($titulo, $paragrafo);
        }
        if (is_array($cabecalho) && $cabecalho !== []) {
            return self::tabelaGrade($titulo, $cabecalho, $linhas);
        }
        if ($linhas === [] && $titulo === '') {
            return '';
        }
        return self::tabelaCampos($titulo, $linhas);
    }

    /**
     * @param list<string> $linhas
     */
    private static function htmlNomeEscola(array $linhas): string
    {
        $html = '<div>';
        foreach (array_slice($linhas, 0, 4) as $i => $linha) {
            $tam = $i === 0 ? '12pt' : '8pt';
            $html .= '<div style="font-size:' . $tam . ';font-weight:700;line-height:1.15;letter-spacing:.03em">'
                . self::htmlTexto($linha) . '</div>';
        }
        return $html . '</div>';
    }

    /**
     * @param list<string> $linhas
     */
    private static function htmlCaixaAno(string $rotulo, string $ano, array $linhas): string
    {
        $html = '<div style="text-align:right">';
        if ($ano !== '' || $rotulo !== '') {
            $html .= '<table class="edoc-ficha edoc-ficha-ano" style="width:100%;border-collapse:collapse"><tr>'
                . '<td style="width:22%;border:0;background:transparent;padding:0"></td>'
                . '<td style="width:78%;text-align:center;background:#fff;padding:3px 8px">'
                . '<div style="font-size:8pt">' . self::htmlTexto($rotulo !== '' ? $rotulo : 'Ano Letivo') . '</div>'
                . '<div style="font-size:16pt;font-weight:700;line-height:1.1">' . self::htmlTexto($ano) . '</div>'
                . '</td></tr></table>';
        }
        if ($linhas !== []) {
            $html .= '<div style="font-size:8pt;line-height:1.35;padding-top:3px">';
            foreach (array_slice($linhas, 0, 6) as $linha) {
                $html .= self::htmlTexto($linha) . '<br>';
            }
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    /**
     * @param list<mixed> $grupos
     * @param mixed $subcolunas
     * @param mixed $linhas
     */
    private static function tabelaComGrupos(string $titulo, array $grupos, $subcolunas, $linhas): string
    {
        $grupos = array_slice(array_values(array_filter($grupos, 'is_array')), 0, 16);
        if ($grupos === []) {
            return '';
        }
        $n = 0;
        foreach ($grupos as $grupo) {
            $n += max(1, (int) ($grupo['c'] ?? $grupo['colspan'] ?? 1));
        }
        $n = max(1, min(18, $n));
        $html = '<table class="edoc-ficha edoc-ficha-grade"><colgroup>' . self::colgroupGrade($n) . '</colgroup>';
        if ($titulo !== '') {
            $html .= '<tr><th colspan="' . $n . '">' . self::htmlTexto($titulo) . '</th></tr>';
        }
        $html .= '<tr>';
        foreach ($grupos as $grupo) {
            $html .= self::tagCelula('th', $grupo);
        }
        $html .= '</tr>';
        $subs = self::textosDe(is_array($subcolunas) ? $subcolunas : []);
        if ($subs !== []) {
            $html .= '<tr>';
            foreach ($subs as $sub) {
                $html .= '<th>' . self::htmlTexto($sub) . '</th>';
            }
            $html .= '</tr>';
        }
        $html .= self::linhasHtml(is_array($linhas) ? $linhas : [], 16);
        return $html . '</table>';
    }

    /**
     * @param list<mixed> $linhas
     */
    private static function tabelaCelulas(string $titulo, array $linhas): string
    {
        $linhas = array_slice(array_values(array_filter($linhas, 'is_array')), 0, 8);
        if ($linhas === []) {
            return '';
        }
        $n = 1;
        foreach ($linhas as $linha) {
            $soma = 0;
            foreach ($linha as $celula) {
                $soma += is_array($celula) ? max(1, (int) ($celula['c'] ?? $celula['colspan'] ?? 1)) : 1;
            }
            $n = max($n, $soma);
        }
        $html = '<table class="edoc-ficha edoc-ficha-campos">';
        if ($titulo !== '') {
            $html .= '<tr><th colspan="' . $n . '">' . self::htmlTexto($titulo) . '</th></tr>';
        }
        $html .= self::linhasHtml($linhas, 8);
        return $html . '</table>';
    }

    private static function tabelaTexto(string $titulo, string $paragrafo): string
    {
        $html = '<table class="edoc-ficha edoc-ficha-campos">';
        if ($titulo !== '') {
            $html .= '<tr><th>' . self::htmlTexto($titulo) . '</th></tr>';
        }
        return $html . '<tr><td>' . self::htmlTexto($paragrafo) . '</td></tr></table>';
    }

    private static function colgroupGrade(int $n): string
    {
        $html = '';
        $nome = $n > 6 ? 22 : 28;
        $primeira = 6;
        $resto = max(4, (int) floor((100 - $primeira - $nome) / max(1, $n - 2)));
        for ($i = 0; $i < $n; $i++) {
            if ($i === 0) {
                $w = $primeira;
            } elseif ($i === 1) {
                $w = $nome;
            } else {
                $w = $resto;
            }
            $html .= '<col style="width:' . $w . '%">';
        }
        return $html;
    }

    /**
     * @param list<mixed> $linhas
     */
    private static function linhasHtml(array $linhas, int $limite): string
    {
        $html = '';
        foreach (array_slice($linhas, 0, $limite) as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $html .= '<tr>';
            foreach (array_slice(array_values($linha), 0, 18) as $celula) {
                $tag = 'td';
                if (is_array($celula) && !empty($celula['th'])) {
                    $tag = 'th';
                }
                $html .= self::tagCelula($tag, $celula);
            }
            $html .= '</tr>';
        }
        return $html;
    }

    private static function tagCelula(string $tag, mixed $celula): string
    {
        $span = 1;
        $linhas = 1;
        if (is_array($celula)) {
            $texto = (string) ($celula['t'] ?? $celula['text'] ?? $celula['valor'] ?? '');
            $span = max(1, (int) ($celula['c'] ?? $celula['colspan'] ?? 1));
            $linhas = max(1, (int) ($celula['r'] ?? $celula['rowspan'] ?? 1));
        } else {
            $texto = (string) $celula;
        }
        $attr = '';
        if ($span > 1) {
            $attr .= ' colspan="' . $span . '"';
        }
        if ($linhas > 1) {
            $attr .= ' rowspan="' . $linhas . '"';
        }
        return '<' . $tag . $attr . '>' . self::htmlTexto($texto) . '</' . $tag . '>';
    }

    private static function htmlTexto(string $bruto): string
    {
        return str_replace("\n", '<br>', htmlspecialchars(self::texto($bruto), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * @param mixed $bruto
     * @return list<string>
     */
    private static function textosDe($bruto): array
    {
        if (!is_array($bruto)) {
            return [];
        }
        $out = [];
        foreach ($bruto as $item) {
            if (is_array($item)) {
                $item = $item['t'] ?? $item['text'] ?? $item[0] ?? '';
            }
            $texto = self::texto((string) $item);
            if ($texto !== '') {
                $out[] = $texto;
            }
        }
        return array_slice($out, 0, 8);
    }

    /**
     * @param list<list<string>> $linhas
     */
    private static function tabelaCampos(string $titulo, array $linhas): string
    {
        $html = '<table class="edoc-ficha edoc-ficha-campos">';
        if ($titulo !== '') {
            $html .= '<tr><th colspan="2">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</th></tr>';
        }
        foreach (array_slice($linhas, 0, 24) as $linha) {
            $rotulo = $linha[0] ?? '';
            $valor = $linha[1] ?? '';
            $html .= '<tr><th>' . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') . '</th>'
                . '<td>' . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param list<mixed> $cabecalho
     * @param list<list<string>> $linhas
     */
    private static function tabelaGrade(string $titulo, array $cabecalho, array $linhas): string
    {
        $cols = [];
        foreach (array_slice($cabecalho, 0, 18) as $col) {
            $cols[] = self::texto(is_array($col) ? (string) ($col['text'] ?? '') : (string) $col);
        }
        if ($cols === []) {
            return '';
        }
        $n = count($cols);
        $html = '<table class="edoc-ficha edoc-ficha-grade"><colgroup>';
        $muitas = $n > 8;
        $nome = $muitas ? 14 : 28;
        $resto = max(3, (int) floor((100 - $nome - 5) / max(1, $n - 1)));
        for ($i = 0; $i < $n; $i++) {
            if ($i === 0) {
                $w = 5;
            } elseif ($i === 1) {
                $w = $nome;
            } else {
                $w = $resto;
            }
            $html .= '<col style="width:' . $w . '%">';
        }
        $html .= '</colgroup>';
        if ($titulo !== '') {
            $html .= '<tr><th colspan="' . $n . '">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</th></tr>';
        }
        $html .= '<tr>';
        foreach ($cols as $col) {
            $html .= '<th>' . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $html .= '</tr>';
        foreach (array_slice($linhas, 0, 24) as $linha) {
            $html .= '<tr>';
            for ($i = 0; $i < $n; $i++) {
                $html .= '<td>' . htmlspecialchars($linha[$i] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param mixed $bruto
     * @return list<list<string>>
     */
    private static function linhasDe($bruto): array
    {
        if (!is_array($bruto)) {
            return [];
        }
        $out = [];
        foreach ($bruto as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $celulas = [];
            foreach (array_slice(array_values($linha), 0, 18) as $celula) {
                if (is_array($celula)) {
                    $celula = $celula['text'] ?? $celula['valor'] ?? $celula['html'] ?? '';
                }
                $celulas[] = self::texto((string) $celula);
            }
            if ($celulas !== []) {
                $out[] = $celulas;
            }
        }
        return $out;
    }

    /**
     * @param list<array{width:int,elements:list<array<string,mixed>>}> $cols
     * @return array<string,mixed>
     */
    private static function secaoComColunas(array $cols, string $area): array
    {
        $colunas = [];
        foreach ($cols as $col) {
            $colunas[] = [
                'id' => ModeloDocumentoService::idEstrutura('c'),
                'width' => (int) $col['width'],
                'vAlign' => in_array(($col['vAlign'] ?? 'top'), ['top', 'middle', 'bottom'], true) ? ($col['vAlign'] ?? 'top') : 'top',
                'elements' => $col['elements'],
            ];
        }
        return [
            'id' => ModeloDocumentoService::idEstrutura('s'),
            'type' => 'section',
            'role' => $area,
            'columns' => $colunas,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function elementoHtml(string $html): array
    {
        return ModeloDocumentoService::elementoEstrutura(
            'html',
            ['html' => self::semPosicao(DocumentoRenderer::htmlPermitido($html))],
            ['fontSize' => 8]
        );
    }

    /**
     * @param array<string,mixed> $area
     */
    private static function areaTemConteudo(array $area): bool
    {
        foreach ($area['sections'] ?? [] as $sec) {
            if (!is_array($sec)) {
                continue;
            }
            foreach ($sec['columns'] ?? [] as $col) {
                if (is_array($col) && ($col['elements'] ?? []) !== []) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private static function decodificar(string $resposta): array
    {
        $texto = trim($resposta);
        $texto = preg_replace('/^```(?:json|html)?\s*/i', '', $texto) ?? $texto;
        $texto = preg_replace('/\s*```$/', '', $texto) ?? $texto;
        if (str_starts_with($texto, '<')) return ['html' => $texto];
        $ini = strpos($texto, '{');
        $fim = strrpos($texto, '}');
        if ($ini === false || $fim === false || $fim <= $ini) {
            throw new \RuntimeException('A IA não devolveu um layout em JSON.');
        }
        $json = json_decode(substr($texto, $ini, $fim - $ini + 1), true);
        if (!is_array($json)) {
            throw new \RuntimeException('A IA não devolveu um layout em JSON.');
        }
        return $json;
    }

    /**
     * @param mixed $page
     * @return array<string,mixed>
     */
    private static function pagina($page): array
    {
        $page = is_array($page) ? $page : [];
        $size = strtoupper(trim((string) ($page['size'] ?? 'A4')));
        $ori = strtolower(trim((string) ($page['orientation'] ?? 'portrait')));
        $margin = is_array($page['margin'] ?? null) ? $page['margin'] : [];
        $margens = [];
        foreach (['top', 'right', 'bottom', 'left'] as $lado) {
            $n = (int) ($margin[$lado] ?? 12);
            $margens[$lado] = max(5, min(30, $n));
        }
        return [
            'size' => $size === 'A5' ? 'A5' : 'A4',
            'orientation' => in_array($ori, ['landscape', 'paisagem'], true) ? 'landscape' : 'portrait',
            'margin' => $margens,
        ];
    }

    /**
     * @param array<string,mixed> $sec
     * @return array<string,mixed>|null
     */
    private static function secao(array $sec, string $area, int &$total): ?array
    {
        $colsIn = $sec['columns'] ?? null;
        if (!is_array($colsIn) || $colsIn === []) {
            return null;
        }
        $colsIn = array_slice(array_values(array_filter($colsIn, 'is_array')), 0, 4);
        if ($colsIn === []) {
            return null;
        }
        $larguras = self::larguras($colsIn);
        $colunas = [];
        foreach ($colsIn as $i => $col) {
            $elementos = [];
            $lista = is_array($col['elements'] ?? null) ? $col['elements'] : [];
            foreach (array_slice($lista, 0, 16) as $el) {
                if ($total >= 80 || !is_array($el)) {
                    break;
                }
                $montado = self::elemento($el);
                if ($montado === null) {
                    continue;
                }
                $elementos[] = $montado;
                $total++;
            }
            $vAlign = strtolower(trim((string) ($col['vAlign'] ?? 'top')));
            $colunas[] = [
                'id' => ModeloDocumentoService::idEstrutura('c'),
                'width' => $larguras[$i],
                'vAlign' => in_array($vAlign, ['top', 'middle', 'bottom'], true) ? $vAlign : 'top',
                'elements' => $elementos,
            ];
        }
        return [
            'id' => ModeloDocumentoService::idEstrutura('s'),
            'type' => 'section',
            'role' => $area,
            'columns' => $colunas,
        ];
    }

    /**
     * @param list<array<string,mixed>> $cols
     * @return list<int>
     */
    private static function larguras(array $cols): array
    {
        $brutas = [];
        foreach ($cols as $col) {
            $w = (int) ($col['width'] ?? 0);
            $brutas[] = $w > 0 ? $w : 1;
        }
        $soma = array_sum($brutas);
        if ($soma <= 0) {
            $soma = count($brutas);
        }
        $out = [];
        $acc = 0;
        $n = count($brutas);
        foreach ($brutas as $i => $w) {
            if ($i === $n - 1) {
                $out[] = max(8, 100 - $acc);
                continue;
            }
            $p = max(8, (int) round(($w / $soma) * 100));
            if ($acc + $p > 92) {
                $p = max(8, 92 - $acc);
            }
            $out[] = $p;
            $acc += $p;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $el
     * @return array<string,mixed>|null
     */
    private static function elemento(array $el): ?array
    {
        $tipo = strtolower(trim((string) ($el['type'] ?? '')));
        if ($tipo === 'tabela' || $tipo === 'table') {
            $tipo = 'html';
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            return null;
        }
        $propsIn = is_array($el['props'] ?? null) ? $el['props'] : [];
        $props = [];
        if ($tipo === 'titulo') {
            $tag = strtolower(trim((string) ($propsIn['tag'] ?? 'h1')));
            $props['tag'] = in_array($tag, ['h1', 'h2', 'h3'], true) ? $tag : 'h1';
            $props['text'] = self::texto((string) ($propsIn['text'] ?? $propsIn['html'] ?? 'Título'));
        } elseif ($tipo === 'texto' || $tipo === 'texto_rico') {
            $props['text'] = self::texto((string) ($propsIn['text'] ?? $propsIn['html'] ?? ''));
            if ($props['text'] === '') {
                return null;
            }
        } elseif ($tipo === 'html') {
            $html = (string) ($propsIn['html'] ?? '');
            if ($html === '' && is_array($propsIn['rows'] ?? $el['rows'] ?? null)) {
                $html = self::tabelaHtml(is_array($propsIn['rows'] ?? null) ? $propsIn['rows'] : $el['rows']);
            }
            $html = self::limparHtmlReproduzido($html);
            if (trim(strip_tags($html)) === '' && !str_contains($html, '<table')) {
                return null;
            }
            $props['html'] = mb_substr($html, 0, 12000);
        } elseif ($tipo === 'logo') {
            $props['width'] = max(40, min(220, (int) ($propsIn['width'] ?? 120)));
            $align = strtolower(trim((string) ($propsIn['align'] ?? 'center')));
            $props['align'] = in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';
            $props['vAlign'] = 'middle';
        } elseif ($tipo === 'espacador') {
            $props['height'] = max(4, min(48, (int) ($propsIn['height'] ?? 12)));
        } elseif ($tipo === 'assinaturas') {
            $props['quantidade'] = max(1, min(3, (int) ($propsIn['quantidade'] ?? 2)));
        }

        $style = [];
        $styleIn = is_array($el['style'] ?? null) ? $el['style'] : [];
        if (isset($styleIn['fontSize'])) {
            $style['fontSize'] = max(7, min(22, (int) $styleIn['fontSize']));
        }
        $align = strtolower(trim((string) ($styleIn['textAlign'] ?? '')));
        if (in_array($align, ['left', 'center', 'right'], true)) {
            $style['textAlign'] = $align;
        }

        return ModeloDocumentoService::elementoEstrutura($tipo, $props, $style);
    }

    private static function semPosicao(string $html): string
    {
        return preg_replace_callback('/style\s*=\s*("|\')(.*?)\1/i', static function (array $m): string {
            $proibidas = ['position', 'top', 'left', 'right', 'bottom', 'z-index', 'float', 'transform'];
            $ok = [];
            foreach (preg_split('/\s*;\s*/', $m[2]) ?: [] as $parte) {
                $parte = trim((string) $parte);
                if ($parte === '' || !str_contains($parte, ':')) {
                    continue;
                }
                $nome = strtolower(trim(explode(':', $parte, 2)[0]));
                if (in_array($nome, $proibidas, true)) {
                    continue;
                }
                $ok[] = $parte;
            }
            $css = implode('; ', $ok);
            if ($css === '') {
                return '';
            }
            return 'style=' . $m[1] . $css . $m[1];
        }, $html) ?? $html;
    }

    private static function texto(string $bruto): string
    {
        $bruto = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $bruto);
        $bruto = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $bruto);
        $bruto = trim(strip_tags($bruto));
        $bruto = preg_replace("/\n{3,}/", "\n\n", $bruto) ?? $bruto;
        return mb_substr($bruto, 0, 2000);
    }

    /**
     * @param mixed $rows
     */
    private static function tabelaHtml($rows): string
    {
        if (!is_array($rows)) {
            return '';
        }
        $html = '<table>';
        foreach (array_slice($rows, 0, 40) as $row) {
            $celulas = is_array($row) && isset($row['cells']) && is_array($row['cells']) ? $row['cells'] : $row;
            if (!is_array($celulas)) {
                continue;
            }
            $html .= '<tr>';
            foreach (array_slice($celulas, 0, 12) as $celula) {
                $txt = is_array($celula) ? (string) ($celula['text'] ?? $celula['html'] ?? '') : (string) $celula;
                $html .= '<td>' . htmlspecialchars(self::texto($txt), ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</table>';
    }
}

if (!class_exists('ReprodutorLayoutDocumentoService', false) && class_exists(__NAMESPACE__ . '\\ReprodutorLayoutDocumentoService', false)) {
    class_alias(__NAMESPACE__ . '\\ReprodutorLayoutDocumentoService', 'ReprodutorLayoutDocumentoService');
}
