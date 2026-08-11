<?php
/**
 * Serviço de monitoramento de mensagens do chat (Tudinha)
 */

namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/OpenAIService.php';

class ChatSafetyMonitorService
{
    private $db;
    private $openai;

    public function __construct()
    {
        $this->db = \Database::getInstance();
        $this->openai = new OpenAIService();
    }

    public function analisarMensagem($alunoId, $turmaId, $mensagem, $origem, array $contextoRecente = [], $mensagemChatId = null)
    {
        $mensagem = trim((string)$mensagem);
        if ($mensagem === '') {
            return null;
        }

        $systemPrompt = $this->buildSystemPrompt();
        $payload = [
            'origem' => $origem,
            'mensagem' => $mensagem,
            'contexto_recente' => $contextoRecente
        ];

        try {
            $resposta = $this->openai->chatCompletion(
                [
                    ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]
                ],
                $systemPrompt,
                'gpt-4o-mini',
                0.1,
                350
            );
            $resultado = $this->parseResultado($resposta['resposta'] ?? '');

            if (!$resultado || empty($resultado['alerta'])) {
                return null;
            }

            $categoria = $resultado['categoria'] ?? '';
            $nivel = $resultado['nivel'] ?? '';
            $resumo = trim($resultado['mensagem_resumo'] ?? '');

            if ($categoria === '' || $nivel === '' || $resumo === '') {
                return null;
            }

            $mensagemAluno = ($origem === 'aluno' && $mensagem !== '') ? $mensagem : null;
            $alertaId = $this->db->insert(
                "INSERT INTO alertas_sensiveis (aluno_id, turma_id, categoria, nivel, mensagem_resumo, mensagem_aluno, mensagem_chat_id, status, created_at)
                 VALUES (:aluno_id, :turma_id, :categoria, :nivel, :mensagem_resumo, :mensagem_aluno, :mensagem_chat_id, 'novo', NOW())",
                [
                    'aluno_id' => $alunoId,
                    'turma_id' => $turmaId,
                    'categoria' => $categoria,
                    'nivel' => $nivel,
                    'mensagem_resumo' => $resumo,
                    'mensagem_aluno' => $mensagemAluno,
                    'mensagem_chat_id' => $mensagemChatId
                ]
            );

            return $alertaId;
        } catch (\Exception $e) {
            error_log("Monitoramento Tudinha falhou: " . $e->getMessage());
            return null;
        }
    }

    private function buildSystemPrompt()
    {
        return "Você é um sistema de segurança educacional invisível. Analise a mensagem e classifique se há conteúdo sensível.\n"
            . "Categorias possíveis: saude_emocional, violencia, abuso_ou_assedio, drogas, sexual_improprio, discurso_de_odio, linguagem_agressiva_recorrente.\n"
            . "Níveis possíveis: baixo, medio, alto, critico.\n"
            . "Regras:\n"
            . "- Se NÃO houver conteúdo sensível, responda somente JSON: {\"alerta\": false}\n"
            . "- Se houver, responda somente JSON com: alerta (true), categoria, nivel, mensagem_resumo.\n"
            . "- mensagem_resumo deve ser neutra e segura, sem repetir texto do aluno, sem gatilhos, sem julgamento.\n"
            . "- Não inclua nenhum texto fora do JSON.\n";
    }

    private function parseResultado($texto)
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        $json = $texto;
        if (strpos($texto, '{') !== false) {
            $start = strpos($texto, '{');
            $end = strrpos($texto, '}');
            if ($end !== false && $end > $start) {
                $json = substr($texto, $start, $end - $start + 1);
            }
        }

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }
}

