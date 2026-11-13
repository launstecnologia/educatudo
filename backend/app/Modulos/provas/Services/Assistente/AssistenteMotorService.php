<?php

namespace App\Modulos\Provas\Services\Assistente;

use App\Modulos\Provas\Services\ProvasAlunoAssistenteService;

/**
 * Motor v2 do Assistente: function calling nativo + estado estruturado.
 * O modelo escolhe a tool; o servidor resolve o aluno, executa e guarda o estado.
 */
class AssistenteMotorService
{
    public const MODELO = 'gpt-5.6-terra';
    private const MAX_RODADAS = 5;
    private const MAX_CHARS_RESULTADO = 14000;
    private const MAX_HISTORICO = 10;

    private ProvasAlunoAssistenteService $servico;
    /** @var \App\Services\OpenAIService */
    private $openai;
    private AssistenteResolvedorAluno $resolvedor;
    private string $modelo;

    public function __construct(
        ProvasAlunoAssistenteService $servico,
        $openai,
        ?AssistenteResolvedorAluno $resolvedor = null,
        ?string $modelo = null
    ) {
        $this->servico = $servico;
        $this->openai = $openai;
        $consulta = $servico->consulta();
        $this->resolvedor = $resolvedor ?? new AssistenteResolvedorAluno(
            static fn(string $termo, ?string $turma): array => $consulta->buscarAlunos($termo, 20, $turma)
        );
        $this->modelo = $modelo ?? self::MODELO;
    }

    /**
     * @param list<array{role:string,content:string}> $historico
     * @param array<string,mixed> $estado
     * @return array{success:bool,mensagem?:string,painel?:?array,consultas?:list,estado?:array,error?:string}
     */
    public function processar(
        string $mensagem,
        array $historico,
        array $estado,
        ?callable $onStatus = null,
        ?callable $onTextoChunk = null
    ): array {
        $inicio = microtime(true);
        $estado = AssistenteEstadoConversa::normalizar($estado);
        $consultas = [];
        $ultimaTool = null;
        $ultimoResultado = null;
        $parada = null;
        $alunoUsado = null;
        $textoModelo = '';

        $selecao = $this->extrairSelecao($mensagem);
        if ($selecao !== null && $estado['pendente'] !== null) {
            $exec = $this->retomarPendente($selecao, $estado, $onStatus);
            $estado = $exec['estado'];
            $consultas[] = ['tool' => $exec['tool'], 'args' => $exec['args']];
            $ultimaTool = $exec['tool'];
            $ultimoResultado = $exec['resultado'];
            $parada = $exec['parada'];
            $alunoUsado = $exec['aluno'];
        } else {
            if ($selecao !== null && isset($selecao['aluno_id'])) {
                $aluno = $this->servico->consulta()->obterAluno((int) $selecao['aluno_id']);
                if ($aluno !== null) {
                    $estado = AssistenteEstadoConversa::definirAlunoAtivo($estado, $aluno);
                }
            }
            $mensagens = $this->montarMensagens($mensagem, $historico, $estado);
            $definicoes = AssistenteFerramentas::definicoes();

            try {
                for ($rodada = 0; $rodada < self::MAX_RODADAS; $rodada++) {
                    $resp = $this->openai->chatCompletionComFerramentas($mensagens, $definicoes, $this->modelo, 1500);
                    $chamadas = $resp['tool_calls'];
                    if ($chamadas === []) {
                        $textoModelo = trim($resp['conteudo']);
                        break;
                    }

                    $mensagens[] = [
                        'role' => 'assistant',
                        'content' => $resp['conteudo'] !== '' ? $resp['conteudo'] : null,
                        'tool_calls' => array_map(static fn(array $c) => [
                            'id' => $c['id'],
                            'type' => 'function',
                            'function' => ['name' => $c['nome'], 'arguments' => $c['args_brutos']],
                        ], $chamadas),
                    ];
                    foreach ($chamadas as $c) {
                        if ($onStatus !== null) {
                            $onStatus($this->rotuloStatus($c['nome']));
                        }
                        $exec = $this->executar($c['nome'], $c['args'], $estado);
                        $estado = $exec['estado'];
                        $consultas[] = ['tool' => $c['nome'], 'args' => $exec['args']];
                        $ultimaTool = $c['nome'];
                        $ultimoResultado = $exec['resultado'];
                        $parada = $parada ?? $exec['parada'];
                        $alunoUsado = $exec['aluno'] ?? $alunoUsado;
                        $mensagens[] = [
                            'role' => 'tool',
                            'tool_call_id' => $c['id'],
                            'content' => $this->resultadoParaModelo($exec['resultado']),
                        ];
                    }
                    if ($parada !== null) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                error_log('AssistenteMotorService: ' . $e->getMessage());
                return ['success' => false, 'error' => 'Falha ao consultar a IA. Tente novamente.'];
            }
        }

        $painel = $this->servico->montarPainel($ultimaTool, $ultimoResultado);
        $texto = $this->montarTexto($mensagem, $parada, $textoModelo, $ultimaTool, $ultimoResultado, $alunoUsado);
        if (str_starts_with((string) ($painel['tipo'] ?? ''), 'candidatos')) {
            $texto = (string) ($painel['aviso'] ?? $texto);
        }
        if ($ultimaTool !== null) {
            $estado['ultima_tool'] = $ultimaTool;
        }

        $this->registrarLog($mensagem, $consultas, $alunoUsado, $parada, $inicio);

        if ($painel === null && $onTextoChunk !== null) {
            $this->emitirEmPedacos($texto, $onTextoChunk);
        }

        return [
            'success' => true,
            'mensagem' => $texto,
            'painel' => $painel,
            'consultas' => $consultas,
            'estado' => $estado,
        ];
    }

    /**
     * @param array<string,mixed> $argsModelo
     * @param array<string,mixed> $estado
     * @return array{resultado:array<string,mixed>,estado:array<string,mixed>,args:array<string,mixed>,parada:?string,aluno:?array}
     */
    public function executar(string $tool, array $argsModelo, array $estado): array
    {
        $args = self::limparArgs($argsModelo);
        if (!in_array($tool, AssistenteFerramentas::nomes(), true)) {
            return $this->saida(['ok' => false, 'error' => 'Ferramenta desconhecida.'], $estado, $args, null, null);
        }
        if ($tool === 'propor_criar_ocorrencia') {
            return $this->executarOcorrencia($args, $estado);
        }
        if ($tool !== 'buscar_alunos' && $estado['fluxo'] !== null) {
            $estado = AssistenteEstadoConversa::limparFluxo($estado);
        }
        $estado['pendente'] = null;

        if (!empty($args['turma_nome'])) {
            $estado['turma_ativa'] = (string) $args['turma_nome'];
        }
        if ((int) ($args['ano_letivo'] ?? 0) > 0) {
            $estado['ano_letivo'] = (int) $args['ano_letivo'];
        }

        $aluno = null;
        if (in_array($tool, AssistenteFerramentas::TOOLS_ALUNO, true)) {
            $res = $this->resolvedor->resolver($args, $estado['aluno_ativo']);
            $argsSemAluno = self::semCamposAluno($args);
            if ($res['status'] === 'candidatos') {
                $estado = AssistenteEstadoConversa::definirPendente($estado, $tool, $argsSemAluno);
                return $this->saida(
                    ['ok' => false, 'aviso' => $res['mensagem'], 'candidatos' => $res['candidatos']],
                    $estado,
                    $args,
                    'candidatos',
                    null
                );
            }
            if ($res['status'] !== 'ok') {
                return $this->saida(['ok' => false, 'error' => $res['mensagem']], $estado, $args, 'erro_aluno', null);
            }
            $aluno = $res['aluno'];
            $args = $argsSemAluno + ['aluno_id' => (int) $aluno['id']];
            if (in_array($tool, ['propor_entrada_saida', 'propor_chamada_aula'], true) && !empty($aluno['turma_nome'])) {
                $args['turma'] = (string) $aluno['turma_nome'];
            }
            $estado = AssistenteEstadoConversa::definirAlunoAtivo($estado, $aluno);
        }

        $resultado = $this->servico->executarTool($tool, $args);

        if (is_array($resultado['aluno'] ?? null) && (int) ($resultado['aluno']['id'] ?? 0) > 0) {
            $aluno = $resultado['aluno'];
            $estado = AssistenteEstadoConversa::definirAlunoAtivo($estado, $aluno);
        } elseif ($tool === 'buscar_alunos' && count((array) ($resultado['alunos'] ?? [])) === 1) {
            $aluno = $resultado['alunos'][0];
            $estado = AssistenteEstadoConversa::definirAlunoAtivo($estado, $aluno);
        }
        $estado = AssistenteEstadoConversa::registrarReferencias($estado, $tool, $resultado);

        $parada = null;
        if ($this->temCandidatos($resultado)) {
            $estado = AssistenteEstadoConversa::definirPendente($estado, $tool, $args);
            $parada = 'candidatos';
        } elseif (in_array($tool, AssistenteFerramentas::TOOLS_PROPOSTA, true)) {
            $parada = !empty($resultado['proposta_id']) ? 'proposta' : 'erro_proposta';
        }
        return $this->saida($resultado, $estado, $args, $parada, $aluno);
    }

    /**
     * @param array<string,mixed> $args
     * @param array<string,mixed> $estado
     * @return array{resultado:array<string,mixed>,estado:array<string,mixed>,args:array<string,mixed>,parada:?string,aluno:?array}
     */
    private function executarOcorrencia(array $args, array $estado): array
    {
        $estado['pendente'] = null;
        $estado = AssistenteEstadoConversa::atualizarRascunhoOcorrencia($estado, $args);
        $rascunho = $estado['rascunho'];

        $citouAluno = !empty($args['aluno_nome']) || !empty($args['ra']);
        $usarDaConversa = !empty($args['aluno_da_conversa']) && empty($rascunho['aluno']);
        if ($citouAluno || $usarDaConversa) {
            $res = $this->resolvedor->resolver($args, $estado['aluno_ativo']);
            if ($res['status'] === 'ok') {
                $rascunho['aluno'] = AssistenteEstadoConversa::resumoAluno($res['aluno']);
                $estado = AssistenteEstadoConversa::definirAlunoAtivo($estado, $res['aluno']);
            } elseif ($res['status'] === 'candidatos') {
                $estado['rascunho'] = $rascunho;
                $estado = AssistenteEstadoConversa::definirPendente($estado, 'propor_criar_ocorrencia', ['papel' => 'aluno']);
                return $this->saida(
                    ['ok' => false, 'aviso' => $res['mensagem'], 'candidatos' => $res['candidatos']],
                    $estado,
                    $args,
                    'candidatos',
                    null
                );
            } elseif ($res['status'] === 'nenhum') {
                $estado['rascunho'] = $rascunho;
                return $this->saida(['ok' => false, 'error' => $res['mensagem']], $estado, $args, 'erro_aluno', null);
            }
        }
        $estado['rascunho'] = $rascunho;

        $faltando = AssistenteEstadoConversa::camposFaltando($rascunho);
        if (empty($rascunho['aluno'])) {
            array_unshift($faltando, 'aluno');
        }
        if ($faltando !== []) {
            return $this->saida(
                $this->resultadoColeta($faltando[0], $rascunho),
                $estado,
                $args,
                'coleta',
                $rascunho['aluno'] ?? null
            );
        }

        foreach ((array) ($rascunho['envolvidos'] ?? []) as $nomeEnvolvido) {
            $resEnv = $this->resolvedor->resolver(['aluno_nome' => (string) $nomeEnvolvido], null);
            if ($resEnv['status'] === 'ok') {
                $rascunho = self::marcarEnvolvidoResolvido($rascunho, (string) $nomeEnvolvido, $resEnv['aluno']);
                continue;
            }
            $estado['rascunho'] = $rascunho;
            if ($resEnv['status'] === 'candidatos') {
                $estado = AssistenteEstadoConversa::definirPendente(
                    $estado,
                    'propor_criar_ocorrencia',
                    ['papel' => 'envolvido', 'nome' => (string) $nomeEnvolvido]
                );
                return $this->saida(
                    ['ok' => false, 'aviso' => $resEnv['mensagem'], 'candidatos' => $resEnv['candidatos']],
                    $estado,
                    $args,
                    'candidatos',
                    $rascunho['aluno']
                );
            }
            $rascunho['envolvidos'] = array_values(array_diff((array) $rascunho['envolvidos'], [$nomeEnvolvido]));
            $estado['rascunho'] = $rascunho;
            return $this->saida(
                ['ok' => false, 'error' => (string) ($resEnv['mensagem'] ?? 'Aluno envolvido não encontrado.') . ' Informe o nome completo do envolvido.'],
                $estado,
                $args,
                'erro_aluno',
                $rascunho['aluno']
            );
        }
        $estado['rascunho'] = $rascunho;

        $idsEnvolvidos = array_values(array_unique(array_filter(
            array_map(static fn($e) => (int) ($e['id'] ?? 0), (array) ($rascunho['envolvidos_resolvidos'] ?? [])),
            static fn(int $id) => $id > 0 && $id !== (int) $rascunho['aluno']['id']
        )));
        $argsProposta = [
            'aluno_id' => (int) $rascunho['aluno']['id'],
            'alunos_ids' => $idsEnvolvidos,
            'turma' => (string) ($rascunho['aluno']['turma_nome'] ?? ''),
            'categoria' => (string) $rascunho['categoria'],
            'titulo' => (string) ($rascunho['titulo'] ?? mb_substr((string) $rascunho['detalhe'], 0, 80)),
            'detalhe' => (string) $rascunho['detalhe'],
            'nivel_gravidade' => (string) $rascunho['nivel_gravidade'],
            'enviar_pais' => (bool) $rascunho['enviar_pais'],
        ];
        $resultado = $this->servico->executarTool('propor_criar_ocorrencia', $argsProposta);

        if (!empty($resultado['proposta_id'])) {
            $estado = AssistenteEstadoConversa::limparFluxo($estado);
            return $this->saida($resultado, $estado, $argsProposta, 'proposta', $rascunho['aluno']);
        }
        if (!empty($resultado['candidatos_categorias'])) {
            unset($rascunho['categoria']);
            $estado['rascunho'] = $rascunho;
            $coleta = $this->resultadoColeta('categoria', $rascunho);
            $coleta['candidatos_categorias'] = $resultado['candidatos_categorias'];
            $coleta['aviso'] = 'Não achei a categoria "' . (string) ($argsProposta['categoria']) . '". Escolha uma das cadastradas:';
            $coleta['pergunta'] = $coleta['aviso'];
            return $this->saida($coleta, $estado, $argsProposta, 'coleta', $rascunho['aluno']);
        }
        $campoInvalido = match ((string) ($resultado['campo_invalido'] ?? '')) {
            'detalhe' => 'detalhe',
            'gravidade' => 'nivel_gravidade',
            default => null,
        };
        if ($campoInvalido !== null) {
            unset($rascunho[$campoInvalido]);
            $estado['rascunho'] = $rascunho;
            return $this->saida($this->resultadoColeta($campoInvalido, $rascunho), $estado, $argsProposta, 'coleta', $rascunho['aluno']);
        }
        return $this->saida($resultado, $estado, $argsProposta, 'erro_proposta', $rascunho['aluno']);
    }

    /**
     * Clique em chip de candidato: retoma a chamada pendente com o id escolhido, sem IA.
     *
     * @param array<string,int> $selecao
     * @param array<string,mixed> $estado
     * @return array{tool:string,args:array,resultado:array,estado:array,parada:?string,aluno:?array}
     */
    private function retomarPendente(array $selecao, array $estado, ?callable $onStatus): array
    {
        $pendente = $estado['pendente'];
        $tool = (string) $pendente['tool'];
        $args = (array) $pendente['args'];
        $estado['pendente'] = null;

        if ($tool === 'propor_criar_ocorrencia') {
            $rascunho = (array) ($estado['rascunho'] ?? []);
            if (isset($selecao['aluno_id'])) {
                $aluno = $this->servico->consulta()->obterAluno((int) $selecao['aluno_id']);
                if ($aluno !== null) {
                    if (($args['papel'] ?? 'aluno') === 'envolvido' && !empty($rascunho['aluno'])) {
                        $rascunho = self::marcarEnvolvidoResolvido($rascunho, (string) ($args['nome'] ?? $aluno['nome']), $aluno);
                    } else {
                        $rascunho['aluno'] = AssistenteEstadoConversa::resumoAluno($aluno);
                        $estado = AssistenteEstadoConversa::definirAlunoAtivo($estado, $aluno);
                    }
                }
            }
            $estado['fluxo'] = AssistenteEstadoConversa::FLUXO_OCORRENCIA;
            $estado['rascunho'] = $rascunho;
            if ($onStatus !== null) {
                $onStatus('Preparando ocorrência…');
            }
            $exec = $this->executarOcorrencia([], $estado);
            return ['tool' => 'propor_criar_ocorrencia'] + $exec;
        }

        foreach ($selecao as $chave => $id) {
            $args[$chave] = $id;
        }
        if (isset($selecao['turma_id'])) {
            unset($args['turma_nome'], $args['turma']);
        }
        if (isset($selecao['aluno_id'])) {
            $args = self::semCamposAluno($args) + ['aluno_id' => (int) $selecao['aluno_id']];
            $aluno = $this->servico->consulta()->obterAluno((int) $selecao['aluno_id']);
            if ($aluno !== null) {
                $estado = AssistenteEstadoConversa::definirAlunoAtivo($estado, $aluno);
            }
        }
        if ($onStatus !== null) {
            $onStatus($this->rotuloStatus($tool));
        }
        $resultado = $this->servico->executarTool($tool, $args);
        $aluno = is_array($resultado['aluno'] ?? null) ? $resultado['aluno'] : null;
        if ($aluno !== null && (int) ($aluno['id'] ?? 0) > 0) {
            $estado = AssistenteEstadoConversa::definirAlunoAtivo($estado, $aluno);
        }
        $estado = AssistenteEstadoConversa::registrarReferencias($estado, $tool, $resultado);
        $parada = null;
        if ($this->temCandidatos($resultado)) {
            $estado = AssistenteEstadoConversa::definirPendente($estado, $tool, $args);
            $parada = 'candidatos';
        } elseif (in_array($tool, AssistenteFerramentas::TOOLS_PROPOSTA, true)) {
            $parada = !empty($resultado['proposta_id']) ? 'proposta' : 'erro_proposta';
        }
        return [
            'tool' => $tool,
            'args' => $args,
            'resultado' => $resultado,
            'estado' => $estado,
            'parada' => $parada,
            'aluno' => $aluno,
        ];
    }

    /**
     * Chips da interface enviam ids em formato fixo ("… aluno_id=12", "Usar evento_id 3").
     *
     * @return array<string,int>|null
     */
    public function extrairSelecao(string $mensagem): ?array
    {
        $sel = [];
        if (preg_match('/\baluno_id\s*=\s*(\d+)/i', $mensagem, $m) === 1
            || preg_match('/^usar\s+aluno_id\s+(\d+)\b/iu', $mensagem, $m) === 1
        ) {
            $sel['aluno_id'] = (int) $m[1];
        }
        if (preg_match('/\bturma_id\s*=\s*(\d+)/i', $mensagem, $m) === 1) {
            $sel['turma_id'] = (int) $m[1];
        }
        if (preg_match('/\bdiario_aula_id\s*=\s*(\d+)/i', $mensagem, $m) === 1) {
            $sel['diario_aula_id'] = (int) $m[1];
        }
        if (preg_match('/^usar\s+evento(?:_id|\s+avaliativo\s+id|\s+id)?\s+(\d+)\b/iu', $mensagem, $m) === 1) {
            $sel['evento_id'] = (int) $m[1];
        }
        $sel = array_filter($sel, static fn(int $v) => $v > 0);
        return $sel === [] ? null : $sel;
    }

    /**
     * @param list<array{role:string,content:string}> $historico
     * @param array<string,mixed> $estado
     * @return list<array<string,mixed>>
     */
    private function montarMensagens(string $mensagem, array $historico, array $estado): array
    {
        $msgs = [
            ['role' => 'system', 'content' => AssistenteFerramentas::promptSistema($this->servico->catalogosParaPrompt())],
            ['role' => 'system', 'content' => AssistenteEstadoConversa::paraPrompt($estado)],
        ];
        foreach (array_slice($historico, -self::MAX_HISTORICO) as $h) {
            $role = (string) ($h['role'] ?? '');
            $content = trim((string) ($h['content'] ?? ''));
            if (($role !== 'user' && $role !== 'assistant') || $content === '' || str_starts_with($content, '[contexto_')) {
                continue;
            }
            $msgs[] = ['role' => $role, 'content' => mb_substr($content, 0, 3000)];
        }
        $msgs[] = ['role' => 'user', 'content' => $mensagem];
        return $msgs;
    }

    /**
     * @param array<string,mixed>|null $resultado
     * @param array<string,mixed>|null $aluno
     */
    private function montarTexto(
        string $mensagem,
        ?string $parada,
        string $textoModelo,
        ?string $tool,
        ?array $resultado,
        ?array $aluno
    ): string {
        if ($parada === 'coleta' && is_array($resultado)) {
            return (string) ($resultado['pergunta'] ?? $resultado['aviso'] ?? 'Complete os dados da ocorrência.');
        }
        if ($parada === 'candidatos' && is_array($resultado)) {
            return (string) ($resultado['aviso'] ?? 'Há mais de uma opção. Escolha uma:');
        }
        if ($parada === 'proposta' && is_array($resultado)) {
            $det = is_array($resultado['detalhes'] ?? null) ? $resultado['detalhes'] : [];
            return 'Preparei uma proposta de **' . trim((string) ($det['tipo_label'] ?? 'ação'))
                . '**. Revise os dados abaixo e clique em Confirmar para gravar.';
        }
        if (in_array($parada, ['erro_aluno', 'erro_proposta'], true) && is_array($resultado)) {
            return (string) ($resultado['error'] ?? $resultado['aviso'] ?? 'Não consegui concluir o pedido.');
        }

        $texto = $textoModelo;
        if ($texto === '' && $tool !== null) {
            $texto = $this->servico->montarRespostaDireta($mensagem, $tool, $resultado)
                ?? (string) ($resultado['error'] ?? $resultado['aviso'] ?? '');
        }
        if ($texto === '') {
            $texto = $tool !== null
                ? 'Veja o resultado da consulta abaixo.'
                : 'Posso consultar boletim, faltas, ocorrências, provas, jornadas, grade e diário, ou preparar uma ocorrência. O que você precisa?';
        }

        if (is_array($aluno) && !empty($aluno['nome']) && !empty($resultado['ok'])) {
            $primeiro = AssistenteResolvedorAluno::normalizar((string) strtok((string) $aluno['nome'], ' '));
            if ($primeiro !== '' && !str_contains(AssistenteResolvedorAluno::normalizar($texto), $primeiro)) {
                $texto = '**' . trim((string) $aluno['nome']) . '**'
                    . (!empty($aluno['turma_nome']) ? ' (' . $aluno['turma_nome'] . ')' : '')
                    . ' — ' . $texto;
            }
        }
        return $texto;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @return array<string,mixed>
     */
    private function resultadoColeta(string $campo, array $rascunho): array
    {
        $pergunta = AssistenteEstadoConversa::perguntaDoCampo($campo);
        $nomeAluno = trim((string) ($rascunho['aluno']['nome'] ?? ''));
        if ($campo === 'categoria' && $nomeAluno !== '') {
            $pergunta = 'Vamos registrar a ocorrência de **' . $nomeAluno . '**. ' . $pergunta;
        }
        $r = [
            'ok' => false,
            'coleta_ocorrencia' => true,
            'pergunta' => $pergunta,
            'aguardando_campo' => match ($campo) {
                'enviar_pais' => 'notificar_pais',
                default => $campo,
            },
            'aviso' => match ($campo) {
                'aluno' => 'Informe o nome ou RA do aluno.',
                'categoria' => 'Digite a categoria ou escolha uma sugestão:',
                'detalhe' => 'Descreva o que aconteceu.',
                'nivel_gravidade' => 'Qual a gravidade?',
                'mais_envolvidos', 'envolvidos' => 'Há mais pessoas envolvidas?',
                'enviar_pais' => 'Notificar os responsáveis?',
                default => strip_tags(str_replace('**', '', AssistenteEstadoConversa::perguntaDoCampo($campo))),
            },
            'aluno' => $rascunho['aluno'] ?? null,
            'payload' => $rascunho,
            'campos_faltando' => [$campo],
        ];
        if ($campo === 'categoria') {
            $r['candidatos_categorias'] = $this->servico->acoes()->listarCategoriasOcorrencia();
        } elseif ($campo === 'nivel_gravidade') {
            $r['aguardando_campo'] = 'gravidade';
            $r['opcoes_gravidade'] = [
                ['valor' => 'leve', 'rotulo' => 'Leve'],
                ['valor' => 'moderado', 'rotulo' => 'Moderado'],
                ['valor' => 'grave', 'rotulo' => 'Grave'],
            ];
        } elseif (in_array($campo, ['mais_envolvidos', 'enviar_pais'], true)) {
            $r['opcoes_sim_nao'] = [['valor' => 'sim', 'rotulo' => 'Sim'], ['valor' => 'nao', 'rotulo' => 'Não']];
        }
        return $r;
    }

    /** @param array<string,mixed> $resultado */
    private function temCandidatos(array $resultado): bool
    {
        foreach (['candidatos', 'candidatos_aulas', 'candidatos_bimestres', 'candidatos_materias', 'candidatos_provas', 'candidatos_eventos', 'candidatos_eventos_sigla'] as $k) {
            if (!empty($resultado[$k]) && is_array($resultado[$k])) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $resultado */
    private function resultadoParaModelo(array $resultado): string
    {
        $json = json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
        if (strlen($json) > self::MAX_CHARS_RESULTADO) {
            $json = mb_strcut($json, 0, self::MAX_CHARS_RESULTADO) . '… [resultado truncado; a interface mostra o restante]';
        }
        return $json;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function limparArgs(array $args): array
    {
        $out = [];
        foreach ($args as $k => $v) {
            if ($v === null || (is_string($v) && trim($v) === '') || (is_array($v) && $v === [])) {
                continue;
            }
            $out[(string) $k] = is_string($v) ? trim($v) : $v;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $aluno
     * @return array<string,mixed>
     */
    private static function marcarEnvolvidoResolvido(array $rascunho, string $nomeDigitado, array $aluno): array
    {
        $resolvidos = (array) ($rascunho['envolvidos_resolvidos'] ?? []);
        $resolvidos[AssistenteResolvedorAluno::normalizar($nomeDigitado)] = [
            'id' => (int) $aluno['id'],
            'nome' => trim((string) ($aluno['nome'] ?? $nomeDigitado)),
        ];
        $rascunho['envolvidos_resolvidos'] = $resolvidos;
        $rascunho['envolvidos'] = array_values(array_filter(
            (array) ($rascunho['envolvidos'] ?? []),
            static fn($n) => AssistenteResolvedorAluno::normalizar((string) $n) !== AssistenteResolvedorAluno::normalizar($nomeDigitado)
        ));
        $rascunho['mais_envolvidos'] = true;
        return $rascunho;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    private static function semCamposAluno(array $args): array
    {
        unset($args['aluno_nome'], $args['ra'], $args['aluno_da_conversa'], $args['turma'], $args['aluno_id']);
        return $args;
    }

    /**
     * @param array<string,mixed> $resultado
     * @param array<string,mixed> $estado
     * @param array<string,mixed> $args
     * @param array<string,mixed>|null $aluno
     * @return array{resultado:array<string,mixed>,estado:array<string,mixed>,args:array<string,mixed>,parada:?string,aluno:?array}
     */
    private function saida(array $resultado, array $estado, array $args, ?string $parada, ?array $aluno): array
    {
        return ['resultado' => $resultado, 'estado' => $estado, 'args' => $args, 'parada' => $parada, 'aluno' => $aluno];
    }

    /**
     * @param list<array{tool:string,args:array}> $consultas
     * @param array<string,mixed>|null $aluno
     */
    private function registrarLog(string $mensagem, array $consultas, ?array $aluno, ?string $parada, float $inicio): void
    {
        try {
            \App\Core\Logger::info('assistente_v2_decisao', [
                'tenant_id' => defined('TENANT_ID') ? TENANT_ID : null,
                'modelo' => $this->modelo,
                'mensagem' => mb_substr($mensagem, 0, 300),
                'tools' => array_map(static fn(array $c) => [
                    'tool' => $c['tool'],
                    'args' => array_diff_key($c['args'], ['detalhe' => 1]),
                ], $consultas),
                'aluno_resolvido' => is_array($aluno) ? ['id' => (int) ($aluno['id'] ?? 0), 'nome' => (string) ($aluno['nome'] ?? '')] : null,
                'parada' => $parada,
                'latencia_ms' => (int) round((microtime(true) - $inicio) * 1000),
            ], 'assistente');
        } catch (\Throwable $e) {
            error_log('AssistenteMotorService log: ' . $e->getMessage());
        }
    }

    private function rotuloStatus(string $tool): string
    {
        return match (true) {
            $tool === 'boletim_aluno' => 'Montando boletim…',
            $tool === 'faltas_aluno' => 'Consultando frequência…',
            str_starts_with($tool, 'ocorrencias_') => 'Consultando ocorrências…',
            $tool === 'propor_criar_ocorrencia' => 'Preparando ocorrência…',
            str_starts_with($tool, 'propor_') => 'Preparando proposta…',
            str_contains($tool, 'jornada') => 'Consultando jornadas…',
            str_contains($tool, 'prova') => 'Consultando provas…',
            str_contains($tool, 'nota') => 'Consultando notas…',
            $tool === 'grade_horaria_consulta' => 'Consultando grade horária…',
            $tool === 'buscar_alunos' => 'Buscando aluno…',
            default => 'Consultando dados…',
        };
    }

    private function emitirEmPedacos(string $texto, callable $onTextoChunk): void
    {
        $len = mb_strlen($texto);
        for ($i = 0; $i < $len; $i += 48) {
            $onTextoChunk(mb_substr($texto, $i, 48));
        }
    }
}
