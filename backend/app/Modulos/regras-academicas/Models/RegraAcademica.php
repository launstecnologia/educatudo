<?php

namespace App\Modulos\RegrasAcademicas\Models;

use App\Core\Database;
use Throwable;

/**
 * EducaTudo - Modelo de Regras de Aprovação
 * Critérios versionados de aprovação, recuperação e frequência.
 */
class RegraAcademica
{
    private $db;

    public const PERIODO_TIPOS = [
        'bimestre' => 'Bimestre',
        'trimestre' => 'Trimestre',
        'semestre' => 'Semestre',
        'etapa_unica' => 'Etapa única',
    ];

    public const RECUPERACAO_TIPOS = [
        'nenhuma' => 'Sem recuperação',
        'continua' => 'Contínua / paralela',
        'periodo' => 'Por período (bimestre/trimestre)',
        'final' => 'Recuperação final',
    ];

    public const ESCALAS = [
        '10' => 'Numérica (0 a 10)',
        '100' => 'Numérica (0 a 100)',
        'abcd' => 'Conceitual (A, B, C, D)',
        'conceito' => 'Conceitual (A+, A, B, C...)',
        'descritiva' => 'Conceitual (Descritiva)',
    ];

    /** Do melhor para o pior. Aprova quem ficar no mínimo ou em um melhor. */
    public const CONCEITOS = [
        'A+' => 'A+',
        'A' => 'A',
        'B' => 'B',
        'C' => 'C',
        'D' => 'D',
    ];

    public static function numerica(string $escala): bool
    {
        return $escala === '10' || $escala === '100';
    }

    /**
     * Conceitos da escala, do melhor para o pior. Nulo quando a escala é numérica ou descritiva livre.
     *
     * @return array<string,string>|null
     */
    public static function conceitosDa(string $escala): ?array
    {
        if ($escala === 'abcd') {
            return ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D'];
        }
        if ($escala === 'conceito') {
            return self::CONCEITOS;
        }
        if ($escala === 'descritiva') {
            return ['Excelente' => 'Excelente', 'Bom' => 'Bom', 'Regular' => 'Regular'];
        }

        return null;
    }

    public const RECUPERACAO_COMPOSICOES = [
        'maior_nota' => 'Maior nota (substitui se for maior)',
        'substitui' => 'Substitui a média',
        'composicao' => 'Composição (média + recuperação) / 2',
        'formula' => 'Fórmula própria',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function schemaPronto(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'regras_academicas'
                 LIMIT 1"
            );
            $ok = !empty($row['ok']);
        } catch (Throwable $e) {
            $ok = false;
        }
        if ($ok) {
            $this->ensureAgrupamentoColumn();
        }
        return $ok;
    }

    /**
     * @param array{ano_letivo?:int,curso_id?:int,serie_id?:int,ativo?:int} $filtros
     * @return list<array<string,mixed>>
     */
    public function getAll(array $filtros = []): array
    {
        if (!$this->schemaPronto()) {
            return [];
        }

        $joinAg = $this->regrasAgrupamentoPronto()
            ? ' LEFT JOIN agrupamentos_componentes ag ON ag.id = r.agrupamento_id'
            : '';
        $selectAg = $this->regrasAgrupamentoPronto() ? ', ag.nome AS agrupamento_nome' : '';

        $sql = "SELECT r.*,
                       c.nome AS curso_nome,
                       s.nome AS serie_nome,
                       m.nome AS materia_nome
                       {$selectAg}
                FROM regras_academicas r
                LEFT JOIN curso c ON c.id = r.curso_id
                LEFT JOIN serie s ON s.id = r.serie_id
                LEFT JOIN materias m ON m.id = r.materia_id
                {$joinAg}
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['ano_letivo'])) {
            $sql .= " AND r.ano_letivo = :ano_letivo";
            $params['ano_letivo'] = (int) $filtros['ano_letivo'];
        }
        if (!empty($filtros['curso_id'])) {
            $sql .= " AND r.curso_id = :curso_id";
            $params['curso_id'] = (int) $filtros['curso_id'];
        }
        if (!empty($filtros['serie_id'])) {
            $serieFiltro = (int) $filtros['serie_id'];
            if ($this->seriesProntas()) {
                $sql .= " AND (r.serie_id = :serie_id OR EXISTS (
                    SELECT 1 FROM regras_academicas_series rs
                    WHERE rs.regra_id = r.id AND rs.serie_id = :serie_id_lista
                ))";
                $params['serie_id'] = $serieFiltro;
                $params['serie_id_lista'] = $serieFiltro;
            } else {
                $sql .= " AND r.serie_id = :serie_id";
                $params['serie_id'] = $serieFiltro;
            }
        }
        if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
            $sql .= " AND r.ativo = :ativo";
            $params['ativo'] = (int) $filtros['ativo'];
        }

        $sql .= " ORDER BY r.ano_letivo DESC, r.nome ASC";

        return $this->comNomesDasSeries($this->db->fetchAll($sql, $params) ?: []);
    }

    /**
     * Regras ativas para o motor resolver a mais específica.
     * @return list<array<string,mixed>>
     */
    public function listarAtivas(?int $anoLetivo = null): array
    {
        if (!$this->schemaPronto()) {
            return [];
        }
        $sql = "SELECT * FROM regras_academicas WHERE ativo = 1";
        $params = [];
        if ($anoLetivo !== null && $anoLetivo > 0) {
            $sql .= " AND (ano_letivo = :ano_letivo OR ano_letivo IS NULL)";
            $params['ano_letivo'] = $anoLetivo;
        }
        $sql .= " ORDER BY updated_at DESC, id DESC";

        return $this->comIdsDasSeries($this->db->fetchAll($sql, $params) ?: []);
    }

    public function findById(int $id): ?array
    {
        if (!$this->schemaPronto() || $id <= 0) {
            return null;
        }
        $joinAg = $this->regrasAgrupamentoPronto()
            ? ' LEFT JOIN agrupamentos_componentes ag ON ag.id = r.agrupamento_id'
            : '';
        $selectAg = $this->regrasAgrupamentoPronto() ? ', ag.nome AS agrupamento_nome' : '';
        $row = $this->db->fetch(
            "SELECT r.*,
                    c.nome AS curso_nome,
                    s.nome AS serie_nome,
                    m.nome AS materia_nome
                    {$selectAg}
             FROM regras_academicas r
             LEFT JOIN curso c ON c.id = r.curso_id
             LEFT JOIN serie s ON s.id = r.serie_id
             LEFT JOIN materias m ON m.id = r.materia_id
             {$joinAg}
             WHERE r.id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function exists(int $id): bool
    {
        return $this->findById($id) !== null;
    }

    public function codigoExists(string $codigo, ?int $ignoreId = null): bool
    {
        if (!$this->schemaPronto() || $codigo === '') {
            return false;
        }
        $sql = "SELECT id FROM regras_academicas WHERE codigo = :codigo";
        $params = ['codigo' => $codigo];
        if ($ignoreId !== null && $ignoreId > 0) {
            $sql .= " AND id != :id";
            $params['id'] = $ignoreId;
        }
        $row = $this->db->fetch($sql . " LIMIT 1", $params);
        return !empty($row);
    }

    public function create(array $data): int
    {
        $escala = $this->escalaPronta();
        $boletim = $this->boletimPronto();
        $cols = $escala ? ', escala, conceito_minimo' : '';
        $vals = $escala ? ', :escala, :conceito_minimo' : '';
        if ($boletim) {
            $cols .= ', finalidade, vis_aluno, vis_pais';
            $vals .= ', :finalidade, :vis_aluno, :vis_pais';
        }
        return (int) $this->db->insert(
            "INSERT INTO regras_academicas (
                nome, codigo, ano_letivo, curso_id, serie_id, matriz_curricular_id, materia_id, agrupamento_id,
                periodo_tipo, periodo_numero, media_minima, frequencia_minima, usar_frequencia,
                round_mode, decimal_places, formula_media, formula_final,
                recuperacao_tipo, recuperacao_composicao, min_avaliacoes, max_avaliacoes,
                componentes_sem_nota, aprovacao_so_frequencia, situacoes_json, observacoes,
                versao, ativo{$cols}
             ) VALUES (
                :nome, :codigo, :ano_letivo, :curso_id, :serie_id, :matriz_curricular_id, :materia_id, :agrupamento_id,
                :periodo_tipo, :periodo_numero, :media_minima, :frequencia_minima, :usar_frequencia,
                :round_mode, :decimal_places, :formula_media, :formula_final,
                :recuperacao_tipo, :recuperacao_composicao, :min_avaliacoes, :max_avaliacoes,
                :componentes_sem_nota, :aprovacao_so_frequencia, :situacoes_json, :observacoes,
                :versao, :ativo{$vals}
             )",
            $this->paramsFromData($data, $escala, $boletim)
        );
    }

    public function update(int $id, array $data): bool
    {
        $escala = $this->escalaPronta();
        $boletim = $this->boletimPronto();
        $params = $this->paramsFromData($data, $escala, $boletim);
        $params['id'] = $id;
        $setEscala = $escala ? ",
                escala = :escala,
                conceito_minimo = :conceito_minimo" : '';
        $setBoletim = $boletim ? ",
                finalidade = :finalidade,
                vis_aluno = :vis_aluno,
                vis_pais = :vis_pais" : '';
        return (bool) $this->db->update(
            "UPDATE regras_academicas SET
                nome = :nome,
                codigo = :codigo,
                ano_letivo = :ano_letivo,
                curso_id = :curso_id,
                serie_id = :serie_id,
                matriz_curricular_id = :matriz_curricular_id,
                materia_id = :materia_id,
                agrupamento_id = :agrupamento_id,
                periodo_tipo = :periodo_tipo,
                periodo_numero = :periodo_numero,
                media_minima = :media_minima,
                frequencia_minima = :frequencia_minima,
                usar_frequencia = :usar_frequencia,
                round_mode = :round_mode,
                decimal_places = :decimal_places,
                formula_media = :formula_media,
                formula_final = :formula_final,
                recuperacao_tipo = :recuperacao_tipo,
                recuperacao_composicao = :recuperacao_composicao,
                min_avaliacoes = :min_avaliacoes,
                max_avaliacoes = :max_avaliacoes,
                componentes_sem_nota = :componentes_sem_nota,
                aprovacao_so_frequencia = :aprovacao_so_frequencia,
                situacoes_json = :situacoes_json,
                observacoes = :observacoes,
                versao = :versao,
                ativo = :ativo{$setEscala}{$setBoletim}
             WHERE id = :id",
            $params
        );
    }

    public function delete(int $id): bool
    {
        if (!$this->schemaPronto() || $id <= 0) {
            return false;
        }
        return (bool) $this->db->query(
            "DELETE FROM regras_academicas WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarHistorico(int $regraId): array
    {
        if (!$this->schemaPronto() || $regraId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT id, regra_id, versao, parametros_json, usuario_id, usuario_nome, created_at
             FROM regras_academicas_historico
             WHERE regra_id = :regra_id
             ORDER BY versao DESC",
            ['regra_id' => $regraId]
        ) ?: [];
    }

    public function gravarHistorico(int $regraId, int $versao, array $parametros, ?int $usuarioId, ?string $usuarioNome): void
    {
        $this->db->insert(
            "INSERT INTO regras_academicas_historico
                (regra_id, versao, parametros_json, usuario_id, usuario_nome)
             VALUES
                (:regra_id, :versao, :parametros_json, :usuario_id, :usuario_nome)",
            [
                'regra_id' => $regraId,
                'versao' => $versao,
                'parametros_json' => json_encode($parametros, JSON_UNESCAPED_UNICODE),
                'usuario_id' => $usuarioId !== null && $usuarioId > 0 ? $usuarioId : null,
                'usuario_nome' => $usuarioNome !== null && $usuarioNome !== '' ? $usuarioNome : null,
            ]
        );
    }

    public function boletimPronto(): bool
    {
        if (!$this->schemaPronto() || !$this->temColuna('finalidade') || !$this->temColuna('vis_aluno') || !$this->temColuna('vis_pais')) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'regras_academicas_materias'
                 LIMIT 1"
            );
            return !empty($row['ok']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<int>
     */
    public function listarMateriasIds(int $regraId): array
    {
        if ($regraId <= 0 || !$this->boletimPronto()) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT materia_id FROM regras_academicas_materias WHERE regra_id = :regra ORDER BY materia_id ASC',
            ['regra' => $regraId]
        ) ?: [];
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['materia_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * @param list<int> $materiaIds
     */
    public function substituirMaterias(int $regraId, array $materiaIds): void
    {
        if ($regraId <= 0 || !$this->boletimPronto()) {
            return;
        }
        $this->db->query(
            'DELETE FROM regras_academicas_materias WHERE regra_id = :regra',
            ['regra' => $regraId]
        );
        $vistos = [];
        foreach ($materiaIds as $materiaId) {
            $materiaId = (int) $materiaId;
            if ($materiaId <= 0 || isset($vistos[$materiaId])) {
                continue;
            }
            $vistos[$materiaId] = true;
            $this->db->insert(
                'INSERT INTO regras_academicas_materias (regra_id, materia_id) VALUES (:regra, :materia)',
                ['regra' => $regraId, 'materia' => $materiaId]
            );
        }
    }

    /**
     * Dados que o demonstrativo e o boletim copiam ao vincular a regra.
     *
     * @return array<string,mixed>|null
     */
    public function pacoteBoletim(int $regraId): ?array
    {
        $regra = $this->findById($regraId);
        if ($regra === null || !$this->boletimPronto()) {
            return null;
        }
        $series = $this->listarSeriesIds($regraId);
        if ($series === []) {
            $serieId = (int) ($regra['serie_id'] ?? 0);
            $series = $serieId > 0 ? [$serieId] : [];
        }
        $finalidade = ((string) ($regra['finalidade'] ?? '')) === 'complementar' ? 'complementar' : 'oficial';
        return [
            'nome' => trim((string) ($regra['nome'] ?? '')),
            'ano_letivo' => (int) ($regra['ano_letivo'] ?? 0),
            'finalidade' => $finalidade,
            'vis_aluno' => (int) ($regra['vis_aluno'] ?? 0) === 1 ? 1 : 0,
            'vis_pais' => (int) ($regra['vis_pais'] ?? 0) === 1 ? 1 : 0,
            'ativo' => (int) ($regra['ativo'] ?? 0) === 1 ? 1 : 0,
            'materias_ids' => $this->listarMateriasIds($regraId),
            'series_ids' => $series,
            'turmas_ids' => [],
        ];
    }

    public function seriesProntas(): bool
    {
        if (!$this->schemaPronto()) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'regras_academicas_series'
                 LIMIT 1"
            );
            return !empty($row['ok']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<int>
     */
    public function listarSeriesIds(int $regraId): array
    {
        if ($regraId <= 0 || !$this->seriesProntas()) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT serie_id FROM regras_academicas_series WHERE regra_id = :regra ORDER BY serie_id ASC',
            ['regra' => $regraId]
        ) ?: [];
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['serie_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * @param list<int> $serieIds
     */
    public function substituirSeries(int $regraId, array $serieIds): void
    {
        if ($regraId <= 0 || !$this->seriesProntas()) {
            return;
        }
        $this->db->query(
            'DELETE FROM regras_academicas_series WHERE regra_id = :regra',
            ['regra' => $regraId]
        );
        $vistos = [];
        foreach ($serieIds as $serieId) {
            $serieId = (int) $serieId;
            if ($serieId <= 0 || isset($vistos[$serieId])) {
                continue;
            }
            $vistos[$serieId] = true;
            $this->db->insert(
                'INSERT INTO regras_academicas_series (regra_id, serie_id) VALUES (:regra, :serie)',
                ['regra' => $regraId, 'serie' => $serieId]
            );
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function comIdsDasSeries(array $rows): array
    {
        $mapa = $this->mapaSeries($rows, false);
        foreach ($rows as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $ids = $mapa[$id]['ids'] ?? [];
            if ($ids === [] && (int) ($row['serie_id'] ?? 0) > 0) {
                $ids = [(int) $row['serie_id']];
            }
            $row['series_ids'] = $ids;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function comNomesDasSeries(array $rows): array
    {
        $mapa = $this->mapaSeries($rows, true);
        foreach ($rows as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $nomes = $mapa[$id]['nomes'] ?? [];
            if ($nomes !== []) {
                $row['serie_nome'] = implode(', ', $nomes);
            }
            $row['series_ids'] = $mapa[$id]['ids'] ?? [];
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int, array{ids:list<int>,nomes:list<string>}>
     */
    private function mapaSeries(array $rows, bool $comNome): array
    {
        if ($rows === [] || !$this->seriesProntas()) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }
        $marcas = [];
        $params = [];
        $i = 0;
        foreach ($ids as $id) {
            $chave = 'regra' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $id;
            $i++;
        }
        $selectNome = $comNome ? ', s.nome AS serie_nome' : '';
        $joinNome = $comNome ? ' INNER JOIN serie s ON s.id = rs.serie_id' : '';
        $ordem = $comNome ? ' ORDER BY s.nome ASC' : '';
        $linhas = $this->db->fetchAll(
            'SELECT rs.regra_id, rs.serie_id' . $selectNome . '
             FROM regras_academicas_series rs' . $joinNome . '
             WHERE rs.regra_id IN (' . implode(', ', $marcas) . ')' . $ordem,
            $params
        ) ?: [];
        $mapa = [];
        foreach ($linhas as $linha) {
            $regraId = (int) ($linha['regra_id'] ?? 0);
            $serieId = (int) ($linha['serie_id'] ?? 0);
            if ($regraId <= 0 || $serieId <= 0) {
                continue;
            }
            if (!isset($mapa[$regraId])) {
                $mapa[$regraId] = ['ids' => [], 'nomes' => []];
            }
            $mapa[$regraId]['ids'][] = $serieId;
            if ($comNome && trim((string) ($linha['serie_nome'] ?? '')) !== '') {
                $mapa[$regraId]['nomes'][] = (string) $linha['serie_nome'];
            }
        }

        return $mapa;
    }

    public function escalaPronta(): bool
    {
        if (!$this->schemaPronto()) {
            return false;
        }
        try {
            $col = $this->db->fetch("SHOW COLUMNS FROM regras_academicas LIKE 'escala'");
            return !empty($col);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function conceitoMinimoCabe(string $valor): bool
    {
        if (strlen($valor) <= 8) {
            return true;
        }
        try {
            $col = $this->db->fetch("SHOW COLUMNS FROM regras_academicas LIKE 'conceito_minimo'");
            $tipo = (string) ($col['Type'] ?? '');
            if (preg_match('/varchar\((\d+)\)/i', $tipo, $m)) {
                return (int) $m[1] >= strlen($valor);
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function paramsFromData(array $data, bool $comEscala = false, bool $comBoletim = false): array
    {
        $params = [
            'nome' => (string) ($data['nome'] ?? ''),
            'codigo' => $data['codigo'] ?? null,
            'ano_letivo' => $data['ano_letivo'] ?? null,
            'curso_id' => $data['curso_id'] ?? null,
            'serie_id' => $data['serie_id'] ?? null,
            'matriz_curricular_id' => $data['matriz_curricular_id'] ?? null,
            'materia_id' => $data['materia_id'] ?? null,
            'agrupamento_id' => !empty($data['agrupamento_id']) ? (int) $data['agrupamento_id'] : null,
            'periodo_tipo' => (string) ($data['periodo_tipo'] ?? 'bimestre'),
            'periodo_numero' => $data['periodo_numero'] ?? null,
            'media_minima' => (float) ($data['media_minima'] ?? 6),
            'frequencia_minima' => (float) ($data['frequencia_minima'] ?? 75),
            'usar_frequencia' => (int) ($data['usar_frequencia'] ?? 0),
            'round_mode' => (string) ($data['round_mode'] ?? 'none'),
            'decimal_places' => (int) ($data['decimal_places'] ?? 2),
            'formula_media' => $data['formula_media'] ?? null,
            'formula_final' => $data['formula_final'] ?? null,
            'recuperacao_tipo' => (string) ($data['recuperacao_tipo'] ?? 'periodo'),
            'recuperacao_composicao' => (string) ($data['recuperacao_composicao'] ?? 'maior_nota'),
            'min_avaliacoes' => $data['min_avaliacoes'] ?? null,
            'max_avaliacoes' => $data['max_avaliacoes'] ?? null,
            'componentes_sem_nota' => (int) ($data['componentes_sem_nota'] ?? 0),
            'aprovacao_so_frequencia' => (int) ($data['aprovacao_so_frequencia'] ?? 0),
            'situacoes_json' => $data['situacoes_json'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'versao' => (int) ($data['versao'] ?? 1),
            'ativo' => (int) ($data['ativo'] ?? 1),
        ];
        if ($comEscala) {
            $params['escala'] = (string) ($data['escala'] ?? '10');
            $params['conceito_minimo'] = $data['conceito_minimo'] ?? null;
        }
        if ($comBoletim) {
            $params['finalidade'] = ((string) ($data['finalidade'] ?? '')) === 'complementar' ? 'complementar' : 'oficial';
            $params['vis_aluno'] = (int) ($data['vis_aluno'] ?? 0) === 1 ? 1 : 0;
            $params['vis_pais'] = (int) ($data['vis_pais'] ?? 0) === 1 ? 1 : 0;
        }
        return $params;
    }

    private function temColuna(string $coluna): bool
    {
        if (!$this->schemaPronto()) {
            return false;
        }
        try {
            $col = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
                 LIMIT 1',
                ['t' => 'regras_academicas', 'c' => $coluna]
            );
            return !empty($col['ok']);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function ensureAgrupamentoColumn(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $col = $this->db->fetch("SHOW COLUMNS FROM regras_academicas LIKE 'agrupamento_id'");
            if ($col) {
                return;
            }
            $this->db->query(
                "ALTER TABLE regras_academicas ADD COLUMN agrupamento_id INT UNSIGNED NULL DEFAULT NULL AFTER materia_id"
            );
        } catch (Throwable $e) {
            // migration ainda não rodou / sem permissão ALTER
        }
    }

    private function agrupamentosTabelaExiste(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'agrupamentos_componentes'
                 LIMIT 1"
            );
            $ok = !empty($row['ok']);
        } catch (Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    private function regrasAgrupamentoPronto(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        if (!$this->agrupamentosTabelaExiste()) {
            $ok = false;
            return false;
        }
        try {
            $col = $this->db->fetch("SHOW COLUMNS FROM regras_academicas LIKE 'agrupamento_id'");
            $ok = !empty($col);
        } catch (Throwable $e) {
            $ok = false;
        }
        return $ok;
    }
}

if (!class_exists('RegraAcademica', false) && class_exists(__NAMESPACE__ . '\\RegraAcademica', false)) {
    class_alias(__NAMESPACE__ . '\\RegraAcademica', 'RegraAcademica');
}
