<?php
/**
 * EducaTudo - AdminJornadaController
 * Gerencia jornadas do lado do admin
 */

if (!class_exists('AdminJourneyController')) {
class AdminJourneyController extends BaseController
{
    private $authManager;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        
        // Verifica se está logado
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
        
        // Verifica se é admin
        $user = $this->authManager->getUser();
        if ($user && $user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }
    
    /**
     * Retorna lista de matérias para select (jornadas_materias + materias, sem duplicar id).
     */
    private function materiasParaJornada()
    {
        $jm = $this->db->fetchAll("SELECT id, nome, cor, icone FROM jornadas_materias ORDER BY nome ASC");
        $mat = [];
        try {
            $mat = $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome ASC");
        } catch (\Throwable $e) {
            // tabela materias pode não existir ou ter estrutura diferente
        }
        $idsJm = array_column($jm, 'id');
        $materias = $jm;
        foreach ($mat as $m) {
            if (!in_array($m['id'], $idsJm, true)) {
                $materias[] = $m;
            }
        }
        usort($materias, function ($a, $b) { return strcmp($a['nome'] ?? '', $b['nome'] ?? ''); });
        return $materias;
    }
    
    /**
     * Lista todas as jornadas da escola
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        
        $filtro_professor = $_GET['professor_id'] ?? '';
        $filtro_turma = $_GET['turma_id'] ?? '';
        $filtro_materia = $_GET['materia_id'] ?? '';
        $filtro_tipo_ensino = $_GET['tipo_ensino'] ?? '';
        $filtro_bimestre = $_GET['bimestre'] ?? '';
        $filtro_avaliativo = $_GET['avaliativo'] ?? '';
        $filtro_busca = trim((string) ($_GET['busca'] ?? ''));
        
        $where = ["(j.ativo = 1 OR j.ativo IS NULL)"];
        $params = [];
        if ($filtro_professor !== '') {
            $where[] = "j.professor_id = :professor_id";
            $params['professor_id'] = $filtro_professor;
        }
        if ($filtro_turma !== '') {
            $where[] = "j.turma_id = :turma_id";
            $params['turma_id'] = $filtro_turma;
        }
        if ($filtro_materia !== '') {
            $where[] = "j.materia_id = :materia_id";
            $params['materia_id'] = $filtro_materia;
        }
        if ($filtro_tipo_ensino !== '') {
            $where[] = "t.tipo_ensino = :tipo_ensino";
            $params['tipo_ensino'] = $filtro_tipo_ensino;
        }
        if ($filtro_bimestre !== '') {
            $where[] = "j.bimestre = :bimestre";
            $params['bimestre'] = (int)$filtro_bimestre;
        }
        if ($filtro_avaliativo !== '') {
            $where[] = "j.avaliativo = :avaliativo";
            $params['avaliativo'] = (int)$filtro_avaliativo;
        }
        if ($filtro_busca !== '') {
            $where[] = "(j.titulo LIKE :busca_titulo OR p.nome LIKE :busca_professor OR t.nome LIKE :busca_turma OR COALESCE(jm.nome, m.nome) LIKE :busca_materia)";
            $buscaLike = '%' . $filtro_busca . '%';
            $params['busca_titulo'] = $buscaLike;
            $params['busca_professor'] = $buscaLike;
            $params['busca_turma'] = $buscaLike;
            $params['busca_materia'] = $buscaLike;
        }
        $whereSql = implode(' AND ', $where);
        
        // Paginação: evita carregar centenas de jornadas e dezenas de queries por página
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $offset = ($page - 1) * $perPage;
        
        $totalJornadas = (int) $this->db->fetch(
            "SELECT COUNT(*) as total FROM jornadas j JOIN professores p ON j.professor_id = p.id JOIN turmas t ON j.turma_id = t.id LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id LEFT JOIN materias m ON j.materia_id = m.id WHERE {$whereSql}",
            $params
        )['total'];
        $totalPages = $totalJornadas > 0 ? (int) ceil($totalJornadas / $perPage) : 1;
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;
        
        // Busca jornadas da página (com filtros). Sem subqueries por linha para acelerar (com e sem filtro).
        $jornadas = $this->db->fetchAll(
            "SELECT j.*, 
                    p.nome as professor_nome,
                    t.nome as turma_nome,
                    COALESCE(jm.nome, m.nome) as materia_nome,
                    COALESCE(jm.cor, NULL) as materia_cor,
                    COALESCE(jm.icone, NULL) as materia_icone
             FROM jornadas j
             JOIN professores p ON j.professor_id = p.id
             JOIN turmas t ON j.turma_id = t.id
             LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
             LEFT JOIN materias m ON j.materia_id = m.id
             WHERE {$whereSql}
             ORDER BY j.created_at DESC
             LIMIT " . (int) $perPage . " OFFSET " . (int) $offset,
            $params
        );
        
        // Pré-busca todos os IDs de turmas usados nesta página (evita N queries no loop)
        $turmaIds = [];
        foreach ($jornadas as $j) {
            $est = json_decode($j['estrutura'] ?? '{}', true) ?: [];
            $ts = $est['turmas_selecionadas'] ?? [];
            if (!empty($ts) && is_array($ts)) {
                foreach ($ts as $tid) {
                    $turmaIds[(int)$tid] = true;
                }
            }
            if (!empty($j['turma_id'])) {
                $turmaIds[(int)$j['turma_id']] = true;
            }
        }
        $turmasMap = [];
        if (!empty($turmaIds)) {
            $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
            $turmasList = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome", array_keys($turmaIds));
            foreach ($turmasList as $t) {
                $turmasMap[(int)$t['id']] = $t['nome'];
            }
        }
        
        // Uma única query: total de alunos por turma (evita N queries no loop)
        $totalAlunosPorTurma = [];
        if (!empty($turmaIds)) {
            $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
            $rows = $this->db->fetchAll(
                "SELECT turma_id, COUNT(*) as total FROM alunos WHERE turma_id IN ($placeholders) AND ativo = 1 GROUP BY turma_id",
                array_keys($turmaIds)
            );
            foreach ($rows as $r) {
                $totalAlunosPorTurma[(int)$r['turma_id']] = (int)$r['total'];
            }
        }
        
        // Enriquece cada jornada com turmas e datas (realizou/não realizou só na tela de detalhe para performance)
        foreach ($jornadas as &$jornada) {
            $estrutura = json_decode($jornada['estrutura'] ?? '{}', true) ?: [];
            $turmasSelecionadas = $estrutura['turmas_selecionadas'] ?? [];
            $alunosSelecionados = $estrutura['alunos_selecionados'] ?? [];
            $tipoSelecaoAlunos = $estrutura['tipo_selecao_alunos'] ?? 'todos';
            $jornada['data_inicio'] = $estrutura['data_inicio'] ?? null;
            $jornada['data_fim'] = $estrutura['data_fim'] ?? null;
            $jornada['hora_inicio'] = $estrutura['hora_inicio'] ?? null;
            $jornada['hora_fim'] = $estrutura['hora_fim'] ?? null;

            // Nomes das turmas (usa mapa pré-carregado)
            if (!empty($turmasSelecionadas) && is_array($turmasSelecionadas)) {
                $jornada['total_turmas_selecionadas'] = count($turmasSelecionadas);
                $nomes = [];
                foreach ($turmasSelecionadas as $tid) {
                    if (isset($turmasMap[(int)$tid])) {
                        $nomes[] = $turmasMap[(int)$tid];
                    }
                }
                sort($nomes);
                $jornada['turmas_selecionadas_nomes'] = $nomes;
            } else {
                $jornada['total_turmas_selecionadas'] = 1;
                $jornada['turmas_selecionadas_nomes'] = $jornada['turma_nome'] ? [$jornada['turma_nome']] : [];
            }
            
            // Total de alunos (usa mapa pré-carregado)
            if ($tipoSelecaoAlunos === 'todos') {
                $ids = !empty($turmasSelecionadas) && is_array($turmasSelecionadas)
                    ? $turmasSelecionadas
                    : [$jornada['turma_id']];
                $total = 0;
                foreach ($ids as $tid) {
                    $total += $totalAlunosPorTurma[(int)$tid] ?? 0;
                }
                $jornada['total_alunos_selecionados'] = $total;
            } else {
                $jornada['total_alunos_selecionados'] = !empty($alunosSelecionados) && is_array($alunosSelecionados)
                    ? count($alunosSelecionados)
                    : 0;
            }
            
            // Listagem não calcula realizou/não realizou (query pesada); ver na tela da jornada
            $jornada['realizou_count'] = null;
            $jornada['nao_realizou_count'] = null;
        }
        unset($jornada);
        
        // Estatísticas leves (sem subqueries em tabelas grandes) para resposta rápida com ou sem filtro
        $statsFrom = "FROM jornadas j JOIN professores p ON j.professor_id = p.id JOIN turmas t ON j.turma_id = t.id LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id LEFT JOIN materias m ON j.materia_id = m.id WHERE {$whereSql}";
        $statsParams = $params;
        $stats = $this->db->fetch(
            "SELECT 
                COUNT(*) as total_jornadas,
                COUNT(CASE WHEN j.status = 'ativa' THEN 1 END) as jornadas_ativas,
                COUNT(CASE WHEN j.status = 'pausada' THEN 1 END) as jornadas_pausadas,
                COUNT(CASE WHEN j.status = 'finalizada' THEN 1 END) as jornadas_finalizadas,
                0 as total_aulas,
                0 as total_exercicios,
                0 as duvidas_pendentes
             " . $statsFrom,
            $statsParams
        );
        
        // Busca professores
        $professores = $this->db->fetchAll(
            "SELECT p.*, COUNT(j.id) as total_jornadas
             FROM professores p
             LEFT JOIN jornadas j ON p.id = j.professor_id
             GROUP BY p.id
             ORDER BY p.nome"
        );
        
        // Busca turmas
        $turmas = $this->db->fetchAll(
            "SELECT t.*, COUNT(j.id) as total_jornadas
             FROM turmas t
             LEFT JOIN jornadas j ON t.id = j.turma_id
             GROUP BY t.id
             ORDER BY t.nome"
        );
        
        // Matérias e tipos de ensino para os filtros
        $materias = $this->db->fetchAll("SELECT id, nome FROM jornadas_materias ORDER BY nome ASC");
        $tipos_ensino = $this->db->fetchAll(
            "SELECT DISTINCT tipo_ensino as tipo_ensino FROM turmas WHERE tipo_ensino IS NOT NULL AND tipo_ensino != '' ORDER BY tipo_ensino ASC"
        );
        
        $data = [
            'title' => 'Gestão de Jornadas - EducaTudo',
            'user' => $user,
            'jornadas' => $jornadas,
            'stats' => $stats,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_jornadas' => $totalJornadas,
                'total_pages' => $totalPages,
            ],
            'professores' => $professores,
            'turmas' => $turmas,
            'materias' => $materias,
            'tipos_ensino' => $tipos_ensino,
            'filtro_professor' => $filtro_professor,
            'filtro_turma' => $filtro_turma,
            'filtro_materia' => $filtro_materia,
            'filtro_tipo_ensino' => $filtro_tipo_ensino,
            'filtro_bimestre' => $filtro_bimestre,
            'filtro_avaliativo' => $filtro_avaliativo,
            'filtro_busca' => $filtro_busca,
            'current_page' => 'journeys',
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('admin', 'admin/journeys/index', $data);
    }
    
    /**
     * Exibe detalhes de uma jornada específica
     */
    public function show($id)
    {
        $user = $this->authManager->getUser();
        
        // Busca dados da jornada
        $jornada = $this->db->fetch(
            "SELECT j.*, 
                    p.nome as professor_nome,
                    t.nome as turma_nome,
                    jm.nome as materia_nome,
                    jm.cor as materia_cor,
                    jm.icone as materia_icone
             FROM jornadas j
             JOIN professores p ON j.professor_id = p.id
             JOIN turmas t ON j.turma_id = t.id
             LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
             WHERE j.id = :jornada_id",
            ['jornada_id' => $id]
        );
        
        if (!$jornada) {
            throw new Exception('Jornada não encontrada');
        }
        if (isset($jornada['ativo']) && (int)$jornada['ativo'] === 0) {
            $_SESSION['error_message'] = 'Esta jornada foi removida da lista e não está disponível.';
            $this->redirect('/admin/jornadas');
            return;
        }
        
        // Busca aulas da jornada
            $aulas = $this->db->fetchAll(
            "SELECT ja.*,
                    (SELECT COUNT(*) FROM jornadas_exercicios je WHERE je.aula_id = ja.id) as total_exercicios,
                    (SELECT COUNT(*) FROM jornadas_duvidas jd WHERE jd.aula_id = ja.id) as total_duvidas
             FROM jornadas_aulas ja
             WHERE ja.jornada_id = :jornada_id
             ORDER BY ja.ordem ASC",
            ['jornada_id' => $id]
        );
        
        // Busca exercícios da jornada
        $exercicios = $this->db->fetchAll(
            "SELECT je.*, ja.nome_aula
             FROM jornadas_exercicios je
             LEFT JOIN jornadas_aulas ja ON je.aula_id = ja.id
             WHERE je.jornada_id = :jornada_id
             ORDER BY je.created_at ASC",
            ['jornada_id' => $id]
        );
        
        // Busca alunos da turma com progresso
        $alunos = $this->db->fetchAll(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM jornadas_progresso_alunos jpa WHERE jpa.aluno_id = a.id AND jpa.jornada_id = :jornada_id_1 AND jpa.status = 'concluido') as atividades_concluidas,
                    (SELECT SUM(jpa.tempo_gasto) FROM jornadas_progresso_alunos jpa WHERE jpa.aluno_id = a.id AND jpa.jornada_id = :jornada_id_2) as tempo_total,
                    (SELECT AVG(jpa.pontuacao) FROM jornadas_progresso_alunos jpa WHERE jpa.aluno_id = a.id AND jpa.jornada_id = :jornada_id_3 AND jpa.pontuacao > 0) as pontuacao_media
             FROM alunos a
             WHERE a.turma_id = :turma_id AND a.ativo = 1
             ORDER BY a.nome",
            [
                'jornada_id_1' => $id,
                'jornada_id_2' => $id,
                'jornada_id_3' => $id,
                'turma_id' => $jornada['turma_id']
            ]
        );
        
        // Busca dúvidas da jornada
        $duvidas = $this->db->fetchAll(
            "SELECT jd.*, a.nome as aluno_nome, ja.nome_aula
             FROM jornadas_duvidas jd
             JOIN alunos a ON jd.aluno_id = a.id
             JOIN jornadas_aulas ja ON jd.aula_id = ja.id
             WHERE ja.jornada_id = :jornada_id
             ORDER BY jd.created_at DESC",
            ['jornada_id' => $id]
        );
        
        // Busca relatórios dos alunos
        $relatorios = $this->db->fetchAll(
            "SELECT jr.*, a.nome as aluno_nome
             FROM jornadas_relatorios jr
             JOIN alunos a ON jr.aluno_id = a.id
             WHERE jr.jornada_id = :jornada_id
             ORDER BY jr.gerado_em DESC",
            ['jornada_id' => $id]
        );
        
        // Turmas da jornada e realizou/não realizou (como na visão do professor)
        $estrutura = json_decode($jornada['estrutura'] ?? '{}', true) ?: [];
        $turmasSelecionadas = $estrutura['turmas_selecionadas'] ?? [];
        $alunosSelecionados = $estrutura['alunos_selecionados'] ?? [];
        $tipoSelecaoAlunos = $estrutura['tipo_selecao_alunos'] ?? 'todos';
        if (!empty($turmasSelecionadas) && is_array($turmasSelecionadas)) {
            $placeholders = implode(',', array_fill(0, count($turmasSelecionadas), '?'));
            $turmasNomes = $this->db->fetchAll(
                "SELECT id, nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
                array_values($turmasSelecionadas)
            );
            $jornada['turmas_selecionadas_nomes'] = array_column($turmasNomes, 'nome');
        } else {
            $jornada['turmas_selecionadas_nomes'] = $jornada['turma_nome'] ? [$jornada['turma_nome']] : [];
        }
        $whereTurma = "(a.turma_id = :turma_id OR EXISTS (
            SELECT 1 FROM alunos_turmas_historico h
            WHERE h.aluno_id = a.id AND h.turma_id = :turma_id_hist
        ))";
        $paramsShow = [
            'jornada_id_1' => $id,
            'jornada_id_2' => $id,
            'turma_id' => $jornada['turma_id'],
            'turma_id_hist' => $jornada['turma_id']
        ];
        if (!empty($turmasSelecionadas) && is_array($turmasSelecionadas)) {
            $turmaClauses = [];
            foreach (array_values($turmasSelecionadas) as $idx => $turmaId) {
                $paramAtual = 'turma_id_' . $idx;
                $paramHist = 'turma_id_hist_' . $idx;
                $turmaClauses[] = "(a.turma_id = :{$paramAtual} OR EXISTS (
                    SELECT 1 FROM alunos_turmas_historico h
                    WHERE h.aluno_id = a.id AND h.turma_id = :{$paramHist}
                ))";
                $paramsShow[$paramAtual] = (int)$turmaId;
                $paramsShow[$paramHist] = (int)$turmaId;
            }
            if (!empty($turmaClauses)) {
                $whereTurma = "(" . implode(' OR ', $turmaClauses) . ")";
                unset($paramsShow['turma_id']);
                unset($paramsShow['turma_id_hist']);
            }
        }
        if ($tipoSelecaoAlunos === 'selecionados' && !empty($alunosSelecionados) && is_array($alunosSelecionados)) {
            $alunoParams = [];
            foreach (array_values($alunosSelecionados) as $idx => $alunoId) {
                $paramName = 'aluno_id_' . $idx;
                $alunoParams[] = ':' . $paramName;
                $paramsShow[$paramName] = (int)$alunoId;
            }
            if (!empty($alunoParams)) {
                $whereTurma .= " AND a.id IN (" . implode(', ', $alunoParams) . ")";
            }
        }
        try {
            // Realizou = concluiu a jornada OU fez pelo menos um exercício (alinhado ao dashboard de exercícios)
            $alunosStatusShow = $this->db->fetchAll(
                "SELECT a.id,
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM jornadas_progresso_alunos jpa 
                            WHERE jpa.aluno_id = a.id AND jpa.jornada_id = :jornada_id_1
                            AND jpa.status = 'concluido' AND (jpa.atividade_tipo IS NULL OR jpa.atividade_tipo = 'jornada_concluida')
                            AND jpa.modulo_id IS NULL AND jpa.aula_id IS NULL AND jpa.exercicio_id IS NULL AND jpa.exercicio_modulo_id IS NULL
                        ) THEN 'concluida'
                        WHEN EXISTS (
                            SELECT 1 FROM jornadas_progresso_alunos jpa 
                            WHERE jpa.aluno_id = a.id AND jpa.jornada_id = :jornada_id_2
                            AND jpa.resposta IS NOT NULL
                            AND (
                                (jpa.atividade_tipo = 'exercicio' AND jpa.exercicio_id IS NOT NULL)
                                OR (jpa.atividade_tipo = 'exercicio_modulo' AND jpa.exercicio_modulo_id IS NOT NULL)
                            )
                        ) THEN 'visualizado'
                        ELSE 'nao_visualizado'
                    END as status_jornada
                 FROM alunos a
                 WHERE $whereTurma AND a.ativo = 1",
                $paramsShow
            );
            $realizou = 0;
            $naoRealizou = 0;
            foreach ($alunosStatusShow as $row) {
                $st = $row['status_jornada'] ?? 'nao_visualizado';
                if ($st === 'concluida' || $st === 'visualizado') {
                    $realizou++;
                } else {
                    $naoRealizou++;
                }
            }
            $jornada['realizou_count'] = $realizou;
            $jornada['nao_realizou_count'] = $naoRealizou;
            $jornada['total_alunos_jornada'] = count($alunosStatusShow);
        } catch (Exception $e) {
            $jornada['realizou_count'] = 0;
            $jornada['nao_realizou_count'] = 0;
            $jornada['total_alunos_jornada'] = 0;
        }
        if (!isset($jornada['total_alunos_jornada'])) {
            $jornada['total_alunos_jornada'] = (int)($jornada['realizou_count'] ?? 0) + (int)($jornada['nao_realizou_count'] ?? 0);
        }
        
        // Dashboard de Exercícios (mesma lógica do professor)
        $dashboard_exercicios = [];
        $exerciciosModulos = $this->db->fetchAll(
            "SELECT 
                jme.id,
                jme.titulo,
                jme.enunciado,
                jm.titulo as modulo_nome,
                COUNT(DISTINCT jpa.id) as total_respostas,
                SUM(CASE WHEN jpa.pontuacao > 0 THEN 1 ELSE 0 END) as total_acertos,
                SUM(CASE WHEN jpa.pontuacao = 0 OR jpa.pontuacao IS NULL THEN 1 ELSE 0 END) as total_erros,
                COALESCE(AVG(CASE WHEN jpa.pontuacao > 0 THEN 1 ELSE 0 END) * 100, 0) as taxa_acerto,
                COALESCE(AVG(CASE WHEN jpa.pontuacao = 0 OR jpa.pontuacao IS NULL THEN 1 ELSE 0 END) * 100, 0) as taxa_erro
             FROM jornadas_modulos jm
             LEFT JOIN jornadas_modulos_exercicios jme ON jme.modulo_id = jm.id
             LEFT JOIN jornadas_progresso_alunos jpa ON jpa.exercicio_modulo_id = jme.id 
                 AND jpa.jornada_id = jm.jornada_id
                 AND jpa.atividade_tipo = 'exercicio_modulo'
                 AND jpa.resposta IS NOT NULL
             WHERE jm.jornada_id = :jornada_id 
                 AND jm.status = 'ativo'
                 AND jme.id IS NOT NULL
             GROUP BY jme.id, jme.titulo, jme.enunciado, jm.titulo
             HAVING total_respostas > 0",
            ['jornada_id' => $id]
        );
        $exerciciosAntigos = $this->db->fetchAll(
            "SELECT 
                je.id,
                je.titulo,
                je.descricao as enunciado,
                'Exercício' as modulo_nome,
                COUNT(DISTINCT jpa.id) as total_respostas,
                SUM(CASE WHEN jpa.pontuacao > 0 THEN 1 ELSE 0 END) as total_acertos,
                SUM(CASE WHEN jpa.pontuacao = 0 OR jpa.pontuacao IS NULL THEN 1 ELSE 0 END) as total_erros,
                COALESCE(AVG(CASE WHEN jpa.pontuacao > 0 THEN 1 ELSE 0 END) * 100, 0) as taxa_acerto,
                COALESCE(AVG(CASE WHEN jpa.pontuacao = 0 OR jpa.pontuacao IS NULL THEN 1 ELSE 0 END) * 100, 0) as taxa_erro
             FROM jornadas_exercicios je
             LEFT JOIN jornadas_progresso_alunos jpa ON jpa.exercicio_id = je.id 
                 AND jpa.jornada_id = je.jornada_id
                 AND jpa.atividade_tipo = 'exercicio'
                 AND jpa.resposta IS NOT NULL
             WHERE je.jornada_id = :jornada_id
             GROUP BY je.id, je.titulo, je.descricao
             HAVING total_respostas > 0",
            ['jornada_id' => $id]
        );
        $dashboard_exercicios = array_merge($exerciciosModulos, $exerciciosAntigos);
        $tem_exercicios = true;
        $stats_exercicios = [
            'total_exercicios_com_respostas' => count($dashboard_exercicios),
            'exercicios_alto_erro' => [],
            'mais_acertados' => [],
            'mais_errados' => []
        ];
        if ($tem_exercicios && !empty($dashboard_exercicios)) {
            foreach ($dashboard_exercicios as $ex) {
                $taxaErro = (float)($ex['taxa_erro'] ?? 0);
                if ($taxaErro > 60) {
                    $stats_exercicios['exercicios_alto_erro'][] = [
                        'titulo' => $ex['titulo'] ?? 'Sem título',
                        'enunciado' => $ex['enunciado'] ?? '',
                        'modulo' => $ex['modulo_nome'] ?? '',
                        'taxa_erro' => round($taxaErro, 1),
                        'total_respostas' => (int)($ex['total_respostas'] ?? 0),
                        'total_erros' => (int)($ex['total_erros'] ?? 0)
                    ];
                }
            }
            // Lista todos na ordem mais acertado primeiro (taxa_acerto DESC)
            $maisAcertados = $dashboard_exercicios;
            usort($maisAcertados, function($a, $b) {
                return ((float)($b['taxa_acerto'] ?? 0)) <=> ((float)($a['taxa_acerto'] ?? 0));
            });
            $stats_exercicios['mais_acertados'] = $maisAcertados;
            // Lista todos na ordem mais errado primeiro (taxa_erro DESC)
            $maisErrados = $dashboard_exercicios;
            usort($maisErrados, function($a, $b) {
                return ((float)($b['taxa_erro'] ?? 0)) <=> ((float)($a['taxa_erro'] ?? 0));
            });
            $stats_exercicios['mais_errados'] = $maisErrados;
        }
        $alunos_atencao = [];
        if ($tem_exercicios) {
            $turmasIdsAtencao = array_values(array_unique(array_filter(
                array_merge([(int)$jornada['turma_id']], array_map('intval', $turmasSelecionadas ?? []))
            )));
            if (empty($turmasIdsAtencao)) {
                $turmasIdsAtencao = [(int)$jornada['turma_id']];
            }
            $placeholdersAtencao = implode(',', array_fill(0, count($turmasIdsAtencao), '?'));
            $paramsAtencao = array_merge([$id], $turmasIdsAtencao);
            $alunosComBaixoDesempenho = $this->db->fetchAll(
                "SELECT 
                    a.id as aluno_id,
                    a.nome as aluno_nome,
                    a.ra as aluno_ra,
                    COUNT(jpa.id) as total_respostas,
                    SUM(CASE WHEN jpa.pontuacao > 0 THEN 1 ELSE 0 END) as total_acertos,
                    SUM(CASE WHEN jpa.pontuacao = 0 OR jpa.pontuacao IS NULL THEN 1 ELSE 0 END) as total_erros,
                    CASE WHEN COUNT(jpa.id) > 0 THEN ROUND((SUM(CASE WHEN jpa.pontuacao = 0 OR jpa.pontuacao IS NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(jpa.id)), 1) ELSE 0 END as taxa_erro,
                    CASE WHEN COUNT(jpa.id) > 0 THEN ROUND((SUM(CASE WHEN jpa.pontuacao > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(jpa.id)), 1) ELSE 0 END as taxa_acerto
                 FROM alunos a
                 LEFT JOIN jornadas_progresso_alunos jpa ON jpa.aluno_id = a.id 
                     AND jpa.jornada_id = ?
                     AND jpa.resposta IS NOT NULL
                     AND (jpa.atividade_tipo = 'exercicio_modulo' OR jpa.atividade_tipo = 'exercicio')
                 WHERE a.turma_id IN ($placeholdersAtencao) AND a.ativo = 1
                 GROUP BY a.id, a.nome, a.ra
                 HAVING total_erros > 0 AND taxa_erro > 40
                 ORDER BY taxa_erro DESC, total_erros DESC
                 LIMIT 10",
                $paramsAtencao
            );
            foreach ($alunosComBaixoDesempenho as $aluno) {
                $alunos_atencao[] = [
                    'id' => $aluno['aluno_id'],
                    'nome' => $aluno['aluno_nome'],
                    'ra' => $aluno['aluno_ra'],
                    'total_exercicios' => (int)($aluno['total_respostas'] ?? 0),
                    'total_acertos' => (int)($aluno['total_acertos'] ?? 0),
                    'total_erros' => (int)($aluno['total_erros'] ?? 0),
                    'total_respostas' => (int)($aluno['total_respostas'] ?? 0),
                    'taxa_erro' => (float)($aluno['taxa_erro'] ?? 0),
                    'taxa_acerto' => (float)($aluno['taxa_acerto'] ?? 0)
                ];
            }
        }
        
        $data = [
            'title' => $jornada['titulo'] . ' - Admin - EducaTudo',
            'user' => $user,
            'jornada' => $jornada,
            'aulas' => $aulas,
            'exercicios' => $exercicios,
            'alunos' => $alunos,
            'duvidas' => $duvidas,
            'relatorios' => $relatorios,
            'tem_exercicios' => $tem_exercicios,
            'dashboard_exercicios' => $dashboard_exercicios,
            'stats_exercicios' => $stats_exercicios,
            'alunos_atencao' => $alunos_atencao,
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('admin', 'admin/journeys/show', $data);
    }
    
    /**
     * Resultados da Jornada - exercícios por aluno (mesma informação do professor)
     */
    public function exerciciosAlunos($id)
    {
        $user = $this->authManager->getUser();
        $id = (int)$id;
        $jornada = $this->db->fetch(
            "SELECT j.*, jm.nome as materia_nome, t.nome as turma_nome
             FROM jornadas j
             LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
             LEFT JOIN turmas t ON j.turma_id = t.id
             WHERE j.id = :jornada_id",
            ['jornada_id' => $id]
        );
        if (!$jornada) {
            $_SESSION['error_message'] = 'Jornada não encontrada';
            $this->redirect('/admin/jornadas');
            return;
        }
        if (isset($jornada['ativo']) && (int)$jornada['ativo'] === 0) {
            $_SESSION['error_message'] = 'Esta jornada foi removida da lista e não está disponível.';
            $this->redirect('/admin/jornadas');
            return;
        }
        $estrutura = json_decode($jornada['estrutura'] ?? '{}', true) ?: [];
        $turmasIds = !empty($estrutura['turmas_selecionadas']) && is_array($estrutura['turmas_selecionadas'])
            ? array_map('intval', $estrutura['turmas_selecionadas'])
            : [(int)$jornada['turma_id']];
        $turmasIds = array_unique(array_filter($turmasIds));
        if (empty($turmasIds)) {
            $turmasIds = [(int)$jornada['turma_id']];
        }
        $placeholders = implode(',', array_fill(0, count($turmasIds), '?'));
        $turmasNomesRows = $this->db->fetchAll(
            "SELECT nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
            $turmasIds
        );
        $turmasNomesTexto = $turmasNomesRows ? implode(', ', array_column($turmasNomesRows, 'nome')) : ($jornada['turma_nome'] ?? 'Sem turma');
        $todosAlunos = $this->db->fetchAll(
            "SELECT a.id as aluno_id, a.nome as aluno_nome, a.ra as aluno_ra, t.serie as serie, t.nome as turma_nome
             FROM alunos a
             JOIN turmas t ON a.turma_id = t.id
             WHERE a.turma_id IN ($placeholders) AND a.ativo = 1
             ORDER BY t.nome ASC, a.nome ASC",
            $turmasIds
        );
        $temModulosExercicios = $this->db->fetch(
            "SELECT COUNT(*) as total FROM jornadas_modulos m
             WHERE m.jornada_id = :jornada_id AND (m.tipo_modulo = 'exercicios' OR m.tipo_modulo = 'exercicio') AND m.status = 'ativo'",
            ['jornada_id' => $id]
        );
        $temExercicios = ($temModulosExercicios['total'] ?? 0) > 0;
        $temposExercicioPorAluno = [];
        if ($temExercicios) {
            $temposEtapaExercicios = $this->db->fetchAll(
                "SELECT jpa.aluno_id, COALESCE(SUM(jpa.tempo_gasto), 0) as tempo_segundos
                 FROM jornadas_progresso_alunos jpa
                 INNER JOIN jornadas_modulos m ON m.id = jpa.modulo_id AND m.jornada_id = jpa.jornada_id
                   AND (m.tipo_modulo = 'exercicios' OR m.tipo_modulo = 'exercicio')
                 WHERE jpa.jornada_id = :jornada_id AND jpa.atividade_tipo = 'modulo'
                 GROUP BY jpa.aluno_id",
                ['jornada_id' => $id]
            );
            foreach ($temposEtapaExercicios as $row) {
                $temposExercicioPorAluno[(int)$row['aluno_id']] = (int)($row['tempo_segundos'] ?? 0);
            }
        }
        // Pré-busca stats de exercícios e status de visualização em 2 queries para todos os alunos (evita N+1)
        $statsExPorAluno = [];
        if ($temExercicios) {
            $statsExRows = $this->db->fetchAll(
                "SELECT jpa.aluno_id,
                    COUNT(DISTINCT jpa.exercicio_modulo_id) as total_exercicios,
                    SUM(CASE WHEN jpa.pontuacao > 0 THEN 1 ELSE 0 END) as acertos,
                    SUM(CASE WHEN jpa.pontuacao = 0 OR jpa.pontuacao IS NULL THEN 1 ELSE 0 END) as erros,
                    COALESCE(SUM(jpa.pontuacao), 0) as nota_total,
                    COALESCE(AVG(jpa.pontuacao), 0) as nota_media,
                    COUNT(jpa.id) as total_respostas
                 FROM jornadas_progresso_alunos jpa
                 LEFT JOIN jornadas_modulos_exercicios jme ON jpa.exercicio_modulo_id = jme.id
                 LEFT JOIN jornadas_modulos jm ON jme.modulo_id = jm.id AND jm.jornada_id = :jornada_id_join
                 WHERE jpa.jornada_id = :jornada_id_where
                     AND jpa.atividade_tipo = 'exercicio_modulo' AND jpa.resposta IS NOT NULL
                 GROUP BY jpa.aluno_id",
                ['jornada_id_join' => $id, 'jornada_id_where' => $id]
            );
            foreach ($statsExRows as $row) {
                $statsExPorAluno[(int)$row['aluno_id']] = $row;
            }
        }
        // Pré-busca alunos que visualizaram a jornada em uma única query
        $visualizouMap = [];
        $visualizouRows = $this->db->fetchAll(
            "SELECT DISTINCT aluno_id FROM jornadas_progresso_alunos
             WHERE jornada_id = :jornada_id
                 AND atividade_tipo IS NULL AND status IN ('visualizado', 'iniciado', 'em_andamento', 'concluido')",
            ['jornada_id' => $id]
        );
        foreach ($visualizouRows as $vr) {
            $visualizouMap[(int)$vr['aluno_id']] = true;
        }

        $alunosExercicios = [];
        if ($temExercicios) {
            foreach ($todosAlunos as $aluno) {
                $stats = $statsExPorAluno[$aluno['aluno_id']] ?? null;
                $visualizou = isset($visualizouMap[$aluno['aluno_id']]);
                $alunosExercicios[] = [
                    'aluno_id' => $aluno['aluno_id'],
                    'aluno_nome' => $aluno['aluno_nome'],
                    'aluno_ra' => $aluno['aluno_ra'],
                    'serie' => $aluno['serie'],
                    'turma_nome' => $aluno['turma_nome'] ?? $aluno['serie'],
                    'total_exercicios' => (int)($stats['total_exercicios'] ?? 0),
                    'acertos' => (int)($stats['acertos'] ?? 0),
                    'erros' => (int)($stats['erros'] ?? 0),
                    'nota_total' => (float)($stats['nota_total'] ?? 0),
                    'nota_media' => (float)($stats['nota_media'] ?? 0),
                    'total_respostas' => (int)($stats['total_respostas'] ?? 0),
                    'status_exercicios' => ($stats['total_respostas'] ?? 0) > 0
                        ? ($visualizou ? 'fez' : 'fez_e_nao_viu')
                        : ($visualizou ? 'viu' : 'nao_viu'),
                    'tempo_total_segundos' => $temposExercicioPorAluno[$aluno['aluno_id']] ?? null
                ];
            }
        } else {
            foreach ($todosAlunos as $aluno) {
                $visualizou = isset($visualizouMap[$aluno['aluno_id']]);
                $alunosExercicios[] = [
                    'aluno_id' => $aluno['aluno_id'],
                    'aluno_nome' => $aluno['aluno_nome'],
                    'aluno_ra' => $aluno['aluno_ra'],
                    'serie' => $aluno['serie'],
                    'turma_nome' => $aluno['turma_nome'] ?? $aluno['serie'],
                    'total_exercicios' => 0,
                    'acertos' => 0,
                    'erros' => 0,
                    'nota_total' => 0,
                    'nota_media' => 0,
                    'total_respostas' => 0,
                    'status_exercicios' => $visualizou ? 'viu' : 'nao_viu',
                    'tempo_total_segundos' => null
                ];
            }
        }
        $modulosJornada = $this->db->fetchAll(
            "SELECT id, titulo, tipo_modulo, ordem FROM jornadas_modulos
             WHERE jornada_id = :jornada_id AND (status = 'ativo' OR status IS NULL)
             ORDER BY ordem ASC",
            ['jornada_id' => $id]
        );
        $etapas = [];
        foreach ($modulosJornada as $mod) {
            $progressoPorAluno = $this->db->fetchAll(
                "SELECT aluno_id, status, tempo_gasto
                 FROM jornadas_progresso_alunos
                 WHERE jornada_id = :jornada_id AND modulo_id = :modulo_id AND atividade_tipo = 'modulo'",
                ['jornada_id' => $id, 'modulo_id' => $mod['id']]
            );
            $mapProgresso = [];
            foreach ($progressoPorAluno as $p) {
                $mapProgresso[(int)$p['aluno_id']] = [
                    'status' => $p['status'] ?? '',
                    'tempo_gasto_segundos' => (int)($p['tempo_gasto'] ?? 0)
                ];
            }
            $alunosEtapa = [];
            foreach ($todosAlunos as $aluno) {
                $prog = $mapProgresso[$aluno['aluno_id']] ?? null;
                $fez = $prog && ($prog['status'] === 'concluido');
                $alunosEtapa[] = [
                    'aluno_id' => $aluno['aluno_id'],
                    'aluno_nome' => $aluno['aluno_nome'],
                    'turma_nome' => $aluno['turma_nome'] ?? $aluno['serie'] ?? '-',
                    'serie' => $aluno['serie'] ?? '',
                    'fez' => $fez,
                    'tempo_gasto_segundos' => $fez && $prog ? $prog['tempo_gasto_segundos'] : null
                ];
            }
            $etapas[] = [
                'id' => $mod['id'],
                'titulo' => $mod['titulo'],
                'tipo_modulo' => $mod['tipo_modulo'],
                'ordem' => (int)$mod['ordem'],
                'alunos' => $alunosEtapa
            ];
        }
        $redacoes = $this->db->fetchAll(
            "SELECT jr.*,
                (SELECT COUNT(*) FROM jornadas_redacoes_alunos jra WHERE jra.jornada_redacao_id = jr.id) as total_alunos,
                (SELECT COUNT(*) FROM jornadas_redacoes_alunos jra WHERE jra.jornada_redacao_id = jr.id AND jra.correcao_professor_feita = 0 AND jra.status IN ('entregue', 'corrigida_ia')) as pendentes_correcao
             FROM jornadas_redacoes jr WHERE jr.jornada_id = :jornada_id ORDER BY jr.created_at DESC",
            ['jornada_id' => $id]
        );
        foreach ($redacoes as &$redacao) {
            $alunosQueFizeram = $this->db->fetchAll(
                "SELECT jra.*, a.nome as aluno_nome, a.ra as aluno_ra, r.titulo as redacao_titulo, r.id as redacao_id, r.nota_final, r.nota_final_professor, r.usar_media_notas as r_usar_media_notas, r.nota_media as r_nota_media, r.nota_final_utilizada as r_nota_final_utilizada
                 FROM jornadas_redacoes_alunos jra JOIN alunos a ON jra.aluno_id = a.id JOIN redacoes r ON jra.redacao_id = r.id
                 WHERE jra.jornada_redacao_id = :redacao_id ORDER BY a.nome ASC, jra.versao ASC",
                ['redacao_id' => $redacao['id']]
            );
            $alunosFizeramMap = [];
            foreach ($alunosQueFizeram as $alunoRed) {
                $alunoId = $alunoRed['aluno_id'];
                if (!isset($alunosFizeramMap[$alunoId])) {
                    $alunosFizeramMap[$alunoId] = [];
                }
                $alunosFizeramMap[$alunoId][] = $alunoRed;
            }
            $redacao['alunos'] = [];
            foreach ($todosAlunos as $aluno) {
                if (isset($alunosFizeramMap[$aluno['aluno_id']])) {
                    foreach ($alunosFizeramMap[$aluno['aluno_id']] as $alunoRed) {
                        $alunoRed['status_redacao'] = 'fez';
                        $redacao['alunos'][] = $alunoRed;
                    }
                } else {
                    $redacao['alunos'][] = [
                        'aluno_id' => $aluno['aluno_id'],
                        'aluno_nome' => $aluno['aluno_nome'],
                        'aluno_ra' => $aluno['aluno_ra'],
                        'status_redacao' => isset($visualizouMap[$aluno['aluno_id']]) ? 'viu' : 'nao_viu',
                        'redacao_titulo' => null,
                        'status' => null,
                        'versao' => null,
                        'nota_final' => null
                    ];
                }
            }
        }
        $modulosResumo = $this->db->fetchAll(
            "SELECT m.* FROM jornadas_modulos m WHERE m.jornada_id = :jornada_id AND m.tipo_modulo = 'resumo_aluno' ORDER BY m.ordem ASC",
            ['jornada_id' => $id]
        );
        $resumos = [];
        foreach ($modulosResumo as $modulo) {
            $alunosQueFizeram = $this->db->fetchAll(
                "SELECT jra.*, a.nome as aluno_nome, a.ra as aluno_ra, m.titulo as modulo_titulo
                 FROM jornadas_resumos_alunos jra JOIN alunos a ON jra.aluno_id = a.id JOIN jornadas_modulos m ON jra.modulo_id = m.id
                 WHERE jra.modulo_id = :modulo_id ORDER BY a.nome ASC",
                ['modulo_id' => $modulo['id']]
            );
            $alunosFizeramMap = [];
            foreach ($alunosQueFizeram as $alunoResumo) {
                $alunosFizeramMap[$alunoResumo['aluno_id']] = $alunoResumo;
            }
            $alunosResumo = [];
            foreach ($todosAlunos as $aluno) {
                if (isset($alunosFizeramMap[$aluno['aluno_id']])) {
                    $alunoResumo = $alunosFizeramMap[$aluno['aluno_id']];
                    $alunoResumo['status_resumo'] = 'fez';
                    $alunosResumo[] = $alunoResumo;
                } else {
                    $alunosResumo[] = [
                        'aluno_id' => $aluno['aluno_id'],
                        'aluno_nome' => $aluno['aluno_nome'],
                        'aluno_ra' => $aluno['aluno_ra'],
                        'modulo_titulo' => $modulo['titulo'],
                        'status_resumo' => isset($visualizouMap[$aluno['aluno_id']]) ? 'viu' : 'nao_viu',
                        'status' => null,
                        'created_at' => null
                    ];
                }
            }
            $resumos[] = ['modulo' => $modulo, 'alunos' => $alunosResumo];
        }
        $alunosResumos = [];
        foreach ($todosAlunos as $aluno) {
            $resumoRecord = null;
            foreach ($resumos as $r) {
                foreach ($r['alunos'] ?? [] as $ar) {
                    if ((int)($ar['aluno_id'] ?? 0) === (int)$aluno['aluno_id'] && ($ar['status_resumo'] ?? '') === 'fez') {
                        $resumoRecord = $ar;
                        break 2;
                    }
                }
            }
            $realizou = $resumoRecord !== null;
            $alunosResumos[] = [
                'aluno_id' => $aluno['aluno_id'],
                'aluno_nome' => $aluno['aluno_nome'],
                'turma_nome' => $aluno['turma_nome'] ?? $aluno['serie'] ?? '-',
                'serie' => $aluno['serie'] ?? '',
                'status_resumo' => $realizou ? 'fez' : 'nao_viu',
                'nota' => $resumoRecord ? ($resumoRecord['nota'] !== null && $resumoRecord['nota'] !== '' ? $resumoRecord['nota'] : null) : null,
                'resumo_id' => $resumoRecord ? ($resumoRecord['id'] ?? null) : null,
                'created_at' => $resumoRecord['created_at'] ?? null
            ];
        }
        $alunos = $this->db->fetchAll(
            "SELECT a.* FROM alunos a WHERE a.turma_id = :turma_id ORDER BY a.nome ASC",
            ['turma_id' => $jornada['turma_id']]
        );
        $data = [
            'title' => 'Resultados da Jornada - ' . $jornada['titulo'] . ' - Admin - EducaTudo',
            'user' => $user,
            'jornada' => $jornada,
            'turmas_nomes_texto' => $turmasNomesTexto,
            'exercicios' => [],
            'alunosExercicios' => $alunosExercicios,
            'temExercicios' => $temExercicios,
            'etapas' => $etapas,
            'redacoes' => $redacoes,
            'resumos' => $resumos,
            'alunosResumos' => $alunosResumos,
            'alunos' => $alunos,
            'current_page' => 'journeys',
            'base_url_jornadas' => URL . '/admin/jornadas'
        ];
        $this->viewWithLayout('admin', 'admin/journeys/exercicios-alunos', $data);
    }
    
    /**
     * Visualiza exercícios detalhados de um aluno (admin: coordenadores, diretores, dev)
     * Mesma lógica e exibição do professor.
     */
    public function verExerciciosAluno($jornada_id, $aluno_id)
    {
        try {
            $user = $this->authManager->getUser();
            $jornada_id = (int)$jornada_id;
            $aluno_id = (int)$aluno_id;
            
            $jornada = $this->db->fetch(
                "SELECT j.*, m.nome as materia_nome, t.nome as turma_nome
                 FROM jornadas j
                 LEFT JOIN jornadas_materias m ON j.materia_id = m.id
                 LEFT JOIN turmas t ON j.turma_id = t.id
                 WHERE j.id = :jornada_id",
                ['jornada_id' => $jornada_id]
            );
            
            if (!$jornada) {
                $_SESSION['error_message'] = 'Jornada não encontrada';
                $this->redirect('/admin/jornadas');
                return;
            }
            
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome, t.serie as serie
                 FROM alunos a
                 JOIN turmas t ON a.turma_id = t.id
                 WHERE a.id = :aluno_id",
                ['aluno_id' => $aluno_id]
            );
            $turmasJornada = [(int)$jornada['turma_id']];
            if (!empty($jornada['estrutura'])) {
                $estrutura = json_decode($jornada['estrutura'], true);
                if (!empty($estrutura['turmas_selecionadas']) && is_array($estrutura['turmas_selecionadas'])) {
                    $turmasJornada = array_unique(array_merge($turmasJornada, array_map('intval', $estrutura['turmas_selecionadas'])));
                }
            }
            if (!$aluno || !in_array((int)$aluno['turma_id'], $turmasJornada, true)) {
                $_SESSION['error_message'] = 'Aluno não encontrado';
                $this->redirect('/admin/jornadas/' . $jornada_id . '/exercicios-alunos');
                return;
            }
            
            $exercicios = $this->db->fetchAll(
                "SELECT 
                    me.id,
                    me.modulo_id,
                    me.tipo,
                    me.titulo,
                    me.enunciado,
                    me.questoes_json,
                    me.resposta_correta,
                    me.gabarito,
                    me.pontuacao,
                    me.imagem_url,
                    me.status as exercicio_status,
                    m.titulo as modulo_titulo,
                    m.ordem as modulo_ordem,
                    jpa.resposta as resposta_aluno,
                    jpa.pontuacao as pontuacao_aluno,
                    jpa.status as status_aluno,
                    jpa.data_conclusao
                 FROM jornadas_modulos m
                 JOIN jornadas_modulos_exercicios me ON me.modulo_id = m.id
                 LEFT JOIN jornadas_progresso_alunos jpa ON jpa.exercicio_modulo_id = me.id 
                     AND jpa.aluno_id = :aluno_id 
                     AND jpa.jornada_id = m.jornada_id
                     AND jpa.atividade_tipo = 'exercicio_modulo'
                 WHERE m.jornada_id = :jornada_id 
                     AND (m.tipo_modulo = 'exercicios' OR m.tipo_modulo = 'exercicio')
                 ORDER BY m.ordem ASC, me.ordem ASC",
                ['jornada_id' => $jornada_id, 'aluno_id' => $aluno_id]
            );
            
            if (empty($exercicios)) {
                $exercicios = $this->db->fetchAll(
                    "SELECT 
                        je.id,
                        je.jornada_id as modulo_id,
                        je.tipo,
                        je.titulo,
                        je.descricao as enunciado,
                        je.questoes_json,
                        NULL as resposta_correta,
                        NULL as gabarito,
                        1.0 as pontuacao,
                        je.status as exercicio_status,
                        'Exercícios da Jornada' as modulo_titulo,
                        1 as modulo_ordem,
                        jpa.resposta as resposta_aluno,
                        jpa.pontuacao as pontuacao_aluno,
                        jpa.status as status_aluno,
                        jpa.data_conclusao
                     FROM jornadas_exercicios je
                     LEFT JOIN jornadas_progresso_alunos jpa ON jpa.exercicio_id = je.id 
                         AND jpa.aluno_id = :aluno_id 
                         AND jpa.jornada_id = je.jornada_id
                         AND jpa.atividade_tipo = 'exercicio'
                     WHERE je.jornada_id = :jornada_id 
                         AND je.status IN ('aprovado', 'publicado')
                     ORDER BY je.created_at ASC",
                    ['jornada_id' => $jornada_id, 'aluno_id' => $aluno_id]
                );
            }
            
            $total = count($exercicios);
            $respondidos = 0;
            $acertos = 0;
            $erros = 0;
            $notaTotal = 0;
            foreach ($exercicios as $ex) {
                if (!empty($ex['resposta_aluno'])) {
                    $respondidos++;
                    if (($ex['pontuacao_aluno'] ?? 0) > 0) {
                        $acertos++;
                        $notaTotal += $ex['pontuacao_aluno'];
                    } else {
                        $erros++;
                    }
                }
            }
            
            $data = [
                'title' => 'Prova Detalhada - ' . $aluno['nome'] . ' - Admin - EducaTudo',
                'user' => $user,
                'jornada' => $jornada,
                'aluno' => $aluno,
                'exercicios' => $exercicios,
                'estatisticas' => [
                    'total' => $total,
                    'respondidos' => $respondidos,
                    'acertos' => $acertos,
                    'erros' => $erros,
                    'nota_total' => $notaTotal,
                    'percentual' => $total > 0 ? round(($acertos / $total) * 100, 1) : 0
                ],
                'current_page' => 'journeys',
                'csrf_token' => $this->generateCsrfToken(),
                'base_url_jornadas' => URL . '/admin/jornadas'
            ];
            
            $this->viewWithLayout('admin', 'admin/journeys/exercicios-aluno-detalhado', $data);
        } catch (Exception $e) {
            error_log("Erro em AdminJourneyController::verExerciciosAluno: " . $e->getMessage());
            $_SESSION['error_message'] = 'Erro ao carregar prova: ' . $e->getMessage();
            $this->redirect('/admin/jornadas/' . ($jornada_id ?? 0) . '/exercicios-alunos');
        }
    }
    
    /**
     * Visualiza resumo do aluno (admin). Mesma tela do professor: nota e observações.
     */
    public function verResumo($jornadaId, $resumoId)
    {
        $jornadaId = filter_var($jornadaId, FILTER_VALIDATE_INT);
        $resumoId = filter_var($resumoId, FILTER_VALIDATE_INT);
        if (!$jornadaId || !$resumoId) {
            $_SESSION['error_message'] = 'ID inválido';
            $this->redirect('/admin/jornadas');
            return;
        }
        $user = $this->authManager->getUser();
        if (!$user) {
            $_SESSION['error_message'] = 'Usuário não autenticado';
            $this->redirect('/logout');
            return;
        }
        $jornada = $this->db->fetch(
            "SELECT j.* FROM jornadas j WHERE j.id = :id",
            ['id' => $jornadaId]
        );
        if (!$jornada) {
            $_SESSION['error_message'] = 'Jornada não encontrada';
            $this->redirect('/admin/jornadas');
            return;
        }
        $resumo = $this->db->fetch(
            "SELECT jra.*, 
                    a.nome as nome_aluno, 
                    a.ra as aluno_ra,
                    COALESCE(m.titulo, ja.nome_aula) as nome_aula,
                    COALESCE(m.titulo, ja.nome_aula) as modulo_titulo,
                    j.id as jornada_id,
                    j.titulo as jornada_titulo
             FROM jornadas_resumos_alunos jra
             JOIN alunos a ON jra.aluno_id = a.id
             LEFT JOIN jornadas_modulos m ON jra.modulo_id = m.id
             LEFT JOIN jornadas_aulas ja ON jra.aula_id = ja.id
             LEFT JOIN jornadas j ON (m.jornada_id = j.id OR ja.jornada_id = j.id)
             WHERE jra.id = :resumo_id AND j.id = :jornada_id",
            ['resumo_id' => $resumoId, 'jornada_id' => $jornadaId]
        );
        if (!$resumo) {
            $_SESSION['error_message'] = 'Resumo não encontrado';
            $this->redirect('/admin/jornadas/' . $jornadaId . '/exercicios-alunos?tab=resumos');
            return;
        }
        $voltarUrl = URL . '/admin/jornadas/' . $jornadaId . '/exercicios-alunos?tab=resumos';
        $urlAtribuirNota = URL . '/admin/jornadas/resumos/atribuir-nota';
        $data = [
            'title' => 'Resumo do aluno - Admin - EducaTudo',
            'jornada' => $jornada,
            'resumo' => $resumo,
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'journeys',
            'voltar_url' => $voltarUrl,
            'url_atribuir_nota' => $urlAtribuirNota
        ];
        $this->viewWithLayout('admin', 'teacher/journeys/ver-resumo', $data);
    }
    
    /**
     * Atribui nota e observações a um resumo (admin). Mesma lógica do professor, sem checagem de professor_id.
     */
    public function atribuirNotaResumo()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola')) {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            $resumoId = $_POST['resumo_id'] ?? null;
            $nota = $_POST['nota'] ?? null;
            $observacoes = $_POST['observacoes'] ?? null;
            if (empty($resumoId)) {
                throw new Exception('ID do resumo é obrigatório');
            }
            if ($nota !== null && $nota !== '') {
                $nota = filter_var($nota, FILTER_VALIDATE_FLOAT);
                if ($nota === false || $nota < 0 || $nota > 10) {
                    throw new Exception('Nota deve ser um número entre 0 e 10');
                }
            } else {
                $nota = null;
            }
            $resumo = $this->db->fetch(
                "SELECT jra.*, COALESCE(m.jornada_id, ja.jornada_id) as jornada_id
                 FROM jornadas_resumos_alunos jra
                 LEFT JOIN jornadas_modulos m ON jra.modulo_id = m.id
                 LEFT JOIN jornadas_aulas ja ON jra.aula_id = ja.id
                 WHERE jra.id = :resumo_id",
                ['resumo_id' => $resumoId]
            );
            if (!$resumo) {
                throw new Exception('Resumo não encontrado');
            }
            $this->db->query(
                "UPDATE jornadas_resumos_alunos 
                 SET nota = :nota, observacoes_professor = :observacoes, updated_at = NOW()
                 WHERE id = :resumo_id",
                [
                    'resumo_id' => $resumoId,
                    'nota' => $nota,
                    'observacoes' => $observacoes
                ]
            );
            $this->json([
                'success' => true,
                'message' => 'Nota atribuída com sucesso',
                'nota' => $nota,
                'observacoes' => $observacoes
            ]);
        } catch (Exception $e) {
            error_log("Erro ao atribuir nota ao resumo (admin): " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Lista jornadas por professor
     */
    public function porProfessor($professor_id)
    {
        $user = $this->authManager->getUser();
        
        // Busca dados do professor
        $professor = $this->db->fetch(
            "SELECT p.* FROM professores p WHERE p.id = :prof_id",
            ['prof_id' => $professor_id]
        );
        
        if (!$professor) {
            throw new Exception('Professor não encontrado');
        }
        
        // Busca jornadas do professor
        $jornadas = $this->db->fetchAll(
            "SELECT j.*, 
                    t.nome as turma_nome,
                    jm.nome as materia_nome,
                    jm.cor as materia_cor,
                    jm.icone as materia_icone,
                    (SELECT COUNT(*) FROM alunos WHERE turma_id = j.turma_id AND ativo = 1) as total_alunos,
                    (SELECT COUNT(*) FROM jornadas_aulas ja WHERE ja.jornada_id = j.id) as total_aulas,
                    (SELECT COUNT(*) FROM jornadas_exercicios je WHERE je.jornada_id = j.id) as total_exercicios
             FROM jornadas j
             JOIN turmas t ON j.turma_id = t.id
             LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
             WHERE j.professor_id = :prof_id AND (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC",
            ['prof_id' => $professor_id]
        );
        
        $data = [
            'title' => 'Jornadas do Professor ' . $professor['nome'] . ' - EducaTudo',
            'user' => $user,
            'professor' => $professor,
            'jornadas' => $jornadas,
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('admin', 'admin/journeys/por-professor', $data);
    }
    
    /**
     * Lista jornadas por turma
     */
    public function porTurma($turma_id)
    {
        $user = $this->authManager->getUser();
        
        // Busca dados da turma
        $turma = $this->db->fetch(
            "SELECT t.*, p.nome as professor_nome
             FROM turmas t
             LEFT JOIN professores p ON t.professor_id = p.id
             WHERE t.id = :turma_id",
            ['turma_id' => $turma_id]
        );
        
        if (!$turma) {
            throw new Exception('Turma não encontrada');
        }
        
        // Busca jornadas da turma
        $jornadas = $this->db->fetchAll(
            "SELECT j.*, 
                    p.nome as professor_nome,
                    jm.nome as materia_nome,
                    jm.cor as materia_cor,
                    jm.icone as materia_icone,
                    (SELECT COUNT(*) FROM alunos WHERE turma_id = j.turma_id AND ativo = 1) as total_alunos,
                    (SELECT COUNT(*) FROM jornadas_aulas ja WHERE ja.jornada_id = j.id) as total_aulas,
                    (SELECT COUNT(*) FROM jornadas_exercicios je WHERE je.jornada_id = j.id) as total_exercicios
             FROM jornadas j
             JOIN professores p ON j.professor_id = p.id
             LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
             WHERE j.turma_id = :turma_id AND (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC",
            ['turma_id' => $turma_id]
        );
        
        $data = [
            'title' => 'Jornadas da Turma ' . $turma['nome'] . ' - EducaTudo',
            'user' => $user,
            'turma' => $turma,
            'jornadas' => $jornadas,
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('admin', 'admin/journeys/por-turma', $data);
    }
    
    /**
     * Relatório pedagógico agregado (aluno × jornada): filtros, totais e ranking.
     */
    public function relatorio()
    {
        $user = $this->authManager->getUser();

        $filtros = [
            'tipo' => $_GET['tipo'] ?? 'geral',
            'turma_id' => $_GET['turma_id'] ?? '',
            'aluno_id' => $_GET['aluno_id'] ?? '',
            'aluno_nome' => trim((string) ($_GET['aluno_nome'] ?? '')),
            'data_inicio' => $_GET['data_inicio'] ?? '',
            'data_fim' => $_GET['data_fim'] ?? '',
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 25,
            'jr_ano_letivo' => $_GET['jr_ano_letivo'] ?? '',
            'jr_bimestre' => $_GET['jr_bimestre'] ?? '',
            'jr_professor_id' => $_GET['jr_professor_id'] ?? '',
            'jr_materia_id' => $_GET['jr_materia_id'] ?? '',
            'jr_jornada_id' => $_GET['jr_jornada_id'] ?? '',
            'jr_turma_ano_letivo' => $_GET['jr_turma_ano_letivo'] ?? '',
            'jr_avaliativo' => $_GET['jr_avaliativo'] ?? '',
            'jr_somente_atencao' => !empty($_GET['jr_somente_atencao']) ? 1 : 0,
            'jr_tempo_ordem' => $_GET['jr_tempo_ordem'] ?? '',
            'jr_modo_materia' => ($_GET['jr_modo_materia'] ?? 'total') === 'por_materia' ? 'por_materia' : 'total',
            'executar' => !empty($_GET['executar']) ? 1 : 0,
        ];
        $filtros['page'] = max(1, (int) $filtros['page']);
        $filtros['limit'] = max(10, min(500, (int) $filtros['limit']));
        if ($filtros['tipo'] === 'usuario' && empty($filtros['aluno_id']) && $filtros['aluno_nome'] !== '') {
            $alunoMatch = $this->db->fetch(
                "SELECT id, nome FROM alunos
                 WHERE ativo = 1 AND nome LIKE :nome
                 ORDER BY (nome = :nome_exato) DESC, nome ASC
                 LIMIT 1",
                [
                    'nome' => '%' . $filtros['aluno_nome'] . '%',
                    'nome_exato' => $filtros['aluno_nome'],
                ]
            );
            if (!empty($alunoMatch['id'])) {
                $filtros['aluno_id'] = (int) $alunoMatch['id'];
                $filtros['aluno_nome'] = (string) ($alunoMatch['nome'] ?? $filtros['aluno_nome']);
            }
        }

        $turmas = $this->db->fetchAll("SELECT * FROM turmas WHERE ativo = 1 ORDER BY nome ASC");
        $alunos = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, t.nome as turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON a.turma_id = t.id
             WHERE a.ativo = 1
             ORDER BY a.nome ASC"
        );

        require_once __DIR__ . '/../../Services/JornadasRelatorioService.php';
        $jornadas_relatorio = [];
        if (!empty($filtros['executar'])) {
            $svc = new JornadasRelatorioService($this->db);
            $jornadas_relatorio = $svc->relatorio($filtros);
        }

        $professores_jornadas_rel = $this->db->fetchAll("SELECT id, nome FROM professores ORDER BY nome ASC");
        $materias_jornadas_rel = $this->materiasParaJornada();
        $anos_turmas_rel = $this->db->fetchAll(
            "SELECT DISTINCT ano_letivo FROM turmas WHERE ativo = 1 ORDER BY ano_letivo DESC"
        );
        $jornadas_select_rel = $this->db->fetchAll(
            "SELECT j.id, j.titulo, t.nome AS turma_nome
             FROM jornadas j
             INNER JOIN turmas t ON j.turma_id = t.id
             WHERE (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC
             LIMIT 500"
        );

        $data = [
            'title' => 'Relatório de Jornadas - EducaTudo',
            'user' => $user,
            'current_page' => 'journeys_relatorio',
            'filtros' => $filtros,
            'turmas' => $turmas,
            'alunos' => $alunos,
            'jornadas_relatorio' => $jornadas_relatorio,
            'professores_jornadas_rel' => $professores_jornadas_rel,
            'materias_jornadas_rel' => $materias_jornadas_rel,
            'anos_turmas_rel' => $anos_turmas_rel,
            'jornadas_select_rel' => $jornadas_select_rel,
            'csrf_token' => $this->generateCsrfToken(),
        ];

        $this->viewWithLayout('admin', 'admin/journeys/relatorio', $data);
    }
    
    /**
     * Ativa/desativa jornada
     */
    public function toggleStatus()
    {
        $user = $this->authManager->getUser();
        
        $jornada_id = $_POST['jornada_id'] ?? null;
        $novo_status = $_POST['novo_status'] ?? null;
        
        if (!$jornada_id || !$novo_status) {
            $this->json(['error' => 'Dados obrigatórios não fornecidos'], 400);
        }
        
        if (!in_array($novo_status, ['ativa', 'pausada', 'finalizada'])) {
            $this->json(['error' => 'Status inválido'], 400);
        }
        
        try {
            $warnings = [];
            if ($novo_status === 'ativa') {
                $modulos = $this->db->fetchAll(
                    "SELECT id, titulo, tipo_modulo FROM jornadas_modulos WHERE jornada_id = :jid AND status = 'ativo' ORDER BY ordem ASC",
                    ['jid' => $jornada_id]
                );
                if (!empty($modulos)) {
                    $modIds = array_column($modulos, 'id');
                    $phMods = implode(',', array_fill(0, count($modIds), '?'));
                    // Pré-busca contagens de conteúdo para todos os módulos de uma vez (evita N*4 queries)
                    $contVideos = $this->db->fetchAll("SELECT modulo_id, COUNT(*) as t FROM jornadas_modulos_videos WHERE modulo_id IN ($phMods) AND status = 'ativo' GROUP BY modulo_id", $modIds);
                    $contDocs   = $this->db->fetchAll("SELECT modulo_id, COUNT(*) as t FROM jornadas_modulos_documentos WHERE modulo_id IN ($phMods) AND status = 'ativo' GROUP BY modulo_id", $modIds);
                    $contTexts  = $this->db->fetchAll("SELECT modulo_id, COUNT(*) as t FROM jornadas_modulos_textos WHERE modulo_id IN ($phMods) GROUP BY modulo_id", $modIds);
                    $contExs    = $this->db->fetchAll("SELECT modulo_id, COUNT(*) as t FROM jornadas_modulos_exercicios WHERE modulo_id IN ($phMods) AND status = 'publicado' GROUP BY modulo_id", $modIds);
                    $mapVideos = array_column($contVideos, 't', 'modulo_id');
                    $mapDocs   = array_column($contDocs,   't', 'modulo_id');
                    $mapTexts  = array_column($contTexts,  't', 'modulo_id');
                    $mapExs    = array_column($contExs,    't', 'modulo_id');
                    foreach ($modulos as $mod) {
                        $vazio = false;
                        if (in_array($mod['tipo_modulo'], ['video', 'conteudo', 'dica_professor'])) {
                            if ((int)($mapVideos[$mod['id']] ?? 0) === 0
                                && (int)($mapDocs[$mod['id']] ?? 0) === 0
                                && (int)($mapTexts[$mod['id']] ?? 0) === 0) {
                                $vazio = true;
                            }
                        } elseif (in_array($mod['tipo_modulo'], ['exercicios', 'exercicio'])) {
                            if ((int)($mapExs[$mod['id']] ?? 0) === 0) {
                                $vazio = true;
                            }
                        }
                        if ($vazio) {
                            $warnings[] = "O módulo \"{$mod['titulo']}\" não possui conteúdo. Os alunos poderão pular esta etapa.";
                        }
                    }
                }
            }

            $this->db->update(
                "UPDATE jornadas SET status = :status, updated_at = NOW() WHERE id = :jornada_id",
                [
                    'status' => $novo_status,
                    'jornada_id' => $jornada_id
                ]
            );
            
            $response = ['success' => true];
            if (!empty($warnings)) {
                $response['warnings'] = $warnings;
            }
            $this->json($response);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Exibe formulário para criar nova jornada
     */
    public function criar()
    {
        $user = $this->authManager->getUser();
        
        // Busca todos os professores (turmas são carregadas via AJAX ao selecionar o professor)
        $professores = $this->db->fetchAll(
            "SELECT p.* FROM professores p ORDER BY p.nome ASC"
        );
        
        // Busca matérias de jornadas_materias e materias (mesma lógica do editar)
        $materias = $this->materiasParaJornada();
        
        $data = [
            'title' => 'Criar Nova Jornada - EducaTudo',
            'professores' => $professores,
            'materias' => $materias,
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('admin', 'admin/journeys/create', $data);
    }
    
    /**
     * Exibe formulário para editar jornada
     */
    public function editar($id)
    {
        $user = $this->authManager->getUser();
        
        // Busca dados da jornada
        $jornada = $this->db->fetch(
            "SELECT j.*, 
                    p.nome as professor_nome,
                    t.nome as turma_nome,
                    jm.nome as materia_nome
             FROM jornadas j
             JOIN professores p ON j.professor_id = p.id
             JOIN turmas t ON j.turma_id = t.id
             LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
             WHERE j.id = :jornada_id",
            ['jornada_id' => $id]
        );
        
        if (!$jornada) {
            throw new Exception('Jornada não encontrada');
        }
        if (isset($jornada['ativo']) && (int)$jornada['ativo'] === 0) {
            $_SESSION['error_message'] = 'Esta jornada foi removida da lista e não está disponível para edição.';
            $this->redirect('/admin/jornadas');
            return;
        }
        
        // Decodifica estrutura JSON se existir
        $estrutura = [];
        if (!empty($jornada['estrutura'])) {
            $estrutura = json_decode($jornada['estrutura'], true) ?: [];
        }
        
        // Busca todos os professores
        $professores = $this->db->fetchAll(
            "SELECT p.* FROM professores p ORDER BY p.nome ASC"
        );
        
        // Busca todas as turmas
        $turmas = $this->db->fetchAll(
            "SELECT t.* FROM turmas t WHERE t.ativo = 1 ORDER BY t.nome ASC"
        );
        
        // Busca matérias (jornadas_materias + materias) para o select
        $materias = $this->materiasParaJornada();
        
        $data = [
            'title' => 'Editar Jornada - EducaTudo',
            'jornada' => $jornada,
            'estrutura' => $estrutura,
            'professores' => $professores,
            'turmas' => $turmas,
            'materias' => $materias,
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('admin', 'admin/journeys/edit', $data);
    }
    
    /**
     * Atualiza jornada editada
     */
    public function atualizar($id)
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            // Verifica se a jornada existe
            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :jornada_id",
                ['jornada_id' => $id]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            $titulo = trim($_POST['titulo'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $professorId = $_POST['professor_id'] ?? null;
            $turmaId = $_POST['turma_id'] ?? null;
            $materiaId = $_POST['materia_id'] ?? null;
            $anoLetivo = (int)($_POST['ano_letivo'] ?? 0);
            $bimestre = (int)($_POST['bimestre'] ?? 0);
            $avaliativo = 1;
            $objetivos = trim($_POST['objetivos'] ?? '');
            $dataInicio = $_POST['data_inicio'] ?? null;
            $dataFim = $_POST['data_fim'] ?? null;
            $criteriosAvaliacao = trim($_POST['criterios_avaliacao'] ?? '');
            
            // Validações
            if (empty($titulo) || empty($turmaId) || empty($professorId)) {
                throw new Exception('Título, professor e turma são obrigatórios');
            }
            
            if (empty($dataInicio) || empty($dataFim)) {
                throw new Exception('Data de início e fim são obrigatórias');
            }

            if ($anoLetivo < 2000 || $anoLetivo > 2100) {
                throw new Exception('Ano letivo inválido');
            }

            if ($bimestre < 1 || $bimestre > 4) {
                throw new Exception('Bimestre inválido');
            }
            
            if ($dataFim < $dataInicio) {
                throw new Exception('Data de fim deve ser posterior à data de início');
            }
            
            // Preserva estrutura existente (turmas_selecionadas, horários, etc.) para não perder
            // visibilidade para múltiplas turmas ao editar (ex.: 8º A, 8º B, 8º C)
            $estruturaExistente = json_decode($jornada['estrutura'] ?? '{}', true) ?: [];
            $estrutura = array_merge($estruturaExistente, [
                'objetivos' => $objetivos,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'criterios_avaliacao' => $criteriosAvaliacao
            ]);
            // Se o formulário enviar múltiplas turmas (turmas_id[]), atualiza estrutura e turma principal
            $turmasIdsPost = $_POST['turmas_id'] ?? null;
            if ($turmasIdsPost !== null) {
                $turmasIdsArr = is_array($turmasIdsPost) ? $turmasIdsPost : [$turmasIdsPost];
                $turmasIdsArr = array_map('intval', array_filter($turmasIdsArr));
                if (!empty($turmasIdsArr)) {
                    $estrutura['turmas_selecionadas'] = $turmasIdsArr;
                    $turmaId = $turmasIdsArr[0];
                }
            }
            
            // Atualiza jornada
            $this->db->update(
                "UPDATE jornadas 
                 SET professor_id = :prof_id, turma_id = :turma_id, materia_id = :materia_id, 
                     titulo = :titulo, descricao = :descricao, ano_letivo = :ano_letivo, bimestre = :bimestre, avaliativo = :avaliativo, estrutura = :estrutura, 
                     updated_at = NOW()
                 WHERE id = :jornada_id",
                [
                    'prof_id' => $professorId,
                    'turma_id' => $turmaId,
                    'materia_id' => $materiaId ?: null,
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                    'ano_letivo' => $anoLetivo,
                    'bimestre' => $bimestre,
                    'avaliativo' => $avaliativo,
                    'estrutura' => json_encode($estrutura),
                    'jornada_id' => $id
                ]
            );
            
            $_SESSION['success_message'] = 'Jornada atualizada com sucesso!';
            $this->json(['success' => true, 'message' => 'Jornada atualizada com sucesso!']);
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar jornada: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Salva nova jornada criada pelo admin (mesmo formato do professor: turmas múltiplas, alunos, estrutura)
     */
    public function salvar()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $titulo = trim($_POST['titulo'] ?? '');
            $professorId = $_POST['professor_id'] ?? null;
            $turmasIds = $_POST['turmas_id'] ?? [];
            if (is_string($turmasIds)) {
                $turmasIds = [$turmasIds];
            }
            $turmasIds = array_map('intval', array_filter($turmasIds));
            $turmaId = !empty($turmasIds) ? $turmasIds[0] : ($_POST['turma_id'] ?? null);
            $materiaId = $_POST['materia_id'] ?? null;
            $anoLetivo = (int)($_POST['ano_letivo'] ?? 0);
            $bimestre = (int)($_POST['bimestre'] ?? 0);
            $avaliativo = 1;
            $dataInicio = $_POST['data_inicio'] ?? null;
            $dataFim = $_POST['data_fim'] ?? null;
            $horaInicio = $_POST['hora_inicio'] ?? null;
            $horaFim = $_POST['hora_fim'] ?? null;
            $tipoSelecaoAlunos = $_POST['tipo_selecao_alunos'] ?? 'todos';
            $alunosIds = [];
            if ($tipoSelecaoAlunos === 'especificos') {
                $alunosIds = $_POST['alunos_id'] ?? [];
                if (is_string($alunosIds)) {
                    $alunosIds = [$alunosIds];
                }
                $alunosIds = array_values(array_unique(array_map('intval', $alunosIds)));
            }
            
            if (empty($titulo) || empty($professorId) || empty($turmasIds)) {
                throw new Exception('Título, professor e pelo menos uma turma são obrigatórios');
            }
            if (empty($dataInicio) || empty($dataFim)) {
                throw new Exception('Data de início e fim são obrigatórias');
            }
            if ($anoLetivo < 2000 || $anoLetivo > 2100) {
                throw new Exception('Ano letivo inválido');
            }
            if ($bimestre < 1 || $bimestre > 4) {
                throw new Exception('Bimestre inválido');
            }
            if ($dataFim < $dataInicio) {
                throw new Exception('Data de fim deve ser igual ou posterior à data de início');
            }
            
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :prof_id",
                ['prof_id' => $professorId]
            );
            if (!$professor) {
                throw new Exception('Professor não encontrado');
            }
            $turmasProfessor = json_decode($professor['turmas'], true) ?: [];
            foreach ($turmasIds as $tid) {
                if (!in_array($tid, $turmasProfessor)) {
                    throw new Exception('Uma ou mais turmas não pertencem ao professor selecionado');
                }
            }
            $turmasIds = array_values(array_unique(array_map('intval', $turmasIds)));
            if ($tipoSelecaoAlunos === 'especificos' && !empty($alunosIds)) {
                $placeholdersTurmas = implode(',', array_fill(0, count($turmasIds), '?'));
                $placeholdersAlunos = implode(',', array_fill(0, count($alunosIds), '?'));
                $alunosValidos = $this->db->fetchAll(
                    "SELECT id FROM alunos
                     WHERE ativo = 1
                     AND id IN ($placeholdersAlunos)
                     AND (
                        turma_id IN ($placeholdersTurmas)
                        OR EXISTS (
                            SELECT 1 FROM alunos_turmas_historico h
                            WHERE h.aluno_id = alunos.id
                            AND h.turma_id IN ($placeholdersTurmas)
                        )
                     )",
                    array_merge($alunosIds, $turmasIds, $turmasIds)
                );
                $alunosIds = array_map('intval', array_column($alunosValidos, 'id'));
            }
            
            // Coluna status aceita apenas 'ativa', 'pausada', 'finalizada'. Status por data fica na estrutura/cron.
            $statusInicial = 'ativa';
            
            $estrutura = [
                'objetivos' => '',
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'turmas_selecionadas' => $turmasIds,
                'alunos_selecionados' => $alunosIds,
                'tipo_selecao_alunos' => $tipoSelecaoAlunos,
                'planos_aula_selecionados' => []
            ];
            
            $jornadaId = $this->db->insert(
                "INSERT INTO jornadas (professor_id, turma_id, materia_id, titulo, ano_letivo, bimestre, avaliativo, estrutura, status, plano_aula_id) 
                 VALUES (:prof_id, :turma_id, :materia_id, :titulo, :ano_letivo, :bimestre, :avaliativo, :estrutura, :status, NULL)",
                [
                    'prof_id' => $professorId,
                    'turma_id' => $turmaId,
                    'materia_id' => $materiaId ?: null,
                    'titulo' => $titulo,
                    'ano_letivo' => $anoLetivo,
                    'bimestre' => $bimestre,
                    'avaliativo' => $avaliativo,
                    'estrutura' => json_encode($estrutura),
                    'status' => $statusInicial
                ]
            );
            
            $this->json(['success' => true, 'message' => 'Jornada criada com sucesso', 'jornada_id' => $jornadaId]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exibe interface para retomar jornada de um aluno (selecionando professor)
     */
    public function retomarJornada()
    {
        $user = $this->authManager->getUser();
        
        // Busca todos os professores
        $professores = $this->db->fetchAll(
            "SELECT p.*, COUNT(j.id) as total_jornadas
             FROM professores p
             LEFT JOIN jornadas j ON p.id = j.professor_id
             GROUP BY p.id
             ORDER BY p.nome ASC"
        );
        
        $data = [
            'title' => 'Retomar Jornada - EducaTudo',
            'user' => $user,
            'professores' => $professores,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('admin', 'admin/journeys/retomar-jornada', $data);
    }
    
    /**
     * Retorna turmas do professor (para formulário de criar jornada)
     */
    public function turmasDoProfessor()
    {
        $professorId = $_GET['professor_id'] ?? null;
        if (empty($professorId)) {
            $this->json(['success' => false, 'turmas' => []], 400);
            return;
        }
        try {
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :prof_id",
                ['prof_id' => $professorId]
            );
            if (!$professor) {
                $this->json(['success' => false, 'turmas' => []], 404);
                return;
            }
            $turmasIds = json_decode($professor['turmas'], true) ?: [];
            if (empty($turmasIds)) {
                $this->json(['success' => true, 'turmas' => []]);
                return;
            }
            $placeholders = str_repeat('?,', count($turmasIds) - 1) . '?';
            $turmas = $this->db->fetchAll(
                "SELECT id, nome FROM turmas WHERE id IN ($placeholders) AND ativo = 1 ORDER BY nome",
                $turmasIds
            );
            $this->json(['success' => true, 'turmas' => $turmas]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca alunos das turmas do professor (para criar jornada - mesmo fluxo do professor)
     */
    public function buscarAlunosCriar()
    {
        $professorId = $_GET['professor_id'] ?? null;
        $turmasParam = $_GET['turmas'] ?? '';
        if (empty($professorId) || empty($turmasParam)) {
            $this->json(['success' => false, 'error' => 'Professor e turmas são obrigatórios'], 400);
            return;
        }
        try {
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :prof_id",
                ['prof_id' => $professorId]
            );
            if (!$professor) {
                $this->json(['success' => false, 'error' => 'Professor não encontrado'], 404);
                return;
            }
            $turmasIds = array_map('intval', array_filter(explode(',', $turmasParam)));
            $turmasProfessor = json_decode($professor['turmas'], true) ?: [];
            $turmasValidas = array_values(array_intersect($turmasIds, $turmasProfessor));
            if (empty($turmasValidas)) {
                $this->json(['success' => false, 'error' => 'Turmas não pertencem ao professor'], 403);
                return;
            }
            // Na seleção de alunos da jornada, considerar turma atual e histórico
            // para ficar consistente com a contagem exibida em Turmas.
            $placeholders = implode(',', array_fill(0, count($turmasValidas), '?'));
            $alunos = $this->db->fetchAll(
                "SELECT a.id, a.nome, a.ra, a.turma_id, t.nome AS turma_nome
                 FROM alunos a
                 INNER JOIN turmas t ON t.id = a.turma_id
                 WHERE a.ativo = 1
                 AND (
                    a.turma_id IN ($placeholders)
                    OR EXISTS (
                        SELECT 1 FROM alunos_turmas_historico h
                        WHERE h.aluno_id = a.id AND h.turma_id IN ($placeholders)
                    )
                 )
                 ORDER BY t.nome ASC, a.nome ASC",
                array_merge($turmasValidas, $turmasValidas)
            );
            $this->json(['success' => true, 'alunos' => $alunos]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca alunos de um professor para retomar jornada
     */
    public function buscarAlunosProfessor()
    {
        $professor_id = $_GET['professor_id'] ?? null;
        
        if (!$professor_id) {
            $this->json(['error' => 'ID do professor não fornecido'], 400);
            return;
        }
        
        try {
            // Busca jornadas do professor
            $jornadas = $this->db->fetchAll(
                "SELECT j.*, t.nome as turma_nome
                 FROM jornadas j
                 JOIN turmas t ON j.turma_id = t.id
                 WHERE j.professor_id = :prof_id AND j.status = 'ativa' AND (j.ativo = 1 OR j.ativo IS NULL)
                 ORDER BY j.titulo ASC",
                ['prof_id' => $professor_id]
            );
            
            // Busca alunos das turmas das jornadas
            $turmasIds = array_unique(array_column($jornadas, 'turma_id'));
            $alunos = [];
            
            if (!empty($turmasIds)) {
                // Criar placeholders nomeados
                $placeholders = [];
                $params = [];
                foreach ($turmasIds as $index => $turmaId) {
                    $key = 'turma_id_' . $index;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $turmaId;
                }
                
                $query = "SELECT a.*, t.nome as turma_nome
                         FROM alunos a
                         JOIN turmas t ON a.turma_id = t.id
                         WHERE a.turma_id IN (" . implode(',', $placeholders) . ") AND a.ativo = 1
                         ORDER BY t.nome, a.nome ASC";
                
                $alunos = $this->db->fetchAll($query, $params);
            }
            
            $this->json([
                'success' => true,
                'jornadas' => $jornadas,
                'alunos' => $alunos
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Retoma jornada de um aluno específico (chamado pelo admin)
     */
    public function retomarJornadaAluno()
    {
        $jornada_id = $_POST['jornada_id'] ?? null;
        $aluno_id = $_POST['aluno_id'] ?? null;
        
        if (!$jornada_id || !$aluno_id) {
            $_SESSION['error_message'] = 'Jornada e aluno são obrigatórios';
            $this->redirect('/admin/journeys/retomar');
            return;
        }
        
        try {
            // Verifica se a jornada existe
            $jornada = $this->db->fetch(
                "SELECT j.* FROM jornadas j WHERE j.id = :jornada_id",
                ['jornada_id' => $jornada_id]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            // Verifica se o aluno pertence à turma da jornada
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a 
                 WHERE a.id = :aluno_id AND a.turma_id = :turma_id AND a.ativo = 1",
                [
                    'aluno_id' => $aluno_id,
                    'turma_id' => $jornada['turma_id']
                ]
            );
            
            if (!$aluno) {
                throw new Exception('Aluno não encontrado ou não pertence à turma da jornada');
            }
            
            // Busca a próxima aula não concluída do aluno
            $proximaAula = $this->db->fetch(
                "SELECT ja.*
                 FROM jornadas_aulas ja
                 LEFT JOIN jornadas_progresso_alunos jpa ON jpa.aula_id = ja.id 
                     AND jpa.aluno_id = :aluno_id 
                     AND jpa.atividade_tipo = 'aula' 
                     AND jpa.status = 'concluido'
                 WHERE ja.jornada_id = :jornada_id 
                     AND ja.status = 'ativa'
                     AND jpa.id IS NULL
                 ORDER BY ja.ordem ASC
                 LIMIT 1",
                [
                    'jornada_id' => $jornada_id,
                    'aluno_id' => $aluno_id
                ]
            );
            
            if ($proximaAula) {
                // Informa qual é a próxima aula e redireciona para visualizar a jornada
                $_SESSION['info_message'] = 'Próxima aula não concluída: ' . $proximaAula['nome_aula'] . '. O aluno pode acessar em: /jornadas/' . $jornada_id . '/aula/' . $proximaAula['id'];
                $this->redirect('/admin/jornadas/' . $jornada_id);
            } else {
                $_SESSION['info_message'] = 'O aluno já concluiu todas as aulas desta jornada!';
                $this->redirect('/admin/jornadas/' . $jornada_id);
            }
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao retomar jornada: ' . $e->getMessage();
            $this->redirect('/admin/jornadas/retomar');
        }
    }
    
    /**
     * Redireciona para o dashboard correto baseado no tipo
     */
    /**
     * Gerencia módulos da jornada
     */
    public function gerenciarModulos($id)
    {
        $user = $this->authManager->getUser();
        
        // Busca dados da jornada
        $jornada = $this->db->fetch(
            "SELECT j.*, 
                    p.nome as professor_nome,
                    t.nome as turma_nome,
                    jm.nome as materia_nome
             FROM jornadas j
             JOIN professores p ON j.professor_id = p.id
             JOIN turmas t ON j.turma_id = t.id
             LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
             WHERE j.id = :jornada_id",
            ['jornada_id' => $id]
        );
        
        if (!$jornada) {
            $_SESSION['error_message'] = 'Jornada não encontrada';
            $this->redirect('/admin/jornadas');
            return;
        }
        
        // Busca aulas da jornada
        $aulas = $this->db->fetchAll(
            "SELECT ja.* FROM jornadas_aulas ja
             WHERE ja.jornada_id = :jornada_id
             ORDER BY ja.ordem ASC",
            ['jornada_id' => $id]
        );
        
        $data = [
            'title' => 'Gerenciar Módulos - ' . $jornada['titulo'] . ' - Admin - EducaTudo',
            'user' => $user,
            'jornada' => $jornada,
            'aulas' => $aulas,
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('admin', 'admin/journeys/modulos', $data);
    }
    
    /**
     * Adiciona um módulo à jornada
     */
    public function adicionarModulo()
    {
        try {
            $jornada_id = $_POST['jornada_id'] ?? null;
            $tipo_modulo = $_POST['tipo_modulo'] ?? null;
            $titulo = $_POST['titulo'] ?? '';
            $obrigatorio = isset($_POST['obrigatorio']) ? 1 : 0;
            $ordem = $_POST['ordem'] ?? null;
            
            if (!$jornada_id || !$tipo_modulo) {
                throw new Exception('Jornada e tipo de módulo são obrigatórios');
            }
            
            // aula_id sempre NULL (removido do formulário)
            $aula_id = null;
            $descricao = null;
            
            // Busca a próxima ordem se não fornecida
            if (!$ordem) {
                $ultimaOrdem = $this->db->fetch(
                    "SELECT MAX(ordem) as max_ordem 
                     FROM jornadas_modulos 
                     WHERE jornada_id = :jornada_id AND aula_id IS NULL",
                    ['jornada_id' => $jornada_id]
                );
                $ordem = ($ultimaOrdem['max_ordem'] ?? 0) + 1;
            }
            
            // Valida e limpa o tipo
            $tipo_modulo = trim($tipo_modulo);
            $tiposValidos = ['resumo_aluno', 'dica_professor', 'redacao', 'exercicios', 'video', 'conteudo'];
            
            if (!in_array($tipo_modulo, $tiposValidos)) {
                throw new Exception('Tipo de módulo inválido: ' . $tipo_modulo);
            }
            
            // Insere o módulo
            $moduloId = $this->db->insert(
                "INSERT INTO jornadas_modulos 
                 (jornada_id, aula_id, tipo_modulo, titulo, descricao, ordem, obrigatorio, status, created_at) 
                 VALUES (:jornada_id, :aula_id, :tipo_modulo, :titulo, :descricao, :ordem, :obrigatorio, 'ativo', NOW())",
                [
                    'jornada_id' => $jornada_id,
                    'aula_id' => null,
                    'tipo_modulo' => $tipo_modulo,
                    'titulo' => $titulo ?: $this->getTituloPadraoModulo($tipo_modulo),
                    'descricao' => null,
                    'ordem' => $ordem,
                    'obrigatorio' => $obrigatorio
                ]
            );
            
            error_log("Módulo criado - ID: $moduloId, Tipo: $tipo_modulo, Título: " . ($titulo ?: $this->getTituloPadraoModulo($tipo_modulo)));
            
            $_SESSION['success_message'] = 'Módulo adicionado com sucesso!';
            $this->json(['success' => true, 'modulo_id' => $moduloId]);
            
        } catch (Exception $e) {
            error_log("Erro ao adicionar módulo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Remove um módulo
     */
    public function removerModulo()
    {
        try {
            $modulo_id = $_POST['modulo_id'] ?? null;
            
            if (!$modulo_id) {
                throw new Exception('ID do módulo é obrigatório');
            }
            
            $this->db->delete(
                "DELETE FROM jornadas_modulos WHERE id = :modulo_id",
                ['modulo_id' => $modulo_id]
            );
            
            $_SESSION['success_message'] = 'Módulo removido com sucesso!';
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            error_log("Erro ao remover módulo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Atualiza ordem dos módulos
     */
    public function atualizarOrdemModulos()
    {
        try {
            $jornada_id = $_POST['jornada_id'] ?? null;
            $ordens = $_POST['ordens'] ?? [];
            
            if (!$jornada_id || empty($ordens)) {
                throw new Exception('Dados inválidos');
            }
            
            foreach ($ordens as $index => $modulo_id) {
                $this->db->update(
                    "UPDATE jornadas_modulos SET ordem = :ordem WHERE id = :modulo_id AND jornada_id = :jornada_id",
                    [
                        'ordem' => $index + 1,
                        'modulo_id' => $modulo_id,
                        'jornada_id' => $jornada_id
                    ]
                );
            }
            
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar ordem: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Lista módulos da jornada (AJAX)
     */
    public function listarModulos($id)
    {
        try {
            $modulos = $this->db->fetchAll(
                "SELECT m.id, m.jornada_id, m.aula_id, m.tipo_modulo, m.titulo, m.descricao, m.ordem, m.obrigatorio, m.status, m.created_at, m.updated_at, ja.nome_aula as aula_nome
                 FROM jornadas_modulos m
                 LEFT JOIN jornadas_aulas ja ON m.aula_id = ja.id
                 WHERE m.jornada_id = :jornada_id AND m.status = 'ativo'
                 ORDER BY m.ordem ASC, m.created_at ASC",
                ['jornada_id' => $id]
            );
            
            // Garante que todos os campos estão presentes e limpos
            foreach ($modulos as &$modulo) {
                // Força o tipo_modulo a ser uma string
                $modulo['tipo_modulo'] = isset($modulo['tipo_modulo']) ? trim((string)$modulo['tipo_modulo']) : '';
                
                if (!isset($modulo['titulo'])) {
                    $modulo['titulo'] = '';
                }
                if (!isset($modulo['descricao'])) {
                    $modulo['descricao'] = '';
                }
                if (!isset($modulo['aula_nome'])) {
                    $modulo['aula_nome'] = null;
                }
                if (!isset($modulo['obrigatorio'])) {
                    $modulo['obrigatorio'] = 0;
                }
                
                // Debug individual
                error_log("Módulo ID {$modulo['id']}: tipo_modulo = '{$modulo['tipo_modulo']}', titulo = '{$modulo['titulo']}'");
            }
            unset($modulo);
            
            error_log("Total de módulos listados: " . count($modulos));
            
            $this->json(['success' => true, 'modulos' => $modulos]);
            
        } catch (Exception $e) {
            error_log("Erro ao listar módulos: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Retorna título padrão para o tipo de módulo
     */
    private function getTituloPadraoModulo($tipo)
    {
        $titulos = [
            'resumo_aluno' => 'Pedir Resumo para Aluno',
            'dica_professor' => 'Dica do Professor',
            'redacao' => 'Pedir Redação',
            'exercicios' => 'Exercícios',
            'video' => 'Conteúdo',
            'conteudo' => 'Conteúdo'
        ];
        
        return $titulos[$tipo] ?? 'Módulo';
    }
    
    /**
     * Gerencia exercícios de um módulo
     */
    public function gerenciarExerciciosModulo($modulo_id)
    {
        $user = $this->authManager->getUser();
        
        // Busca o módulo
        $modulo = $this->db->fetch(
            "SELECT m.*, j.titulo as jornada_titulo, j.id as jornada_id
             FROM jornadas_modulos m
             JOIN jornadas j ON m.jornada_id = j.id
             WHERE m.id = :modulo_id",
            ['modulo_id' => $modulo_id]
        );
        
        if (!$modulo) {
            $_SESSION['error_message'] = 'Módulo não encontrado';
            $this->redirect('/admin/jornadas');
            return;
        }
        
        // Busca exercícios do módulo
        $exercicios = $this->db->fetchAll(
            "SELECT * FROM jornadas_modulos_exercicios
             WHERE modulo_id = :modulo_id
             ORDER BY ordem ASC, created_at ASC",
            ['modulo_id' => $modulo_id]
        );
        
        // Busca dados da jornada para contexto
        $jornada = $this->db->fetch(
            "SELECT j.*, jm.nome as materia_nome
             FROM jornadas j
             LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
             WHERE j.id = :jornada_id",
            ['jornada_id' => $modulo['jornada_id']]
        );
        
        $data = [
            'title' => 'Gerenciar Exercícios - ' . $modulo['titulo'] . ' - Admin - EducaTudo',
            'user' => $user,
            'modulo' => $modulo,
            'jornada' => $jornada,
            'exercicios' => $exercicios,
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('admin', 'admin/journeys/modulos-exercicios', $data);
    }
    
    /**
     * Gerencia tema de redação de um módulo
     */
    public function gerenciarRedacaoModulo($modulo_id)
    {
        $user = $this->authManager->getUser();
        
        // Busca o módulo
        $modulo = $this->db->fetch(
            "SELECT m.*, j.titulo as jornada_titulo, j.id as jornada_id, j.professor_id
             FROM jornadas_modulos m
             JOIN jornadas j ON m.jornada_id = j.id
             WHERE m.id = :modulo_id",
            ['modulo_id' => $modulo_id]
        );
        
        if (!$modulo) {
            $_SESSION['error_message'] = 'Módulo não encontrado';
            $this->redirect('/admin/jornadas');
            return;
        }
        
        if ($modulo['tipo_modulo'] !== 'redacao') {
            $_SESSION['error_message'] = 'Este módulo não é de redação';
            $this->redirect('/admin/jornadas/' . $modulo['jornada_id'] . '/modulos');
            return;
        }
        
        // Busca redação da jornada associada a este módulo (se existir)
        $redacaoJornada = $this->db->fetch(
            "SELECT * FROM jornadas_redacoes 
             WHERE jornada_id = :jornada_id
             ORDER BY created_at DESC
             LIMIT 1",
            ['jornada_id' => $modulo['jornada_id']]
        );
        
        // Busca dados da jornada para contexto
        $jornada = $this->db->fetch(
            "SELECT j.*, jm.nome as materia_nome
             FROM jornadas j
             LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
             WHERE j.id = :jornada_id",
            ['jornada_id' => $modulo['jornada_id']]
        );
        
        $data = [
            'title' => 'Gerenciar Tema de Redação - ' . $modulo['titulo'] . ' - Admin - EducaTudo',
            'user' => $user,
            'modulo' => $modulo,
            'jornada' => $jornada,
            'redacao_jornada' => $redacaoJornada,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('admin', 'admin/journeys/modulos-redacao', $data);
    }
    
    /**
     * Salva ou atualiza tema de redação do módulo
     */
    public function salvarTemaRedacaoModulo()
    {
        try {
            $modulo_id = $_POST['modulo_id'] ?? null;
            // V-02 XSS: Tema da Redação é texto puro - sanitizar no backend antes de salvar (não aceitar HTML/JS)
            $tema = strip_tags(trim($_POST['tema'] ?? ''));
            $descricao = strip_tags(trim($_POST['descricao'] ?? ''));
            $correcaoIAAutomatica = isset($_POST['correcao_ia_automatica']) ? intval($_POST['correcao_ia_automatica']) : 0;
            
            if (!$modulo_id || empty($tema)) {
                throw new Exception('Módulo e tema são obrigatórios');
            }
            
            // Busca o módulo e jornada
            $modulo = $this->db->fetch(
                "SELECT m.*, j.id as jornada_id, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id",
                ['modulo_id' => $modulo_id]
            );
            
            if (!$modulo || $modulo['tipo_modulo'] !== 'redacao') {
                throw new Exception('Módulo não encontrado ou não é de redação');
            }
            
            // Processar upload de imagem se fornecido
            $imagemTema = null;
            if (isset($_FILES['imagem_tema']) && $_FILES['imagem_tema']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'public/uploads/redacoes_temas/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $file = $_FILES['imagem_tema'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($ext, $allowedExts)) {
                    throw new Exception('Formato de imagem inválido. Use: JPG, PNG, GIF ou WEBP');
                }
                
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new Exception('Imagem muito grande. Tamanho máximo: 5MB');
                }
                
                $fileName = uniqid() . '_' . time() . '.' . $ext;
                $filePath = $uploadDir . $fileName;
                
                if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                    throw new Exception('Erro ao fazer upload da imagem');
                }
                
                $imagemTema = $filePath;
            }
            
            // Verifica se já existe redação da jornada
            $redacaoExistente = $this->db->fetch(
                "SELECT * FROM jornadas_redacoes 
                 WHERE jornada_id = :jornada_id
                 ORDER BY created_at DESC
                 LIMIT 1",
                ['jornada_id' => $modulo['jornada_id']]
            );
            
            if ($redacaoExistente) {
                // Atualiza redação existente
                $updateFields = [
                    'tema_sugerido' => $tema,
                    'descricao_tema' => $descricao ?: null,
                    'correcao_ia_automatica' => $correcaoIAAutomatica,
                    'updated_at' => 'NOW()'
                ];
                
                if ($imagemTema) {
                    // Remove imagem antiga se existir
                    if ($redacaoExistente['imagem_tema'] && file_exists($redacaoExistente['imagem_tema'])) {
                        unlink($redacaoExistente['imagem_tema']);
                    }
                    $updateFields['imagem_tema'] = $imagemTema;
                }
                
                $setClause = [];
                $params = ['redacao_id' => $redacaoExistente['id']];
                
                foreach ($updateFields as $field => $value) {
                    if ($value === 'NOW()') {
                        $setClause[] = "$field = NOW()";
                    } else {
                        $setClause[] = "$field = :$field";
                        $params[$field] = $value;
                    }
                }
                
                $this->db->update(
                    "UPDATE jornadas_redacoes SET " . implode(', ', $setClause) . " WHERE id = :redacao_id",
                    $params
                );
                
                $redacaoId = $redacaoExistente['id'];
            } else {
                // Cria nova redação da jornada
                $redacaoId = $this->db->insert(
                    "INSERT INTO jornadas_redacoes 
                     (jornada_id, professor_id, tema_sugerido, descricao_tema, imagem_tema, correcao_ia_automatica, status, created_at)
                     VALUES (:jornada_id, :professor_id, :tema, :descricao, :imagem_tema, :correcao_ia_automatica, 'pendente', NOW())",
                    [
                        'jornada_id' => $modulo['jornada_id'],
                        'professor_id' => $modulo['professor_id'],
                        'tema' => $tema,
                        'descricao' => $descricao ?: null,
                        'imagem_tema' => $imagemTema,
                        'correcao_ia_automatica' => $correcaoIAAutomatica
                    ]
                );
            }
            
            $_SESSION['success_message'] = 'Tema de redação salvo com sucesso!';
            $this->json(['success' => true, 'redacao_id' => $redacaoId]);
            
        } catch (Exception $e) {
            error_log("Erro ao salvar tema de redação: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Gerencia vídeos de um módulo
     */
    public function gerenciarVideosModulo($modulo_id)
    {
        $user = $this->authManager->getUser();
        
        // Busca o módulo
        $modulo = $this->db->fetch(
            "SELECT m.*, j.titulo as jornada_titulo, j.id as jornada_id
             FROM jornadas_modulos m
             JOIN jornadas j ON m.jornada_id = j.id
             WHERE m.id = :modulo_id",
            ['modulo_id' => $modulo_id]
        );
        
        if (!$modulo) {
            $_SESSION['error_message'] = 'Módulo não encontrado';
            $this->redirect('/admin/jornadas');
            return;
        }
        
        // Busca vídeos do módulo
        $videos = $this->db->fetchAll(
            "SELECT * FROM jornadas_modulos_videos
             WHERE modulo_id = :modulo_id
             ORDER BY ordem ASC, created_at ASC",
            ['modulo_id' => $modulo_id]
        );
        
        // Busca documentos do módulo
        $documentos = $this->db->fetchAll(
            "SELECT * FROM jornadas_modulos_documentos
             WHERE modulo_id = :modulo_id
             ORDER BY ordem ASC, created_at ASC",
            ['modulo_id' => $modulo_id]
        );
        
        $data = [
            'title' => 'Gerenciar Vídeos - ' . $modulo['titulo'] . ' - Admin - EducaTudo',
            'user' => $user,
            'modulo' => $modulo,
            'videos' => $videos,
            'documentos' => $documentos,
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('admin', 'admin/journeys/modulos-videos', $data);
    }
    
    /**
     * Adiciona exercício ao módulo
     */
    public function adicionarExercicioModulo()
    {
        try {
            $modulo_id = $_POST['modulo_id'] ?? null;
            $tipo = $_POST['tipo'] ?? 'alternativas';
            $titulo = $_POST['titulo'] ?? '';
            $enunciado = $_POST['enunciado'] ?? '';
            $questoes_json = $_POST['questoes_json'] ?? null;
            $resposta_correta = $_POST['resposta_correta'] ?? null;
            $gabarito = $_POST['gabarito'] ?? null;
            $pontuacao = $_POST['pontuacao'] ?? 1.00;
            $gerado_ia = isset($_POST['gerado_ia']) ? 1 : 0;
            
            if (!$modulo_id || !$titulo || !$enunciado) {
                throw new Exception('Módulo, título e enunciado são obrigatórios');
            }
            
            // Busca a próxima ordem
            $ultimaOrdem = $this->db->fetch(
                "SELECT MAX(ordem) as max_ordem 
                 FROM jornadas_modulos_exercicios 
                 WHERE modulo_id = :modulo_id",
                ['modulo_id' => $modulo_id]
            );
            $ordem = ($ultimaOrdem['max_ordem'] ?? 0) + 1;
            
            $exercicioId = $this->db->insert(
                "INSERT INTO jornadas_modulos_exercicios 
                 (modulo_id, tipo, titulo, enunciado, questoes_json, resposta_correta, gabarito, pontuacao, ordem, gerado_ia, status) 
                 VALUES (:modulo_id, :tipo, :titulo, :enunciado, :questoes_json, :resposta_correta, :gabarito, :pontuacao, :ordem, :gerado_ia, 'publicado')",
                [
                    'modulo_id' => $modulo_id,
                    'tipo' => $tipo,
                    'titulo' => $titulo,
                    'enunciado' => $enunciado,
                    'questoes_json' => $questoes_json ? json_encode($questoes_json) : null,
                    'resposta_correta' => $resposta_correta,
                    'gabarito' => $gabarito,
                    'pontuacao' => $pontuacao,
                    'ordem' => $ordem,
                    'gerado_ia' => $gerado_ia
                ]
            );
            
            $_SESSION['success_message'] = 'Exercício adicionado com sucesso!';
            $this->json(['success' => true, 'exercicio_id' => $exercicioId]);
            
        } catch (Exception $e) {
            error_log("Erro ao adicionar exercício: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Gera exercício por IA para o módulo (assíncrono)
     */
    public function gerarExercicioIAModulo()
    {
        try {
            $modulo_id = $_POST['modulo_id'] ?? null;
            $tipo      = $_POST['tipo']      ?? 'alternativas';
            $quantidade = (int) ($_POST['quantidade'] ?? 5);
            $contexto  = $_POST['contexto']  ?? '';

            if (!$modulo_id) {
                throw new Exception('Módulo é obrigatório');
            }

            $modulo = $this->db->fetch(
                "SELECT m.*, j.titulo as jornada_titulo, j.descricao as jornada_descricao, jm.nome as materia_nome
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
                 WHERE m.id = :modulo_id",
                ['modulo_id' => $modulo_id]
            );

            if (!$modulo) {
                throw new Exception('Módulo não encontrado');
            }

            $contextoCompleto  = "Matéria: " . ($modulo['materia_nome'] ?? 'Geral') . "\n";
            $contextoCompleto .= "Jornada: {$modulo['jornada_titulo']}\n";
            if ($modulo['jornada_descricao']) {
                $contextoCompleto .= "Descrição: {$modulo['jornada_descricao']}\n";
            }
            if ($contexto) {
                $contextoCompleto .= "Contexto adicional: {$contexto}\n";
            }

            require_once __DIR__ . '/../../Services/AIJobService.php';
            $user  = $this->auth->getUser();
            $jobId = \App\Services\AIJobService::enqueue(
                'gerar_exercicios_jornada',
                [
                    'jornada_id' => (int) $modulo['jornada_id'],
                    'aula_id'    => null,
                    'contexto'   => $contextoCompleto,
                    'tipo'       => $tipo,
                    'quantidade' => $quantidade,
                    'materia'    => $modulo['materia_nome'] ?? 'Geral',
                ],
                $user['id'] ?? null,
                $user['tipo'] ?? 'admin'
            );

            $this->json(['job_id' => $jobId]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    
    /**
     * Remove exercício do módulo
     */
    public function removerExercicioModulo()
    {
        try {
            $exercicio_id = $_POST['exercicio_id'] ?? null;
            
            if (!$exercicio_id) {
                throw new Exception('ID do exercício é obrigatório');
            }
            
            $this->db->delete(
                "DELETE FROM jornadas_modulos_exercicios WHERE id = :exercicio_id",
                ['exercicio_id' => $exercicio_id]
            );
            
            $_SESSION['success_message'] = 'Exercício removido com sucesso!';
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            error_log("Erro ao remover exercício: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Busca dados de um exercício para edição
     */
    public function buscarExercicioModulo()
    {
        try {
            $exercicio_id = $_GET['exercicio_id'] ?? null;
            
            if (!$exercicio_id) {
                throw new Exception('ID do exercício é obrigatório');
            }
            
            $exercicio = $this->db->fetch(
                "SELECT * FROM jornadas_modulos_exercicios WHERE id = :exercicio_id",
                ['exercicio_id' => $exercicio_id]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado');
            }
            
            // Decodifica JSON se existir
            if (!empty($exercicio['questoes_json'])) {
                // Se já for array, mantém; se for string, decodifica
                if (is_string($exercicio['questoes_json'])) {
                    $decoded = json_decode($exercicio['questoes_json'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $exercicio['questoes_json'] = $decoded;
                    } else {
                        error_log("Erro ao decodificar JSON do exercício {$exercicio_id}: " . json_last_error_msg());
                        $exercicio['questoes_json'] = [];
                    }
                } elseif (!is_array($exercicio['questoes_json'])) {
                    $exercicio['questoes_json'] = [];
                }
            } else {
                $exercicio['questoes_json'] = [];
            }
            
            // Garante que o status tenha um valor padrão se estiver vazio
            if (empty($exercicio['status'])) {
                $exercicio['status'] = 'publicado';
            }
            
            // Debug log
            error_log("Exercício ID {$exercicio_id}: tipo={$exercicio['tipo']}, status={$exercicio['status']}, questoes_json=" . json_encode($exercicio['questoes_json']));
            
            $this->json(['success' => true, 'exercicio' => $exercicio]);
            
        } catch (Exception $e) {
            error_log("Erro ao buscar exercício: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Atualiza exercício do módulo
     */
    public function atualizarExercicioModulo()
    {
        try {
            $exercicio_id = $_POST['exercicio_id'] ?? null;
            $titulo = $_POST['titulo'] ?? '';
            $enunciado = trim((string)($_POST['enunciado'] ?? ''));
            $questoes_json = $_POST['questoes_json'] ?? null;
            $resposta_correta = $_POST['resposta_correta'] ?? null;
            $gabarito = $_POST['gabarito'] ?? null;
            $pontuacao = $_POST['pontuacao'] ?? 1.00;
            
            if (!$exercicio_id || !$titulo || $enunciado === '') {
                throw new Exception('ID, título e enunciado são obrigatórios');
            }
            
            if (is_string($questoes_json)) {
                $questoes_json = json_decode($questoes_json, true);
            }
            if (is_array($questoes_json) && !empty($questoes_json['opcoes'])) {
                foreach ($questoes_json['opcoes'] as &$opcao) {
                }
                unset($opcao);
            }
            
            // Busca o status atual do exercício para preservar se não for enviado
            $exercicioAtual = $this->db->fetch(
                "SELECT status FROM jornadas_modulos_exercicios WHERE id = :exercicio_id",
                ['exercicio_id' => $exercicio_id]
            );
            
            // Se o status foi enviado, usa ele; senão, preserva o status atual ou usa 'publicado' como padrão
            $status = $_POST['status'] ?? ($exercicioAtual['status'] ?? 'publicado');
            
            // Se questoes_json for string, decodifica antes de codificar novamente
            if (is_string($questoes_json)) {
                $questoes_json = json_decode($questoes_json, true);
            }
            
            $this->db->update(
                "UPDATE jornadas_modulos_exercicios 
                 SET titulo = :titulo, enunciado = :enunciado, questoes_json = :questoes_json, 
                     resposta_correta = :resposta_correta, gabarito = :gabarito, pontuacao = :pontuacao, 
                     status = :status, updated_at = NOW()
                 WHERE id = :exercicio_id",
                [
                    'titulo' => $titulo,
                    'enunciado' => $enunciado,
                    'questoes_json' => $questoes_json ? json_encode($questoes_json) : null,
                    'resposta_correta' => $resposta_correta,
                    'gabarito' => $gabarito,
                    'pontuacao' => $pontuacao,
                    'status' => $status,
                    'exercicio_id' => $exercicio_id
                ]
            );
            
            $_SESSION['success_message'] = 'Exercício atualizado com sucesso!';
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar exercício: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Adiciona vídeo ao módulo
     */
    public function adicionarVideoModulo()
    {
        try {
            $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if (empty($_POST) && $contentLength > 0) {
                throw new Exception(
                    'Os dados do formulário não chegaram ao servidor. Com vídeos grandes, isso costuma indicar limite do PHP (post_max_size / upload_max_filesize). Comprima o vídeo ou aumente esses limites no servidor.'
                );
            }
            $modulo_id = $_POST['modulo_id'] ?? null;
            $tipo = $_POST['tipo'] ?? 'youtube';
            $titulo = trim((string) ($_POST['titulo'] ?? ''));
            $descricao = $_POST['descricao'] ?? '';
            $url_youtube = $_POST['url_youtube'] ?? null;

            if (!$modulo_id) {
                throw new Exception('Não foi identificado o módulo da jornada. Atualize a página e tente novamente.');
            }
            if ($titulo === '') {
                throw new Exception('Informe o título do conteúdo (no topo do formulário ou no bloco acima de Salvar conteúdo).');
            }
            
            if ($tipo === 'youtube' && !$url_youtube) {
                throw new Exception('URL do YouTube é obrigatória para vídeos do YouTube');
            }
            
            // Processa upload se necessário
            $arquivo_video = null;
            $arquivo_nome = null;
            $arquivo_tamanho = null;
            
            if ($tipo === 'upload' && isset($_FILES['arquivo_video'])) {
                $ferr = (int) ($_FILES['arquivo_video']['error'] ?? UPLOAD_ERR_OK);
                if ($ferr === UPLOAD_ERR_INI_SIZE || $ferr === UPLOAD_ERR_FORM_SIZE) {
                    throw new Exception(
                        'O vídeo excede o tamanho máximo permitido pelo servidor (upload_max_filesize / post_max_size). Comprima o arquivo ou aumente o limite no PHP.'
                    );
                }
                if ($ferr !== UPLOAD_ERR_OK) {
                    throw new Exception(
                        $ferr === UPLOAD_ERR_NO_FILE
                            ? 'Obrigatório: selecione um arquivo de vídeo.'
                            : ('Erro no envio do vídeo (código ' . $ferr . '). Tente outro arquivo.')
                    );
                }
                $uploadDir = 'public/uploads/videos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $file = $_FILES['arquivo_video'];
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = uniqid() . '.' . $ext;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $arquivo_video = $filePath;
                    $arquivo_nome = $file['name'];
                    $arquivo_tamanho = $file['size'];
                } else {
                    throw new Exception('Erro ao fazer upload do vídeo');
                }
            }
            
            // Busca a próxima ordem
            $ultimaOrdem = $this->db->fetch(
                "SELECT MAX(ordem) as max_ordem 
                 FROM jornadas_modulos_videos 
                 WHERE modulo_id = :modulo_id",
                ['modulo_id' => $modulo_id]
            );
            $ordem = ($ultimaOrdem['max_ordem'] ?? 0) + 1;
            
            $videoId = $this->db->insert(
                "INSERT INTO jornadas_modulos_videos 
                 (modulo_id, tipo, titulo, descricao, url_youtube, arquivo_video, arquivo_nome, arquivo_tamanho, ordem) 
                 VALUES (:modulo_id, :tipo, :titulo, :descricao, :url_youtube, :arquivo_video, :arquivo_nome, :arquivo_tamanho, :ordem)",
                [
                    'modulo_id' => $modulo_id,
                    'tipo' => $tipo,
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                    'url_youtube' => $url_youtube,
                    'arquivo_video' => $arquivo_video,
                    'arquivo_nome' => $arquivo_nome,
                    'arquivo_tamanho' => $arquivo_tamanho,
                    'ordem' => $ordem
                ]
            );
            
            $_SESSION['success_message'] = 'Vídeo adicionado com sucesso!';
            $this->json(['success' => true, 'video_id' => $videoId]);
            
        } catch (Exception $e) {
            error_log("Erro ao adicionar vídeo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Remove vídeo do módulo
     */
    public function removerVideoModulo()
    {
        try {
            $video_id = $_POST['video_id'] ?? null;
            
            if (!$video_id) {
                throw new Exception('ID do vídeo é obrigatório');
            }
            
            // Busca o vídeo para deletar arquivo se existir
            $video = $this->db->fetch(
                "SELECT * FROM jornadas_modulos_videos WHERE id = :video_id",
                ['video_id' => $video_id]
            );
            
            if ($video && $video['arquivo_video'] && file_exists($video['arquivo_video'])) {
                unlink($video['arquivo_video']);
            }
            
            $this->db->delete(
                "DELETE FROM jornadas_modulos_videos WHERE id = :video_id",
                ['video_id' => $video_id]
            );
            
            $_SESSION['success_message'] = 'Vídeo removido com sucesso!';
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            error_log("Erro ao remover vídeo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Adiciona documento ao módulo
     */
    public function adicionarDocumentoModulo()
    {
        try {
            $modulo_id = $_POST['modulo_id'] ?? null;
            $titulo = $_POST['titulo'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            
            if (!$modulo_id || !$titulo) {
                throw new Exception('Módulo e título são obrigatórios');
            }
            
            if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Arquivo é obrigatório');
            }
            
            // Processa upload
            $uploadDir = 'public/uploads/documentos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $file = $_FILES['arquivo'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '.' . $ext;
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Erro ao fazer upload do documento');
            }
            
            // Limitar tamanho: MIME pode ser longo (ex.: application/vnd.openxmlformats-...)
            $tipo_arquivo = substr((string)($file['type'] ?? ''), 0, 255);
            
            // Busca a próxima ordem
            $ultimaOrdem = $this->db->fetch(
                "SELECT MAX(ordem) as max_ordem 
                 FROM jornadas_modulos_documentos 
                 WHERE modulo_id = :modulo_id",
                ['modulo_id' => $modulo_id]
            );
            $ordem = ($ultimaOrdem['max_ordem'] ?? 0) + 1;
            
            $documentoId = $this->db->insert(
                "INSERT INTO jornadas_modulos_documentos 
                 (modulo_id, titulo, descricao, arquivo, arquivo_nome, arquivo_tamanho, tipo_arquivo, ordem) 
                 VALUES (:modulo_id, :titulo, :descricao, :arquivo, :arquivo_nome, :arquivo_tamanho, :tipo_arquivo, :ordem)",
                [
                    'modulo_id' => $modulo_id,
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                    'arquivo' => $filePath,
                    'arquivo_nome' => $file['name'],
                    'arquivo_tamanho' => $file['size'],
                    'tipo_arquivo' => $tipo_arquivo,
                    'ordem' => $ordem
                ]
            );
            
            $_SESSION['success_message'] = 'Documento adicionado com sucesso!';
            $this->json(['success' => true, 'documento_id' => $documentoId]);
            
        } catch (Exception $e) {
            error_log("Erro ao adicionar documento: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Alterna status de exercício (publicar/despublicar)
     */
    public function alternarStatusExercicio()
    {
        try {
            $exercicio_id = $_POST['exercicio_id'] ?? null;
            
            if (!$exercicio_id) {
                throw new Exception('ID do exercício é obrigatório');
            }
            
            // Busca o exercício atual
            $exercicio = $this->db->fetch(
                "SELECT * FROM jornadas_modulos_exercicios WHERE id = :exercicio_id",
                ['exercicio_id' => $exercicio_id]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado');
            }
            
            // Alterna entre publicado e rascunho
            $novoStatus = $exercicio['status'] === 'publicado' ? 'rascunho' : 'publicado';
            
            $this->db->update(
                "UPDATE jornadas_modulos_exercicios 
                 SET status = :status, updated_at = NOW()
                 WHERE id = :exercicio_id",
                [
                    'status' => $novoStatus,
                    'exercicio_id' => $exercicio_id
                ]
            );
            
            $this->json([
                'success' => true, 
                'message' => 'Status alterado com sucesso!',
                'novo_status' => $novoStatus
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao alternar status do exercício: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Remove documento do módulo
     */
    public function removerDocumentoModulo()
    {
        try {
            $documento_id = $_POST['documento_id'] ?? null;
            
            if (!$documento_id) {
                throw new Exception('ID do documento é obrigatório');
            }
            
            // Busca o documento para deletar arquivo
            $documento = $this->db->fetch(
                "SELECT * FROM jornadas_modulos_documentos WHERE id = :documento_id",
                ['documento_id' => $documento_id]
            );
            
            if ($documento && $documento['arquivo'] && file_exists($documento['arquivo'])) {
                unlink($documento['arquivo']);
            }
            
            $this->db->delete(
                "DELETE FROM jornadas_modulos_documentos WHERE id = :documento_id",
                ['documento_id' => $documento_id]
            );
            
            $_SESSION['success_message'] = 'Documento removido com sucesso!';
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            error_log("Erro ao remover documento: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Inativa uma jornada (soft delete: não exclui, apenas ativo = 0).
     * Não exibe para professor nem aluno. Exige senha do admin para confirmar.
     */
    public function inativar()
    {
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola')) {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }
            $jornadaId = filter_var($_POST['jornada_id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$jornadaId) {
                $this->json(['error' => 'ID da jornada é obrigatório'], 400);
                return;
            }
            $senha = trim($_POST['senha'] ?? '');
            if ($senha === '') {
                $this->json(['error' => 'Digite sua senha para confirmar.'], 400);
                return;
            }
            // Admin está na tabela usuarios (id do getUser é usuario_id)
            $usuario = $this->db->fetch(
                "SELECT id, senha_hash FROM usuarios WHERE id = :user_id AND tipo IN ('admin', 'admin_escola')",
                ['user_id' => $user['id']]
            );
            if (!$usuario || !password_verify($senha, $usuario['senha_hash'] ?? '')) {
                $this->json(['error' => 'Senha incorreta.'], 400);
                return;
            }
            $jornada = $this->db->fetch(
                "SELECT id FROM jornadas WHERE id = :id",
                ['id' => $jornadaId]
            );
            if (!$jornada) {
                $this->json(['error' => 'Jornada não encontrada'], 404);
                return;
            }
            $this->db->update(
                "UPDATE jornadas SET ativo = 0, updated_at = NOW() WHERE id = :id",
                ['id' => $jornadaId]
            );
            $this->json(['success' => true, 'message' => 'Jornada inativada. Ela não será mais exibida para o professor nem para o aluno.']);
        } catch (Exception $e) {
            error_log("Erro ao inativar jornada (admin): " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Inativa jornadas em lote (soft delete: ativo = 0). Exige senha do admin.
     */
    public function inativarLote()
    {
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola')) {
                $this->json(['error' => 'Acesso negado'], 403);
                return;
            }
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }

            $idsRaw = $_POST['jornada_ids'] ?? [];
            if (!is_array($idsRaw)) {
                $idsRaw = [];
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', $idsRaw), static function (int $v): bool {
                return $v > 0;
            })));
            if ($ids === []) {
                $this->json(['error' => 'Selecione ao menos uma jornada.'], 400);
                return;
            }

            $senha = trim((string) ($_POST['senha'] ?? ''));
            if ($senha === '') {
                $this->json(['error' => 'Digite sua senha para confirmar.'], 400);
                return;
            }

            $usuario = $this->db->fetch(
                "SELECT id, senha_hash FROM usuarios WHERE id = :user_id AND tipo IN ('admin', 'admin_escola')",
                ['user_id' => $user['id']]
            );
            if (!$usuario || !password_verify($senha, (string) ($usuario['senha_hash'] ?? ''))) {
                $this->json(['error' => 'Senha incorreta.'], 400);
                return;
            }

            $ph = implode(',', array_fill(0, count($ids), '?'));
            $paramsSel = $ids;
            $existe = $this->db->fetch(
                "SELECT COUNT(*) AS total FROM jornadas WHERE id IN ($ph)",
                $paramsSel
            );
            $totalExiste = (int) ($existe['total'] ?? 0);
            if ($totalExiste <= 0) {
                $this->json(['error' => 'Nenhuma jornada válida encontrada para inativar.'], 404);
                return;
            }

            $paramsUpd = $ids;
            $this->db->update(
                "UPDATE jornadas SET ativo = 0, updated_at = NOW() WHERE id IN ($ph)",
                $paramsUpd
            );

            $this->json([
                'success' => true,
                'message' => $totalExiste . ' jornada(s) inativada(s) com sucesso.'
            ]);
        } catch (Exception $e) {
            error_log("Erro ao inativar jornadas em lote (admin): " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exclui uma jornada (DELETE físico - mantido para compatibilidade; preferir inativar)
     */
    public function excluir()
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || ($user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola')) {
                throw new Exception('Acesso negado');
            }
            
            $jornada_id = $_POST['jornada_id'] ?? null;
            
            if (!$jornada_id) {
                throw new Exception('ID da jornada é obrigatório');
            }
            
            // Verifica se a jornada existe
            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :jornada_id",
                ['jornada_id' => $jornada_id]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            // Exclui a jornada (cascade vai excluir módulos, aulas, exercícios relacionados)
            $this->db->delete(
                "DELETE FROM jornadas WHERE id = :jornada_id",
                ['jornada_id' => $jornada_id]
            );
            
            $_SESSION['success_message'] = 'Jornada excluída com sucesso!';
            $this->json(['success' => true, 'message' => 'Jornada excluída com sucesso!']);
            
        } catch (Exception $e) {
            error_log("Erro ao excluir jornada: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exclui uma jornada (método DELETE para compatibilidade)
     */
    public function delete($id)
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || ($user['tipo'] !== 'admin' && $user['tipo'] !== 'admin_escola')) {
                throw new Exception('Acesso negado');
            }
            
            if (!$id) {
                throw new Exception('ID da jornada é obrigatório');
            }
            
            // Verifica se a jornada existe
            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :jornada_id",
                ['jornada_id' => $id]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            // Exclui a jornada (cascade vai excluir módulos, aulas, exercícios relacionados)
            $this->db->delete(
                "DELETE FROM jornadas WHERE id = :jornada_id",
                ['jornada_id' => $id]
            );
            
            $_SESSION['success_message'] = 'Jornada excluída com sucesso!';
            $this->json(['success' => true, 'message' => 'Jornada excluída com sucesso!']);
            
        } catch (Exception $e) {
            error_log("Erro ao excluir jornada: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/admin/dashboard');
        }
    }
}
}
