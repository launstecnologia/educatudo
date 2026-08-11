<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/Ava/Lesson.php';
require_once __DIR__ . '/../Models/Ava/LessonProgress.php';
require_once __DIR__ . '/../Models/Ava/DisciplineEnrollment.php';

/**
 * EducaTudo - AvaProgressService
 *
 * Registra progresso de aulas/vídeos, recalcula o percentual da disciplina e
 * fornece a frequência EAD (percentual de aulas obrigatórias concluídas).
 */
class AvaProgressService
{
    private $db;
    private Lesson $lessons;
    private LessonProgress $progress;
    private DisciplineEnrollment $enrollments;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->lessons = new Lesson();
        $this->progress = new LessonProgress();
        $this->enrollments = new DisciplineEnrollment();
    }

    /** Marca uma aula como concluída e recalcula o progresso da disciplina. */
    public function completeLesson(int $alunoId, int $aulaId): array
    {
        $aula = $this->lessons->find($aulaId);
        if (!$aula) {
            return ['ok' => false, 'erro' => 'Aula não encontrada'];
        }
        $this->progress->setStatus($alunoId, $aulaId, 'concluida', 100.0);
        $pct = $this->recomputeDiscipline($alunoId, (int) $aula['disciplina_id']);
        return ['ok' => true, 'disciplina_id' => (int) $aula['disciplina_id'], 'progresso' => $pct];
    }

    public function markInProgress(int $alunoId, int $aulaId, float $percentual): void
    {
        $atual = $this->progress->find($alunoId, $aulaId);
        if ($atual && ($atual['status'] ?? '') === 'concluida') {
            return;
        }
        $this->progress->setStatus($alunoId, $aulaId, 'em_andamento', $percentual);
    }

    /** Salva posição/tempo do vídeo; conclui a aula automaticamente ao passar de 90%. */
    public function saveVideoProgress(int $alunoId, int $aulaId, int $segundoAtual, int $tempoAssistido, int $duracao): array
    {
        $aula = $this->lessons->find($aulaId);
        if (!$aula) {
            return ['ok' => false, 'erro' => 'Aula não encontrada'];
        }
        $res = $this->progress->saveVideo($alunoId, $aulaId, $segundoAtual, $tempoAssistido, $duracao);
        if (!empty($res['concluido'])) {
            $this->progress->setStatus($alunoId, $aulaId, 'concluida', 100.0);
            $this->recomputeDiscipline($alunoId, (int) $aula['disciplina_id']);
        } else {
            $this->markInProgress($alunoId, $aulaId, (float) $res['percentual']);
        }
        return ['ok' => true] + $res;
    }

    /** Recalcula e persiste o percentual de conclusão da disciplina para o aluno. */
    public function recomputeDiscipline(int $alunoId, int $disciplinaId): float
    {
        $stats = $this->progress->disciplineStats($alunoId, $disciplinaId);
        $pct = $stats['total'] > 0 ? round(($stats['concluidas'] / $stats['total']) * 100, 2) : 0.0;
        $this->enrollments->updateProgress($alunoId, $disciplinaId, $pct);
        if ($pct >= 100 && $stats['total'] > 0) {
            $m = $this->enrollments->find($alunoId, $disciplinaId);
            if ($m && ($m['status'] ?? '') === 'ativa') {
                $this->enrollments->setStatus((int) $m['id'], 'concluida');
            }
        }
        return $pct;
    }

    /**
     * Frequência EAD do aluno na disciplina = % de aulas obrigatórias concluídas.
     * @return array{total:int,concluidas:int,percentual:float}
     */
    public function eadFrequency(int $alunoId, int $disciplinaId): array
    {
        $total = $this->db->fetch(
            "SELECT COUNT(*) AS t FROM ava_aulas a
             INNER JOIN ava_modulos m ON m.id = a.modulo_id
             WHERE m.disciplina_id = :d AND a.obrigatoria = 1",
            ['d' => $disciplinaId]
        );
        $concl = $this->db->fetch(
            "SELECT COUNT(*) AS t FROM ava_progresso_aula pa
             INNER JOIN ava_aulas a ON a.id = pa.aula_id
             INNER JOIN ava_modulos m ON m.id = a.modulo_id
             WHERE m.disciplina_id = :d AND a.obrigatoria = 1 AND pa.aluno_id = :a AND pa.status = 'concluida'",
            ['d' => $disciplinaId, 'a' => $alunoId]
        );
        $t = (int) ($total['t'] ?? 0);
        $c = (int) ($concl['t'] ?? 0);
        return ['total' => $t, 'concluidas' => $c, 'percentual' => $t > 0 ? round(($c / $t) * 100, 2) : 0.0];
    }

    /** Resumo para o dashboard do aluno (totais agregados). @return array<string,mixed> */
    public function studentSummary(int $alunoId): array
    {
        $matriculas = $this->enrollments->byStudent($alunoId);
        $totalDisc = count($matriculas);
        $concluidas = 0;
        $somaPct = 0.0;
        foreach ($matriculas as $m) {
            if (($m['status'] ?? '') === 'concluida') {
                $concluidas++;
            }
            $somaPct += (float) ($m['progresso_pct'] ?? 0);
        }
        return [
            'total_disciplinas' => $totalDisc,
            'concluidas' => $concluidas,
            'em_andamento' => $totalDisc - $concluidas,
            'progresso_medio' => $totalDisc > 0 ? round($somaPct / $totalDisc, 1) : 0.0,
            'matriculas' => $matriculas,
        ];
    }

    /** Resumo da disciplina para o professor (alunos + progresso). @return array<string,mixed> */
    public function teacherDisciplineSummary(int $disciplinaId): array
    {
        $alunos = $this->enrollments->byDiscipline($disciplinaId);
        $totalAlunos = count($alunos);
        $somaPct = 0.0;
        $concluintes = 0;
        foreach ($alunos as $a) {
            $somaPct += (float) ($a['progresso_pct'] ?? 0);
            if (($a['status'] ?? '') === 'concluida') {
                $concluintes++;
            }
        }
        return [
            'total_alunos' => $totalAlunos,
            'progresso_medio' => $totalAlunos > 0 ? round($somaPct / $totalAlunos, 1) : 0.0,
            'concluintes' => $concluintes,
            'alunos' => $alunos,
        ];
    }
}
