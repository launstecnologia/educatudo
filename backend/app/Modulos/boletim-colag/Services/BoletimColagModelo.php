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

    /**
     * Peças do demonstrativo oficial. As semanas ficam no quadro, não nesta lista.
     *
     * @return list<string>
     */
    public static function pecas(): array
    {
        return ['semanal', 'bimestral', 'jornada', 'enac', 'recuperacao'];
    }

    /**
     * Colunas depois do bloco N/Q, na ordem do boletim oficial.
     *
     * @return list<string>
     */
    public static function colunas(): array
    {
        return ['prova_bim', 'jornada', 'enac', 'media_bim', 'rec', 'media_final', 'faltas'];
    }

    /**
     * @return array<string,string>
     */
    public static function nomes(): array
    {
        return [
            'media_sem' => 'Média Sem',
            'prova_bim' => 'Prova Bim',
            'jornada' => 'Jornada do aluno',
            'enac' => 'ENAC',
            'media_bim' => 'Média Bim',
            'rec' => 'Recuperação Semestral',
            'media_final' => 'Resultado Final',
            'faltas' => 'Faltas',
        ];
    }
}
