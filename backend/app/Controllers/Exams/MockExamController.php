<?php
/**
 * EducaTudo - Controller de Simulados Vestibulares
 * Gerencia simulados utilizando questões do banco de dados local
 */

require_once __DIR__ . '/../../Core/EnemQuestaoTextHelper.php';

if (!class_exists('MockExamController')) {
class MockExamController extends BaseController
{
    private $authManager;
    private $db;
    
    // Tipo de vestibular (apenas ENEM)
    private $tiposVestibular = ['ENEM'];
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        
        // Verifica se é aluno (o middleware Auth já verificou se está logado)
        $user = $this->authManager->getUser();
        if ($user && $user['tipo'] !== 'aluno') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }
    
    /**
     * Lista simulados disponíveis e histórico do aluno
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome as turma_nome 
             FROM alunos a 
             LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );

        if (!$aluno) {
            $this->redirect('/logout');
        }

        // Buscar simulados do aluno
        $simulados = $this->getStudentSimulados($aluno['id']);
        
        // Buscar configurações disponíveis
        $configuracoes = $this->getSimuladoConfigurations();
        
        // Buscar estatísticas gerais
        $estatisticas = $this->getStudentStatistics($aluno['id']);

        $data = [
            'title' => 'Simulados ENEM - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'simulados' => $simulados,
            'configuracoes' => $configuracoes,
            'estatisticas' => $estatisticas,
            'current_page' => 'simulados'
        ];

        $this->viewWithLayout('student', 'student/simulations/simulados', $data);
    }
    
    /**
     * Exibe página de criação de simulado
     */
    public function criar()
    {
        $user = $this->authManager->getUser();
        
        // Verificar se usuário está autenticado
        if (!$user) {
            $this->redirect('/login-aluno');
        }
        
        // Se há parâmetros GET na URL, redirecionar para limpar
        if (!empty($_GET) && count($_GET) > 1) { // Mais de 1 porque sempre tem o _token
            $this->redirect('/simulados/criar');
        }
        
        $aluno = $this->db->fetch(
            "SELECT a.* FROM alunos a 
             WHERE a.id = :user_id",
            ['user_id' => $user['id']]
        );

        if (!$aluno) {
            $this->redirect('/logout');
        }

        // Buscar provas disponíveis da API ENEM.dev
        $provasDisponiveis = $this->getAvailableExams();
        
        // Buscar configurações
        $configuracoes = $this->getSimuladoConfigurations();

        $data = [
            'title' => 'Criar Simulado - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'provas_disponiveis' => $provasDisponiveis,
            'configuracoes' => $configuracoes,
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/simulations/criar-simulado', $data);
    }
    
    /**
     * Cria um novo simulado
     */
    public function criarSimulado()
    {
        try {
            // Verificar se é POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }

            // Verificar usuário autenticado PRIMEIRO (antes do token CSRF)
            $user = $this->authManager->getUser();
            if (!$user) {
                throw new Exception('Usuário não autenticado. Faça login novamente.');
            }

            // Verificar token CSRF
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                throw new Exception('Token CSRF inválido. Recarregue a página e tente novamente.');
            }

            // Buscar dados do aluno
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome 
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id 
                 WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            // Extrair parâmetros do POST
            $ano = trim((string)($_POST['ano'] ?? ''));
            $disciplina = trim((string)($_POST['disciplina'] ?? ''));
            $tipo_vestibular = $_POST['tipo_vestibular'] ?? 'ENEM';
            $quantidade_questoes = (int)($_POST['quantidade_questoes'] ?? 10);
            $idioma = $_POST['idioma'] ?? 'Português';
            $tempo_limite = (int)($_POST['tempo_limite'] ?? 30) * 60; // Converter minutos para segundos

            // Validar parâmetros
            if ($ano === '' || !preg_match('/^\d{4}$/', $ano)) {
                throw new Exception('Selecione o ano da prova.');
            }

            if ($disciplina === '') {
                throw new Exception('Selecione a disciplina.');
            }

            if (!in_array($tipo_vestibular, $this->tiposVestibular)) {
                throw new Exception('Tipo de vestibular inválido');
            }

            if ($quantidade_questoes < 5 || $quantidade_questoes > 50) {
                throw new Exception('Quantidade de questões deve ser entre 5 e 50');
            }

            // Buscar questões do banco local baseado no tipo de vestibular
            $questoes = $this->fetchQuestionsFromDatabase($tipo_vestibular, $ano, $disciplina, $quantidade_questoes);

            if (empty($questoes)) {
                throw new Exception('Nenhuma questão encontrada para os critérios selecionados');
            }

            // Criar simulado no banco
            $simulado_id = $this->db->insert(
                "INSERT INTO simulados (aluno_id, ano, disciplina, tipo_vestibular, idioma, quantidade_questoes, tempo_limite, status) 
                 VALUES (:aluno_id, :ano, :disciplina, :tipo_vestibular, :idioma, :quantidade_questoes, :tempo_limite, 'criado')",
                [
                    'aluno_id' => $aluno['id'],
                    'ano' => $ano,
                    'disciplina' => $disciplina,
                    'tipo_vestibular' => $tipo_vestibular,
                    'idioma' => $idioma,
                    'quantidade_questoes' => $quantidade_questoes,
                    'tempo_limite' => $tempo_limite
                ]
            );

            // Salvar questões do simulado
            foreach ($questoes as $index => $questao) {
                $this->db->insert(
                    "INSERT INTO simulados_questoes (simulado_id, questao_index, questao_id, enunciado, 
                     alternativa_a, alternativa_b, alternativa_c, alternativa_d, alternativa_e, 
                     alternativa_a_file, alternativa_b_file, alternativa_c_file, alternativa_d_file, alternativa_e_file,
                     resposta_certa, materia) 
                     VALUES (:simulado_id, :questao_index, :questao_id, :enunciado, 
                     :alt_a, :alt_b, :alt_c, :alt_d, :alt_e, 
                     :alt_a_file, :alt_b_file, :alt_c_file, :alt_d_file, :alt_e_file,
                     :resposta_certa, :materia)",
                    [
                        'simulado_id' => $simulado_id,
                        'questao_index' => $index + 1,
                        'questao_id' => $questao['id'],
                        'enunciado' => $questao['enunciado'],
                        'alt_a' => $questao['alternativa_a'],
                        'alt_b' => $questao['alternativa_b'],
                        'alt_c' => $questao['alternativa_c'],
                        'alt_d' => $questao['alternativa_d'],
                        'alt_e' => $questao['alternativa_e'],
                        'alt_a_file' => $questao['alternativa_a_file'] ?? null,
                        'alt_b_file' => $questao['alternativa_b_file'] ?? null,
                        'alt_c_file' => $questao['alternativa_c_file'] ?? null,
                        'alt_d_file' => $questao['alternativa_d_file'] ?? null,
                        'alt_e_file' => $questao['alternativa_e_file'] ?? null,
                        'resposta_certa' => $questao['resposta_certa'],
                        'materia' => $questao['materia'] ?? $disciplina
                    ]
                );
            }

            // Redirecionar para iniciar o simulado
            header('Location: ' . URL . '/simulados/iniciar?id=' . $simulado_id);
            exit;

        } catch (Exception $e) {
            error_log("MockExamController::criarSimulado erro: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());

            // Mensagem amigável: usar a mensagem da exceção quando for segura (validação/negócio), senão genérica
            $msg = $e->getMessage();
            if (strpos($msg, 'não autenticado') !== false) {
                $error_message = urlencode($msg);
                header('Location: ' . URL . '/login-aluno?error=' . $error_message);
                exit;
            }
            // Mensagens de validação/negócio (curtas, sem paths) são exibidas ao usuário
            $safe = (strlen($msg) <= 200 && strpos($msg, DIRECTORY_SEPARATOR) === false && strpos($msg, '/') === false);
            $error_message = urlencode($safe ? $msg : 'Ocorreu um erro ao processar sua solicitação. Tente novamente.');
            header('Location: ' . URL . '/simulados/criar?error=' . $error_message);
            exit;
        }
    }
    
    /**
     * Inicia um simulado
     */
    public function iniciar()
    {
        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome 
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $simulado_id = $_GET['id'] ?? '';

            if (empty($simulado_id)) {
                throw new Exception('ID do simulado é obrigatório');
            }

            // Verificar se o simulado pertence ao aluno
            $simulado = $this->db->fetch(
                "SELECT * FROM simulados WHERE id = :simulado_id AND aluno_id = :aluno_id",
                ['simulado_id' => $simulado_id, 'aluno_id' => $aluno['id']]
            );

            if (!$simulado) {
                throw new Exception('Simulado não encontrado');
            }

            if ($simulado['status'] === 'finalizado') {
                throw new Exception('Simulado já foi finalizado');
            }

            // Atualizar status para em_andamento apenas se ainda estiver criado
            if ($simulado['status'] === 'criado') {
                $this->db->update(
                    "UPDATE simulados SET status = 'em_andamento', iniciado_em = NOW() WHERE id = :simulado_id",
                    ['simulado_id' => $simulado_id]
                );
            }

            // Buscar questões do simulado
            $questoes = $this->db->fetchAll(
                "SELECT * FROM simulados_questoes WHERE simulado_id = :simulado_id ORDER BY questao_index",
                ['simulado_id' => $simulado_id]
            );
            $questoes = $this->enrichQuestoesEnunciadoFromEnem($questoes, $simulado['tipo_vestibular'] ?? 'ENEM');

            $data = [
                'title' => 'Simulado ENEM - ' . $simulado['ano'],
                'user' => $user,
                'simulado' => $simulado,
                'questoes' => $questoes,
                'aluno' => $aluno,
                'csrf_token' => $this->generateCsrfToken()
            ];

            $this->viewWithLayout('student', 'student/simulations/simulado-execucao', $data);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Registra resposta de uma questão
     */
    public function responder()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome 
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $simulado_id = $_POST['simulado_id'] ?? '';
            $questao_index = (int)($_POST['questao_index'] ?? 0);
            $resposta_escolhida = $_POST['resposta_escolhida'] ?? '';
            $tempo_gasto = (int)($_POST['tempo_gasto'] ?? 0);

            if (empty($simulado_id) || $questao_index <= 0 || empty($resposta_escolhida)) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }

            // Verificar se o simulado pertence ao aluno e está em andamento
            $simulado = $this->db->fetch(
                "SELECT * FROM simulados WHERE id = :simulado_id AND aluno_id = :aluno_id AND status = 'em_andamento'",
                ['simulado_id' => $simulado_id, 'aluno_id' => $aluno['id']]
            );

            if (!$simulado) {
                throw new Exception('Simulado não encontrado ou não está em andamento');
            }

            // Buscar questão
            $questao = $this->db->fetch(
                "SELECT * FROM simulados_questoes WHERE simulado_id = :simulado_id AND questao_index = :questao_index",
                ['simulado_id' => $simulado_id, 'questao_index' => $questao_index]
            );

            if (!$questao) {
                throw new Exception('Questão não encontrada');
            }

            // Verificar se acertou
            $acertou = ($resposta_escolhida === $questao['resposta_certa']);

            // Atualizar questão (permitir atualizar resposta existente)
            $this->db->update(
                "UPDATE simulados_questoes SET 
                 resposta_aluno = :resposta_aluno, 
                 acertou = :acertou, 
                 respondido_em = NOW(),
                 tempo_gasto = :tempo_gasto
                 WHERE simulado_id = :simulado_id AND questao_index = :questao_index",
                [
                    'resposta_aluno' => $resposta_escolhida,
                    'acertou' => $acertou ? 1 : 0,
                    'tempo_gasto' => $tempo_gasto,
                    'simulado_id' => $simulado_id,
                    'questao_index' => $questao_index
                ]
            );

            $this->json(['success' => true, 'acertou' => $acertou]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Finaliza um simulado
     */
    public function finalizar()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome 
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $simulado_id = $_POST['simulado_id'] ?? '';
            $tempo_total = (int)($_POST['tempo_total'] ?? 0);

            if (empty($simulado_id)) {
                throw new Exception('ID do simulado é obrigatório');
            }

            // Verificar se o simulado pertence ao aluno
            $simulado = $this->db->fetch(
                "SELECT * FROM simulados WHERE id = :simulado_id AND aluno_id = :aluno_id",
                ['simulado_id' => $simulado_id, 'aluno_id' => $aluno['id']]
            );

            if (!$simulado) {
                throw new Exception('Simulado não encontrado');
            }

            if ($simulado['status'] !== 'em_andamento') {
                throw new Exception('Simulado não está em andamento');
            }

            // Calcular estatísticas
            $estatisticas = $this->calculateSimuladoStatistics($simulado_id);

            // Atualizar simulado
            $this->db->update(
                "UPDATE simulados SET 
                 status = 'finalizado',
                 finalizado_em = NOW(),
                 tempo_total = :tempo_total,
                 total_acertos = :total_acertos,
                 total_erros = :total_erros,
                 percentual_acerto = :percentual_acerto,
                 nota_final = :nota_final
                 WHERE id = :simulado_id",
                [
                    'tempo_total' => $tempo_total,
                    'total_acertos' => $estatisticas['total_acertos'],
                    'total_erros' => $estatisticas['total_erros'],
                    'percentual_acerto' => $estatisticas['percentual_acerto'],
                    'nota_final' => $estatisticas['nota_final'],
                    'simulado_id' => $simulado_id
                ]
            );

            // Salvar estatísticas por matéria
            $this->saveSubjectStatistics($simulado_id, $estatisticas['por_materia']);

            $this->json(['success' => true, 'estatisticas' => $estatisticas]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Exibe resultado de um simulado
     */
    public function resultado()
    {
        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome 
                 FROM alunos a 
                 LEFT JOIN turmas t ON a.turma_id = t.id 
             WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $simulado_id = $_GET['id'] ?? '';

            if (empty($simulado_id)) {
                throw new Exception('ID do simulado é obrigatório');
            }

            // Buscar simulado
            $simulado = $this->db->fetch(
                "SELECT * FROM simulados WHERE id = :simulado_id AND aluno_id = :aluno_id",
                ['simulado_id' => $simulado_id, 'aluno_id' => $aluno['id']]
            );

            if (!$simulado) {
                throw new Exception('Simulado não encontrado');
            }

            // Buscar questões e respostas
            $questoes = $this->db->fetchAll(
                "SELECT * FROM simulados_questoes WHERE simulado_id = :simulado_id ORDER BY questao_index",
                ['simulado_id' => $simulado_id]
            );
            $questoes = $this->enrichQuestoesEnunciadoFromEnem($questoes, $simulado['tipo_vestibular'] ?? 'ENEM');

            // Buscar estatísticas por matéria
            $estatisticas_materia = $this->db->fetchAll(
                "SELECT * FROM simulados_estatisticas WHERE simulado_id = :simulado_id",
                ['simulado_id' => $simulado_id]
            );

            $data = [
                'title' => 'Resultado do Simulado - ' . $simulado['ano'],
                'user' => $user,
                'simulado' => $simulado,
                'questoes' => $questoes,
                'estatisticas_materia' => $estatisticas_materia
            ];

            $this->viewWithLayout('student', 'student/simulations/simulado-resultado', $data);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Oculta um simulado (soft delete)
     */
    public function ocultarSimulado($id)
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
            return;
        }

        try {
            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            // Verificar se o simulado pertence ao aluno
            $simulado = $this->db->fetch(
                "SELECT * FROM simulados WHERE id = :id AND aluno_id = :aluno_id",
                ['id' => $id, 'aluno_id' => $aluno['id']]
            );

            if (!$simulado) {
                throw new Exception('Simulado não encontrado ou você não tem permissão');
            }

            // Marcar como oculto
            $this->db->update(
                "UPDATE simulados SET oculto = 1 WHERE id = :id",
                ['id' => $id]
            );

            $this->json(['success' => true, 'message' => 'Simulado oculto com sucesso']);
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    // Métodos auxiliares privados

    private function getStudentSimulados($aluno_id)
    {
        return $this->db->fetchAll(
            "SELECT * FROM simulados WHERE aluno_id = :aluno_id AND (oculto IS NULL OR oculto = 0) ORDER BY criado_em DESC",
            ['aluno_id' => $aluno_id]
        );
    }

    private function getSimuladoConfigurations()
    {
        return $this->db->fetchAll(
            "SELECT * FROM config_simulados WHERE ativo = 1 ORDER BY nome"
        );
    }

    private function getStudentStatistics($aluno_id)
    {
        $stats = $this->db->fetch(
            "SELECT 
                COUNT(*) as total_simulados,
                COUNT(CASE WHEN status = 'finalizado' THEN 1 END) as simulados_finalizados,
                AVG(CASE WHEN status = 'finalizado' THEN percentual_acerto END) as media_acertos,
                AVG(CASE WHEN status = 'finalizado' THEN nota_final END) as media_nota
             FROM simulados WHERE aluno_id = :aluno_id",
            ['aluno_id' => $aluno_id]
        );

        return [
            'total_simulados' => $stats['total_simulados'] ?? 0,
            'simulados_finalizados' => $stats['simulados_finalizados'] ?? 0,
            'media_acertos' => round($stats['media_acertos'] ?? 0, 2),
            'media_nota' => round($stats['media_nota'] ?? 0, 2)
        ];
    }

    private function getAvailableExams()
    {
        try {
            $exams = [];
            
            // Para cada tipo de vestibular, buscar anos e disciplinas disponíveis
            foreach ($this->tiposVestibular as $tipo) {
                if ($tipo === 'ENEM') {
                    // Nova estrutura normalizada para ENEM
                    $anos = $this->db->fetchAll(
                        "SELECT DISTINCT year FROM enem_provas ORDER BY year DESC"
                    );

                    // Disciplinas: tentar enem_disciplinas; se vazio, obter das questões (enem_questoes)
                    $disciplinas = [];
                    try {
                        $disciplinas = $this->db->fetchAll(
                            "SELECT DISTINCT label, value FROM enem_disciplinas WHERE label IS NOT NULL AND (value IS NOT NULL AND value != '') ORDER BY label"
                        );
                    } catch (\Exception $e) {
                        // Tabela pode não existir
                    }
                    if (empty($disciplinas)) {
                        try {
                            $rows = $this->db->fetchAll(
                                "SELECT DISTINCT q.discipline FROM enem_questoes q INNER JOIN enem_provas p ON p.id = q.exam_id WHERE q.discipline IS NOT NULL AND TRIM(q.discipline) != '' ORDER BY q.discipline"
                            );
                            foreach ($rows as $row) {
                                $d = trim($row['discipline']);
                                if ($d !== '') {
                                    $disciplinas[] = ['label' => $d, 'value' => $d];
                                }
                            }
                        } catch (\Exception $e) {
                            error_log("MockExamController: fallback disciplinas de enem_questoes: " . $e->getMessage());
                        }
                    }
                    // Fallback: disciplinas padrão do ENEM se nenhuma foi encontrada
                    if (empty($disciplinas)) {
                        $disciplinas = [
                            ['label' => 'Ciências da Natureza', 'value' => 'Ciências da Natureza'],
                            ['label' => 'Ciências Humanas', 'value' => 'Ciências Humanas'],
                            ['label' => 'Linguagens e Códigos', 'value' => 'Linguagens e Códigos'],
                            ['label' => 'Matemática', 'value' => 'Matemática'],
                            ['label' => 'Redação', 'value' => 'Redação'],
                        ];
                    }

                    // Fallback: anos padrão do ENEM se nenhum foi encontrado
                    $anosArray = !empty($anos) ? array_column($anos, 'year') : ['2023', '2022', '2021', '2020', '2019', '2018', '2017'];

                    // Sempre incluir ENEM
                    $exams[$tipo] = [
                        'tipo_vestibular' => $tipo,
                        'anos' => $anosArray,
                        'disciplinas' => $disciplinas,
                        'languages' => ['Português']
                    ];
                } else {
                    // Estrutura antiga para outros vestibulares
                    $tabelaQuestoes = $this->getTabelaQuestoes($tipo);
                    
                    // Verificar se a tabela existe
                    $checkTable = $this->db->query("SHOW TABLES LIKE '$tabelaQuestoes'");
                    if ($checkTable->rowCount() == 0) {
                        continue; // Pular se a tabela não existir
                    }
                    
                    // Buscar anos disponíveis para este tipo de vestibular
                    $anos = $this->db->fetchAll(
                        "SELECT DISTINCT ano FROM $tabelaQuestoes ORDER BY ano DESC"
                    );
                    
                    // Buscar disciplinas disponíveis para este tipo de vestibular
                    $disciplinas = $this->db->fetchAll(
                        "SELECT DISTINCT disciplina FROM $tabelaQuestoes WHERE disciplina IS NOT NULL ORDER BY disciplina"
                    );
                    
                    if (!empty($anos)) {
                        $exams[$tipo] = [
                            'tipo_vestibular' => $tipo,
                            'anos' => array_column($anos, 'ano'),
                            'disciplinas' => array_column($disciplinas, 'disciplina'),
                            'languages' => ['Português']
                        ];
                    }
                }
            }
            
            return $exams;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar provas do banco local: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca questões do banco de dados local baseado no tipo de vestibular
     */
    private function fetchQuestionsFromDatabase($tipo_vestibular, $ano, $disciplina, $limit)
    {
        try {
            if ($tipo_vestibular === 'ENEM') {
                return $this->fetchEnemQuestions($ano, $disciplina, $limit);
            } else {
                return $this->fetchLegacyQuestions($tipo_vestibular, $ano, $disciplina, $limit);
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar questões do banco local: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Recompõe o enunciado a partir de enem_questoes para snapshots antigos (só título + context).
     *
     * @param array<int,array<string,mixed>> $questoes
     * @return array<int,array<string,mixed>>
     */
    private function enrichQuestoesEnunciadoFromEnem(array $questoes, string $tipoVestibular): array
    {
        if ($tipoVestibular !== 'ENEM' || $questoes === []) {
            return $questoes;
        }
        foreach ($questoes as $i => $q) {
            $questaoId = isset($q['questao_id']) ? (string) $q['questao_id'] : '';
            if (!preg_match('/^(\d{4})_(\d+)$/', $questaoId, $m)) {
                continue;
            }
            $ano = (int) $m[1];
            $idx = (int) $m[2];
            $row = $this->db->fetch(
                "SELECT q.title, q.context, q.alternatives_introduction
                 FROM enem_questoes q
                 INNER JOIN enem_provas ep ON ep.id = q.exam_id AND ep.year = :ano
                 WHERE q.question_index = :idx
                 ORDER BY q.id ASC
                 LIMIT 1",
                ['ano' => $ano, 'idx' => $idx]
            );
            if ($row) {
                $questoes[$i]['enunciado'] = EnemQuestaoTextHelper::buildDisplayEnunciado(
                    $row['title'] ?? '',
                    $row['context'] ?? '',
                    $row['alternatives_introduction'] ?? ''
                );
            }
        }
        return $questoes;
    }

    private function fetchEnemQuestions($ano, $disciplina, $limit)
    {
        try {
            // Buscar exam_id pelo ano
            $exam = $this->db->fetch(
                "SELECT id FROM enem_provas WHERE year = :ano",
                ['ano' => $ano]
            );

            if (!$exam) {
                throw new Exception("Prova do ENEM {$ano} não encontrada");
            }

            // Subconsulta: uma linha por question_index (evita duplicatas no dump)
            $subWhere = ['q2.exam_id = :exam_id'];
            $params = ['exam_id' => $exam['id']];
            if (!empty($disciplina)) {
                $subWhere[] = 'q2.discipline = :disciplina';
                $params['disciplina'] = $disciplina;
            }
            $subWhereClause = implode(' AND ', $subWhere);

            // Buscar questões aleatórias (deduplicadas por índice da prova)
            $questoes = $this->db->fetchAll(
                "SELECT q.* FROM enem_questoes q
                 INNER JOIN (
                     SELECT MIN(q2.id) AS id
                     FROM enem_questoes q2
                     WHERE {$subWhereClause}
                     GROUP BY q2.question_index
                 ) pick ON pick.id = q.id
                 ORDER BY RAND()
                 LIMIT " . (int) $limit,
                $params
            );
            
            // Formatar questões no padrão esperado
            $questoesFormatadas = [];
            foreach ($questoes as $questao) {
                // Buscar alternativas
                $alternativas = $this->db->fetchAll(
                    "SELECT letter, text, file FROM enem_alternativas WHERE question_id = :question_id ORDER BY letter",
                    ['question_id' => $questao['id']]
                );
                
                // Organizar alternativas
                $alt = [];
                $alt_files = [];
                foreach ($alternativas as $alt_item) {
                    $alt[$alt_item['letter']] = $alt_item['text'];
                    $alt_files[$alt_item['letter']] = $alt_item['file'];
                }
                
                // Buscar arquivos de imagem para esta questão
                $arquivos = $this->db->fetchAll(
                    "SELECT file_url FROM enem_questoes_arquivos WHERE question_id = :question_id",
                    ['question_id' => $questao['id']]
                );
                
                $imagens = array_column($arquivos, 'file_url');
                
                $questoesFormatadas[] = [
                    'id' => $questao['year'] . '_' . $questao['question_index'],
                    'enunciado' => EnemQuestaoTextHelper::buildDisplayEnunciado(
                        $questao['title'] ?? '',
                        $questao['context'] ?? '',
                        $questao['alternatives_introduction'] ?? ''
                    ),
                    'alternativa_a' => $alt['A'] ?? '',
                    'alternativa_b' => $alt['B'] ?? '',
                    'alternativa_c' => $alt['C'] ?? '',
                    'alternativa_d' => $alt['D'] ?? '',
                    'alternativa_e' => $alt['E'] ?? '',
                    'alternativa_a_file' => $alt_files['A'] ?? null,
                    'alternativa_b_file' => $alt_files['B'] ?? null,
                    'alternativa_c_file' => $alt_files['C'] ?? null,
                    'alternativa_d_file' => $alt_files['D'] ?? null,
                    'alternativa_e_file' => $alt_files['E'] ?? null,
                    'resposta_certa' => $questao['correct_alternative'],
                    'materia' => $questao['discipline'],
                    'contexto' => $questao['context'],
                    'imagens' => $imagens, // URLs das imagens
                    'alternativas' => $alt // Adicionar campo alternativas para compatibilidade
                ];
            }
            
            return $questoesFormatadas;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar questões ENEM: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca questões usando estrutura antiga (para outros vestibulares)
     */
    private function fetchLegacyQuestions($tipo_vestibular, $ano, $disciplina, $limit)
    {
        try {
            $tabelaQuestoes = $this->getTabelaQuestoes($tipo_vestibular);
            
            // Verificar se a tabela existe
            $checkTable = $this->db->query("SHOW TABLES LIKE '$tabelaQuestoes'");
            if ($checkTable->rowCount() == 0) {
                throw new Exception("Tabela de questões para $tipo_vestibular não encontrada");
            }
            
            // Construir query base
            $whereConditions = ['ano = :ano'];
            $params = ['ano' => $ano];
            
            // Adicionar filtro por disciplina se especificada
            if (!empty($disciplina)) {
                $whereConditions[] = 'disciplina = :disciplina';
                $params['disciplina'] = $disciplina;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Buscar questões aleatórias do banco
            $questoes = $this->db->fetchAll(
                "SELECT * FROM $tabelaQuestoes 
                 WHERE {$whereClause} 
                 ORDER BY RAND() 
                 LIMIT :limit",
                array_merge($params, ['limit' => $limit])
            );
            
            // Converter estrutura JSON para campos separados
            $questoesFormatadas = [];
            foreach ($questoes as $questao) {
                $alternativas = json_decode($questao['alternativas'], true);
                
                $questoesFormatadas[] = [
                    'id' => $questao['ano'] . '_' . $questao['indice'],
                    'enunciado' => $questao['enunciado'],
                    'alternativa_a' => $alternativas['A'] ?? '',
                    'alternativa_b' => $alternativas['B'] ?? '',
                    'alternativa_c' => $alternativas['C'] ?? '',
                    'alternativa_d' => $alternativas['D'] ?? '',
                    'alternativa_e' => $alternativas['E'] ?? '',
                    'resposta_certa' => $questao['correta'],
                    'materia' => $questao['disciplina'],
                    'contexto' => $questao['contexto'],
                    'imagem' => $questao['imagem']
                ];
            }
            
            return $questoesFormatadas;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar questões legadas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Retorna o nome da tabela de questões baseado no tipo de vestibular
     */
    private function getTabelaQuestoes($tipo_vestibular)
    {
        $tabelas = [
            'ENEM' => 'enem_questoes', // Nova estrutura normalizada
            'FUVEST' => 'questoes_fuvest',
            'VUNESP' => 'questoes_vunesp',
            'UNICAMP' => 'questoes_unicamp',
            'UFMG' => 'questoes_ufmg',
            'OUTROS' => 'questoes_outros'
        ];
        
        return $tabelas[$tipo_vestibular] ?? 'enem_questoes';
    }
    

    private function calculateSimuladoStatistics($simulado_id)
    {
        $stats = $this->db->fetch(
            "SELECT 
                COUNT(*) as total_questoes,
                COUNT(CASE WHEN acertou = 1 THEN 1 END) as total_acertos,
                COUNT(CASE WHEN acertou = 0 THEN 1 END) as total_erros,
                AVG(CASE WHEN acertou = 1 THEN 1 ELSE 0 END) * 100 as percentual_acerto,
                AVG(tempo_gasto) as tempo_medio
             FROM simulados_questoes WHERE simulado_id = :simulado_id",
            ['simulado_id' => $simulado_id]
        );

        // Calcular nota (0-1000 baseada no percentual de acertos)
        $nota_final = ($stats['percentual_acerto'] ?? 0) * 10;

        // Estatísticas por matéria
        $por_materia = $this->db->fetchAll(
            "SELECT 
                materia,
                COUNT(*) as total_questoes,
                COUNT(CASE WHEN acertou = 1 THEN 1 END) as acertos,
                COUNT(CASE WHEN acertou = 0 THEN 1 END) as erros,
                AVG(CASE WHEN acertou = 1 THEN 1 ELSE 0 END) * 100 as percentual_acerto,
                AVG(tempo_gasto) as tempo_medio
             FROM simulados_questoes 
             WHERE simulado_id = :simulado_id 
             GROUP BY materia",
            ['simulado_id' => $simulado_id]
        );

        return [
            'total_questoes' => $stats['total_questoes'] ?? 0,
            'total_acertos' => $stats['total_acertos'] ?? 0,
            'total_erros' => $stats['total_erros'] ?? 0,
            'percentual_acerto' => round($stats['percentual_acerto'] ?? 0, 2),
            'nota_final' => round($nota_final, 2),
            'tempo_medio' => round($stats['tempo_medio'] ?? 0, 2),
            'por_materia' => $por_materia
        ];
    }

    private function saveSubjectStatistics($simulado_id, $estatisticas_materia)
    {
        foreach ($estatisticas_materia as $materia_stats) {
            $this->db->insert(
                "INSERT INTO simulados_estatisticas (simulado_id, materia, total_questoes, acertos, erros, percentual_acerto, tempo_medio) 
                 VALUES (:simulado_id, :materia, :total_questoes, :acertos, :erros, :percentual_acerto, :tempo_medio)",
                [
                    'simulado_id' => $simulado_id,
                    'materia' => $materia_stats['materia'],
                    'total_questoes' => $materia_stats['total_questoes'],
                    'acertos' => $materia_stats['acertos'],
                    'erros' => $materia_stats['erros'],
                    'percentual_acerto' => $materia_stats['percentual_acerto'],
                    'tempo_medio' => $materia_stats['tempo_medio']
                ]
            );
        }
    }

    private function redirectToCorrectDashboard($tipo)
    {
        switch ($tipo) {
            case 'admin':
                $this->redirect('/admin/dashboard');
                break;
            case 'professor':
                $this->redirect('/professor/dashboard');
                break;
            case 'pai':
                $this->redirect('/pais/dashboard');
                break;
            default:
                $this->redirect('/');
        }
    }
}
}
