<?php

namespace App\AI\Agentes\Questoes;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;

require_once __DIR__ . '/../../../Services/ExamImageService.php';

/**
 * EducaTudo - wrapper fino em volta do ExamImageService JÁ EXISTENTE (usado
 * hoje só por Prova Online) — reaproveita 100% da lógica de decisão
 * QuickChart/SVG-GPT/Nano Banana+fallback gpt-image-2 sem duplicar nada.
 * Roda só se alguma questão tiver `imagem` não-nulo (o formato canônico do
 * GeradorQuestaoAgent já sai exatamente no shape que
 * ExamImageService::processarImagens() espera, sem tradução).
 *
 * Contexto esperado (entrada):
 *   - questoes_validadas (array, formato canônico)
 *   - disciplina, serie (string — usados no prompt de estilo da imagem)
 *   - config (array, config global da app — necessário pro MediaStorageService)
 * Contexto produzido (saída):
 *   - questoes_validadas (mesmo array, com `imagem.url` preenchido quando aplicável)
 */
class GeradorRecursoVisualAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'GeradorRecursoVisualAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $questoes = $contexto->get('questoes_validadas', []);
        $temImagem = false;
        foreach ($questoes as $q) {
            if (!empty($q['imagem'])) {
                $temImagem = true;
                break;
            }
        }
        if (!$temImagem) {
            return $contexto;
        }

        $config = (array) $contexto->get('config', []);
        $params = [
            'materia' => (string) $contexto->get('disciplina', ''),
            'serie' => (string) $contexto->get('serie', ''),
        ];

        $imageService = new \ExamImageService($config);
        $questoesComImagem = $imageService->processarImagens($questoes, $params);

        return $contexto->set('questoes_validadas', $questoesComImagem);
    }
}
