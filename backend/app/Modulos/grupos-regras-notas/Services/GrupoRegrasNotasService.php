<?php
/**
 * Regras de negócio do Grupo de Regras de Notas.
 */

require_once __DIR__ . '/../Models/GrupoRegrasNotas.php';

class GrupoRegrasNotasService
{
    /** @var GrupoRegrasNotas */
    private $model;

    public function __construct()
    {
        $this->model = new GrupoRegrasNotas();
    }

    public function model(): GrupoRegrasNotas
    {
        return $this->model;
    }

    public function moduloAtivo(): bool
    {
        if (!class_exists('LayoutHelper', false)) {
            require_once dirname(__DIR__, 3) . '/Core/LayoutHelper.php';
        }
        return LayoutHelper::isModuleEnabled('grupos_regras_notas');
    }

    /**
     * @return array<string,mixed>|null
     */
    public function carregarCompleto(int $id): ?array
    {
        $grupo = $this->model->findById($id);
        if ($grupo === null) {
            return null;
        }
        $marcas = $this->model->listarMarcas($id);
        $tipos = [];
        foreach ($this->model->listarTipos($id) as $tipo) {
            $tipoId = (int) ($tipo['id'] ?? 0);
            $tipo['marcas_ids'] = $this->model->marcasIdsDoTipo($tipoId);
            $tipo['materias_ids'] = $this->model->materiasIdsDoTipo($tipoId);
            $tipo['layout_group'] = $this->layoutGroupDoCodigo((string) ($tipo['codigo'] ?? ''));
            $tipo['layout_group_label'] = (string) ($tipo['nome'] ?? '');
            $tipos[] = $tipo;
        }
        $grupo['marcas'] = $marcas;
        $grupo['tipos'] = $tipos;
        return $grupo;
    }

    /**
     * Payload para evento de notas / evento de prova.
     *
     * @return array<string,mixed>|null
     */
    public function payloadPublico(int $id): ?array
    {
        $grupo = $this->carregarCompleto($id);
        if ($grupo === null) {
            return null;
        }
        $marcas = array_map(static function (array $m): array {
            return [
                'id' => (int) ($m['id'] ?? 0),
                'codigo' => (string) ($m['codigo'] ?? ''),
                'nome' => (string) ($m['nome'] ?? ''),
                'numero' => (int) ($m['numero'] ?? 0),
                'papel' => GrupoRegrasNotas::papelDaColuna($m),
                'tipo_nota_id' => (int) ($m['tipo_nota_id'] ?? 0) ?: null,
                'formula_json' => $m['formula_json'] ?? null,
                'vai_para_boletim' => !empty($m['vai_para_boletim']),
            ];
        }, $grupo['marcas'] ?? []);
        $tipos = array_map(static function (array $t): array {
            return [
                'id' => (int) ($t['id'] ?? 0),
                'codigo' => (string) ($t['codigo'] ?? ''),
                'nome' => (string) ($t['nome'] ?? ''),
                'layout_group' => (string) ($t['layout_group'] ?? ''),
                'layout_group_label' => (string) ($t['layout_group_label'] ?? ''),
                'marcas_ids' => array_map('intval', $t['marcas_ids'] ?? []),
                'colunas_ids' => array_map('intval', $t['marcas_ids'] ?? []),
                'materias_ids' => array_map('intval', $t['materias_ids'] ?? []),
            ];
        }, $grupo['tipos'] ?? []);
        return [
            'id' => (int) $grupo['id'],
            'nome' => (string) $grupo['nome'],
            'descricao' => $grupo['descricao'] ?? null,
            'ativo' => (int) ($grupo['ativo'] ?? 1) === 1,
            'escala_max' => (float) ($grupo['escala_max'] ?? 10) ?: 10,
            'criterio_calculo' => (string) ($grupo['criterio_calculo'] ?? 'ultima'),
            'coluna_consolidada_codigo' => (string) ($grupo['coluna_consolidada_codigo'] ?? 'media_sem'),
            'coluna_consolidada_nome' => (string) ($grupo['coluna_consolidada_nome'] ?? 'Média'),
            'modo' => $this->modoDoGrupo($grupo),
            'marcas' => $marcas,
            'colunas' => $marcas,
            'tipos' => $tipos,
            'blocos' => $tipos,
            'componentes_sugeridos' => $this->montarComponentesQuadro($grupo),
        ];
    }

    /**
     * @param array<string,mixed> $grupo
     */
    public function modoDoGrupo(array $grupo): string
    {
        $modo = strtolower(trim((string) ($grupo['modo'] ?? '')));
        if ($modo === 'blocos' || $modo === 'simples') {
            return $modo;
        }
        return !empty($grupo['tipos']) ? 'blocos' : 'simples';
    }

    /**
     * @param array<string,mixed> $grupo
     * @return list<array<string,mixed>>
     */
    public function montarComponentesQuadro(array $grupo): array
    {
        $marcasPorId = [];
        foreach ($grupo['marcas'] ?? [] as $m) {
            $mid = (int) ($m['id'] ?? 0);
            if ($mid > 0) {
                $marcasPorId[$mid] = $m;
            }
        }
        $comps = [];
        $codigosSemana = [];
        $codigosUsados = [];
        $grupoId = (int) ($grupo['id'] ?? 0);
        $escala = (float) ($grupo['escala_max'] ?? 10) ?: 10;
        $criterio = (string) ($grupo['criterio_calculo'] ?? 'ultima');
        if (!in_array($criterio, ['ultima', 'media', 'soma', 'maior'], true)) {
            $criterio = 'ultima';
        }
        $consolCodigo = trim((string) ($grupo['coluna_consolidada_codigo'] ?? ''));
        $consolNome = trim((string) ($grupo['coluna_consolidada_nome'] ?? ''));
        $tipos = is_array($grupo['tipos'] ?? null) ? $grupo['tipos'] : [];
        $todasMarcasIds = array_keys($marcasPorId);

        if ($tipos === []) {
            foreach ($grupo['marcas'] ?? [] as $marca) {
                    $comp = $this->componenteDeMarca($grupoId, $marca, 0, 'quadro_comum', '', [], $codigosUsados, '', $escala, $criterio);
                if ($comp === null) {
                    continue;
                }
                $codigosUsados[$comp['codigo']] = true;
                $codigosSemana[] = $comp['codigo'];
                $comps[] = $comp;
            }
        } else {
            foreach ($tipos as $tipo) {
                $tipoId = (int) ($tipo['id'] ?? 0);
                $layoutGroup = $this->layoutGroupDoCodigo((string) ($tipo['codigo'] ?? ''));
                $label = trim((string) ($tipo['nome'] ?? ''));
                $materiasIds = array_values(array_filter(array_map('intval', $tipo['materias_ids'] ?? [])));
                $slugTipo = $this->slug((string) ($tipo['codigo'] ?? ''));
                $marcasDoTipo = array_values(array_filter(array_map('intval', $tipo['marcas_ids'] ?? [])));
                if ($marcasDoTipo === [] && $todasMarcasIds !== []) {
                    $marcasDoTipo = $todasMarcasIds;
                }
                if ($marcasDoTipo === []) {
                    $codigo = $slugTipo !== '' ? $slugTipo : ('tipo_' . $tipoId);
                    if (isset($codigosUsados[$codigo])) {
                        $codigo = $codigo . '_' . $tipoId;
                    }
                    $codigosUsados[$codigo] = true;
                    $comps[] = [
                        'id' => 0,
                        'codigo' => $codigo,
                        'nome' => $label !== '' ? $label : strtoupper($codigo),
                        'source_type' => 'provas_sistema',
                        'calc_type' => $criterio,
                        'peso' => 1,
                        'filtro_titulo' => '',
                        'bloco_id' => 0,
                        'blocos_ids' => [],
                        'materia_id' => 0,
                        'materias_ids' => $materiasIds,
                        'materia_unica' => $materiasIds !== [] ? 1 : 0,
                        'usar_percentual' => 0,
                        'escala_max' => $escala,
                        'obrigatorio' => 0,
                        'config' => [
                            'grupo_regras_notas_id' => $grupoId,
                            'grupo_regras_tipo_id' => $tipoId,
                            'layout_group' => $layoutGroup,
                            'layout_group_label' => $label,
                            'layout_type' => 'valor10',
                        ],
                        'layout_group' => $layoutGroup,
                        'layout_type' => 'valor10',
                    ];
                    continue;
                }
                foreach ($marcasDoTipo as $marcaId) {
                    $marca = $marcasPorId[$marcaId] ?? null;
                    if (!is_array($marca)) {
                        continue;
                    }
                    $comp = $this->componenteDeMarca(
                        $grupoId,
                        $marca,
                        $tipoId,
                        $layoutGroup,
                        $label,
                        $materiasIds,
                        $codigosUsados,
                        $slugTipo,
                        $escala,
                        $criterio
                    );
                    if ($comp === null) {
                        continue;
                    }
                    $codigosUsados[$comp['codigo']] = true;
                    $codigosSemana[] = $comp['codigo'];
                    $comps[] = $comp;
                }
            }
        }
        $codigosSemana = array_values(array_unique($codigosSemana));
        if ($codigosSemana !== [] && $consolCodigo !== '') {
            $comps[] = [
                'id' => 0,
                'codigo' => $consolCodigo,
                'nome' => $consolNome,
                'source_type' => 'calculado',
                'calc_type' => 'media',
                'peso' => 1,
                'filtro_titulo' => '',
                'blocos_ids' => [],
                'materias_ids' => [],
                'materia_unica' => 0,
                'usar_percentual' => 0,
                'escala_max' => $escala,
                'obrigatorio' => 0,
                'config' => [
                    'expressao' => '',
                    'formula_mode' => 'single',
                    'agregar_nq' => $codigosSemana,
                    'layout_group' => 'quadro_comum',
                    'layout_type' => 'media_sem',
                    'layout' => ['group' => 'quadro_comum', 'type' => 'media_sem'],
                    'coluna_consolidada' => true,
                ],
                'layout_group' => 'quadro_comum',
                'layout_type' => 'media_sem',
            ];
        }
        return $comps;
    }

    /**
     * Escala/fechamento não vêm mais do wizard. Sem os campos no POST:
     * quadro novo = 0–10 e sem coluna de média automática; edição preserva o que já estava.
     *
     * @param array<string,mixed> $post
     * @param array<string,mixed>|null $atual
     * @return array{escala_max:mixed,criterio_calculo:string,coluna_consolidada_codigo:string,coluna_consolidada_nome:string}
     */
    private function metaFechamentoDoPost(array $post, ?array $atual): array
    {
        $temCamposUi = array_key_exists('escala_max', $post)
            || array_key_exists('criterio_calculo', $post)
            || array_key_exists('usar_coluna_consolidada', $post);
        if (!$temCamposUi) {
            if ($atual !== null) {
                return [
                    'escala_max' => $atual['escala_max'] ?? 10,
                    'criterio_calculo' => (string) ($atual['criterio_calculo'] ?? 'media'),
                    'coluna_consolidada_codigo' => (string) ($atual['coluna_consolidada_codigo'] ?? ''),
                    'coluna_consolidada_nome' => (string) ($atual['coluna_consolidada_nome'] ?? ''),
                ];
            }
            return [
                'escala_max' => 10,
                'criterio_calculo' => 'media',
                'coluna_consolidada_codigo' => '',
                'coluna_consolidada_nome' => '',
            ];
        }

        $usarConsol = !empty($post['usar_coluna_consolidada']);
        $consolNome = $usarConsol ? trim((string) ($post['coluna_consolidada_nome'] ?? 'Média')) : '';
        if ($usarConsol && $consolNome === '') {
            $consolNome = 'Média';
        }
        $consolCodigo = '';
        if ($usarConsol) {
            $consolCodigo = GrupoRegrasNotas::normalizarCodigoColuna((string) ($post['coluna_consolidada_codigo'] ?? ''));
            if ($consolCodigo === '') {
                $slugNome = $this->slug($consolNome);
                $consolCodigo = ($slugNome === '' || $slugNome === 'media') ? 'media_sem' : $slugNome;
            }
        }

        return [
            'escala_max' => $post['escala_max'] ?? 10,
            'criterio_calculo' => $post['criterio_calculo'] ?? 'media',
            'coluna_consolidada_codigo' => $consolCodigo,
            'coluna_consolidada_nome' => $consolNome,
        ];
    }

    /**
     * @param array<string,mixed> $post
     * @return array{success:bool,id?:int,error?:string}
     */
    public function salvar(array $post, ?int $id = null): array
    {
        if (!$this->model->tabelasProntas()) {
            return ['success' => false, 'error' => 'Rode as migrations 2026_09_01_grupos_regras_notas.sql e 2026_09_10_quadros_notas.sql no Master.'];
        }
        $nome = trim((string) ($post['nome'] ?? ''));
        if ($nome === '') {
            return ['success' => false, 'error' => 'Informe o nome do quadro.'];
        }
        $descricao = trim((string) ($post['descricao'] ?? ''));
        $descricao = $descricao !== '' ? $descricao : null;
        $ativo = !empty($post['ativo']);

        $marcasNorm = $this->normalizarMarcas($post['colunas'] ?? $post['marcas'] ?? []);
        if ($marcasNorm['error'] !== null) {
            return ['success' => false, 'error' => $marcasNorm['error']];
        }
        $tiposNorm = $this->normalizarTipos($post['tipos'] ?? [], $marcasNorm['itens']);
        if ($tiposNorm['error'] !== null) {
            return ['success' => false, 'error' => $tiposNorm['error']];
        }
        if ($tiposNorm['itens'] === [] && $marcasNorm['itens'] === []) {
            return ['success' => false, 'error' => 'Cadastre pelo menos uma coluna ou um bloco de disciplinas.'];
        }

        $atual = ($id !== null && $id > 0) ? $this->model->findById($id) : null;
        $meta = $this->metaFechamentoDoPost($post, is_array($atual) ? $atual : null);
        $meta['modo'] = $tiposNorm['itens'] !== [] ? 'blocos' : 'simples';
        if (array_key_exists('ritmo_intervalo_semanas', $post) || array_key_exists('ritmo_data_inicio', $post)) {
            $meta['ritmo_intervalo_semanas'] = $post['ritmo_intervalo_semanas'] ?? 2;
            $meta['ritmo_data_inicio'] = $post['ritmo_data_inicio'] ?? null;
        }

        try {
            $ehNovo = ($id === null || $id <= 0);
            if ($ehNovo) {
                $id = $this->model->criar($nome, $descricao, $ativo, $meta);
            } else {
                if ($atual === null) {
                    return ['success' => false, 'error' => 'Quadro não encontrado.'];
                }
                $erroUso = $this->erroRemocaoEmUso($id, $marcasNorm['itens'], $tiposNorm['itens']);
                if ($erroUso !== null) {
                    return ['success' => false, 'error' => $erroUso];
                }
                $this->model->atualizar($id, $nome, $descricao, $ativo, $meta);
            }

            $marcaIdsPorChave = [];
            $manterMarcas = [];
            foreach ($marcasNorm['itens'] as $idx => $marca) {
                $mid = (int) ($marca['id'] ?? 0);
                if ($mid > 0 && $this->model->existeMarcaNoGrupo($mid, $id)) {
                    $this->model->atualizarMarca($mid, $id, $marca['codigo'], $marca['nome'], $marca['numero'], $idx, $marca);
                } else {
                    $mid = $this->model->inserirMarca($id, $marca['codigo'], $marca['nome'], $marca['numero'], $idx, $marca);
                }
                $manterMarcas[] = $mid;
                $marcaIdsPorChave[$marca['chave']] = $mid;
            }
            $this->model->excluirMarcasFora($id, $manterMarcas);

            $manterTipos = [];
            foreach ($tiposNorm['itens'] as $idx => $tipo) {
                $tid = (int) ($tipo['id'] ?? 0);
                if ($tid > 0 && $this->model->existeTipoNoGrupo($tid, $id)) {
                    $this->model->atualizarTipo($tid, $id, $tipo['codigo'], $tipo['nome'], $idx, null);
                } else {
                    $tid = $this->model->inserirTipo($id, $tipo['codigo'], $tipo['nome'], $idx, null);
                }
                $manterTipos[] = $tid;
                $marcaIds = [];
                foreach ($tipo['marcas_chaves'] as $chave) {
                    if (isset($marcaIdsPorChave[$chave])) {
                        $marcaIds[] = $marcaIdsPorChave[$chave];
                    }
                }
                $this->model->substituirMarcasDoTipo($tid, $marcaIds);
                $this->model->substituirMateriasDoTipo($tid, $tipo['materias_ids']);
                if (array_key_exists('ritmo_data_inicio', $tipo)) {
                    $this->model->atualizarRitmoTipo($tid, $tipo['ritmo_data_inicio']);
                }
            }
            $this->model->excluirTiposFora($id, $manterTipos);
        } catch (Throwable $e) {
            error_log('GrupoRegrasNotasService::salvar: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível salvar o quadro. Verifique nomes e números das colunas.'];
        }

        return ['success' => true, 'id' => $id];
    }

    public function excluirGrupo(int $id): array
    {
        if ($id <= 0 || $this->model->findById($id) === null) {
            return ['success' => false, 'error' => 'Quadro não encontrado.'];
        }
        if ($this->model->grupoEmUsoEmProvas($id)) {
            return [
                'success' => false,
                'error' => 'Este quadro está em uso em avaliações. Inative-o em vez de excluir.',
            ];
        }
        $this->model->excluir($id);
        return ['success' => true];
    }

    /**
     * Garante que tipo e marca pertencem ao mesmo grupo. Semana sai do número da marca.
     *
     * @return array{ok:bool,error?:string,grupo_id:?int,tipo_id:?int,marca_id:?int,semana:?int}
     */
    public function validarVinculoProva(?int $grupoId, ?int $tipoId, ?int $marcaId): array
    {
        $grupoId = (int) $grupoId;
        $tipoId = (int) $tipoId;
        $marcaId = (int) $marcaId;
        $vazio = [
            'ok' => true,
            'grupo_id' => null,
            'tipo_id' => null,
            'marca_id' => null,
            'semana' => null,
        ];
        if ($grupoId <= 0 && $tipoId <= 0 && $marcaId <= 0) {
            return $vazio;
        }
        if (!$this->model->tabelasProntas()) {
            return $vazio;
        }

        if ($grupoId <= 0) {
            if ($tipoId > 0) {
                $tipo = $this->model->findTipoById($tipoId);
                $grupoId = (int) ($tipo['grupo_id'] ?? 0);
            } elseif ($marcaId > 0) {
                $marca = $this->model->findMarcaById($marcaId);
                $grupoId = (int) ($marca['grupo_id'] ?? 0);
            }
        }
        if ($grupoId <= 0 || $this->model->findById($grupoId) === null) {
            return ['ok' => false, 'error' => 'Quadro de Notas inválido.'];
        }

        $temTipos = $this->model->listarTipos($grupoId) !== [];
        $temMarcas = $this->model->listarMarcas($grupoId) !== [];
        $semana = null;

        if ($temTipos) {
            if ($tipoId <= 0) {
                return ['ok' => false, 'error' => 'Selecione o bloco de disciplinas deste quadro.'];
            }
            if (!$this->model->existeTipoNoGrupo($tipoId, $grupoId)) {
                return ['ok' => false, 'error' => 'O bloco de disciplinas não pertence ao quadro selecionado.'];
            }
        } else {
            $tipoId = 0;
        }

        if ($temMarcas) {
            if ($marcaId <= 0) {
                return ['ok' => false, 'error' => 'Selecione a coluna deste quadro.'];
            }
            if (!$this->model->existeMarcaNoGrupo($marcaId, $grupoId)) {
                return ['ok' => false, 'error' => 'A coluna não pertence ao quadro selecionado.'];
            }
            if ($tipoId > 0 && !$this->model->marcaVinculadaAoTipo($tipoId, $marcaId)) {
                return ['ok' => false, 'error' => 'A coluna não pertence ao bloco de disciplinas selecionado neste quadro.'];
            }
            $marca = $this->model->findMarcaById($marcaId);
            if (!is_array($marca)) {
                return ['ok' => false, 'error' => 'A coluna não pertence ao quadro selecionado.'];
            }
            if (!GrupoRegrasNotas::ehLancamento($marca)) {
                return ['ok' => false, 'error' => 'Esta coluna é calculada — não recebe lançamento.'];
            }
            $semana = (int) ($marca['numero'] ?? 0);
            if ($semana < 1 || $semana > GrupoRegrasNotas::NUMERO_MAX) {
                return ['ok' => false, 'error' => 'A coluna selecionada não tem um número válido.'];
            }
        } else {
            $marcaId = 0;
            $semana = null;
        }

        return [
            'ok' => true,
            'grupo_id' => $grupoId,
            'tipo_id' => $tipoId > 0 ? $tipoId : null,
            'marca_id' => $marcaId > 0 ? $marcaId : null,
            'semana' => $semana,
        ];
    }

    /**
     * @param list<array<string,mixed>> $marcasItens
     * @param list<array<string,mixed>> $tiposItens
     */
    private function erroRemocaoEmUso(int $grupoId, array $marcasItens, array $tiposItens): ?string
    {
        $manterMarcas = [];
        foreach ($marcasItens as $marca) {
            $mid = (int) ($marca['id'] ?? 0);
            if ($mid > 0 && $this->model->existeMarcaNoGrupo($mid, $grupoId)) {
                $manterMarcas[] = $mid;
            }
        }
        $atuaisMarcas = [];
        foreach ($this->model->listarMarcas($grupoId) as $m) {
            $atuaisMarcas[] = (int) ($m['id'] ?? 0);
        }
        $emUsoMarcas = $this->model->idsMarcasEmUsoEmProvas(array_values(array_diff($atuaisMarcas, $manterMarcas)));
        if ($emUsoMarcas !== []) {
            $nomes = $this->model->nomesMarcasPorIds($emUsoMarcas);
            return 'Não é possível remover colunas em uso em eventos de prova: ' . implode(', ', $nomes) . '.';
        }

        $manterTipos = [];
        foreach ($tiposItens as $tipo) {
            $tid = (int) ($tipo['id'] ?? 0);
            if ($tid > 0 && $this->model->existeTipoNoGrupo($tid, $grupoId)) {
                $manterTipos[] = $tid;
            }
        }
        $atuaisTipos = [];
        foreach ($this->model->listarTipos($grupoId) as $t) {
            $atuaisTipos[] = (int) ($t['id'] ?? 0);
        }
        $emUsoTipos = $this->model->idsTiposEmUsoEmProvas(array_values(array_diff($atuaisTipos, $manterTipos)));
        if ($emUsoTipos !== []) {
            $nomes = $this->model->nomesTiposPorIds($emUsoTipos);
            return 'Não é possível remover blocos de disciplinas em uso em eventos de prova: ' . implode(', ', $nomes) . '.';
        }

        return null;
    }

    /**
     * Semanas do Quadro de Notas: só colunas S1, S2… cadastradas.
     * Sem blocos, todas ficam em A e B fica vazio (uma tabela só).
     *
     * @return array{a:list<int>,b:list<int>}
     */
    public function semanasQuadroPadrao(?int $grupoId = null): array
    {
        $vazio = ['a' => [], 'b' => []];
        $grupo = $this->resolverGrupoQuadro($grupoId);
        if ($grupo === null) {
            return $vazio;
        }
        $numsPorMarca = [];
        foreach ($grupo['marcas'] ?? [] as $m) {
            if (!is_array($m) || !$this->marcaEhColunaSemanal($m)) {
                continue;
            }
            $mid = (int) ($m['id'] ?? 0);
            $n = (int) ($m['numero'] ?? 0);
            if ($mid > 0 && $n >= 1 && $n <= GrupoRegrasNotas::NUMERO_MAX) {
                $numsPorMarca[$mid] = $n;
            }
        }
        $todas = $this->normalizarListaNumeros(array_values($numsPorMarca));
        $tipos = is_array($grupo['tipos'] ?? null) ? $grupo['tipos'] : [];
        if ($tipos === [] || $todas === []) {
            return ['a' => $todas, 'b' => []];
        }
        $a = [];
        $b = [];
        foreach ($tipos as $tipo) {
            if (!is_array($tipo)) {
                continue;
            }
            $layout = $this->layoutGroupDoCodigo((string) ($tipo['codigo'] ?? ''));
            $ids = array_values(array_filter(array_map('intval', $tipo['marcas_ids'] ?? [])));
            $nums = [];
            foreach ($ids !== [] ? $ids : array_keys($numsPorMarca) as $marcaId) {
                if (isset($numsPorMarca[$marcaId])) {
                    $nums[] = $numsPorMarca[$marcaId];
                }
            }
            if ($layout === 'quadro_a') {
                $a = array_merge($a, $nums);
            } elseif ($layout === 'quadro_b') {
                $b = array_merge($b, $nums);
            }
        }
        $a = $this->normalizarListaNumeros($a);
        $b = $this->normalizarListaNumeros($b);
        if ($a === [] && $b === []) {
            return ['a' => $todas, 'b' => []];
        }

        return ['a' => $a, 'b' => $b];
    }

    /**
     * @return array{A:list<array{id:int,nome:string}>,B:list<array{id:int,nome:string}>}
     */
    public function materiasQuadroPadrao(?int $grupoId = null): array
    {
        $out = ['A' => [], 'B' => []];
        $grupo = $this->resolverGrupoQuadro($grupoId);
        if ($grupo === null) {
            return $out;
        }
        $nomes = [];
        foreach ($this->model->listarMaterias() as $m) {
            $nomes[(int) ($m['id'] ?? 0)] = (string) ($m['nome'] ?? '');
        }
        foreach ($grupo['tipos'] ?? [] as $tipo) {
            if (!is_array($tipo)) {
                continue;
            }
            $layout = $this->layoutGroupDoCodigo((string) ($tipo['codigo'] ?? ''));
            $letra = $layout === 'quadro_a' ? 'A' : ($layout === 'quadro_b' ? 'B' : '');
            if ($letra === '') {
                continue;
            }
            foreach ($tipo['materias_ids'] ?? [] as $mid) {
                $id = (int) $mid;
                if ($id <= 0) {
                    continue;
                }
                $out[$letra][] = [
                    'id' => $id,
                    'nome' => $nomes[$id] ?? '',
                ];
            }
        }

        return $out;
    }

    public function layoutGroupDoCodigo(string $codigo): string
    {
        $c = strtolower(trim($codigo));
        $c = str_replace(['-', ' '], '_', $c);
        if (in_array($c, ['a', 'grupo_a', 'quadro_a', 'bloco_a'], true)) {
            return 'quadro_a';
        }
        if (in_array($c, ['b', 'grupo_b', 'quadro_b', 'bloco_b'], true)) {
            return 'quadro_b';
        }
        $slug = $this->slug($c !== '' ? $c : 'tipo');
        if ($slug === '') {
            $slug = 'tipo';
        }
        if (strpos($slug, 'grupo_') === 0) {
            return $slug;
        }
        return 'grupo_' . $slug;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolverGrupoQuadro(?int $grupoId): ?array
    {
        if ($grupoId !== null && $grupoId > 0) {
            try {
                $completo = $this->carregarCompleto($grupoId);
                if (is_array($completo)) {
                    return $completo;
                }
            } catch (Throwable $e) {
                return $this->primeiroQuadroAtivo();
            }
        }

        return $this->primeiroQuadroAtivo();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function primeiroQuadroAtivo(): ?array
    {
        if (!$this->model->tabelasProntas()) {
            return null;
        }
        try {
            foreach ($this->model->listar(true) as $row) {
                $id = (int) ($row['id'] ?? 0);
                $completo = $id > 0 ? $this->carregarCompleto($id) : null;
                if (is_array($completo)) {
                    return $completo;
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function primeiroGrupoQuadroAB(): ?array
    {
        if (!$this->model->tabelasProntas()) {
            return null;
        }
        try {
            foreach ($this->model->listar(true) as $row) {
                $id = (int) ($row['id'] ?? 0);
                $completo = $id > 0 ? $this->carregarCompleto($id) : null;
                if ($completo === null) {
                    continue;
                }
                foreach ($completo['tipos'] ?? [] as $tipo) {
                    if (!is_array($tipo)) {
                        continue;
                    }
                    $lg = $this->layoutGroupDoCodigo((string) ($tipo['codigo'] ?? ''));
                    if ($lg === 'quadro_a' || $lg === 'quadro_b') {
                        return $completo;
                    }
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $grupo
     * @param array<string,mixed> $tipo
     * @return list<int>
     */
    private function numerosMarcasDoTipo(array $grupo, array $tipo): array
    {
        $marcasPorId = [];
        foreach ($grupo['marcas'] ?? [] as $m) {
            if (!is_array($m)) {
                continue;
            }
            $mid = (int) ($m['id'] ?? 0);
            if ($mid > 0) {
                $marcasPorId[$mid] = (int) ($m['numero'] ?? 0);
            }
        }
        $ids = array_values(array_filter(array_map('intval', $tipo['marcas_ids'] ?? [])));
        if ($ids === []) {
            $ids = array_keys($marcasPorId);
        }
        $out = [];
        foreach ($ids as $id) {
            $n = (int) ($marcasPorId[$id] ?? 0);
            if ($n >= 1 && $n <= GrupoRegrasNotas::NUMERO_MAX) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * @param list<int> $nums
     * @return list<int>
     */
    private function normalizarListaNumeros(array $nums): array
    {
        $out = [];
        foreach ($nums as $n) {
            $n = (int) $n;
            if ($n >= 1 && $n <= GrupoRegrasNotas::NUMERO_MAX) {
                $out[] = $n;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * @param mixed $raw
     * @return array{itens:list<array<string,mixed>>,error:?string}
     */
    private function normalizarMarcas($raw): array
    {
        if (!is_array($raw)) {
            return ['itens' => [], 'error' => null];
        }
        $out = [];
        $numeros = [];
        $codigos = [];
        $codigosLancamento = [];
        foreach ($raw as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $nome = trim((string) ($row['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $papel = GrupoRegrasNotas::papelDaColuna($row);
            $numero = (int) ($row['numero'] ?? 0);
            if ($papel === 'calculada') {
                if ($numero < GrupoRegrasNotas::NUMERO_CALCULADA_MIN || $numero > GrupoRegrasNotas::NUMERO_CALCULADA_MAX) {
                    $numero = 0;
                }
            } else {
                if ($numero < 1 || $numero > GrupoRegrasNotas::NUMERO_MAX) {
                    return ['itens' => [], 'error' => 'Cada coluna de lançamento precisa de um número de 1 a ' . GrupoRegrasNotas::NUMERO_MAX . '.'];
                }
            }
            $codigo = $this->slug((string) ($row['codigo'] ?? ''));
            if ($codigo === '') {
                $codigo = $papel === 'calculada' ? $this->slug($nome) : ('s' . $numero);
                if ($codigo === '') {
                    $codigo = 'col_' . ($i + 1);
                }
            }
            if (isset($codigos[$codigo])) {
                $codigo = $codigo . '_' . ($numero > 0 ? $numero : ($i + 1));
            }
            $codigos[$codigo] = true;
            $chave = trim((string) ($row['chave'] ?? ''));
            if ($chave === '') {
                $chave = $codigo;
            }
            $tipoNotaId = (int) ($row['tipo_nota_id'] ?? 0);
            $formulaCodigos = [];
            $rawFormula = $row['formula_colunas'] ?? [];
            if (!is_array($rawFormula) && is_string($row['formula_json'] ?? '')) {
                $dec = json_decode((string) $row['formula_json'], true);
                $rawFormula = is_array($dec['colunas'] ?? null) ? $dec['colunas'] : [];
            }
            if (is_array($rawFormula)) {
                foreach ($rawFormula as $fc) {
                    $fc = trim((string) $fc);
                    if ($fc !== '') {
                        $formulaCodigos[] = $fc;
                    }
                }
            }
            $item = [
                'id' => (int) ($row['id'] ?? 0),
                'chave' => $chave,
                'codigo' => $codigo,
                'nome' => $nome,
                'numero' => $numero,
                'papel' => $papel,
                'tipo_nota_id' => $papel === 'lancamento' && $tipoNotaId > 0 ? $tipoNotaId : null,
                'formula_codigos' => array_values(array_unique($formulaCodigos)),
                'vai_para_boletim' => $papel === 'calculada' && !empty($row['vai_para_boletim']),
            ];
            $out[] = $item;
            if ($papel === 'lancamento') {
                $codigosLancamento[] = $codigo;
            }
        }

        $usadosCalc = [];
        foreach ($out as $item) {
            if ($item['papel'] !== 'calculada' && $item['numero'] > 0) {
                $usadosCalc[$item['numero']] = true;
            }
        }
        foreach ($out as $i => $item) {
            if ($item['papel'] !== 'calculada') {
                if (isset($numeros[$item['numero']])) {
                    return ['itens' => [], 'error' => 'Número de coluna repetido: ' . $item['numero'] . '.'];
                }
                $numeros[$item['numero']] = true;
                continue;
            }
            $n = (int) $item['numero'];
            if ($n < GrupoRegrasNotas::NUMERO_CALCULADA_MIN || isset($usadosCalc[$n]) || isset($numeros[$n])) {
                $n = 0;
                for ($c = GrupoRegrasNotas::NUMERO_CALCULADA_MIN; $c <= GrupoRegrasNotas::NUMERO_CALCULADA_MAX; $c++) {
                    if (!isset($usadosCalc[$c]) && !isset($numeros[$c])) {
                        $n = $c;
                        break;
                    }
                }
                if ($n < GrupoRegrasNotas::NUMERO_CALCULADA_MIN) {
                    return ['itens' => [], 'error' => 'Limite de colunas calculadas atingido.'];
                }
            }
            $usadosCalc[$n] = true;
            $numeros[$n] = true;
            $out[$i]['numero'] = $n;
            $refs = $item['formula_codigos'];
            if ($refs === []) {
                $refs = $codigosLancamento;
            }
            $out[$i]['formula_json'] = json_encode(
                ['modo' => 'media', 'colunas' => $refs],
                JSON_UNESCAPED_UNICODE
            );
            unset($out[$i]['formula_codigos']);
        }
        foreach ($out as $i => $item) {
            if (($item['papel'] ?? '') === 'calculada') {
                continue;
            }
            $out[$i]['formula_json'] = null;
            unset($out[$i]['formula_codigos']);
        }
        return ['itens' => $out, 'error' => null];
    }

    /**
     * @param mixed $raw
     * @param list<array<string,mixed>> $marcas
     * @return array{itens:list<array<string,mixed>>,error:?string}
     */
    private function normalizarTipos($raw, array $marcas): array
    {
        if (!is_array($raw)) {
            return ['itens' => [], 'error' => null];
        }
        $chavesMarca = [];
        foreach ($marcas as $m) {
            $chavesMarca[(string) $m['chave']] = true;
        }
        $out = [];
        $codigos = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $nome = trim((string) ($row['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $codigo = $this->slug((string) ($row['codigo'] ?? $nome));
            if ($codigo === '') {
                $codigo = 'tipo';
            }
            if (isset($codigos[$codigo])) {
                $codigo = $codigo . '_' . (count($out) + 1);
            }
            $codigos[$codigo] = true;
            $marcasChaves = [];
            $rawMarcas = $row['colunas'] ?? $row['marcas'] ?? [];
            if (!is_array($rawMarcas)) {
                $rawMarcas = [];
            }
            foreach ($rawMarcas as $ch) {
                $ch = trim((string) $ch);
                if ($ch !== '' && isset($chavesMarca[$ch])) {
                    $marcasChaves[] = $ch;
                }
            }
            $materias = [];
            $rawMats = $row['materias'] ?? [];
            if (!is_array($rawMats)) {
                $rawMats = [];
            }
            foreach ($rawMats as $mid) {
                $n = (int) $mid;
                if ($n > 0) {
                    $materias[] = $n;
                }
            }
            $item = [
                'id' => (int) ($row['id'] ?? 0),
                'codigo' => $codigo,
                'nome' => $nome,
                'marcas_chaves' => array_values(array_unique($marcasChaves)),
                'materias_ids' => array_values(array_unique($materias)),
            ];
            if (array_key_exists('ritmo_data_inicio', $row)) {
                $item['ritmo_data_inicio'] = GrupoRegrasNotas::normalizarData($row['ritmo_data_inicio'] ?? null);
            }
            $out[] = $item;
        }
        return ['itens' => $out, 'error' => null];
    }

    /**
     * @param array<string,mixed> $marca
     * @param list<int> $materiasIds
     * @param array<string,true> $codigosUsados
     * @return array<string,mixed>|null
     */
    private function componenteDeMarca(
        int $grupoId,
        array $marca,
        int $tipoId,
        string $layoutGroup,
        string $label,
        array $materiasIds,
        array $codigosUsados,
        string $slugTipo,
        float $escala = 10,
        string $criterio = 'media'
    ): ?array {
        $numero = (int) ($marca['numero'] ?? 0);
        $codigoBase = $this->codigoColunaSemana($marca, $numero);
        if ($codigoBase === '' || $numero < 1) {
            return null;
        }
        $codigo = $codigoBase;
        if (isset($codigosUsados[$codigo])) {
            $codigo = ($slugTipo !== '' ? $slugTipo . '_' : '') . $codigoBase;
        }
        $config = [
            'semana' => $numero,
            'grupo_regras_notas_id' => $grupoId,
            'grupo_regras_marca_id' => (int) ($marca['id'] ?? 0),
            'layout_group' => $layoutGroup,
            'layout_type' => 'semana_nq',
            'tipo_avaliacao_nome' => 'Semanal',
        ];
        if ($tipoId > 0) {
            $config['grupo_regras_tipo_id'] = $tipoId;
        }
        if ($label !== '') {
            $config['layout_group_label'] = $label;
        }

        return [
            'id' => 0,
            'codigo' => $codigo,
            'nome' => (string) ($marca['nome'] ?? strtoupper($codigoBase)),
            'source_type' => 'provas_sistema',
            'calc_type' => $criterio,
            'peso' => 1,
            'filtro_titulo' => '',
            'bloco_id' => 0,
            'blocos_ids' => [],
            'materia_id' => 0,
            'materias_ids' => $materiasIds,
            'materia_unica' => 1,
            'usar_percentual' => 1,
            'escala_max' => $escala,
            'obrigatorio' => 0,
            'config' => $config,
            'layout_group' => $layoutGroup,
            'layout_type' => 'semana_nq',
        ];
    }

    /**
     * @param array<string,mixed> $marca
     */
    /**
     * @param array<string,mixed> $marca
     */
    private function marcaEhColunaSemanal(array $marca): bool
    {
        $cod = strtolower(trim((string) ($marca['codigo'] ?? '')));
        $nome = mb_strtolower(trim((string) ($marca['nome'] ?? '')), 'UTF-8');
        $blob = $cod . ' ' . $nome;
        if (
            $cod === 'trab' || $cod === 'trabalho' || $cod === 'prova_bim' || $cod === 'bimestral'
            || str_contains($blob, 'bimestral') || str_contains($blob, 'trabalho')
        ) {
            return false;
        }
        $nomeCompacto = str_replace(' ', '', $nome);
        if (preg_match('/^s[1-9]\d?$/', $cod) || preg_match('/^s[1-9]\d?$/', $nomeCompacto)) {
            return true;
        }

        return false;
    }

    private function codigoColunaSemana(array $marca, int $numero): string
    {
        $cod = strtolower(trim((string) ($marca['codigo'] ?? '')));
        if (preg_match('/^s[1-9]\d?$/', $cod)) {
            return $cod;
        }
        if (!$this->marcaEhColunaSemanal($marca)) {
            return '';
        }
        return $numero >= 1 ? ('s' . $numero) : '';
    }

    private function slug(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');
        $t = strtr($t, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
        $t = preg_replace('/[^a-z0-9]+/', '_', $t) ?? '';
        $t = trim($t, '_');
        if (strlen($t) > 40) {
            $t = substr($t, 0, 40);
        }
        return $t;
    }
}
