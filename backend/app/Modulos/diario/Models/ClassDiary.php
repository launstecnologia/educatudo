<?php

namespace App\Modulos\Diario\Models;

use Database;
use DateTime;
use RuntimeException;
use Throwable;

class ClassDiary
{
    public const TIPOS_AULA = [
        'regular' => 'Aula regular',
        'avaliacao' => 'Avaliação',
        'revisao' => 'Revisão',
        'recuperacao' => 'Recuperação',
        'atividade' => 'Atividade',
        'projeto' => 'Projeto',
        'evento_escolar' => 'Evento escolar',
        'reposicao' => 'Reposição',
    ];

    private $db;

    /** @var array<string,bool> */
    private $colunasCache = [];

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
                evento_bloco_id INT NULL,
                data_aula DATE NOT NULL,
                horario_de TIME NOT NULL,
                horario_ate TIME NOT NULL,
                execucao ENUM('conforme_planejado','parcial','alterado','nao_realizada') NOT NULL DEFAULT 'conforme_planejado',
                conteudo_realizado TEXT NULL,
                observacoes TEXT NULL,
                tipo_aula ENUM('regular','avaliacao','revisao','recuperacao','atividade','projeto','evento_escolar','reposicao') NOT NULL DEFAULT 'regular',
                status ENUM('rascunho','finalizada','cancelada') NOT NULL DEFAULT 'rascunho',
                finalizada_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_diario_grade_data (grade_horaria_id, data_aula),
                KEY idx_diario_prof_data (professor_id, data_aula),
                KEY idx_diario_turma_data (turma_id, data_aula),
                KEY idx_diario_status (status),
                KEY idx_diario_evento_bloco (evento_bloco_id)
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

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS diario_fechamentos (
                id INT NOT NULL AUTO_INCREMENT,
                turma_id INT NOT NULL,
                materia_id INT NOT NULL,
                professor_id INT NOT NULL,
                ano_letivo SMALLINT UNSIGNED NOT NULL,
                bimestre TINYINT UNSIGNED NOT NULL,
                status ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
                fechado_por INT NULL,
                fechado_em DATETIME NULL,
                reaberto_por INT NULL,
                reaberto_em DATETIME NULL,
                observacoes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_diario_fechamento (turma_id, materia_id, professor_id, ano_letivo, bimestre),
                KEY idx_diario_fechamento_turma (turma_id),
                KEY idx_diario_fechamento_professor (professor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureColunasTipoEvento();
    }

    /**
     * Garante colunas novas em tenants que já tinham diario_aulas (CREATE TABLE IF NOT EXISTS
     * não altera tabela existente). Idempotente.
     */
    private function ensureColunasTipoEvento(): void
    {
        if (!$this->colunaExiste('diario_aulas', 'tipo_aula')) {
            try {
                $this->db->query(
                    "ALTER TABLE diario_aulas
                     ADD COLUMN tipo_aula ENUM('regular','avaliacao','revisao','recuperacao','atividade','projeto','evento_escolar','reposicao')
                     NOT NULL DEFAULT 'regular' AFTER observacoes"
                );
                $this->colunasCache['diario_aulas.tipo_aula'] = true;
            } catch (Throwable $e) {
                error_log('ClassDiary ensureColunasTipoEvento tipo_aula: ' . $e->getMessage());
            }
        }
        if (!$this->colunaExiste('diario_aulas', 'evento_bloco_id')) {
            try {
                $this->db->query(
                    "ALTER TABLE diario_aulas ADD COLUMN evento_bloco_id INT NULL DEFAULT NULL AFTER plano_aula_id"
                );
                $this->colunasCache['diario_aulas.evento_bloco_id'] = true;
            } catch (Throwable $e) {
                error_log('ClassDiary ensureColunasTipoEvento evento_bloco_id: ' . $e->getMessage());
            }
        }
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        $chave = $tabela . '.' . $coluna;
        if (array_key_exists($chave, $this->colunasCache)) {
            return $this->colunasCache[$chave];
        }
        $row = $this->db->fetch(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tabela
               AND COLUMN_NAME = :coluna
             LIMIT 1",
            ['tabela' => $tabela, 'coluna' => $coluna]
        );
        $this->colunasCache[$chave] = (bool) $row;
        return $this->colunasCache[$chave];
    }

    public static function tipoAulaValido(string $tipo): string
    {
        return array_key_exists($tipo, self::TIPOS_AULA) ? $tipo : 'regular';
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
        foreach ($this->planosDoDiario($professorId, $turmaId, $materiaId) as $row) {
            if (in_array($data, $this->datasDoPlano($row), true)) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Planos de aula do professor para aquela turma+componente (módulo existente).
     *
     * @return list<array<string,mixed>>
     */
    public function planosDoDiario(int $professorId, int $turmaId, int $materiaId): array
    {
        return $this->db->fetchAll(
            "SELECT id, titulo, data_aula, status, conteudo, objetivos, metodologia, observacoes, created_at
             FROM planos_aula
             WHERE professor_id = :professor_id AND turma_id = :turma_id AND materia_id = :materia_id
               AND deleted_at IS NULL
             ORDER BY id DESC",
            ['professor_id' => $professorId, 'turma_id' => $turmaId, 'materia_id' => $materiaId]
        ) ?: [];
    }

    public function getPlanoDoDiario(int $planoId, int $professorId, int $turmaId, int $materiaId): ?array
    {
        $row = $this->db->fetch(
            "SELECT id, titulo, data_aula, status
             FROM planos_aula
             WHERE id = :id AND professor_id = :professor_id AND turma_id = :turma_id
               AND materia_id = :materia_id AND deleted_at IS NULL
             LIMIT 1",
            ['id' => $planoId, 'professor_id' => $professorId, 'turma_id' => $turmaId, 'materia_id' => $materiaId]
        );
        return $row ?: null;
    }

    /** @return list<string> datas Y-m-d extraídas de planos_aula.data_aula (JSON ou data única) */
    public function datasDoPlano(array $row): array
    {
        $raw = trim((string) ($row['data_aula'] ?? ''));
        if ($raw === '') {
            return [];
        }
        $datas = json_decode($raw, true);
        if (!is_array($datas)) {
            $datas = [$raw];
        }
        $out = [];
        foreach ($datas as $item) {
            $ts = strtotime((string) $item);
            if ($ts !== false) {
                $out[] = date('Y-m-d', $ts);
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Aulas já vinculadas a um plano neste diário (turma+matéria+professor).
     *
     * @return array<int,list<array{id:int,data_aula:string}>> mapa plano_aula_id => aulas
     */
    public function aulasVinculadasPorPlano(int $turmaId, int $materiaId, int $professorId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id, plano_aula_id, data_aula
             FROM diario_aulas
             WHERE turma_id = :turma_id AND materia_id = :materia_id AND professor_id = :professor_id
               AND plano_aula_id IS NOT NULL",
            ['turma_id' => $turmaId, 'materia_id' => $materiaId, 'professor_id' => $professorId]
        ) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $planoId = (int) $row['plano_aula_id'];
            $map[$planoId][] = [
                'id' => (int) $row['id'],
                'data_aula' => (string) $row['data_aula'],
            ];
        }
        return $map;
    }

    /**
     * Aulas já vinculadas a um evento de prova/nota neste diário.
     *
     * @return array<int,list<array{id:int,data_aula:string}>> mapa evento_bloco_id => aulas
     */
    public function aulasVinculadasPorEvento(int $turmaId, int $materiaId, int $professorId): array
    {
        if (!$this->colunaExiste('diario_aulas', 'evento_bloco_id')) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT id, evento_bloco_id, data_aula
             FROM diario_aulas
             WHERE turma_id = :turma_id AND materia_id = :materia_id AND professor_id = :professor_id
               AND evento_bloco_id IS NOT NULL",
            ['turma_id' => $turmaId, 'materia_id' => $materiaId, 'professor_id' => $professorId]
        ) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $eventoId = (int) $row['evento_bloco_id'];
            $map[$eventoId][] = [
                'id' => (int) $row['id'],
                'data_aula' => (string) $row['data_aula'],
            ];
        }
        return $map;
    }

    public function getOrCreateAula(array $grade, string $data, ?int $planoId): array
    {
        $existing = $this->db->fetch(
            "SELECT * FROM diario_aulas WHERE grade_horaria_id = :grade_id AND data_aula = :data_aula LIMIT 1",
            ['grade_id' => (int) $grade['id'], 'data_aula' => $data]
        );
        if ($existing) {
            return $existing;
        }
        $this->assertPeriodoAbertoDaGrade($grade, $data);
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
        $temEvento = $this->colunaExiste('diario_aulas', 'evento_bloco_id');
        $selectEvento = $temEvento
            ? 'pb.titulo AS evento_titulo, pb.data_prova AS evento_data, pb.liberado AS evento_liberado'
            : 'NULL AS evento_titulo, NULL AS evento_data, NULL AS evento_liberado';
        $joinEvento = $temEvento
            ? 'LEFT JOIN provas_blocos pb ON pb.id = da.evento_bloco_id AND pb.deleted_at IS NULL'
            : '';
        $row = $this->db->fetch(
            "SELECT da.*, t.nome AS turma_nome, m.nome AS materia_nome, p.nome AS professor_nome,
                    pa.titulo AS plano_titulo, pa.conteudo AS plano_conteudo,
                    pa.objetivos AS plano_objetivos, pa.metodologia AS plano_metodologia,
                    {$selectEvento}
             FROM diario_aulas da
             INNER JOIN turmas t ON t.id = da.turma_id
             INNER JOIN materias m ON m.id = da.materia_id
             INNER JOIN professores p ON p.id = da.professor_id
             LEFT JOIN planos_aula pa ON pa.id = da.plano_aula_id
             {$joinEvento}
             WHERE da.id = :id LIMIT 1",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function frequenciasMap(int $aulaId): array
    {
        $rows = $this->db->fetchAll("SELECT aluno_id, situacao, nota, observacao" . ($this->colunaExiste('diario_frequencias', 'origem') ? ', origem' : '') . " FROM diario_frequencias WHERE diario_aula_id = :id", ['id' => $aulaId]) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['aluno_id']] = $row;
        }
        return $map;
    }

    public function salvar(int $aulaId, string $execucao, string $conteudo, string $observacoes, array $frequencias, bool $finalizar, array $extras = []): void
    {
        $this->assertPeriodoAberto($aulaId);

        $permitidas = ['presente', 'falta', 'falta_justificada', 'atraso', 'saida_antecipada'];
        $temOrigem = $this->colunaExiste('diario_frequencias', 'origem');
        foreach ($frequencias as $alunoId => $payload) {
            $alunoId = (int) $alunoId;
            if ($alunoId <= 0 || !is_array($payload)) continue;
            $situacao = (string) ($payload['situacao'] ?? 'presente');
            if (!in_array($situacao, $permitidas, true)) $situacao = 'presente';
            $obs  = trim((string) ($payload['observacao'] ?? ''));
            $notaRaw = trim((string) ($payload['nota'] ?? ''));
            $nota = $notaRaw !== '' && is_numeric($notaRaw) ? round(max(0, min(10, (float) $notaRaw)), 2) : null;
            if ($temOrigem) {
                $this->db->query(
                    "INSERT INTO diario_frequencias (diario_aula_id, aluno_id, situacao, nota, observacao, origem)
                     VALUES (:aula_id, :aluno_id, :situacao, :nota, :observacao, 'manual_diario')
                     ON DUPLICATE KEY UPDATE situacao = VALUES(situacao), nota = VALUES(nota), observacao = VALUES(observacao), origem = 'manual_diario', updated_at = CURRENT_TIMESTAMP",
                    ['aula_id' => $aulaId, 'aluno_id' => $alunoId, 'situacao' => $situacao, 'nota' => $nota, 'observacao' => $obs !== '' ? $obs : null]
                );
            } else {
                $this->db->query(
                    "INSERT INTO diario_frequencias (diario_aula_id, aluno_id, situacao, nota, observacao)
                     VALUES (:aula_id, :aluno_id, :situacao, :nota, :observacao)
                     ON DUPLICATE KEY UPDATE situacao = VALUES(situacao), nota = VALUES(nota), observacao = VALUES(observacao), updated_at = CURRENT_TIMESTAMP",
                    ['aula_id' => $aulaId, 'aluno_id' => $alunoId, 'situacao' => $situacao, 'nota' => $nota, 'observacao' => $obs !== '' ? $obs : null]
                );
            }
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
        $this->atualizarVinculos($aulaId, $extras);
    }

    /**
     * Atualiza tipo/plano/evento da aula sem mexer na chamada. Campos ausentes em
     * `$extras` não são alterados (compatível com POST antigo).
     *
     * @param array{tipo_aula?:string,plano_aula_id?:int|null,evento_bloco_id?:int|null} $extras
     */
    public function atualizarVinculos(int $aulaId, array $extras): void
    {
        if ($extras === []) {
            return;
        }
        $this->assertPeriodoAberto($aulaId);
        $sets = [];
        $params = ['id' => $aulaId];
        if (array_key_exists('tipo_aula', $extras) && $this->colunaExiste('diario_aulas', 'tipo_aula')) {
            $sets[] = 'tipo_aula = :tipo_aula';
            $params['tipo_aula'] = self::tipoAulaValido((string) $extras['tipo_aula']);
        }
        if (array_key_exists('plano_aula_id', $extras)) {
            $planoId = (int) ($extras['plano_aula_id'] ?? 0);
            $sets[] = 'plano_aula_id = :plano_aula_id';
            $params['plano_aula_id'] = $planoId > 0 ? $planoId : null;
        }
        if (array_key_exists('evento_bloco_id', $extras) && $this->colunaExiste('diario_aulas', 'evento_bloco_id')) {
            $eventoId = (int) ($extras['evento_bloco_id'] ?? 0);
            $sets[] = 'evento_bloco_id = :evento_bloco_id';
            $params['evento_bloco_id'] = $eventoId > 0 ? $eventoId : null;
        }
        if ($sets === []) {
            return;
        }
        $this->db->update(
            'UPDATE diario_aulas SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    private function assertPeriodoAberto(int $aulaId): void
    {
        $aula = $this->getAula($aulaId);
        if (!$aula) {
            return;
        }
        $fechamento = $this->fechamentoDaData(
            (int) $aula['turma_id'],
            (int) $aula['materia_id'],
            (int) $aula['professor_id'],
            (string) $aula['data_aula']
        );
        if ($fechamento && (string) $fechamento['status'] === 'fechado') {
            throw new RuntimeException('Este período já foi fechado pela coordenação. Peça a reabertura antes de editar.');
        }
    }

    /**
     * @param array<string,mixed> $grade
     */
    private function assertPeriodoAbertoDaGrade(array $grade, string $data): void
    {
        $fechamento = $this->fechamentoDaData(
            (int) ($grade['turma_id'] ?? 0),
            (int) ($grade['materia_id'] ?? 0),
            (int) ($grade['professor_id'] ?? 0),
            $data
        );
        if ($fechamento && (string) $fechamento['status'] === 'fechado') {
            throw new RuntimeException('Este período já foi fechado pela coordenação. Peça a reabertura antes de editar.');
        }
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

    // ── Diários de Classe (visão agregada por Turma+Componente+Professor) ──────
    // Fase 1 da reestruturação (ver specs/PRD.md §9): um "diário lógico" é o
    // agrupamento das linhas de grade_horaria que compartilham turma+matéria+
    // professor. Não duplica dado — só agrega o que já existe.

    /**
     * IDs de grade_horaria que compõem um diário lógico (turma+matéria+professor).
     * Vazio significa que o professor não leciona essa combinação — usado como
     * checagem de ownership antes de abrir um diário.
     *
     * @return list<int>
     */
    public function gradeIdsDoDiario(int $turmaId, int $materiaId, int $professorId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id FROM grade_horaria WHERE turma_id = :turma_id AND materia_id = :materia_id AND professor_id = :professor_id",
            ['turma_id' => $turmaId, 'materia_id' => $materiaId, 'professor_id' => $professorId]
        ) ?: [];
        return array_map(static fn($r) => (int) $r['id'], $rows);
    }

    /**
     * Aulas de um diário (turma+matéria+professor) no período, mesclando as já
     * registradas em diario_aulas com as previstas na grade ainda sem registro.
     * Cada aula recebe uma situação: futura, pendente, rascunho, finalizada ou
     * nao_realizada. Usado pela aba "Aulas" e para calcular o Resumo/Fechamento.
     *
     * @return list<array<string,mixed>>
     */
    public function aulasDoDiario(int $turmaId, int $materiaId, int $professorId, string $inicio, string $fim): array
    {
        $gradeIds = $this->gradeIdsDoDiario($turmaId, $materiaId, $professorId);
        if (empty($gradeIds)) {
            return [];
        }
        $start = DateTime::createFromFormat('Y-m-d', $inicio);
        $end = DateTime::createFromFormat('Y-m-d', $fim);
        if (!$start || !$end || $end < $start) {
            return [];
        }
        if ((int) $start->diff($end)->days > 200) {
            $end = (clone $start)->modify('+200 days');
        }
        $today = new DateTime(date('Y-m-d'));

        $placeholders = implode(',', array_fill(0, count($gradeIds), '?'));
        $registradas = $this->db->fetchAll(
            "SELECT * FROM diario_aulas WHERE grade_horaria_id IN ($placeholders) AND data_aula BETWEEN ? AND ?",
            array_merge($gradeIds, [$start->format('Y-m-d'), $end->format('Y-m-d')])
        ) ?: [];
        $porGradeData = [];
        foreach ($registradas as $r) {
            $porGradeData[(int) $r['grade_horaria_id'] . '|' . $r['data_aula']] = $r;
        }

        $grades = $this->db->fetchAll(
            "SELECT id, dia_semana, horario_de, horario_ate FROM grade_horaria WHERE id IN ($placeholders)",
            $gradeIds
        ) ?: [];

        $out = [];
        foreach ($grades as $g) {
            $diaSemanaIso = (int) $g['dia_semana'];
            for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
                if ((int) $date->format('N') !== $diaSemanaIso) {
                    continue;
                }
                $data = $date->format('Y-m-d');
                $registro = $porGradeData[(int) $g['id'] . '|' . $data] ?? null;
                if ($registro) {
                    $status = (string) $registro['status'];
                    $situacao = $status === 'finalizada' ? 'finalizada' : ($status === 'cancelada' ? 'nao_realizada' : 'rascunho');
                } else {
                    $situacao = $date > $today ? 'futura' : 'pendente';
                }
                $out[] = [
                    'grade_horaria_id' => (int) $g['id'],
                    'data_aula' => $data,
                    'horario_de' => $g['horario_de'],
                    'horario_ate' => $g['horario_ate'],
                    'diario_aula_id' => $registro ? (int) $registro['id'] : null,
                    'plano_aula_id' => $registro['plano_aula_id'] ?? null,
                    'evento_bloco_id' => $registro['evento_bloco_id'] ?? null,
                    'tipo_aula' => $registro['tipo_aula'] ?? 'regular',
                    'conteudo_realizado' => $registro['conteudo_realizado'] ?? null,
                    'situacao' => $situacao,
                ];
            }
        }
        usort($out, static fn($a, $b) => strcmp($a['data_aula'] . $a['horario_de'], $b['data_aula'] . $b['horario_de']));
        return $out;
    }

    /**
     * Indicadores agregados de um diário (turma+matéria+professor) para a aba
     * Resumo e para o Fechamento. Situação usa o mesmo limiar de `indicadores()`
     * (até 2 chamadas pendentes vencidas = atenção; acima = atraso).
     *
     * @return array{aulas_previstas:int,aulas_previstas_ate_hoje:int,aulas_finalizadas:int,aulas_registradas:int,chamadas_pendentes:int,situacao:string}
     */
    public function resumoDiario(int $turmaId, int $materiaId, int $professorId, string $inicio, string $fim): array
    {
        $aulas = $this->aulasDoDiario($turmaId, $materiaId, $professorId, $inicio, $fim);
        $hoje = date('Y-m-d');
        $previstasAteHoje = 0;
        $finalizadas = 0;
        $registradas = 0;
        $pendentes = 0;
        foreach ($aulas as $a) {
            if ($a['data_aula'] <= $hoje) {
                $previstasAteHoje++;
            }
            if ($a['situacao'] === 'finalizada') {
                $finalizadas++;
                $registradas++;
            } elseif (in_array($a['situacao'], ['rascunho', 'nao_realizada'], true)) {
                $registradas++;
            } elseif ($a['situacao'] === 'pendente') {
                $pendentes++;
            }
        }
        if ($previstasAteHoje === 0 && $registradas === 0) {
            $situacao = 'em_dia';
        } elseif ($pendentes <= 0) {
            $situacao = 'em_dia';
        } elseif ($pendentes <= 2) {
            $situacao = 'atencao';
        } else {
            $situacao = 'atraso';
        }
        return [
            'aulas_previstas' => count($aulas),
            'aulas_previstas_ate_hoje' => $previstasAteHoje,
            'aulas_finalizadas' => $finalizadas,
            'aulas_registradas' => $registradas,
            'chamadas_pendentes' => $pendentes,
            'situacao' => $situacao,
        ];
    }

    /**
     * Diários de Classe de um professor, agrupados por Turma+Componente
     * Curricular (diário lógico), com indicadores agregados. Alimenta a tela
     * "Diários de Classe".
     *
     * @param array{professor_id:int,inicio:string,fim:string,ano_letivo?:int,turma_id?:int,materia_id?:int,situacao?:string} $filtros
     * @return list<array<string,mixed>>
     */
    public function diarios(array $filtros): array
    {
        $professorId = (int) ($filtros['professor_id'] ?? 0);
        $inicio = (string) ($filtros['inicio'] ?? '');
        $fim = (string) ($filtros['fim'] ?? '');
        if ($professorId <= 0 || $inicio === '' || $fim === '') {
            return [];
        }

        $where = ['gh.professor_id = :professor_id'];
        $params = ['professor_id' => $professorId];
        $anoLetivo = (int) ($filtros['ano_letivo'] ?? 0);
        if ($anoLetivo > 0) { $where[] = 't.ano_letivo = :ano_letivo'; $params['ano_letivo'] = $anoLetivo; }
        $turmaId = (int) ($filtros['turma_id'] ?? 0);
        if ($turmaId > 0) { $where[] = 'gh.turma_id = :turma_id'; $params['turma_id'] = $turmaId; }
        $materiaId = (int) ($filtros['materia_id'] ?? 0);
        if ($materiaId > 0) { $where[] = 'gh.materia_id = :materia_id'; $params['materia_id'] = $materiaId; }

        $combos = $this->db->fetchAll(
            "SELECT DISTINCT gh.turma_id, gh.materia_id, t.nome AS turma_nome, t.ano_letivo, m.nome AS materia_nome
             FROM grade_horaria gh
             INNER JOIN turmas t ON t.id = gh.turma_id
             INNER JOIN materias m ON m.id = gh.materia_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY t.nome ASC, m.nome ASC",
            $params
        ) ?: [];

        $situacaoFiltro = (string) ($filtros['situacao'] ?? '');
        $out = [];
        foreach ($combos as $c) {
            $resumo = $this->resumoDiario((int) $c['turma_id'], (int) $c['materia_id'], $professorId, $inicio, $fim);
            if ($situacaoFiltro !== '' && $resumo['situacao'] !== $situacaoFiltro) {
                continue;
            }
            $out[] = array_merge([
                'turma_id' => (int) $c['turma_id'],
                'turma_nome' => (string) $c['turma_nome'],
                'ano_letivo' => (int) $c['ano_letivo'],
                'materia_id' => (int) $c['materia_id'],
                'materia_nome' => (string) $c['materia_nome'],
                'professor_id' => $professorId,
            ], $resumo);
        }
        return $out;
    }

    /**
     * Nomes básicos (turma/matéria/professor) de um diário lógico, para o
     * cabeçalho da tela interna. Retorna null se a combinação não existir na
     * grade (mesma checagem de `gradeIdsDoDiario`).
     */
    public function infoDiario(int $turmaId, int $materiaId, int $professorId): ?array
    {
        $row = $this->db->fetch(
            "SELECT t.id AS turma_id, t.nome AS turma_nome, t.ano_letivo,
                    m.id AS materia_id, m.nome AS materia_nome,
                    p.id AS professor_id, p.nome AS professor_nome
             FROM grade_horaria gh
             INNER JOIN turmas t ON t.id = gh.turma_id
             INNER JOIN materias m ON m.id = gh.materia_id
             INNER JOIN professores p ON p.id = gh.professor_id
             WHERE gh.turma_id = :turma_id AND gh.materia_id = :materia_id AND gh.professor_id = :professor_id
             LIMIT 1",
            ['turma_id' => $turmaId, 'materia_id' => $materiaId, 'professor_id' => $professorId]
        );
        return $row ?: null;
    }

    /** @return list<array{id:int,nome:string}> */
    public function turmasDoProfessor(int $professorId): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT t.id, t.nome FROM grade_horaria gh
             INNER JOIN turmas t ON t.id = gh.turma_id
             WHERE gh.professor_id = :professor_id ORDER BY t.nome",
            ['professor_id' => $professorId]
        ) ?: [];
    }

    /** @return list<array{id:int,nome:string}> */
    public function materiasDoProfessor(int $professorId): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT m.id, m.nome FROM grade_horaria gh
             INNER JOIN materias m ON m.id = gh.materia_id
             WHERE gh.professor_id = :professor_id ORDER BY m.nome",
            ['professor_id' => $professorId]
        ) ?: [];
    }

    /** @return list<int> */
    public function anosLetivosDoProfessor(int $professorId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT t.ano_letivo FROM grade_horaria gh
             INNER JOIN turmas t ON t.id = gh.turma_id
             WHERE gh.professor_id = :professor_id ORDER BY t.ano_letivo DESC",
            ['professor_id' => $professorId]
        ) ?: [];
        return array_map(static fn($r) => (int) $r['ano_letivo'], $rows);
    }

    // ── Fechamento por período (diario_fechamentos) ─────────────────────────

    /**
     * Intervalo de datas de um bimestre. Fase 1 não integra com o Calendário
     * Letivo (ver specs/PRD.md §9) — usa o trimestre do calendário civil como
     * convenção fixa e ÚNICA fonte de verdade do período (nunca aceitar
     * inicio/fim vindos do cliente para decidir se um bimestre pode fechar ou
     * se uma data está dentro de um bimestre fechado).
     *
     * @return array{inicio:string,fim:string}
     */
    public function periodoDoBimestre(int $anoLetivo, int $bimestre): array
    {
        $bimestre = max(1, min(4, $bimestre));
        $mesInicio = ($bimestre - 1) * 3 + 1;
        $mesFim = $mesInicio + 2;
        $inicio = sprintf('%04d-%02d-01', $anoLetivo, $mesInicio);
        $fim = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anoLetivo, $mesFim)));
        return ['inicio' => $inicio, 'fim' => $fim];
    }

    /** Bimestre (1-4) correspondente a uma data, pela convenção de `periodoDoBimestre()`. */
    public function bimestreDaData(string $data): int
    {
        $mes = (int) date('n', strtotime($data));
        return (int) ceil($mes / 3);
    }

    /**
     * Fechamento (se existir) do bimestre a que uma data pertence, para o
     * diário turma+matéria+professor. Usado como trava em `salvar()`.
     */
    public function fechamentoDaData(int $turmaId, int $materiaId, int $professorId, string $data): ?array
    {
        $anoLetivo = (int) date('Y', strtotime($data));
        return $this->getFechamento($turmaId, $materiaId, $professorId, $anoLetivo, $this->bimestreDaData($data));
    }

    public function getFechamento(int $turmaId, int $materiaId, int $professorId, int $anoLetivo, int $bimestre): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM diario_fechamentos
             WHERE turma_id = :turma_id AND materia_id = :materia_id AND professor_id = :professor_id
               AND ano_letivo = :ano_letivo AND bimestre = :bimestre LIMIT 1",
            ['turma_id' => $turmaId, 'materia_id' => $materiaId, 'professor_id' => $professorId,
             'ano_letivo' => $anoLetivo, 'bimestre' => $bimestre]
        );
        return $row ?: null;
    }

    public function fechar(int $turmaId, int $materiaId, int $professorId, int $anoLetivo, int $bimestre, int $usuarioId): void
    {
        // INSERT ... ON DUPLICATE KEY UPDATE evita corrida entre select+insert/update
        // (dois fechamentos simultâneos do mesmo bimestre não colidem na UNIQUE KEY).
        $this->db->query(
            "INSERT INTO diario_fechamentos (turma_id, materia_id, professor_id, ano_letivo, bimestre, status, fechado_por, fechado_em)
             VALUES (:turma_id, :materia_id, :professor_id, :ano_letivo, :bimestre, 'fechado', :usuario, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE status = 'fechado', fechado_por = VALUES(fechado_por), fechado_em = VALUES(fechado_em),
                                     reaberto_por = NULL, reaberto_em = NULL",
            ['turma_id' => $turmaId, 'materia_id' => $materiaId, 'professor_id' => $professorId,
             'ano_letivo' => $anoLetivo, 'bimestre' => $bimestre, 'usuario' => $usuarioId]
        );
    }

    public function getFechamentoById(int $id): ?array
    {
        $row = $this->db->fetch(
            "SELECT df.*, t.nome AS turma_nome, m.nome AS materia_nome, p.nome AS professor_nome
             FROM diario_fechamentos df
             LEFT JOIN turmas t ON t.id = df.turma_id
             LEFT JOIN materias m ON m.id = df.materia_id
             LEFT JOIN professores p ON p.id = df.professor_id
             WHERE df.id = :id LIMIT 1",
            ['id' => $id]
        );
        return $row ?: null;
    }

    /**
     * Diários fechados (para a coordenação visualizar ou reabrir).
     *
     * @return list<array<string,mixed>>
     */
    public function fechamentosFechados(int $professorId = 0, int $turmaId = 0): array
    {
        $where = ['df.status = :status'];
        $params = ['status' => 'fechado'];
        if ($professorId > 0) {
            $where[] = 'df.professor_id = :professor_id';
            $params['professor_id'] = $professorId;
        }
        if ($turmaId > 0) {
            $where[] = 'df.turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        }
        return $this->db->fetchAll(
            "SELECT df.*, t.nome AS turma_nome, m.nome AS materia_nome, p.nome AS professor_nome
             FROM diario_fechamentos df
             INNER JOIN turmas t ON t.id = df.turma_id
             INNER JOIN materias m ON m.id = df.materia_id
             INNER JOIN professores p ON p.id = df.professor_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY df.fechado_em DESC",
            $params
        ) ?: [];
    }

    public function reabrir(int $fechamentoId, int $usuarioId): void
    {
        $this->db->update(
            "UPDATE diario_fechamentos SET status = 'aberto', reaberto_por = :usuario, reaberto_em = CURRENT_TIMESTAMP WHERE id = :id",
            ['usuario' => $usuarioId, 'id' => $fechamentoId]
        );
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
