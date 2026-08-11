<?php
/**
 * EducaTudo - Essay Proposal Model (Proposta de redação)
 * Redação Configurável - professor
 */

class EssayProposal
{
    private $db;

    /** @var bool|null cache por instância/request — coluna vem da migration 2026_07_14_redacoes_orientadas_propostas_ativo */
    private ?bool $temColunaAtivo = null;

    public const SUBMISSION_MODES = ['texto', 'foto', 'texto_ou_foto'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function normalizeSubmissionMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));
        return in_array($mode, self::SUBMISSION_MODES, true) ? $mode : 'texto';
    }

    private function ensureSubmissionModeColumn(): void
    {
        // Legado: coluna submission_mode deve existir via migration tenant.
        // Não alterar schema em runtime.
    }

    public function temColunaAtivoProposta(): bool
    {
        if ($this->temColunaAtivo !== null) {
            return $this->temColunaAtivo;
        }

        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'redacoes_orientadas_propostas'
                   AND COLUMN_NAME = 'ativo'
                 LIMIT 1"
            );
            $this->temColunaAtivo = (bool) $row;
        } catch (Throwable $e) {
            $this->temColunaAtivo = false;
        }

        return $this->temColunaAtivo;
    }

    public function sqlFiltroPropostaAtiva(string $alias = 'p'): string
    {
        return $this->temColunaAtivoProposta() ? " AND {$alias}.ativo = 1" : '';
    }

    public function propostaEstaAtiva(?array $proposal): bool
    {
        if (!$proposal) {
            return false;
        }
        if (!$this->temColunaAtivoProposta()) {
            return true;
        }

        return (int) ($proposal['ativo'] ?? 0) === 1;
    }

    /**
     * Propostas publicadas pendentes para o aluno (dashboard).
     */
    public function countPendingForStudent(int $studentId): int
    {
        if ($studentId <= 0) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $filtroAtivo = $this->sqlFiltroPropostaAtiva('p');
        try {
            $row = $this->db->fetch(
                "SELECT COUNT(DISTINCT p.id) AS total
                 FROM redacoes_orientadas_propostas p
                 LEFT JOIN redacoes_orientadas_entregas s
                        ON s.id = (
                            SELECT s2.id
                            FROM redacoes_orientadas_entregas s2
                            WHERE s2.proposal_id = p.id AND s2.student_id = :student_id_sub
                            ORDER BY s2.id DESC
                            LIMIT 1
                        )
                 LEFT JOIN redacoes_orientadas_correcoes c ON c.submission_id = s.id
                 WHERE p.status = 'published'
                   {$filtroAtivo}
                   AND (p.starts_at IS NULL OR p.starts_at <= :now)
                   AND (p.ends_at IS NULL OR p.ends_at >= :now)
                   AND (
                       s.id IS NULL
                       OR (s.status NOT IN ('submitted', 'corrected') AND c.id IS NULL)
                   )
                   AND (
                       EXISTS (
                           SELECT 1 FROM redacoes_orientadas_propostas_alunos ps
                           WHERE ps.proposal_id = p.id AND ps.student_id = :student_id
                       )
                       OR (
                           NOT EXISTS (SELECT 1 FROM redacoes_orientadas_propostas_alunos ps2 WHERE ps2.proposal_id = p.id)
                           AND (
                               EXISTS (
                                   SELECT 1 FROM redacoes_orientadas_propostas_turmas pt
                                   JOIN alunos a ON a.turma_id = pt.turma_id
                                   WHERE pt.proposal_id = p.id AND a.id = :student_id2
                               )
                               OR EXISTS (
                                   SELECT 1 FROM redacoes_orientadas_propostas_turmas pt2
                                   JOIN matricula m ON m.turma_id = pt2.turma_id AND m.status = 'ativa'
                                   WHERE pt2.proposal_id = p.id AND m.aluno_id = :student_id3
                               )
                           )
                       )
                   )",
                [
                    'student_id_sub' => $studentId,
                    'student_id' => $studentId,
                    'student_id2' => $studentId,
                    'student_id3' => $studentId,
                    'now' => $now,
                ]
            );
            return (int) ($row['total'] ?? 0);
        } catch (Throwable $e) {
            error_log('EssayProposal::countPendingForStudent: ' . $e->getMessage());
            return 0;
        }
    }

    public function findByTeacher($teacherId, $status = null)
    {
        $sql = "SELECT p.*, b.name as board_name, t.name as text_type_name
                FROM redacoes_orientadas_propostas p
                JOIN redacoes_orientadas_quadros b ON p.board_id = b.id
                JOIN redacoes_orientadas_tipos_texto t ON p.text_type_id = t.id
                WHERE p.teacher_id = :teacher_id" . $this->sqlFiltroPropostaAtiva('p');
        $params = ['teacher_id' => (int) $teacherId];
        if ($status !== null) {
            $sql .= " AND p.status = :status";
            $params['status'] = $status;
        }
        $sql .= " ORDER BY p.id ASC";
        return $this->sortByTitleNumeric($this->db->fetchAll($sql, $params));
    }

    public function findByTeacherOrShared($teacherId, $status = null, bool $includeInactive = false)
    {
        $this->ensureProfessorAccessTable();
        $sql = "SELECT DISTINCT p.*, b.name as board_name, t.name as text_type_name
                FROM redacoes_orientadas_propostas p
                JOIN redacoes_orientadas_quadros b ON p.board_id = b.id
                JOIN redacoes_orientadas_tipos_texto t ON p.text_type_id = t.id
                LEFT JOIN redacoes_orientadas_propostas_professores pp ON pp.proposal_id = p.id
                WHERE (p.teacher_id = :teacher_id OR pp.professor_id = :teacher_id2)"
                . ($includeInactive ? "" : $this->sqlFiltroPropostaAtiva('p'));
        $params = ['teacher_id' => (int) $teacherId, 'teacher_id2' => (int) $teacherId];
        if ($status !== null) {
            $sql .= " AND p.status = :status";
            $params['status'] = $status;
        }
        $sql .= " ORDER BY p.id ASC";
        return $this->sortByTitleNumeric($this->db->fetchAll($sql, $params));
    }

    /**
     * Ordena propostas pelo número no início do título (ex.: "01.", "06 -", "13.").
     * Títulos sem número ficam ao final, em ordem alfabética.
     */
    public function sortProposalsByTitleNumeric(array $rows): array
    {
        return $this->sortByTitleNumeric($rows);
    }

    private function sortByTitleNumeric(array $rows): array
    {
        usort($rows, function ($a, $b) {
            $na = $this->extractTitleOrderNumber((string) ($a['title'] ?? ''));
            $nb = $this->extractTitleOrderNumber((string) ($b['title'] ?? ''));
            if ($na !== null && $nb !== null && $na !== $nb) {
                return $na <=> $nb;
            }
            if ($na !== null && $nb === null) {
                return -1;
            }
            if ($na === null && $nb !== null) {
                return 1;
            }
            $cmp = strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });
        return $rows;
    }

    private function extractTitleOrderNumber(string $title): ?int
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }
        if (preg_match('/^(\d+)/', $title, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT p.*, b.name as board_name, b.slug as board_slug, t.name as text_type_name, t.slug as text_type_slug,
                    prof.nome as teacher_name
             FROM redacoes_orientadas_propostas p
             JOIN redacoes_orientadas_quadros b ON p.board_id = b.id
             JOIN redacoes_orientadas_tipos_texto t ON p.text_type_id = t.id
             JOIN professores prof ON p.teacher_id = prof.id
             WHERE p.id = :id",
            ['id' => (int) $id]
        );
    }

    /**
     * Turmas linked to this proposal (visibility).
     */
    public function getTurmas($proposalId)
    {
        return $this->db->fetchAll(
            "SELECT pt.turma_id, t.nome as turma_nome FROM redacoes_orientadas_propostas_turmas pt JOIN turmas t ON t.id = pt.turma_id WHERE pt.proposal_id = :id ORDER BY t.nome",
            ['id' => (int) $proposalId]
        );
    }

    /**
     * Student IDs linked to this proposal (optional filter). Empty = all students from turmas.
     */
    public function getStudentIds($proposalId)
    {
        $rows = $this->db->fetchAll("SELECT student_id FROM redacoes_orientadas_propostas_alunos WHERE proposal_id = :id", ['id' => (int) $proposalId]);
        return array_map(function ($r) { return (int) $r['student_id']; }, $rows);
    }

    /**
     * Lista de alunos para os quais a proposta foi disponibilizada (id, nome), para exibir na view.
     */
    public function getStudentsWithAccess($proposalId)
    {
        $studentIds = $this->getStudentIds($proposalId);
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            return $this->db->fetchAll(
                "SELECT id, nome FROM alunos WHERE id IN ($placeholders) ORDER BY nome",
                $studentIds
            );
        }
        $turmas = $this->getTurmas($proposalId);
        if (empty($turmas)) {
            return [];
        }
        $turmaIds = array_column($turmas, 'turma_id');
        $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
        try {
            return $this->db->fetchAll(
                "SELECT DISTINCT a.id, a.nome
                 FROM alunos a
                 WHERE a.ativo = 1
                   AND (
                        a.turma_id IN ($placeholders)
                        OR EXISTS (
                            SELECT 1 FROM matricula m
                            WHERE m.aluno_id = a.id
                              AND m.status = 'ativa'
                              AND m.turma_id IN ($placeholders)
                        )
                   )
                 ORDER BY a.nome",
                array_merge($turmaIds, $turmaIds)
            );
        } catch (Exception $e) {
            // Fallback para bases que ainda não possuem tabela matricula.
            return $this->db->fetchAll(
                "SELECT DISTINCT a.id, a.nome
                 FROM alunos a
                 WHERE a.ativo = 1 AND a.turma_id IN ($placeholders)
                 ORDER BY a.nome",
                $turmaIds
            );
        }
    }

    private function ensureProfessorAccessTable()
    {
        try {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS redacoes_orientadas_propostas_professores (
                    proposal_id INT NOT NULL,
                    professor_id INT NOT NULL,
                    granted_by INT DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (proposal_id, professor_id),
                    KEY idx_professor_id (professor_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (\Throwable $e) {
            // Ambiente sem permissão de DDL: segue com fallback nas operações DML.
        }

        // Compatibilidade com bases antigas: garante colunas novas mesmo se a tabela já existir.
        try {
            $grantedByCol = $this->db->fetch(
                "SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'redacoes_orientadas_propostas_professores'
                   AND COLUMN_NAME = 'granted_by'
                 LIMIT 1"
            );
            if (!$grantedByCol) {
                $this->db->query(
                    "ALTER TABLE redacoes_orientadas_propostas_professores
                     ADD COLUMN granted_by INT DEFAULT NULL AFTER professor_id"
                );
            }
        } catch (\Throwable $e) {
            // Não interrompe o fluxo por falha de compatibilidade.
        }

        try {
            $createdAtCol = $this->db->fetch(
                "SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'redacoes_orientadas_propostas_professores'
                   AND COLUMN_NAME = 'created_at'
                 LIMIT 1"
            );
            if (!$createdAtCol) {
                $this->db->query(
                    "ALTER TABLE redacoes_orientadas_propostas_professores
                     ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER granted_by"
                );
            }
        } catch (\Throwable $e) {
            // Não interrompe o fluxo por falha de compatibilidade.
        }
    }

    public function hasProfessorAccess($proposalId, $professorId)
    {
        $this->ensureProfessorAccessTable();
        $row = $this->db->fetch(
            "SELECT 1 FROM redacoes_orientadas_propostas_professores
             WHERE proposal_id = :proposal_id AND professor_id = :professor_id
             LIMIT 1",
            ['proposal_id' => (int) $proposalId, 'professor_id' => (int) $professorId]
        );
        return (bool) $row;
    }

    public function grantProfessorAccess($proposalId, $professorId, $grantedBy = null)
    {
        $this->ensureProfessorAccessTable();
        $params = [
            'proposal_id' => (int) $proposalId,
            'professor_id' => (int) $professorId,
            'granted_by' => $grantedBy !== null ? (int) $grantedBy : null,
        ];
        try {
            $this->db->insert(
                "INSERT IGNORE INTO redacoes_orientadas_propostas_professores
                 (proposal_id, professor_id, granted_by)
                 VALUES (:proposal_id, :professor_id, :granted_by)",
                $params
            );
            return;
        } catch (\Throwable $e) {
            // Fallback para bases legadas sem coluna granted_by.
        }

        $this->db->insert(
            "INSERT IGNORE INTO redacoes_orientadas_propostas_professores
             (proposal_id, professor_id)
             VALUES (:proposal_id, :professor_id)",
            [
                'proposal_id' => (int) $proposalId,
                'professor_id' => (int) $professorId,
            ]
        );
    }

    public function revokeProfessorAccess($proposalId, $professorId)
    {
        $this->ensureProfessorAccessTable();
        $this->db->delete(
            "DELETE FROM redacoes_orientadas_propostas_professores
             WHERE proposal_id = :proposal_id AND professor_id = :professor_id",
            ['proposal_id' => (int) $proposalId, 'professor_id' => (int) $professorId]
        );
    }

    public function getAllowedProfessors($proposalId)
    {
        $this->ensureProfessorAccessTable();
        return $this->db->fetchAll(
            "SELECT p.id, p.nome, p.email, pp.granted_by, pp.created_at
             FROM redacoes_orientadas_propostas_professores pp
             JOIN professores p ON p.id = pp.professor_id
             WHERE pp.proposal_id = :proposal_id
             ORDER BY p.nome",
            ['proposal_id' => (int) $proposalId]
        );
    }

    public function setTurmas($proposalId, array $turmaIds)
    {
        $this->db->delete("DELETE FROM redacoes_orientadas_propostas_turmas WHERE proposal_id = :id", ['id' => (int) $proposalId]);
        foreach ($turmaIds as $tid) {
            if ((int) $tid <= 0) continue;
            $this->db->insert(
                "INSERT INTO redacoes_orientadas_propostas_turmas (proposal_id, turma_id) VALUES (:proposal_id, :turma_id)",
                ['proposal_id' => (int) $proposalId, 'turma_id' => (int) $tid]
            );
        }
    }

    public function setStudents($proposalId, array $studentIds)
    {
        $this->db->delete("DELETE FROM redacoes_orientadas_propostas_alunos WHERE proposal_id = :id", ['id' => (int) $proposalId]);
        foreach ($studentIds as $sid) {
            if ((int) $sid <= 0) continue;
            $this->db->insert(
                "INSERT INTO redacoes_orientadas_propostas_alunos (proposal_id, student_id) VALUES (:proposal_id, :student_id)",
                ['proposal_id' => (int) $proposalId, 'student_id' => (int) $sid]
            );
        }
    }

    /**
     * Published proposals visible to this student: status=published.
     * Visibility rules:
     * - If specific students are selected: student must be in the list (turma check is optional)
     * - If no specific students: student's turma (principal ou via matrícula ativa) must be in the proposal's turmas list
     */
    public function findPublishedForStudent($studentId)
    {
        $filtroAtivo = $this->sqlFiltroPropostaAtiva('p');
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT p.*, b.name as board_name, t.name as text_type_name, prof.nome as teacher_name,
                    s.id as submission_id, s.status as submission_status, s.submitted_at, s.updated_at as submission_updated_at,
                    c.id as correction_id, c.total_score as correction_total_score, c.updated_at as correction_updated_at
             FROM redacoes_orientadas_propostas p
             JOIN redacoes_orientadas_quadros b ON p.board_id = b.id AND b.is_active = 1
             JOIN redacoes_orientadas_tipos_texto t ON p.text_type_id = t.id
             JOIN professores prof ON p.teacher_id = prof.id
             LEFT JOIN redacoes_orientadas_entregas s
                    ON s.id = (
                        SELECT s2.id
                        FROM redacoes_orientadas_entregas s2
                        WHERE s2.proposal_id = p.id
                          AND s2.student_id = :student_id_submission
                        ORDER BY s2.id DESC
                        LIMIT 1
                    )
             LEFT JOIN redacoes_orientadas_correcoes c ON c.submission_id = s.id
             WHERE p.status = 'published'
               {$filtroAtivo}
               AND (
                   -- Aluno está na lista de alunos específicos
                   EXISTS (
                       SELECT 1 FROM redacoes_orientadas_propostas_alunos ps 
                       WHERE ps.proposal_id = p.id AND ps.student_id = :student_id
                   )
                   OR
                   -- Não há alunos específicos E a turma do aluno está na lista de turmas
                   (
                       NOT EXISTS (SELECT 1 FROM redacoes_orientadas_propostas_alunos ps2 WHERE ps2.proposal_id = p.id)
                       AND (
                           -- Turma principal do aluno
                           EXISTS (
                               SELECT 1 FROM redacoes_orientadas_propostas_turmas pt
                               JOIN alunos a ON a.turma_id = pt.turma_id
                               WHERE pt.proposal_id = p.id AND a.id = :student_id2
                           )
                           OR
                           -- Turmas via matrículas ativas
                           EXISTS (
                               SELECT 1 FROM redacoes_orientadas_propostas_turmas pt
                               JOIN matricula m ON m.turma_id = pt.turma_id AND m.status = 'ativa'
                               WHERE pt.proposal_id = p.id AND m.aluno_id = :student_id3
                           )
                       )
                   )
               )
             ORDER BY p.id ASC",
            [
                'student_id_submission' => (int) $studentId,
                'student_id' => (int) $studentId,
                'student_id2' => (int) $studentId,
                'student_id3' => (int) $studentId
            ]
        );
        return $this->sortByTitleNumeric($rows);
    }

    public function create($data)
    {
        $imagesJson = isset($data['images_json']) ? (is_string($data['images_json']) ? $data['images_json'] : json_encode($data['images_json'])) : null;
        $themeMode = isset($data['theme_mode']) && in_array($data['theme_mode'], ['configurar', 'arquivo'], true) ? $data['theme_mode'] : 'configurar';
        $showTitle = isset($data['show_title_field']) ? (int) (bool) $data['show_title_field'] : 1;
        $submissionMode = $this->normalizeSubmissionMode($data['submission_mode'] ?? 'texto');
        $startsAt = !empty($data['starts_at']) ? $data['starts_at'] : null;
        $endsAt = !empty($data['ends_at']) ? $data['ends_at'] : null;
        return $this->db->insert(
            "INSERT INTO redacoes_orientadas_propostas (teacher_id, board_id, text_type_id, theme_mode, title, theme, contexto, repertoire, tema_pronto_file, images_json, show_title_field, submission_mode, starts_at, ends_at, status) VALUES (:teacher_id, :board_id, :text_type_id, :theme_mode, :title, :theme, :contexto, :repertoire, :tema_pronto_file, :images_json, :show_title_field, :submission_mode, :starts_at, :ends_at, :status)",
            [
                'teacher_id' => (int) $data['teacher_id'],
                'board_id' => (int) $data['board_id'],
                'text_type_id' => (int) $data['text_type_id'],
                'theme_mode' => $themeMode,
                'title' => $data['title'],
                'theme' => $data['theme'] ?? null,
                'contexto' => isset($data['contexto']) ? (string) $data['contexto'] : null,
                'repertoire' => $data['repertoire'] ?? null,
                'tema_pronto_file' => $data['tema_pronto_file'] ?? null,
                'images_json' => $imagesJson,
                'show_title_field' => $showTitle,
                'submission_mode' => $submissionMode,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $data['status'] ?? 'draft'
            ]
        );
    }

    public function update($id, $data)
    {
        $imagesJson = isset($data['images_json']) ? (is_string($data['images_json']) ? $data['images_json'] : json_encode($data['images_json'])) : null;
        $themeMode = isset($data['theme_mode']) && in_array($data['theme_mode'], ['configurar', 'arquivo'], true) ? $data['theme_mode'] : 'configurar';
        $showTitle = isset($data['show_title_field']) ? (int) (bool) $data['show_title_field'] : 1;
        $startsAt = array_key_exists('starts_at', $data) ? ($data['starts_at'] ?: null) : null;
        $endsAt = array_key_exists('ends_at', $data) ? ($data['ends_at'] ?: null) : null;
        $temaProntoFile = array_key_exists('tema_pronto_file', $data) ? ($data['tema_pronto_file'] ?: null) : null;
        $submissionMode = $this->normalizeSubmissionMode($data['submission_mode'] ?? 'texto');
        $this->db->update(
            "UPDATE redacoes_orientadas_propostas SET board_id = :board_id, text_type_id = :text_type_id, theme_mode = :theme_mode, title = :title, theme = :theme, contexto = :contexto, repertoire = :repertoire, tema_pronto_file = :tema_pronto_file, images_json = :images_json, show_title_field = :show_title_field, submission_mode = :submission_mode, starts_at = :starts_at, ends_at = :ends_at, status = :status, updated_at = NOW() WHERE id = :id",
            [
                'id' => (int) $id,
                'board_id' => (int) $data['board_id'],
                'text_type_id' => (int) $data['text_type_id'],
                'theme_mode' => $themeMode,
                'title' => $data['title'],
                'theme' => $data['theme'] ?? null,
                'contexto' => array_key_exists('contexto', $data) ? ($data['contexto'] !== '' ? (string) $data['contexto'] : null) : null,
                'repertoire' => $data['repertoire'] ?? null,
                'tema_pronto_file' => $temaProntoFile,
                'images_json' => $imagesJson,
                'show_title_field' => $showTitle,
                'submission_mode' => $submissionMode,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $data['status'] ?? 'draft'
            ]
        );
    }

    /**
     * Soft delete: nunca apaga a proposta (histórico de envios/correções dos
     * alunos precisa continuar íntegro) — só marca ativo=0/1. Listagem do
     * admin continua mostrando propostas inativas (pode reativar); professor,
     * aluno, relatórios e dashboards de analytics filtram por ativo=1.
     */
    public function toggleAtivo($id, bool $ativo): void
    {
        if (!$this->temColunaAtivoProposta()) {
            throw new RuntimeException('Coluna ativo indisponível. Execute a migration 2026_07_14_redacoes_orientadas_propostas_ativo.');
        }

        // Não usar rowCount() como sinal de sucesso: se o valor já era o
        // pedido (ex.: reativar algo já ativo), o UPDATE casa a linha mas
        // MySQL/PDO reporta 0 linhas afetadas — o controller confirma
        // sucesso checando o novo estado via findById(), não o retorno daqui.
        $this->db->update(
            "UPDATE redacoes_orientadas_propostas SET ativo = :ativo, updated_at = NOW() WHERE id = :id",
            ['id' => (int) $id, 'ativo' => $ativo ? 1 : 0]
        );
    }
}
