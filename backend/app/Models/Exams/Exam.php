<?php
/**
 * EducaTudo - Modelo de Provas
 * Gerencia operações de banco de dados para provas online
 */

class Exam
{
    private $db;
    private $hasInvalidadaColumnCache = null;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Busca todas as provas (sem soft delete)
     */
    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT p.*, 
                    prof.nome as professor_nome,
                    m.nome as materia_nome,
                    t.nome as turma_nome
             FROM provas p
             LEFT JOIN professores prof ON p.professor_id = prof.id
             LEFT JOIN materias m ON p.materia_id = m.id
             LEFT JOIN turmas t ON p.turma_id = t.id
             WHERE p.deleted_at IS NULL
             ORDER BY p.data_inicio DESC, p.created_at DESC"
        );
    }
    
    /**
     * Busca provas por professor (exclui provas de blocos desativados / ativo = 0)
     */
    public function findByProfessor($professorId)
    {
        return $this->db->fetchAll(
            "SELECT p.*, 
                    m.nome as materia_nome,
                    t.nome as turma_nome
             FROM provas p
             LEFT JOIN materias m ON p.materia_id = m.id
             LEFT JOIN turmas t ON p.turma_id = t.id
             WHERE p.professor_id = :professor_id 
             AND p.deleted_at IS NULL
             AND (p.ativo = 1 OR p.ativo IS NULL)
             ORDER BY p.data_inicio DESC, p.created_at DESC",
            ['professor_id' => $professorId]
        );
    }
    
    /**
     * Busca prova por ID
     */
    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT p.*, 
                    prof.nome as professor_nome,
                    prof.id as professor_id,
                    m.nome as materia_nome,
                    m.id as materia_id,
                    t.nome as turma_nome,
                    t.id as turma_id
             FROM provas p
             LEFT JOIN professores prof ON p.professor_id = prof.id
             LEFT JOIN materias m ON p.materia_id = m.id
             LEFT JOIN turmas t ON p.turma_id = t.id
             WHERE p.id = :id 
             AND p.deleted_at IS NULL",
            ['id' => $id]
        );
    }
    
    /**
     * Busca provas disponíveis para um aluno
     */
    public function findByAluno($alunoId)
    {
        if (!class_exists('AlunoTurmaHelper')) {
            require_once __DIR__ . '/../../Core/AlunoTurmaHelper.php';
        }

        $turmaIds = AlunoTurmaHelper::getTurmaIds($this->db, (int) $alunoId);
        $now = date('Y-m-d H:i:s');

        // Sem turmas: só provas globais (sem turma específica).
        if ($turmaIds === []) {
            return $this->db->fetchAll(
                "SELECT DISTINCT p.*,
                        m.nome as materia_nome,
                        t.nome as turma_nome,
                        pr.id as realizacao_id,
                        pr.status as realizacao_status,
                        pr.nota as realizacao_nota
                 FROM provas p
                 LEFT JOIN materias m ON p.materia_id = m.id
                 LEFT JOIN turmas t ON p.turma_id = t.id
                 LEFT JOIN provas_realizacoes pr ON p.id = pr.prova_id AND pr.aluno_id = :aluno_id
                 WHERE p.liberada = 1
                 AND p.ativo = 1
                 AND p.deleted_at IS NULL
                 AND p.data_inicio <= :now_inicio
                 AND p.data_fim >= :now_fim
                 AND p.turma_id IS NULL
                 AND NOT EXISTS (
                     SELECT 1 FROM provas_turmas pt2
                     WHERE pt2.prova_id = p.id
                 )
                 ORDER BY p.data_inicio DESC",
                [
                    'aluno_id' => $alunoId,
                    'now_inicio' => $now,
                    'now_fim' => $now,
                ]
            );
        }

        $turmaInPartsP = [];
        $turmaInPartsPt = [];
        $params = [
            'aluno_id' => $alunoId,
            'now_inicio' => $now,
            'now_fim' => $now,
        ];
        foreach ($turmaIds as $i => $tid) {
            $keyP = 'turma_p_' . $i;
            $keyPt = 'turma_pt_' . $i;
            $turmaInPartsP[] = ':' . $keyP;
            $turmaInPartsPt[] = ':' . $keyPt;
            $params[$keyP] = $tid;
            $params[$keyPt] = $tid;
        }
        $turmaInSqlP = implode(', ', $turmaInPartsP);
        $turmaInSqlPt = implode(', ', $turmaInPartsPt);

        return $this->db->fetchAll(
            "SELECT DISTINCT p.*,
                    m.nome as materia_nome,
                    t.nome as turma_nome,
                    pr.id as realizacao_id,
                    pr.status as realizacao_status,
                    pr.nota as realizacao_nota
             FROM provas p
             LEFT JOIN materias m ON p.materia_id = m.id
             LEFT JOIN turmas t ON p.turma_id = t.id
             LEFT JOIN provas_turmas pt ON p.id = pt.prova_id
             LEFT JOIN provas_realizacoes pr ON p.id = pr.prova_id AND pr.aluno_id = :aluno_id
             WHERE p.liberada = 1
             AND p.ativo = 1
             AND p.deleted_at IS NULL
             AND p.data_inicio <= :now_inicio
             AND p.data_fim >= :now_fim
             AND (
                 p.turma_id IN ({$turmaInSqlP})
                 OR pt.turma_id IN ({$turmaInSqlPt})
                 OR (p.turma_id IS NULL AND NOT EXISTS (
                     SELECT 1 FROM provas_turmas pt2
                     WHERE pt2.prova_id = p.id
                 ))
             )
             ORDER BY p.data_inicio DESC",
            $params
        );
    }

    /**
     * Conta provas distintas disponíveis para o aluno neste momento (avulsas + blocos).
     */
    public function countAvailableForStudent(int $alunoId, ?int $turmaId = null): int
    {
        if ($alunoId <= 0) {
            return 0;
        }

        if ($turmaId === null) {
            $aluno = $this->db->fetch(
                "SELECT turma_id FROM alunos WHERE id = :id",
                ['id' => $alunoId]
            );
            $turmaId = (int) ($aluno['turma_id'] ?? 0);
        }

        $now = date('Y-m-d H:i:s');
        $params = [
            'aluno_id' => $alunoId,
            'now_inicio' => $now,
            'now_fim' => $now,
            'now_block' => $now,
            'now_block2' => $now,
        ];

        $filtroTurmaAvulsa = $turmaId > 0
            ? 'AND (p.turma_id = :turma_u1 OR pt.turma_id = :turma_u2 OR (p.turma_id IS NULL AND NOT EXISTS (SELECT 1 FROM provas_turmas pt3 WHERE pt3.prova_id = p.id)))'
            : 'AND p.turma_id IS NULL AND NOT EXISTS (SELECT 1 FROM provas_turmas pt3 WHERE pt3.prova_id = p.id)';

        $filtroTurmaBloco = $turmaId > 0
            ? 'AND (pb.turma_id = :turma_b1 OR pbt.turma_id = :turma_b2 OR (pb.turma_id IS NULL AND NOT EXISTS (SELECT 1 FROM provas_blocos_turmas pbt2 WHERE pbt2.bloco_id = pb.id)))'
            : '';

        if ($turmaId > 0) {
            $params['turma_u1'] = $turmaId;
            $params['turma_u2'] = $turmaId;
            $params['turma_b1'] = $turmaId;
            $params['turma_b2'] = $turmaId;
        }

        try {
            $row = $this->db->fetch(
                "SELECT COUNT(DISTINCT prova_id) AS total FROM (
                    SELECT p.id AS prova_id
                    FROM provas p
                    LEFT JOIN provas_turmas pt ON p.id = pt.prova_id
                    WHERE p.liberada = 1 AND p.ativo = 1 AND p.deleted_at IS NULL
                      AND p.data_inicio <= :now_inicio AND p.data_fim >= :now_fim
                      {$filtroTurmaAvulsa}
                    UNION
                    SELECT p.id AS prova_id
                    FROM provas_blocos pb
                    INNER JOIN provas_blocos_vinculo pbp ON pbp.bloco_id = pb.id
                    INNER JOIN provas p ON p.id = pbp.prova_id AND p.deleted_at IS NULL
                    LEFT JOIN provas_blocos_turmas pbt ON pbt.bloco_id = pb.id
                    WHERE pb.deleted_at IS NULL
                      AND CONCAT(pb.data_prova, ' ', COALESCE(NULLIF(pb.hora_inicio, ''), '00:00:00')) <= :now_block
                      AND CONCAT(pb.data_prova, ' ', COALESCE(NULLIF(pb.hora_fim, ''), '23:59:59')) >= :now_block2
                      AND p.ativo = 1 AND p.liberada = 1
                      {$filtroTurmaBloco}
                 ) AS disponiveis",
                $params
            );
            return (int) ($row['total'] ?? 0);
        } catch (Throwable $e) {
            error_log('Exam::countAvailableForStudent: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Busca questões de uma prova
     */
    public function getQuestoes($provaId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM provas_questoes 
             WHERE prova_id = :prova_id 
             ORDER BY ordem ASC, id ASC",
            ['prova_id' => $provaId]
        );
    }
    
    /**
     * Busca questão por ID
     */
    public function getQuestaoById($questaoId)
    {
        return $this->db->fetch(
            "SELECT * FROM provas_questoes WHERE id = :id",
            ['id' => $questaoId]
        );
    }
    
    /**
     * Busca alternativas de uma questão
     */
    public function getAlternativas($questaoId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM provas_alternativas 
             WHERE questao_id = :questao_id 
             ORDER BY ordem ASC, id ASC",
            ['questao_id' => $questaoId]
        );
    }
    
    /**
     * Busca respostas de um aluno para uma prova
     */
    public function getRespostas($provaId, $alunoId)
    {
        return $this->db->fetchAll(
            "SELECT r.*, q.tipo as questao_tipo, q.valor as questao_valor
             FROM provas_respostas r
             LEFT JOIN provas_questoes q ON r.questao_id = q.id
             WHERE r.prova_id = :prova_id AND r.aluno_id = :aluno_id",
            [
                'prova_id' => $provaId,
                'aluno_id' => $alunoId
            ]
        );
    }
    
    /**
     * Retorna quantidade de acertos e erros de um aluno em uma prova
     */
    public function getContagemAcertosErros($provaId, $alunoId)
    {
        $row = $this->db->fetch(
            "SELECT 
                SUM(CASE WHEN correta = 1 THEN 1 ELSE 0 END) as acertos,
                SUM(CASE WHEN correta = 0 THEN 1 ELSE 0 END) as erros
             FROM provas_respostas 
             WHERE prova_id = :prova_id AND aluno_id = :aluno_id",
            ['prova_id' => $provaId, 'aluno_id' => $alunoId]
        );
        return [
            'acertos' => (int)($row['acertos'] ?? 0),
            'erros' => (int)($row['erros'] ?? 0)
        ];
    }
    
    /**
     * Busca realização de prova por aluno
     */
    public function getRealizacao($provaId, $alunoId)
    {
        return $this->db->fetch(
            "SELECT * FROM provas_realizacoes 
             WHERE prova_id = :prova_id AND aluno_id = :aluno_id",
            [
                'prova_id' => $provaId,
                'aluno_id' => $alunoId
            ]
        );
    }

    /**
     * Marca realização para continuar sem tempo (liberado pelo admin).
     * Se não existir realização, retorna false (deve criar antes com iniciarRealizacao).
     */
    public function setContinuarSemTempo($provaId, $alunoId)
    {
        $realizacao = $this->getRealizacao($provaId, $alunoId);
        if (!$realizacao) {
            return false;
        }
        $this->db->query(
            "UPDATE provas_realizacoes SET continuar_sem_tempo = 1 WHERE prova_id = :prova_id AND aluno_id = :aluno_id",
            ['prova_id' => $provaId, 'aluno_id' => $alunoId]
        );
        return true;
    }

    /**
     * Bancos antigos têm provas_realizacoes.status como ENUM sem 'cancelada' —
     * gravar 'cancelada' falha com "Data truncated for column 'status'".
     * Converte para VARCHAR(20) uma única vez por request (multi-tenant: cada escola tem seu banco).
     */
    private function garantirStatusCanceladaSuportado(): void
    {
        static $verificado = false;
        if ($verificado) {
            return;
        }
        $verificado = true;
        try {
            $col = $this->db->fetch(
                "SELECT COLUMN_TYPE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'provas_realizacoes'
                   AND COLUMN_NAME = 'status'
                 LIMIT 1"
            );
            $tipo = strtolower((string) ($col['COLUMN_TYPE'] ?? ''));
            if ($tipo !== '' && strpos($tipo, 'enum') === 0 && strpos($tipo, 'cancelada') === false) {
                $this->db->query("ALTER TABLE provas_realizacoes MODIFY COLUMN status VARCHAR(20) NULL DEFAULT NULL");
            }
        } catch (Exception $e) {
            error_log('garantirStatusCanceladaSuportado: ' . $e->getMessage());
        }
    }

    /**
     * Prova avulsa/bloco: turma principal, matrículas ativas ou prova sem turma (todas).
     */
    public function alunoPodeAcessarProva(int $provaId, int $alunoId): bool
    {
        $provaId = (int) $provaId;
        $alunoId = (int) $alunoId;
        if ($provaId <= 0 || $alunoId <= 0) {
            return false;
        }
        $prova = $this->db->fetch(
            'SELECT id, turma_id FROM provas WHERE id = :id AND deleted_at IS NULL',
            ['id' => $provaId]
        );
        if (!$prova) {
            return false;
        }
        return $this->provaCombinaComTurmasDoAluno($prova, $alunoId);
    }

    /**
     * @param array{id?:int,turma_id?:mixed} $prova
     */
    private function provaCombinaComTurmasDoAluno(array $prova, int $alunoId): bool
    {
        if (!class_exists('AlunoTurmaHelper')) {
            require_once __DIR__ . '/../../Core/AlunoTurmaHelper.php';
        }
        $turmaIdsAluno = AlunoTurmaHelper::getTurmaIds($this->db, $alunoId);
        $provaTurmaId = isset($prova['turma_id']) ? (int) $prova['turma_id'] : 0;
        if ($provaTurmaId > 0 && in_array($provaTurmaId, $turmaIdsAluno, true)) {
            return true;
        }
        $vinculos = $this->db->fetchAll(
            'SELECT turma_id FROM provas_turmas WHERE prova_id = :pid',
            ['pid' => (int) ($prova['id'] ?? 0)]
        ) ?: [];
        $turmasProva = [];
        foreach ($vinculos as $row) {
            $tid = (int) ($row['turma_id'] ?? 0);
            if ($tid > 0) {
                $turmasProva[] = $tid;
            }
        }
        if ($turmasProva === []) {
            return $provaTurmaId === 0;
        }
        foreach ($turmasProva as $tid) {
            if (in_array($tid, $turmaIdsAluno, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cancela realizações não finalizadas do aluno nas provas da turma dele no bloco.
     * Só o coordenador poderá liberar nova tentativa.
     */
    public function cancelarRealizacoesBlocoSeguro($blocoId, $alunoId)
    {
        $blocoId = (int) $blocoId;
        $alunoId = (int) $alunoId;
        if ($blocoId <= 0 || $alunoId <= 0) {
            return 0;
        }

        $this->garantirStatusCanceladaSuportado();

        $provas = $this->db->fetchAll(
            "SELECT p.id, p.turma_id
             FROM provas p
             INNER JOIN provas_blocos_vinculo pbp ON pbp.prova_id = p.id
             WHERE pbp.bloco_id = :bloco_id AND p.deleted_at IS NULL",
            ['bloco_id' => $blocoId]
        );

        if (empty($provas)) {
            return 0;
        }

        $idsDoAluno = [];
        foreach ($provas as $prova) {
            $provaId = (int) ($prova['id'] ?? 0);
            if ($provaId > 0 && $this->provaCombinaComTurmasDoAluno($prova, $alunoId)) {
                $idsDoAluno[] = $provaId;
            }
        }
        $idsDoAluno = array_values(array_unique($idsDoAluno));
        if ($idsDoAluno === []) {
            return 0;
        }

        $inParts = [];
        $paramsUpdate = ['aluno_id' => $alunoId];
        foreach ($idsDoAluno as $i => $provaId) {
            $key = 'pid_' . $i;
            $inParts[] = ':' . $key;
            $paramsUpdate[$key] = $provaId;
        }
        $this->db->query(
            'UPDATE provas_realizacoes
             SET status = \'cancelada\'
             WHERE prova_id IN (' . implode(', ', $inParts) . ')
               AND aluno_id = :aluno_id
               AND status != \'finalizado\'',
            $paramsUpdate
        );

        $afetadas = 0;
        foreach ($idsDoAluno as $provaId) {
            $existente = $this->getRealizacao($provaId, $alunoId);
            if ($existente) {
                if (($existente['status'] ?? '') !== 'finalizado') {
                    $afetadas++;
                }
                continue;
            }
            $this->db->query(
                "INSERT INTO provas_realizacoes (prova_id, aluno_id, iniciado_em, status)
                 VALUES (:prova_id, :aluno_id, NOW(), 'cancelada')",
                ['prova_id' => $provaId, 'aluno_id' => $alunoId]
            );
            $afetadas++;
        }

        return $afetadas;
    }

    /**
     * Conta respostas salvas do aluno na prova (para recuperação de tentativa cancelada).
     */
    public function contarRespostasAluno($provaId, $alunoId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM provas_respostas WHERE prova_id = :prova_id AND aluno_id = :aluno_id",
            ['prova_id' => (int) $provaId, 'aluno_id' => (int) $alunoId]
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Valida tentativa cancelada que já possui respostas: calcula nota e marca como finalizada.
     * Uso: coordenador/professor quando o aluno concluiu mas o status ficou cancelada indevidamente.
     */
    public function validarTentativaCancelada($provaId, $alunoId)
    {
        $realizacao = $this->getRealizacao($provaId, $alunoId);
        if (!$realizacao || $realizacao['status'] !== 'cancelada') {
            return false;
        }
        if ($this->contarRespostasAluno($provaId, $alunoId) === 0) {
            return false;
        }
        $this->db->query(
            "UPDATE provas_realizacoes SET status = 'iniciado' WHERE prova_id = :prova_id AND aluno_id = :aluno_id AND status = 'cancelada'",
            ['prova_id' => (int) $provaId, 'aluno_id' => (int) $alunoId]
        );
        $this->finalizarProva($provaId, $alunoId);
        return true;
    }

    /**
     * Garante a tabela de histórico de validações de nota (multi-tenant: cada escola tem seu banco).
     */
    private function garantirTabelaValidacoesLog(): void
    {
        static $verificado = false;
        if ($verificado) {
            return;
        }
        $verificado = true;
        try {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS provas_validacoes_log (
                    id INT NOT NULL AUTO_INCREMENT,
                    prova_id INT NOT NULL,
                    aluno_id INT NOT NULL,
                    bloco_id INT NULL,
                    nota DECIMAL(10,2) NULL,
                    validado_por_id INT NULL,
                    validado_por_nome VARCHAR(255) NULL,
                    validado_por_tipo VARCHAR(30) NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_prova_aluno (prova_id, aluno_id),
                    KEY idx_bloco (bloco_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Exception $e) {
            error_log('garantirTabelaValidacoesLog: ' . $e->getMessage());
        }
    }

    /**
     * Registra no histórico quem validou a nota de uma tentativa cancelada.
     */
    public function registrarValidacaoNota($provaId, $alunoId, $blocoId, $nota, array $validadoPor): void
    {
        $this->garantirTabelaValidacoesLog();
        try {
            $this->db->query(
                "INSERT INTO provas_validacoes_log
                    (prova_id, aluno_id, bloco_id, nota, validado_por_id, validado_por_nome, validado_por_tipo)
                 VALUES (:prova_id, :aluno_id, :bloco_id, :nota, :validado_por_id, :validado_por_nome, :validado_por_tipo)",
                [
                    'prova_id' => (int) $provaId,
                    'aluno_id' => (int) $alunoId,
                    'bloco_id' => $blocoId !== null ? (int) $blocoId : null,
                    'nota' => $nota,
                    'validado_por_id' => (int) ($validadoPor['id'] ?? 0),
                    'validado_por_nome' => (string) ($validadoPor['nome'] ?? ''),
                    'validado_por_tipo' => (string) ($validadoPor['tipo'] ?? ''),
                ]
            );
        } catch (Exception $e) {
            error_log('registrarValidacaoNota: ' . $e->getMessage());
        }
    }

    /**
     * Histórico de validações de nota de um bloco (mais recentes primeiro).
     */
    public function getHistoricoValidacoesBloco($blocoId): array
    {
        $this->garantirTabelaValidacoesLog();
        try {
            return $this->db->fetchAll(
                "SELECT vl.*, a.nome AS aluno_nome, a.ra AS aluno_ra,
                        p.titulo AS prova_titulo, m.nome AS materia_nome
                 FROM provas_validacoes_log vl
                 INNER JOIN alunos a ON a.id = vl.aluno_id
                 INNER JOIN provas p ON p.id = vl.prova_id
                 LEFT JOIN materias m ON p.materia_id = m.id
                 WHERE vl.bloco_id = :bloco_id
                 ORDER BY vl.created_at DESC, vl.id DESC",
                ['bloco_id' => (int) $blocoId]
            ) ?: [];
        } catch (Exception $e) {
            error_log('getHistoricoValidacoesBloco: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Libera nova tentativa: remove realização cancelada e respostas para o aluno poder refazer a prova.
     * Uso: coordenador/professor/admin.
     */
    public function liberarNovaTentativa($provaId, $alunoId)
    {
        $realizacao = $this->getRealizacao($provaId, $alunoId);
        if (!$realizacao || $realizacao['status'] !== 'cancelada') {
            return false;
        }
        $this->db->query(
            "DELETE FROM provas_respostas WHERE prova_id = :prova_id AND aluno_id = :aluno_id",
            ['prova_id' => $provaId, 'aluno_id' => $alunoId]
        );
        $this->db->query(
            "DELETE FROM provas_realizacoes WHERE prova_id = :prova_id AND aluno_id = :aluno_id",
            ['prova_id' => $provaId, 'aluno_id' => $alunoId]
        );
        return true;
    }

    /**
     * Libera nova tentativa para todas as realizações canceladas das provas do bloco.
     * Retorna a quantidade de tentativas liberadas.
     */
    public function liberarNovaTentativaBloco($blocoId)
    {
        $blocoId = (int) $blocoId;
        if ($blocoId <= 0) {
            return 0;
        }

        $rows = $this->db->fetchAll(
            "SELECT pr.prova_id, pr.aluno_id
             FROM provas_realizacoes pr
             INNER JOIN provas_blocos_vinculo pbp ON pbp.prova_id = pr.prova_id AND pbp.bloco_id = :bloco_id
             INNER JOIN provas p ON p.id = pr.prova_id AND p.deleted_at IS NULL
             WHERE pr.status = 'cancelada'",
            ['bloco_id' => $blocoId]
        );
        if (empty($rows)) {
            return 0;
        }

        $this->db->beginTransaction();
        try {
            $liberadas = 0;
            foreach ($rows as $row) {
                if ($this->liberarNovaTentativa((int) $row['prova_id'], (int) $row['aluno_id'])) {
                    $liberadas++;
                }
            }
            $this->db->commit();
            return $liberadas;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log('liberarNovaTentativaBloco: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Cria nova prova
     */
    public function create($data)
    {
        $this->db->beginTransaction();
        
        try {
            // Insere prova
            $sql = "INSERT INTO provas (
                        professor_id, materia_id, turma_id, titulo, descricao,
                        data_inicio, data_fim, tempo_limite, valor_total,
                        mostrar_resultado, permite_correcao, liberar_resultado,
                        ativo, liberada, status
                    ) VALUES (
                        :professor_id, :materia_id, :turma_id, :titulo, :descricao,
                        :data_inicio, :data_fim, :tempo_limite, :valor_total,
                        :mostrar_resultado, :permite_correcao, :liberar_resultado,
                        :ativo, :liberada, :status
                    )";
            
            $provaId = $this->db->insert($sql, [
                'professor_id' => $data['professor_id'],
                'materia_id' => $data['materia_id'],
                'turma_id' => $data['turma_id'] ?? null,
                'titulo' => $data['titulo'],
                'descricao' => $data['descricao'] ?? null,
                'data_inicio' => $data['data_inicio'],
                'data_fim' => $data['data_fim'],
                'tempo_limite' => $data['tempo_limite'] ?? null,
                'valor_total' => $data['valor_total'] ?? 100.00,
                'mostrar_resultado' => $data['mostrar_resultado'] ?? 1,
                'permite_correcao' => $data['permite_correcao'] ?? 0,
                'liberar_resultado' => $data['liberar_resultado'] ?? 'imediatamente',
                'ativo' => $data['ativo'] ?? 1,
                'liberada' => $data['liberada'] ?? 0,
                'status' => $data['status'] ?? 'rascunho'
            ]);
            
            // Se houver turma_id direto, também adiciona na tabela provas_turmas para consistência
            if (!empty($data['turma_id'])) {
                // Verifica se já não existe
                $existe = $this->db->fetch(
                    "SELECT id FROM provas_turmas WHERE prova_id = :prova_id AND turma_id = :turma_id",
                    ['prova_id' => $provaId, 'turma_id' => $data['turma_id']]
                );
                if (!$existe) {
                    $this->db->insert(
                        "INSERT INTO provas_turmas (prova_id, turma_id) VALUES (:prova_id, :turma_id)",
                        [
                            'prova_id' => $provaId,
                            'turma_id' => $data['turma_id']
                        ]
                    );
                }
            }
            
            // Se houver turmas adicionais, insere na tabela provas_turmas
            if (!empty($data['turmas']) && is_array($data['turmas'])) {
                foreach ($data['turmas'] as $turmaId) {
                    // Garante que é um número inteiro válido
                    $turmaId = is_array($turmaId) && isset($turmaId['id']) ? (int)$turmaId['id'] : (int)$turmaId;
                    
                    // Valida se o ID é válido
                    if ($turmaId <= 0) {
                        continue;
                    }
                    
                    // Verifica se a turma existe no banco
                    $turmaExiste = $this->db->fetch(
                        "SELECT id FROM turmas WHERE id = :turma_id",
                        ['turma_id' => $turmaId]
                    );
                    
                    if (!$turmaExiste) {
                        continue;
                    }
                    
                    // Verifica se já não existe
                    $existe = $this->db->fetch(
                        "SELECT id FROM provas_turmas WHERE prova_id = :prova_id AND turma_id = :turma_id",
                        ['prova_id' => $provaId, 'turma_id' => $turmaId]
                    );
                    if (!$existe) {
                        $this->db->insert(
                            "INSERT INTO provas_turmas (prova_id, turma_id) VALUES (:prova_id, :turma_id)",
                            [
                                'prova_id' => $provaId,
                                'turma_id' => $turmaId
                            ]
                        );
                    }
                }
            }
            
            $this->db->commit();
            return $provaId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Atualiza prova
     */
    public function update($id, $data)
    {
        $this->db->beginTransaction();
        
        try {
            $sql = "UPDATE provas SET
                        materia_id = :materia_id,
                        turma_id = :turma_id,
                        titulo = :titulo,
                        descricao = :descricao,
                        data_inicio = :data_inicio,
                        data_fim = :data_fim,
                        data_prova = :data_prova,
                        data_limite_envio = :data_limite_envio,
                        tempo_limite = :tempo_limite,
                        valor_total = :valor_total,
                        mostrar_resultado = :mostrar_resultado,
                        permite_correcao = :permite_correcao,
                        liberar_resultado = :liberar_resultado,
                        ativo = :ativo,
                        liberada = :liberada,
                        status = :status
                    WHERE id = :id 
                    AND deleted_at IS NULL";
            
            $this->db->update($sql, [
                'materia_id' => $data['materia_id'],
                'turma_id' => $data['turma_id'] ?? null,
                'titulo' => $data['titulo'],
                'descricao' => $data['descricao'] ?? null,
                'data_inicio' => $data['data_inicio'],
                'data_fim' => $data['data_fim'],
                'data_prova' => $data['data_prova'] ?? null,
                'data_limite_envio' => $data['data_limite_envio'] ?? null,
                'tempo_limite' => $data['tempo_limite'] ?? null,
                'valor_total' => $data['valor_total'] ?? 100.00,
                'mostrar_resultado' => $data['mostrar_resultado'] ?? 1,
                'permite_correcao' => $data['permite_correcao'] ?? 0,
                'liberar_resultado' => $data['liberar_resultado'] ?? 'imediatamente',
                'ativo' => $data['ativo'] ?? 1,
                'liberada' => $data['liberada'] ?? 0,
                'status' => $data['status'] ?? 'rascunho',
                'id' => $id
            ]);
            
            // Se houver turma_id direto, também adiciona na tabela provas_turmas para consistência
            if (!empty($data['turma_id'])) {
                // Verifica se já não existe
                $existe = $this->db->fetch(
                    "SELECT id FROM provas_turmas WHERE prova_id = :prova_id AND turma_id = :turma_id",
                    ['prova_id' => $id, 'turma_id' => $data['turma_id']]
                );
                if (!$existe) {
                    $this->db->insert(
                        "INSERT INTO provas_turmas (prova_id, turma_id) VALUES (:prova_id, :turma_id)",
                        [
                            'prova_id' => $id,
                            'turma_id' => $data['turma_id']
                        ]
                    );
                }
            }
            
            // Remove turmas antigas e adiciona novas (se o array turmas foi passado)
            if (isset($data['turmas']) && is_array($data['turmas'])) {
                // Remove todas as turmas da prova
                $this->db->delete(
                    "DELETE FROM provas_turmas WHERE prova_id = :prova_id",
                    ['prova_id' => $id]
                );
                
                // Adiciona novas turmas
                foreach ($data['turmas'] as $turmaId) {
                    $this->db->insert(
                        "INSERT INTO provas_turmas (prova_id, turma_id) VALUES (:prova_id, :turma_id)",
                        [
                            'prova_id' => $id,
                            'turma_id' => $turmaId
                        ]
                    );
                }
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Normaliza nível de dificuldade para valor aceito pelo banco (evita "Data truncated").
     * Retorna null ou um de: 'facil', 'medio', 'dificil', 'desafio' (compatível com ENUM ou VARCHAR).
     * Pode ser chamado estaticamente de outros controllers.
     */
    public static function normalizarNivelDificuldadeParaDb($valor)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $v = is_string($valor) ? mb_strtolower(trim($valor)) : '';
        $v = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ã', 'ê'], ['a', 'e', 'i', 'o', 'u', 'a', 'e'], $v);
        $map = [
            'facil' => 'facil',
            'medio' => 'medio',
            'dificil' => 'dificil',
            'desafio' => 'desafio',
            'fácil' => 'facil',
            'médio' => 'medio',
            'difícil' => 'dificil',
            'easy' => 'facil',
            'medium' => 'medio',
            'hard' => 'dificil',
            'challenge' => 'desafio',
        ];
        return $map[$v] ?? null;
    }

    private function normalizarNivelDificuldade($valor)
    {
        return self::normalizarNivelDificuldadeParaDb($valor);
    }
    
    /**
     * Adiciona questão à prova
     */
    public function addQuestao($provaId, $data)
    {
        $this->db->beginTransaction();
        
        try {
            $sql = "INSERT INTO provas_questoes (
                        prova_id, enunciado, imagem_url, tipo, valor, nivel_dificuldade, ordem, explicacao
                    ) VALUES (
                        :prova_id, :enunciado, :imagem_url, :tipo, :valor, :nivel_dificuldade, :ordem, :explicacao
                    )";
            
            $nivel = $this->normalizarNivelDificuldade($data['nivel_dificuldade'] ?? null);
            $questaoId = $this->db->insert($sql, [
                'prova_id' => $provaId,
                'enunciado' => $data['enunciado'],
                'imagem_url' => $data['imagem_url'] ?? null,
                'tipo' => $data['tipo'] ?? 'multipla_escolha',
                'valor' => $data['valor'] ?? 1.00,
                'nivel_dificuldade' => $nivel,
                'ordem' => $data['ordem'] ?? 0,
                'explicacao' => $data['explicacao'] ?? null,
            ]);
            
            // Se for múltipla escolha, adiciona alternativas
            if (($data['tipo'] ?? 'multipla_escolha') === 'multipla_escolha' && !empty($data['alternativas'])) {
                foreach ($data['alternativas'] as $index => $alternativa) {
                    if (empty($alternativa['texto'])) {
                        continue; // Pula alternativas vazias
                    }
                    
                    $this->db->insert(
                        "INSERT INTO provas_alternativas (questao_id, texto, correta, ordem) 
                         VALUES (:questao_id, :texto, :correta, :ordem)",
                        [
                            'questao_id' => $questaoId,
                            'texto' => $alternativa['texto'],
                            'correta' => $alternativa['correta'] ?? 0,
                            'ordem' => $alternativa['ordem'] ?? $index
                        ]
                    );
                }
            }
            
            $this->db->commit();
            return $questaoId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Atualiza questão
     */
    public function updateQuestao($questaoId, $data)
    {
        $this->db->beginTransaction();
        
        try {
            // Atualiza questão
            $sql = "UPDATE provas_questoes SET
                        enunciado = :enunciado,
                        imagem_url = :imagem_url,
                        tipo = :tipo,
                        valor = :valor,
                        nivel_dificuldade = :nivel_dificuldade,
                        ordem = :ordem
                    WHERE id = :id";
            
            $nivel = $this->normalizarNivelDificuldade($data['nivel_dificuldade'] ?? null);
            $this->db->update($sql, [
                'enunciado' => $data['enunciado'],
                'imagem_url' => $data['imagem_url'] ?? null,
                'tipo' => $data['tipo'] ?? 'multipla_escolha',
                'valor' => $data['valor'] ?? 1.00,
                'nivel_dificuldade' => $nivel,
                'ordem' => $data['ordem'] ?? 0,
                'id' => $questaoId
            ]);
            
            // Se for múltipla escolha, atualiza alternativas
            if (($data['tipo'] ?? 'multipla_escolha') === 'multipla_escolha' && isset($data['alternativas'])) {
                // Remove alternativas antigas
                $this->db->delete(
                    "DELETE FROM provas_alternativas WHERE questao_id = :questao_id",
                    ['questao_id' => $questaoId]
                );
                
                // Adiciona novas alternativas
                foreach ($data['alternativas'] as $index => $alternativa) {
                    $this->db->insert(
                        "INSERT INTO provas_alternativas (questao_id, texto, correta, ordem) 
                         VALUES (:questao_id, :texto, :correta, :ordem)",
                        [
                            'questao_id' => $questaoId,
                            'texto' => $alternativa['texto'],
                            'correta' => $alternativa['correta'] ?? 0,
                            'ordem' => $alternativa['ordem'] ?? $index
                        ]
                    );
                }
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Remove questão
     */
    public function deleteQuestao($questaoId)
    {
        // As alternativas são removidas automaticamente por CASCADE
        return $this->db->delete(
            "DELETE FROM provas_questoes WHERE id = :id",
            ['id' => $questaoId]
        );
    }
    
    /**
     * Inicia realização da prova pelo aluno
     */
    public function iniciarRealizacao($provaId, $alunoId, $ordemQuestoes = null)
    {
        // Verifica se já existe realização
        $existente = $this->getRealizacao($provaId, $alunoId);
        if ($existente) {
            if (($existente['status'] ?? '') === 'cancelada') {
                throw new Exception('Prova cancelada; aguarde liberação do coordenador');
            }
            return $existente;
        }
        
        $ordemJson = $ordemQuestoes ? json_encode($ordemQuestoes) : null;
        
        $sql = "INSERT INTO provas_realizacoes (
                    prova_id, aluno_id, iniciado_em, status, ordem_questoes
                ) VALUES (
                    :prova_id, :aluno_id, NOW(), 'iniciado', :ordem_questoes
                )";
        
        return $this->db->insert($sql, [
            'prova_id' => $provaId,
            'aluno_id' => $alunoId,
            'ordem_questoes' => $ordemJson
        ]);
    }
    
    /**
     * Salva resposta do aluno e registra no log de auditoria.
     * @param string|null $ip IP do usuário (para log)
     * @param string|null $userAgent User-Agent (para log)
     */
    public function salvarResposta($provaId, $alunoId, $questaoId, $alternativaId = null, $respostaTexto = null, $ip = null, $userAgent = null)
    {
        $realizacao = $this->getRealizacao($provaId, $alunoId);
        if (!$realizacao) {
            throw new Exception('Realização não encontrada');
        }
        if (($realizacao['status'] ?? '') === 'cancelada') {
            throw new Exception('Prova cancelada; aguarde liberação do coordenador');
        }
        if (($realizacao['status'] ?? '') === 'finalizado') {
            throw new Exception('Prova já foi finalizada');
        }

        // Verifica se já existe resposta
        $existente = $this->db->fetch(
            "SELECT * FROM provas_respostas 
             WHERE prova_id = :prova_id AND aluno_id = :aluno_id AND questao_id = :questao_id",
            [
                'prova_id' => $provaId,
                'aluno_id' => $alunoId,
                'questao_id' => $questaoId
            ]
        );
        
        // Busca questão para determinar tipo e valor
        $questao = $this->getQuestaoById($questaoId);
        if (!$questao) {
            throw new Exception('Questão não encontrada');
        }
        
        // Para múltipla escolha, verifica se está correta
        $correta = null;
        $pontuacao = 0.00;
        
        if ((int)($questao['invalidada'] ?? 0) === 1) {
            // Questão invalidada: aluno ganha o ponto integral independentemente da resposta.
            $correta = 1;
            $pontuacao = (float) ($questao['valor'] ?? 0);
        } elseif ($questao['tipo'] === 'multipla_escolha' && $alternativaId) {
            $alternativa = $this->db->fetch(
                "SELECT * FROM provas_alternativas WHERE id = :id",
                ['id' => $alternativaId]
            );
            
            if ($alternativa) {
                $correta = $alternativa['correta'] ? 1 : 0;
                $pontuacao = $correta ? $questao['valor'] : 0.00;
            }
        }
        
        $tipoAcao = $existente ? 'alterou' : 'marcou';
        $ip = $ip !== null ? substr((string)$ip, 0, 45) : '';
        $userAgent = $userAgent !== null ? substr((string)$userAgent, 0, 500) : null;

        if ($existente) {
            // Atualiza resposta existente
            $sql = "UPDATE provas_respostas SET
                        alternativa_id = :alternativa_id,
                        resposta_texto = :resposta_texto,
                        correta = :correta,
                        pontuacao = :pontuacao
                    WHERE id = :id";
            $this->db->update($sql, [
                'alternativa_id' => $alternativaId,
                'resposta_texto' => $respostaTexto,
                'correta' => $correta,
                'pontuacao' => $pontuacao,
                'id' => $existente['id']
            ]);
        } else {
            // Insere nova resposta
            $sql = "INSERT INTO provas_respostas (
                        prova_id, aluno_id, questao_id, alternativa_id, 
                        resposta_texto, correta, pontuacao
                    ) VALUES (
                        :prova_id, :aluno_id, :questao_id, :alternativa_id,
                        :resposta_texto, :correta, :pontuacao
                    )";
            $this->db->insert($sql, [
                'prova_id' => $provaId,
                'aluno_id' => $alunoId,
                'questao_id' => $questaoId,
                'alternativa_id' => $alternativaId,
                'resposta_texto' => $respostaTexto,
                'correta' => $correta,
                'pontuacao' => $pontuacao
            ]);
        }

        // Log de auditoria
        $this->db->insert(
            "INSERT INTO provas_respostas_log (prova_id, aluno_id, questao_id, alternativa_id, resposta_texto, tipo_acao, ip, user_agent) 
             VALUES (:prova_id, :aluno_id, :questao_id, :alternativa_id, :resposta_texto, :tipo_acao, :ip, :user_agent)",
            [
                'prova_id' => $provaId,
                'aluno_id' => $alunoId,
                'questao_id' => $questaoId,
                'alternativa_id' => $alternativaId,
                'resposta_texto' => $respostaTexto,
                'tipo_acao' => $tipoAcao,
                'ip' => $ip,
                'user_agent' => $userAgent
            ]
        );

        return true;
    }
    
    /**
     * Finaliza prova e calcula nota
     */
    public function finalizarProva($provaId, $alunoId)
    {
        $this->db->beginTransaction();
        
        try {
            // Busca realização
            $realizacao = $this->getRealizacao($provaId, $alunoId);
            if (!$realizacao) {
                throw new Exception('Realização não encontrada');
            }
            
            if ($realizacao['status'] === 'finalizado') {
                throw new Exception('Prova já foi finalizada');
            }
            if ($realizacao['status'] === 'cancelada') {
                throw new Exception('Prova foi cancelada; não é possível finalizar');
            }
            
            // Calcula nota total considerando questões invalidadas (valem para todos os alunos).
            $notaTotal = $this->calcularNotaComQuestoesInvalidas($provaId, $alunoId);
            
            // Busca prova para pegar valor_total
            $prova = $this->findById($provaId);
            $valorTotal = floatval(is_array($prova) ? ($prova['valor_total'] ?? 100.00) : 100.00);
            
            // Calcula tempo gasto (coluna tempo_gasto = minutos inteiros; ver provas_realizacoes)
            $tempoGasto = 0;
            $iniciadoEmRaw = $realizacao['iniciado_em'] ?? null;
            if ($iniciadoEmRaw) {
                try {
                    $iniciadoEm = new DateTime($iniciadoEmRaw);
                    $agora = new DateTime();
                    $segundos = max(0, $agora->getTimestamp() - $iniciadoEm->getTimestamp());
                    $tempoGasto = (int) round($segundos / 60);
                } catch (Exception $e) {
                    $tempoGasto = 0;
                }
            }
            
            // Atualiza realização
            $sql = "UPDATE provas_realizacoes SET
                        finalizado_em = NOW(),
                        tempo_gasto = :tempo_gasto,
                        nota = :nota,
                        status = 'finalizado'
                    WHERE id = :id";
            
            $this->db->update($sql, [
                'tempo_gasto' => $tempoGasto,
                'nota' => $notaTotal,
                'id' => $realizacao['id']
            ]);
            
            $this->db->commit();
            return [
                'nota' => $notaTotal,
                'valor_total' => $valorTotal,
                'tempo_gasto' => $tempoGasto
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Corrige questão dissertativa manualmente
     */
    public function corrigirQuestao($provaId, $alunoId, $questaoId, $correta, $pontuacao)
    {
        $sql = "UPDATE provas_respostas SET
                    correta = :correta,
                    pontuacao = :pontuacao
                WHERE prova_id = :prova_id 
                AND aluno_id = :aluno_id 
                AND questao_id = :questao_id";
        
        $this->db->update($sql, [
            'correta' => $correta ? 1 : 0,
            'pontuacao' => $pontuacao,
            'prova_id' => $provaId,
            'aluno_id' => $alunoId,
            'questao_id' => $questaoId
        ]);
        
        // Recalcula nota da prova
        $this->recalcularNota($provaId, $alunoId);
    }
    
    /**
     * Recalcula nota da prova após correção manual
     */
    private function recalcularNota($provaId, $alunoId)
    {
        $notaTotal = $this->calcularNotaComQuestoesInvalidas($provaId, $alunoId);
        
        $realizacao = $this->getRealizacao($provaId, $alunoId);
        if ($realizacao) {
            $this->db->update(
                "UPDATE provas_realizacoes SET nota = :nota WHERE id = :id",
                [
                    'nota' => $notaTotal,
                    'id' => $realizacao['id']
                ]
            );
        }
    }

    /**
     * Calcula nota final da prova considerando questão invalidada como ponto para todos.
     */
    private function calcularNotaComQuestoesInvalidas($provaId, $alunoId): float
    {
        if (!$this->hasColunaInvalidadaEmQuestoes()) {
            $rowLegado = $this->db->fetch(
                "SELECT COALESCE(SUM(COALESCE(r.pontuacao, 0)), 0) AS nota
                 FROM provas_respostas r
                 WHERE r.prova_id = ?
                   AND r.aluno_id = ?",
                [$provaId, $alunoId]
            );
            return (float) ($rowLegado['nota'] ?? 0);
        }

        // Placeholders posicionais (?) evitam HY093 com :prova_id repetido no PDO MySQL.
        $row = $this->db->fetch(
            "SELECT COALESCE(SUM(
                        CASE
                            WHEN COALESCE(q.invalidada, 0) = 1 THEN COALESCE(q.valor, 0)
                            ELSE COALESCE(r.pontuacao, 0)
                        END
                    ), 0) AS nota
             FROM provas_questoes q
             LEFT JOIN provas_respostas r
                    ON r.questao_id = q.id
                   AND r.prova_id = ?
                   AND r.aluno_id = ?
             WHERE q.prova_id = ?",
            [$provaId, $alunoId, $provaId]
        );

        return (float) ($row['nota'] ?? 0);
    }

    private function hasColunaInvalidadaEmQuestoes(): bool
    {
        if ($this->hasInvalidadaColumnCache !== null) {
            return (bool) $this->hasInvalidadaColumnCache;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'provas_questoes'
                   AND COLUMN_NAME = 'invalidada'
                 LIMIT 1"
            );
            $this->hasInvalidadaColumnCache = !empty($row);
        } catch (Exception $e) {
            $this->hasInvalidadaColumnCache = false;
        }

        return (bool) $this->hasInvalidadaColumnCache;
    }

    /**
     * Invalida/revalida questão e recalcula notas de realizações finalizadas da prova.
     */
    public function definirInvalidacaoQuestao(int $questaoId, bool $invalidar, ?string $observacao, int $usuarioId): void
    {
        if (!$this->hasColunaInvalidadaEmQuestoes()) {
            throw new Exception('Banco sem suporte a invalidação de questão. Execute a migração 2026_05_25_provas_questoes_invalidacao.sql.');
        }
        $questao = $this->getQuestaoById($questaoId);
        if (!$questao) {
            throw new Exception('Questão não encontrada.');
        }

        $obs = trim((string) ($observacao ?? ''));
        if ($obs === '') {
            $obs = null;
        } else {
            $obs = mb_substr($obs, 0, 1000);
        }

        $this->db->beginTransaction();
        try {
            if ($invalidar) {
                $this->db->query(
                    "UPDATE provas_questoes
                     SET invalidada = 1,
                         observacao_invalidacao = :obs,
                         invalidada_por = :uid,
                         invalidada_em = NOW()
                     WHERE id = :id",
                    ['obs' => $obs, 'uid' => $usuarioId, 'id' => $questaoId]
                );
            } else {
                $this->db->query(
                    "UPDATE provas_questoes
                     SET invalidada = 0,
                         observacao_invalidacao = :obs,
                         invalidada_por = :uid,
                         invalidada_em = CASE WHEN :obs IS NULL THEN NULL ELSE invalidada_em END
                     WHERE id = :id",
                    ['obs' => $obs, 'uid' => $usuarioId, 'id' => $questaoId]
                );
            }

            // Ajusta pontuação das respostas já marcadas para manter consistência do histórico.
            if ($invalidar) {
                $this->db->query(
                    "UPDATE provas_respostas
                     SET correta = 1,
                         pontuacao = :valor
                     WHERE questao_id = :questao_id",
                    [
                        'questao_id' => $questaoId,
                        'valor' => (float) ($questao['valor'] ?? 0),
                    ]
                );
            }

            // Recalcula nota de quem já finalizou a prova.
            $this->recalcularNotasFinalizadasDaProva((int) ($questao['prova_id'] ?? 0));
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function recalcularNotasFinalizadasDaProva(int $provaId): void
    {
        if ($provaId <= 0) {
            return;
        }
        $realizacoes = $this->db->fetchAll(
            "SELECT aluno_id
             FROM provas_realizacoes
             WHERE prova_id = :prova_id
               AND status = 'finalizado'",
            ['prova_id' => $provaId]
        );
        foreach ($realizacoes as $r) {
            $alunoId = (int) ($r['aluno_id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            $nota = $this->calcularNotaComQuestoesInvalidas($provaId, $alunoId);
            $this->db->query(
                "UPDATE provas_realizacoes
                 SET nota = :nota
                 WHERE prova_id = :prova_id
                   AND aluno_id = :aluno_id",
                [
                    'nota' => $nota,
                    'prova_id' => $provaId,
                    'aluno_id' => $alunoId,
                ]
            );
        }
    }
    
    /**
     * Busca turmas de uma prova
     */
    public function getTurmas($provaId)
    {
        return $this->db->fetchAll(
            "SELECT t.* FROM provas_turmas pt
             LEFT JOIN turmas t ON pt.turma_id = t.id
             WHERE pt.prova_id = :prova_id",
            ['prova_id' => $provaId]
        );
    }
    
    /**
     * Toggle liberação da prova
     */
    public function toggleLiberada($id)
    {
        $prova = $this->findById($id);
        if (!$prova) {
            return false;
        }
        
        $novaLiberada = $prova['liberada'] ? 0 : 1;
        
        return $this->db->update(
            "UPDATE provas SET liberada = :liberada WHERE id = :id",
            [
                'liberada' => $novaLiberada,
                'id' => $id
            ]
        );
    }
    
    /**
     * Soft delete - marca como excluído
     */
    public function delete($id)
    {
        return $this->db->update(
            "UPDATE provas SET deleted_at = NOW() WHERE id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Restaura prova excluída
     */
    public function restore($id)
    {
        return $this->db->update(
            "UPDATE provas SET deleted_at = NULL WHERE id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Verifica se prova existe
     */
    public function exists($id)
    {
        $result = $this->db->fetch(
            "SELECT id FROM provas WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
        return $result !== false;
    }
    
    /**
     * Verifica se professor pode editar a prova
     */
    public function canEdit($id, $professorId)
    {
        $prova = $this->findById($id);
        if (!$prova) {
            return false;
        }
        
        return $prova['professor_id'] == $professorId;
    }
    
    /**
     * Conta total de provas
     */
    public function count()
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total FROM provas WHERE deleted_at IS NULL"
        );
        return $result['total'];
    }
    
    /**
     * Busca alunos que realizaram a prova
     */
    public function getAlunosRealizacao($provaId)
    {
        return $this->db->fetchAll(
            "SELECT pr.*, 
                    a.nome as aluno_nome,
                    a.ra as aluno_ra,
                    t.nome as turma_nome,
                    COALESCE((
                        SELECT COUNT(*) 
                        FROM provas_respostas r1
                        WHERE r1.prova_id = pr.prova_id
                        AND r1.aluno_id = pr.aluno_id
                        AND r1.correta = 1
                    ), 0) as acertos,
                    COALESCE((
                        SELECT COUNT(*)
                        FROM provas_respostas r2
                        WHERE r2.prova_id = pr.prova_id
                        AND r2.aluno_id = pr.aluno_id
                        AND r2.correta = 0
                    ), 0) as erros
             FROM provas_realizacoes pr
             LEFT JOIN alunos a ON pr.aluno_id = a.id
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE pr.prova_id = :prova_id
             ORDER BY pr.finalizado_em DESC, pr.iniciado_em DESC",
            ['prova_id' => $provaId]
        );
    }
    
    /**
     * Verifica se todos os alunos finalizaram a prova
     */
    public function todosFinalizaram($provaId)
    {
        // Busca todas as turmas da prova
        $turmas = $this->getTurmas($provaId);
        $prova = $this->findById($provaId);
        
        if ($prova['turma_id']) {
            $turmas[] = ['id' => $prova['turma_id']];
        }
        
        if (empty($turmas)) {
            return false;
        }
        
        $turmaIds = array_values(array_map('intval', array_column($turmas, 'id')));
        if (empty($turmaIds)) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
        
        // Conta total de alunos nas turmas
        $totalAlunos = $this->db->fetch(
            "SELECT COUNT(*) as total FROM alunos 
             WHERE turma_id IN ($placeholders) AND ativo = 1",
            $turmaIds
        );
        
        // Conta alunos que finalizaram (só placeholders ? — evita misturar :nome com ? no PDO)
        $finalizaram = $this->db->fetch(
            "SELECT COUNT(DISTINCT pr.aluno_id) as total 
             FROM provas_realizacoes pr
             LEFT JOIN alunos a ON pr.aluno_id = a.id
             WHERE pr.prova_id = ?
             AND pr.status = 'finalizado'
             AND a.turma_id IN ($placeholders)",
            array_merge([$provaId], $turmaIds)
        );
        
        return $totalAlunos['total'] == $finalizaram['total'];
    }
}
