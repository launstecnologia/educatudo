<?php

namespace App\AI\Agentes;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

require_once __DIR__ . '/../../Services/OpenAIService.php';

/**
 * EducaTudo - interpreta o pedido de flashcard do aluno + contexto da
 * conversa e extrai tópico/quantidade, pra alimentar a geração real do
 * baralho (FlashcardService, via EnfileiradorFlashcardAgent). Uma chamada
 * de texto barata (gpt-4o-mini) antes de enfileirar o job de geração.
 *
 * Contexto esperado (entrada):
 *   - pedido_aluno (string, obrigatório)
 *   - contexto_conversa (string, opcional)
 * Contexto produzido (saída):
 *   - topico_flashcard (string)
 *   - quantidade_flashcard (int, 1-30)
 */
class InterpretadorPedidoFlashcardAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'InterpretadorPedidoFlashcardAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $pedidoAluno = trim((string) $contexto->get('pedido_aluno', ''));
        if ($pedidoAluno === '') {
            throw new Exception('InterpretadorPedidoFlashcardAgent: pedido_aluno vazio');
        }
        $contextoConversa = trim((string) $contexto->get('contexto_conversa', ''));

        $openAIService = new \App\Services\OpenAIService();
        $apiKey = $openAIService->getOpenAIApiKey();
        if (empty($apiKey)) {
            throw new Exception('OPENAI_API_KEY não configurada.');
        }

        $promptSistema = file_get_contents(__DIR__ . '/../Prompts/interpretador-pedido-flashcard.md');
        if ($promptSistema === false || trim($promptSistema) === '') {
            throw new Exception('InterpretadorPedidoFlashcardAgent: prompt.md não encontrado');
        }

        $mensagemUsuario = $contextoConversa !== ''
            ? "Contexto da conversa até agora:\n{$contextoConversa}\n\nPedido do aluno: {$pedidoAluno}"
            : "Pedido do aluno: {$pedidoAluno}";

        $resultado = $openAIService->chatCompletion(
            [['role' => 'user', 'content' => $mensagemUsuario]],
            trim($promptSistema),
            'gpt-4o-mini',
            0.3,
            300
        );

        $resposta = trim((string) ($resultado['resposta'] ?? ''));
        if ($resposta === '') {
            throw new Exception('InterpretadorPedidoFlashcardAgent: resposta vazia da OpenAI');
        }

        // Tolera resposta vindo com cerca de código (```json ... ```), mesmo
        // o prompt pedindo JSON puro.
        if (preg_match('/\{.*\}/s', $resposta, $m)) {
            $resposta = $m[0];
        }

        $dados = json_decode($resposta, true);
        $topico = trim((string) ($dados['topico'] ?? ''));
        if ($topico === '') {
            throw new Exception('InterpretadorPedidoFlashcardAgent: não foi possível identificar o tópico.');
        }

        $quantidade = (int) ($dados['quantidade'] ?? 8);
        if ($quantidade < 1) {
            $quantidade = 1;
        } elseif ($quantidade > 30) {
            $quantidade = 30;
        }

        return $contexto
            ->set('topico_flashcard', $topico)
            ->set('quantidade_flashcard', $quantidade);
    }
}
