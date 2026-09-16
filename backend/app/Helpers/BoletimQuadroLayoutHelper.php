<?php
/**
 * Layout do boletim no modelo "quadro semanal" (S1–S8 com N/Q, dois blocos de matérias).
 */
class BoletimQuadroLayoutHelper
{
    public const SEMANA_MAX = 20;

    /** @return list<string> */
    public static function gruposPermitidos(): array
    {
        return ['b1', 'b2', 'b3', 'b4', 'final', 'quadro_a', 'quadro_b', 'quadro_comum'];
    }

    public static function grupoLayoutEhValido(string $grupo): bool
    {
        $g = strtolower(trim($grupo));
        if ($g === '') {
            return false;
        }
        if (in_array($g, self::gruposPermitidos(), true)) {
            return true;
        }
        return (bool) preg_match('/^grupo_[a-z0-9][a-z0-9_]{0,40}$/', $g);
    }

    public static function codigoEhSemana(string $codigo): bool
    {
        $c = strtolower(trim($codigo));

        return (bool) preg_match('/^(?:[a-z0-9][a-z0-9_]{0,40}_)?s([1-9]|[1-9]\d)$/', $c);
    }

    /** @return list<string> */
    public static function tiposPermitidos(): array
    {
        return ['media', 'faltas', 'rec', 'resultado', 'other', 'semana_nq', 'n', 'q', 'valor10', 'media_sem'];
    }

    public static function ehGrupoQuadro(string $grupo): bool
    {
        $g = strtolower(trim($grupo));
        if (in_array($g, ['quadro_a', 'quadro_b', 'quadro_comum'], true)) {
            return true;
        }
        return str_starts_with($g, 'grupo_');
    }

    public static function ehGrupoTabelaQuadro(string $grupo): bool
    {
        $g = strtolower(trim($grupo));
        if ($g === '' || $g === 'quadro_comum') {
            return false;
        }
        return $g === 'quadro_a' || $g === 'quadro_b' || str_starts_with($g, 'grupo_');
    }

    /**
     * Evento de Notas (exibir_em=notas) usa as colunas na ordem da construção.
     * O cabeçalho 1º–4º BIMESTRE / OUTROS / FINAL vale só para o boletim oficial.
     */
    public static function deveAgruparCabecalhoBoletimOficial(string $exibirEm): bool
    {
        return strtolower(trim($exibirEm)) !== 'notas';
    }

    /**
     * @param list<array<string,mixed>> $cols
     */
    public static function ehLayoutQuadro(array $cols): bool
    {
        foreach ($cols as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (self::ehGrupoQuadro((string) ($c['layout_group'] ?? ''))) {
                return true;
            }
        }
        return false;
    }

    public static function colunaEhSemanaNq(array $col): bool
    {
        $tipo = strtolower(trim((string) ($col['layout_type'] ?? '')));
        if ($tipo === 'semana_nq') {
            return true;
        }
        $cod = strtolower(trim((string) ($col['codigo'] ?? '')));
        return self::codigoEhSemana($cod);
    }

    /** Média Sem fica colada nas semanas do bloco, não na ordem das outras colunas. */
    public static function colunaEhMediaSem(array $col): bool
    {
        $tipo = strtolower(trim((string) ($col['layout_type'] ?? '')));
        if ($tipo === 'media_sem') {
            return true;
        }
        return strtolower(trim((string) ($col['codigo'] ?? ''))) === 'media_sem';
    }

    /**
     * Parte as colunas em uma tabela por tipo do quadro (A/B, Humanas…).
     * Colunas comuns (média sem, prova bim, rec) entram em todas as tabelas.
     *
     * @param list<array<string,mixed>> $cols
     * @return list<array{key:string,titulo:string,subtitulo:string,cols:list<array<string,mixed>>}>
     */
    public static function partirTabelas(array $cols): array
    {
        $porGrupo = [];
        $labels = [];
        $mediaSem = [];
        $comum = [];
        $temQuadro = false;
        foreach ($cols as $c) {
            if (!is_array($c)) {
                continue;
            }
            $g = strtolower(trim((string) ($c['layout_group'] ?? '')));
            if (self::ehGrupoTabelaQuadro($g)) {
                $temQuadro = true;
                if (!isset($porGrupo[$g])) {
                    $porGrupo[$g] = [];
                }
                $porGrupo[$g][] = $c;
                if (!isset($labels[$g]) || $labels[$g] === '') {
                    $labels[$g] = self::rotuloGrupoQuadro($c, $g);
                }
            } elseif (self::colunaEhMediaSem($c)) {
                $mediaSem[] = $c;
            } else {
                $comum[] = $c;
            }
        }
        $comum = array_merge($mediaSem, $comum);
        if (!$temQuadro) {
            return [];
        }

        $out = [];
        $variosGrupos = count($porGrupo) > 1;
        foreach ($porGrupo as $key => $colsGrupo) {
            $chaveTab = self::chaveTabelaQuadro((string) $key);
            $titulo = $labels[$key] !== '' ? $labels[$key] : self::rotuloPadraoGrupoQuadro((string) $key);
            if (!$variosGrupos) {
                $titulo = 'Matérias';
            }
            $out[] = [
                'key' => $chaveTab,
                'titulo' => $titulo,
                'subtitulo' => 'Prova semanal',
                'cols' => array_merge($colsGrupo, $comum),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $cols
     */
    public static function grupoIdDasColunas(array $cols): int
    {
        foreach ($cols as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = self::configDoComponente($c);
            $id = (int) ($cfg['grupo_regras_notas_id'] ?? $c['grupo_regras_notas_id'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    public static function chaveTabelaQuadro(string $grupo): string
    {
        $g = strtolower(trim($grupo));
        if (in_array($g, ['quadro_a', 'a', 'grupo_a', 'bloco_a'], true)) {
            return 'a';
        }
        if (in_array($g, ['quadro_b', 'b', 'grupo_b', 'bloco_b'], true)) {
            return 'b';
        }

        return $g !== '' ? $g : 'a';
    }

    /**
     * Remove semanas que não existem no quadro e junta A/B quando não há blocos.
     *
     * @param list<array<string,mixed>> $cols
     * @return list<array<string,mixed>>
     */
    public static function alinharColunasAoQuadroCadastro(array $cols, ?int $grupoId = null): array
    {
        $sem = self::semanasDoQuadroCadastro($grupoId);
        return self::alinharColunasSemanas($cols, $sem['a'], $sem['b']);
    }

    /**
     * @return array{a:list<int>,b:list<int>}
     */
    public static function semanasDoQuadroCadastro(?int $grupoId = null): array
    {
        static $cache = [];
        $k = $grupoId ?? 0;
        if (isset($cache[$k])) {
            return $cache[$k];
        }
        $vazio = ['a' => [], 'b' => []];
        $path = dirname(__DIR__) . '/Modulos/grupos-regras-notas/Services/GrupoRegrasNotasService.php';
        if (!class_exists('GrupoRegrasNotasService', false) && is_file($path)) {
            require_once $path;
        }
        if (!class_exists('GrupoRegrasNotasService', false)) {
            return $cache[$k] = $vazio;
        }
        try {
            $sem = (new GrupoRegrasNotasService())->semanasQuadroPadrao($grupoId);
            $a = is_array($sem['a'] ?? null) ? array_values(array_map('intval', $sem['a'])) : [];
            $b = is_array($sem['b'] ?? null) ? array_values(array_map('intval', $sem['b'])) : [];
            return $cache[$k] = ['a' => $a, 'b' => $b];
        } catch (Throwable $e) {
            return $cache[$k] = $vazio;
        }
    }

    /**
     * @param list<array<string,mixed>> $cols
     * @param list<int> $semanasA
     * @param list<int> $semanasB
     * @return list<array<string,mixed>>
     */
    public static function alinharColunasSemanas(array $cols, array $semanasA, array $semanasB): array
    {
        $semanasA = self::normalizarSemanasLista($semanasA, []);
        $semanasB = self::normalizarSemanasLista($semanasB, []);
        $permitidas = array_fill_keys(array_merge($semanasA, $semanasB), true);
        if ($permitidas === []) {
            return $cols;
        }
        $soUmGrupo = $semanasB === [] || $semanasA === [];
        $grupoUnico = $semanasA !== [] ? 'quadro_a' : 'quadro_b';
        $semanasOut = [];
        $resto = [];
        foreach ($cols as $c) {
            if (!is_array($c)) {
                continue;
            }
            $ehSemana = self::colunaEhSemanaNq($c) || self::codigoEhSemana((string) ($c['codigo'] ?? ''));
            if ($ehSemana) {
                $n = self::numeroSemanaDaColuna($c);
                if ($n > 0 && !isset($permitidas[$n])) {
                    continue;
                }
                $grupo = in_array($n, $semanasA, true) ? 'quadro_a' : 'quadro_b';
                if ($soUmGrupo) {
                    $grupo = $grupoUnico;
                }
                if ($n > 0) {
                    $semanasOut[] = self::aplicarLayoutNoComponente($c, $grupo, 'semana_nq', $n);
                    continue;
                }
            }
            $resto[] = $c;
        }
        if ($soUmGrupo) {
            usort($semanasOut, static function (array $a, array $b): int {
                return self::numeroSemanaDaColuna($a) <=> self::numeroSemanaDaColuna($b);
            });
        }

        return array_merge($semanasOut, $resto);
    }

    /**
     * @param array<string,mixed> $col
     */
    public static function numeroSemanaDaColuna(array $col): int
    {
        $cod = strtolower(trim((string) ($col['codigo'] ?? '')));
        if (preg_match('/(?:^|_)s([1-9]|[1-9]\d)$/', $cod, $m)) {
            return (int) $m[1];
        }
        $cfg = self::configDoComponente($col);
        $s = (int) ($cfg['semana'] ?? $col['semana'] ?? 0);

        return ($s >= 1 && $s <= self::SEMANA_MAX) ? $s : 0;
    }

    /**
     * @param array<string,mixed> $col
     */
    public static function rotuloGrupoQuadro(array $col, string $grupo): string
    {
        $cfg = is_array($col['config'] ?? null) ? $col['config'] : [];
        $layout = is_array($cfg['layout'] ?? null) ? $cfg['layout'] : [];
        $label = trim((string) ($col['layout_group_label'] ?? $cfg['layout_group_label'] ?? $layout['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        return self::rotuloPadraoGrupoQuadro($grupo);
    }

    public static function rotuloPadraoGrupoQuadro(string $grupo): string
    {
        $g = strtolower(trim($grupo));
        if ($g === 'quadro_a') {
            return 'Matérias Bloco A';
        }
        if ($g === 'quadro_b') {
            return 'Matérias Bloco B';
        }
        if (str_starts_with($g, 'grupo_')) {
            $resto = substr($g, 6);
            return $resto !== '' ? ('Matérias ' . mb_convert_case(str_replace('_', ' ', $resto), MB_CASE_TITLE, 'UTF-8')) : 'Matérias';
        }
        return 'Matérias';
    }

    /**
     * @param array<string,mixed> $notas
     */
    public static function celulaNq(array $notas, string $codigo): array
    {
        $nKey = $codigo . '__n';
        $qKey = $codigo . '__q';
        $n = isset($notas[$nKey]) && is_numeric($notas[$nKey]) ? (int) $notas[$nKey] : null;
        $q = isset($notas[$qKey]) && is_numeric($notas[$qKey]) ? (int) $notas[$qKey] : null;

        return ['n' => $n, 'q' => $q];
    }

    /**
     * A linha pertence à tabela se tiver N/Q em alguma semana do bloco ou nota nas colunas comuns.
     *
     * @param list<array<string,mixed>> $colsTabela
     * @param array<string,mixed> $notas
     */
    public static function linhaTemDadosNoBloco(array $colsTabela, array $notas): bool
    {
        $temColunaSemana = false;
        foreach ($colsTabela as $c) {
            if (is_array($c) && self::colunaEhSemanaNq($c)) {
                $temColunaSemana = true;
                break;
            }
        }
        foreach ($colsTabela as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = (string) ($c['codigo'] ?? '');
            if ($cod === '') {
                continue;
            }
            if (self::colunaEhSemanaNq($c)) {
                $nq = self::celulaNq($notas, $cod);
                if (($nq['n'] !== null && $nq['n'] > 0) || ($nq['q'] !== null && $nq['q'] > 0)) {
                    return true;
                }
                $nv = $notas[$cod] ?? null;
                if (is_numeric($nv) && (float) $nv != 0.0) {
                    return true;
                }
                continue;
            }
            if ($temColunaSemana) {
                continue;
            }
            $nv = $notas[$cod] ?? null;
            if (is_numeric($nv) && (float) $nv != 0.0) {
                return true;
            }
            if (is_string($nv) && trim($nv) !== '' && trim($nv) !== '—') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $semanasDeste
     * @param list<array<string,mixed>> $semanasOutro
     * @param list<array<string,mixed>> $outrasCols
     * @param array<string,mixed> $notas
     */
    public static function linhaVisivelNoQuadro(
        string $blocoKey,
        array $semanasDeste,
        array $semanasOutro,
        array $outrasCols,
        array $notas
    ): bool {
        if (self::linhaTemDadosNoBloco($semanasDeste, $notas)) {
            return true;
        }
        if (self::linhaTemDadosNoBloco($semanasOutro, $notas)) {
            return false;
        }
        if (strtolower($blocoKey) !== 'a') {
            return false;
        }
        foreach ($outrasCols as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = (string) ($c['codigo'] ?? '');
            if ($cod === '') {
                continue;
            }
            $nv = $notas[$cod] ?? null;
            if (is_numeric($nv) && (float) $nv != 0.0) {
                return true;
            }
            if (is_string($nv) && trim($nv) !== '' && trim($nv) !== '—') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $componentes
     */
    public static function componentesJaSaoQuadro(array $componentes): bool
    {
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (self::codigoEhSemana($cod)) {
                return true;
            }
            $cfg = self::configDoComponente($c);
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $cfg['layout']['group'] ?? $c['layout_group'] ?? '')));
            if (self::ehGrupoTabelaQuadro($g)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<array<string,mixed>> $componentes
     */
    public static function componentesTemPecaSemanal(array $componentes): bool
    {
        foreach ($componentes as $c) {
            if (is_array($c) && self::componenteEhPecaSemanal($c)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Transforma a coluna única "Prova semanal" nas semanas do quadro (não inventa S5–S8).
     *
     * @param list<array<string,mixed>> $componentes
     * @param list<int> $semanasA
     * @param list<int> $semanasB
     * @return list<array<string,mixed>>
     */
    public static function expandirComponentesParaQuadroSemanal(
        array $componentes,
        array $semanasA = [],
        array $semanasB = []
    ): array {
        $semanasA = self::normalizarSemanasLista($semanasA, []);
        $semanasB = self::normalizarSemanasLista($semanasB, []);
        if ($semanasA === [] && $semanasB === []) {
            return $componentes;
        }

        if (self::componentesJaSaoQuadro($componentes)) {
            return self::garantirLayoutQuadroNosComponentes($componentes, $semanasA, $semanasB);
        }
        $template = null;
        foreach ($componentes as $c) {
            if (is_array($c) && self::componenteEhPecaSemanal($c)) {
                $template = $c;
                break;
            }
        }
        if ($template === null) {
            return $componentes;
        }

        $out = [];
        $codigosSemana = [];
        $grupoA = $semanasB === [] ? 'quadro_a' : 'quadro_a';
        foreach ($semanasA as $s) {
            $out[] = self::clonarComponenteComoSemanaQuadro($template, (int) $s, $grupoA);
            $codigosSemana[] = 's' . (int) $s;
        }
        foreach ($semanasB as $s) {
            $out[] = self::clonarComponenteComoSemanaQuadro($template, (int) $s, 'quadro_b');
            $codigosSemana[] = 's' . (int) $s;
        }
        $out[] = [
            'id' => 0,
            'codigo' => 'media_sem',
            'nome' => 'Média Sem',
            'source_type' => 'calculado',
            'calc_type' => 'media',
            'peso' => 1,
            'filtro_titulo' => '',
            'blocos_ids' => [],
            'materias_ids' => [],
            'materia_unica' => 0,
            'usar_percentual' => 0,
            'escala_max' => 10,
            'obrigatorio' => 0,
            'config' => [
                'expressao' => '',
                'formula_mode' => 'single',
                'agregar_nq' => $codigosSemana,
                'layout_group' => 'quadro_comum',
                'layout_type' => 'media_sem',
                'layout' => ['group' => 'quadro_comum', 'type' => 'media_sem'],
            ],
        ];

        $codOrigem = strtolower(trim((string) ($template['codigo'] ?? 'semanal')));
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (self::componenteEhPecaSemanal($c)) {
                continue;
            }
            $out[] = self::aplicarLayoutComumNoComponente($c, $codOrigem);
        }

        return $out;
    }

    public static function reescreverCodigoSemanalNaFormula(string $formula, string $codigoAntigo = 'semanal'): string
    {
        $antigo = strtolower(trim($codigoAntigo));
        if ($antigo === '' || $antigo === 'media_sem') {
            return $formula;
        }
        $out = preg_replace('/\b' . preg_quote($antigo, '/') . '\b/i', 'media_sem', $formula);

        return is_string($out) ? $out : $formula;
    }

    /**
     * @param list<array<string,mixed>> $componentes
     * @param list<int> $semanasA
     * @param list<int> $semanasB
     * @return list<array<string,mixed>>
     */
    private static function garantirLayoutQuadroNosComponentes(array $componentes, array $semanasA, array $semanasB): array
    {
        $impar = array_fill_keys($semanasA, true);
        $par = array_fill_keys($semanasB, true);
        $permitidas = array_fill_keys(array_merge($semanasA, $semanasB), true);
        $soUmGrupo = $semanasB === [] || $semanasA === [];
        $grupoUnico = $semanasA !== [] ? 'quadro_a' : 'quadro_b';
        $out = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $cfg = self::configDoComponente($c);
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $cfg['layout']['group'] ?? '')));
            if (preg_match('/^s([1-9]|[1-9]\d)$/', $cod, $m)) {
                $s = (int) $m[1];
                if ($permitidas !== [] && !isset($permitidas[$s])) {
                    continue;
                }
                $grupo = isset($impar[$s]) ? 'quadro_a' : (isset($par[$s]) ? 'quadro_b' : $grupoUnico);
                if ($soUmGrupo) {
                    $grupo = $grupoUnico;
                }
                $out[] = self::aplicarLayoutNoComponente($c, $grupo, 'semana_nq', $s);
                continue;
            }
            if (self::ehGrupoTabelaQuadro($g)) {
                $out[] = $c;
                continue;
            }
            $tipo = 'media';
            if ($cod === 'media_sem') {
                $tipo = 'media_sem';
            } elseif ($cod === 'rec' || str_contains($cod, 'recup')) {
                $tipo = 'rec';
            } elseif ($cod === 'media_final' || str_contains($cod, 'final')) {
                $tipo = 'resultado';
            }
            $out[] = self::aplicarLayoutNoComponente($c, 'quadro_comum', $tipo, 0);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function clonarComponenteComoSemanaQuadro(array $template, int $semana, string $grupo): array
    {
        $novo = $template;
        $novo['id'] = 0;
        $novo['codigo'] = 's' . $semana;
        $novo['nome'] = 'S' . $semana;
        $novo['source_type'] = (string) ($template['source_type'] ?? 'provas_sistema');
        $novo['usar_percentual'] = 1;
        $novo['materia_unica'] = 1;
        $novo['obrigatorio'] = 0;

        return self::aplicarLayoutNoComponente($novo, $grupo, 'semana_nq', $semana);
    }

    /**
     * @param array<string,mixed> $c
     * @return array<string,mixed>
     */
    private static function aplicarLayoutComumNoComponente(array $c, string $codigoSemanalAntigo): array
    {
        $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
        $nome = trim((string) ($c['nome'] ?? ''));
        $tipo = 'media';
        if ($cod === 'media_sem') {
            $tipo = 'media_sem';
        } elseif ($cod === 'rec' || str_contains($cod, 'recup') || str_contains(mb_strtolower($nome), 'recup')) {
            $tipo = 'rec';
            if ($nome === '' || str_contains(mb_strtolower($nome), 'recup')) {
                $c['nome'] = 'Rec';
            }
        } elseif ($cod === 'media_final' || (str_contains($cod, 'final') && ($c['source_type'] ?? '') === 'calculado')) {
            $tipo = 'resultado';
        }
        $nomeLow = mb_strtolower($nome);
        if (str_contains($nomeLow, 'bimestral') || $cod === 'bimestral' || $cod === 'prova_bim') {
            $c['nome'] = 'Prova Bim';
        } elseif ($cod === 'media' || $nomeLow === 'média' || $nomeLow === 'media') {
            $c['nome'] = 'Média Bim';
        } elseif ($cod === 'part' || str_contains($nomeLow, 'particip')) {
            $c['nome'] = 'Part';
        } elseif ($cod === 'trab' || str_contains($nomeLow, 'trabalho')) {
            $c['nome'] = 'Trab';
        }
        $c = self::aplicarLayoutNoComponente($c, 'quadro_comum', $tipo, 0);
        $cfg = self::configDoComponente($c);
        $exp = (string) ($cfg['expressao'] ?? '');
        if ($exp !== '') {
            $cfg['expressao'] = self::reescreverCodigoSemanalNaFormula($exp, $codigoSemanalAntigo);
            $c['config'] = $cfg;
        }

        return $c;
    }

    /**
     * @param array<string,mixed> $c
     * @return array<string,mixed>
     */
    private static function aplicarLayoutNoComponente(array $c, string $grupo, string $tipo, int $semana): array
    {
        $cfg = self::configDoComponente($c);
        $cfg['layout_group'] = $grupo;
        $cfg['layout_type'] = $tipo;
        $cfg['layout'] = ['group' => $grupo, 'type' => $tipo];
        if ($semana >= 1 && $semana <= self::SEMANA_MAX) {
            $cfg['semana'] = $semana;
        }
        $c['config'] = $cfg;
        $c['layout_group'] = $grupo;
        $c['layout_type'] = $tipo;

        return $c;
    }

    /**
     * @param array<string,mixed> $c
     */
    private static function componenteEhPecaSemanal(array $c): bool
    {
        $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
        if (self::codigoEhSemana($cod)) {
            return true;
        }
        $cfg = self::configDoComponente($c);
        $grupo = strtolower(trim((string) ($cfg['layout_group'] ?? $cfg['layout']['group'] ?? $c['layout_group'] ?? '')));
        $tipo = strtolower(trim((string) ($cfg['layout_type'] ?? $cfg['layout']['type'] ?? $c['layout_type'] ?? '')));
        if ($tipo === 'semana_nq' || in_array($grupo, ['quadro_a', 'quadro_b'], true) || str_starts_with($grupo, 'grupo_')) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $c
     * @return array<string,mixed>
     */
    private static function configDoComponente(array $c): array
    {
        $cfg = [];
        $raw = $c['config_json'] ?? '';
        if (is_array($raw)) {
            $cfg = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $tmp = json_decode(trim($raw), true);
            if (is_array($tmp)) {
                $cfg = $tmp;
            }
        }
        if (isset($c['config']) && is_array($c['config'])) {
            $cfg = array_replace_recursive($cfg, $c['config']);
        }

        return is_array($cfg) ? $cfg : [];
    }

    /**
     * @param list<int>|mixed $raw
     * @param list<int> $fallback
     * @return list<int>
     */
    private static function normalizarSemanasLista($raw, array $fallback): array
    {
        if (!is_array($raw)) {
            return $fallback;
        }
        $out = [];
        foreach ($raw as $v) {
            $s = (int) $v;
            if ($s >= 1 && $s <= self::SEMANA_MAX) {
                $out[] = $s;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out !== [] ? $out : $fallback;
    }

    /** @return list<string> */
    public static function chavesResumoNotas(): array
    {
        return ['media_semanal', 'prova_bimestral', 'trabalho', 'media_bimestral', 'faltas'];
    }

    /**
     * @return array<string,string>
     */
    public static function rotulosResumoNotas(): array
    {
        return [
            'media_semanal' => 'Média Semanal',
            'prova_bimestral' => 'Prova Bimestral',
            'trabalho' => 'Trabalho',
            'media_bimestral' => 'Média Bimestral',
            'faltas' => 'Faltas',
        ];
    }

    /**
     * Classifica uma coluna do evento/quadro no resumo da aba Notas.
     *
     * @param array<string,mixed> $col
     */
    public static function chaveColunaResumo(array $col): string
    {
        $cod = strtolower(trim((string) ($col['codigo'] ?? '')));
        $tipo = strtolower(trim((string) ($col['layout_type'] ?? '')));
        $src = strtolower(trim((string) ($col['source_type'] ?? '')));
        $nome = mb_strtolower(trim((string) ($col['nome'] ?? '')), 'UTF-8');
        $blob = $cod . ' ' . $tipo . ' ' . $nome;

        if ($src === 'faltas_evento' || $tipo === 'faltas' || $cod === 'faltas' || $cod === 'falta' || $nome === 'faltas' || $nome === 'falta') {
            return 'faltas';
        }
        if (self::colunaEhSemanaNq($col) || self::codigoEhSemana($cod)) {
            return '';
        }
        if (self::colunaEhMediaSem($col) || $cod === 'media_sem') {
            return 'media_semanal';
        }
        if ($cod === 'media_bim') {
            return 'media_bimestral';
        }
        if ($cod === 'prova_bim' || $cod === 'bimestral' || (str_contains($nome, 'prova bim') && !str_contains($blob, 'recup'))) {
            return 'prova_bimestral';
        }
        if ($cod === 'trab' || $cod === 'trabalho' || preg_match('/\btrab(?:alho)?\b/u', $nome)) {
            return 'trabalho';
        }
        $temRecup = str_contains($blob, 'recup');
        if (!$temRecup && (str_contains($blob, 'média bim') || str_contains($blob, 'media bim'))) {
            return 'media_bimestral';
        }
        if (!$temRecup && (str_contains($blob, 'média sem') || str_contains($blob, 'media sem'))) {
            return 'media_semanal';
        }

        return '';
    }

    /**
     * @param list<array<string,mixed>> $eventosGerados
     * @param array<string,mixed> $quadro
     * @return list<array<string,mixed>>
     */
    public static function montarResumosNotas(array $eventosGerados, array $quadro = []): array
    {
        $out = [];
        foreach ($eventosGerados as $evento) {
            if (!is_array($evento) || empty($evento['linhas'])) {
                continue;
            }
            $bimestre = isset($evento['bimestre']) && $evento['bimestre'] !== null
                ? (int) $evento['bimestre']
                : null;
            $anoEvento = isset($evento['ano_letivo']) && $evento['ano_letivo'] !== null
                ? (int) $evento['ano_letivo']
                : null;
            $out[] = self::montarResumoDoEvento(
                $evento,
                self::mapaFaltasDoQuadro($quadro, $bimestre, $anoEvento)
            );
        }

        return $out;
    }

    /**
     * Fallback quando ainda não há evento gerado: usa o quadro de notas sem as semanas.
     *
     * @param list<array<string,mixed>> $paineis
     * @param array<string,mixed> $quadro
     * @return list<array<string,mixed>>
     */
    public static function montarResumosDoPainelQuadro(array $paineis, array $quadro = [], ?int $bimestre = null): array
    {
        $anoQuadro = isset($quadro['ficha']['ano_letivo']) ? (int) $quadro['ficha']['ano_letivo'] : null;
        $faltas = self::mapaFaltasDoQuadro($quadro, $bimestre, $anoQuadro > 0 ? $anoQuadro : null);
        $out = [];
        foreach ($paineis as $painel) {
            if (!is_array($painel)) {
                continue;
            }
            $resumo = self::montarResumoDoPainelQuadro($painel, $faltas);
            if ($resumo !== null) {
                $out[] = $resumo;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $evento
     * @param array<int,int> $faltasPorMateria
     * @return array<string,mixed>
     */
    public static function montarResumoDoEvento(array $evento, array $faltasPorMateria = []): array
    {
        $chaves = self::chavesResumoNotas();
        $rotulos = self::rotulosResumoNotas();
        $mapaCod = [];
        foreach (is_array($evento['colunas'] ?? null) ? $evento['colunas'] : [] as $col) {
            if (!is_array($col)) {
                continue;
            }
            $chave = self::chaveColunaResumo($col);
            if ($chave === '' || isset($mapaCod[$chave])) {
                continue;
            }
            $mapaCod[$chave] = (string) ($col['codigo'] ?? '');
        }

        $linhas = [];
        $vistos = [];
        foreach (is_array($evento['linhas'] ?? null) ? $evento['linhas'] : [] as $lin) {
            if (!is_array($lin)) {
                continue;
            }
            $mid = (int) ($lin['materia_id'] ?? 0);
            $nome = trim((string) ($lin['materia_nome'] ?? ''));
            $chaveLinha = $mid > 0 ? 'id:' . $mid : 'n:' . mb_strtolower($nome, 'UTF-8');
            if ($nome === '' || isset($vistos[$chaveLinha])) {
                continue;
            }
            $vistos[$chaveLinha] = true;
            $notas = is_array($lin['notas'] ?? null) ? $lin['notas'] : [];
            $celulas = [];
            foreach ($chaves as $chave) {
                if ($chave === 'faltas') {
                    $codFaltas = $mapaCod['faltas'] ?? '';
                    $doEvento = $codFaltas !== '' ? ($notas[$codFaltas] ?? null) : null;
                    $celulas[$chave] = $faltasPorMateria[$mid] ?? (is_numeric($doEvento) ? (int) round((float) $doEvento) : null);
                    continue;
                }
                $cod = $mapaCod[$chave] ?? '';
                $val = $cod !== '' ? ($notas[$cod] ?? null) : null;
                if ($chave === 'media_semanal' && !is_numeric($val)) {
                    $val = self::mediaSemanalDasNotas($notas, is_array($evento['colunas'] ?? null) ? $evento['colunas'] : []);
                }
                $celulas[$chave] = is_numeric($val) ? (float) $val : null;
            }
            $temNota = false;
            foreach (['media_semanal', 'prova_bimestral', 'trabalho', 'media_bimestral'] as $chaveNota) {
                if ($celulas[$chaveNota] !== null) {
                    $temNota = true;
                    break;
                }
            }
            if (!$temNota) {
                continue;
            }
            $linhas[] = [
                'materia_id' => $mid,
                'materia_nome' => $nome,
                'pai_nome' => null,
                'celulas' => $celulas,
            ];
        }

        $bimestre = isset($evento['bimestre']) && $evento['bimestre'] !== null ? (int) $evento['bimestre'] : null;
        $ano = isset($evento['ano_letivo']) && $evento['ano_letivo'] !== null ? (int) $evento['ano_letivo'] : null;
        $regraId = (int) ($evento['regra_id'] ?? $evento['id'] ?? 0);

        return [
            'titulo' => self::tituloResumoEvento($evento),
            'subtitulo' => 'Média semanal, prova bimestral, trabalho, média bimestral e faltas.',
            'bimestre' => $bimestre,
            'ano_letivo' => $ano,
            'regra_id' => $regraId,
            'decimal_places' => ((int) ($evento['decimal_places'] ?? 2) === 1) ? 1 : 2,
            'colunas' => $chaves,
            'rotulos' => $rotulos,
            'linhas' => $linhas,
        ];
    }

    /**
     * @param array<string,mixed> $evento
     */
    public static function tituloResumoEvento(array $evento): string
    {
        $bimestre = isset($evento['bimestre']) && $evento['bimestre'] !== null ? (int) $evento['bimestre'] : 0;
        $ano = isset($evento['ano_letivo']) && $evento['ano_letivo'] !== null ? (int) $evento['ano_letivo'] : 0;
        if ($bimestre > 0 && $ano > 0) {
            if (!class_exists('PeriodoLetivo', false)) {
                require_once dirname(__DIR__) . '/Core/PeriodoLetivo.php';
            }
            return PeriodoLetivo::rotulo($ano, $bimestre) . ' ' . $ano;
        }
        $nome = trim((string) ($evento['regra_nome'] ?? $evento['nome'] ?? ''));

        return $nome !== '' ? $nome : 'Notas';
    }

    /**
     * @param array<string,mixed> $quadro
     * @return array<int,int>
     */
    public static function mapaFaltasDoQuadro(array $quadro, ?int $bimestre, ?int $anoLetivo = null): array
    {
        if ($bimestre === null || $bimestre < 0) {
            return [];
        }
        if ($anoLetivo !== null && $anoLetivo > 0) {
            $anoFicha = (int) ($quadro['ficha']['ano_letivo'] ?? 0);
            if ($anoFicha > 0 && $anoFicha !== $anoLetivo) {
                return [];
            }
        }
        $porMateria = [];
        foreach (is_array($quadro['grid'] ?? null) ? $quadro['grid'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mid = (int) (($row['linha']['materia_id'] ?? 0));
            $celulas = is_array($row['celulas'] ?? null) ? $row['celulas'] : [];
            $cel = is_array($celulas[$bimestre] ?? null) ? $celulas[$bimestre] : null;
            if ($mid <= 0 || $cel === null) {
                continue;
            }
            $faltas = $cel['faltas'] ?? null;
            if ($faltas === null || $faltas === '') {
                continue;
            }
            $porMateria[$mid] = (int) $faltas;
        }
        if ($porMateria === []) {
            return [];
        }
        $filhosPorPai = self::mapaFilhosPorPaiCached();
        if ($filhosPorPai === []) {
            return $porMateria;
        }
        foreach ($porMateria as $paiId => $faltas) {
            foreach ($filhosPorPai[$paiId] ?? [] as $filho) {
                $fid = (int) ($filho['id'] ?? 0);
                if ($fid > 0 && !isset($porMateria[$fid])) {
                    $porMateria[$fid] = $faltas;
                }
            }
        }

        return $porMateria;
    }

    /**
     * @param array<string,mixed> $painel
     * @param array<int,int> $faltasPorMateria
     * @return array<string,mixed>|null
     */
    private static function montarResumoDoPainelQuadro(array $painel, array $faltasPorMateria): ?array
    {
        $tabelas = is_array($painel['tabelas'] ?? null) ? $painel['tabelas'] : [];
        if ($tabelas === []) {
            return null;
        }
        $chaves = self::chavesResumoNotas();
        $linhas = [];
        $vistos = [];
        foreach ($tabelas as $tabela) {
            if (!is_array($tabela)) {
                continue;
            }
            $mapaCod = [];
            $codigosSemana = [];
            foreach (is_array($tabela['colunas'] ?? null) ? $tabela['colunas'] : [] as $col) {
                if (!is_array($col)) {
                    continue;
                }
                $cod = (string) ($col['codigo'] ?? '');
                if ($cod === '') {
                    continue;
                }
                if (self::codigoEhSemana($cod)) {
                    $codigosSemana[] = $cod;
                    continue;
                }
                $chave = self::chaveColunaResumo($col);
                if ($chave !== '' && !isset($mapaCod[$chave])) {
                    $mapaCod[$chave] = $cod;
                }
            }
            foreach (is_array($tabela['linhas'] ?? null) ? $tabela['linhas'] : [] as $lin) {
                if (!is_array($lin)) {
                    continue;
                }
                $mid = (int) ($lin['materia_id'] ?? 0);
                $nome = trim((string) ($lin['materia_nome'] ?? ''));
                $chaveLinha = $mid > 0 ? 'id:' . $mid : 'n:' . mb_strtolower($nome, 'UTF-8');
                if ($nome === '' || isset($vistos[$chaveLinha])) {
                    continue;
                }
                $vistos[$chaveLinha] = true;
                $vals = is_array($lin['celulas'] ?? null) ? $lin['celulas'] : [];
                $celulas = [];
                foreach ($chaves as $chave) {
                    if ($chave === 'faltas') {
                        $celulas[$chave] = $faltasPorMateria[$mid] ?? null;
                        continue;
                    }
                    $cod = $mapaCod[$chave] ?? '';
                    $val = $cod !== '' ? ($vals[$cod] ?? null) : null;
                    if ($chave === 'media_semanal' && !is_numeric($val)) {
                        $val = self::mediaDeCodigos($vals, $codigosSemana);
                    }
                    $celulas[$chave] = is_numeric($val) ? (float) $val : null;
                }
                $linhas[] = [
                    'materia_id' => $mid,
                    'materia_nome' => $nome,
                    'pai_nome' => isset($lin['pai_nome']) && $lin['pai_nome'] !== '' ? (string) $lin['pai_nome'] : null,
                    'celulas' => $celulas,
                ];
            }
        }
        if ($linhas === []) {
            return null;
        }
        $nomeQuadro = trim((string) ($painel['quadro']['nome'] ?? ''));

        return [
            'titulo' => $nomeQuadro !== '' ? $nomeQuadro : 'Notas',
            'subtitulo' => 'Média semanal, prova bimestral, trabalho, média bimestral e faltas.',
            'bimestre' => null,
            'ano_letivo' => null,
            'decimal_places' => 1,
            'colunas' => $chaves,
            'rotulos' => self::rotulosResumoNotas(),
            'linhas' => $linhas,
        ];
    }

    /**
     * @param array<string,mixed> $vals
     * @param list<string> $codigos
     */
    private static function mediaDeCodigos(array $vals, array $codigos): ?float
    {
        $nums = [];
        foreach ($codigos as $cod) {
            $v = $vals[$cod] ?? null;
            if (is_numeric($v)) {
                $nums[] = (float) $v;
            }
        }
        if ($nums === []) {
            return null;
        }

        return array_sum($nums) / count($nums);
    }

    /**
     * @param array<string,mixed> $notas
     * @param list<array<string,mixed>> $colunas
     */
    private static function mediaSemanalDasNotas(array $notas, array $colunas): ?float
    {
        $nums = [];
        foreach ($colunas as $col) {
            if (!is_array($col)) {
                continue;
            }
            $cod = (string) ($col['codigo'] ?? '');
            if ($cod === '' || (!self::colunaEhSemanaNq($col) && !self::codigoEhSemana($cod))) {
                continue;
            }
            $nq = self::celulaNq($notas, $cod);
            if ($nq['n'] !== null && $nq['q'] !== null && $nq['q'] > 0) {
                $nums[] = ((float) $nq['n'] / (float) $nq['q']) * 10.0;
                continue;
            }
            $v = $notas[$cod] ?? null;
            if (is_numeric($v)) {
                $nums[] = (float) $v;
            }
        }
        if ($nums === []) {
            return null;
        }

        return array_sum($nums) / count($nums);
    }

    /**
     * @return array<int, list<array<string,mixed>>>
     */
    private static function mapaFilhosPorPaiCached(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $ccPath = dirname(__DIR__) . '/Models/Education/ComponenteCurricular.php';
        if (!class_exists('ComponenteCurricular', false) && is_file($ccPath)) {
            require_once $ccPath;
        }
        if (!class_exists('ComponenteCurricular', false)) {
            $cache = [];
            return $cache;
        }
        try {
            $mapa = (new ComponenteCurricular())->mapaFilhosPorPai();
            $cache = is_array($mapa) ? $mapa : [];
        } catch (Throwable $e) {
            $cache = [];
        }

        return $cache;
    }
}
