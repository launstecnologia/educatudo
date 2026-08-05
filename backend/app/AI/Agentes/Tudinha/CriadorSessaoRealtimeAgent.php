<?php

namespace App\AI\Agentes\Tudinha;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

require_once __DIR__ . '/../../../Services/OpenAIService.php';

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
