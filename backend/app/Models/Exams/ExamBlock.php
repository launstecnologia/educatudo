<?php
/**
 * EducaTudo - Modelo de Blocos de Provas
 * Gerencia operações de banco de dados para blocos de provas
 */

class ExamBlock
{
    private $db;
    private $columnExistsCache = [];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Verifica coluna em provas_blocos (uso em controllers para mensagem de migração).
     */
    public function columnExistsOnBloco(string $column): bool
    {
        return $this->hasProvasBlocosColumn($column);
    }

    private function hasProvasBlocosColumn(string $column): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        if (array_key_exists($column, $this->columnExistsCache)) {
            return $this->columnExistsCache[$column];
        }
        try {
            // SHOW COLUMNS ... LIKE :bind costuma falhar com PDO MySQL; information_schema é confiável.
            $row = $this->db->fetch(
                "SELECT 1 AS ok
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'provas_blocos'
                   AND COLUMN_NAME = :col
                 LIMIT 1",
                ['col' => $column]
            );
            $this->columnExistsCache[$column] = !empty($row);
        } catch (Exception $e) {
            $this->columnExistsCache[$column] = false;
        }
        return $this->columnExistsCache[$column];
    }

    private function hasTable(string $table): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                 LIMIT 1",
                ['table' => $table]
            );
            return !empty($row);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Se o aluno pode ver o evento no portal (lista, iniciar prova, resultados).
     * Sem a coluna no banco: comportamento legado (sempre visível).
     */
    public function alunoPodeVerBlocoNoPortal(?array $bloco): bool
    {
        if (!$bloco || empty($bloco['id'])) {
            return false;
        }
        if (!$this->hasProvasBlocosColumn('visivel_no_portal_aluno')) {
            return true;
        }
        return (int) ($bloco['visivel_no_portal_aluno'] ?? 0) === 1;
    }

    /**
     * Lê formato_evento diretamente da tabela (evita ambiguidade com SELECT pb.* + GROUP BY em listagens).
     *
     * @param list<int> $ids
     * @return array<int, string> id do bloco => formato_evento
     */
    public function fetchFormatoEventoPorBlocoIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids) || !$this->hasProvasBlocosColumn('formato_evento')) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id, formato_evento FROM provas_blocos WHERE id IN ($placeholders) AND deleted_at IS NULL",
            $ids
        );
        $out = [];
        foreach ($rows ?: [] as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0) {
                $out[$id] = (string) ($r['formato_evento'] ?? 'online_questoes');
            }
        }

        return $out;
    }
    
    /**
     * Marca como concluído os blocos cujo horário de término já passou
     */
    public function marcarConcluidos()
    {
        $setExtra = $this->hasProvasBlocosColumn('conclusao_manual')
            ? ', conclusao_manual = 0'
            : '';
        $this->db->query(
            "UPDATE provas_blocos 
             SET status = 'concluido', liberado = 0{$setExtra}
             WHERE deleted_at IS NULL AND status = 'liberado' 
             AND CONCAT(data_prova, ' ', hora_fim) < NOW()"
        );
    }

    /**
     * Reabre blocos que estavam concluídos mas tiveram o horário final estendido pela coordenação.
     * Quando data_prova + hora_fim volta a ser >= NOW(), o bloco fica liberado novamente.
     * Não reabre conclusão manual da coordenação.
     */
    public function reabrirSePrazoEstendido()
    {
        $excluirManual = $this->hasProvasBlocosColumn('conclusao_manual')
            ? ' AND COALESCE(conclusao_manual, 0) = 0'
            : '';
        $this->db->query(
            "UPDATE provas_blocos 
             SET status = 'liberado', liberado = 1, ativo = 1, gabarito_liberado = 0
             WHERE deleted_at IS NULL AND status = 'concluido' 
             AND CONCAT(data_prova, ' ', hora_fim) >= NOW(){$excluirManual}"
        );
    }

    /**
     * Atualiza para "aprovado" os blocos que estão "aguardando" e cujas provas vinculadas já estão todas aprovadas
     */
    public function sincronizarStatusAprovado()
    {
        $this->db->query(
            "UPDATE provas_blocos pb
             SET pb.status = 'aprovado', pb.ativo = 1
             WHERE pb.deleted_at IS NULL AND pb.status = 'aguardando'
             AND (SELECT COUNT(*) FROM provas_blocos_vinculo pbp
                  INNER JOIN provas p ON pbp.prova_id = p.id AND p.deleted_at IS NULL
                  WHERE pbp.bloco_id = pb.id) > 0
             AND (SELECT COUNT(*) FROM provas_blocos_vinculo pbp
                  INNER JOIN provas p ON pbp.prova_id = p.id AND p.deleted_at IS NULL
                  WHERE pbp.bloco_id = pb.id AND p.status != 'aprovada') = 0"
        );
    }

    /**
     * Busca todos os blocos (com turmas demarcadas em provas_blocos_turmas)
     */
    public function getAll()
    {
        $this->marcarConcluidos();
        $this->reabrirSePrazoEstendido();
        $this->sincronizarStatusAprovado();
        return $this->db->fetchAll(
            "SELECT pb.*, 
                    u.nome as criado_por_nome,
                    t.nome as turma_nome,
                    COUNT(DISTINCT pbp.prova_id) as total_provas,
                    COUNT(DISTINCT pbp_rel.professor_id) as total_professores,
                    (SELECT COUNT(*) FROM provas_blocos_professores WHERE bloco_id = pb.id) as total_provas_esperadas,
                    (SELECT COUNT(*) FROM provas_blocos_vinculo pbp2 
                     INNER JOIN provas p2 ON p2.id = pbp2.prova_id 
                     WHERE pbp2.bloco_id = pb.id AND p2.deleted_at IS NULL 
                     AND p2.status IN ('enviada', 'aguardando_aprovacao', 'aprovada', 'reprovada')) as total_provas_entregues,
                    (SELECT GROUP_CONCAT(t2.nome ORDER BY t2.nome SEPARATOR ', ')
                     FROM provas_blocos_turmas pbt
                     INNER JOIN turmas t2 ON pbt.turma_id = t2.id
                     WHERE pbt.bloco_id = pb.id) as turmas_demarcadas,
                    (SELECT GROUP_CONCAT(DISTINCT t3.nome ORDER BY t3.nome SEPARATOR ', ')
                     FROM provas_blocos_professores pbp2
                     INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp2.id
                     INNER JOIN turmas t3 ON pbpt.turma_id = t3.id
                     WHERE pbp2.bloco_id = pb.id) as turmas_por_professor
             FROM provas_blocos pb
             LEFT JOIN usuarios u ON pb.criado_por = u.id
             LEFT JOIN turmas t ON pb.turma_id = t.id
             LEFT JOIN provas_blocos_vinculo pbp ON pb.id = pbp.bloco_id
             LEFT JOIN provas_blocos_professores pbp_rel ON pb.id = pbp_rel.bloco_id
             WHERE pb.deleted_at IS NULL
             GROUP BY pb.id
             ORDER BY pb.data_prova DESC, pb.hora_inicio DESC"
        );
    }
    
    /**
     * Busca blocos com filtros e paginação (10 por página)
     * $filters: ['titulo' => string, 'data_prova' => date Y-m-d, 'turma_id' => int, 'status' => string, 'excluir_status' => string, 'bloco_modelo_id' => int, 'materia_id' => int]
     */
    public function getAllFiltered($filters = [], $limit = 10, $offset = 0)
    {
        $this->marcarConcluidos();
        $this->reabrirSePrazoEstendido();
        $this->sincronizarStatusAprovado();
        $where = ['pb.deleted_at IS NULL'];
        $params = [];
        if (!empty($filters['titulo'])) {
            $where[] = 'pb.titulo LIKE :titulo';
            $params['titulo'] = '%' . trim($filters['titulo']) . '%';
        }
        if (!empty($filters['bloco_modelo_id'])) {
            $where[] = 'pb.bloco_modelo_id = :bloco_modelo_id';
            $params['bloco_modelo_id'] = (int)$filters['bloco_modelo_id'];
        }
        if (!empty($filters['data_prova'])) {
            $where[] = 'DATE(pb.data_prova) = :data_prova';
            $params['data_prova'] = $filters['data_prova'];
        }
        if (!empty($filters['turma_id'])) {
            $where[] = '(pb.turma_id = :turma_id OR EXISTS (SELECT 1 FROM provas_blocos_turmas pbt WHERE pbt.bloco_id = pb.id AND pbt.turma_id = :turma_id) OR EXISTS (SELECT 1 FROM provas_blocos_professores pbp2 INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp2.id WHERE pbp2.bloco_id = pb.id AND pbpt.turma_id = :turma_id))';
            $params['turma_id'] = (int)$filters['turma_id'];
        }
        if (!empty($filters['status']) && $filters['status'] !== 'todos') {
            $where[] = 'pb.status = :status';
            $params['status'] = $filters['status'];
        } elseif (!empty($filters['excluir_status'])) {
            $where[] = 'pb.status <> :excluir_status';
            $params['excluir_status'] = (string) $filters['excluir_status'];
        }
        if (!empty($filters['materia_id'])) {
            $mid = (int) $filters['materia_id'];
            $where[] = '(pb.materia_id = :materia_id_a OR EXISTS (SELECT 1 FROM provas_blocos_professores pbpf WHERE pbpf.bloco_id = pb.id AND pbpf.materia_id = :materia_id_b) OR EXISTS (SELECT 1 FROM provas_blocos_vinculo vb INNER JOIN provas pr ON pr.id = vb.prova_id AND pr.deleted_at IS NULL WHERE vb.bloco_id = pb.id AND pr.materia_id = :materia_id_c))';
            $params['materia_id_a'] = $mid;
            $params['materia_id_b'] = $mid;
            $params['materia_id_c'] = $mid;
        }
        if (!empty($filters['bimestre'])) {
            $where[] = 'pb.bimestre = :bimestre';
            $params['bimestre'] = (int) $filters['bimestre'];
        }
        if (!empty($filters['tipo_avaliacao_id']) && $this->hasProvasBlocosColumn('tipo_avaliacao_id')) {
            $where[] = 'pb.tipo_avaliacao_id = :tipo_avaliacao_id';
            $params['tipo_avaliacao_id'] = (int) $filters['tipo_avaliacao_id'];
        }
        $whereSql = implode(' AND ', $where);
        $limit = (int)$limit;
        $offset = (int)$offset;
        $temTipoAvaliacao = $this->hasProvasBlocosColumn('tipo_avaliacao_id') && $this->hasTable('provas_tipos_avaliacao');
        $selectTipoAvaliacao = $temTipoAvaliacao ? 'pta.nome as tipo_avaliacao_nome,' : "NULL as tipo_avaliacao_nome,";
        $joinTipoAvaliacao = $temTipoAvaliacao ? 'LEFT JOIN provas_tipos_avaliacao pta ON pb.tipo_avaliacao_id = pta.id AND pta.deleted_at IS NULL' : '';
        $sql = "SELECT pb.*, 
                    u.nome as criado_por_nome,
                    t.nome as turma_nome,
                    bm.nome as bloco_modelo_nome,
                    {$selectTipoAvaliacao}
                    COUNT(DISTINCT pbp.prova_id) as total_provas,
                    COUNT(DISTINCT pbp_rel.professor_id) as total_professores,
                    (SELECT COUNT(*) FROM provas_blocos_professores WHERE bloco_id = pb.id) as total_provas_esperadas,
                    (SELECT COUNT(*) FROM provas_blocos_vinculo pbp2 
                     INNER JOIN provas p2 ON p2.id = pbp2.prova_id 
                     WHERE pbp2.bloco_id = pb.id AND p2.deleted_at IS NULL 
                     AND p2.status IN ('enviada', 'aguardando_aprovacao', 'aprovada', 'reprovada')) as total_provas_entregues,
                    (SELECT GROUP_CONCAT(t2.nome ORDER BY t2.nome SEPARATOR ', ')
                     FROM provas_blocos_turmas pbt
                     INNER JOIN turmas t2 ON pbt.turma_id = t2.id
                     WHERE pbt.bloco_id = pb.id) as turmas_demarcadas,
                    (SELECT GROUP_CONCAT(DISTINCT t3.nome ORDER BY t3.nome SEPARATOR ', ')
                     FROM provas_blocos_professores pbp2
                     INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp2.id
                     INNER JOIN turmas t3 ON pbpt.turma_id = t3.id
                     WHERE pbp2.bloco_id = pb.id) as turmas_por_professor
             FROM provas_blocos pb
             LEFT JOIN usuarios u ON pb.criado_por = u.id
             LEFT JOIN turmas t ON pb.turma_id = t.id
             LEFT JOIN provas_blocos_modelos bm ON pb.bloco_modelo_id = bm.id AND bm.deleted_at IS NULL
             {$joinTipoAvaliacao}
             LEFT JOIN provas_blocos_vinculo pbp ON pb.id = pbp.bloco_id
             LEFT JOIN provas_blocos_professores pbp_rel ON pb.id = pbp_rel.bloco_id
             WHERE {$whereSql}
             GROUP BY pb.id
             ORDER BY pb.data_prova DESC, pb.hora_inicio DESC
             LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Conta total de blocos com os mesmos filtros (para paginação)
     */
    public function getCountFiltered($filters = [])
    {
        $where = ['pb.deleted_at IS NULL'];
        $params = [];
        if (!empty($filters['titulo'])) {
            $where[] = 'pb.titulo LIKE :titulo';
            $params['titulo'] = '%' . trim($filters['titulo']) . '%';
        }
        if (!empty($filters['bloco_modelo_id'])) {
            $where[] = 'pb.bloco_modelo_id = :bloco_modelo_id';
            $params['bloco_modelo_id'] = (int)$filters['bloco_modelo_id'];
        }
        if (!empty($filters['data_prova'])) {
            $where[] = 'DATE(pb.data_prova) = :data_prova';
            $params['data_prova'] = $filters['data_prova'];
        }
        if (!empty($filters['turma_id'])) {
            $where[] = '(pb.turma_id = :turma_id OR EXISTS (SELECT 1 FROM provas_blocos_turmas pbt WHERE pbt.bloco_id = pb.id AND pbt.turma_id = :turma_id) OR EXISTS (SELECT 1 FROM provas_blocos_professores pbp2 INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp2.id WHERE pbp2.bloco_id = pb.id AND pbpt.turma_id = :turma_id))';
            $params['turma_id'] = (int)$filters['turma_id'];
        }
        if (!empty($filters['status']) && $filters['status'] !== 'todos') {
            $where[] = 'pb.status = :status';
            $params['status'] = $filters['status'];
        } elseif (!empty($filters['excluir_status'])) {
            $where[] = 'pb.status <> :excluir_status';
            $params['excluir_status'] = (string) $filters['excluir_status'];
        }
        if (!empty($filters['materia_id'])) {
            $mid = (int) $filters['materia_id'];
            $where[] = '(pb.materia_id = :materia_id_a OR EXISTS (SELECT 1 FROM provas_blocos_professores pbpf WHERE pbpf.bloco_id = pb.id AND pbpf.materia_id = :materia_id_b) OR EXISTS (SELECT 1 FROM provas_blocos_vinculo vb INNER JOIN provas pr ON pr.id = vb.prova_id AND pr.deleted_at IS NULL WHERE vb.bloco_id = pb.id AND pr.materia_id = :materia_id_c))';
            $params['materia_id_a'] = $mid;
            $params['materia_id_b'] = $mid;
            $params['materia_id_c'] = $mid;
        }
        if (!empty($filters['bimestre'])) {
            $where[] = 'pb.bimestre = :bimestre';
            $params['bimestre'] = (int) $filters['bimestre'];
        }
        if (!empty($filters['tipo_avaliacao_id']) && $this->hasProvasBlocosColumn('tipo_avaliacao_id')) {
            $where[] = 'pb.tipo_avaliacao_id = :tipo_avaliacao_id';
            $params['tipo_avaliacao_id'] = (int) $filters['tipo_avaliacao_id'];
        }
        $whereSql = implode(' AND ', $where);
        $row = $this->db->fetch("SELECT COUNT(DISTINCT pb.id) as total FROM provas_blocos pb WHERE {$whereSql}", $params);
        return (int)($row['total'] ?? 0);
    }
    
    /**
     * Busca bloco por ID
     */
    public function findById($id)
    {
        $bloco = $this->db->fetch(
            "SELECT pb.*, 
                    u.nome as criado_por_nome,
                    t.nome as turma_nome
             FROM provas_blocos pb
             LEFT JOIN usuarios u ON pb.criado_por = u.id
             LEFT JOIN turmas t ON pb.turma_id = t.id
             WHERE pb.id = :id AND pb.deleted_at IS NULL",
            ['id' => $id]
        );
        
        if ($bloco) {
            $blocoId = (int) ($bloco['id'] ?? $id);
            // Garante campos vindos só de provas_blocos (evita ambiguidade/sobrescrita com pb.* + JOINs em alguns drivers/configs)
            $fmtMap = $this->fetchFormatoEventoPorBlocoIds([$blocoId]);
            if (isset($fmtMap[$blocoId])) {
                $bloco['formato_evento'] = $fmtMap[$blocoId];
            }
            $metaCols = [];
            if ($this->hasProvasBlocosColumn('ano_letivo')) {
                $metaCols[] = 'ano_letivo';
            }
            if ($this->hasProvasBlocosColumn('bimestre')) {
                $metaCols[] = 'bimestre';
            }
            if ($this->hasProvasBlocosColumn('tipo_avaliacao_id')) {
                $metaCols[] = 'tipo_avaliacao_id';
            }
            if ($this->hasProvasBlocosColumn('visivel_no_portal_aluno')) {
                $metaCols[] = 'visivel_no_portal_aluno';
            }
            if ($this->hasProvasBlocosColumn('nota_unica_todas_materias')) {
                $metaCols[] = 'nota_unica_todas_materias';
            }
            if (!empty($metaCols)) {
                $metaSql = 'SELECT ' . implode(', ', $metaCols) . ' FROM provas_blocos WHERE id = :id AND deleted_at IS NULL';
                $metaRow = $this->db->fetch($metaSql, ['id' => $blocoId]);
                if (is_array($metaRow)) {
                    foreach ($metaCols as $col) {
                        if (array_key_exists($col, $metaRow)) {
                            $bloco[$col] = $metaRow[$col];
                        }
                    }
                }
            }
            // Busca provas do bloco
            $bloco['provas'] = $this->getProvas($id);
            // Busca turmas gerais do bloco
            $bloco['turmas'] = $this->getTurmas($id);
            // Busca professores vinculados com suas matérias e turmas
            $bloco['professores'] = $this->getProfessores($id);
            // Fallback: blocos antigos podem não ter turmas por professor; usa turmas do bloco
            foreach ($bloco['professores'] as &$professor) {
                if (empty($professor['turmas']) && !empty($bloco['turmas'])) {
                    $professor['turmas'] = array_map(function ($t) {
                        return ['id' => (int)$t['id'], 'nome' => $t['nome'], 'serie' => $t['serie'] ?? null];
                    }, $bloco['turmas']);
                }
            }
            unset($professor);
        }
        
        return $bloco;
    }
    
    /**
     * Libera provas vinculadas a um bloco já liberado (aluno só vê prova com liberada=1).
     */
    public function garantirProvasLiberadas($blocoId): void
    {
        $this->db->query(
            "UPDATE provas p
             INNER JOIN provas_blocos_vinculo pbp ON p.id = pbp.prova_id
             SET p.liberada = 1, p.ativo = 1
             WHERE pbp.bloco_id = :bloco_id
             AND p.deleted_at IS NULL
             AND (p.liberada = 0 OR p.ativo = 0)",
            ['bloco_id' => (int) $blocoId]
        );
    }

    /**
     * Se o aluno (turma principal + matrículas) tem acesso ao bloco.
     */
    public function alunoTemAcessoAoBloco(array $bloco, int $alunoId): bool
    {
        $blocoId = (int) ($bloco['id'] ?? 0);
        if ($blocoId <= 0 || $alunoId <= 0) {
            return false;
        }
        if (!class_exists('AlunoTurmaHelper')) {
            require_once __DIR__ . '/../../Core/AlunoTurmaHelper.php';
        }
        $turmaIdsAluno = AlunoTurmaHelper::getTurmaIds($this->db, $alunoId);
        $blocoTurmaIds = [];
        $turmaDireta = isset($bloco['turma_id']) ? (int) $bloco['turma_id'] : 0;
        if ($turmaDireta > 0) {
            $blocoTurmaIds[] = $turmaDireta;
        }
        $vinculadas = $this->db->fetchAll(
            'SELECT turma_id FROM provas_blocos_turmas WHERE bloco_id = :bloco_id',
            ['bloco_id' => $blocoId]
        ) ?: [];
        foreach ($vinculadas as $row) {
            $tid = (int) ($row['turma_id'] ?? 0);
            if ($tid > 0) {
                $blocoTurmaIds[] = $tid;
            }
        }
        $blocoTurmaIds = array_values(array_unique($blocoTurmaIds));
        if ($blocoTurmaIds === []) {
            return true;
        }
        foreach ($turmaIdsAluno as $tid) {
            if (in_array((int) $tid, $blocoTurmaIds, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * SQL/params: prova da turma do aluno (ou global) OU já iniciada/finalizada/cancelada por ele.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filtroProvasDoAlunoNoBloco(int $alunoId): array
    {
        if (!class_exists('AlunoTurmaHelper')) {
            require_once __DIR__ . '/../../Core/AlunoTurmaHelper.php';
        }
        $turmaIds = AlunoTurmaHelper::getTurmaIds($this->db, $alunoId);
        $params = [];
        $inP = [];
        $inPt = [];
        foreach ($turmaIds as $i => $tid) {
            $kP = 'turma_p_' . $i;
            $kPt = 'turma_pt_' . $i;
            $params[$kP] = (int) $tid;
            $params[$kPt] = (int) $tid;
            $inP[] = ':' . $kP;
            $inPt[] = ':' . $kPt;
        }
        if ($inP === []) {
            $sqlTurma = "(COALESCE(p.turma_id, 0) = 0 AND NOT EXISTS (
                SELECT 1 FROM provas_turmas pty WHERE pty.prova_id = p.id
            ))";
        } else {
            $sqlTurma = "(p.turma_id IN (" . implode(', ', $inP) . ")
                OR EXISTS (
                    SELECT 1 FROM provas_turmas ptx
                    WHERE ptx.prova_id = p.id AND ptx.turma_id IN (" . implode(', ', $inPt) . ")
                )
                OR (COALESCE(p.turma_id, 0) = 0 AND NOT EXISTS (
                    SELECT 1 FROM provas_turmas pty WHERE pty.prova_id = p.id
                )))";
        }
        $sql = "{$sqlTurma}
            AND (
                (p.ativo = 1 AND p.liberada = 1)
                OR pr.status IN ('finalizado', 'cancelada', 'iniciado')
            )";
        return [$sql, $params];
    }

    /**
     * Busca provas de um bloco. Com $alunoId, restringe à turma do aluno.
     */
    public function getProvas($blocoId, $alunoId = null)
    {
        $sql = "SELECT p.*, 
                    m.nome as materia_nome,
                    prof.nome as professor_nome,
                    pbp.ordem";
        
        if ($alunoId) {
            $sql .= ", pr.id as realizacao_id,
                     pr.status as realizacao_status,
                     pr.nota as realizacao_nota";
        }
        
        $sql .= " FROM provas_blocos_vinculo pbp
             INNER JOIN provas p ON pbp.prova_id = p.id
             LEFT JOIN materias m ON p.materia_id = m.id
             LEFT JOIN professores prof ON p.professor_id = prof.id";
        
        if ($alunoId) {
            $sql .= " LEFT JOIN provas_realizacoes pr ON p.id = pr.prova_id AND pr.aluno_id = :aluno_id";
        }
        
        $sql .= " WHERE pbp.bloco_id = :bloco_id
             AND p.deleted_at IS NULL";
        
        $params = ['bloco_id' => (int) $blocoId];
        if ($alunoId) {
            $params['aluno_id'] = (int) $alunoId;
            [$filtroAluno, $paramsTurma] = $this->filtroProvasDoAlunoNoBloco((int) $alunoId);
            $sql .= " AND {$filtroAluno}";
            $params = array_merge($params, $paramsTurma);
        } else {
            $sql .= " AND p.ativo = 1 AND p.liberada = 1";
        }
        
        $sql .= " ORDER BY pbp.ordem ASC, p.created_at ASC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Resumo das realizações do aluno no bloco (Minhas Provas / bloqueio de reentrada).
     *
     * @return array{alguma_cancelada: bool, todas_finalizadas: bool, total_provas: int}
     */
    public function getResumoRealizacaoAlunoNoBloco($blocoId, $alunoId): array
    {
        $blocoId = (int) $blocoId;
        $alunoId = (int) $alunoId;
        if ($blocoId <= 0 || $alunoId <= 0) {
            return ['alguma_cancelada' => false, 'todas_finalizadas' => false, 'total_provas' => 0];
        }

        $provasDoAluno = $this->getProvas($blocoId, $alunoId);
        $totalProvas = count($provasDoAluno);
        $finalizadas = 0;
        $canceladas = 0;
        foreach ($provasDoAluno as $provaLinha) {
            $status = (string) ($provaLinha['realizacao_status'] ?? '');
            if ($status === 'finalizado') {
                $finalizadas++;
            } elseif ($status === 'cancelada') {
                $canceladas++;
            }
        }

        return [
            'alguma_cancelada' => $canceladas > 0,
            'todas_finalizadas' => $totalProvas > 0 && $finalizadas >= $totalProvas,
            'total_provas' => $totalProvas,
        ];
    }
    
    /**
     * Busca turmas de um bloco
     */
    public function getTurmas($blocoId)
    {
        return $this->db->fetchAll(
            "SELECT t.*
             FROM provas_blocos_turmas pbt
             INNER JOIN turmas t ON pbt.turma_id = t.id
             WHERE pbt.bloco_id = :bloco_id
             ORDER BY t.nome ASC",
            ['bloco_id' => $blocoId]
        );
    }
    
    /**
     * Busca professores vinculados ao bloco com suas matérias e turmas
     */
    public function getProfessores($blocoId)
    {
        $professores = $this->db->fetchAll(
            "SELECT pbp.id as bloco_professor_id,
                    pbp.professor_id,
                    pbp.materia_id,
                    COALESCE(pbp.quantidade_questoes, 5) as quantidade_questoes,
                    prof.nome as professor_nome,
                    m.nome as materia_nome
             FROM provas_blocos_professores pbp
             INNER JOIN professores prof ON pbp.professor_id = prof.id
             INNER JOIN materias m ON pbp.materia_id = m.id
             WHERE pbp.bloco_id = :bloco_id
             ORDER BY prof.nome ASC",
            ['bloco_id' => $blocoId]
        );
        
        // Para cada professor, busca suas turmas e normaliza IDs para o front exibir matéria e checks
        foreach ($professores as &$professor) {
            $professor['professor_id'] = (int) $professor['professor_id'];
            $professor['materia_id'] = (int) $professor['materia_id'];
            $turmas = $this->db->fetchAll(
                "SELECT t.*
                 FROM provas_blocos_professores_turmas pbpt
                 INNER JOIN turmas t ON pbpt.turma_id = t.id
                 WHERE pbpt.bloco_professor_id = :bloco_professor_id
                 ORDER BY t.nome ASC",
                ['bloco_professor_id' => $professor['bloco_professor_id']]
            );
            $professor['turmas'] = array_map(function($t) {
                return ['id' => (int)$t['id'], 'nome' => $t['nome'], 'serie' => $t['serie'] ?? null];
            }, $turmas ?: []);
        }
        unset($professor);
        return $professores;
    }
    
    /**
     * Cria um novo bloco
     */
    public function create($data)
    {
        $this->db->beginTransaction();
        
        try {
            // Insere bloco (com fallback para bancos sem novas colunas)
            $columns = [
                'titulo', 'descricao', 'data_prova', 'hora_inicio', 'hora_fim',
                'criado_por', 'tipo_prova', 'configuracao_nota', 'liberar_gabarito',
                'turma_id', 'bloco_modelo_id', 'ativo', 'liberado', 'status', 'prazo_entrega_professor'
            ];
            $formatoEvento = $data['formato_evento'] ?? 'online_questoes';
            if (!in_array($formatoEvento, ['online_questoes', 'lancamento_nota'], true)) {
                $formatoEvento = 'online_questoes';
            }
            [$dataProva, $horaInicio, $horaFim] = $this->resolverAgendaObrigatoria(
                $data['data_prova'] ?? null,
                $data['hora_inicio'] ?? null,
                $data['hora_fim'] ?? null
            );

            $params = [
                'titulo' => $data['titulo'],
                'descricao' => $data['descricao'] ?? null,
                'data_prova' => $dataProva,
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'criado_por' => $data['criado_por'],
                'tipo_prova' => $data['tipo_prova'] ?? 'original',
                'configuracao_nota' => $data['configuracao_nota'] ?? 'professor_por_questao',
                'liberar_gabarito' => $data['liberar_gabarito'] ?? 'imediatamente',
                'turma_id' => $data['turma_id'] ?? null,
                'bloco_modelo_id' => !empty($data['bloco_modelo_id']) ? (int)$data['bloco_modelo_id'] : null,
                'ativo' => $data['ativo'] ?? 1,
                'liberado' => !empty($data['liberado']) ? 1 : 0,
                'status' => !empty($data['liberado']) ? 'liberado' : 'aguardando',
                'prazo_entrega_professor' => $data['prazo_entrega_professor'] ?? null
            ];

            if ($this->hasProvasBlocosColumn('ano_letivo')) {
                $columns[] = 'ano_letivo';
                $params['ano_letivo'] = isset($data['ano_letivo']) ? (int)$data['ano_letivo'] : null;
            }
            if ($this->hasProvasBlocosColumn('bimestre')) {
                $columns[] = 'bimestre';
                $params['bimestre'] = isset($data['bimestre']) ? (int)$data['bimestre'] : null;
            }
            if ($this->hasProvasBlocosColumn('tipo_avaliacao_id')) {
                $columns[] = 'tipo_avaliacao_id';
                $params['tipo_avaliacao_id'] = !empty($data['tipo_avaliacao_id']) ? (int)$data['tipo_avaliacao_id'] : null;
            }
            if ($this->hasProvasBlocosColumn('semana')) {
                $columns[] = 'semana';
                $semana = isset($data['semana']) ? (int) $data['semana'] : 0;
                $params['semana'] = ($semana >= 1 && $semana <= 8) ? $semana : null;
            }
            if ($this->hasProvasBlocosColumn('formato_evento')) {
                $columns[] = 'formato_evento';
                $params['formato_evento'] = $formatoEvento;
            }
            if ($this->hasProvasBlocosColumn('visivel_no_portal_aluno')) {
                $columns[] = 'visivel_no_portal_aluno';
                $params['visivel_no_portal_aluno'] = isset($data['visivel_no_portal_aluno'])
                    ? ((int) $data['visivel_no_portal_aluno'] ? 1 : 0)
                    : 0;
            }
            if ($this->hasProvasBlocosColumn('nota_unica_todas_materias')) {
                $columns[] = 'nota_unica_todas_materias';
                $params['nota_unica_todas_materias'] = isset($data['nota_unica_todas_materias'])
                    ? ((int) $data['nota_unica_todas_materias'] ? 1 : 0)
                    : 0;
            }

            $placeholders = array_map(function ($col) {
                return ':' . $col;
            }, $columns);

            $sql = "INSERT INTO provas_blocos (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $placeholders) . ")";

            $blocoId = $this->db->insert($sql, $params);
            
            // Adiciona professores com suas matérias e turmas
            if (!empty($data['professores']) && is_array($data['professores'])) {
                $turmasDoBloco = isset($data['turmas']) && is_array($data['turmas']) ? $data['turmas'] : [];
                foreach ($data['professores'] as $professorData) {
                    $qtdQuestoes = (int)($professorData['quantidade_questoes'] ?? $professorData['numero_questoes'] ?? 5);
                    if ($qtdQuestoes < 1) {
                        $qtdQuestoes = 5;
                    }
                    // Insere relacionamento bloco-professor-matéria
                    $blocoProfessorId = $this->db->insert(
                        "INSERT INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes) 
                         VALUES (:bloco_id, :professor_id, :materia_id, :quantidade_questoes)",
                        [
                            'bloco_id' => $blocoId,
                            'professor_id' => $professorData['professor_id'],
                            'materia_id' => $professorData['materia_id'],
                            'quantidade_questoes' => $qtdQuestoes
                        ]
                    );
                    // Turmas: por professor (form envia) ou turmas gerais do bloco (para edição vir preenchida)
                    $turmasParaProfessor = isset($professorData['turmas']) && is_array($professorData['turmas'])
                        ? $professorData['turmas']
                        : $turmasDoBloco;
                    if ($blocoProfessorId && !empty($turmasParaProfessor)) {
                        foreach ($turmasParaProfessor as $turmaId) {
                            $tid = is_array($turmaId) ? (int)($turmaId['id'] ?? $turmaId) : (int)$turmaId;
                            if ($tid > 0) {
                                $this->db->query(
                                    "INSERT IGNORE INTO provas_blocos_professores_turmas (bloco_professor_id, turma_id) VALUES (:bp_id, :turma_id)",
                                    ['bp_id' => $blocoProfessorId, 'turma_id' => $tid]
                                );
                            }
                        }
                    }
                }
            }
            
            // Adiciona provas ao bloco
            if (!empty($data['provas'])) {
                $this->adicionarProvas($blocoId, $data['provas']);
            }
            
            // Adiciona turmas gerais ao bloco (para compatibilidade)
            if (!empty($data['turmas'])) {
                $this->adicionarTurmas($blocoId, $data['turmas']);
            } elseif (!empty($data['turma_id'])) {
                $this->adicionarTurmas($blocoId, [$data['turma_id']]);
            }
            
            // Bloco novo fica aguardando; provas ficam agendadas até aprovação
            if (!empty($data['provas'])) {
                $this->atualizarStatusProvas($data['provas'], 'agendada');
                if (!empty($data['liberado'])) {
                    $provasIds = array_values(array_map('intval', $data['provas']));
                    $placeholdersProvas = implode(',', array_fill(0, count($provasIds), '?'));
                    $this->db->query(
                        "UPDATE provas SET liberada = 1, ativo = 1 WHERE id IN ($placeholdersProvas) AND deleted_at IS NULL",
                        $provasIds
                    );
                }
            }
            
            $this->db->commit();
            return $blocoId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Atualiza um bloco
     */
    public function update($id, $data)
    {
        $this->db->beginTransaction();
        
        try {
            // Busca provas antigas para reverter status se necessário
            $provasAntigas = $this->db->fetchAll(
                "SELECT prova_id FROM provas_blocos_vinculo WHERE bloco_id = :bloco_id",
                ['bloco_id' => $id]
            );
            $provasAntigasIds = array_column($provasAntigas, 'prova_id');
            
            // Atualiza bloco (com fallback para bancos sem novas colunas)
            $setParts = [
                'titulo = :titulo',
                'descricao = :descricao',
                'data_prova = :data_prova',
                'hora_inicio = :hora_inicio',
                'hora_fim = :hora_fim',
                'tipo_prova = :tipo_prova',
                'configuracao_nota = :configuracao_nota',
                'liberar_gabarito = :liberar_gabarito',
                'turma_id = :turma_id',
                'ativo = :ativo',
                'liberado = :liberado',
                "status = CASE WHEN :liberado_status = 1 THEN 'liberado' ELSE status END"
            ];
            $formatoEvento = $data['formato_evento'] ?? 'online_questoes';
            if (!in_array($formatoEvento, ['online_questoes', 'lancamento_nota'], true)) {
                $formatoEvento = 'online_questoes';
            }
            [$dataProva, $horaInicio, $horaFim] = $this->resolverAgendaObrigatoria(
                $data['data_prova'] ?? null,
                $data['hora_inicio'] ?? null,
                $data['hora_fim'] ?? null
            );

            $params = [
                'id' => $id,
                'titulo' => $data['titulo'],
                'descricao' => $data['descricao'] ?? null,
                'data_prova' => $dataProva,
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'tipo_prova' => $data['tipo_prova'] ?? 'original',
                'configuracao_nota' => $data['configuracao_nota'] ?? 'professor_por_questao',
                'liberar_gabarito' => $data['liberar_gabarito'] ?? 'imediatamente',
                'turma_id' => $data['turma_id'] ?? null,
                'ativo' => $data['ativo'] ?? 1,
                'liberado' => $data['liberado'] ?? 0,
                'liberado_status' => $data['liberado'] ?? 0
            ];

            if ($this->hasProvasBlocosColumn('ano_letivo')) {
                $setParts[] = 'ano_letivo = :ano_letivo';
                $params['ano_letivo'] = isset($data['ano_letivo']) ? (int)$data['ano_letivo'] : null;
            }
            if ($this->hasProvasBlocosColumn('bimestre')) {
                $setParts[] = 'bimestre = :bimestre';
                $params['bimestre'] = isset($data['bimestre']) ? (int)$data['bimestre'] : null;
            }
            if ($this->hasProvasBlocosColumn('tipo_avaliacao_id')) {
                $setParts[] = 'tipo_avaliacao_id = :tipo_avaliacao_id';
                $params['tipo_avaliacao_id'] = !empty($data['tipo_avaliacao_id']) ? (int)$data['tipo_avaliacao_id'] : null;
            }
            if ($this->hasProvasBlocosColumn('semana')) {
                $setParts[] = 'semana = :semana';
                $semana = isset($data['semana']) ? (int) $data['semana'] : 0;
                $params['semana'] = ($semana >= 1 && $semana <= 8) ? $semana : null;
            }
            if ($this->hasProvasBlocosColumn('formato_evento')) {
                $setParts[] = 'formato_evento = :formato_evento';
                $params['formato_evento'] = $formatoEvento;
            }
            if ($this->hasProvasBlocosColumn('visivel_no_portal_aluno')) {
                $setParts[] = 'visivel_no_portal_aluno = :visivel_no_portal_aluno';
                $params['visivel_no_portal_aluno'] = isset($data['visivel_no_portal_aluno'])
                    ? ((int) $data['visivel_no_portal_aluno'] ? 1 : 0)
                    : 0;
            }
            if ($this->hasProvasBlocosColumn('nota_unica_todas_materias')) {
                $setParts[] = 'nota_unica_todas_materias = :nota_unica_todas_materias';
                $params['nota_unica_todas_materias'] = isset($data['nota_unica_todas_materias'])
                    ? ((int) $data['nota_unica_todas_materias'] ? 1 : 0)
                    : 0;
            }

            $sql = "UPDATE provas_blocos SET
                        " . implode(",\n                        ", $setParts) . "
                    WHERE id = :id AND deleted_at IS NULL";

            $this->db->query($sql, $params);
            
            // Remove professores antigos
            $blocosProfessoresAntigos = $this->db->fetchAll(
                "SELECT id FROM provas_blocos_professores WHERE bloco_id = :bloco_id",
                ['bloco_id' => $id]
            );
            
            if (!empty($blocosProfessoresAntigos)) {
                $blocosProfessoresIds = array_column($blocosProfessoresAntigos, 'id');
                
                // Remove turmas dos professores antigos
                $placeholders = implode(',', array_fill(0, count($blocosProfessoresIds), '?'));
                $this->db->query(
                    "DELETE FROM provas_blocos_professores_turmas WHERE bloco_professor_id IN ($placeholders)",
                    $blocosProfessoresIds
                );
                
                // Remove professores antigos
                $this->db->query(
                    "DELETE FROM provas_blocos_professores WHERE bloco_id = :bloco_id",
                    ['bloco_id' => $id]
                );
            }
            
            // Adiciona novos professores com suas matérias e turmas
            if (!empty($data['professores']) && is_array($data['professores'])) {
                $turmasDoBloco = isset($data['turmas']) && is_array($data['turmas']) ? $data['turmas'] : [];
                foreach ($data['professores'] as $professorData) {
                    $qtdQuestoes = (int) ($professorData['quantidade_questoes'] ?? $professorData['numero_questoes'] ?? 5);
                    if ($qtdQuestoes < 1) {
                        $qtdQuestoes = 5;
                    }
                    // Insere relacionamento bloco-professor-matéria
                    $blocoProfessorId = $this->db->insert(
                        "INSERT INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes) 
                         VALUES (:bloco_id, :professor_id, :materia_id, :quantidade_questoes)",
                        [
                            'bloco_id' => $id,
                            'professor_id' => $professorData['professor_id'],
                            'materia_id' => $professorData['materia_id'],
                            'quantidade_questoes' => $qtdQuestoes
                        ]
                    );
                    // Vincula turmas deste professor ao bloco-professor.
                    // Se não vier turma por professor, usa turmas gerais do bloco.
                    $turmasParaProfessor = isset($professorData['turmas']) && is_array($professorData['turmas'])
                        ? $professorData['turmas']
                        : $turmasDoBloco;
                    if ($blocoProfessorId && !empty($turmasParaProfessor)) {
                        foreach ($turmasParaProfessor as $turmaId) {
                            $tid = is_array($turmaId) ? (int)($turmaId['id'] ?? $turmaId) : (int)$turmaId;
                            if ($tid > 0) {
                                $this->db->query(
                                    "INSERT IGNORE INTO provas_blocos_professores_turmas (bloco_professor_id, turma_id) VALUES (:bp_id, :turma_id)",
                                    ['bp_id' => $blocoProfessorId, 'turma_id' => $tid]
                                );
                            }
                        }
                    }
                }
            }
            
            // Só altera vínculos de provas quando a lista for explicitamente enviada (evita ao editar
            // apenas quantidade de questões/turmas o form não enviar provas[] e todas ficarem "Não Enviada")
            $provasEnviadas = isset($data['provas']) && is_array($data['provas']) ? $data['provas'] : null;
            if ($provasEnviadas !== null) {
                // Remove provas antigas e revincula conforme lista enviada
                $this->db->query(
                    "DELETE FROM provas_blocos_vinculo WHERE bloco_id = :bloco_id",
                    ['bloco_id' => $id]
                );
                if (!empty($provasEnviadas)) {
                    $this->adicionarProvas($id, $provasEnviadas);
                    $provasIds = array_values(array_map('intval', $provasEnviadas));
                    $provasJaAprovadas = $this->db->fetchAll(
                        "SELECT id FROM provas WHERE id IN (" . implode(',', array_fill(0, count($provasIds), '?')) . ") AND status = 'aprovada' AND deleted_at IS NULL",
                        $provasIds
                    );
                    $idsAprovadas = array_column($provasJaAprovadas ?: [], 'id');
                    $provasParaAgendar = array_values(array_diff($provasIds, $idsAprovadas));
                    if (!empty($provasParaAgendar)) {
                        $this->atualizarStatusProvas($provasParaAgendar, 'agendada');
                    }
                }
                // Reverte status das provas removidas (só as que não estão aprovadas)
                if (!empty($provasAntigasIds)) {
                    $provasRemovidas = array_diff($provasAntigasIds, $provasEnviadas);
                    if (!empty($provasRemovidas)) {
                        $provasRemovidas = array_values($provasRemovidas);
                        $placeholders = implode(',', array_fill(0, count($provasRemovidas), '?'));
                        $jaAprovadas = $this->db->fetchAll(
                            "SELECT id FROM provas WHERE id IN ($placeholders) AND status = 'aprovada' AND deleted_at IS NULL",
                            $provasRemovidas
                        );
                        $idsManterAprovada = array_column($jaAprovadas ?: [], 'id');
                        $paraPendente = array_values(array_diff($provasRemovidas, $idsManterAprovada));
                        if (!empty($paraPendente)) {
                            $this->atualizarStatusProvas($paraPendente, 'pendente');
                        }
                    }
                }
            }
            
            // Remove turmas antigas
            $this->db->query(
                "DELETE FROM provas_blocos_turmas WHERE bloco_id = :bloco_id",
                ['bloco_id' => $id]
            );
            
            // Adiciona novas turmas
            if (!empty($data['turmas'])) {
                $this->adicionarTurmas($id, $data['turmas']);
            } elseif (!empty($data['turma_id'])) {
                $this->adicionarTurmas($id, [$data['turma_id']]);
            }

            if (!empty($data['liberado'])) {
                $this->db->query(
                    "UPDATE provas p
                     INNER JOIN provas_blocos_vinculo pbp ON p.id = pbp.prova_id
                     SET p.liberada = 1, p.ativo = 1
                     WHERE pbp.bloco_id = :bloco_id
                     AND p.deleted_at IS NULL",
                    ['bloco_id' => $id]
                );
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Atualiza apenas data e horários do bloco (sem alterar professores, turmas, provas).
     * Se o novo horário final (data_prova + hora_fim) for no futuro, reabre o bloco (status=liberado, liberado=1, ativo=1).
     */
    public function updateDatasHorarios($id, $dataProva, $horaInicio, $horaFim)
    {
        // Conexões de tenant usam ATTR_EMULATE_PREPARES=false; placeholders nomeados não podem
        // se repetir (HY093). Por isso cada ocorrência de data_prova/hora_fim tem nome próprio.
        $this->db->query(
            "UPDATE provas_blocos SET
                data_prova = :data_prova,
                hora_inicio = :hora_inicio,
                hora_fim = :hora_fim,
                status = CASE 
                    WHEN CONCAT(:data_prova_s, ' ', :hora_fim_s) >= NOW() THEN 'liberado'
                    ELSE status
                END,
                liberado = CASE 
                    WHEN CONCAT(:data_prova_l, ' ', :hora_fim_l) >= NOW() THEN 1
                    ELSE liberado
                END,
                ativo = CASE 
                    WHEN CONCAT(:data_prova_a, ' ', :hora_fim_a) >= NOW() THEN 1
                    ELSE ativo
                END
             WHERE id = :id AND deleted_at IS NULL",
            [
                'id' => $id,
                'data_prova' => $dataProva,
                'data_prova_s' => $dataProva,
                'data_prova_l' => $dataProva,
                'data_prova_a' => $dataProva,
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'hora_fim_s' => $horaFim,
                'hora_fim_l' => $horaFim,
                'hora_fim_a' => $horaFim
            ]
        );
        return true;
    }
    
    /**
     * Adiciona provas a um bloco
     */
    private function adicionarProvas($blocoId, $provas)
    {
        foreach ($provas as $index => $provaId) {
            $this->db->query(
                "INSERT INTO provas_blocos_vinculo (bloco_id, prova_id, ordem) 
                 VALUES (:bloco_id, :prova_id, :ordem)
                 ON DUPLICATE KEY UPDATE ordem = :ordem_update",
                [
                    'bloco_id' => $blocoId,
                    'prova_id' => $provaId,
                    'ordem' => $index,
                    'ordem_update' => $index
                ]
            );
        }
    }
    
    /**
     * Vincula uma prova ao bloco (uso na tela Gerenciar - recuperar provas desvinculadas).
     * Não altera o status da prova (ex.: mantém "enviada" ou "aprovada").
     *
     * @param int $blocoId
     * @param int $provaId
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function vincularProva($blocoId, $provaId)
    {
        $blocoId = (int) $blocoId;
        $provaId = (int) $provaId;
        if ($blocoId <= 0 || $provaId <= 0) {
            return ['success' => false, 'error' => 'Bloco ou prova inválido.'];
        }
        $bloco = $this->db->fetch(
            "SELECT id FROM provas_blocos WHERE id = :id AND deleted_at IS NULL",
            ['id' => $blocoId]
        );
        if (!$bloco) {
            return ['success' => false, 'error' => 'Bloco não encontrado.'];
        }
        $prova = $this->db->fetch(
            "SELECT id, professor_id, materia_id, status FROM provas WHERE id = :id AND deleted_at IS NULL",
            ['id' => $provaId]
        );
        if (!$prova) {
            return ['success' => false, 'error' => 'Prova não encontrada.'];
        }
        $jaVinculada = $this->db->fetch(
            "SELECT 1 FROM provas_blocos_vinculo WHERE bloco_id = :bloco_id AND prova_id = :prova_id",
            ['bloco_id' => $blocoId, 'prova_id' => $provaId]
        );
        if ($jaVinculada) {
            return ['success' => true, 'error' => null]; // já vinculada, idempotente
        }
        $professorNoBloco = $this->db->fetch(
            "SELECT 1 FROM provas_blocos_professores WHERE bloco_id = :bloco_id AND professor_id = :professor_id AND materia_id = :materia_id",
            ['bloco_id' => $blocoId, 'professor_id' => $prova['professor_id'], 'materia_id' => $prova['materia_id']]
        );
        if (!$professorNoBloco) {
            return ['success' => false, 'error' => 'Esta prova não corresponde a um professor/matéria deste bloco.'];
        }
        $maxOrdem = $this->db->fetch(
            "SELECT COALESCE(MAX(ordem), -1) + 1 as proxima FROM provas_blocos_vinculo WHERE bloco_id = :bloco_id",
            ['bloco_id' => $blocoId]
        );
        $ordem = (int) ($maxOrdem['proxima'] ?? 0);
        $this->db->query(
            "INSERT INTO provas_blocos_vinculo (bloco_id, prova_id, ordem) VALUES (:bloco_id, :prova_id, :ordem)",
            ['bloco_id' => $blocoId, 'prova_id' => $provaId, 'ordem' => $ordem]
        );
        return ['success' => true, 'error' => null];
    }
    
    /**
     * Troca a prova vinculada ao bloco por outra (mesmo professor/matéria).
     * Remove a prova atual do vínculo e insere a nova na mesma ordem.
     *
     * @param int $blocoId
     * @param int $provaAtualId
     * @param int $novaProvaId
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function trocarProva($blocoId, $provaAtualId, $novaProvaId)
    {
        $blocoId = (int) $blocoId;
        $provaAtualId = (int) $provaAtualId;
        $novaProvaId = (int) $novaProvaId;
        if ($blocoId <= 0 || $provaAtualId <= 0 || $novaProvaId <= 0) {
            return ['success' => false, 'error' => 'Parâmetros inválidos.'];
        }
        $bloco = $this->db->fetch(
            "SELECT id FROM provas_blocos WHERE id = :id AND deleted_at IS NULL",
            ['id' => $blocoId]
        );
        if (!$bloco) {
            return ['success' => false, 'error' => 'Bloco não encontrado.'];
        }
        $vinculoAtual = $this->db->fetch(
            "SELECT ordem FROM provas_blocos_vinculo WHERE bloco_id = :bloco_id AND prova_id = :prova_id",
            ['bloco_id' => $blocoId, 'prova_id' => $provaAtualId]
        );
        if (!$vinculoAtual) {
            return ['success' => false, 'error' => 'A prova atual não está vinculada a este bloco.'];
        }
        $provaAtual = $this->db->fetch(
            "SELECT id, professor_id, materia_id FROM provas WHERE id = :id AND deleted_at IS NULL",
            ['id' => $provaAtualId]
        );
        $novaProva = $this->db->fetch(
            "SELECT id, professor_id, materia_id FROM provas WHERE id = :id AND deleted_at IS NULL",
            ['id' => $novaProvaId]
        );
        if (!$novaProva) {
            return ['success' => false, 'error' => 'Nova prova não encontrada.'];
        }
        if ($novaProva['professor_id'] != $provaAtual['professor_id'] || $novaProva['materia_id'] != $provaAtual['materia_id']) {
            return ['success' => false, 'error' => 'A nova prova deve ser do mesmo professor e matéria.'];
        }
        $jaVinculada = $this->db->fetch(
            "SELECT 1 FROM provas_blocos_vinculo WHERE bloco_id = :bloco_id AND prova_id = :prova_id",
            ['bloco_id' => $blocoId, 'prova_id' => $novaProvaId]
        );
        if ($jaVinculada) {
            return ['success' => false, 'error' => 'A nova prova já está vinculada a este bloco.'];
        }
        $ordem = (int) ($vinculoAtual['ordem'] ?? 0);
        $this->db->query(
            "DELETE FROM provas_blocos_vinculo WHERE bloco_id = :bloco_id AND prova_id = :prova_id",
            ['bloco_id' => $blocoId, 'prova_id' => $provaAtualId]
        );
        $this->db->query(
            "INSERT INTO provas_blocos_vinculo (bloco_id, prova_id, ordem) VALUES (:bloco_id, :prova_id, :ordem)",
            ['bloco_id' => $blocoId, 'prova_id' => $novaProvaId, 'ordem' => $ordem]
        );
        return ['success' => true, 'error' => null];
    }
    
    /**
     * Lista provas que podem ser vinculadas ao bloco para um professor/matéria
     * (provas do professor na matéria que ainda não estão em nenhum bloco).
     *
     * @param int $blocoId
     * @param int $professorId
     * @param int $materiaId
     * @return array
     */
    public function getProvasDisponiveisParaVincular($blocoId, $professorId, $materiaId)
    {
        $blocoId = (int) $blocoId;
        $professorId = (int) $professorId;
        $materiaId = (int) $materiaId;
        if ($blocoId <= 0 || $professorId <= 0 || $materiaId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT p.id, p.titulo, p.status, p.turma_id,
                    (SELECT COUNT(*) FROM provas_questoes pq WHERE pq.prova_id = p.id) as numero_questoes,
                    COALESCE(p.data_envio, p.created_at) as data_envio,
                    t.nome as turma_nome
             FROM provas p
             LEFT JOIN turmas t ON p.turma_id = t.id
             WHERE p.professor_id = :professor_id
             AND p.materia_id = :materia_id
             AND p.deleted_at IS NULL
             AND NOT EXISTS (
                 SELECT 1 FROM provas_blocos_vinculo pbp WHERE pbp.prova_id = p.id
             )
             ORDER BY COALESCE(p.data_envio, p.created_at) DESC",
            ['professor_id' => $professorId, 'materia_id' => $materiaId]
        );
    }

    /**
     * Garante valores válidos para colunas NOT NULL de agenda no banco.
     * Quando o formulário não envia agenda, aplica defaults técnicos.
     */
    private function resolverAgendaObrigatoria($dataProvaRaw, $horaInicioRaw, $horaFimRaw): array
    {
        $dataProva = trim((string) $dataProvaRaw);
        if ($dataProva === '') {
            $dataProva = date('Y-m-d');
        }

        $horaInicio = $this->normalizarHora($horaInicioRaw, '00:00:00');
        $horaFim = $this->normalizarHora($horaFimRaw, '23:59:59');

        return [$dataProva, $horaInicio, $horaFim];
    }

    private function normalizarHora($horaRaw, string $fallback): string
    {
        $hora = trim((string) $horaRaw);
        if ($hora === '') {
            return $fallback;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $hora) === 1) {
            return $hora . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora) === 1) {
            return $hora;
        }
        return $fallback;
    }
    
    /**
     * Adiciona turmas a um bloco
     */
    private function adicionarTurmas($blocoId, $turmas)
    {
        foreach ($turmas as $turmaId) {
            $this->db->query(
                "INSERT INTO provas_blocos_turmas (bloco_id, turma_id) 
                 VALUES (:bloco_id, :turma_id)
                 ON DUPLICATE KEY UPDATE turma_id = :turma_id_update",
                [
                    'bloco_id' => $blocoId,
                    'turma_id' => $turmaId,
                    'turma_id_update' => $turmaId
                ]
            );
        }
    }
    
    /**
     * Atualiza status de múltiplas provas
     */
    private function atualizarStatusProvas($provasIds, $status)
    {
        if (empty($provasIds)) {
            return;
        }
        
        $placeholders = implode(',', array_fill(0, count($provasIds), '?'));
        $sql = "UPDATE provas SET status = ? WHERE id IN ($placeholders)";
        $params = array_merge([$status], $provasIds);
        
        $this->db->query($sql, $params);
    }
    
    /**
     * Busca blocos disponíveis para um aluno
     */
    public function findByAluno($alunoId)
    {
        $this->marcarConcluidos();
        $this->reabrirSePrazoEstendido();
        // Busca turma do aluno
        $aluno = $this->db->fetch(
            "SELECT turma_id FROM alunos WHERE id = :aluno_id",
            ['aluno_id' => $alunoId]
        );
        
        if (!$aluno) {
            if (defined('DEBUG') && DEBUG) {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    }
                }
            }
            return [];
        }
        
        // Turmas do aluno = turma principal (alunos.turma_id) + matrículas ativas
        // (curso extra/paralelas). Espelha o lado do professor/coordenação, que já
        // inclui matrículas, para que o aluno enxergue os blocos das turmas em que
        // está efetivamente matriculado (não só a turma principal do cadastro).
        $turmaIds = [];
        $principal = (int) ($aluno['turma_id'] ?? 0);
        if ($principal > 0) {
            $turmaIds[] = $principal;
        }
        try {
            if ($this->db->fetch("SHOW TABLES LIKE 'matricula'") !== false) {
                $mats = $this->db->fetchAll(
                    "SELECT DISTINCT turma_id FROM matricula
                     WHERE aluno_id = :aluno_id AND status = 'ativa' AND data_saida IS NULL",
                    ['aluno_id' => $alunoId]
                ) ?: [];
                foreach ($mats as $m) {
                    $tid = (int) ($m['turma_id'] ?? 0);
                    if ($tid > 0) {
                        $turmaIds[] = $tid;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Sem tabela de matrícula: usa apenas a turma principal.
        }
        $turmaIds = array_values(array_unique($turmaIds));
        // Fallback: lista sentinela (0) nunca casa com turma real, evitando match indevido.
        $turmaList = $turmaIds === [] ? [0] : $turmaIds;
        $placeholders = implode(',', array_fill(0, count($turmaList), '?'));

        $filtroVisivelAluno = '';
        if ($this->hasProvasBlocosColumn('visivel_no_portal_aluno')) {
            $filtroVisivelAluno = ' AND pb.visivel_no_portal_aluno = 1 ';
        }

        // Busca blocos: liberados de qualquer turma do aluno OU blocos visíveis no portal
        // dentro do prazo. O segundo caso cobre eventos editados com "Mostrar no portal"
        // antes de o campo liberado ser sincronizado. Placeholders posicionais por causa do IN.
        $visivelPortalExpr = $this->hasProvasBlocosColumn('visivel_no_portal_aluno')
            ? 'pb.visivel_no_portal_aluno = 1'
            : '1 = 1';
        $sql = "SELECT DISTINCT pb.*
                FROM provas_blocos pb
                LEFT JOIN provas_blocos_turmas pbt ON pb.id = pbt.bloco_id AND pbt.turma_id IN ($placeholders)
                LEFT JOIN provas_blocos_vinculo pbv ON pbv.bloco_id = pb.id
                LEFT JOIN provas_realizacoes pr ON pr.prova_id = pbv.prova_id AND pr.aluno_id = ?
                WHERE pb.deleted_at IS NULL
                {$filtroVisivelAluno}
                AND (
                    (pb.ativo = 1 AND (pb.liberado = 1 OR ({$visivelPortalExpr} AND CONCAT(pb.data_prova, ' ', pb.hora_fim) >= NOW())) AND (
                        pb.turma_id IN ($placeholders)
                        OR pbt.turma_id IN ($placeholders)
                        OR (pb.turma_id IS NULL AND NOT EXISTS (
                            SELECT 1 FROM provas_blocos_turmas pbt2
                            WHERE pbt2.bloco_id = pb.id
                        ))
                    ))
                    OR pr.status = 'finalizado'
                )
                ORDER BY pb.data_prova ASC, pb.hora_inicio ASC";

        $params = array_merge($turmaList, [$alunoId], $turmaList, $turmaList);
        $result = $this->db->fetchAll($sql, $params);
        
        if (defined('DEBUG') && DEBUG) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Desabilita a visualização do bloco (LGPD: não apaga dados, só oculta).
     * Bloco some do admin, aluno e professor; provas do bloco ficam inacessíveis.
     * @param int $id ID do bloco
     * @param int|null $deletedByUserId ID do usuário (admin) que desativou
     */
    public function delete($id, $deletedByUserId = null)
    {
        $this->db->beginTransaction();
        
        try {
            $provas = $this->db->fetchAll(
                "SELECT prova_id FROM provas_blocos_vinculo WHERE bloco_id = :bloco_id",
                ['bloco_id' => $id]
            );
            $provasIds = array_column($provas, 'prova_id');
            
            // Soft delete do bloco e registro de quem desativou
            $this->db->query(
                "UPDATE provas_blocos SET deleted_at = NOW(), liberado = 0, deleted_by = :deleted_by WHERE id = :id",
                ['id' => $id, 'deleted_by' => $deletedByUserId]
            );
            
            // Desabilita visualização das provas do bloco (ativo=0, liberada=0) sem apagar dados
            if (!empty($provasIds)) {
                $placeholders = implode(',', array_fill(0, count($provasIds), '?'));
                $this->db->query(
                    "UPDATE provas SET ativo = 0, liberada = 0 WHERE id IN ($placeholders)",
                    $provasIds
                );
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Busca provas pendentes (aguardando agrupamento)
     * Inclui apenas provas com status 'enviada' que ainda não estão em blocos
     */
    public function getProvasPendentes()
    {
        return $this->db->fetchAll(
            "SELECT p.*,
                    m.nome as materia_nome,
                    prof.nome as professor_nome,
                    t.nome as turma_nome
             FROM provas p
             LEFT JOIN materias m ON p.materia_id = m.id
             LEFT JOIN professores prof ON p.professor_id = prof.id
             LEFT JOIN turmas t ON p.turma_id = t.id
             WHERE p.status IN ('enviada', 'aprovada', 'reprovada')
             AND p.deleted_at IS NULL
             AND p.ativo = 1
             AND NOT EXISTS (
                 SELECT 1 FROM provas_blocos_vinculo pbp
                 WHERE pbp.prova_id = p.id
             )
             ORDER BY p.created_at DESC"
        );
    }
}
