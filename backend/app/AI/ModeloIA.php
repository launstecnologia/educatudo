<?php

namespace App\AI;

/**
 * EducaTudo - seleção de modelo por nível/papel pro motor de questões.
 * GPT-5.6 (família Sol/Terra/Luna, OpenAI, lançada em 07/2026) — endpoint
 * chat/completions compatível, mesma integração já usada em OpenAIService,
 * só troca a string do model.
 */
class ModeloIA
{
    public const REVISAO = 'gpt-5.6-terra';
    public const REVISAO_CRITICA = 'gpt-5.6-sol';

    public static function porDificuldade(string $dificuldade): string
    {
        return match ($dificuldade) {
            'facil' => 'gpt-5.6-luna',
            'desafio' => 'gpt-5.6-sol',
            default => 'gpt-5.6-terra', // medio, dificil
        };
    }

    /**
     * Modelos "de raciocínio" (GPT-5.x, o1, o3) rejeitam o parâmetro
     * `max_tokens` do chat/completions clássico — exigem
     * `max_completion_tokens` no lugar (HTTP 400 "Unsupported parameter"
     * caso contrário). Lista por prefixo, não é exaustiva — revisitar a
     * cada modelo novo lançado pela OpenAI (ex.: um futuro gpt-6/o4
     * também tende a precisar disso).
     */
    public static function exigeMaxCompletionTokens(string $modelo): bool
    {
        return (bool) preg_match('/^(gpt-5|o1|o3)/', $modelo);
    }

    /**
     * Mesma família de modelos "de raciocínio" também rejeita qualquer
     * `temperature` diferente do default (1) — HTTP 400 "Unsupported value"
     * caso contrário. gpt-4o/gpt-4o-mini e afins continuam aceitando o
     * parâmetro normalmente.
     */
    public static function aceitaTemperaturaCustomizada(string $modelo): bool
    {
        return !self::exigeMaxCompletionTokens($modelo);
    }

    /**
     * Nos modelos de raciocínio os tokens de "pensamento" são cobrados do MESMO
     * limite de `max_completion_tokens` que a resposta visível. Com um limite
     * apertado (ex.: 4000 pra gerar 10 questões) o modelo consome tudo
     * raciocinando, a API devolve HTTP 200 com `content` vazio e
     * `finish_reason: length` — que é o "resposta vazia da OpenAI" que os
     * agentes viam. Aqui reservamos folga pro raciocínio em cima do tamanho
     * pedido pra resposta. gpt-4o e afins não têm essa etapa: usam o valor cru.
     */
    public const ORCAMENTO_RACIOCINIO_MAXIMO = 64000;

    public static function orcamentoTokens(string $modelo, int $tokensResposta): int
    {
        if (!self::exigeMaxCompletionTokens($modelo)) {
            return $tokensResposta;
        }
        return min(self::ORCAMENTO_RACIOCINIO_MAXIMO, max(16000, $tokensResposta * 4));
    }

    /**
     * Modelo pra tentar de novo quando a chamada original vier com HTTP 401
     * "insufficient permissions" — sinal de que a conta/organização OpenAI
     * ainda não tem acesso liberado àquele modelo específico (comum em
     * lançamentos novos, exige verificação de organização). gpt-5.6-sol é o
     * mais recente/restrito da família; cai pro gpt-5.6-terra, que já se
     * mostrou acessível. Retorna null quando não há fallback definido pro
     * modelo (não insiste indefinidamente).
     */
    public static function modeloFallback(string $modelo): ?string
    {
        return $modelo === 'gpt-5.6-sol' ? 'gpt-5.6-terra' : null;
    }
}
