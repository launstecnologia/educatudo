<?php

require_once __DIR__ . '/../Core/Database.php';

/**
 * EducaTudo - FrequencyService
 *
 * Fonte única de verdade da frequência escolar, calculada a partir do Diário de
 * Classe (chamada por aula). Considera apenas aulas finalizadas. Usado pelo
 * Diário, pelo painel de conformidade e por alertas de frequência mínima legal
 * (LDB Lei 9.394/96, art. 24, VI — mínimo de 75%).
 */
class FrequencyService
{
    /** Frequência mínima legal (LDB). */
    public const MINIMO_LEGAL = 75.0;

    /** @var Database */
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    private function schemaPronto(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS n FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name IN ('diario_aulas','diario_frequencias')"
            );
            $ok = (int) ($row['n'] ?? 0) >= 2;
        } catch (Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * Percentual de presença de uma turma no período (0–100), ou null sem registros.
     */
    public function turmaPercentual(int $turmaId, string $inicio, string $fim): ?float
    {
        if (!$this->schemaPronto() || $turmaId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas
             FROM diario_frequencias df
             INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
             WHERE da.turma_id = :turma_id AND da.status = 'finalizada'
               AND da.data_aula BETWEEN :inicio AND :fim",
            ['turma_id' => $turmaId, 'inicio' => $inicio, 'fim' => $fim]
        );
        $total = (int) ($row['total'] ?? 0);
        if ($total <= 0) {
            return null;
        }
        return round(((int) ($row['presencas'] ?? 0) / $total) * 100, 1);
    }

    /**
     * Frequência por aluno da turma no período.
     *
     * @return list<array{aluno_id:int,nome:string,total_aulas:int,presencas:int,faltas:int,faltas_justificadas:int,percentual:?float}>
     */
    public function alunosPercentual(int $turmaId, string $inicio, string $fim): array
    {
        if (!$this->schemaPronto() || $turmaId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT a.id AS aluno_id, a.nome,
                    COUNT(df.id) AS total_aulas,
                    SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas,
                    SUM(CASE WHEN df.situacao = 'falta' THEN 1 ELSE 0 END) AS faltas,
                    SUM(CASE WHEN df.situacao = 'falta_justificada' THEN 1 ELSE 0 END) AS faltas_justificadas
             FROM alunos a
             LEFT JOIN diario_frequencias df ON df.aluno_id = a.id
             LEFT JOIN diario_aulas da ON da.id = df.diario_aula_id
                  AND da.status = 'finalizada' AND da.turma_id = a.turma_id
                  AND da.data_aula BETWEEN :inicio AND :fim
             WHERE a.turma_id = :turma_id AND a.ativo = 1
             GROUP BY a.id, a.nome
             ORDER BY a.nome ASC",
            ['turma_id' => $turmaId, 'inicio' => $inicio, 'fim' => $fim]
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $total = (int) ($r['total_aulas'] ?? 0);
            $presencas = (int) ($r['presencas'] ?? 0);
            $out[] = [
                'aluno_id' => (int) $r['aluno_id'],
                'nome' => (string) $r['nome'],
                'total_aulas' => $total,
                'presencas' => $presencas,
                'faltas' => (int) ($r['faltas'] ?? 0),
                'faltas_justificadas' => (int) ($r['faltas_justificadas'] ?? 0),
                'percentual' => $total > 0 ? round(($presencas / $total) * 100, 1) : null,
            ];
        }
        return $out;
    }

    /**
     * Alunos com frequência abaixo do mínimo legal (default 75%) na turma/período.
     * Ignora alunos sem nenhuma aula registrada (percentual null).
     *
     * @return list<array<string,mixed>>
     */
    public function abaixoDoMinimo(int $turmaId, string $inicio, string $fim, float $minimo = self::MINIMO_LEGAL): array
    {
        $out = [];
        foreach ($this->alunosPercentual($turmaId, $inicio, $fim) as $aluno) {
            if ($aluno['percentual'] !== null && $aluno['percentual'] < $minimo) {
                $out[] = $aluno;
            }
        }
        return $out;
    }

    /**
     * Percentual de frequência por matéria de uma turma no período.
     *
     * @return list<array{materia_id:int,materia_nome:string,total_aulas:int,percentual:?float}>
     */
    public function porDisciplina(int $turmaId, string $inicio, string $fim): array
    {
        if (!$this->schemaPronto() || $turmaId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT da.materia_id, m.nome AS materia_nome,
                    COUNT(df.id) AS total_aulas,
                    SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas
             FROM diario_aulas da
             INNER JOIN diario_frequencias df ON df.diario_aula_id = da.id
             INNER JOIN materias m ON m.id = da.materia_id
             WHERE da.turma_id = :turma_id AND da.status = 'finalizada'
               AND da.data_aula BETWEEN :inicio AND :fim
             GROUP BY da.materia_id, m.nome
             ORDER BY m.nome ASC",
            ['turma_id' => $turmaId, 'inicio' => $inicio, 'fim' => $fim]
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $total = (int) ($r['total_aulas'] ?? 0);
            $out[] = [
                'materia_id' => (int) $r['materia_id'],
                'materia_nome' => (string) $r['materia_nome'],
                'total_aulas' => $total,
                'percentual' => $total > 0 ? round(((int) ($r['presencas'] ?? 0) / $total) * 100, 1) : null,
            ];
        }
        return $out;
    }
}
