<?php

namespace App\Modulos\BoletimColag\Services;

/**
 * Modelo do boletim do Colag.
 * Bloco A: semanas ímpares. Bloco B: semanas pares. Médias no bloco de cálculo.
 * Outra escola não entra neste modelo — inclusive com Evento Avaliativo ligado.
 */
class BoletimColagModelo
{
    public static function ativo(): bool
    {
        if (!defined('TENANT_SLUG')) {
            return false;
        }

        return strtolower(trim((string) TENANT_SLUG)) === 'colag';
    }

    /**
     * @return array{a:list<int>,b:list<int>}
     */
    public static function semanas(): array
    {
        return [
            'a' => [1, 3, 5],
            'b' => [2, 4, 6],
        ];
    }
}
