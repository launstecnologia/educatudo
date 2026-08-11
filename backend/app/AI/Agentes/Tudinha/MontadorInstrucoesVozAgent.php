<?php

namespace App\AI\Agentes\Tudinha;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use App\AI\Suporte\CarregadorPromptTudinha;
use Exception;

require_once __DIR__ . '/../../../Services/TudinhaService.php';

/**
 * EducaTudo - monta instructions completas para sessão Realtime (voz),
 * combinando prompt de chat + contexto do aluno + regras de voz.
 *
 * Contexto esperado (entrada):
 *   - aluno_id (int, obrigatório)
 * Contexto produzido (saída):
 *   - instructions_voz (string)
 */
class MontadorInstrucoesVozAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'MontadorInstrucoesVozAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $alunoId = (int) $contexto->get('aluno_id', 0);
        if ($alunoId <= 0) {
            throw new Exception('MontadorInstrucoesVozAgent: aluno_id inválido');
        }

        $tudinhaService = new \App\Services\TudinhaService();
        $instructions = CarregadorPromptTudinha::carregarChat();
        $blocoAluno = $tudinhaService->construirContextoAlunoNoPrompt($alunoId);
        if ($blocoAluno !== '') {
            $instructions .= $blocoAluno;
        }
        $instructions .= "\n\n" . CarregadorPromptTudinha::carregarVoz();

        return $contexto->set('instructions_voz', $instructions);
    }
}
