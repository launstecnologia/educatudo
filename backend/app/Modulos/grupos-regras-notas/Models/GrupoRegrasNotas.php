<?php
/**
 * Persistência do Grupo de Regras de Notas.
 */

class GrupoRegrasNotas
{
    public const NUMERO_MAX = 20;
    public const NUMERO_CALCULADA_MIN = 21;
    public const NUMERO_CALCULADA_MAX = 40;

    /** @var Database */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        return $this->existeRelacao('quadros_notas') || $this->existeRelacao('grupos_regras_notas');
    }

    /**
     * Nome físico (ou view) da relação. Prefere quadros_notas* após a migration 2026_09_10.
     */
    public function nomeTabela(string $papel): string
    {
        $novos = [
            'quadro' => 'quadros_notas',
            'blocos' => 'quadros_notas_blocos',
            'colunas' => 'quadros_notas_colunas',
            'bloco_colunas' => 'quadros_notas_bloco_colunas',
            'bloco_materias' => 'quadros_notas_bloco_materias',
            'vinculos_prova' => 'provas_blocos_quadros_notas',
        ];
        $antigos = [
            'quadro' => 'grupos_regras_notas',
            'blocos' => 'grupos_regras_notas_tipos',
            'colunas' => 'grupos_regras_notas_marcas',
            'bloco_colunas' => 'grupos_regras_notas_tipo_marcas',
            'bloco_materias' => 'grupos_regras_notas_tipo_materias',
            'vinculos_prova' => 'provas_blocos_grupos_regras',
        ];
        if (!isset($novos[$papel])) {
            return $antigos['quadro'];
        }
        if ($this->existeRelacao($novos[$papel])) {
            return $novos[$papel];
        }
        return $antigos[$papel];
    }

    public function temColunaQuadro(string $coluna): bool
    {
        $permitidas = [
            'escala_max' => true,
            'criterio_calculo' => true,
            'coluna_consolidada_codigo' => true,
            'coluna_consolidada_nome' => true,
            'modo' => true,
            'ritmo_intervalo_semanas' => true,
            'ritmo_data_inicio' => true,
        ];
        if (!isset($permitidas[$coluna])) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1',
                ['t' => $this->nomeTabela('quadro'), 'c' => $coluna]
            );
            return !empty($row['ok']);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function existeRelacao(string $nome): bool
    {
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :n LIMIT 1',
                ['n' => $nome]
            );
            return !empty($row['ok']);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function sql(string $sql): string
    {
        return strtr($sql, [
            '{quadro}' => '`' . $this->nomeTabela('quadro') . '`',
            '{blocos}' => '`' . $this->nomeTabela('blocos') . '`',
            '{colunas}' => '`' . $this->nomeTabela('colunas') . '`',
            '{bloco_colunas}' => '`' . $this->nomeTabela('bloco_colunas') . '`',
            '{bloco_materias}' => '`' . $this->nomeTabela('bloco_materias') . '`',
            '{vinculos_prova}' => '`' . $this->nomeTabela('vinculos_prova') . '`',
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(bool $somenteAtivos = false): array
    {
        if (!$this->tabelasProntas()) {
            return [];
        }
        $sql = $this->sql('SELECT g.*,
                       (SELECT COUNT(*) FROM {blocos} t WHERE t.grupo_id = g.id) AS total_tipos,
                       (SELECT COUNT(*) FROM {colunas} m WHERE m.grupo_id = g.id) AS total_marcas
                  FROM {quadro} g');
        if ($somenteAtivos) {
            $sql .= ' WHERE g.ativo = 1';
        }
        $sql .= ' ORDER BY g.nome ASC';
        return $this->db->fetchAll($sql) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0 || !$this->tabelasProntas()) {
            return null;
        }
        $row = $this->db->fetch($this->sql('SELECT * FROM {quadro} WHERE id = :id'), ['id' => $id]);
        return is_array($row) ? $row : null;
    }

    public function criar(string $nome, ?string $descricao, bool $ativo, array $meta = []): int
    {
        $params = [
            'nome' => $nome,
            'descricao' => $descricao,
            'ativo' => $ativo ? 1 : 0,
        ];
        $cols = 'nome, descricao, ativo';
        $vals = ':nome, :descricao, :ativo';
        if ($this->temColunaQuadro('escala_max')) {
            $cols .= ', escala_max, criterio_calculo, coluna_consolidada_codigo, coluna_consolidada_nome, modo';
            $vals .= ', :escala_max, :criterio_calculo, :coluna_consolidada_codigo, :coluna_consolidada_nome, :modo';
            $params['escala_max'] = self::normalizarEscala($meta['escala_max'] ?? 10);
            $params['criterio_calculo'] = self::normalizarCriterio($meta['criterio_calculo'] ?? 'ultima');
            $params['coluna_consolidada_codigo'] = self::normalizarCodigoColuna($meta['coluna_consolidada_codigo'] ?? '');
            $params['coluna_consolidada_nome'] = trim((string) ($meta['coluna_consolidada_nome'] ?? ''));
            $params['modo'] = self::normalizarModo($meta['modo'] ?? 'simples');
        }
        if ($this->temColunaQuadro('ritmo_intervalo_semanas') && array_key_exists('ritmo_intervalo_semanas', $meta)) {
            $cols .= ', ritmo_intervalo_semanas, ritmo_data_inicio';
            $vals .= ', :ritmo_intervalo_semanas, :ritmo_data_inicio';
            $params['ritmo_intervalo_semanas'] = self::normalizarIntervalo($meta['ritmo_intervalo_semanas'] ?? 2);
            $params['ritmo_data_inicio'] = self::normalizarData($meta['ritmo_data_inicio'] ?? null);
        }
        return (int) $this->db->insert(
            $this->sql('INSERT INTO {quadro} (' . $cols . ') VALUES (' . $vals . ')'),
            $params
        );
    }

    public function atualizar(int $id, string $nome, ?string $descricao, bool $ativo, array $meta = []): void
    {
        $params = [
            'id' => $id,
            'nome' => $nome,
            'descricao' => $descricao,
            'ativo' => $ativo ? 1 : 0,
        ];
        $set = 'nome = :nome, descricao = :descricao, ativo = :ativo';
        if ($this->temColunaQuadro('escala_max')) {
            $set .= ', escala_max = :escala_max, criterio_calculo = :criterio_calculo,
                      coluna_consolidada_codigo = :coluna_consolidada_codigo,
                      coluna_consolidada_nome = :coluna_consolidada_nome, modo = :modo';
            $params['escala_max'] = self::normalizarEscala($meta['escala_max'] ?? 10);
            $params['criterio_calculo'] = self::normalizarCriterio($meta['criterio_calculo'] ?? 'ultima');
            $params['coluna_consolidada_codigo'] = self::normalizarCodigoColuna($meta['coluna_consolidada_codigo'] ?? '');
            $params['coluna_consolidada_nome'] = trim((string) ($meta['coluna_consolidada_nome'] ?? ''));
            $params['modo'] = self::normalizarModo($meta['modo'] ?? 'simples');
        }
        if ($this->temColunaQuadro('ritmo_intervalo_semanas') && array_key_exists('ritmo_intervalo_semanas', $meta)) {
            $set .= ', ritmo_intervalo_semanas = :ritmo_intervalo_semanas, ritmo_data_inicio = :ritmo_data_inicio';
            $params['ritmo_intervalo_semanas'] = self::normalizarIntervalo($meta['ritmo_intervalo_semanas'] ?? 2);
            $params['ritmo_data_inicio'] = self::normalizarData($meta['ritmo_data_inicio'] ?? null);
        }
        $this->db->query(
            $this->sql('UPDATE {quadro} SET ' . $set . ' WHERE id = :id'),
            $params
        );
    }

    public function atualizarRitmoQuadro(int $id, int $intervalo, ?string $dataInicio): void
    {
        if ($id <= 0 || !$this->temColunaQuadro('ritmo_intervalo_semanas')) {
            return;
        }
        $this->db->query(
            $this->sql('UPDATE {quadro} SET ritmo_intervalo_semanas = :i, ritmo_data_inicio = :d WHERE id = :id'),
            [
                'id' => $id,
                'i' => self::normalizarIntervalo($intervalo),
                'd' => self::normalizarData($dataInicio),
            ]
        );
    }

    public function excluir(int $id): void
    {
        $this->db->query($this->sql('DELETE FROM {quadro} WHERE id = :id'), ['id' => $id]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarTipos(int $grupoId): array
    {
        return $this->db->fetchAll(
            $this->sql('SELECT * FROM {blocos} WHERE grupo_id = :id ORDER BY ordem ASC, id ASC'),
            ['id' => $grupoId]
        ) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarMarcas(int $grupoId): array
    {
        return $this->db->fetchAll(
            $this->sql('SELECT * FROM {colunas} WHERE grupo_id = :id ORDER BY ordem ASC, numero ASC, id ASC'),
            ['id' => $grupoId]
        ) ?: [];
    }

    /**
     * @return list<int>
     */
    public function marcasIdsDoTipo(int $tipoId): array
    {
        $rows = $this->db->fetchAll(
            $this->sql('SELECT marca_id FROM {bloco_colunas} WHERE tipo_id = :id'),
            ['id' => $tipoId]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = (int) ($r['marca_id'] ?? 0);
        }
        return array_values(array_filter($out));
    }

    /**
     * @return list<int>
     */
    public function materiasIdsDoTipo(int $tipoId): array
    {
        $rows = $this->db->fetchAll(
            $this->sql('SELECT materia_id FROM {bloco_materias} WHERE tipo_id = :id'),
            ['id' => $tipoId]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = (int) ($r['materia_id'] ?? 0);
        }
        return array_values(array_filter($out));
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function listarMaterias(): array
    {
        try {
            $rows = $this->db->fetchAll('SELECT id, nome FROM materias ORDER BY nome ASC') ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = ['id' => $id, 'nome' => (string) ($r['nome'] ?? '')];
        }
        return $out;
    }

    public function existeMarcaNoGrupo(int $marcaId, int $grupoId): bool
    {
        if ($marcaId <= 0 || $grupoId <= 0) {
            return false;
        }
        $row = $this->db->fetch(
            $this->sql('SELECT id FROM {colunas} WHERE id = :id AND grupo_id = :grupo_id'),
            ['id' => $marcaId, 'grupo_id' => $grupoId]
        );
        return !empty($row['id']);
    }

    public function existeTipoNoGrupo(int $tipoId, int $grupoId): bool
    {
        if ($tipoId <= 0 || $grupoId <= 0) {
            return false;
        }
        $row = $this->db->fetch(
            $this->sql('SELECT id FROM {blocos} WHERE id = :id AND grupo_id = :grupo_id'),
            ['id' => $tipoId, 'grupo_id' => $grupoId]
        );
        return !empty($row['id']);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findTipoById(int $tipoId): ?array
    {
        if ($tipoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            $this->sql('SELECT * FROM {blocos} WHERE id = :id'),
            ['id' => $tipoId]
        );
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findMarcaById(int $marcaId): ?array
    {
        if ($marcaId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            $this->sql('SELECT * FROM {colunas} WHERE id = :id'),
            ['id' => $marcaId]
        );
        return is_array($row) ? $row : null;
    }

    /**
     * Colunas já usadas neste quadro/bloco/período por eventos que cruzam as turmas informadas.
     * Sem turmas, ninguém "ocupa" a semana — séries diferentes podem compartilhar a mesma S.
     *
     * @param list<int> $turmaIds
     * @return list<int>
     */
    public function marcasIdsUsadasNoPeriodo(
        int $grupoId,
        int $tipoId,
        int $ano,
        int $bimestre,
        int $excetoBlocoId = 0,
        array $turmaIds = []
    ): array {
        if ($grupoId <= 0 || $ano <= 0 || $bimestre <= 0) {
            return [];
        }
        $turmaIds = $this->idsPositivos($turmaIds);
        if ($turmaIds === []) {
            return [];
        }
        if (!$this->provasBlocosTemColuna('grupo_regras_notas_id') || !$this->provasBlocosTemColuna('grupo_regras_marca_id')) {
            return [];
        }
        if (!$this->provasBlocosTemColuna('ano_letivo') || !$this->provasBlocosTemColuna('bimestre')) {
            return [];
        }
        $sql = 'SELECT DISTINCT pb.grupo_regras_marca_id AS id
                FROM provas_blocos pb
                WHERE pb.deleted_at IS NULL
                  AND pb.grupo_regras_notas_id = :g
                  AND pb.grupo_regras_marca_id IS NOT NULL
                  AND pb.ano_letivo = :ano
                  AND pb.bimestre = :bim';
        $params = ['g' => $grupoId, 'ano' => $ano, 'bim' => $bimestre];
        if ($tipoId > 0 && $this->provasBlocosTemColuna('grupo_regras_tipo_id')) {
            $sql .= ' AND pb.grupo_regras_tipo_id = :t';
            $params['t'] = $tipoId;
        }
        if ($excetoBlocoId > 0) {
            $sql .= ' AND pb.id <> :ex';
            $params['ex'] = $excetoBlocoId;
        }
        $cruzam = $this->sqlBlocoCruzaTurmas('pb', $turmaIds);
        $sql .= $cruzam['sql'];
        $params = array_merge($params, $cruzam['params']);
        $rows = $this->db->fetchAll($sql, $params);
        $out = [];
        foreach ($rows ?: [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Quantas semanas civis anteriores já têm prova deste bloco para estas turmas.
     * A próxima coluna segue essa ordem (1ª anterior → S2, 2ª → S4, 3ª → S6).
     *
     * @param list<int> $turmaIds
     */
    public function contarSemanasAnterioresDoBloco(
        int $grupoId,
        int $tipoId,
        int $ano,
        int $bimestre,
        string $dataProva,
        int $excetoBlocoId = 0,
        array $turmaIds = []
    ): int {
        if ($grupoId <= 0 || $tipoId <= 0 || $ano <= 0 || $bimestre <= 0) {
            return 0;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataProva)) {
            return 0;
        }
        if (!$this->provasBlocosTemColuna('ano_letivo') || !$this->provasBlocosTemColuna('bimestre')) {
            return 0;
        }
        if ($this->idsPositivos($turmaIds) !== []) {
            $comTurmas = $this->contarSemanasAnterioresSql($grupoId, $tipoId, $ano, $bimestre, $dataProva, $excetoBlocoId, $turmaIds, true);
            if ($comTurmas > 0) {
                return $comTurmas;
            }
        }

        return $this->contarSemanasAnterioresSql($grupoId, $tipoId, $ano, $bimestre, $dataProva, $excetoBlocoId, [], false);
    }

    /**
     * @param list<int> $turmaIds
     */
    private function contarSemanasAnterioresSql(
        int $grupoId,
        int $tipoId,
        int $ano,
        int $bimestre,
        string $dataProva,
        int $excetoBlocoId,
        array $turmaIds,
        bool $filtrarTurmas
    ): int {
        $params = ['g' => $grupoId, 't' => $tipoId, 'ano' => $ano, 'bim' => $bimestre, 'data' => $dataProva];
        $filtroBloco = '(pb.grupo_regras_notas_id = :g AND pb.grupo_regras_tipo_id = :t)';
        $joinVinculo = '';
        if ($this->tabelaVinculosProvaExiste()) {
            $joinVinculo = ' LEFT JOIN ' . $this->sql('{vinculos_prova}') . ' vq ON vq.bloco_id = pb.id AND vq.grupo_id = :g AND vq.tipo_id = :t';
            $filtroBloco = '(vq.bloco_id IS NOT NULL OR (pb.grupo_regras_notas_id = :g AND pb.grupo_regras_tipo_id = :t))';
        }
        $sql = 'SELECT COUNT(DISTINCT YEARWEEK(pb.data_prova, 3)) AS n
                FROM provas_blocos pb' . $joinVinculo . '
                WHERE pb.deleted_at IS NULL
                  AND ' . $filtroBloco . '
                  AND pb.ano_letivo = :ano
                  AND pb.bimestre = :bim
                  AND pb.data_prova IS NOT NULL
                  AND pb.data_prova < :data';
        if ($excetoBlocoId > 0) {
            $sql .= ' AND pb.id <> :ex';
            $params['ex'] = $excetoBlocoId;
        }
        if ($filtrarTurmas) {
            $cruzam = $this->sqlBlocoCruzaTurmas('pb', $turmaIds);
            if ($cruzam['sql'] === '') {
                return 0;
            }
            $sql .= $cruzam['sql'];
            $params = array_merge($params, $cruzam['params']);
        }
        $row = $this->db->fetch($sql, $params);

        return (int) ($row['n'] ?? 0);
    }

    /**
     * Semana já usada no mesmo bloco A/B na mesma semana civil, por evento sem cruzar estas turmas.
     *
     * @param list<int> $turmaIds
     */
    public function marcaIdDaMesmaSemanaLetiva(
        int $grupoId,
        int $tipoId,
        int $ano,
        int $bimestre,
        string $dataProva,
        int $excetoBlocoId = 0,
        array $turmaIds = []
    ): int {
        if ($grupoId <= 0 || $tipoId <= 0 || $ano <= 0 || $bimestre <= 0) {
            return 0;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataProva)) {
            return 0;
        }
        if (!$this->provasBlocosTemColuna('grupo_regras_notas_id')
            || !$this->provasBlocosTemColuna('grupo_regras_marca_id')
            || !$this->provasBlocosTemColuna('grupo_regras_tipo_id')
            || !$this->provasBlocosTemColuna('ano_letivo')
            || !$this->provasBlocosTemColuna('bimestre')
        ) {
            return 0;
        }
        $sql = 'SELECT pb.grupo_regras_marca_id AS id
                FROM provas_blocos pb
                WHERE pb.deleted_at IS NULL
                  AND pb.grupo_regras_notas_id = :g
                  AND pb.grupo_regras_tipo_id = :t
                  AND pb.grupo_regras_marca_id IS NOT NULL
                  AND pb.ano_letivo = :ano
                  AND pb.bimestre = :bim
                  AND pb.data_prova IS NOT NULL
                  AND YEARWEEK(pb.data_prova, 3) = YEARWEEK(:data, 3)';
        $params = ['g' => $grupoId, 't' => $tipoId, 'ano' => $ano, 'bim' => $bimestre, 'data' => $dataProva];
        if ($excetoBlocoId > 0) {
            $sql .= ' AND pb.id <> :ex';
            $params['ex'] = $excetoBlocoId;
        }
        $turmaIds = $this->idsPositivos($turmaIds);
        if ($turmaIds !== []) {
            $cruzam = $this->sqlBlocoCruzaTurmas('pb', $turmaIds);
            $sql .= str_replace(' AND EXISTS', ' AND NOT EXISTS', $cruzam['sql']);
            $params = array_merge($params, $cruzam['params']);
        }
        $sql .= ' ORDER BY pb.id ASC LIMIT 1';
        $row = $this->db->fetch($sql, $params);

        return (int) ($row['id'] ?? 0);
    }

    /**
     * Turmas dos outros eventos deste bloco na mesma semana civil.
     *
     * @param list<int> $turmaIds
     * @return list<int>
     */
    public function turmasIdsDaMesmaSemanaLetiva(
        int $grupoId,
        int $tipoId,
        int $ano,
        int $bimestre,
        string $dataProva,
        int $excetoBlocoId = 0,
        array $turmaIds = []
    ): array {
        if ($grupoId <= 0 || $tipoId <= 0 || $ano <= 0 || $bimestre <= 0) {
            return [];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataProva)) {
            return [];
        }
        if (!$this->provasBlocosTemColuna('grupo_regras_notas_id')
            || !$this->provasBlocosTemColuna('grupo_regras_tipo_id')
            || !$this->provasBlocosTemColuna('ano_letivo')
            || !$this->provasBlocosTemColuna('bimestre')
        ) {
            return [];
        }
        $sql = 'SELECT pb.id
                FROM provas_blocos pb
                WHERE pb.deleted_at IS NULL
                  AND pb.grupo_regras_notas_id = :g
                  AND pb.grupo_regras_tipo_id = :t
                  AND pb.ano_letivo = :ano
                  AND pb.bimestre = :bim
                  AND pb.data_prova IS NOT NULL
                  AND YEARWEEK(pb.data_prova, 3) = YEARWEEK(:data, 3)';
        $params = ['g' => $grupoId, 't' => $tipoId, 'ano' => $ano, 'bim' => $bimestre, 'data' => $dataProva];
        if ($excetoBlocoId > 0) {
            $sql .= ' AND pb.id <> :ex';
            $params['ex'] = $excetoBlocoId;
        }
        $turmaIds = $this->idsPositivos($turmaIds);
        if ($turmaIds !== []) {
            $cruzam = $this->sqlBlocoCruzaTurmas('pb', $turmaIds);
            $sql .= str_replace(' AND EXISTS', ' AND NOT EXISTS', $cruzam['sql']);
            $params = array_merge($params, $cruzam['params']);
        }
        $rows = $this->db->fetchAll($sql, $params) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $blocoId = (int) ($row['id'] ?? 0);
            if ($blocoId <= 0) {
                continue;
            }
            foreach ($this->turmasIdsDoBloco($blocoId) as $turmaId) {
                $out[$turmaId] = true;
            }
        }

        return array_map('intval', array_keys($out));
    }

    /**
     * @return list<int>
     */
    private function turmasIdsDoBloco(int $blocoId): array
    {
        if ($blocoId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT turma_id FROM provas_blocos_turmas WHERE bloco_id = :id
             UNION
             SELECT turma_id FROM provas_blocos WHERE id = :id_b AND turma_id IS NOT NULL AND turma_id > 0
             UNION
             SELECT pbpt.turma_id
               FROM provas_blocos_professores pbp
               INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
              WHERE pbp.bloco_id = :id_p',
            ['id' => $blocoId, 'id_b' => $blocoId, 'id_p' => $blocoId]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['turma_id'] ?? 0);
            if ($id > 0) {
                $out[$id] = true;
            }
        }

        return array_map('intval', array_keys($out));
    }

    /**
     * @param list<int> $turmaIds
     * @return array{sql:string,params:array<string,int>}
     */
    private function sqlBlocoCruzaTurmas(string $alias, array $turmaIds): array
    {
        $turmaIds = $this->idsPositivos($turmaIds);
        if ($turmaIds === [] || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
            return ['sql' => '', 'params' => []];
        }
        $ph = [];
        $params = [];
        foreach ($turmaIds as $i => $tid) {
            $k = 'tur' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $tid;
        }
        $in = implode(',', $ph);
        $sql = " AND EXISTS (
            SELECT 1 FROM (
                SELECT pbt.turma_id AS turma_id
                  FROM provas_blocos_turmas pbt
                 WHERE pbt.bloco_id = {$alias}.id
                UNION
                SELECT pbx.turma_id
                  FROM provas_blocos pbx
                 WHERE pbx.id = {$alias}.id AND pbx.turma_id IS NOT NULL AND pbx.turma_id > 0
                UNION
                SELECT pbpt.turma_id
                  FROM provas_blocos_professores pbp
                  INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
                 WHERE pbp.bloco_id = {$alias}.id
            ) turmas_ev
            WHERE turmas_ev.turma_id IN ({$in})
        )";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * @param list<mixed> $ids
     * @return list<int>
     */
    private function idsPositivos(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[$n] = true;
            }
        }

        return array_map('intval', array_keys($out));
    }

    public function marcaVinculadaAoTipo(int $tipoId, int $marcaId): bool
    {
        if ($tipoId <= 0 || $marcaId <= 0) {
            return false;
        }
        $total = $this->db->fetch(
            $this->sql('SELECT COUNT(*) AS c FROM {bloco_colunas} WHERE tipo_id = :id'),
            ['id' => $tipoId]
        );
        if ((int) ($total['c'] ?? 0) === 0) {
            return true;
        }
        $row = $this->db->fetch(
            $this->sql('SELECT tipo_id FROM {bloco_colunas}
              WHERE tipo_id = :tipo_id AND marca_id = :marca_id'),
            ['tipo_id' => $tipoId, 'marca_id' => $marcaId]
        );
        return !empty($row['tipo_id']);
    }

    /**
     * Bloco de disciplinas deste quadro que contém a coluna (S).
     */
    public function tipoIdDaMarcaNoGrupo(int $grupoId, int $marcaId): int
    {
        if ($grupoId <= 0 || $marcaId <= 0) {
            return 0;
        }
        $row = $this->db->fetch(
            $this->sql('SELECT tm.tipo_id
              FROM {bloco_colunas} tm
              INNER JOIN {blocos} t ON t.id = tm.tipo_id
              WHERE t.grupo_id = :grupo_id AND tm.marca_id = :marca_id
              ORDER BY tm.tipo_id ASC
              LIMIT 1'),
            ['grupo_id' => $grupoId, 'marca_id' => $marcaId]
        );

        return (int) ($row['tipo_id'] ?? 0);
    }

    public function grupoEmUsoEmProvas(int $grupoId): bool
    {
        if ($grupoId <= 0) {
            return false;
        }
        if ($this->provasBlocosTemColuna('grupo_regras_notas_id')) {
            $row = $this->db->fetch(
                'SELECT id FROM provas_blocos
                  WHERE deleted_at IS NULL AND grupo_regras_notas_id = :id
                  LIMIT 1',
                ['id' => $grupoId]
            );
            if (!empty($row['id'])) {
                return true;
            }
        }
        if ($this->tabelaVinculosProvaExiste()) {
            $row = $this->db->fetch(
                $this->sql('SELECT id FROM {vinculos_prova} WHERE grupo_id = :id LIMIT 1'),
                ['id' => $grupoId]
            );
            if (!empty($row['id'])) {
                return true;
            }
        }
        $tipoIds = [];
        foreach ($this->listarTipos($grupoId) as $t) {
            $tipoIds[] = (int) ($t['id'] ?? 0);
        }
        if ($this->idsTiposEmUsoEmProvas($tipoIds) !== []) {
            return true;
        }
        $marcaIds = [];
        foreach ($this->listarMarcas($grupoId) as $m) {
            $marcaIds[] = (int) ($m['id'] ?? 0);
        }

        return $this->idsMarcasEmUsoEmProvas($marcaIds) !== [];
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    public function idsMarcasEmUsoEmProvas(array $ids): array
    {
        return $this->idsEmUsoEmProvas('grupo_regras_marca_id', $ids);
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    public function idsTiposEmUsoEmProvas(array $ids): array
    {
        return $this->idsEmUsoEmProvas('grupo_regras_tipo_id', $ids);
    }

    /**
     * @param list<int> $ids
     * @return list<string>
     */
    public function nomesMarcasPorIds(array $ids): array
    {
        return $this->nomesPorIds($this->nomeTabela('colunas'), $ids);
    }

    /**
     * @param list<int> $ids
     * @return list<string>
     */
    public function nomesTiposPorIds(array $ids): array
    {
        return $this->nomesPorIds($this->nomeTabela('blocos'), $ids);
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function papelDaColuna(array $row): string
    {
        $papel = strtolower(trim((string) ($row['papel'] ?? 'lancamento')));
        return $papel === 'calculada' ? 'calculada' : 'lancamento';
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function ehLancamento(array $row): bool
    {
        return self::papelDaColuna($row) === 'lancamento';
    }

    public function temColunaMarca(string $coluna): bool
    {
        $permitidas = [
            'papel' => true,
            'tipo_nota_id' => true,
            'formula_json' => true,
            'vai_para_boletim' => true,
        ];
        if (!isset($permitidas[$coluna])) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1',
                ['t' => $this->nomeTabela('colunas'), 'c' => $coluna]
            );
            return !empty($row['ok']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $extra
     */
    public function inserirMarca(int $grupoId, string $codigo, string $nome, int $numero, int $ordem, array $extra = []): int
    {
        $cols = 'grupo_id, codigo, nome, numero, ordem';
        $vals = ':grupo_id, :codigo, :nome, :numero, :ordem';
        $params = [
            'grupo_id' => $grupoId,
            'codigo' => $codigo,
            'nome' => $nome,
            'numero' => $numero,
            'ordem' => $ordem,
        ];
        $this->anexarCamposPapel($cols, $vals, $params, $extra, false);
        return (int) $this->db->insert(
            $this->sql('INSERT INTO {colunas} (' . $cols . ') VALUES (' . $vals . ')'),
            $params
        );
    }

    /**
     * @param array<string,mixed> $extra
     */
    public function atualizarMarca(int $id, int $grupoId, string $codigo, string $nome, int $numero, int $ordem, array $extra = []): void
    {
        $set = 'codigo = :codigo, nome = :nome, numero = :numero, ordem = :ordem';
        $params = [
            'id' => $id,
            'grupo_id' => $grupoId,
            'codigo' => $codigo,
            'nome' => $nome,
            'numero' => $numero,
            'ordem' => $ordem,
        ];
        $valsDummy = '';
        $this->anexarCamposPapel($set, $valsDummy, $params, $extra, true);
        $this->db->query(
            $this->sql('UPDATE {colunas} SET ' . $set . ' WHERE id = :id AND grupo_id = :grupo_id'),
            $params
        );
    }

    /**
     * @param array<string,mixed> $params
     * @param array<string,mixed> $extra
     */
    private function anexarCamposPapel(string &$colsOuSet, string &$vals, array &$params, array $extra, bool $update): void
    {
        if (!$this->temColunaMarca('papel')) {
            return;
        }
        $papel = self::papelDaColuna($extra);
        $tipoNota = (int) ($extra['tipo_nota_id'] ?? 0);
        $formula = isset($extra['formula_json']) ? (string) $extra['formula_json'] : null;
        if ($formula === '') {
            $formula = null;
        }
        $boletim = !empty($extra['vai_para_boletim']) ? 1 : 0;
        if ($update) {
            $colsOuSet .= ', papel = :papel, tipo_nota_id = :tipo_nota_id, formula_json = :formula_json, vai_para_boletim = :vai_para_boletim';
        } else {
            $colsOuSet .= ', papel, tipo_nota_id, formula_json, vai_para_boletim';
            $vals .= ', :papel, :tipo_nota_id, :formula_json, :vai_para_boletim';
        }
        $params['papel'] = $papel;
        $params['tipo_nota_id'] = $tipoNota > 0 ? $tipoNota : null;
        $params['formula_json'] = $formula;
        $params['vai_para_boletim'] = $boletim;
    }

    public function inserirTipo(int $grupoId, string $codigo, string $nome, int $ordem, ?int $tipoAvaliacaoId): int
    {
        return (int) $this->db->insert(
            $this->sql('INSERT INTO {blocos} (grupo_id, codigo, nome, ordem, tipo_avaliacao_id)
             VALUES (:grupo_id, :codigo, :nome, :ordem, :tipo_avaliacao_id)'),
            [
                'grupo_id' => $grupoId,
                'codigo' => $codigo,
                'nome' => $nome,
                'ordem' => $ordem,
                'tipo_avaliacao_id' => null,
            ]
        );
    }

    public function atualizarTipo(int $id, int $grupoId, string $codigo, string $nome, int $ordem, ?int $tipoAvaliacaoId): void
    {
        $this->db->query(
            $this->sql('UPDATE {blocos}
                SET codigo = :codigo, nome = :nome, ordem = :ordem, tipo_avaliacao_id = :tipo_avaliacao_id
              WHERE id = :id AND grupo_id = :grupo_id'),
            [
                'id' => $id,
                'grupo_id' => $grupoId,
                'codigo' => $codigo,
                'nome' => $nome,
                'ordem' => $ordem,
                'tipo_avaliacao_id' => null,
            ]
        );
    }

    /**
     * @param list<int> $manterIds
     */
    public function excluirMarcasFora(int $grupoId, array $manterIds): void
    {
        if ($manterIds === []) {
            $this->db->query($this->sql('DELETE FROM {colunas} WHERE grupo_id = :id'), ['id' => $grupoId]);
            return;
        }
        $ph = [];
        $params = ['grupo_id' => $grupoId];
        foreach (array_values($manterIds) as $i => $mid) {
            $k = 'id' . $i;
            $ph[] = ':' . $k;
            $params[$k] = (int) $mid;
        }
        $this->db->query(
            $this->sql('DELETE FROM {colunas} WHERE grupo_id = :grupo_id AND id NOT IN (' . implode(',', $ph) . ')'),
            $params
        );
    }

    /**
     * @param list<int> $manterIds
     */
    public function excluirTiposFora(int $grupoId, array $manterIds): void
    {
        if ($manterIds === []) {
            $this->db->query($this->sql('DELETE FROM {blocos} WHERE grupo_id = :id'), ['id' => $grupoId]);
            return;
        }
        $ph = [];
        $params = ['grupo_id' => $grupoId];
        foreach (array_values($manterIds) as $i => $tid) {
            $k = 'id' . $i;
            $ph[] = ':' . $k;
            $params[$k] = (int) $tid;
        }
        $this->db->query(
            $this->sql('DELETE FROM {blocos} WHERE grupo_id = :grupo_id AND id NOT IN (' . implode(',', $ph) . ')'),
            $params
        );
    }

    public function substituirMarcasDoTipo(int $tipoId, array $marcaIds): void
    {
        $this->db->query($this->sql('DELETE FROM {bloco_colunas} WHERE tipo_id = :id'), ['id' => $tipoId]);
        foreach ($marcaIds as $marcaId) {
            $mid = (int) $marcaId;
            if ($mid <= 0) {
                continue;
            }
            $this->db->insert(
                $this->sql('INSERT INTO {bloco_colunas} (tipo_id, marca_id) VALUES (:tipo_id, :marca_id)'),
                ['tipo_id' => $tipoId, 'marca_id' => $mid]
            );
        }
    }

    public function substituirMateriasDoTipo(int $tipoId, array $materiaIds): void
    {
        $this->db->query($this->sql('DELETE FROM {bloco_materias} WHERE tipo_id = :id'), ['id' => $tipoId]);
        foreach ($materiaIds as $materiaId) {
            $mid = (int) $materiaId;
            if ($mid <= 0) {
                continue;
            }
            $this->db->insert(
                $this->sql('INSERT INTO {bloco_materias} (tipo_id, materia_id) VALUES (:tipo_id, :materia_id)'),
                ['tipo_id' => $tipoId, 'materia_id' => $mid]
            );
        }
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function idsEmUsoEmProvas(string $coluna, array $ids): array
    {
        $permitidas = [
            'grupo_regras_marca_id' => true,
            'grupo_regras_tipo_id' => true,
        ];
        if (!isset($permitidas[$coluna])) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        if ($ids === []) {
            return [];
        }
        $ph = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $k = 'id' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        $out = [];
        if ($this->provasBlocosTemColuna($coluna)) {
            $rows = $this->db->fetchAll(
                'SELECT DISTINCT `' . $coluna . '` AS id FROM provas_blocos
                  WHERE deleted_at IS NULL AND `' . $coluna . '` IN (' . implode(',', $ph) . ')',
                $params
            ) ?: [];
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $out[] = $id;
                }
            }
        }
        $colVinculo = $coluna === 'grupo_regras_marca_id' ? 'marca_id' : 'tipo_id';
        if ($this->tabelaVinculosProvaExiste()) {
            $rows = $this->db->fetchAll(
                $this->sql('SELECT DISTINCT `' . $colVinculo . '` AS id FROM {vinculos_prova}
                  WHERE `' . $colVinculo . '` IN (' . implode(',', $ph) . ')'),
                $params
            ) ?: [];
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $out[] = $id;
                }
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @param list<int> $ids
     * @return list<string>
     */
    private function nomesPorIds(string $tabela, array $ids): array
    {
        $permitidas = [
            $this->nomeTabela('colunas') => true,
            $this->nomeTabela('blocos') => true,
            'grupos_regras_notas_marcas' => true,
            'grupos_regras_notas_tipos' => true,
        ];
        if (!isset($permitidas[$tabela])) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        if ($ids === []) {
            return [];
        }
        $ph = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $k = 'id' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        $rows = $this->db->fetchAll(
            'SELECT nome FROM `' . $tabela . '` WHERE id IN (' . implode(',', $ph) . ') ORDER BY ordem ASC, id ASC',
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $nome = trim((string) ($row['nome'] ?? ''));
            if ($nome !== '') {
                $out[] = $nome;
            }
        }
        return $out;
    }

    private function tabelaVinculosProvaExiste(): bool
    {
        return $this->existeRelacao($this->nomeTabela('vinculos_prova'))
            || $this->existeRelacao('provas_blocos_grupos_regras');
    }

    public static function normalizarEscala($valor): float
    {
        $n = (float) str_replace(',', '.', (string) $valor);
        if ($n < 1) {
            return 10.0;
        }
        if ($n > 100) {
            return 100.0;
        }
        return round($n, 2);
    }

    public static function normalizarCriterio(string $criterio): string
    {
        $criterio = strtolower(trim($criterio));
        return in_array($criterio, ['ultima', 'media', 'soma', 'maior'], true) ? $criterio : 'ultima';
    }

    public static function normalizarModo(string $modo): string
    {
        $modo = strtolower(trim($modo));
        return $modo === 'blocos' ? 'blocos' : 'simples';
    }

    public static function normalizarCodigoColuna(string $codigo): string
    {
        $codigo = strtolower(trim($codigo));
        $codigo = preg_replace('/[^a-z0-9_]+/', '_', $codigo) ?? '';
        return $codigo;
    }

    public static function normalizarIntervalo($valor): int
    {
        $n = (int) $valor;
        if ($n < 1) {
            return 2;
        }
        return min(8, $n);
    }

    public static function normalizarData($valor): ?string
    {
        $v = trim((string) $valor);
        if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        return $v;
    }

    public function temColunaBloco(string $coluna): bool
    {
        if ($coluna !== 'ritmo_data_inicio') {
            return false;
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1',
                ['t' => $this->nomeTabela('blocos'), 'c' => $coluna]
            );
            return !empty($row['ok']);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function atualizarRitmoTipo(int $id, ?string $dataInicio): void
    {
        if ($id <= 0 || !$this->temColunaBloco('ritmo_data_inicio')) {
            return;
        }
        $this->db->query(
            $this->sql('UPDATE {blocos} SET ritmo_data_inicio = :d WHERE id = :id'),
            ['id' => $id, 'd' => self::normalizarData($dataInicio)]
        );
    }

    private function provasBlocosTemColuna(string $coluna): bool
    {
        $permitidas = [
            'grupo_regras_notas_id' => true,
            'grupo_regras_tipo_id' => true,
            'grupo_regras_marca_id' => true,
            'ano_letivo' => true,
            'bimestre' => true,
        ];
        if (!isset($permitidas[$coluna])) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                'SHOW COLUMNS FROM provas_blocos LIKE :col',
                ['col' => $coluna]
            );
            return is_array($row) && !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }
}
