<?php

namespace App\AI;

/**
 * EducaTudo - contexto compartilhado entre agentes de um pipeline de IA.
 *
 * Bag de dados simples (get/set) + um log de execução leve, pra dar
 * rastreabilidade sem precisar de um EventBus/persistência em disco.
 * Nenhum agente deve conhecer outro agente diretamente — só lê/escreve
 * chaves aqui.
 */
class ContextoExecucao
{
    private array $dados = [];
    private array $logs = [];

    public function get(string $chave, $padrao = null)
    {
        return $this->dados[$chave] ?? $padrao;
    }

    public function set(string $chave, $valor): self
    {
        $this->dados[$chave] = $valor;
        return $this;
    }

    public function has(string $chave): bool
    {
        return array_key_exists($chave, $this->dados);
    }

    public function registrarLog(string $mensagem): void
    {
        $this->logs[] = [
            'ts' => microtime(true),
            'mensagem' => $mensagem,
        ];
    }

    public function logs(): array
    {
        return $this->logs;
    }
}
