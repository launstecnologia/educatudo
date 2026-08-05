<?php
/**
 * Assistente de Provas dos Alunos — chat NL para coordenação consultar
 * desempenho (provas, notas, acertos) via tools de leitura.
 */

require_once __DIR__ . '/OpenAIService.php';
require_once __DIR__ . '/ProvasAlunoConsultaService.php';
require_once __DIR__ . '/JornadasAlunoConsultaService.php';
require_once __DIR__ . '/ProvasProfessorConsultaService.php';
require_once __DIR__ . '/AssistenteConsultaAmpliadaService.php';
require_once __DIR__ . '/CreditosService.php';
require_once __DIR__ . '/../Core/CreditosModuleRegistry.php';

class ProvasAlunoAssistenteService
{
    public const MODULO_CREDITOS = 'provas_aluno_assistente_mensagem';
    public const DELIMITADOR_INICIO = '<<<CONSULTA>>>';
    public const DELIMITADOR_FIM = '<<<FIM>>>';
    private const MAX_RODADAS = 6;

    private ProvasAlunoConsultaService $consulta;
    private JornadasAlunoConsultaService $jornadas;
    private ProvasProfessorConsultaService $professores;
    private AssistenteConsultaAmpliadaService $ampliada;
    /** @var \App\Services\OpenAIService */
    private $openai;

    public function __construct(
        ?ProvasAlunoConsultaService $consulta = null,
        ?JornadasAlunoConsultaService $jornadas = null,
        ?ProvasProfessorConsultaService $professores = null,
        ?AssistenteConsultaAmpliadaService $ampliada = null,
        $openai = null
    ) {
        $this->consulta = $consulta ?? new ProvasAlunoConsultaService();
        $this->jornadas = $jornadas ?? new JornadasAlunoConsultaService($this->consulta);
        $this->professores = $professores ?? new ProvasProfessorConsultaService();
        $this->ampliada = $ampliada ?? new AssistenteConsultaAmpliadaService($this->consulta, $this->professores);
        $this->openai = $openai instanceof \App\Services\OpenAIService
            ? $openai
            : new \App\Services\OpenAIService();
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

        try {
            $this->assertCreditosDisponiveis();
        } catch (Throwable $e) {
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
                        . "\n\nUse esses dados (não invente). Se precisar de outra consulta, emita <<<CONSULTA>>> de novo. "
                        . 'Se o usuário pediu questões erradas/detalhe e você ainda NÃO chamou detalhar_prova_aluno (com prova_id do contexto e somente_erros=true), chame agora. '
                        . 'Caso contrário, texto CURTO (2–4 linhas). A UI monta a tabela — não diga que não consegue se o resultado trouxe questoes[].',
                ];
            }
        } catch (Throwable $e) {
            error_log('ProvasAlunoAssistenteService: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Falha ao consultar a IA. Tente novamente.'];
        }

        if ($textoFinal === null) {
            $textoFinal = 'Consultei os dados. Escolha: provas semanais, bimestrais, um bimestre específico ou consolidado geral.';
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

        try {
            $this->debitarCreditos();
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $painel = $this->montarPainel($ultimaTool, $ultimoResultado);

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
                $termo = trim((string) ($args['termo'] ?? $args['nome'] ?? ''));
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

            case 'boletim_aluno':
                return $this->ampliada->boletimAluno($args);

            case 'faltas_aluno':
                return $this->ampliada->faltasAluno($args);

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
            if (in_array($tool, ['saude_turma', 'resumo_provas_turma'], true)) {
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
        if ($tool === 'boletim_aluno' && !empty($resultado['ok'])) {
            return [
                'tipo' => 'boletim_aluno',
                'aluno' => $resultado['aluno'] ?? null,
                'eventos' => $resultado['eventos'] ?? [],
                'total_eventos' => (int) ($resultado['total_eventos'] ?? 0),
                'opcoes' => [
                    'Ver faltas deste aluno',
                    'Consolidado de provas',
                    'Jornadas deste aluno',
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
                    'Ver boletim deste aluno',
                    'Saúde da turma',
                    'Consolidado de provas',
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

        return <<<PROMPT
Você é o Assistente da EducaTudo (coordenação/direção).

Objetivo: consultar ALUNOS (provas/jornadas/boletim/faltas), TURMAS (saúde e provas), BLOCOS de prova, e PROFESSORES (provas e jornadas).

Regras importantes:
1) Nunca invente notas, datas, progresso, enunciados, alunos ou professores — só dados das consultas.
2) Se citar turma (ex.: "2 Ano B", "2ªB"), SEMPRE passe args.turma / turma_nome (separado do nome).
3) NUNCA peça confirmação antes de consultar. Chame a tool primeiro. Se houver 1 aluno, use-o; se houver vários, mostre candidatos.
4) termo/aluno_nome = só o nome (ex.: "Maria Clara"), NUNCA junte turma no nome ("Maria Clara do 2 Ano B" está errado).
5) Pedido sobre ALUNO + provas → resumo_provas_aluno ou listar_provas_aluno (última prova = listar e pegar a mais recente).
6) Pedido sobre ALUNO + jornadas → resumo_jornadas_aluno.
7) Pedido sobre TURMA sem professor → saude_turma / resumo_provas_turma.
8) Pedido sobre BLOCO → buscar_blocos / resultados_bloco.
9) Pedido sobre PROFESSOR → resumo_provas_professor / saude_turmas_professor / resumo_jornadas_professor.
10) Boletim/faltas → boletim_aluno / faltas_aluno.
11) Detalhe de questão → detalhar_prova_aluno com somente_erros=true.
12) Resposta final: texto CURTO (2–4 linhas). A UI monta tabelas.
13) Se a tool devolver candidatos/aviso, liste-os e peça escolha — não diga "não encontrei" se houver candidatos.

Para obter dados, emita EXATAMENTE:
<<<CONSULTA>>>
{"tool":"NOME","args":{...}}
<<<FIM>>>

Tools — alunos/provas:
1) buscar_alunos — {"termo":"Maria","turma":"2 Ano B"}
2) listar_materias / 3) listar_tipos_avaliacao
4) listar_provas_aluno / 5) detalhar_prova_aluno / 6) resumo_provas_aluno

Tools — jornadas (aluno):
7) listar_materias_jornadas / 8) listar_jornadas_aluno / 9) detalhar_jornada_aluno / 10) resumo_jornadas_aluno

Tools — professor:
11) buscar_professores / 12) listar_turmas_professor / 13) listar_provas_professor
14) resumo_provas_professor / 15) detalhar_prova_professor / 16) ranking_erros_prova_professor
17) saude_turmas_professor / 18) resumo_jornadas_professor — {"professor_nome":"Ana","turma_nome":"2ªB"}

Tools — turma / bloco / boletim / faltas:
19) saude_turma — {"turma_nome":"2 Ano B"} — KPIs crítico/atenção
20) resumo_provas_turma — {"turma_nome":"2 Ano B"} — totais acertos/erros por matéria
21) buscar_blocos — {"titulo":"Simulado"} ou {"turma_nome":"2ªB"}
22) resultados_bloco — {"bloco_id":10} ou {"titulo":"Simulado 1"}
23) boletim_aluno — {"aluno_id":123} ou {"aluno_nome":"Maria","turma":"2ªB"}
24) faltas_aluno — {"aluno_id":123} — frequência no período/ano

Tipos de avaliação (provas) desta escola:
PROMPT
            . json_encode($tiposResumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\nQuando já tiver os dados, responda em texto curto (sem <<<CONSULTA>>>).";
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
        return trim($resposta);
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
            case 'boletim_aluno':
                return 'Consultando boletim…';
            case 'faltas_aluno':
                return 'Consultando frequência…';
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
            throw new \Exception('TudiCoins desabilitado para esta escola. Ações com IA não estão disponíveis.');
        }
        if (!$creditos->podeConsumir(
            'escola',
            \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID,
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
            throw new \Exception('TudiCoins desabilitado para esta escola. Ações com IA não estão disponíveis.');
        }
        $creditos->consumirEscola(self::MODULO_CREDITOS, 'provas_aluno_assistente');
    }
}
