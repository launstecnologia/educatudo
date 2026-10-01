<?php

namespace App\Modulos\ModelosDocumentos\Services;

/**
 * Converte a folha do Excel (células + mesclas) em HTML da ficha,
 * encaixando placeholders nos espaços vazios.
 */
class ConversorFolhaExcelHtml
{
    /**
     * @param array{celulas?:array<int,array<int,string>>,mesclas?:list<array{0:int,1:int,2:int,3:int}>,maxLinha?:int,maxColuna?:int,larguras?:array<int,float>} $grade
     * @return array{frente:string,verso:string}
     */
    public static function converterPartes(array $grade, string $chave): array
    {
        $celulas = $grade['celulas'] ?? [];
        $mesclas = $grade['mesclas'] ?? [];
        $maxLinha = (int) ($grade['maxLinha'] ?? 0);
        $maxColuna = self::cortarColunasVazias(
            $celulas,
            $mesclas,
            $maxLinha,
            (int) ($grade['maxColuna'] ?? 0)
        );
        if ($celulas === [] || $maxLinha < 1 || $maxColuna < 1) {
            return ['frente' => '', 'verso' => ''];
        }

        $tokens = self::montarTokens($celulas, $mesclas, $chave, $maxLinha, $maxColuna);
        $origem = [];
        $coberto = [];
        foreach ($mesclas as $m) {
            [$l1, $c1, $l2, $c2] = $m;
            $origem[$l1 . ',' . $c1] = [$l2 - $l1 + 1, $c2 - $c1 + 1];
            for ($l = $l1; $l <= $l2; $l++) {
                for ($c = $c1; $c <= $c2; $c++) {
                    if ($l === $l1 && $c === $c1) {
                        continue;
                    }
                    $coberto[$l . ',' . $c] = true;
                }
            }
        }

        $colunas = self::colgroup($grade['larguras'] ?? [], $maxColuna);
        $quebra = self::linhaQuebraVerso($celulas, $maxLinha, $maxColuna);
        $frenteAte = ($quebra > 1) ? $quebra - 1 : $maxLinha;
        $alturas = is_array($grade['alturas'] ?? null) ? $grade['alturas'] : [];
        $frente = self::montarTabela($celulas, $tokens, $origem, $coberto, $colunas, 1, $frenteAte, $maxColuna, $alturas);
        $verso = ($quebra > 1)
            ? self::montarTabela($celulas, $tokens, $origem, $coberto, $colunas, $quebra, $maxLinha, $maxColuna, $alturas)
            : '';

        return ['frente' => $frente, 'verso' => $verso];
    }

    /**
     * @param array{celulas?:array<int,array<int,string>>,mesclas?:list<array{0:int,1:int,2:int,3:int}>,maxLinha?:int,maxColuna?:int,larguras?:array<int,float>} $grade
     */
    public static function converter(array $grade, string $chave): string
    {
        $partes = self::converterPartes($grade, $chave);
        if ($partes['frente'] === '') {
            return '';
        }
        if ($partes['verso'] === '') {
            return $partes['frente'];
        }

        return $partes['frente'] . '<div class="page-break"></div>' . $partes['verso'];
    }

    /**
     * @param array<int,array<int,string>> $celulas
     * @param list<array{0:int,1:int,2:int,3:int}> $mesclas
     */
    private static function cortarColunasVazias(array $celulas, array $mesclas, int $maxLinha, int $maxColuna): int
    {
        while ($maxColuna > 1) {
            $usada = false;
            for ($l = 1; $l <= $maxLinha; $l++) {
                if (trim((string) ($celulas[$l][$maxColuna] ?? '')) !== '') {
                    $usada = true;
                    break;
                }
            }
            if (!$usada) {
                foreach ($mesclas as $m) {
                    if ((int) $m[1] === $maxColuna || (int) $m[3] === $maxColuna) {
                        $usada = true;
                        break;
                    }
                }
            }
            if ($usada) {
                break;
            }
            $maxColuna--;
        }

        return $maxColuna;
    }

    /**
     * @param array<int,array<int,string>> $celulas
     * @param array<int,array<int,string>> $tokens
     * @param array<string,array{0:int,1:int}> $origem
     * @param array<string,true> $coberto
     * @param array{html:string,larguraMm:float} $colunas
     * @param array<int,float> $alturas
     */
    private static function montarTabela(
        array $celulas,
        array $tokens,
        array $origem,
        array $coberto,
        array $colunas,
        int $de,
        int $ate,
        int $maxColuna,
        array $alturas = []
    ): string {
        $largura = number_format((float) ($colunas['larguraMm'] ?? 0), 2, '.', '');
        $colgroup = (string) ($colunas['html'] ?? '');
        $html = '<table class="dados seed-folha" style="width:' . $largura . 'mm;border-collapse:collapse;table-layout:fixed;">'
            . $colgroup;
        for ($l = $de; $l <= $ate; $l++) {
            $mm = number_format(self::alturaLinhaMm($alturas, $l), 2, '.', '');
            $html .= '<tr style="height:' . $mm . 'mm;">';
            for ($c = 1; $c <= $maxColuna; $c++) {
                $chaveCel = $l . ',' . $c;
                if (isset($coberto[$chaveCel])) {
                    continue;
                }
                $rs = 1;
                $cs = 1;
                if (isset($origem[$chaveCel])) {
                    [$rs, $cs] = $origem[$chaveCel];
                }
                $texto = trim((string) ($celulas[$l][$c] ?? ''));
                $token = (string) ($tokens[$l][$c] ?? '');
                $conteudo = $texto;
                if ($token !== '') {
                    $marcador = '{{' . $token . '}}';
                    $conteudo = $texto === '' ? $marcador : (self::ehLogotipo($texto) ? $marcador : $texto . ' ' . $marcador);
                }
                $estilo = 'vertical-align:middle;';
                if (self::ehTitulo($texto)) {
                    $estilo .= 'text-align:center;font-weight:bold;';
                }
                $attr = ' style="' . $estilo . '"';
                if ($rs > 1) {
                    $attr .= ' rowspan="' . $rs . '"';
                }
                if ($cs > 1) {
                    $attr .= ' colspan="' . $cs . '"';
                }
                $html .= '<td' . $attr . '>' . ($conteudo === '' ? '&nbsp;' : self::escapar($conteudo)) . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</table>';
    }

    /**
     * Largura de coluna do Excel (caracteres da fonte padrão) em milímetros.
     * Fórmula de pixel do Office, 96 dpi, dígito máximo 7 px (Calibri 11).
     */
    public static function excelLarguraParaMm(float $caracteres): float
    {
        if ($caracteres <= 0) {
            return 0.0;
        }
        $digitoPx = 7.0;
        if ($caracteres < 1) {
            $px = (int) floor($caracteres * $digitoPx + 0.5);
        } else {
            $px = (int) floor(((256 * $caracteres) + (int) floor(128 / 7)) / 256 * $digitoPx);
        }

        return round($px * 25.4 / 96, 2);
    }

    public static function pontosParaMm(float $pontos): float
    {
        if ($pontos <= 0) {
            return 0.0;
        }

        return round($pontos * 25.4 / 72, 2);
    }

    /**
     * @param array<int,float> $alturas altura do Excel em pontos
     */
    private static function alturaLinhaMm(array $alturas, int $linha): float
    {
        $pt = (float) ($alturas[$linha] ?? 15.0);
        if ($pt <= 0) {
            $pt = 15.0;
        }

        return self::pontosParaMm($pt);
    }

    /**
     * @param array<int,float>|mixed $larguras largura do Excel em caracteres
     * @return array{html:string,larguraMm:float}
     */
    private static function colgroup(mixed $larguras, int $maxColuna): array
    {
        if ($maxColuna < 1) {
            return ['html' => '', 'larguraMm' => 0.0];
        }
        if (!is_array($larguras)) {
            $larguras = [];
        }
        $html = '<colgroup>';
        $soma = 0.0;
        for ($c = 1; $c <= $maxColuna; $c++) {
            $caracteres = (float) ($larguras[$c] ?? 0);
            if ($caracteres <= 0) {
                $caracteres = 8.43;
            }
            $mm = self::excelLarguraParaMm($caracteres);
            if ($mm < 1) {
                $mm = 1.0;
            }
            $soma += $mm;
            $html .= '<col style="width:' . number_format($mm, 2, '.', '') . 'mm;">';
        }

        return ['html' => $html . '</colgroup>', 'larguraMm' => round($soma, 2)];
    }

    /**
     * @param array<int,array<int,string>> $celulas
     */
    private static function linhaQuebraVerso(array $celulas, int $maxLinha, int $maxColuna): int
    {
        for ($l = 1; $l <= $maxLinha; $l++) {
            for ($c = 1; $c <= $maxColuna; $c++) {
                $norm = ImportadorModeloPlanilhaService::normalizar((string) ($celulas[$l][$c] ?? ''));
                if ($norm === 'legenda') {
                    return $l;
                }
            }
        }

        return 0;
    }

    /**
     * @param array<int,array<int,string>> $celulas
     * @param list<array{0:int,1:int,2:int,3:int}> $mesclas
     * @return array<int,array<int,string>>
     */
    private static function montarTokens(array $celulas, array $mesclas, string $chave, int $maxLinha, int $maxColuna): array
    {
        $tokens = [];
        $fimMescla = [];
        foreach ($mesclas as $m) {
            [$l1, $c1, $l2, $c2] = $m;
            $fimMescla[$l1 . ',' . $c1] = [$l2, $c2];
        }

        self::preencherCabecalho($celulas, $fimMescla, $tokens, $maxLinha, $maxColuna);
        self::preencherNotas($celulas, $tokens, $chave, $maxLinha, $maxColuna);
        self::preencherRodape($celulas, $fimMescla, $tokens, $maxLinha, $maxColuna);

        return $tokens;
    }

    /**
     * @param array<int,array<int,string>> $celulas
     * @param array<string,array{0:int,1:int}> $fimMescla
     * @param array<int,array<int,string>> $tokens
     */
    private static function preencherCabecalho(array $celulas, array $fimMescla, array &$tokens, int $maxLinha, int $maxColuna): void
    {
        $dic = ImportadorModeloPlanilhaService::dicionarioRotulos();
        unset($dic['media anual'], $dic['media final'], $dic['resultado'], $dic['n'], $dic['organizacao']);
        $limite = min(18, $maxLinha);
        for ($l = 1; $l <= $limite; $l++) {
            for ($c = 1; $c <= $maxColuna; $c++) {
                $texto = trim((string) ($celulas[$l][$c] ?? ''));
                if ($texto === '') {
                    continue;
                }
                if (self::ehLogotipo($texto)) {
                    $tokens[$l][$c] = 'logo_html';
                    continue;
                }
                $rotulo = ImportadorModeloPlanilhaService::normalizar(rtrim($texto, " :\t"));
                if ($rotulo === 'n' || $rotulo === 'no') {
                    self::colocarADireita($celulas, $fimMescla, $tokens, $l, $c, 'numero_chamada', $maxColuna);
                    continue;
                }
                if ($rotulo === 'organizacao') {
                    self::colocarADireita($celulas, $fimMescla, $tokens, $l, $c, 'organizacao', $maxColuna);
                    continue;
                }
                if (!isset($dic[$rotulo])) {
                    continue;
                }
                self::colocarADireita($celulas, $fimMescla, $tokens, $l, $c, $dic[$rotulo], $maxColuna);
            }
        }
    }

    /**
     * @param array<int,array<int,string>> $celulas
     * @param array<int,array<int,string>> $tokens
     */
    private static function preencherNotas(array $celulas, array &$tokens, string $chave, int $maxLinha, int $maxColuna): void
    {
        $periodos = GradeSoltaService::periodos($chave);
        $linhaPeriodo = 0;
        $colsPeriodo = [];
        for ($l = 1; $l <= min(40, $maxLinha); $l++) {
            $achados = [];
            for ($c = 1; $c <= $maxColuna; $c++) {
                $norm = ImportadorModeloPlanilhaService::normalizar((string) ($celulas[$l][$c] ?? ''));
                foreach ($periodos as $p) {
                    $alvo = ImportadorModeloPlanilhaService::normalizar((string) $p['rotulo']);
                    if ($norm === $alvo) {
                        $achados[(int) $p['n']] = $c;
                    }
                }
            }
            if (count($achados) >= max(1, count($periodos) - 1)) {
                $linhaPeriodo = $l;
                $colsPeriodo = $achados;
                break;
            }
        }
        if ($linhaPeriodo === 0 || $colsPeriodo === []) {
            return;
        }

        $colNotas = [];
        $colFaltas = [];
        $colMediaAnual = 0;
        $colMediaFinal = 0;
        for ($l = $linhaPeriodo; $l <= min($linhaPeriodo + 4, $maxLinha); $l++) {
            for ($c = 1; $c <= $maxColuna; $c++) {
                $norm = ImportadorModeloPlanilhaService::normalizar((string) ($celulas[$l][$c] ?? ''));
                if ($norm === 'media anual') {
                    $colMediaAnual = $c;
                }
                if ($norm === 'media final') {
                    $colMediaFinal = $c;
                }
                if ($norm === 'notas ou mencoes') {
                    $n = self::periodoDaColuna($colsPeriodo, $c);
                    if ($n !== null) {
                        $colNotas[$n] = $c;
                    }
                }
                if ($norm === 'faltas' || $norm === 'faltas ') {
                    $n = self::periodoDaColuna($colsPeriodo, $c);
                    if ($n !== null) {
                        $colFaltas[$n] = $c;
                    }
                }
            }
        }
        if ($colMediaAnual === 0 || $colMediaFinal === 0) {
            for ($c = 1; $c <= $maxColuna; $c++) {
                $norm = ImportadorModeloPlanilhaService::normalizar((string) ($celulas[$linhaPeriodo - 1][$c] ?? ''));
                if ($norm === 'media anual') {
                    $colMediaAnual = $c;
                }
                if ($norm === 'media final') {
                    $colMediaFinal = $c;
                }
            }
        }

        $ifa = 0;
        $diversificada = 0;
        for ($l = $linhaPeriodo + 1; $l <= $maxLinha; $l++) {
            $nome = self::nomeComponenteNaLinha($celulas[$l] ?? []);
            if ($nome === '') {
                continue;
            }
            $norm = ImportadorModeloPlanilhaService::normalizar($nome);
            if ($norm === 'total'
                || str_contains($norm, 'legenda')
                || str_contains($norm, 'sintese do sistema')
                || str_contains($norm, 'observacoes')
                || str_contains($norm, 'guia de transferencia')) {
                break;
            }
            if (str_contains($norm, 'formacao geral') || str_contains($norm, 'itinerario formativo')
                || str_contains($norm, 'componentes curriculares') || str_contains($norm, 'adaptacoes')
                || str_contains($norm, 'linguagens e suas') || str_contains($norm, 'ciencias humanas')
                || str_contains($norm, 'matematica e suas') || str_contains($norm, 'ciencias da natureza')) {
                continue;
            }
            $slug = self::slugComponente($norm, $ifa, $diversificada);
            if ($slug === null) {
                continue;
            }
            if (str_starts_with($slug, 'ifa_') || str_starts_with($slug, 'diversificada_')) {
                for ($c = 3; $c <= 7; $c++) {
                    if (trim((string) ($celulas[$l][$c] ?? '')) === '' && !isset($tokens[$l][$c])) {
                        $tokens[$l][$c] = 'comp_' . $slug;
                        break;
                    }
                }
            }
            foreach ($periodos as $p) {
                $n = (int) $p['n'];
                if (isset($colNotas[$n])) {
                    $tokens[$l][$colNotas[$n]] = 'nota_' . $slug . '_' . $n;
                }
                if (isset($colFaltas[$n])) {
                    $tokens[$l][$colFaltas[$n]] = 'falta_' . $slug . '_' . $n;
                }
            }
            if ($colMediaAnual > 0) {
                $tokens[$l][$colMediaAnual] = 'media_anual_' . $slug;
            }
            if ($colMediaFinal > 0) {
                $tokens[$l][$colMediaFinal] = 'media_final_' . $slug;
            }
        }
    }

    /**
     * @param array<int,array<int,string>> $celulas
     * @param array<string,array{0:int,1:int}> $fimMescla
     * @param array<int,array<int,string>> $tokens
     */
    private static function preencherRodape(array $celulas, array $fimMescla, array &$tokens, int $maxLinha, int $maxColuna): void
    {
        for ($l = 19; $l <= $maxLinha; $l++) {
            for ($c = 1; $c <= $maxColuna; $c++) {
                $texto = trim((string) ($celulas[$l][$c] ?? ''));
                if ($texto === '') {
                    continue;
                }
                $norm = ImportadorModeloPlanilhaService::normalizar(rtrim($texto, " :\t"));
                if ($norm === 'local/data') {
                    self::colocarADireita($celulas, $fimMescla, $tokens, $l, $c, 'cidade_data', $maxColuna);
                } elseif ($norm === 'resultado final') {
                    self::colocarADireita($celulas, $fimMescla, $tokens, $l, $c, 'situacao_final', $maxColuna);
                } elseif (str_contains($norm, '% de frequencia')) {
                    self::colocarADireita($celulas, $fimMescla, $tokens, $l, $c, 'frequencia_percentual', $maxColuna);
                } elseif (str_starts_with($norm, 'observacoes')) {
                    self::colocarADireita($celulas, $fimMescla, $tokens, $l, $c, 'observacoes', $maxColuna);
                } elseif (str_contains($norm, 'secretario')) {
                    self::colocarAcima($celulas, $tokens, $l, $c, 'secretario_nome');
                } elseif (str_contains($norm, 'diretor')) {
                    self::colocarAcima($celulas, $tokens, $l, $c, 'diretor_nome');
                } elseif (str_contains($norm, 'expedida ao')) {
                    self::colocarADireita($celulas, $fimMescla, $tokens, $l, $c, 'aluno_nome', $maxColuna);
                } elseif (str_contains($norm, 'matriculado')) {
                    self::colocarADireita($celulas, $fimMescla, $tokens, $l, $c, 'turma_nome', $maxColuna);
                } elseif ($norm === 'no ano') {
                    self::colocarADireita($celulas, $fimMescla, $tokens, $l, $c, 'ano_letivo', $maxColuna);
                }
            }
        }
    }

    /**
     * @param array<int,array<int,string>> $celulas
     * @param array<string,array{0:int,1:int}> $fimMescla
     * @param array<int,array<int,string>> $tokens
     */
    private static function colocarADireita(array $celulas, array $fimMescla, array &$tokens, int $linha, int $coluna, string $token, int $maxColuna): void
    {
        $inicio = $coluna + 1;
        if (isset($fimMescla[$linha . ',' . $coluna])) {
            $inicio = $fimMescla[$linha . ',' . $coluna][1] + 1;
        }
        for ($c = $inicio; $c <= $maxColuna; $c++) {
            if (trim((string) ($celulas[$linha][$c] ?? '')) !== '') {
                if (isset($fimMescla[$linha . ',' . $c])) {
                    $c = $fimMescla[$linha . ',' . $c][1];
                }
                continue;
            }
            if (isset($tokens[$linha][$c])) {
                continue;
            }
            $tokens[$linha][$c] = $token;
            return;
        }
        if (!isset($tokens[$linha][$coluna])) {
            $tokens[$linha][$coluna] = $token;
        }
    }

    /**
     * @param array<int,array<int,string>> $celulas
     * @param array<int,array<int,string>> $tokens
     */
    private static function colocarAcima(array $celulas, array &$tokens, int $linha, int $coluna, string $token): void
    {
        for ($l = $linha - 1; $l >= max(1, $linha - 3); $l--) {
            if (trim((string) ($celulas[$l][$coluna] ?? '')) !== '') {
                continue;
            }
            $tokens[$l][$coluna] = $token;
            return;
        }
    }

    /** @param array<int,int> $colsPeriodo */
    private static function periodoDaColuna(array $colsPeriodo, int $coluna): ?int
    {
        $escolhido = null;
        $melhor = -1;
        foreach ($colsPeriodo as $n => $inicio) {
            if ($coluna >= $inicio && $inicio > $melhor) {
                $melhor = $inicio;
                $escolhido = (int) $n;
            }
        }
        return $escolhido;
    }

    /** @param array<int,string> $linha */
    private static function nomeComponenteNaLinha(array $linha): string
    {
        foreach ([3, 1, 2, 4, 5, 6, 7] as $c) {
            $t = trim((string) ($linha[$c] ?? ''));
            if ($t !== '') {
                return $t;
            }
        }
        return '';
    }

    private static function slugComponente(string $norm, int &$ifa, int &$diversificada): ?string
    {
        foreach (GradeSoltaService::componentes() as $comp) {
            if ($comp['vaga']) {
                continue;
            }
            foreach ($comp['aliases'] as $alias) {
                if ($norm === ImportadorModeloPlanilhaService::normalizar((string) $alias)
                    || $norm === ImportadorModeloPlanilhaService::normalizar((string) $comp['nome'])) {
                    return (string) $comp['slug'];
                }
            }
        }
        if (str_contains($norm, 'projeto integrador') || $norm === 'projeto de vida') {
            $ifa++;
            if ($ifa > 3) {
                return null;
            }
            return 'ifa_' . $ifa;
        }
        if (str_contains($norm, 'parte diversif')) {
            $diversificada++;
            return 'diversificada_' . $diversificada;
        }
        return null;
    }

    private static function ehLogotipo(string $texto): bool
    {
        $n = ImportadorModeloPlanilhaService::normalizar($texto);
        return str_contains($n, 'logotipo') || str_contains($n, 'logo');
    }

    private static function ehTitulo(string $texto): bool
    {
        $n = ImportadorModeloPlanilhaService::normalizar($texto);
        return str_contains($n, 'estado do parana')
            || str_contains($n, 'secretaria de estado')
            || str_contains($n, 'ficha individual')
            || $n === 'ensino medio'
            || str_contains($n, 'formacao geral')
            || str_contains($n, 'itinerario formativo')
            || str_contains($n, 'guia de transferencia')
            || str_contains($n, 'componentes curriculares');
    }

    private static function escapar(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }
}
