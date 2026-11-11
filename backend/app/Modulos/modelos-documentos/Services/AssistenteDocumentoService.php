<?php

namespace App\Modulos\ModelosDocumentos\Services;

use App\Core\AdminPermissionMatrix;
use App\Core\Database;
use App\Services\AIJobService;
use App\Services\CreditosService;
use App\Services\OpenAIService;

class AssistenteDocumentoService
{
    public const ACAO = 'modelo_documento_assistente';

    public static function podeUsar(Database $db, array $usuario): bool
    {
        if (empty($usuario['id']) || !in_array($usuario['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            return false;
        }
        $permissoes = AdminPermissionMatrix::effectivePermissionsForUser($db, $usuario);
        return AdminPermissionMatrix::can($permissoes, 'modelos_documentos', 'alterar')
            || AdminPermissionMatrix::can($permissoes, 'modelos_documentos', 'cadastrar');
    }

    public static function podeConsultar(Database $db, array $usuario, array $job): bool
    {
        return ($job['user_role'] ?? '') === 'admin'
            && (int) ($usuario['id'] ?? 0) === (int) ($job['user_id'] ?? 0)
            && self::podeUsar($db, $usuario);
    }

    public static function mensagensDoPedido(mixed $json): array
    {
        $mensagens = is_string($json) && strlen($json) <= 200000 ? json_decode($json, true) : null;
        if (!is_array($mensagens) || !array_is_list($mensagens) || !$mensagens || count($mensagens) > 12) {
            throw new \InvalidArgumentException('Envie uma mensagem ao assistente.');
        }

        $validas = [];
        foreach ($mensagens as $mensagem) {
            $papel = $mensagem['role'] ?? null;
            $texto = $mensagem['content'] ?? null;
            $limite = $papel === 'user' ? 2500 : 45000;
            if (!in_array($papel, ['user', 'assistant'], true) || !is_string($texto)
                || trim($texto) === '' || mb_strlen($texto) > $limite) {
                throw new \InvalidArgumentException('Há uma mensagem inválida ou muito longa na conversa.');
            }
            $validas[] = ['role' => $papel, 'content' => trim($texto)];
        }
        if (end($validas)['role'] !== 'user') {
            throw new \InvalidArgumentException('Escreva uma mensagem para continuar a conversa.');
        }
        return $validas;
    }

    public static function enfileirar(array $mensagens, int $usuarioId): int
    {
        $referencia = 'modelo_doc_chat:' . bin2hex(random_bytes(10));
        $creditos = new CreditosService();
        $creditos->consumirEscola(self::ACAO, $referencia);
        try {
            $jobId = AIJobService::enqueue(self::ACAO, [
                'mensagens' => $mensagens,
                'credits_ref' => $referencia,
                'credits_modulo' => self::ACAO,
            ], $usuarioId, 'admin', false);
        } catch (\Throwable $e) {
            try {
                $creditos->estornarPorReferencia(self::ACAO, $referencia);
            } catch (\Throwable $estornoErro) {
                error_log('Assistente de documentos estorno: ' . $estornoErro->getMessage());
            }
            throw $e;
        }
        AIJobService::tentarDispararWorker();
        return $jobId;
    }

    public static function responder(array $mensagens): array
    {
        try {
            $retorno = (new OpenAIService())->chatCompletion($mensagens, self::prompt(), 'gpt-4o', 0.3, 6500);
            return self::interpretarResposta($retorno['resposta']);
        } catch (\Throwable $e) {
            error_log('Assistente de documentos IA: ' . $e->getMessage());
            throw new \RuntimeException('O assistente não conseguiu responder. Tente novamente.');
        }
    }

    public static function prompt(): string
    {
        $catalogo = [];
        foreach (ModeloDocumentoService::PLACEHOLDERS as $chave => $rotulo) {
            $catalogo[] = '{{' . $chave . '}} — ' . $rotulo;
        }
        $camposEmissao = array_map(
            static fn (array $campo): string => '{{' . substr($campo['chave'], 3) . '}}',
            ModeloDocumentoService::camposAutorizacao()
        );

        return <<<'PROMPT'
Você é o assistente do editor de modelos de documentos escolares do EducaTudo.
Ajude em português do Brasil com variáveis, campos preenchidos na emissão e montagem de layouts.
Responda com um único objeto JSON válido, sem Markdown, no formato
{"mensagem":"Resposta curta em texto simples","html":"","orientacao":"retrato"}.
Em dúvidas, deixe html vazio. Cite as variáveis exatas do catálogo, com chaves duplas.
Se não houver variável no catálogo, oriente a criar um Campo da emissão no editor ou a inserir texto.
Para criar ou revisar um documento, preencha html com o fragmento editável completo e escolha retrato ou paisagem.
Use títulos, parágrafos, tabelas, mesclas e estilos inline simples. Use larguras proporcionais e deixe o conteúdo caber na largura útil da folha.
Use apenas variáveis do catálogo para dados automáticos e mantenha os campos da emissão que o pedido exigir.
Use {{logo_html}} ou {{aluno_foto_html}} diretamente no conteúdo para logo ou foto, quando solicitados.
Não invente nomes, notas, dados cadastrais, variáveis, imagens externas, scripts, formulários ou links.
Ao revisar, considere o HTML da última proposta desta conversa, se houver, e devolva o documento inteiro.
Explique que a pessoa pode revisar e editar a proposta antes de inserir na folha.
PROMPT
            . "\n\nCampos de autorização e comparecimento preenchidos na emissão: " . implode(', ', $camposEmissao)
            . "\n\nVariáveis do editor:\n" . implode("\n", $catalogo);
    }

    public static function interpretarResposta(string $resposta): array
    {
        $resposta = preg_replace('/\A\s*```(?:json)?\s*|\s*```\s*\z/i', '', trim($resposta));
        $dados = json_decode($resposta, true);
        $mensagem = $dados['mensagem'] ?? null;
        $html = $dados['html'] ?? '';
        if (!is_string($mensagem) || trim($mensagem) === '' || !is_string($html)) {
            throw new \UnexpectedValueException('Resposta inválida do assistente.');
        }

        $estrutura = null;
        if (trim($html) !== '') {
            $estrutura = ReprodutorLayoutDocumentoService::estruturaDaResposta(json_encode([
                'html' => $html,
                'orientacao' => ($dados['orientacao'] ?? '') === 'paisagem' ? 'paisagem' : 'retrato',
            ], JSON_THROW_ON_ERROR));
        }
        return ['mensagem' => mb_substr(trim($mensagem), 0, 4000), 'estrutura' => $estrutura];
    }
}
