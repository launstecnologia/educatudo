<?php

require_once __DIR__ . '/../../Core/PeriodoLetivo.php';
require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
require_once __DIR__ . '/../../Models/Education/JourneyBoletimLancamento.php';
require_once __DIR__ . '/../../Models/Education/SchoolAbsence.php';
require_once __DIR__ . '/../../Helpers/BoletimQuadroLayoutHelper.php';
require_once __DIR__ . '/../../Services/BoletimAssistenteWizard.php';
require_once __DIR__ . '/../../Services/ResultadoAcademicoService.php';
require_once __DIR__ . '/../../Modulos/boletins/Services/BoletimCadastroService.php';
require_once __DIR__ . '/../../Modulos/grupos-regras-notas/Services/GrupoRegrasNotasService.php';
require_once __DIR__ . '/../../Modulos/fechamento/Services/FechamentoGates.php';

use App\Modulos\Boletins\Services\BoletimCadastroService;

class BoletimConfigController extends BaseController
{
    private $auth;
    private $boletimConfig;
    /** @var array<int, array<string, mixed>|null> */
    private array $agrupamentoCadastroCache = [];
    /** @var list<array<string,mixed>>|null */
    private $materiasDisponiveisCache = null;
    /** @var \ComponenteCurricular|null */
    private $componentesCurricularesCache = null;
    /** @var array<string, list<int>> */
    private array $materiasExpandidasCache = [];
    private ?ResultadoAcademicoService $resultadoAcademicoSvc = null;
    /** @var array<int, bool> Matérias filhas de group_line com arredondamento=mae (não aplicam half nas células). */
    private array $midsSemArredondamentoGrupo = [];
    /** @var array<int, ?int> */
    private array $cursoPorTurmaCache = [];
    /** @var array<string, mixed>|null Usuário do job CLI (sem sessão). */
    private ?array $usuarioGeracaoJob = null;
    private int $jobIdHeartbeat = 0;
    private float $ultimoHeartbeatEm = 0.0;
    /** @var array<int, array<string, mixed>> */
    private array $alunosPorIdCache = [];
    /** @var array<string, array<int, list<array<string, mixed>>>> chave prefetch => aluno_id => provas */
    private array $provasGeracaoCache = [];
    private bool $prefetchGeracaoPronto = false;
    private bool $prefetchIncluirPct = false;
    /** @var array<int, array<string, mixed>> */
    private array $faltasEventoCache = [];
    /** @var array<int, array<int, array<int, array<string, mixed>>>> */
    private array $notasManuaisGeracaoCache = [];
    private bool $notasManuaisGeracaoAtivo = false;
    /** @var array<int, array<string, mixed>> */
    private array $eventosFaltasCache = [];
    /** @var array<int, array{regra: array<string, mixed>, componentes: list<array<string, mixed>>}> */
    private array $expansaoRegraCache = [];
    /** @var array<string, array<string, mixed>> */
    private array $simulacaoAlunoCache = [];
    /** @var list<int> */
    private array $turmasEscopoGeracao = [];
    /** @var list<int> */
    private array $seriesEscopoGeracao = [];
    /** @var array<string, list<int>> */
    private array $blocosFiltradosPorTurmaCache = [];
    /** @var array<string, list<int>> */
    private array $blocosSemanaAssistenteCache = [];
    /** @var array<int, array{a: list<int>, b: list<int>}> */
    private array $semanasQuadroCache = [];
    private const TAMANHO_LOTE_ALUNOS_PERSISTIR = 200;
    private const INTERVALO_HEARTBEAT_SEGUNDOS = 2.0;

    public function __construct(bool $somenteGeracao = false)
    {
        parent::__construct();

        $this->auth = new AuthManager();
        $this->boletimConfig = new BoletimConfig();
        $this->boletimConfig->ensureSchema();

        if ($somenteGeracao) {
            return;
        }

        $user = $this->auth->getUser();
        if (!$this->usuarioPodeConfigurarBoletim($user)) {
            $this->redirect(URL . '/admin');
            exit;
        }

        if (($user['perfil_admin'] ?? '') === 'financeiro') {
            $this->redirect(URL . '/admin/dashboard');
            exit;
        }

        if ((string) ($user['perfil_admin'] ?? '') !== 'dev') {
            $this->setFlashMessage('Você não tem permissão. Contate o Administrador.', 'error');
            $this->redirect('/admin/boletins');
        }
    }

    /**
     * Admin global ou admin_escola com perfil dev, diretor ou coordenador.
     */
    private function usuarioPodeConfigurarBoletim(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        if (($user['tipo'] ?? '') === 'admin') {
            return true;
        }
        if (($user['tipo'] ?? '') === 'admin_escola'
            && in_array($user['perfil_admin'] ?? '', ['dev', 'diretor', 'coordenador'], true)) {
            return true;
        }

        return false;
    }

    public function listagem()
    {
        $user = $this->auth->getUser();

        $filtroNome = trim((string) ($_GET['nome'] ?? ''));
        $filtroAno = trim((string) ($_GET['ano_letivo'] ?? ''));
        $filtroBimestre = trim((string) ($_GET['bimestre'] ?? ''));
        $filtroSerieId = (int) ($_GET['serie_id'] ?? 0);
        $ordemLista = strtolower(trim((string) ($_GET['ordem'] ?? '')));
        if (!in_array($ordemLista, ['ref', 'serie', 'bimestre'], true)) {
            $ordemLista = '';
        }
        $dirLista = strtolower(trim((string) ($_GET['dir'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc';
        $exibirDesabilitados = (string) ($_GET['desabilitados'] ?? '') === '1'
            || (string) ($_GET['bloqueados'] ?? '') === '1';

        $eventos = $this->boletimConfig->listAllRules(300);
        $eventos = array_values(array_filter($eventos, static function ($ev) {
            return strtolower(trim((string) ($ev['exibir_em'] ?? 'boletim'))) === 'notas';
        }));
        $eventos = array_values(array_filter($eventos, function ($ev) use ($exibirDesabilitados) {
            $ev = is_array($ev) ? $ev : [];
            $foraDaLista = $this->eventoOcultoNaListaAvaliacoes($ev) || $this->eventoBloqueadoNaExibicao($ev);
            if (!$foraDaLista) {
                return true;
            }

            return $exibirDesabilitados;
        }));

        $nomesBoletim = [];
        try {
            $cadastro = new BoletimCadastroService();
            if ($cadastro->model()->tabelasProntas()) {
                foreach ($cadastro->model()->listar(false, true) as $bol) {
                    $nomesBoletim[(int) $bol['id']] = (string) $bol['nome'];
                }
            }
        } catch (Throwable $e) {
            $nomesBoletim = [];
        }

        if ($filtroNome !== '') {
            $eventos = array_values(array_filter($eventos, static function ($ev) use ($filtroNome) {
                $alvo = mb_strtolower((string) ($ev['nome'] ?? '') . ' ' . (string) ($ev['codigo'] ?? ''));
                return mb_strpos($alvo, mb_strtolower($filtroNome)) !== false;
            }));
        }
        if ($filtroAno !== '') {
            $eventos = array_values(array_filter($eventos, static function ($ev) use ($filtroAno) {
                return (string) ($ev['ano_letivo'] ?? '') === $filtroAno;
            }));
        }
        if ($filtroBimestre !== '') {
            $eventos = array_values(array_filter($eventos, static function ($ev) use ($filtroBimestre) {
                return (string) ($ev['bimestre'] ?? '') === $filtroBimestre;
            }));
        }

        $seriesNomesPorId = [];
        $seriesOrdemPorId = [];
        $seriesCatalogo = [];
        foreach ($this->boletimConfig->getAvailableSeries(300) as $serie) {
            $sid = (int) ($serie['id'] ?? 0);
            $nomeSerie = trim((string) ($serie['nome'] ?? ''));
            if ($sid <= 0 || $nomeSerie === '') {
                continue;
            }
            $seriesNomesPorId[$sid] = $nomeSerie;
            $seriesOrdemPorId[$sid] = (int) ($serie['ordem'] ?? 0);
            $seriesCatalogo[] = ['id' => $sid, 'nome' => $nomeSerie];
        }
        if ($filtroSerieId > 0 && !isset($seriesNomesPorId[$filtroSerieId])) {
            $filtroSerieId = 0;
        }
        if ($filtroSerieId > 0) {
            $eventos = array_values(array_filter($eventos, function ($ev) use ($filtroSerieId) {
                $ids = $this->parseSeriesIdsFromRegra(is_array($ev) ? $ev : []);
                if ($ids === []) {
                    return true;
                }

                return in_array($filtroSerieId, $ids, true);
            }));
        }
        $ultimaGeracaoPorRegra = $this->boletimConfig->getUltimaGeracaoPorRegra();
        $statusGeracaoPorRegra = $this->boletimConfig->mapearStatusGeracaoAssincrona();
        $geracaoJobIds = [];
        $temGeracaoEmAndamento = false;
        $geracaoConcluidaMsg = '';

        foreach ($eventos as &$ev) {
            $seriesIds = $this->parseSeriesIdsFromRegra($ev);
            $nomes = array_filter(array_map(static function ($sid) use ($seriesNomesPorId) {
                return $seriesNomesPorId[(int) $sid] ?? null;
            }, $seriesIds));
            $ev['series_nomes'] = $nomes;
            $ordensSerie = [];
            foreach ($seriesIds as $sidOrdem) {
                $sidOrdem = (int) $sidOrdem;
                if (isset($seriesOrdemPorId[$sidOrdem])) {
                    $ordensSerie[] = $seriesOrdemPorId[$sidOrdem];
                }
            }
            $ev['serie_ordem'] = $ordensSerie === [] ? 999999 : min($ordensSerie);
            $ev['serie_rotulo'] = $nomes === [] ? '' : implode(' ', $nomes);
            $ev['oculto_lista_avaliacoes'] = $this->eventoOcultoNaListaAvaliacoes($ev) ? 1 : 0;
            $bid = (int) ($ev['boletim_id'] ?? 0);
            $ev['boletim_cadastro_nome'] = $bid > 0 ? ($nomesBoletim[$bid] ?? '') : '';

            $regraIdEv = (int) ($ev['id'] ?? 0);
            $ultimaGeracao = $ultimaGeracaoPorRegra[$regraIdEv] ?? null;
            $ev['ultima_geracao'] = $ultimaGeracao;
            $regraAtualizadaEm = strtotime((string) ($ev['updated_at'] ?? ''));
            $geracaoEm = $ultimaGeracao !== null ? strtotime((string) $ultimaGeracao) : false;
            $ev['boletim_desatualizado'] = $geracaoEm !== false && $regraAtualizadaEm !== false && $regraAtualizadaEm > $geracaoEm;
            $stGeracao = $statusGeracaoPorRegra[$regraIdEv] ?? null;
            $ev['geracao_status'] = is_array($stGeracao) ? (string) ($stGeracao['status'] ?? '') : '';
            $ev['geracao_job_id'] = is_array($stGeracao) ? (int) ($stGeracao['job_id'] ?? 0) : 0;
            $ev['geracao_erro'] = is_array($stGeracao) ? (string) ($stGeracao['error'] ?? '') : '';
            $ev['geracao_mensagem'] = is_array($stGeracao) ? (string) ($stGeracao['mensagem'] ?? '') : '';
            $ev['geracao_iniciada_em'] = is_array($stGeracao) ? (string) ($stGeracao['created_at'] ?? '') : '';
            $ev['geracao_completed_at'] = is_array($stGeracao) ? (string) ($stGeracao['completed_at'] ?? '') : '';
            if (in_array($ev['geracao_status'], ['pending', 'processing'], true) && $ev['geracao_job_id'] > 0) {
                $geracaoJobIds[] = $ev['geracao_job_id'];
                $temGeracaoEmAndamento = true;
            }
            if (
                $geracaoConcluidaMsg === ''
                && $ev['geracao_status'] === 'done'
                && $ev['geracao_mensagem'] !== ''
                && $ev['geracao_completed_at'] !== ''
            ) {
                $concluidaEm = strtotime($ev['geracao_completed_at']);
                if ($concluidaEm !== false && (time() - $concluidaEm) <= 180) {
                    $geracaoConcluidaMsg = $ev['geracao_mensagem'];
                }
            }
        }
        unset($ev);

        if ($ordemLista !== '') {
            $multiplicador = $dirLista === 'desc' ? -1 : 1;
            usort($eventos, static function (array $a, array $b) use ($ordemLista, $multiplicador): int {
                if ($ordemLista === 'ref') {
                    $cmp = ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                } elseif ($ordemLista === 'bimestre') {
                    $cmp = ((int) ($a['bimestre'] ?? 0)) <=> ((int) ($b['bimestre'] ?? 0));
                    if ($cmp === 0) {
                        $cmp = ((int) ($a['ano_letivo'] ?? 0)) <=> ((int) ($b['ano_letivo'] ?? 0));
                    }
                } else {
                    $cmp = ((int) ($a['serie_ordem'] ?? 999999)) <=> ((int) ($b['serie_ordem'] ?? 999999));
                    if ($cmp === 0) {
                        $cmp = strnatcasecmp((string) ($a['serie_rotulo'] ?? ''), (string) ($b['serie_rotulo'] ?? ''));
                    }
                }
                if ($cmp === 0) {
                    $cmp = ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                }

                return $cmp * $multiplicador;
            });
        }

        $perPage = 10;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = count($eventos);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min($page, max(1, $totalPages));
        $eventosPagina = array_slice($eventos, ($page - 1) * $perPage, $perPage);

        $data = [
            'title' => 'Avaliações - EducaTudo',
            'page_title' => 'Painel Administrativo',
            'user' => $user,
            'current_page' => 'boletim_config',
            'csrf_token' => $this->generateCsrfToken(),
            'eventos' => $eventosPagina,
            'filtro_nome' => $filtroNome,
            'filtro_ano' => $filtroAno,
            'filtro_bimestre' => $filtroBimestre,
            'filtro_serie_id' => $filtroSerieId,
            'series_catalogo' => $seriesCatalogo,
            'ordem_lista' => $ordemLista,
            'dir_lista' => $dirLista,
            'exibir_desabilitados' => $exibirDesabilitados,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'page' => $page,
                'total_pages' => $totalPages,
            ],
            'flash_message' => $_SESSION['boletim_flash'] ?? '',
            'flash_type' => $_SESSION['boletim_flash_type'] ?? 'success',
            'tem_geracao_em_andamento' => $temGeracaoEmAndamento,
            'geracao_job_ids' => $geracaoJobIds,
            'geracao_concluida_msg' => $geracaoConcluidaMsg,
        ];

        $this->viewWithLayout('admin', 'admin/boletim/listagem', $data);
        unset($_SESSION['boletim_flash'], $_SESSION['boletim_flash_type']);
    }

    /**
     * Lista temporária dos eventos antigos (Boletim e Notas) para consultar a fórmula.
     * Fora do menu. Sai quando o configurador novo cobrir esses eventos.
     */
    public function arquivo(): void
    {
        $user = $this->auth->getUser();
        $eventos = array_values(array_filter($this->boletimConfig->listAllRules(500), static function ($ev): bool {
            $tipo = strtolower(trim((string) ($ev['exibir_em'] ?? '')));

            return $tipo === 'notas' || $tipo === 'boletim';
        }));

        $seriesNomesPorId = [];
        foreach ($this->boletimConfig->getAvailableSeries(300) as $serie) {
            $seriesNomesPorId[(int) ($serie['id'] ?? 0)] = trim((string) ($serie['nome'] ?? ''));
        }

        foreach ($eventos as &$ev) {
            $nomes = [];
            $ordemMax = 0;
            foreach ($this->parseSeriesIdsFromRegra($ev) as $sid) {
                $nomeSerie = $seriesNomesPorId[(int) $sid] ?? '';
                if ($nomeSerie === '') {
                    continue;
                }
                $nomes[] = $nomeSerie;
                if (preg_match('/\d+/', $nomeSerie, $matchSerie)) {
                    $ordemMax = max($ordemMax, (int) $matchSerie[0]);
                }
            }
            $tipoNotas = strtolower(trim((string) ($ev['exibir_em'] ?? ''))) === 'notas';
            $ev['tipo_label'] = $tipoNotas ? 'Notas' : 'Boletim';
            $ev['series_nomes'] = $nomes;
            $seriesLabel = implode(', ', $nomes);
            $ev['nome_exibicao'] = trim(
                $ev['tipo_label'] . ' — ' . (string) ($ev['nome'] ?? 'Evento')
                . ($seriesLabel !== '' ? ' ' . $seriesLabel : '')
            );
            $ev['_serie_ordem'] = $ordemMax;
        }
        unset($ev);

        usort($eventos, static function (array $a, array $b): int {
            $cmp = ((int) ($b['_serie_ordem'] ?? 0)) <=> ((int) ($a['_serie_ordem'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['nome_exibicao'] ?? ''), (string) ($b['nome_exibicao'] ?? ''));
        });

        $this->viewWithLayout('admin', 'admin/boletim/arquivo', [
            'title' => 'Arquivo de fórmulas - EducaTudo',
            'page_title' => 'Arquivo de fórmulas',
            'user' => $user,
            'current_page' => 'boletim_config',
            'eventos' => $eventos,
        ]);
    }

    public function geracaoStatusJson(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Content-Type: application/json; charset=utf-8');

        $idsRaw = trim((string) ($_GET['ids'] ?? ''));
        $ids = $idsRaw === '' ? [] : array_map('intval', explode(',', $idsRaw));
        $jobs = [];
        if ($ids !== []) {
            foreach ($this->boletimConfig->statusJobsGeracaoPorIds($ids) as $jobId => $st) {
                $jobs[(string) (int) $jobId] = [
                    'job_id' => (int) ($st['job_id'] ?? $jobId),
                    'status' => (string) ($st['status'] ?? ''),
                    'error' => (string) ($st['error'] ?? ''),
                    'mensagem' => (string) ($st['mensagem'] ?? ''),
                ];
            }
        }

        echo json_encode(['ok' => true, 'jobs' => $jobs], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function cancelarGeracaoBoletim(): void
    {
        $this->assertCsrfOrRedirect();
        $regraId = (int) ($_POST['regra_id'] ?? 0);
        require_once __DIR__ . '/../../Services/AIJobService.php';
        $n = \App\Services\AIJobService::cancelarBoletimGerar($regraId > 0 ? $regraId : null);
        if ($n > 0) {
            $_SESSION['boletim_flash'] = $n === 1
                ? 'Geração interrompida. Você já pode gerar de novo.'
                : ('Interrompidas ' . $n . ' gerações em andamento.');
            $_SESSION['boletim_flash_type'] = 'info';
        } else {
            $_SESSION['boletim_flash'] = 'Não havia geração na fila; o processo no banco foi encerrado se ainda estivesse preso.';
            $_SESSION['boletim_flash_type'] = 'info';
        }
        $this->redirect('/admin/boletim');
    }

    public function index()
    {
        $user = $this->auth->getUser();
        $somenteTabela = isset($_GET['somente_tabela']) && in_array(strtolower(trim((string) $_GET['somente_tabela'])), ['1', 'true', 'sim', 'yes'], true);
        $modoArquivo = isset($_GET['arquivo']) && in_array(strtolower(trim((string) $_GET['arquivo'])), ['1', 'true', 'sim', 'yes'], true);
        $isNewMode = isset($_GET['novo']) && in_array(strtolower(trim((string) $_GET['novo'])), ['1', 'true', 'sim', 'yes'], true);
        $selectedRegraId = isset($_GET['regra_id']) ? (int) $_GET['regra_id'] : 0;
        $boletimIdGet = isset($_GET['boletim_id']) ? (int) $_GET['boletim_id'] : 0;
        if ($modoArquivo && $selectedRegraId <= 0) {
            $this->redirect('/admin/boletim/arquivo');
            return;
        }
        $regra = null;
        if (!$isNewMode) {
            if ($selectedRegraId > 0) {
                $regra = $this->boletimConfig->getRuleById($selectedRegraId);
            } elseif ($boletimIdGet > 0) {
                $eventosDoBoletim = $this->boletimConfig->listarEventosNotasDoBoletim($boletimIdGet);
                $eventoId = $this->boletimConfig->primeiroEventoNotasSemGeracao($eventosDoBoletim);
                if ($eventoId > 0) {
                    $regra = $this->boletimConfig->getRuleById($eventoId);
                }
            } else {
                $regra = $this->boletimConfig->getUltimaRegraNotas();
            }
            if (!$modoArquivo && $regra && strtolower(trim((string) ($regra['exibir_em'] ?? ''))) === 'boletim') {
                $cadastro = new BoletimCadastroService();
                $bol = $cadastro->model()->findByRegraId((int) ($regra['id'] ?? 0));
                if ($bol) {
                    $this->redirect('/admin/boletins/' . (int) $bol['id'] . '/editar');
                    return;
                }
                $this->redirect('/admin/boletins');
                return;
            }
        }

        if (!$regra) {
            $anoPadrao = (int) date('Y');
            try {
                $anoPadrao = (new BoletimAssistenteWizard())->ferramentas()->anoLetivoPadrao();
            } catch (Throwable $e) {
                // calendário atual se o catálogo da escola falhar
            }
            $regra = [
                'id' => null,
                'nome' => 'Evento padrão da escola',
                'codigo' => null,
                'formula_final' => '',
                'formula_materias_json' => null,
                'materias_ids' => null,
                'series_ids' => null,
                'turmas_ids' => null,
                'exibir_em' => 'notas',
                'finalidade' => 'oficial',
                'boletim_id' => $boletimIdGet > 0 ? $boletimIdGet : null,
                'ano_letivo' => $anoPadrao,
                'bimestre' => null,
                'nota_minima_aprovacao' => 6.0,
                'usar_resultado_aprovacao' => 1,
                'vis_aluno' => 1,
                'vis_pais' => 1,
                'vis_coordenacao' => 1,
                'round_mode' => 'none',
                'extras_json' => null,
                'componentes' => [],
            ];
            $selectedRegraId = 0;
        } elseif ($selectedRegraId <= 0) {
            $selectedRegraId = (int) ($regra['id'] ?? 0);
        }
        $regra = $this->aplicarRascunhoAssistenteSessao($regra, $isNewMode, $selectedRegraId);
        $regra['materias_ids_array'] = $this->parseMateriasIdsFromRegra($regra);
        $regra['formula_materias_map'] = $this->parseFormulaMateriasMapFromRegra($regra);
        $regra['series_ids_array'] = $this->parseSeriesIdsFromRegra($regra);
        $regra['turmas_ids_array'] = $this->parseTurmasIdsFromRegra($regra);

        $alunos = array_slice($this->resolveAlunosVinculadosRegra($regra), 0, 400);
        $blocosProvas = $this->boletimConfig->getAvailableExamBlocks(300);
        $materias = $this->boletimConfig->getAvailableSubjects(300);
        $series = $this->boletimConfig->getAvailableSeries(300);
        $turmas = $this->boletimConfig->getAvailableClasses(1000);
        $regrasCatalogo = $this->boletimConfig->listRulesCatalog(300);
        $faltasEventosCatalogo = (new SchoolAbsence())->listEventos(300);
        $anosLetivosCatalogo = $this->listarAnosLetivosCatalogo();

        $selectedAlunoId = isset($_GET['aluno_id']) ? (int) $_GET['aluno_id'] : 0;
        $dataInicio = $this->normalizarDataYmdOpcional((string) ($_GET['data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataYmdOpcional((string) ($_GET['data_fim'] ?? ''));
        if ($dataInicio === null || $dataFim === null) {
            $regraIni = $this->normalizarDataYmdOpcional((string) ($regra['default_data_inicio'] ?? ''));
            $regraFim = $this->normalizarDataYmdOpcional((string) ($regra['default_data_fim'] ?? ''));
            if ($regraIni !== null && $regraFim !== null) {
                $dataInicio = $regraIni;
                $dataFim = $regraFim;
            } else {
                $rangePadrao = $this->periodoToRange($this->periodoDefault());
                $dataInicio = substr((string) ($rangePadrao['inicio'] ?? ''), 0, 10) ?: date('Y-01-01');
                $dataFim = substr((string) ($rangePadrao['fim'] ?? ''), 0, 10) ?: date('Y-m-d');
            }
        }
        if ($dataInicio > $dataFim) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }

        $periodoRef = trim((string) ($_GET['periodo_ref'] ?? ''));
        if ($periodoRef === '') {
            $periodoRef = $this->buildPeriodoRefFromDateRange($dataInicio, $dataFim);
        }

        $simulacao = null;
        if ($selectedAlunoId > 0 && !empty($regra['componentes'])) {
            // Igual ao Configurar Notas: provas/jornada/faltas vêm do evento salvo;
            // o rascunho do assistente só manda fórmula/layout ainda não gravados.
            $regra = $this->mesclarFontesSalvasNaRegraParaSimulacao($regra);
            // Demonstrativo: matérias soltas + linha-mãe do group_line (ex.: Língua Portuguesa)
            // com filhos aninhados. Boletim: só a linha agrupada.
            $simulacao = $this->simularRegraAluno(
                $regra,
                $selectedAlunoId,
                $periodoRef,
                $dataInicio,
                $dataFim,
                [],
                false,
                true
            );

            // Vista "Boletim": mesma matriz com group_line forçado (aplicar_em=boletim).
            try {
                $simBoletim = $this->simularRegraAluno(
                    $regra,
                    $selectedAlunoId,
                    $periodoRef,
                    $dataInicio,
                    $dataFim,
                    [],
                    true
                );
                $matrizBoletim = is_array($simBoletim) ? ($simBoletim['matriz_materias'] ?? null) : null;
                if (is_array($simulacao) && is_array($matrizBoletim)) {
                    $simulacao['matriz_materias_boletim'] = $matrizBoletim;
                }
            } catch (Throwable $e) {
                error_log('BoletimConfig simulação boletim agrupado aluno #' . $selectedAlunoId . ': ' . $e->getMessage());
            }

            if (is_array($simulacao)) {
                $simulacao = $this->montarMatrizDemonstrativoComGrupoHierarquico($simulacao, $regra);
            }

            // Persistir a simulação como PREVIEW para o aluno selecionado.
            // O preview NÃO é exibido para aluno/pais/coordenação (filtrado por preview=0
            // em getGeneratedBoletinsByAluno / getGeneratedBoletimByAlunoAndRegra).
            // Só vira oficial quando o admin clica em "Gerar boletins de todos os alunos vinculados".
            $regraIdParaPreview = (int) ($regra['id'] ?? 0);
            $matriz = is_array($simulacao) ? ($simulacao['matriz_materias'] ?? null) : null;
            if ($regraIdParaPreview > 0 && is_array($matriz)) {
                $colunasPreview = is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
                // Preview persistido usa a vista Boletim (agrupada) quando existir.
                $matrizPersistir = is_array($simulacao['matriz_materias_boletim'] ?? null)
                    ? $simulacao['matriz_materias_boletim']
                    : $matriz;
                $linhasPreview = is_array($matrizPersistir['linhas'] ?? null) ? $matrizPersistir['linhas'] : [];
                try {
                    $this->boletimConfig->replaceGeneratedResultsForAluno(
                        $regraIdParaPreview,
                        $selectedAlunoId,
                        $periodoRef,
                        $dataInicio,
                        $dataFim,
                        $colunasPreview,
                        $linhasPreview,
                        true
                    );
                } catch (Throwable $e) {
                    error_log('BoletimConfig preview save aluno #' . $selectedAlunoId . ': ' . $e->getMessage());
                }
            }
        }

        // [DEBUG TEMPORARIO] Dump na tela: acesse a mesma URL do boletim com &dbg_redacao=1
        if (isset($_GET['dbg_redacao'])) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<pre style="background:#fff;color:#000;padding:16px;font-size:13px;border:3px solid red;white-space:pre-wrap;">';
            echo "BOLETIM_DEBUG_V3 (codigo novo esta no ar)\n";
            echo 'regra=' . htmlspecialchars((string) ($regra['codigo'] ?? '?'))
                . ' exibir_em=' . htmlspecialchars((string) ($regra['exibir_em'] ?? '?'))
                . ' aluno_id=' . (int) $selectedAlunoId . "\n\n";
            $matrizDbg = is_array($simulacao) ? ($simulacao['matriz_materias'] ?? null) : null;
            if (!is_array($matrizDbg)) {
                echo "SEM matriz_materias (aluno sem componentes/seleção?).\n";
            } else {
                echo "COLUNAS: ";
                foreach ((array) ($matrizDbg['colunas'] ?? []) as $cDbg) {
                    echo htmlspecialchars((string) ($cDbg['codigo'] ?? '')) . '("' . htmlspecialchars((string) ($cDbg['nome'] ?? '')) . '") ';
                }
                echo "\n\n";
                $achouRedacao = false;
                foreach ((array) ($matrizDbg['linhas'] ?? []) as $lDbg) {
                    $nomeDbg = (string) ($lDbg['materia_nome'] ?? '');
                    if (stripos($nomeDbg, 'reda') === false && $this->canonicalMateriaNomeKey($nomeDbg) !== 'redacao') {
                        continue;
                    }
                    $achouRedacao = true;
                    echo 'LINHA mid=' . (int) ($lDbg['materia_id'] ?? 0)
                        . ' nome="' . htmlspecialchars($nomeDbg) . '"'
                        . ' resumo=' . var_export($lDbg['nota_resumo'] ?? null, true) . "\n";
                    foreach ((array) ($lDbg['notas'] ?? []) as $kDbg => $vDbg) {
                        echo '    ' . htmlspecialchars((string) $kDbg) . ' = ' . var_export($vDbg, true) . "\n";
                    }
                }
                if (!$achouRedacao) {
                    echo "NENHUMA linha de Redacao na matriz (foi agrupada/ocultada antes da montagem).\n";
                }
            }
            echo '</pre>';
            exit;
        }

        $data = [
            'title' => 'Evento de Notas - EducaTudo',
            'page_title' => 'Evento de Notas',
            'user' => $user,
            'current_page' => 'boletim_config',
            'csrf_token' => $this->generateCsrfToken(),
            'regra' => $regra,
            'alunos' => $alunos,
            'blocos_provas' => $blocosProvas,
            'materias' => $materias,
            'series' => $series,
            'turmas' => $turmas,
            'regras_catalogo' => $regrasCatalogo,
            'faltas_eventos_catalogo' => $faltasEventosCatalogo,
            'anos_letivos_catalogo' => $anosLetivosCatalogo,
            'selected_regra_id' => (int) ($regra['id'] ?? 0),
            'selected_aluno_id' => $selectedAlunoId,
            'periodo_ref' => $periodoRef,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'simulacao' => $simulacao,
            'flash_message' => $_SESSION['boletim_flash'] ?? '',
            'flash_type' => $_SESSION['boletim_flash_type'] ?? 'success',
            'somente_tabela' => $somenteTabela,
            'modo_arquivo' => $modoArquivo,
            'boletim_assistente_disponivel' => $this->boletimAssistenteDisponivel(),
            'geracao_em_andamento' => $this->boletimConfig->temGeracaoEmAndamento((int) ($regra['id'] ?? 0)),
            'alunos_travados' => ((int) ($regra['id'] ?? 0) > 0)
                ? $this->boletimConfig->listarAlunosTravados((int) $regra['id'], $periodoRef)
                : [],
            'aluno_travado' => ($selectedAlunoId > 0 && (int) ($regra['id'] ?? 0) > 0)
                ? $this->boletimConfig->alunoEstaTravado((int) $regra['id'], $selectedAlunoId, $periodoRef)
                : false,
            'versoes_aluno' => ($selectedAlunoId > 0 && (int) ($regra['id'] ?? 0) > 0)
                ? $this->boletimConfig->listarVersoesAluno((int) $regra['id'], $selectedAlunoId, $periodoRef)
                : [],
            'grupos_regras_notas' => $this->listarGruposRegrasNotasCatalogo(),
            'grupo_regras_notas_id' => $this->grupoRegrasNotasIdDaRegra($regra),
            'semanas_periodo' => $this->semanasPeriodoDaRegra($regra),
            'destinos_quadro' => $this->destinosQuadroDaRegra($regra),
            'agrupamentos_componentes' => $this->listarAgrupamentosComponentesCatalogo(),
            'boletins_cadastro' => $this->listarBoletinsCadastro(),
        ];

        if ($somenteTabela) {
            $this->view('admin/boletim/index', $data);
        } else {
            $this->viewWithLayout('admin', 'admin/boletim/index', $data);
        }

        unset($_SESSION['boletim_flash'], $_SESSION['boletim_flash_type']);
    }

    public function assistente(): void
    {
        $user = $this->auth->getUser();
        $selectedRegraId = isset($_GET['regra_id']) ? (int) $_GET['regra_id'] : 0;
        $boletimId = isset($_GET['boletim_id']) ? (int) $_GET['boletim_id'] : 0;
        $voltarBoletins = $boletimId > 0
            || strtolower(trim((string) ($_GET['voltar'] ?? ''))) === 'boletins';
        $estadoInicial = null;
        $catalogoInicial = null;
        $rascunhoInicial = null;
        $resumoInicial = null;
        $errosIniciais = [];
        $formulasIniciais = [];
        $previewInicial = null;
        $avisoInicial = null;

        try {
            $wizard = new BoletimAssistenteWizard();
            $formSeed = $boletimId > 0 ? ['boletim_id' => $boletimId] : null;
            $estadoInicial = $wizard->estadoPadrao($formSeed, $selectedRegraId > 0 ? $selectedRegraId : null);
            if ($selectedRegraId <= 0 && is_array($estadoInicial)) {
                $selectedRegraId = (int) ($estadoInicial['regra_id'] ?? 0);
            }
            $catalogoInicial = $wizard->catalogo();
            if ($selectedRegraId > 0) {
                $montado = $wizard->enriquecerSaida($wizard->montar($estadoInicial));
                $estadoInicial = $montado['estado'] ?? $estadoInicial;
                $rascunhoInicial = $montado['rascunho'] ?? null;
                $resumoInicial = isset($montado['resumo']) ? (string) $montado['resumo'] : null;
                $errosIniciais = is_array($montado['erros'] ?? null) ? $montado['erros'] : [];
                $formulasIniciais = is_array($montado['formulas_disponiveis'] ?? null) ? $montado['formulas_disponiveis'] : [];
                $previewInicial = $montado['preview'] ?? null;
                if (!is_array($rascunhoInicial) || empty($rascunhoInicial['componentes'])) {
                    $avisoInicial = 'Não consegui carregar a configuração do evento #' . $selectedRegraId . '. Confira se ele existe e está ativo neste ambiente/escola.';
                }
            }
        } catch (Throwable $e) {
            error_log('BoletimConfigController assistente estado inicial: ' . $e->getMessage());
            if ($selectedRegraId > 0) {
                $avisoInicial = 'Não consegui carregar a configuração do evento #' . $selectedRegraId . ' neste ambiente.';
            }
        }

        $nomeEvento = is_array($estadoInicial) ? trim((string) ($estadoInicial['nome'] ?? '')) : '';
        $tituloEvento = $nomeEvento !== '' ? $nomeEvento : 'Evento de Notas';
        $data = [
            'title' => $tituloEvento . ' - EducaTudo',
            'page_title' => $tituloEvento,
            'user' => $user,
            'current_page' => 'boletim_config',
            'csrf_token' => $this->generateCsrfToken(),
            'selected_regra_id' => $selectedRegraId,
            'boletim_id' => $boletimId,
            'voltar_boletins' => $voltarBoletins,
            'boletim_assistente_disponivel' => $this->boletimAssistenteDisponivel(),
            'boletim_assistente_estado_inicial' => $estadoInicial,
            'boletim_assistente_catalogo_inicial' => $catalogoInicial,
            'boletim_assistente_rascunho_inicial' => $rascunhoInicial,
            'boletim_assistente_resumo_inicial' => $resumoInicial,
            'boletim_assistente_erros_iniciais' => $errosIniciais,
            'boletim_assistente_formulas_iniciais' => $formulasIniciais,
            'boletim_assistente_preview_inicial' => $previewInicial,
            'boletim_assistente_aviso_inicial' => $avisoInicial,
            'boletim_config_versoes' => $selectedRegraId > 0
                ? $this->boletimConfig->listarVersoesConfiguracao($selectedRegraId, 8)
                : [],
        ];

        $this->viewWithLayout('admin', 'admin/boletim/assistente', $data);
    }

    /**
     * GET /admin/boletim-configuracao/gerados
     *
     * Lista paginada dos boletins gerados (agrupados por aluno + regra + período)
     * para que o admin/coordenação possa inspecionar e remover registros antigos
     * que ficaram no banco (testes, regras descontinuadas, etc.).
     */
    public function boletinsGerados(): void
    {
        $user = $this->auth->getUser();

        $regraId = isset($_GET['regra_id']) ? (int) $_GET['regra_id'] : 0;
        $alunoId = isset($_GET['aluno_id']) ? (int) $_GET['aluno_id'] : 0;
        $alunoQ = trim((string) ($_GET['aluno_q'] ?? ''));
        $exibirEm = strtolower(trim((string) ($_GET['exibir_em'] ?? '')));
        if (!in_array($exibirEm, ['boletim', 'notas'], true)) {
            $exibirEm = '';
        }
        $previewFilter = strtolower(trim((string) ($_GET['preview'] ?? 'all')));
        if (!in_array($previewFilter, ['0', '1', 'all'], true)) {
            $previewFilter = 'all';
        }
        $atualizadoDe = trim((string) ($_GET['atualizado_de'] ?? ''));
        $atualizadoAte = trim((string) ($_GET['atualizado_ate'] ?? ''));
        $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
        if ($perPage < 10) { $perPage = 10; }
        if ($perPage > 200) { $perPage = 200; }
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $offset = ($page - 1) * $perPage;

        $filters = [
            'regra_id' => $regraId,
            'aluno_id' => $alunoId,
            'aluno_q' => $alunoQ,
            'exibir_em' => $exibirEm,
            'preview' => $previewFilter,
            'atualizado_de' => $atualizadoDe,
            'atualizado_ate' => $atualizadoAte,
        ];

        $result = $this->boletimConfig->listGeneratedBoletinsAdmin($perPage, $offset, $filters);
        $rows = (array) ($result['rows'] ?? []);
        $total = (int) ($result['total'] ?? 0);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        if ($totalPages < 1) { $totalPages = 1; }

        $data = [
            'title' => 'Versão de Notas - EducaTudo',
            'page_title' => 'Painel Administrativo',
            'user' => $user,
            'current_page' => 'boletim_config',
            'csrf_token' => $this->generateCsrfToken(),
            'regras_catalogo' => $this->boletimConfig->listRulesCatalog(300),
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'filters' => [
                'regra_id' => $regraId,
                'aluno_id' => $alunoId,
                'aluno_q' => $alunoQ,
                'exibir_em' => $exibirEm,
                'preview' => $previewFilter,
                'atualizado_de' => $atualizadoDe,
                'atualizado_ate' => $atualizadoAte,
            ],
            'flash_message' => $_SESSION['boletim_flash'] ?? '',
            'flash_type' => $_SESSION['boletim_flash_type'] ?? 'success',
        ];

        $this->viewWithLayout('admin', 'admin/boletim/gerados', $data);
        unset($_SESSION['boletim_flash'], $_SESSION['boletim_flash_type']);
    }

    /**
     * GET /admin/boletim-configuracao/gerados/exportar?formato=json|excel|pdf
     *
     * Exporta todos os boletins do filtro atual, com uma linha por matéria e todas as notas.
     */
    public function exportarBoletinsGerados(): void
    {
        $formato = strtolower(trim((string) ($_GET['formato'] ?? '')));
        if (!in_array($formato, ['json', 'excel', 'pdf'], true)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Escolha JSON, Excel ou PDF.';
            return;
        }

        $exibirEm = strtolower(trim((string) ($_GET['exibir_em'] ?? '')));
        if (!in_array($exibirEm, ['boletim', 'notas'], true)) {
            $exibirEm = '';
        }
        $previewFilter = strtolower(trim((string) ($_GET['preview'] ?? 'all')));
        if (!in_array($previewFilter, ['0', '1', 'all'], true)) {
            $previewFilter = 'all';
        }
        $filters = [
            'regra_id' => isset($_GET['regra_id']) ? (int) $_GET['regra_id'] : 0,
            'aluno_id' => isset($_GET['aluno_id']) ? (int) $_GET['aluno_id'] : 0,
            'aluno_q' => trim((string) ($_GET['aluno_q'] ?? '')),
            'exibir_em' => $exibirEm,
            'preview' => $previewFilter,
            'atualizado_de' => trim((string) ($_GET['atualizado_de'] ?? '')),
            'atualizado_ate' => trim((string) ($_GET['atualizado_ate'] ?? '')),
        ];

        $pacote = $this->boletimConfig->listarNotasExportacaoGerados($filters);
        require_once __DIR__ . '/../../Services/BoletimGeradosExportacao.php';
        BoletimGeradosExportacao::enviar(
            $formato,
            (array) ($pacote['boletins'] ?? []),
            !empty($pacote['truncado'])
        );
        exit;
    }

    /**
     * GET /admin/boletim-configuracao/gerados/preview
     *
     * Retorna apenas o HTML do partial `boletins_gerados.php` para um par
     * (aluno, regra, período), pronto para ser injetado em um modal/expander
     * via fetch.
     */
    public function boletimGeradoPreview(): void
    {
        $alunoId = (int) ($_GET['aluno_id'] ?? 0);
        $regraId = (int) ($_GET['regra_id'] ?? 0);
        $periodoRef = trim((string) ($_GET['periodo_ref'] ?? ''));
        $versao = isset($_GET['versao']) ? (int) $_GET['versao'] : 0;

        header('Content-Type: text/html; charset=utf-8');

        if ($alunoId <= 0 || $regraId <= 0 || $periodoRef === '') {
            http_response_code(400);
            echo '<div class="p-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">Parâmetros inválidos.</div>';
            return;
        }

        $evento = $this->boletimConfig->getGeneratedBoletimAdmin(
            $alunoId,
            $regraId,
            $periodoRef,
            $versao > 0 ? $versao : null
        );
        if (!$evento) {
            echo '<div class="p-4 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg">Nenhum dado encontrado para esse boletim.</div>';
            return;
        }

        $regraPreview = $this->boletimConfig->getRuleById($regraId);
        $ehVigente = (int) ($evento['vigente'] ?? 0) === 1 || (int) ($evento['preview'] ?? 0) === 1;
        if ($ehVigente && is_array($regraPreview) && !empty($regraPreview['componentes'])) {
            $iniEvt = $this->normalizarDataYmdOpcional((string) ($evento['data_inicio'] ?? ''));
            $fimEvt = $this->normalizarDataYmdOpcional((string) ($evento['data_fim'] ?? ''));
            if ($iniEvt === null || $fimEvt === null) {
                $iniEvt = $this->normalizarDataYmdOpcional((string) ($regraPreview['default_data_inicio'] ?? ''));
                $fimEvt = $this->normalizarDataYmdOpcional((string) ($regraPreview['default_data_fim'] ?? ''));
            }
            try {
                $matrizAtual = $this->matrizDemonstrativoAtual($regraPreview, $alunoId, $periodoRef, $iniEvt, $fimEvt);
                if (!empty($matrizAtual['linhas']) && is_array($matrizAtual['linhas'])) {
                    if (!empty($matrizAtual['colunas']) && is_array($matrizAtual['colunas'])) {
                        $evento['colunas'] = $matrizAtual['colunas'];
                    }
                    $evento['linhas'] = $matrizAtual['linhas'];
                }
            } catch (Throwable $e) {
                error_log('Preview vigente regra #' . $regraId . ': ' . $e->getMessage());
            }
        } elseif (is_array($regraPreview) && !empty($regraPreview['componentes']) && !empty($evento['linhas'])) {
            try {
                $simulacaoPreview = $this->montarMatrizDemonstrativoComGrupoHierarquico([
                    'matriz_materias' => [
                        'colunas' => $evento['colunas'] ?? [],
                        'linhas' => $evento['linhas'],
                    ],
                ], $regraPreview);
                $matrizPreview = is_array($simulacaoPreview['matriz_materias'] ?? null) ? $simulacaoPreview['matriz_materias'] : [];
                if (!empty($matrizPreview['linhas']) && is_array($matrizPreview['linhas'])) {
                    $evento['linhas'] = $matrizPreview['linhas'];
                }
            } catch (Throwable $e) {
                error_log('Preview gerado regra #' . $regraId . ': ' . $e->getMessage());
            }
        }

        $boletim_versoes = ((int) ($evento['preview'] ?? 0) === 1)
            ? []
            : $this->boletimConfig->listarVersoesAluno($regraId, $alunoId, $periodoRef);
        $idExibido = (int) ($evento['geracao_id'] ?? 0);
        if ($idExibido <= 0) {
            $idExibido = (int) ($evento['id'] ?? 0);
        }
        $versaoExibida = (int) ($evento['versao'] ?? 0);
        foreach ($boletim_versoes as $versaoItem) {
            if (!is_array($versaoItem) || (int) ($versaoItem['versao'] ?? 0) !== $versaoExibida) {
                continue;
            }
            if ((int) ($versaoItem['config_id'] ?? 0) > 0) {
                $idExibido = (int) $versaoItem['config_id'];
                $versaoExibida = (int) ($versaoItem['config_versao'] ?? $versaoExibida);
            }
            break;
        }

        $rotuloBimestre = PeriodoLetivo::rotuloBoletim(
            (int) ($evento['ano_letivo'] ?? 0),
            (int) ($evento['bimestre'] ?? 0),
            (string) ($evento['regra_nome'] ?? '')
        );
        $cabecalho = sprintf(
            '<div class="mb-3 text-sm text-gray-700"><strong>Aluno:</strong> %s%s &middot; <strong>Regra:</strong> %s &middot; <strong>Bimestre:</strong> %s%s%s</div>',
            htmlspecialchars((string) ($evento['aluno_nome'] ?? ''), ENT_QUOTES, 'UTF-8'),
            $evento['aluno_ra'] ? ' (RA ' . htmlspecialchars((string) $evento['aluno_ra'], ENT_QUOTES, 'UTF-8') . ')' : '',
            htmlspecialchars((string) ($evento['regra_nome'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($rotuloBimestre, ENT_QUOTES, 'UTF-8'),
            ((int) ($evento['preview'] ?? 0) === 1)
                ? ' <span class="inline-block ml-2 px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800">preview</span>'
                : '',
            ($versaoExibida > 0)
                ? ' <span class="inline-block ml-2 px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-700">'
                    . ($idExibido > 0 ? 'ID ' . $idExibido . ' · ' : '')
                    . 'versão ' . $versaoExibida . (((int) ($evento['vigente'] ?? 0) === 1) ? ' vigente' : '') . '</span>'
                : ''
        );
        $boletim_versoes_fallback = (string) ($evento['updated_at'] ?? '');
        $boletim_versoes_compact = false;
        $boletim_versao_aberta = (int) ($evento['versao'] ?? 0);

        // Reusa o partial existente, sem botão de remover (a tela já tem o seu próprio).
        $boletins_gerados = [$evento];
        $boletim_pode_excluir = false;
        $boletim_aluno_id = 0;

        echo $cabecalho;
        if ((int) ($evento['preview'] ?? 0) !== 1) {
            require __DIR__ . '/../../Views/partials/boletim_versoes_historico.php';
        }
        require __DIR__ . '/../../Views/partials/boletins_gerados.php';
    }

    /**
     * POST /admin/boletim-configuracao/gerados/excluir
     *
     * Remove um boletim gerado específico do banco. Aceita escopo:
     *  - aluno + regra + periodo_ref  → remove apenas aquele lançamento
     *  - aluno + regra (sem período)  → remove TODOS os períodos daquele par
     */
    public function excluirBoletimGeradoAdmin(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $payload = $_POST;
        if (empty($payload)) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
        }

        $token = (string) ($payload['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!$this->verifyCsrfToken($token)) {
            http_response_code(419);
            echo json_encode(['error' => 'Token CSRF inválido'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $alunoId = (int) ($payload['aluno_id'] ?? 0);
        $regraId = (int) ($payload['regra_id'] ?? 0);
        $periodoRef = trim((string) ($payload['periodo_ref'] ?? ''));

        if ($alunoId <= 0 || $regraId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetros inválidos'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        try {
            if ($periodoRef !== '') {
                $removidos = $this->boletimConfig->deleteGeneratedResultForPeriodo($alunoId, $regraId, $periodoRef);
                $escopo = 'periodo';
            } else {
                $removidos = $this->boletimConfig->deleteGeneratedResultsForAluno($alunoId, $regraId);
                $escopo = 'todos_periodos';
            }
            echo json_encode([
                'success' => true,
                'removidos' => $removidos,
                'aluno_id' => $alunoId,
                'regra_id' => $regraId,
                'periodo_ref' => $periodoRef,
                'escopo' => $escopo,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            error_log('BoletimConfigController excluirBoletimGeradoAdmin: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao remover boletim'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * POST /admin/boletim-configuracao/gerados/excluir-lote
     *
     * Remove vários boletins gerados em uma única chamada. O payload deve conter
     * `itens` como JSON ou um array já decodificado, no formato:
     *   [{"aluno_id":1,"regra_id":2,"periodo_ref":"RANGE:..."}, ...]
     */
    public function excluirBoletimGeradoLote(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $payload = $_POST;
        if (empty($payload)) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
        }

        $token = (string) ($payload['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!$this->verifyCsrfToken($token)) {
            http_response_code(419);
            echo json_encode(['error' => 'Token CSRF inválido'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $itensRaw = $payload['itens'] ?? null;
        if (is_string($itensRaw)) {
            $decoded = json_decode($itensRaw, true);
            $itensRaw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($itensRaw) || empty($itensRaw)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nenhum item informado'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        if (count($itensRaw) > 500) {
            http_response_code(400);
            echo json_encode(['error' => 'Máximo de 500 itens por chamada'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        try {
            $result = $this->boletimConfig->deleteGeneratedResultsLote($itensRaw);
            echo json_encode([
                'success' => true,
                'itens' => (int) ($result['itens'] ?? 0),
                'removidos' => (int) ($result['removidos'] ?? 0),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            error_log('BoletimConfigController excluirBoletimGeradoLote: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao remover em lote'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * Lista jornadas ativas (escopo turmas da escola) para multiselect na regra do boletim.
     */
    public function jornadasJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $jb = new JourneyBoletimLancamento();
        $turmas = $jb->listarTurmasAtivas();
        $turmaIds = [];
        foreach ($turmas as $t) {
            $tid = (int) ($t['id'] ?? 0);
            if ($tid > 0) {
                $turmaIds[] = $tid;
            }
        }
        $turmaIds = array_values(array_unique($turmaIds));
        if ($turmaIds === []) {
            echo json_encode(['jornadas' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $dataIniFiltro = $this->normalizarDataYmdOpcional((string) ($_GET['data_ini'] ?? ''));
        $dataFimFiltro = $this->normalizarDataYmdOpcional((string) ($_GET['data_fim'] ?? ''));
        if ($dataIniFiltro !== null && $dataFimFiltro !== null && $dataIniFiltro > $dataFimFiltro) {
            [$dataIniFiltro, $dataFimFiltro] = [$dataFimFiltro, $dataIniFiltro];
        }

        // Carrega candidatas sem filtro de data e aplica filtro manual
        // usando início/fim da própria jornada.
        $rows = $jb->listarJornadasCandidatas($turmaIds, null, null);
        $db = Database::getInstance();
        $materiaIds = [];
        $professorIds = [];
        foreach ($rows as $j) {
            $mid = (int) ($j['materia_id'] ?? 0);
            $pid = (int) ($j['professor_id'] ?? 0);
            if ($mid > 0) {
                $materiaIds[$mid] = true;
            }
            if ($pid > 0) {
                $professorIds[$pid] = true;
            }
        }

        $materiasMap = [];
        if (!empty($materiaIds)) {
            $ids = array_keys($materiaIds);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $rowsMat = $db->fetchAll(
                "SELECT id, nome FROM jornadas_materias WHERE id IN ($ph)",
                $ids
            ) ?: [];
            foreach ($rowsMat as $m) {
                $mid = (int) ($m['id'] ?? 0);
                if ($mid > 0) {
                    $materiasMap[$mid] = (string) ($m['nome'] ?? '');
                }
            }
            $faltantes = array_values(array_filter($ids, static function (int $id) use ($materiasMap): bool {
                return !isset($materiasMap[$id]);
            }));
            if (!empty($faltantes)) {
                $ph2 = implode(',', array_fill(0, count($faltantes), '?'));
                $rowsMat2 = $db->fetchAll(
                    "SELECT id, nome FROM materias WHERE id IN ($ph2)",
                    $faltantes
                ) ?: [];
                foreach ($rowsMat2 as $m2) {
                    $mid2 = (int) ($m2['id'] ?? 0);
                    if ($mid2 > 0 && !isset($materiasMap[$mid2])) {
                        $materiasMap[$mid2] = (string) ($m2['nome'] ?? '');
                    }
                }
            }
        }

        $professoresMap = [];
        if (!empty($professorIds)) {
            $idsP = array_keys($professorIds);
            $phP = implode(',', array_fill(0, count($idsP), '?'));
            $rowsProf = $db->fetchAll(
                "SELECT id, nome FROM professores WHERE id IN ($phP)",
                $idsP
            ) ?: [];
            foreach ($rowsProf as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $nome = trim((string) ($p['nome'] ?? ''));
                if ($pid > 0) {
                    $primeiro = $nome === '' ? '' : explode(' ', $nome)[0];
                    $professoresMap[$pid] = $primeiro;
                }
            }
        }

        $out = [];
        $seen = [];
        foreach ($rows as $j) {
            $jid = (int) ($j['id'] ?? 0);
            if ($jid <= 0 || isset($seen[$jid])) {
                continue;
            }
            $estrutura = json_decode((string) ($j['estrutura'] ?? ''), true);
            $dataIniJornada = is_array($estrutura) ? ($estrutura['data_inicio'] ?? null) : null;
            $dataFimJornada = is_array($estrutura) ? ($estrutura['data_fim'] ?? null) : null;
            $dataIniJornada = $this->normalizarDataYmdOpcional((string) ($dataIniJornada ?? ''));
            $dataFimJornada = $this->normalizarDataYmdOpcional((string) ($dataFimJornada ?? ''));

            // Entre datas baseado no intervalo da jornada (início e encerramento).
            // Se faltar uma ponta, usa a outra; se faltar ambas e há filtro, ignora.
            $iniRef = $dataIniJornada ?? $dataFimJornada;
            $fimRef = $dataFimJornada ?? $dataIniJornada;
            if (($dataIniFiltro !== null || $dataFimFiltro !== null) && ($iniRef === null || $fimRef === null)) {
                continue;
            }
            if ($dataIniFiltro !== null && $iniRef !== null && $iniRef < $dataIniFiltro) {
                continue;
            }
            if ($dataFimFiltro !== null && $fimRef !== null && $fimRef > $dataFimFiltro) {
                continue;
            }

            $seen[$jid] = true;
            $mid = (int) ($j['materia_id'] ?? 0);
            $pid = (int) ($j['professor_id'] ?? 0);
            $materiaBase = trim((string) ($materiasMap[$mid] ?? 'SEM MATERIA'));
            $profBase = trim((string) ($professoresMap[$pid] ?? 'SEM PROFESSOR'));
            $dataBase = (string) ($dataFimJornada ?? 'SEM DATA FINAL');
            if ($dataFimJornada !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFimJornada)) {
                $dataBase = date('d/m/Y', strtotime($dataFimJornada));
            }
            $materiaNome = function_exists('mb_strtoupper') ? mb_strtoupper($materiaBase, 'UTF-8') : strtoupper($materiaBase);
            $profPrimeiro = function_exists('mb_strtoupper') ? mb_strtoupper($profBase, 'UTF-8') : strtoupper($profBase);
            $dataFinal = function_exists('mb_strtoupper') ? mb_strtoupper($dataBase, 'UTF-8') : strtoupper($dataBase);
            $out[] = [
                'id' => $jid,
                'titulo' => (string) ($j['titulo'] ?? ('Jornada #' . $jid)),
                'turma_id' => (int) ($j['turma_id'] ?? 0),
                'created_at' => (string) ($j['created_at'] ?? ''),
                'materia_nome' => (string) ($materiaNome ?? ''),
                'professor_nome' => (string) ($profPrimeiro ?? ''),
                'data_fim_jornada' => (string) ($dataFimJornada ?? ''),
                'ano_letivo' => $j['ano_letivo'] !== null ? (int) $j['ano_letivo'] : null,
                'bimestre' => $j['bimestre'] !== null ? (int) $j['bimestre'] : null,
                'rotulo' => '#' . $jid . ' - ' . $materiaNome . ' - ' . $profPrimeiro . ' - ' . $dataFinal,
            ];
        }

        echo json_encode(['jornadas' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function eventoComponentesJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $codigoEvento = trim((string) ($_GET['regra_codigo'] ?? ''));
        if ($codigoEvento === '') {
            echo json_encode(['componentes' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $regra = $this->boletimConfig->getRuleByCode($codigoEvento);
        if (!$regra) {
            echo json_encode(['componentes' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $componentes = [];
        foreach ((array) ($regra['componentes'] ?? []) as $comp) {
            $codigo = trim((string) ($comp['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $componentes[] = [
                'codigo' => $codigo,
                'nome' => trim((string) ($comp['nome'] ?? $codigo)),
            ];
        }

        echo json_encode(['componentes' => $componentes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Checklist pré-geração (item 11/14): roda a auditoria de matéria órfã + evento de
     * origem incompatível, e o indicador de cobertura, antes do usuário clicar em
     * "Gerar boletins de todos os alunos vinculados".
     */
    public function checklistPreGeracao(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $regraId = (int) ($_GET['regra_id'] ?? 0);
        $periodoRef = trim((string) ($_GET['periodo_ref'] ?? ''));
        $dataInicio = $this->normalizarDataYmdOpcional((string) ($_GET['data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataYmdOpcional((string) ($_GET['data_fim'] ?? ''));
        if ($periodoRef === '' && $dataInicio !== null && $dataFim !== null) {
            $periodoRef = $this->buildPeriodoRefFromDateRange($dataInicio, $dataFim);
        }
        if ($periodoRef === '') {
            $periodoRef = $this->periodoDefault();
        }

        $regra = $regraId > 0 ? $this->boletimConfig->getRuleById($regraId) : null;
        if (!$regra) {
            echo json_encode(['erro' => 'Evento não encontrado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $auditoria = $this->auditarConsistenciaRegra($regra);
        $gates = $this->diagnosticarGatesFechamentoRegra($regra);
        $alunos = $this->resolveAlunosVinculadosRegra($regra);
        $cobertura = $this->calcularCoberturaRegra($regra, $alunos, $periodoRef, $dataInicio, $dataFim);

        echo json_encode([
            'total_alunos_escopo' => count($alunos),
            'gates' => $gates,
            'materias_orfas' => $auditoria['materias_orfas'],
            'eventos_incompativeis' => $auditoria['eventos_incompativeis'],
            'cobertura' => $cobertura,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Logs das últimas gerações em massa de um evento (item 15).
     */
    public function logsGeracaoJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $regraId = (int) ($_GET['regra_id'] ?? 0);
        if ($regraId <= 0) {
            echo json_encode(['logs' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $logs = $this->boletimConfig->getLogsGeracaoPorRegra($regraId, 10);
        foreach ($logs as &$log) {
            $log['created_at_fmt'] = !empty($log['created_at']) ? date('d/m/Y H:i', strtotime((string) $log['created_at'])) : '';
        }
        unset($log);

        $geracoes = $this->boletimConfig->listarGeracoesPorRegra($regraId, '', 30);
        foreach ($geracoes as &$g) {
            $g['created_at_fmt'] = !empty($g['created_at']) ? date('d/m/Y H:i', strtotime((string) $g['created_at'])) : '';
        }
        unset($g);

        echo json_encode(['logs' => $logs, 'geracoes' => $geracoes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function geracaoDetalheJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $geracaoId = (int) ($_GET['id'] ?? 0);
        if ($geracaoId <= 0) {
            echo json_encode(['erro' => 'Geração inválida.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $geracao = $this->boletimConfig->findGeracao($geracaoId);
        if (!$geracao) {
            echo json_encode(['erro' => 'Geração não encontrada.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $geracao['created_at_fmt'] = !empty($geracao['created_at']) ? date('d/m/Y H:i', strtotime((string) $geracao['created_at'])) : '';
        $alunos = $this->boletimConfig->listarAlunosDaGeracao($geracaoId);
        $travados = $this->boletimConfig->listarAlunosTravados((int) $geracao['regra_id'], (string) $geracao['periodo_ref']);
        echo json_encode([
            'geracao' => $geracao,
            'alunos' => $alunos,
            'travados' => $travados,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function restaurarGeracao(): void
    {
        $this->assertCsrfOrRedirect();
        $geracaoId = (int) ($_POST['geracao_id'] ?? 0);
        $regraIdPost = (int) ($_POST['regra_id'] ?? 0);
        $geracao = $geracaoId > 0 ? $this->boletimConfig->findGeracao($geracaoId) : null;
        $regraId = (int) ($geracao['regra_id'] ?? $regraIdPost);
        $periodoRef = trim((string) ($geracao['periodo_ref'] ?? ''));
        $qs = ['regra_id' => $regraId];
        if ($periodoRef !== '') {
            $qs['periodo_ref'] = $periodoRef;
        }

        if ($geracao === null || $regraId <= 0 || ($regraIdPost > 0 && $regraIdPost !== $regraId)) {
            $_SESSION['boletim_flash'] = 'Não foi possível voltar para essa versão.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao?' . http_build_query($qs));
            return;
        }
        if ((int) ($geracao['vigente'] ?? 0) === 1) {
            $_SESSION['boletim_flash'] = 'Essa versão já é a vigente.';
            $_SESSION['boletim_flash_type'] = 'info';
            $this->redirect('/admin/boletim-configuracao?' . http_build_query($qs));
            return;
        }

        try {
            $ok = $this->boletimConfig->restaurarGeracaoComoVigente($geracaoId);
        } catch (Throwable $e) {
            error_log('restaurarGeracao: ' . $e->getMessage());
            $ok = false;
        }
        if (!$ok) {
            $_SESSION['boletim_flash'] = 'Não foi possível voltar para essa versão. Ela não tem boletins gravados.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao?' . http_build_query($qs));
            return;
        }

        $alunoIds = [];
        foreach ($this->boletimConfig->listarAlunosDaGeracao($geracaoId) as $alunoGeracao) {
            $alunoId = (int) ($alunoGeracao['aluno_id'] ?? 0);
            if ($alunoId > 0) {
                $alunoIds[] = $alunoId;
            }
        }
        $usuario = $this->auth->getUser();
        if ($alunoIds !== []) {
            $this->sincronizarFichasVidaEscolarLote(
                $alunoIds,
                is_array($usuario) ? $usuario : [],
                $periodoRef,
                $regraId
            );
        }

        $versao = (int) ($geracao['versao'] ?? 0);
        $_SESSION['boletim_flash'] = 'Versão ' . ($versao > 0 ? $versao : $geracaoId) . ' voltou a ser a vigente. O boletim oficial da vida escolar usa esta versão, exceto ficha homologada ou nota lançada de fora. As outras versões continuam no histórico.';
        $_SESSION['boletim_flash_type'] = 'success';
        $this->redirect('/admin/boletim-configuracao?' . http_build_query($qs));
    }

    public function travarAluno(): void
    {
        $this->assertCsrfOrRedirect();
        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $periodoRef = trim((string) ($_POST['periodo_ref'] ?? ''));
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $dataInicio = $this->normalizarDataYmdOpcional((string) ($_POST['data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataYmdOpcional((string) ($_POST['data_fim'] ?? ''));
        $qs = ['regra_id' => $regraId, 'aluno_id' => $alunoId, 'periodo_ref' => $periodoRef];
        if ($dataInicio !== null && $dataFim !== null) {
            $qs['data_inicio'] = $dataInicio;
            $qs['data_fim'] = $dataFim;
        }

        if ($regraId <= 0 || $alunoId <= 0 || $periodoRef === '') {
            $_SESSION['boletim_flash'] = 'Informe evento, aluno e período para travar.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao?' . http_build_query($qs));
            return;
        }

        $user = $this->auth->getUser();
        $ok = $this->boletimConfig->travarAluno(
            $regraId,
            $alunoId,
            $periodoRef,
            $motivo !== '' ? $motivo : 'Ajuste manual — não recalcular em lote',
            (int) ($user['id'] ?? 0) ?: null,
            (string) ($user['nome'] ?? '') ?: null
        );
        $_SESSION['boletim_flash'] = $ok
            ? 'Aluno travado neste período. As próximas gerações em lote não vão recalcular as notas dele.'
            : 'Não foi possível travar este aluno.';
        $_SESSION['boletim_flash_type'] = $ok ? 'success' : 'error';
        $this->redirect('/admin/boletim-configuracao?' . http_build_query($qs));
    }

    public function destravarAluno(): void
    {
        $this->assertCsrfOrRedirect();
        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $periodoRef = trim((string) ($_POST['periodo_ref'] ?? ''));
        $dataInicio = $this->normalizarDataYmdOpcional((string) ($_POST['data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataYmdOpcional((string) ($_POST['data_fim'] ?? ''));
        $qs = ['regra_id' => $regraId, 'periodo_ref' => $periodoRef];
        if ($alunoId > 0) {
            $qs['aluno_id'] = $alunoId;
        }
        if ($dataInicio !== null && $dataFim !== null) {
            $qs['data_inicio'] = $dataInicio;
            $qs['data_fim'] = $dataFim;
        }

        $ok = $this->boletimConfig->destravarAluno($regraId, $alunoId, $periodoRef);
        $_SESSION['boletim_flash'] = $ok
            ? 'Aluno destravado. A próxima geração em lote vai recalcular as notas dele.'
            : 'Este aluno não estava travado.';
        $_SESSION['boletim_flash_type'] = $ok ? 'success' : 'error';
        $this->redirect('/admin/boletim-configuracao?' . http_build_query($qs));
    }

    /**
     * Simulação em lote (item 12): roda a simulação para uma amostra de alunos
     * (uma turma específica, ou até N alunos do escopo do evento) sem gravar nada,
     * só para visualizar antes de gerar em massa.
     */
    public function simularLote(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->assertCsrfOrRedirect();

        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $turmaId = (int) ($_POST['turma_id'] ?? 0);
        $periodoRef = trim((string) ($_POST['periodo_ref'] ?? ''));
        $dataInicio = $this->normalizarDataYmdOpcional((string) ($_POST['data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataYmdOpcional((string) ($_POST['data_fim'] ?? ''));
        if ($periodoRef === '' && $dataInicio !== null && $dataFim !== null) {
            $periodoRef = $this->buildPeriodoRefFromDateRange($dataInicio, $dataFim);
        }
        if ($periodoRef === '') {
            $periodoRef = $this->periodoDefault();
        }

        $regra = $regraId > 0 ? $this->boletimConfig->getRuleById($regraId) : null;
        if (!$regra) {
            echo json_encode(['erro' => 'Evento não encontrado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $alunos = $turmaId > 0
            ? $this->boletimConfig->getStudentsListByClasses([$turmaId], 300)
            : $this->resolveAlunosVinculadosRegra($regra);
        $alunos = array_slice($alunos, 0, 60);

        $codigoFinal = $this->boletimConfig->getComponenteFinalCodigo($regraId) ?? '';
        $resultado = [];
        foreach ($alunos as $aluno) {
            $alunoId = (int) ($aluno['id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            try {
                $sim = $this->simularRegraAluno($regra, $alunoId, $periodoRef, $dataInicio, $dataFim);
                $linhas = $sim['matriz_materias']['linhas'] ?? [];
                $somaFinal = 0.0;
                $qtdFinal = 0;
                $temLacuna = false;
                foreach ($linhas as $linha) {
                    $notas = (array) ($linha['notas'] ?? []);
                    if ($codigoFinal !== '' && isset($notas[$codigoFinal]) && is_numeric($notas[$codigoFinal])) {
                        $somaFinal += (float) $notas[$codigoFinal];
                        $qtdFinal++;
                    }
                    foreach ($notas as $v) {
                        if ($v === null || $v === '') {
                            $temLacuna = true;
                            break;
                        }
                    }
                }
                $resultado[] = [
                    'aluno_id' => $alunoId,
                    'nome' => (string) ($aluno['nome'] ?? ('#' . $alunoId)),
                    'media_final' => $qtdFinal > 0 ? round($somaFinal / $qtdFinal, 2) : null,
                    'tem_lacuna' => $temLacuna,
                ];
            } catch (Throwable $e) {
                $resultado[] = [
                    'aluno_id' => $alunoId,
                    'nome' => (string) ($aluno['nome'] ?? ('#' . $alunoId)),
                    'erro' => $e->getMessage(),
                ];
            }
        }

        echo json_encode([
            'total' => count($resultado),
            'alunos' => $resultado,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function renomearRegra(): void
    {
        $this->assertCsrfOrRedirect();
        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $nome = trim((string) ($_POST['regra_nome'] ?? ''));
        $voltar = '/admin/boletim-configuracao' . ($regraId > 0 ? ('?regra_id=' . $regraId) : '');
        if ($regraId <= 0 || $this->boletimConfig->getRuleById($regraId) === null) {
            $_SESSION['boletim_flash'] = 'Evento não encontrado.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
        }
        if ($nome === '') {
            $_SESSION['boletim_flash'] = 'Informe o título do evento.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect($voltar);
        }
        $this->boletimConfig->atualizarNome($regraId, $nome);
        $_SESSION['boletim_flash'] = 'Título atualizado.';
        $_SESSION['boletim_flash_type'] = 'success';
        $this->redirect($voltar);
    }

    public function salvarRegra()
    {
        $this->assertCsrfOrRedirect();
        $this->guardarRascunhoAssistenteDaSessao($_POST);

        $regraId = isset($_POST['regra_id']) && $_POST['regra_id'] !== '' ? (int) $_POST['regra_id'] : null;
        $nome = trim((string) ($_POST['regra_nome'] ?? ''));
        $codigoRegra = $this->slugEvent((string) ($_POST['regra_codigo'] ?? ''));
        $descricaoCurta = trim((string) ($_POST['regra_descricao_curta'] ?? ''));
        $formulaFinal = trim((string) ($_POST['formula_final'] ?? ''));
        $formulaMateriasJson = trim((string) ($_POST['formula_materias_json'] ?? ''));
        $componentesJson = (string) ($_POST['componentes_json'] ?? '[]');
        $materiasIds = $this->parseMateriasIdsFromPost($_POST['materias_ids'] ?? []);
        $seriesIds = $this->parseSeriesIdsFromPost($_POST['series_ids'] ?? []);
        $turmasIds = $this->parseSeriesIdsFromPost($_POST['turmas_ids'] ?? []);
        $exibirEm = 'notas';
        $boletimIdPost = (int) ($_POST['boletim_id'] ?? 0);
        $finalidade = $this->boletimConfig->normalizeFinalidade($_POST['finalidade'] ?? 'oficial');
        $cadastroBoletim = null;
        $criteriosBol = null;
        if ($boletimIdPost > 0) {
            try {
                $cadastroBoletim = (new BoletimCadastroService())->model()->findById($boletimIdPost);
            } catch (Throwable $e) {
                $cadastroBoletim = null;
            }
            if (!is_array($cadastroBoletim)) {
                $_SESSION['boletim_flash'] = 'Modelo de boletim inválido. Cadastre ou selecione um modelo em Acadêmico → Modelo de Boletim.';
                $_SESSION['boletim_flash_type'] = 'error';
                $this->redirectFalhaConfiguracao($regraId);
            }
        }
        if (is_array($cadastroBoletim)) {
            $finalidade = $this->boletimConfig->normalizeFinalidade($cadastroBoletim['finalidade'] ?? 'oficial');
            $materiasIds = array_map('intval', (array) ($cadastroBoletim['materias_ids'] ?? []));
            $materiasIds = $this->expandirMateriasComFilhos($materiasIds);
            $seriesIds = array_map('intval', (array) ($cadastroBoletim['series_ids'] ?? []));
            $turmasIds = array_map('intval', (array) ($cadastroBoletim['turmas_ids'] ?? []));
            try {
                $criteriosBol = (new BoletimCadastroService())->criteriosDoBoletim($cadastroBoletim);
            } catch (Throwable $e) {
                $criteriosBol = null;
            }
        }
        $anoLetivo = (int) ($_POST['ano_letivo'] ?? 0);
        $bimestre = (int) ($_POST['bimestre'] ?? 0);
        $visAluno = !empty($_POST['vis_aluno']) ? 1 : 0;
        $visPais = !empty($_POST['vis_pais']) ? 1 : 0;
        $visCoordenacao = !empty($_POST['vis_coordenacao']) ? 1 : 0;
        $notaMinimaAprovacaoRaw = trim((string) ($_POST['nota_minima_aprovacao'] ?? ''));
        $notaMinimaAprovacao = $notaMinimaAprovacaoRaw === '' ? null : (float) str_replace(',', '.', $notaMinimaAprovacaoRaw);
        $usarResultadoAprovacao = !empty($_POST['usar_resultado_aprovacao']) ? 1 : 0;
        $roundMode = $this->normalizeRoundMode((string) ($_POST['round_mode'] ?? 'none'));
        $decimalPlaces = ((int) ($_POST['decimal_places'] ?? 2) === 1) ? 1 : 2;
        if (is_array($criteriosBol)) {
            $notaMinimaAprovacao = (float) ($criteriosBol['nota_minima_aprovacao'] ?? 6);
            $roundMode = $this->normalizeRoundMode((string) ($criteriosBol['round_mode'] ?? $roundMode));
            $decimalPlaces = ((int) ($criteriosBol['decimal_places'] ?? $decimalPlaces) === 1) ? 1 : 2;
        }
        $defaultDataInicio = $this->normalizarDataYmdOpcional((string) ($_POST['default_data_inicio'] ?? ''));
        $defaultDataFim = $this->normalizarDataYmdOpcional((string) ($_POST['default_data_fim'] ?? ''));
        if ($defaultDataInicio !== null && $defaultDataFim !== null && $defaultDataInicio > $defaultDataFim) {
            [$defaultDataInicio, $defaultDataFim] = [$defaultDataFim, $defaultDataInicio];
        }

        if ($nome === '') {
            $_SESSION['boletim_flash'] = 'Informe o nome do evento.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirectFalhaConfiguracao($regraId);
        }
        if ($codigoRegra !== '') {
            $donoCodigo = $this->boletimConfig->getRuleByCode($codigoRegra);
            $donoId = is_array($donoCodigo) ? (int) ($donoCodigo['id'] ?? 0) : 0;
            if ($donoId > 0 && ($regraId === null || (int) $regraId <= 0)) {
                $regraId = $donoId;
            } elseif ($donoId > 0 && (int) $regraId > 0 && $donoId !== (int) $regraId) {
                $eventoAtual = $this->boletimConfig->getRuleById((int) $regraId);
                $codigoAtual = is_array($eventoAtual) ? trim((string) ($eventoAtual['codigo'] ?? '')) : '';
                if ($codigoAtual !== '') {
                    $codigoRegra = $codigoAtual;
                }
            }
        }
        if ($anoLetivo < 2000 || $anoLetivo > 2100) {
            $_SESSION['boletim_flash'] = 'Selecione um ano letivo válido.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirectFalhaConfiguracao($regraId);
        }
        if ($exibirEm === 'notas' && !PeriodoLetivo::numeroValido($anoLetivo, $bimestre)) {
            $_SESSION['boletim_flash'] = PeriodoLetivo::mensagemNumeroInvalido($anoLetivo);
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirectFalhaConfiguracao($regraId);
        }
        $componentes = json_decode($componentesJson, true);
        if (!is_array($componentes) || empty($componentes)) {
            $_SESSION['boletim_flash'] = 'Adicione pelo menos um componente no fluxo do boletim.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirectFalhaConfiguracao($regraId);
        }

        $componentesNormalizados = [];
        foreach ($componentes as $componente) {
            $nomeComp = trim((string) ($componente['nome'] ?? ''));
            if ($nomeComp === '') {
                continue;
            }

            $codigo = trim((string) ($componente['codigo'] ?? ''));
            if ($codigo === '') {
                $codigo = $this->slug($nomeComp);
            }

            $src = $this->normalizeSourceTypeForSave((string) ($componente['source_type'] ?? 'provas_sistema'));
            $cfgJTmp = $src === 'jornadas' ? $this->parseJornadasConfigFromComponente($componente) : null;
            $temFaixasJTmp = $src === 'jornadas' && !empty($cfgJTmp['faixas_percentuais']);
            $blocoNorm = ($src === 'jornadas' || $src === 'calculado' || $src === 'evento_boletim' || $src === 'faltas_evento' || $src === 'nenhuma')
                ? ['bloco_id' => null, 'blocos_ids' => null]
                : $this->normalizeBlocoFieldsForPersist($componente);
            $filtroTitulo = trim((string) ($componente['filtro_titulo'] ?? ''));
            if ($src === 'jornadas' || $src === 'calculado' || $src === 'evento_boletim' || $src === 'faltas_evento' || $src === 'nenhuma') {
                $filtroTitulo = '';
            }

            $componentesNormalizados[] = [
                'codigo' => $codigo,
                'nome' => $nomeComp,
                'source_type' => $src,
                'calc_type' => $this->normalizeCalcType((string) ($componente['calc_type'] ?? 'media')),
                'peso' => (float) ($componente['peso'] ?? 1),
                'filtro_titulo' => $filtroTitulo,
                'bloco_id' => $blocoNorm['bloco_id'],
                'blocos_ids' => $blocoNorm['blocos_ids'],
                'config_json' => $this->encodeComponenteConfigJsonForSave($src, $componente),
                'materia_id' => !empty($componente['materia_id']) ? (int) $componente['materia_id'] : null,
                'materias_ids' => json_encode($this->parseMateriasIdsFromComponente($componente), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'materia_unica' => !empty($componente['materia_unica']) ? 1 : 0,
                'materia_unica_modo' => $this->normalizeMateriaUnicaModo($componente['materia_unica_modo'] ?? null),
                'usar_percentual' => ($src === 'jornadas' && $temFaixasJTmp) ? 0 : (!empty($componente['usar_percentual']) ? 1 : 0),
                'escala_max' => max(0.01, (float) ($componente['escala_max'] ?? 10)),
                'obrigatorio' => !empty($componente['obrigatorio']) ? 1 : 0,
            ];
        }

        if (empty($componentesNormalizados)) {
            $_SESSION['boletim_flash'] = 'Nenhum componente válido foi informado.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirectFalhaConfiguracao($regraId);
        }

        $extrasJsonNormalized = null;
        $extrasJsonRaw = trim((string) ($_POST['regra_extras_json'] ?? ''));
        $decodedExtras = [];
        if ($extrasJsonRaw !== '') {
            $decodedExtras = json_decode($extrasJsonRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['boletim_flash'] = 'O campo Extras (JSON) não contém um JSON válido: ' . json_last_error_msg();
                $_SESSION['boletim_flash_type'] = 'error';
                $this->redirectFalhaConfiguracao($regraId);
            }
            if (!is_array($decodedExtras)) {
                $_SESSION['boletim_flash'] = 'O campo Extras (JSON) deve ser um objeto JSON (ex.: {}).';
                $_SESSION['boletim_flash_type'] = 'error';
                $this->redirectFalhaConfiguracao($regraId);
            }
        } elseif ($regraId !== null && $regraId > 0) {
            $regraExistente = $this->boletimConfig->getRuleById($regraId);
            if (is_array($regraExistente)) {
                $rawPrev = $regraExistente['extras_json'] ?? '';
                if (is_string($rawPrev) && trim($rawPrev) !== '') {
                    $decodedExtras = json_decode($rawPrev, true);
                    if (!is_array($decodedExtras)) {
                        $decodedExtras = [];
                    }
                }
            }
        }

        unset($decodedExtras['jornada_media_condicional']);

        $grupoRegrasId = (int) ($_POST['grupo_regras_notas_id'] ?? 0);
        $payloadQuadro = null;
        if ($grupoRegrasId > 0) {
            try {
                $payloadQuadro = (new GrupoRegrasNotasService())->payloadPublico($grupoRegrasId);
            } catch (Throwable $e) {
                $payloadQuadro = null;
            }
            if (!is_array($payloadQuadro) || empty($payloadQuadro['ativo'])) {
                $_SESSION['boletim_flash'] = 'Quadro de Notas inválido ou inativo. Revise o cadastro em Acadêmico → Quadro de Notas.';
                $_SESSION['boletim_flash_type'] = 'error';
                $this->redirectFalhaConfiguracao($regraId);
            }
        }
        if ($grupoRegrasId > 0) {
            $decodedExtras['grupo_regras_notas_id'] = $grupoRegrasId;
            $decodedExtras['quadro_notas_id'] = $grupoRegrasId;
            $semanasPeriodo = (int) ($_POST['semanas_periodo'] ?? 0);
            if ($semanasPeriodo > 0) {
                $maxQuadro = (int) ($payloadQuadro['quantidade_semanas'] ?? 0);
                if ($maxQuadro > 0 && $semanasPeriodo > $maxQuadro) {
                    $semanasPeriodo = $maxQuadro;
                }
                $decodedExtras['semanas_periodo'] = $semanasPeriodo;
                $componentesNormalizados = (new GrupoRegrasNotasService())->recortarComponentesPorSemanas(
                    $componentesNormalizados,
                    $semanasPeriodo
                );
            } else {
                unset($decodedExtras['semanas_periodo']);
            }
            $destinos = $this->normalizarDestinosQuadroPost($_POST['destinos_json'] ?? $decodedExtras['destinos'] ?? []);
            if ($destinos !== []) {
                $decodedExtras['destinos'] = $destinos;
            } else {
                unset($decodedExtras['destinos']);
            }
        } else {
            unset($decodedExtras['grupo_regras_notas_id'], $decodedExtras['quadro_notas_id'], $decodedExtras['destinos'], $decodedExtras['semanas_periodo']);
        }

        if ($decodedExtras !== []) {
            $extrasJsonNormalized = json_encode($decodedExtras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        try {
            $savedId = $this->boletimConfig->saveRule(
                $nome,
                $formulaFinal,
                $componentesNormalizados,
                ($regraId !== null && $regraId > 0) ? $regraId : null,
                $descricaoCurta,
                json_encode($materiasIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $this->normalizeFormulaMateriasJsonForSave($formulaMateriasJson),
                $codigoRegra,
                json_encode($seriesIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($turmasIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $exibirEm,
                $anoLetivo,
                $exibirEm === 'notas' ? $bimestre : null,
                $visAluno,
                $visPais,
                $visCoordenacao,
                $roundMode,
                $decimalPlaces,
                $defaultDataInicio,
                $defaultDataFim,
                $notaMinimaAprovacao,
                $usarResultadoAprovacao,
                $extrasJsonNormalized,
                $finalidade
            );
            if ($savedId > 0) {
                $this->boletimConfig->setBoletimId((int) $savedId, $boletimIdPost > 0 ? $boletimIdPost : null);
                if ($boletimIdPost > 0) {
                    try {
                        (new BoletimCadastroService())->sincronizarDocumento($boletimIdPost);
                    } catch (Throwable $e) {
                        error_log('salvarRegra sincronizarDocumento: ' . $e->getMessage());
                    }
                }
            }
            $versaoConfig = 0;
            if ($savedId > 0) {
                $usuario = $this->auth->getUser();
                $versaoConfig = $this->boletimConfig->gravarVersaoConfiguracao(
                    (int) $savedId,
                    (int) (is_array($usuario) ? ($usuario['id'] ?? 0) : 0),
                    trim((string) (is_array($usuario) ? ($usuario['nome'] ?? '') : ''))
                );
                $regraId = $savedId;
                unset($_SESSION['boletim_assistente_rascunho']);
            }
            $publicarNotas = $exibirEm === 'notas'
                && (string) ($_POST['gerar_nova_versao'] ?? '') === '1';
            if ($savedId > 0 && $publicarNotas && $this->iniciarNovaVersaoNotas((int) $savedId, $versaoConfig)) {
                return;
            }
            $soConfiguracao = $exibirEm === 'notas' && !empty($_POST['origem_assistente']);
            $_SESSION['boletim_flash'] = $versaoConfig > 0
                ? ('Configuração salva na versão ' . $versaoConfig . '.'
                    . ($soConfiguracao
                        ? ' As notas não foram geradas. Para publicá-las no aluno, use Concluir e aplicar.'
                        : ' Dá para recuperar esta versão no final do Configurar Notas.'))
                : ($soConfiguracao
                    ? 'Evento de notas salvo. As notas não foram geradas.'
                    : 'Evento de notas salvo com sucesso.');
            $_SESSION['boletim_flash_type'] = 'success';
        } catch (Throwable $e) {
            error_log('Erro ao salvar regra de boletim: ' . $e->getMessage());
            $_SESSION['boletim_flash'] = 'Erro ao salvar regra: ' . $e->getMessage();
            $_SESSION['boletim_flash_type'] = 'error';
        }

        $redirId = (int) ($regraId ?? 0);
        if ($redirId <= 0 && $codigoRegra !== '') {
            $saved = $this->boletimConfig->getRuleByCode($codigoRegra);
            $redirId = (int) ($saved['id'] ?? 0);
        }
        if (!empty($_POST['origem_assistente']) && $redirId > 0) {
            $this->redirect('/admin/boletim-configuracao/assistente?regra_id=' . $redirId);
        }
        $this->redirect('/admin/boletim-configuracao' . ($redirId > 0 ? ('?regra_id=' . $redirId) : ''));
    }

    public function restaurarConfigVersao(): void
    {
        $this->assertCsrfOrRedirect();
        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $versaoId = (int) ($_POST['versao_id'] ?? 0);
        $snap = $this->boletimConfig->obterSnapshotConfiguracao($regraId, $versaoId);
        if (!is_array($snap) || trim((string) ($snap['nome'] ?? '')) === '' || !is_array($snap['componentes'] ?? null)) {
            $_SESSION['boletim_flash'] = 'Não encontrei essa versão da configuração.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect($regraId > 0 ? ('/admin/boletim-configuracao/assistente?regra_id=' . $regraId) : '/admin/boletim');
        }
        $notaMin = $snap['nota_minima_aprovacao'] ?? null;
        $notaMin = ($notaMin === null || $notaMin === '') ? null : (float) $notaMin;
        try {
            $savedId = $this->boletimConfig->saveRule(
                (string) $snap['nome'],
                (string) ($snap['formula_final'] ?? ''),
                (array) $snap['componentes'],
                $regraId,
                (string) ($snap['descricao_curta'] ?? ''),
                (string) ($snap['materias_ids'] ?? '[]'),
                (string) ($snap['formula_materias_json'] ?? ''),
                (string) ($snap['codigo'] ?? ''),
                (string) ($snap['series_ids'] ?? '[]'),
                (string) ($snap['turmas_ids'] ?? '[]'),
                (string) ($snap['exibir_em'] ?? 'notas'),
                (int) ($snap['ano_letivo'] ?? 0) > 0 ? (int) $snap['ano_letivo'] : null,
                (int) ($snap['bimestre'] ?? 0) > 0 ? (int) $snap['bimestre'] : null,
                (int) ($snap['vis_aluno'] ?? 1),
                (int) ($snap['vis_pais'] ?? 1),
                (int) ($snap['vis_coordenacao'] ?? 1),
                (string) ($snap['round_mode'] ?? 'none'),
                (int) ($snap['decimal_places'] ?? 2) === 1 ? 1 : 2,
                $this->normalizarDataYmdOpcional((string) ($snap['default_data_inicio'] ?? '')),
                $this->normalizarDataYmdOpcional((string) ($snap['default_data_fim'] ?? '')),
                $notaMin,
                (int) ($snap['usar_resultado_aprovacao'] ?? 1),
                is_string($snap['extras_json'] ?? null) ? (string) $snap['extras_json'] : null,
                (string) ($snap['finalidade'] ?? 'oficial')
            );
            if ($savedId > 0) {
                $boletimId = (int) ($snap['boletim_id'] ?? 0);
                $this->boletimConfig->setBoletimId($savedId, $boletimId > 0 ? $boletimId : null);
                $usuario = $this->auth->getUser();
                $nova = $this->boletimConfig->gravarVersaoConfiguracao(
                    $savedId,
                    (int) (is_array($usuario) ? ($usuario['id'] ?? 0) : 0),
                    trim((string) (is_array($usuario) ? ($usuario['nome'] ?? '') : ''))
                );
                $exibirSnap = strtolower(trim((string) ($snap['exibir_em'] ?? '')));
                if ($exibirSnap === 'notas'
                    && $this->iniciarNovaVersaoNotas($savedId, (int) $nova)) {
                    return;
                }
                $_SESSION['boletim_flash'] = 'Configuração da versão ' . (int) ($snap['versao'] ?? 0) . ' recuperada'
                    . ($nova > 0 ? (' e salva como versão ' . $nova . '.') : '.');
                $_SESSION['boletim_flash_type'] = 'success';
            }
        } catch (Throwable $e) {
            error_log('restaurarConfigVersao: ' . $e->getMessage());
            $_SESSION['boletim_flash'] = 'Não foi possível recuperar essa versão.';
            $_SESSION['boletim_flash_type'] = 'error';
        }
        $this->redirect('/admin/boletim-configuracao/assistente?regra_id=' . $regraId);
    }

    private function iniciarNovaVersaoNotas(int $regraId, int $versaoConfig): bool
    {
        $regra = $this->boletimConfig->getRuleById($regraId);
        if (!is_array($regra)) {
            return false;
        }
        $dataInicio = $this->normalizarDataYmdOpcional((string) ($regra['default_data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataYmdOpcional((string) ($regra['default_data_fim'] ?? ''));
        if ($dataInicio === null || $dataFim === null) {
            $rangePadrao = $this->periodoToRange($this->periodoDefault());
            $dataInicio = substr((string) ($rangePadrao['inicio'] ?? ''), 0, 10) ?: date('Y-01-01');
            $dataFim = substr((string) ($rangePadrao['fim'] ?? ''), 0, 10) ?: date('Y-m-d');
        }
        if ($dataInicio > $dataFim) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }
        $periodoRef = $this->buildPeriodoRefFromDateRange($dataInicio, $dataFim);
        if (strlen($periodoRef) > 60) {
            $periodoRef = substr($periodoRef, 0, 60);
        }
        $gates = $this->diagnosticarGatesFechamentoRegra($regra);
        if (!empty($gates['bloqueios'])) {
            $_SESSION['boletim_flash'] = 'Configuração salva'
                . ($versaoConfig > 0 ? (' na versão ' . $versaoConfig) : '')
                . '. A nova versão das notas não foi gerada: ' . implode(' ', $gates['bloqueios']);
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao/assistente?regra_id=' . $regraId);
        }
        $alunos = $this->resolveAlunosVinculadosRegra($regra);
        if ($alunos === []) {
            $_SESSION['boletim_flash'] = 'Configuração salva'
                . ($versaoConfig > 0 ? (' na versão ' . $versaoConfig) : '')
                . '. Não há aluno ativo nestas séries para gerar as notas.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao/assistente?regra_id=' . $regraId);
        }
        $mensagem = 'Configuração salva'
            . ($versaoConfig > 0 ? (' na versão ' . $versaoConfig) : '')
            . '. Esta versão passa a ser a vigente no detalhe do aluno e substitui a nota no boletim oficial. A anterior fica no histórico.';
        if ($this->enfileirarGeracaoBoletim($regraId, $periodoRef, $dataInicio, $dataFim, 'gerar', $mensagem)) {
            return true;
        }
        $resultado = $this->executarGeracaoMassaInterna($regra, $regraId, $periodoRef, $dataInicio, $dataFim, $alunos, 'gerar');
        $_SESSION['boletim_flash'] = $mensagem . ' ' . (string) ($resultado['mensagem'] ?? '');
        $_SESSION['boletim_flash_type'] = ((int) ($resultado['erros'] ?? 0) > 0) ? 'error' : 'success';
        $this->redirect('/admin/boletim');
        return true;
    }

    public function duplicarRegra()
    {
        $this->assertCsrfOrRedirect();

        $regraId = isset($_POST['regra_id']) ? (int) $_POST['regra_id'] : 0;
        if ($regraId <= 0) {
            $_SESSION['boletim_flash'] = 'Informe um evento válido para duplicar.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim');
            return;
        }

        try {
            $novoId = $this->boletimConfig->duplicateRule($regraId);
            if ($novoId) {
                $_SESSION['boletim_flash'] = 'Evento duplicado com sucesso.';
                $_SESSION['boletim_flash_type'] = 'success';
                $this->redirect('/admin/boletim-configuracao?regra_id=' . $novoId);
                return;
            }
            $_SESSION['boletim_flash'] = 'Não foi possível duplicar o evento (não encontrado).';
            $_SESSION['boletim_flash_type'] = 'error';
        } catch (Throwable $e) {
            error_log('Erro ao duplicar regra de boletim: ' . $e->getMessage());
            $_SESSION['boletim_flash'] = 'Erro ao duplicar evento: ' . $e->getMessage();
            $_SESSION['boletim_flash_type'] = 'error';
        }

        $this->redirect('/admin/boletim');
    }

    public function excluirRegra()
    {
        $this->assertCsrfOrRedirect();

        $regraId = isset($_POST['regra_id']) ? (int) $_POST['regra_id'] : 0;
        if ($regraId <= 0) {
            $_SESSION['boletim_flash'] = 'Informe um evento válido para excluir.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao?novo=1');
        }

        if ($this->boletimConfig->deactivateRule($regraId)) {
            $_SESSION['boletim_flash'] = 'Evento excluído com sucesso.';
            $_SESSION['boletim_flash_type'] = 'success';
        } else {
            $_SESSION['boletim_flash'] = 'Não foi possível excluir o evento (não encontrado ou já removido).';
            $_SESSION['boletim_flash_type'] = 'error';
        }

        $this->redirect('/admin/boletim-configuracao?novo=1');
    }

    public function desabilitarListaAvaliacoes(): void
    {
        $this->assertCsrfOrRedirect();
        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $oculto = (int) ($_POST['oculto'] ?? 1) === 1;
        if ($regraId <= 0 || $this->boletimConfig->getRuleById($regraId) === null) {
            $_SESSION['boletim_flash'] = 'Evento não encontrado.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim');
        }
        $okLista = $this->boletimConfig->mesclarExtrasJson($regraId, [
            'oculto_lista_avaliacoes' => $oculto ? 1 : 0,
        ]);
        $okVis = $this->boletimConfig->atualizarVisibilidadeCoordenacao($regraId, $oculto ? 0 : 1);
        $ok = $okLista || $okVis;
        if ($ok) {
            $_SESSION['boletim_flash'] = $oculto
                ? 'Evento desabilitado. Ele saiu desta lista, de Notas da Coordenação e do detalhe do aluno.'
                : 'Evento habilitado de novo nesta lista, em Notas da Coordenação e no detalhe do aluno.';
            $_SESSION['boletim_flash_type'] = 'success';
        } else {
            $_SESSION['boletim_flash'] = 'Não foi possível alterar o evento.';
            $_SESSION['boletim_flash_type'] = 'error';
        }
        $this->redirect($oculto ? '/admin/boletim' : '/admin/boletim?desabilitados=1');
    }

    /**
     * @param array<string,mixed> $evento
     */
    private function eventoOcultoNaListaAvaliacoes(array $evento): bool
    {
        $raw = $evento['extras_json'] ?? '';
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) && !empty($decoded['oculto_lista_avaliacoes']);
    }

    /**
     * Bloquear exibição tira o evento da lista de Avaliações e de Notas da Coordenação.
     *
     * @param array<string,mixed> $evento
     */
    private function eventoBloqueadoNaExibicao(array $evento): bool
    {
        return (int) ($evento['vis_coordenacao'] ?? 1) === 0;
    }

    public function alternarVisibilidadeRegra()
    {
        $this->assertCsrfOrRedirect();

        $regraId = isset($_POST['regra_id']) ? (int) $_POST['regra_id'] : 0;
        $retorno = trim((string) ($_POST['retorno'] ?? ''));
        if ($regraId <= 0) {
            $_SESSION['boletim_flash'] = 'Informe um evento válido para alterar a visibilidade.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim');
            return;
        }

        if (array_key_exists('bloquear_exibicao', $_POST)) {
            $bloquear = (int) ($_POST['bloquear_exibicao'] ?? 0) === 1;
            $ok = $this->boletimConfig->atualizarVisibilidadeCoordenacao($regraId, $bloquear ? 0 : 1);
            if ($ok) {
                $_SESSION['boletim_flash'] = $bloquear
                    ? 'Evento bloqueado. Ele saiu desta lista e de Notas da Coordenação. As notas já geradas continuam no aluno.'
                    : 'Exibição liberada. O evento voltou para esta lista e para Notas da Coordenação.';
                $_SESSION['boletim_flash_type'] = 'success';
            } else {
                $_SESSION['boletim_flash'] = 'Não foi possível alterar a exibição do evento.';
                $_SESSION['boletim_flash_type'] = 'error';
            }
            $this->redirect('/admin/boletim');
            return;
        }

        $temVisDetalhada = array_key_exists('vis_aluno', $_POST)
            || array_key_exists('vis_pais', $_POST)
            || array_key_exists('vis_coordenacao', $_POST);

        if ($temVisDetalhada) {
            $visAluno = !empty($_POST['vis_aluno']) ? 1 : 0;
            $visPais = !empty($_POST['vis_pais']) ? 1 : 0;
            $visCoordenacao = !empty($_POST['vis_coordenacao']) ? 1 : 0;
            $ok = $this->boletimConfig->updateRuleVisibility($regraId, $visAluno, $visPais, $visCoordenacao);
            if ($ok) {
                $_SESSION['boletim_flash'] = 'Visibilidade do evento atualizada.';
                $_SESSION['boletim_flash_type'] = 'success';
            } else {
                $_SESSION['boletim_flash'] = 'Não foi possível alterar a visibilidade do evento.';
                $_SESSION['boletim_flash_type'] = 'error';
            }
        } else {
            $visivel = isset($_POST['visivel']) ? (int) $_POST['visivel'] : 0;
            $ok = $this->boletimConfig->updateRuleVisibility($regraId, $visivel, $visivel, null);
            if ($ok) {
                $_SESSION['boletim_flash'] = $visivel
                    ? 'Evento disponibilizado para alunos e pais.'
                    : 'Evento ocultado para alunos e pais.';
                $_SESSION['boletim_flash_type'] = 'success';
            } else {
                $_SESSION['boletim_flash'] = 'Não foi possível alterar a visibilidade do evento.';
                $_SESSION['boletim_flash_type'] = 'error';
            }
        }

        if ($retorno !== '' && $retorno[0] === '/'
            && !str_contains($retorno, '//')
            && !str_contains($retorno, '..')
            && preg_match('#^/admin/boletim-configuracao\?regra_id=[0-9]+&arquivo=1$#', $retorno)
        ) {
            $this->redirect($retorno);
            return;
        }

        $this->redirect('/admin/boletim');
    }

    /**
     * Salva (ou remove) a sobrescrita manual de uma célula calculada direto na
     * tabela "Notas por matéria" da simulação, sem precisar abrir um formulário
     * separado. Afeta só aquela matéria, daquele componente, daquele aluno.
     */
    public function salvarNotaManualMateriaAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = (string) ($_POST['_token'] ?? '');
        if (!$this->verifyCsrfToken($token)) {
            http_response_code(419);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido. Atualize a página.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $componenteId = (int) ($_POST['componente_id'] ?? 0);
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $materiaId = (int) ($_POST['materia_id'] ?? 0);
        $periodoRef = trim((string) ($_POST['periodo_ref'] ?? ''));
        $valorRaw = trim((string) ($_POST['valor'] ?? ''));
        $limpar = !empty($_POST['limpar']);
        // 'vazio' força a célula a ficar "sem nota" (traço), mesmo que exista dado
        // real por trás (prova/jornada) — diferente de 'limpar', que remove a
        // sobrescrita e volta a usar o dado real/fórmula quando ele existir.
        $vazio = !empty($_POST['vazio']);

        // materia_id = 0 é válido pra blocos 'manual' (ex.: ENAC, valor global por
        // componente) e negativo é válido pra linhas agrupadas em "linha única"
        // (ex.: "Língua Portuguesa" juntando Português/Literatura/Leitura), que usam
        // um id sintético negativo só pra essa simulação.
        if ($regraId <= 0 || $componenteId <= 0 || $alunoId <= 0 || $periodoRef === '') {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $regraNotas = $this->boletimConfig->getRuleById($regraId);
        if (is_array($regraNotas)) {
            $alunoRow = Database::getInstance()->fetch('SELECT turma_id FROM alunos WHERE id = :id LIMIT 1', ['id' => $alunoId]);
            $tid = (int) ($alunoRow['turma_id'] ?? 0);
            $bloqueioNotas = $this->bloquearGeracaoSePeriodoHomologado($regraNotas, $tid > 0 ? [['turma_id' => $tid]] : []);
            if ($bloqueioNotas !== null) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => $bloqueioNotas], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        if ($limpar) {
            $resultado = $this->boletimConfig->removerNotaManual($componenteId, $alunoId, $periodoRef, $materiaId);
        } elseif ($vazio) {
            $resultado = $this->boletimConfig->saveManualNote([
                'regra_id' => $regraId,
                'componente_id' => $componenteId,
                'aluno_id' => $alunoId,
                'materia_id' => $materiaId,
                'periodo_ref' => $periodoRef,
                'nota' => null,
                'bloqueado' => false,
                'observacao' => null,
            ]);
        } else {
            if ($valorRaw === '' || !is_numeric(str_replace(',', '.', $valorRaw))) {
                echo json_encode(['success' => false, 'message' => 'Informe uma nota válida.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $resultado = $this->boletimConfig->saveManualNote([
                'regra_id' => $regraId,
                'componente_id' => $componenteId,
                'aluno_id' => $alunoId,
                'materia_id' => $materiaId,
                'periodo_ref' => $periodoRef,
                'nota' => (float) str_replace(',', '.', $valorRaw),
                'bloqueado' => false,
                'observacao' => null,
            ]);
        }

        // Se esse aluno já tem boletim OFICIAL gravado (publicado) pra esse evento e
        // período, regrava automaticamente com o novo valor — senão a sobrescrita só
        // valeria na simulação, e quem já tinha o boletim publicado não veria a
        // correção até alguém lembrar de clicar em "Gravar boletim oficial" de novo.
        if (!empty($resultado['success']) && $this->boletimConfig->hasOfficialResult($regraId, $alunoId, $periodoRef)) {
            try {
                $regra = $this->boletimConfig->getRuleById($regraId);
                if ($regra) {
                    $range = $this->periodoToRange($periodoRef);
                    $dataInicio = $range['inicio'] !== null ? substr((string) $range['inicio'], 0, 10) : null;
                    $dataFim = $range['fim'] !== null ? substr((string) $range['fim'], 0, 10) : null;
                    $sim = $this->simularRegraAluno($regra, $alunoId, $periodoRef, $dataInicio, $dataFim);
                    $matriz = $sim['matriz_materias'] ?? null;
                    $colunas = is_array($matriz) && is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
                    $linhas = is_array($matriz) && is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
                    if ($colunas !== [] && $linhas !== []) {
                        $userEdit = $this->auth->getUser();
                        $geracaoId = $this->boletimConfig->criarGeracao(
                            $regraId,
                            $periodoRef,
                            'edicao',
                            (int) ($userEdit['id'] ?? 0) ?: null,
                            (string) ($userEdit['nome'] ?? '') ?: null
                        );
                        $this->boletimConfig->replaceGeneratedResultsForAluno(
                            $regraId,
                            $alunoId,
                            $periodoRef,
                            $dataInicio,
                            $dataFim,
                            $colunas,
                            $linhas,
                            false,
                            $geracaoId
                        );
                        if ($geracaoId !== null) {
                            $this->boletimConfig->atualizarGeracaoTotais($geracaoId, 1, 0, count($linhas), 0, 0, ['modo' => 'edicao']);
                        }
                        $resultado['boletim_oficial_atualizado'] = true;
                    }
                }
            } catch (Throwable $e) {
                error_log('salvarNotaManualMateriaAjax: falha ao regravar boletim oficial: ' . $e->getMessage());
            }
        }

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function keepalive()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['last_activity'] = time();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'ts' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function salvarNotasManuais()
    {
        $this->assertCsrfOrRedirect();

        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $regraNotas = $regraId > 0 ? $this->boletimConfig->getRuleById($regraId) : null;
        if (is_array($regraNotas)) {
            $alunoRow = Database::getInstance()->fetch('SELECT turma_id FROM alunos WHERE id = :id LIMIT 1', ['id' => $alunoId]);
            $tid = (int) ($alunoRow['turma_id'] ?? 0);
            $bloqueioNotas = $this->bloquearGeracaoSePeriodoHomologado($regraNotas, $tid > 0 ? [['turma_id' => $tid]] : []);
            if ($bloqueioNotas !== null) {
                $_SESSION['boletim_flash'] = $bloqueioNotas;
                $_SESSION['boletim_flash_type'] = 'error';
                $this->redirect('/admin/boletim-configuracao?regra_id=' . $regraId);
            }
        }
        $periodoRef = trim((string) ($_POST['periodo_ref'] ?? ''));
        $dataInicio = $this->normalizarDataYmdOpcional((string) ($_POST['data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataYmdOpcional((string) ($_POST['data_fim'] ?? ''));
        if ($dataInicio !== null && $dataFim !== null && $dataInicio > $dataFim) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }
        if ($periodoRef === '' && $dataInicio !== null && $dataFim !== null) {
            $periodoRef = $this->buildPeriodoRefFromDateRange($dataInicio, $dataFim);
        }
        $manualNotas = $_POST['manual_notas'] ?? [];
        $locks = $_POST['manual_lock'] ?? [];
        $manualNotasMateria = $_POST['manual_notas_materia'] ?? [];
        $locksMateria = $_POST['manual_lock_materia'] ?? [];

        if ($regraId <= 0 || $alunoId <= 0 || $periodoRef === '') {
            $_SESSION['boletim_flash'] = 'Dados incompletos para salvar notas manuais.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
        }

        $erros = [];

        foreach ($manualNotas as $componenteIdRaw => $valorRaw) {
            $componenteId = (int) $componenteIdRaw;
            $valorRaw = trim((string) $valorRaw);
            if ($componenteId <= 0 || $valorRaw === '') {
                continue;
            }

            $nota = (float) str_replace(',', '.', $valorRaw);
            $resultado = $this->boletimConfig->saveManualNote([
                'regra_id' => $regraId,
                'componente_id' => $componenteId,
                'aluno_id' => $alunoId,
                'periodo_ref' => $periodoRef,
                'nota' => $nota,
                'bloqueado' => !empty($locks[$componenteId]),
                'observacao' => null,
            ]);

            if (empty($resultado['success'])) {
                $erros[] = 'Componente #' . $componenteId . ': ' . ($resultado['message'] ?? 'erro ao salvar');
            }
        }

        if (is_array($manualNotasMateria)) {
            foreach ($manualNotasMateria as $componenteIdRaw => $porMateria) {
                $componenteId = (int) $componenteIdRaw;
                if ($componenteId <= 0 || !is_array($porMateria)) {
                    continue;
                }
                foreach ($porMateria as $materiaIdRaw => $valorRaw) {
                    $materiaId = (int) $materiaIdRaw;
                    $valorRaw = trim((string) $valorRaw);
                    if ($materiaId <= 0 || $valorRaw === '') {
                        continue;
                    }
                    $nota = (float) str_replace(',', '.', $valorRaw);
                    $resultado = $this->boletimConfig->saveManualNote([
                        'regra_id' => $regraId,
                        'componente_id' => $componenteId,
                        'aluno_id' => $alunoId,
                        'materia_id' => $materiaId,
                        'periodo_ref' => $periodoRef,
                        'nota' => $nota,
                        'bloqueado' => !empty($locksMateria[$componenteId][$materiaId]),
                        'observacao' => null,
                    ]);
                    if (empty($resultado['success'])) {
                        $erros[] = 'Componente #' . $componenteId . ' / matéria #' . $materiaId . ': ' . ($resultado['message'] ?? 'erro ao salvar');
                    }
                }
            }
        }

        if (!empty($erros)) {
            $_SESSION['boletim_flash'] = implode(' | ', $erros);
            $_SESSION['boletim_flash_type'] = 'error';
        } else {
            $_SESSION['boletim_flash'] = 'Notas manuais salvas com sucesso.';
            $_SESSION['boletim_flash_type'] = 'success';
        }

        $qs = [
            'regra_id' => $regraId,
            'aluno_id' => $alunoId,
            'periodo_ref' => $periodoRef,
        ];
        if ($dataInicio !== null && $dataFim !== null) {
            $qs['data_inicio'] = $dataInicio;
            $qs['data_fim'] = $dataFim;
        }
        $this->redirect('/admin/boletim-configuracao?' . http_build_query($qs));
    }

    public function gerarBoletins()
    {
        $this->assertCsrfOrRedirect();

        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $periodoRef = trim((string) ($_POST['periodo_ref'] ?? ''));
        $dataInicio = $this->normalizarDataYmdOpcional((string) ($_POST['data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataYmdOpcional((string) ($_POST['data_fim'] ?? ''));
        if ($dataInicio !== null && $dataFim !== null && $dataInicio > $dataFim) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }
        if ($periodoRef === '' && $dataInicio !== null && $dataFim !== null) {
            $periodoRef = $this->buildPeriodoRefFromDateRange($dataInicio, $dataFim);
        }
        if ($periodoRef === '') {
            $periodoRef = $this->periodoDefault();
        }
        if (strlen($periodoRef) > 60) {
            $periodoRef = substr($periodoRef, 0, 60);
        }

        $regra = $regraId > 0
            ? $this->boletimConfig->getRuleById($regraId)
            : $this->boletimConfig->getActiveRule();
        if (!$regra) {
            $_SESSION['boletim_flash'] = 'Nenhum evento válido encontrado para gerar o boletim.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect($this->urlRetornoGeracaoModelo() ?? '/admin/boletim-configuracao');
        }
        $regraId = (int) ($regra['id'] ?? 0);
        if ($regraId <= 0) {
            $_SESSION['boletim_flash'] = 'Evento sem ID válido.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect($this->urlRetornoGeracaoModelo() ?? '/admin/boletim-configuracao');
        }
        $gates = $this->diagnosticarGatesFechamentoRegra($regra);
        if (!empty($gates['bloqueios'])) {
            $_SESSION['boletim_flash'] = implode(' ', $gates['bloqueios']);
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect($this->urlRetornoGeracaoModelo() ?? ('/admin/boletim-configuracao?regra_id=' . $regraId));
        }

        $alunos = $this->resolveAlunosVinculadosRegra($regra);
        if ($alunos === []) {
            $_SESSION['boletim_flash'] = 'Nenhum aluno ativo encontrado para as séries vinculadas.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect($this->urlRetornoGeracaoModelo() ?? ('/admin/boletim-configuracao?regra_id=' . $regraId));
        }

        if ($this->enfileirarGeracaoBoletim($regraId, $periodoRef, $dataInicio, $dataFim, 'gerar')) {
            return;
        }

        $resultado = $this->executarGeracaoMassaInterna(
            $regra,
            $regraId,
            $periodoRef,
            $dataInicio,
            $dataFim,
            $alunos,
            'gerar'
        );
        $_SESSION['boletim_flash'] = $resultado['mensagem'];
        $_SESSION['boletim_flash_type'] = ((int) ($resultado['erros'] ?? 0) > 0) ? 'error' : 'success';
        $this->redirect($this->urlRetornoGeracaoModelo() ?? '/admin/boletim');
    }

    /**
     * Empurra para a Vida Escolar os boletins já gravados (preview=0),
     * sem recalcular nem gerar de novo.
     */
    public function sincronizarVidaEscolarSalvos(): void
    {
        $this->assertCsrfOrRedirect();

        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $periodoRef = trim((string) ($_POST['periodo_ref'] ?? ''));
        if ($periodoRef === '') {
            $dataInicio = $this->normalizarDataYmdOpcional((string) ($_POST['data_inicio'] ?? ''));
            $dataFim = $this->normalizarDataYmdOpcional((string) ($_POST['data_fim'] ?? ''));
            if ($dataInicio !== null && $dataFim !== null) {
                if ($dataInicio > $dataFim) {
                    [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
                }
                $periodoRef = $this->buildPeriodoRefFromDateRange($dataInicio, $dataFim);
            }
        }
        if (strlen($periodoRef) > 60) {
            $periodoRef = substr($periodoRef, 0, 60);
        }

        $regra = $regraId > 0 ? $this->boletimConfig->getRuleById($regraId) : null;
        if (!$regra || $regraId <= 0) {
            $_SESSION['boletim_flash'] = 'Selecione um evento válido para sincronizar os boletins salvos.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect($this->urlRetornoGeracaoModelo() ?? '/admin/boletim-configuracao');
            return;
        }

        $alunoIds = [];
        if ($periodoRef !== '') {
            $alunoIds = $this->boletimConfig->listAlunoIdsWithOfficialBoletim($regraId, $periodoRef);
        }
        if ($alunoIds === []) {
            // Fallback: periodo_ref da tela pode diferir do gravado na geração.
            $alunoIds = $this->boletimConfig->listAlunoIdsWithOfficialBoletimNaRegra($regraId);
        }
        if ($alunoIds === []) {
            $_SESSION['boletim_flash'] = 'Não há boletim oficial salvo neste evento para sincronizar. Gere o período antes ou confira o evento selecionado.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect($this->urlRetornoGeracaoModelo() ?? ('/admin/boletim-configuracao?regra_id=' . $regraId));
            return;
        }

        $usuarioVidaRaw = $this->auth->getUser();
        $usuarioVida = is_array($usuarioVidaRaw) ? $usuarioVidaRaw : [];
        // Sem filtrar por regra: aplica todos os eventos oficiais já salvos do aluno
        // (1º/2º/3º), cada um só no próprio bimestre.
        $this->sincronizarFichasVidaEscolarLote($alunoIds, $usuarioVida, null, null);

        $qtd = count($alunoIds);
        $_SESSION['boletim_flash'] = $qtd === 1
            ? 'Vida Escolar sincronizada com o boletim já salvo de 1 aluno (sem regenerar).'
            : ('Vida Escolar sincronizada com os boletins já salvos de ' . $qtd . ' alunos (sem regenerar).');
        $_SESSION['boletim_flash_type'] = 'success';
        $this->redirect($this->urlRetornoGeracaoModelo() ?? ('/admin/boletim-configuracao?regra_id=' . $regraId));
    }

    /**
     * Grava o boletim oficial (preview=0) só para o aluno da simulação atual,
     * sem percorrer todos os vinculados ao evento.
     */
    public function publicarBoletimAlunoSimulado(): void
    {
        $this->assertCsrfOrRedirect();

        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $periodoRef = trim((string) ($_POST['periodo_ref'] ?? ''));
        $dataInicio = $this->normalizarDataYmdOpcional((string) ($_POST['data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataYmdOpcional((string) ($_POST['data_fim'] ?? ''));
        if ($dataInicio !== null && $dataFim !== null && $dataInicio > $dataFim) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }
        if ($periodoRef === '' && $dataInicio !== null && $dataFim !== null) {
            $periodoRef = $this->buildPeriodoRefFromDateRange($dataInicio, $dataFim);
        }
        if ($periodoRef === '') {
            $periodoRef = $this->periodoDefault();
        }
        if (strlen($periodoRef) > 60) {
            $periodoRef = substr($periodoRef, 0, 60);
        }

        $qsOk = [
            'regra_id' => $regraId,
            'aluno_id' => $alunoId,
            'periodo_ref' => $periodoRef,
        ];
        if ($dataInicio !== null && $dataFim !== null) {
            $qsOk['data_inicio'] = $dataInicio;
            $qsOk['data_fim'] = $dataFim;
        }

        if ($regraId <= 0 || $alunoId <= 0) {
            $_SESSION['boletim_flash'] = 'Informe um evento salvo e um aluno válidos para gravar o boletim oficial.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
            return;
        }

        $regra = $this->boletimConfig->getRuleById($regraId);
        if (!$regra) {
            $_SESSION['boletim_flash'] = 'Evento de boletim não encontrado.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
            return;
        }

        $permitido = false;
        foreach ($this->resolveAlunosVinculadosRegra($regra) as $row) {
            if ((int) ($row['id'] ?? 0) === $alunoId) {
                $permitido = true;
                break;
            }
        }
        if (!$permitido) {
            $_SESSION['boletim_flash'] = 'Este aluno não está no escopo deste evento (séries vinculadas).';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao?' . http_build_query($qsOk));
            return;
        }

        try {
            $sim = $this->simularRegraAluno($regra, $alunoId, $periodoRef, $dataInicio, $dataFim);
            $matriz = $sim['matriz_materias'] ?? null;
            $colunas = is_array($matriz) && is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
            $linhas = is_array($matriz) && is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
            if ($linhas === []) {
                $_SESSION['boletim_flash'] = 'Não há linhas de matéria para gravar. Ajuste a simulação antes de publicar o boletim oficial.';
                $_SESSION['boletim_flash_type'] = 'error';
                $this->redirect('/admin/boletim-configuracao?' . http_build_query($qsOk));
                return;
            }
            $userPub = $this->auth->getUser();
            $geracaoId = $this->boletimConfig->criarGeracao(
                $regraId,
                $periodoRef,
                'aluno',
                (int) ($userPub['id'] ?? 0) ?: null,
                (string) ($userPub['nome'] ?? '') ?: null
            );
            $this->boletimConfig->replaceGeneratedResultsForAluno(
                $regraId,
                $alunoId,
                $periodoRef,
                $dataInicio,
                $dataFim,
                $colunas,
                $linhas,
                false,
                $geracaoId
            );
            if ($geracaoId !== null) {
                $this->boletimConfig->atualizarGeracaoTotais($geracaoId, 1, 0, count($linhas), 0, 0, ['modo' => 'aluno']);
            }
            $travar = !empty($_POST['travar_aluno']);
            if ($travar) {
                $this->boletimConfig->travarAluno(
                    $regraId,
                    $alunoId,
                    $periodoRef,
                    'Ajuste manual — não recalcular em lote',
                    (int) ($userPub['id'] ?? 0) ?: null,
                    (string) ($userPub['nome'] ?? '') ?: null
                );
            }
            $msgTravado = $travar
                ? ' Este aluno ficou travado: as próximas gerações em lote não vão recalcular as notas dele.'
                : '';
            $_SESSION['boletim_flash'] = 'Boletim oficial gravado como nova versão vigente para este aluno neste período.' . $msgTravado;
            $_SESSION['boletim_flash_type'] = 'success';
            $this->sincronizarFichaVidaEscolar(
                $alunoId,
                is_array($userPub) ? $userPub : [],
                $periodoRef,
                $regraId
            );
        } catch (Throwable $e) {
            error_log('publicarBoletimAlunoSimulado: ' . $e->getMessage());
            $_SESSION['boletim_flash'] = 'Não foi possível gravar o boletim oficial. Tente novamente ou verifique os logs.';
            $_SESSION['boletim_flash_type'] = 'error';
        }

        $this->redirect('/admin/boletim-configuracao?' . http_build_query($qsOk));
    }

    public function atualizarBoletinsGravados()
    {
        $this->assertCsrfOrRedirect();

        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $periodoRef = trim((string) ($_POST['periodo_ref'] ?? ''));
        $dataInicio = $this->normalizarDataYmdOpcional((string) ($_POST['data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataYmdOpcional((string) ($_POST['data_fim'] ?? ''));
        if ($dataInicio !== null && $dataFim !== null && $dataInicio > $dataFim) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }
        if ($periodoRef === '' && $dataInicio !== null && $dataFim !== null) {
            $periodoRef = $this->buildPeriodoRefFromDateRange($dataInicio, $dataFim);
        }
        if ($periodoRef === '') {
            $periodoRef = $this->periodoDefault();
        }
        if (strlen($periodoRef) > 60) {
            $periodoRef = substr($periodoRef, 0, 60);
        }

        $regra = $regraId > 0
            ? $this->boletimConfig->getRuleById($regraId)
            : $this->boletimConfig->getActiveRule();
        if (!$regra) {
            $_SESSION['boletim_flash'] = 'Nenhum evento válido encontrado para atualizar o boletim.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
        }
        $regraId = (int) ($regra['id'] ?? 0);
        if ($regraId <= 0) {
            $_SESSION['boletim_flash'] = 'Evento sem ID válido.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
        }

        $publicarAPartirDePrevia = false;
        $idsComGravacao = $this->boletimConfig->listAlunoIdsWithOfficialBoletim($regraId, $periodoRef);
        if ($idsComGravacao === []) {
            $idsComGravacao = $this->boletimConfig->listAlunoIdsWithGeneratedBoletim($regraId, $periodoRef, true);
            $publicarAPartirDePrevia = $idsComGravacao !== [];
        }
        if ($idsComGravacao === []) {
            $_SESSION['boletim_flash'] = 'Não há boletins gravados neste período para atualizar. Confira o período selecionado ou use "Gerar boletins de todos os alunos vinculados" na primeira vez ou quando precisar incluir alunos que ainda não têm registro.';
            $_SESSION['boletim_flash_type'] = 'error';
            $qs = [
                'regra_id' => $regraId,
                'periodo_ref' => $periodoRef,
            ];
            if ($dataInicio !== null && $dataFim !== null) {
                $qs['data_inicio'] = $dataInicio;
                $qs['data_fim'] = $dataFim;
            }
            $this->redirect($this->urlRetornoGeracaoModelo() ?? ('/admin/boletim-configuracao?' . http_build_query($qs)));
        }

        $alunos = $publicarAPartirDePrevia
            ? $this->resolveAlunosVinculadosRegra($regra)
            : $this->boletimConfig->getStudentsByIds($idsComGravacao);
        if ($alunos === []) {
            $_SESSION['boletim_flash'] = 'Nenhum aluno ativo encontrado para o escopo deste evento.';
            $_SESSION['boletim_flash_type'] = 'error';
            $qs = [
                'regra_id' => $regraId,
                'periodo_ref' => $periodoRef,
            ];
            if ($dataInicio !== null && $dataFim !== null) {
                $qs['data_inicio'] = $dataInicio;
                $qs['data_fim'] = $dataFim;
            }
            $this->redirect($this->urlRetornoGeracaoModelo() ?? ('/admin/boletim-configuracao?' . http_build_query($qs)));
        }

        if ($this->enfileirarGeracaoBoletim(
            $regraId,
            $periodoRef,
            $dataInicio,
            $dataFim,
            $publicarAPartirDePrevia ? 'atualizar_previa' : 'atualizar'
        )) {
            return;
        }

        $resultado = $this->executarGeracaoMassaInterna(
            $regra,
            $regraId,
            $periodoRef,
            $dataInicio,
            $dataFim,
            $alunos,
            $publicarAPartirDePrevia ? 'atualizar_previa' : 'atualizar'
        );
        $_SESSION['boletim_flash'] = $resultado['mensagem'];
        $_SESSION['boletim_flash_type'] = ((int) ($resultado['erros'] ?? 0) > 0) ? 'error' : 'success';
        $this->redirect($this->urlRetornoGeracaoModelo() ?? '/admin/boletim');
    }

    /**
     * Worker CLI da fila ai_jobs (tipo boletim_gerar). Sem sessão admin.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function executarGeracaoJob(array $payload): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        $this->jobIdHeartbeat = (int) ($payload['_job_id'] ?? 0);
        $this->renovarHeartbeatGeracao();
        $this->usuarioGeracaoJob = [
            'id' => (int) ($payload['user_id'] ?? 0),
            'nome' => (string) ($payload['user_nome'] ?? ''),
            'tipo' => (string) ($payload['user_tipo'] ?? 'admin'),
        ];

        $regraId = (int) ($payload['regra_id'] ?? 0);
        $periodoRef = trim((string) ($payload['periodo_ref'] ?? ''));
        $dataInicio = $this->normalizarDataYmdOpcional((string) ($payload['data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataYmdOpcional((string) ($payload['data_fim'] ?? ''));
        $modo = (string) ($payload['modo'] ?? 'gerar');
        if (!in_array($modo, ['gerar', 'atualizar', 'atualizar_previa'], true)) {
            throw new RuntimeException('Modo de geração inválido.');
        }
        if ($periodoRef === '') {
            $periodoRef = $this->periodoDefault();
        }
        if (strlen($periodoRef) > 60) {
            $periodoRef = substr($periodoRef, 0, 60);
        }

        $regra = $regraId > 0 ? $this->boletimConfig->getRuleById($regraId) : null;
        if (!$regra) {
            throw new RuntimeException('Evento de boletim não encontrado para gerar em segundo plano.');
        }
        $regraId = (int) ($regra['id'] ?? 0);

        $alunos = $this->resolverAlunosGeracaoPorModo($regra, $regraId, $periodoRef, $modo);
        if ($alunos === []) {
            throw new RuntimeException('Nenhum aluno ativo encontrado para gerar o boletim.');
        }

        return $this->executarGeracaoMassaInterna(
            $regra,
            $regraId,
            $periodoRef,
            $dataInicio,
            $dataFim,
            $alunos,
            $modo
        );
    }

    /**
     * Enfileira e redireciona para a listagem. Retorna false se a fila não existir (caller roda síncrono).
     */
    private function enfileirarGeracaoBoletim(
        int $regraId,
        string $periodoRef,
        ?string $dataInicio,
        ?string $dataFim,
        string $modo,
        string $mensagem = ''
    ): bool {
        if ($this->boletimConfig->temGeracaoEmAndamento($regraId)) {
            $avisoFila = 'Já existe uma geração em andamento para este evento. Aguarde terminar para gerar de novo.';
            $_SESSION['boletim_flash'] = $mensagem !== ''
                ? ('A configuração foi salva. ' . $avisoFila . ' Se ela ainda não começou, esta versão entra quando rodar. Se já estiver calculando, salve outra vez ao terminar.')
                : $avisoFila;
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect($this->urlRetornoGeracaoModelo() ?? '/admin/boletim');
        }

        $db = Database::getInstance();
        if (!$db->tableExists('ai_jobs')) {
            return false;
        }

        $user = $this->auth->getUser();
        require_once __DIR__ . '/../../Services/AIJobService.php';
        try {
            \App\Services\AIJobService::enqueue('boletim_gerar', [
                'regra_id' => $regraId,
                'periodo_ref' => $periodoRef,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'modo' => $modo,
                'user_id' => (int) ($user['id'] ?? 0),
                'user_nome' => (string) ($user['nome'] ?? ''),
                'user_tipo' => (string) ($user['tipo'] ?? 'admin'),
            ], (int) ($user['id'] ?? 0), 'admin', false);
            \App\Services\AIJobService::tentarDispararWorker();
        } catch (Throwable $e) {
            error_log('enfileirarGeracaoBoletim: ' . $e->getMessage());
            return false;
        }

        $_SESSION['boletim_flash'] = $mensagem !== ''
            ? $mensagem
            : 'Geração iniciada em segundo plano. Acompanhe o status na listagem — a página atualiza sozinha quando terminar.';
        $_SESSION['boletim_flash_type'] = 'info';
        $this->redirect($this->urlRetornoGeracaoModelo() ?? '/admin/boletim');
        return true;
    }

    /**
     * Volta à tela do modelo quando a geração partiu de /admin/boletins/{id}/gerar-boletins.
     */
    private function urlRetornoGeracaoModelo(): ?string
    {
        $retorno = trim((string) ($_POST['retorno'] ?? ''));
        if ($retorno === '' || $retorno[0] !== '/') {
            return null;
        }
        if (str_contains($retorno, '//') || str_contains($retorno, '..') || str_contains($retorno, "\n") || str_contains($retorno, "\r")) {
            return null;
        }
        if (!preg_match('#^/admin/boletins/[0-9]+/gerar-boletins(?:\?regra_id=[0-9]+)?$#', $retorno)) {
            return null;
        }
        return $retorno;
    }

    private function renovarHeartbeatGeracao(): void
    {
        if ($this->jobIdHeartbeat <= 0) {
            return;
        }
        $agora = microtime(true);
        if ($this->ultimoHeartbeatEm > 0.0
            && ($agora - $this->ultimoHeartbeatEm) < self::INTERVALO_HEARTBEAT_SEGUNDOS) {
            return;
        }
        $this->ultimoHeartbeatEm = $agora;
        if (!class_exists(\App\Services\AIJobService::class, false)) {
            require_once __DIR__ . '/../../Services/AIJobService.php';
        }
        \App\Services\AIJobService::renovarHeartbeat($this->jobIdHeartbeat);
    }

    /**
     * @return list<array{id:int,nome?:string}>
     */
    private function resolverAlunosGeracaoPorModo(array $regra, int $regraId, string $periodoRef, string $modo): array
    {
        if ($modo === 'gerar' || $modo === 'atualizar_previa') {
            return $this->resolveAlunosVinculadosRegra($regra);
        }

        $idsComGravacao = $this->boletimConfig->listAlunoIdsWithOfficialBoletim($regraId, $periodoRef);
        if ($idsComGravacao === []) {
            $idsComGravacao = $this->boletimConfig->listAlunoIdsWithGeneratedBoletim($regraId, $periodoRef, true);
        }
        if ($idsComGravacao === []) {
            return [];
        }

        return $this->boletimConfig->getStudentsByIds($idsComGravacao);
    }

    /**
     * @param list<array{id:int,nome?:string}> $alunos
     * @return array{gerados:int,linhas:int,erros:int,mensagem:string}
     */
    private function executarGeracaoMassaInterna(
        array $regra,
        int $regraId,
        string $periodoRef,
        ?string $dataInicio,
        ?string $dataFim,
        array $alunos,
        string $modo
    ): array {
        $logContexto = $modo === 'gerar' ? 'gerarBoletins' : 'atualizarBoletinsGravados';
        $codigoFinal = $modo === 'gerar'
            ? ($this->boletimConfig->getComponenteFinalCodigo($regraId) ?? '')
            : '';
        $mediasAntes = $codigoFinal !== ''
            ? $this->boletimConfig->getMediaFinalPorAluno($regraId, $periodoRef, $codigoFinal)
            : [];

        $usuarioLog = $this->usuarioGeracaoJob ?? $this->auth->getUser();
        $usuarioIdLog = (int) ($usuarioLog['id'] ?? 0) ?: null;
        $usuarioNomeLog = (string) ($usuarioLog['nome'] ?? '') ?: null;
        $geracaoId = $this->boletimConfig->criarGeracao(
            $regraId,
            $periodoRef,
            $modo,
            $usuarioIdLog,
            $usuarioNomeLog
        );

        $stats = $this->gravarBoletinsSimulacaoParaAlunos(
            $regra,
            $regraId,
            $periodoRef,
            $dataInicio,
            $dataFim,
            $alunos,
            $logContexto,
            false,
            $geracaoId
        );
        $gerados = $stats['gerados'];
        $linhas = $stats['linhas'];
        $erros = $stats['erros'];
        $errosAmostra = $stats['errosAmostra'];
        $preservados = (int) ($stats['preservados'] ?? 0);

        $alunosComMudancaSignificativa = [];
        if ($codigoFinal !== '') {
            $mediasDepois = $this->boletimConfig->getMediaFinalPorAluno($regraId, $periodoRef, $codigoFinal);
            $nomesPorAlunoId = [];
            foreach ($alunos as $al) {
                $nomesPorAlunoId[(int) ($al['id'] ?? 0)] = (string) ($al['nome'] ?? '');
            }
            foreach ($mediasDepois as $alunoIdDiff => $valorDepois) {
                $valorAntes = $mediasAntes[$alunoIdDiff] ?? null;
                if ($valorAntes === null) {
                    continue;
                }
                $diferenca = abs($valorDepois - $valorAntes);
                if ($diferenca >= 2.0) {
                    $alunosComMudancaSignificativa[] = [
                        'aluno_id' => $alunoIdDiff,
                        'nome' => $nomesPorAlunoId[$alunoIdDiff] ?? ('#' . $alunoIdDiff),
                        'antes' => round($valorAntes, 2),
                        'depois' => round($valorDepois, 2),
                    ];
                }
            }
        }

        $usuarioLog = $this->usuarioGeracaoJob ?? $this->auth->getUser();
        $this->boletimConfig->registrarLogGeracao(
            $regraId,
            $periodoRef,
            (int) ($usuarioLog['id'] ?? 0) ?: null,
            (string) ($usuarioLog['nome'] ?? '') ?: null,
            $gerados,
            $linhas,
            $erros,
            count($alunosComMudancaSignificativa),
            [
                'alunos_mudanca_significativa' => array_slice($alunosComMudancaSignificativa, 0, 20),
                'modo' => $modo,
                'alunos_preservados' => $preservados,
                'geracao_id' => $geracaoId,
            ]
        );
        if ($geracaoId !== null) {
            $this->boletimConfig->atualizarGeracaoTotais(
                $geracaoId,
                $gerados,
                $preservados,
                $linhas,
                $erros,
                count($alunosComMudancaSignificativa),
                [
                    'alunos_mudanca_significativa' => array_slice($alunosComMudancaSignificativa, 0, 20),
                    'modo' => $modo,
                ]
            );
        }

        if ($modo === 'gerar') {
            $msg = 'Geração concluída: versão nova vigente com ' . $gerados . ' aluno(s), ' . $linhas . ' linha(s) de matéria.' . ($erros > 0 ? (' Falhas: ' . $erros . '.') : '');
        } else {
            $tipoAtualizado = $modo === 'atualizar_previa' ? 'boletins oficiais dos alunos vinculados' : 'boletins oficiais';
            $msg = 'Atualização dos ' . $tipoAtualizado . ' já gravados concluída: versão nova vigente com ' . $gerados . ' aluno(s), ' . $linhas . ' linha(s) de matéria.' . ($erros > 0 ? (' Falhas: ' . $erros . '.') : '');
        }
        if ($preservados > 0) {
            $msg .= ' ' . $preservados . ' aluno(s) travado(s) não foram recalculados.';
        }
        if (!empty($errosAmostra)) {
            $msg .= ' Exemplo(s): ' . implode(' | ', $errosAmostra);
        }
        if (!empty($alunosComMudancaSignificativa)) {
            $exemplosMudanca = array_slice(array_map(static function ($a) {
                return $a['nome'] . ' (' . $a['antes'] . '→' . $a['depois'] . ')';
            }, $alunosComMudancaSignificativa), 0, 5);
            $msg .= ' ⚠️ ' . count($alunosComMudancaSignificativa) . ' aluno(s) com nota final mudando 2+ pontos: ' . implode(', ', $exemplosMudanca) . '.';
        }

        $this->atualizarBoletimDestinoAposNotas($regra, $periodoRef, $dataInicio, $dataFim);

        return [
            'gerados' => $gerados,
            'linhas' => $linhas,
            'erros' => $erros,
            'preservados' => $preservados,
            'mensagem' => $msg,
        ];
    }

    /**
     * Quadro igual ao da montagem do assistente: S1, S2 e N/Q atuais, com a linha da área.
     *
     * O lote filtra blocos pela união das turmas e reusa o cache de provas. Isso puxa
     * eventos a mais da mesma semana e grava N/Q diferente do que a montagem mostrou.
     * Aqui a simulação segue o caminho de um aluno só.
     *
     * @param array<string,mixed> $regra
     * @return array{colunas:list<array<string,mixed>>,linhas:list<array<string,mixed>>}
     */
    private function matrizDemonstrativoAtual(
        array $regra,
        int $alunoId,
        string $periodoRef,
        ?string $dataInicio,
        ?string $dataFim
    ): array {
        $escopoTurmas = $this->turmasEscopoGeracao;
        $escopoSeries = $this->seriesEscopoGeracao;
        $cacheProvas = $this->provasGeracaoCache;
        $prefetch = $this->prefetchGeracaoPronto;
        $this->turmasEscopoGeracao = [];
        $this->seriesEscopoGeracao = [];
        $this->provasGeracaoCache = [];
        $this->prefetchGeracaoPronto = false;
        try {
            $sim = $this->simularRegraAluno(
                $regra,
                $alunoId,
                $periodoRef,
                $dataInicio,
                $dataFim,
                [],
                false,
                true
            );
            try {
                $simBoletim = $this->simularRegraAluno(
                    $regra,
                    $alunoId,
                    $periodoRef,
                    $dataInicio,
                    $dataFim,
                    [],
                    true,
                    false
                );
                $matrizBoletim = is_array($simBoletim['matriz_materias'] ?? null)
                    ? $simBoletim['matriz_materias']
                    : null;
                if (is_array($matrizBoletim)) {
                    $sim['matriz_materias_boletim'] = $matrizBoletim;
                }
            } catch (\App\Services\FilaJobCanceladaException $e) {
                throw $e;
            } catch (Throwable $e) {
                error_log('matriz demonstrativo boletim aluno #' . $alunoId . ': ' . $e->getMessage());
            }
            $sim = $this->montarMatrizDemonstrativoComGrupoHierarquico($sim, $regra);
            $matriz = is_array($sim['matriz_materias'] ?? null) ? $sim['matriz_materias'] : null;
            if (!is_array($matriz)) {
                return ['colunas' => [], 'linhas' => []];
            }

            return [
                'colunas' => is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [],
                'linhas' => is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [],
            ];
        } finally {
            $this->turmasEscopoGeracao = $escopoTurmas;
            $this->seriesEscopoGeracao = $escopoSeries;
            $this->provasGeracaoCache = $cacheProvas;
            $this->prefetchGeracaoPronto = $prefetch;
        }
    }

    /**
     * Recalcula a matriz e grava uma nova versão vigente em boletim_resultados_gerados por aluno.
     * Alunos travados são pulados (a versão vigente deles permanece).
     *
     * @param list<array{id:int,nome?:string}> $alunos
     * @return array{gerados:int,linhas:int,erros:int,errosAmostra:list<string>,preservados:int}
     */
    private function gravarBoletinsSimulacaoParaAlunos(
        array $regra,
        int $regraId,
        string $periodoRef,
        ?string $dataInicio,
        ?string $dataFim,
        array $alunos,
        string $logContexto,
        bool $preview = false,
        ?int $geracaoId = null
    ): array {
        $gerados = 0;
        $linhas = 0;
        $erros = 0;
        $errosAmostra = [];
        $preservados = 0;
        $idsTravados = [];
        if (!$preview) {
            $bloqueio = $this->bloquearGeracaoSePeriodoHomologado($regra, $alunos);
            if ($bloqueio !== null) {
                return [
                    'gerados' => 0,
                    'linhas' => 0,
                    'erros' => 1,
                    'errosAmostra' => [$bloqueio],
                    'preservados' => 0,
                    'mensagem' => $bloqueio,
                ];
            }
            $idsTravados = array_fill_keys($this->boletimConfig->idsAlunosTravados($regraId, $periodoRef), true);
        }
        $usuarioVidaRaw = $this->usuarioGeracaoJob ?? $this->auth->getUser();
        $usuarioVida = is_array($usuarioVidaRaw) ? $usuarioVidaRaw : [];
        $this->prepararCacheGeracaoBoletim($regra, $alunos, $periodoRef, $dataInicio, $dataFim);
        $itensPersistir = [];
        $alunoIdsOk = [];
        $alunoIdsSync = [];
        foreach ($alunos as $aluno) {
            $alunoId = (int) ($aluno['id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            if (isset($idsTravados[$alunoId])) {
                $preservados++;
                continue;
            }
            $this->renovarHeartbeatGeracao();
            try {
                $matriz = $this->matrizDemonstrativoAtual($regra, $alunoId, $periodoRef, $dataInicio, $dataFim);
                $colunas = is_array($matriz) && is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
                $rows = is_array($matriz) && is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
                $itensPersistir[] = [
                    'aluno_id' => $alunoId,
                    'colunas' => $colunas,
                    'linhas' => $rows,
                ];
                $alunoIdsOk[] = $alunoId;
                $gerados++;
                $linhas += count($rows);
            } catch (\App\Services\FilaJobCanceladaException $e) {
                throw $e;
            } catch (Throwable $e) {
                $erros++;
                if (count($errosAmostra) < 3) {
                    $nomeAluno = trim((string) ($aluno['nome'] ?? ('#' . $alunoId)));
                    $errosAmostra[] = $nomeAluno . ': ' . $e->getMessage();
                }
                error_log('Boletim ' . $logContexto . ' aluno #' . $alunoId . ': ' . $e->getMessage());
            }
            if (count($itensPersistir) >= self::TAMANHO_LOTE_ALUNOS_PERSISTIR) {
                $this->persistirLoteSimulacao(
                    $regraId,
                    $periodoRef,
                    $dataInicio,
                    $dataFim,
                    $itensPersistir,
                    $alunoIdsOk,
                    $preview,
                    $geracaoId,
                    $logContexto,
                    $gerados,
                    $linhas,
                    $erros,
                    $errosAmostra,
                    $alunoIdsSync
                );
                $itensPersistir = [];
                $alunoIdsOk = [];
            }
        }

        if ($itensPersistir !== []) {
            $this->persistirLoteSimulacao(
                $regraId,
                $periodoRef,
                $dataInicio,
                $dataFim,
                $itensPersistir,
                $alunoIdsOk,
                $preview,
                $geracaoId,
                $logContexto,
                $gerados,
                $linhas,
                $erros,
                $errosAmostra,
                $alunoIdsSync
            );
        }

        if (!$preview && $alunoIdsSync !== []) {
            $this->sincronizarFichasVidaEscolarLote($alunoIdsSync, $usuarioVida, $periodoRef, $regraId);
        }

        return [
            'gerados' => $gerados,
            'linhas' => $linhas,
            'erros' => $erros,
            'errosAmostra' => $errosAmostra,
            'preservados' => $preservados,
        ];
    }

    /**
     * @param list<array{aluno_id:int, colunas:list<array<string,mixed>>, linhas:list<array<string,mixed>>}> $itensPersistir
     * @param list<int> $alunoIdsOk
     * @param list<string> $errosAmostra
     * @param list<int> $alunoIdsSync
     */
    private function persistirLoteSimulacao(
        int $regraId,
        string $periodoRef,
        ?string $dataInicio,
        ?string $dataFim,
        array $itensPersistir,
        array $alunoIdsOk,
        bool $preview,
        ?int $geracaoId,
        string $logContexto,
        int &$gerados,
        int &$linhas,
        int &$erros,
        array &$errosAmostra,
        array &$alunoIdsSync
    ): void {
        if ($itensPersistir === []) {
            return;
        }
        $this->renovarHeartbeatGeracao();
        try {
            $this->boletimConfig->replaceGeneratedResultsEmLote(
                $regraId,
                $periodoRef,
                $dataInicio,
                $dataFim,
                $itensPersistir,
                $preview,
                $geracaoId
            );
            $alunoIdsSync = array_merge($alunoIdsSync, $alunoIdsOk);
        } catch (\App\Services\FilaJobCanceladaException $e) {
            throw $e;
        } catch (Throwable $e) {
            $n = count($alunoIdsOk);
            $linhasLote = 0;
            foreach ($itensPersistir as $item) {
                $linhasLote += count($item['linhas'] ?? []);
            }
            $erros += $n;
            $gerados = max(0, $gerados - $n);
            $linhas = max(0, $linhas - $linhasLote);
            if (count($errosAmostra) < 3) {
                $errosAmostra[] = 'Gravação em lote: ' . $e->getMessage();
            }
            error_log('Boletim ' . $logContexto . ' lote: ' . $e->getMessage());
        }
    }

    private function sincronizarFichaVidaEscolar(int $alunoId, array $usuario, ?string $periodoRef = null, ?int $regraId = null): void
    {
        if ($alunoId <= 0) {
            return;
        }
        try {
            if (!class_exists('LayoutHelper', false)) {
                require_once dirname(__DIR__, 2) . '/Core/LayoutHelper.php';
            }
            if (!\LayoutHelper::isModuleEnabled('vida_escolar')) {
                return;
            }
            require_once dirname(__DIR__, 2) . '/Modulos/vida-escolar/Services/VidaEscolarService.php';
            $vida = new \App\Modulos\VidaEscolar\Services\VidaEscolarService();
            $vida->sincronizarDeEventosGerados($alunoId, $usuario, $regraId, $periodoRef, null, true, false, true);
        } catch (Throwable $e) {
            error_log('Vida escolar sync aluno #' . $alunoId . ': ' . $e->getMessage());
        }
    }

    /**
     * Prefetch de provas, faltas e notas manuais pra geração em massa.
     *
     * @param list<array<string,mixed>> $alunos
     */
    private function prepararCacheGeracaoBoletim(
        array $regra,
        array $alunos,
        string $periodoRef,
        ?string $dataInicio,
        ?string $dataFim
    ): void {
        $this->alunosPorIdCache = [];
        $this->provasGeracaoCache = [];
        $this->prefetchGeracaoPronto = false;
        $this->prefetchIncluirPct = false;
        $this->faltasEventoCache = [];
        $this->eventosFaltasCache = [];
        $this->notasManuaisGeracaoCache = [];
        $this->notasManuaisGeracaoAtivo = false;
        $this->simulacaoAlunoCache = [];
        $this->turmasEscopoGeracao = [];
        $this->seriesEscopoGeracao = [];
        $this->blocosFiltradosPorTurmaCache = [];

        foreach ($alunos as $aluno) {
            $id = (int) ($aluno['id'] ?? 0);
            if ($id > 0) {
                $this->alunosPorIdCache[$id] = $aluno;
            }
        }
        $alunoIds = array_keys($this->alunosPorIdCache);
        if ($alunoIds === []) {
            return;
        }
        $this->definirEscopoGeracaoFromAlunos($this->alunosPorIdCache);

        $this->renovarHeartbeatGeracao();

        $range = [
            'inicio' => $dataInicio ? ($dataInicio . ' 00:00:00') : null,
            'fim' => $dataFim ? ($dataFim . ' 23:59:59') : null,
        ];
        if ($range['inicio'] === null || $range['fim'] === null) {
            $range = $this->periodoToRange($periodoRef);
        }

        $compIdsManual = [];
        $incluirPct = false;
        $chavesProvas = [];
        $absence = null;
        $visitados = [];
        $this->acumularPrefetchDaRegra($regra, $range, $incluirPct, $chavesProvas, $compIdsManual, $absence, $visitados);

        foreach ($chavesProvas as $chave => $cfgP) {
            $this->renovarHeartbeatGeracao();
            $blocoIdsP = $cfgP['bloco_ids'];
            if ($blocoIdsP !== []) {
                $porAluno = $this->boletimConfig->getProvasFinalizadasPorAlunosAndBlocos(
                    $alunoIds,
                    $blocoIdsP,
                    $cfgP['data_inicio'],
                    $cfgP['data_fim'],
                    $cfgP['filtro_titulo'],
                    $cfgP['materia_id'],
                    $incluirPct
                );
                $this->provasGeracaoCache[$chave] = $porAluno;
            }
        }

        if ($compIdsManual !== []) {
            $this->notasManuaisGeracaoCache = $this->boletimConfig->getManualNotesPorAlunos($compIdsManual, $alunoIds, $periodoRef);
            $this->notasManuaisGeracaoAtivo = true;
        }
        $this->prefetchIncluirPct = $incluirPct;
        $this->prefetchGeracaoPronto = true;
    }

    /**
     * Expande quadro semanal e anexa faltas uma vez por evento (não a cada aluno).
     *
     * @return array{regra: array<string,mixed>, componentes: list<array<string,mixed>>}
     */
    private function componentesParaSimulacao(array $regra): array
    {
        $regraId = (int) ($regra['id'] ?? 0);
        if ($regraId > 0 && isset($this->expansaoRegraCache[$regraId])) {
            return $this->expansaoRegraCache[$regraId];
        }
        $componentes = $regra['componentes'] ?? [];
        $expansao = $this->expandirRegraQuadroSemanalNaSimulacao($regra, is_array($componentes) ? $componentes : []);
        $componentes = $this->anexarFaltasEventoNotasSeFaltar($expansao['regra'], $expansao['componentes']);
        $expansao['regra']['componentes'] = $componentes;
        $out = ['regra' => $expansao['regra'], 'componentes' => $componentes];
        if ($regraId > 0) {
            $this->expansaoRegraCache[$regraId] = $out;
        }

        return $out;
    }

    /**
     * Simulação da tela inicial: reusa blocos/jornadas/faltas já salvos no evento.
     * Colunas s1/s3… herdam tipo/blocos da peça "semanal" quando o salvo não tem a semana.
     *
     * @param array<string,mixed> $regra
     * @return array<string,mixed>
     */
    private function mesclarFontesSalvasNaRegraParaSimulacao(array $regra): array
    {
        $regraId = (int) ($regra['id'] ?? 0);
        if ($regraId <= 0) {
            return $regra;
        }
        $salva = $this->boletimConfig->getRuleById($regraId);
        if (!is_array($salva) || empty($salva['componentes']) || !is_array($salva['componentes'])) {
            return $regra;
        }
        $salvas = [];
        $fonteSemanal = null;
        foreach ($salva['componentes'] as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $cod = strtolower(trim((string) ($comp['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $salvas[$cod] = $comp;
            if ($fonteSemanal === null && $this->componenteEhFonteSemanalSalva($comp)) {
                $fonteSemanal = $comp;
            }
        }
        if ($salvas === []) {
            return $regra;
        }
        $componentes = [];
        foreach ((array) ($regra['componentes'] ?? []) as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $cod = strtolower(trim((string) ($comp['codigo'] ?? '')));
            $origem = strtolower(trim((string) ($comp['source_type'] ?? '')));
            if ($origem === 'calculado') {
                $componentes[] = $comp;
                continue;
            }
            $ehSemana = BoletimQuadroLayoutHelper::codigoEhSemana($cod);
            $salvaComp = $salvas[$cod] ?? null;
            if ($salvaComp === null && $ehSemana && is_array($fonteSemanal)) {
                $salvaComp = $fonteSemanal;
            }
            if (!is_array($salvaComp)) {
                $componentes[] = $comp;
                continue;
            }

            $cfgDraft = $this->decodeComponenteConfig($comp);
            $cfgFonte = $this->decodeComponenteConfig($salvaComp);
            $cfg = $cfgFonte;

            // Mantém layout/semana do rascunho (s1… no quadro).
            foreach (['layout_group', 'layout_type', 'layout', 'semana', 'group_line'] as $chaveLayout) {
                if (isset($cfgDraft[$chaveLayout]) && $cfgDraft[$chaveLayout] !== '' && $cfgDraft[$chaveLayout] !== null) {
                    $cfg[$chaveLayout] = $cfgDraft[$chaveLayout];
                }
            }
            if ($ehSemana) {
                if (!isset($cfg['semana']) || (int) $cfg['semana'] <= 0) {
                    if (preg_match('/^s([1-9]|[1-9]\d)$/', $cod, $mSem)) {
                        $cfg['semana'] = (int) $mSem[1];
                    }
                }
                $cfg['layout_type'] = $cfg['layout_type'] ?? 'semana_nq';
                if (empty($cfg['layout_group']) && !empty($cfgDraft['layout_group'])) {
                    $cfg['layout_group'] = $cfgDraft['layout_group'];
                }
            }

            // Fontes de prova: preferir salvo; se vazio, manter draft.
            foreach (['tipo_avaliacao_id', 'tipo_avaliacao_nome', 'prova_bimestres', 'blocos_ids_manual', 'grupo_regras_tipo_id', 'grupo_regras_marca_id'] as $chaveFonte) {
                $vSalvo = $cfgFonte[$chaveFonte] ?? null;
                $vDraft = $cfgDraft[$chaveFonte] ?? null;
                $salvoVazio = $vSalvo === null || $vSalvo === '' || $vSalvo === [] || $vSalvo === 0 || $vSalvo === '0';
                if ($salvoVazio && $vDraft !== null && $vDraft !== '' && $vDraft !== []) {
                    $cfg[$chaveFonte] = $vDraft;
                }
            }

            if ($origem === 'jornadas') {
                if (isset($cfgDraft['distribuicao_notas'])) {
                    $cfg['distribuicao_notas'] = $cfgDraft['distribuicao_notas'];
                }
                if (array_key_exists('faixas_percentuais', $cfgDraft)) {
                    $cfg['faixas_percentuais'] = $cfgDraft['faixas_percentuais'];
                }
                if (isset($cfgDraft['jornada_ids']) && is_array($cfgDraft['jornada_ids']) && $cfgDraft['jornada_ids'] !== []) {
                    $cfg['jornada_ids'] = $cfgDraft['jornada_ids'];
                }
                if (isset($cfgDraft['jornada_bimestres']) && is_array($cfgDraft['jornada_bimestres'])) {
                    $cfg['jornada_bimestres'] = $cfgDraft['jornada_bimestres'];
                }
                if (array_key_exists('nota_unica_incluir_materias', $cfgDraft) && is_array($cfgDraft['nota_unica_incluir_materias'])) {
                    $cfg['nota_unica_incluir_materias'] = $cfgDraft['nota_unica_incluir_materias'];
                    unset($cfg['nota_unica_omitir_materias']);
                }
            }

            $nome = trim((string) ($comp['nome'] ?? ''));
            $out = $salvaComp;
            // Semana do quadro: mantém codigo/nome/layout do draft (s1, S1…).
            if ($ehSemana) {
                $out['codigo'] = (string) ($comp['codigo'] ?? $cod);
                if ($nome !== '') {
                    $out['nome'] = $nome;
                }
                $out['source_type'] = (string) ($comp['source_type'] ?? $out['source_type'] ?? 'provas_sistema');
                if (array_key_exists('usar_percentual', $comp)) {
                    $out['usar_percentual'] = (int) ((int) $comp['usar_percentual'] ? 1 : 0);
                } else {
                    $out['usar_percentual'] = 1;
                }
            } else {
                if ($nome !== '') {
                    $out['nome'] = $nome;
                }
                if (array_key_exists('usar_percentual', $comp)) {
                    $out['usar_percentual'] = (int) ((int) $comp['usar_percentual'] ? 1 : 0);
                }
            }
            if (!empty($comp['materia_unica'])) {
                $out['materia_unica'] = 1;
                $out['materia_unica_modo'] = $this->normalizeMateriaUnicaModo($comp['materia_unica_modo'] ?? null);
            }

            // Semana do quadro: igual ao assistente — tipo + busca dinâmica por semana.
            // blocos_ids fixos (snapshot) deixam S1/S3 vazios quando a lista não cobre a semana.
            // Exceção: seleção manual explícita (blocos_ids_manual) — mantém os IDs.
            if ($ehSemana) {
                $tipoSemana = (int) ($cfg['tipo_avaliacao_id'] ?? 0);
                if ($tipoSemana <= 0) {
                    $tipoSemana = (int) ($cfgDraft['tipo_avaliacao_id'] ?? $comp['tipo_avaliacao_id'] ?? 0);
                }
                if ($tipoSemana <= 0 && is_array($fonteSemanal)) {
                    $cfgFs = $this->decodeComponenteConfig($fonteSemanal);
                    $tipoSemana = (int) ($cfgFs['tipo_avaliacao_id'] ?? $fonteSemanal['tipo_avaliacao_id'] ?? 0);
                    if ($tipoSemana > 0) {
                        $cfg['tipo_avaliacao_id'] = $tipoSemana;
                        if (!empty($cfgFs['tipo_avaliacao_nome'])) {
                            $cfg['tipo_avaliacao_nome'] = $cfgFs['tipo_avaliacao_nome'];
                        }
                    }
                }
                $manualBlocos = !empty($cfg['blocos_ids_manual']) || !empty($cfgDraft['blocos_ids_manual']);
                unset($cfg['grupo_regras_tipo_id'], $cfg['grupo_regras_marca_id'], $cfg['grupo_regras_notas_id']);
                if ($manualBlocos) {
                    $cfg['blocos_ids_manual'] = 1;
                    $blocosDraft = $this->idsBlocosDoComponente($comp);
                    $blocosSalvo = $this->idsBlocosDoComponente($salvaComp);
                    if ($blocosDraft !== []) {
                        $out['blocos_ids'] = implode(',', $blocosDraft);
                    } elseif ($blocosSalvo !== []) {
                        $out['blocos_ids'] = implode(',', $blocosSalvo);
                    }
                } elseif ($tipoSemana > 0) {
                    $cfg['tipo_avaliacao_id'] = $tipoSemana;
                    $out['tipo_avaliacao_id'] = $tipoSemana;
                    $out['blocos_ids'] = '';
                    unset($out['bloco_id'], $cfg['blocos_ids']);
                    $out['filtro_titulo'] = '';
                } else {
                    $blocosDraft = $this->idsBlocosDoComponente($comp);
                    $blocosSalvo = $this->idsBlocosDoComponente($salvaComp);
                    if ($blocosSalvo !== []) {
                        $out['blocos_ids'] = implode(',', $blocosSalvo);
                    } elseif ($blocosDraft !== []) {
                        $out['blocos_ids'] = implode(',', $blocosDraft);
                    }
                }
                if ($tipoSemana > 0) {
                    $cfg['tipo_avaliacao_id'] = $tipoSemana;
                    $out['tipo_avaliacao_id'] = $tipoSemana;
                }
                $out['usar_percentual'] = 1;
                $out['materia_unica'] = 1;
                $out['materia_unica_modo'] = $this->normalizeMateriaUnicaModo(
                    $comp['materia_unica_modo'] ?? $salvaComp['materia_unica_modo'] ?? 'soma'
                );
            } else {
                $blocosDraft = $this->idsBlocosDoComponente($comp);
                $blocosSalvo = $this->idsBlocosDoComponente($salvaComp);
                if ($blocosSalvo !== []) {
                    $out['blocos_ids'] = implode(',', $blocosSalvo);
                } elseif ($blocosDraft !== []) {
                    $out['blocos_ids'] = implode(',', $blocosDraft);
                }
                if (!empty($comp['filtro_titulo']) && empty($out['filtro_titulo'])) {
                    $out['filtro_titulo'] = $comp['filtro_titulo'];
                }
            }

            $out['config'] = $cfg;
            $out['config_json'] = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!empty($cfg['layout_group'])) {
                $out['layout_group'] = $cfg['layout_group'];
            }
            if (!empty($cfg['layout_type'])) {
                $out['layout_type'] = $cfg['layout_type'];
            }
            $componentes[] = $out;
        }
        if ($componentes !== []) {
            $regra['componentes'] = $this->enriquecerSemanasComFonteSemanal($componentes, $componentes);
        }
        if ($this->grupoRegrasNotasIdDaRegra($regra) <= 0) {
            $gid = $this->grupoRegrasNotasIdDaRegra($salva);
            if ($gid > 0) {
                $regra['grupo_regras_notas_id'] = $gid;
            }
        }

        return $regra;
    }

    /**
     * @param array<string,mixed> $comp
     */
    private function componenteEhFonteSemanalSalva(array $comp): bool
    {
        $cod = strtolower(trim((string) ($comp['codigo'] ?? '')));
        if ($cod === 'semanal' || $cod === 'prova_semanal') {
            return true;
        }
        if (BoletimQuadroLayoutHelper::codigoEhSemana($cod)) {
            return false;
        }
        $nome = mb_strtolower(trim((string) ($comp['nome'] ?? '')), 'UTF-8');

        return str_contains($nome, 'semanal') || str_contains($nome, 'prova semanal');
    }

    /**
     * @param array<string,mixed> $comp
     * @return list<int>
     */
    private function idsBlocosDoComponente(array $comp): array
    {
        $ids = [];
        $raw = $comp['blocos_ids'] ?? null;
        if (is_array($raw)) {
            foreach ($raw as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            foreach (explode(',', $raw) as $id) {
                $id = (int) trim($id);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $blocoId = (int) ($comp['bloco_id'] ?? 0);
        if ($blocoId > 0) {
            $ids[] = $blocoId;
        }
        $cfg = $this->decodeComponenteConfig($comp);
        foreach ((array) ($cfg['blocos_ids'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array{inicio:?string,fim:?string} $range
     * @param array<string, array<string, mixed>> $chavesProvas
     * @param list<int> $compIdsManual
     * @param array<string, bool> $visitados
     */
    private function acumularPrefetchDaRegra(
        array $regra,
        array $range,
        bool &$incluirPct,
        array &$chavesProvas,
        array &$compIdsManual,
        ?SchoolAbsence &$absence,
        array &$visitados
    ): void {
        $codigo = trim((string) ($regra['codigo'] ?? ''));
        $vid = $codigo !== '' ? $codigo : ('id:' . (int) ($regra['id'] ?? 0));
        if ($vid === 'id:0' || isset($visitados[$vid])) {
            return;
        }
        $visitados[$vid] = true;

        $expansao = $this->componentesParaSimulacao($regra);
        foreach ($expansao['componentes'] as $componente) {
            if (!is_array($componente)) {
                continue;
            }
            $src = (string) ($componente['source_type'] ?? 'provas_sistema');
            $cid = (int) ($componente['id'] ?? 0);
            if ($cid > 0 && in_array($src, ['provas_sistema', 'jornadas', 'manual'], true)) {
                $compIdsManual[] = $cid;
            }
            if (!empty($componente['usar_percentual'])) {
                $incluirPct = true;
            }
            if ($src === 'evento_boletim') {
                $cfgEvento = $this->parseEventoConfigFromComponente($componente);
                $ref = $this->boletimConfig->getRuleByCode((string) ($cfgEvento['regra_codigo'] ?? ''));
                if (is_array($ref) && $this->regraNoEscopoGeracao($ref)) {
                    $this->acumularPrefetchDaRegra(
                        $ref,
                        $range,
                        $incluirPct,
                        $chavesProvas,
                        $compIdsManual,
                        $absence,
                        $visitados
                    );
                }
                continue;
            }
            if ($src === 'faltas_evento') {
                $cfgF = $this->parseFaltasConfigFromComponente($componente);
                $eventoIdF = (int) ($cfgF['evento_id'] ?? 0);
                if ($eventoIdF > 0 && !isset($this->faltasEventoCache[$eventoIdF])) {
                    if ($absence === null) {
                        $absence = new SchoolAbsence();
                    }
                    $this->faltasEventoCache[$eventoIdF] = $absence->getLancamentosMapByEvento($eventoIdF);
                    $ev = $absence->getEventoById($eventoIdF);
                    $this->eventosFaltasCache[$eventoIdF] = is_array($ev) ? $ev : [];
                }
            }
            if ($src !== 'provas_sistema' && $src !== '') {
                continue;
            }
            $blocoIds = $this->resolveBlocoIdsFromComponentePersisted($componente);
            $bimestresComp = $this->bimestresDoComponenteOuRegra($componente, $regra);
            $cfgBlocos = $this->decodeComponenteConfig($componente);
            if ($blocoIds !== [] && $bimestresComp !== [] && empty($cfgBlocos['blocos_ids_manual'])) {
                $blocoIds = $this->boletimConfig->filtrarBlocoIdsPorBimestres($blocoIds, $bimestresComp);
            }
            $resolvidoQuadro = $this->resolverBlocosQuadroDoComponente(
                $componente,
                $blocoIds,
                $range['inicio'] ?? null,
                $range['fim'] ?? null,
                $bimestresComp,
                (int) ($regra['ano_letivo'] ?? 0)
            );
            $blocoIds = $resolvidoQuadro['bloco_ids'];
            $quadroForcado = !empty($resolvidoQuadro['forcada']);
            $filtroTitulo = trim((string) ($componente['filtro_titulo'] ?? ''));
            $filtroTitulo = $filtroTitulo !== '' ? $filtroTitulo : null;
            $materiasFiltro = $this->parseMateriasIdsFromComponente($componente);
            $materiaFiltroConsulta = $materiasFiltro !== []
                ? null
                : ((int) ($componente['materia_id'] ?? 0) > 0 ? (int) $componente['materia_id'] : null);
            if ($blocoIds === [] && $quadroForcado) {
                continue;
            }
            $tinhaBlocos = $blocoIds !== [];
            $blocoIds = $this->aplicarFiltroTurmasNosBlocos($blocoIds);
            if ($tinhaBlocos && $blocoIds === []) {
                continue;
            }
            $inicioChave = $blocoIds !== [] ? null : ($range['inicio'] ?? null);
            $fimChave = $blocoIds !== [] ? null : ($range['fim'] ?? null);
            $chave = $this->chaveCacheProvasGeracao($blocoIds, $filtroTitulo, $materiaFiltroConsulta, $inicioChave, $fimChave);
            $chavesProvas[$chave] = [
                'bloco_ids' => $blocoIds,
                'filtro_titulo' => $filtroTitulo,
                'materia_id' => $materiaFiltroConsulta,
                'data_inicio' => $inicioChave,
                'data_fim' => $fimChave,
            ];
        }
    }

    /**
     * @param list<int> $blocoIds
     */
    private function chaveCacheProvasGeracao(
        array $blocoIds,
        ?string $filtroTitulo,
        ?int $materiaId,
        ?string $inicio,
        ?string $fim
    ): string {
        $blocoIds = array_values(array_unique(array_filter(array_map('intval', $blocoIds), static function ($id) {
            return $id > 0;
        })));
        sort($blocoIds);

        return implode(',', $blocoIds) . '|' . (string) $filtroTitulo . '|' . (int) $materiaId . '|' . (string) $inicio . '|' . (string) $fim;
    }

    /**
     * @param list<array<string, mixed>> $alunos
     */
    private function definirEscopoGeracaoFromAlunos(array $alunos): void
    {
        $turmas = [];
        $series = [];
        foreach ($alunos as $aluno) {
            if (!is_array($aluno)) {
                continue;
            }
            $tid = (int) ($aluno['turma_id'] ?? 0);
            if ($tid > 0) {
                $turmas[$tid] = true;
            }
            $sid = (int) ($aluno['serie_id'] ?? 0);
            if ($sid > 0) {
                $series[$sid] = true;
            }
        }
        $this->turmasEscopoGeracao = array_map('intval', array_keys($turmas));
        $this->seriesEscopoGeracao = array_map('intval', array_keys($series));
    }

    /**
     * Desmarcar série/turma no evento só reduz alunos; os blocos de prova gravados
     * no componente continuam os da escola inteira. Filtra pela turma dos alunos
     * desta geração para não varrer pauta do Fundamental II ao gerar só o EM.
     *
     * @param list<int> $blocoIds
     * @return list<int>
     */
    private function aplicarFiltroTurmasNosBlocos(array $blocoIds): array
    {
        if ($this->turmasEscopoGeracao === [] || $blocoIds === []) {
            return $blocoIds;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $blocoIds), static function ($id) {
            return $id > 0;
        })));
        sort($ids);
        $chave = implode(',', $ids);
        if (isset($this->blocosFiltradosPorTurmaCache[$chave])) {
            return $this->blocosFiltradosPorTurmaCache[$chave];
        }

        return $this->blocosFiltradosPorTurmaCache[$chave] = $this->boletimConfig->filtrarBlocoIdsPorTurmas(
            $ids,
            $this->turmasEscopoGeracao
        );
    }

    private function regraNoEscopoGeracao(array $regra): bool
    {
        if ($this->turmasEscopoGeracao === [] && $this->seriesEscopoGeracao === []) {
            return true;
        }
        $turmasRef = $this->parseTurmasIdsFromRegra($regra);
        if ($turmasRef !== [] && $this->turmasEscopoGeracao !== []) {
            return array_intersect($turmasRef, $this->turmasEscopoGeracao) !== [];
        }
        $seriesRef = $this->parseSeriesIdsFromRegra($regra);
        if ($seriesRef !== [] && $this->seriesEscopoGeracao !== []) {
            return array_intersect($seriesRef, $this->seriesEscopoGeracao) !== [];
        }

        return true;
    }

    /**
     * @param list<int> $alunoIds
     */
    private function sincronizarFichasVidaEscolarLote(
        array $alunoIds,
        array $usuario,
        ?string $periodoRef,
        ?int $regraId
    ): void {
        $alunoIds = array_values(array_unique(array_filter(array_map('intval', $alunoIds), static function ($id) {
            return $id > 0;
        })));
        if ($alunoIds === []) {
            return;
        }
        try {
            if (!class_exists('LayoutHelper', false)) {
                require_once dirname(__DIR__, 2) . '/Core/LayoutHelper.php';
            }
            if (!\LayoutHelper::isModuleEnabled('vida_escolar')) {
                return;
            }
            require_once dirname(__DIR__, 2) . '/Modulos/vida-escolar/Services/VidaEscolarService.php';
            $vida = new \App\Modulos\VidaEscolar\Services\VidaEscolarService();
            $vida->sincronizarDeEventosGeradosEmLote($alunoIds, $usuario, $regraId, $periodoRef, true);
        } catch (Throwable $e) {
            error_log('Vida escolar sync lote: ' . $e->getMessage());
        }
    }

    public function simularRegraAluno(
        array $regra,
        int $alunoId,
        string $periodoRef,
        ?string $rangeInicioOverride = null,
        ?string $rangeFimOverride = null,
        array $visitedRuleCodes = [],
        bool $forcarAgrupamentoLinhas = false,
        bool $pularAgrupamentoLinhas = false
    ): array
    {
        $regraIdCache = (int) ($regra['id'] ?? 0);
        $chaveSimulacao = $regraIdCache . ':' . $alunoId . ':' . $periodoRef . ':'
            . (string) ($rangeInicioOverride ?? '') . ':' . (string) ($rangeFimOverride ?? '')
            . ':' . ($forcarAgrupamentoLinhas ? 'agrupar' : 'auto')
            . ':' . ($pularAgrupamentoLinhas ? 'sem-grupo' : 'com-grupo');
        if ($regraIdCache > 0 && isset($this->simulacaoAlunoCache[$chaveSimulacao])) {
            return $this->simulacaoAlunoCache[$chaveSimulacao];
        }

        $roundMode = $this->normalizeRoundMode((string) ($regra['round_mode'] ?? 'none'));
        $range = [
            'inicio' => $rangeInicioOverride ? ($rangeInicioOverride . ' 00:00:00') : null,
            'fim' => $rangeFimOverride ? ($rangeFimOverride . ' 23:59:59') : null,
        ];
        if ($range['inicio'] === null || $range['fim'] === null) {
            $range = $this->periodoToRange($periodoRef);
        }
        $expansaoQuadro = $this->componentesParaSimulacao($regra);
        $regra = $expansaoQuadro['regra'];
        $componentes = $expansaoQuadro['componentes'];
        $regra['componentes'] = $componentes;
        $this->carregarMidsSemArredondamentoGrupo(
            $componentes,
            (string) ($regra['exibir_em'] ?? 'boletim'),
            $forcarAgrupamentoLinhas
        );

        $componentesResultado = [];
        $valoresPorCodigo = [];
        $faltantesObrigatorios = [];
        /** @var array<string, array<int, float>> */
        $matrizPorCodigo = [];
        /** @var array<string, array<int, array{acertos:int,total:int}>> */
        $matrizPercentStatsPorCodigo = [];
        /** @var array<int, string> */
        $materiaNomesPorId = [];
        /** @var array<string, list<array{nome:string,valor:float}>> */
        $notasJornadaPorNome = [];
        // Quando algum componente puxa de outro evento via source_type='evento_boletim',
        // herdamos os agrupamentos por linha (group_line) da regra de origem.
        // - $materiasAgrupadasHerdadas: mids de matérias-filhas que devem ficar OCULTAS no destino
        //   (já estão consolidadas na linha do grupo, ex.: "Língua Portuguesa").
        // - $inheritedGroupMidsByKey: mapeia "<refRegraId>:<sourceVirtualMid>" para um mid sintético
        //   local (negativo, em faixa distinta da geração interna que começa em -1) reutilizado entre
        //   componentes do mesmo destino para que todas as colunas vão para a MESMA linha do grupo.
        /** @var array<int, true> */
        $materiasAgrupadasHerdadas = [];
        /** @var array<int, true> */
        $materiasIndependentesHerdadas = [];
        /** @var array<string, true> */
        $nomesMateriasIndependentesHerdadas = [];
        /** @var array<string, int> */
        $inheritedGroupMidsByKey = [];
        $inheritedGroupMidSeq = -10001;

        foreach ($componentes as $componente) {
            $codigo = (string) ($componente['codigo'] ?? '');
            if ($codigo === '') {
                continue;
            }
            if (($componente['source_type'] ?? '') === 'calculado') {
                continue;
            }

            $valor = null;
            $detalhes = [];
            $bloqueado = false;

            // Sobrescrita manual por matéria (mesmo mecanismo usado em blocos
            // calculados): permite editar direto na tabela a nota de uma matéria
            // num bloco de Prova/Jornada, sem afetar as demais matérias nem os
            // demais alunos. Para blocos 'manual' (ex.: ENAC), o valor é global
            // por componente e continua tratado pelo ramo 'manual' abaixo.
            $compIdOverride = (int) ($componente['id'] ?? 0);
            $overridesPorMateria = ($compIdOverride > 0 && in_array(($componente['source_type'] ?? ''), ['provas_sistema', 'jornadas'], true))
                ? $this->obterNotasManuaisComponenteCached($compIdOverride, $alunoId, $periodoRef)
                : [];

            if (($componente['source_type'] ?? 'provas_sistema') === 'nenhuma') {
                $detalhes['origem'] = 'nenhuma';
            } elseif (($componente['source_type'] ?? 'provas_sistema') === 'manual') {
                $compIdManual = (int) ($componente['id'] ?? 0);
                $manual = $compIdManual > 0
                    ? $this->obterNotaManualCached($compIdManual, $alunoId, $periodoRef, 0)
                    : null;
                if ($manual) {
                    $bloqueado = (int) ($manual['bloqueado'] ?? 0) === 1;
                    $detalhes['manual_id'] = (int) $manual['id'];
                    if (is_numeric($manual['nota'] ?? null)) {
                        $valor = (float) $manual['nota'];
                    } else {
                        // Sobrescrita explícita "sem nota" (nota = NULL): mantém $valor
                        // vazio, força o traço mesmo que fosse obrigatório.
                        $detalhes['manual_vazio'] = true;
                    }
                } elseif ($compIdManual > 0 && !$this->notasManuaisGeracaoAtivo) {
                    $outros = $this->boletimConfig->listManualNotesOtherPeriods($compIdManual, $alunoId, $periodoRef, 8);
                    if (!empty($outros)) {
                        $detalhes['manual_outros_periodos'] = $outros;
                    }
                }
            } elseif (($componente['source_type'] ?? '') === 'jornadas') {
                $alunoRow = $this->buscarAluno($alunoId);
                $turmaId = (int) ($alunoRow['turma_id'] ?? 0);
                if ($turmaId <= 0 && $alunoId > 0) {
                    $rowTurma = Database::getInstance()->fetch(
                        'SELECT turma_id FROM alunos WHERE id = :id LIMIT 1',
                        ['id' => $alunoId]
                    );
                    $turmaId = (int) ($rowTurma['turma_id'] ?? 0);
                }
                $cfg = $this->parseJornadasConfigFromComponente($componente);
                // Não sobrescrever o range global da simulação (GET data_inicio/data_fim).
                $rangePeriodoRef = $this->periodoToRange($periodoRef);
                $dataIni = $cfg['data_ini'];
                $dataFim = $cfg['data_fim'];
                if ($dataIni === null && !empty($range['inicio'])) {
                    $dataIni = substr((string) $range['inicio'], 0, 10);
                }
                if ($dataFim === null && !empty($range['fim'])) {
                    $dataFim = substr((string) $range['fim'], 0, 10);
                }
                // Fallback para período_ref só quando não houver intervalo explícito.
                if ($dataIni === null && !empty($rangePeriodoRef['inicio'])) {
                    $dataIni = substr((string) $rangePeriodoRef['inicio'], 0, 10);
                }
                if ($dataFim === null && !empty($rangePeriodoRef['fim'])) {
                    $dataFim = substr((string) $rangePeriodoRef['fim'], 0, 10);
                }
                $detalhes['jornada_ids'] = $cfg['jornada_ids'];
                $detalhes['data_ini'] = $dataIni;
                $detalhes['data_fim'] = $dataFim;

                if ($turmaId <= 0) {
                    $detalhes['erro'] = 'Aluno sem turma vinculada.';
                } else {
                    $jb = new JourneyBoletimLancamento();
                    $escalaJ = max(0.01, (float) ($componente['escala_max'] ?? 10));
                    // Se houver tabela por faixas configurada, ela tem prioridade
                    // sobre o modo proporcional linear.
                    $temFaixasJ = !empty($cfg['faixas_percentuais']) && is_array($cfg['faixas_percentuais']);
                    $linearJ = !empty($componente['usar_percentual']) && !$temFaixasJ;
                    $calcJ = $this->normalizeCalcType((string) ($componente['calc_type'] ?? 'media'));
                    $distJ = strtolower(trim((string) ($cfg['distribuicao_notas'] ?? 'por_materia')));
                    $notaUnicaTodasLinhas = ($distJ === 'nota_unica_todas_linhas');
                    $fonteMerged = $notaUnicaTodasLinhas
                        ? $this->mergeFonteNotaUnicaJornadasPorGrupo(
                            $componentes,
                            (array) ($cfg['nota_unica_fonte_por_materia'] ?? []),
                            (array) ($cfg['nota_unica_fonte_por_grupo'] ?? [])
                        )
                        : [];
                    $notaUnicaExtras = $notaUnicaTodasLinhas ? ['fonte_por_materia' => $fonteMerged] : null;
                    $resJ = $jb->notasPorMateriaAluno(
                        $alunoId,
                        $turmaId,
                        $cfg['jornada_ids'],
                        $dataIni,
                        $dataFim,
                        $escalaJ,
                        $linearJ,
                        $calcJ,
                        $cfg['faixas_percentuais'] ?? [],
                        $notaUnicaTodasLinhas,
                        $notaUnicaExtras
                    );
                    if ($linearJ) {
                        $detalhes['jornada_nota_linear'] = 1;
                    }
                    $totJ = (int) ($resJ['total_jornadas_escopo'] ?? 0);
                    $detalhes['concluidas'] = (int) ($resJ['concluidas_agregado'] ?? 0);
                    $detalhes['total_jornadas_escopo'] = $totJ;
                    $detalhes['percentual_jornadas'] = $notaUnicaTodasLinhas
                        ? (float) ($resJ['percentual_conclusao_escopo'] ?? 0)
                        : (float) ($resJ['percentual_medio_jornadas'] ?? 0);
                    if ($notaUnicaTodasLinhas) {
                        $detalhes['distribuicao_jornadas'] = 'nota_unica_todas_linhas';
                        $padrao = $resJ['nota_unica_valor_padrao'] ?? null;
                        if (!is_numeric($padrao)) {
                            $porMap = (array) ($resJ['por_materia'] ?? []);
                            $padrao = $porMap !== [] ? (float) reset($porMap) : null;
                        } else {
                            $padrao = (float) $padrao;
                        }
                        $detalhes['nota_global_jornadas'] = is_numeric($padrao) ? (float) $padrao : null;
                        $detalhes['nota_unica_omitir_materias'] = array_values(
                            array_map('intval', (array) ($cfg['nota_unica_omitir_materias'] ?? []))
                        );
                        $detalhes['nota_unica_substituicao_por_materia'] = (array) ($resJ['nota_unica_substituicao_por_materia'] ?? []);
                    }
                    $detalhes['jornadas_materias_distintas'] = count($resJ['por_materia'] ?? []);
                    foreach ((array) ($resJ['por_nome'] ?? []) as $itemNome) {
                        if (!is_array($itemNome)) {
                            continue;
                        }
                        $nomeJornada = trim((string) ($itemNome['nome'] ?? ''));
                        if ($nomeJornada === '' || !is_numeric($itemNome['valor'] ?? null)) {
                            continue;
                        }
                        $notasJornadaPorNome[$codigo][] = [
                            'nome' => $nomeJornada,
                            'valor' => (float) $itemNome['valor'],
                        ];
                    }
                    if ($totJ <= 0) {
                        $detalhes['aviso_jornadas'] = 'Nenhuma jornada do escopo aplica-se à turma deste aluno (verifique turmas na jornada).';
                    } else {
                        $roundModeComp = $this->resolveRoundModeComponente($componente, $roundMode);
                        $matrizPorCodigo[$codigo] = $this->applyRoundModeToMateriaMap((array) ($resJ['por_materia'] ?? []), $roundModeComp);
                        foreach ($resJ['notas_lista'] ?? [] as $nj) {
                            $midN = (int) ($nj['materia_id'] ?? 0);
                            $nomeN = trim((string) ($nj['materia_nome'] ?? ''));
                            if ($midN > 0 && $nomeN !== '') {
                                $materiaNomesPorId[$midN] = $nomeN;
                            }
                        }
                        $listaGlobal = $resJ['notas_lista'] ?? [];
                        $valor = $this->applyRoundMode($this->agruparNotas($listaGlobal, 'media'), $roundModeComp);
                    }
                }
            } elseif (($componente['source_type'] ?? '') === 'evento_boletim') {
                $cfgEvento = $this->parseEventoConfigFromComponente($componente);
                $codEvento = $cfgEvento['regra_codigo'];
                $codColuna = $cfgEvento['componente_codigo'];
                $detalhes['regra_codigo'] = $codEvento;
                $detalhes['componente_codigo'] = $codColuna;

                if ($codEvento === '') {
                    $detalhes['erro'] = 'Informe o código (slug) do evento de origem.';
                } else {
                    $codigoAtual = trim((string) ($regra['codigo'] ?? ''));
                    $stack = $visitedRuleCodes;
                    if ($codigoAtual !== '') {
                        $stack[] = $codigoAtual;
                    }
                    $stack = array_values(array_unique(array_filter($stack, static function ($v) {
                        return trim((string) $v) !== '';
                    })));
                    if (in_array($codEvento, $stack, true)) {
                        $detalhes['erro'] = 'Referência circular de evento detectada (' . $codEvento . ').';
                    } else {
                        $refRegra = $this->boletimConfig->getRuleByCode($codEvento);
                        if (!$refRegra) {
                            $detalhes['erro'] = 'Evento não encontrado pelo código: ' . $codEvento;
                        } elseif ($this->boletimConfig->normalizeFinalidade((string) ($refRegra['finalidade'] ?? 'oficial'))
                            !== $this->boletimConfig->normalizeFinalidade((string) ($regra['finalidade'] ?? 'oficial'))
                        ) {
                            $tipoEsperado = $this->boletimConfig->normalizeFinalidade((string) ($regra['finalidade'] ?? 'oficial')) === 'complementar'
                                ? 'Notas extra (curso complementar)'
                                : 'Notas da série';
                            $detalhes['erro'] = 'O evento “' . trim((string) ($refRegra['nome'] ?? $codEvento))
                                . '” não é ' . $tipoEsperado . '. O boletim extra só puxa Notas extra, e o oficial só puxa Notas da série.';
                        } elseif (!$this->regraNoEscopoGeracao($refRegra)) {
                            $detalhes['aviso_escopo'] = 'Evento de origem fora das turmas/séries desta geração.';
                        } else {
                            $codColunaResolvido = $this->resolveEventoComponenteCodigo($refRegra, $codColuna);
                            $detalhes['componente_codigo_resolvido'] = $codColunaResolvido;
                            if ($codColuna === '') {
                                $detalhes['componente_codigo'] = $codColunaResolvido;
                            }
                            $resRef = $this->simularRegraAluno(
                                $refRegra,
                                $alunoId,
                                $periodoRef,
                                null,
                                null,
                                $stack,
                                true
                            );
                            $matrizRef = $resRef['matriz_materias'] ?? null;
                            $linhasRef = is_array($matrizRef) ? ($matrizRef['linhas'] ?? []) : [];
                            $codColunaResolvido = $this->resolveEventoComponenteCodigoNasLinhas(
                                $linhasRef,
                                $codColuna,
                                $codColunaResolvido
                            );
                            $detalhes['componente_codigo_resolvido'] = $codColunaResolvido;

                            // Herda os agrupamentos por linha (group_line) da regra de origem.
                            // Só oculta no destino as matérias-filhas que realmente deixaram de existir
                            // como linhas independentes na origem; linhas como Redação devem ser mantidas.
                            $refRegraIdHer = (int) ($refRegra['id'] ?? 0);
                            // aplicarAgrupamentoLinhasPorComponente atribui -1, -2, ... aos
                            // grupos, na ordem em que suas chaves aparecem. Reconstruímos esse
                            // mapa para preservar a identidade semântica do grupo ao importar
                            // colunas de regras diferentes (ex.: B1 e B2). Usar apenas o ID da
                            // regra de origem criava duas linhas para o mesmo código de grupo.
                            $groupKeyByVirtualMidHer = [];
                            $groupVirtualMidByKeyHer = [];
                            $nextGroupVirtualMidHer = -1;
                            $materiasVisiveisRef = [];
                            $nomesVisiveisRef = [];
                            foreach ((array) $linhasRef as $linhaVisivelRef) {
                                $midVisivelRef = (int) ($linhaVisivelRef['materia_id'] ?? 0);
                                if ($midVisivelRef <= 0) {
                                    continue;
                                }
                                $materiasVisiveisRef[$midVisivelRef] = true;
                                $nomeVisivelKeyRef = $this->canonicalMateriaNomeKey((string) ($linhaVisivelRef['materia_nome'] ?? ''));
                                if ($nomeVisivelKeyRef !== '') {
                                    $nomesVisiveisRef[$nomeVisivelKeyRef] = true;
                                }
                            }
                            if (!is_array($this->materiasDisponiveisCache)) {
                                $this->materiasDisponiveisCache = $this->boletimConfig->getAvailableSubjects(1000);
                            }
                            $nomesCatalogoRef = [];
                            foreach ($this->materiasDisponiveisCache as $materiaCatalogoRef) {
                                $midCatalogoRef = (int) ($materiaCatalogoRef['id'] ?? 0);
                                if ($midCatalogoRef > 0) {
                                    $nomesCatalogoRef[$midCatalogoRef] = (string) ($materiaCatalogoRef['nome'] ?? '');
                                }
                            }
                            foreach (array_keys($materiasVisiveisRef) as $midVisivelRef) {
                                $materiasIndependentesHerdadas[(int) $midVisivelRef] = true;
                            }
                            foreach (array_keys($nomesVisiveisRef) as $nomeVisivelKeyRef) {
                                $nomesMateriasIndependentesHerdadas[(string) $nomeVisivelKeyRef] = true;
                            }
                            foreach (array_keys($materiasAgrupadasHerdadas) as $midAgrupadaAnterior) {
                                $nomeAnteriorKey = $this->canonicalMateriaNomeKey((string) ($nomesCatalogoRef[(int) $midAgrupadaAnterior] ?? ''));
                                if (isset($materiasIndependentesHerdadas[(int) $midAgrupadaAnterior])
                                    || ($nomeAnteriorKey !== '' && isset($nomesMateriasIndependentesHerdadas[$nomeAnteriorKey]))) {
                                    unset($materiasAgrupadasHerdadas[(int) $midAgrupadaAnterior]);
                                }
                            }
                            foreach ((array) ($refRegra['componentes'] ?? []) as $compRef) {
                                if (trim((string) ($compRef['codigo'] ?? '')) === '') {
                                    continue;
                                }
                                $grpHer = $this->parseGroupLineConfigFromComponente((array) $compRef);
                                if ($grpHer === null) {
                                    continue;
                                }
                                $groupKeyHer = (string) ($grpHer['key'] ?? '');
                                if ($groupKeyHer !== '' && !isset($groupVirtualMidByKeyHer[$groupKeyHer])) {
                                    $groupVirtualMidByKeyHer[$groupKeyHer] = $nextGroupVirtualMidHer;
                                    $groupKeyByVirtualMidHer[$nextGroupVirtualMidHer] = $groupKeyHer;
                                    $nextGroupVirtualMidHer--;
                                }
                                foreach ((array) ($grpHer['materias_ids'] ?? []) as $midHer) {
                                    $midHer = (int) $midHer;
                                    if ($midHer > 0) {
                                        $nomeHerKey = $this->canonicalMateriaNomeKey((string) ($nomesCatalogoRef[$midHer] ?? ''));
                                        // Se a matéria continua visível como linha independente no evento
                                        // de origem, ela não deve ser ocultada no boletim que o referencia.
                                        // A comparação por nome também cobre cadastros duplicados de Redação.
                                        if (isset($materiasIndependentesHerdadas[$midHer])
                                            || ($nomeHerKey !== '' && isset($nomesMateriasIndependentesHerdadas[$nomeHerKey]))) {
                                            continue;
                                        }
                                        $materiasAgrupadasHerdadas[$midHer] = true;
                                    }
                                }
                            }

                            $resolveLinhasParaMapRef = function (array $linhasRef, string $codColRes) use (
                                &$materiaNomesPorId,
                                &$inheritedGroupMidsByKey,
                                &$inheritedGroupMidSeq,
                                $refRegraIdHer,
                                $groupKeyByVirtualMidHer
                            ): array {
                                $map = [];
                                foreach ($linhasRef as $linRef) {
                                    $midRef = (int) ($linRef['materia_id'] ?? 0);
                                    $notasRef = (array) ($linRef['notas'] ?? []);
                                    $valRef = $notasRef[$codColRes] ?? null;
                                    $nomeRef = trim((string) ($linRef['materia_nome'] ?? ''));
                                    $canonRef = $this->canonicalMateriaNomeKey($nomeRef);
                                    // A Redação costuma participar de colunas diferentes das demais
                                    // matérias no evento de origem (ex.: só tem "Média", sem "Prova
                                    // Semanal"). Quando a célula da coluna específica vem vazia, usamos
                                    // a média da própria linha do bimestre para não perder o período.
                                    if (!is_numeric($valRef) && $canonRef === 'redacao' && is_numeric($linRef['nota_resumo'] ?? null)) {
                                        $valRef = $linRef['nota_resumo'];
                                    }
                                    if (!is_numeric($valRef)) {
                                        continue;
                                    }
                                    // A Redação costuma ter cadastros/IDs diferentes em cada
                                    // bimestre (B1, B2...). Sem ancorar numa linha única, cada
                                    // bimestre cai num mid distinto: a coluna FINAL é calculada
                                    // por mid parcial e a média sai errada (ex.: (9,0 + 0)/2 = 4,5)
                                    // e a linha parece "sumir" do boletim combinado.
                                    if ($canonRef === 'redacao') {
                                        $key = 'canon:redacao';
                                        if (!isset($inheritedGroupMidsByKey[$key])) {
                                            $inheritedGroupMidsByKey[$key] = $inheritedGroupMidSeq;
                                            $inheritedGroupMidSeq--;
                                        }
                                        $localMid = $inheritedGroupMidsByKey[$key];
                                        $map[$localMid] = (float) $valRef;
                                        $materiaNomesPorId[$localMid] = $nomeRef !== '' ? $nomeRef : 'Redação';
                                    } elseif ($midRef > 0) {
                                        $map[$midRef] = (float) $valRef;
                                        if ($nomeRef !== '') {
                                            $materiaNomesPorId[$midRef] = $nomeRef;
                                        }
                                    } elseif ($midRef < 0) {
                                        // Linha agrupada vinda do evento de origem (ex.: "Língua Portuguesa").
                                        // O nome normalizado permite consolidar configurações antigas
                                        // que usaram códigos diferentes para o mesmo grupo em B1/B2.
                                        // Sem nome, usamos o código compartilhado e, por último, a origem.
                                        $groupKeyHer = (string) ($groupKeyByVirtualMidHer[$midRef] ?? '');
                                        $groupLabelKeyHer = $this->normalizeEventoCodigoToken($nomeRef);
                                        if ($groupLabelKeyHer !== '') {
                                            $key = 'label:' . $groupLabelKeyHer;
                                        } elseif ($groupKeyHer !== '') {
                                            $key = 'group:' . $groupKeyHer;
                                        } else {
                                            $key = 'source:' . $refRegraIdHer . ':' . $midRef;
                                        }
                                        if (!isset($inheritedGroupMidsByKey[$key])) {
                                            $inheritedGroupMidsByKey[$key] = $inheritedGroupMidSeq;
                                            $inheritedGroupMidSeq--;
                                        }
                                        $localMid = $inheritedGroupMidsByKey[$key];
                                        $map[$localMid] = (float) $valRef;
                                        if ($nomeRef !== '') {
                                            $materiaNomesPorId[$localMid] = $nomeRef;
                                        }
                                    }
                                }
                                return $map;
                            };

                            $mapRef = $resolveLinhasParaMapRef((array) $linhasRef, (string) $codColunaResolvido);
                            // Só usa fallback automático quando a coluna NÃO foi informada manualmente.
                            // Se foi informada, é melhor mostrar sem dados do que trocar por outra coluna errada.
                            if ($mapRef === [] && $codColuna === '') {
                                $codFallback = $this->resolveEventoComponenteCodigo($refRegra, '');
                                if ($codFallback !== '' && $codFallback !== $codColunaResolvido) {
                                    $mapRef = $resolveLinhasParaMapRef((array) $linhasRef, (string) $codFallback);
                                    if ($mapRef !== []) {
                                        $codColunaResolvido = $codFallback;
                                        $detalhes['componente_codigo_resolvido'] = $codFallback;
                                        $detalhes['componente_codigo_fallback'] = $codFallback;
                                    }
                                }
                            }
                            if ($mapRef === []) {
                                $detalhes['aviso_evento'] = 'Evento/coluna sem notas para este aluno no intervalo.';
                            } else {
                                $matrizPorCodigo[$codigo] = $mapRef;
                                $listaGlobalRef = [];
                                foreach ($mapRef as $midRef => $vRef) {
                                    $listaGlobalRef[] = [
                                        'valor' => (float) $vRef,
                                        'materia_id' => (int) $midRef,
                                        'materia_nome' => (string) ($materiaNomesPorId[$midRef] ?? ''),
                                    ];
                                }
                                $valor = $this->agruparNotas($listaGlobalRef, (string) ($componente['calc_type'] ?? 'media'));
                            }
                        }
                    }
                }
            } elseif (($componente['source_type'] ?? '') === 'faltas_evento') {
                $cfgFaltas = $this->parseFaltasConfigFromComponente($componente);
                $eventoIdFaltas = (int) ($cfgFaltas['evento_id'] ?? 0);
                $detalhes['faltas_evento_id'] = $eventoIdFaltas;
                if ($eventoIdFaltas <= 0) {
                    $detalhes['erro'] = 'Selecione um evento de faltas.';
                } else {
                    $absence = new SchoolAbsence();
                    $lancamentosFaltas = $this->faltasEventoCache[$eventoIdFaltas]
                        ?? $absence->getLancamentosMapByEvento($eventoIdFaltas);
                    if (!isset($this->faltasEventoCache[$eventoIdFaltas])) {
                        $this->faltasEventoCache[$eventoIdFaltas] = $lancamentosFaltas;
                    }
                    $faltasPorMateria = [];
                    $faltasLegado = null;
                    $materiasFiltroFaltas = $this->parseMateriasIdsFromComponente($componente);
                    $materiasFiltroFaltasSet = $materiasFiltroFaltas !== [] ? array_fill_keys($materiasFiltroFaltas, true) : [];
                    foreach ($lancamentosFaltas as $keyF => $itemF) {
                        $partsF = explode('_', (string) $keyF, 2);
                        $aidF = (int) ($partsF[0] ?? 0);
                        $midF = (int) ($partsF[1] ?? 0);
                        if ($aidF !== $alunoId) {
                            continue;
                        }
                        $faltasValor = (float) ($itemF['faltas'] ?? 0);
                        if ($midF > 0) {
                            if ($materiasFiltroFaltasSet !== [] && !isset($materiasFiltroFaltasSet[$midF])) {
                                continue;
                            }
                            $faltasPorMateria[$midF] = $faltasValor;
                        } elseif ($midF === 0) {
                            $faltasLegado = $faltasValor;
                        }
                    }

                    if ($faltasPorMateria !== []) {
                        $matrizPorCodigo[$codigo] = $faltasPorMateria;
                        $detalhes['faltas_por_materia'] = count($faltasPorMateria);
                        if ($materiasFiltroFaltas !== []) {
                            $detalhes['materias_ids'] = $materiasFiltroFaltas;
                        }
                        $valor = array_sum($faltasPorMateria);

                        $nomesFaltasPorMateria = [];
                        $eventoFaltas = $this->eventosFaltasCache[$eventoIdFaltas]
                            ?? $absence->getEventoById($eventoIdFaltas);
                        if (is_array($eventoFaltas) && !isset($this->eventosFaltasCache[$eventoIdFaltas])) {
                            $this->eventosFaltasCache[$eventoIdFaltas] = $eventoFaltas;
                        }
                        $materiasEventoIds = array_map('intval', (array) ($eventoFaltas['materias_ids'] ?? []));
                        if ($materiasEventoIds !== []) {
                            foreach ($absence->listMateriasByIds($materiasEventoIds) as $matF) {
                                $midNomeF = (int) ($matF['id'] ?? 0);
                                $nomeF = trim((string) ($matF['nome'] ?? ''));
                                if ($midNomeF > 0 && $nomeF !== '') {
                                    $nomesFaltasPorMateria[$midNomeF] = $nomeF;
                                }
                            }
                        }
                        foreach ($this->boletimConfig->getAvailableSubjects(2000) as $matF) {
                            $midNomeF = (int) ($matF['id'] ?? 0);
                            $nomeF = trim((string) ($matF['nome'] ?? ''));
                            if ($midNomeF > 0 && $nomeF !== '' && !isset($nomesFaltasPorMateria[$midNomeF])) {
                                $nomesFaltasPorMateria[$midNomeF] = $nomeF;
                            }
                        }
                        foreach (array_keys($faltasPorMateria) as $midF) {
                            $midF = (int) $midF;
                            if (isset($nomesFaltasPorMateria[$midF])) {
                                $materiaNomesPorId[$midF] = $nomesFaltasPorMateria[$midF];
                            }
                        }
                    } elseif ($faltasLegado !== null) {
                        $valor = $faltasLegado;
                        $detalhes['faltas_legado_sem_materia'] = 1;
                    } else {
                        $totaisFaltas = $absence->getTotalFaltasPorAlunoNoEvento($eventoIdFaltas);
                        if (array_key_exists($alunoId, $totaisFaltas)) {
                            $valor = (float) ($totaisFaltas[$alunoId] ?? 0);
                            $detalhes['faltas_legado_sem_materia'] = 1;
                        }
                    }

                    if ($valor === null) {
                        $detalhes['aviso_faltas'] = 'Sem lançamento de faltas para este aluno no evento selecionado.';
                    }
                }
            } else {
                $usouNotaFinalTipo = $this->preencherComponenteComNotaFinalTipo(
                    $componente,
                    $alunoId,
                    $regra,
                    $periodoRef,
                    $codigo,
                    $roundMode,
                    $valor,
                    $detalhes,
                    $matrizPorCodigo,
                    $materiaNomesPorId
                );
                if ($usouNotaFinalTipo) {
                    // nota_final do tipo — boletim não varre eventos
                } else {
                $materiaFiltro = (int) ($componente['materia_id'] ?? 0);
                $materiasFiltro = $this->parseMateriasIdsFromComponente($componente);
                $materiaFiltroConsulta = $materiasFiltro !== []
                    ? null
                    : ($materiaFiltro > 0 ? $materiaFiltro : null);
                $blocoIds = $this->resolveBlocoIdsFromComponentePersisted($componente);
                $bimestresComp = $this->bimestresDoComponenteOuRegra($componente, $regra);
                $cfgBlocos = $this->decodeComponenteConfig($componente);
                if ($blocoIds !== [] && $bimestresComp !== [] && empty($cfgBlocos['blocos_ids_manual'])) {
                    $blocoIds = $this->boletimConfig->filtrarBlocoIdsPorBimestres($blocoIds, $bimestresComp);
                }
                $resolvidoQuadro = $this->resolverBlocosQuadroDoComponente(
                    $componente,
                    $blocoIds,
                    $range['inicio'] ?? null,
                    $range['fim'] ?? null,
                    $bimestresComp,
                    (int) ($regra['ano_letivo'] ?? 0)
                );
                $blocoIds = $resolvidoQuadro['bloco_ids'];
                $quadroForcado = !empty($resolvidoQuadro['forcada']);
                $filtroTitulo = trim((string) ($componente['filtro_titulo'] ?? ''));
                $filtroTitulo = $filtroTitulo !== '' ? $filtroTitulo : null;
                $tinhaBlocos = $blocoIds !== [];
                $blocoIds = $this->aplicarFiltroTurmasNosBlocos($blocoIds);

                if (!empty($blocoIds)) {
                    $rows = $this->obterProvasAlunoBlocosCached(
                        $alunoId,
                        $blocoIds,
                        null,
                        null,
                        $filtroTitulo,
                        $materiaFiltroConsulta
                    );
                    $rows = $this->restringirProvasAosBlocosDaTurma($rows, $blocoIds, $alunoId);
                    $detalhes['blocos_ids'] = $blocoIds;
                } elseif ($quadroForcado) {
                    $rows = [];
                    $detalhes['aviso_semana'] = 'Nenhum evento de prova correspondente a esta coluna do quadro.';
                } elseif ($tinhaBlocos) {
                    $rows = [];
                } else {
                    $rows = $this->boletimConfig->getProvasFinalizadasByAluno(
                        $alunoId,
                        $range['inicio'],
                        $range['fim'],
                        $filtroTitulo,
                        $materiaFiltroConsulta
                    );
                }
                if ($materiasFiltro !== []) {
                    $rows = $this->expandirNotaUnicaBlocoParaMateriasSelecionadas($rows, $materiasFiltro);
                    $rows = $this->filtrarEReconciliarMateriasSelecionadas($rows, $materiasFiltro);
                    $detalhes['materias_ids'] = $materiasFiltro;
                } else {
                    // ENAC nota única sem filtro: preenche matérias do bloco adicionadas depois do lançamento.
                    $rows = $this->expandirNotaUnicaBlocoParaMateriasSelecionadas($rows, []);
                }
                // Dois professores da mesma matéria somam as questões uma vez.
                // A mesma prova ligada a mais de um bloco da semana não entra de novo.
                $rows = $this->deduplicarProvasDaMateria($rows);

                $statsPorMateria = [];
                $layoutNq = $this->parseLayoutMetaFromComponente($componente);
                $ehSemanaNq = $this->parseSemanaFromComponente($componente) > 0
                    || strtolower((string) ($layoutNq['type'] ?? '')) === 'semana_nq';
                if (!empty($componente['usar_percentual']) || $ehSemanaNq) {
                    foreach ($rows as $row) {
                        $midRow = isset($row['materia_id']) ? (int) ($row['materia_id'] ?? 0) : 0;
                        $totalQuestoes = (int) ($row['total_questoes'] ?? 0);
                        $acertos = (int) ($row['acertos'] ?? 0);
                        if ($midRow <= 0 || $totalQuestoes <= 0) {
                            continue;
                        }
                        if (!isset($statsPorMateria[$midRow])) {
                            $statsPorMateria[$midRow] = ['acertos' => 0, 'total' => 0];
                        }
                        $statsPorMateria[$midRow]['acertos'] += max(0, $acertos);
                        $statsPorMateria[$midRow]['total'] += $totalQuestoes;
                    }
                    if ($statsPorMateria !== []) {
                        $matrizPercentStatsPorCodigo[$codigo] = $statsPorMateria;
                    }
                }

                $notas = [];
                foreach ($rows as $row) {
                    $notaItem = $this->extrairNotaDaProva($row, $componente);
                    if ($notaItem !== null) {
                        $midRow = isset($row['materia_id']) ? (int) $row['materia_id'] : 0;
                        $nomeRow = trim((string) ($row['materia_nome'] ?? ''));
                        $notas[] = [
                            'valor' => $notaItem,
                            'materia_id' => $midRow,
                            'materia_nome' => $nomeRow,
                            'prova_uid' => $this->extrairProvaUid($row),
                        ];
                        if ($midRow > 0 && $nomeRow !== '') {
                            $materiaNomesPorId[$midRow] = $nomeRow;
                        }
                    }
                }

                if (!empty($componente['materia_unica'])) {
                    $notas = $this->deduplicarNotasPorMateria($notas, $this->modoMateriaUnica($componente));
                    $detalhes['qtd_materias_unicas'] = count($notas);
                }

                $detalhes['materias_nomes'] = $this->extrairMateriasNomes($notas);

                $notasParaMatriz = $notas;
                if (!empty($componente['materia_unica'])) {
                    $notasParaMatriz = [];
                    foreach ($rows as $row) {
                        $notaItem = $this->extrairNotaDaProva($row, $componente);
                        if ($notaItem === null) {
                            continue;
                        }
                        $midRow = isset($row['materia_id']) ? (int) $row['materia_id'] : 0;
                        $nomeRow = trim((string) ($row['materia_nome'] ?? ''));
                        $notasParaMatriz[] = [
                            'valor' => $notaItem,
                            'materia_id' => $midRow,
                            'materia_nome' => $nomeRow,
                            'prova_uid' => $this->extrairProvaUid($row),
                        ];
                        if ($midRow > 0 && $nomeRow !== '') {
                            $materiaNomesPorId[$midRow] = $nomeRow;
                        }
                    }
                }

                $mapPorMateria = $this->valoresPorMateriaFromNotasLista($notasParaMatriz, $componente, $statsPorMateria);
                $mapPorMateria = $this->completarMapaNotaUnicaBloco(
                    $mapPorMateria,
                    $rows,
                    $materiasFiltro,
                    $materiaNomesPorId,
                    $regra
                );
                $roundModeComp = $this->resolveRoundModeComponente($componente, $roundMode);
                $matrizPorCodigo[$codigo] = $this->applyRoundModeToMateriaMap($mapPorMateria, $roundModeComp);

                if (!empty($componente['usar_percentual'])
                    && $this->normalizeCalcType((string) ($componente['calc_type'] ?? 'media')) === 'media'
                    && $mapPorMateria !== []) {
                    $valsMid = array_values($mapPorMateria);
                    $valor = $this->applyRoundMode(round(array_sum($valsMid) / count($valsMid), 2), $roundModeComp);
                } else {
                    $valor = $this->applyRoundMode($this->agruparNotas($notas, (string) ($componente['calc_type'] ?? 'media')), $roundModeComp);
                }
                $detalhes['qtd_provas'] = count($rows);
                }
            }

            if ($overridesPorMateria !== []) {
                $roundModeOv = $this->resolveRoundModeComponente($componente, $roundMode);
                if (!is_array($matrizPorCodigo[$codigo] ?? null)) {
                    $matrizPorCodigo[$codigo] = [];
                }
                $materiasComOverrideOv = [];
                foreach ($overridesPorMateria as $midOv => $rowOv) {
                    $midOv = (int) $midOv;
                    if ($midOv === 0) {
                        continue;
                    }
                    $notaOv = $rowOv['nota'] ?? null;
                    if ($notaOv === null) {
                        // Sobrescrita explícita "sem nota": força a célula vazia (traço)
                        // pra essa matéria/aluno, mesmo que exista dado real (prova/
                        // jornada) por trás — diferente de não ter sobrescrita nenhuma.
                        $matrizPorCodigo[$codigo][$midOv] = null;
                        $materiasComOverrideOv[$midOv] = [
                            'manual_id' => (int) ($rowOv['id'] ?? 0),
                            'bloqueado' => (int) ($rowOv['bloqueado'] ?? 0) === 1,
                            'vazio' => true,
                        ];
                        if ((int) ($rowOv['bloqueado'] ?? 0) === 1) {
                            $bloqueado = true;
                        }
                        continue;
                    }
                    if (!is_numeric($notaOv)) {
                        continue;
                    }
                    $matrizPorCodigo[$codigo][$midOv] = $this->applyRoundMode((float) $notaOv, $roundModeOv);
                    $materiasComOverrideOv[$midOv] = [
                        'manual_id' => (int) ($rowOv['id'] ?? 0),
                        'bloqueado' => (int) ($rowOv['bloqueado'] ?? 0) === 1,
                    ];
                    if ((int) ($rowOv['bloqueado'] ?? 0) === 1) {
                        $bloqueado = true;
                    }
                }
                if ($materiasComOverrideOv !== []) {
                    $detalhes['materias_com_override_manual'] = $materiasComOverrideOv;
                    $listaComOverrideOv = [];
                    foreach ($matrizPorCodigo[$codigo] as $midC => $vC) {
                        if (!is_numeric($vC)) {
                            continue;
                        }
                        $listaComOverrideOv[] = ['valor' => (float) $vC, 'materia_id' => (int) $midC, 'materia_nome' => (string) ($materiaNomesPorId[$midC] ?? '')];
                    }
                    $valor = $this->applyRoundMode($this->agruparNotas($listaComOverrideOv, 'media'), $roundModeOv);
                }
            }

            if ($valor !== null) {
                $valoresPorCodigo[$codigo] = $valor;
            } elseif (!empty($componente['obrigatorio'])) {
                $faltantesObrigatorios[] = (string) ($componente['nome'] ?? $codigo);
            }

            $componentesResultado[] = [
                'id' => (int) ($componente['id'] ?? 0),
                'codigo' => $codigo,
                'nome' => (string) ($componente['nome'] ?? $codigo),
                'source_type' => (string) ($componente['source_type'] ?? 'provas_sistema'),
                'peso' => (float) ($componente['peso'] ?? 1),
                'escala_max' => (float) ($componente['escala_max'] ?? 10),
                'bloco_id' => (int) ($componente['bloco_id'] ?? 0),
                'materia_id' => (int) ($componente['materia_id'] ?? 0),
                'materia_unica' => !empty($componente['materia_unica']),
                'valor' => $valor,
                'obrigatorio' => !empty($componente['obrigatorio']),
                'bloqueado' => $bloqueado,
                'detalhes' => $detalhes,
            ];
        }

        $roundModeSim = $this->normalizeRoundMode((string) ($regra['round_mode'] ?? 'none'));
        $allMidsPreCalc = [];
        foreach ($matrizPorCodigo as $mapPre) {
            if (!is_array($mapPre)) {
                continue;
            }
            foreach (array_keys($mapPre) as $midPre) {
                $allMidsPreCalc[(int) $midPre] = true;
            }
        }
        foreach ($this->expandirMateriasComFilhos($this->parseMateriasIdsFromRegra($regra)) as $midSelPre) {
            $midSelPre = (int) $midSelPre;
            if ($midSelPre > 0) {
                $allMidsPreCalc[$midSelPre] = true;
            }
        }
        $this->aplicarEspalhamentoJornadasNotaUnicaNaMatriz($componentes, $matrizPorCodigo, $componentesResultado, $allMidsPreCalc, $roundModeSim);

        $manterManualPorCodigo = [];
        foreach ($this->listarCalculadosNaOrdemDaFormula($componentes) as $componente) {
            $codigo = (string) ($componente['codigo'] ?? '');
            if ($codigo === '' || ($componente['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $this->fundirMateriasIguaisNaMatriz($matrizPorCodigo, $materiaNomesPorId, $componentes);

            $valor = null;
            $detalhes = [];
            $bloqueado = false;

            // Sobrescrita manual por matéria, por aluno: cobre o caso do aluno que
            // ingressou no meio do período e não tem dados de origem (provas/jornadas)
            // numa ou mais matérias. Permite informar direto a nota final calculada
            // dessa(s) matéria(s) sem exigir lançamento bloco a bloco. A fórmula
            // continua rodando normalmente; o valor manual só sobrescreve a(s)
            // matéria(s) com lançamento salvo. Não afeta os demais alunos.
            $compIdCalcManual = (int) ($componente['id'] ?? 0);
            $overridesPorMateria = $compIdCalcManual > 0
                ? $this->boletimConfig->getManualNotesByComponente($compIdCalcManual, $alunoId, $periodoRef)
                : [];

            $expr = $this->parseExpressaoColunaCalculada($componente);
            $formulaPorMateria = $this->parseFormulaMateriasCalculadoFromComponente($componente);
            $agregarNq = $this->parseAgregarNqFromComponente($componente);
            if ($agregarNq !== []) {
                $escalaNq = max(0.01, (float) ($componente['escala_max'] ?? 10));
                $mapNq = $this->matrizColunaAgregarNq($agregarNq, $matrizPercentStatsPorCodigo, $escalaNq);
                $roundModeComp = $this->resolveRoundModeComponente($componente, $roundMode);
                if ($mapNq !== []) {
                    $matrizPorCodigo[$codigo] = $this->applyRoundModeToMateriaMap($mapNq, $roundModeComp);
                    $listaGlobalCalc = [];
                    foreach ($matrizPorCodigo[$codigo] as $midC => $vC) {
                        if (!is_numeric($vC)) {
                            continue;
                        }
                        $listaGlobalCalc[] = [
                            'valor' => (float) $vC,
                            'materia_id' => (int) $midC,
                            'materia_nome' => (string) ($materiaNomesPorId[$midC] ?? ''),
                        ];
                    }
                    $valor = $this->agruparNotas($listaGlobalCalc, 'media');
                    $valor = $this->applyRoundMode($valor, $roundModeComp);
                } else {
                    $detalhes['aviso_calculado'] = 'Sem acertos/questões nas semanas referenciadas em agregar_nq.';
                }
                $detalhes['agregar_nq'] = $agregarNq;
            } elseif ($expr === '' && $formulaPorMateria === []) {
                $detalhes['erro'] = 'Informe a expressão (use os códigos dos outros blocos, ex.: (semanal + bimestral) / 2).';
            } else {
                $detalhes['expressao'] = $expr;
                if ($formulaPorMateria !== []) {
                    $detalhes['formula_materias'] = $formulaPorMateria;
                }
                $mapCalc = $this->matrizColunaCalculada($expr, $formulaPorMateria, $codigo, $matrizPorCodigo);
                $roundModeComp = $this->resolveRoundModeComponente($componente, $roundMode);
                if ($mapCalc !== []) {
                    $matrizPorCodigo[$codigo] = $this->applyRoundModeToMateriaMap($mapCalc, $roundModeComp);
                    $listaGlobalCalc = [];
                    foreach ($matrizPorCodigo[$codigo] as $midC => $vC) {
                        if (!is_numeric($vC)) {
                            continue;
                        }
                        $listaGlobalCalc[] = [
                            'valor' => (float) $vC,
                            'materia_id' => (int) $midC,
                            'materia_nome' => (string) ($materiaNomesPorId[$midC] ?? ''),
                        ];
                    }
                    $valor = $this->agruparNotas($listaGlobalCalc, 'media');
                    $valor = $this->applyRoundMode($valor, $roundModeComp);
                } else {
                    // Fallback: expressão com blocos "globais" (ex.: faltas_evento/manual)
                    // sem mapa por matéria deve replicar o resultado para todas as matérias atuais.
                    $rGlobalCalc = ($expr !== '') ? $this->avaliarFormula($expr, $valoresPorCodigo) : ['ok' => false];
                    if (!empty($rGlobalCalc['ok']) && isset($rGlobalCalc['valor']) && is_numeric($rGlobalCalc['valor'])) {
                        $vGlobalCalc = (float) $rGlobalCalc['valor'];
                        $midsAtuais = [];
                        foreach ($matrizPorCodigo as $mapTmp) {
                            if (!is_array($mapTmp)) {
                                continue;
                            }
                            foreach (array_keys($mapTmp) as $midTmp) {
                                $midsAtuais[(int) $midTmp] = true;
                            }
                        }
                        if ($midsAtuais === []) {
                            $midsAtuais[0] = true;
                        }
                        $mapGlobalCalc = [];
                        foreach (array_keys($midsAtuais) as $midTmp) {
                            $mapGlobalCalc[(int) $midTmp] = $vGlobalCalc;
                        }
                        $matrizPorCodigo[$codigo] = $this->applyRoundModeToMateriaMap($mapGlobalCalc, $roundModeComp);
                        $valor = $this->applyRoundMode($vGlobalCalc, $roundModeComp);
                    } else {
                    $detalhes['aviso_calculado'] = 'Nenhuma matéria com dados dos blocos referenciados na expressão (verifique os códigos e a ordem dos blocos).';
                    }
                }
            }

            if ($overridesPorMateria !== []) {
                $roundModeComp = $roundModeComp ?? $this->resolveRoundModeComponente($componente, $roundMode);
                if (!is_array($matrizPorCodigo[$codigo] ?? null)) {
                    $matrizPorCodigo[$codigo] = [];
                }
                $materiasComOverride = [];
                foreach ($overridesPorMateria as $midOv => $rowOv) {
                    $midOv = (int) $midOv;
                    if ($midOv === 0) {
                        continue;
                    }
                    $notaOv = $rowOv['nota'] ?? null;
                    if ($notaOv === null) {
                        // Sobrescrita explícita "sem nota": força a célula vazia (traço)
                        // pra essa matéria/aluno, mesmo que a fórmula calculasse um valor.
                        $matrizPorCodigo[$codigo][$midOv] = null;
                        $materiasComOverride[$midOv] = [
                            'manual_id' => (int) ($rowOv['id'] ?? 0),
                            'bloqueado' => (int) ($rowOv['bloqueado'] ?? 0) === 1,
                            'vazio' => true,
                        ];
                        if ((int) ($rowOv['bloqueado'] ?? 0) === 1) {
                            $bloqueado = true;
                        }
                        continue;
                    }
                    if (!is_numeric($notaOv)) {
                        continue;
                    }
                    $matrizPorCodigo[$codigo][$midOv] = $this->applyRoundMode((float) $notaOv, $roundModeComp);
                    $materiasComOverride[$midOv] = [
                        'manual_id' => (int) ($rowOv['id'] ?? 0),
                        'bloqueado' => (int) ($rowOv['bloqueado'] ?? 0) === 1,
                    ];
                    if ((int) ($rowOv['bloqueado'] ?? 0) === 1) {
                        $bloqueado = true;
                    }
                }
                foreach ($materiasComOverride as $midManter => $_infoManter) {
                    $midManter = (int) $midManter;
                    $manterManualPorCodigo[$codigo][$midManter] = $matrizPorCodigo[$codigo][$midManter] ?? null;
                }
                if ($materiasComOverride !== []) {
                    $detalhes['materias_com_override_manual'] = $materiasComOverride;
                    $listaComOverride = [];
                    foreach ($matrizPorCodigo[$codigo] as $midC => $vC) {
                        if (!is_numeric($vC)) {
                            continue;
                        }
                        $listaComOverride[] = ['valor' => (float) $vC, 'materia_id' => (int) $midC, 'materia_nome' => (string) ($materiaNomesPorId[$midC] ?? '')];
                    }
                    $valor = $this->applyRoundMode($this->agruparNotas($listaComOverride, 'media'), $roundModeComp);
                }
            }

            if ($valor !== null) {
                $valoresPorCodigo[$codigo] = $valor;
            } elseif (!empty($componente['obrigatorio'])) {
                $faltantesObrigatorios[] = (string) ($componente['nome'] ?? $codigo);
            }

            $componentesResultado[] = [
                'id' => (int) ($componente['id'] ?? 0),
                'codigo' => $codigo,
                'nome' => (string) ($componente['nome'] ?? $codigo),
                'source_type' => 'calculado',
                'peso' => (float) ($componente['peso'] ?? 1),
                'escala_max' => (float) ($componente['escala_max'] ?? 10),
                'bloco_id' => 0,
                'materia_id' => 0,
                'materia_unica' => false,
                'valor' => $valor,
                'obrigatorio' => !empty($componente['obrigatorio']),
                'bloqueado' => $bloqueado,
                'detalhes' => $detalhes,
            ];
        }
        $this->repassarFormulasNaMatriz($componentes, $matrizPorCodigo, $componentesResultado, $roundMode, $manterManualPorCodigo);
        $this->fundirMateriasIguaisNaMatriz($matrizPorCodigo, $materiaNomesPorId, $componentes);

        $porCodigoRes = [];
        foreach ($componentesResultado as $cr) {
            $ck = trim((string) ($cr['codigo'] ?? ''));
            if ($ck !== '') {
                $porCodigoRes[$ck] = $cr;
            }
        }
        $componentesResultadoOrdenado = [];
        foreach ($componentes as $compOrd) {
            $ck = trim((string) ($compOrd['codigo'] ?? ''));
            if ($ck !== '' && isset($porCodigoRes[$ck])) {
                $componentesResultadoOrdenado[] = $porCodigoRes[$ck];
            }
        }
        $componentesResultado = $componentesResultadoOrdenado;

        $final = $this->calcularNotaFinal($regra, $componentesResultado, $valoresPorCodigo, $faltantesObrigatorios);

        $aluno = $this->buscarAluno($alunoId);
        $this->espalharNotasJornadaPeloNome($matrizPorCodigo, $notasJornadaPorNome, $materiaNomesPorId);
        $matrizMaterias = $this->montarMatrizMateriasSimulacao(
            $regra,
            $componentes,
            $componentesResultado,
            $matrizPorCodigo,
            $materiaNomesPorId,
            $matrizPercentStatsPorCodigo,
            $materiasAgrupadasHerdadas,
            [
                'aluno_id' => $alunoId,
                'turma_id' => (int) ($aluno['turma_id'] ?? 0),
                'serie_id' => (int) ($aluno['serie_id'] ?? 0),
                'curso_id' => $this->cursoIdDaTurma((int) ($aluno['turma_id'] ?? 0)),
                'ano_letivo' => isset($regra['ano_letivo']) ? (int) $regra['ano_letivo'] : null,
                'periodo_numero' => isset($regra['bimestre']) ? (int) $regra['bimestre'] : null,
                'periodo_tipo' => 'bimestre',
                'data_inicio' => substr((string) ($range['inicio'] ?? ''), 0, 10),
                'data_fim' => substr((string) ($range['fim'] ?? ''), 0, 10),
            ],
            $forcarAgrupamentoLinhas,
            $pularAgrupamentoLinhas
        );

        $resultado = [
            'aluno' => $aluno,
            'periodo_ref' => $periodoRef,
            'data_inicio' => substr((string) ($range['inicio'] ?? ''), 0, 10),
            'data_fim' => substr((string) ($range['fim'] ?? ''), 0, 10),
            'componentes' => $componentesResultado,
            'faltantes_obrigatorios' => $faltantesObrigatorios,
            'nota_final' => $final['nota_final'],
            'metodo_final' => $final['metodo'],
            'expressao_final' => $final['expressao'],
            'erro_formula' => $final['erro_formula'],
            'matriz_materias' => $matrizMaterias,
        ];
        if ($regraIdCache > 0) {
            $this->simulacaoAlunoCache[$chaveSimulacao] = $resultado;
        }

        return $resultado;
    }

    /**
     * Nota do componente por matéria (cada célula = provas só daquela matéria).
     * Com matérias únicas: deduplica por matéria antes de média/soma/etc. dentro da matéria.
     *
     * Com "usar_percentual" + regra "média": usa (soma acertos / soma questões) * escala por matéria,
     * e não a média aritmética das notas (acertos/total)*10 de cada prova — evita distorcer o total
     * quando as provas têm quantidades de questões diferentes.
     *
     * @param list<array{valor: float, materia_id: int, materia_nome: string}> $notas
     * @param array<int, array{acertos:int,total:int}> $statsPorMateria totais agregados por matéria (opcional)
     * @return array<int, float>
     */
    private function valoresPorMateriaFromNotasLista(array $notas, array $componente, array $statsPorMateria = []): array
    {
        $calc = $this->normalizeCalcType((string) ($componente['calc_type'] ?? 'media'));
        $usarPct = !empty($componente['usar_percentual']);
        $escalaMax = $usarPct
            ? 10.0
            : max(0.01, (float) ($componente['escala_max'] ?? 10));

        if ($usarPct && $calc === 'media' && $statsPorMateria !== []) {
            $out = [];
            foreach ($statsPorMateria as $midRaw => $st) {
                $mid = (int) $midRaw;
                $tot = (int) ($st['total'] ?? 0);
                if ($mid <= 0 || $tot <= 0) {
                    continue;
                }
                $acr = (int) ($st['acertos'] ?? 0);
                $out[$mid] = round(max(0.0, min($escalaMax, ($acr / $tot) * $escalaMax)), 2);
            }
            if ($notas !== []) {
                $byMid = [];
                foreach ($notas as $n) {
                    $mid = (int) ($n['materia_id'] ?? 0);
                    if ($mid <= 0) {
                        continue;
                    }
                    if (!isset($out[$mid])) {
                        if (!isset($byMid[$mid])) {
                            $byMid[$mid] = [];
                        }
                        $byMid[$mid][] = $n;
                    }
                }
                foreach ($byMid as $mid => $lista) {
                    $listaProc = $lista;
                    if (!empty($componente['materia_unica'])) {
                        $listaProc = $this->deduplicarNotasPorMateria($listaProc, $this->modoMateriaUnica($componente));
                    }
                    $v = $this->agruparNotas($listaProc, $this->calcAoJuntarMateriasIguais($componente, $calc));
                    if ($v !== null) {
                        $out[$mid] = $v;
                    }
                }
            }

            return $out;
        }

        if ($notas === []) {
            return [];
        }
        $byMid = [];
        foreach ($notas as $n) {
            $mid = (int) ($n['materia_id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            if (!isset($byMid[$mid])) {
                $byMid[$mid] = [];
            }
            $byMid[$mid][] = $n;
        }
        $out = [];
        foreach ($byMid as $mid => $lista) {
            $listaProc = $lista;
            if (!empty($componente['materia_unica'])) {
                $listaProc = $this->deduplicarNotasPorMateria($listaProc, $this->modoMateriaUnica($componente));
            }
            $v = $this->agruparNotas($listaProc, $this->calcAoJuntarMateriasIguais($componente, $calc));
            if ($v !== null) {
                $out[$mid] = $v;
            }
        }

        return $out;
    }

    /**
     * Mantém as matérias selecionadas no componente e reconcilia cadastros duplicados pelo nome.
     *
     * Algumas escolas possuem mais de um registro em `materias` com o mesmo nome (por exemplo,
     * "Redação"). A pauta pode guardar a nota em um desses IDs enquanto o boletim foi configurado
     * com o outro. Nessa situação, o filtro antigo descartava uma nota válida e exibia "—".
     *
     * IDs exatos continuam tendo prioridade. A equivalência por nome só é aplicada quando existe
     * um único ID selecionado com aquele nome normalizado, evitando misturar matérias ambíguas.
     *
     * @param list<array<string,mixed>> $rows
     * @param list<int> $materiasSelecionadas
     * @return list<array<string,mixed>>
     */
    private function filtrarEReconciliarMateriasSelecionadas(array $rows, array $materiasSelecionadas): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $materiasSelecionadas), static function (int $id): bool {
            return $id > 0;
        })));
        $ids = $this->expandirMateriasComFilhos($ids);
        if ($ids === []) {
            return $rows;
        }

        $setIds = array_fill_keys($ids, true);
        $idsSelecionadosPorNome = [];
        if (!is_array($this->materiasDisponiveisCache)) {
            $this->materiasDisponiveisCache = $this->boletimConfig->getAvailableSubjects(1000);
        }
        foreach ($this->materiasDisponiveisCache as $materia) {
            $mid = (int) ($materia['id'] ?? 0);
            if ($mid <= 0 || !isset($setIds[$mid])) {
                continue;
            }
            $nomeKey = $this->canonicalMateriaNomeKey((string) ($materia['nome'] ?? ''));
            if ($nomeKey !== '') {
                $idsSelecionadosPorNome[$nomeKey][$mid] = $mid;
            }
        }

        $resultado = [];
        foreach ($rows as $row) {
            $mid = (int) ($row['materia_id'] ?? 0);
            if ($mid > 0 && isset($setIds[$mid])) {
                $resultado[] = $row;
                continue;
            }

            $rowAlternativa = $this->reconciliarMateriaAlternativaSelecionada($row, $setIds, $idsSelecionadosPorNome);
            if ($rowAlternativa !== null) {
                $resultado[] = $rowAlternativa;
                continue;
            }

            $nomeKey = $this->canonicalMateriaNomeKey((string) ($row['materia_nome'] ?? ''));
            $candidatos = $nomeKey !== '' ? array_values($idsSelecionadosPorNome[$nomeKey] ?? []) : [];
            if (count($candidatos) !== 1) {
                continue;
            }

            // Canoniza a linha para o ID escolhido no componente antes de montar a matriz.
            $row['materia_id'] = (int) $candidatos[0];
            $resultado[] = $row;
        }

        return $resultado;
    }

    /**
     * Blocos de pauta podem trazer mais de uma referência de matéria para a mesma nota:
     * a matéria gravada na nota e a matéria configurada na coluna/professor do bloco.
     * Usa qualquer uma delas que esteja no filtro do componente antes de descartar a linha.
     *
     * @param array<string,mixed> $row
     * @param array<int,bool> $setIds
     * @param array<string,array<int,int>> $idsSelecionadosPorNome
     * @return array<string,mixed>|null
     */
    private function reconciliarMateriaAlternativaSelecionada(array $row, array $setIds, array $idsSelecionadosPorNome): ?array
    {
        $notaMid = (int) ($row['nota_materia_id'] ?? $row['materia_id'] ?? 0);
        $notaNome = trim((string) ($row['nota_materia_nome'] ?? $row['materia_nome'] ?? ''));

        // Matéria da nota fora do filtro: descarta (não “empurra” Leitura/Literatura para Português).
        if ($notaMid > 0 && !isset($setIds[$notaMid])) {
            $nomeKeyNota = $this->canonicalMateriaNomeKey($notaNome);
            $candidatosNota = $nomeKeyNota !== '' ? array_values($idsSelecionadosPorNome[$nomeKeyNota] ?? []) : [];
            if (count($candidatosNota) === 1) {
                $row['materia_id'] = (int) $candidatosNota[0];
                if ($notaNome !== '') {
                    $row['materia_nome'] = $notaNome;
                }

                return $row;
            }

            return null;
        }

        if ($notaMid > 0 && isset($setIds[$notaMid])) {
            $row['materia_id'] = $notaMid;
            if ($notaNome !== '') {
                $row['materia_nome'] = $notaNome;
            }

            return $row;
        }

        // Legado sem materia_id na nota: tenta o vínculo do professor.
        $alternativas = [
            [
                'id' => (int) ($row['professor_materia_id'] ?? 0),
                'nome' => trim((string) ($row['professor_materia_nome'] ?? '')),
            ],
        ];

        foreach ($alternativas as $alt) {
            $midAlt = (int) ($alt['id'] ?? 0);
            if ($midAlt <= 0 || !isset($setIds[$midAlt])) {
                continue;
            }
            $row['materia_id'] = $midAlt;
            if ((string) ($alt['nome'] ?? '') !== '') {
                $row['materia_nome'] = (string) $alt['nome'];
            }

            return $row;
        }

        foreach ($alternativas as $alt) {
            $nomeKey = $this->canonicalMateriaNomeKey((string) ($alt['nome'] ?? ''));
            $candidatos = $nomeKey !== '' ? array_values($idsSelecionadosPorNome[$nomeKey] ?? []) : [];
            if (count($candidatos) !== 1) {
                continue;
            }
            $row['materia_id'] = (int) $candidatos[0];
            if ((string) ($alt['nome'] ?? '') !== '') {
                $row['materia_nome'] = (string) $alt['nome'];
            }

            return $row;
        }

        return null;
    }

    /**
     * A jornada guarda outro id de matéria. A nota entra na linha do quadro com o mesmo nome.
     *
     * @param array<string, array<int, float|null>> $matrizPorCodigo
     * @param array<string, list<array{nome:string,valor:float}>> $notasJornadaPorNome
     * @param array<int, string> $materiaNomesPorId
     */
    private function espalharNotasJornadaPeloNome(
        array &$matrizPorCodigo,
        array $notasJornadaPorNome,
        array $materiaNomesPorId
    ): void {
        if ($materiaNomesPorId === [] || $notasJornadaPorNome === []) {
            return;
        }
        foreach ($notasJornadaPorNome as $codigo => $itens) {
            if ($codigo === '' || $itens === []) {
                continue;
            }
            $porChave = [];
            foreach ($itens as $item) {
                $chave = $this->canonicalMateriaNomeKey((string) ($item['nome'] ?? ''));
                if ($chave === '' || !is_numeric($item['valor'] ?? null)) {
                    continue;
                }
                $porChave[$chave][] = (float) $item['valor'];
            }
            if (!isset($matrizPorCodigo[$codigo]) || !is_array($matrizPorCodigo[$codigo])) {
                $matrizPorCodigo[$codigo] = [];
            }
            foreach ($materiaNomesPorId as $mid => $nome) {
                $mid = (int) $mid;
                if ($mid === 0) {
                    continue;
                }
                if (isset($matrizPorCodigo[$codigo][$mid]) && is_numeric($matrizPorCodigo[$codigo][$mid])) {
                    continue;
                }
                $chave = $this->canonicalMateriaNomeKey((string) $nome);
                if ($chave === '' || empty($porChave[$chave])) {
                    continue;
                }
                $valores = $porChave[$chave];
                $matrizPorCodigo[$codigo][$mid] = round(array_sum($valores) / count($valores), 2);
            }
        }
    }

    private function chaveOrdemAlfabeticaMateria(string $nome): string
    {
        $v = mb_strtolower(trim($nome), 'UTF-8');
        if ($v === '') {
            return '';
        }
        if (class_exists(\Normalizer::class)) {
            $norm = \Normalizer::normalize($v, \Normalizer::FORM_D);
            if (is_string($norm) && $norm !== '') {
                $v = preg_replace('/\p{Mn}+/u', '', $norm) ?? $norm;
            }
        } else {
            $v = strtr($v, [
                'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
                'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
                'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
                'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
                'ç' => 'c', 'ñ' => 'n',
            ]);
        }
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

        return trim($v);
    }

    private function canonicalMateriaNomeKey(string $nome): string
    {
        $key = $this->normalizeEventoCodigoToken($nome);
        if ($key === '') {
            return '';
        }

        // Alguns lançamentos antigos usam nomes equivalentes para a mesma área.
        // Sem essa canonização, a nota existe no bloco, mas cai fora do filtro do boletim.
        if (strpos($key, 'redacao') !== false
            || (strpos($key, 'producao') !== false && strpos($key, 'textual') !== false)
            || (strpos($key, 'texto') !== false && strpos($key, 'dissertativo') !== false)) {
            return 'redacao';
        }

        return $key;
    }

    /**
     * Prova única (última nota) de dois professores da mesma matéria:
     * com "juntar matérias iguais" usa soma ou média conforme materia_unica_modo.
     * A média semanal continua média (agrega semanas); a deduplicação já aplica o modo.
     */
    private function calcAoJuntarMateriasIguais(array $componente, string $calc): string
    {
        if (empty($componente['materia_unica']) || $calc !== 'ultima') {
            return $calc;
        }

        return $this->modoMateriaUnica($componente);
    }

    /**
     * @param mixed $modo
     */
    private function normalizeMateriaUnicaModo($modo): string
    {
        return strtolower(trim((string) $modo)) === 'media' ? 'media' : 'soma';
    }

    /**
     * Retorna 'soma'|'media' se materia_unica ativo; string vazia caso contrário.
     */
    private function modoMateriaUnica(array $componente): string
    {
        if (empty($componente['materia_unica'])) {
            return '';
        }

        return $this->normalizeMateriaUnicaModo($componente['materia_unica_modo'] ?? 'soma');
    }

    /**
     * Modo de juntar matérias iguais a partir da lista de componentes (primeiro ativo).
     *
     * @param array<int|string, mixed> $componentes
     */
    private function modoMateriaUnicaDosComponentes(array $componentes): string
    {
        foreach ($componentes as $componente) {
            if (!is_array($componente)) {
                continue;
            }
            $modo = $this->modoMateriaUnica($componente);
            if ($modo !== '') {
                return $modo;
            }
        }

        return 'soma';
    }

    /**
     * Mesma matéria em dois cadastros (professores diferentes): soma ou média as células.
     * Independente do modo da "linha única" (média/soma entre matérias distintas).
     *
     * @param array<string, array<int, float|null>> $matrizPorCodigo
     * @param array<int, string> $materiaNomesPorId
     * @param array<int|string, mixed> $componentes
     */
    private function fundirMateriasIguaisNaMatriz(array &$matrizPorCodigo, array $materiaNomesPorId, array $componentes): void
    {
        $temUnica = false;
        foreach ($componentes as $componente) {
            if (!is_array($componente)) {
                continue;
            }
            if (!empty($componente['materia_unica'])) {
                $temUnica = true;
                break;
            }
        }
        if (!$temUnica) {
            return;
        }

        $modo = $this->modoMateriaUnicaDosComponentes($componentes);

        $idsPorNome = [];
        foreach ($materiaNomesPorId as $mid => $nome) {
            $mid = (int) $mid;
            if ($mid <= 0) {
                continue;
            }
            $chave = $this->canonicalMateriaNomeKey((string) $nome);
            if ($chave === '') {
                continue;
            }
            $idsPorNome[$chave][$mid] = $mid;
        }

        foreach ($idsPorNome as $ids) {
            if (count($ids) < 2) {
                continue;
            }
            $ids = array_values($ids);
            sort($ids);
            $primario = (int) $ids[0];
            foreach ($matrizPorCodigo as $cod => $map) {
                if (!is_array($map)) {
                    continue;
                }
                $vals = [];
                foreach ($ids as $mid) {
                    if (isset($map[$mid]) && is_numeric($map[$mid])) {
                        $vals[] = (float) $map[$mid];
                    }
                }
                if ($vals === []) {
                    continue;
                }
                $matrizPorCodigo[$cod][$primario] = $modo === 'media'
                    ? round(array_sum($vals) / count($vals), 2)
                    : array_sum($vals);
                foreach ($ids as $mid) {
                    if ((int) $mid !== $primario) {
                        unset($matrizPorCodigo[$cod][$mid]);
                    }
                }
            }
        }
    }

    /**
     * Blocos de lançamento com "nota única para todas as matérias" podem ter a nota
     * gravada em apenas uma das matérias do evento. Para o boletim, replica essa nota
     * só para as demais matérias DO MESMO BLOCO (não para o filtro inteiro do componente).
     *
     * @param list<array<string,mixed>> $rows
     * @param list<int> $materiasSelecionadas
     * @return list<array<string,mixed>>
     */
    private function expandirNotaUnicaBlocoParaMateriasSelecionadas(array $rows, array $materiasSelecionadas): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $materiasSelecionadas), static function (int $id): bool {
            return $id > 0;
        })));
        if ($ids !== []) {
            $ids = $this->expandirMateriasComFilhos($ids);
        }
        if ($rows === []) {
            return $rows;
        }
        $setIds = $ids !== [] ? array_fill_keys($ids, true) : null;

        if (!is_array($this->materiasDisponiveisCache)) {
            $this->materiasDisponiveisCache = $this->boletimConfig->getAvailableSubjects(1000);
        }
        $nomesById = [];
        foreach ($this->materiasDisponiveisCache as $materia) {
            $mid = (int) ($materia['id'] ?? 0);
            if ($mid > 0) {
                $nomesById[$mid] = trim((string) ($materia['nome'] ?? ('Matéria #' . $mid)));
            }
        }

        $temLinhaPorBlocoMateria = [];
        $basePorBloco = [];
        $materiasDoBloco = [];
        foreach ($rows as $row) {
            $blocoId = (int) ($row['bloco_id'] ?? 0);
            $mid = (int) ($row['materia_id'] ?? 0);
            if ($blocoId > 0 && $mid > 0) {
                $temLinhaPorBlocoMateria[$blocoId][$mid] = true;
                $materiasDoBloco[$blocoId][$mid] = true;
            }
            $profMid = (int) ($row['professor_materia_id'] ?? 0);
            if ($blocoId > 0 && $profMid > 0) {
                $materiasDoBloco[$blocoId][$profMid] = true;
            }
            if ($blocoId <= 0 || !$this->flagNotaUnicaVerdadeira($row['nota_unica_todas_materias'] ?? 0)) {
                continue;
            }
            if (!isset($row['nota']) || $row['nota'] === '' || $row['nota'] === null) {
                continue;
            }
            if (!isset($basePorBloco[$blocoId])) {
                $basePorBloco[$blocoId] = $row;
            }
        }
        if ($basePorBloco === []) {
            return $rows;
        }

        $materiasVinculoBloco = $this->materiasIdsDosBlocosNotaUnica(array_keys($basePorBloco));
        foreach ($materiasVinculoBloco as $blocoId => $midsBloco) {
            foreach ($midsBloco as $midBloco) {
                $materiasDoBloco[(int) $blocoId][(int) $midBloco] = true;
            }
        }

        foreach ($basePorBloco as $blocoId => $base) {
            // Só preenche matérias vinculadas ao evento (provas_blocos_professores),
            // inclusive as adicionadas depois do primeiro lançamento.
            $candidatos = array_keys($materiasDoBloco[$blocoId] ?? []);
            if ($candidatos === [] && $setIds !== null) {
                $candidatos = $ids;
            }
            foreach ($candidatos as $midSel) {
                $midSel = (int) $midSel;
                if ($midSel <= 0) {
                    continue;
                }
                if ($setIds !== null && !isset($setIds[$midSel])) {
                    continue;
                }
                if (isset($temLinhaPorBlocoMateria[$blocoId][$midSel])) {
                    continue;
                }
                $clone = $base;
                $clone['materia_id'] = $midSel;
                $clone['materia_nome'] = (string) ($nomesById[$midSel] ?? ('Matéria #' . $midSel));
                $clone['prova_id'] = ((int) ($base['prova_id'] ?? 0) > 0)
                    ? ((int) $base['prova_id'] + $midSel)
                    : 0;
                $rows[] = $clone;
                $temLinhaPorBlocoMateria[$blocoId][$midSel] = true;
            }
        }

        return $rows;
    }

    /**
     * Garante ENAC/nota única nas matérias do bloco que ainda não têm valor na matriz
     * (ex.: Leitura adicionada depois do lançamento).
     *
     * @param array<int, float|int|string|null> $map
     * @param list<array<string,mixed>> $rows
     * @param list<int> $materiasFiltro
     * @param array<int, string> $materiaNomesPorId
     * @param array<string,mixed> $regra
     * @return array<int, float|int|string|null>
     */
    private function completarMapaNotaUnicaBloco(
        array $map,
        array $rows,
        array $materiasFiltro,
        array &$materiaNomesPorId,
        array $regra = []
    ): array {
        $blocoIdsNasRows = [];
        $notaPorBloco = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $blocoId = (int) ($row['bloco_id'] ?? 0);
            if ($blocoId <= 0) {
                continue;
            }
            $blocoIdsNasRows[$blocoId] = true;
            if (!isset($row['nota']) || $row['nota'] === '' || $row['nota'] === null || !is_numeric($row['nota'])) {
                continue;
            }
            if (!isset($notaPorBloco[$blocoId])) {
                $notaPorBloco[$blocoId] = (float) $row['nota'];
            }
        }
        if ($blocoIdsNasRows === [] || $notaPorBloco === []) {
            return $map;
        }

        $blocosNotaUnica = $this->filtrarBlocoIdsComNotaUnica(array_keys($blocoIdsNasRows));
        // Fallback: confia no flag trazido na row se a coluna/consulta falhar.
        if ($blocosNotaUnica === []) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (!$this->flagNotaUnicaVerdadeira($row['nota_unica_todas_materias'] ?? 0)) {
                    continue;
                }
                $blocoId = (int) ($row['bloco_id'] ?? 0);
                if ($blocoId > 0) {
                    $blocosNotaUnica[$blocoId] = true;
                }
            }
        }
        if ($blocosNotaUnica === []) {
            return $map;
        }

        $basePorBloco = [];
        foreach (array_keys($blocosNotaUnica) as $blocoId) {
            $blocoId = (int) $blocoId;
            if (isset($notaPorBloco[$blocoId])) {
                $basePorBloco[$blocoId] = $notaPorBloco[$blocoId];
            }
        }
        if ($basePorBloco === []) {
            return $map;
        }

        $idsFiltro = array_values(array_unique(array_filter(array_map('intval', $materiasFiltro), static fn ($id) => $id > 0)));
        if ($idsFiltro !== []) {
            $idsFiltro = $this->expandirMateriasComFilhos($idsFiltro);
        }
        $setFiltro = $idsFiltro !== [] ? array_fill_keys($idsFiltro, true) : null;

        if (!is_array($this->materiasDisponiveisCache)) {
            $this->materiasDisponiveisCache = $this->boletimConfig->getAvailableSubjects(1000);
        }
        $nomesById = [];
        foreach ($this->materiasDisponiveisCache as $materia) {
            $mid = (int) ($materia['id'] ?? 0);
            if ($mid > 0) {
                $nomesById[$mid] = trim((string) ($materia['nome'] ?? ''));
            }
        }

        $candidatosPorBloco = $this->materiasIdsDosBlocosNotaUnica(array_keys($basePorBloco));

        // Inclui irmãs do group_line (Gramática tem ENAC → completa Leitura).
        $midsComNota = [];
        foreach ($map as $midMap => $valMap) {
            if (is_numeric($valMap) && (int) $midMap > 0) {
                $midsComNota[(int) $midMap] = true;
            }
        }
        $irmasDoGrupo = [];
        $materiasOriginaisPorBloco = $candidatosPorBloco;
        foreach ((array) ($regra['componentes'] ?? []) as $compGl) {
            if (!is_array($compGl)) {
                continue;
            }
            $grp = $this->parseGroupLineConfigFromComponente($compGl);
            if ($grp === null) {
                continue;
            }
            $idsGrupo = [];
            foreach ((array) ($grp['materias_ids'] ?? []) as $midG) {
                $midG = (int) $midG;
                if ($midG > 0) {
                    $idsGrupo[$midG] = true;
                }
            }
            if (count($idsGrupo) < 2) {
                continue;
            }
            foreach (array_keys($basePorBloco) as $blocoId) {
                $blocoId = (int) $blocoId;
                $noBloco = [];
                foreach ((array) ($materiasOriginaisPorBloco[$blocoId] ?? []) as $midBloco) {
                    $midBloco = (int) $midBloco;
                    if ($midBloco > 0) {
                        $noBloco[$midBloco] = true;
                    }
                }
                $cruzaBloco = false;
                $cruzaNota = false;
                foreach (array_keys($idsGrupo) as $midG) {
                    if (isset($noBloco[$midG])) {
                        $cruzaBloco = true;
                    }
                    if (isset($midsComNota[$midG]) && isset($noBloco[$midG])) {
                        $cruzaNota = true;
                    }
                }
                if (!$cruzaBloco || !$cruzaNota) {
                    continue;
                }
                foreach (array_keys($idsGrupo) as $midG) {
                    $irmasDoGrupo[$midG] = true;
                    $candidatosPorBloco[$blocoId][] = $midG;
                }
            }
        }

        foreach ($basePorBloco as $blocoId => $notaBase) {
            $vistos = [];
            foreach ((array) ($candidatosPorBloco[$blocoId] ?? []) as $mid) {
                $mid = (int) $mid;
                if ($mid <= 0 || isset($vistos[$mid])) {
                    continue;
                }
                $vistos[$mid] = true;
                if ($setFiltro !== null && !isset($setFiltro[$mid]) && !isset($irmasDoGrupo[$mid])) {
                    continue;
                }
                if (array_key_exists($mid, $map) && $map[$mid] !== null && is_numeric($map[$mid])) {
                    continue;
                }
                $map[$mid] = $notaBase;
                if (!isset($materiaNomesPorId[$mid]) || trim((string) $materiaNomesPorId[$mid]) === '') {
                    $nome = (string) ($nomesById[$mid] ?? '');
                    if ($nome !== '') {
                        $materiaNomesPorId[$mid] = $nome;
                    }
                }
            }
        }

        return $map;
    }

    /** @param mixed $raw */
    private function flagNotaUnicaVerdadeira($raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }
        if (is_int($raw) || is_float($raw)) {
            return (int) $raw === 1;
        }
        $s = strtolower(trim((string) $raw));

        return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'sim';
    }

    /**
     * Nota única (ex.: ENAC) fechada pelo tipo só vinha nas matérias que receberam a linha.
     * Completa as outras matérias do evento e as irmãs do grupo (Gramática tem nota → Leitura também).
     *
     * @param array<int, float|int|string|null> $map
     * @param list<int> $materiasFiltro
     * @param array<int, string> $materiaNomesPorId
     * @return array<int, float|int|string|null>
     */
    private function completarMapaNotaUnicaDoTipo(
        array $map,
        array $componente,
        array $regra,
        array $materiasFiltro,
        array &$materiaNomesPorId,
        int $periodo,
        int $ano
    ): array {
        $temNota = false;
        foreach ($map as $valor) {
            if (is_numeric($valor)) {
                $temNota = true;
                break;
            }
        }
        if (!$temNota) {
            return $map;
        }
        $explicitos = $this->resolveBlocoIdsFromComponentePersisted($componente);
        $blocoIds = $this->blocosNotaUnicaDoComponente($componente, $periodo, $ano, false);
        $rows = $this->rowsNotaUnicaComNotaDoBloco($blocoIds, $map);
        if ($rows === [] && $explicitos !== []) {
            $blocoIds = $this->blocosNotaUnicaDoComponente($componente, $periodo, $ano, true);
            $rows = $this->rowsNotaUnicaComNotaDoBloco($blocoIds, $map);
        }
        if ($rows === []) {
            return $map;
        }

        return $this->completarMapaNotaUnicaBloco($map, $rows, $materiasFiltro, $materiaNomesPorId, $regra);
    }

    /**
     * A nota do bloco é a de uma matéria da pauta que já está no mapa.
     * Sem essa interseção, o bloco não espalha nota.
     *
     * @param list<int> $blocoIds
     * @param array<int, float|int|string|null> $map
     * @return list<array<string,mixed>>
     */
    private function rowsNotaUnicaComNotaDoBloco(array $blocoIds, array $map): array
    {
        if ($blocoIds === []) {
            return [];
        }
        $materiasPorBloco = $this->materiasIdsDosBlocosNotaUnica($blocoIds);
        $rows = [];
        foreach ($blocoIds as $blocoId) {
            $blocoId = (int) $blocoId;
            $notaBloco = null;
            foreach ((array) ($materiasPorBloco[$blocoId] ?? []) as $mid) {
                $mid = (int) $mid;
                if ($mid > 0 && isset($map[$mid]) && is_numeric($map[$mid])) {
                    $notaBloco = (float) $map[$mid];
                    break;
                }
            }
            if ($notaBloco === null) {
                continue;
            }
            $rows[] = [
                'bloco_id' => $blocoId,
                'nota' => $notaBloco,
                'nota_unica_todas_materias' => 1,
            ];
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function blocosNotaUnicaDoComponente(array $componente, int $periodo, int $ano, bool $somenteTipo): array
    {
        $explicitos = $somenteTipo ? [] : $this->resolveBlocoIdsFromComponentePersisted($componente);
        $tipoId = $this->parseTipoAvaliacaoIdFromComponente($componente);
        $bimestres = $this->parseProvaBimestresFromComponente($componente);
        if ($bimestres === [] && $periodo >= 1 && $periodo <= 4) {
            $bimestres = [$periodo];
        }
        if ($bimestres === []) {
            return [];
        }
        if ($explicitos === [] && $tipoId <= 0) {
            return [];
        }
        if ($somenteTipo && $tipoId <= 0) {
            return [];
        }
        try {
            $db = Database::getInstance();
            $sql = 'SELECT id FROM provas_blocos
                    WHERE deleted_at IS NULL
                      AND nota_unica_todas_materias = 1';
            $params = [];
            if ($explicitos !== []) {
                $ph = implode(',', array_fill(0, count($explicitos), '?'));
                $sql .= ' AND id IN (' . $ph . ')';
                foreach ($explicitos as $id) {
                    $params[] = $id;
                }
            } else {
                $sql .= ' AND tipo_avaliacao_id = ?';
                $params[] = $tipoId;
            }
            $phB = implode(',', array_fill(0, count($bimestres), '?'));
            $sql .= ' AND bimestre IN (' . $phB . ')';
            foreach ($bimestres as $bim) {
                $params[] = (int) $bim;
            }
            if ($ano > 0) {
                $sql .= ' AND (ano_letivo = ? OR ano_letivo IS NULL)';
                $params[] = $ano;
            }
            $rows = $db->fetchAll($sql, $params) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param list<int> $blocoIds
     * @return array<int, true>
     */
    private function filtrarBlocoIdsComNotaUnica(array $blocoIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $blocoIds), static fn ($id) => $id > 0)));
        if ($ids === []) {
            return [];
        }
        try {
            $db = \Database::getInstance();
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $rows = $db->fetchAll(
                "SELECT id FROM provas_blocos
                 WHERE deleted_at IS NULL
                   AND nota_unica_todas_materias = 1
                   AND id IN ({$ph})",
                $ids
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $out[$id] = true;
            }
        }

        return $out;
    }

    /**
     * @param list<int|string> $blocoIds
     * @return array<int, list<int>>
     */
    private function materiasIdsDosBlocosNotaUnica(array $blocoIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $blocoIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        try {
            $db = \Database::getInstance();
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $rows = $db->fetchAll(
                "SELECT bloco_id, materia_id
                 FROM provas_blocos_professores
                 WHERE bloco_id IN ({$ph})
                   AND materia_id > 0",
                $ids
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $bid = (int) ($row['bloco_id'] ?? 0);
            $mid = (int) ($row['materia_id'] ?? 0);
            if ($bid > 0 && $mid > 0) {
                $out[$bid][] = $mid;
            }
        }

        return $out;
    }

    /**
     * @param array<int, array<int, float>> $matrizPorCodigo
     * @param array<int, string> $materiaNomesPorId
     * @return array{colunas: list<array{codigo: string, nome: string, valor_global?: bool}>, linhas: list<array{materia_id: int, materia_nome: string, notas: array<string, float|null>, nota_resumo: ?float, metodo_resumo: string, erro_resumo: ?string}>}|null
     */
    private function montarMatrizMateriasSimulacao(
        array $regra,
        array $componentesRegra,
        array $componentesResultado,
        array $matrizPorCodigo,
        array $materiaNomesPorId,
        array $matrizPercentStatsPorCodigo = [],
        array $materiasAgrupadasHerdadas = [],
        array $contextoAluno = [],
        bool $forcarAgrupamentoLinhas = false,
        bool $pularAgrupamentoLinhas = false
    ): ?array {
        $roundMode = $this->normalizeRoundMode((string) ($regra['round_mode'] ?? 'none'));
        if ($pularAgrupamentoLinhas) {
            $grp = [
                'matriz_por_codigo' => $matrizPorCodigo,
                'materia_nomes_por_id' => $materiaNomesPorId,
                'materias_agrupadas' => [],
                'grupos_virtual_mids' => [],
                'agrupamento_por_virtual_mid' => [],
            ];
            $materiasAgrupadasHerdadas = [];
        } else {
            $grp = $this->aplicarAgrupamentoLinhasPorComponente(
                $componentesRegra,
                $matrizPorCodigo,
                $materiaNomesPorId,
                $matrizPercentStatsPorCodigo,
                (string) ($regra['exibir_em'] ?? 'boletim'),
                $forcarAgrupamentoLinhas,
                $roundMode
            );
        }
        $matrizPorCodigo = $grp['matriz_por_codigo'];
        $materiaNomesPorId = $grp['materia_nomes_por_id'];
        $materiasAgrupadas = $grp['materias_agrupadas'];
        // Adiciona matérias agrupadas herdadas (vindas de evento_boletim que aponta para
        // outra regra com group_line ativo). Elas serão ocultadas como linhas individuais,
        // pois o destino já recebeu a linha agregada do grupo (mid sintético negativo).
        foreach ($materiasAgrupadasHerdadas as $midHer => $_v) {
            $midHer = (int) $midHer;
            if ($midHer > 0) {
                $materiasAgrupadas[$midHer] = true;
            }
        }
        $this->absorverFaltasDeMateriasOcultas(
            $matrizPorCodigo,
            $materiaNomesPorId,
            $materiasAgrupadas,
            $componentesRegra
        );
        $gruposVirtualMids = is_array($grp['grupos_virtual_mids'] ?? null) ? $grp['grupos_virtual_mids'] : [];
        $agrupamentoPorVirtualMid = is_array($grp['agrupamento_por_virtual_mid'] ?? null)
            ? $grp['agrupamento_por_virtual_mid']
            : [];

        $colunas = [];
        foreach ($componentesRegra as $c) {
            $cod = trim((string) ($c['codigo'] ?? ''));
            if ($cod === '') {
                continue;
            }
            $stCol = (string) ($c['source_type'] ?? 'provas_sistema');
            $layoutMeta = $this->parseLayoutMetaFromComponente($c);
            $colunas[] = [
                'id' => (int) ($c['id'] ?? 0),
                'codigo' => $cod,
                'nome' => (string) ($c['nome'] ?? $cod),
                'valor_global' => ($stCol === 'manual'),
                'source_type' => $stCol,
                'escala_max' => max(0.01, (float) ($c['escala_max'] ?? 10)),
                'layout_group' => $layoutMeta['group'],
                'layout_type' => $layoutMeta['type'],
                'round_mode_efetivo' => $this->resolveRoundModeComponente($c, $roundMode),
            ];
        }
        if ($colunas === []) {
            return null;
        }

        $allMids = [];
        foreach ($matrizPorCodigo as $map) {
            foreach (array_keys($map) as $mid) {
                $allMids[(int) $mid] = true;
            }
        }
        if ($gruposVirtualMids !== []) {
            foreach (array_keys($gruposVirtualMids) as $vmid) {
                $allMids[(int) $vmid] = true;
            }
        }

        if ($allMids === [] && $materiaNomesPorId !== []) {
            foreach (array_keys($materiaNomesPorId) as $mid) {
                $allMids[(int) $mid] = true;
            }
        }

        $materiasFiltroPorCodigo = [];
        foreach ($componentesRegra as $cFiltro) {
            $codFiltro = trim((string) ($cFiltro['codigo'] ?? ''));
            if ($codFiltro === '') {
                continue;
            }
            $idsFiltro = $this->parseMateriasIdsFromComponente($cFiltro);
            if ($idsFiltro !== []) {
                $materiasFiltroPorCodigo[$codFiltro] = array_fill_keys($idsFiltro, true);
            }
        }

        foreach ($componentesResultado as $cr) {
            $st = (string) ($cr['source_type'] ?? '');
            // Jornadas e faltas por matéria entram só via matrizPorCodigo.
            // Manual e faltas legadas (sem matéria) repetem valor global.
            if (($st !== 'manual' && $st !== 'faltas_evento') || !is_numeric($cr['valor'] ?? null)) {
                continue;
            }
            $cod = (string) ($cr['codigo'] ?? '');
            if ($st === 'faltas_evento' && isset($matrizPorCodigo[$cod]) && is_array($matrizPorCodigo[$cod]) && $matrizPorCodigo[$cod] !== []) {
                continue;
            }
            $materiasFiltroGlobal = ($st === 'faltas_evento') ? ($materiasFiltroPorCodigo[$cod] ?? []) : [];
            $v = (float) $cr['valor'];
            if ($allMids === []) {
                $allMids[0] = true;
                $materiaNomesPorId[0] = '—';
            }
            foreach (array_keys($allMids) as $mid) {
                $mid = (int) $mid;
                if ($materiasFiltroGlobal !== [] && !isset($materiasFiltroGlobal[$mid])) {
                    continue;
                }
                $matrizPorCodigo[$cod][(int) $mid] = $v;
            }
        }

        if ($allMids === []) {
            return null;
        }

        $materiasSelecionadas = $this->parseMateriasIdsFromRegra($regra);
        // Pais com desdobramento nunca viram linha avulsa (só o group_line / filhos).
        $idsRotuloPai = $this->idsRotuloPaiComponente();
        $boletimIdEscopo = (int) ($regra['boletim_id'] ?? 0);
        $modeloMaterias = $boletimIdEscopo > 0
            ? $this->materiasIdsDoModeloBoletim($boletimIdEscopo)
            : [];
        if ($modeloMaterias !== []) {
            // Modelo de Boletim é a fonte da verdade (ex.: Redação desmarcada some da simulação).
            $materiasSelecionadas = $this->expandirMateriasComFilhos($modeloMaterias);
        } elseif ($pularAgrupamentoLinhas) {
            $materiasSelecionadas = $this->expandirMateriasComFilhos($materiasSelecionadas);
        }
        $nomesCatalogoById = [];
        if ($materiasSelecionadas !== [] || $allMids !== []) {
            $catalogoMaterias = $this->boletimConfig->getAvailableSubjects(2000);
            foreach ((array) $catalogoMaterias as $mCat) {
                $midCat = (int) ($mCat['id'] ?? 0);
                $nomeCat = trim((string) ($mCat['nome'] ?? ''));
                if ($midCat > 0 && $nomeCat !== '') {
                    $nomesCatalogoById[$midCat] = $nomeCat;
                }
            }
            $pathComp = dirname(__DIR__, 2) . '/Models/Education/ComponenteCurricular.php';
            if (is_file($pathComp)) {
                require_once $pathComp;
                try {
                    foreach ((new \ComponenteCurricular())->getOficiaisParaMatriz(true) as $of) {
                        $oid = (int) ($of['id'] ?? 0);
                        $nomeOf = trim((string) ($of['nome'] ?? ''));
                        if ($oid > 0 && $nomeOf !== '' && !isset($nomesCatalogoById[$oid])) {
                            $nomesCatalogoById[$oid] = $nomeOf;
                        }
                    }
                } catch (Throwable $e) {
                    // catálogo operacional já preenchido
                }
            }
        }
        if ($materiasSelecionadas !== []) {
            $nomesGruposVirtuais = [];
            foreach (array_keys($gruposVirtualMids) as $vmidNome) {
                $nomeGrupo = trim((string) ($materiaNomesPorId[(int) $vmidNome] ?? ''));
                $nomeKeyGrupo = $this->canonicalMateriaNomeKey($nomeGrupo);
                if ($nomeKeyGrupo !== '') {
                    $nomesGruposVirtuais[$nomeKeyGrupo] = true;
                }
            }
            foreach ($materiasSelecionadas as $midSel) {
                $midSel = (int) $midSel;
                if ($midSel <= 0) {
                    continue;
                }
                // Sem nome no catálogo = id órfão (ex.: matéria apagada) — não inventa linha.
                if (!isset($nomesCatalogoById[$midSel])) {
                    continue;
                }
                $nomeSel = (string) ($materiaNomesPorId[$midSel] ?? $nomesCatalogoById[$midSel]);
                $nomeKeySel = $this->canonicalMateriaNomeKey($nomeSel);
                if ($nomeKeySel !== '' && isset($nomesGruposVirtuais[$nomeKeySel])) {
                    continue;
                }
                if (isset($idsRotuloPai[$midSel])) {
                    continue;
                }
                $allMids[$midSel] = true;
                if (!isset($materiaNomesPorId[$midSel])) {
                    $materiaNomesPorId[$midSel] = $nomesCatalogoById[$midSel];
                }
            }
        }
        // No demonstrativo (sem colapsar group_line), a mãe-rótulo continua na matriz
        // só para a hierarquia herdar a nota. A linha solta "Língua Portuguesa" não fica.
        $labelsGrupoDemonstrativo = [];
        if ($pularAgrupamentoLinhas) {
            foreach ($componentesRegra as $compLbl) {
                if (!is_array($compLbl)) {
                    continue;
                }
                $grpLbl = $this->parseGroupLineConfigFromComponente($compLbl);
                if ($grpLbl === null) {
                    continue;
                }
                $labelKey = $this->canonicalMateriaNomeKey((string) ($grpLbl['label'] ?? ''));
                if ($labelKey !== '') {
                    $labelsGrupoDemonstrativo[$labelKey] = true;
                }
            }
        }
        // Remove órfãos / pais-rótulo que já entraram via nota sem nome resolvido.
        foreach (array_keys($allMids) as $midLimpeza) {
            $midLimpeza = (int) $midLimpeza;
            if ($midLimpeza <= 0) {
                continue;
            }
            if (isset($idsRotuloPai[$midLimpeza])) {
                $nomeRotulo = (string) ($materiaNomesPorId[$midLimpeza] ?? ($nomesCatalogoById[$midLimpeza] ?? ''));
                $nomeRotuloKey = $this->canonicalMateriaNomeKey($nomeRotulo);
                if ($nomeRotuloKey === '' || !isset($labelsGrupoDemonstrativo[$nomeRotuloKey])) {
                    unset($allMids[$midLimpeza]);
                    continue;
                }
            }
            $nomeLimpeza = trim((string) ($materiaNomesPorId[$midLimpeza] ?? ($nomesCatalogoById[$midLimpeza] ?? '')));
            if ($nomeLimpeza === '' || preg_match('/^Matéria #\d+$/u', $nomeLimpeza) === 1) {
                unset($allMids[$midLimpeza]);
                continue;
            }
            if (!isset($materiaNomesPorId[$midLimpeza])) {
                $materiaNomesPorId[$midLimpeza] = $nomeLimpeza;
            }
        }
        // Filha da área desmarcada neste evento (ex.: Literatura fora de Língua
        // Portuguesa só neste bimestre) não vira linha, nem no quadro nem no gravado.
        $excluidosGroupLine = $this->materiaIdsExcluidosGroupLineDosComponentes($componentesRegra);
        if ($excluidosGroupLine !== []) {
            foreach (array_keys($excluidosGroupLine) as $midExcluido) {
                unset($allMids[(int) $midExcluido]);
            }
            if ($materiasSelecionadas !== []) {
                $materiasSelecionadas = array_values(array_filter(
                    $materiasSelecionadas,
                    static function ($id) use ($excluidosGroupLine): bool {
                        return !isset($excluidosGroupLine[(int) $id]);
                    }
                ));
            }
        }
        if ($allMids === []) {
            return null;
        }

        $filhasPorVirtualMid = is_array($grp['filhas_por_virtual_mid'] ?? null)
            ? $grp['filhas_por_virtual_mid']
            : [];
        $this->aplicarEspalhamentoJornadasNotaUnicaNaMatriz(
            $componentesRegra,
            $matrizPorCodigo,
            $componentesResultado,
            $allMids,
            $roundMode,
            $filhasPorVirtualMid
        );

        $midsOrdenados = array_keys($allMids);
        if ($materiasAgrupadas !== []) {
            $midsOrdenados = array_values(array_filter($midsOrdenados, static function (int $mid) use ($materiasAgrupadas) {
                return $mid <= 0 || !isset($materiasAgrupadas[$mid]);
            }));
        }
        // Com lista do modelo/evento: só exibe essas matérias (notas órfãs de disciplina
        // desmarcada — ex. Redação — não entram na simulação).
        if ($materiasSelecionadas !== []) {
            $set = array_fill_keys($materiasSelecionadas, true);
            $midsOrdenados = array_values(array_filter($midsOrdenados, static function (int $mid) use ($set) {
                return $mid <= 0 || isset($set[$mid]);
            }));
        }
        // Filhas marcadas na linha única entram mesmo se o modelo só tinha o pai
        // (a nota pode estar no id da filha e o filtro do boletim ter ficado no rótulo).
        if ($pularAgrupamentoLinhas) {
            $idsGrupoLinha = [];
            $labelsGrupoLinha = [];
            foreach ($componentesRegra as $compGl) {
                if (!is_array($compGl)) {
                    continue;
                }
                $grpGl = $this->parseGroupLineConfigFromComponente($compGl);
                if ($grpGl === null) {
                    continue;
                }
                $labelGl = $this->canonicalMateriaNomeKey((string) ($grpGl['label'] ?? ''));
                if ($labelGl !== '') {
                    $labelsGrupoLinha[$labelGl] = true;
                }
                foreach ((array) ($grpGl['materias_ids'] ?? []) as $midGl) {
                    $midGl = (int) $midGl;
                    if ($midGl > 0) {
                        $idsGrupoLinha[$midGl] = true;
                    }
                }
            }
            $jaOrdenado = array_fill_keys(array_map('intval', $midsOrdenados), true);
            foreach (array_keys($idsGrupoLinha) as $midGl) {
                $midGl = (int) $midGl;
                if (isset($jaOrdenado[$midGl])) {
                    continue;
                }
                $nomeGl = trim((string) ($nomesCatalogoById[$midGl] ?? ($materiaNomesPorId[$midGl] ?? '')));
                if ($nomeGl === '') {
                    continue;
                }
                if (!isset($materiaNomesPorId[$midGl])) {
                    $materiaNomesPorId[$midGl] = $nomeGl;
                }
                $nomeKeyGl = $this->canonicalMateriaNomeKey($nomeGl);
                foreach ($matrizPorCodigo as $codGl => $mapGl) {
                    if (!is_array($mapGl)) {
                        continue;
                    }
                    if (isset($mapGl[$midGl]) && is_numeric($mapGl[$midGl])) {
                        continue;
                    }
                    foreach ($mapGl as $midOutro => $valOutro) {
                        $midOutro = (int) $midOutro;
                        if ($midOutro === $midGl || !is_numeric($valOutro)) {
                            continue;
                        }
                        $nomeOutro = trim((string) ($materiaNomesPorId[$midOutro] ?? ($nomesCatalogoById[$midOutro] ?? '')));
                        if ($nomeKeyGl !== '' && $this->canonicalMateriaNomeKey($nomeOutro) === $nomeKeyGl) {
                            $matrizPorCodigo[$codGl][$midGl] = (float) $valOutro;
                            break;
                        }
                    }
                }
                $midsOrdenados[] = $midGl;
                $jaOrdenado[$midGl] = true;
            }
            if ($labelsGrupoLinha !== []) {
                foreach (array_keys($idsRotuloPai) as $pidMae) {
                    $pidMae = (int) $pidMae;
                    if ($pidMae <= 0 || isset($jaOrdenado[$pidMae])) {
                        continue;
                    }
                    $nomeMae = trim((string) ($nomesCatalogoById[$pidMae] ?? ($materiaNomesPorId[$pidMae] ?? '')));
                    $nomeMaeKey = $this->canonicalMateriaNomeKey($nomeMae);
                    if ($nomeMaeKey === '' || !isset($labelsGrupoLinha[$nomeMaeKey])) {
                        continue;
                    }
                    if (!isset($materiaNomesPorId[$pidMae]) && $nomeMae !== '') {
                        $materiaNomesPorId[$pidMae] = $nomeMae;
                    }
                    $midsOrdenados[] = $pidMae;
                    $jaOrdenado[$pidMae] = true;
                }
            }
        }

        $colunasFaltasPorCodigo = [];
        foreach ($colunas as $colMetaDup) {
            $codDup = trim((string) ($colMetaDup['codigo'] ?? ''));
            if ($codDup === '') {
                continue;
            }
            $colunasFaltasPorCodigo[$codDup] = ((string) ($colMetaDup['source_type'] ?? '')) === 'faltas_evento'
                || strtolower((string) ($colMetaDup['layout_type'] ?? '')) === 'faltas';
        }
        $primaryMidPorNome = [];
        $remapMidDuplicado = [];
        foreach ($midsOrdenados as $midDup) {
            $midDup = (int) $midDup;
            $nomeDup = trim((string) ($materiaNomesPorId[$midDup] ?? ($midDup === 0 ? '—' : ('Matéria #' . $midDup))));
            $nomeKeyDup = $this->canonicalMateriaNomeKey($nomeDup);
            if ($nomeKeyDup === '') {
                continue;
            }
            if (!isset($primaryMidPorNome[$nomeKeyDup])) {
                $primaryMidPorNome[$nomeKeyDup] = $midDup;
                continue;
            }
            $remapMidDuplicado[$midDup] = (int) $primaryMidPorNome[$nomeKeyDup];
        }
        if ($remapMidDuplicado !== []) {
            foreach ($remapMidDuplicado as $midOrigem => $midDestino) {
                foreach ($matrizPorCodigo as $codMap => $mapVals) {
                    if (!is_array($mapVals) || !array_key_exists($midOrigem, $mapVals)) {
                        continue;
                    }
                    $valorOrigem = $mapVals[$midOrigem];
                    if (!is_numeric($valorOrigem)) {
                        unset($matrizPorCodigo[$codMap][$midOrigem]);
                        continue;
                    }
                    $valorDestino = $matrizPorCodigo[$codMap][$midDestino] ?? null;
                    if (!is_numeric($valorDestino)) {
                        $matrizPorCodigo[$codMap][$midDestino] = (float) $valorOrigem;
                    } elseif (!empty($colunasFaltasPorCodigo[(string) $codMap])) {
                        $matrizPorCodigo[$codMap][$midDestino] = (float) $valorDestino + (float) $valorOrigem;
                    } elseif (abs((float) $valorDestino) < 0.00001 && abs((float) $valorOrigem) > 0.00001) {
                        $matrizPorCodigo[$codMap][$midDestino] = (float) $valorOrigem;
                    }
                    unset($matrizPorCodigo[$codMap][$midOrigem]);
                }
            }
            $midsRemovidos = array_fill_keys(array_map('intval', array_keys($remapMidDuplicado)), true);
            $midsOrdenados = array_values(array_filter($midsOrdenados, static function (int $mid) use ($midsRemovidos) {
                return !isset($midsRemovidos[$mid]);
            }));
        }

        $chavesNomeMateria = [];
        foreach ($midsOrdenados as $midOrdem) {
            $midOrdem = (int) $midOrdem;
            $chavesNomeMateria[$midOrdem] = $this->chaveOrdemAlfabeticaMateria(
                (string) ($materiaNomesPorId[$midOrdem] ?? '')
            );
        }
        usort($midsOrdenados, static function (int $a, int $b) use ($chavesNomeMateria): int {
            $na = $chavesNomeMateria[$a] ?? '';
            $nb = $chavesNomeMateria[$b] ?? '';
            if ($na !== $nb) {
                return $na <=> $nb;
            }

            return $a <=> $b;
        });

        $formula = trim((string) ($regra['formula_final'] ?? ''));
        $resultadoCodigos = [];
        $mediaFinalCodigo = '';
        $temExpressao = [];
        foreach ($componentesRegra as $cExpr) {
            $kExpr = trim((string) ($cExpr['codigo'] ?? ''));
            if ($kExpr === '') {
                continue;
            }
            if ($this->parseExpressaoColunaCalculada($cExpr) !== '') {
                $temExpressao[$kExpr] = true;
            }
        }
        foreach ($colunas as $cMeta) {
            $lt = strtolower(trim((string) ($cMeta['layout_type'] ?? '')));
            $lg = strtolower(trim((string) ($cMeta['layout_group'] ?? '')));
            $cc = trim((string) ($cMeta['codigo'] ?? ''));
            if ($cc === '') {
                continue;
            }
            if ($lt === 'resultado' && empty($temExpressao[$cc])) {
                $resultadoCodigos[] = $cc;
            }
            if ($mediaFinalCodigo === '' && $lg === 'final' && $lt === 'media') {
                $mediaFinalCodigo = $cc;
            }
        }
        $usarResultadoAprovacao = (int) ($regra['usar_resultado_aprovacao'] ?? 1) === 1;
        $notaMinimaAprovacao = isset($regra['nota_minima_aprovacao']) && is_numeric($regra['nota_minima_aprovacao'])
            ? (float) $regra['nota_minima_aprovacao']
            : 6.0;
        $tracoMinPorCodigo = [];
        foreach ($componentesRegra as $cTr) {
            $kTr = trim((string) ($cTr['codigo'] ?? ''));
            if ($kTr === '') {
                continue;
            }
            $stTr = (string) ($cTr['source_type'] ?? '');
            if ($stTr === 'calculado' && $this->parseCalculadoTracoAbaixoMinimoFromComponente($cTr)) {
                $tracoMinPorCodigo[$kTr] = true;
            } elseif ($stTr === 'jornadas') {
                $cfgJrTr = $this->parseJornadasConfigFromComponente($cTr);
                if (!empty($cfgJrTr['traco_abaixo_minimo'])) {
                    $tracoMinPorCodigo[$kTr] = true;
                }
            }
        }
        $modoSomaMaePorCodigo = [];
        foreach ($componentesRegra as $cSoma) {
            if (!is_array($cSoma)) {
                continue;
            }
            $codSoma = trim((string) ($cSoma['codigo'] ?? ''));
            if ($codSoma === '') {
                continue;
            }
            $grpSoma = $this->parseGroupLineConfigFromComponente($cSoma);
            if ($grpSoma === null) {
                continue;
            }
            $modoSomaMaePorCodigo[$codSoma] = strtolower((string) ($grpSoma['mode'] ?? 'media')) === 'soma';
        }
        $linhas = [];
        foreach ($midsOrdenados as $mid) {
            $notasLinha = [];
            $valoresFormula = [];
            foreach ($colunas as $col) {
                $cod = $col['codigo'];
                $mapCod = $matrizPorCodigo[$cod] ?? [];
                $cell = $mapCod[$mid] ?? null;
                $roundModeCol = (string) ($col['round_mode_efetivo'] ?? $roundMode);
                if (is_numeric($cell)) {
                    $midLinha = (int) $mid;
                    // Mãe (mid virtual < 0): já saiu arredondada (ou não) do group_line — não reaplicar half.
                    if ($midLinha < 0) {
                        $vcell = round((float) $cell, 2);
                    } elseif ($midLinha > 0 && !empty($this->midsSemArredondamentoGrupo[$midLinha])) {
                        $vcell = round((float) $cell, 2);
                    } else {
                        $vcell = $this->applyRoundMode((float) $cell, $roundModeCol);
                    }
                } else {
                    $vcell = null;
                }
                $colunaEhFaltas = ((string) ($col['source_type'] ?? '')) === 'faltas_evento'
                    || strtolower((string) ($col['layout_type'] ?? '')) === 'faltas';
                // Soma na linha-mãe passa de 10 (ex.: 8,50+5,00). O teto vale para cada matéria.
                $maeEmSoma = ((int) $mid) < 0 && !empty($modoSomaMaePorCodigo[$cod]);
                if (!$colunaEhFaltas && !$maeEmSoma && is_numeric($vcell)) {
                    $escalaCol = max(0.01, (float) ($col['escala_max'] ?? 10));
                    $vcell = round(max(0.0, min($escalaCol, (float) $vcell)), 2);
                }
                $notasLinha[$cod] = $vcell;
                $valoresFormula[$cod] = is_numeric($vcell) ? (float) $vcell : 0.0;
                $statsCod = $matrizPercentStatsPorCodigo[$cod] ?? [];
                if (isset($statsCod[$mid]) && is_array($statsCod[$mid])) {
                    $notasLinha[$cod . '__n'] = (int) ($statsCod[$mid]['acertos'] ?? 0);
                    $notasLinha[$cod . '__q'] = (int) ($statsCod[$mid]['total'] ?? 0);
                }
            }
            $resumo = $this->resumoNotaLinhaMatriz($regra, $componentesResultado, $notasLinha, $valoresFormula, $formula, (int) $mid);
            if ($resultadoCodigos !== []) {
                $mediaRef = null;
                if ($mediaFinalCodigo !== '' && isset($notasLinha[$mediaFinalCodigo]) && is_numeric($notasLinha[$mediaFinalCodigo])) {
                    $mediaRef = (float) $notasLinha[$mediaFinalCodigo];
                } elseif (is_numeric($resumo['valor'] ?? null)) {
                    $mediaRef = (float) $resumo['valor'];
                }
                $resultadoTxt = '-';
                if ($usarResultadoAprovacao && $mediaRef !== null) {
                    $agrupamentoLinha = ((int) $mid < 0)
                        ? (int) ($agrupamentoPorVirtualMid[(int) $mid] ?? 0)
                        : 0;
                    $resultadoTxt = $this->rotuloResultadoAcademico(
                        $regra,
                        $contextoAluno,
                        (int) $mid,
                        $notasLinha,
                        $colunas,
                        $mediaRef,
                        $notaMinimaAprovacao,
                        $agrupamentoLinha > 0 ? $agrupamentoLinha : null
                    );
                }
                foreach ($resultadoCodigos as $codRes) {
                    $notasLinha[$codRes] = $resultadoTxt;
                }
            }
            foreach (array_keys($tracoMinPorCodigo) as $codTraco) {
                if (!isset($notasLinha[$codTraco]) || !is_numeric($notasLinha[$codTraco])) {
                    continue;
                }
                if ((float) $notasLinha[$codTraco] < $notaMinimaAprovacao) {
                    $notasLinha[$codTraco] = '-';
                }
            }

            $linhas[] = [
                'materia_id' => (int) $mid,
                'materia_nome' => (string) ($materiaNomesPorId[$mid] ?? ($mid === 0 ? '—' : ('Matéria #' . $mid))),
                'notas' => $notasLinha,
                'nota_resumo' => $this->applyRoundMode($resumo['valor'], $roundMode),
                'metodo_resumo' => $resumo['metodo'],
                'erro_resumo' => $resumo['erro'],
            ];
        }

        return [
            'colunas' => $colunas,
            'linhas' => $linhas,
            'tem_formula' => $formula !== '',
        ];
    }

    /**
     * Outra peça gravou a mesma área com outra chave (ex.: Língua Portuguesa).
     * O evento mostra uma vez; coordenação e detalhe do aluno não podem repetir o bloco.
     *
     * @param array<string, array<string,mixed>> $grupos
     * @param array<int,int> $filhosIds
     */
    private function chaveGrupoLinhaMesmaArea(array $grupos, string $labelKey, array $filhosIds): string
    {
        $filhosIds = $this->idsPositivosDeLista($filhosIds);
        foreach ($grupos as $gkExistente => $g) {
            if (!is_array($g)) {
                continue;
            }
            $labelExistente = $this->tokenNomeAreaGrupo((string) ($g['label'] ?? ''));
            $idsExistentes = $this->idsPositivosDeLista(is_array($g['filhos_ids'] ?? null) ? $g['filhos_ids'] : []);
            $mesmoNome = $labelKey !== ''
                && $labelKey !== 'grupo'
                && $labelExistente !== ''
                && $labelExistente !== 'grupo'
                && $labelExistente === $labelKey;
            $mesmosFilhos = $filhosIds !== [] && $idsExistentes === $filhosIds;
            $compartilhamFilho = $mesmoNome && array_intersect_key($idsExistentes, $filhosIds) !== [];
            if ($mesmosFilhos || $compartilhamFilho) {
                return (string) $gkExistente;
            }
        }

        return '';
    }

    private function tokenNomeAreaGrupo(string $nome): string
    {
        $token = $this->normalizeEventoCodigoToken($nome);
        if ($token === '' || $token === 'grupo') {
            return '';
        }

        return $token;
    }

    /**
     * @param array<int|string,mixed> $ids
     * @return array<int,int>
     */
    private function idsPositivosDeLista(array $ids): array
    {
        $out = [];
        foreach ($ids as $k => $v) {
            if ($v === true && (int) $k > 0) {
                $out[(int) $k] = (int) $k;
                continue;
            }
            $id = (int) $v;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Demonstrativo: linha-mãe do group_line (ex.: Língua Portuguesa) com as médias,
     * seguida das matérias filhas com as notas individuais.
     *
     * Mantém o nome público antigo para callers legados.
     *
     * @param array<string,mixed> $simulacao
     * @param array<string,mixed> $regra
     * @return array<string,mixed>
     */
    public function filtrarMatrizDemonstrativoSemPaiAgrupado(array $simulacao, array $regra): array
    {
        return $this->montarMatrizDemonstrativoComGrupoHierarquico($simulacao, $regra);
    }

    /**
     * @param array<string,mixed> $simulacao
     * @param array<string,mixed> $regra
     * @return array<string,mixed>
     */
    public function montarMatrizDemonstrativoComGrupoHierarquico(array $simulacao, array $regra): array
    {
        $matriz = is_array($simulacao['matriz_materias'] ?? null) ? $simulacao['matriz_materias'] : null;
        if (!is_array($matriz) || empty($matriz['linhas']) || !is_array($matriz['linhas'])) {
            return $simulacao;
        }

        $grupos = [];
        $modoPorCodigo = [];
        $modoPorPeca = [];
        foreach ((array) ($regra['componentes'] ?? []) as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $grp = $this->parseGroupLineConfigFromComponente($comp);
            if ($grp === null) {
                continue;
            }
            $codComp = trim((string) ($comp['codigo'] ?? ''));
            $modoColuna = strtolower((string) ($grp['mode'] ?? 'media')) === 'soma' ? 'soma' : 'media';
            if ($codComp !== '') {
                $modoPorCodigo[$codComp] = $modoColuna;
                $pecaComp = $this->pecaGrupoDoCodigoColuna($codComp);
                if ($pecaComp !== '' && !isset($modoPorPeca[$pecaComp])) {
                    $modoPorPeca[$pecaComp] = $modoColuna;
                }
            }
            foreach ((array) ($grp['modos'] ?? []) as $pecaM => $modoM) {
                $pecaM = strtolower(trim((string) $pecaM));
                if ($pecaM === '') {
                    continue;
                }
                $modoPorPeca[$pecaM] = strtolower((string) $modoM) === 'soma' ? 'soma' : 'media';
            }
            $gk = (string) ($grp['key'] ?? '');
            if ($gk === '') {
                continue;
            }
            $filhosIds = [];
            foreach ((array) ($grp['materias_ids'] ?? []) as $midF) {
                $midF = (int) $midF;
                if ($midF > 0) {
                    $filhosIds[$midF] = $midF;
                }
            }
            if (count($filhosIds) < 2) {
                continue;
            }
            $label = trim((string) ($grp['label'] ?? ''));
            if ($label === '') {
                $label = 'Grupo';
            }
            $rotuloIds = [];
            $agId = (int) ($grp['agrupamento_id'] ?? 0);
            if ($agId > 0) {
                $cad = $this->carregarAgrupamentoCadastro($agId);
                $rotuloId = (int) ($cad['materia_rotulo_id'] ?? 0);
                if ($rotuloId > 0 && empty($filhosIds[$rotuloId])) {
                    $rotuloIds[$rotuloId] = true;
                }
                $nomeCad = trim((string) ($cad['nome'] ?? ''));
                if ($nomeCad !== '' && $label === 'Grupo') {
                    $label = $nomeCad;
                }
            }
            $labelKeyGrupo = $this->tokenNomeAreaGrupo($label);
            // Mesma chave, ou a mesma área gravada de novo em outra peça (outro key).
            // Sem isso, Língua Portuguesa sai duas vezes na coordenação e no aluno.
            $gkMesmaArea = isset($grupos[$gk])
                ? $gk
                : $this->chaveGrupoLinhaMesmaArea($grupos, $labelKeyGrupo, $filhosIds);
            if ($gkMesmaArea !== '') {
                if (!isset($grupos[$gk])) {
                    foreach ($filhosIds as $midF) {
                        $grupos[$gkMesmaArea]['filhos_ids'][$midF] = $midF;
                    }
                    foreach ($rotuloIds as $rid => $_) {
                        $grupos[$gkMesmaArea]['rotulo_ids'][(int) $rid] = true;
                    }
                    if ($this->tokenNomeAreaGrupo((string) ($grupos[$gkMesmaArea]['label'] ?? '')) === ''
                        && $labelKeyGrupo !== '') {
                        $grupos[$gkMesmaArea]['label'] = $label;
                        $grupos[$gkMesmaArea]['label_key'] = $this->canonicalMateriaNomeKey($label);
                    }
                }
                $arredJa = $this->normalizarArredondamentoGrupo($grp['arredondamento'] ?? 'todos');
                if ($arredJa !== 'todos') {
                    $grupos[$gkMesmaArea]['arredondamento'] = $arredJa;
                }
                if (!empty($grp['ocultar_filhas'])) {
                    $grupos[$gkMesmaArea]['ocultar_filhas'] = true;
                }
                continue;
            }
            $modoGrupo = strtolower(trim((string) ($grp['modo_padrao'] ?? '')));
            if ($modoGrupo !== 'media' && $modoGrupo !== 'soma') {
                $modoGrupo = strtolower(trim((string) ($grp['mode'] ?? 'media'))) === 'soma' ? 'soma' : 'media';
            }
            $grupos[$gk] = [
                'key' => $gk,
                'label' => $label,
                'label_key' => $this->canonicalMateriaNomeKey($label),
                'mode' => $modoGrupo,
                'filhos_ids' => $filhosIds,
                'rotulo_ids' => $rotuloIds,
                'agrupamento_id' => $agId,
                'arredondamento' => $this->normalizarArredondamentoGrupo($grp['arredondamento'] ?? 'todos'),
                'ocultar_filhas' => !empty($grp['ocultar_filhas']),
            ];
        }
        if ($grupos === []) {
            return $simulacao;
        }

        // Filhas da área no cadastro que foram desmarcadas neste evento não aparecem
        // (nem aninhadas, nem como linha avulsa). Ex.: Literatura fora de materias_ids.
        $excluidosDoGrupo = $this->materiasExcluidasDoGroupLineNoEvento($grupos);

        $linhasBoletim = [];
        $matrizBol = is_array($simulacao['matriz_materias_boletim'] ?? null)
            ? $simulacao['matriz_materias_boletim']
            : null;
        if (is_array($matrizBol) && is_array($matrizBol['linhas'] ?? null)) {
            $linhasBoletim = $matrizBol['linhas'];
        }

        $todosFilhos = [];
        $rotulosGrupo = [];
        $rotulosIds = [];
        foreach ($grupos as $g) {
            foreach ($g['filhos_ids'] as $midF) {
                $todosFilhos[(int) $midF] = true;
            }
            if ($g['label_key'] !== '') {
                $rotulosGrupo[$g['label_key']] = true;
            }
            foreach ((array) ($g['rotulo_ids'] ?? []) as $rid => $_) {
                $rotulosIds[(int) $rid] = true;
            }
        }

        $linhasPorMid = [];
        $outras = [];
        $notasMaeHerdada = [];
        foreach ($matriz['linhas'] as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $mid = (int) ($linha['materia_id'] ?? 0);
            $nomeKey = $this->canonicalMateriaNomeKey((string) ($linha['materia_nome'] ?? ''));
            $notasLinha = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
            // Virtual / rótulo solto: some do flat; a nota da mãe fica para o bloco do grupo.
            if ($mid < 0) {
                if ($nomeKey !== '' && isset($rotulosGrupo[$nomeKey]) && $notasLinha !== []) {
                    $notasMaeHerdada[$nomeKey] = $notasLinha;
                }
                continue;
            }
            if ($mid > 0 && isset($excluidosDoGrupo[$mid])) {
                continue;
            }
            if ($mid > 0 && isset($rotulosIds[$mid])) {
                if ($nomeKey !== '' && isset($rotulosGrupo[$nomeKey]) && $notasLinha !== []) {
                    $notasMaeHerdada[$nomeKey] = $notasLinha;
                }
                continue;
            }
            if ($mid > 0 && isset($todosFilhos[$mid])) {
                $linhasPorMid[$mid] = $linha;
                continue;
            }
            if ($nomeKey !== '' && isset($rotulosGrupo[$nomeKey]) && !isset($todosFilhos[$mid])) {
                if ($notasLinha !== []) {
                    $notasMaeHerdada[$nomeKey] = $notasLinha;
                }
                continue;
            }
            $outras[] = $linha;
        }

        $blocos = [];
        foreach ($outras as $linha) {
            $blocos[] = [
                'sort' => $this->canonicalMateriaNomeKey((string) ($linha['materia_nome'] ?? '')),
                'linhas' => [$linha],
            ];
        }

        foreach ($grupos as $g) {
            $filhosLinhas = [];
            foreach ($g['filhos_ids'] as $midF) {
                if (isset($linhasPorMid[$midF])) {
                    $filho = $linhasPorMid[$midF];
                    $filho['eh_grupo_pai'] = 0;
                    $filho['eh_grupo_filho'] = 1;
                    $filho['grupo_pai_nome'] = $g['label'];
                    $filho['grupo_key'] = $g['key'];
                    $filhosLinhas[] = $filho;
                }
            }
            $notasHerdadasMae = is_array($notasMaeHerdada[$g['label_key']] ?? null)
                ? $notasMaeHerdada[$g['label_key']]
                : [];
            $temNotaHerdada = false;
            foreach ($notasHerdadasMae as $valHerdado) {
                if (is_numeric($valHerdado)) {
                    $temNotaHerdada = true;
                    break;
                }
            }
            $paiNotasBoletim = $this->notasLinhaGrupoDoBoletim($linhasBoletim, $g['label'], $g['label_key']);
            $temNotaBoletim = false;
            if (is_array($paiNotasBoletim)) {
                foreach ($paiNotasBoletim as $valBoletim) {
                    if (is_numeric($valBoletim)) {
                        $temNotaBoletim = true;
                        break;
                    }
                }
            }
            if ($filhosLinhas === [] && !$temNotaHerdada && !$temNotaBoletim) {
                continue;
            }
            usort($filhosLinhas, function (array $a, array $b): int {
                $ka = $this->canonicalMateriaNomeKey((string) ($a['materia_nome'] ?? ''));
                $kb = $this->canonicalMateriaNomeKey((string) ($b['materia_nome'] ?? ''));
                return $ka <=> $kb;
            });

            // Cada coluna usa o modo da própria peça (média na semanal, soma na bimestral).
            $arredGrupo = $this->normalizarArredondamentoGrupo($g['arredondamento'] ?? 'todos');
            $roundModeGrupo = $this->normalizeRoundMode((string) ($regra['round_mode'] ?? 'half'));
            $paiNotas = $this->agregarNotasLinhasFilhosGrupo(
                $filhosLinhas,
                $g['mode'],
                $modoPorCodigo,
                $modoPorPeca,
                $roundModeGrupo,
                $arredGrupo
            );
            foreach ($notasHerdadasMae as $codHerdado => $valHerdado) {
                if (!is_numeric($valHerdado)) {
                    continue;
                }
                $codHerdado = (string) $codHerdado;
                if (!isset($paiNotas[$codHerdado]) || !is_numeric($paiNotas[$codHerdado])) {
                    $paiNotas[$codHerdado] = $valHerdado;
                }
            }
            if ($paiNotasBoletim !== null) {
                foreach ($paiNotasBoletim as $cod => $val) {
                    if (!is_numeric($val)) {
                        continue;
                    }
                    $cod = (string) $cod;
                    if (!isset($paiNotas[$cod]) || !is_numeric($paiNotas[$cod])) {
                        $paiNotas[$cod] = $val;
                    }
                }
            }
            // Média Bim e as colunas seguintes saem da fórmula, com as notas já juntadas da área.
            $paiNotas = $this->aplicarFormulasCalculadasNaLinhaGrupo($paiNotas, $regra, $arredGrupo);
            $pai = [
                'materia_id' => 0,
                'materia_nome' => $g['label'],
                'notas' => $paiNotas,
                'eh_grupo_pai' => 1,
                'eh_grupo_filho' => 0,
                'grupo_key' => $g['key'],
                'grupo_filhos_qtd' => count($filhosLinhas),
            ];
            $linhasGrupo = [$pai];
            if (empty($g['ocultar_filhas'])) {
                $linhasGrupo = array_merge($linhasGrupo, $filhosLinhas);
            }
            $blocos[] = [
                'sort' => $g['label_key'] !== '' ? $g['label_key'] : 'grupo',
                'linhas' => $linhasGrupo,
            ];
        }

        usort($blocos, static function (array $a, array $b): int {
            return strcmp((string) ($a['sort'] ?? ''), (string) ($b['sort'] ?? ''));
        });

        $linhasNovas = [];
        foreach ($blocos as $bloco) {
            foreach ((array) ($bloco['linhas'] ?? []) as $lin) {
                if (is_array($lin)) {
                    $linhasNovas[] = $lin;
                }
            }
        }
        $matriz['linhas'] = $linhasNovas;
        $simulacao['matriz_materias'] = $matriz;

        return $simulacao;
    }

    /**
     * Tira do quadro já gravado as filhas desmarcadas neste evento
     * (ex.: Literatura fora de Língua Portuguesa só neste bimestre).
     *
     * @param list<array<string,mixed>> $linhas
     * @param array<string,mixed> $regra
     * @return list<array<string,mixed>>
     */
    public function filtrarLinhasSemFilhasExcluidasDoEvento(array $linhas, array $regra): array
    {
        if ($linhas === []) {
            return [];
        }
        $excluidos = $this->materiaIdsExcluidosGroupLineDosComponentes((array) ($regra['componentes'] ?? []));
        if ($excluidos === []) {
            return $linhas;
        }
        $out = [];
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $mid = (int) ($linha['materia_id'] ?? 0);
            if ($mid > 0 && isset($excluidos[$mid])) {
                continue;
            }
            $out[] = $linha;
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $componentes
     * @return array<int, true>
     */
    private function materiaIdsExcluidosGroupLineDosComponentes(array $componentes): array
    {
        $grupos = [];
        foreach ($componentes as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $grp = $this->parseGroupLineConfigFromComponente($comp);
            if ($grp === null) {
                continue;
            }
            $gk = (string) ($grp['key'] ?? '');
            if ($gk === '' || isset($grupos[$gk])) {
                continue;
            }
            $filhosIds = [];
            foreach ((array) ($grp['materias_ids'] ?? []) as $midF) {
                $midF = (int) $midF;
                if ($midF > 0) {
                    $filhosIds[$midF] = $midF;
                }
            }
            if (count($filhosIds) < 2) {
                continue;
            }
            $grupos[$gk] = [
                'filhos_ids' => $filhosIds,
                'agrupamento_id' => (int) ($grp['agrupamento_id'] ?? 0),
            ];
        }

        return $this->materiasExcluidasDoGroupLineNoEvento($grupos);
    }

    /**
     * Irmãs de cadastro (área / pai→filhos) que não estão em group_line.materias_ids
     * neste evento — devem sumir do Demonstrativo.
     *
     * @param array<string, array{filhos_ids:array<int,int>,agrupamento_id?:int}> $grupos
     * @return array<int, true>
     */
    private function materiasExcluidasDoGroupLineNoEvento(array $grupos): array
    {
        $excluidos = [];
        $comp = $this->componentesCurriculares();
        $mapaPai = [];
        $mapaFilhos = [];
        if ($comp !== null) {
            try {
                $mapaPai = $comp->mapaPaiPorFilho();
                $mapaFilhos = $comp->mapaFilhosPorPai();
            } catch (Throwable $e) {
                $mapaPai = [];
                $mapaFilhos = [];
            }
        }
        $catalogoAreas = null;

        foreach ($grupos as $g) {
            if (!is_array($g)) {
                continue;
            }
            $incluidos = [];
            foreach ((array) ($g['filhos_ids'] ?? []) as $midF) {
                $midF = (int) $midF;
                if ($midF > 0) {
                    $incluidos[$midF] = true;
                }
            }
            if (count($incluidos) < 2) {
                continue;
            }

            $familia = [];
            $agId = (int) ($g['agrupamento_id'] ?? 0);
            if ($agId > 0) {
                $cad = $this->carregarAgrupamentoCadastro($agId);
                foreach ((array) ($cad['materias_ids'] ?? []) as $midCad) {
                    $midCad = (int) $midCad;
                    if ($midCad > 0) {
                        $familia[$midCad] = true;
                    }
                }
            }
            // Após editar checkboxes o wizard zera agrupamento_id — recupera a área pelo catálogo.
            if ($familia === []) {
                if ($catalogoAreas === null) {
                    $catalogoAreas = $this->listarAgrupamentosComponentesCatalogo();
                }
                foreach ((array) $catalogoAreas as $area) {
                    if (!is_array($area)) {
                        continue;
                    }
                    $idsArea = [];
                    foreach ((array) ($area['materias_ids'] ?? []) as $midA) {
                        $midA = (int) $midA;
                        if ($midA > 0) {
                            $idsArea[$midA] = true;
                        }
                    }
                    if (count($idsArea) < 2) {
                        continue;
                    }
                    $cobreTodos = true;
                    foreach (array_keys($incluidos) as $midInc) {
                        if (!isset($idsArea[$midInc])) {
                            $cobreTodos = false;
                            break;
                        }
                    }
                    if ($cobreTodos) {
                        $familia = $idsArea;
                        break;
                    }
                }
            }
            // Fallback: só irmãos do mesmo pai curricular (sem ampliar se já há área).
            if ($familia === []) {
                foreach (array_keys($incluidos) as $midInc) {
                    $paiId = (int) ($mapaPai[$midInc] ?? 0);
                    if ($paiId <= 0) {
                        continue;
                    }
                    foreach ((array) ($mapaFilhos[$paiId] ?? []) as $filhoRow) {
                        $fid = (int) (is_array($filhoRow) ? ($filhoRow['id'] ?? 0) : $filhoRow);
                        if ($fid > 0) {
                            $familia[$fid] = true;
                        }
                    }
                }
            }
            foreach (array_keys($familia) as $midFam) {
                if (!isset($incluidos[$midFam])) {
                    $excluidos[$midFam] = true;
                }
            }
        }

        return $excluidos;
    }

    /**
     * @param list<array<string,mixed>> $linhasBoletim
     * @return array<string,mixed>|null
     */
    private function notasLinhaGrupoDoBoletim(array $linhasBoletim, string $label, string $labelKey): ?array
    {
        foreach ($linhasBoletim as $lin) {
            if (!is_array($lin)) {
                continue;
            }
            $nome = trim((string) ($lin['materia_nome'] ?? ''));
            $nk = $this->canonicalMateriaNomeKey($nome);
            $matchNome = ($labelKey !== '' && $nk === $labelKey)
                || ($label !== '' && strcasecmp($nome, $label) === 0);
            if (!$matchNome) {
                continue;
            }
            $notas = $lin['notas'] ?? null;

            return is_array($notas) ? $notas : [];
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $filhos
     * @param array<string,string> $modoPorCodigo modo da peça em cada coluna (media|soma)
     * @param array<string,string> $modoPorPeca modo explícito da peça (semanal, jornada…)
     * @return array<string,mixed>
     */
    private function agregarNotasLinhasFilhosGrupo(
        array $filhos,
        string $mode,
        array $modoPorCodigo = [],
        array $modoPorPeca = [],
        string $roundMode = 'half',
        string $arredondamento = 'todos'
    ): array
    {
        $mode = $mode === 'soma' ? 'soma' : 'media';
        $porCod = [];
        foreach ($filhos as $lin) {
            $notas = is_array($lin['notas'] ?? null) ? $lin['notas'] : [];
            foreach ($notas as $cod => $val) {
                $cod = (string) $cod;
                if ($cod === '' || !is_numeric($val)) {
                    continue;
                }
                if (!isset($porCod[$cod])) {
                    $porCod[$cod] = [];
                }
                $porCod[$cod][] = (float) $val;
            }
        }
        $out = [];
        foreach ($porCod as $cod => $vals) {
            if ($vals === []) {
                continue;
            }
            $ehNq = str_ends_with($cod, '__n') || str_ends_with($cod, '__q');
            $ehFaltas = stripos($cod, 'falta') !== false;
            $ehJornada = stripos($cod, 'jornada') !== false;
            $pecaCol = $this->pecaGrupoDoCodigoColuna((string) $cod);
            if ($pecaCol !== '' && isset($modoPorPeca[$pecaCol])) {
                $modoCol = $modoPorPeca[$pecaCol];
            } else {
                $modoCol = strtolower((string) ($modoPorCodigo[$cod] ?? $mode));
            }
            if ($modoCol !== 'soma') {
                $modoCol = 'media';
            }
            if ($ehNq || $ehFaltas) {
                $soma = array_sum($vals);
                $out[$cod] = (int) round($soma);
                continue;
            }
            if ($ehJornada) {
                $vals = array_values(array_filter($vals, static function (float $v): bool {
                    return $v > 0.0;
                }));
                if ($vals === []) {
                    continue;
                }
            }
            $bruto = $modoCol === 'soma'
                ? array_sum($vals)
                : array_sum($vals) / count($vals);
            $out[$cod] = $this->aplicarArredondamentoMaeGrupo($bruto, $roundMode, $arredondamento);
        }

        return $out;
    }

    /**
     * Peça da linha única a partir do código da coluna.
     * Semanas e média semanal são a mesma peça.
     */
    private function pecaGrupoDoCodigoColuna(string $codigo): string
    {
        $cod = strtolower(trim($codigo));
        if ($cod === '' || str_ends_with($cod, '__n') || str_ends_with($cod, '__q')) {
            return '';
        }
        if ($cod === 'media_sem' || $cod === 'semanal' || preg_match('/^s[1-8]$/', $cod) === 1) {
            return 'semanal';
        }
        if (in_array($cod, ['media_bim', 'media_final', 'media_parcial', 'media_etapa', 'faltas', 'resultado'], true)) {
            return '';
        }
        if (preg_match('/^media_[ms]\d+$/', $cod) === 1) {
            return '';
        }
        $canon = match ($cod) {
            'bimestral', 'prova_bim' => 'bimestral',
            'jornada' => 'jornada',
            'enac' => 'enac',
            'trab', 'trabalho' => 'trabalho',
            'part', 'participacao' => 'participacao',
            'rec', 'recuperacao' => 'recuperacao',
            default => '',
        };
        if ($canon !== '') {
            return $canon;
        }
        if (str_contains($cod, 'jornada')) {
            return 'jornada';
        }
        if (str_contains($cod, 'enac')) {
            return 'enac';
        }
        if (str_contains($cod, 'recup')) {
            return 'recuperacao';
        }
        if (preg_match('/^[a-z][a-z0-9_]{0,40}$/', $cod) === 1) {
            return $cod;
        }

        return '';
    }

    /**
     * Colunas calculadas da mãe (Média Bim, média com ENAC, média final) usam a fórmula
     * sobre as peças já juntadas. Não somam o resultado que cada filha já calculou.
     *
     * @param array<string,mixed> $notas
     * @param array<string,mixed> $regra
     * @return array<string,mixed>
     */
    private function aplicarFormulasCalculadasNaLinhaGrupo(array $notas, array $regra, string $arredondamento = 'todos'): array
    {
        $roundMode = $this->normalizeRoundMode((string) ($regra['round_mode'] ?? 'half'));
        $exprs = [];
        foreach ((array) ($regra['componentes'] ?? []) as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            if (strtolower(trim((string) ($comp['source_type'] ?? ''))) !== 'calculado') {
                continue;
            }
            $cod = strtolower(trim((string) ($comp['codigo'] ?? '')));
            if ($cod === '' || $cod === 'media_sem') {
                continue;
            }
            $expr = $this->parseExpressaoColunaCalculada($comp);
            if ($expr === '') {
                continue;
            }
            $exprs[] = ['codigo' => $cod, 'expr' => $expr, 'comp' => $comp];
        }
        if ($exprs === []) {
            return $notas;
        }
        $passos = count($exprs);
        for ($passo = 0; $passo < $passos; $passo++) {
            foreach ($exprs as $item) {
                $refs = $this->codigosReferenciadosNaExpressao($item['expr'], array_keys($notas));
                $vars = [];
                foreach ($refs as $ref) {
                    $vars[(string) $ref] = $this->valorNotaGrupoPorCodigo($notas, (string) $ref);
                }
                $resultado = $this->avaliarFormula($item['expr'], $vars);
                if (empty($resultado['ok']) || !isset($resultado['valor']) || !is_numeric($resultado['valor'])) {
                    continue;
                }
                $modoCol = $this->resolveRoundModeComponente($item['comp'], $roundMode);
                $arred = $this->aplicarArredondamentoMaeGrupo((float) $resultado['valor'], $modoCol, $arredondamento);
                if ($arred === null) {
                    continue;
                }
                $notas[$item['codigo']] = $arred;
            }
        }

        return $notas;
    }

    /**
     * A fórmula cita o código da peça (prova_bim) e a coluna pode estar com o outro nome (bimestral).
     *
     * @param array<string,mixed> $notas
     */
    private function valorNotaGrupoPorCodigo(array $notas, string $codigo): float
    {
        $candidatos = $this->aliasesCodigoColuna($codigo);
        foreach ($candidatos as $cand) {
            foreach ($notas as $chave => $valor) {
                if (strcasecmp((string) $chave, (string) $cand) === 0 && is_numeric($valor)) {
                    return (float) $valor;
                }
            }
        }

        return 0.0;
    }

    /**
     * Agrupa linhas por componente (ex.: Linguagem) com estratégia própria por bloco.
     *
     * @param array<int, array<string, mixed>> $componentesRegra
     * @param array<string, array<int, float>> $matrizPorCodigo
     * @param array<int, string> $materiaNomesPorId
     * @return array{
     *   matriz_por_codigo: array<string, array<int, float>>,
     *   materia_nomes_por_id: array<int, string>,
     *   materias_agrupadas: array<int, bool>,
     *   grupos_virtual_mids: array<int, bool>,
     *   agrupamento_por_virtual_mid: array<int, int>
     * }
     */
    private function aplicarAgrupamentoLinhasPorComponente(
        array $componentesRegra,
        array $matrizPorCodigo,
        array $materiaNomesPorId,
        array $matrizPercentStatsPorCodigo = [],
        string $exibirEm = 'boletim',
        bool $forcarAgrupamento = false,
        string $roundMode = 'half'
    ): array
    {
        $roundMode = $this->normalizeRoundMode($roundMode);
        // Estilo do arredondamento vem do evento; quem recebe (mãe/filhas) vem de group_line.arredondamento.
        $roundModeGrupo = $roundMode;
        $groupMetaByKey = [];
        $groupMidByKey = [];
        $materiasAgrupadas = [];
        $groupKeysAtivos = [];
        $componentGroupByCode = [];
        $materiasComValorForaDeGrupo = [];
        $materiasComFormulaPropria = [];
        $nextVirtualMid = -1;
        $calcCfgByCode = [];
        $codigosFaltas = [];
        foreach ($componentesRegra as $compF) {
            $codF = trim((string) ($compF['codigo'] ?? ''));
            if ($codF !== '' && $this->componenteContaComoFalta($compF)) {
                $codigosFaltas[$codF] = true;
            }
        }

        foreach ($componentesRegra as $comp) {
            $codigo = trim((string) ($comp['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $grp = $this->parseGroupLineConfigFromComponente($comp);
            if ($grp === null) {
                continue;
            }
            $exibirEmNorm = strtolower(trim($exibirEm)) === 'notas' ? 'notas' : 'boletim';
            if (
                !$forcarAgrupamento
                && $exibirEmNorm === 'notas'
                && (($grp['aplicar_em'] ?? 'ambos') === 'boletim')
            ) {
                continue;
            }
            $gk = $grp['key'];
            if (!isset($groupMidByKey[$gk])) {
                $groupMidByKey[$gk] = $nextVirtualMid;
                $nextVirtualMid--;
                $modeMeta = strtolower(trim((string) ($grp['modo_padrao'] ?? '')));
                if ($modeMeta !== 'media' && $modeMeta !== 'soma') {
                    $modeMeta = strtolower((string) ($grp['mode'] ?? 'media')) === 'soma' ? 'soma' : 'media';
                }
                $groupMetaByKey[$gk] = [
                    'label' => $grp['label'],
                    'materias_ids' => $grp['materias_ids'],
                    'mode' => $modeMeta,
                    'divisor' => $grp['divisor'],
                    'agrupamento_id' => (int) ($grp['agrupamento_id'] ?? 0),
                    'arredondamento' => $this->normalizarArredondamentoGrupo($grp['arredondamento'] ?? 'todos'),
                ];
            }
            // Mantém o grupo ativo sempre que foi configurado, mesmo com notas faltantes.
            $groupKeysAtivos[(string) $gk] = true;
            foreach ($grp['materias_ids'] as $midGrp) {
                if ($midGrp > 0) {
                    $materiasAgrupadas[$midGrp] = true;
                }
            }
            $grp['virtual_mid'] = $groupMidByKey[$gk];
            $componentGroupByCode[$codigo] = $grp;
        }

        foreach ($componentesRegra as $comp) {
            $codigo = trim((string) ($comp['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $sourceType = strtolower(trim((string) ($comp['source_type'] ?? 'provas')));
            if ($sourceType !== 'calculado') {
                continue;
            }
            $expr = $this->parseExpressaoColunaCalculada($comp);
            if ($expr === '') {
                continue;
            }
            $calcCfgByCode[$codigo] = [
                'expr' => $expr,
            ];
            foreach (array_keys($this->parseFormulaMateriasCalculadoFromComponente($comp)) as $midFormula) {
                $midFormula = (int) $midFormula;
                if ($midFormula > 0) {
                    $materiasComFormulaPropria[$midFormula] = true;
                }
            }
        }

        if ($codigosFaltas !== []) {
            $this->consolidarFaltasDuplicadasPorNome($matrizPorCodigo, $materiaNomesPorId, $codigosFaltas, true);
        }

        if ($componentGroupByCode === []) {
            return [
                'matriz_por_codigo' => $matrizPorCodigo,
                'materia_nomes_por_id' => $materiaNomesPorId,
                'materias_agrupadas' => [],
                'grupos_virtual_mids' => [],
                'agrupamento_por_virtual_mid' => [],
                'filhas_por_virtual_mid' => [],
            ];
        }

        foreach ($groupMetaByKey as $gk => $meta) {
            $vmid = (int) ($groupMidByKey[$gk] ?? 0);
            if ($vmid >= 0) {
                continue;
            }
            $materiaNomesPorId[$vmid] = (string) ($meta['label'] ?? $gk);
            $groupKeysAtivos[(string) $gk] = true;
        }

        $linhasPropriasForaDoGrupo = [];
        foreach ($matrizPorCodigo as $codigoScan => $mapScan) {
            if (!is_array($mapScan)) {
                continue;
            }
            $grupoScan = $componentGroupByCode[$codigoScan] ?? null;
            $idsGrupoScan = is_array($grupoScan)
                ? array_fill_keys(array_map('intval', (array) ($grupoScan['materias_ids'] ?? [])), true)
                : [];
            $nkGrupoScan = is_array($grupoScan)
                ? $this->canonicalMateriaNomeKey((string) ($grupoScan['label'] ?? $grupoScan['key'] ?? ''))
                : '';
            foreach ($mapScan as $midScan => $valorScan) {
                $midScan = (int) $midScan;
                if ($midScan <= 0 || !is_numeric($valorScan)) {
                    continue;
                }
                if ($grupoScan !== null && !isset($idsGrupoScan[$midScan])) {
                    $nkScan = $this->canonicalMateriaNomeKey((string) ($materiaNomesPorId[$midScan] ?? ''));
                    $duplicataRotuloGrupo = ($nkGrupoScan !== '' && $nkScan === $nkGrupoScan);
                    $materiasComValorForaDeGrupo[$midScan] = true;
                    if (!$duplicataRotuloGrupo && !isset($codigosFaltas[(string) $codigoScan])) {
                        $linhasPropriasForaDoGrupo[$midScan] = true;
                    }
                }
                if (isset($materiasComFormulaPropria[$midScan])) {
                    $materiasComValorForaDeGrupo[$midScan] = true;
                }
            }
        }

        $notasManuaisVirtuais = [];
        foreach ($matrizPorCodigo as $codVirtual => $mapVirtual) {
            if (!is_array($mapVirtual)) {
                continue;
            }
            foreach ($mapVirtual as $midVirtual => $valVirtual) {
                if ((int) $midVirtual < 0 && (is_numeric($valVirtual) || $valVirtual === null)) {
                    $notasManuaisVirtuais[(string) $codVirtual][(int) $midVirtual] = $valVirtual;
                }
            }
        }

        $faltasFontePorCodigo = [];
        foreach ($matrizPorCodigo as $codFonte => $mapFonte) {
            if (!isset($codigosFaltas[$codFonte]) || !is_array($mapFonte)) {
                continue;
            }
            $faltasFontePorCodigo[(string) $codFonte] = $mapFonte;
        }

        foreach ($matrizPorCodigo as $codigo => $map) {
            if (!is_array($map)) {
                continue;
            }
            $mapOriginal = $map;

            // Um mesmo grupo pode ser reutilizado por vários componentes com escopos
            // diferentes. Ex.: Prova Semanal agrupa Redação em Língua Portuguesa,
            // enquanto a Avaliação Bimestral mantém Redação como linha própria.
            // Nesse caso a matéria não pode ser ocultada globalmente só porque aparece
            // no group_line de outro componente.
            $grupoDoComponente = $componentGroupByCode[$codigo] ?? null;
            $idsGrupoDoComponente = is_array($grupoDoComponente)
                ? array_fill_keys(array_map('intval', (array) ($grupoDoComponente['materias_ids'] ?? [])), true)
                : [];
            $ehFaltasCol = isset($codigosFaltas[$codigo]);

            foreach (array_keys($materiasAgrupadas) as $midRem) {
                $midRem = (int) $midRem;
                $temValorIndependenteNesteComponente = isset($materiasComValorForaDeGrupo[$midRem])
                    && isset($mapOriginal[$midRem])
                    && is_numeric($mapOriginal[$midRem])
                    && (
                        isset($materiasComFormulaPropria[$midRem])
                        || ($grupoDoComponente !== null && !isset($idsGrupoDoComponente[$midRem]))
                    );
                if ($ehFaltasCol) {
                    // Falta da linha visível (ex.: Redação) permanece nela; só some
                    // a falta das filhas ocultas, que entra na soma do grupo.
                    if (!$temValorIndependenteNesteComponente && !isset($materiasComValorForaDeGrupo[$midRem])) {
                        unset($map[$midRem]);
                    }
                } elseif (!$temValorIndependenteNesteComponente) {
                    unset($map[$midRem]);
                }
            }

            if (isset($componentGroupByCode[$codigo])) {
                $cfg = $componentGroupByCode[$codigo];
                $pecaCfg = $this->pecaGrupoDoCodigoColuna($codigo);
                $modosCfg = is_array($cfg['modos'] ?? null) ? $cfg['modos'] : [];
                if ($pecaCfg !== '' && isset($modosCfg[$pecaCfg])) {
                    $cfg['mode'] = strtolower((string) $modosCfg[$pecaCfg]) === 'soma' ? 'soma' : 'media';
                }
                $vals = [];
                $sumAcertos = 0;
                $sumQuestoes = 0;
                $statsMap = isset($matrizPercentStatsPorCodigo[$codigo]) && is_array($matrizPercentStatsPorCodigo[$codigo])
                    ? $matrizPercentStatsPorCodigo[$codigo]
                    : [];
                foreach ($cfg['materias_ids'] as $midSel) {
                    $midSel = (int) $midSel;
                    if ($ehFaltasCol && $midSel > 0 && isset($linhasPropriasForaDoGrupo[$midSel])) {
                        continue;
                    }
                    if (isset($matrizPorCodigo[$codigo][$midSel]) && is_numeric($matrizPorCodigo[$codigo][$midSel])) {
                        $vals[] = (float) $matrizPorCodigo[$codigo][$midSel];
                    }
                    if (isset($statsMap[$midSel]) && is_array($statsMap[$midSel])) {
                        $sumAcertos += (int) ($statsMap[$midSel]['acertos'] ?? 0);
                        $sumQuestoes += (int) ($statsMap[$midSel]['total'] ?? 0);
                    }
                }
                $agr = null;
                $arredGrupo = $this->normalizarArredondamentoGrupo(
                    $cfg['arredondamento'] ?? ($groupMetaByKey[(string) ($cfg['key'] ?? '')]['arredondamento'] ?? 'todos')
                );
                if ($ehFaltasCol) {
                    $agr = $this->agruparValoresGrupoLinha($vals, 'soma', 0.0);
                } elseif (!empty($cfg['usar_percentual'])
                    && strtolower((string) ($cfg['mode'] ?? 'media')) === 'media'
                    && $sumQuestoes > 0
                    && $this->componenteAgregaGrupoPorNq($codigo, $cfg)
                ) {
                    // Semanas N/Q: aproveitamento conjunto (soma N ÷ soma Q), não média das notas 0–10.
                    $agr = ($sumAcertos / $sumQuestoes) * 10.0;
                } else {
                    $modoCfg = strtolower(trim((string) ($cfg['mode'] ?? 'media')));
                    if ($modoCfg !== 'soma') {
                        $modoCfg = 'media';
                    }
                    // Média do grupo = média aritmética das matérias (Leitura, Literatura, Português…).
                    // Divisor fixo do cadastro só vale para modo soma (ex.: dividir total por N).
                    $divisorCfg = ($modoCfg === 'media')
                        ? 0.0
                        : (float) ($cfg['divisor'] ?? 0);
                    if (($cfg['source_type'] ?? '') === 'jornadas' && $modoCfg === 'media') {
                        // Ignora zeros no agrupamento de Jornadas para não diluir
                        // a nota da área quando há matérias do grupo sem jornada aplicável.
                        $vals = array_values(array_filter($vals, static function ($v) {
                            return is_numeric($v) && (float) $v > 0.0;
                        }));
                    }
                    $agr = $this->agruparValoresGrupoLinha($vals, $modoCfg, $divisorCfg);
                }
                if ($agr !== null) {
                    $agr = $this->aplicarArredondamentoMaeGrupo((float) $agr, $roundModeGrupo, $arredGrupo);
                }
                $virtualMidCfg = (int) $cfg['virtual_mid'];
                // Se já existe um valor no id sintético do grupo ANTES de recalcular (só
                // pode vir de sobrescrita manual salva direto nessa linha agrupada — dado
                // bruto de prova/jornada nunca usa id negativo), preserva a sobrescrita em
                // vez de recalcular por cima e perder a edição manual da linha agrupada.
                if (isset($mapOriginal[$virtualMidCfg]) && is_numeric($mapOriginal[$virtualMidCfg])) {
                    $map[$virtualMidCfg] = $mapOriginal[$virtualMidCfg];
                    $groupKeysAtivos[(string) ($cfg['key'] ?? '')] = true;
                } elseif ($agr !== null) {
                    $map[$virtualMidCfg] = (float) $agr;
                    $groupKeysAtivos[(string) ($cfg['key'] ?? '')] = true;
                }
            } else {
                foreach ($groupMetaByKey as $gk => $meta) {
                    $vmid = (int) ($groupMidByKey[$gk] ?? 0);
                    if ($vmid >= 0) {
                        continue;
                    }
                    // Mesma regra do ramo acima: preserva sobrescrita manual salva
                    // direto nesta coluna pra essa linha agrupada, sem recalcular por cima.
                    if (isset($mapOriginal[$vmid]) && is_numeric($mapOriginal[$vmid])) {
                        $map[$vmid] = $mapOriginal[$vmid];
                        $groupKeysAtivos[(string) $gk] = true;
                        continue;
                    }
                    if (isset($calcCfgByCode[$codigo])) {
                        $expr = (string) ($calcCfgByCode[$codigo]['expr'] ?? '');
                        if ($expr !== '') {
                            $refsExpr = $this->codigosReferenciadosNaExpressao($expr, array_keys($matrizPorCodigo));
                            if ($refsExpr !== []) {
                                $notasVirtuais = [];
                                foreach ($matrizPorCodigo as $codExistente => $mapExistente) {
                                    if (!is_array($mapExistente)) {
                                        continue;
                                    }
                                    if (isset($mapExistente[$vmid]) && is_numeric($mapExistente[$vmid])) {
                                        $notasVirtuais[(string) $codExistente] = (float) $mapExistente[$vmid];
                                    }
                                }
                                $vars = [];
                                foreach ($refsExpr as $refToken) {
                                    $vars[(string) $refToken] = $this->valorNotaGrupoPorCodigo($notasVirtuais, (string) $refToken);
                                }
                                $rFormula = $this->avaliarFormula($expr, $vars);
                                if (!empty($rFormula['ok']) && isset($rFormula['valor']) && is_numeric($rFormula['valor'])) {
                                    $arredFormula = $this->normalizarArredondamentoGrupo($meta['arredondamento'] ?? 'todos');
                                    $map[$vmid] = $this->aplicarArredondamentoMaeGrupo(
                                        (float) $rFormula['valor'],
                                        $roundModeGrupo,
                                        $arredFormula
                                    );
                                    $groupKeysAtivos[(string) $gk] = true;
                                    continue;
                                }
                            }
                        }
                    }
                    $vals = [];
                    foreach ((array) ($meta['materias_ids'] ?? []) as $midSel) {
                        $midSel = (int) $midSel;
                        if ($midSel <= 0 || ($ehFaltasCol && isset($linhasPropriasForaDoGrupo[$midSel]))) {
                            continue;
                        }
                        if (isset($mapOriginal[$midSel]) && is_numeric($mapOriginal[$midSel])) {
                            $vals[] = (float) $mapOriginal[$midSel];
                        }
                    }
                    $modoGrupo = $ehFaltasCol ? 'soma' : (string) ($meta['mode'] ?? 'media');
                    $agr = $this->agruparValoresGrupoLinha(
                        $vals,
                        $modoGrupo,
                        ($ehFaltasCol || strtolower((string) ($meta['mode'] ?? 'media')) === 'media')
                            ? 0.0
                            : (float) ($meta['divisor'] ?? 0)
                    );
                    if ($agr !== null) {
                        $arredMeta = $this->normalizarArredondamentoGrupo($meta['arredondamento'] ?? 'todos');
                        $map[$vmid] = $ehFaltasCol
                            ? $agr
                            : $this->aplicarArredondamentoMaeGrupo((float) $agr, $roundModeGrupo, $arredMeta);
                        $groupKeysAtivos[(string) $gk] = true;
                    }
                }
            }

            $matrizPorCodigo[$codigo] = $map;
        }

        $this->aplicarFormulasNasLinhasVirtuais($matrizPorCodigo, $componentesRegra, $roundModeGrupo, $notasManuaisVirtuais);
        foreach ($notasManuaisVirtuais as $codVirtual => $porMidVirtual) {
            if (!isset($matrizPorCodigo[$codVirtual]) || !is_array($matrizPorCodigo[$codVirtual])) {
                $matrizPorCodigo[$codVirtual] = [];
            }
            foreach ($porMidVirtual as $midVirtual => $valVirtual) {
                $matrizPorCodigo[$codVirtual][(int) $midVirtual] = $valVirtual;
            }
        }

        $this->recomputarSomaFaltasDosGrupos(
            $matrizPorCodigo,
            $faltasFontePorCodigo,
            $groupMetaByKey,
            $groupMidByKey,
            $materiaNomesPorId,
            $linhasPropriasForaDoGrupo,
            $codigosFaltas
        );
        if ($codigosFaltas !== []) {
            $this->consolidarFaltasDuplicadasPorNome($matrizPorCodigo, $materiaNomesPorId, $codigosFaltas, false);
        }

        $materiasAgrupadasAtivas = [];
        foreach ($groupMetaByKey as $gk => $meta) {
            if (empty($groupKeysAtivos[(string) $gk])) {
                continue;
            }
            foreach ((array) ($meta['materias_ids'] ?? []) as $midSel) {
                $midSel = (int) $midSel;
                if ($midSel > 0 && !isset($linhasPropriasForaDoGrupo[$midSel])) {
                    $materiasAgrupadasAtivas[$midSel] = true;
                }
            }
        }
        $nomesRotuloGrupo = [];
        foreach ($groupMetaByKey as $gk => $meta) {
            $nkRotulo = $this->canonicalMateriaNomeKey((string) ($meta['label'] ?? $gk));
            if ($nkRotulo !== '') {
                $nomesRotuloGrupo[$nkRotulo] = true;
            }
        }
        foreach ($materiaNomesPorId as $midNome => $nomeMat) {
            $midNome = (int) $midNome;
            if ($midNome <= 0 || isset($materiasAgrupadasAtivas[$midNome])) {
                continue;
            }
            $nomeKeyDup = $this->canonicalMateriaNomeKey((string) $nomeMat);
            if ($nomeKeyDup === '' || !isset($nomesRotuloGrupo[$nomeKeyDup])) {
                continue;
            }
            // Rótulo do grupo (ex.: matéria "Língua Portuguesa") cede lugar à linha virtual.
            $materiasAgrupadasAtivas[$midNome] = true;
        }

        $gruposVirtualMids = [];
        $agrupamentoPorVirtualMid = [];
        $filhasPorVirtualMid = [];
        foreach ($groupMetaByKey as $gk => $meta) {
            if (empty($groupKeysAtivos[(string) $gk])) {
                continue;
            }
            $vmid = (int) ($groupMidByKey[$gk] ?? 0);
            if ($vmid < 0) {
                $gruposVirtualMids[$vmid] = true;
                $aid = (int) ($meta['agrupamento_id'] ?? 0);
                if ($aid > 0) {
                    $agrupamentoPorVirtualMid[$vmid] = $aid;
                }
                $filhas = [];
                foreach ((array) ($meta['materias_ids'] ?? []) as $midFilha) {
                    $midFilha = (int) $midFilha;
                    if ($midFilha > 0) {
                        $filhas[$midFilha] = $midFilha;
                    }
                }
                $filhasPorVirtualMid[$vmid] = array_values($filhas);
            }
        }

        return [
            'matriz_por_codigo' => $matrizPorCodigo,
            'materia_nomes_por_id' => $materiaNomesPorId,
            // Quando há agrupamento configurado, oculta as matérias originais
            // daquele grupo e mantém apenas a linha consolidada.
            'materias_agrupadas' => $materiasAgrupadasAtivas,
            'grupos_virtual_mids' => $gruposVirtualMids,
            'agrupamento_por_virtual_mid' => $agrupamentoPorVirtualMid,
            'filhas_por_virtual_mid' => $filhasPorVirtualMid,
        ];
    }

    /**
     * Faltas costumam estar num cadastro/ID diferente da nota (grade horária vs
     * grupo do boletim, Redação duplicada, Língua Portuguesa vs Português).
     * Junta valores de mesmo nome canônico na linha que o quadro já usa.
     *
     * @param array<string, array<int, float>> $matrizPorCodigo
     * @param array<int, string> $materiaNomesPorId
     * @param array<string, bool> $codigosFaltas
     */
    private function consolidarFaltasDuplicadasPorNome(
        array &$matrizPorCodigo,
        array $materiaNomesPorId,
        array $codigosFaltas,
        bool $somar = true
    ): void {
        if ($codigosFaltas === []) {
            return;
        }

        foreach ($matrizPorCodigo as $codigo => $map) {
            if (!isset($codigosFaltas[(string) $codigo]) || !is_array($map)) {
                continue;
            }
            $porNome = [];
            foreach ($map as $mid => $valor) {
                $mid = (int) $mid;
                if (!is_numeric($valor)) {
                    continue;
                }
                $nomeKey = $this->canonicalMateriaNomeKey((string) ($materiaNomesPorId[$mid] ?? ''));
                if ($nomeKey === '') {
                    continue;
                }
                $porNome[$nomeKey][$mid] = (float) $valor;
            }
            foreach ($porNome as $nomeKey => $valsPorMid) {
                if (count($valsPorMid) < 2) {
                    continue;
                }
                $destino = $this->midDestinoFaltasMesmoNome(
                    array_keys($valsPorMid),
                    $matrizPorCodigo,
                    $codigosFaltas,
                    !$somar
                );
                if ($destino === 0) {
                    continue;
                }
                $valorDestino = null;
                foreach ($valsPorMid as $midOrigem => $valorOrigem) {
                    $midOrigem = (int) $midOrigem;
                    if ($somar) {
                        $valorDestino = ($valorDestino ?? 0.0) + (float) $valorOrigem;
                    } elseif ($midOrigem === $destino) {
                        $valorDestino = (float) $valorOrigem;
                    } elseif ($valorDestino === null) {
                        $valorDestino = (float) $valorOrigem;
                    }
                    if ($midOrigem !== $destino) {
                        unset($map[$midOrigem]);
                    }
                }
                if ($valorDestino !== null) {
                    $map[$destino] = $valorDestino;
                }
            }
            $matrizPorCodigo[(string) $codigo] = $map;
        }
    }

    /**
     * @param list<int> $mids
     * @param array<string, array<int, float>> $matrizPorCodigo
     * @param array<string, bool> $codigosFaltas
     */
    private function midDestinoFaltasMesmoNome(
        array $mids,
        array $matrizPorCodigo,
        array $codigosFaltas,
        bool $preferirSintetico = false
    ): int {
        $melhorMid = 0;
        $melhorScore = -1;
        foreach ($mids as $mid) {
            $mid = (int) $mid;
            $score = 0;
            if ($mid < 0) {
                $score += $preferirSintetico ? 50 : 5;
            }
            foreach ($matrizPorCodigo as $cod => $map) {
                if (!is_array($map)) {
                    continue;
                }
                if (!isset($map[$mid]) || !is_numeric($map[$mid])) {
                    continue;
                }
                if (isset($codigosFaltas[(string) $cod])) {
                    $score += 1;
                } else {
                    $score += 10;
                }
            }
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhorMid = $mid;
            }
        }

        return $melhorMid;
    }

    /**
     * @param array<string, array<int, float>> $matrizPorCodigo
     * @param array<string, bool> $codigosFaltas
     */
    private function midTemNotaForaDeFaltas(int $mid, array $matrizPorCodigo, array $codigosFaltas): bool
    {
        if ($mid === 0) {
            return false;
        }
        foreach ($matrizPorCodigo as $cod => $map) {
            if (!is_array($map) || isset($codigosFaltas[(string) $cod])) {
                continue;
            }
            if (isset($map[$mid]) && is_numeric($map[$mid])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Depois que filhas herdadas são ocultadas, sobe a falta delas para a linha
     * agrupada já visível (id negativo) se essa célula ainda estiver vazia.
     *
     * @param array<string, array<int, float>> $matrizPorCodigo
     * @param array<int, string> $materiaNomesPorId
     * @param array<int, bool> $materiasAgrupadas
     * @param list<array<string, mixed>> $componentesRegra
     */
    private function absorverFaltasDeMateriasOcultas(
        array &$matrizPorCodigo,
        array $materiaNomesPorId,
        array $materiasAgrupadas,
        array $componentesRegra
    ): void {
        if ($materiasAgrupadas === []) {
            return;
        }
        $codigosFaltas = [];
        $membrosPorRotulo = [];
        foreach ($componentesRegra as $comp) {
            $cod = trim((string) ($comp['codigo'] ?? ''));
            if ($cod !== '' && $this->componenteContaComoFalta($comp)) {
                $codigosFaltas[$cod] = true;
            }
            $grp = $this->parseGroupLineConfigFromComponente($comp);
            if ($grp === null) {
                continue;
            }
            $nkGrupo = $this->canonicalMateriaNomeKey((string) ($grp['label'] ?? $grp['key'] ?? ''));
            if ($nkGrupo === '') {
                continue;
            }
            foreach ((array) ($grp['materias_ids'] ?? []) as $midMembro) {
                $midMembro = (int) $midMembro;
                if ($midMembro > 0) {
                    $membrosPorRotulo[$nkGrupo][$midMembro] = true;
                }
            }
        }
        if ($codigosFaltas === []) {
            return;
        }
        $destinos = [];
        foreach ($materiaNomesPorId as $midNome => $nomeMat) {
            $midNome = (int) $midNome;
            if ($midNome >= 0) {
                continue;
            }
            $nk = $this->canonicalMateriaNomeKey((string) $nomeMat);
            if ($nk !== '') {
                $destinos[$nk] = $midNome;
            }
        }
        if ($destinos === []) {
            return;
        }
        foreach ($matrizPorCodigo as $codigo => $map) {
            if (!isset($codigosFaltas[(string) $codigo]) || !is_array($map)) {
                continue;
            }
            foreach ($destinos as $nkDest => $vmid) {
                $vmid = (int) $vmid;
                if (isset($map[$vmid]) && is_numeric($map[$vmid])) {
                    continue;
                }
                $idsMembros = $membrosPorRotulo[$nkDest] ?? [];
                $soma = 0.0;
                $tem = false;
                foreach ($map as $mid => $valor) {
                    $mid = (int) $mid;
                    if ($mid <= 0 || !isset($materiasAgrupadas[$mid]) || !is_numeric($valor)) {
                        continue;
                    }
                    $nkMid = $this->canonicalMateriaNomeKey((string) ($materiaNomesPorId[$mid] ?? ''));
                    $entra = isset($idsMembros[$mid]) || ($nkMid !== '' && $nkMid === $nkDest);
                    if (!$entra && $nkMid !== '') {
                        foreach (array_keys($idsMembros) as $midMembro) {
                            $nkMembro = $this->canonicalMateriaNomeKey((string) ($materiaNomesPorId[(int) $midMembro] ?? ''));
                            if ($nkMembro !== '' && $nkMembro === $nkMid) {
                                $entra = true;
                                break;
                            }
                        }
                    }
                    if (!$entra) {
                        continue;
                    }
                    $soma += (float) $valor;
                    $tem = true;
                    unset($map[$mid]);
                }
                if ($tem) {
                    $map[$vmid] = $soma;
                }
            }
            $matrizPorCodigo[(string) $codigo] = $map;
        }
    }

    /**
     * Recalcula a linha agrupada nas colunas de faltas: soma filhas (por id ou nome),
     * inclusive cadastros duplicados e a matéria com o rótulo do grupo.
     *
     * @param array<string, array<int, float>> $matrizPorCodigo
     * @param array<string, array<int, float>> $faltasFontePorCodigo
     * @param array<string, array<string, mixed>> $groupMetaByKey
     * @param array<string, int> $groupMidByKey
     * @param array<int, string> $materiaNomesPorId
     * @param array<int, bool> $linhasPropriasForaDoGrupo
     * @param array<string, bool> $codigosFaltas
     */
    private function recomputarSomaFaltasDosGrupos(
        array &$matrizPorCodigo,
        array $faltasFontePorCodigo,
        array $groupMetaByKey,
        array $groupMidByKey,
        array $materiaNomesPorId,
        array $linhasPropriasForaDoGrupo,
        array $codigosFaltas
    ): void {
        if ($faltasFontePorCodigo === [] || $groupMetaByKey === []) {
            return;
        }

        $nomesProprios = [];
        foreach (array_keys($linhasPropriasForaDoGrupo) as $midProp) {
            $nkProp = $this->canonicalMateriaNomeKey((string) ($materiaNomesPorId[(int) $midProp] ?? ''));
            if ($nkProp !== '') {
                $nomesProprios[$nkProp] = true;
            }
        }

        foreach ($codigosFaltas as $codigo => $_ehFaltas) {
            $codigo = (string) $codigo;
            $fonte = $faltasFontePorCodigo[$codigo] ?? ($matrizPorCodigo[$codigo] ?? []);
            if (!is_array($fonte)) {
                continue;
            }
            $map = is_array($matrizPorCodigo[$codigo] ?? null) ? $matrizPorCodigo[$codigo] : [];
            foreach ($groupMetaByKey as $gk => $meta) {
                $vmid = (int) ($groupMidByKey[$gk] ?? 0);
                if ($vmid >= 0) {
                    continue;
                }
                $idsMembros = [];
                $nomesMembros = [];
                foreach ((array) ($meta['materias_ids'] ?? []) as $midMembro) {
                    $midMembro = (int) $midMembro;
                    if ($midMembro <= 0) {
                        continue;
                    }
                    $idsMembros[$midMembro] = true;
                    $nkMembro = $this->canonicalMateriaNomeKey((string) ($materiaNomesPorId[$midMembro] ?? ''));
                    if ($nkMembro !== '') {
                        $nomesMembros[$nkMembro] = true;
                    }
                }
                $nkGrupo = $this->canonicalMateriaNomeKey((string) ($meta['label'] ?? $gk));
                if ($nkGrupo !== '') {
                    $nomesMembros[$nkGrupo] = true;
                }
                $somaFilhas = 0.0;
                $temFilhas = false;
                $valorGrupoExistente = null;
                $contados = [];
                $absorvidos = [];
                foreach ($fonte as $midFonte => $valorFonte) {
                    $midFonte = (int) $midFonte;
                    if (!is_numeric($valorFonte)) {
                        continue;
                    }
                    $nkFonte = $this->canonicalMateriaNomeKey((string) ($materiaNomesPorId[$midFonte] ?? ''));
                    if ($midFonte === $vmid || ($midFonte < 0 && $nkFonte !== '' && $nkFonte === $nkGrupo)) {
                        if ($valorGrupoExistente === null) {
                            $valorGrupoExistente = (float) $valorFonte;
                        }
                        continue;
                    }
                    if ($midFonte < 0) {
                        continue;
                    }
                    if (isset($linhasPropriasForaDoGrupo[$midFonte])) {
                        continue;
                    }
                    if ($nkFonte !== '' && isset($nomesProprios[$nkFonte])) {
                        continue;
                    }
                    $entra = isset($idsMembros[$midFonte])
                        || ($nkFonte !== '' && isset($nomesMembros[$nkFonte]));
                    if (!$entra) {
                        continue;
                    }
                    $chaveContagem = $nkFonte !== '' ? ('n:' . $nkFonte . ':' . $midFonte) : ('id:' . $midFonte);
                    if (isset($contados[$chaveContagem])) {
                        continue;
                    }
                    $contados[$chaveContagem] = true;
                    $absorvidos[$midFonte] = true;
                    $somaFilhas += (float) $valorFonte;
                    $temFilhas = true;
                }
                if ($temFilhas) {
                    $map[$vmid] = $somaFilhas;
                    foreach (array_keys($absorvidos) as $midAbs) {
                        unset($map[(int) $midAbs]);
                    }
                    foreach (array_keys($map) as $midMap) {
                        $midMap = (int) $midMap;
                        if ($midMap === $vmid) {
                            continue;
                        }
                        $nkMap = $this->canonicalMateriaNomeKey((string) ($materiaNomesPorId[$midMap] ?? ''));
                        if ($midMap < 0 && $nkMap !== '' && $nkMap === $nkGrupo) {
                            unset($map[$midMap]);
                        }
                    }
                } elseif ($valorGrupoExistente !== null) {
                    $map[$vmid] = $valorGrupoExistente;
                }
            }
            $matrizPorCodigo[$codigo] = $map;
        }
    }

    /**
     * Só colunas sN agregam N/Q em conjunto. Média final / prova / jornada / media_sem
     * usam a média (ou soma) das notas 0–10 de cada matéria do grupo.
     *
     * @param array<string,mixed> $cfgGroup
     */
    private function componenteAgregaGrupoPorNq(string $codigo, array $cfgGroup): bool
    {
        return BoletimQuadroLayoutHelper::codigoEhSemana(strtolower(trim($codigo)));
    }

    private function agruparValoresGrupoLinha(array $valores, string $modo, float $divisor): ?float
    {
        if ($valores === []) {
            return null;
        }
        $modo = strtolower(trim($modo));
        if ($modo === 'soma') {
            return array_sum($valores);
        }
        $div = $divisor > 0 ? $divisor : count($valores);
        if ($div <= 0) {
            return null;
        }
        return array_sum($valores) / $div;
    }

    /**
     * @param array<string, float|null> $notasLinha
     * @param array<string, float> $valoresFormula
     * @return array{valor: ?float, metodo: string, erro: ?string}
     */
    private function resumoNotaLinhaMatriz(
        array $regra,
        array $componentesResultado,
        array $notasLinha,
        array $valoresFormula,
        string $formula,
        int $materiaId
    ): array {
        $formulaMateria = $this->formulaFinalPorMateria($regra, $materiaId);
        if ($formulaMateria !== '') {
            $rMat = $this->avaliarFormula($formulaMateria, $valoresFormula);
            if (!empty($rMat['ok'])) {
                return ['valor' => (float) $rMat['valor'], 'metodo' => 'formula_materia', 'erro' => null];
            }

            return ['valor' => null, 'metodo' => 'formula_materia', 'erro' => (string) ($rMat['erro'] ?? 'Erro na fórmula da matéria')];
        }
        $formula = trim($formula);
        if ($formula !== '') {
            $r = $this->avaliarFormula($formula, $valoresFormula);
            if (!empty($r['ok'])) {
                return ['valor' => (float) $r['valor'], 'metodo' => 'formula', 'erro' => null];
            }

            return ['valor' => null, 'metodo' => 'formula', 'erro' => (string) ($r['erro'] ?? 'Erro na fórmula')];
        }

        $somaPesos = 0.0;
        $somaPonderada = 0.0;
        foreach ($componentesResultado as $comp) {
            if ($this->componenteContaComoFalta($comp)) {
                continue;
            }
            $cod = (string) ($comp['codigo'] ?? '');
            if ($cod === '' || !array_key_exists($cod, $notasLinha) || !is_numeric($notasLinha[$cod])) {
                continue;
            }
            $peso = (float) ($comp['peso'] ?? 1);
            $peso = $peso > 0 ? $peso : 1;
            $somaPesos += $peso;
            $somaPonderada += ((float) $notasLinha[$cod]) * $peso;
        }
        if ($somaPesos <= 0) {
            return ['valor' => null, 'metodo' => 'ponderada', 'erro' => null];
        }

        return [
            'valor' => round($somaPonderada / $somaPesos, 2),
            'metodo' => 'ponderada',
            'erro' => null,
        ];
    }

    private function calcularNotaFinal(array $regra, array $componentes, array $valoresPorCodigo, array $faltantesObrigatorios): array
    {
        $roundMode = $this->normalizeRoundMode((string) ($regra['round_mode'] ?? 'none'));
        if (!empty($faltantesObrigatorios)) {
            return [
                'nota_final' => null,
                'metodo' => 'incompleto',
                'expressao' => '',
                'erro_formula' => 'Faltam componentes obrigatórios.',
            ];
        }

        $formula = trim((string) ($regra['formula_final'] ?? ''));
        if ($formula !== '') {
            $resultadoFormula = $this->avaliarFormula($formula, $valoresPorCodigo);
            if ($resultadoFormula['ok']) {
                return [
                    'nota_final' => $this->applyRoundMode((float) $resultadoFormula['valor'], $roundMode),
                    'metodo' => 'formula',
                    'expressao' => $resultadoFormula['expressao'],
                    'erro_formula' => null,
                ];
            }

            return [
                'nota_final' => null,
                'metodo' => 'formula',
                'expressao' => '',
                'erro_formula' => $resultadoFormula['erro'],
            ];
        }

        $somaPesos = 0.0;
        $somaPonderada = 0.0;
        foreach ($componentes as $comp) {
            if ($this->componenteContaComoFalta($comp)) {
                continue;
            }
            if (!is_numeric($comp['valor'] ?? null)) {
                continue;
            }
            $peso = (float) ($comp['peso'] ?? 1);
            $peso = $peso > 0 ? $peso : 1;
            $somaPesos += $peso;
            $somaPonderada += ((float) $comp['valor']) * $peso;
        }

        if ($somaPesos <= 0) {
            return [
                'nota_final' => null,
                'metodo' => 'ponderada',
                'expressao' => '',
                'erro_formula' => 'Sem dados para calcular média final.',
            ];
        }

        return [
            'nota_final' => $this->applyRoundMode(round($somaPonderada / $somaPesos, 2), $roundMode),
            'metodo' => 'ponderada',
            'expressao' => '',
            'erro_formula' => null,
        ];
    }

    private function normalizeRoundMode(string $value): string
    {
        return $this->resultadoAcademico()->normalizeRoundMode($value);
    }

    private function applyRoundMode(?float $value, string $mode): ?float
    {
        return $this->resultadoAcademico()->applyRoundMode($value, $mode);
    }

    /**
     * Resolve o arredondamento efetivo de um componente: por padrão "herda" o
     * arredondamento do evento (round_mode da regra), mas o componente pode
     * sobrescrever individualmente via config_json.round_mode_override
     * ('none' ou 'half'). Qualquer outro valor (ou ausência) = herda do evento.
     */
    private function resolveRoundModeComponente(array $componente, string $roundModeEvento): string
    {
        $decoded = [];
        $raw = $componente['config_json'] ?? '';
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $tmp = json_decode(trim($raw), true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        }
        if (isset($componente['config']) && is_array($componente['config'])) {
            $decoded = array_replace($decoded, $componente['config']);
        }
        $override = strtolower(trim((string) ($decoded['round_mode_override'] ?? 'herdar')));
        return in_array($override, ['none', 'half'], true) ? $override : $roundModeEvento;
    }

    /**
     * @param array<int, float> $map
     * @return array<int, float>
     */
    private function applyRoundModeToMateriaMap(array $map, string $mode): array
    {
        foreach ($map as $k => $v) {
            if (!is_numeric($v)) {
                continue;
            }
            $mid = (int) $k;
            if ($mid > 0 && !empty($this->midsSemArredondamentoGrupo[$mid])) {
                $map[$k] = round((float) $v, 2);
                continue;
            }
            $map[$k] = (float) ($this->applyRoundMode((float) $v, $mode) ?? $v);
        }
        return $map;
    }

    /**
     * @param mixed $raw
     */
    private function normalizarArredondamentoGrupo($raw): string
    {
        $v = strtolower(trim((string) $raw));
        if ($v === 'filhas' || $v === 'mae' || $v === 'mãe') {
            return $v === 'mãe' ? 'mae' : $v;
        }
        return 'todos';
    }

    private function aplicarArredondamentoMaeGrupo(float $valor, string $roundMode, string $arredondamento): float
    {
        // filhas: média/soma exata da mãe (sem half); todos/mae: aplica round do evento.
        if ($arredondamento === 'filhas') {
            return round($valor, 2);
        }
        return (float) ($this->applyRoundMode($valor, $roundMode) ?? round($valor, 2));
    }

    /**
     * Filhas de group_line com arredondamento=mae não passam pelo half nas células.
     *
     * @param list<array<string,mixed>> $componentes
     */
    private function carregarMidsSemArredondamentoGrupo(array $componentes, string $exibirEm, bool $forcarAgrupamento): void
    {
        $this->midsSemArredondamentoGrupo = [];
        $exibirEmNorm = strtolower(trim($exibirEm)) === 'notas' ? 'notas' : 'boletim';
        foreach ($componentes as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $grp = $this->parseGroupLineConfigFromComponente($comp);
            if ($grp === null) {
                continue;
            }
            if (
                !$forcarAgrupamento
                && $exibirEmNorm === 'notas'
                && (($grp['aplicar_em'] ?? 'ambos') === 'boletim')
            ) {
                continue;
            }
            if ($this->normalizarArredondamentoGrupo($grp['arredondamento'] ?? 'todos') !== 'mae') {
                continue;
            }
            foreach ((array) ($grp['materias_ids'] ?? []) as $mid) {
                $mid = (int) $mid;
                if ($mid > 0) {
                    $this->midsSemArredondamentoGrupo[$mid] = true;
                }
            }
        }
    }

    private function avaliarFormula(string $formula, array $valoresPorCodigo): array
    {
        return $this->resultadoAcademico()->avaliarFormula($formula, $valoresPorCodigo);
    }

    private function resultadoAcademico(): ResultadoAcademicoService
    {
        if ($this->resultadoAcademicoSvc === null) {
            $this->resultadoAcademicoSvc = new ResultadoAcademicoService();
        }
        return $this->resultadoAcademicoSvc;
    }

    private function cursoIdDaTurma(int $turmaId): ?int
    {
        if ($turmaId <= 0) {
            return null;
        }
        if (array_key_exists($turmaId, $this->cursoPorTurmaCache)) {
            return $this->cursoPorTurmaCache[$turmaId];
        }
        try {
            $row = Database::getInstance()->fetch(
                "SELECT curso_novo_id FROM turmas WHERE id = :id LIMIT 1",
                ['id' => $turmaId]
            );
            $id = (int) ($row['curso_novo_id'] ?? 0);
            $this->cursoPorTurmaCache[$turmaId] = $id > 0 ? $id : null;
        } catch (Throwable $e) {
            $this->cursoPorTurmaCache[$turmaId] = null;
        }
        return $this->cursoPorTurmaCache[$turmaId];
    }

    /**
     * Situação acadêmica da linha do boletim — motor único, com fallback na mínima do evento.
     *
     * @param array<string,mixed> $regra
     * @param array<string,mixed> $contextoAluno
     * @param array<string,mixed> $notasLinha
     * @param list<array<string,mixed>> $colunas
     */
    private function rotuloResultadoAcademico(
        array $regra,
        array $contextoAluno,
        int $materiaId,
        array $notasLinha,
        array $colunas,
        float $mediaRef,
        float $notaMinimaFallback,
        ?int $agrupamentoId = null
    ): string {
        $motor = $this->resultadoAcademico();
        $contexto = $contextoAluno;
        $contexto['materia_id'] = $materiaId > 0 ? $materiaId : null;
        if ($agrupamentoId !== null && $agrupamentoId > 0) {
            $contexto['agrupamento_id'] = $agrupamentoId;
        }
        $regraAcad = $motor->resolverRegra($contexto);
        if ($regraAcad === null) {
            $regraAcad = $motor->regraFallbackDoBoletim($regra + ['nota_minima_aprovacao' => $notaMinimaFallback]);
        }

        $rec = null;
        $mediaAntes = null;
        foreach ($colunas as $col) {
            $cod = (string) ($col['codigo'] ?? '');
            if ($cod === '' || !isset($notasLinha[$cod]) || !is_numeric($notasLinha[$cod])) {
                continue;
            }
            $lt = strtolower((string) ($col['layout_type'] ?? ''));
            $lg = strtolower((string) ($col['layout_group'] ?? ''));
            if ($lt === 'rec' || $cod === 'rec' || $cod === 'rec_final' || str_contains($cod, 'recup')) {
                $rec = (float) $notasLinha[$cod];
            }
            if ($lt === 'media' && ($lg === 'quadro_comum' || $cod === 'media_bim' || str_contains($cod, 'media_bim'))) {
                $mediaAntes = (float) $notasLinha[$cod];
            }
        }

        $avaliado = $motor->avaliar([
            'media' => $mediaRef,
            'media_antes_rec' => $mediaAntes,
            'recuperacao' => $rec,
            'tem_nota' => true,
            'aluno_id' => (int) ($contexto['aluno_id'] ?? 0),
            'turma_id' => (int) ($contexto['turma_id'] ?? 0),
            'materia_id' => $materiaId > 0 ? $materiaId : null,
            'data_inicio' => $contexto['data_inicio'] ?? null,
            'data_fim' => $contexto['data_fim'] ?? null,
        ], $regraAcad);

        return (string) ($avaliado['rotulo'] ?? '-');
    }

    private function extrairNotaDaProva(array $row, array $componente): ?float
    {
        $usarPercentual = !empty($componente['usar_percentual']);
        // Regra pedagógica do boletim: no modo "acertos/questões", a nota deve ser sempre 0..10.
        $escalaMax = $usarPercentual
            ? 10.0
            : max(0.01, (float) ($componente['escala_max'] ?? 10));
        $total = (int) ($row['total_questoes'] ?? 0);
        $acertos = (int) ($row['acertos'] ?? 0);
        $notaBruta = array_key_exists('nota', $row) && $row['nota'] !== null && $row['nota'] !== ''
            ? (float) $row['nota']
            : null;
        $valorTotalProva = isset($row['valor_total']) && is_numeric($row['valor_total'])
            ? (float) $row['valor_total']
            : null;

        if ($usarPercentual) {
            if ($total > 0) {
                $n = ($acertos / $total) * $escalaMax;
                return round(max(0.0, min($escalaMax, $n)), 2);
            }
            if ($notaBruta !== null) {
                return $this->normalizarNotaBrutaEscala($notaBruta, $valorTotalProva, $escalaMax);
            }

            return null;
        }

        if ($notaBruta !== null) {
            return $this->normalizarNotaBrutaEscala($notaBruta, $valorTotalProva, $escalaMax);
        }
        if ($total > 0) {
            $n = ($acertos / $total) * $escalaMax;
            return round(max(0.0, min($escalaMax, $n)), 2);
        }

        return null;
    }

    private function normalizarNotaBrutaEscala(float $notaBruta, ?float $valorTotalProva, float $escalaMax): float
    {
        if ($valorTotalProva !== null && $valorTotalProva > 0.0) {
            $n = ($notaBruta / $valorTotalProva) * $escalaMax;
            return round(max(0.0, min($escalaMax, $n)), 2);
        }
        // Fallback: sem valor_total, assume que já está na escala do componente.
        return round(max(0.0, min($escalaMax, $notaBruta)), 2);
    }

    private function agruparNotas(array $notas, string $calcType): ?float
    {
        $valores = [];
        foreach ($notas as $item) {
            if (is_array($item)) {
                if (isset($item['valor']) && is_numeric($item['valor'])) {
                    $valores[] = (float) $item['valor'];
                }
                continue;
            }
            if (is_numeric($item)) {
                $valores[] = (float) $item;
            }
        }

        if (empty($valores)) {
            return null;
        }

        $calcType = $this->normalizeCalcType($calcType);

        if ($calcType === 'soma') {
            return round(array_sum($valores), 2);
        }

        if ($calcType === 'maior') {
            return round((float) max($valores), 2);
        }

        if ($calcType === 'ultima') {
            return round((float) $valores[0], 2);
        }

        return round(array_sum($valores) / count($valores), 2);
    }

    private function deduplicarNotasPorMateria(array $notas, string $modo = 'soma'): array
    {
        $modo = strtolower(trim($modo)) === 'media' ? 'media' : 'soma';
        $porMateriaEProva = [];
        $semMateria = [];
        $seqSemProva = 0;

        foreach ($notas as $item) {
            $valor = is_array($item) ? (float) ($item['valor'] ?? 0) : (float) $item;
            $materiaId = is_array($item) ? (int) ($item['materia_id'] ?? 0) : 0;
            $materiaNome = is_array($item) ? trim((string) ($item['materia_nome'] ?? '')) : '';
            $provaUid = is_array($item) ? trim((string) ($item['prova_uid'] ?? '')) : '';

            if ($materiaId <= 0) {
                $semMateria[] = ['valor' => $valor, 'materia_id' => 0, 'materia_nome' => $materiaNome];
                continue;
            }

            // Matéria única por prova: consolida professores repetidos na mesma prova.
            if ($provaUid === '') {
                $provaUid = 'sem_prova_' . (++$seqSemProva);
            }
            $k = $materiaId . '|' . $provaUid;
            if (!isset($porMateriaEProva[$k])) {
                $porMateriaEProva[$k] = [
                    'soma' => 0.0,
                    'qtd' => 0,
                    'materia_id' => $materiaId,
                    'materia_nome' => $materiaNome,
                ];
            }
            $porMateriaEProva[$k]['soma'] += $valor;
            $porMateriaEProva[$k]['qtd']++;
            if (($porMateriaEProva[$k]['materia_nome'] ?? '') === '' && $materiaNome !== '') {
                $porMateriaEProva[$k]['materia_nome'] = $materiaNome;
            }
        }

        $saida = [];
        foreach ($porMateriaEProva as $item) {
            $soma = (float) ($item['soma'] ?? 0);
            $qtd = max(1, (int) ($item['qtd'] ?? 1));
            $saida[] = [
                'valor' => $modo === 'media' ? round($soma / $qtd, 2) : $soma,
                'materia_id' => (int) ($item['materia_id'] ?? 0),
                'materia_nome' => (string) ($item['materia_nome'] ?? ''),
            ];
        }
        foreach ($semMateria as $item) {
            $saida[] = $item;
        }

        return $saida;
    }

    /**
     * Mantém só os blocos da turma do aluno (e blocos sem turma).
     * Outra série com a mesma semana não soma as questões de novo.
     *
     * @param list<array<string,mixed>> $rows
     * @param list<int> $blocoIds
     * @return list<array<string,mixed>>
     */
    private function restringirProvasAosBlocosDaTurma(array $rows, array $blocoIds, int $alunoId): array
    {
        if ($rows === [] || $alunoId <= 0 || $blocoIds === []) {
            return $rows;
        }
        if (!class_exists('AlunoTurmaHelper', false)) {
            require_once dirname(__DIR__, 2) . '/Core/AlunoTurmaHelper.php';
        }
        $turmas = AlunoTurmaHelper::getTurmaIds(Database::getInstance(), $alunoId);
        if ($turmas === []) {
            return $rows;
        }
        $permitidos = array_fill_keys($this->boletimConfig->filtrarBlocoIdsPorTurmas($blocoIds, $turmas), true);
        if ($permitidos === []) {
            return $rows;
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $blocoId = (int) ($row['bloco_id'] ?? 0);
            if ($blocoId > 0 && !isset($permitidos[$blocoId])) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Uma prova conta uma vez na matéria, mesmo com duas realizações ou dois blocos.
     * Professores diferentes continuam somando, porque cada um tem a sua prova.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function deduplicarProvasDaMateria(array $rows): array
    {
        $vistas = [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $provaId = (int) ($row['prova_id'] ?? 0);
            $mid = (int) ($row['materia_id'] ?? 0);
            $aluno = (int) ($row['aluno_id'] ?? 0);
            if ($provaId <= 0) {
                $out[] = $row;
                continue;
            }
            $chave = $aluno . ':' . $provaId . ':' . $mid;
            if (isset($vistas[$chave])) {
                continue;
            }
            $vistas[$chave] = true;
            $out[] = $row;
        }

        return $out;
    }

    private function extrairProvaUid(array $row): string
    {
        foreach (['prova_id', 'id_prova', 'prova_evento_id', 'id'] as $k) {
            if (isset($row[$k]) && is_numeric($row[$k])) {
                return 'id:' . (int) $row[$k];
            }
        }
        $bloco = isset($row['bloco_id']) && is_numeric($row['bloco_id']) ? (int) $row['bloco_id'] : 0;
        $data = substr((string) ($row['data_prova'] ?? $row['created_at'] ?? ''), 0, 10);
        $titulo = trim((string) ($row['titulo'] ?? $row['nome'] ?? ''));
        $base = $bloco . '|' . $data . '|' . $titulo;
        if (trim($base, '|') === '') {
            return '';
        }
        return 'h:' . substr(sha1($base), 0, 16);
    }

    private function extrairMateriasNomes(array $notas): array
    {
        $nomes = [];
        foreach ($notas as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nome = trim((string) ($item['materia_nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $nomes[$nome] = true;
        }
        return array_keys($nomes);
    }

    private function periodoDefault(): string
    {
        $ano = (int) date('Y');
        $mes = (int) date('n');

        if ($mes <= 3) {
            $b = 1;
        } elseif ($mes <= 6) {
            $b = 2;
        } elseif ($mes <= 9) {
            $b = 3;
        } else {
            $b = 4;
        }

        return $ano . '-B' . $b;
    }

    private function periodoToRange(string $periodoRef): array
    {
        $periodoRef = trim($periodoRef);

        // Formato gerado por buildPeriodoRefFromDateRange(): "RANGE:YYYY-MM-DD:YYYY-MM-DD".
        // Sem esse caso, qualquer chamada que reconstrua o intervalo só a partir do
        // periodo_ref (sem datas explícitas) ficava sem filtro de data nenhum.
        if (preg_match('/^RANGE:(\d{4}-\d{2}-\d{2}):(\d{4}-\d{2}-\d{2})$/', $periodoRef, $m)) {
            return [
                'inicio' => $m[1] . ' 00:00:00',
                'fim' => $m[2] . ' 23:59:59',
            ];
        }

        if (preg_match('/^(\d{4})-B([1-4])$/', $periodoRef, $m)) {
            $ano = (int) $m[1];
            $b = (int) $m[2];

            $ranges = [
                1 => ['01-01 00:00:00', '03-31 23:59:59'],
                2 => ['04-01 00:00:00', '06-30 23:59:59'],
                3 => ['07-01 00:00:00', '09-30 23:59:59'],
                4 => ['10-01 00:00:00', '12-31 23:59:59'],
            ];

            return [
                'inicio' => $ano . '-' . $ranges[$b][0],
                'fim' => $ano . '-' . $ranges[$b][1],
            ];
        }

        if (preg_match('/^(\d{4})$/', $periodoRef, $m)) {
            $ano = (int) $m[1];
            return [
                'inicio' => $ano . '-01-01 00:00:00',
                'fim' => $ano . '-12-31 23:59:59',
            ];
        }

        return [
            'inicio' => null,
            'fim' => null,
        ];
    }

    private function buscarAluno(int $alunoId): ?array
    {
        if (isset($this->alunosPorIdCache[$alunoId])) {
            return $this->alunosPorIdCache[$alunoId];
        }
        $rows = $this->boletimConfig->getStudentsByIds([$alunoId]);
        $aluno = is_array($rows[0] ?? null) ? $rows[0] : null;
        if ($aluno !== null) {
            $this->alunosPorIdCache[$alunoId] = $aluno;
        }

        return $aluno;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function obterNotasManuaisComponenteCached(int $componenteId, int $alunoId, string $periodoRef): array
    {
        if ($this->notasManuaisGeracaoAtivo) {
            $porMateria = $this->notasManuaisGeracaoCache[$componenteId][$alunoId] ?? [];
            unset($porMateria[0]);

            return $porMateria;
        }

        return $this->boletimConfig->getManualNotesByComponente($componenteId, $alunoId, $periodoRef);
    }

    /**
     * @param list<int> $blocoIds
     * @return list<array<string, mixed>>
     */
    private function obterProvasAlunoBlocosCached(
        int $alunoId,
        array $blocoIds,
        ?string $inicio,
        ?string $fim,
        ?string $filtroTitulo,
        ?int $materiaId
    ): array {
        $chave = $this->chaveCacheProvasGeracao($blocoIds, $filtroTitulo, $materiaId, $inicio, $fim);
        if (!isset($this->provasGeracaoCache[$chave]) && $this->prefetchGeracaoPronto) {
            $alunoIds = array_keys($this->alunosPorIdCache);
            if ($alunoId > 0 && !isset($this->alunosPorIdCache[$alunoId])) {
                $alunoIds[] = $alunoId;
            }
            if ($blocoIds !== [] && $alunoIds !== []) {
                $this->provasGeracaoCache[$chave] = $this->boletimConfig->getProvasFinalizadasPorAlunosAndBlocos(
                    $alunoIds,
                    $blocoIds,
                    $inicio,
                    $fim,
                    $filtroTitulo,
                    $materiaId,
                    $this->prefetchIncluirPct
                );
            }
        }
        if (isset($this->provasGeracaoCache[$chave])) {
            return $this->provasGeracaoCache[$chave][$alunoId] ?? [];
        }

        return $this->boletimConfig->getProvasFinalizadasByAlunoAndBlocos(
            $alunoId,
            $blocoIds,
            $inicio,
            $fim,
            $filtroTitulo,
            $materiaId
        );
    }

    private function obterNotaManualCached(int $componenteId, int $alunoId, string $periodoRef, int $materiaId = 0): ?array
    {
        if ($this->notasManuaisGeracaoAtivo) {
            $row = $this->notasManuaisGeracaoCache[$componenteId][$alunoId][$materiaId] ?? null;

            return is_array($row) ? $row : null;
        }

        return $this->boletimConfig->getManualNote($componenteId, $alunoId, $periodoRef, $materiaId);
    }

    private function slug(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', '_', $text) ?? $text;
        $text = trim($text, '_');
        if ($text === '') {
            $text = 'comp_' . time();
        }
        return substr($text, 0, 60);
    }

    private function slugEvent(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
        $text = trim($text, '-');

        return substr($text, 0, 120);
    }

    private function normalizeCalcType(string $calcType): string
    {
        $calcType = strtolower(trim($calcType));
        $allowed = ['media', 'soma', 'maior', 'ultima'];
        return in_array($calcType, $allowed, true) ? $calcType : 'media';
    }

    private function normalizeSourceTypeForSave(string $sourceType): string
    {
        $t = strtolower(trim($sourceType));
        if ($t === 'jornadas') {
            return 'jornadas';
        }
        if ($t === 'calculado') {
            return 'calculado';
        }
        if ($t === 'evento_boletim') {
            return 'evento_boletim';
        }
        if ($t === 'faltas_evento') {
            return 'faltas_evento';
        }
        if ($t === 'nenhuma') {
            return 'nenhuma';
        }

        return 'provas_sistema';
    }

    private function parseCalculadoTracoAbaixoMinimoFromComponente(array $componente): bool
    {
        $raw = $componente['config_json'] ?? '';
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode(trim((string) $raw), true);
        }
        if (!is_array($decoded) && isset($componente['config']) && is_array($componente['config'])) {
            $decoded = $componente['config'];
        }
        if (!is_array($decoded)) {
            return false;
        }

        return !empty($decoded['traco_abaixo_minimo']);
    }

    private function parseExpressaoColunaCalculada(array $componente): string
    {
        $raw = $componente['config_json'] ?? '';
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode(trim((string) $raw), true);
        }
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $e = trim((string) ($decoded['expressao'] ?? ''));
        if ($e === '' && isset($componente['config']) && is_array($componente['config'])) {
            $e = trim((string) ($componente['config']['expressao'] ?? ''));
        }

        return $e;
    }

    /**
     * @return array<int, string>
     */
    private function parseFormulaMateriasCalculadoFromComponente(array $componente): array
    {
        // config do assistente (exceção por matéria) precisa vencer o config_json
        // vazio: sem isso a prévia ignora a fórmula da matéria e usa a geral.
        $decoded = $this->decodeComponenteConfig($componente);
        $formulaMode = strtolower(trim((string) ($decoded['formula_mode'] ?? '')));
        if ($formulaMode === 'single') {
            return [];
        }
        $map = $decoded['formula_materias'] ?? [];
        if (!is_array($map)) {
            return [];
        }
        $out = [];
        foreach ($map as $midRaw => $exprRaw) {
            $mid = (int) $midRaw;
            $expr = trim((string) $exprRaw);
            if ($mid > 0 && $expr !== '') {
                $out[$mid] = $expr;
            }
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private function parseMateriasIdsFromComponente(array $componente): array
    {
        $ids = [];
        if (isset($componente['materias_ids']) && is_array($componente['materias_ids'])) {
            foreach ($componente['materias_ids'] as $v) {
                $id = (int) $v;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } elseif (isset($componente['materias_ids']) && is_string($componente['materias_ids'])) {
            $dec = json_decode((string) $componente['materias_ids'], true);
            if (is_array($dec)) {
                foreach ($dec as $v) {
                    $id = (int) $v;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) > 300) {
            $ids = array_slice($ids, 0, 300);
        }

        return $ids;
    }

    /**
     * @return array<int, string>
     */
    private function parseFormulaMateriasMapFromRegra(array $regra): array
    {
        $raw = trim((string) ($regra['formula_materias_json'] ?? ''));
        if ($raw === '') {
            return [];
        }
        $dec = json_decode($raw, true);
        if (!is_array($dec)) {
            return [];
        }
        $out = [];
        foreach ($dec as $midRaw => $exprRaw) {
            if (is_array($exprRaw)) {
                $mid = (int) ($exprRaw['materia_id'] ?? 0);
                $expr = trim((string) ($exprRaw['formula'] ?? ''));
                if ($mid > 0 && $expr !== '') {
                    $out[$mid] = $expr;
                }
                continue;
            }
            $mid = (int) $midRaw;
            $expr = trim((string) $exprRaw);
            if ($mid > 0 && $expr !== '') {
                $out[$mid] = $expr;
            }
        }

        return $out;
    }

    private function formulaFinalPorMateria(array $regra, int $materiaId): string
    {
        if ($materiaId <= 0) {
            return '';
        }
        $map = $regra['formula_materias_map'] ?? null;
        if (!is_array($map)) {
            $map = $this->parseFormulaMateriasMapFromRegra($regra);
            $regra['formula_materias_map'] = $map;
        }

        return trim((string) ($map[$materiaId] ?? ''));
    }

    private function normalizeFormulaMateriasJsonForSave(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $dec = json_decode($raw, true);
        if (!is_array($dec)) {
            return null;
        }
        $out = [];
        foreach ($dec as $item) {
            if (!is_array($item)) {
                continue;
            }
            $mid = (int) ($item['materia_id'] ?? 0);
            $expr = trim((string) ($item['formula'] ?? ''));
            if ($mid > 0 && $expr !== '') {
                $out[$mid] = $expr;
            }
        }
        if ($out === []) {
            return null;
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return list<string>
     */
    private function codigosReferenciadosNaExpressao(string $expr, array $knownCodes = []): array
    {
        $out = [];

        // Primeiro, tenta casar explicitamente códigos conhecidos
        // (aceita hífen e códigos iniciando por número).
        if (!empty($knownCodes)) {
            usort($knownCodes, static function ($a, $b) {
                return strlen((string) $b) <=> strlen((string) $a);
            });
            foreach ($knownCodes as $codeRaw) {
                $code = trim((string) $codeRaw);
                if ($code === '') {
                    continue;
                }
                $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($code, '/') . '(?![A-Za-z0-9_])/u';
                if (preg_match($pattern, $expr)) {
                    $out[$code] = true;
                }
            }
        }

        // Fallback para identificadores "clássicos".
        if (preg_match_all('/\b[a-zA-Z_][a-zA-Z0-9_]*\b/', $expr, $m)) {
            foreach ($m[0] as $token) {
                $tl = strtolower((string) $token);
                if ($tl === 'max' || $tl === 'min') {
                    continue;
                }
                $out[(string) $token] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Evita expressões em que um "código" com hífen vira vários símbolos (ex.: prova-semanal-1 → prova, semanal, 1):
     * o avaliador substitui o que não reconhece por zero e a média fica errada.
     *
     * @param list<string> $codigosBlocos códigos dos componentes do fluxo
     */
    private function validarTokensExpressaoContraCodigosBlocos(string $expr, array $codigosBlocos): ?string
    {
        $expr = trim($expr);
        if ($expr === '') {
            return null;
        }
        $valid = [];
        foreach ($codigosBlocos as $c) {
            $c = trim((string) $c);
            if ($c !== '') {
                $valid[strtolower($c)] = true;
            }
        }
        if ($valid === []) {
            return null;
        }
        if (!preg_match_all('/\b[a-zA-Z_][a-zA-Z0-9_]*\b/u', $expr, $m)) {
            return null;
        }
        foreach ($m[0] as $token) {
            $tl = strtolower((string) $token);
            if ($tl === 'max' || $tl === 'min') {
                continue;
            }
            if (preg_match('/^\d+$/', (string) $token)) {
                continue;
            }
            if (!isset($valid[$tl])) {
                return 'o texto "' . $token . '" não é um código de bloco neste evento. Use exatamente o código de cada bloco no fluxo (ex.: semanal e bimestral), sem inventar nomes com hífen — isso quebra o cálculo.';
            }
        }

        return null;
    }

    /**
     * Nome da peça e código da coluna são a mesma nota (recuperacao = rec, semanal = media_sem).
     *
     * @return list<string>
     */
    private function aliasesCodigoColuna(string $codigo): array
    {
        $codigo = trim($codigo);
        $baixo = strtolower($codigo);
        $pares = [
            'recuperacao' => 'rec',
            'rec' => 'recuperacao',
            'semanal' => 'media_sem',
            'media_sem' => 'semanal',
            'bimestral' => 'prova_bim',
            'prova_bim' => 'bimestral',
            'trabalho' => 'trab',
            'trab' => 'trabalho',
            'participacao' => 'part',
            'part' => 'participacao',
        ];
        $out = [];
        foreach ([$codigo, $baixo, $pares[$baixo] ?? ''] as $cand) {
            $cand = trim($cand);
            if ($cand !== '' && !in_array($cand, $out, true)) {
                $out[] = $cand;
            }
        }

        return $out;
    }

    /**
     * Fórmula só com número (min(7, 8) ou 6) vale para toda matéria que já tem alguma coluna.
     *
     * @param array<string, string> $exprs
     * @param array<string, array<int, float>> $matrizPorCodigo
     * @return array<int, float>
     */
    private function matrizFormulaConstante(array $exprs, array $matrizPorCodigo): array
    {
        $fallback = trim((string) ($exprs['*'] ?? ''));
        if ($fallback === '') {
            foreach ($exprs as $kExpr => $vExpr) {
                if ((string) $kExpr === '*') {
                    continue;
                }
                $cand = trim((string) $vExpr);
                if ($cand !== '') {
                    $fallback = $cand;
                    break;
                }
            }
        }
        if ($fallback === '') {
            return [];
        }
        $mids = [];
        foreach ($matrizPorCodigo as $map) {
            if (!is_array($map)) {
                continue;
            }
            foreach (array_keys($map) as $mid) {
                $mid = (int) $mid;
                if ($mid !== 0) {
                    $mids[$mid] = true;
                }
            }
        }
        $out = [];
        foreach (array_keys($mids) as $mid) {
            $mid = (int) $mid;
            $exprMid = trim((string) ($exprs[(string) $mid] ?? $fallback));
            if ($exprMid === '') {
                continue;
            }
            $resultado = $this->avaliarFormula($exprMid, []);
            if (!empty($resultado['ok']) && isset($resultado['valor']) && is_numeric($resultado['valor'])) {
                $out[$mid] = (float) $resultado['valor'];
            }
        }

        return $out;
    }

    /**
     * Segunda passada: coluna que cita outra calculada enxerga o valor já fechado.
     *
     * @param array<int|string, mixed> $componentes
     * @param array<string, array<int, float>> $matrizPorCodigo
     * @param list<array<string, mixed>> $componentesResultado
     * @param array<string, array<int, float|null>> $manterManualPorCodigo
     */
    private function repassarFormulasNaMatriz(
        array $componentes,
        array &$matrizPorCodigo,
        array &$componentesResultado,
        string $roundMode,
        array $manterManualPorCodigo = []
    ): void {
        $lista = $this->listarCalculadosNaOrdemDaFormula($componentes);
        if ($lista === []) {
            return;
        }
        $passos = count($lista);
        for ($passo = 0; $passo < $passos; $passo++) {
            foreach ($lista as $comp) {
                if (!is_array($comp)) {
                    continue;
                }
                if ($this->parseAgregarNqFromComponente($comp) !== []) {
                    continue;
                }
                $codigo = trim((string) ($comp['codigo'] ?? ''));
                $expr = $this->parseExpressaoColunaCalculada($comp);
                $porMateria = $this->parseFormulaMateriasCalculadoFromComponente($comp);
                if ($codigo === '' || ($expr === '' && $porMateria === [])) {
                    continue;
                }
                $map = $this->matrizColunaCalculada($expr, $porMateria, $codigo, $matrizPorCodigo);
                if ($map === []) {
                    continue;
                }
                $modo = $this->resolveRoundModeComponente($comp, $roundMode);
                $map = $this->applyRoundModeToMateriaMap($map, $modo);
                foreach ($manterManualPorCodigo as $codManual => $porMidManual) {
                    if (strcasecmp((string) $codManual, $codigo) !== 0 || !is_array($porMidManual)) {
                        continue;
                    }
                    foreach ($porMidManual as $midManual => $notaManual) {
                        $map[(int) $midManual] = $notaManual;
                    }
                }
                $matrizPorCodigo[$codigo] = $map;
                $vals = [];
                foreach ($map as $nota) {
                    if (is_numeric($nota)) {
                        $vals[] = (float) $nota;
                    }
                }
                if ($vals === []) {
                    continue;
                }
                $valor = $this->applyRoundMode(round(array_sum($vals) / count($vals), 2), $modo);
                foreach ($componentesResultado as &$cr) {
                    if (!is_array($cr)) {
                        continue;
                    }
                    if (strcasecmp((string) ($cr['codigo'] ?? ''), $codigo) === 0) {
                        $cr['valor'] = $valor;
                        break;
                    }
                }
                unset($cr);
            }
        }
    }

    /**
     * Coluna calculada que cita outra (Resultado Final usa media_3) roda depois da citada.
     *
     * @param array<int|string, mixed> $componentes
     * @return list<array<string, mixed>>
     */
    private function listarCalculadosNaOrdemDaFormula(array $componentes): array
    {
        $calc = [];
        $exprPorCod = [];
        foreach ($componentes as $comp) {
            if (!is_array($comp) || strtolower(trim((string) ($comp['source_type'] ?? ''))) !== 'calculado') {
                continue;
            }
            $cod = strtolower(trim((string) ($comp['codigo'] ?? '')));
            if ($cod === '' || isset($calc[$cod])) {
                continue;
            }
            $calc[$cod] = $comp;
            $expr = strtolower($this->parseExpressaoColunaCalculada($comp));
            foreach ($this->parseFormulaMateriasCalculadoFromComponente($comp) as $exprFm) {
                $expr .= ' ' . strtolower((string) $exprFm);
            }
            $exprPorCod[$cod] = $expr;
        }
        if ($calc === []) {
            return [];
        }
        $deps = [];
        foreach ($exprPorCod as $cod => $expr) {
            $deps[$cod] = [];
            if ($expr === '') {
                continue;
            }
            foreach (array_keys($calc) as $outro) {
                if ($outro === $cod) {
                    continue;
                }
                foreach ($this->aliasesCodigoColuna((string) $outro) as $nome) {
                    $nome = strtolower(trim($nome));
                    if ($nome === '') {
                        continue;
                    }
                    if (preg_match('/(?<![a-z0-9_])' . preg_quote($nome, '/') . '(?![a-z0-9_])/', $expr)) {
                        $deps[$cod][$outro] = true;
                        break;
                    }
                }
            }
        }
        $ordenados = [];
        $visitados = [];
        $walk = function (string $cod) use (&$walk, &$visitados, &$ordenados, $deps, $calc): void {
            if (isset($visitados[$cod]) || !isset($calc[$cod])) {
                return;
            }
            $visitados[$cod] = true;
            foreach (array_keys($deps[$cod] ?? []) as $dep) {
                $walk((string) $dep);
            }
            $ordenados[] = $calc[$cod];
        };
        foreach (array_keys($calc) as $cod) {
            $walk($cod);
        }

        return $ordenados;
    }

    /**
     * @param array<string, string> $formulaPorMateria
     * @param array<string, array<int, float>> $matrizPorCodigo
     * @return array<int, float>
     */
    private function matrizColunaCalculada(
        string $expr,
        array $formulaPorMateria,
        string $codigoProprio,
        array $matrizPorCodigo
    ): array {
        $exprs = [];
        $expr = trim($expr);
        if ($expr !== '') {
            $exprs['*'] = $expr;
        }
        foreach ($formulaPorMateria as $midFm => $exprFm) {
            $midFm = (int) $midFm;
            $exprFm = trim((string) $exprFm);
            if ($midFm > 0 && $exprFm !== '') {
                $exprs[(string) $midFm] = $exprFm;
            }
        }
        if ($exprs === []) {
            return [];
        }
        $refsMap = [];
        foreach ($exprs as $exOne) {
            foreach ($this->codigosReferenciadosNaExpressao((string) $exOne, array_keys($matrizPorCodigo)) as $rc) {
                $refsMap[$rc] = true;
            }
        }
        $refs = array_keys($refsMap);
        $codigoMapInsensitive = [];
        $codigoMapNormalized = [];
        $normKey = static function (string $s): string {
            $s = strtolower(trim($s));
            $s = str_replace('-', '_', $s);
            $s = preg_replace('/_+/', '_', $s) ?? $s;
            return trim($s, '_');
        };
        foreach (array_keys($matrizPorCodigo) as $kCode) {
            $kCode = (string) $kCode;
            $codigoMapInsensitive[strtolower($kCode)] = $kCode;
            $codigoMapNormalized[$normKey($kCode)] = $kCode;
        }
        $codigoProprioKey = strtolower((string) $codigoProprio);
        $codigoProprioNorm = $normKey((string) $codigoProprio);
        $refsResolved = [];
        foreach ($refs as $c) {
            $ckey = strtolower((string) $c);
            $cnorm = $normKey((string) $c);
            if ($ckey === $codigoProprioKey || ($codigoProprioNorm !== '' && $cnorm === $codigoProprioNorm)) {
                continue;
            }
            $resolved = $codigoMapInsensitive[$ckey] ?? ($codigoMapNormalized[$cnorm] ?? (string) $c);
            $refsResolved[$resolved] = true;
        }
        $refs = array_keys($refsResolved);
        if ($refs === []) {
            return $this->matrizFormulaConstante($exprs, $matrizPorCodigo);
        }

        $fallbackExpr = trim((string) ($exprs['*'] ?? ''));
        if ($fallbackExpr === '') {
            foreach ($exprs as $kExpr => $vExpr) {
                if ($kExpr === '*') {
                    continue;
                }
                $cand = trim((string) $vExpr);
                if ($cand !== '') {
                    $fallbackExpr = $cand;
                    break;
                }
            }
        }

        $unionMids = [];
        foreach ($refs as $rc) {
            foreach ($this->aliasesCodigoColuna((string) $rc) as $cand) {
                foreach ($matrizPorCodigo as $chave => $mapChave) {
                    if (!is_array($mapChave) || strcasecmp((string) $chave, $cand) !== 0) {
                        continue;
                    }
                    foreach ($mapChave as $mid => $cell) {
                        if (is_numeric($cell)) {
                            $unionMids[(int) $mid] = true;
                        }
                    }
                }
            }
        }
        $out = [];
        foreach (array_keys($unionMids) as $mid) {
            $valoresFormula = [];
            foreach ($refs as $rc) {
                $notaRef = $this->notaNaMatrizPorApelido($matrizPorCodigo, (string) $rc, (int) $mid);
                $valoresFormula[$rc] = $notaRef ?? 0.0;
            }
            $exprUse = trim((string) ($exprs[(string) ((int) $mid)] ?? $fallbackExpr));
            if ($exprUse === '') {
                continue;
            }
            $r = $this->avaliarFormula($exprUse, $valoresFormula);
            if (!empty($r['ok'])) {
                $out[$mid] = (float) $r['valor'];
            }
        }

        return $out;
    }

    /**
     * Nota da matéria no código citado ou no apelido (rec e recuperacao são a mesma coluna).
     *
     * @param array<string, array<int, float|null>> $matrizPorCodigo
     */
    private function notaNaMatrizPorApelido(array $matrizPorCodigo, string $codigo, int $mid): ?float
    {
        $alternativa = null;
        foreach ($this->aliasesCodigoColuna($codigo) as $cand) {
            foreach ($matrizPorCodigo as $chave => $map) {
                if (!is_array($map) || strcasecmp((string) $chave, $cand) !== 0) {
                    continue;
                }
                if (!isset($map[$mid]) || !is_numeric($map[$mid])) {
                    continue;
                }
                $valor = (float) $map[$mid];
                if (strcasecmp((string) $chave, trim($codigo)) === 0) {
                    return $valor;
                }
                if ($alternativa === null) {
                    $alternativa = $valor;
                }
            }
        }

        return $alternativa;
    }

    /**
     * Mãe do grupo: a coluna calculada usa a fórmula nas notas já juntadas, não a média dos resultados das filhas.
     *
     * @param array<string, array<int, float|null>> $matrizPorCodigo
     * @param array<int|string, mixed> $componentes
     * @param array<string, array<int, float|null>> $notasManuaisVirtuais
     */
    private function aplicarFormulasNasLinhasVirtuais(
        array &$matrizPorCodigo,
        array $componentes,
        string $roundMode,
        array $notasManuaisVirtuais = []
    ): void
    {
        $vmids = [];
        foreach ($matrizPorCodigo as $map) {
            if (!is_array($map)) {
                continue;
            }
            foreach (array_keys($map) as $mid) {
                if ((int) $mid < 0) {
                    $vmids[(int) $mid] = true;
                }
            }
        }
        if ($vmids === []) {
            return;
        }
        $lista = $this->listarCalculadosNaOrdemDaFormula($componentes);
        if ($lista === []) {
            return;
        }
        $passos = count($lista);
        for ($passo = 0; $passo < $passos; $passo++) {
            foreach ($notasManuaisVirtuais as $codManual => $porMidManual) {
                if (!is_array($porMidManual)) {
                    continue;
                }
                if (!isset($matrizPorCodigo[$codManual]) || !is_array($matrizPorCodigo[$codManual])) {
                    $matrizPorCodigo[$codManual] = [];
                }
                foreach ($porMidManual as $midManual => $notaManual) {
                    $midManual = (int) $midManual;
                    $matrizPorCodigo[$codManual][$midManual] = $notaManual;
                    if ($midManual < 0) {
                        $vmids[$midManual] = true;
                    }
                }
            }
            foreach ($lista as $comp) {
                if (!is_array($comp) || $this->parseAgregarNqFromComponente($comp) !== []) {
                    continue;
                }
                $codigo = trim((string) ($comp['codigo'] ?? ''));
                $expr = $this->parseExpressaoColunaCalculada($comp);
                if ($codigo === '' || $expr === '') {
                    continue;
                }
                $modo = $this->resolveRoundModeComponente($comp, $roundMode);
                $grpFormula = $this->parseGroupLineConfigFromComponente($comp);
                $arredFormula = $this->normalizarArredondamentoGrupo(
                    is_array($grpFormula) ? ($grpFormula['arredondamento'] ?? 'todos') : 'todos'
                );
                if (!isset($matrizPorCodigo[$codigo]) || !is_array($matrizPorCodigo[$codigo])) {
                    $matrizPorCodigo[$codigo] = [];
                }
                foreach (array_keys($vmids) as $vmid) {
                    $celulaManual = false;
                    foreach ($notasManuaisVirtuais as $codManual => $porMidManual) {
                        if (!is_array($porMidManual) || strcasecmp((string) $codManual, $codigo) !== 0) {
                            continue;
                        }
                        if (array_key_exists((int) $vmid, $porMidManual)) {
                            $celulaManual = true;
                            break;
                        }
                    }
                    if ($celulaManual) {
                        continue;
                    }
                    $notas = [];
                    foreach ($matrizPorCodigo as $codNota => $mapNota) {
                        if (!is_array($mapNota) || !isset($mapNota[$vmid]) || !is_numeric($mapNota[$vmid])) {
                            continue;
                        }
                        $notas[(string) $codNota] = (float) $mapNota[$vmid];
                    }
                    $refs = $this->codigosReferenciadosNaExpressao($expr, array_merge(array_keys($notas), array_keys($matrizPorCodigo)));
                    $vars = [];
                    foreach ($refs as $ref) {
                        $vars[(string) $ref] = $this->valorNotaGrupoPorCodigo($notas, (string) $ref);
                    }
                    if ($refs === [] && !preg_match('/\d/', $expr)) {
                        continue;
                    }
                    $resultado = $this->avaliarFormula($expr, $vars);
                    if (empty($resultado['ok']) || !isset($resultado['valor']) || !is_numeric($resultado['valor'])) {
                        continue;
                    }
                    $arred = $this->aplicarArredondamentoMaeGrupo((float) $resultado['valor'], $modo, $arredFormula);
                    if ($arred === null) {
                        continue;
                    }
                    $matrizPorCodigo[$codigo][(int) $vmid] = $arred;
                    $vmids[(int) $vmid] = true;
                }
            }
        }
    }

    /**
     * Replica a nota única de Jornadas em todas as linhas da matriz (omitir / substituição / padrão),
     * igual ao passo usado na montagem da tabela. Deve rodar antes de {@see matrizColunaCalculada}
     * para que expressões como (bimestral + jornadas) / 2 enxerguem o valor de jornadas em cada matéria.
     *
     * @param array<int|string, mixed> $componentesRegra
     * @param array<string, array<int, float|null>> $matrizPorCodigo
     * @param array<int|string, mixed> $componentesResultado
     * @param array<int, true> $allMids
     * @param array<int, list<int>> $filhasPorVirtualMid id virtual da área => matérias do grupo
     */
    private function aplicarEspalhamentoJornadasNotaUnicaNaMatriz(
        array $componentesRegra,
        array &$matrizPorCodigo,
        array $componentesResultado,
        array $allMids,
        string $roundMode,
        array $filhasPorVirtualMid = []
    ): void {
        foreach ($componentesRegra as $cJr) {
            $codJr = trim((string) ($cJr['codigo'] ?? ''));
            if ($codJr === '' || ($cJr['source_type'] ?? '') !== 'jornadas') {
                continue;
            }
            $cfgJr = $this->parseJornadasConfigFromComponente($cJr);
            if (($cfgJr['distribuicao_notas'] ?? 'por_materia') !== 'nota_unica_todas_linhas') {
                continue;
            }
            $crJr = null;
            foreach ($componentesResultado as $cr) {
                if (trim((string) ($cr['codigo'] ?? '')) === $codJr) {
                    $crJr = $cr;
                    break;
                }
            }
            if (!$crJr || !is_array($crJr['detalhes'] ?? null)) {
                continue;
            }
            $ng = $crJr['detalhes']['nota_global_jornadas'] ?? null;
            if (!is_numeric($ng)) {
                continue;
            }
            $roundModeJr = $this->resolveRoundModeComponente($cJr, $roundMode);
            $padrao = $this->applyRoundMode((float) $ng, $roundModeJr);
            $omitSet = [];
            $omitRaw = $crJr['detalhes']['nota_unica_omitir_materias'] ?? null;
            if (is_array($omitRaw)) {
                foreach ($omitRaw as $om) {
                    $omitSet[(int) $om] = true;
                }
            } elseif (!empty($cfgJr['nota_unica_omitir_materias'])) {
                foreach ((array) $cfgJr['nota_unica_omitir_materias'] as $om) {
                    $omitSet[(int) $om] = true;
                }
            }
            $substNorm = [];
            $substRaw = $crJr['detalhes']['nota_unica_substituicao_por_materia'] ?? null;
            if (is_array($substRaw)) {
                foreach ($substRaw as $k => $v) {
                    $substNorm[(int) $k] = is_numeric($v) ? (float) $v : null;
                }
            }
            if (!isset($matrizPorCodigo[$codJr]) || !is_array($matrizPorCodigo[$codJr])) {
                $matrizPorCodigo[$codJr] = [];
            }
            $incluirRaw = $cfgJr['nota_unica_incluir_materias'] ?? null;
            $temInclusaoExplicita = is_array($incluirRaw);
            $incluirSet = [];
            if ($temInclusaoExplicita) {
                foreach ($incluirRaw as $im) {
                    $im = (int) $im;
                    if ($im > 0) {
                        $incluirSet[$im] = true;
                    }
                }
            }
            // Sem lista explícita: só matérias que já têm jornada no escopo.
            // Com a lista, a matéria marcada recebe a nota mesmo sem jornada própria.
            $midsComJornada = [];
            foreach ($matrizPorCodigo[$codJr] as $midExistente => $valExistente) {
                $midExistente = (int) $midExistente;
                if ($midExistente > 0 && is_numeric($valExistente)) {
                    $midsComJornada[$midExistente] = true;
                }
            }
            // Só jornadas sem matéria (mid 0): aí a nota global ainda vale para todas as linhas.
            $espalharEmTodas = ($midsComJornada === []);
            $midsAlvo = $allMids;
            if ($temInclusaoExplicita) {
                foreach (array_keys($incluirSet) as $imAlvo) {
                    $midsAlvo[(int) $imAlvo] = true;
                }
            }
            $vistos = [];
            foreach (array_keys($midsAlvo) as $midRep) {
                $midRep = (int) $midRep;
                if (isset($vistos[$midRep])) {
                    continue;
                }
                $vistos[$midRep] = true;
                // A linha da área (id negativo) é resolvida depois, pelas filhas marcadas.
                if ($midRep < 0 && $temInclusaoExplicita) {
                    continue;
                }
                if ($temInclusaoExplicita && !isset($incluirSet[$midRep])) {
                    $matrizPorCodigo[$codJr][$midRep] = null;
                    continue;
                }
                if (!$temInclusaoExplicita && isset($omitSet[$midRep])) {
                    $matrizPorCodigo[$codJr][$midRep] = null;
                    continue;
                }
                if (array_key_exists($midRep, $substNorm)) {
                    $sv = $substNorm[$midRep];
                    $matrizPorCodigo[$codJr][$midRep] = is_numeric($sv)
                        ? $this->applyRoundMode((float) $sv, $roundModeJr)
                        : null;
                    continue;
                }
                if (!$temInclusaoExplicita && !$espalharEmTodas && !isset($midsComJornada[$midRep])) {
                    $matrizPorCodigo[$codJr][$midRep] = null;
                    continue;
                }
                $matrizPorCodigo[$codJr][$midRep] = $padrao;
            }
            if (!$temInclusaoExplicita || $filhasPorVirtualMid === []) {
                continue;
            }
            foreach ($filhasPorVirtualMid as $vmid => $filhas) {
                $vmid = (int) $vmid;
                if ($vmid >= 0) {
                    continue;
                }
                if (array_key_exists($vmid, $substNorm)) {
                    $sv = $substNorm[$vmid];
                    $matrizPorCodigo[$codJr][$vmid] = is_numeric($sv)
                        ? $this->applyRoundMode((float) $sv, $roundModeJr)
                        : null;
                    continue;
                }
                $recebe = false;
                foreach ((array) $filhas as $fid) {
                    if (isset($incluirSet[(int) $fid])) {
                        $recebe = true;
                        break;
                    }
                }
                $matrizPorCodigo[$codJr][$vmid] = $recebe ? $padrao : null;
            }
        }
    }

    /**
     * @return array{
     *   jornada_ids: list<int>,
     *   data_ini: ?string,
     *   data_fim: ?string,
     *   faixas_percentuais: list<array{percentual_min:int, nota:float}>,
     *   distribuicao_notas: string,
     *   nota_unica_omitir_materias: list<int>,
     *   nota_unica_incluir_materias: list<int>|null,
     *   nota_unica_fonte_por_materia: array<int, list<int>>,
     *   nota_unica_fonte_por_grupo: array<string, list<int>>,
     *   traco_abaixo_minimo: bool
     * }
     */
    private function parseJornadasConfigFromComponente(array $componente): array
    {
        $raw = $componente['config_json'] ?? '';
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode(trim((string) $raw), true);
        }
        if (!is_array($decoded) && isset($componente['config']) && is_array($componente['config'])) {
            $decoded = $componente['config'];
        }
        if (!is_array($decoded)) {
            return [
                'jornada_ids' => [],
                'data_ini' => null,
                'data_fim' => null,
                'faixas_percentuais' => [],
                'distribuicao_notas' => 'por_materia',
                'nota_unica_omitir_materias' => [],
                'nota_unica_incluir_materias' => null,
                'nota_unica_fonte_por_materia' => [],
                'nota_unica_fonte_por_grupo' => [],
                'traco_abaixo_minimo' => false,
            ];
        }
        $ids = [];
        foreach ((array) ($decoded['jornada_ids'] ?? []) as $v) {
            $ids[] = (int) $v;
        }
        $ids = array_values(array_unique(array_filter($ids, static function ($id) {
            return $id > 0;
        })));
        $faixas = [];
        foreach ((array) ($decoded['faixas_percentuais'] ?? []) as $faixa) {
            if (!is_array($faixa)) {
                continue;
            }
            $pct = (int) ($faixa['percentual_min'] ?? 0);
            $nota = is_numeric($faixa['nota'] ?? null) ? (float) $faixa['nota'] : null;
            if ($pct < 0 || $pct > 100 || $nota === null) {
                continue;
            }
            $faixas[] = [
                'percentual_min' => $pct,
                'nota' => max(0.0, $nota),
            ];
        }

        $dist = strtolower(trim((string) ($decoded['distribuicao_notas'] ?? '')));
        if ($dist !== 'nota_unica_todas_linhas') {
            $dist = 'por_materia';
        }

        $omitir = [];
        foreach ((array) ($decoded['nota_unica_omitir_materias'] ?? []) as $om) {
            $iom = (int) $om;
            if ($iom !== 0) {
                $omitir[] = $iom;
            }
        }
        $omitir = array_values(array_unique($omitir));

        $incluir = null;
        if (array_key_exists('nota_unica_incluir_materias', $decoded)) {
            $incluir = [];
            foreach ((array) $decoded['nota_unica_incluir_materias'] as $im) {
                $im = (int) $im;
                if ($im > 0) {
                    $incluir[] = $im;
                }
            }
            $incluir = array_values(array_unique($incluir));
        }

        $fontePorMateria = [];
        foreach ((array) ($decoded['nota_unica_fonte_por_materia'] ?? []) as $tk => $list) {
            $tki = (int) $tk;
            if ($tki === 0) {
                continue;
            }
            $idsF = [];
            foreach ((array) $list as $z) {
                $zi = (int) $z;
                if ($zi > 0) {
                    $idsF[] = $zi;
                }
            }
            $idsF = array_values(array_unique($idsF));
            if ($idsF !== []) {
                $fontePorMateria[$tki] = $idsF;
            }
        }

        $fontePorGrupo = [];
        foreach ((array) ($decoded['nota_unica_fonte_por_grupo'] ?? []) as $gk => $list) {
            $gk = trim((string) $gk);
            if ($gk === '') {
                continue;
            }
            $idsG = [];
            foreach ((array) $list as $z) {
                $zi = (int) $z;
                if ($zi > 0) {
                    $idsG[] = $zi;
                }
            }
            $idsG = array_values(array_unique($idsG));
            if ($idsG !== []) {
                $fontePorGrupo[$gk] = $idsG;
            }
        }

        return [
            'jornada_ids' => $ids,
            'data_ini' => $this->normalizarDataYmdOpcional((string) ($decoded['data_ini'] ?? '')),
            'data_fim' => $this->normalizarDataYmdOpcional((string) ($decoded['data_fim'] ?? '')),
            'faixas_percentuais' => $faixas,
            'distribuicao_notas' => $dist,
            'nota_unica_omitir_materias' => $omitir,
            'nota_unica_incluir_materias' => $incluir,
            'nota_unica_fonte_por_materia' => $fontePorMateria,
            'nota_unica_fonte_por_grupo' => $fontePorGrupo,
            'traco_abaixo_minimo' => !empty($decoded['traco_abaixo_minimo']),
        ];
    }

    /**
     * Mescla fontes por grupo (código do group_line) em fontes por virtual_mid,
     * usando a mesma ordem de atribuição de IDs virtuais do agrupamento do boletim.
     *
     * @param array<int|string, mixed> $componentesRegra
     * @param array<int, list<int>> $fontePorMateria
     * @param array<string, list<int>> $fontePorGrupo
     * @return array<int, list<int>>
     */
    private function mergeFonteNotaUnicaJornadasPorGrupo(array $componentesRegra, array $fontePorMateria, array $fontePorGrupo): array
    {
        $out = $fontePorMateria;
        if ($fontePorGrupo === []) {
            return $out;
        }
        $mapaGrupoVm = $this->mapGroupKeysToVirtualMidsFromComponentes($componentesRegra);
        foreach ($fontePorGrupo as $gk => $lista) {
            $gk = trim((string) $gk);
            if ($gk === '' || $lista === []) {
                continue;
            }
            $vmid = (int) ($mapaGrupoVm[$gk] ?? 0);
            if ($vmid >= 0) {
                continue;
            }
            $out[$vmid] = $lista;
        }

        return $out;
    }

    /**
     * @param array<int|string, mixed> $componentesRegra
     * @return array<string, int> chave do grupo → mid virtual negativo
     */
    private function mapGroupKeysToVirtualMidsFromComponentes(array $componentesRegra): array
    {
        $groupMidByKey = [];
        $nextVirtualMid = -1;
        foreach ($componentesRegra as $comp) {
            if (!is_array($comp)) {
                continue;
            }
            $grp = $this->parseGroupLineConfigFromComponente($comp);
            if ($grp === null) {
                continue;
            }
            $gk = trim((string) ($grp['key'] ?? ''));
            if ($gk === '') {
                continue;
            }
            if (!isset($groupMidByKey[$gk])) {
                $groupMidByKey[$gk] = $nextVirtualMid;
                $nextVirtualMid--;
            }
        }

        return $groupMidByKey;
    }

    /**
     * @return array{regra_codigo: string, componente_codigo: string}
     */
    private function parseEventoConfigFromComponente(array $componente): array
    {
        $raw = $componente['config_json'] ?? '';
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode(trim((string) $raw), true);
        }
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $regraCodigo = trim((string) ($decoded['regra_codigo'] ?? ''));
        $componenteCodigo = trim((string) ($decoded['componente_codigo'] ?? ''));
        if (($regraCodigo === '' || $componenteCodigo === '') && isset($componente['config']) && is_array($componente['config'])) {
            $regraCodigo = $regraCodigo !== '' ? $regraCodigo : trim((string) ($componente['config']['regra_codigo'] ?? ''));
            $componenteCodigo = $componenteCodigo !== '' ? $componenteCodigo : trim((string) ($componente['config']['componente_codigo'] ?? ''));
        }

        return [
            'regra_codigo' => $regraCodigo,
            'componente_codigo' => $componenteCodigo,
        ];
    }

    /**
     * @return array{evento_id:int}
     */
    private function parseFaltasConfigFromComponente(array $componente): array
    {
        $raw = $componente['config_json'] ?? '';
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode(trim((string) $raw), true);
        }
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $eventoId = (int) ($decoded['faltas_evento_id'] ?? 0);
        if ($eventoId <= 0 && isset($componente['config']) && is_array($componente['config'])) {
            $eventoId = (int) ($componente['config']['faltas_evento_id'] ?? 0);
        }

        return ['evento_id' => $eventoId];
    }

    /**
     * @param array<int, array<string, mixed>> $linhasRef
     */
    private function resolveEventoComponenteCodigoNasLinhas(array $linhasRef, string $requestedCode, string $fallbackCode): string
    {
        $requestedCode = trim($requestedCode);
        $fallbackCode = trim($fallbackCode);

        $available = [];
        foreach ($linhasRef as $linRef) {
            $notas = is_array($linRef['notas'] ?? null) ? $linRef['notas'] : [];
            foreach (array_keys($notas) as $k) {
                $cod = trim((string) $k);
                if ($cod !== '') {
                    $available[$cod] = true;
                }
            }
        }
        $candidates = array_keys($available);
        if ($candidates === []) {
            return $fallbackCode !== '' ? $fallbackCode : $requestedCode;
        }

        if ($requestedCode !== '') {
            $matched = $this->matchEventoCodigoInCandidates($requestedCode, $candidates);
            if ($matched !== '') {
                return $matched;
            }
        }

        if ($fallbackCode !== '') {
            $matchedFallback = $this->matchEventoCodigoInCandidates($fallbackCode, $candidates);
            if ($matchedFallback !== '') {
                return $matchedFallback;
            }
        }

        return $fallbackCode !== '' ? $fallbackCode : ($requestedCode !== '' ? $requestedCode : (string) ($candidates[0] ?? ''));
    }

    /**
     * @param list<string> $candidates
     */
    private function matchEventoCodigoInCandidates(string $code, array $candidates): string
    {
        $code = trim($code);
        if ($code === '' || $candidates === []) {
            return '';
        }

        foreach ($candidates as $cand) {
            if ($cand === $code) {
                return $cand;
            }
        }
        foreach ($candidates as $cand) {
            if (strtolower($cand) === strtolower($code)) {
                return $cand;
            }
        }

        $normReq = $this->normalizeEventoCodigoToken($code);
        if ($normReq === '') {
            return '';
        }
        foreach ($candidates as $cand) {
            if ($this->normalizeEventoCodigoToken($cand) === $normReq) {
                return $cand;
            }
        }

        $best = '';
        $bestDist = PHP_INT_MAX;
        foreach ($candidates as $cand) {
            $normCand = $this->normalizeEventoCodigoToken($cand);
            if ($normCand === '') {
                continue;
            }
            $dist = levenshtein($normReq, $normCand);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $cand;
            }
        }

        return ($best !== '' && $bestDist <= 2) ? $best : '';
    }

    private function resolveEventoComponenteCodigo(array $refRegra, string $requestedCode): string
    {
        $requestedCode = trim($requestedCode);
        $componentes = is_array($refRegra['componentes'] ?? null) ? $refRegra['componentes'] : [];

        if ($requestedCode !== '') {
            // 1) Match exato
            foreach ($componentes as $compRef) {
                $cod = trim((string) ($compRef['codigo'] ?? ''));
                if ($cod !== '' && $cod === $requestedCode) {
                    return $cod;
                }
            }
            // 2) Match case-insensitive
            foreach ($componentes as $compRef) {
                $cod = trim((string) ($compRef['codigo'] ?? ''));
                if ($cod !== '' && strtolower($cod) === strtolower($requestedCode)) {
                    return $cod;
                }
            }
            // 3) Match normalizado (remove separadores e acentos)
            $normReq = $this->normalizeEventoCodigoToken($requestedCode);
            if ($normReq !== '') {
                foreach ($componentes as $compRef) {
                    $cod = trim((string) ($compRef['codigo'] ?? ''));
                    if ($cod === '') {
                        continue;
                    }
                    if ($this->normalizeEventoCodigoToken($cod) === $normReq) {
                        return $cod;
                    }
                }
                // 4) Match aproximado (pequeno typo)
                $bestCode = '';
                $bestDist = PHP_INT_MAX;
                foreach ($componentes as $compRef) {
                    $cod = trim((string) ($compRef['codigo'] ?? ''));
                    if ($cod === '') {
                        continue;
                    }
                    $normCod = $this->normalizeEventoCodigoToken($cod);
                    if ($normCod === '') {
                        continue;
                    }
                    $dist = levenshtein($normReq, $normCod);
                    if ($dist < $bestDist) {
                        $bestDist = $dist;
                        $bestCode = $cod;
                    }
                }
                if ($bestCode !== '' && $bestDist <= 2) {
                    return $bestCode;
                }
            }

            return $requestedCode;
        }

        $preferidos = ['media_final', 'media-final', 'nota', 'media'];
        $byNorm = [];
        foreach ($componentes as $compRef) {
            $cod = trim((string) ($compRef['codigo'] ?? ''));
            if ($cod === '') {
                continue;
            }
            $norm = str_replace('-', '_', strtolower($cod));
            if (!isset($byNorm[$norm])) {
                $byNorm[$norm] = $cod;
            }
        }
        foreach ($preferidos as $pref) {
            $norm = str_replace('-', '_', strtolower($pref));
            if (isset($byNorm[$norm])) {
                return (string) $byNorm[$norm];
            }
        }
        // Se o evento tiver só um componente, usa ele como fonte padrão.
        if (count($componentes) === 1) {
            $codUnico = trim((string) (($componentes[0]['codigo'] ?? '')));
            if ($codUnico !== '') {
                return $codUnico;
            }
        }
        // Fallback: primeiro código disponível no evento.
        foreach ($componentes as $compRef) {
            $codAny = trim((string) ($compRef['codigo'] ?? ''));
            if ($codAny !== '') {
                return $codAny;
            }
        }
        return 'media_final';
    }

    private function normalizeEventoCodigoToken(string $value): string
    {
        $v = strtolower(trim($value));
        if ($v === '') {
            return '';
        }
        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
            if (is_string($tmp) && $tmp !== '') {
                $v = strtolower($tmp);
            }
        }
        $v = preg_replace('/[^a-z0-9]+/u', '', $v) ?? $v;

        return $v;
    }

    /**
     * @return array{key:string,label:string,mode:string,divisor:float,materias_ids:list<int>,aplicar_em:string,usar_percentual:bool,source_type:string,agrupamento_id:int}|null
     */
    private function parseGroupLineConfigFromComponente(array $componente): ?array
    {
        $decoded = [];
        $raw = $componente['config_json'] ?? '';
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $tmp = json_decode(trim($raw), true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        }
        if (isset($componente['config']) && is_array($componente['config'])) {
            $decoded = array_replace_recursive($decoded, $componente['config']);
        }
        $grp = $decoded['group_line'] ?? null;
        if (!is_array($grp)) {
            return null;
        }
        $enabled = !empty($grp['enabled']);
        if (!$enabled) {
            return null;
        }
        $agrupamentoId = (int) ($grp['agrupamento_id'] ?? 0);
        $cadastro = $agrupamentoId > 0 ? $this->carregarAgrupamentoCadastro($agrupamentoId) : null;
        if ($agrupamentoId > 0 && $cadastro === null) {
            return null;
        }

        $key = $this->slug((string) ($grp['key'] ?? ''));
        $label = trim((string) ($grp['label'] ?? ''));
        $mode = strtolower(trim((string) ($grp['mode'] ?? 'media')));
        if (!in_array($mode, ['media', 'soma'], true)) {
            $mode = 'media';
        }
        $ids = [];
        foreach ((array) ($grp['materias_ids'] ?? []) as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        $divisor = (float) ($grp['divisor'] ?? 0);
        if ($divisor < 0) {
            $divisor = 0;
        }
        $aplicarEm = $this->normalizarGroupLineAplicarEm($grp['aplicar_em'] ?? 'ambos');
        $idsLocais = $ids;
        if (is_array($cadastro) && count((array) ($cadastro['materias_ids'] ?? [])) >= 2) {
            $labelCad = trim((string) ($cadastro['nome'] ?? ''));
            if ($label === '' && $labelCad !== '') {
                $label = $labelCad;
            }
            // Lista do evento (materias_ids) prevalece sobre o cadastro — permite excluir/incluir filha por bimestre.
            if (count($idsLocais) < 2) {
                $ids = array_values(array_map('intval', (array) $cadastro['materias_ids']));
                $modoEvento = strtolower(trim((string) ($grp['modo_padrao'] ?? $grp['mode'] ?? '')));
                if ($modoEvento === 'media' || $modoEvento === 'soma') {
                    $mode = $modoEvento;
                } else {
                    $modeCad = strtolower(trim((string) ($cadastro['modo'] ?? 'media')));
                    $mode = $modeCad === 'soma' ? 'soma' : 'media';
                }
                $divCad = $cadastro['divisor'] ?? null;
                $divisor = ($divCad !== null && $divCad !== '' && (float) $divCad > 0)
                    ? (float) $divCad
                    : 0.0;
                if (trim((string) ($grp['aplicar_em'] ?? '')) === '') {
                    $aplicarEm = $this->normalizarGroupLineAplicarEm($cadastro['aplicar_em'] ?? $aplicarEm);
                }
            }
            if ($key === '' && $labelCad !== '') {
                $key = $this->slug($labelCad);
            }
        }
        if ($key === '') {
            return null;
        }
        if ($label === '') {
            $label = $key;
        }
        if ($ids === []) {
            return null;
        }

        $modos = [];
        foreach ((array) ($grp['modos'] ?? []) as $pecaModo => $modoPeca) {
            $pecaModo = strtolower(trim((string) $pecaModo));
            if ($pecaModo === '' || preg_match('/^[a-z][a-z0-9_]{0,40}$/', $pecaModo) !== 1) {
                continue;
            }
            $modos[$pecaModo] = strtolower(trim((string) $modoPeca)) === 'soma' ? 'soma' : 'media';
            if (count($modos) >= 20) {
                break;
            }
        }
        $modoPadrao = strtolower(trim((string) ($grp['modo_padrao'] ?? '')));
        if ($modoPadrao !== 'media' && $modoPadrao !== 'soma') {
            $modoPadrao = '';
        }

        return [
            'key' => $key,
            'label' => $label,
            'mode' => $mode,
            'modo_padrao' => $modoPadrao,
            'modos' => $modos,
            'divisor' => $divisor,
            'materias_ids' => $ids,
            'aplicar_em' => $aplicarEm,
            'arredondamento' => $this->normalizarArredondamentoGrupo($grp['arredondamento'] ?? 'todos'),
            'usar_percentual' => !empty($componente['usar_percentual']),
            'source_type' => strtolower(trim((string) ($componente['source_type'] ?? 'provas_sistema'))),
            'distribuicao_notas' => strtolower(trim((string) ($decoded['distribuicao_notas'] ?? ''))) === 'nota_unica_todas_linhas'
                ? 'nota_unica_todas_linhas'
                : 'por_materia',
            'agrupamento_id' => $agrupamentoId > 0 ? $agrupamentoId : 0,
            'ocultar_filhas' => !empty($grp['ocultar_filhas']) || !empty($grp['exemplo_sem_filhas']),
        ];
    }

    /**
     * @param mixed $raw
     */
    private function normalizarGroupLineAplicarEm($raw): string
    {
        return strtolower(trim((string) $raw)) === 'boletim' ? 'boletim' : 'ambos';
    }

    private function normalizarDataYmdOpcional(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if (!checkdate($mo, $d, $y)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s)) {
            return $s;
        }
        $ts = strtotime($s);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function buildPeriodoRefFromDateRange(?string $inicioYmd, ?string $fimYmd): string
    {
        $ini = $this->normalizarDataYmdOpcional((string) $inicioYmd);
        $fim = $this->normalizarDataYmdOpcional((string) $fimYmd);
        if ($ini === null || $fim === null) {
            return $this->periodoDefault();
        }

        return 'RANGE:' . $ini . ':' . $fim;
    }

    private function encodeComponenteConfigJsonForSave(string $src, array $componente): ?string
    {
        $groupLine = $this->normalizeGroupLineConfigForSave($componente);
        $layoutMeta = $this->extractLayoutMetaForSave($componente);
        $roundOverride = null;
        if (isset($componente['config']) && is_array($componente['config'])) {
            $rmRaw = strtolower(trim((string) ($componente['config']['round_mode_override'] ?? '')));
            if (in_array($rmRaw, ['none', 'half'], true)) {
                $roundOverride = $rmRaw;
            }
        }

        if ($src === 'calculado') {
            $exp = '';
            $fmOut = [];
            $formulaMode = 'single';
            if (isset($componente['config']) && is_array($componente['config'])) {
                $exp = trim((string) ($componente['config']['expressao'] ?? ''));
                $formulaModeRaw = strtolower(trim((string) ($componente['config']['formula_mode'] ?? '')));
                if ($formulaModeRaw === 'per_materia') {
                    $formulaMode = 'per_materia';
                }
                if (isset($componente['config']['formula_materias']) && is_array($componente['config']['formula_materias'])) {
                    foreach ($componente['config']['formula_materias'] as $midRaw => $exprRaw) {
                        $mid = (int) $midRaw;
                        $exprItem = trim((string) $exprRaw);
                        if ($mid > 0 && $exprItem !== '') {
                            $fmOut[$mid] = substr($exprItem, 0, 500);
                        }
                    }
                }
            }
            if ($exp === '') {
                $exp = trim((string) ($componente['expressao'] ?? ''));
            }
            $agregarNqSave = $this->parseAgregarNqFromComponente($componente);
            if ($exp === '' && $fmOut === [] && $agregarNqSave === []) {
                return null;
            }
            // Se há exceções por matéria, o modo é per_materia mesmo quando o JSON do
            // formulário não traz formula_mode (ex.: telas antigas ou bootstrap só com formula_materias).
            if ($fmOut !== []) {
                $formulaMode = 'per_materia';
            }
            if ($formulaMode !== 'per_materia') {
                $fmOut = [];
            }
            if (strlen($exp) > 500) {
                $exp = substr($exp, 0, 500);
            }
            $payload = [
                'expressao' => $exp,
                'formula_mode' => ($fmOut !== [] ? 'per_materia' : 'single'),
            ];
            if ($fmOut !== []) {
                $payload['formula_materias'] = $fmOut;
            }
            if ($agregarNqSave !== []) {
                $payload['agregar_nq'] = $agregarNqSave;
            }
            $tracoMin = false;
            if (isset($componente['config']) && is_array($componente['config'])) {
                $tracoMin = !empty($componente['config']['traco_abaixo_minimo']);
            }
            if ($tracoMin) {
                $payload['traco_abaixo_minimo'] = true;
            }
            if ($groupLine !== null) {
                $payload['group_line'] = $groupLine;
            }
            if ($layoutMeta !== null) {
                $payload['layout'] = $layoutMeta;
            }
            if ($roundOverride !== null) {
                $payload['round_mode_override'] = $roundOverride;
            }

            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($src === 'evento_boletim') {
            $regraCodigo = '';
            $componenteCodigo = '';
            if (isset($componente['config']) && is_array($componente['config'])) {
                $regraCodigo = trim((string) ($componente['config']['regra_codigo'] ?? ''));
                $componenteCodigo = trim((string) ($componente['config']['componente_codigo'] ?? ''));
            }
            if ($regraCodigo === '') {
                $regraCodigo = trim((string) ($componente['evento_regra_codigo'] ?? ''));
            }
            if ($componenteCodigo === '') {
                $componenteCodigo = trim((string) ($componente['evento_componente_codigo'] ?? ''));
            }
            if ($regraCodigo === '') {
                return null;
            }

            $payload = [
                'regra_codigo' => $regraCodigo,
                'componente_codigo' => $componenteCodigo,
            ];
            if ($groupLine !== null) {
                $payload['group_line'] = $groupLine;
            }
            if ($layoutMeta !== null) {
                $payload['layout'] = $layoutMeta;
            }
            if ($roundOverride !== null) {
                $payload['round_mode_override'] = $roundOverride;
            }

            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($src === 'faltas_evento') {
            $eventoId = 0;
            if (isset($componente['config']) && is_array($componente['config'])) {
                $eventoId = (int) ($componente['config']['faltas_evento_id'] ?? 0);
            }
            if ($eventoId <= 0) {
                $eventoId = (int) ($componente['faltas_evento_id'] ?? 0);
            }
            if ($eventoId <= 0) {
                return null;
            }
            $payload = ['faltas_evento_id' => $eventoId];
            if ($groupLine !== null) {
                $payload['group_line'] = $groupLine;
            }
            if ($layoutMeta !== null) {
                $payload['layout'] = $layoutMeta;
            }
            if ($roundOverride !== null) {
                $payload['round_mode_override'] = $roundOverride;
            }

            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($src !== 'jornadas') {
            $quadroMeta = $this->extractQuadroMetaForSave($componente);
            if ($groupLine === null && $layoutMeta === null && $roundOverride === null && $quadroMeta === []) {
                return null;
            }
            $payload = $quadroMeta;
            if ($groupLine !== null) {
                $payload['group_line'] = $groupLine;
            }
            if ($layoutMeta !== null) {
                $payload['layout'] = $layoutMeta;
            }
            if ($roundOverride !== null) {
                $payload['round_mode_override'] = $roundOverride;
            }
            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $ids = [];
        $from = $componente['jornada_ids'] ?? null;
        if (isset($componente['config']) && is_array($componente['config'])) {
            $from = $componente['config']['jornada_ids'] ?? $from;
        }
        if (is_array($from)) {
            foreach ($from as $v) {
                $ids[] = (int) $v;
            }
        }
        $ids = array_values(array_unique(array_filter($ids, static function ($id) {
            return $id > 0;
        })));
        // Permite escopos grandes de jornadas sem truncar seleção no salvar regra.
        // O campo config_json é TEXT e suporta esse volume.
        if (count($ids) > 2000) {
            $ids = array_slice($ids, 0, 2000);
        }
        $ini = null;
        $fim = null;
        if (isset($componente['config']) && is_array($componente['config'])) {
            $ini = $this->normalizarDataYmdOpcional((string) ($componente['config']['data_ini'] ?? ''));
            $fim = $this->normalizarDataYmdOpcional((string) ($componente['config']['data_fim'] ?? ''));
        }
        $ini = $ini ?? $this->normalizarDataYmdOpcional((string) ($componente['jornada_data_ini'] ?? ''));
        $fim = $fim ?? $this->normalizarDataYmdOpcional((string) ($componente['jornada_data_fim'] ?? ''));
        if ($ini && $fim && $ini > $fim) {
            [$ini, $fim] = [$fim, $ini];
        }
        $payload = [
            'jornada_ids' => $ids,
            'data_ini' => $ini,
            'data_fim' => $fim,
        ];
        $faixasOut = [];
        if (isset($componente['config']) && is_array($componente['config']) && isset($componente['config']['faixas_percentuais']) && is_array($componente['config']['faixas_percentuais'])) {
            foreach ($componente['config']['faixas_percentuais'] as $fItem) {
                if (!is_array($fItem)) {
                    continue;
                }
                $pctMin = (int) ($fItem['percentual_min'] ?? 0);
                $nota = is_numeric($fItem['nota'] ?? null) ? (float) $fItem['nota'] : null;
                if ($pctMin < 0 || $pctMin > 100 || $nota === null) {
                    continue;
                }
                $faixasOut[] = [
                    'percentual_min' => $pctMin,
                    'nota' => max(0.0, $nota),
                ];
            }
        }
        if ($faixasOut !== []) {
            $payload['faixas_percentuais'] = $faixasOut;
        }
        $bimsOut = [];
        if (isset($componente['config']) && is_array($componente['config'])) {
            foreach ((array) ($componente['config']['jornada_bimestres'] ?? []) as $b) {
                $b = (int) $b;
                if ($b >= 1 && $b <= 4) {
                    $bimsOut[] = $b;
                }
            }
        }
        $bimsOut = array_values(array_unique($bimsOut));
        if ($bimsOut !== []) {
            $payload['jornada_bimestres'] = $bimsOut;
        }
        $distNotas = 'por_materia';
        if (isset($componente['config']) && is_array($componente['config'])) {
            $dr = strtolower(trim((string) ($componente['config']['distribuicao_notas'] ?? '')));
            if ($dr === 'nota_unica_todas_linhas') {
                $distNotas = 'nota_unica_todas_linhas';
            }
        }
        if ($distNotas !== 'por_materia') {
            $payload['distribuicao_notas'] = $distNotas;
        }
        if ($distNotas === 'nota_unica_todas_linhas' && isset($componente['config']) && is_array($componente['config'])) {
            $om = [];
            foreach ((array) ($componente['config']['nota_unica_omitir_materias'] ?? []) as $x) {
                $ix = (int) $x;
                if ($ix !== 0) {
                    $om[] = $ix;
                }
            }
            $om = array_values(array_unique($om));
            if ($om !== []) {
                $payload['nota_unica_omitir_materias'] = $om;
            }
            if (array_key_exists('nota_unica_incluir_materias', $componente['config']) && is_array($componente['config']['nota_unica_incluir_materias'])) {
                $incluirSalvar = [];
                foreach ($componente['config']['nota_unica_incluir_materias'] as $im) {
                    $im = (int) $im;
                    if ($im > 0) {
                        $incluirSalvar[] = $im;
                    }
                }
                $payload['nota_unica_incluir_materias'] = array_values(array_unique($incluirSalvar));
                unset($payload['nota_unica_omitir_materias']);
            }
            $fp = [];
            if (isset($componente['config']['nota_unica_fonte_por_materia']) && is_array($componente['config']['nota_unica_fonte_por_materia'])) {
                foreach ($componente['config']['nota_unica_fonte_por_materia'] as $tk => $list) {
                    $tki = (int) $tk;
                    if ($tki === 0) {
                        continue;
                    }
                    $idsF = [];
                    foreach ((array) $list as $z) {
                        $zi = (int) $z;
                        if ($zi > 0) {
                            $idsF[] = $zi;
                        }
                    }
                    $idsF = array_values(array_unique($idsF));
                    if ($idsF !== []) {
                        $fp[(string) $tki] = $idsF;
                    }
                }
            }
            if ($fp !== []) {
                $payload['nota_unica_fonte_por_materia'] = $fp;
            }
            $fg = [];
            if (isset($componente['config']['nota_unica_fonte_por_grupo']) && is_array($componente['config']['nota_unica_fonte_por_grupo'])) {
                foreach ($componente['config']['nota_unica_fonte_por_grupo'] as $gk => $list) {
                    $gk = trim((string) $gk);
                    if ($gk === '') {
                        continue;
                    }
                    $idsG = [];
                    foreach ((array) $list as $z) {
                        $zi = (int) $z;
                        if ($zi > 0) {
                            $idsG[] = $zi;
                        }
                    }
                    $idsG = array_values(array_unique($idsG));
                    if ($idsG !== []) {
                        $fg[$gk] = $idsG;
                    }
                }
            }
            if ($fg !== []) {
                $payload['nota_unica_fonte_por_grupo'] = $fg;
            }
        }
        if ($groupLine !== null) {
            $payload['group_line'] = $groupLine;
        }
        if ($layoutMeta !== null) {
            $payload['layout'] = $layoutMeta;
        }
        if (isset($componente['config']) && is_array($componente['config']) && !empty($componente['config']['traco_abaixo_minimo'])) {
            $payload['traco_abaixo_minimo'] = true;
        }
        if ($roundOverride !== null) {
            $payload['round_mode_override'] = $roundOverride;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{group:string,type:string}|null
     */
    private function extractLayoutMetaForSave(array $componente): ?array
    {
        $group = '';
        $type = '';
        if (isset($componente['config']) && is_array($componente['config'])) {
            $group = strtolower(trim((string) ($componente['config']['layout_group'] ?? '')));
            $type = strtolower(trim((string) ($componente['config']['layout_type'] ?? '')));
        }
        if ($group === '') {
            $group = strtolower(trim((string) ($componente['layout_group'] ?? '')));
        }
        if ($type === '') {
            $type = strtolower(trim((string) ($componente['layout_type'] ?? '')));
        }
        $allowedTypes = BoletimQuadroLayoutHelper::tiposPermitidos();
        if (!BoletimQuadroLayoutHelper::grupoLayoutEhValido($group)) {
            $group = '';
        }
        if (!in_array($type, $allowedTypes, true)) {
            $type = '';
        }
        if ($group === '' && $type === '') {
            return null;
        }
        $label = '';
        if (isset($componente['config']) && is_array($componente['config'])) {
            $label = trim((string) ($componente['config']['layout_group_label'] ?? ''));
        }
        if ($label === '') {
            $label = trim((string) ($componente['layout_group_label'] ?? ''));
        }
        $out = ['group' => $group, 'type' => ($type !== '' ? $type : 'other')];
        if ($label !== '') {
            $out['label'] = $label;
        }

        return $out;
    }

    /**
     * @return array{group:string,type:string}
     */
    private function parseLayoutMetaFromComponente(array $componente): array
    {
        $decoded = [];
        $raw = $componente['config_json'] ?? '';
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $tmp = json_decode(trim($raw), true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        }
        if (isset($componente['config']) && is_array($componente['config'])) {
            $decoded = array_replace_recursive($decoded, $componente['config']);
        }
        $layout = is_array($decoded['layout'] ?? null) ? $decoded['layout'] : [];
        $group = strtolower(trim((string) ($layout['group'] ?? $decoded['layout_group'] ?? '')));
        $type = strtolower(trim((string) ($layout['type'] ?? $decoded['layout_type'] ?? '')));
        $allowedTypes = BoletimQuadroLayoutHelper::tiposPermitidos();
        if (!BoletimQuadroLayoutHelper::grupoLayoutEhValido($group)) {
            $group = '';
        }
        if (!in_array($type, $allowedTypes, true)) {
            $type = '';
        }

        return ['group' => $group, 'type' => ($type !== '' ? $type : 'other')];
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeComponenteConfig(array $componente): array
    {
        $decoded = [];
        $raw = $componente['config_json'] ?? '';
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $tmp = json_decode(trim($raw), true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        }
        if (isset($componente['config']) && is_array($componente['config'])) {
            $decoded = array_replace_recursive($decoded, $componente['config']);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Garante que cada coluna sN resolva provas como no assistente:
     * tipo Semanal + semana no config, sem blocos_ids fixos.
     *
     * @param list<array<string,mixed>> $novos
     * @param list<array<string,mixed>> $originais
     * @return list<array<string,mixed>>
     */
    private function enriquecerSemanasComFonteSemanal(array $novos, array $originais): array
    {
        $template = null;
        $tipoTpl = 0;
        $tipoNomeTpl = '';
        $cfgTplExtra = [];
        foreach (array_merge($originais, $novos) as $c) {
            if (!is_array($c)) {
                continue;
            }
            if ($template === null && $this->componenteEhFonteSemanalSalva($c)) {
                $template = $c;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (!BoletimQuadroLayoutHelper::codigoEhSemana($cod) && $cod !== 'semanal' && $cod !== 'prova_semanal') {
                continue;
            }
            $cfgC = $this->decodeComponenteConfig($c);
            $t = (int) ($cfgC['tipo_avaliacao_id'] ?? $c['tipo_avaliacao_id'] ?? 0);
            if ($t > 0 && $tipoTpl <= 0) {
                $tipoTpl = $t;
                $tipoNomeTpl = trim((string) ($cfgC['tipo_avaliacao_nome'] ?? $c['tipo_avaliacao_nome'] ?? ''));
                foreach (['prova_bimestres', 'grupo_regras_tipo_id', 'grupo_regras_marca_id'] as $chave) {
                    if (!empty($cfgC[$chave])) {
                        $cfgTplExtra[$chave] = $cfgC[$chave];
                    }
                }
            }
        }
        if ($template !== null) {
            $cfgTpl = $this->decodeComponenteConfig($template);
            if ($tipoTpl <= 0) {
                $tipoTpl = (int) ($cfgTpl['tipo_avaliacao_id'] ?? $template['tipo_avaliacao_id'] ?? 0);
                $tipoNomeTpl = trim((string) ($cfgTpl['tipo_avaliacao_nome'] ?? $template['tipo_avaliacao_nome'] ?? ''));
            }
            foreach (['prova_bimestres', 'grupo_regras_tipo_id', 'grupo_regras_marca_id'] as $chave) {
                if (empty($cfgTplExtra[$chave]) && !empty($cfgTpl[$chave])) {
                    $cfgTplExtra[$chave] = $cfgTpl[$chave];
                }
            }
        }
        if ($tipoTpl <= 0) {
            $cat = $this->resolverTipoAvaliacaoSemanalCatalogo();
            $tipoTpl = (int) ($cat['id'] ?? 0);
            if ($tipoNomeTpl === '' && !empty($cat['nome'])) {
                $tipoNomeTpl = (string) $cat['nome'];
            }
        }
        if ($tipoTpl <= 0) {
            return $novos;
        }

        $out = [];
        foreach ($novos as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (!BoletimQuadroLayoutHelper::codigoEhSemana($cod)) {
                $out[] = $c;
                continue;
            }
            $cfg = $this->decodeComponenteConfig($c);
            $cfg['tipo_avaliacao_id'] = $tipoTpl;
            if ($tipoNomeTpl !== '') {
                $cfg['tipo_avaliacao_nome'] = $tipoNomeTpl;
            }
            // Só prova_bimestres — grupo_regras_* em sN desvia o resolver e zera N/Q.
            if (empty($cfg['prova_bimestres']) && !empty($cfgTplExtra['prova_bimestres'])) {
                $cfg['prova_bimestres'] = $cfgTplExtra['prova_bimestres'];
            }
            unset($cfg['grupo_regras_tipo_id'], $cfg['grupo_regras_marca_id'], $cfg['grupo_regras_notas_id']);
            if (!isset($cfg['semana']) || (int) $cfg['semana'] <= 0) {
                if (preg_match('/^s([1-9]|[1-9]\d)$/', $cod, $mSem)) {
                    $cfg['semana'] = (int) $mSem[1];
                }
            }
            $cfg['layout_type'] = $cfg['layout_type'] ?? 'semana_nq';

            $manualBlocos = !empty($cfg['blocos_ids_manual']);
            if (!$manualBlocos) {
                // Mesmo contrato do assistente (componenteSemanaQuadro): busca por tipo+semana.
                $c['blocos_ids'] = '';
                unset($c['bloco_id'], $cfg['blocos_ids']);
                $c['filtro_titulo'] = '';
            }
            $c['usar_percentual'] = 1;
            $c['materia_unica'] = 1;
            if (empty($c['materia_unica_modo'])) {
                $c['materia_unica_modo'] = 'soma';
            }
            $c['tipo_avaliacao_id'] = $tipoTpl;
            if ($tipoNomeTpl !== '') {
                $c['tipo_avaliacao_nome'] = $tipoNomeTpl;
            }
            $c['config'] = $cfg;
            $c['config_json'] = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $out[] = $c;
        }

        return $out;
    }

    /**
     * @return array{id:int,nome:string}
     */
    private function resolverTipoAvaliacaoSemanalCatalogo(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $cache = ['id' => 0, 'nome' => ''];
        try {
            if (!class_exists('ExamEvaluationType', false)) {
                require_once dirname(__DIR__, 2) . '/Models/Exams/ExamEvaluationType.php';
            }
            if (!class_exists('ExamEvaluationType', false)) {
                return $cache;
            }
            $fallbackNome = null;
            foreach ((new ExamEvaluationType())->getAllActive() as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $chave = strtolower(trim((string) ($row['chave_quadro'] ?? '')));
                $nome = trim((string) ($row['nome'] ?? ''));
                if ($chave === 'semanal') {
                    $cache = ['id' => $id, 'nome' => $nome !== '' ? $nome : 'Semanal'];
                    break;
                }
                if ($fallbackNome === null && str_contains(mb_strtolower($nome, 'UTF-8'), 'semanal')) {
                    $fallbackNome = ['id' => $id, 'nome' => $nome !== '' ? $nome : 'Semanal'];
                }
            }
            if (($cache['id'] ?? 0) <= 0 && is_array($fallbackNome)) {
                $cache = $fallbackNome;
            }
        } catch (Throwable $e) {
            error_log('BoletimConfig tipo semanal catálogo: ' . $e->getMessage());
        }

        return $cache;
    }

    /**
     * @param array<string,mixed> $comp
     */
    private function componenteContaComoFalta(array $comp): bool
    {
        if ((string) ($comp['source_type'] ?? '') === 'faltas_evento') {
            return true;
        }
        $cfg = is_array($comp['config'] ?? null) ? $comp['config'] : [];
        $lt = strtolower(trim((string) ($cfg['layout_type'] ?? ($comp['layout_type'] ?? ''))));

        return $lt === 'faltas';
    }

    /**
     * Evento de Notas sem bloco de faltas: puxa o lançamento do mesmo ano/bimestre.
     *
     * @param list<array<string,mixed>> $componentes
     * @return list<array<string,mixed>>
     */
    private function anexarFaltasEventoNotasSeFaltar(array $regra, array $componentes): array
    {
        if (strtolower(trim((string) ($regra['exibir_em'] ?? ''))) !== 'notas') {
            return $componentes;
        }
        $bim = (int) ($regra['bimestre'] ?? 0);
        $ano = (int) ($regra['ano_letivo'] ?? 0);
        if ($bim < 1 || $bim > 4) {
            return $componentes;
        }
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $src = (string) ($c['source_type'] ?? '');
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $lt = strtolower(trim((string) ($cfg['layout_type'] ?? ($c['layout_type'] ?? ''))));
            if ($src === 'faltas_evento' || $lt === 'faltas' || str_contains($cod, 'falt')) {
                return $componentes;
            }
        }
        $eventoId = (new SchoolAbsence())->idEventoPorAnoBimestre($ano, $bim);
        if ($eventoId <= 0) {
            return $componentes;
        }
        $grupo = 'b' . $bim;
        $componentes[] = [
            'codigo' => 'faltas',
            'nome' => 'Faltas',
            'source_type' => 'faltas_evento',
            'calc_type' => 'media',
            'peso' => 1,
            'filtro_titulo' => '',
            'blocos_ids' => [],
            'materias_ids' => [],
            'usar_percentual' => 0,
            'escala_max' => 999,
            'obrigatorio' => 0,
            'config' => [
                'faltas_evento_id' => $eventoId,
                'layout_group' => $grupo,
                'layout_type' => 'faltas',
            ],
            'config_json' => json_encode([
                'faltas_evento_id' => $eventoId,
                'layout_group' => $grupo,
                'layout_type' => 'faltas',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        return $componentes;
    }

    /**
     * Se o evento tem "Prova semanal" genérica, a simulação/boletim usa o quadro S1–S8 (N/Q).
     *
     * @param array<string,mixed> $regra
     * @param list<array<string,mixed>> $componentes
     * @return array{regra:array<string,mixed>,componentes:list<array<string,mixed>>}
     */
    private function expandirRegraQuadroSemanalNaSimulacao(array $regra, array $componentes): array
    {
        if ($componentes === []) {
            return ['regra' => $regra, 'componentes' => $componentes];
        }
        $jaQuadro = BoletimQuadroLayoutHelper::componentesJaSaoQuadro($componentes);
        $temSemanal = BoletimQuadroLayoutHelper::componentesTemPecaSemanal($componentes);
        if (!$jaQuadro && !$temSemanal) {
            return ['regra' => $regra, 'componentes' => $componentes];
        }

        $codigoSemanal = 'semanal';
        foreach ($componentes as $c) {
            if (!is_array($c) || !BoletimQuadroLayoutHelper::componentesTemPecaSemanal([$c])) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod !== '' && !BoletimQuadroLayoutHelper::codigoEhSemana($cod)) {
                $codigoSemanal = $cod;
                break;
            }
        }

        $semanas = $this->semanasQuadroNaSimulacao($this->grupoRegrasNotasIdDaRegra($regra));
        $novos = BoletimQuadroLayoutHelper::expandirComponentesParaQuadroSemanal(
            $componentes,
            $semanas['a'],
            $semanas['b']
        );
        $novos = $this->enriquecerSemanasComFonteSemanal($novos, $componentes);
        $formula = trim((string) ($regra['formula_final'] ?? ''));
        if ($formula !== '') {
            $regra['formula_final'] = BoletimQuadroLayoutHelper::reescreverCodigoSemanalNaFormula($formula, $codigoSemanal);
        } else {
            $codigos = [];
            foreach ($novos as $cN) {
                $cn = strtolower(trim((string) ($cN['codigo'] ?? '')));
                if ($cn !== '') {
                    $codigos[$cn] = true;
                }
            }
            if (isset($codigos['media_final'])) {
                $regra['formula_final'] = 'media_final';
            } elseif (isset($codigos['media_bim'])) {
                $regra['formula_final'] = 'media_bim';
            } elseif (isset($codigos['media'])) {
                $regra['formula_final'] = 'media';
            }
        }
        $regra['componentes'] = $novos;

        return ['regra' => $regra, 'componentes' => $novos];
    }

    /**
     * @return array{a:list<int>,b:list<int>}
     */
    private function semanasQuadroNaSimulacao(int $grupoId = 0): array
    {
        if (isset($this->semanasQuadroCache[$grupoId])) {
            return $this->semanasQuadroCache[$grupoId];
        }
        $a = [];
        $b = [];
        $path = dirname(__DIR__, 2) . '/Modulos/grupos-regras-notas/Services/GrupoRegrasNotasService.php';
        if (!class_exists('GrupoRegrasNotasService', false) && is_file($path)) {
            require_once $path;
        }
        if (!class_exists('GrupoRegrasNotasService', false)) {
            return $this->semanasQuadroCache[$grupoId] = ['a' => $a, 'b' => $b];
        }
        try {
            $sem = (new GrupoRegrasNotasService())->semanasQuadroPadrao($grupoId > 0 ? $grupoId : null);
            $sa = is_array($sem['a'] ?? null) ? $sem['a'] : $a;
            $sb = is_array($sem['b'] ?? null) ? $sem['b'] : $b;
            $a = array_values(array_map('intval', $sa));
            $b = array_values(array_map('intval', $sb));
        } catch (Throwable $e) {
            error_log('BoletimConfig semanas quadro: ' . $e->getMessage());
        }

        return $this->semanasQuadroCache[$grupoId] = ['a' => $a, 'b' => $b];
    }

    private function parseSemanaFromComponente(array $componente): int
    {
        $cfg = $this->decodeComponenteConfig($componente);
        $s = (int) ($cfg['semana'] ?? $componente['semana'] ?? 0);
        if ($s >= 1 && $s <= BoletimQuadroLayoutHelper::SEMANA_MAX) {
            return $s;
        }
        $cod = strtolower(trim((string) ($componente['codigo'] ?? '')));
        if (preg_match('/^s([1-9]|[1-9]\d)$/', $cod, $m)) {
            $s = (int) $m[1];
            if ($s >= 1 && $s <= BoletimQuadroLayoutHelper::SEMANA_MAX) {
                return $s;
            }
        }

        return 0;
    }

    private function parseGrupoRegrasIdFromComponente(array $componente, string $chave): int
    {
        $cfg = $this->decodeComponenteConfig($componente);
        $id = (int) ($cfg[$chave] ?? $componente[$chave] ?? 0);

        return $id > 0 ? $id : 0;
    }

    /**
     * Bimestres marcados na peça. Se a coluna não tiver, usa o bimestre do evento.
     *
     * @param array<string,mixed> $componente
     * @param array<string,mixed> $regra
     * @return list<int>
     */
    private function bimestresDoComponenteOuRegra(array $componente, array $regra): array
    {
        $bimestres = $this->parseProvaBimestresFromComponente($componente);
        if ($bimestres !== []) {
            return $bimestres;
        }
        $bimestre = (int) ($regra['bimestre'] ?? 0);
        if ($bimestre >= 1 && $bimestre <= 4) {
            return [$bimestre];
        }

        return [];
    }

    /**
     * Mesma lista da montagem: ano do evento, bimestre marcado e semana do quadro.
     * Evento sem bimestre não entra quando a peça já tem bimestre — a lista marcada também não mostra.
     *
     * @param list<int> $bimestres
     * @return list<int>
     */
    private function blocosSemanaComoAssistente(
        int $tipoAvaliacaoId,
        int $semana,
        ?string $inicio,
        ?string $fim,
        array $bimestres,
        int $anoLetivo = 0
    ): array {
        $bims = [];
        foreach ($bimestres as $bimestre) {
            $n = (int) $bimestre;
            if ($n >= 1 && $n <= 4 && !in_array($n, $bims, true)) {
                $bims[] = $n;
            }
        }
        sort($bims);
        if (!class_exists('BoletimAssistenteFerramentas', false)) {
            require_once dirname(__DIR__, 2) . '/Services/BoletimAssistenteFerramentas.php';
        }
        $ferramentas = new BoletimAssistenteFerramentas($this->boletimConfig);
        if ($anoLetivo < 2000 || $anoLetivo > 2100) {
            $anoLetivo = $ferramentas->anoLetivoPadrao();
        }
        $chave = $tipoAvaliacaoId . '|' . $semana . '|' . (string) $inicio . '|' . (string) $fim
            . '|' . implode(',', $bims) . '|' . $anoLetivo;
        if (isset($this->blocosSemanaAssistenteCache[$chave])) {
            return $this->blocosSemanaAssistenteCache[$chave];
        }
        $resolvido = $ferramentas->resolverBlocosPorTipo(
            $tipoAvaliacaoId,
            $inicio,
            $fim,
            4000,
            $semana,
            $bims,
            $anoLetivo
        );
        $ids = [];
        foreach ((array) ($resolvido['eventos'] ?? []) as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $id = (int) ($ev['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($bims !== []) {
                $bimEv = (int) ($ev['bimestre'] ?? 0);
                if (!in_array($bimEv, $bims, true)) {
                    continue;
                }
            }
            $ids[$id] = $id;
        }

        return $this->blocosSemanaAssistenteCache[$chave] = array_values($ids);
    }

    /**
     * Resolve eventos de prova da coluna: IDs do grupo (tipo+marca) têm prioridade sobre semana.
     *
     * @param list<int> $blocoIds
     * @param list<int> $bimestresComp
     * @return array{bloco_ids: list<int>, forcada: bool}
     */
    private function resolverBlocosQuadroDoComponente(
        array $componente,
        array $blocoIds,
        ?string $inicio,
        ?string $fim,
        array $bimestresComp,
        int $anoLetivo = 0
    ): array {
        // Coluna sN: sempre tipo + semana (igual ao assistente). grupo_regras_* sem filtro
        // de semana misturava/zerava S1–S8 na simulação da home.
        $semanaComp = $this->parseSemanaFromComponente($componente);
        $tipoAvaliacaoComp = $this->parseTipoAvaliacaoIdFromComponente($componente);
        $semanaForcada = $semanaComp >= 1 && $semanaComp <= BoletimQuadroLayoutHelper::SEMANA_MAX;
        if ($semanaForcada && $tipoAvaliacaoComp <= 0) {
            $tipoAvaliacaoComp = (int) ($this->resolverTipoAvaliacaoSemanalCatalogo()['id'] ?? 0);
        }

        if (!$semanaForcada) {
            $tipoGr = $this->parseGrupoRegrasIdFromComponente($componente, 'grupo_regras_tipo_id');
            $marcaGr = $this->parseGrupoRegrasIdFromComponente($componente, 'grupo_regras_marca_id');
            if ($tipoGr > 0 || $marcaGr > 0) {
                if ($blocoIds !== []) {
                    $filtrados = $this->boletimConfig->filtrarBlocoIdsPorGrupoRegras($blocoIds, $tipoGr, $marcaGr);
                    if ($filtrados !== []) {
                        return ['bloco_ids' => $filtrados, 'forcada' => true];
                    }
                }
                $buscados = $this->boletimConfig->buscarBlocoIdsPorGrupoRegras(
                    $tipoGr,
                    $marcaGr,
                    $inicio,
                    $fim,
                    $bimestresComp
                );

                return ['bloco_ids' => $buscados, 'forcada' => true];
            }
            if ($blocoIds !== [] && $bimestresComp !== []) {
                $blocoIds = $this->boletimConfig->filtrarBlocoIdsPorBimestres($blocoIds, $bimestresComp);
            }
            if ($blocoIds === [] && $tipoAvaliacaoComp > 0) {
                $buscados = $this->boletimConfig->buscarBlocoIdsPorTipoESemana(
                    $tipoAvaliacaoComp,
                    0,
                    $inicio,
                    $fim,
                    $bimestresComp
                );
                if ($buscados === [] && ($inicio !== null || $fim !== null)) {
                    $buscados = $this->boletimConfig->buscarBlocoIdsPorTipoESemana(
                        $tipoAvaliacaoComp,
                        0,
                        null,
                        null,
                        $bimestresComp
                    );
                }

                return ['bloco_ids' => $buscados, 'forcada' => true];
            }

            return ['bloco_ids' => $blocoIds, 'forcada' => $blocoIds !== []];
        }

        $cfgSemana = $this->decodeComponenteConfig($componente);
        $manualSemana = !empty($cfgSemana['blocos_ids_manual']);
        if (!$manualSemana && $tipoAvaliacaoComp > 0) {
            return [
                'bloco_ids' => $this->blocosSemanaComoAssistente($tipoAvaliacaoComp, $semanaComp, $inicio, $fim, $bimestresComp, $anoLetivo),
                'forcada' => true,
            ];
        }
        if ($manualSemana) {
            return ['bloco_ids' => $blocoIds, 'forcada' => true];
        }
        if ($blocoIds !== []) {
            $filtradosSemana = $this->boletimConfig->filtrarBlocoIdsPorSemana($blocoIds, $semanaComp);
            if ($bimestresComp !== [] && $filtradosSemana !== []) {
                $filtradosSemana = $this->boletimConfig->filtrarBlocoIdsPorBimestres($filtradosSemana, $bimestresComp);
            }
            if ($filtradosSemana !== []) {
                return ['bloco_ids' => $filtradosSemana, 'forcada' => true];
            }
        }
        if ($tipoAvaliacaoComp > 0) {
            return [
                'bloco_ids' => $this->blocosSemanaComoAssistente($tipoAvaliacaoComp, $semanaComp, $inicio, $fim, $bimestresComp, $anoLetivo),
                'forcada' => true,
            ];
        }

        return ['bloco_ids' => [], 'forcada' => true];
    }

    /**
     * Peça sem semana: usa a nota_final já fechada no Tipo de Nota.
     *
     * @param array<string,mixed> $componente
     * @param array<string,mixed> $regra
     * @param array<string,mixed> $detalhes
     * @param array<string, array<int, float|null>> $matrizPorCodigo
     * @param array<int, string> $materiaNomesPorId
     */
    private function preencherComponenteComNotaFinalTipo(
        array $componente,
        int $alunoId,
        array $regra,
        string $periodoRef,
        string $codigo,
        string $roundMode,
        &$valor,
        array &$detalhes,
        array &$matrizPorCodigo,
        array &$materiaNomesPorId
    ): bool {
        if ($this->parseSemanaFromComponente($componente) > 0) {
            return false;
        }
        $tipoId = $this->parseTipoAvaliacaoIdFromComponente($componente);
        if ($tipoId <= 0) {
            return false;
        }
        $svcPath = __DIR__ . '/../../Services/TipoNotaRegraService.php';
        if (!is_file($svcPath)) {
            return false;
        }
        require_once $svcPath;
        $svc = new TipoNotaRegraService();
        $tipo = $svc->tipos()->findById($tipoId);
        if (!$svc->tipoFechaNotaFinal($tipo) || !$svc->finais()->tabelaPronta()) {
            return false;
        }
        $aluno = $this->buscarAluno($alunoId);
        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        $ano = (int) ($regra['ano_letivo'] ?? 0);
        $periodo = (int) ($regra['bimestre'] ?? 0);
        if ($ano <= 0 && preg_match('/^(\d{4})-B([1-4])$/', $periodoRef, $m)) {
            $ano = (int) $m[1];
            $periodo = (int) $m[2];
        }
        $map = !empty($componente['materia_unica'])
            ? $svc->notasPorMateriaForcandoCriterioProfessores($tipoId, $alunoId, $turmaId, $ano, $periodo, 'soma')
            : $svc->notasFinaisDoAluno($tipoId, $alunoId, $turmaId, $ano, $periodo);
        if ($map === [] && !empty($componente['materia_unica'])) {
            $map = $svc->notasFinaisDoAluno($tipoId, $alunoId, $turmaId, $ano, $periodo);
        }
        if ($map === []) {
            return false;
        }
        $materiasFiltro = $this->parseMateriasIdsFromComponente($componente);
        $materiaFiltro = (int) ($componente['materia_id'] ?? 0);
        if ($materiasFiltro !== []) {
            $permitidos = array_fill_keys($materiasFiltro, true);
            $map = array_filter($map, static function ($nota, $mid) use ($permitidos) {
                return isset($permitidos[(int) $mid]);
            }, ARRAY_FILTER_USE_BOTH);
        } elseif ($materiaFiltro > 0) {
            $map = array_filter($map, static function ($nota, $mid) use ($materiaFiltro) {
                return (int) $mid === $materiaFiltro;
            }, ARRAY_FILTER_USE_BOTH);
        }
        if ($map === []) {
            return false;
        }
        $periodoBlocos = $periodo;
        if ($periodoBlocos <= 0 && preg_match('/^(\d{4})-B([1-4])$/', $periodoRef, $mPeriodo)) {
            $periodoBlocos = (int) $mPeriodo[2];
            if ($ano <= 0) {
                $ano = (int) $mPeriodo[1];
            }
        }
        $map = $this->completarMapaNotaUnicaDoTipo(
            $map,
            $componente,
            $regra,
            $materiasFiltro,
            $materiaNomesPorId,
            $periodoBlocos,
            $ano
        );
        $roundModeComp = $this->resolveRoundModeComponente($componente, $roundMode);
        $matrizPorCodigo[$codigo] = $this->applyRoundModeToMateriaMap($map, $roundModeComp);
        $lista = [];
        foreach ($matrizPorCodigo[$codigo] as $mid => $v) {
            if (!is_numeric($v)) {
                continue;
            }
            $lista[] = [
                'valor' => (float) $v,
                'materia_id' => (int) $mid,
                'materia_nome' => (string) ($materiaNomesPorId[(int) $mid] ?? ''),
            ];
        }
        $valor = $this->applyRoundMode($this->agruparNotas($lista, 'media'), $roundModeComp);
        $detalhes['nota_final_tipo'] = $tipoId;
        $detalhes['qtd_materias'] = count($map);

        return true;
    }

    private function parseTipoAvaliacaoIdFromComponente(array $componente): int
    {
        $cfg = $this->decodeComponenteConfig($componente);
        $id = (int) ($cfg['tipo_avaliacao_id'] ?? $componente['tipo_avaliacao_id'] ?? 0);

        return $id > 0 ? $id : 0;
    }

    /** @return list<int> */
    private function parseProvaBimestresFromComponente(array $componente): array
    {
        $cfg = $this->decodeComponenteConfig($componente);
        $out = [];
        foreach ((array) ($cfg['prova_bimestres'] ?? []) as $b) {
            $n = (int) $b;
            if ($n >= 1 && $n <= 4 && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function parseAgregarNqFromComponente(array $componente): array
    {
        $cfg = $this->decodeComponenteConfig($componente);
        $raw = $cfg['agregar_nq'] ?? $componente['agregar_nq'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/[,\s;]+/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $cod = strtolower(trim((string) $v));
            $cod = preg_replace('/[^a-z0-9_]+/', '_', $cod) ?? '';
            $cod = trim($cod, '_');
            if ($cod !== '') {
                $out[] = $cod;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<string,mixed>
     */
    private function extractQuadroMetaForSave(array $componente): array
    {
        $payload = [];
        $semana = $this->parseSemanaFromComponente($componente);
        if ($semana > 0) {
            $payload['semana'] = $semana;
        }
        $tipoId = $this->parseTipoAvaliacaoIdFromComponente($componente);
        if ($tipoId > 0) {
            $payload['tipo_avaliacao_id'] = $tipoId;
        }
        $cfg = $this->decodeComponenteConfig($componente);
        $tipoNome = trim((string) ($cfg['tipo_avaliacao_nome'] ?? $componente['tipo_avaliacao_nome'] ?? ''));
        if ($tipoNome !== '') {
            $payload['tipo_avaliacao_nome'] = $tipoNome;
        }
        $bimsProva = $this->parseProvaBimestresFromComponente($componente);
        if ($bimsProva !== []) {
            $payload['prova_bimestres'] = $bimsProva;
        }
        $grupoRegrasId = $this->parseGrupoRegrasIdFromComponente($componente, 'grupo_regras_notas_id');
        if ($grupoRegrasId > 0) {
            $payload['grupo_regras_notas_id'] = $grupoRegrasId;
        }
        $tipoRegrasId = $this->parseGrupoRegrasIdFromComponente($componente, 'grupo_regras_tipo_id');
        if ($tipoRegrasId > 0) {
            $payload['grupo_regras_tipo_id'] = $tipoRegrasId;
        }
        $marcaRegrasId = $this->parseGrupoRegrasIdFromComponente($componente, 'grupo_regras_marca_id');
        if ($marcaRegrasId > 0) {
            $payload['grupo_regras_marca_id'] = $marcaRegrasId;
        }
        $cfgManual = $this->decodeComponenteConfig($componente);
        if (!empty($cfgManual['blocos_ids_manual'])) {
            $payload['blocos_ids_manual'] = 1;
        }

        return $payload;
    }

    /**
     * Média 0–escala a partir da soma de acertos/questões de várias colunas (quadro semanal).
     *
     * @param list<string> $codigos
     * @param array<string, array<int, array{acertos?:int,total?:int}>> $statsPorCodigo
     * @return array<int, float>
     */
    private function matrizColunaAgregarNq(array $codigos, array $statsPorCodigo, float $escala): array
    {
        $mids = [];
        foreach ($codigos as $cod) {
            foreach (array_keys($statsPorCodigo[$cod] ?? []) as $mid) {
                $mids[(int) $mid] = true;
            }
        }
        $out = [];
        foreach (array_keys($mids) as $mid) {
            $n = 0;
            $q = 0;
            foreach ($codigos as $cod) {
                $st = $statsPorCodigo[$cod][$mid] ?? null;
                if (!is_array($st)) {
                    continue;
                }
                $n += max(0, (int) ($st['acertos'] ?? 0));
                $q += max(0, (int) ($st['total'] ?? 0));
            }
            if ($q > 0) {
                $out[(int) $mid] = ($n / $q) * $escala;
            }
        }

        return $out;
    }

    /**
     * @return array{enabled:bool,key:string,label:string,mode:string,divisor:float,materias_ids:list<int>,aplicar_em:string,agrupamento_id?:int}|null
     */
    private function normalizeGroupLineConfigForSave(array $componente): ?array
    {
        $cfg = [];
        if (isset($componente['config']) && is_array($componente['config'])) {
            $cfg = $componente['config'];
        }
        $grp = $cfg['group_line'] ?? ($componente['group_line'] ?? null);
        if (!is_array($grp) || empty($grp['enabled'])) {
            return null;
        }
        $agrupamentoId = (int) ($grp['agrupamento_id'] ?? 0);
        $cadastro = $agrupamentoId > 0 ? $this->carregarAgrupamentoCadastro($agrupamentoId) : null;
        if ($agrupamentoId > 0 && $cadastro === null) {
            return null;
        }
        if (is_array($cadastro) && count((array) ($cadastro['materias_ids'] ?? [])) >= 2) {
            if (trim((string) ($grp['key'] ?? '')) === '') {
                $grp['key'] = (string) ($cadastro['nome'] ?? '');
            }
            if (trim((string) ($grp['label'] ?? '')) === '') {
                $grp['label'] = (string) ($cadastro['nome'] ?? '');
            }
            $idsLocais = [];
            foreach ((array) ($grp['materias_ids'] ?? []) as $v) {
                $id = (int) $v;
                if ($id > 0) {
                    $idsLocais[] = $id;
                }
            }
            $idsLocais = array_values(array_unique($idsLocais));
            // Só herda do cadastro se o evento não trouxe lista própria.
            if (count($idsLocais) < 2) {
                $grp['materias_ids'] = $cadastro['materias_ids'];
                $modoLocal = strtolower(trim((string) ($grp['modo_padrao'] ?? $grp['mode'] ?? '')));
                if ($modoLocal !== 'media' && $modoLocal !== 'soma') {
                    $grp['mode'] = $cadastro['modo'];
                }
                if (trim((string) ($grp['aplicar_em'] ?? '')) === '') {
                    $grp['aplicar_em'] = $cadastro['aplicar_em'];
                }
                if (($cadastro['divisor'] ?? null) !== null) {
                    $grp['divisor'] = $cadastro['divisor'];
                }
            }
        }
        $key = $this->slug((string) ($grp['key'] ?? ''));
        if ($key === '') {
            return null;
        }
        $label = trim((string) ($grp['label'] ?? ''));
        if ($label === '') {
            $label = $key;
        }
        $mode = strtolower(trim((string) ($grp['mode'] ?? 'media')));
        if (!in_array($mode, ['media', 'soma'], true)) {
            $mode = 'media';
        }
        $ids = [];
        foreach ((array) ($grp['materias_ids'] ?? []) as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return null;
        }
        $divisor = (float) ($grp['divisor'] ?? 0);
        if ($divisor < 0) {
            $divisor = 0;
        }

        $modosSalvos = [];
        foreach ((array) ($grp['modos'] ?? []) as $pecaModo => $modoPeca) {
            $pecaModo = strtolower(trim((string) $pecaModo));
            if ($pecaModo === '' || preg_match('/^[a-z][a-z0-9_]{0,40}$/', $pecaModo) !== 1) {
                continue;
            }
            $modosSalvos[$pecaModo] = strtolower(trim((string) $modoPeca)) === 'soma' ? 'soma' : 'media';
            if (count($modosSalvos) >= 20) {
                break;
            }
        }
        $modoPadraoSalvo = strtolower(trim((string) ($grp['modo_padrao'] ?? '')));
        $out = [
            'enabled' => true,
            'key' => $key,
            'label' => $label,
            'mode' => $mode,
            'divisor' => $divisor,
            'materias_ids' => $ids,
            'aplicar_em' => $this->normalizarGroupLineAplicarEm($grp['aplicar_em'] ?? 'ambos'),
            'arredondamento' => $this->normalizarArredondamentoGrupo($grp['arredondamento'] ?? 'todos'),
        ];
        if ($modosSalvos !== []) {
            $out['modos'] = $modosSalvos;
        }
        if ($modoPadraoSalvo === 'media' || $modoPadraoSalvo === 'soma') {
            $out['modo_padrao'] = $modoPadraoSalvo;
        }
        $agrupamentoId = (int) ($grp['agrupamento_id'] ?? 0);
        if ($agrupamentoId > 0) {
            $out['agrupamento_id'] = $agrupamentoId;
        }
        $out['ocultar_filhas'] = !empty($grp['ocultar_filhas']) || !empty($grp['exemplo_sem_filhas']);
        return $out;
    }

    /**
     * @return array{bloco_id: int|null, blocos_ids: string|null}
     */
    private function normalizeBlocoFieldsForPersist(array $componente): array
    {
        $ids = [];
        if (!empty($componente['blocos_ids']) && is_array($componente['blocos_ids'])) {
            foreach ($componente['blocos_ids'] as $v) {
                $ids[] = (int) $v;
            }
        } elseif (!empty($componente['blocos_ids']) && is_string($componente['blocos_ids'])) {
            foreach (explode(',', $componente['blocos_ids']) as $part) {
                $ids[] = (int) trim($part);
            }
        }
        $ids = array_values(array_unique(array_filter($ids, static function ($id) {
            return $id > 0;
        })));
        $max = 40;
        if (count($ids) > $max) {
            $ids = array_slice($ids, 0, $max);
        }

        if (count($ids) >= 2) {
            $csv = implode(',', $ids);

            return ['bloco_id' => null, 'blocos_ids' => strlen($csv) > 500 ? substr($csv, 0, 500) : $csv];
        }
        if (count($ids) === 1) {
            return ['bloco_id' => $ids[0], 'blocos_ids' => null];
        }

        $legacy = (int) ($componente['bloco_id'] ?? 0);

        return $legacy > 0 ? ['bloco_id' => $legacy, 'blocos_ids' => null] : ['bloco_id' => null, 'blocos_ids' => null];
    }

    /**
     * @return list<int>
     */
    private function resolveBlocoIdsFromComponentePersisted(array $componente): array
    {
        $rawBlocos = $componente['blocos_ids'] ?? '';
        if (is_array($rawBlocos)) {
            return array_values(array_unique(array_filter(array_map('intval', $rawBlocos), static function ($id) {
                return $id > 0;
            })));
        }
        $raw = trim((string) $rawBlocos);
        if ($raw !== '') {
            $ids = [];
            foreach (explode(',', $raw) as $part) {
                $ids[] = (int) trim($part);
            }

            return array_values(array_unique(array_filter($ids, static function ($id) {
                return $id > 0;
            })));
        }
        $bid = (int) ($componente['bloco_id'] ?? 0);

        return $bid > 0 ? [$bid] : [];
    }

    /**
     * @param array<string,mixed> $post
     */
    private function guardarRascunhoAssistenteDaSessao(array $post): void
    {
        if (empty($post['origem_assistente']) && empty($post['assistente_rascunho'])) {
            return;
        }
        $rascunho = null;
        $raw = $post['assistente_rascunho'] ?? '';
        if (is_string($raw) && $raw !== '') {
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                $rascunho = $dec;
            }
        }
        if ($rascunho === null) {
            $comps = json_decode((string) ($post['componentes_json'] ?? '[]'), true);
            if (!is_array($comps) || $comps === []) {
                return;
            }
            $rascunho = [
                'nome' => (string) ($post['regra_nome'] ?? ''),
                'codigo' => (string) ($post['regra_codigo'] ?? ''),
                'formula_final' => (string) ($post['formula_final'] ?? ''),
                'exibir_em' => (string) ($post['exibir_em'] ?? 'notas'),
                'ano_letivo' => (int) ($post['ano_letivo'] ?? 0),
                'bimestre' => (int) ($post['bimestre'] ?? 0),
                'round_mode' => (string) ($post['round_mode'] ?? 'half'),
                'nota_minima_aprovacao' => $post['nota_minima_aprovacao'] ?? 7,
                'componentes' => $comps,
            ];
        }
        $rascunho['regra_id'] = (int) ($post['regra_id'] ?? $rascunho['regra_id'] ?? 0);
        if (empty($rascunho['componentes']) || !is_array($rascunho['componentes'])) {
            return;
        }
        $_SESSION['boletim_assistente_rascunho'] = $rascunho;
    }

    /**
     * @param array<string,mixed> $regra
     * @return array<string,mixed>
     */
    private function aplicarRascunhoAssistenteSessao(array $regra, bool $isNewMode, int $selectedRegraId): array
    {
        $raw = $_SESSION['boletim_assistente_rascunho'] ?? null;
        if (!is_array($raw) || empty($raw['componentes']) || !is_array($raw['componentes'])) {
            return $regra;
        }
        $rascunhoId = (int) ($raw['regra_id'] ?? 0);
        $regraId = (int) ($regra['id'] ?? $selectedRegraId);
        $bateNovo = $isNewMode && $rascunhoId <= 0;
        $bateEdicao = !$isNewMode && $rascunhoId > 0 && $rascunhoId === $regraId;
        if (!$bateNovo && !$bateEdicao) {
            return $regra;
        }
        unset($_SESSION['boletim_assistente_rascunho']);
        foreach (['nome', 'codigo', 'formula_final', 'exibir_em', 'finalidade', 'ano_letivo', 'bimestre', 'round_mode', 'nota_minima_aprovacao'] as $k) {
            if (isset($raw[$k]) && $raw[$k] !== '' && $raw[$k] !== null) {
                $regra[$k] = $raw[$k];
            }
        }
        $comps = [];
        foreach ($raw['componentes'] as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (empty($c['config_json']) && isset($c['config']) && is_array($c['config'])) {
                $c['config_json'] = json_encode($c['config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (isset($c['blocos_ids']) && is_array($c['blocos_ids'])) {
                $ids = [];
                foreach ($c['blocos_ids'] as $bid) {
                    $bid = (int) $bid;
                    if ($bid > 0) {
                        $ids[] = $bid;
                    }
                }
                $c['blocos_ids'] = implode(',', $ids);
            }
            $comps[] = $c;
        }
        if ($comps !== []) {
            $regra['componentes'] = $comps;
        }
        return $regra;
    }

    private function redirectFalhaConfiguracao(?int $regraId): void
    {
        $id = (int) ($regraId ?? 0);
        if ($id <= 0 && !empty($_POST['origem_assistente'])) {
            $this->redirect('/admin/boletim-configuracao?novo=1');
        }
        $this->redirect('/admin/boletim-configuracao' . ($id > 0 ? ('?regra_id=' . $id) : ''));
    }

    /**
     * @param array<string,mixed> $regra
     * @return array{ok:bool,bloqueios:list<string>,avisos:list<string>}
     */
    private function diagnosticarGatesFechamentoRegra(array $regra): array
    {
        $avisos = [];
        $boletimId = (int) ($regra['boletim_id'] ?? 0);
        $modeloEncontrado = true;
        $regraAprovacaoId = 0;
        if ($boletimId > 0) {
            try {
                $boletim = (new BoletimCadastroService())->model()->findById($boletimId);
            } catch (Throwable $e) {
                $boletim = null;
            }
            $modeloEncontrado = is_array($boletim);
            $regraAprovacaoId = is_array($boletim) ? (int) ($boletim['regra_academica_id'] ?? 0) : 0;
        }

        $quadroId = $this->grupoRegrasNotasIdDaRegra($regra);
        $quadroAtivo = true;
        if ($quadroId > 0) {
            try {
                $quadro = (new GrupoRegrasNotasService())->payloadPublico($quadroId);
            } catch (Throwable $e) {
                $quadro = null;
            }
            $quadroAtivo = is_array($quadro) && !empty($quadro['ativo']);
            if ($quadroAtivo && (empty($quadro['componentes_sugeridos']) || !is_array($quadro['componentes_sugeridos']))) {
                $avisos[] = 'O Quadro de Notas não possui colunas sugeridas; revise o cadastro antes do fechamento oficial.';
            }
        }

        $diag = FechamentoGates::diagnosticarGeracao(
            $boletimId,
            $quadroId,
            $regraAprovacaoId,
            $modeloEncontrado,
            $quadroAtivo
        );
        $diag['avisos'] = $avisos;

        return $diag;
    }

    /**
     * Geração oficial (preview=0) não reescreve boletim de período homologado.
     *
     * @param array<string,mixed> $regra
     * @param list<array<string,mixed>> $alunos
     */
    private function bloquearGeracaoSePeriodoHomologado(array $regra, array $alunos): ?string
    {
        $path = __DIR__ . '/../../Modulos/fechamento/Models/FechamentoPeriodo.php';
        if (!is_file($path)) {
            return null;
        }
        require_once $path;
        try {
            $model = new FechamentoPeriodo();
            if (!$model->schemaPronto()) {
                return null;
            }
        } catch (Throwable $e) {
            return null;
        }
        $ano = (int) ($regra['ano_letivo'] ?? date('Y'));
        $bim = (int) ($regra['bimestre'] ?? 0);
        $tipo = ($bim >= 1 && $bim <= 4) ? 'bimestre' : 'ano';
        $num = $tipo === 'bimestre' ? $bim : 0;
        $vistos = [];
        foreach ($alunos as $aluno) {
            $tid = (int) ($aluno['turma_id'] ?? 0);
            if ($tid <= 0 || isset($vistos[$tid])) {
                continue;
            }
            $vistos[$tid] = true;
            $res = $model->assertEditavel($tid, $ano, $tipo, $num);
            if (empty($res['ok'])) {
                return (string) ($res['error'] ?? 'Período homologado: geração oficial bloqueada.');
            }
        }
        return null;
    }

    private function assertCsrfOrRedirect(): void
    {
        $token = (string) ($_POST['_token'] ?? '');
        if (!$this->verifyCsrfToken($token)) {
            $_SESSION['boletim_flash'] = 'Token CSRF inválido. Atualize a página e tente novamente.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
        }
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function parseMateriasIdsFromPost($raw): array
    {
        $ids = [];
        if (is_array($raw)) {
            foreach ($raw as $v) {
                $id = (int) $v;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) > 300) {
            $ids = array_slice($ids, 0, 300);
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function parseMateriasIdsFromRegra(array $regra): array
    {
        $raw = $regra['materias_ids'] ?? null;
        $decoded = [];
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $parsed = json_decode(trim($raw), true);
            if (is_array($parsed)) {
                $decoded = $parsed;
            }
        }
        $ids = [];
        foreach ($decoded as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Inclui desdobramentos quando o filtro traz o componente pai.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    private function expandirMateriasComFilhos(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $cacheKey = implode(',', $ids);
        if (isset($this->materiasExpandidasCache[$cacheKey])) {
            return $this->materiasExpandidasCache[$cacheKey];
        }
        $comp = $this->componentesCurriculares();
        if ($comp === null) {
            return $this->materiasExpandidasCache[$cacheKey] = $ids;
        }
        try {
            return $this->materiasExpandidasCache[$cacheKey] = $comp->expandirIdsComFilhos($ids);
        } catch (Throwable $e) {
            return $this->materiasExpandidasCache[$cacheKey] = $ids;
        }
    }

    /**
     * @return list<int>
     */
    private function materiasIdsDoModeloBoletim(int $boletimId): array
    {
        if ($boletimId <= 0) {
            return [];
        }
        try {
            $cadastro = (new BoletimCadastroService())->model()->findById($boletimId);
        } catch (Throwable $e) {
            return [];
        }
        if (!is_array($cadastro)) {
            return [];
        }
        $ids = [];
        foreach ((array) ($cadastro['materias_ids'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return array<int, true>
     */
    private function idsRotuloPaiComponente(): array
    {
        $comp = $this->componentesCurriculares();
        if ($comp === null) {
            return [];
        }
        try {
            $ids = $comp->idsComFilhos();
        } catch (Throwable $e) {
            return [];
        }

        return is_array($ids) ? $ids : [];
    }

    private function componentesCurriculares(): ?\ComponenteCurricular
    {
        if ($this->componentesCurricularesCache instanceof \ComponenteCurricular) {
            return $this->componentesCurricularesCache;
        }
        $path = dirname(__DIR__, 2) . '/Models/Education/ComponenteCurricular.php';
        if (!is_file($path)) {
            return null;
        }
        require_once $path;
        try {
            $this->componentesCurricularesCache = new \ComponenteCurricular();
            return $this->componentesCurricularesCache;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function parseSeriesIdsFromPost($raw): array
    {
        $ids = [];
        if (is_array($raw)) {
            foreach ($raw as $v) {
                $id = (int) $v;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } elseif (is_string($raw)) {
            $s = trim($raw);
            if ($s !== '') {
                $parsed = json_decode($s, true);
                if (is_array($parsed)) {
                    foreach ($parsed as $v) {
                        $id = (int) $v;
                        if ($id > 0) {
                            $ids[] = $id;
                        }
                    }
                } else {
                    $parts = preg_split('/[,\s;]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
                    if (is_array($parts)) {
                        foreach ($parts as $v) {
                            $id = (int) $v;
                            if ($id > 0) {
                                $ids[] = $id;
                            }
                        }
                    }
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) > 300) {
            $ids = array_slice($ids, 0, 300);
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function parseSeriesIdsFromRegra(array $regra): array
    {
        $raw = $regra['series_ids'] ?? null;
        $decoded = [];
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $rawTrim = trim($raw);
            $parsed = json_decode($rawTrim, true);
            if (is_array($parsed)) {
                $decoded = $parsed;
            } else {
                $parts = preg_split('/[,\s;]+/', $rawTrim, -1, PREG_SPLIT_NO_EMPTY);
                if (is_array($parts)) {
                    $decoded = $parts;
                }
            }
        }
        $ids = [];
        foreach ($decoded as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    private function parseTurmasIdsFromRegra(array $regra): array
    {
        return $this->parseSeriesIdsFromRegra(['series_ids' => $regra['turmas_ids'] ?? null]);
    }

    /**
     * @return list<int>
     */
    private function listarAnosLetivosCatalogo(): array
    {
        $out = [];
        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll("SELECT ano FROM ano_letivo WHERE ano IS NOT NULL ORDER BY ano DESC");
            foreach ((array) $rows as $r) {
                $ano = (int) ($r['ano'] ?? 0);
                if ($ano >= 2000 && $ano <= 2100) {
                    $out[] = $ano;
                }
            }
        } catch (Throwable $e) {
            // fallback silencioso
        }
        $out = array_values(array_unique($out));
        rsort($out);
        if ($out === []) {
            $atual = (int) date('Y');
            $out = [$atual];
        }
        return $out;
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    /**
     * Checklist automático antes de gerar boletins em massa:
     * 1) matéria presente no evento mas ausente em algum componente de prova/falta;
     * 2) componente "evento de boletim" referenciando evento de bimestre/série incompatível.
     *
     * @return array{matérias_orfas: array<int,array>, eventos_incompativeis: array<int,array>}
     */
    private function auditarConsistenciaRegra(array $regra): array
    {
        $materiasOrfas = [];
        $eventosIncompativeis = [];

        $materiasIdsRegra = $this->parseMateriasIdsFromRegra($regra);
        $componentes = is_array($regra['componentes'] ?? null) ? $regra['componentes'] : [];

        if ($materiasIdsRegra !== []) {
            $materiasPorId = [];
            foreach ($this->boletimConfig->getAvailableSubjects(500) as $m) {
                $mid = (int) ($m['id'] ?? 0);
                if ($mid > 0) {
                    $materiasPorId[$mid] = (string) ($m['nome'] ?? ('Matéria #' . $mid));
                }
            }
            foreach ($componentes as $comp) {
                $sourceType = (string) ($comp['source_type'] ?? '');
                if (!in_array($sourceType, ['provas_sistema', 'faltas_evento'], true)) {
                    continue;
                }
                $materiasComp = $this->parseMateriasIdsRaw($comp['materias_ids'] ?? null);
                if ($materiasComp === []) {
                    // Sem lista própria = usa a lista do evento inteira; nada a conferir.
                    continue;
                }
                $faltantes = array_diff($materiasIdsRegra, $materiasComp);
                foreach ($faltantes as $midFaltante) {
                    $materiasOrfas[] = [
                        'componente_codigo' => (string) ($comp['codigo'] ?? ''),
                        'componente_nome' => (string) ($comp['nome'] ?? ''),
                        'materia_id' => $midFaltante,
                        'materia_nome' => $materiasPorId[$midFaltante] ?? ('Matéria #' . $midFaltante),
                    ];
                }
            }
        }

        $bimestreRegra = (int) ($regra['bimestre'] ?? 0);
        $seriesRegra = $this->parseSeriesIdsFromRegra($regra);
        foreach ($componentes as $comp) {
            if ((string) ($comp['source_type'] ?? '') !== 'evento_boletim') {
                continue;
            }
            $cfgEvento = $this->parseEventoConfigFromComponente($comp);
            $codEvento = trim((string) ($cfgEvento['regra_codigo'] ?? ''));
            if ($codEvento === '') {
                continue;
            }
            $refRegra = $this->boletimConfig->getRuleByCode($codEvento);
            if (!$refRegra) {
                continue;
            }
            $bimestreRef = (int) ($refRegra['bimestre'] ?? 0);
            $seriesRef = $this->parseSeriesIdsFromRegra($refRegra);
            $motivos = [];
            $finRegra = $this->boletimConfig->normalizeFinalidade((string) ($regra['finalidade'] ?? 'oficial'));
            $finRef = $this->boletimConfig->normalizeFinalidade((string) ($refRegra['finalidade'] ?? 'oficial'));
            if ($finRegra !== $finRef) {
                $motivos[] = $finRegra === 'complementar'
                    ? 'é de Notas da série (este evento é extra)'
                    : 'é de Notas extra (este evento é da série)';
            }
            if ($bimestreRegra > 0 && $bimestreRef > 0 && $bimestreRef !== $bimestreRegra) {
                $motivos[] = "é do {$bimestreRef}º bimestre (este evento é {$bimestreRegra}º)";
            }
            if ($seriesRegra !== [] && $seriesRef !== [] && array_intersect($seriesRegra, $seriesRef) === []) {
                $motivos[] = 'não cobre as mesmas séries';
            }
            if ($motivos !== []) {
                $eventosIncompativeis[] = [
                    'componente_codigo' => (string) ($comp['codigo'] ?? ''),
                    'componente_nome' => (string) ($comp['nome'] ?? ''),
                    'evento_origem_codigo' => $codEvento,
                    'evento_origem_nome' => (string) ($refRegra['nome'] ?? ''),
                    'motivo' => implode(' e ', $motivos),
                ];
            }
        }

        return [
            'materias_orfas' => $materiasOrfas,
            'eventos_incompativeis' => $eventosIncompativeis,
        ];
    }

    /**
     * @return list<int>
     */
    private function parseMateriasIdsRaw($raw): array
    {
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $decoded = is_array($decoded) ? $decoded : [];
        } else {
            $decoded = [];
        }
        $ids = [];
        foreach ($decoded as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Indicador de cobertura: quantos alunos do escopo têm pelo menos um componente
     * de nota vazio na última simulação/geração — ajuda a decidir se vale gerar agora.
     *
     * @return array{total:int, completos:int, incompletos:array<int,array>}
     */
    private function calcularCoberturaRegra(array $regra, array $alunos, string $periodoRef, ?string $dataInicio, ?string $dataFim): array
    {
        $completos = 0;
        $incompletos = [];
        $colunasObrigatorias = [];
        foreach ((is_array($regra['componentes'] ?? null) ? $regra['componentes'] : []) as $comp) {
            $sourceType = (string) ($comp['source_type'] ?? '');
            if (in_array($sourceType, ['nenhuma', 'manual'], true)) {
                continue;
            }
            $layout = $this->parseLayoutMetaFromComponente($comp);
            if (($layout['type'] ?? '') === 'faltas') {
                continue;
            }
            $colunasObrigatorias[] = (string) ($comp['codigo'] ?? '');
        }
        $colunasObrigatorias = array_values(array_filter($colunasObrigatorias));

        foreach (array_slice($alunos, 0, 300) as $aluno) {
            $alunoId = (int) ($aluno['id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            try {
                $sim = $this->simularRegraAluno($regra, $alunoId, $periodoRef, $dataInicio, $dataFim);
                $linhas = $sim['matriz_materias']['linhas'] ?? [];
                $lacunas = 0;
                foreach ($linhas as $linha) {
                    foreach ($colunasObrigatorias as $cod) {
                        $valor = $linha['notas'][$cod] ?? null;
                        if ($valor === null || $valor === '') {
                            $lacunas++;
                        }
                    }
                }
                if ($lacunas > 0) {
                    $incompletos[] = [
                        'aluno_id' => $alunoId,
                        'nome' => (string) ($aluno['nome'] ?? ('#' . $alunoId)),
                        'lacunas' => $lacunas,
                    ];
                } else {
                    $completos++;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return [
            'total' => $completos + count($incompletos),
            'completos' => $completos,
            'incompletos' => $incompletos,
        ];
    }

    private function resolveAlunosVinculadosRegra(array $regra): array
    {
        $turmasIds = $this->parseTurmasIdsFromRegra($regra);
        if ($turmasIds !== []) {
            return $this->boletimConfig->getStudentsListByClasses($turmasIds, 5000);
        }

        $seriesIds = $this->parseSeriesIdsFromRegra($regra);
        if ($seriesIds === []) {
            return $this->boletimConfig->getStudentsList(5000);
        }

        return $this->boletimConfig->getStudentsListBySeries($seriesIds, 5000);
    }

    private function boletimAssistenteDisponivel(): bool
    {
        if (!class_exists('CreditosModuleRegistry', false)) {
            require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
        }
        return \CreditosModuleRegistry::acaoIaDisponivel('boletim_assistente_mensagem');
    }

    /**
     * @return list<array{id:int,nome:string,modo:string,aplicar_em:string,divisor:?float,materias_ids:list<int>}>
     */
    private function listarAgrupamentosComponentesCatalogo(): array
    {
        $path = dirname(__DIR__, 2) . '/Modulos/agrupamentos-componentes/Services/AgrupamentoComponenteService.php';
        if (!is_file($path)) {
            return [];
        }
        require_once $path;
        try {
            return (new \App\Modulos\AgrupamentosComponentes\Services\AgrupamentoComponenteService())->listarParaBoletim();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array{id:int,nome:string,finalidade:string,ano_letivo:?int}>
     */
    private function listarBoletinsCadastro(): array
    {
        try {
            return (new BoletimCadastroService())->listarParaEventoNotas();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Depois de gerar as notas do bimestre, atualiza o documento 1º–4º do boletim escolhido.
     */
    private function atualizarBoletimDestinoAposNotas(
        array $regra,
        string $periodoRef,
        ?string $dataInicio,
        ?string $dataFim
    ): void {
        if (strtolower(trim((string) ($regra['exibir_em'] ?? ''))) !== 'notas') {
            return;
        }
        $boletimId = (int) ($regra['boletim_id'] ?? 0);
        if ($boletimId <= 0) {
            return;
        }
        try {
            $sync = (new BoletimCadastroService())->sincronizarDocumento($boletimId);
            $regraDocId = (int) ($sync['regra_id'] ?? 0);
            if ($regraDocId <= 0) {
                return;
            }
            if ($this->boletimConfig->temGeracaoEmAndamento($regraDocId)) {
                error_log('atualizarBoletimDestinoAposNotas: documento já em geração #' . $regraDocId);
                return;
            }
            $db = Database::getInstance();
            if ($db->tableExists('ai_jobs')) {
                $user = $this->usuarioGeracaoJob ?? $this->auth->getUser();
                require_once __DIR__ . '/../../Services/AIJobService.php';
                \App\Services\AIJobService::enqueue('boletim_gerar', [
                    'regra_id' => $regraDocId,
                    'periodo_ref' => $periodoRef,
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim,
                    'modo' => 'gerar',
                    'user_id' => (int) ($user['id'] ?? 0),
                    'user_nome' => (string) ($user['nome'] ?? ''),
                    'user_tipo' => (string) ($user['tipo'] ?? 'admin'),
                ], (int) ($user['id'] ?? 0), 'admin', false);
                \App\Services\AIJobService::tentarDispararWorker();
                return;
            }
            $doc = $this->boletimConfig->getRuleById($regraDocId);
            if (!is_array($doc)) {
                return;
            }
            $alunos = $this->resolverAlunosGeracaoPorModo($doc, $regraDocId, $periodoRef, 'gerar');
            if ($alunos === []) {
                return;
            }
            $this->executarGeracaoMassaInterna(
                $doc,
                $regraDocId,
                $periodoRef,
                $dataInicio,
                $dataFim,
                $alunos,
                'gerar'
            );
        } catch (Throwable $e) {
            error_log('atualizarBoletimDestinoAposNotas: ' . $e->getMessage());
        }
    }

    /**
     * @return array{id:int,nome:string,modo:string,aplicar_em:string,divisor:?float,materias_ids:list<int>}|null
     */
    private function carregarAgrupamentoCadastro(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        if (array_key_exists($id, $this->agrupamentoCadastroCache)) {
            return $this->agrupamentoCadastroCache[$id];
        }
        $this->agrupamentoCadastroCache[$id] = null;
        $path = dirname(__DIR__, 2) . '/Modulos/agrupamentos-componentes/Services/AgrupamentoComponenteService.php';
        if (!is_file($path)) {
            return null;
        }
        require_once $path;
        try {
            $row = (new \App\Modulos\AgrupamentosComponentes\Services\AgrupamentoComponenteService())->carregarParaBoletim($id);
            if (!is_array($row) || count((array) ($row['materias_ids'] ?? [])) < 2) {
                return null;
            }
            $this->agrupamentoCadastroCache[$id] = [
                'id' => $id,
                'nome' => (string) ($row['nome'] ?? ''),
                'modo' => ((string) ($row['modo'] ?? 'media')) === 'soma' ? 'soma' : 'media',
                'aplicar_em' => ((string) ($row['aplicar_em'] ?? 'boletim')) === 'ambos' ? 'ambos' : 'boletim',
                'divisor' => isset($row['divisor']) && $row['divisor'] !== null && (float) $row['divisor'] > 0
                    ? (float) $row['divisor']
                    : null,
                'materias_ids' => array_values(array_map('intval', $row['materias_ids'])),
                'materia_rotulo_id' => (int) ($row['materia_rotulo_id'] ?? 0),
            ];
        } catch (Throwable $e) {
            return null;
        }
        return $this->agrupamentoCadastroCache[$id];
    }

    private function listarGruposRegrasNotasCatalogo(): array
    {
        $path = dirname(__DIR__, 2) . '/Modulos/grupos-regras-notas/Services/GrupoRegrasNotasService.php';
        if (!is_file($path)) {
            return [];
        }
        require_once $path;
        try {
            $svc = new GrupoRegrasNotasService();
            if (!$svc->moduloAtivo() || !$svc->model()->tabelasProntas()) {
                return [];
            }
            $out = [];
            foreach ($svc->model()->listar(true) as $g) {
                $id = (int) ($g['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $row = ['id' => $id, 'nome' => (string) ($g['nome'] ?? '')];
                $payload = $svc->payloadPublico($id);
                if (is_array($payload)) {
                    $row['tipos'] = $payload['tipos'] ?? [];
                    $row['marcas'] = $payload['marcas'] ?? [];
                    $row['blocos'] = $payload['blocos'] ?? [];
                    $row['colunas'] = $payload['colunas'] ?? [];
                }
                $out[] = $row;
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $regra
     */
    private function grupoRegrasNotasIdDaRegra(array $regra): int
    {
        $regraId = (int) ($regra['id'] ?? 0);
        if ($regraId > 0) {
            return $this->boletimConfig->garantirQuadroNotasNaRegra($regraId);
        }
        return $this->boletimConfig->resolverGrupoRegrasNotasId($regra, 0);
    }

    /**
     * @param array<string,mixed> $regra
     */
    private function semanasPeriodoDaRegra(array $regra): int
    {
        $raw = $regra['extras_json'] ?? '';
        if (!is_string($raw) || trim($raw) === '') {
            return 0;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return 0;
        }

        return max(0, (int) ($decoded['semanas_periodo'] ?? 0));
    }

    /**
     * @param array<string,mixed> $regra
     * @return list<array{grupo_id:int,tipo_id:?int,marca_id:?int}>
     */
    private function destinosQuadroDaRegra(array $regra): array
    {
        $raw = $regra['extras_json'] ?? '';
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return $this->normalizarDestinosQuadroPost($decoded['destinos'] ?? []);
    }

    /**
     * @param mixed $raw
     * @return list<array{grupo_id:int,tipo_id:?int,marca_id:?int}>
     */
    private function normalizarDestinosQuadroPost($raw): array
    {
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        $vistos = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $gid = (int) ($row['grupo_id'] ?? $row['quadro_id'] ?? $row['grupo_regras_notas_id'] ?? 0);
            if ($gid <= 0) {
                continue;
            }
            $tid = (int) ($row['tipo_id'] ?? $row['bloco_id'] ?? 0);
            $mid = (int) ($row['marca_id'] ?? $row['coluna_id'] ?? 0);
            $chave = $gid . ':' . $tid . ':' . $mid;
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;
            $out[] = [
                'grupo_id' => $gid,
                'tipo_id' => $tid > 0 ? $tid : null,
                'marca_id' => $mid > 0 ? $mid : null,
            ];
        }
        return $out;
    }
}
