<?php

namespace App\Agentes\Tudinha;

use App\Agentes\AgenteIAInterface;
use App\Agentes\ContextoExecucao;
use App\Agentes\Suporte\CarregadorPromptTudinha;
use Exception;


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

if (!class_exists('MontadorInstrucoesVozAgent', false) && class_exists(__NAMESPACE__ . '\\MontadorInstrucoesVozAgent', false)) {
    class_alias(__NAMESPACE__ . '\\MontadorInstrucoesVozAgent', 'MontadorInstrucoesVozAgent');
}
