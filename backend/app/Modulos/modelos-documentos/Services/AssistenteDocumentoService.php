<?php

namespace App\Modulos\ModelosDocumentos\Services;

use App\Core\AdminPermissionMatrix;
use App\Core\CreditosModuleRegistry;
use App\Core\Database;
use App\Services\AIJobService;
use App\Services\CreditosService;
use App\Services\OpenAIService;

class AssistenteDocumentoService
{
    public const ACAO = 'modelo_documento_assistente';
    // Liberação temporária do chat no domínio usado para o teste.
    private const DOMINIO_TESTE_SEM_TUDICOINS = 'educa.educatudo.com';

    public static function iaDisponivel(): bool
    {
        return CreditosModuleRegistry::acaoIaDisponivel(self::ACAO)
            || (defined('TENANT_DOMAIN') && strcasecmp(TENANT_DOMAIN, self::DOMINIO_TESTE_SEM_TUDICOINS) === 0);
    }

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
        $payload = ['mensagens' => $mensagens];
        if (CreditosModuleRegistry::acaoIaDisponivel(self::ACAO)) {
            $creditos = new CreditosService();
            $referencia = 'modelo_doc_chat:' . bin2hex(random_bytes(10));
            $creditos->consumirEscola(self::ACAO, $referencia);
            $payload['credits_ref'] = $referencia;
            $payload['credits_modulo'] = self::ACAO;
        }
        try {
            $jobId = AIJobService::enqueue(self::ACAO, $payload, $usuarioId, 'admin', false);
        } catch (\Throwable $e) {
            if (isset($creditos, $referencia)) {
                try {
                    $creditos->estornarPorReferencia(self::ACAO, $referencia);
                } catch (\Throwable $estornoErro) {
                    error_log('Assistente de documentos estorno: ' . $estornoErro->getMessage());
                }
            }
            throw $e;
        }
        AIJobService::tentarDispararWorker();
        return $jobId;
    }

    public static function responder(array $mensagens, int $jobId): array
    {
        try {
            $db = Database::getInstance();
            $db->query("UPDATE ai_jobs SET result = NULL WHERE id = ? AND status = 'processing'", [$jobId]);
            $resposta = '';
            $ultimaGravacao = 0.0;
            $textoPublicado = '';
            $pedido = [
                'model' => 'gpt-4o',
                'messages' => array_merge([['role' => 'system', 'content' => self::prompt()]], $mensagens),
                'temperature' => 0.3,
                'max_tokens' => 6500,
                'response_format' => ['type' => 'json_object'],
            ];
            (new OpenAIService())->chatCompletionStream($pedido, static function (string $trecho) use (
                $db, $jobId, &$resposta, &$ultimaGravacao, &$textoPublicado
            ): void {
                $resposta .= $trecho;
                $agora = microtime(true);
                if ($agora - $ultimaGravacao < 0.35) {
                    return;
                }
                $ultimaGravacao = $agora;
                $texto = self::mensagemParcial($resposta);
                if ($texto !== '' && $texto !== $textoPublicado) {
                    $db->query("UPDATE ai_jobs SET result = ? WHERE id = ? AND status = 'processing'", [
                        json_encode(['mensagem_parcial' => $texto], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        $jobId,
                    ]);
                    $textoPublicado = $texto;
                }
            });
            return self::interpretarResposta($resposta);
        } catch (\Throwable $e) {
            error_log('Assistente de documentos IA: ' . $e->getMessage());
            throw new \RuntimeException('O assistente não conseguiu responder. Tente novamente.');
        }
    }

    public static function mensagemParcial(string $resposta): string
    {
        // Lê só o primeiro campo e ignora escapes JSON ainda incompletos.
        $padrao = '~\A\s*\{\s*"mensagem"\s*:\s*"((?:[^"\x5c]|\x5c(?:["/bfnrt\x5c]|u[0-9a-fA-F]{4}))*+)~u';
        if (!preg_match($padrao, $resposta, $partes)) {
            return '';
        }
        $texto = json_decode('"' . $partes[1] . '"', true);
        return is_string($texto) ? mb_substr($texto, 0, 4000) : '';
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
Responda com um único objeto JSON válido, sem Markdown, no formato abaixo. Escreva o campo mensagem primeiro, seguido de html e orientacao.
{"mensagem":"Resposta curta em texto simples","html":"","orientacao":"retrato"}.
Fale de forma direta e natural. Quando o tipo de autorização estiver ambíguo, pergunte o que precisa ser autorizado antes de sugerir um modelo.
Em dúvidas, deixe html vazio. Cite as variáveis exatas do catálogo, com chaves duplas.
Se não houver variável no catálogo, oriente a criar um Campo da emissão no editor ou a inserir texto.
Quando o pedido de criação estiver aberto e faltar informação que muda o documento, faça até três perguntas objetivas na mesma resposta e deixe html vazio.
Para uma autorização de passeio, pergunte apenas pelo que falta entre local, data e horários de saída e retorno. Ofereça a opção de deixar esses dados para preencher na emissão.
Quando a pessoa responder ou pedir para seguir com campos da emissão, monte o documento completo. Não repita perguntas respondidas e não peça dados pessoais que já existem como variáveis.
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
