<?php

require_once __DIR__ . '/../../Core/Database.php';

class BoletimConfig
{
    /**
     * Linhas por INSERT em lote. 500 (não 999): cada linha leva JSON de colunas/notas
     * e o limite real no MySQL é max_allowed_packet, não o teto de 1000 do SQL Server.
     */
    private const TAMANHO_LOTE_INSERT_RESULTADOS = 500;

    private $db;
    /** @var array<string, array<int,int>> */
    private array $mapaOrdemBoletimCache = [];
    /** @var array<string, bool> */
    private array $colunaExisteCache = [];
    /** @var array<string, list<array<string,mixed>>> */
    private array $materiasDisponiveisCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function ensureSchema(): void
    {
        // Evita reexecutar toda a verificação de schema mais de uma vez por request
        // (CREATE TABLE + dezenas de checks em INFORMATION_SCHEMA são caros).
        static $jaVerificado = false;
        if ($jaVerificado) {
            return;
        }
        $jaVerificado = true;

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS boletim_regras (
                id INT NOT NULL AUTO_INCREMENT,
                nome VARCHAR(150) NOT NULL,
                formula_final TEXT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_boletim_regras_ativo (ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS boletim_componentes (
                id INT NOT NULL AUTO_INCREMENT,
                regra_id INT NOT NULL,
                codigo VARCHAR(60) NOT NULL,
                nome VARCHAR(150) NOT NULL,
                source_type ENUM('provas_sistema','manual','jornadas','calculado','evento_boletim','faltas_evento','nenhuma') NOT NULL DEFAULT 'provas_sistema',
                calc_type ENUM('media','soma','maior','ultima') NOT NULL DEFAULT 'media',
                peso DECIMAL(8,3) NOT NULL DEFAULT 1.000,
                filtro_titulo VARCHAR(255) NULL,
                bloco_id INT NULL,
                materia_id INT NULL,
                materia_unica TINYINT(1) NOT NULL DEFAULT 0,
                usar_percentual TINYINT(1) NOT NULL DEFAULT 1,
                escala_max DECIMAL(8,2) NOT NULL DEFAULT 10.00,
                obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                ordem INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_boletim_componentes_regra_codigo (regra_id, codigo),
                KEY idx_boletim_componentes_regra (regra_id),
                KEY idx_boletim_componentes_bloco (bloco_id),
                CONSTRAINT fk_boletim_componentes_regra FOREIGN KEY (regra_id) REFERENCES boletim_regras(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureComponenteColumn('bloco_id', "ALTER TABLE boletim_componentes ADD COLUMN bloco_id INT NULL AFTER filtro_titulo");
        $this->ensureComponenteColumn('materia_id', "ALTER TABLE boletim_componentes ADD COLUMN materia_id INT NULL AFTER bloco_id");
        $this->ensureComponenteColumn('materia_unica', "ALTER TABLE boletim_componentes ADD COLUMN materia_unica TINYINT(1) NOT NULL DEFAULT 0 AFTER materia_id");
        $this->ensureComponenteColumn('blocos_ids', "ALTER TABLE boletim_componentes ADD COLUMN blocos_ids VARCHAR(500) NULL AFTER bloco_id");
        $this->ensureComponenteColumn('materias_ids', "ALTER TABLE boletim_componentes ADD COLUMN materias_ids TEXT NULL AFTER materia_id");
        $this->ensureComponenteColumn('config_json', "ALTER TABLE boletim_componentes ADD COLUMN config_json TEXT NULL AFTER blocos_ids");
        $this->ensureSourceTypeIncludesJornadas();
        $this->ensureRegraColumn('codigo', "ALTER TABLE boletim_regras ADD COLUMN codigo VARCHAR(120) NULL AFTER nome");
        $this->ensureRegraColumn('descricao_curta', "ALTER TABLE boletim_regras ADD COLUMN descricao_curta VARCHAR(255) NULL AFTER codigo");
        $this->ensureRegraColumn('materias_ids', "ALTER TABLE boletim_regras ADD COLUMN materias_ids TEXT NULL AFTER formula_final");
        $this->ensureRegraColumn('formula_materias_json', "ALTER TABLE boletim_regras ADD COLUMN formula_materias_json TEXT NULL AFTER formula_final");
        $this->ensureRegraColumn('extras_json', "ALTER TABLE boletim_regras ADD COLUMN extras_json TEXT NULL AFTER formula_materias_json");
        $this->ensureRegraColumn('series_ids', "ALTER TABLE boletim_regras ADD COLUMN series_ids TEXT NULL AFTER materias_ids");
        $this->ensureRegraColumn('turmas_ids', "ALTER TABLE boletim_regras ADD COLUMN turmas_ids TEXT NULL AFTER series_ids");
        $this->ensureRegraColumn('exibir_em', "ALTER TABLE boletim_regras ADD COLUMN exibir_em ENUM('notas','boletim') NOT NULL DEFAULT 'boletim' AFTER series_ids");
        $this->ensureRegraColumn('vis_aluno', "ALTER TABLE boletim_regras ADD COLUMN vis_aluno TINYINT(1) NOT NULL DEFAULT 1 AFTER exibir_em");
        $this->ensureRegraColumn('vis_pais', "ALTER TABLE boletim_regras ADD COLUMN vis_pais TINYINT(1) NOT NULL DEFAULT 1 AFTER vis_aluno");
        $this->ensureRegraColumn('vis_coordenacao', "ALTER TABLE boletim_regras ADD COLUMN vis_coordenacao TINYINT(1) NOT NULL DEFAULT 1 AFTER vis_pais");
        $this->ensureRegraColumn('round_mode', "ALTER TABLE boletim_regras ADD COLUMN round_mode ENUM('none','half') NOT NULL DEFAULT 'none' AFTER vis_coordenacao");
        $this->ensureRegraColumn('decimal_places', "ALTER TABLE boletim_regras ADD COLUMN decimal_places TINYINT(1) NOT NULL DEFAULT 2 AFTER round_mode");
        $this->ensureRegraColumn('default_data_inicio', "ALTER TABLE boletim_regras ADD COLUMN default_data_inicio DATE NULL AFTER round_mode");
        $this->ensureRegraColumn('default_data_fim', "ALTER TABLE boletim_regras ADD COLUMN default_data_fim DATE NULL AFTER default_data_inicio");
        $this->ensureRegraColumn('ano_letivo', "ALTER TABLE boletim_regras ADD COLUMN ano_letivo SMALLINT UNSIGNED NULL AFTER exibir_em");
        $this->ensureRegraColumn('bimestre', "ALTER TABLE boletim_regras ADD COLUMN bimestre TINYINT UNSIGNED NULL AFTER ano_letivo");
        $this->ensureRegraColumn('nota_minima_aprovacao', "ALTER TABLE boletim_regras ADD COLUMN nota_minima_aprovacao DECIMAL(8,2) NULL AFTER bimestre");
        $this->ensureRegraColumn('usar_resultado_aprovacao', "ALTER TABLE boletim_regras ADD COLUMN usar_resultado_aprovacao TINYINT(1) NOT NULL DEFAULT 1 AFTER nota_minima_aprovacao");

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS boletim_notas_manuais (
                id INT NOT NULL AUTO_INCREMENT,
                regra_id INT NOT NULL,
                componente_id INT NOT NULL,
                aluno_id INT NOT NULL,
                periodo_ref VARCHAR(20) NOT NULL,
                nota DECIMAL(8,2) NULL DEFAULT NULL,
                bloqueado TINYINT(1) NOT NULL DEFAULT 0,
                observacao VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_boletim_notas_manuais_item (componente_id, aluno_id, periodo_ref),
                KEY idx_boletim_notas_manuais_aluno (aluno_id),
                KEY idx_boletim_notas_manuais_regra (regra_id),
                CONSTRAINT fk_boletim_notas_manuais_regra FOREIGN KEY (regra_id) REFERENCES boletim_regras(id) ON DELETE CASCADE,
                CONSTRAINT fk_boletim_notas_manuais_componente FOREIGN KEY (componente_id) REFERENCES boletim_componentes(id) ON DELETE CASCADE,
                CONSTRAINT fk_boletim_notas_manuais_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->ensureNotaManualMateriaColumn();

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS boletim_resultados_gerados (
                id INT NOT NULL AUTO_INCREMENT,
                regra_id INT NOT NULL,
                aluno_id INT NOT NULL,
                periodo_ref VARCHAR(20) NOT NULL,
                data_inicio DATE NULL,
                data_fim DATE NULL,
                materia_id INT NULL,
                materia_nome VARCHAR(180) NOT NULL,
                materia_ref VARCHAR(180) NOT NULL,
                ordem_linha INT NOT NULL DEFAULT 0,
                colunas_json TEXT NULL,
                notas_json TEXT NULL,
                media_final DECIMAL(8,2) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_boletim_resultados_aluno (aluno_id),
                KEY idx_boletim_resultados_regra (regra_id),
                KEY idx_boletim_resultados_lookup (regra_id, aluno_id, periodo_ref),
                CONSTRAINT fk_boletim_resultados_regra FOREIGN KEY (regra_id) REFERENCES boletim_regras(id) ON DELETE CASCADE,
                CONSTRAINT fk_boletim_resultados_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->ensureBoletimResultadoColumn('materia_ref', "ALTER TABLE boletim_resultados_gerados ADD COLUMN materia_ref VARCHAR(180) NOT NULL DEFAULT '' AFTER materia_nome");
        // preview=1 indica que a linha veio da tela "Simular" do admin e NÃO deve ser exposta a aluno/pais/coordenação.
        // preview=0 (default) é o boletim oficial, gerado pelo botão "Gerar boletins de todos os alunos vinculados".
        $this->ensureBoletimResultadoColumn('preview', "ALTER TABLE boletim_resultados_gerados ADD COLUMN preview TINYINT(1) NOT NULL DEFAULT 0 AFTER media_final");
        $this->ensureBoletimResultadoPreviewIndex();
        $this->ensureBoletimResultadoMateriaIdNullable();
        $this->ensureBoletimResultadoNoUniqueByMateriaRef();

        // Log de execuções de "Gerar boletins" — auditoria de quem gerou, quando e o impacto.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS boletim_log_geracoes (
                id INT NOT NULL AUTO_INCREMENT,
                regra_id INT NOT NULL,
                periodo_ref VARCHAR(20) NOT NULL,
                usuario_id INT NULL,
                usuario_nome VARCHAR(150) NULL,
                alunos_processados INT NOT NULL DEFAULT 0,
                linhas_geradas INT NOT NULL DEFAULT 0,
                erros INT NOT NULL DEFAULT 0,
                alunos_mudanca_significativa INT NOT NULL DEFAULT 0,
                detalhes_json TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_boletim_log_geracoes_regra (regra_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->ensurePeriodoRefWidth();
        $this->ensureSchemaVersionamento();

        // Observação do boletim por aluno (escrita pela coordenação, exibida no PDF).
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS boletim_observacoes (
                id INT NOT NULL AUTO_INCREMENT,
                aluno_id INT NOT NULL,
                conteudo TEXT NULL,
                updated_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_boletim_observacoes_aluno (aluno_id),
                CONSTRAINT fk_boletim_observacoes_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function getObservacaoCoordenacao(int $alunoId): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT id, aluno_id, conteudo, updated_by, updated_at
             FROM boletim_observacoes
             WHERE aluno_id = :aluno_id
             LIMIT 1",
            ['aluno_id' => $alunoId]
        );
        return $row ?: null;
    }

    public function saveObservacaoCoordenacao(int $alunoId, string $conteudo, ?int $userId = null): bool
    {
        if ($alunoId <= 0) {
            return false;
        }
        $conteudo = trim($conteudo);
        if (mb_strlen($conteudo, 'UTF-8') > 5000) {
            $conteudo = mb_substr($conteudo, 0, 5000, 'UTF-8');
        }

        $existente = $this->getObservacaoCoordenacao($alunoId);
        if ($existente) {
            $this->db->update(
                "UPDATE boletim_observacoes
                 SET conteudo = :conteudo,
                     updated_by = :updated_by,
                     updated_at = NOW()
                 WHERE id = :id",
                [
                    'conteudo' => $conteudo,
                    'updated_by' => $userId !== null && $userId > 0 ? $userId : null,
                    'id' => (int) $existente['id'],
                ]
            );
            return true;
        }

        $this->db->insert(
            "INSERT INTO boletim_observacoes (aluno_id, conteudo, updated_by)
             VALUES (:aluno_id, :conteudo, :updated_by)",
            [
                'aluno_id' => $alunoId,
                'conteudo' => $conteudo,
                'updated_by' => $userId !== null && $userId > 0 ? $userId : null,
            ]
        );
        return true;
    }

    /**
     * Lista os boletins gerados no sistema (agrupados por aluno+regra+período) para
     * a tela administrativa de inspeção/limpeza. Retorna uma página simples
     * (LIMIT/OFFSET) com metadados úteis para o admin.
     *
     * Filtros suportados em $filters:
     *  - regra_id   (int)   restringe a uma regra
     *  - aluno_id   (int)   restringe a um aluno
     *  - aluno_q    (string) busca por nome/RA do aluno (LIKE)
     *  - exibir_em  ('boletim'|'notas')
     *  - preview    ('0'|'1'|'all'); default 'all'
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function listGeneratedBoletinsAdmin(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $where = ['1=1'];
        $params = [];

        $regraId = isset($filters['regra_id']) ? (int) $filters['regra_id'] : 0;
        if ($regraId > 0) {
            $where[] = 'g.regra_id = :regra_id';
            $params['regra_id'] = $regraId;
        }
        $alunoId = isset($filters['aluno_id']) ? (int) $filters['aluno_id'] : 0;
        if ($alunoId > 0) {
            $where[] = 'g.aluno_id = :aluno_id';
            $params['aluno_id'] = $alunoId;
        }
        $exibirEm = strtolower(trim((string) ($filters['exibir_em'] ?? '')));
        if (in_array($exibirEm, ['boletim', 'notas'], true)) {
            $where[] = 'r.exibir_em = :exibir_em';
            $params['exibir_em'] = $exibirEm;
        }
        $previewFilter = strtolower(trim((string) ($filters['preview'] ?? 'all')));
        if ($previewFilter === '0' || $previewFilter === '1') {
            $where[] = 'g.preview = :preview_flag';
            $params['preview_flag'] = (int) $previewFilter;
        }
        $vigenteFilter = strtolower(trim((string) ($filters['vigente'] ?? '1')));
        if (
            $previewFilter !== '1'
            && ($vigenteFilter === '0' || $vigenteFilter === '1')
            && $this->hasColumn('boletim_resultados_gerados', 'vigente')
        ) {
            $where[] = 'g.vigente = :vigente_flag';
            $params['vigente_flag'] = (int) $vigenteFilter;
        }
        $alunoQ = trim((string) ($filters['aluno_q'] ?? ''));
        if ($alunoQ !== '') {
            // O wrapper de Database desta aplicação exige nomes únicos por ocorrência
            // do placeholder no SQL, então usamos duas chaves distintas com o mesmo valor.
            $where[] = '(a.nome LIKE :aluno_q_nome OR a.ra LIKE :aluno_q_ra)';
            $params['aluno_q_nome'] = '%' . $alunoQ . '%';
            $params['aluno_q_ra'] = '%' . $alunoQ . '%';
        }
        // Filtros por intervalo de data/hora de atualização (updated_at). Aceita
        // formato YYYY-MM-DD ou YYYY-MM-DDTHH:MM (datetime-local). Quando só a
        // data é informada, expandimos para o início/fim do dia para incluir tudo.
        $atualizadoDe = trim((string) ($filters['atualizado_de'] ?? ''));
        if ($atualizadoDe !== '') {
            $atualizadoDe = $this->normalizarDataHoraIntervalo($atualizadoDe, false);
            if ($atualizadoDe !== null) {
                $where[] = 'g.updated_at >= :atualizado_de';
                $params['atualizado_de'] = $atualizadoDe;
            }
        }
        $atualizadoAte = trim((string) ($filters['atualizado_ate'] ?? ''));
        if ($atualizadoAte !== '') {
            $atualizadoAte = $this->normalizarDataHoraIntervalo($atualizadoAte, true);
            if ($atualizadoAte !== null) {
                $where[] = 'g.updated_at <= :atualizado_ate';
                $params['atualizado_ate'] = $atualizadoAte;
            }
        }
        $whereSql = implode(' AND ', $where);

        $versaoSelect = $this->hasColumn('boletim_resultados_gerados', 'versao')
            ? 'MAX(g.versao) AS versao,'
            : '1 AS versao,';
        $rows = $this->db->fetchAll(
            "SELECT
                g.regra_id,
                g.aluno_id,
                g.periodo_ref,
                MAX(g.preview) AS preview,
                MIN(g.data_inicio) AS data_inicio,
                MAX(g.data_fim) AS data_fim,
                COUNT(*) AS linhas_qtd,
                {$versaoSelect}
                MAX(g.updated_at) AS updated_at,
                r.nome AS regra_nome,
                r.codigo AS regra_codigo,
                r.exibir_em,
                a.nome AS aluno_nome,
                a.ra AS aluno_ra,
                t.nome AS turma_nome
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             INNER JOIN alunos a ON a.id = g.aluno_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE {$whereSql}
             GROUP BY g.regra_id, g.aluno_id, g.periodo_ref, r.nome, r.codigo, r.exibir_em, a.nome, a.ra, t.nome
             ORDER BY updated_at DESC, g.aluno_id ASC, g.regra_id ASC, g.periodo_ref ASC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        ) ?: [];

        $totalRow = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM (
                SELECT g.regra_id, g.aluno_id, g.periodo_ref
                FROM boletim_resultados_gerados g
                INNER JOIN boletim_regras r ON r.id = g.regra_id
                INNER JOIN alunos a ON a.id = g.aluno_id
                WHERE {$whereSql}
                GROUP BY g.regra_id, g.aluno_id, g.periodo_ref
             ) sub",
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Retorna o boletim gerado (colunas + linhas) para um par (aluno, regra) e período
     * específico, com a mesma forma esperada pelo partial `partials/boletins_gerados.php`.
     * Diferente de getGeneratedBoletimByAlunoAndRegra, NÃO filtra por preview nem
     * vis_aluno/vis_pais/vis_coordenacao — é uma visão administrativa pura.
     *
     * Sem $versao: mostra a vigente (oficial). Com $versao: snapshot histórico daquela versão.
     */
    public function getGeneratedBoletimAdmin(int $alunoId, int $regraId, string $periodoRef, ?int $versao = null): ?array
    {
        if ($alunoId <= 0 || $regraId <= 0) {
            return null;
        }
        $periodoRef = trim($periodoRef);
        if ($periodoRef === '') {
            return null;
        }

        $params = [
            'aluno_id' => $alunoId,
            'regra_id' => $regraId,
            'periodo_ref' => $periodoRef,
        ];
        $extra = '';
        if ($versao !== null && $versao > 0 && $this->hasColumn('boletim_resultados_gerados', 'versao')) {
            $extra = ' AND g.preview = 0 AND g.versao = :versao';
            $params['versao'] = $versao;
        } elseif ($this->hasColumn('boletim_resultados_gerados', 'vigente')) {
            $extra = ' AND ((g.preview = 0 AND g.vigente = 1) OR (g.preview = 1 AND NOT EXISTS (
                SELECT 1 FROM boletim_resultados_gerados g2
                WHERE g2.aluno_id = g.aluno_id AND g2.regra_id = g.regra_id
                  AND g2.periodo_ref = g.periodo_ref AND g2.preview = 0 AND g2.vigente = 1
                LIMIT 1
            )))';
        }

        $rows = $this->db->fetchAll(
            "SELECT g.*, r.nome AS regra_nome, r.codigo AS regra_codigo, r.exibir_em, r.decimal_places,
                    a.nome AS aluno_nome, a.ra AS aluno_ra, t.nome AS turma_nome
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             INNER JOIN alunos a ON a.id = g.aluno_id
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE g.aluno_id = :aluno_id
               AND g.regra_id = :regra_id
               AND g.periodo_ref = :periodo_ref
               {$extra}
             ORDER BY g.ordem_linha ASC, g.id ASC",
            $params
        ) ?: [];

        if (!$rows) {
            return null;
        }

        $first = $rows[0];
        $evento = [
            'regra_id' => (int) ($first['regra_id'] ?? 0),
            'regra_nome' => (string) ($first['regra_nome'] ?? 'Evento'),
            'regra_codigo' => (string) ($first['regra_codigo'] ?? ''),
            'exibir_em' => (string) ($first['exibir_em'] ?? 'boletim'),
            'decimal_places' => $this->normalizeDecimalPlaces((int) ($first['decimal_places'] ?? 2)),
            'periodo_ref' => (string) ($first['periodo_ref'] ?? ''),
            'data_inicio' => (string) ($first['data_inicio'] ?? ''),
            'data_fim' => (string) ($first['data_fim'] ?? ''),
            'updated_at' => (string) ($first['updated_at'] ?? ''),
            'preview' => (int) ($first['preview'] ?? 0),
            'versao' => (int) ($first['versao'] ?? 1),
            'vigente' => (int) ($first['vigente'] ?? 1),
            'aluno_id' => (int) ($first['aluno_id'] ?? 0),
            'aluno_nome' => (string) ($first['aluno_nome'] ?? ''),
            'aluno_ra' => (string) ($first['aluno_ra'] ?? ''),
            'turma_nome' => (string) ($first['turma_nome'] ?? ''),
            'colunas' => [],
            'linhas' => [],
        ];
        $colsRaw = trim((string) ($first['colunas_json'] ?? ''));
        $decCols = $colsRaw !== '' ? json_decode($colsRaw, true) : [];
        if (is_array($decCols)) {
            $evento['colunas'] = $decCols;
        }
        foreach ($rows as $r) {
            $notasRaw = trim((string) ($r['notas_json'] ?? ''));
            $decNotas = $notasRaw !== '' ? json_decode($notasRaw, true) : [];
            $evento['linhas'][] = [
                'materia_id' => (int) ($r['materia_id'] ?? 0),
                'materia_nome' => (string) ($r['materia_nome'] ?? 'Sem matéria'),
                'notas' => is_array($decNotas) ? $decNotas : [],
            ];
        }
        return $evento;
    }

    /**
     * Normaliza uma data/hora vinda de filtro para usar em comparação com DATETIME.
     * Aceita "YYYY-MM-DD" (expande para 00:00:00 ou 23:59:59 conforme $endOfDay)
     * ou "YYYY-MM-DD HH:MM[:SS]" / "YYYY-MM-DDTHH:MM[:SS]". Retorna null se inválido.
     */
    private function normalizarDataHoraIntervalo(string $value, bool $endOfDay): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            return $value . ':' . ($endOfDay ? '59' : '00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }
        return null;
    }

    /**
     * Remove em lote vários boletins gerados (combinação aluno+regra+periodo).
     * Cada item do array deve ter as chaves aluno_id, regra_id e periodo_ref.
     * Retorna ['removidos' => int, 'itens' => int] com a contagem.
     */
    public function deleteGeneratedResultsLote(array $itens): array
    {
        $itensValidos = 0;
        $removidos = 0;
        foreach ($itens as $item) {
            if (!is_array($item)) { continue; }
            $alunoId = (int) ($item['aluno_id'] ?? 0);
            $regraId = (int) ($item['regra_id'] ?? 0);
            $periodoRef = trim((string) ($item['periodo_ref'] ?? ''));
            if ($alunoId <= 0 || $regraId <= 0 || $periodoRef === '') { continue; }
            $itensValidos++;
            $removidos += $this->deleteGeneratedResultForPeriodo($alunoId, $regraId, $periodoRef);
        }
        return ['removidos' => $removidos, 'itens' => $itensValidos];
    }

    /**
     * Remove resultados gerados de um período específico (aluno+regra+periodo).
     */
    public function deleteGeneratedResultForPeriodo(int $alunoId, int $regraId, string $periodoRef): int
    {
        $periodoRef = trim($periodoRef);
        if ($alunoId <= 0 || $regraId <= 0 || $periodoRef === '') {
            return 0;
        }
        try {
            $deleted = $this->db->delete(
                "DELETE FROM boletim_resultados_gerados
                 WHERE aluno_id = :aluno_id AND regra_id = :regra_id AND periodo_ref = :periodo_ref",
                [
                    'aluno_id' => $alunoId,
                    'regra_id' => $regraId,
                    'periodo_ref' => $periodoRef,
                ]
            );
            return is_int($deleted) ? $deleted : (int) ($deleted ?? 0);
        } catch (Throwable $e) {
            error_log('BoletimConfig deleteGeneratedResultForPeriodo a=' . $alunoId . ' r=' . $regraId . ' p=' . $periodoRef . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Remove TODOS os resultados gerados (preview e oficiais, em qualquer período)
     * de um aluno para uma regra de boletim específica. Útil para a coordenação
     * limpar manualmente um boletim que ficou "preso" na visão do aluno.
     *
     * Retorna o número de linhas removidas.
     */
    public function deleteGeneratedResultsForAluno(int $alunoId, int $regraId): int
    {
        if ($alunoId <= 0 || $regraId <= 0) {
            return 0;
        }
        try {
            $deleted = $this->db->delete(
                "DELETE FROM boletim_resultados_gerados
                 WHERE aluno_id = :aluno_id AND regra_id = :regra_id",
                [
                    'aluno_id' => $alunoId,
                    'regra_id' => $regraId,
                ]
            );
            return is_int($deleted) ? $deleted : (int) ($deleted ?? 0);
        } catch (Throwable $e) {
            error_log('BoletimConfig deleteGeneratedResultsForAluno aluno=' . $alunoId . ' regra=' . $regraId . ': ' . $e->getMessage());
            return 0;
        }
    }

    private function ensureSchemaVersionamento(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS boletim_geracoes (
                id INT NOT NULL AUTO_INCREMENT,
                regra_id INT NOT NULL,
                periodo_ref VARCHAR(60) NOT NULL,
                versao INT NOT NULL DEFAULT 1,
                vigente TINYINT(1) NOT NULL DEFAULT 1,
                modo VARCHAR(40) NOT NULL DEFAULT 'gerar',
                usuario_id INT NULL,
                usuario_nome VARCHAR(150) NULL,
                alunos_processados INT NOT NULL DEFAULT 0,
                alunos_preservados INT NOT NULL DEFAULT 0,
                linhas_geradas INT NOT NULL DEFAULT 0,
                erros INT NOT NULL DEFAULT 0,
                alunos_mudanca_significativa INT NOT NULL DEFAULT 0,
                detalhes_json TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_boletim_geracoes_regra_periodo_versao (regra_id, periodo_ref, versao),
                KEY idx_boletim_geracoes_regra_vigente (regra_id, periodo_ref, vigente),
                CONSTRAINT fk_boletim_geracoes_regra FOREIGN KEY (regra_id) REFERENCES boletim_regras(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS boletim_alunos_travados (
                id INT NOT NULL AUTO_INCREMENT,
                regra_id INT NOT NULL,
                aluno_id INT NOT NULL,
                periodo_ref VARCHAR(60) NOT NULL,
                motivo VARCHAR(255) NULL,
                usuario_id INT NULL,
                usuario_nome VARCHAR(150) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_boletim_alunos_travados (regra_id, aluno_id, periodo_ref),
                KEY idx_boletim_alunos_travados_regra (regra_id, periodo_ref),
                CONSTRAINT fk_boletim_alunos_travados_regra FOREIGN KEY (regra_id) REFERENCES boletim_regras(id) ON DELETE CASCADE,
                CONSTRAINT fk_boletim_alunos_travados_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureBoletimResultadoColumn('geracao_id', "ALTER TABLE boletim_resultados_gerados ADD COLUMN geracao_id INT NULL DEFAULT NULL AFTER preview");
        $this->ensureBoletimResultadoColumn('versao', "ALTER TABLE boletim_resultados_gerados ADD COLUMN versao INT NOT NULL DEFAULT 1 AFTER geracao_id");
        $this->ensureBoletimResultadoColumn('vigente', "ALTER TABLE boletim_resultados_gerados ADD COLUMN vigente TINYINT(1) NOT NULL DEFAULT 1 AFTER versao");

        try {
            $row = $this->db->fetch(
                "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'boletim_resultados_gerados'
                   AND INDEX_NAME = 'idx_boletim_resultados_vigente'
                 LIMIT 1"
            );
            if (!$row) {
                $this->db->query(
                    "ALTER TABLE boletim_resultados_gerados
                     ADD INDEX idx_boletim_resultados_vigente (regra_id, aluno_id, periodo_ref, vigente, preview)"
                );
            }
        } catch (Throwable $e) {
            error_log('BoletimConfig idx vigente: ' . $e->getMessage());
        }

        try {
            $row = $this->db->fetch(
                "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'boletim_resultados_gerados'
                   AND INDEX_NAME = 'idx_boletim_resultados_geracao'
                 LIMIT 1"
            );
            if (!$row) {
                $this->db->query(
                    "ALTER TABLE boletim_resultados_gerados
                     ADD INDEX idx_boletim_resultados_geracao (geracao_id)"
                );
            }
        } catch (Throwable $e) {
            error_log('BoletimConfig idx geracao: ' . $e->getMessage());
        }

        try {
            $fk = $this->db->fetch(
                "SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'boletim_resultados_gerados'
                   AND CONSTRAINT_NAME = 'fk_boletim_resultados_geracao'
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                 LIMIT 1"
            );
            if (!$fk) {
                $this->db->query(
                    "ALTER TABLE boletim_resultados_gerados
                     ADD CONSTRAINT fk_boletim_resultados_geracao
                     FOREIGN KEY (geracao_id) REFERENCES boletim_geracoes(id) ON DELETE SET NULL"
                );
            }
        } catch (Throwable $e) {
            error_log('BoletimConfig fk geracao: ' . $e->getMessage());
        }
    }

    private function ensureBoletimResultadoPreviewIndex(): void
    {
        try {
            $row = $this->db->fetch(
                "SELECT 1
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'boletim_resultados_gerados'
                   AND INDEX_NAME = 'idx_boletim_resultados_preview'
                 LIMIT 1"
            );
            if ($row) {
                return;
            }
            $this->db->query(
                "ALTER TABLE boletim_resultados_gerados
                 ADD INDEX idx_boletim_resultados_preview (regra_id, aluno_id, periodo_ref, preview)"
            );
        } catch (Throwable $e) {
            error_log('BoletimConfig ensureBoletimResultadoPreviewIndex: ' . $e->getMessage());
        }
    }

    public function getActiveRule(): ?array
    {
        $regra = $this->db->fetch(
            "SELECT * FROM boletim_regras WHERE ativo = 1 ORDER BY updated_at DESC, id DESC LIMIT 1"
        );

        if (!$regra) {
            return null;
        }

        $regra['componentes'] = $this->db->fetchAll(
            "SELECT * FROM boletim_componentes WHERE regra_id = :regra_id AND ativo = 1 ORDER BY ordem ASC, id ASC",
            ['regra_id' => (int) $regra['id']]
        );

        return $regra;
    }

    public function getRuleById(int $ruleId): ?array
    {
        if ($ruleId <= 0) {
            return null;
        }
        $regra = $this->db->fetch(
            "SELECT * FROM boletim_regras WHERE id = :id AND ativo = 1 LIMIT 1",
            ['id' => $ruleId]
        );
        if (!$regra) {
            return null;
        }
        $regra['componentes'] = $this->db->fetchAll(
            "SELECT * FROM boletim_componentes WHERE regra_id = :regra_id AND ativo = 1 ORDER BY ordem ASC, id ASC",
            ['regra_id' => (int) $regra['id']]
        );

        return $regra;
    }

    public function saveRule(
        string $nome,
        string $formulaFinal,
        array $componentes,
        ?int $regraId = null,
        ?string $descricaoCurta = null,
        ?string $materiasIdsJson = null,
        ?string $formulaMateriasJson = null,
        ?string $codigo = null,
        ?string $seriesIdsJson = null,
        ?string $turmasIdsJson = null,
        string $exibirEm = 'boletim',
        ?int $anoLetivo = null,
        ?int $bimestre = null,
        int $visAluno = 1,
        int $visPais = 1,
        int $visCoordenacao = 1,
        string $roundMode = 'none',
        int $decimalPlaces = 2,
        ?string $defaultDataInicio = null,
        ?string $defaultDataFim = null,
        ?float $notaMinimaAprovacao = null,
        int $usarResultadoAprovacao = 1,
        ?string $extrasJson = null
    ): int
    {
        $this->db->beginTransaction();

        try {
            if ($regraId !== null && $regraId > 0) {
                $this->db->update(
                    "UPDATE boletim_regras
                     SET nome = :nome, codigo = :codigo, descricao_curta = :descricao_curta, formula_final = :formula_final, formula_materias_json = :formula_materias_json, extras_json = :extras_json, materias_ids = :materias_ids, series_ids = :series_ids, turmas_ids = :turmas_ids, exibir_em = :exibir_em, ano_letivo = :ano_letivo, bimestre = :bimestre, nota_minima_aprovacao = :nota_minima_aprovacao, usar_resultado_aprovacao = :usar_resultado_aprovacao, vis_aluno = :vis_aluno, vis_pais = :vis_pais, vis_coordenacao = :vis_coordenacao, round_mode = :round_mode, decimal_places = :decimal_places, default_data_inicio = :default_data_inicio, default_data_fim = :default_data_fim, ativo = 1
                     WHERE id = :id",
                    [
                        'nome' => $nome,
                        'codigo' => $this->normalizeRuleCode($codigo, $nome),
                        'descricao_curta' => $this->trimNullableVarchar($descricaoCurta, 255),
                        'formula_final' => $formulaFinal !== '' ? $formulaFinal : null,
                        'formula_materias_json' => $this->trimConfigJson($formulaMateriasJson),
                        'extras_json' => $this->trimConfigJson($extrasJson),
                        'materias_ids' => $this->trimConfigJson($materiasIdsJson),
                        'series_ids' => $this->trimConfigJson($seriesIdsJson),
                        'turmas_ids' => $this->trimConfigJson($turmasIdsJson),
                        'exibir_em' => $this->normalizeExibirEm($exibirEm),
                        'ano_letivo' => $this->normalizeAnoLetivo($anoLetivo),
                        'bimestre' => $this->normalizeBimestre($bimestre),
                        'nota_minima_aprovacao' => $this->normalizeNotaMinimaAprovacao($notaMinimaAprovacao),
                        'usar_resultado_aprovacao' => $usarResultadoAprovacao ? 1 : 0,
                        'vis_aluno' => $visAluno ? 1 : 0,
                        'vis_pais' => $visPais ? 1 : 0,
                        'vis_coordenacao' => $visCoordenacao ? 1 : 0,
                        'round_mode' => $this->normalizeRoundMode($roundMode),
                        'decimal_places' => $this->normalizeDecimalPlaces($decimalPlaces),
                        'default_data_inicio' => $defaultDataInicio,
                        'default_data_fim' => $defaultDataFim,
                        'id' => $regraId,
                    ]
                );
            } else {
                $regraId = (int) $this->db->insert(
                    "INSERT INTO boletim_regras (nome, codigo, descricao_curta, formula_final, formula_materias_json, extras_json, materias_ids, series_ids, turmas_ids, exibir_em, ano_letivo, bimestre, nota_minima_aprovacao, usar_resultado_aprovacao, vis_aluno, vis_pais, vis_coordenacao, round_mode, decimal_places, default_data_inicio, default_data_fim, ativo)
                     VALUES (:nome, :codigo, :descricao_curta, :formula_final, :formula_materias_json, :extras_json, :materias_ids, :series_ids, :turmas_ids, :exibir_em, :ano_letivo, :bimestre, :nota_minima_aprovacao, :usar_resultado_aprovacao, :vis_aluno, :vis_pais, :vis_coordenacao, :round_mode, :decimal_places, :default_data_inicio, :default_data_fim, 1)",
                    [
                        'nome' => $nome,
                        'codigo' => $this->normalizeRuleCode($codigo, $nome),
                        'descricao_curta' => $this->trimNullableVarchar($descricaoCurta, 255),
                        'formula_final' => $formulaFinal !== '' ? $formulaFinal : null,
                        'formula_materias_json' => $this->trimConfigJson($formulaMateriasJson),
                        'extras_json' => $this->trimConfigJson($extrasJson),
                        'materias_ids' => $this->trimConfigJson($materiasIdsJson),
                        'series_ids' => $this->trimConfigJson($seriesIdsJson),
                        'turmas_ids' => $this->trimConfigJson($turmasIdsJson),
                        'exibir_em' => $this->normalizeExibirEm($exibirEm),
                        'ano_letivo' => $this->normalizeAnoLetivo($anoLetivo),
                        'bimestre' => $this->normalizeBimestre($bimestre),
                        'nota_minima_aprovacao' => $this->normalizeNotaMinimaAprovacao($notaMinimaAprovacao),
                        'usar_resultado_aprovacao' => $usarResultadoAprovacao ? 1 : 0,
                        'vis_aluno' => $visAluno ? 1 : 0,
                        'vis_pais' => $visPais ? 1 : 0,
                        'vis_coordenacao' => $visCoordenacao ? 1 : 0,
                        'round_mode' => $this->normalizeRoundMode($roundMode),
                        'decimal_places' => $this->normalizeDecimalPlaces($decimalPlaces),
                        'default_data_inicio' => $defaultDataInicio,
                        'default_data_fim' => $defaultDataFim,
                    ]
                );
            }

            // Mantém múltiplos eventos ativos simultaneamente.
            // Cada evento novo/edição já grava ativo=1 no próprio registro.

            $this->db->delete(
                "DELETE FROM boletim_componentes WHERE regra_id = :regra_id",
                ['regra_id' => $regraId]
            );

            foreach ($componentes as $idx => $componente) {
                $codigo = trim((string) ($componente['codigo'] ?? ''));
                $nomeComp = trim((string) ($componente['nome'] ?? ''));
                if ($codigo === '' || $nomeComp === '') {
                    continue;
                }

                $this->db->insert(
                    "INSERT INTO boletim_componentes
                    (regra_id, codigo, nome, source_type, calc_type, peso, filtro_titulo, bloco_id, blocos_ids, config_json, materia_id, materias_ids, materia_unica, usar_percentual, escala_max, obrigatorio, ativo, ordem)
                    VALUES
                    (:regra_id, :codigo, :nome, :source_type, :calc_type, :peso, :filtro_titulo, :bloco_id, :blocos_ids, :config_json, :materia_id, :materias_ids, :materia_unica, :usar_percentual, :escala_max, :obrigatorio, 1, :ordem)",
                    [
                        'regra_id' => $regraId,
                        'codigo' => $codigo,
                        'nome' => $nomeComp,
                        'source_type' => $this->normalizeSourceTypeForSave($componente['source_type'] ?? 'provas_sistema'),
                        'calc_type' => $this->normalizeCalcType($componente['calc_type'] ?? 'media'),
                        'peso' => (float) ($componente['peso'] ?? 1),
                        'filtro_titulo' => trim((string) ($componente['filtro_titulo'] ?? '')) ?: null,
                        'bloco_id' => !empty($componente['bloco_id']) ? (int) $componente['bloco_id'] : null,
                        'blocos_ids' => $this->trimBlocosIdsCsv($componente['blocos_ids'] ?? null),
                        'config_json' => $this->trimConfigJson($componente['config_json'] ?? null),
                        'materia_id' => !empty($componente['materia_id']) ? (int) $componente['materia_id'] : null,
                        'materias_ids' => $this->trimConfigJson($componente['materias_ids'] ?? null),
                        'materia_unica' => !empty($componente['materia_unica']) ? 1 : 0,
                        'usar_percentual' => !empty($componente['usar_percentual']) ? 1 : 0,
                        'escala_max' => max(0.01, (float) ($componente['escala_max'] ?? 10)),
                        'obrigatorio' => !empty($componente['obrigatorio']) ? 1 : 0,
                        'ordem' => (int) $idx,
                    ]
                );
            }

            $this->db->commit();
            return (int) $regraId;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function getRuleByCode(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        $regra = $this->db->fetch(
            "SELECT * FROM boletim_regras WHERE codigo = :codigo AND ativo = 1 ORDER BY updated_at DESC, id DESC LIMIT 1",
            ['codigo' => $codigo]
        );
        if (!$regra) {
            return null;
        }
        $regra['componentes'] = $this->db->fetchAll(
            "SELECT * FROM boletim_componentes WHERE regra_id = :regra_id AND ativo = 1 ORDER BY ordem ASC, id ASC",
            ['regra_id' => (int) $regra['id']]
        );

        return $regra;
    }

    public function listRulesCatalog(int $limit = 300): array
    {
        $limit = max(1, min($limit, 1000));
        return $this->db->fetchAll(
            "SELECT id, nome, codigo, updated_at, ativo, bimestre, series_ids
             FROM boletim_regras
             WHERE ativo = 1
               AND codigo IS NOT NULL AND codigo <> ''
             ORDER BY updated_at DESC, id DESC
             LIMIT {$limit}"
        ) ?: [];
    }

    /**
     * Lista todos os eventos de boletim ativos com os campos usados na tela de listagem.
     */
    public function listAllRules(int $limit = 300): array
    {
        $limit = max(1, min($limit, 1000));
        return $this->db->fetchAll(
            "SELECT id, nome, codigo, descricao_curta, exibir_em, ano_letivo, bimestre, series_ids, turmas_ids, vis_aluno, vis_pais, vis_coordenacao, updated_at
             FROM boletim_regras
             WHERE ativo = 1
             ORDER BY updated_at DESC, id DESC
             LIMIT {$limit}"
        ) ?: [];
    }

    /**
     * Data/hora da última geração em massa ("Gerar boletins") por regra_id.
     * @return array<int, string> regra_id => updated_at mais recente em boletim_resultados_gerados
     */
    public function getUltimaGeracaoPorRegra(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT regra_id, MAX(updated_at) AS ultima_geracao
             FROM boletim_resultados_gerados
             GROUP BY regra_id"
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $rid = (int) ($r['regra_id'] ?? 0);
            if ($rid > 0) {
                $out[$rid] = (string) ($r['ultima_geracao'] ?? '');
            }
        }
        return $out;
    }

    /**
     * Último job de geração em massa por regra (fila ai_jobs, tipo boletim_gerar).
     *
     * @return array<int, array{job_id:int,status:string,error:string,created_at:string,completed_at:string,mensagem:string}>
     */
    public function mapearStatusGeracaoAssincrona(): array
    {
        if (!$this->db->tableExists('ai_jobs')) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT id, status, payload, error_message, created_at, completed_at, result
             FROM ai_jobs
             WHERE job_type = :tipo
             ORDER BY id DESC
             LIMIT 80",
            ['tipo' => 'boletim_gerar']
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            $regraId = (int) ($payload['regra_id'] ?? 0);
            if ($regraId <= 0 || isset($out[$regraId])) {
                continue;
            }
            $result = json_decode((string) ($row['result'] ?? ''), true);
            $out[$regraId] = [
                'job_id' => (int) ($row['id'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'error' => (string) ($row['error_message'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'completed_at' => (string) ($row['completed_at'] ?? ''),
                'mensagem' => is_array($result) ? (string) ($result['mensagem'] ?? '') : '',
            ];
        }

        return $out;
    }

    public function temGeracaoEmAndamento(int $regraId): bool
    {
        if ($regraId <= 0 || !$this->db->tableExists('ai_jobs')) {
            return false;
        }
        $row = $this->db->fetch(
            "SELECT id
             FROM ai_jobs
             WHERE job_type = :tipo
               AND status IN ('pending', 'processing')
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.regra_id')) AS UNSIGNED) = :regra
             LIMIT 1",
            ['tipo' => 'boletim_gerar', 'regra' => $regraId]
        );

        return is_array($row) && (int) ($row['id'] ?? 0) > 0;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{job_id:int,status:string,error:string,mensagem:string}>
     */
    public function statusJobsGeracaoPorIds(array $ids): array
    {
        if (!$this->db->tableExists('ai_jobs')) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $params = ['tipo' => 'boletim_gerar'];
        $placeholders = [];
        foreach ($ids as $i => $id) {
            $key = 'id' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $rows = $this->db->fetchAll(
            'SELECT id, status, error_message, result
             FROM ai_jobs
             WHERE job_type = :tipo AND id IN (' . implode(',', $placeholders) . ')',
            $params
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $result = json_decode((string) ($row['result'] ?? ''), true);
            $jobId = (int) ($row['id'] ?? 0);
            $out[$jobId] = [
                'job_id' => $jobId,
                'status' => (string) ($row['status'] ?? ''),
                'error' => (string) ($row['error_message'] ?? ''),
                'mensagem' => is_array($result) ? (string) ($result['mensagem'] ?? '') : '',
            ];
        }

        return $out;
    }

    /**
     * Grava uma linha de auditoria de execução de "Gerar boletins".
     */
    public function registrarLogGeracao(
        int $regraId,
        string $periodoRef,
        ?int $usuarioId,
        ?string $usuarioNome,
        int $alunosProcessados,
        int $linhasGeradas,
        int $erros,
        int $alunosMudancaSignificativa,
        array $detalhes = []
    ): void {
        $this->db->insert(
            "INSERT INTO boletim_log_geracoes
            (regra_id, periodo_ref, usuario_id, usuario_nome, alunos_processados, linhas_geradas, erros, alunos_mudanca_significativa, detalhes_json)
            VALUES (:regra_id, :periodo_ref, :usuario_id, :usuario_nome, :alunos_processados, :linhas_geradas, :erros, :alunos_mudanca, :detalhes_json)",
            [
                'regra_id' => $regraId,
                'periodo_ref' => substr(trim($periodoRef), 0, 60),
                'usuario_id' => $usuarioId,
                'usuario_nome' => $usuarioNome !== null ? mb_substr($usuarioNome, 0, 150, 'UTF-8') : null,
                'alunos_processados' => $alunosProcessados,
                'linhas_geradas' => $linhasGeradas,
                'erros' => $erros,
                'alunos_mudanca' => $alunosMudancaSignificativa,
                'detalhes_json' => $detalhes !== [] ? json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]
        );
    }

    /**
     * Últimas gerações de um evento, mais recente primeiro.
     */
    public function getLogsGeracaoPorRegra(int $regraId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        return $this->db->fetchAll(
            "SELECT * FROM boletim_log_geracoes
             WHERE regra_id = :regra_id
             ORDER BY created_at DESC
             LIMIT {$limit}",
            ['regra_id' => $regraId]
        ) ?: [];
    }

    /**
     * Fragmento SQL para ler só a versão vigente. Vazio se a coluna ainda não existir.
     */
    public function sqlFiltroVigente(string $alias = 'g'): string
    {
        if (!$this->hasColumn('boletim_resultados_gerados', 'vigente')) {
            return '';
        }
        $prefixo = $alias === '' ? '' : ($alias . '.');
        return " AND {$prefixo}vigente = 1";
    }

    public function criarGeracao(
        int $regraId,
        string $periodoRef,
        string $modo,
        ?int $usuarioId,
        ?string $usuarioNome
    ): ?int {
        if ($regraId <= 0 || !$this->hasTable('boletim_geracoes')) {
            return null;
        }
        $periodoRef = substr(trim($periodoRef), 0, 60);
        $modos = ['gerar', 'atualizar', 'atualizar_previa', 'aluno', 'edicao'];
        if (!in_array($modo, $modos, true)) {
            $modo = 'gerar';
        }

        $max = $this->db->fetch(
            "SELECT MAX(versao) AS max_versao
             FROM boletim_geracoes
             WHERE regra_id = :regra_id AND periodo_ref = :periodo_ref",
            ['regra_id' => $regraId, 'periodo_ref' => $periodoRef]
        );
        $versao = (int) ($max['max_versao'] ?? 0) + 1;

        $lote = in_array($modo, ['gerar', 'atualizar', 'atualizar_previa'], true);
        if ($lote) {
            $this->db->update(
                "UPDATE boletim_geracoes
                 SET vigente = 0
                 WHERE regra_id = :regra_id AND periodo_ref = :periodo_ref AND vigente = 1",
                ['regra_id' => $regraId, 'periodo_ref' => $periodoRef]
            );
        }

        $id = (int) $this->db->insert(
            "INSERT INTO boletim_geracoes
            (regra_id, periodo_ref, versao, vigente, modo, usuario_id, usuario_nome)
            VALUES (:regra_id, :periodo_ref, :versao, :vigente, :modo, :usuario_id, :usuario_nome)",
            [
                'regra_id' => $regraId,
                'periodo_ref' => $periodoRef,
                'versao' => $versao,
                'vigente' => $lote ? 1 : 0,
                'modo' => $modo,
                'usuario_id' => $usuarioId,
                'usuario_nome' => $usuarioNome !== null ? mb_substr($usuarioNome, 0, 150, 'UTF-8') : null,
            ]
        );

        return $id > 0 ? $id : null;
    }

    public function atualizarGeracaoTotais(
        int $geracaoId,
        int $processados,
        int $preservados,
        int $linhas,
        int $erros,
        int $mudanca,
        array $detalhes = []
    ): void {
        if ($geracaoId <= 0 || !$this->hasTable('boletim_geracoes')) {
            return;
        }
        $this->db->update(
            "UPDATE boletim_geracoes
             SET alunos_processados = :processados,
                 alunos_preservados = :preservados,
                 linhas_geradas = :linhas,
                 erros = :erros,
                 alunos_mudanca_significativa = :mudanca,
                 detalhes_json = :detalhes_json
             WHERE id = :id",
            [
                'id' => $geracaoId,
                'processados' => $processados,
                'preservados' => $preservados,
                'linhas' => $linhas,
                'erros' => $erros,
                'mudanca' => $mudanca,
                'detalhes_json' => $detalhes !== [] ? json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarGeracoesPorRegra(int $regraId, string $periodoRef = '', int $limit = 20): array
    {
        if ($regraId <= 0 || !$this->hasTable('boletim_geracoes')) {
            return [];
        }
        $limit = max(1, min($limit, 50));
        $sql = "SELECT * FROM boletim_geracoes WHERE regra_id = :regra_id";
        $params = ['regra_id' => $regraId];
        if ($periodoRef !== '') {
            $sql .= " AND periodo_ref = :periodo_ref";
            $params['periodo_ref'] = substr(trim($periodoRef), 0, 60);
        }
        $sql .= " ORDER BY versao DESC, id DESC LIMIT {$limit}";

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    public function findGeracao(int $geracaoId): ?array
    {
        if ($geracaoId <= 0 || !$this->hasTable('boletim_geracoes')) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM boletim_geracoes WHERE id = :id LIMIT 1",
            ['id' => $geracaoId]
        );
        return $row ?: null;
    }

    /**
     * Alunos que participaram de uma geração (linhas gravadas naquele geracao_id).
     *
     * @return list<array{aluno_id:int,nome:string,versao:int,preservado:int}>
     */
    public function listarAlunosDaGeracao(int $geracaoId): array
    {
        if ($geracaoId <= 0 || !$this->hasColumn('boletim_resultados_gerados', 'geracao_id')) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT g.aluno_id, a.nome, MAX(g.versao) AS versao
             FROM boletim_resultados_gerados g
             INNER JOIN alunos a ON a.id = g.aluno_id
             WHERE g.geracao_id = :geracao_id AND g.preview = 0
             GROUP BY g.aluno_id, a.nome
             ORDER BY a.nome ASC",
            ['geracao_id' => $geracaoId]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'aluno_id' => (int) ($r['aluno_id'] ?? 0),
                'nome' => (string) ($r['nome'] ?? ''),
                'versao' => (int) ($r['versao'] ?? 0),
                'preservado' => 0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{versao:int,vigente:int,geracao_id:?int,created_at:string,usuario_nome:?string,modo:?string}>
     */
    public function listarVersoesAluno(int $regraId, int $alunoId, string $periodoRef): array
    {
        if ($regraId <= 0 || $alunoId <= 0 || !$this->hasColumn('boletim_resultados_gerados', 'versao')) {
            return [];
        }
        $periodoRef = substr(trim($periodoRef), 0, 60);
        if ($periodoRef === '') {
            return [];
        }
        $joinGeracao = $this->hasTable('boletim_geracoes')
            ? 'LEFT JOIN boletim_geracoes ge ON ge.id = g.geracao_id'
            : '';
        $selectExtra = $this->hasTable('boletim_geracoes')
            ? ', MAX(ge.usuario_nome) AS usuario_nome, MAX(ge.modo) AS modo'
            : ', NULL AS usuario_nome, NULL AS modo';

        return $this->db->fetchAll(
            "SELECT g.versao, MAX(g.vigente) AS vigente, MAX(g.geracao_id) AS geracao_id,
                    MAX(g.created_at) AS created_at {$selectExtra}
             FROM boletim_resultados_gerados g
             {$joinGeracao}
             WHERE g.regra_id = :regra_id AND g.aluno_id = :aluno_id
               AND g.periodo_ref = :periodo_ref AND g.preview = 0
             GROUP BY g.versao
             ORDER BY g.versao DESC",
            [
                'regra_id' => $regraId,
                'aluno_id' => $alunoId,
                'periodo_ref' => $periodoRef,
            ]
        ) ?: [];
    }

    /**
     * @return list<int>
     */
    public function idsAlunosTravados(int $regraId, string $periodoRef): array
    {
        if ($regraId <= 0 || !$this->hasTable('boletim_alunos_travados')) {
            return [];
        }
        $periodoRef = substr(trim($periodoRef), 0, 60);
        if ($periodoRef === '') {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT aluno_id FROM boletim_alunos_travados
             WHERE regra_id = :regra_id AND periodo_ref = :periodo_ref",
            ['regra_id' => $regraId, 'periodo_ref' => $periodoRef]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['aluno_id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }

    public function alunoEstaTravado(int $regraId, int $alunoId, string $periodoRef): bool
    {
        if ($regraId <= 0 || $alunoId <= 0 || !$this->hasTable('boletim_alunos_travados')) {
            return false;
        }
        $periodoRef = substr(trim($periodoRef), 0, 60);
        $row = $this->db->fetch(
            "SELECT 1 FROM boletim_alunos_travados
             WHERE regra_id = :regra_id AND aluno_id = :aluno_id AND periodo_ref = :periodo_ref
             LIMIT 1",
            ['regra_id' => $regraId, 'aluno_id' => $alunoId, 'periodo_ref' => $periodoRef]
        );

        return $row !== false && !empty($row);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarAlunosTravados(int $regraId, string $periodoRef): array
    {
        if ($regraId <= 0 || !$this->hasTable('boletim_alunos_travados')) {
            return [];
        }
        $periodoRef = substr(trim($periodoRef), 0, 60);

        return $this->db->fetchAll(
            "SELECT t.aluno_id, t.motivo, t.usuario_nome, t.created_at, a.nome AS aluno_nome
             FROM boletim_alunos_travados t
             INNER JOIN alunos a ON a.id = t.aluno_id
             WHERE t.regra_id = :regra_id AND t.periodo_ref = :periodo_ref
             ORDER BY a.nome ASC",
            ['regra_id' => $regraId, 'periodo_ref' => $periodoRef]
        ) ?: [];
    }

    public function travarAluno(
        int $regraId,
        int $alunoId,
        string $periodoRef,
        ?string $motivo,
        ?int $usuarioId,
        ?string $usuarioNome
    ): bool {
        if ($regraId <= 0 || $alunoId <= 0 || !$this->hasTable('boletim_alunos_travados')) {
            return false;
        }
        $periodoRef = substr(trim($periodoRef), 0, 60);
        if ($periodoRef === '') {
            return false;
        }
        $motivo = $motivo !== null ? mb_substr(trim($motivo), 0, 255, 'UTF-8') : null;
        $this->db->query(
            "INSERT INTO boletim_alunos_travados
            (regra_id, aluno_id, periodo_ref, motivo, usuario_id, usuario_nome)
            VALUES (:regra_id, :aluno_id, :periodo_ref, :motivo, :usuario_id, :usuario_nome)
            ON DUPLICATE KEY UPDATE
                motivo = VALUES(motivo),
                usuario_id = VALUES(usuario_id),
                usuario_nome = VALUES(usuario_nome)",
            [
                'regra_id' => $regraId,
                'aluno_id' => $alunoId,
                'periodo_ref' => $periodoRef,
                'motivo' => $motivo !== '' ? $motivo : null,
                'usuario_id' => $usuarioId,
                'usuario_nome' => $usuarioNome !== null ? mb_substr($usuarioNome, 0, 150, 'UTF-8') : null,
            ]
        );

        return true;
    }

    public function destravarAluno(int $regraId, int $alunoId, string $periodoRef): bool
    {
        if ($regraId <= 0 || $alunoId <= 0 || !$this->hasTable('boletim_alunos_travados')) {
            return false;
        }
        $periodoRef = substr(trim($periodoRef), 0, 60);
        $n = $this->db->delete(
            "DELETE FROM boletim_alunos_travados
             WHERE regra_id = :regra_id AND aluno_id = :aluno_id AND periodo_ref = :periodo_ref",
            ['regra_id' => $regraId, 'aluno_id' => $alunoId, 'periodo_ref' => $periodoRef]
        );

        return (int) $n > 0;
    }

    /**
     * Código do componente marcado como "final" (layout.group = final) de uma regra,
     * usado para comparar a nota final antes/depois de uma geração em massa.
     */
    public function getComponenteFinalCodigo(int $regraId): ?string
    {
        $row = $this->db->fetch(
            "SELECT codigo FROM boletim_componentes
             WHERE regra_id = :regra_id AND ativo = 1
               AND JSON_UNQUOTE(JSON_EXTRACT(config_json, '$.layout.group')) = 'final'
             ORDER BY ordem DESC
             LIMIT 1",
            ['regra_id' => $regraId]
        );
        return $row && !empty($row['codigo']) ? (string) $row['codigo'] : null;
    }

    /**
     * Média (entre matérias) do valor da coluna final, por aluno, para o snapshot
     * já gravado de uma regra/período — usado para comparar "antes x depois" de gerar.
     * @return array<int, float> aluno_id => média do componente final
     */
    public function getMediaFinalPorAluno(int $regraId, string $periodoRef, string $codigoFinal): array
    {
        if ($codigoFinal === '') {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT aluno_id, JSON_UNQUOTE(JSON_EXTRACT(notas_json, :path)) AS valor
             FROM boletim_resultados_gerados
             WHERE regra_id = :regra_id AND periodo_ref = :periodo_ref AND preview = 0"
            . $this->sqlFiltroVigente(''),
            [
                'regra_id' => $regraId,
                'periodo_ref' => $periodoRef,
                'path' => '$.' . $codigoFinal,
            ]
        ) ?: [];
        $somaPorAluno = [];
        $qtdPorAluno = [];
        foreach ($rows as $r) {
            $alunoId = (int) ($r['aluno_id'] ?? 0);
            $valor = $r['valor'] ?? null;
            if ($alunoId <= 0 || $valor === null || $valor === '' || !is_numeric($valor)) {
                continue;
            }
            $somaPorAluno[$alunoId] = ($somaPorAluno[$alunoId] ?? 0) + (float) $valor;
            $qtdPorAluno[$alunoId] = ($qtdPorAluno[$alunoId] ?? 0) + 1;
        }
        $out = [];
        foreach ($somaPorAluno as $alunoId => $soma) {
            $qtd = $qtdPorAluno[$alunoId] ?? 0;
            if ($qtd > 0) {
                $out[$alunoId] = $soma / $qtd;
            }
        }
        return $out;
    }

    /**
     * Desativa o evento (soft delete): some do catálogo e das telas que filtram ativo = 1.
     */
    public function deactivateRule(int $ruleId): bool
    {
        if ($ruleId <= 0) {
            return false;
        }
        $n = (int) $this->db->update(
            "UPDATE boletim_regras SET ativo = 0 WHERE id = :id AND ativo = 1",
            ['id' => $ruleId]
        );

        return $n > 0;
    }

    public function updateRuleVisibility(int $ruleId, int $visAluno, int $visPais, ?int $visCoordenacao = null): bool
    {
        if ($ruleId <= 0) {
            return false;
        }

        $set = [
            'vis_aluno = :vis_aluno',
            'vis_pais = :vis_pais',
        ];
        $params = [
            'id' => $ruleId,
            'vis_aluno' => $visAluno ? 1 : 0,
            'vis_pais' => $visPais ? 1 : 0,
        ];
        if ($visCoordenacao !== null) {
            $set[] = 'vis_coordenacao = :vis_coordenacao';
            $params['vis_coordenacao'] = $visCoordenacao ? 1 : 0;
        }

        $n = (int) $this->db->update(
            "UPDATE boletim_regras
             SET " . implode(', ', $set) . "
             WHERE id = :id AND ativo = 1",
            $params
        );

        return $n > 0;
    }

    /**
     * Duplica um evento de boletim (regra + componentes) gerando um novo código único.
     * Retorna o id do novo evento, ou null se o evento original não existir.
     */
    public function duplicateRule(int $ruleId): ?int
    {
        $regra = $this->db->fetch(
            "SELECT * FROM boletim_regras WHERE id = :id AND ativo = 1",
            ['id' => $ruleId]
        );
        if (!$regra) {
            return null;
        }

        $novoNome = trim((string) $regra['nome']) . ' (cópia)';
        $novoCodigo = $this->gerarCodigoCopiaUnico((string) ($regra['codigo'] ?? ''), $novoNome);

        $this->db->beginTransaction();
        try {
            $novoId = (int) $this->db->insert(
                "INSERT INTO boletim_regras
                (nome, codigo, descricao_curta, formula_final, formula_materias_json, extras_json, materias_ids, series_ids, turmas_ids, exibir_em, ano_letivo, bimestre, nota_minima_aprovacao, usar_resultado_aprovacao, vis_aluno, vis_pais, vis_coordenacao, round_mode, decimal_places, default_data_inicio, default_data_fim, ativo)
                SELECT :nome, :codigo, descricao_curta, formula_final, formula_materias_json, extras_json, materias_ids, series_ids, turmas_ids, exibir_em, ano_letivo, bimestre, nota_minima_aprovacao, usar_resultado_aprovacao, vis_aluno, vis_pais, vis_coordenacao, round_mode, decimal_places, default_data_inicio, default_data_fim, 1
                FROM boletim_regras WHERE id = :id",
                ['nome' => $novoNome, 'codigo' => $novoCodigo, 'id' => $ruleId]
            );

            $this->db->insert(
                "INSERT INTO boletim_componentes
                (regra_id, codigo, nome, source_type, calc_type, peso, filtro_titulo, bloco_id, blocos_ids, config_json, materia_id, materias_ids, materia_unica, usar_percentual, escala_max, obrigatorio, ativo, ordem)
                SELECT :novo_regra_id, codigo, nome, source_type, calc_type, peso, filtro_titulo, bloco_id, blocos_ids, config_json, materia_id, materias_ids, materia_unica, usar_percentual, escala_max, obrigatorio, ativo, ordem
                FROM boletim_componentes WHERE regra_id = :regra_id AND ativo = 1",
                ['novo_regra_id' => $novoId, 'regra_id' => $ruleId]
            );

            $this->db->commit();
            return $novoId;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function gerarCodigoCopiaUnico(string $codigoOriginal, string $nomeFallback): string
    {
        $base = $codigoOriginal !== '' ? $codigoOriginal : $this->normalizeRuleCode('', $nomeFallback);
        $base = preg_replace('/-copia(-\d+)?$/', '', (string) $base);
        $candidato = $base . '-copia';
        $i = 1;
        while ($this->db->fetch("SELECT id FROM boletim_regras WHERE codigo = :codigo", ['codigo' => $candidato])) {
            $i++;
            $candidato = $base . '-copia-' . $i;
        }
        return $candidato;
    }

    public function getAvailableSeries(int $limit = 200): array
    {
        $limit = max(1, min($limit, 1000));
        return $this->db->fetchAll(
            "SELECT s.id, s.nome, s.curso_id, c.nome AS curso_nome
             FROM serie s
             LEFT JOIN curso c ON c.id = s.curso_id
             WHERE s.ativo = 1
             ORDER BY c.nome ASC, s.ordem ASC, s.nome ASC
             LIMIT {$limit}"
        ) ?: [];
    }

    public function getAvailableClasses(int $limit = 500): array
    {
        $limit = max(1, min($limit, 2000));
        if ($this->hasColumn('turmas', 'serie_id')) {
            return $this->db->fetchAll(
                "SELECT t.id, t.nome, t.serie_id, t.ano_letivo,
                        s.nome AS serie_nome, c.nome AS curso_nome
                 FROM turmas t
                 LEFT JOIN serie s ON s.id = t.serie_id
                 LEFT JOIN curso c ON c.id = s.curso_id
                 WHERE (t.ativo = 1 OR t.ativo IS NULL)
                 ORDER BY t.ano_letivo DESC, c.nome ASC, s.ordem ASC, s.nome ASC, t.nome ASC
                 LIMIT {$limit}"
            ) ?: [];
        }

        $serieNome = $this->hasColumn('turmas', 'serie') ? 't.serie' : 'NULL';
        return $this->db->fetchAll(
            "SELECT t.id, t.nome, NULL AS serie_id, t.ano_letivo,
                    {$serieNome} AS serie_nome, NULL AS curso_nome
             FROM turmas t
             WHERE (t.ativo = 1 OR t.ativo IS NULL)
             ORDER BY t.ano_letivo DESC, t.nome ASC
             LIMIT {$limit}"
        ) ?: [];
    }

    public function getStudentsList(int $limit = 300): array
    {
        $limit = max(1, min($limit, 10000));
        $serieSelect = $this->hasColumn('turmas', 'serie_id')
            ? 't.serie_id'
            : 'NULL AS serie_id';
        return $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome, {$serieSelect}
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.ativo = 1
             ORDER BY a.nome ASC
             LIMIT {$limit}"
        );
    }

    /**
     * @param list<int> $seriesIds
     * @return list<array{id:int,nome:string,ra:?string,turma_id:int,turma_nome:?string,serie_id:?int}>
     */
    public function getStudentsListBySeries(array $seriesIds, int $limit = 3000): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $seriesIds), static function ($v) {
            return $v > 0;
        })));
        if ($ids === []) {
            return $this->getStudentsList(min(1000, $limit));
        }
        $limit = max(1, min($limit, 10000));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($this->hasColumn('turmas', 'serie_id')) {
            return $this->db->fetchAll(
                "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome, t.serie_id
                 FROM alunos a
                 INNER JOIN turmas t ON t.id = a.turma_id
                 WHERE a.ativo = 1
                   AND t.serie_id IN ($placeholders)
                 ORDER BY a.nome ASC
                 LIMIT {$limit}",
                $ids
            ) ?: [];
        }

        if ($this->hasColumn('turmas', 'serie') && $this->hasTable('serie')) {
            return $this->db->fetchAll(
                "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome, s.id AS serie_id
                 FROM alunos a
                 INNER JOIN turmas t ON t.id = a.turma_id
                 INNER JOIN serie s ON s.nome = t.serie
                 WHERE a.ativo = 1
                   AND s.id IN ($placeholders)
                 ORDER BY a.nome ASC
                 LIMIT {$limit}",
                $ids
            ) ?: [];
        }

        return $this->getStudentsList(min(1000, $limit));
    }

    /**
     * @param list<int> $turmasIds
     * @return list<array{id:int,nome:string,ra:?string,turma_id:int,turma_nome:?string,serie_id:?int}>
     */
    public function getStudentsListByClasses(array $turmasIds, int $limit = 5000): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $turmasIds), static function ($v) {
            return $v > 0;
        })));
        if ($ids === []) {
            return $this->getStudentsList(min(1000, $limit));
        }
        $limit = max(1, min($limit, 10000));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, $ids);
        $matriculaSql = '';
        if ($this->hasTable('matricula')) {
            $matriculaSql = " OR EXISTS (
                SELECT 1 FROM matricula ma
                WHERE ma.aluno_id = a.id
                  AND ma.turma_id IN ($placeholders)
                  AND ma.status = 'ativa'
                  AND ma.data_saida IS NULL
            )";
        } else {
            $params = $ids;
        }

        $serieSelect = $this->hasColumn('turmas', 'serie_id')
            ? 't.serie_id'
            : 'NULL AS serie_id';

        return $this->db->fetchAll(
            "SELECT DISTINCT a.id, a.nome, a.ra, a.turma_id,
                    t.nome AS turma_nome, {$serieSelect}
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.ativo = 1
               AND (a.turma_id IN ($placeholders){$matriculaSql})
             ORDER BY a.nome ASC
             LIMIT {$limit}",
            $params
        ) ?: [];
    }

    /**
     * Grava as linhas da matriz por matéria para aluno/regra/período.
     * Preview: substitui só as linhas de prévia.
     * Oficial: cria uma nova versão vigente e mantém as anteriores para auditoria.
     *
     * @param list<array<string,mixed>> $colunas
     * @param list<array<string,mixed>> $linhas
     */
    public function replaceGeneratedResultsForAluno(
        int $regraId,
        int $alunoId,
        string $periodoRef,
        ?string $dataInicio,
        ?string $dataFim,
        array $colunas,
        array $linhas,
        bool $preview = false,
        ?int $geracaoId = null
    ): void {
        $this->replaceGeneratedResultsEmLote(
            $regraId,
            $periodoRef,
            $dataInicio,
            $dataFim,
            [[
                'aluno_id' => $alunoId,
                'colunas' => $colunas,
                'linhas' => $linhas,
            ]],
            $preview,
            $geracaoId
        );
    }

    /**
     * Grava resultados de vários alunos numa transação: versiona em lote e
     * INSERT de {@see TAMANHO_LOTE_INSERT_RESULTADOS} linhas por vez.
     *
     * @param list<array{aluno_id:int, colunas?:list<array<string,mixed>>, linhas?:list<array<string,mixed>>}> $itens
     */
    public function replaceGeneratedResultsEmLote(
        int $regraId,
        string $periodoRef,
        ?string $dataInicio,
        ?string $dataFim,
        array $itens,
        bool $preview = false,
        ?int $geracaoId = null
    ): void {
        $periodoRef = trim($periodoRef);
        if (strlen($periodoRef) > 60) {
            $periodoRef = substr($periodoRef, 0, 60);
        }
        if ($regraId <= 0 || $periodoRef === '' || $itens === []) {
            return;
        }

        $porAluno = [];
        foreach ($itens as $item) {
            if (!is_array($item)) {
                continue;
            }
            $alunoId = (int) ($item['aluno_id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            $porAluno[$alunoId] = $item;
        }
        $alunoIds = array_keys($porAluno);
        if ($alunoIds === []) {
            return;
        }

        $versionar = !$preview && $this->hasColumn('boletim_resultados_gerados', 'versao');
        $temVersao = $this->hasColumn('boletim_resultados_gerados', 'versao');
        $temGeracaoCol = $temVersao && $this->hasColumn('boletim_resultados_gerados', 'geracao_id');
        $in = $this->sqlInIds($alunoIds, 'aid_');

        $this->db->beginTransaction();
        try {
            $baseParams = [
                'regra_id' => $regraId,
                'periodo_ref' => $periodoRef,
            ] + $in['params'];

            if ($preview) {
                $this->db->delete(
                    "DELETE FROM boletim_resultados_gerados
                     WHERE regra_id = :regra_id AND periodo_ref = :periodo_ref
                       AND preview = 1 AND aluno_id IN ({$in['sql']})",
                    $baseParams
                );
            } elseif (!$versionar) {
                $this->db->delete(
                    "DELETE FROM boletim_resultados_gerados
                     WHERE regra_id = :regra_id AND periodo_ref = :periodo_ref
                       AND aluno_id IN ({$in['sql']})",
                    $baseParams
                );
            }

            $versaoPorAluno = [];
            if ($versionar) {
                $this->db->delete(
                    "DELETE FROM boletim_resultados_gerados
                     WHERE regra_id = :regra_id AND periodo_ref = :periodo_ref
                       AND preview = 1 AND aluno_id IN ({$in['sql']})",
                    $baseParams
                );
                $maxRows = $this->db->fetchAll(
                    "SELECT aluno_id, MAX(versao) AS max_versao
                     FROM boletim_resultados_gerados
                     WHERE regra_id = :regra_id AND periodo_ref = :periodo_ref
                       AND preview = 0 AND aluno_id IN ({$in['sql']})
                     GROUP BY aluno_id",
                    $baseParams
                ) ?: [];
                foreach ($maxRows as $rowMax) {
                    $versaoPorAluno[(int) ($rowMax['aluno_id'] ?? 0)] = (int) ($rowMax['max_versao'] ?? 0) + 1;
                }
                $this->db->update(
                    "UPDATE boletim_resultados_gerados
                     SET vigente = 0
                     WHERE regra_id = :regra_id AND periodo_ref = :periodo_ref
                       AND preview = 0 AND vigente = 1 AND aluno_id IN ({$in['sql']})",
                    $baseParams
                );
            }

            $registros = [];
            foreach ($porAluno as $alunoId => $item) {
                $versao = $versaoPorAluno[$alunoId] ?? 1;
                foreach ($this->montarRegistrosResultadoAluno(
                    $regraId,
                    (int) $alunoId,
                    $periodoRef,
                    $dataInicio,
                    $dataFim,
                    is_array($item['colunas'] ?? null) ? $item['colunas'] : [],
                    is_array($item['linhas'] ?? null) ? $item['linhas'] : [],
                    $preview,
                    $geracaoId,
                    $versao,
                    $temVersao,
                    $temGeracaoCol
                ) as $registro) {
                    $registros[] = $registro;
                }
            }
            $this->inserirResultadosGeradosEmLote($registros, $temVersao);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * @param list<int> $ids
     * @return array{sql: string, params: array<string, int>}
     */
    private function sqlInIds(array $ids, string $prefix): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        $sql = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $key = $prefix . $i;
            $sql[] = ':' . $key;
            $params[$key] = $id;
        }

        return ['sql' => implode(',', $sql), 'params' => $params];
    }

    /**
     * @param list<array<string,mixed>> $colunas
     * @param list<array<string,mixed>> $linhas
     * @return list<array<string, mixed>>
     */
    private function montarRegistrosResultadoAluno(
        int $regraId,
        int $alunoId,
        string $periodoRef,
        ?string $dataInicio,
        ?string $dataFim,
        array $colunas,
        array $linhas,
        bool $preview,
        ?int $geracaoId,
        int $versao,
        bool $temVersao,
        bool $temGeracaoCol
    ): array {
        if ($linhas === []) {
            return [];
        }
        $colunasJsonTrim = $this->trimConfigJson(json_encode($colunas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ordem = 0;
        $registros = [];
        foreach ($linhas as $lin) {
            if (!is_array($lin)) {
                continue;
            }
            $materiaId = (int) ($lin['materia_id'] ?? 0);
            $materiaNome = trim((string) ($lin['materia_nome'] ?? 'Sem matéria'));
            $notas = is_array($lin['notas'] ?? null) ? $lin['notas'] : [];
            $mediaFinal = null;
            if (isset($notas['media_final']) && is_numeric($notas['media_final'])) {
                $mediaFinal = (float) $notas['media_final'];
            } elseif (isset($lin['nota_resumo']) && is_numeric($lin['nota_resumo'])) {
                $mediaFinal = (float) $lin['nota_resumo'];
            }
            if ($mediaFinal !== null && !isset($notas['media_final'])) {
                $notas['media_final'] = $mediaFinal;
            }
            $registro = [
                'regra_id' => $regraId,
                'aluno_id' => $alunoId,
                'periodo_ref' => $periodoRef,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'materia_id' => $materiaId > 0 ? $materiaId : null,
                'materia_nome' => $materiaNome !== '' ? $materiaNome : 'Sem matéria',
                'materia_ref' => (string) $alunoId,
                'ordem_linha' => $ordem++,
                'colunas_json' => $colunasJsonTrim,
                'notas_json' => $this->trimConfigJson(json_encode($notas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'media_final' => $mediaFinal,
                'preview' => $preview ? 1 : 0,
            ];
            if ($temVersao) {
                $registro['geracao_id'] = ($temGeracaoCol && $geracaoId !== null && $geracaoId > 0) ? $geracaoId : null;
                $registro['versao'] = $preview ? 0 : $versao;
                $registro['vigente'] = $preview ? 0 : 1;
            }
            $registros[] = $registro;
        }

        return $registros;
    }

    /**
     * Insere linhas de boletim_resultados_gerados em lotes (prepared statement).
     *
     * @param list<array<string, mixed>> $registros
     */
    private function inserirResultadosGeradosEmLote(array $registros, bool $comVersao): void
    {
        if ($registros === []) {
            return;
        }

        $chaves = [
            'regra_id', 'aluno_id', 'periodo_ref', 'data_inicio', 'data_fim',
            'materia_id', 'materia_nome', 'materia_ref', 'ordem_linha',
            'colunas_json', 'notas_json', 'media_final', 'preview',
        ];
        if ($comVersao) {
            $chaves[] = 'geracao_id';
            $chaves[] = 'versao';
            $chaves[] = 'vigente';
        }
        $colunasSql = implode(', ', $chaves);

        foreach (array_chunk($registros, self::TAMANHO_LOTE_INSERT_RESULTADOS) as $lote) {
            $valuesSql = [];
            $params = [];
            foreach ($lote as $i => $row) {
                $placeholders = [];
                foreach ($chaves as $chave) {
                    $nome = $chave . '_' . $i;
                    $placeholders[] = ':' . $nome;
                    $params[$nome] = $row[$chave] ?? null;
                }
                $valuesSql[] = '(' . implode(', ', $placeholders) . ')';
            }
            $this->db->insert(
                'INSERT INTO boletim_resultados_gerados (' . $colunasSql . ') VALUES ' . implode(', ', $valuesSql),
                $params
            );
        }
    }

    /**
     * Indica se já existe boletim oficial gravado (não preview) pra esse aluno
     * nesse evento/período — usado pra decidir se uma sobrescrita manual deve
     * re-gravar automaticamente o boletim oficial já publicado.
     */
    public function hasOfficialResult(int $regraId, int $alunoId, string $periodoRef): bool
    {
        if ($regraId <= 0 || $alunoId <= 0) {
            return false;
        }
        $periodoRef = trim($periodoRef);
        if (strlen($periodoRef) > 60) {
            $periodoRef = substr($periodoRef, 0, 60);
        }
        if ($periodoRef === '') {
            return false;
        }
        $row = $this->db->fetch(
            "SELECT 1 FROM boletim_resultados_gerados
             WHERE regra_id = :regra_id AND aluno_id = :aluno_id AND periodo_ref = :periodo_ref AND preview = 0"
            . $this->sqlFiltroVigente('') . "
             LIMIT 1",
            ['regra_id' => $regraId, 'aluno_id' => $alunoId, 'periodo_ref' => $periodoRef]
        );
        return $row !== false && !empty($row);
    }

    /**
     * IDs de alunos que já têm boletim oficial gravado (não preview) para regra + período.
     *
     * @return list<int>
     */
    public function listAlunoIdsWithOfficialBoletim(int $regraId, string $periodoRef): array
    {
        return $this->listAlunoIdsWithGeneratedBoletim($regraId, $periodoRef, false);
    }

    /**
     * IDs de alunos que já têm boletim gravado para regra + período.
     *
     * @return list<int>
     */
    public function listAlunoIdsWithGeneratedBoletim(int $regraId, string $periodoRef, ?bool $preview = null): array
    {
        if ($regraId <= 0) {
            return [];
        }
        $periodoRef = trim($periodoRef);
        if (strlen($periodoRef) > 60) {
            $periodoRef = substr($periodoRef, 0, 60);
        }
        if ($periodoRef === '') {
            return [];
        }
        $previewSql = '';
        $params = [
            'regra_id' => $regraId,
            'periodo_ref' => $periodoRef,
        ];
        if ($preview !== null) {
            $previewSql = ' AND preview = :preview_flag';
            $params['preview_flag'] = $preview ? 1 : 0;
        }
        if ($preview === false && $this->hasColumn('boletim_resultados_gerados', 'vigente')) {
            $previewSql .= ' AND vigente = 1';
        }
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT aluno_id
             FROM boletim_resultados_gerados
             WHERE regra_id = :regra_id
               AND periodo_ref = :periodo_ref
               {$previewSql}
             ORDER BY aluno_id ASC",
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['aluno_id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * Nome/id para exibir em mensagens ao processar um conjunto fixo de alunos.
     *
     * @param list<int> $ids
     * @return list<array{id:int,nome:string}>
     */
    public function getStudentsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($v) {
            return $v > 0;
        })));
        if ($ids === []) {
            return [];
        }
        $limit = max(1, min(count($ids), 10000));
        $ids = array_slice($ids, 0, $limit);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id, nome FROM alunos WHERE id IN ($placeholders) ORDER BY nome ASC",
            $ids
        ) ?: [];
        $byId = [];
        foreach ($rows as $r) {
            $aid = (int) ($r['id'] ?? 0);
            if ($aid > 0) {
                $byId[$aid] = [
                    'id' => $aid,
                    'nome' => trim((string) ($r['nome'] ?? '')) ?: ('Aluno #' . $aid),
                ];
            }
        }
        $out = [];
        foreach ($ids as $aid) {
            if (isset($byId[$aid])) {
                $out[] = $byId[$aid];
            } else {
                $out[] = ['id' => $aid, 'nome' => 'Aluno #' . $aid];
            }
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getGeneratedBoletinsByAluno(int $alunoId, string $perfilVisibilidade, ?string $exibirEm = null): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        $perfil = strtolower(trim($perfilVisibilidade));
        $visCol = $perfil === 'pais' ? 'r.vis_pais' : ($perfil === 'coordenacao' ? 'r.vis_coordenacao' : 'r.vis_aluno');
        $exibirFilter = null;
        if ($exibirEm !== null) {
            $tmp = strtolower(trim($exibirEm));
            if (in_array($tmp, ['notas', 'boletim'], true)) {
                $exibirFilter = $tmp;
            }
        }

        $sql = "SELECT g.*, r.nome AS regra_nome, r.codigo AS regra_codigo, r.exibir_em, r.decimal_places, r.bimestre AS regra_bimestre, r.ano_letivo AS regra_ano_letivo
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             WHERE g.aluno_id = :aluno_id
               AND g.preview = 0
               AND r.ativo = 1
               AND {$visCol} = 1"
            . $this->sqlFiltroVigente('g');
        $params = ['aluno_id' => $alunoId];
        if ($exibirFilter !== null) {
            $sql .= " AND r.exibir_em = :exibir_em";
            $params['exibir_em'] = $exibirFilter;
        }
        $sql .= " ORDER BY g.updated_at DESC, g.regra_id DESC, g.ordem_linha ASC";

        $rows = $this->db->fetchAll(
            $sql,
            $params
        ) ?: [];

        $eventos = [];
        foreach ($rows as $r) {
            $k = (int) ($r['regra_id'] ?? 0) . '|' . (string) ($r['periodo_ref'] ?? '');
            if (!isset($eventos[$k])) {
                $eventos[$k] = [
                    'regra_id' => (int) ($r['regra_id'] ?? 0),
                    'regra_nome' => (string) ($r['regra_nome'] ?? 'Evento'),
                    'regra_codigo' => (string) ($r['regra_codigo'] ?? ''),
                    'exibir_em' => (string) ($r['exibir_em'] ?? 'boletim'),
                    'bimestre' => $r['regra_bimestre'] !== null ? (int) $r['regra_bimestre'] : null,
                    'ano_letivo' => $r['regra_ano_letivo'] !== null ? (int) $r['regra_ano_letivo'] : null,
                    'decimal_places' => $this->normalizeDecimalPlaces((int) ($r['decimal_places'] ?? 2)),
                    'periodo_ref' => (string) ($r['periodo_ref'] ?? ''),
                    'data_inicio' => (string) ($r['data_inicio'] ?? ''),
                    'data_fim' => (string) ($r['data_fim'] ?? ''),
                    'colunas' => [],
                    'linhas' => [],
                    'updated_at' => (string) ($r['updated_at'] ?? ''),
                ];
                $colsRaw = trim((string) ($r['colunas_json'] ?? ''));
                $decCols = $colsRaw !== '' ? json_decode($colsRaw, true) : [];
                if (is_array($decCols)) {
                    $eventos[$k]['colunas'] = $decCols;
                }
            }
            $notasRaw = trim((string) ($r['notas_json'] ?? ''));
            $decNotas = $notasRaw !== '' ? json_decode($notasRaw, true) : [];
            $eventos[$k]['linhas'][] = [
                'materia_id' => (int) ($r['materia_id'] ?? 0),
                'materia_nome' => (string) ($r['materia_nome'] ?? 'Sem matéria'),
                'notas' => is_array($decNotas) ? $decNotas : [],
            ];
        }

        $maisRecentes = [];
        foreach (array_values($eventos) as $evento) {
            $escopoKey = implode('|', [
                strtolower(trim((string) ($evento['exibir_em'] ?? 'boletim'))),
                mb_strtolower(trim((string) ($evento['regra_nome'] ?? '')), 'UTF-8'),
                (string) ((int) ($evento['ano_letivo'] ?? 0)),
                (string) ((int) ($evento['bimestre'] ?? 0)),
            ]);
            $updatedAt = strtotime((string) ($evento['updated_at'] ?? '')) ?: 0;
            $atual = $maisRecentes[$escopoKey] ?? null;
            $atualUpdatedAt = is_array($atual) ? (strtotime((string) ($atual['updated_at'] ?? '')) ?: 0) : -1;

            if (
                $atual === null
                || $updatedAt > $atualUpdatedAt
                || ($updatedAt === $atualUpdatedAt && (int) ($evento['regra_id'] ?? 0) > (int) ($atual['regra_id'] ?? 0))
            ) {
                $maisRecentes[$escopoKey] = $evento;
            }
        }

        $eventosFiltrados = array_values($maisRecentes);
        usort($eventosFiltrados, static function (array $a, array $b): int {
            $bUpdatedAt = strtotime((string) ($b['updated_at'] ?? '')) ?: 0;
            $aUpdatedAt = strtotime((string) ($a['updated_at'] ?? '')) ?: 0;
            if ($bUpdatedAt !== $aUpdatedAt) {
                return $bUpdatedAt <=> $aUpdatedAt;
            }
            return (int) ($b['regra_id'] ?? 0) <=> (int) ($a['regra_id'] ?? 0);
        });

        return $eventosFiltrados;
    }

    public function getGeneratedBoletimByAlunoAndRegra(int $alunoId, int $regraId): ?array
    {
        if ($alunoId <= 0 || $regraId <= 0) {
            return null;
        }
        $rowRef = $this->db->fetch(
            "SELECT periodo_ref, MAX(updated_at) AS updated_at
             FROM boletim_resultados_gerados
             WHERE aluno_id = :aluno_id AND regra_id = :regra_id AND preview = 0"
            . $this->sqlFiltroVigente('') . "
             GROUP BY periodo_ref
             ORDER BY updated_at DESC
             LIMIT 1",
            [
                'aluno_id' => $alunoId,
                'regra_id' => $regraId,
            ]
        );
        if (!$rowRef) {
            return null;
        }

        $periodoRef = (string) ($rowRef['periodo_ref'] ?? '');
        if ($periodoRef === '') {
            return null;
        }

        $rows = $this->db->fetchAll(
            "SELECT g.*, r.nome AS regra_nome, r.codigo AS regra_codigo, r.decimal_places
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             WHERE g.aluno_id = :aluno_id
               AND g.regra_id = :regra_id
               AND g.periodo_ref = :periodo_ref
               AND g.preview = 0"
            . $this->sqlFiltroVigente('g') . "
             ORDER BY g.ordem_linha ASC",
            [
                'aluno_id' => $alunoId,
                'regra_id' => $regraId,
                'periodo_ref' => $periodoRef,
            ]
        ) ?: [];
        if ($rows === []) {
            return null;
        }

        $first = $rows[0];
        $ev = [
            'regra_id' => (int) ($first['regra_id'] ?? 0),
            'regra_nome' => (string) ($first['regra_nome'] ?? 'Evento'),
            'regra_codigo' => (string) ($first['regra_codigo'] ?? ''),
            'decimal_places' => $this->normalizeDecimalPlaces((int) ($first['decimal_places'] ?? 2)),
            'periodo_ref' => (string) ($first['periodo_ref'] ?? ''),
            'data_inicio' => (string) ($first['data_inicio'] ?? ''),
            'data_fim' => (string) ($first['data_fim'] ?? ''),
            'colunas' => [],
            'linhas' => [],
            'updated_at' => (string) ($first['updated_at'] ?? ''),
        ];
        $colsRaw = trim((string) ($first['colunas_json'] ?? ''));
        $decCols = $colsRaw !== '' ? json_decode($colsRaw, true) : [];
        if (is_array($decCols)) {
            $ev['colunas'] = $decCols;
        }
        foreach ($rows as $r) {
            $notasRaw = trim((string) ($r['notas_json'] ?? ''));
            $decNotas = $notasRaw !== '' ? json_decode($notasRaw, true) : [];
            $ev['linhas'][] = [
                'materia_id' => (int) ($r['materia_id'] ?? 0),
                'materia_nome' => (string) ($r['materia_nome'] ?? 'Sem matéria'),
                'notas' => is_array($decNotas) ? $decNotas : [],
            ];
        }

        return $ev;
    }

    public function getManualNote(int $componenteId, int $alunoId, string $periodoRef, int $materiaId = 0): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM boletim_notas_manuais
             WHERE componente_id = :componente_id
               AND aluno_id = :aluno_id
               AND periodo_ref = :periodo_ref
               AND materia_id = :materia_id
             LIMIT 1",
            [
                'componente_id' => $componenteId,
                'aluno_id' => $alunoId,
                'periodo_ref' => $periodoRef,
                'materia_id' => $materiaId,
            ]
        );
        return $row ?: null;
    }

    /**
     * Lançamentos manuais por matéria de um componente (ex.: sobrescrita da Média
     * Final calculada, matéria a matéria, pra um aluno que ingressou sem dados de
     * origem em algumas matérias).
     *
     * @return array<int, array<string, mixed>> chave = materia_id
     */
    public function getManualNotesByComponente(int $componenteId, int $alunoId, string $periodoRef): array
    {
        if ($componenteId <= 0 || $alunoId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT * FROM boletim_notas_manuais
             WHERE componente_id = :componente_id
               AND aluno_id = :aluno_id
               AND periodo_ref = :periodo_ref
               AND materia_id <> 0",
            [
                'componente_id' => $componenteId,
                'aluno_id' => $alunoId,
                'periodo_ref' => $periodoRef,
            ]
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[(int) ($r['materia_id'] ?? 0)] = $r;
        }
        return $out;
    }

    /**
     * @param list<int> $componenteIds
     * @param list<int> $alunoIds
     * @return array<int, array<int, array<int, array<string, mixed>>>> [componente_id][aluno_id][materia_id]
     */
    public function getManualNotesPorAlunos(array $componenteIds, array $alunoIds, string $periodoRef): array
    {
        $componenteIds = array_values(array_unique(array_filter(array_map('intval', $componenteIds), static function ($id) {
            return $id > 0;
        })));
        $alunoIds = array_values(array_unique(array_filter(array_map('intval', $alunoIds), static function ($id) {
            return $id > 0;
        })));
        $periodoRef = substr(trim($periodoRef), 0, 60);
        if ($componenteIds === [] || $alunoIds === [] || $periodoRef === '') {
            return [];
        }
        $inComp = $this->sqlInIds($componenteIds, 'c_');
        $inAluno = $this->sqlInIds($alunoIds, 'a_');
        $rows = $this->db->fetchAll(
            "SELECT * FROM boletim_notas_manuais
             WHERE periodo_ref = :periodo_ref
               AND componente_id IN ({$inComp['sql']})
               AND aluno_id IN ({$inAluno['sql']})",
            ['periodo_ref' => $periodoRef] + $inComp['params'] + $inAluno['params']
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $cid = (int) ($r['componente_id'] ?? 0);
            $aid = (int) ($r['aluno_id'] ?? 0);
            $mid = (int) ($r['materia_id'] ?? 0);
            $out[$cid][$aid][$mid] = $r;
        }

        return $out;
    }

    /**
     * Lista lançamentos manuais do mesmo componente/aluno em outros períodos (útil quando a simulação usa periodo_ref diferente do lançamento).
     *
     * @return list<array{periodo_ref: string, nota: float, updated_at: ?string}>
     */
    public function listManualNotesOtherPeriods(int $componenteId, int $alunoId, string $periodoRefAtual, int $limit = 8): array
    {
        if ($componenteId <= 0 || $alunoId <= 0) {
            return [];
        }
        $periodoRefAtual = trim($periodoRefAtual);
        $limit = max(1, min(20, $limit));
        $rows = $this->db->fetchAll(
            "SELECT periodo_ref, nota, updated_at
             FROM boletim_notas_manuais
             WHERE componente_id = :componente_id
               AND aluno_id = :aluno_id
               AND periodo_ref <> :periodo_ref
             ORDER BY updated_at DESC
             LIMIT {$limit}",
            [
                'componente_id' => $componenteId,
                'aluno_id' => $alunoId,
                'periodo_ref' => $periodoRefAtual,
            ]
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'periodo_ref' => (string) ($r['periodo_ref'] ?? ''),
                'nota' => (float) ($r['nota'] ?? 0),
                'updated_at' => isset($r['updated_at']) ? (string) $r['updated_at'] : null,
            ];
        }

        return $out;
    }

    public function removerNotaManual(int $componenteId, int $alunoId, string $periodoRef, int $materiaId = 0): array
    {
        $existente = $this->getManualNote($componenteId, $alunoId, $periodoRef, $materiaId);
        if (!$existente) {
            return ['success' => true, 'removed' => false];
        }
        if ((int) ($existente['bloqueado'] ?? 0) === 1) {
            return ['success' => false, 'locked' => true, 'message' => 'Nota bloqueada para edição.'];
        }
        $this->db->query("DELETE FROM boletim_notas_manuais WHERE id = :id", ['id' => (int) $existente['id']]);
        return ['success' => true, 'removed' => true];
    }

    public function saveManualNote(array $payload): array
    {
        $materiaId = (int) ($payload['materia_id'] ?? 0);
        $existente = $this->getManualNote((int) $payload['componente_id'], (int) $payload['aluno_id'], (string) $payload['periodo_ref'], $materiaId);
        if ($existente && (int) ($existente['bloqueado'] ?? 0) === 1) {
            return [
                'success' => false,
                'locked' => true,
                'message' => 'Nota bloqueada para edição.',
            ];
        }

        if ($existente) {
            $this->db->update(
                "UPDATE boletim_notas_manuais
                 SET nota = :nota,
                     bloqueado = :bloqueado,
                     observacao = :observacao,
                     updated_at = NOW()
                 WHERE id = :id",
                [
                    'nota' => $payload['nota'],
                    'bloqueado' => !empty($payload['bloqueado']) ? 1 : 0,
                    'observacao' => $payload['observacao'] ?? null,
                    'id' => (int) $existente['id'],
                ]
            );

            return ['success' => true, 'updated' => true, 'id' => (int) $existente['id']];
        }

        $id = $this->db->insert(
            "INSERT INTO boletim_notas_manuais
             (regra_id, componente_id, aluno_id, materia_id, periodo_ref, nota, bloqueado, observacao)
             VALUES
             (:regra_id, :componente_id, :aluno_id, :materia_id, :periodo_ref, :nota, :bloqueado, :observacao)",
            [
                'regra_id' => (int) $payload['regra_id'],
                'componente_id' => (int) $payload['componente_id'],
                'aluno_id' => (int) $payload['aluno_id'],
                'materia_id' => $materiaId,
                'periodo_ref' => (string) $payload['periodo_ref'],
                'nota' => $payload['nota'] === null ? null : (float) $payload['nota'],
                'bloqueado' => !empty($payload['bloqueado']) ? 1 : 0,
                'observacao' => $payload['observacao'] ?? null,
            ]
        );

        return ['success' => true, 'updated' => false, 'id' => (int) $id];
    }

    public function getProvasFinalizadasByAluno(int $alunoId, ?string $inicio, ?string $fim, ?string $filtroTitulo = null, ?int $materiaId = null): array
    {
                $sql = "SELECT
                    pr.prova_id,
                    pr.aluno_id,
                    pr.nota,
                    pr.finalizado_em,
                    p.materia_id,
                    m.nome AS materia_nome,
                    p.titulo,
                    p.valor_total,
                    (SELECT COUNT(*) FROM provas_respostas rr
                      WHERE rr.prova_id = pr.prova_id AND rr.aluno_id = pr.aluno_id) AS total_questoes,
                    (SELECT COUNT(*) FROM provas_respostas rr
                      WHERE rr.prova_id = pr.prova_id AND rr.aluno_id = pr.aluno_id AND rr.correta = 1) AS acertos
                FROM provas_realizacoes pr
                INNER JOIN provas p ON p.id = pr.prova_id
                LEFT JOIN materias m ON m.id = p.materia_id
                WHERE pr.aluno_id = :aluno_id
                  AND pr.status = 'finalizado'";

        $params = ['aluno_id' => $alunoId];

        if ($inicio !== null && $fim !== null) {
            $iniD = substr((string) $inicio, 0, 10);
            $fimD = substr((string) $fim, 0, 10);
            $sql .= " AND (
                    (pr.finalizado_em IS NOT NULL AND pr.finalizado_em BETWEEN :inicio AND :fim)
                    OR (
                        p.data_inicio IS NOT NULL AND p.data_fim IS NOT NULL
                        AND CAST(p.data_fim AS DATE) >= :ini_d
                        AND CAST(p.data_inicio AS DATE) <= :fim_d
                    )
                )";
            $params['inicio'] = $inicio;
            $params['fim'] = $fim;
            $params['ini_d'] = $iniD;
            $params['fim_d'] = $fimD;
        }

        if ($filtroTitulo !== null && trim($filtroTitulo) !== '') {
            $terms = preg_split('/[|,]+/', trim($filtroTitulo), -1, PREG_SPLIT_NO_EMPTY);
            $terms = array_values(array_filter(array_map('trim', $terms)));
            if (count($terms) === 1) {
                $sql .= ' AND p.titulo LIKE :titulo';
                $params['titulo'] = '%' . $terms[0] . '%';
            } elseif (count($terms) > 1) {
                $ors = [];
                $ti = 0;
                foreach ($terms as $t) {
                    if ($t === '') {
                        continue;
                    }
                    $key = 'titulo_f_' . $ti++;
                    $ors[] = 'p.titulo LIKE :' . $key;
                    $params[$key] = '%' . $t . '%';
                }
                if ($ors !== []) {
                    $sql .= ' AND (' . implode(' OR ', $ors) . ')';
                }
            }
        }
        if ($materiaId !== null && $materiaId > 0) {
            $sql .= " AND p.materia_id = :materia_id";
            $params['materia_id'] = $materiaId;
        }

        $sql .= " ORDER BY pr.finalizado_em DESC, pr.id DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function getProvasFinalizadasByAlunoAndBloco(int $alunoId, int $blocoId, ?string $inicio, ?string $fim, ?int $materiaId = null): array
    {
        $sql = "SELECT
                    pr.prova_id,
                    pr.aluno_id,
                    pr.nota,
                    pr.finalizado_em,
                    p.materia_id,
                    m.nome AS materia_nome,
                    p.titulo,
                    p.valor_total,
                    (SELECT COUNT(*) FROM provas_respostas rr
                      WHERE rr.prova_id = pr.prova_id AND rr.aluno_id = pr.aluno_id) AS total_questoes,
                    (SELECT COUNT(*) FROM provas_respostas rr
                      WHERE rr.prova_id = pr.prova_id AND rr.aluno_id = pr.aluno_id AND rr.correta = 1) AS acertos
                FROM provas_realizacoes pr
                INNER JOIN provas p ON p.id = pr.prova_id
                LEFT JOIN materias m ON m.id = p.materia_id
                INNER JOIN provas_blocos_vinculo pbv ON pbv.prova_id = pr.prova_id
                INNER JOIN provas_blocos pb ON pb.id = pbv.bloco_id AND pb.deleted_at IS NULL
                WHERE pr.aluno_id = :aluno_id
                  AND pbv.bloco_id = :bloco_id
                  AND pr.status = 'finalizado'";

        $params = [
            'aluno_id' => $alunoId,
            'bloco_id' => $blocoId,
        ];

        if ($inicio !== null && $fim !== null) {
            $sql .= " AND (
                    (pr.finalizado_em IS NOT NULL AND pr.finalizado_em BETWEEN :inicio AND :fim)
                    OR (
                        pb.data_prova IS NOT NULL
                        AND CAST(pb.data_prova AS CHAR) <> '0000-00-00'
                        AND pb.data_prova BETWEEN DATE(:inicio2) AND DATE(:fim2)
                    )
                )";
            $params['inicio'] = $inicio;
            $params['fim'] = $fim;
            $params['inicio2'] = $inicio;
            $params['fim2'] = $fim;
        }
        if ($materiaId !== null && $materiaId > 0) {
            $sql .= " AND p.materia_id = :materia_id";
            $params['materia_id'] = $materiaId;
        }

        $sql .= " ORDER BY pr.finalizado_em DESC, pr.id DESC";
        $online = $this->db->fetchAll($sql, $params) ?: [];
        $manual = $this->hasNotasLancadasBlocosTable()
            ? $this->fetchNotasLancadasPorBlocosAluno($alunoId, [$blocoId], $inicio, $fim, $materiaId)
            : [];

        return $this->mergeProvasOnlineENotasLancadas($online, $manual);
    }

    /**
     * Provas finalizadas vinculadas a qualquer um dos blocos informados (união).
     * Inclui notas de blocos em formato lançamento na pauta (provas_blocos_notas_lancadas).
     * Provas repetidas em mais de um bloco entram uma vez (DISTINCT por realização).
     *
     * O parâmetro $filtroTitulo é ignorado: o escopo já é definido pelos blocos e o título da prova
     * raramente repete o nome do bloco (ex.: bloco "Avaliação Bimestral" com provas sem a palavra "bimestral").
     *
     * @param list<int> $blocoIds
     * @return list<array<string,mixed>>
     */
    public function getProvasFinalizadasByAlunoAndBlocos(
        int $alunoId,
        array $blocoIds,
        ?string $inicio,
        ?string $fim,
        ?string $filtroTitulo = null,
        ?int $materiaId = null
    ): array {
        $blocoIds = array_values(array_unique(array_filter(array_map('intval', $blocoIds), static function ($id) {
            return $id > 0;
        })));
        if (empty($blocoIds)) {
            return [];
        }
        $max = 40;
        if (count($blocoIds) > $max) {
            $blocoIds = array_slice($blocoIds, 0, $max);
        }
        $placeholders = implode(',', array_fill(0, count($blocoIds), '?'));

        $selectNotaUnica = $this->hasColumn('provas_blocos', 'nota_unica_todas_materias')
            ? 'pb.nota_unica_todas_materias'
            : '0 AS nota_unica_todas_materias';

        $sql = "SELECT DISTINCT
                    pr.id,
                    pr.prova_id,
                    pr.aluno_id,
                    pr.nota,
                    pr.finalizado_em,
                    pb.id AS bloco_id,
                    {$selectNotaUnica},
                    p.materia_id,
                    m.nome AS materia_nome,
                    p.titulo,
                    p.valor_total,
                    (SELECT COUNT(*) FROM provas_respostas rr
                      WHERE rr.prova_id = pr.prova_id AND rr.aluno_id = pr.aluno_id) AS total_questoes,
                    (SELECT COUNT(*) FROM provas_respostas rr
                      WHERE rr.prova_id = pr.prova_id AND rr.aluno_id = pr.aluno_id AND rr.correta = 1) AS acertos
                FROM provas_realizacoes pr
                INNER JOIN provas p ON p.id = pr.prova_id
                LEFT JOIN materias m ON m.id = p.materia_id
                INNER JOIN provas_blocos_vinculo pbv ON pbv.prova_id = pr.prova_id
                INNER JOIN provas_blocos pb ON pb.id = pbv.bloco_id AND pb.deleted_at IS NULL
                WHERE pr.aluno_id = ?
                  AND pbv.bloco_id IN ($placeholders)
                  AND pr.status = 'finalizado'";

        if ($inicio !== null && $fim !== null) {
            $sql .= ' AND (
                    (pr.finalizado_em IS NOT NULL AND pr.finalizado_em BETWEEN ? AND ?)
                    OR (
                        pb.data_prova IS NOT NULL
                        AND CAST(pb.data_prova AS CHAR) <> \'0000-00-00\'
                        AND pb.data_prova BETWEEN DATE(?) AND DATE(?)
                    )
                )';
        }
        if ($materiaId !== null && $materiaId > 0) {
            $sql .= ' AND p.materia_id = ?';
        }
        $sql .= ' ORDER BY pr.finalizado_em DESC, pr.id DESC';

        $execParams = array_merge([$alunoId], $blocoIds);
        if ($inicio !== null && $fim !== null) {
            $execParams[] = $inicio;
            $execParams[] = $fim;
            $execParams[] = $inicio;
            $execParams[] = $fim;
        }
        if ($materiaId !== null && $materiaId > 0) {
            $execParams[] = $materiaId;
        }

        $online = $this->db->fetchAll($sql, $execParams) ?: [];
        $manual = $this->hasNotasLancadasBlocosTable()
            ? $this->fetchNotasLancadasPorBlocosAluno($alunoId, $blocoIds, $inicio, $fim, $materiaId)
            : [];

        return $this->mergeProvasOnlineENotasLancadas($online, $manual);
    }

    /**
     * Provas finalizadas de vários alunos nos mesmos blocos, agrupadas por aluno_id.
     *
     * @param list<int> $alunoIds
     * @param list<int> $blocoIds
     * @return array<int, list<array<string,mixed>>>
     */
    public function getProvasFinalizadasPorAlunosAndBlocos(
        array $alunoIds,
        array $blocoIds,
        ?string $inicio,
        ?string $fim,
        ?string $filtroTitulo = null,
        ?int $materiaId = null,
        bool $incluirEstatisticasQuestoes = false
    ): array {
        $alunoIds = array_values(array_unique(array_filter(array_map('intval', $alunoIds), static function ($id) {
            return $id > 0;
        })));
        $blocoIds = array_values(array_unique(array_filter(array_map('intval', $blocoIds), static function ($id) {
            return $id > 0;
        })));
        if ($alunoIds === [] || $blocoIds === []) {
            return [];
        }
        if (count($blocoIds) > 40) {
            $blocoIds = array_slice($blocoIds, 0, 40);
        }

        $phAlunos = implode(',', array_fill(0, count($alunoIds), '?'));
        $phBlocos = implode(',', array_fill(0, count($blocoIds), '?'));
        $selectNotaUnica = $this->hasColumn('provas_blocos', 'nota_unica_todas_materias')
            ? 'pb.nota_unica_todas_materias'
            : '0 AS nota_unica_todas_materias';
        $statsSelect = '0 AS total_questoes, 0 AS acertos';
        $statsJoin = '';
        if ($incluirEstatisticasQuestoes) {
            $statsSelect = 'COALESCE(st.total_questoes, 0) AS total_questoes, COALESCE(st.acertos, 0) AS acertos';
            $statsJoin = "LEFT JOIN (
                    SELECT prova_id, aluno_id,
                           COUNT(*) AS total_questoes,
                           SUM(CASE WHEN correta = 1 THEN 1 ELSE 0 END) AS acertos
                    FROM provas_respostas
                    WHERE aluno_id IN ($phAlunos)
                    GROUP BY prova_id, aluno_id
                 ) st ON st.prova_id = pr.prova_id AND st.aluno_id = pr.aluno_id";
        }

        $sql = "SELECT DISTINCT
                    pr.id,
                    pr.prova_id,
                    pr.aluno_id,
                    pr.nota,
                    pr.finalizado_em,
                    pb.id AS bloco_id,
                    {$selectNotaUnica},
                    p.materia_id,
                    m.nome AS materia_nome,
                    p.titulo,
                    p.valor_total,
                    {$statsSelect}
                FROM provas_realizacoes pr
                INNER JOIN provas p ON p.id = pr.prova_id
                LEFT JOIN materias m ON m.id = p.materia_id
                INNER JOIN provas_blocos_vinculo pbv ON pbv.prova_id = pr.prova_id
                INNER JOIN provas_blocos pb ON pb.id = pbv.bloco_id AND pb.deleted_at IS NULL
                {$statsJoin}
                WHERE pr.aluno_id IN ($phAlunos)
                  AND pbv.bloco_id IN ($phBlocos)
                  AND pr.status = 'finalizado'";

        if ($inicio !== null && $fim !== null) {
            $sql .= ' AND (
                    (pr.finalizado_em IS NOT NULL AND pr.finalizado_em BETWEEN ? AND ?)
                    OR (
                        pb.data_prova IS NOT NULL
                        AND CAST(pb.data_prova AS CHAR) <> \'0000-00-00\'
                        AND pb.data_prova BETWEEN DATE(?) AND DATE(?)
                    )
                )';
        }
        if ($materiaId !== null && $materiaId > 0) {
            $sql .= ' AND p.materia_id = ?';
        }
        $sql .= ' ORDER BY pr.aluno_id ASC, pr.finalizado_em DESC, pr.id DESC';

        $execParams = $incluirEstatisticasQuestoes
            ? array_merge($alunoIds, $alunoIds, $blocoIds)
            : array_merge($alunoIds, $blocoIds);
        if ($inicio !== null && $fim !== null) {
            $execParams[] = $inicio;
            $execParams[] = $fim;
            $execParams[] = $inicio;
            $execParams[] = $fim;
        }
        if ($materiaId !== null && $materiaId > 0) {
            $execParams[] = $materiaId;
        }

        $online = $this->db->fetchAll($sql, $execParams) ?: [];
        $manual = $this->hasNotasLancadasBlocosTable()
            ? $this->fetchNotasLancadasPorBlocosAlunos($alunoIds, $blocoIds, $inicio, $fim, $materiaId)
            : [];
        $merged = $this->mergeProvasOnlineENotasLancadas($online, $manual);
        $porAluno = [];
        foreach ($merged as $row) {
            $aid = (int) ($row['aluno_id'] ?? 0);
            if ($aid > 0) {
                $porAluno[$aid][] = $row;
            }
        }

        return $porAluno;
    }

    /**
     * Blocos em formato "lançamento de nota" usam {@see provas_blocos_notas_lancadas}, não provas_realizacoes.
     */
    private function hasNotasLancadasBlocosTable(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'provas_blocos_notas_lancadas'");
            $cache = $row !== false && $row !== null;

            return $cache;
        } catch (Throwable $e) {
            error_log('BoletimConfig hasNotasLancadasBlocosTable: ' . $e->getMessage());
            $cache = false;

            return false;
        }
    }

    /**
     * Notas digitadas na pauta do bloco (evento lançamento_nota), no mesmo escopo de datas que provas online.
     *
     * @param list<int> $blocoIds
     * @return list<array<string,mixed>>
     */
    private function fetchNotasLancadasPorBlocosAluno(
        int $alunoId,
        array $blocoIds,
        ?string $inicio,
        ?string $fim,
        ?int $materiaId
    ): array {
        $blocoIds = array_values(array_unique(array_filter(array_map('intval', $blocoIds), static function ($id) {
            return $id > 0;
        })));
        if ($blocoIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($blocoIds), '?'));
        $selectNotaUnica = $this->hasColumn('provas_blocos', 'nota_unica_todas_materias')
            ? 'pb.nota_unica_todas_materias'
            : '0 AS nota_unica_todas_materias';

        $sql = "SELECT
                    n.id,
                    n.bloco_id,
                    n.professor_id,
                    (2000000000 + n.id) AS prova_id,
                    n.aluno_id,
                    n.nota,
                    COALESCE(n.updated_at, CONCAT(pb.data_prova, ' 12:00:00')) AS finalizado_em,
                    {$selectNotaUnica},
                    pb.data_prova,
                    NULLIF(n.materia_id, 0) AS nota_materia_id,
                    m.nome AS nota_materia_nome,
                    (
                        SELECT pbp2.materia_id
                        FROM provas_blocos_professores pbp2
                        WHERE pbp2.bloco_id = n.bloco_id
                          AND pbp2.professor_id = n.professor_id
                        ORDER BY (pbp2.materia_id = n.materia_id) DESC, pbp2.id ASC
                        LIMIT 1
                    ) AS professor_materia_id,
                    (
                        SELECT m2.nome
                        FROM provas_blocos_professores pbp2
                        INNER JOIN materias m2 ON m2.id = pbp2.materia_id
                        WHERE pbp2.bloco_id = n.bloco_id
                          AND pbp2.professor_id = n.professor_id
                        ORDER BY (pbp2.materia_id = n.materia_id) DESC, pbp2.id ASC
                        LIMIT 1
                    ) AS professor_materia_nome,
                    COALESCE(NULLIF(n.materia_id, 0), (
                        SELECT pbp2.materia_id
                        FROM provas_blocos_professores pbp2
                        WHERE pbp2.bloco_id = n.bloco_id
                          AND pbp2.professor_id = n.professor_id
                        ORDER BY (pbp2.materia_id = n.materia_id) DESC, pbp2.id ASC
                        LIMIT 1
                    )) AS materia_id,
                    COALESCE(m.nome, (
                        SELECT m2.nome
                        FROM provas_blocos_professores pbp2
                        INNER JOIN materias m2 ON m2.id = pbp2.materia_id
                        WHERE pbp2.bloco_id = n.bloco_id
                          AND pbp2.professor_id = n.professor_id
                        ORDER BY (pbp2.materia_id = n.materia_id) DESC, pbp2.id ASC
                        LIMIT 1
                    )) AS materia_nome,
                    CONCAT(COALESCE(pb.titulo, 'Bloco'), ' (pauta)') AS titulo,
                    10 AS valor_total,
                    0 AS total_questoes,
                    0 AS acertos
                FROM provas_blocos_notas_lancadas n
                INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.deleted_at IS NULL
                LEFT JOIN materias m ON m.id = n.materia_id
                WHERE n.aluno_id = ?
                  AND n.bloco_id IN ($placeholders)
                  AND n.nota IS NOT NULL";

        $params = array_merge([$alunoId], $blocoIds);
        if ($inicio !== null && $fim !== null) {
            $sql .= ' AND (
                    (n.updated_at IS NOT NULL AND n.updated_at BETWEEN ? AND ?)
                    OR (
                        pb.data_prova IS NOT NULL
                        AND CAST(pb.data_prova AS CHAR) <> \'0000-00-00\'
                        AND pb.data_prova BETWEEN DATE(?) AND DATE(?)
                    )
                )';
            $params[] = $inicio;
            $params[] = $fim;
            $params[] = $inicio;
            $params[] = $fim;
        }
        if ($materiaId !== null && $materiaId > 0) {
            $sql .= ' AND n.materia_id = ?';
            $params[] = $materiaId;
        }
        $sql .= ' ORDER BY finalizado_em DESC, n.id DESC';

        try {
            return $this->db->fetchAll($sql, $params) ?: [];
        } catch (Throwable $e) {
            error_log('BoletimConfig fetchNotasLancadasPorBlocosAluno: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @param list<int> $alunoIds
     * @param list<int> $blocoIds
     * @return list<array<string,mixed>>
     */
    private function fetchNotasLancadasPorBlocosAlunos(
        array $alunoIds,
        array $blocoIds,
        ?string $inicio,
        ?string $fim,
        ?int $materiaId
    ): array {
        $alunoIds = array_values(array_unique(array_filter(array_map('intval', $alunoIds), static function ($id) {
            return $id > 0;
        })));
        $blocoIds = array_values(array_unique(array_filter(array_map('intval', $blocoIds), static function ($id) {
            return $id > 0;
        })));
        if ($alunoIds === [] || $blocoIds === []) {
            return [];
        }
        $phAlunos = implode(',', array_fill(0, count($alunoIds), '?'));
        $phBlocos = implode(',', array_fill(0, count($blocoIds), '?'));
        $selectNotaUnica = $this->hasColumn('provas_blocos', 'nota_unica_todas_materias')
            ? 'pb.nota_unica_todas_materias'
            : '0 AS nota_unica_todas_materias';

        $sql = "SELECT
                    n.id,
                    n.bloco_id,
                    n.professor_id,
                    (2000000000 + n.id) AS prova_id,
                    n.aluno_id,
                    n.nota,
                    COALESCE(n.updated_at, CONCAT(pb.data_prova, ' 12:00:00')) AS finalizado_em,
                    {$selectNotaUnica},
                    pb.data_prova,
                    NULLIF(n.materia_id, 0) AS nota_materia_id,
                    m.nome AS nota_materia_nome,
                    COALESCE(NULLIF(n.materia_id, 0), pbp.materia_id) AS materia_id,
                    COALESCE(m.nome, m2.nome) AS materia_nome,
                    CONCAT(COALESCE(pb.titulo, 'Bloco'), ' (pauta)') AS titulo,
                    10 AS valor_total,
                    0 AS total_questoes,
                    0 AS acertos
                FROM provas_blocos_notas_lancadas n
                INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.deleted_at IS NULL
                LEFT JOIN materias m ON m.id = n.materia_id
                LEFT JOIN provas_blocos_professores pbp
                  ON pbp.bloco_id = n.bloco_id AND pbp.professor_id = n.professor_id
                LEFT JOIN materias m2 ON m2.id = pbp.materia_id
                WHERE n.aluno_id IN ($phAlunos)
                  AND n.bloco_id IN ($phBlocos)
                  AND n.nota IS NOT NULL";

        $params = array_merge($alunoIds, $blocoIds);
        if ($inicio !== null && $fim !== null) {
            $sql .= ' AND (
                    (n.updated_at IS NOT NULL AND n.updated_at BETWEEN ? AND ?)
                    OR (
                        pb.data_prova IS NOT NULL
                        AND CAST(pb.data_prova AS CHAR) <> \'0000-00-00\'
                        AND pb.data_prova BETWEEN DATE(?) AND DATE(?)
                    )
                )';
            $params[] = $inicio;
            $params[] = $fim;
            $params[] = $inicio;
            $params[] = $fim;
        }
        if ($materiaId !== null && $materiaId > 0) {
            $sql .= ' AND (n.materia_id = ? OR pbp.materia_id = ?)';
            $params[] = $materiaId;
            $params[] = $materiaId;
        }
        $sql .= ' ORDER BY n.aluno_id ASC, finalizado_em DESC, n.id DESC';

        try {
            $rows = $this->db->fetchAll($sql, $params) ?: [];
        } catch (Throwable $e) {
            error_log('BoletimConfig fetchNotasLancadasPorBlocosAlunos: ' . $e->getMessage());

            return [];
        }

        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = (int) ($row['id'] ?? 0);
            if ($key > 0 && isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $online
     * @param list<array<string,mixed>> $manual
     * @return list<array<string,mixed>>
     */
    private function mergeProvasOnlineENotasLancadas(array $online, array $manual): array
    {
        $merged = array_merge($online, $manual);
        usort($merged, static function ($a, $b) {
            $ta = strtotime((string) ($a['finalizado_em'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['finalizado_em'] ?? '')) ?: 0;

            return $tb <=> $ta;
        });

        return $merged;
    }

    public function getAvailableExamBlocks(int $limit = 300): array
    {
        $limit = max(1, min($limit, 1000));
        $temTipoAvaliacao = $this->hasColumn('provas_blocos', 'tipo_avaliacao_id') && $this->hasTable('provas_tipos_avaliacao');
        $selectTipo = $temTipoAvaliacao
            ? 'pb.tipo_avaliacao_id, pta.nome AS tipo_avaliacao_nome'
            : 'NULL AS tipo_avaliacao_id, NULL AS tipo_avaliacao_nome';
        $joinTipo = $temTipoAvaliacao ? 'LEFT JOIN provas_tipos_avaliacao pta ON pta.id = pb.tipo_avaliacao_id AND pta.deleted_at IS NULL' : '';

        return $this->db->fetchAll(
            "SELECT pb.id, pb.titulo, pb.data_prova, pb.ano_letivo, pb.bimestre, {$selectTipo}
             FROM provas_blocos pb
             {$joinTipo}
             WHERE pb.deleted_at IS NULL
             ORDER BY pb.data_prova DESC, pb.id DESC
             LIMIT {$limit}"
        );
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->colunaExisteCache)) {
            return $this->colunaExisteCache[$cacheKey];
        }
        $existe = false;
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :col
                 LIMIT 1",
                ['table' => $table, 'col' => $column]
            );
            if (!empty($row)) {
                $existe = true;
            }
        } catch (Throwable $e) {
            // Tenant sem information_schema: cai no SHOW COLUMNS.
        }
        if (!$existe) {
            try {
                $row = $this->db->fetch("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
                $existe = !empty($row);
            } catch (Throwable $e) {
                $existe = false;
            }
        }
        $this->colunaExisteCache[$cacheKey] = $existe;
        return $existe;
    }

    private function hasTable(string $table): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
                 LIMIT 1",
                ['table' => $table]
            );
            return !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function getAvailableSubjects(int $limit = 300): array
    {
        $limit = max(1, min($limit, 2000));
        $cacheKey = 'lim' . $limit;
        if (isset($this->materiasDisponiveisCache[$cacheKey])) {
            return $this->materiasDisponiveisCache[$cacheKey];
        }
        $rows = $this->db->fetchAll(
            "SELECT m.id, m.nome
             FROM materias m
             ORDER BY m.nome ASC
             LIMIT {$limit}"
        ) ?: [];
        $this->materiasDisponiveisCache[$cacheKey] = $rows;

        return $rows;
    }

    /**
     * Ordem das matérias no boletim (matriz curricular). Sem matriz, usa o id de cadastro.
     *
     * @param list<int> $seriesIds
     * @param list<int> $turmasIds
     * @return array<int, int> materia_id => ordem
     */
    public function mapaOrdemBoletimMaterias(array $seriesIds = [], array $turmasIds = []): array
    {
        $seriesIds = array_values(array_unique(array_filter(array_map('intval', $seriesIds), static fn ($id) => $id > 0)));
        $turmasIds = array_values(array_unique(array_filter(array_map('intval', $turmasIds), static fn ($id) => $id > 0)));
        $cacheKey = implode(',', $seriesIds) . '|' . implode(',', $turmasIds);
        if (isset($this->mapaOrdemBoletimCache[$cacheKey])) {
            return $this->mapaOrdemBoletimCache[$cacheKey];
        }

        $out = [];
        foreach ($this->getAvailableSubjects(2000) as $m) {
            $id = (int) ($m['id'] ?? 0);
            if ($id > 0) {
                $out[$id] = 100000 + $id;
            }
        }
        if (!$this->hasTable('matrizes_curriculares_componentes')) {
            $this->mapaOrdemBoletimCache[$cacheKey] = $out;
            return $out;
        }

        $rows = [];

        if ($turmasIds !== [] && $this->hasColumn('turmas', 'matriz_curricular_id')) {
            $params = [];
            $ph = [];
            foreach ($turmasIds as $i => $tid) {
                $key = 't' . $i;
                $ph[] = ':' . $key;
                $params[$key] = $tid;
            }
            $rows = $this->db->fetchAll(
                'SELECT mcc.materia_id, MIN(mcc.ordem_boletim) AS ordem
                 FROM turmas t
                 INNER JOIN matrizes_curriculares_componentes mcc ON mcc.matriz_id = t.matriz_curricular_id
                 WHERE t.id IN (' . implode(',', $ph) . ')
                 GROUP BY mcc.materia_id',
                $params
            ) ?: [];
        }

        if ($rows === [] && $seriesIds !== [] && $this->hasTable('matrizes_curriculares')) {
            $params = [];
            $ph = [];
            foreach ($seriesIds as $i => $sid) {
                $key = 's' . $i;
                $ph[] = ':' . $key;
                $params[$key] = $sid;
            }
            $rows = $this->db->fetchAll(
                'SELECT mcc.materia_id, MIN(mcc.ordem_boletim) AS ordem
                 FROM matrizes_curriculares mx
                 INNER JOIN matrizes_curriculares_componentes mcc ON mcc.matriz_id = mx.id
                 WHERE mx.serie_id IN (' . implode(',', $ph) . ')
                 GROUP BY mcc.materia_id',
                $params
            ) ?: [];
        }

        if ($rows === []) {
            $rows = $this->db->fetchAll(
                'SELECT materia_id, MIN(ordem_boletim) AS ordem
                 FROM matrizes_curriculares_componentes
                 GROUP BY materia_id'
            ) ?: [];
        }

        foreach ($rows as $r) {
            $mid = (int) ($r['materia_id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            $out[$mid] = (int) ($r['ordem'] ?? 0);
        }

        $this->mapaOrdemBoletimCache[$cacheKey] = $out;
        return $out;
    }

    /**
     * Lançamento manual de blocos calculados (ex.: Média Final) precisa de um valor
     * por matéria, não só um valor global por componente (ENAC/manual segue global).
     * Adiciona materia_id (0 = sem matéria/legado) e troca a UNIQUE para incluir essa
     * coluna, sem apagar nenhuma nota já lançada.
     */
    private function ensureNotaManualMateriaColumn(): void
    {
        $exists = $this->db->fetch(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'boletim_notas_manuais'
               AND COLUMN_NAME = 'materia_id'
             LIMIT 1"
        );
        if (!$exists) {
            $this->db->query("ALTER TABLE boletim_notas_manuais ADD COLUMN materia_id INT NOT NULL DEFAULT 0 AFTER aluno_id");
        }

        $oldUnique = $this->db->fetch(
            "SELECT 1
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'boletim_notas_manuais'
               AND INDEX_NAME = 'uk_boletim_notas_manuais_item'
             LIMIT 1"
        );
        $newUnique = $this->db->fetch(
            "SELECT 1
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'boletim_notas_manuais'
               AND INDEX_NAME = 'uk_boletim_notas_manuais_item_materia'
             LIMIT 1"
        );
        if ($oldUnique && !$newUnique) {
            // uk_boletim_notas_manuais_item é o único índice que começa com
            // componente_id, então o MySQL usa ele pra suportar a FK
            // fk_boletim_notas_manuais_componente e recusa o DROP direto
            // (erro 1553). Cria um índice simples equivalente antes de dropar.
            $idxComponente = $this->db->fetch(
                "SELECT 1
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'boletim_notas_manuais'
                   AND INDEX_NAME = 'idx_boletim_notas_manuais_componente'
                 LIMIT 1"
            );
            if (!$idxComponente) {
                $this->db->query("ALTER TABLE boletim_notas_manuais ADD INDEX idx_boletim_notas_manuais_componente (componente_id)");
            }
            $this->db->query("ALTER TABLE boletim_notas_manuais DROP INDEX uk_boletim_notas_manuais_item");
            $this->db->query("ALTER TABLE boletim_notas_manuais ADD UNIQUE KEY uk_boletim_notas_manuais_item_materia (componente_id, aluno_id, periodo_ref, materia_id)");
        }

        // Escolas antigas (tabela criada antes desta feature) ainda têm `nota
        // NOT NULL` — sem isso não dá pra gravar uma sobrescrita manual
        // explicitamente "sem nota" (ver migration 2026_08_19_boletim_nota_manual_vazia.sql).
        $notaNullable = $this->db->fetch(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'boletim_notas_manuais'
               AND COLUMN_NAME = 'nota'
               AND IS_NULLABLE = 'YES'
             LIMIT 1"
        );
        if (!$notaNullable) {
            $this->db->query("ALTER TABLE boletim_notas_manuais MODIFY COLUMN nota DECIMAL(8,2) NULL DEFAULT NULL");
        }
    }

    /**
     * periodo_ref nasceu pensado em códigos curtos (ex.: "B1-2026"), mas a tela de
     * simulação por intervalo de datas gera "RANGE:2026-04-01:2026-06-30" (27+
     * caracteres), que não cabe em VARCHAR(20) e quebrava a edição inline com
     * SQLSTATE 22001 (1406 Data too long) — a request falhava com 500 e o
     * fetch() do navegador não tinha JSON pra ler, parecendo que o botão "não
     * fazia nada". Alarga pra VARCHAR(60) nas 3 tabelas que guardam periodo_ref.
     */
    private function ensurePeriodoRefWidth(): void
    {
        $tabelas = ['boletim_notas_manuais', 'boletim_resultados_gerados', 'boletim_log_geracoes'];
        foreach ($tabelas as $tabela) {
            $col = $this->db->fetch(
                "SELECT CHARACTER_MAXIMUM_LENGTH AS len
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :tabela
                   AND COLUMN_NAME = 'periodo_ref'",
                ['tabela' => $tabela]
            );
            if ($col && (int) ($col['len'] ?? 0) < 60) {
                $this->db->query("ALTER TABLE {$tabela} MODIFY COLUMN periodo_ref VARCHAR(60) NOT NULL");
            }
        }
    }

    private function ensureComponenteColumn(string $column, string $sqlAlter): void
    {
        $exists = $this->db->fetch(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'boletim_componentes'
               AND COLUMN_NAME = :column_name
             LIMIT 1",
            ['column_name' => $column]
        );

        if ($exists) {
            return;
        }

        $this->db->query($sqlAlter);
    }

    private function ensureRegraColumn(string $column, string $sqlAlter): void
    {
        $exists = $this->db->fetch(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'boletim_regras'
               AND COLUMN_NAME = :column_name
             LIMIT 1",
            ['column_name' => $column]
        );

        if ($exists) {
            return;
        }

        $this->db->query($sqlAlter);
    }

    private function ensureBoletimResultadoColumn(string $column, string $sqlAlter): void
    {
        $exists = $this->db->fetch(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'boletim_resultados_gerados'
               AND COLUMN_NAME = :column_name
             LIMIT 1",
            ['column_name' => $column]
        );

        if ($exists) {
            $this->colunaExisteCache['boletim_resultados_gerados.' . $column] = true;
            return;
        }
        $this->db->query($sqlAlter);
        $this->colunaExisteCache['boletim_resultados_gerados.' . $column] = true;
    }

    private function ensureBoletimResultadoNoUniqueByMateriaRef(): void
    {
        try {
            $idxRows = $this->db->fetchAll(
                "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'boletim_resultados_gerados'
                   AND INDEX_NAME = 'uk_boletim_resultados_item'
                 ORDER BY SEQ_IN_INDEX ASC"
            ) ?: [];
            if (!empty($idxRows)) {
                $this->db->query("ALTER TABLE boletim_resultados_gerados DROP INDEX uk_boletim_resultados_item");
            }
        } catch (Throwable $e) {
            error_log('BoletimConfig ensureBoletimResultadoNoUniqueByMateriaRef: ' . $e->getMessage());
        }
    }

    private function ensureBoletimResultadoMateriaIdNullable(): void
    {
        try {
            $col = $this->db->fetch(
                "SELECT IS_NULLABLE AS is_nullable
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'boletim_resultados_gerados'
                   AND COLUMN_NAME = 'materia_id'
                 LIMIT 1"
            );
            $isNullable = strtoupper(trim((string) ($col['is_nullable'] ?? 'YES')));
            if ($isNullable === 'YES') {
                return;
            }
            $this->db->query("ALTER TABLE boletim_resultados_gerados MODIFY COLUMN materia_id INT NULL");
        } catch (Throwable $e) {
            error_log('BoletimConfig ensureBoletimResultadoMateriaIdNullable: ' . $e->getMessage());
        }
    }

    private function normalizeCalcType(string $calcType): string
    {
        $calcType = strtolower(trim($calcType));
        $allowed = ['media', 'soma', 'maior', 'ultima'];
        return in_array($calcType, $allowed, true) ? $calcType : 'media';
    }

    private function normalizeSourceTypeForSave(string $sourceType): string
    {
        $t = strtolower(trim($sourceType));
        if ($t === 'jornadas') {
            return 'jornadas';
        }
        if ($t === 'calculado') {
            return 'calculado';
        }
        if ($t === 'evento_boletim') {
            return 'evento_boletim';
        }
        if ($t === 'faltas_evento') {
            return 'faltas_evento';
        }
        if ($t === 'nenhuma') {
            return 'nenhuma';
        }

        return 'provas_sistema';
    }

    private function ensureSourceTypeIncludesJornadas(): void
    {
        $row = $this->db->fetch(
            "SELECT COLUMN_TYPE AS ct
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'boletim_componentes'
               AND COLUMN_NAME = 'source_type'
             LIMIT 1"
        );
        $ct = strtolower((string) ($row['ct'] ?? ''));
        if (
            $ct !== ''
            && strpos($ct, 'jornadas') !== false
            && strpos($ct, 'calculado') !== false
            && strpos($ct, 'evento_boletim') !== false
            && strpos($ct, 'faltas_evento') !== false
            && strpos($ct, 'nenhuma') !== false
        ) {
            return;
        }
        try {
            $this->db->query(
                "ALTER TABLE boletim_componentes
                 MODIFY COLUMN source_type ENUM('provas_sistema','manual','jornadas','calculado','evento_boletim','faltas_evento','nenhuma') NOT NULL DEFAULT 'provas_sistema'"
            );
        } catch (Throwable $e) {
            error_log('BoletimConfig ensureSourceTypeIncludesJornadas: ' . $e->getMessage());
        }
    }

    /**
     * @param mixed $value
     */
    private function trimConfigJson($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        return strlen($s) > 65000 ? substr($s, 0, 65000) : $s;
    }

    private function trimNullableVarchar(?string $value, int $maxLen): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        return mb_substr($v, 0, max(1, $maxLen), 'UTF-8');
    }

    /**
     * @param mixed $value
     */
    private function trimBlocosIdsCsv($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : substr($s, 0, 500);
    }

    public function existsRuleCode(string $codigo, ?int $exceptId = null): bool
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return false;
        }
        $params = ['codigo' => $codigo];
        $sql = "SELECT id FROM boletim_regras WHERE codigo = :codigo AND ativo = 1";
        if ($exceptId !== null && $exceptId > 0) {
            $sql .= " AND id <> :id";
            $params['id'] = $exceptId;
        }
        $sql .= " LIMIT 1";

        $row = $this->db->fetch($sql, $params);
        return !empty($row);
    }

    private function normalizeRuleCode(?string $codigo, string $fallbackNome): ?string
    {
        $raw = trim((string) $codigo);
        if ($raw === '') {
            $raw = trim($fallbackNome);
        }
        $raw = mb_strtolower($raw, 'UTF-8');
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ];
        $raw = strtr($raw, $map);
        $raw = preg_replace('/[^a-z0-9]+/', '-', $raw) ?? '';
        $raw = trim($raw, '-');
        if ($raw === '') {
            return null;
        }

        return substr($raw, 0, 120);
    }

    private function normalizeExibirEm(string $value): string
    {
        $v = strtolower(trim($value));
        return $v === 'notas' ? 'notas' : 'boletim';
    }

    private function normalizeAnoLetivo(?int $ano): ?int
    {
        $a = (int) ($ano ?? 0);
        return ($a >= 2000 && $a <= 2100) ? $a : null;
    }

    private function normalizeBimestre(?int $bimestre): ?int
    {
        $b = (int) ($bimestre ?? 0);
        return in_array($b, [1, 2, 3, 4], true) ? $b : null;
    }

    private function normalizeRoundMode(string $value): string
    {
        $v = strtolower(trim($value));
        return in_array($v, ['none', 'half'], true) ? $v : 'none';
    }

    private function normalizeNotaMinimaAprovacao(?float $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $v = (float) $value;
        if ($v < 0) {
            $v = 0;
        }
        if ($v > 10) {
            $v = 10;
        }
        return round($v, 2);
    }

    private function normalizeDecimalPlaces(int $value): int
    {
        return $value === 1 ? 1 : 2;
    }

    /**
     * @param list<int> $blocoIds
     * @return list<int>
     */
    public function filtrarBlocoIdsPorSemana(array $blocoIds, int $semana): array
    {
        $blocoIds = array_values(array_unique(array_filter(array_map('intval', $blocoIds), static function ($id) {
            return $id > 0;
        })));
        if ($blocoIds === [] || $semana < 1 || $semana > 8 || !$this->hasColumn('provas_blocos', 'semana')) {
            return $blocoIds;
        }
        $placeholders = implode(',', array_fill(0, count($blocoIds), '?'));
        $sql = "SELECT id FROM provas_blocos
                WHERE deleted_at IS NULL AND semana = ? AND id IN ({$placeholders})";
        $params = array_merge([$semana], $blocoIds);
        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<int> $blocoIds
     * @param list<int> $bimestres
     * @return list<int>
     */
    public function filtrarBlocoIdsPorBimestres(array $blocoIds, array $bimestres): array
    {
        $blocoIds = array_values(array_unique(array_filter(array_map('intval', $blocoIds), static function ($id) {
            return $id > 0;
        })));
        $bims = [];
        foreach ($bimestres as $b) {
            $n = (int) $b;
            if ($n >= 1 && $n <= 4 && !in_array($n, $bims, true)) {
                $bims[] = $n;
            }
        }
        if ($blocoIds === [] || $bims === [] || !$this->hasColumn('provas_blocos', 'bimestre')) {
            return $blocoIds;
        }
        $placeholdersIds = implode(',', array_fill(0, count($blocoIds), '?'));
        $placeholdersBims = implode(',', array_fill(0, count($bims), '?'));
        $sql = "SELECT id FROM provas_blocos
                WHERE deleted_at IS NULL
                  AND id IN ({$placeholdersIds})
                  AND (bimestre IS NULL OR bimestre = 0 OR bimestre IN ({$placeholdersBims}))";
        $params = array_merge($blocoIds, $bims);
        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<int> $bimestres
     * @return list<int>
     */
    public function buscarBlocoIdsPorTipoESemana(
        int $tipoAvaliacaoId,
        int $semana,
        ?string $inicio = null,
        ?string $fim = null,
        array $bimestres = []
    ): array {
        if ($tipoAvaliacaoId <= 0 || $semana < 1 || $semana > 8) {
            return [];
        }
        if (!$this->hasColumn('provas_blocos', 'tipo_avaliacao_id')
            || !$this->hasColumn('provas_blocos', 'semana')
        ) {
            return [];
        }
        $sql = 'SELECT id FROM provas_blocos
                WHERE deleted_at IS NULL
                  AND tipo_avaliacao_id = :tipo
                  AND semana = :semana';
        $params = [
            'tipo' => $tipoAvaliacaoId,
            'semana' => $semana,
        ];
        if ($inicio !== null && $fim !== null && $inicio !== '' && $fim !== '') {
            $sql .= ' AND (
                data_prova IS NULL
                OR CAST(data_prova AS CHAR) = \'0000-00-00\'
                OR data_prova BETWEEN DATE(:ini) AND DATE(:fim)
            )';
            $params['ini'] = $inicio;
            $params['fim'] = $fim;
        }
        $bims = [];
        foreach ($bimestres as $b) {
            $n = (int) $b;
            if ($n >= 1 && $n <= 4 && !in_array($n, $bims, true)) {
                $bims[] = $n;
            }
        }
        if ($bims !== [] && $this->hasColumn('provas_blocos', 'bimestre')) {
            $ph = [];
            foreach ($bims as $i => $n) {
                $key = 'bim' . $i;
                $ph[] = ':' . $key;
                $params[$key] = $n;
            }
            $sql .= ' AND (bimestre IS NULL OR bimestre = 0 OR bimestre IN (' . implode(',', $ph) . '))';
        }
        $sql .= ' ORDER BY data_prova DESC, id DESC LIMIT 40';
        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }
}
