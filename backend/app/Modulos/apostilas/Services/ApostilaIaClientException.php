<?php

namespace App\Modulos\Apostilas\Services;
/**
 * EducaTudo - Exceção lançada por ApostilaIaClientService em falhas de
 * conexão ou respostas não-2xx do microserviço Python "IA da Apostila".
 */

if (!class_exists('ApostilaIaClientException', false)) {
class ApostilaIaClientException extends \RuntimeException
{
    public function __construct(string $message, int $httpCode = 0)
    {
        parent::__construct($message, $httpCode);
    }

    public function getHttpCode(): int
    {
        return $this->getCode();
    }
}
}

if (!class_exists('ApostilaIaClientException', false) && class_exists(__NAMESPACE__ . '\\ApostilaIaClientException', false)) {
    class_alias(__NAMESPACE__ . '\\ApostilaIaClientException', 'ApostilaIaClientException');
}
