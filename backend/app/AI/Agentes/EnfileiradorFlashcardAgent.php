<?php

namespace App\AI\Agentes;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use Exception;

require_once __DIR__ . '/../../Services/CreditosService.php';
require_once __DIR__ . '/../../Services/AIJobService.php';

/**
 * EducaTudo - debita o crédito de flashcard e enfileira o job assíncrono de
 * geração (mesma fila/payload que FlashcardController::generate() já usa —
 * quem gera de fato é FlashcardService, dentro do worker/cron; este agente
 * só enfileira, não chama IA diretamente).
 *
 * Contexto esperado (entrada):
 *   - topico_flashcard (string, obrigatório)
 *   - quantidade_flashcard (int, obrigatório)
 *   - aluno_id (int, obrigatório)
 *   - grade (string, opcional — série/turma do aluno)
 * Contexto produzido (saída):
 *   - job_id (int)
 */
class EnfileiradorFlashcardAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'EnfileiradorFlashcardAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $topico = trim((string) $contexto->get('topico_flashcard', ''));
        if ($topico === '') {
            throw new Exception('EnfileiradorFlashcardAgent: topico_flashcard vazio');
        }
        $quantidade = (int) $contexto->get('quantidade_flashcard', 8);
        $alunoId = (int) $contexto->get('aluno_id', 0);
        if ($alunoId <= 0) {
            throw new Exception('EnfileiradorFlashcardAgent: aluno_id inválido');
        }
        $grade = trim((string) $contexto->get('grade', ''));

        $refCredito = 'flashcard_gen_' . $alunoId . '_' . str_replace('.', '', uniqid('', true));

        $creditosService = new \App\Services\CreditosService();
        $creditosService->consumir('aluno', $alunoId, 'flashcard', $refCredito);

        $jobId = \App\Services\AIJobService::enqueue(
            'criar_flashcards',
            [
                'user_id'        => $alunoId,
                'mode'           => 'topic',
                'topic'          => $topico,
                'grade'          => $grade,
                'quantity'       => $quantidade,
                'credits_ref'    => $refCredito,
                'credits_modulo' => 'flashcard',
            ],
            $alunoId,
            'aluno'
        );

        return $contexto->set('job_id', $jobId);
    }
}
