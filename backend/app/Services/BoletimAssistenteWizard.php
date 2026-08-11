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
        'periodo',
        'formula',
        'publico',
        'revisar',
    ];

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
        foreach ($this->ferramentas->listarRegras(80) as $r) {
            $regras[] = [
                'id' => (int) ($r['id'] ?? 0),
                'nome' => (string) ($r['nome'] ?? ''),
                'bimestre' => isset($r['bimestre']) ? (int) $r['bimestre'] : null,
                'ano_letivo' => isset($r['ano_letivo']) ? (int) $r['ano_letivo'] : null,
            ];
        }

        $series = [];
        // Séries vêm indiretamente pelas turmas do catálogo compacto
        $porSerie = [];
        foreach ($this->ferramentas->listarTurmas(300) as $t) {
            $sid = (int) ($t['serie_id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            if (!isset($porSerie[$sid])) {
                $porSerie[$sid] = [
                    'id' => $sid,
                    'nome' => trim((string) ($t['serie_nome'] ?? ('Série #' . $sid))),
                    'curso_nome' => trim((string) ($t['curso_nome'] ?? '')),
                ];
            }
        }
        $series = array_values($porSerie);

        $turmas = [];
        foreach ($this->ferramentas->listarTurmas(300) as $t) {
            $turmas[] = [
                'id' => (int) ($t['id'] ?? 0),
                'nome' => trim((string) ($t['nome'] ?? '')),
                'serie_id' => isset($t['serie_id']) ? (int) $t['serie_id'] : null,
                'serie_nome' => isset($t['serie_nome']) ? trim((string) $t['serie_nome']) : null,
                'ano_letivo' => isset($t['ano_letivo']) ? (int) $t['ano_letivo'] : null,
            ];
        }

        $materias = [];
        foreach ($this->ferramentas->listarMaterias(200) as $m) {
            $materias[] = [
                'id' => (int) ($m['id'] ?? 0),
                'nome' => (string) ($m['nome'] ?? ''),
            ];
        }

        return [
            'passos' => self::PASSOS,
            'modelos' => $this->modelosProntos(),
            'formulas' => $this->formulasPresetMeta(),
            'regras' => $regras,
            'series' => $series,
            'turmas' => $turmas,
            'materias' => $materias,
            'jornadas' => $this->ferramentas->listarJornadas(120),
            'tipos_avaliacao' => $this->ferramentas->listarTiposAvaliacao(),
            'pecas' => $this->pecasMeta(),
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
            'pecas' => ['bimestral', 'jornada'],
            'pecas_opcoes' => $this->pecasOpcoesPadrao(['bimestral', 'jornada']),
            'formula_preset' => 'media_simples',
            'formula_custom' => '',
            'data_inicio' => '',
            'data_fim' => '',
            'jornada_modo' => 'todas',
            'jornada_ids' => [],
            'series_ids' => [],
            'turmas_ids' => [],
            'materias_ids' => [],
            'rascunho_preservado' => null,
        ];

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
                $estado['pecas_opcoes'] = $this->inferirPecasOpcoesDeComponentes($comps, $estado['pecas']);
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
     * @param array<string,mixed> $estado
     * @return array{ok:bool,estado:array,rascunho:?array,resumo:string,erros:list<string>,formulas_disponiveis:list<array>}
     */
    public function montar(array $estado): array
    {
        $estado = $this->normalizarEstado($estado);
        $erros = [];

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
            $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
            $resumo = $this->montarResumoHumano($validado['rascunho']);
            return [
                'ok' => $validado['rascunho']['componentes'] !== [],
                'estado' => $estado,
                'rascunho' => $validado['rascunho'],
                'resumo' => $resumo,
                'erros' => $validado['erros'],
                'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
            ];
        }

        // Edição do formulário já aberto / ajuste via chat: preserva componentes técnicos.
        if (in_array($estado['origem'], ['formulario', 'chat'], true)
            && !empty($estado['rascunho_preservado'])
            && is_array($estado['rascunho_preservado'])
        ) {
            $rascunho = $estado['rascunho_preservado'];
            $rascunho['nome'] = trim((string) ($estado['nome'] ?? $rascunho['nome'] ?? ''));
            $rascunho['ano_letivo'] = (int) ($estado['ano_letivo'] ?? $rascunho['ano_letivo'] ?? date('Y'));
            $rascunho['bimestre'] = (int) ($estado['bimestre'] ?? $rascunho['bimestre'] ?? 0) ?: null;
            $rascunho['exibir_em'] = (string) ($estado['exibir_em'] ?? $rascunho['exibir_em'] ?? 'notas');
            $rascunho['round_mode'] = (string) ($estado['round_mode'] ?? $rascunho['round_mode'] ?? 'half');
            $rascunho['nota_minima_aprovacao'] = $estado['nota_minima_aprovacao'] ?? $rascunho['nota_minima_aprovacao'] ?? 7;
            $rascunho['series_ids'] = $estado['series_ids'];
            $rascunho['turmas_ids'] = $estado['turmas_ids'];
            $rascunho['materias_ids'] = $estado['materias_ids'] !== []
                ? $estado['materias_ids']
                : ($rascunho['materias_ids'] ?? []);
            if (!empty($estado['data_inicio'])) {
                $rascunho['default_data_inicio'] = (string) $estado['data_inicio'];
            }
            if (!empty($estado['data_fim'])) {
                $rascunho['default_data_fim'] = (string) $estado['data_fim'];
            }
            $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
            return [
                'ok' => $validado['rascunho']['componentes'] !== [],
                'estado' => $estado,
                'rascunho' => $validado['rascunho'],
                'resumo' => $this->montarResumoHumano($validado['rascunho']),
                'erros' => $validado['erros'],
                'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
            ];
        }

        if ($estado['origem'] === 'modelo' && $estado['modelo_key'] !== '') {
            $modelo = $this->encontrarModelo($estado['modelo_key']);
            if ($modelo !== null) {
                $estado['pecas'] = $modelo['pecas'];
                $estado['pecas_opcoes'] = $this->mesclarPecasOpcoes(
                    $this->pecasOpcoesPadrao($modelo['pecas']),
                    is_array($estado['pecas_opcoes'] ?? null) ? $estado['pecas_opcoes'] : []
                );
                if ($estado['formula_preset'] === '' || $estado['formula_preset'] === 'media_simples') {
                    $estado['formula_preset'] = $modelo['formula_preset'];
                }
            }
        }

        if ($estado['pecas'] === []) {
            $erros[] = 'Escolha ao menos uma peça da média (ex.: Bimestral, Jornada, ENAC).';
        }

        $rascunho = $this->rascunhoAPartirDePecas($estado);
        $validado = $this->ferramentas->validarEEnriquecerRascunho($rascunho);
        $erros = array_merge($erros, $validado['erros']);

        return [
            'ok' => $validado['rascunho']['componentes'] !== [] && $erros === [],
            'estado' => $estado,
            'rascunho' => $validado['rascunho'],
            'resumo' => $this->montarResumoHumano($validado['rascunho']),
            'erros' => array_values(array_unique($erros)),
            'formulas_disponiveis' => $this->formulasParaPecas($estado['pecas']),
        ];
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
                    'ultima' => 'última nota',
                    default => 'média',
                };
                $extras = [];
                if (!empty($c['materia_unica'])) {
                    $extras[] = 'junta matérias iguais (professores)';
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
                        . (!empty($c['config']['jornada_ids']) ? ' · ' . count((array) $c['config']['jornada_ids']) . ' selecionada(s)' : ' · todas no período')
                        . $extraTxt,
                    'calculado' => 'calculado: ' . trim((string) ($c['config']['expressao'] ?? '')),
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
                'descricao' => 'Média parcial, depois média final com ENAC.',
                'pecas' => ['bimestral', 'jornada', 'enac'],
                'formula_preset' => 'parcial_depois_final',
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
            ],
        ];
    }

    /** @return list<array{key:string,label:string,hint:string}> */
    public function pecasMeta(): array
    {
        return [
            ['key' => 'semanal', 'label' => 'Prova semanal', 'hint' => 'Notas de provas do tipo/filtro semanal'],
            ['key' => 'bimestral', 'label' => 'Prova bimestral', 'hint' => 'Notas de provas bimestrais'],
            ['key' => 'jornada', 'label' => 'Jornada do aluno', 'hint' => 'Conclusão de jornadas (não acerto de questões)'],
            ['key' => 'enac', 'label' => 'ENAC', 'hint' => 'Avaliação ENAC / filtro enac'],
        ];
    }

    /** @return list<array{key:string,titulo:string,descricao:string}> */
    public function formulasPresetMeta(): array
    {
        return [
            [
                'key' => 'media_simples',
                'titulo' => 'Média simples de tudo',
                'descricao' => 'Soma as peças e divide pela quantidade.',
            ],
            [
                'key' => 'parcial_depois_final',
                'titulo' => 'Média parcial → depois final com ENAC',
                'descricao' => 'Primeiro média das peças (sem ENAC); depois média com ENAC.',
            ],
            [
                'key' => 'enac_so_melhora',
                'titulo' => 'ENAC só melhora',
                'descricao' => 'Se o ENAC for menor que a média, mantém a média.',
            ],
        ];
    }

    /**
     * @param list<string> $pecas
     * @return list<array{key:string,titulo:string,descricao:string}>
     */
    public function formulasParaPecas(array $pecas): array
    {
        $pecas = array_values(array_unique(array_map('strval', $pecas)));
        $temEnac = in_array('enac', $pecas, true);
        $outras = array_values(array_filter($pecas, static fn ($p) => $p !== 'enac'));
        $out = [];
        foreach ($this->formulasPresetMeta() as $f) {
            if ($f['key'] === 'parcial_depois_final' && (!$temEnac || count($outras) < 1)) {
                continue;
            }
            if ($f['key'] === 'enac_so_melhora' && (!$temEnac || count($outras) < 1)) {
                continue;
            }
            $out[] = $f;
        }
        return $out;
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
        $pecasOk = ['semanal', 'bimestral', 'jornada', 'enac'];
        $pecas = [];
        foreach ((array) ($merged['pecas'] ?? []) as $p) {
            $p = strtolower(trim((string) $p));
            if (in_array($p, $pecasOk, true)) {
                $pecas[] = $p;
            }
        }
        $merged['pecas'] = array_values(array_unique($pecas));
        $merged['pecas_opcoes'] = $this->mesclarPecasOpcoes(
            $this->pecasOpcoesPadrao($merged['pecas']),
            is_array($merged['pecas_opcoes'] ?? null) ? $merged['pecas_opcoes'] : []
        );
        $merged['formula_preset'] = trim((string) ($merged['formula_preset'] ?? 'media_simples')) ?: 'media_simples';
        $merged['formula_custom'] = trim((string) ($merged['formula_custom'] ?? ''));
        $merged['data_inicio'] = $this->normalizarDataCampo($merged['data_inicio'] ?? '');
        $merged['data_fim'] = $this->normalizarDataCampo($merged['data_fim'] ?? '');
        $merged['jornada_modo'] = in_array((string) ($merged['jornada_modo'] ?? ''), ['todas', 'selecionadas'], true)
            ? (string) $merged['jornada_modo']
            : 'todas';
        $merged['jornada_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($merged['jornada_ids'] ?? [])))));
        $merged['series_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($merged['series_ids'] ?? [])))));
        $merged['turmas_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($merged['turmas_ids'] ?? [])))));
        $merged['materias_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($merged['materias_ids'] ?? [])))));
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
     * @param list<string> $pecas
     * @return array<string,array{calc_type:string,materia_unica:int,usar_percentual:int}>
     */
    public function pecasOpcoesPadrao(array $pecas): array
    {
        $out = [];
        foreach ($pecas as $p) {
            $p = strtolower(trim((string) $p));
            if ($p === '') {
                continue;
            }
            $out[$p] = [
                'calc_type' => 'media',
                'materia_unica' => 0,
                'usar_percentual' => 1,
                'tipo_avaliacao_id' => 0,
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
                    'calc_type' => 'media',
                    'materia_unica' => 0,
                    'usar_percentual' => 1,
                    'tipo_avaliacao_id' => 0,
                ];
            }
            $calc = strtolower(trim((string) ($opts['calc_type'] ?? $base[$peca]['calc_type'])));
            if (!in_array($calc, ['media', 'soma', 'maior', 'ultima'], true)) {
                $calc = 'media';
            }
            $base[$peca]['calc_type'] = $calc;
            $base[$peca]['materia_unica'] = !empty($opts['materia_unica']) ? 1 : 0;
            if (array_key_exists('usar_percentual', $opts)) {
                $base[$peca]['usar_percentual'] = !empty($opts['usar_percentual']) ? 1 : 0;
            }
            if (array_key_exists('tipo_avaliacao_id', $opts)) {
                $base[$peca]['tipo_avaliacao_id'] = max(0, (int) $opts['tipo_avaliacao_id']);
            }
        }
        return $base;
    }

    /**
     * @param list<array> $comps
     * @param list<string> $pecas
     * @return array<string,array{calc_type:string,materia_unica:int,usar_percentual:int}>
     */
    private function inferirPecasOpcoesDeComponentes(array $comps, array $pecas): array
    {
        $out = $this->pecasOpcoesPadrao($pecas);
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $src = (string) ($c['source_type'] ?? '');
            if ($src === 'calculado') {
                continue;
            }
            $cod = mb_strtolower(trim((string) ($c['codigo'] ?? '')));
            $nome = mb_strtolower(trim((string) ($c['nome'] ?? '')));
            $filtro = mb_strtolower(trim((string) ($c['filtro_titulo'] ?? '')));
            $blob = $cod . ' ' . $nome . ' ' . $filtro;
            $key = null;
            if ($src === 'jornadas' || str_contains($blob, 'jornada')) {
                $key = 'jornada';
            } elseif (str_contains($blob, 'enac')) {
                $key = 'enac';
            } elseif (str_contains($blob, 'semanal')) {
                $key = 'semanal';
            } elseif (str_contains($blob, 'bimestral') || str_contains($blob, 'bimestr')) {
                $key = 'bimestral';
            }
            if ($key === null || !isset($out[$key])) {
                continue;
            }
            $calc = strtolower(trim((string) ($c['calc_type'] ?? 'media')));
            if (in_array($calc, ['media', 'soma', 'maior', 'ultima'], true)) {
                $out[$key]['calc_type'] = $calc;
            }
            $out[$key]['materia_unica'] = !empty($c['materia_unica']) ? 1 : 0;
            $out[$key]['usar_percentual'] = !empty($c['usar_percentual']) ? 1 : 0;
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
            $src = (string) ($c['source_type'] ?? '');
            $cod = mb_strtolower(trim((string) ($c['codigo'] ?? '')));
            $nome = mb_strtolower(trim((string) ($c['nome'] ?? '')));
            $filtro = mb_strtolower(trim((string) ($c['filtro_titulo'] ?? '')));
            $blob = $cod . ' ' . $nome . ' ' . $filtro;
            if ($src === 'jornadas' || str_contains($blob, 'jornada')) {
                $pecas[] = 'jornada';
            } elseif (str_contains($blob, 'enac')) {
                $pecas[] = 'enac';
            } elseif (str_contains($blob, 'semanal')) {
                $pecas[] = 'semanal';
            } elseif (str_contains($blob, 'bimestral') || str_contains($blob, 'bimestr')) {
                $pecas[] = 'bimestral';
            }
        }
        return array_values(array_unique($pecas));
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
            'componentes' => is_array($regra['componentes'] ?? null) ? $regra['componentes'] : [],
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
            $componentes[] = $def;
            $mapaCodigo[$peca] = $def['codigo'];
        }

        $preset = (string) $estado['formula_preset'];
        $custom = trim((string) $estado['formula_custom']);
        $codigosBase = [];
        foreach ($pecas as $p) {
            if ($p === 'enac') {
                continue;
            }
            if (isset($mapaCodigo[$p])) {
                $codigosBase[] = $mapaCodigo[$p];
            }
        }
        $codEnac = $mapaCodigo['enac'] ?? null;

        if ($custom !== '') {
            // Fórmula livre: um bloco calculado final
            $componentes[] = $this->blocoCalculado('media_final', 'Média final', $custom);
            $formulaFinal = 'media_final';
        } elseif ($preset === 'parcial_depois_final' && $codEnac && count($codigosBase) >= 1) {
            $expParcial = $this->expressaoMedia($codigosBase);
            $componentes[] = $this->blocoCalculado('media_parcial', 'Média parcial', $expParcial);
            $expFinal = '(media_parcial + ' . $codEnac . ') / 2';
            $componentes[] = $this->blocoCalculado('media_final', 'Média final', $expFinal);
            $formulaFinal = 'media_final';
        } elseif ($preset === 'enac_so_melhora' && $codEnac && count($codigosBase) >= 1) {
            $expBase = $this->expressaoMedia($codigosBase);
            $componentes[] = $this->blocoCalculado('media_base', 'Média base', $expBase);
            $expFinal = 'max(media_base, (media_base + ' . $codEnac . ') / 2)';
            $componentes[] = $this->blocoCalculado('media_final', 'Média final', $expFinal);
            $formulaFinal = 'media_final';
        } else {
            // media_simples
            $todos = [];
            foreach ($pecas as $p) {
                if (isset($mapaCodigo[$p])) {
                    $todos[] = $mapaCodigo[$p];
                }
            }
            if (count($todos) >= 2) {
                $exp = $this->expressaoMedia($todos);
                $componentes[] = $this->blocoCalculado('media_final', 'Média final', $exp);
                $formulaFinal = 'media_final';
            } elseif (count($todos) === 1) {
                $formulaFinal = $todos[0];
            } else {
                $formulaFinal = '';
            }
        }

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
     * @param array{calc_type?:string,materia_unica?:int|bool,usar_percentual?:int|bool,tipo_avaliacao_id?:int} $opts
     * @param array<string,mixed> $estado
     * @return array<string,mixed>|null
     */
    private function definirComponentePeca(string $peca, array $opts = [], array $estado = []): ?array
    {
        $calc = strtolower(trim((string) ($opts['calc_type'] ?? 'media')));
        if (!in_array($calc, ['media', 'soma', 'maior', 'ultima'], true)) {
            $calc = 'media';
        }
        $materiaUnica = !empty($opts['materia_unica']) ? 1 : 0;
        $usarPerc = array_key_exists('usar_percentual', $opts)
            ? (!empty($opts['usar_percentual']) ? 1 : 0)
            : 1;

        $base = match ($peca) {
            'bimestral' => [
                'codigo' => 'bimestral',
                'nome' => 'Prova bimestral',
                'source_type' => 'provas_sistema',
                'filtro_titulo' => 'bimestral',
                'tipo_sugerido' => 'Bimestral',
            ],
            'jornada' => [
                'codigo' => 'jornada',
                'nome' => 'Jornada do aluno',
                'source_type' => 'jornadas',
                'filtro_titulo' => '',
                'tipo_sugerido' => '',
            ],
            'enac' => [
                'codigo' => 'enac',
                'nome' => 'ENAC',
                'source_type' => 'provas_sistema',
                'filtro_titulo' => 'enac',
                'tipo_sugerido' => 'ENAC',
            ],
            'semanal' => [
                'codigo' => 'semanal',
                'nome' => 'Prova semanal',
                'source_type' => 'provas_sistema',
                'filtro_titulo' => 'semanal',
                'tipo_sugerido' => 'Semanal',
            ],
            default => null,
        };
        if ($base === null) {
            return null;
        }

        $dataIni = (string) ($estado['data_inicio'] ?? '');
        $dataFim = (string) ($estado['data_fim'] ?? '');
        $config = ['formula_mode' => 'single', 'expressao' => ''];
        $blocosIds = [];
        $tipoId = max(0, (int) ($opts['tipo_avaliacao_id'] ?? 0));
        $tipoNome = '';
        $filtroTitulo = $base['filtro_titulo'];

        if ($base['source_type'] === 'jornadas') {
            $modo = (string) ($estado['jornada_modo'] ?? 'todas');
            $ids = $modo === 'selecionadas'
                ? array_values(array_filter(array_map('intval', (array) ($estado['jornada_ids'] ?? []))))
                : [];
            $config['jornada_ids'] = $ids;
            $config['data_ini'] = $dataIni;
            $config['data_fim'] = $dataFim;
        } elseif ($base['source_type'] === 'provas_sistema') {
            $tipoRef = $tipoId > 0 ? $tipoId : ($base['tipo_sugerido'] !== '' ? $base['tipo_sugerido'] : $filtroTitulo);
            $resolvido = $this->ferramentas->resolverBlocosPorTipo(
                $tipoRef,
                $dataIni !== '' ? $dataIni : null,
                $dataFim !== '' ? $dataFim : null
            );
            if ($resolvido['tipo'] !== null) {
                $tipoId = (int) $resolvido['tipo']['id'];
                $tipoNome = (string) $resolvido['tipo']['nome'];
                $config['tipo_avaliacao_id'] = $tipoId;
                $config['tipo_avaliacao_nome'] = $tipoNome;
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
}
