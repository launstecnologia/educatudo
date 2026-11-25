<?php

namespace App\Modulos\Boletim26Colag\Models;

use App\Core\Database;

/**
 * Leitura das provas, jornadas e faltas do tenant. Não grava nada.
 */
class Boletim26ColagLeitura
{
    private Database $db;

    /** @var array<string, bool> */
    private array $tabelas = [];

    /** @var array<string, bool> */
    private array $colunas = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelaExiste(string $tabela): bool
    {
        if (isset($this->tabelas[$tabela])) {
            return $this->tabelas[$tabela];
        }
        if (!preg_match('/^[a-z0-9_]+$/', $tabela)) {
            return $this->tabelas[$tabela] = false;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE '{$tabela}'");
            return $this->tabelas[$tabela] = ($row !== false && $row !== null);
        } catch (\Throwable $e) {
            return $this->tabelas[$tabela] = false;
        }
    }

    public function colunaExiste(string $tabela, string $coluna): bool
    {
        $chave = $tabela . '.' . $coluna;
        if (isset($this->colunas[$chave])) {
            return $this->colunas[$chave];
        }
        if (!$this->tabelaExiste($tabela) || !preg_match('/^[a-z0-9_]+$/', $coluna)) {
            return $this->colunas[$chave] = false;
        }
        try {
            $rows = $this->db->fetchAll('SHOW COLUMNS FROM `' . $tabela . '` LIKE \'' . $coluna . '\'');
            return $this->colunas[$chave] = !empty($rows);
        } catch (\Throwable $e) {
            return $this->colunas[$chave] = false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarTurmas(): array
    {
        if (!$this->tabelaExiste('turmas')) {
            return [];
        }
        $temSerieId = $this->colunaExiste('turmas', 'serie_id') && $this->tabelaExiste('serie');
        $joinSerie = $temSerieId ? 'LEFT JOIN serie s ON s.id = t.serie_id' : '';
        if ($temSerieId) {
            $selSerie = 's.nome AS serie_nome';
        } elseif ($this->colunaExiste('turmas', 'serie')) {
            $selSerie = 't.serie AS serie_nome';
        } else {
            $selSerie = 'NULL AS serie_nome';
        }
        $joinCurso = '';
        $selCurso = 'NULL AS curso_nome';
        if ($temSerieId && $this->tabelaExiste('curso') && $this->colunaExiste('serie', 'curso_id')) {
            $joinCurso = 'LEFT JOIN curso c ON c.id = s.curso_id';
            $selCurso = 'c.nome AS curso_nome';
        }
        $selTipo = $this->colunaExiste('turmas', 'tipo_ensino') ? 't.tipo_ensino' : 'NULL AS tipo_ensino';
        $ativo = $this->colunaExiste('turmas', 'ativo') ? ' WHERE (t.ativo = 1 OR t.ativo IS NULL)' : '';
        try {
            return $this->db->fetchAll(
                "SELECT t.id, t.nome, {$selSerie}, {$selCurso}, {$selTipo}
                   FROM turmas t
                   {$joinSerie}
                   {$joinCurso}
                   {$ativo}
                  ORDER BY t.nome ASC"
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<int> $blocoIds
     * @return list<array<string,mixed>>
     */
    public function provasDoBimestre(int $turmaId, int $bimestre, int $ano): array
    {
        if ($turmaId <= 0 || $bimestre < 1 || $bimestre > 4 || !$this->tabelaExiste('provas_blocos')) {
            return [];
        }
        $ors = [];
        $params = [
            'bim' => $bimestre,
            'ano' => $ano,
            'ano_data' => $ano,
            'turma' => $turmaId,
        ];
        if ($this->tabelaExiste('provas_blocos_turmas')) {
            $ors[] = 'EXISTS (SELECT 1 FROM provas_blocos_turmas pbt WHERE pbt.bloco_id = pb.id AND pbt.turma_id = :turma)';
        }
        if ($this->tabelaExiste('provas_blocos_professores') && $this->tabelaExiste('provas_blocos_professores_turmas')) {
            $ors[] = 'EXISTS (
                SELECT 1 FROM provas_blocos_professores pbp
                INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
                WHERE pbp.bloco_id = pb.id AND pbpt.turma_id = :turma_prof
            )';
            $params['turma_prof'] = $turmaId;
        }
        if ($this->colunaExiste('provas_blocos', 'turma_id')) {
            $ors[] = 'pb.turma_id = :turma_col';
            $params['turma_col'] = $turmaId;
        }
        if ($ors === []) {
            return [];
        }
        $selectSemana = $this->colunaExiste('provas_blocos', 'semana') ? 'pb.semana' : 'NULL AS semana';
        $selectAno = $this->colunaExiste('provas_blocos', 'ano_letivo') ? 'pb.ano_letivo' : 'NULL AS ano_letivo';
        $selectBim = $this->colunaExiste('provas_blocos', 'bimestre') ? 'pb.bimestre' : 'NULL AS bimestre';
        $joinTipo = '';
        $selectTipo = 'NULL AS tipo_nome';
        if ($this->tabelaExiste('provas_tipos_avaliacao') && $this->colunaExiste('provas_blocos', 'tipo_avaliacao_id')) {
            $joinTipo = 'LEFT JOIN provas_tipos_avaliacao pta ON pta.id = pb.tipo_avaliacao_id AND pta.deleted_at IS NULL';
            $selectTipo = 'pta.nome AS tipo_nome';
        }
        $sql = "SELECT pb.id, pb.titulo, pb.data_prova, {$selectSemana}, {$selectAno}, {$selectBim}, {$selectTipo}
                  FROM provas_blocos pb
                  {$joinTipo}
                 WHERE pb.deleted_at IS NULL
                   AND {$selectBim} = :bim
                   AND (
                        {$selectAno} IS NULL OR {$selectAno} = 0 OR {$selectAno} = :ano
                        OR YEAR(pb.data_prova) = :ano_data
                   )
                   AND (" . implode(' OR ', $ors) . ')
                 ORDER BY pb.data_prova ASC, pb.id ASC';
        if (!$this->colunaExiste('provas_blocos', 'bimestre')) {
            return [];
        }
        $sql = str_replace($selectBim . ' = :bim', 'pb.bimestre = :bim', $sql);
        if (!$this->colunaExiste('provas_blocos', 'ano_letivo')) {
            $sql = "SELECT pb.id, pb.titulo, pb.data_prova, {$selectSemana}, NULL AS ano_letivo, pb.bimestre, {$selectTipo}
                      FROM provas_blocos pb
                      {$joinTipo}
                     WHERE pb.deleted_at IS NULL
                       AND pb.bimestre = :bim
                       AND (pb.data_prova IS NULL OR YEAR(pb.data_prova) = :ano_data)
                       AND (" . implode(' OR ', $ors) . ')
                     ORDER BY pb.data_prova ASC, pb.id ASC';
            unset($params['ano']);
        }
        try {
            return $this->db->fetchAll($sql, $params) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<int> $blocoIds
     * @return array<int, list<int>>
     */
    public function materiasPorBloco(array $blocoIds): array
    {
        $blocoIds = $this->ids($blocoIds);
        if ($blocoIds === [] || !$this->tabelaExiste('provas_blocos_professores')) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($blocoIds), '?'));
        try {
            $rows = $this->db->fetchAll(
                "SELECT bloco_id, materia_id FROM provas_blocos_professores WHERE bloco_id IN ($ph) AND materia_id > 0",
                $blocoIds
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $bid = (int) ($row['bloco_id'] ?? 0);
            $mid = (int) ($row['materia_id'] ?? 0);
            if ($bid > 0 && $mid > 0) {
                $out[$bid][$mid] = $mid;
            }
        }
        foreach ($out as $bid => $ids) {
            $out[$bid] = array_values($ids);
        }

        return $out;
    }

    /**
     * @param list<int> $blocoIds
     * @return list<array{bloco_id:int,materia_id:int,nota:float}>
     */
    public function notasLancadas(int $alunoId, array $blocoIds): array
    {
        $blocoIds = $this->ids($blocoIds);
        if ($alunoId <= 0 || $blocoIds === [] || !$this->tabelaExiste('provas_blocos_notas_lancadas')) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($blocoIds), '?'));
        try {
            $rows = $this->db->fetchAll(
                "SELECT bloco_id, materia_id, nota
                   FROM provas_blocos_notas_lancadas
                  WHERE aluno_id = ? AND nota IS NOT NULL AND bloco_id IN ($ph)",
                array_merge([$alunoId], $blocoIds)
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_numeric($row['nota'] ?? null)) {
                continue;
            }
            $out[] = [
                'bloco_id' => (int) ($row['bloco_id'] ?? 0),
                'materia_id' => (int) ($row['materia_id'] ?? 0),
                'nota' => (float) $row['nota'],
            ];
        }

        return $out;
    }

    /**
     * Acertos e questões das provas online ligadas aos blocos.
     *
     * @param list<int> $blocoIds
     * @return list<array{bloco_id:int,materia_id:int,n:int,q:int,nota:?float}>
     */
    public function acertosPorBloco(int $alunoId, array $blocoIds): array
    {
        $blocoIds = $this->ids($blocoIds);
        if ($alunoId <= 0 || $blocoIds === [] || !$this->tabelaExiste('provas_blocos_vinculo') || !$this->tabelaExiste('provas')) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($blocoIds), '?'));
        $invalida = $this->colunaExiste('provas_questoes', 'invalidada')
            ? ' AND COALESCE(pq.invalidada, 0) = 0'
            : '';
        $invalidaJoin = $this->colunaExiste('provas_questoes', 'invalidada')
            ? ' AND COALESCE(pq.invalidada, 0) = 0'
            : '';
        try {
            $rows = $this->db->fetchAll(
                "SELECT pbv.bloco_id, p.materia_id, p.id AS prova_id, pr.nota,
                        (SELECT COUNT(*) FROM provas_questoes pq WHERE pq.prova_id = p.id{$invalida}) AS questoes,
                        (SELECT COUNT(DISTINCT rr.questao_id)
                           FROM provas_respostas rr
                           INNER JOIN provas_questoes pq ON pq.id = rr.questao_id{$invalidaJoin}
                          WHERE rr.prova_id = p.id AND rr.aluno_id = pr.aluno_id AND rr.correta = 1) AS acertos
                   FROM provas_blocos_vinculo pbv
                   INNER JOIN provas p ON p.id = pbv.prova_id AND p.deleted_at IS NULL
                   INNER JOIN provas_realizacoes pr ON pr.prova_id = p.id AND pr.aluno_id = ? AND pr.status = 'finalizado'
                  WHERE pbv.bloco_id IN ($ph)",
                array_merge([$alunoId], $blocoIds)
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'bloco_id' => (int) ($row['bloco_id'] ?? 0),
                'materia_id' => (int) ($row['materia_id'] ?? 0),
                'n' => (int) ($row['acertos'] ?? 0),
                'q' => (int) ($row['questoes'] ?? 0),
                'nota' => is_numeric($row['nota'] ?? null) ? (float) $row['nota'] : null,
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));
    }
}
