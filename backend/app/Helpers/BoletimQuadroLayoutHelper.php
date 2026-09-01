<?php
/**
 * Layout do boletim no modelo "quadro semanal" (S1–S8 com N/Q, dois blocos de matérias).
 */
class BoletimQuadroLayoutHelper
{
    /** @return list<string> */
    public static function gruposPermitidos(): array
    {
        return ['b1', 'b2', 'b3', 'b4', 'final', 'quadro_a', 'quadro_b', 'quadro_comum'];
    }

    /** @return list<string> */
    public static function tiposPermitidos(): array
    {
        return ['media', 'faltas', 'rec', 'resultado', 'other', 'semana_nq', 'n', 'q', 'valor10', 'media_sem'];
    }

    public static function ehGrupoQuadro(string $grupo): bool
    {
        $g = strtolower(trim($grupo));
        return in_array($g, ['quadro_a', 'quadro_b', 'quadro_comum'], true);
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
        return (bool) preg_match('/^s[1-8]$/', $cod);
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
     * Parte as colunas em tabelas Bloco A / Bloco B (colunas comuns nas duas).
     *
     * @param list<array<string,mixed>> $cols
     * @return list<array{key:string,titulo:string,subtitulo:string,cols:list<array<string,mixed>>}>
     */
    public static function partirTabelas(array $cols): array
    {
        $blocoA = [];
        $blocoB = [];
        $mediaSem = [];
        $comum = [];
        $temQuadro = false;
        foreach ($cols as $c) {
            if (!is_array($c)) {
                continue;
            }
            $g = strtolower(trim((string) ($c['layout_group'] ?? '')));
            if ($g === 'quadro_a') {
                $temQuadro = true;
                $blocoA[] = $c;
            } elseif ($g === 'quadro_b') {
                $temQuadro = true;
                $blocoB[] = $c;
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
        if ($blocoA !== []) {
            $out[] = [
                'key' => 'a',
                'titulo' => 'Matérias Bloco A',
                'subtitulo' => 'Prova semanal',
                'cols' => array_merge($blocoA, $comum),
            ];
        }
        if ($blocoB !== []) {
            $out[] = [
                'key' => 'b',
                'titulo' => 'Matérias Bloco B',
                'subtitulo' => 'Prova semanal',
                'cols' => array_merge($blocoB, $comum),
            ];
        }

        return $out;
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
            if (preg_match('/^s[1-8]$/', $cod)) {
                return true;
            }
            $cfg = self::configDoComponente($c);
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $cfg['layout']['group'] ?? $c['layout_group'] ?? '')));
            if (in_array($g, ['quadro_a', 'quadro_b'], true)) {
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
     * Transforma a coluna única "Prova semanal" em S1–S8 (N/Q) + média semanal.
     *
     * @param list<array<string,mixed>> $componentes
     * @param list<int> $semanasA
     * @param list<int> $semanasB
     * @return list<array<string,mixed>>
     */
    public static function expandirComponentesParaQuadroSemanal(
        array $componentes,
        array $semanasA = [1, 3, 5, 7],
        array $semanasB = [2, 4, 6, 8]
    ): array {
        $semanasA = self::normalizarSemanasLista($semanasA, [1, 3, 5, 7]);
        $semanasB = self::normalizarSemanasLista($semanasB, [2, 4, 6, 8]);

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
        foreach ($semanasA as $s) {
            $out[] = self::clonarComponenteComoSemanaQuadro($template, (int) $s, 'quadro_a');
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
        $out = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $cfg = self::configDoComponente($c);
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $cfg['layout']['group'] ?? '')));
            if (preg_match('/^s([1-8])$/', $cod, $m)) {
                $s = (int) $m[1];
                $grupo = isset($impar[$s]) ? 'quadro_a' : (isset($par[$s]) ? 'quadro_b' : ($s % 2 === 1 ? 'quadro_a' : 'quadro_b'));
                $out[] = self::aplicarLayoutNoComponente($c, $grupo, 'semana_nq', $s);
                continue;
            }
            if ($g === 'quadro_a' || $g === 'quadro_b') {
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
        if ($semana >= 1 && $semana <= 8) {
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
        if (preg_match('/^s[1-8]$/', $cod)) {
            return true;
        }
        $cfg = self::configDoComponente($c);
        $grupo = strtolower(trim((string) ($cfg['layout_group'] ?? $cfg['layout']['group'] ?? $c['layout_group'] ?? '')));
        $tipo = strtolower(trim((string) ($cfg['layout_type'] ?? $cfg['layout']['type'] ?? $c['layout_type'] ?? '')));
        if ($tipo === 'semana_nq' || in_array($grupo, ['quadro_a', 'quadro_b'], true)) {
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
            if ($s >= 1 && $s <= 8) {
                $out[] = $s;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out !== [] ? $out : $fallback;
    }
}
