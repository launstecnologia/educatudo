<?php

namespace App\Modulos\Lex\Services;

class LexExcecaoHttp extends \RuntimeException
{
    public function __construct(
        string $mensagem,
        public readonly int $httpStatus = 0,
        public readonly string $tipo = 'http'
    ) {
        parent::__construct($mensagem);
    }
}
