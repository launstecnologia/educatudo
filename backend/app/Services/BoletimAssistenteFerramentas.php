<?php
/**
 * Tools de grounding do Assistente de Boletim (catálogos do tenant).
 * Usado pelo chat in-app e pelo MCP (mesma superfície).
 */

require_once __DIR__ . '/../Models/System/BoletimConfig.php';

class BoletimAssistenteFerramentas
{
    private BoletimConfig $boletimConfig;
    private $db;

    public function __construct(?BoletimConfig $boletimConfig = null)
    {
        $this->boletimConfig = $boletimConfig ?? new BoletimConfig();
        $this->boletimConfig->ensureSchema();
        $this->db = Database::getInstance();
    }

    /**
     * @return list<array{id:int,nome:string,descricao:?string}>
     */
    public function listarTiposAvaliacao(): array
    {
        if (!class_exists('ExamEvaluationType', false)) {
            require_once __DIR__ . '/../Models/Exams/ExamEvaluationType.php';
        }
        $rows = (new ExamEvaluationType())->getAllActive();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($row['nome'] ?? '')),
                'descricao' => isset($row['descricao']) ? trim((string) $row['descricao']) : null,
            ];
        }
        return $out;
    }

    /**
     * @return list<array{id:int,nome:string,serie_id:?int,serie_nome:?string,curso_nome:?string,ano_letivo:?int}>
     */
    public function listarTurmas(int $limit = 500): array
    {
        $out = [];
        foreach ($this->boletimConfig->getAvailableClasses($limit) as $turma) {
            $id = (int) ($turma['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($turma['nome'] ?? '')),
                'serie_id' => isset($turma['serie_id']) ? (int) $turma['serie_id'] : null,
                'serie_nome' => isset($turma['serie_nome']) ? trim((string) $turma['serie_nome']) : null,
                'curso_nome' => isset($turma['curso_nome']) ? trim((string) $turma['curso_nome']) : null,
                'ano_letivo' => isset($turma['ano_letivo']) ? (int) $turma['ano_letivo'] : null,
            ];
        }
        return $out;
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function listarMaterias(int $limit = 300): array
    {
        $out = [];
        foreach ($this->boletimConfig->getAvailableSubjects($limit) as $materia) {
            $id = (int) ($materia['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($materia['nome'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * Lista jornadas candidatas para o escopo do boletim (compacto).
     *
     * @return list<array{id:int,nome:string,materia_nome:?string}>
     */
    public function listarJornadas(int $limit = 150): array
    {
        $limit = max(1, min($limit, 300));
        $path = __DIR__ . '/../Models/Education/JourneyBoletimLancamento.php';
        if (!class_exists('JourneyBoletimLancamento', false) && is_file($path)) {
            require_once $path;
        }
        if (!class_exists('JourneyBoletimLancamento', false)) {
            return [];
        }

        try {
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
                return [];
            }
            $rows = $jb->listarJornadasCandidatas($turmaIds, null, null);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteFerramentas::listarJornadas: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $j) {
            $id = (int) ($j['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($j['nome'] ?? ('Jornada #' . $id))),
                'materia_nome' => isset($j['materia_nome']) ? trim((string) $j['materia_nome']) : null,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @return list<array{id:int,nome:string,codigo:?string,ano_letivo:?int,bimestre:?int,exibir_em:?string}>
     */
    public function listarRegras(int $limit = 100): array
    {
        $out = [];
        foreach ($this->boletimConfig->listAllRules($limit) as $regra) {
            $id = (int) ($regra['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($regra['nome'] ?? '')),
                'codigo' => isset($regra['codigo']) ? trim((string) $regra['codigo']) : null,
                'ano_letivo' => isset($regra['ano_letivo']) ? (int) $regra['ano_letivo'] : null,
                'bimestre' => isset($regra['bimestre']) ? (int) $regra['bimestre'] : null,
                'exibir_em' => isset($regra['exibir_em']) ? (string) $regra['exibir_em'] : null,
            ];
        }
        return $out;
    }

    public function obterRegra(int $regraId): ?array
    {
        if ($regraId <= 0) {
            return null;
        }
        $regra = $this->boletimConfig->getRuleById($regraId);
        if (!$regra) {
            return null;
        }
        return $this->normalizarRegraParaAssistente($regra);
    }

    /**
     * Eventos de prova (blocos) com tipo de avaliação.
     *
     * @return list<array{id:int,titulo:string,tipo_avaliacao_id:?int,tipo_avaliacao_nome:?string,data_prova:?string,ano_letivo:?int,bimestre:?int}>
     */
    public function listarEventosProva(?int $tipoAvaliacaoId = null, int $limit = 200): array
    {
        $limit = max(1, min($limit, 500));
        $temTipo = $this->temColuna('provas_blocos', 'tipo_avaliacao_id')
            && $this->temTabela('provas_tipos_avaliacao');

        $selectTipoId = $temTipo ? 'pb.tipo_avaliacao_id' : 'NULL AS tipo_avaliacao_id';
        $selectTipoNome = $temTipo ? 'pta.nome AS tipo_avaliacao_nome' : 'NULL AS tipo_avaliacao_nome';
        $joinTipo = $temTipo
            ? 'LEFT JOIN provas_tipos_avaliacao pta ON pta.id = pb.tipo_avaliacao_id AND pta.deleted_at IS NULL'
            : '';

        $sql = "SELECT pb.id, pb.titulo, pb.data_prova, pb.ano_letivo, pb.bimestre,
                       {$selectTipoId}, {$selectTipoNome}
                FROM provas_blocos pb
                {$joinTipo}
                WHERE pb.deleted_at IS NULL";
        $params = [];
        if ($temTipo && $tipoAvaliacaoId !== null && $tipoAvaliacaoId > 0) {
            $sql .= ' AND pb.tipo_avaliacao_id = :tipo_id';
            $params['tipo_id'] = $tipoAvaliacaoId;
        }
        $sql .= " ORDER BY pb.data_prova DESC, pb.id DESC LIMIT {$limit}";

        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'titulo' => trim((string) ($row['titulo'] ?? '')),
                'tipo_avaliacao_id' => isset($row['tipo_avaliacao_id']) ? (int) $row['tipo_avaliacao_id'] : null,
                'tipo_avaliacao_nome' => isset($row['tipo_avaliacao_nome']) ? trim((string) $row['tipo_avaliacao_nome']) : null,
                'data_prova' => isset($row['data_prova']) ? (string) $row['data_prova'] : null,
                'ano_letivo' => isset($row['ano_letivo']) ? (int) $row['ano_letivo'] : null,
                'bimestre' => isset($row['bimestre']) ? (int) $row['bimestre'] : null,
            ];
        }
        return $out;
    }

    /**
     * Resolve tipo de avaliação (id ou nome parcial) → IDs de eventos/blocos no período.
     *
     * @return array{tipo:?array{id:int,nome:string},blocos_ids:list<int>,eventos:list<array>}
     */
    public function resolverBlocosPorTipo(
        $tipoAvaliacaoIdOuNome,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        int $limit = 100
    ): array {
        $tipo = $this->resolverTipoAvaliacao($tipoAvaliacaoIdOuNome);
        if ($tipo === null) {
            return ['tipo' => null, 'blocos_ids' => [], 'eventos' => []];
        }

        $eventos = $this->listarEventosProva((int) $tipo['id'], $limit);
        $ini = $this->normalizarData($dataInicio);
        $fim = $this->normalizarData($dataFim);
        if ($ini !== null && $fim !== null) {
            $eventos = array_values(array_filter($eventos, static function ($ev) use ($ini, $fim) {
                $d = substr((string) ($ev['data_prova'] ?? ''), 0, 10);
                if ($d === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                    return true; // sem data: mantém (pode estar no bimestre)
                }
                return $d >= $ini && $d <= $fim;
            }));
        }

        $ids = [];
        foreach ($eventos as $ev) {
            $ids[] = (int) $ev['id'];
        }

        return [
            'tipo' => $tipo,
            'blocos_ids' => array_values(array_unique(array_filter($ids))),
            'eventos' => $eventos,
        ];
    }

    /**
     * @param mixed $tipoAvaliacaoIdOuNome
     * @return array{id:int,nome:string}|null
     */
    public function resolverTipoAvaliacao($tipoAvaliacaoIdOuNome): ?array
    {
        if (is_int($tipoAvaliacaoIdOuNome) || (is_string($tipoAvaliacaoIdOuNome) && ctype_digit($tipoAvaliacaoIdOuNome))) {
            $id = (int) $tipoAvaliacaoIdOuNome;
            foreach ($this->listarTiposAvaliacao() as $t) {
                if ((int) $t['id'] === $id) {
                    return ['id' => $id, 'nome' => $t['nome']];
                }
            }
            return null;
        }

        $needle = mb_strtolower(trim((string) $tipoAvaliacaoIdOuNome));
        if ($needle === '') {
            return null;
        }

        $exato = null;
        $parcial = null;
        foreach ($this->listarTiposAvaliacao() as $t) {
            $nome = mb_strtolower($t['nome']);
            if ($nome === $needle) {
                $exato = ['id' => (int) $t['id'], 'nome' => $t['nome']];
                break;
            }
            if ($parcial === null && (str_contains($nome, $needle) || str_contains($needle, $nome))) {
                $parcial = ['id' => (int) $t['id'], 'nome' => $t['nome']];
            }
        }
        return $exato ?? $parcial;
    }

    /**
     * Enriquece componentes: tipo → blocos_ids; valida enums básicos.
     *
     * @return array{ok:bool,erros:list<string>,rascunho:array}
     */
    public function validarEEnriquecerRascunho(array $rascunho): array
    {
        $erros = [];
        $componentes = $rascunho['componentes'] ?? [];
        if (!is_array($componentes) || $componentes === []) {
            $erros[] = 'Informe ao menos um componente (ex.: prova semanal, bimestral).';
        }

        $dataIni = $this->normalizarData($rascunho['default_data_inicio'] ?? null);
        $dataFim = $this->normalizarData($rascunho['default_data_fim'] ?? null);
        if ($dataIni !== null) {
            $rascunho['default_data_inicio'] = $dataIni;
        }
        if ($dataFim !== null) {
            $rascunho['default_data_fim'] = $dataFim;
        }

        $codigos = [];
        $novos = [];
        foreach ((array) $componentes as $idx => $comp) {
            if (!is_array($comp)) {
                $erros[] = "Componente #{$idx} inválido.";
                continue;
            }
            $codigo = $this->slugCodigo((string) ($comp['codigo'] ?? ''));
            if ($codigo === '') {
                $codigo = 'comp' . ($idx + 1);
            }
            if (isset($codigos[$codigo])) {
                $erros[] = "Código duplicado: {$codigo}";
            }
            $codigos[$codigo] = true;

            $source = (string) ($comp['source_type'] ?? 'provas_sistema');
            $sourcesOk = ['provas_sistema', 'jornadas', 'calculado', 'evento_boletim', 'faltas_evento', 'nenhuma'];
            if (!in_array($source, $sourcesOk, true)) {
                // legado manual → provas_sistema (nota via lançamento/evento)
                $source = $source === 'manual' ? 'provas_sistema' : 'provas_sistema';
            }

            $calc = (string) ($comp['calc_type'] ?? 'media');
            if (!in_array($calc, ['media', 'soma', 'maior', 'ultima'], true)) {
                $calc = 'media';
            }

            $blocosIds = $this->normalizarIds($comp['blocos_ids'] ?? []);
            $tipoId = isset($comp['tipo_avaliacao_id']) ? (int) $comp['tipo_avaliacao_id'] : 0;
            $tipoNome = trim((string) ($comp['tipo_avaliacao_nome'] ?? ''));
            if ($blocosIds === [] && ($tipoId > 0 || $tipoNome !== '')) {
                $resolvido = $this->resolverBlocosPorTipo(
                    $tipoId > 0 ? $tipoId : $tipoNome,
                    $dataIni,
                    $dataFim
                );
                if ($resolvido['tipo'] !== null) {
                    $tipoId = (int) $resolvido['tipo']['id'];
                    $tipoNome = $resolvido['tipo']['nome'];
                    $blocosIds = $resolvido['blocos_ids'];
                }
            }

            $config = is_array($comp['config'] ?? null) ? $comp['config'] : [];
            $groupLine = is_array($config['group_line'] ?? null) ? $config['group_line'] : [];
            $config['group_line'] = [
                'enabled' => !empty($groupLine['enabled']),
                'key' => trim((string) ($groupLine['key'] ?? '')),
                'label' => trim((string) ($groupLine['label'] ?? '')),
                'mode' => in_array(($groupLine['mode'] ?? 'media'), ['media', 'soma'], true)
                    ? (string) $groupLine['mode']
                    : 'media',
                'divisor' => max(1, (int) ($groupLine['divisor'] ?? 1)),
                'materias_ids' => $this->normalizarIds($groupLine['materias_ids'] ?? []),
            ];
            if ($tipoId > 0) {
                $config['tipo_avaliacao_id'] = $tipoId;
            }
            if ($tipoNome !== '') {
                $config['tipo_avaliacao_nome'] = $tipoNome;
            }

            if ($source === 'calculado') {
                $exp = trim((string) ($config['expressao'] ?? $comp['expressao'] ?? ''));
                $config['expressao'] = $exp;
                $modeFm = strtolower(trim((string) ($config['formula_mode'] ?? 'single')));
                $config['formula_mode'] = $modeFm === 'per_materia' ? 'per_materia' : 'single';
                if ($exp === '') {
                    $erros[] = "Bloco calculado \"{$codigo}\" precisa de config.expressao (ex.: (semanal + bimestral) / 2).";
                }
            }

            $filtroTitulo = trim((string) ($comp['filtro_titulo'] ?? ''));
            // Se resolveu por tipo/blocos, não força filtro de título
            if ($blocosIds !== []) {
                $filtroTitulo = '';
            } elseif ($filtroTitulo === '' && $tipoNome !== '') {
                // fallback frágil: palavra do tipo (ex.: "Semanal")
                $filtroTitulo = $this->extrairPalavraFiltro($tipoNome);
            }

            $novos[] = [
                'codigo' => $codigo,
                'nome' => trim((string) ($comp['nome'] ?? $codigo)) ?: $codigo,
                'source_type' => $source,
                'calc_type' => $calc,
                'peso' => isset($comp['peso']) ? (float) $comp['peso'] : 1.0,
                'filtro_titulo' => $filtroTitulo,
                'bloco_id' => $blocosIds[0] ?? null,
                'blocos_ids' => $blocosIds,
                'tipo_avaliacao_id' => $tipoId > 0 ? $tipoId : null,
                'tipo_avaliacao_nome' => $tipoNome !== '' ? $tipoNome : null,
                'materias_ids' => $this->normalizarIds($comp['materias_ids'] ?? []),
                'materia_unica' => !empty($comp['materia_unica']) ? 1 : 0,
                'usar_percentual' => !empty($comp['usar_percentual']) ? 1 : 0,
                'escala_max' => isset($comp['escala_max']) ? (float) $comp['escala_max'] : 10.0,
                'obrigatorio' => !empty($comp['obrigatorio']) ? 1 : 0,
                'config' => $config,
            ];
        }

        $rascunho['componentes'] = $novos;
        $rascunho = $this->garantirBlocoCalculadoMedia($rascunho);
        $novos = $rascunho['componentes'];
        $rascunho['turmas_ids'] = $this->normalizarIds($rascunho['turmas_ids'] ?? []);
        $rascunho['materias_ids'] = $this->normalizarIds($rascunho['materias_ids'] ?? []);
        $rascunho['series_ids'] = $this->normalizarIds($rascunho['series_ids'] ?? []);

        $formula = trim((string) ($rascunho['formula_final'] ?? ''));
        $rascunho['formula_final'] = $formula;
        $rascunho['modo'] = (($rascunho['modo'] ?? '') === 'editar') ? 'editar' : 'criar';
        if (($rascunho['modo'] ?? '') === 'criar') {
            $rascunho['regra_id'] = null;
        } else {
            $rascunho['regra_id'] = isset($rascunho['regra_id']) ? (int) $rascunho['regra_id'] : null;
        }

        $exibir = (string) ($rascunho['exibir_em'] ?? 'boletim');
        $rascunho['exibir_em'] = in_array($exibir, ['notas', 'boletim'], true) ? $exibir : 'boletim';
        $rascunho['round_mode'] = (($rascunho['round_mode'] ?? '') === 'half') ? 'half' : 'none';

        return [
            'ok' => $erros === [],
            'erros' => $erros,
            'rascunho' => $rascunho,
        ];
    }

    /**
     * Se a média final está só em formula_final e o usuário espera um 3º bloco,
     * materializa um componente source_type=calculado (visível em "Blocos da regra").
     */
    private function garantirBlocoCalculadoMedia(array $rascunho): array
    {
        $comps = is_array($rascunho['componentes'] ?? null) ? $rascunho['componentes'] : [];
        $temCalculado = false;
        foreach ($comps as $c) {
            if (($c['source_type'] ?? '') === 'calculado') {
                $temCalculado = true;
                break;
            }
        }
        if ($temCalculado) {
            return $rascunho;
        }

        $formula = trim((string) ($rascunho['formula_final'] ?? ''));
        if ($formula === '') {
            return $rascunho;
        }

        // Se a fórmula é só o código de um bloco já existente, não cria coluna calculada extra.
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cod = trim((string) ($c['codigo'] ?? ''));
            if ($cod !== '' && strcasecmp($cod, $formula) === 0) {
                return $rascunho;
            }
        }

        // Expressão sem operadores: provavelmente referência simples, não "bloco de média".
        if (!preg_match('/[+\-*\/()]|max\s*\(|min\s*\(/i', $formula)) {
            return $rascunho;
        }

        $codigoMedia = 'media_final';
        foreach ($comps as $c) {
            if (($c['codigo'] ?? '') === $codigoMedia) {
                $codigoMedia = 'media_bimestre';
                break;
            }
        }

        $comps[] = [
            'codigo' => $codigoMedia,
            'nome' => 'Média',
            'source_type' => 'calculado',
            'calc_type' => 'media',
            'peso' => 1.0,
            'filtro_titulo' => '',
            'bloco_id' => null,
            'blocos_ids' => [],
            'materias_ids' => [],
            'usar_percentual' => 0,
            'escala_max' => 10.0,
            'obrigatorio' => 0,
            'config' => [
                'expressao' => $formula,
                'formula_mode' => 'single',
                'group_line' => [
                    'enabled' => false,
                    'key' => '',
                    'label' => '',
                    'mode' => 'media',
                    'divisor' => 1,
                    'materias_ids' => [],
                ],
            ],
        ];
        $rascunho['componentes'] = $comps;
        $rascunho['formula_final'] = $codigoMedia;

        return $rascunho;
    }

    /**
     * Formato portável para copiar/colar entre chats (RECEITA_BOLETIM v1).
     */
    public function formatarReceitaTexto(array $estadoOuRascunho): string
    {
        $e = $estadoOuRascunho;
        $lines = [];
        $lines[] = '# RECEITA_BOLETIM v1';
        $lines[] = '# Cole isto em outro Assistente de Boletim (mesma escola) para montar igual. Edite o que quiser antes de colar.';
        $lines[] = '# IDs (turmas, matérias, blocos) são desta escola — em outra escola resolva de novo pelos nomes/tipos.';
        $lines[] = 'nome: ' . $this->escaparReceitaValor($e['nome'] ?? '');
        $lines[] = 'codigo: ' . $this->escaparReceitaValor($e['codigo'] ?? '');
        $lines[] = 'descricao_curta: ' . $this->escaparReceitaValor($e['descricao_curta'] ?? '');
        $lines[] = 'ano_letivo: ' . (isset($e['ano_letivo']) && $e['ano_letivo'] !== '' && $e['ano_letivo'] !== null ? (int) $e['ano_letivo'] : '');
        $lines[] = 'bimestre: ' . (isset($e['bimestre']) && $e['bimestre'] !== '' && $e['bimestre'] !== null ? (int) $e['bimestre'] : '');
        $lines[] = 'exibir_em: ' . $this->escaparReceitaValor($e['exibir_em'] ?? 'boletim');
        $lines[] = 'turmas_ids: ' . $this->formatarListaIdsReceita($e['turmas_ids'] ?? []);
        $lines[] = 'materias_ids: ' . $this->formatarListaIdsReceita($e['materias_ids'] ?? []);
        $lines[] = 'series_ids: ' . $this->formatarListaIdsReceita($e['series_ids'] ?? []);
        $lines[] = 'round_mode: ' . $this->escaparReceitaValor($e['round_mode'] ?? 'none');
        $lines[] = 'nota_minima_aprovacao: ' . (isset($e['nota_minima_aprovacao']) ? (string) $e['nota_minima_aprovacao'] : '');
        $lines[] = 'formula_final: ' . $this->escaparReceitaValor($e['formula_final'] ?? '');
        $lines[] = 'default_data_inicio: ' . $this->escaparReceitaValor($e['default_data_inicio'] ?? '');
        $lines[] = 'default_data_fim: ' . $this->escaparReceitaValor($e['default_data_fim'] ?? '');
        $lines[] = '';

        $comps = is_array($e['componentes'] ?? null) ? $e['componentes'] : [];
        foreach ($comps as $c) {
            if (!is_array($c)) {
                continue;
            }
            $cfg = is_array($c['config'] ?? null) ? $c['config'] : [];
            $lines[] = '[[componente]]';
            $lines[] = 'nome: ' . $this->escaparReceitaValor($c['nome'] ?? '');
            $lines[] = 'codigo: ' . $this->escaparReceitaValor($c['codigo'] ?? '');
            $lines[] = 'source_type: ' . $this->escaparReceitaValor($c['source_type'] ?? 'provas_sistema');
            $lines[] = 'calc_type: ' . $this->escaparReceitaValor($c['calc_type'] ?? 'media');
            $lines[] = 'peso: ' . (isset($c['peso']) ? (string) $c['peso'] : '1');
            $lines[] = 'filtro_titulo: ' . $this->escaparReceitaValor($c['filtro_titulo'] ?? '');
            $lines[] = 'blocos_ids: ' . $this->formatarListaIdsReceita($c['blocos_ids'] ?? []);
            $lines[] = 'materias_ids: ' . $this->formatarListaIdsReceita($c['materias_ids'] ?? []);
            $lines[] = 'materia_unica: ' . (!empty($c['materia_unica']) ? '1' : '0');
            $lines[] = 'usar_percentual: ' . (!empty($c['usar_percentual']) ? '1' : '0');
            $lines[] = 'escala_max: ' . (isset($c['escala_max']) ? (string) $c['escala_max'] : '10');
            $lines[] = 'obrigatorio: ' . (!empty($c['obrigatorio']) ? '1' : '0');
            $tipoId = 0;
            if (!empty($c['tipo_avaliacao_id'])) {
                $tipoId = (int) $c['tipo_avaliacao_id'];
            } elseif (!empty($cfg['tipo_avaliacao_id'])) {
                $tipoId = (int) $cfg['tipo_avaliacao_id'];
            }
            $lines[] = 'tipo_avaliacao_id: ' . ($tipoId > 0 ? $tipoId : '');
            $lines[] = 'tipo_avaliacao_nome: ' . $this->escaparReceitaValor($c['tipo_avaliacao_nome'] ?? $cfg['tipo_avaliacao_nome'] ?? '');
            $lines[] = 'expressao: ' . $this->escaparReceitaValor($cfg['expressao'] ?? $c['expressao'] ?? '');
            $lines[] = 'formula_mode: ' . $this->escaparReceitaValor($cfg['formula_mode'] ?? 'single');
            if (!empty($cfg['jornada_ids']) && is_array($cfg['jornada_ids'])) {
                $lines[] = 'jornada_ids: ' . $this->formatarListaIdsReceita($cfg['jornada_ids']);
            }
            if (!empty($cfg['group_line']['enabled'])) {
                $gl = $cfg['group_line'];
                $lines[] = 'group_line_enabled: 1';
                $lines[] = 'group_line_key: ' . $this->escaparReceitaValor($gl['key'] ?? '');
                $lines[] = 'group_line_label: ' . $this->escaparReceitaValor($gl['label'] ?? '');
                $lines[] = 'group_line_mode: ' . $this->escaparReceitaValor($gl['mode'] ?? 'media');
                $lines[] = 'group_line_materias_ids: ' . $this->formatarListaIdsReceita($gl['materias_ids'] ?? []);
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    /**
     * @return array{ok:bool,rascunho?:array,aviso?:string}|null null = não é receita
     */
    public function tentarParseReceita(string $texto): ?array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        $ehReceitaV1 = (bool) preg_match('/#\s*RECEITA_BOLETIM\b/i', $texto)
            || (bool) preg_match('/\[\[componente\]\]/i', $texto);
        $ehMarkdownEstruturado = (bool) preg_match('/^\s*-\s*Nome\s*:/im', $texto)
            && (bool) preg_match('/Componentes\s*:/i', $texto);

        if (!$ehReceitaV1 && !$ehMarkdownEstruturado) {
            return null;
        }

        if ($ehReceitaV1) {
            return $this->parseReceitaV1($texto);
        }

        return $this->parseReceitaMarkdownSolta($texto);
    }

    /**
     * @return array{ok:bool,rascunho?:array,aviso?:string}
     */
    private function parseReceitaV1(string $texto): array
    {
        $blocos = preg_split('/\n(?=\[\[componente\]\])/i', $texto) ?: [$texto];
        $cabecalho = array_shift($blocos);
        $meta = $this->parseLinhasChaveValor((string) $cabecalho);

        $componentes = [];
        foreach ($blocos as $bloco) {
            $kv = $this->parseLinhasChaveValor($bloco);
            if (($kv['codigo'] ?? '') === '' && ($kv['nome'] ?? '') === '') {
                continue;
            }
            $config = [
                'expressao' => trim((string) ($kv['expressao'] ?? '')),
                'formula_mode' => trim((string) ($kv['formula_mode'] ?? 'single')) ?: 'single',
            ];
            if (isset($kv['tipo_avaliacao_id']) && (int) $kv['tipo_avaliacao_id'] > 0) {
                $config['tipo_avaliacao_id'] = (int) $kv['tipo_avaliacao_id'];
            }
            if (!empty($kv['tipo_avaliacao_nome'])) {
                $config['tipo_avaliacao_nome'] = trim((string) $kv['tipo_avaliacao_nome']);
            }
            if (!empty($kv['jornada_ids'])) {
                $config['jornada_ids'] = $this->parseListaIdsReceita($kv['jornada_ids']);
            }
            if (!empty($kv['group_line_enabled'])) {
                $config['group_line'] = [
                    'enabled' => true,
                    'key' => trim((string) ($kv['group_line_key'] ?? '')),
                    'label' => trim((string) ($kv['group_line_label'] ?? '')),
                    'mode' => trim((string) ($kv['group_line_mode'] ?? 'media')) ?: 'media',
                    'divisor' => 1,
                    'materias_ids' => $this->parseListaIdsReceita($kv['group_line_materias_ids'] ?? '[]'),
                ];
            }
            $componentes[] = [
                'nome' => trim((string) ($kv['nome'] ?? '')),
                'codigo' => trim((string) ($kv['codigo'] ?? '')),
                'source_type' => trim((string) ($kv['source_type'] ?? 'provas_sistema')),
                'calc_type' => trim((string) ($kv['calc_type'] ?? 'media')),
                'peso' => isset($kv['peso']) ? (float) $kv['peso'] : 1.0,
                'filtro_titulo' => trim((string) ($kv['filtro_titulo'] ?? '')),
                'blocos_ids' => $this->parseListaIdsReceita($kv['blocos_ids'] ?? '[]'),
                'materias_ids' => $this->parseListaIdsReceita($kv['materias_ids'] ?? '[]'),
                'materia_unica' => !empty($kv['materia_unica']) ? 1 : 0,
                'usar_percentual' => !empty($kv['usar_percentual']) ? 1 : 0,
                'escala_max' => isset($kv['escala_max']) ? (float) $kv['escala_max'] : 10.0,
                'obrigatorio' => !empty($kv['obrigatorio']) ? 1 : 0,
                'tipo_avaliacao_id' => isset($kv['tipo_avaliacao_id']) ? (int) $kv['tipo_avaliacao_id'] : null,
                'tipo_avaliacao_nome' => trim((string) ($kv['tipo_avaliacao_nome'] ?? '')),
                'config' => $config,
            ];
        }

        $rascunho = [
            'modo' => 'criar',
            'nome' => trim((string) ($meta['nome'] ?? 'Evento importado')),
            'codigo' => trim((string) ($meta['codigo'] ?? '')),
            'descricao_curta' => trim((string) ($meta['descricao_curta'] ?? '')),
            'ano_letivo' => isset($meta['ano_letivo']) && $meta['ano_letivo'] !== '' ? (int) $meta['ano_letivo'] : null,
            'bimestre' => isset($meta['bimestre']) && $meta['bimestre'] !== '' ? (int) $meta['bimestre'] : null,
            'exibir_em' => trim((string) ($meta['exibir_em'] ?? 'boletim')) ?: 'boletim',
            'turmas_ids' => $this->parseListaIdsReceita($meta['turmas_ids'] ?? '[]'),
            'materias_ids' => $this->parseListaIdsReceita($meta['materias_ids'] ?? '[]'),
            'series_ids' => $this->parseListaIdsReceita($meta['series_ids'] ?? '[]'),
            'round_mode' => trim((string) ($meta['round_mode'] ?? 'none')) ?: 'none',
            'nota_minima_aprovacao' => isset($meta['nota_minima_aprovacao']) && $meta['nota_minima_aprovacao'] !== ''
                ? (float) $meta['nota_minima_aprovacao']
                : null,
            'formula_final' => trim((string) ($meta['formula_final'] ?? '')),
            'default_data_inicio' => trim((string) ($meta['default_data_inicio'] ?? '')),
            'default_data_fim' => trim((string) ($meta['default_data_fim'] ?? '')),
            'componentes' => $componentes,
        ];

        $validado = $this->validarEEnriquecerRascunho($rascunho);
        $avisoParts = [];
        if (!empty($validado['erros']) && is_array($validado['erros'])) {
            $avisoParts[] = implode(' ', $validado['erros']);
        }
        foreach ($validado['rascunho']['componentes'] as $c) {
            if (($c['source_type'] ?? '') === 'calculado' && trim((string) ($c['config']['expressao'] ?? '')) === '') {
                $avisoParts[] = 'Há bloco(s) calculado(s) sem expressao. Complete a fórmula (ex.: (c1 + c2) / 2).';
                break;
            }
        }

        return [
            'ok' => $validado['rascunho']['componentes'] !== [],
            'rascunho' => $validado['rascunho'],
            'aviso' => implode(' ', array_filter($avisoParts)),
        ];
    }

    /**
     * @return array{ok:bool,rascunho?:array,aviso?:string}
     */
    private function parseReceitaMarkdownSolta(string $texto): array
    {
        $meta = [];
        if (preg_match('/-\s*Nome\s*:\s*(.+)$/mi', $texto, $m)) {
            $meta['nome'] = trim($m[1]);
        }
        if (preg_match('/-\s*C[oó]digo\s*:\s*(.+)$/mi', $texto, $m)) {
            $meta['codigo'] = trim($m[1]);
        }
        if (preg_match('/-\s*Ano Letivo\s*:\s*(\d+)/mi', $texto, $m)) {
            $meta['ano_letivo'] = (int) $m[1];
        }
        if (preg_match('/-\s*Bimestre\s*:\s*(\d+)/mi', $texto, $m)) {
            $meta['bimestre'] = (int) $m[1];
        }
        if (preg_match('/-\s*Exibir em\s*:\s*(\w+)/mi', $texto, $m)) {
            $meta['exibir_em'] = strtolower(trim($m[1]));
        }
        if (preg_match('/-\s*Turmas IDs\s*:\s*(\[[^\]]*\])/mi', $texto, $m)) {
            $meta['turmas_ids'] = $m[1];
        }
        if (preg_match('/-\s*Mat[eé]rias IDs\s*:\s*(\[[^\]]*\])/mi', $texto, $m)) {
            $meta['materias_ids'] = $m[1];
        }
        if (preg_match('/-\s*S[eé]ries IDs\s*:\s*(\[[^\]]*\])/mi', $texto, $m)) {
            $meta['series_ids'] = $m[1];
        }
        if (preg_match('/-\s*Modo de Arredondamento\s*:\s*(\w+)/mi', $texto, $m)) {
            $meta['round_mode'] = strtolower(trim($m[1]));
        }
        if (preg_match('/-\s*Nota M[ií]nima[^\n:]*:\s*([0-9.,]+)/mi', $texto, $m)) {
            $meta['nota_minima_aprovacao'] = str_replace(',', '.', $m[1]);
        }

        $componentes = [];
        if (preg_match_all(
            '/\d+\.\s*\*\*([^*]+)\*\*\s*((?:.|\n)*?)(?=\n\s*\d+\.\s*\*\*|\z)/u',
            $texto,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $i => $match) {
                $bloco = $match[0];
                $nome = trim($match[1]);
                $codigo = 'c' . ($i + 1);
                if (preg_match('/C[oó]digo\s*:\s*([^\s\n]+)/ui', $bloco, $cm)) {
                    $codigo = trim($cm[1]);
                }
                $source = 'provas_sistema';
                if (preg_match('/Tipo de Avalia[cç][aã]o\s*:\s*([^\s\n]+)/ui', $bloco, $sm)) {
                    $source = strtolower(trim($sm[1]));
                }
                $calc = 'media';
                if (preg_match('/Tipo de C[aá]lculo\s*:\s*([^\s\n]+)/ui', $bloco, $cam)) {
                    $calc = strtolower(trim($cam[1]));
                }
                $blocosIds = [];
                if (preg_match('/Blocos IDs\s*:\s*(\[[^\]]*\])/ui', $bloco, $bm)) {
                    $blocosIds = $this->parseListaIdsReceita($bm[1]);
                }
                $mats = [];
                if (preg_match('/Mat[eé]rias IDs\s*:\s*(\[[^\]]*\])/ui', $bloco, $mm)) {
                    $mats = $this->parseListaIdsReceita($mm[1]);
                }
                $expressao = '';
                if (preg_match('/[Ee]xpress[aã]o\s*:\s*(.+)$/mui', $bloco, $em)) {
                    $expressao = trim($em[1]);
                }
                $componentes[] = [
                    'nome' => $nome,
                    'codigo' => $codigo,
                    'source_type' => $source,
                    'calc_type' => $calc,
                    'blocos_ids' => $blocosIds,
                    'materias_ids' => $mats,
                    'usar_percentual' => $source === 'provas_sistema' ? 1 : 0,
                    'config' => [
                        'expressao' => $expressao,
                        'formula_mode' => 'single',
                    ],
                ];
            }
        }

        $rascunho = [
            'modo' => 'criar',
            'nome' => $meta['nome'] ?? 'Evento importado',
            'codigo' => $meta['codigo'] ?? '',
            'ano_letivo' => $meta['ano_letivo'] ?? null,
            'bimestre' => $meta['bimestre'] ?? null,
            'exibir_em' => $meta['exibir_em'] ?? 'boletim',
            'turmas_ids' => $this->parseListaIdsReceita($meta['turmas_ids'] ?? '[]'),
            'materias_ids' => $this->parseListaIdsReceita($meta['materias_ids'] ?? '[]'),
            'series_ids' => $this->parseListaIdsReceita($meta['series_ids'] ?? '[]'),
            'round_mode' => $meta['round_mode'] ?? 'none',
            'nota_minima_aprovacao' => isset($meta['nota_minima_aprovacao']) ? (float) $meta['nota_minima_aprovacao'] : null,
            'formula_final' => '',
            'componentes' => $componentes,
        ];

        $validado = $this->validarEEnriquecerRascunho($rascunho);
        $faltam = [];
        foreach ($validado['rascunho']['componentes'] as $c) {
            if (($c['source_type'] ?? '') === 'calculado' && trim((string) ($c['config']['expressao'] ?? '')) === '') {
                $faltam[] = (string) ($c['codigo'] ?? $c['nome'] ?? 'calculado');
            }
        }
        $avisoParts = [];
        if (!empty($validado['erros']) && is_array($validado['erros'])) {
            $avisoParts[] = implode(' ', $validado['erros']);
        }
        $avisoParts[] = $faltam !== []
            ? 'Receita parcialmente importada. Falta expressao nos calculados: ' . implode(', ', $faltam)
                . '. Ex.: Média Parcial → expressao: (c1 + c2) / 2. Use "Copiar receita" para o formato completo.'
            : 'Receita markdown importada. Prefira o botão "Copiar receita" da próxima vez.';

        return [
            'ok' => $validado['rascunho']['componentes'] !== [],
            'rascunho' => $validado['rascunho'],
            'aviso' => implode(' ', array_filter($avisoParts)),
        ];
    }

    /** @return array<string,string> */
    private function parseLinhasChaveValor(string $texto): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $texto) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '[[')) {
                continue;
            }
            if (!preg_match('/^([a-zA-Z0-9_]+)\s*:\s*(.*)$/', $line, $m)) {
                continue;
            }
            $out[$m[1]] = trim($m[2]);
        }
        return $out;
    }

    private function formatarListaIdsReceita($raw): string
    {
        $ids = $this->normalizarIds(is_array($raw) ? $raw : $this->parseListaIdsReceita((string) $raw));
        return '[' . implode(', ', $ids) . ']';
    }

    /** @return list<int> */
    private function parseListaIdsReceita($raw): array
    {
        if (is_array($raw)) {
            return $this->normalizarIds($raw);
        }
        $s = trim((string) $raw);
        if ($s === '' || $s === '[]') {
            return [];
        }
        if (isset($s[0]) && $s[0] === '[') {
            $dec = json_decode($s, true);
            return is_array($dec) ? $this->normalizarIds($dec) : [];
        }
        return $this->normalizarIds(preg_split('/[,\s]+/', $s) ?: []);
    }

    private function escaparReceitaValor($v): string
    {
        $s = trim((string) $v);
        return str_replace(["\n", "\r"], ' ', $s);
    }

    /**
     * Catálogo compacto para o system prompt (evita estourar tokens).
     */
    public function montarContextoCatalogo(): array
    {
        return [
            'tipos_avaliacao' => $this->listarTiposAvaliacao(),
            'turmas' => array_slice($this->listarTurmas(200), 0, 80),
            'materias' => array_slice($this->listarMaterias(200), 0, 80),
            'regras_existentes' => array_slice($this->listarRegras(50), 0, 40),
            'eventos_prova_recentes' => array_slice($this->listarEventosProva(null, 40), 0, 40),
        ];
    }

    private function normalizarRegraParaAssistente(array $regra): array
    {
        $componentes = [];
        foreach ((array) ($regra['componentes'] ?? []) as $c) {
            $blocos = [];
            if (!empty($c['blocos_ids'])) {
                $blocos = $this->normalizarIds(is_array($c['blocos_ids']) ? $c['blocos_ids'] : explode(',', (string) $c['blocos_ids']));
            } elseif (!empty($c['bloco_id'])) {
                $blocos = [(int) $c['bloco_id']];
            }
            $config = [];
            if (!empty($c['config_json'])) {
                $dec = json_decode((string) $c['config_json'], true);
                if (is_array($dec)) {
                    $config = $dec;
                }
            }
            $componentes[] = [
                'codigo' => (string) ($c['codigo'] ?? ''),
                'nome' => (string) ($c['nome'] ?? ''),
                'source_type' => (string) ($c['source_type'] ?? 'provas_sistema'),
                'calc_type' => (string) ($c['calc_type'] ?? 'media'),
                'filtro_titulo' => (string) ($c['filtro_titulo'] ?? ''),
                'blocos_ids' => $blocos,
                'materias_ids' => $this->normalizarIds(
                    !empty($c['materias_ids'])
                        ? (is_array($c['materias_ids']) ? $c['materias_ids'] : json_decode((string) $c['materias_ids'], true))
                        : []
                ),
                'config' => $config,
                'tipo_avaliacao_id' => isset($config['tipo_avaliacao_id']) ? (int) $config['tipo_avaliacao_id'] : null,
            ];
        }

        return [
            'id' => (int) ($regra['id'] ?? 0),
            'nome' => (string) ($regra['nome'] ?? ''),
            'codigo' => (string) ($regra['codigo'] ?? ''),
            'formula_final' => (string) ($regra['formula_final'] ?? ''),
            'exibir_em' => (string) ($regra['exibir_em'] ?? 'boletim'),
            'ano_letivo' => isset($regra['ano_letivo']) ? (int) $regra['ano_letivo'] : null,
            'bimestre' => isset($regra['bimestre']) ? (int) $regra['bimestre'] : null,
            'default_data_inicio' => (string) ($regra['default_data_inicio'] ?? ''),
            'default_data_fim' => (string) ($regra['default_data_fim'] ?? ''),
            'turmas_ids' => $this->decodeIdsJson($regra['turmas_ids'] ?? null),
            'materias_ids' => $this->decodeIdsJson($regra['materias_ids'] ?? null),
            'series_ids' => $this->decodeIdsJson($regra['series_ids'] ?? null),
            'round_mode' => (string) ($regra['round_mode'] ?? 'none'),
            'nota_minima_aprovacao' => isset($regra['nota_minima_aprovacao']) ? (float) $regra['nota_minima_aprovacao'] : null,
            'componentes' => $componentes,
        ];
    }

    /** @return list<int> */
    private function decodeIdsJson($raw): array
    {
        if (is_array($raw)) {
            return $this->normalizarIds($raw);
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $dec = json_decode($raw, true);
        return is_array($dec) ? $this->normalizarIds($dec) : [];
    }

    /** @return list<int> */
    private function normalizarIds($raw): array
    {
        if (!is_array($raw)) {
            if (is_string($raw) && $raw !== '') {
                $raw = preg_split('/[,\s]+/', $raw) ?: [];
            } else {
                return [];
            }
        }
        $ids = [];
        foreach ($raw as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function slugCodigo(string $codigo): string
    {
        $codigo = trim(mb_strtolower($codigo));
        $codigo = preg_replace('/[^a-z0-9_]+/', '_', $codigo) ?? '';
        return trim($codigo, '_');
    }

    private function extrairPalavraFiltro(string $tipoNome): string
    {
        $t = mb_strtolower($tipoNome);
        foreach (['semanal', 'bimestral', 'enac', 'trabalho', 'simulado', 'recuperacao', 'recuperação'] as $p) {
            if (str_contains($t, $p)) {
                return str_replace('ç', 'c', $p);
            }
        }
        $parts = preg_split('/\s+/', $t) ?: [];
        return $parts[count($parts) - 1] ?? $t;
    }

    private function normalizarData($v): ?string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }
        return null;
    }

    private function temColuna(string $table, string $column): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1",
                ['t' => $table, 'c' => $column]
            );
            return !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function temTabela(string $table): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1",
                ['t' => $table]
            );
            return !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }
}
