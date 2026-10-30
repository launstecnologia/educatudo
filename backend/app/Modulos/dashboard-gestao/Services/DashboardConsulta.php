<?php

namespace App\Modulos\DashboardGestao\Services;

use App\Core\Database;
/**
 * Consultas de leitura sobre tabelas oficiais. Sem regra de negócio nova.
 */
class DashboardConsulta
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function tabelaExiste(string $tabela): bool
    {
        static $cache = [];
        if (isset($cache[$tabela])) {
            return $cache[$tabela];
        }
        if (!preg_match('/^[a-z0-9_]+$/i', $tabela)) {
            $cache[$tabela] = false;
            return false;
        }
        try {
            $cache[$tabela] = $this->db->fetch("SHOW TABLES LIKE '{$tabela}'") !== false;
        } catch (\Throwable $e) {
            $cache[$tabela] = false;
        }
        return $cache[$tabela];
    }

    public function colunaExiste(string $tabela, string $coluna): bool
    {
        $key = $tabela . '.' . $coluna;
        static $cache = [];
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $row = $this->db->fetch(
                'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c',
                ['t' => $tabela, 'c' => $coluna]
            );
            $cache[$key] = ((int) ($row['c'] ?? 0)) > 0;
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }

    /**
     * @param list<int> $turmaIds
     * @return array{0:string,1:array<string,int>}
     */
    public function sqlInTurmas(array $turmaIds, string $coluna): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $turmaIds), static fn ($id) => $id > 0)));
        if ($ids === []) {
            return ['', []];
        }
        $params = [];
        $ph = [];
        foreach ($ids as $i => $id) {
            $k = 'dash_turma_' . substr(md5($coluna), 0, 4) . '_' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $id;
        }
        return [' AND ' . $coluna . ' IN (' . implode(',', $ph) . ')', $params];
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function cursos(): array
    {
        foreach (['curso', 'cursos'] as $tabela) {
            if (!$this->tabelaExiste($tabela)) {
                continue;
            }
            try {
                $rows = $this->db->fetchAll("SELECT id, nome FROM {$tabela} ORDER BY nome ASC") ?: [];
            } catch (\Throwable $e) {
                continue;
            }
            $out = [];
            foreach ($rows as $row) {
                $out[] = ['id' => (int) $row['id'], 'nome' => (string) $row['nome']];
            }
            return $out;
        }
        return [];
    }

    /**
     * @return list<array{id:int,nome:string,curso_id:int}>
     */
    public function series(?int $cursoId = null): array
    {
        if (!$this->tabelaExiste('serie')) {
            return [];
        }
        $sql = 'SELECT id, nome, curso_id FROM serie';
        $params = [];
        if ($cursoId !== null && $cursoId > 0 && $this->colunaExiste('serie', 'curso_id')) {
            $sql .= ' WHERE curso_id = :curso_id';
            $params['curso_id'] = $cursoId;
        }
        $sql .= ' ORDER BY nome ASC';
        try {
            $rows = $this->db->fetchAll($sql, $params) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'nome' => (string) $row['nome'],
                'curso_id' => (int) ($row['curso_id'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return list<array{id:int,nome:string,curso_id:int,serie_id:int,turno:?string}>
     */
    public function turmas(DashboardFiltro $filtro): array
    {
        $where = ['t.ativo = 1'];
        $params = [];
        if ($filtro->anoLetivoId > 0 && DashboardFiltro::colunaTurma($this->db, 'ano_letivo_id')) {
            $where[] = '(t.ano_letivo_id = :ano_letivo_id OR (t.ano_letivo_id IS NULL AND t.ano_letivo = :ano_civil))';
            $params['ano_letivo_id'] = $filtro->anoLetivoId;
            $params['ano_civil'] = $filtro->anoCivil;
        } elseif ($filtro->anoCivil > 0) {
            $where[] = 't.ano_letivo = :ano_civil';
            $params['ano_civil'] = $filtro->anoCivil;
        }
        if ($filtro->cursoId > 0) {
            if (DashboardFiltro::colunaTurma($this->db, 'curso_novo_id')) {
                $where[] = '(t.curso_novo_id = :curso_id OR t.curso_id = :curso_id2)';
                $params['curso_id'] = $filtro->cursoId;
                $params['curso_id2'] = $filtro->cursoId;
            } else {
                $where[] = 't.curso_id = :curso_id';
                $params['curso_id'] = $filtro->cursoId;
            }
        }
        if ($filtro->serieId > 0 && DashboardFiltro::colunaTurma($this->db, 'serie_id')) {
            $where[] = 't.serie_id = :serie_id';
            $params['serie_id'] = $filtro->serieId;
        }
        $sql = 'SELECT t.id, t.nome, t.curso_id, t.serie_id, t.turno
                FROM turmas t WHERE ' . implode(' AND ', $where) . ' ORDER BY t.nome ASC';
        try {
            $rows = $this->db->fetchAll($sql, $params) ?: [];
        } catch (\Throwable $e) {
            $sql = 'SELECT t.id, t.nome FROM turmas t WHERE t.ativo = 1 ORDER BY t.nome ASC';
            $rows = $this->db->fetchAll($sql) ?: [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'nome' => (string) $row['nome'],
                'curso_id' => (int) ($row['curso_id'] ?? 0),
                'serie_id' => (int) ($row['serie_id'] ?? 0),
                'turno' => $row['turno'] ?? null,
            ];
        }
        return $out;
    }

    public function contarAlunosMatriculados(DashboardFiltro $filtro): int
    {
        if ($filtro->semTurmas) {
            return 0;
        }
        if ($this->tabelaExiste('matricula')) {
            $where = ["m.status = 'ativa'", 'm.data_saida IS NULL', 'a.ativo = 1'];
            $params = [];
            if ($filtro->anoLetivoId > 0) {
                $where[] = 'm.ano_letivo_id = :ano_letivo_id';
                $params['ano_letivo_id'] = $filtro->anoLetivoId;
            }
            [$turmaSql, $turmaParams] = $this->sqlInTurmas($filtro->turmaIds, 'm.turma_id');
            $row = $this->db->fetch(
                'SELECT COUNT(DISTINCT m.aluno_id) AS n
                 FROM matricula m
                 INNER JOIN alunos a ON a.id = m.aluno_id
                 WHERE ' . implode(' AND ', $where) . $turmaSql,
                array_merge($params, $turmaParams)
            );
            return (int) ($row['n'] ?? 0);
        }

        $where = ['a.ativo = 1'];
        $params = [];
        if ($filtro->turmaIds !== []) {
            [$turmaSql, $turmaParams] = $this->sqlInTurmas($filtro->turmaIds, 'a.turma_id');
            $row = $this->db->fetch(
                'SELECT COUNT(*) AS n FROM alunos a WHERE ' . implode(' AND ', $where) . $turmaSql,
                array_merge($params, $turmaParams)
            );
            return (int) ($row['n'] ?? 0);
        }
        $row = $this->db->fetch('SELECT COUNT(*) AS n FROM alunos a WHERE a.ativo = 1');
        return (int) ($row['n'] ?? 0);
    }

    public function contarTurmasAtivas(DashboardFiltro $filtro): int
    {
        if ($filtro->semTurmas) {
            return 0;
        }
        if ($filtro->turmaIds !== []) {
            return count($filtro->turmaIds);
        }
        $row = $this->db->fetch('SELECT COUNT(*) AS n FROM turmas WHERE ativo = 1');
        return (int) ($row['n'] ?? 0);
    }

    public function contarTurmasCadastradas(DashboardFiltro $filtro): int
    {
        $where = ['1=1'];
        $params = [];
        if ($filtro->turmaId > 0) {
            $where[] = 't.id = :turma_id';
            $params['turma_id'] = $filtro->turmaId;
        }
        if ($filtro->anoLetivoId > 0 && DashboardFiltro::colunaTurma($this->db, 'ano_letivo_id')) {
            $where[] = '(t.ano_letivo_id = :ano_letivo_id OR (t.ano_letivo_id IS NULL AND t.ano_letivo = :ano_civil))';
            $params['ano_letivo_id'] = $filtro->anoLetivoId;
            $params['ano_civil'] = $filtro->anoCivil;
        } elseif ($filtro->anoCivil > 0 && DashboardFiltro::colunaTurma($this->db, 'ano_letivo')) {
            $where[] = 't.ano_letivo = :ano_civil';
            $params['ano_civil'] = $filtro->anoCivil;
        }
        if ($filtro->cursoId > 0) {
            if (DashboardFiltro::colunaTurma($this->db, 'curso_novo_id')) {
                $where[] = '(t.curso_novo_id = :curso_id OR t.curso_id = :curso_id2)';
                $params['curso_id'] = $filtro->cursoId;
                $params['curso_id2'] = $filtro->cursoId;
            } else {
                $where[] = 't.curso_id = :curso_id';
                $params['curso_id'] = $filtro->cursoId;
            }
        }
        if ($filtro->serieId > 0 && DashboardFiltro::colunaTurma($this->db, 'serie_id')) {
            $where[] = 't.serie_id = :serie_id';
            $params['serie_id'] = $filtro->serieId;
        }
        if ($filtro->turno !== '' && DashboardFiltro::colunaTurma($this->db, 'turno')) {
            $where[] = 't.turno = :turno';
            $params['turno'] = $filtro->turno;
        }
        try {
            $row = $this->db->fetch(
                'SELECT COUNT(*) AS n FROM turmas t WHERE ' . implode(' AND ', $where),
                $params
            );
            return (int) ($row['n'] ?? 0);
        } catch (\Throwable $e) {
            return $this->contarTurmasAtivas($filtro);
        }
    }

    public function contarProfessoresAtivos(DashboardFiltro $filtro): int
    {
        if ($filtro->semTurmas) {
            return 0;
        }
        if ($filtro->turmaIds !== [] && $this->tabelaExiste('grade_horaria')) {
            [$turmaSql, $turmaParams] = $this->sqlInTurmas($filtro->turmaIds, 'gh.turma_id');
            $row = $this->db->fetch(
                "SELECT COUNT(DISTINCT gh.professor_id) AS n
                 FROM grade_horaria gh
                 INNER JOIN professores p ON p.id = gh.professor_id AND p.ativo = 1
                 WHERE 1=1{$turmaSql}",
                $turmaParams
            );
            return (int) ($row['n'] ?? 0);
        }
        $row = $this->db->fetch('SELECT COUNT(*) AS n FROM professores WHERE ativo = 1');
        return (int) ($row['n'] ?? 0);
    }

    /**
     * Média das notas já calculadas na ficha do boletim (não recalcula a regra).
     */
    public function mediaNotasFicha(DashboardFiltro $filtro, ?int $periodo = null): ?float
    {
        $medias = $this->mediasPorAlunoFicha($filtro, $periodo);
        if ($medias === []) {
            return null;
        }
        $soma = 0.0;
        foreach ($medias as $media) {
            $soma += $media;
        }
        return round($soma / count($medias), 1);
    }

    /**
     * @return list<array{nome:string,media:float}>
     */
    public function disciplinasFicha(DashboardFiltro $filtro, ?int $periodo = null): array
    {
        if ($filtro->semTurmas || !$this->fichaPronta()) {
            return [];
        }
        [$turmaSql, $turmaParams] = $this->sqlInTurmas($filtro->turmaIds, 'f.turma_id');
        $params = $this->paramsFicha($filtro, $periodo);
        try {
            $rows = $this->db->fetchAll(
                "SELECT l.componente_nome AS nome, AVG(c.nota) AS media
                 FROM boletim_ficha_celulas c
                 INNER JOIN boletim_ficha_linhas l ON l.id = c.linha_id
                 INNER JOIN boletim_fichas f ON f.id = l.ficha_id
                 WHERE f.ano_letivo = :ano
                   AND c.periodo_numero = :periodo
                   AND c.origem = 'calculada'
                   AND c.nota IS NOT NULL{$turmaSql}
                 GROUP BY l.componente_nome
                 ORDER BY media DESC
                 LIMIT 8",
                array_merge($params, $turmaParams)
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $nome = trim((string) ($row['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $out[] = [
                'nome' => $nome,
                'media' => round((float) $row['media'], 1),
            ];
        }
        return $out;
    }

    /**
     * Média de cada aluno no período, a partir das notas calculadas da ficha.
     *
     * @return list<float>
     */
    public function mediasPorAlunoFicha(DashboardFiltro $filtro, ?int $periodo = null): array
    {
        if ($filtro->semTurmas || !$this->fichaPronta()) {
            return [];
        }
        [$turmaSql, $turmaParams] = $this->sqlInTurmas($filtro->turmaIds, 'f.turma_id');
        $params = $this->paramsFicha($filtro, $periodo);
        try {
            $rows = $this->db->fetchAll(
                "SELECT AVG(c.nota) AS media
                 FROM boletim_ficha_celulas c
                 INNER JOIN boletim_ficha_linhas l ON l.id = c.linha_id
                 INNER JOIN boletim_fichas f ON f.id = l.ficha_id
                 WHERE f.ano_letivo = :ano
                   AND c.periodo_numero = :periodo
                   AND c.origem = 'calculada'
                   AND c.nota IS NOT NULL{$turmaSql}
                 GROUP BY f.aluno_id",
                array_merge($params, $turmaParams)
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if ($row['media'] === null) {
                continue;
            }
            $out[] = round((float) $row['media'], 2);
        }
        return $out;
    }

    /**
     * Alunos com média abaixo da nota mínima gravada na regra do boletim.
     *
     * @return list<array{aluno:string,turma:string,indicador:string,valor:string,situacao:string,badge:string}>
     */
    public function atencaoNotasFicha(DashboardFiltro $filtro, int $limite): array
    {
        $minima = $this->notaMinimaRegra($filtro);
        if ($minima === null || $limite <= 0 || $filtro->semTurmas || !$this->fichaPronta()) {
            return [];
        }
        [$turmaSql, $turmaParams] = $this->sqlInTurmas($filtro->turmaIds, 'f.turma_id');
        $limite = max(1, min(12, $limite));
        try {
            $rows = $this->db->fetchAll(
                "SELECT a.nome AS aluno, t.nome AS turma, AVG(c.nota) AS media
                 FROM boletim_ficha_celulas c
                 INNER JOIN boletim_ficha_linhas l ON l.id = c.linha_id
                 INNER JOIN boletim_fichas f ON f.id = l.ficha_id
                 INNER JOIN alunos a ON a.id = f.aluno_id
                 INNER JOIN turmas t ON t.id = f.turma_id
                 WHERE f.ano_letivo = :ano
                   AND c.periodo_numero = :periodo
                   AND c.origem = 'calculada'
                   AND c.nota IS NOT NULL{$turmaSql}
                 GROUP BY f.aluno_id, a.nome, t.nome
                 HAVING AVG(c.nota) < :minima
                 ORDER BY media ASC, a.nome ASC
                 LIMIT {$limite}",
                array_merge($this->paramsFicha($filtro, null), $turmaParams, ['minima' => $minima])
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'aluno' => (string) $row['aluno'],
                'turma' => (string) $row['turma'],
                'indicador' => 'Notas',
                'valor' => number_format((float) $row['media'], 1, ',', '.'),
                'situacao' => 'Abaixo da média',
                'badge' => 'atencao',
            ];
        }
        return $out;
    }

    public function notaMinimaRegra(DashboardFiltro $filtro): ?float
    {
        if (!$this->tabelaExiste('boletim_regras') || !$this->colunaExiste('boletim_regras', 'nota_minima_aprovacao')) {
            return null;
        }
        try {
            $row = $this->db->fetch(
                "SELECT nota_minima_aprovacao AS minima
                 FROM boletim_regras
                 WHERE ativo = 1
                   AND ano_letivo = :ano
                   AND nota_minima_aprovacao IS NOT NULL
                   AND (bimestre = :periodo OR bimestre IS NULL)
                 ORDER BY (bimestre IS NULL) ASC, id DESC
                 LIMIT 1",
                ['ano' => $filtro->anoCivil, 'periodo' => $filtro->bimestre]
            );
        } catch (\Throwable $e) {
            return null;
        }
        if (!$row || $row['minima'] === null) {
            return null;
        }
        return (float) $row['minima'];
    }

    private function fichaPronta(): bool
    {
        return $this->tabelaExiste('boletim_ficha_celulas')
            && $this->tabelaExiste('boletim_ficha_linhas')
            && $this->tabelaExiste('boletim_fichas');
    }

    /**
     * @return array{ano:int,periodo:int}
     */
    private function paramsFicha(DashboardFiltro $filtro, ?int $periodo): array
    {
        $numero = $periodo ?? $filtro->bimestre;
        return [
            'ano' => $filtro->anoCivil,
            'periodo' => max(0, $numero),
        ];
    }
}

if (!class_exists('DashboardConsulta', false) && class_exists(__NAMESPACE__ . '\\DashboardConsulta', false)) {
    class_alias(__NAMESPACE__ . '\\DashboardConsulta', 'DashboardConsulta');
}
