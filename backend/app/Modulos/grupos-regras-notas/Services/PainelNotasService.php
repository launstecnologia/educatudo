<?php
/**
 * Monta o painel de notas do aluno a partir do Quadro de Notas.
 * Lançamento preenche célula; calculada fecha a linha; blocos viram tabelas; filhos nas linhas.
 */

require_once __DIR__ . '/GrupoRegrasNotasService.php';
require_once dirname(__DIR__, 3) . '/Models/Education/ComponenteCurricular.php';

class PainelNotasService
{
    private GrupoRegrasNotasService $quadro;
    private Database $db;

    /** @var array{portal?:bool,ano_letivo?:int|null,bimestre?:int|null} */
    private array $opts = [];

    public function __construct(?GrupoRegrasNotasService $quadro = null)
    {
        $this->quadro = $quadro ?? new GrupoRegrasNotasService();
        $this->db = Database::getInstance();
    }

    /**
     * @param array{portal?:bool,ano_letivo?:int|null,bimestre?:int|null} $opts
     * @return list<array<string,mixed>>
     */
    public static function paraAluno(int $alunoId, array $opts = []): array
    {
        try {
            $svc = new self();
            if ($alunoId <= 0 || !$svc->quadro->moduloAtivo() || !$svc->quadro->model()->tabelasProntas()) {
                return [];
            }
            return $svc->montarParaAluno($alunoId, null, $opts);
        } catch (Throwable $e) {
            error_log('PainelNotasService::paraAluno: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param array{portal?:bool,ano_letivo?:int|null,bimestre?:int|null} $opts
     * @return list<array{quadro:array<string,mixed>,tabelas:list<array<string,mixed>>}>
     */
    public function montarParaAluno(int $alunoId, ?int $quadroId = null, array $opts = []): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        $this->opts = $opts;
        $notas = $this->carregarNotasAluno($alunoId);
        $materias = $this->catalogoMaterias();
        $out = [];
        foreach ($this->quadrosAlvo($quadroId) as $grupo) {
            $id = (int) ($grupo['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $completo = $this->quadro->carregarCompleto($id);
            if ($completo === null) {
                continue;
            }
            $tabelas = $this->montarTabelas($completo, $notas, $materias);
            if ($tabelas === []) {
                continue;
            }
            $out[] = [
                'quadro' => [
                    'id' => $id,
                    'nome' => (string) ($completo['nome'] ?? 'Quadro de notas'),
                    'escala_max' => (float) ($completo['escala_max'] ?? 10) ?: 10,
                ],
                'tabelas' => $tabelas,
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function quadrosAlvo(?int $quadroId): array
    {
        if ($quadroId !== null && $quadroId > 0) {
            $um = $this->quadro->model()->findById($quadroId);
            return is_array($um) ? [$um] : [];
        }
        return $this->quadro->model()->listar(true);
    }

    /**
     * @param array<string,mixed> $grupo
     * @param list<array<string,mixed>> $notas
     * @param array<int,array<string,mixed>> $materias
     * @return list<array<string,mixed>>
     */
    private function montarTabelas(array $grupo, array $notas, array $materias): array
    {
        $colunas = is_array($grupo['marcas'] ?? null) ? $grupo['marcas'] : [];
        $blocos = is_array($grupo['tipos'] ?? null) ? $grupo['tipos'] : [];
        if ($colunas === [] && $blocos === []) {
            return [];
        }
        $gruposTabela = [];
        if ($blocos === []) {
            $gruposTabela[] = [
                'titulo' => '',
                'bloco_id' => 0,
                'colunas' => $colunas,
                'materias_ids' => [],
            ];
        } else {
            foreach ($blocos as $bloco) {
                if (!is_array($bloco)) {
                    continue;
                }
                $ids = array_map('intval', $bloco['marcas_ids'] ?? []);
                $cols = $colunas;
                if ($ids !== []) {
                    $cols = array_values(array_filter(
                        $colunas,
                        static fn ($c) => in_array((int) ($c['id'] ?? 0), $ids, true)
                    ));
                }
                $gruposTabela[] = [
                    'titulo' => (string) ($bloco['nome'] ?? ''),
                    'bloco_id' => (int) ($bloco['id'] ?? 0),
                    'colunas' => $cols,
                    'materias_ids' => array_map('intval', $bloco['materias_ids'] ?? []),
                ];
            }
        }

        $quadroId = (int) ($grupo['id'] ?? 0);
        $tabelas = [];
        foreach ($gruposTabela as $g) {
            $cols = $this->prepararColunas($grupo, is_array($g['colunas']) ? $g['colunas'] : []);
            if ($cols === []) {
                continue;
            }
            $linhas = $this->linhasDoGrupo($g['materias_ids'], $materias, $notas, $cols, $quadroId, (int) $g['bloco_id']);
            $linhasPreenchidas = [];
            foreach ($linhas as $linha) {
                $celulas = [];
                $valsLanc = [];
                foreach ($cols as $col) {
                    if (GrupoRegrasNotas::ehLancamento($col)) {
                        $nota = $this->notaDaCelula($notas, $col, $linha['materia_id'], $quadroId, (int) $g['bloco_id']);
                        $celulas[$col['codigo']] = $nota;
                        if ($nota !== null) {
                            $valsLanc[$col['codigo']] = $nota;
                        }
                    }
                }
                foreach ($cols as $col) {
                    if (GrupoRegrasNotas::ehLancamento($col)) {
                        continue;
                    }
                    $celulas[$col['codigo']] = $this->calcularColuna($col, $valsLanc, $cols);
                }
                $linhasPreenchidas[] = [
                    'materia_id' => $linha['materia_id'],
                    'materia_nome' => $linha['materia_nome'],
                    'pai_nome' => $linha['pai_nome'],
                    'celulas' => $celulas,
                ];
            }
            $tabelas[] = [
                'titulo' => (string) $g['titulo'],
                'colunas' => array_map(static function (array $c): array {
                    return [
                        'id' => (int) ($c['id'] ?? 0),
                        'codigo' => (string) ($c['codigo'] ?? ''),
                        'nome' => (string) ($c['nome'] ?? ''),
                        'papel' => GrupoRegrasNotas::papelDaColuna($c),
                        'vai_para_boletim' => !empty($c['vai_para_boletim']),
                    ];
                }, $cols),
                'linhas' => $linhasPreenchidas,
            ];
        }
        return $tabelas;
    }

    /**
     * @param array<string,mixed> $grupo
     * @param list<array<string,mixed>> $colunas
     * @return list<array<string,mixed>>
     */
    private function prepararColunas(array $grupo, array $colunas): array
    {
        $out = [];
        $temCalculada = false;
        foreach ($colunas as $c) {
            if (!is_array($c)) {
                continue;
            }
            $codigo = trim((string) ($c['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            if (!GrupoRegrasNotas::ehLancamento($c)) {
                $temCalculada = true;
            }
            $out[] = $c;
        }
        $consolCodigo = GrupoRegrasNotas::normalizarCodigoColuna((string) ($grupo['coluna_consolidada_codigo'] ?? ''));
        $consolNome = trim((string) ($grupo['coluna_consolidada_nome'] ?? 'Média'));
        if (!$temCalculada && $consolCodigo !== '') {
            $refs = [];
            foreach ($out as $c) {
                if (GrupoRegrasNotas::ehLancamento($c)) {
                    $refs[] = (string) ($c['codigo'] ?? '');
                }
            }
            $out[] = [
                'id' => 0,
                'codigo' => $consolCodigo,
                'nome' => $consolNome !== '' ? $consolNome : 'Média',
                'papel' => 'calculada',
                'vai_para_boletim' => 1,
                'formula_json' => json_encode(
                    ['modo' => $this->modoFormulaDoQuadro($grupo), 'colunas' => $refs],
                    JSON_UNESCAPED_UNICODE
                ),
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $grupo
     */
    private function modoFormulaDoQuadro(array $grupo): string
    {
        $criterio = strtolower(trim((string) ($grupo['criterio_calculo'] ?? 'media')));
        if ($criterio === 'soma') {
            return 'soma';
        }
        if ($criterio === 'maior') {
            return 'maior';
        }
        if ($criterio === 'ultima') {
            return 'ultima';
        }
        return 'media';
    }

    /**
     * @param list<int> $materiasIds
     * @param array<int,array<string,mixed>> $materias
     * @param list<array<string,mixed>> $notas
     * @param list<array<string,mixed>> $colunas
     * @return list<array{materia_id:int,materia_nome:string,pai_nome:?string}>
     */
    private function linhasDoGrupo(
        array $materiasIds,
        array $materias,
        array $notas,
        array $colunas,
        int $quadroId,
        int $blocoId
    ): array {
        $ids = array_values(array_filter(array_map('intval', $materiasIds)));
        $cc = new ComponenteCurricular();
        $filhosPorPai = $cc->mapaFilhosPorPai();
        if ($ids === []) {
            $ids = $this->materiasComNotaNasColunas($notas, $colunas, $quadroId, $blocoId);
        }
        if ($ids === []) {
            return [];
        }
        $ids = $cc->expandirIdsComFilhos($ids);
        $linhas = [];
        $vistos = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($vistos[$id]) || !isset($materias[$id])) {
                continue;
            }
            $filhos = $filhosPorPai[$id] ?? [];
            if ($filhos !== []) {
                foreach ($filhos as $f) {
                    $fid = (int) ($f['id'] ?? 0);
                    if ($fid <= 0 || isset($vistos[$fid]) || !isset($materias[$fid])) {
                        continue;
                    }
                    $vistos[$fid] = true;
                    $linhas[] = [
                        'materia_id' => $fid,
                        'materia_nome' => (string) ($materias[$fid]['nome'] ?? $f['nome'] ?? ''),
                        'pai_nome' => (string) ($materias[$id]['nome'] ?? ''),
                    ];
                }
                continue;
            }
            $vistos[$id] = true;
            $paiId = (int) ($materias[$id]['pai_id'] ?? 0);
            $linhas[] = [
                'materia_id' => $id,
                'materia_nome' => (string) ($materias[$id]['nome'] ?? ''),
                'pai_nome' => $paiId > 0 && isset($materias[$paiId]) ? (string) $materias[$paiId]['nome'] : null,
            ];
        }
        return $linhas;
    }

    /**
     * @param list<array<string,mixed>> $notas
     * @param list<array<string,mixed>> $colunas
     * @return list<int>
     */
    private function materiasComNotaNasColunas(array $notas, array $colunas, int $quadroId, int $blocoId): array
    {
        $ids = [];
        foreach ($notas as $n) {
            foreach ($colunas as $col) {
                if (!GrupoRegrasNotas::ehLancamento($col)) {
                    continue;
                }
                if ($this->notaBateColuna($n, $col, $quadroId, $blocoId)) {
                    $mid = (int) ($n['materia_id'] ?? 0);
                    if ($mid > 0) {
                        $ids[$mid] = $mid;
                    }
                }
            }
        }
        return array_values($ids);
    }

    /**
     * @param list<array<string,mixed>> $notas
     * @param array<string,mixed> $coluna
     */
    private function notaDaCelula(array $notas, array $coluna, int $materiaId, int $quadroId, int $blocoId): ?float
    {
        $vals = [];
        foreach ($notas as $n) {
            if ((int) ($n['materia_id'] ?? 0) !== $materiaId) {
                continue;
            }
            if (!$this->notaBateColuna($n, $coluna, $quadroId, $blocoId)) {
                continue;
            }
            if ($n['nota'] === null) {
                continue;
            }
            $vals[] = (float) $n['nota'];
        }
        if ($vals === []) {
            return null;
        }
        return array_sum($vals) / count($vals);
    }

    /**
     * @param array<string,mixed> $nota
     * @param array<string,mixed> $coluna
     */
    private function notaBateColuna(array $nota, array $coluna, int $quadroId, int $blocoId): bool
    {
        $colId = (int) ($coluna['id'] ?? 0);
        $marcaNota = (int) ($nota['vinculo_marca_id'] ?? 0);
        if ($marcaNota <= 0) {
            $marcaNota = (int) ($nota['grupo_regras_marca_id'] ?? 0);
        }
        if ($colId > 0 && $marcaNota === $colId) {
            if ($blocoId <= 0) {
                return true;
            }
            $tipoNota = (int) ($nota['vinculo_tipo_id'] ?? 0);
            if ($tipoNota <= 0) {
                $tipoNota = (int) ($nota['grupo_regras_tipo_id'] ?? 0);
            }
            return $tipoNota === 0 || $tipoNota === $blocoId;
        }
        $grupoNota = (int) ($nota['vinculo_grupo_id'] ?? 0);
        if ($grupoNota <= 0) {
            $grupoNota = (int) ($nota['grupo_regras_notas_id'] ?? 0);
        }
        if ($quadroId > 0 && $grupoNota > 0 && $grupoNota !== $quadroId) {
            return false;
        }
        $numero = (int) ($coluna['numero'] ?? 0);
        $semana = (int) ($nota['semana'] ?? 0);
        if ($numero < 1 || $semana !== $numero) {
            return false;
        }
        $tipoCol = (int) ($coluna['tipo_nota_id'] ?? 0);
        $tipoEv = (int) ($nota['tipo_avaliacao_id'] ?? 0);
        if ($tipoCol > 0 && $tipoEv > 0 && $tipoCol !== $tipoEv) {
            return false;
        }
        if ($blocoId > 0) {
            $tipoNota = (int) ($nota['vinculo_tipo_id'] ?? 0);
            if ($tipoNota <= 0) {
                $tipoNota = (int) ($nota['grupo_regras_tipo_id'] ?? 0);
            }
            if ($tipoNota > 0 && $tipoNota !== $blocoId) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string,mixed> $coluna
     * @param array<string,float> $valsLanc
     * @param list<array<string,mixed>> $todas
     */
    private function calcularColuna(array $coluna, array $valsLanc, array $todas): ?float
    {
        $formula = [];
        $raw = $coluna['formula_json'] ?? '';
        if (is_string($raw) && $raw !== '') {
            $dec = json_decode($raw, true);
            $formula = is_array($dec) ? $dec : [];
        }
        $modo = strtolower(trim((string) ($formula['modo'] ?? 'media')));
        $codigos = [];
        if (isset($formula['colunas']) && is_array($formula['colunas'])) {
            foreach ($formula['colunas'] as $c) {
                $c = trim((string) $c);
                if ($c !== '') {
                    $codigos[] = $c;
                }
            }
        }
        $pesos = [];
        if (isset($formula['pesos']) && is_array($formula['pesos'])) {
            foreach ($formula['pesos'] as $cod => $p) {
                $pesos[trim((string) $cod)] = (float) $p;
            }
        }
        $permitidos = [];
        foreach ($todas as $c) {
            if (GrupoRegrasNotas::ehLancamento($c)) {
                $permitidos[(string) ($c['codigo'] ?? '')] = true;
            }
        }
        if ($codigos === []) {
            $codigos = array_keys($permitidos);
        }
        $vals = [];
        foreach ($codigos as $cod) {
            if (!isset($permitidos[$cod]) || !isset($valsLanc[$cod])) {
                continue;
            }
            $vals[$cod] = $valsLanc[$cod];
        }
        if ($vals === []) {
            return null;
        }
        if ($modo === 'soma') {
            return array_sum($vals);
        }
        if ($modo === 'maior') {
            return max($vals);
        }
        if ($modo === 'ultima') {
            $ultimo = null;
            foreach ($todas as $c) {
                $cod = (string) ($c['codigo'] ?? '');
                if (isset($vals[$cod])) {
                    $ultimo = $vals[$cod];
                }
            }
            return $ultimo;
        }
        if ($modo === 'pesos' && $pesos !== []) {
            $num = 0.0;
            $den = 0.0;
            foreach ($vals as $cod => $v) {
                $p = $pesos[$cod] ?? 0.0;
                if ($p <= 0) {
                    continue;
                }
                $num += $v * $p;
                $den += $p;
            }
            return $den > 0 ? $num / $den : null;
        }
        return array_sum($vals) / count($vals);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function catalogoMaterias(): array
    {
        try {
            $rows = (new ComponenteCurricular())->getAll(true) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[$id] = [
                'id' => $id,
                'nome' => (string) ($r['nome'] ?? ''),
                'pai_id' => (int) ($r['pai_id'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function carregarNotasAluno(int $alunoId): array
    {
        $out = [];
        foreach ($this->notasLancadas($alunoId) as $n) {
            $out[] = $n;
        }
        foreach ($this->notasOnline($alunoId) as $n) {
            $out[] = $n;
        }
        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function notasLancadas(int $alunoId): array
    {
        try {
            $existe = $this->db->fetch("SHOW TABLES LIKE 'provas_blocos_notas_lancadas'");
            if (!$existe) {
                return [];
            }
        } catch (Throwable $e) {
            return [];
        }
        $colsPb = $this->colunasProvasBlocos();
        $sel = ['n.nota', 'n.materia_id'];
        foreach (['semana', 'tipo_avaliacao_id', 'grupo_regras_marca_id', 'grupo_regras_tipo_id', 'grupo_regras_notas_id'] as $c) {
            if (isset($colsPb[$c])) {
                $sel[] = 'pb.`' . $c . '`';
            }
        }
        $joinV = '';
        $selV = ['NULL AS vinculo_marca_id', 'NULL AS vinculo_tipo_id', 'NULL AS vinculo_grupo_id'];
        $vinculo = $this->quadro->model()->nomeTabela('vinculos_prova');
        if ($this->tabelaExiste($vinculo)) {
            $joinV = ' LEFT JOIN `' . str_replace('`', '', $vinculo) . '` v ON v.bloco_id = pb.id';
            $selV = ['v.marca_id AS vinculo_marca_id', 'v.tipo_id AS vinculo_tipo_id', 'v.grupo_id AS vinculo_grupo_id'];
        }
        $params = ['aluno' => $alunoId];
        $sql = 'SELECT ' . implode(', ', $sel) . ', ' . implode(', ', $selV) . '
                  FROM provas_blocos_notas_lancadas n
            INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.deleted_at IS NULL
                  ' . $joinV . '
                 WHERE n.aluno_id = :aluno AND n.nota IS NOT NULL'
            . $this->sqlFiltroPortalEPeriodo($params);
        try {
            return $this->db->fetchAll($sql, $params) ?: [];
        } catch (Throwable $e) {
            error_log('PainelNotasService::notasLancadas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function notasOnline(int $alunoId): array
    {
        $colsPb = $this->colunasProvasBlocos();
        $sel = ['pr.nota', 'p.materia_id'];
        foreach (['semana', 'tipo_avaliacao_id', 'grupo_regras_marca_id', 'grupo_regras_tipo_id', 'grupo_regras_notas_id'] as $c) {
            if (isset($colsPb[$c])) {
                $sel[] = 'pb.`' . $c . '`';
            }
        }
        $joinV = '';
        $selV = ['NULL AS vinculo_marca_id', 'NULL AS vinculo_tipo_id', 'NULL AS vinculo_grupo_id'];
        $vinculo = $this->quadro->model()->nomeTabela('vinculos_prova');
        if ($this->tabelaExiste($vinculo)) {
            $joinV = ' LEFT JOIN `' . str_replace('`', '', $vinculo) . '` v ON v.bloco_id = pb.id';
            $selV = ['v.marca_id AS vinculo_marca_id', 'v.tipo_id AS vinculo_tipo_id', 'v.grupo_id AS vinculo_grupo_id'];
        }
        $params = ['aluno' => $alunoId, 'status' => 'finalizado'];
        $sql = 'SELECT ' . implode(', ', $sel) . ', ' . implode(', ', $selV) . '
                  FROM provas_realizacoes pr
            INNER JOIN provas p ON p.id = pr.prova_id
            INNER JOIN provas_blocos_vinculo pbv ON pbv.prova_id = pr.prova_id
            INNER JOIN provas_blocos pb ON pb.id = pbv.bloco_id AND pb.deleted_at IS NULL
                  ' . $joinV . '
                 WHERE pr.aluno_id = :aluno
                   AND pr.status = :status
                   AND pr.nota IS NOT NULL'
            . $this->sqlFiltroPortalEPeriodo($params);
        try {
            return $this->db->fetchAll($sql, $params) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string,true>
     */
    private function colunasProvasBlocos(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provas_blocos'
                    AND COLUMN_NAME IN ('semana','tipo_avaliacao_id','grupo_regras_marca_id','grupo_regras_tipo_id','grupo_regras_notas_id','visivel_no_portal_aluno','ano_letivo','bimestre')"
            ) ?: [];
        } catch (Throwable $e) {
            return $cache;
        }
        foreach ($rows as $r) {
            $n = (string) ($r['COLUMN_NAME'] ?? '');
            if ($n !== '') {
                $cache[$n] = true;
            }
        }
        return $cache;
    }

    /**
     * @param array<string,mixed> $params
     */
    private function sqlFiltroPortalEPeriodo(array &$params): string
    {
        $sql = '';
        $portal = $this->opts['portal'] ?? true;
        $cols = $this->colunasProvasBlocos();
        if ($portal) {
            if (isset($cols['visivel_no_portal_aluno'])) {
                $sql .= ' AND pb.visivel_no_portal_aluno = 1';
            } elseif (isset($cols['bimestre'])) {
                $sql .= ' AND (pb.bimestre IS NULL OR pb.bimestre NOT BETWEEN 1 AND 4)';
            }
        }
        $ano = (int) ($this->opts['ano_letivo'] ?? 0);
        if ($ano > 0 && isset($cols['ano_letivo'])) {
            $sql .= ' AND pb.ano_letivo = :ano_letivo';
            $params['ano_letivo'] = $ano;
        }
        $bim = (int) ($this->opts['bimestre'] ?? 0);
        if ($bim >= 1 && $bim <= 4 && isset($cols['bimestre'])) {
            $sql .= ' AND pb.bimestre = :bimestre';
            $params['bimestre'] = $bim;
        }
        return $sql;
    }

    private function tabelaExiste(string $nome): bool
    {
        $nome = str_replace('`', '', $nome);
        if ($nome === '' || !preg_match('/^[a-z0-9_]+$/i', $nome)) {
            return false;
        }
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
}
