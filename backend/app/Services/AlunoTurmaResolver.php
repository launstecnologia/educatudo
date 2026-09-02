<?php

namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/ListaChamadaService.php';
require_once __DIR__ . '/../Helpers/StudentFormHelper.php';

class AlunoTurmaResolver
{
    private $db;
    private $listaChamada;
    /** @var bool|null */
    private $supportsMatriculaCache = null;
    /** @var array<int, int|null> */
    private $turmaPrincipalCache = [];

    public function __construct()
    {
        $this->db = \Database::getInstance();
        $this->listaChamada = new ListaChamadaService();
    }

    public function supportsMatricula(): bool
    {
        if ($this->supportsMatriculaCache !== null) {
            return $this->supportsMatriculaCache;
        }
        try {
            $this->supportsMatriculaCache = $this->db->fetch("SHOW TABLES LIKE 'matricula'") !== false;
        } catch (\Exception $e) {
            $this->supportsMatriculaCache = false;
        }
        return $this->supportsMatriculaCache;
    }

    public function supportsListaChamada(): bool
    {
        return $this->listaChamada->tabelaExiste();
    }

    public function supportsStatusGovernanca(): bool
    {
        try {
            return $this->db->fetch("SHOW COLUMNS FROM alunos LIKE 'status'") !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function resolverAnoLetivoIdParaTurma(int $turmaId): int
    {
        return $this->listaChamada->resolverAnoLetivoIdParaTurma($turmaId);
    }

    public function getTurmaPrincipal(int $alunoId): ?int
    {
        if ($alunoId <= 0) {
            return null;
        }
        if (array_key_exists($alunoId, $this->turmaPrincipalCache)) {
            return $this->turmaPrincipalCache[$alunoId];
        }
        $row = $this->db->fetch('SELECT turma_id FROM alunos WHERE id = :id', ['id' => $alunoId]);
        $tid = $row ? (int) ($row['turma_id'] ?? 0) : 0;
        $this->turmaPrincipalCache[$alunoId] = $tid > 0 ? $tid : null;

        return $this->turmaPrincipalCache[$alunoId];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarMatriculasAtivas(int $alunoId, ?int $anoLetivoId = null): array
    {
        if ($alunoId <= 0 || !$this->supportsMatricula()) {
            return [];
        }

        $sql = "SELECT m.*, t.nome AS turma_nome, al.ano AS ano_letivo_ano,
                       COALESCE(c.tipo, 'regular') AS curso_tipo
                FROM matricula m
                INNER JOIN turmas t ON t.id = m.turma_id
                LEFT JOIN ano_letivo al ON al.id = m.ano_letivo_id
                LEFT JOIN curso c ON c.id = t.curso_novo_id
                WHERE m.aluno_id = :aluno_id
                  AND m.status = 'ativa'
                  AND m.data_saida IS NULL";
        $params = ['aluno_id' => $alunoId];

        if ($anoLetivoId !== null && $anoLetivoId > 0) {
            $sql .= ' AND m.ano_letivo_id = :ano_letivo_id';
            $params['ano_letivo_id'] = $anoLetivoId;
        }

        $sql .= ' ORDER BY m.data_entrada DESC, m.id DESC';

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * Matrículas ativas em turmas diferentes da turma principal do cadastro.
     *
     * @return list<array<string, mixed>>
     */
    public function listarMatriculasParalelas(int $alunoId): array
    {
        if ($alunoId <= 0 || !$this->supportsMatricula()) {
            return [];
        }

        $principalId = $this->getTurmaPrincipal($alunoId);

        return $this->db->fetchAll(
            "SELECT m.*, t.nome AS turma_nome, al.ano AS ano_letivo_ano,
                    COALESCE(c.tipo, 'regular') AS curso_tipo
             FROM matricula m
             INNER JOIN turmas t ON t.id = m.turma_id
             LEFT JOIN ano_letivo al ON al.id = m.ano_letivo_id
             LEFT JOIN curso c ON c.id = t.curso_novo_id
             WHERE m.aluno_id = :aluno_id
               AND m.status = 'ativa'
               AND m.data_saida IS NULL
               AND m.turma_id != :turma_principal
             ORDER BY m.data_entrada DESC, m.id DESC",
            [
                'aluno_id' => $alunoId,
                'turma_principal' => $principalId ?? 0,
            ]
        ) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarAlunosPorTurma(int $turmaId): array
    {
        if ($turmaId <= 0) {
            return [];
        }

        if ($this->supportsMatricula()) {
            $orderBy = $this->sqlOrdemNomeAluno();
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT a.*,
                        CASE WHEN a.turma_id = :tid_principal THEN 'principal' ELSE 'matriculado' END AS vinculo_tipo
                 FROM alunos a
                 LEFT JOIN matricula m ON m.aluno_id = a.id
                    AND m.turma_id = :tid_mat
                    AND m.status = 'ativa'
                    AND m.data_saida IS NULL
                 WHERE a.turma_id = :tid_where OR m.id IS NOT NULL
                 ORDER BY {$orderBy}",
                [
                    'tid_principal' => $turmaId,
                    'tid_mat' => $turmaId,
                    'tid_where' => $turmaId,
                ]
            ) ?: [];
            return $this->enriquecerAlunosParaListagem($rows);
        }

        $orderBy = $this->sqlOrdemNomeAluno();
        $rows = $this->db->fetchAll(
            "SELECT a.*, 'principal' AS vinculo_tipo
             FROM alunos a
             WHERE a.turma_id = :id
             ORDER BY {$orderBy}",
            ['id' => $turmaId]
        ) ?: [];
        return $this->enriquecerAlunosParaListagem($rows);
    }

    public function contarAlunosPorTurma(int $turmaId): int
    {
        if ($turmaId <= 0) {
            return 0;
        }

        if ($this->supportsMatricula()) {
            $result = $this->db->fetch(
                "SELECT COUNT(DISTINCT aluno_id) AS total FROM (
                    SELECT a.id AS aluno_id FROM alunos a WHERE a.turma_id = :id1
                    UNION
                    SELECT m.aluno_id FROM matricula m
                    WHERE m.turma_id = :id2 AND m.status = 'ativa' AND m.data_saida IS NULL
                ) x",
                ['id1' => $turmaId, 'id2' => $turmaId]
            );

            return (int) ($result['total'] ?? 0);
        }

        $result = $this->db->fetch(
            "SELECT COUNT(*) as total FROM (
                SELECT a.id FROM alunos a WHERE a.turma_id = :id1
                UNION
                SELECT h.aluno_id FROM alunos_turmas_historico h WHERE h.turma_id = :id2
            ) x",
            ['id1' => $turmaId, 'id2' => $turmaId]
        );

        return (int) ($result['total'] ?? 0);
    }

    /**
     * @param list<int> $turmaIds
     * @return list<array<string, mixed>>
     */
    public function listarAlunosPorTurmasIds(array $turmaIds): array
    {
        $turmaIds = array_values(array_filter(array_map('intval', $turmaIds), static fn ($id) => $id > 0));
        if ($turmaIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));

        if ($this->supportsMatricula()) {
            $params = array_merge($turmaIds, $turmaIds, $turmaIds);
            $sql = "SELECT x.id, x.nome, x.ra, x.turma_id, t.nome AS turma_nome
                    FROM (
                        SELECT DISTINCT a.id, a.nome, a.ra,
                            COALESCE(
                                (SELECT MIN(m.turma_id) FROM matricula m
                                 WHERE m.aluno_id = a.id AND m.status = 'ativa' AND m.turma_id IN ($placeholders)),
                                NULLIF(a.turma_id, 0)
                            ) AS turma_id
                        FROM alunos a
                        WHERE a.ativo = 1
                        AND (
                            a.turma_id IN ($placeholders)
                            OR EXISTS (
                                SELECT 1 FROM matricula m2
                                WHERE m2.aluno_id = a.id AND m2.turma_id IN ($placeholders) AND m2.status = 'ativa'
                            )
                        )
                    ) x
                    INNER JOIN turmas t ON t.id = x.turma_id
                    ORDER BY t.nome, x.nome";

            return $this->db->fetchAll($sql, $params) ?: [];
        }

        return $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome
             FROM alunos a
             JOIN turmas t ON t.id = a.turma_id
             WHERE a.turma_id IN ($placeholders) AND a.ativo = 1
             ORDER BY t.nome, a.nome",
            $turmaIds
        ) ?: [];
    }

    public function detectarDivergenciaCadastro(int $alunoId): bool
    {
        if ($alunoId <= 0 || !$this->supportsMatricula()) {
            return false;
        }

        $principalId = $this->getTurmaPrincipal($alunoId);
        if ($principalId === null) {
            return false;
        }

        $row = $this->db->fetch(
            "SELECT m.id
             FROM matricula m
             INNER JOIN turmas t ON t.id = m.turma_id
             LEFT JOIN curso c ON c.id = t.curso_novo_id
             WHERE m.aluno_id = :aid
               AND m.status = 'ativa'
               AND m.data_saida IS NULL
               AND m.turma_id != :tid
               AND COALESCE(c.tipo, 'regular') != 'extra'
             LIMIT 1",
            ['aid' => $alunoId, 'tid' => $principalId]
        );

        return $row !== false;
    }

    public function isCursoExtraTurma(int $turmaId): bool
    {
        if ($turmaId <= 0) {
            return false;
        }

        try {
            $hasCursoNovo = $this->db->fetch("SHOW COLUMNS FROM turmas LIKE 'curso_novo_id'");
            if ($hasCursoNovo === false) {
                return false;
            }
            $row = $this->db->fetch(
                "SELECT c.tipo FROM turmas t
                 INNER JOIN curso c ON c.id = t.curso_novo_id
                 WHERE t.id = :id",
                ['id' => $turmaId]
            );

            return ($row['tipo'] ?? '') === 'extra';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Rótulo de vínculo para exibição na ficha do aluno.
     */
    public function rotuloVinculoMatricula(int $alunoId, int $turmaMatriculaId, string $cursoTipo = 'regular'): string
    {
        $principalId = $this->getTurmaPrincipal($alunoId);
        if ($principalId !== null && $turmaMatriculaId === $principalId) {
            return 'Principal';
        }
        if ($cursoTipo === 'extra') {
            return 'Extra';
        }

        return 'Paralela';
    }

    private function sqlOrdemNomeAluno(): string
    {
        return \StudentFormHelper::sqlNomeExibicao('a', $this->db) . ' ASC';
    }

    /**
     * @param list<array<string, mixed>> $alunos
     * @return list<array<string, mixed>>
     */
    private function enriquecerAlunosParaListagem(array $alunos): array
    {
        $out = [];
        foreach ($alunos as $aluno) {
            if (!is_array($aluno)) {
                continue;
            }
            $out[] = \StudentFormHelper::aplicarNomeExibicao($aluno, true);
        }

        return $out;
    }
}
