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
 */
class GeradorQuestaoAgent implements AgenteIAInterface
{
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

        $blocoReferencias = '';
        if (!empty($referenciasReais)) {
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
            if (!empty($linhas)) {
                $blocoReferencias = "Fontes reais disponíveis pra contextualizar (use se fizer sentido pro assunto, não invente dados além delas):\n"
                    . implode("\n", $linhas) . "\n";
            }
        }

        $mensagemUsuario = "Disciplina: {$disciplina}\n"
            . "Assunto: {$assunto}\n"
            . ($serie !== '' ? "Série: {$serie}\n" : '')
            . "Dificuldade: {$dificuldade}\n"
            . "Tipo de questão: {$tipoQuestao}\n"
            . "Quantidade de questões: {$quantidade}\n"
            . ($tipoQuestao === 'multipla_escolha' ? "Quantidade de alternativas por questão: {$qtdAlternativas}\n" : '')
            . "tipo_recurso_visual_decidido: " . ($tipoRecursoVisual ?: 'null') . "\n"
            . $blocoReferencias
            . ($contextoAdicional !== '' ? "Contexto adicional do professor: {$contextoAdicional}\n" : '');

        // JSON de uma questão com alternativas + explicação gira em ~450 tokens;
        // com limite fixo de 4000 um lote grande saía truncado.
        $tokensResposta = max(4000, $quantidade * 450);

        $resultado = $openAIService->chatCompletion(
            [['role' => 'user', 'content' => $mensagemUsuario]],
            trim($promptSistema),
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

        return $contexto->set('questoes_geradas', $dados['questoes']);
    }
}
