<?php

namespace App\Agentes\Questoes;

use App\Agentes\AgenteIAInterface;
use App\Agentes\ContextoExecucao;
use Exception;


/**
 * EducaTudo - segunda camada de revisão (segunda opinião), mais rigorosa,
 * usando gpt-5.6-sol. Roda só nas questões que o RevisorQuestaoAgent marcou
 * em `questoes_para_revisao_critica` (incerteza sinalizada por ele, ou
 * nível "desafio" — sempre passa por aqui).
 *
 * Contexto esperado (entrada):
 *   - questoes_validadas (array, formato canônico)
 *   - questoes_para_revisao_critica (array de índices em questoes_validadas)
 * Contexto produzido (saída):
 *   - questoes_validadas (mesmo array, com as questões marcadas revisadas de novo)
 */
class RevisorCriticoAgent implements AgenteIAInterface
{
    use RevisaoQuestaoTrait;

    public function nome(): string
    {
        return 'RevisorCriticoAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $indices = $contexto->get('questoes_para_revisao_critica', []);
        if (empty($indices)) {
            return $contexto;
        }

        $questoes = $contexto->get('questoes_validadas', []);
        $openAIService = new \App\Services\OpenAIService();

        $descartarIndices = [];
        foreach ($indices as $indice) {
            if (!isset($questoes[$indice])) {
                continue;
            }
            try {
                $decisao = $this->revisarQuestao(
                    $openAIService,
                    \App\Agentes\ModeloIA::REVISAO_CRITICA,
                    $questoes[$indice],
                    'segunda opinião — seja mais rigoroso que a revisão anterior'
                );
            } catch (\Throwable $e) {
                continue; // mantém a questão como veio da primeira revisão
            }

            if ($decisao['aprovada']) {
                continue;
            }
            if ($decisao['versao_corrigida'] !== null) {
                $corrigida = $decisao['versao_corrigida'];
                // Mesma proteção do RevisorQuestaoAgent: não deixar a segunda
                // opinião apagar a imagem decidida anteriormente.
                if (empty($corrigida['imagem']) && !empty($questoes[$indice]['imagem'])) {
                    $corrigida['imagem'] = $questoes[$indice]['imagem'];
                }
                $questoes[$indice] = $corrigida;
            } else {
                $descartarIndices[] = $indice;
            }
        }

        foreach ($descartarIndices as $indice) {
            unset($questoes[$indice]);
        }
        $questoes = array_values($questoes);

        if (empty($questoes)) {
            throw new Exception('RevisorCriticoAgent: todas as questões foram reprovadas na segunda opinião');
        }

        return $contexto->set('questoes_validadas', $questoes);
    }
}

if (!class_exists('RevisorCriticoAgent', false) && class_exists(__NAMESPACE__ . '\\RevisorCriticoAgent', false)) {
    class_alias(__NAMESPACE__ . '\\RevisorCriticoAgent', 'RevisorCriticoAgent');
}
