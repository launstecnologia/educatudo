<?php

namespace App\AI\Agentes\Questoes;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

require_once __DIR__ . '/../../../Services/OpenAIService.php';
require_once __DIR__ . '/../../ModeloIA.php';
require_once __DIR__ . '/RevisaoQuestaoTrait.php';

/**
 * EducaTudo - primeira camada de revisão: uma IA (gpt-5.6-terra) critica
 * cada questão gerada contra as regras de nível/Bloom do prompt de geração,
 * podendo aprovar, corrigir ou descartar. Roda depois do ValidadorQuestaoAgent
 * (que só valida estrutura por regra fixa, sem IA) — este agente é quem
 * avalia qualidade de conteúdo.
 *
 * Contexto esperado (entrada):
 *   - questoes_validadas (array, formato canônico)
 * Contexto produzido (saída):
 *   - questoes_validadas (mesmo array, com questões corrigidas/descartadas)
 *   - questoes_para_revisao_critica (array de índices que precisam de uma
 *     segunda opinião mais rigorosa — RevisorCriticoAgent processa só esses)
 */
class RevisorQuestaoAgent implements AgenteIAInterface
{
    use RevisaoQuestaoTrait;

    public function nome(): string
    {
        return 'RevisorQuestaoAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $questoes = $contexto->get('questoes_validadas', []);
        if (empty($questoes)) {
            return $contexto;
        }

        $openAIService = new \App\Services\OpenAIService();

        $revisadas = [];
        $paraRevisaoCritica = [];
        foreach (array_values($questoes) as $questao) {
            try {
                $decisao = $this->revisarQuestao($openAIService, \App\AI\ModeloIA::REVISAO, $questao, 'revisão padrão');
            } catch (\Throwable $e) {
                // Falha na revisão não deve derrubar a questão em si — mantém como veio do Gerador.
                $revisadas[] = $questao;
                continue;
            }

            if ($decisao['aprovada']) {
                $revisadas[] = $questao;
            } elseif ($decisao['versao_corrigida'] !== null) {
                $corrigida = $decisao['versao_corrigida'];
                // O revisor às vezes esquece de reincluir 'imagem' na versão
                // corrigida mesmo quando instruído — preserva a decisão
                // original do Planejador/Gerador em vez de perder a imagem.
                if (empty($corrigida['imagem']) && !empty($questao['imagem'])) {
                    $corrigida['imagem'] = $questao['imagem'];
                }
                $revisadas[] = $corrigida;
            } else {
                continue; // descarta — questão reprovada sem correção
            }

            $ultimoIndice = count($revisadas) - 1;
            $nivelQuestao = $revisadas[$ultimoIndice]['nivel_dificuldade'] ?? '';
            if ($decisao['precisa_segunda_opiniao'] || $nivelQuestao === 'desafio') {
                $paraRevisaoCritica[] = $ultimoIndice;
            }
        }

        if (empty($revisadas)) {
            throw new Exception('RevisorQuestaoAgent: todas as questões foram reprovadas na revisão');
        }

        return $contexto
            ->set('questoes_validadas', $revisadas)
            ->set('questoes_para_revisao_critica', $paraRevisaoCritica);
    }
}
