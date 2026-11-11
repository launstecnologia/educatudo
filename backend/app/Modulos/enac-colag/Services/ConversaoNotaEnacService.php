<?php

namespace App\Modulos\EnacColag\Services;

/**
 * Converte pontuação ENAC (TOTAL) em nota 0–10 pela tabela de descarte 30%.
 */
class ConversaoNotaEnacService
{
    /**
     * Faixas em ordem decrescente de mínimo: [mínimo, nota].
     *
     * @var list<array{0:int,1:float}>
     */
    private const FAIXAS = [
        [54, 10.0],
        [51, 9.5],
        [48, 9.0],
        [45, 8.5],
        [42, 8.0],
        [39, 7.5],
        [36, 7.0],
        [33, 6.5],
        [30, 6.0],
        [27, 5.5],
        [24, 5.0],
        [20, 4.5],
        [0, 4.0],
    ];

    /**
     * @return array{nota:float,abaixo_de_20:bool}
     */
    public function converter(float $total): array
    {
        if (!is_finite($total)) {
            throw new \InvalidArgumentException('Pontuação ENAC inválida.');
        }
        if ($total < 0) {
            $total = 0.0;
        }
        foreach (self::FAIXAS as [$min, $nota]) {
            if ($total >= $min) {
                return [
                    'nota' => $nota,
                    'abaixo_de_20' => $total < 20,
                ];
            }
        }

        return [
            'nota' => 4.0,
            'abaixo_de_20' => true,
        ];
    }
}
