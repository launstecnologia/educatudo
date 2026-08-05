<?php

namespace App\AI\Agentes\Tudinha;

use App\AI\AgenteIAInterface;
use App\AI\ContextoExecucao;
use App\AI\Suporte\CarregadorPromptTudinha;
use Exception;

require_once __DIR__ . '/../../../Services/TudinhaService.php';

/**
 * EducaTudo - monta histórico da conversa, system prompt e mensagens OpenAI.
 * Determinístico, sem chamada de IA.
 *
 * Contexto esperado (entrada):
 *   - conversa_id (int, obrigatório)
 *   - mensagem_aluno (string)
 *   - aluno_id (int, opcional)
 *   - image_url (string|null, opcional)
 *   - imagem_data_uri_visao (string|null, opcional)
 * Contexto produzido (saída):
 *   - messages_openai (array)
 *   - system_prompt (string)
 */
class MontadorContextoTudinhaAgent implements AgenteIAInterface
{
    public function nome(): string
    {
        return 'MontadorContextoTudinhaAgent';
    }

    public function executar(ContextoExecucao $contexto): ContextoExecucao
    {
        $conversaId = (int) $contexto->get('conversa_id', 0);
        if ($conversaId <= 0) {
            throw new Exception('MontadorContextoTudinhaAgent: conversa_id inválido');
        }

        $alunoId = (int) $contexto->get('aluno_id', 0);
        if ($alunoId > 0) {
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../../../Core/Database.php';
            }
            $conversa = \Database::getInstance()->fetch(
                'SELECT id FROM tudinha_conversas WHERE id = :id AND aluno_id = :aluno_id',
                ['id' => $conversaId, 'aluno_id' => $alunoId]
            );
            if (!$conversa) {
                throw new Exception('MontadorContextoTudinhaAgent: conversa não pertence ao aluno');
            }
        }

        $tudinhaService = new \App\Services\TudinhaService();
        $messages = $tudinhaService->construirMensagensParaOpenAI(
            $conversaId,
            (string) $contexto->get('mensagem_aluno', ''),
            $contexto->get('image_url'),
            $contexto->get('imagem_data_uri_visao')
        );

        $systemPrompt = CarregadorPromptTudinha::carregarChat();
        if ($alunoId > 0) {
            $blocoAluno = $tudinhaService->construirContextoAlunoNoPrompt($alunoId);
            if ($blocoAluno !== '') {
                $systemPrompt .= $blocoAluno;
            }
        }

        return $contexto
            ->set('messages_openai', $messages)
            ->set('system_prompt', $systemPrompt);
    }
}
