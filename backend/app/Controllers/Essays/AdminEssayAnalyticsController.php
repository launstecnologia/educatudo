<?php

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Models/Essays/EssayProposal.php';

if (!class_exists('AdminEssayAnalyticsController')) {
class AdminEssayAnalyticsController extends BaseController
{
    private $db;
    private $authManager;
    private $proposalModel;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->authManager = new AuthManager();
        $this->proposalModel = new EssayProposal();

        $user = $this->authManager->getUser();
        if (!$user || !in_array($user['tipo'] ?? '', ['admin', 'dev'], true)) {
            $this->redirect('/admin/dashboard');
        }
    }

    /**
     * Renderiza a view de analytics em estado vazio + flash de erro, em vez
     * de deixar a exceção subir e derrubar a página inteira com "Erro Interno".
     * A causa real fica registrada no log (Database::query()/Logger já loga
     * a query e a mensagem original antes de chegar aqui).
     */
    private function falharComEstadoVazio(Throwable $e, string $view, array $dadosBase): void
    {
        if (!class_exists('Logger')) {
            require_once __DIR__ . '/../../Core/Logger.php';
        }
        Logger::error('Falha ao carregar Analytics de Redação: ' . $e->getMessage(), [
            'exception' => $e,
            'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        ], 'general');

        $this->setFlashMessage(
            'Não foi possível carregar os dados de analytics agora. Tente novamente em instantes.',
            'error'
        );

        $this->viewWithLayout('admin', $view, array_merge($dadosBase, [
            'user' => $this->authManager->getUser(),
        ]));
    }

    // -------------------------------------------------------------------------
    // Visão geral
    // -------------------------------------------------------------------------
    public function index(): void
    {
        $filtroTurma  = isset($_GET['turma_id'])    ? (int)$_GET['turma_id']       : null;
        $filtroFrom   = trim((string)($_GET['de']   ?? ''));
        $filtroTo     = trim((string)($_GET['ate']  ?? ''));

        try {
            $this->renderIndex($filtroTurma, $filtroFrom, $filtroTo);
        } catch (Throwable $e) {
            $this->falharComEstadoVazio($e, 'admin/essays-analytics/index', [
                'title'            => 'Analytics — Jornada da Redação',
                'kpis'             => [],
                'envios_por_mes'   => [],
                'distribuicao_notas' => [],
                'top_propostas'    => [],
                'turmas'           => [],
                'filtro_turma'     => $filtroTurma,
                'filtro_from'      => $filtroFrom,
                'filtro_to'        => $filtroTo,
            ]);
        }
    }

    private function renderIndex(?int $filtroTurma, string $filtroFrom, string $filtroTo): void
    {
        $filtroAtivo = $this->proposalModel->sqlFiltroPropostaAtiva('p');

        $params = [];
        $where  = ['1=1'];

        if ($filtroTurma) {
            $where[] = 'a.turma_id = :turma_id';
            $params['turma_id'] = $filtroTurma;
        }
        if ($filtroFrom !== '') {
            $where[] = 'DATE(e.submitted_at) >= :de';
            $params['de'] = $filtroFrom;
        }
        if ($filtroTo !== '') {
            $where[] = 'DATE(e.submitted_at) <= :ate';
            $params['ate'] = $filtroTo;
        }

        $whereSql = implode(' AND ', $where);

        // KPIs gerais
        $kpis = $this->db->fetch(
            "SELECT
               COUNT(DISTINCT e.student_id)                                           AS total_alunos,
               COUNT(e.id)                                                             AS total_envios,
               SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END)                      AS total_corrigidos,
               SUM(CASE WHEN c.id IS NULL AND e.status = 'submitted' THEN 1 ELSE 0 END) AS total_pendentes,
               ROUND(AVG(COALESCE(c.teacher_total_score, c.total_score)), 1)          AS media_geral,
               MAX(COALESCE(c.teacher_total_score, c.total_score))                    AS nota_maxima,
               MIN(COALESCE(c.teacher_total_score, c.total_score))                    AS nota_minima,
               COUNT(DISTINCT e.proposal_id)                                          AS total_propostas
             FROM redacoes_orientadas_entregas e
             INNER JOIN alunos a ON a.id = e.student_id
             LEFT  JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
             WHERE {$whereSql}",
            $params
        ) ?: [];

        // Envios por mês (últimos 12 meses)
        $paramsMonth = $params;
        $paramsMonth['de_12m'] = date('Y-m-d', strtotime('-12 months'));
        $whereMonth = $whereSql . " AND e.submitted_at >= :de_12m";
        $enviosPorMes = $this->db->fetchAll(
            "SELECT DATE_FORMAT(e.submitted_at, '%Y-%m') AS mes,
                    COUNT(e.id) AS total,
                    SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) AS corrigidos
             FROM redacoes_orientadas_entregas e
             INNER JOIN alunos a ON a.id = e.student_id
             LEFT  JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
             WHERE {$whereMonth}
             GROUP BY mes ORDER BY mes ASC",
            $paramsMonth
        );

        // Distribuição de notas (faixas de 100 em 100, até 1000)
        $distribuicaoNotas = $this->db->fetchAll(
            "SELECT
               FLOOR(COALESCE(c.teacher_total_score, c.total_score) / 100) * 100 AS faixa,
               COUNT(*) AS total
             FROM redacoes_orientadas_entregas e
             INNER JOIN alunos a ON a.id = e.student_id
             INNER JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
             WHERE {$whereSql}
             GROUP BY faixa ORDER BY faixa ASC",
            $params
        );

        // Top 10 propostas por participação
        $topPropostas = $this->db->fetchAll(
            "SELECT p.id AS proposal_id, p.title AS titulo,
                    COUNT(e.id)                                                         AS total_envios,
                    ROUND(AVG(COALESCE(c.teacher_total_score, c.total_score)), 1)       AS media_nota,
                    SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END)                  AS corrigidos
             FROM redacoes_orientadas_entregas e
             INNER JOIN alunos a ON a.id = e.student_id
             INNER JOIN redacoes_orientadas_propostas p ON p.id = e.proposal_id
             LEFT  JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
             WHERE {$whereSql}{$filtroAtivo}
             GROUP BY p.id, p.title ORDER BY total_envios DESC LIMIT 10",
            $params
        );

        // Turmas para filtro
        $turmas = $this->db->fetchAll(
            "SELECT DISTINCT t.id, t.nome FROM turmas t
             INNER JOIN alunos a ON a.turma_id = t.id
             INNER JOIN redacoes_orientadas_entregas e ON e.student_id = a.id
             ORDER BY t.nome"
        );

        $this->viewWithLayout('admin', 'admin/essays-analytics/index', [
            'title'            => 'Analytics — Jornada da Redação',
            'user'             => $this->authManager->getUser(),
            'kpis'             => $kpis,
            'envios_por_mes'   => $enviosPorMes,
            'distribuicao_notas' => $distribuicaoNotas,
            'top_propostas'    => $topPropostas,
            'turmas'           => $turmas,
            'filtro_turma'     => $filtroTurma,
            'filtro_from'      => $filtroFrom,
            'filtro_to'        => $filtroTo,
        ]);
    }

    // -------------------------------------------------------------------------
    // Analytics por aluno
    // -------------------------------------------------------------------------
    public function byStudent(): void
    {
        $alunoId    = isset($_GET['aluno_id']) ? (int)$_GET['aluno_id'] : null;
        $filtroFrom = trim((string)($_GET['de']  ?? ''));
        $filtroTo   = trim((string)($_GET['ate'] ?? ''));

        try {
            $this->renderByStudent($alunoId, $filtroFrom, $filtroTo);
        } catch (Throwable $e) {
            $this->falharComEstadoVazio($e, 'admin/essays-analytics/by-student', [
                'title'        => 'Analytics por Aluno — Redação',
                'alunos'       => [],
                'aluno'        => null,
                'aluno_id'     => $alunoId,
                'kpis'         => null,
                'evolucao'     => [],
                'por_proposta' => [],
                'filtro_from'  => $filtroFrom,
                'filtro_to'    => $filtroTo,
            ]);
        }
    }

    private function renderByStudent(?int $alunoId, string $filtroFrom, string $filtroTo): void
    {
        $filtroAtivo = $this->proposalModel->sqlFiltroPropostaAtiva('p');

        // Lista de alunos que já enviaram pelo menos uma redação
        $alunos = $this->db->fetchAll(
            "SELECT DISTINCT a.id, a.nome, t.nome AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             INNER JOIN redacoes_orientadas_entregas e ON e.student_id = a.id
             ORDER BY a.nome"
        );

        $kpis = null;
        $evolucao = [];
        $porProposta = [];
        $alunoInfo = null;

        if ($alunoId) {
            $alunoInfo = $this->db->fetch(
                "SELECT a.*, t.nome AS turma_nome FROM alunos a LEFT JOIN turmas t ON t.id = a.turma_id WHERE a.id = :id",
                ['id' => $alunoId]
            );

            $dateWhere  = '';
            $dateParams = ['aluno_id' => $alunoId];
            if ($filtroFrom !== '') { $dateWhere .= ' AND DATE(e.submitted_at) >= :de';  $dateParams['de']  = $filtroFrom; }
            if ($filtroTo   !== '') { $dateWhere .= ' AND DATE(e.submitted_at) <= :ate'; $dateParams['ate'] = $filtroTo; }

            // KPIs do aluno
            $kpis = $this->db->fetch(
                "SELECT
                   COUNT(e.id)                                                          AS total_envios,
                   SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END)                    AS corrigidos,
                   SUM(CASE WHEN c.id IS NULL AND e.status = 'submitted' THEN 1 ELSE 0 END) AS pendentes,
                   ROUND(AVG(COALESCE(c.teacher_total_score, c.total_score)), 1)       AS media_nota,
                   MAX(COALESCE(c.teacher_total_score, c.total_score))                 AS nota_maxima,
                   MIN(COALESCE(c.teacher_total_score, c.total_score))                 AS nota_minima,
                   COUNT(DISTINCT e.proposal_id)                                       AS total_temas
                 FROM redacoes_orientadas_entregas e
                 LEFT JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
                 WHERE e.student_id = :aluno_id {$dateWhere}",
                $dateParams
            ) ?: [];

            // Evolução da nota (cronológico)
            $evolucao = $this->db->fetchAll(
                "SELECT e.submitted_at,
                        p.title AS proposta_titulo,
                        COALESCE(c.teacher_total_score, c.total_score) AS nota,
                        c.teacher_adjusted_at AS data_correcao
                 FROM redacoes_orientadas_entregas e
                 INNER JOIN redacoes_orientadas_propostas p ON p.id = e.proposal_id
                 LEFT  JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
                 WHERE e.student_id = :aluno_id {$dateWhere} AND c.id IS NOT NULL{$filtroAtivo}
                 ORDER BY e.submitted_at ASC",
                $dateParams
            );

            // Por proposta: nota e status
            $porProposta = $this->db->fetchAll(
                "SELECT p.id AS proposal_id,
                        p.title AS titulo,
                        p.created_at AS proposta_criada_em,
                        q.name AS banca,
                        CASE WHEN c.id IS NOT NULL THEN 'corrected' ELSE e.status END AS status,
                        e.submitted_at,
                        COALESCE(c.teacher_total_score, c.total_score) AS nota
                 FROM redacoes_orientadas_propostas p
                 LEFT JOIN redacoes_orientadas_quadros q ON q.id = p.board_id
                 LEFT JOIN redacoes_orientadas_entregas e ON e.proposal_id = p.id AND e.student_id = :aluno_id {$dateWhere}
                 LEFT JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
                 WHERE 1=1{$filtroAtivo}
                 ORDER BY e.submitted_at DESC",
                $dateParams
            );
        }

        $isPrint = isset($_GET['print']) && $_GET['print'] === '1';

        if ($isPrint) {
            $this->view('admin/essays-analytics/pdf-student', [
                'aluno'        => $alunoInfo,
                'kpis'         => $kpis,
                'evolucao'     => $evolucao,
                'por_proposta' => $porProposta,
                'filtro_from'  => $filtroFrom,
                'filtro_to'    => $filtroTo,
            ]);
            return;
        }

        $this->viewWithLayout('admin', 'admin/essays-analytics/by-student', [
            'title'        => 'Analytics por Aluno — Redação',
            'user'         => $this->authManager->getUser(),
            'alunos'       => $alunos,
            'aluno'        => $alunoInfo,
            'aluno_id'     => $alunoId,
            'kpis'         => $kpis,
            'evolucao'     => $evolucao,
            'por_proposta' => $porProposta,
            'filtro_from'  => $filtroFrom,
            'filtro_to'    => $filtroTo,
        ]);
    }

    // -------------------------------------------------------------------------
    // Analytics por proposta/tema
    // -------------------------------------------------------------------------
    public function byProposal(): void
    {
        $proposalId = isset($_GET['proposta_id']) ? (int)$_GET['proposta_id'] : null;
        $filtroFrom = trim((string)($_GET['de']  ?? ''));
        $filtroTo   = trim((string)($_GET['ate'] ?? ''));

        try {
            $this->renderByProposal($proposalId, $filtroFrom, $filtroTo);
        } catch (Throwable $e) {
            $this->falharComEstadoVazio($e, 'admin/essays-analytics/by-proposal', [
                'title'        => 'Analytics por Proposta — Redação',
                'propostas'    => [],
                'proposta'     => null,
                'proposta_id'  => $proposalId,
                'kpis'         => null,
                'distribuicao' => [],
                'alunos'       => [],
                'filtro_from'  => $filtroFrom,
                'filtro_to'    => $filtroTo,
            ]);
        }
    }

    private function renderByProposal(?int $proposalId, string $filtroFrom, string $filtroTo): void
    {
        $filtroAtivo = $this->proposalModel->sqlFiltroPropostaAtiva('p');

        $propostas = $this->db->fetchAll(
            "SELECT p.id, p.title AS titulo, q.name AS banca
             FROM redacoes_orientadas_propostas p
             LEFT JOIN redacoes_orientadas_quadros q ON q.id = p.board_id
             WHERE 1=1{$filtroAtivo}
             ORDER BY p.title"
        );

        $kpis         = null;
        $distribuicao = [];
        $alunos       = [];
        $proposalInfo = null;

        if ($proposalId) {
            $proposalInfo = $this->db->fetch(
                "SELECT p.*, p.title AS titulo, q.name AS banca FROM redacoes_orientadas_propostas p LEFT JOIN redacoes_orientadas_quadros q ON q.id = p.board_id WHERE p.id = :id{$filtroAtivo}",
                ['id' => $proposalId]
            );

            $dateWhere  = '';
            $dateParams = ['proposal_id' => $proposalId];
            if ($filtroFrom !== '') { $dateWhere .= ' AND DATE(e.submitted_at) >= :de';  $dateParams['de']  = $filtroFrom; }
            if ($filtroTo   !== '') { $dateWhere .= ' AND DATE(e.submitted_at) <= :ate'; $dateParams['ate'] = $filtroTo; }

            // KPIs da proposta
            $kpis = $this->db->fetch(
                "SELECT
                   COUNT(e.id)                                                          AS total_envios,
                   SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END)                    AS corrigidos,
                   SUM(CASE WHEN c.id IS NULL AND e.status = 'submitted' THEN 1 ELSE 0 END) AS pendentes,
                   ROUND(AVG(COALESCE(c.teacher_total_score, c.total_score)), 1)       AS media_nota,
                   MAX(COALESCE(c.teacher_total_score, c.total_score))                 AS nota_maxima,
                   MIN(COALESCE(c.teacher_total_score, c.total_score))                 AS nota_minima,
                   COUNT(DISTINCT a.turma_id)                                          AS total_turmas
                 FROM redacoes_orientadas_entregas e
                 INNER JOIN alunos a ON a.id = e.student_id
                 LEFT JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
                 WHERE e.proposal_id = :proposal_id {$dateWhere}",
                $dateParams
            ) ?: [];

            // Distribuição de notas por faixa
            $distribuicao = $this->db->fetchAll(
                "SELECT FLOOR(COALESCE(c.teacher_total_score, c.total_score) / 100) * 100 AS faixa,
                        COUNT(*) AS total
                 FROM redacoes_orientadas_entregas e
                 INNER JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
                 WHERE e.proposal_id = :proposal_id {$dateWhere}
                 GROUP BY faixa ORDER BY faixa ASC",
                $dateParams
            );

            // Alunos com nota
            $alunos = $this->db->fetchAll(
                "SELECT a.nome AS aluno_nome,
                        t.nome AS turma_nome,
                        CASE WHEN c.id IS NOT NULL THEN 'corrected' ELSE e.status END AS status,
                        e.submitted_at,
                        COALESCE(c.teacher_total_score, c.total_score) AS nota
                 FROM redacoes_orientadas_entregas e
                 INNER JOIN alunos a ON a.id = e.student_id
                 LEFT  JOIN turmas t ON t.id = a.turma_id
                 LEFT  JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
                 WHERE e.proposal_id = :proposal_id {$dateWhere}
                 ORDER BY nota DESC, a.nome ASC",
                $dateParams
            );
        }

        $isPrint = isset($_GET['print']) && $_GET['print'] === '1';

        if ($isPrint) {
            $this->view('admin/essays-analytics/pdf-proposal', [
                'proposta'     => $proposalInfo,
                'kpis'         => $kpis,
                'distribuicao' => $distribuicao,
                'alunos'       => $alunos,
                'filtro_from'  => $filtroFrom,
                'filtro_to'    => $filtroTo,
            ]);
            return;
        }

        $this->viewWithLayout('admin', 'admin/essays-analytics/by-proposal', [
            'title'        => 'Analytics por Proposta — Redação',
            'user'         => $this->authManager->getUser(),
            'propostas'    => $propostas,
            'proposta'     => $proposalInfo,
            'proposta_id'  => $proposalId,
            'kpis'         => $kpis,
            'distribuicao' => $distribuicao,
            'alunos'       => $alunos,
            'filtro_from'  => $filtroFrom,
            'filtro_to'    => $filtroTo,
        ]);
    }

    private function rotuloStatusEntrega(?string $status): string
    {
        return match ((string) $status) {
            'corrected' => 'corrigido',
            'submitted' => 'enviado',
            'draft'     => 'rascunho',
            default     => (string) $status,
        };
    }

    // -------------------------------------------------------------------------
    // Exportação Excel (CSV)
    // -------------------------------------------------------------------------
    public function exportExcel(): void
    {
        try {
            $this->renderExportExcel();
        } catch (Throwable $e) {
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../../Core/Logger.php';
            }
            Logger::error('Falha ao exportar Analytics de Redação: ' . $e->getMessage(), [
                'exception' => $e,
                'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            ], 'general');

            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Não foi possível gerar a exportação agora. Tente novamente em instantes.';
        }
    }

    private function renderExportExcel(): void
    {
        $filtroAtivo = $this->proposalModel->sqlFiltroPropostaAtiva('p');
        $tipo       = trim((string)($_GET['tipo']       ?? 'geral'));
        $alunoId    = isset($_GET['aluno_id'])    ? (int)$_GET['aluno_id']    : null;
        $proposalId = isset($_GET['proposta_id']) ? (int)$_GET['proposta_id'] : null;
        $filtroFrom = trim((string)($_GET['de']  ?? ''));
        $filtroTo   = trim((string)($_GET['ate'] ?? ''));

        $rows = [];
        $headers = [];
        $filename = 'relatorio-redacoes-' . date('Y-m-d') . '.csv';

        if ($tipo === 'aluno' && $alunoId) {
            $headers = ['Proposta', 'Banca', 'Status', 'Data Envio', 'Nota'];
            $dateWhere  = ''; $dateParams = ['aluno_id' => $alunoId];
            if ($filtroFrom !== '') { $dateWhere .= ' AND DATE(e.submitted_at) >= :de';  $dateParams['de']  = $filtroFrom; }
            if ($filtroTo   !== '') { $dateWhere .= ' AND DATE(e.submitted_at) <= :ate'; $dateParams['ate'] = $filtroTo; }
            $data = $this->db->fetchAll(
                "SELECT p.title AS titulo, q.name AS banca, CASE WHEN c.id IS NOT NULL THEN 'corrected' ELSE e.status END AS status, e.submitted_at,
                        COALESCE(c.teacher_total_score, c.total_score) AS nota
                 FROM redacoes_orientadas_entregas e
                 INNER JOIN redacoes_orientadas_propostas p ON p.id = e.proposal_id
                 LEFT  JOIN redacoes_orientadas_quadros q ON q.id = p.board_id
                 LEFT  JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
                 WHERE e.student_id = :aluno_id {$dateWhere}{$filtroAtivo}
                 ORDER BY e.submitted_at DESC",
                $dateParams
            );
            foreach ($data as $r) {
                $rows[] = [$r['titulo'], $r['banca'] ?? '', $this->rotuloStatusEntrega($r['status'] ?? null), $r['submitted_at'] ?? '', $r['nota'] ?? ''];
            }
            $aluno = $this->db->fetch("SELECT nome FROM alunos WHERE id = :id", ['id' => $alunoId]);
            $filename = 'redacoes-aluno-' . preg_replace('/[^a-z0-9]/i', '-', (string)($aluno['nome'] ?? $alunoId)) . '-' . date('Y-m-d') . '.csv';

        } elseif ($tipo === 'proposta' && $proposalId) {
            $headers = ['Aluno', 'Turma', 'Status', 'Data Envio', 'Nota'];
            $dateWhere  = ''; $dateParams = ['proposal_id' => $proposalId];
            if ($filtroFrom !== '') { $dateWhere .= ' AND DATE(e.submitted_at) >= :de';  $dateParams['de']  = $filtroFrom; }
            if ($filtroTo   !== '') { $dateWhere .= ' AND DATE(e.submitted_at) <= :ate'; $dateParams['ate'] = $filtroTo; }
            $data = $this->db->fetchAll(
                "SELECT a.nome AS aluno, t.nome AS turma, CASE WHEN c.id IS NOT NULL THEN 'corrected' ELSE e.status END AS status, e.submitted_at,
                        COALESCE(c.teacher_total_score, c.total_score) AS nota
                 FROM redacoes_orientadas_entregas e
                 INNER JOIN alunos a ON a.id = e.student_id
                 INNER JOIN redacoes_orientadas_propostas p ON p.id = e.proposal_id
                 LEFT  JOIN turmas t ON t.id = a.turma_id
                 LEFT  JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
                 WHERE e.proposal_id = :proposal_id {$dateWhere}{$filtroAtivo}
                 ORDER BY nota DESC, a.nome ASC",
                $dateParams
            );
            foreach ($data as $r) {
                $rows[] = [$r['aluno'], $r['turma'] ?? '', $this->rotuloStatusEntrega($r['status'] ?? null), $r['submitted_at'] ?? '', $r['nota'] ?? ''];
            }
            $filename = 'redacoes-proposta-' . $proposalId . '-' . date('Y-m-d') . '.csv';

        } else {
            $headers = ['Aluno', 'Turma', 'Proposta', 'Status', 'Data Envio', 'Nota'];
            $where = ['1=1']; $params = [];
            if ($filtroFrom !== '') { $where[] = 'DATE(e.submitted_at) >= :de';  $params['de']  = $filtroFrom; }
            if ($filtroTo   !== '') { $where[] = 'DATE(e.submitted_at) <= :ate'; $params['ate'] = $filtroTo; }
            $data = $this->db->fetchAll(
                "SELECT a.nome AS aluno, t.nome AS turma, p.title AS titulo, CASE WHEN c.id IS NOT NULL THEN 'corrected' ELSE e.status END AS status, e.submitted_at,
                        COALESCE(c.teacher_total_score, c.total_score) AS nota
                 FROM redacoes_orientadas_entregas e
                 INNER JOIN alunos a ON a.id = e.student_id
                 INNER JOIN redacoes_orientadas_propostas p ON p.id = e.proposal_id
                 LEFT  JOIN turmas t ON t.id = a.turma_id
                 LEFT  JOIN redacoes_orientadas_correcoes c ON c.submission_id = e.id
                 WHERE " . implode(' AND ', $where) . " {$filtroAtivo}
                 ORDER BY e.submitted_at DESC LIMIT 5000",
                $params
            );
            foreach ($data as $r) {
                $rows[] = [$r['aluno'], $r['turma'] ?? '', $r['titulo'], $this->rotuloStatusEntrega($r['status'] ?? null), $r['submitted_at'] ?? '', $r['nota'] ?? ''];
            }
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM para Excel
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }
}
}
