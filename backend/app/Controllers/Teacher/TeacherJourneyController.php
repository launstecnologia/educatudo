<?php
/**
 * EducaTudo - ProfessorJornadaController
 * Gerencia jornadas do lado do professor
 */

if (!class_exists('TeacherJourneyController')) {
class TeacherJourneyController extends BaseController
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
        
        // Verifica se é professor
        $user = $this->authManager->getUser();
        if ($user['tipo'] !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }

    /**
     * Lista jornadas do professor
     */
    public function index()
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca dados do professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            if (!$professor) {
                throw new Exception('Professor não encontrado');
            }
        
        // Busca turmas do professor
        $turmas_professor = json_decode($professor['turmas'], true) ?: [];
        
        if (empty($turmas_professor)) {
            $turmas = [];
        } else {
            $placeholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
            $turmas = $this->db->fetchAll(
                "SELECT * FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
                $turmas_professor
            );
        }
        
        // Busca jornadas do professor
        $jornadas = $this->db->fetchAll(
            "SELECT j.*, 
                    t.nome as turma_nome,
                    m.nome as materia_nome,
                    COUNT(DISTINCT ja.id) as total_aulas,
                    COUNT(DISTINCT je.id) as total_exercicios,
                    COUNT(DISTINCT a.id) as total_alunos
             FROM jornadas j
             JOIN turmas t ON j.turma_id = t.id
             LEFT JOIN materias m ON j.materia_id = m.id
             LEFT JOIN jornadas_aulas ja ON j.id = ja.jornada_id
             LEFT JOIN jornadas_exercicios je ON j.id = je.jornada_id
             LEFT JOIN alunos a ON t.id = a.turma_id
             WHERE j.professor_id = :prof_id
             AND (j.ativo = 1 OR j.ativo IS NULL)
             GROUP BY j.id
             ORDER BY j.created_at DESC",
            ['prof_id' => $professor['id']]
        );
        
        // Pré-busca nomes e contagem de alunos de todas as turmas referenciadas nas jornadas (evita N+1)
        $todasTurmaIds = [];
        foreach ($jornadas as $j) {
            $est = json_decode($j['estrutura'] ?? '{}', true) ?: [];
            $ts = $est['turmas_selecionadas'] ?? [];
            if (!empty($ts) && is_array($ts)) {
                foreach ($ts as $tid) { $todasTurmaIds[(int)$tid] = true; }
            }
            if (!empty($j['turma_id'])) {
                $todasTurmaIds[(int)$j['turma_id']] = true;
            }
        }
        $turmasMapIdx = [];
        $totalAlunosPorTurmaIdx = [];
        if (!empty($todasTurmaIds)) {
            $phIds = implode(',', array_fill(0, count($todasTurmaIds), '?'));
            $turmasListIdx = $this->db->fetchAll("SELECT id, nome FROM turmas WHERE id IN ($phIds) ORDER BY nome", array_keys($todasTurmaIds));
            foreach ($turmasListIdx as $t) { $turmasMapIdx[(int)$t['id']] = $t['nome']; }
            $alunoCountRows = $this->db->fetchAll(
                "SELECT turma_id, COUNT(*) as total FROM alunos WHERE turma_id IN ($phIds) AND ativo = 1 GROUP BY turma_id",
                array_keys($todasTurmaIds)
            );
            foreach ($alunoCountRows as $row) { $totalAlunosPorTurmaIdx[(int)$row['turma_id']] = (int)$row['total']; }
        }

        // Pré-busca "para corrigir" e "corrigidos hoje" para todas as jornadas de uma vez (evita 2N queries)
        $paraCorrigirMap = [];
        $corrigidosHojeMap = [];
        if (!empty($jornadas)) {
            $todosJornadaIds = array_column($jornadas, 'id');
            $phJIds = implode(',', array_fill(0, count($todosJornadaIds), '?'));
            $redacoesPendentes = $this->db->fetchAll(
                "SELECT jr.jornada_id, COUNT(*) as total
                 FROM jornadas_redacoes_alunos jra
                 JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                 WHERE jr.jornada_id IN ($phJIds)
                   AND jra.status IN ('entregue', 'corrigida_ia')
                   AND jra.correcao_professor_feita = 0
                 GROUP BY jr.jornada_id",
                $todosJornadaIds
            );
            $exerciciosPendentes = $this->db->fetchAll(
                "SELECT jm.jornada_id, COUNT(DISTINCT jpa.id) as total
                 FROM jornadas_progresso_alunos jpa
                 LEFT JOIN jornadas_modulos_exercicios jme ON jpa.exercicio_modulo_id = jme.id
                 LEFT JOIN jornadas_modulos jm ON jme.modulo_id = jm.id
                 WHERE jm.jornada_id IN ($phJIds)
                   AND jpa.resposta IS NOT NULL AND jpa.resposta != ''
                   AND jpa.pontuacao IS NULL
                 GROUP BY jm.jornada_id",
                $todosJornadaIds
            );
            foreach ($redacoesPendentes as $r) { $paraCorrigirMap[(int)$r['jornada_id']] = (int)$r['total']; }
            foreach ($exerciciosPendentes as $r) { $paraCorrigirMap[(int)$r['jornada_id']] = ($paraCorrigirMap[(int)$r['jornada_id']] ?? 0) + (int)$r['total']; }

            $redacoesCorrigidasHoje = $this->db->fetchAll(
                "SELECT jr.jornada_id, COUNT(*) as total
                 FROM jornadas_redacoes_alunos jra
                 JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                 WHERE jr.jornada_id IN ($phJIds)
                   AND jra.correcao_professor_feita = 1 AND DATE(jra.updated_at) = CURDATE()
                 GROUP BY jr.jornada_id",
                $todosJornadaIds
            );
            $exerciciosCorrigidosHoje = $this->db->fetchAll(
                "SELECT jm.jornada_id, COUNT(DISTINCT jpa.id) as total
                 FROM jornadas_progresso_alunos jpa
                 LEFT JOIN jornadas_modulos_exercicios jme ON jpa.exercicio_modulo_id = jme.id
                 LEFT JOIN jornadas_modulos jm ON jme.modulo_id = jm.id
                 WHERE jm.jornada_id IN ($phJIds)
                   AND jpa.pontuacao IS NOT NULL AND DATE(jpa.updated_at) = CURDATE()
                 GROUP BY jm.jornada_id",
                $todosJornadaIds
            );
            foreach ($redacoesCorrigidasHoje as $r) { $corrigidosHojeMap[(int)$r['jornada_id']] = (int)$r['total']; }
            foreach ($exerciciosCorrigidosHoje as $r) { $corrigidosHojeMap[(int)$r['jornada_id']] = ($corrigidosHojeMap[(int)$r['jornada_id']] ?? 0) + (int)$r['total']; }
        }

        // Estatísticas gerais
        $stats = [
            'total' => count($jornadas),
            'aguardando' => 0,
            'em_andamento' => 0,
            'concluidas' => 0,
            'total_para_corrigir' => 0,
            'total_corrigidos' => 0
        ];

        // Processa dados das jornadas para extrair informações da estrutura JSON e buscar status dos alunos
        $dataAtual = date('Y-m-d');
        foreach ($jornadas as &$jornada) {
            $estrutura = json_decode($jornada['estrutura'], true) ?: [];
            $jornada['data_inicio'] = $estrutura['data_inicio'] ?? null;
            $jornada['data_fim'] = $estrutura['data_fim'] ?? null;
            $jornada['hora_inicio'] = $estrutura['hora_inicio'] ?? null;
            $jornada['hora_fim'] = $estrutura['hora_fim'] ?? null;
            $jornada['objetivos'] = $estrutura['objetivos'] ?? '';
            $jornada['criterios_avaliacao'] = $estrutura['criterios_avaliacao'] ?? '';

            // Extrai informações de turmas e alunos selecionados
            $turmasSelecionadas = $estrutura['turmas_selecionadas'] ?? [];
            $alunosSelecionados = $estrutura['alunos_selecionados'] ?? [];
            $tipoSelecaoAlunos = $estrutura['tipo_selecao_alunos'] ?? 'todos';

            // Calcula quantidade de turmas selecionadas e resolve nomes do mapa pré-carregado
            if (!empty($turmasSelecionadas) && is_array($turmasSelecionadas)) {
                $jornada['total_turmas_selecionadas'] = count($turmasSelecionadas);
                $nomes = [];
                foreach ($turmasSelecionadas as $tid) {
                    if (isset($turmasMapIdx[(int)$tid])) { $nomes[] = $turmasMapIdx[(int)$tid]; }
                }
                $jornada['turmas_selecionadas_nomes'] = $nomes;
            } else {
                $jornada['total_turmas_selecionadas'] = 1;
                $jornada['turmas_selecionadas_nomes'] = isset($turmasMapIdx[(int)$jornada['turma_id']])
                    ? [$turmasMapIdx[(int)$jornada['turma_id']]]
                    : ($jornada['turma_nome'] ? [$jornada['turma_nome']] : []);
            }

            // Calcula quantidade de alunos selecionados usando mapa pré-carregado
            if ($tipoSelecaoAlunos === 'todos') {
                $ids = !empty($turmasSelecionadas) && is_array($turmasSelecionadas)
                    ? $turmasSelecionadas
                    : [$jornada['turma_id']];
                $totalAlunos = 0;
                foreach ($ids as $tid) { $totalAlunos += $totalAlunosPorTurmaIdx[(int)$tid] ?? 0; }
                $jornada['total_alunos_selecionados'] = $totalAlunos;
            } else {
                $jornada['total_alunos_selecionados'] = !empty($alunosSelecionados) && is_array($alunosSelecionados)
                    ? count($alunosSelecionados)
                    : 0;
            }
            
            // Recalcula e persiste status (data+hora) ao carregar — corrige sem depender do CRON
            if (!class_exists('JornadaStatusHelper')) {
                require_once __DIR__ . '/../../Core/JornadaStatusHelper.php';
            }
            // Em listagem, evita UPDATE por item para não sobrecarregar o banco com muitos acessos simultâneos.
            $jornada['status_jornada'] = JornadaStatusHelper::recalcularSemPersistir((string) ($jornada['estrutura'] ?? ''));
            if ($jornada['status_jornada'] === 'aguardando') {
                $stats['aguardando']++;
            } elseif ($jornada['status_jornada'] === 'concluido') {
                $stats['concluidas']++;
            } else {
                $stats['em_andamento']++;
            }
            
            // Busca alunos da turma com status na jornada
            // Considera alunos selecionados se houver na estrutura
            try {
                $estrutura = json_decode($jornada['estrutura'], true) ?: [];
                $alunosSelecionados = $estrutura['alunos_selecionados'] ?? [];
                $tipoSelecaoAlunos = $estrutura['tipo_selecao_alunos'] ?? 'todos';
                $turmasSelecionadas = $estrutura['turmas_selecionadas'] ?? [];
                
                // Monta query baseada na seleção de alunos
                $whereTurma = "a.turma_id = :turma_id";
                $jornadaId = $jornada['id'];
                $params = [
                    'jornada_id_1' => $jornadaId,
                    'jornada_id_2' => $jornadaId,
                    'turma_id' => $jornada['turma_id']
                ];
                
                // Se houver turmas selecionadas, filtra por elas
                if (!empty($turmasSelecionadas) && is_array($turmasSelecionadas) && count($turmasSelecionadas) > 0) {
                    $turmaParams = [];
                    foreach ($turmasSelecionadas as $idx => $turmaId) {
                        if (!empty($turmaId)) {
                            $paramName = 'turma_id_' . $idx;
                            $turmaParams[] = ':' . $paramName;
                            $params[$paramName] = (int)$turmaId;
                        }
                    }
                    if (!empty($turmaParams)) {
                        $whereTurma = "a.turma_id IN (" . implode(', ', $turmaParams) . ")";
                        unset($params['turma_id']);
                    }
                }
                
                // Se selecionou alunos específicos, filtra por eles
                if ($tipoSelecaoAlunos === 'selecionados' && !empty($alunosSelecionados) && is_array($alunosSelecionados) && count($alunosSelecionados) > 0) {
                    $alunoParams = [];
                    foreach ($alunosSelecionados as $idx => $alunoId) {
                        if (!empty($alunoId)) {
                            $paramName = 'aluno_id_' . $idx;
                            $alunoParams[] = ':' . $paramName;
                            $params[$paramName] = (int)$alunoId;
                        }
                    }
                    if (!empty($alunoParams)) {
                        $whereTurma .= " AND a.id IN (" . implode(', ', $alunoParams) . ")";
                    }
                }
                
                $alunosJornada = $this->db->fetchAll(
                    "SELECT a.id, a.nome, a.ra,
                        CASE 
                            WHEN EXISTS (
                                SELECT 1 FROM jornadas_progresso_alunos jpa 
                                WHERE jpa.aluno_id = a.id 
                                AND jpa.jornada_id = :jornada_id_1
                                AND jpa.status = 'concluido'
                                AND jpa.atividade_tipo IS NULL
                                AND jpa.modulo_id IS NULL
                                AND jpa.aula_id IS NULL
                                AND jpa.exercicio_id IS NULL
                            ) THEN 'concluida'
                            WHEN EXISTS (
                                SELECT 1 FROM jornadas_progresso_alunos jpa 
                                WHERE jpa.aluno_id = a.id 
                                AND jpa.jornada_id = :jornada_id_2
                                AND (
                                    (jpa.status IN ('visualizado', 'iniciado', 'em_andamento', 'concluido')
                                     AND jpa.atividade_tipo IS NULL
                                     AND jpa.modulo_id IS NULL
                                     AND jpa.aula_id IS NULL
                                     AND jpa.exercicio_id IS NULL)
                                    OR jpa.atividade_tipo IS NOT NULL
                                )
                            ) THEN 'visualizado'
                            ELSE 'nao_visualizado'
                        END as status_jornada
                     FROM alunos a
                     WHERE $whereTurma AND a.ativo = 1
                     ORDER BY a.nome ASC",
                    $params
                );
                
                $jornada['alunos_status'] = $alunosJornada;
                // Se está em_andamento e todos os alunos já finalizaram a jornada → marcar como Concluído
                $totalAlunos = (int)($jornada['total_alunos_selecionados'] ?? 0);
                if ($totalAlunos > 0 && ($jornada['status_jornada'] ?? '') === 'em_andamento') {
                    $concluidaCount = count(array_filter($alunosJornada, function ($a) {
                        return ($a['status_jornada'] ?? '') === 'concluida';
                    }));
                    if ($concluidaCount >= $totalAlunos) {
                        $jornada['status_jornada'] = 'concluido';
                        $stats['em_andamento'] = max(0, $stats['em_andamento'] - 1);
                        $stats['concluidas']++;
                    }
                }
            } catch (Exception $e) {
                // Em caso de erro, usa query simples com turma_id principal
                error_log("Erro ao buscar alunos da jornada {$jornada['id']}: " . $e->getMessage());
                try {
                    $alunosJornada = $this->db->fetchAll(
                        "SELECT a.id, a.nome, a.ra,
                            CASE 
                                WHEN EXISTS (
                                    SELECT 1 FROM jornadas_progresso_alunos jpa 
                                    WHERE jpa.aluno_id = a.id 
                                    AND jpa.jornada_id = :jornada_id_1
                                    AND jpa.status = 'concluido'
                                    AND jpa.atividade_tipo IS NULL
                                    AND jpa.modulo_id IS NULL
                                    AND jpa.aula_id IS NULL
                                    AND jpa.exercicio_id IS NULL
                                ) THEN 'concluida'
                                WHEN EXISTS (
                                    SELECT 1 FROM jornadas_progresso_alunos jpa 
                                    WHERE jpa.aluno_id = a.id 
                                    AND jpa.jornada_id = :jornada_id_2
                                    AND jpa.status IN ('visualizado', 'iniciado', 'em_andamento')
                                    AND jpa.atividade_tipo IS NULL
                                    AND jpa.modulo_id IS NULL
                                    AND jpa.aula_id IS NULL
                                    AND jpa.exercicio_id IS NULL
                                ) THEN 'visualizado'
                                ELSE 'nao_visualizado'
                            END as status_jornada
                         FROM alunos a
                         WHERE a.turma_id = :turma_id AND a.ativo = 1
                         ORDER BY a.nome ASC",
                        [
                            'jornada_id_1' => $jornada['id'],
                            'jornada_id_2' => $jornada['id'],
                            'turma_id' => $jornada['turma_id']
                        ]
                    );
                    $jornada['alunos_status'] = $alunosJornada;
                    $totalAlunos = (int)($jornada['total_alunos_selecionados'] ?? 0);
                    if ($totalAlunos > 0 && ($jornada['status_jornada'] ?? '') === 'em_andamento') {
                        $concluidaCount = count(array_filter($alunosJornada, function ($a) {
                            return ($a['status_jornada'] ?? '') === 'concluida';
                        }));
                        if ($concluidaCount >= $totalAlunos) {
                            $jornada['status_jornada'] = 'concluido';
                            $stats['em_andamento'] = max(0, $stats['em_andamento'] - 1);
                            $stats['concluidas']++;
                        }
                    }
                } catch (Exception $e2) {
                    error_log("Erro ao buscar alunos (fallback): " . $e2->getMessage());
                    $jornada['alunos_status'] = [];
                }
            }
            
            // Usa mapa pré-carregado (evita 2 queries por jornada)
            $jornada['para_corrigir'] = $paraCorrigirMap[(int)$jornada['id']] ?? 0;
            $stats['total_para_corrigir'] += $jornada['para_corrigir'];
            $stats['total_corrigidos'] += $corrigidosHojeMap[(int)$jornada['id']] ?? 0;
        }
        
        // Total de alunos únicos em todas as jornadas
        $totalAlunosUnicos = $this->db->fetch(
            "SELECT COUNT(DISTINCT a.id) as total
             FROM alunos a
             JOIN turmas t ON a.turma_id = t.id
             JOIN jornadas j ON j.turma_id = t.id
             WHERE j.professor_id = :prof_id",
            ['prof_id' => $professor['id']]
        );
        
        $stats['total_alunos'] = (int)($totalAlunosUnicos['total'] ?? 0);
        
        $data = [
            'title' => 'Minhas Jornadas - EducaTudo',
            'jornadas' => $jornadas,
            'turmas' => $turmas,
            'stats' => $stats,
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('professor', 'teacher/journeys/index', $data);
        
        } catch (Exception $e) {
            // Log do erro para debug
            error_log("Erro em ProfessorJornadaController::index(): " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            
            // Em vez de redirecionar, mostra o erro
            $this->json(['error' => 'Erro ao carregar jornadas: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Formulário para criar nova jornada
     */
    public function criar()
    {
        $user = $this->authManager->getUser();
        
        // Busca dados do professor
        $professor = $this->db->fetch(
            "SELECT p.* FROM professores p WHERE p.id = :user_id",
            ['user_id' => $user['id']]
        );
        
        if (!$professor) {
            throw new Exception('Professor não encontrado');
        }
        
        // Busca turmas do professor
        $turmas_professor = json_decode($professor['turmas'], true) ?: [];
        
        if (empty($turmas_professor)) {
            $turmas = [];
        } else {
            $placeholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
            $turmas = $this->db->fetchAll(
                "SELECT * FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
                $turmas_professor
            );
        }
        
        // Busca matérias do professor
        $materias_professor = json_decode($professor['materias'], true) ?: [];
        
        if (empty($materias_professor)) {
            $materias = [];
        } else {
            $placeholders = str_repeat('?,', count($materias_professor) - 1) . '?';
            $materias = $this->db->fetchAll(
                "SELECT * FROM materias WHERE nome IN ($placeholders) ORDER BY nome ASC",
                $materias_professor
            );
        }
        
        // Busca planos de aula do professor (apenas os vinculados a este professor e suas turmas)
        // Garante que apenas planos com professor_id correspondente ao professor logado sejam exibidos
        // E que as turmas dos planos estejam nas turmas do professor
        $planos_aula = [];
        if (!empty($turmas_professor) && is_array($turmas_professor)) {
            $turmaPlaceholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
            $params = array_merge([(int)$professor['id']], $turmas_professor);
            $planos_aula = $this->db->fetchAll(
                "SELECT pa.*, t.nome as turma_nome
                 FROM planos_aula pa
                 INNER JOIN turmas t ON pa.turma_id = t.id
                 WHERE pa.professor_id = ?
                 AND pa.professor_id IS NOT NULL
                 AND pa.professor_id > 0
                 AND pa.turma_id IN ($turmaPlaceholders)
                 ORDER BY pa.created_at DESC",
                $params
            );
        }
        
        $data = [
            'title' => 'Criar Jornada - EducaTudo',
            'turmas' => $turmas,
            'materias' => $materias,
            'planos_aula' => $planos_aula,
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('professor', 'teacher/journeys/create', $data);
    }
    
    /**
     * Busca objetivo do plano de aula (AJAX)
     */
    public function buscarObjetivoPlanoAula()
    {
        try {
            $user = $this->authManager->getUser();
            
            $planoAulaId = $_GET['plano_aula_id'] ?? null;
            if (empty($planoAulaId)) {
                $this->json(['success' => false, 'objetivo' => ''], 400);
                return;
            }
            
            // Busca dados do professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            if (!$professor) {
                throw new Exception('Professor não encontrado');
            }
            
            // Busca o plano de aula e verifica se pertence ao professor
            $planoAula = $this->db->fetch(
                "SELECT objetivos, objetivos_lista FROM planos_aula WHERE id = :id AND professor_id = :prof_id",
                [
                    'id' => $planoAulaId,
                    'prof_id' => $professor['id']
                ]
            );
            
            if (!$planoAula) {
                $this->json(['success' => false, 'error' => 'Plano de aula não encontrado'], 404);
                return;
            }
            
            // Combina objetivos e objetivos_lista
            $objetivos = [];
            if (!empty($planoAula['objetivos'])) {
                // Remove tags HTML e converte entidades HTML para texto
                $objetivoLimpo = strip_tags($planoAula['objetivos']);
                $objetivoLimpo = html_entity_decode($objetivoLimpo, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Converte <br> e <br/> para quebras de linha
                $objetivoLimpo = preg_replace('/<br\s*\/?>/i', "\n", $objetivoLimpo);
                // Remove espaços múltiplos mas preserva quebras de linha
                $objetivoLimpo = preg_replace('/[ \t]+/', ' ', $objetivoLimpo);
                // Remove quebras de linha múltiplas (mais de 2)
                $objetivoLimpo = preg_replace('/\n{3,}/', "\n\n", $objetivoLimpo);
                $objetivoLimpo = trim($objetivoLimpo);
                if (!empty($objetivoLimpo)) {
                    $objetivos[] = $objetivoLimpo;
                }
            }
            if (!empty($planoAula['objetivos_lista'])) {
                // Remove tags HTML e converte entidades HTML para texto
                $objetivoListaLimpo = strip_tags($planoAula['objetivos_lista']);
                $objetivoListaLimpo = html_entity_decode($objetivoListaLimpo, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Converte <br> e <br/> para quebras de linha
                $objetivoListaLimpo = preg_replace('/<br\s*\/?>/i', "\n", $objetivoListaLimpo);
                // Remove espaços múltiplos mas preserva quebras de linha
                $objetivoListaLimpo = preg_replace('/[ \t]+/', ' ', $objetivoListaLimpo);
                // Remove quebras de linha múltiplas (mais de 2)
                $objetivoListaLimpo = preg_replace('/\n{3,}/', "\n\n", $objetivoListaLimpo);
                $objetivoListaLimpo = trim($objetivoListaLimpo);
                if (!empty($objetivoListaLimpo)) {
                    $objetivos[] = $objetivoListaLimpo;
                }
            }
            
            $objetivo = implode("\n\n", $objetivos);
            
            $this->json([
                'success' => true,
                'objetivo' => $objetivo
            ]);
            
        } catch (Exception $e) {
            error_log("Erro em buscarObjetivoPlanoAula: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao buscar objetivo: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Busca alunos das turmas selecionadas (AJAX)
     */
    public function buscarAlunos()
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca dados do professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            if (!$professor) {
                throw new Exception('Professor não encontrado');
            }
            
            // Obtém IDs das turmas da query string
            $turmasParam = $_GET['turmas'] ?? '';
            if (empty($turmasParam)) {
                $this->json(['success' => false, 'error' => 'Nenhuma turma selecionada'], 400);
                return;
            }
            
            $turmasIds = array_map('intval', explode(',', $turmasParam));
            $turmasIds = array_filter($turmasIds);
            
            if (empty($turmasIds)) {
                $this->json(['success' => false, 'error' => 'IDs de turmas inválidos'], 400);
                return;
            }
            
            // Verifica se as turmas pertencem ao professor
            $turmas_professor = json_decode($professor['turmas'], true) ?: [];
            $turmasValidas = array_values(array_intersect($turmasIds, $turmas_professor));
            
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
            
            $this->json([
                'success' => true,
                'alunos' => $alunos
            ]);
            
        } catch (Exception $e) {
            error_log("Erro em buscarAlunos: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao buscar alunos: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Salva nova jornada
     */
    public function salvar()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $user = $this->authManager->getUser();
            $titulo = trim($_POST['titulo'] ?? '');
            
            // Processa múltiplas turmas
            $turmasIds = $_POST['turmas_id'] ?? [];
            if (is_string($turmasIds)) {
                $turmasIds = [$turmasIds];
            }
            $turmaId = !empty($turmasIds) ? (int)$turmasIds[0] : ($_POST['turma_id'] ?? null);
            
            $materiaId = $_POST['materia_id'] ?? null;
            $anoLetivo = (int)($_POST['ano_letivo'] ?? 0);
            $bimestre = (int)($_POST['bimestre'] ?? 0);
            $avaliativo = 1;
            $objetivos = trim($_POST['objetivos'] ?? '');
            $dataInicio = $_POST['data_inicio'] ?? null;
            $dataFim = $_POST['data_fim'] ?? null;
            $horaInicio = $_POST['hora_inicio'] ?? null;
            $horaFim = $_POST['hora_fim'] ?? null;
            
            // Processa seleção de planos de aula (múltiplos)
            $planosAulaIds = $_POST['planos_aula_id'] ?? [];
            if (is_string($planosAulaIds)) {
                $planosAulaIds = [$planosAulaIds];
            }
            $planosAulaIds = array_map('intval', array_filter($planosAulaIds));
            
            // Primeiro plano é usado como plano_aula_id principal (para compatibilidade)
            $planoAulaId = !empty($planosAulaIds) ? $planosAulaIds[0] : null;
            
            // Processa seleção de alunos
            $tipoSelecaoAlunos = $_POST['tipo_selecao_alunos'] ?? 'todos';
            $alunosIds = [];
            if ($tipoSelecaoAlunos === 'especificos') {
                $alunosIds = $_POST['alunos_id'] ?? [];
                if (is_string($alunosIds)) {
                    $alunosIds = [$alunosIds];
                }
                $alunosIds = array_values(array_unique(array_map('intval', $alunosIds)));
            }
            
            // Validações
            if (empty($titulo) || empty($turmaId) || empty($turmasIds)) {
                throw new Exception('Título e pelo menos uma turma são obrigatórios');
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
            
            // Verifica se as turmas pertencem ao professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            $turmas_professor = json_decode($professor['turmas'], true) ?: [];
            foreach ($turmasIds as $tid) {
                if (!in_array($tid, $turmas_professor)) {
                    throw new Exception('Uma ou mais turmas não são autorizadas');
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
            
            // Verifica se os planos de aula pertencem ao professor (se fornecidos)
            if (!empty($planosAulaIds)) {
                foreach ($planosAulaIds as $pid) {
                    $planoAula = $this->db->fetch(
                        "SELECT id FROM planos_aula WHERE id = :id AND professor_id = :prof_id",
                        ['id' => $pid, 'prof_id' => $user['id']]
                    );
                    if (!$planoAula) {
                        throw new Exception('Um ou mais planos de aula não foram encontrados ou não são autorizados');
                    }
                }
            }
            
            // Status na coluna: apenas 'ativa', 'pausada', 'finalizada' (ENUM/VARCHAR curto).
            // O status por data (aguardando/em_andamento/concluido) fica na estrutura JSON e no cron.
            $statusInicial = 'ativa';
            
            // Insere jornada (ativo = 1 para aparecer na listagem do professor)
            $jornadaId = $this->db->insert(
                "INSERT INTO jornadas (professor_id, turma_id, materia_id, titulo, ano_letivo, bimestre, avaliativo, estrutura, status, plano_aula_id, ativo) 
                 VALUES (:prof_id, :turma_id, :materia_id, :titulo, :ano_letivo, :bimestre, :avaliativo, :estrutura, :status, :plano_aula_id, 1)",
                [
                    'prof_id' => $user['id'],
                    'turma_id' => $turmaId,
                    'materia_id' => $materiaId,
                    'titulo' => $titulo,
                    'ano_letivo' => $anoLetivo,
                    'bimestre' => $bimestre,
                    'avaliativo' => $avaliativo,
                    'status' => $statusInicial,
                    'plano_aula_id' => $planoAulaId,
                    'estrutura' => json_encode([
                        'objetivos' => $objetivos,
                        'data_inicio' => $dataInicio,
                        'data_fim' => $dataFim,
                        'hora_inicio' => $horaInicio,
                        'hora_fim' => $horaFim,
                        'turmas_selecionadas' => array_map('intval', $turmasIds),
                        'alunos_selecionados' => $alunosIds,
                        'tipo_selecao_alunos' => $tipoSelecaoAlunos,
                        'planos_aula_selecionados' => $planosAulaIds
                    ])
                ]
            );
            
            $this->json(['success' => true, 'message' => 'Jornada criada com sucesso', 'jornada_id' => $jornadaId]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Mostra detalhes de uma jornada
     */
    public function show($id)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca dados da jornada
            $jornada = $this->db->fetch(
                "SELECT j.*, t.nome as turma_nome, m.nome as materia_nome
                 FROM jornadas j
                 JOIN turmas t ON j.turma_id = t.id
                 LEFT JOIN materias m ON j.materia_id = m.id
                 WHERE j.id = :id AND j.professor_id = :prof_id",
                ['id' => $id, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            if (isset($jornada['ativo']) && (int)$jornada['ativo'] === 0) {
                $_SESSION['error_message'] = 'Esta jornada foi deletada da lista e não está mais disponível.';
                $this->redirect('/professor/jornadas');
                return;
            }
            
            // Busca aulas da jornada
            $aulas = $this->db->fetchAll(
                "SELECT * FROM jornadas_aulas WHERE jornada_id = :jornada_id ORDER BY ordem ASC",
                ['jornada_id' => $id]
            );
            
            // Busca exercícios da jornada
            $exercicios = $this->db->fetchAll(
                "SELECT je.*, ja.nome_aula as nome_aula
                 FROM jornadas_exercicios je
                 LEFT JOIN jornadas_aulas ja ON je.aula_id = ja.id
                 WHERE je.jornada_id = :jornada_id
                 ORDER BY je.created_at DESC",
                ['jornada_id' => $id]
            );
            
            // Busca dúvidas pendentes
            $duvidas = $this->db->fetchAll(
                "SELECT jd.*, a.nome as nome_aluno, ja.nome_aula as nome_aula
                 FROM jornadas_duvidas jd
                 JOIN alunos a ON jd.aluno_id = a.id
                 JOIN jornadas_aulas ja ON jd.aula_id = ja.id
                 WHERE ja.jornada_id = :jornada_id AND jd.status = 'pendente'
                 ORDER BY jd.created_at DESC",
                ['jornada_id' => $id]
            );
            
            // Busca estatísticas
            $total_alunos = $this->db->fetch(
                "SELECT COUNT(DISTINCT a.id) as total 
                 FROM alunos a 
                 JOIN turmas t ON a.turma_id = t.id 
                 WHERE t.id = :turma_id",
                ['turma_id' => $jornada['turma_id']]
            )['total'] ?? 0;
            
            $total_aulas = count($aulas);
            $total_exercicios = count($exercicios);
            
            // Extrai dados da estrutura JSON
            $estrutura = json_decode($jornada['estrutura'], true) ?: [];
            $data_inicio = $estrutura['data_inicio'] ?? null;
            $data_fim = $estrutura['data_fim'] ?? null;
            
            // Busca todas as turmas selecionadas
            $turmasSelecionadas = $estrutura['turmas_selecionadas'] ?? [];
            $turmasNomes = [];
            
            if (!empty($turmasSelecionadas) && is_array($turmasSelecionadas)) {
                // Busca nomes das turmas selecionadas
                $placeholders = str_repeat('?,', count($turmasSelecionadas) - 1) . '?';
                $turmasNomesResult = $this->db->fetchAll(
                    "SELECT nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
                    $turmasSelecionadas
                );
                $turmasNomes = array_column($turmasNomesResult, 'nome');
            } else {
                // Se não houver turmas selecionadas, usa a turma principal
                $turmasNomes = [$jornada['turma_nome']];
            }
            
            // Recalcula e persiste status (data+hora) ao abrir a jornada — corrige sem depender do CRON
            $dataAtual = date('Y-m-d');
            if (!class_exists('JornadaStatusHelper')) {
                require_once __DIR__ . '/../../Core/JornadaStatusHelper.php';
            }
            $jornada['status_jornada'] = JornadaStatusHelper::recalcularEPersistir(
                $this->db,
                (int) $jornada['id'],
                $jornada['estrutura'],
                $jornada['status'] ?? null
            );

            // Se em_andamento e todos os alunos já finalizaram a jornada → Concluído
            if ($total_alunos > 0 && ($jornada['status_jornada'] ?? '') === 'em_andamento') {
                $concluidaRow = $this->db->fetch(
                    "SELECT COUNT(DISTINCT jpa.aluno_id) as total
                     FROM jornadas_progresso_alunos jpa
                     WHERE jpa.jornada_id = :jornada_id
                     AND jpa.status = 'concluido'
                     AND jpa.atividade_tipo IS NULL
                     AND jpa.modulo_id IS NULL
                     AND jpa.aula_id IS NULL
                     AND jpa.exercicio_id IS NULL",
                    ['jornada_id' => $id]
                );
                $concluidaCount = (int)($concluidaRow['total'] ?? 0);
                if ($concluidaCount >= $total_alunos) {
                    $jornada['status_jornada'] = 'concluido';
                    $estrutura['status_jornada'] = 'concluido';
                    $this->db->update(
                        "UPDATE jornadas SET estrutura = :estrutura, status = 'finalizada', updated_at = NOW() WHERE id = :id",
                        ['estrutura' => json_encode($estrutura, JSON_UNESCAPED_UNICODE), 'id' => $jornada['id']]
                    );
                }
            }
            
            // Verifica se há alunos que já iniciaram a jornada
            $alunosIniciaram = false;
            if ($total_alunos > 0) {
                $progressoAlunos = $this->db->fetch(
                    "SELECT COUNT(DISTINCT aluno_id) as total 
                     FROM jornadas_progresso_alunos 
                     WHERE jornada_id = :jornada_id",
                    ['jornada_id' => $id]
                );
                $alunosIniciaram = ($progressoAlunos['total'] ?? 0) > 0;
            }
            
            // Professor pode sempre acessar o gerenciador de blocos (aguardando, em andamento ou concluído)
            $mostrarGerenciarBlocos = true;
            
            // Busca estatísticas dos exercícios (dashboard)
            $dashboard_exercicios = [];
            $tem_exercicios = false;
            
            // Busca exercícios dos módulos (estrutura nova)
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
            
            // Busca exercícios da estrutura antiga
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
            
            // Combina ambos os tipos
            $dashboard_exercicios = array_merge($exerciciosModulos, $exerciciosAntigos);
            // Sempre mostra o dashboard, mesmo sem exercícios com respostas
            $tem_exercicios = true;
            
            // Calcula estatísticas gerais
            $stats_exercicios = [
                'total_exercicios_com_respostas' => count($dashboard_exercicios),
                'exercicios_alto_erro' => [],
                'mais_acertados' => [],
                'mais_errados' => []
            ];
            
            if ($tem_exercicios) {
                // Identifica exercícios com mais de 60% de erro
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
                    $taxaA = (float)($a['taxa_acerto'] ?? 0);
                    $taxaB = (float)($b['taxa_acerto'] ?? 0);
                    return $taxaB <=> $taxaA;
                });
                $stats_exercicios['mais_acertados'] = $maisAcertados;
                
                // Lista todos na ordem mais errado primeiro (taxa_erro DESC)
                $maisErrados = $dashboard_exercicios;
                usort($maisErrados, function($a, $b) {
                    $taxaA = (float)($a['taxa_erro'] ?? 0);
                    $taxaB = (float)($b['taxa_erro'] ?? 0);
                    return $taxaB <=> $taxaA;
                });
                $stats_exercicios['mais_errados'] = $maisErrados;
            }
            
            // Busca alunos que precisam de atenção (taxa de erro > 40%) – todas as turmas da jornada, módulos e estrutura antiga
            $alunos_atencao = [];
            if ($tem_exercicios) {
                $turmasIds = array_values(array_unique(array_filter(
                    array_merge([(int) $jornada['turma_id']], array_map('intval', $turmasSelecionadas ?? []))
                )));
                if (empty($turmasIds)) {
                    $turmasIds = [(int) $jornada['turma_id']];
                }
                $placeholders = implode(',', array_fill(0, count($turmasIds), '?'));
                $params = array_merge([$id], $turmasIds);
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
                     WHERE a.turma_id IN ($placeholders) AND a.ativo = 1
                     GROUP BY a.id, a.nome, a.ra
                     HAVING total_erros > 0 AND taxa_erro > 40
                     ORDER BY taxa_erro DESC, total_erros DESC
                     LIMIT 10",
                    $params
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
                'title' => $jornada['titulo'] . ' - EducaTudo',
                'jornada' => $jornada,
                'aulas' => $aulas,
                'exercicios' => $exercicios,
                'duvidas' => $duvidas,
                'total_alunos' => $total_alunos,
                'total_aulas' => $total_aulas,
                'total_exercicios' => $total_exercicios,
                'data_inicio' => $data_inicio,
                'data_fim' => $data_fim,
                'turmas_nomes' => $turmasNomes,
                'tem_exercicios' => $tem_exercicios,
                'dashboard_exercicios' => $dashboard_exercicios,
                'stats_exercicios' => $stats_exercicios,
                'alunos_atencao' => $alunos_atencao,
                'mostrarGerenciarBlocos' => $mostrarGerenciarBlocos,
                'user' => $user,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/show', $data);
            
        } catch (Exception $e) {
            // Log do erro para debug
            error_log("Erro em ProfessorJornadaController::show(): " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            
            // Em vez de redirecionar, mostra o erro
            $this->json(['error' => 'Erro ao carregar jornada: ' . $e->getMessage()], 500);
        }
    }
    
    /** POST: enfileira geração de exercícios por IA (assíncrono) */
    public function gerarExercicioIA()
    {
        $jornadaId = $_POST['jornada_id'] ?? null;

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->redirect("/teacher/jornadas/{$jornadaId}/exercicios/ia?erro=" . urlencode('Token inválido'));
            return;
        }

        try {
            $user    = $this->authManager->getUser();
            $aulaId  = $_POST['aula_id']       ?? null;
            $materia = trim($_POST['materia']   ?? '');
            $nivel   = trim($_POST['nivel']     ?? 'médio');
            $tipoExercicio = trim($_POST['tipo_exercicio'] ?? 'múltipla_escolha');
            $quantidade    = (int) ($_POST['quantidade']   ?? 5);
            $contextoAula  = trim($_POST['contexto_aula']  ?? '');

            if (empty($jornadaId) || empty($materia)) {
                throw new Exception('Jornada e matéria são obrigatórios');
            }

            $jornada = $this->db->fetch(
                "SELECT j.*, m.nome as materia_nome FROM jornadas j
                 LEFT JOIN materias m ON j.materia_id = m.id
                 WHERE j.id = :id AND j.professor_id = :prof_id",
                ['id' => $jornadaId, 'prof_id' => $user['id']]
            );
            if (!$jornada) {
                throw new Exception('Jornada não encontrada ou não autorizada');
            }

            $aula = null;
            if ($aulaId) {
                $aula = $this->db->fetch(
                    "SELECT * FROM jornadas_aulas WHERE id = :id AND jornada_id = :jornada_id",
                    ['id' => $aulaId, 'jornada_id' => $jornadaId]
                );
            }

            $contextoCompleto  = "Matéria: {$materia}\nNível: {$nivel}\n";
            if ($aula) {
                $contextoCompleto .= "Título da aula: {$aula['nome_aula']}\nConteúdo da aula: {$aula['conteudo']}\n";
            }
            if ($contextoAula) {
                $contextoCompleto .= "Contexto adicional: {$contextoAula}\n";
            }

            // Consome crédito antes de enfileirar
            $referenciaJornada = 'jornada_' . $jornadaId . '_aula_' . ($aulaId ?? 0);
            require_once __DIR__ . '/../../Services/CreditosService.php';
            $creditosService = new \App\Services\CreditosService();
            try {
                $creditosService->consumir('professor', (int) $user['id'], 'gerar_exercicio_ia_professor', $referenciaJornada);
            } catch (Exception $e) {
                if (stripos($e->getMessage(), 'TudiCoins') !== false || stripos($e->getMessage(), 'insuficientes') !== false || stripos($e->getMessage(), 'Créditos') !== false) {
                    $this->redirect("/teacher/jornadas/{$jornadaId}/exercicios/ia?erro=" . urlencode($e->getMessage()));
                    return;
                }
                throw $e;
            }

            require_once __DIR__ . '/../../Services/AIJobService.php';
            $jobId = \App\Services\AIJobService::enqueue(
                'gerar_exercicios_jornada',
                [
                    'jornada_id'   => (int) $jornadaId,
                    'aula_id'      => $aulaId ? (int) $aulaId : null,
                    'contexto'     => $contextoCompleto,
                    'tipo'         => $tipoExercicio,
                    'quantidade'   => $quantidade,
                    'materia'      => $materia,
                    'credits_ref'    => $referenciaJornada,
                    'credits_modulo' => 'gerar_exercicio_ia_professor',
                ],
                (int) $user['id'],
                'professor'
            );

            // Redireciona para página de aguardo com polling
            $this->redirect("/teacher/jornadas/{$jornadaId}/exercicios/ia?job_id={$jobId}");

        } catch (Exception $e) {
            if (isset($creditosService, $referenciaJornada)) {
                try {
                    $creditosService->estornarPorReferencia('gerar_exercicio_ia_professor', $referenciaJornada);
                } catch (Exception $e2) {
                    // estorno falhou, logar mas não bloquear
                }
            }
            $this->redirect("/teacher/jornadas/{$jornadaId}/exercicios/ia?erro=" . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Aprova exercício gerado pela IA
     */
    public function aprovarExercicioIA()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $user = $this->authManager->getUser();
            $exercicioId = $_POST['exercicio_id'] ?? null;
            $modificacoes = $_POST['modificacoes'] ?? null;
            
            if (empty($exercicioId)) {
                throw new Exception('ID do exercício é obrigatório');
            }
            
            // Busca o exercício
            $exercicio = $this->db->fetch(
                "SELECT je.*, j.professor_id FROM jornadas_exercicios je
                 JOIN jornadas j ON je.jornada_id = j.id
                 WHERE je.id = :id AND j.professor_id = :prof_id",
                ['id' => $exercicioId, 'prof_id' => $user['id']]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado ou não autorizado');
            }
            
            // Se há modificações, aplica elas
            $conteudoFinal = $exercicio['questoes_json'];
            if ($modificacoes) {
                $conteudoAtual = json_decode($exercicio['questoes_json'], true);
                $modificacoesArray = json_decode($modificacoes, true);
                
                // Aplica as modificações
                foreach ($modificacoesArray as $modificacao) {
                    if (isset($modificacao['questao_id']) && isset($modificacao['campo']) && isset($modificacao['valor'])) {
                        $questaoId = $modificacao['questao_id'];
                        $campo = $modificacao['campo'];
                        $valor = $modificacao['valor'];
                        
                        if (isset($conteudoAtual['questoes'][$questaoId])) {
                            $conteudoAtual['questoes'][$questaoId][$campo] = $valor;
                        }
                    }
                }
                
                $conteudoFinal = json_encode($conteudoAtual);
            }
            
            // Atualiza o exercício para aprovado
            $this->db->update(
                "UPDATE jornadas_exercicios 
                 SET status = 'aprovado', questoes_json = :questoes_json, updated_at = NOW()
                 WHERE id = :id",
                [
                    'questoes_json' => $conteudoFinal,
                    'id' => $exercicioId
                ]
            );
            
            $this->json([
                'success' => true,
                'message' => 'Exercício aprovado com sucesso!'
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Publica exercício aprovado
     */
    public function publicarExercicio()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $user = $this->authManager->getUser();
            $exercicioId = $_POST['exercicio_id'] ?? null;
            
            if (empty($exercicioId)) {
                throw new Exception('ID do exercício é obrigatório');
            }
            
            // Busca o exercício
            $exercicio = $this->db->fetch(
                "SELECT je.*, j.professor_id FROM jornadas_exercicios je
                 JOIN jornadas j ON je.jornada_id = j.id
                 WHERE je.id = :id AND j.professor_id = :prof_id AND je.status = 'aprovado'",
                ['id' => $exercicioId, 'prof_id' => $user['id']]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado ou não está aprovado');
            }
            
            // Atualiza o exercício para publicado
            $this->db->update(
                "UPDATE jornadas_exercicios 
                 SET status = 'publicado', updated_at = NOW()
                 WHERE id = :id",
                ['id' => $exercicioId]
            );
            
            $this->json([
                'success' => true,
                'message' => 'Exercício publicado com sucesso!'
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Gera preview dos exercícios
     */
    private function gerarPreviewExercicios($exercicios)
    {
        $preview = '<div class="space-y-4">';
        
        if (isset($exercicios['questoes'])) {
            foreach ($exercicios['questoes'] as $index => $questao) {
                $preview .= '<div class="border rounded-lg p-4">';
                $preview .= '<h4 class="font-semibold mb-2">Questão ' . ($index + 1) . '</h4>';
                $preview .= '<p class="text-gray-700 mb-3">' . htmlspecialchars($questao['enunciado']) . '</p>';
                
                if (isset($questao['alternativas'])) {
                    $preview .= '<div class="space-y-1">';
                    foreach ($questao['alternativas'] as $letra => $alternativa) {
                        $preview .= '<div class="flex items-center">';
                        $preview .= '<span class="font-semibold mr-2">' . strtoupper($letra) . ')</span>';
                        $preview .= '<span>' . htmlspecialchars($alternativa) . '</span>';
                        $preview .= '</div>';
                    }
                    $preview .= '</div>';
                }
                
                if (isset($questao['resposta_correta'])) {
                    $preview .= '<div class="mt-2 text-sm text-green-600">';
                    $preview .= '<strong>Resposta:</strong> ' . strtoupper($questao['resposta_correta']);
                    $preview .= '</div>';
                }
                
                $preview .= '</div>';
            }
        }
        
        $preview .= '</div>';
        return $preview;
    }
    
    /**
     * Exibe formulário para gerar exercícios com IA
     */
    public function exercicioIAForm($jornadaId)
    {
        try {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("=== INÍCIO exercicioIAForm ===");
                }
            }
            error_log("Jornada ID: " . $jornadaId);
            
            $user = $this->authManager->getUser();
            error_log("User: " . print_r($user, true));
            
            // Se não conseguir autenticar, usa professor ID 4 para teste
            if (!$user) {
                error_log("Usuário não autenticado, usando professor ID 4 para teste");
                $user = ['id' => 4, 'tipo' => 'professor'];
            }
            
            // Busca dados da jornada
            $jornada = $this->db->fetch(
                "SELECT j.*, m.nome as materia_nome FROM jornadas j
                 LEFT JOIN materias m ON j.materia_id = m.id
                 WHERE j.id = :id AND j.professor_id = :prof_id",
                ['id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            error_log("Jornada encontrada: " . print_r($jornada, true));
            
            if (!$jornada) {
                error_log("Jornada não encontrada para ID: $jornadaId e professor: " . $user['id']);
                throw new Exception('Jornada não encontrada');
            }
            
            // Busca matérias do professor
            $professor = $this->db->fetch(
                "SELECT materias FROM professores WHERE id = :id",
                ['id' => $user['id']]
            );
            
            $materias_professor = json_decode($professor['materias'], true) ?: [];
            if (empty($materias_professor)) {
                $materias = [];
            } else {
                $placeholders = str_repeat('?,', count($materias_professor) - 1) . '?';
                $materias = $this->db->fetchAll(
                    "SELECT * FROM materias WHERE nome IN ($placeholders) ORDER BY nome ASC",
                    $materias_professor
                );
            }
            
            // Busca aulas da jornada
            $aulas = $this->db->fetchAll(
                "SELECT * FROM jornadas_aulas WHERE jornada_id = :jornada_id ORDER BY ordem ASC",
                ['jornada_id' => $jornadaId]
            );
            
            $data = [
                'title' => 'Gerar Exercícios com IA - EducaTudo',
                'jornada' => $jornada,
                'materias' => $materias,
                'aulas' => $aulas,
                'jornada_id' => $jornadaId,
                'csrf_token' => $this->generateCsrfToken(),
                'user' => $user
            ];
            
            $this->view('teacher/journeys/exercicio-ia-form', $data);
            
        } catch (Exception $e) {
            error_log("Erro em ProfessorJornadaController::exercicioIAForm(): " . $e->getMessage());
            $this->json(['error' => 'Erro ao carregar formulário: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Exibe resultado dos exercícios gerados pela IA
     */
    public function exercicioResultado($jornadaId, $exercicioId)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Se não conseguir autenticar, usa professor ID 4 para teste
            if (!$user) {
                $user = ['id' => 4, 'tipo' => 'professor'];
            }
            
            // Busca o exercício gerado
            $exercicio = $this->db->fetch(
                "SELECT je.*, j.titulo as jornada_titulo FROM jornadas_exercicios je
                 JOIN jornadas j ON je.jornada_id = j.id
                 WHERE je.id = :exercicio_id AND je.jornada_id = :jornada_id AND j.professor_id = :prof_id",
                ['exercicio_id' => $exercicioId, 'jornada_id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado');
            }
            
            $exerciciosData = json_decode($exercicio['questoes_json'], true);
            
            $data = [
                'title' => 'Exercícios Gerados - EducaTudo',
                'jornada' => ['id' => $jornadaId, 'titulo' => $exercicio['jornada_titulo']],
                'exercicio' => $exercicio,
                'exercicios' => $exerciciosData,
                'preview' => $this->gerarPreviewExercicios($exerciciosData),
                'csrf_token' => $this->generateCsrfToken(),
                'user' => $user,
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/exercicio-resultado', $data);
            
        } catch (Exception $e) {
            error_log("Erro em exercicioResultado: " . $e->getMessage());
            $this->redirect("/teacher/jornadas/{$jornadaId}/exercicios/ia?erro=" . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Lista exercícios da jornada
     */
    public function exercicios($jornadaId)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca dados do professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            // Busca dados da jornada
            $jornada = $this->db->fetch(
                "SELECT j.*, t.nome as turma_nome, m.nome as materia_nome
                 FROM jornadas j
                 JOIN turmas t ON j.turma_id = t.id
                 LEFT JOIN materias m ON j.materia_id = m.id
                 WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
                [
                    'jornada_id' => $jornadaId,
                    'prof_id' => $professor['id']
                ]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            // Busca exercícios da jornada
            $exercicios = $this->db->fetchAll(
                "SELECT je.*, ja.nome_aula as nome_aula
                 FROM jornadas_exercicios je
                 LEFT JOIN jornadas_aulas ja ON je.aula_id = ja.id
                 WHERE je.jornada_id = :jornada_id
                 ORDER BY je.created_at DESC",
                ['jornada_id' => $jornadaId]
            );
            
            // Busca aulas da jornada
            $aulas = $this->db->fetchAll(
                "SELECT * FROM jornadas_aulas WHERE jornada_id = :jornada_id ORDER BY ordem ASC",
                ['jornada_id' => $jornadaId]
            );
            
            $data = [
                'title' => 'Exercícios da Jornada - EducaTudo',
                'jornada' => $jornada,
                'exercicios' => $exercicios,
                'aulas' => $aulas,
                'user' => $user,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/exercicios', $data);
            
        } catch (Exception $e) {
            $this->redirectToCorrectDashboard($user['tipo'] ?? 'professor');
        }
    }
    
    /**
     * Formulário para criar/editar exercício
     */
    public function exercicioForm($jornadaId, $exercicioId = null)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca dados do professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            // Busca dados da jornada
            $jornada = $this->db->fetch(
                "SELECT j.*, t.nome as turma_nome, m.nome as materia_nome
                 FROM jornadas j
                 JOIN turmas t ON j.turma_id = t.id
                 LEFT JOIN materias m ON j.materia_id = m.id
                 WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
                [
                    'jornada_id' => $jornadaId,
                    'prof_id' => $professor['id']
                ]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            // Busca aulas da jornada
            $aulas = $this->db->fetchAll(
                "SELECT * FROM jornadas_aulas WHERE jornada_id = :jornada_id ORDER BY ordem ASC",
                ['jornada_id' => $jornadaId]
            );
            
            // Busca matérias do professor
            $materias_professor = json_decode($professor['materias'], true) ?: [];
            
            if (empty($materias_professor)) {
                $materias = [];
            } else {
                $placeholders = str_repeat('?,', count($materias_professor) - 1) . '?';
                $materias = $this->db->fetchAll(
                    "SELECT * FROM materias WHERE nome IN ($placeholders) ORDER BY nome ASC",
                    $materias_professor
                );
            }
            
            $exercicio = null;
            if ($exercicioId) {
                $exercicio = $this->db->fetch(
                    "SELECT * FROM jornadas_exercicios WHERE id = :id AND jornada_id = :jornada_id",
                    ['id' => $exercicioId, 'jornada_id' => $jornadaId]
                );
            }
            
            $data = [
                'title' => ($exercicio ? 'Editar Exercício' : 'Criar Exercício') . ' - EducaTudo',
                'jornada' => $jornada,
                'aulas' => $aulas,
                'materias' => $materias,
                'exercicio' => $exercicio,
                'aula' => $aulas[0] ?? null, // Primeira aula como padrão
                'user' => $user,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/exercicio-form', $data);
            
        } catch (Exception $e) {
            $this->redirectToCorrectDashboard($user['tipo'] ?? 'professor');
        }
    }
    
    /**
     * Antiga "Análise de Resumos": redireciona para Resultados da Jornada (aba Resumos)
     */
    public function analiseResumos($jornadaId)
    {
        $jornadaId = filter_var($jornadaId, FILTER_VALIDATE_INT);
        if (!$jornadaId || $jornadaId <= 0) {
            $_SESSION['error_message'] = 'ID da jornada inválido';
            $this->redirect('/professor/jornadas');
            return;
        }
        // Se veio com resumo_id, vai direto para a página do resumo
        $resumoId = isset($_GET['resumo_id']) ? filter_var($_GET['resumo_id'], FILTER_VALIDATE_INT) : null;
        if ($resumoId && $resumoId > 0) {
            $this->redirect('/professor/jornadas/' . $jornadaId . '/resumos/' . $resumoId);
            return;
        }
        $this->redirect('/professor/jornadas/' . $jornadaId . '/exercicios-alunos?tab=resumos');
    }
    
    /**
     * Página única: ver resumo do aluno + atribuir nota e observações
     */
    public function verResumo($jornadaId, $resumoId)
    {
        $jornadaId = filter_var($jornadaId, FILTER_VALIDATE_INT);
        $resumoId = filter_var($resumoId, FILTER_VALIDATE_INT);
        if (!$jornadaId || !$resumoId) {
            $_SESSION['error_message'] = 'ID inválido';
            $this->redirect('/professor/jornadas');
            return;
        }
        
        $user = $this->authManager->getUser();
        if (!$user) {
            $_SESSION['error_message'] = 'Usuário não autenticado';
            $this->redirect('/logout');
            return;
        }
        
        $professor = $this->db->fetch(
            "SELECT p.* FROM professores p WHERE p.id = :user_id",
            ['user_id' => $user['id']]
        );
        if (!$professor) {
            $_SESSION['error_message'] = 'Professor não encontrado';
            $this->redirect('/professor/jornadas');
            return;
        }
        
        $jornada = $this->db->fetch(
            "SELECT j.* FROM jornadas j WHERE j.id = :id AND j.professor_id = :prof_id",
            ['id' => $jornadaId, 'prof_id' => $professor['id']]
        );
        if (!$jornada) {
            $_SESSION['error_message'] = 'Jornada não encontrada ou sem permissão';
            $this->redirect('/professor/jornadas');
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
             WHERE jra.id = :resumo_id AND (j.id = :jornada_id) AND j.professor_id = :prof_id",
            ['resumo_id' => $resumoId, 'jornada_id' => $jornadaId, 'prof_id' => $professor['id']]
        );
        if (!$resumo) {
            $_SESSION['error_message'] = 'Resumo não encontrado';
            $this->redirect('/professor/jornadas/' . $jornadaId . '/exercicios-alunos?tab=resumos');
            return;
        }
        
        $data = [
            'title' => 'Resumo do aluno - EducaTudo',
            'jornada' => $jornada,
            'resumo' => $resumo,
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'journeys'
        ];
        $this->viewWithLayout('professor', 'teacher/journeys/ver-resumo', $data);
    }
    
    /**
     * Busca detalhes de um exercício
     */
    public function buscarExercicio($exercicioId)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca dados do professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            // Busca exercício
            $exercicio = $this->db->fetch(
                "SELECT je.* FROM jornadas_exercicios je
                 JOIN jornadas j ON je.jornada_id = j.id
                 WHERE je.id = :id AND j.professor_id = :prof_id",
                ['id' => $exercicioId, 'prof_id' => $professor['id']]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado');
            }
            
            $this->json(['success' => true, 'exercicio' => $exercicio]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Busca detalhes de um resumo
     */
    public function buscarResumo($resumoId)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca dados do professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            // Busca resumo (com suporte para módulos e aulas)
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
                 WHERE jra.id = :id AND j.professor_id = :prof_id",
                ['id' => $resumoId, 'prof_id' => $professor['id']]
            );
            
            if (!$resumo) {
                throw new Exception('Resumo não encontrado');
            }

            $textoResumo = $resumo['resumo_texto'] ?? $resumo['resumo_aluno'] ?? '';
            $resumo['resumo_texto_display'] = \App\Utils\HtmlSanitizer::displaySafe($textoResumo);
            
            $this->json(['success' => true, 'resumo' => $resumo]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Atribui nota a um resumo
     */
    public function atribuirNotaResumo()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            $user = $this->authManager->getUser();
            
            // Busca dados do professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            $resumoId = $_POST['resumo_id'] ?? null;
            $nota = $_POST['nota'] ?? null;
            $observacoes = $_POST['observacoes'] ?? null;
            
            if (empty($resumoId)) {
                throw new Exception('ID do resumo é obrigatório');
            }
            
            // Valida nota (0 a 10)
            if ($nota !== null && $nota !== '') {
                $nota = filter_var($nota, FILTER_VALIDATE_FLOAT);
                if ($nota === false || $nota < 0 || $nota > 10) {
                    throw new Exception('Nota deve ser um número entre 0 e 10');
                }
            } else {
                $nota = null;
            }
            
            // Verifica se o resumo existe e pertence a uma jornada do professor
            $resumo = $this->db->fetch(
                "SELECT jra.*, 
                        COALESCE(m.jornada_id, ja.jornada_id) as jornada_id
                 FROM jornadas_resumos_alunos jra
                 LEFT JOIN jornadas_modulos m ON jra.modulo_id = m.id
                 LEFT JOIN jornadas_aulas ja ON jra.aula_id = ja.id
                 WHERE jra.id = :resumo_id",
                ['resumo_id' => $resumoId]
            );
            
            if (!$resumo) {
                throw new Exception('Resumo não encontrado');
            }
            
            // Verifica se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT j.* FROM jornadas j WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
                [
                    'jornada_id' => $resumo['jornada_id'],
                    'prof_id' => $professor['id']
                ]
            );
            
            if (!$jornada) {
                throw new Exception('Você não tem permissão para avaliar este resumo');
            }
            
            // Atualiza a nota e observações
            $this->db->query(
                "UPDATE jornadas_resumos_alunos 
                 SET nota = :nota, 
                     observacoes_professor = :observacoes,
                     updated_at = NOW()
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
            error_log("Erro ao atribuir nota ao resumo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Atribui nota à resposta dissertativa de um exercício (professor)
     */
    public function atribuirNotaDissertativa()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        try {
            $user = $this->authManager->getUser();
            $professor = $this->db->fetch("SELECT p.* FROM professores p WHERE p.id = :user_id", ['user_id' => $user['id']]);
            if (!$professor) {
                throw new Exception('Acesso negado');
            }
            $exercicio_id = $_POST['exercicio_id'] ?? null;
            $aluno_id = $_POST['aluno_id'] ?? null;
            $jornada_id = $_POST['jornada_id'] ?? null;
            $pontuacao = $_POST['pontuacao'] ?? null;
            if (empty($exercicio_id) || empty($aluno_id) || empty($jornada_id)) {
                throw new Exception('Dados obrigatórios: exercício, aluno e jornada');
            }
            $pontuacao = $pontuacao !== null && $pontuacao !== '' ? filter_var($pontuacao, FILTER_VALIDATE_FLOAT) : null;
            if ($pontuacao !== null && ($pontuacao < 0 || $pontuacao === false)) {
                throw new Exception('Pontuação deve ser um número não negativo');
            }
            $jornada = $this->db->fetch(
                "SELECT j.* FROM jornadas j WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
                ['jornada_id' => $jornada_id, 'prof_id' => $professor['id']]
            );
            if (!$jornada) {
                throw new Exception('Você não tem permissão para avaliar esta jornada');
            }
            $pontuacao_val = $pontuacao === null ? 0 : (float) $pontuacao;
            $stmt = $this->db->query(
                "UPDATE jornadas_progresso_alunos 
                 SET pontuacao = :pontuacao, updated_at = NOW() 
                 WHERE exercicio_modulo_id = :exercicio_id AND aluno_id = :aluno_id AND jornada_id = :jornada_id AND atividade_tipo = 'exercicio_modulo'",
                [
                    'pontuacao' => $pontuacao_val,
                    'exercicio_id' => $exercicio_id,
                    'aluno_id' => $aluno_id,
                    'jornada_id' => $jornada_id
                ]
            );
            $affected = $stmt ? $stmt->rowCount() : 0;
            if ($affected === 0) {
                $this->db->query(
                    "UPDATE jornadas_progresso_alunos 
                     SET pontuacao = :pontuacao, updated_at = NOW() 
                     WHERE exercicio_id = :exercicio_id AND aluno_id = :aluno_id AND jornada_id = :jornada_id AND atividade_tipo = 'exercicio'",
                    [
                        'pontuacao' => $pontuacao_val,
                        'exercicio_id' => $exercicio_id,
                        'aluno_id' => $aluno_id,
                        'jornada_id' => $jornada_id
                    ]
                );
            }
            $this->json([
                'success' => true,
                'message' => 'Nota atribuída com sucesso',
                'pontuacao' => $pontuacao_val
            ]);
        } catch (Exception $e) {
            error_log("Erro ao atribuir nota dissertativa: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Gera explicação complementar baseada nas lacunas
     */
    public function gerarExplicacaoComplementar()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $user = $this->authManager->getUser();
            $resumoId = $_POST['resumo_id'] ?? null;
            
            if (empty($resumoId)) {
                throw new Exception('ID do resumo é obrigatório');
            }
            
            // Busca dados do professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            // Busca resumo e análise
            $resumo = $this->db->fetch(
                "SELECT jra.*, a.nome as nome_aluno, ja.nome_aula as nome_aula, m.nome as materia_nome
                 FROM jornadas_resumos_alunos jra
                 JOIN alunos a ON jra.aluno_id = a.id
                 LEFT JOIN jornadas_aulas ja ON jra.aula_id = ja.id
                 LEFT JOIN jornadas j ON ja.jornada_id = j.id
                 LEFT JOIN materias m ON j.materia_id = m.id
                 WHERE jra.id = :id AND j.professor_id = :prof_id",
                ['id' => $resumoId, 'prof_id' => $professor['id']]
            );
            
            if (!$resumo) {
                throw new Exception('Resumo não encontrado');
            }
            
            $analise = json_decode($resumo['analise_ia'] ?? '{}', true);
            $lacunasIdentificadas = $analise['lacunas_identificadas'] ?? [];
            
            if (empty($lacunasIdentificadas)) {
                throw new Exception('Nenhuma lacuna identificada para gerar explicação');
            }
            
            // Gera explicação usando IA
            require_once 'app/Services/OpenAIService.php';
            $openAI = new OpenAIService();
            
            $explicacao = $openAI->gerarExplicacaoComplementar(
                implode(', ', $lacunasIdentificadas),
                $resumo['materia_nome'] ?? 'Geral'
            );
            
            $this->json(['success' => true, 'explicacao' => $explicacao]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Testa conexão com OpenAI
     */
    public function testarOpenAI()
    {
        try {
            require_once 'app/Services/OpenAIService.php';
            $openAI = new OpenAIService();
            
            $resultado = $openAI->testarConexao();
            
            $this->json($resultado);
            
        } catch (Exception $e) {
            $this->json(['success' => false, 'mensagem' => $e->getMessage()], 400);
        }
    }

    /**
     * Formulário para editar jornada
     */
    public function editar($id)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca dados do professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            // Busca dados da jornada
            $jornada = $this->db->fetch(
                "SELECT j.*, t.nome as turma_nome, m.nome as materia_nome
                 FROM jornadas j
                 JOIN turmas t ON j.turma_id = t.id
                 LEFT JOIN materias m ON j.materia_id = m.id
                 WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
                [
                    'jornada_id' => $id,
                    'prof_id' => $professor['id']
                ]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            // Busca turmas do professor
            $turmas_professor = json_decode($professor['turmas'], true) ?: [];
            
            if (empty($turmas_professor)) {
                $turmas = [];
            } else {
                $placeholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
                $turmas = $this->db->fetchAll(
                    "SELECT * FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
                    $turmas_professor
                );
            }
            
            // Busca matérias do professor
            $materias_professor = json_decode($professor['materias'], true) ?: [];
            
            if (empty($materias_professor)) {
                $materias = [];
            } else {
                $placeholders = str_repeat('?,', count($materias_professor) - 1) . '?';
                $materias = $this->db->fetchAll(
                    "SELECT * FROM materias WHERE nome IN ($placeholders) ORDER BY nome ASC",
                    $materias_professor
                );
            }
            
            // Busca planos de aula do professor (apenas os vinculados a este professor e suas turmas)
            // Garante que apenas planos com professor_id correspondente ao professor logado sejam exibidos
            // E que as turmas dos planos estejam nas turmas do professor
            $planos_aula = [];
            if (!empty($turmas_professor) && is_array($turmas_professor)) {
                $turmaPlaceholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
                $params = array_merge([(int)$professor['id']], $turmas_professor);
                $planos_aula = $this->db->fetchAll(
                    "SELECT pa.*, t.nome as turma_nome
                     FROM planos_aula pa
                     INNER JOIN turmas t ON pa.turma_id = t.id
                     WHERE pa.professor_id = ?
                     AND pa.professor_id IS NOT NULL
                     AND pa.professor_id > 0
                     AND pa.turma_id IN ($turmaPlaceholders)
                     ORDER BY pa.created_at DESC",
                    $params
                );
            }
            
            // Extrai dados da estrutura JSON
            $estrutura = json_decode($jornada['estrutura'], true) ?: [];
            $jornada['data_inicio'] = $estrutura['data_inicio'] ?? '';
            $jornada['data_fim'] = $estrutura['data_fim'] ?? '';
            $jornada['hora_inicio'] = $estrutura['hora_inicio'] ?? '';
            $jornada['hora_fim'] = $estrutura['hora_fim'] ?? '';
            $jornada['objetivos'] = $estrutura['objetivos'] ?? '';
            $jornada['criterios_avaliacao'] = $estrutura['criterios_avaliacao'] ?? '';
            $jornada['planos_aula_selecionados'] = $estrutura['planos_aula_selecionados'] ?? [];
            
            $data = [
                'title' => 'Editar Jornada - EducaTudo',
                'jornada' => $jornada,
                'turmas' => $turmas,
                'materias' => $materias,
                'planos_aula' => $planos_aula,
                'user' => $user,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/editar', $data);
            
        } catch (Exception $e) {
            $this->redirectToCorrectDashboard($user['tipo'] ?? 'professor');
        }
    }
    
    /**
     * Atualiza jornada
     */
    public function atualizar($id)
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $user = $this->authManager->getUser();
            $titulo = trim($_POST['titulo'] ?? '');
            
            // Processa múltiplas turmas
            $turmasIds = $_POST['turmas_id'] ?? [];
            if (is_string($turmasIds)) {
                $turmasIds = [$turmasIds];
            }
            $turmaId = !empty($turmasIds) ? (int)$turmasIds[0] : ($_POST['turma_id'] ?? null);
            
            $materiaId = $_POST['materia_id'] ?? null;
            $anoLetivo = (int)($_POST['ano_letivo'] ?? 0);
            $bimestre = (int)($_POST['bimestre'] ?? 0);
            $avaliativo = 1;
            $objetivos = trim($_POST['objetivos'] ?? '');
            $dataInicio = $_POST['data_inicio'] ?? null;
            $dataFim = $_POST['data_fim'] ?? null;
            $horaInicio = $_POST['hora_inicio'] ?? null;
            $horaFim = $_POST['hora_fim'] ?? null;
            
            // Processa seleção de planos de aula (múltiplos)
            $planosAulaIds = $_POST['planos_aula_id'] ?? [];
            if (is_string($planosAulaIds)) {
                $planosAulaIds = [$planosAulaIds];
            }
            $planosAulaIds = array_map('intval', array_filter($planosAulaIds));
            
            // Primeiro plano é usado como plano_aula_id principal (para compatibilidade)
            $planoAulaId = !empty($planosAulaIds) ? $planosAulaIds[0] : null;
            
            // Processa seleção de alunos
            $tipoSelecaoAlunos = $_POST['tipo_selecao_alunos'] ?? 'todos';
            $alunosIds = [];
            if ($tipoSelecaoAlunos === 'especificos') {
                $alunosIds = $_POST['alunos_id'] ?? [];
                if (is_string($alunosIds)) {
                    $alunosIds = [$alunosIds];
                }
                $alunosIds = array_values(array_unique(array_map('intval', $alunosIds)));
            }
            
            // Validações
            if (empty($titulo) || empty($turmaId) || empty($turmasIds)) {
                throw new Exception('Título e pelo menos uma turma são obrigatórios');
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
            
            // Verifica se a jornada pertence ao professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :id AND professor_id = :prof_id",
                ['id' => $id, 'prof_id' => $professor['id']]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            // Verifica se as turmas pertencem ao professor
            $turmas_professor = json_decode($professor['turmas'], true) ?: [];
            foreach ($turmasIds as $tid) {
                if (!in_array($tid, $turmas_professor)) {
                    throw new Exception('Uma ou mais turmas não são autorizadas');
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
            
            // Verifica se os planos de aula pertencem ao professor (se fornecidos)
            if (!empty($planosAulaIds)) {
                foreach ($planosAulaIds as $pid) {
                    $planoAula = $this->db->fetch(
                        "SELECT id FROM planos_aula WHERE id = :id AND professor_id = :prof_id",
                        ['id' => $pid, 'prof_id' => $user['id']]
                    );
                    if (!$planoAula) {
                        throw new Exception('Um ou mais planos de aula não foram encontrados ou não são autorizados');
                    }
                }
            }
            
            // Atualiza jornada
            $this->db->update(
                "UPDATE jornadas 
                 SET turma_id = :turma_id, materia_id = :materia_id, titulo = :titulo, 
                     ano_letivo = :ano_letivo, bimestre = :bimestre, avaliativo = :avaliativo,
                     estrutura = :estrutura, plano_aula_id = :plano_aula_id, updated_at = NOW()
                 WHERE id = :id",
                [
                    'id' => $id,
                    'turma_id' => $turmaId,
                    'materia_id' => $materiaId,
                    'titulo' => $titulo,
                    'ano_letivo' => $anoLetivo,
                    'bimestre' => $bimestre,
                    'avaliativo' => $avaliativo,
                    'plano_aula_id' => $planoAulaId,
                    'estrutura' => json_encode([
                        'objetivos' => $objetivos,
                        'data_inicio' => $dataInicio,
                        'data_fim' => $dataFim,
                        'hora_inicio' => $horaInicio,
                        'hora_fim' => $horaFim,
                        'turmas_selecionadas' => array_map('intval', $turmasIds),
                        'alunos_selecionados' => $alunosIds,
                        'tipo_selecao_alunos' => $tipoSelecaoAlunos,
                        'planos_aula_selecionados' => $planosAulaIds
                    ])
                ]
            );
            
            $this->json(['success' => true, 'message' => 'Jornada atualizada com sucesso']);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Visualiza alunos da jornada
     */
    public function verAlunos($id)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca dados do professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            // Busca dados da jornada
            $jornada = $this->db->fetch(
                "SELECT j.*, t.nome as turma_nome, m.nome as materia_nome
                 FROM jornadas j
                 JOIN turmas t ON j.turma_id = t.id
                 LEFT JOIN materias m ON j.materia_id = m.id
                 WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
                [
                    'jornada_id' => $id,
                    'prof_id' => $professor['id']
                ]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            // Busca alunos da turma (com tempo total da jornada quando concluída)
            $alunos = $this->db->fetchAll(
                "SELECT a.*, 
                        COUNT(DISTINCT jpa.id) as atividades_concluidas,
                        COUNT(DISTINCT jra.id) as resumos_enviados,
                        COUNT(DISTINCT jd.id) as duvidas_enviadas,
                        MAX(jta.tempo_total_segundos) as tempo_total_segundos,
                        MAX(jta.data_inicio) as jornada_data_inicio,
                        MAX(jta.data_fim) as jornada_data_fim
                 FROM alunos a
                 LEFT JOIN jornadas_progresso_alunos jpa ON jpa.aluno_id = a.id AND jpa.jornada_id = :jornada_id AND jpa.status = 'concluido'
                 LEFT JOIN jornadas_resumos_alunos jra ON jra.aluno_id = a.id AND jra.jornada_id = :jornada_id
                 LEFT JOIN jornadas_duvidas jd ON jd.aluno_id = a.id 
                 LEFT JOIN jornadas_aulas ja2 ON jd.aula_id = ja2.id AND ja2.jornada_id = :jornada_id
                 LEFT JOIN jornadas_tempo_alunos jta ON jta.aluno_id = a.id AND jta.jornada_id = :jornada_id
                 WHERE a.turma_id = :turma_id
                 GROUP BY a.id
                 ORDER BY a.nome ASC",
                [
                    'jornada_id' => $id,
                    'turma_id' => $jornada['turma_id']
                ]
            );
            
            $data = [
                'title' => 'Alunos da Jornada - EducaTudo',
                'jornada' => $jornada,
                'alunos' => $alunos,
                'user' => $user,
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/alunos', $data);
            
        } catch (Exception $e) {
            $this->redirectToCorrectDashboard($user['tipo'] ?? 'professor');
        }
    }
    
    /**
     * Alterna status da jornada
     */
    public function toggleStatus()
    {
        try {
            $user = $this->authManager->getUser();
            
            // Verifica se é POST JSON ou POST form
            $input = null;
            if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }
            
            // Verifica CSRF token
            if (!$this->verifyCsrfToken($input['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
            }
            
            $jornadaId = $input['jornada_id'] ?? null;
            $novoStatus = $input['status'] ?? null;
            
            if (!$jornadaId || !$novoStatus) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }
            
            // Valida status permitidos
            $statusPermitidos = ['aguardando', 'em_andamento', 'concluido'];
            if (!in_array($novoStatus, $statusPermitidos)) {
                throw new Exception('Status inválido. Apenas: aguardando, em_andamento ou concluido');
            }
            
            // Verifica se a jornada pertence ao professor
            $professor = $this->db->fetch(
                "SELECT p.* FROM professores p WHERE p.id = :user_id",
                ['user_id' => $user['id']]
            );
            
            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :id AND professor_id = :prof_id",
                ['id' => $jornadaId, 'prof_id' => $professor['id']]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            // Verifica módulos sem conteúdo ao ativar
            $warnings = [];
            if ($novoStatus === 'em_andamento') {
                $modulos = $this->db->fetchAll(
                    "SELECT id, titulo, tipo_modulo FROM jornadas_modulos WHERE jornada_id = :jid AND status = 'ativo' ORDER BY ordem ASC",
                    ['jid' => $jornadaId]
                );
                foreach ($modulos as $mod) {
                    $vazio = false;
                    if (in_array($mod['tipo_modulo'], ['video', 'conteudo', 'dica_professor'])) {
                        $tv = $this->db->fetch("SELECT COUNT(*) as t FROM jornadas_modulos_videos WHERE modulo_id = :m AND status = 'ativo'", ['m' => $mod['id']]);
                        $td = $this->db->fetch("SELECT COUNT(*) as t FROM jornadas_modulos_documentos WHERE modulo_id = :m AND status = 'ativo'", ['m' => $mod['id']]);
                        $tt = $this->db->fetch("SELECT COUNT(*) as t FROM jornadas_modulos_textos WHERE modulo_id = :m", ['m' => $mod['id']]);
                        if ((int)$tv['t'] === 0 && (int)$td['t'] === 0 && (int)$tt['t'] === 0) {
                            $vazio = true;
                        }
                    } elseif (in_array($mod['tipo_modulo'], ['exercicios', 'exercicio'])) {
                        $te = $this->db->fetch("SELECT COUNT(*) as t FROM jornadas_modulos_exercicios WHERE modulo_id = :m AND status = 'publicado'", ['m' => $mod['id']]);
                        if ((int)$te['t'] === 0) {
                            $vazio = true;
                        }
                    }
                    if ($vazio) {
                        $warnings[] = "O módulo \"{$mod['titulo']}\" não possui conteúdo. Os alunos poderão pular esta etapa.";
                    }
                }
            }

            // Atualiza status
            $this->db->update(
                "UPDATE jornadas SET status = :status, updated_at = NOW() WHERE id = :id",
                ['status' => $novoStatus, 'id' => $jornadaId]
            );
            
            $response = ['success' => true, 'message' => 'Status atualizado com sucesso'];
            if (!empty($warnings)) {
                $response['warnings'] = $warnings;
            }
            $this->json($response);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Inativa jornada (soft delete: oculta da listagem do professor, admin e aluno).
     * Permite inativar mesmo que a jornada já tenha sido iniciada. Exige senha do professor para confirmar.
     */
    public function inativarJornada()
    {
        try {
            $user = $this->authManager->getUser();
            if (!$user) {
                $this->json(['error' => 'Não autenticado'], 403);
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
            $professor = $this->db->fetch(
                "SELECT id, senha_hash FROM professores WHERE id = :user_id",
                ['user_id' => $user['id']]
            );
            if (!$professor) {
                $this->json(['error' => 'Professor não encontrado'], 403);
                return;
            }
            if (!password_verify($senha, $professor['senha_hash'] ?? '')) {
                $this->json(['error' => 'Senha incorreta.'], 400);
                return;
            }
            $jornada = $this->db->fetch(
                "SELECT id FROM jornadas WHERE id = :id AND professor_id = :prof_id",
                ['id' => $jornadaId, 'prof_id' => $professor['id']]
            );
            if (!$jornada) {
                $this->json(['error' => 'Jornada não encontrada'], 404);
                return;
            }
            $rows = $this->db->update(
                "UPDATE jornadas SET ativo = 0, updated_at = NOW() WHERE id = :id AND professor_id = :prof_id",
                ['id' => $jornadaId, 'prof_id' => $professor['id']]
            );
            if ($rows === 0) {
                $this->json(['error' => 'Não foi possível inativar a jornada. Verifique se a coluna ativo existe na tabela jornadas (rode a migração 20260309_000001_jornadas_ativo.sql).'], 400);
                return;
            }
            $this->json(['success' => true, 'message' => 'Jornada inativada. Ela saiu da sua lista, do admin e do aluno.']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Duplica uma jornada (cópia completa: estrutura, módulos, exercícios, vídeos, documentos, textos e temas de redação).
     * A nova jornada é criada com status "aguardando" e título "Cópia de [título original]".
     */
    public function duplicarJornada()
    {
        try {
            $user = $this->authManager->getUser();
            if (!$user) {
                $this->json(['error' => 'Não autenticado'], 403);
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

            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :id AND professor_id = :prof_id",
                ['id' => $jornadaId, 'prof_id' => $user['id']]
            );
            if (!$jornada) {
                $this->json(['error' => 'Jornada não encontrada'], 404);
                return;
            }

            $this->db->beginTransaction();

            $novoTitulo = 'Cópia de ' . $jornada['titulo'];
            $novoJornadaId = $this->db->insert(
                "INSERT INTO jornadas (professor_id, turma_id, materia_id, titulo, descricao, estrutura, status, plano_aula_id, ativo, created_at, updated_at) 
                 VALUES (:prof_id, :turma_id, :materia_id, :titulo, :descricao, :estrutura, 'aguardando', :plano_aula_id, 1, NOW(), NOW())",
                [
                    'prof_id' => $jornada['professor_id'],
                    'turma_id' => $jornada['turma_id'],
                    'materia_id' => $jornada['materia_id'] ?? null,
                    'titulo' => $novoTitulo,
                    'descricao' => $jornada['descricao'] ?? null,
                    'estrutura' => $jornada['estrutura'],
                    'plano_aula_id' => $jornada['plano_aula_id'] ?? null
                ]
            );

            $modulos = $this->db->fetchAll(
                "SELECT * FROM jornadas_modulos WHERE jornada_id = :jornada_id ORDER BY ordem ASC, id ASC",
                ['jornada_id' => $jornadaId]
            );
            $mapModuloId = [];
            foreach ($modulos as $mod) {
                $novoModId = $this->db->insert(
                    "INSERT INTO jornadas_modulos (jornada_id, aula_id, tipo_modulo, titulo, descricao, ordem, obrigatorio, status, created_at) 
                     VALUES (:jornada_id, :aula_id, :tipo_modulo, :titulo, :descricao, :ordem, :obrigatorio, 'ativo', NOW())",
                    [
                        'jornada_id' => $novoJornadaId,
                        'aula_id' => $mod['aula_id'],
                        'tipo_modulo' => $mod['tipo_modulo'],
                        'titulo' => $mod['titulo'],
                        'descricao' => $mod['descricao'] ?? null,
                        'ordem' => (int)$mod['ordem'],
                        'obrigatorio' => (int)($mod['obrigatorio'] ?? 0)
                    ]
                );
                $mapModuloId[(int)$mod['id']] = $novoModId;
            }

            foreach ($mapModuloId as $oldModId => $newModId) {
                $exercicios = $this->db->fetchAll(
                    "SELECT tipo, titulo, enunciado, questoes_json, resposta_correta, gabarito, pontuacao, ordem, gerado_ia, status, imagem_url, nivel_dificuldade 
                     FROM jornadas_modulos_exercicios WHERE modulo_id = :modulo_id",
                    ['modulo_id' => $oldModId]
                );
                foreach ($exercicios as $ex) {
                    $this->db->insert(
                        "INSERT INTO jornadas_modulos_exercicios (modulo_id, tipo, titulo, enunciado, questoes_json, resposta_correta, gabarito, pontuacao, ordem, gerado_ia, status, imagem_url, nivel_dificuldade) 
                         VALUES (:modulo_id, :tipo, :titulo, :enunciado, :questoes_json, :resposta_correta, :gabarito, :pontuacao, :ordem, :gerado_ia, 'publicado', :imagem_url, :nivel_dificuldade)",
                        [
                            'modulo_id' => $newModId,
                            'tipo' => $ex['tipo'],
                            'titulo' => $ex['titulo'],
                            'enunciado' => $ex['enunciado'] ?? null,
                            'questoes_json' => $ex['questoes_json'],
                            'resposta_correta' => $ex['resposta_correta'] ?? null,
                            'gabarito' => $ex['gabarito'] ?? null,
                            'pontuacao' => (int)($ex['pontuacao'] ?? 0),
                            'ordem' => (int)($ex['ordem'] ?? 0),
                            'gerado_ia' => (int)($ex['gerado_ia'] ?? 0),
                            'imagem_url' => $ex['imagem_url'] ?? null,
                            'nivel_dificuldade' => $ex['nivel_dificuldade'] ?? null
                        ]
                    );
                }

                $videos = $this->db->fetchAll("SELECT tipo, titulo, descricao, url_youtube, arquivo_video, arquivo_nome, arquivo_tamanho, ordem FROM jornadas_modulos_videos WHERE modulo_id = :modulo_id", ['modulo_id' => $oldModId]);
                foreach ($videos as $v) {
                    $this->db->insert(
                        "INSERT INTO jornadas_modulos_videos (modulo_id, tipo, titulo, descricao, url_youtube, arquivo_video, arquivo_nome, arquivo_tamanho, ordem) 
                         VALUES (:modulo_id, :tipo, :titulo, :descricao, :url_youtube, :arquivo_video, :arquivo_nome, :arquivo_tamanho, :ordem)",
                        [
                            'modulo_id' => $newModId,
                            'tipo' => $v['tipo'] ?? 'youtube',
                            'titulo' => $v['titulo'] ?? '',
                            'descricao' => $v['descricao'] ?? null,
                            'url_youtube' => $v['url_youtube'] ?? null,
                            'arquivo_video' => $v['arquivo_video'] ?? null,
                            'arquivo_nome' => $v['arquivo_nome'] ?? null,
                            'arquivo_tamanho' => $v['arquivo_tamanho'] ?? null,
                            'ordem' => (int)($v['ordem'] ?? 0)
                        ]
                    );
                }

                $docs = $this->db->fetchAll("SELECT titulo, descricao, arquivo, arquivo_nome, arquivo_tamanho, tipo_arquivo, ordem FROM jornadas_modulos_documentos WHERE modulo_id = :modulo_id", ['modulo_id' => $oldModId]);
                foreach ($docs as $d) {
                    $this->db->insert(
                        "INSERT INTO jornadas_modulos_documentos (modulo_id, titulo, descricao, arquivo, arquivo_nome, arquivo_tamanho, tipo_arquivo, ordem) 
                         VALUES (:modulo_id, :titulo, :descricao, :arquivo, :arquivo_nome, :arquivo_tamanho, :tipo_arquivo, :ordem)",
                        [
                            'modulo_id' => $newModId,
                            'titulo' => $d['titulo'] ?? '',
                            'descricao' => $d['descricao'] ?? null,
                            'arquivo' => $d['arquivo'] ?? null,
                            'arquivo_nome' => $d['arquivo_nome'] ?? null,
                            'arquivo_tamanho' => $d['arquivo_tamanho'] ?? null,
                            'tipo_arquivo' => $d['tipo_arquivo'] ?? null,
                            'ordem' => (int)($d['ordem'] ?? 0)
                        ]
                    );
                }

                $textos = $this->db->fetchAll("SELECT titulo, conteudo, ordem FROM jornadas_modulos_textos WHERE modulo_id = :modulo_id", ['modulo_id' => $oldModId]);
                foreach ($textos as $t) {
                    $this->db->insert(
                        "INSERT INTO jornadas_modulos_textos (modulo_id, titulo, conteudo, ordem) VALUES (:modulo_id, :titulo, :conteudo, :ordem)",
                        [
                            'modulo_id' => $newModId,
                            'titulo' => $t['titulo'] ?? '',
                            'conteudo' => $t['conteudo'] ?? '',
                            'ordem' => (int)($t['ordem'] ?? 0)
                        ]
                    );
                }
            }

            $redacoes = $this->db->fetchAll(
                "SELECT professor_id, tema_sugerido, descricao_tema, imagem_tema, documento_tema, correcao_ia_automatica 
                 FROM jornadas_redacoes WHERE jornada_id = :jornada_id",
                ['jornada_id' => $jornadaId]
            );
            foreach ($redacoes as $r) {
                $campos = ['jornada_id', 'professor_id', 'tema_sugerido', 'descricao_tema', 'imagem_tema', 'correcao_ia_automatica', 'status', 'created_at'];
                $vals = [':jornada_id', ':professor_id', ':tema', ':descricao', ':imagem_tema', ':correcao_ia', "'pendente'", 'NOW()'];
                $params = [
                    'jornada_id' => $novoJornadaId,
                    'professor_id' => $r['professor_id'],
                    'tema' => $r['tema_sugerido'] ?? '',
                    'descricao' => $r['descricao_tema'] ?? null,
                    'imagem_tema' => $r['imagem_tema'] ?? null,
                    'correcao_ia' => (int)($r['correcao_ia_automatica'] ?? 0)
                ];
                if (!empty($r['documento_tema'])) {
                    $campos[] = 'documento_tema';
                    $vals[] = ':documento_tema';
                    $params['documento_tema'] = $r['documento_tema'];
                }
                $this->db->insert(
                    "INSERT INTO jornadas_redacoes (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $vals) . ")",
                    $params
                );
            }

            $this->db->commit();
            $this->json(['success' => true, 'message' => 'Jornada duplicada com sucesso.', 'jornada_id' => $novoJornadaId]);

        } catch (Exception $e) {
            if (isset($this->db) && method_exists($this->db, 'rollback')) {
                $this->db->rollback();
            }
            $this->json(['error' => 'Erro ao duplicar: ' . $e->getMessage()], 400);
        }
    }

    /**
     * ============================================
     * SISTEMA DE REDAÇÕES NA JORNADA
     * ============================================
     */
    
    /**
     * Sugerir tema de redação para a jornada
     */
    public function sugerirTemaRedacao()
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }
            
            $jornadaId = $_POST['jornada_id'] ?? null;
            $aulaId = $_POST['aula_id'] ?? null;
            $tema = trim($_POST['tema'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $correcaoIAAutomatica = isset($_POST['correcao_ia_automatica']) ? intval($_POST['correcao_ia_automatica']) : 0;
            
            if (!$jornadaId || empty($tema)) {
                $this->json(['error' => 'Jornada e tema são obrigatórios'], 400);
                return;
            }
            
            // Verificar se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :id AND professor_id = :prof_id",
                ['id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                $this->json(['error' => 'Jornada não encontrada'], 404);
                return;
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
                    $this->json(['error' => 'Formato de imagem inválido. Use: JPG, PNG, GIF ou WEBP'], 400);
                    return;
                }
                
                // Limitar tamanho a 5MB
                if ($file['size'] > 5 * 1024 * 1024) {
                    $this->json(['error' => 'Imagem muito grande. Tamanho máximo: 5MB'], 400);
                    return;
                }
                
                $fileName = uniqid() . '_' . time() . '.' . $ext;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $imagemTema = $filePath;
                } else {
                    $this->json(['error' => 'Erro ao fazer upload da imagem'], 500);
                    return;
                }
            }
            
            // Criar redação da jornada
            $jornadaRedacaoId = $this->db->insert(
                "INSERT INTO jornadas_redacoes 
                 (jornada_id, aula_id, professor_id, tema_sugerido, descricao_tema, imagem_tema, correcao_ia_automatica, status, created_at)
                 VALUES (:jornada_id, :aula_id, :professor_id, :tema, :descricao, :imagem_tema, :correcao_ia_automatica, 'pendente', NOW())",
                [
                    'jornada_id' => $jornadaId,
                    'aula_id' => $aulaId ?: null,
                    'professor_id' => $user['id'],
                    'tema' => $tema,
                    'descricao' => $descricao ?: null,
                    'imagem_tema' => $imagemTema,
                    'correcao_ia_automatica' => $correcaoIAAutomatica
                ]
            );
            
            $this->json([
                'success' => true,
                'message' => 'Tema sugerido com sucesso',
                'jornada_redacao_id' => $jornadaRedacaoId
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Listar redações da jornada
     */
    public function listarRedacoesJornada($jornadaId)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Verificar se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT j.*, m.nome as materia_nome, t.nome as turma_nome
                 FROM jornadas j
                 LEFT JOIN materias m ON j.materia_id = m.id
                 LEFT JOIN turmas t ON j.turma_id = t.id
                 WHERE j.id = :id AND j.professor_id = :prof_id",
                ['id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                $_SESSION['error_message'] = 'Jornada não encontrada';
                $this->redirect('/teacher/jornadas');
                return;
            }
            
            // Buscar TODAS as redações dos alunos na jornada
            // Mostrar todas as versões (incluindo rascunhos de novas versões)
            // Se houver múltiplos vínculos para a mesma redação, pegar o mais recente (MAX(jra.id))
            // Mostrar apenas uma entrada por versão do aluno
            $redacoesAlunos = $this->db->fetchAll(
                "SELECT 
                    jra.id as jra_id,
                    jra.jornada_redacao_id,
                    jra.redacao_id,
                    jra.aluno_id,
                    jra.status,
                    jra.versao,
                    jra.correcao_professor_feita,
                    jra.usar_media_notas,
                    jra.nota_media,
                    jra.nota_final_utilizada,
                    jra.created_at as jra_created_at,
                    jra.updated_at as jra_updated_at,
                    r.*,
                    a.nome as aluno_nome,
                    a.ra as aluno_ra,
                    jr_tema.tema_sugerido,
                    jr_tema.descricao_tema
                 FROM (
                     SELECT jra2.aluno_id, jra2.versao, MAX(jra2.updated_at) as max_updated_at, MAX(jra2.id) as max_jra_id
                     FROM jornadas_redacoes_alunos jra2
                     INNER JOIN jornadas_redacoes jr2 ON jra2.jornada_redacao_id = jr2.id
                     WHERE jr2.jornada_id = :jornada_id
                     GROUP BY jra2.aluno_id, jra2.versao
                 ) as versoes_aluno
                 INNER JOIN jornadas_redacoes_alunos jra ON jra.id = versoes_aluno.max_jra_id
                 INNER JOIN redacoes r ON jra.redacao_id = r.id
                 INNER JOIN jornadas_redacoes jr_tema ON jra.jornada_redacao_id = jr_tema.id
                 INNER JOIN alunos a ON jra.aluno_id = a.id
                 WHERE jr_tema.jornada_id = :jornada_id_2
                 ORDER BY a.nome ASC, jra.versao ASC, r.created_at DESC",
                [
                    'jornada_id' => $jornadaId,
                    'jornada_id_2' => $jornadaId
                ]
            );
            
            // Agrupar redações por tema para exibição (mas cada aluno aparece apenas uma vez)
            $redacoesJornada = $this->db->fetchAll(
                "SELECT jr.*, ja.nome_aula
                 FROM jornadas_redacoes jr
                 LEFT JOIN jornadas_aulas ja ON jr.aula_id = ja.id
                 WHERE jr.jornada_id = :jornada_id
                 ORDER BY jr.created_at DESC",
                ['jornada_id' => $jornadaId]
            );
            
            foreach ($redacoesJornada as &$redacaoJornada) {
                $jornadaRedacaoId = $redacaoJornada['id'];
                // Filtrar apenas as redações que pertencem a este tema
                $redacoesFiltradas = array_filter($redacoesAlunos, function($redacao) use ($jornadaRedacaoId) {
                    return $redacao['jornada_redacao_id'] == $jornadaRedacaoId;
                });
                $redacaoJornada['redacoes_alunos'] = array_values($redacoesFiltradas);
            }
            
            $data = [
                'title' => 'Redações da Jornada - EducaTudo',
                'jornada' => $jornada,
                'redacoes_jornada' => $redacoesJornada,
                'user' => $user,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/redacoes', $data);
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao carregar redações: ' . $e->getMessage();
            $this->redirect('/teacher/jornadas');
        }
    }
    
    /**
     * Corrigir redação manualmente (professor)
     */
    public function corrigirRedacaoJornada()
    {
        $logFile = __DIR__ . '/../../storage/logs/correcao_redacao.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $log = function($message) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
        };
        
        try {
            $log("=== INÍCIO CORREÇÃO REDAÇÃO ===");
            $user = $this->authManager->getUser();
            $log("Usuário: " . ($user['id'] ?? 'N/A') . " - " . ($user['nome'] ?? 'N/A'));
            
            // Aceitar dados de POST normal ou JSON
            $input = $_POST;
            $log("POST recebido: " . json_encode($input));
            
            if (empty($input) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
                $jsonInput = json_decode(file_get_contents('php://input'), true);
                if ($jsonInput) {
                    $input = $jsonInput;
                    $log("JSON recebido: " . json_encode($jsonInput));
                }
            }
            
            if (!$this->verifyCsrfToken($input['_token'] ?? '')) {
                $log("ERRO: Token CSRF inválido");
                $_SESSION['error_message'] = 'Token inválido';
                $this->redirect('/teacher/jornadas');
                return;
            }
            $log("Token CSRF válido");
            
            $redacaoId = $input['redacao_id'] ?? null;
            $competencia1 = (int)($input['competencia_1'] ?? 0);
            $competencia2 = (int)($input['competencia_2'] ?? 0);
            $competencia3 = (int)($input['competencia_3'] ?? 0);
            $competencia4 = (int)($input['competencia_4'] ?? 0);
            $competencia5 = (int)($input['competencia_5'] ?? 0);
            
            // Verificar se deve usar média com a nota da IA
            $usarMedia = isset($input['usar_media_notas']) && $input['usar_media_notas'] == '1';
            $explicacao1 = trim($input['explicacao_1'] ?? '');
            $explicacao2 = trim($input['explicacao_2'] ?? '');
            $explicacao3 = trim($input['explicacao_3'] ?? '');
            $explicacao4 = trim($input['explicacao_4'] ?? '');
            $explicacao5 = trim($input['explicacao_5'] ?? '');
            $comentariosGerais = trim($input['comentarios_gerais'] ?? '');
            $sugestoesMelhoria = trim($input['sugestoes_melhoria'] ?? '');
            $permitirRefazer = isset($input['permitir_refazer']) && $input['permitir_refazer'] == '1';
            
            $log("Redação ID: {$redacaoId}");
            $log("Competências: C1={$competencia1}, C2={$competencia2}, C3={$competencia3}, C4={$competencia4}, C5={$competencia5}");
            
            // Checkboxes para mostrar competências ao aluno
            $mostrarComp1 = isset($input['mostrar_competencia_1_aluno']) && $input['mostrar_competencia_1_aluno'] == '1';
            $mostrarComp2 = isset($input['mostrar_competencia_2_aluno']) && $input['mostrar_competencia_2_aluno'] == '1';
            $mostrarComp3 = isset($input['mostrar_competencia_3_aluno']) && $input['mostrar_competencia_3_aluno'] == '1';
            $mostrarComp4 = isset($input['mostrar_competencia_4_aluno']) && $input['mostrar_competencia_4_aluno'] == '1';
            $mostrarComp5 = isset($input['mostrar_competencia_5_aluno']) && $input['mostrar_competencia_5_aluno'] == '1';
            
            $log("Mostrar ao aluno: C1=" . ($mostrarComp1 ? 'SIM' : 'NÃO') . ", C2=" . ($mostrarComp2 ? 'SIM' : 'NÃO') . ", C3=" . ($mostrarComp3 ? 'SIM' : 'NÃO') . ", C4=" . ($mostrarComp4 ? 'SIM' : 'NÃO') . ", C5=" . ($mostrarComp5 ? 'SIM' : 'NÃO'));
            
            if (!$redacaoId) {
                $log("ERRO: ID da redação é obrigatório");
                $_SESSION['error_message'] = 'ID da redação é obrigatório';
                $this->redirect('/teacher/jornadas');
                return;
            }
            
            // Verificar se a redação pertence a uma jornada do professor
            $log("Buscando redação no banco...");
            $log("Parâmetros da busca: redacao_id={$redacaoId}, prof_id={$user['id']}");
            
            // Primeiro, verificar se existe na tabela jornadas_redacoes_alunos
            $jraCheck = $this->db->fetch(
                "SELECT * FROM jornadas_redacoes_alunos WHERE redacao_id = :redacao_id LIMIT 1",
                ['redacao_id' => $redacaoId]
            );
            $log("Verificação jornadas_redacoes_alunos: " . ($jraCheck ? "ENCONTRADO" : "NÃO ENCONTRADO"));
            if ($jraCheck) {
                $log("Dados jra: " . json_encode($jraCheck));
            }
            
            // Buscar redação com todas as relações
            $redacao = $this->db->fetch(
                "SELECT r.*, j.professor_id, j.id as jornada_id
                 FROM redacoes r
                 INNER JOIN jornadas_redacoes_alunos jra ON r.id = jra.redacao_id
                 INNER JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                 INNER JOIN jornadas j ON jr.jornada_id = j.id
                 WHERE r.id = :redacao_id AND j.professor_id = :prof_id
                 LIMIT 1",
                ['redacao_id' => $redacaoId, 'prof_id' => $user['id']]
            );
            
            if (!$redacao) {
                $log("ERRO: Redação não encontrada ou não pertence ao professor");
                
                // Se não existe vínculo, buscar pela jornada_id que pode estar na URL ou buscar todas as jornadas do professor
                $log("Tentando buscar jornada através de outras formas...");
                
                // Verificar se há jornada_id no POST
                $jornadaIdAlternativo = $input['jornada_id'] ?? null;
                
                // Se não tem no POST, buscar todas as jornadas do professor que podem ter essa redação
                if (!$jornadaIdAlternativo) {
                    $log("Buscando todas as jornadas do professor que podem ter essa redação...");
                    $jornadasProfessor = $this->db->fetchAll(
                        "SELECT j.id 
                         FROM jornadas j 
                         WHERE j.professor_id = :prof_id",
                        ['prof_id' => $user['id']]
                    );
                    $log("Jornadas do professor: " . json_encode($jornadasProfessor));
                    
                    // Tentar encontrar a jornada através da redação (pode ter jornada_id na tabela redacoes)
                    $redacaoSimples = $this->db->fetch(
                        "SELECT * FROM redacoes WHERE id = :redacao_id",
                        ['redacao_id' => $redacaoId]
                    );
                    if ($redacaoSimples && isset($redacaoSimples['jornada_id'])) {
                        $jornadaIdAlternativo = $redacaoSimples['jornada_id'];
                        $log("Jornada ID encontrado na tabela redacoes: {$jornadaIdAlternativo}");
                    }
                }
                
                if ($jornadaIdAlternativo) {
                    $log("Jornada ID encontrado: {$jornadaIdAlternativo}");
                    $jornadaCheck = $this->db->fetch(
                        "SELECT * FROM jornadas WHERE id = :id AND professor_id = :prof_id",
                        ['id' => $jornadaIdAlternativo, 'prof_id' => $user['id']]
                    );
                    if ($jornadaCheck) {
                        $log("Jornada encontrada! Permitindo salvar correção mesmo sem vínculo direto.");
                        // Continuar com a correção usando a jornada encontrada
                        $redacao = $this->db->fetch(
                            "SELECT * FROM redacoes WHERE id = :redacao_id",
                            ['redacao_id' => $redacaoId]
                        );
                        $redacao['professor_id'] = $user['id'];
                        $redacao['jornada_id'] = $jornadaIdAlternativo;
                    } else {
                        $log("ERRO: Jornada não pertence ao professor");
                        $_SESSION['error_message'] = 'Redação não encontrada ou não pertence ao professor';
                        $this->redirect('/teacher/jornadas');
                        return;
                    }
                } else {
                    $log("ERRO: Não foi possível encontrar a jornada associada");
                    $_SESSION['error_message'] = 'Redação não encontrada ou não pertence ao professor. A redação não está vinculada a nenhuma jornada.';
                    $this->redirect('/teacher/jornadas');
                    return;
                }
            }
            
            $log("Redação encontrada: ID={$redacao['id']}, Nota atual={$redacao['nota_final']}, Jornada ID={$redacao['jornada_id']}, Professor ID={$redacao['professor_id']}");
            
            $notaFinalProfessor = $competencia1 + $competencia2 + $competencia3 + $competencia4 + $competencia5;
            $log("Nota final do professor calculada: {$notaFinalProfessor}");
            
            // Calcular nota da IA se existir feedback_ia
            $notaIAFinal = 0;
            $notaFinalUtilizada = $notaFinalProfessor;
            $notaMedia = null;
            
            if (!empty($redacao['feedback_ia'])) {
                $feedbackIA = json_decode($redacao['feedback_ia'], true);
                if ($feedbackIA) {
                    for ($i = 1; $i <= 5; $i++) {
                        if (isset($feedbackIA["competencia_{$i}"]['nota'])) {
                            $notaIAFinal += (int)$feedbackIA["competencia_{$i}"]['nota'];
                        }
                    }
                    $log("Nota final da IA calculada: {$notaIAFinal}");
                    
                    // Se deve usar média, calcular
                    if ($usarMedia && $notaIAFinal > 0) {
                        $notaMedia = round(($notaFinalProfessor + $notaIAFinal) / 2);
                        $notaFinalUtilizada = $notaMedia;
                        $log("Média calculada: ({$notaFinalProfessor} + {$notaIAFinal}) / 2 = {$notaMedia}");
                    }
                }
            }
            
            // Atualizar correção do professor (pode ser edição da IA ou correção própria)
            // Salvar nas colunas principais (competencia_X) e nas _professor para explicações
            $log("Preparando UPDATE no banco de dados...");
            $updateParams = [
                'comp1' => $competencia1,
                'exp1' => $explicacao1,
                'comp2' => $competencia2,
                'exp2' => $explicacao2,
                'comp3' => $competencia3,
                'exp3' => $explicacao3,
                'comp4' => $competencia4,
                'exp4' => $explicacao4,
                'comp5' => $competencia5,
                'exp5' => $explicacao5,
                'nota_final' => $notaFinalUtilizada,
                'nota_final_professor' => $notaFinalProfessor,
                'comentarios' => $comentariosGerais,
                'sugestoes' => $sugestoesMelhoria,
                'prof_id' => $user['id'],
                'usar_media' => $usarMedia ? 1 : 0,
                'nota_media' => $notaMedia,
                'nota_final_utilizada' => $notaFinalUtilizada,
                'permitir_refazer' => $permitirRefazer ? 1 : 0,
                'mostrar_comp1' => $mostrarComp1 ? 1 : 0,
                'mostrar_comp2' => $mostrarComp2 ? 1 : 0,
                'mostrar_comp3' => $mostrarComp3 ? 1 : 0,
                'mostrar_comp4' => $mostrarComp4 ? 1 : 0,
                'mostrar_comp5' => $mostrarComp5 ? 1 : 0,
                'redacao_id' => $redacaoId
            ];
            $log("Parâmetros do UPDATE: " . json_encode($updateParams));
            
            try {
                $rowsAffected = $this->db->update(
                    "UPDATE redacoes SET
                        competencia_1 = :comp1,
                        competencia_1_explicacao_professor = :exp1,
                        competencia_2 = :comp2,
                        competencia_2_explicacao_professor = :exp2,
                        competencia_3 = :comp3,
                        competencia_3_explicacao_professor = :exp3,
                        competencia_4 = :comp4,
                        competencia_4_explicacao_professor = :exp4,
                        competencia_5 = :comp5,
                        competencia_5_explicacao_professor = :exp5,
                        nota_final = :nota_final,
                        nota_final_professor = :nota_final_professor,
                        comentarios_gerais_professor = :comentarios,
                        sugestoes_melhoria_professor = :sugestoes,
                        corrigida_por_professor = :prof_id,
                        corrigida_em_professor = NOW(),
                        usar_media_notas = :usar_media,
                        nota_media = :nota_media,
                        nota_final_utilizada = :nota_final_utilizada,
                        permitir_refazer = :permitir_refazer,
                        mostrar_competencia_1_aluno = :mostrar_comp1,
                        mostrar_competencia_2_aluno = :mostrar_comp2,
                        mostrar_competencia_3_aluno = :mostrar_comp3,
                        mostrar_competencia_4_aluno = :mostrar_comp4,
                        mostrar_competencia_5_aluno = :mostrar_comp5
                     WHERE id = :redacao_id",
                    $updateParams
                );
                
                $log("UPDATE executado. Linhas afetadas: {$rowsAffected}");
                
                if ($rowsAffected === 0) {
                    $log("ERRO: Nenhuma linha foi afetada pelo UPDATE. A redação pode não existir ou o ID está incorreto.");
                    throw new Exception("Falha ao salvar correção: nenhuma linha foi atualizada no banco de dados.");
                }
                
                // Verificar se os dados foram realmente salvos
                $redacaoVerificacao = $this->db->fetch(
                    "SELECT competencia_1, competencia_1_explicacao_professor, nota_final, nota_final_professor 
                     FROM redacoes 
                     WHERE id = :redacao_id",
                    ['redacao_id' => $redacaoId]
                );
                
                if ($redacaoVerificacao) {
                    $log("Verificação pós-UPDATE - C1: " . ($redacaoVerificacao['competencia_1'] ?? 'NULL') . 
                         ", Nota Final: " . ($redacaoVerificacao['nota_final'] ?? 'NULL') . 
                         ", Nota Final Professor: " . ($redacaoVerificacao['nota_final_professor'] ?? 'NULL'));
                } else {
                    $log("ERRO: Não foi possível verificar os dados salvos");
                }
                
                error_log("Correção salva - Redação ID: {$redacaoId}, Nota Final: {$notaFinalUtilizada}, Resultado: " . ($updateResult ? 'sucesso' : 'falha'));
            } catch (Exception $updateException) {
                $log("ERRO ao executar UPDATE: " . $updateException->getMessage());
                $log("Stack trace: " . $updateException->getTraceAsString());
                error_log("Erro ao atualizar redação: " . $updateException->getMessage());
                throw $updateException;
            }
            
            // Atualizar status na tabela de vínculo
            $log("Atualizando status na tabela jornadas_redacoes_alunos...");
            try {
                // Buscar dados necessários para verificar/criar vínculo
                $jornadaIdParaVinculo = $redacao['jornada_id'] ?? null;
                $alunoRedacao = $this->db->fetch(
                    "SELECT aluno_id FROM redacoes WHERE id = :redacao_id",
                    ['redacao_id' => $redacaoId]
                );
                
                if ($jornadaIdParaVinculo && $alunoRedacao && $alunoRedacao['aluno_id']) {
                    // Buscar jornada_redacao da jornada
                    $jornadaRedacao = $this->db->fetch(
                        "SELECT * FROM jornadas_redacoes WHERE jornada_id = :jornada_id LIMIT 1",
                        ['jornada_id' => $jornadaIdParaVinculo]
                    );
                    
                    if ($jornadaRedacao) {
                        // Verificar se existe o vínculo (usando redacao_id OU a combinação jornada_redacao_id + aluno_id)
                        $vinculoExiste = $this->db->fetch(
                            "SELECT * FROM jornadas_redacoes_alunos 
                             WHERE (redacao_id = :redacao_id) 
                                OR (jornada_redacao_id = :jornada_redacao_id AND aluno_id = :aluno_id)
                             ORDER BY id DESC LIMIT 1",
                            [
                                'redacao_id' => $redacaoId,
                                'jornada_redacao_id' => $jornadaRedacao['id'],
                                'aluno_id' => $alunoRedacao['aluno_id']
                            ]
                        );
                        
                        if ($vinculoExiste) {
                            // Atualizar TODOS os vínculos relacionados (pode haver duplicatas)
                            // Primeiro, atualizar o vínculo que corresponde à redação atual
                            $this->db->update(
                                "UPDATE jornadas_redacoes_alunos 
                                 SET correcao_professor_feita = 1, 
                                     status = 'corrigida_professor', 
                                     usar_media_notas = 0,
                                     nota_media = NULL,
                                     nota_final_utilizada = :nota_final_utilizada,
                                     updated_at = NOW()
                                 WHERE redacao_id = :redacao_id",
                                [
                                    'nota_final_utilizada' => $notaFinal,
                                    'redacao_id' => $redacaoId
                                ]
                            );
                            $log("Status atualizado com sucesso (vínculo existente)");
                            
                            // IMPORTANTE: NÃO remover versões anteriores!
                            // Cada versão (1, 2, 3...) deve ser mantida para histórico.
                            // Apenas remover duplicatas reais (mesma versão, mesma redação_id, múltiplos vínculos)
                            // Isso pode acontecer se houver um bug que criou múltiplos vínculos para a mesma redação
                            $vinculosDuplicados = $this->db->fetchAll(
                                "SELECT jra1.id 
                                 FROM jornadas_redacoes_alunos jra1
                                 INNER JOIN jornadas_redacoes_alunos jra2 
                                 WHERE jra1.jornada_redacao_id = jra2.jornada_redacao_id
                                   AND jra1.aluno_id = jra2.aluno_id
                                   AND jra1.redacao_id = jra2.redacao_id
                                   AND jra1.versao = jra2.versao
                                   AND jra1.id < jra2.id
                                   AND jra1.jornada_redacao_id = :jornada_redacao_id
                                   AND jra1.aluno_id = :aluno_id
                                   AND jra1.redacao_id = :redacao_id",
                                [
                                    'jornada_redacao_id' => $jornadaRedacao['id'],
                                    'aluno_id' => $alunoRedacao['aluno_id'],
                                    'redacao_id' => $redacaoId
                                ]
                            );
                            
                            if (!empty($vinculosDuplicados)) {
                                foreach ($vinculosDuplicados as $dup) {
                                    $this->db->delete(
                                        "DELETE FROM jornadas_redacoes_alunos WHERE id = :id",
                                        ['id' => $dup['id']]
                                    );
                                }
                                $log("Duplicatas removidas (mesma versão, mesma redação): " . count($vinculosDuplicados) . " registros");
                            } else {
                                $log("Nenhuma duplicata encontrada (versões anteriores são mantidas)");
                            }
                        } else {
                            // Criar vínculo se não existir
                            $log("Vínculo não existe. Criando vínculo...");
                            $this->db->insert(
                                "INSERT INTO jornadas_redacoes_alunos 
                                 (jornada_redacao_id, redacao_id, aluno_id, status, correcao_professor_feita, usar_media_notas, nota_media, nota_final_utilizada) 
                                 VALUES (:jornada_redacao_id, :redacao_id, :aluno_id, 'corrigida_professor', 1, 0, NULL, :nota_final_utilizada)",
                                [
                                    'jornada_redacao_id' => $jornadaRedacao['id'],
                                    'redacao_id' => $redacaoId,
                                    'aluno_id' => $alunoRedacao['aluno_id'],
                                    'nota_final_utilizada' => $notaFinal
                                ]
                            );
                            $log("Vínculo criado com sucesso!");
                        }
                    } else {
                        $log("ERRO: Não foi possível encontrar jornada_redacao para a jornada");
                    }
                } else {
                    $log("ERRO: Não foi possível encontrar jornada_id ou aluno_id para criar/atualizar vínculo");
                }
            } catch (Exception $statusException) {
                $log("ERRO ao atualizar/criar status: " . $statusException->getMessage());
                $log("Stack trace: " . $statusException->getTraceAsString());
            }
            
            // Buscar jornada_id para redirecionar
            $log("Buscando jornada_id para redirecionamento...");
            $jornadaIdParaRedirect = $redacao['jornada_id'] ?? null;
            
            // Se não tem jornada_id na redação, tentar buscar de outras formas
            if (!$jornadaIdParaRedirect) {
                $jornadaInfo = $this->db->fetch(
                    "SELECT jr.jornada_id 
                     FROM jornadas_redacoes_alunos jra
                     INNER JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                     WHERE jra.redacao_id = :redacao_id
                     LIMIT 1",
                    ['redacao_id' => $redacaoId]
                );
                if ($jornadaInfo) {
                    $jornadaIdParaRedirect = $jornadaInfo['jornada_id'];
                }
            }
            
            // Se ainda não tem, usar o jornada_id do input
            if (!$jornadaIdParaRedirect) {
                $jornadaIdParaRedirect = $input['jornada_id'] ?? null;
            }
            
            if ($jornadaIdParaRedirect) {
                $log("Jornada encontrada: ID={$jornadaIdParaRedirect}. Redirecionando...");
                $_SESSION['success_message'] = 'Redação corrigida com sucesso!';
                $log("=== CORREÇÃO CONCLUÍDA COM SUCESSO ===");
                $this->redirect('/teacher/jornadas/' . $jornadaIdParaRedirect . '/exercicios-alunos');
            } else {
                $log("ERRO: Jornada não encontrada para redirecionamento");
                $_SESSION['success_message'] = 'Redação corrigida com sucesso!';
                $this->redirect('/teacher/jornadas');
            }
            
        } catch (Exception $e) {
            $log("=== ERRO GERAL ===");
            $log("Mensagem: " . $e->getMessage());
            $log("Arquivo: " . $e->getFile());
            $log("Linha: " . $e->getLine());
            $log("Stack trace: " . $e->getTraceAsString());
            $log("=== FIM ERRO ===");
            
            error_log("Erro ao corrigir redação: " . $e->getMessage());
            $_SESSION['error_message'] = 'Erro ao corrigir redação: ' . $e->getMessage();
            
            // Tentar redirecionar mesmo em caso de erro
            $jornadaInfo = $this->db->fetch(
                "SELECT jr.jornada_id 
                 FROM jornadas_redacoes_alunos jra
                 INNER JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                 WHERE jra.redacao_id = :redacao_id
                 LIMIT 1",
                ['redacao_id' => $redacaoId ?? 0]
            );
            
            if ($jornadaInfo) {
                $this->redirect('/teacher/jornadas/' . $jornadaInfo['jornada_id'] . '/exercicios-alunos');
            } else {
                // Tentar buscar pela jornada_id do POST
                $jornadaIdPost = $_POST['jornada_id'] ?? null;
                if ($jornadaIdPost) {
                    $this->redirect('/teacher/jornadas/' . $jornadaIdPost . '/exercicios-alunos');
                } else {
                    $this->redirect('/teacher/jornadas');
                }
            }
        }
    }
    
    /**
     * Excluir jornada (com confirmação de senha)
     */
    public function excluirJornada()
    {
        try {
            $user = $this->authManager->getUser();
            
            // Ler dados do JSON ou POST
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            if (!$this->verifyCsrfToken($input['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }
            
            $jornadaId = $input['jornada_id'] ?? null;
            $senha = $input['senha'] ?? '';
            
            if (!$jornadaId) {
                $this->json(['error' => 'ID da jornada é obrigatório'], 400);
                return;
            }
            
            if (empty($senha)) {
                $this->json(['error' => 'Senha é obrigatória para confirmar a exclusão'], 400);
                return;
            }
            
            // Verificar se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :id AND professor_id = :prof_id",
                ['id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                $this->json(['error' => 'Jornada não encontrada'], 404);
                return;
            }
            
            // Verificar senha do professor
            $professor = $this->db->fetch(
                "SELECT * FROM professores WHERE id = :user_id AND ativo = 1",
                ['user_id' => $user['id']]
            );
            
            if (!$professor) {
                $this->json(['error' => 'Professor não encontrado'], 404);
                return;
            }
            
            // Verificar senha
            if (!password_verify($senha, $professor['senha_hash'])) {
                $this->json(['error' => 'Senha incorreta'], 401);
                return;
            }
            
            // Iniciar transação
            $this->db->beginTransaction();
            
            try {
                // Excluir progresso dos alunos
                $this->db->query(
                    "DELETE FROM jornadas_progresso_alunos WHERE jornada_id = :jornada_id",
                    ['jornada_id' => $jornadaId]
                );
                
                // Excluir redações dos alunos
                $redacoesAlunos = $this->db->fetchAll(
                    "SELECT redacao_id FROM jornadas_redacoes_alunos 
                     WHERE jornada_redacao_id IN (
                         SELECT id FROM jornadas_redacoes WHERE jornada_id = :jornada_id
                     )",
                    ['jornada_id' => $jornadaId]
                );
                
                foreach ($redacoesAlunos as $redacaoAluno) {
                    // Excluir redação
                    $this->db->query(
                        "DELETE FROM redacoes WHERE id = :id",
                        ['id' => $redacaoAluno['redacao_id']]
                    );
                }
                
                // Excluir vínculos de redações
                $this->db->query(
                    "DELETE FROM jornadas_redacoes_alunos 
                     WHERE jornada_redacao_id IN (
                         SELECT id FROM jornadas_redacoes WHERE jornada_id = :jornada_id
                     )",
                    ['jornada_id' => $jornadaId]
                );
                
                // Excluir redações da jornada
                $this->db->query(
                    "DELETE FROM jornadas_redacoes WHERE jornada_id = :jornada_id",
                    ['jornada_id' => $jornadaId]
                );
                
                // Excluir exercícios dos módulos
                $this->db->query(
                    "DELETE FROM jornadas_modulos_exercicios 
                     WHERE modulo_id IN (
                         SELECT id FROM jornadas_modulos WHERE jornada_id = :jornada_id
                     )",
                    ['jornada_id' => $jornadaId]
                );
                
                // Excluir vídeos dos módulos
                $this->db->query(
                    "DELETE FROM jornadas_modulos_videos 
                     WHERE modulo_id IN (
                         SELECT id FROM jornadas_modulos WHERE jornada_id = :jornada_id
                     )",
                    ['jornada_id' => $jornadaId]
                );
                
                // Excluir módulos
                $this->db->query(
                    "DELETE FROM jornadas_modulos WHERE jornada_id = :jornada_id",
                    ['jornada_id' => $jornadaId]
                );
                
                // Excluir jornada
                $this->db->query(
                    "DELETE FROM jornadas WHERE id = :id",
                    ['id' => $jornadaId]
                );
                
                // Confirmar transação
                $this->db->commit();
                
                $this->json([
                    'success' => true,
                    'message' => 'Jornada excluída com sucesso'
                ]);
                
            } catch (Exception $e) {
                // Reverter transação em caso de erro
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->json(['error' => 'Erro ao excluir jornada: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Gerencia vídeos de um módulo (Professor)
     */
    public function gerenciarVideosModulo($modulo_id)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca o módulo e verifica se a jornada pertence ao professor
            $modulo = $this->db->fetch(
                "SELECT m.*, j.titulo as jornada_titulo, j.id as jornada_id, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );
            
            if (!$modulo) {
                $_SESSION['error_message'] = 'Módulo não encontrado ou não autorizado';
                $this->redirect('/teacher/jornadas');
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
            if (!empty($documentos)) {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                $media = new MediaStorageService($this->config);
                foreach ($documentos as &$doc) {
                    $doc['url_arquivo'] = '';
                    if (!empty($doc['arquivo'])) {
                        $doc['url_arquivo'] = $media->getDisplayUrl('jornadas_documentos', $doc['arquivo']);
                    }
                }
                unset($doc);
            }
            
            // Busca textos do módulo (conteúdo em texto, igual à Dica do Professor)
            $textos = [];
            try {
                $textos = $this->db->fetchAll(
                    "SELECT * FROM jornadas_modulos_textos
                     WHERE modulo_id = :modulo_id
                     ORDER BY ordem ASC, created_at ASC",
                    ['modulo_id' => $modulo_id]
                );
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'jornadas_modulos_textos') !== false) {
                    error_log("Tabela jornadas_modulos_textos não existe. Execute: php scripts/run_migrations.php");
                }
            }
            
            // Busca dados da jornada para contexto
            $jornada = $this->db->fetch(
                "SELECT j.*, m.nome as materia_nome, t.nome as turma_nome
                 FROM jornadas j
                 LEFT JOIN materias m ON j.materia_id = m.id
                 LEFT JOIN turmas t ON j.turma_id = t.id
                 WHERE j.id = :jornada_id",
                ['jornada_id' => $modulo['jornada_id']]
            );
            
            $data = [
                'title' => 'Gerenciar Vídeos - ' . ($modulo['titulo'] ?? 'Módulo') . ' - EducaTudo',
                'user' => $user,
                'modulo' => $modulo,
                'jornada' => $jornada,
                'videos' => $videos,
                'documentos' => $documentos,
                'textos' => $textos,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/modulos-videos', $data);
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao carregar vídeos: ' . $e->getMessage();
            $this->redirect('/teacher/jornadas');
        }
    }
    
    /**
     * Gerencia Dica do Professor de um módulo (Professor)
     * Usa a mesma estrutura do módulo de conteúdo (videos/documentos)
     */
    public function gerenciarDicaProfessorModulo($modulo_id)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca o módulo e verifica se a jornada pertence ao professor
            $modulo = $this->db->fetch(
                "SELECT m.*, j.titulo as jornada_titulo, j.id as jornada_id, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );
            
            if (!$modulo) {
                $_SESSION['error_message'] = 'Módulo não encontrado ou não autorizado';
                $this->redirect('/professor/jornadas');
                return;
            }
            
            // Verifica se é uma dica do professor (pelo tipo ou pelo título)
            $tipoModulo = strtolower(trim($modulo['tipo_modulo'] ?? ''));
            $tituloModulo = strtolower(trim($modulo['titulo'] ?? ''));
            $isDicaProfessor = ($tipoModulo === 'dica_professor') || 
                              (strpos($tituloModulo, 'dica do professor') !== false) ||
                              (strpos($tituloModulo, 'dica professor') !== false);
            
            if (!$isDicaProfessor) {
                $_SESSION['error_message'] = 'Este módulo não é uma dica do professor';
                $this->redirect('/professor/jornadas/' . $modulo['jornada_id'] . '/modulos');
                return;
            }
            
            // Busca vídeos do módulo (mesma tabela, diferenciado pelo tipo do módulo)
            $videos = $this->db->fetchAll(
                "SELECT * FROM jornadas_modulos_videos
                 WHERE modulo_id = :modulo_id
                 ORDER BY ordem ASC, created_at ASC",
                ['modulo_id' => $modulo_id]
            );
            
            // Busca documentos do módulo (mesma tabela, diferenciado pelo tipo do módulo)
            $documentos = $this->db->fetchAll(
                "SELECT * FROM jornadas_modulos_documentos
                 WHERE modulo_id = :modulo_id
                 ORDER BY ordem ASC, created_at ASC",
                ['modulo_id' => $modulo_id]
            );
            if (!empty($documentos)) {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                $media = new MediaStorageService($this->config);
                foreach ($documentos as &$doc) {
                    $doc['url_arquivo'] = '';
                    if (!empty($doc['arquivo'])) {
                        $doc['url_arquivo'] = $media->getDisplayUrl('jornadas_documentos', $doc['arquivo']);
                    }
                }
                unset($doc);
            }
            
            // Busca textos do módulo (dica do professor sem anexo; tabela pode não existir se migration não rodou)
            $textos = [];
            try {
                $textos = $this->db->fetchAll(
                    "SELECT * FROM jornadas_modulos_textos
                     WHERE modulo_id = :modulo_id
                     ORDER BY ordem ASC, created_at ASC",
                    ['modulo_id' => $modulo_id]
                );
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'jornadas_modulos_textos') !== false) {
                    error_log("Tabela jornadas_modulos_textos não existe. Execute: php scripts/run_migrations.php");
                } else {
                    throw $e;
                }
            }
            
            // Busca dados da jornada para contexto
            $jornada = $this->db->fetch(
                "SELECT j.*, m.nome as materia_nome, t.nome as turma_nome
                 FROM jornadas j
                 LEFT JOIN materias m ON j.materia_id = m.id
                 LEFT JOIN turmas t ON j.turma_id = t.id
                 WHERE j.id = :jornada_id",
                ['jornada_id' => $modulo['jornada_id']]
            );
            
            $data = [
                'title' => 'Gerenciar Dica do Professor - ' . ($modulo['titulo'] ?? 'Módulo') . ' - EducaTudo',
                'user' => $user,
                'modulo' => $modulo,
                'jornada' => $jornada,
                'videos' => $videos,
                'documentos' => $documentos,
                'textos' => $textos,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys',
                'is_dica_professor' => true // Flag para diferenciar na view
            ];
            
            // Usa a mesma view do módulo de conteúdo, mas com flag para diferenciar
            $this->viewWithLayout('professor', 'teacher/journeys/modulos-videos', $data);
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao carregar dica do professor: ' . $e->getMessage();
            $this->redirect('/professor/jornadas');
        }
    }
    
    /**
     * Gerencia Resumo do Aluno de um módulo (Professor)
     * Permite editar a descrição/instrução do resumo
     */
    public function gerenciarResumoAlunoModulo($modulo_id)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca o módulo e verifica se a jornada pertence ao professor
            $modulo = $this->db->fetch(
                "SELECT m.*, j.titulo as jornada_titulo, j.id as jornada_id, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );
            
            if (!$modulo) {
                $_SESSION['error_message'] = 'Módulo não encontrado ou não autorizado';
                $this->redirect('/professor/jornadas');
                return;
            }
            
            // Verifica se é um módulo de resumo do aluno
            $tipoModulo = strtolower(trim($modulo['tipo_modulo'] ?? ''));
            if ($tipoModulo !== 'resumo_aluno') {
                $_SESSION['error_message'] = 'Este módulo não é um resumo do aluno';
                $this->redirect('/professor/jornadas/' . $modulo['jornada_id'] . '/modulos');
                return;
            }
            
            // Busca dados da jornada para contexto
            $jornada = $this->db->fetch(
                "SELECT j.*, m.nome as materia_nome, t.nome as turma_nome
                 FROM jornadas j
                 LEFT JOIN materias m ON j.materia_id = m.id
                 LEFT JOIN turmas t ON j.turma_id = t.id
                 WHERE j.id = :jornada_id",
                ['jornada_id' => $modulo['jornada_id']]
            );
            
            $data = [
                'title' => 'Gerenciar Resumo do Aluno - ' . ($modulo['titulo'] ?? 'Módulo') . ' - EducaTudo',
                'user' => $user,
                'modulo' => $modulo,
                'jornada' => $jornada,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/modulos-resumo-aluno', $data);
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao carregar resumo do aluno: ' . $e->getMessage();
            $this->redirect('/professor/jornadas');
        }
    }
    
    /**
     * Salva a descrição/instrução do resumo do aluno
     */
    public function salvarDescricaoResumoModulo($modulo_id)
    {
        try {
            $user = $this->authManager->getUser();
            $descricao = $_POST['descricao'] ?? '';
            
            // Verifica se o módulo pertence a uma jornada do professor
            $modulo = $this->db->fetch(
                "SELECT m.*, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id AND m.tipo_modulo = 'resumo_aluno'",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );
            
            if (!$modulo) {
                throw new Exception('Módulo não encontrado ou não autorizado');
            }
            
            // Atualiza a descrição do módulo
            $this->db->update(
                "UPDATE jornadas_modulos 
                 SET descricao = :descricao, updated_at = NOW()
                 WHERE id = :modulo_id",
                [
                    'descricao' => $descricao,
                    'modulo_id' => $modulo_id
                ]
            );
            
            $_SESSION['success_message'] = 'Descrição salva com sucesso!';
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            error_log("Erro ao salvar descrição do resumo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Adiciona vídeo ao módulo (Professor).
     * Um conteúdo por bloco: remove vídeos e documentos existentes do módulo antes de inserir.
     * Tipos: youtube, upload (S3), link_externo (URL em url_youtube).
     */
    public function adicionarVideoModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if (empty($_POST) && $contentLength > 0) {
                throw new Exception(
                    'Os dados do formulário não chegaram ao servidor. Com vídeos grandes, isso costuma indicar limite do PHP (post_max_size / upload_max_filesize). Comprima o vídeo ou peça à escola para aumentar esses limites.'
                );
            }
            $modulo_id = $_POST['modulo_id'] ?? null;
            $tipo = $_POST['tipo'] ?? 'youtube';
            $titulo = trim((string) ($_POST['titulo'] ?? ''));
            $descricao = $_POST['descricao'] ?? '';
            $url_youtube = trim((string) ($_POST['url_youtube'] ?? ''));

            if (!$modulo_id) {
                throw new Exception('Não foi identificado o módulo da jornada. Atualize a página e tente novamente.');
            }
            if ($titulo === '') {
                throw new Exception('Informe o título do conteúdo (no topo do formulário ou no bloco amarelo acima de Salvar conteúdo).');
            }

            if ($tipo === 'upload' && !empty($_FILES['arquivo_video'])) {
                $ferr = (int) ($_FILES['arquivo_video']['error'] ?? UPLOAD_ERR_OK);
                if ($ferr === UPLOAD_ERR_INI_SIZE || $ferr === UPLOAD_ERR_FORM_SIZE) {
                    throw new Exception(
                        'O vídeo excede o tamanho máximo permitido pelo servidor (upload_max_filesize / post_max_size). Comprima o arquivo ou peça para aumentar o limite no PHP.'
                    );
                }
                if ($ferr !== UPLOAD_ERR_OK && $ferr !== UPLOAD_ERR_NO_FILE) {
                    throw new Exception('Erro no envio do vídeo (código ' . $ferr . '). Tente outro arquivo.');
                }
            }
            
            $modulo = $this->db->fetch(
                "SELECT m.*, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );
            
            if (!$modulo) {
                throw new Exception('Módulo não encontrado ou não autorizado');
            }
            
            $temLink = $url_youtube !== '';
            $temArquivoVideo = isset($_FILES['arquivo_video']) && $_FILES['arquivo_video']['error'] === UPLOAD_ERR_OK && !empty($_FILES['arquivo_video']['tmp_name']);
            if (in_array($tipo, ['youtube', 'link_externo'], true) && !$temLink) {
                throw new Exception($tipo === 'link_externo' ? 'Obrigatório: informe o link externo.' : 'Obrigatório: informe o link do YouTube.');
            }
            if ($tipo === 'upload' && !$temArquivoVideo) {
                throw new Exception('Obrigatório: faça o upload de um arquivo de vídeo.');
            }
            
            $arquivo_video = null;
            $arquivo_nome = null;
            $arquivo_tamanho = null;
            
            if ($tipo === 'upload' && isset($_FILES['arquivo_video'])) {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                $media = new MediaStorageService($this->config);
                $file = $_FILES['arquivo_video'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = uniqid() . '.' . $ext;
                $key = MediaStorageService::userKey('teacher', (int) $user['id'], $filename);
                $contentType = $file['type'] ?? 'video/mp4';
                if (!$media->put('jornadas_videos', $key, $file['tmp_name'], $contentType)) {
                    throw new Exception('Erro ao enviar vídeo. Tente novamente.');
                }
                $arquivo_video = $key;
                $arquivo_nome = $file['name'];
                $arquivo_tamanho = (int) $file['size'];
            }
            
            $this->limparConteudoModuloParaUmConteudo($modulo_id, $user['id']);
            
            $videoId = $this->db->insert(
                "INSERT INTO jornadas_modulos_videos 
                 (modulo_id, tipo, titulo, descricao, url_youtube, arquivo_video, arquivo_nome, arquivo_tamanho, ordem) 
                 VALUES (:modulo_id, :tipo, :titulo, :descricao, :url_youtube, :arquivo_video, :arquivo_nome, :arquivo_tamanho, 0)",
                [
                    'modulo_id' => $modulo_id,
                    'tipo' => $tipo,
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                    'url_youtube' => $url_youtube !== '' ? $url_youtube : null,
                    'arquivo_video' => $arquivo_video,
                    'arquivo_nome' => $arquivo_nome,
                    'arquivo_tamanho' => $arquivo_tamanho,
                ]
            );
            
            $_SESSION['success_message'] = 'Conteúdo salvo com sucesso!';
            $this->json(['success' => true, 'video_id' => $videoId]);
            
        } catch (Exception $e) {
            error_log("Erro ao adicionar vídeo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Remove todos os vídeos, documentos e textos do módulo (um conteúdo por bloco) e arquivos no S3 quando aplicável.
     */
    private function limparConteudoModuloParaUmConteudo($modulo_id, $prof_id)
    {
        $videos = $this->db->fetchAll("SELECT id, arquivo_video FROM jornadas_modulos_videos WHERE modulo_id = :m", ['m' => $modulo_id]);
        foreach ($videos as $v) {
            if (!empty($v['arquivo_video'])) {
                $this->removerArquivoJornadaStorage('jornadas_videos', $v['arquivo_video']);
                if (is_file($v['arquivo_video'])) {
                    @unlink($v['arquivo_video']);
                }
            }
            $this->db->delete("DELETE FROM jornadas_modulos_videos WHERE id = :id", ['id' => $v['id']]);
        }
        $docs = $this->db->fetchAll("SELECT id, arquivo FROM jornadas_modulos_documentos WHERE modulo_id = :m", ['m' => $modulo_id]);
        foreach ($docs as $d) {
            if (!empty($d['arquivo'])) {
                $this->removerArquivoJornadaStorage('jornadas_documentos', $d['arquivo']);
                if (is_file($d['arquivo'])) {
                    @unlink($d['arquivo']);
                }
            }
            $this->db->delete("DELETE FROM jornadas_modulos_documentos WHERE id = :id", ['id' => $d['id']]);
        }
        $this->db->delete("DELETE FROM jornadas_modulos_textos WHERE modulo_id = :m", ['m' => $modulo_id]);
    }
    
    /**
     * Remove arquivo do storage (S3). $ref = key (ex: teacher/1/x.mp4). Path legado não chama delete.
     */
    private function removerArquivoJornadaStorage($type, $ref)
    {
        $ref = trim((string) $ref);
        if ($ref === '' || strpos($ref, 'public/uploads/') !== false || strpos($ref, 'uploads/') !== false) {
            return;
        }
        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $media->delete($type, $ref);
    }
    
    /**
     * Remove vídeo do módulo (Professor)
     */
    public function removerVideoModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $video_id = $_POST['video_id'] ?? null;
            
            if (!$video_id) {
                throw new Exception('ID do vídeo é obrigatório');
            }
            
            // Verifica se o vídeo pertence a um módulo de uma jornada do professor
            $video = $this->db->fetch(
                "SELECT v.*, j.professor_id
                 FROM jornadas_modulos_videos v
                 JOIN jornadas_modulos m ON v.modulo_id = m.id
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE v.id = :video_id AND j.professor_id = :prof_id",
                ['video_id' => $video_id, 'prof_id' => $user['id']]
            );
            
            if (!$video) {
                throw new Exception('Vídeo não encontrado ou não autorizado');
            }
            
            if (!empty($video['arquivo_video'])) {
                $this->removerArquivoJornadaStorage('jornadas_videos', $video['arquivo_video']);
                if (is_file($video['arquivo_video'])) {
                    @unlink($video['arquivo_video']);
                }
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
     * Adiciona documento ao módulo (Professor)
     */
    public function adicionarDocumentoModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $modulo_id = $_POST['modulo_id'] ?? null;
            $documento_id = isset($_POST['documento_id']) ? (int) $_POST['documento_id'] : null;
            $titulo = $_POST['titulo'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            
            if (!$modulo_id || !$titulo) {
                throw new Exception('Módulo e título são obrigatórios');
            }
            
            // Verifica se o módulo pertence a uma jornada do professor
            $modulo = $this->db->fetch(
                "SELECT m.*, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );
            
            if (!$modulo) {
                throw new Exception('Módulo não encontrado ou não autorizado');
            }
            
            $temArquivo = isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK && !empty($_FILES['arquivo']['tmp_name']);
            // Edição: apenas atualizar título e descrição sem trocar o arquivo
            if ($documento_id && !$temArquivo) {
                $doc = $this->db->fetch(
                    "SELECT d.id FROM jornadas_modulos_documentos d
                     JOIN jornadas_modulos m ON d.modulo_id = m.id
                     JOIN jornadas j ON m.jornada_id = j.id
                     WHERE d.id = :documento_id AND d.modulo_id = :modulo_id AND j.professor_id = :prof_id",
                    ['documento_id' => $documento_id, 'modulo_id' => $modulo_id, 'prof_id' => $user['id']]
                );
                if (!$doc) {
                    throw new Exception('Documento não encontrado ou não autorizado');
                }
                $this->db->update(
                    "UPDATE jornadas_modulos_documentos SET titulo = :titulo, descricao = :descricao WHERE id = :id",
                    ['titulo' => $titulo, 'descricao' => $descricao, 'id' => $documento_id]
                );
                $_SESSION['success_message'] = 'Conteúdo atualizado com sucesso!';
                $this->json(['success' => true, 'documento_id' => $documento_id]);
                return;
            }
            
            if (!$temArquivo) {
                throw new Exception('Arquivo obrigatório. Para adicionar apenas texto, use a opção Texto.');
            }
            
            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $media = new MediaStorageService($this->config);
            $file = $_FILES['arquivo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = uniqid() . '.' . $ext;
            $key = MediaStorageService::userKey('teacher', (int) $user['id'], $filename);
            $contentType = $file['type'] ?? 'application/octet-stream';
            if (!$media->put('jornadas_documentos', $key, $file['tmp_name'], $contentType)) {
                throw new Exception('Erro ao enviar documento. Tente novamente.');
            }
            
            $arquivo_caminho = $key;
            $arquivo_nome = $file['name'];
            $arquivo_tamanho = (int) $file['size'];
            $tipo_arquivo = substr((string)($file['type'] ?? ''), 0, 255);
            
            $this->limparConteudoModuloParaUmConteudo($modulo_id, $user['id']);
            
            $documentoId = $this->db->insert(
                "INSERT INTO jornadas_modulos_documentos 
                 (modulo_id, titulo, descricao, arquivo, arquivo_nome, arquivo_tamanho, tipo_arquivo, ordem) 
                 VALUES (:modulo_id, :titulo, :descricao, :arquivo, :arquivo_nome, :arquivo_tamanho, :tipo_arquivo, 0)",
                [
                    'modulo_id' => $modulo_id,
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                    'arquivo' => $arquivo_caminho,
                    'arquivo_nome' => $arquivo_nome,
                    'arquivo_tamanho' => $arquivo_tamanho,
                    'tipo_arquivo' => $tipo_arquivo,
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
     * Remove documento do módulo (Professor)
     */
    public function removerDocumentoModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $documento_id = $_POST['documento_id'] ?? null;
            
            if (!$documento_id) {
                throw new Exception('ID do documento é obrigatório');
            }
            
            // Verifica se o documento pertence a um módulo de uma jornada do professor
            $documento = $this->db->fetch(
                "SELECT d.*, j.professor_id
                 FROM jornadas_modulos_documentos d
                 JOIN jornadas_modulos m ON d.modulo_id = m.id
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE d.id = :documento_id AND j.professor_id = :prof_id",
                ['documento_id' => $documento_id, 'prof_id' => $user['id']]
            );
            
            if (!$documento) {
                throw new Exception('Documento não encontrado ou não autorizado');
            }
            
            if (!empty($documento['arquivo'])) {
                $this->removerArquivoJornadaStorage('jornadas_documentos', $documento['arquivo']);
                if (is_file($documento['arquivo'])) {
                    @unlink($documento['arquivo']);
                }
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
     * Adiciona texto (dica sem anexo) ao módulo Dica do Professor
     */
    public function adicionarTextoModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $modulo_id = $_POST['modulo_id'] ?? null;
            $titulo = trim($_POST['titulo'] ?? '');
            $conteudo = $_POST['conteudo'] ?? '';
            
            if (!$modulo_id || $titulo === '') {
                throw new Exception('Módulo e título são obrigatórios');
            }
            
            $modulo = $this->db->fetch(
                "SELECT m.*, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );
            
            if (!$modulo) {
                throw new Exception('Módulo não encontrado ou não autorizado');
            }
            
            // Um conteúdo por bloco: limpar vídeos, documentos e textos do módulo antes de inserir
            $this->limparConteudoModuloParaUmConteudo($modulo_id, $user['id']);
            
            // Sanitiza HTML permitindo tags seguras (evita XSS), incluindo img do Quill
            $conteudoSanitizado = strip_tags($conteudo, '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><img>');
            // Remove img com src que não seja http/https ou caminho relativo (evita javascript: e base64 gigante)
            $conteudoSanitizado = preg_replace_callback('/<img([^>]*)>/i', function ($m) {
                if (!preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $m[1], $src)) {
                    return '';
                }
                $url = trim($src[1]);
                if (preg_match('/^\s*https?:\/\//i', $url) || preg_match('/^\s*\//', $url)) {
                    return $m[0];
                }
                return '';
            }, $conteudoSanitizado);
            
            $ultimaOrdem = $this->db->fetch(
                "SELECT MAX(ordem) as max_ordem FROM jornadas_modulos_textos WHERE modulo_id = :modulo_id",
                ['modulo_id' => $modulo_id]
            );
            $ordem = ($ultimaOrdem['max_ordem'] ?? 0) + 1;
            
            $this->db->insert(
                "INSERT INTO jornadas_modulos_textos (modulo_id, titulo, conteudo, ordem) 
                 VALUES (:modulo_id, :titulo, :conteudo, :ordem)",
                [
                    'modulo_id' => $modulo_id,
                    'titulo' => $titulo,
                    'conteudo' => $conteudoSanitizado,
                    'ordem' => $ordem
                ]
            );
            
            $_SESSION['success_message'] = 'Texto adicionado com sucesso!';
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            $msg = $e->getMessage();
            // Mensagem amigável para estouro de tamanho da coluna (SQLSTATE 22001 / erro 1406)
            if (strpos($msg, 'SQLSTATE[22001]') !== false || strpos($msg, '1406 Data too long for column') !== false) {
                $msg = 'O texto da dica está grande demais para salvar. Reduza o conteúdo e remova imagens coladas no texto (base64). Use links de imagem/arquivo.';
            }
            error_log("Erro ao adicionar texto: " . $e->getMessage());
            $this->json(['error' => $msg], 400);
        }
    }
    
    /**
     * Remove texto do módulo Dica do Professor
     */
    public function removerTextoModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $texto_id = $_POST['texto_id'] ?? null;
            
            if (!$texto_id) {
                throw new Exception('ID do texto é obrigatório');
            }
            
            $texto = $this->db->fetch(
                "SELECT t.*, j.professor_id
                 FROM jornadas_modulos_textos t
                 JOIN jornadas_modulos m ON t.modulo_id = m.id
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE t.id = :texto_id AND j.professor_id = :prof_id",
                ['texto_id' => $texto_id, 'prof_id' => $user['id']]
            );
            
            if (!$texto) {
                throw new Exception('Texto não encontrado ou não autorizado');
            }
            
            $this->db->delete(
                "DELETE FROM jornadas_modulos_textos WHERE id = :texto_id",
                ['texto_id' => $texto_id]
            );
            
            $_SESSION['success_message'] = 'Texto removido com sucesso!';
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            error_log("Erro ao remover texto: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Gerencia exercícios de um módulo (Professor)
     */
    public function gerenciarExerciciosModulo($modulo_id)
    {
        $this->renderModuloExerciciosPage($modulo_id, 'lista');
    }

    /**
     * Página de criação/edição de exercícios de um módulo (Professor)
     */
    public function gerenciarExerciciosModuloEditor($modulo_id)
    {
        $this->renderModuloExerciciosPage($modulo_id, 'editor');
    }

    /**
     * Renderiza páginas de exercícios do módulo (lista ou editor)
     */
    private function renderModuloExerciciosPage($modulo_id, $pageMode = 'lista')
    {
        try {
            $user = $this->authManager->getUser();
            
            // Busca o módulo e verifica se a jornada pertence ao professor
            $modulo = $this->db->fetch(
                "SELECT m.*, j.titulo as jornada_titulo, j.id as jornada_id, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );
            
            if (!$modulo) {
                $_SESSION['error_message'] = 'Módulo não encontrado ou não autorizado';
                $this->redirect('/teacher/jornadas');
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
                "SELECT j.*, m.nome as materia_nome, t.nome as turma_nome
                 FROM jornadas j
                 LEFT JOIN materias m ON j.materia_id = m.id
                 LEFT JOIN turmas t ON j.turma_id = t.id
                 WHERE j.id = :jornada_id",
                ['jornada_id' => $modulo['jornada_id']]
            );
            
            // Busca planos de aula do professor
            $planosAula = [];
            try {
                $planosAula = $this->db->fetchAll(
                    "SELECT pa.id, pa.titulo, pa.objetivos, m.nome as materia_nome
                     FROM planos_aula pa
                     LEFT JOIN materias m ON pa.materia_id = m.id
                     WHERE pa.professor_id = :prof_id AND pa.professor_id IS NOT NULL
                     ORDER BY pa.created_at DESC",
                    ['prof_id' => $user['id']]
                );
            } catch (Exception $e) {
                error_log("Erro ao buscar planos de aula: " . $e->getMessage());
            }
            
            $data = [
                'title' => (($pageMode === 'editor') ? 'Criar/Editar Exercícios - ' : 'Exercícios do Módulo - ') . ($modulo['titulo'] ?? 'Módulo') . ' - EducaTudo',
                'user' => $user,
                'modulo' => $modulo,
                'jornada' => $jornada,
                'exercicios' => $exercicios,
                'planosAula' => $planosAula,
                'page_mode' => $pageMode,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys',
                'additional_css' => '<link rel="stylesheet" href="' . URL . '/public/static/css/mathlive-static.css"><link rel="stylesheet" href="' . URL . '/public/static/css/math-editor.css">',
                'additional_js' => '<script src="' . URL . '/public/static/js/mathlive.min.js"></script><script src="' . URL . '/public/static/js/math-editor.js"></script>',
            ];

            if ($pageMode === 'editor') {
                $this->viewWithLayout('professor', 'teacher/journeys/modulos-exercicios', $data);
                return;
            }
            $this->viewWithLayout('professor', 'teacher/journeys/modulos-exercicios-lista', $data);
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao carregar exercícios: ' . $e->getMessage();
            $this->redirect('/teacher/jornadas');
        }
    }
    
    /**
     * Adiciona exercício ao módulo (Professor)
     */
    private function preserveAllowedImagesInEnunciado(string $rawEnunciado, string $cleanEnunciado): string
    {
        $rawEnunciado = trim($rawEnunciado);
        $cleanEnunciado = trim($cleanEnunciado);

        if ($rawEnunciado === '' || stripos($rawEnunciado, '<img') === false) {
            return $cleanEnunciado;
        }

        $imagesHtml = [];
        if (preg_match_all('/<img[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $rawEnunciado, $matches)) {
            foreach (($matches[1] ?? []) as $srcRaw) {
                $src = trim(html_entity_decode((string) $srcRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($src === '' || stripos($src, 'data:') === 0) {
                    continue;
                }

                $isRelativeMedia = (strpos($src, '/media/serve?') === 0);
                $isSameHostMedia = false;
                $appUrl = rtrim((string) (defined('URL') ? URL : ''), '/');
                if ($appUrl !== '' && strpos($src, $appUrl . '/media/serve?') === 0) {
                    $isSameHostMedia = true;
                }

                if (!$isRelativeMedia && !$isSameHostMedia) {
                    continue;
                }

                $imagesHtml[] = '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
                    . '" style="max-width:100%;height:auto;vertical-align:middle;">';
            }
        }

        if (empty($imagesHtml)) {
            return $cleanEnunciado;
        }

        $imagesBlock = implode('<br>', $imagesHtml);
        if ($cleanEnunciado === '') {
            return $imagesBlock;
        }

        return $cleanEnunciado . '<br>' . $imagesBlock;
    }

    /**
     * Converte imagens inline base64 (<img src="data:image/...">) em arquivos reais
     * e troca o src por URL estável de media/serve.
     */
    private function persistInlineBase64ImagesInHtml(string $html, int $professorId): string
    {
        $html = trim($html);
        if ($html === '' || stripos($html, 'data:image/') === false) {
            return $html;
        }

        require_once __DIR__ . '/../../Services/MediaStorageService.php';
        $media = new MediaStorageService($this->config);
        $baseUrl = rtrim((string) (defined('URL') ? URL : ''), '/');

        $result = preg_replace_callback('/<img\b([^>]*)\bsrc=["\']([^"\']+)["\']([^>]*)>/i', function ($m) use ($media, $professorId, $baseUrl) {
            $before = $m[1] ?? '';
            $srcRaw = html_entity_decode((string)($m[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $after = $m[3] ?? '';
            $src = trim($srcRaw);

            if (stripos($src, 'data:image/') !== 0) {
                return $m[0];
            }

            if (!preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,(.+)$/is', $src, $mm)) {
                return $m[0];
            }

            $ext = strtolower($mm[1]);
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            $payload = preg_replace('/\s+/', '', (string)$mm[2]);
            $bin = base64_decode($payload, true);
            if ($bin === false || $bin === '' || strlen($bin) > 10 * 1024 * 1024) {
                return $m[0];
            }

            $filename = 'exercicio_inline_' . $professorId . '_' . time() . '_' . uniqid('', true) . '.' . $ext;
            $key = \App\Services\MediaStorageService::userKey('teacher', $professorId, $filename);
            $tmpPath = sys_get_temp_dir() . '/' . $filename;
            $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);

            $ok = false;
            if (@file_put_contents($tmpPath, $bin) !== false) {
                $ok = $media->put('jornadas_exercicios', $key, $tmpPath, $mime);
            }
            @unlink($tmpPath);

            if (!$ok) {
                return $m[0];
            }

            $url = $this->buildStableJourneyExerciseMediaUrl($key);
            return '<img' . $before . 'src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $after . '>';
        }, $html);

        return is_string($result) ? $result : $html;
    }

    private function buildStableJourneyExerciseMediaUrl(string $key): string
    {
        $baseUrl = rtrim((string) (defined('URL') ? URL : ''), '/');
        $slug = trim((string) ($this->config['tenant']['slug'] ?? ''));
        if ($slug === '') {
            $slug = trim((string) ($this->config['school']['code'] ?? ''));
        }

        $url = $baseUrl . '/media/serve?type=jornadas_exercicios&key=' . rawurlencode(trim($key, '/'));
        if ($slug !== '' && $slug !== 'default') {
            $url .= '&tenant=' . rawurlencode($slug);
        }

        return $url;
    }

    public function adicionarExercicioModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $modulo_id = $_POST['modulo_id'] ?? null;
            $tipo = $_POST['tipo'] ?? 'alternativas';
            $titulo = trim($_POST['titulo'] ?? '');
            $enunciadoRaw = trim((string)($_POST['enunciado'] ?? ''));
            $enunciado = $enunciadoRaw;
            $questoes_json = $_POST['questoes_json'] ?? null;
            $resposta_correta = $_POST['resposta_correta'] ?? null;
            $gabarito = $_POST['gabarito'] ?? null;
            $pontuacao = $_POST['pontuacao'] ?? 1.00;
            $gerado_ia = isset($_POST['gerado_ia']) ? 1 : 0;
            $imagem_url = trim($_POST['imagem_url'] ?? '') ?: null;
            $nivel_dificuldade = trim($_POST['nivel_dificuldade'] ?? '') ?: null;
            if ($nivel_dificuldade && !in_array($nivel_dificuldade, ['facil', 'medio', 'dificil'], true)) {
                $nivel_dificuldade = null;
            }

            $enunciado = $this->persistInlineBase64ImagesInHtml($enunciado, (int)$user['id']);
            $enunciado = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($enunciado);
            $enunciadoTexto = trim((string) strip_tags($enunciadoRaw));
            $temImagemNoEnunciado = stripos((string) $enunciadoRaw, '<img') !== false;
            $temImagemManual = !empty($imagem_url);
            if (is_string($questoes_json)) {
                $questoes_json = json_decode($questoes_json, true);
            }
            if (is_array($questoes_json) && !empty($questoes_json['opcoes'])) {
                foreach ($questoes_json['opcoes'] as &$opcao) {
                    if (isset($opcao['texto']) && is_string($opcao['texto'])) {
                        $opcao['texto'] = $this->persistInlineBase64ImagesInHtml($opcao['texto'], (int)$user['id']);
                        $opcao['texto'] = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($opcao['texto']);
                    }
                }
                unset($opcao);
            }
            
            if (empty($modulo_id) || (!$enunciadoTexto && !$temImagemNoEnunciado && !$temImagemManual)) {
                throw new Exception('Módulo e enunciado (texto ou imagem) são obrigatórios');
            }
            
            // Se não tiver título, usa o enunciado (limitado a 100 caracteres, strip HTML)
            if (empty($titulo)) {
                if ($enunciadoTexto !== '') {
                    $titulo = mb_substr($enunciadoTexto, 0, 100);
                    if (mb_strlen($enunciadoTexto) > 100) {
                        $titulo .= '...';
                    }
                } else {
                    $titulo = 'Exercício com imagem';
                }
            }
            
            // Verifica se o módulo pertence a uma jornada do professor
            $modulo = $this->db->fetch(
                "SELECT m.*, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );
            
            if (!$modulo) {
                throw new Exception('Módulo não encontrado ou não autorizado');
            }
            
            // Busca a próxima ordem
            $ultimaOrdem = $this->db->fetch(
                "SELECT MAX(ordem) as max_ordem 
                 FROM jornadas_modulos_exercicios 
                 WHERE modulo_id = :modulo_id",
                ['modulo_id' => $modulo_id]
            );
            $ordem = ($ultimaOrdem['max_ordem'] ?? 0) + 1;
            
            $questoesJsonParaBanco = $questoes_json ? json_encode($questoes_json) : null;
            
            $exercicioId = $this->db->insert(
                "INSERT INTO jornadas_modulos_exercicios 
                 (modulo_id, tipo, titulo, enunciado, questoes_json, resposta_correta, gabarito, pontuacao, ordem, gerado_ia, status, imagem_url, nivel_dificuldade) 
                 VALUES (:modulo_id, :tipo, :titulo, :enunciado, :questoes_json, :resposta_correta, :gabarito, :pontuacao, :ordem, :gerado_ia, 'publicado', :imagem_url, :nivel_dificuldade)",
                [
                    'modulo_id' => $modulo_id,
                    'tipo' => $tipo,
                    'titulo' => $titulo,
                    'enunciado' => $enunciado,
                    'questoes_json' => $questoesJsonParaBanco,
                    'resposta_correta' => $resposta_correta,
                    'gabarito' => $gabarito,
                    'pontuacao' => $pontuacao,
                    'ordem' => $ordem,
                    'gerado_ia' => $gerado_ia,
                    'imagem_url' => $imagem_url,
                    'nivel_dificuldade' => $nivel_dificuldade
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
     * Enfileira geração de exercícios do módulo via motor único (gerar_questao_ia).
     * A request responde rápido com job_id; o frontend faz polling e chama
     * importarExerciciosModuloIA ao concluir — evita timeout da Cloudflare (~100s)
     * que ocorria no fluxo síncrono anterior.
     */
    public function gerarExercicioIAModulo()
    {
        $traceId = 'ia_jornada_' . date('Ymd_His') . '_' . substr(md5((string) microtime(true)), 0, 8);
        $userId = null;
        $modulo_id = null;
        $tipo = 'alternativas';
        $quantidade = 0;
        $niveis = [];
        $planosAulaIds = [];
        $contexto = '';
        $input = '';

        try {
            $user = $this->authManager->getUser();
            $userId = $user['id'] ?? null;

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            $tokenCsrf = $data['_token'] ?? ($_POST['_token'] ?? '');
            if (!$this->verifyCsrfToken($tokenCsrf)) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }

            if ($data) {
                $modulo_id = $data['modulo_id'] ?? null;
                $tipo = $data['tipo'] ?? 'alternativas';
                $quantidade = (int) ($data['quantidade'] ?? 5);
                $contexto = $data['contexto'] ?? '';
                $planosAulaIds = $data['planos_aula_id'] ?? [];
                $niveis = $data['niveis'] ?? [];
                $serie = trim((string) ($data['serie'] ?? ''));
                $comImagens = !empty($data['com_imagens']) && $data['com_imagens'] !== '0';
                $csrfToken = (string) ($data['_token'] ?? '');
                $quantidadesPorNivel = [
                    'facil' => (int) ($data['quantidade_facil'] ?? 0),
                    'medio' => (int) ($data['quantidade_medio'] ?? 0),
                    'dificil' => (int) ($data['quantidade_dificil'] ?? 0),
                    'desafio' => (int) ($data['quantidade_desafio'] ?? 0),
                ];
            } else {
                $modulo_id = $_POST['modulo_id'] ?? null;
                $tipo = $_POST['tipo'] ?? 'alternativas';
                $quantidade = (int) ($_POST['quantidade'] ?? 5);
                $contexto = $_POST['contexto'] ?? '';
                $planosAulaIds = $_POST['planos_aula_id'] ?? [];
                $niveis = $_POST['niveis'] ?? [];
                $serie = trim((string) ($_POST['serie'] ?? ''));
                $comImagens = !empty($_POST['com_imagens']) && $_POST['com_imagens'] !== '0';
                $csrfToken = (string) ($_POST['_token'] ?? '');
                $quantidadesPorNivel = [
                    'facil' => (int) ($_POST['quantidade_facil'] ?? 0),
                    'medio' => (int) ($_POST['quantidade_medio'] ?? 0),
                    'dificil' => (int) ($_POST['quantidade_dificil'] ?? 0),
                    'desafio' => (int) ($_POST['quantidade_desafio'] ?? 0),
                ];
            }

            if (!$this->verifyCsrfToken($csrfToken)) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }

            if (!$modulo_id) {
                throw new Exception('Módulo é obrigatório');
            }

            $quantidade = max(1, min(20, $quantidade));

            $modulo = $this->db->fetch(
                "SELECT m.*, j.titulo as jornada_titulo, j.descricao as jornada_descricao, j.professor_id, mat.nome as materia_nome
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 LEFT JOIN materias mat ON j.materia_id = mat.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );

            if (!$modulo) {
                throw new Exception('Módulo não encontrado ou não autorizado');
            }

            if (is_string($planosAulaIds)) {
                $planosAulaIds = [$planosAulaIds];
            }
            $planosAulaIds = array_map('intval', array_filter($planosAulaIds));

            $objetivosPlanos = [];
            if (!empty($planosAulaIds)) {
                foreach ($planosAulaIds as $planoId) {
                    $plano = $this->db->fetch(
                        "SELECT objetivos FROM planos_aula WHERE id = :id AND professor_id = :prof_id",
                        ['id' => $planoId, 'prof_id' => $user['id']]
                    );
                    if ($plano && !empty($plano['objetivos'])) {
                        $objetivo = strip_tags($plano['objetivos']);
                        $objetivo = html_entity_decode($objetivo, ENT_QUOTES, 'UTF-8');
                        $objetivo = preg_replace('/\s+/', ' ', trim($objetivo));
                        if (!empty($objetivo)) {
                            $objetivosPlanos[] = $objetivo;
                        }
                    }
                }
            }

            $contextoCompleto = "Matéria: " . ($modulo['materia_nome'] ?? 'Geral') . "\n";
            $contextoCompleto .= "Jornada: {$modulo['jornada_titulo']}\n";
            if ($modulo['jornada_descricao']) {
                $contextoCompleto .= "Descrição: {$modulo['jornada_descricao']}\n";
            }
            if (!empty($objetivosPlanos)) {
                $contextoCompleto .= "Objetivos dos planos de aula:\n" . implode("\n", $objetivosPlanos) . "\n";
            }
            if ($contexto) {
                $contextoCompleto .= "Contexto adicional: {$contexto}\n";
            }
            $contextoCompleto .= "Tipo de exercício: {$tipo}\n";
            $contextoCompleto .= "Quantidade: {$quantidade} exercícios\n";
            if (!empty($niveis)) {
                $contextoCompleto .= "Níveis de dificuldade: " . implode(', ', $niveis) . "\n";
            }

            $tipoQuestaoBlueprint = [
                'alternativas' => 'multipla_escolha',
                'verdadeiro_falso' => 'verdadeiro_falso',
                'dissertativa' => 'dissertativa',
            ][$tipo] ?? 'multipla_escolha';

            $niveisComQtd = array_filter($quantidadesPorNivel);
            $dificuldadeBase = count($niveisComQtd) === 1 ? array_key_first($niveisComQtd) : 'medio';
            $distribuicaoTexto = '';
            if (!empty($niveisComQtd)) {
                $labelsNivel = ['facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil', 'desafio' => 'Desafio'];
                $partes = [];
                foreach ($niveisComQtd as $nivelChave => $qtd) {
                    $partes[] = "{$labelsNivel[$nivelChave]}: {$qtd}";
                }
                $distribuicaoTexto = 'Distribuição por dificuldade solicitada: ' . implode(', ', $partes) . '. ';
            }

            require_once __DIR__ . '/../../AI/GeradorQuestaoService.php';

            $jobId = \App\AI\GeradorQuestaoService::solicitar([
                'disciplina' => $modulo['materia_nome'] ?? 'Geral',
                'assunto' => mb_substr($modulo['jornada_titulo'] . '. ' . $contexto, 0, 200, 'UTF-8'),
                'serie' => $serie,
                'dificuldade' => $dificuldadeBase,
                'tipo_questao' => $tipoQuestaoBlueprint,
                'quantidade' => $quantidade,
                'quantidade_alternativas' => 5,
                'com_recurso_visual' => $comImagens ? 'auto' : false,
                'contexto_adicional' => $distribuicaoTexto . $contextoCompleto,
                'origem' => 'jornada',
                'usuario_id' => (int) $user['id'],
                'papel' => 'professor',
                'config' => $this->config,
                'modulo_id' => (int) $modulo_id,
                'tipo' => $tipo,
            ]);

            $this->json(['success' => true, 'job_id' => $jobId]);
        } catch (Exception $e) {
            error_log('Erro ao enfileirar exercício por IA (módulo): ' . $e->getMessage());
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../../Core/Logger.php';
            }
            Logger::error(
                'Falha ao enfileirar geração de exercícios com IA (módulo)',
                [
                    'trace_id' => $traceId,
                    'exception' => $e,
                    'user_id' => $userId,
                    'modulo_id' => $modulo_id,
                    'tipo' => $tipo,
                    'quantidade' => $quantidade,
                    'niveis' => $niveis,
                    'planos_aula_ids' => $planosAulaIds,
                    'contexto_len' => mb_strlen((string) $contexto),
                    'payload_excerpt' => mb_substr((string) $input, 0, 1200),
                ],
                'jornadas'
            );
            $this->json(['error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            error_log('Erro fatal ao enfileirar exercício por IA (módulo): ' . $e->getMessage());
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../../Core/Logger.php';
            }
            Logger::error(
                'Erro fatal ao enfileirar geração de exercícios com IA (módulo)',
                [
                    'trace_id' => $traceId,
                    'exception' => $e,
                    'user_id' => $userId,
                    'modulo_id' => $modulo_id,
                ],
                'jornadas'
            );
            $this->json(['error' => 'Falha interna ao gerar exercícios por IA. Ref: ' . $traceId], 500);
        }
    }

    /**
     * Persiste em jornadas_modulos_exercicios o resultado canônico do job
     * gerar_questao_ia (chamado pelo frontend após AIJobPoller.onDone).
     */
    public function importarExerciciosModuloIA($jobId)
    {
        try {
            $user = $this->authManager->getUser();

            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }

            require_once __DIR__ . '/../../Services/AIJobService.php';
            $job = \App\Services\AIJobService::getJob((int) $jobId);
            if (!$job || $job['status'] !== 'done') {
                $this->json(['error' => 'Job não concluído ou não encontrado'], 404);
                return;
            }

            if (($job['job_type'] ?? '') !== 'gerar_questao_ia') {
                $this->json(['error' => 'Tipo de job inválido para importação'], 422);
                return;
            }

            if ((int) ($job['user_id'] ?? 0) !== (int) $user['id']) {
                $this->json(['error' => 'Não autorizado'], 403);
                return;
            }

            $payload = json_decode($job['payload'], true) ?: [];
            $moduloId = (int) ($payload['modulo_id'] ?? 0);
            $tipo = $payload['tipo'] ?? 'alternativas';

            if ($moduloId <= 0 || ($payload['origem'] ?? '') !== 'jornada') {
                $this->json(['error' => 'Payload do job inválido para módulo de jornada'], 422);
                return;
            }

            $result = json_decode($job['result'], true) ?: [];
            if (!empty($result['imported_at'])) {
                $this->json([
                    'success' => true,
                    'exercicios_ids' => $result['exercicios_ids'] ?? [],
                    'already_imported' => true,
                ]);
                return;
            }

            $modulo = $this->db->fetch(
                "SELECT m.id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $moduloId, 'prof_id' => $user['id']]
            );
            if (!$modulo) {
                $this->json(['error' => 'Módulo não encontrado ou não autorizado'], 403);
                return;
            }

            $questoesCanonicas = $result['questoes'] ?? [];
            if (empty($questoesCanonicas)) {
                $this->json(['error' => 'Nenhuma questão gerada'], 422);
                return;
            }

            $ultimaOrdem = $this->db->fetch(
                "SELECT MAX(ordem) as max_ordem
                 FROM jornadas_modulos_exercicios
                 WHERE modulo_id = :modulo_id",
                ['modulo_id' => $moduloId]
            );
            $ordemBase = (int) ($ultimaOrdem['max_ordem'] ?? 0);

            $letrasMaiusculas = ['A', 'B', 'C', 'D', 'E'];
            $exerciciosIds = [];

            $this->db->beginTransaction();
            try {
                foreach ($questoesCanonicas as $index => $questao) {
                    $ordem = $ordemBase + $index + 1;
                    $enunciado = $questao['enunciado'] ?? '';
                    $respostaCorreta = null;

                    if ($tipo === 'alternativas') {
                        $opcoes = [];
                        foreach (array_slice($questao['alternativas'] ?? [], 0, 5) as $i => $alt) {
                            $letra = $letrasMaiusculas[$i];
                            $correta = !empty($alt['correta']);
                            $opcoes[] = [
                                'letra' => $letra,
                                'texto' => $alt['texto'] ?? '',
                                'correta' => $correta,
                            ];
                            if ($correta) {
                                $respostaCorreta = $letra;
                            }
                        }
                        $questoesJson = ['opcoes' => $opcoes];
                    } else {
                        $questoesJson = $questao;
                    }

                    $exercicioId = $this->db->insert(
                        "INSERT INTO jornadas_modulos_exercicios
                         (modulo_id, tipo, titulo, enunciado, imagem_url, questoes_json, resposta_correta, pontuacao, ordem, gerado_ia, status)
                         VALUES (:modulo_id, :tipo, :titulo, :enunciado, :imagem_url, :questoes_json, :resposta_correta, :pontuacao, :ordem, 1, 'publicado')",
                        [
                            'modulo_id' => $moduloId,
                            'tipo' => $tipo,
                            'titulo' => 'Questão ' . ($index + 1),
                            'enunciado' => $enunciado,
                            'imagem_url' => $questao['imagem']['url'] ?? null,
                            'questoes_json' => json_encode($questoesJson),
                            'resposta_correta' => $respostaCorreta,
                            'pontuacao' => 1.00,
                            'ordem' => $ordem,
                        ]
                    );
                    $exerciciosIds[] = $exercicioId;
                }

                $result['imported_at'] = date('c');
                $result['exercicios_ids'] = $exerciciosIds;
                $this->db->query(
                    "UPDATE ai_jobs SET result = ? WHERE id = ?",
                    [json_encode($result, JSON_UNESCAPED_UNICODE), (int) $jobId]
                );
                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

            $_SESSION['success_message'] = count($exerciciosIds) . ' exercício(s) gerado(s) com sucesso!';
            $this->json(['success' => true, 'exercicios_ids' => $exerciciosIds]);
        } catch (Exception $e) {
            error_log('Erro ao importar exercícios IA (módulo): ' . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Lê imagem e extrai exercício usando OCR
     */
    public function lerImagemExercicio($modulo_id)
    {
        // Limpa qualquer output anterior
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Desabilita exibição de erros para não quebrar o JSON
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', 0);
        
        try {
            $user = $this->authManager->getUser();
            
            // Verifica se o módulo pertence a uma jornada do professor
            $modulo = $this->db->fetch(
                "SELECT m.*, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );
            
            if (!$modulo) {
                throw new Exception('Módulo não encontrado ou não autorizado');
            }
            
            // Verifica se foi enviada uma imagem
            if (empty($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Nenhuma imagem foi enviada ou ocorreu um erro no upload');
            }
            
            $imagem = $_FILES['imagem'];
            
            // Valida tipo de arquivo
            $tiposPermitidos = ['image/png', 'image/jpeg', 'image/jpg'];
            if (!in_array($imagem['type'], $tiposPermitidos)) {
                throw new Exception('Tipo de arquivo não permitido. Use PNG, JPG ou JPEG');
            }
            
            // Valida tamanho (10MB)
            if ($imagem['size'] > 10 * 1024 * 1024) {
                throw new Exception('Imagem muito grande. Tamanho máximo: 10MB');
            }
            
            // Lê imagem e converte para base64
            $imageData = base64_encode(file_get_contents($imagem['tmp_name']));
            
            // Usa OpenAIService para transcrever com Google Vision
            require_once __DIR__ . '/../../Services/OpenAIService.php';
            $openaiService = new \App\Services\OpenAIService();
            
            // Transcreve a imagem
            $textoTranscrito = $openaiService->transcreverComGoogleVision($imageData);
            
            if (empty($textoTranscrito)) {
                throw new Exception('Não foi possível transcrever o texto da imagem');
            }
            
            // Usa IA para extrair enunciado e questões do texto transcrito
            $systemPrompt = 'Você é um assistente especializado em extrair questões de exercícios a partir de texto transcrito por OCR. 

Sua tarefa é analisar o texto transcrito e extrair:
1. O ENUNCIADO COMPLETO da questão (todo o texto antes das alternativas, incluindo o contexto e a pergunta)
2. As alternativas (A, B, C, D, E, etc.) se for múltipla escolha
3. NÃO marque nenhuma alternativa como correta (sempre false) - o professor vai marcar depois
4. O tipo de questão (alternativas para múltipla escolha, verdadeiro_falso ou dissertativa)

REGRAS IMPORTANTES:
- O enunciado deve incluir TODO o texto antes das alternativas, incluindo contexto, explicações e a pergunta final
- Se houver texto como "QUESTÃO 180" ou números, remova apenas esses identificadores, mas mantenha todo o conteúdo da questão
- Para múltipla escolha, extraia TODAS as alternativas encontradas (A, B, C, D, E, etc.)
- Cada alternativa deve conter apenas o texto da opção, sem o prefixo "A)", "B)", etc.
- SEMPRE marque todas as alternativas como "correta": false - o professor vai selecionar a correta depois
- Limpe artefatos de OCR (quebras de linha estranhas no meio de frases, caracteres especiais)
- Mantenha parágrafos e quebras de linha naturais do texto

Retorne APENAS um JSON válido no seguinte formato:
{
  "enunciado": "texto completo do enunciado incluindo contexto e pergunta",
  "tipo": "alternativas",
  "alternativas": [
    {"texto": "texto da alternativa A sem o prefixo A)", "correta": false},
    {"texto": "texto da alternativa B sem o prefixo B)", "correta": false},
    {"texto": "texto da alternativa C sem o prefixo C)", "correta": false},
    {"texto": "texto da alternativa D sem o prefixo D)", "correta": false},
    {"texto": "texto da alternativa E sem o prefixo E)", "correta": false}
  ]
}';
            
            $userPrompt = "Extraia a questão do seguinte texto transcrito por OCR:\n\n" . $textoTranscrito;
            
            // Aumenta timeout para processamento de imagem
            set_time_limit(300);
            ini_set('max_execution_time', 300);
            
            $response = $openaiService->chatCompletion(
                [
                    [
                        'role' => 'user',
                        'content' => $userPrompt
                    ]
                ],
                $systemPrompt,
                'gpt-4o',
                0.3,
                4000
            );
            
            $content = $response['resposta'] ?? '';
            
            if (empty($content)) {
                error_log("Resposta vazia da IA ao processar imagem");
                throw new Exception('A IA não retornou nenhuma resposta. Tente novamente.');
            }
            
            // Garante que o conteúdo está em UTF-8 válido
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'auto');
            }
            
            // Limpar caracteres de controle
            $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);
            
            // Remove BOM UTF-8 se presente
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $content = substr($content, 3);
            }
            
            // Extrai JSON da resposta
            $jsonContent = null;
            
            // Tenta extrair de markdown code block
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $jsonContent = trim($matches[1]);
            } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $jsonContent = trim($matches[1]);
            } elseif (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $jsonContent = $matches[0];
            } else {
                $jsonContent = trim($content);
            }
            
            if (empty($jsonContent)) {
                throw new Exception('Não foi possível extrair a questão do texto. O formato da resposta não é válido.');
            }
            
            // Tenta decodificar JSON
            $exercicio = json_decode($jsonContent, true, 512, JSON_UNESCAPED_UNICODE);
            
            // Se falhar, tenta corrigir JSON comum
            if (json_last_error() !== JSON_ERROR_NONE) {
                $jsonContent = preg_replace('/,\s*}/', '}', $jsonContent);
                $jsonContent = preg_replace('/,\s*]/', ']', $jsonContent);
                $jsonContent = preg_replace('/,\s*,/', ',', $jsonContent);
                
                $exercicio = json_decode($jsonContent, true, 512, JSON_UNESCAPED_UNICODE);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Erro ao processar a resposta da IA: ' . json_last_error_msg() . '. Tente novamente.');
                }
            }
            
            if (!$exercicio || !is_array($exercicio)) {
                throw new Exception('A resposta da IA não está no formato esperado. Tente novamente.');
            }
            
            // Garante que o tipo seja válido
            if (!isset($exercicio['tipo']) || !in_array($exercicio['tipo'], ['alternativas', 'verdadeiro_falso', 'dissertativa'])) {
                $exercicio['tipo'] = 'alternativas';
            }
            
            // Garante que alternativas seja array
            if (!isset($exercicio['alternativas']) || !is_array($exercicio['alternativas'])) {
                $exercicio['alternativas'] = [];
            }
            
            // Garante que enunciado exista
            if (empty($exercicio['enunciado'])) {
                $exercicio['enunciado'] = $textoTranscrito;
            }
            
            $this->json([
                'success' => true,
                'message' => 'Exercício extraído com sucesso',
                'exercicio' => $exercicio,
                'texto_transcrito' => substr($textoTranscrito, 0, 200) . '...'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao ler imagem de exercício: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        } finally {
            // Restaura display_errors
            ini_set('display_errors', $oldDisplayErrors);
        }
    }
    
    /**
     * Remove exercício do módulo (Professor)
     */
    public function removerExercicioModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $exercicio_id = $_POST['exercicio_id'] ?? null;
            
            if (!$exercicio_id) {
                throw new Exception('ID do exercício é obrigatório');
            }
            
            // Verifica se o exercício pertence a uma jornada do professor
            $exercicio = $this->db->fetch(
                "SELECT e.*, j.professor_id
                 FROM jornadas_modulos_exercicios e
                 JOIN jornadas_modulos m ON e.modulo_id = m.id
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE e.id = :exercicio_id AND j.professor_id = :prof_id",
                ['exercicio_id' => $exercicio_id, 'prof_id' => $user['id']]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado ou não autorizado');
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
     * Upload de imagem do enunciado do exercício (Professor) - arquivo ou base64 (colagem)
     */
    public function uploadImagemExercicio()
    {
        try {
            $user = $this->authManager->getUser();
            if (!$user || ($user['tipo'] ?? '') !== 'professor') {
                $this->json(['error' => 'Não autorizado'], 403);
                return;
            }

            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $media = new MediaStorageService($this->config);
            $imageUrl = null;
            $filename = null;

            // Colagem: imagem em base64 no POST
            $base64 = $_POST['imagem_base64'] ?? '';
            if ($base64 !== '') {
                if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64, $m)) {
                    $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
                    $data = base64_decode($m[2], true);
                    if ($data !== false && strlen($data) > 0 && strlen($data) <= 10 * 1024 * 1024) {
                        $filename = 'exercicio_' . $user['id'] . '_' . time() . '_' . uniqid() . '.' . $ext;
                        $key = MediaStorageService::userKey('teacher', $user['id'], $filename);
                        $tmpPath = sys_get_temp_dir() . '/' . $filename;
                        if (file_put_contents($tmpPath, $data) !== false && $media->put('jornadas_exercicios', $key, $tmpPath, 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext))) {
                            $imageUrl = $this->buildStableJourneyExerciseMediaUrl($key);
                        }
                        @unlink($tmpPath);
                    }
                }
            }

            // Upload por arquivo
            if ($imageUrl === null && !empty($_FILES['imagem']['name'])) {
                $file = $_FILES['imagem'];
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($file['type'], $allowedTypes)) {
                    throw new Exception('Tipo não permitido. Use JPG, PNG, GIF ou WebP.');
                }
                if ($file['size'] > 10 * 1024 * 1024) {
                    throw new Exception('Arquivo muito grande. Máximo 10MB.');
                }
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'exercicio_' . $user['id'] . '_' . time() . '_' . uniqid() . '.' . $ext;
                $key = MediaStorageService::userKey('teacher', $user['id'], $filename);
                if ($media->put('jornadas_exercicios', $key, $file['tmp_name'], $file['type'])) {
                    $imageUrl = $this->buildStableJourneyExerciseMediaUrl($key);
                }
            }

            if ($imageUrl === null) {
                throw new Exception('Nenhuma imagem recebida. Escolha um arquivo ou cole uma imagem (Ctrl+V).');
            }

            $this->json(['success' => true, 'image_url' => $imageUrl]);
        } catch (Exception $e) {
            error_log("Erro ao fazer upload de imagem do exercício: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Busca dados de um exercício para edição (Professor)
     */
    public function buscarExercicioModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $exercicio_id = $_GET['exercicio_id'] ?? null;
            
            if (!$exercicio_id) {
                throw new Exception('ID do exercício é obrigatório');
            }
            
            // Verifica se o exercício pertence a uma jornada do professor
            $exercicio = $this->db->fetch(
                "SELECT e.*, j.professor_id
                 FROM jornadas_modulos_exercicios e
                 JOIN jornadas_modulos m ON e.modulo_id = m.id
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE e.id = :exercicio_id AND j.professor_id = :prof_id",
                ['exercicio_id' => $exercicio_id, 'prof_id' => $user['id']]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado ou não autorizado');
            }
            
            // Remove professor_id do resultado
            unset($exercicio['professor_id']);
            
            // Decodifica JSON se existir
            if (!empty($exercicio['questoes_json'])) {
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
            
            if (empty($exercicio['status'])) {
                $exercicio['status'] = 'publicado';
            }
            
            $this->json(['success' => true, 'exercicio' => $exercicio]);
            
        } catch (Exception $e) {
            error_log("Erro ao buscar exercício: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Atualiza exercício do módulo (Professor)
     */
    public function atualizarExercicioModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $exercicio_id = $_POST['exercicio_id'] ?? null;
            $titulo = trim($_POST['titulo'] ?? '');
            $enunciadoRaw = trim((string)($_POST['enunciado'] ?? ''));
            $enunciado = $enunciadoRaw;
            $questoes_json = $_POST['questoes_json'] ?? null;
            $resposta_correta = $_POST['resposta_correta'] ?? null;
            $gabarito = $_POST['gabarito'] ?? null;
            $pontuacao = $_POST['pontuacao'] ?? 1.00;
            $imagem_url = trim($_POST['imagem_url'] ?? '') ?: null;
            $nivel_dificuldade = trim($_POST['nivel_dificuldade'] ?? '') ?: null;
            if ($nivel_dificuldade && !in_array($nivel_dificuldade, ['facil', 'medio', 'dificil'], true)) {
                $nivel_dificuldade = null;
            }

            $enunciado = $this->persistInlineBase64ImagesInHtml($enunciado, (int)$user['id']);
            $enunciado = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($enunciado);
            $enunciadoTexto = trim((string) strip_tags($enunciadoRaw));
            $temImagemNoEnunciado = stripos((string) $enunciadoRaw, '<img') !== false;
            $temImagemManual = !empty($imagem_url);
            if (is_string($questoes_json)) {
                $questoes_json = json_decode($questoes_json, true);
            }
            if (is_array($questoes_json) && !empty($questoes_json['opcoes'])) {
                foreach ($questoes_json['opcoes'] as &$opcao) {
                    if (isset($opcao['texto']) && is_string($opcao['texto'])) {
                        $opcao['texto'] = $this->persistInlineBase64ImagesInHtml($opcao['texto'], (int)$user['id']);
                        $opcao['texto'] = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($opcao['texto']);
                    }
                }
                unset($opcao);
            }
            
            if (empty($exercicio_id) || (!$enunciadoTexto && !$temImagemNoEnunciado && !$temImagemManual)) {
                throw new Exception('ID e enunciado (texto ou imagem) são obrigatórios');
            }
            
            if (empty($titulo)) {
                if ($enunciadoTexto !== '') {
                    $titulo = mb_substr($enunciadoTexto, 0, 100);
                    if (mb_strlen($enunciadoTexto) > 100) {
                        $titulo .= '...';
                    }
                } else {
                    $titulo = 'Exercício com imagem';
                }
            }
            
            $exercicio = $this->db->fetch(
                "SELECT e.*, j.professor_id
                 FROM jornadas_modulos_exercicios e
                 JOIN jornadas_modulos m ON e.modulo_id = m.id
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE e.id = :exercicio_id AND j.professor_id = :prof_id",
                ['exercicio_id' => $exercicio_id, 'prof_id' => $user['id']]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado ou não autorizado');
            }
            
            // Se o sanitizer esvaziou o enunciado mas o usuário enviou conteúdo (ex.: fórmulas), usar texto puro ou valor atual do banco para não apagar
            if (trim((string) $enunciado) === '' && ($enunciadoTexto !== '' || $temImagemNoEnunciado || $temImagemManual)) {
                $enunciado = $enunciadoTexto !== '' ? $enunciadoTexto : (string) ($exercicio['enunciado'] ?? '');
            }
            
            // Preservar status atual se não for enviado ou for inválido (evita exercício "sumir" ao editar só enunciado/alternativas)
            $statusEnviado = trim((string) ($_POST['status'] ?? ''));
            $statusAtual = trim((string) ($exercicio['status'] ?? 'publicado'));
            $statusPermitidos = ['publicado', 'rascunho', 'arquivado'];
            $statusNormalizado = $statusEnviado !== '' ? strtolower($statusEnviado) : $statusAtual;
            if (!in_array($statusNormalizado, $statusPermitidos, true)) {
                $statusNormalizado = $statusAtual !== '' && in_array($statusAtual, $statusPermitidos, true) ? $statusAtual : 'publicado';
            }
            $status = $statusNormalizado;
            
            $this->db->update(
                "UPDATE jornadas_modulos_exercicios 
                 SET titulo = :titulo, enunciado = :enunciado, questoes_json = :questoes_json, 
                     resposta_correta = :resposta_correta, gabarito = :gabarito, pontuacao = :pontuacao, 
                     status = :status, imagem_url = :imagem_url, nivel_dificuldade = :nivel_dificuldade, updated_at = NOW()
                 WHERE id = :exercicio_id",
                [
                    'titulo' => $titulo,
                    'enunciado' => $enunciado,
                    'questoes_json' => $questoes_json ? json_encode($questoes_json) : null,
                    'resposta_correta' => $resposta_correta,
                    'gabarito' => $gabarito,
                    'pontuacao' => $pontuacao,
                    'status' => $status,
                    'imagem_url' => $imagem_url,
                    'nivel_dificuldade' => $nivel_dificuldade,
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
     * Alterna status de exercício (publicar/despublicar) (Professor)
     */
    public function alternarStatusExercicio()
    {
        try {
            $user = $this->authManager->getUser();
            $exercicio_id = $_POST['exercicio_id'] ?? null;
            
            if (!$exercicio_id) {
                throw new Exception('ID do exercício é obrigatório');
            }
            
            // Verifica se o exercício pertence a uma jornada do professor
            $exercicio = $this->db->fetch(
                "SELECT e.*, j.professor_id
                 FROM jornadas_modulos_exercicios e
                 JOIN jornadas_modulos m ON e.modulo_id = m.id
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE e.id = :exercicio_id AND j.professor_id = :prof_id",
                ['exercicio_id' => $exercicio_id, 'prof_id' => $user['id']]
            );
            
            if (!$exercicio) {
                throw new Exception('Exercício não encontrado ou não autorizado');
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
            
            $_SESSION['success_message'] = 'Status do exercício alterado com sucesso!';
            $this->json(['success' => true, 'novo_status' => $novoStatus]);
            
        } catch (Exception $e) {
            error_log("Erro ao alternar status do exercício: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Visualiza exercícios dos alunos (Professor)
     */
    public function exerciciosAlunos($jornada_id)
    {
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            error_log("=== exerciciosAlunos CHAMADO - jornada_id: $jornada_id (tipo: " . gettype($jornada_id) . ") ===");
        }
        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            error_log("=== exerciciosAlunos - REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . " ===");
        }
        try {
            $user = $this->authManager->getUser();
            if (!$user) {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("=== exerciciosAlunos - ERRO: Usuário não autenticado ===");
                }
                $this->redirect('/professor/login');
                return;
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== exerciciosAlunos - user_id: " . ($user['id'] ?? 'N/A') . " ===");
            }
            
            // Converte para int se necessário
            $jornada_id = (int)$jornada_id;
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== exerciciosAlunos - jornada_id convertido: $jornada_id ===");
            }
            
            // Verifica se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT j.*, m.nome as materia_nome, t.nome as turma_nome
                 FROM jornadas j
                 LEFT JOIN materias m ON j.materia_id = m.id
                 LEFT JOIN turmas t ON j.turma_id = t.id
                 WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
                ['jornada_id' => $jornada_id, 'prof_id' => $user['id']]
            );
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                error_log("=== exerciciosAlunos - jornada encontrada: " . ($jornada ? 'SIM' : 'NÃO') . " ===");
            
            }
            
            if (!$jornada) {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("=== exerciciosAlunos - ERRO: Jornada não encontrada para ID: $jornada_id, professor_id: " . $user['id'] . " ===");
                }
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("=== exerciciosAlunos - Redirecionando para /professor/jornadas ===");
                }
                $_SESSION['error_message'] = 'Jornada não encontrada';
                $this->redirect('/professor/jornadas');
                return;
            }
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                error_log("=== exerciciosAlunos - Jornada encontrada! Continuando processamento... ===");
            
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== exerciciosAlunos - Título da jornada: " . ($jornada['titulo'] ?? 'N/A') . " ===");
            }
            
            // Primeiro verifica se a jornada tem módulos de exercícios
            $temModulosExercicios = $this->db->fetch(
                "SELECT COUNT(*) as total 
                 FROM jornadas_modulos m
                 WHERE m.jornada_id = :jornada_id 
                 AND (m.tipo_modulo = 'exercicios' OR m.tipo_modulo = 'exercicio')
                 AND m.status = 'ativo'",
                ['jornada_id' => $jornada_id]
            );
            
            $temExercicios = ($temModulosExercicios['total'] ?? 0) > 0;
            
            // Busca TODOS os alunos das turmas da jornada (turma principal + turmas_selecionadas)
            $alunosExercicios = [];
            $estrutura = json_decode($jornada['estrutura'] ?? '{}', true);
            $turmasIds = !empty($estrutura['turmas_selecionadas']) && is_array($estrutura['turmas_selecionadas'])
                ? array_map('intval', $estrutura['turmas_selecionadas'])
                : [(int) $jornada['turma_id']];
            $turmasIds = array_unique(array_filter($turmasIds));
            if (empty($turmasIds)) {
                $turmasIds = [(int) $jornada['turma_id']];
            }
            $placeholders = implode(',', array_fill(0, count($turmasIds), '?'));
            // Nomes das turmas para exibir no topo (ex.: 1ºA, 1ºB, 2ºA, 2ºB, 3ºA)
            $turmasNomesRows = $this->db->fetchAll(
                "SELECT nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
                $turmasIds
            );
            $turmasNomesTexto = $turmasNomesRows ? implode(', ', array_column($turmasNomesRows, 'nome')) : ($jornada['turma_nome'] ?? 'Sem turma');
            $todosAlunos = $this->db->fetchAll(
                "SELECT 
                    a.id as aluno_id,
                    a.nome as aluno_nome,
                    a.ra as aluno_ra,
                    t.serie as serie,
                    t.nome as turma_nome
                 FROM alunos a
                 JOIN turmas t ON a.turma_id = t.id
                 WHERE a.turma_id IN ($placeholders) AND a.ativo = 1
                 ORDER BY t.nome ASC, a.nome ASC",
                $turmasIds
            );
            
            // Tempo apenas na etapa de exercícios por aluno (soma de tempo_gasto nos módulos tipo exercicios)
            $temposExercicioPorAluno = [];
            if ($temExercicios) {
                $temposEtapaExercicios = $this->db->fetchAll(
                    "SELECT jpa.aluno_id, COALESCE(SUM(jpa.tempo_gasto), 0) as tempo_segundos
                     FROM jornadas_progresso_alunos jpa
                     INNER JOIN jornadas_modulos m ON m.id = jpa.modulo_id AND m.jornada_id = jpa.jornada_id
                       AND (m.tipo_modulo = 'exercicios' OR m.tipo_modulo = 'exercicio')
                     WHERE jpa.jornada_id = :jornada_id AND jpa.atividade_tipo = 'modulo'
                     GROUP BY jpa.aluno_id",
                    ['jornada_id' => $jornada_id]
                );
                foreach ($temposEtapaExercicios as $row) {
                    $temposExercicioPorAluno[(int)$row['aluno_id']] = (int)($row['tempo_segundos'] ?? 0);
                }
            }
            
            if ($temExercicios) {
                // Para cada aluno, busca estatísticas de exercícios e status
                foreach ($todosAlunos as $aluno) {
                    $stats = $this->db->fetch(
                        "SELECT
                            COUNT(*) as total_exercicios,
                            SUM(CASE WHEN base.pontuacao > 0 THEN 1 ELSE 0 END) as acertos,
                            SUM(CASE WHEN base.pontuacao <= 0 THEN 1 ELSE 0 END) as erros,
                            COALESCE(SUM(base.pontuacao), 0) as nota_total,
                            COALESCE(AVG(base.pontuacao), 0) as nota_media,
                            COUNT(*) as total_respostas
                         FROM (
                            SELECT jpa.exercicio_modulo_id, MAX(COALESCE(jpa.pontuacao, 0)) as pontuacao
                            FROM jornadas_progresso_alunos jpa
                            WHERE jpa.aluno_id = :aluno_id
                              AND jpa.jornada_id = :jornada_id
                              AND jpa.atividade_tipo = 'exercicio_modulo'
                              AND jpa.resposta IS NOT NULL
                            GROUP BY jpa.exercicio_modulo_id
                         ) base",
                        [
                            'aluno_id' => $aluno['aluno_id'],
                            'jornada_id' => $jornada_id
                        ]
                    );
                    
                    // Verifica se visualizou a jornada
                    $visualizou = $this->db->fetch(
                        "SELECT 1 FROM jornadas_progresso_alunos 
                         WHERE aluno_id = :aluno_id 
                             AND jornada_id = :jornada_id 
                             AND atividade_tipo IS NULL 
                             AND status IN ('visualizado', 'iniciado', 'em_andamento', 'concluido')",
                        ['aluno_id' => $aluno['aluno_id'], 'jornada_id' => $jornada_id]
                    );
                    
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
                // Se não tem exercícios, ainda mostra todos os alunos com status de visualização
                foreach ($todosAlunos as $aluno) {
                    $visualizou = $this->db->fetch(
                        "SELECT 1 FROM jornadas_progresso_alunos 
                         WHERE aluno_id = :aluno_id 
                             AND jornada_id = :jornada_id 
                             AND atividade_tipo IS NULL 
                             AND status IN ('visualizado', 'iniciado', 'em_andamento', 'concluido')",
                        ['aluno_id' => $aluno['aluno_id'], 'jornada_id' => $jornada_id]
                    );
                    
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
            
            // Mantém exercicios para referência (pode ser usado em outras partes)
            $exercicios = [];
            
            // Etapas (módulos) da jornada: para cada uma, lista de alunos com status e tempo (conteúdo/dica) ou referência aos exercícios
            $modulosJornada = $this->db->fetchAll(
                "SELECT id, titulo, tipo_modulo, ordem FROM jornadas_modulos 
                 WHERE jornada_id = :jornada_id AND (status = 'ativo' OR status IS NULL) 
                 ORDER BY ordem ASC",
                ['jornada_id' => $jornada_id]
            );
            $etapas = [];
            foreach ($modulosJornada as $mod) {
                $progressoPorAluno = $this->db->fetchAll(
                    "SELECT aluno_id, status, tempo_gasto 
                     FROM jornadas_progresso_alunos 
                     WHERE jornada_id = :jornada_id AND modulo_id = :modulo_id AND atividade_tipo = 'modulo'",
                    ['jornada_id' => $jornada_id, 'modulo_id' => $mod['id']]
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
            
            // Busca redações da jornada
            $redacoes = $this->db->fetchAll(
                "SELECT jr.*, 
                    (SELECT COUNT(*) FROM jornadas_redacoes_alunos jra 
                     WHERE jra.jornada_redacao_id = jr.id) as total_alunos,
                    (SELECT COUNT(*) FROM jornadas_redacoes_alunos jra 
                     WHERE jra.jornada_redacao_id = jr.id 
                     AND jra.correcao_professor_feita = 0 
                     AND jra.status IN ('entregue', 'corrigida_ia')) as pendentes_correcao
                 FROM jornadas_redacoes jr
                 WHERE jr.jornada_id = :jornada_id
                 ORDER BY jr.created_at DESC",
                ['jornada_id' => $jornada_id]
            );
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                    error_log("DEBUG exerciciosAlunos - Total de temas de redação encontrados: " . count($redacoes));
            
                }
            
            }
            
            // Uma única query para todos os alunos que fizeram redação (evita N+1)
            $alunosPorRedacao = [];
            if (!empty($redacoes)) {
                $redacaoIds = array_column($redacoes, 'id');
                $placeholders = implode(',', array_fill(0, count($redacaoIds), '?'));
                $allAlunosRedacoes = $this->db->fetchAll(
                    "SELECT 
                        jra.id as jra_id,
                        jra.jornada_redacao_id,
                        jra.redacao_id,
                        jra.aluno_id,
                        jra.status,
                        jra.versao,
                        jra.correcao_professor_feita,
                        jra.usar_media_notas,
                        jra.nota_media,
                        jra.nota_final_utilizada,
                        jra.created_at as jra_created_at,
                        jra.updated_at as jra_updated_at,
                        a.nome as aluno_nome, 
                        a.ra as aluno_ra, 
                        r.titulo as redacao_titulo, 
                        r.id as redacao_id,
                        r.eh_rascunho,
                        r.nota_final,
                        r.nota_final_professor,
                        r.usar_media_notas as r_usar_media_notas,
                        r.nota_media as r_nota_media,
                        r.nota_final_utilizada as r_nota_final_utilizada
                     FROM jornadas_redacoes_alunos jra
                     JOIN alunos a ON jra.aluno_id = a.id
                     JOIN redacoes r ON jra.redacao_id = r.id
                     WHERE jra.jornada_redacao_id IN ($placeholders)
                     ORDER BY jra.jornada_redacao_id, a.nome ASC, jra.versao ASC",
                    $redacaoIds
                );
                foreach ($allAlunosRedacoes as $row) {
                    $rid = (int) $row['jornada_redacao_id'];
                    if (!isset($alunosPorRedacao[$rid])) {
                        $alunosPorRedacao[$rid] = [];
                    }
                    $alunosPorRedacao[$rid][] = $row;
                }
            }

            foreach ($redacoes as &$redacao) {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                        error_log("DEBUG exerciciosAlunos - Processando tema de redação ID: {$redacao['id']}");
                    }
                }
                $alunosQueFizeram = $alunosPorRedacao[(int) $redacao['id']] ?? [];
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
                        $visualizou = $this->db->fetch(
                            "SELECT 1 FROM jornadas_progresso_alunos 
                             WHERE aluno_id = :aluno_id 
                                 AND jornada_id = :jornada_id 
                                 AND atividade_tipo IS NULL 
                                 AND status IN ('visualizado', 'iniciado', 'em_andamento', 'concluido')",
                            ['aluno_id' => $aluno['aluno_id'], 'jornada_id' => $jornada_id]
                        );
                        
                        $redacao['alunos'][] = [
                            'aluno_id' => $aluno['aluno_id'],
                            'aluno_nome' => $aluno['aluno_nome'],
                            'aluno_ra' => $aluno['aluno_ra'],
                            'status_redacao' => $visualizou ? 'viu' : 'nao_viu',
                            'redacao_titulo' => null,
                            'status' => null,
                            'versao' => null,
                            'nota_final' => null
                        ];
                    }
                }
            }
            
            // Busca módulos de resumo
            $modulosResumo = $this->db->fetchAll(
                "SELECT m.* FROM jornadas_modulos m
                 WHERE m.jornada_id = :jornada_id 
                 AND m.tipo_modulo = 'resumo_aluno'
                 ORDER BY m.ordem ASC",
                ['jornada_id' => $jornada_id]
            );
            
            // Para cada módulo de resumo, busca TODOS os alunos (com status)
            $resumos = [];
            foreach ($modulosResumo as $modulo) {
                // Busca alunos que fizeram resumo
                $alunosQueFizeram = $this->db->fetchAll(
                    "SELECT jra.*, a.nome as aluno_nome, a.ra as aluno_ra, m.titulo as modulo_titulo
                     FROM jornadas_resumos_alunos jra
                     JOIN alunos a ON jra.aluno_id = a.id
                     JOIN jornadas_modulos m ON jra.modulo_id = m.id
                     WHERE jra.modulo_id = :modulo_id
                     ORDER BY a.nome ASC",
                    ['modulo_id' => $modulo['id']]
                );
                
                // Cria um mapa de alunos que fizeram
                $alunosFizeramMap = [];
                foreach ($alunosQueFizeram as $alunoResumo) {
                    $alunosFizeramMap[$alunoResumo['aluno_id']] = $alunoResumo;
                }
                
                // Para cada aluno da turma, verifica status
                $alunosResumo = [];
                foreach ($todosAlunos as $aluno) {
                    if (isset($alunosFizeramMap[$aluno['aluno_id']])) {
                        // Aluno fez resumo
                        $alunoResumo = $alunosFizeramMap[$aluno['aluno_id']];
                        $alunoResumo['status_resumo'] = 'fez';
                        $alunosResumo[] = $alunoResumo;
                    } else {
                        // Aluno não fez - verifica se visualizou
                        $visualizou = $this->db->fetch(
                            "SELECT 1 FROM jornadas_progresso_alunos 
                             WHERE aluno_id = :aluno_id 
                                 AND jornada_id = :jornada_id 
                                 AND atividade_tipo IS NULL 
                                 AND status IN ('visualizado', 'iniciado', 'em_andamento', 'concluido')",
                            ['aluno_id' => $aluno['aluno_id'], 'jornada_id' => $jornada_id]
                        );
                        
                        $alunosResumo[] = [
                            'aluno_id' => $aluno['aluno_id'],
                            'aluno_nome' => $aluno['aluno_nome'],
                            'aluno_ra' => $aluno['aluno_ra'],
                            'modulo_titulo' => $modulo['titulo'],
                            'status_resumo' => $visualizou ? 'viu' : 'nao_viu',
                            'status' => null,
                            'created_at' => null
                        ];
                    }
                }
                
                $resumos[] = [
                    'modulo' => $modulo,
                    'alunos' => $alunosResumo
                ];
            }
            
            // Lista plana de alunos para a aba Resumo (mesmo layout da aba Exercícios): Nome, Série, Status, Nota, botão Resumo
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
            
            // Busca alunos da turma
            $alunos = $this->db->fetchAll(
                "SELECT a.* FROM alunos a
                 WHERE a.turma_id = :turma_id
                 ORDER BY a.nome ASC",
                ['turma_id' => $jornada['turma_id']]
            );
            
            $data = [
                'title' => 'Resultados da Jornada - ' . $jornada['titulo'] . ' - EducaTudo',
                'user' => $user,
                'jornada' => $jornada,
                'turmas_nomes_texto' => $turmasNomesTexto,
                'exercicios' => $exercicios,
                'alunosExercicios' => $alunosExercicios,
                'temExercicios' => $temExercicios,
                'etapas' => $etapas,
                'redacoes' => $redacoes,
                'resumos' => $resumos,
                'alunosResumos' => $alunosResumos,
                'alunos' => $alunos,
                'current_page' => 'journeys'
            ];
            
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            
                error_log("=== exerciciosAlunos - Preparando dados para renderizar view ===");
            
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== exerciciosAlunos - Total de alunos: " . count($alunosExercicios) . " ===");
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== exerciciosAlunos - Total de redações: " . count($redacoes) . " ===");
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== exerciciosAlunos - Total de resumos: " . count($resumos) . " ===");
            }
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== exerciciosAlunos - Chamando viewWithLayout ===");
            }
            $this->viewWithLayout('professor', 'teacher/journeys/exercicios-alunos', $data);
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== exerciciosAlunos - viewWithLayout concluído ===");
            }
            
        } catch (Exception $e) {
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== exerciciosAlunos - EXCEÇÃO CAPTURADA ===");
            }
            error_log("Erro em exerciciosAlunos: " . $e->getMessage());
            error_log("Arquivo: " . $e->getFile());
            error_log("Linha: " . $e->getLine());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            $_SESSION['error_message'] = 'Erro ao carregar exercícios: ' . $e->getMessage();
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== exerciciosAlunos - Redirecionando para /professor/jornadas devido à exceção ===");
            }
            $this->redirect('/professor/jornadas');
        }
    }
    
    /**
     * Visualiza exercícios detalhados de um aluno específico
     */
    public function verExerciciosAluno($jornada_id, $aluno_id)
    {
        try {
            error_log("verExerciciosAluno chamado com jornada_id: $jornada_id (tipo: " . gettype($jornada_id) . "), aluno_id: $aluno_id (tipo: " . gettype($aluno_id) . ")");
            $user = $this->authManager->getUser();
            error_log("verExerciciosAluno - user_id: " . ($user['id'] ?? 'N/A'));
            
            // Converte para int se necessário
            $jornada_id = (int)$jornada_id;
            $aluno_id = (int)$aluno_id;
            
            // Verifica se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT j.*, m.nome as materia_nome, t.nome as turma_nome
                 FROM jornadas j
                 LEFT JOIN materias m ON j.materia_id = m.id
                 LEFT JOIN turmas t ON j.turma_id = t.id
                 WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
                ['jornada_id' => $jornada_id, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                error_log("verExerciciosAluno - Jornada não encontrada: jornada_id=$jornada_id, prof_id=" . $user['id']);
                $_SESSION['error_message'] = 'Jornada não encontrada';
                $this->redirect('/professor/jornadas');
                return;
            }
            
            // Busca dados do aluno (por id); valida turma depois (jornada pode ter várias turmas em turmas_selecionadas)
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome, t.serie as serie
                 FROM alunos a
                 JOIN turmas t ON a.turma_id = t.id
                 WHERE a.id = :aluno_id",
                ['aluno_id' => $aluno_id]
            );
            $turmasJornada = [(int) $jornada['turma_id']];
            if (!empty($jornada['estrutura'])) {
                $estrutura = json_decode($jornada['estrutura'], true);
                if (!empty($estrutura['turmas_selecionadas']) && is_array($estrutura['turmas_selecionadas'])) {
                    $turmasJornada = array_unique(array_merge($turmasJornada, array_map('intval', $estrutura['turmas_selecionadas'])));
                }
            }
            if (!$aluno || !in_array((int) $aluno['turma_id'], $turmasJornada, true)) {
                error_log("verExerciciosAluno - Aluno não encontrado ou não pertence às turmas da jornada: aluno_id=$aluno_id");
                $_SESSION['error_message'] = 'Aluno não encontrado';
                $this->redirect('/professor/jornadas/' . $jornada_id . '/exercicios-alunos');
                return;
            }
            
            // Busca todos os exercícios da jornada com as respostas do aluno
            // Primeiro tenta buscar da estrutura nova (módulos)
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
            
            // Se não encontrar na estrutura nova, busca na estrutura antiga
            if (empty($exercicios)) {
                $exerciciosAntigos = $this->db->fetchAll(
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
                
                if (!empty($exerciciosAntigos)) {
                    $exercicios = $exerciciosAntigos;
                }
            }
            
            // Debug: log se não encontrar exercícios
            if (empty($exercicios)) {
                error_log("Nenhum exercício encontrado para jornada_id: $jornada_id, aluno_id: $aluno_id");
                
                // Verifica se há módulos de exercícios
                $modulosCheck = $this->db->fetchAll(
                    "SELECT m.* FROM jornadas_modulos m
                     WHERE m.jornada_id = :jornada_id 
                     AND (m.tipo_modulo = 'exercicios' OR m.tipo_modulo = 'exercicio')",
                    ['jornada_id' => $jornada_id]
                );
                error_log("Módulos de exercícios encontrados: " . count($modulosCheck));
                
                // Verifica se há exercícios na estrutura antiga
                $exerciciosAntigosCheck = $this->db->fetchAll(
                    "SELECT * FROM jornadas_exercicios WHERE jornada_id = :jornada_id",
                    ['jornada_id' => $jornada_id]
                );
                error_log("Exercícios antigos encontrados: " . count($exerciciosAntigosCheck));
            }
            
            // Calcula estatísticas
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
                'title' => 'Prova Detalhada - ' . $aluno['nome'] . ' - EducaTudo',
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
                'csrf_token' => $this->generateCsrfToken()
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/exercicios-aluno-detalhado', $data);
            
        } catch (Exception $e) {
            error_log("Erro em verExerciciosAluno: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            $_SESSION['error_message'] = 'Erro ao carregar prova: ' . $e->getMessage();
            $this->redirect('/professor/jornadas/' . $jornada_id . '/exercicios-alunos');
        }
    }
    
    /**
     * Atualiza ordem dos módulos (Professor)
     */
    public function atualizarOrdemModulos()
    {
        try {
            $user = $this->authManager->getUser();
            $jornada_id = $_POST['jornada_id'] ?? null;
            $ordens = $_POST['ordens'] ?? [];
            
            if (!$jornada_id || empty($ordens)) {
                throw new Exception('Dados inválidos');
            }
            
            // Verifica se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT id FROM jornadas WHERE id = :jornada_id AND professor_id = :prof_id",
                ['jornada_id' => $jornada_id, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada ou não autorizada');
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
     * Escolher qual correção mostrar (IA ou professor)
     */
    public function escolherCorrecao()
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }
            
            $redacaoId = $_POST['redacao_id'] ?? null;
            $usarCorrecaoProfessor = isset($_POST['usar_correcao_professor']) && $_POST['usar_correcao_professor'] == '1';
            
            if (!$redacaoId) {
                $this->json(['error' => 'ID da redação é obrigatório'], 400);
                return;
            }
            
            // Verificar se a redação pertence a uma jornada do professor
            $redacao = $this->db->fetch(
                "SELECT r.*, j.professor_id
                 FROM redacoes r
                 INNER JOIN jornadas_redacoes_alunos jra ON r.id = jra.redacao_id
                 INNER JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                 INNER JOIN jornadas j ON jr.jornada_id = j.id
                 WHERE r.id = :redacao_id AND j.professor_id = :prof_id",
                ['redacao_id' => $redacaoId, 'prof_id' => $user['id']]
            );
            
            if (!$redacao) {
                $this->json(['error' => 'Redação não encontrada'], 404);
                return;
            }
            
            // Atualizar escolha
            $this->db->update(
                "UPDATE redacoes SET usar_correcao_professor = :usar WHERE id = :redacao_id",
                [
                    'usar' => $usarCorrecaoProfessor ? 1 : 0,
                    'redacao_id' => $redacaoId
                ]
            );
            
            $this->db->update(
                "UPDATE jornadas_redacoes_alunos SET usar_correcao_professor = :usar WHERE redacao_id = :redacao_id",
                [
                    'usar' => $usarCorrecaoProfessor ? 1 : 0,
                    'redacao_id' => $redacaoId
                ]
            );
            
            $this->json([
                'success' => true,
                'message' => 'Escolha de correção atualizada com sucesso'
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Retornar redação para aluno reescrever
     */
    public function retornarParaReescrever()
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }
            
            $redacaoId = $_POST['redacao_id'] ?? null;
            $observacoes = trim($_POST['observacoes'] ?? '');
            
            if (!$redacaoId) {
                $this->json(['error' => 'ID da redação é obrigatório'], 400);
                return;
            }
            
            // Verificar se a redação pertence a uma jornada do professor
            $redacao = $this->db->fetch(
                "SELECT r.*, j.professor_id
                 FROM redacoes r
                 INNER JOIN jornadas_redacoes_alunos jra ON r.id = jra.redacao_id
                 INNER JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                 INNER JOIN jornadas j ON jr.jornada_id = j.id
                 WHERE r.id = :redacao_id AND j.professor_id = :prof_id",
                ['redacao_id' => $redacaoId, 'prof_id' => $user['id']]
            );
            
            if (!$redacao) {
                $this->json(['error' => 'Redação não encontrada'], 404);
                return;
            }
            
            // Atualizar status para retornada
            $this->db->update(
                "UPDATE redacoes SET 
                    retornada_para_reescrever = 1,
                    observacoes_professor = :observacoes
                 WHERE id = :redacao_id",
                [
                    'observacoes' => $observacoes,
                    'redacao_id' => $redacaoId
                ]
            );
            
            $this->db->update(
                "UPDATE jornadas_redacoes_alunos SET 
                    status = 'retornada',
                    retornada_para_reescrever = 1,
                    observacoes_professor = :observacoes,
                    updated_at = NOW()
                 WHERE redacao_id = :redacao_id",
                [
                    'observacoes' => $observacoes,
                    'redacao_id' => $redacaoId
                ]
            );
            
            $this->json([
                'success' => true,
                'message' => 'Redação retornada para reescrita com sucesso'
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ver redação do aluno (Professor)
     */
    public function verRedacaoJornada($jornadaId, $redacaoId)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Verificar se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :id AND professor_id = :prof_id",
                ['id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                $_SESSION['error_message'] = 'Jornada não encontrada';
                $this->redirect('/teacher/jornadas');
                return;
            }
            
            // Buscar redação diretamente primeiro (garante que sempre encontra os dados salvos)
            $redacao = $this->db->fetch(
                "SELECT r.*, 
                        r.competencia_1, r.competencia_2, r.competencia_3, r.competencia_4, r.competencia_5,
                        r.competencia_1_explicacao_professor, r.competencia_2_explicacao_professor,
                        r.competencia_3_explicacao_professor, r.competencia_4_explicacao_professor,
                        r.competencia_5_explicacao_professor,
                        r.nota_final, r.nota_final_professor, r.nota_final_utilizada,
                        r.usar_media_notas, r.nota_media,
                        r.comentarios_gerais_professor, r.sugestoes_melhoria_professor,
                        r.permitir_refazer,
                        r.mostrar_competencia_1_aluno, r.mostrar_competencia_2_aluno,
                        r.mostrar_competencia_3_aluno, r.mostrar_competencia_4_aluno,
                        r.mostrar_competencia_5_aluno, r.corrigida_por_professor,
                        r.feedback_ia,
                        r.id as redacao_id,
                        a.nome as aluno_nome, a.ra as aluno_ra
                 FROM redacoes r
                 INNER JOIN alunos a ON r.aluno_id = a.id
                 WHERE r.id = :redacao_id",
                ['redacao_id' => $redacaoId]
            );
            
            // Buscar dados da jornada e vínculo separadamente
            if ($redacao) {
                $jornadaRedacao = $this->db->fetch(
                    "SELECT jr.*, jr.tema_sugerido, jr.descricao_tema, jra.versao
                     FROM jornadas_redacoes jr
                     INNER JOIN jornadas_redacoes_alunos jra ON jr.id = jra.jornada_redacao_id
                     WHERE jra.redacao_id = :redacao_id AND jr.jornada_id = :jornada_id
                     ORDER BY jra.id DESC
                     LIMIT 1",
                    ['redacao_id' => $redacaoId, 'jornada_id' => $jornadaId]
                );
                
                if ($jornadaRedacao) {
                    $redacao['tema_sugerido'] = $jornadaRedacao['tema_sugerido'];
                    $redacao['descricao_tema'] = $jornadaRedacao['descricao_tema'];
                    $redacao['versao'] = $jornadaRedacao['versao'] ?? 1;
                } else {
                    // Se não encontrou vínculo, buscar tema da jornada diretamente
                    $jornadaRedacaoAlt = $this->db->fetch(
                        "SELECT jr.*, jr.tema_sugerido, jr.descricao_tema
                         FROM jornadas_redacoes jr
                         WHERE jr.jornada_id = :jornada_id
                         LIMIT 1",
                        ['jornada_id' => $jornadaId]
                    );
                    
                    if ($jornadaRedacaoAlt) {
                        $redacao['tema_sugerido'] = $jornadaRedacaoAlt['tema_sugerido'];
                        $redacao['descricao_tema'] = $jornadaRedacaoAlt['descricao_tema'];
                    }
                    $redacao['versao'] = 1;
                }
            }
            
            if (!$redacao) {
                $_SESSION['error_message'] = 'Redação não encontrada';
                $this->redirect('/teacher/jornadas/' . $jornadaId . '/redacoes');
                return;
            }
            
            // Decodificar feedback da IA se existir
            $feedbackIA = null;
            if ($redacao['feedback_ia']) {
                $feedbackIA = json_decode($redacao['feedback_ia'], true);
            }
            
            $data = [
                'title' => 'Ver Redação - ' . $redacao['aluno_nome'] . ' - EducaTudo',
                'jornada' => $jornada,
                'redacao' => $redacao,
                'feedback_ia' => $feedbackIA,
                'user' => $user,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            // Adicionar CSRF token
            $data['csrf_token'] = $this->generateCsrfToken();
            
            $this->viewWithLayout('professor', 'teacher/journeys/ver-redacao', $data);
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao carregar redação: ' . $e->getMessage();
            $this->redirect('/teacher/jornadas');
        }
    }
    
    /**
     * Permitir ou não que o aluno refaça a redação
     */
    public function permitirRefazerRedacao()
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $_SESSION['error_message'] = 'Token inválido';
                $this->redirect('/teacher/jornadas');
                return;
            }
            
            $redacaoId = $_POST['redacao_id'] ?? null;
            $jornadaId = $_POST['jornada_id'] ?? null;
            $permitirRefazer = isset($_POST['permitir_refazer']) && $_POST['permitir_refazer'] == '1';
            
            if (!$redacaoId || !$jornadaId) {
                $_SESSION['error_message'] = 'Dados inválidos';
                $this->redirect('/teacher/jornadas');
                return;
            }
            
            // Verificar se a redação pertence a uma jornada do professor
            $redacao = $this->db->fetch(
                "SELECT r.*, j.professor_id
                 FROM redacoes r
                 INNER JOIN jornadas_redacoes_alunos jra ON r.id = jra.redacao_id
                 INNER JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                 INNER JOIN jornadas j ON jr.jornada_id = j.id
                 WHERE r.id = :redacao_id AND j.professor_id = :prof_id
                 LIMIT 1",
                ['redacao_id' => $redacaoId, 'prof_id' => $user['id']]
            );
            
            if (!$redacao) {
                $_SESSION['error_message'] = 'Redação não encontrada ou não pertence ao professor';
                $this->redirect('/teacher/jornadas');
                return;
            }
            
            // Atualizar campo permitir_refazer
            $this->db->update(
                "UPDATE redacoes SET permitir_refazer = :permitir_refazer WHERE id = :redacao_id",
                [
                    'permitir_refazer' => $permitirRefazer ? 1 : 0,
                    'redacao_id' => $redacaoId
                ]
            );
            
            $_SESSION['success_message'] = $permitirRefazer 
                ? 'Aluno agora pode refazer esta redação' 
                : 'Aluno não pode mais refazer esta redação';
            
            // Redirecionar de volta para a tela de ver redação
            $this->redirect('/teacher/jornadas/' . $jornadaId . '/redacao/' . $redacaoId . '/ver');
            
        } catch (Exception $e) {
            error_log("Erro ao permitir refazer redação: " . $e->getMessage());
            $_SESSION['error_message'] = 'Erro ao atualizar permissão: ' . $e->getMessage();
            $this->redirect('/teacher/jornadas');
        }
    }
    
    /**
     * Corrigir redação do aluno (Professor) - Formulário
     */
    public function corrigirRedacaoJornadaForm($jornadaId, $redacaoId)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Verificar se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :id AND professor_id = :prof_id",
                ['id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                $_SESSION['error_message'] = 'Jornada não encontrada';
                $this->redirect('/teacher/jornadas');
                return;
            }
            
            // Buscar redação diretamente primeiro (garante que sempre encontra os dados salvos)
            $redacao = $this->db->fetch(
                "SELECT r.*, 
                        r.competencia_1, r.competencia_2, r.competencia_3, r.competencia_4, r.competencia_5,
                        r.competencia_1_explicacao_professor, r.competencia_2_explicacao_professor,
                        r.competencia_3_explicacao_professor, r.competencia_4_explicacao_professor,
                        r.competencia_5_explicacao_professor,
                        r.nota_final, r.nota_final_professor, r.nota_final_utilizada,
                        r.comentarios_gerais_professor, r.sugestoes_melhoria_professor,
                        r.mostrar_competencia_1_aluno, r.mostrar_competencia_2_aluno,
                        r.mostrar_competencia_3_aluno, r.mostrar_competencia_4_aluno,
                        r.mostrar_competencia_5_aluno, r.usar_media_notas, r.nota_media,
                        r.feedback_ia,
                        r.id as redacao_id,
                        a.nome as aluno_nome, a.ra as aluno_ra
                 FROM redacoes r
                 INNER JOIN alunos a ON r.aluno_id = a.id
                 WHERE r.id = :redacao_id",
                ['redacao_id' => $redacaoId]
            );
            
            // Buscar dados da jornada e vínculo separadamente
            if ($redacao) {
                $jornadaRedacao = $this->db->fetch(
                    "SELECT jr.id as jornada_redacao_id, jr.*, jr.tema_sugerido, jr.descricao_tema, jra.versao, jra.status as jra_status, jra.aluno_id
                     FROM jornadas_redacoes jr
                     INNER JOIN jornadas_redacoes_alunos jra ON jr.id = jra.jornada_redacao_id
                     WHERE jra.redacao_id = :redacao_id AND jr.jornada_id = :jornada_id
                     ORDER BY jra.id DESC
                     LIMIT 1",
                    ['redacao_id' => $redacaoId, 'jornada_id' => $jornadaId]
                );
                
                if ($jornadaRedacao) {
                    $redacao['tema_sugerido'] = $jornadaRedacao['tema_sugerido'];
                    $redacao['descricao_tema'] = $jornadaRedacao['descricao_tema'];
                    $redacao['versao'] = $jornadaRedacao['versao'] ?? 1;
                } else {
                    // Se não encontrou vínculo, buscar tema da jornada diretamente
                    $jornadaRedacaoAlt = $this->db->fetch(
                        "SELECT jr.*, jr.tema_sugerido, jr.descricao_tema
                         FROM jornadas_redacoes jr
                         WHERE jr.jornada_id = :jornada_id
                         LIMIT 1",
                        ['jornada_id' => $jornadaId]
                    );
                    
                    if ($jornadaRedacaoAlt) {
                        $redacao['tema_sugerido'] = $jornadaRedacaoAlt['tema_sugerido'];
                        $redacao['descricao_tema'] = $jornadaRedacaoAlt['descricao_tema'];
                    }
                    $redacao['versao'] = 1;
                }
            }
            
            if (!$redacao) {
                $_SESSION['error_message'] = 'Redação não encontrada';
                $this->redirect('/teacher/jornadas/' . $jornadaId . '/redacoes');
                return;
            }
            
            // Garantir que redacao_id existe
            if (!isset($redacao['redacao_id'])) {
                $redacao['redacao_id'] = $redacao['id'];
            }
            
            // Decodificar feedback da IA se existir
            $feedbackIA = null;
            if ($redacao['feedback_ia']) {
                $feedbackIA = json_decode($redacao['feedback_ia'], true);
            }
            
            // Buscar dados da versão anterior (versão imediatamente anterior) se existir
            $correcaoVersaoAnterior = null;
            $versaoAtual = $redacao['versao'] ?? 1;
            $versaoAnteriorNumero = null;
            if ($versaoAtual > 1 && isset($jornadaRedacao['jornada_redacao_id'])) {
                // Buscar a versão imediatamente anterior (versao_atual - 1) da mesma jornada_redacao_id e aluno_id
                $versaoAnteriorNumero = $versaoAtual - 1;
                $versaoAnterior = $this->db->fetch(
                    "SELECT r.*, jra.versao
                     FROM redacoes r
                     INNER JOIN jornadas_redacoes_alunos jra ON r.id = jra.redacao_id
                     WHERE jra.jornada_redacao_id = :jornada_redacao_id 
                     AND jra.aluno_id = :aluno_id 
                     AND jra.versao = :versao_anterior
                     LIMIT 1",
                    [
                        'jornada_redacao_id' => $jornadaRedacao['jornada_redacao_id'],
                        'aluno_id' => $jornadaRedacao['aluno_id'] ?? $redacao['aluno_id'],
                        'versao_anterior' => $versaoAnteriorNumero
                    ]
                );
                
                if ($versaoAnterior) {
                    $correcaoVersaoAnterior = [
                        'competencia_1' => $versaoAnterior['competencia_1'] ?? null,
                        'competencia_2' => $versaoAnterior['competencia_2'] ?? null,
                        'competencia_3' => $versaoAnterior['competencia_3'] ?? null,
                        'competencia_4' => $versaoAnterior['competencia_4'] ?? null,
                        'competencia_5' => $versaoAnterior['competencia_5'] ?? null,
                        'competencia_1_explicacao_professor' => $versaoAnterior['competencia_1_explicacao_professor'] ?? '',
                        'competencia_2_explicacao_professor' => $versaoAnterior['competencia_2_explicacao_professor'] ?? '',
                        'competencia_3_explicacao_professor' => $versaoAnterior['competencia_3_explicacao_professor'] ?? '',
                        'competencia_4_explicacao_professor' => $versaoAnterior['competencia_4_explicacao_professor'] ?? '',
                        'competencia_5_explicacao_professor' => $versaoAnterior['competencia_5_explicacao_professor'] ?? '',
                        'comentarios_gerais_professor' => $versaoAnterior['comentarios_gerais_professor'] ?? '',
                        'sugestoes_melhoria_professor' => $versaoAnterior['sugestoes_melhoria_professor'] ?? '',
                        'nota_final_professor' => $versaoAnterior['nota_final_professor'] ?? null,
                        'nota_final' => $versaoAnterior['nota_final'] ?? null
                    ];
                }
            }
            
            $data = [
                'title' => 'Corrigir Redação - ' . $redacao['aluno_nome'] . ' - EducaTudo',
                'jornada' => $jornada,
                'redacao' => $redacao,
                'feedback_ia' => $feedbackIA,
                'correcao_versao_anterior' => $correcaoVersaoAnterior,
                'versao_atual' => $versaoAtual,
                'versao_anterior_numero' => $versaoAnteriorNumero,
                'user' => $user,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/corrigir-redacao', $data);
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao carregar redação: ' . $e->getMessage();
            $this->redirect('/teacher/jornadas');
        }
    }
    
    /** POST: enfileira correção de redação de jornada pela IA (assíncrono) */
    public function corrigirRedacaoJornadaIA()
    {
        try {
            $user  = $this->authManager->getUser();
            $input = $_POST;
            if (empty($input) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
                $jsonInput = json_decode(file_get_contents('php://input'), true);
                if ($jsonInput) { $input = $jsonInput; }
            }

            if (!$this->verifyCsrfToken($input['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }

            $redacaoId = $input['redacao_id'] ?? null;
            if (!$redacaoId) {
                $this->json(['error' => 'ID da redação é obrigatório'], 400);
                return;
            }

            $redacao = $this->db->fetch(
                "SELECT r.*, j.professor_id
                 FROM redacoes r
                 INNER JOIN jornadas_redacoes_alunos jra ON r.id = jra.redacao_id
                 INNER JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                 INNER JOIN jornadas j ON jr.jornada_id = j.id
                 WHERE r.id = :redacao_id AND j.professor_id = :prof_id",
                ['redacao_id' => $redacaoId, 'prof_id' => $user['id']]
            );
            if (!$redacao) {
                $this->json(['error' => 'Redação não encontrada'], 404);
                return;
            }

            require_once __DIR__ . '/../../Services/AIJobService.php';
            $db = \Database::getInstance();
            $jobId = \App\Services\AIJobService::enqueue(
                'corrigir_redacao_jornada',
                ['redacao_id' => (int) $redacaoId],
                (int) $user['id'],
                'professor'
            );

            $baseUrl = defined('URL') ? URL : '';
            $statusUrl = $baseUrl . '/professor/jornadas/corrigir-redacao-ia/status/' . $jobId;

            $this->json([
                'success' => true,
                'job_id' => $jobId,
                'status_url' => $statusUrl,
                'debug' => [
                    'ai_jobs_table' => $db->tableExists('ai_jobs'),
                    'professor_id' => (int) $user['id'],
                    'redacao_id' => (int) $redacaoId,
                    'status_route' => '/professor/jornadas/corrigir-redacao-ia/status/' . $jobId,
                ],
            ]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Polling do status da correção por IA (mesmo controller/rota-base do enqueue).
     */
    public function corrigirRedacaoJornadaIAStatus($id)
    {
        if (class_exists('\Logger')) {
            \Logger::info('Polling status correção IA jornada', [
                'job_id' => (int) $id,
                'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                'user_id' => $_SESSION['user_id'] ?? null,
            ], 'ai_jobs');
        }
        require_once __DIR__ . '/../AIJobController.php';
        $controller = new \AIJobController();
        $controller->status($id);
    }

    /**
     * Gerencia módulos da jornada (Professor)
     */
    public function gerenciarModulos($id)
    {
        $user = $this->authManager->getUser();
        
        // Busca dados da jornada
        $jornada = $this->db->fetch(
            "SELECT j.*, 
                    t.nome as turma_nome,
                    m.nome as materia_nome
             FROM jornadas j
             JOIN turmas t ON j.turma_id = t.id
             LEFT JOIN materias m ON j.materia_id = m.id
             WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
            ['jornada_id' => $id, 'prof_id' => $user['id']]
        );
        
        if (!$jornada) {
            $_SESSION['error_message'] = 'Jornada não encontrada';
            $this->redirect('/teacher/jornadas');
            return;
        }
        
        $data = [
            'title' => 'Gerenciar Módulos - ' . $jornada['titulo'] . ' - EducaTudo',
            'user' => $user,
            'jornada' => $jornada,
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('professor', 'teacher/journeys/modulos', $data);
    }
    
    /**
     * Adiciona um módulo à jornada (Professor)
     */
    public function adicionarModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $jornada_id = $_POST['jornada_id'] ?? null;
            
            // CRÍTICO: Captura tipo_modulo de forma mais robusta
            $tipo_modulo = null;
            if (isset($_POST['tipo_modulo'])) {
                $tipo_modulo = is_array($_POST['tipo_modulo']) ? $_POST['tipo_modulo'][0] : $_POST['tipo_modulo'];
                $tipo_modulo = trim((string)$tipo_modulo);
                if ($tipo_modulo === '') {
                    $tipo_modulo = null;
                }
            }
            
            $titulo = $_POST['titulo'] ?? '';
            $obrigatorio = isset($_POST['obrigatorio']) ? 1 : 0;
            $ordem = $_POST['ordem'] ?? null;
            
            // Log dos dados recebidos - CRÍTICO para debug
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("=== ADICIONAR MÓDULO - DADOS RECEBIDOS ===");
            }
            error_log("jornada_id: " . ($jornada_id ?? 'NULL'));
            error_log("tipo_modulo (raw do _POST): " . var_export($_POST['tipo_modulo'] ?? 'NÃO EXISTE', true));
            error_log("tipo_modulo (processado): " . var_export($tipo_modulo, true));
            error_log("tipo_modulo (tipo): " . gettype($tipo_modulo));
            error_log("tipo_modulo (vazio?): " . (empty($tipo_modulo) ? 'SIM' : 'NÃO'));
            error_log("titulo (raw): " . var_export($titulo, true));
            error_log("obrigatorio: " . ($obrigatorio ? '1' : '0'));
            error_log("_POST completo: " . json_encode($_POST));
            
            // Validação rigorosa
            if (!$jornada_id) {
                error_log("ERRO: jornada_id está vazio!");
                throw new Exception('Jornada é obrigatória');
            }
            
            if (empty($tipo_modulo) || trim($tipo_modulo) === '') {
                error_log("ERRO: tipo_modulo está vazio ou inválido! Valor recebido: " . var_export($tipo_modulo, true));
                throw new Exception('Tipo de módulo é obrigatório');
            }
            
            // Verifica se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :jornada_id AND professor_id = :prof_id",
                ['jornada_id' => $jornada_id, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada ou não autorizada');
            }
            
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
            
            // Prepara o título final
            $tituloFinal = $titulo ? trim($titulo) : $this->getTituloPadraoModulo($tipo_modulo);
            
            // Log antes de inserir
            error_log("Adicionando módulo - tipo_modulo: '{$tipo_modulo}', titulo: '{$tituloFinal}'");
            error_log("Valores que serão inseridos: " . json_encode([
                'jornada_id' => $jornada_id,
                'aula_id' => null,
                'tipo_modulo' => $tipo_modulo,
                'titulo' => $tituloFinal,
                'descricao' => null,
                'ordem' => $ordem,
                'obrigatorio' => $obrigatorio
            ]));
            
            // CRÍTICO: Verifica o tipo de dado antes de inserir
            $tipoModuloType = gettype($tipo_modulo);
            $tipoModuloLength = strlen($tipo_modulo);
            error_log("tipo_modulo - tipo: {$tipoModuloType}, tamanho: {$tipoModuloLength}, valor: '{$tipo_modulo}'");
            
            // CRÍTICO: Tenta inserir usando PDO direto para debug
            try {
                $pdo = $this->db->getPdo();
                
                // Prepara statement manualmente para ter mais controle
                $stmt = $pdo->prepare(
                    "INSERT INTO jornadas_modulos 
                     (jornada_id, aula_id, tipo_modulo, titulo, descricao, ordem, obrigatorio, status, created_at) 
                     VALUES (:jornada_id, :aula_id, :tipo_modulo, :titulo, :descricao, :ordem, :obrigatorio, 'ativo', NOW())"
                );
                
                // Bind com tipo explícito
                $stmt->bindValue(':jornada_id', (int)$jornada_id, PDO::PARAM_INT);
                $stmt->bindValue(':aula_id', null, PDO::PARAM_NULL);
                $stmt->bindValue(':tipo_modulo', (string)$tipo_modulo, PDO::PARAM_STR); // FORÇA como STRING
                $stmt->bindValue(':titulo', (string)$tituloFinal, PDO::PARAM_STR);
                $stmt->bindValue(':descricao', null, PDO::PARAM_NULL);
                $stmt->bindValue(':ordem', (int)$ordem, PDO::PARAM_INT);
                $stmt->bindValue(':obrigatorio', (int)$obrigatorio, PDO::PARAM_INT);
                
                error_log("Valores bindados - tipo_modulo: '{$tipo_modulo}' (tipo: " . gettype($tipo_modulo) . ", tamanho: " . strlen($tipo_modulo) . ")");
                
                $stmt->execute();
                $moduloId = $pdo->lastInsertId();
                
                error_log("✅ INSERT executado com sucesso. ID retornado: {$moduloId}");
                
            } catch (PDOException $e) {
                error_log("❌ ERRO PDO na inserção: " . $e->getMessage());
                error_log("Código do erro: " . $e->getCode());
                error_log("Info do erro: " . print_r($e->errorInfo, true));
                throw $e;
            }
            
            error_log("Módulo inserido com ID: {$moduloId}");
            
            // Verifica se foi inserido corretamente - CRÍTICO para debug
            $moduloInserido = $this->db->fetch(
                "SELECT id, tipo_modulo, titulo, ordem FROM jornadas_modulos WHERE id = :id",
                ['id' => $moduloId]
            );
            
            if ($moduloInserido) {
                $tipoInserido = $moduloInserido['tipo_modulo'] ?? 'NULL';
                $tipoIsNull = is_null($moduloInserido['tipo_modulo']);
                $tipoVazio = $tipoInserido === '' || trim($tipoInserido) === '';
                
                error_log("Módulo inserido - ID: {$moduloId}, tipo_modulo no banco: " . ($tipoIsNull ? 'NULL' : "'{$tipoInserido}'") . ", titulo: '{$moduloInserido['titulo']}'");
                error_log("Valor que tentamos inserir: '{$tipo_modulo}'");
                
                // Se o tipo está NULL ou vazio no banco, isso é um problema crítico!
                if ($tipoIsNull || $tipoVazio) {
                    error_log("⚠️⚠️⚠️ ERRO CRÍTICO: tipo_modulo está NULL ou vazio no banco após inserção!");
                    error_log("Valor que tentamos inserir: '{$tipo_modulo}'");
                    error_log("Título inserido: '{$tituloFinal}'");
                    
                    // Tenta corrigir atualizando o registro com o valor que deveria ter sido inserido
                    if (!empty($tipo_modulo)) {
                        // FORÇA como string e tenta UPDATE direto
                        $tipoModuloForUpdate = (string)$tipo_modulo;
                        error_log("Tentando corrigir com UPDATE - valor: '{$tipoModuloForUpdate}', tipo: " . gettype($tipoModuloForUpdate));
                        
                        // Tenta UPDATE direto com SQL explícito
                        try {
                            $rowsAffected = $this->db->update(
                                "UPDATE jornadas_modulos SET tipo_modulo = :tipo_modulo WHERE id = :id",
                                ['tipo_modulo' => $tipoModuloForUpdate, 'id' => $moduloId]
                            );
                            error_log("UPDATE executado - linhas afetadas: {$rowsAffected}");
                            
                            // Verifica novamente
                            $moduloCorrigido = $this->db->fetch(
                                "SELECT tipo_modulo, CHAR_LENGTH(tipo_modulo) as tamanho FROM jornadas_modulos WHERE id = :id",
                                ['id' => $moduloId]
                            );
                            
                            if ($moduloCorrigido) {
                                $tipoAposCorrecao = $moduloCorrigido['tipo_modulo'] ?? 'AINDA NULL';
                                $tamanhoAposCorrecao = $moduloCorrigido['tamanho'] ?? 0;
                                error_log("Após correção, tipo_modulo: '{$tipoAposCorrecao}', tamanho: {$tamanhoAposCorrecao}");
                                
                                if ($tipoAposCorrecao === 'AINDA NULL' || trim($tipoAposCorrecao) === '' || $tamanhoAposCorrecao == 0) {
                                    error_log("❌ FALHA: Correção não funcionou! tipo_modulo ainda está vazio.");
                                    error_log("⚠️ POSSÍVEL CAUSA: A coluna tipo_modulo pode ter constraint/trigger ou ser ENUM sem o valor 'dica_professor'");
                                    
                                    // Tenta verificar a estrutura da coluna
                                    $colunaInfo = $this->db->fetch(
                                        "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
                                         FROM INFORMATION_SCHEMA.COLUMNS 
                                         WHERE TABLE_SCHEMA = DATABASE() 
                                         AND TABLE_NAME = 'jornadas_modulos' 
                                         AND COLUMN_NAME = 'tipo_modulo'"
                                    );
                                    if ($colunaInfo) {
                                        error_log("Estrutura da coluna tipo_modulo: " . json_encode($colunaInfo));
                                    }
                                } else {
                                    error_log("✅ SUCESSO: Correção funcionou! tipo_modulo agora é: '{$tipoAposCorrecao}'");
                                }
                            }
                        } catch (Exception $e) {
                            error_log("❌ ERRO ao tentar corrigir: " . $e->getMessage());
                        }
                    } else {
                        error_log("❌ Não foi possível corrigir: tipo_modulo está vazio também na variável PHP!");
                    }
                } else {
                    error_log("✅ Módulo inserido corretamente com tipo_modulo: '{$tipoInserido}'");
                }
            } else {
                error_log("ERRO: Módulo não encontrado após inserção - ID: {$moduloId}");
            }
            
            $_SESSION['success_message'] = 'Módulo adicionado com sucesso!';
            $this->json(['success' => true, 'modulo_id' => $moduloId]);
            
        } catch (Exception $e) {
            error_log("Erro ao adicionar módulo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Remove um módulo (Professor)
     */
    public function removerModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $modulo_id = $_POST['modulo_id'] ?? null;
            
            if (!$modulo_id) {
                throw new Exception('ID do módulo é obrigatório');
            }
            
            // Verifica se o módulo pertence a uma jornada do professor
            $modulo = $this->db->fetch(
                "SELECT m.* FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );
            
            if (!$modulo) {
                throw new Exception('Módulo não encontrado ou não autorizado');
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
     * Edita título e obrigatoriedade de um módulo (Professor)
     */
    public function editarModulo()
    {
        try {
            $user = $this->authManager->getUser();
            $modulo_id = (int)($_POST['modulo_id'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $obrigatorio = isset($_POST['obrigatorio']) ? 1 : 0;

            if ($modulo_id <= 0) {
                throw new Exception('ID do módulo é obrigatório');
            }

            if ($titulo === '') {
                throw new Exception('Título do bloco é obrigatório');
            }

            // Verifica se o módulo pertence a uma jornada do professor
            $modulo = $this->db->fetch(
                "SELECT m.id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
            );

            if (!$modulo) {
                throw new Exception('Módulo não encontrado ou não autorizado');
            }

            $this->db->update(
                "UPDATE jornadas_modulos
                 SET titulo = :titulo,
                     obrigatorio = :obrigatorio,
                     updated_at = NOW()
                 WHERE id = :modulo_id",
                [
                    'titulo' => $titulo,
                    'obrigatorio' => $obrigatorio,
                    'modulo_id' => $modulo_id
                ]
            );

            $this->json(['success' => true]);
        } catch (Exception $e) {
            error_log("Erro ao editar módulo: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Lista módulos da jornada (AJAX) (Professor)
     */
    public function listarModulos($id)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Verifica se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT * FROM jornadas WHERE id = :jornada_id AND professor_id = :prof_id",
                ['jornada_id' => $id, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada ou não autorizada');
            }
            
            // Busca módulos - Garante que tipo_modulo nunca seja NULL
            $modulos = $this->db->fetchAll(
                "SELECT m.id, m.jornada_id, m.aula_id, 
                        COALESCE(NULLIF(TRIM(m.tipo_modulo), ''), '') as tipo_modulo, 
                        m.titulo, m.descricao, m.ordem, m.obrigatorio, m.status, 
                        m.created_at, m.updated_at, ja.nome_aula as aula_nome
                 FROM jornadas_modulos m
                 LEFT JOIN jornadas_aulas ja ON m.aula_id = ja.id
                 WHERE m.jornada_id = :jornada_id AND m.status = 'ativo'
                 ORDER BY m.ordem ASC, m.created_at ASC",
                ['jornada_id' => $id]
            );
            
            // Log para debug - verifica se tipo_modulo está vindo do banco (valor RAW)
            error_log("Total de módulos encontrados: " . count($modulos));
            foreach ($modulos as $m) {
                $tipoRaw = $m['tipo_modulo'] ?? 'NULL';
                $tipoIsNull = is_null($m['tipo_modulo']);
                error_log("Módulo ID {$m['id']}: tipo_modulo RAW do banco = " . ($tipoIsNull ? 'NULL' : "'{$tipoRaw}'") . ", tipo é NULL? " . ($tipoIsNull ? 'SIM' : 'NÃO') . ", titulo = '{$m['titulo']}'");
            }
            
            // Garante que todos os campos estão presentes e limpos
            foreach ($modulos as &$modulo) {
                // CRÍTICO: Garante que tipo_modulo existe e está limpo
                // Se for NULL do banco, mantém como string vazia para o JavaScript processar
                if (!isset($modulo['tipo_modulo']) || $modulo['tipo_modulo'] === null) {
                    $modulo['tipo_modulo'] = '';
                    error_log("⚠️ Módulo ID {$modulo['id']}: tipo_modulo estava NULL no banco! Título: '{$modulo['titulo']}'");
                } else {
                    $modulo['tipo_modulo'] = trim((string)$modulo['tipo_modulo']);
                }
                
                // Log ANTES de qualquer processamento
                error_log("Módulo ID {$modulo['id']}: tipo_modulo após processamento='{$modulo['tipo_modulo']}', titulo='{$modulo['titulo']}'");
                
                // IMPORTANTE: NÃO sobrescreve o tipo baseado no título se o tipo já existe
                // O tipo do banco é a fonte da verdade
                // Só usa fallback se o tipo realmente estiver vazio
                if (empty($modulo['tipo_modulo']) && !empty($modulo['titulo'])) {
                    $tituloLower = strtolower(trim($modulo['titulo']));
                    if (strpos($tituloLower, 'dica do professor') !== false || 
                        strpos($tituloLower, 'dica professor') !== false) {
                        $modulo['tipo_modulo'] = 'dica_professor';
                        error_log("Módulo ID {$modulo['id']}: Tipo definido como 'dica_professor' baseado no título (FALLBACK): '{$modulo['titulo']}'");
                    }
                }
                
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
                
                // Log FINAL para debug
                error_log("Módulo ID {$modulo['id']}: tipo_modulo FINAL='{$modulo['tipo_modulo']}', titulo='{$modulo['titulo']}'");
            }
            unset($modulo);
            
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
     * Gerencia tema de redação de um módulo (Professor)
     */
    public function gerenciarRedacaoModulo($modulo_id)
    {
        $user = $this->authManager->getUser();
        
        // Busca o módulo
        $modulo = $this->db->fetch(
            "SELECT m.*, j.titulo as jornada_titulo, j.id as jornada_id, j.professor_id
             FROM jornadas_modulos m
             JOIN jornadas j ON m.jornada_id = j.id
             WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
            ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
        );
        
        if (!$modulo) {
            $_SESSION['error_message'] = 'Módulo não encontrado';
            $this->redirect('/teacher/jornadas');
            return;
        }
        
        if ($modulo['tipo_modulo'] !== 'redacao') {
            $_SESSION['error_message'] = 'Este módulo não é de redação';
            $this->redirect('/teacher/jornadas/' . $modulo['jornada_id']);
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
            "SELECT j.*, m.nome as materia_nome
             FROM jornadas j
             LEFT JOIN materias m ON j.materia_id = m.id
             WHERE j.id = :jornada_id",
            ['jornada_id' => $modulo['jornada_id']]
        );
        
        $data = [
            'title' => 'Gerenciar Tema de Redação - ' . $modulo['titulo'] . ' - EducaTudo',
            'user' => $user,
            'modulo' => $modulo,
            'jornada' => $jornada,
            'redacao_jornada' => $redacaoJornada,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'journeys'
        ];
        
        $this->viewWithLayout('professor', 'teacher/journeys/modulos-redacao', $data);
    }
    
    /**
     * Salva ou atualiza tema de redação do módulo (Professor)
     */
    public function salvarTemaRedacaoModulo()
    {
        // Log em arquivo separado para garantir que seja escrito
        $logDir = __DIR__ . '/../../storage/logs';
        $logFile = $logDir . '/documento_upload_' . date('Y-m-d') . '.log';
        
        // Garante que o diretório de logs existe
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $log = function($message) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            // Tenta escrever no arquivo, mas não falha se não conseguir
            @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
            error_log($message); // Sempre loga no error_log padrão
        };
        
        $log("=== INÍCIO salvarTemaRedacaoModulo ===");
        $log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
        $log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'N/A'));
        
        try {
            $user = $this->authManager->getUser();
            $log("DEBUG - Usuário autenticado: " . ($user['id'] ?? 'N/A'));
            
            // Debug: Verificar o que está chegando
            $log("DEBUG salvarTemaRedacaoModulo - _POST: " . json_encode($_POST));
            $log("DEBUG salvarTemaRedacaoModulo - _FILES: " . json_encode($_FILES));
            
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->json(['error' => 'Token inválido'], 400);
                return;
            }
            
            $modulo_id = $_POST['modulo_id'] ?? null;
            // V-02 XSS: Tema é texto puro; descrição/repertório é HTML rico (WYSIWYG com imagens) – sanitizar
            $tema = strip_tags(trim($_POST['tema'] ?? ''));
            $descricao = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages(trim($_POST['descricao'] ?? ''));
            $correcaoIAAutomatica = isset($_POST['correcao_ia_automatica']) ? intval($_POST['correcao_ia_automatica']) : 0;
            
            if (!$modulo_id || empty($tema)) {
                throw new Exception('Módulo e tema são obrigatórios');
            }
            
            // Busca o módulo e jornada
            $modulo = $this->db->fetch(
                "SELECT m.*, j.id as jornada_id, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON m.jornada_id = j.id
                 WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                ['modulo_id' => $modulo_id, 'prof_id' => $user['id']]
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
            
            // Processar upload de documento se fornecido
            $documentoTema = null;
            $log("DEBUG - Verificando upload de documento. _FILES['documento_tema'] existe: " . (isset($_FILES['documento_tema']) ? 'sim' : 'não'));
            if (isset($_FILES['documento_tema'])) {
                $log("DEBUG - Erro do upload: " . ($_FILES['documento_tema']['error'] ?? 'N/A'));
                $log("DEBUG - Nome do arquivo: " . ($_FILES['documento_tema']['name'] ?? 'N/A'));
                $log("DEBUG - Tamanho: " . ($_FILES['documento_tema']['size'] ?? 'N/A'));
                $log("DEBUG - Tipo: " . ($_FILES['documento_tema']['type'] ?? 'N/A'));
                $log("DEBUG - tmp_name: " . ($_FILES['documento_tema']['tmp_name'] ?? 'N/A'));
            }
            
            // Verificar erros de upload
            if (isset($_FILES['documento_tema'])) {
                $uploadError = $_FILES['documento_tema']['error'];
                $uploadErrorMessages = [
                    UPLOAD_ERR_OK => 'Nenhum erro',
                    UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo PHP (upload_max_filesize)',
                    UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido pelo formulário (MAX_FILE_SIZE)',
                    UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente',
                    UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
                    UPLOAD_ERR_NO_TMP_DIR => 'Falta a pasta temporária',
                    UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o arquivo no disco',
                    UPLOAD_ERR_EXTENSION => 'Uma extensão PHP interrompeu o upload'
                ];
                $log("DEBUG - Erro de upload: " . $uploadError . " - " . ($uploadErrorMessages[$uploadError] ?? 'Erro desconhecido'));
            }
            
            if (isset($_FILES['documento_tema']) && $_FILES['documento_tema']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'public/uploads/redacoes_temas/documentos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                    $log("DEBUG - Diretório criado: " . $uploadDir);
                }
                
                $file = $_FILES['documento_tema'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['pdf', 'doc', 'docx', 'txt', 'rtf'];
                
                $log("DEBUG - Extensão do arquivo: " . $ext);
                
                if (!in_array($ext, $allowedExts)) {
                    $log("DEBUG - Formato inválido: " . $ext);
                    throw new Exception('Formato de documento inválido. Use: PDF, DOC, DOCX, TXT ou RTF');
                }
                
                if ($file['size'] > 10 * 1024 * 1024) {
                    $log("DEBUG - Arquivo muito grande: " . $file['size']);
                    throw new Exception('Documento muito grande. Tamanho máximo: 10MB');
                }
                
                $fileName = uniqid() . '_' . time() . '.' . $ext;
                $filePath = $uploadDir . $fileName;
                
                $log("DEBUG - Tentando mover arquivo de " . $file['tmp_name'] . " para " . $filePath);
                $log("DEBUG - Arquivo existe? " . (file_exists($file['tmp_name']) ? 'sim' : 'não'));
                $log("DEBUG - Diretório destino existe? " . (is_dir($uploadDir) ? 'sim' : 'não'));
                $log("DEBUG - Diretório destino é gravável? " . (is_writable($uploadDir) ? 'sim' : 'não'));
                
                if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                    $log("DEBUG - ERRO ao mover arquivo! Erro PHP: " . error_get_last()['message'] ?? 'N/A');
                    throw new Exception('Erro ao fazer upload do documento');
                }
                
                $log("DEBUG - Arquivo movido com sucesso para: " . $filePath);
                $log("DEBUG - Arquivo existe após mover? " . (file_exists($filePath) ? 'sim' : 'não'));
                $documentoTema = $filePath;
            } else {
                if (isset($_FILES['documento_tema'])) {
                    $uploadError = $_FILES['documento_tema']['error'];
                    $log("DEBUG - Erro no upload: " . $uploadError);
                    
                    if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                        $maxSize = ini_get('upload_max_filesize');
                        $log("DEBUG - Arquivo muito grande! Limite PHP: " . $maxSize);
                        throw new Exception("O arquivo é muito grande. O limite máximo permitido pelo servidor é: " . $maxSize . ". Por favor, reduza o tamanho do arquivo ou entre em contato com o administrador para aumentar o limite.");
                    } elseif ($uploadError !== UPLOAD_ERR_NO_FILE) {
                        $errorMessages = [
                            UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente',
                            UPLOAD_ERR_NO_TMP_DIR => 'Falta a pasta temporária no servidor',
                            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o arquivo no disco',
                            UPLOAD_ERR_EXTENSION => 'Uma extensão PHP interrompeu o upload'
                        ];
                        $errorMsg = $errorMessages[$uploadError] ?? 'Erro desconhecido no upload';
                        $log("DEBUG - Erro de upload: " . $errorMsg);
                        throw new Exception("Erro ao fazer upload do documento: " . $errorMsg);
                    }
                } else {
                    $log("DEBUG - Nenhum documento enviado no formulário");
                }
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
                
                // IMPORTANTE: Sempre atualizar documento_tema, mesmo que seja null (para limpar se necessário)
                // Mas só atualizar se um novo documento foi enviado
                if (isset($_FILES['documento_tema'])) {
                    if ($documentoTema) {
                        $log("DEBUG - Documento será atualizado: " . $documentoTema);
                        // Remove documento antigo se existir
                        if ($redacaoExistente['documento_tema'] && file_exists($redacaoExistente['documento_tema'])) {
                            unlink($redacaoExistente['documento_tema']);
                            $log("DEBUG - Documento antigo removido");
                        }
                        $updateFields['documento_tema'] = $documentoTema;
                    } else {
                        // Se o arquivo foi enviado mas houve erro, manter o documento atual
                        $log("DEBUG - Arquivo enviado mas houve erro no processamento. Mantendo documento atual.");
                    }
                } else {
                    $log("DEBUG - Nenhum arquivo de documento foi enviado no formulário");
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
                
                $log("DEBUG - UPDATE SQL: " . "UPDATE jornadas_redacoes SET " . implode(', ', $setClause) . " WHERE id = :redacao_id");
                $log("DEBUG - Parâmetros UPDATE: " . json_encode($params));
                
                $this->db->update(
                    "UPDATE jornadas_redacoes SET " . implode(', ', $setClause) . " WHERE id = :redacao_id",
                    $params
                );
                
                // Verificar se foi salvo
                $verificacao = $this->db->fetch(
                    "SELECT documento_tema FROM jornadas_redacoes WHERE id = :id",
                    ['id' => $redacaoExistente['id']]
                );
                $log("DEBUG - Verificação pós-UPDATE - documento_tema salvo: " . ($verificacao['documento_tema'] ?? 'NULL'));
                
                $redacaoId = $redacaoExistente['id'];
            } else {
                // Cria nova redação da jornada
                $log("DEBUG - Criando nova redação. documento_tema: " . ($documentoTema ?? 'null'));
                $insertFields = ['jornada_id', 'professor_id', 'tema_sugerido', 'descricao_tema', 'imagem_tema', 'correcao_ia_automatica', 'status', 'created_at'];
                $insertValues = [':jornada_id', ':professor_id', ':tema', ':descricao', ':imagem_tema', ':correcao_ia_automatica', "'pendente'", 'NOW()'];
                $insertParams = [
                    'jornada_id' => $modulo['jornada_id'],
                    'professor_id' => $modulo['professor_id'],
                    'tema' => $tema,
                    'descricao' => $descricao ?: null,
                    'imagem_tema' => $imagemTema,
                    'correcao_ia_automatica' => $correcaoIAAutomatica
                ];
                
                // Adicionar documento_tema apenas se existir
                if ($documentoTema) {
                    $insertFields[] = 'documento_tema';
                    $insertValues[] = ':documento_tema';
                    $insertParams['documento_tema'] = $documentoTema;
                }
                
                $log("DEBUG - INSERT SQL: " . "INSERT INTO jornadas_redacoes (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")");
                $log("DEBUG - Parâmetros INSERT: " . json_encode($insertParams));
                
                $redacaoId = $this->db->insert(
                    "INSERT INTO jornadas_redacoes (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")",
                    $insertParams
                );
                $log("DEBUG - Redação criada com ID: " . $redacaoId);
                
                // Verificar se foi salvo
                if ($redacaoId) {
                    $verificacao = $this->db->fetch(
                        "SELECT documento_tema FROM jornadas_redacoes WHERE id = :id",
                        ['id' => $redacaoId]
                    );
                    $log("DEBUG - Verificação pós-INSERT - documento_tema salvo: " . ($verificacao['documento_tema'] ?? 'NULL'));
                }
            }
            
            $log("=== SUCESSO - Tema salvo com ID: " . $redacaoId . " ===");
            $_SESSION['success_message'] = 'Tema de redação salvo com sucesso!';
            $this->json(['success' => true, 'redacao_id' => $redacaoId]);
            
        } catch (Exception $e) {
            $log("=== ERRO em salvarTemaRedacaoModulo ===");
            $log("Erro ao salvar tema de redação: " . $e->getMessage());
            $log("Stack trace: " . $e->getTraceAsString());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Gera descrição do tema de redação usando IA
     */
    public function gerarDescricaoRedacaoIA()
    {
        // Limpa qualquer output anterior para garantir resposta JSON limpa
        if (ob_get_level()) {
            ob_clean();
        }
        
        try {
            $user = $this->authManager->getUser();
            $tema = $_POST['tema'] ?? '';
            $moduloId = $_POST['modulo_id'] ?? null;
            
            if (empty($tema)) {
                throw new Exception('Tema é obrigatório');
            }
            
            // Verifica se o módulo pertence ao professor
            if ($moduloId) {
                $modulo = $this->db->fetch(
                    "SELECT m.*, j.professor_id
                     FROM jornadas_modulos m
                     JOIN jornadas j ON m.jornada_id = j.id
                     WHERE m.id = :modulo_id AND j.professor_id = :prof_id",
                    ['modulo_id' => $moduloId, 'prof_id' => $user['id']]
                );
                
                if (!$modulo) {
                    throw new Exception('Módulo não encontrado ou não autorizado');
                }
            }
            
            // Carrega OpenAI Service
            if (!class_exists('App\Services\OpenAIService')) {
                require_once __DIR__ . '/../../Services/OpenAIService.php';
            }
            $openAIService = new \App\Services\OpenAIService();
            
            // Prompt para gerar texto de repertório
            $systemPrompt = "Você é um especialista em educação e produção de textos acadêmicos. 
Sua tarefa é criar um texto de repertório (contexto e orientações) para temas de redação que será apresentado aos alunos.

O texto deve:
- Apresentar o tema de forma clara e contextualizada
- Fornecer informações relevantes sobre o assunto
- Incluir orientações sobre como abordar o tema na redação
- Ser adequado para estudantes do ensino médio
- Ter entre 200 e 400 palavras
- Ser objetivo, didático e estimulante
- Não incluir exemplos de redação completa, apenas orientações e contexto

Formato: Texto corrido, sem títulos ou subtítulos, em parágrafos bem estruturados.";

            $userPrompt = "Crie um texto de repertório e orientações para o seguinte tema de redação:\n\n" . $tema;
            
            // Gera resposta usando OpenAI
            $resposta = $openAIService->chatCompletion(
                [
                    [
                        'role' => 'user',
                        'content' => $userPrompt
                    ]
                ],
                $systemPrompt,
                'gpt-4o-mini',
                0.7,
                1000
            );
            
            $descricao = $resposta['resposta'] ?? '';
            
            if (empty($descricao)) {
                throw new Exception('A IA não retornou uma descrição válida');
            }
            
            // Limpa a resposta (remove markdown se houver, remove espaços extras)
            $descricao = trim($descricao);
            $descricao = preg_replace('/^#+\s*/m', '', $descricao); // Remove markdown headers
            $descricao = preg_replace('/\*\*(.*?)\*\*/', '$1', $descricao); // Remove markdown bold
            $descricao = preg_replace('/\*(.*?)\*/', '$1', $descricao); // Remove markdown italic
            
            $this->json([
                'success' => true,
                'descricao' => $descricao
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao gerar descrição de redação com IA: " . $e->getMessage());
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
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/');
        }
    }
    
    /**
     * Visualiza mensagens dos alunos da jornada
     */
    public function mensagens($jornada_id)
    {
        try {
            $user = $this->authManager->getUser();
            
            // Verifica se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT j.*, t.nome as turma_nome, m.nome as materia_nome
                 FROM jornadas j
                 LEFT JOIN turmas t ON j.turma_id = t.id
                 LEFT JOIN materias m ON j.materia_id = m.id
                 WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
                ['jornada_id' => $jornada_id, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                $_SESSION['error_message'] = 'Jornada não encontrada';
                $this->redirect('/teacher/jornadas');
                return;
            }
            
            // Busca alunos que têm mensagens nesta jornada
            $alunosComMensagens = $this->db->fetchAll(
                "SELECT DISTINCT a.*, 
                        COUNT(DISTINCT CASE WHEN jm.lida = 0 THEN jm.id END) as mensagens_nao_lidas
                 FROM alunos a
                 INNER JOIN jornadas_mensagens jm ON jm.aluno_id = a.id
                 WHERE jm.jornada_id = :jornada_id
                 GROUP BY a.id
                 ORDER BY mensagens_nao_lidas DESC, a.nome ASC",
                ['jornada_id' => $jornada_id]
            );
            
            // Se houver aluno_id na query, busca mensagens desse aluno
            $alunoSelecionado = null;
            $mensagens = [];
            $alunoId = $_GET['aluno_id'] ?? null;
            
            if ($alunoId) {
                $alunoSelecionado = $this->db->fetch(
                    "SELECT * FROM alunos WHERE id = :aluno_id AND turma_id = :turma_id",
                    ['aluno_id' => $alunoId, 'turma_id' => $jornada['turma_id']]
                );
                
                if ($alunoSelecionado) {
                    // Busca mensagens
                    $mensagens = $this->db->fetchAll(
                        "SELECT jm.*, 
                                a.nome as aluno_nome,
                                p.nome as professor_nome
                         FROM jornadas_mensagens jm
                         LEFT JOIN alunos a ON jm.aluno_id = a.id
                         LEFT JOIN professores p ON jm.professor_id = p.id
                         WHERE jm.jornada_id = :jornada_id 
                           AND jm.aluno_id = :aluno_id
                         ORDER BY jm.created_at ASC",
                        [
                            'jornada_id' => $jornada_id,
                            'aluno_id' => $alunoId
                        ]
                    );
                    
                    // Busca anexos das mensagens
                    foreach ($mensagens as &$mensagem) {
                        $mensagem['anexos'] = $this->db->fetchAll(
                            "SELECT * FROM jornadas_mensagens_anexos 
                             WHERE mensagem_id = :mensagem_id
                             ORDER BY created_at ASC",
                            ['mensagem_id' => $mensagem['id']]
                        );
                    }
                    
                    // Marca mensagens como lidas
                    $this->db->update(
                        "UPDATE jornadas_mensagens 
                         SET lida = 1, lida_em = NOW() 
                         WHERE jornada_id = :jornada_id 
                           AND aluno_id = :aluno_id 
                           AND remetente_tipo = 'aluno'
                           AND lida = 0",
                        [
                            'jornada_id' => $jornada_id,
                            'aluno_id' => $alunoId
                        ]
                    );
                }
            }
            
            $data = [
                'title' => 'Mensagens - ' . $jornada['titulo'] . ' - EducaTudo',
                'user' => $user,
                'jornada' => $jornada,
                'alunos_com_mensagens' => $alunosComMensagens,
                'aluno_selecionado' => $alunoSelecionado,
                'mensagens' => $mensagens,
                'csrf_token' => $this->generateCsrfToken(),
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/mensagens', $data);
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao carregar mensagens: ' . $e->getMessage();
            $this->redirect('/teacher/jornadas');
        }
    }
    
    /**
     * Responde mensagem do aluno
     */
    public function responderMensagem()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $user = $this->authManager->getUser();
            
            $jornadaId = $_POST['jornada_id'] ?? null;
            $alunoId = $_POST['aluno_id'] ?? null;
            $mensagemTexto = $_POST['mensagem'] ?? '';
            // Remove todos os espaços extras: início, fim e múltiplos espaços no meio
            $mensagemTexto = preg_replace('/\s+/u', ' ', $mensagemTexto);
            $mensagemTexto = trim($mensagemTexto);
            // Remove espaços não quebráveis e outros caracteres invisíveis
            $mensagemTexto = str_replace(["\xC2\xA0", "\xE2\x80\x8B"], ' ', $mensagemTexto);
            $mensagemTexto = trim($mensagemTexto);
            
            if (empty($jornadaId) || empty($alunoId) || empty($mensagemTexto)) {
                throw new Exception('Jornada, aluno e mensagem são obrigatórios');
            }
            
            // Verifica se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT j.* FROM jornadas j
                 WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
                ['jornada_id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            // Insere mensagem de resposta
            $mensagemId = $this->db->insert(
                "INSERT INTO jornadas_mensagens 
                 (jornada_id, aluno_id, professor_id, remetente_tipo, remetente_id, mensagem, lida) 
                 VALUES (:jornada_id, :aluno_id, :professor_id, 'professor', :remetente_id, :mensagem, 0)",
                [
                    'jornada_id' => $jornadaId,
                    'aluno_id' => $alunoId,
                    'professor_id' => $user['id'],
                    'remetente_id' => $user['id'],
                    'mensagem' => $mensagemTexto
                ]
            );
            
            // Processa anexos se houver
            $anexosIds = [];
            if (!empty($_FILES['anexos'])) {
                $anexosIds = $this->processarAnexosMensagem($mensagemId, $_FILES['anexos']);
            }
            
            // Busca a mensagem criada com todos os dados
            $mensagemCriada = $this->db->fetch(
                "SELECT jm.*, 
                        a.nome as aluno_nome,
                        p.nome as professor_nome
                 FROM jornadas_mensagens jm
                 LEFT JOIN alunos a ON jm.aluno_id = a.id
                 LEFT JOIN professores p ON jm.professor_id = p.id
                 WHERE jm.id = :mensagem_id",
                ['mensagem_id' => $mensagemId]
            );
            
            // Busca anexos da mensagem
            if ($mensagemCriada) {
                $mensagemCriada['anexos'] = $this->db->fetchAll(
                    "SELECT * FROM jornadas_mensagens_anexos 
                     WHERE mensagem_id = :mensagem_id
                     ORDER BY created_at ASC",
                    ['mensagem_id' => $mensagemId]
                );
            }
            
            $this->json([
                'success' => true, 
                'message' => 'Mensagem enviada com sucesso', 
                'mensagem_id' => $mensagemId,
                'mensagem' => $mensagemCriada,
                'anexos' => $anexosIds
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao responder mensagem: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * API EducaProf: contexto permitido (turmas e matérias do professor logado via SSO)
     */
    public function apiEducaProfJornadasContexto()
    {
        try {
            $user = $this->authManager->getUser();
            $ctx = $this->buildEducaProfContexto((int)$user['id']);
            $this->json(['success' => true, 'contexto' => $ctx]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * API EducaProf: valida payload completo sem gravar
     */
    public function apiEducaProfJornadasDryRun()
    {
        try {
            $user = $this->authManager->getUser();
            $payload = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                throw new Exception('Payload JSON inválido');
            }

            [$normalized, $warnings] = $this->validateEducaProfJornadaPayload($payload, (int)$user['id']);
            $this->json([
                'success' => true,
                'dry_run' => true,
                'warnings' => $warnings,
                'normalized' => $normalized
            ]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * API EducaProf: cria jornada completa (jornada + blocos + exercícios) com payload único
     */
    public function apiEducaProfJornadasCriar()
    {
        try {
            $user = $this->authManager->getUser();
            $payload = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                throw new Exception('Payload JSON inválido');
            }

            [$normalized, $warnings] = $this->validateEducaProfJornadaPayload($payload, (int)$user['id']);

            $this->db->beginTransaction();
            try {
                $j = $normalized['jornada'];
                $estrutura = [
                    'data_inicio' => $j['data_inicio'] ?? null,
                    'hora_inicio' => $j['hora_inicio'] ?? null,
                    'data_fim' => $j['data_fim'] ?? null,
                    'hora_fim' => $j['hora_fim'] ?? null,
                    'objetivos' => (string)($j['descricao'] ?? ''),
                    'turmas_selecionadas' => $j['turmas_ids'],
                    'alunos_selecionados' => [],
                    'tipo_selecao_alunos' => 'todos'
                ];

                $jornadaId = (int)$this->db->insert(
                    "INSERT INTO jornadas (professor_id, turma_id, materia_id, titulo, ano_letivo, bimestre, avaliativo, estrutura, status, ativo, created_at, updated_at)
                     VALUES (:professor_id, :turma_id, :materia_id, :titulo, :ano_letivo, :bimestre, :avaliativo, :estrutura, 'ativa', 1, NOW(), NOW())",
                    [
                        'professor_id' => (int)$user['id'],
                        'turma_id' => (int)$j['turmas_ids'][0],
                        'materia_id' => (int)$j['materia_id'],
                        'titulo' => (string)$j['titulo'],
                        'ano_letivo' => (int)$j['ano_letivo'],
                        'bimestre' => (int)$j['bimestre'],
                        'avaliativo' => (int)($j['avaliativo'] ? 1 : 0),
                        'estrutura' => json_encode($estrutura, JSON_UNESCAPED_UNICODE)
                    ]
                );

                $modulosCriados = 0;
                $exerciciosCriados = 0;
                $ordemModulo = 1;

                foreach (($normalized['blocos'] ?? []) as $bloco) {
                    $tipoModulo = (string)$bloco['tipo_modulo'];
                    $tituloModulo = trim((string)($bloco['titulo'] ?? ''));
                    if ($tituloModulo === '') {
                        $tituloModulo = $this->defaultTituloByTipoModulo($tipoModulo);
                    }

                    $moduloId = (int)$this->db->insert(
                        "INSERT INTO jornadas_modulos (jornada_id, aula_id, tipo_modulo, titulo, descricao, ordem, obrigatorio, status, created_at)
                         VALUES (:jornada_id, NULL, :tipo_modulo, :titulo, :descricao, :ordem, :obrigatorio, 'ativo', NOW())",
                        [
                            'jornada_id' => $jornadaId,
                            'tipo_modulo' => $tipoModulo,
                            'titulo' => $tituloModulo,
                            'descricao' => (string)($bloco['conteudo']['texto'] ?? $bloco['conteudo']['descricao'] ?? ''),
                            'ordem' => $ordemModulo++,
                            'obrigatorio' => !empty($bloco['obrigatorio']) ? 1 : 0
                        ]
                    );
                    $modulosCriados++;

                    if (in_array($tipoModulo, ['video', 'conteudo', 'dica_professor'], true)) {
                        $this->persistEducaProfConteudoModulo($moduloId, $tituloModulo, (array)($bloco['conteudo'] ?? []));
                    }

                    if ($tipoModulo === 'exercicios' && !empty($bloco['exercicios']) && is_array($bloco['exercicios'])) {
                        $ordemEx = 1;
                        foreach ($bloco['exercicios'] as $ex) {
                            $tipoEx = (string)($ex['tipo'] ?? 'alternativas');
                            $enunciado = trim((string)($ex['enunciado'] ?? ''));
                            if ($enunciado === '') {
                                throw new Exception('Exercício sem enunciado em bloco de exercícios');
                            }
                            $tituloEx = mb_substr(strip_tags($enunciado), 0, 100);
                            $questoesJson = isset($ex['questoes_json']) && is_array($ex['questoes_json']) ? json_encode($ex['questoes_json'], JSON_UNESCAPED_UNICODE) : null;
                            $respostaCorreta = (string)($ex['resposta_correta'] ?? '');
                            $statusEx = in_array((string)($ex['status'] ?? ''), ['publicado', 'rascunho', 'arquivado'], true) ? (string)$ex['status'] : 'publicado';

                            $this->db->insert(
                                "INSERT INTO jornadas_modulos_exercicios
                                 (modulo_id, tipo, titulo, enunciado, questoes_json, resposta_correta, gabarito, pontuacao, ordem, gerado_ia, status, imagem_url, nivel_dificuldade, created_at, updated_at)
                                 VALUES (:modulo_id, :tipo, :titulo, :enunciado, :questoes_json, :resposta_correta, NULL, :pontuacao, :ordem, 0, :status, NULL, NULL, NOW(), NOW())",
                                [
                                    'modulo_id' => $moduloId,
                                    'tipo' => $tipoEx,
                                    'titulo' => $tituloEx !== '' ? $tituloEx : 'Exercício',
                                    'enunciado' => \App\Utils\HtmlSanitizer::clean((string)$enunciado),
                                    'questoes_json' => $questoesJson,
                                    'resposta_correta' => $respostaCorreta,
                                    'pontuacao' => (float)($ex['pontuacao'] ?? 1),
                                    'ordem' => $ordemEx++,
                                    'status' => $statusEx
                                ]
                            );
                            $exerciciosCriados++;
                        }
                    }
                }

                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

            $this->json([
                'success' => true,
                'jornada_id' => $jornadaId,
                'modulos_criados' => $modulosCriados,
                'exercicios_criados' => $exerciciosCriados,
                'warnings' => $warnings
            ]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Banco de Questões (modal da jornada): retorna facetas dinâmicas.
     */
    public function apiBancoQuestoesFacetsModulo()
    {
        try {
            $this->assertProfessorLogado();
            $query = $this->buildBancoQuestoesQueryFromRequest($_GET);
            $payload = $this->bancoQuestoesApiGet('/api/facets', $query);
            $this->json(['success' => true, 'data' => $payload]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Banco de Questões (modal da jornada): lista questões com filtros e paginação.
     */
    public function apiBancoQuestoesListarModulo()
    {
        try {
            $this->assertProfessorLogado();
            $query = $this->buildBancoQuestoesQueryFromRequest($_GET);
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            if ($limit < 1) $limit = 20;
            if ($limit > 100) $limit = 100;
            if ($offset < 0) $offset = 0;
            $query['limit'] = $limit;
            $query['offset'] = $offset;

            $payload = $this->bancoQuestoesApiGet('/api/questoes', $query);
            $this->json(['success' => true, 'data' => $payload]);
        } catch (Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Banco de Questões (modal da jornada): importa questões selecionadas para o módulo.
     */
    public function apiBancoQuestoesImportarModulo()
    {
        try {
            @set_time_limit(120);
            $user = $this->assertProfessorLogado();
            require_once __DIR__ . '/../../Services/CreditosService.php';
            $creditosService = new \App\Services\CreditosService();
            $moduloCredito = 'gerar_exercicio_ia_professor';

            $raw = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($raw)) {
                throw new Exception('Payload JSON inválido');
            }

            $moduloId = (int)($raw['modulo_id'] ?? 0);
            $ids = isset($raw['questao_ids']) && is_array($raw['questao_ids']) ? $raw['questao_ids'] : [];
            if ($moduloId <= 0) {
                throw new Exception('Módulo inválido');
            }
            if (empty($ids)) {
                throw new Exception('Selecione ao menos uma questão');
            }

            $modulo = $this->db->fetch(
                "SELECT m.id, m.jornada_id, j.professor_id
                 FROM jornadas_modulos m
                 JOIN jornadas j ON j.id = m.jornada_id
                 WHERE m.id = :modulo_id",
                ['modulo_id' => $moduloId]
            );
            if (!$modulo || (int)$modulo['professor_id'] !== (int)$user['id']) {
                throw new Exception('Módulo não encontrado ou não autorizado');
            }

            $ids = array_values(array_unique(array_filter(array_map(function ($id) {
                return trim((string)$id);
            }, $ids), function ($id) {
                return $id !== '';
            })));
            if (empty($ids)) {
                throw new Exception('Nenhum ID de questão válido informado');
            }

            $inseridos = 0;
            $falhas = [];
            $creditosConsumidos = 0;
            $ordemBase = $this->getNextOrdemExercicioModulo($moduloId);

            foreach ($ids as $idx => $questaoId) {
                $creditoReferencia = null;
                try {
                    $payload = $this->bancoQuestoesApiGet('/api/questoes', ['id' => $questaoId, 'limit' => 1, 'offset' => 0]);
                    $questoes = is_array($payload['questoes'] ?? null) ? $payload['questoes'] : [];
                    if (empty($questoes[0]) || !is_array($questoes[0])) {
                        throw new Exception('Questão não encontrada na API');
                    }
                    $map = $this->mapBancoQuestaoToJornadaExercicio($questoes[0], (int)$user['id']);

                    // 1 crédito por nova questão importada do banco.
                    $creditoReferencia = 'bancoq:' . $moduloId . ':' . $questaoId . ':' . uniqid();
                    $creditosService->consumir('professor', (int)$user['id'], $moduloCredito, $creditoReferencia);

                    $this->db->insert(
                        "INSERT INTO jornadas_modulos_exercicios
                         (modulo_id, tipo, titulo, enunciado, questoes_json, resposta_correta, gabarito, pontuacao, ordem, gerado_ia, status, imagem_url, nivel_dificuldade, created_at, updated_at)
                         VALUES
                         (:modulo_id, :tipo, :titulo, :enunciado, :questoes_json, :resposta_correta, NULL, :pontuacao, :ordem, 0, 'publicado', NULL, :nivel_dificuldade, NOW(), NOW())",
                        [
                            'modulo_id' => $moduloId,
                            'tipo' => $map['tipo'],
                            'titulo' => $map['titulo'],
                            'enunciado' => $map['enunciado'],
                            'questoes_json' => $map['questoes_json'],
                            'resposta_correta' => $map['resposta_correta'],
                            'pontuacao' => $map['pontuacao'],
                            'ordem' => $ordemBase + $idx,
                            'nivel_dificuldade' => $map['nivel_dificuldade']
                        ]
                    );
                    $inseridos++;
                    $creditosConsumidos++;
                } catch (\Throwable $qe) {
                    if ($creditoReferencia !== null) {
                        try {
                            $creditosService->estornarPorReferencia($moduloCredito, $creditoReferencia);
                        } catch (\Throwable $e2) {
                            error_log('Falha no estorno de crédito (banco questões): ' . $e2->getMessage());
                        }
                    }
                    $falhas[] = ['id' => $questaoId, 'erro' => $qe->getMessage()];
                }
            }

            $this->json([
                'success' => true,
                'importados' => $inseridos,
                'creditos_consumidos' => $creditosConsumidos,
                'falhas' => $falhas
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function assertProfessorLogado(): array
    {
        $user = $this->authManager->getUser();
        if (!$user || ($user['tipo'] ?? '') !== 'professor') {
            throw new Exception('Não autorizado');
        }
        return $user;
    }

    private function buildBancoQuestoesQueryFromRequest(array $src): array
    {
        $allowed = ['q', 'id', 'materia', 'dificuldade', 'tag', 'topico', 'ano', 'origem_titulo', 'tipo'];
        $query = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $src)) {
                continue;
            }
            $v = trim((string)$src[$k]);
            if ($v !== '') {
                $query[$k] = $v;
            }
        }
        return $query;
    }

    private function bancoQuestoesApiBaseUrl(): string
    {
        $fromEnv = getenv('BANCO_QUESTOES_API_BASE');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return rtrim(trim($fromEnv), '/');
        }
        return 'http://69.62.86.185:8080';
    }

    private function bancoQuestoesApiGet(string $path, array $query = []): array
    {
        $url = $this->bancoQuestoesApiBaseUrl() . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        if (!function_exists('curl_init')) {
            throw new Exception('cURL não está disponível no servidor.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            throw new Exception('Falha ao consultar API de questões: ' . $error);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception('API de questões retornou HTTP ' . $httpCode);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new Exception('Resposta inválida da API de questões.');
        }
        return $decoded;
    }

    private function getNextOrdemExercicioModulo(int $moduloId): int
    {
        $row = $this->db->fetch(
            "SELECT COALESCE(MAX(ordem), 0) as max_ordem FROM jornadas_modulos_exercicios WHERE modulo_id = :modulo_id",
            ['modulo_id' => $moduloId]
        );
        return ((int)($row['max_ordem'] ?? 0)) + 1;
    }

    private function mapBancoQuestaoToJornadaExercicio(array $q, int $professorId): array
    {
        $enunciadoRaw = (string)($q['enunciado_html'] ?? $q['enunciado'] ?? '');
        $enunciadoRaw = $this->persistInlineBase64ImagesInHtml($enunciadoRaw, $professorId);
        $enunciado = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($enunciadoRaw);
        if (trim(strip_tags($enunciado)) === '' && trim($enunciado) === '') {
            throw new Exception('Enunciado vazio');
        }

        $alternativas = isset($q['alternativas']) && is_array($q['alternativas']) ? $q['alternativas'] : [];
        $gabarito = strtoupper(trim((string)($q['gabarito'] ?? '')));

        $isAlternativa = !empty($alternativas);
        $tipo = $isAlternativa ? 'alternativas' : 'dissertativa';

        $questoesJson = null;
        $respostaCorreta = '';
        if ($isAlternativa) {
            $opcoes = [];
            $ordemLetras = ['A', 'B', 'C', 'D', 'E', 'F'];
            foreach ($ordemLetras as $letra) {
                if (!array_key_exists($letra, $alternativas)) {
                    continue;
                }
                $textoAltRaw = $this->persistInlineBase64ImagesInHtml((string)$alternativas[$letra], $professorId);
                $textoAlt = \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($textoAltRaw);
                $opcoes[] = [
                    'texto' => $textoAlt,
                    'correta' => ($gabarito !== '' && strtoupper($letra) === $gabarito)
                ];
            }
            if (count($opcoes) < 2) {
                throw new Exception('Questão sem alternativas suficientes');
            }
            $questoesJson = json_encode(['opcoes' => $opcoes], JSON_UNESCAPED_UNICODE);
            $respostaCorreta = $gabarito !== '' ? $gabarito : '';
        } else {
            $respostaCorreta = trim((string)($q['resolucao_html'] ?? ''));
        }

        $tituloBase = trim(strip_tags((string)$enunciado));
        if ($tituloBase === '') {
            $tituloBase = 'Questão do Banco';
        }
        $titulo = mb_substr($tituloBase, 0, 100);

        $dif = strtolower(trim((string)($q['dificuldade'] ?? '')));
        $nivel = null;
        if (in_array($dif, ['fácil', 'facil'], true)) $nivel = 'facil';
        if ($dif === 'médio' || $dif === 'medio') $nivel = 'medio';
        if ($dif === 'difícil' || $dif === 'dificil') $nivel = 'dificil';

        return [
            'tipo' => $tipo,
            'titulo' => $titulo,
            'enunciado' => $enunciado,
            'questoes_json' => $questoesJson,
            'resposta_correta' => $respostaCorreta,
            'pontuacao' => 1.00,
            'nivel_dificuldade' => $nivel
        ];
    }

    private function buildEducaProfContexto(int $professorId): array
    {
        $professor = $this->db->fetch("SELECT id, nome, turmas, materias FROM professores WHERE id = :id", ['id' => $professorId]);
        if (!$professor) {
            throw new Exception('Professor não encontrado');
        }

        $turmasIds = json_decode((string)($professor['turmas'] ?? '[]'), true);
        $turmasIds = is_array($turmasIds) ? array_values(array_unique(array_map('intval', $turmasIds))) : [];

        $materiasNomes = json_decode((string)($professor['materias'] ?? '[]'), true);
        $materiasNomes = is_array($materiasNomes) ? array_values(array_filter(array_map('strval', $materiasNomes))) : [];

        $turmas = [];
        if (!empty($turmasIds)) {
            $placeholders = implode(',', array_fill(0, count($turmasIds), '?'));
            $turmas = $this->db->fetchAll("SELECT id, nome, serie, ano_letivo FROM turmas WHERE id IN ($placeholders) ORDER BY nome ASC", $turmasIds);
        }

        $materias = [];
        if (!empty($materiasNomes)) {
            $placeholders = implode(',', array_fill(0, count($materiasNomes), '?'));
            $materias = $this->db->fetchAll("SELECT id, nome FROM materias WHERE nome IN ($placeholders) ORDER BY nome ASC", $materiasNomes);
        }

        return [
            'professor' => ['id' => (int)$professor['id'], 'nome' => (string)$professor['nome']],
            'turmas' => array_map(static function ($t) {
                return [
                    'id' => (int)$t['id'],
                    'nome' => (string)$t['nome'],
                    'serie' => (string)($t['serie'] ?? ''),
                    'ano_letivo' => (int)($t['ano_letivo'] ?? 0)
                ];
            }, $turmas),
            'materias' => array_map(static function ($m) {
                return ['id' => (int)$m['id'], 'nome' => (string)$m['nome']];
            }, $materias),
            'bimestres_disponiveis' => [1, 2, 3, 4]
        ];
    }

    private function defaultTituloByTipoModulo(string $tipoModulo): string
    {
        $map = [
            'resumo_aluno' => 'Pedir Resumo para Aluno',
            'dica_professor' => 'Dica do Professor',
            'exercicios' => 'Exercícios',
            'video' => 'Conteúdo',
            'conteudo' => 'Conteúdo'
        ];
        return $map[$tipoModulo] ?? 'Bloco';
    }

    private function validateEducaProfJornadaPayload(array $payload, int $professorId): array
    {
        $warnings = [];
        $ctx = $this->buildEducaProfContexto($professorId);
        $allowedTurmas = array_column($ctx['turmas'], 'id');
        $allowedMaterias = array_column($ctx['materias'], 'id');

        $j = $payload['jornada'] ?? [];
        if (!is_array($j)) {
            throw new Exception('Campo "jornada" é obrigatório');
        }

        $titulo = trim((string)($j['titulo'] ?? ''));
        if ($titulo === '') throw new Exception('jornada.titulo é obrigatório');

        $materiaId = (int)($j['materia_id'] ?? 0);
        if ($materiaId <= 0 || !in_array($materiaId, $allowedMaterias, true)) {
            throw new Exception('Matéria inválida para este professor');
        }

        $turmasIds = $j['turmas_ids'] ?? [];
        if (!is_array($turmasIds) || empty($turmasIds)) {
            throw new Exception('jornada.turmas_ids é obrigatório');
        }
        $turmasIds = array_values(array_unique(array_map('intval', $turmasIds)));
        foreach ($turmasIds as $tid) {
            if (!in_array($tid, $allowedTurmas, true)) {
                throw new Exception('Turma inválida para este professor: ' . $tid);
            }
        }

        $anoLetivo = (int)($j['ano_letivo'] ?? date('Y'));
        $bimestre = (int)($j['bimestre'] ?? 1);
        if ($bimestre < 1 || $bimestre > 4) throw new Exception('jornada.bimestre deve ser de 1 a 4');

        $avaliativo = !empty($j['avaliativo']);
        $dataInicio = (string)($j['data_inicio'] ?? date('Y-m-d'));
        $dataFim = (string)($j['data_fim'] ?? $dataInicio);
        $horaInicio = (string)($j['hora_inicio'] ?? '00:00');
        $horaFim = (string)($j['hora_fim'] ?? '23:59');

        $blocos = $payload['blocos'] ?? [];
        if (!is_array($blocos) || empty($blocos)) {
            throw new Exception('Ao menos um bloco é obrigatório');
        }

        $tiposBlocoPermitidos = ['resumo_aluno', 'dica_professor', 'exercicios', 'video', 'conteudo'];
        $tiposExPermitidos = ['alternativas', 'verdadeiro_falso', 'dissertativa', 'preencher_lacuna'];
        $normalizedBlocos = [];

        foreach ($blocos as $i => $b) {
            if (!is_array($b)) throw new Exception("blocos[$i] inválido");
            $tipoModulo = strtolower(trim((string)($b['tipo_modulo'] ?? '')));
            if (!in_array($tipoModulo, $tiposBlocoPermitidos, true)) {
                throw new Exception("blocos[$i].tipo_modulo inválido");
            }
            $conteudoRaw = is_array($b['conteudo'] ?? null) ? $b['conteudo'] : [];
            $tipoDocumento = strtolower(trim((string)($conteudoRaw['tipo_documento'] ?? 'nenhum')));
            $tiposDocumentoPermitidos = ['nenhum', 'youtube', 'upload_video', 'upload_documento', 'link_externo', 'texto'];
            if (!in_array($tipoDocumento, $tiposDocumentoPermitidos, true)) {
                throw new Exception("blocos[$i].conteudo.tipo_documento inválido");
            }
            $urlConteudo = trim((string)($conteudoRaw['url'] ?? $conteudoRaw['url_youtube'] ?? ''));
            $arquivoRef = trim((string)($conteudoRaw['arquivo_referencia'] ?? ''));
            if (in_array($tipoDocumento, ['youtube', 'link_externo'], true) && $urlConteudo === '') {
                throw new Exception("blocos[$i].conteudo.url é obrigatório para {$tipoDocumento}");
            }
            if (in_array($tipoDocumento, ['upload_video', 'upload_documento'], true) && $arquivoRef === '') {
                throw new Exception("blocos[$i].conteudo.arquivo_referencia é obrigatório para {$tipoDocumento}");
            }

            $norm = [
                'ordem' => (int)($b['ordem'] ?? ($i + 1)),
                'tipo_modulo' => $tipoModulo,
                'titulo' => trim((string)($b['titulo'] ?? '')),
                'obrigatorio' => !empty($b['obrigatorio']),
                'conteudo' => [
                    'tipo_documento' => $tipoDocumento,
                    'texto' => (string)($conteudoRaw['texto'] ?? $conteudoRaw['descricao'] ?? ''),
                    'titulo_conteudo' => trim((string)($conteudoRaw['titulo_conteudo'] ?? $conteudoRaw['titulo'] ?? '')),
                    'url' => $urlConteudo,
                    'conteudo_texto' => (string)($conteudoRaw['conteudo_texto'] ?? ''),
                    'arquivo_referencia' => $arquivoRef,
                    'arquivo_nome' => trim((string)($conteudoRaw['arquivo_nome'] ?? '')),
                    'arquivo_tamanho' => isset($conteudoRaw['arquivo_tamanho']) ? (int)$conteudoRaw['arquivo_tamanho'] : null,
                    'tipo_arquivo' => trim((string)($conteudoRaw['tipo_arquivo'] ?? '')),
                ],
                'exercicios' => []
            ];

            if ($tipoModulo === 'exercicios') {
                $exercicios = $b['exercicios'] ?? [];
                if (!is_array($exercicios) || empty($exercicios)) {
                    $warnings[] = "blocos[$i] tipo exercícios sem itens";
                } else {
                    foreach ($exercicios as $jIdx => $ex) {
                        if (!is_array($ex)) throw new Exception("blocos[$i].exercicios[$jIdx] inválido");
                        $tipoEx = strtolower(trim((string)($ex['tipo'] ?? 'alternativas')));
                        if (!in_array($tipoEx, $tiposExPermitidos, true)) {
                            throw new Exception("blocos[$i].exercicios[$jIdx].tipo inválido");
                        }
                        $enunciado = trim((string)($ex['enunciado'] ?? ''));
                        if ($enunciado === '') throw new Exception("blocos[$i].exercicios[$jIdx].enunciado é obrigatório");

                        $respostaCorreta = (string)($ex['resposta_correta'] ?? '');
                        $questoesJson = is_array($ex['questoes_json'] ?? null) ? $ex['questoes_json'] : [];
                        if ($tipoEx === 'alternativas' || $tipoEx === 'verdadeiro_falso') {
                            if (empty($questoesJson['opcoes']) || !is_array($questoesJson['opcoes'])) {
                                throw new Exception("blocos[$i].exercicios[$jIdx].questoes_json.opcoes é obrigatório para objetivas");
                            }
                        }
                        if ($tipoEx === 'preencher_lacuna') {
                            if (empty($questoesJson['opcoes_lacuna']) || !is_array($questoesJson['opcoes_lacuna'])) {
                                throw new Exception("blocos[$i].exercicios[$jIdx].questoes_json.opcoes_lacuna é obrigatório");
                            }
                        }
                        if ($respostaCorreta === '' && $tipoEx !== 'dissertativa') {
                            $warnings[] = "blocos[$i].exercicios[$jIdx] sem resposta_correta explícita";
                        }

                        $norm['exercicios'][] = [
                            'tipo' => $tipoEx,
                            'enunciado' => $enunciado,
                            'pontuacao' => (float)($ex['pontuacao'] ?? 1),
                            'status' => (string)($ex['status'] ?? 'publicado'),
                            'questoes_json' => $questoesJson,
                            'resposta_correta' => $respostaCorreta
                        ];
                    }
                }
            }

            $normalizedBlocos[] = $norm;
        }

        $normalized = [
            'jornada' => [
                'titulo' => $titulo,
                'materia_id' => $materiaId,
                'turmas_ids' => $turmasIds,
                'ano_letivo' => $anoLetivo,
                'bimestre' => $bimestre,
                'avaliativo' => $avaliativo,
                'data_inicio' => $dataInicio,
                'hora_inicio' => $horaInicio,
                'data_fim' => $dataFim,
                'hora_fim' => $horaFim,
                'descricao' => (string)($j['descricao'] ?? '')
            ],
            'blocos' => $normalizedBlocos
        ];

        return [$normalized, $warnings];
    }

    private function persistEducaProfConteudoModulo(int $moduloId, string $tituloModulo, array $conteudo): void
    {
        $tipoDocumento = strtolower(trim((string)($conteudo['tipo_documento'] ?? 'nenhum')));
        $tituloConteudo = trim((string)($conteudo['titulo_conteudo'] ?? ''));
        if ($tituloConteudo === '') {
            $tituloConteudo = $tituloModulo !== '' ? $tituloModulo : 'Conteúdo';
        }
        $descricao = (string)($conteudo['texto'] ?? '');
        $url = trim((string)($conteudo['url'] ?? ''));
        $conteudoTexto = (string)($conteudo['conteudo_texto'] ?? '');
        $arquivoRef = trim((string)($conteudo['arquivo_referencia'] ?? ''));
        $arquivoNome = trim((string)($conteudo['arquivo_nome'] ?? ''));
        $arquivoTamanho = isset($conteudo['arquivo_tamanho']) ? (int)$conteudo['arquivo_tamanho'] : null;
        $tipoArquivo = trim((string)($conteudo['tipo_arquivo'] ?? ''));

        if ($tipoDocumento === 'nenhum') {
            return;
        }

        if ($tipoDocumento === 'texto') {
            $this->db->insert(
                "INSERT INTO jornadas_modulos_textos (modulo_id, titulo, conteudo, ordem)
                 VALUES (:modulo_id, :titulo, :conteudo, 0)",
                [
                    'modulo_id' => $moduloId,
                    'titulo' => $tituloConteudo,
                    'conteudo' => $conteudoTexto !== '' ? $conteudoTexto : $descricao,
                ]
            );
            return;
        }

        if (in_array($tipoDocumento, ['youtube', 'link_externo', 'upload_video'], true)) {
            $tipoVideo = $tipoDocumento === 'upload_video' ? 'upload' : $tipoDocumento;
            $this->db->insert(
                "INSERT INTO jornadas_modulos_videos
                 (modulo_id, tipo, titulo, descricao, url_youtube, arquivo_video, arquivo_nome, arquivo_tamanho, ordem)
                 VALUES (:modulo_id, :tipo, :titulo, :descricao, :url_youtube, :arquivo_video, :arquivo_nome, :arquivo_tamanho, 0)",
                [
                    'modulo_id' => $moduloId,
                    'tipo' => $tipoVideo,
                    'titulo' => $tituloConteudo,
                    'descricao' => $descricao !== '' ? $descricao : null,
                    'url_youtube' => $url !== '' ? $url : null,
                    'arquivo_video' => $arquivoRef !== '' ? $arquivoRef : null,
                    'arquivo_nome' => $arquivoNome !== '' ? $arquivoNome : null,
                    'arquivo_tamanho' => $arquivoTamanho,
                ]
            );
            return;
        }

        if ($tipoDocumento === 'upload_documento') {
            if ($arquivoRef === '') {
                throw new Exception('arquivo_referencia é obrigatório para upload_documento');
            }
            if ($arquivoNome === '') {
                $arquivoNome = basename($arquivoRef);
            }
            $this->db->insert(
                "INSERT INTO jornadas_modulos_documentos
                 (modulo_id, titulo, descricao, arquivo, arquivo_nome, arquivo_tamanho, tipo_arquivo, ordem)
                 VALUES (:modulo_id, :titulo, :descricao, :arquivo, :arquivo_nome, :arquivo_tamanho, :tipo_arquivo, 0)",
                [
                    'modulo_id' => $moduloId,
                    'titulo' => $tituloConteudo,
                    'descricao' => $descricao !== '' ? $descricao : null,
                    'arquivo' => $arquivoRef,
                    'arquivo_nome' => $arquivoNome,
                    'arquivo_tamanho' => $arquivoTamanho,
                    'tipo_arquivo' => $tipoArquivo !== '' ? $tipoArquivo : null,
                ]
            );
        }
    }
    
    /**
     * Processa anexos de uma mensagem (método auxiliar)
     */
    private function processarAnexosMensagem($mensagemId, $arquivos)
    {
        $anexosIds = [];
        $uploadDir = __DIR__ . '/../../public/uploads/mensagens/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Permite múltiplos arquivos
        $files = [];
        if (is_array($arquivos['name'])) {
            foreach ($arquivos['name'] as $key => $name) {
                if (!empty($name) && $arquivos['error'][$key] === UPLOAD_ERR_OK) {
                    $files[] = [
                        'name' => $name,
                        'type' => $arquivos['type'][$key],
                        'tmp_name' => $arquivos['tmp_name'][$key],
                        'size' => $arquivos['size'][$key],
                        'error' => $arquivos['error'][$key]
                    ];
                }
            }
        } else {
            if (!empty($arquivos['name']) && $arquivos['error'] === UPLOAD_ERR_OK) {
                $files[] = $arquivos;
            }
        }
        
        foreach ($files as $file) {
            // Validar tipo de arquivo
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 
                           'application/pdf', 'application/msword', 
                           'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!in_array($file['type'], $allowedTypes)) {
                continue;
            }
            
            // Validar tamanho (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                continue;
            }
            
            // Gerar nome único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'msg_' . $mensagemId . '_' . time() . '_' . uniqid() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            // Mover arquivo
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Salvar anexo no banco
                $anexoId = $this->db->insert(
                    "INSERT INTO jornadas_mensagens_anexos 
                     (mensagem_id, nome_arquivo, caminho_arquivo, tipo_arquivo, tamanho_arquivo) 
                     VALUES (:mensagem_id, :nome_arquivo, :caminho_arquivo, :tipo_arquivo, :tamanho_arquivo)",
                    [
                        'mensagem_id' => $mensagemId,
                        'nome_arquivo' => $file['name'],
                        'caminho_arquivo' => '/public/uploads/mensagens/' . $filename,
                        'tipo_arquivo' => $file['type'],
                        'tamanho_arquivo' => $file['size']
                    ]
                );
                $anexosIds[] = $anexoId;
            }
        }
        
        return $anexosIds;
    }
}
}
