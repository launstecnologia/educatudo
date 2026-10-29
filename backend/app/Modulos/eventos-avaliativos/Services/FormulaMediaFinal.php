<?php

namespace App\Modulos\EventosAvaliativos\Services;

/**
 * Média final do boletim a partir da fórmula do layout. Sem eval.
 */
class FormulaMediaFinal
{
    /**
     * @param array<int,float|null> $medias
     */
    public static function calcular(?string $formula, array $medias): ?float
    {
        $numeros = [];
        foreach ($medias as $periodo => $valor) {
            if ($valor === null) {
                continue;
            }
            $numeros[(int) $periodo] = (float) $valor;
        }
        $formula = trim((string) $formula);
        if ($formula === '') {
            if ($numeros === []) {
                return null;
            }

            return array_sum($numeros) / count($numeros);
        }
        if (!preg_match('/^[0-9m+*\/().,\s-]+$/u', $formula)) {
            return null;
        }
        if (!preg_match_all('/m(\d+)/', $formula, $achados)) {
            return null;
        }
        $expr = $formula;
        $tokens = array_unique($achados[0]);
        usort($tokens, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($tokens as $token) {
            $periodo = (int) substr($token, 1);
            if (!array_key_exists($periodo, $numeros)) {
                return null;
            }
            $expr = str_replace($token, (string) $numeros[$periodo], $expr);
        }
        $expr = str_replace([',', ' '], ['.', ''], $expr);
        if (!preg_match('/^[0-9+\-*\/().]+$/', $expr)) {
            return null;
        }

        return self::avaliarExpressao($expr);
    }

    public static function exibir(?float $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return number_format($valor, 1, ',', '');
    }

    private static function avaliarExpressao(string $expr): ?float
    {
        $i = 0;
        $tamanho = strlen($expr);
        $resultado = self::avaliarTrecho($expr, $i, $tamanho);
        if ($resultado === null || $i !== $tamanho || !is_finite($resultado)) {
            return null;
        }

        return $resultado;
    }

    private static function avaliarTrecho(string $expr, int &$i, int $tamanho): ?float
    {
        $valor = self::lerTermo($expr, $i, $tamanho);
        if ($valor === null) {
            return null;
        }
        while ($i < $tamanho && ($expr[$i] === '+' || $expr[$i] === '-')) {
            $op = $expr[$i];
            $i++;
            $prox = self::lerTermo($expr, $i, $tamanho);
            if ($prox === null) {
                return null;
            }
            $valor = $op === '+' ? $valor + $prox : $valor - $prox;
        }

        return $valor;
    }

    private static function lerTermo(string $expr, int &$i, int $tamanho): ?float
    {
        $valor = self::lerFator($expr, $i, $tamanho);
        if ($valor === null) {
            return null;
        }
        while ($i < $tamanho && ($expr[$i] === '*' || $expr[$i] === '/')) {
            $op = $expr[$i];
            $i++;
            $prox = self::lerFator($expr, $i, $tamanho);
            if ($prox === null || ($op === '/' && abs($prox) < 0.0000001)) {
                return null;
            }
            $valor = $op === '*' ? $valor * $prox : $valor / $prox;
        }

        return $valor;
    }

    private static function lerFator(string $expr, int &$i, int $tamanho): ?float
    {
        if ($i >= $tamanho) {
            return null;
        }
        if ($expr[$i] === '(') {
            $i++;
            $valor = self::avaliarTrecho($expr, $i, $tamanho);
            if ($valor === null || $i >= $tamanho || $expr[$i] !== ')') {
                return null;
            }
            $i++;

            return $valor;
        }
        $inicio = $i;
        if ($expr[$i] === '-' || $expr[$i] === '+') {
            $i++;
        }
        $temDigito = false;
        while ($i < $tamanho && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
            $temDigito = $temDigito || ctype_digit($expr[$i]);
            $i++;
        }
        if (!$temDigito) {
            return null;
        }

        return (float) substr($expr, $inicio, $i - $inicio);
    }
}
