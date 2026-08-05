<?php
/**
 * EducaTudo - Controller de Administracao (extraido de AdminController)
 */

require_once __DIR__ . '/../../Models/User/Student.php';
require_once __DIR__ . '/../../Models/User/Parent.php';
require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
require_once __DIR__ . '/../../Services/StudentStatusService.php';
require_once __DIR__ . '/../../Services/MatriculaSchemaHelper.php';
require_once __DIR__ . '/../../Core/LayoutHelper.php';
require_once __DIR__ . '/AdminBaseController.php';

use App\Services\MatriculaSchemaHelper;

if (!class_exists('StudentAdminController')) {
class StudentAdminController extends AdminBaseController
{
    public function dashboard()
    {
        $user = $this->auth->getUser();
        $authManager = new AuthManager();
        $alunosOnline = $authManager->getAlunosOnline();
        
        // Verificar se é diretor ou dev para mostrar valor total a pagar
        $isDiretorOuDev = false;
        $valorTotalPagar = 0;
        
        if (isset($user['perfil_admin'])) {
            $perfil = $user['perfil_admin'];
            if ($perfil === 'dev' || $perfil === 'diretor') {
                $isDiretorOuDev = true;
                
                // Buscar valor por usuário
                require_once __DIR__ . '/../../Core/LayoutHelper.php';
                $valorPorUsuario = floatval(LayoutHelper::get('valor_por_usuario', '0.00'));
                
                // Contar total de pagantes (alunos + professores)
                $totalAlunosPagantes = $this->db->fetch("SELECT COUNT(*) as count FROM alunos WHERE ativo = 1 AND pagante = 1")['count'];
                $totalProfessoresPagantes = $this->db->fetch("SELECT COUNT(*) as count FROM professores WHERE ativo = 1 AND pagante = 1")['count'];
                $totalPagantes = $totalAlunosPagantes + $totalProfessoresPagantes;
                
                // Calcular valor total
                $valorTotalPagar = $totalPagantes * $valorPorUsuario;
            }
        }
        
        // Estatísticas gerais
        $stats = [
            'alunos_online' => is_array($alunosOnline) ? count($alunosOnline) : 0,
            'total_alunos' => $this->db->fetch("SELECT COUNT(*) as count FROM alunos WHERE ativo = 1 AND pagante = 1")['count'],
            'total_professores' => $this->db->fetch("SELECT COUNT(*) as count FROM professores WHERE ativo = 1 AND pagante = 1")['count'],
            'total_turmas' => $this->db->fetch("SELECT COUNT(*) as count FROM turmas WHERE ativo = 1")['count'],
            'total_jornadas' => $this->db->fetch("SELECT COUNT(*) as count FROM jornadas")['count'],
            'total_exercicios' => $this->db->fetch("SELECT COUNT(*) as count FROM exercicios")['count'],
            'is_diretor_ou_dev' => $isDiretorOuDev,
            'valor_total_pagar' => $valorTotalPagar
        ];

        if ($this->podeVerAlertasSensiveis($user)) {
            $stats['alertas_novos'] = $this->db->fetch(
                "SELECT COUNT(*) as count FROM alertas_sensiveis WHERE status = 'novo'"
            )['count'];
        }
        
        $panoramaJornada = $this->obterPanoramaJornadaDashboard();

        $aulasOnline = [];
        try {
            require_once __DIR__ . '/../../Models/Education/OnlineClass.php';
            $aulasOnline = (new OnlineClass())->listLiveAndUpcomingForAdmin(5);
        } catch (Throwable $e) {
            $aulasOnline = [];
        }

        $data = [
            'title' => 'Painel Administrativo - EducaTudo',
            'user' => $user,
            'stats' => $stats,
            'panorama_jornada' => $panoramaJornada,
            'aulas_online' => $aulasOnline,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'dashboard'
        ];
        
        $this->viewWithLayout('admin', 'admin/dashboard', $data);
    }

    private function obterPanoramaJornadaDashboard(): array
    {
        $fallback = [
            'ensino_medio' => $this->calcularPanoramaJornadaPorTipoEnsino('medio'),
            'fundamental_ii' => $this->calcularPanoramaJornadaPorTipoEnsino('fundamental_ii'),
        ];

        try {
            $rows = $this->db->fetchAll(
                "SELECT segmento, jornadas_escopo, pares_atribuidos, concluidos, pendentes, taxa_conclusao, atualizado_em
                 FROM dashboard_jornadas_resumo
                 WHERE segmento IN ('medio', 'fundamental_ii')"
            );
        } catch (Exception $e) {
            return $fallback;
        }

        if (empty($rows)) {
            return $fallback;
        }

        $out = $fallback;
        foreach ($rows as $row) {
            $segmento = (string)($row['segmento'] ?? '');
            if ($segmento === 'medio') {
                $out['ensino_medio'] = [
                    'jornadas_escopo' => (int)($row['jornadas_escopo'] ?? 0),
                    'pares_atribuidos' => (int)($row['pares_atribuidos'] ?? 0),
                    'concluidos' => (int)($row['concluidos'] ?? 0),
                    'pendentes' => (int)($row['pendentes'] ?? 0),
                    'taxa_conclusao' => (float)($row['taxa_conclusao'] ?? 0),
                    'atualizado_em' => $row['atualizado_em'] ?? null,
                ];
            } elseif ($segmento === 'fundamental_ii') {
                $out['fundamental_ii'] = [
                    'jornadas_escopo' => (int)($row['jornadas_escopo'] ?? 0),
                    'pares_atribuidos' => (int)($row['pares_atribuidos'] ?? 0),
                    'concluidos' => (int)($row['concluidos'] ?? 0),
                    'pendentes' => (int)($row['pendentes'] ?? 0),
                    'taxa_conclusao' => (float)($row['taxa_conclusao'] ?? 0),
                    'atualizado_em' => $row['atualizado_em'] ?? null,
                ];
            }
        }

        return $out;
    }

    private function calcularPanoramaJornadaPorTipoEnsino(string $segmento): array
    {
        if ($segmento === 'medio') {
            $whereTipoJornada = "LOWER(COALESCE(t.tipo_ensino, '')) LIKE '%medio%'";
            $whereTipoAluno = "LOWER(COALESCE(t_aluno.tipo_ensino, '')) LIKE '%medio%'";
        } elseif ($segmento === 'fundamental_ii') {
            $whereTipoJornada = "(LOWER(COALESCE(t.tipo_ensino, '')) LIKE '%fundamental ii%' OR LOWER(COALESCE(t.tipo_ensino, '')) LIKE '%fundamental 2%')";
            $whereTipoAluno = "(LOWER(COALESCE(t_aluno.tipo_ensino, '')) LIKE '%fundamental ii%' OR LOWER(COALESCE(t_aluno.tipo_ensino, '')) LIKE '%fundamental 2%')";
        } else {
            return ['total' => 0, 'realizadas' => 0, 'nao_realizadas' => 0, 'percentual' => 0];
        }

        $total = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS count
             FROM jornadas j
             INNER JOIN turmas t ON t.id = j.turma_id
             WHERE {$whereTipoJornada}"
        )['count'] ?? 0);

        // Percentual geral consolidado por etapa:
        // 1) para cada aluno: jornadas_atribuidas da turma e jornadas_feitas
        // 2) consolida tudo: percentual = SUM(feitas) / SUM(atribuidas) * 100
        $resumoPorAluno = $this->db->fetch(
            "SELECT
                COALESCE(SUM(base.jornadas_atribuidas), 0) AS total_previstas,
                COALESCE(SUM(base.jornadas_feitas), 0) AS total_realizadas,
                COALESCE(
                    (SUM(base.jornadas_feitas) / NULLIF(SUM(base.jornadas_atribuidas), 0)) * 100,
                    0
                ) AS percentual_geral
             FROM (
                 SELECT
                    a.id AS aluno_id,
                    COUNT(DISTINCT j.id) AS jornadas_atribuidas,
                    LEAST(
                        COUNT(DISTINCT CASE WHEN jpa.jornada_id IS NOT NULL THEN j.id END),
                        COUNT(DISTINCT j.id)
                    ) AS jornadas_feitas
                 FROM alunos a
                 INNER JOIN turmas t_aluno ON t_aluno.id = a.turma_id
                 LEFT JOIN jornadas j ON j.turma_id = a.turma_id
                 LEFT JOIN jornadas_progresso_alunos jpa
                    ON jpa.aluno_id = a.id
                   AND jpa.jornada_id = j.id
                   AND jpa.status = 'concluido'
                   AND (jpa.atividade_tipo IS NULL OR jpa.atividade_tipo = 'jornada_concluida')
                 WHERE a.ativo = 1
                   AND {$whereTipoAluno}
                 GROUP BY a.id
             ) base"
        ) ?? [];

        $totalPrevistas = (int) ($resumoPorAluno['total_previstas'] ?? 0);
        $realizadas = (int) ($resumoPorAluno['total_realizadas'] ?? 0);
        $naoRealizadas = max(0, $totalPrevistas - $realizadas);
        $percentualBruto = (float)($resumoPorAluno['percentual_geral'] ?? 0);
        $percentual = round(min(100, max(0, $percentualBruto)), 1);

        return [
            'jornadas_escopo' => $total,
            'pares_atribuidos' => $totalPrevistas,
            'concluidos' => $realizadas,
            'pendentes' => $naoRealizadas,
            'taxa_conclusao' => $percentual,
            // compatibilidade com estrutura antiga
            'total' => $total,
            'realizadas' => $realizadas,
            'nao_realizadas' => $naoRealizadas,
            'percentual' => $percentual,
        ];
    }

    public function painelManutencao()
    {
        $user = $this->auth->getUser();
        $data = [
            'title' => 'Painel de Manutenção - EducaTudo',
            'page_title' => 'Modo Manutenção',
            'user' => $user,
            'maintenance_mode' => $this->isMaintenanceEnabled(),
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'maintenance_panel'
        ];

        $this->viewWithLayout('admin', 'admin/maintenance/painel', $data);
    }

    public function toggleMaintenance()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/maintenance/painel');
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect('/admin/maintenance/painel?erro=token');
            return;
        }

        $enabled = ($_POST['enabled'] ?? '0') === '1';
        $this->setMaintenanceEnabled($enabled);
        $this->redirect('/admin/maintenance/painel?ok=1');
    }

    public function alunos()
    {
        $user = $this->auth->getUser();
        
        // Parâmetros de paginação
        $page = (int)($_GET['page'] ?? 1);
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        // Parâmetros de filtro
        $filtros = [
            'nome' => trim($_GET['nome'] ?? ''),
            'ra' => trim($_GET['ra'] ?? ''),
            'cpf' => preg_replace('/\D+/', '', (string) ($_GET['cpf'] ?? '')),
            'nickname' => trim($_GET['nickname'] ?? ''),
            'responsavel' => trim($_GET['responsavel'] ?? ''),
            'turma_id' => $_GET['turma_id'] ?? '',
        ];
        
        // Construir query WHERE
        $where_clause = "WHERE 1=1";
        $params = [];
        
        if ($filtros['nome'] !== '') {
            $where_clause .= " AND a.nome LIKE :nome";
            $params['nome'] = '%' . $filtros['nome'] . '%';
        }
        
        if ($filtros['ra'] !== '') {
            $where_clause .= " AND (a.ra LIKE :ra OR a.codigo_aluno LIKE :ra_codigo)";
            $params['ra'] = '%' . $filtros['ra'] . '%';
            $params['ra_codigo'] = '%' . $filtros['ra'] . '%';
        }

        if ($filtros['cpf'] !== '') {
            $where_clause .= " AND REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') LIKE :cpf";
            $params['cpf'] = '%' . $filtros['cpf'] . '%';
        }

        if ($filtros['nickname'] !== '') {
            $where_clause .= " AND a.nickname LIKE :nickname";
            $params['nickname'] = '%' . $filtros['nickname'] . '%';
        }

        if ($filtros['responsavel'] !== '') {
            $where_clause .= " AND (
                EXISTS (
                    SELECT 1 FROM alunos_responsaveis ar_f
                    INNER JOIN responsaveis r_f ON r_f.id = ar_f.responsavel_id
                    WHERE ar_f.aluno_id = a.id AND ar_f.ativo = 1 AND r_f.nome LIKE :responsavel
                )
                OR p.nome LIKE :responsavel_p
            )";
            $params['responsavel'] = '%' . $filtros['responsavel'] . '%';
            $params['responsavel_p'] = '%' . $filtros['responsavel'] . '%';
        }
        
        if (!empty($filtros['turma_id'])) {
            $where_clause .= " AND (a.turma_id = :turma_id OR EXISTS (SELECT 1 FROM alunos_turmas_historico h WHERE h.aluno_id = a.id AND h.turma_id = :turma_id_hist))";
            $params['turma_id'] = $filtros['turma_id'];
            $params['turma_id_hist'] = $filtros['turma_id'];
        }
        
        // Contar total de registros
        $total_alunos = $this->db->fetch(
            "SELECT COUNT(*) as total
             FROM alunos a
             LEFT JOIN responsaveis p ON a.responsavel_id = p.id
             {$where_clause}",
            $params
        )['total'];
        
        // Buscar alunos com paginação
        $turmaLabelSql = $this->sqlTurmaLabelFieldsAndJoins();
        $alunos = $this->db->fetchAll(
            "SELECT a.*, t.nome as turma_nome,
                    {$turmaLabelSql['select']},
                    COALESCE(
                        (SELECT GROUP_CONCAT(DISTINCT r2.nome ORDER BY r2.nome SEPARATOR ', ')
                         FROM alunos_responsaveis ar2
                         INNER JOIN responsaveis r2 ON r2.id = ar2.responsavel_id
                         WHERE ar2.aluno_id = a.id AND ar2.ativo = 1),
                        p.nome
                    ) as responsavel_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             {$turmaLabelSql['joins']}
             LEFT JOIN responsaveis p ON a.responsavel_id = p.id
             {$where_clause}
             ORDER BY {$this->sqlAlunosOrderByClause()}
             LIMIT {$per_page} OFFSET {$offset}",
            $params
        );

        require_once __DIR__ . '/../../Helpers/StudentPhotoHelper.php';
        require_once __DIR__ . '/../../Helpers/TurmaLabelHelper.php';
        foreach ($alunos as $idx => $alunoRow) {
            $alunos[$idx] = StudentPhotoHelper::enrichStudent($alunoRow, false);
            $alunos[$idx]['turma_display'] = TurmaLabelHelper::formatListLabel($alunoRow);
        }
        
        // Buscar turmas para o filtro
        $turmas = $this->db->fetchAll(
            "SELECT t.id, t.nome AS turma_nome, {$turmaLabelSql['select']}
             FROM turmas t
             {$turmaLabelSql['joins']}
             ORDER BY {$this->sqlAlunosOrderByClause(false)}"
        );
        foreach ($turmas as $idx => $turmaRow) {
            $turmas[$idx]['label'] = TurmaLabelHelper::formatListLabel($turmaRow);
        }
        
        // Calcular informações de paginação
        $total_pages = ceil($total_alunos / $per_page);
        
        $data = [
            'title' => 'Gestão de Alunos - EducaTudo',
            'students' => $alunos,
            'turmas' => $turmas,
            'filtros' => $filtros,
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'students',
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total_alunos,
                'per_page' => $per_page,
                'has_prev' => $page > 1,
                'has_next' => $page < $total_pages,
                'prev_page' => $page > 1 ? $page - 1 : 1,
                'next_page' => $page < $total_pages ? $page + 1 : $total_pages
            ]
        ];
        
        $this->viewWithLayout('admin', 'admin/students/index', $data);
    }

    public function transferenciaAlunos()
    {
        if (!$this->enforceAdminPermissionKey('transferencia', 'visualizar', false)) {
            return;
        }
        $turmaId = trim((string) ($_GET['turma_id'] ?? ''));
        $url = '/admin/students/remanejamento';
        if ($turmaId !== '') {
            $url .= '?turma_id=' . urlencode($turmaId);
        }
        $this->redirect($url);
    }

    public function processarTransferenciaAlunos()
    {
        if (!$this->enforceAdminPermissionKey('transferencia', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/students/remanejamento');
        }

        $turma_origem = $_POST['turma_origem'] ?? '';
        $turma_destino = $_POST['turma_destino'] ?? '';
        $student_ids = $_POST['student_ids'] ?? [];

        if ((string)$turma_origem === '' || (string)$turma_destino === '') {
            $this->setFlashMessage('Selecione a turma de origem e a turma de destino.', 'error');
            $this->redirect('/admin/students/remanejamento?turma_id=' . urlencode($turma_origem));
        }

        if ((string)$turma_destino !== '0' && (string)$turma_origem === (string)$turma_destino) {
            $this->setFlashMessage('A turma de destino deve ser diferente da turma de origem.', 'error');
            $this->redirect('/admin/students/remanejamento?turma_id=' . urlencode($turma_origem));
        }

        $student_ids = array_values(array_unique(array_map('intval', (array)$student_ids)));
        $student_ids = array_filter($student_ids, fn($id) => $id > 0);

        if (empty($student_ids)) {
            $this->setFlashMessage('Selecione ao menos um aluno para transferir.', 'error');
            $this->redirect('/admin/students/remanejamento?turma_id=' . urlencode($turma_origem));
        }

        $turmaOrigem = $this->db->fetch(
            "SELECT id, nome FROM turmas WHERE id = :id",
            ['id' => $turma_origem]
        );
        $turmaDestino = null;
        if ((string)$turma_destino !== '0') {
            $turmaDestino = $this->db->fetch(
                "SELECT id, nome FROM turmas WHERE id = :id",
                ['id' => $turma_destino]
            );
        }

        if (!$turmaOrigem || ((string)$turma_destino !== '0' && !$turmaDestino)) {
            $this->setFlashMessage('Turma de origem ou destino inválida.', 'error');
            $this->redirect('/admin/students/remanejamento');
        }

        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $validIds = $this->db->fetchAll(
            "SELECT id FROM alunos WHERE turma_id = ? AND id IN ($placeholders)",
            array_merge([$turma_origem], $student_ids)
        );
        $validIds = array_map('intval', array_column($validIds, 'id'));

        if (empty($validIds)) {
            $this->setFlashMessage('Nenhum aluno válido encontrado para transferência.', 'error');
            $this->redirect('/admin/students/remanejamento?turma_id=' . urlencode($turma_origem));
        }

        try {
            $this->db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            $destinoValor = (string)$turma_destino === '0' ? null : $turma_destino;
            $this->db->query(
                "UPDATE alunos SET turma_id = ? WHERE id IN ($placeholders)",
                array_merge([$destinoValor], $validIds)
            );

            $this->db->query(
                "UPDATE alunos_turmas_historico
                 SET data_fim = CURDATE()
                 WHERE aluno_id IN ($placeholders)
                 AND data_fim IS NULL
                 AND turma_id = ?",
                array_merge($validIds, [$turma_origem])
            );

            if ((string)$turma_destino !== '0') {
                $anoLetivo = date('Y');
                foreach ($validIds as $alunoId) {
                    $this->db->query(
                        "INSERT INTO alunos_turmas_historico (aluno_id, turma_id, ano_letivo, data_inicio)
                         VALUES (:aluno_id, :turma_id, :ano_letivo, CURDATE())",
                        [
                            'aluno_id' => $alunoId,
                            'turma_id' => $turma_destino,
                            'ano_letivo' => $anoLetivo
                        ]
                    );
                }
            }

            foreach ($validIds as $alunoId) {
                if ((string) $turma_destino === '0') {
                    $this->encerrarMatriculasAtivasNaTurma((int) $alunoId, (int) $turma_origem);
                } else {
                    $this->reconcileMatriculaAposAtualizarAluno(
                        (int) $alunoId,
                        (int) $turma_destino,
                        (int) $turma_origem,
                        true
                    );
                }
            }

            $this->db->commit();

            $totalTransferidos = count($validIds);
            $totalIgnorados = count($student_ids) - $totalTransferidos;
            if ((string)$turma_destino === '0') {
                $mensagem = "Transferência concluída: {$totalTransferidos} aluno(s) sem turma.";
            } else {
                $mensagem = "Transferência concluída: {$totalTransferidos} aluno(s) para {$turmaDestino['nome']}.";
            }
            if ($totalIgnorados > 0) {
                $mensagem .= " {$totalIgnorados} aluno(s) ignorado(s).";
            }

            $this->setFlashMessage($mensagem, 'success');
            $this->redirect('/admin/students/remanejamento?turma_id=' . urlencode($turma_origem));
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->setFlashMessage('Erro ao transferir alunos: ' . $e->getMessage(), 'error');
            $this->redirect('/admin/students/remanejamento?turma_id=' . urlencode($turma_origem));
        }
    }

    public function remanejamentoAlunos()
    {
        if (!$this->enforceAdminPermissionKey('transferencia', 'visualizar', false)) {
            return;
        }
        $data = $this->buildMovimentacaoAlunosViewData(
            'Remanejamento',
            'Selecione turma de origem, destino e os alunos.',
            'admin/students/remanejamento',
            'students_remanejamento'
        );
        $this->viewWithLayout('admin', 'admin/students/remanejamento', $data);
    }

    public function processarRemanejamentoAlunos()
    {
        if (!$this->enforceAdminPermissionKey('transferencia', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/students/remanejamento');
            return;
        }

        $turmaOrigem = (int) ($_POST['turma_origem'] ?? 0);
        $turmaDestino = (int) ($_POST['turma_destino'] ?? 0);
        $studentIds = $_POST['student_ids'] ?? [];

        require_once __DIR__ . '/../../Services/AlunoMovimentacaoService.php';
        try {
            $result = (new \App\Services\AlunoMovimentacaoService())->remanejarEmLote($turmaOrigem, $turmaDestino, (array) $studentIds);
            $msg = "Remanejamento concluído: {$result['transferidos']} aluno(s).";
            if ($result['ignorados'] > 0) {
                $msg .= " {$result['ignorados']} ignorado(s).";
            }
            $this->setFlashMessage($msg, 'success');
        } catch (Exception $e) {
            $this->setFlashMessage('Erro no remanejamento: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/admin/students/remanejamento?turma_id=' . urlencode((string) $turmaOrigem));
    }

    public function transferenciaEscolarAlunos()
    {
        if (!$this->enforceAdminPermissionKey('transferencia', 'visualizar', false)) {
            return;
        }
        $data = $this->buildMovimentacaoAlunosViewData(
            'Saída da escola (TR)',
            'Selecione a turma de origem e os alunos que deixam a escola.',
            'admin/students/transferencia-escolar',
            'students_transferencia_escolar'
        );
        $this->viewWithLayout('admin', 'admin/students/transferencia-escolar', $data);
    }

    public function processarTransferenciaEscolar()
    {
        if (!$this->enforceAdminPermissionKey('transferencia', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/students/transferencia-escolar');
            return;
        }

        $turmaOrigem = (int) ($_POST['turma_origem'] ?? 0);
        $studentIds = $_POST['student_ids'] ?? [];
        $removerTurma = isset($_POST['remover_turma']);

        require_once __DIR__ . '/../../Services/AlunoMovimentacaoService.php';
        try {
            $user = $this->auth->getUser();
            $result = (new \App\Services\AlunoMovimentacaoService())->transferenciaEscolarEmLote(
                $turmaOrigem,
                (array) $studentIds,
                $_POST,
                $user,
                $removerTurma
            );
            $msg = "Transferência escolar concluída: {$result['processados']} aluno(s).";
            if ($result['ignorados'] > 0) {
                $msg .= " {$result['ignorados']} ignorado(s).";
            }
            $this->setFlashMessage($msg, 'success');
        } catch (Exception $e) {
            $this->setFlashMessage('Erro na transferência escolar: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/admin/students/transferencia-escolar?turma_id=' . urlencode((string) $turmaOrigem));
    }

    private function buildMovimentacaoAlunosViewData(string $title, string $subtitle, string $viewKey, string $currentPage): array
    {
        $user = $this->auth->getUser();
        $turma_id = $_GET['turma_id'] ?? '';
        $turmas = $this->db->fetchAll('SELECT id, nome, serie FROM turmas ORDER BY nome ASC');
        $alunos = [];
        if (!empty($turma_id)) {
            $alunos = $this->db->fetchAll(
                "SELECT id, nome, ra, nickname, ativo FROM alunos WHERE turma_id = :turma_id AND ativo = 1 ORDER BY nome ASC",
                ['turma_id' => $turma_id]
            );
        }
        $flash = $this->getFlashMessage();

        return [
            'title' => $title . ' - EducaTudo',
            'page_title' => $title,
            'page_subtitle' => $subtitle,
            'user' => $user,
            'turmas' => $turmas,
            'alunos' => $alunos,
            'turma_id' => $turma_id,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'current_page' => $currentPage,
            'view_key' => $viewKey,
        ];
    }

    public function exportarAlunosCSV()
    {
        $filtros = [
            'nome' => $_GET['nome'] ?? '',
            'ra' => $_GET['ra'] ?? '',
            'turma_id' => $_GET['turma_id'] ?? ''
        ];

        $where_clause = "WHERE 1=1";
        $params = [];

        if (!empty($filtros['nome'])) {
            $where_clause .= " AND a.nome LIKE :nome";
            $params['nome'] = '%' . $filtros['nome'] . '%';
        }

        if (!empty($filtros['ra'])) {
            $where_clause .= " AND a.ra LIKE :ra";
            $params['ra'] = '%' . $filtros['ra'] . '%';
        }

        if (!empty($filtros['turma_id'])) {
            $where_clause .= " AND a.turma_id = :turma_id";
            $params['turma_id'] = $filtros['turma_id'];
        }

        $alunos = $this->db->fetchAll(
            "SELECT a.id, a.codigo_aluno, a.ra, a.nickname, a.nome, a.cpf, a.email, a.foto_url,
                    t.nome as turma_nome, YEAR(CURDATE()) as ano_letivo, a.created_at
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             {$where_clause}
             ORDER BY a.nome ASC",
            $params
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=alunos_' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['id', 'codigo_aluno', 'ra', 'nickname', 'nome', 'cpf', 'email', 'foto_url', 'turma', 'ano_turma', 'data_cadastro'], ';');

        foreach ($alunos as $aluno) {
            fputcsv($output, [
                $aluno['id'],
                $aluno['codigo_aluno'] ?? '',
                $aluno['ra'] ?? '',
                $aluno['nickname'] ?? '',
                $aluno['nome'] ?? '',
                $aluno['cpf'] ?? '',
                $aluno['email'] ?? '',
                $aluno['foto_url'] ?? '',
                $aluno['turma_nome'] ?? '',
                $aluno['ano_letivo'] ?? '',
                $aluno['created_at'] ?? ''
            ], ';');
        }
        fclose($output);
        exit;
    }

    public function exportarCensoCSV()
    {
        if (!$this->enforceAdminPermissionKey('alunos', 'visualizar', false)) {
            return;
        }

        $where = 'WHERE 1=1';
        $params = [];
        $unidadeId = (int) ($_GET['unidade_id'] ?? 0);

        $temUnidades = $this->db->fetch("SHOW TABLES LIKE 'unidades'") !== false;
        $temUnidadeCol = $this->colunaAlunoUnidadeExiste();

        $joinUnidade = '';
        $selectUnidade = "'' AS unidade_nome, '' AS unidade_inep, '' AS dependencia_administrativa";
        if ($temUnidades && $temUnidadeCol) {
            $colsUnidade = [];
            try {
                $rows = $this->db->fetchAll(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unidades'"
                );
                foreach ($rows as $r) {
                    $colsUnidade[(string) $r['COLUMN_NAME']] = true;
                }
            } catch (\Throwable $e) {
                $colsUnidade = [];
            }
            $joinUnidade = 'LEFT JOIN unidades u ON u.id = a.unidade_id';
            $selUnidadeNome = isset($colsUnidade['nome']) ? 'u.nome' : "''";
            $selUnidadeInep = isset($colsUnidade['inep']) ? 'u.inep' : "''";
            $selUnidadeDep = isset($colsUnidade['dependencia_administrativa']) ? 'u.dependencia_administrativa' : "''";
            $selectUnidade = "{$selUnidadeNome} AS unidade_nome, {$selUnidadeInep} AS unidade_inep, {$selUnidadeDep} AS dependencia_administrativa";
            if ($unidadeId > 0) {
                $where .= ' AND a.unidade_id = :unidade_id';
                $params['unidade_id'] = $unidadeId;
            }
        }

        $alunos = $this->db->fetchAll(
            "SELECT a.*, t.nome AS turma_nome, {$selectUnidade}
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             {$joinUnidade}
             {$where}
             ORDER BY a.nome ASC",
            $params
        );

        $this->auditarAluno('EXPORT_CENSO_CSV', null, [
            'unidade_id' => $unidadeId > 0 ? $unidadeId : null,
            'total_registros' => count($alunos),
        ]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=censo_inep_' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, [
            'codigo_inep_aluno', 'nome', 'nome_social', 'nome_mae', 'nome_pai',
            'data_nascimento', 'sexo', 'cor_raca', 'nacionalidade', 'naturalidade', 'uf_nascimento',
            'cpf', 'nis', 'certidao_nascimento',
            'logradouro', 'numero', 'bairro', 'municipio', 'uf', 'cep', 'zona',
            'unidade', 'unidade_inep', 'dependencia_administrativa', 'turma',
        ], ';');

        foreach ($alunos as $a) {
            fputcsv($output, [
                $a['codigo_inep'] ?? '',
                $a['nome'] ?? '',
                $a['nome_social'] ?? '',
                $a['nome_mae'] ?? '',
                $a['nome_pai'] ?? '',
                $a['data_nasc'] ?? '',
                $a['sexo'] ?? '',
                $a['cor_raca'] ?? '',
                $a['nacionalidade'] ?? '',
                $a['naturalidade'] ?? '',
                $a['uf_nascimento'] ?? '',
                $a['cpf'] ?? '',
                $a['nis'] ?? '',
                $a['certidao_nascimento'] ?? '',
                $a['logradouro'] ?? '',
                $a['numero'] ?? '',
                $a['bairro'] ?? '',
                $a['cidade'] ?? '',
                $a['uf'] ?? '',
                $a['cep'] ?? '',
                $a['zona'] ?? '',
                $a['unidade_nome'] ?? '',
                $a['unidade_inep'] ?? '',
                $a['dependencia_administrativa'] ?? '',
                $a['turma_nome'] ?? '',
            ], ';');
        }
        fclose($output);
        exit;
    }

    public function exportarHistoricoTurmasCSV()
    {
        $historico = $this->db->fetchAll(
            "SELECT h.aluno_id,
                    a.nome as aluno_nome,
                    a.nickname,
                    t.nome as turma_nome,
                    t.ano_letivo,
                    t.tipo_ensino,
                    h.data_inicio,
                    h.data_fim
             FROM alunos_turmas_historico h
             INNER JOIN alunos a ON a.id = h.aluno_id
             INNER JOIN turmas t ON t.id = h.turma_id
             ORDER BY a.nome ASC, h.data_inicio ASC"
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=\"historico_turmas_alunos_' . date('Y-m-d') . '.csv\"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['aluno_id', 'nickname', 'nome', 'turma', 'ano_turma', 'tipo_ensino', 'data_inicio', 'data_fim'], ';');

        foreach ($historico as $row) {
            fputcsv($output, [
                $row['aluno_id'],
                $row['nickname'] ?? '',
                $row['aluno_nome'] ?? '',
                $row['turma_nome'] ?? '',
                $row['ano_letivo'] ?? '',
                $row['tipo_ensino'] ?? '',
                $row['data_inicio'] ?? '',
                $row['data_fim'] ?? ''
            ], ';');
        }
        fclose($output);
        exit;
    }

    /**
     * Fragmento HTML da aba "Provas" do Detalhe do Aluno, carregado via AJAX
     * sob demanda (ver app/Views/admin/students/show.php, função JS
     * carregarAbaSobDemanda) em vez de ir junto na carga inicial da página.
     */
    public function provasTabFragment($id)
    {
        $user = $this->auth->getUser();
        if (!$user) {
            http_response_code(403);
            exit;
        }

        $id = (int) $id;
        $aluno = $this->db->fetch('SELECT id FROM alunos WHERE id = :id', ['id' => $id]);
        if (!$aluno) {
            http_response_code(404);
            exit;
        }

        require_once __DIR__ . '/../../Services/AdminStudentProfileService.php';
        $service = new \App\Services\AdminStudentProfileService($this->db, $this);
        $tabData = $service->getProvasTabData($id);

        extract($tabData);
        require __DIR__ . '/../../Views/admin/students/_tab_provas.php';
        exit;
    }

    /**
     * Histórico de auditoria do aluno — página própria (antes ficava embutido na
     * carga principal do perfil, rodando SHOW TABLES + SELECT em toda visita,
     * mesmo sem ninguém abrir essa seção).
     */
    public function auditoriaAluno($id)
    {
        $user = $this->auth->getUser();
        if (!$user) {
            $this->redirect('/admin/students');
            return;
        }

        $id = (int) $id;
        $aluno = $this->db->fetch('SELECT id, nome FROM alunos WHERE id = :id', ['id' => $id]);
        if (!$aluno) {
            $this->redirect('/admin/students');
            return;
        }

        require_once __DIR__ . '/../../Services/AdminStudentProfileService.php';
        $service = new \App\Services\AdminStudentProfileService($this->db, $this);

        $data = [
            'title' => 'Histórico de Auditoria - EducaTudo',
            'page_title' => 'Histórico de Auditoria',
            'current_page' => 'students',
            'student' => $aluno,
            'audit_logs' => $service->getAuditTrail($id),
        ];

        $this->viewWithLayout('admin', 'admin/students/auditoria', $data);
    }

    public function mostrarAluno($id)
    {
        $user = $this->auth->getUser();

        require_once __DIR__ . '/../../Services/AdminStudentProfileService.php';
        $service = new \App\Services\AdminStudentProfileService($this->db, $this);

        try {
            $data = $service->getStudentProfile((int) $id, $user ?? []);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 404) {
                $this->redirect('/admin/students');
                return;
            }
            $this->redirect('/admin/students');
            return;
        }

        $flash = $this->getFlashMessage();
        $data['title'] = 'Detalhes do Aluno - EducaTudo';
        $data['page_title'] = 'Detalhes do Aluno';
        $data['page_subtitle'] = 'Início > Alunos > Detalhes do Aluno';
        $data['flash_message'] = $flash['message'] ?? '';
        $data['flash_type'] = $flash['type'] ?? '';
        $data['current_page'] = 'students';
        $data['csrf_token'] = $this->generateCsrfToken();

        try {
            $this->viewWithLayout('admin', 'admin/students/show', $data);
        } catch (Throwable $e) {
            require_once __DIR__ . '/../Core/Logger.php';
            Logger::error("Erro fatal ao renderizar página do aluno {$id}: " . $e->getMessage(), [
                'exception' => $e,
                'aluno_id' => $id,
                'aluno_nome' => $data['student']['nome'] ?? 'N/A'
            ], 'general');

            // Tentar renderizar uma página de erro simples
            http_response_code(500);
            echo "<!DOCTYPE html><html><head><title>Erro</title></head><body>";
            echo "<h1>Erro ao carregar página do aluno</h1>";
            echo "<p>Ocorreu um erro ao carregar os detalhes do aluno. Por favor, tente novamente.</p>";
            if (DEBUG) {
                echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
            }
            echo "</body></html>";
        }
    }

    public function acessarComoAluno($id)
    {
        if (!$this->enforceAdminPermissionKey('acao_rapida_acessar_aluno', 'visualizar', false)) {
            return;
        }
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $_SESSION['error_message'] = 'Acesso não autorizado';
            $this->redirect('/admin/students');
        }

        $id = (int) $id;
        if ($id < 1) {
            $_SESSION['error_message'] = 'Aluno inválido';
            $this->redirect('/admin/students');
        }

        $aluno = $this->db->fetch(
            "SELECT a.id, a.nome, a.ativo
             FROM alunos a
             WHERE a.id = ? LIMIT 1",
            [$id]
        );

        if (!$aluno) {
            $_SESSION['error_message'] = 'Aluno não encontrado';
            $this->redirect('/admin/students');
        }

        if ((int) ($aluno['ativo'] ?? 0) !== 1) {
            $_SESSION['error_message'] = 'Só é possível acessar como alunos ativos';
            $this->redirect('/admin/students/' . $id);
        }

        $secret = $this->config['security']['entra_como_secret'] ?? '';
        if ($secret === '') {
            $_SESSION['error_message'] = 'Não foi possível gerar o acesso do aluno. Verifique a configuração de segurança.';
            $this->redirect('/admin/students/' . $id);
        }

        $payload = json_encode([
            'escola_id' => defined('TENANT_ID') ? (int) TENANT_ID : 0,
            'tipo' => 'aluno',
            'user_id' => $id,
            'exp' => time() + 300,
        ]);

        if ($payload === false) {
            $_SESSION['error_message'] = 'Não foi possível gerar o acesso do aluno.';
            $this->redirect('/admin/students/' . $id);
        }

        $sig = hash_hmac('sha256', $payload, $secret, true);
        $token = strtr(base64_encode($payload), '+/', '-_') . '.' . strtr(base64_encode($sig), '+/', '-_');

        header('Location: ' . URL . '/auth/entrar-como?token=' . rawurlencode($token));
        exit;
    }

    public function acessarComoPai($id)
    {
        if (!$this->enforceAdminPermissionKey('acao_rapida_acessar_pai', 'visualizar', false)) {
            return;
        }
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $_SESSION['error_message'] = 'Acesso não autorizado';
            $this->redirect('/admin/students');
        }

        $alunoId = (int) $id;
        $responsavelId = (int) ($_GET['responsavel_id'] ?? 0);

        if ($alunoId < 1 || $responsavelId < 1) {
            $_SESSION['error_message'] = 'Aluno ou responsável inválido';
            $this->redirect('/admin/students/' . max(0, $alunoId));
        }

        $aluno = $this->db->fetch(
            "SELECT a.id, a.nome, a.ativo
             FROM alunos a
             WHERE a.id = ? LIMIT 1",
            [$alunoId]
        );

        if (!$aluno) {
            $_SESSION['error_message'] = 'Aluno não encontrado';
            $this->redirect('/admin/students');
        }

        if ((int) ($aluno['ativo'] ?? 0) !== 1) {
            $_SESSION['error_message'] = 'Só é possível acessar responsáveis de alunos ativos';
            $this->redirect('/admin/students/' . $alunoId);
        }

        $responsavel = $this->db->fetch(
            "SELECT r.id, r.nome, r.email, r.ativo
               FROM responsaveis r
              WHERE r.id = :responsavel_id
                AND r.ativo = 1
                AND (
                    EXISTS (
                        SELECT 1
                          FROM alunos_responsaveis ar
                         WHERE ar.aluno_id = :aluno_id
                           AND ar.responsavel_id = r.id
                           AND ar.ativo = 1
                    )
                    OR EXISTS (
                        SELECT 1
                          FROM alunos a
                         WHERE a.id = :aluno_id_legacy
                           AND a.responsavel_id = r.id
                    )
                )
              LIMIT 1",
            [
                'responsavel_id' => $responsavelId,
                'aluno_id' => $alunoId,
                'aluno_id_legacy' => $alunoId,
            ]
        );

        if (!$responsavel) {
            $_SESSION['error_message'] = 'Responsável não encontrado ou não vinculado a este aluno';
            $this->redirect('/admin/students/' . $alunoId);
        }

        $secret = $this->config['security']['entra_como_secret'] ?? '';
        if ($secret === '') {
            $_SESSION['error_message'] = 'Não foi possível gerar o acesso do responsável. Verifique a configuração de segurança.';
            $this->redirect('/admin/students/' . $alunoId);
        }

        $payload = json_encode([
            'escola_id' => defined('TENANT_ID') ? (int) TENANT_ID : 0,
            'tipo' => 'pai',
            'user_id' => $responsavelId,
            'exp' => time() + 300,
        ]);

        if ($payload === false) {
            $_SESSION['error_message'] = 'Não foi possível gerar o acesso do responsável.';
            $this->redirect('/admin/students/' . $alunoId);
        }

        $sig = hash_hmac('sha256', $payload, $secret, true);
        $token = strtr(base64_encode($payload), '+/', '-_') . '.' . strtr(base64_encode($sig), '+/', '-_');

        header('Location: ' . URL . '/auth/entrar-como?token=' . rawurlencode($token));
        exit;
    }

    public function excluirListaExercicioIA()
    {
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $_SESSION['error_message'] = 'Acesso não autorizado';
            $this->redirect('/admin/students');
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $_SESSION['error_message'] = 'Token inválido';
            $this->redirect('/admin/students');
            return;
        }
        $listaId = (int) ($_POST['lista_id'] ?? 0);
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        if ($listaId <= 0 || $alunoId <= 0) {
            $_SESSION['error_message'] = 'Dados inválidos';
            $this->redirect($alunoId > 0 ? '/admin/students/' . $alunoId : '/admin/students');
            return;
        }
        $lista = $this->db->fetch(
            "SELECT id FROM listas_personalizadas_exercicios WHERE id = :id AND aluno_id = :aluno_id",
            ['id' => $listaId, 'aluno_id' => $alunoId]
        );
        if (!$lista) {
            $_SESSION['error_message'] = 'Lista não encontrada ou não pertence a este aluno';
            $this->redirect('/admin/students/' . $alunoId);
            return;
        }
        try {
            $sessoes = $this->db->fetchAll("SELECT id FROM listas_personalizadas_sessoes WHERE lista_id = :lista_id", ['lista_id' => $listaId]);
            $sessaoIds = array_column($sessoes, 'id');
            if (!empty($sessaoIds)) {
                $placeholders = implode(',', array_fill(0, count($sessaoIds), '?'));
                $this->db->delete("DELETE FROM listas_personalizadas_respostas WHERE sessao_id IN ($placeholders)", $sessaoIds);
            }
            $this->db->delete("DELETE FROM listas_personalizadas_sessoes WHERE lista_id = ?", [$listaId]);
            $this->db->delete("DELETE FROM questoes_personalizadas WHERE lista_id = ?", [$listaId]);
            $this->db->delete("DELETE FROM listas_personalizadas_exercicios WHERE id = ?", [$listaId]);
            $_SESSION['success_message'] = 'Lista de exercícios excluída com sucesso.';
        } catch (Throwable $e) {
            $_SESSION['error_message'] = 'Erro ao excluir lista.';
        }
        $this->redirect('/admin/students/' . $alunoId);
    }

    public function verDetalhesExercicioIA($sessaoId)
    {
        $user = $this->auth->getUser();
        
        if (!$user || $user['tipo'] !== 'admin') {
            $_SESSION['error_message'] = 'Acesso não autorizado';
            $this->redirect('/admin/students');
            return;
        }
        
        // Buscar sessão do exercício
        $sessao = $this->db->fetch(
            "SELECT sep.*, lep.titulo as lista_titulo, lep.materia, lep.quantidade_exercicios,
                    a.nome as aluno_nome, a.ra as aluno_ra, a.id as aluno_id
             FROM listas_personalizadas_sessoes sep
             LEFT JOIN listas_personalizadas_exercicios lep ON sep.lista_id = lep.id
             LEFT JOIN alunos a ON sep.aluno_id = a.id
             WHERE sep.id = :sessao_id",
            ['sessao_id' => $sessaoId]
        );
        
        if (!$sessao) {
            $_SESSION['error_message'] = 'Exercício não encontrado';
            $this->redirect('/admin/students');
            return;
        }
        
        // Buscar todas as questões com as respostas do aluno
        $questoes = $this->db->fetchAll(
            "SELECT qp.*, rep.resposta as resposta_escolhida, rep.is_correct, rep.answered_at
             FROM questoes_personalizadas qp
             LEFT JOIN listas_personalizadas_respostas rep ON qp.id = rep.questao_id AND rep.sessao_id = :sessao_id
             WHERE qp.lista_id = :lista_id
             ORDER BY qp.ordem ASC",
            ['sessao_id' => $sessaoId, 'lista_id' => $sessao['lista_id']]
        );
        
        $data = [
            'title' => 'Detalhes do Exercício IA - EducaTudo',
            'sessao' => $sessao,
            'questoes' => $questoes,
            'user' => $user,
            'current_page' => 'students'
        ];
        
        $this->viewWithLayout('admin', 'admin/students/exercicio-ia-detalhes', $data);
    }

    public function updateStudentPassword($id)
    {
        if (!$this->enforceAdminPermissionKey('acao_rapida_resetar_senha', 'alterar', true)) {
            return;
        }
        // Limpar buffer de saída para evitar HTML antes do JSON
        ob_clean();
        
        // Desabilitar exibição de erros para evitar HTML na resposta
        error_reporting(0);
        ini_set('display_errors', 0);
        
        try {
            // Verifica CSRF token
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                error_log('CSRF token inválido para alteração de senha do aluno: ' . $id);
                $this->json(['error' => 'Token inválido'], 400);
            }
            
            $user = $this->auth->getUser();
            error_log('Usuário logado: ' . json_encode($user));
            
            if (!$user || $user['tipo'] !== 'admin') {
                error_log('Usuário não autorizado: ' . json_encode($user));
                throw new Exception('Acesso não autorizado');
            }
            
            // Verifica se o aluno existe
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :aluno_id",
                ['aluno_id' => $id]
            );
            
            if (!$aluno) {
                error_log('Aluno não encontrado: ' . $id);
                throw new Exception('Aluno não encontrado');
            }
            
            error_log('Aluno encontrado: ' . $aluno['nome'] . ' (ID: ' . $aluno['id'] . ')');
            
            // Define senha padrão
            $senha_padrao = '123456';
            $senha_hash = password_hash($senha_padrao, PASSWORD_DEFAULT);
            
            // Log do hash gerado para debug
            error_log('Senha hash gerada (primeiros 50 chars): ' . substr($senha_hash, 0, 50));
            error_log('Tamanho do hash: ' . strlen($senha_hash));
            
            // Verificar se o hash foi gerado corretamente
            if (!password_verify($senha_padrao, $senha_hash)) {
                error_log('ERRO: Hash gerado não é válido para verificação!');
                throw new Exception('Erro ao gerar hash da senha');
            }
            
            // Atualiza senha do aluno e marca como já fez primeiro acesso (pode entrar com nickname e senha)
            $result = $this->db->update(
                "UPDATE alunos SET senha_hash = :senha, primeiro_acesso = 0 WHERE id = :aluno_id",
                ['senha' => $senha_hash, 'aluno_id' => $aluno['id']]
            );
            
            if ($result === 0) {
                error_log('Nenhuma linha foi atualizada na tabela alunos para ID: ' . $aluno['id']);
                error_log('Hash usado: ' . substr($senha_hash, 0, 30) . '...');
                throw new Exception('Erro ao atualizar senha no banco de dados');
            }
            
            // Verificar se a senha foi realmente atualizada
            $aluno_verificado = $this->db->fetch(
                "SELECT senha_hash FROM alunos WHERE id = :aluno_id",
                ['aluno_id' => $aluno['id']]
            );
            
            if (!$aluno_verificado) {
                error_log('Erro: Não foi possível recuperar aluno após atualização');
                throw new Exception('Erro ao verificar senha atualizada');
            }
            
            // Testar se a senha salva funciona
            if (!password_verify($senha_padrao, $aluno_verificado['senha_hash'])) {
                error_log('ERRO CRÍTICO: Senha salva no banco não funciona com password_verify!');
                error_log('Hash salvo (primeiros 50 chars): ' . substr($aluno_verificado['senha_hash'], 0, 50));
                throw new Exception('Senha não foi salva corretamente no banco de dados');
            }
            
            error_log('Senha atualizada e verificada com sucesso para aluno: ' . $aluno['id']);
            
            $this->json([
                'success' => true, 
                'message' => 'Senha alterada para padrão com sucesso!',
                'senha_padrao' => $senha_padrao,
                'aluno_nome' => $aluno['nome']
            ]);
            
        } catch (Exception $e) {
            error_log('Erro em updateStudentPassword: ' . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log('Stack trace: ' . $e->getTraceAsString());
                }
            }
            
            // Garantir que não há saída antes do JSON
            ob_clean();
            
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function gerarAnaliseTudinha($id)
    {
        if (!$this->enforceAdminPermissionKey('acao_rapida_analise_tudinha', 'alterar', true)) {
            return;
        }
        ob_clean();
        error_reporting(0);
        ini_set('display_errors', 0);

        try {
            // Verifica CSRF token
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }

            $user = $this->auth->getUser();
            if (!$user || ($user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola')) {
                throw new Exception('Acesso não autorizado');
            }

            $dataAte = $_POST['data_ate'] ?? date('Y-m-d');

            // Validação rápida de existência do aluno antes de enfileirar o job
            $aluno = $this->db->fetch("SELECT id FROM alunos WHERE id = :id", ['id' => $id]);
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            require_once __DIR__ . '/../../Services/AIJobService.php';
            $jobId = \App\Services\AIJobService::enqueue(
                'gerar_analise_tudinha',
                [
                    'aluno_id' => (int) $id,
                    'data_ate' => $dataAte,
                    'user_id'  => $user['id'],
                ],
                $user['id'] ?? null,
                $user['tipo'] ?? 'admin'
            );

            $this->json(['job_id' => $jobId]);
        } catch (Exception $e) {
            error_log('Erro ao gerar análise Tudinha: ' . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function criarAluno()
    {
        $user = $this->auth->getUser();
        
        // Busca turmas e pais para os selects
        $turmas = $this->db->fetchAll("SELECT * FROM turmas WHERE ativo = 1 ORDER BY nome ASC");
        $pais = $this->db->fetchAll(
            "SELECT * FROM responsaveis WHERE ativo = 1 ORDER BY nome ASC"
        );
        
        $data = [
            'title' => 'Cadastrar Aluno - EducaTudo',
            'classes' => $turmas,
            'parents' => $pais,
            'units' => $this->listarUnidadesAtivas(),
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'students'
        ];
        
        $this->viewWithLayout('admin', 'admin/students/create', $data);
    }

    public function salvarAluno()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $nome = trim($_POST['nome'] ?? '');
            $ra = trim($_POST['ra'] ?? '');
            $codigoAluno = trim($_POST['codigo_aluno'] ?? '');
            $nickname = trim($_POST['nickname'] ?? '');
            $docEndereco = $this->extrairDocumentoEnderecoPost();
            $contato = $this->extrairContatoPost();
            $civil = $this->extrairIdentificacaoCivilPost();
            $email = $contato['email'] ?? null;
            $senha = $_POST['senha'] ?? '';
            $turma_id = null;
            $data_nasc = $docEndereco['data_nasc'];
            $responsavel_id = null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            // Validações
            if (empty($nome)) {
                throw new Exception('Nome é obrigatório');
            }

            if (empty($senha)) {
                $senha = null;
            }
            
            $serie = 'Não informada';
            
            $studentModel = new Student();
            
            $codigoUnico = $codigoAluno !== '' ? $codigoAluno : $ra;

            // Verifica se RA/código já existe
            if (!empty($codigoUnico) && $studentModel->raExists($codigoUnico)) {
                throw new Exception('Código do aluno já cadastrado');
            }

            if ($nickname !== '' && $studentModel->nicknameExists($nickname)) {
                throw new Exception('Apelido (nickname) já está em uso por outro aluno');
            }
            
            // Cria aluno usando o modelo
            $pagante = isset($_POST['pagante']) ? 1 : 1; // Padrão: pagante
            $sexo = $this->normalizarSexoAluno($_POST['sexo'] ?? null);
            $createPayload = [
                'nome' => $nome,
                'email' => $email ?: null,
                'senha' => $senha ?: null,
                'ra' => $codigoUnico ?: null,
                'codigo_aluno' => $codigoUnico ?: null,
                'cpf' => $docEndereco['cpf'],
                'foto_url' => null,
                'nickname' => $nickname ?: null,
                'turma_id' => $turma_id,
                'serie' => $serie,
                'data_nasc' => $data_nasc ?: null,
                'responsavel_id' => $responsavel_id ?: null,
                'ativo' => $ativo,
                'pagante' => $pagante
            ];
            if ($this->colunaAlunoEnderecoExiste()) {
                $createPayload['rg'] = $docEndereco['rg'];
                $createPayload['logradouro'] = $docEndereco['logradouro'];
                $createPayload['numero'] = $docEndereco['numero'];
                $createPayload['complemento'] = $docEndereco['complemento'];
                $createPayload['bairro'] = $docEndereco['bairro'];
                $createPayload['cidade'] = $docEndereco['cidade'];
                $createPayload['uf'] = $docEndereco['uf'];
                $createPayload['cep'] = $docEndereco['cep'];
            }
            if ($this->colunaAlunoContatoExiste()) {
                $createPayload['telefone'] = $contato['telefone'];
                $createPayload['celular'] = $contato['celular'];
            }
            if ($this->colunaAlunoUnidadeExiste()) {
                $createPayload['unidade_id'] = $this->unidadeIdFromPost();
            }
            if ($this->colunaAlunoCadastroCompletoExiste()) {
                foreach ($civil as $campo => $valor) {
                    $createPayload[$campo] = $valor;
                }
                $createPayload['whatsapp'] = $contato['whatsapp'] ?? null;
                $createPayload['email_secundario'] = $contato['email_secundario'] ?? null;
            }
            $alunoId = $studentModel->create($createPayload);

            if ($sexo !== null && $this->colunaAlunoSexoExiste()) {
                $this->db->query('UPDATE alunos SET sexo = :sexo WHERE id = :id', ['sexo' => $sexo, 'id' => $alunoId]);
            }

            $this->salvarFichaComplementarPost((int) $alunoId);

            $this->syncAlunoStatusMatricula((int) $alunoId);

            $this->auditarAluno('CREATE_STUDENT', (int) $alunoId, [
                'nome' => $createPayload['nome'] ?? null,
            ]);

            $this->json(['success' => true, 'message' => 'Aluno cadastrado com sucesso', 'id' => $alunoId]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function editarAluno($id)
    {
        if (!$this->enforceAdminPermissionKey('acao_rapida_editar_aluno', 'visualizar', false)) {
            return;
        }
        $user = $this->auth->getUser();
        
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome,
                    COALESCE(
                        (SELECT GROUP_CONCAT(DISTINCT r2.nome ORDER BY r2.nome SEPARATOR ', ')
                         FROM alunos_responsaveis ar2
                         INNER JOIN responsaveis r2 ON r2.id = ar2.responsavel_id
                         WHERE ar2.aluno_id = a.id AND ar2.ativo = 1),
                        p.nome
                    ) as responsavel_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             LEFT JOIN responsaveis p ON a.responsavel_id = p.id
             WHERE a.id = :id",
            ['id' => $id]
        );
        
        if (!$aluno) {
            $this->redirect('/admin/students');
        }

        require_once __DIR__ . '/../../Helpers/StudentPhotoHelper.php';
        $aluno = StudentPhotoHelper::enrichStudent($aluno, false);

        if (!class_exists('AdminPermissionMatrix')) {
            require_once __DIR__ . '/../../Core/AdminPermissionMatrix.php';
        }
        $adminPermissions = AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);
        
        $turmas = $this->db->fetchAll("SELECT * FROM turmas WHERE ativo = 1 ORDER BY nome ASC");
        $pais = $this->db->fetchAll(
            "SELECT * FROM responsaveis WHERE ativo = 1 ORDER BY nome ASC"
        );
        
        $data = [
            'title' => 'Editar Aluno - EducaTudo',
            'student' => $aluno,
            'classes' => $turmas,
            'parents' => $pais,
            'units' => $this->listarUnidadesAtivas(),
            'user' => $user,
            'admin_permissions' => $adminPermissions,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'students',
            'prompt_foto' => isset($_GET['foto']) && (string) $_GET['foto'] === '1',
            'ficha' => $this->carregarFichaComplementar((int) $id),
        ];
        
        $this->viewWithLayout('admin', 'admin/students/edit', $data);
    }

    public function atualizarAluno($id)
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $nome = trim($_POST['nome'] ?? '');
            $nickname = trim($_POST['nickname'] ?? '');
            $ra = trim($_POST['ra'] ?? '');
            $codigoAluno = trim($_POST['codigo_aluno'] ?? '');
            $docEndereco = $this->extrairDocumentoEnderecoPost();
            $contato = $this->extrairContatoPost();
            $senha = $_POST['senha'] ?? '';
            $data_nasc = $docEndereco['data_nasc'];
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $pagante = isset($_POST['pagante']) ? 1 : 0;
            $primeiro_acesso = isset($_POST['primeiro_acesso']) ? (int)$_POST['primeiro_acesso'] : 1;
            if ($primeiro_acesso !== 0 && $primeiro_acesso !== 1) {
                $primeiro_acesso = 1;
            }
            
            // Validações
            if (empty($nome)) {
                throw new Exception('Nome é obrigatório');
            }
            
            $studentModel = new Student();

            $alunoAtual = $studentModel->findById($id);
            if (!$alunoAtual) {
                throw new Exception('Aluno não encontrado');
            }

            $email = array_key_exists('email', $_POST)
                ? ($contato['email'] ?? null)
                : ($alunoAtual['email'] ?? null);
            $civil = $this->extrairIdentificacaoCivilPost();
            $turma_id = $alunoAtual['turma_id'] ?? null;
            $responsavel_id = $alunoAtual['responsavel_id'] ?? null;
            $serie = $alunoAtual['serie'] ?? 'Não informada';
            
            $codigoUnico = $codigoAluno !== '' ? $codigoAluno : $ra;

            // Verifica se código já existe (exceto para o próprio aluno)
            if (!empty($codigoUnico) && $studentModel->raExists($codigoUnico, $id)) {
                throw new Exception('Código do aluno já cadastrado');
            }
            
            // Verifica se nickname já existe para outro aluno
            if ($nickname !== '' && $studentModel->nicknameExists($nickname, $id)) {
                throw new Exception('Apelido (nickname) já está em uso por outro aluno');
            }
            
            // Só exige fluxo de inativação quando o usuário tenta desmarcar "Aluno ativo" (1 → 0)
            if (isset($alunoAtual['ativo']) && (int)$alunoAtual['ativo'] === 1 && (int)$ativo === 0) {
                throw new Exception('Alteração de status deve ser feita pelo fluxo de inativação');
            }
            
            // Atualiza aluno usando o modelo
            $sexo = $this->normalizarSexoAluno($_POST['sexo'] ?? null);
            $updateData = [
                'nome' => $nome,
                'nickname' => $nickname ?: null,
                'email' => $email ?: null,
                'senha' => $senha ?: null,
                'ra' => $codigoUnico ?: null,
                'codigo_aluno' => $codigoUnico ?: null,
                'cpf' => $docEndereco['cpf'],
                'foto_url' => $alunoAtual['foto_url'] ?? null,
                'turma_id' => $turma_id,
                'serie' => $serie,
                'data_nasc' => $data_nasc ?: null,
                'responsavel_id' => $responsavel_id ?: null,
                'ativo' => $ativo,
                'pagante' => $pagante,
                'primeiro_acesso' => $primeiro_acesso
            ];
            if ($this->colunaAlunoSexoExiste()) {
                $updateData['sexo'] = $sexo;
            }
            if ($this->colunaAlunoEnderecoExiste()) {
                $updateData['rg'] = $docEndereco['rg'];
                $updateData['logradouro'] = $docEndereco['logradouro'];
                $updateData['numero'] = $docEndereco['numero'];
                $updateData['complemento'] = $docEndereco['complemento'];
                $updateData['bairro'] = $docEndereco['bairro'];
                $updateData['cidade'] = $docEndereco['cidade'];
                $updateData['uf'] = $docEndereco['uf'];
                $updateData['cep'] = $docEndereco['cep'];
            }
            if ($this->colunaAlunoContatoExiste()) {
                $updateData['telefone'] = $contato['telefone'];
                $updateData['celular'] = $contato['celular'];
            }
            if ($this->colunaAlunoUnidadeExiste() && array_key_exists('unidade_id', $_POST)) {
                $updateData['unidade_id'] = $this->unidadeIdFromPost();
            }
            if ($this->colunaAlunoCadastroCompletoExiste()) {
                foreach ($civil as $campo => $valor) {
                    $updateData[$campo] = $valor;
                }
                $updateData['whatsapp'] = $contato['whatsapp'] ?? null;
                $updateData['email_secundario'] = $contato['email_secundario'] ?? null;
            }
            $studentModel->update($id, $updateData);

            $this->salvarFichaComplementarPost((int) $id);

            $this->syncAlunoStatusMatricula((int) $id);

            $this->auditarAluno('UPDATE_STUDENT', (int) $id, [
                'campos' => array_keys($updateData),
            ]);

            $this->json(['success' => true, 'message' => 'Aluno atualizado com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function uploadFotoAluno($id)
    {
        if (!$this->enforceAdminPermissionKey('alunos', 'alterar', true)) {
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        $id = (int) $id;
        if ($id < 1) {
            $this->json(['error' => 'Aluno inválido'], 400);
            return;
        }

        try {
            $aluno = $this->db->fetch('SELECT id, foto_url FROM alunos WHERE id = :id', ['id' => $id]);
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload da imagem');
            }

            $file = $_FILES['foto'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExtension, $allowedExtensions, true)) {
                throw new Exception('Tipo de arquivo não permitido. Use: ' . implode(', ', $allowedExtensions));
            }

            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande. Máximo 2MB');
            }

            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new Exception('Arquivo não é uma imagem válida');
            }

            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $media = new MediaStorageService($this->config);

            $fileName = 'aluno_' . $id . '_' . time() . '.' . $fileExtension;
            $contentType = $imageInfo['mime'] ?? ($file['type'] ?? 'application/octet-stream');
            if (!$media->put('avatars', $fileName, $file['tmp_name'], $contentType)) {
                throw new Exception('Erro ao salvar arquivo');
            }

            if (!empty($aluno['foto_url'])) {
                $this->removerFotoAlunoStorage($media, $aluno['foto_url']);
            }

            require_once __DIR__ . '/../../Helpers/AvatarUrlHelper.php';
            $slug = trim((string) ($this->config['tenant']['slug'] ?? $this->config['school']['code'] ?? ''));
            $storedPath = AvatarUrlHelper::buildStoredAvatarPath($fileName, $slug !== '' ? $slug : null);
            $displayUrl = AvatarUrlHelper::normalizeAdminAvatarUrl($storedPath) ?? $storedPath;

            $this->db->query(
                'UPDATE alunos SET foto_url = ? WHERE id = ?',
                [$storedPath, $id]
            );

            $this->json([
                'success' => true,
                'message' => 'Foto enviada com sucesso!',
                'url' => $displayUrl,
            ]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function excluirAluno($id)
    {
        if (!$this->enforceAdminPermissionKey('acao_rapida_excluir_aluno', 'excluir', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $password = trim((string) ($_POST['password'] ?? ''));
            if ($password === '') {
                throw new Exception('Senha obrigatória para excluir o aluno');
            }

            $confirmText = strtoupper(trim((string) ($_POST['confirm_text'] ?? '')));
            if ($confirmText !== 'CONFIRMAR') {
                throw new Exception('Digite CONFIRMAR para confirmar a exclusão');
            }

            $user = $this->auth->getUser();
            $observation = trim((string) ($_POST['observation'] ?? ''));
            if ($observation === '') {
                $observation = 'Exclusão administrativa — aluno ocultado da visualização';
            }

            // Soft-delete: inativa (ativo=0) sem apagar do banco
            $service = new \App\Services\StudentStatusService();
            $result = $service->inactivate((int) $id, [
                'password' => $password,
                'reason' => 'ADMINISTRATIVO',
                'observation' => $observation,
                'confirm_text' => 'CONFIRMAR',
                'confirm' => '1',
            ], $user);

            $this->auditarAluno('SOFT_DELETE_STUDENT', (int) $id, [
                'old_status' => $result['old_status'] ?? null,
                'new_status' => $result['new_status'] ?? null,
            ]);

            $this->json([
                'success' => true,
                'message' => 'Aluno excluído da visualização. Os dados foram preservados no banco.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function adicionarMatricula($aluno_id)
    {
        if (!$this->enforceAdminPermissionKey('matriculas_aluno', 'cadastrar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);
            return;
        }
        try {
            $turma_id = (int)($_POST['turma_id'] ?? 0);
            $ano_letivo_id = (int)($_POST['ano_letivo_id'] ?? 0);
            $data_entrada = trim($_POST['data_entrada'] ?? '') ?: date('Y-m-d');
            $definirPrincipal = isset($_POST['definir_turma_principal']) && $_POST['definir_turma_principal'] !== '0';
            if ($turma_id <= 0 || $ano_letivo_id <= 0) {
                throw new Exception('Selecione turma e ano letivo.');
            }
            require_once __DIR__ . '/../../Services/AlunoMovimentacaoService.php';
            $movimentacao = new \App\Services\AlunoMovimentacaoService();
            $movimentacao->vincularAlunoTurma((int) $aluno_id, $turma_id, $ano_letivo_id, $definirPrincipal, $data_entrada);
            $this->syncAlunoStatusMatricula((int) $aluno_id);
            $this->json(['success' => true, 'message' => 'Matrícula adicionada.']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function encerrarMatricula($aluno_id, $matricula_id)
    {
        $alunoId = (int) $aluno_id;
        $redirectUrl = '/admin/students/' . $alunoId . '#section-matriculas-aluno';
        $ajax = $this->isAjaxRequest();

        if (!$this->enforceAdminPermissionKey('matriculas_aluno', 'alterar', $ajax)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            if ($ajax) {
                $this->json(['error' => 'Token inválido.'], 400);
                return;
            }
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect($redirectUrl);
            return;
        }
        try {
            $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
            if (!$hasMatricula) {
                throw new Exception('Estrutura de matrículas não disponível.');
            }
            $m = $this->db->fetch("SELECT id FROM matricula WHERE id = :id AND aluno_id = :aluno_id", ['id' => $matricula_id, 'aluno_id' => $aluno_id]);
            if (!$m) {
                throw new Exception('Matrícula não encontrada.');
            }
            $data_saida = trim($_POST['data_saida'] ?? '') ?: date('Y-m-d');
            $statusEncerramento = MatriculaSchemaHelper::statusEncerramentoManual();
            $this->db->update(
                "UPDATE matricula SET data_saida = :data_saida, status = :status, updated_at = NOW() WHERE id = :id",
                ['data_saida' => $data_saida, 'status' => $statusEncerramento, 'id' => $matricula_id]
            );
            $this->syncAlunoStatusMatricula($alunoId);
            if ($ajax) {
                $this->json(['success' => true, 'message' => 'Matrícula encerrada.']);
                return;
            }
            $this->setFlashMessage('Matrícula encerrada.', 'success');
            $this->redirect($redirectUrl);
        } catch (Exception $e) {
            if ($ajax) {
                $this->json(['error' => $e->getMessage()], 400);
                return;
            }
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect($redirectUrl);
        }
    }

    public function sincronizarMatriculaComTurmaCadastro($aluno_id)
    {
        if (!$this->enforceAdminPermissionKey('matriculas_aluno', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido.'], 400);

            return;
        }
        try {
            $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
            if (!$hasMatricula) {
                throw new Exception('Estrutura de matrículas não disponível.');
            }
            $aluno = $this->db->fetch(
                'SELECT id, turma_id FROM alunos WHERE id = :id',
                ['id' => $aluno_id]
            );
            if (!$aluno) {
                throw new Exception('Aluno não encontrado.');
            }
            $tid = (int) ($aluno['turma_id'] ?? 0);
            if ($tid <= 0) {
                throw new Exception('Defina a turma do aluno no cadastro (Editar) antes de sincronizar.');
            }
            $ativas = $this->db->fetchAll(
                "SELECT id, turma_id, ano_letivo_id FROM matricula
                 WHERE aluno_id = :aid AND status = 'ativa' AND data_saida IS NULL",
                ['aid' => $aluno_id]
            ) ?: [];
            require_once __DIR__ . '/../../Services/AlunoTurmaResolver.php';
            $turmaResolver = new \App\Services\AlunoTurmaResolver();
            $wrong = [];
            foreach ($ativas as $row) {
                $turmaMatriculaId = (int) ($row['turma_id'] ?? 0);
                if ($turmaMatriculaId === $tid) {
                    continue;
                }
                if ($turmaResolver->isCursoExtraTurma($turmaMatriculaId)) {
                    continue;
                }
                $wrong[] = $row;
            }
            if ($wrong === []) {
                $this->json(['success' => true, 'message' => 'Matrículas já estão alinhadas com a turma do cadastro.']);

                return;
            }
            if (count($ativas) > 1 && count($wrong) > 1) {
                throw new Exception('Há várias matrículas ativas fora da turma do cadastro. Encerre manualmente as que não se aplicam e adicione a correta.');
            }
            $anoParaNova = 0;
            foreach ($wrong as $w) {
                if ($anoParaNova <= 0) {
                    $anoParaNova = (int) $w['ano_letivo_id'];
                }
                $this->db->query(
                    'UPDATE matricula SET data_saida = CURDATE(), status = \'transferido\', updated_at = NOW() WHERE id = :id',
                    ['id' => $w['id']]
                );
            }
            $tem = $this->db->fetch(
                "SELECT id FROM matricula WHERE aluno_id = :aid AND turma_id = :tid AND status = 'ativa' AND data_saida IS NULL LIMIT 1",
                ['aid' => $aluno_id, 'tid' => $tid]
            );
            if ($tem === false) {
                if ($anoParaNova <= 0) {
                    $anoParaNova = $this->resolverAnoLetivoIdParaTurma($tid);
                }
                if ($anoParaNova > 0) {
                    $this->upsertMatriculaAtiva((int) $aluno_id, $tid, $anoParaNova);
                }
            }
            $wrongTidsMigrar = [];
            foreach ($wrong as $w) {
                $wt = (int) ($w['turma_id'] ?? 0);
                if ($wt > 0 && $wt !== $tid) {
                    $wrongTidsMigrar[$wt] = true;
                }
            }
            if ($wrongTidsMigrar !== []) {
                require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';
                $gradeModel = new ExamBlockManualGrade();
                foreach (array_keys($wrongTidsMigrar) as $wt) {
                    $gradeModel->migrarTurmaIdEmNotasLancadasParaAluno((int) $aluno_id, (int) $wt, $tid);
                }
            }
            $this->json(['success' => true, 'message' => 'Matrículas alinhadas com a turma do cadastro.']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    private function reconcileMatriculaAposAtualizarAluno(int $alunoId, int $turmaNovaId, ?int $turmaAntigaId, bool $mudouDeTurma): void
    {
        try {
            $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
            if ($hasMatricula === false || $alunoId <= 0 || $turmaNovaId <= 0) {
                return;
            }
            $fetchAtivas = function () use ($alunoId) {
                return $this->db->fetchAll(
                    "SELECT id, turma_id, ano_letivo_id FROM matricula
                     WHERE aluno_id = :aid AND status = 'ativa' AND data_saida IS NULL",
                    ['aid' => $alunoId]
                ) ?: [];
            };
            $anoParaNova = 0;
            if ($mudouDeTurma && $turmaAntigaId !== null && $turmaAntigaId > 0) {
                foreach ($fetchAtivas() as $row) {
                    if ((int) $row['turma_id'] === $turmaAntigaId) {
                        if ($anoParaNova <= 0) {
                            $anoParaNova = (int) $row['ano_letivo_id'];
                        }
                        $this->db->query(
                            "UPDATE matricula SET data_saida = CURDATE(), status = 'transferido', updated_at = NOW() WHERE id = :id",
                            ['id' => $row['id']]
                        );
                    }
                }
            }
            $ativas = $fetchAtivas();
            $turmaDriftAntiga = null;
            if (count($ativas) === 1 && (int) $ativas[0]['turma_id'] !== $turmaNovaId) {
                $turmaDriftAntiga = (int) $ativas[0]['turma_id'];
                if ($anoParaNova <= 0) {
                    $anoParaNova = (int) $ativas[0]['ano_letivo_id'];
                }
                $this->db->query(
                    "UPDATE matricula SET data_saida = CURDATE(), status = 'transferido', updated_at = NOW() WHERE id = :id",
                    ['id' => $ativas[0]['id']]
                );
            }
            $temAtivaNova = $this->db->fetch(
                "SELECT id FROM matricula WHERE aluno_id = :aid AND turma_id = :tid AND status = 'ativa' AND data_saida IS NULL LIMIT 1",
                ['aid' => $alunoId, 'tid' => $turmaNovaId]
            );
            if ($temAtivaNova === false) {
                if ($anoParaNova <= 0) {
                    $anoParaNova = $this->resolverAnoLetivoIdParaTurma($turmaNovaId);
                }
                if ($anoParaNova > 0) {
                    $this->upsertMatriculaAtiva($alunoId, $turmaNovaId, $anoParaNova);
                }
            }

            $turmaMigrarNotasDe = null;
            if ($mudouDeTurma && $turmaAntigaId !== null && $turmaAntigaId > 0 && $turmaAntigaId !== $turmaNovaId) {
                $turmaMigrarNotasDe = $turmaAntigaId;
            }
            if ($turmaMigrarNotasDe === null && $turmaDriftAntiga !== null && $turmaDriftAntiga > 0 && $turmaDriftAntiga !== $turmaNovaId) {
                $turmaMigrarNotasDe = $turmaDriftAntiga;
            }
            if ($turmaMigrarNotasDe !== null && $turmaMigrarNotasDe > 0 && $turmaMigrarNotasDe !== $turmaNovaId) {
                require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';
                (new ExamBlockManualGrade())->migrarTurmaIdEmNotasLancadasParaAluno($alunoId, $turmaMigrarNotasDe, $turmaNovaId);
            }

            if ($mudouDeTurma && $turmaAntigaId !== null && $turmaAntigaId > 0 && $turmaNovaId > 0) {
                $listaSvcPath = __DIR__ . '/../../Services/ListaChamadaService.php';
                if (is_file($listaSvcPath)) {
                    require_once $listaSvcPath;
                    $listaSvc = new \App\Services\ListaChamadaService();
                    if ($listaSvc->tabelaExiste()) {
                        $listaSvc->moverRemanejamento($alunoId, $turmaAntigaId, $turmaNovaId);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('reconcileMatriculaAposAtualizarAluno: ' . $e->getMessage());
        }
    }

    public function resolverAnoLetivoIdParaTurma(int $turmaId): int
    {
        $t = $this->db->fetch('SELECT ano_letivo FROM turmas WHERE id = :id', ['id' => $turmaId]);
        $ano = (int) ($t['ano_letivo'] ?? date('Y'));
        $row = $this->db->fetch(
            'SELECT id FROM ano_letivo WHERE ano = :ano ORDER BY id DESC LIMIT 1',
            ['ano' => $ano]
        );

        return $row ? (int) $row['id'] : 0;
    }

    public function sqlTurmaLabelFieldsAndJoins(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $hasSerie = $this->db->fetch("SHOW TABLES LIKE 'serie'") !== false;
        $hasCurso = $this->db->fetch("SHOW TABLES LIKE 'curso'") !== false;
        $hasCursos = $this->db->fetch("SHOW TABLES LIKE 'cursos'") !== false;
        $hasTipos = $this->db->fetch("SHOW TABLES LIKE 'tipos_curso'") !== false;

        $selectParts = ['t.serie AS turma_serie'];
        $joins = '';
        $coalesce = [];

        if ($hasSerie) {
            $selectParts[] = 's.nome AS serie_nome';
            $joins .= ' LEFT JOIN serie s ON s.id = t.serie_id';
            if ($hasCurso) {
                $joins .= ' LEFT JOIN curso c_s ON c_s.id = s.curso_id';
                $coalesce[] = 'c_s.nome';
            }
        } else {
            $selectParts[] = 'NULL AS serie_nome';
        }

        if ($hasCurso) {
            $joins .= ' LEFT JOIN curso c_n ON c_n.id = t.curso_novo_id';
            $coalesce[] = 'c_n.nome';
        }

        if ($hasCursos && $hasTipos) {
            $joins .= ' LEFT JOIN cursos c_leg ON c_leg.id = t.curso_id';
            $joins .= ' LEFT JOIN tipos_curso tc ON tc.id = c_leg.tipo_curso_id';
            $coalesce[] = 'tc.nome';
        }

        if (empty($coalesce)) {
            $selectParts[] = 't.tipo_ensino AS curso_nome';
        } else {
            $selectParts[] = 'COALESCE(' . implode(', ', $coalesce) . ', t.tipo_ensino) AS curso_nome';
        }

        $cache = [
            'select' => implode(",\n                    ", $selectParts),
            'joins' => $joins,
        ];

        return $cache;
    }

    private function sqlAlunosOrderByClause(bool $includeAlunoNome = true): string
    {
        static $cache = [];
        $key = $includeAlunoNome ? 'alunos' : 'turmas';
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $hasSerie = $this->db->fetch("SHOW TABLES LIKE 'serie'") !== false;
        $hasCurso = $this->db->fetch("SHOW TABLES LIKE 'curso'") !== false;

        $parts = [];
        if ($hasCurso) {
            $parts[] = $hasSerie
                ? 'COALESCE(c_s.ordem, c_n.ordem, 9999) ASC'
                : 'COALESCE(c_n.ordem, 9999) ASC';
        }
        if ($hasSerie) {
            $parts[] = 'COALESCE(s.ordem, 9999) ASC';
        }
        $parts[] = 't.nome ASC';
        if ($includeAlunoNome) {
            $parts[] = 'a.nome ASC';
        }

        if (count($parts) <= 2 && !$hasSerie && !$hasCurso) {
            $parts = ['t.serie ASC', 't.nome ASC'];
            if ($includeAlunoNome) {
                $parts[] = 'a.nome ASC';
            }
        }

        $cache[$key] = implode(', ', $parts);

        return $cache[$key];
    }

    private function colunaAlunoSexoExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'sexo'"
        );
        $cache = !empty($row['total']);

        return $cache;
    }

    private function colunaAlunoEnderecoExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'logradouro'"
        );
        $cache = !empty($row['total']);

        return $cache;
    }

    private function colunaAlunoContatoExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'telefone'"
        );
        $cache = !empty($row['total']);

        return $cache;
    }

    private function colunaAlunoUnidadeExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'unidade_id'"
        );
        $cache = !empty($row['total']);

        return $cache;
    }

    private function colunaAlunoCadastroCompletoExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nome_social'"
        );
        $cache = !empty($row['total']);

        return $cache;
    }

    private function extrairIdentificacaoCivilPost(): array
    {
        require_once __DIR__ . '/../../Helpers/StudentFormHelper.php';

        return StudentFormHelper::extractIdentificacaoCivilFromPost($_POST);
    }

    private function carregarFichaComplementar(int $alunoId): array
    {
        require_once __DIR__ . '/../../Models/User/StudentComplementaryRecord.php';

        try {
            return (new \StudentComplementaryRecord())->getByAluno($alunoId);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function salvarFichaComplementarPost(int $alunoId): void
    {
        if ($alunoId <= 0) {
            return;
        }
        require_once __DIR__ . '/../../Models/User/StudentComplementaryRecord.php';

        try {
            $model = new \StudentComplementaryRecord();
            if (!$model->tableExists()) {
                return;
            }
            $dados = ['usa_transporte_escolar' => isset($_POST['usa_transporte_escolar']) && (string) $_POST['usa_transporte_escolar'] === '1' ? 1 : 0];
            foreach (\StudentComplementaryRecord::FIELDS as $field) {
                $dados[$field] = $_POST[$field] ?? null;
            }
            $model->upsert($alunoId, $dados);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                \Logger::error('Erro ao salvar ficha complementar do aluno ' . $alunoId . ': ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * Registra uma ação sensível na trilha de auditoria (logs_auditoria).
     * Nunca interrompe o fluxo principal.
     */
    private function auditarAluno(string $action, ?int $alunoId, array $extra = []): void
    {
        try {
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../../Core/Logger.php';
            }
            $user = $this->auth->getUser();
            $payload = array_merge(['aluno_id' => $alunoId], $extra);
            \Logger::logAudit(
                $action,
                '/admin/students/' . ($alunoId !== null ? $alunoId : ''),
                $payload,
                $user['id'] ?? null,
                $user['tipo'] ?? null
            );
        } catch (\Throwable $e) {
            // auditoria é best-effort
        }
    }

    private function carregarDocumentosAluno(int $alunoId): array
    {
        require_once __DIR__ . '/../../Models/User/StudentDocument.php';

        try {
            return (new \StudentDocument())->getByAluno($alunoId);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function tenantSlugParaArquivo(): string
    {
        $slug = trim((string) ($this->config['tenant']['slug'] ?? ''));
        if ($slug === '') {
            $slug = trim((string) ($this->config['school']['code'] ?? ''));
        }

        return $slug !== '' ? $slug : 'default';
    }

    /** Extensoes aceitas para documentos do aluno. */
    private function documentoExtensoesPermitidas(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
    }

    public function salvarDocumentoAluno($id)
    {
        if (!$this->enforceAdminPermissionKey('documentos_aluno', 'cadastrar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        $alunoId = (int) $id;
        if ($alunoId < 1) {
            $this->json(['error' => 'Aluno inválido'], 400);
            return;
        }

        try {
            require_once __DIR__ . '/../../Models/User/StudentDocument.php';
            $model = new \StudentDocument();
            if (!$model->tableExists()) {
                throw new Exception('Recurso de documentos ainda não disponível. Execute a migration.');
            }

            $aluno = $this->db->fetch('SELECT id FROM alunos WHERE id = :id', ['id' => $alunoId]);
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $tipo = trim((string) ($_POST['tipo'] ?? ''));
            $checklist = \StudentDocument::checklist();
            if ($tipo === '' || !isset($checklist[$tipo])) {
                $tipo = 'outros';
            }
            $docId = (int) ($_POST['doc_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'pendente');
            $titulo = trim((string) ($_POST['titulo'] ?? ''));
            $observacao = trim((string) ($_POST['observacao'] ?? ''));

            $dados = [
                'titulo' => $titulo !== '' ? $titulo : null,
                'status' => $status,
                'observacao' => $observacao !== '' ? $observacao : null,
                'created_by' => (int) ($this->auth->getUser()['id'] ?? 0) ?: null,
            ];

            // Upload opcional
            if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
                $arquivo = $this->processarUploadDocumento($alunoId, $_FILES['arquivo']);
                $dados['arquivo_key'] = $arquivo['key'];
                $dados['arquivo_nome'] = $arquivo['nome'];
                $dados['arquivo_mime'] = $arquivo['mime'];
                $dados['arquivo_tamanho'] = $arquivo['tamanho'];
                if ($status !== 'dispensado') {
                    $dados['status'] = 'entregue';
                }
            }

            $savedId = $model->save($alunoId, $tipo, $dados, $docId > 0 ? $docId : null);

            $this->auditarAluno('SAVE_STUDENT_DOCUMENT', $alunoId, [
                'documento_id' => $savedId,
                'tipo' => $tipo,
                'status' => $dados['status'] ?? null,
                'com_arquivo' => array_key_exists('arquivo_key', $dados),
            ]);

            $this->json(['success' => true, 'message' => 'Documento salvo com sucesso', 'id' => $savedId]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    private function processarUploadDocumento(int $alunoId, array $file): array
    {
        $extensoes = $this->documentoExtensoesPermitidas();
        $ext = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $extensoes, true)) {
            throw new Exception('Tipo de arquivo não permitido. Use: ' . implode(', ', $extensoes));
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Máximo 10MB');
        }

        // Valida MIME real (não confia na extensão)
        $mimesPermitidos = [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/octet-stream',
        ];
        $mimeReal = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if ($detected) {
                    $mimeReal = $detected;
                }
            }
        }
        if (!in_array($mimeReal, $mimesPermitidos, true)) {
            throw new Exception('Conteúdo do arquivo não corresponde a um documento válido');
        }

        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);

        $slug = $this->tenantSlugParaArquivo();
        $nomeUnico = 'doc_' . $alunoId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $key = $slug . '/alunos_documentos/' . $alunoId . '/' . $nomeUnico;

        if (!$media->put('arquivos', $key, $file['tmp_name'], $mimeReal)) {
            throw new Exception('Falha ao armazenar o arquivo');
        }

        return [
            'key' => $key,
            'nome' => substr((string) $file['name'], 0, 255),
            'mime' => $mimeReal,
            'tamanho' => (int) $file['size'],
        ];
    }

    public function removerDocumentoAluno($id, $docId)
    {
        if (!$this->enforceAdminPermissionKey('documentos_aluno', 'excluir', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        $alunoId = (int) $id;
        $documentoId = (int) $docId;
        try {
            require_once __DIR__ . '/../../Models/User/StudentDocument.php';
            $model = new \StudentDocument();
            $doc = $model->find($alunoId, $documentoId);
            if (!$doc) {
                throw new Exception('Documento não encontrado');
            }

            if (!empty($doc['arquivo_key'])) {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                $media = new MediaStorageService($this->config);
                $media->delete('arquivos', (string) $doc['arquivo_key']);
            }

            // "outros" some por completo; itens do checklist voltam para pendente
            if (($doc['tipo'] ?? '') === 'outros') {
                $model->delete($alunoId, $documentoId);
            } else {
                $model->clearArquivo($alunoId, $documentoId);
                $model->save($alunoId, (string) $doc['tipo'], ['status' => 'pendente', 'titulo' => $doc['titulo'] ?? null], $documentoId);
            }

            $this->auditarAluno('DELETE_STUDENT_DOCUMENT', $alunoId, [
                'documento_id' => $documentoId,
                'tipo' => $doc['tipo'] ?? null,
            ]);

            $this->json(['success' => true, 'message' => 'Documento removido']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function baixarDocumentoAluno($id, $docId)
    {
        if (!$this->enforceAdminPermissionKey('documentos_aluno', 'visualizar', false)) {
            return;
        }

        $alunoId = (int) $id;
        $documentoId = (int) $docId;

        require_once __DIR__ . '/../../Models/User/StudentDocument.php';
        $model = new \StudentDocument();
        $doc = $model->find($alunoId, $documentoId);
        if (!$doc || empty($doc['arquivo_key'])) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }

        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $conteudo = $media->getContents('arquivos', (string) $doc['arquivo_key']);
        if ($conteudo === null) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            return;
        }

        $this->auditarAluno('DOWNLOAD_STUDENT_DOCUMENT', $alunoId, [
            'documento_id' => $documentoId,
            'tipo' => $doc['tipo'] ?? null,
            'arquivo_nome' => $doc['arquivo_nome'] ?? null,
        ]);

        $mime = (string) ($doc['arquivo_mime'] ?? 'application/octet-stream');
        $nome = (string) ($doc['arquivo_nome'] ?? 'documento');
        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="' . str_replace('"', '', $nome) . '"');
            header('Content-Length: ' . strlen($conteudo));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }
        echo $conteudo;
    }

    private function colunaVinculoResponsavelFlagsExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos_responsaveis' AND COLUMN_NAME = 'parentesco'"
        );
        $cache = !empty($row['total']);

        return $cache;
    }

    private function colunaResponsavelExiste(string $coluna): bool
    {
        static $cache = [];
        if (array_key_exists($coluna, $cache)) {
            return $cache[$coluna];
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'responsaveis' AND COLUMN_NAME = :coluna",
            ['coluna' => $coluna]
        );
        $cache[$coluna] = !empty($row['total']);

        return $cache[$coluna];
    }

    private function extrairVinculoResponsavelPost(): array
    {
        $flags = ['pode_retirar', 'recebe_boletos', 'recebe_boletim', 'recebe_notificacoes', 'responsavel_pedagogico', 'guarda_judicial', 'assina_documentos'];
        $out = [
            'parentesco' => trim((string) ($_POST['parentesco'] ?? '')) ?: null,
            'profissao' => trim((string) ($_POST['profissao'] ?? '')) ?: null,
            'empresa' => trim((string) ($_POST['empresa'] ?? '')) ?: null,
        ];
        foreach ($flags as $flag) {
            $out[$flag] = isset($_POST[$flag]) ? 1 : 0;
        }

        return $out;
    }

    private function upsertVinculoResponsavel(int $alunoId, int $responsavelId, int $isFinanceiro): void
    {
        $extraCols = '';
        $extraVals = '';
        $extraUpd = '';
        $params = [
            'aluno_id' => $alunoId,
            'pai_id' => $responsavelId,
            'is_financeiro' => $isFinanceiro,
        ];
        if ($this->colunaVinculoResponsavelFlagsExiste()) {
            foreach ($this->extrairVinculoResponsavelPost() as $campo => $valor) {
                $extraCols .= ", {$campo}";
                $extraVals .= ", :{$campo}";
                $extraUpd .= ", {$campo} = VALUES({$campo})";
                $params[$campo] = $valor;
            }
        }

        $this->db->insert(
            "INSERT INTO alunos_responsaveis (aluno_id, responsavel_id, tipo_vinculo, is_financeiro, ativo, created_at, updated_at{$extraCols})
             VALUES (:aluno_id, :pai_id, 'responsavel', :is_financeiro, 1, NOW(), NOW(){$extraVals})
             ON DUPLICATE KEY UPDATE is_financeiro = VALUES(is_financeiro), ativo = VALUES(ativo), updated_at = NOW(){$extraUpd}",
            $params
        );
    }

    private function listarUnidadesAtivas(): array
    {
        if (!$this->colunaAlunoUnidadeExiste()) {
            return [];
        }
        require_once __DIR__ . '/../../Models/Education/SchoolUnit.php';
        return (new \SchoolUnit())->getActive();
    }

    private function unidadeIdFromPost(): ?int
    {
        $raw = $_POST['unidade_id'] ?? '';
        $id = (int) $raw;
        return $id > 0 ? $id : null;
    }

    private function extrairContatoPost(): array
    {
        require_once __DIR__ . '/../../Helpers/StudentFormHelper.php';

        return StudentFormHelper::extractContatoFromPost($_POST);
    }

    private function extrairDocumentoEnderecoPost(): array
    {
        require_once __DIR__ . '/../../Helpers/StudentFormHelper.php';

        return StudentFormHelper::extractDocumentoEnderecoFromPost($_POST);
    }

    private function colunaAlunoStatusExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'status'"
        );
        $cache = !empty($row['total']);

        return $cache;
    }

    private function alunoTemMatriculaAtiva(int $alunoId): bool
    {
        $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
        if ($hasMatricula !== false) {
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS total FROM matricula
                 WHERE aluno_id = :id AND status = 'ativa'
                   AND data_saida IS NULL",
                ['id' => $alunoId]
            );

            return (int) ($row['total'] ?? 0) > 0;
        }

        $row = $this->db->fetch('SELECT turma_id FROM alunos WHERE id = :id', ['id' => $alunoId]);

        return !empty($row['turma_id']);
    }

    private function alunoTeveMatricula(int $alunoId): bool
    {
        $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
        if ($hasMatricula === false) {
            return false;
        }
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS total FROM matricula WHERE aluno_id = :id',
            ['id' => $alunoId]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    public function syncAlunoStatusMatricula(int $alunoId): void
    {
        if (!$this->colunaAlunoStatusExiste()) {
            return;
        }

        $aluno = $this->db->fetch('SELECT status, ativo FROM alunos WHERE id = :id', ['id' => $alunoId]);
        if (!$aluno) {
            return;
        }

        $statusAtual = strtoupper(trim((string) ($aluno['status'] ?? '')));
        if (in_array($statusAtual, ['INACTIVE', 'GRADUATED'], true)) {
            return;
        }

        if (!$this->alunoTemMatriculaAtiva($alunoId)) {
            if ($this->alunoTeveMatricula($alunoId)) {
                $this->db->update(
                    "UPDATE alunos SET status = 'INACTIVE', ativo = 0 WHERE id = :id",
                    ['id' => $alunoId]
                );
            } else {
                $this->db->update(
                    'UPDATE alunos SET status = :status WHERE id = :id',
                    ['status' => MatriculaSchemaHelper::alunoStatusSemMatricula(), 'id' => $alunoId]
                );
            }

            return;
        }

        if ((int) ($aluno['ativo'] ?? 0) === 1) {
            $this->db->update(
                "UPDATE alunos SET status = 'ACTIVE' WHERE id = :id",
                ['id' => $alunoId]
            );
        }
    }

    private function removerFotoAlunoStorage(MediaStorageService $media, $fotoRef): void
    {
        $mediaRef = $this->extrairMediaTypeKeyDeFotoUrl($fotoRef);
        if ($mediaRef !== null) {
            $tenantSlug = $mediaRef['tenant'] ?? '';
            if ($tenantSlug !== '') {
                $config = $this->config;
                $config['tenant'] = array_merge($config['tenant'] ?? [], ['slug' => $tenantSlug]);
                $config['school'] = array_merge($config['school'] ?? [], ['code' => $tenantSlug]);
                $config['media'] = array_merge($config['media'] ?? [], ['tenant_prefix' => true]);
                $media = new MediaStorageService($config);
            }
            $media->delete($mediaRef['type'], $mediaRef['key']);
        }
    }

    private function extrairMediaTypeKeyDeFotoUrl($fotoRef): ?array
    {
        $ref = trim((string) $fotoRef);
        if ($ref === '' || strpos($ref, '/media/serve') === false) {
            return null;
        }
        $query = parse_url($ref, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return null;
        }
        parse_str($query, $params);
        $type = isset($params['type']) ? trim((string) $params['type']) : '';
        $key = isset($params['key']) ? trim((string) $params['key']) : '';
        if ($type === '' || $key === '') {
            return null;
        }
        $result = ['type' => $type, 'key' => $key];
        if (isset($params['tenant']) && trim((string) $params['tenant']) !== '') {
            $result['tenant'] = trim((string) $params['tenant']);
        }

        return $result;
    }

    private function normalizarSexoAluno($sexo): ?string
    {
        $sexo = strtoupper(trim((string) $sexo));
        if ($sexo === '') {
            return null;
        }
        if (!in_array($sexo, ['M', 'F', 'N'], true)) {
            return null;
        }

        return $sexo;
    }

    private function encerrarMatriculasAtivasNaTurma(int $alunoId, int $turmaId, string $status = 'transferido'): void
    {
        if ($alunoId <= 0 || $turmaId <= 0) {
            return;
        }
        try {
            $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
            if ($hasMatricula === false) {
                return;
            }
            $status = MatriculaSchemaHelper::normalizarStatusEncerramentoMatricula($status);
            $this->db->query(
                "UPDATE matricula SET data_saida = CURDATE(), status = :status, updated_at = NOW()
                 WHERE aluno_id = :aid AND turma_id = :tid AND status = 'ativa'
                   AND data_saida IS NULL",
                ['aid' => $alunoId, 'tid' => $turmaId, 'status' => $status]
            );
        } catch (Exception $e) {
            error_log('encerrarMatriculasAtivasNaTurma: ' . $e->getMessage());
        }
    }

    private function upsertMatriculaAtiva(int $alunoId, int $turmaId, int $anoLetivoId): void
    {
        if ($alunoId <= 0 || $turmaId <= 0 || $anoLetivoId <= 0) {
            return;
        }
        $ex = $this->db->fetch(
            'SELECT id FROM matricula WHERE aluno_id = :a AND turma_id = :t AND ano_letivo_id = :y',
            ['a' => $alunoId, 't' => $turmaId, 'y' => $anoLetivoId]
        );
        if ($ex) {
            $this->db->query(
                "UPDATE matricula SET status = 'ativa', data_entrada = CURDATE(), data_saida = NULL, updated_at = NOW() WHERE id = :id",
                ['id' => $ex['id']]
            );

            return;
        }
        $this->db->insert(
            "INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status) VALUES (:a, :t, :y, CURDATE(), 'ativa')",
            ['a' => $alunoId, 't' => $turmaId, 'y' => $anoLetivoId]
        );
    }

    public function activateAluno($id)
    {
        if (!$this->enforceAdminPermissionKey('acao_rapida_ativar_desativar', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $user = $this->auth->getUser();
            $service = new \App\Services\StudentStatusService();
            $result = $service->activate((int) $id, $_POST, $user);

            $this->json([
                'success' => true,
                'message' => 'Aluno reativado com sucesso',
                'data' => $result
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function inactivateAluno($id)
    {
        if (!$this->enforceAdminPermissionKey('acao_rapida_ativar_desativar', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $user = $this->auth->getUser();
            $service = new \App\Services\StudentStatusService();
            $result = $service->inactivate($id, $_POST, $user);

            $this->json([
                'success' => true,
                'message' => 'Aluno inativado com sucesso',
                'data' => $result
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function togglePaganteAluno($id)
    {
        try {
            $studentModel = new Student();
            
            if (!$studentModel->exists($id)) {
                throw new Exception('Aluno não encontrado');
            }
            
            // Buscar aluno atual
            $aluno = $studentModel->findById($id);
            $novoStatus = ($aluno['pagante'] ?? 1) ? 0 : 1;
            
            // Atualizar status de pagante
            $this->db->update(
                "UPDATE alunos SET pagante = :pagante WHERE id = :id",
                ['pagante' => $novoStatus, 'id' => $id]
            );
            
            $this->json(['success' => true, 'message' => 'Status de pagante alterado com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function uploadExcelAlunos()
    {
        try {
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                throw new Exception('Token inválido ou sessão expirada. Atualize a página e tente de novo.');
            }
            // Verificar se arquivo foi enviado
            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Nenhum arquivo foi enviado ou ocorreu um erro no upload');
            }
            
            $file = $_FILES['excel_file'];
            
            // Verificar tipo de arquivo (aceitar apenas CSV)
            $allowedTypes = ['text/csv', 'application/csv'];
            $allowedExtensions = ['csv'];
            
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file['type'], $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
                throw new Exception('Tipo de arquivo não permitido. Use apenas .csv');
            }
            
            // Verificar tamanho (10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande. Máximo 10MB');
            }
            
            // Processar arquivo CSV (formato recomendado)
            $rows = [];
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                throw new Exception('Erro ao abrir arquivo');
            }
            
            // Detectar delimitador (priorizar ; ponto e vírgula, depois vírgula)
            $primeiraLinha = fgets($handle);
            rewind($handle);
            
            $delimitador = ';'; // Padrão: ponto e vírgula
            // Se não tiver ponto e vírgula mas tiver vírgula, usa vírgula
            if (strpos($primeiraLinha, ';') === false && strpos($primeiraLinha, ',') !== false) {
                $delimitador = ',';
            }
            
            while (($data = fgetcsv($handle, 1000, $delimitador)) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
            
            if (empty($rows) || count($rows) < 2) {
                throw new Exception('Arquivo CSV está vazio ou não possui dados');
            }
            
            // Verificar cabeçalhos (remover BOM se existir)
            $headers = array_map('trim', $rows[0]);
            if (!empty($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            }
            $headers = array_map('strtolower', $headers);
            $requiredHeaders = ['nome', 'ra', 'turma_id'];
            
            foreach ($requiredHeaders as $header) {
                if (!in_array($header, $headers)) {
                    throw new Exception("Coluna obrigatória '$header' não encontrada no arquivo");
                }
            }
            
            // Obter índices das colunas
            $nomeIndex = array_search('nome', $headers);
            $emailIndex = array_search('email', $headers); // Pode ser false se não existir
            $raIndex = array_search('ra', $headers);
            $turmaIdIndex = array_search('turma_id', $headers);
            
            $imported = 0;
            $skipped = 0;
            $errors = [];
            
            // Função para normalizar texto (corrigir encoding do CSV)
            $normalizeText = function ($value) {
                $value = trim((string) $value);
                if ($value === '') {
                    return $value;
                }
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }
                return $value;
            };
            
            // Processar cada linha (pular cabeçalho)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Pular linhas vazias
                if (empty(array_filter($row))) {
                    continue;
                }
                
                $nome = $normalizeText($row[$nomeIndex] ?? '');
                // Email pode não existir no CSV, tratar adequadamente
                $email = ($emailIndex !== false) ? $normalizeText($row[$emailIndex] ?? '') : '';
                $ra = trim($row[$raIndex] ?? '');
                $turmaId = trim($row[$turmaIdIndex] ?? '');
                
                // Validar dados obrigatórios
                if (empty($nome) || empty($ra) || empty($turmaId)) {
                    $errors[] = "Linha " . ($i + 1) . ": Dados obrigatórios em branco";
                    $skipped++;
                    continue;
                }
                
                // Verificar se RA já existe
                $existingRa = $this->db->fetch("SELECT id FROM alunos WHERE ra = :ra", ['ra' => $ra]);
                if ($existingRa) {
                    $errors[] = "Linha " . ($i + 1) . ": RA '$ra' já existe";
                    $skipped++;
                    continue;
                }
                
                // Verificar se turma existe e obter série para compatibilidade com schema de alunos
                $turma = $this->db->fetch("SELECT id, serie FROM turmas WHERE id = :id", ['id' => $turmaId]);
                if (!$turma) {
                    $errors[] = "Linha " . ($i + 1) . ": Turma ID '$turmaId' não existe";
                    $skipped++;
                    continue;
                }
                $serieAluno = trim((string)($turma['serie'] ?? ''));
                if ($serieAluno === '') {
                    $errors[] = "Linha " . ($i + 1) . ": Turma ID '$turmaId' sem série definida";
                    $skipped++;
                    continue;
                }
                
                // Verificar email se fornecido
                if (!empty($email)) {
                    $existingEmail = $this->db->fetch("SELECT id FROM alunos WHERE email = :email", ['email' => $email]);
                    if ($existingEmail) {
                        $errors[] = "Linha " . ($i + 1) . ": Email '$email' já existe";
                        $skipped++;
                        continue;
                    }
                }
                
                // Criar aluno
                $senhaHash = password_hash('123456', PASSWORD_DEFAULT);
                
                $this->db->query(
                    "INSERT INTO alunos (nome, email, ra, password, serie, turma_id, ativo, created_at) 
                     VALUES (:nome, :email, :ra, :senha, :serie, :turma_id, 1, NOW())",
                    [
                        'nome' => $nome,
                        'email' => $email ?: null,
                        'ra' => $ra,
                        'senha' => $senhaHash,
                        'serie' => $serieAluno,
                        'turma_id' => $turmaId
                    ]
                );
                
                $imported++;
            }
            
            $message = "Importação concluída!";
            if (!empty($errors)) {
                $message .= " " . count($errors) . " erros encontrados.";
            }
            
            $this->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors
            ]);
            
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function importarResponsaveisCsv()
    {
        try {
            @set_time_limit(0);
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                throw new Exception('Token inválido');
            }
            $file = null;
            if (isset($_FILES['csv_file']) && ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $file = $_FILES['csv_file'];
            } elseif (isset($_FILES['excel_file']) && ($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $file = $_FILES['excel_file'];
            }
            if ($file === null) {
                throw new Exception('Envie um arquivo CSV ou JSON válido.');
            }
            $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'json'], true)) {
                throw new Exception('Tipo de arquivo não permitido. Use .csv ou .json');
            }
            if (($file['size'] ?? 0) > 200 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande. Máximo permitido: 200MB.');
            }

            require_once __DIR__ . '/../../Services/StudentResponsibleImportService.php';
            $service = new StudentResponsibleImportService();
            $result = $ext === 'json'
                ? $service->importFromJson((string)$file['tmp_name'])
                : $service->importFromCsv((string)$file['tmp_name']);

            $this->json([
                'success' => true,
                'message' => 'Importação finalizada.',
                'totais' => $result['totais'],
                'relatorios' => $result['relatorios']
            ]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function baixarRelatorioImportResponsaveis($arquivo)
    {
        $nome = basename((string)$arquivo);
        if (!preg_match('/^import_responsaveis_(sucesso|pendencias|resumo)_[0-9]{8}_[0-9]{6}\.(csv|json)$/', $nome)) {
            http_response_code(400);
            exit;
        }
        $base = dirname(__DIR__, 3) . '/storage/imports/responsaveis/';
        $path = $base . $nome;
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($ext === 'json' ? 'application/json' : 'text/csv; charset=utf-8'));
        header('Content-Disposition: attachment; filename="' . addslashes($nome) . '"');
        readfile($path);
        exit;
    }

    public function cadastrarPai()
    {
        if (!$this->enforceAdminPermissionKey('responsaveis_vinculados', 'cadastrar', true)) {
            return;
        }
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $alunoId = $_POST['aluno_id'] ?? null;
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $cpf = preg_replace('/\D+/', '', (string)($_POST['cpf'] ?? ''));
            $telefone = trim((string)($_POST['telefone'] ?? ''));
            $celular = trim((string)($_POST['celular'] ?? ''));
            $rg = trim((string)($_POST['rg'] ?? ''));
            $dataNasc = trim($_POST['data_nascimento'] ?? '') ?: null;
            $endereco = trim($_POST['endereco'] ?? '') ?: null;
            $numero = trim($_POST['numero'] ?? '') ?: null;
            $complemento = trim($_POST['complemento'] ?? '') ?: null;
            $bairro = trim($_POST['bairro'] ?? '') ?: null;
            $cidade = trim($_POST['cidade'] ?? '') ?: null;
            $uf = trim($_POST['uf'] ?? '') ?: null;
            $cep = trim($_POST['cep'] ?? '') ?: null;
            $observacoes = trim($_POST['observacoes'] ?? '') ?: null;
            $senha = $_POST['senha'] ?? '';
            $isFinanceiro = isset($_POST['is_financeiro']) ? 1 : 0;

            // Validações
            if (empty($alunoId) || empty($nome) || empty($cpf)) {
                throw new Exception('Nome e CPF são obrigatórios');
            }
            if (strlen($cpf) < 11) {
                throw new Exception('CPF inválido');
            }
            
            // Verifica se o aluno existe
            $aluno = $this->db->fetch(
                "SELECT * FROM alunos WHERE id = :id",
                ['id' => $alunoId]
            );
            
            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }
            
            // Verifica se já existe um responsável por CPF (preferência) e por e-mail (fallback)
            $paiExistente = $this->db->fetch(
                "SELECT * FROM responsaveis 
                 WHERE REPLACE(REPLACE(REPLACE(COALESCE(cpf, ''), '.', ''), '-', ''), '/', '') = :cpf
                 LIMIT 1",
                ['cpf' => $cpf]
            );
            if (!$paiExistente && $email !== '') {
                $paiExistente = $this->db->fetch(
                    "SELECT * FROM responsaveis WHERE email = :email LIMIT 1",
                    ['email' => $email]
                );
            }
            
            if ($paiExistente) {
                // Atualiza dados do responsável existente
                $this->db->update(
                    "UPDATE responsaveis
                     SET nome = :nome, email = :email, cpf = :cpf,
                         telefone = :telefone, celular = :celular, rg = :rg,
                         data_nascimento = :data_nascimento,
                         endereco = :endereco, numero = :numero, complemento = :complemento,
                         bairro = :bairro, cidade = :cidade, uf = :uf, cep = :cep,
                         observacoes = :observacoes, ativo = 1
                     WHERE id = :id",
                    [
                        'id' => $paiExistente['id'],
                        'nome' => $nome,
                        'email' => $email !== '' ? $email : null,
                        'cpf' => $cpf,
                        'telefone' => $telefone !== '' ? $telefone : null,
                        'celular' => $celular !== '' ? $celular : null,
                        'rg' => $rg !== '' ? $rg : null,
                        'data_nascimento' => $dataNasc,
                        'endereco' => $endereco, 'numero' => $numero, 'complemento' => $complemento,
                        'bairro' => $bairro, 'cidade' => $cidade, 'uf' => $uf, 'cep' => $cep,
                        'observacoes' => $observacoes,
                    ]
                );

                // Se já existe, vincula no modelo N:N e mantém compatibilidade legada
                $this->upsertVinculoResponsavel((int) $alunoId, (int) $paiExistente['id'], $isFinanceiro);
                if ($isFinanceiro === 1) {
                    $this->db->update("UPDATE alunos SET responsavel_id = :pai_id WHERE id = :aluno_id", [
                        'pai_id' => $paiExistente['id'],
                        'aluno_id' => $alunoId
                    ]);
                } else {
                    $this->db->update("UPDATE alunos SET responsavel_id = COALESCE(responsavel_id, :pai_id) WHERE id = :aluno_id", [
                        'pai_id' => $paiExistente['id'],
                        'aluno_id' => $alunoId
                    ]);
                }
                
                $this->auditarAluno('LINK_GUARDIAN', (int) $alunoId, [
                    'responsavel_id' => (int) $paiExistente['id'],
                    'is_financeiro' => $isFinanceiro,
                    'novo' => false,
                ]);

                $this->json([
                    'success' => true,
                    'message' => 'Responsável vinculado com sucesso! (Cadastro já existente)'
                ]);
            } else {
                // Cria novo responsável — senha obrigatória
                if (empty($senha) || strlen($senha) < 6) {
                    throw new Exception('Senha obrigatória para novo responsável (mínimo 6 caracteres)');
                }
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                
                $paiId = $this->db->insert(
                    "INSERT INTO responsaveis (nome, email, cpf, telefone, celular, rg, data_nascimento,
                         endereco, numero, complemento, bairro, cidade, uf, cep, observacoes, senha_hash, ativo, created_at)
                     VALUES (:nome, :email, :cpf, :telefone, :celular, :rg, :data_nascimento,
                         :endereco, :numero, :complemento, :bairro, :cidade, :uf, :cep, :observacoes, :senha_hash, 1, NOW())",
                    [
                        'nome' => $nome,
                        'email' => $email !== '' ? $email : null,
                        'cpf' => $cpf,
                        'telefone' => $telefone !== '' ? $telefone : null,
                        'celular' => $celular !== '' ? $celular : null,
                        'rg' => $rg !== '' ? $rg : null,
                        'data_nascimento' => $dataNasc,
                        'endereco' => $endereco, 'numero' => $numero, 'complemento' => $complemento,
                        'bairro' => $bairro, 'cidade' => $cidade, 'uf' => $uf, 'cep' => $cep,
                        'observacoes' => $observacoes,
                        'senha_hash' => $senhaHash,
                    ]
                );
                
                // Vincula ao aluno no modelo N:N e mantém legado
                $this->upsertVinculoResponsavel((int) $alunoId, (int) $paiId, $isFinanceiro);
                if ($isFinanceiro === 1) {
                    $this->db->update("UPDATE alunos SET responsavel_id = :pai_id WHERE id = :aluno_id", [
                        'pai_id' => $paiId,
                        'aluno_id' => $alunoId
                    ]);
                } else {
                    $this->db->update("UPDATE alunos SET responsavel_id = COALESCE(responsavel_id, :pai_id) WHERE id = :aluno_id", [
                        'pai_id' => $paiId,
                        'aluno_id' => $alunoId
                    ]);
                }
                
                $this->auditarAluno('LINK_GUARDIAN', (int) $alunoId, [
                    'responsavel_id' => (int) $paiId,
                    'is_financeiro' => $isFinanceiro,
                    'novo' => true,
                ]);

                $this->json([
                    'success' => true,
                    'message' => 'Responsável cadastrado e vinculado com sucesso!'
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao cadastrar pai: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function atualizarResponsavelAluno()
    {
        if (!$this->enforceAdminPermissionKey('responsaveis_vinculados', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $alunoId = (int) ($_POST['aluno_id'] ?? 0);
            $responsavelId = (int) ($_POST['responsavel_id'] ?? 0);
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $telefone = trim((string) ($_POST['telefone'] ?? ''));
            $cpf = preg_replace('/\D+/', '', (string) ($_POST['cpf'] ?? ''));
            $senha = (string) ($_POST['senha'] ?? '');
            $isFinanceiro = isset($_POST['is_financeiro']) ? 1 : 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if ($alunoId <= 0 || $responsavelId <= 0 || $nome === '') {
                throw new Exception('Dados obrigatórios não informados.');
            }
            if ($senha !== '' && strlen($senha) < 6) {
                throw new Exception('A nova senha deve ter pelo menos 6 caracteres.');
            }

            $vinculo = $this->db->fetch(
                "SELECT 1
                 FROM alunos_responsaveis
                 WHERE aluno_id = :aluno_id AND responsavel_id = :responsavel_id AND ativo = 1
                 LIMIT 1",
                ['aluno_id' => $alunoId, 'responsavel_id' => $responsavelId]
            );
            if (!$vinculo) {
                throw new Exception('Responsável não está vinculado a este aluno.');
            }

            $setResponsavel = [
                'nome = :nome',
                'email = :email',
                'telefone = :telefone',
                'cpf = :cpf',
                'ativo = :ativo',
            ];
            $paramsResponsavel = [
                'id' => $responsavelId,
                'nome' => $nome,
                'email' => $email !== '' ? $email : null,
                'telefone' => $telefone !== '' ? $telefone : null,
                'cpf' => $cpf !== '' ? $cpf : null,
                'ativo' => $ativo,
            ];
            $camposExtrasResponsavel = [
                'rg' => trim((string) ($_POST['rg'] ?? '')),
                'celular' => trim((string) ($_POST['celular'] ?? '')),
                'data_nascimento' => trim((string) ($_POST['data_nascimento'] ?? '')),
                'endereco' => trim((string) ($_POST['endereco'] ?? '')),
                'numero' => trim((string) ($_POST['numero'] ?? '')),
                'complemento' => trim((string) ($_POST['complemento'] ?? '')),
                'bairro' => trim((string) ($_POST['bairro'] ?? '')),
                'cidade' => trim((string) ($_POST['cidade'] ?? '')),
                'uf' => strtoupper(trim((string) ($_POST['uf'] ?? ''))),
                'cep' => trim((string) ($_POST['cep'] ?? '')),
                'observacoes' => trim((string) ($_POST['observacoes'] ?? '')),
            ];
            foreach ($camposExtrasResponsavel as $campo => $valor) {
                if ($this->colunaResponsavelExiste($campo)) {
                    $setResponsavel[] = "{$campo} = :{$campo}";
                    $paramsResponsavel[$campo] = $valor !== '' ? $valor : null;
                }
            }
            if ($senha !== '') {
                if (!$this->colunaResponsavelExiste('senha_hash')) {
                    throw new Exception('Campo de senha do responsável não encontrado.');
                }
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                $setResponsavel[] = 'senha_hash = :senha_hash';
                $paramsResponsavel['senha_hash'] = $senhaHash;
                if ($this->colunaResponsavelExiste('password')) {
                    $setResponsavel[] = 'password = :password';
                    $paramsResponsavel['password'] = $senhaHash;
                }
                if ($this->colunaResponsavelExiste('force_password_change')) {
                    $setResponsavel[] = 'force_password_change = 0';
                }
            }
            if ($this->colunaResponsavelExiste('updated_at')) {
                $setResponsavel[] = 'updated_at = NOW()';
            }
            $this->db->update(
                'UPDATE responsaveis SET ' . implode(', ', $setResponsavel) . ' WHERE id = :id',
                $paramsResponsavel
            );

            $extraSetVinculo = '';
            $paramsVinculo = [
                'is_financeiro' => $isFinanceiro,
                'aluno_id' => $alunoId,
                'responsavel_id' => $responsavelId,
            ];
            if ($this->colunaVinculoResponsavelFlagsExiste()) {
                foreach ($this->extrairVinculoResponsavelPost() as $campo => $valor) {
                    $extraSetVinculo .= ", {$campo} = :{$campo}";
                    $paramsVinculo[$campo] = $valor;
                }
            }
            $this->db->update(
                "UPDATE alunos_responsaveis
                 SET is_financeiro = :is_financeiro,
                     updated_at = NOW(){$extraSetVinculo}
                 WHERE aluno_id = :aluno_id AND responsavel_id = :responsavel_id",
                $paramsVinculo
            );

            if ($isFinanceiro === 1) {
                $this->db->update(
                    "UPDATE alunos
                     SET responsavel_id = :responsavel_id
                     WHERE id = :aluno_id",
                    [
                        'responsavel_id' => $responsavelId,
                        'aluno_id' => $alunoId
                    ]
                );
            }

            $this->auditarAluno('UPDATE_GUARDIAN', $alunoId, [
                'responsavel_id' => $responsavelId,
                'is_financeiro' => $isFinanceiro,
                'ativo' => $ativo,
                'senha_atualizada' => $senha !== '',
            ]);

            $this->json([
                'success' => true,
                'message' => 'Responsável atualizado com sucesso.'
            ]);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function boletimObservacaoSafe(int $alunoId): array
    {
        $default = ['conteudo' => '', 'updated_at' => null];
        if ($alunoId <= 0) {
            return $default;
        }
        try {
            require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            $cfg = new BoletimConfig();
            $cfg->ensureSchema();
            $row = $cfg->getObservacaoCoordenacao($alunoId);
            if (!$row) {
                return $default;
            }
            return [
                'conteudo' => (string) ($row['conteudo'] ?? ''),
                'updated_at' => $row['updated_at'] ?? null,
            ];
        } catch (Throwable $e) {
            error_log('AdminController boletimObservacaoSafe aluno #' . $alunoId . ': ' . $e->getMessage());
            return $default;
        }
    }

    public function salvarObservacaoBoletim($id)
    {
        $user = $this->auth->getUser();
        if (!$this->coordenacaoPodeEditarBoletim($user)) {
            $this->json(['error' => 'Acesso não autorizado'], 403);
            return;
        }

        $alunoId = (int) $id;
        if ($alunoId <= 0) {
            $this->json(['error' => 'Aluno inválido'], 400);
            return;
        }

        $payload = $_POST;
        if (empty($payload)) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
        }

        $token = (string) ($payload['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!$this->verifyCsrfToken($token)) {
            $this->json(['error' => 'Token inválido'], 419);
            return;
        }

        $conteudo = (string) ($payload['conteudo'] ?? '');
        try {
            require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            $cfg = new BoletimConfig();
            $cfg->ensureSchema();
            $cfg->saveObservacaoCoordenacao($alunoId, $conteudo, (int) ($user['id'] ?? 0));
            $row = $cfg->getObservacaoCoordenacao($alunoId);
            $this->json([
                'success' => true,
                'conteudo' => (string) ($row['conteudo'] ?? ''),
                'updated_at' => $row['updated_at'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('AdminController salvarObservacaoBoletim aluno #' . $alunoId . ': ' . $e->getMessage());
            $this->json(['error' => 'Erro ao salvar observação'], 500);
        }
    }

    public function excluirBoletimGerado($id, $regraId = 0)
    {
        $user = $this->auth->getUser();
        if (!$this->coordenacaoPodeEditarBoletim($user)) {
            $this->json(['error' => 'Acesso não autorizado'], 403);
            return;
        }

        $alunoId = (int) $id;
        $regraIdInt = (int) $regraId;
        if ($alunoId <= 0 || $regraIdInt <= 0) {
            $this->json(['error' => 'Parâmetros inválidos'], 400);
            return;
        }

        $payload = $_POST;
        if (empty($payload)) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
        }

        $token = (string) ($payload['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!$this->verifyCsrfToken($token)) {
            $this->json(['error' => 'Token inválido'], 419);
            return;
        }

        try {
            require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            $cfg = new BoletimConfig();
            $cfg->ensureSchema();
            $removidos = $cfg->deleteGeneratedResultsForAluno($alunoId, $regraIdInt);
            $this->json([
                'success' => true,
                'removidos' => $removidos,
                'aluno_id' => $alunoId,
                'regra_id' => $regraIdInt,
            ]);
        } catch (Throwable $e) {
            error_log('AdminController excluirBoletimGerado aluno=' . $alunoId . ' regra=' . $regraIdInt . ': ' . $e->getMessage());
            $this->json(['error' => 'Erro ao remover boletim'], 500);
        }
    }

    public function listaChamadaTurma($id)
    {
        if (!$this->enforceAdminPermissionKey('lista_chamada', 'visualizar', false)) {
            return;
        }

        $turmaId = (int) $id;
        $turma = $this->db->fetch('SELECT * FROM turmas WHERE id = :id', ['id' => $turmaId]);
        if (!$turma) {
            $this->setFlashMessage('Turma não encontrada.', 'error');
            $this->redirect('/admin/turmas');
            return;
        }

        require_once __DIR__ . '/../../Services/ListaChamadaService.php';
        $listaSvc = new \App\Services\ListaChamadaService();
        $anoLetivoId = $listaSvc->resolverAnoLetivoIdParaTurma($turmaId);
        $flash = $this->getFlashMessage();
        $alunos = $listaSvc->listarPorTurma($turmaId, $anoLetivoId);

        $this->viewWithLayout('admin', 'admin/turmas/lista-chamada', [
            'title' => 'Lista de Chamada - ' . ($turma['nome'] ?? ''),
            'user' => $this->auth->getUser(),
            'current_page' => 'turmas',
            'turma' => $turma,
            'ano_letivo_id' => $anoLetivoId,
            'config' => $listaSvc->getConfig($turmaId, $anoLetivoId),
            'alunos' => $alunos,
            'totais_listagem' => $listaSvc->calcularTotaisListagem($alunos),
            'campos_exportacao' => $listaSvc->getCamposExportacaoDisponiveis(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'lista_schema_ready' => $listaSvc->tabelaExiste(),
        ]);
    }

    public function salvarListaChamadaConfig($id)
    {
        if (!$this->enforceAdminPermissionKey('lista_chamada', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/turmas/' . (int) $id . '/lista-chamada');
            return;
        }

        $turmaId = (int) $id;
        require_once __DIR__ . '/../../Services/ListaChamadaService.php';
        $listaSvc = new \App\Services\ListaChamadaService();
        $anoLetivoId = (int) ($_POST['ano_letivo_id'] ?? 0);
        if ($anoLetivoId <= 0) {
            $anoLetivoId = $listaSvc->resolverAnoLetivoIdParaTurma($turmaId);
        }

        $listaSvc->salvarConfig(
            $turmaId,
            $anoLetivoId,
            (string) ($_POST['criterio_ordem'] ?? 'alfabetica'),
            (($d = trim((string) ($_POST['data_corte'] ?? ''))) !== '') ? $d : null
        );

        $this->setFlashMessage('Configuração da lista salva.', 'success');
        $this->redirect('/admin/turmas/' . $turmaId . '/lista-chamada');
    }

    public function recalcularListaChamada($id)
    {
        if (!$this->enforceAdminPermissionKey('lista_chamada', 'alterar', true)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/admin/turmas/' . (int) $id . '/lista-chamada');
            return;
        }

        $turmaId = (int) $id;
        require_once __DIR__ . '/../../Services/ListaChamadaService.php';
        $listaSvc = new \App\Services\ListaChamadaService();
        $anoLetivoId = (int) ($_POST['ano_letivo_id'] ?? 0);
        if ($anoLetivoId <= 0) {
            $anoLetivoId = $listaSvc->resolverAnoLetivoIdParaTurma($turmaId);
        }

        try {
            $adicionados = $listaSvc->backfillTurma($turmaId, $anoLetivoId);
            $listaSvc->recalcularOrdem($turmaId, $anoLetivoId);
            if ($adicionados > 0) {
                $this->setFlashMessage(
                    "Lista atualizada: {$adicionados} aluno(s) adicionado(s) e reordenada.",
                    'success'
                );
            } else {
                $this->setFlashMessage('Lista recalculada com sucesso.', 'success');
            }
        } catch (Exception $e) {
            $this->setFlashMessage('Erro ao recalcular: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/admin/turmas/' . $turmaId . '/lista-chamada');
    }

    public function listaChamadaPdf($id)
    {
        $_GET['formato'] = 'pdf';
        if (empty($_GET['campos'])) {
            $_GET['campos'] = ['numero_chamada', 'nome', 'ra'];
        }
        $this->listaChamadaExportar($id);
    }

    public function listaChamadaExportar($id)
    {
        if (!$this->enforceAdminPermissionKey('lista_chamada', 'visualizar', false)) {
            return;
        }

        $turmaId = (int) $id;
        $turma = $this->db->fetch('SELECT * FROM turmas WHERE id = :id', ['id' => $turmaId]);
        if (!$turma) {
            $this->setFlashMessage('Turma não encontrada.', 'error');
            $this->redirect('/admin/turmas');
            return;
        }

        require_once __DIR__ . '/../../Services/ListaChamadaService.php';
        $listaSvc = new \App\Services\ListaChamadaService();
        $anoLetivoId = $listaSvc->resolverAnoLetivoIdParaTurma($turmaId);
        $opcoes = $listaSvc->parseOpcoesExportacao($_GET);
        $alunos = $listaSvc->listarPorTurmaCompleto($turmaId, $anoLetivoId);
        $colunas = $listaSvc->resolverOrdemColunasExportacao($opcoes['campos'], $opcoes['assinatura']);
        $dados = $listaSvc->montarDadosExportacao($alunos, $colunas);
        $logoUrl = $listaSvc->resolverLogoUrlExportacao($opcoes['logo']);
        $totais = $listaSvc->calcularTotaisListagem($alunos);

        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($turma['nome'] ?? 'turma'));
        $slug = trim($slug, '_-');
        if ($slug === '') {
            $slug = 'turma';
        }
        $filenameBase = 'lista_chamada_' . $slug . '_' . date('Ymd');

        if ($opcoes['formato'] === 'excel') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, $dados['headers'], ';');
            foreach ($dados['rows'] as $row) {
                fputcsv($out, $row, ';');
            }
            fputcsv($out, [], ';');
            fputcsv($out, ['Total de alunos', (string) $totais['total']], ';');
            fputcsv($out, ['Total masculino', (string) $totais['masculino']], ';');
            fputcsv($out, ['Total feminino', (string) $totais['feminino']], ';');
            fclose($out);
            exit;
        }

        ob_start();
        extract([
            'turma' => $turma,
            'headers' => $dados['headers'],
            'rows' => $dados['rows'],
            'gerado_em' => date('d/m/Y H:i'),
            'logo_url' => $logoUrl,
            'orientacao' => $opcoes['orientacao'],
            'total_alunos' => $totais['total'],
            'total_masculino' => $totais['masculino'],
            'total_feminino' => $totais['feminino'],
        ], EXTR_SKIP);
        require __DIR__ . '/../../Views/admin/turmas/lista-chamada-pdf.php';
        $html = (string) ob_get_clean();

        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $opcoes['orientacao'] === 'horizontal' ? 'landscape' : 'portrait');
            $dompdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filenameBase . '.pdf"');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }

    public function gerarBoletimPdf($id)
    {
        $user = $this->auth->getUser();
        if (!$this->coordenacaoPodeEditarBoletim($user)) {
            $_SESSION['error_message'] = 'Acesso não autorizado';
            $this->redirect('/admin/dashboard');
            return;
        }

        $alunoId = (int) $id;
        if ($alunoId <= 0) {
            $_SESSION['error_message'] = 'Aluno inválido';
            $this->redirect('/admin/students');
            return;
        }

        $aluno = $this->db->fetch(
            "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = ? LIMIT 1",
            [$alunoId]
        );
        if (!$aluno) {
            $_SESSION['error_message'] = 'Aluno não encontrado';
            $this->redirect('/admin/students');
            return;
        }

        try {
            require_once __DIR__ . '/../../Models/System/BoletimConfig.php';
            $cfg = new BoletimConfig();
            $cfg->ensureSchema();
            // Filtra para exibir_em='boletim' (mesma fonte da aba "Boletim" do aluno) e
            // mantém apenas o lançamento mais recente por regra (a query do model já vem
            // ordenada por updated_at DESC, então o primeiro acerto por regra_id é o mais novo).
            $todosGerados = $cfg->getGeneratedBoletinsByAluno($alunoId, 'coordenacao', 'boletim');
            $boletinsGerados = [];
            $seenRegras = [];
            foreach ((array) $todosGerados as $ev) {
                $rid = (int) ($ev['regra_id'] ?? 0);
                if ($rid <= 0 || isset($seenRegras[$rid])) {
                    continue;
                }
                $seenRegras[$rid] = true;
                $boletinsGerados[] = $ev;
            }
            $observacaoRow = $cfg->getObservacaoCoordenacao($alunoId);
        } catch (Throwable $e) {
            error_log('AdminController gerarBoletimPdf aluno #' . $alunoId . ': ' . $e->getMessage());
            $boletinsGerados = [];
            $observacaoRow = null;
        }

        $anoLetivo = (int) date('Y');
        foreach ($boletinsGerados as $ev) {
            $ini = (string) ($ev['data_inicio'] ?? '');
            if ($ini !== '' && preg_match('/^(\d{4})-/', $ini, $m)) {
                $anoLetivo = (int) $m[1];
                break;
            }
        }

        // Logo do PDF: prioriza a logo da escola (mesma usada no navbar/sidebar via
        // LayoutHelper::getNavbarLogoUrl). Faz fallback para a logo institucional do
        // Educatudo apenas se a escola não tiver logo configurada.
        $logoData = $this->resolveSchoolLogoForPdf();
        if ($logoData === '') {
            $logoPath = __DIR__ . '/../../../logo-educatudo.png';
            if (is_file($logoPath) && is_readable($logoPath)) {
                $logoBin = @file_get_contents($logoPath);
                if (is_string($logoBin) && $logoBin !== '') {
                    $logoData = 'data:image/png;base64,' . base64_encode($logoBin);
                }
            }
        }

        ob_start();
        $viewData = [
            'aluno' => $aluno,
            'boletins_gerados' => $boletinsGerados,
            'observacao' => is_array($observacaoRow) ? (string) ($observacaoRow['conteudo'] ?? '') : '',
            'ano_letivo' => $anoLetivo,
            'logo_data' => $logoData,
            'gerado_em' => date('d/m/Y H:i'),
        ];
        extract($viewData, EXTR_SKIP);
        require __DIR__ . '/../../Views/admin/students/boletim_pdf.php';
        $html = (string) ob_get_clean();

        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($aluno['nome'] ?? 'aluno'));
            $slug = trim((string) $slug, '_-');
            if ($slug === '') {
                $slug = 'aluno_' . $alunoId;
            }
            $filename = 'boletim_' . $slug . '_' . date('Ymd_His') . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }

    public function coordenacaoPodeEditarBoletim(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        if (($user['tipo'] ?? '') === 'admin') {
            return true;
        }
        if (($user['tipo'] ?? '') === 'admin_escola'
            && in_array($user['perfil_admin'] ?? '', ['dev', 'diretor', 'coordenador'], true)) {
            return true;
        }
        return false;
    }

    private function resolveSchoolLogoForPdf(): string
    {
        try {
            $url = (string) LayoutHelper::getNavbarLogoUrl();
            if ($url === '') {
                return '';
            }

            $parts = parse_url($url) ?: [];
            $query = [];
            if (!empty($parts['query'])) {
                parse_str((string) $parts['query'], $query);
            }

            $filePath = '';

            // Caso comum (storage local de layout/avatars/professores): URL no formato
            // /media/serve?type=layout&key=logo_xxx.png — usa o MediaStorageService para
            // resolver o caminho absoluto no disco.
            $key = isset($query['key']) ? (string) $query['key'] : '';
            $type = isset($query['type']) ? (string) $query['type'] : 'layout';
            if ($key !== '') {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                $media = new MediaStorageService($this->config);
                $localPath = $media->getLocalPath($type, $key);
                if ($localPath !== null && is_file($localPath) && is_readable($localPath)) {
                    $filePath = $localPath;
                }
            }

            // Fallback: tenta resolver pelo path da URL (ex.: legado em /uploads/layout/x.png
            // ou caminho direto público em /storage/files/.../x.png).
            if ($filePath === '' && !empty($parts['path'])) {
                $relative = ltrim((string) $parts['path'], '/');
                $candidates = [
                    __DIR__ . '/../../../public/' . $relative,
                    __DIR__ . '/../../../' . $relative,
                ];
                foreach ($candidates as $cand) {
                    if (is_file($cand) && is_readable($cand)) {
                        $filePath = $cand;
                        break;
                    }
                }
            }

            if ($filePath === '') {
                return '';
            }

            $bin = @file_get_contents($filePath);
            if (!is_string($bin) || $bin === '') {
                return '';
            }

            $ext = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeMap = [
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
            ];
            $mime = $mimeMap[$ext] ?? 'image/png';

            return 'data:' . $mime . ';base64,' . base64_encode($bin);
        } catch (Throwable $e) {
            error_log('AdminController resolveSchoolLogoForPdf: ' . $e->getMessage());
            return '';
        }
    }

    private function normalizarTextoBoletim(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        if ($texto === '') {
            return '';
        }

        $map = [
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
            'ª' => 'a',
            'º' => 'o',
        ];

        return strtr($texto, $map);
    }

    public function parseSeriesIdsRaw($raw): array
    {
        $decoded = [];
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw)) {
            $s = trim($raw);
            if ($s !== '') {
                $parsed = json_decode($s, true);
                if (is_array($parsed)) {
                    $decoded = $parsed;
                } else {
                    $decoded = preg_split('/[,\s;]+/', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                }
            }
        }

        $ids = [];
        foreach ($decoded as $v) {
            if (is_array($v)) {
                $cand = (int) ($v['id'] ?? $v['serie_id'] ?? $v['value'] ?? 0);
                if ($cand > 0) {
                    $ids[$cand] = true;
                    continue;
                }
                continue;
            }

            $cand = (int) $v;
            if ($cand > 0) {
                $ids[$cand] = true;
            }
        }

        return array_values(array_keys($ids));
    }

    private function inferGrupoTabelaBlocoLetra(array $col): array
    {
        $textos = array_filter([
            trim((string) ($col['bloco_modelo_nome'] ?? '')),
            trim((string) ($col['bloco_titulo'] ?? '')),
            trim((string) ($col['sample_prova_titulo'] ?? '')),
        ]);

        foreach ($textos as $textoOriginal) {
            $texto = $this->normalizarTextoBoletim($textoOriginal);
            if ($texto === '') {
                continue;
            }

            if (strpos($texto, 'avaliacao diagnostica') !== false) {
                return [
                    'key' => 'avaliacao_diagnostica',
                    'label' => 'Avaliação Diagnóstica',
                    'ord_primary' => 10,
                    'ord_secondary' => 0,
                ];
            }

            if (preg_match('/\bbloco\s*[:\-]?\s*b(?:\s*(?:1|1a|1o|primeira|2|2a|2o|segunda))?\b/u', $texto)) {
                return [
                    'key' => 'bloco_b',
                    'label' => 'Bloco B',
                    'ord_primary' => 66,
                    'ord_secondary' => 0,
                ];
            }

            if (preg_match('/\bbloco\s*[:\-]?\s*([a-z])\b/u', $texto, $m)) {
                $letra = strtoupper($m[1]);
                return [
                    'key' => 'letra_' . $letra,
                    'label' => 'Bloco ' . $letra,
                    'ord_primary' => ord($letra),
                    'ord_secondary' => 0,
                ];
            }
        }

        $bid = (int) ($col['bloco_id'] ?? 0);
        if ($bid > 0) {
            $label = trim((string) ($col['bloco_modelo_nome'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($col['bloco_titulo'] ?? ''));
            }
            if ($label === '') {
                $label = 'Bloco #' . $bid;
            }

            return [
                'key' => 'bloco_' . $bid,
                'label' => $label,
                'ord_primary' => 200000,
                'ord_secondary' => $bid,
            ];
        }

        return [
            'key' => 'sem_bloco',
            'label' => 'Sem bloco',
            'ord_primary' => 500000,
            'ord_secondary' => 0,
        ];
    }

    public function buildProvasMatrizPorBlocoAplicado(array $rawRows): array
    {
        $vazio = ['tabelas' => [], 'tem_dados' => false];
        if ($rawRows === []) {
            return $vazio;
        }

        $colCandidates = [];
        $grid = [];
        $materiaRotulo = [];

        foreach ($rawRows as $row) {
            $blocoId = isset($row['bloco_id']) ? (int) $row['bloco_id'] : 0;
            $materiaExpr = trim((string) ($row['prova_materia'] ?? ''));
            $mk = $materiaExpr !== '' ? mb_strtolower($materiaExpr, 'UTF-8') : '_sem_materia';
            if (!isset($materiaRotulo[$mk])) {
                $materiaRotulo[$mk] = $materiaExpr !== '' ? $materiaExpr : '—';
            }

            $totalQ = (int) ($row['prova_total_questoes'] ?? 0);
            $acertos = (int) ($row['prova_acertos'] ?? 0);

            $dataProvaBanco = $row['bloco_data_prova'] ?? null;
            $iniciado = $row['iniciado_em'] ?? null;
            $tsColuna = 0;
            if (!empty($dataProvaBanco)) {
                $tsColuna = (int) strtotime((string) $dataProvaBanco);
            }
            if ($tsColuna <= 0 && !empty($iniciado)) {
                $tsColuna = (int) strtotime((string) $iniciado);
            }

            if ($blocoId > 0) {
                $colKey = 'b:' . $blocoId;
            } else {
                $dia = $tsColuna > 0 ? date('Y-m-d', $tsColuna) : 'sem_data';
                $colKey = 'd:' . $dia;
            }

            if (!isset($colCandidates[$colKey])) {
                $colCandidates[$colKey] = [
                    'key' => $colKey,
                    'bloco_id' => $blocoId,
                    'sort_ts' => $tsColuna > 0 ? $tsColuna : PHP_INT_MAX,
                    'data_label' => $tsColuna > 0 ? date('d/m/Y', $tsColuna) : '—',
                    'bloco_titulo' => trim((string) ($row['bloco_titulo'] ?? '')),
                    'bloco_modelo_id' => (int) ($row['bloco_modelo_id'] ?? 0),
                    'bloco_modelo_nome' => trim((string) ($row['bloco_modelo_nome'] ?? '')),
                    'bloco_bimestre' => !empty($row['bloco_bimestre']) ? (int) $row['bloco_bimestre'] : 0,
                    'bloco_ano_letivo' => !empty($row['bloco_ano_letivo']) ? (int) $row['bloco_ano_letivo'] : 0,
                    'sample_prova_titulo' => '',
                ];
            } else {
                $colCandidates[$colKey]['sort_ts'] = min(
                    (int) $colCandidates[$colKey]['sort_ts'],
                    $tsColuna > 0 ? $tsColuna : PHP_INT_MAX
                );
                if ($colCandidates[$colKey]['data_label'] === '—' && $tsColuna > 0) {
                    $colCandidates[$colKey]['data_label'] = date('d/m/Y', $tsColuna);
                }
                if ($colCandidates[$colKey]['bloco_titulo'] === '' && !empty($row['bloco_titulo'])) {
                    $colCandidates[$colKey]['bloco_titulo'] = trim((string) $row['bloco_titulo']);
                }
                if (($colCandidates[$colKey]['bloco_modelo_id'] ?? 0) <= 0 && !empty($row['bloco_modelo_id'])) {
                    $colCandidates[$colKey]['bloco_modelo_id'] = (int) $row['bloco_modelo_id'];
                }
                if (($colCandidates[$colKey]['bloco_modelo_nome'] ?? '') === '' && !empty($row['bloco_modelo_nome'])) {
                    $colCandidates[$colKey]['bloco_modelo_nome'] = trim((string) $row['bloco_modelo_nome']);
                }
            }
            $provaTit = trim((string) ($row['prova_titulo'] ?? ''));
            if ($provaTit !== '' && $colCandidates[$colKey]['sample_prova_titulo'] === '') {
                $colCandidates[$colKey]['sample_prova_titulo'] = $provaTit;
            }
            if ($colCandidates[$colKey]['bloco_titulo'] === '' && $provaTit !== '') {
                if ($colCandidates[$colKey]['sample_prova_titulo'] === '') {
                    $colCandidates[$colKey]['sample_prova_titulo'] = $provaTit;
                }
            }

            if (!isset($grid[$mk])) {
                $grid[$mk] = [];
            }
            if (!isset($grid[$mk][$colKey])) {
                $grid[$mk][$colKey] = ['q' => 0, 'acertos' => 0];
            }
            $grid[$mk][$colKey]['q'] += $totalQ;
            $grid[$mk][$colKey]['acertos'] += $acertos;
        }

        uasort($colCandidates, function ($a, $b) {
            if ($a['sort_ts'] === $b['sort_ts']) {
                return strcmp((string) $a['key'], (string) $b['key']);
            }

            return $a['sort_ts'] <=> $b['sort_ts'];
        });

        $colunas = [];
        foreach ($colCandidates as $meta) {
            $colunas[] = [
                'key' => $meta['key'],
                'titulo' => '',
                'data_label' => $meta['data_label'],
                'bloco_titulo' => $meta['bloco_titulo'],
                'bloco_modelo_id' => (int) ($meta['bloco_modelo_id'] ?? 0),
                'bloco_modelo_nome' => (string) ($meta['bloco_modelo_nome'] ?? ''),
                'bloco_bimestre' => (int) ($meta['bloco_bimestre'] ?? 0),
                'bloco_ano_letivo' => (int) ($meta['bloco_ano_letivo'] ?? 0),
                'sample_prova_titulo' => (string) ($meta['sample_prova_titulo'] ?? ''),
                'bloco_id' => (int) $meta['bloco_id'],
                'sort_ts' => (int) $meta['sort_ts'],
            ];
        }

        $porGrupo = [];
        foreach ($colunas as $col) {
            $g = $this->inferGrupoTabelaBlocoLetra($col);
            $bimestreCol = (int) ($col['bloco_bimestre'] ?? 0);
            $gk = $g['key'] . '|bim:' . $bimestreCol;
            if (!isset($porGrupo[$gk])) {
                $porGrupo[$gk] = [
                    'grupo' => $g,
                    'bimestre' => $bimestreCol,
                    'ano_letivo' => (int) ($col['bloco_ano_letivo'] ?? 0),
                    'colunas' => [],
                ];
            }
            $porGrupo[$gk]['colunas'][] = $col;
        }

        $tabelas = [];
        foreach ($porGrupo as $gk => $pacote) {
            $grupo = $pacote['grupo'];
            $bimestrePacote = (int) ($pacote['bimestre'] ?? 0);
            $anoPacote = (int) ($pacote['ano_letivo'] ?? 0);
            $cols = $pacote['colunas'];
            usort($cols, function ($a, $b) {
                if (($a['sort_ts'] ?? 0) === ($b['sort_ts'] ?? 0)) {
                    return strcmp((string) ($a['key'] ?? ''), (string) ($b['key'] ?? ''));
                }

                return ($a['sort_ts'] ?? 0) <=> ($b['sort_ts'] ?? 0);
            });
            $idx = 0;
            foreach ($cols as &$c) {
                $idx++;
                $c['titulo'] = 'S' . $idx;
            }
            unset($c);

            $colKeysOrd = array_column($cols, 'key');
            $materiasSaida = [];
            foreach ($grid as $mk => $celdas) {
                $linhaCelulas = [];
                $tAcertos = 0;
                $tErros = 0;
                $tQ = 0;
                foreach ($colKeysOrd as $ck) {
                    $c = $celdas[$ck] ?? ['q' => 0, 'acertos' => 0];
                    $q = (int) ($c['q'] ?? 0);
                    $a = (int) ($c['acertos'] ?? 0);
                    $e = max(0, $q - $a);
                    $linhaCelulas[$ck] = ['q' => $q, 'acertos' => $a, 'erros' => $e];
                    $tAcertos += $a;
                    $tErros += $e;
                    $tQ += $q;
                }
                $materiasSaida[] = [
                    'materia_key' => $mk,
                    'materia' => $materiaRotulo[$mk] ?? '—',
                    'celdas' => $linhaCelulas,
                    'totais' => [
                        'acertos' => $tAcertos,
                        'erros' => $tErros,
                        'q' => $tQ,
                    ],
                ];
            }

            // Só matérias que tiveram prova neste bloco (Q > 0 nas colunas desta tabela).
            $materiasSaida = array_values(array_filter($materiasSaida, static function ($linha) {
                $tQ = (int) (($linha['totais']['q'] ?? 0));

                return $tQ > 0;
            }));

            usort($materiasSaida, function ($a, $b) {
                return strcasecmp((string) $a['materia'], (string) $b['materia']);
            });

            if ($cols === [] || $materiasSaida === []) {
                continue;
            }

            $tituloSecao = $grupo['label'];
            if ($bimestrePacote > 0) {
                $tituloSecao .= ' — ' . $bimestrePacote . 'º Bimestre';
            }

            $tabelas[] = [
                'grupo_key' => $gk,
                'titulo_secao' => $tituloSecao,
                'bimestre' => $bimestrePacote,
                'ano_letivo' => $anoPacote,
                'colunas' => $cols,
                'materias' => $materiasSaida,
                'ord_primary' => (int) $grupo['ord_primary'],
                'ord_secondary' => (int) $grupo['ord_secondary'],
                'tem_dados' => true,
            ];
        }

        usort($tabelas, function ($a, $b) {
            if (($a['bimestre'] ?? 0) !== ($b['bimestre'] ?? 0)) {
                return ($a['bimestre'] ?? 0) <=> ($b['bimestre'] ?? 0);
            }
            if ($a['ord_primary'] === $b['ord_primary']) {
                return $a['ord_secondary'] <=> $b['ord_secondary'];
            }

            return $a['ord_primary'] <=> $b['ord_primary'];
        });

        $temDados = false;
        foreach ($tabelas as $t) {
            if (!empty($t['tem_dados'])) {
                $temDados = true;
                break;
            }
        }

        return [
            'tabelas' => $tabelas,
            'tem_dados' => $temDados,
        ];
    }
}
}
