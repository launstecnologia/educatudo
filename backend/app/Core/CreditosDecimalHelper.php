<?php
/**
 * Normalização de valores de TudiCoins (créditos) para precificação, carteira e UI.
 * Na interface, TudiCoins são tratados como dinheiro (formato BR + prefixo TC$).
 */
class CreditosDecimalHelper
{
    public const SCALE = 4;

    /** Prefixo de exibição (como R$ / $). */
    public const PREFIXO = 'TC$';

    /** Conversão fixa: 1 TudiCoin = R$ 0,20 */
    public const BRL_POR_UNIDADE = 0.20;

    /**
     * Valor vindo do POST (aceita vírgula ou ponto; remove prefixo/espaços).
     * Com vírgula: formato BR (1.000,50). Sem vírgula: ponto decimal (1.5).
     */
    public static function parsePost(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        $s = trim((string) $value);
        $s = preg_replace('/^TC\$\s*/i', '', $s) ?? $s;
        $s = str_replace(' ', '', $s);
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        if ($s === '' || !is_numeric($s)) {
            return 0.0;
        }
        return round(max(0.0, (float) $s), self::SCALE);
    }

    /**
     * Valor vindo do banco ou config (string/float).
     * Valores negativos viram $default (útil para custos/preços que não devem ser negativos).
     */
    public static function fromScalar(mixed $value, float $default = 1.0): float
    {
        if ($value === null || $value === '') {
            return round($default, self::SCALE);
        }
        if (!is_numeric($value)) {
            return round($default, self::SCALE);
        }
        $n = (float) $value;
        return round($n >= 0 ? $n : $default, self::SCALE);
    }

    /**
     * Valor com sinal preservado (movimentações: consumo negativo, recarga/compra positiva).
     */
    public static function fromSignedScalar(mixed $value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return round($default, self::SCALE);
        }
        if (!is_numeric($value)) {
            return round($default, self::SCALE);
        }
        return round((float) $value, self::SCALE);
    }

    /**
     * Texto para atributo value de input (ponto decimal, sem milhar).
     */
    public static function formatInput(float $v): string
    {
        $v = round($v, self::SCALE);
        if (abs($v - round($v)) < 1e-9) {
            return (string) (int) round($v);
        }
        return rtrim(rtrim(number_format($v, self::SCALE, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * Número no formato brasileiro (1.000,00) sem prefixo.
     */
    public static function formatNumero(float $v, int $casas = 2): string
    {
        return number_format(round($v, $casas), $casas, ',', '.');
    }

    /**
     * Exibição monetária: TC$ 1.000,00
     */
    public static function formatDisplay(float $v, bool $withPrefix = true): string
    {
        $num = self::formatNumero($v, 2);
        return $withPrefix ? (self::PREFIXO . ' ' . $num) : $num;
    }

    /**
     * Equivalente em reais (1 TudiCoin = R$ 0,20).
     */
    public static function toReais(float $tudicoins): float
    {
        return round($tudicoins * self::BRL_POR_UNIDADE, 2);
    }

    /**
     * Ex.: R$ 20,00
     */
    public static function formatReaisFromTudicoins(float $tudicoins): string
    {
        return 'R$ ' . self::formatNumero(self::toReais($tudicoins), 2);
    }
}
