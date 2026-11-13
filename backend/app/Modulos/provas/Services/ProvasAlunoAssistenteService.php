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
    /** Suspende a exigência e o débito de TudiCoins no Chat Assistente (só para debug local). */
    public const LIBERAR_SEM_TUDICOINS = true;
    /** Motor v2: function calling nativo + estado estruturado (Services/Assistente). */
    public const MOTOR_V2 = true;

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
    /** @var array<string,mixed> */
    private array $estadoConversa = [];

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

    /** @param array<string,mixed> $estado estado_json da conversa (motor v2) */
    public function definirEstadoConversa(array $estado): void
    {
        $this->estadoConversa = $estado;
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

        if (self::MOTOR_V2) {
            return $this->processarComMotorV2($mensagemUsuario, $historico, $onStatus, $onTextoChunk);
        }

        
        return ['success' => false, 'error' => 'Motor v1 do Assistente foi desativado. Ative MOTOR_V2.'];
    }

    /**
     * @param list<array{role:string,content:string,proposta_id?:int}> $historico
     * @return array<string,mixed>
     */
    private function processarComMotorV2(
        string $mensagemUsuario,
        array $historico,
        ?callable $onStatus,
        ?callable $onTextoChunk
    ): array {
        $confirmacao = $this->tentarConfirmarProposta($mensagemUsuario, $historico, $onStatus, $onTextoChunk);
        if ($confirmacao !== null) {
            $estado = Assistente\AssistenteEstadoConversa::normalizar($this->estadoConversa);
            $confirmacao['estado'] = Assistente\AssistenteEstadoConversa::limparFluxo($estado);
            return $confirmacao;
        }

        try {
            $this->assertCreditosDisponiveis();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $motor = new Assistente\AssistenteMotorService($this, $this->openai);
        $resultado = $motor->processar($mensagemUsuario, $historico, $this->estadoConversa, $onStatus, $onTextoChunk);
        if (empty($resultado['success'])) {
            return $resultado;
        }

        try {
            $this->debitarCreditos();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
        return $resultado;
    }

    /**
     * Confirma apenas o card exibido na última resposta, por ID.
     *
     * @param list<array{role:string,content:string,proposta_id?:int}> $historico
     * @return array<string,mixed>|null
     */
    private function tentarConfirmarProposta(
        string $mensagemUsuario,
        array $historico,
        ?callable $onStatus,
        ?callable $onTextoChunk
    ): ?array {
        $propostaExibida = $this->propostaExibidaNoHistorico($historico);
        if ($propostaExibida <= 0 || !$this->mensagemConfirmaProposta($mensagemUsuario)) {
            return null;
        }
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

    /** Catálogos vivos da escola + data de hoje, anexados ao prompt do motor v2. */
    public function catalogosParaPrompt(): string
    {
        $tiposResumo = [];
        foreach (array_slice($this->consulta->listarTiposAvaliacao(), 0, 30) as $t) {
            $tiposResumo[] = ['id' => $t['id'], 'nome' => $t['nome']];
        }
        $eventosResumo = [];
        $eventosCat = $this->ampliada->listarEventosAvaliativos([]);
        foreach (array_slice($eventosCat['eventos'] ?? [], 0, 40) as $ev) {
            $eventosResumo[] = [
                'sigla' => (string) ($ev['sigla'] ?? ''),
                'nome' => (string) ($ev['nome'] ?? ''),
                'bimestre' => $ev['bimestre'] ?? null,
                'ano_letivo' => $ev['ano_letivo'] ?? null,
            ];
        }
        return 'Eventos Avaliativos desta escola (use só estas siglas em nota_evento_sigla): '
            . json_encode($eventosResumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\nTipos de avaliação de provas: "
            . json_encode($tiposResumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\nHoje (America/Sao_Paulo): " . $this->ampliada->hojeLocal();
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
    public function montarPainel(?string $tool, ?array $resultado): ?array
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














    /**
     * Quando o pedido é pontual (nota de uma matéria, boletim, faltas), monta frase assertiva
     * a partir do resultado da tool — independente do estilo da IA.
     *
     * @param array<string,mixed>|null $resultado
     */
    public function montarRespostaDireta(string $pergunta, ?string $tool, ?array $resultado): ?string
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
            if ($tool === 'boletim_aluno' && $materiaPedido !== null) {
                if (!$this->boletimTemConteudoExibivel($resultado)) {
                    return 'Não encontrei um boletim com notas para exibir para **' . $quem . '**.';
                }
                $ano = (int) ($resultado['ano_letivo'] ?? 0);
                return 'Encontrei o boletim de **' . $quem . '**'
                    . ($ano > 0 ? ' de **' . $ano . '**' : '')
                    . '. Confira as notas disponíveis no quadro abaixo. '
                    . 'Para consultar uma nota específica, diga o período ou a avaliação.';
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









    private function formatarDataBr(string $ymd): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m) === 1) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }
        return $ymd;
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
