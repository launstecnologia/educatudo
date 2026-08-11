<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/Ava/Activity.php';
require_once __DIR__ . '/../Models/Ava/ActivitySubmission.php';
require_once __DIR__ . '/../Models/Ava/Rubric.php';

/**
 * EducaTudo - AvaActivityService
 *
 * Lógica de atividades/tarefas com entrega: listagem por professor/aluno,
 * controle de prazo e cálculo de nota a partir de rubrica.
 */
class AvaActivityService
{
    private Activity $activities;
    private ActivitySubmission $submissions;
    private Rubric $rubrics;

    public function __construct()
    {
        $this->activities = new Activity();
        $this->submissions = new ActivitySubmission();
        $this->rubrics = new Rubric();
    }

    public function activitiesModel(): Activity { return $this->activities; }
    public function submissionsModel(): ActivitySubmission { return $this->submissions; }
    public function rubricsModel(): Rubric { return $this->rubrics; }

    /** Atividades da disciplina com contadores (visão professor/admin). @return list<array<string,mixed>> */
    public function listForTeacher(int $disciplinaId): array
    {
        return $this->activities->byDiscipline($disciplinaId, false);
    }

    /**
     * Atividades publicadas + a entrega do aluno em cada uma (visão aluno).
     * @return list<array<string,mixed>>
     */
    public function listForStudent(int $alunoId, int $disciplinaId): array
    {
        $atividades = $this->activities->byDiscipline($disciplinaId, true);
        $ids = array_map(static fn($a) => (int) $a['id'], $atividades);
        $entregas = $this->submissions->mapByStudentForActivities($alunoId, $ids);
        foreach ($atividades as &$a) {
            $a['entrega'] = $entregas[(int) $a['id']] ?? null;
            $a['situacao'] = $this->studentSituation($a, $a['entrega']);
        }
        unset($a);
        return $atividades;
    }

    /** Classifica a situação da atividade para o aluno. */
    public function studentSituation(array $atividade, ?array $entrega): string
    {
        if ($entrega) {
            $status = (string) ($entrega['status'] ?? '');
            if ($status === 'avaliada') {
                return 'avaliada';
            }
            if ($status === 'enviada') {
                return 'enviada';
            }
            if ($status === 'reenviar') {
                return 'reenviar';
            }
        }
        if ($this->isClosed($atividade)) {
            return 'encerrada';
        }
        return 'pendente';
    }

    /** A atividade ainda aceita envio? */
    public function canSubmit(array $atividade): bool
    {
        if (($atividade['status'] ?? '') !== 'publicada') {
            return false;
        }
        if (!empty($atividade['data_abertura']) && strtotime((string) $atividade['data_abertura']) > time()) {
            return false;
        }
        if ($this->isClosed($atividade)) {
            return (int) ($atividade['aceita_atraso'] ?? 0) === 1;
        }
        return true;
    }

    /** Prazo final já passou? */
    public function isClosed(array $atividade): bool
    {
        if (($atividade['status'] ?? '') === 'encerrada') {
            return true;
        }
        if (!empty($atividade['data_entrega'])) {
            return strtotime((string) $atividade['data_entrega']) < time();
        }
        return false;
    }

    /** A entrega de agora seria considerada atrasada? */
    public function wouldBeLate(array $atividade): bool
    {
        return !empty($atividade['data_entrega']) && strtotime((string) $atividade['data_entrega']) < time();
    }

    /**
     * Calcula a nota a partir das pontuações por critério da rubrica.
     * A nota final é normalizada para a nota_maxima da atividade.
     *
     * @param array<int,float> $pontuacoesPorCriterio  [criterio_id => pontuacao]
     * @return array{nota:float,resultado:array<int,array<string,mixed>>}
     */
    public function gradeFromRubric(int $rubricaId, array $pontuacoesPorCriterio, float $notaMaxima): array
    {
        $criterios = $this->rubrics->criteria($rubricaId);
        if (empty($criterios)) {
            return ['nota' => 0.0, 'resultado' => []];
        }
        $somaPesoMax = 0.0;
        $somaPesoObtido = 0.0;
        $resultado = [];
        foreach ($criterios as $c) {
            $cid = (int) $c['id'];
            $peso = (float) $c['peso'];
            $pmax = (float) $c['pontuacao_max'];
            $obtido = max(0.0, min($pmax, (float) ($pontuacoesPorCriterio[$cid] ?? 0)));
            $somaPesoMax += $pmax * $peso;
            $somaPesoObtido += $obtido * $peso;
            $resultado[$cid] = [
                'criterio_id' => $cid,
                'titulo' => $c['titulo'],
                'pontuacao' => $obtido,
                'pontuacao_max' => $pmax,
                'peso' => $peso,
            ];
        }
        $nota = $somaPesoMax > 0 ? round(($somaPesoObtido / $somaPesoMax) * $notaMaxima, 2) : 0.0;
        return ['nota' => $nota, 'resultado' => $resultado];
    }
}
