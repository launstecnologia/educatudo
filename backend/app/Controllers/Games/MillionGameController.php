<?php
/**
 * EducaTudo - Controller do Tudinha do Milhão
 * Gerencia o jogo de perguntas e respostas
 */

if (!class_exists('MillionGameController')) {
class MillionGameController extends BaseController
{
    private const GAMES_BLOQUEADO_ALUNO = true;
    private $authManager;
    private $db;
    private $webhookManager;
    private $securityMiddleware;
    
    // Valores dos prêmios por pergunta
    private $premios = [
        1 => 100,      // R$ 100
        2 => 200,      // R$ 200
        3 => 300,      // R$ 300
        4 => 500,      // R$ 500
        5 => 1000,     // R$ 1.000
        6 => 2000,     // R$ 2.000
        7 => 4000,     // R$ 4.000
        8 => 8000,     // R$ 8.000
        9 => 16000,    // R$ 16.000
        10 => 32000,   // R$ 32.000
        11 => 64000,   // R$ 64.000
        12 => 125000,  // R$ 125.000
        13 => 250000,  // R$ 250.000
        14 => 500000,  // R$ 500.000
        15 => 1000000  // R$ 1.000.000
    ];
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $this->webhookManager = new WebhookManager();
        $this->securityMiddleware = new GameSecurityMiddleware();
        
        // Verifica se está logado
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
        
        // Verifica se é aluno
        $user = $this->authManager->getUser();
        if ($user['tipo'] !== 'aluno') {
            // Redirecionar para dashboard correto baseado no tipo de usuário
            switch ($user['tipo']) {
                case 'admin':
                case 'diretor':
                case 'coordenador':
                case 'dev':
                    $this->redirect('/admin/dashboard');
                    break;
                case 'professor':
                    $this->redirect('/professor/dashboard');
                    break;
                case 'pais':
                    $this->redirect('/pais/dashboard');
                    break;
                default:
                    $this->redirect('/');
            }
        }

        if (self::GAMES_BLOQUEADO_ALUNO) {
            $this->setFlashMessage('Games está temporariamente desabilitado para alunos.', 'error');
            $this->redirect('/dashboard');
            return;
        }
    }
    
    /**
     * Página principal do jogo
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        
        // Buscar dados do aluno com informações da turma
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
        
        // Buscar estatísticas do aluno
        $stats = $this->db->fetch(
            "SELECT * FROM pontuacao_alunos WHERE aluno_id = :aluno_id",
            ['aluno_id' => $aluno['id']]
        );
        
        // Se não tem estatísticas, criar
        if (!$stats) {
            $this->db->insert(
                "INSERT INTO pontuacao_alunos (aluno_id) VALUES (:aluno_id)",
                ['aluno_id' => $aluno['id']]
            );
            $stats = [
                'total_partidas' => 0,
                'partidas_vencidas' => 0,
                'maior_premio' => 0,
                'total_premio' => 0,
                'nivel_atual' => 'Iniciante'
            ];
        }
        
        // Verificar se tem partida em andamento
        $partidaAtiva = $this->db->fetch(
            "SELECT * FROM jogos_milhao_partidas WHERE aluno_id = :aluno_id AND status = 'em_andamento' ORDER BY data_inicio DESC LIMIT 1",
            ['aluno_id' => $aluno['id']]
        );
        
        $data = [
            'title' => 'Tudinha do Milhão - EducaTudo',
            'user' => $user,
            'aluno' => $aluno,
            'stats' => $stats,
            'partidaAtiva' => $partidaAtiva,
            'premios' => $this->premios,
            'current_page' => 'jogos',
            'csrf_token' => $this->generateCsrfToken()
        ];
        
        $this->viewWithLayout('student', 'student/jogo-milhao/index', $data);
    }
    
    /**
     * Continuar partida existente
     */
    public function continuarPartida()
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
            
            $partidaId = $_POST['partida_id'] ?? '';
            
            if (empty($partidaId)) {
                throw new Exception('ID da partida não fornecido');
            }
            
            // Buscar partida existente
            $partida = $this->db->fetch(
                "SELECT * FROM jogos_milhao_partidas WHERE id = :partida_id AND aluno_id = :aluno_id AND status = 'em_andamento'",
                ['partida_id' => $partidaId, 'aluno_id' => $aluno['id']]
            );
            
            if (!$partida) {
                throw new Exception('Partida não encontrada ou já finalizada');
            }
            
            // VERIFICAR SE A ÚLTIMA PERGUNTA FOI RESPONDIDA
            $perguntaNumero = (int)$partida['pergunta_atual'];
            
            // Buscar última resposta desta partida
            $ultimaResposta = $this->db->fetch(
                "SELECT * FROM jogos_milhao_respostas 
                 WHERE partida_id = :partida_id 
                 ORDER BY id DESC 
                 LIMIT 1",
                ['partida_id' => $partidaId]
            );
            
            // Se existe uma resposta, verificar se já foi processada
            if ($ultimaResposta && $ultimaResposta['acertou'] == 1) {
                // Se acertou a pergunta anterior, deve avançar para a próxima
                $perguntaNumero = $partida['pergunta_atual'] + 1;
                
                // Atualizar partida com nova pergunta
                $novoPremio = $this->premios[$perguntaNumero] ?? $this->premios[15];
                $this->db->update(
                    "UPDATE jogos_milhao_partidas SET pergunta_atual = :pergunta_atual, pontuacao_atual = :pontuacao_atual WHERE id = :partida_id",
                    [
                        'pergunta_atual' => $perguntaNumero,
                        'pontuacao_atual' => $novoPremio,
                        'partida_id' => $partidaId
                    ]
                );
                $partida['pergunta_atual'] = $perguntaNumero;
                $partida['pontuacao_atual'] = $novoPremio;
            }
            
            // Verificar se aluno já respondeu 100 perguntas (zerar jogo)
            $totalRespondidas = $this->getTotalPerguntasRespondidas($aluno['id']);
            if ($totalRespondidas >= 100) {
                // Zerar contagem - permite começar de novo
                $this->zerarJogoAluno($aluno['id']);
            }
            
            // Buscar pergunta baseada no nível atual
            $nivelAtual = $this->getNivelDificuldade($partida['pergunta_atual']);
            $pergunta = $this->getPerguntaAleatoria($nivelAtual, $partidaId, $aluno['id']);
            
            if (!$pergunta) {
                // Se não há perguntas disponíveis, buscar de qualquer nível
                $pergunta = $this->getPerguntaAleatoria('facil', $partidaId, $aluno['id']);
                if (!$pergunta) {
                    throw new Exception('Você já respondeu todas as perguntas disponíveis! Parabéns por completar o jogo! 🎉');
                }
            }
            
            $this->json([
                'success' => true,
                'partida_id' => $partidaId,
                'pergunta' => $pergunta,
                'premio_atual' => $partida['pontuacao_atual'],
                'pergunta_numero' => $partida['pergunta_atual'],
                'ajudas_usadas' => json_decode($partida['ajudas_usadas'] ?? '[]', true)
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Página do jogo (nova página)
     */
    public function jogar()
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
            
            // Verificar se deve iniciar nova partida ou continuar existente
            $partidaAtiva = $this->db->fetch(
                "SELECT * FROM jogos_milhao_partidas WHERE aluno_id = :aluno_id AND status = 'em_andamento' ORDER BY data_inicio DESC LIMIT 1",
                ['aluno_id' => $aluno['id']]
            );
            
            // Se não tem partida ativa, criar nova
            if (!$partidaAtiva) {
                // Verificar se aluno já respondeu 100 perguntas (zerar jogo)
                $totalRespondidas = $this->getTotalPerguntasRespondidas($aluno['id']);
                if ($totalRespondidas >= 100) {
                    $this->zerarJogoAluno($aluno['id']);
                }
                
                // Buscar primeira pergunta
                $nivelDificuldade = $this->getNivelDificuldade(1);
                $pergunta = $this->getPerguntaAleatoria($nivelDificuldade, null, $aluno['id']);
                
                // Criar nova partida
                $partidaId = $this->db->insert(
                    "INSERT INTO jogos_milhao_partidas 
                     (aluno_id, status, pergunta_atual, pontuacao_atual, data_inicio, last_activity) 
                     VALUES (:aluno_id, 'em_andamento', 1, 100, NOW(), NOW())",
                    ['aluno_id' => $aluno['id']]
                );
                
                $partidaAtiva = [
                    'id' => $partidaId,
                    'pergunta_atual' => 1,
                    'pontuacao_atual' => 100,
                    'pergunta' => $pergunta
                ];
            }
            
            $data = [
                'title' => 'Tudinha do Milhão - Jogando',
                'user' => $user,
                'aluno' => $aluno,
                'partidaAtiva' => $partidaAtiva,
                'premios' => $this->premios,
                'current_page' => 'jogos',
                'csrf_token' => $this->generateCsrfToken()
            ];
            
            $this->view('student/jogo-milhao/jogar', $data);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Iniciar nova partida
     */
    public function iniciarPartida()
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
            
            // Primeiro, limpar partidas órfãs (mais de 5 minutos sem atividade)
            $this->limparPartidasOrfas($aluno['id']);
            
            // Verificar se já tem partida em andamento
            $partidaAtiva = $this->db->fetch(
                "SELECT * FROM jogos_milhao_partidas WHERE aluno_id = :aluno_id AND status = 'em_andamento'",
                ['aluno_id' => $aluno['id']]
            );
            
            if ($partidaAtiva) {
                throw new Exception('Você já tem uma partida em andamento');
            }
            
            // Criar nova partida
            $partidaId = $this->db->insert(
                "INSERT INTO jogos_milhao_partidas (aluno_id, pontuacao_atual, pergunta_atual) VALUES (:aluno_id, 0, 1)",
                ['aluno_id' => $aluno['id']]
            );
            
            // Verificar se aluno já respondeu 100 perguntas (zerar jogo)
            $totalRespondidas = $this->getTotalPerguntasRespondidas($aluno['id']);
            if ($totalRespondidas >= 100) {
                $this->zerarJogoAluno($aluno['id']);
            }
            
            // Buscar primeira pergunta
            $pergunta = $this->getPerguntaAleatoria('facil', $partidaId, $aluno['id']);
            
            if (!$pergunta) {
                throw new Exception('Não há perguntas disponíveis no sistema. Entre em contato com o administrador.');
            }
            
            $this->json([
                'success' => true,
                'partida_id' => $partidaId,
                'pergunta' => $pergunta,
                'premio_atual' => $this->premios[1],
                'pergunta_numero' => 1
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Responder pergunta
     */
    public function responderPergunta()
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
            
            $partidaId = $_POST['partida_id'] ?? '';
            $perguntaId = $_POST['pergunta_id'] ?? '';
            $resposta = $_POST['resposta'] ?? '';
            $ajudaUsada = $_POST['ajuda_usada'] ?? 'nenhuma';
            $tempoGasto = isset($_POST['tempo_resposta']) ? intval($_POST['tempo_resposta']) : 0;
            
            // Debug logs
            error_log("responderPergunta - partidaId: $partidaId, perguntaId: $perguntaId, resposta: $resposta, ajudaUsada: $ajudaUsada");
            
            if (empty($partidaId) || empty($perguntaId) || empty($resposta)) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }
            
            // SEGURANÇA: Validar ação de jogo
            $securityValidation = $this->securityMiddleware->validateGameAction(
                $aluno['id'], 
                $partidaId, 
                'responder'
            );
            
            if (!$securityValidation['valid']) {
                error_log("SECURITY: " . $securityValidation['error']);
                $this->json(['error' => $securityValidation['error']], 403);
                return;
            }
            
            // Verificar se a partida existe e pertence ao aluno
            $partida = $this->db->fetch(
                "SELECT * FROM jogos_milhao_partidas WHERE id = :partida_id AND aluno_id = :aluno_id AND status = 'em_andamento'",
                ['partida_id' => $partidaId, 'aluno_id' => $aluno['id']]
            );
            
            if (!$partida) {
                throw new Exception('Partida não encontrada ou já finalizada');
            }
            
            // VERIFICAÇÃO ANTI-BUG: Verificar se já existe uma resposta para esta pergunta nesta partida
            $respostaExistente = $this->db->fetch(
                "SELECT * FROM jogos_milhao_respostas WHERE partida_id = :partida_id AND pergunta_id = :pergunta_id LIMIT 1",
                ['partida_id' => $partidaId, 'pergunta_id' => $perguntaId]
            );
            
            if ($respostaExistente) {
                error_log("BUG PREVENIDO: Tentativa de enviar resposta duplicada para pergunta $perguntaId na partida $partidaId");
                // Retornar os dados da resposta existente
                $this->json([
                    'success' => true,
                    'acertou' => $respostaExistente['acertou'],
                    'resposta_existente' => true,
                    'jogo_finalizado' => true,
                    'premio_final' => $partida['pontuacao_atual'],
                    'mensagem' => 'Você já respondeu esta pergunta.'
                ]);
                return;
            }
            
            // Buscar pergunta
            $pergunta = $this->db->fetch(
                "SELECT * FROM jogos_milhao_perguntas WHERE id = :pergunta_id",
                ['pergunta_id' => $perguntaId]
            );
            
            if (!$pergunta) {
                throw new Exception('Pergunta não encontrada');
            }
            
            $acertou = ($resposta === $pergunta['resposta_correta']);
            
            // Debug logs detalhados
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                error_log("responderPergunta - Comparação: '$resposta' === '{$pergunta['resposta_correta']}' = " . ($acertou ? 'TRUE' : 'FALSE'));
            }
            error_log("responderPergunta - Resposta enviada (ord): " . ord($resposta));
            error_log("responderPergunta - Resposta correta (ord): " . ord($pergunta['resposta_correta']));
            
            // Registrar pergunta como usada
            $this->registrarPerguntaUsada($partidaId, $perguntaId);
            
            // Salvar resposta com tempo
            $this->db->insert(
                "INSERT INTO jogos_milhao_respostas (partida_id, pergunta_id, resposta_escolhida, resposta_correta, acertou, ajuda_usada, tempo_resposta) VALUES (:partida_id, :pergunta_id, :resposta_escolhida, :resposta_correta, :acertou, :ajuda_usada, :tempo_resposta)",
                [
                    'partida_id' => $partidaId,
                    'pergunta_id' => $perguntaId,
                    'resposta_escolhida' => $resposta,
                    'resposta_correta' => $pergunta['resposta_correta'],
                    'acertou' => $acertou ? 1 : 0,
                    'ajuda_usada' => $ajudaUsada,
                    'tempo_resposta' => $tempoGasto
                ]
            );
            
            if ($acertou) {
                // Atualizar partida
                $novaPergunta = $partida['pergunta_atual'] + 1;
                $novoPremio = $this->premios[$novaPergunta] ?? $this->premios[15];
                
                $this->db->update(
                    "UPDATE jogos_milhao_partidas SET pergunta_atual = :pergunta_atual, pontuacao_atual = :pontuacao_atual WHERE id = :partida_id",
                    [
                        'pergunta_atual' => $novaPergunta,
                        'pontuacao_atual' => $novoPremio,
                        'partida_id' => $partidaId
                    ]
                );
                
                // Verificar se ganhou o jogo
                if ($novaPergunta > 15) {
                    $this->finalizarPartida($partidaId, $aluno['id'], true);
                    
                    $this->json([
                        'success' => true,
                        'acertou' => true,
                        'jogo_finalizado' => true,
                        'premio_final' => $this->premios[15],
                        'mensagem' => 'Parabéns! Você ganhou R$ 1.000.000!'
                    ]);
                } else {
                    // Verificar se aluno já respondeu 100 perguntas
                    $totalRespondidas = $this->getTotalPerguntasRespondidas($aluno['id']);
                    if ($totalRespondidas >= 100) {
                        $this->zerarJogoAluno($aluno['id']);
                    }
                    
                    // Buscar próxima pergunta
                    $nivelProximo = $this->getNivelDificuldade($novaPergunta);
                    $proximaPergunta = $this->getPerguntaAleatoria($nivelProximo, $partidaId, $aluno['id']);
                    
                    $this->json([
                        'success' => true,
                        'acertou' => true,
                        'jogo_finalizado' => false,
                        'proxima_pergunta' => $proximaPergunta,
                        'premio_atual' => $novoPremio,
                        'pergunta_numero' => $novaPergunta,
                        'explicacao' => $pergunta['explicacao']
                    ]);
                }
            } else {
                // Errou - finalizar partida
                $this->finalizarPartida($partidaId, $aluno['id'], false);
                
                $this->json([
                    'success' => true,
                    'acertou' => false,
                    'jogo_finalizado' => true,
                    'premio_final' => $partida['pontuacao_atual'],
                    'explicacao' => $pergunta['explicacao'],
                    'mensagem' => 'Ahhh, você errou! 😢 Não foi dessa vez, mas não desista! Tente novamente e você vai conseguir! 💪'
                ]);
            }
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Usar ajuda especial
     */
    public function usarAjuda()
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
            
            $partidaId = $_POST['partida_id'] ?? '';
            
            // SEGURANÇA: Validar ação de jogo
            $securityValidation = $this->securityMiddleware->validateGameAction(
                $aluno['id'], 
                $partidaId, 
                'usar_ajuda'
            );
            
            if (!$securityValidation['valid']) {
                error_log("SECURITY: " . $securityValidation['error']);
                $this->json(['error' => $securityValidation['error']], 403);
                return;
            }
            
            $tipoAjuda = $_POST['tipo_ajuda'] ?? '';
            $perguntaId = $_POST['pergunta_id'] ?? '';
            
            if (empty($partidaId) || empty($tipoAjuda) || empty($perguntaId)) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }
            
            // Verificar se a partida existe e pertence ao aluno
            $partida = $this->db->fetch(
                "SELECT * FROM jogos_milhao_partidas WHERE id = :partida_id AND aluno_id = :aluno_id AND status = 'em_andamento'",
                ['partida_id' => $partidaId, 'aluno_id' => $aluno['id']]
            );
            
            if (!$partida) {
                throw new Exception('Partida não encontrada ou já finalizada');
            }
            
            // Verificar ajudas já usadas
            $ajudasUsadas = json_decode($partida['ajudas_usadas'], true);
            
            if ($ajudasUsadas[$tipoAjuda]) {
                throw new Exception('Esta ajuda já foi usada nesta partida');
            }
            
            // Buscar pergunta
            $pergunta = $this->db->fetch(
                "SELECT * FROM jogos_milhao_perguntas WHERE id = :pergunta_id",
                ['pergunta_id' => $perguntaId]
            );
            
            if (!$pergunta) {
                throw new Exception('Pergunta não encontrada');
            }
            
            $resultado = [];
            
            switch ($tipoAjuda) {
                case 'plateia':
                    $resultado = $this->gerarAjudaPlateia($pergunta);
                    break;
                    
                case 'universitarios':
                    $resultado = $this->gerarAjudaUniversitarios($pergunta);
                    break;
                    
                case 'pular':
                    $resultado = $this->pularPergunta($partida, $pergunta);
                    break;
                    
                default:
                    throw new Exception('Tipo de ajuda inválido');
            }
            
            // Marcar ajuda como usada
            $ajudasUsadas[$tipoAjuda] = true;
            $this->db->update(
                "UPDATE jogos_milhao_partidas SET ajudas_usadas = :ajudas_usadas WHERE id = :partida_id",
                [
                    'ajudas_usadas' => json_encode($ajudasUsadas),
                    'partida_id' => $partidaId
                ]
            );
            
            $this->json([
                'success' => true,
                'tipo_ajuda' => $tipoAjuda,
                'resultado' => $resultado
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Registrar pergunta como usada na partida
     */
    private function registrarPerguntaUsada($partidaId, $perguntaId)
    {
        $partida = $this->db->fetch(
            "SELECT perguntas_usadas FROM jogos_milhao_partidas WHERE id = :partida_id",
            ['partida_id' => $partidaId]
        );
        
        $perguntasUsadas = $partida['perguntas_usadas'] ?? '';
        $perguntasArray = empty($perguntasUsadas) ? [] : explode(',', $perguntasUsadas);
        
        // Adicionar nova pergunta se não estiver na lista
        if (!in_array($perguntaId, $perguntasArray)) {
            $perguntasArray[] = $perguntaId;
            $perguntasUsadas = implode(',', $perguntasArray);
            
            $this->db->update(
                "UPDATE jogos_milhao_partidas SET perguntas_usadas = :perguntas_usadas WHERE id = :partida_id",
                [
                    'perguntas_usadas' => $perguntasUsadas,
                    'partida_id' => $partidaId
                ]
            );
        }
    }
    
    /**
     * Buscar pergunta aleatória por nível
     * Não repete perguntas já respondidas pelo aluno em nenhuma partida (até 100 perguntas)
     */
    private function getPerguntaAleatoria($nivel, $partidaId = null, $alunoId = null)
    {
        $sql = "SELECT * FROM jogos_milhao_perguntas WHERE nivel_dificuldade = :nivel AND ativa = 1";
        $params = ['nivel' => $nivel];
        
        // Excluir perguntas já respondidas pelo aluno em TODAS as partidas
        if ($alunoId) {
            // Buscar todas as perguntas já respondidas por este aluno
            $perguntasRespondidas = $this->db->fetchAll(
                "SELECT DISTINCT r.pergunta_id 
                 FROM jogos_milhao_respostas r
                 INNER JOIN jogos_milhao_partidas p ON r.partida_id = p.id
                 WHERE p.aluno_id = :aluno_id",
                ['aluno_id' => $alunoId]
            );
            
            if (!empty($perguntasRespondidas)) {
                $idsRespondidos = array_column($perguntasRespondidas, 'pergunta_id');
                $idsRespondidos = array_filter($idsRespondidos);
                
                if (!empty($idsRespondidos)) {
                    $placeholders = [];
                    foreach ($idsRespondidos as $index => $id) {
                        $placeholders[] = ":id_aluno_{$index}";
                        $params["id_aluno_{$index}"] = $id;
                    }
                    $sql .= " AND id NOT IN (" . implode(',', $placeholders) . ")";
                }
            }
        }
        
        // Se temos uma partida, também excluir perguntas já usadas nesta partida específica
        if ($partidaId) {
            $partida = $this->db->fetch(
                "SELECT perguntas_usadas FROM jogos_milhao_partidas WHERE id = :partida_id",
                ['partida_id' => $partidaId]
            );
            
            if ($partida && !empty($partida['perguntas_usadas'])) {
                $perguntasUsadas = explode(',', $partida['perguntas_usadas']);
                $perguntasUsadas = array_filter($perguntasUsadas);
                
                if (!empty($perguntasUsadas)) {
                    $placeholdersPartida = [];
                    foreach ($perguntasUsadas as $index => $id) {
                        $key = "id_partida_{$index}";
                        $placeholdersPartida[] = ":{$key}";
                        $params[$key] = $id;
                    }
                    if (!empty($placeholdersPartida)) {
                        $sql .= " AND id NOT IN (" . implode(',', $placeholdersPartida) . ")";
                    }
                }
            }
        }
        
        $sql .= " ORDER BY RAND() LIMIT 1";
        
        return $this->db->fetch($sql, $params);
    }
    
    /**
     * Determinar nível de dificuldade pela pergunta
     */
    private function getNivelDificuldade($perguntaNumero)
    {
        if ($perguntaNumero <= 5) return 'facil';
        if ($perguntaNumero <= 10) return 'medio';
        return 'dificil';
    }
    
    /**
     * Finalizar partida
     */
    private function finalizarPartida($partidaId, $alunoId, $venceu, $status = 'finalizada')
    {
        $partida = $this->db->fetch(
            "SELECT * FROM jogos_milhao_partidas WHERE id = :partida_id",
            ['partida_id' => $partidaId]
        );
        
        $premioFinal = $venceu ? $this->premios[15] : $partida['pontuacao_atual'];
        
        // Atualizar partida
        $this->db->update(
            "UPDATE jogos_milhao_partidas SET status = :status, data_fim = NOW(), premio_final = :premio_final WHERE id = :partida_id",
            [
                'status' => $status,
                'premio_final' => $premioFinal,
                'partida_id' => $partidaId
            ]
        );
        
        // Atualizar estatísticas do aluno (só se não for abandonada)
        if ($status !== 'abandonada') {
            $this->db->update(
                "UPDATE pontuacao_alunos SET 
                    total_partidas = total_partidas + 1,
                    partidas_vencidas = partidas_vencidas + :venceu,
                    maior_premio = CASE WHEN :premio_final1 > maior_premio THEN :premio_final2 ELSE maior_premio END,
                    total_premio = total_premio + :premio_final3
                 WHERE aluno_id = :aluno_id",
                [
                    'venceu' => $venceu ? 1 : 0,
                    'premio_final1' => $premioFinal,
                    'premio_final2' => $premioFinal,
                    'premio_final3' => $premioFinal,
                    'aluno_id' => $alunoId
                ]
            );
        }
    }
    
    /**
     * Gerar ajuda da plateia
     */
    private function gerarAjudaPlateia($pergunta)
    {
        $alternativas = ['A', 'B', 'C', 'D'];
        $respostaCorreta = $pergunta['resposta_correta'];
        
        // Gerar percentuais simulados (inclinados para a resposta correta)
        $percentuais = [];
        $total = 100;
        
        foreach ($alternativas as $alt) {
            if ($alt === $respostaCorreta) {
                // Resposta correta tem maior percentual (40-60%)
                $percentuais[$alt] = rand(40, 60);
            } else {
                // Outras alternativas dividem o restante
                $restante = $total - array_sum($percentuais);
                $max = min(30, $restante - (3 - count($percentuais)));
                $percentuais[$alt] = rand(5, $max);
            }
        }
        
        // Ajustar para totalizar 100%
        $soma = array_sum($percentuais);
        if ($soma !== 100) {
            $diferenca = 100 - $soma;
            $percentuais[$respostaCorreta] += $diferenca;
        }
        
        return [
            'tipo' => 'plateia',
            'percentuais' => $percentuais,
            'mensagem' => 'A plateia votou! Aqui está o resultado:'
        ];
    }
    
    /**
     * Gerar ajuda dos universitários
     */
    private function gerarAjudaUniversitarios($pergunta)
    {
        $alternativas = ['A', 'B', 'C', 'D'];
        $respostaCorreta = $pergunta['resposta_correta'];
        
        $universitarios = [
            ['nome' => 'Ana', 'curso' => 'Medicina'],
            ['nome' => 'Carlos', 'curso' => 'Engenharia'],
            ['nome' => 'Maria', 'curso' => 'Direito']
        ];
        
        $opinioes = [];
        
        foreach ($universitarios as $uni) {
            // 70% de chance de acertar
            if (rand(1, 100) <= 70) {
                $opinioes[] = [
                    'nome' => $uni['nome'],
                    'curso' => $uni['curso'],
                    'resposta' => $respostaCorreta,
                    'confianca' => rand(60, 90)
                ];
            } else {
                $alternativasErradas = array_diff($alternativas, [$respostaCorreta]);
                $opinioes[] = [
                    'nome' => $uni['nome'],
                    'curso' => $uni['curso'],
                    'resposta' => $alternativasErradas[array_rand($alternativasErradas)],
                    'confianca' => rand(30, 70)
                ];
            }
        }
        
        return [
            'tipo' => 'universitarios',
            'opinioes' => $opinioes,
            'mensagem' => 'Os universitários opinaram:'
        ];
    }
    
    /**
     * Pular pergunta
     */
    private function pularPergunta($partida, $perguntaAtual)
    {
        // Buscar aluno_id da partida
        $partidaCompleta = $this->db->fetch(
            "SELECT aluno_id FROM jogos_milhao_partidas WHERE id = :partida_id",
            ['partida_id' => $partida['id']]
        );
        
        $nivelProximo = $this->getNivelDificuldade($partida['pergunta_atual']);
        $novaPergunta = $this->getPerguntaAleatoria($nivelProximo, $partida['id'], $partidaCompleta['aluno_id'] ?? null);
        
        if (!$novaPergunta) {
            throw new Exception('Não há mais perguntas disponíveis para este nível');
        }
        
        // Registrar a pergunta pulada como usada
        $this->registrarPerguntaUsada($partida['id'], $perguntaAtual['id']);
        
        // Atualizar a partida com a nova pergunta
        $this->db->update(
            "UPDATE jogos_milhao_partidas SET pergunta_atual = :pergunta_atual WHERE id = :partida_id",
            [
                'pergunta_atual' => $partida['pergunta_atual'], // Manter o mesmo número da pergunta
                'partida_id' => $partida['id']
            ]
        );
        
        return [
            'tipo' => 'pular',
            'nova_pergunta' => $novaPergunta,
            'premio_atual' => $partida['pontuacao_atual'],
            'pergunta_numero' => $partida['pergunta_atual'],
            'mensagem' => 'Pergunta pulada! Aqui está uma nova pergunta:'
        ];
    }
    
    /**
     * Abandonar partida (quando usuário sai do jogo)
     */
    public function abandonar()
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
            
            $partidaId = $_POST['partida_id'] ?? '';
            
            if (empty($partidaId)) {
                throw new Exception('ID da partida não fornecido');
            }
            
            // Buscar partida existente
            $partida = $this->db->fetch(
                "SELECT * FROM jogos_milhao_partidas WHERE id = :partida_id AND aluno_id = :aluno_id AND status = 'em_andamento'",
                ['partida_id' => $partidaId, 'aluno_id' => $aluno['id']]
            );
            
            if (!$partida) {
                throw new Exception('Partida não encontrada ou já finalizada');
            }
            
            // Finalizar partida como abandonada
            $this->finalizarPartida($partidaId, $aluno['id'], false, 'abandonada');
            
            $this->json([
                'success' => true,
                'message' => 'Partida abandonada com sucesso'
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Heartbeat para manter partida ativa
     */
    public function heartbeat()
    {
        try {
            $user = $this->authManager->getUser();
            
            // Verificar se há partida ativa
            $partidaAtiva = $this->db->fetch(
                "SELECT * FROM jogos_milhao_partidas WHERE aluno_id = :aluno_id AND status = 'em_andamento'",
                ['aluno_id' => $user['id']]
            );
            
            if ($partidaAtiva) {
                // Atualizar timestamp da última atividade
                $this->db->query(
                    "UPDATE jogos_milhao_partidas SET data_inicio = NOW() WHERE id = :partida_id",
                    ['partida_id' => $partidaAtiva['id']]
                );
                
                $this->json(['success' => true, 'partida_ativa' => true]);
            } else {
                $this->json(['success' => true, 'partida_ativa' => false]);
            }
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Verificar se há partida ativa
     */
    public function verificarPartida()
    {
        try {
            $user = $this->authManager->getUser();
            
            // Primeiro, limpar partidas órfãs (mais de 5 minutos sem atividade)
            $this->limparPartidasOrfas($user['id']);
            
            $partidaAtiva = $this->db->fetch(
                "SELECT * FROM jogos_milhao_partidas WHERE aluno_id = :aluno_id AND status = 'em_andamento'",
                ['aluno_id' => $user['id']]
            );
            
            $this->json([
                'success' => true,
                'partida_ativa' => $partidaAtiva ? true : false,
                'partida_id' => $partidaAtiva ? $partidaAtiva['id'] : null
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Contar total de perguntas respondidas pelo aluno
     */
    private function getTotalPerguntasRespondidas($alunoId)
    {
        $resultado = $this->db->fetch(
            "SELECT COUNT(DISTINCT r.pergunta_id) as total
             FROM jogos_milhao_respostas r
             INNER JOIN jogos_milhao_partidas p ON r.partida_id = p.id
             WHERE p.aluno_id = :aluno_id",
            ['aluno_id' => $alunoId]
        );
        
        return (int)($resultado['total'] ?? 0);
    }
    
    /**
     * Zerar jogo do aluno (deleta todas as respostas para permitir recomeçar)
     */
    private function zerarJogoAluno($alunoId)
    {
        // Buscar todas as partidas do aluno
        $partidas = $this->db->fetchAll(
            "SELECT id FROM jogos_milhao_partidas WHERE aluno_id = :aluno_id",
            ['aluno_id' => $alunoId]
        );
        
        if (!empty($partidas)) {
            $partidaIds = array_column($partidas, 'id');
            
            // Deletar todas as respostas
            $placeholders = [];
            $params = [];
            foreach ($partidaIds as $index => $id) {
                $placeholders[] = ":partida_{$index}";
                $params["partida_{$index}"] = $id;
            }
            
            if (!empty($placeholders)) {
                $this->db->query(
                    "DELETE FROM jogos_milhao_respostas WHERE partida_id IN (" . implode(',', $placeholders) . ")",
                    $params
                );
            }
            
            // Finalizar todas as partidas antigas como 'finalizada'
            $this->db->update(
                "UPDATE jogos_milhao_partidas SET status = 'finalizada', data_fim = NOW() WHERE aluno_id = :aluno_id AND status = 'em_andamento'",
                ['aluno_id' => $alunoId]
            );
        }
        
        error_log("Jogo zerado para aluno $alunoId - permitindo recomeçar");
    }
    
    /**
     * Limpar partidas órfãs (mais de 2 minutos sem atividade)
     */
    private function limparPartidasOrfas($alunoId)
    {
        try {
            // Limpar partidas órfãs mais agressivamente (2 minutos)
            $this->db->query(
                "UPDATE jogos_milhao_partidas 
                 SET status = 'abandonada' 
                 WHERE aluno_id = :aluno_id 
                 AND status = 'em_andamento' 
                 AND data_inicio < DATE_SUB(NOW(), INTERVAL 2 MINUTE)",
                ['aluno_id' => $alunoId]
            );
            
            // Log para debug
            error_log("Partidas órfãs limpas para aluno $alunoId");
        } catch (Exception $e) {
            error_log("Erro ao limpar partidas órfãs: " . $e->getMessage());
        }
    }
    
    /**
     * Endpoint público para limpar partidas órfãs
     */
    public function limparOrfas()
    {
        try {
            $user = $this->authManager->getUser();
            
            // Limpar partidas órfãs do usuário atual
            $this->limparPartidasOrfas($user['id']);
            
            $this->json([
                'success' => true,
                'message' => 'Partidas órfãs limpas com sucesso'
            ]);
            
        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
}
