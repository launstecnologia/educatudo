<?php

namespace App\AI\Agentes\Questoes;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

/**
 * EducaTudo - validação semântica pós-geração. Não existia em NENHUM módulo
 * antes deste motor único (achado do discovery em
 * src/docs/assessment-discovery/10-problemas.md) — até aqui só havia parsing
 * sintático de JSON, nunca checagem de "isso é uma questão utilizável".
 * Determinístico, sem chamada de IA (rápido, não pesa na fila).
 *
 * Regras:
 *   - multipla_escolha/verdadeiro_falso: exatamente 1 alternativa correta,
 *     mínimo 2 alternativas, nenhum texto de alternativa vazio.
 *   - dissertativa: explicacao/gabarito não vazio.
 *   - enunciado nunca vazio.
 * Questões individuais inválidas são descartadas (não travam o pipeline por
 * causa de 1 questão ruim); se TODAS vierem inválidas, lança exceção.
 *
 * Contexto esperado (entrada):
 *   - questoes_geradas (array, formato canônico do GeradorQuestaoAgent)
 * Contexto produzido (saída):
 *   - questoes_validadas (array, subconjunto válido de questoes_geradas)
 *   - questoes_descartadas (int, quantas foram removidas por invalidez)
 */
class ValidadorQuestaoAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'ValidadorQuestaoAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $questoes = $contexto->get('questoes_geradas', []);
        if (!is_array($questoes) || empty($questoes)) {
            throw new Exception('ValidadorQuestaoAgent: nenhuma questão pra validar');
        }

        $validas = [];
        foreach ($questoes as $questao) {
            if (!is_array($questao)) {
                continue; // item malformado (ex.: string solta na lista) — descarta, não trava o pipeline
            }
            if ($this->questaoValida($questao)) {
                $validas[] = $questao;
            }
        }

        if (empty($validas)) {
            throw new Exception('ValidadorQuestaoAgent: todas as questões geradas falharam na validação');
        }

        return $contexto
            ->set('questoes_validadas', $validas)
            ->set('questoes_descartadas', count($questoes) - count($validas));
    }

    private function questaoValida(array $questao): bool
    {
        if (trim((string) ($questao['enunciado'] ?? '')) === '') {
            return false;
        }

        $tipo = $questao['tipo'] ?? '';

        if ($tipo === 'dissertativa') {
            return trim((string) ($questao['explicacao'] ?? '')) !== '';
        }

        if ($tipo === 'multipla_escolha' || $tipo === 'verdadeiro_falso') {
            $alternativas = $questao['alternativas'] ?? [];
            if (!is_array($alternativas) || count($alternativas) < 2) {
                return false;
            }

            $corretas = 0;
            foreach ($alternativas as $alt) {
                if (trim((string) ($alt['texto'] ?? '')) === '') {
                    return false;
                }
                if (!empty($alt['correta'])) {
                    $corretas++;
                }
            }

            return $corretas === 1;
        }

        return false;
    }
}
