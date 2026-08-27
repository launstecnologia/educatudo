<?php
/**
 * Wizard pedagógico do Assistente de Boletim.
 * Traduz escolhas em linguagem de coordenação → rascunho técnico (sem OpenAI).
 */

require_once __DIR__ . '/BoletimAssistenteFerramentas.php';

class BoletimAssistenteWizard
{
    public const PASSOS = [
        'inicio',
        'identidade',
        'pecas',
        'formula',
        'publico',
        'revisar',
    ];

    public const PAPEIS = ['media', 'depois', 'so_melhora', 'substitui', 'exibe'];

    private BoletimAssistenteFerramentas $ferramentas;

    public function __construct(?BoletimAssistenteFerramentas $ferramentas = null)
    {
        $this->ferramentas = $ferramentas ?? new BoletimAssistenteFerramentas();
    }

    public function ferramentas(): BoletimAssistenteFerramentas
    {
        return $this->ferramentas;
    }

    /**
     * @return array{passos:list<string>,modelos:list<array>,formulas:list<array>,regras:list<array>,series:list<array>,tipos_avaliacao:list<array>}
     */
    public function catalogo(): array
    {
        $regras = [];
        try {
            foreach ($this->ferramentas->listarRegras(200) as $r) {
                $regras[] = [
                    'id' => (int) ($r['id'] ?? 0),
                    'nome' => (string) ($r['nome'] ?? ''),
                    'codigo' => isset($r['codigo']) ? (string) $r['codigo'] : null,
                    'bimestre' => isset($r['bimestre']) ? (int) $r['bimestre'] : null,
                    'ano_letivo' => isset($r['ano_letivo']) ? (int) $r['ano_letivo'] : null,
                    'exibir_em' => isset($r['exibir_em']) ? (string) $r['exibir_em'] : null,
                    'formula_final' => isset($r['formula_final']) ? (string) $r['formula_final'] : null,
                ];
            }
        } catch (Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo regras: ' . $e->getMessage());
        }

        $series = [];
        $turmas = [];
        try {
            $turmasRaw = $this->ferramentas->listarTurmas(300);
            $porSerie = [];
            foreach ($turmasRaw as $t) {
                $sid = (int) ($t['serie_id'] ?? 0);
                if ($sid > 0 && !isset($porSerie[$sid])) {
                    $porSerie[$sid] = [
                        'id' => $sid,
                        'nome' => trim((string) ($t['serie_nome'] ?? ('Série #' . $sid))),
                        'curso_nome' => trim((string) ($t['curso_nome'] ?? '')),
                    ];
                }
                $turmas[] = [
                    'id' => (int) ($t['id'] ?? 0),
                    'nome' => trim((string) ($t['nome'] ?? '')),
                    'serie_id' => isset($t['serie_id']) ? (int) $t['serie_id'] : null,
                    'serie_nome' => isset($t['serie_nome']) ? trim((string) $t['serie_nome']) : null,
                    'ano_letivo' => isset($t['ano_letivo']) ? (int) $t['ano_letivo'] : null,
                ];
            }
            $series = array_values($porSerie);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo turmas: ' . $e->getMessage());
        }

        $materias = [];
        try {
            foreach ($this->ferramentas->listarMaterias(200) as $m) {
                $materias[] = [
                    'id' => (int) ($m['id'] ?? 0),
                    'nome' => (string) ($m['nome'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo materias: ' . $e->getMessage());
        }

        $alunos = [];
        try {
            $alunos = $this->ferramentas->listarAlunos(400);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo alunos: ' . $e->getMessage());
        }

        $jornadas = [];
        try {
            $jornadas = $this->ferramentas->listarJornadas(120);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo jornadas: ' . $e->getMessage());
        }

        $tipos = [];
        try {
            $tipos = $this->ferramentas->listarTiposAvaliacao();
        } catch (Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo tipos: ' . $e->getMessage());
        }

        $eventosProva = [];
        try {
            $eventosProva = $this->ferramentas->listarEventosProva(null, 500);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo eventos prova: ' . $e->getMessage());
        }

        $faltasEventos = [];
        try {
            $faltasEventos = $this->ferramentas->listarFaltasEventos(300);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteWizard catalogo faltas: ' . $e->getMessage());
        }

        return [
            'passos' => self::PASSOS,
            'modelos' => $this->modelosProntos(),
            'formulas' => $this->formulasPresetMeta(),
            'regras' => $regras,
            'series' => $series,
            'turmas' => $turmas,
            'materias' => $materias,
            'alunos' => $alunos,
            'jornadas' => $jornadas,
            'tipos_avaliacao' => $tipos,
            'eventos_prova' => $eventosProva,
            'faltas_eventos' => $faltasEventos,
            'pecas' => $this->pecasMeta($tipos),
            'papeis' => self::papeisMeta(),
        ];
    }

    /**
     * Estado inicial do wizard (opcionalmente a partir do formulário aberto).
     *
     * @param array<string,mixed>|null $estadoFormulario
     * @return array<string,mixed>
     */
    public function estadoPadrao(?array $estadoFormulario = null, ?int $regraIdAtual = null): array
    {
        $ano = (int) date('Y');
        $estado = [
            'passo' => 'inicio',
            'origem' => 'zero',
            'modelo_key' => '',
            'clonar_regra_id' => null,
            'modo' => ($regraIdAtual !== null && $regraIdAtual > 0) ? 'editar' : 'criar',
            'regra_id' => ($regraIdAtual !== null && $regraIdAtual > 0) ? $regraIdAtual : null,
            'nome' => '',
            'codigo' => '',
            'ano_letivo' => $ano,
            'bimestre' => 1,
            'exibir_em' => 'notas',
            'nota_minima_aprovacao' => 7.0,
            'round_mode' => 'half',
            'pecas' => [],
            'pecas_opcoes' => $this->pecasOpcoesPadrao([]),
            'materia_unica' => 0,
            'materia_unica_tocada' => false,
            'formula_preset' => 'media_simples',
            'formula_custom' => '',
            'formula_tokens' => [],
            'formulas_blocos' => [],
            'formulas_materias_blocos' => [],
            'nomes_blocos' => [],
            'bloco_calc' => '',
            'materia_calc' => 0,
            'blocos_calc' => [],
            'colunas_ordem' => [],
            'fontes_bimestres' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
            'fontes_faltas' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
            'data_inicio' => '',
            'data_fim' => '',
            'jornada_modo' => 'bimestre',
            'jornada_ids' => [],
            'jornada_bimestres' => [],
            'jornada_nota_modo' => 'linear',
            'jornada_faixas' => self::faixasJornadaPadrao(),
            'series_ids' => [],
            'turmas_ids' => [],
            'materias_ids' => [],
            'aluno_preview_id' => 0,
            'grupo_linha' => $this->grupoLinhaPadrao(),
            'rascunho_preservado' => null,
        ];

        if (
            (!is_array($estadoFormulario) || $estadoFormulario === [] || $this->estadoFormularioEstaVazio($estadoFormulario))
            && $regraIdAtual !== null
            && $regraIdAtual > 0
        ) {
            $regraExistente = $this->ferramentas->obterRegra($regraIdAtual);
            if (is_array($regraExistente)) {
                $estadoFormulario = $regraExistente;
            }
        }

        if (is_array($estadoFormulario)) {
            if (trim((string) ($estadoFormulario['nome'] ?? '')) !== '') {
                $estado['nome'] = trim((string) $estadoFormulario['nome']);
            }
            if (trim((string) ($estadoFormulario['codigo'] ?? '')) !== '') {
                $estado['codigo'] = trim((string) $estadoFormulario['codigo']);
            }
            if (!empty($estadoFormulario['ano_letivo'])) {
                $estado['ano_letivo'] = (int) $estadoFormulario['ano_letivo'];
            }
            if (isset($estadoFormulario['bimestre']) && $estadoFormulario['bimestre'] !== '') {
                $estado['bimestre'] = (int) $estadoFormulario['bimestre'];
            }
            if (!empty($estadoFormulario['exibir_em'])) {
                $estado['exibir_em'] = (string) $estadoFormulario['exibir_em'];
            }
            if (isset($estadoFormulario['nota_minima_aprovacao']) && $estadoFormulario['nota_minima_aprovacao'] !== '') {
                $estado['nota_minima_aprovacao'] = (float) $estadoFormulario['nota_minima_aprovacao'];
            }
            if (!empty($estadoFormulario['round_mode'])) {
                $estado['round_mode'] = (string) $estadoFormulario['round_mode'];
            }
            $estado['series_ids'] = array_values(array_map('intval', (array) ($estadoFormulario['series_ids'] ?? [])));
            $estado['turmas_ids'] = array_values(array_map('intval', (array) ($estadoFormulario['turmas_ids'] ?? [])));
            $estado['materias_ids'] = array_values(array_map('intval', (array) ($estadoFormulario['materias_ids'] ?? [])));
            if (!empty($estadoFormulario['default_data_inicio'])) {
                $estado['data_inicio'] = substr((string) $estadoFormulario['default_data_inicio'], 0, 10);
            }
            if (!empty($estadoFormulario['default_data_fim'])) {
                $estado['data_fim'] = substr((string) $estadoFormulario['default_data_fim'], 0, 10);
            }

            $comps = is_array($estadoFormulario['componentes'] ?? null) ? $estadoFormulario['componentes'] : [];
            if ($comps !== []) {
                $estado['origem'] = 'formulario';
                $estado['pecas'] = $this->inferirPecasDeComponentes($comps);
                $estado['pecas_opcoes'] = $this->inferirPecasOpcoesDeComponentes(
                    $comps,
                    $estado['pecas'],
                    (string) ($estadoFormulario['formula_final'] ?? '')
                );
                $estado['materia_unica'] = $this->inferirMateriaUnicaDeComponentes($comps);
                $this->aplicarMateriaUnicaNasPecasOpcoes($estado);
                $this->aplicarJornadaDoFormulario($estado, $comps);
                $this->aplicarGrupoLinhaDoFormulario($estado, $comps);
                $estado['passo'] = 'revisar';
                $estado['rascunho_preservado'] = [
                    'modo' => ($regraIdAtual !== null && $regraIdAtual > 0) ? 'editar' : 'criar',
                    'regra_id' => ($regraIdAtual !== null && $regraIdAtual > 0) ? $regraIdAtual : null,
                    'nome' => $estado['nome'],
                    'codigo' => $estado['codigo'],
                    'formula_final' => (string) ($estadoFormulario['formula_final'] ?? ''),
                    'exibir_em' => $estado['exibir_em'],
                    'ano_letivo' => $estado['ano_letivo'],
                    'bimestre' => $estado['bimestre'] > 0 ? $estado['bimestre'] : null,
                    'turmas_ids' => $estado['turmas_ids'],
                    'materias_ids' => $estado['materias_ids'],
                    'series_ids' => $estado['series_ids'],
                    'round_mode' => $estado['round_mode'],
                    'nota_minima_aprovacao' => $estado['nota_minima_aprovacao'],
                    'componentes' => $comps,
                ];
                $estado['fontes_bimestres'] = $this->inferirFontesBimestresDeComponentes($comps);
                $estado['fontes_faltas'] = $this->inferirFontesFaltasDeComponentes($comps);
            }
        }

        if ($estado['nome'] === '') {
            $bim = (int) $estado['bimestre'];
            $estado['nome'] = $bim > 0
                ? sprintf('Notas Bimestrais — %dº bimestre %d', $bim, (int) $estado['ano_letivo'])
                : sprintf('Notas — %d', (int) $estado['ano_letivo']);
        }

        return $estado;
    }

    /**
     * @param array<string,mixed> $estadoFormulario
     */
    private function estadoFormularioEstaVazio(array $estadoFormulario): bool
    {
        $camposTexto = ['nome', 'codigo', 'formula_final', 'exibir_em', 'ano_letivo', 'bimestre'];
        foreach ($camposTexto as $campo) {
            if (trim((string) ($estadoFormulario[$campo] ?? '')) !== '') {
                return false;
            }
        }

        foreach (['componentes', 'series_ids', 'turmas_ids', 'materias_ids'] as $campo) {
            if (!empty($estadoFormulario[$campo])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $estado
     * @return array{ok:bool,estado:array,rascunho:?array,resumo:string,erros:list<string>,formulas_disponiveis:list<array>}
     */
    public function montar(array $estado): array
    {
        $estado = $this->normalizarEstado($estado);
        $erros = $this->errosGrupoLinha($estado);

        if ($estado['origem'] === 'clonar' && !empty($estado['clonar_regra_id'])) {
            $base = $this->ferramentas->obterRegra((int) $estado['clonar_regra_id']);
            if ($base === null) {
                return [
                    'ok' => false,
                    'estado' => $estado,
                    'rascunho' => null,
                    'resumo' => 'Não encontrei o evento para clonar.',
                    'erros' => ['Evento de origem não encontrado.'],
                    'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
                ];
            }
            $rascunho = $this->rascunhoAPartirDeRegra($base, $estado);
            if ($this->grupoLinhaEstaVazio($estado)) {
                $this->aplicarGrupoLinhaDoFormulario(
                    $estado,
                    is_array($base['componentes'] ?? null) ? $base['componentes'] : []
                );
            }
            if (empty($estado['materia_unica_tocada'])) {
                $estado['materia_unica'] = $this->inferirMateriaUnicaDeComponentes(
                    is_array($base['componentes'] ?? null) ? $base['componentes'] : []
                );
                $this->aplicarMateriaUnicaNasPecasOpcoes($estado);
            }
            if ($estado['series_ids'] === [] && is_array($rascunho['series_ids'] ?? null)) {
                $estado['series_ids'] = array_values(array_filter(array_map('intval', (array) $rascunho['series_ids'])));
            }
            if ($estado['turmas_ids'] === [] && is_array($rascunho['turmas_ids'] ?? null)) {
                $estado['turmas_ids'] = array_values(array_filter(array_map('intval', (array) $rascunho['turmas_ids'])));
            }
            $rascunho = $this->aplicarMateriaUnicaNoRascunho(
                $this->aplicarGrupoLinhaNoRascunho($rascunho, $estado),
                $estado
            );
            if (!$this->deveRemontarComoQuadro($estado, $rascunho)) {
                $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
                $rascunhoOk = $this->aplicarLayoutBimestreNoRascunhoNotas($validado['rascunho'], $estado);
                $resumo = $this->montarResumoHumano($rascunhoOk);
                $errosOut = array_values(array_unique(array_merge($erros, $validado['erros'])));
                return [
                    'ok' => $rascunhoOk['componentes'] !== [] && $errosOut === [],
                    'estado' => $estado,
                    'rascunho' => $rascunhoOk,
                    'resumo' => $resumo,
                    'erros' => $errosOut,
                    'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
                ];
            }
        }

        // Edição do formulário já aberto / ajuste via chat: preserva componentes técnicos,
        // salvo quando a prova semanal precisa virar o quadro S1–S8 (layout Colag).
        if (in_array($estado['origem'], ['formulario', 'chat'], true)
            && !empty($estado['rascunho_preservado'])
            && is_array($estado['rascunho_preservado'])
            && !$this->ehBoletimComposto($estado)
            && !$this->deveRemontarComoQuadro($estado, $estado['rascunho_preservado'])
        ) {
            $rascunho = $estado['rascunho_preservado'];
            $rascunho['nome'] = trim((string) ($estado['nome'] ?? $rascunho['nome'] ?? ''));
            $rascunho['ano_letivo'] = (int) ($estado['ano_letivo'] ?? $rascunho['ano_letivo'] ?? date('Y'));
            $rascunho['bimestre'] = (int) ($estado['bimestre'] ?? $rascunho['bimestre'] ?? 0) ?: null;
            $rascunho['exibir_em'] = (string) ($estado['exibir_em'] ?? $rascunho['exibir_em'] ?? 'notas');
            $rascunho['round_mode'] = (string) ($estado['round_mode'] ?? $rascunho['round_mode'] ?? 'half');
            $rascunho['nota_minima_aprovacao'] = $estado['nota_minima_aprovacao'] ?? $rascunho['nota_minima_aprovacao'] ?? 7;
            $rascunho['series_ids'] = $estado['series_ids'] !== []
                ? $estado['series_ids']
                : ($rascunho['series_ids'] ?? []);
            $rascunho['turmas_ids'] = $estado['turmas_ids'] !== []
                ? $estado['turmas_ids']
                : ($rascunho['turmas_ids'] ?? []);
            $rascunho['materias_ids'] = $estado['materias_ids'] !== []
                ? $estado['materias_ids']
                : ($rascunho['materias_ids'] ?? []);
            if ($estado['series_ids'] === [] && is_array($rascunho['series_ids'] ?? null)) {
                $estado['series_ids'] = array_values(array_filter(array_map('intval', $rascunho['series_ids'])));
            }
            if ($estado['turmas_ids'] === [] && is_array($rascunho['turmas_ids'] ?? null)) {
                $estado['turmas_ids'] = array_values(array_filter(array_map('intval', $rascunho['turmas_ids'])));
            }
            if (!empty($estado['data_inicio'])) {
                $rascunho['default_data_inicio'] = (string) $estado['data_inicio'];
            }
            if (!empty($estado['data_fim'])) {
                $rascunho['default_data_fim'] = (string) $estado['data_fim'];
            }
            if ($this->grupoLinhaEstaVazio($estado)) {
                $this->aplicarGrupoLinhaDoFormulario(
                    $estado,
                    is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : []
                );
            }
            if (empty($estado['materia_unica_tocada'])) {
                $estado['materia_unica'] = $this->inferirMateriaUnicaDeComponentes(
                    is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : []
                );
                $this->aplicarMateriaUnicaNasPecasOpcoes($estado);
            }
            $rascunho = $this->aplicarMateriaUnicaNoRascunho(
                $this->aplicarGrupoLinhaNoRascunho($rascunho, $estado),
                $estado
            );
            $rascunho = $this->aplicarFormulasBlocos($rascunho, $estado, []);
            $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
            $errosOut = array_values(array_unique(array_merge($erros, $validado['erros'])));
            $rascunhoOk = $this->aplicarLayoutBimestreNoRascunhoNotas($validado['rascunho'], $estado);
            return [
                'ok' => $rascunhoOk['componentes'] !== [] && $errosOut === [],
                'estado' => $estado,
                'rascunho' => $rascunhoOk,
                'resumo' => $this->montarResumoHumano($rascunhoOk),
                'erros' => $errosOut,
                'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
            ];
        }

        if ($this->ehBoletimComposto($estado)) {
            $errosFontes = [];
            $rascunho = $this->aplicarGrupoLinhaNoRascunho(
                $this->rascunhoAPartirDeFontesBoletim($estado, $errosFontes),
                $estado
            );
            $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
            $errosOut = array_values(array_unique(array_merge($erros, $errosFontes, $validado['erros'])));
            return [
                'ok' => $validado['rascunho']['componentes'] !== [] && $errosOut === [],
                'estado' => $estado,
                'rascunho' => $validado['rascunho'],
                'resumo' => $this->montarResumoHumano($validado['rascunho']),
                'erros' => $errosOut,
                'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
            ];
        }

        if ($estado['origem'] === 'modelo' && $estado['modelo_key'] !== '') {
            $modelo = $this->encontrarModelo($estado['modelo_key']);
            if ($modelo !== null) {
                $estado['pecas'] = $modelo['pecas'];
                $padrao = $this->pecasOpcoesPadrao($modelo['pecas'], (int) ($estado['bimestre'] ?? 0));
                $tmpModelo = ['pecas_opcoes' => $padrao];
                $this->aplicarPapeisDoModelo($tmpModelo, $modelo);
                $estado['pecas_opcoes'] = $this->mesclarPecasOpcoes(
                    $tmpModelo['pecas_opcoes'],
                    is_array($estado['pecas_opcoes'] ?? null) ? $estado['pecas_opcoes'] : []
                );
                if ($estado['formula_preset'] === '' || $estado['formula_preset'] === 'media_simples') {
                    $estado['formula_preset'] = $modelo['formula_preset'];
                }
            }
        }

        if (in_array('jornada', $estado['pecas'] ?? [], true)
            && $this->resolverIdsJornadaPorBimestre($estado) === []) {
            $erros[] = 'Nenhuma jornada com esse bimestre cadastrado. Cadastre o bimestre na jornada ou ajuste o bimestre da regra.';
        }
        if (($estado['jornada_modo'] ?? '') === 'selecionadas' && in_array('jornada', $estado['pecas'] ?? [], true)
            && array_values(array_filter(array_map('intval', (array) ($estado['jornada_ids'] ?? [])), static fn ($id) => $id > 0)) === []) {
            $erros[] = 'Marque ao menos uma jornada ou use as jornadas vinculadas ao bimestre da regra.';
        }

        if ($estado['pecas'] === []) {
            $erros[] = 'Escolha ao menos uma peça da média (ex.: Bimestral, Jornada, ENAC).';
        } elseif (!$this->temFormulaMontada($estado) && !$this->temPecaComPapelMedia($estado)) {
            $erros[] = 'Marque ao menos uma peça para entrar na média, ou clique numa coluna amarela em Exibir e monte o cálculo.';
        }

        if ($this->querLayoutQuadro($estado)) {
            $rascunhoQuadro = $this->montarQuadroDoEstado($estado);
            $rascunhoQuadro = $this->aplicarGrupoLinhaNoRascunho($rascunhoQuadro, $estado);
            if (empty($estado['materia_unica_tocada'])) {
                $estado['materia_unica'] = $this->inferirMateriaUnicaDeComponentes(
                    is_array($rascunhoQuadro['componentes'] ?? null) ? $rascunhoQuadro['componentes'] : []
                );
                $this->aplicarMateriaUnicaNasPecasOpcoes($estado);
            }
            $rascunhoQuadro = $this->aplicarMateriaUnicaNoRascunho($rascunhoQuadro, $estado);
            $validadoQuadro = $this->ferramentas->validarEEnriquecerRascunho($rascunhoQuadro);
            $errosQuadro = array_values(array_unique(array_merge($erros, $validadoQuadro['erros'])));
            return [
                'ok' => $validadoQuadro['rascunho']['componentes'] !== [] && $errosQuadro === [],
                'estado' => $estado,
                'rascunho' => $validadoQuadro['rascunho'],
                'resumo' => $this->montarResumoHumano($validadoQuadro['rascunho']),
                'erros' => $errosQuadro,
                'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
            ];
        }

        $rascunho = $this->aplicarMateriaUnicaNoRascunho(
            $this->aplicarGrupoLinhaNoRascunho($this->rascunhoAPartirDePecas($estado), $estado),
            $estado
        );
        $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
        $erros = array_merge($erros, $validado['erros']);
        $rascunhoOut = $this->aplicarLayoutBimestreNoRascunhoNotas($validado['rascunho'], $estado);

        return [
            'ok' => $rascunhoOut['componentes'] !== [] && $erros === [],
            'estado' => $estado,
            'rascunho' => $rascunhoOut,
            'resumo' => $this->montarResumoHumano($rascunhoOut),
            'erros' => array_values(array_unique($erros)),
            'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
        ];
    }

    /**
     * Evento de Notas: a média calculada e as faltas entram no bimestre da Vida Escolar (B1–B4).
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarLayoutBimestreNoRascunhoNotas(array $rascunho, array $estado): array
    {
        if ($this->ehBoletimComposto($estado)) {
            return $rascunho;
        }
        $exibir = strtolower(trim((string) ($rascunho['exibir_em'] ?? $estado['exibir_em'] ?? '')));
        if ($exibir !== 'notas') {
            return $rascunho;
        }
        $bim = (int) ($rascunho['bimestre'] ?? $estado['bimestre'] ?? 0);
        if ($bim < 1 || $bim > 4) {
            return $rascunho;
        }
        $grupo = 'b' . $bim;
        $formulaFinal = strtolower(trim((string) ($rascunho['formula_final'] ?? '')));
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $src = (string) ($c['source_type'] ?? '');
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $ehMediaFinal = ($src === 'calculado' && in_array($cod, ['media_bim', 'media_final', 'media', $grupo . '_media'], true))
                || ($formulaFinal !== '' && $cod === $formulaFinal);
            $ehFaltas = $src === 'faltas_evento' || str_contains($cod, 'falt');
            if (!$ehMediaFinal && !$ehFaltas) {
                continue;
            }
            if (trim((string) ($cfg['layout_group'] ?? '')) === '') {
                $cfg['layout_group'] = $grupo;
            }
            if (trim((string) ($cfg['layout_type'] ?? '')) === '') {
                $cfg['layout_type'] = $ehFaltas ? 'faltas' : 'media';
            }
            $c['config'] = $cfg;
            $comps[$i] = $c;
        }
        $rascunho['componentes'] = $comps;

        return $rascunho;
    }

    /**
     * @param array<string,mixed> $rascunho
     */
    public function montarResumoHumano(array $rascunho): string
    {
        $nome = trim((string) ($rascunho['nome'] ?? 'Evento'));
        $ano = $rascunho['ano_letivo'] ?? '—';
        $bim = $rascunho['bimestre'] ?? null;
        $bimTxt = ($bim !== null && (int) $bim > 0) ? ((int) $bim) . 'º bimestre' : 'sem bimestre';
        $notaMin = $rascunho['nota_minima_aprovacao'] ?? null;
        $exibir = (string) ($rascunho['exibir_em'] ?? 'boletim');

        $linhas = [];
        $linhas[] = $nome;
        $linhas[] = sprintf('Período: %s · %s · Exibir em: %s', $ano, $bimTxt, $exibir);
        $di = trim((string) ($rascunho['default_data_inicio'] ?? ''));
        $df = trim((string) ($rascunho['default_data_fim'] ?? ''));
        if ($di !== '' || $df !== '') {
            $linhas[] = 'Datas de referência: ' . ($di !== '' ? $di : '—') . ' a ' . ($df !== '' ? $df : '—');
        }
        if ($notaMin !== null && $notaMin !== '') {
            $linhas[] = 'Nota mínima para aprovação: ' . $notaMin;
        }

        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        if ($comps !== []) {
            $linhas[] = '';
            $linhas[] = 'Colunas da média:';
            foreach ($comps as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $titulo = trim((string) ($c['nome'] ?? $c['codigo'] ?? 'Bloco'));
                $src = (string) ($c['source_type'] ?? '');
                $calc = (string) ($c['calc_type'] ?? 'media');
                $calcTxt = match ($calc) {
                    'soma' => 'somatória',
                    'maior' => 'maior nota',
                    'ultima' => 'nota do evento',
                    default => 'média',
                };
                $extras = [];
                $papelTxt = $this->rotuloPapel((string) (($c['config']['papel_wizard'] ?? '') ?: ''));
                if ($papelTxt !== '' && $src !== 'calculado') {
                    $extras[] = $papelTxt;
                }
                if ($src === 'provas_sistema' && !empty($c['usar_percentual'])) {
                    $extras[] = 'por acertos/questões';
                }
                $extraTxt = $extras !== [] ? ' · ' . implode(' · ', $extras) : '';
                $como = match ($src) {
                    'provas_sistema' => 'provas (' . $calcTxt . ')'
                        . (!empty($c['tipo_avaliacao_nome']) ? ' · tipo “' . $c['tipo_avaliacao_nome'] . '”' : '')
                        . (!empty($c['filtro_titulo']) ? ' · filtro “' . $c['filtro_titulo'] . '”' : '')
                        . (!empty($c['blocos_ids']) ? ' · ' . count((array) $c['blocos_ids']) . ' bloco(s)' : '')
                        . $extraTxt,
                    'jornadas' => 'jornadas concluídas (' . $calcTxt . ')'
                        . $this->resumoEscopoJornada($c)
                        . (!empty($c['config']['faixas_percentuais']) ? ' · tabela por % concluídas' : (!empty($c['usar_percentual']) ? ' · nota linear (% × 10)' : ''))
                        . $extraTxt,
                    'calculado' => 'calculado: ' . trim((string) ($c['config']['expressao'] ?? '')),
                    'evento_boletim' => 'média final do evento “'
                        . trim((string) ($c['config']['regra_codigo'] ?? '')) . '”'
                        . (trim((string) ($c['config']['componente_codigo'] ?? '')) !== ''
                            ? ' · coluna ' . trim((string) $c['config']['componente_codigo'])
                            : ''),
                    'faltas_evento' => 'faltas do evento #'
                        . (int) ($c['config']['faltas_evento_id'] ?? 0),
                    'nenhuma' => 'coluna vazia (preenchida depois)',
                    default => ($src !== '' ? $src : 'origem indefinida') . ' (' . $calcTxt . ')' . $extraTxt,
                };
                $linhas[] = '• ' . $titulo . ' — ' . $como;
            }
        }

        $series = is_array($rascunho['series_ids'] ?? null) ? $rascunho['series_ids'] : [];
        $turmas = is_array($rascunho['turmas_ids'] ?? null) ? $rascunho['turmas_ids'] : [];
        $materias = is_array($rascunho['materias_ids'] ?? null) ? $rascunho['materias_ids'] : [];
        $linhas[] = '';
        if ($materias === []) {
            $linhas[] = 'Matérias: todas.';
        } else {
            $linhas[] = 'Matérias: ' . count($materias) . ' selecionada(s).';
        }
        if ($this->rascunhoTemMateriaUnica($rascunho)) {
            $linhas[] = 'Juntar matérias iguais: sim (mesma matéria, professores diferentes).';
        }
        if ($series === [] && $turmas === []) {
            $linhas[] = 'Público: todas as séries/turmas (sem filtro).';
        } else {
            $parts = [];
            if ($series !== []) {
                $parts[] = count($series) . ' série(s)';
            }
            if ($turmas !== []) {
                $parts[] = count($turmas) . ' turma(s)';
            }
            $linhas[] = 'Público: ' . implode(', ', $parts) . ' selecionada(s).';
        }

        $grupoResumo = $this->resumoGrupoLinhaDoRascunho($rascunho);
        if ($grupoResumo !== '') {
            $linhas[] = $grupoResumo;
        }

        $linhas[] = '';
        $linhas[] = 'Ainda não está salvo — revise e clique em Aplicar no formulário, depois Salvar evento.';

        return implode("\n", $linhas);
    }

    /** @return list<array{key:string,titulo:string,descricao:string,pecas:list<string>,formula_preset:string}> */
    public function modelosProntos(): array
    {
        return [
            [
                'key' => 'bimestral_jornada',
                'titulo' => 'Bimestral + Jornada',
                'descricao' => 'Média simples das duas. Ideal para começar.',
                'pecas' => ['bimestral', 'jornada'],
                'formula_preset' => 'media_simples',
            ],
            [
                'key' => 'bimestral_jornada_enac',
                'titulo' => 'Bimestral + Jornada + ENAC',
                'descricao' => 'Média parcial (bimestral + jornada), depois média final com ENAC.',
                'pecas' => ['bimestral', 'jornada', 'enac'],
                'formula_preset' => 'parcial_depois_final',
                'papeis' => ['enac' => 'depois'],
            ],
            [
                'key' => 'semanal_bimestral_jornada',
                'titulo' => 'Semanal + Bimestral + Jornada',
                'descricao' => 'Média dos três blocos.',
                'pecas' => ['semanal', 'bimestral', 'jornada'],
                'formula_preset' => 'media_simples',
            ],
            [
                'key' => 'media3_enac_melhora',
                'titulo' => 'Média 3 + ENAC (só melhora)',
                'descricao' => 'ENAC só entra se melhorar a média.',
                'pecas' => ['semanal', 'bimestral', 'jornada', 'enac'],
                'formula_preset' => 'enac_so_melhora',
                'papeis' => ['enac' => 'so_melhora'],
            ],
            [
                'key' => 'quadro_semanal',
                'titulo' => 'Quadro semanal (S1–S8)',
                'descricao' => 'N/Q por semana, média semanal, prova bimestral, ENAC/trabalho se houver e recuperação na média final. Junta a mesma matéria de professores diferentes.',
                'pecas' => ['semanal', 'bimestral', 'enac'],
                'formula_preset' => 'quadro_semanal',
            ],
        ];
    }

    /**
     * @param list<array{id:int,nome:string,chave_quadro:?string,descricao:?string}> $tipos
     * @return list<array{key:string,label:string,hint:string,tipo_avaliacao_id:int,chave_quadro:?string}>
     */
    public function pecasMeta(array $tipos = []): array
    {
        if ($tipos === []) {
            try {
                $tipos = $this->ferramentas->listarTiposAvaliacao();
            } catch (Throwable $e) {
                $tipos = [];
            }
        }
        $out = [];
        $usadas = [];
        foreach ($tipos as $t) {
            if (!is_array($t)) {
                continue;
            }
            $id = (int) ($t['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $key = $this->chavePecaDoTipo($t, $usadas);
            $usadas[$key] = true;
            $out[] = [
                'key' => $key,
                'label' => trim((string) ($t['nome'] ?? ('Tipo #' . $id))),
                'hint' => $this->hintPecaDoTipo($key, $t),
                'tipo_avaliacao_id' => $id,
                'chave_quadro' => isset($t['chave_quadro']) ? (string) $t['chave_quadro'] : null,
            ];
        }
        $out[] = [
            'key' => 'jornada',
            'label' => 'Jornada do aluno',
            'hint' => 'Nota pela quantidade de jornadas concluídas (quantitativo) ou tabela por faixa. Pode entrar na média ou só melhorar.',
            'tipo_avaliacao_id' => 0,
            'chave_quadro' => null,
        ];
        return $out;
    }

    /** @return list<array{key:string,label:string,hint:string}> */
    public static function papeisMeta(): array
    {
        return [
            ['key' => 'media', 'label' => 'Entra na média', 'hint' => 'Compõe a 1ª média (média parcial) com as outras neste papel.'],
            ['key' => 'depois', 'label' => 'Entra depois (média final)', 'hint' => 'Entra numa segunda média, depois da média parcial. Ex.: ENAC.'],
            ['key' => 'so_melhora', 'label' => 'Só melhora', 'hint' => 'Se for menor que a média, a média permanece.'],
            ['key' => 'substitui', 'label' => 'Substitui se for maior', 'hint' => 'Usa esta nota quando ela for maior que a média (recuperação).'],
            ['key' => 'exibe', 'label' => 'Só mostra', 'hint' => 'Aparece no boletim, mas não entra no cálculo da média.'],
        ];
    }

    /**
     * @param array{id?:int,chave_quadro?:?string} $tipo
     * @param array<string,bool> $usadas
     */
    private function chavePecaDoTipo(array $tipo, array $usadas = []): string
    {
        $canon = $this->chaveCanonicaQuadro($tipo['chave_quadro'] ?? null);
        if ($canon === '') {
            $canon = $this->chaveCanonicaPorTexto((string) ($tipo['nome'] ?? '') . ' ' . (string) ($tipo['descricao'] ?? ''));
        }
        if ($canon !== '' && empty($usadas[$canon])) {
            return $canon;
        }
        return 'tipo_' . (int) ($tipo['id'] ?? 0);
    }

    private function chaveCanonicaQuadro($chave): string
    {
        $ch = strtolower(trim((string) $chave));
        return match ($ch) {
            'semanal' => 'semanal',
            'prova_bim', 'bimestral' => 'bimestral',
            'enac' => 'enac',
            'trabalho', 'trab' => 'trabalho',
            'participacao', 'participa', 'part' => 'participacao',
            'recuperacao', 'recupera', 'rec' => 'recuperacao',
            default => '',
        };
    }

    private function chaveCanonicaPorTexto(string $texto): string
    {
        $txt = mb_strtolower(trim($texto));
        if ($txt === '') {
            return '';
        }
        if (str_contains($txt, 'enac')) {
            return 'enac';
        }
        if (str_contains($txt, 'semanal')) {
            return 'semanal';
        }
        if (str_contains($txt, 'bimestral') || str_contains($txt, 'bimestr')) {
            return 'bimestral';
        }
        if (str_contains($txt, 'trabalho') || str_contains($txt, 'atividade')) {
            return 'trabalho';
        }
        if (str_contains($txt, 'participa')) {
            return 'participacao';
        }
        if (str_contains($txt, 'recupera')) {
            return 'recuperacao';
        }
        return '';
    }

    /** @param array{nome?:string,descricao?:?string} $tipo */
    private function hintPecaDoTipo(string $key, array $tipo): string
    {
        return match ($key) {
            'semanal' => 'Monta o quadro S1–S8 com N/Q (Bloco A ímpares, Bloco B pares).',
            'bimestral' => 'Notas lançadas da prova bimestral (nota do evento).',
            'enac' => 'Avaliação ENAC. Escolha se entra na média, só melhora ou só aparece.',
            'trabalho' => 'Trabalho / atividade. Qualquer papel: média, só melhora, substitui ou só mostra.',
            'participacao' => 'Participação. Mesmas opções de papel das outras peças.',
            'recuperacao' => 'Recuperação. Padrão: substitui a média se for maior.',
            default => trim((string) ($tipo['descricao'] ?? 'Tipo de avaliação da escola. Escolha como a nota entra no boletim.')),
        };
    }

    private function papelPadrao(string $peca): string
    {
        return $peca === 'recuperacao' ? 'substitui' : 'media';
    }

    /** Peças de evento único (prova lançada) começam em "nota do evento", não em média. */
    private function calcTypePadrao(string $peca): string
    {
        return match ($peca) {
            'bimestral', 'enac', 'trabalho', 'participacao', 'recuperacao' => 'ultima',
            default => 'media',
        };
    }

    private function usarPercentualPadrao(string $peca): int
    {
        return $peca === 'semanal' ? 1 : 0;
    }

    private function pecaPermiteAcertosQuestoes(string $peca): bool
    {
        return in_array($peca, ['semanal', 'bimestral', 'enac'], true)
            || str_starts_with($peca, 'tipo_');
    }

    private function normalizarPapel($raw, string $peca = ''): string
    {
        $p = strtolower(trim((string) $raw));
        if (in_array($p, self::PAPEIS, true)) {
            return $p;
        }
        return $this->papelPadrao($peca);
    }

    private function rotuloPapel(string $papel): string
    {
        return match (strtolower(trim($papel))) {
            'media' => 'entra na média',
            'depois' => 'entra depois (média final)',
            'so_melhora' => 'só melhora',
            'substitui' => 'substitui se for maior',
            'exibe' => 'só mostra',
            default => '',
        };
    }

    /**
     * Códigos de coluna que a fórmula pode citar além das peças (média, ENAC…).
     *
     * @return list<string>
     */
    private function codigosLivresFormula(): array
    {
        return [
            'media_sem', 'media_bim', 'media_final', 'prova_bim',
        ];
    }

    /** @param array<string,mixed> $estado */
    private function temFormulaMontada(array $estado): bool
    {
        if (($estado['formula_tokens'] ?? []) !== []) {
            return true;
        }
        foreach ((array) ($estado['formulas_blocos'] ?? []) as $toks) {
            if (is_array($toks) && $toks !== []) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $estado */
    private function temPecaComPapelMedia(array $estado): bool
    {
        foreach ((array) ($estado['pecas'] ?? []) as $p) {
            $p = strtolower(trim((string) $p));
            if ($p === '') {
                continue;
            }
            $opts = is_array($estado['pecas_opcoes'][$p] ?? null) ? $estado['pecas_opcoes'][$p] : [];
            if ($this->normalizarPapel($opts['papel'] ?? '', $p) === 'media') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $estado
     * @param array{papeis?:array<string,string>,formula_preset?:string} $modelo
     */
    private function aplicarPapeisDoModelo(array &$estado, array $modelo): void
    {
        $papeis = is_array($modelo['papeis'] ?? null) ? $modelo['papeis'] : [];
        if ($papeis === [] && ($modelo['formula_preset'] ?? '') === 'enac_so_melhora') {
            $papeis = ['enac' => 'so_melhora'];
        } elseif ($papeis === [] && ($modelo['formula_preset'] ?? '') === 'parcial_depois_final') {
            $papeis = ['enac' => 'depois'];
        }
        foreach ($papeis as $k => $papel) {
            $k = strtolower(trim((string) $k));
            if ($k === '' || !isset($estado['pecas_opcoes'][$k]) || !is_array($estado['pecas_opcoes'][$k])) {
                continue;
            }
            $estado['pecas_opcoes'][$k]['papel'] = $this->normalizarPapel($papel, $k);
        }
    }

    /** @return list<array{key:string,titulo:string,descricao:string}> */
    public function formulasPresetMeta(): array
    {
        return [
            [
                'key' => 'media_simples',
                'titulo' => 'Uma média só',
                'descricao' => 'Todas as peças da 1ª etapa pesam igual. Ex.: (bimestral + semanal + ENAC) / 3.',
            ],
            [
                'key' => 'parcial_depois_final',
                'titulo' => 'Média parcial, depois média final',
                'descricao' => 'Primeiro a média das peças da 1ª etapa. Depois junta com as que entram depois. Ex.: ((bimestral + semanal) / 2 + ENAC) / 2.',
            ],
            [
                'key' => 'enac_so_melhora',
                'titulo' => 'A segunda etapa só melhora',
                'descricao' => 'A média parcial vale; ENAC (ou outra peça de depois) só entra se melhorar.',
            ],
        ];
    }

    /**
     * @param list<string> $pecas
     * @return list<array{key:string,titulo:string,descricao:string}>
     */
    public function formulasParaPecas(array $pecas): array
    {
        return $this->formulasPresetMeta();
    }

    /** @param array<string,mixed> $estado */
    private function normalizarEstado(array $estado): array
    {
        $base = $this->estadoPadrao();
        $merged = array_merge($base, $estado);
        $merged['passo'] = in_array((string) ($merged['passo'] ?? ''), self::PASSOS, true)
            ? (string) $merged['passo']
            : 'inicio';
        $merged['origem'] = in_array((string) ($merged['origem'] ?? ''), ['zero', 'modelo', 'clonar', 'formulario', 'chat'], true)
            ? (string) $merged['origem']
            : 'zero';
        if (!empty($merged['rascunho_preservado']) && is_array($merged['rascunho_preservado'])) {
            // mantém
        } else {
            $merged['rascunho_preservado'] = null;
        }
        $merged['modelo_key'] = trim((string) ($merged['modelo_key'] ?? ''));
        $merged['clonar_regra_id'] = !empty($merged['clonar_regra_id']) ? (int) $merged['clonar_regra_id'] : null;
        $merged['nome'] = trim((string) ($merged['nome'] ?? ''));
        $merged['codigo'] = trim((string) ($merged['codigo'] ?? ''));
        $merged['ano_letivo'] = (int) ($merged['ano_letivo'] ?? date('Y'));
        $merged['bimestre'] = (int) ($merged['bimestre'] ?? 0);
        $merged['exibir_em'] = in_array((string) ($merged['exibir_em'] ?? ''), ['notas', 'boletim'], true)
            ? (string) $merged['exibir_em']
            : 'notas';
        $merged['nota_minima_aprovacao'] = isset($merged['nota_minima_aprovacao'])
            ? (float) $merged['nota_minima_aprovacao']
            : 7.0;
        $merged['round_mode'] = in_array((string) ($merged['round_mode'] ?? ''), ['none', 'half'], true)
            ? (string) $merged['round_mode']
            : 'half';
        $pecas = [];
        foreach ((array) ($merged['pecas'] ?? []) as $p) {
            $p = strtolower(trim((string) $p));
            if ($p !== '' && (bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $p)) {
                $pecas[] = $p;
            }
        }
        $merged['pecas'] = array_values(array_unique($pecas));
        $merged['pecas_opcoes'] = $this->mesclarPecasOpcoes(
            $this->pecasOpcoesPadrao($merged['pecas'], (int) ($merged['bimestre'] ?? 0)),
            is_array($merged['pecas_opcoes'] ?? null) ? $merged['pecas_opcoes'] : []
        );
        $merged['formula_preset'] = trim((string) ($merged['formula_preset'] ?? 'media_simples')) ?: 'media_simples';
        $merged['formula_tokens'] = $this->normalizarFormulaTokens($merged['formula_tokens'] ?? []);
        $merged['formulas_blocos'] = $this->normalizarFormulasBlocos($merged['formulas_blocos'] ?? []);
        $merged['formulas_materias_blocos'] = $this->normalizarFormulasMateriasBlocos($merged['formulas_materias_blocos'] ?? []);
        $merged['nomes_blocos'] = $this->normalizarNomesBlocos($merged['nomes_blocos'] ?? []);
        $merged['blocos_calc'] = $this->normalizarColunasOrdem($merged['blocos_calc'] ?? []);
        $merged['bloco_calc'] = strtolower(trim((string) ($merged['bloco_calc'] ?? '')));
        if ($merged['bloco_calc'] !== '' && !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $merged['bloco_calc'])) {
            $merged['bloco_calc'] = '';
        }
        $merged['materia_calc'] = max(0, (int) ($merged['materia_calc'] ?? 0));
        if (
            $merged['formula_tokens'] !== []
            && empty($merged['formulas_blocos']['media_final'])
            && $merged['materia_calc'] <= 0
            && ($merged['bloco_calc'] === '' || $merged['bloco_calc'] === 'media_final')
        ) {
            $merged['formulas_blocos']['media_final'] = $merged['formula_tokens'];
        }
        if ($merged['bloco_calc'] !== '') {
            $midCalc = $merged['materia_calc'];
            $porMatCalc = $merged['formulas_materias_blocos'][$merged['bloco_calc']] ?? null;
            if ($porMatCalc instanceof \stdClass) {
                $porMatCalc = (array) $porMatCalc;
            }
            if ($midCalc > 0 && is_array($porMatCalc) && isset($porMatCalc[$midCalc])) {
                $merged['formula_tokens'] = $porMatCalc[$midCalc];
            } elseif (isset($merged['formulas_blocos'][$merged['bloco_calc']])) {
                $merged['formula_tokens'] = $merged['formulas_blocos'][$merged['bloco_calc']];
            }
        }
        $codigosLivres = array_flip($this->codigosLivresFormula());
        foreach (array_merge(
            $merged['blocos_calc'],
            array_keys($merged['formulas_blocos']),
            array_keys($merged['formulas_materias_blocos']),
            array_keys($merged['nomes_blocos'])
        ) as $extra) {
            $extra = strtolower(trim((string) $extra));
            if ($extra !== '') {
                $codigosLivres[$extra] = true;
            }
        }
        $pecasOk = array_flip($merged['pecas']);
        $filtrarTok = static function ($t) use ($pecasOk, $codigosLivres): bool {
            if (!is_array($t)) {
                return false;
            }
            if (($t['type'] ?? '') !== 'peca') {
                return true;
            }
            $v = (string) ($t['value'] ?? '');
            return isset($pecasOk[$v]) || isset($codigosLivres[$v]);
        };
        $merged['formula_tokens'] = array_values(array_filter($merged['formula_tokens'], $filtrarTok));
        foreach ($merged['formulas_blocos'] as $codFb => $toksFb) {
            $merged['formulas_blocos'][$codFb] = array_values(array_filter($toksFb, $filtrarTok));
        }
        foreach ($merged['formulas_materias_blocos'] as $codFb => $porMat) {
            if ($porMat instanceof \stdClass) {
                $porMat = (array) $porMat;
                $merged['formulas_materias_blocos'][$codFb] = $porMat;
            }
            if (!is_array($porMat)) {
                continue;
            }
            foreach ($porMat as $midFb => $toksFb) {
                $merged['formulas_materias_blocos'][$codFb][$midFb] = array_values(array_filter(
                    is_array($toksFb) ? $toksFb : [],
                    $filtrarTok
                ));
            }
            if ($merged['formulas_materias_blocos'][$codFb] === []) {
                $merged['formulas_materias_blocos'][$codFb] = new \stdClass();
            }
        }
        $temPecaTok = false;
        foreach ($merged['formula_tokens'] as $t) {
            if (is_array($t) && ($t['type'] ?? '') === 'peca') {
                $temPecaTok = true;
                break;
            }
        }
        if (!$temPecaTok) {
            $merged['formula_tokens'] = [];
        }
        $merged['formula_custom'] = trim((string) ($merged['formula_custom'] ?? ''));
        if ($merged['formula_tokens'] === []) {
            $merged['formula_custom'] = '';
        }
        $merged['data_inicio'] = $this->normalizarDataCampo($merged['data_inicio'] ?? '');
        $merged['data_fim'] = $this->normalizarDataCampo($merged['data_fim'] ?? '');
        $merged['jornada_modo'] = in_array((string) ($merged['jornada_modo'] ?? ''), ['todas', 'bimestre', 'selecionadas'], true)
            ? (string) $merged['jornada_modo']
            : 'bimestre';
        if ($merged['jornada_modo'] === 'todas' && in_array('jornada', $merged['pecas'], true)) {
            $merged['jornada_modo'] = 'bimestre';
        }
        $merged['jornada_ids'] = array_values(array_unique(array_filter(
            array_map('intval', (array) ($merged['jornada_ids'] ?? [])),
            static fn ($id) => $id > 0
        )));
        $bims = [];
        foreach ((array) ($merged['jornada_bimestres'] ?? []) as $b) {
            $b = (int) $b;
            if ($b >= 1 && $b <= 4) {
                $bims[] = $b;
            }
        }
        $merged['jornada_bimestres'] = array_values(array_unique($bims));
        $merged['jornada_nota_modo'] = in_array((string) ($merged['jornada_nota_modo'] ?? ''), ['linear', 'faixas'], true)
            ? (string) $merged['jornada_nota_modo']
            : 'linear';
        $merged['jornada_faixas'] = $this->normalizarFaixasJornada($merged['jornada_faixas'] ?? null);
        $merged['series_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($merged['series_ids'] ?? [])))));
        $merged['turmas_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($merged['turmas_ids'] ?? [])))));
        $merged['materias_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($merged['materias_ids'] ?? [])), static fn ($id) => $id > 0)));
        $merged['aluno_preview_id'] = max(0, (int) ($merged['aluno_preview_id'] ?? 0));
        $merged['grupo_linha'] = $this->normalizarGrupoLinha($merged['grupo_linha'] ?? null);
        $flagExplicita = array_key_exists('materia_unica', $merged);
        $merged['materia_unica_tocada'] = !empty($merged['materia_unica_tocada']);
        if ($merged['materia_unica_tocada'] || $flagExplicita) {
            $merged['materia_unica'] = !empty($merged['materia_unica']) ? 1 : 0;
        } else {
            $flag = 0;
            foreach ($merged['pecas_opcoes'] as $opts) {
                if (!empty($opts['materia_unica'])) {
                    $flag = 1;
                    break;
                }
            }
            $merged['materia_unica'] = $flag;
        }
        $this->aplicarMateriaUnicaNasPecasOpcoes($merged);
        $merged['colunas_ordem'] = $this->normalizarColunasOrdem($merged['colunas_ordem'] ?? []);
        $merged['fontes_bimestres'] = $this->normalizarFontesBimestres($merged['fontes_bimestres'] ?? []);
        $merged['fontes_faltas'] = $this->normalizarFontesBimestres($merged['fontes_faltas'] ?? []);
        return $merged;
    }

    private function normalizarDataCampo($raw): string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return '';
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            return substr($s, 0, 10);
        }
        return '';
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function normalizarBimestresLista($raw): array
    {
        $out = [];
        foreach ((array) $raw as $b) {
            $n = (int) $b;
            if ($n >= 1 && $n <= 4 && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function normalizarIdsLista($raw): array
    {
        $out = [];
        foreach ((array) $raw as $id) {
            $n = (int) $id;
            if ($n > 0 && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $pecas
     * @return array<string,array{calc_type:string,materia_unica:int,usar_percentual:int,tipo_avaliacao_id:int,papel:string,bimestres:list<int>}>
     */
    public function pecasOpcoesPadrao(array $pecas, int $bimestreIdentidade = 0): array
    {
        $bims = ($bimestreIdentidade >= 1 && $bimestreIdentidade <= 4) ? [$bimestreIdentidade] : [];
        $out = [];
        foreach ($pecas as $p) {
            $p = strtolower(trim((string) $p));
            if ($p === '') {
                continue;
            }
            $out[$p] = [
                'calc_type' => $this->calcTypePadrao($p),
                'materia_unica' => 0,
                'usar_percentual' => $this->usarPercentualPadrao($p),
                'tipo_avaliacao_id' => 0,
                'papel' => $this->papelPadrao($p),
                'bimestres' => $bims,
                'blocos_ids' => [],
                'blocos_ids_manual' => 0,
            ];
        }
        return $out;
    }

    /**
     * @param array<string,array> $base
     * @param array<string,mixed> $override
     * @return array<string,array{calc_type:string,materia_unica:int,usar_percentual:int,tipo_avaliacao_id:int}>
     */
    private function mesclarPecasOpcoes(array $base, array $override): array
    {
        foreach ($override as $peca => $opts) {
            $peca = strtolower(trim((string) $peca));
            if ($peca === '' || !is_array($opts)) {
                continue;
            }
            if (!isset($base[$peca])) {
                $base[$peca] = [
                    'calc_type' => $this->calcTypePadrao($peca),
                    'materia_unica' => 0,
                    'usar_percentual' => $this->usarPercentualPadrao($peca),
                    'tipo_avaliacao_id' => 0,
                    'papel' => $this->papelPadrao($peca),
                    'bimestres' => [],
                    'blocos_ids' => [],
                    'blocos_ids_manual' => 0,
                ];
            }
            $calc = strtolower(trim((string) ($opts['calc_type'] ?? $base[$peca]['calc_type'])));
            if (!in_array($calc, ['media', 'soma', 'maior', 'ultima'], true)) {
                $calc = $this->calcTypePadrao($peca);
            }
            $base[$peca]['calc_type'] = $calc;
            $base[$peca]['materia_unica'] = !empty($opts['materia_unica']) ? 1 : 0;
            if (array_key_exists('usar_percentual', $opts)) {
                $base[$peca]['usar_percentual'] = !empty($opts['usar_percentual']) ? 1 : 0;
            }
            if (!$this->pecaPermiteAcertosQuestoes($peca)) {
                $base[$peca]['usar_percentual'] = 0;
            }
            if (array_key_exists('tipo_avaliacao_id', $opts)) {
                $base[$peca]['tipo_avaliacao_id'] = max(0, (int) $opts['tipo_avaliacao_id']);
            }
            if (array_key_exists('bimestres', $opts)) {
                $base[$peca]['bimestres'] = $this->normalizarBimestresLista($opts['bimestres']);
            }
            if (array_key_exists('blocos_ids', $opts)) {
                $base[$peca]['blocos_ids'] = $this->normalizarIdsLista($opts['blocos_ids']);
            }
            if (array_key_exists('blocos_ids_manual', $opts)) {
                $base[$peca]['blocos_ids_manual'] = !empty($opts['blocos_ids_manual']) ? 1 : 0;
            }
            $base[$peca]['papel'] = $this->normalizarPapel($opts['papel'] ?? ($base[$peca]['papel'] ?? ''), $peca);
        }
        return $base;
    }

    /**
     * @param list<array> $comps
     * @param list<string> $pecas
     * @return array<string,array{calc_type:string,materia_unica:int,usar_percentual:int,tipo_avaliacao_id:int,papel:string}>
     */
    private function inferirPecasOpcoesDeComponentes(array $comps, array $pecas, string $formulaFinal = ''): array
    {
        $out = $this->pecasOpcoesPadrao($pecas);
        $formulaBlob = strtolower($formulaFinal);
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $formulaBlob .= ' ' . strtolower((string) (($c['config']['expressao'] ?? '') ?: ''));
        }
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') === 'calculado') {
                continue;
            }
            $key = $this->chavePecaDeComponente($c);
            if ($key === null || !isset($out[$key])) {
                continue;
            }
            $calc = strtolower(trim((string) ($c['calc_type'] ?? 'media')));
            if (in_array($calc, ['media', 'soma', 'maior', 'ultima'], true)) {
                $out[$key]['calc_type'] = $calc;
            }
            $out[$key]['materia_unica'] = !empty($c['materia_unica']) ? 1 : 0;
            $out[$key]['usar_percentual'] = !empty($c['usar_percentual']) ? 1 : 0;
            if (!$this->pecaPermiteAcertosQuestoes($key)) {
                $out[$key]['usar_percentual'] = 0;
            }
            $tipoId = (int) (($c['config']['tipo_avaliacao_id'] ?? null) ?: ($c['tipo_avaliacao_id'] ?? 0));
            if ($tipoId > 0) {
                $out[$key]['tipo_avaliacao_id'] = $tipoId;
            }
            $bimsComp = $this->normalizarBimestresLista($c['config']['prova_bimestres'] ?? []);
            if ($bimsComp !== []) {
                $out[$key]['bimestres'] = $bimsComp;
            }
            $blocosComp = $this->normalizarIdsLista($c['blocos_ids'] ?? []);
            if ($blocosComp !== [] || !empty($c['config']['blocos_ids_manual'])) {
                $out[$key]['blocos_ids'] = $blocosComp;
                $out[$key]['blocos_ids_manual'] = 1;
            }
            $out[$key]['papel'] = $this->inferirPapelDeComponente($c, $formulaBlob);
        }
        return $out;
    }

    /** @param list<array> $comps @return list<string> */
    private function inferirPecasDeComponentes(array $comps): array
    {
        $pecas = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $key = $this->chavePecaDeComponente($c);
            if ($key !== null) {
                $pecas[] = $key;
            }
        }
        return array_values(array_unique($pecas));
    }

    /** @param array<string,mixed> $c */
    private function chavePecaDeComponente(array $c): ?string
    {
        $src = (string) ($c['source_type'] ?? '');
        if ($src === 'calculado') {
            return null;
        }
        $cod = mb_strtolower(trim((string) ($c['codigo'] ?? '')));
        $nome = mb_strtolower(trim((string) ($c['nome'] ?? '')));
        $filtro = mb_strtolower(trim((string) ($c['filtro_titulo'] ?? '')));
        $blob = $cod . ' ' . $nome . ' ' . $filtro;
        $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
        $layoutG = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
        $chaveTipo = $this->chaveCanonicaQuadro($cfg['chave_quadro'] ?? ($c['chave_quadro'] ?? ''));
        if ($src === 'jornadas' || str_contains($blob, 'jornada')) {
            return 'jornada';
        }
        if ($chaveTipo !== '') {
            return $chaveTipo;
        }
        if (str_contains($blob, 'enac')) {
            return 'enac';
        }
        if (
            str_contains($blob, 'semanal')
            || (bool) preg_match('/^s[1-8]$/', $cod)
            || in_array($layoutG, ['quadro_a', 'quadro_b'], true)
        ) {
            return 'semanal';
        }
        if (str_contains($blob, 'bimestral') || str_contains($blob, 'bimestr') || $cod === 'prova_bim') {
            return 'bimestral';
        }
        if (str_contains($blob, 'trabalho') || $cod === 'trab') {
            return 'trabalho';
        }
        if (str_contains($blob, 'participa') || $cod === 'part') {
            return 'participacao';
        }
        if (str_contains($blob, 'recupera') || $cod === 'rec') {
            return 'recuperacao';
        }
        $tipoId = (int) (($cfg['tipo_avaliacao_id'] ?? null) ?: ($c['tipo_avaliacao_id'] ?? 0));
        if ($tipoId > 0) {
            return $this->chavePecaPorTipoId($tipoId);
        }
        return null;
    }

    private function chavePecaPorTipoId(int $tipoId): string
    {
        if ($tipoId <= 0) {
            return '';
        }
        foreach ($this->pecasMeta() as $meta) {
            if ((int) ($meta['tipo_avaliacao_id'] ?? 0) === $tipoId) {
                return (string) ($meta['key'] ?? ('tipo_' . $tipoId));
            }
        }
        return 'tipo_' . $tipoId;
    }

    private function inferirPapelDeComponente(array $c, string $formula): string
    {
        $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
        $salvo = strtolower(trim((string) ($cfg['papel_wizard'] ?? '')));
        if (in_array($salvo, self::PAPEIS, true)) {
            return $salvo;
        }
        $key = $this->chavePecaDeComponente($c) ?? '';
        $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
        $f = strtolower($formula);
        $layoutType = strtolower(trim((string) ($cfg['layout_type'] ?? '')));
        if ($layoutType === 'semana_nq' || (bool) preg_match('/^s[1-8]$/', $cod)) {
            return 'media';
        }
        if ($cod !== '' && $f !== '') {
            $q = preg_quote($cod, '/');
            if (preg_match('/max\s*\([^,]*,\s*\([^)]*' . $q . '/', $f)) {
                return 'so_melhora';
            }
            if (preg_match('/max\s*\([^,]*,\s*' . $q . '\s*\)/', $f)) {
                return 'substitui';
            }
            if (preg_match('/\(\s*(media_parcial|media_base|media_bim)\s*\+\s*[^)]*' . $q . '/', $f)
                || preg_match('/\(\s*' . $q . '\s*\+\s*(media_parcial|media_base|media_bim)/', $f)) {
                return 'depois';
            }
            if (!preg_match('/\b' . $q . '\b/', $f)) {
                return 'exibe';
            }
            return 'media';
        }
        return $this->papelPadrao($key);
    }

    /** @return array<string,mixed>|null */
    private function encontrarModelo(string $key): ?array
    {
        foreach ($this->modelosProntos() as $m) {
            if ($m['key'] === $key) {
                return $m;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $regra
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function rascunhoAPartirDeRegra(array $regra, array $estado): array
    {
        $bim = (int) ($estado['bimestre'] ?? 0);
        $ano = (int) ($estado['ano_letivo'] ?? date('Y'));
        $nomeBase = trim((string) ($regra['nome'] ?? 'Evento'));
        $nome = trim((string) ($estado['nome'] ?? ''));
        if ($nome === '' || $nome === $nomeBase) {
            $nome = $bim > 0
                ? preg_replace('/\d+\s*º?\s*bimestre/iu', $bim . 'º bimestre', $nomeBase) ?? ($nomeBase . ' (cópia)')
                : ($nomeBase . ' (cópia)');
            if ($nome === $nomeBase) {
                $nome = $nomeBase . ' — ' . $bim . 'º bim';
            }
        }

        return [
            'modo' => 'criar',
            'regra_id' => null,
            'nome' => $nome,
            'codigo' => '',
            'descricao_curta' => (string) ($regra['descricao_curta'] ?? ''),
            'formula_final' => (string) ($regra['formula_final'] ?? ''),
            'exibir_em' => (string) ($estado['exibir_em'] ?? $regra['exibir_em'] ?? 'notas'),
            'ano_letivo' => $ano,
            'bimestre' => $bim > 0 ? $bim : ($regra['bimestre'] ?? null),
            'default_data_inicio' => (string) ($estado['data_inicio'] ?? ''),
            'default_data_fim' => (string) ($estado['data_fim'] ?? ''),
            'turmas_ids' => $estado['turmas_ids'] !== [] ? $estado['turmas_ids'] : ($regra['turmas_ids'] ?? []),
            'materias_ids' => $estado['materias_ids'] !== [] ? $estado['materias_ids'] : ($regra['materias_ids'] ?? []),
            'series_ids' => $estado['series_ids'] !== [] ? $estado['series_ids'] : ($regra['series_ids'] ?? []),
            'round_mode' => (string) ($estado['round_mode'] ?? $regra['round_mode'] ?? 'half'),
            'nota_minima_aprovacao' => $estado['nota_minima_aprovacao'] ?? $regra['nota_minima_aprovacao'] ?? 7.0,
            'componentes' => $this->ajustarComponentesClonados(
                is_array($regra['componentes'] ?? null) ? $regra['componentes'] : [],
                $estado
            ),
        ];
    }

    /**
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function rascunhoAPartirDePecas(array $estado): array
    {
        $pecas = $estado['pecas'];
        $componentes = [];
        $mapaCodigo = [];

        foreach ($pecas as $peca) {
            $opts = is_array($estado['pecas_opcoes'][$peca] ?? null) ? $estado['pecas_opcoes'][$peca] : [];
            $def = $this->definirComponentePeca($peca, $opts, $estado);
            if ($def === null) {
                continue;
            }
            $papel = $this->normalizarPapel($opts['papel'] ?? '', $peca);
            $def['config'] = is_array($def['config'] ?? null) ? $def['config'] : [];
            $def['config']['papel_wizard'] = $papel;
            $componentes[] = $def;
            $mapaCodigo[$peca] = ['codigo' => $def['codigo'], 'papel' => $papel, 'nome' => (string) $def['nome']];
        }

        $formulaFinal = $this->montarFormulaPorPapeis($componentes, $mapaCodigo, $pecas);
        $rascunhoTmp = [
            'componentes' => $componentes,
            'formula_final' => $formulaFinal,
        ];
        $rascunhoTmp = $this->aplicarFormulasBlocos($rascunhoTmp, $estado, $mapaCodigo);
        $componentes = is_array($rascunhoTmp['componentes'] ?? null) ? $rascunhoTmp['componentes'] : $componentes;
        $formulaFinal = trim((string) ($rascunhoTmp['formula_final'] ?? $formulaFinal));

        $bim = (int) ($estado['bimestre'] ?? 0);
        $ano = (int) ($estado['ano_letivo'] ?? date('Y'));
        $nome = trim((string) ($estado['nome'] ?? ''));
        if ($nome === '') {
            $nome = $bim > 0
                ? sprintf('Notas Bimestrais — %dº bimestre %d', $bim, $ano)
                : sprintf('Notas — %d', $ano);
        }

        return [
            'modo' => ($estado['modo'] ?? 'criar') === 'editar' && !empty($estado['regra_id']) ? 'editar' : 'criar',
            'regra_id' => !empty($estado['regra_id']) ? (int) $estado['regra_id'] : null,
            'nome' => $nome,
            'codigo' => trim((string) ($estado['codigo'] ?? '')),
            'descricao_curta' => '',
            'formula_final' => $formulaFinal,
            'exibir_em' => (string) ($estado['exibir_em'] ?? 'notas'),
            'ano_letivo' => $ano,
            'bimestre' => $bim > 0 ? $bim : null,
            'default_data_inicio' => (string) ($estado['data_inicio'] ?? ''),
            'default_data_fim' => (string) ($estado['data_fim'] ?? ''),
            'turmas_ids' => $estado['turmas_ids'],
            'materias_ids' => $estado['materias_ids'],
            'series_ids' => $estado['series_ids'],
            'round_mode' => (string) ($estado['round_mode'] ?? 'half'),
            'nota_minima_aprovacao' => (float) ($estado['nota_minima_aprovacao'] ?? 7),
            'componentes' => $componentes,
        ];
    }

    /**
     * Boletim oficial: puxa a média final dos eventos de Notas (não remonta o cálculo).
     */
    private function ehBoletimComposto(array $estado): bool
    {
        return (string) ($estado['exibir_em'] ?? '') === 'boletim'
            && ($estado['origem'] ?? '') !== 'clonar';
    }

    /**
     * @param mixed $raw
     * @return array<int,int>
     */
    private function normalizarFontesBimestres($raw): array
    {
        $out = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ([1, 2, 3, 4] as $bim) {
            $id = (int) ($raw[$bim] ?? $raw[(string) $bim] ?? 0);
            $out[$bim] = $id > 0 ? $id : 0;
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @return array<int,int>
     */
    private function inferirFontesBimestresDeComponentes(array $comps): array
    {
        $out = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $grupoBim = ['b1' => 1, 'b2' => 2, 'b3' => 3, 'b4' => 4];
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'evento_boletim') {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            $bim = $grupoBim[$g] ?? 0;
            if ($bim <= 0 && preg_match('/^b([1-4])/', strtolower(trim((string) ($c['codigo'] ?? ''))), $m)) {
                $bim = (int) $m[1];
            }
            if ($bim < 1 || $bim > 4) {
                continue;
            }
            $slug = trim((string) ($cfg['regra_codigo'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $ref = $this->ferramentas->obterRegraPorCodigo($slug);
            if ($ref === null) {
                continue;
            }
            $out[$bim] = (int) ($ref['id'] ?? 0);
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @return array<int,int>
     */
    private function inferirFontesFaltasDeComponentes(array $comps): array
    {
        $out = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $grupoBim = ['b1' => 1, 'b2' => 2, 'b3' => 3, 'b4' => 4];
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'faltas_evento') {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            $lt = strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? '')));
            if ($g === 'final' || $lt === 'resultado' || $lt === 'rec') {
                continue;
            }
            $bim = $grupoBim[$g] ?? 0;
            if ($bim <= 0 && preg_match('/^b([1-4])/', strtolower(trim((string) ($c['codigo'] ?? ''))), $m)) {
                $bim = (int) $m[1];
            }
            if ($bim < 1 || $bim > 4) {
                continue;
            }
            $out[$bim] = (int) ($cfg['faltas_evento_id'] ?? $c['faltas_evento_id'] ?? 0);
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private function componenteBoletim(string $codigo, string $nome, string $source, array $config): array
    {
        $ehFaltas = $source === 'faltas_evento'
            || strtolower((string) ($config['layout_type'] ?? '')) === 'faltas';

        return [
            'codigo' => $codigo,
            'nome' => $nome,
            'source_type' => $source,
            'calc_type' => 'media',
            'peso' => 1,
            'filtro_titulo' => '',
            'blocos_ids' => [],
            'materias_ids' => [],
            'usar_percentual' => 0,
            'escala_max' => $ehFaltas ? 999 : 10,
            'obrigatorio' => 0,
            'config' => $config,
        ];
    }

    /**
     * Coluna da média final no evento de Notas (código de componente, não expressão).
     *
     * @param array<string,mixed> $ref
     */
    private function codigoColunaMediaDaRegra(array $ref): string
    {
        $codigos = [];
        $primeiroCalculado = '';
        foreach ((array) ($ref['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $codigos[$cod] = (string) ($c['codigo'] ?? $cod);
            if ($primeiroCalculado === '' && (string) ($c['source_type'] ?? '') === 'calculado') {
                $primeiroCalculado = (string) ($c['codigo'] ?? $cod);
            }
        }
        $formula = strtolower(trim((string) ($ref['formula_final'] ?? '')));
        if ($formula !== '' && isset($codigos[$formula])) {
            return $codigos[$formula];
        }
        foreach (['media_final', 'media_bim', 'media'] as $pref) {
            if (isset($codigos[$pref])) {
                return $codigos[$pref];
            }
        }

        return $primeiroCalculado;
    }

    /**
     * @param array<string,mixed> $estado
     * @param list<string> $erros
     * @return array<string,mixed>
     */
    private function rascunhoAPartirDeFontesBoletim(array $estado, array &$erros): array
    {
        $ano = (int) ($estado['ano_letivo'] ?? date('Y'));
        $nome = trim((string) ($estado['nome'] ?? ''));
        if ($nome === '') {
            $nome = sprintf('Boletim %d', $ano);
        }
        $fontes = $this->normalizarFontesBimestres($estado['fontes_bimestres'] ?? []);
        $fontesFaltas = $this->normalizarFontesBimestres($estado['fontes_faltas'] ?? []);
        $selfId = (int) ($estado['regra_id'] ?? 0);
        $componentes = [];
        $codigosMedia = [];
        $codigosFaltas = [];
        $rotulos = [1 => '1º bim', 2 => '2º bim', 3 => '3º bim', 4 => '4º bim'];

        foreach ([1, 2, 3, 4] as $bim) {
            $idFonte = (int) ($fontes[$bim] ?? 0);
            $idFaltas = (int) ($fontesFaltas[$bim] ?? 0);
            if ($idFonte <= 0 && $idFaltas <= 0) {
                continue;
            }
            $grupo = 'b' . $bim;

            if ($idFonte > 0) {
                if ($selfId > 0 && $idFonte === $selfId) {
                    $erros[] = 'O ' . $rotulos[$bim] . ' não pode apontar para este mesmo evento.';
                } else {
                    $ref = $this->ferramentas->obterRegra($idFonte);
                    if ($ref === null) {
                        $erros[] = 'Não encontrei o evento de Notas do ' . $rotulos[$bim] . '.';
                    } elseif (strtolower(trim((string) ($ref['exibir_em'] ?? ''))) !== 'notas') {
                        $erros[] = 'O evento “' . trim((string) ($ref['nome'] ?? '')) . '” não é de Notas. No boletim só entram médias finais de eventos com Exibir em: Notas.';
                    } else {
                        $slug = trim((string) ($ref['codigo'] ?? ''));
                        if ($slug === '') {
                            $erros[] = 'O evento “' . trim((string) ($ref['nome'] ?? '')) . '” não tem código (slug). Abra o evento de Notas e preencha o código.';
                        } else {
                            $codComp = 'b' . $bim . '_media';
                            $codigosMedia[] = $codComp;
                            $componentes[] = $this->componenteBoletim($codComp, 'Média', 'evento_boletim', [
                                'regra_codigo' => $slug,
                                'componente_codigo' => $this->codigoColunaMediaDaRegra($ref),
                                'layout_group' => $grupo,
                                'layout_type' => 'media',
                            ]);
                        }
                    }
                }
            }

            $codFaltas = 'b' . $bim . '_faltas';
            if ($idFaltas > 0) {
                $evF = $this->ferramentas->obterFaltasEvento($idFaltas);
                if ($evF === null) {
                    $erros[] = 'Não encontrei o evento de faltas do ' . $rotulos[$bim] . '.';
                } else {
                    $codigosFaltas[] = $codFaltas;
                    $componentes[] = $this->componenteBoletim($codFaltas, 'Faltas', 'faltas_evento', [
                        'faltas_evento_id' => $idFaltas,
                        'layout_group' => $grupo,
                        'layout_type' => 'faltas',
                    ]);
                }
            } elseif ($idFonte > 0) {
                $componentes[] = $this->componenteBoletim($codFaltas, 'Faltas', 'nenhuma', [
                    'layout_group' => $grupo,
                    'layout_type' => 'faltas',
                ]);
            }
        }

        if ($codigosMedia === []) {
            $erros[] = 'Selecione ao menos um evento de Notas (a média final de um bimestre).';
        } else {
            $n = count($codigosMedia);
            $exp = $n === 1
                ? $codigosMedia[0]
                : '(' . implode(' + ', $codigosMedia) . ') / ' . $n;
            $componentes[] = $this->componenteBoletim('media_final', 'Média', 'calculado', [
                'expressao' => $exp,
                'formula_mode' => 'single',
                'layout_group' => 'final',
                'layout_type' => 'media',
            ]);
            $componentes[] = $this->componenteBoletim('rec_final', 'Rec.', 'nenhuma', [
                'layout_group' => 'final',
                'layout_type' => 'rec',
            ]);
            if ($codigosFaltas !== []) {
                $expF = count($codigosFaltas) === 1
                    ? $codigosFaltas[0]
                    : implode(' + ', $codigosFaltas);
                $componentes[] = $this->componenteBoletim('faltas_final', 'Faltas', 'calculado', [
                    'expressao' => $expF,
                    'formula_mode' => 'single',
                    'layout_group' => 'final',
                    'layout_type' => 'faltas',
                ]);
            } else {
                $componentes[] = $this->componenteBoletim('faltas_final', 'Faltas', 'nenhuma', [
                    'layout_group' => 'final',
                    'layout_type' => 'faltas',
                ]);
            }
            $componentes[] = $this->componenteBoletim('resultado', 'Resultado', 'nenhuma', [
                'layout_group' => 'final',
                'layout_type' => 'resultado',
            ]);
        }

        return [
            'modo' => ($estado['modo'] ?? 'criar') === 'editar' && !empty($estado['regra_id']) ? 'editar' : 'criar',
            'regra_id' => !empty($estado['regra_id']) ? (int) $estado['regra_id'] : null,
            'nome' => $nome,
            'codigo' => trim((string) ($estado['codigo'] ?? '')),
            'descricao_curta' => '',
            'formula_final' => $codigosMedia === [] ? '' : 'media_final',
            'exibir_em' => 'boletim',
            'ano_letivo' => $ano,
            'bimestre' => null,
            'usar_resultado_aprovacao' => 1,
            'default_data_inicio' => (string) ($estado['data_inicio'] ?? ''),
            'default_data_fim' => (string) ($estado['data_fim'] ?? ''),
            'turmas_ids' => $estado['turmas_ids'] ?? [],
            'materias_ids' => $estado['materias_ids'] ?? [],
            'series_ids' => $estado['series_ids'] ?? [],
            'round_mode' => (string) ($estado['round_mode'] ?? 'half'),
            'nota_minima_aprovacao' => (float) ($estado['nota_minima_aprovacao'] ?? 7),
            'componentes' => $componentes,
        ];
    }

    /**
     * @param mixed $raw
     * @return list<array{type:string,value:string,label:string}>
     */
    private function normalizarFormulaTokens($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $t) {
            if (!is_array($t)) {
                continue;
            }
            $type = strtolower(trim((string) ($t['type'] ?? '')));
            $value = trim((string) ($t['value'] ?? ''));
            if ($type === 'peca') {
                $value = strtolower($value);
                if (!(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $value)) {
                    continue;
                }
                $out[] = [
                    'type' => 'peca',
                    'value' => $value,
                    'label' => trim((string) ($t['label'] ?? $value)) ?: $value,
                ];
            } elseif ($type === 'op') {
                if (!in_array($value, ['+', '-', '*', '/', '(', ')', ','], true)) {
                    continue;
                }
                $out[] = ['type' => 'op', 'value' => $value, 'label' => $value];
            } elseif ($type === 'num') {
                if (!(bool) preg_match('/^\d+(?:\.\d+)?$/', $value)) {
                    continue;
                }
                $out[] = ['type' => 'num', 'value' => $value, 'label' => $value];
            } elseif ($type === 'fn') {
                $value = strtolower($value);
                if (!in_array($value, ['max', 'min'], true)) {
                    continue;
                }
                $out[] = [
                    'type' => 'fn',
                    'value' => $value,
                    'label' => $value === 'max' ? 'maior (' : 'menor (',
                ];
            }
            if (count($out) >= 80) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<string,list<array{type:string,value:string,label:string}>>
     */
    private function normalizarFormulasBlocos($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $codigo => $tokens) {
            $codigo = strtolower(trim((string) $codigo));
            if ($codigo === '' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
                continue;
            }
            if ($codigo === 'media_sem') {
                continue;
            }
            $norm = $this->normalizarFormulaTokens($tokens);
            if ($norm === []) {
                continue;
            }
            $out[$codigo] = $norm;
            if (count($out) >= 20) {
                break;
            }
        }
        return $out;
    }

    /**
     * Exceções de fórmula por matéria em cada bloco calculado.
     *
     * @param mixed $raw
     * @return array<string,array<int,list<array{type:string,value:string,label:string}>>|\stdClass>
     */
    private function normalizarFormulasMateriasBlocos($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $codigo => $porMateria) {
            $codigo = strtolower(trim((string) $codigo));
            if ($codigo === '' || $codigo === 'media_sem' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
                continue;
            }
            if ($porMateria instanceof \stdClass) {
                $porMateria = (array) $porMateria;
            }
            if (!is_array($porMateria)) {
                continue;
            }
            $mapa = [];
            foreach ($porMateria as $midRaw => $tokens) {
                $mid = (int) $midRaw;
                if ($mid <= 0) {
                    continue;
                }
                $norm = $this->normalizarFormulaTokens($tokens);
                if ($norm === []) {
                    continue;
                }
                $mapa[$mid] = $norm;
                if (count($mapa) >= 40) {
                    break;
                }
            }
            if ($mapa === []) {
                $out[$codigo] = new \stdClass();
            } else {
                $out[$codigo] = $mapa;
            }
            if (count($out) >= 20) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<string,string>
     */
    private function normalizarNomesBlocos($raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $codigo => $nome) {
            $codigo = strtolower(trim((string) $codigo));
            if ($codigo === '' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
                continue;
            }
            if ($codigo === 'media_sem' || (bool) preg_match('/^s[1-8]$/', $codigo)) {
                continue;
            }
            $nome = trim(mb_substr((string) $nome, 0, 60));
            if ($nome === '') {
                continue;
            }
            $out[$codigo] = $nome;
            if (count($out) >= 20) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param list<array{type:string,value:string,label?:string}> $tokens
     * @param array<string,array{codigo:string,papel?:string,nome?:string}> $mapaCodigo
     * @param list<string> $pecasPermitidas
     */
    private function compilarTokensParaExpressao(array $tokens, array $mapaCodigo, array $pecasPermitidas = []): string
    {
        $parts = [];
        $temPeca = false;
        $parens = 0;
        foreach ($tokens as $t) {
            if (!is_array($t)) {
                continue;
            }
            $type = (string) ($t['type'] ?? '');
            $v = (string) ($t['value'] ?? '');
            if ($type === 'peca') {
                if (!isset($mapaCodigo[$v]['codigo'])) {
                    return '';
                }
                $parts[] = (string) $mapaCodigo[$v]['codigo'];
                $temPeca = true;
            } elseif ($type === 'fn') {
                $v = strtolower($v);
                if (!in_array($v, ['max', 'min'], true)) {
                    return '';
                }
                $parts[] = $v . '(';
                $parens++;
            } elseif ($type === 'op') {
                if (!in_array($v, ['+', '-', '*', '/', '(', ')', ','], true)) {
                    return '';
                }
                if ($v === '(') {
                    $parens++;
                } elseif ($v === ')') {
                    $parens--;
                    if ($parens < 0) {
                        return '';
                    }
                }
                $parts[] = $v;
            } elseif ($type === 'num') {
                if (!(bool) preg_match('/^\d+(?:\.\d+)?$/', $v)) {
                    return '';
                }
                $parts[] = $v;
            }
        }
        if (!$temPeca || $parts === [] || $parens !== 0) {
            return '';
        }
        $primeiro = (string) $parts[0];
        $ultimo = (string) $parts[count($parts) - 1];
        if (in_array($primeiro, ['+', '*', '/', ',', ')'], true)) {
            return '';
        }
        if (in_array($ultimo, ['+', '-', '*', '/', ',', '('], true) || str_ends_with($ultimo, '(')) {
            return '';
        }
        $exp = trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '');
        $exp = str_replace(['( ', ' )', ' ,'], ['(', ')', ','], $exp);
        $exp = preg_replace('/\b(max|min)\(\s+/', '$1(', $exp) ?? $exp;
        $exp = trim($exp);
        if ($exp === '' || !(bool) preg_match('/^[a-z0-9_+\-*\/(),.\s]+$/i', $exp)) {
            return '';
        }
        if ((str_contains($exp, 'max(') || str_contains($exp, 'min(')) && !str_contains($exp, ',')) {
            return '';
        }
        return $exp;
    }

    /**
     * Gera a fórmula a partir dos papéis: média das peças "media", depois
     * cada "so_melhora" (max com média da peça) e cada "substitui" (max com a peça).
     *
     * @param list<array<string,mixed>> $componentes
     * @param array<string,array{codigo:string,papel:string,nome:string}> $mapaCodigo
     * @param list<string> $pecas
     */
    private function montarFormulaPorPapeis(
        array &$componentes,
        array $mapaCodigo,
        array $pecas,
        string $codigoBase = 'media_base',
        string $codigoFinal = 'media_final',
        bool $forcarBlocoBase = false
    ): string {
        $media = [];
        $depois = [];
        $soMelhora = [];
        $substitui = [];
        foreach ($pecas as $p) {
            if (!isset($mapaCodigo[$p])) {
                continue;
            }
            $cod = (string) $mapaCodigo[$p]['codigo'];
            $papel = (string) $mapaCodigo[$p]['papel'];
            if ($papel === 'media') {
                $media[] = $cod;
            } elseif ($papel === 'depois') {
                $depois[] = $cod;
            } elseif ($papel === 'so_melhora') {
                $soMelhora[] = $cod;
            } elseif ($papel === 'substitui') {
                $substitui[] = $cod;
            }
        }

        if ($media === []) {
            return '';
        }

        $expBase = $this->expressaoMedia($media);
        $temDepois = $depois !== [];
        $temExtras = $temDepois || $soMelhora !== [] || $substitui !== [];
        if ($temDepois && !$forcarBlocoBase) {
            $codigoBase = 'media_parcial';
        }
        if (!$temExtras && !$forcarBlocoBase) {
            if (count($media) === 1) {
                return $media[0];
            }
            $componentes[] = $this->blocoCalculado($codigoFinal, 'Média final', $expBase);
            return $codigoFinal;
        }

        $prev = $codigoBase;
        $nomeBase = $forcarBlocoBase ? 'Média Bim' : ($temDepois ? 'Média parcial' : 'Média base');
        $componentes[] = $this->blocoCalculado($prev, $nomeBase, $expBase);
        if (!$temExtras) {
            return $prev;
        }

        if ($temDepois) {
            $expDepois = '(' . $prev . ' + ' . implode(' + ', $depois) . ') / ' . (1 + count($depois));
            $restantesApos = count($soMelhora) + count($substitui);
            $next = $restantesApos === 0 ? $codigoFinal : 'media_etapa';
            $nome = $next === $codigoFinal ? 'Média final' : 'Média com etapa 2';
            $componentes[] = $this->blocoCalculado($next, $nome, $expDepois);
            $prev = $next;
            if ($restantesApos === 0) {
                return $prev;
            }
        }

        $restantes = count($soMelhora) + count($substitui);
        $i = 0;
        foreach ($soMelhora as $cod) {
            $i++;
            $restantes--;
            $next = $restantes === 0 ? $codigoFinal : ('media_m' . $i);
            $nome = $next === $codigoFinal ? 'Média final' : ('Média com ' . $cod);
            $componentes[] = $this->blocoCalculado($next, $nome, 'max(' . $prev . ', (' . $prev . ' + ' . $cod . ') / 2)');
            $prev = $next;
        }
        $j = 0;
        foreach ($substitui as $cod) {
            $j++;
            $restantes--;
            $next = $restantes === 0 ? $codigoFinal : ('media_s' . $j);
            $nome = $next === $codigoFinal ? 'Média final' : ('Média com ' . $cod);
            $componentes[] = $this->blocoCalculado($next, $nome, 'max(' . $prev . ', ' . $cod . ')');
            $prev = $next;
        }

        return $prev;
    }

    /**
     * @param array{tipo_avaliacao_id?:int} $opts
     * @return array{codigo:string,nome:string,source_type:string,filtro_titulo:string,tipo_sugerido:string,tipo_avaliacao_id:int}|null
     */
    private function resolverDefinicaoPeca(string $peca, array $opts = []): ?array
    {
        if ($peca === 'jornada') {
            return [
                'codigo' => 'jornada',
                'nome' => 'Jornada do aluno',
                'source_type' => 'jornadas',
                'filtro_titulo' => '',
                'tipo_sugerido' => '',
                'tipo_avaliacao_id' => 0,
            ];
        }

        $canonicos = [
            'bimestral' => ['codigo' => 'bimestral', 'nome' => 'Prova bimestral', 'filtro_titulo' => 'bimestral', 'tipo_sugerido' => 'Bimestral'],
            'enac' => ['codigo' => 'enac', 'nome' => 'ENAC', 'filtro_titulo' => 'enac', 'tipo_sugerido' => 'ENAC'],
            'semanal' => ['codigo' => 'semanal', 'nome' => 'Prova semanal', 'filtro_titulo' => 'semanal', 'tipo_sugerido' => 'Semanal'],
            'trabalho' => ['codigo' => 'trab', 'nome' => 'Trabalho', 'filtro_titulo' => 'trabalho', 'tipo_sugerido' => 'Trabalho'],
            'participacao' => ['codigo' => 'part', 'nome' => 'Participação', 'filtro_titulo' => 'participacao', 'tipo_sugerido' => 'Participação'],
            'recuperacao' => ['codigo' => 'rec', 'nome' => 'Recuperação', 'filtro_titulo' => 'recuperacao', 'tipo_sugerido' => 'Recuperação'],
        ];

        $tipoId = max(0, (int) ($opts['tipo_avaliacao_id'] ?? 0));
        $meta = null;
        foreach ($this->pecasMeta() as $m) {
            if (!is_array($m)) {
                continue;
            }
            if ((string) ($m['key'] ?? '') === $peca) {
                $meta = $m;
                break;
            }
        }

        if (isset($canonicos[$peca])) {
            $def = $canonicos[$peca];
            $def['source_type'] = 'provas_sistema';
            $def['tipo_avaliacao_id'] = $tipoId;
            if ($meta !== null) {
                $label = trim((string) ($meta['label'] ?? ''));
                if ($label !== '') {
                    $def['nome'] = $label;
                }
                if ($tipoId <= 0 && (int) ($meta['tipo_avaliacao_id'] ?? 0) > 0) {
                    $def['tipo_avaliacao_id'] = (int) $meta['tipo_avaliacao_id'];
                }
            }
            return $def;
        }

        if ($meta === null && $tipoId <= 0) {
            return null;
        }

        $id = $tipoId > 0 ? $tipoId : (int) ($meta['tipo_avaliacao_id'] ?? 0);
        $nome = $meta !== null ? trim((string) ($meta['label'] ?? '')) : '';
        if ($nome === '') {
            $nome = 'Tipo #' . $id;
        }
        $chaveQuadro = $meta !== null ? trim((string) ($meta['chave_quadro'] ?? '')) : '';
        $codigo = $peca;
        if ($codigo === '' || !(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $codigo)) {
            $codigo = 'tipo_' . $id;
        }

        return [
            'codigo' => $codigo,
            'nome' => $nome,
            'source_type' => 'provas_sistema',
            'filtro_titulo' => $chaveQuadro !== '' ? strtolower($chaveQuadro) : strtolower($nome),
            'tipo_sugerido' => $nome,
            'tipo_avaliacao_id' => $id,
        ];
    }

    /**
     * @param array{calc_type?:string,materia_unica?:int|bool,usar_percentual?:int|bool,tipo_avaliacao_id?:int,papel?:string} $opts
     * @param array<string,mixed> $estado
     * @return array<string,mixed>|null
     */
    private function definirComponentePeca(string $peca, array $opts = [], array $estado = []): ?array
    {
        $calc = strtolower(trim((string) ($opts['calc_type'] ?? $this->calcTypePadrao($peca))));
        if (!in_array($calc, ['media', 'soma', 'maior', 'ultima'], true)) {
            $calc = $this->calcTypePadrao($peca);
        }
        $materiaUnica = array_key_exists('materia_unica', $estado)
            ? (!empty($estado['materia_unica']) ? 1 : 0)
            : (!empty($opts['materia_unica']) ? 1 : 0);
        $usarPerc = array_key_exists('usar_percentual', $opts)
            ? (!empty($opts['usar_percentual']) ? 1 : 0)
            : $this->usarPercentualPadrao($peca);
        if (!$this->pecaPermiteAcertosQuestoes($peca)) {
            $usarPerc = 0;
        }

        $base = $this->resolverDefinicaoPeca($peca, $opts);
        if ($base === null) {
            return null;
        }

        $dataIni = (string) ($estado['data_inicio'] ?? '');
        $dataFim = (string) ($estado['data_fim'] ?? '');
        $config = ['formula_mode' => 'single', 'expressao' => '', 'papel_wizard' => $this->normalizarPapel($opts['papel'] ?? '', $peca)];
        $blocosIds = [];
        $tipoId = max(0, (int) ($opts['tipo_avaliacao_id'] ?? ($base['tipo_avaliacao_id'] ?? 0)));
        $tipoNome = '';
        $filtroTitulo = $base['filtro_titulo'];

        if ($base['source_type'] === 'jornadas') {
            $modo = (string) ($estado['jornada_modo'] ?? 'todas');
            if ($modo === 'selecionadas') {
                $ids = array_values(array_unique(array_filter(
                    array_map('intval', (array) ($estado['jornada_ids'] ?? [])),
                    static fn ($id) => $id > 0
                )));
                if ($ids === []) {
                    return null;
                }
            } else {
                $ids = $this->resolverIdsJornadaPorBimestre($estado);
                if ($ids === []) {
                    return null;
                }
                $config['jornada_bimestres'] = $this->bimestresJornadaDoEstado($estado);
            }
            $config['jornada_ids'] = $ids;
            if ($dataIni !== '') {
                $config['data_ini'] = $dataIni;
            }
            if ($dataFim !== '') {
                $config['data_fim'] = $dataFim;
            }
            $notaModo = (string) ($estado['jornada_nota_modo'] ?? 'linear');
            if ($notaModo === 'faixas') {
                $faixas = $this->normalizarFaixasJornada($estado['jornada_faixas'] ?? null);
                $config['faixas_percentuais'] = $faixas;
                $usarPerc = 0;
            } else {
                $config['faixas_percentuais'] = [];
                $usarPerc = 1;
            }
        } elseif ($base['source_type'] === 'provas_sistema') {
            $tipoRef = $tipoId > 0 ? $tipoId : ($base['tipo_sugerido'] !== '' ? $base['tipo_sugerido'] : $filtroTitulo);
            $bimsPeca = $this->normalizarBimestresLista($opts['bimestres'] ?? []);
            if ($bimsPeca === []) {
                $bimEstado = (int) ($estado['bimestre'] ?? 0);
                if ($bimEstado >= 1 && $bimEstado <= 4) {
                    $bimsPeca = [$bimEstado];
                }
            }
            $manualBlocos = !empty($opts['blocos_ids_manual']);
            $blocosManualIds = $this->normalizarIdsLista($opts['blocos_ids'] ?? []);
            $resolvido = $manualBlocos
                ? ['tipo' => null, 'blocos_ids' => $blocosManualIds]
                : $this->ferramentas->resolverBlocosPorTipo(
                    $tipoRef,
                    $dataIni !== '' ? $dataIni : null,
                    $dataFim !== '' ? $dataFim : null,
                    100,
                    null,
                    $bimsPeca
                );
            if ($resolvido['tipo'] !== null) {
                $tipoId = (int) $resolvido['tipo']['id'];
                $tipoNome = (string) $resolvido['tipo']['nome'];
                $config['tipo_avaliacao_id'] = $tipoId;
                $config['tipo_avaliacao_nome'] = $tipoNome;
            }
            if ($bimsPeca !== []) {
                $config['prova_bimestres'] = $bimsPeca;
            }
            if ($manualBlocos) {
                $config['blocos_ids_manual'] = 1;
            }
            $blocosIds = $resolvido['blocos_ids'] ?? [];
            // Com blocos resolvidos, o motor ignora filtro_titulo — ok.
            if ($blocosIds !== []) {
                $filtroTitulo = '';
            }
        }

        return [
            'codigo' => $base['codigo'],
            'nome' => $base['nome'],
            'source_type' => $base['source_type'],
            'calc_type' => $calc,
            'peso' => 1,
            'filtro_titulo' => $filtroTitulo,
            'blocos_ids' => $blocosIds,
            'materias_ids' => [],
            'materia_unica' => $materiaUnica,
            'usar_percentual' => $usarPerc,
            'escala_max' => 10,
            'obrigatorio' => 0,
            'tipo_avaliacao_id' => $tipoId > 0 ? $tipoId : null,
            'tipo_avaliacao_nome' => $tipoNome !== '' ? $tipoNome : null,
            'config' => $config,
        ];
    }

    /** @param list<string> $codigos */
    private function expressaoMedia(array $codigos): string
    {
        $codigos = array_values(array_filter(array_map('strval', $codigos)));
        $n = count($codigos);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $codigos[0];
        }
        return '(' . implode(' + ', $codigos) . ') / ' . $n;
    }

    /** @return array<string,mixed> */
    private function blocoCalculado(string $codigo, string $nome, string $expressao): array
    {
        return [
            'codigo' => $codigo,
            'nome' => $nome,
            'source_type' => 'calculado',
            'calc_type' => 'media',
            'peso' => 1,
            'filtro_titulo' => '',
            'blocos_ids' => [],
            'materias_ids' => [],
            'usar_percentual' => 0,
            'escala_max' => 10,
            'obrigatorio' => 0,
            'config' => [
                'expressao' => $expressao,
                'formula_mode' => 'single',
            ],
        ];
    }

    /**
     * Tabela padrão: 90% concluídas → 10, 80% → 9, … (igual ao motor do boletim).
     *
     * @return list<array{percentual_min:int,nota:float}>
     */
    public static function faixasJornadaPadrao(): array
    {
        return [
            ['percentual_min' => 90, 'nota' => 10.0],
            ['percentual_min' => 80, 'nota' => 9.0],
            ['percentual_min' => 70, 'nota' => 8.0],
            ['percentual_min' => 60, 'nota' => 7.0],
            ['percentual_min' => 50, 'nota' => 6.0],
            ['percentual_min' => 40, 'nota' => 5.0],
            ['percentual_min' => 30, 'nota' => 3.75],
            ['percentual_min' => 20, 'nota' => 2.5],
            ['percentual_min' => 10, 'nota' => 1.25],
        ];
    }

    /**
     * @param mixed $raw
     * @return list<array{percentual_min:int,nota:float}>
     */
    private function normalizarFaixasJornada($raw): array
    {
        $base = self::faixasJornadaPadrao();
        if (!is_array($raw) || $raw === []) {
            return $base;
        }
        $map = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $p = (int) ($item['percentual_min'] ?? 0);
            if ($p < 0 || $p > 100 || !is_numeric($item['nota'] ?? null)) {
                continue;
            }
            $map[$p] = max(0.0, (float) $item['nota']);
        }
        if ($map === []) {
            return $base;
        }
        foreach ($base as $i => $faixa) {
            $p = (int) $faixa['percentual_min'];
            if (isset($map[$p])) {
                $base[$i]['nota'] = $map[$p];
            }
        }
        foreach ($map as $p => $nota) {
            $existe = false;
            foreach ($base as $faixa) {
                if ((int) $faixa['percentual_min'] === $p) {
                    $existe = true;
                    break;
                }
            }
            if (!$existe) {
                $base[] = ['percentual_min' => $p, 'nota' => $nota];
            }
        }
        usort($base, static function (array $a, array $b): int {
            return (int) $b['percentual_min'] <=> (int) $a['percentual_min'];
        });
        return $base;
    }

    /**
     * @param array<string,mixed> $estado
     * @param list<array<string,mixed>> $comps
     */
    private function aplicarJornadaDoFormulario(array &$estado, array $comps): void
    {
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'jornadas') {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $ids = array_values(array_filter(array_map('intval', (array) ($cfg['jornada_ids'] ?? []))));
            $bims = [];
            foreach ((array) ($cfg['jornada_bimestres'] ?? []) as $b) {
                $b = (int) $b;
                if ($b >= 1 && $b <= 4) {
                    $bims[] = $b;
                }
            }
            $bims = array_values(array_unique($bims));
            if ($bims !== []) {
                $estado['jornada_modo'] = 'bimestre';
                $estado['jornada_bimestres'] = $bims;
                $estado['jornada_ids'] = $ids;
            } elseif ($ids !== []) {
                $estado['jornada_modo'] = 'selecionadas';
                $estado['jornada_ids'] = $ids;
            }
            $faixas = is_array($cfg['faixas_percentuais'] ?? null) ? $cfg['faixas_percentuais'] : [];
            if ($faixas !== []) {
                $estado['jornada_nota_modo'] = 'faixas';
                $estado['jornada_faixas'] = $this->normalizarFaixasJornada($faixas);
            } elseif (!empty($c['usar_percentual'])) {
                $estado['jornada_nota_modo'] = 'linear';
            }
            return;
        }
    }

    /**
     * @param array<string,mixed> $comp
     */
    private function resumoEscopoJornada(array $comp): string
    {
        $cfg = is_array($comp['config'] ?? null) ? $comp['config'] : [];
        $bims = [];
        foreach ((array) ($cfg['jornada_bimestres'] ?? []) as $b) {
            $b = (int) $b;
            if ($b >= 1 && $b <= 4) {
                $bims[] = $b;
            }
        }
        $bims = array_values(array_unique($bims));
        $nIds = count((array) ($cfg['jornada_ids'] ?? []));
        if ($bims !== []) {
            $txt = implode(', ', array_map(static fn (int $b) => $b . 'º', $bims));
            return ' · ' . $txt . ' bimestre' . (count($bims) > 1 ? 's' : '') . ($nIds > 0 ? ' (' . $nIds . ')' : '');
        }
        if ($nIds > 0) {
            return ' · ' . $nIds . ' selecionada(s)';
        }
        return ' · bimestre da regra';
    }

    /**
     * @param array<string,mixed> $estado
     * @return list<int>
     */
    private function bimestresJornadaDoEstado(array $estado): array
    {
        $bims = array_values(array_unique(array_filter(array_map('intval', (array) ($estado['jornada_bimestres'] ?? [])))));
        $bims = array_values(array_filter($bims, static fn ($b) => $b >= 1 && $b <= 4));
        if ($bims === []) {
            $b = (int) ($estado['bimestre'] ?? 0);
            if ($b >= 1 && $b <= 4) {
                $bims = [$b];
            }
        }
        return $bims;
    }

    /**
     * @param array<string,mixed> $estado
     * @return list<int>
     */
    private function resolverIdsJornadaPorBimestre(array $estado): array
    {
        return $this->ferramentas->resolverIdsJornadaPorBimestre(
            $this->bimestresJornadaDoEstado($estado),
            (int) ($estado['ano_letivo'] ?? 0)
        );
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function querLayoutQuadro(array $estado): bool
    {
        if ($this->ehBoletimComposto($estado)) {
            return false;
        }
        if (($estado['modelo_key'] ?? '') === 'quadro_semanal'
            && in_array('semanal', (array) ($estado['pecas'] ?? []), true)) {
            return true;
        }
        return in_array('semanal', (array) ($estado['pecas'] ?? []), true);
    }

    /**
     * @param array<string,mixed> $estado
     * @param array<string,mixed> $rascunho
     */
    private function deveRemontarComoQuadro(array $estado, array $rascunho): bool
    {
        if (($estado['origem'] ?? '') === 'clonar') {
            return false;
        }
        if (!$this->querLayoutQuadro($estado)) {
            return false;
        }
        return !$this->rascunhoJaEhQuadro($rascunho);
    }

    /**
     * @param array<string,mixed> $rascunho
     */
    private function rascunhoJaEhQuadro(array $rascunho): bool
    {
        foreach ((array) ($rascunho['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (preg_match('/^s[1-8]$/', $cod)) {
                return true;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            if (in_array($g, ['quadro_a', 'quadro_b'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function montarQuadroDoEstado(array $estado): array
    {
        $semanas = $this->semanasQuadroConfig();
        $rascunho = $this->ferramentas->montarRascunhoQuadroSemanal([
            'ano_letivo' => (int) ($estado['ano_letivo'] ?? date('Y')),
            'bimestre' => (int) ($estado['bimestre'] ?? 1),
            'nome' => (string) ($estado['nome'] ?? ''),
            'codigo' => (string) ($estado['codigo'] ?? ''),
            'modo' => (string) ($estado['modo'] ?? 'criar'),
            'regra_id' => $estado['regra_id'] ?? null,
            'exibir_em' => (string) ($estado['exibir_em'] ?? 'boletim'),
            'turmas_ids' => $estado['turmas_ids'] ?? [],
            'materias_ids' => $estado['materias_ids'] ?? [],
            'series_ids' => $estado['series_ids'] ?? [],
            'default_data_inicio' => (string) ($estado['data_inicio'] ?? ''),
            'default_data_fim' => (string) ($estado['data_fim'] ?? ''),
            'round_mode' => (string) ($estado['round_mode'] ?? 'none'),
            'nota_minima_aprovacao' => $estado['nota_minima_aprovacao'] ?? 6,
            'semanas_a' => $semanas['a'],
            'semanas_b' => $semanas['b'],
            'so_semanas_com_evento' => false,
            'pecas' => $estado['pecas'] ?? [],
        ]);
        $rascunho = $this->mesclarJornadaNoQuadro($rascunho, $estado);
        return $this->aplicarPapeisNoQuadro($rascunho, $estado);
    }

    /**
     * @return array{a:list<int>,b:list<int>}
     */
    private function semanasQuadroConfig(): array
    {
        $a = [1, 3, 5, 7];
        $b = [2, 4, 6, 8];
        $path = dirname(__DIR__) . '/Modulos/notas-semanais/Models/NotasSemanaisConfig.php';
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
            error_log('BoletimAssistenteWizard semanas quadro: ' . $e->getMessage());
        }
        return ['a' => $a, 'b' => $b];
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function mesclarJornadaNoQuadro(array $rascunho, array $estado): array
    {
        if (!in_array('jornada', (array) ($estado['pecas'] ?? []), true)) {
            return $rascunho;
        }
        $opts = is_array($estado['pecas_opcoes']['jornada'] ?? null) ? $estado['pecas_opcoes']['jornada'] : [];
        $def = $this->definirComponentePeca('jornada', $opts, $estado);
        if ($def === null) {
            return $rascunho;
        }
        $def['config'] = is_array($def['config'] ?? null) ? $def['config'] : [];
        $def['config']['layout_group'] = 'quadro_comum';
        $def['config']['layout_type'] = 'media';
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $out = [];
        $inserido = false;
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (!$inserido && (string) ($c['codigo'] ?? '') === 'media_bim') {
                $out[] = $def;
                $inserido = true;
            }
            $out[] = $c;
        }
        if (!$inserido) {
            $out[] = $def;
        }
        $rascunho['componentes'] = $out;
        return $rascunho;
    }

    /**
     * Recalcula media_bim / media_final do quadro a partir dos papéis do wizard.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarPapeisNoQuadro(array $rascunho, array $estado): array
    {
        $mapaCodigos = [
            'semanal' => 'media_sem',
            'bimestral' => 'prova_bim',
            'enac' => 'enac',
            'trabalho' => 'trab',
            'participacao' => 'part',
            'recuperacao' => 'rec',
            'jornada' => 'jornada',
        ];
        $dataIni = (string) ($estado['data_inicio'] ?? '');
        $dataFim = (string) ($estado['data_fim'] ?? '');
        $optsSemanal = is_array($estado['pecas_opcoes']['semanal'] ?? null) ? $estado['pecas_opcoes']['semanal'] : [];
        $bimsSemanal = $this->normalizarBimestresLista($optsSemanal['bimestres'] ?? []);
        $manualSemanal = !empty($optsSemanal['blocos_ids_manual']);
        $blocosSemanal = $this->normalizarIdsLista($optsSemanal['blocos_ids'] ?? []);
        $comps = [];
        foreach ((array) ($rascunho['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if (in_array($cod, ['media_bim', 'media_final'], true)) {
                continue;
            }
            $c['config'] = is_array($c['config'] ?? null) ? $c['config'] : [];
            if (preg_match('/^s([1-8])$/', $cod, $mSem)) {
                if ($bimsSemanal !== []) {
                    $c['config']['prova_bimestres'] = $bimsSemanal;
                }
                if ($manualSemanal) {
                    $c['blocos_ids'] = $blocosSemanal;
                    $c['config']['blocos_ids_manual'] = 1;
                }
                $tipoSem = (int) ($c['config']['tipo_avaliacao_id'] ?? $c['tipo_avaliacao_id'] ?? 0);
                if ($tipoSem > 0 && !$manualSemanal) {
                    $resolvidoSem = $this->ferramentas->resolverBlocosPorTipo(
                        $tipoSem,
                        $dataIni !== '' ? $dataIni : null,
                        $dataFim !== '' ? $dataFim : null,
                        100,
                        (int) $mSem[1],
                        $bimsSemanal
                    );
                    if (($resolvidoSem['blocos_ids'] ?? []) !== []) {
                        $c['blocos_ids'] = $resolvidoSem['blocos_ids'];
                    }
                }
            }
            $pecaKey = array_search($cod, $mapaCodigos, true);
            if ($pecaKey !== false) {
                $opts = is_array($estado['pecas_opcoes'][$pecaKey] ?? null) ? $estado['pecas_opcoes'][$pecaKey] : [];
                $c['config']['papel_wizard'] = $this->normalizarPapel($opts['papel'] ?? '', (string) $pecaKey);
                $calc = strtolower(trim((string) ($opts['calc_type'] ?? $this->calcTypePadrao((string) $pecaKey))));
                if (in_array($calc, ['media', 'soma', 'maior', 'ultima'], true)) {
                    $c['calc_type'] = $calc;
                }
                if (array_key_exists('usar_percentual', $opts)) {
                    $c['usar_percentual'] = !empty($opts['usar_percentual']) ? 1 : 0;
                } else {
                    $c['usar_percentual'] = $this->usarPercentualPadrao((string) $pecaKey);
                }
                if (!$this->pecaPermiteAcertosQuestoes((string) $pecaKey)) {
                    $c['usar_percentual'] = 0;
                }
                if ($pecaKey !== 'jornada') {
                    $bimsPeca = $this->normalizarBimestresLista($opts['bimestres'] ?? []);
                    if ($bimsPeca !== []) {
                        $c['config']['prova_bimestres'] = $bimsPeca;
                    }
                    $manualBlocosPeca = !empty($opts['blocos_ids_manual']);
                    if ($manualBlocosPeca) {
                        $c['blocos_ids'] = $this->normalizarIdsLista($opts['blocos_ids'] ?? []);
                        $c['config']['blocos_ids_manual'] = 1;
                    }
                    $tipoPeca = (int) ($opts['tipo_avaliacao_id'] ?? $c['config']['tipo_avaliacao_id'] ?? $c['tipo_avaliacao_id'] ?? 0);
                    if ($tipoPeca > 0 && !$manualBlocosPeca) {
                        $resolvidoPeca = $this->ferramentas->resolverBlocosPorTipo(
                            $tipoPeca,
                            $dataIni !== '' ? $dataIni : null,
                            $dataFim !== '' ? $dataFim : null,
                            100,
                            null,
                            $bimsPeca
                        );
                        if (($resolvidoPeca['blocos_ids'] ?? []) !== []) {
                            $c['blocos_ids'] = $resolvidoPeca['blocos_ids'];
                        }
                    }
                }
            }
            $comps[] = $c;
        }

        $presentes = [];
        foreach ($comps as $c) {
            $presentes[strtolower(trim((string) ($c['codigo'] ?? '')))] = true;
        }

        $mapa = [];
        $pecasFormula = [];
        foreach ((array) ($estado['pecas'] ?? []) as $p) {
            $p = strtolower(trim((string) $p));
            if ($p === '' || !isset($mapaCodigos[$p])) {
                continue;
            }
            $cod = $mapaCodigos[$p];
            if (empty($presentes[$cod])) {
                continue;
            }
            $opts = is_array($estado['pecas_opcoes'][$p] ?? null) ? $estado['pecas_opcoes'][$p] : [];
            $mapa[$p] = [
                'codigo' => $cod,
                'papel' => $this->normalizarPapel($opts['papel'] ?? '', $p),
                'nome' => $p,
            ];
            $pecasFormula[] = $p;
        }

        $formula = $this->montarFormulaPorPapeis($comps, $mapa, $pecasFormula, 'media_bim', 'media_final', true);
        $rascunho['componentes'] = $comps;
        $rascunho['formula_final'] = $formula !== '' ? $formula : (string) ($rascunho['formula_final'] ?? 'media_bim');
        $rascunho = $this->aplicarFormulasBlocos($rascunho, $estado, $mapa);
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : $comps;
        foreach ($comps as &$c) {
            if ((string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $cod = (string) ($c['codigo'] ?? '');
            if ($cod === 'media_sem') {
                continue;
            }
            $c['config'] = is_array($c['config'] ?? null) ? $c['config'] : [];
            $c['config']['layout_group'] = $c['config']['layout_group'] ?? 'quadro_comum';
            $nomeCustom = trim((string) ((is_array($estado['nomes_blocos'] ?? null) ? $estado['nomes_blocos'] : [])[$cod] ?? ''));
            if ($nomeCustom !== '') {
                $c['nome'] = $nomeCustom;
            }
            if ($cod === 'media_final') {
                $c['config']['layout_type'] = 'resultado';
                if ($nomeCustom === '') {
                    $c['nome'] = 'Média Bim Final';
                }
            } elseif ($cod === 'media_bim') {
                $c['config']['layout_type'] = 'media';
                if ($nomeCustom === '') {
                    $c['nome'] = 'Média Bim';
                }
            } else {
                $c['config']['layout_type'] = $c['config']['layout_type'] ?? 'media';
            }
        }
        unset($c);

        $rascunho['componentes'] = $comps;
        $ff = trim((string) ($rascunho['formula_final'] ?? ''));
        $rascunho['formula_final'] = $ff !== '' ? $ff : 'media_bim';
        return $rascunho;
    }

    /**
     * Aplica estado.formulas_blocos (clique na coluna amarela) em cada calculado.
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @param array<string,array{codigo:string,papel?:string,nome?:string}> $mapaPecas
     * @return array<string,mixed>
     */
    private function aplicarFormulasBlocos(array $rascunho, array $estado, array $mapaPecas): array
    {
        $formulas = is_array($estado['formulas_blocos'] ?? null) ? $estado['formulas_blocos'] : [];
        $excecoes = is_array($estado['formulas_materias_blocos'] ?? null) ? $estado['formulas_materias_blocos'] : [];
        if ($formulas === [] && $excecoes === []) {
            return $this->aplicarNomesBlocos($rascunho, $estado);
        }

        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $ehQuadro = $this->rascunhoJaEhQuadro($rascunho);
        $mapa = $this->expandirMapaFormula($mapaPecas, $comps);
        $nomes = [
            'media_bim' => 'Média Bim',
            'media_final' => $ehQuadro ? 'Média Bim Final' : 'Média final',
            'media_sem' => 'Média Sem',
        ];

        $ordem = [];
        foreach (['media_bim', 'media_final'] as $codPref) {
            if (isset($formulas[$codPref])) {
                $ordem[] = $codPref;
            }
        }
        foreach ($formulas as $cod => $_toks) {
            $cod = strtolower(trim((string) $cod));
            if ($cod === '' || $cod === 'media_sem' || in_array($cod, $ordem, true)) {
                continue;
            }
            $ordem[] = $cod;
        }

        $aplicouFinal = false;
        $aplicouBim = false;
        foreach ($ordem as $codigoAlvo) {
            $tokens = is_array($formulas[$codigoAlvo] ?? null) ? $formulas[$codigoAlvo] : [];
            if ($tokens === []) {
                continue;
            }
            $mapaCompila = $mapa;
            unset($mapaCompila[$codigoAlvo]);
            $exp = $this->compilarTokensParaExpressao($tokens, $mapaCompila, []);
            if ($exp === '') {
                continue;
            }
            $nomeCustom = trim((string) ((is_array($estado['nomes_blocos'] ?? null) ? $estado['nomes_blocos'] : [])[$codigoAlvo] ?? ''));
            $nome = $nomeCustom !== '' ? $nomeCustom : ($nomes[$codigoAlvo] ?? $codigoAlvo);
            $idxExistente = null;
            $sourceExistente = '';
            foreach ($comps as $i => $c) {
                if (!is_array($c)) {
                    continue;
                }
                if (strtolower(trim((string) ($c['codigo'] ?? ''))) !== $codigoAlvo) {
                    continue;
                }
                $idxExistente = $i;
                $sourceExistente = (string) ($c['source_type'] ?? '');
                break;
            }
            if ($idxExistente !== null && $sourceExistente !== 'calculado') {
                continue;
            }
            if ($idxExistente !== null) {
                $c = $comps[$idxExistente];
                $c['config'] = is_array($c['config'] ?? null) ? $c['config'] : [];
                $c['config']['expressao'] = $exp;
                if ($ehQuadro) {
                    $c['config']['layout_group'] = $c['config']['layout_group'] ?? 'quadro_comum';
                    $c['config']['layout_type'] = $codigoAlvo === 'media_final' ? 'resultado' : 'media';
                }
                $c['nome'] = $nome;
                $comps[$idxExistente] = $c;
            } else {
                $bloco = $this->blocoCalculado($codigoAlvo, $nome, $exp);
                if ($ehQuadro) {
                    $bloco['config']['layout_group'] = 'quadro_comum';
                    $bloco['config']['layout_type'] = $codigoAlvo === 'media_final' ? 'resultado' : 'media';
                }
                $comps[] = $bloco;
            }
            $mapa[$codigoAlvo] = ['codigo' => $codigoAlvo, 'papel' => 'exibe', 'nome' => $nome];
            if ($codigoAlvo === 'media_final') {
                $aplicouFinal = true;
            } elseif ($codigoAlvo === 'media_bim') {
                $aplicouBim = true;
            }
        }

        $rascunho['componentes'] = $comps;
        if ($aplicouFinal) {
            $rascunho['formula_final'] = 'media_final';
        } elseif ($aplicouBim) {
            $temFinal = false;
            foreach ($comps as $c) {
                if (is_array($c) && strtolower((string) ($c['codigo'] ?? '')) === 'media_final') {
                    $temFinal = true;
                    break;
                }
            }
            if (!$temFinal) {
                $rascunho['formula_final'] = 'media_bim';
            }
        }
        $rascunho = $this->aplicarExcecoesMateriaNosCalculados($rascunho, $estado, $mapa);
        return $this->aplicarNomesBlocos($rascunho, $estado);
    }

    /**
     * Fórmulas alternativas só em algumas matérias (ex.: sem semanal → bimestral + ENAC).
     *
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @param array<string,array{codigo:string,papel?:string,nome?:string}> $mapa
     * @return array<string,mixed>
     */
    private function aplicarExcecoesMateriaNosCalculados(array $rascunho, array $estado, array $mapa): array
    {
        $excecoes = is_array($estado['formulas_materias_blocos'] ?? null) ? $estado['formulas_materias_blocos'] : [];
        if ($excecoes === []) {
            return $rascunho;
        }
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $mapa = $this->expandirMapaFormula($mapa, $comps);
        foreach ($comps as $i => $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || $cod === 'media_sem' || !isset($excecoes[$cod])) {
                continue;
            }
            $porMat = $excecoes[$cod];
            if ($porMat instanceof \stdClass) {
                $porMat = (array) $porMat;
            }
            $porMat = is_array($porMat) ? $porMat : [];
            $mapaCompila = $mapa;
            unset($mapaCompila[$cod]);
            $fm = [];
            foreach ($porMat as $midRaw => $tokens) {
                $mid = (int) $midRaw;
                if ($mid <= 0 || !is_array($tokens) || $tokens === []) {
                    continue;
                }
                $exp = $this->compilarTokensParaExpressao($tokens, $mapaCompila, []);
                if ($exp !== '') {
                    $fm[$mid] = $exp;
                }
            }
            $c['config'] = is_array($c['config'] ?? null) ? $c['config'] : [];
            $c['config']['formula_materias'] = $fm;
            $c['config']['formula_mode'] = $fm !== [] ? 'per_materia' : 'single';
            $comps[$i] = $c;
        }
        $rascunho['componentes'] = $comps;
        return $rascunho;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarNomesBlocos(array $rascunho, array $estado): array
    {
        $nomes = is_array($estado['nomes_blocos'] ?? null) ? $estado['nomes_blocos'] : [];
        if ($nomes === []) {
            return $rascunho;
        }
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as &$c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || !isset($nomes[$cod])) {
                continue;
            }
            if ((string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            if ($cod === 'media_sem' || (bool) preg_match('/^s[1-8]$/', $cod)) {
                continue;
            }
            $nome = trim((string) $nomes[$cod]);
            if ($nome !== '') {
                $c['nome'] = $nome;
            }
        }
        unset($c);
        $rascunho['componentes'] = $comps;
        return $rascunho;
    }

    /**
     * @param array<string,array{codigo:string,papel?:string,nome?:string}> $mapaPecas
     * @param list<array<string,mixed>> $comps
     * @return array<string,array{codigo:string,papel?:string,nome?:string}>
     */
    private function expandirMapaFormula(array $mapaPecas, array $comps): array
    {
        $mapa = $mapaPecas;
        $aliases = [
            'semanal' => 'media_sem',
            'bimestral' => 'prova_bim',
            'trabalho' => 'trab',
            'participacao' => 'part',
            'recuperacao' => 'rec',
        ];
        foreach ($aliases as $peca => $codQuadro) {
            if (!isset($mapa[$peca]['codigo'])) {
                continue;
            }
            if (!isset($mapa[$codQuadro])) {
                $mapa[$codQuadro] = $mapa[$peca];
            }
        }
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $mapa[$cod] = [
                'codigo' => $cod,
                'papel' => 'exibe',
                'nome' => (string) ($c['nome'] ?? $cod),
            ];
        }
        return $mapa;
    }

    /**
     * Zera IDs de prova/jornada da origem para o clone re-resolver no bimestre/datas novos.
     *
     * @param list<array> $componentes
     * @param array<string,mixed> $estado
     * @return list<array>
     */
    private function ajustarComponentesClonados(array $componentes, array $estado): array
    {
        $dataIni = (string) ($estado['data_inicio'] ?? '');
        $dataFim = (string) ($estado['data_fim'] ?? '');
        $bims = [];
        foreach ((array) ($estado['jornada_bimestres'] ?? []) as $b) {
            $b = (int) $b;
            if ($b >= 1 && $b <= 4) {
                $bims[] = $b;
            }
        }
        $bims = array_values(array_unique($bims));
        if ($bims === []) {
            $bim = (int) ($estado['bimestre'] ?? 0);
            if ($bim >= 1 && $bim <= 4) {
                $bims = [$bim];
            }
        }
        $modoJornada = (string) ($estado['jornada_modo'] ?? 'todas');
        $out = [];
        foreach ($componentes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $c['blocos_ids'] = [];
            $c['bloco_id'] = null;
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if ((string) ($c['source_type'] ?? '') === 'jornadas') {
                $cfg['jornada_ids'] = [];
                if ($modoJornada === 'selecionadas') {
                    $cfg['jornada_ids'] = array_values(array_filter(
                        array_map('intval', (array) ($estado['jornada_ids'] ?? [])),
                        static fn ($id) => $id > 0
                    ));
                    unset($cfg['jornada_bimestres']);
                } elseif ($modoJornada === 'todas' && ($dataIni !== '' || $dataFim !== '')) {
                    unset($cfg['jornada_bimestres']);
                } elseif ($bims !== []) {
                    $cfg['jornada_bimestres'] = $bims;
                } else {
                    unset($cfg['jornada_bimestres']);
                }
                if ($dataIni !== '') {
                    $cfg['data_ini'] = $dataIni;
                }
                if ($dataFim !== '') {
                    $cfg['data_fim'] = $dataFim;
                }
            }
            $c['config'] = $cfg;
            $out[] = $c;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $estado
     * @return list<string>
     */
    private function errosGrupoLinha(array $estado): array
    {
        $g = $this->normalizarGrupoLinha($estado['grupo_linha'] ?? null);
        if (!$g['ativo']) {
            return [];
        }
        $erros = [];
        if ($g['nome'] === '') {
            $erros[] = 'Dê um nome à linha agrupada (ex.: Linguagem Português).';
        }
        if (count($g['materias_ids']) < 2) {
            $erros[] = 'Marque ao menos duas matérias para juntar na linha única.';
        }
        return $erros;
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function grupoLinhaEstaVazio(array $estado): bool
    {
        $g = $this->normalizarGrupoLinha($estado['grupo_linha'] ?? null);
        return !$g['ativo'] && $g['nome'] === '' && $g['materias_ids'] === [];
    }

    /**
     * @return array{ativo:bool,nome:string,modo:string,materias_ids:list<int>}
     */
    public static function grupoLinhaPadrao(): array
    {
        return [
            'ativo' => false,
            'nome' => '',
            'modo' => 'media',
            'materias_ids' => [],
        ];
    }

    /**
     * @param mixed $raw
     * @return array{ativo:bool,nome:string,modo:string,materias_ids:list<int>}
     */
    private function normalizarGrupoLinha($raw): array
    {
        $base = self::grupoLinhaPadrao();
        if (!is_array($raw)) {
            return $base;
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($raw['materias_ids'] ?? [])),
            static fn ($id) => $id > 0
        )));
        $modo = strtolower(trim((string) ($raw['modo'] ?? 'media')));
        return [
            'ativo' => !empty($raw['ativo']),
            'nome' => trim((string) ($raw['nome'] ?? '')),
            'modo' => $modo === 'soma' ? 'soma' : 'media',
            'materias_ids' => $ids,
        ];
    }

    /**
     * @param array<string,mixed> $estado
     * @return array{enabled:bool,key:string,label:string,mode:string,divisor:float,materias_ids:list<int>}|null
     */
    private function configGrupoLinha(array $estado): ?array
    {
        $g = $this->normalizarGrupoLinha($estado['grupo_linha'] ?? null);
        if (!$g['ativo']) {
            return null;
        }
        $ids = $g['materias_ids'];
        $label = $g['nome'];
        if ($label === '' || count($ids) < 2) {
            return null;
        }
        return [
            'enabled' => true,
            'key' => $this->slugGrupo($label),
            'label' => $label,
            'mode' => $g['modo'],
            'divisor' => 1.0,
            'materias_ids' => $ids,
        ];
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarMateriaUnicaNoRascunho(array $rascunho, array $estado): array
    {
        $flag = !empty($estado['materia_unica']) ? 1 : 0;
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            if ((string) ($c['source_type'] ?? '') === 'calculado') {
                continue;
            }
            $c['materia_unica'] = $flag;
            $comps[$i] = $c;
        }
        $rascunho['componentes'] = $comps;
        return $rascunho;
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function aplicarMateriaUnicaNasPecasOpcoes(array &$estado): void
    {
        $flag = !empty($estado['materia_unica']) ? 1 : 0;
        $opcoes = is_array($estado['pecas_opcoes'] ?? null) ? $estado['pecas_opcoes'] : [];
        foreach ($opcoes as $k => $opts) {
            if (!is_array($opts)) {
                $opts = [];
            }
            $opts['materia_unica'] = $flag;
            $opcoes[$k] = $opts;
        }
        $estado['pecas_opcoes'] = $opcoes;
    }

    /**
     * @param list<array<string,mixed>> $comps
     */
    private function inferirMateriaUnicaDeComponentes(array $comps): int
    {
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            if ((string) ($c['source_type'] ?? '') === 'calculado') {
                continue;
            }
            if (!empty($c['materia_unica'])) {
                return 1;
            }
        }
        return 0;
    }

    /**
     * @param array<string,mixed> $rascunho
     */
    private function rascunhoTemMateriaUnica(array $rascunho): bool
    {
        return $this->inferirMateriaUnicaDeComponentes(
            is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : []
        ) === 1;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarGrupoLinhaNoRascunho(array $rascunho, array $estado): array
    {
        $gl = $this->configGrupoLinha($estado);
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        foreach ($comps as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if ($gl !== null) {
                $cfg['group_line'] = $gl;
            } elseif (!$this->grupoLinhaEstaVazio($estado)) {
                unset($cfg['group_line']);
            }
            $c['config'] = $cfg;
            $comps[$i] = $c;
        }
        $rascunho['componentes'] = $comps;
        return $rascunho;
    }

    /**
     * @param array<string,mixed> $estado
     * @param list<array<string,mixed>> $comps
     */
    private function aplicarGrupoLinhaDoFormulario(array &$estado, array $comps): void
    {
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $gl = is_array($cfg['group_line'] ?? null) ? $cfg['group_line'] : [];
            if (empty($gl['enabled'])) {
                continue;
            }
            $ids = array_values(array_unique(array_filter(
                array_map('intval', (array) ($gl['materias_ids'] ?? [])),
                static fn ($id) => $id > 0
            )));
            $label = trim((string) ($gl['label'] ?? $gl['key'] ?? ''));
            if ($label === '' || count($ids) < 2) {
                continue;
            }
            $estado['grupo_linha'] = [
                'ativo' => true,
                'nome' => $label,
                'modo' => (strtolower((string) ($gl['mode'] ?? 'media')) === 'soma') ? 'soma' : 'media',
                'materias_ids' => $ids,
            ];
            return;
        }
    }

    private function slugGrupo(string $nome): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
        ];
        $s = strtr(mb_strtolower(trim($nome)), $map);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? '';
        $s = trim($s, '_');
        if ($s === '') {
            return 'grupo';
        }
        return substr($s, 0, 40);
    }

    /**
     * @param array<string,mixed> $rascunho
     */
    private function resumoGrupoLinhaDoRascunho(array $rascunho): string
    {
        foreach ((array) ($rascunho['componentes'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $gl = is_array($c['config']['group_line'] ?? null) ? $c['config']['group_line'] : [];
            if (empty($gl['enabled'])) {
                continue;
            }
            $label = trim((string) ($gl['label'] ?? ''));
            $n = count((array) ($gl['materias_ids'] ?? []));
            $modo = ((string) ($gl['mode'] ?? 'media')) === 'soma' ? 'soma' : 'média';
            if ($label === '' || $n < 2) {
                continue;
            }
            return 'Linha única: “' . $label . '” (' . $n . ' matérias, ' . $modo . ').';
        }
        return '';
    }

    /**
     * Aplica ordem das colunas e gera preview fictício (exemplo visual).
     *
     * @param array<string,mixed> $resultado
     * @return array<string,mixed>
     */
    public function enriquecerSaida(array $resultado): array
    {
        $estado = is_array($resultado['estado'] ?? null) ? $resultado['estado'] : [];
        $rascunho = is_array($resultado['rascunho'] ?? null) ? $resultado['rascunho'] : null;
        if ($rascunho !== null) {
            try {
                if (!$this->ehBoletimComposto($estado)) {
                    $rascunho = $this->aplicarOrdemColunas($rascunho, $estado);
                    $estado['colunas_ordem'] = $this->extrairCodigosColunasMoveis($rascunho);
                }
                $resultado['rascunho'] = $rascunho;
                $resultado['estado'] = $estado;
            } catch (Throwable $e) {
                error_log('BoletimAssistenteWizard ordem colunas: ' . $e->getMessage());
            }
        }
        try {
            $resultado['preview'] = $this->montarPreviewFicticio($rascunho, $estado);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteWizard preview: ' . $e->getMessage());
            $resultado['preview'] = [
                'modo' => 'vazio',
                'aviso' => 'Exemplo indisponível agora.',
                'tabelas' => [],
                'colunas' => [],
                'pecas_disponiveis' => [],
            ];
        }
        return $resultado;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizarColunasOrdem($raw): array
    {
        $out = [];
        foreach ((array) $raw as $cod) {
            $cod = strtolower(trim((string) $cod));
            if ($cod === '' || $cod === '_semanal' || $cod === 'media_sem') {
                continue;
            }
            if (!(bool) preg_match('/^[a-z][a-z0-9_]{0,40}$/', $cod)) {
                continue;
            }
            if (preg_match('/^s[1-8]$/', $cod)) {
                continue;
            }
            if (!in_array($cod, $out, true)) {
                $out[] = $cod;
            }
            if (count($out) >= 40) {
                break;
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $c */
    private function componenteEhSemanaQuadro(array $c): bool
    {
        $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
        if (preg_match('/^s[1-8]$/', $cod)) {
            return true;
        }
        $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
        return strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? ''))) === 'semana_nq';
    }

    /**
     * @param array<string,mixed> $rascunho
     * @return list<string>
     */
    private function extrairCodigosColunasMoveis(array $rascunho): array
    {
        $out = [];
        foreach ((array) ($rascunho['componentes'] ?? []) as $c) {
            if (!is_array($c) || $this->componenteEhSemanaQuadro($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || $cod === 'media_sem' || in_array($cod, $out, true)) {
                continue;
            }
            $out[] = $cod;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function aplicarOrdemColunas(array $rascunho, array $estado): array
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $semanas = [];
        $mediaSem = [];
        $outros = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            if ($this->componenteEhSemanaQuadro($c)) {
                $semanas[] = $c;
            } elseif ($cod === 'media_sem') {
                $mediaSem[] = $c;
            } else {
                $outros[$cod] = $c;
            }
        }
        $ordem = $this->normalizarColunasOrdem($estado['colunas_ordem'] ?? []);
        $ordenados = [];
        $vistos = [];
        foreach ($ordem as $cod) {
            if (!isset($outros[$cod])) {
                continue;
            }
            $ordenados[] = $outros[$cod];
            $vistos[$cod] = true;
        }
        foreach ($outros as $cod => $c) {
            if (!isset($vistos[$cod])) {
                $ordenados[] = $c;
            }
        }
        $rascunho['componentes'] = array_merge($semanas, $mediaSem, $ordenados);
        return $rascunho;
    }

    /**
     * @param array<string,mixed>|null $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function montarPreviewFicticio(?array $rascunho, array $estado): array
    {
        if ($this->rascunhoEhLayoutBoletim($rascunho, $estado)) {
            return $this->montarPreviewFicticioBoletim($rascunho, $estado);
        }

        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $pecasSel = [];
        foreach ((array) ($estado['pecas'] ?? []) as $p) {
            $p = strtolower(trim((string) $p));
            if ($p !== '') {
                $pecasSel[] = $p;
            }
        }
        $disponiveis = [];
        try {
            foreach ($this->pecasMeta() as $meta) {
                if (!is_array($meta)) {
                    continue;
                }
                $key = strtolower(trim((string) ($meta['key'] ?? '')));
                if ($key === '' || in_array($key, $pecasSel, true)) {
                    continue;
                }
                $disponiveis[] = [
                    'key' => $key,
                    'label' => (string) ($meta['label'] ?? $key),
                ];
            }
        } catch (Throwable $e) {
            $disponiveis = [];
        }

        if ($comps === []) {
            $comps = $this->componentesSinteticosPreview($pecasSel);
        }

        $colunas = [];
        $temSemana = false;
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            if ($this->componenteEhSemanaQuadro($c)) {
                $temSemana = true;
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '' || $cod === 'media_sem') {
                continue;
            }
            $colunas[] = [
                'codigo' => $cod,
                'nome' => (string) ($c['nome'] ?? $cod),
                'tipo' => ((string) ($c['source_type'] ?? '')) === 'calculado' ? 'calculado' : 'peca',
                'travada' => false,
            ];
        }
        if ($temSemana) {
            array_unshift($colunas, [
                'codigo' => '_semanal',
                'nome' => 'Prova semanal (S1–S8 · N e Q)',
                'tipo' => 'semana_grupo',
                'travada' => true,
            ]);
        }

        $tabelas = $this->partirPreviewTabelas($comps, $estado);
        $outTabelas = [];
        foreach ($tabelas as $tab) {
            $semanas = is_array($tab['semanas'] ?? null) ? $tab['semanas'] : [];
            $outras = is_array($tab['outras'] ?? null) ? $tab['outras'] : [];
            $codigosSemana = [];
            foreach ($semanas as $sc) {
                $codigosSemana[] = strtolower((string) ($sc['codigo'] ?? ''));
            }
            $linhas = [];
            foreach ($tab['materias'] as $matNome) {
                $linhas[] = [
                    'materia_nome' => $matNome,
                    'notas' => $this->notasFicticiasLinha($matNome, $comps, $codigosSemana, $estado),
                ];
            }
            $outTabelas[] = [
                'key' => (string) ($tab['key'] ?? 'u'),
                'titulo' => (string) ($tab['titulo'] ?? 'Matérias'),
                'subtitulo' => (string) ($tab['subtitulo'] ?? ''),
                'semanas' => $semanas,
                'outras' => $outras,
                'linhas' => $linhas,
            ];
        }

        return [
            'modo' => $temSemana ? 'quadro' : 'simples',
            'aviso' => 'Exemplo com dados fictícios — não são notas reais.',
            'tabelas' => $outTabelas,
            'colunas' => $colunas,
            'pecas_disponiveis' => $disponiveis,
        ];
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @return list<array<string,mixed>>
     */
    private function partirPreviewTabelas(array $comps, array $estado): array
    {
        $blocoA = [];
        $blocoB = [];
        $mediaSem = [];
        $comum = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            $col = [
                'codigo' => strtolower(trim((string) ($c['codigo'] ?? ''))),
                'nome' => (string) ($c['nome'] ?? ''),
                'layout_type' => strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? ''))),
                'source_type' => (string) ($c['source_type'] ?? ''),
            ];
            if ($col['codigo'] === '') {
                continue;
            }
            if ($g === 'quadro_a' || ($this->componenteEhSemanaQuadro($c) && preg_match('/^s[1357]$/', $col['codigo']))) {
                $blocoA[] = $col;
            } elseif ($g === 'quadro_b' || ($this->componenteEhSemanaQuadro($c) && preg_match('/^s[2468]$/', $col['codigo']))) {
                $blocoB[] = $col;
            } elseif ($col['codigo'] === 'media_sem' || $col['layout_type'] === 'media_sem') {
                $mediaSem[] = $col;
            } else {
                $comum[] = $col;
            }
        }
        $comum = array_merge($mediaSem, $comum);

        $materias = $this->materiasPreview($estado, ['Física', 'Matemática', 'Geografia', 'Biologia', 'Educação Física']);
        $metade = (int) ceil(count($materias) / 2);
        $materiasA = array_slice($materias, 0, $metade);
        $materiasB = array_slice($materias, $metade);
        if ($materiasB === []) {
            $materiasB = $materiasA;
        }

        if ($blocoA === [] && $blocoB === []) {
            return [[
                'key' => 'u',
                'titulo' => 'Matérias',
                'subtitulo' => 'Exemplo',
                'semanas' => [],
                'outras' => $comum,
                'materias' => $materias,
            ]];
        }

        $out = [];
        if ($blocoA !== []) {
            $out[] = [
                'key' => 'a',
                'titulo' => 'Matérias Bloco A',
                'subtitulo' => 'Prova semanal',
                'semanas' => $blocoA,
                'outras' => $comum,
                'materias' => $materiasA,
            ];
        }
        if ($blocoB !== []) {
            $out[] = [
                'key' => 'b',
                'titulo' => 'Matérias Bloco B',
                'subtitulo' => 'Prova semanal',
                'semanas' => $blocoB,
                'outras' => $comum,
                'materias' => $materiasB,
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed>|null $rascunho
     * @param array<string,mixed> $estado
     */
    private function rascunhoEhLayoutBoletim(?array $rascunho, array $estado): bool
    {
        return $this->ehBoletimComposto($estado);
    }

    /**
     * @param array<string,mixed>|null $rascunho
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    private function montarPreviewFicticioBoletim(?array $rascunho, array $estado): array
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        if ($comps === []) {
            $comps = $this->componentesSinteticosPreviewBoletim($estado);
        }
        $grupos = $this->gruposPreviewBoletim($comps);
        $notaMin = (float) ($estado['nota_minima_aprovacao'] ?? $rascunho['nota_minima_aprovacao'] ?? 7);
        $materias = $this->materiasPreview($estado, ['Língua Portuguesa', 'Matemática', 'História', 'Geografia', 'Ciências']);
        $linhas = [];
        foreach ($materias as $matNome) {
            $linhas[] = [
                'materia_nome' => $matNome,
                'notas' => $this->notasFicticiasLinhaBoletim($matNome, $comps, $notaMin, $estado),
            ];
        }
        $colunas = [];
        foreach ($grupos as $grp) {
            foreach ((array) ($grp['cols'] ?? []) as $col) {
                $colunas[] = [
                    'codigo' => (string) ($col['codigo'] ?? ''),
                    'nome' => (string) ($col['nome'] ?? $col['codigo'] ?? ''),
                    'tipo' => ((string) ($col['source_type'] ?? '')) === 'calculado' ? 'calculado' : 'peca',
                    'travada' => true,
                ];
            }
        }

        return [
            'modo' => 'boletim',
            'aviso' => 'Exemplo com dados fictícios — não são notas reais.',
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

    /**
     * @param array<string,mixed> $estado
     * @param list<string> $fallback
     * @return list<string>
     */
    private function materiasPreview(array $estado, array $fallback): array
    {
        $selecionadas = array_values(array_unique(array_filter(
            array_map('intval', (array) ($estado['materias_ids'] ?? [])),
            static fn ($id) => $id > 0
        )));
        $selecionadasSet = array_fill_keys($selecionadas, true);
        $nomes = [];

        try {
            foreach ($this->ferramentas->listarMaterias(500) as $m) {
                $id = (int) ($m['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                if ($selecionadas !== [] && !isset($selecionadasSet[$id])) {
                    continue;
                }
                $nome = trim((string) ($m['nome'] ?? ''));
                if ($nome !== '') {
                    $nomes[] = $nome;
                }
            }
        } catch (Throwable $e) {
            error_log('BoletimAssistenteWizard materias preview: ' . $e->getMessage());
        }

        $nomes = array_values(array_unique($nomes));
        return $nomes !== [] ? $nomes : $fallback;
    }

    /**
     * @param array<string,mixed> $estado
     */
    private function hashAlunoPreview(array $estado): int
    {
        $alunoId = (int) ($estado['aluno_preview_id'] ?? 0);
        if ($alunoId <= 0) {
            return 0;
        }
        try {
            foreach ($this->ferramentas->listarAlunos(500) as $aluno) {
                if ((int) ($aluno['id'] ?? 0) !== $alunoId) {
                    continue;
                }
                return abs(crc32(mb_strtolower((string) ($aluno['nome'] ?? '')) . '#' . $alunoId));
            }
        } catch (Throwable $e) {
            error_log('BoletimAssistenteWizard aluno preview: ' . $e->getMessage());
        }
        return abs(crc32((string) $alunoId));
    }

    /**
     * @param array<string,mixed> $estado
     * @return list<array<string,mixed>>
     */
    private function componentesSinteticosPreviewBoletim(array $estado): array
    {
        $fontes = $this->normalizarFontesBimestres($estado['fontes_bimestres'] ?? []);
        $faltas = $this->normalizarFontesBimestres($estado['fontes_faltas'] ?? []);
        $bims = [];
        foreach ([1, 2, 3, 4] as $b) {
            if ((int) ($fontes[$b] ?? 0) > 0 || (int) ($faltas[$b] ?? 0) > 0) {
                $bims[] = $b;
            }
        }
        if ($bims === []) {
            $bims = [1, 2, 3, 4];
        }
        $comps = [];
        $codigosMedia = [];
        $codigosFaltas = [];
        foreach ($bims as $bim) {
            $codM = 'b' . $bim . '_media';
            $codF = 'b' . $bim . '_faltas';
            $codigosMedia[] = $codM;
            $codigosFaltas[] = $codF;
            $comps[] = $this->componenteBoletim($codM, 'Média', 'evento_boletim', [
                'layout_group' => 'b' . $bim,
                'layout_type' => 'media',
            ]);
            $comps[] = $this->componenteBoletim($codF, 'Faltas', 'faltas_evento', [
                'layout_group' => 'b' . $bim,
                'layout_type' => 'faltas',
            ]);
        }
        $n = count($codigosMedia);
        $comps[] = $this->componenteBoletim('media_final', 'Média', 'calculado', [
            'expressao' => $n === 1 ? $codigosMedia[0] : '(' . implode(' + ', $codigosMedia) . ') / ' . $n,
            'layout_group' => 'final',
            'layout_type' => 'media',
        ]);
        $comps[] = $this->componenteBoletim('rec_final', 'Rec.', 'nenhuma', [
            'layout_group' => 'final',
            'layout_type' => 'rec',
        ]);
        $comps[] = $this->componenteBoletim('faltas_final', 'Faltas', 'calculado', [
            'expressao' => implode(' + ', $codigosFaltas),
            'layout_group' => 'final',
            'layout_type' => 'faltas',
        ]);
        $comps[] = $this->componenteBoletim('resultado', 'Resultado', 'nenhuma', [
            'layout_group' => 'final',
            'layout_type' => 'resultado',
        ]);

        return $comps;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @return list<array{key:string,label:string,cols:list<array<string,mixed>>}>
     */
    private function gruposPreviewBoletim(array $comps): array
    {
        $ordem = ['b1', 'b2', 'b3', 'b4', 'final'];
        $labels = [
            'b1' => '1º BIMESTRE',
            'b2' => '2º BIMESTRE',
            'b3' => '3º BIMESTRE',
            'b4' => '4º BIMESTRE',
            'final' => 'FINAL',
        ];
        $subs = [
            'media' => 'Média',
            'faltas' => 'Faltas',
            'rec' => 'Rec.',
            'resultado' => 'Resultado',
        ];
        $porGrupo = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $g = strtolower(trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? '')));
            if (!isset($labels[$g])) {
                continue;
            }
            $lt = strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? '')));
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $porGrupo[$g][] = [
                'codigo' => $cod,
                'nome' => $subs[$lt] ?? (string) ($c['nome'] ?? $cod),
                'layout_type' => $lt !== '' ? $lt : 'media',
                'source_type' => (string) ($c['source_type'] ?? ''),
            ];
        }
        $out = [];
        foreach ($ordem as $gk) {
            if (empty($porGrupo[$gk])) {
                continue;
            }
            $out[] = [
                'key' => $gk,
                'label' => $labels[$gk],
                'cols' => $porGrupo[$gk],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @return array<string,mixed>
     */
    private function notasFicticiasLinhaBoletim(string $materia, array $comps, float $notaMin, array $estado): array
    {
        $hash = abs(crc32(mb_strtolower($materia))) + $this->hashAlunoPreview($estado);
        $roundMode = ((string) ($estado['round_mode'] ?? 'none')) === 'half' ? 'half' : 'none';
        $notas = [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $src = (string) ($c['source_type'] ?? '');
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $lt = strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? '')));
            if ($src === 'calculado') {
                continue;
            }
            if ($lt === 'resultado') {
                continue;
            }
            if ($lt === 'rec') {
                $notas[$cod] = ($hash % 3) === 0 ? $this->roundPreviewFicticio(5 + (($hash + 11) % 40) / 10, $roundMode) : '—';
                continue;
            }
            if ($lt === 'faltas' || $src === 'faltas_evento') {
                $notas[$cod] = (int) (($hash + strlen($cod) * 5) % 9);
                continue;
            }
            $notas[$cod] = $this->roundPreviewFicticio(5 + (($hash + strlen($cod) * 7) % 51) / 10, $roundMode);
        }
        foreach ($comps as $c) {
            if (!is_array($c) || (string) ($c['source_type'] ?? '') !== 'calculado') {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $exp = trim((string) ($cfg['expressao'] ?? ''));
            $val = $this->avaliarPreviewExpr($exp, $notas);
            $lt = strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? '')));
            if ($val === null) {
                $notas[$cod] = '—';
            } elseif ($lt === 'faltas') {
                $notas[$cod] = (int) round($val);
            } else {
                $notas[$cod] = $this->roundPreviewFicticio($val, $roundMode);
            }
        }
        $mediaFinal = is_numeric($notas['media_final'] ?? null) ? (float) $notas['media_final'] : null;
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $lt = strtolower(trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? '')));
            if ($lt !== 'resultado') {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            if ($cod === '') {
                continue;
            }
            $notas[$cod] = ($mediaFinal !== null && $mediaFinal >= $notaMin) ? 'Aprovado' : 'Reprovado';
        }

        return $notas;
    }

    /**
     * Quadro de exemplo (S1–S8) quando o rascunho ainda não tem componentes.
     *
     * @param list<string> $pecas
     * @return list<array<string,mixed>>
     */
    private function componentesSinteticosPreview(array $pecas): array
    {
        $comps = [];
        foreach ([1, 3, 5, 7] as $s) {
            $comps[] = [
                'codigo' => 's' . $s,
                'nome' => 'S' . $s,
                'source_type' => 'provas_sistema',
                'config' => ['layout_group' => 'quadro_a', 'layout_type' => 'semana_nq'],
            ];
        }
        foreach ([2, 4, 6, 8] as $s) {
            $comps[] = [
                'codigo' => 's' . $s,
                'nome' => 'S' . $s,
                'source_type' => 'provas_sistema',
                'config' => ['layout_group' => 'quadro_b', 'layout_type' => 'semana_nq'],
            ];
        }
        $comps[] = [
            'codigo' => 'media_sem',
            'nome' => 'Média Sem',
            'source_type' => 'calculado',
            'config' => [
                'layout_group' => 'quadro_comum',
                'layout_type' => 'media_sem',
                'agregar_nq' => ['s1', 's3', 's5', 's7', 's2', 's4', 's6', 's8'],
            ],
        ];
        $mapa = [
            'bimestral' => ['prova_bim', 'Prova Bim'],
            'enac' => ['enac', 'ENAC'],
            'participacao' => ['part', 'Part'],
            'trabalho' => ['trab', 'Trab'],
            'recuperacao' => ['rec', 'Rec'],
            'jornada' => ['jornada', 'Jornada'],
        ];
        if ($pecas === []) {
            $pecas = ['bimestral'];
        }
        $partes = ['media_sem'];
        foreach ($pecas as $p) {
            if ($p === 'semanal' || !isset($mapa[$p])) {
                continue;
            }
            [$cod, $nome] = $mapa[$p];
            $tipo = $p === 'recuperacao' ? 'rec' : 'media';
            $comps[] = [
                'codigo' => $cod,
                'nome' => $nome,
                'source_type' => 'provas_sistema',
                'config' => ['layout_group' => 'quadro_comum', 'layout_type' => $tipo],
            ];
            if ($p !== 'recuperacao') {
                $partes[] = $cod;
            }
        }
        $n = count($partes);
        $exp = $n === 1 ? $partes[0] : '(' . implode(' + ', $partes) . ') / ' . $n;
        $comps[] = [
            'codigo' => 'media_bim',
            'nome' => 'Média Bim',
            'source_type' => 'calculado',
            'config' => [
                'expressao' => $exp,
                'layout_group' => 'quadro_comum',
                'layout_type' => 'media',
            ],
        ];
        if (in_array('recuperacao', $pecas, true)) {
            $comps[] = [
                'codigo' => 'media_final',
                'nome' => 'Média Bim Final',
                'source_type' => 'calculado',
                'config' => [
                    'expressao' => 'max(media_bim, rec)',
                    'layout_group' => 'quadro_comum',
                    'layout_type' => 'resultado',
                ],
            ];
        }
        return $comps;
    }

    /**
     * @param list<array<string,mixed>> $comps
     * @param list<string> $codigosSemanaBloco
     * @return array<string,mixed>
     */
    private function notasFicticiasLinha(string $materia, array $comps, array $codigosSemanaBloco, array $estado): array
    {
        $hash = abs(crc32(mb_strtolower($materia))) + $this->hashAlunoPreview($estado);
        $roundMode = ((string) ($estado['round_mode'] ?? 'none')) === 'half' ? 'half' : 'none';
        $notas = [];
        $sumN = 0;
        $sumQ = 0;
        foreach ($comps as $c) {
            if (!is_array($c) || !$this->componenteEhSemanaQuadro($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $s = (int) substr($cod, 1);
            $naBloco = in_array($cod, $codigosSemanaBloco, true);
            $q = $naBloco ? 10 : 0;
            $n = $naBloco ? (5 + (($hash + $s * 3) % 6)) : 0;
            $notas[$cod . '__n'] = $n;
            $notas[$cod . '__q'] = $q;
            if ($naBloco) {
                $sumN += $n;
                $sumQ += $q;
            }
        }

        foreach ($comps as $c) {
            if (!is_array($c) || $this->componenteEhSemanaQuadro($c)) {
                continue;
            }
            $cod = strtolower(trim((string) ($c['codigo'] ?? '')));
            $src = (string) ($c['source_type'] ?? '');
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            if ($src === 'calculado') {
                $agregar = is_array($cfg['agregar_nq'] ?? null) ? $cfg['agregar_nq'] : [];
                if ($agregar !== [] || $cod === 'media_sem') {
                    $notas[$cod] = $sumQ > 0 ? $this->roundPreviewFicticio(10 * $sumN / $sumQ, $roundMode) : 0.0;
                    continue;
                }
                $exp = trim((string) ($cfg['expressao'] ?? ''));
                $val = $this->avaliarPreviewExpr($exp, $notas);
                $notas[$cod] = $val !== null ? $this->roundPreviewFicticio($val, $roundMode) : '—';
                continue;
            }
            if ($cod === 'rec' && ($hash % 3) === 0) {
                $notas[$cod] = '—';
                continue;
            }
            $notas[$cod] = $this->roundPreviewFicticio(5 + (($hash + strlen($cod) * 7) % 51) / 10, $roundMode);
        }

        return $notas;
    }

    private function roundPreviewFicticio(float $valor, string $roundMode): float
    {
        if ($roundMode !== 'half') {
            return round($valor, 2);
        }

        $base = floor($valor);
        $dec = $valor - $base;
        if ($dec < 0.25) {
            return (float) $base;
        }
        if ($dec < 0.75) {
            return $base + 0.5;
        }

        return $base + 1.0;
    }

    /**
     * @param array<string,mixed> $vals
     */
    private function avaliarPreviewExpr(string $expr, array $vals): ?float
    {
        $expr = strtolower(trim($expr));
        if ($expr === '') {
            return null;
        }
        $nums = [];
        foreach ($vals as $cod => $n) {
            if (!is_numeric($n) || str_contains((string) $cod, '__')) {
                continue;
            }
            $nums[strtolower((string) $cod)] = (float) $n;
        }
        uksort($nums, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
        foreach ($nums as $cod => $n) {
            $expr = preg_replace('/\b' . preg_quote((string) $cod, '/') . '\b/', (string) $n, $expr) ?? $expr;
        }
        $guard = 0;
        while ($guard++ < 20 && preg_match('/\b(max|min)\s*\(/i', $expr)) {
            if (!preg_match('/\b(max|min)\s*\(\s*([0-9.+\-*\/()\s]+)\s*,\s*([0-9.+\-*\/()\s]+)\s*\)/i', $expr, $m)) {
                break;
            }
            $l = $this->avaliarAritmeticaPreview($m[2]);
            $r = $this->avaliarAritmeticaPreview($m[3]);
            if ($l === null || $r === null) {
                return null;
            }
            $v = strtolower($m[1]) === 'max' ? max($l, $r) : min($l, $r);
            $expr = str_replace($m[0], (string) $v, $expr);
        }
        return $this->avaliarAritmeticaPreview($expr);
    }

    private function avaliarAritmeticaPreview(string $expr): ?float
    {
        $expr = preg_replace('/\s+/', '', $expr) ?? '';
        if ($expr === '' || !preg_match('/^[0-9.+\-*\/()]+$/', $expr)) {
            return null;
        }
        if (preg_match('/[+\-*\/]{2,}/', $expr) || str_contains($expr, '()')) {
            return null;
        }
        try {
            $resultado = @eval('return (float) (' . $expr . ');');
            if (!is_numeric($resultado) || !is_finite((float) $resultado)) {
                return null;
            }
            return (float) $resultado;
        } catch (Throwable $e) {
            return null;
        }
    }
}
