<?php
/**
 * EducaTudo - Resultado acadêmico homologado (fechamento).
 * Snapshot imutável por aluno × turma × ano × período.
 */

class ResultadoAcademico
{
    public const STATUS = [
        'em_andamento' => 'Em andamento',
        'homologado' => 'Homologado',
        'reaberto' => 'Reaberto',
    ];

    public const PERIODO_TIPOS = [
        'ano' => 'Ano letivo',
        'bimestre' => 'Bimestre',
        'trimestre' => 'Trimestre',
        'semestre' => 'Semestre',
    ];

    public const ESPECIAIS = [
        'dispensado' => 'Dispensado',
        'aproveitamento' => 'Aproveitamento de estudos',
        'progressao_parcial' => 'Progressão parcial',
        'dependencia' => 'Dependência',
        'transferencia' => 'Transferência',
        'classificacao' => 'Classificação/reclassificação',
    ];

    public const DOCUMENTO_TIPOS = [
        'boletim' => 'Boletim',
        'ficha_individual' => 'Ficha Individual',
        'ata_resultados' => 'Ata de Resultados Finais',
        'historico' => 'Histórico Escolar',
        'relatorio_aprovados' => 'Relatório — Aprovados',
        'relatorio_reprovados' => 'Relatório — Reprovados',
        'relatorio_recuperacao' => 'Relatório — Recuperação',
        'relatorio_frequencia' => 'Relatório — Frequência',
        'relatorio_desempenho' => 'Relatório — Desempenho',
        'relatorio_classificacao' => 'Relatório — Classificação',
        'relatorio_pendencias' => 'Relatório — Pendências',
        'relatorio_fechamento' => 'Relatório — Fechamento por turma',
    ];

    public const LAYOUT_PADRAO = [
        'boletim' => 'resultado_boletim_padrao',
        'ficha_individual' => 'resultado_ficha_individual',
        'ata_resultados' => 'resultado_ata_finais',
        'historico' => 'resultado_historico',
        'relatorio_aprovados' => 'resultado_relatorio_padrao',
        'relatorio_reprovados' => 'resultado_relatorio_padrao',
        'relatorio_recuperacao' => 'resultado_relatorio_padrao',
        'relatorio_frequencia' => 'resultado_relatorio_padrao',
        'relatorio_desempenho' => 'resultado_relatorio_padrao',
        'relatorio_classificacao' => 'resultado_relatorio_padrao',
        'relatorio_pendencias' => 'resultado_relatorio_padrao',
        'relatorio_fechamento' => 'resultado_relatorio_padrao',
    ];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureSchema();
    }

    public function schemaPronto(): bool
    {
        return $this->tabelaExiste('resultado_academico');
    }

    public function ensureSchema(): void
    {
        if ($this->tabelaExiste('resultado_academico')) {
            return;
        }
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS resultado_academico (
                id INT NOT NULL AUTO_INCREMENT,
                aluno_id INT NOT NULL,
                turma_id INT NOT NULL,
                ano_letivo SMALLINT UNSIGNED NOT NULL,
                periodo_tipo ENUM('bimestre','trimestre','semestre','ano') NOT NULL DEFAULT 'ano',
                periodo_numero TINYINT UNSIGNED NOT NULL DEFAULT 0,
                versao INT NOT NULL DEFAULT 1,
                status ENUM('em_andamento','homologado','reaberto') NOT NULL DEFAULT 'em_andamento',
                situacao VARCHAR(40) NOT NULL DEFAULT 'em_andamento',
                rotulo VARCHAR(120) NOT NULL DEFAULT 'Em andamento',
                media_final DECIMAL(8,2) NULL,
                frequencia_percentual DECIMAL(5,2) NULL,
                faltas INT NULL,
                regra_id INT NULL,
                regra_versao INT NULL,
                conselho_sessao_id INT NULL,
                conselho_resultado VARCHAR(80) NULL,
                snapshot_json MEDIUMTEXT NULL,
                homologado_em DATETIME NULL,
                homologado_por INT NULL,
                reaberto_em DATETIME NULL,
                reaberto_por INT NULL,
                reaberto_motivo TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_resultado_aluno_periodo (aluno_id, turma_id, ano_letivo, periodo_tipo, periodo_numero),
                KEY idx_resultado_turma_periodo (turma_id, ano_letivo, periodo_tipo, periodo_numero, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS resultado_academico_itens (
                id INT NOT NULL AUTO_INCREMENT,
                resultado_id INT NOT NULL,
                materia_id INT NULL,
                materia_nome VARCHAR(180) NOT NULL,
                carga_horaria INT NULL,
                media DECIMAL(8,2) NULL,
                recuperacao DECIMAL(8,2) NULL,
                media_final DECIMAL(8,2) NULL,
                faltas INT NULL,
                frequencia_percentual DECIMAL(5,2) NULL,
                situacao VARCHAR(40) NOT NULL DEFAULT 'em_andamento',
                rotulo VARCHAR(120) NOT NULL DEFAULT 'Em andamento',
                situacao_especial VARCHAR(40) NULL,
                observacao VARCHAR(255) NULL,
                ordem INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_resultado_itens_resultado (resultado_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS resultado_academico_historico (
                id INT NOT NULL AUTO_INCREMENT,
                resultado_id INT NOT NULL,
                versao INT NOT NULL,
                status VARCHAR(20) NOT NULL,
                situacao VARCHAR(40) NOT NULL,
                rotulo VARCHAR(120) NOT NULL,
                snapshot_json MEDIUMTEXT NULL,
                motivo TEXT NULL,
                usuario_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_resultado_hist_resultado (resultado_id, versao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS resultado_situacoes_especiais (
                id INT NOT NULL AUTO_INCREMENT,
                aluno_id INT NOT NULL,
                turma_id INT NULL,
                ano_letivo SMALLINT UNSIGNED NOT NULL,
                materia_id INT NULL,
                tipo ENUM('dispensado','aproveitamento','progressao_parcial','dependencia','transferencia','classificacao') NOT NULL,
                observacao TEXT NULL,
                criado_por INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_resultado_esp_aluno_ano (aluno_id, ano_letivo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS resultado_documento_layouts (
                tipo VARCHAR(40) NOT NULL,
                modelo_codigo VARCHAR(80) NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (tipo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS resultado_fechamento_config (
                id TINYINT UNSIGNED NOT NULL DEFAULT 1,
                exigir_conselho TINYINT(1) NOT NULL DEFAULT 0,
                exigir_frequencia TINYINT(1) NOT NULL DEFAULT 0,
                exigir_notas TINYINT(1) NOT NULL DEFAULT 1,
                atualizado_por INT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS resultado_documento_emissoes (
                id INT NOT NULL AUTO_INCREMENT,
                tipo VARCHAR(40) NOT NULL,
                modelo_codigo VARCHAR(80) NULL,
                aluno_id INT NULL,
                turma_id INT NULL,
                resultado_id INT NULL,
                ano_letivo SMALLINT UNSIGNED NULL,
                periodo_tipo VARCHAR(20) NULL,
                periodo_numero TINYINT UNSIGNED NULL,
                numero INT NOT NULL DEFAULT 0,
                hash_validacao CHAR(64) NULL,
                snapshot_json MEDIUMTEXT NULL,
                emitido_por INT NULL,
                emitido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_resultado_emis_tipo_ano (tipo, ano_letivo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0 || !$this->schemaPronto()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT r.*, a.nome AS aluno_nome, a.ra AS aluno_ra, t.nome AS turma_nome, t.serie AS turma_serie
             FROM resultado_academico r
             INNER JOIN alunos a ON a.id = r.aluno_id
             INNER JOIN turmas t ON t.id = r.turma_id
             WHERE r.id = :id LIMIT 1',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function findVigente(int $alunoId, int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): ?array
    {
        if (!$this->schemaPronto() || $alunoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM resultado_academico
             WHERE aluno_id = :aluno AND turma_id = :turma AND ano_letivo = :ano
               AND periodo_tipo = :tipo AND periodo_numero = :num
             LIMIT 1',
            [
                'aluno' => $alunoId,
                'turma' => $turmaId,
                'ano' => $anoLetivo,
                'tipo' => $periodoTipo,
                'num' => $periodoNumero,
            ]
        );
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarDaTurma(int $turmaId, int $anoLetivo, string $periodoTipo, int $periodoNumero): array
    {
        if (!$this->schemaPronto()) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT r.*, a.nome AS aluno_nome, a.ra AS aluno_ra
             FROM resultado_academico r
             INNER JOIN alunos a ON a.id = r.aluno_id
             WHERE r.turma_id = :turma AND r.ano_letivo = :ano
               AND r.periodo_tipo = :tipo AND r.periodo_numero = :num
             ORDER BY a.nome ASC',
            [
                'turma' => $turmaId,
                'ano' => $anoLetivo,
                'tipo' => $periodoTipo,
                'num' => $periodoNumero,
            ]
        ) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorAluno(int $alunoId): array
    {
        if (!$this->schemaPronto() || $alunoId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT r.*, t.nome AS turma_nome, t.serie AS turma_serie
             FROM resultado_academico r
             INNER JOIN turmas t ON t.id = r.turma_id
             WHERE r.aluno_id = :aluno
             ORDER BY r.ano_letivo DESC, r.periodo_tipo ASC, r.periodo_numero ASC',
            ['aluno' => $alunoId]
        ) ?: [];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function upsertVigente(array $data): int
    {
        $existente = $this->findVigente(
            (int) $data['aluno_id'],
            (int) $data['turma_id'],
            (int) $data['ano_letivo'],
            (string) $data['periodo_tipo'],
            (int) $data['periodo_numero']
        );
        if ($existente) {
            $id = (int) $existente['id'];
            $this->atualizar($id, $data);
            return $id;
        }
        return $this->criar($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function criar(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO resultado_academico
                (aluno_id, turma_id, ano_letivo, periodo_tipo, periodo_numero, versao, status,
                 situacao, rotulo, media_final, frequencia_percentual, faltas, regra_id, regra_versao,
                 conselho_sessao_id, conselho_resultado, snapshot_json,
                 homologado_em, homologado_por)
             VALUES
                (:aluno_id, :turma_id, :ano_letivo, :periodo_tipo, :periodo_numero, :versao, :status,
                 :situacao, :rotulo, :media_final, :frequencia_percentual, :faltas, :regra_id, :regra_versao,
                 :conselho_sessao_id, :conselho_resultado, :snapshot_json,
                 :homologado_em, :homologado_por)',
            [
                'aluno_id' => (int) $data['aluno_id'],
                'turma_id' => (int) $data['turma_id'],
                'ano_letivo' => (int) $data['ano_letivo'],
                'periodo_tipo' => (string) $data['periodo_tipo'],
                'periodo_numero' => (int) $data['periodo_numero'],
                'versao' => (int) ($data['versao'] ?? 1),
                'status' => (string) ($data['status'] ?? 'em_andamento'),
                'situacao' => (string) ($data['situacao'] ?? 'em_andamento'),
                'rotulo' => (string) ($data['rotulo'] ?? 'Em andamento'),
                'media_final' => $data['media_final'] ?? null,
                'frequencia_percentual' => $data['frequencia_percentual'] ?? null,
                'faltas' => $data['faltas'] ?? null,
                'regra_id' => $data['regra_id'] ?? null,
                'regra_versao' => $data['regra_versao'] ?? null,
                'conselho_sessao_id' => $data['conselho_sessao_id'] ?? null,
                'conselho_resultado' => $data['conselho_resultado'] ?? null,
                'snapshot_json' => $data['snapshot_json'] ?? null,
                'homologado_em' => $data['homologado_em'] ?? null,
                'homologado_por' => $data['homologado_por'] ?? null,
            ]
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function atualizar(int $id, array $data): void
    {
        $sets = [];
        $params = ['id' => $id];
        $permitidos = [
            'versao', 'status', 'situacao', 'rotulo', 'media_final', 'frequencia_percentual',
            'faltas', 'regra_id', 'regra_versao', 'conselho_sessao_id', 'conselho_resultado',
            'snapshot_json', 'homologado_em', 'homologado_por', 'reaberto_em', 'reaberto_por',
            'reaberto_motivo',
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
            'UPDATE resultado_academico SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    public function substituirItens(int $resultadoId, array $itens): void
    {
        $this->db->delete(
            'DELETE FROM resultado_academico_itens WHERE resultado_id = :id',
            ['id' => $resultadoId]
        );
        $ordem = 0;
        foreach ($itens as $item) {
            if (!is_array($item)) {
                continue;
            }
            $ordem++;
            $this->db->insert(
                'INSERT INTO resultado_academico_itens
                    (resultado_id, materia_id, materia_nome, carga_horaria, media, recuperacao,
                     media_final, faltas, frequencia_percentual, situacao, rotulo,
                     situacao_especial, observacao, ordem)
                 VALUES
                    (:resultado_id, :materia_id, :materia_nome, :carga_horaria, :media, :recuperacao,
                     :media_final, :faltas, :frequencia_percentual, :situacao, :rotulo,
                     :situacao_especial, :observacao, :ordem)',
                [
                    'resultado_id' => $resultadoId,
                    'materia_id' => $item['materia_id'] ?? null,
                    'materia_nome' => (string) ($item['materia_nome'] ?? 'Componente'),
                    'carga_horaria' => $item['carga_horaria'] ?? null,
                    'media' => $item['media'] ?? null,
                    'recuperacao' => $item['recuperacao'] ?? null,
                    'media_final' => $item['media_final'] ?? null,
                    'faltas' => $item['faltas'] ?? null,
                    'frequencia_percentual' => $item['frequencia_percentual'] ?? null,
                    'situacao' => (string) ($item['situacao'] ?? 'em_andamento'),
                    'rotulo' => (string) ($item['rotulo'] ?? 'Em andamento'),
                    'situacao_especial' => $item['situacao_especial'] ?? null,
                    'observacao' => $item['observacao'] ?? null,
                    'ordem' => (int) ($item['ordem'] ?? $ordem),
                ]
            );
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarItens(int $resultadoId): array
    {
        if (!$this->tabelaExiste('resultado_academico_itens')) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT * FROM resultado_academico_itens WHERE resultado_id = :id ORDER BY ordem ASC, id ASC',
            ['id' => $resultadoId]
        ) ?: [];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function registrarHistorico(int $resultadoId, array $data): void
    {
        if (!$this->tabelaExiste('resultado_academico_historico')) {
            return;
        }
        $this->db->insert(
            'INSERT INTO resultado_academico_historico
                (resultado_id, versao, status, situacao, rotulo, snapshot_json, motivo, usuario_id)
             VALUES
                (:resultado_id, :versao, :status, :situacao, :rotulo, :snapshot_json, :motivo, :usuario_id)',
            [
                'resultado_id' => $resultadoId,
                'versao' => (int) ($data['versao'] ?? 1),
                'status' => (string) ($data['status'] ?? ''),
                'situacao' => (string) ($data['situacao'] ?? ''),
                'rotulo' => (string) ($data['rotulo'] ?? ''),
                'snapshot_json' => $data['snapshot_json'] ?? null,
                'motivo' => $data['motivo'] ?? null,
                'usuario_id' => $data['usuario_id'] ?? null,
            ]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarHistorico(int $resultadoId): array
    {
        if (!$this->tabelaExiste('resultado_academico_historico')) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT * FROM resultado_academico_historico WHERE resultado_id = :id ORDER BY versao DESC, id DESC',
            ['id' => $resultadoId]
        ) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarEspeciais(int $alunoId, int $anoLetivo): array
    {
        if (!$this->tabelaExiste('resultado_situacoes_especiais') || $alunoId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT e.*, m.nome AS materia_nome
             FROM resultado_situacoes_especiais e
             LEFT JOIN materias m ON m.id = e.materia_id
             WHERE e.aluno_id = :aluno AND e.ano_letivo = :ano
             ORDER BY e.id ASC',
            ['aluno' => $alunoId, 'ano' => $anoLetivo]
        ) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarEspeciaisTurma(int $turmaId, int $anoLetivo): array
    {
        if (!$this->tabelaExiste('resultado_situacoes_especiais')) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT e.*, a.nome AS aluno_nome, m.nome AS materia_nome
             FROM resultado_situacoes_especiais e
             INNER JOIN alunos a ON a.id = e.aluno_id
             LEFT JOIN materias m ON m.id = e.materia_id
             WHERE e.ano_letivo = :ano AND (e.turma_id = :turma OR a.turma_id = :turma)
             ORDER BY a.nome ASC, e.id ASC',
            ['ano' => $anoLetivo, 'turma' => $turmaId]
        ) ?: [];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function criarEspecial(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO resultado_situacoes_especiais
                (aluno_id, turma_id, ano_letivo, materia_id, tipo, observacao, criado_por)
             VALUES
                (:aluno_id, :turma_id, :ano_letivo, :materia_id, :tipo, :observacao, :criado_por)',
            [
                'aluno_id' => (int) $data['aluno_id'],
                'turma_id' => $data['turma_id'] ?? null,
                'ano_letivo' => (int) $data['ano_letivo'],
                'materia_id' => $data['materia_id'] ?? null,
                'tipo' => (string) $data['tipo'],
                'observacao' => $data['observacao'] ?? null,
                'criado_por' => $data['criado_por'] ?? null,
            ]
        );
    }

    public function excluirEspecial(int $id, ?int $turmaId = null): void
    {
        if ($id <= 0) {
            return;
        }
        if ($turmaId !== null && $turmaId > 0) {
            $this->db->delete(
                'DELETE FROM resultado_situacoes_especiais WHERE id = :id AND turma_id = :turma',
                ['id' => $id, 'turma' => $turmaId]
            );
            return;
        }
        $this->db->delete('DELETE FROM resultado_situacoes_especiais WHERE id = :id', ['id' => $id]);
    }

    public function getLayoutCodigo(string $tipo): string
    {
        $padrao = self::LAYOUT_PADRAO[$tipo] ?? 'resultado_relatorio_padrao';
        if (!$this->tabelaExiste('resultado_documento_layouts')) {
            return $padrao;
        }
        $row = $this->db->fetch(
            'SELECT modelo_codigo FROM resultado_documento_layouts WHERE tipo = :tipo LIMIT 1',
            ['tipo' => $tipo]
        );
        $codigo = trim((string) ($row['modelo_codigo'] ?? ''));
        return $codigo !== '' ? $codigo : $padrao;
    }

    /**
     * @return array<string,string>
     */
    public function listarLayoutsEscola(): array
    {
        $out = self::LAYOUT_PADRAO;
        if (!$this->tabelaExiste('resultado_documento_layouts')) {
            return $out;
        }
        foreach ($this->db->fetchAll('SELECT tipo, modelo_codigo FROM resultado_documento_layouts') ?: [] as $row) {
            $tipo = (string) ($row['tipo'] ?? '');
            $codigo = trim((string) ($row['modelo_codigo'] ?? ''));
            if ($tipo !== '' && $codigo !== '') {
                $out[$tipo] = $codigo;
            }
        }
        return $out;
    }

    public function salvarLayout(string $tipo, string $modeloCodigo): void
    {
        if (!isset(self::DOCUMENTO_TIPOS[$tipo])) {
            return;
        }
        $codigo = mb_substr(trim($modeloCodigo), 0, 80);
        if ($codigo === '') {
            return;
        }
        $exist = $this->db->fetch(
            'SELECT tipo FROM resultado_documento_layouts WHERE tipo = :tipo LIMIT 1',
            ['tipo' => $tipo]
        );
        if ($exist) {
            $this->db->update(
                'UPDATE resultado_documento_layouts SET modelo_codigo = :codigo WHERE tipo = :tipo',
                ['codigo' => $codigo, 'tipo' => $tipo]
            );
            return;
        }
        $this->db->insert(
            'INSERT INTO resultado_documento_layouts (tipo, modelo_codigo) VALUES (:tipo, :codigo)',
            ['tipo' => $tipo, 'codigo' => $codigo]
        );
    }

    /**
     * @return array{exigir_conselho:bool,exigir_frequencia:bool,exigir_notas:bool}
     */
    public function getConfigFechamento(): array
    {
        $padrao = ['exigir_conselho' => false, 'exigir_frequencia' => false, 'exigir_notas' => true];
        if (!$this->tabelaExiste('resultado_fechamento_config')) {
            return $padrao;
        }
        $row = $this->db->fetch('SELECT * FROM resultado_fechamento_config WHERE id = 1 LIMIT 1');
        if (!$row) {
            return $padrao;
        }
        return [
            'exigir_conselho' => !empty($row['exigir_conselho']),
            'exigir_frequencia' => !empty($row['exigir_frequencia']),
            'exigir_notas' => !empty($row['exigir_notas']),
        ];
    }

    public function salvarConfigFechamento(array $data, ?int $usuarioId): void
    {
        if (!$this->tabelaExiste('resultado_fechamento_config')) {
            return;
        }
        $exist = $this->db->fetch('SELECT id FROM resultado_fechamento_config WHERE id = 1 LIMIT 1');
        $payload = [
            'exigir_conselho' => !empty($data['exigir_conselho']) ? 1 : 0,
            'exigir_frequencia' => !empty($data['exigir_frequencia']) ? 1 : 0,
            'exigir_notas' => array_key_exists('exigir_notas', $data) ? (!empty($data['exigir_notas']) ? 1 : 0) : 1,
            'atualizado_por' => $usuarioId,
        ];
        if ($exist) {
            $this->db->update(
                'UPDATE resultado_fechamento_config
                 SET exigir_conselho = :exigir_conselho, exigir_frequencia = :exigir_frequencia,
                     exigir_notas = :exigir_notas, atualizado_por = :atualizado_por
                 WHERE id = 1',
                $payload
            );
            return;
        }
        $this->db->insert(
            'INSERT INTO resultado_fechamento_config
                (id, exigir_conselho, exigir_frequencia, exigir_notas, atualizado_por)
             VALUES (1, :exigir_conselho, :exigir_frequencia, :exigir_notas, :atualizado_por)',
            $payload
        );
    }

    /**
     * @param array<string,mixed> $meta
     */
    public function registrarEmissao(array $meta): int
    {
        if (!$this->tabelaExiste('resultado_documento_emissoes')) {
            return 0;
        }
        $ano = (int) ($meta['ano_letivo'] ?? date('Y'));
        $tipo = (string) ($meta['tipo'] ?? '');
        $numero = isset($meta['numero']) ? (int) $meta['numero'] : 0;
        if ($numero <= 0) {
            $row = $this->db->fetch(
                'SELECT COALESCE(MAX(numero), 0) + 1 AS prox
                 FROM resultado_documento_emissoes
                 WHERE tipo = :tipo AND ano_letivo = :ano',
                ['tipo' => $tipo, 'ano' => $ano]
            );
            $numero = (int) ($row['prox'] ?? 1);
        }
        $this->db->insert(
            'INSERT INTO resultado_documento_emissoes
                (tipo, modelo_codigo, aluno_id, turma_id, resultado_id, ano_letivo,
                 periodo_tipo, periodo_numero, numero, hash_validacao, snapshot_json, emitido_por)
             VALUES
                (:tipo, :modelo_codigo, :aluno_id, :turma_id, :resultado_id, :ano_letivo,
                 :periodo_tipo, :periodo_numero, :numero, :hash_validacao, :snapshot_json, :emitido_por)',
            [
                'tipo' => $tipo,
                'modelo_codigo' => $meta['modelo_codigo'] ?? null,
                'aluno_id' => $meta['aluno_id'] ?? null,
                'turma_id' => $meta['turma_id'] ?? null,
                'resultado_id' => $meta['resultado_id'] ?? null,
                'ano_letivo' => $ano,
                'periodo_tipo' => $meta['periodo_tipo'] ?? null,
                'periodo_numero' => $meta['periodo_numero'] ?? null,
                'numero' => $numero,
                'hash_validacao' => $meta['hash_validacao'] ?? null,
                'snapshot_json' => $meta['snapshot_json'] ?? null,
                'emitido_por' => $meta['emitido_por'] ?? null,
            ]
        );
        return $numero;
    }

    public function proximoNumeroEmissao(string $tipo, int $anoLetivo): int
    {
        if (!$this->tabelaExiste('resultado_documento_emissoes')) {
            return 1;
        }
        $row = $this->db->fetch(
            'SELECT COALESCE(MAX(numero), 0) + 1 AS prox
             FROM resultado_documento_emissoes WHERE tipo = :tipo AND ano_letivo = :ano',
            ['tipo' => $tipo, 'ano' => $anoLetivo]
        );
        return (int) ($row['prox'] ?? 1);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function turmasAtivas(int $anoLetivo = 0): array
    {
        $sql = 'SELECT id, nome, serie, ano_letivo, turno FROM turmas WHERE ativo = 1';
        $params = [];
        if ($anoLetivo > 0) {
            $sql .= ' AND ano_letivo = :ano';
            $params['ano'] = $anoLetivo;
        }
        $sql .= ' ORDER BY nome ASC';
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * @return list<int>
     */
    public function anosLetivosTurmas(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT ano_letivo FROM turmas WHERE ano_letivo IS NOT NULL ORDER BY ano_letivo DESC'
        ) ?: [];
        $anos = [];
        foreach ($rows as $row) {
            $anos[] = (int) $row['ano_letivo'];
        }
        if ($anos === []) {
            $anos[] = (int) date('Y');
        }
        return $anos;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function alunosDaTurma(int $turmaId, ?int $anoLetivo = null): array
    {
        if ($anoLetivo !== null && $anoLetivo > 0 && $this->tabelaExiste('matricula') && $this->tabelaExiste('ano_letivo')) {
            $porMatricula = $this->db->fetchAll(
                "SELECT a.id, a.nome, a.ra, a.ativo,
                        CASE WHEN m.status = 'transferido' THEN 1 ELSE 0 END AS transferido
                 FROM matricula m
                 INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
                 INNER JOIN alunos a ON a.id = m.aluno_id
                 WHERE m.turma_id = :turma_id AND al.ano = :ano
                   AND m.status IN ('ativa', 'concluido', 'transferido')
                 ORDER BY a.nome ASC",
                ['turma_id' => $turmaId, 'ano' => $anoLetivo]
            ) ?: [];
            if ($porMatricula !== []) {
                $vistos = [];
                $out = [];
                foreach ($porMatricula as $aluno) {
                    $id = (int) $aluno['id'];
                    if (isset($vistos[$id])) {
                        continue;
                    }
                    $vistos[$id] = true;
                    $out[] = $aluno;
                }
                return $out;
            }
        }

        $atuais = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, a.ativo, 0 AS transferido
             FROM alunos a
             WHERE a.turma_id = :turma_id AND a.ativo = 1
             ORDER BY a.nome ASC",
            ['turma_id' => $turmaId]
        ) ?: [];

        $saidos = [];
        if ($this->tabelaExiste('alunos_turmas_historico')) {
            $saidos = $this->db->fetchAll(
                "SELECT a.id, a.nome, a.ra, a.ativo, 1 AS transferido
                 FROM alunos_turmas_historico h
                 INNER JOIN alunos a ON a.id = h.aluno_id
                 WHERE h.turma_id = :turma_id AND h.data_fim IS NOT NULL
                   AND (a.turma_id IS NULL OR a.turma_id <> :turma_id2)
                 ORDER BY a.nome ASC",
                ['turma_id' => $turmaId, 'turma_id2' => $turmaId]
            ) ?: [];
        }

        $vistos = [];
        $out = [];
        foreach (array_merge($atuais, $saidos) as $aluno) {
            $id = (int) $aluno['id'];
            if (isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $out[] = $aluno;
        }
        return $out;
    }

    public function tabelaExiste(string $tabela): bool
    {
        static $cache = [];
        $tenantKey = defined('TENANT_ID') ? ('t' . TENANT_ID) : 'no_tenant';
        $key = $tenantKey . ':' . $tabela;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1',
                ['t' => $tabela]
            );
            $cache[$key] = !empty($row['ok']);
        } catch (Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }
}
