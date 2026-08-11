<?php

namespace App\AI\Agentes;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

require_once __DIR__ . '/../../Services/OpenAIService.php';

/**
 * EducaTudo - interpreta o pedido de imagem do aluno + contexto da conversa
 * e escreve um prompt detalhado/rigoroso pro gerador de imagem, em vez de
 * mandar a frase crua do aluno direto. Uma chamada de texto barata
 * (gpt-4o-mini) antes da chamada de imagem em si.
 *
 * Contexto esperado (entrada):
 *   - pedido_aluno (string, obrigatório)
 *   - contexto_conversa (string, opcional)
 * Contexto produzido (saída):
 *   - prompt_imagem (string)
 */
class ConstrutorPromptImagemAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'ConstrutorPromptImagemAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $pedidoAluno = trim((string) $contexto->get('pedido_aluno', ''));
        if ($pedidoAluno === '') {
            throw new Exception('ConstrutorPromptImagemAgent: pedido_aluno vazio');
        }
        $contextoConversa = trim((string) $contexto->get('contexto_conversa', ''));

        $openAIService = new \App\Services\OpenAIService();
        $apiKey = $openAIService->getOpenAIApiKey();
        if (empty($apiKey)) {
            throw new Exception('OPENAI_API_KEY não configurada.');
        }

        $promptSistema = file_get_contents(__DIR__ . '/../Prompts/construtor-prompt-imagem.md');
        if ($promptSistema === false || trim($promptSistema) === '') {
            throw new Exception('ConstrutorPromptImagemAgent: prompt.md não encontrado');
        }

        $mensagemUsuario = $contextoConversa !== ''
            ? "Contexto da conversa até agora:\n{$contextoConversa}\n\nPedido do aluno: {$pedidoAluno}"
            : "Pedido do aluno: {$pedidoAluno}";

        $resultado = $openAIService->chatCompletion(
            [['role' => 'user', 'content' => $mensagemUsuario]],
            trim($promptSistema),
            'gpt-4o-mini',
            0.4,
            1400
        );

        $promptImagem = trim((string) ($resultado['resposta'] ?? ''));
        if ($promptImagem === '') {
            throw new Exception('ConstrutorPromptImagemAgent: resposta vazia da OpenAI');
        }

        return $contexto->set('prompt_imagem', $promptImagem);
    }
}
