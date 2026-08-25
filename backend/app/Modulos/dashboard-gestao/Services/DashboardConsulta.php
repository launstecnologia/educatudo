<?php

namespace App\Modulos\DashboardGestao\Services;

use Database;

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
}
