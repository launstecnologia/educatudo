<?php

namespace App\AI\Agentes\Questoes;

/**
 * EducaTudo - lógica compartilhada entre RevisorQuestaoAgent e
 * RevisorCriticoAgent: os dois fazem exatamente a mesma coisa (pedir pra um
 * modelo criticar 1 questão contra o prompt de revisão), só mudam o modelo
 * e o rótulo de rigor na mensagem. Trait em vez de duplicar ~30 linhas.
 */
trait RevisaoQuestaoTrait
{
    /**
     * @param array $questao Questão no formato canônico (enunciado, tipo, alternativas, ...)
     * @return array {aprovada: bool, motivo: string, precisa_segunda_opiniao: bool, versao_corrigida: array|null}
     */
    private function revisarQuestao(\App\Services\OpenAIService $openAIService, string $modelo, array $questao, string $rotuloRigor): array
    {
        static $promptSistema = null;
        if ($promptSistema === null) {
            $promptSistema = file_get_contents(__DIR__ . '/../../Prompts/Questoes/revisor-questao.md');
        }
        if ($promptSistema === false || trim($promptSistema) === '') {
            throw new \Exception('RevisaoQuestaoTrait: revisor-questao.md não encontrado');
        }

        $mensagemUsuario = "Nível de rigor: {$rotuloRigor}\n\nQuestão a revisar (formato canônico):\n"
            . json_encode($questao, JSON_UNESCAPED_UNICODE);

        // Falha na revisão não deve derrubar a questão — aprova por omissão.
        try {
            $resultado = $openAIService->chatCompletion(
                [['role' => 'user', 'content' => $mensagemUsuario]],
                trim($promptSistema),
                $modelo,
                0.3,
                2000
            );
        } catch (\Exception $e) {
            error_log('RevisaoQuestaoTrait: revisão indisponível — ' . $e->getMessage());
            return ['aprovada' => true, 'motivo' => 'Revisão indisponível.', 'precisa_segunda_opiniao' => false, 'versao_corrigida' => null];
        }

        $resposta = trim((string) ($resultado['resposta'] ?? ''));
        if ($resposta === '') {
            return ['aprovada' => true, 'motivo' => 'Revisão indisponível.', 'precisa_segunda_opiniao' => false, 'versao_corrigida' => null];
        }

        $resposta = preg_replace('/^```json\s*|\s*```$/m', '', $resposta);
        if (preg_match('/\{.*\}/s', $resposta, $m)) {
            $resposta = $m[0];
        }
        $resposta = preg_replace('/,(\s*[}\]])/', '$1', $resposta);

        $dados = json_decode($resposta, true);
        if (!is_array($dados)) {
            return ['aprovada' => true, 'motivo' => 'Resposta de revisão malformada.', 'precisa_segunda_opiniao' => false, 'versao_corrigida' => null];
        }

        return [
            'aprovada' => !empty($dados['aprovada']),
            'motivo' => (string) ($dados['motivo'] ?? ''),
            'precisa_segunda_opiniao' => !empty($dados['precisa_segunda_opiniao']),
            'versao_corrigida' => is_array($dados['versao_corrigida'] ?? null) ? $dados['versao_corrigida'] : null,
        ];
    }
}
