<?php
/**
 * Tools de grounding do Assistente de Boletim (catálogos do tenant).
 * Usado pelo chat in-app e pelo MCP (mesma superfície).
 */

require_once __DIR__ . '/../Models/System/BoletimConfig.php';
require_once __DIR__ . '/../Helpers/BoletimQuadroLayoutHelper.php';

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
            $chave = isset($row['chave_quadro']) ? strtolower(trim((string) $row['chave_quadro'])) : '';
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($row['nome'] ?? '')),
                'descricao' => isset($row['descricao']) ? trim((string) $row['descricao']) : null,
                'chave_quadro' => $chave !== '' ? $chave : null,
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
     * @return list<array{id:int,nome:string,turma_nome:?string}>
     */
    public function listarAlunos(int $limit = 300): array
    {
        $out = [];
        foreach ($this->boletimConfig->getStudentsList($limit) as $aluno) {
            $id = (int) ($aluno['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($aluno['nome'] ?? '')),
                'turma_nome' => isset($aluno['turma_nome']) ? trim((string) $aluno['turma_nome']) : null,
            ];
        }
        return $out;
    }

    /**
     * Lista jornadas candidatas para o escopo do boletim (compacto).
     *
     * @return list<array{id:int,nome:string,materia_nome:?string,bimestre:?int,ano_letivo:?int}>
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
                'nome' => trim((string) ($j['titulo'] ?? $j['nome'] ?? ('Jornada #' . $id))),
                'materia_nome' => isset($j['materia_nome']) ? trim((string) $j['materia_nome']) : null,
                'bimestre' => isset($j['bimestre']) && $j['bimestre'] !== '' && $j['bimestre'] !== null
                    ? (int) $j['bimestre']
                    : null,
                'ano_letivo' => isset($j['ano_letivo']) && $j['ano_letivo'] !== '' && $j['ano_letivo'] !== null
                    ? (int) $j['ano_letivo']
                    : null,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param list<int> $bimestres
     * @return list<int>
     */
    public function resolverIdsJornadaPorBimestre(array $bimestres, int $anoLetivo = 0): array
    {
        $bims = array_values(array_unique(array_filter(array_map('intval', $bimestres), static fn ($b) => $b >= 1 && $b <= 4)));
        if ($bims === []) {
            return [];
        }
        $ids = [];
        foreach ($this->listarJornadas(300) as $j) {
            $jb = (int) ($j['bimestre'] ?? 0);
            if (!in_array($jb, $bims, true)) {
                continue;
            }
            $ja = (int) ($j['ano_letivo'] ?? 0);
            if ($anoLetivo > 0 && $ja > 0 && $ja !== $anoLetivo) {
                continue;
            }
            $id = (int) ($j['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @return list<array{id:int,nome:string,codigo:?string,ano_letivo:?int,bimestre:?int,exibir_em:?string,formula_final:?string}>
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
                'formula_final' => isset($regra['formula_final']) ? trim((string) $regra['formula_final']) : null,
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

    public function obterRegraPorCodigo(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        $regra = $this->boletimConfig->getRuleByCode($codigo);
        if (!$regra) {
            return null;
        }
        return $this->normalizarRegraParaAssistente($regra);
    }

    /**
     * @return list<array{id:int,nome:string,bimestre:?int,bimestre_rotulo:?string,ano_letivo:?int}>
     */
    public function listarFaltasEventos(int $limit = 300): array
    {
        if (!class_exists('SchoolAbsence', false)) {
            require_once __DIR__ . '/../Models/Education/SchoolAbsence.php';
        }
        $out = [];
        foreach ((new SchoolAbsence())->listEventos($limit) as $ev) {
            $id = (int) ($ev['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rotulo = trim((string) ($ev['bimestre'] ?? ''));
            $bim = 0;
            if (preg_match('/([1-4])/', $rotulo, $m)) {
                $bim = (int) $m[1];
            }
            $out[] = [
                'id' => $id,
                'nome' => trim((string) ($ev['nome'] ?? '')),
                'bimestre' => $bim > 0 ? $bim : null,
                'bimestre_rotulo' => $rotulo !== '' ? $rotulo : null,
                'ano_letivo' => isset($ev['ano_letivo']) ? (int) $ev['ano_letivo'] : null,
            ];
        }

        return $out;
    }

    public function obterFaltasEvento(int $eventoId): ?array
    {
        if ($eventoId <= 0) {
            return null;
        }
        if (!class_exists('SchoolAbsence', false)) {
            require_once __DIR__ . '/../Models/Education/SchoolAbsence.php';
        }
        $ev = (new SchoolAbsence())->getEventoById($eventoId);
        if (!$ev) {
            return null;
        }
        $rotulo = trim((string) ($ev['bimestre'] ?? ''));
        $bim = 0;
        if (preg_match('/([1-4])/', $rotulo, $m)) {
            $bim = (int) $m[1];
        }

        return [
            'id' => (int) ($ev['id'] ?? 0),
            'nome' => trim((string) ($ev['nome'] ?? '')),
            'bimestre' => $bim > 0 ? $bim : null,
            'bimestre_rotulo' => $rotulo !== '' ? $rotulo : null,
            'ano_letivo' => isset($ev['ano_letivo']) ? (int) $ev['ano_letivo'] : null,
        ];
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
        $selectChave = ($temTipo && $this->temColuna('provas_tipos_avaliacao', 'chave_quadro'))
            ? 'pta.chave_quadro'
            : 'NULL AS chave_quadro';
        $selectSemana = $this->temColuna('provas_blocos', 'semana') ? 'pb.semana' : 'NULL AS semana';
        $joinTipo = $temTipo
            ? 'LEFT JOIN provas_tipos_avaliacao pta ON pta.id = pb.tipo_avaliacao_id AND pta.deleted_at IS NULL'
            : '';

        $sql = "SELECT pb.id, pb.titulo, pb.data_prova, pb.ano_letivo, pb.bimestre,
                       {$selectTipoId}, {$selectTipoNome}, {$selectChave}, {$selectSemana}
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
            $semanaEv = isset($row['semana']) ? (int) $row['semana'] : 0;
            $chaveEv = isset($row['chave_quadro']) ? strtolower(trim((string) $row['chave_quadro'])) : '';
            $out[] = [
                'id' => $id,
                'titulo' => trim((string) ($row['titulo'] ?? '')),
                'tipo_avaliacao_id' => isset($row['tipo_avaliacao_id']) ? (int) $row['tipo_avaliacao_id'] : null,
                'tipo_avaliacao_nome' => isset($row['tipo_avaliacao_nome']) ? trim((string) $row['tipo_avaliacao_nome']) : null,
                'chave_quadro' => $chaveEv !== '' ? $chaveEv : null,
                'semana' => ($semanaEv >= 1 && $semanaEv <= 8) ? $semanaEv : null,
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
     * @param mixed $tipoAvaliacaoIdOuNome
     * @param list<int> $bimestres
     * @return array{tipo:?array{id:int,nome:string},blocos_ids:list<int>,eventos:list<array>}
     */
    public function resolverBlocosPorTipo(
        $tipoAvaliacaoIdOuNome,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        int $limit = 100,
        ?int $semana = null,
        array $bimestres = []
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
        $semanaFiltro = ($semana !== null && $semana >= 1 && $semana <= 8) ? $semana : 0;
        if ($semanaFiltro > 0) {
            $eventos = array_values(array_filter($eventos, static function ($ev) use ($semanaFiltro) {
                return (int) ($ev['semana'] ?? 0) === $semanaFiltro;
            }));
        }
        $bims = [];
        foreach ($bimestres as $b) {
            $n = (int) $b;
            if ($n >= 1 && $n <= 4 && !in_array($n, $bims, true)) {
                $bims[] = $n;
            }
        }
        if ($bims !== []) {
            $eventos = array_values(array_filter($eventos, static function ($ev) use ($bims) {
                $bimEv = (int) ($ev['bimestre'] ?? 0);
                if ($bimEv <= 0) {
                    return true;
                }
                return in_array($bimEv, $bims, true);
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
            $layoutGroup = strtolower(trim((string) ($config['layout_group'] ?? $comp['layout_group'] ?? '')));
            $layoutType = strtolower(trim((string) ($config['layout_type'] ?? $comp['layout_type'] ?? '')));
            if (in_array($layoutGroup, BoletimQuadroLayoutHelper::gruposPermitidos(), true)) {
                $config['layout_group'] = $layoutGroup;
            } else {
                unset($config['layout_group']);
            }
            if (in_array($layoutType, BoletimQuadroLayoutHelper::tiposPermitidos(), true)) {
                $config['layout_type'] = $layoutType;
            } else {
                unset($config['layout_type']);
            }
            $semanaComp = (int) ($config['semana'] ?? $comp['semana'] ?? 0);
            if ($semanaComp >= 1 && $semanaComp <= 8) {
                $config['semana'] = $semanaComp;
            } else {
                unset($config['semana']);
                $semanaComp = 0;
            }
            $agregarNq = $this->normalizarCodigosAgregarNq($config['agregar_nq'] ?? $comp['agregar_nq'] ?? []);
            if ($agregarNq !== []) {
                $config['agregar_nq'] = $agregarNq;
            } else {
                unset($config['agregar_nq']);
            }

            $blocosIds = $this->normalizarIds($comp['blocos_ids'] ?? []);
            $tipoId = isset($comp['tipo_avaliacao_id']) ? (int) $comp['tipo_avaliacao_id'] : 0;
            $tipoNome = trim((string) ($comp['tipo_avaliacao_nome'] ?? $config['tipo_avaliacao_nome'] ?? ''));
            if ($tipoId <= 0 && isset($config['tipo_avaliacao_id'])) {
                $tipoId = (int) $config['tipo_avaliacao_id'];
            }
            if ($blocosIds === [] && ($tipoId > 0 || $tipoNome !== '')) {
                $bimsResolve = [];
                foreach ((array) ($config['prova_bimestres'] ?? []) as $b) {
                    $n = (int) $b;
                    if ($n >= 1 && $n <= 4 && !in_array($n, $bimsResolve, true)) {
                        $bimsResolve[] = $n;
                    }
                }
                $resolvido = $this->resolverBlocosPorTipo(
                    $tipoId > 0 ? $tipoId : $tipoNome,
                    $dataIni,
                    $dataFim,
                    100,
                    $semanaComp > 0 ? $semanaComp : null,
                    $bimsResolve
                );
                if ($resolvido['tipo'] !== null) {
                    $tipoId = (int) $resolvido['tipo']['id'];
                    $tipoNome = $resolvido['tipo']['nome'];
                    $blocosIds = $resolvido['blocos_ids'];
                    $config['tipo_avaliacao_id'] = $tipoId;
                    $config['tipo_avaliacao_nome'] = $tipoNome;
                }
            }

            if ($source === 'evento_boletim') {
                $slugEv = trim((string) ($config['regra_codigo'] ?? $comp['regra_codigo'] ?? ''));
                $config['regra_codigo'] = $slugEv;
                $config['componente_codigo'] = trim((string) ($config['componente_codigo'] ?? $comp['componente_codigo'] ?? ''));
                if ($slugEv === '') {
                    $erros[] = "Bloco \"{$codigo}\" precisa do código do evento de Notas (média final já criada).";
                } elseif ($this->obterRegraPorCodigo($slugEv) === null) {
                    $erros[] = "Bloco \"{$codigo}\" aponta para evento inexistente ({$slugEv}).";
                }
            }

            if ($source === 'faltas_evento') {
                $evFaltas = (int) ($config['faltas_evento_id'] ?? $comp['faltas_evento_id'] ?? 0);
                $config['faltas_evento_id'] = $evFaltas;
                if ($evFaltas <= 0) {
                    $erros[] = "Bloco \"{$codigo}\" precisa do evento de faltas.";
                } elseif ($this->obterFaltasEvento($evFaltas) === null) {
                    $erros[] = "Bloco \"{$codigo}\" aponta para evento de faltas inexistente.";
                }
            }

            if ($source === 'calculado') {
                $exp = trim((string) ($config['expressao'] ?? $comp['expressao'] ?? ''));
                $config['expressao'] = $exp;
                $modeFm = strtolower(trim((string) ($config['formula_mode'] ?? 'single')));
                $config['formula_mode'] = $modeFm === 'per_materia' ? 'per_materia' : 'single';
                if ($exp === '' && $agregarNq === []) {
                    $erros[] = "Bloco calculado \"{$codigo}\" precisa de config.expressao (ex.: (semanal + bimestral) / 2) ou config.agregar_nq (média por acertos/questões das semanas).";
                }
            }

            if ($source === 'jornadas') {
                $config['jornada_ids'] = $this->normalizarIds($config['jornada_ids'] ?? $comp['jornada_ids'] ?? []);
                $bimsCfg = [];
                foreach ((array) ($config['jornada_bimestres'] ?? []) as $b) {
                    $b = (int) $b;
                    if ($b >= 1 && $b <= 4) {
                        $bimsCfg[] = $b;
                    }
                }
                if ($bimsCfg !== []) {
                    $config['jornada_bimestres'] = array_values(array_unique($bimsCfg));
                    if ($config['jornada_ids'] === []) {
                        $config['jornada_ids'] = $this->resolverIdsJornadaPorBimestre(
                            $config['jornada_bimestres'],
                            (int) ($rascunho['ano_letivo'] ?? 0)
                        );
                        if ($config['jornada_ids'] === []) {
                            $erros[] = "Nenhuma jornada encontrada para o(s) bimestre(s) do bloco \"{$codigo}\".";
                        }
                    }
                } else {
                    unset($config['jornada_bimestres']);
                }
                $config['faixas_percentuais'] = $this->normalizarFaixasPercentuaisJornada(
                    $config['faixas_percentuais'] ?? []
                );
                if ($config['faixas_percentuais'] === []) {
                    unset($config['faixas_percentuais']);
                }
            }

            if ($source === 'provas_sistema') {
                $bimsProva = [];
                foreach ((array) ($config['prova_bimestres'] ?? []) as $b) {
                    $n = (int) $b;
                    if ($n >= 1 && $n <= 4) {
                        $bimsProva[] = $n;
                    }
                }
                if ($bimsProva !== []) {
                    $config['prova_bimestres'] = array_values(array_unique($bimsProva));
                } else {
                    unset($config['prova_bimestres']);
                }
            }

            $filtroTitulo = trim((string) ($comp['filtro_titulo'] ?? ''));
            // Se resolveu por tipo/blocos, não força filtro de título
            if ($blocosIds !== [] || $semanaComp > 0) {
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
            $semanaRec = (int) ($cfg['semana'] ?? $c['semana'] ?? 0);
            if ($semanaRec >= 1 && $semanaRec <= 8) {
                $lines[] = 'semana: ' . $semanaRec;
            }
            $lgRec = trim((string) ($cfg['layout_group'] ?? $c['layout_group'] ?? ''));
            $ltRec = trim((string) ($cfg['layout_type'] ?? $c['layout_type'] ?? ''));
            if ($lgRec !== '') {
                $lines[] = 'layout_group: ' . $this->escaparReceitaValor($lgRec);
            }
            if ($ltRec !== '') {
                $lines[] = 'layout_type: ' . $this->escaparReceitaValor($ltRec);
            }
            $agRec = $this->normalizarCodigosAgregarNq($cfg['agregar_nq'] ?? []);
            if ($agRec !== []) {
                $lines[] = 'agregar_nq: [' . implode(', ', $agRec) . ']';
            }
            $regraCodRec = trim((string) ($cfg['regra_codigo'] ?? $c['regra_codigo'] ?? ''));
            if ($regraCodRec !== '') {
                $lines[] = 'regra_codigo: ' . $this->escaparReceitaValor($regraCodRec);
            }
            $compCodRec = trim((string) ($cfg['componente_codigo'] ?? $c['componente_codigo'] ?? ''));
            if ($compCodRec !== '') {
                $lines[] = 'componente_codigo: ' . $this->escaparReceitaValor($compCodRec);
            }
            $faltasIdRec = (int) ($cfg['faltas_evento_id'] ?? $c['faltas_evento_id'] ?? 0);
            if ($faltasIdRec > 0) {
                $lines[] = 'faltas_evento_id: ' . $faltasIdRec;
            }
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
            $semanaKv = (int) ($kv['semana'] ?? 0);
            if ($semanaKv >= 1 && $semanaKv <= 8) {
                $config['semana'] = $semanaKv;
            }
            $lgKv = strtolower(trim((string) ($kv['layout_group'] ?? '')));
            $ltKv = strtolower(trim((string) ($kv['layout_type'] ?? '')));
            if (in_array($lgKv, BoletimQuadroLayoutHelper::gruposPermitidos(), true)) {
                $config['layout_group'] = $lgKv;
            }
            if (in_array($ltKv, BoletimQuadroLayoutHelper::tiposPermitidos(), true)) {
                $config['layout_type'] = $ltKv;
            }
            $agKv = $this->normalizarCodigosAgregarNq($kv['agregar_nq'] ?? []);
            if ($agKv !== []) {
                $config['agregar_nq'] = $agKv;
            }
            $regraCodKv = trim((string) ($kv['regra_codigo'] ?? ''));
            if ($regraCodKv !== '') {
                $config['regra_codigo'] = $regraCodKv;
            }
            $compCodKv = trim((string) ($kv['componente_codigo'] ?? ''));
            if ($compCodKv !== '') {
                $config['componente_codigo'] = $compCodKv;
            }
            $faltasIdKv = (int) ($kv['faltas_evento_id'] ?? 0);
            if ($faltasIdKv > 0) {
                $config['faltas_evento_id'] = $faltasIdKv;
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
        $tipos = [];
        $turmas = [];
        $materias = [];
        $regras = [];
        $eventos = [];
        $quadro = [
            'semanas_com_evento' => [],
            'tipos_por_chave' => [],
            'grupos_materias' => ['A' => [], 'B' => []],
            'dica' => '',
        ];
        try {
            $tipos = $this->listarTiposAvaliacao();
        } catch (Throwable $e) {
            error_log('BoletimAssistenteFerramentas catalogo tipos: ' . $e->getMessage());
        }
        try {
            $turmas = array_slice($this->listarTurmas(200), 0, 80);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteFerramentas catalogo turmas: ' . $e->getMessage());
        }
        try {
            $materias = array_slice($this->listarMaterias(200), 0, 80);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteFerramentas catalogo materias: ' . $e->getMessage());
        }
        try {
            $regras = array_slice($this->listarRegras(50), 0, 40);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteFerramentas catalogo regras: ' . $e->getMessage());
        }
        try {
            $eventos = array_slice($this->listarEventosProva(null, 40), 0, 40);
        } catch (Throwable $e) {
            error_log('BoletimAssistenteFerramentas catalogo eventos: ' . $e->getMessage());
        }
        try {
            $quadro = $this->montarContextoQuadroSemanal();
        } catch (Throwable $e) {
            error_log('BoletimAssistenteFerramentas catalogo quadro: ' . $e->getMessage());
        }
        $jornadasCat = [];
        try {
            foreach (array_slice($this->listarJornadas(80), 0, 80) as $j) {
                $jornadasCat[] = [
                    'id' => (int) ($j['id'] ?? 0),
                    'nome' => (string) ($j['nome'] ?? ''),
                    'bimestre' => isset($j['bimestre']) ? (int) $j['bimestre'] : null,
                    'ano_letivo' => isset($j['ano_letivo']) ? (int) $j['ano_letivo'] : null,
                ];
            }
        } catch (Throwable $e) {
            error_log('BoletimAssistenteFerramentas catalogo jornadas: ' . $e->getMessage());
        }

        $faltasEventos = [];
        try {
            foreach ($this->listarFaltasEventos(80) as $fe) {
                $faltasEventos[] = [
                    'id' => (int) ($fe['id'] ?? 0),
                    'nome' => (string) ($fe['nome'] ?? ''),
                    'bimestre' => isset($fe['bimestre']) ? (int) $fe['bimestre'] : null,
                    'ano_letivo' => isset($fe['ano_letivo']) ? (int) $fe['ano_letivo'] : null,
                ];
            }
        } catch (Throwable $e) {
            error_log('BoletimAssistenteFerramentas catalogo faltas: ' . $e->getMessage());
        }

        return [
            'tipos_avaliacao' => $tipos,
            'turmas' => $turmas,
            'materias' => $materias,
            'regras_existentes' => $regras,
            'eventos_prova_recentes' => $eventos,
            'quadro_semanal' => $quadro,
            'jornadas' => $jornadasCat,
            'faltas_eventos' => $faltasEventos,
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
                'materias_ids' => $this->decodeIdsJson($c['materias_ids'] ?? null),
                'materia_unica' => !empty($c['materia_unica']) ? 1 : 0,
                'usar_percentual' => !empty($c['usar_percentual']) ? 1 : 0,
                'escala_max' => isset($c['escala_max']) ? (float) $c['escala_max'] : 10.0,
                'obrigatorio' => !empty($c['obrigatorio']) ? 1 : 0,
                'config' => $config,
                'tipo_avaliacao_id' => isset($config['tipo_avaliacao_id']) ? (int) $config['tipo_avaliacao_id'] : null,
                'tipo_avaliacao_nome' => isset($config['tipo_avaliacao_nome']) ? (string) $config['tipo_avaliacao_nome'] : null,
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
        $raw = trim($raw);
        $dec = json_decode($raw, true);
        if (is_array($dec)) {
            return $this->normalizarIds($dec);
        }
        if (preg_match('/^\d+(?:\s*,\s*\d+)*$/', $raw)) {
            return $this->normalizarIds(explode(',', $raw));
        }
        if (ctype_digit($raw)) {
            return [(int) $raw];
        }
        return [];
    }

    /**
     * Aceita lista [{percentual_min, nota}] ou mapa { "90": 10 }.
     *
     * @param mixed $raw
     * @return list<array{percentual_min:int,nota:float}>
     */
    private function normalizarFaixasPercentuaisJornada($raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return [];
        }
        $out = [];
        $isLista = array_is_list($raw) || (isset($raw[0]) && is_array($raw[0]));
        if ($isLista) {
            foreach ($raw as $fx) {
                if (!is_array($fx)) {
                    continue;
                }
                $p = (int) ($fx['percentual_min'] ?? 0);
                if ($p < 0 || $p > 100 || !is_numeric($fx['nota'] ?? null)) {
                    continue;
                }
                $out[] = ['percentual_min' => $p, 'nota' => max(0.0, (float) $fx['nota'])];
            }
        } else {
            foreach ($raw as $k => $v) {
                $p = (int) $k;
                if ($p < 0 || $p > 100 || !is_numeric($v)) {
                    continue;
                }
                $out[] = ['percentual_min' => $p, 'nota' => max(0.0, (float) $v)];
            }
        }
        usort($out, static function (array $a, array $b): int {
            return (int) $b['percentual_min'] <=> (int) $a['percentual_min'];
        });
        return $out;
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

    /**
     * Rascunho completo do quadro semanal (S1–S8 N/Q, média semanal, prova bim, ENAC, rec).
     *
     * @param array<string,mixed> $opcoes
     * @return array<string,mixed>
     */
    public function montarRascunhoQuadroSemanal(array $opcoes = []): array
    {
        $ano = (int) ($opcoes['ano_letivo'] ?? date('Y'));
        if ($ano < 2000) {
            $ano = (int) date('Y');
        }
        $bim = (int) ($opcoes['bimestre'] ?? 1);
        if ($bim < 1 || $bim > 4) {
            $bim = 1;
        }
        $semanasA = $this->normalizarListaSemanas($opcoes['semanas_a'] ?? [1, 3, 5, 7], [1, 3, 5, 7]);
        $semanasB = $this->normalizarListaSemanas($opcoes['semanas_b'] ?? [2, 4, 6, 8], [2, 4, 6, 8]);
        $tipos = $this->indexarTiposQuadro();
        $tipoSemanal = $tipos['semanal'] ?? null;

        $soComEvento = !empty($opcoes['so_semanas_com_evento']);
        $semanasEvento = [];
        if ($soComEvento) {
            foreach ($this->listarEventosProva($tipoSemanal['id'] ?? null, 200) as $ev) {
                $s = (int) ($ev['semana'] ?? 0);
                if ($s >= 1 && $s <= 8) {
                    $semanasEvento[$s] = true;
                }
            }
            if ($semanasEvento !== []) {
                $semanasA = array_values(array_filter($semanasA, static fn ($s) => isset($semanasEvento[$s])));
                $semanasB = array_values(array_filter($semanasB, static fn ($s) => isset($semanasEvento[$s])));
            }
        }

        $componentes = [];
        $codigosSemana = [];
        foreach ($semanasA as $s) {
            $cod = 's' . $s;
            $componentes[] = $this->componenteSemanaQuadro($cod, 'S' . $s, $s, 'quadro_a', $tipoSemanal);
            $codigosSemana[] = $cod;
        }
        foreach ($semanasB as $s) {
            $cod = 's' . $s;
            $componentes[] = $this->componenteSemanaQuadro($cod, 'S' . $s, $s, 'quadro_b', $tipoSemanal);
            $codigosSemana[] = $cod;
        }

        $componentes[] = [
            'codigo' => 'media_sem',
            'nome' => 'Média Sem',
            'source_type' => 'calculado',
            'calc_type' => 'media',
            'peso' => 1,
            'filtro_titulo' => '',
            'blocos_ids' => [],
            'materias_ids' => [],
            'materia_unica' => 0,
            'usar_percentual' => 0,
            'escala_max' => 10,
            'obrigatorio' => 0,
            'config' => [
                'expressao' => '',
                'formula_mode' => 'single',
                'agregar_nq' => $codigosSemana !== [] ? $codigosSemana : ['s1', 's3', 's5', 's7'],
                'layout_group' => 'quadro_comum',
                'layout_type' => 'media_sem',
            ],
        ];

        $partesMedia = ['media_sem'];
        $pecasFiltro = null;
        if (array_key_exists('pecas', $opcoes) && is_array($opcoes['pecas'])) {
            $pecasFiltro = [];
            foreach ($opcoes['pecas'] as $p) {
                $p = strtolower(trim((string) $p));
                if ($p !== '') {
                    $pecasFiltro[] = $p;
                }
            }
        }
        $querPeca = static function (string $chave) use ($pecasFiltro): bool {
            if ($pecasFiltro === null) {
                return true;
            }
            return in_array($chave, $pecasFiltro, true);
        };

        $provaBim = $tipos['prova_bim'] ?? $tipos['bimestral'] ?? null;
        if ($provaBim !== null && $querPeca('bimestral')) {
            $componentes[] = $this->componenteTipoQuadro(
                'prova_bim',
                'Prova Bim',
                $provaBim,
                false,
                'quadro_comum',
                'media'
            );
            $partesMedia[] = 'prova_bim';
        }
        foreach ([
            'enac' => ['enac', 'ENAC'],
            'participacao' => ['part', 'Part'],
            'trabalho' => ['trab', 'Trab'],
        ] as $chave => [$cod, $nome]) {
            if (!isset($tipos[$chave]) || !$querPeca($chave)) {
                continue;
            }
            $componentes[] = $this->componenteTipoQuadro(
                $cod,
                $nome,
                $tipos[$chave],
                false,
                'quadro_comum',
                'media'
            );
            $partesMedia[] = $cod;
        }

        $nPartes = count($partesMedia);
        $expBim = $nPartes === 1
            ? $partesMedia[0]
            : '(' . implode(' + ', $partesMedia) . ') / ' . $nPartes;
        $componentes[] = [
            'codigo' => 'media_bim',
            'nome' => 'Média Bim',
            'source_type' => 'calculado',
            'calc_type' => 'media',
            'peso' => 1,
            'filtro_titulo' => '',
            'blocos_ids' => [],
            'materias_ids' => [],
            'materia_unica' => 0,
            'usar_percentual' => 0,
            'escala_max' => 10,
            'obrigatorio' => 0,
            'config' => [
                'expressao' => $expBim,
                'formula_mode' => 'single',
                'layout_group' => 'quadro_comum',
                'layout_type' => 'media',
            ],
        ];

        $formulaFinal = 'media_bim';
        if (isset($tipos['recuperacao']) && $querPeca('recuperacao')) {
            $componentes[] = $this->componenteTipoQuadro(
                'rec',
                'Rec',
                $tipos['recuperacao'],
                false,
                'quadro_comum',
                'rec'
            );
            $componentes[] = [
                'codigo' => 'media_final',
                'nome' => 'Média Bim Final',
                'source_type' => 'calculado',
                'calc_type' => 'media',
                'peso' => 1,
                'filtro_titulo' => '',
                'blocos_ids' => [],
                'materias_ids' => [],
                'materia_unica' => 0,
                'usar_percentual' => 0,
                'escala_max' => 10,
                'obrigatorio' => 0,
                'config' => [
                    'expressao' => 'max(media_bim, rec)',
                    'formula_mode' => 'single',
                    'layout_group' => 'quadro_comum',
                    'layout_type' => 'resultado',
                ],
            ];
            $formulaFinal = 'media_final';
        }

        $nome = trim((string) ($opcoes['nome'] ?? ''));
        if ($nome === '') {
            $nome = sprintf('Quadro semanal — %dº bimestre %d', $bim, $ano);
        }

        return [
            'modo' => ($opcoes['modo'] ?? 'criar') === 'editar' ? 'editar' : 'criar',
            'regra_id' => !empty($opcoes['regra_id']) ? (int) $opcoes['regra_id'] : null,
            'nome' => $nome,
            'codigo' => trim((string) ($opcoes['codigo'] ?? '')),
            'descricao_curta' => 'Quadro S1–S8 (N/Q), média semanal por acertos, prova bimestral, ENAC/trabalho se houver e recuperação na média final.',
            'formula_final' => $formulaFinal,
            'exibir_em' => (string) ($opcoes['exibir_em'] ?? 'boletim'),
            'ano_letivo' => $ano,
            'bimestre' => $bim,
            'default_data_inicio' => (string) ($opcoes['default_data_inicio'] ?? ''),
            'default_data_fim' => (string) ($opcoes['default_data_fim'] ?? ''),
            'turmas_ids' => $this->normalizarIds($opcoes['turmas_ids'] ?? []),
            'materias_ids' => $this->normalizarIds($opcoes['materias_ids'] ?? []),
            'series_ids' => $this->normalizarIds($opcoes['series_ids'] ?? []),
            'round_mode' => (($opcoes['round_mode'] ?? '') === 'half') ? 'half' : 'none',
            'nota_minima_aprovacao' => isset($opcoes['nota_minima_aprovacao'])
                ? (float) $opcoes['nota_minima_aprovacao']
                : 6.0,
            'componentes' => $componentes,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function montarContextoQuadroSemanal(): array
    {
        $tipos = $this->indexarTiposQuadro();
        $semanas = [];
        foreach ($this->listarEventosProva(null, 200) as $ev) {
            $s = (int) ($ev['semana'] ?? 0);
            if ($s >= 1 && $s <= 8) {
                $semanas[$s] = true;
            }
        }
        ksort($semanas);

        return [
            'semanas_com_evento' => array_map('intval', array_keys($semanas)),
            'tipos_por_chave' => $tipos,
            'grupos_materias' => $this->listarGruposMateriasQuadro(),
            'dica' => 'Quadro: colunas s1..s8 com config.semana + tipo semanal e usar_percentual=1 (mostra N/Q). Prova bim/ENAC/Trab = nota do evento (calc_type=ultima, usar_percentual=0). media_sem usa agregar_nq das semanas. materia_unica=1 junta a mesma matéria de professores diferentes. ENAC/Part/Trab/Rec só se o tipo existir no catálogo. Códigos sem hífen.',
        ];
    }

    /** @return list<string> */
    private function normalizarCodigosAgregarNq($raw): array
    {
        if (is_string($raw)) {
            $raw = trim($raw, "[] \t");
            $raw = preg_split('/[,\s;]+/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $cod = $this->slugCodigo((string) $v);
            if ($cod !== '') {
                $out[] = $cod;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<int>|mixed $raw
     * @param list<int> $fallback
     * @return list<int>
     */
    private function normalizarListaSemanas($raw, array $fallback): array
    {
        if (!is_array($raw)) {
            return $fallback;
        }
        $out = [];
        foreach ($raw as $v) {
            $s = (int) $v;
            if ($s >= 1 && $s <= 8) {
                $out[] = $s;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out !== [] ? $out : $fallback;
    }

    /**
     * @return array<string,array{id:int,nome:string,chave_quadro:?string}>
     */
    private function indexarTiposQuadro(): array
    {
        $out = [];
        foreach ($this->listarTiposAvaliacao() as $t) {
            $id = (int) ($t['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $item = [
                'id' => $id,
                'nome' => (string) ($t['nome'] ?? ''),
                'chave_quadro' => $t['chave_quadro'] ?? null,
            ];
            $chave = strtolower(trim((string) ($t['chave_quadro'] ?? '')));
            if ($chave !== '' && !isset($out[$chave])) {
                $out[$chave] = $item;
            }
            $nome = mb_strtolower((string) ($t['nome'] ?? ''));
            if ($chave !== '') {
                continue;
            }
            $mapa = [
                'recupera' => 'recuperacao',
                'enac' => 'enac',
                'participa' => 'participacao',
                'trabalho' => 'trabalho',
                'bimestral' => 'prova_bim',
                'prova bim' => 'prova_bim',
                'semanal' => 'semanal',
            ];
            foreach ($mapa as $needle => $dest) {
                if (!isset($out[$dest]) && str_contains($nome, $needle)) {
                    $out[$dest] = $item;
                }
            }
        }

        return $out;
    }

    /**
     * @param array{id:int,nome:string}|null $tipo
     * @return array<string,mixed>
     */
    private function componenteSemanaQuadro(string $codigo, string $nome, int $semana, string $grupo, ?array $tipo): array
    {
        $cfg = [
            'semana' => $semana,
            'layout_group' => $grupo,
            'layout_type' => 'semana_nq',
        ];
        $tipoId = (int) ($tipo['id'] ?? 0);
        $tipoNome = trim((string) ($tipo['nome'] ?? 'Semanal'));
        if ($tipoId > 0) {
            $cfg['tipo_avaliacao_id'] = $tipoId;
            $cfg['tipo_avaliacao_nome'] = $tipoNome;
        }

        return [
            'codigo' => $codigo,
            'nome' => $nome,
            'source_type' => 'provas_sistema',
            'calc_type' => 'media',
            'peso' => 1,
            'filtro_titulo' => '',
            'blocos_ids' => [],
            'materias_ids' => [],
            'materia_unica' => 1,
            'usar_percentual' => 1,
            'escala_max' => 10,
            'obrigatorio' => 0,
            'tipo_avaliacao_id' => $tipoId > 0 ? $tipoId : null,
            'tipo_avaliacao_nome' => $tipoNome,
            'config' => $cfg,
        ];
    }

    /**
     * @param array{id:int,nome:string} $tipo
     * @return array<string,mixed>
     */
    private function componenteTipoQuadro(
        string $codigo,
        string $nome,
        array $tipo,
        bool $usarPercentual,
        string $grupo,
        string $layoutType
    ): array {
        $tipoId = (int) ($tipo['id'] ?? 0);
        $tipoNome = trim((string) ($tipo['nome'] ?? $nome));

        return [
            'codigo' => $codigo,
            'nome' => $nome,
            'source_type' => 'provas_sistema',
            'calc_type' => 'ultima',
            'peso' => 1,
            'filtro_titulo' => '',
            'blocos_ids' => [],
            'materias_ids' => [],
            'materia_unica' => 1,
            'usar_percentual' => $usarPercentual ? 1 : 0,
            'escala_max' => 10,
            'obrigatorio' => 0,
            'tipo_avaliacao_id' => $tipoId > 0 ? $tipoId : null,
            'tipo_avaliacao_nome' => $tipoNome,
            'config' => [
                'tipo_avaliacao_id' => $tipoId,
                'tipo_avaliacao_nome' => $tipoNome,
                'layout_group' => $grupo,
                'layout_type' => $layoutType,
            ],
        ];
    }

    /**
     * @return array{A:list<array{id:int,nome:string}>,B:list<array{id:int,nome:string}>}
     */
    private function listarGruposMateriasQuadro(): array
    {
        $out = ['A' => [], 'B' => []];
        $path = __DIR__ . '/../Modulos/notas-semanais/Models/NotasSemanaisConfig.php';
        if (!class_exists('NotasSemanaisConfig', false) && is_file($path)) {
            require_once $path;
        }
        if (!class_exists('NotasSemanaisConfig', false)) {
            return $out;
        }
        try {
            $cfg = new NotasSemanaisConfig();
            if (!method_exists($cfg, 'listarMateriasComGrupo')) {
                return $out;
            }
            foreach ($cfg->listarMateriasComGrupo() as $m) {
                $id = (int) ($m['id'] ?? 0);
                $g = strtoupper((string) ($m['grupo'] ?? ''));
                if ($id <= 0 || ($g !== 'A' && $g !== 'B')) {
                    continue;
                }
                $out[$g][] = [
                    'id' => $id,
                    'nome' => trim((string) ($m['nome'] ?? '')),
                ];
            }
        } catch (Throwable $e) {
            return $out;
        }

        return $out;
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
