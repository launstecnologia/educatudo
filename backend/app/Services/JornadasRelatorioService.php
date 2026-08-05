<?php
/**
 * Agregação de relatório de jornadas para o admin (Relatórios).
 * Alinha critério de "concluída" e público elegível à visão de AdminJourneyController::show.
 */
class JornadasRelatorioService
{
    private const MAX_JORNADAS = 350;
    private const MAX_CELULAS = 40000;

    /** @var object Database instance (fetch/fetchAll) */
    private $db;

    /** @var array<string,bool>|null */
    private $jornadasExtraCols = null;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string,mixed> $filtros Mescla filtros globais do relatório + chaves jr_*
     * @return array<string,mixed>
     */
    public function relatorio(array $filtros): array
    {
        $jornadas = $this->fetchJornadasCandidatas($filtros);
        $modoPorMateria = (($filtros['tipo'] ?? '') === 'usuario') && (($filtros['jr_modo_materia'] ?? 'total') === 'por_materia');
        $truncadas = count($jornadas) > self::MAX_JORNADAS;
        if ($truncadas) {
            $jornadas = array_slice($jornadas, 0, self::MAX_JORNADAS);
        }

        $agg = [];
        $totalAtrib = 0;
        $totalConc = 0;
        $cells = 0;

        foreach ($jornadas as $j) {
            $jid = (int) $j['id'];
            $materiaNome = trim((string) ($j['materia_nome'] ?? 'Sem matéria'));
            $elig = $this->eligibleAlunosForJornada($j, $filtros);
            $eligIds = [];
            $aggKeysByAluno = [];
            foreach ($elig as $row) {
                $aid = (int) $row['id'];
                $eligIds[$aid] = true;
                $aggKey = (string) $aid;
                if ($modoPorMateria) {
                    $aggKey = $aid . '|' . strtolower($materiaNome);
                }
                $aggKeysByAluno[$aid][] = $aggKey;
                if (!isset($agg[$aggKey])) {
                    $agg[$aggKey] = [
                        'aluno_id' => $aid,
                        'nome' => $row['nome'] ?? '',
                        'ra' => $row['ra'] ?? '',
                        'materia_nome' => $materiaNome !== '' ? $materiaNome : 'Sem matéria',
                        'turma_id' => (int) ($row['turma_id'] ?? 0),
                        'turma_nome' => $row['turma_nome'] ?? '',
                        'assigned' => 0,
                        'concluidas' => 0,
                        'ultima_atividade' => null,
                        'tempo_total_segundos' => 0,
                    ];
                }
                $agg[$aggKey]['assigned']++;
                $totalAtrib++;
                $cells++;
                if ($cells > self::MAX_CELULAS) {
                    break 2;
                }
            }

            $concluidos = $this->fetchAlunoIdsJornadaConcluida($jid);
            foreach ($concluidos as $aid) {
                if (!isset($eligIds[$aid])) {
                    continue;
                }
                foreach ($aggKeysByAluno[$aid] ?? [] as $aggKey) {
                    if (isset($agg[$aggKey])) {
                        $agg[$aggKey]['concluidas']++;
                    }
                    $totalConc++;
                }
            }

            $ultimas = $this->fetchUltimaAtividadePorAlunoJornada($jid, array_keys($eligIds));
            foreach ($ultimas as $aid => $ts) {
                foreach ($aggKeysByAluno[$aid] ?? [] as $aggKey) {
                    if (!isset($agg[$aggKey])) {
                        continue;
                    }
                    $prev = $agg[$aggKey]['ultima_atividade'];
                    if ($prev === null || $ts > $prev) {
                        $agg[$aggKey]['ultima_atividade'] = $ts;
                    }
                }
            }

            $temposJ = $this->fetchTempoPorAlunoJornada($jid, array_keys($eligIds));
            foreach ($temposJ as $aid => $seg) {
                foreach ($aggKeysByAluno[$aid] ?? [] as $aggKey) {
                    if (!isset($agg[$aggKey])) {
                        continue;
                    }
                    $agg[$aggKey]['tempo_total_segundos'] = (int) ($agg[$aggKey]['tempo_total_segundos'] ?? 0) + (int) $seg;
                }
            }
        }

        $porAluno = array_values($agg);
        foreach ($porAluno as &$row) {
            $a = (int) $row['assigned'];
            $c = (int) $row['concluidas'];
            $row['pendentes'] = max(0, $a - $c);
            $row['taxa_pct'] = $a > 0 ? round(100 * $c / $a, 1) : 0.0;
            $row['precisa_atencao'] = $a >= 2 && ($c === 0 || ($a > 0 && ($c / $a) < 0.5));
            $row['tempo_total_label'] = self::formatDuracao((int) ($row['tempo_total_segundos'] ?? 0));
        }
        unset($row);

        $ordemTempo = (string) ($filtros['jr_tempo_ordem'] ?? '');
        if ($ordemTempo === 'rapido' || $ordemTempo === 'lento') {
            usort($porAluno, static function ($x, $y) use ($ordemTempo, $modoPorMateria) {
                $tx = (int) ($x['tempo_total_segundos'] ?? 0);
                $ty = (int) ($y['tempo_total_segundos'] ?? 0);
                $zx = $tx <= 0;
                $zy = $ty <= 0;
                if ($zx && !$zy) {
                    return 1;
                }
                if (!$zx && $zy) {
                    return -1;
                }
                if ($zx && $zy) {
                    $cmp = ($x['taxa_pct'] <=> $y['taxa_pct']);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    $cmpNome = strcmp((string) ($x['nome'] ?? ''), (string) ($y['nome'] ?? ''));
                    if ($cmpNome !== 0 || !$modoPorMateria) {
                        return $cmpNome;
                    }
                    return strcmp((string) ($x['materia_nome'] ?? ''), (string) ($y['materia_nome'] ?? ''));
                }
                if ($ordemTempo === 'rapido') {
                    $cmp = $tx <=> $ty;
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                } else {
                    $cmp = $ty <=> $tx;
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                }
                $cmp = ($x['taxa_pct'] <=> $y['taxa_pct']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmpNome = strcmp((string) ($x['nome'] ?? ''), (string) ($y['nome'] ?? ''));
                if ($cmpNome !== 0 || !$modoPorMateria) {
                    return $cmpNome;
                }
                return strcmp((string) ($x['materia_nome'] ?? ''), (string) ($y['materia_nome'] ?? ''));
            });
        } else {
            usort($porAluno, static function ($x, $y) use ($modoPorMateria) {
                $cmp = ($x['taxa_pct'] <=> $y['taxa_pct']);
                if ($cmp !== 0) {
                    return $cmp;
                }

                $cmpPend = ($y['pendentes'] <=> $x['pendentes']);
                if ($cmpPend !== 0) {
                    return $cmpPend;
                }
                $cmpNome = strcmp((string) ($x['nome'] ?? ''), (string) ($y['nome'] ?? ''));
                if ($cmpNome !== 0 || !$modoPorMateria) {
                    return $cmpNome;
                }
                return strcmp((string) ($x['materia_nome'] ?? ''), (string) ($y['materia_nome'] ?? ''));
            });
        }

        $somenteAtencao = !empty($filtros['jr_somente_atencao']);
        $listaExibicao = $somenteAtencao
            ? array_values(array_filter($porAluno, static fn ($r) => !empty($r['precisa_atencao'])))
            : $porAluno;
        $totalItems = count($listaExibicao);
        $limit = max(10, min(500, (int) ($filtros['limit'] ?? 25)));
        $totalPages = max(1, (int) ceil($totalItems / $limit));
        $page = max(1, min((int) ($filtros['page'] ?? 1), $totalPages));
        $offset = ($page - 1) * $limit;
        $listaPaginada = array_slice($listaExibicao, $offset, $limit);
        $from = $totalItems > 0 ? $offset + 1 : 0;
        $to = min($offset + $limit, $totalItems);

        return [
            'totais' => [
                'atribuidas' => $totalAtrib,
                'concluidas' => $totalConc,
                'pendentes' => max(0, $totalAtrib - $totalConc),
                'taxa_pct' => $totalAtrib > 0 ? round(100 * $totalConc / $totalAtrib, 1) : 0.0,
            ],
            'jornadas_no_escopo' => count($jornadas),
            'jornadas_truncadas' => $truncadas,
            'celulas_limitadas' => $cells > self::MAX_CELULAS,
            'por_aluno' => $listaPaginada,
            'por_aluno_completo' => $porAluno,
            'jr_tempo_ordem' => $ordemTempo,
            'modo_por_materia' => $modoPorMateria,
            'paginacao' => [
                'page' => $page,
                'limit' => $limit,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_page' => $page > 1 ? $page - 1 : 1,
                'next_page' => $page < $totalPages ? $page + 1 : $totalPages,
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    private static function formatDuracao(int $sec): string
    {
        if ($sec <= 0) {
            return '—';
        }
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $s = $sec % 60;
        if ($h > 0) {
            return $h . 'h ' . $m . 'min';
        }
        if ($m > 0) {
            return $m . 'min ' . $s . 's';
        }

        return $s . 's';
    }

    private function detectJornadasExtraColumns(): array
    {
        if ($this->jornadasExtraCols !== null) {
            return $this->jornadasExtraCols;
        }
        $this->jornadasExtraCols = ['ano_letivo' => false, 'bimestre' => false, 'avaliativo' => false];
        try {
            $rows = $this->db->fetchAll(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jornadas'
                 AND COLUMN_NAME IN ('ano_letivo','bimestre','avaliativo')"
            );
            foreach ($rows as $r) {
                $n = $r['COLUMN_NAME'] ?? '';
                if (isset($this->jornadasExtraCols[$n])) {
                    $this->jornadasExtraCols[$n] = true;
                }
            }
        } catch (\Throwable $e) {
        }

        return $this->jornadasExtraCols;
    }

    /**
     * @param array<string,mixed> $filtros
     * @return list<array<string,mixed>>
     */
    private function fetchJornadasCandidatas(array $filtros): array
    {
        $extras = $this->detectJornadasExtraColumns();
        $where = ['(j.ativo = 1 OR j.ativo IS NULL)'];
        $params = [];

        if (!empty($filtros['data_inicio'])) {
            $where[] = 'DATE(j.created_at) >= :data_inicio';
            $params['data_inicio'] = $filtros['data_inicio'];
        }
        if (!empty($filtros['data_fim'])) {
            $where[] = 'DATE(j.created_at) <= :data_fim';
            $params['data_fim'] = $filtros['data_fim'];
        }
        if (!empty($filtros['jr_professor_id'])) {
            $where[] = 'j.professor_id = :jr_professor_id';
            $params['jr_professor_id'] = (int) $filtros['jr_professor_id'];
        }
        if (!empty($filtros['jr_materia_id'])) {
            $where[] = 'j.materia_id = :jr_materia_id';
            $params['jr_materia_id'] = (int) $filtros['jr_materia_id'];
        }
        if (!empty($filtros['jr_jornada_id'])) {
            $where[] = 'j.id = :jr_jornada_id';
            $params['jr_jornada_id'] = (int) $filtros['jr_jornada_id'];
        }
        if ($extras['ano_letivo'] && $filtros['jr_ano_letivo'] !== '' && $filtros['jr_ano_letivo'] !== null) {
            $where[] = 'j.ano_letivo = :jr_ano_letivo';
            $params['jr_ano_letivo'] = (int) $filtros['jr_ano_letivo'];
        } elseif ($filtros['jr_ano_letivo'] !== '' && $filtros['jr_ano_letivo'] !== null) {
            $where[] = 't.ano_letivo = :jr_ano_letivo_via_turma';
            $params['jr_ano_letivo_via_turma'] = (int) $filtros['jr_ano_letivo'];
        }
        if ($extras['bimestre'] && $filtros['jr_bimestre'] !== '' && $filtros['jr_bimestre'] !== null) {
            $where[] = 'j.bimestre = :jr_bimestre';
            $params['jr_bimestre'] = (int) $filtros['jr_bimestre'];
        }
        if ($extras['avaliativo'] && isset($filtros['jr_avaliativo']) && $filtros['jr_avaliativo'] !== '') {
            $where[] = 'j.avaliativo = :jr_avaliativo';
            $params['jr_avaliativo'] = (int) $filtros['jr_avaliativo'];
        }

        if (!empty($filtros['jr_turma_ano_letivo'])) {
            $where[] = 't.ano_letivo = :jr_turma_ano_letivo';
            $params['jr_turma_ano_letivo'] = (int) $filtros['jr_turma_ano_letivo'];
        }

        $whereSql = implode(' AND ', $where);
        $lim = (int) self::MAX_JORNADAS + 1;

        $sql = "SELECT j.id, j.titulo, j.turma_id, j.professor_id, j.materia_id, j.estrutura, j.created_at,
                       p.nome AS professor_nome,
                       t.nome AS turma_nome,
                       COALESCE(jm.nome, m.nome) AS materia_nome
                FROM jornadas j
                INNER JOIN professores p ON j.professor_id = p.id
                INNER JOIN turmas t ON j.turma_id = t.id
                LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
                LEFT JOIN materias m ON j.materia_id = m.id
                WHERE {$whereSql}
                ORDER BY j.created_at DESC
                LIMIT {$lim}";

        try {
            $rows = $this->db->fetchAll($sql, $params);
        } catch (\Throwable $e) {
            return [];
        }

        if (($filtros['tipo'] ?? '') === 'turma' && !empty($filtros['turma_id'])) {
            $tid = (int) $filtros['turma_id'];
            $rows = array_values(array_filter($rows, function ($j) use ($tid) {
                return $this->jornadaEnvolveTurma($j, $tid);
            }));
        }

        if (($filtros['tipo'] ?? '') === 'usuario' && !empty($filtros['aluno_id'])) {
            $aid = (int) $filtros['aluno_id'];
            $baseF = $filtros;
            $baseF['tipo'] = 'geral';
            $baseF['turma_id'] = '';
            $baseF['aluno_id'] = '';
            $rows = array_values(array_filter($rows, function ($j) use ($aid, $baseF) {
                foreach ($this->eligibleAlunosForJornada($j, $baseF) as $r) {
                    if ((int) $r['id'] === $aid) {
                        return true;
                    }
                }

                return false;
            }));
        }

        return $rows;
    }

    private function jornadaEnvolveTurma(array $j, int $turmaId): bool
    {
        if ((int) ($j['turma_id'] ?? 0) === $turmaId) {
            return true;
        }
        $e = json_decode($j['estrutura'] ?? '{}', true) ?: [];
        $ts = $e['turmas_selecionadas'] ?? [];

        return is_array($ts) && in_array($turmaId, array_map('intval', $ts), true);
    }

    /**
     * @param array<string,mixed> $j
     * @param array<string,mixed> $filtros
     * @return list<array{id:int,nome:string,ra:string,turma_id:int,turma_nome:string}>
     */
    private function eligibleAlunosForJornada(array $j, array $filtros): array
    {
        $estrutura = json_decode($j['estrutura'] ?? '{}', true) ?: [];
        $turmasSelecionadas = $estrutura['turmas_selecionadas'] ?? [];
        $alunosSelecionados = $estrutura['alunos_selecionados'] ?? [];
        $tipoSelecaoAlunos = $estrutura['tipo_selecao_alunos'] ?? 'todos';

        $whereAudience = '(a.turma_id = :turma_id OR EXISTS (
            SELECT 1 FROM alunos_turmas_historico h
            WHERE h.aluno_id = a.id AND h.turma_id = :turma_id_hist
        ))';
        $paramsShow = [
            'turma_id' => (int) $j['turma_id'],
            'turma_id_hist' => (int) $j['turma_id'],
        ];

        if (!empty($turmasSelecionadas) && is_array($turmasSelecionadas)) {
            $turmaClauses = [];
            foreach (array_values($turmasSelecionadas) as $idx => $turmaId) {
                $paramAtual = 'turma_id_' . $idx;
                $paramHist = 'turma_id_hist_' . $idx;
                $turmaClauses[] = "(a.turma_id = :{$paramAtual} OR EXISTS (
                    SELECT 1 FROM alunos_turmas_historico h
                    WHERE h.aluno_id = a.id AND h.turma_id = :{$paramHist}
                ))";
                $paramsShow[$paramAtual] = (int) $turmaId;
                $paramsShow[$paramHist] = (int) $turmaId;
            }
            if (!empty($turmaClauses)) {
                $whereAudience = '(' . implode(' OR ', $turmaClauses) . ')';
                unset($paramsShow['turma_id'], $paramsShow['turma_id_hist']);
            }
        }

        if ($tipoSelecaoAlunos === 'selecionados' && !empty($alunosSelecionados) && is_array($alunosSelecionados)) {
            $alunoParams = [];
            foreach (array_values($alunosSelecionados) as $idx => $alunoId) {
                $paramName = 'aluno_id_' . $idx;
                $alunoParams[] = ':' . $paramName;
                $paramsShow[$paramName] = (int) $alunoId;
            }
            if (!empty($alunoParams)) {
                $whereAudience = '(' . $whereAudience . ') AND a.id IN (' . implode(', ', $alunoParams) . ')';
            }
        }

        $whereFinal = $whereAudience;
        if (($filtros['tipo'] ?? '') === 'usuario' && !empty($filtros['aluno_id'])) {
            $whereFinal = 'a.id = :filtro_aluno_id AND (' . $whereAudience . ')';
            $paramsShow['filtro_aluno_id'] = (int) $filtros['aluno_id'];
        } elseif (($filtros['tipo'] ?? '') === 'turma' && !empty($filtros['turma_id'])) {
            $tid = (int) $filtros['turma_id'];
            $whereFinal = '(a.turma_id = :scope_turma OR EXISTS (
                SELECT 1 FROM alunos_turmas_historico h
                WHERE h.aluno_id = a.id AND h.turma_id = :scope_turma_hist
            )) AND (' . $whereAudience . ')';
            $paramsShow['scope_turma'] = $tid;
            $paramsShow['scope_turma_hist'] = $tid;
        }

        $sql = "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome
                FROM alunos a
                INNER JOIN turmas t ON a.turma_id = t.id
                WHERE ({$whereFinal}) AND a.ativo = 1
                ORDER BY a.nome ASC";

        try {
            return $this->db->fetchAll($sql, $paramsShow);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<int>
     */
    private function fetchAlunoIdsJornadaConcluida(int $jornadaId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT aluno_id FROM jornadas_progresso_alunos jpa
             WHERE jpa.jornada_id = :jid
             AND jpa.status = 'concluido'
             AND (jpa.atividade_tipo IS NULL OR jpa.atividade_tipo = 'jornada_concluida')
             AND jpa.modulo_id IS NULL AND jpa.aula_id IS NULL
             AND jpa.exercicio_id IS NULL AND jpa.exercicio_modulo_id IS NULL",
            ['jid' => $jornadaId]
        );

        return array_map('intval', array_column($rows, 'aluno_id'));
    }

    /**
     * @param list<int> $alunoIds
     * @return array<int,string>
     */
    private function fetchUltimaAtividadePorAlunoJornada(int $jornadaId, array $alunoIds): array
    {
        if ($alunoIds === []) {
            return [];
        }
        $alunoIds = array_values(array_unique(array_map('intval', $alunoIds)));
        $placeholders = implode(',', array_fill(0, count($alunoIds), '?'));
        $params = array_merge([$jornadaId], $alunoIds);
        $sql = "SELECT aluno_id, MAX(updated_at) AS mx FROM jornadas_progresso_alunos
                WHERE jornada_id = ? AND aluno_id IN ({$placeholders})
                GROUP BY aluno_id";
        $rows = $this->db->fetchAll($sql, $params);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['aluno_id']] = $r['mx'] ?? null;
        }

        return $out;
    }

    /**
     * Soma tempo_gasto (segundos) por aluno na jornada, todas as linhas de progresso.
     *
     * @param list<int> $alunoIds
     * @return array<int,int> aluno_id => segundos
     */
    private function fetchTempoPorAlunoJornada(int $jornadaId, array $alunoIds): array
    {
        if ($alunoIds === []) {
            return [];
        }
        $alunoIds = array_values(array_unique(array_map('intval', $alunoIds)));
        $placeholders = implode(',', array_fill(0, count($alunoIds), '?'));
        $params = array_merge([$jornadaId], $alunoIds);
        $sql = "SELECT aluno_id, COALESCE(SUM(COALESCE(tempo_gasto, 0)), 0) AS seg
                FROM jornadas_progresso_alunos
                WHERE jornada_id = ? AND aluno_id IN ({$placeholders})
                GROUP BY aluno_id";
        try {
            $rows = $this->db->fetchAll($sql, $params);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['aluno_id']] = (int) ($r['seg'] ?? 0);
        }

        return $out;
    }
}
