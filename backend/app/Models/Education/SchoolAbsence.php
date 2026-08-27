<?php

require_once __DIR__ . '/../../Core/Database.php';

class SchoolAbsence
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function ensureSchema(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS faltas_eventos (
                id INT NOT NULL AUTO_INCREMENT,
                nome VARCHAR(150) NOT NULL,
                bimestre VARCHAR(20) NOT NULL,
                ano_letivo INT NOT NULL,
                turmas_json TEXT NOT NULL,
                created_by INT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_faltas_eventos_ano (ano_letivo),
                KEY idx_faltas_eventos_ativo (ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureFaltasEventosMateriasJsonColumn();
        $this->ensureFaltasEventosOrigemColumn();

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS faltas_lancamentos (
                id INT NOT NULL AUTO_INCREMENT,
                evento_id INT NOT NULL,
                aluno_id INT NOT NULL,
                materia_id INT NOT NULL DEFAULT 0,
                faltas DECIMAL(6,2) NOT NULL DEFAULT 0,
                observacao VARCHAR(255) NULL,
                created_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_faltas_evento_aluno_materia (evento_id, aluno_id, materia_id),
                KEY idx_faltas_lanc_aluno (aluno_id),
                KEY idx_faltas_lanc_materia (materia_id),
                CONSTRAINT fk_faltas_lanc_evento FOREIGN KEY (evento_id) REFERENCES faltas_eventos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function ensureFaltasEventosMateriasJsonColumn(): void
    {
        $exists = $this->db->fetch(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'faltas_eventos'
               AND COLUMN_NAME = 'materias_json'
             LIMIT 1"
        );
        if ($exists) {
            return;
        }
        try {
            $this->db->query(
                "ALTER TABLE faltas_eventos ADD COLUMN materias_json TEXT NULL COMMENT 'IDs das matérias em colunas; NULL = grade horária' AFTER turmas_json"
            );
        } catch (Throwable $e) {
            error_log('SchoolAbsence ensureFaltasEventosMateriasJsonColumn: ' . $e->getMessage());
        }
    }

    private function ensureFaltasEventosOrigemColumn(): void
    {
        if ($this->colunaOrigem()) {
            return;
        }
        try {
            $this->db->query(
                "ALTER TABLE faltas_eventos ADD COLUMN origem ENUM('manual','diario') NOT NULL DEFAULT 'manual' AFTER materias_json"
            );
        } catch (Throwable $e) {
            error_log('SchoolAbsence ensureFaltasEventosOrigemColumn: ' . $e->getMessage());
        }
    }

    private function colunaOrigem(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $exists = $this->db->fetch(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faltas_eventos' AND COLUMN_NAME = 'origem' LIMIT 1"
        );
        $ok = (bool) $exists;
        return $ok;
    }

    private function origemValida(string $origem): string
    {
        return $origem === 'diario' ? 'diario' : 'manual';
    }

    public function listEventos(int $limit = 200): array
    {
        $useLimit = $limit > 0;
        $limit = $useLimit ? max(1, min($limit, 1000)) : 0;
        $origemSql = $this->colunaOrigem() ? ', origem' : '';
        $sql = "SELECT id, nome, bimestre, ano_letivo, turmas_json, materias_json{$origemSql}, created_at, updated_at
                FROM faltas_eventos
                WHERE ativo = 1
                ORDER BY ano_letivo DESC, updated_at DESC, id DESC";
        if ($useLimit) {
            $sql .= ' LIMIT ' . $limit;
        }
        $rows = $this->db->fetchAll(
            $sql
        ) ?: [];

        foreach ($rows as &$row) {
            $row['turmas_ids'] = $this->decodeIds((string) ($row['turmas_json'] ?? ''));
            $row['materias_ids'] = $this->decodeIds((string) ($row['materias_json'] ?? ''));
            $row['origem'] = (string) ($row['origem'] ?? 'manual');
        }
        unset($row);

        return $rows;
    }

    public function getEventoById(int $eventoId): ?array
    {
        if ($eventoId <= 0) {
            return null;
        }
        $origemSql = $this->colunaOrigem() ? ', origem' : '';
        $row = $this->db->fetch(
            "SELECT id, nome, bimestre, ano_letivo, turmas_json, materias_json{$origemSql}, created_at, updated_at
             FROM faltas_eventos
             WHERE id = :id AND ativo = 1
             LIMIT 1",
            ['id' => $eventoId]
        );
        if (!$row) {
            return null;
        }
        $row['turmas_ids'] = $this->decodeIds((string) ($row['turmas_json'] ?? ''));
        $row['materias_ids'] = $this->decodeIds((string) ($row['materias_json'] ?? ''));
        $row['origem'] = (string) ($row['origem'] ?? 'manual');

        return $row;
    }

    public function createEvento(string $nome, string $bimestre, int $anoLetivo, array $turmasIds, ?int $createdBy = null, array $materiasIds = [], string $origem = 'manual'): int
    {
        $ids = $this->normalizeIds($turmasIds);
        if ($ids === []) {
            throw new RuntimeException('Selecione ao menos uma turma.');
        }

        $materiasNorm = $this->normalizeIds($materiasIds);
        $materiasJson = $materiasNorm === [] ? null : json_encode($materiasNorm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $origem = $this->origemValida($origem);

        if ($this->colunaOrigem()) {
            return (int) $this->db->insert(
                "INSERT INTO faltas_eventos (nome, bimestre, ano_letivo, turmas_json, materias_json, origem, created_by, ativo)
                 VALUES (:nome, :bimestre, :ano_letivo, :turmas_json, :materias_json, :origem, :created_by, 1)",
                [
                    'nome' => trim($nome),
                    'bimestre' => trim($bimestre),
                    'ano_letivo' => $anoLetivo,
                    'turmas_json' => json_encode($ids, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'materias_json' => $materiasJson,
                    'origem' => $origem,
                    'created_by' => $createdBy,
                ]
            );
        }

        return (int) $this->db->insert(
            "INSERT INTO faltas_eventos (nome, bimestre, ano_letivo, turmas_json, materias_json, created_by, ativo)
             VALUES (:nome, :bimestre, :ano_letivo, :turmas_json, :materias_json, :created_by, 1)",
            [
                'nome' => trim($nome),
                'bimestre' => trim($bimestre),
                'ano_letivo' => $anoLetivo,
                'turmas_json' => json_encode($ids, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'materias_json' => $materiasJson,
                'created_by' => $createdBy,
            ]
        );
    }

    public function updateEvento(
        int $eventoId,
        string $nome,
        string $bimestre,
        int $anoLetivo,
        array $turmasIds,
        array $materiasIds,
        string $origem = 'manual'
    ): void {
        if ($eventoId <= 0) {
            throw new RuntimeException('Evento inválido.');
        }
        if (!$this->getEventoById($eventoId)) {
            throw new RuntimeException('Evento não encontrado.');
        }
        $ids = $this->normalizeIds($turmasIds);
        if ($ids === []) {
            throw new RuntimeException('Selecione ao menos uma turma.');
        }
        $materiasNorm = $this->normalizeIds($materiasIds);
        $materiasJson = $materiasNorm === [] ? null : json_encode($materiasNorm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $params = [
            'id' => $eventoId,
            'nome' => trim($nome),
            'bimestre' => trim($bimestre),
            'ano_letivo' => $anoLetivo,
            'turmas_json' => json_encode($ids, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'materias_json' => $materiasJson,
        ];
        $origemSql = '';
        if ($this->colunaOrigem()) {
            $origemSql = ', origem = :origem';
            $params['origem'] = $this->origemValida($origem);
        }

        $this->db->update(
            "UPDATE faltas_eventos
             SET nome = :nome, bimestre = :bimestre, ano_letivo = :ano_letivo, turmas_json = :turmas_json, materias_json = :materias_json{$origemSql}, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND ativo = 1",
            $params
        );
    }

    /**
     * Matérias cadastradas na escola (para compor colunas do evento / totais do bimestre).
     */
    public function listMateriasCadastradas(int $limit = 500): array
    {
        $limit = max(1, min($limit, 2000));
        return $this->db->fetchAll(
            "SELECT m.id, m.nome
             FROM materias m
             ORDER BY m.nome ASC
             LIMIT {$limit}"
        ) ?: [];
    }

    /**
     * Dados de matéria na ordem dos IDs informados (colunas da matriz).
     */
    public function listMateriasByIds(array $materiaIds): array
    {
        $ids = $this->normalizeIds($materiaIds);
        if ($ids === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(
            "SELECT m.id, m.nome
             FROM materias m
             WHERE m.id IN ($ph)",
            $ids
        ) ?: [];
        $byId = [];
        foreach ($rows as $r) {
            $mid = (int) ($r['id'] ?? 0);
            if ($mid > 0) {
                $byId[$mid] = $r;
            }
        }
        $out = [];
        foreach ($ids as $mid) {
            if (isset($byId[$mid])) {
                $out[] = $byId[$mid];
            }
        }

        return $out;
    }

    public function listTurmasAtivas(): array
    {
        return $this->db->fetchAll(
            "SELECT t.id, t.nome, t.serie_id, s.nome AS serie_nome
             FROM turmas t
             LEFT JOIN serie s ON s.id = t.serie_id
             ORDER BY s.nome ASC, t.nome ASC"
        ) ?: [];
    }

    /**
     * Alunos ativos das turmas. Quando existir lista de chamada (migration 059),
     * ordena pelo número de chamada da turma no ano letivo do evento (ou da turma).
     *
     * @param int $anoLetivoEvento ano numérico do evento de faltas (ex.: 2026); 0 = usa ano da turma
     */
    public function listAlunosByTurmas(array $turmasIds, int $anoLetivoEvento = 0): array
    {
        $ids = $this->normalizeIds($turmasIds);
        if ($ids === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $hasChamada = $this->db->fetch("SHOW TABLES LIKE 'alunos_turma_chamada'");
        if ($hasChamada !== false) {
            $params = array_merge([$anoLetivoEvento, $anoLetivoEvento], $ids);

            return $this->db->fetchAll(
                "SELECT a.id, a.nome, a.turma_id, t.nome AS turma_nome, c.numero_chamada
                 FROM alunos a
                 INNER JOIN turmas t ON t.id = a.turma_id
                 LEFT JOIN alunos_turma_chamada c ON c.aluno_id = a.id AND c.turma_id = a.turma_id
                   AND c.ano_letivo_id = COALESCE(
                     (SELECT al.id FROM ano_letivo al
                      WHERE al.ano = IF(? > 0, ?, t.ano_letivo)
                      ORDER BY al.id DESC LIMIT 1),
                     (SELECT al2.id FROM ano_letivo al2 ORDER BY al2.ano DESC LIMIT 1)
                   )
                 WHERE a.ativo = 1 AND a.turma_id IN ($ph)
                 ORDER BY t.nome ASC, COALESCE(c.numero_chamada, 99999) ASC, a.nome ASC",
                $params
            ) ?: [];
        }

        return $this->db->fetchAll(
            "SELECT a.id, a.nome, a.turma_id, t.nome AS turma_nome, NULL AS numero_chamada
             FROM alunos a
             INNER JOIN turmas t ON t.id = a.turma_id
             WHERE a.ativo = 1 AND a.turma_id IN ($ph)
             ORDER BY t.nome ASC, a.nome ASC",
            $ids
        ) ?: [];
    }

    /**
     * Matérias distintas da grade horária da turma (para lançamento de faltas por matéria).
     */
    public function listMateriasPorTurma(int $turmaId): array
    {
        if ($turmaId <= 0) {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT DISTINCT m.id, m.nome
             FROM grade_horaria gh
             INNER JOIN materias m ON m.id = gh.materia_id
             WHERE gh.turma_id = :turma_id
             ORDER BY m.nome ASC",
            ['turma_id' => $turmaId]
        ) ?: [];
    }

    public static function lancamentoMapKey(int $alunoId, int $materiaId): string
    {
        return $alunoId . '_' . $materiaId;
    }

    /**
     * Mapa por chave "alunoId_materiaId" => faltas / observação.
     */
    public function getLancamentosMapByEvento(int $eventoId): array
    {
        if ($eventoId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT aluno_id, materia_id, faltas, observacao
             FROM faltas_lancamentos
             WHERE evento_id = :evento_id",
            ['evento_id' => $eventoId]
        ) ?: [];
        $map = [];
        foreach ($rows as $r) {
            $aid = (int) ($r['aluno_id'] ?? 0);
            $mid = (int) ($r['materia_id'] ?? 0);
            if ($aid <= 0) {
                continue;
            }
            $map[self::lancamentoMapKey($aid, $mid)] = [
                'faltas' => (float) ($r['faltas'] ?? 0),
                'observacao' => (string) ($r['observacao'] ?? ''),
            ];
        }

        return $map;
    }

    /**
     * Total de faltas por aluno no evento (boletim).
     * Se existir lançamento por matéria (materia_id > 0), soma só esses; senão usa materia_id = 0 (legado).
     */
    public function getTotalFaltasPorAlunoNoEvento(int $eventoId): array
    {
        if ($eventoId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT aluno_id,
                    CASE
                        WHEN SUM(CASE WHEN materia_id > 0 THEN 1 ELSE 0 END) > 0
                            THEN SUM(CASE WHEN materia_id > 0 THEN faltas ELSE 0 END)
                        ELSE COALESCE(SUM(CASE WHEN materia_id = 0 THEN faltas ELSE 0 END), 0)
                    END AS total_faltas
             FROM faltas_lancamentos
             WHERE evento_id = :evento_id
             GROUP BY aluno_id",
            ['evento_id' => $eventoId]
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $aid = (int) ($r['aluno_id'] ?? 0);
            if ($aid <= 0) {
                continue;
            }
            $out[$aid] = (float) ($r['total_faltas'] ?? 0);
        }

        return $out;
    }

    /**
     * Evento de faltas mais recente do ano/bimestre (1–4). 0 se não houver.
     */
    public function idEventoPorAnoBimestre(int $anoLetivo, int $bimestre): int
    {
        if ($anoLetivo < 2000 || $bimestre < 1 || $bimestre > 4) {
            return 0;
        }
        foreach ($this->listEventos(300) as $ev) {
            if ((int) ($ev['ano_letivo'] ?? 0) !== $anoLetivo) {
                continue;
            }
            $rotulo = (string) ($ev['bimestre'] ?? '');
            if (preg_match('/([1-4])/', $rotulo, $m) && (int) $m[1] === $bimestre) {
                return (int) ($ev['id'] ?? 0);
            }
        }

        return 0;
    }

    public function deleteEvento(int $eventoId): void
    {
        if ($eventoId <= 0) {
            return;
        }
        $this->db->update(
            "UPDATE faltas_eventos SET ativo = 0 WHERE id = :id",
            ['id' => $eventoId]
        );
    }

    public function upsertLancamentos(int $eventoId, array $faltasPorAluno, array $obsPorAluno = [], ?int $createdBy = null): void
    {
        if ($eventoId <= 0) {
            return;
        }
        foreach ($faltasPorAluno as $alunoIdRaw => $faltasPayload) {
            $alunoId = (int) $alunoIdRaw;
            if ($alunoId <= 0) {
                continue;
            }

            if (is_array($faltasPayload)) {
                foreach ($faltasPayload as $materiaIdRaw => $faltasRaw) {
                    $materiaId = (int) $materiaIdRaw;
                    if ($materiaId < 0) {
                        continue;
                    }
                    $faltasTxt = trim((string) $faltasRaw);
                    $obsNested = $obsPorAluno[$alunoId] ?? [];
                    $obs = '';
                    if (is_array($obsNested)) {
                        if (array_key_exists($materiaId, $obsNested)) {
                            $obs = trim((string) $obsNested[$materiaId]);
                        } elseif (array_key_exists((string) $materiaId, $obsNested)) {
                            $obs = trim((string) $obsNested[(string) $materiaId]);
                        }
                    } else {
                        $obs = trim((string) $obsNested);
                    }

                    $this->upsertUmLancamento($eventoId, $alunoId, $materiaId, $faltasTxt, $obs, $createdBy);
                }
                continue;
            }

            $faltasTxt = trim((string) $faltasPayload);
            $obs = trim((string) ($obsPorAluno[$alunoId] ?? ''));
            $this->upsertUmLancamento($eventoId, $alunoId, 0, $faltasTxt, $obs, $createdBy);
        }
    }

    private function upsertUmLancamento(
        int $eventoId,
        int $alunoId,
        int $materiaId,
        string $faltasTxt,
        string $obs,
        ?int $createdBy
    ): void {
        if ($faltasTxt === '' && $obs === '') {
            $this->db->delete(
                "DELETE FROM faltas_lancamentos WHERE evento_id = :evento_id AND aluno_id = :aluno_id AND materia_id = :materia_id",
                ['evento_id' => $eventoId, 'aluno_id' => $alunoId, 'materia_id' => $materiaId]
            );

            return;
        }

        $faltasNorm = str_replace(',', '.', $faltasTxt);
        $faltas = is_numeric($faltasNorm) ? (float) $faltasNorm : 0.0;
        if ($faltas < 0) {
            $faltas = 0.0;
        }

        $this->db->query(
            "INSERT INTO faltas_lancamentos (evento_id, aluno_id, materia_id, faltas, observacao, created_by)
             VALUES (:evento_id, :aluno_id, :materia_id, :faltas, :observacao, :created_by)
             ON DUPLICATE KEY UPDATE faltas = VALUES(faltas), observacao = VALUES(observacao), updated_at = CURRENT_TIMESTAMP",
            [
                'evento_id' => $eventoId,
                'aluno_id' => $alunoId,
                'materia_id' => $materiaId,
                'faltas' => $faltas,
                'observacao' => $obs !== '' ? $obs : null,
                'created_by' => $createdBy,
            ]
        );
    }

    private function decodeIds(string $raw): array
    {
        $dec = json_decode($raw, true);
        if (!is_array($dec)) {
            return [];
        }

        return $this->normalizeIds($dec);
    }

    private function normalizeIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }
}
