<?php

namespace App\AI;

use Exception;

require_once __DIR__ . '/../Services/CreditosService.php';
require_once __DIR__ . '/../Services/AIJobService.php';

/**
 * EducaTudo - ponto de entrada único pro motor de geração de questão por IA.
 * Qualquer tela (Prova Online, Jornada do Aluno, Exercício IA) monta seu
 * Blueprint (array associativo — ver docblock de solicitar()) com os campos
 * específicos daquela tela e chama este serviço; a geração em si roda
 * assíncrona, via fila (job_type 'gerar_questao_ia', processado em
 * AIJobService::dispatchGerarQuestaoIA()).
 *
 * Módulo de crédito é decidido por `origem` (cada tela mantém seu próprio
 * módulo de cobrança já existente — não unifica cobrança entre telas, é
 * decisão de produto, não técnica).
 *
 * Por padrão não dispara processamento imediato (spawnBackgroundWorker/inline):
 * job de geração de questão pode ser de alto volume. Fluxos pontuais podem
 * passar disparar_imediatamente=true no blueprint para reduzir a latência.
 */
class GeradorQuestaoService
{
    private const MODULO_CREDITO_POR_ORIGEM = [
        'prova' => 'gerar_exercicio_ia_professor',
        'jornada' => 'gerar_exercicio_ia_professor',
        'exercicio_ia' => 'gerar_exercicio_ia_aluno',
    ];

    /**
     * @param array $blueprint Campos esperados:
     *   - disciplina, assunto (string, obrigatórios)
     *   - serie (string, opcional)
     *   - dificuldade (facil|medio|dificil|desafio, opcional)
     *   - tipo_questao (multipla_escolha|verdadeiro_falso|dissertativa, opcional)
     *   - quantidade (int, opcional)
     *   - quantidade_alternativas (int, opcional)
     *   - com_recurso_visual (bool|'auto', opcional)
     *   - tipo_recurso_visual (string|null, opcional — override manual)
     *   - contexto_adicional (string, opcional)
     *   - origem (prova|jornada|exercicio_ia, obrigatório — define módulo de crédito)
     *   - usuario_id (int, obrigatório)
     *   - papel (aluno|professor, obrigatório — passado ao CreditosService/AIJobService)
     *   - config (array, obrigatório — config global da app, usado se houver recurso visual)
     * @return int job_id (pra o caller iniciar o polling via AIJobPoller)
     */
    public static function solicitar(array $blueprint): int
    {
        $origem = $blueprint['origem'] ?? '';
        if (!isset(self::MODULO_CREDITO_POR_ORIGEM[$origem])) {
            throw new Exception('GeradorQuestaoService: origem inválida ou não informada.');
        }
        $usuarioId = (int) ($blueprint['usuario_id'] ?? 0);
        if ($usuarioId <= 0) {
            throw new Exception('GeradorQuestaoService: usuario_id inválido.');
        }
        $papel = $blueprint['papel'] ?? 'aluno';
        $modulo = self::MODULO_CREDITO_POR_ORIGEM[$origem];

        $refCredito = 'gerar_questao_' . $usuarioId . '_' . str_replace('.', '', uniqid('', true));

        $creditosService = new \App\Services\CreditosService();
        $creditosService->consumir($papel, $usuarioId, $modulo, $refCredito);

        $dispararImediatamente = !empty($blueprint['disparar_imediatamente']);
        $payload = $blueprint;
        unset($payload['disparar_imediatamente']);
        $payload['credits_ref'] = $refCredito;
        $payload['credits_modulo'] = $modulo;

        return \App\Services\AIJobService::enqueue(
            'gerar_questao_ia',
            $payload,
            $usuarioId,
            $papel,
            $dispararImediatamente
        );
    }
}
