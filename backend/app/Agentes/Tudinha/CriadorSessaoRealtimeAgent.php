<?php

namespace App\Agentes\Tudinha;

use App\Agentes\AgenteIAInterface;
use App\Agentes\ContextoExecucao;
use Exception;


/**
 * EducaTudo - abre sessão OpenAI Realtime para conversa por voz.
 *
 * Contexto esperado (entrada):
 *   - instructions_voz (string, obrigatório)
 * Contexto produzido (saída):
 *   - client_secret (string)
 *   - expires_at (int|string)
 *   - model (string)
 */
class CriadorSessaoRealtimeAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'CriadorSessaoRealtimeAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $instructions = trim((string) $contexto->get('instructions_voz', ''));
        if ($instructions === '') {
            throw new Exception('CriadorSessaoRealtimeAgent: instructions_voz vazio');
        }

        $openAIService = new \App\Services\OpenAIService();
        $sessao = $openAIService->criarSessaoRealtime($instructions);

        return $contexto
            ->set('client_secret', $sessao['client_secret'] ?? '')
            ->set('expires_at', $sessao['expires_at'] ?? '')
            ->set('model', $sessao['model'] ?? '');
    }
}

if (!class_exists('CriadorSessaoRealtimeAgent', false) && class_exists(__NAMESPACE__ . '\\CriadorSessaoRealtimeAgent', false)) {
    class_alias(__NAMESPACE__ . '\\CriadorSessaoRealtimeAgent', 'CriadorSessaoRealtimeAgent');
}
