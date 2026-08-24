<?php
/**
 * Histórico de jornadas no portal dos pais — mesma visibilidade do aluno
 * (turma principal + estrutura.turmas_selecionadas + alunos_selecionados).
 */

require_once __DIR__ . '/../Core/AlunoTurmaHelper.php';
require_once __DIR__ . '/../Models/Education/JourneyBoletimLancamento.php';

class ParentJornadasService
{
    private $db;

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
        if ($turmaIds === []) {
            return [];
        }

        $candidatas = $this->listarCandidatas($alunoId, $turmaIds);
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
        if ($turmaIds === []) {
            return [];
        }
        $anos = [];
        foreach ($this->listarCandidatas($alunoId, $turmaIds) as $j) {
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
     * @param list<int> $turmaIds
     * @return list<array<string,mixed>>
     */
    private function listarCandidatas(int $alunoId, array $turmaIds): array
    {
        $params = [];
        $ph = [];
        foreach (array_values($turmaIds) as $i => $tid) {
            $key = 'tid_' . $i;
            $ph[] = ':' . $key;
            $params[$key] = $tid;
        }
        $inSql = implode(',', $ph);
        $hasAno = $this->temColuna('jornadas', 'ano_letivo');
        $hasBim = $this->temColuna('jornadas', 'bimestre');
        $cols = 'j.id, j.turma_id, j.estrutura, j.created_at';
        if ($hasAno) {
            $cols .= ', j.ano_letivo';
        }
        if ($hasBim) {
            $cols .= ', j.bimestre';
        }

        try {
            $rows = $this->db->fetchAll(
                "SELECT {$cols}
                 FROM jornadas j
                 WHERE j.turma_id IN ({$inSql})
                    OR (j.estrutura IS NOT NULL AND TRIM(j.estrutura) <> '')
                 ORDER BY j.created_at DESC
                 LIMIT 800",
                $params
            ) ?: [];
        } catch (Exception $e) {
            error_log('ParentJornadasService listarCandidatas: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $j) {
            if ($this->alunoElegivel($j, $alunoId, $turmaIds)) {
                $out[] = $j;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $jornada
     * @param list<int> $turmaIds
     */
    private function alunoElegivel(array $jornada, int $alunoId, array $turmaIds): bool
    {
        $cobre = false;
        foreach ($turmaIds as $tid) {
            if (JourneyBoletimLancamento::jornadaCobreTurma($jornada, (int) $tid)) {
                $cobre = true;
                break;
            }
        }
        if (!$cobre) {
            return false;
        }
        $e = json_decode((string) ($jornada['estrutura'] ?? ''), true) ?: [];
        $tipo = strtolower(trim((string) ($e['tipo_selecao_alunos'] ?? 'todos')));
        if ($tipo === 'selecionados') {
            $ids = array_map('intval', (array) ($e['alunos_selecionados'] ?? []));
            return in_array($alunoId, $ids, true);
        }
        return true;
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

        $params = [];
        $idPh = [];
        foreach (array_values($ids) as $i => $jid) {
            $key = 'jid_' . $i;
            $idPh[] = ':' . $key;
            $params[$key] = $jid;
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
                 WHERE j.id IN (" . implode(',', $idPh) . ")
                 ORDER BY j.created_at DESC",
                $params
            );
        } catch (Exception $e) {
            error_log('ParentJornadasService carregarDetalhes: ' . $e->getMessage());
            return [];
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
