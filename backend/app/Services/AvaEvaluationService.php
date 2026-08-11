<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/Ava/DisciplineEvaluation.php';
require_once __DIR__ . '/../Models/Ava/DisciplineEnrollment.php';

/**
 * EducaTudo - AvaEvaluationService
 *
 * Regras de negócio das avaliações do AVA: vincular uma prova existente a uma
 * disciplina, liberar pelo progresso do aluno e devolver a nota (de
 * `provas_realizacoes`) para a matrícula da disciplina.
 */
class AvaEvaluationService
{
    private $db;
    private DisciplineEvaluation $model;
    private DisciplineEnrollment $enrollments;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->model = new DisciplineEvaluation();
        $this->enrollments = new DisciplineEnrollment();
    }

    public function model(): DisciplineEvaluation
    {
        return $this->model;
    }

    /** @return list<array<string,mixed>> */
    public function listForDiscipline(int $disciplinaId): array
    {
        return $this->model->byDiscipline($disciplinaId);
    }

    /**
     * Provas disponíveis para vincular (módulo Provas), exceto as já vinculadas.
     * @return list<array<string,mixed>>
     */
    public function availableProvas(int $disciplinaId): array
    {
        try {
            $ja = array_column($this->model->byDiscipline($disciplinaId), 'prova_id');
            $provas = $this->db->fetchAll(
                "SELECT p.id, p.titulo, p.valor_total, p.liberada, p.ativo, m.nome AS materia_nome
                 FROM provas p
                 LEFT JOIN materias m ON m.id = p.materia_id
                 WHERE p.deleted_at IS NULL
                 ORDER BY p.id DESC
                 LIMIT 500"
            ) ?: [];
            if (empty($ja)) {
                return $provas;
            }
            return array_values(array_filter($provas, static fn($p) => !in_array((int) $p['id'], array_map('intval', $ja), true)));
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @param array<string,mixed> $data */
    public function attach(array $data): int
    {
        return $this->model->create($data);
    }

    public function detach(int $id): void
    {
        $this->model->delete($id);
    }

    /**
     * Visão do aluno: para cada avaliação da disciplina, estado de liberação,
     * status da tentativa e nota. Também sincroniza a nota final da matrícula.
     *
     * @return list<array<string,mixed>>
     */
    public function studentView(int $alunoId, int $disciplinaId): array
    {
        if (!$this->model->tableExists()) {
            return [];
        }
        $avaliacoes = $this->model->byDiscipline($disciplinaId);
        if (empty($avaliacoes)) {
            return [];
        }
        $matricula = $this->enrollments->find($alunoId, $disciplinaId);
        $progresso = (float) ($matricula['progresso_pct'] ?? 0);

        $itens = [];
        $somaPesos = 0.0;
        $somaNotas = 0.0;
        $algumaFinalizada = false;

        foreach ($avaliacoes as $av) {
            $requisito = (float) ($av['requisito_progresso_pct'] ?? 80);
            $liberado = $progresso >= $requisito;
            $realizacao = $this->realizacao((int) $av['prova_id'], $alunoId);
            $status = 'pendente';
            $nota = null;
            if ($realizacao) {
                $status = (string) ($realizacao['status'] ?? 'pendente');
                if ($status === 'finalizado' && $realizacao['nota'] !== null) {
                    $nota = (float) $realizacao['nota'];
                }
            }
            if ($status === 'finalizado') {
                $algumaFinalizada = true;
                $peso = max(0, (float) ($av['peso'] ?? 1));
                $somaPesos += $peso;
                $somaNotas += ($nota ?? 0) * $peso;
            }

            $itens[] = [
                'id' => (int) $av['id'],
                'prova_id' => (int) $av['prova_id'],
                'titulo' => ($av['titulo'] ?? null) ?: ($av['prova_titulo'] ?? 'Avaliação'),
                'requisito_progresso_pct' => $requisito,
                'obrigatoria' => (int) ($av['obrigatoria'] ?? 1) === 1,
                'valor_total' => $av['prova_valor'] ?? null,
                'progresso_atual' => $progresso,
                'liberado' => $liberado,
                'status' => $status,
                'nota' => $nota,
            ];
        }

        if ($algumaFinalizada && $somaPesos > 0) {
            $this->enrollments->setNotaFinal($alunoId, $disciplinaId, round($somaNotas / $somaPesos, 2));
        }

        return $itens;
    }

    /**
     * Verifica se a prova está liberada para o aluno via AVA (matriculado em uma
     * disciplina que vincula a prova e com progresso >= requisito). Usado como
     * bypass de liberação no fluxo de provas.
     */
    public function provaLiberadaParaAluno(int $provaId, int $alunoId): bool
    {
        if ($provaId <= 0 || $alunoId <= 0 || !$this->model->tableExists()) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                "SELECT av.requisito_progresso_pct, mm.progresso_pct
                 FROM ava_disciplina_avaliacoes av
                 INNER JOIN ava_matriculas_disciplina mm
                    ON mm.disciplina_id = av.disciplina_id AND mm.aluno_id = :a
                 WHERE av.prova_id = :p AND mm.status IN ('ativa','concluida')
                 ORDER BY mm.progresso_pct DESC
                 LIMIT 1",
                ['a' => $alunoId, 'p' => $provaId]
            );
            if (!$row) {
                return false;
            }
            return (float) $row['progresso_pct'] >= (float) $row['requisito_progresso_pct'];
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array<string,mixed>|null */
    private function realizacao(int $provaId, int $alunoId): ?array
    {
        try {
            $row = $this->db->fetch(
                "SELECT status, nota FROM provas_realizacoes WHERE prova_id = :p AND aluno_id = :a",
                ['p' => $provaId, 'a' => $alunoId]
            );
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
