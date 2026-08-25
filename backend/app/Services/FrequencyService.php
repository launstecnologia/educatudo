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
     * Com `$materiaId`, restringe ao diário dessa matéria (turma+matéria) em vez
     * de todas as matérias da turma.
     */
    public function turmaPercentual(int $turmaId, string $inicio, string $fim, ?int $materiaId = null, ?int $professorId = null): ?float
    {
        if (!$this->schemaPronto() || $turmaId <= 0) {
            return null;
        }
        $params = ['turma_id' => $turmaId, 'inicio' => $inicio, 'fim' => $fim];
        $materiaSql = '';
        if ($materiaId !== null && $materiaId > 0) {
            $materiaSql = ' AND da.materia_id = :materia_id';
            $params['materia_id'] = $materiaId;
        }
        if ($professorId !== null && $professorId > 0) {
            $materiaSql .= ' AND da.professor_id = :professor_id';
            $params['professor_id'] = $professorId;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas
             FROM diario_frequencias df
             INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
             WHERE da.turma_id = :turma_id AND da.status = 'finalizada'
               AND da.data_aula BETWEEN :inicio AND :fim{$materiaSql}",
            $params
        );
        $total = (int) ($row['total'] ?? 0);
        if ($total <= 0) {
            return null;
        }
        return round(((int) ($row['presencas'] ?? 0) / $total) * 100, 1);
    }

    /**
     * Percentual de presença de um aluno (0–100), ou null sem registros.
     */
    public function alunoPercentual(int $alunoId, int $turmaId, string $inicio, string $fim, ?int $materiaId = null): ?float
    {
        if (!$this->schemaPronto() || $alunoId <= 0 || $turmaId <= 0) {
            return null;
        }
        $params = [
            'aluno_id' => $alunoId,
            'turma_id' => $turmaId,
            'inicio' => $inicio,
            'fim' => $fim,
        ];
        $materiaSql = '';
        if ($materiaId !== null && $materiaId > 0) {
            $materiaSql = ' AND da.materia_id = :materia_id';
            $params['materia_id'] = $materiaId;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas
             FROM diario_frequencias df
             INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
             WHERE df.aluno_id = :aluno_id AND da.turma_id = :turma_id AND da.status = 'finalizada'
               AND da.data_aula BETWEEN :inicio AND :fim{$materiaSql}",
            $params
        );
        $total = (int) ($row['total'] ?? 0);
        if ($total <= 0) {
            return null;
        }
        return round(((int) ($row['presencas'] ?? 0) / $total) * 100, 1);
    }

    /**
     * Frequência por aluno da turma no período. Com `$materiaId`, restringe ao
     * diário dessa matéria (turma+matéria) em vez de todas as matérias da turma.
     *
     * @return list<array{aluno_id:int,nome:string,total_aulas:int,presencas:int,faltas:int,faltas_justificadas:int,percentual:?float}>
     */
    public function alunosPercentual(int $turmaId, string $inicio, string $fim, ?int $materiaId = null, ?int $professorId = null): array
    {
        if (!$this->schemaPronto() || $turmaId <= 0) {
            return [];
        }
        $params = ['turma_id' => $turmaId, 'inicio' => $inicio, 'fim' => $fim];
        $materiaSql = '';
        if ($materiaId !== null && $materiaId > 0) {
            $materiaSql = ' AND da.materia_id = :materia_id';
            $params['materia_id'] = $materiaId;
        }
        if ($professorId !== null && $professorId > 0) {
            $materiaSql .= ' AND da.professor_id = :professor_id';
            $params['professor_id'] = $professorId;
        }
        $rows = $this->db->fetchAll(
            "SELECT a.id AS aluno_id, a.nome,
                    COUNT(df.id) AS total_aulas,
                    SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas,
                    SUM(CASE WHEN df.situacao = 'falta' THEN 1 ELSE 0 END) AS faltas,
                    SUM(CASE WHEN df.situacao = 'falta_justificada' THEN 1 ELSE 0 END) AS faltas_justificadas
             FROM diario_aulas da
             INNER JOIN diario_frequencias df ON df.diario_aula_id = da.id
             INNER JOIN alunos a ON a.id = df.aluno_id
             WHERE da.turma_id = :turma_id AND da.status = 'finalizada'
               AND da.data_aula BETWEEN :inicio AND :fim{$materiaSql}
             GROUP BY a.id, a.nome
             ORDER BY a.nome ASC",
            $params
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
     * Frequência de um aluno por componente na turma/período.
     *
     * @return array<int, array{materia_id:int,total_aulas:int,presencas:int,faltas:int,faltas_justificadas:int,percentual:?float}>
     */
    public function alunoPorComponente(int $alunoId, int $turmaId, string $inicio, string $fim): array
    {
        if (!$this->schemaPronto() || $alunoId <= 0 || $turmaId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT da.materia_id,
                    COUNT(df.id) AS total_aulas,
                    SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas,
                    SUM(CASE WHEN df.situacao = 'falta' THEN 1 ELSE 0 END) AS faltas,
                    SUM(CASE WHEN df.situacao = 'falta_justificada' THEN 1 ELSE 0 END) AS faltas_justificadas
             FROM diario_aulas da
             INNER JOIN diario_frequencias df ON df.diario_aula_id = da.id AND df.aluno_id = :aluno_id
             WHERE da.turma_id = :turma_id AND da.status = 'finalizada'
               AND da.data_aula BETWEEN :inicio AND :fim
             GROUP BY da.materia_id",
            [
                'aluno_id' => $alunoId,
                'turma_id' => $turmaId,
                'inicio' => $inicio,
                'fim' => $fim,
            ]
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $mid = (int) ($r['materia_id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            $total = (int) ($r['total_aulas'] ?? 0);
            $presencas = (int) ($r['presencas'] ?? 0);
            $out[$mid] = [
                'materia_id' => $mid,
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
     * Alunos abaixo do mínimo legal no recorte (uma query). Mesma regra de abaixoDoMinimo.
     *
     * @param list<int> $turmaIds
     * @return list<array{aluno_id:int,nome:string,turma_id:int,turma_nome:string,percentual:float}>
     */
    public function abaixoDoMinimoGeral(string $inicio, string $fim, array $turmaIds = [], float $minimo = self::MINIMO_LEGAL, int $limite = 20): array
    {
        if (!$this->schemaPronto()) {
            return [];
        }
        [$turmaSql, $turmaParams] = $this->filtroTurmasSql($turmaIds, 'da.turma_id');
        $limite = max(1, min(50, $limite));
        $rows = $this->db->fetchAll(
            "SELECT a.id AS aluno_id, a.nome, da.turma_id, t.nome AS turma_nome,
                    COUNT(df.id) AS total_aulas,
                    SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas
             FROM diario_aulas da
             INNER JOIN diario_frequencias df ON df.diario_aula_id = da.id
             INNER JOIN alunos a ON a.id = df.aluno_id
             INNER JOIN turmas t ON t.id = da.turma_id
             WHERE da.status = 'finalizada'
               AND da.data_aula BETWEEN :inicio AND :fim{$turmaSql}
             GROUP BY a.id, a.nome, da.turma_id, t.nome
             HAVING total_aulas > 0
                AND (presencas / total_aulas) * 100 < :minimo
             ORDER BY (presencas / total_aulas) ASC, a.nome ASC
             LIMIT {$limite}",
            array_merge(['inicio' => $inicio, 'fim' => $fim, 'minimo' => $minimo], $turmaParams)
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $total = (int) ($r['total_aulas'] ?? 0);
            $presencas = (int) ($r['presencas'] ?? 0);
            $out[] = [
                'aluno_id' => (int) $r['aluno_id'],
                'nome' => (string) $r['nome'],
                'turma_id' => (int) $r['turma_id'],
                'turma_nome' => (string) ($r['turma_nome'] ?? ''),
                'percentual' => $total > 0 ? round(($presencas / $total) * 100, 1) : 0.0,
            ];
        }
        return $out;
    }

    /**
     * @param list<int> $turmaIds
     */
    public function contarAbaixoDoMinimo(string $inicio, string $fim, array $turmaIds = [], float $minimo = self::MINIMO_LEGAL): int
    {
        if (!$this->schemaPronto()) {
            return 0;
        }
        [$turmaSql, $turmaParams] = $this->filtroTurmasSql($turmaIds, 'da.turma_id');
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS n FROM (
                SELECT df.aluno_id
                FROM diario_aulas da
                INNER JOIN diario_frequencias df ON df.diario_aula_id = da.id
                WHERE da.status = 'finalizada'
                  AND da.data_aula BETWEEN :inicio AND :fim{$turmaSql}
                GROUP BY df.aluno_id
                HAVING COUNT(df.id) > 0
                   AND (SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) / COUNT(df.id)) * 100 < :minimo
             ) x",
            array_merge(['inicio' => $inicio, 'fim' => $fim, 'minimo' => $minimo], $turmaParams)
        );
        return (int) ($row['n'] ?? 0);
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

    /**
     * Percentual de presença no recorte (mesma regra de turmaPercentual:
     * presente+atraso em aulas finalizadas). Sem turmas = escola inteira.
     *
     * @param list<int> $turmaIds
     */
    public function percentualGeral(string $inicio, string $fim, array $turmaIds = []): ?float
    {
        if (!$this->schemaPronto()) {
            return null;
        }
        [$turmaSql, $turmaParams] = $this->filtroTurmasSql($turmaIds, 'da.turma_id');
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas
             FROM diario_frequencias df
             INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
             WHERE da.status = 'finalizada'
               AND da.data_aula BETWEEN :inicio AND :fim{$turmaSql}",
            array_merge(['inicio' => $inicio, 'fim' => $fim], $turmaParams)
        );
        $total = (int) ($row['total'] ?? 0);
        if ($total <= 0) {
            return null;
        }
        return round(((int) ($row['presencas'] ?? 0) / $total) * 100, 1);
    }

    /**
     * Consolidado do dia a partir do Diário (fonte pedagógica). Não duplica a Gestão de Presença.
     *
     * @param list<int> $turmaIds
     * @return array{
     *   percentual:?float,
     *   presentes:int,
     *   ausentes:int,
     *   justificadas:int,
     *   total:int,
     *   aulas_finalizadas:int,
     *   chamadas_pendentes:int
     * }
     */
    public function resumoDoDia(string $data, array $turmaIds = []): array
    {
        $vazio = [
            'percentual' => null,
            'presentes' => 0,
            'ausentes' => 0,
            'justificadas' => 0,
            'total' => 0,
            'aulas_finalizadas' => 0,
            'chamadas_pendentes' => 0,
        ];
        if (!$this->schemaPronto() || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return $vazio;
        }
        [$turmaSql, $turmaParams] = $this->filtroTurmasSql($turmaIds, 'da.turma_id');
        $params = array_merge(['data_aula' => $data], $turmaParams);
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN df.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presentes,
                    SUM(CASE WHEN df.situacao = 'falta' THEN 1 ELSE 0 END) AS ausentes,
                    SUM(CASE WHEN df.situacao = 'falta_justificada' THEN 1 ELSE 0 END) AS justificadas
             FROM diario_frequencias df
             INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
             WHERE da.status = 'finalizada' AND da.data_aula = :data_aula{$turmaSql}",
            $params
        );
        $total = (int) ($row['total'] ?? 0);
        $presentes = (int) ($row['presentes'] ?? 0);
        $aulas = $this->db->fetch(
            "SELECT COUNT(*) AS n FROM diario_aulas da
             WHERE da.status = 'finalizada' AND da.data_aula = :data_aula{$turmaSql}",
            $params
        );

        return [
            'percentual' => $total > 0 ? round(($presentes / $total) * 100, 1) : null,
            'presentes' => $presentes,
            'ausentes' => (int) ($row['ausentes'] ?? 0),
            'justificadas' => (int) ($row['justificadas'] ?? 0),
            'total' => $total,
            'aulas_finalizadas' => (int) ($aulas['n'] ?? 0),
            'chamadas_pendentes' => 0,
        ];
    }

    /**
     * @param list<int> $turmaIds
     * @return array{0:string,1:array<string,int>}
     */
    private function filtroTurmasSql(array $turmaIds, string $coluna): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $turmaIds), static fn ($id) => $id > 0)));
        if ($ids === []) {
            return ['', []];
        }
        $params = [];
        $ph = [];
        foreach ($ids as $i => $id) {
            $k = 'freq_turma_' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        return [' AND ' . $coluna . ' IN (' . implode(',', $ph) . ')', $params];
    }
}
