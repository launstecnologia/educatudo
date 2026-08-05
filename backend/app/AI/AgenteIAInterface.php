<?php

namespace App\AI;

/**
 * EducaTudo - contrato base de um agente de IA especializado.
 *
 * Um agente faz UMA coisa (interpretar um pedido, gerar uma imagem, revisar
 * um texto...) e recebe/devolve um ContextoExecucao — nunca chama outro
 * agente diretamente. Pipelines encadeiam agentes via ExecutorPipeline.
 *
 * Esta é a versão enxuta do conceito de "Agent Registry" — sem descoberta
 * automática por pasta, sem workflow.json declarativo, sem event bus. A
 * lista de agentes de cada pipeline fica no próprio código de quem chama
 * (ex.: StudentController). Cresce pra um registry completo quando existirem
 * pipelines o suficiente pra justificar (hoje: só geração de imagem).
 */
interface AgenteIAInterface
{
    /**
     * Nome curto do agente, usado em logs do ContextoExecucao.
     */
    public function nome(): string;

    /**
     * Executa a etapa do agente e devolve o contexto atualizado.
     * Deve lançar Exception em caso de falha — quem chama o ExecutorPipeline
     * decide se trata (fallback) ou deixa propagar.
     */
    public function executar(ContextoExecucao $contexto): ContextoExecucao;
}
