<?php
/**
 * Montagem de texto de questões ENEM a partir de context + alternatives_introduction.
 * Alinhado a src/database/migrations/041_import_enem_catalogo_master.sql (CASE do enunciado).
 */
class EnemQuestaoTextHelper
{
    /**
     * Corpo do enunciado (sem o título curto tipo "Questão N - ENEM YYYY").
     */
    public static function buildStem(?string $context, ?string $alternativesIntroduction): string
    {
        $c = self::normalize($context);
        $a = self::normalize($alternativesIntroduction);
        if ($c === '' && $a === '') {
            return '';
        }
        if ($c === '') {
            return $a;
        }
        if ($a === '') {
            return $c;
        }
        return $c . "\n\n" . $a;
    }

    /**
     * Texto exibido ao aluno: título (rótulo) + corpo, quando existirem.
     */
    public static function buildDisplayEnunciado(?string $title, ?string $context, ?string $alternativesIntroduction): string
    {
        $t = self::normalize($title);
        $stem = self::buildStem($context, $alternativesIntroduction);
        if ($t === '') {
            return $stem;
        }
        if ($stem === '') {
            return $t;
        }
        return $t . "\n\n" . $stem;
    }

    private static function normalize(?string $s): string
    {
        if ($s === null) {
            return '';
        }
        return trim($s);
    }
}
