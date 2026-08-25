<?php

namespace App\AI\Agentes\Questoes;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

/**
 * EducaTudo - validação semântica e estrutural pós-geração.
 * Determinístico, sem chamada de IA (rápido, não pesa na fila).
 *
 * Responsabilidades:
 *   1. Sanitização rigorosa: remoção de tags HTML brutas (<p>, <div>, etc.) de
 *      enunciados, alternativas e explicações.
 *   2. Validação estrutural: exatamente 1 alternativa correta, mínimo 2 opções,
 *      sem opções vazias ou textos duplicados.
 *   3. Distribuição balanceada de gabarito: embaralha/distribui a posição da
 *      alternativa correta entre A, B, C, D, E ao longo do lote para evitar
 *      vícios de gabarito (como 100% C) e sincroniza a explicação/gabarito.
 *
 * Contexto esperado (entrada):
 *   - questoes_geradas (array, formato canônico do GeradorQuestaoAgent)
 * Contexto produzido (saída):
 *   - questoes_validadas (array, subconjunto válido e normalizado)
 *   - questoes_descartadas (int, quantas foram removidas por invalidez)
 */
class ValidadorQuestaoAgent implements AgenteIAInterface
{
    private const LETRAS = ['A', 'B', 'C', 'D', 'E', 'F'];

    public function nome(): string
    {
        return 'ValidadorQuestaoAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $questoes = $contexto->get('questoes_validadas');
        if (!is_array($questoes) || empty($questoes)) {
            $questoes = $contexto->get('questoes_geradas', []);
        }

        if (!is_array($questoes) || empty($questoes)) {
            throw new Exception('ValidadorQuestaoAgent: nenhuma questão pra validar');
        }

        $validas = [];
        $indiceValida = 0;
        foreach ($questoes as $questao) {
            if (!is_array($questao)) {
                continue; // item malformado — descarta sem travar o pipeline
            }

            $normalizada = $this->sanitizarENormalizarQuestao($questao);
            $corrigida = $this->corrigirInconsistenciaGabarito($normalizada);

            // Filtro anti-clone: rejeita questões repetidas/clonadas no mesmo lote
            if ($this->ehQuestaoClone($corrigida, $validas)) {
                continue;
            }

            if ($this->questaoValida($corrigida)) {
                $validas[] = $corrigida;
                $indiceValida++;
            }
        }

        if (empty($validas)) {
            throw new Exception('ValidadorQuestaoAgent: todas as questões geradas falharam na validação');
        }

        // Garante e valida que a distribuição estatística de gabarito não viole limites (100% PHP)
        $validas = $this->validarEAssegurarDistribuicaoGabarito($validas);

        return $contexto
            ->set('questoes_validadas', $validas)
            ->set('questoes_descartadas', count($questoes) - count($validas));
    }

    /**
     * Detecta se a questão é um clone/duplicada de outra já aceita no mesmo lote.
     */
    private function ehQuestaoClone(array $novaQuestao, array $questoesValidadas): bool
    {
        $enunciadoNovo = mb_strtolower(trim((string) ($novaQuestao['enunciado'] ?? '')), 'UTF-8');
        if ($enunciadoNovo === '') {
            return false;
        }

        preg_match_all('/[0-9]+(?:[.,][0-9]+)?/u', $enunciadoNovo, $matchesNovo);
        $numerosNovo = array_unique($matchesNovo[0] ?? []);

        foreach ($questoesValidadas as $qAnterior) {
            $enunciadoAnt = mb_strtolower(trim((string) ($qAnterior['enunciado'] ?? '')), 'UTF-8');
            if ($enunciadoAnt === '') {
                continue;
            }

            // Checa similaridade direta de texto
            similar_text($enunciadoNovo, $enunciadoAnt, $percentualSimilaridade);
            if ($percentualSimilaridade >= 75.0) {
                return true;
            }

            // Checa se compartilha exatamente os mesmos números em enunciado parecido
            preg_match_all('/[0-9]+(?:[.,][0-9]+)?/u', $enunciadoAnt, $matchesAnt);
            $numerosAnt = array_unique($matchesAnt[0] ?? []);

            if (!empty($numerosNovo) && !empty($numerosAnt)) {
                $intersecao = array_intersect($numerosNovo, $numerosAnt);
                if (count($intersecao) >= 3 && count($intersecao) === count($numerosNovo)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Valida e assegura que a distribuição de gabarito respeite os limites estatísticos
     * estritamente em PHP, sem depender do LLM.
     */
    private function validarEAssegurarDistribuicaoGabarito(array $questoes): array
    {
        $totalMC = 0;
        foreach ($questoes as $q) {
            if (($q['tipo'] ?? '') === 'multipla_escolha' && !empty($q['alternativas'])) {
                $totalMC++;
            }
        }

        if ($totalMC < 2) {
            return $questoes;
        }

        // Aplica rotação estrita das alternativas para cada questão de múltipla escolha
        $ajustadas = [];
        $mcIdx = 0;
        foreach ($questoes as $q) {
            if (($q['tipo'] ?? '') === 'multipla_escolha' && !empty($q['alternativas'])) {
                $ajustadas[] = $this->balancearAlternativas($q, $mcIdx);
                $mcIdx++;
            } else {
                $ajustadas[] = $q;
            }
        }
        $questoes = $ajustadas;

        // Validação final de contagem estrita em PHP
        $gabaritos = [];
        foreach ($questoes as $q) {
            if (($q['tipo'] ?? '') === 'multipla_escolha' && !empty($q['alternativas'])) {
                foreach ($q['alternativas'] as $idx => $alt) {
                    if (!empty($alt['correta'])) {
                        $gabaritos[] = self::LETRAS[$idx] ?? 'A';
                        break;
                    }
                }
            }
        }

        $limiteMaximo = ($totalMC <= 4) ? 1 : (int) ceil($totalMC / 2.5);
        $contagem = array_count_values($gabaritos);
        foreach ($contagem as $letra => $quantidade) {
            if ($quantidade > $limiteMaximo) {
                throw new Exception("Distribuição inválida: letra {$letra} aparece {$quantidade} vezes no conjunto (máximo permitido: {$limiteMaximo}).");
            }
        }

        return $questoes;
    }

    /**
     * Corrige casos de gabarito invertido onde a fórmula da explicação calcula
     * o valor correto mas a IA marcou outra opção como 'correta: true' por alucinação,
     * ou onde o valor correto foi omitido das alternativas.
     */
    private function corrigirInconsistenciaGabarito(array $questao): array
    {
        if (($questao['tipo'] ?? '') !== 'multipla_escolha' || empty($questao['alternativas']) || empty($questao['explicacao'])) {
            return $questao;
        }

        $explicacao = $questao['explicacao'];
        $alternativas = $questao['alternativas'];

        // Procura por resultados de cálculo na explicação: "= [R$] XXX"
        if (preg_match_all('/=\s*(?:R\$\s*)?([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?|[0-9]+(?:\.[0-9]+)?%?)/u', $explicacao, $matches)) {
            $valoresCalculados = array_unique(array_map('trim', $matches[1]));

            // Verifica se a alternativa atualmente marcada como correta contém algum dos valores da fórmula
            $corretaAtualIdx = -1;
            foreach ($alternativas as $idx => $alt) {
                if (!empty($alt['correta'])) {
                    $corretaAtualIdx = $idx;
                    break;
                }
            }

            $corretaAtualValida = false;
            if ($corretaAtualIdx !== -1) {
                $txtCorreta = $alternativas[$corretaAtualIdx]['texto'] ?? '';
                foreach ($valoresCalculados as $val) {
                    if (mb_stripos($txtCorreta, $val) !== false) {
                        $corretaAtualValida = true;
                        break;
                    }
                }
            }

            // Se a atual marcada NÃO contém o valor da fórmula
            if (!$corretaAtualValida && !empty($valoresCalculados)) {
                $ultimoCalculado = end($valoresCalculados);
                $encontrou = false;
                foreach ($alternativas as $idx => $alt) {
                    $txtAlt = $alt['texto'] ?? '';
                    if (mb_stripos($txtAlt, $ultimoCalculado) !== false) {
                        // Inverte o gabarito para a alternativa que tem o valor calculado
                        foreach ($alternativas as $k => $v) {
                            $alternativas[$k]['correta'] = ($k === $idx);
                        }
                        $questao['alternativas'] = $alternativas;
                        $encontrou = true;
                        break;
                    }
                }

                // Se o valor calculado não constava em NENHUMA alternativa (caso da Q2), injeta o valor na correta
                if (!$encontrou && $corretaAtualIdx !== -1) {
                    $prefixoMonetario = (mb_stripos($explicacao, 'R$') !== false || mb_stripos($alternativas[$corretaAtualIdx]['texto'], 'R$') !== false);
                    $novoTexto = $prefixoMonetario ? ('R$ ' . $ultimoCalculado) : $ultimoCalculado;
                    $alternativas[$corretaAtualIdx]['texto'] = $novoTexto;
                    $questao['alternativas'] = $alternativas;
                }
            }
        }

        return $questao;
    }

    /**
     * Remove tags HTML brutas e espaços excessivos dos textos.
     */
    private function sanitizarENormalizarQuestao(array $questao): array
    {
        $questao['enunciado'] = $this->sanitizarTexto($questao['enunciado'] ?? '');
        $questao['explicacao'] = $this->sanitizarTexto($questao['explicacao'] ?? '');

        if (!empty($questao['alternativas']) && is_array($questao['alternativas'])) {
            $alternativasSanitizadas = [];
            foreach ($questao['alternativas'] as $alt) {
                if (!is_array($alt)) {
                    continue;
                }
                $alternativasSanitizadas[] = [
                    'texto' => $this->sanitizarTexto($alt['texto'] ?? ''),
                    'correta' => !empty($alt['correta']),
                ];
            }
            $questao['alternativas'] = $alternativasSanitizadas;
        }

        return $questao;
    }

    /**
     * Limpa tags HTML brutas (<p>, <div>, <span> etc.) e decodifica entidades.
     */
    private function sanitizarTexto(?string $str): string
    {
        if ($str === null || $str === '') {
            return '';
        }
        $str = html_entity_decode((string) $str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Remove tags HTML comuns vazadas por modelos de IA ou editores ricos
        $str = strip_tags($str);
        return trim($str);
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
            $textosVistos = [];
            foreach ($alternativas as $alt) {
                $txt = mb_strtolower(trim((string) ($alt['texto'] ?? '')), 'UTF-8');
                if ($txt === '') {
                    return false;
                }
                // Evita alternativas com textos idênticos
                if (isset($textosVistos[$txt])) {
                    return false;
                }
                $textosVistos[$txt] = true;

                if (!empty($alt['correta'])) {
                    $corretas++;
                }
            }

            return $corretas === 1;
        }

        return false;
    }

    /**
     * Embaralha as alternativas garantindo que a alternativa correta seja
     * distribuída de forma equilibrada entre as letras (A, B, C, D, E) no lote,
     * e remapeia as referências de letras na explicação.
     */
    private function balancearAlternativas(array $questao, int $ordemNoLote): array
    {
        if (($questao['tipo'] ?? '') !== 'multipla_escolha' || empty($questao['alternativas'])) {
            return $questao;
        }

        $alternativas = $questao['alternativas'];
        $totalAlts = count($alternativas);
        if ($totalAlts < 2) {
            return $questao;
        }

        // Identifica índice original da alternativa correta
        $indiceCorretaOriginal = -1;
        foreach ($alternativas as $idx => $alt) {
            if (!empty($alt['correta'])) {
                $indiceCorretaOriginal = $idx;
                break;
            }
        }
        if ($indiceCorretaOriginal === -1) {
            return $questao;
        }

        // Define a posição alvo da correta com base na ordem no lote (ex.: Q0->A, Q1->B, Q2->C, Q3->D, Q4->E...)
        $posicaoAlvo = $ordemNoLote % $totalAlts;

        // Monta os novos índices reordenados
        $indicesIncorretas = [];
        foreach ($alternativas as $idx => $alt) {
            if ($idx !== $indiceCorretaOriginal) {
                $indicesIncorretas[] = $idx;
            }
        }
        // Embaralha as incorretas
        shuffle($indicesIncorretas);

        $novoMapeamento = [];
        $cursorIncorreta = 0;
        for ($pos = 0; $pos < $totalAlts; $pos++) {
            if ($pos === $posicaoAlvo) {
                $novoMapeamento[$pos] = $indiceCorretaOriginal;
            } else {
                $novoMapeamento[$pos] = $indicesIncorretas[$cursorIncorreta++];
            }
        }

        // Constrói novo array de alternativas
        $novasAlternativas = [];
        for ($pos = 0; $pos < $totalAlts; $pos++) {
            $origIdx = $novoMapeamento[$pos];
            $novasAlternativas[] = $alternativas[$origIdx];
        }
        $questao['alternativas'] = $novasAlternativas;

        // Sincroniza a explicação com as novas posições das letras
        if (!empty($questao['explicacao'])) {
            $questao['explicacao'] = $this->sincronizarExplicacaoComNovasPosicoes(
                $questao['explicacao'],
                $novoMapeamento,
                $posicaoAlvo,
                $totalAlts
            );
        }

        return $questao;
    }

    /**
     * Atualiza as letras das alternativas e a linha de gabarito na explicação
     * para coincidir perfeitamente com a nova ordem das alternativas.
     */
    private function sincronizarExplicacaoComNovasPosicoes(
        string $explicacao,
        array $novoMapeamento,
        int $novaPosicaoCorreta,
        int $totalAlts
    ): string {
        $letras = self::LETRAS;
        $novaLetraCorreta = $letras[$novaPosicaoCorreta] ?? 'A';

        // Atualiza a linha final "Gabarito: [Letra]"
        $explicacao = preg_replace(
            '/\b(Gabarito:\s*)([A-Fa-f])\b/u',
            '${1}' . $novaLetraCorreta,
            $explicacao
        );

        // Se a explicação tiver blocos no padrão "**Alternativa X)** ...", reorganiza os blocos
        $blocosPorLetraOriginal = [];
        $padraoBloco = '/(?:\r?\n|^)\s*(?:\*\*Alternativa\s+([A-Fa-f])\)\*\*|\*\*Alternativa\s+([A-Fa-f])\*\*|\*\*Alternativa\s+([A-Fa-f])\)|Alternativa\s+([A-Fa-f])\))\s*([^\r\n]*(?:\r?\n(?!\s*(?:\*\*Alternativa|\bAlternativa|\*\*Gabarito|\bGabarito))[^\r\n]*)*)/iu';

        if (preg_match_all($padraoBloco, $explicacao, $matches, PREG_SET_ORDER)) {
            $primeiroMatchPos = mb_strpos($explicacao, trim($matches[0][0]));
            $intro = $primeiroMatchPos !== false ? trim(mb_substr($explicacao, 0, $primeiroMatchPos)) : '';

            // Mapeia o conteúdo (sem o prefixo de letra) de cada letra original
            foreach ($matches as $match) {
                $letra = strtoupper($match[1] ?: ($match[2] ?: ($match[3] ?: $match[4])));
                $corpoBloco = trim(ltrim($match[5], "* \t"));
                $idx = array_search($letra, $letras, true);
                if ($idx !== false) {
                    $blocosPorLetraOriginal[$idx] = $corpoBloco;
                }
            }

            // Se encontramos blocos para as alternativas, reconstruímos a seção na nova ordem
            if (count($blocosPorLetraOriginal) >= 2) {
                $novosBlocos = [];
                for ($pos = 0; $pos < $totalAlts; $pos++) {
                    $origIdx = $novoMapeamento[$pos] ?? $pos;
                    $novaLetra = $letras[$pos] ?? 'A';

                    if (isset($blocosPorLetraOriginal[$origIdx])) {
                        $corpo = $blocosPorLetraOriginal[$origIdx];
                        $novosBlocos[] = "**Alternativa {$novaLetra})** " . $corpo;
                    }
                }

                if (!empty($novosBlocos)) {
                    $resultado = '';
                    if ($intro !== '') {
                        $resultado .= $intro . "\n\n";
                    }
                    $resultado .= implode("\n\n", $novosBlocos) . "\n\n";
                    $resultado .= "**Gabarito: " . $novaLetraCorreta . "**";
                    return trim($resultado);
                }
            }
        }

        return $explicacao;
    }
}

