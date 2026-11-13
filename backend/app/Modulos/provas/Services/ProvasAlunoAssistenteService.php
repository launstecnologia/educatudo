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
    /** Liberação temporária: permite usar o Assistente sem TudiCoins ligado na escola. */
    public const LIBERAR_SEM_TUDICOINS = true;
    public const DELIMITADOR_INICIO = '<<<CONSULTA>>>';
    public const DELIMITADOR_FIM = '<<<FIM>>>';
    private const MAX_RODADAS = 6;

    private ProvasAlunoConsultaService $consulta;
    private JornadasAlunoConsultaService $jornadas;
    private ProvasProfessorConsultaService $professores;
    private AssistenteConsultaAmpliadaService $ampliada;
    private AssistenteAcaoPropostaService $acoes;
    /** @var \App\Services\OpenAIService */
    private $openai;
    private int $usuarioIdContexto = 0;
    private int $conversaIdContexto = 0;

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
    public function definirContextoAcao(int $usuarioId, int $conversaId = 0): void
    {
        $this->usuarioIdContexto = max(0, $usuarioId);
        $this->conversaIdContexto = max(0, $conversaId);
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
     * @param list<array{role:string,content:string}> $historico
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

        $system = $this->montarSystemPrompt();
        $consultasFeitas = [];
        $ultimoResultado = null;
        $ultimaTool = null;
        $textoFinal = null;

        try {
            for ($rodada = 0; $rodada < self::MAX_RODADAS; $rodada++) {
                $raw = $this->openai->chatCompletion($mensagens, $system, 'gpt-4o-mini', 0.25, 2800);
                $resposta = trim((string) ($raw['resposta'] ?? ''));
                $pedido = $this->extrairConsulta($resposta);

                if ($pedido === null) {
                    $textoFinal = $this->limparRespostaFinal($resposta);
                    if ($textoFinal === '') {
                        $textoFinal = 'Não encontrei dados suficientes. Informe o nome do aluno e a turma.';
                    }
                    break;
                }

                $tool = (string) ($pedido['tool'] ?? '');
                $args = is_array($pedido['args'] ?? null) ? $pedido['args'] : [];
                if ($onStatus !== null) {
                    $onStatus($this->rotuloStatus($tool, $args));
                }

                $resultadoTool = $this->executarTool($tool, $args);
                $consultasFeitas[] = ['tool' => $tool, 'args' => $args];
                $ultimoResultado = $resultadoTool;
                $ultimaTool = $tool;

                $mensagens[] = ['role' => 'assistant', 'content' => $resposta];
                $mensagens[] = [
                    'role' => 'user',
                    'content' => "Resultado da consulta ({$tool}):\n"
                        . json_encode($resultadoTool, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        . "\n\nPedido original do usuário: «{$mensagemUsuario}»\n"
                        . "Use esses dados (não invente). Se precisar de outra consulta, emita <<<CONSULTA>>> de novo. "
                        . 'Se o usuário pediu questões erradas/detalhe e você ainda NÃO chamou detalhar_prova_aluno (com prova_id do contexto e somente_erros=true), chame agora. '
                        . 'Caso contrário, responda de forma ASSERTIVA: 1–2 frases respondendo EXATAMENTE o pedido, com o valor em **negrito**. '
                        . 'Ex.: "A nota de Matemática da Júlia Martiz (2ª B) é **10**." '
                        . 'A UI monta tabelas/PDF — não diga que não consegue se o resultado trouxe dados.',
                ];
            }
        } catch (\Throwable $e) {
            error_log('ProvasAlunoAssistenteService: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Falha ao consultar a IA. Tente novamente.'];
        }

        if ($textoFinal === null) {
            $textoFinal = 'Consultei os dados. Escolha: provas semanais, bimestrais, um bimestre específico ou consolidado geral.';
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
            in_array($ultimaTool, ['propor_criar_ocorrencia', 'propor_entrada_saida', 'propor_chamada_aula'], true)
            && is_array($ultimoResultado)
            && !empty($ultimoResultado['ok'])
            && !empty($ultimoResultado['proposta_id'])
        ) {
            $textoFinal = 'Preparei uma proposta. Revise abaixo e confirme se estiver de acordo.';
        }

        // Conferência server-side por RA/nome: a IA não pode inventar "não encontrei".
        $identidade = $this->garantirIdentidadeAluno(
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

        try {
            $this->debitarCreditos();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $painel = $this->montarPainel($ultimaTool, $ultimoResultado);
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
                return $this->professores->rankingErrosProva(
                    (int) ($args['professor_id'] ?? 0),
                    (int) ($args['prova_id'] ?? 0),
                    (int) ($args['limite'] ?? 15)
                );

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
            return [
                'tipo' => 'proposta',
                'proposta_id' => (int) $resultado['proposta_id'],
                'acao' => (string) ($resultado['tipo'] ?? ''),
                'resumo' => (string) ($resultado['resumo'] ?? ''),
                'situacao' => (string) ($resultado['situacao'] ?? 'RASCUNHO'),
                'payload' => is_array($resultado['payload'] ?? null) ? $resultado['payload'] : [],
            ];
        }
        if (!empty($resultado['candidatos_aulas']) && is_array($resultado['candidatos_aulas'])) {
            return [
                'tipo' => 'candidatos_aulas',
                'aviso' => (string) ($resultado['aviso'] ?? 'Há mais de uma aula. Escolha uma:'),
                'candidatos_aulas' => $resultado['candidatos_aulas'],
            ];
        }
        if (!empty($resultado['candidatos']) && is_array($resultado['candidatos'])) {
            $primeiro = $resultado['candidatos'][0] ?? [];
            if (
                str_contains($tool, 'professor')
                || $tool === 'resumo_jornadas_professor'
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
            if (in_array($tool, ['saude_turma', 'resumo_provas_turma', 'saude_academica', 'diario_pendencias', 'grade_horaria_consulta', 'ocorrencias_turma'], true)) {
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
                'diario_pendencias', 'grade_horaria_consulta',
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

Regras importantes:
1) Nunca invente notas, datas, progresso, enunciados, alunos ou professores — só dados das consultas/propostas.
2) Se citar turma (ex.: "2 Ano B", "2ªB"), SEMPRE passe args.turma / turma_nome (separado do nome).
3) NUNCA peça confirmação antes de consultar. Chame a tool primeiro. Se houver 1 aluno, use-o; se houver vários, mostre candidatos.
4) termo/aluno_nome = só o nome (ex.: "Maria Clara" = nome + sobrenome com LIKE %%token%%), NUNCA junte turma no nome ("Maria Clara do 2 Ano B" está errado).
5) Se o usuário informar RA/matrícula (ex.: "RA 24254"), passe args.ra só com os dígitos ("24254"). NUNCA use aluno_id como RA. Se a mensagem trouxer aluno_id=76, passe args.aluno_id=76 (inteiro) — não chame buscar_alunos de novo.
6) Ano letivo (ex.: 2024) vai em args.ano_letivo / args.ano, nunca dentro de aluno_nome ou ra.
7) Pedido sobre ALUNO + provas → resumo_provas_aluno ou listar_provas_aluno (última prova = listar e pegar a mais recente).
8) Pedido sobre ALUNO + jornadas → resumo_jornadas_aluno.
9) Pedido sobre TURMA sem professor → saude_turma / resumo_provas_turma.
10) Pedido sobre BLOCO → buscar_blocos / resultados_bloco.
11) Pedido sobre PROFESSOR → resumo_provas_professor / saude_turmas_professor / resumo_jornadas_professor.
12) Pedido de SAÚDE ACADÊMICA da escola / alunos com alerta cadastral → saude_academica.
13) Pedido de PERFIL 360 / visão geral de um aluno → perfil_aluno_360.
14) Pedido de DIÁRIO atrasado / pendências de diário → diario_pendencias.
15) Pedido de GRADE HORÁRIA / "qual aula hoje" → grade_horaria_consulta.
16) Pedido de BOLETIM completo → boletim_aluno. Pedido de UMA NOTA de matéria no boletim (ex.: "nota de matemática da Júlia") → notas_evento_aluno com materia="Matemática" (e bimestre/coluna se souber).
17) Pedido de nota de avaliação/prova por SIGLA ou NOME (ex.: "PB", "prova bimestral", "PO de matemática") → nota_evento_sigla. Compare SEMPRE com o catálogo de Eventos Avaliativos desta escola (abaixo): use a sigla cadastrada em args.sigla OU o nome cadastrado em args.evento_nome / args.termo. NUNCA invente o significado da sigla (não diga que PO = "Prova Objetiva" se isso não estiver no cadastro). Se não souber qual evento, chame listar_eventos_avaliativos. Coluna do boletim (PP/PU/MU) → notas_evento_aluno.
18) Frequência/presença/faltas (consulta) → faltas_aluno.
19) CONSULTA de ocorrência/incidente de ALUNO → ocorrencias_aluno. Ocorrências da TURMA → ocorrencias_turma. NUNCA use faltas_aluno para ocorrência.
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
26) CRIAR ocorrência / registrar incidente → propor_criar_ocorrencia (aceita vários alunos). NÃO diga que criou — diga que preparou a proposta e o usuário confirma no botão.
27) Registrar ENTRADA ou SAÍDA (portaria) → propor_entrada_saida.
28) Marcar PRESENTE/FALTA na CHAMADA de uma aula do diário → propor_chamada_aula.
29) Em ações de escrita, se nome ficar ambíguo, devolva candidatos; nunca chute aluno_id.

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
14) resumo_provas_professor / 15) detalhar_prova_professor / 16) ranking_erros_prova_professor
17) saude_turmas_professor / 18) resumo_jornadas_professor — {"professor_nome":"Ana","turma_nome":"2ªB"}

Tools — turma / bloco / diário / agenda / boletim / faltas / ocorrências:
19) saude_turma — {"turma_nome":"2 Ano B"} — KPIs crítico/atenção
20) resumo_provas_turma — {"turma_nome":"2 Ano B"} — totais acertos/erros por matéria
21) saude_academica — {"ano":2024} ou {"ano_letivo_id":4,"turma_nome":"1A"} — alertas cadastrais/matrícula
22) diario_pendencias — {"turma_nome":"1A","situacao":"atraso"} ou {"professor_nome":"Ana"} — diários pendentes
23) grade_horaria_consulta — {"turma_nome":"1A","data":"2026-11-12"} ou {"professor_nome":"Ana"} — aulas do dia
24) buscar_blocos — {"titulo":"Simulado"} ou {"turma_nome":"2ªB"}
25) resultados_bloco — {"bloco_id":10} ou {"titulo":"Simulado 1"}
26) perfil_aluno_360 — {"ra":"24254","ano_letivo":2024} — resumo geral do aluno
27) boletim_aluno — {"ra":"24254","ano_letivo":2024} — quadro anual (PP/PU/CH/PE/MU por bimestre); a UI oferece PDF
28) notas_evento_aluno — {"ra":"24254","materia":"Matemática","bimestre":2} ou {"coluna":"MU","bimestre":2} — nota/matéria/coluna do boletim/demonstrativo
29) listar_eventos_avaliativos — {"ano_letivo":2026} ou {"termo":"bimestral"} — catálogo vivo (sigla+nome) desta escola
30) nota_evento_sigla — {"aluno_id":76,"sigla":"PB","materia":"Matemática"} OU {"aluno_id":76,"evento_nome":"Prova Bimestral","materia":"Matemática"} OU {"termo":"prova bimestral","materia":"Matemática","aluno_id":76} — compara com Eventos Avaliativos cadastrados (sigla OU nome). Nunca invente sigla.
31) faltas_aluno — {"ra":"24254","ano_letivo":2024} ou {"aluno_id":365} — frequência/presença (NÃO é ocorrência). Passe o mesmo ano_letivo da conversa.
32) ocorrencias_aluno — {"ra":"24254","ano_letivo":2024} ou {"aluno_id":123,"status":"aberta"} — incidentes disciplinares
33) ocorrencias_turma — {"turma_nome":"1A","status":"aberta"} ou {"turma_id":37} — incidentes da turma

Tools — ações (só rascunho; o usuário confirma no botão):
34) propor_criar_ocorrencia — {"alunos":["João Silva","Maria Souza"],"turma":"1A","titulo":"Conversa","detalhe":"...","nivel_gravidade":"leve","categoria_id":1,"enviar_pais":false} — N alunos ok; nome+sobrenome; push SÓ se o usuário pedir notificar pais (enviar_pais=true)
35) propor_entrada_saida — {"aluno_nome":"João Silva","turma":"1A","tipo":"entrada","ocorrido_em":"2026-11-12 07:55"} ou tipo=saida
36) propor_chamada_aula — {"aluno_nome":"João Silva","turma":"1A","situacao":"presente","data":"2026-11-12","materia":"Matemática"} ou {"diario_aula_id":10,"aluno_id":123,"situacao":"falta"}

Eventos Avaliativos desta escola (fonte oficial — use só estes nomes/siglas):
PROMPT
            . json_encode($eventosResumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\nTipos de avaliação (provas) desta escola:\n"
            . json_encode($tiposResumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
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
        $alunoId = 0;
        $provas = [];
        foreach ($mensagens as $msg) {
            $c = (string) ($msg['content'] ?? '');
            if (preg_match('/aluno_id\s*=\s*(\d+)/', $c, $m)) {
                $alunoId = (int) $m[1];
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
            // "Detalhe da prova_id 123"
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
        return ['aluno_id' => $alunoId, 'provas' => $provas];
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
        if (preg_match('/^usar\s+aluno_id\s+(\d+)\b/iu', $mensagem, $m) !== 1) {
            return $mensagem;
        }
        $alunoId = (int) $m[1];
        $pedidoAnterior = $this->ultimoPedidoUsuario($historico, '/^usar\s+aluno_id\s+\d+/iu');
        $evento = $this->extrairEventoAvaliativoDoTexto($pedidoAnterior !== '' ? $pedidoAnterior : $mensagem);
        $materia = $this->extrairMateriaDaPergunta($pedidoAnterior !== '' ? $pedidoAnterior : $mensagem);

        $parts = ['Aluno já escolhido: aluno_id=' . $alunoId . '.'];
        $parts[] = 'NÃO chame buscar_alunos. NÃO use esse número como RA.';
        if ($pedidoAnterior !== '') {
            $parts[] = 'Retome o pedido original: «' . mb_substr($pedidoAnterior, 0, 400) . '».';
        }
        if ($evento !== null) {
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
    private function ultimoPedidoUsuario(array $historico, string $ignorarPadrao): string
    {
        foreach (array_reverse($historico) as $msg) {
            if (($msg['role'] ?? '') !== 'user') {
                continue;
            }
            $c = trim((string) ($msg['content'] ?? ''));
            if ($c === '' || preg_match($ignorarPadrao, $c) === 1) {
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
            $freq = is_array($resultado['frequencia'] ?? null) ? $resultado['frequencia'] : $resultado;
            $faltas = $freq['faltas'] ?? $freq['total_faltas'] ?? $resultado['faltas'] ?? null;
            $perc = $freq['percentual'] ?? $freq['frequencia_percentual'] ?? $resultado['percentual'] ?? null;
            if ($faltas === null && $perc === null) {
                return null;
            }
            $txt = $quem;
            if ($faltas !== null) {
                $txt .= ' tem **' . $faltas . '** falta' . ((int) $faltas === 1 ? '' : 's');
            }
            if ($perc !== null && $perc !== '') {
                $txt .= ($faltas !== null ? ' (frequência **' . $perc . '%**)' : ' está com frequência **' . $perc . '%**');
            }
            return $txt . '.';
        }

        if ($tool === 'nota_evento_sigla') {
            $sigla = strtoupper(trim((string) ($resultado['evento']['sigla'] ?? '')));
            $nomeEv = trim((string) ($resultado['evento']['nome'] ?? $sigla));
            $rotuloEv = $sigla !== '' ? $sigla : $nomeEv;
            if (($resultado['formato'] ?? '') === 'nota_unica') {
                $mat = trim((string) ($resultado['materia'] ?? ''));
                $nota = $resultado['nota'] ?? null;
                if ($nota === null || $nota === '') {
                    return 'Não há nota lançada da ' . $rotuloEv
                        . ($mat !== '' ? ' de ' . $mat : '')
                        . ' para ' . $quem . '.';
                }
                return 'A nota da ' . $rotuloEv
                    . ($mat !== '' ? ' de ' . $mat : '')
                    . ' d' . $this->artigoDe($quem) . ' ' . $quem
                    . ' é **' . $nota . '**.';
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
                $achado = $this->buscarNotaMateriaNoQuadro($quadro, $materiaPedido);
                if ($achado !== null) {
                    $rotuloMat = $achado['materia'] !== '' ? $achado['materia'] : $materiaPedido;
                    if ($achado['valor'] === null || $achado['valor'] === '') {
                        return 'Não há nota lançada de ' . $rotuloMat . ' para ' . $quem . '.';
                    }
                    return 'A nota de ' . $rotuloMat . ' d' . $this->artigoDe($quem) . ' ' . $quem
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
            '/\bturma\b|\bquem\s+(é|e)\b|\bnome\s+d[oa]\b|\bdados\s+d[oa]\b|\bcadastro\b|\b(em\s+)?qual\s+turma\b/iu',
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
        if (preg_match('/\bRA\s*[#:]?\s*(\d{3,12})\b/iu', $texto, $m) === 1) {
            return $m[1];
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
        // "nota de matemática", "em matemática", "matéria matemática"
        if (preg_match(
            '/\b(?:nota|media|média|desempenho|resultado)?\s*(?:de|em|da|do)?\s*(matematica|portugues|lingua portuguesa|ciencias|historia|geografia|ingles|educacao fisica|artes?|biologia|fisica|quimica|filosofia|sociologia|redacao)\b/u',
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
    private function buscarNotaMateriaNoQuadro(array $quadro, string $materiaPedido): ?array
    {
        $alvo = $this->semAcento(mb_strtolower($materiaPedido));
        $linhas = is_array($quadro['linhas'] ?? null) ? $quadro['linhas'] : [];
        $melhor = null;
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
            $valor = $this->escolherValorNota($notas, is_array($quadro['colunas'] ?? null) ? $quadro['colunas'] : []);
            $melhor = ['materia' => $nome, 'valor' => $valor];
            if ($norm === $alvo) {
                break;
            }
        }
        return $melhor;
    }

    /**
     * @param array<string,mixed> $notas
     * @param list<array<string,mixed>> $colunas
     */
    private function escolherValorNota(array $notas, array $colunas): ?string
    {
        if ($notas === []) {
            return null;
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
        if (!CreditosModuleRegistry::isValid(self::MODULO_CREDITOS)) {
            return;
        }
        $creditos = new \App\Services\CreditosService();
        if (!$creditos->isCreditosHabilitado()) {
            if (self::LIBERAR_SEM_TUDICOINS) {
                return;
            }
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
        if (!CreditosModuleRegistry::isValid(self::MODULO_CREDITOS)) {
            return;
        }
        $creditos = new \App\Services\CreditosService();
        if (!$creditos->isCreditosHabilitado()) {
            if (self::LIBERAR_SEM_TUDICOINS) {
                return;
            }
            throw new \Exception('TudiCoins desabilitado para esta escola. Ações com IA não estão disponíveis.');
        }
        $creditos->consumirEscola(self::MODULO_CREDITOS, 'provas_aluno_assistente');
    }
}

if (!class_exists('ProvasAlunoAssistenteService', false) && class_exists(__NAMESPACE__ . '\\ProvasAlunoAssistenteService', false)) {
    class_alias(__NAMESPACE__ . '\\ProvasAlunoAssistenteService', 'ProvasAlunoAssistenteService');
}
