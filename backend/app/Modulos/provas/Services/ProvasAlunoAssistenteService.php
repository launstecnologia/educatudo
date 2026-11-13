<?php

namespace App\Modulos\Provas\Services;
/**
 * Assistente de Provas dos Alunos — chat NL para coordenação consultar
 * desempenho (provas, notas, acertos) via tools de leitura.
 */


use App\Core\CreditosModuleRegistry;
use App\Modulos\Jornadas\Services\JornadasAlunoConsultaService;
use App\Painel\Admin\Services\AssistenteAcaoPropostaService;
use App\Painel\Admin\Services\AssistenteConsultaAmpliadaService;

class ProvasAlunoAssistenteService
{
    public const MODULO_CREDITOS = 'provas_aluno_assistente_mensagem';
    /** Suspende a exigência e o débito de TudiCoins no Chat Assistente. */
    public const LIBERAR_SEM_TUDICOINS = true;
    public const DELIMITADOR_INICIO = '<<<CONSULTA>>>';
    public const DELIMITADOR_FIM = '<<<FIM>>>';
    private const MAX_RODADAS = 6;
    private const TOOLS_PROPOSTA = ['propor_criar_ocorrencia', 'propor_entrada_saida', 'propor_chamada_aula'];

    private ProvasAlunoConsultaService $consulta;
    private JornadasAlunoConsultaService $jornadas;
    private ProvasProfessorConsultaService $professores;
    private AssistenteConsultaAmpliadaService $ampliada;
    private AssistenteAcaoPropostaService $acoes;
    /** @var \App\Services\OpenAIService */
    private $openai;
    private int $usuarioIdContexto = 0;
    private int $conversaIdContexto = 0;
    /** @var array<string,mixed> */
    private array $usuarioContexto = [];

    public function __construct(
        ?ProvasAlunoConsultaService $consulta = null,
        ?JornadasAlunoConsultaService $jornadas = null,
        ?ProvasProfessorConsultaService $professores = null,
        ?AssistenteConsultaAmpliadaService $ampliada = null,
        $openai = null,
        ?AssistenteAcaoPropostaService $acoes = null
    ) {
        $this->consulta = $consulta ?? new ProvasAlunoConsultaService();
        $this->jornadas = $jornadas ?? new JornadasAlunoConsultaService($this->consulta);
        $this->professores = $professores ?? new ProvasProfessorConsultaService();
        $this->ampliada = $ampliada ?? new AssistenteConsultaAmpliadaService($this->consulta, $this->professores);
        $this->acoes = $acoes ?? new AssistenteAcaoPropostaService(null, $this->consulta);
        $this->openai = $openai instanceof \App\Services\OpenAIService
            ? $openai
            : new \App\Services\OpenAIService();
    }

    /** Contexto do admin/conversa para gravar propostas de escrita. */
    public function definirContextoAcao(int $usuarioId, int $conversaId = 0, ?array $usuario = null): void
    {
        $this->usuarioIdContexto = max(0, $usuarioId);
        $this->conversaIdContexto = max(0, $conversaId);
        $this->usuarioContexto = is_array($usuario) ? $usuario : [];
        if ($this->usuarioContexto === [] && $usuarioId > 0) {
            $this->usuarioContexto = ['id' => $usuarioId, 'tipo' => 'admin'];
        }
    }

    public function acoes(): AssistenteAcaoPropostaService
    {
        return $this->acoes;
    }

    public function consulta(): ProvasAlunoConsultaService
    {
        return $this->consulta;
    }

    /**
     * @param list<array{role:string,content:string,proposta_id?:int}> $historico
     * @param callable(string):void|null $onStatus
     * @param callable(string):void|null $onTextoChunk
     * @return array{success:bool,mensagem?:string,painel?:?array,consultas?:list,error?:string}
     */
    public function processarMensagem(
        string $mensagemUsuario,
        array $historico = [],
        ?callable $onStatus = null,
        ?callable $onTextoChunk = null
    ): array {
        $mensagemUsuario = trim($mensagemUsuario);
        if ($mensagemUsuario === '') {
            return ['success' => false, 'error' => 'Digite sua pergunta sobre o aluno (provas, jornadas…).'];
        }
        if (mb_strlen($mensagemUsuario) > 4000) {
            return ['success' => false, 'error' => 'Mensagem muito longa (máx. 4000 caracteres).'];
        }

        // Clique "Selecionar" no painel: nunca trate o id como RA; retome o pedido anterior.
        $mensagemUsuario = $this->reescreverSelecaoAluno($mensagemUsuario, $historico);
        $mensagemUsuario = $this->reescreverSelecaoAula($mensagemUsuario, $historico);
        if (preg_match('/^Turma\s+.+\s+turma_id=(\d+)$/iu', $mensagemUsuario, $mTurma) === 1) {
            $pedidoAnterior = $this->ultimoPedidoUsuario($historico, '/^Turma\s+.+\s+turma_id=\d+$/iu');
            if ($this->pedidoPareceGradeTurma($pedidoAnterior) || $this->pedidoPareceConsultaOcorrencia($pedidoAnterior)) {
                $mensagemUsuario = $pedidoAnterior . ' Turma selecionada: turma_id=' . (int) $mTurma[1];
            }
        }

        // Confirma apenas o card exibido na última resposta, por ID.
        $propostaExibida = $this->propostaExibidaNoHistorico($historico);
        if ($propostaExibida > 0 && $this->mensagemConfirmaProposta($mensagemUsuario)) {
            if ($this->usuarioIdContexto <= 0) {
                return ['success' => false, 'error' => 'Sessão inválida para confirmar proposta.'];
            }
            if ($onStatus !== null) {
                $onStatus('Confirmando proposta…');
            }
            $conf = $this->acoes->confirmar(
                $propostaExibida,
                $this->usuarioIdContexto,
                $this->usuarioContexto
            );
            if (!empty($conf['success'])) {
                $textoOk = (string) ($conf['mensagem'] ?? 'Proposta confirmada e gravada no sistema.');
                if ($onTextoChunk !== null) {
                    $this->emitirEmPedacos($textoOk, $onTextoChunk);
                }
                return [
                    'success' => true,
                    'mensagem' => $textoOk,
                    'painel' => null,
                    'consultas' => [['tool' => 'confirmar_proposta', 'args' => [
                        'proposta_id' => $propostaExibida,
                        'conversa_id' => $this->conversaIdContexto,
                    ]]],
                ];
            }
            $textoErro = (string) ($conf['error'] ?? 'Não foi possível confirmar a proposta.');
            if ($onTextoChunk !== null) {
                $this->emitirEmPedacos($textoErro, $onTextoChunk);
            }
            return [
                'success' => true,
                'mensagem' => $textoErro,
                'painel' => null,
                'consultas' => [],
            ];
        }

        try {
            $this->assertCreditosDisponiveis();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $mensagens = [];
        foreach (array_slice($historico, -10) as $msg) {
            $role = (string) ($msg['role'] ?? '');
            $content = trim((string) ($msg['content'] ?? ''));
            if ($content === '' || ($role !== 'user' && $role !== 'assistant')) {
                continue;
            }
            $mensagens[] = [
                'role' => $role,
                'content' => mb_substr($content, 0, 3000),
            ];
        }
        $mensagens[] = ['role' => 'user', 'content' => $mensagemUsuario];

        // Hint só para a OpenAI (não grava no histórico do usuário).
        $intencao = $this->classificarIntencaoPedido($mensagemUsuario, $historico);
        $campoOcorrencia = $this->assistentePediuCampoOcorrencia($historico);
        $continuacaoOcorrencia = $intencao === null
            && $campoOcorrencia !== null
            && $this->pedidoPareceContinuacaoEscrita($mensagemUsuario, $historico);
        $coletaOcorrencia = $continuacaoOcorrencia || ($intencao['intent'] ?? '') === 'criar_ocorrencia';
        if (($intencao['intent'] ?? '') === 'criar_ocorrencia') {
            $alunoExplicito = $this->resolverArgsAlunoParaConsulta($mensagemUsuario, []);
            if (!empty($alunoExplicito['ra']) || !empty($alunoExplicito['aluno_nome'])) {
                $mensagens = [['role' => 'user', 'content' => $mensagemUsuario]];
            }
        }
        $mensagensParaIa = $mensagens;
        if ($intencao !== null) {
            $hint = $this->montarHintRoteamento($intencao);
            if ($hint !== '') {
                $mensagensParaIa[count($mensagensParaIa) - 1] = [
                    'role' => 'user',
                    'content' => $mensagemUsuario . "\n\n" . $hint,
                ];
            }
        }

        $consultasFeitas = [];
        $ultimoResultado = null;
        $ultimaTool = null;
        $textoFinal = '';

        try {
            $consultaDireta = $coletaOcorrencia ? null : $this->consultaDiretaDoPedido($mensagemUsuario);
            if ($consultaDireta !== null) {
                $ultimaTool = $consultaDireta['tool'];
                if ($onStatus !== null) {
                    $onStatus($this->rotuloStatus($ultimaTool, $consultaDireta['args']));
                }
                $ultimoResultado = $this->executarTool($ultimaTool, $consultaDireta['args']);
                $consultasFeitas[] = $consultaDireta;
            }
            $usarModelo = !$coletaOcorrencia && $consultaDireta === null;
            $system = $usarModelo ? $this->montarSystemPrompt() : '';
            for ($rodada = 0; $usarModelo && $rodada < self::MAX_RODADAS; $rodada++) {
                $raw = $this->openai->chatCompletion($mensagensParaIa, $system, 'gpt-4o-mini', 0.25, 2800);
                $resposta = trim((string) ($raw['resposta'] ?? ''));
                $pedido = $this->extrairConsulta($resposta);

                if ($pedido === null) {
                    if ($consultasFeitas !== []) {
                        $textoFinal = $this->limparRespostaFinal($resposta);
                    }
                    break;
                }

                $tool = (string) ($pedido['tool'] ?? '');
                $args = is_array($pedido['args'] ?? null) ? $pedido['args'] : [];
                if (in_array($tool, self::TOOLS_PROPOSTA, true)
                    && (($intencao['modo'] ?? '') !== 'escrita' || $intencao['tool'] !== $tool)
                ) {
                    break;
                }
                $args = $this->aplicarFiltrosDoPedido($tool, $args, $mensagemUsuario);
                if ($onStatus !== null) {
                    $onStatus($this->rotuloStatus($tool, $args));
                }

                $resultadoTool = $this->executarTool($tool, $args);
                $consultasFeitas[] = ['tool' => $tool, 'args' => $args];
                $ultimoResultado = $resultadoTool;
                $ultimaTool = $tool;

                // Encerra diante de uma escolha humana ou de uma tentativa de proposta.
                if (in_array($tool, self::TOOLS_PROPOSTA, true)
                    || str_starts_with((string) ($this->montarPainel($tool, $resultadoTool)['tipo'] ?? ''), 'candidatos')
                ) {
                    break;
                }
                if ($tool === ($intencao['tool'] ?? '') && !empty($resultadoTool['ok'])) {
                    $textoFinal = $this->montarRespostaDireta($mensagemUsuario, $tool, $resultadoTool) ?? '';
                    if ($textoFinal !== '') {
                        break;
                    }
                }

                $msgAssistente = ['role' => 'assistant', 'content' => $resposta];
                $corpoResultado = "Resultado da consulta ({$tool}):\n"
                    . json_encode($resultadoTool, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    . "\n\nPedido original do usuário: «{$mensagemUsuario}»\n"
                    . "Use esses dados (não invente). Se precisar de outra consulta, emita <<<CONSULTA>>> de novo. "
                    . 'Se o usuário pediu questões erradas/detalhe e você ainda NÃO chamou detalhar_prova_aluno (com prova_id do contexto e somente_erros=true), chame agora. '
                    . 'Caso contrário, responda de forma ASSERTIVA: 1–2 frases respondendo EXATAMENTE o pedido, com o valor em **negrito**. '
                    . 'Ex.: "A nota de Matemática da Júlia Martiz (2ª B) é **10**." '
                    . 'A UI monta tabelas/PDF — não diga que não consegue se o resultado trouxe dados.';
                $msgResultado = ['role' => 'user', 'content' => $corpoResultado];
                $msgResultadoIa = $msgResultado;
                if ($intencao !== null) {
                    $hintLoop = $this->montarHintRoteamento($intencao);
                    if ($hintLoop !== '') {
                        $msgResultadoIa = [
                            'role' => 'user',
                            'content' => $corpoResultado . "\n" . $hintLoop,
                        ];
                    }
                }
                $mensagens[] = $msgAssistente;
                $mensagens[] = $msgResultado;
                $mensagensParaIa[] = $msgAssistente;
                $mensagensParaIa[] = $msgResultadoIa;
            }
        } catch (\Throwable $e) {
            error_log('ProvasAlunoAssistenteService: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Falha ao consultar a IA. Tente novamente.'];
        }

        $direta = $this->montarRespostaDireta($mensagemUsuario, $ultimaTool, $ultimoResultado);
        if ($direta !== null) {
            $textoFinal = $direta;
        }

        // Fallback: pedido de "qual errou" + última tool foi lista → detalha automaticamente.
        if (
            $this->pedidoPedeDetalheQuestoes($mensagemUsuario)
            && $ultimaTool !== 'detalhar_prova_aluno'
        ) {
            $provaAuto = 0;
            $alunoAuto = 0;
            if (
                $ultimaTool === 'listar_provas_aluno'
                && is_array($ultimoResultado)
                && !empty($ultimoResultado['provas'])
                && is_array($ultimoResultado['provas'])
            ) {
                $provaAuto = $this->escolherProvaParaDetalhe($ultimoResultado['provas'], $mensagemUsuario);
                $alunoAuto = (int) ($ultimoResultado['aluno']['id'] ?? 0);
            }
            if ($provaAuto <= 0 || $alunoAuto <= 0) {
                $ctx = $this->extrairContextoProvasDoHistorico($mensagens);
                $alunoAuto = $alunoAuto > 0 ? $alunoAuto : $ctx['aluno_id'];
                $provaAuto = $provaAuto > 0 ? $provaAuto : $this->escolherProvaParaDetalhe($ctx['provas'], $mensagemUsuario);
            }
            // Sem prova_id no histórico: lista provas do aluno e escolhe.
            if ($provaAuto <= 0 && $alunoAuto > 0) {
                if ($onStatus !== null) {
                    $onStatus('Buscando prova para detalhar…');
                }
                $listaAuto = $this->consulta->listarProvasAluno([
                    'aluno_id' => $alunoAuto,
                    'limite' => 30,
                    'status' => 'finalizado',
                ]);
                $consultasFeitas[] = ['tool' => 'listar_provas_aluno', 'args' => ['aluno_id' => $alunoAuto]];
                if (!empty($listaAuto['ok']) && !empty($listaAuto['provas'])) {
                    $provaAuto = $this->escolherProvaParaDetalhe($listaAuto['provas'], $mensagemUsuario);
                }
            }
            if ($provaAuto > 0 && $alunoAuto > 0) {
                if ($onStatus !== null) {
                    $onStatus('Detalhando questões…');
                }
                $ultimoResultado = $this->consulta->detalharProvaAluno($alunoAuto, $provaAuto, [
                    'somente_erros' => true,
                ]);
                $ultimaTool = 'detalhar_prova_aluno';
                $consultasFeitas[] = [
                    'tool' => 'detalhar_prova_aluno',
                    'args' => ['aluno_id' => $alunoAuto, 'prova_id' => $provaAuto, 'somente_erros' => true],
                ];
                if (!empty($ultimoResultado['ok'])) {
                    $textoFinal = 'Segue o detalhe das questões com erro.';
                }
            }
        }

        if (
            $ultimaTool === 'detalhar_prova_aluno'
            && is_array($ultimoResultado)
            && empty($ultimoResultado['ok'])
            && !empty($ultimoResultado['candidatos_provas'])
        ) {
            $textoFinal = 'Encontrei mais de uma prova. Qual delas você quer detalhar?';
        }

        if (
            in_array($ultimaTool, self::TOOLS_PROPOSTA, true)
            && is_array($ultimoResultado)
            && !empty($ultimoResultado['ok'])
            && !empty($ultimoResultado['proposta_id'])
        ) {
            $det = is_array($ultimoResultado['detalhes'] ?? null)
                ? $ultimoResultado['detalhes']
                : $this->acoes->detalhesProposta(
                    (string) ($ultimoResultado['tipo'] ?? ''),
                    is_array($ultimoResultado['payload'] ?? null) ? $ultimoResultado['payload'] : []
                );
            $textoFinal = 'Preparei uma proposta de **'
                . trim((string) ($det['tipo_label'] ?? 'ação'))
                . '**. Revise o título, a descrição, a data/hora e os envolvidos abaixo e confirme.';
        }

        // Conferência server-side por RA/nome: a IA não pode inventar "não encontrei".
        $identidade = $coletaOcorrencia || in_array($ultimaTool, self::TOOLS_PROPOSTA, true) ? [] : $this->garantirIdentidadeAluno(
            $mensagemUsuario,
            $mensagens,
            $ultimaTool,
            $ultimoResultado,
            $onStatus
        );
        if (!empty($identidade['consulta'])) {
            $consultasFeitas[] = $identidade['consulta'];
        }
        if (!empty($identidade['tool'])) {
            $ultimaTool = $identidade['tool'];
        }
        if (is_array($identidade['resultado'] ?? null)) {
            $ultimoResultado = $identidade['resultado'];
        }
        if (!empty($identidade['texto'])) {
            $textoFinal = (string) $identidade['texto'];
        } elseif (
            is_array($identidade['resultado']['aluno'] ?? null)
            && preg_match('/n[aã]o\s+encontrei|n[aã]o\s+achei|nenhum\s+aluno|sem\s+registros/iu', (string) $textoFinal) === 1
        ) {
            // IA inventou "não encontrei", mas o RA existe no cadastro.
            $textoFinal = $this->montarRespostaIdentidadeAluno(
                $mensagemUsuario,
                $identidade['resultado']['aluno']
            );
            if (($ultimaTool ?? '') === '' || $ultimaTool === null) {
                $ultimaTool = 'buscar_alunos';
            }
        }

        // Pedido de boletim: garante consulta real e nunca afirma quadro/PDF sem conteúdo.
        if ($this->pedidoPareceBoletim($mensagemUsuario, $mensagens)) {
            $garantia = $this->garantirConsultaBoletim(
                $mensagemUsuario,
                $mensagens,
                $ultimaTool,
                $ultimoResultado,
                $onStatus
            );
            $ultimaTool = $garantia['tool'];
            $ultimoResultado = $garantia['resultado'];
            if (!empty($garantia['consulta'])) {
                $consultasFeitas[] = $garantia['consulta'];
            }
            $temCandidatosAluno = is_array($ultimoResultado)
                && !empty($ultimoResultado['candidatos'])
                && is_array($ultimoResultado['candidatos']);
            if ($this->boletimTemConteudoExibivel($ultimoResultado)) {
                $diretaBoletim = $this->montarRespostaDireta($mensagemUsuario, 'boletim_aluno', $ultimoResultado);
                $textoFinal = $diretaBoletim ?? $textoFinal;
            } elseif ($temCandidatosAluno) {
                $textoFinal = trim((string) ($ultimoResultado['aviso'] ?? 'Há mais de um aluno. Escolha um:'));
            } else {
                $erroBol = is_array($ultimoResultado)
                    ? trim((string) ($ultimoResultado['error'] ?? $ultimoResultado['aviso'] ?? ''))
                    : '';
                $alunoBol = is_array($ultimoResultado['aluno'] ?? null) ? $ultimoResultado['aluno'] : [];
                $nomeBol = trim((string) ($alunoBol['nome'] ?? ''));
                $textoFinal = $erroBol !== ''
                    ? $erroBol
                    : ('Não encontrei boletim com notas para exibir'
                        . ($nomeBol !== '' ? ' d' . $this->artigoDe($nomeBol) . ' ' . $nomeBol : '')
                        . '. Verifique o RA/turma ou se há regra de boletim no ano letivo.');
            }
        }

        // Nota de coluna do boletim (PP/PU/MU…): mesma célula do quadro — nunca lançamento bruto.
        if ($this->pedidoPareceNotaColunaBoletim($mensagemUsuario, $mensagens)) {
            $garantiaNota = $this->garantirNotaColunaBoletim(
                $mensagemUsuario,
                $mensagens,
                $ultimaTool,
                $ultimoResultado,
                $onStatus
            );
            if (!empty($garantiaNota['consulta'])) {
                $consultasFeitas[] = $garantiaNota['consulta'];
            }
            if (!empty($garantiaNota['tool'])) {
                $ultimaTool = $garantiaNota['tool'];
            }
            if (is_array($garantiaNota['resultado'] ?? null)) {
                $ultimoResultado = $garantiaNota['resultado'];
            }
            if (!empty($garantiaNota['texto'])) {
                $textoFinal = (string) $garantiaNota['texto'];
            }
        }

        // Nome ambíguo (ex.: só "Ana"): força chips clicáveis via buscar_alunos.
        // Não sobrescreve resultado já resolvido (nota/boletim/proposta).
        $jaResolvidoEstruturado = is_array($ultimoResultado) && (
            !empty($ultimoResultado['proposta_id'])
            || ($ultimoResultado['formato'] ?? '') === 'nota_unica'
            || $this->boletimTemConteudoExibivel($ultimoResultado)
            || !empty($ultimoResultado['candidatos_bimestres'])
        );
        if (
            !$jaResolvidoEstruturado
            && $this->pedidoPareceBuscaAlunoAmbiguo($mensagemUsuario, $ultimaTool, $ultimoResultado)
        ) {
            $garantiaAlunos = $this->garantirCandidatosAluno($mensagemUsuario, $onStatus);
            if (!empty($garantiaAlunos['consulta'])) {
                $consultasFeitas[] = $garantiaAlunos['consulta'];
            }
            if (!empty($garantiaAlunos['tool'])) {
                $ultimaTool = $garantiaAlunos['tool'];
            }
            if (is_array($garantiaAlunos['resultado'] ?? null)) {
                $ultimoResultado = $garantiaAlunos['resultado'];
            }
            if (!empty($garantiaAlunos['texto'])) {
                $textoFinal = (string) $garantiaAlunos['texto'];
            }
        }

        // CONSULTA de faltas (leitura) — nunca vira ocorrência/criação.
        // Não sobrescreve boletim se o pedido era híbrido ("boletim e faltas").
        if (
            $this->pedidoPareceConsultaFaltas($mensagemUsuario)
            && !$this->pedidoPareceBoletim($mensagemUsuario, $mensagens)
            && $ultimaTool !== 'boletim_aluno'
        ) {
            $gFa = $this->garantirConsultaFaltas(
                $mensagemUsuario,
                $mensagens,
                $ultimaTool,
                $ultimoResultado,
                $onStatus
            );
            if (!empty($gFa['consulta'])) {
                $consultasFeitas[] = $gFa['consulta'];
            }
            if (!empty($gFa['tool'])) {
                $ultimaTool = $gFa['tool'];
            }
            if (is_array($gFa['resultado'] ?? null)) {
                $ultimoResultado = $gFa['resultado'];
            }
            if (!empty($gFa['texto'])) {
                $textoFinal = (string) $gFa['texto'];
            }
        }

        // CONSULTA de ocorrências (leitura) tem prioridade sobre rascunho de criação.
        if (($intencao['tool'] ?? '') === 'ocorrencias_turma' && $ultimaTool !== 'ocorrencias_turma') {
            $turmaOc = $this->extrairNomeTurma($mensagemUsuario);
            if ($turmaOc !== null) {
                $argsOc = $this->aplicarFiltrosDoPedido('ocorrencias_turma', ['turma_nome' => $turmaOc], $mensagemUsuario);
                $ultimaTool = 'ocorrencias_turma';
                $ultimoResultado = $this->ampliada->ocorrenciasTurma($argsOc);
                $consultasFeitas[] = ['tool' => $ultimaTool, 'args' => $argsOc];
                $textoFinal = $this->montarRespostaDireta($mensagemUsuario, $ultimaTool, $ultimoResultado) ?? '';
            }
        } elseif ($this->pedidoPareceConsultaOcorrencia($mensagemUsuario) && $ultimaTool !== 'ocorrencias_turma') {
            $gOc = $this->garantirConsultaOcorrencias(
                $mensagemUsuario,
                $mensagens,
                $ultimaTool,
                $ultimoResultado,
                $onStatus
            );
            if (!empty($gOc['consulta'])) {
                $consultasFeitas[] = $gOc['consulta'];
            }
            if (!empty($gOc['tool'])) {
                $ultimaTool = $gOc['tool'];
            }
            if (is_array($gOc['resultado'] ?? null)) {
                $ultimoResultado = $gOc['resultado'];
            }
            if (!empty($gOc['texto'])) {
                $textoFinal = (string) $gOc['texto'];
            }
        }

        // Registro (ocorrência/entrada/saída/chamada): exige painel com botão Confirmar.
        // Não sequestrar consultas de leitura já resolvidas.
        $toolConsultaOcorrencia = in_array($ultimaTool, ['ocorrencias_aluno', 'ocorrencias_turma'], true);
        $toolConsultaFaltas = ($ultimaTool === 'faltas_aluno');
        $ehConsultaOc = $this->pedidoPareceConsultaOcorrencia($mensagemUsuario);
        $ehConsultaFa = $this->pedidoPareceConsultaFaltas($mensagemUsuario);
        $continuacaoEscrita = !$ehConsultaOc && !$ehConsultaFa
            && !$this->mensagemParecePerguntaNova($mensagemUsuario)
            && $this->pedidoPareceContinuacaoEscrita($mensagemUsuario, $mensagens);
        if (
            !$ehConsultaOc
            && !$ehConsultaFa
            && !$toolConsultaOcorrencia
            && !$toolConsultaFaltas
            && ($coletaOcorrencia || ($intencao['modo'] ?? '') === 'escrita' || ($intencao === null && $continuacaoEscrita))
        ) {
            $garantiaProp = $this->garantirPropostaEscrita(
                $mensagemUsuario,
                $mensagens,
                $ultimaTool,
                $ultimoResultado,
                $textoFinal,
                $onStatus
            );
            if (!empty($garantiaProp['consulta'])) {
                $consultasFeitas[] = $garantiaProp['consulta'];
            }
            if (!empty($garantiaProp['tool'])) {
                $ultimaTool = $garantiaProp['tool'];
            }
            if (is_array($garantiaProp['resultado'] ?? null)) {
                $ultimoResultado = $garantiaProp['resultado'];
            }
            if (!empty($garantiaProp['texto'])) {
                $textoFinal = (string) $garantiaProp['texto'];
            }
        }

        // Jornadas pelo mesmo RA/nome que já funciona no boletim.
        if (!$coletaOcorrencia && $this->pedidoPareceJornadasAluno($mensagemUsuario)) {
            $gJor = $this->garantirJornadasAluno(
                $mensagemUsuario,
                $mensagens,
                $ultimaTool,
                $ultimoResultado,
                $onStatus
            );
            if (!empty($gJor['consulta'])) {
                $consultasFeitas[] = $gJor['consulta'];
            }
            if (!empty($gJor['tool'])) {
                $ultimaTool = $gJor['tool'];
            }
            if (is_array($gJor['resultado'] ?? null)) {
                $ultimoResultado = $gJor['resultado'];
            }
            if (!empty($gJor['texto'])) {
                $textoFinal = (string) $gJor['texto'];
            }
        }

        // Grade por nome de turma (sem pedir aluno/ID).
        if ($this->pedidoPareceGradeTurma($mensagemUsuario) && !$this->pedidoPareceGradeProfessor($mensagemUsuario, $mensagens)) {
            $gTurma = $this->garantirGradeTurma(
                $mensagemUsuario,
                $mensagens,
                $ultimaTool,
                $ultimoResultado,
                $onStatus
            );
            if (!empty($gTurma['consulta'])) {
                $consultasFeitas[] = $gTurma['consulta'];
            }
            if (!empty($gTurma['tool'])) {
                $ultimaTool = $gTurma['tool'];
            }
            if (is_array($gTurma['resultado'] ?? null)) {
                $ultimoResultado = $gTurma['resultado'];
            }
            if (!empty($gTurma['texto'])) {
                $textoFinal = (string) $gTurma['texto'];
            }
        }

        // Grade do professor / ranking de erros: nunca terminar em "Um momento…".
        if ($this->pedidoPareceGradeProfessor($mensagemUsuario, $mensagens)) {
            $gGrade = $this->garantirGradeProfessor(
                $mensagemUsuario,
                $mensagens,
                $ultimaTool,
                $ultimoResultado,
                $onStatus
            );
            if (!empty($gGrade['consulta'])) {
                $consultasFeitas[] = $gGrade['consulta'];
            }
            if (!empty($gGrade['tool'])) {
                $ultimaTool = $gGrade['tool'];
            }
            if (is_array($gGrade['resultado'] ?? null)) {
                $ultimoResultado = $gGrade['resultado'];
            }
            if (!empty($gGrade['texto'])) {
                $textoFinal = (string) $gGrade['texto'];
            }
        }
        if ($this->pedidoPareceRankingErros($mensagemUsuario, $mensagens)) {
            $gRank = $this->garantirRankingErros(
                $mensagemUsuario,
                $mensagens,
                $ultimaTool,
                $ultimoResultado,
                $onStatus
            );
            if (!empty($gRank['consulta'])) {
                $consultasFeitas[] = $gRank['consulta'];
            }
            if (!empty($gRank['tool'])) {
                $ultimaTool = $gRank['tool'];
            }
            if (is_array($gRank['resultado'] ?? null)) {
                $ultimoResultado = $gRank['resultado'];
            }
            if (!empty($gRank['texto'])) {
                $textoFinal = (string) $gRank['texto'];
            }
        }

        // IA às vezes responde só "Um momento…" sem painel — força texto útil.
        if ($textoFinal === '' || $this->textoPareceAdiamento($textoFinal)) {
            $diretaPos = $this->montarRespostaDireta($mensagemUsuario, $ultimaTool, $ultimoResultado);
            if ($diretaPos !== null) {
                $textoFinal = $diretaPos;
            } elseif (is_array($ultimoResultado) && !empty($ultimoResultado['error'])) {
                $textoFinal = (string) $ultimoResultado['error'];
            } elseif (is_array($ultimoResultado) && !empty($ultimoResultado['aviso'])) {
                $textoFinal = (string) $ultimoResultado['aviso'];
            } else {
                $textoFinal = 'Ainda não consegui confirmar essa informação. Posso consultar boletim, faltas, ocorrências, provas, jornadas, grade e pendências de diário. Diga o que precisa e informe o aluno, a turma ou o professor.';
            }
        }

        try {
            $this->debitarCreditos();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $painel = $this->montarPainel($ultimaTool, $ultimoResultado);
        if ($consultaDireta !== null && $painel !== null && !empty($ultimoResultado['ok']) && $direta === null) {
            $textoFinal = 'Veja o resultado da consulta abaixo.';
        }
        if (str_starts_with((string) ($painel['tipo'] ?? ''), 'candidatos')) {
            $textoFinal = (string) ($painel['aviso'] ?? $textoFinal);
        } elseif (empty($ultimoResultado['ok']) && !empty($ultimoResultado['error']) && $painel === null) {
            $textoFinal = (string) $ultimoResultado['error'];
        }
        if (
            $this->pedidoPareceBoletim($mensagemUsuario, $mensagens)
            && !$this->boletimTemConteudoExibivel($ultimoResultado)
            && $painel !== null
            && ($painel['tipo'] ?? '') === 'boletim_aluno'
        ) {
            // Sem conteúdo real: não devolve painel fingindo sucesso.
            $painel = null;
        }

        // Com painel estruturado, evita stream de texto longo/feio — o front renderiza o painel.
        if ($painel === null && $onTextoChunk !== null) {
            $this->emitirEmPedacos($textoFinal, $onTextoChunk);
        }

        return [
            'success' => true,
            'mensagem' => $textoFinal,
            'painel' => $painel,
            'consultas' => $consultasFeitas,
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function executarTool(string $tool, array $args): array
    {
        switch ($tool) {
            case 'buscar_alunos':
                $ra = trim((string) ($args['ra'] ?? $args['aluno_ra'] ?? ''));
                $termo = trim((string) ($args['termo'] ?? $args['nome'] ?? ''));
                if ($ra !== '') {
                    $termo = 'ra ' . $ra;
                } elseif ($termo !== '' && ($extraido = $this->consulta->extrairCodigoRa($termo)) !== null) {
                    $termo = 'ra ' . $extraido;
                }
                $turma = trim((string) ($args['turma'] ?? $args['turma_nome'] ?? ''));
                $alunos = $this->consulta->buscarAlunos(
                    $termo,
                    20,
                    $turma !== '' ? $turma : null
                );
                $aviso = null;
                if ($alunos === [] && $turma !== '') {
                    $alunos = $this->consulta->buscarAlunos($termo, 15, null);
                    if ($alunos !== []) {
                        $aviso = 'Não encontrei com a turma informada. Candidatos pelo nome:';
                    }
                }
                $payload = ['ok' => true, 'alunos' => $alunos];
                if ($aviso !== null) {
                    $payload['aviso'] = $aviso;
                }
                return $payload;

            case 'listar_materias':
                return ['ok' => true, 'materias' => $this->consulta->listarMaterias()];

            case 'listar_tipos_avaliacao':
                return ['ok' => true, 'tipos' => $this->consulta->listarTiposAvaliacao()];

            case 'listar_provas_aluno':
                return $this->consulta->listarProvasAluno($args);

            case 'detalhar_prova_aluno':
                return $this->consulta->detalharProvaAluno(
                    (int) ($args['aluno_id'] ?? 0),
                    (int) ($args['prova_id'] ?? 0),
                    [
                        'somente_erros' => $args['somente_erros'] ?? false,
                        'materia_nome' => $args['materia_nome'] ?? $args['materia'] ?? '',
                        'titulo' => $args['titulo'] ?? $args['titulo_prova'] ?? '',
                    ]
                );

            case 'resumo_provas_aluno':
                return $this->consulta->resumoProvasAluno($args);

            case 'listar_materias_jornadas':
                return ['ok' => true, 'materias' => $this->jornadas->listarMaterias()];

            case 'listar_jornadas_aluno':
                return $this->jornadas->listarJornadasAluno($args);

            case 'detalhar_jornada_aluno':
                return $this->jornadas->detalharJornadaAluno(
                    (int) ($args['aluno_id'] ?? 0),
                    (int) ($args['jornada_id'] ?? 0),
                    ['somente_erros' => $args['somente_erros'] ?? false]
                );

            case 'resumo_jornadas_aluno':
                return $this->jornadas->resumoJornadasAluno($args);

            case 'buscar_professores':
                $termo = trim((string) ($args['termo'] ?? $args['nome'] ?? $args['professor_nome'] ?? ''));
                return ['ok' => true, 'professores' => $this->professores->buscarProfessores($termo, 20)];

            case 'listar_turmas_professor':
                $r = $this->professores->resolverProfessor($args);
                if (empty($r['ok'])) {
                    return $r;
                }
                return [
                    'ok' => true,
                    'professor' => $r['professor'],
                    'turmas' => $this->professores->listarTurmasProfessor((int) $r['professor']['id']),
                ];

            case 'listar_provas_professor':
                return $this->professores->listarProvasProfessor($args);

            case 'resumo_provas_professor':
                return $this->professores->resumoProvasProfessor($args);

            case 'detalhar_prova_professor':
                return $this->professores->detalharProvaProfessor(
                    (int) ($args['professor_id'] ?? 0),
                    (int) ($args['prova_id'] ?? 0)
                );

            case 'ranking_erros_prova_professor':
                return $this->rankingErrosProvaComFiltros($args);

            case 'saude_turmas_professor':
                return $this->professores->saudeTurmasProfessor($args);

            case 'saude_turma':
                return $this->ampliada->saudeTurma($args);

            case 'resumo_provas_turma':
                return $this->ampliada->resumoProvasTurma($args);

            case 'buscar_blocos':
                return $this->ampliada->buscarBlocos($args);

            case 'resultados_bloco':
                return $this->ampliada->resultadosBloco($args);

            case 'resumo_jornadas_professor':
                return $this->ampliada->resumoJornadasProfessor($args);

            case 'saude_academica':
                return $this->ampliada->saudeAcademica($args);

            case 'perfil_aluno_360':
                return $this->ampliada->perfilAluno360($args);

            case 'diario_pendencias':
                return $this->ampliada->diarioPendencias($args);

            case 'grade_horaria_consulta':
                return $this->ampliada->gradeHorariaConsulta($args);

            case 'ocorrencias_turma':
                return $this->ampliada->ocorrenciasTurma($args);

            case 'boletim_aluno':
                return $this->ampliada->boletimAluno($args);

            case 'notas_evento_aluno':
                return $this->ampliada->notasEventoAluno($args);

            case 'nota_evento_sigla':
                return $this->ampliada->notaEventoPorSigla($args);

            case 'listar_eventos_avaliativos':
                return $this->ampliada->listarEventosAvaliativos($args);

            case 'faltas_aluno':
                return $this->ampliada->faltasAluno($args);

            case 'ocorrencias_aluno':
                return $this->ampliada->ocorrenciasAluno($args);

            case 'propor_criar_ocorrencia':
                return $this->acoes->proporCriarOcorrencia(
                    $args,
                    $this->usuarioIdContexto,
                    $this->conversaIdContexto
                );

            case 'propor_entrada_saida':
                return $this->acoes->proporEntradaSaida(
                    $args,
                    $this->usuarioIdContexto,
                    $this->conversaIdContexto
                );

            case 'propor_chamada_aula':
                return $this->acoes->proporChamadaAula(
                    $args,
                    $this->usuarioIdContexto,
                    $this->conversaIdContexto
                );

            default:
                return ['ok' => false, 'error' => 'Tool desconhecida: ' . $tool];
        }
    }

    /**
     * @param array<string,mixed>|null $resultado
     * @return array<string,mixed>|null
     */
    private function montarPainel(?string $tool, ?array $resultado): ?array
    {
        if ($resultado === null || $tool === null) {
            return null;
        }
        if (
            in_array($tool, ['propor_criar_ocorrencia', 'propor_entrada_saida', 'propor_chamada_aula'], true)
            && !empty($resultado['ok'])
            && !empty($resultado['proposta_id'])
        ) {
            $payload = is_array($resultado['payload'] ?? null) ? $resultado['payload'] : [];
            $tipoAcao = (string) ($resultado['tipo'] ?? '');
            $detalhes = is_array($resultado['detalhes'] ?? null)
                ? $resultado['detalhes']
                : $this->acoes->detalhesProposta($tipoAcao, $payload);
            $alunoPainel = null;
            if (!empty($payload['alunos'][0]) && is_array($payload['alunos'][0])) {
                $alunoPainel = $payload['alunos'][0];
            }
            return [
                'tipo' => 'proposta',
                'proposta_id' => (int) $resultado['proposta_id'],
                'acao' => $tipoAcao,
                'proposta_tipo' => $tipoAcao,
                'resumo' => (string) ($resultado['resumo'] ?? ''),
                'detalhes' => $detalhes,
                'situacao' => (string) ($resultado['situacao'] ?? 'RASCUNHO'),
                'payload' => $payload,
                'aluno' => $alunoPainel,
            ];
        }
        if (!empty($resultado['candidatos_aulas']) && is_array($resultado['candidatos_aulas'])) {
            return [
                'tipo' => 'candidatos_aulas',
                'aviso' => (string) ($resultado['aviso'] ?? 'Há mais de uma aula. Escolha uma:'),
                'candidatos_aulas' => $resultado['candidatos_aulas'],
            ];
        }
        if (!empty($resultado['candidatos_bimestres']) && is_array($resultado['candidatos_bimestres'])) {
            return [
                'tipo' => 'candidatos_bimestres',
                'aviso' => (string) ($resultado['aviso'] ?? 'Há mais de um bimestre. Escolha um:'),
                'candidatos_bimestres' => $resultado['candidatos_bimestres'],
                'aluno' => $resultado['aluno'] ?? null,
                'coluna' => $resultado['coluna'] ?? null,
                'materia' => $resultado['materia'] ?? null,
                'ano_letivo' => $resultado['ano_letivo'] ?? null,
            ];
        }
        if (!empty($resultado['candidatos_materias']) && is_array($resultado['candidatos_materias'])) {
            return [
                'tipo' => 'candidatos_materias',
                'aviso' => (string) ($resultado['aviso'] ?? 'Há mais de uma matéria. Qual delas?'),
                'candidatos_materias' => $resultado['candidatos_materias'],
                'aluno' => $resultado['aluno'] ?? null,
                'coluna' => $resultado['coluna'] ?? null,
                'ano_letivo' => $resultado['ano_letivo'] ?? null,
                'bimestre' => $resultado['bimestre'] ?? null,
            ];
        }
        if (!empty($resultado['coleta_ocorrencia']) || !empty($resultado['aguardando_campo'])) {
            $painelColeta = [
                'tipo' => 'coleta_ocorrencia',
                'aviso' => (string) ($resultado['aviso'] ?? 'Complete os dados da ocorrência:'),
                'aguardando_campo' => (string) ($resultado['aguardando_campo'] ?? ''),
                'aluno' => $resultado['aluno'] ?? null,
                'payload' => $resultado['payload'] ?? null,
            ];
            if (!empty($resultado['candidatos_categorias']) && is_array($resultado['candidatos_categorias'])) {
                $painelColeta['candidatos_categorias'] = $resultado['candidatos_categorias'];
            }
            if (!empty($resultado['opcoes_gravidade']) && is_array($resultado['opcoes_gravidade'])) {
                $painelColeta['opcoes_gravidade'] = $resultado['opcoes_gravidade'];
            }
            if (!empty($resultado['opcoes_sim_nao']) && is_array($resultado['opcoes_sim_nao'])) {
                $painelColeta['opcoes_sim_nao'] = $resultado['opcoes_sim_nao'];
            }
            return $painelColeta;
        }
        if (!empty($resultado['candidatos_categorias']) && is_array($resultado['candidatos_categorias'])) {
            return [
                'tipo' => 'candidatos_categorias',
                'aviso' => (string) ($resultado['aviso'] ?? 'Digite a categoria ou escolha uma sugestão:'),
                'candidatos_categorias' => $resultado['candidatos_categorias'],
                'aluno' => $resultado['aluno'] ?? null,
                'payload' => $resultado['payload'] ?? null,
            ];
        }
        if (!empty($resultado['categorias']) && is_array($resultado['categorias']) && empty($resultado['ok'])) {
            return [
                'tipo' => 'candidatos_categorias',
                'aviso' => (string) ($resultado['aviso'] ?? $resultado['error'] ?? 'Escolha a categoria:'),
                'candidatos_categorias' => $resultado['categorias'],
            ];
        }
        if (!empty($resultado['candidatos']) && is_array($resultado['candidatos'])) {
            $primeiro = $resultado['candidatos'][0] ?? [];
            if (
                str_contains($tool, 'professor')
                && ($resultado['tipo_candidatos'] ?? '') !== 'turmas'
            ) {
                return [
                    'tipo' => 'candidatos_professores',
                    'aviso' => (string) ($resultado['aviso'] ?? 'Há mais de um professor. Escolha um:'),
                    'candidatos' => $resultado['candidatos'],
                ];
            }
            if (
                $tool === 'resultados_bloco'
                || isset($primeiro['bloco_id'])
            ) {
                return [
                    'tipo' => 'candidatos_blocos',
                    'aviso' => (string) ($resultado['aviso'] ?? 'Há mais de um bloco. Escolha um:'),
                    'candidatos' => $resultado['candidatos'],
                ];
            }
            if (($resultado['tipo_candidatos'] ?? '') === 'turmas'
                || in_array($tool, ['saude_turma', 'resumo_provas_turma', 'saude_academica', 'diario_pendencias', 'grade_horaria_consulta', 'ocorrencias_turma'], true)
            ) {
                return [
                    'tipo' => 'candidatos_turmas',
                    'aviso' => (string) ($resultado['aviso'] ?? 'Há mais de uma turma. Escolha uma:'),
                    'candidatos' => $resultado['candidatos'],
                ];
            }
            return [
                'tipo' => 'candidatos',
                'aviso' => (string) ($resultado['aviso'] ?? 'Há mais de um aluno. Escolha um:'),
                'candidatos' => $resultado['candidatos'],
            ];
        }
        if ($tool === 'buscar_alunos' && !empty($resultado['alunos']) && is_array($resultado['alunos'])) {
            if (count($resultado['alunos']) > 1 || !empty($resultado['aviso'])) {
                return [
                    'tipo' => 'candidatos',
                    'aviso' => (string) ($resultado['aviso'] ?? 'Encontrei mais de um aluno. Qual deles?'),
                    'candidatos' => $resultado['alunos'],
                ];
            }
        }
        if ($tool === 'resumo_provas_aluno' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'resumo',
                'aluno' => $resultado['aluno'] ?? null,
                'total_provas' => (int) ($resultado['total_provas'] ?? 0),
                'total_acertos' => (int) ($resultado['total_acertos'] ?? 0),
                'total_erros' => (int) ($resultado['total_erros'] ?? 0),
                'total_questoes' => (int) ($resultado['total_questoes'] ?? 0),
                'percentual_acerto' => $resultado['percentual_acerto'] ?? null,
                'por_tipo' => $resultado['por_tipo'] ?? [],
                'por_bimestre' => $resultado['por_bimestre'] ?? [],
                'por_materia' => $resultado['por_materia'] ?? [],
                'opcoes' => [
                    'Provas semanais (detalhe)',
                    'Provas bimestrais (detalhe)',
                    'Um bimestre específico (1º, 2º…)',
                    'Tudo detalhado',
                    'Só uma matéria',
                ],
            ];
        }
        if ($tool === 'listar_provas_aluno' && !empty($resultado['ok']) && isset($resultado['provas'])) {
            return [
                'tipo' => 'lista',
                'aluno' => $resultado['aluno'] ?? null,
                'total' => (int) ($resultado['total'] ?? count($resultado['provas'])),
                'provas' => $resultado['provas'],
                'filtros' => $resultado['filtros_aplicados'] ?? [],
                'opcoes' => [
                    'Filtrar por bimestre',
                    'Só bimestrais',
                    'Só semanais',
                    'Consolidado / resumo',
                ],
            ];
        }
        if ($tool === 'detalhar_prova_aluno' && !empty($resultado['candidatos_provas']) && is_array($resultado['candidatos_provas'])) {
            return [
                'tipo' => 'candidatos_provas',
                'aviso' => (string) ($resultado['error'] ?? 'Há mais de uma prova. Qual delas?'),
                'aluno' => $resultado['aluno'] ?? null,
                'candidatos_provas' => $resultado['candidatos_provas'],
            ];
        }
        if ($tool === 'detalhar_prova_aluno' && !empty($resultado['ok']) && !empty($resultado['detalhe'])) {
            return [
                'tipo' => 'detalhe_prova',
                'aluno' => $resultado['aluno'] ?? null,
                'detalhe' => $resultado['detalhe'],
                'opcoes' => [
                    'Só questões erradas',
                    'Todas as questões',
                    'Consolidado / resumo',
                    'Ver jornadas deste aluno',
                ],
            ];
        }
        if (
            in_array($tool, ['resumo_jornadas_aluno', 'listar_jornadas_aluno'], true)
            && !empty($resultado['candidatos'])
            && is_array($resultado['candidatos'])
        ) {
            return [
                'tipo' => 'candidatos',
                'aviso' => (string) ($resultado['aviso'] ?? 'Há mais de um aluno. Confirme pelo nome:'),
                'candidatos' => $resultado['candidatos'],
            ];
        }
        if ($tool === 'resumo_jornadas_aluno' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'resumo_jornadas',
                'aluno' => $resultado['aluno'] ?? null,
                'total_jornadas' => (int) ($resultado['total_jornadas'] ?? 0),
                'concluidas' => (int) ($resultado['concluidas'] ?? 0),
                'em_andamento' => (int) ($resultado['em_andamento'] ?? 0),
                'nao_iniciadas' => (int) ($resultado['nao_iniciadas'] ?? 0),
                'percentual_medio' => $resultado['percentual_medio'] ?? 0,
                'por_materia' => $resultado['por_materia'] ?? [],
                'por_bimestre' => $resultado['por_bimestre'] ?? [],
                'opcoes' => [
                    'Jornadas em andamento',
                    'Jornadas concluídas',
                    'Detalhe de uma jornada',
                    'Ver provas deste aluno',
                ],
            ];
        }
        if ($tool === 'listar_jornadas_aluno' && !empty($resultado['ok']) && isset($resultado['jornadas'])) {
            return [
                'tipo' => 'lista_jornadas',
                'aluno' => $resultado['aluno'] ?? null,
                'total' => (int) ($resultado['total'] ?? count($resultado['jornadas'])),
                'jornadas' => $resultado['jornadas'],
                'opcoes' => [
                    'Só concluídas',
                    'Só em andamento',
                    'Filtrar por bimestre',
                    'Consolidado de jornadas',
                    'Ver provas',
                ],
            ];
        }
        if ($tool === 'detalhar_jornada_aluno' && !empty($resultado['ok']) && !empty($resultado['detalhe'])) {
            return [
                'tipo' => 'detalhe_jornada',
                'aluno' => $resultado['aluno'] ?? null,
                'detalhe' => $resultado['detalhe'],
                'opcoes' => [
                    'Só exercícios errados',
                    'Todos os exercícios',
                    'Voltar ao resumo de jornadas',
                    'Ver provas deste aluno',
                ],
            ];
        }
        if ($tool === 'buscar_professores' && !empty($resultado['professores']) && is_array($resultado['professores'])) {
            if (count($resultado['professores']) > 1) {
                return [
                    'tipo' => 'candidatos_professores',
                    'aviso' => 'Encontrei mais de um professor. Qual deles?',
                    'candidatos' => $resultado['professores'],
                ];
            }
        }
        if (
            in_array($tool, [
                'listar_provas_professor', 'resumo_provas_professor', 'saude_turmas_professor', 'listar_turmas_professor',
                'diario_pendencias', 'grade_horaria_consulta', 'ranking_erros_prova_professor',
            ], true)
            && !empty($resultado['candidatos'])
            && is_array($resultado['candidatos'])
        ) {
            return [
                'tipo' => 'candidatos_professores',
                'aviso' => (string) ($resultado['aviso'] ?? 'Há mais de um professor. Escolha um:'),
                'candidatos' => $resultado['candidatos'],
            ];
        }
        if ($tool === 'resumo_provas_professor' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'resumo_professor',
                'professor' => $resultado['professor'] ?? null,
                'total_provas' => (int) ($resultado['total_provas'] ?? 0),
                'total_acertos' => (int) ($resultado['total_acertos'] ?? 0),
                'total_erros' => (int) ($resultado['total_erros'] ?? 0),
                'percentual_acerto' => $resultado['percentual_acerto'] ?? null,
                'por_materia' => $resultado['por_materia'] ?? [],
                'por_turma' => $resultado['por_turma'] ?? [],
                'opcoes' => [
                    'Listar provas deste professor',
                    'Saúde das turmas',
                    'Detalhe de uma prova',
                    'Ranking de erros de uma prova',
                ],
            ];
        }
        if ($tool === 'listar_provas_professor' && !empty($resultado['ok']) && isset($resultado['provas'])) {
            return [
                'tipo' => 'lista_provas_professor',
                'professor' => $resultado['professor'] ?? null,
                'total' => (int) ($resultado['total'] ?? count($resultado['provas'])),
                'provas' => $resultado['provas'],
                'opcoes' => [
                    'Consolidado do professor',
                    'Detalhe por aluno de uma prova',
                    'Questões mais erradas',
                    'Saúde das turmas',
                ],
            ];
        }
        if ($tool === 'detalhar_prova_professor' && !empty($resultado['ok']) && !empty($resultado['detalhe'])) {
            return [
                'tipo' => 'detalhe_prova_professor',
                'professor' => $resultado['professor'] ?? null,
                'detalhe' => $resultado['detalhe'],
                'opcoes' => [
                    'Ranking de erros desta prova',
                    'Consolidado do professor',
                    'Ver outro aluno desta turma',
                ],
            ];
        }
        if (
            $tool === 'ranking_erros_prova_professor'
            && !empty($resultado['candidatos_provas'])
            && is_array($resultado['candidatos_provas'])
        ) {
            return [
                'tipo' => 'candidatos_provas',
                'aviso' => (string) ($resultado['aviso'] ?? 'Há mais de uma prova. Qual delas para o ranking?'),
                'professor' => $resultado['professor'] ?? null,
                'candidatos_provas' => $resultado['candidatos_provas'],
            ];
        }
        if ($tool === 'ranking_erros_prova_professor' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'ranking_erros_professor',
                'professor' => $resultado['professor'] ?? null,
                'prova' => $resultado['prova'] ?? null,
                'questoes' => $resultado['questoes'] ?? [],
                'opcoes' => ['Detalhe por aluno', 'Outras provas do professor'],
            ];
        }
        if ($tool === 'saude_turmas_professor' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'saude_professor',
                'professor' => $resultado['professor'] ?? null,
                'ano_letivo' => $resultado['ano_letivo'] ?? null,
                'kpis' => $resultado['kpis'] ?? [],
                'alunos_atencao' => $resultado['alunos_atencao'] ?? [],
                'total_alunos' => (int) ($resultado['total_alunos'] ?? 0),
                'turmas' => $resultado['turmas'] ?? [],
                'opcoes' => [
                    'Só alunos críticos',
                    'Resumo de provas do professor',
                    'Filtrar por turma',
                ],
            ];
        }
        if ($tool === 'saude_turma' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'saude_turma',
                'turma' => $resultado['turma'] ?? null,
                'ano_letivo' => $resultado['ano_letivo'] ?? null,
                'kpis' => $resultado['kpis'] ?? [],
                'alunos_atencao' => $resultado['alunos_atencao'] ?? [],
                'total_alunos' => (int) ($resultado['total_alunos'] ?? 0),
                'opcoes' => [
                    'Resumo de provas desta turma',
                    'Só alunos críticos',
                    'Ver faltas de um aluno',
                ],
            ];
        }
        if ($tool === 'resumo_provas_turma' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'resumo_turma',
                'turma' => $resultado['turma'] ?? null,
                'total_provas' => (int) ($resultado['total_provas'] ?? 0),
                'total_acertos' => (int) ($resultado['total_acertos'] ?? 0),
                'total_erros' => (int) ($resultado['total_erros'] ?? 0),
                'percentual_acerto' => $resultado['percentual_acerto'] ?? null,
                'por_materia' => $resultado['por_materia'] ?? [],
                'opcoes' => [
                    'Saúde da turma',
                    'Resultados de um bloco de prova',
                    'Ver um aluno específico',
                ],
            ];
        }
        if ($tool === 'buscar_blocos' && !empty($resultado['ok']) && isset($resultado['blocos'])) {
            return [
                'tipo' => 'lista_blocos',
                'blocos' => $resultado['blocos'],
                'total' => (int) ($resultado['total'] ?? count($resultado['blocos'])),
                'opcoes' => ['Ver resultados de um bloco'],
            ];
        }
        if ($tool === 'resultados_bloco' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'resultados_bloco',
                'bloco' => $resultado['bloco'] ?? null,
                'indicadores' => $resultado['indicadores'] ?? [],
                'por_turma' => $resultado['por_turma'] ?? [],
                'questoes_mais_erradas' => $resultado['questoes_mais_erradas'] ?? [],
                'alunos_atencao' => $resultado['alunos_atencao'] ?? [],
                'opcoes' => [
                    'Questões mais erradas',
                    'Alunos em atenção',
                    'Buscar outro bloco',
                ],
            ];
        }
        if ($tool === 'resumo_jornadas_professor' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'resumo_jornadas_professor',
                'professor' => $resultado['professor'] ?? null,
                'totais' => $resultado['totais'] ?? [],
                'jornadas_no_escopo' => $resultado['jornadas_no_escopo'] ?? null,
                'alunos_atencao' => $resultado['alunos_atencao'] ?? [],
                'opcoes' => [
                    'Só alunos em atenção',
                    'Resumo de provas do professor',
                    'Filtrar por turma',
                ],
            ];
        }
        if ($tool === 'saude_academica' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'saude_academica',
                'ano_letivo' => $resultado['ano_letivo'] ?? null,
                'turma' => $resultado['turma'] ?? null,
                'natureza' => $resultado['natureza'] ?? null,
                'kpis' => $resultado['kpis'] ?? [],
                'alertas' => $resultado['alertas'] ?? [],
                'total_alertas' => (int) ($resultado['total_alertas'] ?? 0),
                'aviso' => $resultado['aviso'] ?? null,
                'opcoes' => [
                    'Só sem matrícula no ano',
                    'Filtrar por turma',
                    'Perfil 360 de um aluno',
                ],
            ];
        }
        if ($tool === 'perfil_aluno_360' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'perfil_aluno_360',
                'aluno' => $resultado['aluno'] ?? null,
                'ano_letivo' => $resultado['ano_letivo'] ?? null,
                'indicadores' => $resultado['indicadores'] ?? [],
                'atividade' => $resultado['atividade'] ?? [],
                'pendencias' => $resultado['pendencias'] ?? [],
                'destaques' => $resultado['destaques'] ?? [],
                'opcoes' => [
                    'Ver boletim deste aluno',
                    'Ver faltas deste aluno',
                    'Ver ocorrências deste aluno',
                ],
            ];
        }
        if ($tool === 'diario_pendencias' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'diario_pendencias',
                'periodo' => $resultado['periodo'] ?? null,
                'professor' => $resultado['professor'] ?? null,
                'turma' => $resultado['turma'] ?? null,
                'resumo' => $resultado['resumo'] ?? [],
                'diarios' => $resultado['diarios'] ?? [],
                'total' => (int) ($resultado['total'] ?? 0),
                'aviso' => $resultado['aviso'] ?? null,
                'opcoes' => [
                    'Só em atraso',
                    'Só atenção',
                    'Filtrar por professor',
                ],
            ];
        }
        if ($tool === 'grade_horaria_consulta' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'grade_horaria_consulta',
                'data' => $resultado['data'] ?? null,
                'dia_semana' => $resultado['dia_semana'] ?? null,
                'dia_semana_label' => $resultado['dia_semana_label'] ?? null,
                'escopo' => $resultado['escopo'] ?? null,
                'aulas' => $resultado['aulas'] ?? [],
                'aviso' => $resultado['aviso'] ?? null,
                'opcoes' => [
                    'Ver grade da turma hoje',
                    'Ver grade do professor hoje',
                    'Filtrar por período',
                ],
            ];
        }
        if ($tool === 'ocorrencias_turma' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'ocorrencias_turma',
                'turma' => $resultado['turma'] ?? null,
                'filtros' => $resultado['filtros'] ?? [],
                'total' => (int) ($resultado['total'] ?? 0),
                'por_gravidade' => $resultado['por_gravidade'] ?? [],
                'ocorrencias' => $resultado['ocorrencias'] ?? [],
                'aviso' => $resultado['aviso'] ?? null,
                'opcoes' => [
                    'Só ocorrências abertas',
                    'Só graves',
                    'Ver perfil 360 de um aluno',
                ],
            ];
        }
        if ($tool === 'boletim_aluno' && !empty($resultado['ok'])) {
            $alunoId = (int) ($resultado['aluno']['id'] ?? 0);
            $base = defined('URL') ? rtrim((string) URL, '/') : '';
            $pdfUrl = $alunoId > 0 ? ($base . '/admin/students/' . $alunoId . '/boletim/pdf') : null;
            $pdfOficial = $alunoId > 0
                ? ($base . '/admin/resultados-finais/aluno/' . $alunoId . '/boletim/pdf'
                    . (!empty($resultado['aluno']['turma_id'])
                        ? ('?turma_id=' . (int) $resultado['aluno']['turma_id'])
                        : ''))
                : null;
            return [
                'tipo' => 'boletim_aluno',
                'formato' => $resultado['formato'] ?? 'eventos',
                'aluno' => $resultado['aluno'] ?? null,
                'ano_letivo' => $resultado['ano_letivo'] ?? null,
                'regra' => $resultado['regra'] ?? null,
                'quadro' => $resultado['quadro'] ?? null,
                'eventos' => $resultado['eventos'] ?? [],
                'total_eventos' => (int) ($resultado['total_eventos'] ?? 0),
                'aviso' => $resultado['aviso'] ?? null,
                'pdf_url' => $pdfUrl,
                'pdf_oficial_url' => $pdfOficial,
                'opcoes' => [
                    'Notas do 1º bimestre',
                    'Ver ocorrências deste aluno',
                    'Ver faltas deste aluno',
                ],
            ];
        }
        if ($tool === 'nota_evento_sigla' && !empty($resultado['candidatos_eventos_sigla'])) {
            return [
                'tipo' => 'candidatos_eventos_sigla',
                'aluno' => $resultado['aluno'] ?? null,
                'aviso' => $resultado['aviso'] ?? 'Escolha o evento pela sigla:',
                'candidatos' => $resultado['candidatos_eventos_sigla'],
            ];
        }
        if ($tool === 'nota_evento_sigla' && !empty($resultado['eventos_disponiveis']) && empty($resultado['ok'])) {
            return [
                'tipo' => 'eventos_avaliativos_disponiveis',
                'aluno' => $resultado['aluno'] ?? null,
                'aviso' => $resultado['error'] ?? 'Eventos avaliativos desta escola:',
                'candidatos' => $resultado['eventos_disponiveis'],
            ];
        }
        if ($tool === 'listar_eventos_avaliativos' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'eventos_avaliativos_disponiveis',
                'aviso' => 'Eventos avaliativos cadastrados nesta escola:',
                'candidatos' => $resultado['eventos'] ?? [],
                'ano_letivo' => $resultado['ano_letivo'] ?? null,
            ];
        }
        if ($tool === 'nota_evento_sigla' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'nota_evento_sigla',
                'formato' => $resultado['formato'] ?? 'nota_unica',
                'aluno' => $resultado['aluno'] ?? null,
                'evento' => $resultado['evento'] ?? null,
                'materia' => $resultado['materia'] ?? null,
                'nota' => $resultado['nota'] ?? null,
                'notas' => $resultado['notas'] ?? [],
                'aviso' => $resultado['aviso'] ?? null,
            ];
        }
        if ($tool === 'notas_evento_aluno' && !empty($resultado['candidatos_eventos'])) {
            return [
                'tipo' => 'candidatos_eventos',
                'aluno' => $resultado['aluno'] ?? null,
                'aviso' => $resultado['aviso'] ?? 'Escolha o evento:',
                'candidatos' => $resultado['candidatos_eventos'],
            ];
        }
        if ($tool === 'notas_evento_aluno' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'notas_evento_aluno',
                'aluno' => $resultado['aluno'] ?? null,
                'ano_letivo' => $resultado['ano_letivo'] ?? null,
                'bimestre' => $resultado['bimestre'] ?? ($resultado['evento']['bimestre'] ?? null),
                'materia' => $resultado['materia'] ?? null,
                'evento' => $resultado['evento'] ?? null,
                'coluna' => $resultado['coluna'] ?? null,
                'quadro' => $resultado['quadro'] ?? null,
                'aviso' => $resultado['aviso'] ?? null,
                'opcoes' => [
                    'Ver boletim completo',
                    'Só a coluna MU',
                    'Ver faltas deste aluno',
                ],
            ];
        }
        if ($tool === 'faltas_aluno' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'faltas_aluno',
                'aluno' => $resultado['aluno'] ?? null,
                'periodo' => $resultado['periodo'] ?? null,
                'frequencia' => $resultado['frequencia'] ?? null,
                'turma_percentual' => $resultado['turma_percentual'] ?? null,
                'aviso' => $resultado['aviso'] ?? null,
                'opcoes' => [
                    'Ver ocorrências deste aluno',
                    'Ver boletim deste aluno',
                    'Saúde da turma',
                ],
            ];
        }
        if ($tool === 'ocorrencias_aluno' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'ocorrencias_aluno',
                'aluno' => $resultado['aluno'] ?? null,
                'periodo' => $resultado['periodo'] ?? null,
                'ocorrencias' => $resultado['ocorrencias'] ?? [],
                'total' => (int) ($resultado['total'] ?? 0),
                'aviso' => $resultado['aviso'] ?? null,
                'opcoes' => [
                    'Ver faltas deste aluno',
                    'Ver boletim deste aluno',
                    'Só ocorrências abertas',
                ],
            ];
        }
        return null;
    }

    private function montarSystemPrompt(): string
    {
        $tipos = $this->consulta->listarTiposAvaliacao();
        $tiposResumo = [];
        foreach (array_slice($tipos, 0, 30) as $t) {
            $tiposResumo[] = ['id' => $t['id'], 'nome' => $t['nome']];
        }

        $eventosCat = $this->ampliada->listarEventosAvaliativos([]);
        $eventosResumo = [];
        foreach (array_slice($eventosCat['eventos'] ?? [], 0, 40) as $ev) {
            $eventosResumo[] = [
                'id' => (int) ($ev['id'] ?? 0),
                'sigla' => (string) ($ev['sigla'] ?? ''),
                'nome' => (string) ($ev['nome'] ?? ''),
                'bimestre' => $ev['bimestre'] ?? null,
                'ano_letivo' => $ev['ano_letivo'] ?? null,
            ];
        }

        return <<<PROMPT
Você é o Assistente da EducaTudo (coordenação/direção).

Objetivo: consultar ALUNOS (provas/jornadas/boletim/faltas/ocorrências/perfil 360), TURMAS (saúde, provas, ocorrências, grade), DIÁRIO, BLOCOS de prova, PROFESSORES, e propor AÇÕES DE ESCRITA (ocorrência, entrada/saída, chamada) que o usuário confirma no botão.

MAPA RÁPIDO (pedido → tool) — use isto primeiro:
- ver/consultar/quais/tem ocorrências → ocorrencias_aluno (LEITURA; NÃO criar)
- criar/registrar ocorrência → propor_criar_ocorrencia (só depois da coleta conversacional)
- faltas/frequência/presença (consulta) → faltas_aluno (NÃO é ocorrência)
- boletim / quadro de notas → boletim_aluno
- nota de matéria ou coluna PP/PU/MU/CH/PE → notas_evento_aluno
- nota por sigla de Evento Avaliativo (PB, PO…) → nota_evento_sigla
- jornadas do aluno → resumo_jornadas_aluno
- grade / aula de hoje → grade_horaria_consulta
- perfil / visão geral do aluno → perfil_aluno_360
- registrar entrada/saída → propor_entrada_saida
- marcar presente/falta na chamada → propor_chamada_aula
Se a consulta falhar ou devolver candidatos, preserve o aviso da ferramenta. Para ações indisponíveis, explique o que pode ser consultado ou preparado.
Se a mensagem trouxer [hint_roteamento], OBEDEÇA: use a tool indicada e não invente outro fluxo.

Regras importantes:
1) Nunca invente notas, datas, progresso, enunciados, alunos ou professores — só dados das consultas/propostas.
2) Se citar turma (ex.: "2 Ano B", "2ªB"), SEMPRE passe args.turma / turma_nome (separado do nome).
3) NUNCA peça confirmação antes de consultar. Chame a tool primeiro. Se houver 1 aluno, use-o; se houver vários, mostre candidatos.
4) termo/aluno_nome = só o nome (ex.: "Maria Clara" = nome + sobrenome com LIKE %%token%%), NUNCA junte turma no nome ("Maria Clara do 2 Ano B" está errado).
5) Se o usuário informar RA/matrícula (ex.: "RA 24254" ou "RA SM24254"), passe args.ra com o código completo (aceita alfanumérico). NUNCA use aluno_id como RA. NUNCA peça id numérico ao usuário (aluno_id, turma_id, categoria_id, diario_aula_id, prova_id, professor_id) — localize sempre por nome, RA ou turma. Se a UI já trouxe aluno_id=76 no histórico interno, use esse id sem perguntar. Pronomes como "essa aluna"/"esse aluno" reusam o aluno já identificado na conversa.
   Interprete a frase inteira, incluindo pedidos informais. Em "Oi, gostaria de saber se tem ocorrência na aluna Ana Luiza", chame ocorrencias_aluno com aluno_nome="Ana Luiza". Use ra apenas para um código de matrícula informado, nunca para um nome. Só aplique ano ou data quando o pedido ou uma referência explícita à conversa indicar o período.
6) Ano letivo (ex.: 2024) vai em args.ano_letivo / args.ano, nunca dentro de aluno_nome ou ra.
7) Pedido sobre ALUNO + provas → resumo_provas_aluno ou listar_provas_aluno (última prova = listar e pegar a mais recente).
8) Pedido sobre ALUNO + jornadas → resumo_jornadas_aluno.
9) Pedido sobre TURMA sem professor → saude_turma / resumo_provas_turma.
10) Pedido sobre BLOCO → buscar_blocos / resultados_bloco.
11) Pedido sobre PROFESSOR → resumo_provas_professor / saude_turmas_professor / resumo_jornadas_professor.
12) Pedido de SAÚDE ACADÊMICA da escola / alunos com alerta cadastral → saude_academica.
13) Pedido de PERFIL 360 / visão geral de um aluno → perfil_aluno_360.
14) Pedido de DIÁRIO atrasado / pendências de diário → diario_pendencias.
15) Pedido de GRADE HORÁRIA / "qual aula hoje" → grade_horaria_consulta. Isso NÃO é chamada do Diário. Marcar presença/falta → propor_chamada_aula (só se houver aula no diário).
16) Pedido de BOLETIM completo → boletim_aluno. Pedido de UMA NOTA de matéria no boletim (ex.: "nota de matemática da Júlia") → notas_evento_aluno com materia="Matemática" (e bimestre/coluna se souber).
17) Colunas do BOLETIM (PP, PU, MU, CH, PE, MS, MA, REC, PF) → SEMPRE notas_evento_aluno com args.coluna="PP" (etc.), materia exata (ex.: "Matemática 1") e bimestre se souber. NUNCA use nota_evento_sigla para essas siglas — elas não são Eventos Avaliativos; o valor DEVE ser a mesma célula do quadro/PDF. Se a coluna existir em vários bimestres e o usuário não disse qual, a tool devolve candidatos — peça o bimestre. Nota por SIGLA/NOME de Evento Avaliativo cadastrado (ex.: "PB", "prova bimestral", "PO") → nota_evento_sigla. Compare com o catálogo abaixo; NUNCA invente o significado da sigla.
18) Frequência/presença/faltas (consulta) → faltas_aluno.
19) CONSULTA de ocorrência/incidente de ALUNO → ocorrencias_aluno. Ocorrências da TURMA → ocorrencias_turma. NUNCA use faltas_aluno para ocorrência. Frases como "consultar/ver/quais/tem ocorrências", "quero saber as ocorrências" = LEITURA (ocorrencias_aluno), NUNCA propor_criar_ocorrencia. Se havia coleta de criação em andamento e o usuário muda para consulta, ABANDONE a coleta e responda a lista.
20) Detalhe de questão → detalhar_prova_aluno com somente_erros=true.
21) RESPOSTA ASSERTIVA (obrigatório): responda EXATAMENTE o que foi pedido, em 1–2 frases, com o valor em destaque markdown (**10**). Exemplos:
    - "qual a nota de matemática da Júlia Martiz do 2B?" → "A nota de Matemática da Júlia Martiz (2ª B) é **10**."
    - "nota da Aline na PB de matemática" → use a sigla/nome cadastrados; "A nota da PB de Matemática da Aline … é **8,5**."
    - "boletim do João" → "**Boletim do João Silva (1ª A) — 2026.** O quadro completo está abaixo; use o botão para baixar o PDF."
    - "quantas faltas tem a Ana?" → "**Ana Souza tem 4 faltas (frequência 92%).**"
    NÃO enrole, NÃO invente opções extras, NÃO diga "segue um resumo" se o pedido era um número/nota.
22) Se a tool devolver candidatos/aviso, liste-os e peça escolha — não diga "não encontrei" se houver candidatos.
23) NUNCA invente quantidade de faltas/ocorrências/notas. Sempre chame a tool adequada. Se frequencia=null ou aviso de sem lançamentos, diga isso — não chute um número.
24) NUNCA escreva [contexto_estruturado], aluno_id=, prova_id= nem metadados internos na resposta ao usuário. Isso é só para a UI/histórico.
25) Saude_academica é alerta cadastral/matrícula/chamada da escola; para rendimento de uma turma use saude_turma.
26) CRIAR ocorrência / registrar incidente → OBRIGATÓRIO emitir <<<CONSULTA>>> propor_criar_ocorrencia. NUNCA peça "confirme" só em texto — sem a tool não há botão Confirmar. NÃO diga que criou; diga que preparou a proposta. Nunca afirme "ocorrência registrada" só porque o usuário escreveu "sim" sem a confirmação ter rodado.
27) Registrar ENTRADA ou SAÍDA (portaria) → OBRIGATÓRIO propor_entrada_saida (mesmo: botão Confirmar via tool, nunca só texto).
28) Marcar PRESENTE/FALTA na CHAMADA → OBRIGATÓRIO propor_chamada_aula. Se a tool disser que a aula só está na grade (sem diário), explique isso — não invente chamada.
29) Em ações de escrita, se nome ficar ambíguo, devolva candidatos clicáveis; nunca chute aluno_id. Categoria de ocorrência pelo NOME (ex.: Conversa). Preserve RA, turma e descrição entre mensagens. Sem titulo+detalhe+gravidade, complete com o que já houver no histórico — ainda assim NÃO simule confirmação em texto.
30) Follow-up "no mesmo bimestre" / "dessa mesma turma" → reaproveite ano_letivo, bimestre e turma_nome do [contexto_estruturado].

Para obter dados, emita EXATAMENTE:
<<<CONSULTA>>>
{"tool":"NOME","args":{...}}
<<<FIM>>>

Tools — alunos/provas:
1) buscar_alunos — {"termo":"Maria","turma":"2 Ano B"} ou {"ra":"24254"}
2) listar_materias / 3) listar_tipos_avaliacao
4) listar_provas_aluno / 5) detalhar_prova_aluno / 6) resumo_provas_aluno

Tools — jornadas (aluno):
7) listar_materias_jornadas / 8) listar_jornadas_aluno / 9) detalhar_jornada_aluno / 10) resumo_jornadas_aluno

Tools — professor:
11) buscar_professores / 12) listar_turmas_professor / 13) listar_provas_professor
14) resumo_provas_professor / 15) detalhar_prova_professor / 16) ranking_erros_prova_professor — {"professor_nome":"Ana"} ou {"professor_id":10,"prova_id":55}. Se faltar prova_id, a tool lista candidatos. NUNCA responda só "Um momento".
17) saude_turmas_professor / 18) resumo_jornadas_professor — {"professor_nome":"Ana","turma_nome":"2ªB"}

Tools — turma / bloco / diário / agenda / boletim / faltas / ocorrências:
19) saude_turma — {"turma_nome":"2 Ano B"} — KPIs crítico/atenção
20) resumo_provas_turma — {"turma_nome":"2 Ano B"} — totais acertos/erros por matéria
21) saude_academica — {"ano":2024} ou {"ano_letivo_id":4,"turma_nome":"1A"} — alertas cadastrais/matrícula
22) diario_pendencias — {"turma_nome":"1A","situacao":"atraso"} ou {"professor_nome":"Ana"} — diários pendentes
23) grade_horaria_consulta — {"turma_nome":"1A","data":"2026-11-13"} ou {"professor_nome":"Ana"} — aulas do dia. Se o usuário disser "hoje", use a data de hoje do contexto (fuso America/Sao_Paulo). Grade de PROFESSOR → passe professor_nome/professor_id. NUNCA responda só "Um momento".
24) buscar_blocos — {"titulo":"Simulado"} ou {"turma_nome":"2ªB"}
25) resultados_bloco — {"bloco_id":10} ou {"titulo":"Simulado 1"}
26) perfil_aluno_360 — {"ra":"24254","ano_letivo":2024} — resumo geral do aluno
27) boletim_aluno — {"ra":"24254","ano_letivo":2024} — quadro anual (PP/PU/CH/PE/MU por bimestre); a UI oferece PDF
28) notas_evento_aluno — {"ra":"24254","coluna":"PP","materia":"Matemática 1","bimestre":1,"ano_letivo":2024} — coluna do boletim (mesma célula do quadro). Use materia com número quando houver (Matemática 1 ≠ Matemática 2).
29) listar_eventos_avaliativos — {"ano_letivo":2026} ou {"termo":"bimestral"} — catálogo vivo (sigla+nome) desta escola
30) nota_evento_sigla — {"aluno_id":76,"sigla":"PB","materia":"Matemática"} — SÓ para Eventos Avaliativos cadastrados (NÃO use para PP/PU/MU/CH/PE).
31) faltas_aluno — {"ra":"24254","ano_letivo":2024} ou {"aluno_id":365} — frequência/presença (NÃO é ocorrência). Passe o mesmo ano_letivo da conversa.
32) ocorrencias_aluno — {"ra":"24254","ano_letivo":2024} ou {"aluno_id":123,"status":"aberta"} — incidentes disciplinares
33) ocorrencias_turma — {"turma_nome":"1A","status":"aberta"} ou {"turma_id":37} — incidentes da turma

Tools — ações (só rascunho; o usuário confirma no botão):
34) propor_criar_ocorrencia — só depois de coletar em conversa: categoria (usuário pode digitar), descrição do fato, gravidade (leve|moderado|grave), se há mais envolvidos, e se notifica os pais. Ex.: {"alunos":["João Silva"],"turma":"1A","titulo":"Conversa na sala","detalhe":"Conversou durante a explicação e não parou quando o professor pediu.","nivel_gravidade":"leve","categoria":"Comportamento / convivência","enviar_pais":false}. NUNCA proponha o card Confirmar com campos faltando; detalhe SEMPRE real (nunca "..."); categoria pelo NOME (nunca categoria_id)
35) propor_entrada_saida — {"aluno_nome":"João Silva","turma":"1A","tipo":"entrada","ocorrido_em":"2026-11-13 07:55"} — aceite 7h55 / 07:55
36) propor_chamada_aula — {"aluno_nome":"João Silva","turma":"1A","situacao":"presente","data":"13/11/2026","materia":"Matemática"} — data em BR ou ISO; nunca peça diario_aula_id

Eventos Avaliativos desta escola (fonte oficial — use só estes nomes/siglas):
PROMPT
            . json_encode($eventosResumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\nTipos de avaliação (provas) desta escola:\n"
            . json_encode($tiposResumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\nHoje (America/Sao_Paulo): " . $this->ampliada->hojeLocal()
            . "\n\nQuando já tiver os dados, responda de forma ASSERTIVA e formatada (sem <<<CONSULTA>>>), destacando o valor pedido em **negrito**.";
    }

    /**
     * Extrai aluno_id e provas do histórico (incluindo [contexto_estruturado]).
     *
     * @param list<array{role:string,content:string}> $mensagens
     * @return array{aluno_id:int,provas:list<array<string,mixed>>}
     */
    private function extrairContextoProvasDoHistorico(array $mensagens): array
    {
        $ctx = $this->extrairContextoConsultaDoHistorico($mensagens);
        return [
            'aluno_id' => (int) ($ctx['aluno_id'] ?? 0),
            'provas' => is_array($ctx['provas'] ?? null) ? $ctx['provas'] : [],
        ];
    }

    /**
     * Contexto rico da conversa: aluno, RA, turma, bimestre, ano, ocorrência em andamento.
     *
     * @param list<array{role?:string,content?:string}> $mensagens
     * @return array<string,mixed>
     */
    private function extrairContextoConsultaDoHistorico(array $mensagens): array
    {
        $alunoId = 0;
        $alunoNome = '';
        $ra = '';
        $turmaNome = '';
        $bimestre = 0;
        $anoLetivo = 0;
        $coluna = '';
        $materia = '';
        $ocorrenciaTitulo = '';
        $ocorrenciaDetalhe = '';
        $ocorrenciaCategoria = '';
        $ocorrenciaNivel = '';
        $ocorrenciaPais = null;
        $ocorrenciaPaisExplicito = false;
        $ocorrenciaEnvolvidosOk = false;
        $ocorrenciaEnvolvidos = [];
        $propostaPendente = false;
        $provas = [];

        foreach ($mensagens as $msg) {
            $c = (string) ($msg['content'] ?? '');
            if (preg_match('/aluno_id\s*=\s*(\d+)/', $c, $m)) {
                $alunoId = (int) $m[1];
            }
            if (preg_match('/aluno_nome="([^"]+)"/', $c, $mN)) {
                $alunoNome = trim($mN[1]);
            }
            if (preg_match('/\bra="([^"]+)"/', $c, $mRa)) {
                $ra = trim($mRa[1]);
            } elseif (
                str_contains($c, '[contexto_estruturado]') || str_contains($c, '[contexto_conversa]')
            ) {
                // Só lê RA livre dentro de blocos estruturados (evita falso positivo no chat).
                if (preg_match('/\bRA\s*[#:]?\s*([A-Za-z0-9]{3,20})\b/iu', $c, $mRa2) === 1) {
                    $cand = $mRa2[1];
                    if (preg_match('/^20\d{2}$/', $cand) !== 1) {
                        $ra = $cand;
                    }
                }
            }
            if (preg_match('/turma_nome="([^"]+)"/', $c, $mT)) {
                $turmaNome = trim($mT[1]);
            }
            if (preg_match('/\bbimestre\s*=\s*([1-4])\b/', $c, $mB) === 1) {
                $bimestre = (int) $mB[1];
            }
            if (preg_match('/\bano_letivo\s*=\s*(20\d{2})\b/', $c, $mA) === 1) {
                $anoLetivo = (int) $mA[1];
            }
            if (preg_match('/coluna="([^"]+)"/', $c, $mC)) {
                $coluna = trim($mC[1]);
            }
            if (preg_match('/materia="([^"]+)"/', $c, $mM)) {
                $materia = trim($mM[1]);
            }
            if (preg_match('/ocorrencia_titulo="([^"]+)"/', $c, $mOt)) {
                $ocorrenciaTitulo = trim($mOt[1]);
            }
            if (preg_match('/ocorrencia_detalhe="([^"]+)"/', $c, $mOd)) {
                $ocorrenciaDetalhe = trim($mOd[1]);
            }
            if (preg_match('/ocorrencia_categoria="([^"]+)"/', $c, $mOc)) {
                $ocorrenciaCategoria = trim($mOc[1]);
            }
            if (preg_match('/ocorrencia_nivel=(leve|moderado|grave)\b/u', $c, $mOn)) {
                $ocorrenciaNivel = strtolower($mOn[1]);
            }
            if (preg_match('/ocorrencia_pais=(0|1)\b/u', $c, $mOp)) {
                $ocorrenciaPais = $mOp[1] === '1';
                $ocorrenciaPaisExplicito = true;
            }
            if (preg_match('/ocorrencia_envolvidos_ok=1\b/u', $c) === 1) {
                $ocorrenciaEnvolvidosOk = true;
            }
            if (preg_match('/ocorrencia_envolvidos="([^"]+)"/', $c, $mOe)) {
                $parts = array_map('trim', explode('|', $mOe[1]));
                $ocorrenciaEnvolvidos = array_values(array_filter($parts, static fn ($n) => $n !== ''));
            }
            if (preg_match('/proposta_pendente\s*=\s*1|aguardando_categoria\s*=\s*1|coleta_ocorrencia\s*=\s*1/u', $c) === 1) {
                $propostaPendente = true;
            }
            if (preg_match_all(
                '/prova_id\s*=\s*(\d+)\s+titulo="([^"]*)"\s+materia="([^"]*)"(?:\s+erros=(\d+|—))?/',
                $c,
                $matches,
                PREG_SET_ORDER
            )) {
                foreach ($matches as $row) {
                    $provas[] = [
                        'prova_id' => (int) $row[1],
                        'titulo' => $row[2],
                        'materia' => ['nome' => $row[3]],
                        'realizacao' => [
                            'erros' => isset($row[4]) && $row[4] !== '—' ? (int) $row[4] : 0,
                        ],
                    ];
                }
            }
            if (preg_match('/prova_id\s+(\d+)/', $c, $m2)) {
                $pid = (int) $m2[1];
                $exists = false;
                foreach ($provas as $p) {
                    if ((int) $p['prova_id'] === $pid) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $provas[] = [
                        'prova_id' => $pid,
                        'titulo' => '',
                        'materia' => ['nome' => ''],
                        'realizacao' => ['erros' => 1],
                    ];
                }
            }
        }

        return [
            'aluno_id' => $alunoId,
            'aluno_nome' => $alunoNome,
            'ra' => $ra,
            'turma_nome' => $turmaNome,
            'bimestre' => $bimestre,
            'ano_letivo' => $anoLetivo,
            'coluna' => $coluna,
            'materia' => $materia,
            'ocorrencia_titulo' => $ocorrenciaTitulo,
            'ocorrencia_detalhe' => $ocorrenciaDetalhe,
            'ocorrencia_categoria' => $ocorrenciaCategoria,
            'ocorrencia_nivel' => $ocorrenciaNivel,
            'ocorrencia_pais' => $ocorrenciaPais,
            'ocorrencia_pais_explicito' => $ocorrenciaPaisExplicito,
            'ocorrencia_envolvidos_ok' => $ocorrenciaEnvolvidosOk,
            'ocorrencia_envolvidos' => $ocorrenciaEnvolvidos,
            'proposta_pendente' => $propostaPendente,
            'provas' => $provas,
        ];
    }

    private function pedidoPedeDetalheQuestoes(string $mensagem): bool
    {
        $m = mb_strtolower($mensagem);
        // "total de acertos/erros", consolidado, resumo → NÃO é detalhe de questão
        if (
            preg_match('/\b(total|quantidade|consolidado|resumo|quantos|qtd)\b/u', $m)
            && !preg_match('/\b(quest[aã]o|quest[oõ]es|enunciado|gabarito|alternativa|detalh|qual\s+el[ae]\s+errou)\b/u', $m)
        ) {
            return false;
        }
        foreach ([
            'qual errou', 'quais errou', 'quais erros', 'qual ela errou', 'qual ele errou',
            'questão', 'questao', 'questões', 'questoes',
            'detalhe', 'detalhar', 'gabarito', 'alternativa', 'enunciado',
        ] as $t) {
            if (str_contains($m, $t)) {
                return true;
            }
        }
        if (str_contains($m, 'errou')) {
            return true;
        }
        return false;
    }

    /**
     * @param list<array<string,mixed>> $provas
     */
    private function escolherProvaParaDetalhe(array $provas, string $mensagem): int
    {
        if ($provas === []) {
            return 0;
        }
        $m = mb_strtolower($mensagem);
        $candidatas = [];
        foreach ($provas as $p) {
            $mat = mb_strtolower(trim((string) ($p['materia']['nome'] ?? '')));
            $tit = mb_strtolower(trim((string) ($p['titulo'] ?? '')));
            if ($mat !== '' && str_contains($m, $mat)) {
                $candidatas[] = $p;
                continue;
            }
            // palavras do título com 4+ chars presentes na mensagem
            foreach (preg_split('/\s+/', $tit) ?: [] as $tok) {
                if (mb_strlen($tok) >= 5 && str_contains($m, $tok)) {
                    $candidatas[] = $p;
                    break;
                }
            }
        }
        if ($candidatas === []) {
            $candidatas = $provas;
        }
        $comErro = [];
        foreach ($candidatas as $p) {
            if ((int) ($p['realizacao']['erros'] ?? 0) > 0) {
                $comErro[] = $p;
            }
        }
        $pool = $comErro !== [] ? $comErro : $candidatas;
        if (count($pool) === 1) {
            return (int) ($pool[0]['prova_id'] ?? 0);
        }
        // Se só há uma na lista original, usa ela
        if (count($provas) === 1) {
            return (int) ($provas[0]['prova_id'] ?? 0);
        }
        return (int) ($pool[0]['prova_id'] ?? 0);
    }

    /** @return array{tool:string,args:array}|null */
    private function extrairConsulta(string $resposta): ?array
    {
        $ini = strpos($resposta, self::DELIMITADOR_INICIO);
        if ($ini === false) {
            return null;
        }
        $depois = substr($resposta, $ini + strlen(self::DELIMITADOR_INICIO));
        $fim = strpos($depois, self::DELIMITADOR_FIM);
        $jsonRaw = trim($fim === false ? $depois : substr($depois, 0, $fim));
        $jsonRaw = preg_replace('/^```(?:json)?\s*/i', '', $jsonRaw) ?? $jsonRaw;
        $jsonRaw = preg_replace('/\s*```$/', '', $jsonRaw) ?? $jsonRaw;
        $decoded = json_decode(trim($jsonRaw), true);
        if (!is_array($decoded) || empty($decoded['tool'])) {
            return null;
        }
        return [
            'tool' => (string) $decoded['tool'],
            'args' => is_array($decoded['args'] ?? null) ? $decoded['args'] : [],
        ];
    }

    /**
     * @param list<array{role?:string,content?:string}> $historico
     */
    private function reescreverSelecaoAluno(string $mensagem, array $historico): string
    {
        if (preg_match('/^usar\s+evento(?:\s+avaliativo)?(?:\s+id)?\s+(\d+)\b/iu', $mensagem, $mEv) === 1) {
            return $this->reescreverSelecaoEvento((int) $mEv[1], $mensagem, $historico);
        }
        // Chip novo: "Aluna Nome da turma X RA Y aluno_id=123"
        if (preg_match('/aluno_id\s*=\s*(\d+)/i', $mensagem, $mNovo) === 1
            && preg_match('/^alun[oa]\b/iu', $mensagem) === 1
        ) {
            $mensagem = 'Usar aluno_id ' . (int) $mNovo[1];
        }
        if (preg_match('/^usar\s+aluno_id\s+(\d+)\b/iu', $mensagem, $m) !== 1) {
            return $mensagem;
        }
        $alunoId = (int) $m[1];
        $pedidoAnterior = $this->ultimoPedidoUsuario($historico, '/^usar\s+aluno_id\s+\d+/iu');
        $evento = $this->extrairEventoAvaliativoDoTexto($pedidoAnterior !== '' ? $pedidoAnterior : $mensagem);
        $materia = $this->extrairMateriaDaPergunta($pedidoAnterior !== '' ? $pedidoAnterior : $mensagem);
        $acaoEscrita = $this->detectarTipoProposta($pedidoAnterior !== '' ? $pedidoAnterior : '');

        $parts = ['Aluno já escolhido: aluno_id=' . $alunoId . '.'];
        $parts[] = 'NÃO chame buscar_alunos. NÃO use esse número como RA.';
        if ($pedidoAnterior !== '') {
            $parts[] = 'Retome o pedido original: «' . mb_substr($pedidoAnterior, 0, 400) . '».';
        }
        if ($pedidoAnterior !== '' && $this->pedidoPareceRegistroEscrita($pedidoAnterior)) {
            if ($acaoEscrita === 'criar_ocorrencia') {
                $parts[] = 'Aluno definido. NÃO invente título/descrição. '
                    . 'Se faltar categoria ou detalhe do fato, peça ao usuário (ou mostre categorias). '
                    . 'Só emita propor_criar_ocorrencia quando houver categoria e descrição reais do ocorrido.';
            } else {
                $toolHint = match ($acaoEscrita) {
                    'entrada_saida' => 'propor_entrada_saida',
                    'chamada_aula' => 'propor_chamada_aula',
                    default => 'propor_criar_ocorrencia',
                };
                $parts[] = 'Emita ' . $toolHint . ' com aluno_id=' . $alunoId
                    . ' e os demais dados do pedido. Monte o rascunho com botão Confirmar.';
            }
        } elseif ($evento !== null) {
            $argsHint = [];
            if (!empty($evento['sigla'])) {
                $argsHint[] = 'sigla="' . $evento['sigla'] . '"';
            }
            if (!empty($evento['nome'])) {
                $argsHint[] = 'evento_nome="' . $evento['nome'] . '"';
            }
            if (!empty($evento['id'])) {
                $argsHint[] = 'evento_id=' . (int) $evento['id'];
            }
            $parts[] = 'Use nota_evento_sigla com ' . implode(', ', $argsHint)
                . ($materia !== null ? ' e materia="' . $materia . '"' : '')
                . ' e aluno_id=' . $alunoId . '. Responda com a nota, sem perguntar de novo.';
        } elseif ($materia !== null) {
            $parts[] = 'Consulte a nota de ' . $materia . ' com aluno_id=' . $alunoId . ' e responda direto.';
        } else {
            $parts[] = 'Continue a consulta pedida com aluno_id=' . $alunoId . ' imediatamente.';
        }
        return implode(' ', $parts);
    }

    /**
     * Chip de aula do diário: "… diario_aula_id=123".
     *
     * @param list<array{role?:string,content?:string}> $historico
     */
    private function reescreverSelecaoAula(string $mensagem, array $historico): string
    {
        if (preg_match('/diario_aula_id\s*=\s*(\d+)/i', $mensagem, $m) !== 1) {
            return $mensagem;
        }
        $aulaId = (int) $m[1];
        if ($aulaId <= 0) {
            return $mensagem;
        }
        $pedidoAnterior = $this->ultimoPedidoUsuario($historico, '/diario_aula_id\s*=\s*\d+/i');
        $ctx = $this->extrairContextoConsultaDoHistorico($historico);
        $parts = ['Aula do diário já escolhida: diario_aula_id=' . $aulaId . '.'];
        $parts[] = 'Emita propor_chamada_aula com diario_aula_id=' . $aulaId . '.';
        if (!empty($ctx['aluno_id'])) {
            $parts[] = 'aluno_id=' . (int) $ctx['aluno_id'] . '.';
        }
        if ($pedidoAnterior !== '') {
            $parts[] = 'Retome o pedido original: «' . mb_substr($pedidoAnterior, 0, 400) . '».';
        }
        $parts[] = 'Monte o rascunho com botão Confirmar.';
        return implode(' ', $parts);
    }

    /**
     * @param list<array{role?:string,content?:string}> $historico
     */
    private function reescreverSelecaoEvento(int $eventoId, string $mensagem, array $historico): string
    {
        $pedidoAnterior = $this->ultimoPedidoUsuario(
            $historico,
            '/^usar\s+(?:aluno_id\s+\d+|evento(?:\s+avaliativo)?(?:\s+id)?\s+\d+)/iu'
        );
        $materia = $this->extrairMateriaDaPergunta($pedidoAnterior !== '' ? $pedidoAnterior : $mensagem);
        $alunoId = 0;
        if (preg_match('/aluno_id\s*=\s*(\d+)/i', $pedidoAnterior, $mA) === 1) {
            $alunoId = (int) $mA[1];
        } else {
            foreach (array_reverse($historico) as $msg) {
                $c = (string) ($msg['content'] ?? '');
                if (preg_match('/aluno_id\s*=\s*(\d+)/i', $c, $mH) === 1) {
                    $alunoId = (int) $mH[1];
                    break;
                }
            }
        }
        $siglaMsg = '';
        if (preg_match('/\bsigla\s+([A-Za-z0-9]{1,12})\b/u', $mensagem, $mS) === 1) {
            $siglaMsg = strtoupper($mS[1]);
        }

        $parts = ['Evento avaliativo já escolhido: evento_id=' . $eventoId . '.'];
        if ($siglaMsg !== '') {
            $parts[] = 'sigla="' . $siglaMsg . '".';
        }
        if ($pedidoAnterior !== '') {
            $parts[] = 'Retome o pedido original: «' . mb_substr($pedidoAnterior, 0, 400) . '».';
        }
        $parts[] = 'Use nota_evento_sigla com evento_id=' . $eventoId
            . ($siglaMsg !== '' ? ' e sigla="' . $siglaMsg . '"' : '')
            . ($materia !== null ? ' e materia="' . $materia . '"' : '')
            . ($alunoId > 0 ? ' e aluno_id=' . $alunoId : '')
            . '. Responda com a nota, sem perguntar de novo.';
        return implode(' ', $parts);
    }

    /**
     * @param list<array{role?:string,content?:string}> $historico
     */
    private function ultimoPedidoUsuario(array $historico, ?string $ignorarPadrao = null): string
    {
        foreach (array_reverse($historico) as $msg) {
            if (($msg['role'] ?? '') !== 'user') {
                continue;
            }
            $c = trim((string) ($msg['content'] ?? ''));
            if ($c === '') {
                continue;
            }
            if ($ignorarPadrao !== null && $ignorarPadrao !== '' && preg_match($ignorarPadrao, $c) === 1) {
                continue;
            }
            return $c;
        }
        return '';
    }

    /**
     * Compara o texto com Eventos Avaliativos cadastrados (sigla ou nome).
     * Não usa lista fixa de siglas inventadas.
     *
     * @return array{id?:int,sigla?:string,nome?:string}|null
     */
    private function extrairEventoAvaliativoDoTexto(string $texto): ?array
    {
        $t = trim($texto);
        if ($t === '') {
            return null;
        }

        $catalogo = $this->ampliada->listarEventosAvaliativos([]);
        $eventos = is_array($catalogo['eventos'] ?? null) ? $catalogo['eventos'] : [];
        if ($eventos === []) {
            return null;
        }

        $textoNorm = mb_strtolower($t, 'UTF-8');
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
        ];
        $textoNorm = strtr($textoNorm, $map);

        $melhor = null;
        $melhorScore = 0;
        foreach ($eventos as $ev) {
            $sigla = strtoupper(trim((string) ($ev['sigla'] ?? '')));
            $nome = trim((string) ($ev['nome'] ?? ''));
            $nomeNorm = strtr(mb_strtolower($nome, 'UTF-8'), $map);
            $score = 0;

            if ($sigla !== '' && preg_match('/\b' . preg_quote($sigla, '/') . '\b/iu', $t) === 1) {
                $score = 100;
            } elseif ($nomeNorm !== '' && $nomeNorm !== ' ' && str_contains($textoNorm, $nomeNorm)) {
                $score = 90;
            } elseif ($nomeNorm !== '') {
                // tokens significativos do nome (ex.: "prova bimestral")
                $tokens = array_values(array_filter(
                    preg_split('/\s+/u', $nomeNorm) ?: [],
                    static fn($tok) => mb_strlen($tok) >= 4
                ));
                if ($tokens !== []) {
                    $achados = 0;
                    foreach ($tokens as $tok) {
                        if (str_contains($textoNorm, $tok)) {
                            $achados++;
                        }
                    }
                    if ($achados === count($tokens)) {
                        $score = 80;
                    } elseif ($achados > 0 && $achados >= (int) ceil(count($tokens) * 0.6)) {
                        $score = 65;
                    }
                }
            }

            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = [
                    'id' => (int) ($ev['id'] ?? 0),
                    'sigla' => $sigla,
                    'nome' => $nome,
                ];
            }
        }

        if ($melhor !== null && $melhorScore >= 65) {
            return $melhor;
        }

        // Fallback: token curto no texto que bata exatamente com alguma sigla cadastrada.
        if (preg_match_all('/\b([A-Za-z0-9]{1,12})\b/u', $t, $mm) === false) {
            return null;
        }
        $siglasCadastro = [];
        foreach ($eventos as $ev) {
            $s = strtoupper(trim((string) ($ev['sigla'] ?? '')));
            if ($s !== '') {
                $siglasCadastro[$s] = $ev;
            }
        }
        foreach ($mm[1] as $token) {
            $up = strtoupper($token);
            if (isset($siglasCadastro[$up])) {
                $ev = $siglasCadastro[$up];
                return [
                    'id' => (int) ($ev['id'] ?? 0),
                    'sigla' => $up,
                    'nome' => trim((string) ($ev['nome'] ?? '')),
                ];
            }
        }
        return null;
    }

    private function limparRespostaFinal(string $resposta): string
    {
        $resposta = preg_replace(
            '/' . preg_quote(self::DELIMITADOR_INICIO, '/') . '.*?' . preg_quote(self::DELIMITADOR_FIM, '/') . '/s',
            '',
            $resposta
        ) ?? $resposta;
        $pos = strpos($resposta, self::DELIMITADOR_INICIO);
        if ($pos !== false) {
            $resposta = substr($resposta, 0, $pos);
        }
        // Metadado interno do histórico — nunca mostrar ao usuário.
        $resposta = preg_replace('/\[contexto_estruturado\][\s\S]*$/u', '', $resposta) ?? $resposta;
        $resposta = preg_replace('/^\s*aluno_id\s*=\s*\d+.*$/mu', '', $resposta) ?? $resposta;
        return trim($resposta);
    }

    /**
     * Quando o pedido é pontual (nota de uma matéria, boletim, faltas), monta frase assertiva
     * a partir do resultado da tool — independente do estilo da IA.
     *
     * @param array<string,mixed>|null $resultado
     */
    private function montarRespostaDireta(string $pergunta, ?string $tool, ?array $resultado): ?string
    {
        if ($tool === null || !is_array($resultado) || empty($resultado['ok'])) {
            return null;
        }
        $aluno = is_array($resultado['aluno'] ?? null) ? $resultado['aluno'] : [];
        $nomeAluno = trim((string) ($aluno['nome'] ?? ''));
        $turma = trim((string) ($aluno['turma_nome'] ?? ''));
        $quem = $nomeAluno !== ''
            ? ($nomeAluno . ($turma !== '' ? ' (' . $turma . ')' : ''))
            : 'o aluno';

        if ($tool === 'faltas_aluno') {
            return $this->montarTextoConsultaFaltas($resultado);
        }

        if ($tool === 'ocorrencias_aluno') {
            return $this->montarTextoConsultaOcorrencias($resultado);
        }

        if (in_array($tool, ['ocorrencias_turma', 'diario_pendencias'], true)) {
            if (!empty($resultado['aviso']) && empty($resultado['total'])) {
                return (string) $resultado['aviso'];
            }
            $total = (int) ($resultado['total'] ?? 0);
            $registro = $tool === 'ocorrencias_turma' ? 'ocorrência' : 'diário';
            return 'Encontrei **' . $total . '** ' . $registro . ($total === 1 ? '' : 's') . ' no filtro consultado.';
        }

        if ($tool === 'grade_horaria_consulta') {
            $data = (string) ($resultado['data'] ?? '');
            $dia = (string) ($resultado['dia_semana_label'] ?? '');
            $escopo = is_array($resultado['escopo'] ?? null) ? $resultado['escopo'] : [];
            $tipoEsc = (string) ($escopo['tipo'] ?? '');
            $nomeEsc = trim((string) ($escopo['nome'] ?? $escopo['professor_nome'] ?? $escopo['aluno_nome'] ?? $escopo['turma_nome'] ?? ''));
            $aulas = is_array($resultado['aulas'] ?? null) ? $resultado['aulas'] : [];
            $quando = trim(($dia !== '' ? $dia : '') . ($data !== '' ? ' (' . $this->formatarDataBr($data) . ')' : ''));
            if ($aulas === []) {
                return 'Nenhuma aula na grade'
                    . ($nomeEsc !== '' ? ' d' . $this->artigoDe($nomeEsc) . ' **' . $nomeEsc . '**' : '')
                    . ($quando !== '' ? ' em ' . $quando : '')
                    . '.';
            }
            $rotulo = $tipoEsc === 'professor' ? 'Grade do professor' : 'Grade';
            return $rotulo
                . ($nomeEsc !== '' ? ' **' . $nomeEsc . '**' : '')
                . ($quando !== '' ? ' — ' . $quando : '')
                . ': **' . count($aulas) . '** aula' . (count($aulas) === 1 ? '' : 's') . ' abaixo.';
        }

        if ($tool === 'ranking_erros_prova_professor') {
            $prof = trim((string) ($resultado['professor']['nome'] ?? 'professor'));
            $prova = trim((string) ($resultado['prova']['titulo'] ?? 'prova'));
            $questoes = is_array($resultado['questoes'] ?? null) ? $resultado['questoes'] : [];
            if ($questoes === []) {
                return 'Não há questões com erro registradas na prova **' . $prova . '** d'
                    . $this->artigoDe($prof) . ' ' . $prof . '.';
            }
            $top = $questoes[0];
            $enunciado = trim((string) ($top['enunciado'] ?? ''));
            return 'Ranking de erros da prova **' . $prova . '** (' . $prof . '): '
                . count($questoes) . ' questão' . (count($questoes) === 1 ? '' : 'ões')
                . ' com erro. A mais errada teve **' . (int) ($top['erros'] ?? 0) . '** erros'
                . ($enunciado !== '' ? ' — ' . mb_substr($enunciado, 0, 80) : '')
                . '.';
        }

        if (
            $tool === 'nota_evento_sigla'
            || (
                in_array($tool, ['notas_evento_aluno', 'nota_coluna_boletim'], true)
                && ($resultado['formato'] ?? '') === 'nota_unica'
            )
        ) {
            $sigla = strtoupper(trim((string) ($resultado['evento']['sigla'] ?? $resultado['coluna'] ?? '')));
            $nomeEv = trim((string) ($resultado['evento']['nome'] ?? $sigla));
            $rotuloEv = $sigla !== '' ? $sigla : $nomeEv;
            if (($resultado['formato'] ?? '') === 'nota_unica') {
                $mat = trim((string) ($resultado['materia'] ?? ''));
                $nota = $resultado['nota'] ?? null;
                $bim = (int) ($resultado['bimestre'] ?? $resultado['evento']['bimestre'] ?? 0);
                $ano = (int) ($resultado['ano_letivo'] ?? 0);
                $sufixoPeriodo = '';
                if ($bim > 0) {
                    $sufixoPeriodo .= ' no ' . $bim . 'º bimestre';
                }
                if ($ano > 0) {
                    $sufixoPeriodo .= ($sufixoPeriodo !== '' ? ' de ' : ' em ') . $ano;
                }
                if ($nota === null || $nota === '') {
                    return 'Não há nota lançada da ' . $rotuloEv
                        . ($mat !== '' ? ' de ' . $mat : '')
                        . ' para ' . $quem
                        . $sufixoPeriodo . '.';
                }
                return 'A nota da ' . $rotuloEv
                    . ($mat !== '' ? ' de ' . $mat : '')
                    . ' d' . $this->artigoDe($quem) . ' ' . $quem
                    . $sufixoPeriodo
                    . ' é **' . $nota . '**'
                    . (!empty($resultado['fonte']) && $resultado['fonte'] === 'quadro_boletim'
                        ? ' (quadro do boletim)'
                        : '')
                    . '.';
            }
            $linhas = is_array($resultado['notas'] ?? null) ? $resultado['notas'] : [];
            if ($linhas === []) {
                return 'Não há notas lançadas da ' . $rotuloEv . ' para ' . $quem . '.';
            }
            $partes = [];
            foreach (array_slice($linhas, 0, 12) as $l) {
                $m = trim((string) ($l['materia'] ?? ''));
                $n = $l['nota'] ?? null;
                if ($m === '' || $n === null || $n === '') {
                    continue;
                }
                $partes[] = $m . ' **' . $n . '**';
            }
            if ($partes === []) {
                return 'Não há notas lançadas da ' . $rotuloEv . ' para ' . $quem . '.';
            }
            return 'Notas da ' . $rotuloEv . ' d' . $this->artigoDe($quem) . ' ' . $quem . ': ' . implode('; ', $partes) . '.';
        }

        if (in_array($tool, ['boletim_aluno', 'notas_evento_aluno'], true)) {
            $materiaPedido = $this->extrairMateriaDaPergunta($pergunta);
            if ($materiaPedido === null && !empty($resultado['materia'])) {
                $materiaPedido = trim((string) $resultado['materia']);
            }
            $quadro = is_array($resultado['quadro'] ?? null) ? $resultado['quadro'] : null;
            if ($quadro === null && !empty($resultado['eventos'][0]) && is_array($resultado['eventos'][0])) {
                $ev0 = $resultado['eventos'][0];
                $quadro = [
                    'colunas' => $ev0['colunas'] ?? [],
                    'linhas' => array_map(static function ($l) {
                        return [
                            'materia' => $l['materia'] ?? ($l['materia_nome'] ?? ''),
                            'notas' => $l['notas'] ?? [],
                        ];
                    }, (array) ($ev0['linhas'] ?? [])),
                ];
            }

            if ($materiaPedido !== null && is_array($quadro)) {
                $colunaPref = strtoupper(trim((string) ($resultado['coluna'] ?? '')));
                $achado = $this->buscarNotaMateriaNoQuadro($quadro, $materiaPedido, $colunaPref !== '' ? $colunaPref : null);
                if ($achado !== null) {
                    $rotuloMat = $achado['materia'] !== '' ? $achado['materia'] : $materiaPedido;
                    $bim = (int) ($resultado['bimestre'] ?? $resultado['evento']['bimestre'] ?? 0);
                    $ano = (int) ($resultado['ano_letivo'] ?? 0);
                    $sufixo = '';
                    if ($colunaPref !== '') {
                        $sufixo .= ' (' . $colunaPref . ')';
                    }
                    if ($bim > 0) {
                        $sufixo .= ' — ' . $bim . 'º bimestre';
                    }
                    if ($ano > 0) {
                        $sufixo .= ($bim > 0 ? ' de ' : ' — ') . $ano;
                    }
                    if ($achado['valor'] === null || $achado['valor'] === '') {
                        return 'Não há nota lançada de ' . $rotuloMat . ' para ' . $quem . $sufixo . '.';
                    }
                    return 'A nota de ' . $rotuloMat . ' d' . $this->artigoDe($quem) . ' ' . $quem
                        . $sufixo
                        . ' é **' . $achado['valor'] . '**.';
                }
            }

            if ($tool === 'boletim_aluno' && $this->pedidoPareceBoletim($pergunta)) {
                if (!$this->boletimTemConteudoExibivel($resultado)) {
                    return 'Não encontrei boletim com notas para exibir d'
                        . $this->artigoDe($quem) . ' ' . $quem . '.';
                }
                $ano = (string) ($resultado['ano_letivo'] ?? '');
                return 'Boletim d' . $this->artigoDe($quem) . ' **' . $quem . '**'
                    . ($ano !== '' ? ' — ' . $ano : '')
                    . '. O quadro completo está abaixo; use o botão para baixar o PDF.';
            }

            if ($tool === 'notas_evento_aluno' && $materiaPedido === null) {
                $evento = trim((string) ($resultado['evento']['nome'] ?? 'evento'));
                $coluna = trim((string) ($resultado['coluna'] ?? ''));
                return 'Notas d' . $this->artigoDe($quem) . ' **' . $quem . '** — ' . $evento
                    . ($coluna !== '' ? ' (' . $coluna . ')' : '')
                    . '. O quadro está abaixo.';
            }
        }

        return null;
    }

    /**
     * @param list<array{role?:string,content?:string}> $historico
     */
    private function mensagemConfirmaProposta(string $mensagem): bool
    {
        $t = mb_strtolower(trim($mensagem), 'UTF-8');
        $t = preg_replace('/[!?.]+$/u', '', $t) ?? $t;
        $t = trim($t);
        if ($t === '') {
            return false;
        }
        // Só confirmações curtas/explícitas — evita "sim, e mostra o boletim".
        if (preg_match('/^(sim|ok|pode|confirma|confirmo|confirmar|pode confirmar|pode aplicar|aplicar|fechado|isso|isso mesmo|pode ser)(\s+por favor)?$/iu', $t) === 1) {
            return true;
        }
        if (preg_match('/^(sim|ok),?\s+(pode\s+)?(confirmar|aplicar|gravar|registrar)\.?$/iu', $t) === 1) {
            return true;
        }
        return false;
    }

    private function propostaExibidaNoHistorico(array $historico): int
    {
        $ultima = $historico[array_key_last($historico)] ?? [];
        return ($ultima['role'] ?? '') === 'assistant' ? (int) ($ultima['proposta_id'] ?? 0) : 0;
    }

    private function pedidoPareceBoletim(string $pergunta, array $historico = []): bool
    {
        if (preg_match('/\bboletim\b|\bquadro\s+de\s+notas\b/iu', $pergunta) === 1) {
            return true;
        }
        // Continuação do chat: "traga ano de 2024", "mostra 2024"
        if (
            preg_match('/\b(?:traga|mostra|quero|pegar|ver).{0,40}\b(20\d{2})\b|\bano\s+(?:de\s+)?(20\d{2})\b/iu', $pergunta) === 1
            && $this->historicoRecenteFalaBoletim($historico)
        ) {
            return true;
        }
        return false;
    }

    /** @param list<array{role?:string,content?:string}> $historico */
    private function historicoRecenteFalaBoletim(array $historico): bool
    {
        foreach (array_reverse(array_slice($historico, -8)) as $msg) {
            $c = (string) ($msg['content'] ?? '');
            if (preg_match('/\bboletim\b|\bquadro\s+de\s+notas\b|baixar o pdf/iu', $c) === 1) {
                return true;
            }
        }
        return false;
    }

    private function pedidoPareceIdentidadeAluno(string $pergunta): bool
    {
        // Não use só "RA" aqui — senão qualquer pedido com RA vira resposta de identidade.
        return (bool) preg_match(
            '/\bquem\s+(é|e)\b|\bnome\s+d[oa]\b|\bdados\s+d[oa]\b|\bcadastro\b|\bqual\s+(?:é\s+a\s+)?turma\b/iu',
            $pergunta
        );
    }

    /**
     * Resolve aluno por RA/histórico e responde identidade/turma com dado real.
     *
     * @param list<array{role?:string,content?:string}> $mensagens
     * @param callable|null $onStatus
     * @return array{tool?:?string,resultado?:?array,consulta?:array,texto?:string}
     */
    private function garantirIdentidadeAluno(
        string $mensagemUsuario,
        array $mensagens,
        ?string $ultimaTool,
        ?array $ultimoResultado,
        $onStatus = null
    ): array {
        $raMsg = $this->extrairRaDaMensagem($mensagemUsuario);
        $precisaIdentidade = $this->pedidoPareceIdentidadeAluno($mensagemUsuario);

        $alunoJa = is_array($ultimoResultado['aluno'] ?? null) ? $ultimoResultado['aluno'] : null;
        $listaJa = [];
        if (is_array($ultimoResultado['alunos'] ?? null)) {
            $listaJa = $ultimoResultado['alunos'];
        } elseif (is_array($ultimoResultado['candidatos'] ?? null)) {
            $listaJa = $ultimoResultado['candidatos'];
        }

        $aluno = null;
        $consulta = null;
        $tool = $ultimaTool;
        $resultado = $ultimoResultado;

        if (is_array($alunoJa) && (int) ($alunoJa['id'] ?? 0) > 0) {
            $aluno = $alunoJa;
        } elseif (count($listaJa) === 1 && (int) ($listaJa[0]['id'] ?? 0) > 0) {
            $aluno = $listaJa[0];
        }

        // Se a mensagem traz RA, confere no banco — mesmo que a IA diga que não achou.
        if ($raMsg !== null && ($aluno === null || (string) ($aluno['ra'] ?? '') !== $raMsg)) {
            if ($onStatus !== null) {
                $onStatus('Conferindo aluno pelo RA…');
            }
            $lista = $this->consulta->buscarAlunos('ra ' . $raMsg, 10, null);
            $consulta = ['tool' => 'buscar_alunos', 'args' => ['ra' => $raMsg]];
            $tool = 'buscar_alunos';
            if ($lista === []) {
                $resultado = [
                    'ok' => false,
                    'alunos' => [],
                    'error' => 'Não encontrei aluno com RA ' . $raMsg . ' nesta escola.',
                ];
                return [
                    'tool' => $tool,
                    'resultado' => $resultado,
                    'consulta' => $consulta,
                    'texto' => (string) $resultado['error'],
                ];
            }
            if (count($lista) > 1) {
                $resultado = [
                    'ok' => false,
                    'aviso' => 'Há mais de um aluno com RA ' . $raMsg . '. Escolha um:',
                    'candidatos' => $lista,
                    'alunos' => $lista,
                ];
                return [
                    'tool' => $tool,
                    'resultado' => $resultado,
                    'consulta' => $consulta,
                    'texto' => (string) $resultado['aviso'],
                ];
            }
            $aluno = $lista[0];
            $resultado = ['ok' => true, 'alunos' => [$aluno], 'aluno' => $aluno];
        }

        if ($aluno === null && $precisaIdentidade) {
            // Sem RA: tenta aluno_id do histórico.
            $ctx = $this->extrairContextoProvasDoHistorico($mensagens);
            $alunoId = (int) ($ctx['aluno_id'] ?? 0);
            if ($alunoId > 0) {
                $aluno = $this->consulta->obterAluno($alunoId);
                if ($aluno !== null) {
                    $tool = 'buscar_alunos';
                    $resultado = ['ok' => true, 'alunos' => [$aluno], 'aluno' => $aluno];
                    $consulta = ['tool' => 'buscar_alunos', 'args' => ['aluno_id' => $alunoId]];
                }
            }
        }

        if ($aluno === null) {
            return [];
        }

        $out = [
            'tool' => $tool,
            'resultado' => $resultado,
        ];
        if ($consulta !== null) {
            $out['consulta'] = $consulta;
        }

        // Pedidos de identidade/turma: resposta assertiva com o cadastro real.
        if ($precisaIdentidade && !$this->pedidoPareceBoletim($mensagemUsuario)) {
            $out['texto'] = $this->montarRespostaIdentidadeAluno($mensagemUsuario, $aluno);
            $out['tool'] = 'buscar_alunos';
            $out['resultado'] = ['ok' => true, 'alunos' => [$aluno], 'aluno' => $aluno];
        }

        return $out;
    }

    /** @param array<string,mixed> $aluno */
    private function montarRespostaIdentidadeAluno(string $pergunta, array $aluno): string
    {
        $nome = trim((string) ($aluno['nome'] ?? 'Aluno'));
        $ra = trim((string) ($aluno['ra'] ?? ''));
        $turma = trim((string) ($aluno['turma_nome'] ?? ''));
        $pedeTurma = (bool) preg_match('/\bturma\b/iu', $pergunta);

        if ($pedeTurma) {
            if ($turma === '') {
                return '**' . $nome . '**'
                    . ($ra !== '' ? ' (RA ' . $ra . ')' : '')
                    . ' está cadastrad' . $this->artigoDe($nome) . ', mas sem turma vinculada no momento.';
            }
            return 'A turma d' . $this->artigoDe($nome) . ' **' . $nome . '**'
                . ($ra !== '' ? ' (RA ' . $ra . ')' : '')
                . ' é **' . $turma . '**.';
        }

        return 'Aluno(a) encontrado(a): **' . $nome . '**'
            . ($ra !== '' ? ' — RA **' . $ra . '**' : '')
            . ($turma !== '' ? ' — turma **' . $turma . '**' : '')
            . '.';
    }

    private function extrairRaDaMensagem(string $texto): ?string
    {
        $ra = $this->consulta->extrairCodigoRa($texto);
        if ($ra !== null && $ra !== '') {
            return $ra;
        }
        if (preg_match('/\bRA\s*[#:]?\s*([A-Za-z0-9]{3,20})\b/iu', $texto, $m) === 1) {
            $cand = $m[1];
            if (preg_match('/^20\d{2}$/', $cand) === 1) {
                return null;
            }
            return preg_match('/[A-Za-z]/', $cand) === 1 ? strtoupper($cand) : $cand;
        }
        return null;
    }

    /**
     * Garante que um pedido de boletim passou pela tool e trouxe dados reais.
     *
     * @param list<array{role?:string,content?:string}> $mensagens
     * @param callable|null $onStatus
     * @return array{tool:?string,resultado:?array,consulta?:array}
     */
    private function garantirConsultaBoletim(
        string $mensagemUsuario,
        array $mensagens,
        ?string $ultimaTool,
        ?array $ultimoResultado,
        $onStatus = null
    ): array {
        $anoPedido = 0;
        if (preg_match('/\b(20\d{2})\b/', $mensagemUsuario, $mAno) === 1) {
            $anoPedido = (int) $mAno[1];
        }
        $anoResultado = (int) ($ultimoResultado['ano_letivo'] ?? 0);
        $mesmoAnoOk = $anoPedido <= 0 || $anoResultado === $anoPedido;

        if (
            $ultimaTool === 'boletim_aluno'
            && $this->boletimTemConteudoExibivel($ultimoResultado)
            && $mesmoAnoOk
        ) {
            return ['tool' => $ultimaTool, 'resultado' => $ultimoResultado];
        }

        // Ambiguidade de aluno: não força nova busca.
        if (
            is_array($ultimoResultado)
            && !empty($ultimoResultado['candidatos'])
            && is_array($ultimoResultado['candidatos'])
        ) {
            return ['tool' => $ultimaTool, 'resultado' => $ultimoResultado];
        }

        $args = $this->montarArgsBoletim($mensagemUsuario, $mensagens, $ultimoResultado);
        if ($args === null) {
            return [
                'tool' => 'boletim_aluno',
                'resultado' => [
                    'ok' => false,
                    'error' => 'Para trazer o boletim, informe o RA ou o nome completo do aluno (com turma, se possível).',
                ],
            ];
        }

        if ($onStatus !== null) {
            $onStatus('Montando boletim…');
        }
        $resultado = $this->ampliada->boletimAluno($args);
        return [
            'tool' => 'boletim_aluno',
            'resultado' => $resultado,
            'consulta' => ['tool' => 'boletim_aluno', 'args' => $args],
        ];
    }

    /**
     * @param list<array{role?:string,content?:string}> $mensagens
     * @return array<string,mixed>|null
     */
    private function montarArgsBoletim(string $mensagemUsuario, array $mensagens, ?array $ultimoResultado): ?array
    {
        $args = [];

        $alunoId = (int) ($ultimoResultado['aluno']['id'] ?? 0);
        if ($alunoId <= 0 && preg_match('/aluno_id\s*=\s*(\d+)/i', $mensagemUsuario, $m) === 1) {
            $alunoId = (int) $m[1];
        }
        if ($alunoId <= 0) {
            $ctx = $this->extrairContextoProvasDoHistorico($mensagens);
            $alunoId = (int) ($ctx['aluno_id'] ?? 0);
        }
        if ($alunoId > 0) {
            $args['aluno_id'] = $alunoId;
        }

        $ra = null;
        if (method_exists($this->consulta, 'extrairCodigoRa')) {
            $ra = $this->consulta->extrairCodigoRa($mensagemUsuario);
        }
        if ($ra === null && preg_match('/\bRA\s*[#:]?\s*(\d{3,})\b/iu', $mensagemUsuario, $mRa) === 1) {
            $ra = $mRa[1];
        }
        if ($ra !== null && $ra !== '' && empty($args['aluno_id'])) {
            $args['ra'] = (string) $ra;
        }

        if (preg_match('/\b(20\d{2})\b/', $mensagemUsuario, $mAno) === 1) {
            $args['ano_letivo'] = (int) $mAno[1];
        }

        if (empty($args['aluno_id']) && empty($args['ra'])) {
            // Último recurso: nome após "aluna/aluno"
            if (preg_match('/\b(?:aluna|aluno)\s+([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\s]{2,60})/u', $mensagemUsuario, $mNome) === 1) {
                $nome = trim($mNome[1]);
                $nome = preg_replace('/\s+d[oae]\s+\d.*$/iu', '', $nome) ?? $nome;
                if ($nome !== '' && stripos($nome, 'RA') !== 0) {
                    $args['aluno_nome'] = $nome;
                }
            }
        }

        if (empty($args['aluno_id']) && empty($args['ra']) && empty($args['aluno_nome'])) {
            return null;
        }
        return $args;
    }

    private function extrairColunaBoletimDaPergunta(string $pergunta): ?string
    {
        // Siglas curtas só em MAIÚSCULAS (evita "pe", "ma", "mu" no meio da frase).
        if (preg_match('/\b(PP|PU|MU|CH|PE|MS|MA|REC|PF|RF)\b/', $pergunta, $m) === 1) {
            return $m[1];
        }
        // Nomes falados comuns das colunas do boletim.
        $p = mb_strtolower($this->semAcento($pergunta));
        $mapa = [
            'prova parcial' => 'PP',
            'prova unica' => 'PU',
            'media unica' => 'MU',
            'media bimestral' => 'MU',
        ];
        foreach ($mapa as $termo => $cod) {
            if (str_contains($p, $this->semAcento($termo))) {
                return $cod;
            }
        }
        return null;
    }

    private function extrairBimestreDaPergunta(string $pergunta): int
    {
        if (preg_match('/\b([1-4])\s*[ºªo]?\s*bimestre\b/iu', $pergunta, $m) === 1) {
            return (int) $m[1];
        }
        if (preg_match('/\bbimestre\s*([1-4])\b/iu', $pergunta, $m) === 1) {
            return (int) $m[1];
        }
        return 0;
    }

    /** @param list<array{role?:string,content?:string}> $historico */
    private function pedidoPareceNotaColunaBoletim(string $pergunta, array $historico = []): bool
    {
        if ($this->extrairColunaBoletimDaPergunta($pergunta) !== null) {
            return true;
        }
        // Continuação curta: "e a de matemática 2?", "do 2º bimestre", "mesmo bimestre".
        if (preg_match('/\b(faltas?|frequencia|frequência|ocorr|jornada|prova online)\b/iu', $pergunta) === 1) {
            return false;
        }
        if (
            preg_match('/\b(?:no\s+|do\s+)?mesmo\s+bimestre\b|\bmesma\s+coluna\b/iu', $pergunta) === 1
            && $this->historicoRecenteFalaNotaColuna($historico)
        ) {
            return true;
        }
        if (
            preg_match('/\b(nota|pp|pu|mu).{0,40}\b(matematica|matemática)\s*[12]?\b/iu', $pergunta) === 1
            && $this->historicoRecenteFalaNotaColuna($historico)
        ) {
            return true;
        }
        if (
            preg_match('/^\s*(e\s+)?(a\s+)?(de\s+)?(matematica|matemática)\s*[12]?\s*\??\s*$/iu', $pergunta) === 1
            && $this->historicoRecenteFalaNotaColuna($historico)
        ) {
            return true;
        }
        if (
            preg_match('/^\s*(e\s+)?(no\s+|do\s+)?([1-4])\s*[ºªo]?\s*bimestre\s*\??\s*$/iu', $pergunta) === 1
            && $this->historicoRecenteFalaNotaColuna($historico)
        ) {
            return true;
        }
        return false;
    }

    /** @param list<array{role?:string,content?:string}> $historico */
    private function historicoRecenteFalaNotaColuna(array $historico): bool
    {
        foreach (array_reverse(array_slice($historico, -8)) as $msg) {
            $c = (string) ($msg['content'] ?? '');
            if ($this->extrairColunaBoletimDaPergunta($c) !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<array{role?:string,content?:string}> $mensagens
     * @param callable|null $onStatus
     * @return array{tool?:?string,resultado?:?array,consulta?:array,texto?:string}
     */
    private function garantirNotaColunaBoletim(
        string $mensagemUsuario,
        array $mensagens,
        ?string $ultimaTool,
        ?array $ultimoResultado,
        $onStatus = null
    ): array {
        $coluna = $this->extrairColunaBoletimDaPergunta($mensagemUsuario);
        if ($coluna === null) {
            $coluna = $this->extrairColunaBoletimDaPergunta(
                $this->ultimoPedidoUsuario($mensagens, null) ?: ''
            );
        }
        if ($coluna === null) {
            return [];
        }

        // Já veio do quadro — só reaproveita se a matéria pedida for a mesma (senão "Mat 2" reaproveitaria Mat 1).
        $materiaNova = $this->extrairMateriaDaPergunta($mensagemUsuario);
        $materiaAntiga = trim((string) ($ultimoResultado['materia'] ?? ''));
        $mesmaMateria = $materiaNova === null
            || $materiaAntiga === ''
            || $this->semAcento(mb_strtolower($materiaNova)) === $this->semAcento(mb_strtolower($materiaAntiga));
        if (
            is_array($ultimoResultado)
            && !empty($ultimoResultado['ok'])
            && ($ultimoResultado['fonte'] ?? '') === 'quadro_boletim'
            && strtoupper((string) ($ultimoResultado['coluna'] ?? '')) === $coluna
            && $mesmaMateria
            && in_array((string) ($ultimoResultado['formato'] ?? ''), ['nota_unica', 'coluna_quadro'], true)
        ) {
            if (!empty($ultimoResultado['candidatos_materias'])) {
                return [
                    'tool' => $ultimaTool ?? 'notas_evento_aluno',
                    'resultado' => $ultimoResultado,
                    'texto' => (string) ($ultimoResultado['aviso'] ?? 'Há mais de uma matéria. Qual delas?'),
                ];
            }
            $texto = $this->montarRespostaDireta($mensagemUsuario, 'notas_evento_aluno', $ultimoResultado);
            return [
                'tool' => $ultimaTool ?? 'notas_evento_aluno',
                'resultado' => $ultimoResultado,
                'texto' => $texto ?? null,
            ];
        }

        if (!empty($ultimoResultado['candidatos_bimestres']) || !empty($ultimoResultado['candidatos'])) {
            return [
                'tool' => $ultimaTool,
                'resultado' => $ultimoResultado,
                'texto' => trim((string) ($ultimoResultado['aviso'] ?? 'Há mais de uma opção. Escolha uma:')),
            ];
        }

        $args = $this->montarArgsNotaColuna($mensagemUsuario, $mensagens, $ultimoResultado, $coluna);
        if ($args === null) {
            return [
                'tool' => 'notas_evento_aluno',
                'resultado' => [
                    'ok' => false,
                    'error' => 'Para consultar a ' . $coluna . ' do boletim, informe o aluno (RA ou nome) e a matéria.',
                ],
                'texto' => 'Para consultar a **' . $coluna . '** do boletim, informe o aluno (RA ou nome) e a matéria.',
            ];
        }

        if ($onStatus !== null) {
            $onStatus('Consultando coluna ' . $coluna . ' do boletim…');
        }
        $resultado = $this->ampliada->notaColunaBoletim($args);
        $texto = null;
        if (!empty($resultado['candidatos_bimestres'])) {
            $texto = (string) ($resultado['aviso'] ?? 'A coluna aparece em mais de um bimestre. Qual deles?');
        } elseif (!empty($resultado['candidatos_materias'])) {
            $texto = (string) ($resultado['aviso'] ?? 'Há mais de uma matéria. Qual delas?');
        } elseif (!empty($resultado['ok'])) {
            $texto = $this->montarRespostaDireta($mensagemUsuario, 'notas_evento_aluno', $resultado);
        } else {
            $texto = trim((string) ($resultado['error'] ?? $resultado['aviso'] ?? 'Não encontrei essa nota no boletim.'));
        }

        return [
            'tool' => 'notas_evento_aluno',
            'resultado' => $resultado,
            'consulta' => ['tool' => 'notas_evento_aluno', 'args' => $args],
            'texto' => $texto,
        ];
    }

    /**
     * @param list<array{role?:string,content?:string}> $mensagens
     * @return array<string,mixed>|null
     */
    private function montarArgsNotaColuna(
        string $mensagemUsuario,
        array $mensagens,
        ?array $ultimoResultado,
        string $coluna
    ): ?array {
        $args = ['coluna' => $coluna];

        $alunoId = (int) ($ultimoResultado['aluno']['id'] ?? 0);
        if ($alunoId <= 0 && preg_match('/aluno_id\s*=\s*(\d+)/i', $mensagemUsuario, $m) === 1) {
            $alunoId = (int) $m[1];
        }
        if ($alunoId <= 0) {
            $ctx = $this->extrairContextoProvasDoHistorico($mensagens);
            $alunoId = (int) ($ctx['aluno_id'] ?? 0);
        }
        if ($alunoId > 0) {
            $args['aluno_id'] = $alunoId;
        }

        $ra = null;
        if (method_exists($this->consulta, 'extrairCodigoRa')) {
            $ra = $this->consulta->extrairCodigoRa($mensagemUsuario);
        }
        if ($ra === null && preg_match('/\bRA\s*[#:]?\s*(\d{3,})\b/iu', $mensagemUsuario, $mRa) === 1) {
            $ra = $mRa[1];
        }
        if ($ra !== null && $ra !== '' && empty($args['aluno_id'])) {
            $args['ra'] = (string) $ra;
        }

        $materia = $this->extrairMateriaDaPergunta($mensagemUsuario);
        if ($materia === null) {
            $materia = $this->extrairMateriaDaPergunta($this->ultimoPedidoUsuario($mensagens, null) ?: '');
        }
        if ($materia !== null) {
            $args['materia'] = $materia;
        }

        $ctxHist = $this->extrairContextoConsultaDoHistorico($mensagens);

        $bim = $this->extrairBimestreDaPergunta($mensagemUsuario);
        if ($bim <= 0 && preg_match('/\b(?:no\s+|do\s+)?mesmo\s+bimestre\b/iu', $mensagemUsuario) === 1) {
            $bim = (int) ($ultimoResultado['bimestre'] ?? $ultimoResultado['evento']['bimestre'] ?? $ctxHist['bimestre'] ?? 0);
        }
        if ($bim <= 0) {
            $bim = $this->extrairBimestreDaPergunta($this->ultimoPedidoUsuario($mensagens, null) ?: '');
        }
        if ($bim <= 0) {
            $bim = (int) ($ultimoResultado['bimestre'] ?? $ultimoResultado['evento']['bimestre'] ?? $ctxHist['bimestre'] ?? 0);
        }
        if ($bim > 0) {
            $args['bimestre'] = $bim;
        }

        if (preg_match('/\b(20\d{2})\b/', $mensagemUsuario, $mAno) === 1) {
            $args['ano_letivo'] = (int) $mAno[1];
        } elseif (!empty($ultimoResultado['ano_letivo'])) {
            $args['ano_letivo'] = (int) $ultimoResultado['ano_letivo'];
        } elseif (!empty($ctxHist['ano_letivo'])) {
            $args['ano_letivo'] = (int) $ctxHist['ano_letivo'];
        } elseif (preg_match('/\b(20\d{2})\b/', $this->ultimoPedidoUsuario($mensagens, null) ?: '', $mAno2) === 1) {
            $args['ano_letivo'] = (int) $mAno2[1];
        }

        if (empty($args['aluno_id']) && empty($args['ra'])) {
            $raCtx = trim((string) ($ultimoResultado['aluno']['ra'] ?? ''));
            if ($raCtx !== '') {
                $args['ra'] = $raCtx;
            } elseif (preg_match('/\b(?:aluna|aluno)\s+([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\s]{2,60})/u', $mensagemUsuario, $mNome) === 1) {
                $nome = trim($mNome[1]);
                $nome = preg_replace('/\s+d[oae]\s+\d.*$/iu', '', $nome) ?? $nome;
                if ($nome !== '') {
                    $args['aluno_nome'] = $nome;
                }
            } elseif (!empty($ultimoResultado['aluno']['nome'])) {
                $args['aluno_nome'] = trim((string) $ultimoResultado['aluno']['nome']);
            }
        }

        if (empty($args['aluno_id']) && empty($args['ra']) && empty($args['aluno_nome'])) {
            return null;
        }
        return $args;
    }

    /**
     * Nome explícito na mensagem de consulta ("ocorrências da Maria", "faltas do João Silva").
     */
    private function extrairNomeAlunoDaConsulta(string $mensagem): ?string
    {
        $msg = trim($mensagem);
        if ($msg === '') {
            return null;
        }
        // Prioriza a entidade ligada à consulta, inclusive "ocorrência na aluna Ana Luiza".
        if (preg_match(
            '/\b(?:ocorr[eê]ncias?|faltas?|frequ[eê]ncia|boletim|notas?|jornadas?|perfil)\s+(?:(?:da|do|de|sobre)\s+(?:alun[oa]\s+)?|(?:na|no|para\s+[ao])\s+alun[oa]\s+)([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'\-]{1,}(?:\s+[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'\-]{1,}){0,4})/iu',
            $msg,
            $m
        ) === 1) {
            $n = $this->limparNomeAlunoCapturado($m[1]);
            if ($n !== null) {
                return $n;
            }
        }
        // A identificação explícita tem prioridade sobre preposições soltas da frase.
        if (preg_match('/\b(?:aluna|aluno)\s+([\p{L}][\p{L}\'\-]*(?:\s+[\p{L}][\p{L}\'\-]*){0,4})/iu', $msg, $m) === 1) {
            $nome = $this->limparNomeAlunoCapturado($m[1]);
            if ($nome !== null) {
                return $nome;
            }
        }
        // "da Maria" / "do João" / "de Pedro Santos"
        if (preg_match(
            '/\b(?:da|do|de|pelo|pela)\s+(?:o\s+|a\s+|aluno\s+|aluna\s+)?([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'\-]{1,}(?:\s+[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'\-]{1,}){0,4})/iu',
            $msg,
            $m2
        ) === 1) {
            $cand = $this->limparNomeAlunoCapturado($m2[1]);
            if ($cand !== null) {
                return $cand;
            }
        }
        return null;
    }

    private function limparNomeAlunoCapturado(string $bruto): ?string
    {
        $nome = trim($bruto);
        $nome = preg_replace('/\s+(?:(?:da|do|na|no)\s+turma\b|(?:em|no|na|do|da|neste|nesta)\s+(?:(?:[uú]ltim[oa]|primeir[oa]|segund[oa])\s+)?(?:ano|bimestre|trimestre|per[ií]odo|m[eê]s|semana)\b|(?:hoje|ontem|amanh[aã]|desde|at[eé])\b|(?:tem|teve|possui)\s+(?:alguma?s?\s+)?ocorr[eê]ncias?\b|(?:em|no|na|do|da|de|com)$).*$/iu', '', $nome) ?? $nome;
        $nome = preg_replace('/[?.!,;:]+$/u', '', $nome) ?? $nome;
        $nome = trim($nome);
        if ($nome === '' || preg_match('/^(ela|ele|dessa|deste|desse|aluno|aluna|turma|sala|escola)$/iu', $nome) === 1) {
            return null;
        }
        // Evita capturar "ocorrencia" / palavras comuns como nome.
        if (preg_match('/^(ocorr|falta|boletim|nota|jornada|prova|frequ|(?:saber|ver|consultar|listar|mostrar|que|no|na|em|ess[ea]|est[ea]|mesm[oa])\b)/iu', $nome) === 1) {
            return null;
        }
        return $nome;
    }

    /**
     * Resolve aluno para consultas de leitura: prioriza RA/nome da mensagem atual;
     * histórico só como fallback (dela/dele/essa aluna).
     *
     * @param list<array{role?:string,content?:string}> $mensagens
     * @return array<string,mixed>
     */
    private function resolverArgsAlunoParaConsulta(string $mensagemUsuario, array $mensagens): array
    {
        $args = [];
        if (preg_match('/^Aluno já escolhido: aluno_id=(\d+)\./u', $mensagemUsuario, $m) === 1) {
            return ['aluno_id' => (int) $m[1]];
        }
        if (preg_match('/\b(?:da|do|na|no)\s+turma\s+([\pL\dªº .-]+)/iu', $mensagemUsuario, $m) === 1) {
            $turma = preg_split('/\s+(?:hoje|ontem|amanh[aã]|em|no|na)\b/iu', trim($m[1]), 2)[0] ?? '';
            $args['turma'] = trim($turma, ' .');
        }
        $raMsg = $this->extrairRaDaMensagem($mensagemUsuario);
        if ($raMsg !== null && $raMsg !== '') {
            $args['ra'] = $raMsg;
            return $args;
        }

        // Nome explícito na frase SEMPRE ganha do contexto da conversa.
        $nomeMsg = $this->extrairNomeAlunoDaConsulta($mensagemUsuario);
        if ($nomeMsg !== null) {
            $args['aluno_nome'] = $nomeMsg;
            return $args;
        }

        $ctx = $this->extrairContextoConsultaDoHistorico($mensagens);
        if ((int) ($ctx['aluno_id'] ?? 0) > 0) {
            $args['aluno_id'] = (int) $ctx['aluno_id'];
        } elseif (!empty($ctx['ra'])) {
            $args['ra'] = (string) $ctx['ra'];
        } elseif (!empty($ctx['aluno_nome'])) {
            $args['aluno_nome'] = (string) $ctx['aluno_nome'];
        }
        return $args;
    }

    private function aplicarFiltrosDoPedido(string $tool, array $args, string $pedido): array
    {
        $turmaSelecionada = 0;
        if (preg_match('/Turma selecionada: turma_id=(\d+)$/u', $pedido, $mTurma) === 1) {
            $turmaSelecionada = (int) $mTurma[1];
        }
        if ($tool === 'grade_horaria_consulta') {
            $data = $this->extrairDataDoPedido($pedido);
            $professor = $this->extrairNomeProfessor($pedido);
            if ($professor !== null && $this->pedidoPareceGradeProfessor($pedido)) {
                $args = ['professor_nome' => $professor] + array_intersect_key($args, ['data' => true, 'periodo' => true]);
            } elseif ($this->pedidoPareceGradeTurma($pedido) && ($turma = $this->extrairNomeTurma($pedido)) !== null) {
                $escopo = $turmaSelecionada > 0 ? ['turma_id' => $turmaSelecionada] : ['turma_nome' => $turma];
                $args = $escopo + array_intersect_key($args, ['data' => true, 'periodo' => true]);
            }
            return $data === null ? $args : ['data' => $data] + $args;
        }
        if (in_array($tool, ['faltas_aluno', 'ocorrencias_aluno', 'ocorrencias_turma'], true)) {
            $filtros = $this->filtrosDeFrequenciaEOcorrencias($pedido);
            if (isset($filtros['ano_letivo']) || isset($filtros['data_inicio']) || isset($filtros['data_fim'])) {
                unset($args['qualquer_periodo'], $args['todos_periodos'], $args['sem_filtro_ano'], $args['ano_letivo_id']);
                unset($args['data_inicio'], $args['data_fim'], $args['inicio'], $args['fim']);
            }
            if ($tool === 'ocorrencias_turma' && ($turma = $this->extrairNomeTurma($pedido)) !== null) {
                unset($args['turma_id'], $args['turma']);
                $filtros += $turmaSelecionada > 0 ? ['turma_id' => $turmaSelecionada] : ['turma_nome' => $turma];
            }
            $args = $filtros + $args;
        }
        if (in_array($tool, ['faltas_aluno', 'ocorrencias_aluno', 'boletim_aluno', 'notas_evento_aluno', 'nota_evento_sigla', 'perfil_aluno_360', 'propor_entrada_saida', 'propor_chamada_aula'], true)) {
            $aluno = $this->resolverArgsAlunoParaConsulta($pedido, []);
            if (!empty($aluno['ra']) || !empty($aluno['aluno_id'])
                || (!empty($aluno['aluno_nome']) && (!empty($aluno['turma']) || $tool === 'ocorrencias_aluno'))
            ) {
                unset($args['aluno_id'], $args['aluno_ra'], $args['ra'], $args['aluno_nome'], $args['nome'], $args['termo'], $args['alunos'], $args['alunos_ids'], $args['aluno_ids']);
                if (!empty($aluno['turma'])) {
                    unset($args['turma_nome'], $args['turma_id']);
                }
                $args = $aluno + $args;
            }
        }
        if ($tool === 'propor_entrada_saida') {
            $args['tipo'] = preg_match('/\bsa[ií]da\b/iu', $pedido) === 1 ? 'saida' : 'entrada';
            if (preg_match('/\b([01]?\d|2[0-3])[h:]([0-5]\d)\b/iu', $pedido, $m) === 1) {
                $data = $this->extrairDataDoPedido($pedido) ?? $this->ampliada->hojeLocal();
                $args['ocorrido_em'] = sprintf('%s %02d:%02d:00', $data, (int) $m[1], (int) $m[2]);
            }
        } elseif ($tool === 'propor_chamada_aula') {
            $data = $this->extrairDataDoPedido($pedido);
            if ($data !== null) {
                $args['data'] = $data;
            }
        }
        return $args;
    }

    private function consultaDiretaDoPedido(string $pedido): ?array
    {
        $aluno = $this->resolverArgsAlunoParaConsulta($pedido, []);
        $normalizado = mb_strtolower($this->semAcento($pedido));
        if (!empty($aluno['ra'])
            && preg_match('/^quais\s+provas\s+(?:o\s+)?ra\s+[a-z0-9]+\s+(?:fez|realizou)[?.!]*$/u', $normalizado) === 1
        ) {
            return ['tool' => 'listar_provas_aluno', 'args' => $aluno];
        }
        if (!empty($aluno['ra'])
            && preg_match('/^como\s+estao\s+as\s+jornadas\s+d[ao]\s+(?:alun[oa]\s+)?ra\s+[a-z0-9]+[?.!]*$/u', $normalizado) === 1
        ) {
            return ['tool' => 'resumo_jornadas_aluno', 'args' => $aluno];
        }
        if (!empty($aluno['ra']) && preg_match('/\bnota\s+(?:na|no|da|do)\s+([A-Z][A-Z0-9]{1,9})\b/u', $pedido, $m) === 1
            && $m[1] !== 'RA'
            && !$this->pedidoPareceNotaColunaBoletim($pedido)
        ) {
            $args = $aluno + ['sigla' => $m[1]] + $this->filtrosDeFrequenciaEOcorrencias($pedido);
            $materia = $this->extrairMateriaDaPergunta($pedido);
            if ($materia !== null) {
                $args['materia'] = $materia;
            }
            $bimestre = $this->extrairBimestreDaPergunta($pedido);
            if ($bimestre > 0) {
                $args['bimestre'] = $bimestre;
            }
            return ['tool' => 'nota_evento_sigla', 'args' => $args];
        }
        if (preg_match('/^quais\s+alunos\s+precisam\s+de\s+atencao\s+na\s+turma\s+([\d]+\s*[a-z])[?.!]*$/u', $normalizado, $m) === 1) {
            return ['tool' => 'saude_turma', 'args' => ['turma_nome' => $m[1]]];
        }
        if (preg_match('/^(?:quais|listar|buscar)\s+blocos?(?:\s+de\s+provas?)?\s+com\s+(.+?)\s+no\s+t[ií]tulo(?:\s+est[aã]o\s+cadastrados)?[?.!]*$/iu', $pedido, $m) === 1) {
            return ['tool' => 'buscar_blocos', 'args' => ['titulo' => trim($m[1], " \"'“”")]];
        }
        if (preg_match('/^quais\s+diarios\s+estao\s+em\s+atraso[?.!]*$/u', $normalizado) === 1) {
            return ['tool' => 'diario_pendencias', 'args' => ['situacao' => 'atraso']];
        }
        return null;
    }

    private function filtrosDeFrequenciaEOcorrencias(string $pedido): array
    {
        $filtros = [];
        if (preg_match('/\b(?:em|de|ano(?:\s+letivo)?(?:\s+de)?)\s+(20\d{2})\b/iu', $pedido, $m) === 1) {
            $filtros['ano_letivo'] = (int) $m[1];
        }
        $data = $this->extrairDataDoPedido($pedido);
        if (preg_match('/\b(?:de|desde|a\s+partir\s+de)\s+(\d{2}\/\d{2}\/\d{4}|\d{4}-\d{2}-\d{2})\s+(?:a|at[eé])\s+(\d{2}\/\d{2}\/\d{4}|\d{4}-\d{2}-\d{2})\b/iu', $pedido, $m) === 1) {
            $filtros['data_inicio'] = $this->extrairDataDoPedido($m[1]);
            $filtros['data_fim'] = $this->extrairDataDoPedido($m[2]);
        } elseif ($data !== null && preg_match_all('/\b(?:\d{2}\/\d{2}\/\d{4}|\d{4}-\d{2}-\d{2})\b/', $pedido) < 2) {
            if (preg_match('/\b(?:desde|a\s+partir\s+de)\s+/iu', $pedido) !== 1) {
                $filtros['data_fim'] = $data;
            }
            if (preg_match('/\bat[eé]\s+/iu', $pedido) !== 1) {
                $filtros['data_inicio'] = $data;
            }
        }
        if (preg_match('/\bocorr[eê]ncias?\s+abertas?\b/iu', $pedido) === 1) {
            $filtros['status'] = 'aberta';
        } elseif (preg_match('/\bocorr[eê]ncias?\s+encerradas?\b/iu', $pedido) === 1) {
            $filtros['status'] = 'encerrada';
        }
        return $filtros;
    }

    private function extrairDataDoPedido(string $pedido): ?string
    {
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $pedido, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/\b(\d{2})\/(\d{2})\/(\d{4})\b/', $pedido, $m) === 1) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/\b(hoje|amanh[aã]|ontem)\b/iu', $pedido, $m) !== 1) {
            return null;
        }
        $hoje = $this->ampliada->hojeLocal();
        $dia = mb_strtolower($m[1]);
        if ($dia === 'hoje') {
            return $hoje;
        }
        return (new \DateTimeImmutable($hoje, new \DateTimeZone('America/Sao_Paulo')))
            ->modify($dia === 'ontem' ? '-1 day' : '+1 day')->format('Y-m-d');
    }

    /**
     * Confere se o resultado da tool é do aluno pedido (evita reusar Ana quando pediram Maria).
     *
     * @param array<string,mixed> $args
     * @param array<string,mixed> $resultado
     */
    private function resultadoConsultaBateComPedido(array $args, array $resultado): bool
    {
        if (empty($resultado['ok'])) {
            return false;
        }
        $aluno = is_array($resultado['aluno'] ?? null) ? $resultado['aluno'] : [];
        $idRes = (int) ($aluno['id'] ?? 0);
        $raRes = mb_strtolower(trim((string) ($aluno['ra'] ?? '')));
        $nomeRes = mb_strtolower($this->semAcento(trim((string) ($aluno['nome'] ?? ''))));

        if (!empty($args['aluno_id'])) {
            return $idRes === (int) $args['aluno_id'];
        }
        if (!empty($args['ra'])) {
            $raPed = mb_strtolower(trim((string) $args['ra']));
            return $raRes === $raPed;
        }
        if (!empty($args['aluno_nome'])) {
            $nomePed = mb_strtolower($this->semAcento(trim((string) $args['aluno_nome'])));
            if ($nomePed === '' || $nomeRes === '') {
                return false;
            }
            // "Maria" deve casar com "Maria Silva"; "Ana Luiza" não casa com pedido "Maria".
            if ($nomeRes === $nomePed || str_starts_with($nomeRes, $nomePed . ' ')) {
                return true;
            }
            // Também aceita se o pedido for nome composto contido no início.
            $tokens = preg_split('/\s+/u', $nomePed) ?: [];
            if (count($tokens) >= 2 && str_contains($nomeRes, $nomePed)) {
                return true;
            }
            return false;
        }
        // Sem critério na mensagem → aceita o resultado (usou só contexto).
        return true;
    }

    /**
     * Classifica o pedido do usuário para escolher a tool certa (determinístico).
     *
     * @param list<array{role?:string,content?:string}> $historico
     * @return array{intent:string,tool:string,modo:string}|null
     */
    private function classificarIntencaoPedido(string $mensagem, array $historico = []): ?array
    {
        $msg = trim(preg_replace('/\[hint_roteamento\][\s\S]*$/u', '', $mensagem) ?? $mensagem);
        if ($msg === '') {
            return null;
        }

        // Leitura antes de escrita nos pares ambíguos.
        if ($this->pedidoPareceConsultaOcorrencia($msg)) {
            $tool = preg_match('/\bocorr[eê]ncias?(?:\s+\pL+)?\s+(?:da|de)\s+turma\b/iu', $msg) === 1
                ? 'ocorrencias_turma' : 'ocorrencias_aluno';
            return ['intent' => 'consultar_ocorrencias', 'tool' => $tool, 'modo' => 'leitura'];
        }
        if ($this->pedidoPareceConsultaFaltas($msg)) {
            return ['intent' => 'consultar_faltas', 'tool' => 'faltas_aluno', 'modo' => 'leitura'];
        }
        if ($this->pedidoPareceBoletim($msg, $historico)) {
            return ['intent' => 'boletim', 'tool' => 'boletim_aluno', 'modo' => 'leitura'];
        }
        if ($this->pedidoPareceNotaColunaBoletim($msg, $historico)) {
            return ['intent' => 'nota_coluna', 'tool' => 'notas_evento_aluno', 'modo' => 'leitura'];
        }
        if ($this->pedidoPareceJornadasAluno($msg)) {
            return ['intent' => 'jornadas', 'tool' => 'resumo_jornadas_aluno', 'modo' => 'leitura'];
        }
        if ($this->pedidoPareceGradeTurma($msg) || $this->pedidoPareceGradeProfessor($msg, $historico)) {
            return ['intent' => 'grade', 'tool' => 'grade_horaria_consulta', 'modo' => 'leitura'];
        }
        if (preg_match('/\b(perfil\s*360|vis[aã]o\s+geral|perfil\s+(do|da)\s+alun)/iu', $msg) === 1) {
            return ['intent' => 'perfil_360', 'tool' => 'perfil_aluno_360', 'modo' => 'leitura'];
        }

        if ($this->pedidoPareceRegistroEscrita($msg)) {
            $tipo = $this->detectarTipoProposta($msg);
            return match ($tipo) {
                'entrada_saida' => ['intent' => 'entrada_saida', 'tool' => 'propor_entrada_saida', 'modo' => 'escrita'],
                'chamada_aula' => ['intent' => 'chamada', 'tool' => 'propor_chamada_aula', 'modo' => 'escrita'],
                default => ['intent' => 'criar_ocorrencia', 'tool' => 'propor_criar_ocorrencia', 'modo' => 'escrita'],
            };
        }

        return null;
    }

    /** @param array{intent:string,tool:string,modo:string} $intencao */
    private function montarHintRoteamento(array $intencao): string
    {
        $intent = strtoupper((string) ($intencao['intent'] ?? ''));
        $tool = (string) ($intencao['tool'] ?? '');
        $modo = (string) ($intencao['modo'] ?? '');
        if ($intent === '' || $tool === '') {
            return '';
        }

        $linhas = [
            '[hint_roteamento] Pedido classificado como ' . $intent . '.',
            'Tool obrigatória: ' . $tool . ' (modo ' . $modo . ').',
        ];
        if ($intent === 'CONSULTAR_OCORRENCIAS') {
            $linhas[] = 'NÃO use propor_criar_ocorrencia. NÃO peça categoria, gravidade, envolvidos nem notificar pais.';
            $linhas[] = 'Responda com a lista de ocorrências, de forma assertiva.';
        } elseif ($intent === 'CONSULTAR_FALTAS') {
            $linhas[] = 'NÃO use ocorrencias_aluno nem propor_criar_ocorrencia. Falta ≠ ocorrência.';
            $linhas[] = 'Responda quantidade de faltas/frequência de forma assertiva.';
        } elseif ($intent === 'CRIAR_OCORRENCIA') {
            $linhas[] = 'Fluxo de ESCRITA: colete categoria, descrição, gravidade, envolvidos e notificar pais antes do card Confirmar.';
        } elseif ($intent === 'BOLETIM') {
            $linhas[] = 'NÃO invente notas — chame boletim_aluno.';
        } elseif ($intent === 'NOTA_COLUNA') {
            $linhas[] = 'Use notas_evento_aluno com coluna/matéria/bimestre quando souber.';
        }

        return implode("\n", $linhas);
    }

    /** Consulta de faltas/frequência — não confundir com ocorrência nem com marcar falta. */
    private function pedidoPareceConsultaFaltas(string $pergunta): bool
    {
        $p = trim($pergunta);
        if ($p === '') {
            return false;
        }
        // Escrita de chamada ("marcar falta") não é consulta.
        if (preg_match(
            '/\b(marcar|marque|registrar|registre|lancar|lançar)\b.{0,40}\b(falta|faltas|presente|presenca|presença|chamada)\b/iu',
            $p
        ) === 1) {
            return false;
        }
        if ($this->pedidoPareceConsultaOcorrencia($p)) {
            return false;
        }
        $temFaltaFreq = preg_match('/\b(faltas?|frequ[eê]ncia|presen[cç]as?)\b/iu', $p) === 1;
        if (!$temFaltaFreq) {
            return false;
        }
        // Precisa de pista de leitura OU pergunta direta ("quantas faltas").
        return preg_match(
            '/\b(consult(ar|a|e)|ver|veja|mostra(r)?|liste?|listar|quais|quantas?|tem|tinha|saber|como\s+est[aá])\b/iu',
            $p
        ) === 1
            || preg_match('/\bquantas?\s+faltas?\b/iu', $p) === 1
            || preg_match('/\bfaltas?\s+(dela|dele|do\s+aluno|da\s+aluna)\b/iu', $p) === 1;
    }

    /**
     * @param list<array{role?:string,content?:string}> $mensagens
     * @param callable|null $onStatus
     * @return array{tool?:?string,resultado?:?array,consulta?:array,texto?:string}
     */
    private function garantirConsultaFaltas(
        string $mensagemUsuario,
        array $mensagens,
        ?string $ultimaTool,
        ?array $ultimoResultado,
        $onStatus = null
    ): array {
        $args = $this->resolverArgsAlunoParaConsulta($mensagemUsuario, $mensagens);
        $filtros = $this->filtrosDeFrequenciaEOcorrencias($mensagemUsuario);
        $ctxFa = $this->extrairContextoConsultaDoHistorico($mensagens);
        if (!empty($ctxFa['ano_letivo']) && empty($filtros['ano_letivo'])) {
            $args['ano_letivo'] = (int) $ctxFa['ano_letivo'];
        }
        if (empty($args['aluno_id']) && empty($args['ra']) && empty($args['aluno_nome'])) {
            if ($ultimaTool === 'faltas_aluno' && is_array($ultimoResultado) && !empty($ultimoResultado['ok'])) {
                return ['texto' => $this->montarTextoConsultaFaltas($ultimoResultado)];
            }
            return [
                'texto' => 'Para consultar as faltas, me diga o **nome** ou **RA** do aluno.',
            ];
        }

        if (
            $ultimaTool === 'faltas_aluno'
            && is_array($ultimoResultado)
            && $this->resultadoConsultaBateComPedido($args, $ultimoResultado)
        ) {
            return [
                'tool' => 'faltas_aluno',
                'resultado' => $ultimoResultado,
                'texto' => $this->montarTextoConsultaFaltas($ultimoResultado),
            ];
        }

        if ($onStatus !== null) {
            $onStatus('Consultando frequência…');
        }
        $args += $filtros;
        $resultado = $this->ampliada->faltasAluno($args);
        if (!empty($resultado['candidatos']) || ((!empty($resultado['alunos'])) && count($resultado['alunos']) > 1)) {
            return [
                'tool' => 'faltas_aluno',
                'resultado' => $resultado,
                'consulta' => ['tool' => 'faltas_aluno', 'args' => $args],
                'texto' => trim((string) ($resultado['aviso'] ?? 'Há mais de um aluno. Escolha um:')),
            ];
        }
        if (empty($resultado['ok'])) {
            return [
                'tool' => 'faltas_aluno',
                'resultado' => $resultado,
                'consulta' => ['tool' => 'faltas_aluno', 'args' => $args],
                'texto' => trim((string) ($resultado['error'] ?? $resultado['aviso'] ?? 'Não foi possível consultar as faltas.')),
            ];
        }

        return [
            'tool' => 'faltas_aluno',
            'resultado' => $resultado,
            'consulta' => ['tool' => 'faltas_aluno', 'args' => $args],
            'texto' => $this->montarTextoConsultaFaltas($resultado),
        ];
    }

    /** @param array<string,mixed> $resultado */
    private function montarTextoConsultaFaltas(array $resultado): string
    {
        $aluno = is_array($resultado['aluno'] ?? null) ? $resultado['aluno'] : [];
        $nome = trim((string) ($aluno['nome'] ?? 'o aluno'));
        $freq = is_array($resultado['frequencia'] ?? null) ? $resultado['frequencia'] : null;
        if ($freq === null) {
            return '**' . $nome . '** não tem lançamentos de frequência no período atual.'
                . (!empty($resultado['aviso']) ? ' ' . trim((string) $resultado['aviso']) : '');
        }
        $faltas = $freq['faltas'] ?? null;
        $pct = $freq['percentual'] ?? null;
        $just = $freq['faltas_justificadas'] ?? null;
        $txt = '**' . $nome . '**';
        if ($faltas !== null && $faltas !== '') {
            $txt .= ' tem **' . $faltas . '** falta' . ((int) $faltas === 1 ? '' : 's') . ' sem justificativa';
        } else {
            $txt .= ' — frequência consultada';
        }
        if ($pct !== null && $pct !== '') {
            $txt .= ' (frequência **' . $pct . '%**)';
        }
        $txt .= '.';
        if ($just !== null && (int) $just > 0) {
            $txt .= ' Também tem **' . $just . '** falta' . ((int) $just === 1 ? ' justificada.' : 's justificadas.');
        }
        return $txt;
    }

    private function pedidoPareceRegistroEscrita(string $pergunta): bool
    {
        // Consulta/listagem nunca é escrita — mesmo com "quero" ("quero saber quais ocorrências").
        if ($this->pedidoPareceConsultaOcorrencia($pergunta) || $this->pedidoPareceConsultaFaltas($pergunta)) {
            return false;
        }
        if (preg_match('/\b(n[aã]o|nunca)\s+(?:(?:quero|preciso|pode|deve)\s+)?(?:registr|cri|fa[çz]|lanc|lanç|abr|marc)/iu', $pergunta) === 1) {
            return false;
        }
        // Só verbos de escrita — não capturar consulta ("tem ocorrência do João?").
        // Inclui "fazer/quero uma ocorrência" (falas comuns do QA).
        return (bool) preg_match(
            '/\b(registrar|registre|criar|crie|fazer|fa[cç]a|quero|preciso|lancar|lançar|abra|abrir|marcar|marque)\b.{0,80}\b(ocorr[eê]ncia|incidente|entrada|sa[ií]da|chamada|presen[cç]a|falta|presente)\b/iu',
            $pergunta
        ) && !preg_match(
            '/\b(quero|preciso)\b.{0,40}\b(saber|ver|consultar|listar|mostrar|quais|quantas?)\b/iu',
            $pergunta
        );
    }

    /**
     * Pedido de LEITURA de ocorrências (lista/consulta) — não cria rascunho.
     */
    private function pedidoPareceConsultaOcorrencia(string $pergunta): bool
    {
        $p = trim($pergunta);
        if ($p === '') {
            return false;
        }
        if (!preg_match('/\bocorr[eê]nci/iu', $p)) {
            return false;
        }
        // Verbos explícitos de criação → só é consulta se o pedido COMEÇA como leitura.
        $temCriacao = preg_match(
            '/\b(registrar|registre|criar|crie|fazer|fa[cç]a|lancar|lançar|abrir|abra)\b.{0,60}\b(ocorr[eê]ncia|incidente)\b/iu',
            $p
        ) === 1;
        $leituraForte = preg_match(
            '/^\s*(poderia\s+|pode\s+|quero\s+|preciso\s+)?(saber|consultar|consultas?|ver|veja|mostra(r)?|liste?|listar|quais|quantas?)\b/iu',
            $p
        ) === 1
            || preg_match('/\b(quero|preciso)\b.{0,30}\b(saber|ver|consultar|listar|mostrar)\b.{0,40}\bocorr/iu', $p) === 1;
        $leituraFraca = preg_match(
            '/\b(tem|tinha|houve|histórico|historico)\b.{0,40}\bocorr/iu',
            $p
        ) === 1
            || preg_match('/\bocorr[eê]ncias?\b.{0,40}\b(dela|dele|do\s+aluno|da\s+aluna|dessa|deste)\b/iu', $p) === 1
            || preg_match('/\b(dela|dele)\b.{0,20}\bocorr/iu', $p) === 1;

        if ($temCriacao) {
            // "registrar ocorrência… ele tem batido" NÃO é consulta.
            return $leituraForte;
        }
        return $leituraForte || $leituraFraca;
    }

    /**
     * Continuação de rascunho (categoria, "essa aluna", descrição) sem repetir o verbo.
     *
     * @param list<array{role?:string,content?:string}> $historico
     */
    private function pedidoPareceContinuacaoEscrita(string $pergunta, array $historico): bool
    {
        // Mudança de intenção: usuário pediu consulta → abandona coleta.
        if ($this->pedidoPareceConsultaOcorrencia($pergunta) || $this->pedidoPareceConsultaFaltas($pergunta)) {
            return false;
        }
        $ctx = $this->extrairContextoConsultaDoHistorico($historico);
        $p = trim($pergunta);
        if (preg_match('/^(?:registrar\s+ocorr[eê]ncia\s+com\s+)?categoria\s+/iu', $p) === 1) {
            return true;
        }
        if (preg_match('/diario_aula_id\s*=\s*\d+/i', $p) === 1) {
            return true;
        }
        if (
            preg_match('/\b(ess[ae]\s+alun[oa]|est[ae]\s+alun[oa]|el[ae]|dessel[ae]|dessa\s+aluna)\b/iu', $p) === 1
            && (
                !empty($ctx['aluno_id'])
                || !empty($ctx['proposta_pendente'])
                || $this->historicoRecenteFalaEscrita($historico)
            )
        ) {
            return true;
        }
        // Seleção de aluno após pedido de escrita.
        if (
            preg_match('/aluno_id\s*=\s*\d+|usar\s+aluno_id\s+\d+/i', $p) === 1
            && $this->historicoRecenteFalaEscrita($historico)
        ) {
            return true;
        }

        $emOcorrencia = !empty($ctx['proposta_pendente'])
            || $this->historicoRecenteFalaEscrita($historico)
            || $this->assistentePediuCampoOcorrencia($historico) !== null;
        if (!$emOcorrencia) {
            return false;
        }

        // Resposta livre após a Tudinha pedir categoria/detalhe (ex.: "Conversa na Sala de Aula").
        // Não engolir perguntas novas (consulta, boletim, etc.).
        if ($this->assistentePediuCampoOcorrencia($historico) !== null) {
            if ($this->mensagemParecePerguntaNova($p)) {
                return false;
            }
            return true;
        }
        if (preg_match('/^(conversa|disciplina|atraso|bullying|uniforme|comportamento)\b/iu', $p) === 1) {
            return true;
        }
        if (preg_match('/(?:detalhe|descri[cç][aã]o|t[ií]tulo)\s*[:\-]/iu', $p) === 1) {
            return true;
        }
        // Descrição longa sem verbo novo (continuação do rascunho).
        if (mb_strlen($p) >= 12
            && preg_match('/\b(boletim|nota|faltas?|grade|jornada|prova|chamada|entrada|sa[ií]da)\b/iu', $p) !== 1
            && !$this->mensagemParecePerguntaNova($p)
        ) {
            return true;
        }
        return false;
    }

    /** Usuário mudou de assunto / fez pergunta nova em vez de responder o campo da coleta. */
    private function mensagemParecePerguntaNova(string $mensagem): bool
    {
        $p = trim($mensagem);
        if ($p === '') {
            return false;
        }
        if ($this->pedidoPareceConsultaOcorrencia($p) || $this->pedidoPareceConsultaFaltas($p)) {
            return true;
        }
        if (preg_match('/\b(boletim|faltas?|nota|jornada|grade|prova|chamada)\b/iu', $p) === 1
            && preg_match('/\b(consultar|ver|mostra|lista|quais|quantas?|tem|saber)\b/iu', $p) === 1
        ) {
            return true;
        }
        if (preg_match('/\b(cancela|cancelar|desiste|desistir|para\s+com\s+isso|esquece|outra\s+coisa)\b/iu', $p) === 1) {
            return true;
        }
        // Pergunta explícita (não é "leve"/"não"/categoria curta).
        if (mb_strlen($p) >= 20
            && preg_match('/\?|(poderia|pode|quero\s+saber|me\s+mostra|me\s+fala|quais|quantas?)/iu', $p) === 1
            && preg_match('/^(leve|moderado|grave|sim|n[aã]o|nao)\b/iu', $p) !== 1
        ) {
            return true;
        }
        return false;
    }

    /**
     * Garante listagem real de ocorrências (não deixa a coleta de criação sequestrar).
     *
     * @param list<array{role?:string,content?:string}> $mensagens
     * @param callable|null $onStatus
     * @return array{tool?:?string,resultado?:?array,consulta?:array,texto?:string}
     */
    private function garantirConsultaOcorrencias(
        string $mensagemUsuario,
        array $mensagens,
        ?string $ultimaTool,
        ?array $ultimoResultado,
        $onStatus = null
    ): array {
        $args = $this->resolverArgsAlunoParaConsulta($mensagemUsuario, $mensagens);
        if (empty($args['aluno_id']) && empty($args['ra']) && empty($args['aluno_nome'])) {
            if ($ultimaTool === 'ocorrencias_aluno' && is_array($ultimoResultado) && !empty($ultimoResultado['ok'])) {
                return ['texto' => $this->montarTextoConsultaOcorrencias($ultimoResultado)];
            }
            return [
                'texto' => 'Para consultar as ocorrências, me diga o **nome** ou **RA** do aluno.',
            ];
        }

        // Só reusa o resultado da IA se for o MESMO aluno pedido na mensagem.
        if (
            in_array($ultimaTool, ['ocorrencias_aluno', 'ocorrencias_turma'], true)
            && is_array($ultimoResultado)
            && $this->resultadoConsultaBateComPedido($args, $ultimoResultado)
        ) {
            return [
                'tool' => $ultimaTool,
                'resultado' => $ultimoResultado,
                'texto' => $this->montarTextoConsultaOcorrencias($ultimoResultado),
            ];
        }

        if ($onStatus !== null) {
            $onStatus('Consultando ocorrências…');
        }
        $args += $this->filtrosDeFrequenciaEOcorrencias($mensagemUsuario);
        $resultado = $this->ampliada->ocorrenciasAluno($args);
        if (!empty($resultado['candidatos']) || ((!empty($resultado['alunos'])) && count($resultado['alunos']) > 1)) {
            return [
                'tool' => 'ocorrencias_aluno',
                'resultado' => $resultado,
                'consulta' => ['tool' => 'ocorrencias_aluno', 'args' => $args],
                'texto' => trim((string) ($resultado['aviso'] ?? 'Há mais de um aluno. Escolha um:')),
            ];
        }
        if (empty($resultado['ok'])) {
            return [
                'tool' => 'ocorrencias_aluno',
                'resultado' => $resultado,
                'consulta' => ['tool' => 'ocorrencias_aluno', 'args' => $args],
                'texto' => trim((string) ($resultado['error'] ?? $resultado['aviso'] ?? 'Não foi possível consultar as ocorrências.')),
            ];
        }

        return [
            'tool' => 'ocorrencias_aluno',
            'resultado' => $resultado,
            'consulta' => ['tool' => 'ocorrencias_aluno', 'args' => $args],
            'texto' => $this->montarTextoConsultaOcorrencias($resultado),
        ];
    }

    /** @param array<string,mixed> $resultado */
    private function montarTextoConsultaOcorrencias(array $resultado): string
    {
        $aluno = is_array($resultado['aluno'] ?? null) ? $resultado['aluno'] : [];
        $nome = trim((string) ($aluno['nome'] ?? 'o aluno'));
        $total = (int) ($resultado['total'] ?? 0);
        $lista = is_array($resultado['ocorrencias'] ?? null) ? $resultado['ocorrencias'] : [];
        if ($total <= 0 || $lista === []) {
            return '**' . $nome . '** não tem ocorrências registradas no filtro atual.';
        }
        $linhas = ['**' . $nome . '** tem **' . $total . '** ocorrência' . ($total === 1 ? '' : 's') . ':'];
        foreach (array_slice($lista, 0, 8) as $oc) {
            if (!is_array($oc)) {
                continue;
            }
            $data = trim((string) ($oc['data'] ?? ''));
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $data, $m)) {
                $data = $m[3] . '/' . $m[2] . '/' . $m[1];
            }
            $titulo = trim((string) ($oc['titulo'] ?? ''));
            $cat = trim((string) ($oc['categoria'] ?? ''));
            $grav = trim((string) ($oc['gravidade_label'] ?? $oc['gravidade'] ?? ''));
            $status = trim((string) ($oc['status_label'] ?? $oc['status'] ?? ''));
            $pedaco = '- ';
            if ($data !== '') {
                $pedaco .= $data . ' — ';
            }
            $pedaco .= $titulo !== '' ? $titulo : 'Ocorrência';
            $meta = array_filter([$cat, $grav, $status], static fn ($x) => $x !== '');
            if ($meta !== []) {
                $pedaco .= ' (' . implode(' · ', $meta) . ')';
            }
            $linhas[] = $pedaco;
        }
        if ($total > 8) {
            $linhas[] = '_…e mais ' . ($total - 8) . ' no painel abaixo._';
        }
        return implode("\n", $linhas);
    }

    /**
     * Última fala do assistente pedindo campo da ocorrência.
     *
     * @param list<array{role?:string,content?:string}> $historico
     * @return 'categoria'|'detalhe'|'titulo'|'gravidade'|'notificar_pais'|'mais_envolvidos'|'aluno'|null
     */
    private function assistentePediuCampoOcorrencia(array $historico): ?string
    {
        foreach (array_reverse(array_slice($historico, -8)) as $msg) {
            if (($msg['role'] ?? '') !== 'assistant') {
                continue;
            }
            $raw = (string) ($msg['content'] ?? '');
            if (preg_match('/aguardando_campo="(categoria|detalhe|titulo|gravidade|notificar_pais|mais_envolvidos)"/u', $raw, $mCampo) === 1) {
                return $mCampo[1];
            }
            if (!empty($msg['proposta_id']) || str_contains($raw, '[contexto_estruturado]')) {
                return null;
            }
            break;
        }
        foreach (array_reverse(array_slice($historico, -6)) as $msg) {
            if (($msg['role'] ?? '') !== 'assistant') {
                continue;
            }
            $c = mb_strtolower($this->semAcento((string) ($msg['content'] ?? '')));
            if ($c === '') {
                continue;
            }
            if (preg_match('/(notificar|avisar).{0,30}(pais|responsaveis)/u', $c) === 1) {
                return 'notificar_pais';
            }
            if (preg_match('/(mais alguem|mais pessoa|outra pessoa|outros alunos|vincular|envolvid)/u', $c) === 1) {
                return 'mais_envolvidos';
            }
            if (preg_match('/(gravidade|leve.*moderado.*grave|moderado ou grave)/u', $c) === 1) {
                return 'gravidade';
            }
            if (preg_match('/categoria/u', $c) === 1) {
                return 'categoria';
            }
            if (preg_match('/(detalhe|descricao|descreva|o que aconteceu|forneca um detalhe|informe o detalhe)/u', $c) === 1) {
                return 'detalhe';
            }
            if (preg_match('/titulo/u', $c) === 1) {
                return 'titulo';
            }
            if (preg_match('/(qual alun|informe o alun|confirme o alun)/u', $c) === 1) {
                return 'aluno';
            }
            // Só olha a última resposta relevante do assistente.
            break;
        }
        return null;
    }

    /** @param list<array{role?:string,content?:string}> $historico */
    private function historicoRecenteFalaEscrita(array $historico): bool
    {
        foreach (array_reverse(array_slice($historico, -8)) as $msg) {
            $c = (string) ($msg['content'] ?? '');
            if ($this->pedidoPareceRegistroEscrita($c)) {
                return true;
            }
            if (preg_match('/proposta_pendente\s*=\s*1|coleta_ocorrencia\s*=\s*1|propor_criar_ocorrencia|propor_chamada_aula|propor_entrada_saida|candidatos_categorias|informe a categoria|aguardando_campo=/iu', $c) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<array{role?:string,content?:string}> $mensagens
     * @param callable|null $onStatus
     * @return array{tool?:?string,resultado?:?array,consulta?:array,texto?:string}
     */
    private function garantirPropostaEscrita(
        string $mensagemUsuario,
        array $mensagens,
        ?string $ultimaTool,
        ?array $ultimoResultado,
        string $textoAtual,
        $onStatus = null
    ): array {
        $toolsProposta = ['propor_criar_ocorrencia', 'propor_entrada_saida', 'propor_chamada_aula'];
        if (
            in_array($ultimaTool, $toolsProposta, true)
            && is_array($ultimoResultado)
            && !empty($ultimoResultado['ok'])
            && !empty($ultimoResultado['proposta_id'])
        ) {
            return [
                'tool' => $ultimaTool,
                'resultado' => $ultimoResultado,
            ];
        }

        // Candidatos de aluno já resolvidos pela tool de propor — manter chips.
        if (
            in_array($ultimaTool, $toolsProposta, true)
            && is_array($ultimoResultado)
            && !empty($ultimoResultado['candidatos'])
        ) {
            return [
                'tool' => $ultimaTool,
                'resultado' => $ultimoResultado,
                'texto' => trim((string) ($ultimoResultado['aviso'] ?? 'Há mais de um aluno. Escolha um:')),
            ];
        }
        // Categoria ambígua: manter chips (não deixar a IA inventar "não reconhecida").
        if (
            in_array($ultimaTool, $toolsProposta, true)
            && is_array($ultimoResultado)
            && !empty($ultimoResultado['candidatos_categorias'])
            && empty($ultimoResultado['ok'])
            && !$this->mensagemTrazCategoriaOuDetalhe($mensagemUsuario, $mensagens)
        ) {
            return [
                'tool' => $ultimaTool,
                'resultado' => $ultimoResultado,
                'texto' => trim((string) ($ultimoResultado['aviso'] ?? 'Confirme a categoria pelo nome:')),
            ];
        }

        $tipo = $this->detectarTipoProposta($mensagemUsuario);
        if ($tipo === 'criar_ocorrencia' && $this->historicoRecenteFalaEscrita($mensagens)) {
            $tipo = 'criar_ocorrencia';
        } elseif (
            $this->historicoRecenteFalaEscrita($mensagens)
            && !$this->pedidoPareceRegistroEscrita($mensagemUsuario)
            && !preg_match('/\b(entrada|saida|chamada|presente|falta)\b/iu', $mensagemUsuario)
        ) {
            $tipo = 'criar_ocorrencia';
        }
        $args = $this->montarArgsProposta($mensagemUsuario, $mensagens, $ultimoResultado, $tipo);

        // Sem aluno resolvido: busca candidatos com botão Confirmar (não só texto).
        if ($args === null || (empty($args['aluno_id']) && empty($args['aluno_nome']) && empty($args['alunos']))) {
            $termoAluno = $this->extrairNomeAlunoParaAcao($mensagemUsuario);
            if ($termoAluno !== null) {
                if ($onStatus !== null) {
                    $onStatus('Buscando aluno para a ação…');
                }
                $busca = $this->executarTool('buscar_alunos', ['termo' => $termoAluno]);
                $alunos = is_array($busca['alunos'] ?? null) ? $busca['alunos'] : [];
                if (count($alunos) > 1) {
                    return [
                        'tool' => 'buscar_alunos',
                        'resultado' => $busca + ['aviso' => 'Confirme o aluno para continuar a ação:'],
                        'consulta' => ['tool' => 'buscar_alunos', 'args' => ['termo' => $termoAluno]],
                        'texto' => 'Encontrei mais de um aluno. Confirme qual deles:',
                    ];
                }
                if (count($alunos) === 1) {
                    $args = $args ?? [];
                    $args['aluno_id'] = (int) ($alunos[0]['id'] ?? 0);
                    $args['aluno_nome'] = trim((string) ($alunos[0]['nome'] ?? ''));
                    $args['alunos'] = [$args['aluno_nome']];
                    if (!empty($alunos[0]['turma_nome'])) {
                        $args['turma'] = (string) $alunos[0]['turma_nome'];
                    }
                }
            }
        }

        if ($args === null || (empty($args['aluno_id']) && empty($args['aluno_nome']) && empty($args['alunos']))) {
            return [
                'texto' => 'Para montar a proposta com o botão **Confirmar**, informe o aluno (nome+turma ou RA)'
                    . ($tipo === 'criar_ocorrencia'
                        ? ' e o motivo da ocorrência.'
                        : ($tipo === 'entrada_saida'
                            ? ', se é entrada ou saída, e o horário.'
                            : ', a situação (presente/falta) e a data/matéria da aula.')),
            ];
        }

        // Completa ocorrência; sem categoria/descrição reais NÃO inventa rascunho vazio.
        if ($tipo === 'criar_ocorrencia') {
            $args = $this->completarArgsOcorrencia($args, $mensagemUsuario, $mensagens);
            // Antes de pedir categoria, resolve ambiguidade de aluno (chips) — sem gravar rascunho.
            if (empty($args['aluno_id']) && (!empty($args['aluno_nome']) || !empty($args['alunos']) || !empty($args['ra']))) {
                $probe = $this->acoes->resolverAlunosParaAcao([
                    'aluno_id' => $args['aluno_id'] ?? null,
                    'aluno_nome' => $args['aluno_nome'] ?? null,
                    'alunos' => $args['alunos'] ?? null,
                    'ra' => $args['ra'] ?? null,
                    'turma' => $args['turma'] ?? null,
                ]);
                if (!empty($probe['candidatos'])) {
                    return [
                        'tool' => 'propor_criar_ocorrencia',
                        'resultado' => $probe,
                        'consulta' => ['tool' => 'resolver_alunos_acao', 'args' => $args],
                        'texto' => trim((string) ($probe['aviso'] ?? 'Encontrei mais de um aluno. Confirme qual deles:')),
                    ];
                }
                if (!empty($probe['error']) && str_contains((string) $probe['error'], 'aluno')) {
                    return [
                        'texto' => (string) $probe['error'],
                    ];
                }
                if (!empty($probe['aluno']['id'])) {
                    $args['aluno_id'] = (int) $probe['aluno']['id'];
                    $args['aluno_nome'] = trim((string) ($probe['aluno']['nome'] ?? $args['aluno_nome'] ?? ''));
                    if (!empty($probe['aluno']['ra'])) {
                        $args['ra'] = (string) $probe['aluno']['ra'];
                    }
                    if (!empty($probe['aluno']['turma_nome'])) {
                        $args['turma'] = (string) $probe['aluno']['turma_nome'];
                    }
                }
            }
            if (!$this->ocorrenciaProntaParaProposta($args)) {
                return $this->pedirCamposOcorrencia($args, $mensagemUsuario, $mensagens, $onStatus);
            }
        }

        $tool = match ($tipo) {
            'entrada_saida' => 'propor_entrada_saida',
            'chamada_aula' => 'propor_chamada_aula',
            default => 'propor_criar_ocorrencia',
        };

        if ($onStatus !== null) {
            $onStatus('Preparando proposta…');
        }
        $resultado = $this->executarTool($tool, $args);
        $texto = null;
        if (!empty($resultado['ok']) && !empty($resultado['proposta_id'])) {
            $det = is_array($resultado['detalhes'] ?? null)
                ? $resultado['detalhes']
                : $this->acoes->detalhesProposta(
                    (string) ($resultado['tipo'] ?? ''),
                    is_array($resultado['payload'] ?? null) ? $resultado['payload'] : []
                );
            $texto = 'Preparei uma proposta de **'
                . trim((string) ($det['tipo_label'] ?? 'ação'))
                . '**. Revise o título, a descrição, a data/hora e os envolvidos abaixo e confirme.';
        } elseif (!empty($resultado['candidatos'])) {
            $texto = trim((string) ($resultado['aviso'] ?? 'Há mais de um aluno. Escolha um:'));
        } elseif ($tipo === 'criar_ocorrencia' && ($resultado['campo_invalido'] ?? '') === 'detalhe') {
            $args['detalhe'] = '';
            $args['titulo'] = '';
            return $this->pedirCamposOcorrencia($args, $mensagemUsuario, $mensagens, $onStatus);
        } elseif ($tipo === 'criar_ocorrencia' && ($resultado['campo_invalido'] ?? '') === 'gravidade') {
            $args['nivel_gravidade'] = '';
            $args['nivel_gravidade_explicito'] = false;
            return $this->pedirCamposOcorrencia($args, $mensagemUsuario, $mensagens, $onStatus);
        } elseif ($tipo === 'criar_ocorrencia' && !empty($resultado['candidatos_categorias'])) {
            $args['categoria'] = '';
            unset($args['categoria_nome'], $args['categoria_id']);
            return $this->pedirCamposOcorrencia(
                $args,
                $mensagemUsuario,
                $mensagens,
                $onStatus,
                $resultado['candidatos_categorias']
            );
        } else {
            $texto = trim((string) ($resultado['error'] ?? $resultado['aviso'] ?? ''));
            if ($texto === '') {
                $texto = 'Não consegui montar a proposta com botão Confirmar. '
                    . 'Informe os dados faltantes (aluno, título/detalhe/gravidade ou horário).';
            }
            // Evita resposta da IA pedindo "confirme" sem painel.
            if (preg_match('/confirm/iu', $textoAtual) === 1 && empty($resultado['proposta_id'])) {
                $texto = $texto !== '' ? $texto : 'Faltam dados para montar a proposta com o botão Confirmar.';
            }
        }

        return [
            'tool' => $tool,
            'resultado' => $resultado,
            'consulta' => ['tool' => $tool, 'args' => $args],
            'texto' => $texto,
        ];
    }

    private function detectarTipoProposta(string $pergunta): string
    {
        $p = mb_strtolower($this->semAcento($pergunta));
        if (preg_match('/\bocorrencia\b|\bincidente\b/u', $p) === 1) {
            return 'criar_ocorrencia';
        }
        // Chip/continuação de categoria → ocorrência (não consulta genérica).
        if (preg_match('/^(?:registrar\s+ocorrencia\s+com\s+)?categoria\s+/u', $p) === 1) {
            return 'criar_ocorrencia';
        }
        if (preg_match('/\b(entrada|saida)\b/u', $p) === 1) {
            return 'entrada_saida';
        }
        if (preg_match('/\b(chamada|presente|falta|diario_aula_id)\b/u', $p) === 1) {
            return 'chamada_aula';
        }
        return 'criar_ocorrencia';
    }

    /**
     * @param list<array{role?:string,content?:string}> $mensagens
     * @return array<string,mixed>|null
     */
    private function montarArgsProposta(
        string $mensagemUsuario,
        array $mensagens,
        ?array $ultimoResultado,
        string $tipo
    ): ?array {
        $args = [];
        $ctx = $this->extrairContextoConsultaDoHistorico($mensagens);
        $refPronome = preg_match(
            '/\b(ess[ae]\s+alun[oa]|est[ae]\s+alun[oa]|el[ae]|dessa\s+aluna|desse\s+aluno)\b/iu',
            $mensagemUsuario
        ) === 1;

        $alunoId = (int) ($ultimoResultado['aluno']['id'] ?? 0);
        if (preg_match('/^Aluno já escolhido: aluno_id=(\d+)\./u', $mensagemUsuario, $mSelecionado) === 1) {
            $alunoId = (int) $mSelecionado[1];
        }
        if ($alunoId <= 0) {
            $alunoId = (int) ($ctx['aluno_id'] ?? 0);
        }
        if ($alunoId <= 0 && preg_match('/aluno_id\s*=\s*(\d+)/i', $mensagemUsuario, $mAid) === 1) {
            $alunoId = (int) $mAid[1];
        }
        if ($alunoId > 0) {
            $args['aluno_id'] = $alunoId;
        }

        $raMsg = $this->extrairRaDaMensagem($mensagemUsuario);
        if ($raMsg !== null && $raMsg !== '' && empty($args['aluno_id'])) {
            $args['ra'] = $raMsg;
            $args['aluno_nome'] = 'ra ' . $raMsg;
            $args['alunos'] = ['ra ' . $raMsg];
        } elseif (!empty($ctx['ra']) && ($refPronome || empty($args['aluno_id']))) {
            $args['ra'] = (string) $ctx['ra'];
        }

        $nomeAluno = trim((string) ($ultimoResultado['aluno']['nome'] ?? ''));
        if ($nomeAluno === '' && !empty($ctx['aluno_nome']) && ($refPronome || $alunoId > 0)) {
            $nomeAluno = (string) $ctx['aluno_nome'];
        }
        if ($nomeAluno === '' && !$refPronome && preg_match(
            '/\b(?:aluna|aluno)\s+([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\s]{2,60})/u',
            $mensagemUsuario,
            $mNome
        ) === 1) {
            $nomeAluno = trim($mNome[1]);
            $nomeAluno = preg_replace('/\s+(?:da|do|de|no|na|para|com|por|sobre|RA)\b.*$/iu', '', $nomeAluno) ?? $nomeAluno;
            $nomeAluno = trim($nomeAluno);
        }
        if ($nomeAluno !== '') {
            $args['aluno_nome'] = $nomeAluno;
            $args['alunos'] = [$nomeAluno];
        }
        if ($raMsg !== null && !preg_match('/^Aluno já escolhido:/u', $mensagemUsuario)) {
            unset($args['aluno_id'], $args['aluno_nome'], $args['alunos']);
            $args['ra'] = $raMsg;
            $args['aluno_nome'] = 'ra ' . $raMsg;
        }

        $turma = trim((string) ($ultimoResultado['aluno']['turma_nome'] ?? $ultimoResultado['turma']['nome'] ?? ''));
        if ($turma === '' && !empty($ctx['turma_nome'])) {
            $turma = (string) $ctx['turma_nome'];
        }
        if ($turma === '' && preg_match('/\bturma\s+([A-Za-zÀ-ÿ0-9ªº°][A-Za-zÀ-ÿ0-9ªº°\s.\-]{0,40})/iu', $mensagemUsuario, $mTur) === 1) {
            $turma = trim($mTur[1]);
        }
        if ($turma !== '') {
            $args['turma'] = $turma;
        }

        if ($tipo === 'entrada_saida') {
            $p = mb_strtolower($this->semAcento($mensagemUsuario));
            $args['tipo'] = str_contains($p, 'saida') ? 'saida' : 'entrada';
            // 7h55, 07:55, 7:55
            if (preg_match('/\b(\d{1,2})[h:](\d{2})\b/iu', $mensagemUsuario, $mH) === 1) {
                $args['ocorrido_em'] = sprintf(
                    '%s %02d:%02d:00',
                    $this->extrairDataDoPedido($mensagemUsuario) ?? $this->ampliada->hojeLocal(),
                    (int) $mH[1],
                    (int) $mH[2]
                );
            }
            if (empty($args['aluno_id']) && empty($args['aluno_nome']) && empty($args['ra'])) {
                return null;
            }
            return $args;
        }

        if ($tipo === 'chamada_aula') {
            $p = mb_strtolower($this->semAcento($mensagemUsuario));
            $args['situacao'] = str_contains($p, 'falta') ? 'falta' : 'presente';
            if (preg_match('/diario_aula_id\s*=\s*(\d+)/i', $mensagemUsuario, $mAula) === 1) {
                $args['diario_aula_id'] = (int) $mAula[1];
            }
            $args['data'] = $this->extrairDataDoPedido($mensagemUsuario) ?? $this->ampliada->hojeLocal();
            $mat = $this->extrairMateriaDaPergunta($mensagemUsuario);
            if ($mat !== null) {
                $args['materia'] = $mat;
            }
            if (empty($args['aluno_id']) && empty($args['aluno_nome']) && empty($args['ra'])) {
                return null;
            }
            return $args;
        }

        // Ocorrência: monta rascunho mesmo com campos parciais (defaults no completarArgsOcorrencia).
        if (empty($args['aluno_id']) && empty($args['aluno_nome']) && empty($args['alunos']) && empty($args['ra'])) {
            $nomeLivre = $this->extrairNomeAlunoParaAcao($mensagemUsuario);
            if ($nomeLivre === null) {
                // Continuação: "Conversa na Sala…" / detalhe — reusa aluno do pedido original.
                $pedidoOc = $this->ultimoPedidoUsuario($mensagens, null) ?: '';
                foreach (array_reverse(array_slice($mensagens, -10)) as $msg) {
                    if (($msg['role'] ?? '') !== 'user') {
                        continue;
                    }
                    $c = (string) ($msg['content'] ?? '');
                    if ($this->pedidoPareceRegistroEscrita($c) || preg_match('/\bocorr/iu', $c) === 1) {
                        $pedidoOc = $c;
                        break;
                    }
                }
                $nomeLivre = $this->extrairNomeAlunoParaAcao($pedidoOc);
                if ($nomeLivre === null) {
                    // "para Ana Luiza" / "da Ana Luiza" sem a palavra aluno.
                    if (preg_match('/\b(?:para|da|do)\s+(?:a\s+|o\s+)?([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\s]{1,60})/u', $pedidoOc, $mP) === 1) {
                        $nomeLivre = trim(preg_replace('/\s+(?:da|do|de|turma|RA|,).*$/iu', '', trim($mP[1])) ?? '');
                        if ($nomeLivre === '') {
                            $nomeLivre = null;
                        }
                    }
                }
            }
            if ($nomeLivre !== null) {
                $args['aluno_nome'] = $nomeLivre;
                $args['alunos'] = [$nomeLivre];
            } elseif ($refPronome && !empty($ctx['aluno_id'])) {
                $args['aluno_id'] = (int) $ctx['aluno_id'];
            } elseif (!empty($ctx['aluno_id'])) {
                $args['aluno_id'] = (int) $ctx['aluno_id'];
            } elseif (!empty($ctx['aluno_nome'])) {
                $args['aluno_nome'] = (string) $ctx['aluno_nome'];
                $args['alunos'] = [(string) $ctx['aluno_nome']];
            } else {
                return null;
            }
        }
        return $this->completarArgsOcorrencia($args, $mensagemUsuario, $mensagens);
    }

    /**
     * @param array<string,mixed> $args
     * @param list<array{role?:string,content?:string}> $mensagens
     * @return array<string,mixed>
     */
    private function completarArgsOcorrencia(array $args, string $mensagemUsuario, array $mensagens = []): array
    {
        $ctx = $mensagens !== [] ? $this->extrairContextoConsultaDoHistorico($mensagens) : [];
        $titulo = trim((string) ($args['titulo'] ?? ''));
        $detalhe = trim((string) ($args['detalhe'] ?? $args['descricao'] ?? ''));
        $nivel = strtolower(trim((string) ($args['nivel_gravidade'] ?? $args['gravidade'] ?? '')));

        if ($titulo !== '' && !$this->textoOcorrenciaSignificativo($titulo)) {
            $titulo = '';
        }
        if ($detalhe !== '' && !$this->textoOcorrenciaSignificativo($detalhe)) {
            $detalhe = '';
        }

        if ($titulo === '' && !empty($ctx['ocorrencia_titulo'])
            && $this->textoOcorrenciaSignificativo((string) $ctx['ocorrencia_titulo'])
        ) {
            $titulo = (string) $ctx['ocorrencia_titulo'];
        }
        if ($detalhe === '' && !empty($ctx['ocorrencia_detalhe'])
            && $this->textoOcorrenciaSignificativo((string) $ctx['ocorrencia_detalhe'])
        ) {
            $detalhe = (string) $ctx['ocorrencia_detalhe'];
        }

        // Junta texto de pedidos anteriores da ocorrência (preserva descrição entre mensagens).
        $textoHist = $mensagemUsuario;
        foreach (array_reverse(array_slice($mensagens, -8)) as $msg) {
            if (($msg['role'] ?? '') !== 'user') {
                continue;
            }
            $c = trim((string) ($msg['content'] ?? ''));
            if ($c === '' || str_starts_with($c, '[')) {
                continue;
            }
            // Ignora chip de seleção de aluno e o pedido inicial só com nome.
            if (preg_match('/aluno_id\s*=|usar\s+aluno_id/i', $c) === 1) {
                continue;
            }
            if ($this->pedidoPareceRegistroEscrita($c)
                || preg_match('/(?:detalhe|descri[cç][aã]o|t[ií]tulo|categoria)/iu', $c) === 1
                || preg_match('/^(conversa|disciplina|atraso|bullying|uniforme|comportamento)\b/iu', $c) === 1
            ) {
                $textoHist .= "\n" . $c;
            }
        }

        if ($titulo === '' && preg_match('/t[ií]tulo\s*[:\-]\s*([^\n;]+)/iu', $textoHist, $mT) === 1) {
            $cand = trim($mT[1]);
            if ($this->textoOcorrenciaSignificativo($cand)) {
                $titulo = $cand;
            }
        }
        if ($detalhe === '' && preg_match('/(?:detalhe|descri[cç][aã]o)\s*[:\-]\s*([^\n;]+)/iu', $textoHist, $mD) === 1) {
            $cand = trim($mD[1]);
            if ($this->textoOcorrenciaSignificativo($cand)) {
                $detalhe = $cand;
            }
        }
        // "criar ocorrência do RA 24254: conversou na aula..." — texto após ":" é o fato.
        if ($detalhe === '' && preg_match(
            '/(?:\bra\s*[\w\-]+|ocorr[eê]ncia[^:\n]{0,80})\s*:\s*(.+)$/iu',
            $textoHist,
            $mColon
        ) === 1) {
            $cand = trim($mColon[1]);
            $cand = preg_replace('/\s*,\s*(?:categoria|gravidade|n[ií]vel).*$/iu', '', $cand) ?? $cand;
            $cand = trim($cand, " \t,;");
            if ($this->textoOcorrenciaSignificativo($cand)) {
                $detalhe = $cand;
                if ($titulo === '') {
                    $titulo = mb_substr($cand, 0, 80);
                }
            }
        }
        $nivelExplicito = !empty($args['nivel_gravidade_explicito'])
            || (!empty($ctx['ocorrencia_nivel']) && in_array((string) $ctx['ocorrencia_nivel'], ['leve', 'moderado', 'grave'], true));
        if ($nivel === '' && !empty($ctx['ocorrencia_nivel'])) {
            $nivel = (string) $ctx['ocorrencia_nivel'];
            $nivelExplicito = true;
        }
        // Só aceita gravidade do texto livre se vier rotulada (evita "queda grave" na descrição).
        if (preg_match('/(?:nivel(?:_gravidade)?|gravidade)\s*[:\-]?\s*(leve|moderado|m[eé]dio|grave)\b/iu', $textoHist, $mN) === 1) {
            $nivel = $this->normalizarNivelGravidade($mN[1]);
            $nivelExplicito = true;
        } elseif (preg_match('/^(leve|moderado|m[eé]dio|grave)$/iu', trim($mensagemUsuario), $mNSo) === 1) {
            $nivel = $this->normalizarNivelGravidade($mNSo[1]);
            $nivelExplicito = true;
        }
        // Só pega motivo explícito: "ocorrência por conversa" / "ocorrência: conversa"
        // NÃO captura "ocorrência para Ana Luiza".
        if ($titulo === '' && preg_match(
            '/ocorr[eê]ncia\s+(?:de|por|sobre|:\s*)\s*([A-Za-zÀ-ÿ0-9][^.]{2,80})/iu',
            $textoHist,
            $mOc
        ) === 1) {
            $cand = trim($mOc[1]);
            $cand = preg_replace('/\s+para\s+.+$/iu', '', $cand) ?? $cand;
            $cand = preg_replace('/\s+d[oa]\s+alun[oa]\s+.+$/iu', '', $cand) ?? $cand;
            if ($this->textoOcorrenciaSignificativo($cand)) {
                $titulo = $cand;
            }
        }
        if (preg_match(
            '/\b(?:por|sobre|motivo)\s+([A-Za-zÀ-ÿ0-9][^.,;]{2,80})/iu',
            $textoHist,
            $mMot
        ) === 1) {
            $cand = trim($mMot[1]);
            // Remove cauda de metadados ("..., categoria X, gravidade leve").
            $cand = preg_replace('/\s*,\s*(?:categoria|gravidade|nivel).*$/iu', '', $cand) ?? $cand;
            $cand = trim($cand);
            // "por Ana Luiza" não é motivo.
            $pareceNomeProprio = preg_match(
                '/^(?:a\s+|o\s+)?[A-ZÀ-Ÿ][a-zà-ÿ]+(?:\s+[A-ZÀ-Ÿ][a-zà-ÿ]+){0,3}$/u',
                $cand
            ) === 1;
            if ($this->textoOcorrenciaSignificativo($cand) && !$pareceNomeProprio) {
                if ($titulo === '') {
                    $titulo = $cand;
                }
                if ($detalhe === '') {
                    $detalhe = $cand;
                }
            }
        }
        // Não inventa gravidade: só preenche se o usuário disse leve/médio/grave.
        if ($nivel !== '' && !in_array($nivel, ['leve', 'moderado', 'grave'], true)) {
            $nivel = $this->normalizarNivelGravidade($nivel);
        }
        if ($nivel === '' || !in_array($nivel, ['leve', 'moderado', 'grave'], true)) {
            $nivel = '';
            $nivelExplicito = false;
        }

        $paisExplicito = !empty($args['enviar_pais_explicito']) || !empty($ctx['ocorrencia_pais_explicito']);
        $enviarPais = array_key_exists('enviar_pais', $args)
            ? !empty($args['enviar_pais'])
            : (array_key_exists('ocorrencia_pais', $ctx) ? (bool) $ctx['ocorrencia_pais'] : false);
        $textoNotificacao = $paisExplicito ? $mensagemUsuario : $textoHist;
        if (preg_match('/\b((?:n[aã]o|nunca)\s+(?:(?:quero|precisa|preciso|deve|pode|[eé]\s+(?:para|pra))\s+)?|sem\s+)?(?:notificar|avisar|enviar(?:\s+aviso)?)\s+(?:(?:os|aos|para\s+os)\s+)?(?:pais|respons[aá]veis)\b/iu', $textoNotificacao, $mPais) === 1) {
            $enviarPais = empty($mPais[1]);
            $paisExplicito = true;
        }

        $envolvidosOk = !empty($args['envolvidos_ok']) || !empty($ctx['ocorrencia_envolvidos_ok']);
        if ($this->pedidoPareceRegistroEscrita($mensagemUsuario)
            && preg_match('/\b(?:sem\s+(?:mais\s+|outros?\s+)?envolvidos|n[aã]o\s+h[aá]\s+(?:mais\s+|outros?\s+)?envolvidos|s[oó]\s+(?:essa\s+pessoa|ess[ae]\s+alun[oa]|el[ae]))\b/iu', $mensagemUsuario) === 1
        ) {
            $envolvidosOk = true;
        }
        // Só marca envolvidos_ok a partir da mensagem curta do usuário (não da descrição).
        $msgEnvolvidos = trim($mensagemUsuario);
        if (preg_match('/^(n[aã]o|nao)([,.\s].*)?$/iu', $msgEnvolvidos) === 1
            || preg_match('/\b(s[oó]\s+(ess[ae]|est[ae]|el[ae]|um)|nenhum\s+outro|somente\s+(ela|ele|ess[ae]))\b/iu', $msgEnvolvidos) === 1
            || preg_match('/\b(n[aã]o|nao)\b.{0,40}\b(mais\s+ninguem|outra\s+pessoa|outros?\s+alunos?)\b/iu', $msgEnvolvidos) === 1
        ) {
            // Só aplica se o passo atual (ou contexto) pede envolvidos — evita falso positivo na descrição.
            $campoAntes = $this->assistentePediuCampoOcorrencia($mensagens);
            if ($campoAntes === 'mais_envolvidos' || !empty($ctx['ocorrencia_envolvidos_ok'])) {
                $envolvidosOk = true;
            }
        }

        $args['nivel_gravidade'] = $nivel;
        $args['nivel_gravidade_explicito'] = $nivelExplicito;
        $args['enviar_pais'] = $enviarPais;
        $args['enviar_pais_explicito'] = $paisExplicito;
        $args['envolvidos_ok'] = $envolvidosOk;

        if (empty($args['categoria']) && empty($args['categoria_nome']) && !empty($ctx['ocorrencia_categoria'])) {
            $args['categoria'] = (string) $ctx['ocorrencia_categoria'];
        }

        if (empty($args['turma']) && !empty($ctx['turma_nome'])) {
            $args['turma'] = (string) $ctx['turma_nome'];
        }
        if (empty($args['ra']) && !empty($ctx['ra'])) {
            $args['ra'] = (string) $ctx['ra'];
        }

        // Campo pedido pela Tudinha na mensagem anterior (categoria / detalhe).
        $campoPedido = $this->assistentePediuCampoOcorrencia($mensagens);
        $msgLimpa = trim($mensagemUsuario);
        // Chip de aluno não é categoria/detalhe.
        if (preg_match('/aluno_id\s*=|usar\s+aluno_id|^alun[oa]\b/i', $msgLimpa) === 1) {
            $msgLimpa = '';
        }
        if (preg_match('/^(?:registrar\s+ocorr[eê]ncia\s+com\s+)?categoria\s+(.+)$/iu', $msgLimpa, $mCatDireta) === 1) {
            $args['categoria'] = trim($mCatDireta[1], " \t\"'.,;:");
        } elseif ($campoPedido === 'categoria' && $msgLimpa !== '' && !$this->pedidoPareceRegistroEscrita($msgLimpa)) {
            // Chip / frase: "Registrar ocorrência com categoria Comportamento / convivência"
            if (preg_match('/^(?:registrar\s+ocorr[eê]ncia\s+com\s+|usar\s+categoria\s+|categoria\s*[:\-]?\s*)(.+)$/iu', $msgLimpa, $mChipCat) === 1) {
                $args['categoria'] = trim($mChipCat[1], " \t\"'.,;:");
            } else {
                $args['categoria'] = $msgLimpa;
            }
            $catChip = trim((string) ($args['categoria'] ?? ''));
            if ($titulo === '' && $this->textoOcorrenciaSignificativo($catChip)) {
                $titulo = $catChip;
            }
        }
        if ($campoPedido === 'detalhe' && $msgLimpa !== '') {
            $detalhe = $msgLimpa;
        }
        if ($campoPedido === 'titulo' && $msgLimpa !== '') {
            $titulo = $msgLimpa;
        }
        if ($campoPedido === 'gravidade' && $msgLimpa !== '') {
            $nivel = $this->normalizarNivelGravidade($msgLimpa);
            if (in_array($nivel, ['leve', 'moderado', 'grave'], true)) {
                $args['nivel_gravidade'] = $nivel;
                $args['nivel_gravidade_explicito'] = true;
            }
        }
        if ($campoPedido === 'notificar_pais' && $msgLimpa !== '') {
            $resp = $this->interpretarSimNao($msgLimpa);
            if ($resp !== null) {
                $args['enviar_pais'] = $resp;
                $args['enviar_pais_explicito'] = true;
            }
        }
        // Restaura lista de envolvidos de turnos anteriores.
        if (!empty($ctx['ocorrencia_envolvidos'])) {
            $args['alunos'] = array_values(array_unique(array_merge(
                (array) ($args['alunos'] ?? []),
                $ctx['ocorrencia_envolvidos']
            )));
        }

        if ($campoPedido === 'mais_envolvidos' && $msgLimpa !== '') {
            $resp = $this->interpretarSimNao($msgLimpa);
            if ($resp === false) {
                $args['envolvidos_ok'] = true;
            } elseif ($resp === true || $this->textoOcorrenciaSignificativo($msgLimpa)) {
                // "sim, João Silva" ou só o nome → tenta acrescentar envolvido.
                $extra = $msgLimpa;
                $extra = preg_replace('/^(sim|yes|claro|pode)[,:\s]*/iu', '', $extra) ?? $extra;
                $extra = trim($extra);
                if ($extra !== '' && !preg_match('/^(sim|yes|claro|pode)$/iu', $extra)) {
                    $atuais = [];
                    if (!empty($args['alunos']) && is_array($args['alunos'])) {
                        $atuais = $args['alunos'];
                    } elseif (!empty($ctx['ocorrencia_envolvidos']) && is_array($ctx['ocorrencia_envolvidos'])) {
                        $atuais = $ctx['ocorrencia_envolvidos'];
                    } elseif (!empty($args['aluno_nome'])) {
                        $atuais = [(string) $args['aluno_nome']];
                    } elseif (!empty($ctx['aluno_nome'])) {
                        $atuais = [(string) $ctx['aluno_nome']];
                    }
                    $atuais[] = $extra;
                    $args['alunos'] = array_values(array_unique(array_filter(array_map('strval', $atuais))));
                }
                // Continua perguntando até o usuário dizer que não há mais ninguém.
                $args['envolvidos_ok'] = false;
                $args['pedir_mais_envolvidos'] = true;
            }
        }

        // Categoria pelo nome (nunca peça categoria_id ao usuário).
        if (empty($args['categoria']) && empty($args['categoria_nome']) && empty($args['categoria_id'])) {
            if (preg_match('/^(?:registrar\s+ocorr[eê]ncia\s+com\s+)?categoria\s+(.+)$/iu', $msgLimpa, $mChip) === 1) {
                $args['categoria'] = trim($mChip[1], " \t\"'.,;:");
            } elseif (preg_match('/categoria\s*[:\-]?\s*([A-Za-zÀ-ÿ0-9][\wÀ-ÿ\s\/\-]{1,80})/iu', $textoHist, $mCat) === 1) {
                $args['categoria'] = trim($mCat[1], " \t\"'.,;:");
            } elseif ($msgLimpa !== ''
                && preg_match('/^(conversa|disciplina|atraso|bullying|uniforme|comportamento)\b(.{0,60})$/iu', $msgLimpa, $mSo) === 1
            ) {
                $args['categoria'] = trim($mSo[0]);
                if ($titulo === '') {
                    $titulo = trim($mSo[0]);
                }
            } elseif ($titulo !== '' && $this->textoOcorrenciaSignificativo($titulo)) {
                $args['categoria'] = $titulo;
            }
        }

        // Recupera categoria digitada em turno anterior (só nomes tipicamente de categoria).
        if (empty($args['categoria']) && empty($args['categoria_nome'])) {
            foreach (array_reverse(array_slice($mensagens, -8)) as $msg) {
                if (($msg['role'] ?? '') !== 'user') {
                    continue;
                }
                $c = trim((string) ($msg['content'] ?? ''));
                if ($c === '' || str_starts_with($c, '[') || $this->pedidoPareceRegistroEscrita($c)) {
                    continue;
                }
                if (preg_match('/aluno_id\s*=|^alun[oa]\b|usar\s+aluno_id/i', $c) === 1) {
                    continue;
                }
                if (preg_match('/^(conversa|disciplina|atraso|bullying|uniforme|comportamento)\b.{0,60}$/iu', $c) === 1) {
                    $args['categoria'] = $c;
                    if ($titulo === '') {
                        $titulo = $c;
                    }
                    break;
                }
            }
        }

        // Detalhe longo livre — só se NÃO for o pedido inicial de "criar ocorrência…".
        if ($detalhe === '' && $msgLimpa !== '' && $campoPedido === null
            && mb_strlen($msgLimpa) >= 20
            && !$this->pedidoPareceRegistroEscrita($msgLimpa)
            && preg_match('/^(conversa|disciplina|atraso|bullying|uniforme|comportamento)\b/iu', $msgLimpa) !== 1
            && $this->textoOcorrenciaSignificativo($msgLimpa)
        ) {
            $detalhe = $msgLimpa;
        }

        $catAtual = trim((string) ($args['categoria'] ?? $args['categoria_nome'] ?? ''));
        // Só espelha titulo→detalhe quando o título é o fato (não o nome da categoria).
        if (
            $detalhe === ''
            && $this->textoOcorrenciaSignificativo($titulo)
            && !(
                $catAtual !== ''
                && mb_strtolower($this->semAcento($titulo)) === mb_strtolower($this->semAcento($catAtual))
            )
        ) {
            $detalhe = $titulo;
        }
        if (
            $titulo === ''
            || (
                $catAtual !== ''
                && mb_strtolower($this->semAcento($titulo)) === mb_strtolower($this->semAcento($catAtual))
                && $this->textoOcorrenciaSignificativo($detalhe)
            )
        ) {
            if ($this->textoOcorrenciaSignificativo($detalhe)) {
                $titulo = mb_substr($detalhe, 0, 80);
            }
        }
        $args['titulo'] = $titulo !== '' ? mb_substr($titulo, 0, 120) : '';
        $args['detalhe'] = $detalhe;

        return $args;
    }

    /** Título/descrição inventados a partir do pedido ("para Ana Luiza»") não contam. */
    private function textoOcorrenciaSignificativo(string $texto): bool
    {
        $t = trim($texto);
        if ($t === '' || mb_strlen($t) < 3) {
            return false;
        }
        // Placeholders comuns da LLM (ex.: detalhe "..." copiado do exemplo do prompt).
        if (preg_match('/^(\.{1,}|…+|-+|_+|n\/?a|null|undefined|placeholder|aguardando|sem descricao|a definir)$/iu', $t) === 1) {
            return false;
        }
        $n = mb_strtolower($this->semAcento($t));
        $n = trim($n, " \t«»\"'.,;:…-_");
        if ($n === '' || mb_strlen($n) < 3) {
            return false;
        }
        if ($n === 'ocorrencia via assistente' || $n === 'ocorrencia') {
            return false;
        }
        if (preg_match('/^(para|da|do|de)\s+/u', $n) === 1) {
            return false;
        }
        if (preg_match('/aluno_id\s*=|usar\s+aluno_id|^alun[oa]\b/i', $t) === 1) {
            return false;
        }
        if (preg_match('/retome o pedido original/iu', $t) === 1) {
            return false;
        }
        return true;
    }

    /** @param array<string,mixed> $args */
    private function ocorrenciaProntaParaProposta(array $args): bool
    {
        $titulo = trim((string) ($args['titulo'] ?? ''));
        $detalhe = trim((string) ($args['detalhe'] ?? ''));
        $cat = trim((string) ($args['categoria'] ?? $args['categoria_nome'] ?? ''));
        if ((int) ($args['categoria_id'] ?? 0) > 0) {
            $cat = 'ok';
        }
        $nivel = strtolower(trim((string) ($args['nivel_gravidade'] ?? '')));
        $nivelOk = !empty($args['nivel_gravidade_explicito'])
            && in_array($nivel, ['leve', 'moderado', 'grave'], true);
        $paisOk = !empty($args['enviar_pais_explicito']);
        $envolvidosOk = !empty($args['envolvidos_ok']);

        $temCategoria = $cat !== '';
        $temDetalhe = $this->textoOcorrenciaSignificativo($detalhe)
            && !(
                $cat !== ''
                && mb_strtolower($this->semAcento($detalhe)) === mb_strtolower($this->semAcento($cat))
            );
        if ($temCategoria && $titulo !== '' && $detalhe === $titulo && mb_strlen($detalhe) < 20
            && mb_strtolower($this->semAcento($detalhe)) === mb_strtolower($this->semAcento($cat))
        ) {
            $temDetalhe = false;
        }

        return $temCategoria && $temDetalhe && $nivelOk && $paisOk && $envolvidosOk;
    }

    /**
     * Conversa guiada até ter categoria, descrição, gravidade, envolvidos e notificação dos pais.
     *
     * @param array<string,mixed> $args
     * @param list<array{role?:string,content?:string}> $mensagens
     * @param callable|null $onStatus
     * @return array{tool?:?string,resultado?:?array,consulta?:array,texto?:string}
     */
    private function pedirCamposOcorrencia(array $args, string $mensagemUsuario, array $mensagens, $onStatus = null, ?array $categorias = null): array
    {
        $cat = trim((string) ($args['categoria'] ?? $args['categoria_nome'] ?? ''));
        $detalhe = trim((string) ($args['detalhe'] ?? ''));
        $nivel = strtolower(trim((string) ($args['nivel_gravidade'] ?? '')));
        $temCat = $cat !== '' || (int) ($args['categoria_id'] ?? 0) > 0;
        $temDetalhe = $this->textoOcorrenciaSignificativo($detalhe)
            && !($temCat && mb_strtolower($this->semAcento($detalhe)) === mb_strtolower($this->semAcento($cat)));
        $temNivel = !empty($args['nivel_gravidade_explicito'])
            && in_array($nivel, ['leve', 'moderado', 'grave'], true);
        $temPais = !empty($args['enviar_pais_explicito']);
        $temEnvolvidos = !empty($args['envolvidos_ok']);

        $aluno = null;
        if (!empty($args['aluno_id'])) {
            $aluno = [
                'id' => (int) $args['aluno_id'],
                'nome' => trim((string) ($args['aluno_nome'] ?? '')),
                'ra' => $args['ra'] ?? null,
                'turma_nome' => $args['turma'] ?? null,
            ];
        }
        $payloadBase = [
            'titulo' => trim((string) ($args['titulo'] ?? '')),
            'detalhe' => $detalhe,
            'categoria' => $cat,
            'nivel_gravidade' => $nivel,
            'enviar_pais' => !empty($args['enviar_pais']),
            'enviar_pais_explicito' => $temPais,
            'nivel_gravidade_explicito' => $temNivel,
            'envolvidos_ok' => $temEnvolvidos,
            'turma' => trim((string) ($args['turma'] ?? '')),
            'alunos' => $args['alunos'] ?? [],
        ];
        $nomeAluno = trim((string) ($aluno['nome'] ?? $args['aluno_nome'] ?? 'o aluno'));

        $baseResultado = static function (string $campo, string $aviso, array $extra = []) use ($aluno, $payloadBase): array {
            return array_merge([
                'ok' => false,
                'coleta_ocorrencia' => true,
                'aguardando_campo' => $campo,
                'aviso' => $aviso,
                'aluno' => $aluno,
                'payload' => $payloadBase,
            ], $extra);
        };

        if (!$temCat) {
            if ($onStatus !== null) {
                $onStatus('Buscando categorias…');
            }
            $lista = $categorias ?? $this->acoes->listarCategoriasOcorrencia();
            $texto = 'Vamos registrar a ocorrência de **' . $nomeAluno . '**. '
                . 'Qual a **categoria**? Você pode **digitar** (ex.: Conversa) ou escolher uma sugestão abaixo.';
            return [
                'tool' => 'propor_criar_ocorrencia',
                'resultado' => $baseResultado(
                    'categoria',
                    'Digite a categoria ou escolha uma sugestão:',
                    $lista !== [] ? ['candidatos_categorias' => $lista] : []
                ),
                'texto' => $texto,
            ];
        }

        if (!$temDetalhe) {
            return [
                'tool' => 'propor_criar_ocorrencia',
                'resultado' => $baseResultado('detalhe', 'Descreva o que aconteceu.'),
                'texto' => 'Categoria **' . $cat . '**. Agora descreva o que aconteceu (descrição da ocorrência).',
            ];
        }

        if (!$temNivel) {
            return [
                'tool' => 'propor_criar_ocorrencia',
                'resultado' => $baseResultado('gravidade', 'Qual a gravidade?', [
                    'opcoes_gravidade' => [
                        ['valor' => 'leve', 'rotulo' => 'Leve'],
                        ['valor' => 'moderado', 'rotulo' => 'Moderado'],
                        ['valor' => 'grave', 'rotulo' => 'Grave'],
                    ],
                ]),
                'texto' => 'Qual a **gravidade** da ocorrência: **leve**, **moderado** ou **grave**?',
            ];
        }

        if (!$temEnvolvidos || !empty($args['pedir_mais_envolvidos'])) {
            return [
                'tool' => 'propor_criar_ocorrencia',
                'resultado' => $baseResultado('mais_envolvidos', 'Há mais pessoas envolvidas?', [
                    'opcoes_sim_nao' => [
                        ['valor' => 'nao', 'rotulo' => 'Não, só essa pessoa'],
                        ['valor' => 'sim', 'rotulo' => 'Sim, tem mais'],
                    ],
                ]),
                'texto' => !empty($args['pedir_mais_envolvidos'])
                    ? 'Incluí. Tem **mais alguém** para vincular? Se sim, diga o nome; se não, responda **não**.'
                    : 'Além de **' . $nomeAluno . '**, tem **mais alguém** para vincular nesta ocorrência? '
                        . 'Responda **não** ou informe o nome.',
            ];
        }

        if (!$temPais) {
            return [
                'tool' => 'propor_criar_ocorrencia',
                'resultado' => $baseResultado('notificar_pais', 'Notificar os responsáveis?', [
                    'opcoes_sim_nao' => [
                        ['valor' => 'sim', 'rotulo' => 'Sim, notificar pais'],
                        ['valor' => 'nao', 'rotulo' => 'Não notificar'],
                    ],
                ]),
                'texto' => 'Deseja **notificar os responsáveis/pais** sobre esta ocorrência?',
            ];
        }

        return [
            'texto' => 'Não consegui preparar o rascunho da ocorrência. Tente novamente.',
        ];
    }

    private function normalizarNivelGravidade(string $texto): string
    {
        $n = mb_strtolower($this->semAcento(trim($texto)));
        if (preg_match('/\bgrave\b/u', $n) === 1) {
            return 'grave';
        }
        if (preg_match('/\b(moderado|medio)\b/u', $n) === 1) {
            return 'moderado';
        }
        if (preg_match('/\bleve\b/u', $n) === 1) {
            return 'leve';
        }
        return '';
    }

    /** @return bool|null true/false se interpretou; null se ambíguo */
    private function interpretarSimNao(string $texto): ?bool
    {
        $n = mb_strtolower($this->semAcento(trim($texto)));
        if (preg_match('/^(nao|no|n|negativo|nunca)\b/u', $n) === 1
            || preg_match('/\b(nao|sem)\b.{0,20}\b(notificar|avisar|pais|mais|outra|outro)\b/u', $n) === 1
            || preg_match('/\b(so|somente)\s+(ela|ele|essa|esse|um)\b/u', $n) === 1
        ) {
            return false;
        }
        if (preg_match('/^(sim|yes|s|claro|pode|quero)\b/u', $n) === 1
            || preg_match('/\b(notificar|avisar|enviar).{0,20}pais\b/u', $n) === 1
        ) {
            return true;
        }
        return null;
    }

    /**
     * @param list<array{role?:string,content?:string}> $mensagens
     */
    private function mensagemTrazCategoriaOuDetalhe(string $mensagem, array $mensagens): bool
    {
        $campo = $this->assistentePediuCampoOcorrencia($mensagens);
        if (in_array($campo, ['categoria', 'detalhe', 'titulo', 'gravidade', 'notificar_pais', 'mais_envolvidos'], true)) {
            return trim($mensagem) !== '';
        }
        $m = trim($mensagem);
        if (preg_match('/^(?:registrar\s+ocorr[eê]ncia\s+com\s+|usar\s+categoria\s+)?categoria\s+.+/iu', $m) === 1) {
            return true;
        }
        if (preg_match('/^(leve|moderado|m[eé]dio|grave|sim|n[aã]o)\b/iu', $m) === 1) {
            return true;
        }
        return preg_match('/^(conversa|disciplina|atraso|bullying|uniforme|comportamento)\b/iu', $m) === 1
            || preg_match('/(?:detalhe|descri[cç][aã]o|categoria|gravidade)\s*[:\-]?\s*\S/iu', $mensagem) === 1;
    }

    private function extrairNomeAlunoParaAcao(string $mensagem): ?string
    {
        if (preg_match('/\b(?:aluna|aluno)\s+([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\s]{1,60})/u', $mensagem, $m) === 1) {
            $nome = trim($m[1]);
            // Pronome ("essa aluna") não é nome.
            if (preg_match('/^(ess[ae]|est[ae]|el[ae])$/iu', $nome) === 1) {
                return null;
            }
            $nome = preg_replace('/\s+(?:da|do|de|no|na|para|com|por|sobre|RA)\b.*$/iu', '', $nome) ?? $nome;
            $nome = trim($nome);
            return $nome !== '' ? $nome : null;
        }
        if (preg_match('/\bpara\s+(?:a\s+|o\s+)?([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\s]{1,60})/u', $mensagem, $m2) === 1) {
            $nome = trim($m2[1]);
            if (preg_match('/^(ess[ae]|est[ae]|el[ae])$/iu', $nome) === 1) {
                return null;
            }
            $nome = preg_replace('/\s+(?:da|do|de|turma|RA|,).*$/iu', '', $nome) ?? $nome;
            $nome = trim($nome);
            return $nome !== '' ? $nome : null;
        }
        $ra = $this->extrairRaDaMensagem($mensagem);
        if ($ra !== null && $ra !== '') {
            return 'ra ' . $ra;
        }
        return null;
    }

    /**
     * @param array<string,mixed>|null $ultimoResultado
     */
    private function pedidoPareceBuscaAlunoAmbiguo(
        string $mensagemUsuario,
        ?string $ultimaTool,
        ?array $ultimoResultado
    ): bool {
        // Já tem chips.
        if (is_array($ultimoResultado) && !empty($ultimoResultado['candidatos']) && is_array($ultimoResultado['candidatos'])) {
            return false;
        }
        if ($ultimaTool === 'buscar_alunos' && !empty($ultimoResultado['alunos']) && count((array) $ultimoResultado['alunos']) > 1) {
            return false;
        }
        $t = trim($mensagemUsuario);
        if (preg_match('/\bRA\b|\d{3,}/u', $t) === 1) {
            return false;
        }
        // "Ana", "aluno Ana", "busca Ana", "encontre a aluna Ana"
        if (preg_match('/^(alun[oa]\s+)?[A-Za-zÀ-ÿ]{2,20}$/u', $t) === 1) {
            $soNome = mb_strtolower($this->semAcento(preg_replace('/^alun[oa]\s+/iu', '', $t) ?? $t));
            $stop = ['hoje', 'ontem', 'turma', 'boletim', 'nota', 'faltas', 'ocorrencia', 'sim', 'ok', 'pp', 'pu', 'mu'];
            return !in_array($soNome, $stop, true);
        }
        if (preg_match(
            '/\b(?:busca|busque|encontre|localiza|quem\s+[eé]|alun[oa])\s+(?:a\s+|o\s+)?([A-Za-zÀ-ÿ]{2,20})\b/iu',
            $t,
            $m
        ) === 1) {
            // Só primeiro nome (sem sobrenome na frase).
            return preg_match('/\b' . preg_quote($m[1], '/') . '\s+[A-Za-zÀ-ÿ]{2,}/u', $t) !== 1;
        }
        return false;
    }

    /**
     * @param callable|null $onStatus
     * @return array{tool?:?string,resultado?:?array,consulta?:array,texto?:string}
     */
    private function garantirCandidatosAluno(string $mensagemUsuario, $onStatus = null): array
    {
        $termo = trim($mensagemUsuario);
        if (preg_match(
            '/\b(?:busca|busque|encontre|localiza|alun[oa]|nota|boletim|faltas|ocorr)\s+(?:a\s+|o\s+)?([A-Za-zÀ-ÿ]{2,20})\b/iu',
            $termo,
            $m
        ) === 1) {
            $termo = $m[1];
        }
        $termo = preg_replace('/^(alun[oa]\s+)/iu', '', $termo) ?? $termo;
        $termo = trim($termo);
        if ($termo === '') {
            return [];
        }
        if ($onStatus !== null) {
            $onStatus('Buscando alunos…');
        }
        $resultado = $this->executarTool('buscar_alunos', ['termo' => $termo]);
        $alunos = is_array($resultado['alunos'] ?? null) ? $resultado['alunos'] : [];
        if (count($alunos) <= 1) {
            return [];
        }
        return [
            'tool' => 'buscar_alunos',
            'resultado' => $resultado + ['aviso' => 'Encontrei mais de um aluno. Qual deles?'],
            'consulta' => ['tool' => 'buscar_alunos', 'args' => ['termo' => $termo]],
            'texto' => 'Encontrei mais de um aluno. Qual deles?',
        ];
    }

    /** @param array<string,mixed>|null $resultado */
    private function boletimTemConteudoExibivel(?array $resultado): bool
    {
        if (!is_array($resultado) || empty($resultado['ok'])) {
            return false;
        }
        $quadro = is_array($resultado['quadro'] ?? null) ? $resultado['quadro'] : null;
        if (is_array($quadro) && $this->quadroTemNotaPreenchida($quadro)) {
            return true;
        }
        $eventos = is_array($resultado['eventos'] ?? null) ? $resultado['eventos'] : [];
        foreach ($eventos as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            if ($this->quadroTemNotaPreenchida([
                'colunas' => $ev['colunas'] ?? [],
                'linhas' => $ev['linhas'] ?? [],
            ])) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $quadro */
    private function quadroTemNotaPreenchida(array $quadro): bool
    {
        if (empty($quadro['colunas']) || empty($quadro['linhas']) || !is_array($quadro['linhas'])) {
            return false;
        }
        foreach ($quadro['linhas'] as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $notas = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
            foreach ($notas as $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }
                if (is_numeric($valor)) {
                    return true;
                }
                if (is_string($valor)) {
                    $v = trim($valor);
                    if ($v === '' || $v === '—' || $v === '-') {
                        continue;
                    }
                    if (preg_match('/^(reprovado|aprovado|recuperacao|recuperação)$/iu', $v) === 1) {
                        continue;
                    }
                    return true;
                }
                if (is_array($valor)) {
                    $inner = $valor['valor'] ?? $valor['nota'] ?? null;
                    if ($inner !== null && $inner !== '' && is_numeric($inner)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function artigoDe(string $nomeComTurma): string
    {
        // "da Júlia" / "do João" — heurística simples pelo primeiro nome.
        $primeiro = trim((string) preg_replace('/\s*\(.*$/', '', $nomeComTurma));
        $primeiro = trim(explode(' ', $primeiro)[0] ?? '');
        if ($primeiro === '') {
            return 'e';
        }
        // Nomes terminados em a (exceto alguns) → da
        if (preg_match('/a$/iu', $primeiro) && !preg_match('/^(Joshua|Josué|Tobias|Lucas|Nicolas|Matias)$/iu', $primeiro)) {
            return 'a';
        }
        return 'o';
    }

    private function extrairMateriaDaPergunta(string $pergunta): ?string
    {
        $p = mb_strtolower($this->semAcento($pergunta));
        // Preferência: "matemática 1", "lingua portuguesa 2" (número faz parte do nome no boletim).
        // Não capturar o dígito de "2º bimestre".
        if (preg_match(
            '/\b(matematica|portugues|lingua portuguesa|ciencias|historia|geografia|ingles|educacao fisica|artes?|biologia|fisica|quimica|filosofia|sociologia|redacao)\s*([12])\b(?!\s*[ºoa]?\s*bimestre)/u',
            $p,
            $m
        )) {
            return $this->rotuloMateriaCanonico($m[1]) . ' ' . $m[2];
        }
        // "nota de matemática", "em matemática", "matéria matemática"
        if (preg_match(
            '/\b(?:nota|media|média|desempenho|resultado|pp|pu|mu|ch|pe)?\s*(?:de|em|da|do)?\s*(matematica|portugues|lingua portuguesa|ciencias|historia|geografia|ingles|educacao fisica|artes?|biologia|fisica|quimica|filosofia|sociologia|redacao)\b/u',
            $p,
            $m
        )) {
            return $this->rotuloMateriaCanonico($m[1]);
        }
        return null;
    }

    private function rotuloMateriaCanonico(string $slug): string
    {
        $mapa = [
            'matematica' => 'Matemática',
            'portugues' => 'Português',
            'lingua portuguesa' => 'Língua Portuguesa',
            'ciencias' => 'Ciências',
            'historia' => 'História',
            'geografia' => 'Geografia',
            'ingles' => 'Inglês',
            'educacao fisica' => 'Educação Física',
            'arte' => 'Arte',
            'artes' => 'Artes',
            'biologia' => 'Biologia',
            'fisica' => 'Física',
            'quimica' => 'Química',
            'filosofia' => 'Filosofia',
            'sociologia' => 'Sociologia',
            'redacao' => 'Redação',
        ];
        return $mapa[$slug] ?? mb_convert_case($slug, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @param array{colunas?:list,linhas?:list} $quadro
     * @return array{materia:string,valor:string|null}|null
     */
    private function buscarNotaMateriaNoQuadro(array $quadro, string $materiaPedido, ?string $colunaPreferida = null): ?array
    {
        $alvo = $this->semAcento(mb_strtolower($materiaPedido));
        $linhas = is_array($quadro['linhas'] ?? null) ? $quadro['linhas'] : [];
        $exatas = [];
        $prefixos = [];
        $parciais = [];
        $colunas = is_array($quadro['colunas'] ?? null) ? $quadro['colunas'] : [];
        foreach ($linhas as $linha) {
            $nome = trim((string) ($linha['materia'] ?? $linha['materia_nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $norm = $this->semAcento(mb_strtolower($nome));
            if ($norm !== $alvo && !str_contains($norm, $alvo) && !str_contains($alvo, $norm)) {
                continue;
            }
            $notas = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
            $valor = $this->escolherValorNota($notas, $colunas, $colunaPreferida);
            $item = ['materia' => $nome, 'valor' => $valor];
            if ($norm === $alvo) {
                $exatas[] = $item;
                continue;
            }
            if (str_starts_with($norm, $alvo) || str_starts_with($alvo, $norm)) {
                $prefixos[] = $item;
                continue;
            }
            $parciais[] = $item;
        }
        if (count($exatas) === 1) {
            return $exatas[0];
        }
        if (count($exatas) > 1 || count($prefixos) > 1 || (count($exatas) === 0 && count($prefixos) === 0 && count($parciais) > 1)) {
            // Ambiguidade: não inventa a primeira linha.
            return null;
        }
        if (count($prefixos) === 1) {
            return $prefixos[0];
        }
        if (count($parciais) === 1) {
            return $parciais[0];
        }
        return null;
    }

    /**
     * @param array<string,mixed> $notas
     * @param list<array<string,mixed>> $colunas
     */
    private function escolherValorNota(array $notas, array $colunas, ?string $colunaPreferida = null): ?string
    {
        if ($notas === []) {
            return null;
        }
        $prefUser = $colunaPreferida !== null ? strtoupper(trim($colunaPreferida)) : '';
        if ($prefUser !== '') {
            foreach ($notas as $cod => $val) {
                $c = strtoupper((string) $cod);
                $nomeCol = '';
                foreach ($colunas as $col) {
                    if ((string) ($col['codigo'] ?? $col['chave'] ?? '') === (string) $cod) {
                        $nomeCol = strtoupper(trim((string) ($col['nome'] ?? $col['label'] ?? '')));
                        break;
                    }
                }
                if (
                    $c === $prefUser
                    || $nomeCol === $prefUser
                    || str_ends_with($c, '_' . $prefUser)
                    || str_starts_with($c, $prefUser . '_')
                ) {
                    if ($val === null || $val === '') {
                        return null;
                    }
                    return is_numeric($val)
                        ? (abs((float) $val - round((float) $val)) < 0.00001
                            ? (string) (int) round((float) $val)
                            : rtrim(rtrim(number_format((float) $val, 2, ',', ''), '0'), ','))
                        : (string) $val;
                }
            }
        }
        // Preferência: MU / média / última coluna com valor.
        $preferidas = ['MU', 'MEDIA', 'MÉDIA', 'MS', 'MA', 'RF', 'RESULTADO'];
        foreach ($preferidas as $pref) {
            foreach ($notas as $cod => $val) {
                $c = strtoupper((string) $cod);
                $nomeCol = '';
                foreach ($colunas as $col) {
                    if ((string) ($col['codigo'] ?? $col['chave'] ?? '') === (string) $cod) {
                        $nomeCol = strtoupper((string) ($col['nome'] ?? ''));
                        break;
                    }
                }
                if ($c === $pref || str_ends_with($c, '_' . $pref) || $nomeCol === $pref) {
                    $fmt = $this->formatarValorNota($val);
                    if ($fmt !== null) {
                        return $fmt;
                    }
                }
            }
        }
        $ultimo = null;
        foreach ($notas as $val) {
            $fmt = $this->formatarValorNota($val);
            if ($fmt !== null) {
                $ultimo = $fmt;
            }
        }
        return $ultimo;
    }

    /** @param mixed $val */
    private function formatarValorNota($val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }
        if (is_array($val)) {
            $val = $val['valor'] ?? $val['nota'] ?? $val['exibicao'] ?? null;
            if ($val === null || $val === '') {
                return null;
            }
        }
        if (is_float($val) || is_int($val)) {
            $n = (float) $val;
            return fmod($n, 1.0) === 0.0 ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
        }
        $s = trim((string) $val);
        return $s !== '' ? $s : null;
    }

    private function semAcento(string $texto): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u',
            'ç' => 'c',
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'É' => 'E', 'Ê' => 'E',
            'Í' => 'I',
            'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ];
        return strtr($texto, $map);
    }

    /**
     * Ranking de erros aceitando professor_nome e sem prova_id (lista candidatos).
     *
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    private function rankingErrosProvaComFiltros(array $args): array
    {
        $provaId = (int) ($args['prova_id'] ?? 0);
        $professorId = (int) ($args['professor_id'] ?? 0);
        $limite = (int) ($args['limite'] ?? 15);

        if ($professorId <= 0) {
            $resolvido = $this->professores->resolverProfessor($args);
            if (!empty($resolvido['candidatos'])) {
                return $resolvido;
            }
            if (empty($resolvido['ok'])) {
                return $resolvido;
            }
            $professorId = (int) ($resolvido['professor']['id'] ?? 0);
        }

        if ($provaId <= 0 && preg_match('/prova_id\s*[:=]?\s*(\d+)/i', (string) ($args['termo'] ?? ''), $m) === 1) {
            $provaId = (int) $m[1];
        }

        if ($provaId <= 0) {
            $lista = $this->professores->listarProvasProfessor([
                'professor_id' => $professorId,
                'limite' => 25,
            ]);
            if (empty($lista['ok'])) {
                return $lista;
            }
            $provas = is_array($lista['provas'] ?? null) ? $lista['provas'] : [];
            if ($provas === []) {
                return [
                    'ok' => false,
                    'error' => 'Este professor não tem provas para montar o ranking de erros.',
                    'professor' => $lista['professor'] ?? null,
                ];
            }
            if (count($provas) === 1) {
                $provaId = (int) ($provas[0]['prova_id'] ?? 0);
            } else {
                $cands = [];
                foreach ($provas as $p) {
                    $cands[] = [
                        'prova_id' => (int) ($p['prova_id'] ?? 0),
                        'titulo' => trim((string) ($p['titulo'] ?? '')),
                        'materia' => trim((string) ($p['materia']['nome'] ?? '')),
                        'erros' => $p['erros'] ?? null,
                        'turma' => trim((string) ($p['turma']['nome'] ?? '')),
                    ];
                }
                return [
                    'ok' => false,
                    'aviso' => 'Há mais de uma prova. Qual delas para o ranking de erros?',
                    'candidatos_provas' => $cands,
                    'professor' => $lista['professor'] ?? null,
                ];
            }
        }

        return $this->professores->rankingErrosProva($professorId, $provaId, $limite);
    }

    private function pedidoPareceJornadasAluno(string $pergunta): bool
    {
        return (bool) preg_match('/\bjornadas?\b/iu', $pergunta);
    }

    /**
     * @param list<array{role?:string,content?:string}> $mensagens
     * @param callable|null $onStatus
     * @return array{tool?:?string,resultado?:?array,consulta?:array,texto?:string}
     */
    private function garantirJornadasAluno(
        string $mensagemUsuario,
        array $mensagens,
        ?string $ultimaTool,
        ?array $ultimoResultado,
        $onStatus = null
    ): array {
        $toolsOk = ['resumo_jornadas_aluno', 'listar_jornadas_aluno'];
        if (
            in_array($ultimaTool, $toolsOk, true)
            && is_array($ultimoResultado)
            && (!empty($ultimoResultado['ok']) || !empty($ultimoResultado['candidatos']))
        ) {
            if (!empty($ultimoResultado['candidatos'])) {
                return [
                    'tool' => $ultimaTool,
                    'resultado' => $ultimoResultado,
                    'texto' => (string) ($ultimoResultado['aviso'] ?? 'Há mais de um aluno. Confirme pelo nome:'),
                ];
            }
            return ['tool' => $ultimaTool, 'resultado' => $ultimoResultado];
        }

        $args = [];
        $alunoId = (int) ($ultimoResultado['aluno']['id'] ?? 0);
        if ($alunoId <= 0) {
            $ctx = $this->extrairContextoProvasDoHistorico($mensagens);
            $alunoId = (int) ($ctx['aluno_id'] ?? 0);
        }
        if ($alunoId > 0) {
            $args['aluno_id'] = $alunoId;
        }
        $ra = null;
        if (method_exists($this->consulta, 'extrairCodigoRa')) {
            $ra = $this->consulta->extrairCodigoRa($mensagemUsuario);
        }
        if ($ra === null) {
            $ra = method_exists($this->consulta, 'extrairCodigoRa')
                ? $this->consulta->extrairCodigoRa($this->ultimoPedidoUsuario($mensagens) ?: '')
                : null;
        }
        if (($ra === null || $ra === '') && !empty($ultimoResultado['aluno']['ra'])) {
            $ra = (string) $ultimoResultado['aluno']['ra'];
        }
        if ($ra !== null && $ra !== '' && empty($args['aluno_id'])) {
            $args['ra'] = (string) $ra;
        }
        if (empty($args['aluno_id']) && empty($args['ra']) && !empty($ultimoResultado['aluno']['nome'])) {
            $args['aluno_nome'] = trim((string) $ultimoResultado['aluno']['nome']);
        }
        if (empty($args['aluno_id']) && empty($args['ra']) && empty($args['aluno_nome'])) {
            return [
                'texto' => 'Para consultar jornadas, informe o RA ou o nome do aluno.',
            ];
        }

        if ($onStatus !== null) {
            $onStatus('Consultando jornadas…');
        }
        $resultado = $this->jornadas->resumoJornadasAluno($args);
        $texto = null;
        if (!empty($resultado['candidatos'])) {
            $texto = (string) ($resultado['aviso'] ?? 'Há mais de um aluno. Confirme pelo nome:');
        } elseif (!empty($resultado['ok'])) {
            $total = (int) ($resultado['total_jornadas'] ?? 0);
            $nome = trim((string) ($resultado['aluno']['nome'] ?? 'aluno'));
            $texto = 'Jornadas d' . $this->artigoDe($nome) . ' **' . $nome . '**: **' . $total . '** no total.';
        } else {
            $texto = (string) ($resultado['error'] ?? 'Não encontrei jornadas para este aluno.');
        }

        return [
            'tool' => 'resumo_jornadas_aluno',
            'resultado' => $resultado,
            'consulta' => ['tool' => 'resumo_jornadas_aluno', 'args' => $args],
            'texto' => $texto,
        ];
    }

    private function pedidoPareceGradeTurma(string $pergunta): bool
    {
        $p = mb_strtolower($this->semAcento($pergunta));
        if (preg_match('/\b(grade|horario|agenda)\b/u', $p) !== 1) {
            return false;
        }
        if (preg_match('/\bprofessor/u', $p) === 1) {
            return false;
        }
        return preg_match('/\bturma\b/u', $p) === 1
            || preg_match('/\b(grade|horario)\s+(da|do|de)\s+/u', $p) === 1
            || preg_match('/\b(?:dessa|desta|mesma)\s+turma\b/u', $p) === 1;
    }

    private function extrairNomeTurma(string $mensagem): ?string
    {
        // "dessa mesma turma" / "mesma turma" — sem nome literal.
        if (preg_match('/\b(?:dessa|desta|mesma)\s+turma\b/iu', $mensagem) === 1
            && preg_match('/\bturma\s+[A-Za-zÀ-ÿ0-9ªº°]/iu', $mensagem) !== 1
        ) {
            return null;
        }
        if (preg_match('/\bturma\s+([A-Za-zÀ-ÿ0-9ªº°][A-Za-zÀ-ÿ0-9ªº°\s.\-]{0,40})/iu', $mensagem, $m) === 1) {
            $nome = trim($m[1]);
            if (preg_match('/^(dessa|desta|mesma)$/iu', $nome) === 1) {
                return null;
            }
            $nome = preg_replace('/\s+(?:hoje|amanh[aã]|ontem|em|para|de|do|da)\b.*$/iu', '', $nome) ?? $nome;
            $nome = trim($nome, " \t,.");
            return $nome !== '' ? $nome : null;
        }
        if (preg_match('/\b(?:grade|hor[aá]rio|agenda)\s+(?:da|do|de)\s+([A-Za-zÀ-ÿ0-9ªº°][A-Za-zÀ-ÿ0-9ªº°\s.\-]{1,40})/iu', $mensagem, $m2) === 1) {
            $nome = trim($m2[1]);
            $nome = preg_replace('/\s+(?:hoje|amanh[aã]|ontem|em|para)\b.*$/iu', '', $nome) ?? $nome;
            return trim($nome) !== '' ? trim($nome) : null;
        }
        return null;
    }

    /**
     * @param list<array{role?:string,content?:string}> $mensagens
     * @param callable|null $onStatus
     * @return array{tool?:?string,resultado?:?array,consulta?:array,texto?:string}
     */
    private function garantirGradeTurma(
        string $mensagemUsuario,
        array $mensagens,
        ?string $ultimaTool,
        ?array $ultimoResultado,
        $onStatus = null
    ): array {
        if (
            $ultimaTool === 'grade_horaria_consulta'
            && is_array($ultimoResultado)
            && (!empty($ultimoResultado['ok']) || !empty($ultimoResultado['candidatos']))
            && (($ultimoResultado['escopo']['tipo'] ?? '') === 'turma' || !empty($ultimoResultado['candidatos']))
        ) {
            $texto = !empty($ultimoResultado['ok'])
                ? $this->montarRespostaDireta($mensagemUsuario, 'grade_horaria_consulta', $ultimoResultado)
                : (string) ($ultimoResultado['aviso'] ?? 'Há mais de uma turma. Confirme pelo nome:');
            return [
                'tool' => $ultimaTool,
                'resultado' => $ultimoResultado,
                'texto' => $texto,
            ];
        }

        $args = [];
        $ctx = $this->extrairContextoConsultaDoHistorico($mensagens);
        $turma = $this->extrairNomeTurma($mensagemUsuario);
        if ($turma === null) {
            $turma = $this->extrairNomeTurma($this->ultimoPedidoUsuario($mensagens) ?: '');
        }
        if (
            $turma === null
            && preg_match('/\b(?:dessa|desta|mesma)\s+turma\b/iu', $mensagemUsuario) === 1
            && !empty($ctx['turma_nome'])
        ) {
            $turma = (string) $ctx['turma_nome'];
        }
        if ($turma === null && !empty($ctx['turma_nome']) && preg_match('/\b(grade|horario|agenda)\b/iu', $mensagemUsuario) === 1) {
            // Follow-up só com data nova ("e amanhã?") após grade da turma.
            $turma = (string) $ctx['turma_nome'];
        }
        if ($turma !== null) {
            $args['turma_nome'] = $turma;
        }
        $args['data'] = $this->extrairDataDoPedido($mensagemUsuario) ?? $this->ampliada->hojeLocal();

        if (empty($args['turma_nome'])) {
            return [
                'texto' => 'Para a grade da turma, informe o nome (ex.: grade da turma 2ª B hoje).',
            ];
        }

        if ($onStatus !== null) {
            $onStatus('Consultando grade da turma…');
        }
        $args = $this->aplicarFiltrosDoPedido('grade_horaria_consulta', $args, $mensagemUsuario);
        $resultado = $this->ampliada->gradeHorariaConsulta($args);
        $texto = null;
        if (!empty($resultado['candidatos'])) {
            $texto = (string) ($resultado['aviso'] ?? 'Há mais de uma turma. Confirme pelo nome:');
        } elseif (!empty($resultado['ok'])) {
            $texto = $this->montarRespostaDireta($mensagemUsuario, 'grade_horaria_consulta', $resultado);
        } else {
            $texto = (string) ($resultado['error'] ?? 'Não encontrei a grade desta turma.');
        }

        return [
            'tool' => 'grade_horaria_consulta',
            'resultado' => $resultado,
            'consulta' => ['tool' => 'grade_horaria_consulta', 'args' => $args],
            'texto' => $texto,
        ];
    }

    /** @param list<array{role?:string,content?:string}> $historico */
    private function pedidoPareceGradeProfessor(string $pergunta, array $historico = []): bool
    {
        $p = mb_strtolower($this->semAcento($pergunta));
        if (preg_match('/\b(grade|horario|agenda)\b/u', $p) !== 1) {
            return false;
        }
        if (preg_match('/\bprofessor[ae]?\b/u', $p) === 1) {
            return true;
        }
        // Continuação após grade de professor no histórico.
        foreach (array_reverse(array_slice($historico, -6)) as $msg) {
            $c = mb_strtolower($this->semAcento((string) ($msg['content'] ?? '')));
            if (preg_match('/\bgrade\b.*\bprofessor|\bprofessor\b.*\bgrade/u', $c) === 1) {
                return preg_match('/\b(hoje|amanha|ontem|\d{2}\/\d{2}|\d{4}-\d{2}-\d{2})\b/u', $p) === 1;
            }
        }
        return false;
    }

    /** @param list<array{role?:string,content?:string}> $historico */
    private function pedidoPareceRankingErros(string $pergunta, array $historico = []): bool
    {
        $p = mb_strtolower($this->semAcento($pergunta));
        // Evita sequestrar "questões mais erradas do aluno" / erros da turma.
        if (preg_match('/\b(alun[oa]|turma)\b/u', $p) === 1 && preg_match('/\bprofessor/u', $p) !== 1) {
            if (preg_match('/ranking de erros da prova_id\s+\d+/iu', $pergunta) !== 1) {
                return false;
            }
        }
        if (preg_match('/\branking\b.*\berros?\b|\berros?\b.*\branking\b|\bquestoes?\s+mais\s+errad/u', $p) === 1) {
            return true;
        }
        if (preg_match('/ranking de erros da prova_id\s+\d+/iu', $pergunta) === 1) {
            return true;
        }
        if (preg_match('/\bprova_id\s+\d+/iu', $pergunta) === 1) {
            foreach (array_reverse(array_slice($historico, -6)) as $msg) {
                $c = mb_strtolower($this->semAcento((string) ($msg['content'] ?? '')));
                if (preg_match('/\branking\b.*\berro|\berros?\b.*\branking/u', $c) === 1) {
                    return true;
                }
            }
        }
        return false;
    }

    private function textoPareceAdiamento(string $texto): bool
    {
        $t = mb_strtolower(trim($this->semAcento($texto)));
        if ($t === '') {
            return true;
        }
        return (bool) preg_match(
            '/^(um momento|aguarde|so um momento|ja retorno|ja ja|em instantes|processando|consultando)([.!,…\s]|$)/u',
            $t
        );
    }

    private function formatarDataBr(string $ymd): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m) === 1) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }
        return $ymd;
    }

    private function extrairNomeProfessor(string $mensagem): ?string
    {
        if (preg_match('/\bprofessor[ae]?\s+([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\s.]{1,60})/iu', $mensagem, $m) === 1) {
            $nome = trim($m[1]);
            // Só corta em palavras-chave de data/contexto — não em "de"/"da" do meio do nome.
            $nome = preg_replace('/\s+(?:hoje|amanh[aã]|ontem|em|para|turma|prova)\b.*$/iu', '', $nome) ?? $nome;
            $nome = trim($nome, " \t\n\r\0\x0B,.");
            return $nome !== '' ? $nome : null;
        }
        return null;
    }

    /**
     * @param list<array{role?:string,content?:string}> $mensagens
     * @param callable|null $onStatus
     * @return array{tool?:?string,resultado?:?array,consulta?:array,texto?:string}
     */
    private function garantirGradeProfessor(
        string $mensagemUsuario,
        array $mensagens,
        ?string $ultimaTool,
        ?array $ultimoResultado,
        $onStatus = null
    ): array {
        if (
            $ultimaTool === 'grade_horaria_consulta'
            && is_array($ultimoResultado)
            && (!empty($ultimoResultado['ok']) || !empty($ultimoResultado['candidatos']))
        ) {
            $texto = !empty($ultimoResultado['ok'])
                ? $this->montarRespostaDireta($mensagemUsuario, 'grade_horaria_consulta', $ultimoResultado)
                : (string) ($ultimoResultado['aviso'] ?? 'Há mais de um professor. Escolha um:');
            return [
                'tool' => $ultimaTool,
                'resultado' => $ultimoResultado,
                'texto' => $texto,
            ];
        }

        $args = [];
        if (preg_match('/professor_id\s*=\s*(\d+)/i', $mensagemUsuario, $mId) === 1) {
            $args['professor_id'] = (int) $mId[1];
        }
        $nome = $this->extrairNomeProfessor($mensagemUsuario);
        if ($nome === null) {
            $nome = $this->extrairNomeProfessor($this->ultimoPedidoUsuario($mensagens) ?: '');
        }
        if ($nome !== null) {
            $args['professor_nome'] = $nome;
        }
        $args['data'] = $this->extrairDataDoPedido($mensagemUsuario) ?? $this->ampliada->hojeLocal();

        if (empty($args['professor_id']) && empty($args['professor_nome'])) {
            return [
                'texto' => 'Para montar a grade do professor, informe o nome (ex.: grade do professor Ana hoje).',
            ];
        }

        if ($onStatus !== null) {
            $onStatus('Consultando grade do professor…');
        }
        $resultado = $this->ampliada->gradeHorariaConsulta($args);
        $texto = null;
        if (!empty($resultado['candidatos'])) {
            $texto = (string) ($resultado['aviso'] ?? 'Há mais de um professor. Escolha um:');
        } elseif (!empty($resultado['ok'])) {
            $texto = $this->montarRespostaDireta($mensagemUsuario, 'grade_horaria_consulta', $resultado);
        } else {
            $texto = (string) ($resultado['error'] ?? 'Não encontrei a grade deste professor.');
        }

        return [
            'tool' => 'grade_horaria_consulta',
            'resultado' => $resultado,
            'consulta' => ['tool' => 'grade_horaria_consulta', 'args' => $args],
            'texto' => $texto,
        ];
    }

    /**
     * @param list<array{role?:string,content?:string}> $mensagens
     * @param callable|null $onStatus
     * @return array{tool?:?string,resultado?:?array,consulta?:array,texto?:string}
     */
    private function garantirRankingErros(
        string $mensagemUsuario,
        array $mensagens,
        ?string $ultimaTool,
        ?array $ultimoResultado,
        $onStatus = null
    ): array {
        if (
            $ultimaTool === 'ranking_erros_prova_professor'
            && is_array($ultimoResultado)
            && (
                !empty($ultimoResultado['ok'])
                || !empty($ultimoResultado['candidatos'])
                || !empty($ultimoResultado['candidatos_provas'])
            )
        ) {
            $texto = null;
            if (!empty($ultimoResultado['ok'])) {
                $texto = $this->montarRespostaDireta($mensagemUsuario, 'ranking_erros_prova_professor', $ultimoResultado);
            } elseif (!empty($ultimoResultado['candidatos_provas'])) {
                $texto = (string) ($ultimoResultado['aviso'] ?? 'Há mais de uma prova. Qual delas?');
            } else {
                $texto = (string) ($ultimoResultado['aviso'] ?? 'Há mais de um professor. Escolha um:');
            }
            return [
                'tool' => $ultimaTool,
                'resultado' => $ultimoResultado,
                'texto' => $texto,
            ];
        }

        $args = [];
        if (preg_match('/professor_id\s*=\s*(\d+)/i', $mensagemUsuario, $mPid) === 1) {
            $args['professor_id'] = (int) $mPid[1];
        }
        if (preg_match('/prova_id\s*[:=]?\s*(\d+)/i', $mensagemUsuario, $mPr) === 1) {
            $args['prova_id'] = (int) $mPr[1];
        }
        $nome = $this->extrairNomeProfessor($mensagemUsuario);
        if ($nome === null) {
            $nome = $this->extrairNomeProfessor($this->ultimoPedidoUsuario($mensagens) ?: '');
        }
        if ($nome !== null) {
            $args['professor_nome'] = $nome;
        }
        // Contexto: última listagem/resumo de professor.
        if (empty($args['professor_id']) && is_array($ultimoResultado['professor'] ?? null)) {
            $args['professor_id'] = (int) ($ultimoResultado['professor']['id'] ?? 0);
        }
        if (empty($args['prova_id']) && !empty($ultimoResultado['prova']['prova_id'])) {
            $args['prova_id'] = (int) $ultimoResultado['prova']['prova_id'];
        }

        if (empty($args['professor_id']) && empty($args['professor_nome']) && empty($args['prova_id'])) {
            // Não sobrescreve resposta válida de outra tool (ex.: erros do aluno/turma).
            if (
                $ultimaTool
                && $ultimaTool !== 'ranking_erros_prova_professor'
                && is_array($ultimoResultado)
                && !empty($ultimoResultado['ok'])
            ) {
                return [];
            }
            return [
                'texto' => 'Para o ranking de erros, informe o professor (e a prova, se souber).',
            ];
        }

        // Troca de professor: não reaproveita prova do contexto anterior.
        if (!empty($args['professor_id']) || !empty($args['professor_nome'])) {
            $profCtxId = (int) ($ultimoResultado['professor']['id'] ?? 0);
            if (
                !empty($args['prova_id'])
                && $profCtxId > 0
                && !empty($args['professor_id'])
                && (int) $args['professor_id'] !== $profCtxId
            ) {
                unset($args['prova_id']);
            }
        }

        if ($onStatus !== null) {
            $onStatus('Montando ranking de erros…');
        }
        $resultado = $this->rankingErrosProvaComFiltros($args);
        $texto = null;
        if (!empty($resultado['candidatos_provas'])) {
            $texto = (string) ($resultado['aviso'] ?? 'Há mais de uma prova. Qual delas?');
        } elseif (!empty($resultado['candidatos'])) {
            $texto = (string) ($resultado['aviso'] ?? 'Há mais de um professor. Escolha um:');
        } elseif (!empty($resultado['ok'])) {
            $texto = $this->montarRespostaDireta($mensagemUsuario, 'ranking_erros_prova_professor', $resultado);
        } else {
            $texto = (string) ($resultado['error'] ?? 'Não consegui montar o ranking de erros.');
        }

        return [
            'tool' => 'ranking_erros_prova_professor',
            'resultado' => $resultado,
            'consulta' => ['tool' => 'ranking_erros_prova_professor', 'args' => $args],
            'texto' => $texto,
        ];
    }

    /** @param array<string,mixed> $args */
    private function rotuloStatus(string $tool, array $args): string
    {
        switch ($tool) {
            case 'buscar_alunos':
                return 'Buscando aluno…';
            case 'listar_provas_aluno':
                return 'Consultando provas…';
            case 'detalhar_prova_aluno':
                return 'Detalhando prova…';
            case 'resumo_provas_aluno':
                return 'Montando consolidado de provas…';
            case 'listar_jornadas_aluno':
                return 'Consultando jornadas…';
            case 'detalhar_jornada_aluno':
                return 'Detalhando jornada…';
            case 'resumo_jornadas_aluno':
                return 'Montando consolidado de jornadas…';
            case 'buscar_professores':
                return 'Buscando professor…';
            case 'listar_turmas_professor':
                return 'Listando turmas do professor…';
            case 'listar_provas_professor':
                return 'Consultando provas do professor…';
            case 'resumo_provas_professor':
                return 'Montando consolidado do professor…';
            case 'detalhar_prova_professor':
                return 'Detalhando prova do professor…';
            case 'ranking_erros_prova_professor':
                return 'Ranking de erros da prova…';
            case 'saude_turmas_professor':
                return 'Analisando saúde das turmas…';
            case 'saude_turma':
                return 'Analisando saúde da turma…';
            case 'resumo_provas_turma':
                return 'Montando consolidado da turma…';
            case 'buscar_blocos':
                return 'Buscando blocos de prova…';
            case 'resultados_bloco':
                return 'Consultando resultados do bloco…';
            case 'resumo_jornadas_professor':
                return 'Montando jornadas do professor…';
            case 'saude_academica':
                return 'Analisando saúde acadêmica…';
            case 'perfil_aluno_360':
                return 'Montando perfil do aluno…';
            case 'diario_pendencias':
                return 'Consultando pendências do diário…';
            case 'grade_horaria_consulta':
                return 'Consultando grade horária…';
            case 'boletim_aluno':
                return 'Montando boletim…';
            case 'notas_evento_aluno':
                return 'Consultando notas do evento…';
            case 'nota_evento_sigla':
                return 'Consultando nota no evento avaliativo…';
            case 'listar_eventos_avaliativos':
                return 'Listando eventos avaliativos…';
            case 'faltas_aluno':
                return 'Consultando frequência…';
            case 'ocorrencias_turma':
            case 'ocorrencias_aluno':
                return 'Consultando ocorrências…';
            case 'listar_materias':
            case 'listar_materias_jornadas':
                return 'Listando matérias…';
            case 'listar_tipos_avaliacao':
                return 'Listando tipos de avaliação…';
            default:
                return 'Consultando dados…';
        }
    }

    private function emitirEmPedacos(string $texto, callable $onTextoChunk): void
    {
        $len = mb_strlen($texto);
        $i = 0;
        while ($i < $len) {
            $pedaco = mb_substr($texto, $i, 48);
            $onTextoChunk($pedaco);
            $i += 48;
        }
    }

    private function assertCreditosDisponiveis(): void
    {
        if (self::LIBERAR_SEM_TUDICOINS || !CreditosModuleRegistry::isValid(self::MODULO_CREDITOS)) {
            return;
        }
        $creditos = new \App\Services\CreditosService();
        if (!$creditos->isCreditosHabilitado()) {
            throw new \Exception('TudiCoins desabilitado para esta escola. Ações com IA não estão disponíveis.');
        }
        if (!$creditos->podeConsumir(
            'escola',
            \App\Core\CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID,
            self::MODULO_CREDITOS
        )) {
            throw new \Exception('TudiCoins insuficientes na carteira da escola.');
        }
    }

    private function debitarCreditos(): void
    {
        if (self::LIBERAR_SEM_TUDICOINS || !CreditosModuleRegistry::isValid(self::MODULO_CREDITOS)) {
            return;
        }
        $creditos = new \App\Services\CreditosService();
        if (!$creditos->isCreditosHabilitado()) {
            throw new \Exception('TudiCoins desabilitado para esta escola. Ações com IA não estão disponíveis.');
        }
        $creditos->consumirEscola(self::MODULO_CREDITOS, 'provas_aluno_assistente');
    }
}

if (!class_exists('ProvasAlunoAssistenteService', false) && class_exists(__NAMESPACE__ . '\\ProvasAlunoAssistenteService', false)) {
    class_alias(__NAMESPACE__ . '\\ProvasAlunoAssistenteService', 'ProvasAlunoAssistenteService');
}
