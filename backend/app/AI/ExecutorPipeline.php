<?php

namespace App\AI;

// Em CLI/cron o Composer às vezes não está no path do worker; garanta o
// contrato dos agentes antes de qualquer `implements AgenteIAInterface`.
require_once __DIR__ . '/AgenteIAInterface.php';
require_once __DIR__ . '/ContextoExecucao.php';

/**
 * EducaTudo - executa uma lista de agentes em sequência, passando o mesmo
 * ContextoExecucao adiante. Versão mínima do "Workflow Engine" — sem
 * workflow.json, sem retry/pause/resume automáticos (quem chama decide isso
 * via try/catch, mesmo padrão já usado no resto do projeto).
 */
class ExecutorPipeline
{
    /**
     * @param AgenteIAInterface[] $agentes
     */
    public static function executar(array $agentes, ContextoExecucao $contexto): ContextoExecucao
    {
        foreach ($agentes as $agente) {
            $contexto->registrarLog("Iniciando agente: {$agente->nome()}");
            try {
                $contexto = $agente->executar($contexto);
                $contexto->registrarLog("Agente concluído: {$agente->nome()}");
            } catch (\Throwable $e) {
                $contexto->registrarLog("Agente falhou: {$agente->nome()} — {$e->getMessage()}");
                throw $e;
            }
        }
        return $contexto;
    }
}
