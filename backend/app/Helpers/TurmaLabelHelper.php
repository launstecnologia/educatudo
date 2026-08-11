<?php

/**
 * Formata rótulo de turma: "6º B do Ensino Fundamental II" ou "3ª A do Ensino Médio".
 */
class TurmaLabelHelper
{
    /**
     * @param array $row Campos: turma_nome, turma_serie, serie_nome, curso_nome (opcionais)
     */
    public static function formatListLabel(array $row): string
    {
        $turmaNome = trim((string) ($row['turma_nome'] ?? ''));
        if ($turmaNome === '') {
            return 'Sem turma';
        }

        $serieNome = trim((string) ($row['serie_nome'] ?? ''));
        $turmaSerie = trim((string) ($row['turma_serie'] ?? ''));

        $cursoNome = trim((string) ($row['curso_nome'] ?? ''));
        if ($cursoNome === '') {
            $cursoNome = self::inferCursoFromSerie($serieNome, $turmaSerie);
        }
        $cursoNome = self::normalizarCursoNome($cursoNome);

        [$numero, $letra] = self::parseNumeroLetra($turmaNome);

        if ($numero === null && $serieNome !== '' && preg_match('/(\d+)/u', $serieNome, $m)) {
            $numero = $m[1];
        }

        $ensinoMedio = self::isEnsinoMedio($cursoNome, $serieNome, $turmaSerie);

        if ($ensinoMedio && $serieNome !== '' && preg_match('/(\d+)/u', $serieNome, $mSerie)) {
            $numero = $mSerie[1];
        }

        if ($cursoNome !== '' && $numero !== null) {
            $turmaParte = self::montarParteTurma($numero, $letra, $ensinoMedio);

            return $turmaParte . ' do ' . $cursoNome;
        }

        $serie = $turmaSerie !== '' ? $turmaSerie : $serieNome;
        if ($serie !== '' && stripos($serie, $turmaNome) === false) {
            return self::normalizarTurmaNomeExibicao($turmaNome) . ' — ' . $serie;
        }

        return self::normalizarTurmaNomeExibicao($turmaNome);
    }

    private static function isEnsinoMedio(string $cursoNome, string $serieNome, string $turmaSerie): bool
    {
        foreach ([$cursoNome, $serieNome, $turmaSerie] as $texto) {
            if ($texto !== '' && preg_match('/ensino\s+m[eé]dio/iu', $texto)) {
                return true;
            }
            if ($texto !== '' && preg_match('/\d+\s*ª\s*série/iu', $texto)) {
                return true;
            }
        }

        return false;
    }

    private static function montarParteTurma(string $numero, ?string $letra, bool $ensinoMedio): string
    {
        $parte = $numero . ($ensinoMedio ? 'ª' : 'º');
        if ($letra !== null && $letra !== '') {
            $parte .= ' ' . $letra;
        }

        return $parte;
    }

    private static function normalizarTurmaNomeExibicao(string $turmaNome): string
    {
        [$numero, $letra] = self::parseNumeroLetra($turmaNome);
        if ($numero !== null) {
            return self::montarParteTurma($numero, $letra, false);
        }

        return $turmaNome;
    }

    private static function normalizarCursoNome(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return '';
        }

        if ($nome !== mb_strtolower($nome, 'UTF-8')) {
            return $nome;
        }

        $nome = mb_convert_case($nome, MB_CASE_TITLE, 'UTF-8');

        return preg_replace_callback(
            '/\b(Ii|Iii|Iv|Vi|Vii|Viii|Ix|Xi|Xii)\b/',
            static fn (array $m) => strtoupper($m[1]),
            $nome
        ) ?? $nome;
    }

    /**
     * @return array{0: ?string, 1: ?string} [numero, letra]
     */
    private static function parseNumeroLetra(string $turmaNome): array
    {
        $turmaNome = trim($turmaNome);
        if (preg_match('/^(\d+)\s*[º°ª]?\s*([A-Za-z])\s*$/u', $turmaNome, $m)) {
            return [$m[1], strtoupper($m[2])];
        }
        if (preg_match('/^(\d+)\s*[º°ª]?([A-Za-z])$/u', $turmaNome, $m)) {
            return [$m[1], strtoupper($m[2])];
        }
        if (preg_match('/^(\d+)\s*[º°ª]?\s*$/u', $turmaNome, $m)) {
            return [$m[1], null];
        }

        return [null, null];
    }

    private static function inferCursoFromSerie(string $serieNome, string $turmaSerie): string
    {
        foreach ([$serieNome, $turmaSerie] as $texto) {
            if ($texto === '') {
                continue;
            }
            if (preg_match('/ensino\s+m[eé]dio/iu', $texto)) {
                return 'Ensino Médio';
            }
            if (preg_match('/ensino\s+fundamental\s*ii/iu', $texto)) {
                return 'Ensino Fundamental II';
            }
            if (preg_match('/ensino\s+fundamental/iu', $texto)) {
                return 'Ensino Fundamental';
            }
            if (preg_match('/educa[cç][aã]o\s+infantil/iu', $texto)) {
                return 'Educação Infantil';
            }
        }

        return '';
    }
}
