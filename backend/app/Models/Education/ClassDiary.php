<?php

require_once __DIR__ . '/../../Core/Database.php';

class ClassDiary
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function ensureSchema(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS diario_aulas (
                id INT NOT NULL AUTO_INCREMENT,
                grade_horaria_id INT NOT NULL,
                professor_id INT NOT NULL,
                turma_id INT NOT NULL,
                materia_id INT NOT NULL,
                plano_aula_id INT NULL,
                data_aula DATE NOT NULL,
                horario_de TIME NOT NULL,
                horario_ate TIME NOT NULL,
                execucao ENUM('conforme_planejado','parcial','alterado','nao_realizada') NOT NULL DEFAULT 'conforme_planejado',
                conteudo_realizado TEXT NULL,
                observacoes TEXT NULL,
                status ENUM('rascunho','finalizada','cancelada') NOT NULL DEFAULT 'rascunho',
                finalizada_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_diario_grade_data (grade_horaria_id, data_aula),
                KEY idx_diario_prof_data (professor_id, data_aula),
                KEY idx_diario_turma_data (turma_id, data_aula),
                KEY idx_diario_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS diario_frequencias (
                id INT NOT NULL AUTO_INCREMENT,
                diario_aula_id INT NOT NULL,
                aluno_id INT NOT NULL,
                situacao ENUM('presente','falta','falta_justificada','atraso') NOT NULL DEFAULT 'presente',
                observacao VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_diario_aluno (diario_aula_id, aluno_id),
                KEY idx_diario_freq_aluno (aluno_id),
                KEY idx_diario_freq_situacao (situacao),
                CONSTRAINT fk_diario_freq_aula FOREIGN KEY (diario_aula_id) REFERENCES diario_aulas(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function aulasProfessorNoDia(int $professorId, string $data): array
    {
        $diaSemana = (int) date('N', strtotime($data));
        return $this->db->fetchAll(
            "SELECT gh.id AS grade_horaria_id, gh.horario_de, gh.horario_ate,
                    gh.turma_id, gh.materia_id, t.nome AS turma_nome, m.nome AS materia_nome,
                    da.id AS diario_id, da.status, da.execucao, da.plano_aula_id
             FROM grade_horaria gh
             INNER JOIN turmas t ON t.id = gh.turma_id
             INNER JOIN materias m ON m.id = gh.materia_id
             LEFT JOIN diario_aulas da ON da.grade_horaria_id = gh.id AND da.data_aula = :data_aula
             WHERE gh.professor_id = :professor_id AND gh.dia_semana = :dia_semana
             ORDER BY gh.horario_de ASC, t.nome ASC",
            ['data_aula' => $data, 'professor_id' => $professorId, 'dia_semana' => $diaSemana]
        ) ?: [];
    }

    public function getGradeDoProfessor(int $gradeId, int $professorId): ?array
    {
        $row = $this->db->fetch(
            "SELECT gh.*, t.nome AS turma_nome, t.ano_letivo, m.nome AS materia_nome, p.nome AS professor_nome
             FROM grade_horaria gh
             INNER JOIN turmas t ON t.id = gh.turma_id
             INNER JOIN materias m ON m.id = gh.materia_id
             INNER JOIN professores p ON p.id = gh.professor_id
             WHERE gh.id = :id AND gh.professor_id = :professor_id LIMIT 1",
            ['id' => $gradeId, 'professor_id' => $professorId]
        );
        return $row ?: null;
    }

    public function getGrade(int $gradeId): ?array
    {
        $row = $this->db->fetch(
            "SELECT gh.*, t.nome AS turma_nome, t.ano_letivo, m.nome AS materia_nome, p.nome AS professor_nome
             FROM grade_horaria gh
             INNER JOIN turmas t ON t.id = gh.turma_id
             INNER JOIN materias m ON m.id = gh.materia_id
             INNER JOIN professores p ON p.id = gh.professor_id
             WHERE gh.id = :id LIMIT 1",
            ['id' => $gradeId]
        );
        return $row ?: null;
    }

    public function findPlanoParaAula(int $professorId, int $turmaId, int $materiaId, string $data): ?array
    {
        $rows = $this->db->fetchAll(
            "SELECT id, titulo, data_aula, conteudo, objetivos, metodologia, observacoes
             FROM planos_aula
             WHERE professor_id = :professor_id AND turma_id = :turma_id AND materia_id = :materia_id
               AND deleted_at IS NULL
             ORDER BY id DESC",
            ['professor_id' => $professorId, 'turma_id' => $turmaId, 'materia_id' => $materiaId]
        ) ?: [];
        foreach ($rows as $row) {
            $raw = trim((string) ($row['data_aula'] ?? ''));
            $datas = json_decode($raw, true);
            if (!is_array($datas)) {
                $datas = [$raw];
            }
            foreach ($datas as $item) {
                $ts = strtotime((string) $item);
                if ($ts !== false && date('Y-m-d', $ts) === $data) {
                    return $row;
                }
            }
        }
        return null;
    }

    public function getOrCreateAula(array $grade, string $data, ?int $planoId): array
    {
        $existing = $this->db->fetch(
            "SELECT * FROM diario_aulas WHERE grade_horaria_id = :grade_id AND data_aula = :data_aula LIMIT 1",
            ['grade_id' => (int) $grade['id'], 'data_aula' => $data]
        );
        if ($existing) {
            if (empty($existing['plano_aula_id']) && $planoId) {
                $this->db->update("UPDATE diario_aulas SET plano_aula_id = :plano WHERE id = :id", ['plano' => $planoId, 'id' => $existing['id']]);
                $existing['plano_aula_id'] = $planoId;
            }
            return $existing;
        }
        $id = (int) $this->db->insert(
            "INSERT INTO diario_aulas
                (grade_horaria_id, professor_id, turma_id, materia_id, plano_aula_id, data_aula, horario_de, horario_ate)
             VALUES (:grade_id, :professor_id, :turma_id, :materia_id, :plano_id, :data_aula, :horario_de, :horario_ate)",
            [
                'grade_id' => (int) $grade['id'], 'professor_id' => (int) $grade['professor_id'],
                'turma_id' => (int) $grade['turma_id'], 'materia_id' => (int) $grade['materia_id'],
                'plano_id' => $planoId, 'data_aula' => $data,
                'horario_de' => $grade['horario_de'], 'horario_ate' => $grade['horario_ate'],
            ]
        );
        return $this->getAula($id) ?: [];
    }

    public function getAula(int $id): ?array
    {
        $row = $this->db->fetch(
            "SELECT da.*, t.nome AS turma_nome, m.nome AS materia_nome, p.nome AS professor_nome,
                    pa.titulo AS plano_titulo, pa.conteudo AS plano_conteudo,
                    pa.objetivos AS plano_objetivos, pa.metodologia AS plano_metodologia
             FROM diario_aulas da
             INNER JOIN turmas t ON t.id = da.turma_id
             INNER JOIN materias m ON m.id = da.materia_id
             INNER JOIN professores p ON p.id = da.professor_id
             LEFT JOIN planos_aula pa ON pa.id = da.plano_aula_id
             WHERE da.id = :id LIMIT 1",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function frequenciasMap(int $aulaId): array
    {
        $rows = $this->db->fetchAll("SELECT aluno_id, situacao, nota, observacao FROM diario_frequencias WHERE diario_aula_id = :id", ['id' => $aulaId]) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['aluno_id']] = $row;
        }
        return $map;
    }

    public function salvar(int $aulaId, string $execucao, string $conteudo, string $observacoes, array $frequencias, bool $finalizar): void
    {
        $permitidas = ['presente', 'falta', 'falta_justificada', 'atraso'];
        foreach ($frequencias as $alunoId => $payload) {
            $alunoId = (int) $alunoId;
            if ($alunoId <= 0 || !is_array($payload)) continue;
            $situacao = (string) ($payload['situacao'] ?? 'presente');
            if (!in_array($situacao, $permitidas, true)) $situacao = 'presente';
            $obs  = trim((string) ($payload['observacao'] ?? ''));
            $notaRaw = trim((string) ($payload['nota'] ?? ''));
            $nota = $notaRaw !== '' && is_numeric($notaRaw) ? round(max(0, min(10, (float) $notaRaw)), 2) : null;
            $this->db->query(
                "INSERT INTO diario_frequencias (diario_aula_id, aluno_id, situacao, nota, observacao)
                 VALUES (:aula_id, :aluno_id, :situacao, :nota, :observacao)
                 ON DUPLICATE KEY UPDATE situacao = VALUES(situacao), nota = VALUES(nota), observacao = VALUES(observacao), updated_at = CURRENT_TIMESTAMP",
                ['aula_id' => $aulaId, 'aluno_id' => $alunoId, 'situacao' => $situacao, 'nota' => $nota, 'observacao' => $obs !== '' ? $obs : null]
            );
        }
        $status = $finalizar ? ($execucao === 'nao_realizada' ? 'cancelada' : 'finalizada') : 'rascunho';
        $this->db->update(
            "UPDATE diario_aulas SET execucao = :execucao, conteudo_realizado = :conteudo,
                    observacoes = :observacoes, status = :status,
                    finalizada_at = CASE WHEN :finalizar = 1 THEN CURRENT_TIMESTAMP ELSE NULL END
             WHERE id = :id",
            ['execucao' => $execucao, 'conteudo' => $conteudo !== '' ? $conteudo : null,
             'observacoes' => $observacoes !== '' ? $observacoes : null, 'status' => $status,
             'finalizar' => $finalizar ? 1 : 0, 'id' => $aulaId]
        );
    }

    public function acompanhamento(string $inicio, string $fim, int $professorId = 0, string $status = '', int $turmaId = 0): array
    {
        $where = ['da.data_aula BETWEEN :inicio AND :fim'];
        $params = ['inicio' => $inicio, 'fim' => $fim];
        if ($professorId > 0) { $where[] = 'da.professor_id = :professor_id'; $params['professor_id'] = $professorId; }
        if ($turmaId > 0) { $where[] = 'da.turma_id = :turma_id'; $params['turma_id'] = $turmaId; }
        if (in_array($status, ['rascunho', 'finalizada', 'cancelada'], true)) { $where[] = 'da.status = :status'; $params['status'] = $status; }
        return $this->db->fetchAll(
            "SELECT da.*, p.nome AS professor_nome, t.nome AS turma_nome, m.nome AS materia_nome,
                    SUM(CASE WHEN df.situacao = 'falta' THEN 1 ELSE 0 END) AS faltas,
                    SUM(CASE WHEN df.situacao = 'falta_justificada' THEN 1 ELSE 0 END) AS justificadas
             FROM diario_aulas da
             INNER JOIN professores p ON p.id = da.professor_id
             INNER JOIN turmas t ON t.id = da.turma_id
             INNER JOIN materias m ON m.id = da.materia_id
             LEFT JOIN diario_frequencias df ON df.diario_aula_id = da.id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY da.id ORDER BY da.data_aula DESC, da.horario_de ASC",
            $params
        ) ?: [];
    }

    public function aulasPendentes(string $inicio, string $fim, int $professorId = 0, int $turmaId = 0): array
    {
        $start = new DateTime($inicio);
        $end = new DateTime($fim);
        if ($end < $start) return [];
        if ((int) $start->diff($end)->days > 62) $end = (clone $start)->modify('+62 days');
        $out = [];
        for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
            $data = $date->format('Y-m-d');
            $params = ['dia_semana' => (int) $date->format('N'), 'data_aula' => $data];
            $profSql = '';
            $turmaSql = '';
            if ($professorId > 0) { $profSql = ' AND gh.professor_id = :professor_id'; $params['professor_id'] = $professorId; }
            if ($turmaId > 0) { $turmaSql = ' AND gh.turma_id = :turma_id'; $params['turma_id'] = $turmaId; }
            $rows = $this->db->fetchAll(
                "SELECT gh.id AS grade_horaria_id, gh.professor_id, gh.turma_id, gh.materia_id,
                        gh.horario_de, gh.horario_ate, p.nome AS professor_nome,
                        t.nome AS turma_nome, m.nome AS materia_nome
                 FROM grade_horaria gh
                 INNER JOIN professores p ON p.id = gh.professor_id
                 INNER JOIN turmas t ON t.id = gh.turma_id
                 INNER JOIN materias m ON m.id = gh.materia_id
                 LEFT JOIN diario_aulas da ON da.grade_horaria_id = gh.id AND da.data_aula = :data_aula
                 WHERE gh.dia_semana = :dia_semana AND da.id IS NULL{$profSql}{$turmaSql}
                 ORDER BY gh.horario_de",
                $params
            ) ?: [];
            foreach ($rows as $row) {
                $row['data_aula'] = $data;
                $row['status'] = 'pendente';
                $row['faltas'] = 0;
                $row['justificadas'] = 0;
                $out[] = $row;
            }
        }
        usort($out, static fn($a, $b) => strcmp($b['data_aula'] . $b['horario_de'], $a['data_aula'] . $a['horario_de']));
        return $out;
    }

    /**
     * Frequências de uma aula com nome do aluno (para o detalhe no admin).
     * Alunos sem registro aparecem com situacao NULL (chamada não lançada).
     */
    public function frequenciasDetalhadas(int $aulaId, int $turmaId): array
    {
        return $this->db->fetchAll(
            "SELECT a.id AS aluno_id, a.nome, df.situacao, df.nota, df.observacao
             FROM alunos a
             LEFT JOIN diario_frequencias df ON df.diario_aula_id = :aula_id AND df.aluno_id = a.id
             WHERE a.turma_id = :turma_id AND a.ativo = 1
             ORDER BY a.nome ASC",
            ['aula_id' => $aulaId, 'turma_id' => $turmaId]
        ) ?: [];
    }

    /**
     * Indicadores do diário por linha da grade horária (professor × turma × matéria),
     * com semáforo de situação e carga horária prevista vs ministrada.
     *
     * Retorna, por grade: professor, turma, matéria, aulas_previstas (no período),
     * aulas_previstas_ate_hoje, aulas_ministradas (finalizadas), aulas_registradas
     * (qualquer status), pendentes_vencidas, percentual (cobertura), minutos_previstos,
     * minutos_ministrados, ultima_data e situacao ('em_dia'|'atencao'|'atraso').
     *
     * @return list<array<string,mixed>>
     */
    public function indicadores(string $inicio, string $fim, int $professorId = 0): array
    {
        $start = DateTime::createFromFormat('Y-m-d', $inicio);
        $end = DateTime::createFromFormat('Y-m-d', $fim);
        if (!$start || !$end || $end < $start) {
            return [];
        }
        if ((int) $start->diff($end)->days > 200) {
            $end = (clone $start)->modify('+200 days');
        }
        $today = new DateTime(date('Y-m-d'));
        $endAteHoje = $end < $today ? $end : $today;

        // Contagem de cada dia da semana (ISO 1..7) no período total e até hoje.
        $weekdaysTotal = [];
        $weekdaysAteHoje = [];
        for ($iso = 1; $iso <= 7; $iso++) {
            $weekdaysTotal[$iso] = $this->countWeekday($start, $end, $iso);
            $weekdaysAteHoje[$iso] = ($endAteHoje >= $start)
                ? $this->countWeekday($start, $endAteHoje, $iso)
                : 0;
        }

        $params = [];
        $profSql = '';
        if ($professorId > 0) {
            $profSql = ' WHERE gh.professor_id = :professor_id';
            $params['professor_id'] = $professorId;
        }
        $grades = $this->db->fetchAll(
            "SELECT gh.id AS grade_horaria_id, gh.professor_id, gh.turma_id, gh.materia_id,
                    gh.dia_semana, gh.horario_de, gh.horario_ate,
                    p.nome AS professor_nome, t.nome AS turma_nome, m.nome AS materia_nome
             FROM grade_horaria gh
             INNER JOIN professores p ON p.id = gh.professor_id
             INNER JOIN turmas t ON t.id = gh.turma_id
             INNER JOIN materias m ON m.id = gh.materia_id" . $profSql,
            $params
        ) ?: [];

        // Aulas registradas no período agrupadas por grade.
        $registros = $this->db->fetchAll(
            "SELECT grade_horaria_id,
                    COUNT(*) AS registradas,
                    SUM(CASE WHEN status = 'finalizada' THEN 1 ELSE 0 END) AS ministradas,
                    SUM(CASE WHEN status = 'finalizada' THEN TIMESTAMPDIFF(MINUTE, horario_de, horario_ate) ELSE 0 END) AS minutos,
                    MAX(updated_at) AS ultima_data
             FROM diario_aulas
             WHERE data_aula BETWEEN :inicio AND :fim
             GROUP BY grade_horaria_id",
            ['inicio' => $start->format('Y-m-d'), 'fim' => $end->format('Y-m-d')]
        ) ?: [];
        $regMap = [];
        foreach ($registros as $r) {
            $regMap[(int) $r['grade_horaria_id']] = $r;
        }

        $out = [];
        foreach ($grades as $g) {
            $iso = (int) $g['dia_semana'];
            $previstasTotal = $weekdaysTotal[$iso] ?? 0;
            $previstasAteHoje = $weekdaysAteHoje[$iso] ?? 0;
            $reg = $regMap[(int) $g['grade_horaria_id']] ?? null;
            $registradas = $reg ? (int) $reg['registradas'] : 0;
            $ministradas = $reg ? (int) $reg['ministradas'] : 0;
            $minutosMinistrados = $reg ? (int) $reg['minutos'] : 0;
            $ultimaData = $reg ? (string) $reg['ultima_data'] : '';

            $pendentesVencidas = max(0, $previstasAteHoje - $registradas);
            if ($previstasAteHoje === 0 && $registradas === 0) {
                $situacao = 'em_dia';
            } elseif ($pendentesVencidas <= 0) {
                $situacao = 'em_dia';
            } elseif ($pendentesVencidas <= 2) {
                $situacao = 'atencao';
            } else {
                $situacao = 'atraso';
            }

            $minutosAula = max(0, $this->minutosEntre((string) $g['horario_de'], (string) $g['horario_ate']));
            $percentual = $previstasTotal > 0
                ? min(100.0, round(($ministradas / $previstasTotal) * 100, 1))
                : null;

            $out[] = [
                'grade_horaria_id' => (int) $g['grade_horaria_id'],
                'professor_id' => (int) $g['professor_id'],
                'turma_id' => (int) $g['turma_id'],
                'materia_id' => (int) $g['materia_id'],
                'professor_nome' => (string) $g['professor_nome'],
                'turma_nome' => (string) $g['turma_nome'],
                'materia_nome' => (string) $g['materia_nome'],
                'aulas_previstas' => $previstasTotal,
                'aulas_previstas_ate_hoje' => $previstasAteHoje,
                'aulas_ministradas' => $ministradas,
                'aulas_registradas' => $registradas,
                'pendentes_vencidas' => $pendentesVencidas,
                'percentual' => $percentual,
                'minutos_previstos' => $previstasTotal * $minutosAula,
                'minutos_ministrados' => $minutosMinistrados,
                'ultima_data' => $ultimaData,
                'situacao' => $situacao,
            ];
        }

        usort($out, static function ($a, $b) {
            $ordem = ['atraso' => 0, 'atencao' => 1, 'em_dia' => 2];
            $sa = $ordem[$a['situacao']] ?? 3;
            $sb = $ordem[$b['situacao']] ?? 3;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            return strcmp((string) $a['professor_nome'], (string) $b['professor_nome']);
        });

        return $out;
    }

    /**
     * Número de ocorrências de um dia da semana (ISO 1=segunda..7=domingo)
     * dentro do intervalo [start, end] (inclusivo), sem iterar dia a dia.
     */
    private function countWeekday(DateTime $start, DateTime $end, int $iso): int
    {
        if ($end < $start) {
            return 0;
        }
        $totalDays = (int) $start->diff($end)->days + 1;
        $count = intdiv($totalDays, 7);
        $rem = $totalDays % 7;
        $startIso = (int) $start->format('N');
        for ($i = 0; $i < $rem; $i++) {
            $w = (($startIso - 1 + $i) % 7) + 1;
            if ($w === $iso) {
                $count++;
            }
        }
        return $count;
    }

    private function minutosEntre(string $de, string $ate): int
    {
        $a = strtotime('1970-01-01 ' . $de);
        $b = strtotime('1970-01-01 ' . $ate);
        if ($a === false || $b === false || $b <= $a) {
            return 0;
        }
        return (int) round(($b - $a) / 60);
    }
}
