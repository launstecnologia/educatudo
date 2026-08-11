<?php
/**
 * Serviço para governança de status do aluno (LGPD)
 */

namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';

class StudentStatusService
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance();
    }

    public function inactivate($studentId, array $payload, array $currentUser)
    {
        $this->assertPermission($currentUser);
        $reason = strtoupper(trim((string)($payload['reason'] ?? '')));
        $observation = trim((string)($payload['observation'] ?? ''));
        $confirm = strtolower(trim((string)($payload['confirm'] ?? '')));
        $confirmText = strtoupper(trim((string)($payload['confirm_text'] ?? '')));

        if (!in_array($reason, ['TRANSFERENCIA', 'EVASAO', 'CONCLUSAO', 'ADMINISTRATIVO'], true)) {
            throw new \Exception('Motivo inválido para inativação');
        }
        if ($observation === '') {
            throw new \Exception('Observação é obrigatória');
        }
        if (!in_array($confirm, ['1', 'true', 'on', 'yes'], true) && $confirmText !== 'CONFIRMAR') {
            throw new \Exception('Confirmação explícita é obrigatória');
        }

        if (empty($payload['_skip_reauth'])) {
            $this->verifyReauth($currentUser['id'] ?? null, $payload);
        }

        $student = $this->buscarAluno($studentId, true);
        if (!$student) {
            throw new \Exception('Aluno não encontrado');
        }

        $oldStatus = $this->normalizarStatus($student);
        $newStatus = ($reason === 'CONCLUSAO') ? 'GRADUATED' : 'INACTIVE';
        if ($oldStatus === $newStatus && (int) ($student['ativo'] ?? 0) === 0) {
            throw new \Exception('Aluno já está com o status informado');
        }

        $ip = $this->getIpAddress();

        $transacaoAtiva = $this->iniciarTransacao();
        try {
            $this->atualizarStatusAluno((int) $studentId, $newStatus);
            $this->registrarHistoricoStatus((int) $studentId, $oldStatus, $newStatus, $reason, $observation, (int) ($currentUser['id'] ?? 0), $ip);

            $this->executarOpcional('endStudentSessions', function () use ($studentId) {
                $this->endStudentSessions($studentId);
            });
            $this->executarOpcional('applyLgpdRules', function () use ($studentId, $newStatus) {
                $this->applyLgpdRules($studentId, $newStatus);
            });
            $this->executarOpcional('encerrarMatriculasAtivas', function () use ($studentId, $reason) {
                $this->encerrarMatriculasAtivas($studentId, $reason);
            });
            $this->executarOpcional('aplicarListaChamadaInativacao', function () use ($studentId, $student, $reason) {
                $this->aplicarListaChamadaInativacao($studentId, $student, $reason);
            });

            $this->executarOpcional('logAudit', function () use ($studentId, $oldStatus, $newStatus, $reason, $currentUser, $ip) {
                if (!class_exists('\Logger')) {
                    require_once __DIR__ . '/../Core/Logger.php';
                }
                if (class_exists('\Logger')) {
                    \Logger::logAudit(
                        'student_inactivated',
                        'student',
                        [
                            'student_id' => $studentId,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'reason' => $reason
                        ],
                        $currentUser['id'],
                        $currentUser['tipo'] ?? null,
                        $ip
                    );
                }
            });

            $this->confirmarTransacao($transacaoAtiva);
        } catch (\Throwable $e) {
            $this->reverterTransacao($transacaoAtiva);
            throw $e;
        }

        return [
            'student_id' => $studentId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ];
    }

    public function activate($studentId, array $payload, array $currentUser)
    {
        $this->assertPermission($currentUser);
        $confirm = strtolower(trim((string) ($payload['confirm'] ?? '')));
        $confirmText = strtoupper(trim((string) ($payload['confirm_text'] ?? '')));
        if (!in_array($confirm, ['1', 'true', 'on', 'yes'], true) && $confirmText !== 'CONFIRMAR') {
            throw new \Exception('Confirmação explícita é obrigatória');
        }

        $student = $this->buscarAluno($studentId, false);
        if (!$student) {
            throw new \Exception('Aluno não encontrado');
        }

        $oldStatus = $this->normalizarStatus($student);
        if ($oldStatus === 'ACTIVE' && (int) $student['ativo'] === 1) {
            throw new \Exception('Aluno já está ativo');
        }

        $ip = $this->getIpAddress();
        $transacaoAtiva = $this->iniciarTransacao();
        try {
            if ($this->colunaStatusExiste()) {
                $this->db->update(
                    "UPDATE alunos SET status = 'ACTIVE', ativo = 1 WHERE id = :id",
                    ['id' => $studentId]
                );
            } else {
                $this->db->update(
                    "UPDATE alunos SET ativo = 1 WHERE id = :id",
                    ['id' => $studentId]
                );
            }

            $this->registrarHistoricoStatus(
                (int) $studentId,
                $oldStatus,
                'ACTIVE',
                'REATIVACAO',
                trim((string) ($payload['observation'] ?? 'Reativação administrativa')),
                (int) ($currentUser['id'] ?? 0),
                $ip
            );

            $this->executarOpcional('logAudit', function () use ($studentId, $oldStatus, $currentUser, $ip) {
                if (!class_exists('\Logger')) {
                    require_once __DIR__ . '/../Core/Logger.php';
                }
                if (class_exists('\Logger')) {
                    \Logger::logAudit(
                        'student_activated',
                        'student',
                        ['student_id' => $studentId, 'old_status' => $oldStatus],
                        $currentUser['id'],
                        $currentUser['tipo'] ?? null,
                        $ip
                    );
                }
            });

            $this->confirmarTransacao($transacaoAtiva);
        } catch (\Throwable $e) {
            $this->reverterTransacao($transacaoAtiva);
            throw $e;
        }

        return [
            'student_id' => $studentId,
            'old_status' => $oldStatus,
            'new_status' => 'ACTIVE'
        ];
    }

    private function iniciarTransacao(): bool
    {
        if (!method_exists($this->db, 'beginTransaction')) {
            return false;
        }
        $this->db->beginTransaction();

        return true;
    }

    private function confirmarTransacao(bool $transacaoAtiva): void
    {
        if (!$transacaoAtiva || !method_exists($this->db, 'commit')) {
            return;
        }
        $this->db->commit();
    }

    private function reverterTransacao(bool $transacaoAtiva): void
    {
        if (!$transacaoAtiva) {
            return;
        }
        if (method_exists($this->db, 'inTransaction') && !$this->db->inTransaction()) {
            return;
        }
        if (method_exists($this->db, 'rollback')) {
            $this->db->rollback();
        } elseif (method_exists($this->db, 'rollBack')) {
            $this->db->rollBack();
        }
    }

    private function buscarAluno(int $studentId, bool $comTurma): ?array
    {
        $cols = ['id', 'nome', 'ativo'];
        if ($this->colunaStatusExiste()) {
            $cols[] = 'status';
        }
        if ($comTurma) {
            $cols[] = 'turma_id';
        }

        return $this->db->fetch(
            'SELECT ' . implode(', ', $cols) . ' FROM alunos WHERE id = :id',
            ['id' => $studentId]
        ) ?: null;
    }

    private function normalizarStatus(array $student): string
    {
        $status = strtoupper(trim((string) ($student['status'] ?? '')));
        if ($status !== '') {
            return $status;
        }

        return (int) ($student['ativo'] ?? 0) === 1 ? 'ACTIVE' : 'INACTIVE';
    }

    private function atualizarStatusAluno(int $studentId, string $newStatus): void
    {
        if ($this->colunaStatusExiste()) {
            $this->db->update(
                "UPDATE alunos SET status = :status, ativo = 0 WHERE id = :id",
                [
                    'status' => $newStatus,
                    'id' => $studentId
                ]
            );

            return;
        }

        $this->db->update(
            "UPDATE alunos SET ativo = 0 WHERE id = :id",
            ['id' => $studentId]
        );
    }

    private function registrarHistoricoStatus(
        int $studentId,
        string $oldStatus,
        string $newStatus,
        string $reason,
        string $observation,
        int $changedBy,
        string $ip
    ): void {
        if (!$this->tabelaHistoricoStatusExiste()) {
            return;
        }

        $this->db->insert(
            "INSERT INTO alunos_historico_status
             (student_id, old_status, new_status, reason, observation, changed_by, ip, created_at)
             VALUES (:student_id, :old_status, :new_status, :reason, :observation, :changed_by, :ip, NOW())",
            [
                'student_id' => $studentId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'observation' => $observation,
                'changed_by' => $changedBy,
                'ip' => $ip
            ]
        );
    }

    private function colunaStatusExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'status'"
        );
        $cache = !empty($row['total']);

        return $cache;
    }

    private function tabelaHistoricoStatusExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = $this->db->fetch("SHOW TABLES LIKE 'alunos_historico_status'") !== false;

        return $cache;
    }

    private function executarOpcional(string $contexto, callable $callback): void
    {
        try {
            $callback();
        } catch (\Exception $e) {
            error_log('StudentStatusService ' . $contexto . ': ' . $e->getMessage());
        }
    }

    private function encerrarMatriculasAtivas($studentId, string $reason): void
    {
        try {
            $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
            if ($hasMatricula === false) {
                return;
            }
            require_once __DIR__ . '/MatriculaSchemaHelper.php';
            $status = $reason === 'CONCLUSAO'
                ? \App\Services\MatriculaSchemaHelper::statusEncerramentoManual()
                : 'transferido';
            $this->db->query(
                "UPDATE matricula SET data_saida = CURDATE(), status = :status, updated_at = NOW()
                 WHERE aluno_id = :aid AND status = 'ativa'
                   AND data_saida IS NULL",
                ['aid' => $studentId, 'status' => $status]
            );
        } catch (\Exception $e) {
            error_log('StudentStatusService encerrarMatriculasAtivas: ' . $e->getMessage());
        }
    }

    private function aplicarListaChamadaInativacao($studentId, array $student, string $reason): void
    {
        if ($reason !== 'TRANSFERENCIA') {
            return;
        }
        $turmaId = (int) ($student['turma_id'] ?? 0);
        if ($turmaId <= 0) {
            return;
        }
        $svcPath = __DIR__ . '/ListaChamadaService.php';
        if (!is_file($svcPath)) {
            return;
        }
        require_once $svcPath;
        $svc = new \App\Services\ListaChamadaService();
        if (!$svc->tabelaExiste()) {
            return;
        }
        $anoLetivoId = $svc->resolverAnoLetivoIdParaTurma($turmaId);
        if ($anoLetivoId > 0) {
            $svc->marcarTR((int) $studentId, $turmaId, $anoLetivoId);
        }
    }

    private function assertPermission(array $user)
    {
        if (!$user || !in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            throw new \Exception('Acesso negado');
        }
        $perfil = $user['perfil_admin'] ?? '';
        if ($perfil === 'financeiro') {
            throw new \Exception('Acesso negado');
        }
        if (!class_exists('\AdminSecretariaAccess')) {
            $accessPath = __DIR__ . '/../Core/AdminSecretariaAccess.php';
            if (is_file($accessPath)) {
                require_once $accessPath;
            }
        }
        if (class_exists('\AdminSecretariaAccess')
            && $perfil !== ''
            && !in_array($perfil, \AdminSecretariaAccess::perfisAdminEscolaGestaoPedagogica(), true)) {
            throw new \Exception('Acesso negado');
        }
    }

    public function verifyReauthForUser($userId, array $payload): void
    {
        $this->verifyReauth($userId, $payload);
    }

    private function verifyReauth($userId, array $payload)
    {
        $password = (string)($payload['password'] ?? '');
        $pin = (string)($payload['pin'] ?? '');

        if ($password === '' && $pin === '') {
            throw new \Exception('Reautenticação obrigatória');
        }

        $admin = $this->db->fetch(
            "SELECT senha_hash FROM usuarios WHERE id = :id",
            ['id' => $userId]
        );
        if (!$admin) {
            throw new \Exception('Usuário administrador não encontrado');
        }

        if ($password !== '') {
            if (!password_verify($password, $admin['senha_hash'])) {
                throw new \Exception('Senha inválida');
            }
            return;
        }

        if ($pin !== '') {
            $hasPinColumn = $this->db->fetch(
                "SELECT COUNT(*) as total
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'usuarios'
                   AND COLUMN_NAME = 'pin_hash'"
            );
            if (empty($hasPinColumn['total'])) {
                throw new \Exception('PIN não configurado para este usuário');
            }
            $adminPin = $this->db->fetch(
                "SELECT pin_hash FROM usuarios WHERE id = :id",
                ['id' => $userId]
            );
            if (!$adminPin || empty($adminPin['pin_hash']) || !password_verify($pin, $adminPin['pin_hash'])) {
                throw new \Exception('PIN inválido');
            }
        }
    }

    private function endStudentSessions($studentId)
    {
        $tabela = $this->db->fetch("SHOW TABLES LIKE 'alunos_sessoes_acesso'");
        if ($tabela === false) {
            return;
        }

        $this->db->query(
            "UPDATE alunos_sessoes_acesso
             SET status = 'expirado',
                 logout_at = NOW(),
                 tempo_uso_segundos = TIMESTAMPDIFF(SECOND, login_at, NOW())
             WHERE aluno_id = :aluno_id AND status = 'ativo'",
            ['aluno_id' => $studentId]
        );
    }

    private function applyLgpdRules($studentId, $newStatus)
    {
        if (!in_array($newStatus, ['INACTIVE', 'GRADUATED'], true)) {
            return;
        }

        if ($this->tabelaExiste('alertas_sensiveis')) {
            $hasAlerts = $this->db->fetch(
                "SELECT id FROM alertas_sensiveis WHERE aluno_id = :aluno_id LIMIT 1",
                ['aluno_id' => $studentId]
            );

            if (!$hasAlerts && $this->tabelaExiste('tudinha_mensagens') && $this->tabelaExiste('tudinha_conversas')) {
                $this->db->update(
                    "UPDATE tudinha_mensagens mc
                     INNER JOIN tudinha_conversas cc ON mc.conversa_id = cc.id
                     SET mc.expires_at = DATE_ADD(NOW(), INTERVAL 5 YEAR),
                         mc.retention_reason = 'educational_and_legal'
                     WHERE cc.aluno_id = :aluno_id
                       AND (mc.expires_at IS NULL OR mc.expires_at < DATE_ADD(NOW(), INTERVAL 5 YEAR))",
                    ['aluno_id' => $studentId]
                );
            }
        }

        if ($this->tabelaExiste('tudinha_analises')) {
            $this->db->update(
                "UPDATE tudinha_analises
                 SET expires_at = DATE_ADD(created_at, INTERVAL 20 YEAR),
                     retention_reason = 'educational_and_legal'
                 WHERE aluno_id = :aluno_id
                   AND expires_at IS NULL",
                ['aluno_id' => $studentId]
            );
        }

        if ($this->tabelaExiste('alertas_sensiveis')) {
            $this->db->update(
                "UPDATE alertas_sensiveis
                 SET expires_at = DATE_ADD(created_at, INTERVAL 20 YEAR),
                     retention_reason = 'educational_and_legal'
                 WHERE aluno_id = :aluno_id
                   AND expires_at IS NULL",
                ['aluno_id' => $studentId]
            );
        }

        if ($this->tabelaExiste('alertas_sensiveis_acoes') && $this->tabelaExiste('alertas_sensiveis')) {
            $this->db->update(
                "UPDATE alertas_sensiveis_acoes a
                 INNER JOIN alertas_sensiveis s ON a.alerta_id = s.id
                 SET a.expires_at = DATE_ADD(a.created_at, INTERVAL 20 YEAR),
                     a.retention_reason = 'educational_and_legal'
                 WHERE s.aluno_id = :aluno_id
                   AND a.expires_at IS NULL",
                ['aluno_id' => $studentId]
            );
        }
    }

    private function tabelaExiste(string $nome): bool
    {
        $nome = preg_replace('/[^a-zA-Z0-9_]/', '', $nome);
        if ($nome === '') {
            return false;
        }

        return $this->db->fetch("SHOW TABLES LIKE '{$nome}'") !== false;
    }

    private function getIpAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
