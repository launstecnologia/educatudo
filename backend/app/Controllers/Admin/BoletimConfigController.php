<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
require_once __DIR__ . '/../../Models/Education/JourneyBoletimLancamento.php';
require_once __DIR__ . '/../../Models/Education/SchoolAbsence.php';
require_once __DIR__ . '/../../Helpers/BoletimQuadroLayoutHelper.php';
require_once __DIR__ . '/../../Services/BoletimAssistenteWizard.php';

class BoletimConfigController extends BaseController
{
    private $auth;
    private $boletimConfig;
    private $materiasDisponiveisCache = null;

    public function __construct()
    {
        parent::__construct();

        $this->auth = new AuthManager();
        $this->boletimConfig = new BoletimConfig();
        $this->boletimConfig->ensureSchema();

        $user = $this->auth->getUser();
        if (!$this->usuarioPodeConfigurarBoletim($user)) {
            $this->redirect(URL . '/admin');
            exit;
        }

        if (($user['perfil_admin'] ?? '') === 'financeiro') {
            $this->redirect(URL . '/admin/dashboard');
            exit;
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

        $eventos = $this->boletimConfig->listAllRules(300);

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
        foreach ($this->boletimConfig->getAvailableSeries(300) as $serie) {
            $seriesNomesPorId[(int) ($serie['id'] ?? 0)] = trim((string) ($serie['nome'] ?? ''));
        }
        $turmasNomesPorId = [];
        foreach ($this->boletimConfig->getAvailableClasses(1000) as $turma) {
            $tid = (int) ($turma['id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $turmaNome = trim((string) ($turma['nome'] ?? ('Turma #' . $tid)));
            $serieNome = trim((string) ($turma['serie_nome'] ?? ''));
            $cursoNome = trim((string) ($turma['curso_nome'] ?? ''));
            $anoTurma = (int) ($turma['ano_letivo'] ?? 0);
            $rotuloTurma = $turmaNome;
            if ($serieNome !== '' && stripos($turmaNome, $serieNome) === false) {
                $rotuloTurma .= ' · ' . $serieNome;
            }
            if ($cursoNome !== '' && stripos($rotuloTurma, $cursoNome) === false) {
                $rotuloTurma .= ' · ' . $cursoNome;
            }
            if ($serieNome === '') {
                $rotuloTurma .= ' · Sem série';
            }
            if ($anoTurma > 0) {
                $rotuloTurma .= ' · ' . $anoTurma;
            }
            $turmasNomesPorId[$tid] = $rotuloTurma;
        }
        $ultimaGeracaoPorRegra = $this->boletimConfig->getUltimaGeracaoPorRegra();

        foreach ($eventos as &$ev) {
            $seriesIds = $this->parseSeriesIdsFromRegra($ev);
            $nomes = array_filter(array_map(static function ($sid) use ($seriesNomesPorId) {
                return $seriesNomesPorId[(int) $sid] ?? null;
            }, $seriesIds));
            $ev['series_nomes'] = $nomes;
            $turmasIds = $this->parseTurmasIdsFromRegra($ev);
            $turmasNomes = array_filter(array_map(static function ($tid) use ($turmasNomesPorId) {
                return $turmasNomesPorId[(int) $tid] ?? null;
            }, $turmasIds));
            $ev['turmas_nomes'] = $turmasNomes;

            $regraIdEv = (int) ($ev['id'] ?? 0);
            $ultimaGeracao = $ultimaGeracaoPorRegra[$regraIdEv] ?? null;
            $ev['ultima_geracao'] = $ultimaGeracao;
            $regraAtualizadaEm = strtotime((string) ($ev['updated_at'] ?? ''));
            $geracaoEm = $ultimaGeracao !== null ? strtotime((string) $ultimaGeracao) : false;
            $ev['boletim_desatualizado'] = $geracaoEm !== false && $regraAtualizadaEm !== false && $regraAtualizadaEm > $geracaoEm;
        }
        unset($ev);

        $perPage = 10;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = count($eventos);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min($page, max(1, $totalPages));
        $eventosPagina = array_slice($eventos, ($page - 1) * $perPage, $perPage);

        $data = [
            'title' => 'Notas e Boletim - EducaTudo',
            'page_title' => 'Notas e Boletim',
            'user' => $user,
            'current_page' => 'boletim_config',
            'csrf_token' => $this->generateCsrfToken(),
            'eventos' => $eventosPagina,
            'filtro_nome' => $filtroNome,
            'filtro_ano' => $filtroAno,
            'filtro_bimestre' => $filtroBimestre,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'page' => $page,
                'total_pages' => $totalPages,
            ],
        ];

        $this->viewWithLayout('admin', 'admin/boletim/listagem', $data);
    }

    public function index()
    {
        $user = $this->auth->getUser();
        $somenteTabela = isset($_GET['somente_tabela']) && in_array(strtolower(trim((string) $_GET['somente_tabela'])), ['1', 'true', 'sim', 'yes'], true);
        $isNewMode = isset($_GET['novo']) && in_array(strtolower(trim((string) $_GET['novo'])), ['1', 'true', 'sim', 'yes'], true);
        $selectedRegraId = isset($_GET['regra_id']) ? (int) $_GET['regra_id'] : 0;
        $regra = null;
        if (!$isNewMode) {
            $regra = $selectedRegraId > 0
                ? $this->boletimConfig->getRuleById($selectedRegraId)
                : $this->boletimConfig->getActiveRule();
        }

        if (!$regra) {
            $regra = [
                'id' => null,
                'nome' => 'Evento padrão da escola',
                'codigo' => null,
                'formula_final' => '',
                'formula_materias_json' => null,
                'materias_ids' => null,
                'series_ids' => null,
                'turmas_ids' => null,
                'exibir_em' => 'boletim',
                'ano_letivo' => (int) date('Y'),
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
            $simulacao = $this->simularRegraAluno($regra, $selectedAlunoId, $periodoRef, $dataInicio, $dataFim);

            // Persistir a simulação como PREVIEW para o aluno selecionado.
            // O preview NÃO é exibido para aluno/pais/coordenação (filtrado por preview=0
            // em getGeneratedBoletinsByAluno / getGeneratedBoletimByAlunoAndRegra).
            // Só vira oficial quando o admin clica em "Gerar boletins de todos os alunos vinculados".
            $regraIdParaPreview = (int) ($regra['id'] ?? 0);
            $matriz = is_array($simulacao) ? ($simulacao['matriz_materias'] ?? null) : null;
            if ($regraIdParaPreview > 0 && is_array($matriz)) {
                $colunasPreview = is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
                $linhasPreview = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
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
            'title' => 'Configuração de Boletim - EducaTudo',
            'page_title' => 'Configuração de Boletim',
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
            'boletim_assistente_disponivel' => $this->boletimAssistenteDisponivel(),
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
        $estadoInicial = null;
        $catalogoInicial = null;
        $rascunhoInicial = null;
        $resumoInicial = null;
        $errosIniciais = [];
        $formulasIniciais = [];
        $previewInicial = null;
        $avisoInicial = null;

        if ($selectedRegraId > 0) {
            try {
                $wizard = new BoletimAssistenteWizard();
                $estadoInicial = $wizard->estadoPadrao(null, $selectedRegraId);
                $catalogoInicial = $wizard->catalogo();
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
            } catch (Throwable $e) {
                error_log('BoletimConfigController assistente estado inicial: ' . $e->getMessage());
                $avisoInicial = 'Não consegui carregar a configuração do evento #' . $selectedRegraId . ' neste ambiente.';
            }
        }

        $data = [
            'title' => 'Assistente do Boletim - EducaTudo',
            'page_title' => 'Assistente do Boletim',
            'user' => $user,
            'current_page' => 'boletim_config',
            'csrf_token' => $this->generateCsrfToken(),
            'selected_regra_id' => $selectedRegraId,
            'boletim_assistente_disponivel' => $this->boletimAssistenteDisponivel(),
            'boletim_assistente_estado_inicial' => $estadoInicial,
            'boletim_assistente_catalogo_inicial' => $catalogoInicial,
            'boletim_assistente_rascunho_inicial' => $rascunhoInicial,
            'boletim_assistente_resumo_inicial' => $resumoInicial,
            'boletim_assistente_erros_iniciais' => $errosIniciais,
            'boletim_assistente_formulas_iniciais' => $formulasIniciais,
            'boletim_assistente_preview_inicial' => $previewInicial,
            'boletim_assistente_aviso_inicial' => $avisoInicial,
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
            'title' => 'Boletins Gerados - EducaTudo',
            'page_title' => 'Boletins Gerados',
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

        header('Content-Type: text/html; charset=utf-8');

        if ($alunoId <= 0 || $regraId <= 0 || $periodoRef === '') {
            http_response_code(400);
            echo '<div class="p-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">Parâmetros inválidos.</div>';
            return;
        }

        $evento = $this->boletimConfig->getGeneratedBoletimAdmin($alunoId, $regraId, $periodoRef);
        if (!$evento) {
            echo '<div class="p-4 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg">Nenhum dado encontrado para esse boletim.</div>';
            return;
        }

        $cabecalho = sprintf(
            '<div class="mb-3 text-sm text-gray-700"><strong>Aluno:</strong> %s%s &middot; <strong>Regra:</strong> %s &middot; <strong>Período:</strong> %s%s</div>',
            htmlspecialchars((string) ($evento['aluno_nome'] ?? ''), ENT_QUOTES, 'UTF-8'),
            $evento['aluno_ra'] ? ' (RA ' . htmlspecialchars((string) $evento['aluno_ra'], ENT_QUOTES, 'UTF-8') . ')' : '',
            htmlspecialchars((string) ($evento['regra_nome'] ?? ''), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) ($evento['periodo_ref'] ?? ''), ENT_QUOTES, 'UTF-8'),
            ((int) ($evento['preview'] ?? 0) === 1)
                ? ' <span class="inline-block ml-2 px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800">preview</span>'
                : ''
        );

        // Reusa o partial existente, sem botão de remover (a tela já tem o seu próprio).
        $boletins_gerados = [$evento];
        $boletim_pode_excluir = false;
        $boletim_aluno_id = 0;

        echo $cabecalho;
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
        $alunos = $this->resolveAlunosVinculadosRegra($regra);
        $cobertura = $this->calcularCoberturaRegra($regra, $alunos, $periodoRef, $dataInicio, $dataFim);

        echo json_encode([
            'total_alunos_escopo' => count($alunos),
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

        echo json_encode(['logs' => $logs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
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

    public function salvarRegra()
    {
        $this->assertCsrfOrRedirect();

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
        $exibirEm = strtolower(trim((string) ($_POST['exibir_em'] ?? 'boletim')));
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
        $defaultDataInicio = $this->normalizarDataYmdOpcional((string) ($_POST['default_data_inicio'] ?? ''));
        $defaultDataFim = $this->normalizarDataYmdOpcional((string) ($_POST['default_data_fim'] ?? ''));
        if ($defaultDataInicio !== null && $defaultDataFim !== null && $defaultDataInicio > $defaultDataFim) {
            [$defaultDataInicio, $defaultDataFim] = [$defaultDataFim, $defaultDataInicio];
        }

        if ($nome === '') {
            $_SESSION['boletim_flash'] = 'Informe o nome do evento.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
        }
        if ($codigoRegra !== '' && $this->boletimConfig->existsRuleCode($codigoRegra, $regraId)) {
            $_SESSION['boletim_flash'] = 'Já existe outro evento com esse código.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
        }
        if ($anoLetivo < 2000 || $anoLetivo > 2100) {
            $_SESSION['boletim_flash'] = 'Selecione um ano letivo válido.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
        }
        if ($exibirEm === 'notas' && !in_array($bimestre, [1, 2, 3, 4], true)) {
            $_SESSION['boletim_flash'] = 'Selecione um bimestre válido para exibição em Notas.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
        }

        $componentes = json_decode($componentesJson, true);
        if (!is_array($componentes) || empty($componentes)) {
            $_SESSION['boletim_flash'] = 'Adicione pelo menos um componente no fluxo do boletim.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
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
                'usar_percentual' => ($src === 'jornadas' && $temFaixasJTmp) ? 0 : (!empty($componente['usar_percentual']) ? 1 : 0),
                'escala_max' => max(0.01, (float) ($componente['escala_max'] ?? 10)),
                'obrigatorio' => !empty($componente['obrigatorio']) ? 1 : 0,
            ];
        }

        if (empty($componentesNormalizados)) {
            $_SESSION['boletim_flash'] = 'Nenhum componente válido foi informado.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
        }

        $extrasJsonNormalized = null;
        $extrasJsonRaw = trim((string) ($_POST['regra_extras_json'] ?? ''));
        $decodedExtras = [];
        if ($extrasJsonRaw !== '') {
            $decodedExtras = json_decode($extrasJsonRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['boletim_flash'] = 'O campo Extras (JSON) não contém um JSON válido: ' . json_last_error_msg();
                $_SESSION['boletim_flash_type'] = 'error';
                $this->redirect('/admin/boletim-configuracao');
            }
            if (!is_array($decodedExtras)) {
                $_SESSION['boletim_flash'] = 'O campo Extras (JSON) deve ser um objeto JSON (ex.: {}).';
                $_SESSION['boletim_flash_type'] = 'error';
                $this->redirect('/admin/boletim-configuracao');
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

        if ($decodedExtras !== []) {
            $extrasJsonNormalized = json_encode($decodedExtras, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        try {
            $this->boletimConfig->saveRule(
                $nome,
                $formulaFinal,
                $componentesNormalizados,
                $regraId,
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
                $extrasJsonNormalized
            );
            $_SESSION['boletim_flash'] = 'Evento de boletim salvo com sucesso.';
            $_SESSION['boletim_flash_type'] = 'success';
        } catch (Throwable $e) {
            error_log('Erro ao salvar regra de boletim: ' . $e->getMessage());
            $_SESSION['boletim_flash'] = 'Erro ao salvar regra: ' . $e->getMessage();
            $_SESSION['boletim_flash_type'] = 'error';
        }

        $redirId = $regraId ?? 0;
        if ($redirId <= 0 && $codigoRegra !== '') {
            $saved = $this->boletimConfig->getRuleByCode($codigoRegra);
            $redirId = (int) ($saved['id'] ?? 0);
        }
        $this->redirect('/admin/boletim-configuracao' . ($redirId > 0 ? ('?regra_id=' . $redirId) : ''));
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

        // materia_id = 0 é válido pra blocos 'manual' (ex.: ENAC, valor global por
        // componente) e negativo é válido pra linhas agrupadas em "linha única"
        // (ex.: "Língua Portuguesa" juntando Português/Literatura/Leitura), que usam
        // um id sintético negativo só pra essa simulação.
        if ($regraId <= 0 || $componenteId <= 0 || $alunoId <= 0 || $periodoRef === '') {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($limpar) {
            $resultado = $this->boletimConfig->removerNotaManual($componenteId, $alunoId, $periodoRef, $materiaId);
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
                        $this->boletimConfig->replaceGeneratedResultsForAluno(
                            $regraId,
                            $alunoId,
                            $periodoRef,
                            $dataInicio,
                            $dataFim,
                            $colunas,
                            $linhas,
                            false
                        );
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
            $this->redirect('/admin/boletim-configuracao');
        }
        $regraId = (int) ($regra['id'] ?? 0);
        if ($regraId <= 0) {
            $_SESSION['boletim_flash'] = 'Evento sem ID válido.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao');
        }

        $alunos = $this->resolveAlunosVinculadosRegra($regra);
        if ($alunos === []) {
            $_SESSION['boletim_flash'] = 'Nenhum aluno ativo encontrado para as séries vinculadas.';
            $_SESSION['boletim_flash_type'] = 'error';
            $this->redirect('/admin/boletim-configuracao?regra_id=' . $regraId);
        }

        $codigoFinal = $this->boletimConfig->getComponenteFinalCodigo($regraId) ?? '';
        $mediasAntes = $codigoFinal !== ''
            ? $this->boletimConfig->getMediaFinalPorAluno($regraId, $periodoRef, $codigoFinal)
            : [];

        $stats = $this->gravarBoletinsSimulacaoParaAlunos(
            $regra,
            $regraId,
            $periodoRef,
            $dataInicio,
            $dataFim,
            $alunos,
            'gerarBoletins'
        );
        $gerados = $stats['gerados'];
        $linhas = $stats['linhas'];
        $erros = $stats['erros'];
        $errosAmostra = $stats['errosAmostra'];

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

        $usuarioLog = $this->auth->getUser();
        $this->boletimConfig->registrarLogGeracao(
            $regraId,
            $periodoRef,
            (int) ($usuarioLog['id'] ?? 0) ?: null,
            (string) ($usuarioLog['nome'] ?? '') ?: null,
            $gerados,
            $linhas,
            $erros,
            count($alunosComMudancaSignificativa),
            ['alunos_mudanca_significativa' => array_slice($alunosComMudancaSignificativa, 0, 20)]
        );

        $msg = 'Geração concluída: ' . $gerados . ' aluno(s), ' . $linhas . ' linha(s) de matéria.' . ($erros > 0 ? (' Falhas: ' . $erros . '.') : '');
        if (!empty($errosAmostra)) {
            $msg .= ' Exemplo(s): ' . implode(' | ', $errosAmostra);
        }
        if (!empty($alunosComMudancaSignificativa)) {
            $exemplosMudanca = array_slice(array_map(static function ($a) {
                return $a['nome'] . ' (' . $a['antes'] . '→' . $a['depois'] . ')';
            }, $alunosComMudancaSignificativa), 0, 5);
            $msg .= ' ⚠️ ' . count($alunosComMudancaSignificativa) . ' aluno(s) com nota final mudando 2+ pontos: ' . implode(', ', $exemplosMudanca) . '.';
        }
        $_SESSION['boletim_flash'] = $msg;
        $_SESSION['boletim_flash_type'] = $erros > 0 ? 'error' : 'success';
        $qs = [
            'regra_id' => $regraId,
            'periodo_ref' => $periodoRef,
        ];
        if ($dataInicio !== null && $dataFim !== null) {
            $qs['data_inicio'] = $dataInicio;
            $qs['data_fim'] = $dataFim;
        }
        $this->redirect('/admin/boletim-configuracao?' . http_build_query($qs));
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
            $this->boletimConfig->replaceGeneratedResultsForAluno(
                $regraId,
                $alunoId,
                $periodoRef,
                $dataInicio,
                $dataFim,
                $colunas,
                $linhas,
                false
            );
            $_SESSION['boletim_flash'] = 'Boletim oficial gravado para este aluno neste período (visível no app como os demais gerados em lote).';
            $_SESSION['boletim_flash_type'] = 'success';
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

        $idsComGravacao = $this->boletimConfig->listAlunoIdsWithOfficialBoletim($regraId, $periodoRef);
        if ($idsComGravacao === []) {
            $_SESSION['boletim_flash'] = 'Não há boletins gravados neste período para atualizar. Use "Gerar boletins de todos os alunos vinculados" na primeira vez ou quando precisar incluir alunos que ainda não têm registro.';
            $_SESSION['boletim_flash_type'] = 'error';
            $qs = [
                'regra_id' => $regraId,
                'periodo_ref' => $periodoRef,
            ];
            if ($dataInicio !== null && $dataFim !== null) {
                $qs['data_inicio'] = $dataInicio;
                $qs['data_fim'] = $dataFim;
            }
            $this->redirect('/admin/boletim-configuracao?' . http_build_query($qs));
        }

        $alunos = $this->boletimConfig->getStudentsByIds($idsComGravacao);
        $stats = $this->gravarBoletinsSimulacaoParaAlunos(
            $regra,
            $regraId,
            $periodoRef,
            $dataInicio,
            $dataFim,
            $alunos,
            'atualizarBoletinsGravados'
        );
        $gerados = $stats['gerados'];
        $linhas = $stats['linhas'];
        $erros = $stats['erros'];
        $errosAmostra = $stats['errosAmostra'];

        $msg = 'Atualização dos boletins já gravados concluída: ' . $gerados . ' aluno(s), ' . $linhas . ' linha(s) de matéria.' . ($erros > 0 ? (' Falhas: ' . $erros . '.') : '');
        if (!empty($errosAmostra)) {
            $msg .= ' Exemplo(s): ' . implode(' | ', $errosAmostra);
        }
        $_SESSION['boletim_flash'] = $msg;
        $_SESSION['boletim_flash_type'] = $erros > 0 ? 'error' : 'success';
        $qs = [
            'regra_id' => $regraId,
            'periodo_ref' => $periodoRef,
        ];
        if ($dataInicio !== null && $dataFim !== null) {
            $qs['data_inicio'] = $dataInicio;
            $qs['data_fim'] = $dataFim;
        }
        $this->redirect('/admin/boletim-configuracao?' . http_build_query($qs));
    }

    /**
     * Recalcula a matriz e substitui linhas em boletim_resultados_gerados (oficial) por aluno.
     *
     * @param list<array{id:int,nome?:string}> $alunos
     * @return array{gerados:int,linhas:int,erros:int,errosAmostra:list<string>}
     */
    private function gravarBoletinsSimulacaoParaAlunos(
        array $regra,
        int $regraId,
        string $periodoRef,
        ?string $dataInicio,
        ?string $dataFim,
        array $alunos,
        string $logContexto
    ): array {
        $gerados = 0;
        $linhas = 0;
        $erros = 0;
        $errosAmostra = [];
        foreach ($alunos as $aluno) {
            $alunoId = (int) ($aluno['id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            try {
                $sim = $this->simularRegraAluno($regra, $alunoId, $periodoRef, $dataInicio, $dataFim);
                $matriz = $sim['matriz_materias'] ?? null;
                $colunas = is_array($matriz) && is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
                $rows = is_array($matriz) && is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
                $this->boletimConfig->replaceGeneratedResultsForAluno(
                    $regraId,
                    $alunoId,
                    $periodoRef,
                    $dataInicio,
                    $dataFim,
                    $colunas,
                    $rows,
                    false
                );
                $gerados++;
                $linhas += count($rows);
            } catch (Throwable $e) {
                $erros++;
                if (count($errosAmostra) < 3) {
                    $nomeAluno = trim((string) ($aluno['nome'] ?? ('#' . $alunoId)));
                    $errosAmostra[] = $nomeAluno . ': ' . $e->getMessage();
                }
                error_log('Boletim ' . $logContexto . ' aluno #' . $alunoId . ': ' . $e->getMessage());
            }
        }

        return [
            'gerados' => $gerados,
            'linhas' => $linhas,
            'erros' => $erros,
            'errosAmostra' => $errosAmostra,
        ];
    }

    private function simularRegraAluno(
        array $regra,
        int $alunoId,
        string $periodoRef,
        ?string $rangeInicioOverride = null,
        ?string $rangeFimOverride = null,
        array $visitedRuleCodes = []
    ): array
    {
        $roundMode = $this->normalizeRoundMode((string) ($regra['round_mode'] ?? 'none'));
        $range = [
            'inicio' => $rangeInicioOverride ? ($rangeInicioOverride . ' 00:00:00') : null,
            'fim' => $rangeFimOverride ? ($rangeFimOverride . ' 23:59:59') : null,
        ];
        if ($range['inicio'] === null || $range['fim'] === null) {
            $range = $this->periodoToRange($periodoRef);
        }
        $componentes = $regra['componentes'] ?? [];
        $expansaoQuadro = $this->expandirRegraQuadroSemanalNaSimulacao($regra, is_array($componentes) ? $componentes : []);
        $regra = $expansaoQuadro['regra'];
        $componentes = $expansaoQuadro['componentes'];

        $componentesResultado = [];
        $valoresPorCodigo = [];
        $faltantesObrigatorios = [];
        /** @var array<string, array<int, float>> */
        $matrizPorCodigo = [];
        /** @var array<string, array<int, array{acertos:int,total:int}>> */
        $matrizPercentStatsPorCodigo = [];
        /** @var array<int, string> */
        $materiaNomesPorId = [];
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
                ? $this->boletimConfig->getManualNotesByComponente($compIdOverride, $alunoId, $periodoRef)
                : [];

            if (($componente['source_type'] ?? 'provas_sistema') === 'nenhuma') {
                $detalhes['origem'] = 'nenhuma';
            } elseif (($componente['source_type'] ?? 'provas_sistema') === 'manual') {
                $compIdManual = (int) ($componente['id'] ?? 0);
                $manual = $compIdManual > 0
                    ? $this->boletimConfig->getManualNote($compIdManual, $alunoId, $periodoRef)
                    : null;
                if ($manual) {
                    $valor = (float) $manual['nota'];
                    $bloqueado = (int) ($manual['bloqueado'] ?? 0) === 1;
                    $detalhes['manual_id'] = (int) $manual['id'];
                } elseif ($compIdManual > 0) {
                    $outros = $this->boletimConfig->listManualNotesOtherPeriods($compIdManual, $alunoId, $periodoRef, 8);
                    if (!empty($outros)) {
                        $detalhes['manual_outros_periodos'] = $outros;
                    }
                }
            } elseif (($componente['source_type'] ?? '') === 'jornadas') {
                $alunoRow = $this->buscarAluno($alunoId);
                $turmaId = (int) ($alunoRow['turma_id'] ?? 0);
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
                                $stack
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
                                    // [DEBUG TEMPORARIO] Diagnostico da Redacao no boletim combinado.
                                    // Remover apos validar o 2o bimestre da Redacao.
                                    if ($canonRef === 'redacao' || stripos($nomeRef, 'reda') !== false) {
                                        $this->debugBoletim(sprintf(
                                            'BOLETIM_REDACAO_DEBUG col_resolvida=%s mid=%d nome="%s" canon=%s celula=%s nota_resumo=%s colunas_disponiveis=[%s]',
                                            (string) $codColRes,
                                            $midRef,
                                            $nomeRef,
                                            $canonRef,
                                            var_export($notasRef[$codColRes] ?? null, true),
                                            var_export($linRef['nota_resumo'] ?? null, true),
                                            implode(',', array_keys($notasRef))
                                        ));
                                    }
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
                    $lancamentosFaltas = $absence->getLancamentosMapByEvento($eventoIdFaltas);
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
                        $eventoFaltas = $absence->getEventoById($eventoIdFaltas);
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
                $materiaFiltro = (int) ($componente['materia_id'] ?? 0);
                $materiasFiltro = $this->parseMateriasIdsFromComponente($componente);
                $materiaFiltroConsulta = $materiasFiltro !== []
                    ? null
                    : ($materiaFiltro > 0 ? $materiaFiltro : null);
                $blocoIds = $this->resolveBlocoIdsFromComponentePersisted($componente);
                $semanaComp = $this->parseSemanaFromComponente($componente);
                $tipoAvaliacaoComp = $this->parseTipoAvaliacaoIdFromComponente($componente);
                $bimestresComp = $this->parseProvaBimestresFromComponente($componente);
                if ($blocoIds !== [] && $bimestresComp !== []) {
                    $blocoIds = $this->boletimConfig->filtrarBlocoIdsPorBimestres($blocoIds, $bimestresComp);
                }
                $semanaForcada = $semanaComp >= 1 && $semanaComp <= 8;
                if ($semanaForcada) {
                    if ($blocoIds !== []) {
                        $filtradosSemana = $this->boletimConfig->filtrarBlocoIdsPorSemana($blocoIds, $semanaComp);
                        if ($filtradosSemana !== []) {
                            $blocoIds = $filtradosSemana;
                        } elseif ($tipoAvaliacaoComp > 0) {
                            $blocoIds = $this->boletimConfig->buscarBlocoIdsPorTipoESemana(
                                $tipoAvaliacaoComp,
                                $semanaComp,
                                $range['inicio'] ?? null,
                                $range['fim'] ?? null,
                                $bimestresComp
                            );
                        } else {
                            $blocoIds = [];
                        }
                    } elseif ($tipoAvaliacaoComp > 0) {
                        $blocoIds = $this->boletimConfig->buscarBlocoIdsPorTipoESemana(
                            $tipoAvaliacaoComp,
                            $semanaComp,
                            $range['inicio'] ?? null,
                            $range['fim'] ?? null,
                            $bimestresComp
                        );
                    }
                }
                $filtroTitulo = trim((string) ($componente['filtro_titulo'] ?? ''));
                $filtroTitulo = $filtroTitulo !== '' ? $filtroTitulo : null;

                if (!empty($blocoIds)) {
                    // Regra de negócio: bloco(s) de prova selecionado(s) devem
                    // contabilizar independente do intervalo de data global.
                    $rows = $this->boletimConfig->getProvasFinalizadasByAlunoAndBlocos(
                        $alunoId,
                        $blocoIds,
                        null,
                        null,
                        $filtroTitulo,
                        $materiaFiltroConsulta
                    );
                    $detalhes['blocos_ids'] = $blocoIds;
                } elseif ($semanaForcada) {
                    $rows = [];
                    $detalhes['aviso_semana'] = 'Nenhum evento de prova com a semana S' . $semanaComp . '.';
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
                }

                $statsPorMateria = [];
                if (!empty($componente['usar_percentual'])) {
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
                    $notas = $this->deduplicarNotasPorMateria($notas);
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

            if ($overridesPorMateria !== []) {
                $roundModeOv = $this->resolveRoundModeComponente($componente, $roundMode);
                if (!is_array($matrizPorCodigo[$codigo] ?? null)) {
                    $matrizPorCodigo[$codigo] = [];
                }
                $materiasComOverrideOv = [];
                foreach ($overridesPorMateria as $midOv => $rowOv) {
                    $midOv = (int) $midOv;
                    if ($midOv === 0 || !is_numeric($rowOv['nota'] ?? null)) {
                        continue;
                    }
                    $matrizPorCodigo[$codigo][$midOv] = $this->applyRoundMode((float) $rowOv['nota'], $roundModeOv);
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
        foreach ($this->parseMateriasIdsFromRegra($regra) as $midSelPre) {
            $midSelPre = (int) $midSelPre;
            if ($midSelPre > 0) {
                $allMidsPreCalc[$midSelPre] = true;
            }
        }
        $this->aplicarEspalhamentoJornadasNotaUnicaNaMatriz($componentes, $matrizPorCodigo, $componentesResultado, $allMidsPreCalc, $roundModeSim);

        foreach ($componentes as $componente) {
            $codigo = (string) ($componente['codigo'] ?? '');
            if ($codigo === '' || ($componente['source_type'] ?? '') !== 'calculado') {
                continue;
            }

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
                    if ($midOv === 0 || !is_numeric($rowOv['nota'] ?? null)) {
                        continue;
                    }
                    $matrizPorCodigo[$codigo][$midOv] = $this->applyRoundMode((float) $rowOv['nota'], $roundModeComp);
                    $materiasComOverride[$midOv] = [
                        'manual_id' => (int) ($rowOv['id'] ?? 0),
                        'bloqueado' => (int) ($rowOv['bloqueado'] ?? 0) === 1,
                    ];
                    if ((int) ($rowOv['bloqueado'] ?? 0) === 1) {
                        $bloqueado = true;
                    }
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
        $matrizMaterias = $this->montarMatrizMateriasSimulacao(
            $regra,
            $componentes,
            $componentesResultado,
            $matrizPorCodigo,
            $materiaNomesPorId,
            $matrizPercentStatsPorCodigo,
            $materiasAgrupadasHerdadas
        );

        return [
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
                        $listaProc = $this->deduplicarNotasPorMateria($listaProc);
                    }
                    $v = $this->agruparNotas($listaProc, $calc);
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
            if (!isset($byMid[$mid])) {
                $byMid[$mid] = [];
            }
            $byMid[$mid][] = $n;
        }
        $out = [];
        foreach ($byMid as $mid => $lista) {
            $listaProc = $lista;
            if (!empty($componente['materia_unica'])) {
                $listaProc = $this->deduplicarNotasPorMateria($listaProc);
            }
            $v = $this->agruparNotas($listaProc, $calc);
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
        $alternativas = [
            [
                'id' => (int) ($row['professor_materia_id'] ?? 0),
                'nome' => trim((string) ($row['professor_materia_nome'] ?? '')),
            ],
            [
                'id' => (int) ($row['nota_materia_id'] ?? 0),
                'nome' => trim((string) ($row['nota_materia_nome'] ?? '')),
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
     * Blocos de lançamento com "nota única para todas as matérias" podem ter a nota
     * gravada em apenas uma das matérias do evento. Para o boletim, replica essa nota
     * para as matérias selecionadas no bloco antes de aplicar o filtro final.
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
        if ($ids === [] || $rows === []) {
            return $rows;
        }

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
        foreach ($rows as $row) {
            $blocoId = (int) ($row['bloco_id'] ?? 0);
            $mid = (int) ($row['materia_id'] ?? 0);
            if ($blocoId > 0 && $mid > 0) {
                $temLinhaPorBlocoMateria[$blocoId][$mid] = true;
            }
            if ($blocoId <= 0 || empty($row['nota_unica_todas_materias'])) {
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

        foreach ($basePorBloco as $blocoId => $base) {
            foreach ($ids as $midSel) {
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
     * [DEBUG TEMPORARIO] Escreve diagnostico do boletim num arquivo fixo e conhecido
     * (storage/logs/boletim_debug.log), alem do error_log padrao. Remover apos validar.
     */
    private function debugBoletim(string $msg): void
    {
        error_log($msg);
        $dir = __DIR__ . '/../../../storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/boletim_debug.log', date('c') . ' ' . $msg . "\n", FILE_APPEND);
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
        array $materiasAgrupadasHerdadas = []
    ): ?array {
        // [DEBUG TEMPORARIO] Carimbo para confirmar que o codigo novo esta no ar.
        $this->debugBoletim('BOLETIM_DEBUG_V2 montarMatriz regra=' . (string) ($regra['codigo'] ?? '?') . ' exibir_em=' . (string) ($regra['exibir_em'] ?? '?'));
        $roundMode = $this->normalizeRoundMode((string) ($regra['round_mode'] ?? 'none'));
        $grp = $this->aplicarAgrupamentoLinhasPorComponente($componentesRegra, $matrizPorCodigo, $materiaNomesPorId, $matrizPercentStatsPorCodigo);
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
        $gruposVirtualMids = is_array($grp['grupos_virtual_mids'] ?? null) ? $grp['grupos_virtual_mids'] : [];

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
        if ($materiasSelecionadas !== []) {
            $catalogoMaterias = $this->boletimConfig->getAvailableSubjects(2000);
            $nomesCatalogoById = [];
            foreach ((array) $catalogoMaterias as $mCat) {
                $midCat = (int) ($mCat['id'] ?? 0);
                if ($midCat > 0) {
                    $nomesCatalogoById[$midCat] = trim((string) ($mCat['nome'] ?? ('Matéria #' . $midCat)));
                }
            }
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
                $nomeSel = (string) ($materiaNomesPorId[$midSel] ?? ($nomesCatalogoById[$midSel] ?? ''));
                $nomeKeySel = $this->canonicalMateriaNomeKey($nomeSel);
                if ($nomeKeySel !== '' && isset($nomesGruposVirtuais[$nomeKeySel])) {
                    continue;
                }
                $allMids[$midSel] = true;
                if (!isset($materiaNomesPorId[$midSel])) {
                    $materiaNomesPorId[$midSel] = (string) ($nomesCatalogoById[$midSel] ?? ('Matéria #' . $midSel));
                }
            }
        }

        $this->aplicarEspalhamentoJornadasNotaUnicaNaMatriz($componentesRegra, $matrizPorCodigo, $componentesResultado, $allMids, $roundMode);

        $midsOrdenados = array_keys($allMids);
        if ($materiasAgrupadas !== []) {
            $midsOrdenados = array_values(array_filter($midsOrdenados, static function (int $mid) use ($materiasAgrupadas) {
                return $mid <= 0 || !isset($materiasAgrupadas[$mid]);
            }));
        }
        $exibirEmRegra = strtolower(trim((string) ($regra['exibir_em'] ?? 'boletim')));
        if ($materiasSelecionadas !== [] && $exibirEmRegra === 'notas') {
            $set = array_fill_keys($materiasSelecionadas, true);
            $midsOrdenados = array_values(array_filter($midsOrdenados, static function (int $mid) use ($set) {
                return $mid <= 0 || isset($set[$mid]);
            }));
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

        usort($midsOrdenados, static function (int $a, int $b) use ($materiaNomesPorId) {
            $na = $materiaNomesPorId[$a] ?? ('#' . $a);
            $nb = $materiaNomesPorId[$b] ?? ('#' . $b);
            $la = function_exists('mb_strtolower') ? mb_strtolower($na, 'UTF-8') : strtolower($na);
            $lb = function_exists('mb_strtolower') ? mb_strtolower($nb, 'UTF-8') : strtolower($nb);

            return strcasecmp($la, $lb);
        });

        $formula = trim((string) ($regra['formula_final'] ?? ''));
        $resultadoCodigos = [];
        $mediaFinalCodigo = '';
        foreach ($colunas as $cMeta) {
            $lt = strtolower(trim((string) ($cMeta['layout_type'] ?? '')));
            $lg = strtolower(trim((string) ($cMeta['layout_group'] ?? '')));
            $cc = trim((string) ($cMeta['codigo'] ?? ''));
            if ($cc === '') {
                continue;
            }
            if ($lt === 'resultado') {
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
        $linhas = [];
        foreach ($midsOrdenados as $mid) {
            $notasLinha = [];
            $valoresFormula = [];
            foreach ($colunas as $col) {
                $cod = $col['codigo'];
                $mapCod = $matrizPorCodigo[$cod] ?? [];
                $cell = $mapCod[$mid] ?? null;
                $roundModeCol = (string) ($col['round_mode_efetivo'] ?? $roundMode);
                $vcell = is_numeric($cell) ? $this->applyRoundMode((float) $cell, $roundModeCol) : null;
                $colunaEhFaltas = ((string) ($col['source_type'] ?? '')) === 'faltas_evento'
                    || strtolower((string) ($col['layout_type'] ?? '')) === 'faltas';
                if (!$colunaEhFaltas && is_numeric($vcell)) {
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
                    $resultadoTxt = $mediaRef >= $notaMinimaAprovacao ? 'Aprovado' : 'Reprovado';
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

        // [DEBUG TEMPORARIO] Loga as linhas de Redacao com as notas por coluna.
        foreach ($linhas as $linhaDbg) {
            $nomeDbg = (string) ($linhaDbg['materia_nome'] ?? '');
            if (stripos($nomeDbg, 'reda') === false && $this->canonicalMateriaNomeKey($nomeDbg) !== 'redacao') {
                continue;
            }
            $notasDbg = [];
            foreach ((array) ($linhaDbg['notas'] ?? []) as $kDbg => $vDbg) {
                $notasDbg[] = $kDbg . '=' . var_export($vDbg, true);
            }
            $this->debugBoletim('BOLETIM_DEBUG_V2 LINHA_REDACAO regra=' . (string) ($regra['codigo'] ?? '?')
                . ' mid=' . (int) ($linhaDbg['materia_id'] ?? 0)
                . ' nome="' . $nomeDbg . '"'
                . ' resumo=' . var_export($linhaDbg['nota_resumo'] ?? null, true)
                . ' notas=[' . implode(', ', $notasDbg) . ']');
        }

        return [
            'colunas' => $colunas,
            'linhas' => $linhas,
            'tem_formula' => $formula !== '',
        ];
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
     *   grupos_virtual_mids: array<int, bool>
     * }
     */
    private function aplicarAgrupamentoLinhasPorComponente(array $componentesRegra, array $matrizPorCodigo, array $materiaNomesPorId, array $matrizPercentStatsPorCodigo = []): array
    {
        $groupMetaByKey = [];
        $groupMidByKey = [];
        $materiasAgrupadas = [];
        $groupKeysAtivos = [];
        $componentGroupByCode = [];
        $materiasComValorForaDeGrupo = [];
        $materiasComFormulaPropria = [];
        $nextVirtualMid = -1;
        $calcCfgByCode = [];

        foreach ($componentesRegra as $comp) {
            $codigo = trim((string) ($comp['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $grp = $this->parseGroupLineConfigFromComponente($comp);
            if ($grp === null) {
                continue;
            }
            $gk = $grp['key'];
            if (!isset($groupMidByKey[$gk])) {
                $groupMidByKey[$gk] = $nextVirtualMid;
                $nextVirtualMid--;
                $groupMetaByKey[$gk] = [
                    'label' => $grp['label'],
                    'materias_ids' => $grp['materias_ids'],
                    'mode' => $grp['mode'],
                    'divisor' => $grp['divisor'],
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

        if ($componentGroupByCode === []) {
            return [
                'matriz_por_codigo' => $matrizPorCodigo,
                'materia_nomes_por_id' => $materiaNomesPorId,
                'materias_agrupadas' => [],
                'grupos_virtual_mids' => [],
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
            foreach ($mapOriginal as $midOriginal => $valorOriginal) {
                $midOriginal = (int) $midOriginal;
                if ($midOriginal <= 0 || !is_numeric($valorOriginal)) {
                    continue;
                }
                if ($grupoDoComponente !== null && !isset($idsGrupoDoComponente[$midOriginal])) {
                    $materiasComValorForaDeGrupo[$midOriginal] = true;
                }
                if (isset($materiasComFormulaPropria[$midOriginal])) {
                    $materiasComValorForaDeGrupo[$midOriginal] = true;
                }
            }

            foreach (array_keys($materiasAgrupadas) as $midRem) {
                $midRem = (int) $midRem;
                $temValorIndependenteNesteComponente = isset($materiasComValorForaDeGrupo[$midRem])
                    && isset($mapOriginal[$midRem])
                    && is_numeric($mapOriginal[$midRem])
                    && (
                        isset($materiasComFormulaPropria[$midRem])
                        || ($grupoDoComponente !== null && !isset($idsGrupoDoComponente[$midRem]))
                    );
                if (!$temValorIndependenteNesteComponente) {
                    unset($map[$midRem]);
                }
            }

            if (isset($componentGroupByCode[$codigo])) {
                $cfg = $componentGroupByCode[$codigo];
                $vals = [];
                $sumAcertos = 0;
                $sumQuestoes = 0;
                $statsMap = isset($matrizPercentStatsPorCodigo[$codigo]) && is_array($matrizPercentStatsPorCodigo[$codigo])
                    ? $matrizPercentStatsPorCodigo[$codigo]
                    : [];
                foreach ($cfg['materias_ids'] as $midSel) {
                    if (isset($matrizPorCodigo[$codigo][$midSel]) && is_numeric($matrizPorCodigo[$codigo][$midSel])) {
                        $vals[] = (float) $matrizPorCodigo[$codigo][$midSel];
                    }
                    if (isset($statsMap[$midSel]) && is_array($statsMap[$midSel])) {
                        $sumAcertos += (int) ($statsMap[$midSel]['acertos'] ?? 0);
                        $sumQuestoes += (int) ($statsMap[$midSel]['total'] ?? 0);
                    }
                }
                $agr = null;
                if (!empty($cfg['usar_percentual']) && strtolower((string) $cfg['mode']) === 'media' && $sumQuestoes > 0) {
                    $agr = ($sumAcertos / $sumQuestoes) * 10.0;
                } else {
                    $divisorCfg = (float) ($cfg['divisor'] ?? 0);
                    // Em Jornadas, a média agrupada deve respeitar só as matérias com nota
                    // no período, sem forçar divisor fixo (evita queda artificial, ex.: 4,5).
                    if (($cfg['source_type'] ?? '') === 'jornadas' && strtolower((string) ($cfg['mode'] ?? 'media')) === 'media') {
                        // Também ignora zeros no agrupamento de Jornadas para não diluir
                        // a nota da área quando há matérias do grupo sem jornada aplicável.
                        $vals = array_values(array_filter($vals, static function ($v) {
                            return is_numeric($v) && (float) $v > 0.0;
                        }));
                        $divisorCfg = 0.0;
                    }
                    $agr = $this->agruparValoresGrupoLinha($vals, $cfg['mode'], $divisorCfg);
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
                    $map[$virtualMidCfg] = $agr;
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
                                $vars = [];
                                $codeInsensitive = [];
                                foreach (array_keys($matrizPorCodigo) as $existingCode) {
                                    $codeInsensitive[strtolower((string) $existingCode)] = (string) $existingCode;
                                }
                                foreach ($refsExpr as $refToken) {
                                    $refCode = null;
                                    if (array_key_exists((string) $refToken, $matrizPorCodigo)) {
                                        $refCode = (string) $refToken;
                                    } else {
                                        $lk = strtolower((string) $refToken);
                                        if (isset($codeInsensitive[$lk])) {
                                            $refCode = $codeInsensitive[$lk];
                                        }
                                    }
                                    if ($refCode === null) {
                                        $vars[(string) $refToken] = 0.0;
                                        continue;
                                    }
                                    $valRef = $matrizPorCodigo[$refCode][$vmid] ?? null;
                                    $vars[(string) $refCode] = is_numeric($valRef) ? (float) $valRef : 0.0;
                                }
                                $rFormula = $this->avaliarFormula($expr, $vars);
                                if (!empty($rFormula['ok']) && isset($rFormula['valor']) && is_numeric($rFormula['valor'])) {
                                    $map[$vmid] = (float) $rFormula['valor'];
                                    $groupKeysAtivos[(string) $gk] = true;
                                    continue;
                                }
                            }
                        }
                    }
                    $vals = [];
                    foreach ((array) ($meta['materias_ids'] ?? []) as $midSel) {
                        $midSel = (int) $midSel;
                        if ($midSel > 0 && isset($mapOriginal[$midSel]) && is_numeric($mapOriginal[$midSel])) {
                            $vals[] = (float) $mapOriginal[$midSel];
                        }
                    }
                    $agr = $this->agruparValoresGrupoLinha(
                        $vals,
                        (string) ($meta['mode'] ?? 'media'),
                        strtolower((string) ($meta['mode'] ?? 'media')) === 'media'
                            ? 0.0
                            : (float) ($meta['divisor'] ?? 0)
                    );
                    if ($agr !== null) {
                        $map[$vmid] = $agr;
                        $groupKeysAtivos[(string) $gk] = true;
                    }
                }
            }

            $matrizPorCodigo[$codigo] = $map;
        }

        $materiasAgrupadasAtivas = [];
        foreach ($groupMetaByKey as $gk => $meta) {
            if (empty($groupKeysAtivos[(string) $gk])) {
                continue;
            }
            foreach ((array) ($meta['materias_ids'] ?? []) as $midSel) {
                $midSel = (int) $midSel;
                if ($midSel > 0 && !isset($materiasComValorForaDeGrupo[$midSel])) {
                    $materiasAgrupadasAtivas[$midSel] = true;
                }
            }
        }

        $gruposVirtualMids = [];
        foreach ($groupMetaByKey as $gk => $meta) {
            if (empty($groupKeysAtivos[(string) $gk])) {
                continue;
            }
            $vmid = (int) ($groupMidByKey[$gk] ?? 0);
            if ($vmid < 0) {
                $gruposVirtualMids[$vmid] = true;
            }
        }

        return [
            'matriz_por_codigo' => $matrizPorCodigo,
            'materia_nomes_por_id' => $materiaNomesPorId,
            // Quando há agrupamento configurado, oculta as matérias originais
            // daquele grupo e mantém apenas a linha consolidada.
            'materias_agrupadas' => $materiasAgrupadasAtivas,
            'grupos_virtual_mids' => $gruposVirtualMids,
        ];
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
            if (!is_numeric($comp['valor'])) {
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
        $v = strtolower(trim($value));
        return in_array($v, ['none', 'half'], true) ? $v : 'none';
    }

    private function applyRoundMode(?float $value, string $mode): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $v = (float) $value;
        if ($mode !== 'half') {
            return round($v, 2);
        }
        $base = floor($v);
        $dec = $v - $base;
        if ($dec < 0.25) {
            return round($base, 2);
        }
        if ($dec < 0.75) {
            return round($base + 0.5, 2);
        }
        return round($base + 1.0, 2);
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
            $map[$k] = (float) ($this->applyRoundMode((float) $v, $mode) ?? $v);
        }
        return $map;
    }

    private function avaliarFormula(string $formula, array $valoresPorCodigo): array
    {
        $valoresPorCodigoInsensitive = [];
        $valoresPorCodigoNormalized = [];
        $normKey = static function (string $s): string {
            $s = strtolower(trim($s));
            $s = str_replace('-', '_', $s);
            $s = preg_replace('/_+/', '_', $s) ?? $s;
            return trim($s, '_');
        };
        foreach ($valoresPorCodigo as $k => $v) {
            $ks = (string) $k;
            $valoresPorCodigoInsensitive[strtolower($ks)] = $v;
            $valoresPorCodigoNormalized[$normKey($ks)] = $v;
        }
        // Preserve vírgula de argumentos em max/min e converte apenas vírgula decimal (ex.: 8,5 -> 8.5).
        $expr = preg_replace('/(?<=\d),(?=\d)/', '.', $formula) ?? $formula;

        // Suporta códigos de bloco que não são identificadores "puros" na expressão
        // (ex.: com hífen ou iniciando com número, como "3serie_...").
        // Sem esse passo, o parser lê hífen como subtração e ignora nomes iniciados por dígito.
        $codeKeys = array_keys($valoresPorCodigo);
        usort($codeKeys, static function ($a, $b) {
            return strlen((string) $b) <=> strlen((string) $a);
        });
        $aliasMap = [];
        $aliasValues = [];
        $aliasSeq = 0;
        foreach ($codeKeys as $codeKey) {
            $code = (string) $codeKey;
            if ($code === '') {
                continue;
            }
            $isIdentificadorValido = (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/u', $code);
            if ($isIdentificadorValido) {
                continue;
            }
            $alias = '__codealias_' . $aliasSeq++;
            $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($code, '/') . '(?![A-Za-z0-9_])/u';
            $exprNovo = preg_replace($pattern, $alias, $expr);
            if ($exprNovo !== null && $exprNovo !== $expr) {
                $expr = $exprNovo;
                $aliasMap[$alias] = $code;
                if (array_key_exists($code, $valoresPorCodigo)) {
                    $aliasValues[$alias] = $valoresPorCodigo[$code];
                }
            }
        }
        if (!empty($aliasValues)) {
            foreach ($aliasValues as $ak => $av) {
                $valoresPorCodigo[$ak] = $av;
                $valoresPorCodigoInsensitive[strtolower((string) $ak)] = $av;
            }
        }

        $expr = preg_replace_callback('/\b[a-zA-Z_][a-zA-Z0-9_]*\b/', function ($m) use ($valoresPorCodigo, $valoresPorCodigoInsensitive, $valoresPorCodigoNormalized, $normKey) {
            $token = $m[0];
            $tl = strtolower($token);
            if ($tl === 'max' || $tl === 'min') {
                return $token;
            }
            if (array_key_exists($token, $valoresPorCodigo) && is_numeric($valoresPorCodigo[$token])) {
                return (string) ((float) $valoresPorCodigo[$token]);
            }
            if (array_key_exists($tl, $valoresPorCodigoInsensitive) && is_numeric($valoresPorCodigoInsensitive[$tl])) {
                return (string) ((float) $valoresPorCodigoInsensitive[$tl]);
            }
            $tn = $normKey($token);
            if ($tn !== '' && array_key_exists($tn, $valoresPorCodigoNormalized) && is_numeric($valoresPorCodigoNormalized[$tn])) {
                return (string) ((float) $valoresPorCodigoNormalized[$tn]);
            }

            return '0';
        }, $expr);

        try {
            $expr = preg_replace('/\s+/', '', $expr);
            $resultado = $this->avaliarExpressaoComMaxMin($expr);
            return ['ok' => true, 'valor' => round((float) $resultado, 2), 'expressao' => $expr];
        } catch (Throwable $e) {
            return ['ok' => false, 'erro' => 'Erro na fórmula final: ' . $e->getMessage()];
        }
    }

    /**
     * Avalia expressão após substituição dos códigos dos componentes; permite max(a,b) e min(a,b) aninhados.
     */
    private function avaliarExpressaoComMaxMin(string $expr): float
    {
        $expr = trim($expr);
        $guard = 0;
        while (preg_match('/\b(max|min)\s*\(/i', $expr)) {
            if (++$guard > 200) {
                throw new RuntimeException('Limite de aninhamento em max/min.');
            }
            $expr = $this->substituirPrimeiraFuncaoMaxMin($expr);
        }
        if ($expr === '' || !preg_match('/^[0-9\.\+\-\*\/\(\)]+$/', $expr)) {
            throw new RuntimeException('Fórmula contém caracteres inválidos após max/min.');
        }

        return $this->safeEval($expr);
    }

    private function substituirPrimeiraFuncaoMaxMin(string $expr): string
    {
        if (!preg_match('/\b(max|min)\s*\(/i', $expr, $m, PREG_OFFSET_CAPTURE)) {
            return $expr;
        }
        $fn = strtolower($m[1][0]);
        $startFn = (int) $m[0][1];
        $openParen = $startFn + strlen($m[0][0]) - 1;
        [$commaPos, $closePos] = $this->localizarArgumentosMaxMin($expr, $openParen);
        if ($commaPos === null) {
            throw new RuntimeException('Função max/min precisa de dois argumentos separados por vírgula.');
        }
        $left = substr($expr, $openParen + 1, $commaPos - $openParen - 1);
        $right = substr($expr, $commaPos + 1, $closePos - $commaPos - 1);
        $lv = $this->avaliarExpressaoComMaxMin($left);
        $rv = $this->avaliarExpressaoComMaxMin($right);
        $val = $fn === 'max' ? max($lv, $rv) : min($lv, $rv);

        return substr($expr, 0, $startFn) . (string) $val . substr($expr, $closePos + 1);
    }

    /**
     * @return array{0: ?int, 1: int} [commaPos or null, closeParenPos]
     */
    private function localizarArgumentosMaxMin(string $expr, int $openParenIdx): array
    {
        $n = strlen($expr);
        $i = $openParenIdx + 1;
        $depth = 1;
        $commaPos = null;
        for (; $i < $n; $i++) {
            $c = $expr[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return [$commaPos, $i];
                }
            } elseif ($c === ',' && $depth === 1) {
                $commaPos = $i;
            }
        }

        return [null, $n - 1];
    }

    private function safeEval(string $expr): float
    {
        set_error_handler(function ($severity, $message) {
            throw new RuntimeException($message, $severity);
        });

        try {
            $result = 0.0;
            // Expressão sanitizada em avaliarFormula (somente números e operadores).
            eval('$result = ' . $expr . ';');
            return (float) $result;
        } finally {
            restore_error_handler();
        }
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

    private function deduplicarNotasPorMateria(array $notas): array
    {
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
                $porMateriaEProva[$k] = ['valor' => 0.0, 'materia_id' => $materiaId, 'materia_nome' => $materiaNome];
            }
            $porMateriaEProva[$k]['valor'] += $valor;
            if (($porMateriaEProva[$k]['materia_nome'] ?? '') === '' && $materiaNome !== '') {
                $porMateriaEProva[$k]['materia_nome'] = $materiaNome;
            }
        }

        $saida = [];
        foreach ($porMateriaEProva as $item) {
            $saida[] = [
                'valor' => (float) ($item['valor'] ?? 0),
                'materia_id' => (int) ($item['materia_id'] ?? 0),
                'materia_nome' => (string) ($item['materia_nome'] ?? ''),
            ];
        }
        foreach ($semMateria as $item) {
            $saida[] = $item;
        }

        return $saida;
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
        $rows = $this->boletimConfig->getStudentsList(1000);
        foreach ($rows as $aluno) {
            if ((int) ($aluno['id'] ?? 0) === $alunoId) {
                return $aluno;
            }
        }

        return null;
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
        $raw = $componente['config_json'] ?? '';
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode(trim((string) $raw), true);
        }
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $formulaMode = strtolower(trim((string) ($decoded['formula_mode'] ?? '')));
        if ($formulaMode === '' && isset($componente['config']) && is_array($componente['config'])) {
            $formulaMode = strtolower(trim((string) ($componente['config']['formula_mode'] ?? '')));
        }
        if ($formulaMode === 'single') {
            return [];
        }
        $map = $decoded['formula_materias'] ?? [];
        if (!is_array($map) && isset($componente['config']) && is_array($componente['config'])) {
            $map = $componente['config']['formula_materias'] ?? [];
        }
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
            return [];
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
            foreach (array_keys($matrizPorCodigo[$rc] ?? []) as $mid) {
                $cell = $matrizPorCodigo[$rc][$mid] ?? null;
                if (is_numeric($cell)) {
                    $unionMids[(int) $mid] = true;
                }
            }
        }

        $out = [];
        foreach (array_keys($unionMids) as $mid) {
            $valoresFormula = [];
            foreach ($refs as $rc) {
                $map = $matrizPorCodigo[$rc] ?? [];
                $valoresFormula[$rc] = (isset($map[$mid]) && is_numeric($map[$mid])) ? (float) $map[$mid] : 0.0;
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
     * Replica a nota única de Jornadas em todas as linhas da matriz (omitir / substituição / padrão),
     * igual ao passo usado na montagem da tabela. Deve rodar antes de {@see matrizColunaCalculada}
     * para que expressões como (bimestral + jornadas) / 2 enxerguem o valor de jornadas em cada matéria.
     *
     * @param array<int|string, mixed> $componentesRegra
     * @param array<string, array<int, float|null>> $matrizPorCodigo
     * @param array<int|string, mixed> $componentesResultado
     * @param array<int, true> $allMids
     */
    private function aplicarEspalhamentoJornadasNotaUnicaNaMatriz(
        array $componentesRegra,
        array &$matrizPorCodigo,
        array $componentesResultado,
        array $allMids,
        string $roundMode
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
            foreach (array_keys($allMids) as $midRep) {
                $midRep = (int) $midRep;
                if (isset($omitSet[$midRep])) {
                    $matrizPorCodigo[$codJr][$midRep] = null;
                    continue;
                }
                if (array_key_exists($midRep, $substNorm)) {
                    $sv = $substNorm[$midRep];
                    $matrizPorCodigo[$codJr][$midRep] = is_numeric($sv) ? $this->applyRoundMode((float) $sv, $roundModeJr) : null;
                    continue;
                }
                $matrizPorCodigo[$codJr][$midRep] = $padrao;
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
     * @return array{key:string,label:string,mode:string,divisor:float,materias_ids:list<int>,usar_percentual:bool,source_type:string}|null
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

        return [
            'key' => $key,
            'label' => $label,
            'mode' => $mode,
            'divisor' => $divisor,
            'materias_ids' => $ids,
            'usar_percentual' => !empty($componente['usar_percentual']),
            'source_type' => strtolower(trim((string) ($componente['source_type'] ?? 'provas_sistema'))),
        ];
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
        $allowedGroups = BoletimQuadroLayoutHelper::gruposPermitidos();
        $allowedTypes = BoletimQuadroLayoutHelper::tiposPermitidos();
        if (!in_array($group, $allowedGroups, true)) {
            $group = '';
        }
        if (!in_array($type, $allowedTypes, true)) {
            $type = '';
        }
        if ($group === '' && $type === '') {
            return null;
        }

        return ['group' => $group, 'type' => ($type !== '' ? $type : 'other')];
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
        $allowedGroups = BoletimQuadroLayoutHelper::gruposPermitidos();
        $allowedTypes = BoletimQuadroLayoutHelper::tiposPermitidos();
        if (!in_array($group, $allowedGroups, true)) {
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
            if ($cod !== '' && !preg_match('/^s[1-8]$/', $cod)) {
                $codigoSemanal = $cod;
                break;
            }
        }

        $semanas = $this->semanasQuadroNaSimulacao();
        $novos = BoletimQuadroLayoutHelper::expandirComponentesParaQuadroSemanal(
            $componentes,
            $semanas['a'],
            $semanas['b']
        );
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
    private function semanasQuadroNaSimulacao(): array
    {
        $a = [1, 3, 5, 7];
        $b = [2, 4, 6, 8];
        $path = dirname(__DIR__, 2) . '/Modulos/notas-semanais/Models/NotasSemanaisConfig.php';
        if (!class_exists('NotasSemanaisConfig', false) && is_file($path)) {
            require_once $path;
        }
        if (!class_exists('NotasSemanaisConfig', false)) {
            return ['a' => $a, 'b' => $b];
        }
        try {
            $cfg = (new NotasSemanaisConfig())->obter();
            $sa = is_array($cfg['semanas_grupo_a'] ?? null) ? $cfg['semanas_grupo_a'] : $a;
            $sb = is_array($cfg['semanas_grupo_b'] ?? null) ? $cfg['semanas_grupo_b'] : $b;
            if ($sa !== []) {
                $a = array_values(array_map('intval', $sa));
            }
            if ($sb !== []) {
                $b = array_values(array_map('intval', $sb));
            }
        } catch (Throwable $e) {
            error_log('BoletimConfig semanas quadro: ' . $e->getMessage());
        }

        return ['a' => $a, 'b' => $b];
    }

    private function parseSemanaFromComponente(array $componente): int
    {
        $cfg = $this->decodeComponenteConfig($componente);
        $s = (int) ($cfg['semana'] ?? $componente['semana'] ?? 0);

        return ($s >= 1 && $s <= 8) ? $s : 0;
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
     * @return array{enabled:bool,key:string,label:string,mode:string,divisor:float,materias_ids:list<int>}|null
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

        return [
            'enabled' => true,
            'key' => $key,
            'label' => $label,
            'mode' => $mode,
            'divisor' => $divisor,
            'materias_ids' => $ids,
        ];
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
}
