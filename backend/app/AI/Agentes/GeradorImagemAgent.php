<?php

namespace App\AI\Agentes;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

require_once __DIR__ . '/../../Services/OpenAIService.php';

/**
 * EducaTudo - gera a imagem em si a partir de um prompt já pronto
 * (prompt_imagem no contexto, normalmente produzido pelo
 * ConstrutorPromptImagemAgent). gpt-image-2 (OpenAI) como principal, Nano
 * Banana (Gemini) como reserva se a OpenAI falhar.
 *
 * Contexto esperado (entrada):
 *   - prompt_imagem (string, obrigatório)
 *   - aluno_id (int, obrigatório)
 *   - config (array, obrigatório — config global da app, usado pelo
 *     MediaStorageService pra salvar o arquivo)
 * Contexto produzido (saída):
 *   - image_url (string)
 *   - provedor_imagem (string) — rótulo legível de qual provedor gerou
 */
class GeradorImagemAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'GeradorImagemAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $prompt = trim((string) $contexto->get('prompt_imagem', ''));
        if ($prompt === '') {
            throw new Exception('GeradorImagemAgent: prompt_imagem vazio');
        }
        $alunoId = (int) $contexto->get('aluno_id', 0);
        if ($alunoId <= 0) {
            throw new Exception('GeradorImagemAgent: aluno_id inválido');
        }
        $config = (array) $contexto->get('config', []);

        $provedor = 'OpenAI (gpt-image-2)';
        try {
            $openAIService = new \App\Services\OpenAIService();
            $resultado = $openAIService->generateImage($prompt, $alunoId, $config);
        } catch (Exception $openAiImgError) {
            require_once __DIR__ . '/../../Services/NanoBananaService.php';
            $nanoBananaService = new \App\Services\NanoBananaService();
            $resultado = $nanoBananaService->gerarImagem($prompt, [
                'width' => 1024,
                'height' => 1024,
                'style' => 'realistic',
                'quality' => 'standard',
            ]);
            $provedor = 'Google (Nano Banana)';
        }

        if (empty($resultado['image_url'])) {
            throw new Exception('GeradorImagemAgent: nenhum provedor retornou image_url');
        }

        return $contexto
            ->set('image_url', $resultado['image_url'])
            ->set('provedor_imagem', $provedor);
    }
}
