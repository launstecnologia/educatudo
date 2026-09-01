<?php
/**
 * Controller para Exercícios Personalizados
 * Permite que alunos criem seus próprios exercícios usando IA
 */

require_once __DIR__ . '/../../Core/BaseController.php';
require_once __DIR__ . '/../../Core/AuthManager.php';
require_once __DIR__ . '/../../Services/OpenAIService.php';

if (!class_exists('CustomExerciseController')) {
class CustomExerciseController extends BaseController
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
        
        // Verifica se é aluno
        $user = $this->authManager->getUser();
        if ($user['tipo'] !== 'aluno') {
            $this->redirect('/logout');
        }
    }
    
    /**
     * Página inicial - Menu de opções
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
            throw new Exception('Aluno não encontrado');
        }

        // Buscar listas do aluno
        $listas = $this->db->fetchAll(
            "SELECT lep.*, 
                    COUNT(qp.id) as total_questoes,
                    COUNT(sep.id) as total_sessoes
             FROM listas_personalizadas_exercicios lep
             LEFT JOIN questoes_personalizadas qp ON lep.id = qp.lista_id
             LEFT JOIN listas_personalizadas_sessoes sep ON lep.id = sep.lista_id
             WHERE lep.aluno_id = :aluno_id
             GROUP BY lep.id
             ORDER BY lep.created_at DESC",
            ['aluno_id' => $aluno['id']]
        );

        $data = [
            'title' => 'Meus Exercícios Personalizados',
            'user' => $user,
            'aluno' => $aluno,
            'listas' => $listas,
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/exercises-personalizados/index', $data);
    }
    
    /**
     * Mostrar formulário de criação
     */
    public function criar()
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
            throw new Exception('Aluno não encontrado');
        }

        // Buscar matérias do banco e adicionar outras comuns
        $materias_db = $this->db->fetchAll(
            "SELECT DISTINCT materia FROM listas_exercicios WHERE materia IS NOT NULL AND materia != '' ORDER BY materia"
        );
        
        // Lista completa de matérias
        $materias = [
            ['materia' => 'Artes'],
            ['materia' => 'Biologia'],
            ['materia' => 'Educação Física'],
            ['materia' => 'Filosofia'],
            ['materia' => 'Física'],
            ['materia' => 'Geografia'],
            ['materia' => 'História'],
            ['materia' => 'Inglês'],
            ['materia' => 'Literatura'],
            ['materia' => 'Matemática'],
            ['materia' => 'Português'],
            ['materia' => 'Química'],
            ['materia' => 'Sociologia'],
            ['materia' => 'Outros']
        ];

        $data = [
            'title' => 'Criar Exercícios Personalizados',
            'user' => $user,
            'aluno' => $aluno,
            'materias' => $materias,
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/exercises-personalizados/criar', $data);
    }
    
    /**
     * Processar criação de exercícios personalizados
     */
    public function gerarExercicios()
    {
        // LIMPAR TUDO ANTES DE QUALQUER PROCESSAMENTO
        // Remove todos os output buffers existentes
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Desabilita display de erros temporariamente
        $oldDisplayErrors = ini_get('display_errors');
        $oldErrorReporting = error_reporting();
        @ini_set('display_errors', 0);
        @error_reporting(0);
        
        // Iniciar novo output buffer limpo
        ob_start();
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            ob_clean();
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Token inválido'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            ob_end_flush();
            @ini_set('display_errors', $oldDisplayErrors);
            @error_reporting($oldErrorReporting);
            exit;
        }

        @ini_set('display_errors', 0);
        @error_reporting($oldErrorReporting);

        $logCat = 'exercicios_personalizados';
        try {
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../../Core/Logger.php';
            }

            $user = $this->authManager->getUser();
            
            $aluno = $this->db->fetch(
                "SELECT a.* FROM alunos a WHERE a.id = :user_id",
                ['user_id' => $user['id']]
            );

            if (!$aluno) {
                throw new Exception('Aluno não encontrado');
            }

            $tema = trim((string)($_POST['tema'] ?? ''));
            $materia = trim((string)($_POST['materia'] ?? ''));
            $quantidadeFacil = max(0, (int)($_POST['quantidade_facil'] ?? 0));
            $quantidadeMedio = max(0, (int)($_POST['quantidade_medio'] ?? 0));
            $quantidadeDificil = max(0, (int)($_POST['quantidade_dificil'] ?? 0));
            $quantidade = $quantidadeFacil + $quantidadeMedio + $quantidadeDificil;

            $niveis = [];
            if ($quantidadeFacil > 0) { $niveis[] = 'Fácil'; }
            if ($quantidadeMedio > 0) { $niveis[] = 'Médio'; }
            if ($quantidadeDificil > 0) { $niveis[] = 'Difícil'; }

            if ($tema === '' || $materia === '' || empty($niveis)) {
                throw new Exception('Todos os campos são obrigatórios');
            }

            if ($quantidade < 3 || $quantidade > 30) {
                throw new Exception('A soma das quantidades deve estar entre 3 e 30 exercícios');
            }

            $contextoAdicional = trim(implode("\n", array_filter([
                "Distribuição por dificuldade solicitada:",
                $quantidadeFacil > 0 ? "Fácil: {$quantidadeFacil}" : '',
                $quantidadeMedio > 0 ? "Médio: {$quantidadeMedio}" : '',
                $quantidadeDificil > 0 ? "Difícil: {$quantidadeDificil}" : '',
            ])));

            $detalhesPrompt = [
                'distribuicao_niveis' => [
                    'Fácil' => $quantidadeFacil,
                    'Médio' => $quantidadeMedio,
                    'Difícil' => $quantidadeDificil,
                ],
            ];

            // Timeout longo: geração com IA pode levar 1–2 minutos
            @set_time_limit(300);
            @ini_set('max_execution_time', 300);

            require_once __DIR__ . '/../../Services/CreditosService.php';
            $creditosSvc = new \App\Services\CreditosService();
            $refCredLista = 'ex_pers_' . (int) $aluno['id'] . '_' . str_replace('.', '', uniqid('', true));
            $moduloDebitadoLista = null;
            try {
                $creditosSvc->consumir('aluno', (int) $aluno['id'], 'gerar_exercicio_ia_aluno', $refCredLista);
                $moduloDebitadoLista = 'gerar_exercicio_ia_aluno';
            } catch (\Exception $eCred) {
                if (stripos($eCred->getMessage(), 'TudiCoins') !== false || stripos($eCred->getMessage(), 'insuficientes') !== false || stripos($eCred->getMessage(), 'Créditos') !== false) {
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    @ini_set('display_errors', $oldDisplayErrors);
                    @error_reporting($oldErrorReporting);
                    http_response_code(200);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => false,
                        'error' => $eCred->getMessage(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
                throw $eCred;
            }

            $listaId = null;
            try {
                $listaId = $this->db->insert(
                    "INSERT INTO listas_personalizadas_exercicios 
                     (aluno_id, titulo, tema, materia, quantidade_exercicios, niveis_selecionados, status) 
                     VALUES (:aluno_id, :titulo, :tema, :materia, :quantidade, :niveis, 'gerando')",
                    [
                        'aluno_id' => $aluno['id'],
                        'titulo' => $tema . ' - ' . $materia,
                        'tema' => $tema,
                        'materia' => $materia,
                        'quantidade' => $quantidade,
                        'niveis' => implode(',', $niveis),
                    ]
                );
            } catch (Exception $eIns) {
                if ($moduloDebitadoLista !== null) {
                    try {
                        $creditosSvc->estornarPorReferencia($moduloDebitadoLista, $refCredLista);
                    } catch (\Exception $eEst) {
                        Logger::warning('[EXERC PERSONALIZADO] Estorno após falha no INSERT', ['exception' => $eEst], $logCat);
                    }
                }
                throw $eIns;
            }

            // Motor único de geração de questão por IA (app/AI/) — assíncrono, via fila.
            // Substitui a chamada síncrona antiga (gerarExerciciosComIA rodava a IA
            // na mesma requisição, com timeout elevado pra suportar 1-2 min de espera).
            // A distribuição exata por nível (fácil/médio/difícil) vira instrução de
            // texto no contexto adicional — o motor único pede 1 nível "base" por
            // Blueprint, não 3 chamadas separadas; simplificação aceita nesta migração.
            try {
                require_once __DIR__ . '/../../Services/AIJobService.php';

                $nivelBase = count($niveis) === 1
                    ? ['Fácil' => 'facil', 'Médio' => 'medio', 'Difícil' => 'dificil'][$niveis[0]]
                    : 'medio';

                $payload = [
                    'disciplina' => $materia,
                    'assunto' => $tema,
                    'dificuldade' => $nivelBase,
                    'tipo_questao' => 'multipla_escolha',
                    'quantidade' => $quantidade,
                    'quantidade_alternativas' => 5,
                    'com_recurso_visual' => false,
                    'contexto_adicional' => $contextoAdicional,
                    'origem' => 'exercicio_ia',
                    'lista_id' => (int) $listaId,
                    'credits_ref' => $refCredLista,
                    'credits_modulo' => $moduloDebitadoLista,
                ];

                $jobId = \App\Services\AIJobService::enqueue(
                    'gerar_questao_ia',
                    $payload,
                    (int) $aluno['id'],
                    'aluno',
                    false // alto volume potencial — só o cron processa, ver AIJobService::enqueue()
                );
            } catch (Exception $e) {
                if ($moduloDebitadoLista !== null) {
                    try {
                        $creditosSvc->estornarPorReferencia($moduloDebitadoLista, $refCredLista);
                    } catch (\Exception $eEst) {
                        Logger::warning('[EXERC PERSONALIZADO] Estorno após erro ao enfileirar', ['exception' => $eEst], $logCat);
                    }
                }
                Logger::error('[EXERC PERSONALIZADO] Erro ao enfileirar geração', [
                    'lista_id' => $listaId,
                    'exception' => $e,
                    'message' => $e->getMessage(),
                ], $logCat);
                try {
                    $this->db->update(
                        "UPDATE listas_personalizadas_exercicios SET status = 'erro', mensagem_erro = :erro WHERE id = :lista_id",
                        ['lista_id' => $listaId, 'erro' => substr($e->getMessage(), 0, 500)]
                    );
                } catch (Exception $e2) {
                    Logger::error('[EXERC PERSONALIZADO] Falha ao atualizar status para erro', ['lista_id' => $listaId, 'exception' => $e2], $logCat);
                }
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                @ini_set('display_errors', $oldDisplayErrors);
                @error_reporting($oldErrorReporting);
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            @ini_set('display_errors', $oldDisplayErrors);
            @error_reporting($oldErrorReporting);
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'lista_id' => $listaId,
                'job_id' => $jobId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::error('[EXERC PERSONALIZADO] Exceção antes/durante criação da lista', [
                    'exception' => $e,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 'exercicios_personalizados');
            }
            error_log('[EXERC PERSONALIZADO] Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            while (ob_get_level()) {
                ob_end_clean();
            }
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    
    /**
     * Chamado pelo frontend (AIJobPoller) quando o job assíncrono do motor único
     * de geração de questão termina — transforma o formato canônico
     * (alternativas: [{texto, correta}]) pro schema rígido desta tabela
     * (colunas alternativa_a..e + resposta_correta como 1 letra maiúscula A-E,
     * exigido por CustomExerciseController::responderQuestao(), que compara
     * com === estrito sem normalização).
     */
    public function importarQuestoesIA($jobId)
    {
        $user = $this->authManager->getUser();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
            return;
        }

        require_once __DIR__ . '/../../Services/AIJobService.php';
        require_once __DIR__ . '/../../Services/CustomExerciseImportService.php';
        $job = \App\Services\AIJobService::getJob((int) $jobId);
        if (!$job || $job['status'] !== 'done') {
            $this->json(['success' => false, 'error' => 'Job não concluído ou não encontrado'], 404);
            return;
        }

        $payload = json_decode($job['payload'], true) ?: [];
        $listaId = (int) ($payload['lista_id'] ?? 0);

        $lista = $this->db->fetch(
            "SELECT id FROM listas_personalizadas_exercicios WHERE id = :id AND aluno_id = :aluno_id",
            ['id' => $listaId, 'aluno_id' => $user['id']]
        );
        if (!$lista) {
            $this->json(['success' => false, 'error' => 'Lista não encontrada'], 404);
            return;
        }

        // Mesma lógica usada pelo cron (process_ai_jobs.php) — o navegador pode chegar
        // primeiro (aluno ainda na tela) ou o cron pode ter resolvido antes; idempotente.
        // importar() usa transação e relança em caso de falha no meio do INSERT (rollback
        // desfaz e devolve a lista pra 'gerando' — o timeout do cron resolve depois);
        // aqui só precisamos não deixar essa exceção estourar sem virar um JSON limpo.
        try {
            \CustomExerciseImportService::importar($listaId, $job);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('[EXERC PERSONALIZADO] Falha ao importar questões da IA', ['lista_id' => $listaId, 'exception' => $e], 'exercicios_personalizados');
            }
            $this->json(['success' => false, 'error' => 'Erro ao salvar os exercícios. Tente novamente em instantes.'], 500);
            return;
        }

        $listaAtualizada = $this->db->fetch(
            "SELECT status, mensagem_erro FROM listas_personalizadas_exercicios WHERE id = :id",
            ['id' => $listaId]
        );
        if (($listaAtualizada['status'] ?? '') === 'erro') {
            $this->json(['success' => false, 'error' => $listaAtualizada['mensagem_erro'] ?? 'Não foi possível gerar os exercícios'], 422);
            return;
        }

        $this->json(['success' => true, 'lista_id' => $listaId]);
    }

    /**
     * Chamado pelo frontend quando o AIJobPoller reporta falha — marca a lista
     * como erro (o estorno de crédito já acontece sozinho dentro de
     * AIJobService quando o job falha definitivamente, via credits_ref/credits_modulo
     * salvos no payload).
     */
    public function erroGeracaoIA()
    {
        $user = $this->authManager->getUser();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
            return;
        }
        $listaId = (int) ($_POST['lista_id'] ?? 0);

        $lista = $this->db->fetch(
            "SELECT id FROM listas_personalizadas_exercicios WHERE id = :id AND aluno_id = :aluno_id",
            ['id' => $listaId, 'aluno_id' => $user['id']]
        );
        if (!$lista) {
            $this->json(['success' => false, 'error' => 'Lista não encontrada'], 404);
            return;
        }

        $this->db->update(
            "UPDATE listas_personalizadas_exercicios SET status = 'erro', mensagem_erro = 'Não foi possível gerar os exercícios.' WHERE id = :id",
            ['id' => $listaId]
        );

        $this->json(['success' => true]);
    }

    /**
     * Botão "Tentar novamente" em "Minhas Listas" (lista com status='erro', inclusive
     * as que o cron marcou como erro por ter travado em 'gerando' além do tempo limite).
     * Reaproveita tema/matéria/quantidade do job anterior (guardados em ai_jobs.payload),
     * cobra créditos de novo (a falha anterior já foi estornada automaticamente pelo
     * AIJobService) e reenfileira.
     */
    public function tentarNovamente($listaId)
    {
        $user = $this->authManager->getUser();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['success' => false, 'error' => 'Token inválido'], 400);
            return;
        }

        $listaId = (int) $listaId;
        $lista = $this->db->fetch(
            "SELECT * FROM listas_personalizadas_exercicios WHERE id = :id AND aluno_id = :aluno_id",
            ['id' => $listaId, 'aluno_id' => $user['id']]
        );
        if (!$lista) {
            $this->json(['success' => false, 'error' => 'Lista não encontrada'], 404);
            return;
        }
        if ($lista['status'] !== 'erro') {
            $this->json(['success' => false, 'error' => 'Essa lista não está com erro.'], 400);
            return;
        }

        $ultimoJob = $this->db->fetch(
            "SELECT payload FROM ai_jobs
             WHERE job_type = 'gerar_questao_ia' AND JSON_EXTRACT(payload, '$.lista_id') = :lista_id
             ORDER BY id DESC LIMIT 1",
            ['lista_id' => $listaId]
        );
        $payloadAnterior = $ultimoJob ? (json_decode($ultimoJob['payload'], true) ?: []) : [];

        // Cobrança primeiro, fora do try de reenfileiramento: se o crédito em si falhar
        // (ex.: saldo insuficiente), não há nada a estornar — só devolve o erro.
        require_once __DIR__ . '/../../Services/CreditosService.php';
        $creditosSvc = new \App\Services\CreditosService();
        $refCredLista = 'ex_pers_retry_' . $listaId . '_' . str_replace('.', '', uniqid('', true));
        try {
            $creditosSvc->consumir('aluno', (int) $user['id'], 'gerar_exercicio_ia_aluno', $refCredLista);
        } catch (\Exception $eCred) {
            $this->json(['success' => false, 'error' => $eCred->getMessage()], 400);
            return;
        }

        try {
            // Sem questões parciais de uma importação anterior mal-sucedida.
            $this->db->delete("DELETE FROM questoes_personalizadas WHERE lista_id = :id", ['id' => $listaId]);

            $this->db->update(
                "UPDATE listas_personalizadas_exercicios SET status = 'gerando', mensagem_erro = NULL WHERE id = :id",
                ['id' => $listaId]
            );

            require_once __DIR__ . '/../../Services/AIJobService.php';
            $payload = array_merge($payloadAnterior, [
                'disciplina' => $payloadAnterior['disciplina'] ?? $lista['materia'],
                'assunto' => $payloadAnterior['assunto'] ?? $lista['tema'],
                'quantidade' => $payloadAnterior['quantidade'] ?? $lista['quantidade_exercicios'],
                'lista_id' => $listaId,
                'credits_ref' => $refCredLista,
                'credits_modulo' => 'gerar_exercicio_ia_aluno',
            ]);
            $jobId = \App\Services\AIJobService::enqueue('gerar_questao_ia', $payload, (int) $user['id'], 'aluno', false);

            $this->json(['success' => true, 'lista_id' => $listaId, 'job_id' => $jobId]);
        } catch (\Exception $e) {
            // Crédito já foi debitado acima — estorna, igual ao fluxo de gerarExercicios().
            try {
                $creditosSvc->estornarPorReferencia('gerar_exercicio_ia_aluno', $refCredLista);
            } catch (\Exception $eEst) {
                if (class_exists('Logger')) {
                    Logger::warning('[EXERC PERSONALIZADO] Estorno após falha em tentarNovamente', ['lista_id' => $listaId, 'exception' => $eEst], 'exercicios_personalizados');
                }
            }
            $this->db->update(
                "UPDATE listas_personalizadas_exercicios SET status = 'erro', mensagem_erro = :erro WHERE id = :id",
                ['id' => $listaId, 'erro' => mb_substr($e->getMessage(), 0, 500)]
            );
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Listar minhas listas de exercícios
     */
    public function listaMinhasListas()
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
            throw new Exception('Aluno não encontrado');
        }

        // Buscar listas do aluno
        $listas = $this->db->fetchAll(
            "SELECT lep.*, 
                    COUNT(qp.id) as total_questoes,
                    COUNT(sep.id) as total_sessoes
             FROM listas_personalizadas_exercicios lep
             LEFT JOIN questoes_personalizadas qp ON lep.id = qp.lista_id
             LEFT JOIN listas_personalizadas_sessoes sep ON lep.id = sep.lista_id
             WHERE lep.aluno_id = :aluno_id
             GROUP BY lep.id
             ORDER BY lep.created_at DESC",
            ['aluno_id' => $aluno['id']]
        );

        $data = [
            'title' => 'Minhas Listas de Exercícios',
            'user' => $user,
            'aluno' => $aluno,
            'listas' => $listas,
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/exercises-personalizados/lista-minhas', $data);
    }
    
    /**
     * Verificar status da geração
     */
    public function verificarStatus()
    {
        $user = $this->authManager->getUser();
        
        $listaId = $_GET['lista_id'] ?? '';
        
        $lista = $this->db->fetch(
            "SELECT lep.*, COUNT(qp.id) as total_questoes
             FROM listas_personalizadas_exercicios lep
             LEFT JOIN questoes_personalizadas qp ON lep.id = qp.lista_id
             WHERE lep.id = :lista_id AND lep.aluno_id = :aluno_id
             GROUP BY lep.id",
            ['lista_id' => $listaId, 'aluno_id' => $user['id']]
        );

        if (!$lista) {
            $this->json(['error' => 'Lista não encontrada'], 404);
        }

        $this->json([
            'status' => $lista['status'],
            'total_questoes' => $lista['total_questoes'],
            'mensagem_erro' => $lista['mensagem_erro']
        ]);
    }
    
    /**
     * Iniciar execução de exercícios
     */
    public function iniciarExecucao()
    {
        // Limpar qualquer output anterior
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        ob_start();
        
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            ob_clean();
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Token inválido'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            ob_end_flush();
            exit;
        }

        try {
            $user = $this->authManager->getUser();
            $listaId = $_POST['lista_id'] ?? '';

            if (empty($listaId)) {
                throw new Exception('Lista ID é obrigatório');
            }

            // Verificar se a lista existe e pertence ao aluno
            $lista = $this->db->fetch(
                "SELECT * FROM listas_personalizadas_exercicios 
                 WHERE id = :lista_id AND aluno_id = :aluno_id AND status = 'concluido'",
                ['lista_id' => $listaId, 'aluno_id' => $user['id']]
            );

            if (!$lista) {
                // Verificar se a lista existe mas não está concluída
                $listaCheck = $this->db->fetch(
                    "SELECT id, status FROM listas_personalizadas_exercicios 
                     WHERE id = :lista_id AND aluno_id = :aluno_id",
                    ['lista_id' => $listaId, 'aluno_id' => $user['id']]
                );
                
                if ($listaCheck) {
                    throw new Exception('Lista ainda não finalizada. Status: ' . $listaCheck['status']);
                } else {
                    throw new Exception('Lista não encontrada ou não pertence a você');
                }
            }

            // Verificar se há questões antes de criar a sessão
            $questoes = $this->db->fetchAll(
                "SELECT * FROM questoes_personalizadas 
                 WHERE lista_id = :lista_id 
                 ORDER BY ordem ASC",
                ['lista_id' => $listaId]
            );

            if (empty($questoes)) {
                throw new Exception('Nenhuma questão encontrada nesta lista. A lista pode não ter sido gerada corretamente.');
            }

            // Criar sessão de execução
            $sessaoId = $this->db->insert(
                "INSERT INTO listas_personalizadas_sessoes (aluno_id, lista_id, status, started_at) 
                 VALUES (:aluno_id, :lista_id, 'em_andamento', NOW())",
                ['aluno_id' => $user['id'], 'lista_id' => $listaId]
            );

            if (!$sessaoId) {
                throw new Exception('Erro ao criar sessão de execução');
            }

            ob_clean();
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            
            echo json_encode([
                'success' => true,
                'sessao_id' => $sessaoId,
                'total' => count($questoes)
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            ob_end_flush();
            exit;

        } catch (Exception $e) {
            error_log("Erro em iniciarExecucao: " . $e->getMessage());
            if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            }
            
            ob_clean();
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            ob_end_flush();
            exit;
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Responder questão
     */
    public function responderQuestao()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        try {
            $user = $this->authManager->getUser();
            $sessaoId = $_POST['sessao_id'] ?? '';
            $questaoId = $_POST['questao_id'] ?? '';
            $resposta = $_POST['resposta'] ?? '';

            if (empty($sessaoId) || empty($questaoId) || empty($resposta)) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }

            // Buscar questão
            $questao = $this->db->fetch(
                "SELECT * FROM questoes_personalizadas WHERE id = :questao_id",
                ['questao_id' => $questaoId]
            );

            if (!$questao) {
                throw new Exception('Questão não encontrada');
            }

            $isCorrect = ($resposta === $questao['resposta_correta']);

            // Salvar resposta
            $this->db->insert(
                "INSERT INTO listas_personalizadas_respostas 
                 (sessao_id, questao_id, aluno_id, resposta, is_correct) 
                 VALUES (:sessao_id, :questao_id, :aluno_id, :resposta, :is_correct)",
                [
                    'sessao_id' => $sessaoId,
                    'questao_id' => $questaoId,
                    'aluno_id' => $user['id'],
                    'resposta' => $resposta,
                    'is_correct' => $isCorrect ? 1 : 0
                ]
            );

            $this->json([
                'success' => true,
                'is_correct' => $isCorrect,
                'explicacao' => $questao['explicacao'],
                'resposta_correta' => $questao['resposta_correta']
            ]);

        } catch (Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Finalizar sessão de exercícios
     */
    public function finalizar()
    {
        $user = $this->authManager->getUser();
        $sessaoId = $_GET['sessao_id'] ?? $_POST['sessao_id'] ?? '';

        if (empty($sessaoId)) {
            $this->redirect('/exercicios-personalizados/minhas-listas');
            return;
        }

        try {
            // Buscar sessão
            $sessao = $this->db->fetch(
                "SELECT * FROM listas_personalizadas_sessoes 
                 WHERE id = :sessao_id AND aluno_id = :aluno_id",
                ['sessao_id' => $sessaoId, 'aluno_id' => $user['id']]
            );

            if (!$sessao) {
                throw new Exception('Sessão não encontrada');
            }

            // Buscar todas as respostas
            $respostas = $this->db->fetchAll(
                "SELECT * FROM listas_personalizadas_respostas 
                 WHERE sessao_id = :sessao_id",
                ['sessao_id' => $sessaoId]
            );

            // Calcular estatísticas
            $total = count($respostas);
            $corretas = array_sum(array_column($respostas, 'is_correct'));
            $percentual = $total > 0 ? ($corretas / $total) * 100 : 0;

            // Atualizar sessão
            $this->db->query(
                "UPDATE listas_personalizadas_sessoes 
                 SET status = 'finalizado', finished_at = NOW() 
                 WHERE id = :sessao_id",
                ['sessao_id' => $sessaoId]
            );

            // Redirecionar para página de resultados
            $this->redirect('/exercicios-personalizados/resultados?sessao_id=' . $sessaoId);
            return;

        } catch (Exception $e) {
            $this->redirect('/exercicios-personalizados/minhas-listas');
        }
    }
    
    /**
     * Página de execução de exercícios
     */
    public function executar()
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
            throw new Exception('Aluno não encontrado');
        }

        $sessaoId = $_GET['sessao_id'] ?? '';

        if (empty($sessaoId)) {
            throw new Exception('Sessão ID é obrigatório');
        }

        // Buscar sessão
        $sessao = $this->db->fetch(
            "SELECT sep.*, lep.titulo as lista_titulo
             FROM listas_personalizadas_sessoes sep
             LEFT JOIN listas_personalizadas_exercicios lep ON sep.lista_id = lep.id
             WHERE sep.id = :sessao_id AND sep.aluno_id = :aluno_id",
            ['sessao_id' => $sessaoId, 'aluno_id' => $user['id']]
        );

        if (!$sessao) {
            throw new Exception('Sessão não encontrada');
        }

        // Buscar questões
        $questoes = $this->db->fetchAll(
            "SELECT qp.*, rep.resposta as resposta_escolhida, rep.is_correct
             FROM questoes_personalizadas qp
             LEFT JOIN listas_personalizadas_respostas rep ON qp.id = rep.questao_id AND rep.sessao_id = :sessao_id
             WHERE qp.lista_id = :lista_id
             ORDER BY qp.ordem ASC",
            ['sessao_id' => $sessaoId, 'lista_id' => $sessao['lista_id']]
        );

        $questao_atual = (int)($_GET['questao'] ?? 1);

        // Processar resposta se houver POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $questao_id = $_POST['questao_id'] ?? '';
            $resposta_escolhida = $_POST['resposta_escolhida'] ?? '';
            $proxima_questao = (int)($_POST['proxima_questao'] ?? 0);

            if (!empty($questao_id) && !empty($resposta_escolhida)) {
                // Buscar questão para verificar resposta
                $questao_atual_data = $this->db->fetch(
                    "SELECT * FROM questoes_personalizadas WHERE id = :questao_id",
                    ['questao_id' => $questao_id]
                );

                if ($questao_atual_data) {
                    $is_correct = ($resposta_escolhida === $questao_atual_data['resposta_correta']) ? 1 : 0;

                    // Verificar se já existe resposta
                    $resposta_existente = $this->db->fetch(
                        "SELECT * FROM listas_personalizadas_respostas 
                         WHERE sessao_id = :sessao_id AND questao_id = :questao_id",
                        ['sessao_id' => $sessaoId, 'questao_id' => $questao_id]
                    );

                    if ($resposta_existente) {
                        // Atualizar resposta existente
                        $this->db->query(
                            "UPDATE listas_personalizadas_respostas 
                             SET resposta = :resposta, is_correct = :is_correct 
                             WHERE sessao_id = :sessao_id AND questao_id = :questao_id",
                            [
                                'resposta' => $resposta_escolhida,
                                'is_correct' => $is_correct,
                                'sessao_id' => $sessaoId,
                                'questao_id' => $questao_id
                            ]
                        );
                    } else {
                        // Inserir nova resposta
                        $this->db->insert(
                            "INSERT INTO listas_personalizadas_respostas 
                             (sessao_id, questao_id, aluno_id, resposta, is_correct) 
                             VALUES (:sessao_id, :questao_id, :aluno_id, :resposta, :is_correct)",
                            [
                                'sessao_id' => $sessaoId,
                                'questao_id' => $questao_id,
                                'aluno_id' => $user['id'],
                                'resposta' => $resposta_escolhida,
                                'is_correct' => $is_correct
                            ]
                        );
                    }
                }
            }

            // Se próxima questão é 0, finalizar
            if ($proxima_questao === 0) {
                // Redirecionar para página de resultados
                $this->redirect('/exercicios-personalizados/finalizar?sessao_id=' . $sessaoId);
                return;
            } else {
                // Redirecionar para próxima questão
                $this->redirect('/exercicios-personalizados/executar?sessao_id=' . $sessaoId . '&questao=' . $proxima_questao);
                return;
            }
        }

        $data = [
            'title' => 'Fazendo Exercícios Personalizados',
            'user' => $user,
            'aluno' => $aluno,
            'sessao' => $sessao,
            'questoes' => $questoes,
            'questao_atual' => $questao_atual,
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/exercises-personalizados/executar', $data);
    }
    
    /**
     * Resultados da execução
     */
    public function resultados()
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
            throw new Exception('Aluno não encontrado');
        }

        $sessaoId = $_GET['sessao_id'] ?? '';

        if (empty($sessaoId)) {
            throw new Exception('Sessão ID é obrigatório');
        }

        // Buscar sessão finalizada
        $sessao = $this->db->fetch(
            "SELECT sep.*, lep.titulo as lista_titulo, lep.tema, lep.materia
             FROM listas_personalizadas_sessoes sep
             LEFT JOIN listas_personalizadas_exercicios lep ON sep.lista_id = lep.id
             WHERE sep.id = :sessao_id AND sep.aluno_id = :aluno_id AND sep.status = 'finalizado'",
            ['sessao_id' => $sessaoId, 'aluno_id' => $user['id']]
        );

        if (!$sessao) {
            throw new Exception('Sessão não encontrada ou não finalizada');
        }

        // Buscar questões com respostas
        $questoes = $this->db->fetchAll(
            "SELECT qp.*, rep.resposta as resposta_escolhida, rep.is_correct
             FROM questoes_personalizadas qp
             LEFT JOIN listas_personalizadas_respostas rep ON qp.id = rep.questao_id AND rep.sessao_id = :sessao_id
             WHERE qp.lista_id = :lista_id
             ORDER BY qp.ordem ASC",
            ['sessao_id' => $sessaoId, 'lista_id' => $sessao['lista_id']]
        );

        // Calcular estatísticas
        $total = count($questoes);
        $corretas = count(array_filter($questoes, function($q) { return $q['is_correct'] == 1; }));
        $erradas = $total - $corretas;
        $percentual = $total > 0 ? round(($corretas / $total) * 100, 2) : 0;

        $data = [
            'title' => 'Resultados dos Exercícios',
            'user' => $user,
            'aluno' => $aluno,
            'sessao' => $sessao,
            'questoes' => $questoes,
            'total' => $total,
            'corretas' => $corretas,
            'erradas' => $erradas,
            'percentual' => $percentual,
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/exercises-personalizados/resultados', $data);
    }
    
    /**
     * Histórico de execuções de uma lista
     */
    public function historico()
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
            throw new Exception('Aluno não encontrado');
        }

        $listaId = $_GET['lista_id'] ?? '';
        $sessaoId = $_GET['sessao_id'] ?? '';

        if (empty($listaId)) {
            $this->redirect('/exercicios-personalizados/minhas-listas');
            return;
        }

        // Buscar lista
        $lista = $this->db->fetch(
            "SELECT * FROM listas_personalizadas_exercicios 
             WHERE id = :lista_id AND aluno_id = :aluno_id",
            ['lista_id' => $listaId, 'aluno_id' => $user['id']]
        );

        if (!$lista) {
            throw new Exception('Lista não encontrada');
        }

        // Buscar todas as sessões desta lista
        $sessoes = $this->db->fetchAll(
            "SELECT * FROM listas_personalizadas_sessoes 
             WHERE lista_id = :lista_id AND aluno_id = :aluno_id AND status = 'finalizado'
             ORDER BY started_at DESC",
            ['lista_id' => $listaId, 'aluno_id' => $user['id']]
        );

        foreach ($sessoes as &$sessaoItem) {
            $sessaoItem['total_respostas'] = 0;
            $sessaoItem['corretas'] = 0;
            $sessaoItem['percentual'] = 0;
        }
        unset($sessaoItem);

        if (!empty($sessoes)) {
            $paramsStats = ['aluno_id' => (int) $user['id']];
            $placeholders = [];
            foreach (array_values($sessoes) as $i => $sessaoItem) {
                $chave = 'sid' . $i;
                $placeholders[] = ':' . $chave;
                $paramsStats[$chave] = (int) $sessaoItem['id'];
            }
            $statsSessoes = $this->db->fetchAll(
                "SELECT sessao_id,
                        COUNT(*) AS total_respostas,
                        SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS corretas
                 FROM listas_personalizadas_respostas
                 WHERE aluno_id = :aluno_id
                   AND sessao_id IN (" . implode(', ', $placeholders) . ")
                 GROUP BY sessao_id",
                $paramsStats
            );
            $statsPorId = [];
            foreach ($statsSessoes as $stat) {
                $statsPorId[(int) $stat['sessao_id']] = $stat;
            }
            foreach ($sessoes as &$sessaoItem) {
                $stat = $statsPorId[(int) $sessaoItem['id']] ?? null;
                $totalSessao = $stat ? (int) $stat['total_respostas'] : 0;
                $corretasSessao = $stat ? (int) $stat['corretas'] : 0;
                $sessaoItem['total_respostas'] = $totalSessao;
                $sessaoItem['corretas'] = $corretasSessao;
                $sessaoItem['percentual'] = $totalSessao > 0 ? (int) round(($corretasSessao / $totalSessao) * 100) : 0;
            }
            unset($sessaoItem);
        }

        if ($sessaoId === '' && !empty($sessoes)) {
            $sessaoId = (string) $sessoes[0]['id'];
        }

        // Se uma sessão específica foi escolhida, buscar seus dados
        $sessao_selecionada = null;
        $questoes_com_respostas = [];

        if (!empty($sessaoId)) {
            $sessao_selecionada = $this->db->fetch(
                "SELECT * FROM listas_personalizadas_sessoes 
                 WHERE id = :sessao_id AND lista_id = :lista_id AND aluno_id = :aluno_id",
                ['sessao_id' => $sessaoId, 'lista_id' => $listaId, 'aluno_id' => $user['id']]
            );

            if ($sessao_selecionada) {
                // Buscar questões com respostas
                $questoes_com_respostas = $this->db->fetchAll(
                    "SELECT qp.*, rep.resposta as resposta_escolhida, rep.is_correct
                     FROM questoes_personalizadas qp
                     LEFT JOIN listas_personalizadas_respostas rep ON qp.id = rep.questao_id AND rep.sessao_id = :sessao_id
                     WHERE qp.lista_id = :lista_id
                     ORDER BY qp.ordem ASC",
                    ['sessao_id' => $sessaoId, 'lista_id' => $listaId]
                );
            }
        }

        $data = [
            'title' => 'Histórico de Exercícios',
            'user' => $user,
            'aluno' => $aluno,
            'lista' => $lista,
            'sessoes' => $sessoes,
            'sessao_id' => $sessaoId,
            'sessao_selecionada' => $sessao_selecionada,
            'questoes' => $questoes_com_respostas,
            'current_page' => 'exercises',
            'csrf_token' => $this->generateCsrfToken()
        ];

        $this->viewWithLayout('student', 'student/exercises-personalizados/historico', $data);
    }
}
}

