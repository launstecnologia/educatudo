<?php

namespace App\Modulos\ModelosDocumentos\Services;

require_once __DIR__ . '/DocumentoRenderer.php';
require_once __DIR__ . '/ModeloDocumentoService.php';

/**
 * Converte a resposta da IA (foto de um documento) na estrutura do editor.
 */
class ReprodutorLayoutDocumentoService
{
    /** @var list<string> */
    private const TIPOS = [
        'titulo', 'texto', 'texto_rico', 'html', 'logo',
        'dados_escola', 'dados_aluno', 'dados_responsavel', 'dados_turma',
        'tabela_notas', 'tabela_aluno', 'tabela_frequencia', 'tabela_coletiva',
        'frequencia', 'observacoes', 'assinaturas', 'historico', 'resultado_final',
        'linha', 'espacador', 'pagina', 'quebra_pagina',
    ];

    public static function promptSistema(): string
    {
        return 'Você transcreve o layout visível de um documento escolar a partir da imagem. '
            . 'Não invente outro formulário. Responda somente com JSON válido, sem markdown.';
    }

    public static function promptUsuario(): string
    {
        return <<<'TXT'
Reproduza o layout visível. Não resuma e não junte numa tabela só os blocos que estão lado a lado.

Se a página for um RELATÓRIO FINAL coletivo (lista numerada de alunos e muitas colunas vazias de disciplina), devolva só:
{"orientacao":"paisagem","html":"<table><tr><td>RELATÓRIO FINAL</td></tr></table>"}

Em qualquer outro documento, devolva só este JSON, sem markdown e sem HTML:
{
  "orientacao":"retrato",
  "cabecalho_esquerdo":["NOME DA ESCOLA","slogan"],
  "ano_letivo_rotulo":"Ano Letivo",
  "ano_letivo":"2026",
  "cabecalho_direito":["Código da Escola: 123","INEP: 000"],
  "titulo":"TÍTULO CENTRAL",
  "subtitulo":"subtítulo",
  "faixas":[
    {"quadros":[
      {"titulo":"1. BLOCO","linhas":[["Rótulo:","valor"]]},
      {"titulo":"2. BLOCO","linhas":[["Rótulo:","valor"]]}
    ]},
    {"quadros":[{
      "titulo":"3. TABELA",
      "grupos":[{"t":"Nº","c":1,"r":2},{"t":"Nome","c":1,"r":2},{"t":"Notas","c":4,"r":1},{"t":"Média","c":1,"r":2}],
      "subcolunas":["1º","2º","3º","4º"],
      "linhas":[["1","Português","8","7","8","9","8,2"],[{"t":"MÉDIA","c":2,"th":true},"8","8","8","8","8"]]
    }]},
    {"quadros":[
      {"titulo":"4. CAIXA","celulas":[[{"t":"Resultado:","r":2,"th":true},{"t":"APROVADO","r":2},{"t":"Média:","th":true},"8,4"],[{"t":"Frequência:","th":true},"96%"]]},
      {"titulo":"5. TEXTO","texto":"parágrafo visível"}
    ]}
  ],
  "data":"Cidade, 15 de dezembro de 2026.",
  "assinaturas":["Direção","Professor(a) Responsável"]
}

Regras:
- orientacao "retrato" quando a folha é vertical. "paisagem" só se a página for horizontal.
- Copie o texto visível, inclusive valores de exemplo. Não invente linha que não está na imagem.
- O que está lado a lado entra no mesmo item de faixas, um quadro por bloco. O que está embaixo entra em outra faixa.
- linhas é par rótulo e valor, um campo por linha, como na foto.
- grupos descreve o cabeçalho da grade: c é colspan, r é rowspan. subcolunas lista só os títulos da segunda linha (os que ficam debaixo de um grupo com r 1).
- Uma linha de totais pode mesclar: {"t":"MÉDIA GERAL","c":2,"th":true}.
- celulas é uma caixa com grade irregular. texto é o parágrafo de uma caixa.
- data e assinaturas só se aparecerem na imagem.
- O brasão fica ao lado de cabecalho_esquerdo; não desenhe o logo no texto.
TXT;
    }

    /**
     * @return array<string,mixed>
     */
    public static function estruturaDaResposta(string $resposta): array
    {
        $dados = self::decodificar($resposta);
        if (isset($dados['faixas']) && is_array($dados['faixas']) && $dados['faixas'] !== []) {
            return self::estruturaDasFaixas($dados);
        }
        $html = trim((string) ($dados['html'] ?? ''));
        if ($html !== '' && str_contains($html, '<table')) {
            return self::estruturaDoHtml($dados, $html);
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
        $orientacao = self::orientacaoDe($dados['orientacao'] ?? '');
        $limpo = self::semPosicao(DocumentoRenderer::htmlPermitido($html));
        if (self::ehRelatorioColetivo($limpo)) {
            $limpo = self::htmlRelatorioColetivo();
            $orientacao = 'paisagem';
        } elseif ($orientacao === 'retrato' && preg_match_all('/<t[dh]\b/i', $html) > 40) {
            $orientacao = 'paisagem';
        }
        $base = ModeloDocumentoService::estruturaVazia('a4', $orientacao, 6);
        if (!str_contains($limpo, '<table')) {
            throw new \RuntimeException('A IA não montou a tabela do documento. Tente enviar a imagem de novo.');
        }
        $base['body']['sections'] = [
            self::secaoComColunas([
                ['width' => 100, 'elements' => [self::elementoHtml($limpo)]],
            ], 'body'),
        ];

        return $base;
    }

    private static function ehRelatorioColetivo(string $html): bool
    {
        $texto = mb_strtolower(strip_tags($html));
        return str_contains($texto, 'relatório final') || str_contains($texto, 'relatorio final');
    }

    /**
     * Grade do relatório coletivo SEED: colunas vazias de disciplina não aparecem na leitura da foto.
     */
    private static function htmlRelatorioColetivo(): string
    {
        $bnc = 10;
        $div = 5;
        $notas = $bnc + $div;
        $total = 3 + $notas + 1;
        $html = '<table class="edoc-ficha edoc-ficha-grade edoc-relatorio"><colgroup>'
            . '<col style="width:4%"><col style="width:16%"><col style="width:4%">';
        $larguraNota = 68 / $notas;
        for ($i = 0; $i < $notas; $i++) {
            $html .= '<col style="width:' . round($larguraNota, 2) . '%">';
        }
        $html .= '<col style="width:8%"></colgroup>';

        $cel = static function (string $texto, int $span = 1, int $linhas = 1, bool $titulo = false): string {
            $tag = $titulo ? 'th' : 'td';
            $attr = '';
            if ($span > 1) {
                $attr .= ' colspan="' . $span . '"';
            }
            if ($linhas > 1) {
                $attr .= ' rowspan="' . $linhas . '"';
            }
            return '<' . $tag . $attr . '>' . $texto . '</' . $tag . '>';
        };

        $html .= '<tr>'
            . $cel('ESTADO DO PARANÁ<br>SECRETARIA DE ESTADO DA EDUCAÇÃO E DO ESPORTE', 6, 1, true)
            . $cel('RELATÓRIO FINAL - LEI Nº 9394/96, DOU de 23/12/96<br>ENSINO MÉDIO', 10, 1, true)
            . $cel('FL. &nbsp;/', 3, 1, true)
            . '</tr>';
        $html .= '<tr>'
            . $cel('Estabelecimento: {{escola_nome}}', 7)
            . $cel('Município: {{escola_municipio}}', 6)
            . $cel('NRE: {{escola_nre}}', 6)
            . '</tr>';
        $html .= '<tr>' . $cel('Entidade Mantenedora: {{entidade_mantenedora}}', $total) . '</tr>';
        $html .= '<tr>'
            . $cel('Ato Oficial do Estabelecimento: Ato (nº/ano, DOE data)', 10)
            . $cel('Ato Oficial do Curso: Ato (nº/ano, DOE data)', 9)
            . '</tr>';
        $html .= '<tr>'
            . $cel('Organização: {{organizacao}}', 7)
            . $cel('Turma: {{turma_nome}}', 6)
            . $cel('Turno: {{turno}}', 6)
            . '</tr>';
        $html .= '<tr>'
            . $cel('Total de Horas: {{carga_horaria_total}}', 7)
            . $cel('Dias Letivos: {{dias_letivos}}', 6)
            . $cel('Ano/Época: {{ano_letivo}}', 6)
            . '</tr>';

        $alunos = 22;
        $html .= '<tr>'
            . $cel('DISCIPLINAS', 3, 2, true)
            . $cel('Base Nacional Comum', $bnc, 1, true)
            . $cel('Parte Diversificada', $div, 1, true)
            . $cel('RESULTADO', 1, 2 + 1 + $alunos, true)
            . '</tr><tr>';
        for ($i = 0; $i < $notas; $i++) {
            $html .= '<td>&nbsp;</td>';
        }
        $html .= '</tr><tr>'
            . $cel('Nº', 1, 1, true)
            . $cel('ALUNO(A)', 1, 1, true)
            . $cel('Gên.', 1, 1, true)
            . $cel('NOTAS OU MENÇÕES', $notas, 1, true)
            . '</tr>';
        for ($n = 1; $n <= $alunos; $n++) {
            $html .= '<tr><td>' . $n . '</td><td></td><td></td>';
            for ($i = 0; $i < $notas; $i++) {
                $html .= '<td></td>';
            }
            $html .= '</tr>';
        }
        $html .= '<tr>' . $cel('O presente documento não contém emendas nem rasuras.', $total) . '</tr>';

        return $html . '</table>';
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
                ['width' => 16, 'elements' => [$logo]],
                ['width' => 50, 'elements' => [$nome]],
                ['width' => 34, 'elements' => [$caixaAno]],
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
                'vAlign' => 'top',
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
        $texto = preg_replace('/^```(?:json)?\s*/i', '', $texto) ?? $texto;
        $texto = preg_replace('/\s*```$/', '', $texto) ?? $texto;
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
            $html = self::semPosicao(DocumentoRenderer::htmlPermitido($html));
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
