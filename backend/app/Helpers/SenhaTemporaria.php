<?php

namespace App\Helpers;

/**
 * Senha inicial entregue uma vez à secretaria. Não é um segredo fixo do sistema.
 */
class SenhaTemporaria
{
    public static function gerar(): string
    {
        $grupos = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '!@#$%&*',
        ];
        $senha = '';
        foreach ($grupos as $grupo) {
            $senha .= $grupo[random_int(0, strlen($grupo) - 1)];
        }
        $todos = implode('', $grupos);
        $ultimo = strlen($todos) - 1;
        for ($i = 0; $i < 8; $i++) {
            $senha .= $todos[random_int(0, $ultimo)];
        }
        $chars = str_split($senha);
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
