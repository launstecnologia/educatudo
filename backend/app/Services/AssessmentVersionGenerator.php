<?php

require_once __DIR__ . '/../Models/Exams/Exam.php';
require_once __DIR__ . '/../Models/EducaInclui/VersaoAdaptada.php';
require_once __DIR__ . '/../Models/EducaInclui/VersaoAdaptadaLog.php';
require_once __DIR__ . '/AssessmentHasher.php';
require_once __DIR__ . '/../Helpers/CatalogoRegraMascara.php';
require_once __DIR__ . '/MascaraResolver.php';

if (!class_exists('AssessmentVersionGenerator')) {
/**
 * EducaInclui — gera uma versão adaptada (clone) de uma prova para um aluno.
 *
 * Estratégia de entrega: "native redirect". O clone é uma prova normal criada
 * com ativo=0 e liberada=0, portanto invisível em TODAS as listagens (todas
 * exigem ativo=1 AND liberada=1) sem precisar alterar nenhuma query. Só o dono
 * a alcança via redirect a partir da prova original (com checagem de ownership
 * em ExamController::realizar()).
 *
 * Normalização de nota: proporcional, resolvida AQUI (na geração) escalando o
 * `valor` de cada questão mantida para que a soma bata com a prova original.
 * Assim o motor de correção (Exam::finalizarProva) não precisa ser alterado.
 */
class AssessmentVersionGenerator
{
    private $exam;
    private $versions;
    private $log;
    private $hasher;

    public function __construct()
    {
        $this->exam = new Exam();
        $this->versions = new VersaoAdaptada();
        $this->log = new VersaoAdaptadaLog();
        $this->hasher = new AssessmentHasher($this->exam);
    }

    /**
     * Garante que exista um rascunho de versão adaptada para (prova, aluno).
     * Não regenera se já houver uma versão (qualquer status).
     *
     * @param array<string,string> $rules
     * @return array<string,mixed>|null  a versão (existente ou recém-criada) ou null se não aplicável
     */
    public function ensureDraft(int $originalProvaId, int $alunoId, int $mascaraId, array $rules): ?array
    {
        $reduce = (int) ($rules['reduce_question_count'] ?? 0);
        $keepOptions = \MascaraResolver::reduceOptionsKeep($rules);
        $aiRewrite = \CatalogoRegraMascara::hasAiRewriteRule($rules);
        if ((!$aiRewrite && $reduce < 1 && $keepOptions < 2) || $originalProvaId <= 0 || $alunoId <= 0) {
            return null;
        }

        $existing = $this->versions->getAnyFor($originalProvaId, $alunoId);
        if ($existing) {
            return $existing;
        }

        $prova = $this->exam->findById($originalProvaId);
        if (!$prova) {
            return null;
        }

        $questoes = $this->exam->getQuestoes($originalProvaId);
        $total = count($questoes);
        if ($total < 1) {
            return null;
        }

        // Define quantas manter: reduz se pedido; senão mantém todas (reescrita por IA).
        $keep = ($reduce >= 1) ? min($reduce, $total) : $total;
        if (!$aiRewrite && $keep >= $total && $keepOptions < 2) {
            // Nada a reduzir e sem reescrita: entrega a prova original normalmente.
            return null;
        }

        // Seleção determinística por aluno (estável); reordena pela ordem original.
        // Quando keep == total, retorna todas na ordem original.
        $selecionadas = $this->selecionarQuestoes($questoes, $keep, $alunoId);

        // TudiCoins: debita carteira da escola antes de gerar (por prova gerada).
        $creditsRef = 'prova:' . $originalProvaId . ':aluno:' . $alunoId;
        $creditsModulo = 'educainclui_gerar_prova';
        $debitou = false;
        try {
            require_once __DIR__ . '/CreditosService.php';
            require_once __DIR__ . '/../Core/CreditosModuleRegistry.php';
            $creditos = new \App\Services\CreditosService();
            if (!$creditos->podeConsumir('escola', \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, $creditsModulo)) {
                throw new \RuntimeException('TudiCoins insuficientes na carteira da escola para gerar prova adaptada.');
            }
            $creditos->consumirEscola($creditsModulo, $creditsRef);
            $debitou = true;
        } catch (Throwable $eCred) {
            error_log('AssessmentVersionGenerator TudiCoins: ' . $eCred->getMessage());
            return null; // fail-closed: sem TudiCoins / erro de carteira → não gera
        }

        $somaOriginal = 0.0;
        foreach ($questoes as $q) {
            $somaOriginal += (float) ($q['valor'] ?? 0);
        }
        $somaSelecionada = 0.0;
        foreach ($selecionadas as $q) {
            $somaSelecionada += (float) ($q['valor'] ?? 0);
        }
        $fator = ($somaSelecionada > 0 && $somaOriginal > 0) ? ($somaOriginal / $somaSelecionada) : 1.0;

        try {
            $adaptedProvaId = (int) $this->exam->create([
                'professor_id' => $prova['professor_id'],
                'materia_id' => $prova['materia_id'],
                'turma_id' => null,
                'titulo' => mb_substr((string) $prova['titulo'], 0, 200) . ' (Adaptada — EducaInclui)',
                'descricao' => $prova['descricao'] ?? null,
                'data_inicio' => $prova['data_inicio'],
                'data_fim' => $prova['data_fim'],
                'tempo_limite' => $prova['tempo_limite'] ?? null,
                'valor_total' => $prova['valor_total'] ?? 100.00,
                'mostrar_resultado' => $prova['mostrar_resultado'] ?? 1,
                'permite_correcao' => $prova['permite_correcao'] ?? 0,
                'liberar_resultado' => $prova['liberar_resultado'] ?? 'imediatamente',
                'ativo' => 0,      // invisível em todas as listagens
                'liberada' => 0,   // invisível em todas as listagens
                'status' => 'aprovada',
            ]);

            $ordem = 0;
            foreach ($selecionadas as $q) {
                $alternativas = [];
                if (($q['tipo'] ?? '') === 'multipla_escolha') {
                    foreach ($this->exam->getAlternativas((int) $q['id']) as $a) {
                        $alternativas[] = [
                            'id' => (int) ($a['id'] ?? 0),
                            'texto' => $a['texto'] ?? '',
                            'correta' => (int) ($a['correta'] ?? 0),
                            'ordem' => (int) ($a['ordem'] ?? 0),
                        ];
                    }
                    if ($keepOptions >= 2 && count($alternativas) > $keepOptions) {
                        $alternativas = $this->reduceAlternativas($alternativas, $keepOptions, $alunoId);
                    }
                }
                $this->exam->addQuestao($adaptedProvaId, [
                    'enunciado' => $q['enunciado'] ?? '',
                    'imagem_url' => $q['imagem_url'] ?? null,
                    'tipo' => $q['tipo'] ?? 'multipla_escolha',
                    'valor' => round((float) ($q['valor'] ?? 1) * $fator, 2),
                    'nivel_dificuldade' => $q['nivel_dificuldade'] ?? null,
                    'ordem' => $ordem++,
                    'explicacao' => $q['explicacao'] ?? null,
                    'alternativas' => $alternativas,
                ]);
            }

            $hash = $this->hasher->hashProva($originalProvaId);
            $versionId = $this->versions->create([
                'prova_id' => $originalProvaId,
                'aluno_id' => $alunoId,
                'mascara_id' => $mascaraId,
                'tipo_versao' => 'significativa',
                'hash_prova_origem' => $hash,
                'adapted_prova_id' => $adaptedProvaId,
                'rules' => $rules,
                'status_aprovacao' => 'pendente',
            ]);

            $this->log->record('versao_gerada', [
                'versao_adaptada_id' => $versionId,
                'mascara_id' => $mascaraId,
                'aluno_id' => $alunoId,
                'prova_id' => $originalProvaId,
                'user_id' => $alunoId,
                'details' => [
                    'adapted_prova_id' => $adaptedProvaId,
                    'reduce_question_count' => $reduce,
                    'reduce_options' => $keepOptions,
                    'ai_rewrite' => $aiRewrite,
                    'fator_nota' => $fator,
                ],
            ]);

            // Reescrita de enunciado por IA é assíncrona (regra do projeto: IA > 2s não bloqueia a request).
            if ($aiRewrite) {
                try {
                    require_once __DIR__ . '/AIJobService.php';
                    \App\Services\AIJobService::enqueue('inclusao_reescrever_versao', [
                        'version_id' => $versionId,
                        'adapted_prova_id' => $adaptedProvaId,
                        'mascara_id' => $mascaraId,
                        'aluno_id' => $alunoId,
                        'prova_id' => $originalProvaId,
                        'reduce_options_keep' => $keepOptions,
                        'modes' => [
                            'simplified_instructions' => !empty($rules['simplified_instructions']),
                            'shorten_statement' => !empty($rules['shorten_statement']),
                            'literal_language' => !empty($rules['literal_language']),
                        ],
                    ], $alunoId, 'aluno');
                    $this->log->record('reescrita_ia_enfileirada', [
                        'versao_adaptada_id' => $versionId,
                        'mascara_id' => $mascaraId,
                        'aluno_id' => $alunoId,
                        'prova_id' => $originalProvaId,
                        'user_id' => $alunoId,
                    ]);
                } catch (Throwable $eJob) {
                    error_log('AssessmentVersionGenerator enqueue reescrita: ' . $eJob->getMessage());
                }
            }

            return $this->versions->getById($versionId);
        } catch (Throwable $e) {
            error_log('AssessmentVersionGenerator::ensureDraft: ' . $e->getMessage());
            if ($debitou) {
                try {
                    require_once __DIR__ . '/CreditosService.php';
                    (new \App\Services\CreditosService())->estornarPorReferencia($creditsModulo, $creditsRef);
                } catch (Throwable $eEstorno) {
                    error_log('AssessmentVersionGenerator estorno TudiCoins: ' . $eEstorno->getMessage());
                }
            }
            return null;
        }
    }

    /**
     * @param list<array<string,mixed>> $questoes
     * @return list<array<string,mixed>>
     */
    private function selecionarQuestoes(array $questoes, int $keep, int $seed): array
    {
        $pool = $questoes;
        usort($pool, static function ($a, $b) use ($seed) {
            return strcmp(
                md5('pick-' . $seed . '-' . ($a['id'] ?? '')),
                md5('pick-' . $seed . '-' . ($b['id'] ?? ''))
            );
        });
        $selecionadas = array_slice($pool, 0, $keep);

        // Reordena pela ordem original para uma leitura coerente.
        usort($selecionadas, static function ($a, $b) {
            $oa = (int) ($a['ordem'] ?? 0);
            $ob = (int) ($b['ordem'] ?? 0);
            return $oa <=> $ob ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
        });

        return $selecionadas;
    }

    /**
     * @param list<array<string,mixed>> $alternativas
     * @return list<array<string,mixed>>
     */
    private function reduceAlternativas(array $alternativas, int $keep, int $seed): array
    {
        $corretas = [];
        $distratores = [];
        foreach ($alternativas as $alt) {
            if (!empty($alt['correta'])) {
                $corretas[] = $alt;
            } else {
                $distratores[] = $alt;
            }
        }
        if (empty($corretas)) {
            return $alternativas;
        }

        $ordenarPor = static function (string $prefixo) use ($seed) {
            return static function ($a, $b) use ($prefixo, $seed) {
                return strcmp(
                    md5($prefixo . '-' . $seed . '-' . ($a['id'] ?? '')),
                    md5($prefixo . '-' . $seed . '-' . ($b['id'] ?? ''))
                );
            };
        };

        usort($distratores, $ordenarPor('pick'));
        $faltam = max(0, $keep - count($corretas));
        $selecionadas = array_merge($corretas, array_slice($distratores, 0, $faltam));
        usort($selecionadas, $ordenarPor('ord'));

        return array_values($selecionadas);
    }
}
}
