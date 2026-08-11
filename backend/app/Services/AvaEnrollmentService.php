<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/Ava/Discipline.php';
require_once __DIR__ . '/../Models/Ava/DisciplineEnrollment.php';

/**
 * EducaTudo - AvaEnrollmentService
 *
 * Matrícula de alunos em disciplinas do AVA. Suporta matrícula manual e
 * sincronização a partir do ERP (alunos de uma turma vinculada à disciplina).
 */
class AvaEnrollmentService
{
    private $db;
    private Discipline $disciplines;
    private DisciplineEnrollment $enrollments;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->disciplines = new Discipline();
        $this->enrollments = new DisciplineEnrollment();
    }

    public function enroll(int $alunoId, int $disciplinaId, string $origem = 'manual'): int
    {
        return $this->enrollments->enroll($alunoId, $disciplinaId, $origem);
    }

    public function unenroll(int $alunoId, int $disciplinaId): void
    {
        $m = $this->enrollments->find($alunoId, $disciplinaId);
        if ($m) {
            $this->enrollments->setStatus((int) $m['id'], 'cancelada');
        }
    }

    /**
     * Matricula todos os alunos ativos de uma turma na disciplina.
     * Retorna a quantidade efetivamente matriculada.
     */
    public function syncFromTurma(int $disciplinaId, int $turmaId): int
    {
        if ($disciplinaId <= 0 || $turmaId <= 0) {
            return 0;
        }
        $alunos = $this->alunosDaTurma($turmaId);
        $count = 0;
        foreach ($alunos as $a) {
            $this->enrollments->enroll((int) $a['id'], $disciplinaId, 'erp');
            $count++;
        }
        return $count;
    }

    /**
     * Usa o turma_id configurado na disciplina (quando houver) para sincronizar.
     */
    public function syncDisciplineFromErp(int $disciplinaId): int
    {
        $disc = $this->disciplines->find($disciplinaId);
        $turmaId = (int) ($disc['turma_id'] ?? 0);
        if ($turmaId <= 0) {
            return 0;
        }
        return $this->syncFromTurma($disciplinaId, $turmaId);
    }

    /** @return list<array<string,mixed>> */
    private function alunosDaTurma(int $turmaId): array
    {
        // Resolve alunos da turma de forma defensiva conforme o schema disponível.
        try {
            if ($this->columnExists('alunos', 'turma_id')) {
                return $this->db->fetchAll(
                    "SELECT id, nome FROM alunos WHERE turma_id = :t AND (ativo = 1 OR ativo IS NULL) ORDER BY nome",
                    ['t' => $turmaId]
                ) ?: [];
            }
            if ($this->tableExists('alunos_turmas')) {
                return $this->db->fetchAll(
                    "SELECT a.id, a.nome FROM alunos a
                     INNER JOIN alunos_turmas at ON at.aluno_id = a.id
                     WHERE at.turma_id = :t ORDER BY a.nome",
                    ['t' => $turmaId]
                ) ?: [];
            }
        } catch (Throwable $e) {
            return [];
        }
        return [];
    }

    private function tableExists(string $tabela): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t",
                ['t' => $tabela]
            );
            return $row !== false && !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function columnExists(string $tabela, string $coluna): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c",
                ['t' => $tabela, 'c' => $coluna]
            );
            return $row !== false && !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }
}
