<?php
/**
 * EducaTudo - Controller do Professor
 * Gerencia painel do professor
 */

if (!class_exists('TeacherController')) {
    class TeacherController extends BaseController
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
            $this->redirect('/professor');
            return;
        }
        
        // Verifica se é professor (o middleware Auth já verificou se está logado)
        $user = $this->authManager->getUser();
        if ($user && $user['tipo'] !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }

        
        // Verificar se o módulo está desabilitado (exceto para dashboard e logout)
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($uri && strpos($uri, '/professor/dashboard') === false && strpos($uri, '/logout') === false) {
            require_once __DIR__ . '/../../Core/FeatureGate.php';
            $blockedModule = FeatureGate::getBlockedProfessorModule($uri);
            if ($blockedModule) {
                $this->setFlashMessage("A funcionalidade '{$blockedModule}' está desativada no momento.", 'error');
                $this->redirect(URL . '/professor/dashboard');
                return;
            }
        }
    }

    
    /**
     * Página de alteração obrigatória de senha
     */
    public function alterarSenhaObrigatoria()
    {
        $user = $this->authManager->getUser();
        
        // Verificar se realmente precisa alterar senha
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        if (!$professor || !password_verify('123456', $professor['senha_hash'])) {
            // Se não tem senha padrão, redireciona para dashboard
            $this->redirect(URL . '/professor/dashboard');
        }
        
        $data = [
            'title' => 'Alterar Senha - Portal do Professor',
            'user' => $user,
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('professor', 'teacher/alterar-senha-obrigatoria', $data);
    }
    
    /**
     * Processa alteração obrigatória de senha
     */
    public function processarAlteracaoSenha()
    {
        // Verifica CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        
        try {
            $user = $this->authManager->getUser();
            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmarSenha = $_POST['confirmar_senha'] ?? '';
            
            // Validações
            if (empty($novaSenha) || empty($confirmarSenha)) {
                throw new Exception('Todos os campos são obrigatórios');
            }
            
            // Valida senha forte
            $auth = new Auth();
            $passwordValidation = $auth->validateStrongPassword($novaSenha);
            if ($passwordValidation !== true) {
                throw new Exception($passwordValidation);
            }
            
            if ($novaSenha !== $confirmarSenha) {
                throw new Exception('As senhas não coincidem');
            }
            
            if ($novaSenha === '123456') {
                throw new Exception('A nova senha não pode ser a senha padrão');
            }
            
            // Atualizar senha no banco
            $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            
            $result = $this->db->query(
                "UPDATE professores SET senha_hash = :senha_hash WHERE id = :user_id",
                ['senha_hash' => $senhaHash, 'user_id' => $user['id']]
            );
            
            if ($result === false) {
                throw new Exception('Erro ao atualizar senha');
            }
            
            $this->json([
                'success' => true, 
                'message' => 'Senha alterada com sucesso!',
                'redirect' => URL . '/professor/dashboard'
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * API para buscar alunos online
     */
    public function apiAlunosOnline()
    {
        header('Content-Type: application/json');
        
        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        $alunos_online = $this->authManager->getAlunosOnline();
        $formatted = $this->formatAlunosOnline($alunos_online);
        
        $this->json(['alunos' => $formatted, 'total' => count($formatted)]);
    }

    /**
     * SSE para alunos online (professor)
     */
    public function apiAlunosOnlineStream()
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', 0);
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $user = $this->authManager->getUser();
        if (!$user || $user['tipo'] !== 'professor') {
            echo "event: error\n";
            echo "data: " . json_encode(['error' => 'Não autorizado']) . "\n\n";
            @ob_flush();
            flush();
            exit;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $lastHash = null;
        $start = time();
        $lastPing = time();
        $checkIntervalSeconds = 10;

        while (time() - $start < 55) {
            if (connection_aborted()) {
                break;
            }

            $alunos_online = $this->authManager->getAlunosOnline();
            $formatted = $this->formatAlunosOnline($alunos_online);
            $payload = ['alunos' => $formatted, 'total' => count($formatted)];
            $hash = md5(json_encode($payload));

            if ($lastHash === null || $hash !== $lastHash) {
                echo "event: online\n";
                echo "data: " . json_encode($payload) . "\n\n";
                @ob_flush();
                flush();
                $lastHash = $hash;
            }

            if (time() - $lastPing >= 15) {
                echo "event: ping\n";
                echo "data: {}\n\n";
                @ob_flush();
                flush();
                $lastPing = time();
            }

            usleep($checkIntervalSeconds * 1000000);
        }

        exit;
    }

    /**
     * Formata alunos online para resposta
     */
    private function formatAlunosOnline($alunos_online)
    {
        return array_map(function($aluno) {
            $tempo_online = $aluno['tempo_online_segundos'] ?? 0;
            $horas = floor($tempo_online / 3600);
            $minutos = floor(($tempo_online % 3600) / 60);
            $segundos = $tempo_online % 60;
            
            return [
                'id' => $aluno['aluno_id'],
                'nome' => $aluno['nome'],
                'ra' => $aluno['ra'],
                'turma_nome' => $aluno['turma_nome'] ?? 'Sem turma',
                'login_at' => $aluno['login_at'],
                'contexto_tipo' => $aluno['contexto_tipo'] ?? null,
                'contexto_label' => $aluno['contexto_label'] ?? null,
                'tempo_online' => [
                    'total_segundos' => $tempo_online,
                    'formatado' => sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos)
                ]
            ];
        }, $alunos_online);
    }
    
    /**
     * Dashboard do professor
     */
    public function dashboard()
    {
        $user = $this->authManager->getUser();
        
        // Busca dados do professor
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        // Estatísticas do professor (apenas jornadas ativas; excluídas têm ativo = 0)
        $total_jornadas = $this->db->fetch(
            "SELECT COUNT(*) as count FROM jornadas WHERE professor_id = :prof_id AND (ativo = 1 OR ativo IS NULL)",
            ['prof_id' => $professor['id']]
        )['count'];
        
        // Busca todas as jornadas ativas para calcular status baseado em datas
        $todas_jornadas = $this->db->fetchAll(
            "SELECT id, estrutura, status FROM jornadas WHERE professor_id = :prof_id AND (ativo = 1 OR ativo IS NULL)",
            ['prof_id' => $professor['id']]
        );
        
        $dataAtual = date('Y-m-d');
        $jornadas_pausadas = 0;
        $jornadas_aguardando = 0;
        $jornadas_em_andamento = 0;
        $jornadas_concluidas = 0;
        
        foreach ($todas_jornadas as $j) {
            // Se está pausada, conta como pausada independente da data
            if ($j['status'] === 'pausada') {
                $jornadas_pausadas++;
                continue;
            }
            
            // Para jornadas não pausadas, calcula status baseado em data e hora
            $estrutura = json_decode($j['estrutura'], true) ?: [];
            $dataInicio = $estrutura['data_inicio'] ?? null;
            $dataFim = $estrutura['data_fim'] ?? null;
            $horaInicio = trim((string)($estrutura['hora_inicio'] ?? '')) ?: '00:00';
            $horaFim = trim((string)($estrutura['hora_fim'] ?? '')) ?: '23:59:59';
            
            if ($dataInicio && $dataFim) {
                $tsNow = time();
                $tsInicio = strtotime($dataInicio . ' ' . $horaInicio);
                $tsFim = strtotime($dataFim . ' ' . $horaFim);
                if ($tsInicio !== false && $tsFim !== false) {
                    if ($tsNow < $tsInicio) {
                        $jornadas_aguardando++;
                    } elseif ($tsNow > $tsFim) {
                        $jornadas_concluidas++;
                    } else {
                        $jornadas_em_andamento++;
                    }
                } else {
                    if ($dataAtual < $dataInicio) {
                        $jornadas_aguardando++;
                    } elseif ($dataAtual > $dataFim) {
                        $jornadas_concluidas++;
                    } else {
                        $jornadas_em_andamento++;
                    }
                }
            } else {
                $jornadas_em_andamento++;
            }
        }
        
        // Total de itens para corrigir (redações + exercícios)
        $total_para_corrigir = $this->db->fetch(
            "SELECT 
                COALESCE((
                    SELECT COUNT(*) 
                    FROM jornadas_redacoes_alunos jra
                    JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                    JOIN jornadas j ON jr.jornada_id = j.id
                    WHERE j.professor_id = :prof_id_1
                    AND jra.status IN ('entregue', 'corrigida_ia')
                    AND jra.correcao_professor_feita = 0
                ), 0) +
                COALESCE((
                    SELECT COUNT(DISTINCT jpa.id)
                    FROM jornadas_progresso_alunos jpa
                    LEFT JOIN jornadas_modulos_exercicios jme ON jpa.exercicio_modulo_id = jme.id
                    LEFT JOIN jornadas_modulos jm ON jme.modulo_id = jm.id
                    LEFT JOIN jornadas_exercicios je ON jpa.exercicio_id = je.id
                    LEFT JOIN jornadas j ON (jm.jornada_id = j.id OR je.jornada_id = j.id)
                    WHERE j.professor_id = :prof_id_2
                    AND jpa.resposta IS NOT NULL
                    AND jpa.resposta != ''
                    AND jpa.pontuacao IS NULL
                ), 0) as total",
            [
                'prof_id_1' => $professor['id'],
                'prof_id_2' => $professor['id']
            ]
        )['total'] ?? 0;
        
        // Total de itens corrigidos hoje
        $total_corrigidos = $this->db->fetch(
            "SELECT 
                COALESCE((
                    SELECT COUNT(*) 
                    FROM jornadas_redacoes_alunos jra
                    JOIN jornadas_redacoes jr ON jra.jornada_redacao_id = jr.id
                    JOIN jornadas j ON jr.jornada_id = j.id
                    WHERE j.professor_id = :prof_id_1
                    AND jra.correcao_professor_feita = 1
                    AND DATE(jra.updated_at) = CURDATE()
                ), 0) +
                COALESCE((
                    SELECT COUNT(DISTINCT jpa.id)
                    FROM jornadas_progresso_alunos jpa
                    LEFT JOIN jornadas_modulos_exercicios jme ON jpa.exercicio_modulo_id = jme.id
                    LEFT JOIN jornadas_modulos jm ON jme.modulo_id = jm.id
                    LEFT JOIN jornadas_exercicios je ON jpa.exercicio_id = je.id
                    LEFT JOIN jornadas j ON (jm.jornada_id = j.id OR je.jornada_id = j.id)
                    WHERE j.professor_id = :prof_id_2
                    AND jpa.pontuacao IS NOT NULL
                    AND DATE(jpa.updated_at) = CURDATE()
                ), 0) as total",
            [
                'prof_id_1' => $professor['id'],
                'prof_id_2' => $professor['id']
            ]
        )['total'] ?? 0;
        
        // Pontos de atenção (alunos com muitas pendências, turmas sem atividade recente, etc)
        $pontos_atencao = [];
        
        // Alunos com muitas atividades pendentes
        $alunos_pendentes = $this->db->fetchAll(
            "WITH professor_turmas AS (
                SELECT DISTINCT j.turma_id
                FROM jornadas j
                WHERE j.professor_id = :prof_id_1
            ),
            alunos_base AS (
                SELECT a.id, a.nome, a.ra, t.nome AS turma_nome
                FROM alunos a
                JOIN turmas t ON t.id = a.turma_id
                JOIN professor_turmas pt ON pt.turma_id = a.turma_id
                WHERE a.ativo = 1
            ),
            pend_red AS (
                SELECT jra.aluno_id, COUNT(*) AS qtd
                FROM jornadas_redacoes_alunos jra
                JOIN jornadas_redacoes jr ON jr.id = jra.jornada_redacao_id
                JOIN jornadas j ON j.id = jr.jornada_id
                WHERE j.professor_id = :prof_id_2
                  AND jra.status IN ('entregue', 'corrigida_ia')
                  AND jra.correcao_professor_feita = 0
                GROUP BY jra.aluno_id
            ),
            pend_ex AS (
                SELECT x.aluno_id, COUNT(*) AS qtd
                FROM (
                    SELECT DISTINCT jpa.id, jpa.aluno_id
                    FROM jornadas_progresso_alunos jpa
                    JOIN jornadas_modulos_exercicios jme ON jme.id = jpa.exercicio_modulo_id
                    JOIN jornadas_modulos jm ON jm.id = jme.modulo_id
                    JOIN jornadas j ON j.id = jm.jornada_id
                    WHERE j.professor_id = :prof_id_3
                      AND jpa.resposta IS NOT NULL
                      AND jpa.resposta <> ''
                      AND jpa.pontuacao IS NULL

                    UNION

                    SELECT DISTINCT jpa.id, jpa.aluno_id
                    FROM jornadas_progresso_alunos jpa
                    JOIN jornadas_exercicios je ON je.id = jpa.exercicio_id
                    JOIN jornadas j ON j.id = je.jornada_id
                    WHERE j.professor_id = :prof_id_4
                      AND jpa.resposta IS NOT NULL
                      AND jpa.resposta <> ''
                      AND jpa.pontuacao IS NULL
                ) x
                GROUP BY x.aluno_id
            )
            SELECT 
                ab.id,
                ab.nome,
                ab.ra,
                ab.turma_nome,
                (COALESCE(pr.qtd, 0) + COALESCE(pe.qtd, 0)) AS total_pendentes
            FROM alunos_base ab
            LEFT JOIN pend_red pr ON pr.aluno_id = ab.id
            LEFT JOIN pend_ex pe ON pe.aluno_id = ab.id
            WHERE (COALESCE(pr.qtd, 0) + COALESCE(pe.qtd, 0)) > 5
            ORDER BY total_pendentes DESC
            LIMIT 5",
            [
                'prof_id_1' => $professor['id'],
                'prof_id_2' => $professor['id'],
                'prof_id_3' => $professor['id'],
                'prof_id_4' => $professor['id']
            ]
        );
        
        if (!empty($alunos_pendentes)) {
            $pontos_atencao[] = [
                'tipo' => 'alunos_pendentes',
                'titulo' => 'Alunos com muitas pendências',
                'dados' => $alunos_pendentes
            ];
        }
        
        $stats = [
            'total_jornadas' => $total_jornadas,
            'jornadas_aguardando' => $jornadas_aguardando,
            'jornadas_em_andamento' => $jornadas_em_andamento,
            'jornadas_pausadas' => $jornadas_pausadas,
            'jornadas_concluidas' => $jornadas_concluidas,
            'total_para_corrigir' => $total_para_corrigir,
            'total_corrigidos' => $total_corrigidos,
            'total_turmas' => $this->db->fetch("SELECT COUNT(DISTINCT turma_id) as count FROM jornadas WHERE professor_id = :prof_id AND (ativo = 1 OR ativo IS NULL)", ['prof_id' => $professor['id']])['count'],
            'pontos_atencao' => $pontos_atencao
        ];
        
        // Jornadas recentes (apenas ativas; excluídas não aparecem)
        $jornadas_recentes = $this->db->fetchAll(
            "SELECT j.*, t.nome as turma_nome, m.nome as materia_nome
             FROM jornadas j
             JOIN turmas t ON j.turma_id = t.id
             LEFT JOIN materias m ON j.materia_id = m.id
             WHERE j.professor_id = :prof_id AND (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC
             LIMIT 4",
            ['prof_id' => $professor['id']]
        );
        
        // Processa cada jornada para adicionar informações de turmas, status dos alunos e status da jornada
        $dataAtual = date('Y-m-d');
        foreach ($jornadas_recentes as &$jornada) {
            $estrutura = json_decode($jornada['estrutura'], true) ?: [];
            $jornada['data_inicio'] = $estrutura['data_inicio'] ?? null;
            $jornada['data_fim'] = $estrutura['data_fim'] ?? null;
            $jornada['hora_inicio'] = $estrutura['hora_inicio'] ?? null;
            $jornada['hora_fim'] = $estrutura['hora_fim'] ?? null;
            $turmasSelecionadas = $estrutura['turmas_selecionadas'] ?? [];
            $alunosSelecionados = $estrutura['alunos_selecionados'] ?? [];
            $tipoSelecaoAlunos = $estrutura['tipo_selecao_alunos'] ?? 'todos';
            
            // Busca nomes das turmas selecionadas
            if (!empty($turmasSelecionadas) && is_array($turmasSelecionadas)) {
                $placeholders = str_repeat('?,', count($turmasSelecionadas) - 1) . '?';
                $turmasNomes = $this->db->fetchAll(
                    "SELECT nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome",
                    $turmasSelecionadas
                );
                $jornada['turmas_selecionadas_nomes'] = array_column($turmasNomes, 'nome');
            } else {
                // Fallback: usa a turma principal
                $jornada['turmas_selecionadas_nomes'] = [$jornada['turma_nome']];
            }
            
            // Calcula status da jornada baseado em data e hora (após 17h no dia do fim = Concluído)
            if ($jornada['data_inicio'] && $jornada['data_fim']) {
                $tsNow = time();
                $horaInicio = trim((string)($jornada['hora_inicio'] ?? '')) ?: '00:00';
                $horaFim = trim((string)($jornada['hora_fim'] ?? '')) ?: '23:59:59';
                $tsInicio = strtotime($jornada['data_inicio'] . ' ' . $horaInicio);
                $tsFim = strtotime($jornada['data_fim'] . ' ' . $horaFim);
                if ($tsInicio !== false && $tsFim !== false) {
                    if ($tsNow < $tsInicio) {
                        $jornada['status_jornada'] = 'aguardando';
                    } elseif ($tsNow > $tsFim) {
                        $jornada['status_jornada'] = 'concluido';
                    } else {
                        $jornada['status_jornada'] = 'em_andamento';
                    }
                } else {
                    if ($dataAtual < $jornada['data_inicio']) {
                        $jornada['status_jornada'] = 'aguardando';
                    } elseif ($dataAtual > $jornada['data_fim']) {
                        $jornada['status_jornada'] = 'concluido';
                    } else {
                        $jornada['status_jornada'] = 'em_andamento';
                    }
                }
            } else {
                $jornada['status_jornada'] = 'em_andamento';
            }
            
            // Monta filtro-base de alunos elegíveis da jornada
            $whereTurma = "a.turma_id = :turma_id";
            $paramsWhereTurma = ['turma_id' => $jornada['turma_id']];
            
            if (!empty($turmasSelecionadas) && is_array($turmasSelecionadas) && count($turmasSelecionadas) > 0) {
                $turmaParams = [];
                foreach ($turmasSelecionadas as $idx => $turmaId) {
                    if (!empty($turmaId)) {
                        $paramName = 'turma_id_' . $idx;
                        $turmaParams[] = ':' . $paramName;
                        $paramsWhereTurma[$paramName] = (int) $turmaId;
                    }
                }
                if (!empty($turmaParams)) {
                    $whereTurma = "a.turma_id IN (" . implode(', ', $turmaParams) . ")";
                    unset($paramsWhereTurma['turma_id']);
                }
            }
            
            if ($tipoSelecaoAlunos === 'selecionados' && !empty($alunosSelecionados) && is_array($alunosSelecionados) && count($alunosSelecionados) > 0) {
                $alunoParams = [];
                foreach ($alunosSelecionados as $idx => $alunoId) {
                    if (!empty($alunoId)) {
                        $paramName = 'aluno_id_' . $idx;
                        $alunoParams[] = ':' . $paramName;
                        $paramsWhereTurma[$paramName] = (int) $alunoId;
                    }
                }
                if (!empty($alunoParams)) {
                    $whereTurma .= " AND a.id IN (" . implode(', ', $alunoParams) . ")";
                }
            }
            
            // Busca contagem de alunos por status com queries agregadas (evita N+1 por aluno)
            try {
                $paramsConcluido = array_merge(
                    $paramsWhereTurma,
                    [
                        'jornada_id_main' => (int)$jornada['id']
                    ]
                );
                $paramsVisualizado = array_merge(
                    $paramsWhereTurma,
                    [
                        'jornada_id_main' => (int)$jornada['id'],
                        'jornada_id_sub' => (int)$jornada['id']
                    ]
                );

                $totalAlunos = (int)($this->db->fetch(
                    "SELECT COUNT(DISTINCT a.id) as total
                     FROM alunos a
                     WHERE $whereTurma AND a.ativo = 1",
                    $paramsWhereTurma
                )['total'] ?? 0);

                $totalConcluido = (int)($this->db->fetch(
                    "SELECT COUNT(DISTINCT jpa.aluno_id) as total
                     FROM jornadas_progresso_alunos jpa
                     INNER JOIN alunos a ON a.id = jpa.aluno_id
                     WHERE jpa.jornada_id = :jornada_id_main
                     AND jpa.status = 'concluido'
                     AND (jpa.atividade_tipo IS NULL OR jpa.atividade_tipo = 'jornada_concluida')
                     AND jpa.modulo_id IS NULL
                     AND jpa.aula_id IS NULL
                     AND jpa.exercicio_id IS NULL
                     AND jpa.exercicio_modulo_id IS NULL
                     AND a.ativo = 1
                     AND $whereTurma",
                    $paramsConcluido
                )['total'] ?? 0);

                $totalVisualizado = (int)($this->db->fetch(
                    "SELECT COUNT(DISTINCT jpa.aluno_id) as total
                     FROM jornadas_progresso_alunos jpa
                     INNER JOIN alunos a ON a.id = jpa.aluno_id
                     WHERE jpa.jornada_id = :jornada_id_main
                     AND jpa.resposta IS NOT NULL
                     AND jpa.resposta != ''
                     AND (
                         (jpa.atividade_tipo = 'exercicio' AND jpa.exercicio_id IS NOT NULL)
                         OR (jpa.atividade_tipo = 'exercicio_modulo' AND jpa.exercicio_modulo_id IS NOT NULL)
                     )
                     AND a.ativo = 1
                     AND $whereTurma
                     AND NOT EXISTS (
                         SELECT 1
                         FROM jornadas_progresso_alunos jc
                         WHERE jc.aluno_id = jpa.aluno_id
                         AND jc.jornada_id = :jornada_id_sub
                         AND jc.status = 'concluido'
                         AND (jc.atividade_tipo IS NULL OR jc.atividade_tipo = 'jornada_concluida')
                         AND jc.modulo_id IS NULL
                         AND jc.aula_id IS NULL
                         AND jc.exercicio_id IS NULL
                         AND jc.exercicio_modulo_id IS NULL
                     )",
                    $paramsVisualizado
                )['total'] ?? 0);

                $jornada['concluido'] = $totalConcluido;
                $jornada['visualizado'] = $totalVisualizado;
                $jornada['nao_visualizado'] = max(0, $totalAlunos - $totalConcluido - $totalVisualizado);
            } catch (Exception $e) {
                // Em caso de erro, define valores padrão
                error_log("Erro ao buscar status dos alunos da jornada {$jornada['id']}: " . $e->getMessage());
                $jornada['nao_visualizado'] = 0;
                $jornada['visualizado'] = 0;
                $jornada['concluido'] = 0;
            }
        }
        unset($jornada);
        
        // Totais de realizou / não realizou (soma das jornadas recentes)
        $stats['total_realizou_jornada'] = 0;
        $stats['total_nao_realizou_jornada'] = 0;
        foreach ($jornadas_recentes as $j) {
            $stats['total_realizou_jornada'] += (int)($j['visualizado'] ?? 0) + (int)($j['concluido'] ?? 0);
            $stats['total_nao_realizou_jornada'] += (int)($j['nao_visualizado'] ?? 0);
        }
        
        // Eventos de prova recentes (substitui chat com alunos)
        $eventos_prova_recentes = [];
        
        try {
            // Uma linha por (bloco, matéria): subquery com GROUP BY bloco_id, materia_id (igual à página Minhas Provas)
            $eventos = $this->db->fetchAll(
                "SELECT pb.*, rel.materia_id, rel.materia_nome,
                        COUNT(DISTINCT CASE WHEN p.professor_id = :professor_id_join THEN pbp.prova_id END) as provas_criadas_professor
                 FROM provas_blocos pb
                 INNER JOIN (
                     SELECT bloco_id, materia_id, MAX(m.nome) AS materia_nome
                     FROM provas_blocos_professores pbp_rel
                     INNER JOIN materias m ON m.id = pbp_rel.materia_id
                     WHERE pbp_rel.professor_id = :professor_id_where
                     GROUP BY bloco_id, materia_id
                 ) rel ON rel.bloco_id = pb.id
                 LEFT JOIN provas_blocos_vinculo pbp ON pbp.bloco_id = pb.id
                 LEFT JOIN provas p ON p.id = pbp.prova_id
                 WHERE pb.deleted_at IS NULL
                 GROUP BY pb.id, rel.materia_id
                 ORDER BY pb.data_prova DESC, pb.hora_inicio DESC
                 LIMIT 5",
                [
                    'professor_id_where' => $professor['id'],
                    'professor_id_join' => $professor['id']
                ]
            );
            require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
            $blocoFmtModel = new ExamBlock();
            $idsBlocosFmt = array_values(array_unique(array_filter(array_map(static function ($e) {
                return (int) ($e['id'] ?? 0);
            }, $eventos))));
            $fmtPorBlocoId = $blocoFmtModel->fetchFormatoEventoPorBlocoIds($idsBlocosFmt);
            foreach ($eventos as &$evRow) {
                $bid = (int) ($evRow['id'] ?? 0);
                if ($bid > 0 && isset($fmtPorBlocoId[$bid])) {
                    $evRow['formato_evento'] = $fmtPorBlocoId[$bid];
                }
            }
            unset($evRow);
            $eventosAgrupados = [];
            foreach ($eventos as $evento) {
                $chave = (int)$evento['id'] . '_' . (int)$evento['materia_id'];
                if (!isset($eventosAgrupados[$chave])) {
                    $eventosAgrupados[$chave] = $evento;
                    $provaExistente = $this->db->fetch(
                        "SELECT p.id, p.status FROM provas p
                         INNER JOIN provas_blocos_vinculo pbp ON p.id = pbp.prova_id
                         WHERE pbp.bloco_id = :bloco_id AND p.professor_id = :professor_id AND p.materia_id = :materia_id AND p.deleted_at IS NULL LIMIT 1",
                        ['bloco_id' => $evento['id'], 'professor_id' => $professor['id'], 'materia_id' => $evento['materia_id']]
                    );
                    $eventosAgrupados[$chave]['prova_existente_id'] = $provaExistente['id'] ?? null;
                }
            }
            $eventos_prova_recentes = array_values($eventosAgrupados);
            
        } catch (Exception $e) {
            // Se houver erro, simplesmente não carrega os eventos
            error_log("Erro ao buscar eventos de prova: " . $e->getMessage());
            $eventos_prova_recentes = [];
        }
        
        // Total de mensagens não lidas (mantido para compatibilidade com o card de mensagens)
        $total_mensagens_nao_lidas = 0;
        
        $data = [
            'title' => 'Painel do Professor - EducaTudo',
            'professor' => $professor,
            'stats' => $stats,
            'jornadas_recentes' => $jornadas_recentes,
            'eventos_prova_recentes' => $eventos_prova_recentes,
            'total_mensagens_nao_lidas' => $total_mensagens_nao_lidas,
            'user' => $user,
            'current_page' => 'dashboard'
        ];
        
        $this->viewWithLayout('professor', 'teacher/dashboard', $data);
    }

    /**
     * Minha Carteira (saldo de créditos e histórico de movimentações)
     */
    public function carteira()
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        if (!$professor) {
            $this->redirect('/logout');
        }
        require_once __DIR__ . '/../../Services/CreditosService.php';
        require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        $creditosService = new \App\Services\CreditosService();
        $creditosService->aplicarRecargaInicialSeAplicavel('professor', (int) $professor['id']);
        $walletSaldos = $creditosService->getWalletSaldos('professor', (int) $professor['id']);
        $saldo = $walletSaldos['saldo_total'];
        $fTipo = trim((string) ($_GET['filtro_tipo'] ?? ''));
        $fMod = trim((string) ($_GET['filtro_modulo'] ?? ''));
        $fIni = trim((string) ($_GET['data_ini'] ?? ''));
        $fFim = trim((string) ($_GET['data_fim'] ?? ''));
        $movimentacoes = $creditosService->getMovimentacoesFiltradas(
            'professor',
            (int) $professor['id'],
            100,
            0,
            $fTipo !== '' ? $fTipo : null,
            $fMod !== '' ? $fMod : null,
            $fIni !== '' ? $fIni : null,
            $fFim !== '' ? $fFim : null
        );
        $creditosHabilitado = $creditosService->isCreditosHabilitado();
        $tabelaPrecosModulos = $creditosService->listarTabelaPrecosModulos();
        $primaryColor = LayoutHelper::get('primary_color', $this->config['school']['colors']['primary'] ?? '#3b82f6');
        $data = [
            'title' => 'Minha Carteira - EducaTudo',
            'user' => $user,
            'professor' => $professor,
            'saldo' => $saldo,
            'wallet_saldos' => $walletSaldos,
            'movimentacoes' => $movimentacoes,
            'creditos_habilitado' => $creditosHabilitado,
            'current_page' => 'carteira',
            'primary_color' => $primaryColor,
            'filtro_tipo' => $fTipo,
            'filtro_modulo' => $fMod,
            'data_ini' => $fIni,
            'data_fim' => $fFim,
            'tabela_precos_modulos' => $tabelaPrecosModulos,
            'modulos_opcao_filtro' => \CreditosModuleRegistry::getModuleLabels(),
        ];
        $this->viewWithLayout('professor', 'teacher/carteira', $data);
    }

    /**
     * Comprar créditos: lista de pacotes (GET) ou criar compra pendente (POST)
     */
    public function carteiraComprar()
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :user_id", ['user_id' => $user['id']]);
        if (!$professor) {
            $this->redirect('/logout');
        }
        try {
            $pacotes = $this->db->fetchAll("SELECT id, creditos, valor_centavos, nome FROM pacotes_creditos WHERE ativo = 1 ORDER BY creditos ASC");
        } catch (Exception $e) {
            $pacotes = [];
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pacote_id'])) {
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->setFlashMessage('Token inválido.', 'error');
                header('Location: ' . URL . '/professor/carteira/comprar');
                exit;
            }
            $pacoteId = (int) $_POST['pacote_id'];
            $pacote = $this->db->fetch("SELECT id, creditos, valor_centavos FROM pacotes_creditos WHERE id = ? AND ativo = 1", [$pacoteId]);
            if (!$pacote) {
                $this->setFlashMessage('Pacote inválido.', 'error');
                header('Location: ' . URL . '/professor/carteira/comprar');
                exit;
            }
            $compraId = $this->db->insert(
                "INSERT INTO compras_creditos (user_type, user_id, pacote_id, valor_centavos, status) VALUES ('professor', ?, ?, ?, 'pending')",
                [$professor['id'], $pacoteId, $pacote['valor_centavos']]
            );
            header('Location: ' . URL . '/professor/carteira/comprar/aguardando/' . $compraId);
            exit;
        }
        $data = [
            'title' => 'Comprar créditos - EducaTudo',
            'user' => $user,
            'professor' => $professor,
            'pacotes' => $pacotes,
            'current_page' => 'educashop',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('professor', 'teacher/carteira-comprar', $data);
    }

    public function carteiraComprarAguardando($compraId)
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :user_id", ['user_id' => $user['id']]);
        if (!$professor) {
            $this->redirect('/logout');
        }
        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos, p.nome as pacote_nome FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ? AND c.user_type = 'professor' AND c.user_id = ?",
            [$compraId, $professor['id']]
        );
        if (!$compra || ($compra['status'] ?? '') !== 'pending') {
            $this->setFlashMessage('Compra não encontrada ou já processada.', 'error');
            header('Location: ' . URL . '/professor/carteira');
            exit;
        }
        $data = [
            'title' => 'Aguardando pagamento - EducaTudo',
            'user' => $user,
            'professor' => $professor,
            'compra' => $compra,
            'current_page' => 'educashop',
            'pagar_action' => URL . '/professor/carteira/comprar/pagar/' . (int) $compraId,
            'verificar_action' => URL . '/professor/carteira/comprar/verificar/' . (int) $compraId,
            'status_action' => URL . '/professor/carteira/comprar/status/' . (int) $compraId,
            'csrf_token' => $this->generateCsrfToken(),
            'pix_checkout' => null,
            'erro_pagamento' => $_SESSION['flash_message'] ?? null,
            'pagador' => [
                'nome' => $professor['nome'] ?? '',
                'email' => $professor['email'] ?? '',
                'cpf_cnpj' => $professor['cpf'] ?? $professor['cpf_cnpj'] ?? '',
                'phone' => $professor['telefone'] ?? $professor['celular'] ?? $professor['whatsapp'] ?? '',
            ],
        ];
        $this->viewWithLayout('professor', 'teacher/carteira-aguardando', $data);
    }

    public function carteiraComprarPagar($compraId)
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :user_id", ['user_id' => $user['id']]);
        if (!$professor) {
            $this->redirect('/logout');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            header('Location: ' . URL . '/professor/carteira/comprar/aguardando/' . (int) $compraId);
            exit;
        }

        $billingType = strtoupper(trim((string) ($_POST['billing_type'] ?? 'PIX')));
        if (!in_array($billingType, ['PIX', 'CREDIT_CARD'], true)) {
            $billingType = 'PIX';
        }

        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos, p.nome as pacote_nome FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ? AND c.user_type = 'professor' AND c.user_id = ?",
            [$compraId, $professor['id']]
        );
        if (!$compra || ($compra['status'] ?? '') !== 'pending') {
            $this->setFlashMessage('Compra não encontrada ou já processada.', 'error');
            header('Location: ' . URL . '/professor/carteira');
            exit;
        }

        require_once __DIR__ . '/../../Services/TenantCreditsCheckoutService.php';
        require_once __DIR__ . '/../../Services/CreditosTenantCheckoutHelper.php';
        $escolaId = \App\Services\CreditosTenantCheckoutHelper::escolaIdFromConfig($this->config);
        $result = \App\Services\TenantCreditsCheckoutService::createOrReuse(
            $this->db->getPdo(),
            $escolaId,
            $compra,
            $billingType,
            [
                'nome' => trim((string) ($_POST['payer_nome'] ?? ($professor['nome'] ?? 'Professor'))),
                'email' => trim((string) ($_POST['payer_email'] ?? ($professor['email'] ?? 'sem-email@educatudo.local'))),
                'cpf_cnpj' => trim((string) ($_POST['payer_cpf_cnpj'] ?? ($professor['cpf'] ?? $professor['cpf_cnpj'] ?? ''))),
                'phone' => trim((string) ($_POST['payer_phone'] ?? ($professor['telefone'] ?? $professor['celular'] ?? $professor['whatsapp'] ?? ''))),
            ]
        );

        if (!($result['ok'] ?? false)) {
            $data = [
                'title' => 'Aguardando pagamento - EducaTudo',
                'user' => $user,
                'professor' => $professor,
                'compra' => $compra,
                'current_page' => 'educashop',
                'pagar_action' => URL . '/professor/carteira/comprar/pagar/' . (int) $compraId,
                'verificar_action' => URL . '/professor/carteira/comprar/verificar/' . (int) $compraId,
                'status_action' => URL . '/professor/carteira/comprar/status/' . (int) $compraId,
                'csrf_token' => $this->generateCsrfToken(),
                'pix_checkout' => null,
                'erro_pagamento' => $result['message'] ?? 'Falha ao iniciar pagamento.',
                'pagador' => [
                    'nome' => trim((string) ($_POST['payer_nome'] ?? ($professor['nome'] ?? ''))),
                    'email' => trim((string) ($_POST['payer_email'] ?? ($professor['email'] ?? ''))),
                    'cpf_cnpj' => trim((string) ($_POST['payer_cpf_cnpj'] ?? ($professor['cpf'] ?? $professor['cpf_cnpj'] ?? ''))),
                    'phone' => trim((string) ($_POST['payer_phone'] ?? ($professor['telefone'] ?? $professor['celular'] ?? $professor['whatsapp'] ?? ''))),
                ],
            ];
            $this->viewWithLayout('professor', 'teacher/carteira-aguardando', $data);
            return;
        }

        if ($billingType === 'PIX' && !empty($result['pix'])) {
            $data = [
                'title' => 'Aguardando pagamento - EducaTudo',
                'user' => $user,
                'professor' => $professor,
                'compra' => $compra,
                'current_page' => 'educashop',
                'pagar_action' => URL . '/professor/carteira/comprar/pagar/' . (int) $compraId,
                'verificar_action' => URL . '/professor/carteira/comprar/verificar/' . (int) $compraId,
                'status_action' => URL . '/professor/carteira/comprar/status/' . (int) $compraId,
                'csrf_token' => $this->generateCsrfToken(),
                'pix_checkout' => $result['pix'],
                'erro_pagamento' => null,
                'pagador' => [
                    'nome' => trim((string) ($_POST['payer_nome'] ?? ($professor['nome'] ?? ''))),
                    'email' => trim((string) ($_POST['payer_email'] ?? ($professor['email'] ?? ''))),
                    'cpf_cnpj' => trim((string) ($_POST['payer_cpf_cnpj'] ?? ($professor['cpf'] ?? $professor['cpf_cnpj'] ?? ''))),
                    'phone' => trim((string) ($_POST['payer_phone'] ?? ($professor['telefone'] ?? $professor['celular'] ?? $professor['whatsapp'] ?? ''))),
                ],
            ];
            $this->viewWithLayout('professor', 'teacher/carteira-aguardando', $data);
            return;
        }

        if (!empty($result['checkout_url'])) {
            header('Location: ' . $result['checkout_url']);
            exit;
        }

        $this->setFlashMessage('Não foi possível iniciar o pagamento.', 'error');
        header('Location: ' . URL . '/professor/carteira/comprar/aguardando/' . (int) $compraId);
        exit;
    }

    public function carteiraComprarVerificar($compraId)
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :user_id", ['user_id' => $user['id']]);
        if (!$professor) {
            $this->redirect('/logout');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            header('Location: ' . URL . '/professor/carteira/comprar/aguardando/' . (int) $compraId);
            exit;
        }

        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos, p.nome as pacote_nome FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ? AND c.user_type = 'professor' AND c.user_id = ?",
            [$compraId, $professor['id']]
        );
        if (!$compra) {
            $this->setFlashMessage('Compra não encontrada.', 'error');
            header('Location: ' . URL . '/professor/carteira');
            exit;
        }
        if (($compra['status'] ?? '') === 'paid') {
            $this->setFlashMessage('Pagamento já confirmado e créditos liberados.', 'success');
            header('Location: ' . URL . '/professor/carteira');
            exit;
        }

        require_once __DIR__ . '/../../Services/TenantCreditsCheckoutService.php';
        require_once __DIR__ . '/../../Services/CreditosTenantCheckoutHelper.php';
        $escolaId = \App\Services\CreditosTenantCheckoutHelper::escolaIdFromConfig($this->config);
        $result = \App\Services\TenantCreditsCheckoutService::verifyAndFulfill(
            $this->db->getPdo(),
            $escolaId,
            $compra
        );

        if ($result['ok'] ?? false) {
            $this->setFlashMessage('Pagamento confirmado e créditos liberados com sucesso.', 'success');
            header('Location: ' . URL . '/professor/carteira');
            exit;
        }

        $this->setFlashMessage((string) ($result['message'] ?? 'Pagamento ainda não confirmado.'), 'info');
        header('Location: ' . URL . '/professor/carteira/comprar/aguardando/' . (int) $compraId);
        exit;
    }

    public function carteiraComprarStatus($compraId)
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :user_id", ['user_id' => $user['id']]);
        if (!$professor) {
            $this->json(['ok' => false, 'message' => 'Professor não encontrado.'], 404);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['ok' => false, 'message' => 'Token inválido.'], 400);
        }

        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos, p.nome as pacote_nome FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ? AND c.user_type = 'professor' AND c.user_id = ?",
            [$compraId, $professor['id']]
        );
        if (!$compra) {
            $this->json(['ok' => false, 'message' => 'Compra não encontrada.'], 404);
        }
        if (($compra['status'] ?? '') === 'paid') {
            $this->json([
                'ok' => true,
                'paid' => true,
                'message' => 'Pagamento confirmado e créditos liberados.',
                'creditos' => (float) ($compra['creditos'] ?? 0),
                'redirect_url' => URL . '/professor/carteira',
            ]);
        }

        require_once __DIR__ . '/../../Services/TenantCreditsCheckoutService.php';
        require_once __DIR__ . '/../../Services/CreditosTenantCheckoutHelper.php';
        $escolaId = \App\Services\CreditosTenantCheckoutHelper::escolaIdFromConfig($this->config);
        $result = \App\Services\TenantCreditsCheckoutService::verifyAndFulfill(
            $this->db->getPdo(),
            $escolaId,
            $compra
        );

        $this->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'paid' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? 'Pagamento ainda não confirmado.'),
            'status' => (string) ($result['status'] ?? ''),
            'creditos' => (float) ($compra['creditos'] ?? 0),
            'redirect_url' => URL . '/professor/carteira',
        ]);
    }

    public function carteiraComprarSimular($compraId)
    {
        if (!defined('DEBUG') || !DEBUG) {
            http_response_code(404);
            exit;
        }
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :user_id", ['user_id' => $user['id']]);
        if (!$professor) {
            $this->redirect('/logout');
        }
        $compra = $this->db->fetch(
            "SELECT c.*, p.creditos FROM compras_creditos c JOIN pacotes_creditos p ON p.id = c.pacote_id WHERE c.id = ? AND c.user_type = 'professor' AND c.user_id = ? AND c.status = 'pending'",
            [$compraId, $professor['id']]
        );
        if (!$compra) {
            $this->setFlashMessage('Compra não encontrada ou já processada.', 'error');
            header('Location: ' . URL . '/professor/carteira');
            exit;
        }
        require_once __DIR__ . '/../../Services/CreditosService.php';
        $creditosService = new \App\Services\CreditosService();
        $creditosService->adicionarCreditos('professor', (int) $professor['id'], (float) ($compra['creditos'] ?? 0), 'compra', null, (string) $compraId);
        $this->db->query("UPDATE compras_creditos SET status = 'paid', updated_at = NOW() WHERE id = ?", [$compraId]);
        $this->setFlashMessage('Pagamento simulado: ' . $compra['creditos'] . ' créditos adicionados.', 'success');
        header('Location: ' . URL . '/professor/carteira');
        exit;
    }

    public function carteiraPlanos()
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :user_id", ['user_id' => $user['id']]);
        if (!$professor) {
            $this->redirect('/logout');
        }
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('creditos_liberar_b2c', '0') !== '1') {
            $this->setFlashMessage('Planos de assinatura não estão disponíveis para esta escola.', 'info');
            header('Location: ' . URL . '/professor/carteira');
            exit;
        }
        try {
            $planos = $this->db->fetchAll("SELECT id, nome, creditos_mensais, valor_mensal, destino FROM planos_creditos WHERE ativo = 1 AND (destino = 'professor' OR destino = 'ambos') ORDER BY creditos_mensais");
            $assinaturas = $this->db->fetchAll("SELECT a.id, a.plano_id, a.inicio_em, a.ativa, p.nome as plano_nome, p.creditos_mensais FROM assinaturas_creditos a JOIN planos_creditos p ON p.id = a.plano_id WHERE a.user_type = 'professor' AND a.user_id = ? ORDER BY a.id DESC", [$professor['id']]);
        } catch (Exception $e) {
            $planos = [];
            $assinaturas = [];
        }
        $data = [
            'title' => 'Planos de créditos - EducaTudo',
            'user' => $user,
            'professor' => $professor,
            'planos' => $planos,
            'assinaturas' => $assinaturas,
            'current_page' => 'carteira',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('professor', 'teacher/carteira-planos', $data);
    }

    public function carteiraPlanosAssinar()
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :user_id", ['user_id' => $user['id']]);
        if (!$professor) {
            $this->redirect('/logout');
        }
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (LayoutHelper::get('creditos_liberar_b2c', '0') !== '1') {
            $this->setFlashMessage('Planos de assinatura não estão disponíveis para esta escola.', 'info');
            header('Location: ' . URL . '/professor/carteira');
            exit;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            header('Location: ' . URL . '/professor/carteira/planos');
            exit;
        }
        $planoId = (int) ($_POST['plano_id'] ?? 0);
        $plano = $this->db->fetch("SELECT id, creditos_mensais FROM planos_creditos WHERE id = ? AND ativo = 1 AND (destino = 'professor' OR destino = 'ambos')", [$planoId]);
        if (!$plano) {
            $this->setFlashMessage('Plano inválido.', 'error');
            header('Location: ' . URL . '/professor/carteira/planos');
            exit;
        }
        $hoje = date('Y-m-d');
        $this->db->insert(
            "INSERT INTO assinaturas_creditos (user_type, user_id, plano_id, inicio_em, ativa) VALUES ('professor', ?, ?, ?, 1)",
            [$professor['id'], $planoId, $hoje]
        );
        $this->setFlashMessage('Assinatura ativada. Você receberá ' . $plano['creditos_mensais'] . ' créditos por mês.', 'success');
        header('Location: ' . URL . '/professor/carteira/planos');
        exit;
    }
    
    /**
     * Lista jornadas do professor
     */
    public function jornadas()
    {
        $user = $this->authManager->getUser();
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        $jornadas = $this->db->fetchAll(
            "SELECT j.*, t.nome as turma_nome,
                (SELECT COUNT(*) FROM exercicios WHERE jornada_id = j.id) as total_exercicios
             FROM jornadas j
             JOIN turmas t ON j.turma_id = t.id
             WHERE j.professor_id = :prof_id AND (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC",
            ['prof_id' => $professor['id']]
        );
        
        $data = [
            'title' => 'Minhas Jornadas - EducaTudo',
            'jornadas' => $jornadas
        ];
        
        $this->view('teacher/jornadas', $data);
    }
    
    /**
     * Cria nova jornada
     */
    public function criarJornada()
    {
        // Busca turmas disponíveis
        $turmas = $this->db->fetchAll("SELECT * FROM turmas WHERE ativo = 1 ORDER BY nome ASC");
        
        $data = [
            'title' => 'Criar Jornada - EducaTudo',
            'turmas' => $turmas
        ];
        
        $this->view('teacher/criar-jornada', $data);
    }
    
    /**
     * Salva nova jornada
     */
    public function salvarJornada()
    {
        $user = $this->authManager->getUser();
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        $titulo = $_POST['titulo'] ?? '';
        $descricao = $_POST['descricao'] ?? '';
        $turma_id = $_POST['turma_id'] ?? '';
        $estrutura = json_encode($_POST['estrutura'] ?? []);
        
        if (empty($titulo) || empty($turma_id)) {
            $this->json(['error' => 'Título e turma são obrigatórios'], 400);
        }
        
        $jornada_id = $this->db->insert(
            "INSERT INTO jornadas (professor_id, turma_id, titulo, descricao, estrutura) VALUES (:prof_id, :turma_id, :titulo, :descricao, :estrutura)",
            [
                'prof_id' => $professor['id'],
                'turma_id' => $turma_id,
                'titulo' => $titulo,
                'descricao' => $descricao,
                'estrutura' => $estrutura
            ]
        );
        
        $this->json(['success' => true, 'jornada_id' => $jornada_id]);
    }
    
    /**
     * Lista exercícios do professor
     */
    public function exercicios()
    {
        $user = $this->authManager->getUser();
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        $exercicios = $this->db->fetchAll(
            "SELECT e.*, j.titulo as jornada_titulo
             FROM exercicios e
             JOIN jornadas j ON e.jornada_id = j.id
             WHERE j.professor_id = :prof_id
             ORDER BY e.created_at DESC",
            ['prof_id' => $professor['id']]
        );
        
        $data = [
            'title' => 'Meus Exercícios - EducaTudo',
            'exercicios' => $exercicios
        ];
        
        $this->view('teacher/exercicios', $data);
    }
    
    /**
     * Lista alunos do professor (pasta Student)
     */
    public function student()
    {
        $user = $this->authManager->getUser();
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        // Busca alunos das turmas onde o professor leciona
        $turmas_professor = json_decode($professor['turmas'], true) ?: [];
        
        if (empty($turmas_professor)) {
            $alunos = [];
        } else {
            $placeholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
            $alunos = $this->db->fetchAll(
                "SELECT DISTINCT a.*, t.nome as turma_nome,
                    COALESCE(j.total_jornadas, 0) as total_jornadas
                 FROM alunos a
                 JOIN turmas t ON a.turma_id = t.id
                 LEFT JOIN (
                     SELECT turma_id, COUNT(*) as total_jornadas
                     FROM jornadas 
                     WHERE professor_id = ?
                     GROUP BY turma_id
                 ) j ON j.turma_id = a.turma_id
                 WHERE a.turma_id IN ($placeholders) AND a.ativo = 1
                 ORDER BY a.nome ASC",
                array_merge([$professor['id']], $turmas_professor)
            );
        }
        
        $data = [
            'title' => 'Student Management - EducaTudo',
            'alunos' => $alunos,
            'user' => $user,
            'current_page' => 'student',
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('professor', 'teacher/student', $data);
    }
    
    /**
     * Visualiza dados de um aluno específico
     */
    public function viewStudent($id)
    {
        $user = $this->authManager->getUser();
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        // Busca dados do aluno
        $turmas_professor = json_decode($professor['turmas'], true) ?: [];
        
        if (empty($turmas_professor)) {
            $aluno = null;
        } else {
            $placeholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome
                 FROM alunos a
                 JOIN turmas t ON a.turma_id = t.id
                 WHERE a.id = ? AND a.turma_id IN ($placeholders)",
                array_merge([$id], $turmas_professor)
            );
        }
        
        if (!$aluno) {
            $this->redirect('/teacher/student');
        }
        
        // Busca estatísticas do aluno
        // Total de Jornadas
        $total_jornadas = $this->db->fetch(
            "SELECT COUNT(*) as total 
             FROM jornadas j
             WHERE j.turma_id = :turma_id AND j.status = 'ativa'",
            ['turma_id' => $aluno['turma_id']]
        )['total'] ?? 0;
        
        // Jornadas Concluídas (jornadas onde o aluno completou todas as atividades)
        $jornadas_concluidas = $this->db->fetch(
            "SELECT COUNT(DISTINCT j.id) as total
             FROM jornadas j
             WHERE j.turma_id = :turma_id 
             AND j.status = 'ativa'
             AND NOT EXISTS (
                 SELECT 1 FROM jornadas_modulos jm
                 WHERE jm.jornada_id = j.id AND jm.status = 'ativo'
                 AND NOT EXISTS (
                     SELECT 1 FROM jornadas_progresso_alunos jpa
                     WHERE jpa.modulo_id = jm.id 
                     AND jpa.aluno_id = :aluno_id
                     AND jpa.status = 'concluido'
                 )
             )",
            ['turma_id' => $aluno['turma_id'], 'aluno_id' => $aluno['id']]
        )['total'] ?? 0;
        
        $jornadas_pendentes = $total_jornadas - $jornadas_concluidas;
        
        // Mensagens com o Prof
        $mensagens_prof = $this->db->fetch(
            "SELECT COUNT(*) as total
             FROM jornadas_mensagens jm
             WHERE jm.aluno_id = :aluno_id
             AND jm.professor_id = :prof_id",
            ['aluno_id' => $aluno['id'], 'prof_id' => $professor['id']]
        )['total'] ?? 0;
        
        // Nota Última Prova
        $ultima_prova = $this->db->fetch(
            "SELECT pr.nota, p.valor_total
             FROM provas_realizacoes pr
             JOIN provas p ON pr.prova_id = p.id
             WHERE pr.aluno_id = :aluno_id
             AND pr.status = 'finalizado'
             ORDER BY pr.finalizado_em DESC
             LIMIT 1",
            ['aluno_id' => $aluno['id']]
        );
        
        $nota_ultima_prova = null;
        if ($ultima_prova && isset($ultima_prova['nota'])) {
            $valor_total = floatval($ultima_prova['valor_total'] ?? 100);
            $nota = floatval($ultima_prova['nota']);
            $nota_ultima_prova = number_format(($nota / $valor_total) * 10, 1);
        }
        
        $data = [
            'title' => 'Student Details - EducaTudo',
            'aluno' => $aluno,
            'user' => $user,
            'current_page' => 'student',
            'csrf_token' => $this->generateCsrfToken(),
            'total_jornadas' => $total_jornadas,
            'jornadas_concluidas' => $jornadas_concluidas,
            'jornadas_pendentes' => $jornadas_pendentes,
            'mensagens_prof' => $mensagens_prof,
            'nota_ultima_prova' => $nota_ultima_prova
        ];
        
        $this->viewWithLayout('professor', 'teacher/student-view', $data);
    }
    
    /**
     * Exibe provas semanais do aluno
     */
    public function studentProvas($id)
    {
        $user = $this->authManager->getUser();
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        // Busca dados do aluno
        $turmas_professor = json_decode($professor['turmas'], true) ?: [];
        
        if (empty($turmas_professor)) {
            $aluno = null;
        } else {
            $placeholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome
                 FROM alunos a
                 JOIN turmas t ON a.turma_id = t.id
                 WHERE a.id = ? AND a.turma_id IN ($placeholders)",
                array_merge([$id], $turmas_professor)
            );
        }
        
        if (!$aluno) {
            $this->redirect('/teacher/student');
            return;
        }
        
        // Busca provas do aluno
        $provas = $this->db->fetchAll(
            "SELECT DISTINCT p.*, 
                    m.nome as materia_nome,
                    pr.id as realizacao_id,
                    pr.status as realizacao_status,
                    pr.nota as realizacao_nota,
                    pr.finalizado_em as finalizado_em,
                    pr.tempo_gasto as tempo_gasto
             FROM provas p
             LEFT JOIN materias m ON p.materia_id = m.id
             LEFT JOIN provas_turmas pt ON p.id = pt.prova_id
             LEFT JOIN provas_realizacoes pr ON p.id = pr.prova_id AND pr.aluno_id = :aluno_id
             WHERE (p.turma_id = :turma_id OR pt.turma_id = :turma_id_pt)
             AND p.ativo = 1
             AND p.deleted_at IS NULL
             ORDER BY p.data_inicio DESC, p.created_at DESC",
            [
                'aluno_id' => $aluno['id'],
                'turma_id' => $aluno['turma_id'],
                'turma_id_pt' => $aluno['turma_id']
            ]
        );
        
        $data = [
            'title' => 'Provas do Aluno - EducaTudo',
            'aluno' => $aluno,
            'provas' => $provas,
            'user' => $user,
            'current_page' => 'student',
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('professor', 'teacher/student-exams', $data);
    }
    
    /**
     * Exibe relatório completo do aluno
     */
    public function studentRelatorio($id)
    {
        $user = $this->authManager->getUser();
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        // Busca dados do aluno
        $turmas_professor = json_decode($professor['turmas'], true) ?: [];
        
        if (empty($turmas_professor)) {
            $aluno = null;
        } else {
            $placeholders = str_repeat('?,', count($turmas_professor) - 1) . '?';
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome
                 FROM alunos a
                 JOIN turmas t ON a.turma_id = t.id
                 WHERE a.id = ? AND a.turma_id IN ($placeholders)",
                array_merge([$id], $turmas_professor)
            );
        }
        
        if (!$aluno) {
            $this->redirect('/teacher/student');
            return;
        }
        
        // Histórico de Provas
        $provas = $this->db->fetchAll(
            "SELECT p.*, 
                    m.nome as materia_nome,
                    pr.nota as realizacao_nota,
                    pr.finalizado_em,
                    pr.tempo_gasto,
                    pr.status as realizacao_status
             FROM provas p
             LEFT JOIN materias m ON p.materia_id = m.id
             LEFT JOIN provas_turmas pt ON p.id = pt.prova_id
             LEFT JOIN provas_realizacoes pr ON p.id = pr.prova_id AND pr.aluno_id = :aluno_id
             WHERE (p.turma_id = :turma_id OR pt.turma_id = :turma_id_pt)
             AND p.ativo = 1
             AND p.deleted_at IS NULL
             AND pr.status = 'finalizado'
             ORDER BY pr.finalizado_em DESC",
            [
                'aluno_id' => $aluno['id'],
                'turma_id' => $aluno['turma_id'],
                'turma_id_pt' => $aluno['turma_id']
            ]
        );
        
        // Histórico de Exercícios (Jornadas)
        $exercicios = $this->db->fetchAll(
            "SELECT jpa.*,
                    jm.titulo as modulo_titulo,
                    j.titulo as jornada_titulo,
                    jme.titulo as exercicio_titulo,
                    jme.enunciado as exercicio_enunciado,
                    jme.resposta_correta
             FROM jornadas_progresso_alunos jpa
             LEFT JOIN jornadas_modulos jm ON jpa.modulo_id = jm.id
             LEFT JOIN jornadas j ON jm.jornada_id = j.id
             LEFT JOIN jornadas_modulos_exercicios jme ON jpa.exercicio_modulo_id = jme.id
             WHERE jpa.aluno_id = :aluno_id
             AND jpa.status = 'concluido'
             AND jpa.pontuacao IS NOT NULL
             ORDER BY jpa.data_conclusao DESC
             LIMIT 50",
            ['aluno_id' => $aluno['id']]
        );
        
        // Interações (Mensagens)
        $interacoes = $this->db->fetchAll(
            "SELECT jm.*,
                    j.titulo as jornada_titulo
             FROM jornadas_mensagens jm
             LEFT JOIN jornadas j ON jm.jornada_id = j.id
             WHERE jm.aluno_id = :aluno_id
             AND jm.professor_id = :prof_id
             ORDER BY jm.created_at DESC
             LIMIT 50",
            [
                'aluno_id' => $aluno['id'],
                'prof_id' => $professor['id']
            ]
        );
        
        // Estatísticas gerais
        $estatisticas = [
            'total_provas' => count($provas),
            'media_provas' => 0,
            'total_exercicios' => count($exercicios),
            'acertos_exercicios' => 0,
            'total_interacoes' => count($interacoes),
            'ultima_atividade' => null
        ];
        
        // Calcular média das provas
        if (!empty($provas)) {
            $soma_notas = 0;
            $soma_valor_total = 0;
            foreach ($provas as $prova) {
                if ($prova['realizacao_nota'] !== null) {
                    $soma_notas += floatval($prova['realizacao_nota']);
                    $soma_valor_total += floatval($prova['valor_total']);
                }
            }
            if ($soma_valor_total > 0) {
                $estatisticas['media_provas'] = ($soma_notas / $soma_valor_total) * 10;
            }
        }
        
        // Calcular acertos nos exercícios
        if (!empty($exercicios)) {
            $acertos = 0;
            foreach ($exercicios as $exercicio) {
                if ($exercicio['pontuacao'] > 0) {
                    $acertos++;
                }
            }
            $estatisticas['acertos_exercicios'] = $acertos;
        }
        
        // Última atividade
        $ultimas_atividades = [];
        foreach ($provas as $prova) {
            if ($prova['finalizado_em']) {
                $ultimas_atividades[] = $prova['finalizado_em'];
            }
        }
        foreach ($exercicios as $exercicio) {
            if ($exercicio['data_conclusao']) {
                $ultimas_atividades[] = $exercicio['data_conclusao'];
            }
        }
        foreach ($interacoes as $interacao) {
            if ($interacao['created_at']) {
                $ultimas_atividades[] = $interacao['created_at'];
            }
        }
        if (!empty($ultimas_atividades)) {
            rsort($ultimas_atividades);
            $estatisticas['ultima_atividade'] = $ultimas_atividades[0];
        }
        
        // Evolução ao longo do tempo (últimos 6 meses)
        $evolucao = [];
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $data = date('Y-m', strtotime("-$i months"));
            $meses[] = $data;
            $evolucao[$data] = [
                'provas' => 0,
                'nota_media' => 0,
                'exercicios' => 0,
                'interacoes' => 0
            ];
        }
        
        // Agrupar provas por mês
        foreach ($provas as $prova) {
            if ($prova['finalizado_em']) {
                $mes = date('Y-m', strtotime($prova['finalizado_em']));
                if (isset($evolucao[$mes])) {
                    $evolucao[$mes]['provas']++;
                    if ($prova['realizacao_nota'] !== null && $prova['valor_total'] > 0) {
                        $nota_percentual = (floatval($prova['realizacao_nota']) / floatval($prova['valor_total'])) * 10;
                        $evolucao[$mes]['nota_media'] = ($evolucao[$mes]['nota_media'] * ($evolucao[$mes]['provas'] - 1) + $nota_percentual) / $evolucao[$mes]['provas'];
                    }
                }
            }
        }
        
        // Agrupar exercícios por mês
        foreach ($exercicios as $exercicio) {
            if ($exercicio['data_conclusao']) {
                $mes = date('Y-m', strtotime($exercicio['data_conclusao']));
                if (isset($evolucao[$mes])) {
                    $evolucao[$mes]['exercicios']++;
                }
            }
        }
        
        // Agrupar interações por mês
        foreach ($interacoes as $interacao) {
            if ($interacao['created_at']) {
                $mes = date('Y-m', strtotime($interacao['created_at']));
                if (isset($evolucao[$mes])) {
                    $evolucao[$mes]['interacoes']++;
                }
            }
        }
        
        $data = [
            'title' => 'Relatório do Aluno - EducaTudo',
            'aluno' => $aluno,
            'provas' => $provas,
            'exercicios' => $exercicios,
            'interacoes' => $interacoes,
            'estatisticas' => $estatisticas,
            'evolucao' => $evolucao,
            'meses' => $meses,
            'user' => $user,
            'current_page' => 'student',
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('professor', 'teacher/student-relatorio', $data);
    }
    
    /**
     * Atualiza senha do aluno para senha padrão (123456)
     */
    public function updateStudentPassword($id)
    {
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
            
            $user = $this->authManager->getUser();
            error_log('Usuário logado: ' . json_encode($user));
            
            $professor = $this->db->fetch(
                "SELECT * FROM professores WHERE id = :user_id",
                ['user_id' => $user['id']]
            );
            
            if (!$professor) {
                error_log('Professor não encontrado: ' . $user['id']);
                throw new Exception('Professor não encontrado');
            }
            
            error_log('Professor encontrado: ' . json_encode($professor));
            
            // Verifica se o aluno existe
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :aluno_id",
                ['aluno_id' => $id]
            );
            
            if (!$aluno) {
                error_log('Aluno não encontrado: ' . $id);
                throw new Exception('Aluno não encontrado');
            }
            
            // Professores podem alterar senha de qualquer aluno
            error_log('Aluno encontrado: ' . $aluno['nome'] . ' (ID: ' . $aluno['id'] . ')');
            
            // Define senha padrão
            $senha_padrao = '123456';
            $senha_hash = password_hash($senha_padrao, PASSWORD_DEFAULT);
            
            error_log('Senha hash gerada para aluno: ' . $id);
            
            // Atualiza senha do aluno
            $result = $this->db->update(
                "UPDATE alunos SET senha_hash = :senha WHERE id = :aluno_id",
                ['senha' => $senha_hash, 'aluno_id' => $aluno['id']]
            );
            
            if ($result === 0) {
                error_log('Nenhuma linha foi atualizada na tabela alunos para ID: ' . $aluno['id']);
                throw new Exception('Erro ao atualizar senha no banco de dados');
            }
            
            error_log('Senha atualizada com sucesso para aluno: ' . $aluno['id']);
            
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
    
    /**
     * Student Journey (visão do professor)
     */
    public function studentJourney()
    {
        $user = $this->authManager->getUser();
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        // Busca jornadas do professor com alunos associados
        $jornadas = $this->db->fetchAll(
            "SELECT j.*, t.nome as turma_nome,
                (SELECT COUNT(*) FROM alunos WHERE turma_id = j.turma_id AND ativo = 1) as total_alunos,
                (SELECT COUNT(*) FROM exercicios WHERE jornada_id = j.id) as total_exercicios
             FROM jornadas j
             JOIN turmas t ON j.turma_id = t.id
             WHERE j.professor_id = :prof_id
             ORDER BY j.created_at DESC",
            ['prof_id' => $professor['id']]
        );
        
        // Processa jornadas para calcular status baseado em data e hora
        $dataAtual = date('Y-m-d');
        foreach ($jornadas as &$jornada) {
            $estrutura = json_decode($jornada['estrutura'], true) ?: [];
            $dataInicio = $estrutura['data_inicio'] ?? null;
            $dataFim = $estrutura['data_fim'] ?? null;
            $horaInicio = trim((string)($estrutura['hora_inicio'] ?? '')) ?: '00:00';
            $horaFim = trim((string)($estrutura['hora_fim'] ?? '')) ?: '23:59:59';
            
            if ($dataInicio && $dataFim) {
                $tsNow = time();
                $tsInicio = strtotime($dataInicio . ' ' . $horaInicio);
                $tsFim = strtotime($dataFim . ' ' . $horaFim);
                if ($tsInicio !== false && $tsFim !== false) {
                    if ($tsNow < $tsInicio) {
                        $jornada['status_jornada'] = 'aguardando';
                    } elseif ($tsNow > $tsFim) {
                        $jornada['status_jornada'] = 'concluido';
                    } else {
                        $jornada['status_jornada'] = 'em_andamento';
                    }
                } else {
                    if ($dataAtual < $dataInicio) {
                        $jornada['status_jornada'] = 'aguardando';
                    } elseif ($dataAtual > $dataFim) {
                        $jornada['status_jornada'] = 'concluido';
                    } else {
                        $jornada['status_jornada'] = 'em_andamento';
                    }
                }
            } else {
                $jornada['status_jornada'] = 'em_andamento';
            }
            
            $jornada['data_inicio'] = $dataInicio;
            $jornada['data_fim'] = $dataFim;
        }
        
        $data = [
            'title' => 'Student Journey - EducaTudo',
            'jornadas' => $jornadas,
            'user' => $user,
            'current_page' => 'student-journey'
        ];
        
        $this->viewWithLayout('professor', 'teacher/student-journey', $data);
    }
    
    /**
     * Gerador de Slides com Gamma API
     */
    public function gerarSlides()
    {
        $user = $this->authManager->getUser();
        
        // Verificação adicional de segurança
        if (!$user || $user['tipo'] !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? 'aluno');
            return;
        }
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado', 'error');
            $this->redirect('/teacher/dashboard');
            return;
        }
        
        $data = [
            'title' => 'Gerador de Slides - EducaTudo',
            'professor' => $professor,
            'user' => $user,
            'current_page' => 'gerar-slides'
        ];
        
        $this->viewWithLayout('professor', 'teacher/gerar-slides', $data);
    }
    
    /**
     * Lista os slides salvos do professor
     */
    public function listarSlides()
    {
        $user = $this->authManager->getUser();
        
        // Verificação adicional de segurança
        if (!$user || $user['tipo'] !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo'] ?? 'aluno');
            return;
        }
        
        $professor = $this->db->fetch(
            "SELECT * FROM professores WHERE id = :user_id",
            ['user_id' => $user['id']]
        );
        
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado', 'error');
            $this->redirect('/professor/dashboard');
            return;
        }
        
        // Buscar slides salvos do professor
        $slides = $this->db->fetchAll(
            "SELECT * FROM professores_slides 
             WHERE professor_id = :prof_id 
             ORDER BY created_at DESC",
            ['prof_id' => $professor['id']]
        );
        
        $data = [
            'title' => 'Meus Slides - EducaTudo',
            'professor' => $professor,
            'user' => $user,
            'slides' => $slides,
            'current_page' => 'meus-slides'
        ];
        
        $this->viewWithLayout('professor', 'teacher/meus-slides', $data);
    }
    
    /**
     * Upload de foto de perfil
     */
    /**
     * Página de Tutoriais
     */
    public function tutoriais()
    {
        $user = $this->authManager->getUser();
        
        // Buscar todos os tutoriais ativos
        $tutoriais = $this->db->fetchAll(
            "SELECT * FROM tutoriais WHERE ativo = 1 ORDER BY ordem ASC, id ASC"
        );
        
        $data = [
            'title' => 'Tutoriais - Portal do Professor',
            'user' => $user,
            'tutoriais' => $tutoriais,
            'current_page' => 'tutoriais'
        ];
        
        $this->viewWithLayout('professor', 'teacher/tutoriais', $data);
    }
    
    public function uploadFotoPerfil()
    {
        $user = $this->authManager->getUser();
        
        if (!$user || $user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        // Verificar CSRF token
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }
        
        try {
            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload da imagem');
            }
            
            $file = $_FILES['foto'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception('Tipo de arquivo não permitido. Use: ' . implode(', ', $allowedExtensions));
            }
            
            // Verificar tamanho (máximo 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Arquivo muito grande. Máximo 5MB');
            }
            
            // Buscar professor atual
            $professor = $this->db->fetch(
                "SELECT * FROM professores WHERE id = :prof_id",
                ['prof_id' => $user['id']]
            );
            
            if (!$professor) {
                throw new Exception('Professor não encontrado');
            }

            require_once __DIR__ . '/../../Services/MediaStorageService.php';
            $media = new MediaStorageService($this->config);
            
            // Gerar nome único para o arquivo
            $fileName = 'prof_' . $user['id'] . '_' . time() . '.' . $fileExtension;
            $contentType = $file['type'] ?? 'application/octet-stream';
            if (!$media->put('professores', $fileName, $file['tmp_name'], $contentType)) {
                error_log('TeacherController uploadFotoPerfil: put() retornou false. Verifique permissões da pasta storage/files no servidor.');
                throw new Exception('Erro ao salvar arquivo. Verifique se a pasta storage/files existe e tem permissão de escrita no servidor.');
            }
            
            // Remover foto anterior se existir
            $avatarUrlAtual = $professor['avatar_url'] ?? null;
            if (!empty($avatarUrlAtual)) {
                $this->removerAvatarAnteriorStorage($media, $avatarUrlAtual);
            }
            
            // Salvar URL no banco (incluir tenant para servir de storage/files/{slug}/professores).
            $url = '/media/serve?type=professores&key=' . rawurlencode($fileName);
            $slug = trim((string) ($this->config['tenant']['slug'] ?? $this->config['school']['code'] ?? ''));
            if ($slug !== '') {
                $url .= '&tenant=' . rawurlencode($slug);
            }

            // Tentar atualizar avatar_url (a coluna será criada pela migration)
            try {
                $this->db->update(
                    "UPDATE professores SET avatar_url = :avatar_url WHERE id = :prof_id",
                    [
                        'avatar_url' => $url,
                        'prof_id' => $user['id']
                    ]
                );
            } catch (Exception $e) {
                // Se a coluna não existir, apenas logar o erro e continuar
                // A migration deve ser executada para adicionar a coluna
                error_log("Aviso: Erro ao atualizar avatar_url. Execute a migration 20260126_140000_add_avatar_url_to_professores.sql: " . $e->getMessage());
            }
            
            // Atualizar também na sessão
            $_SESSION['avatar_url'] = $url;
            
            $this->json([
                'success' => true,
                'message' => 'Foto de perfil atualizada com sucesso!',
                'url' => $url
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao fazer upload de foto de perfil: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Redireciona para o dashboard correto baseado no tipo
     */
    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'aluno':
                $this->redirect('/dashboard');
                break;
            case 'admin_escola':
                $this->redirect('/admin/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            case 'monitor':
                $this->redirect('/monitor/dashboard');
                break;
            default:
                $this->redirect('/professor');
        }
    }

    /**
     * Relatório agregado de jornadas — apenas jornadas do professor logado.
     */
    public function jornadasRelatorio()
    {
        $user = $this->authManager->getUser();
        $professorId = (int) ($user['id'] ?? 0);
        if ($professorId <= 0) {
            $this->redirect(URL . '/professor/dashboard');
            return;
        }

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
            'jr_materia_id' => $_GET['jr_materia_id'] ?? '',
            'jr_jornada_id' => $_GET['jr_jornada_id'] ?? '',
            'jr_turma_ano_letivo' => $_GET['jr_turma_ano_letivo'] ?? '',
            'jr_avaliativo' => $_GET['jr_avaliativo'] ?? '',
            'jr_somente_atencao' => !empty($_GET['jr_somente_atencao']) ? 1 : 0,
            'jr_tempo_ordem' => $_GET['jr_tempo_ordem'] ?? '',
            'jr_modo_materia' => ($_GET['jr_modo_materia'] ?? 'total') === 'por_materia' ? 'por_materia' : 'total',
            'executar' => !empty($_GET['executar']) ? 1 : 0,
            'jr_professor_id' => $professorId,
        ];
        $filtros['page'] = max(1, (int) $filtros['page']);
        $filtros['limit'] = max(10, min(500, (int) $filtros['limit']));
        if ($filtros['tipo'] === 'usuario' && empty($filtros['aluno_id']) && $filtros['aluno_nome'] !== '') {
            $alunoMatch = $this->db->fetch(
                "SELECT a.id, a.nome FROM alunos a
                 INNER JOIN turmas t ON a.turma_id = t.id
                 WHERE a.ativo = 1
                   AND a.turma_id IN (
                      SELECT DISTINCT j2.turma_id FROM jornadas j2
                      WHERE j2.professor_id = :pid AND (j2.ativo = 1 OR j2.ativo IS NULL)
                   )
                   AND a.nome LIKE :nome
                 ORDER BY (a.nome = :nome_exato) DESC, a.nome ASC
                 LIMIT 1",
                [
                    'pid' => $professorId,
                    'nome' => '%' . $filtros['aluno_nome'] . '%',
                    'nome_exato' => $filtros['aluno_nome'],
                ]
            );
            if (!empty($alunoMatch['id'])) {
                $filtros['aluno_id'] = (int) $alunoMatch['id'];
                $filtros['aluno_nome'] = (string) ($alunoMatch['nome'] ?? $filtros['aluno_nome']);
            }
        }

        if (!empty($filtros['jr_jornada_id'])) {
            $jid = (int) $filtros['jr_jornada_id'];
            $ok = $this->db->fetch(
                "SELECT id FROM jornadas WHERE id = :id AND professor_id = :pid AND (ativo = 1 OR ativo IS NULL)",
                ['id' => $jid, 'pid' => $professorId]
            );
            if (!$ok) {
                $filtros['jr_jornada_id'] = '';
            }
        }

        require_once __DIR__ . '/../../Services/JornadasRelatorioService.php';
        $jornadas_relatorio = [];
        if (!empty($filtros['executar'])) {
            $jornadasRelatorioService = new JornadasRelatorioService($this->db);
            $jornadas_relatorio = $jornadasRelatorioService->relatorio($filtros);
        }

        $turmas = $this->db->fetchAll(
            "SELECT DISTINCT t.id, t.nome
             FROM jornadas j
             INNER JOIN turmas t ON j.turma_id = t.id
             WHERE j.professor_id = :pid AND (j.ativo = 1 OR j.ativo IS NULL) AND t.ativo = 1
             ORDER BY t.nome ASC",
            ['pid' => $professorId]
        );

        $alunos = $this->db->fetchAll(
            "SELECT DISTINCT a.id, a.nome, a.ra, t.nome AS turma_nome
             FROM alunos a
             INNER JOIN turmas t ON a.turma_id = t.id
             WHERE a.ativo = 1
               AND a.turma_id IN (
                  SELECT DISTINCT j2.turma_id FROM jornadas j2
                  WHERE j2.professor_id = :pid AND (j2.ativo = 1 OR j2.ativo IS NULL)
               )
             ORDER BY a.nome ASC",
            ['pid' => $professorId]
        );

        $materias_jornadas_rel = $this->db->fetchAll(
            "SELECT DISTINCT j.materia_id AS id, COALESCE(jm.nome, m.nome) AS nome
             FROM jornadas j
             LEFT JOIN jornadas_materias jm ON j.materia_id = jm.id
             LEFT JOIN materias m ON j.materia_id = m.id
             WHERE j.professor_id = :pid AND (j.ativo = 1 OR j.ativo IS NULL) AND j.materia_id IS NOT NULL
               AND TRIM(COALESCE(jm.nome, m.nome, '')) <> ''
             ORDER BY nome ASC",
            ['pid' => $professorId]
        );

        $anos_turmas_rel = $this->db->fetchAll(
            "SELECT DISTINCT t.ano_letivo
             FROM jornadas j
             INNER JOIN turmas t ON j.turma_id = t.id
             WHERE j.professor_id = :pid AND (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY t.ano_letivo DESC",
            ['pid' => $professorId]
        );

        $jornadas_select_rel = $this->db->fetchAll(
            "SELECT j.id, j.titulo, t.nome AS turma_nome
             FROM jornadas j
             INNER JOIN turmas t ON j.turma_id = t.id
             WHERE j.professor_id = :pid AND (j.ativo = 1 OR j.ativo IS NULL)
             ORDER BY j.created_at DESC
             LIMIT 500",
            ['pid' => $professorId]
        );

        $this->viewWithLayout('professor', 'teacher/jornadas-relatorio', [
            'title' => 'Relatório de Jornadas - Professor',
            'user' => $user,
            'current_page' => 'jornadas_relatorio',
            'filtros' => $filtros,
            'turmas' => $turmas,
            'alunos' => $alunos,
            'materias_jornadas_rel' => $materias_jornadas_rel,
            'anos_turmas_rel' => $anos_turmas_rel,
            'jornadas_select_rel' => $jornadas_select_rel,
            'jornadas_relatorio' => $jornadas_relatorio,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Resolve URL/caminho salvo do avatar para caminho absoluto no filesystem.
     */
    private function resolverCaminhoArquivoAvatar($avatarRef)
    {
        $ref = trim((string) $avatarRef);
        if ($ref === '') {
            return null;
        }

        $path = parse_url($ref, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $ref;
        }

        $path = str_replace('\\', '/', $path);
        if (strpos($path, '/public/uploads/') !== false) {
            $path = substr($path, strpos($path, '/public/uploads/'));
            return dirname(__DIR__, 3) . $path;
        }

        if (strpos($path, '/uploads/') !== false) {
            $path = substr($path, strpos($path, '/uploads/'));
            return dirname(__DIR__, 3) . '/public' . $path;
        }

        return null;
    }

    /**
     * Remove avatar anterior no storage atual (S3/local).
     */
    private function removerAvatarAnteriorStorage(MediaStorageService $media, $avatarRef): void
    {
        $mediaRef = $this->extrairMediaTypeKeyDeUrl($avatarRef);
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
            return;
        }

        $oldPath = $this->resolverCaminhoArquivoAvatar($avatarRef);
        if ($oldPath && file_exists($oldPath)) {
            @unlink($oldPath);
        }
    }

    /**
     * Extrai type/key/tenant de URLs no formato /media/serve?type=...&key=...&tenant=...
     */
    private function extrairMediaTypeKeyDeUrl($avatarRef): ?array
    {
        $ref = trim((string) $avatarRef);
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

    public function calendarioLetivo(): void
    {
        require_once __DIR__ . '/../../Services/SchoolCalendarService.php';
        $ano = (int) ($_GET['ano'] ?? date('Y'));
        if ($ano < 2000 || $ano > 2100) $ano = (int) date('Y');
        $service = new SchoolCalendarService($this->db);
        $cfg     = $service->getAno($ano);
        $todos   = $cfg ? $service->eventos((int) $cfg['id']) : [];
        $eventos = array_values(array_filter($todos, fn($e) => (int) ($e['visivel_professor'] ?? 0) === 1));
        $this->viewWithLayout('professor', 'teacher/calendario-letivo', [
            'title'   => 'Calendário Letivo - EducaTudo',
            'user'    => $this->auth->getUser(),
            'ano'     => $ano,
            'eventos' => $eventos,
        ]);
    }
}
}
