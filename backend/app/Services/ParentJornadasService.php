<?php
/**
 * Histórico de jornadas no portal dos pais — mesma visibilidade do aluno
 * (turma principal + estrutura.turmas_selecionadas + progresso do aluno).
 */

require_once __DIR__ . '/../Core/AlunoTurmaHelper.php';
require_once __DIR__ . '/../Models/Education/JourneyBoletimLancamento.php';

class ParentJornadasService
{
    private $db;

    /** @var array<int, array<int,true>> */
    private $cacheProgresso = [];

    /** @var array<string, list<array<string,mixed>>> */
    private $cacheCandidatas = [];

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarDetalhadas(int $alunoId, int $turmaIdFallback = 0, ?int $anoLetivo = null, ?int $bimestre = null): array
    {
        if ($alunoId <= 0 || !$this->db->tableExists('jornadas')) {
            return [];
        }

        $turmaIds = $this->turmaIdsDoAluno($alunoId, $turmaIdFallback);
        $idsProgresso = $this->idsComProgresso($alunoId);
        $candidatas = $this->listarCandidatas($turmaIds, $idsProgresso);
        $ids = [];
        foreach ($candidatas as $j) {
            if (!$this->passaFiltroPeriodo($j, $anoLetivo, $bimestre)) {
                continue;
            }
            $jid = (int) ($j['id'] ?? 0);
            if ($jid > 0) {
                $ids[] = $jid;
            }
        }
        if ($ids === []) {
            return [];
        }

        return $this->carregarDetalhes($alunoId, $ids);
    }

    /**
     * @return list<int>
     */
    public function anosDisponiveis(int $alunoId, int $turmaIdFallback = 0): array
    {
        $turmaIds = $this->turmaIdsDoAluno($alunoId, $turmaIdFallback);
        $idsProgresso = $this->idsComProgresso($alunoId);
        $anos = [];
        foreach ($this->listarCandidatas($turmaIds, $idsProgresso) as $j) {
            $ano = (int) ($j['ano_letivo'] ?? 0);
            if ($ano <= 0 && !empty($j['created_at'])) {
                $ano = (int) date('Y', strtotime((string) $j['created_at']));
            }
            if ($ano > 0) {
                $anos[$ano] = true;
            }
        }
        $lista = array_map('intval', array_keys($anos));
        rsort($lista, SORT_NUMERIC);
        return $lista;
    }

    /**
     * @return list<int>
     */
    private function turmaIdsDoAluno(int $alunoId, int $turmaIdFallback): array
    {
        $ids = AlunoTurmaHelper::getTurmaIds($this->db, $alunoId);
        if ($turmaIdFallback > 0 && !in_array($turmaIdFallback, $ids, true)) {
            $ids[] = $turmaIdFallback;
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * @return array<int,true>
     */
    private function idsComProgresso(int $alunoId): array
    {
        if (isset($this->cacheProgresso[$alunoId])) {
            return $this->cacheProgresso[$alunoId];
        }
        if (!$this->db->tableExists('jornadas_progresso_alunos')) {
            $this->cacheProgresso[$alunoId] = [];
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT DISTINCT jornada_id
                 FROM jornadas_progresso_alunos
                 WHERE aluno_id = :aluno_id AND jornada_id IS NOT NULL',
                ['aluno_id' => $alunoId]
            ) ?: [];
        } catch (Throwable $e) {
            error_log('ParentJornadasService idsComProgresso: ' . $e->getMessage());
            $this->cacheProgresso[$alunoId] = [];
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['jornada_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        $this->cacheProgresso[$alunoId] = $ids;
        return $ids;
    }

    /**
     * @param list<int> $turmaIds
     * @param array<int,true> $idsProgresso
     * @return list<array<string,mixed>>
     */
    private function listarCandidatas(array $turmaIds, array $idsProgresso): array
    {
        $cacheKey = implode(',', $turmaIds) . '#' . implode(',', array_keys($idsProgresso));
        if (isset($this->cacheCandidatas[$cacheKey])) {
            return $this->cacheCandidatas[$cacheKey];
        }

        $porId = [];
        $cols = $this->colunasCandidatas();

        if ($turmaIds !== []) {
            [$inSql, $params] = $this->placeholders($turmaIds, 'tid');
            if ($inSql !== '') {
                try {
                    $porTurma = $this->db->fetchAll(
                        "SELECT {$cols} FROM jornadas j WHERE j.turma_id IN ({$inSql}) ORDER BY j.created_at DESC",
                        $params
                    ) ?: [];
                    $porId = $this->indexarPorId($porId, $porTurma);
                } catch (Throwable $e) {
                    error_log('ParentJornadasService listarCandidatas turma: ' . $e->getMessage());
                }
                $porId = $this->indexarPorId($porId, $this->buscarPorTurmasSelecionadas($turmaIds, $cols, $params, $inSql));
            }
        }

        $faltandoProgresso = [];
        foreach (array_keys($idsProgresso) as $jid) {
            if (!isset($porId[$jid])) {
                $faltandoProgresso[] = $jid;
            }
        }
        if ($faltandoProgresso !== []) {
            $porId = $this->indexarPorId($porId, $this->buscarPorIds($faltandoProgresso, $cols));
        }

        $out = [];
        foreach ($porId as $j) {
            if ($this->alunoElegivel($this->normalizarCandidata($j), $turmaIds, $idsProgresso)) {
                $out[] = $j;
            }
        }
        $this->cacheCandidatas[$cacheKey] = $out;
        return $out;
    }

    /**
     * Jornadas cuja turma principal é outra, mas o aluno está em estrutura.turmas_selecionadas.
     * Traz só o array de turmas (JSON_EXTRACT), não o JSON inteiro da jornada.
     *
     * @param list<int> $turmaIds
     * @param array<string,int> $paramsTurma
     * @return list<array<string,mixed>>
     */
    private function buscarPorTurmasSelecionadas(array $turmaIds, string $cols, array $paramsTurma, string $inSql): array
    {
        $colsExtra = $cols . ", JSON_EXTRACT(j.estrutura, '$.turmas_selecionadas') AS turmas_selecionadas_json";
        try {
            $rows = $this->db->fetchAll(
                "SELECT {$colsExtra}
                 FROM jornadas j
                 WHERE JSON_EXTRACT(j.estrutura, '$.turmas_selecionadas') IS NOT NULL
                   AND (j.turma_id IS NULL OR j.turma_id NOT IN ({$inSql}))",
                $paramsTurma
            ) ?: [];
        } catch (Throwable $e) {
            error_log('ParentJornadasService buscarPorTurmasSelecionadas: ' . $e->getMessage());
            return [];
        }

        $out = [];
        $turmaSet = array_fill_keys(array_map('intval', $turmaIds), true);
        foreach ($rows as $row) {
            $lista = json_decode((string) ($row['turmas_selecionadas_json'] ?? ''), true);
            if (!is_array($lista)) {
                continue;
            }
            foreach ($lista as $tid) {
                if (isset($turmaSet[(int) $tid])) {
                    $out[] = $row;
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $jornada
     * @return array<string,mixed>
     */
    private function normalizarCandidata(array $jornada): array
    {
        if (isset($jornada['estrutura'])) {
            return $jornada;
        }
        $lista = json_decode((string) ($jornada['turmas_selecionadas_json'] ?? ''), true);
        $jornada['estrutura'] = json_encode(['turmas_selecionadas' => is_array($lista) ? $lista : []]);
        return $jornada;
    }

    private function colunasCandidatas(): string
    {
        $cols = 'j.id, j.turma_id, j.created_at';
        if ($this->temColuna('jornadas', 'ano_letivo')) {
            $cols .= ', j.ano_letivo';
        }
        if ($this->temColuna('jornadas', 'bimestre')) {
            $cols .= ', j.bimestre';
        }
        return $cols;
    }

    /**
     * @param list<int> $ids
     * @return list<array<string,mixed>>
     */
    private function buscarPorIds(array $ids, string $cols): array
    {
        [$inSql, $params] = $this->placeholders($ids, 'jid');
        if ($inSql === '') {
            return [];
        }
        try {
            return $this->db->fetchAll(
                "SELECT {$cols} FROM jornadas j WHERE j.id IN ({$inSql})",
                $params
            ) ?: [];
        } catch (Throwable $e) {
            error_log('ParentJornadasService buscarPorIds: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param array<int,array<string,mixed>> $porId
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function indexarPorId(array $porId, array $rows): array
    {
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $porId[$id] = $row;
            }
        }
        return $porId;
    }

    /**
     * @param list<int> $ids
     * @return array{0:string,1:array<string,int>}
     */
    private function placeholders(array $ids, string $prefix): array
    {
        $ph = [];
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $intId = (int) $id;
            if ($intId <= 0) {
                continue;
            }
            $key = $prefix . '_' . $i;
            $ph[] = ':' . $key;
            $params[$key] = $intId;
        }
        return [implode(',', $ph), $params];
    }

    /**
     * @param array<string,mixed> $jornada
     * @param list<int> $turmaIds
     * @param array<int,true> $idsProgresso
     */
    private function alunoElegivel(array $jornada, array $turmaIds, array $idsProgresso): bool
    {
        $jid = (int) ($jornada['id'] ?? 0);
        if ($jid > 0 && isset($idsProgresso[$jid])) {
            return true;
        }
        foreach ($turmaIds as $tid) {
            if (JourneyBoletimLancamento::jornadaCobreTurma($jornada, (int) $tid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $j
     */
    private function passaFiltroPeriodo(array $j, ?int $anoLetivo, ?int $bimestre): bool
    {
        if ($anoLetivo !== null && $anoLetivo > 0) {
            $ano = (int) ($j['ano_letivo'] ?? 0);
            if ($ano <= 0 && !empty($j['created_at'])) {
                $ano = (int) date('Y', strtotime((string) $j['created_at']));
            }
            if ($ano !== $anoLetivo) {
                return false;
            }
        }
        if ($bimestre !== null && $bimestre >= 1 && $bimestre <= 4) {
            $bim = (int) ($j['bimestre'] ?? 0);
            if ($bim <= 0) {
                return false;
            }
            if ($bim !== $bimestre) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param list<int> $ids
     * @return list<array<string,mixed>>
     */
    private function carregarDetalhes(int $alunoId, array $ids): array
    {
        $hasMateriaId = $this->temColuna('jornadas', 'materia_id');
        $hasMaterias = $this->db->tableExists('materias');
        $hasProgress = $this->db->tableExists('jornadas_progresso_alunos');
        $hasModulos = $this->db->tableExists('jornadas_modulos');
        $hasExercicios = $this->db->tableExists('jornadas_exercicios');
        $hasRedacoes = $this->db->tableExists('jornadas_redacoes');
        $hasModulosExercicios = $this->db->tableExists('jornadas_modulos_exercicios');

        $hasStatus = $this->temColuna('jornadas', 'status');
        $statusExpr = $hasStatus ? 'j.status' : 'NULL';
        $joinMateria = ($hasMateriaId && $hasMaterias) ? 'LEFT JOIN materias m ON m.id = j.materia_id' : '';
        $materiaExpr = ($hasMateriaId && $hasMaterias) ? 'm.nome' : 'NULL';

        $modulosExpr = $hasModulos
            ? '(SELECT COUNT(*) FROM jornadas_modulos jm WHERE jm.jornada_id = j.id)'
            : '0';
        $exerciciosExpr = $hasExercicios
            ? '(SELECT COUNT(*) FROM jornadas_exercicios je WHERE je.jornada_id = j.id)'
            : '0';
        $redacoesExpr = $hasRedacoes
            ? '(SELECT COUNT(*) FROM jornadas_redacoes jr WHERE jr.jornada_id = j.id)'
            : '0';

        $fezExpr = $hasProgress
            ? 'EXISTS(SELECT 1 FROM jornadas_progresso_alunos jpx WHERE jpx.jornada_id = j.id AND jpx.aluno_id = :aluno_id_fez)'
            : '0';
        $concluiuExpr = $hasProgress
            ? "EXISTS(SELECT 1 FROM jornadas_progresso_alunos jpc WHERE jpc.jornada_id = j.id AND jpc.aluno_id = :aluno_id_concluiu AND jpc.atividade_tipo = 'jornada_concluida')"
            : '0';
        $interacoesExpr = $hasProgress
            ? '(SELECT COUNT(*) FROM jornadas_progresso_alunos jpi WHERE jpi.jornada_id = j.id AND jpi.aluno_id = :aluno_id_interacoes)'
            : '0';
        $modulosFeitosExpr = ($hasProgress && $hasModulos)
            ? "(SELECT COUNT(DISTINCT jpm.modulo_id) FROM jornadas_progresso_alunos jpm WHERE jpm.jornada_id = j.id AND jpm.aluno_id = :aluno_id_modulos AND jpm.atividade_tipo = 'modulo')"
            : '0';
        $alternativasTotalExpr = ($hasModulosExercicios && $hasModulos)
            ? "(SELECT COUNT(*)
                FROM jornadas_modulos_exercicios jme
                INNER JOIN jornadas_modulos jm2 ON jm2.id = jme.modulo_id
                WHERE jm2.jornada_id = j.id
                  AND jme.status = 'publicado'
                  AND jme.tipo = 'alternativas')"
            : '0';
        $alternativasFeitasExpr = ($hasModulosExercicios && $hasModulos && $hasProgress)
            ? "(SELECT COUNT(DISTINCT jpaAlt.exercicio_modulo_id)
                FROM jornadas_progresso_alunos jpaAlt
                INNER JOIN jornadas_modulos_exercicios jmeAlt ON jmeAlt.id = jpaAlt.exercicio_modulo_id
                INNER JOIN jornadas_modulos jmAlt ON jmAlt.id = jmeAlt.modulo_id
                WHERE jmAlt.jornada_id = j.id
                  AND jpaAlt.aluno_id = :aluno_id_alt_feitas
                  AND jpaAlt.atividade_tipo = 'exercicio_modulo'
                  AND jmeAlt.tipo = 'alternativas')"
            : '0';
        $alternativasAcertosExpr = ($hasModulosExercicios && $hasModulos && $hasProgress)
            ? "(SELECT COUNT(DISTINCT jpaAltAc.exercicio_modulo_id)
                FROM jornadas_progresso_alunos jpaAltAc
                INNER JOIN jornadas_modulos_exercicios jmeAltAc ON jmeAltAc.id = jpaAltAc.exercicio_modulo_id
                INNER JOIN jornadas_modulos jmAltAc ON jmAltAc.id = jmeAltAc.modulo_id
                WHERE jmAltAc.jornada_id = j.id
                  AND jpaAltAc.aluno_id = :aluno_id_alt_acertos
                  AND jpaAltAc.atividade_tipo = 'exercicio_modulo'
                  AND jmeAltAc.tipo = 'alternativas'
                  AND COALESCE(jpaAltAc.pontuacao, 0) > 0)"
            : '0';

        [$idSql, $params] = $this->placeholders($ids, 'jid');
        if ($idSql === '') {
            return [];
        }
        if ($hasProgress) {
            $params['aluno_id_fez'] = $alunoId;
            $params['aluno_id_concluiu'] = $alunoId;
            $params['aluno_id_interacoes'] = $alunoId;
            $params['aluno_id_modulos'] = $alunoId;
            $params['aluno_id_alt_feitas'] = $alunoId;
            $params['aluno_id_alt_acertos'] = $alunoId;
        }

        try {
            $rows = $this->db->fetchAll(
                "SELECT
                    j.id,
                    j.titulo,
                    j.descricao,
                    j.estrutura,
                    j.created_at,
                    {$statusExpr} AS status,
                    p.nome AS professor_nome,
                    {$materiaExpr} AS materia_nome,
                    {$modulosExpr} AS total_modulos,
                    {$exerciciosExpr} AS total_exercicios,
                    {$redacoesExpr} AS total_redacoes,
                    {$fezExpr} AS fez,
                    {$concluiuExpr} AS concluiu,
                    {$interacoesExpr} AS total_interacoes,
                    {$modulosFeitosExpr} AS modulos_feitos,
                    {$alternativasTotalExpr} AS total_exercicios_alternativa,
                    {$alternativasFeitasExpr} AS exercicios_alternativa_feitos,
                    {$alternativasAcertosExpr} AS exercicios_alternativa_acertos
                 FROM jornadas j
                 LEFT JOIN professores p ON p.id = j.professor_id
                 {$joinMateria}
                 WHERE j.id IN ({$idSql})
                 ORDER BY j.created_at DESC",
                $params
            );
        } catch (Throwable $e) {
            error_log('ParentJornadasService carregarDetalhes: ' . $e->getMessage());
            return $this->carregarDetalhesBasico($ids);
        }

        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['total_modulos'] = (int) ($row['total_modulos'] ?? 0);
            $row['total_exercicios'] = (int) ($row['total_exercicios'] ?? 0);
            $row['total_redacoes'] = (int) ($row['total_redacoes'] ?? 0);
            $row['total_interacoes'] = (int) ($row['total_interacoes'] ?? 0);
            $row['modulos_feitos'] = (int) ($row['modulos_feitos'] ?? 0);
            $row['total_exercicios_alternativa'] = (int) ($row['total_exercicios_alternativa'] ?? 0);
            $row['exercicios_alternativa_feitos'] = (int) ($row['exercicios_alternativa_feitos'] ?? 0);
            $row['exercicios_alternativa_acertos'] = (int) ($row['exercicios_alternativa_acertos'] ?? 0);
            $row['exercicios_alternativa_erros'] = max(0, $row['exercicios_alternativa_feitos'] - $row['exercicios_alternativa_acertos']);
            $row['fez'] = (int) ($row['fez'] ?? 0) === 1;
            $row['concluiu'] = (int) ($row['concluiu'] ?? 0) === 1;
            $den = max(1, $row['total_modulos']);
            $row['percentual_modulos'] = min(100, (int) round(($row['modulos_feitos'] / $den) * 100));
            $denAlt = max(1, $row['total_exercicios_alternativa']);
            $row['percentual_exercicios_alternativa_feitos'] = min(100, (int) round(($row['exercicios_alternativa_feitos'] / $denAlt) * 100));
            $denAltResp = max(1, $row['exercicios_alternativa_feitos']);
            $row['percentual_exercicios_alternativa_acerto'] = $row['exercicios_alternativa_feitos'] > 0
                ? min(100, (int) round(($row['exercicios_alternativa_acertos'] / $denAltResp) * 100))
                : 0;

            $row['expirada'] = false;
            $fimTs = null;
            $estruturaRaw = (string) ($row['estrutura'] ?? '');
            if ($estruturaRaw !== '') {
                $estrutura = json_decode($estruturaRaw, true);
                if (is_array($estrutura) && !empty($estrutura['data_fim'])) {
                    $dataFim = trim((string) $estrutura['data_fim']);
                    $horaFim = trim((string) ($estrutura['hora_fim'] ?? '23:59:59'));
                    if ($horaFim === '') {
                        $horaFim = '23:59:59';
                    } elseif (preg_match('/^\d{2}:\d{2}$/', $horaFim)) {
                        $horaFim .= ':00';
                    }
                    $fimTs = strtotime($dataFim . ' ' . $horaFim);
                }
            }
            if (!$row['fez'] && $fimTs !== null && $fimTs > 0 && time() > $fimTs) {
                $row['expirada'] = true;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Fallback sem agregações — ainda mostra a lista se o SELECT completo falhar.
     *
     * @param list<int> $ids
     * @return list<array<string,mixed>>
     */
    private function carregarDetalhesBasico(array $ids): array
    {
        [$idSql, $params] = $this->placeholders($ids, 'jid');
        if ($idSql === '') {
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT j.id, j.titulo, j.descricao, j.estrutura, j.created_at,
                        p.nome AS professor_nome
                 FROM jornadas j
                 LEFT JOIN professores p ON p.id = j.professor_id
                 WHERE j.id IN ({$idSql})
                 ORDER BY j.created_at DESC",
                $params
            ) ?: [];
        } catch (Throwable $e) {
            error_log('ParentJornadasService carregarDetalhesBasico: ' . $e->getMessage());
            return [];
        }

        foreach ($rows as &$row) {
            $row['total_modulos'] = 0;
            $row['total_exercicios'] = 0;
            $row['total_redacoes'] = 0;
            $row['total_interacoes'] = 0;
            $row['modulos_feitos'] = 0;
            $row['total_exercicios_alternativa'] = 0;
            $row['exercicios_alternativa_feitos'] = 0;
            $row['exercicios_alternativa_acertos'] = 0;
            $row['exercicios_alternativa_erros'] = 0;
            $row['fez'] = false;
            $row['concluiu'] = false;
            $row['percentual_modulos'] = 0;
            $row['percentual_exercicios_alternativa_feitos'] = 0;
            $row['percentual_exercicios_alternativa_acerto'] = 0;
            $row['expirada'] = false;
            $row['materia_nome'] = null;
            $row['status'] = null;
        }
        unset($row);

        return $rows;
    }

    private function temColuna(string $table, string $column): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        try {
            return !empty($this->db->fetchAll("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'"));
        } catch (Exception $e) {
            return false;
        }
    }
}
