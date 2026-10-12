<?php

namespace App\Agentes\Questoes;

use App\Agentes\AgenteIAInterface;
use App\Agentes\ContextoExecucao;


/**
 * EducaTudo - busca referência bibliográfica REAL via SerpApi, pra alimentar
 * o GeradorQuestaoAgent com fonte de verdade em vez de deixar a IA inventar
 * uma citação. Roda logo depois do PlanejadorQuestaoAgent, só quando o
 * caller pediu explicitamente (opt-in, mesma filosofia do
 * com_recurso_visual) — best-effort, nunca lança exceção (SerpApiService já
 * é defensivo: sem key/rede/resultado só retorna array vazio).
 *
 * Contexto esperado (entrada):
 *   - com_referencia_real (bool, opcional, default false)
 *   - disciplina, assunto (string)
 * Contexto produzido (saída):
 *   - referencias_reais (array, pode ser vazio)
 */
class BuscadorReferenciaAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'BuscadorReferenciaAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        if (!$contexto->get('com_referencia_real', false)) {
            return $contexto;
        }

        $disciplina = trim((string) $contexto->get('disciplina', ''));
        $assunto = trim((string) $contexto->get('assunto', ''));
        $query = trim("{$disciplina} {$assunto}");

        $serpApiService = new \App\Services\SerpApiService();
        $referencias = $serpApiService->buscarReferencias($query, 3);

        return $contexto->set('referencias_reais', $referencias);
    }
}

if (!class_exists('BuscadorReferenciaAgent', false) && class_exists(__NAMESPACE__ . '\\BuscadorReferenciaAgent', false)) {
    class_alias(__NAMESPACE__ . '\\BuscadorReferenciaAgent', 'BuscadorReferenciaAgent');
}
