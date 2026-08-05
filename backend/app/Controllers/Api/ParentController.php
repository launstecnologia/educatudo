<?php
/**
 * EducaTudo - API REST exclusiva para Pais (Parent)
 * Retorna JSON; acesso apenas aos filhos vinculados ao parent_id do JWT
 */

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../Policies/ParentStudentAccessPolicy.php';

class ParentController extends BaseController
{
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
    }

    private function parentId(): int
    {
        $id = ApiAuthMiddleware::$parentId ?? $_SERVER['API_PARENT_ID'] ?? 0;
        return (int) $id;
    }

    private function jsonSuccess($data)
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->json(['success' => true, 'data' => $data]);
    }

    private function jsonError(string $message, int $code = 400)
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->json(['success' => false, 'message' => $message], $code);
    }

    /**
     * Verifica se o child_id pertence ao parent_id logado.
     * Regra crítica de segurança: nunca confiar apenas no ID da URL.
     */
    private function validateChildOwnership(int $childId, int $parentId): bool
    {
        return (new ParentStudentAccessPolicy($this->db))->canAccess($parentId, $childId);
    }

    /**
     * GET /api/parents/children
     */
    public function children()
    {
        $parentId = $this->parentId();
        if ($parentId <= 0) {
            $this->jsonError('Não autorizado', 401);
            return;
        }

        $filhos = $this->db->fetchAll(
            "SELECT a.id, a.nome, t.nome as turma_nome, t.serie
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.ativo = 1
               AND (
                    a.responsavel_id = :parent_id
                    OR EXISTS (
                        SELECT 1
                        FROM alunos_responsaveis ar
                        WHERE ar.aluno_id = a.id
                          AND ar.responsavel_id = :parent_id
                          AND ar.ativo = 1
                    )
               )
             ORDER BY a.nome ASC",
            ['parent_id' => $parentId]
        );

        $mediaGeral = $this->db->fetch(
            "SELECT AVG(nota) as media FROM (
                SELECT pr.nota FROM provas_realizacoes pr
                INNER JOIN alunos a ON pr.aluno_id = a.id
                WHERE a.ativo = 1
                  AND (
                      a.responsavel_id = :parent_id
                      OR EXISTS (
                          SELECT 1
                          FROM alunos_responsaveis ar
                          WHERE ar.aluno_id = a.id
                            AND ar.responsavel_id = :parent_id
                            AND ar.ativo = 1
                      )
                  )
                  AND pr.status = 'finalizado'
                  AND pr.nota IS NOT NULL
                UNION ALL
                SELECT r.nota_final as nota FROM redacoes r
                INNER JOIN alunos a ON r.aluno_id = a.id
                WHERE a.ativo = 1
                  AND (
                      a.responsavel_id = :parent_id
                      OR EXISTS (
                          SELECT 1
                          FROM alunos_responsaveis ar
                          WHERE ar.aluno_id = a.id
                            AND ar.responsavel_id = :parent_id
                            AND ar.ativo = 1
                      )
                  )
                  AND r.nota_final IS NOT NULL
            ) u",
            ['parent_id' => $parentId]
        );
        $avgGlobal = $mediaGeral && isset($mediaGeral['media']) ? round((float) $mediaGeral['media'], 1) : null;

        $list = [];
        foreach ($filhos as $f) {
            $mediaAluno = $this->db->fetch(
                "SELECT AVG(nota) as media FROM (
                    SELECT pr.nota FROM provas_realizacoes pr WHERE pr.aluno_id = :aluno_id AND pr.status = 'finalizado' AND pr.nota IS NOT NULL
                    UNION ALL
                    SELECT r.nota_final FROM redacoes r WHERE r.aluno_id = :aluno_id2 AND r.nota_final IS NOT NULL
                ) u",
                ['aluno_id' => $f['id'], 'aluno_id2' => $f['id']]
            );
            $list[] = [
                'id' => (int) $f['id'],
                'name' => $f['nome'],
                'class' => $f['turma_nome'] ?? $f['serie'] ?? '',
                'average_grade' => $mediaAluno && $mediaAluno['media'] !== null ? round((float) $mediaAluno['media'], 1) : ($avgGlobal ?? 0),
            ];
        }

        $this->jsonSuccess($list);
    }

    /**
     * GET /api/parents/child/{id}/dashboard
     */
    public function dashboard($id)
    {
        $childId = (int) $id;
        $parentId = $this->parentId();
        if ($parentId <= 0) {
            $this->jsonError('Não autorizado', 401);
            return;
        }
        if (!$this->validateChildOwnership($childId, $parentId)) {
            $this->jsonError('Acesso negado a este aluno', 403);
            return;
        }

        $totalExams = $this->db->fetch(
            "SELECT COUNT(*) as c FROM provas_realizacoes WHERE aluno_id = :aluno_id AND status = 'finalizado'",
            ['aluno_id' => $childId]
        );
        $totalExercises = $this->db->fetch(
            "SELECT COUNT(*) as c FROM jornadas_progresso_alunos WHERE aluno_id = :aluno_id AND atividade_tipo IN ('exercicio', 'exercicio_modulo')",
            ['aluno_id' => $childId]
        );
        $activeJourneys = $this->db->fetch(
            "SELECT COUNT(DISTINCT j.id) as c FROM jornadas j
             INNER JOIN jornadas_progresso_alunos jpa ON jpa.jornada_id = j.id AND jpa.aluno_id = :aluno_id
             WHERE (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status IS NULL OR j.status = '')
             AND (j.ativo = 1 OR j.ativo IS NULL)",
            ['aluno_id' => $childId]
        );

        $avgRow = $this->db->fetch(
            "SELECT AVG(nota) as media FROM (
                SELECT pr.nota FROM provas_realizacoes pr WHERE pr.aluno_id = :aluno_id AND pr.status = 'finalizado' AND pr.nota IS NOT NULL
                UNION ALL
                SELECT r.nota_final FROM redacoes r WHERE r.aluno_id = :aluno_id2 AND r.nota_final IS NOT NULL
            ) u",
            ['aluno_id' => $childId, 'aluno_id2' => $childId]
        );
        $averageGrade = $avgRow && $avgRow['media'] !== null ? round((float) $avgRow['media'], 1) : null;

        $lastActivity = $this->db->fetch(
            "SELECT MAX(dt) as last_dt FROM (
                SELECT finalizado_em as dt FROM provas_realizacoes WHERE aluno_id = :aluno_id AND finalizado_em IS NOT NULL
                UNION ALL
                SELECT data_conclusao FROM jornadas_progresso_alunos WHERE aluno_id = :aluno_id2 AND data_conclusao IS NOT NULL
                UNION ALL
                SELECT created_at FROM redacoes WHERE aluno_id = :aluno_id3
            ) u",
            ['aluno_id' => $childId, 'aluno_id2' => $childId, 'aluno_id3' => $childId]
        );
        $lastDate = $lastActivity && !empty($lastActivity['last_dt']) ? date('Y-m-d', strtotime($lastActivity['last_dt'])) : null;

        $this->jsonSuccess([
            'average_grade' => $averageGrade,
            'total_exams' => (int) ($totalExams['c'] ?? 0),
            'total_exercises' => (int) ($totalExercises['c'] ?? 0),
            'active_journeys' => (int) ($activeJourneys['c'] ?? 0),
            'last_activity' => $lastDate,
        ]);
    }

    /**
     * GET /api/parents/child/{id}/exams
     */
    public function exams($id)
    {
        $childId = (int) $id;
        $parentId = $this->parentId();
        if ($parentId <= 0) {
            $this->jsonError('Não autorizado', 401);
            return;
        }
        if (!$this->validateChildOwnership($childId, $parentId)) {
            $this->jsonError('Acesso negado a este aluno', 403);
            return;
        }

        $provas = $this->db->fetchAll(
            "SELECT p.id, p.titulo, pr.nota, pr.finalizado_em as data, pr.status
             FROM provas_realizacoes pr
             INNER JOIN provas p ON p.id = pr.prova_id AND p.deleted_at IS NULL
             WHERE pr.aluno_id = :aluno_id
             ORDER BY pr.finalizado_em DESC",
            ['aluno_id' => $childId]
        );

        $list = [];
        foreach ($provas as $p) {
            $list[] = [
                'id' => (int) $p['id'],
                'title' => $p['titulo'],
                'grade' => $p['nota'] !== null ? (float) $p['nota'] : null,
                'date' => $p['data'] ? date('Y-m-d', strtotime($p['data'])) : null,
                'status' => $p['status'] ?? 'finalizado',
            ];
        }
        $this->jsonSuccess($list);
    }

    /**
     * GET /api/parents/child/{id}/exercises
     */
    public function exercises($id)
    {
        $childId = (int) $id;
        $parentId = $this->parentId();
        if ($parentId <= 0) {
            $this->jsonError('Não autorizado', 401);
            return;
        }
        if (!$this->validateChildOwnership($childId, $parentId)) {
            $this->jsonError('Acesso negado a este aluno', 403);
            return;
        }

        $totais = $this->db->fetch(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN pontuacao > 0 THEN 1 ELSE 0 END) as acertos
             FROM jornadas_progresso_alunos
             WHERE aluno_id = :aluno_id AND atividade_tipo IN ('exercicio', 'exercicio_modulo')",
            ['aluno_id' => $childId]
        );
        $total = (int) ($totais['total'] ?? 0);
        $acertos = (int) ($totais['acertos'] ?? 0);
        $taxa = $total > 0 ? round(($acertos / $total) * 100, 1) : 0;

        $ultimos = $this->db->fetchAll(
            "SELECT jpa.id, jpa.jornada_id, jpa.pontuacao, jpa.data_conclusao, j.titulo as jornada_titulo
             FROM jornadas_progresso_alunos jpa
             INNER JOIN jornadas j ON j.id = jpa.jornada_id
             WHERE jpa.aluno_id = :aluno_id AND jpa.atividade_tipo IN ('exercicio', 'exercicio_modulo')
             ORDER BY jpa.data_conclusao DESC, jpa.created_at DESC
             LIMIT 10",
            ['aluno_id' => $childId]
        );

        $ultimosList = [];
        foreach ($ultimos as $u) {
            $ultimosList[] = [
                'id' => (int) $u['id'],
                'jornada_id' => (int) $u['jornada_id'],
                'jornada_titulo' => $u['jornada_titulo'],
                'pontuacao' => $u['pontuacao'] !== null ? (float) $u['pontuacao'] : null,
                'data_conclusao' => $u['data_conclusao'] ? date('Y-m-d H:i', strtotime($u['data_conclusao'])) : null,
            ];
        }

        $this->jsonSuccess([
            'total_realizados' => $total,
            'taxa_acerto_percent' => $taxa,
            'ultimos_10' => $ultimosList,
        ]);
    }

    /**
     * GET /api/parents/child/{id}/journeys
     */
    public function journeys($id)
    {
        $childId = (int) $id;
        $parentId = $this->parentId();
        if ($parentId <= 0) {
            $this->jsonError('Não autorizado', 401);
            return;
        }
        if (!$this->validateChildOwnership($childId, $parentId)) {
            $this->jsonError('Acesso negado a este aluno', 403);
            return;
        }

        $aluno = $this->db->fetch("SELECT turma_id FROM alunos WHERE id = :id", ['id' => $childId]);
        $turmaId = $aluno ? (int) $aluno['turma_id'] : 0;
        if ($turmaId <= 0) {
            $this->jsonSuccess([]);
            return;
        }

        $jornadas = $this->db->fetchAll(
            "SELECT j.id, j.titulo, j.status,
                    (SELECT COUNT(*) FROM jornadas_modulos jm WHERE jm.jornada_id = j.id) as total_modulos,
                    (SELECT COUNT(*) FROM jornadas_progresso_alunos jpa WHERE jpa.jornada_id = j.id AND jpa.aluno_id = :aluno_id AND jpa.atividade_tipo = 'modulo' AND jpa.status = 'concluido') as modulos_concluidos
             FROM jornadas j
             WHERE (j.turma_id = :turma_id OR (j.estrutura IS NOT NULL AND j.estrutura != ''))
               AND (j.status = 'ativa' OR j.status = 'em_andamento' OR j.status IS NULL OR j.status = '')
               AND (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC",
            ['aluno_id' => $childId, 'turma_id' => $turmaId]
        );

        $list = [];
        foreach ($jornadas as $j) {
            $totalMod = (int) ($j['total_modulos'] ?? 0);
            $concluidos = (int) ($j['modulos_concluidos'] ?? 0);
            $percentual = $totalMod > 0 ? round(($concluidos / $totalMod) * 100, 1) : 0;
            $list[] = [
                'id' => (int) $j['id'],
                'titulo' => $j['titulo'],
                'status' => $j['status'] ?? 'ativa',
                'percentual_progresso' => $percentual,
            ];
        }
        $this->jsonSuccess($list);
    }

    /**
     * GET /api/parents/child/{id}/lesson-plans
     */
    public function lessonPlans($id)
    {
        $childId = (int) $id;
        $parentId = $this->parentId();
        if ($parentId <= 0) {
            $this->jsonError('Não autorizado', 401);
            return;
        }
        if (!$this->validateChildOwnership($childId, $parentId)) {
            $this->jsonError('Acesso negado a este aluno', 403);
            return;
        }

        $aluno = $this->db->fetch("SELECT turma_id FROM alunos WHERE id = :id", ['id' => $childId]);
        $turmaId = $aluno ? (int) $aluno['turma_id'] : 0;
        if ($turmaId <= 0) {
            $this->jsonSuccess([]);
            return;
        }

        $anoAtual = date('Y');
        $planos = $this->db->fetchAll(
            "SELECT pa.id, pa.titulo, pa.objetivos, pa.created_at,
                    p.nome as professor_nome, m.nome as materia_nome
             FROM planos_aula pa
             LEFT JOIN professores p ON pa.professor_id = p.id
             LEFT JOIN materias m ON pa.materia_id = m.id
             WHERE pa.turma_id = :turma_id AND pa.deleted_at IS NULL AND YEAR(pa.created_at) = :ano_atual
             ORDER BY pa.created_at DESC",
            ['turma_id' => $turmaId, 'ano_atual' => $anoAtual]
        );

        $list = [];
        foreach ($planos as $pa) {
            $list[] = [
                'id' => (int) $pa['id'],
                'titulo' => $pa['titulo'],
                'objetivos' => $pa['objetivos'],
                'professor_nome' => $pa['professor_nome'],
                'materia_nome' => $pa['materia_nome'],
                'created_at' => $pa['created_at'] ? date('Y-m-d', strtotime($pa['created_at'])) : null,
            ];
        }
        $this->jsonSuccess($list);
    }

    /**
     * GET /api/parents/child/{id}/essays
     */
    public function essays($id)
    {
        $childId = (int) $id;
        $parentId = $this->parentId();
        if ($parentId <= 0) {
            $this->jsonError('Não autorizado', 401);
            return;
        }
        if (!$this->validateChildOwnership($childId, $parentId)) {
            $this->jsonError('Acesso negado a este aluno', 403);
            return;
        }

        $redacoes = $this->db->fetchAll(
            "SELECT id, tema, titulo, nota_final, feedback_ia, created_at
             FROM redacoes
             WHERE aluno_id = :aluno_id
             ORDER BY created_at DESC",
            ['aluno_id' => $childId]
        );

        $list = [];
        foreach ($redacoes as $r) {
            $comentario = $r['feedback_ia'] ?? '';
            $list[] = [
                'id' => (int) $r['id'],
                'tema' => $r['tema'] ?? $r['titulo'] ?? '',
                'nota' => $r['nota_final'] !== null ? (float) $r['nota_final'] : null,
                'comentario_correcao' => $comentario,
                'data' => $r['created_at'] ? date('Y-m-d', strtotime($r['created_at'])) : null,
            ];
        }
        $this->jsonSuccess($list);
    }
}
