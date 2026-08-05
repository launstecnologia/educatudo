<?php

namespace App\AI;

require_once __DIR__ . '/ContextoExecucao.php';
require_once __DIR__ . '/ExecutorPipeline.php';
require_once __DIR__ . '/TudinhaChatPipeline.php';

/**
 * EducaTudo - ponto de entrada do motor de chat Tudinha via pipeline de agentes.
 */
class TudinhaChatService
{
    /**
     * Gera resposta em HTML (sync).
     */
    public static function gerarResposta(
        int $conversaId,
        string $mensagemAluno,
        ?string $imageUrl = null,
        ?int $alunoId = null,
        ?string $imagemDataUriVisao = null
    ): string {
        $contexto = self::montarContextoBase($conversaId, $mensagemAluno, $imageUrl, $alunoId, $imagemDataUriVisao);
        $contexto = ExecutorPipeline::executar(TudinhaChatPipeline::agentesChat(), $contexto);
        return (string) $contexto->get('resposta_html', '');
    }

    /**
     * Gera resposta em HTML com streaming (chunks via callback).
     */
    public static function gerarRespostaStream(
        int $conversaId,
        string $mensagemAluno,
        callable $onChunk,
        ?string $imageUrl = null,
        ?int $alunoId = null,
        ?string $imagemDataUriVisao = null
    ): string {
        $contexto = self::montarContextoBase($conversaId, $mensagemAluno, $imageUrl, $alunoId, $imagemDataUriVisao);
        $contexto->set('stream_callback', $onChunk);
        $contexto = ExecutorPipeline::executar(TudinhaChatPipeline::agentesChat(), $contexto);
        return (string) $contexto->get('resposta_html', '');
    }

    /**
     * Abre sessão Realtime para conversa por voz.
     *
     * @return array{client_secret: string, expires_at: mixed, model: string}
     */
    public static function iniciarSessaoVoz(int $alunoId): array
    {
        $contexto = (new ContextoExecucao())->set('aluno_id', $alunoId);
        $contexto = ExecutorPipeline::executar(TudinhaChatPipeline::agentesVoz(), $contexto);

        return [
            'client_secret' => (string) $contexto->get('client_secret', ''),
            'expires_at' => $contexto->get('expires_at'),
            'model' => (string) $contexto->get('model', ''),
        ];
    }

    private static function montarContextoBase(
        int $conversaId,
        string $mensagemAluno,
        ?string $imageUrl,
        ?int $alunoId,
        ?string $imagemDataUriVisao
    ): ContextoExecucao {
        $contexto = (new ContextoExecucao())
            ->set('conversa_id', $conversaId)
            ->set('mensagem_aluno', $mensagemAluno);

        if ($imageUrl !== null && $imageUrl !== '') {
            $contexto->set('image_url', $imageUrl);
        }
        if ($alunoId !== null && $alunoId > 0) {
            $contexto->set('aluno_id', $alunoId);
        }
        if ($imagemDataUriVisao !== null && $imagemDataUriVisao !== '') {
            $contexto->set('imagem_data_uri_visao', $imagemDataUriVisao);
        }

        return $contexto;
    }
}
