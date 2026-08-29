<?php

namespace App\Modulos\VidaEscolar\Models;

use Database;

/**
 * Persistência das fichas oficiais de boletim, escolarização e importação.
 */
class VidaEscolar
{
    private $db;

    private ?bool $schemaProntoCache = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function schemaPronto(): bool
    {
        if ($this->schemaProntoCache !== null) {
            return $this->schemaProntoCache;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'boletim_fichas'");
            $this->schemaProntoCache = $row !== false && !empty($row);
        } catch (\Throwable $e) {
            $this->schemaProntoCache = false;
        }
        return $this->schemaProntoCache;
    }

    public function findFicha(int $id): ?array
    {
        $row = $this->db->fetch(
            "SELECT f.*, a.nome AS aluno_nome, a.cpf AS aluno_cpf, a.data_nasc AS aluno_data_nasc,
                    t.nome AS turma_nome, t.serie AS turma_serie, t.matriz_curricular_id
             FROM boletim_fichas f
             INNER JOIN alunos a ON a.id = f.aluno_id
             LEFT JOIN turmas t ON t.id = f.turma_id
             WHERE f.id = :id",
            ['id' => $id]
        );
        return is_array($row) ? $row : null;
    }

    public function findFichaAlunoAno(int $alunoId, int $anoLetivo, ?int $turmaId = null): ?array
    {
        $sql = "SELECT f.* FROM boletim_fichas f
                WHERE f.aluno_id = :aluno_id AND f.ano_letivo = :ano_letivo";
        $params = ['aluno_id' => $alunoId, 'ano_letivo' => $anoLetivo];
        if ($turmaId !== null && $turmaId > 0) {
            $sql .= ' AND f.turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        }
        $sql .= ' ORDER BY f.id DESC LIMIT 1';
        $row = $this->db->fetch($sql, $params);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function listarFichasAluno(int $alunoId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT f.*, t.nome AS turma_nome
             FROM boletim_fichas f
             LEFT JOIN turmas t ON t.id = f.turma_id
             WHERE f.aluno_id = :id
             ORDER BY f.ano_letivo DESC, f.id DESC",
            ['id' => $alunoId]
        );
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string,mixed>> */
    public function listarFichasTurma(int $turmaId, int $anoLetivo): array
    {
        $rows = $this->db->fetchAll(
            "SELECT f.*, a.nome AS aluno_nome
             FROM boletim_fichas f
             INNER JOIN alunos a ON a.id = f.aluno_id
             WHERE f.turma_id = :turma_id AND f.ano_letivo = :ano
             ORDER BY a.nome ASC",
            ['turma_id' => $turmaId, 'ano' => $anoLetivo]
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Última ficha do ano letivo para cada aluno informado.
     *
     * @param list<int> $alunoIds
     * @return list<array<string,mixed>>
     */
    public function listarFichasAlunosAno(array $alunoIds, int $anoLetivo): array
    {
        $ids = [];
        foreach ($alunoIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        if ($ids === [] || $anoLetivo <= 0) {
            return [];
        }
        $params = ['ano' => $anoLetivo];
        $placeholders = [];
        foreach ($ids as $i => $id) {
            $chave = 'a' . $i;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $id;
        }
        $rows = $this->db->fetchAll(
            'SELECT f.*, a.nome AS aluno_nome
             FROM boletim_fichas f
             INNER JOIN (
                SELECT aluno_id, MAX(id) AS id
                FROM boletim_fichas
                WHERE ano_letivo = :ano AND aluno_id IN (' . implode(',', $placeholders) . ')
                GROUP BY aluno_id
             ) ult ON ult.id = f.id
             INNER JOIN alunos a ON a.id = f.aluno_id
             ORDER BY a.nome ASC',
            $params
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Última ficha do ano por aluno, opcionalmente filtrada por turma.
     *
     * @return list<array<string,mixed>>
     */
    public function listarFichasAnoLetivo(int $anoLetivo, int $turmaId = 0): array
    {
        if ($anoLetivo <= 0) {
            return [];
        }
        $params = ['ano' => $anoLetivo];
        $whereTurmaSub = '';
        if ($turmaId > 0) {
            $whereTurmaSub = ' AND turma_id = :turma_id';
            $params['turma_id'] = $turmaId;
        }
        $rows = $this->db->fetchAll(
            'SELECT f.*, a.nome AS aluno_nome, a.ra, t.nome AS turma_nome
             FROM boletim_fichas f
             INNER JOIN (
                SELECT aluno_id, MAX(id) AS id
                FROM boletim_fichas
                WHERE ano_letivo = :ano' . $whereTurmaSub . '
                GROUP BY aluno_id
             ) ult ON ult.id = f.id
             INNER JOIN alunos a ON a.id = f.aluno_id
             LEFT JOIN turmas t ON t.id = f.turma_id
             WHERE a.ativo = 1
             ORDER BY t.nome ASC, a.nome ASC',
            $params
        );
        return is_array($rows) ? $rows : [];
    }

    public function criarFicha(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO boletim_fichas
                (aluno_id, turma_id, matricula_id, ano_letivo, serie_nome, status, observacao)
             VALUES
                (:aluno_id, :turma_id, :matricula_id, :ano_letivo, :serie_nome, :status, :observacao)",
            [
                'aluno_id' => (int) $data['aluno_id'],
                'turma_id' => !empty($data['turma_id']) ? (int) $data['turma_id'] : null,
                'matricula_id' => !empty($data['matricula_id']) ? (int) $data['matricula_id'] : null,
                'ano_letivo' => (int) $data['ano_letivo'],
                'serie_nome' => $data['serie_nome'] ?? null,
                'status' => $data['status'] ?? 'em_curso',
                'observacao' => $data['observacao'] ?? null,
            ]
        );
    }

    public function atualizarFicha(int $id, array $campos): void
    {
        $sets = [];
        $params = ['id' => $id];
        $permitidos = [
            'status', 'versao', 'homologada_em', 'homologada_por',
            'observacao', 'serie_nome', 'turma_id', 'matricula_id',
        ];
        foreach ($campos as $k => $v) {
            if (!in_array($k, $permitidos, true)) {
                continue;
            }
            $sets[] = "`$k` = :$k";
            $params[$k] = $v;
        }
        if ($sets === []) {
            return;
        }
        $this->db->update(
            'UPDATE boletim_fichas SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    public function criarLinha(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO boletim_ficha_linhas
                (ficha_id, materia_id, componente_nome, carga_horaria, ordem)
             VALUES
                (:ficha_id, :materia_id, :componente_nome, :carga_horaria, :ordem)",
            [
                'ficha_id' => (int) $data['ficha_id'],
                'materia_id' => !empty($data['materia_id']) ? (int) $data['materia_id'] : null,
                'componente_nome' => (string) $data['componente_nome'],
                'carga_horaria' => $data['carga_horaria'] !== null && $data['carga_horaria'] !== ''
                    ? (int) $data['carga_horaria'] : null,
                'ordem' => (int) ($data['ordem'] ?? 0),
            ]
        );
    }

    public function criarCelula(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO boletim_ficha_celulas
                (linha_id, periodo_numero, nota, conceito, faltas, aulas_dadas, origem, status,
                 escola_origem, documento_id, nota_original, escala_original, observacao)
             VALUES
                (:linha_id, :periodo_numero, :nota, :conceito, :faltas, :aulas_dadas, :origem, :status,
                 :escola_origem, :documento_id, :nota_original, :escala_original, :observacao)",
            [
                'linha_id' => (int) $data['linha_id'],
                'periodo_numero' => (int) $data['periodo_numero'],
                'nota' => $data['nota'] ?? null,
                'conceito' => $data['conceito'] ?? null,
                'faltas' => $data['faltas'] ?? null,
                'aulas_dadas' => $data['aulas_dadas'] ?? null,
                'origem' => $data['origem'] ?? 'vazia',
                'status' => $data['status'] ?? 'aberta',
                'escola_origem' => $data['escola_origem'] ?? null,
                'documento_id' => !empty($data['documento_id']) ? (int) $data['documento_id'] : null,
                'nota_original' => $data['nota_original'] ?? null,
                'escala_original' => $data['escala_original'] ?? null,
                'observacao' => $data['observacao'] ?? null,
            ]
        );
    }

    public function findLinha(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM boletim_ficha_linhas WHERE id = :id',
            ['id' => $id]
        );
        return is_array($row) ? $row : null;
    }

    /**
     * Eventos oficiais (preview=0) já gravados para o aluno.
     *
     * @return list<array<string,mixed>>
     */
    public function listarResultadosGeradosOficiais(int $alunoId): array
    {
        try {
            $tem = $this->db->fetch("SHOW TABLES LIKE 'boletim_resultados_gerados'");
            if (!$tem) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }
        $vigenteSql = '';
        try {
            $colVigente = $this->db->fetch("SHOW COLUMNS FROM boletim_resultados_gerados LIKE 'vigente'");
            if ($colVigente) {
                $vigenteSql = ' AND g.vigente = 1';
            }
        } catch (\Throwable $e) {
            $vigenteSql = '';
        }
        $rows = $this->db->fetchAll(
            "SELECT g.id, g.materia_id, g.materia_nome, g.media_final, g.notas_json, g.colunas_json,
                    g.periodo_ref, r.exibir_em, r.bimestre, r.ano_letivo
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             WHERE g.aluno_id = :aid AND g.preview = 0{$vigenteSql}
             ORDER BY g.id ASC",
            ['aid' => $alunoId]
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param list<int> $alunoIds
     * @return array<int, list<array<string,mixed>>>
     */
    public function listarResultadosGeradosOficiaisPorAlunos(array $alunoIds): array
    {
        $alunoIds = array_values(array_unique(array_filter(array_map('intval', $alunoIds), static function ($id) {
            return $id > 0;
        })));
        if ($alunoIds === []) {
            return [];
        }
        if (!$this->temTabelaResultadosGerados()) {
            return [];
        }
        $vigenteSql = $this->sqlFiltroVigenteResultados();
        $ph = [];
        $params = [];
        foreach ($alunoIds as $i => $id) {
            $k = 'a' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        $rows = $this->db->fetchAll(
            "SELECT g.id, g.aluno_id, g.materia_id, g.materia_nome, g.media_final, g.notas_json, g.colunas_json,
                    g.periodo_ref, r.exibir_em, r.bimestre, r.ano_letivo
             FROM boletim_resultados_gerados g
             INNER JOIN boletim_regras r ON r.id = g.regra_id
             WHERE g.aluno_id IN (" . implode(',', $ph) . ") AND g.preview = 0{$vigenteSql}
             ORDER BY g.aluno_id ASC, g.id ASC",
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $aid = (int) ($row['aluno_id'] ?? 0);
            if ($aid > 0) {
                $out[$aid][] = $row;
            }
        }

        return $out;
    }

    /**
     * @param list<int> $alunoIds
     * @return array<int, array<string,mixed>>
     */
    public function listarFichasPorAlunosAno(array $alunoIds, int $anoLetivo): array
    {
        $alunoIds = array_values(array_unique(array_filter(array_map('intval', $alunoIds), static function ($id) {
            return $id > 0;
        })));
        if ($alunoIds === [] || $anoLetivo <= 0) {
            return [];
        }
        $ph = [];
        $params = ['ano' => $anoLetivo];
        foreach ($alunoIds as $i => $id) {
            $k = 'a' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        $rows = $this->db->fetchAll(
            'SELECT f.* FROM boletim_fichas f
             WHERE f.ano_letivo = :ano AND f.aluno_id IN (' . implode(',', $ph) . ')
             ORDER BY f.id DESC',
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $aid = (int) ($row['aluno_id'] ?? 0);
            $tid = (int) ($row['turma_id'] ?? 0);
            if ($aid <= 0) {
                continue;
            }
            $chave = $aid . ':' . $tid;
            if (!isset($out[$chave])) {
                $out[$chave] = $row;
            }
        }

        return $out;
    }

    /**
     * @param list<int> $fichaIds
     * @return array<int, list<array<string,mixed>>>
     */
    public function listarLinhasPorFichas(array $fichaIds): array
    {
        $fichaIds = array_values(array_unique(array_filter(array_map('intval', $fichaIds), static function ($id) {
            return $id > 0;
        })));
        if ($fichaIds === []) {
            return [];
        }
        $ph = [];
        $params = [];
        foreach ($fichaIds as $i => $id) {
            $k = 'f' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM boletim_ficha_linhas
             WHERE ficha_id IN (' . implode(',', $ph) . ')
             ORDER BY ficha_id ASC, ordem ASC, id ASC',
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $fid = (int) ($row['ficha_id'] ?? 0);
            $out[$fid][] = $row;
        }

        return $out;
    }

    /**
     * @param list<int> $linhaIds
     * @return array<string, array<string,mixed>>
     */
    public function listarCelulasPorLinhas(array $linhaIds): array
    {
        $linhaIds = array_values(array_unique(array_filter(array_map('intval', $linhaIds), static function ($id) {
            return $id > 0;
        })));
        if ($linhaIds === []) {
            return [];
        }
        $ph = [];
        $params = [];
        foreach ($linhaIds as $i => $id) {
            $k = 'l' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM boletim_ficha_celulas
             WHERE linha_id IN (' . implode(',', $ph) . ')',
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $lid = (int) ($row['linha_id'] ?? 0);
            $p = (int) ($row['periodo_numero'] ?? 0);
            $out[$lid . ':' . $p] = $row;
        }

        return $out;
    }

    /**
     * @param list<array{id:int, nota?:mixed, faltas?:mixed, origem?:string}> $updates
     */
    public function atualizarCelulasEmLote(array $updates): void
    {
        foreach (array_chunk($updates, 500) as $lote) {
            $ids = [];
            $notaSql = [];
            $faltasSql = [];
            $origemSql = [];
            $params = [];
            foreach ($lote as $i => $u) {
                $id = (int) ($u['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $ikN = 'cidn' . $i;
                $ikF = 'cidf' . $i;
                $ikO = 'cido' . $i;
                $nk = 'n' . $i;
                $fk = 'f' . $i;
                $ok = 'o' . $i;
                $wk = 'wid' . $i;
                $ids[] = ':' . $wk;
                $params[$wk] = $id;
                $params[$ikN] = $id;
                $params[$ikF] = $id;
                $params[$ikO] = $id;
                $params[$nk] = $u['nota'] ?? null;
                $params[$fk] = $u['faltas'] ?? null;
                $params[$ok] = $u['origem'] ?? 'calculada';
                $notaSql[] = 'WHEN :' . $ikN . ' THEN :' . $nk;
                $faltasSql[] = 'WHEN :' . $ikF . ' THEN :' . $fk;
                $origemSql[] = 'WHEN :' . $ikO . ' THEN :' . $ok;
            }
            if ($ids === []) {
                continue;
            }
            $this->db->update(
                'UPDATE boletim_ficha_celulas SET
                    nota = CASE id ' . implode(' ', $notaSql) . ' END,
                    faltas = CASE id ' . implode(' ', $faltasSql) . ' END,
                    origem = CASE id ' . implode(' ', $origemSql) . ' END
                 WHERE id IN (' . implode(',', $ids) . ')',
                $params
            );
        }
    }

    private function temTabelaResultadosGerados(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $tem = $this->db->fetch("SHOW TABLES LIKE 'boletim_resultados_gerados'");
            $cache = $tem !== false && !empty($tem);
        } catch (\Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    private function sqlFiltroVigenteResultados(): string
    {
        static $sql = null;
        if ($sql !== null) {
            return $sql;
        }
        $sql = '';
        try {
            $col = $this->db->fetch("SHOW COLUMNS FROM boletim_resultados_gerados LIKE 'vigente'");
            if ($col) {
                $sql = ' AND g.vigente = 1';
            }
        } catch (\Throwable $e) {
            $sql = '';
        }

        return $sql;
    }

    /** @return list<array<string,mixed>> */
    public function listarLinhas(int $fichaId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM boletim_ficha_linhas WHERE ficha_id = :id ORDER BY ordem ASC, id ASC',
            ['id' => $fichaId]
        );
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string,mixed>> */
    public function listarCelulas(int $fichaId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT c.*, l.materia_id, l.componente_nome, l.ordem
             FROM boletim_ficha_celulas c
             INNER JOIN boletim_ficha_linhas l ON l.id = c.linha_id
             WHERE l.ficha_id = :id
             ORDER BY l.ordem ASC, c.periodo_numero ASC",
            ['id' => $fichaId]
        );
        return is_array($rows) ? $rows : [];
    }

    public function findCelula(int $celulaId): ?array
    {
        $row = $this->db->fetch(
            "SELECT c.*, l.ficha_id, l.materia_id, l.componente_nome
             FROM boletim_ficha_celulas c
             INNER JOIN boletim_ficha_linhas l ON l.id = c.linha_id
             WHERE c.id = :id",
            ['id' => $celulaId]
        );
        return is_array($row) ? $row : null;
    }

    public function findCelulaLinhaPeriodo(int $linhaId, int $periodo): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM boletim_ficha_celulas WHERE linha_id = :lid AND periodo_numero = :p',
            ['lid' => $linhaId, 'p' => $periodo]
        );
        return is_array($row) ? $row : null;
    }

    public function atualizarCelula(int $id, array $campos): void
    {
        $sets = [];
        $params = ['id' => $id];
        $permitidos = [
            'nota', 'conceito', 'faltas', 'aulas_dadas', 'origem', 'status',
            'escola_origem', 'documento_id', 'nota_original', 'escala_original',
            'observacao', 'versao',
        ];
        foreach ($campos as $k => $v) {
            if (!in_array($k, $permitidos, true)) {
                continue;
            }
            $sets[] = "`$k` = :$k";
            $params[$k] = $v;
        }
        if ($sets === []) {
            return;
        }
        $this->db->update(
            'UPDATE boletim_ficha_celulas SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    public function registrarAuditoria(array $data): void
    {
        $this->db->insert(
            "INSERT INTO boletim_ficha_auditoria
                (ficha_id, celula_id, acao, campo, valor_anterior, valor_novo, motivo,
                 usuario_id, usuario_nome, usuario_perfil)
             VALUES
                (:ficha_id, :celula_id, :acao, :campo, :valor_anterior, :valor_novo, :motivo,
                 :usuario_id, :usuario_nome, :usuario_perfil)",
            [
                'ficha_id' => (int) $data['ficha_id'],
                'celula_id' => !empty($data['celula_id']) ? (int) $data['celula_id'] : null,
                'acao' => (string) $data['acao'],
                'campo' => $data['campo'] ?? null,
                'valor_anterior' => $data['valor_anterior'] ?? null,
                'valor_novo' => $data['valor_novo'] ?? null,
                'motivo' => $data['motivo'] ?? null,
                'usuario_id' => !empty($data['usuario_id']) ? (int) $data['usuario_id'] : null,
                'usuario_nome' => $data['usuario_nome'] ?? null,
                'usuario_perfil' => $data['usuario_perfil'] ?? null,
            ]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listarAuditoria(int $fichaId, int $limite = 80): array
    {
        $limite = max(1, min(200, $limite));
        $rows = $this->db->fetchAll(
            "SELECT * FROM boletim_ficha_auditoria
             WHERE ficha_id = :id
             ORDER BY id DESC
             LIMIT $limite",
            ['id' => $fichaId]
        );
        return is_array($rows) ? $rows : [];
    }

    public function criarAnoEscolarizacao(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO escolarizacao_anos
                (aluno_id, ano_letivo, serie_ano, origem, escola_nome, escola_inep, municipio, uf,
                 resultado, carga_horaria_total, faltas, frequencia_percentual, observacao, documento_id, ficha_id)
             VALUES
                (:aluno_id, :ano_letivo, :serie_ano, :origem, :escola_nome, :escola_inep, :municipio, :uf,
                 :resultado, :carga_horaria_total, :faltas, :frequencia_percentual, :observacao, :documento_id, :ficha_id)",
            [
                'aluno_id' => (int) $data['aluno_id'],
                'ano_letivo' => (string) $data['ano_letivo'],
                'serie_ano' => (string) $data['serie_ano'],
                'origem' => $data['origem'] ?? 'externo',
                'escola_nome' => $data['escola_nome'] ?? null,
                'escola_inep' => $data['escola_inep'] ?? null,
                'municipio' => $data['municipio'] ?? null,
                'uf' => $data['uf'] ?? null,
                'resultado' => $data['resultado'] ?? null,
                'carga_horaria_total' => $data['carga_horaria_total'] ?? null,
                'faltas' => $data['faltas'] ?? null,
                'frequencia_percentual' => $data['frequencia_percentual'] ?? null,
                'observacao' => $data['observacao'] ?? null,
                'documento_id' => !empty($data['documento_id']) ? (int) $data['documento_id'] : null,
                'ficha_id' => !empty($data['ficha_id']) ? (int) $data['ficha_id'] : null,
            ]
        );
    }

    public function atualizarAnoEscolarizacao(int $id, array $campos): void
    {
        $sets = [];
        $params = ['id' => $id];
        $permitidos = ['resultado', 'ficha_id', 'faltas', 'frequencia_percentual', 'observacao'];
        foreach ($campos as $k => $v) {
            if (!in_array($k, $permitidos, true)) {
                continue;
            }
            $sets[] = "`$k` = :$k";
            $params[$k] = $v;
        }
        if ($sets === []) {
            return;
        }
        $this->db->update(
            'UPDATE escolarizacao_anos SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    public function criarComponenteEscolarizacao(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO escolarizacao_componentes
                (ano_id, componente_original, materia_id, nota_original, escala_original,
                 nota_convertida, carga_horaria, frequencia_percentual, ordem)
             VALUES
                (:ano_id, :componente_original, :materia_id, :nota_original, :escala_original,
                 :nota_convertida, :carga_horaria, :frequencia_percentual, :ordem)",
            [
                'ano_id' => (int) $data['ano_id'],
                'componente_original' => (string) $data['componente_original'],
                'materia_id' => !empty($data['materia_id']) ? (int) $data['materia_id'] : null,
                'nota_original' => $data['nota_original'] ?? null,
                'escala_original' => $data['escala_original'] ?? null,
                'nota_convertida' => $data['nota_convertida'] ?? null,
                'carga_horaria' => $data['carga_horaria'] ?? null,
                'frequencia_percentual' => $data['frequencia_percentual'] ?? null,
                'ordem' => (int) ($data['ordem'] ?? 0),
            ]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listarAnosEscolarizacao(int $alunoId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM escolarizacao_anos WHERE aluno_id = :id ORDER BY ano_letivo ASC, id ASC',
            ['id' => $alunoId]
        );
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string,mixed>> */
    public function listarComponentesAno(int $anoId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM escolarizacao_componentes WHERE ano_id = :id ORDER BY ordem ASC, id ASC',
            ['id' => $anoId]
        );
        return is_array($rows) ? $rows : [];
    }

    public function findAnoEscolarizacao(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM escolarizacao_anos WHERE id = :id', ['id' => $id]);
        return is_array($row) ? $row : null;
    }

    public function criarDocumento(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO vida_escolar_documentos
                (aluno_id, tipo, escola_emissora, data_emissao, arquivo_key, arquivo_nome,
                 arquivo_mime, arquivo_tamanho, status, observacao, enviado_por)
             VALUES
                (:aluno_id, :tipo, :escola_emissora, :data_emissao, :arquivo_key, :arquivo_nome,
                 :arquivo_mime, :arquivo_tamanho, :status, :observacao, :enviado_por)",
            [
                'aluno_id' => (int) $data['aluno_id'],
                'tipo' => $data['tipo'] ?? 'historico',
                'escola_emissora' => $data['escola_emissora'] ?? null,
                'data_emissao' => $data['data_emissao'] ?? null,
                'arquivo_key' => $data['arquivo_key'] ?? null,
                'arquivo_nome' => $data['arquivo_nome'] ?? null,
                'arquivo_mime' => $data['arquivo_mime'] ?? null,
                'arquivo_tamanho' => $data['arquivo_tamanho'] ?? null,
                'status' => $data['status'] ?? 'recebido',
                'observacao' => $data['observacao'] ?? null,
                'enviado_por' => !empty($data['enviado_por']) ? (int) $data['enviado_por'] : null,
            ]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listarDocumentos(int $alunoId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM vida_escolar_documentos WHERE aluno_id = :id ORDER BY id DESC',
            ['id' => $alunoId]
        );
        return is_array($rows) ? $rows : [];
    }

    public function findDocumento(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM vida_escolar_documentos WHERE id = :id', ['id' => $id]);
        return is_array($row) ? $row : null;
    }

    public function criarImportacao(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO vida_escolar_importacoes
                (aluno_id, documento_id, escola_origem, escola_inep, municipio, uf,
                 data_transferencia, data_entrada, status, payload_json, criado_por)
             VALUES
                (:aluno_id, :documento_id, :escola_origem, :escola_inep, :municipio, :uf,
                 :data_transferencia, :data_entrada, :status, :payload_json, :criado_por)",
            [
                'aluno_id' => (int) $data['aluno_id'],
                'documento_id' => !empty($data['documento_id']) ? (int) $data['documento_id'] : null,
                'escola_origem' => $data['escola_origem'] ?? null,
                'escola_inep' => $data['escola_inep'] ?? null,
                'municipio' => $data['municipio'] ?? null,
                'uf' => $data['uf'] ?? null,
                'data_transferencia' => $data['data_transferencia'] ?? null,
                'data_entrada' => $data['data_entrada'] ?? null,
                'status' => $data['status'] ?? 'rascunho',
                'payload_json' => $data['payload_json'] ?? null,
                'criado_por' => !empty($data['criado_por']) ? (int) $data['criado_por'] : null,
            ]
        );
    }

    public function atualizarImportacao(int $id, array $campos): void
    {
        $sets = [];
        $params = ['id' => $id];
        $permitidos = [
            'documento_id', 'escola_origem', 'escola_inep', 'municipio', 'uf',
            'data_transferencia', 'data_entrada', 'status', 'payload_json', 'resumo_json',
            'validada_por', 'validada_em', 'cancelada_por', 'cancelada_em', 'motivo_cancelamento',
        ];
        foreach ($campos as $k => $v) {
            if (!in_array($k, $permitidos, true)) {
                continue;
            }
            $sets[] = "`$k` = :$k";
            $params[$k] = $v;
        }
        if ($sets === []) {
            return;
        }
        $this->db->update(
            'UPDATE vida_escolar_importacoes SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    public function findImportacao(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM vida_escolar_importacoes WHERE id = :id', ['id' => $id]);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function listarImportacoes(int $alunoId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM vida_escolar_importacoes WHERE aluno_id = :id ORDER BY id DESC',
            ['id' => $alunoId]
        );
        return is_array($rows) ? $rows : [];
    }

    public function alunoPorId(int $id): ?array
    {
        $row = $this->db->fetch(
            "SELECT a.*, t.nome AS turma_nome, t.serie AS turma_serie, t.matriz_curricular_id,
                    t.ano_letivo AS turma_ano_letivo
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id",
            ['id' => $id]
        );
        return is_array($row) ? $row : null;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<string,mixed>>
     */
    public function alunosPorIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
        if ($ids === []) {
            return [];
        }
        $ph = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $k = 'a' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        $rows = $this->db->fetchAll(
            "SELECT a.*, t.nome AS turma_nome, t.serie AS turma_serie, t.matriz_curricular_id,
                    t.ano_letivo AS turma_ano_letivo
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id IN (" . implode(',', $ph) . ')',
            $params
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $out[$id] = $row;
            }
        }

        return $out;
    }

    public function turmaPorId(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM turmas WHERE id = :id', ['id' => $id]);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function turmasAtivas(int $anoLetivo): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, nome, serie, ano_letivo, matriz_curricular_id
             FROM turmas WHERE ativo = 1 AND ano_letivo = :ano ORDER BY nome ASC',
            ['ano' => $anoLetivo]
        );
        return is_array($rows) ? $rows : [];
    }

    /** @return list<int> */
    public function anosLetivosTurmas(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT ano_letivo FROM turmas WHERE ativo = 1 AND ano_letivo IS NOT NULL ORDER BY ano_letivo DESC'
        );
        $anos = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $a = (int) ($r['ano_letivo'] ?? 0);
            if ($a > 0) {
                $anos[] = $a;
            }
        }
        if ($anos === []) {
            $anos[] = (int) date('Y');
        }
        return $anos;
    }

    /** @return list<array<string,mixed>> */
    public function componentesDaTurma(int $turmaId): array
    {
        $turma = $this->turmaPorId($turmaId);
        if (!$turma) {
            return [];
        }
        $matrizId = (int) ($turma['matriz_curricular_id'] ?? 0);
        if ($matrizId > 0) {
            $ok = $this->db->fetch("SHOW TABLES LIKE 'matrizes_curriculares_componentes'");
            if ($ok) {
                $rows = $this->db->fetchAll(
                    "SELECT mcc.materia_id, mat.nome AS componente_nome, mcc.ordem_boletim AS ordem,
                            mcc.aulas_semana
                     FROM matrizes_curriculares_componentes mcc
                     INNER JOIN materias mat ON mat.id = mcc.materia_id
                     WHERE mcc.matriz_id = :mid
                     ORDER BY mcc.ordem_boletim ASC, mat.nome ASC",
                    ['mid' => $matrizId]
                );
                if (!empty($rows)) {
                    return $rows;
                }
            }
        }
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT gh.materia_id, mat.nome AS componente_nome, 0 AS ordem
             FROM grade_horaria gh
             INNER JOIN materias mat ON mat.id = gh.materia_id
             WHERE gh.turma_id = :tid
             ORDER BY mat.nome ASC",
            ['tid' => $turmaId]
        );
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string,mixed>> */
    public function materiasAtivas(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, nome FROM materias ORDER BY nome ASC'
        );
        return is_array($rows) ? $rows : [];
    }

    public function findMatriculaAtiva(int $alunoId, int $turmaId): ?array
    {
        try {
            $row = $this->db->fetch(
                "SELECT id FROM matricula
                 WHERE aluno_id = :aid AND turma_id = :tid AND status = 'ativa'
                 ORDER BY id DESC LIMIT 1",
                ['aid' => $alunoId, 'tid' => $turmaId]
            );
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
