<?php
/**
 * Monta dados analíticos para o dashboard de resultados de blocos de prova (admin).
 */

namespace App\Services;

class ExamBlockResultsDashboardService
{
    private const MEDIA_MINIMA = 55.0;
    private const META_BOA = 70.0;

    /** @var \Database */
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @return array<string,mixed>
     */
    public function build(int $blocoId, array $bloco): array
    {
        $provas = $this->fetchProvas($blocoId);
        $provaIds = array_column($provas, 'id');
        $totalCanceladas = $this->countCanceladas($provaIds);

        if (empty($provaIds)) {
            return $this->emptyPayload($totalCanceladas);
        }

        $placeholders = implode(',', array_fill(0, count($provaIds), '?'));
        $eligible = $this->fetchEligibleStudents($blocoId, $bloco);
        $eligibleById = [];
        foreach ($eligible as $a) {
            $eligibleById[(int) $a['id']] = $a;
        }

        $porQuestao = $this->db->fetchAll(
            "SELECT r.prova_id, r.questao_id,
                    COUNT(*) as total_respostas,
                    SUM(IF(r.correta = 1, 1, 0)) as total_acertos
             FROM provas_respostas r
             INNER JOIN provas_realizacoes pr
                ON r.prova_id = pr.prova_id AND r.aluno_id = pr.aluno_id AND pr.status = 'finalizado'
             WHERE r.prova_id IN ($placeholders)
             GROUP BY r.prova_id, r.questao_id",
            $provaIds
        );

        $questoesStats = $this->buildQuestoesStats($porQuestao, $provas);
        $porMateria = $this->buildPorMateria($provas, $porQuestao);

        $porAlunoAgg = $this->db->fetchAll(
            "SELECT r.aluno_id,
                    COUNT(*) as total_respostas,
                    SUM(IF(r.correta = 1, 1, 0)) as total_acertos
             FROM provas_respostas r
             INNER JOIN provas_realizacoes pr
                ON r.prova_id = pr.prova_id AND r.aluno_id = pr.aluno_id AND pr.status = 'finalizado'
             WHERE r.prova_id IN ($placeholders)
             GROUP BY r.aluno_id",
            $provaIds
        );

        $porAlunoProva = $this->db->fetchAll(
            "SELECT r.aluno_id, r.prova_id,
                    COUNT(*) as total_respostas,
                    SUM(IF(r.correta = 1, 1, 0)) as total_acertos
             FROM provas_respostas r
             INNER JOIN provas_realizacoes pr
                ON r.prova_id = pr.prova_id AND r.aluno_id = pr.aluno_id AND pr.status = 'finalizado'
             WHERE r.prova_id IN ($placeholders)
             GROUP BY r.aluno_id, r.prova_id",
            $provaIds
        );

        $realizacoes = $this->db->fetchAll(
            "SELECT pr.aluno_id, pr.prova_id, pr.status, pr.tempo_gasto, pr.iniciado_em, pr.finalizado_em
             FROM provas_realizacoes pr
             WHERE pr.prova_id IN ($placeholders)",
            $provaIds
        );

        $realByAluno = [];
        foreach ($realizacoes as $r) {
            $aid = (int) $r['aluno_id'];
            if (!isset($realByAluno[$aid])) {
                $realByAluno[$aid] = [];
            }
            $realByAluno[$aid][] = $r;
        }

        $aggByAlunoId = [];
        foreach ($porAlunoAgg as $row) {
            $aggByAlunoId[(int) $row['aluno_id']] = $row;
        }

        $alunos = $this->buildAlunosLista(
            $eligibleById,
            $aggByAlunoId,
            $realByAluno,
            $provas,
            count($provaIds)
        );

        $indicadores = $this->buildIndicadores($alunos, $eligibleById);
        $porTurma = $this->buildPorTurma($alunos);
        $heatmap = $this->buildHeatmap($alunos, $porAlunoProva, $provas);
        $rankingAlunos = $this->buildRankingAlunos($alunos);
        $rankingTurmas = $this->buildRankingTurmas($porTurma);
        $filtros = $this->buildFiltros($alunos, $provas);

        return [
            'media_minima' => self::MEDIA_MINIMA,
            'meta_boa' => self::META_BOA,
            'provas' => $provas,
            'indicadores' => $indicadores,
            'por_turma' => $porTurma,
            'por_disciplina' => $porMateria,
            'heatmap' => $heatmap,
            'questoes_mais_erradas' => array_slice($questoesStats['mais_erradas'], 0, 10),
            'questoes_mais_acertadas' => array_slice($questoesStats['mais_acertadas'], 0, 10),
            'alunos' => $alunos,
            'alunos_atencao' => array_values(array_filter($alunos, static function (array $a): bool {
                return !empty($a['precisa_atencao']);
            })),
            'ranking_alunos' => $rankingAlunos,
            'ranking_turmas' => $rankingTurmas,
            'filtros' => $filtros,
            'total_canceladas' => $totalCanceladas,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyPayload(int $totalCanceladas): array
    {
        return [
            'media_minima' => self::MEDIA_MINIMA,
            'meta_boa' => self::META_BOA,
            'provas' => [],
            'indicadores' => [
                'aprovados' => 0,
                'precisam_atencao' => 0,
                'concluiram' => 0,
                'nao_realizaram' => 0,
                'media_geral' => 0.0,
                'tempo_medio_segundos' => 0,
                'tempo_medio_label' => '—',
                'total_elegiveis' => 0,
            ],
            'por_turma' => [],
            'por_disciplina' => [],
            'heatmap' => ['turmas' => [], 'disciplinas' => [], 'cells' => []],
            'questoes_mais_erradas' => [],
            'questoes_mais_acertadas' => [],
            'alunos' => [],
            'alunos_atencao' => [],
            'ranking_alunos' => [],
            'ranking_turmas' => [],
            'filtros' => [
                'turmas' => [],
                'series' => [],
                'professores' => [],
                'materias' => [],
                'status' => ['concluido', 'em_andamento', 'nao_iniciado', 'cancelada'],
            ],
            'total_canceladas' => $totalCanceladas,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchProvas(int $blocoId): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.titulo, p.materia_id, m.nome AS materia_nome, prof.id AS professor_id, prof.nome AS professor_nome
             FROM provas_blocos_vinculo pbp
             INNER JOIN provas p ON pbp.prova_id = p.id
             LEFT JOIN materias m ON p.materia_id = m.id
             LEFT JOIN professores prof ON p.professor_id = prof.id
             WHERE pbp.bloco_id = :bloco_id AND p.deleted_at IS NULL
             ORDER BY pbp.ordem ASC, m.nome ASC",
            ['bloco_id' => $blocoId]
        );
    }

    /**
     * @param list<int> $provaIds
     */
    private function countCanceladas(array $provaIds): int
    {
        if (empty($provaIds)) {
            return 0;
        }
        $ph = implode(',', array_fill(0, count($provaIds), '?'));
        $cnt = $this->db->fetch(
            "SELECT COUNT(*) as total FROM provas_realizacoes pr WHERE pr.prova_id IN ($ph) AND pr.status = 'cancelada'",
            $provaIds
        );
        return (int) ($cnt['total'] ?? 0);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeStudentRow(array $row): array
    {
        $ra = trim((string) ($row['ra'] ?? ''));
        if ($ra === '') {
            $ra = trim((string) ($row['codigo_aluno'] ?? ''));
        }
        if ($ra === '') {
            $ra = trim((string) ($row['nickname'] ?? ''));
        }
        $serie = trim((string) ($row['serie_aluno'] ?? ''));
        if ($serie === '') {
            $serie = trim((string) ($row['serie_turma'] ?? ''));
        }
        $row['ra'] = $ra;
        $row['serie'] = $serie;
        unset($row['serie_aluno'], $row['serie_turma'], $row['codigo_aluno'], $row['nickname']);
        return $row;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchEligibleStudents(int $blocoId, array $bloco): array
    {
        $alunos = $this->db->fetchAll(
            "SELECT DISTINCT a.id, a.nome, a.ra, a.codigo_aluno, a.nickname, a.serie AS serie_aluno,
                    t.id AS turma_id, t.nome AS turma_nome, t.serie AS serie_turma
             FROM alunos a
             INNER JOIN turmas t ON a.turma_id = t.id
             WHERE a.ativo = 1
             AND a.turma_id IN (
                SELECT turma_id FROM provas_blocos_turmas WHERE bloco_id = :bloco_id_1
                UNION
                SELECT pt.turma_id
                FROM provas_blocos_vinculo pbv
                INNER JOIN provas_turmas pt ON pt.prova_id = pbv.prova_id
                WHERE pbv.bloco_id = :bloco_id_2
                UNION
                SELECT p.turma_id
                FROM provas_blocos_vinculo pbv
                INNER JOIN provas p ON p.id = pbv.prova_id
                WHERE pbv.bloco_id = :bloco_id_3
                AND p.turma_id IS NOT NULL
             )
             ORDER BY a.nome ASC",
            [
                'bloco_id_1' => $blocoId,
                'bloco_id_2' => $blocoId,
                'bloco_id_3' => $blocoId,
            ]
        );

        if (empty($alunos) && !empty($bloco['turma_id'])) {
            $alunos = $this->db->fetchAll(
                "SELECT a.id, a.nome, a.ra, a.codigo_aluno, a.nickname, a.serie AS serie_aluno,
                        t.id AS turma_id, t.nome AS turma_nome, t.serie AS serie_turma
                 FROM alunos a
                 INNER JOIN turmas t ON a.turma_id = t.id
                 WHERE a.turma_id = :turma_id AND a.ativo = 1
                 ORDER BY a.nome ASC",
                ['turma_id' => $bloco['turma_id']]
            );
        }

        return array_map(fn (array $row): array => $this->normalizeStudentRow($row), $alunos);
    }

    /**
     * @param list<array<string,mixed>> $porQuestao
     * @param list<array<string,mixed>> $provas
     * @return array{mais_acertadas:list<array<string,mixed>>,mais_erradas:list<array<string,mixed>>}
     */
    private function buildQuestoesStats(array $porQuestao, array $provas): array
    {
        $questoesMap = [];
        foreach ($porQuestao as $row) {
            $key = $row['prova_id'] . '_' . $row['questao_id'];
            $total = (int) $row['total_respostas'];
            $acertos = (int) $row['total_acertos'];
            $erros = $total - $acertos;
            $questoesMap[$key] = [
                'prova_id' => (int) $row['prova_id'],
                'questao_id' => (int) $row['questao_id'],
                'total_respostas' => $total,
                'total_acertos' => $acertos,
                'total_erros' => $erros,
                'taxa_acerto' => $total > 0 ? round(100 * $acertos / $total, 1) : 0.0,
                'taxa_erro' => $total > 0 ? round(100 * $erros / $total, 1) : 0.0,
            ];
        }

        $questaoIds = array_unique(array_column($porQuestao, 'questao_id'));
        if (!empty($questaoIds)) {
            $ph = implode(',', array_map('intval', $questaoIds));
            $questoesInfo = $this->db->fetchAll("SELECT id, prova_id, enunciado, ordem FROM provas_questoes WHERE id IN ($ph)");
            $provasById = [];
            foreach ($provas as $p) {
                $provasById[$p['id']] = $p;
            }
            foreach ($questoesInfo as $q) {
                $k = $q['prova_id'] . '_' . $q['id'];
                if (!isset($questoesMap[$k])) {
                    continue;
                }
                $enunciado = strip_tags((string) ($q['enunciado'] ?? ''));
                $questoesMap[$k]['enunciado'] = $enunciado;
                $questoesMap[$k]['enunciado_curto'] = mb_strlen($enunciado) > 100
                    ? mb_substr($enunciado, 0, 100) . '…'
                    : $enunciado;
                $questoesMap[$k]['numero'] = (int) ($q['ordem'] ?? 0);
                $questoesMap[$k]['materia_nome'] = $provasById[$q['prova_id']]['materia_nome'] ?? '';
            }
        }

        $lista = array_values($questoesMap);
        usort($lista, static fn ($a, $b) => ($b['taxa_acerto'] ?? 0) <=> ($a['taxa_acerto'] ?? 0));
        $maisAcertadas = $lista;
        usort($lista, static fn ($a, $b) => ($b['taxa_erro'] ?? 0) <=> ($a['taxa_erro'] ?? 0));
        $maisErradas = $lista;

        return ['mais_acertadas' => $maisAcertadas, 'mais_erradas' => $maisErradas];
    }

    /**
     * @param list<array<string,mixed>> $provas
     * @param list<array<string,mixed>> $porQuestao
     * @return list<array<string,mixed>>
     */
    private function buildPorMateria(array $provas, array $porQuestao): array
    {
        $porMateria = [];
        foreach ($provas as $prova) {
            $pid = (int) $prova['id'];
            $totalRespostas = 0;
            $totalAcertos = 0;
            $questoesRespondidas = 0;
            foreach ($porQuestao as $row) {
                if ((int) $row['prova_id'] !== $pid) {
                    continue;
                }
                $totalRespostas += (int) $row['total_respostas'];
                $totalAcertos += (int) $row['total_acertos'];
                $questoesRespondidas++;
            }
            $totalErros = $totalRespostas - $totalAcertos;
            $percentual = $totalRespostas > 0 ? round(100 * $totalAcertos / $totalRespostas, 1) : 0.0;
            $porMateria[] = [
                'prova_id' => $pid,
                'materia_nome' => $prova['materia_nome'] ?? 'Sem matéria',
                'professor_nome' => trim((string) ($prova['professor_nome'] ?? '')),
                'professor_id' => (int) ($prova['professor_id'] ?? 0),
                'acertos' => $totalAcertos,
                'erros' => $totalErros,
                'total_questoes' => $questoesRespondidas,
                'percentual' => $percentual,
            ];
        }
        usort($porMateria, static fn ($a, $b) => ($a['percentual'] ?? 0) <=> ($b['percentual'] ?? 0));
        return $porMateria;
    }

    /**
     * @param array<int,array<string,mixed>> $eligibleById
     * @param array<int,array<string,mixed>> $aggByAlunoId
     * @param array<int,list<array<string,mixed>>> $realByAluno
     * @param list<array<string,mixed>> $provas
     * @return list<array<string,mixed>>
     */
    private function buildAlunosLista(
        array $eligibleById,
        array $aggByAlunoId,
        array $realByAluno,
        array $provas,
        int $totalProvasBloco
    ): array {
        $allIds = array_unique(array_merge(array_keys($eligibleById), array_keys($aggByAlunoId)));
        $alunos = [];

        foreach ($allIds as $alunoId) {
            $info = $eligibleById[$alunoId] ?? null;
            if ($info === null && isset($aggByAlunoId[$alunoId])) {
                $row = $this->db->fetch(
                    "SELECT a.id, a.nome, a.ra, a.codigo_aluno, a.nickname, a.serie AS serie_aluno,
                            t.id AS turma_id, t.nome AS turma_nome, t.serie AS serie_turma
                     FROM alunos a
                     LEFT JOIN turmas t ON t.id = a.turma_id
                     WHERE a.id = ?",
                    [$alunoId]
                );
                $info = $row
                    ? $this->normalizeStudentRow($row)
                    : ['id' => $alunoId, 'nome' => 'Aluno #' . $alunoId, 'ra' => '', 'turma_id' => 0, 'turma_nome' => '—', 'serie' => ''];
            }
            if ($info === null) {
                continue;
            }

            $agg = $aggByAlunoId[$alunoId] ?? null;
            $total = $agg ? (int) $agg['total_respostas'] : 0;
            $acertos = $agg ? (int) $agg['total_acertos'] : 0;
            $erros = $total - $acertos;
            $percent = $total > 0 ? round(100 * $acertos / $total, 1) : 0.0;

            $reals = $realByAluno[$alunoId] ?? [];
            $status = $this->resolveStatusAluno($reals, $totalProvasBloco, $total > 0);
            $tempoSeg = 0;
            $finalizadas = 0;
            foreach ($reals as $r) {
                if (($r['status'] ?? '') === 'finalizado') {
                    $tempoSeg += $this->resolveTempoSegundosRealizacao($r);
                    $finalizadas++;
                }
            }

            $precisaAtencao = $status === 'nao_iniciado'
                || $status === 'cancelada'
                || ($total > 0 && $percent < self::MEDIA_MINIMA)
                || ($total > 0 && $erros > 0 && ($erros / max(1, $total)) > 0.4);

            $alunos[] = [
                'aluno_id' => (int) $alunoId,
                'nome' => $info['nome'] ?? '',
                'ra' => $info['ra'] ?? '',
                'serie' => trim((string) ($info['serie'] ?? '')),
                'turma_id' => (int) ($info['turma_id'] ?? 0),
                'turma_nome' => $info['turma_nome'] ?? '—',
                'total_respostas' => $total,
                'total_acertos' => $acertos,
                'total_erros' => $erros,
                'percentual' => $percent,
                'tempo_segundos' => $tempoSeg,
                'tempo_label' => $this->formatTempo($tempoSeg),
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'aprovado' => $total > 0 && $percent >= self::MEDIA_MINIMA,
                'precisa_atencao' => $precisaAtencao,
                'provas_finalizadas' => $finalizadas,
                'provas_bloco' => $totalProvasBloco,
            ];
        }

        usort($alunos, static fn ($a, $b) => strcasecmp((string) $a['nome'], (string) $b['nome']));
        return $alunos;
    }

    /**
     * @param list<array<string,mixed>> $reals
     */
    private function resolveStatusAluno(array $reals, int $totalProvasBloco, bool $temRespostasFinalizadas): string
    {
        if (empty($reals)) {
            return 'nao_iniciado';
        }
        $hasCancelada = false;
        $hasIniciado = false;
        $finalizadas = 0;
        foreach ($reals as $r) {
            $st = (string) ($r['status'] ?? '');
            if ($st === 'cancelada') {
                $hasCancelada = true;
            }
            if ($st === 'iniciado') {
                $hasIniciado = true;
            }
            if ($st === 'finalizado') {
                $finalizadas++;
            }
        }
        if ($hasCancelada && !$temRespostasFinalizadas) {
            return 'cancelada';
        }
        if ($temRespostasFinalizadas || $finalizadas > 0) {
            return 'concluido';
        }
        if ($hasIniciado) {
            return 'em_andamento';
        }
        return 'nao_iniciado';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'concluido' => 'Concluído',
            'em_andamento' => 'Em andamento',
            'cancelada' => 'Cancelada',
            'nao_iniciado' => 'Não iniciado',
            default => ucfirst($status),
        };
    }

    /**
     * tempo_gasto em provas_realizacoes é gravado em MINUTOS (ver schema).
     * Preferimos iniciado_em/finalizado_em para precisão em segundos.
     *
     * @param array<string,mixed> $realizacao
     */
    private function resolveTempoSegundosRealizacao(array $realizacao): int
    {
        $inicio = trim((string) ($realizacao['iniciado_em'] ?? ''));
        $fim = trim((string) ($realizacao['finalizado_em'] ?? ''));
        if ($inicio !== '' && $fim !== '') {
            try {
                $t0 = new \DateTime($inicio);
                $t1 = new \DateTime($fim);
                return max(0, $t1->getTimestamp() - $t0->getTimestamp());
            } catch (\Throwable $e) {
            }
        }

        $minutos = (int) ($realizacao['tempo_gasto'] ?? 0);
        return max(0, $minutos * 60);
    }

    private function formatTempo(int $segundos): string
    {
        if ($segundos <= 0) {
            return '—';
        }
        $h = intdiv($segundos, 3600);
        $m = intdiv($segundos % 3600, 60);
        $s = $segundos % 60;
        if ($h > 0) {
            return sprintf('%dh %02dm', $h, $m);
        }
        if ($m > 0) {
            return sprintf('%dm %02ds', $m, $s);
        }
        return sprintf('%ds', $s);
    }

    /**
     * @param list<array<string,mixed>> $alunos
     * @param array<int,array<string,mixed>> $eligibleById
     * @return array<string,mixed>
     */
    private function buildIndicadores(array $alunos, array $eligibleById): array
    {
        $concluiram = 0;
        $aprovados = 0;
        $atencao = 0;
        $somaPercent = 0.0;
        $countPercent = 0;
        $somaTempo = 0;
        $countTempo = 0;

        foreach ($alunos as $a) {
            if (($a['status'] ?? '') === 'concluido') {
                $concluiram++;
            }
            if (!empty($a['aprovado'])) {
                $aprovados++;
            }
            if (!empty($a['precisa_atencao'])) {
                $atencao++;
            }
            if (($a['total_respostas'] ?? 0) > 0) {
                $somaPercent += (float) $a['percentual'];
                $countPercent++;
            }
            if (($a['tempo_segundos'] ?? 0) > 0) {
                $somaTempo += (int) $a['tempo_segundos'];
                $countTempo++;
            }
        }

        $naoRealizaram = 0;
        foreach ($eligibleById as $id => $_) {
            $found = false;
            foreach ($alunos as $a) {
                if ((int) $a['aluno_id'] === (int) $id && ($a['status'] ?? '') === 'concluido') {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $naoRealizaram++;
            }
        }

        $mediaGeral = $countPercent > 0 ? round($somaPercent / $countPercent, 1) : 0.0;
        $tempoMedio = $countTempo > 0 ? (int) round($somaTempo / $countTempo) : 0;

        return [
            'aprovados' => $aprovados,
            'precisam_atencao' => $atencao,
            'concluiram' => $concluiram,
            'nao_realizaram' => $naoRealizaram,
            'media_geral' => $mediaGeral,
            'tempo_medio_segundos' => $tempoMedio,
            'tempo_medio_label' => $this->formatTempo($tempoMedio),
            'total_elegiveis' => count($eligibleById),
        ];
    }

    /**
     * @param list<array<string,mixed>> $alunos
     * @return list<array<string,mixed>>
     */
    private function buildPorTurma(array $alunos): array
    {
        $map = [];
        foreach ($alunos as $a) {
            if (($a['total_respostas'] ?? 0) <= 0) {
                continue;
            }
            $key = (string) ($a['turma_nome'] ?? '—');
            if (!isset($map[$key])) {
                $map[$key] = ['turma_nome' => $key, 'soma_percent' => 0.0, 'count' => 0, 'alunos' => 0];
            }
            $map[$key]['soma_percent'] += (float) $a['percentual'];
            $map[$key]['count']++;
            $map[$key]['alunos']++;
        }
        $out = [];
        foreach ($map as $row) {
            $row['percentual'] = $row['count'] > 0
                ? round($row['soma_percent'] / $row['count'], 1)
                : 0.0;
            unset($row['soma_percent'], $row['count']);
            $out[] = $row;
        }
        usort($out, static fn ($a, $b) => ($a['percentual'] ?? 0) <=> ($b['percentual'] ?? 0));
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $alunos
     * @param list<array<string,mixed>> $porAlunoProva
     * @param list<array<string,mixed>> $provas
     * @return array<string,mixed>
     */
    private function buildHeatmap(array $alunos, array $porAlunoProva, array $provas): array
    {
        $turmas = [];
        $disciplinas = [];
        foreach ($alunos as $a) {
            $t = (string) ($a['turma_nome'] ?? '—');
            if ($t !== '' && !in_array($t, $turmas, true)) {
                $turmas[] = $t;
            }
        }
        sort($turmas);

        $provaMeta = [];
        foreach ($provas as $p) {
            $nome = (string) ($p['materia_nome'] ?? $p['titulo'] ?? 'Prova');
            $disciplinas[] = $nome;
            $provaMeta[(int) $p['id']] = $nome;
        }

        $alunoTurma = [];
        foreach ($alunos as $a) {
            $alunoTurma[(int) $a['aluno_id']] = (string) ($a['turma_nome'] ?? '—');
        }

        $acc = [];
        foreach ($porAlunoProva as $row) {
            $aid = (int) $row['aluno_id'];
            $pid = (int) $row['prova_id'];
            $turma = $alunoTurma[$aid] ?? '—';
            $disc = $provaMeta[$pid] ?? 'Prova';
            $total = (int) $row['total_respostas'];
            if ($total <= 0) {
                continue;
            }
            $pct = round(100 * (int) $row['total_acertos'] / $total, 1);
            $key = $turma . '||' . $disc;
            if (!isset($acc[$key])) {
                $acc[$key] = ['soma' => 0.0, 'n' => 0];
            }
            $acc[$key]['soma'] += $pct;
            $acc[$key]['n']++;
        }

        $cells = [];
        foreach ($turmas as $turma) {
            $cells[$turma] = [];
            foreach ($disciplinas as $disc) {
                $key = $turma . '||' . $disc;
                if (!isset($acc[$key]) || $acc[$key]['n'] <= 0) {
                    $cells[$turma][$disc] = null;
                    continue;
                }
                $pct = round($acc[$key]['soma'] / $acc[$key]['n'], 1);
                $cells[$turma][$disc] = [
                    'percentual' => $pct,
                    'nivel' => $this->heatmapNivel($pct),
                ];
            }
        }

        return [
            'turmas' => $turmas,
            'disciplinas' => $disciplinas,
            'cells' => $cells,
        ];
    }

    private function heatmapNivel(float $pct): string
    {
        if ($pct >= self::META_BOA) {
            return 'bom';
        }
        if ($pct >= self::MEDIA_MINIMA) {
            return 'atencao';
        }
        return 'critico';
    }

    /**
     * @param list<array<string,mixed>> $alunos
     * @return list<array<string,mixed>>
     */
    private function buildRankingAlunos(array $alunos): array
    {
        $rank = array_values(array_filter($alunos, static fn ($a) => ($a['total_respostas'] ?? 0) > 0));
        usort($rank, static fn ($a, $b) => ($b['percentual'] ?? 0) <=> ($a['percentual'] ?? 0));
        return array_slice($rank, 0, 10);
    }

    /**
     * @param list<array<string,mixed>> $porTurma
     * @return list<array<string,mixed>>
     */
    private function buildRankingTurmas(array $porTurma): array
    {
        $rank = $porTurma;
        usort($rank, static fn ($a, $b) => ($b['percentual'] ?? 0) <=> ($a['percentual'] ?? 0));
        return array_slice($rank, 0, 10);
    }

    /**
     * @param list<array<string,mixed>> $alunos
     * @param list<array<string,mixed>> $provas
     * @return array<string,list<string>>
     */
    private function buildFiltros(array $alunos, array $provas): array
    {
        $turmas = [];
        $series = [];
        foreach ($alunos as $a) {
            $t = trim((string) ($a['turma_nome'] ?? ''));
            if ($t !== '' && !in_array($t, $turmas, true)) {
                $turmas[] = $t;
            }
            $s = trim((string) ($a['serie'] ?? ''));
            if ($s !== '' && !in_array($s, $series, true)) {
                $series[] = $s;
            }
        }
        sort($turmas);
        sort($series);

        $professores = [];
        $materias = [];
        foreach ($provas as $p) {
            $prof = trim((string) ($p['professor_nome'] ?? ''));
            if ($prof !== '' && !in_array($prof, $professores, true)) {
                $professores[] = $prof;
            }
            $mat = trim((string) ($p['materia_nome'] ?? ''));
            if ($mat !== '' && !in_array($mat, $materias, true)) {
                $materias[] = $mat;
            }
        }
        sort($professores);
        sort($materias);

        return [
            'turmas' => $turmas,
            'series' => $series,
            'professores' => $professores,
            'materias' => $materias,
            'status' => ['concluido', 'em_andamento', 'nao_iniciado', 'cancelada'],
        ];
    }
}
