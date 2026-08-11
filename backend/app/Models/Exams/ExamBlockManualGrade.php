<?php
/**
 * Notas lançadas manualmente em eventos de prova (formato lancamento_nota).
 */

class ExamBlockManualGrade
{
    private $db;

    /** @var string|null fragmento SQL extra (cache por requisição); null = ainda não calculado */
    private static $filtroPortalAlunoPaisSql = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Portal aluno/pais: não listar blocos bimestrais ou com visivel_no_portal_aluno = 0.
     * Se existir coluna visivel_no_portal_aluno, usa ela; senão, exclui bimestre 1–4 quando houver coluna bimestre.
     */
    private function getFiltroSqlVisibilidadePortalAlunoPais(): string
    {
        if (self::$filtroPortalAlunoPaisSql !== null) {
            return self::$filtroPortalAlunoPaisSql;
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provas_blocos'
                   AND COLUMN_NAME IN ('visivel_no_portal_aluno','bimestre')"
            );
            $names = [];
            foreach ($rows ?: [] as $r) {
                $cn = (string) ($r['COLUMN_NAME'] ?? '');
                if ($cn !== '') {
                    $names[] = $cn;
                }
            }
            if (in_array('visivel_no_portal_aluno', $names, true)) {
                self::$filtroPortalAlunoPaisSql = ' AND pb.visivel_no_portal_aluno = 1';
            } elseif (in_array('bimestre', $names, true)) {
                self::$filtroPortalAlunoPaisSql = ' AND (pb.bimestre IS NULL OR pb.bimestre NOT BETWEEN 1 AND 4)';
            } else {
                self::$filtroPortalAlunoPaisSql = '';
            }
        } catch (Exception $e) {
            self::$filtroPortalAlunoPaisSql = '';
        }

        return self::$filtroPortalAlunoPaisSql;
    }

    public function tableExists(): bool
    {
        try {
            return $this->db->fetch("SHOW TABLES LIKE 'provas_blocos_notas_lancadas'") !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Quando o aluno muda de turma, as linhas de nota (evento lançamento) ainda guardam o turma_id antigo;
     * o relatório da coordenação e o mapa do professor usam esse campo — atualiza para a turma atual.
     */
    public function migrarTurmaIdEmNotasLancadasParaAluno(int $alunoId, int $turmaAntigaId, int $turmaNovaId): void
    {
        if (!$this->tableExists() || $alunoId <= 0 || $turmaAntigaId <= 0 || $turmaNovaId <= 0 || $turmaAntigaId === $turmaNovaId) {
            return;
        }
        try {
            $this->db->query(
                'UPDATE provas_blocos_notas_lancadas SET turma_id = :novo, updated_at = NOW() WHERE aluno_id = :aluno AND turma_id = :antigo',
                ['novo' => $turmaNovaId, 'aluno' => $alunoId, 'antigo' => $turmaAntigaId]
            );
        } catch (Exception $e) {
            error_log('ExamBlockManualGrade::migrarTurmaIdEmNotasLancadasParaAluno: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, array{nota: ?float, observacao: string}>
     *   Chave: "{turma_id}_{aluno_id}"
     */
    public function fetchMap(int $blocoId, int $professorId, int $materiaId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT turma_id, aluno_id, nota, observacao
             FROM provas_blocos_notas_lancadas
             WHERE bloco_id = :bloco_id AND professor_id = :professor_id AND materia_id = :materia_id',
            [
                'bloco_id' => $blocoId,
                'professor_id' => $professorId,
                'materia_id' => $materiaId,
            ]
        );
        $out = [];
        foreach ($rows ?: [] as $r) {
            $k = (int) ($r['turma_id'] ?? 0) . '_' . (int) ($r['aluno_id'] ?? 0);
            $n = $r['nota'];
            $out[$k] = [
                'nota' => $n === null || $n === '' ? null : (float) $n,
                'observacao' => (string) ($r['observacao'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Agregados por professor/matéria (painel coordenação — lançamento de notas).
     *
     * @return array<string, array{com_nota:int, media_nota:?float, abaixo_seis:int}>
     *   Chave: "{professor_id}_{materia_id}"
     */
    public function fetchAgregadosPorBloco(int $blocoId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT professor_id, materia_id,
                    SUM(CASE WHEN nota IS NOT NULL THEN 1 ELSE 0 END) AS com_nota,
                    AVG(nota) AS media_nota,
                    SUM(CASE WHEN nota IS NOT NULL AND nota < 6 THEN 1 ELSE 0 END) AS abaixo_seis
             FROM provas_blocos_notas_lancadas
             WHERE bloco_id = :bloco_id
             GROUP BY professor_id, materia_id',
            ['bloco_id' => $blocoId]
        );
        $out = [];
        foreach ($rows ?: [] as $r) {
            $pid = (int) ($r['professor_id'] ?? 0);
            $mid = (int) ($r['materia_id'] ?? 0);
            if ($pid <= 0 || $mid <= 0) {
                continue;
            }
            $k = $pid . '_' . $mid;
            $out[$k] = [
                'com_nota' => (int) ($r['com_nota'] ?? 0),
                'media_nota' => isset($r['media_nota']) && $r['media_nota'] !== null && $r['media_nota'] !== ''
                    ? round((float) $r['media_nota'], 2) : null,
                'abaixo_seis' => (int) ($r['abaixo_seis'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Lista notas do bloco para um professor/matéria (coordenação).
     *
     * @return list<array<string, mixed>>
     */
    public function fetchNotasDetalheAdmin(int $blocoId, int $professorId, int $materiaId): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT n.turma_id, n.aluno_id, n.nota, n.observacao,
                    t.nome AS turma_nome, a.nome AS aluno_nome
             FROM provas_blocos_notas_lancadas n
             INNER JOIN alunos a ON a.id = n.aluno_id
             INNER JOIN turmas t ON t.id = n.turma_id
             WHERE n.bloco_id = :bid AND n.professor_id = :pid AND n.materia_id = :mid
             ORDER BY t.nome ASC, a.nome ASC',
            [
                'bid' => $blocoId,
                'pid' => $professorId,
                'mid' => $materiaId,
            ]
        ) ?: [];
    }

    /**
     * Todas as linhas de nota do bloco (relatório coordenação).
     *
     * @return list<array<string, mixed>>
     */
    public function fetchTodasNotasBlocoAdmin(int $blocoId): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT n.professor_id, n.materia_id, n.turma_id, n.aluno_id, n.nota, n.observacao, n.updated_at,
                    pr.nome AS professor_nome, m.nome AS materia_nome,
                    a.nome AS aluno_nome, t.nome AS turma_nome
             FROM provas_blocos_notas_lancadas n
             LEFT JOIN professores pr ON pr.id = n.professor_id
             LEFT JOIN materias m ON m.id = n.materia_id
             LEFT JOIN alunos a ON a.id = n.aluno_id
             LEFT JOIN turmas t ON t.id = n.turma_id
             WHERE n.bloco_id = :bloco_id
             ORDER BY m.nome ASC, pr.nome ASC, t.nome ASC, a.nome ASC',
            ['bloco_id' => $blocoId]
        ) ?: [];
    }

    /**
     * Notas lançadas manualmente visíveis no portal do aluno e na tela de notas do responsável.
     * Respeita provas_blocos.visivel_no_portal_aluno (se existir) ou oculta bimestre 1–4.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchNotasPorAluno(int $alunoId): array
    {
        if (!$this->tableExists() || $alunoId <= 0) {
            return [];
        }

        $filtroPortal = $this->getFiltroSqlVisibilidadePortalAlunoPais();

        return $this->db->fetchAll(
            'SELECT n.nota, n.observacao, n.updated_at,
                    pb.id AS bloco_id, pb.titulo AS bloco_titulo, pb.data_prova AS bloco_data_prova,
                    m.nome AS materia_nome
             FROM provas_blocos_notas_lancadas n
             INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.deleted_at IS NULL
             LEFT JOIN materias m ON m.id = n.materia_id
             WHERE n.aluno_id = :aluno_id' . $filtroPortal . '
             ORDER BY COALESCE(n.updated_at, pb.data_prova) DESC, pb.id DESC
             LIMIT 200',
            ['aluno_id' => $alunoId]
        ) ?: [];
    }

    public function countComNota(int $blocoId, int $professorId, int $materiaId): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS c FROM provas_blocos_notas_lancadas
             WHERE bloco_id = :bloco_id AND professor_id = :professor_id AND materia_id = :materia_id
               AND nota IS NOT NULL',
            [
                'bloco_id' => $blocoId,
                'professor_id' => $professorId,
                'materia_id' => $materiaId,
            ]
        );

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Combinações de evento/professor/matéria que possuem notas disponíveis para importação.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchFontesImportacao(int $blocoDestinoId): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT pb.id AS bloco_id, pb.titulo AS bloco_titulo, pb.data_prova, pb.bimestre, pb.created_at,
                    n.professor_id, n.materia_id,
                    pr.nome AS professor_nome, m.nome AS materia_nome,
                    COUNT(DISTINCT n.aluno_id) AS total_notas
             FROM provas_blocos_notas_lancadas n
             INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.deleted_at IS NULL
             INNER JOIN professores pr ON pr.id = n.professor_id
             INNER JOIN materias m ON m.id = n.materia_id
             WHERE n.bloco_id <> :destino_id
               AND n.nota IS NOT NULL
             GROUP BY pb.id, pb.titulo, pb.data_prova, pb.bimestre, pb.created_at,
                      n.professor_id, n.materia_id, pr.nome, m.nome
             ORDER BY COALESCE(pb.data_prova, pb.created_at) DESC, pb.titulo ASC, m.nome ASC, pr.nome ASC",
            ['destino_id' => $blocoDestinoId]
        ) ?: [];
    }

    /**
     * @return list<array{turma_id:int,aluno_id:int,nota:float,observacao:string}>
     */
    public function fetchNotasParaImportacao(int $blocoId, int $professorId, int $materiaId): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT turma_id, aluno_id, nota, observacao
             FROM provas_blocos_notas_lancadas
             WHERE bloco_id = :bloco_id AND professor_id = :professor_id
               AND materia_id = :materia_id AND nota IS NOT NULL
             ORDER BY aluno_id ASC',
            [
                'bloco_id' => $blocoId,
                'professor_id' => $professorId,
                'materia_id' => $materiaId,
            ]
        ) ?: [];
    }

    /**
     * @param list<array{turma_id:int, aluno_id:int, nota:?float, observacao?:string}> $linhas
     */
    public function upsertLinhas(int $blocoId, int $professorId, int $materiaId, array $linhas): void
    {
        if (!$this->tableExists() || empty($linhas)) {
            return;
        }
        foreach ($linhas as $linha) {
            $tid = (int) ($linha['turma_id'] ?? 0);
            $aid = (int) ($linha['aluno_id'] ?? 0);
            if ($tid <= 0 || $aid <= 0) {
                continue;
            }
            $nota = $linha['nota'] ?? null;
            if ($nota !== null && (!is_numeric($nota) || (float) $nota < 0)) {
                continue;
            }
            $obs = isset($linha['observacao']) ? substr((string) $linha['observacao'], 0, 500) : null;

            $this->db->query(
                'INSERT INTO provas_blocos_notas_lancadas (bloco_id, professor_id, materia_id, turma_id, aluno_id, nota, observacao)
                 VALUES (:bloco_id, :professor_id, :materia_id, :turma_id, :aluno_id, :nota, :observacao)
                 ON DUPLICATE KEY UPDATE nota = VALUES(nota), observacao = VALUES(observacao), updated_at = CURRENT_TIMESTAMP',
                [
                    'bloco_id' => $blocoId,
                    'professor_id' => $professorId,
                    'materia_id' => $materiaId,
                    'turma_id' => $tid,
                    'aluno_id' => $aid,
                    'nota' => $nota === '' || $nota === null ? null : round((float) $nota, 2),
                    'observacao' => $obs !== '' ? $obs : null,
                ]
            );
        }
    }
}
