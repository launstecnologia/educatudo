<?php

/**
 * Cálculo de nota a partir de jornadas concluídas (não usa acerto/erro de questões).
 * Usado pela regra do boletim (origem Jornadas). Conclusão: jornada marcada concluída,
 * todos os módulos concluídos ou ao menos uma conclusão registrada na jornada.
 */
class JourneyBoletimLancamento
{
    /** @var Database */
    private $db;

    /**
     * null = ainda não verificado; true = coluna jornadas.materia_id existe; false = não existe (SELECT usa literal).
     * @var bool|null
     */
    private static $jornadasHasMateriaIdColumn = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureJornadasMateriaIdColumn();
    }

    /**
     * Bases antigas podem não ter jornadas.materia_id. Tenta ALTER; se não for possível, queries usam 0 AS materia_id
     * para não quebrar a página (notas por matéria ficam degradadas até o DBA criar a coluna).
     */
    private function ensureJornadasMateriaIdColumn(): void
    {
        if (self::$jornadasHasMateriaIdColumn !== null) {
            return;
        }
        if (!$this->db->tableExists('jornadas')) {
            self::$jornadasHasMateriaIdColumn = false;

            return;
        }
        if ($this->jornadasMateriaIdColumnExists()) {
            self::$jornadasHasMateriaIdColumn = true;

            return;
        }
        try {
            $this->db->query(
                'ALTER TABLE jornadas ADD COLUMN materia_id INT NULL DEFAULT NULL'
            );
        } catch (Throwable $e) {
            error_log('JourneyBoletimLancamento ensureJornadasMateriaIdColumn (ALTER): ' . $e->getMessage());
        }
        self::$jornadasHasMateriaIdColumn = $this->jornadasMateriaIdColumnExists();
    }

    private function jornadasMateriaIdColumnExists(): bool
    {
        try {
            $rows = $this->db->fetchAll("SHOW COLUMNS FROM jornadas LIKE 'materia_id'");

            return !empty($rows);
        } catch (Throwable $e) {
            error_log('JourneyBoletimLancamento jornadasMateriaIdColumnExists: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Expressão SQL para o campo de matéria na listagem de jornadas (após {@see ensureJornadasMateriaIdColumn}).
     */
    private function sqlJornadasMateriaIdSelect(): string
    {
        return self::$jornadasHasMateriaIdColumn ? 'j.materia_id' : '0 AS materia_id';
    }

    /**
     * Tabela padrão: % de jornadas concluídas no escopo → nota 0–10.
     */
    public static function percentualParaNota(float $pct): float
    {
        if ($pct >= 90.0) {
            return 10.0;
        }
        if ($pct >= 80.0) {
            return 9.0;
        }
        if ($pct >= 70.0) {
            return 8.0;
        }
        if ($pct >= 60.0) {
            return 7.0;
        }
        if ($pct >= 50.0) {
            return 6.0;
        }
        if ($pct >= 40.0) {
            return 5.0;
        }
        if ($pct > 0.0) {
            return round(($pct / 40.0) * 5.0, 2);
        }

        return 0.0;
    }

    public function listarTurmasAtivas(): array
    {
        return $this->db->fetchAll(
            "SELECT id, nome, serie FROM turmas WHERE ativo = 1 ORDER BY nome ASC"
        ) ?: [];
    }

    /**
     * Jornadas candidatas (turma principal ou turmas_selecionadas), depois filtro de datas em PHP.
     *
     * @param list<int> $turmaIds
     * @return list<array<string,mixed>>
     */
    public function listarJornadasCandidatas(array $turmaIds, ?string $dataIni, ?string $dataFim): array
    {
        $turmaIds = array_values(array_filter(array_map('intval', $turmaIds)));
        if (empty($turmaIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
        $materiaSql = $this->sqlJornadasMateriaIdSelect();
        $rows = $this->db->fetchAll(
            "SELECT j.id, j.titulo, j.turma_id, j.estrutura, j.created_at, {$materiaSql}, j.professor_id, j.ano_letivo, j.bimestre
             FROM jornadas j
             WHERE (j.ativo = 1 OR j.ativo IS NULL)
               AND (
                    j.turma_id IN ($placeholders)
                    OR (j.estrutura IS NOT NULL AND j.estrutura != '')
               )
             ORDER BY j.created_at DESC
             LIMIT 800",
            $turmaIds
        ) ?: [];

        $out = [];
        foreach ($rows as $j) {
            $aplica = false;
            foreach ($turmaIds as $tid) {
                if (self::jornadaCobreTurma($j, (int) $tid)) {
                    $aplica = true;
                    break;
                }
            }
            if (!$aplica) {
                continue;
            }
            if (!self::jornadaNoPeriodo($j, $dataIni, $dataFim)) {
                continue;
            }
            $out[] = $j;
        }

        return $out;
    }

    /**
     * Jornadas escolhidas na regra do boletim: por ID + turma, sem filtrar por datas da jornada
     * (evita escopo vazio quando o bimestre não intercepta data_inicio/fim da jornada).
     *
     * @param list<int> $turmaIds
     * @param list<int> $jornadaIds
     * @return list<array<string,mixed>>
     */
    public function buscarJornadasPorIdsEscopoTurma(array $turmaIds, array $jornadaIds): array
    {
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        $jornadaIds = array_values(array_unique(array_filter(array_map('intval', $jornadaIds))));
        if ($turmaIds === [] || $jornadaIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($jornadaIds), '?'));
        $materiaSql = $this->sqlJornadasMateriaIdSelect();
        $rows = $this->db->fetchAll(
            "SELECT j.id, j.titulo, j.turma_id, j.estrutura, j.created_at, {$materiaSql}, j.professor_id
             FROM jornadas j
             WHERE (j.ativo = 1 OR j.ativo IS NULL)
               AND j.id IN ($ph)
             ORDER BY j.created_at DESC",
            $jornadaIds
        ) ?: [];

        $out = [];
        foreach ($rows as $j) {
            foreach ($turmaIds as $tid) {
                if (self::jornadaCobreTurma($j, (int) $tid)) {
                    $out[] = $j;
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $j
     */
    public static function jornadaCobreTurma(array $j, int $turmaId): bool
    {
        if ((int) ($j['turma_id'] ?? 0) === (int) $turmaId) {
            return true;
        }
        $e = json_decode((string) ($j['estrutura'] ?? ''), true);
        if (!is_array($e) || empty($e['turmas_selecionadas'])) {
            return false;
        }
        $lista = array_map('intval', (array) $e['turmas_selecionadas']);

        return in_array((int) $turmaId, $lista, true);
    }

    /**
     * Interseção [início,fim] da jornada (estrutura ou created_at) com [dataIni,dataFim] do filtro.
     * Se dataIni e dataFim vazios, não filtra por data.
     *
     * @param array<string,mixed> $j
     */
    public static function jornadaNoPeriodo(array $j, ?string $dataIni, ?string $dataFim): bool
    {
        $iniF = self::normalizarData($dataIni);
        $fimF = self::normalizarData($dataFim);
        if ($iniF === null && $fimF === null) {
            return true;
        }
        if ($iniF === null) {
            $iniF = '1970-01-01';
        }
        if ($fimF === null) {
            $fimF = '2099-12-31';
        }
        if ($iniF > $fimF) {
            [$iniF, $fimF] = [$fimF, $iniF];
        }

        $e = json_decode((string) ($j['estrutura'] ?? ''), true) ?: [];
        $ji = self::normalizarData($e['data_inicio'] ?? null);
        $jf = self::normalizarData($e['data_fim'] ?? null);
        if ($ji === null && $jf === null) {
            $c = substr((string) ($j['created_at'] ?? ''), 0, 10);
            $ji = self::normalizarData($c) ?? '1970-01-01';
            $jf = $ji;
        } else {
            if ($ji === null) {
                $ji = '1970-01-01';
            }
            if ($jf === null) {
                $jf = '2099-12-31';
            }
        }
        if ($ji > $jf) {
            [$ji, $jf] = [$jf, $ji];
        }

        return !($jf < $iniF || $ji > $fimF);
    }

    /**
     * Percentual de conclusão de uma jornada para o aluno (0 ou 100),
     * baseado em jornada concluída.
     *
     * @param array<string,bool> $concluidasExplicit chave alunoId:jornadaId
     * @param array<string,int> $modulosConcluidos chave alunoId:jornadaId => count
     * @param array<int,int> $totaisModulos jornada_id => total módulos
     */
    public function percentualConclusaoAlunoJornada(
        int $alunoId,
        int $jornadaId,
        array $totaisModulos,
        array $concluidasExplicit,
        array $modulosConcluidos
    ): float {
        if ($alunoId <= 0 || $jornadaId <= 0) {
            return 0.0;
        }

        $chave = $alunoId . ':' . $jornadaId;
        if (!empty($concluidasExplicit[$chave])) {
            return 100.0;
        }

        $tm = (int) ($totaisModulos[$jornadaId] ?? 0);
        if ($tm > 0) {
            $mc = (int) ($modulosConcluidos[$chave] ?? 0);
            $pct = ($mc / $tm) * 100.0;
            return round(max(0.0, min(100.0, $pct)), 2);
        }

        return $this->alunoTemQualquerConclusaoNaJornada($alunoId, $jornadaId) ? 100.0 : 0.0;
    }

    /**
     * Mesma regra que o somatório "concluída" em {@see montarPreview} (0/1 por jornada).
     *
     * @param array<string,bool> $concluidasExplicit
     * @param array<string,int> $modulosConcluidos
     * @param array<int,int> $totaisModulos
     */
    private function alunoJornadaConcluidaBinario(
        int $alunoId,
        int $jornadaId,
        array $totaisModulos,
        array $concluidasExplicit,
        array $modulosConcluidos
    ): bool {
        if ($alunoId <= 0 || $jornadaId <= 0) {
            return false;
        }
        $chave = $alunoId . ':' . $jornadaId;
        if (!empty($concluidasExplicit[$chave])) {
            return true;
        }
        $tm = (int) ($totaisModulos[$jornadaId] ?? 0);
        if ($tm > 0) {
            $mc = (int) ($modulosConcluidos[$chave] ?? 0);

            return $mc >= $tm;
        }

        return $this->alunoTemQualquerConclusaoNaJornada($alunoId, $jornadaId);
    }

    /**
     * Converte % de conclusão da jornada em nota na escala configurada.
     */
    public static function notaFromPercentualConclusao(
        float $pct,
        float $escalaMaxNota,
        bool $linearPorPercentualConclusao,
        array $faixasPercentuais = []
    ): float {
        $em = max(0.01, $escalaMaxNota);
        if ($linearPorPercentualConclusao) {
            return round(($pct / 100.0) * $em, 2);
        }
        $notaFaixa = self::notaPorFaixasPercentuais($pct, $faixasPercentuais);
        if ($notaFaixa !== null) {
            return round(max(0.0, min($em, $notaFaixa)), 2);
        }

        return self::percentualParaNota($pct);
    }

    /**
     * @param list<array{percentual_min:int,nota:float|int|string}> $faixasPercentuais
     */
    private static function notaPorFaixasPercentuais(float $pct, array $faixasPercentuais): ?float
    {
        if ($faixasPercentuais === []) {
            return null;
        }
        $norm = [];
        foreach ($faixasPercentuais as $fx) {
            if (!is_array($fx)) {
                continue;
            }
            $p = (int) ($fx['percentual_min'] ?? 0);
            $n = is_numeric($fx['nota'] ?? null) ? (float) $fx['nota'] : null;
            if ($p < 0 || $p > 100 || $n === null) {
                continue;
            }
            $norm[] = ['percentual_min' => $p, 'nota' => $n];
        }
        if ($norm === []) {
            return null;
        }
        usort($norm, static function (array $a, array $b): int {
            return (int) ($b['percentual_min'] ?? 0) <=> (int) ($a['percentual_min'] ?? 0);
        });
        foreach ($norm as $fx) {
            if ($pct >= (float) ($fx['percentual_min'] ?? 0)) {
                return (float) ($fx['nota'] ?? 0);
            }
        }

        return 0.0;
    }

    /**
     * Nota do componente jornadas por matéria: cada jornada usa {@see jornadas.materia_id};
     * várias jornadas na mesma matéria → média/soma/maior/última conforme $calcType.
     *
     * @return array{
     *   por_materia: array<int,float>,
     *   notas_lista: list<array{valor: float, materia_id: int, materia_nome: string}>,
     *   total_jornadas_escopo: int,
     *   concluidas_agregado: int,
     *   percentual_medio_jornadas: float,
     *   percentual_conclusao_escopo: float,
     *   nota_unica_valor_padrao: ?float,
     *   nota_unica_substituicao_por_materia: array<int, float|null>
     * }
     */
    public function notasPorMateriaAluno(
        int $alunoId,
        int $turmaId,
        array $jornadaIdsEscopo,
        ?string $dataIni,
        ?string $dataFim,
        float $escalaMaxNota,
        bool $linearPorPercentualConclusao,
        string $calcType,
        array $faixasPercentuais = [],
        bool $notaUnicaPeloTotalConcluidasNoEscopo = false,
        ?array $notaUnicaExtras = null
    ): array {
        $calcType = strtolower(trim($calcType));
        if (!in_array($calcType, ['media', 'soma', 'maior', 'ultima'], true)) {
            $calcType = 'media';
        }
        $usaTabelaFaixas = !$linearPorPercentualConclusao && !empty($faixasPercentuais);

        $turmaIds = [$turmaId];
        $jornadaIdsEscopo = array_values(array_unique(array_filter(array_map('intval', $jornadaIdsEscopo))));
        if (!empty($jornadaIdsEscopo)) {
            $jornadas = $this->buscarJornadasPorIdsEscopoTurma($turmaIds, $jornadaIdsEscopo);
            $jornadas = $this->filtrarJornadasPorPeriodo($jornadas, $dataIni, $dataFim);
        } else {
            $jornadas = $this->listarJornadasCandidatas($turmaIds, $dataIni, $dataFim);
        }

        $jornadaIds = array_values(array_filter(array_map(static function ($j) {
            return (int) ($j['id'] ?? 0);
        }, $jornadas)));
        if ($jornadaIds === []) {
            return [
                'por_materia' => [],
                'notas_lista' => [],
                'total_jornadas_escopo' => 0,
                'concluidas_agregado' => 0,
                'percentual_medio_jornadas' => 0.0,
                'percentual_conclusao_escopo' => 0.0,
                'nota_unica_valor_padrao' => null,
                'nota_unica_substituicao_por_materia' => [],
            ];
        }

        $jornadaById = [];
        foreach ($jornadas as $j) {
            $jid = (int) ($j['id'] ?? 0);
            if ($jid > 0) {
                $jornadaById[$jid] = $j;
            }
        }

        $noEscopo = [];
        foreach ($jornadaIds as $jid) {
            $jr = $jornadaById[$jid] ?? null;
            if (!$jr || !self::jornadaCobreTurma($jr, $turmaId)) {
                continue;
            }
            $noEscopo[] = $jid;
        }

        $total = count($noEscopo);
        if ($total === 0) {
            return [
                'por_materia' => [],
                'notas_lista' => [],
                'total_jornadas_escopo' => 0,
                'concluidas_agregado' => 0,
                'percentual_medio_jornadas' => 0.0,
                'percentual_conclusao_escopo' => 0.0,
                'nota_unica_valor_padrao' => null,
                'nota_unica_substituicao_por_materia' => [],
            ];
        }

        $totaisModulos = $this->totaisModulosPorJornada($jornadaIds);
        $concluidasExplicit = $this->paresJornadaConcluidaExplicita($jornadaIds, [$alunoId]);
        $modulosConcluidos = $this->contagemModulosConcluidosPorAlunoJornada($jornadaIds, [$alunoId]);

        $byMid = [];
        $byMidTotais = [];
        $byMidConcluidas = [];
        $somaPct = 0.0;
        $conclAgregado = 0;
        foreach ($noEscopo as $jid) {
            $pct = $this->percentualConclusaoAlunoJornada($alunoId, $jid, $totaisModulos, $concluidasExplicit, $modulosConcluidos);
            $somaPct += $pct;
            $concluiuBinario = $this->alunoJornadaConcluidaBinario($alunoId, $jid, $totaisModulos, $concluidasExplicit, $modulosConcluidos);
            if ($concluiuBinario) {
                $conclAgregado++;
            }
            $jr = $jornadaById[$jid] ?? [];
            $mid = (int) ($jr['materia_id'] ?? 0);
            if ($mid < 0) {
                $mid = 0;
            }
            if (!isset($byMidTotais[$mid])) {
                $byMidTotais[$mid] = 0;
                $byMidConcluidas[$mid] = 0;
            }
            $byMidTotais[$mid]++;
            if ($concluiuBinario) {
                $byMidConcluidas[$mid]++;
            }

            $notaJ = self::notaFromPercentualConclusao($pct, $escalaMaxNota, $linearPorPercentualConclusao, $faixasPercentuais);
            if (!isset($byMid[$mid])) {
                $byMid[$mid] = [];
            }
            $byMid[$mid][] = $notaJ;
        }

        $pctConclusaoEscopo = $total > 0 ? round(($conclAgregado / $total) * 100.0, 2) : 0.0;

        $porMateria = [];
        $notaUnicaValorPadrao = null;
        $substituicaoPorMateria = [];
        if ($notaUnicaPeloTotalConcluidasNoEscopo) {
            $notaGlobal = self::notaFromPercentualConclusao(
                $pctConclusaoEscopo,
                $escalaMaxNota,
                $linearPorPercentualConclusao,
                $faixasPercentuais
            );
            $notaUnicaValorPadrao = $notaGlobal;
            if (is_array($notaUnicaExtras) && isset($notaUnicaExtras['fonte_por_materia']) && is_array($notaUnicaExtras['fonte_por_materia'])) {
                foreach ($notaUnicaExtras['fonte_por_materia'] as $targetRaw => $srcListRaw) {
                    $targetMid = (int) $targetRaw;
                    if ($targetMid === 0) {
                        continue;
                    }
                    $allowed = [];
                    foreach ((array) $srcListRaw as $x) {
                        $ix = (int) $x;
                        if ($ix !== 0) {
                            $allowed[$ix] = true;
                        }
                    }
                    if ($allowed === []) {
                        continue;
                    }
                    $subIds = [];
                    foreach ($noEscopo as $jid) {
                        $jr = $jornadaById[$jid] ?? [];
                        $mj = (int) ($jr['materia_id'] ?? 0);
                        if ($mj !== 0 && isset($allowed[$mj])) {
                            $subIds[] = $jid;
                        }
                    }
                    if ($subIds === []) {
                        $substituicaoPorMateria[$targetMid] = null;
                        continue;
                    }
                    $subTot = count($subIds);
                    $subConcl = 0;
                    foreach ($subIds as $jid) {
                        if ($this->alunoJornadaConcluidaBinario($alunoId, $jid, $totaisModulos, $concluidasExplicit, $modulosConcluidos)) {
                            $subConcl++;
                        }
                    }
                    $subPct = $subTot > 0 ? round(($subConcl / $subTot) * 100.0, 2) : 0.0;
                    $substituicaoPorMateria[$targetMid] = self::notaFromPercentualConclusao(
                        $subPct,
                        $escalaMaxNota,
                        $linearPorPercentualConclusao,
                        $faixasPercentuais
                    );
                }
            }
            foreach (array_keys($byMidTotais) as $mid) {
                $porMateria[(int) $mid] = $notaGlobal;
            }
        } else {
            foreach ($byMid as $mid => $valores) {
                if ($usaTabelaFaixas) {
                    $totMid = (int) ($byMidTotais[$mid] ?? 0);
                    $conclMid = (int) ($byMidConcluidas[$mid] ?? 0);
                    $pctMid = $totMid > 0 ? round(($conclMid / $totMid) * 100.0, 2) : 0.0;
                    $v = self::notaFromPercentualConclusao($pctMid, $escalaMaxNota, false, $faixasPercentuais);
                } else {
                    $v = $this->agruparValoresJornadaMateria($valores, $calcType);
                }
                if ($v !== null) {
                    $porMateria[(int) $mid] = $v;
                }
            }
        }

        $mids = array_keys($porMateria);
        $nomes = $this->buscarNomesMateriasPorIds($mids);
        $notasLista = [];
        foreach ($porMateria as $mid => $v) {
            $notasLista[] = [
                'valor' => $v,
                'materia_id' => (int) $mid,
                'materia_nome' => (string) ($nomes[(int) $mid] ?? ($mid === 0 ? 'Sem matéria' : ('Matéria #' . $mid))),
            ];
        }

        return [
            'por_materia' => $porMateria,
            'notas_lista' => $notasLista,
            'total_jornadas_escopo' => $total,
            'concluidas_agregado' => $conclAgregado,
            'percentual_medio_jornadas' => round($somaPct / max(1, $total), 2),
            'percentual_conclusao_escopo' => $pctConclusaoEscopo,
            'nota_unica_valor_padrao' => $notaUnicaValorPadrao,
            'nota_unica_substituicao_por_materia' => $substituicaoPorMateria,
        ];
    }

    /**
     * @param list<float> $valores
     */
    private function agruparValoresJornadaMateria(array $valores, string $calcType): ?float
    {
        $valores = array_values(array_filter($valores, static function ($x) {
            return is_numeric($x);
        }));
        if ($valores === []) {
            return null;
        }
        if ($calcType === 'soma') {
            return round(array_sum($valores), 2);
        }
        if ($calcType === 'maior') {
            return round((float) max($valores), 2);
        }
        if ($calcType === 'ultima') {
            return round((float) $valores[count($valores) - 1], 2);
        }

        return round(array_sum($valores) / count($valores), 2);
    }

    /**
     * @param list<int> $materiaIds
     * @return array<int,string>
     */
    private function buscarNomesMateriasPorIds(array $materiaIds): array
    {
        $materiaIds = array_values(array_unique(array_filter(array_map('intval', $materiaIds))));
        if ($materiaIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($materiaIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id, nome FROM materias WHERE id IN ($ph)",
            $materiaIds
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[(int) ($r['id'] ?? 0)] = (string) ($r['nome'] ?? '');
        }

        return $out;
    }

    private static function normalizarData(?string $d): ?string
    {
        if ($d === null || trim($d) === '') {
            return null;
        }
        $d = trim($d);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $d;
        }
        $ts = strtotime($d);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * @param list<int> $turmaIds
     * @param list<int> $jornadaIdsEscopo vazio = todas as candidatas após filtro de datas
     * @param float $escalaMaxNota teto da nota (ex.: 10) quando $linearPorPercentualConclusao for true
     * @param bool $linearPorPercentualConclusao se true, nota = (% jornadas concluídas / 100) × escala; se false, usa {@see percentualParaNota()}
     * @return list<array{aluno_id:int,turma_id:int,nome:string,turma_nome:string,total:int,concluidas:int,percentual:float,nota:?float}>
     */
    public function montarPreview(
        array $turmaIds,
        array $jornadaIdsEscopo,
        ?string $dataIni,
        ?string $dataFim,
        float $escalaMaxNota = 10.0,
        bool $linearPorPercentualConclusao = false
    ): array {
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        if (empty($turmaIds)) {
            return [];
        }

        $jornadaIdsEscopo = array_values(array_unique(array_filter(array_map('intval', $jornadaIdsEscopo))));
        if (!empty($jornadaIdsEscopo)) {
            $jornadas = $this->buscarJornadasPorIdsEscopoTurma($turmaIds, $jornadaIdsEscopo);
            $jornadas = $this->filtrarJornadasPorPeriodo($jornadas, $dataIni, $dataFim);
        } else {
            $jornadas = $this->listarJornadasCandidatas($turmaIds, $dataIni, $dataFim);
        }

        $jornadaIds = array_map(static function ($j) {
            return (int) ($j['id'] ?? 0);
        }, $jornadas);
        $jornadaIds = array_values(array_filter($jornadaIds));
        if (empty($jornadaIds)) {
            return [];
        }

        $jornadaById = [];
        foreach ($jornadas as $j) {
            $jid = (int) ($j['id'] ?? 0);
            if ($jid > 0) {
                $jornadaById[$jid] = $j;
            }
        }

        $alunos = $this->db->fetchAll(
            'SELECT a.id, a.nome, a.turma_id, t.nome AS turma_nome
             FROM alunos a
             INNER JOIN turmas t ON t.id = a.turma_id
             WHERE a.ativo = 1 AND a.turma_id IN (' . implode(',', array_fill(0, count($turmaIds), '?')) . ')
             ORDER BY t.nome ASC, a.nome ASC',
            $turmaIds
        ) ?: [];

        $totaisModulos = $this->totaisModulosPorJornada($jornadaIds);
        $concluidasExplicit = $this->paresJornadaConcluidaExplicita($jornadaIds, array_column($alunos, 'id'));
        $modulosConcluidos = $this->contagemModulosConcluidosPorAlunoJornada($jornadaIds, array_column($alunos, 'id'));

        $out = [];
        foreach ($alunos as $a) {
            $alunoId = (int) ($a['id'] ?? 0);
            $turmaId = (int) ($a['turma_id'] ?? 0);
            if ($alunoId <= 0 || $turmaId <= 0) {
                continue;
            }

            $noEscopo = [];
            foreach ($jornadaIds as $jid) {
                $jr = $jornadaById[$jid] ?? null;
                if (!$jr || !self::jornadaCobreTurma($jr, $turmaId)) {
                    continue;
                }
                $noEscopo[] = $jid;
            }

            $total = count($noEscopo);
            $concl = 0;
            foreach ($noEscopo as $jid) {
                $chave = $alunoId . ':' . $jid;
                if (!empty($concluidasExplicit[$chave])) {
                    $concl++;
                    continue;
                }
                $tm = (int) ($totaisModulos[$jid] ?? 0);
                if ($tm > 0) {
                    $mc = (int) ($modulosConcluidos[$chave] ?? 0);
                    if ($mc >= $tm) {
                        $concl++;
                    }
                } elseif ($this->alunoTemQualquerConclusaoNaJornada($alunoId, $jid)) {
                    $concl++;
                }
            }

            $pct = $total > 0 ? round(($concl / $total) * 100, 2) : 0.0;
            $em = max(0.01, $escalaMaxNota);
            if ($total <= 0) {
                $nota = null;
            } elseif ($linearPorPercentualConclusao) {
                $nota = round(($pct / 100.0) * $em, 2);
            } else {
                $nota = self::percentualParaNota($pct);
            }
            $out[] = [
                'aluno_id' => $alunoId,
                'turma_id' => $turmaId,
                'nome' => (string) ($a['nome'] ?? ''),
                'turma_nome' => (string) ($a['turma_nome'] ?? ''),
                'total' => $total,
                'concluidas' => $concl,
                'percentual' => $pct,
                'nota' => $nota,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $jornadas
     * @return list<array<string,mixed>>
     */
    private function filtrarJornadasPorPeriodo(array $jornadas, ?string $dataIni, ?string $dataFim): array
    {
        if ($jornadas === []) {
            return [];
        }
        $out = [];
        foreach ($jornadas as $j) {
            if (self::jornadaNoPeriodo($j, $dataIni, $dataFim)) {
                $out[] = $j;
            }
        }

        return $out;
    }

    private function alunoTemQualquerConclusaoNaJornada(int $alunoId, int $jornadaId): bool
    {
        if ($alunoId <= 0 || $jornadaId <= 0) {
            return false;
        }
        $r = $this->db->fetch(
            "SELECT 1 AS ok
             FROM jornadas_progresso_alunos
             WHERE aluno_id = :aluno_id
               AND jornada_id = :jornada_id
               AND (status = 'concluido' OR status = 'concluído')
             LIMIT 1",
            ['aluno_id' => $alunoId, 'jornada_id' => $jornadaId]
        );

        return $r !== false && $r !== null;
    }

    /**
     * @param list<int> $jornadaIds
     * @return array<int,int>
     */
    private function totaisModulosPorJornada(array $jornadaIds): array
    {
        if (empty($jornadaIds)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($jornadaIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT jornada_id, COUNT(*) AS c FROM jornadas_modulos WHERE jornada_id IN ($ph) GROUP BY jornada_id",
            $jornadaIds
        ) ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[(int) ($r['jornada_id'] ?? 0)] = (int) ($r['c'] ?? 0);
        }

        return $map;
    }

    /**
     * @param list<int> $jornadaIds
     * @param list<int> $alunoIds
     * @return array<string,bool> chave "alunoId:jornadaId"
     */
    private function paresJornadaConcluidaExplicita(array $jornadaIds, array $alunoIds): array
    {
        $jornadaIds = array_values(array_filter(array_map('intval', $jornadaIds)));
        $alunoIds = array_values(array_filter(array_map('intval', $alunoIds)));
        if (empty($jornadaIds) || empty($alunoIds)) {
            return [];
        }
        $pj = implode(',', array_fill(0, count($jornadaIds), '?'));
        $pa = implode(',', array_fill(0, count($alunoIds), '?'));
        $params = array_merge($jornadaIds, $alunoIds);
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT jpa.aluno_id, jpa.jornada_id
             FROM jornadas_progresso_alunos jpa
             WHERE jpa.atividade_tipo = 'jornada_concluida'
               AND jpa.status = 'concluido'
               AND jpa.jornada_id IN ($pj)
               AND jpa.aluno_id IN ($pa)",
            $params
        ) ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[(int) ($r['aluno_id'] ?? 0) . ':' . (int) ($r['jornada_id'] ?? 0)] = true;
        }

        return $map;
    }

    /**
     * @param list<int> $jornadaIds
     * @param list<int> $alunoIds
     * @return array<string,int> chave alunoId:jornadaId => módulos distintos concluídos
     */
    private function contagemModulosConcluidosPorAlunoJornada(array $jornadaIds, array $alunoIds): array
    {
        $jornadaIds = array_values(array_filter(array_map('intval', $jornadaIds)));
        $alunoIds = array_values(array_filter(array_map('intval', $alunoIds)));
        if (empty($jornadaIds) || empty($alunoIds)) {
            return [];
        }
        $pj = implode(',', array_fill(0, count($jornadaIds), '?'));
        $pa = implode(',', array_fill(0, count($alunoIds), '?'));
        $params = array_merge($jornadaIds, $alunoIds);
        $rows = $this->db->fetchAll(
            "SELECT jpa.aluno_id, jpa.jornada_id, COUNT(DISTINCT jpa.modulo_id) AS c
             FROM jornadas_progresso_alunos jpa
             WHERE jpa.atividade_tipo = 'modulo'
               AND jpa.status = 'concluido'
               AND jpa.modulo_id IS NOT NULL
               AND jpa.jornada_id IN ($pj)
               AND jpa.aluno_id IN ($pa)
             GROUP BY jpa.aluno_id, jpa.jornada_id",
            $params
        ) ?: [];
        $map = [];
        foreach ($rows as $r) {
            $k = (int) ($r['aluno_id'] ?? 0) . ':' . (int) ($r['jornada_id'] ?? 0);
            $map[$k] = (int) ($r['c'] ?? 0);
        }

        return $map;
    }
}
