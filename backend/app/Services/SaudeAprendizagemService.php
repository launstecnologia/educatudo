<?php

namespace App\Services;

/**
 * Diagnóstico read-only de sinais pedagógicos.
 *
 * A classificação serve para priorizar acompanhamento humano. Ela não altera
 * notas, não reprova alunos e não deve ser tratada como diagnóstico clínico.
 */
class SaudeAprendizagemService
{
    public const NIVEL_CRITICO = 'critico';
    public const NIVEL_ATENCAO = 'atencao';
    public const NIVEL_MONITORAR = 'monitorar';
    public const NIVEL_SAUDAVEL = 'saudavel';
    public const NIVEL_SEM_DADOS = 'sem_dados';

    public const NIVEIS = [
        self::NIVEL_CRITICO,
        self::NIVEL_ATENCAO,
        self::NIVEL_MONITORAR,
        self::NIVEL_SAUDAVEL,
        self::NIVEL_SEM_DADOS,
    ];

    private $db;
    private array $tableCache = [];
    private array $columnCache = [];

    public function __construct()
    {
        $this->db = \Database::getInstance();
    }

    /**
     * @return array{linhas:list<array<string,mixed>>,kpis:array<string,int>,fontes:array<string,bool>,regras:array<string,mixed>}
     */
    public function analisar(int $anoLetivoId, int $turmaId = 0, ?string $nivel = null): array
    {
        $ano = $this->buscarAnoLetivo($anoLetivoId);
        $fontes = $this->fontesDisponiveis();
        $regras = $this->regras();
        $kpis = array_fill_keys(array_merge(['total'], self::NIVEIS), 0);

        if ($ano === null) {
            return ['linhas' => [], 'kpis' => $kpis, 'fontes' => $fontes, 'regras' => $regras];
        }

        $alunos = $this->buscarAlunos($anoLetivoId, $turmaId);
        if ($alunos === []) {
            return ['linhas' => [], 'kpis' => $kpis, 'fontes' => $fontes, 'regras' => $regras];
        }

        $ids = array_values(array_map(static fn (array $a): int => (int) $a['aluno_id'], $alunos));
        $turmasPorAluno = [];
        foreach ($alunos as $aluno) {
            $turmasPorAluno[(int) $aluno['aluno_id']] = (int) ($aluno['turma_id'] ?? 0);
        }
        $inicio = (string) ($ano['data_inicio'] ?: $ano['ano'] . '-01-01');
        $fim = (string) ($ano['data_fim'] ?: $ano['ano'] . '-12-31');
        $anoNumero = (int) $ano['ano'];

        $boletim = $this->carregarFonte('boletim', $fontes, fn (): array => $this->agregarBoletim($ids, $anoLetivoId, $anoNumero, $inicio, $fim));
        $provas = $this->carregarFonte('provas', $fontes, fn (): array => $this->agregarProvas($ids, $inicio, $fim));
        $exercicios = $this->carregarFonte('exercicios', $fontes, fn (): array => $this->agregarExercicios($ids, $inicio, $fim));
        $jornadas = $this->carregarFonte('jornadas', $fontes, fn (): array => $this->agregarJornadas($ids, $anoNumero));
        $faltas = $this->carregarFonte('faltas', $fontes, fn (): array => $this->agregarFaltas($turmasPorAluno, $anoLetivoId, $anoNumero));

        $linhas = [];
        foreach ($alunos as $aluno) {
            $id = (int) $aluno['aluno_id'];
            $notaConsolidada = $boletim[$id] ?? null;
            $linha = array_merge($aluno, [
                'notas_fonte' => $notaConsolidada['fonte'] ?? (!empty($provas[$id]) ? 'provas_online' : null),
                'notas_total' => (int) ($notaConsolidada['total'] ?? ($provas[$id]['total'] ?? 0)),
                'notas_media' => isset($notaConsolidada) ? round((float) $notaConsolidada['media'], 2) : null,
                'notas_minima' => isset($notaConsolidada) ? round((float) $notaConsolidada['nota_minima'], 2) : null,
                'notas_abaixo' => (int) ($notaConsolidada['abaixo'] ?? 0),
                'notas_indice_pct' => isset($notaConsolidada) ? round((float) $notaConsolidada['indice_pct'], 1) : null,
                'provas_total' => (int) ($provas[$id]['total'] ?? 0),
                'provas_media_pct' => isset($provas[$id]) ? round((float) $provas[$id]['media_pct'], 1) : null,
                'exercicios_total' => (int) ($exercicios[$id]['total'] ?? 0),
                'exercicios_media_pct' => isset($exercicios[$id]) ? round((float) $exercicios[$id]['media_pct'], 1) : null,
                'jornadas_modulos_total' => (int) ($jornadas[$id]['total'] ?? 0),
                'jornadas_modulos_concluidos' => (int) ($jornadas[$id]['concluidos'] ?? 0),
                'jornadas_progresso_pct' => isset($jornadas[$id]) ? round((float) $jornadas[$id]['percentual'], 1) : null,
                'faltas_total' => isset($faltas[$id]) ? round((float) $faltas[$id], 1) : null,
            ]);
            $linha = array_merge($linha, $this->classificar($linha, $regras));
            $kpis['total']++;
            $kpis[$linha['nivel']]++;
            if ($nivel === null || $nivel === '' || $linha['nivel'] === $nivel) {
                $linhas[] = $linha;
            }
        }

        $ordem = [self::NIVEL_CRITICO => 0, self::NIVEL_ATENCAO => 1, self::NIVEL_MONITORAR => 2, self::NIVEL_SAUDAVEL => 3, self::NIVEL_SEM_DADOS => 4];
        usort($linhas, static function (array $a, array $b) use ($ordem): int {
            $cmp = ($ordem[$a['nivel']] ?? 9) <=> ($ordem[$b['nivel']] ?? 9);
            if ($cmp !== 0) return $cmp;
            $cmp = ((int) $b['pontuacao_risco']) <=> ((int) $a['pontuacao_risco']);
            return $cmp !== 0 ? $cmp : strcasecmp((string) $a['nome'], (string) $b['nome']);
        });

        return ['linhas' => $linhas, 'kpis' => $kpis, 'fontes' => $fontes, 'regras' => $regras];
    }

    public function rotuloNivel(string $nivel): string
    {
        return match ($nivel) {
            self::NIVEL_CRITICO => 'Crítico',
            self::NIVEL_ATENCAO => 'Atenção',
            self::NIVEL_MONITORAR => 'Monitorar',
            self::NIVEL_SAUDAVEL => 'Saudável',
            self::NIVEL_SEM_DADOS => 'Sem dados suficientes',
            default => $nivel,
        };
    }

    /** @return array<string,mixed> */
    public function regras(): array
    {
        return [
            'prova_critica' => 40.0,
            'prova_atencao' => 60.0,
            'exercicio_critico' => 40.0,
            'exercicio_atencao' => 60.0,
            'jornada_critica' => 30.0,
            'jornada_atencao' => 60.0,
            'faltas_criticas' => 20.0,
            'faltas_atencao' => 10.0,
            'min_exercicios' => 2,
        ];
    }

    /** @return array<string,mixed> */
    private function classificar(array $linha, array $r): array
    {
        $pontos = 0;
        $indicadores = 0;
        $indicadoresAcademicos = 0;
        $motivos = [];

        if ($linha['notas_media'] !== null && $linha['notas_total'] > 0) {
            $indicadores++;
            $indicadoresAcademicos++;
            $media = (float) $linha['notas_media'];
            $minima = max(0.01, (float) ($linha['notas_minima'] ?? 6.0));
            $abaixo = (int) ($linha['notas_abaixo'] ?? 0);
            $total = max(1, (int) $linha['notas_total']);
            $proporcaoAbaixo = $abaixo / $total;
            if ($media < ($minima * 0.7) || $proporcaoAbaixo >= 0.5) {
                $pontos += 3;
                $motivos[] = ['tipo' => 'notas', 'severidade' => 'alta', 'texto' => 'Desempenho consolidado muito abaixo do esperado (' . $this->nota($media) . '; média mínima ' . $this->nota($minima) . ').'];
            } elseif ($media < $minima || $abaixo > 0) {
                $pontos += 2;
                $motivos[] = ['tipo' => 'notas', 'severidade' => 'media', 'texto' => $abaixo . ' resultado(s) abaixo da média mínima; média consolidada ' . $this->nota($media) . '.'];
            }
        } elseif ($linha['provas_media_pct'] !== null && $linha['provas_total'] > 0) {
            $indicadores++;
            $indicadoresAcademicos++;
            $v = (float) $linha['provas_media_pct'];
            if ($v < $r['prova_critica']) {
                $pontos += 3;
                $motivos[] = ['tipo' => 'provas', 'severidade' => 'alta', 'texto' => 'Aproveitamento muito baixo nas provas on-line (' . $this->pct($v) . ').'];
            } elseif ($v < $r['prova_atencao']) {
                $pontos += 2;
                $motivos[] = ['tipo' => 'provas', 'severidade' => 'media', 'texto' => 'Aproveitamento em provas on-line abaixo de 60% (' . $this->pct($v) . ').'];
            }
        }

        if ($linha['exercicios_media_pct'] !== null && $linha['exercicios_total'] >= $r['min_exercicios']) {
            $indicadores++;
            $indicadoresAcademicos++;
            $v = (float) $linha['exercicios_media_pct'];
            if ($v < $r['exercicio_critico']) {
                $pontos += 2;
                $motivos[] = ['tipo' => 'exercicios', 'severidade' => 'alta', 'texto' => 'Baixo aproveitamento em exercícios (' . $this->pct($v) . ').'];
            } elseif ($v < $r['exercicio_atencao']) {
                $pontos += 1;
                $motivos[] = ['tipo' => 'exercicios', 'severidade' => 'media', 'texto' => 'Aproveitamento em exercícios abaixo de 60% (' . $this->pct($v) . ').'];
            }
        }

        if ($linha['jornadas_progresso_pct'] !== null && $linha['jornadas_modulos_total'] > 0) {
            $indicadores++;
            $indicadoresAcademicos++;
            $v = (float) $linha['jornadas_progresso_pct'];
            if ($v < $r['jornada_critica']) {
                $pontos += 2;
                $motivos[] = ['tipo' => 'jornadas', 'severidade' => 'alta', 'texto' => 'Progresso muito baixo nas jornadas (' . $this->pct($v) . ').'];
            } elseif ($v < $r['jornada_atencao']) {
                $pontos += 1;
                $motivos[] = ['tipo' => 'jornadas', 'severidade' => 'media', 'texto' => 'Menos de 60% dos módulos de jornadas concluídos (' . $this->pct($v) . ').'];
            }
        }

        if ($linha['faltas_total'] !== null) {
            $indicadores++;
            $v = (float) $linha['faltas_total'];
            if ($v >= $r['faltas_criticas']) {
                $pontos += 3;
                $motivos[] = ['tipo' => 'faltas', 'severidade' => 'alta', 'texto' => $this->numero($v) . ' faltas registradas no período.'];
            } elseif ($v >= $r['faltas_atencao']) {
                $pontos += 1;
                $motivos[] = ['tipo' => 'faltas', 'severidade' => 'media', 'texto' => $this->numero($v) . ' faltas registradas no período.'];
            }
        }

        if ($indicadoresAcademicos === 0 && $pontos === 0) {
            $nivel = self::NIVEL_SEM_DADOS;
        } elseif ($pontos >= 4) {
            $nivel = self::NIVEL_CRITICO;
        } elseif ($pontos >= 2) {
            $nivel = self::NIVEL_ATENCAO;
        } elseif ($pontos === 1) {
            $nivel = self::NIVEL_MONITORAR;
        } else {
            $nivel = self::NIVEL_SAUDAVEL;
        }

        return ['nivel' => $nivel, 'pontuacao_risco' => $pontos, 'indicadores_disponiveis' => $indicadores, 'motivos' => $motivos];
    }

    /** @return array<string,mixed>|null */
    private function buscarAnoLetivo(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('ano_letivo')) return null;
        $row = $this->db->fetch('SELECT id, ano, data_inicio, data_fim FROM ano_letivo WHERE id = :id LIMIT 1', ['id' => $id]);
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    private function buscarAlunos(int $anoLetivoId, int $turmaId): array
    {
        $sql = "SELECT DISTINCT a.id AS aluno_id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome
                FROM alunos a
                INNER JOIN turmas t ON t.id = a.turma_id
                WHERE a.ativo = 1 AND a.turma_id IS NOT NULL";
        $params = [];
        if ($turmaId > 0) {
            $sql .= ' AND a.turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        }
        if ($this->tableExists('matricula')) {
            $sql .= " AND EXISTS (SELECT 1 FROM matricula m WHERE m.aluno_id = a.id AND m.ano_letivo_id = :ano_id AND m.status = 'ativa' AND m.data_saida IS NULL)";
            $params['ano_id'] = $anoLetivoId;
        }
        $sql .= ' ORDER BY a.nome ASC LIMIT 500';
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    private function agregarProvas(array $ids, string $inicio, string $fim): array
    {
        if ($ids === []) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$inicio . ' 00:00:00', $fim . ' 23:59:59']);
        $statsJoin = $this->tableExists('provas_respostas')
            ? "LEFT JOIN (
                SELECT prova_id, aluno_id,
                       COUNT(*) AS total_questoes,
                       SUM(CASE WHEN correta = 1 THEN 1 ELSE 0 END) AS acertos
                FROM provas_respostas
                WHERE correta IS NOT NULL
                GROUP BY prova_id, aluno_id
               ) stats ON stats.prova_id = pr.prova_id AND stats.aluno_id = pr.aluno_id"
            : 'LEFT JOIN (SELECT NULL AS prova_id, NULL AS aluno_id, 0 AS total_questoes, 0 AS acertos) stats ON 1 = 0';
        $deletedFilter = $this->columnExists('provas', 'deleted_at') ? ' AND p.deleted_at IS NULL' : '';
        $rows = $this->db->fetchAll(
            "SELECT pr.aluno_id, COUNT(*) AS total,
                    AVG(
                        CASE
                            WHEN stats.total_questoes > 0 THEN (stats.acertos / stats.total_questoes) * 100
                            WHEN p.valor_total <= 10 THEN (pr.nota / NULLIF(p.valor_total, 0)) * 100
                            WHEN pr.nota <= 10 AND p.valor_total > 10 THEN pr.nota * 10
                            ELSE (pr.nota / NULLIF(p.valor_total, 0)) * 100
                        END
                    ) AS media_pct
             FROM provas_realizacoes pr
             INNER JOIN provas p ON p.id = pr.prova_id
             $statsJoin
             WHERE pr.aluno_id IN ($ph)
               AND pr.status = 'finalizado' AND pr.nota IS NOT NULL
               AND p.valor_total > 0 $deletedFilter
               AND COALESCE(pr.finalizado_em, pr.updated_at, pr.created_at) BETWEEN ? AND ?
             GROUP BY pr.aluno_id",
            $params
        ) ?: [];
        return $this->indexar($rows, 'aluno_id');
    }

    /**
     * Usa primeiro o boletim oficial gerado. Para alunos ainda sem boletim,
     * usa notas digitadas em eventos de lançamento. Ambas preservam a escala
     * usada pela escola e são comparadas com a média mínima configurada.
     *
     * @return array<int,array<string,mixed>>
     */
    private function agregarBoletim(array $ids, int $anoLetivoId, int $ano, string $inicio, string $fim): array
    {
        if ($ids === []) return [];
        $out = [];

        if ($this->tableExists('boletim_resultados_gerados') && $this->tableExists('boletim_regras')) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $previewFilter = $this->columnExists('boletim_resultados_gerados', 'preview') ? ' AND g.preview = 0' : '';
            $notaMinExpr = $this->columnExists('boletim_regras', 'nota_minima_aprovacao')
                ? 'COALESCE(r.nota_minima_aprovacao, 6)'
                : '6';
            $materiaGroup = $this->columnExists('boletim_resultados_gerados', 'materia_ref')
                ? 'g2.materia_ref'
                : ($this->columnExists('boletim_resultados_gerados', 'materia_id') ? 'g2.materia_id' : 'g2.materia_nome');
            $anoFilter = $this->columnExists('boletim_regras', 'ano_letivo')
                ? " AND (r.ano_letivo = ? OR (r.ano_letivo IS NULL AND (g.data_inicio BETWEEN ? AND ? OR g.data_fim BETWEEN ? AND ? OR YEAR(g.created_at) = ?)))"
                : " AND (g.data_inicio BETWEEN ? AND ? OR g.data_fim BETWEEN ? AND ? OR YEAR(g.created_at) = ?)";
            $params = $ids;
            if ($this->columnExists('boletim_regras', 'ano_letivo')) $params[] = $ano;
            array_push($params, $inicio, $fim, $inicio, $fim, $ano);

            $rows = $this->db->fetchAll(
                "SELECT g.aluno_id, COUNT(*) AS total,
                        AVG(g.media_final) AS media,
                        AVG($notaMinExpr) AS nota_minima,
                        SUM(CASE WHEN g.media_final < $notaMinExpr THEN 1 ELSE 0 END) AS abaixo,
                        AVG((g.media_final / NULLIF($notaMinExpr, 0)) * 100) AS indice_pct
                 FROM boletim_resultados_gerados g
                 INNER JOIN boletim_regras r ON r.id = g.regra_id AND r.ativo = 1
                 INNER JOIN (
                    SELECT MAX(g2.id) AS id
                    FROM boletim_resultados_gerados g2
                    WHERE g2.aluno_id IN ($ph)" . ($this->columnExists('boletim_resultados_gerados', 'preview') ? ' AND g2.preview = 0' : '') . "
                    GROUP BY g2.regra_id, g2.aluno_id, g2.periodo_ref, $materiaGroup
                 ) atual ON atual.id = g.id
                 WHERE g.media_final IS NOT NULL $previewFilter $anoFilter
                 GROUP BY g.aluno_id",
                $params
            ) ?: [];
            foreach ($rows as $row) {
                $row['fonte'] = 'boletim';
                $out[(int) $row['aluno_id']] = $row;
            }
        }

        if ($this->tableExists('provas_blocos_notas_lancadas') && $this->tableExists('provas_blocos')) {
            $faltantes = array_values(array_filter($ids, static fn (int $id): bool => !isset($out[$id])));
            if ($faltantes !== []) {
                $ph = implode(',', array_fill(0, count($faltantes), '?'));
                $filtroAno = $this->columnExists('provas_blocos', 'ano_letivo')
                    ? ' AND (pb.ano_letivo = ? OR (pb.ano_letivo IS NULL AND COALESCE(pb.data_prova, DATE(pb.created_at)) BETWEEN ? AND ?))'
                    : ' AND COALESCE(pb.data_prova, DATE(pb.created_at)) BETWEEN ? AND ?';
                $params = $faltantes;
                if ($this->columnExists('provas_blocos', 'ano_letivo')) $params[] = $ano;
                $params[] = $inicio;
                $params[] = $fim;
                $notaMinima = $this->buscarNotaMinimaPadrao($anoLetivoId, $ano);
                $params[] = $notaMinima;
                $params[] = $notaMinima;
                $params[] = $notaMinima;

                $deletedFilter = $this->columnExists('provas_blocos', 'deleted_at') ? ' AND pb.deleted_at IS NULL' : '';
                $rows = $this->db->fetchAll(
                    "SELECT n.aluno_id, COUNT(*) AS total, AVG(n.nota) AS media,
                            ? AS nota_minima,
                            SUM(CASE WHEN n.nota < ? THEN 1 ELSE 0 END) AS abaixo,
                            AVG((n.nota / NULLIF(?, 0)) * 100) AS indice_pct
                     FROM provas_blocos_notas_lancadas n
                     INNER JOIN provas_blocos pb ON pb.id = n.bloco_id $deletedFilter
                     WHERE n.aluno_id IN ($ph) AND n.nota IS NOT NULL $filtroAno
                     GROUP BY n.aluno_id",
                    array_merge(array_slice($params, -3), array_slice($params, 0, -3))
                ) ?: [];
                foreach ($rows as $row) {
                    $row['fonte'] = 'evento_notas';
                    $out[(int) $row['aluno_id']] = $row;
                }
            }
        }

        return $out;
    }

    private function buscarNotaMinimaPadrao(int $anoLetivoId, int $ano): float
    {
        if (!$this->tableExists('boletim_regras') || !$this->columnExists('boletim_regras', 'nota_minima_aprovacao')) return 6.0;
        try {
            $anoFilter = $this->columnExists('boletim_regras', 'ano_letivo') ? ' AND (ano_letivo = :ano OR ano_letivo = :ano_id)' : '';
            $row = $this->db->fetch(
                "SELECT AVG(nota_minima_aprovacao) AS media
                 FROM boletim_regras
                 WHERE ativo = 1 AND nota_minima_aprovacao IS NOT NULL $anoFilter",
                $anoFilter !== '' ? ['ano' => $ano, 'ano_id' => $anoLetivoId] : []
            );
            $nota = (float) ($row['media'] ?? 0);
            return $nota > 0 ? $nota : 6.0;
        } catch (\Throwable $e) {
            return 6.0;
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function agregarExercicios(array $ids, string $inicio, string $fim): array
    {
        if ($ids === []) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$inicio . ' 00:00:00', $fim . ' 23:59:59']);
        $rows = $this->db->fetchAll(
            "SELECT aluno_id, COUNT(*) AS total, AVG(percentual_acerto) AS media_pct
             FROM exercicios_historico
             WHERE aluno_id IN ($ph) AND status = 'finalizado'
               AND data_execucao BETWEEN ? AND ?
             GROUP BY aluno_id",
            $params
        ) ?: [];
        return $this->indexar($rows, 'aluno_id');
    }

    /** @return array<int,array<string,mixed>> */
    private function agregarJornadas(array $ids, int $ano): array
    {
        if ($ids === []) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $filtroAno = $this->columnExists('jornadas', 'ano_letivo')
            ? ' AND (j.ano_letivo = ? OR (j.ano_letivo IS NULL AND YEAR(j.created_at) = ?))'
            : ' AND YEAR(j.created_at) = ?';
        $params = $ids;
        if ($this->columnExists('jornadas', 'ano_letivo')) {
            $params[] = $ano;
            $params[] = $ano;
        } else {
            $params[] = $ano;
        }
        $rows = $this->db->fetchAll(
            "SELECT a.id AS aluno_id,
                    COUNT(DISTINCT jm.id) AS total,
                    COUNT(DISTINCT CASE WHEN jpa.status = 'concluido' THEN jm.id END) AS concluidos
             FROM alunos a
             INNER JOIN jornadas j ON j.turma_id = a.turma_id AND j.ativo = 1
             INNER JOIN jornadas_modulos jm ON jm.jornada_id = j.id AND COALESCE(jm.status, 'ativo') = 'ativo'
             LEFT JOIN jornadas_progresso_alunos jpa
               ON jpa.aluno_id = a.id AND jpa.jornada_id = j.id AND jpa.modulo_id = jm.id
              AND jpa.atividade_tipo = 'modulo'
             WHERE a.id IN ($ph) $filtroAno
             GROUP BY a.id",
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $total = (int) ($row['total'] ?? 0);
            $concluidos = (int) ($row['concluidos'] ?? 0);
            $row['percentual'] = $total > 0 ? min(100, ($concluidos / $total) * 100) : 0;
            $out[(int) $row['aluno_id']] = $row;
        }
        return $out;
    }

    /** @return array<int,float> */
    private function agregarFaltas(array $turmasPorAluno, int $anoLetivoId, int $ano): array
    {
        $ids = array_keys($turmasPorAluno);
        if ($ids === []) return [];
        $eventos = $this->db->fetchAll(
            'SELECT id, turmas_json FROM faltas_eventos WHERE ativo = 1 AND ano_letivo IN (?, ?)',
            [$ano, $anoLetivoId]
        ) ?: [];
        if ($eventos === []) return [];

        $eventoIds = [];
        $turmasComEvento = [];
        foreach ($eventos as $evento) {
            $turmas = json_decode((string) ($evento['turmas_json'] ?? '[]'), true);
            if (!is_array($turmas)) continue;
            $turmas = array_values(array_filter(array_map('intval', $turmas), static fn (int $id): bool => $id > 0));
            if ($turmas === []) continue;
            $eventoIds[] = (int) $evento['id'];
            foreach ($turmas as $tid) $turmasComEvento[$tid] = true;
        }
        if ($eventoIds === []) return [];

        $out = [];
        foreach ($turmasPorAluno as $alunoId => $turmaId) {
            if (isset($turmasComEvento[(int) $turmaId])) $out[(int) $alunoId] = 0.0;
        }

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pe = implode(',', array_fill(0, count($eventoIds), '?'));
        $params = array_merge($ids, $eventoIds);
        $rows = $this->db->fetchAll(
            "SELECT fl.aluno_id, SUM(fl.faltas) AS total_faltas
             FROM faltas_lancamentos fl
             WHERE fl.aluno_id IN ($ph) AND fl.evento_id IN ($pe)
             GROUP BY fl.aluno_id",
            $params
        ) ?: [];
        foreach ($rows as $row) $out[(int) $row['aluno_id']] = (float) ($row['total_faltas'] ?? 0);
        return $out;
    }

    /** @return array<string,bool> */
    private function fontesDisponiveis(): array
    {
        return [
            'boletim' => ($this->tableExists('boletim_resultados_gerados') && $this->tableExists('boletim_regras'))
                || ($this->tableExists('provas_blocos_notas_lancadas') && $this->tableExists('provas_blocos')),
            'provas' => $this->tableExists('provas_realizacoes') && $this->tableExists('provas'),
            'exercicios' => $this->tableExists('exercicios_historico'),
            'jornadas' => $this->tableExists('jornadas') && $this->tableExists('jornadas_modulos') && $this->tableExists('jornadas_progresso_alunos'),
            'faltas' => $this->tableExists('faltas_eventos') && $this->tableExists('faltas_lancamentos'),
        ];
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableCache)) {
            try {
                $this->tableCache[$table] = $this->db->fetch("SHOW TABLES LIKE '{$table}'") !== false;
            } catch (\Throwable $e) {
                $this->tableCache[$table] = false;
            }
        }
        return $this->tableCache[$table];
    }

    /**
     * Uma escola pode estar em versão de schema diferente. Se uma fonte falhar,
     * a análise continua com as demais e a interface avisa que ela não foi usada.
     *
     * @param array<string,bool> $fontes
     * @param callable():array $loader
     * @return array<mixed>
     */
    private function carregarFonte(string $nome, array &$fontes, callable $loader): array
    {
        if (empty($fontes[$nome])) return [];
        try {
            return $loader();
        } catch (\Throwable $e) {
            $fontes[$nome] = false;
            try {
                if (class_exists('Logger')) {
                    \Logger::warning('Saúde da Aprendizagem: fonte indisponível', [
                        'fonte' => $nome,
                        'erro' => $e->getMessage(),
                    ], 'saude_academica');
                }
            } catch (\Throwable $ignored) {
                // O diagnóstico deve continuar mesmo quando o logger não estiver disponível.
            }
            return [];
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $this->columnCache)) {
            try {
                $this->columnCache[$key] = $this->db->fetch("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'") !== false;
            } catch (\Throwable $e) {
                $this->columnCache[$key] = false;
            }
        }
        return $this->columnCache[$key];
    }

    /** @return array<int,array<string,mixed>> */
    private function indexar(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) $out[(int) ($row[$key] ?? 0)] = $row;
        return $out;
    }

    private function pct(float $value): string
    {
        return number_format($value, 1, ',', '.') . '%';
    }

    private function numero(float $value): string
    {
        return number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 1, ',', '.');
    }

    private function nota(float $value): string
    {
        return number_format($value, 1, ',', '.');
    }
}
