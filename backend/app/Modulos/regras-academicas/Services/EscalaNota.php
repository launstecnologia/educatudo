<?php

namespace App\Modulos\RegrasAcademicas\Services;

use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

/**
 * Limites da nota lançada na ficha, conforme a escala da regra.
 */
class EscalaNota
{
    public static function maximo(string $escala): ?float
    {
        if ($escala === '100') {
            return 100.0;
        }
        if ($escala === '' || !isset(RegraAcademica::ESCALAS[$escala]) || RegraAcademica::numerica($escala)) {
            return 10.0;
        }

        return null;
    }

    /**
     * @return array{ok:bool,nota:?float,erro:string}
     */
    public static function ler(mixed $bruto): array
    {
        if ($bruto === null) {
            return ['ok' => true, 'nota' => null, 'erro' => ''];
        }
        $texto = trim(str_replace(',', '.', (string) $bruto));
        if ($texto === '') {
            return ['ok' => true, 'nota' => null, 'erro' => ''];
        }
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $texto)) {
            return ['ok' => false, 'nota' => null, 'erro' => 'Informe a nota com número. A vírgula vale como decimal.'];
        }

        return ['ok' => true, 'nota' => round((float) $texto, 2), 'erro' => ''];
    }

    public static function rejeitar(?float $nota, string $escala): ?string
    {
        if ($nota === null) {
            return null;
        }
        $max = self::maximo($escala);
        if ($max === null) {
            return null;
        }
        if ($nota < 0 || $nota > $max) {
            return 'A nota precisa estar entre 0 e ' . (int) $max . '.';
        }

        return null;
    }

    public static function aprovado(?float $nota, float $minima): ?bool
    {
        if ($nota === null) {
            return null;
        }

        return $nota >= $minima;
    }
}
