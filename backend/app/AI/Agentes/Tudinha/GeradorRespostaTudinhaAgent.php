<?php

namespace App\AI\Agentes\Tudinha;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

require_once __DIR__ . '/../../../Services/OpenAIService.php';

/**
 * EducaTudo - gera a resposta da Tudinha (sync ou stream via stream_callback no contexto).
 *
 * Contexto esperado (entrada):
 *   - messages_openai (array, obrigatório)
 *   - system_prompt (string, obrigatório)
 *   - stream_callback (callable|null, opcional — se presente, usa chatCompletionStream)
 * Contexto produzido (saída):
 *   - resposta_bruta (string)
 */
class GeradorRespostaTudinhaAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'GeradorRespostaTudinhaAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $messages = $contexto->get('messages_openai', []);
        $systemPrompt = trim((string) $contexto->get('system_prompt', ''));
        if (!is_array($messages) || empty($messages)) {
            throw new Exception('GeradorRespostaTudinhaAgent: messages_openai vazio');
        }
        if ($systemPrompt === '') {
            throw new Exception('GeradorRespostaTudinhaAgent: system_prompt vazio');
        }

        $openAIService = new \App\Services\OpenAIService();
        $apiKey = $openAIService->getOpenAIApiKey();
        if (empty($apiKey)) {
            throw new Exception('OPENAI_API_KEY não configurada. Configure via Admin > Dev Settings > Chaves de API ou no arquivo .env');
        }

        $streamCallback = $contexto->get('stream_callback');
        $respostaBruta = '';

        if (is_callable($streamCallback)) {
            $messagesWithSystem = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages
            );
            $requestData = [
                'model' => 'gpt-4o',
                'messages' => $messagesWithSystem,
                'max_tokens' => 2000,
                'temperature' => 0.7,
            ];
            $openAIService->chatCompletionStream($requestData, function ($chunk) use (&$respostaBruta, $streamCallback) {
                $respostaBruta .= $chunk;
                $streamCallback($chunk);
            });
        } else {
            $resultado = $openAIService->chatCompletion(
                $messages,
                $systemPrompt,
                'gpt-4o',
                0.7,
                2000
            );
            $respostaBruta = trim((string) ($resultado['resposta'] ?? ''));
        }

        if ($respostaBruta === '') {
            throw new Exception('GeradorRespostaTudinhaAgent: resposta vazia da OpenAI');
        }

        return $contexto->set('resposta_bruta', $respostaBruta);
    }
}
