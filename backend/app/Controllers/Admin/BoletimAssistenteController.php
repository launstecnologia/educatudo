<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../../Services/BoletimAssistenteService.php';
require_once __DIR__ . '/../../Services/BoletimAssistenteWizard.php';
require_once __DIR__ . '/BoletimConfigController.php';

/**
 * Endpoints JSON do Assistente de Boletim (chat NL → rascunho de regra).
 */
class BoletimAssistenteController extends BaseController
{
    private const LIMITE_ESTADO_BYTES = 250000;
    private const LIMITE_HISTORICO_BYTES = 80000;

    private AuthManager $auth;
    private BoletimAssistenteService $assistente;
    private BoletimAssistenteWizard $wizard;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $user = $this->auth->getUser();
        if (!$this->usuarioPodeConfigurarBoletim($user)) {
            $this->json(['success' => false, 'error' => 'Acesso negado.'], 403);
        }
        $this->assistente = new BoletimAssistenteService();
        $this->wizard = new BoletimAssistenteWizard($this->assistente->ferramentas());
    }

    public function contexto(): void
    {
        $ferramentas = $this->assistente->ferramentas();
        $this->json([
            'success' => true,
            'disponivel' => CreditosModuleRegistry::acaoIaDisponivel(BoletimAssistenteService::MODULO_CREDITOS),
            'catalogo' => $ferramentas->montarContextoCatalogo(),
            'wizard' => $this->wizard->catalogo(),
        ]);
    }

    /**
     * Wizard pedagógico: monta rascunho sem OpenAI / sem TudiCoins.
     */
    public function wizardMontar(): void
    {
        $token = (string) ($_POST['_token'] ?? '');
        if (!$this->verifyCsrfToken($token)) {
            $this->json(['success' => false, 'error' => 'CSRF inválido. Recarregue a página.'], 419);
        }

        $estado = [];
        $raw = $_POST['wizard_estado'] ?? '';
        if (is_string($raw) && $raw !== '') {
            if (strlen($raw) > self::LIMITE_ESTADO_BYTES) {
                $this->json(['success' => false, 'error' => 'Estado do wizard muito grande.'], 400);
            }
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                $estado = $dec;
            }
        } elseif (is_array($_POST['wizard_estado'] ?? null)) {
            $estado = $_POST['wizard_estado'];
        }

        $this->soltarSessao();

        try {
            $resultado = $this->wizard->montar($estado);
        } catch (Throwable $e) {
            error_log('BoletimAssistente wizardMontar: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'error' => 'Não deu para montar o evento agora. Tente de novo ou volte nas peças.',
                'ok' => false,
                'estado' => is_array($estado) ? $estado : [],
                'rascunho' => null,
                'resumo' => 'Falha ao montar o rascunho.',
                'erros' => ['Falha ao montar o rascunho.'],
                'formulas_disponiveis' => [],
                'preview' => null,
            ]);
            return;
        }
        try {
            $resultado = $this->wizard->enriquecerSaida($resultado);
            $resultado = $this->aplicarPreviewRealAluno($resultado);
        } catch (Throwable $e) {
            error_log('BoletimAssistente preview: ' . $e->getMessage());
            $resultado['preview'] = $resultado['preview'] ?? null;
        }
        $this->json([
            'success' => true,
            'ok' => !empty($resultado['ok']),
            'estado' => $resultado['estado'] ?? $estado,
            'rascunho' => $resultado['rascunho'] ?? null,
            'resumo' => $resultado['resumo'] ?? '',
            'erros' => $resultado['erros'] ?? [],
            'formulas_disponiveis' => $resultado['formulas_disponiveis'] ?? [],
            'preview' => $resultado['preview'] ?? null,
        ]);
    }

    /**
     * Estado inicial + catálogo do wizard (sem créditos).
     */
    public function wizardInicio(): void
    {
        $token = (string) ($_POST['_token'] ?? '');
        if (!$this->verifyCsrfToken($token)) {
            $this->json(['success' => false, 'error' => 'CSRF inválido.'], 419);
        }

        $regraId = (int) ($_POST['regra_id'] ?? 0);
        $estadoForm = null;
        $estadoRaw = $_POST['estado_formulario'] ?? '';
        if (is_string($estadoRaw) && $estadoRaw !== '') {
            if (strlen($estadoRaw) > self::LIMITE_ESTADO_BYTES) {
                $this->json(['success' => false, 'error' => 'Estado do formulário muito grande.'], 400);
            }
            $dec = json_decode($estadoRaw, true);
            if (is_array($dec)) {
                $estadoForm = $dec;
            }
        }

        $this->soltarSessao();

        try {
            $estado = $this->wizard->estadoPadrao($estadoForm, $regraId > 0 ? $regraId : null);
            $catalogo = $this->wizard->catalogo();
            $preservado = is_array($estado['rascunho_preservado'] ?? null) ? $estado['rascunho_preservado'] : [];
            $querQuadro = in_array('semanal', (array) ($estado['pecas'] ?? []), true)
                || ($estado['modelo_key'] ?? '') === 'quadro_semanal';
            $rascunhoOut = $preservado !== [] ? $preservado : null;
            $resumoOut = $preservado !== []
                ? 'Há um rascunho no formulário. Ajuste pelo chat ou avance para revisar e aplicar.'
                : 'Monte as escolhas à esquerda ou descreva o quadro no chat.';
            $errosOut = [];
            $formulasOut = [];
            $previewOut = null;
            if ($preservado !== [] || $querQuadro) {
                $montado = $this->wizard->enriquecerSaida($this->wizard->montar($estado));
                $montado = $this->aplicarPreviewRealAluno($montado);
                $estado = $montado['estado'];
                $rascunhoOut = $montado['rascunho'];
                $resumoOut = (string) ($montado['resumo'] ?? $resumoOut);
                $errosOut = is_array($montado['erros'] ?? null) ? $montado['erros'] : [];
                $formulasOut = is_array($montado['formulas_disponiveis'] ?? null) ? $montado['formulas_disponiveis'] : [];
                $previewOut = $montado['preview'] ?? null;
            }
            $this->json([
                'success' => true,
                'disponivel_ia' => CreditosModuleRegistry::acaoIaDisponivel(BoletimAssistenteService::MODULO_CREDITOS),
                'catalogo' => $catalogo,
                'estado' => $estado,
                'rascunho' => $rascunhoOut,
                'resumo' => $resumoOut,
                'erros' => $errosOut,
                'formulas_disponiveis' => $formulasOut,
                'preview' => $previewOut ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('BoletimAssistente wizardInicio: ' . $e->getMessage());
            $this->json([
                'success' => true,
                'disponivel_ia' => CreditosModuleRegistry::acaoIaDisponivel(BoletimAssistenteService::MODULO_CREDITOS),
                'catalogo' => [
                    'passos' => BoletimAssistenteWizard::PASSOS,
                    'modelos' => [],
                    'formulas' => [],
                    'regras' => [],
                    'series' => [],
                    'turmas' => [],
                    'materias' => [],
                    'jornadas' => [],
                    'tipos_avaliacao' => [],
                    'eventos_prova' => [],
                    'pecas' => [],
                ],
                'estado' => $this->wizard->estadoPadrao(null, $regraId > 0 ? $regraId : null),
                'rascunho' => null,
                'resumo' => 'Catálogo parcial. Descreva o quadro no chat mesmo assim.',
                'erros' => ['Não deu para carregar turmas/matérias agora.'],
                'formulas_disponiveis' => [],
            ]);
        }
    }

    /**
     * Quando o assistente tem um aluno selecionado, a prévia precisa mostrar os
     * lançamentos reais calculados pela mesma rotina da tela de configuração.
     *
     * @param array<string,mixed> $resultado
     * @return array<string,mixed>
     */
    private function aplicarPreviewRealAluno(array $resultado): array
    {
        $estado = is_array($resultado['estado'] ?? null) ? $resultado['estado'] : [];
        $rascunho = is_array($resultado['rascunho'] ?? null) ? $resultado['rascunho'] : null;
        $alunoId = (int) ($estado['aluno_preview_id'] ?? 0);
        if ($alunoId <= 0 || $rascunho === null || empty($rascunho['componentes'])) {
            return $resultado;
        }

        [$periodoRef, $dataInicio, $dataFim] = $this->resolverPeriodoPreview($rascunho, $estado);

        try {
            $configController = new BoletimConfigController();
            $simulacao = $configController->simularRegraAluno($rascunho, $alunoId, $periodoRef, $dataInicio, $dataFim);
            $previewReal = $this->montarPreviewRealDaSimulacao($simulacao);
            if ($previewReal !== null) {
                $resultado['preview'] = $previewReal;
            } else {
                $resultado['preview'] = [
                    'modo' => 'vazio',
                    'aviso' => 'Aluno selecionado, mas não há notas reais para este evento no período configurado.',
                    'tabelas' => [],
                    'colunas' => [],
                    'pecas_disponiveis' => [],
                ];
            }
        } catch (Throwable $e) {
            error_log('BoletimAssistente preview real aluno #' . $alunoId . ': ' . $e->getMessage());
        }

        return $resultado;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array{0:string,1:?string,2:?string}
     */
    private function resolverPeriodoPreview(array $rascunho, array $estado): array
    {
        $dataInicio = $this->normalizarDataPreview((string) ($rascunho['default_data_inicio'] ?? $estado['data_inicio'] ?? ''));
        $dataFim = $this->normalizarDataPreview((string) ($rascunho['default_data_fim'] ?? $estado['data_fim'] ?? ''));
        if ($dataInicio !== null && $dataFim !== null) {
            if ($dataInicio > $dataFim) {
                [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
            }
            return ['RANGE:' . $dataInicio . ':' . $dataFim, $dataInicio, $dataFim];
        }

        $ano = (int) ($rascunho['ano_letivo'] ?? $estado['ano_letivo'] ?? date('Y'));
        $bimestre = (int) ($rascunho['bimestre'] ?? $estado['bimestre'] ?? 1);
        if ($ano <= 0) {
            $ano = (int) date('Y');
        }
        if ($bimestre < 1 || $bimestre > 4) {
            $bimestre = 1;
        }

        return [$ano . '-B' . $bimestre, null, null];
    }

    private function normalizarDataPreview(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * @param array<string,mixed> $simulacao
     * @return array<string,mixed>|null
     */
    private function montarPreviewRealDaSimulacao(array $simulacao): ?array
    {
        $matriz = is_array($simulacao['matriz_materias'] ?? null) ? $simulacao['matriz_materias'] : null;
        if ($matriz === null) {
            return null;
        }

        $colunasRaw = is_array($matriz['colunas'] ?? null) ? $matriz['colunas'] : [];
        $linhasRaw = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
        if ($colunasRaw === [] || $linhasRaw === []) {
            return null;
        }

        $colunas = [];
        $semanasA = [];
        $semanasB = [];
        $outras = [];
        $gruposBoletim = [];
        $temQuadro = false;
        $temBoletim = false;

        foreach ($colunasRaw as $colRaw) {
            if (!is_array($colRaw)) {
                continue;
            }
            $codigo = strtolower(trim((string) ($colRaw['codigo'] ?? '')));
            if ($codigo === '') {
                continue;
            }
            $col = [
                'codigo' => $codigo,
                'nome' => (string) ($colRaw['nome'] ?? $codigo),
                'layout_group' => strtolower(trim((string) ($colRaw['layout_group'] ?? ''))),
                'layout_type' => strtolower(trim((string) ($colRaw['layout_type'] ?? ''))),
                'source_type' => (string) ($colRaw['source_type'] ?? ''),
                'tipo' => ((string) ($colRaw['source_type'] ?? '')) === 'calculado' ? 'calculado' : 'peca',
                'travada' => false,
            ];

            if ($this->colunaPreviewEhSemana($col)) {
                $temQuadro = true;
                if ($col['layout_group'] === 'quadro_b' || preg_match('/^s[2468]$/', $codigo)) {
                    $semanasB[] = $col;
                } else {
                    $semanasA[] = $col;
                }
                continue;
            }

            if ($this->colunaPreviewEhBoletim($col)) {
                $temBoletim = true;
                $grupoKey = $col['layout_group'] !== '' ? $col['layout_group'] : 'outros';
                if (!isset($gruposBoletim[$grupoKey])) {
                    $gruposBoletim[$grupoKey] = [
                        'key' => $grupoKey,
                        'label' => $this->labelGrupoBoletimPreview($grupoKey),
                        'cols' => [],
                    ];
                }
                $gruposBoletim[$grupoKey]['cols'][] = $col;
            } else {
                $outras[] = $col;
            }

            $colunas[] = $col;
        }

        $linhas = [];
        foreach ($linhasRaw as $linhaRaw) {
            if (!is_array($linhaRaw)) {
                continue;
            }
            $nome = trim((string) ($linhaRaw['materia_nome'] ?? ''));
            if ($nome === '') {
                $nome = 'Matéria';
            }
            $linhas[] = [
                'materia_nome' => $nome,
                'notas' => is_array($linhaRaw['notas'] ?? null) ? $linhaRaw['notas'] : [],
            ];
        }
        if ($linhas === []) {
            return null;
        }

        if ($temBoletim && !$temQuadro) {
            $grupos = array_values($gruposBoletim);
            return [
                'modo' => 'boletim',
                'dados_reais' => true,
                'aviso' => 'Prévia com notas reais do aluno selecionado.',
                'grupos' => $grupos,
                'tabelas' => [[
                    'key' => 'u',
                    'titulo' => 'Matérias',
                    'subtitulo' => '',
                    'semanas' => [],
                    'outras' => [],
                    'grupos' => $grupos,
                    'linhas' => $linhas,
                ]],
                'colunas' => $colunas,
                'pecas_disponiveis' => [],
            ];
        }

        if ($temQuadro) {
            $previewCols = [[
                'codigo' => '_semanal',
                'nome' => 'Prova semanal (S1-S8 · N e Q)',
                'tipo' => 'semana_grupo',
                'travada' => true,
            ]];
            $previewCols = array_merge($previewCols, $outras);
            $tabelas = [];
            if ($semanasA !== []) {
                $tabelas[] = [
                    'key' => 'a',
                    'titulo' => 'Matérias Bloco A',
                    'subtitulo' => 'Prova semanal',
                    'semanas' => $semanasA,
                    'outras' => $outras,
                    'linhas' => $this->filtrarLinhasPreviewPorSemanas($linhas, $semanasA),
                ];
            }
            if ($semanasB !== []) {
                $tabelas[] = [
                    'key' => 'b',
                    'titulo' => 'Matérias Bloco B',
                    'subtitulo' => 'Prova semanal',
                    'semanas' => $semanasB,
                    'outras' => $outras,
                    'linhas' => $this->filtrarLinhasPreviewPorSemanas($linhas, $semanasB),
                ];
            }

            return [
                'modo' => 'quadro',
                'dados_reais' => true,
                'aviso' => 'Prévia com notas reais do aluno selecionado.',
                'tabelas' => $tabelas,
                'colunas' => $previewCols,
                'pecas_disponiveis' => [],
            ];
        }

        return [
            'modo' => 'simples',
            'dados_reais' => true,
            'aviso' => 'Prévia com notas reais do aluno selecionado.',
            'tabelas' => [[
                'key' => 'u',
                'titulo' => 'Matérias',
                'subtitulo' => '',
                'semanas' => [],
                'outras' => $outras !== [] ? $outras : $colunas,
                'linhas' => $linhas,
            ]],
            'colunas' => $colunas,
            'pecas_disponiveis' => [],
        ];
    }

    /** @param array<string,mixed> $col */
    private function colunaPreviewEhSemana(array $col): bool
    {
        $codigo = strtolower(trim((string) ($col['codigo'] ?? '')));
        $layoutType = strtolower(trim((string) ($col['layout_type'] ?? '')));
        $layoutGroup = strtolower(trim((string) ($col['layout_group'] ?? '')));

        return preg_match('/^s[1-8]$/', $codigo) === 1
            || $layoutType === 'semana_nq'
            || in_array($layoutGroup, ['quadro_a', 'quadro_b'], true);
    }

    /** @param array<string,mixed> $col */
    private function colunaPreviewEhBoletim(array $col): bool
    {
        $layoutGroup = strtolower(trim((string) ($col['layout_group'] ?? '')));
        return in_array($layoutGroup, ['b1', 'b2', 'b3', 'b4', 'final'], true);
    }

    private function labelGrupoBoletimPreview(string $grupo): string
    {
        $labels = [
            'b1' => '1º Bimestre',
            'b2' => '2º Bimestre',
            'b3' => '3º Bimestre',
            'b4' => '4º Bimestre',
            'final' => 'Final',
        ];

        return $labels[$grupo] ?? $grupo;
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @param list<array<string,mixed>> $semanas
     * @return list<array<string,mixed>>
     */
    private function filtrarLinhasPreviewPorSemanas(array $linhas, array $semanas): array
    {
        $codigos = array_values(array_filter(array_map(static function ($semana) {
            return strtolower(trim((string) ($semana['codigo'] ?? '')));
        }, $semanas)));
        if ($codigos === []) {
            return $linhas;
        }

        $filtradas = [];
        foreach ($linhas as $linha) {
            $notas = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
            foreach ($codigos as $codigo) {
                if (array_key_exists($codigo, $notas)
                    || array_key_exists($codigo . '__n', $notas)
                    || array_key_exists($codigo . '__q', $notas)) {
                    $filtradas[] = $linha;
                    break;
                }
            }
        }

        return $filtradas !== [] ? $filtradas : $linhas;
    }

    /**
     * Extrai corpo comum das requests de mensagem (CSRF já validado pelo caller).
     *
     * @return array{mensagem:string,historico:list,estado:?array,wizard:?array,regra_id:int}
     */
    private function lerPayloadMensagem(): array
    {
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        $limiteMsg = $this->assistente->mensagemPareceReceita($mensagem) ? 12000 : 8000;
        if (mb_strlen($mensagem) > $limiteMsg) {
            $this->json(['success' => false, 'error' => 'Mensagem muito longa (máx. ' . $limiteMsg . ' caracteres).'], 400);
        }

        $historico = [];
        $histRaw = $_POST['historico'] ?? '[]';
        if (is_string($histRaw)) {
            if (strlen($histRaw) > self::LIMITE_HISTORICO_BYTES) {
                $this->json(['success' => false, 'error' => 'Histórico muito grande.'], 400);
            }
            $dec = json_decode($histRaw, true);
            if (is_array($dec)) {
                $historico = $dec;
            }
        } elseif (is_array($histRaw)) {
            $historico = $histRaw;
        }

        $estado = null;
        $estadoRaw = $_POST['estado_formulario'] ?? '';
        if (is_string($estadoRaw) && $estadoRaw !== '') {
            if (strlen($estadoRaw) > self::LIMITE_ESTADO_BYTES) {
                $this->json(['success' => false, 'error' => 'Estado do formulário muito grande.'], 400);
            }
            $decEstado = json_decode($estadoRaw, true);
            if (is_array($decEstado)) {
                $estado = $decEstado;
            }
        }

        $wizard = null;
        $wizardRaw = $_POST['wizard_estado'] ?? '';
        if (is_string($wizardRaw) && $wizardRaw !== '') {
            if (strlen($wizardRaw) > self::LIMITE_ESTADO_BYTES) {
                $this->json(['success' => false, 'error' => 'Estado do wizard muito grande.'], 400);
            }
            $decW = json_decode($wizardRaw, true);
            if (is_array($decW)) {
                $wizard = $decW;
            }
        }

        return [
            'mensagem' => $mensagem,
            'historico' => $historico,
            'estado' => $estado,
            'wizard' => $wizard,
            'regra_id' => (int) ($_POST['regra_id'] ?? 0),
        ];
    }

    private function assertAssistenteIaDisponivel(): void
    {
        if (!CreditosModuleRegistry::acaoIaDisponivel(BoletimAssistenteService::MODULO_CREDITOS)) {
            $this->json([
                'success' => false,
                'error' => 'Assistente de boletim indisponível. Ative TudiCoins para esta escola no Master.',
            ], 403);
        }
    }

    public function mensagem(): void
    {
        $token = (string) ($_POST['_token'] ?? '');
        if (!$this->verifyCsrfToken($token)) {
            $this->json(['success' => false, 'error' => 'CSRF inválido. Recarregue a página.'], 419);
        }

        $payload = $this->lerPayloadMensagem();
        $atalho = $this->assistente->tentarAtalhoReceitaPublico($payload['mensagem'], $payload['estado']);
        if ($atalho === null) {
            $this->assertAssistenteIaDisponivel();
        }

        $resultado = $atalho ?? $this->assistente->processarMensagem(
            $payload['mensagem'],
            $payload['historico'],
            $payload['estado'],
            $payload['regra_id'] > 0 ? $payload['regra_id'] : null,
            $payload['wizard']
        );

        $status = !empty($resultado['success']) ? 200 : 400;
        $this->json($resultado, $status);
    }

    /**
     * Streaming SSE: texto aparece aos poucos; ao final envia rascunho JSON.
     */
    public function mensagemStream(): void
    {
        $token = (string) ($_POST['_token'] ?? '');
        if (!$this->verifyCsrfToken($token)) {
            $this->json(['success' => false, 'error' => 'CSRF inválido. Recarregue a página.'], 419);
        }

        $payload = $this->lerPayloadMensagem();
        $this->soltarSessao();
        $atalho = $this->assistente->tentarAtalhoReceitaPublico($payload['mensagem'], $payload['estado']);

        @set_time_limit(200);
        @ini_set('max_execution_time', '200');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        $emit = static function (string $event, array $data): void {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === false) {
                $json = json_encode(['error' => 'Falha ao serializar a resposta da IA.'], JSON_UNESCAPED_UNICODE);
                $event = 'error';
            }
            echo 'event: ' . $event . "\n";
            echo 'data: ' . $json . "\n\n";
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
            @flush();
        };

        if ($atalho !== null) {
            $msg = (string) ($atalho['mensagem'] ?? '');
            if ($msg !== '') {
                $emit('chunk', ['text' => $msg]);
            }
            $emit('done', [
                'success' => true,
                'acao' => $atalho['acao'] ?? 'esclarecimento',
                'mensagem' => $atalho['mensagem'] ?? '',
                'rascunho' => $atalho['rascunho'] ?? null,
                'receita' => $atalho['receita'] ?? null,
                'erros' => $atalho['erros'] ?? [],
            ]);
            exit;
        }

        if (!CreditosModuleRegistry::acaoIaDisponivel(BoletimAssistenteService::MODULO_CREDITOS)) {
            $emit('error', [
                'error' => 'Assistente de boletim indisponível. Ative TudiCoins para esta escola no Master.',
            ]);
            exit;
        }

        $emit('fase', ['fase' => 'consultando']);

        try {
            $resultado = $this->assistente->processarMensagemStream(
                $payload['mensagem'],
                static function (string $chunk) use ($emit): void {
                    if ($chunk === '') {
                        return;
                    }
                    $emit('chunk', ['text' => $chunk]);
                },
                $payload['historico'],
                $payload['estado'],
                $payload['regra_id'] > 0 ? $payload['regra_id'] : null,
                $payload['wizard'],
                static function (string $fase) use ($emit): void {
                    $emit('fase', ['fase' => $fase]);
                }
            );
        } catch (Throwable $e) {
            error_log('BoletimAssistente mensagemStream: ' . $e->getMessage());
            $emit('error', ['error' => 'Falha ao consultar a IA. Tente de novo com um pedido mais curto.']);
            exit;
        }

        if (empty($resultado['success'])) {
            $emit('error', ['error' => $resultado['error'] ?? 'Falha no assistente.']);
            exit;
        }

        $emit('done', [
            'success' => true,
            'acao' => $resultado['acao'] ?? 'esclarecimento',
            'mensagem' => $resultado['mensagem'] ?? '',
            'rascunho' => $resultado['rascunho'] ?? null,
            'receita' => $resultado['receita'] ?? null,
            'erros' => $resultado['erros'] ?? [],
        ]);
        exit;
    }

    /**
     * Tools MCP / utilitários JSON (leitura).
     */
    public function ferramenta(): void
    {
        $token = (string) ($_POST['_token'] ?? '');
        if (!$this->verifyCsrfToken($token)) {
            $this->json(['success' => false, 'error' => 'CSRF inválido.'], 419);
        }

        $nome = trim((string) ($_POST['tool'] ?? ''));
        $ferramentas = $this->assistente->ferramentas();

        // Formatar receita só serializa o formulário local — não precisa de TudiCoins.
        if ($nome === 'formatar_receita') {
            $estado = null;
            $estadoRaw = $_POST['estado_formulario'] ?? '';
            if (is_string($estadoRaw) && $estadoRaw !== '') {
                $decEstado = json_decode($estadoRaw, true);
                if (is_array($decEstado)) {
                    $estado = $decEstado;
                }
            }
            if (!is_array($estado)) {
                $this->json(['success' => false, 'error' => 'Estado do formulário inválido.'], 400);
            }
            $texto = $ferramentas->formatarReceitaTexto($estado);
            $this->json(['success' => true, 'data' => ['receita' => $texto]]);
        }

        if (!CreditosModuleRegistry::acaoIaDisponivel(BoletimAssistenteService::MODULO_CREDITOS)) {
            $this->json([
                'success' => false,
                'error' => 'Assistente de boletim indisponível. Ative TudiCoins para esta escola no Master.',
            ], 403);
        }

        switch ($nome) {
            case 'listar_tipos_avaliacao':
                $this->json(['success' => true, 'data' => $ferramentas->listarTiposAvaliacao()]);
                break;
            case 'listar_turmas':
                $this->json(['success' => true, 'data' => $ferramentas->listarTurmas()]);
                break;
            case 'listar_materias':
                $this->json(['success' => true, 'data' => $ferramentas->listarMaterias()]);
                break;
            case 'listar_regras':
                $this->json(['success' => true, 'data' => $ferramentas->listarRegras()]);
                break;
            case 'listar_eventos_prova':
                $tipoId = isset($_POST['tipo_avaliacao_id']) ? (int) $_POST['tipo_avaliacao_id'] : null;
                $this->json(['success' => true, 'data' => $ferramentas->listarEventosProva($tipoId ?: null)]);
                break;
            case 'obter_regra':
                $id = (int) ($_POST['regra_id'] ?? 0);
                $regra = $ferramentas->obterRegra($id);
                $this->json(['success' => $regra !== null, 'data' => $regra]);
                break;
            case 'resolver_blocos_por_tipo':
                $tipo = $_POST['tipo'] ?? '';
                $ini = trim((string) ($_POST['data_inicio'] ?? ''));
                $fim = trim((string) ($_POST['data_fim'] ?? ''));
                $this->json([
                    'success' => true,
                    'data' => $ferramentas->resolverBlocosPorTipo($tipo, $ini !== '' ? $ini : null, $fim !== '' ? $fim : null),
                ]);
                break;
            default:
                $this->json(['success' => false, 'error' => 'Tool desconhecida.'], 400);
        }
    }

    /**
     * Libera o lock da sessão PHP para o chat e o wizard não se bloquearem.
     */
    private function soltarSessao(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

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
}
