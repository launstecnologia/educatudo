<?php

namespace App\AI\Agentes\Questoes;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

require_once __DIR__ . '/../../../Services/OpenAIService.php';
require_once __DIR__ . '/../../ModeloIA.php';

/**
 * EducaTudo - motor único de geração de questão por IA. Substitui as
 * implementações paralelas que existiam em ExamController::gerarQuestoesIA,
 * TeacherJourneyController (jornada) e CustomExerciseController (exercício
 * do aluno) — um só prompt, um só formato de saída canônico.
 *
 * Formato de saída canônico (escrito em 'questoes_geradas' no contexto):
 *   [{ enunciado, tipo, alternativas: [{texto, correta}], nivel_dificuldade,
 *      explicacao, imagem: null|{tipo, descricao, dados_grafico|prompt_imagem} }]
 * O campo `imagem`, quando presente, já sai no formato que
 * ExamImageService::processarImagens() espera — sem tradução extra.
 *
 * Contexto esperado (entrada):
 *   - disciplina, assunto (string, obrigatórios)
 *   - serie (string, opcional)
 *   - dificuldade (facil|medio|dificil|desafio, opcional, default 'medio')
 *   - tipo_questao (multipla_escolha|verdadeiro_falso|dissertativa, opcional, default 'multipla_escolha')
 *   - quantidade (int, opcional, default 5, clamp 1-30)
 *   - quantidade_alternativas (int, opcional, default 5)
 *   - contexto_adicional (string, opcional)
 *   - tipo_recurso_visual_decidido (string|null, escrito pelo PlanejadorQuestaoAgent)
 *   - referencias_reais (array, opcional, escrito pelo BuscadorReferenciaAgent)
 * Contexto produzido (saída):
 *   - questoes_geradas (array, formato canônico acima)
 *
 * Lotes: gera no máximo LOTE_MAX questões por chamada OpenAI. Pedidos grandes
 * (ex.: 10) eram truncados por orçamento de tokens (JSON verbose + modelos com
 * raciocínio), e o agent aceitava JSON com menos itens — professora pedia 10 e
 * recebia ~3. Agora completa em lotes e exige a quantidade solicitada.
 */
class GeradorQuestaoAgent implements AgenteIAInterface
{
    /** Máximo por chamada — cabe no orçamento mesmo com explicação longa + raciocínio. */
    private const LOTE_MAX = 4;

    /** Estimativa conservadora por questão (enunciado + alternativas + explicação). */
    private const TOKENS_POR_QUESTAO = 1100;

    private const RETRIES_POR_LOTE = 2;

    public function nome(): string
    {
        return 'GeradorQuestaoAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $disciplina = trim((string) $contexto->get('disciplina', ''));
        $assunto = trim((string) $contexto->get('assunto', ''));
        if ($disciplina === '' || $assunto === '') {
            throw new Exception('GeradorQuestaoAgent: disciplina/assunto obrigatórios');
        }

        $serie = trim((string) $contexto->get('serie', ''));
        $dificuldade = trim((string) $contexto->get('dificuldade', 'medio'));
        $tipoQuestao = trim((string) $contexto->get('tipo_questao', 'multipla_escolha'));
        $quantidade = max(1, min(30, (int) $contexto->get('quantidade', 5)));
        $qtdAlternativas = max(2, min(6, (int) $contexto->get('quantidade_alternativas', 5)));
        $contextoAdicional = trim((string) $contexto->get('contexto_adicional', ''));
        $tipoRecursoVisual = $contexto->get('tipo_recurso_visual_decidido', null);
        $referenciasReais = (array) $contexto->get('referencias_reais', []);

        $openAIService = new \App\Services\OpenAIService();
        $apiKey = $openAIService->getOpenAIApiKey();
        if (empty($apiKey)) {
            throw new Exception('OPENAI_API_KEY não configurada.');
        }

        $promptSistema = file_get_contents(__DIR__ . '/../../Prompts/Questoes/gerador-questao.md');
        if ($promptSistema === false || trim($promptSistema) === '') {
            throw new Exception('GeradorQuestaoAgent: prompt.md não encontrado');
        }

        $blocoReferencias = $this->montarBlocoReferencias($referenciasReais);
        $todas = [];
        $restante = $quantidade;
        $loteNum = 0;

        while ($restante > 0) {
            $loteNum++;
            $tamanhoLote = min(self::LOTE_MAX, $restante);
            $geradas = $this->gerarLoteComRetry(
                $openAIService,
                trim($promptSistema),
                $disciplina,
                $assunto,
                $serie,
                $dificuldade,
                $tipoQuestao,
                $tamanhoLote,
                $qtdAlternativas,
                $tipoRecursoVisual,
                $blocoReferencias,
                $contextoAdicional,
                $loteNum,
                $quantidade,
                count($todas)
            );

            foreach ($geradas as $q) {
                if (count($todas) >= $quantidade) {
                    break;
                }
                $todas[] = $q;
            }
            $restante = $quantidade - count($todas);
        }

        if (count($todas) < $quantidade) {
            throw new Exception(
                'GeradorQuestaoAgent: gerou apenas ' . count($todas)
                . " de {$quantidade} questões solicitadas"
            );
        }

        return $contexto->set('questoes_geradas', array_slice($todas, 0, $quantidade));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function gerarLoteComRetry(
        \App\Services\OpenAIService $openAIService,
        string $promptSistema,
        string $disciplina,
        string $assunto,
        string $serie,
        string $dificuldade,
        string $tipoQuestao,
        int $tamanhoLote,
        int $qtdAlternativas,
        mixed $tipoRecursoVisual,
        string $blocoReferencias,
        string $contextoAdicional,
        int $loteNum,
        int $quantidadeTotal,
        int $jaGeradas
    ): array {
        $ultimoErro = '';
        for ($tentativa = 1; $tentativa <= self::RETRIES_POR_LOTE; $tentativa++) {
            try {
                $geradas = $this->gerarLote(
                    $openAIService,
                    $promptSistema,
                    $disciplina,
                    $assunto,
                    $serie,
                    $dificuldade,
                    $tipoQuestao,
                    $tamanhoLote,
                    $qtdAlternativas,
                    $tipoRecursoVisual,
                    $blocoReferencias,
                    $contextoAdicional,
                    $loteNum,
                    $quantidadeTotal,
                    $jaGeradas
                );
                if (count($geradas) >= $tamanhoLote) {
                    return array_slice($geradas, 0, $tamanhoLote);
                }
                // Aceita parcial só se for a última tentativa — o loop externo pede o resto.
                if ($tentativa === self::RETRIES_POR_LOTE && count($geradas) > 0) {
                    return $geradas;
                }
                $ultimoErro = 'lote devolveu ' . count($geradas) . " de {$tamanhoLote}";
            } catch (Exception $e) {
                $ultimoErro = $e->getMessage();
                if ($tentativa === self::RETRIES_POR_LOTE) {
                    throw $e;
                }
            }
        }

        throw new Exception(
            'GeradorQuestaoAgent: falha ao gerar lote ' . $loteNum . ' (' . $ultimoErro . ')'
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function gerarLote(
        \App\Services\OpenAIService $openAIService,
        string $promptSistema,
        string $disciplina,
        string $assunto,
        string $serie,
        string $dificuldade,
        string $tipoQuestao,
        int $tamanhoLote,
        int $qtdAlternativas,
        mixed $tipoRecursoVisual,
        string $blocoReferencias,
        string $contextoAdicional,
        int $loteNum,
        int $quantidadeTotal,
        int $jaGeradas
    ): array {
        $mensagemUsuario = "Disciplina: {$disciplina}\n"
            . "Assunto: {$assunto}\n"
            . ($serie !== '' ? "Série: {$serie}\n" : '')
            . "Dificuldade: {$dificuldade}\n"
            . "Tipo de questão: {$tipoQuestao}\n"
            . "Quantidade de questões NESTE LOTE: {$tamanhoLote}\n"
            . "Pedido total do professor: {$quantidadeTotal} (já geradas em lotes anteriores: {$jaGeradas}; este é o lote {$loteNum}).\n"
            . "IMPORTANTE: devolva EXATAMENTE {$tamanhoLote} questões neste JSON, completas e válidas.\n"
            . "DIRETRIZES DESTE LOTE: Varie amplamente os cenários e valores numéricos entre as questões (nunca repita mesmos valores ou historinhas). Distribua as respostas corretas entre as posições (A, B, C, D, E). Não inclua tags HTML no texto.\n"
            . ($tipoQuestao === 'multipla_escolha' ? "Quantidade de alternativas por questão: {$qtdAlternativas}\n" : '')
            . 'tipo_recurso_visual_decidido: ' . ($tipoRecursoVisual ?: 'null') . "\n"
            . $blocoReferencias
            . ($contextoAdicional !== '' ? "Contexto adicional do professor: {$contextoAdicional}\n" : '');

        $tokensResposta = max(4000, $tamanhoLote * self::TOKENS_POR_QUESTAO);

        $resultado = $openAIService->chatCompletion(
            [['role' => 'user', 'content' => $mensagemUsuario]],
            $promptSistema,
            \App\AI\ModeloIA::porDificuldade($dificuldade),
            0.6,
            $tokensResposta
        );

        $resposta = trim((string) ($resultado['resposta'] ?? ''));
        if ($resposta === '') {
            throw new Exception('GeradorQuestaoAgent: resposta vazia da OpenAI');
        }

        // Parsing defensivo: remove cercas ```json, isola o objeto JSON, tolera
        // vírgula final — mesmo espírito do parsing já usado em OpenAIService,
        // mas centralizado aqui em vez de reimplementado por módulo consumidor.
        $resposta = preg_replace('/^```json\s*|\s*```$/m', '', $resposta);
        if (preg_match('/\{.*\}/s', $resposta, $m)) {
            $resposta = $m[0];
        }
        $resposta = preg_replace('/,(\s*[}\]])/', '$1', $resposta);

        $dados = json_decode($resposta, true);
        if (!is_array($dados) || empty($dados['questoes']) || !is_array($dados['questoes'])) {
            throw new Exception('GeradorQuestaoAgent: JSON de questões inválido ou vazio');
        }

        $questoes = [];
        foreach ($dados['questoes'] as $q) {
            if (is_array($q)) {
                $questoes[] = $q;
            }
        }
        if ($questoes === []) {
            throw new Exception('GeradorQuestaoAgent: JSON de questões inválido ou vazio');
        }

        return $questoes;
    }

    /**
     * @param list<array<string,mixed>> $referenciasReais
     */
    private function montarBlocoReferencias(array $referenciasReais): string
    {
        if ($referenciasReais === []) {
            return '';
        }
        $linhas = [];
        foreach ($referenciasReais as $ref) {
            $titulo = $ref['titulo'] ?? '';
            $snippet = $ref['snippet'] ?? '';
            $link = $ref['link'] ?? '';
            if ($titulo === '') {
                continue;
            }
            $linhas[] = "- {$titulo}: {$snippet} ({$link})";
        }
        if ($linhas === []) {
            return '';
        }
        return "Fontes reais disponíveis pra contextualizar (use se fizer sentido pro assunto, não invente dados além delas):\n"
            . implode("\n", $linhas) . "\n";
    }
}
