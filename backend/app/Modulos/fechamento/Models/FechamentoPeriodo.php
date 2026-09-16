<?php
require_once __DIR__ . '/../Services/FechamentoMaquinaEstados.php';

/**
 * Persistência do fechamento oficial por turma × período.
 */
class FechamentoPeriodo
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureSchema();
    }

    public function schemaPronto(): bool
    {
        return $this->tabelaExiste('fechamento_periodo');
    }

    public function ensureSchema(): void
    {
        if ($this->tabelaExiste('fechamento_periodo')) {
            return;
        }
        try {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS fechamento_periodo (
                    id INT NOT NULL AUTO_INCREMENT,
                    turma_id INT NOT NULL,
                    ano_letivo SMALLINT UNSIGNED NOT NULL,
                    periodo_tipo ENUM('bimestre','trimestre','semestre','ano') NOT NULL DEFAULT 'ano',
                    periodo_numero TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    periodo_ref VARCHAR(32) NOT NULL,
                    status ENUM('ABERTO','EM_FECHAMENTO','EM_RECUPERACAO','HOMOLOGADO','RETIFICADO') NOT NULL DEFAULT 'ABERTO',
                    regra_academica_id INT NULL,
                    homologado_em DATETIME NULL,
                    homologado_por INT NULL,
                    retificado_de_id INT NULL,
                    justificativa TEXT NULL,
                    vigente TINYINT(1) NOT NULL DEFAULT 1,
                    vigente_chave VARCHAR(80) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uk_fechamento_vigente (vigente_chave),
                    KEY idx_fechamento_turma_periodo (turma_id, ano_letivo, periodo_tipo, periodo_numero, vigente)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS fechamento_periodo_historico (
                    id INT NOT NULL AUTO_INCREMENT,
                    fechamento_id INT NOT NULL,
                    status_anterior VARCHAR(20) NULL,
                    status_novo VARCHAR(20) NOT NULL,
                    justificativa TEXT NULL,
                    usuario_id INT NULL,
                    payload_json MEDIUMTEXT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_fechamento_hist_fechamento (fechamento_id, id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            error_log('FechamentoPeriodo::ensureSchema: ' . $e->getMessage());
        }
    }

    public function tabelaExiste(string $tabela): bool
    {
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1',
                ['t' => $tabela]
            );
            return !empty($row['ok']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        if (!$this->schemaPronto() || $id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM fechamento_periodo WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findVigente(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): ?array
    {
        if (!$this->schemaPronto() || $turmaId <= 0 || $anoLetivo <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM fechamento_periodo
             WHERE turma_id = :turma AND ano_letivo = :ano
               AND periodo_tipo = :tipo AND periodo_numero = :num
               AND vigente = 1
             LIMIT 1',
            [
                'turma' => $turmaId,
                'ano' => $anoLetivo,
                'tipo' => $periodoTipo,
                'num' => $periodoNumero,
            ]
        );
        return $row ?: null;
    }

    public function estaTravado(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): bool
    {
        $row = $this->findVigente($turmaId, $anoLetivo, $periodoTipo, $periodoNumero);
        if (!$row) {
            return false;
        }
        return FechamentoMaquinaEstados::estaTravado((string) ($row['status'] ?? ''));
    }

    /**
     * Trava se o ano estiver homologado ou o bimestre da data.
     */
    public function estaTravadoNaData(int $turmaId, int $anoLetivo, string $dataYmd, int $bimestre = 0): bool
    {
        if ($this->estaTravado($turmaId, $anoLetivo, 'ano', 0)) {
            return true;
        }
        if ($bimestre >= 1 && $bimestre <= 4 && $this->estaTravado($turmaId, $anoLetivo, 'bimestre', $bimestre)) {
            return true;
        }
        return false;
    }

    public function mensagemBloqueioOficial(): string
    {
        return 'Este período está homologado. Notas, faltas e o boletim oficial só podem ser alterados após retificação formal.';
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public function assertEditavel(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): array
    {
        if ($turmaId <= 0 || $anoLetivo <= 0) {
            return ['ok' => true];
        }
        if ($this->estaTravado($turmaId, $anoLetivo, $periodoTipo, $periodoNumero)) {
            return ['ok' => false, 'error' => $this->mensagemBloqueioOficial()];
        }
        if ($periodoTipo !== 'ano' && $this->estaTravado($turmaId, $anoLetivo, 'ano', 0)) {
            return ['ok' => false, 'error' => 'O ano letivo desta turma está homologado. Use retificação para alterar lançamentos oficiais.'];
        }
        return ['ok' => true];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function criar(array $data): int
    {
        $turmaId = (int) ($data['turma_id'] ?? 0);
        $ano = (int) ($data['ano_letivo'] ?? 0);
        $tipo = (string) ($data['periodo_tipo'] ?? 'ano');
        $num = (int) ($data['periodo_numero'] ?? 0);
        $ref = (string) ($data['periodo_ref'] ?? FechamentoMaquinaEstados::periodoRef($ano, $tipo, $num));
        $status = FechamentoMaquinaEstados::normalizar((string) ($data['status'] ?? FechamentoMaquinaEstados::ABERTO));
        $vigente = array_key_exists('vigente', $data) ? ((int) $data['vigente'] ? 1 : 0) : 1;
        $chave = $vigente === 1 ? $this->chaveVigente($turmaId, $ano, $tipo, $num) : null;

        return (int) $this->db->insert(
            'INSERT INTO fechamento_periodo
                (turma_id, ano_letivo, periodo_tipo, periodo_numero, periodo_ref, status,
                 regra_academica_id, homologado_em, homologado_por, retificado_de_id,
                 justificativa, vigente, vigente_chave)
             VALUES
                (:turma_id, :ano_letivo, :periodo_tipo, :periodo_numero, :periodo_ref, :status,
                 :regra_academica_id, :homologado_em, :homologado_por, :retificado_de_id,
                 :justificativa, :vigente, :vigente_chave)',
            [
                'turma_id' => $turmaId,
                'ano_letivo' => $ano,
                'periodo_tipo' => $tipo,
                'periodo_numero' => $num,
                'periodo_ref' => $ref,
                'status' => $status,
                'regra_academica_id' => !empty($data['regra_academica_id']) ? (int) $data['regra_academica_id'] : null,
                'homologado_em' => $data['homologado_em'] ?? null,
                'homologado_por' => !empty($data['homologado_por']) ? (int) $data['homologado_por'] : null,
                'retificado_de_id' => !empty($data['retificado_de_id']) ? (int) $data['retificado_de_id'] : null,
                'justificativa' => $data['justificativa'] ?? null,
                'vigente' => $vigente,
                'vigente_chave' => $chave,
            ]
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function atualizar(int $id, array $data): void
    {
        if ($id <= 0 || !$this->schemaPronto()) {
            return;
        }
        $sets = [];
        $params = ['id' => $id];
        $permitidos = [
            'status', 'regra_academica_id', 'homologado_em', 'homologado_por',
            'retificado_de_id', 'justificativa', 'vigente', 'vigente_chave', 'periodo_ref',
        ];
        foreach ($permitidos as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $sets[] = $col . ' = :' . $col;
            $params[$col] = $data[$col];
        }
        if ($sets === []) {
            return;
        }
        $this->db->update(
            'UPDATE fechamento_periodo SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    public function marcarNaoVigente(int $id): void
    {
        $this->atualizar($id, ['vigente' => 0, 'vigente_chave' => null]);
    }

    /**
     * @param array<string,mixed> $meta
     */
    public function registrarHistorico(int $fechamentoId, array $meta): void
    {
        if ($fechamentoId <= 0 || !$this->tabelaExiste('fechamento_periodo_historico')) {
            return;
        }
        $this->db->insert(
            'INSERT INTO fechamento_periodo_historico
                (fechamento_id, status_anterior, status_novo, justificativa, usuario_id, payload_json)
             VALUES
                (:fechamento_id, :status_anterior, :status_novo, :justificativa, :usuario_id, :payload_json)',
            [
                'fechamento_id' => $fechamentoId,
                'status_anterior' => $meta['status_anterior'] ?? null,
                'status_novo' => (string) ($meta['status_novo'] ?? ''),
                'justificativa' => $meta['justificativa'] ?? null,
                'usuario_id' => !empty($meta['usuario_id']) ? (int) $meta['usuario_id'] : null,
                'payload_json' => $meta['payload_json'] ?? null,
            ]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarDoAno(int $anoLetivo, string $periodoTipo = '', int $periodoNumero = -1): array
    {
        if (!$this->schemaPronto() || $anoLetivo <= 0) {
            return [];
        }
        $sql = 'SELECT f.*, t.nome AS turma_nome, t.serie AS turma_serie, t.turno AS turma_turno
                FROM fechamento_periodo f
                INNER JOIN turmas t ON t.id = f.turma_id
                WHERE f.ano_letivo = :ano AND f.vigente = 1';
        $params = ['ano' => $anoLetivo];
        if ($periodoTipo !== '') {
            $sql .= ' AND f.periodo_tipo = :tipo AND f.periodo_numero = :num';
            $params['tipo'] = $periodoTipo;
            $params['num'] = $periodoNumero;
        }
        $sql .= ' ORDER BY t.nome ASC';
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarLivro(int $anoLetivo = 0, int $turmaId = 0): array
    {
        if (!$this->schemaPronto()) {
            return [];
        }
        $sql = 'SELECT f.*, t.nome AS turma_nome,
                       u.nome AS homologado_por_nome
                FROM fechamento_periodo f
                INNER JOIN turmas t ON t.id = f.turma_id
                LEFT JOIN usuarios u ON u.id = f.homologado_por
                WHERE 1=1';
        $params = [];
        if ($anoLetivo > 0) {
            $sql .= ' AND f.ano_letivo = :ano';
            $params['ano'] = $anoLetivo;
        }
        if ($turmaId > 0) {
            $sql .= ' AND f.turma_id = :turma';
            $params['turma'] = $turmaId;
        }
        $sql .= ' ORDER BY f.ano_letivo DESC, t.nome ASC, f.id DESC';
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarHistorico(int $fechamentoId): array
    {
        if (!$this->tabelaExiste('fechamento_periodo_historico') || $fechamentoId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT h.*, u.nome AS usuario_nome
             FROM fechamento_periodo_historico h
             LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.fechamento_id = :id
             ORDER BY h.id DESC',
            ['id' => $fechamentoId]
        ) ?: [];
    }

    public function chaveVigente(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): string
    {
        return $turmaId . ':' . $anoLetivo . ':' . $periodoTipo . ':' . $periodoNumero;
    }
}
