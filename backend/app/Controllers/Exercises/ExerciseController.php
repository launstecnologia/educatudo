<?php
/**
 * Controller para gerenciar exercícios com histórico e restrições
 */

require_once 'app/Core/BaseController.php';
require_once 'app/Core/AuthManager.php';

if (!class_exists('ExerciseController')) {
class ExerciseController extends BaseController
{
    private $authManager;
    private $db;

    public function __construct()
    {
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
    }

    /**
     * Lista exercícios disponíveis com dashboard e paginação
     */
    public function index()
    {
        try {
            // Temporariamente, o menu "Exercícios" deve abrir direto a geração por IA.
            $this->redirect('/exercicios-personalizados/criar');
            return;
            
        } catch (Exception $e) {
            $this->redirect('/exercicios?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Inicia execução de exercícios com verificação de 24h
     */
    public function iniciar()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new Exception('Acesso negado');
            }
            
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

            $lista_id = $_GET['id'] ?? '';
            $questao_atual = (int)($_GET['questao'] ?? 1);

            if (empty($lista_id)) {
                throw new Exception('ID da lista é obrigatório');
            }

            // Buscar lista de exercícios
            $lista = $this->db->fetch(
                "SELECT * FROM listas_exercicios WHERE id = :lista_id AND ativo = 1",
                ['lista_id' => $lista_id]
            );

            if (!$lista) {
                throw new Exception('Lista de exercícios não encontrada');
            }

            // Verificar restrição de 24h
            $ultima_execucao = $this->db->fetch(
                "SELECT * FROM exercicios_historico 
                 WHERE aluno_id = :aluno_id AND lista_id = :lista_id 
                 ORDER BY data_execucao DESC LIMIT 1",
                ['aluno_id' => $aluno['id'], 'lista_id' => $lista_id]
            );

            if ($ultima_execucao) {
                $data_ultima_execucao = strtotime($ultima_execucao['data_execucao']);
                $agora = time();
                $diferenca_horas = ($agora - $data_ultima_execucao) / 3600;

                if ($diferenca_horas < 24) {
                    $horas_restantes = 24 - $diferenca_horas;
                    $this->redirect("/exercicios?error=" . urlencode("Você só pode refazer este exercício em " . number_format($horas_restantes, 1) . " horas."));
                    return;
                }
            }

            // Verificar se já existe sessão em andamento
            $sessaoExistente = $this->db->fetch(
                "SELECT * FROM exercicios_sessoes 
                 WHERE aluno_id = :aluno_id AND lista_id = :lista_id AND finished_at IS NULL",
                ['aluno_id' => $aluno['id'], 'lista_id' => $lista_id]
            );

            if ($sessaoExistente) {
                // Continuar sessão existente
                $sessao_id = $sessaoExistente['id'];
            } else {
                // Criar nova sessão
                $sessao_id = $this->db->insert(
                    "INSERT INTO exercicios_sessoes (aluno_id, lista_id, started_at) 
                     VALUES (:aluno_id, :lista_id, NOW())",
                    ['aluno_id' => $aluno['id'], 'lista_id' => $lista_id]
                );
            }

            // Buscar questões da lista
            $questoes = $this->db->fetchAll(
                "SELECT q.*, re.resposta as resposta_escolhida, re.tempo_resposta as tempo_gasto, re.is_correct as acertou
                 FROM questoes q
                 LEFT JOIN exercicios_respostas re ON q.id = re.exercicio_id AND re.sessao_id = :sessao_id
                 WHERE q.lista_id = :lista_id AND q.ativo = 1
                 ORDER BY q.ordem",
                ['lista_id' => $lista_id, 'sessao_id' => $sessao_id]
            );

            if (empty($questoes)) {
                throw new Exception('Nenhuma questão encontrada nesta lista');
            }

            $data = [
                'title' => 'Exercícios - ' . $lista['titulo'],
                'user' => $user,
                'lista' => $lista,
                'questoes' => $questoes,
                'execucao_id' => $sessao_id,
                'aluno' => $aluno,
                'questao_atual' => $questao_atual,
                'total_questoes' => count($questoes),
                'csrf_token' => $this->generateCsrfToken()
            ];

            $this->viewWithLayout('student', 'student/exercises/exercicio-execucao', $data);

        } catch (Exception $e) {
            $this->redirect('/exercicios?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Responde uma questão
     */
    public function responder()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new Exception('Acesso negado');
            }
            
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

            $sessao_id = $_POST['execucao_id'] ?? '';
            $questao_id = $_POST['questao_id'] ?? '';
            $resposta_escolhida = mb_substr(trim((string)($_POST['resposta_escolhida'] ?? '')), 0, 100);
            $tempo_gasto = (int)($_POST['tempo_gasto'] ?? 0);
            $proxima_questao = (int)($_POST['proxima_questao'] ?? 0);

            if (empty($sessao_id) || empty($questao_id)) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }

            // Verificar se a sessão pertence ao aluno
            $sessao = $this->db->fetch(
                "SELECT * FROM exercicios_sessoes WHERE id = :sessao_id AND aluno_id = :aluno_id",
                ['sessao_id' => $sessao_id, 'aluno_id' => $aluno['id']]
            );

            if (!$sessao) {
                throw new Exception('Sessão não encontrada');
            }

            // Buscar questão
            $questao = $this->db->fetch(
                "SELECT * FROM questoes WHERE id = :questao_id",
                ['questao_id' => $questao_id]
            );

            if (!$questao) {
                throw new Exception('Questão não encontrada');
            }

            // Verificar se já existe resposta
            $respostaExistente = $this->db->fetch(
                "SELECT * FROM exercicios_respostas WHERE sessao_id = :sessao_id AND exercicio_id = :questao_id",
                ['sessao_id' => $sessao_id, 'questao_id' => $questao_id]
            );

            $acertou = !empty($resposta_escolhida) && $resposta_escolhida === $questao['resposta_correta'];

            if ($respostaExistente) {
                // Atualizar resposta existente
                $this->db->update(
                    "UPDATE exercicios_respostas 
                     SET resposta = :resposta_escolhida, 
                         tempo_resposta = :tempo_gasto, 
                         is_correct = :acertou,
                         answered_at = NOW()
                     WHERE id = :resposta_id",
                    [
                        'resposta_escolhida' => $resposta_escolhida,
                        'tempo_gasto' => $tempo_gasto,
                        'acertou' => $acertou ? 1 : 0,
                        'resposta_id' => $respostaExistente['id']
                    ]
                );
            } else {
                // Criar nova resposta
                $this->db->insert(
                    "INSERT INTO exercicios_respostas 
                     (sessao_id, exercicio_id, aluno_id, resposta, is_correct, tempo_resposta) 
                     VALUES (:sessao_id, :questao_id, :aluno_id, :resposta_escolhida, :acertou, :tempo_gasto)",
                    [
                        'sessao_id' => $sessao_id,
                        'questao_id' => $questao_id,
                        'aluno_id' => $aluno['id'],
                        'resposta_escolhida' => $resposta_escolhida,
                        'acertou' => $acertou ? 1 : 0,
                        'tempo_gasto' => $tempo_gasto
                    ]
                );
            }

            // Redirecionar para a próxima questão ou finalizar
            if ($proxima_questao > 0) {
                $this->redirect("/exercicios/iniciar?id={$sessao['lista_id']}&questao={$proxima_questao}&success=Resposta salva");
            } else {
                // Finalizar automaticamente quando todas as questões forem respondidas
                $this->finalizarAutomaticamente($sessao_id, $aluno);
            }

        } catch (Exception $e) {
            $this->redirect('/exercicios?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Finaliza automaticamente um exercício quando todas as questões são respondidas
     */
    private function finalizarAutomaticamente($sessao_id, $aluno)
    {
        try {
            // Buscar sessão
            $sessao = $this->db->fetch(
                "SELECT * FROM exercicios_sessoes WHERE id = :sessao_id",
                ['sessao_id' => $sessao_id]
            );

            if (!$sessao) {
                throw new Exception('Sessão não encontrada');
            }

            // Calcular estatísticas finais
            $estatisticas = $this->db->fetch(
                "SELECT 
                    COUNT(*) as total_questoes,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as questoes_corretas,
                    SUM(tempo_resposta) as tempo_total_respostas
                 FROM exercicios_respostas 
                 WHERE sessao_id = :sessao_id",
                ['sessao_id' => $sessao_id]
            );

            $percentual_acerto = $estatisticas['total_questoes'] > 0 
                ? ($estatisticas['questoes_corretas'] / $estatisticas['total_questoes']) * 100 
                : 0;

            $tempo_total = $estatisticas['tempo_total_respostas'] ?? 0;

            // Atualizar sessão
            $this->db->update(
                "UPDATE exercicios_sessoes 
                 SET finished_at = NOW(), 
                     tempo_gasto = :tempo_total
                 WHERE id = :sessao_id",
                [
                    'tempo_total' => $tempo_total,
                    'sessao_id' => $sessao_id
                ]
            );
            
            // Registrar uso diário de exercícios
            $this->db->insert(
                "INSERT INTO alunos_acoes_diarias (aluno_id, acao, created_at) 
                 VALUES (:aluno_id, 'exercicios', NOW())",
                ['aluno_id' => $aluno['id']]
            );

            // Salvar no histórico
            $this->db->insert(
                "INSERT INTO exercicios_historico 
                 (aluno_id, lista_id, sessao_id, data_execucao, tempo_total, questoes_corretas, questoes_total, percentual_acerto, status) 
                 VALUES (:aluno_id, :lista_id, :sessao_id, NOW(), :tempo_total, :questoes_corretas, :questoes_total, :percentual_acerto, 'finalizado')",
                [
                    'aluno_id' => $aluno['id'],
                    'lista_id' => $sessao['lista_id'],
                    'sessao_id' => $sessao_id,
                    'tempo_total' => $tempo_total,
                    'questoes_corretas' => $estatisticas['questoes_corretas'],
                    'questoes_total' => $estatisticas['total_questoes'],
                    'percentual_acerto' => $percentual_acerto
                ]
            );

            // Atualizar estatísticas do aluno
            $this->atualizarEstatisticasAluno($aluno['id'], $sessao['lista_id']);

            // Redirecionar para resultado
            $this->redirect("/exercicios/resultado?id={$sessao_id}&success=Exercício finalizado automaticamente!");

        } catch (Exception $e) {
            error_log("Erro na finalização automática: " . $e->getMessage());
            $this->redirect('/exercicios?error=' . urlencode('Erro ao finalizar exercício: ' . $e->getMessage()));
        }
    }

    /**
     * Finaliza execução de exercícios
     */
    public function finalizar()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new Exception('Acesso negado');
            }
            
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

            // Detectar se é GET ou POST
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // GET - apenas redirecionar para resultado
                $sessao_id = $_GET['id'] ?? '';
                
                if (empty($sessao_id)) {
                    throw new Exception('ID da sessão é obrigatório');
                }

                // Verificar se a sessão pertence ao aluno
                $sessao = $this->db->fetch(
                    "SELECT * FROM exercicios_sessoes WHERE id = :sessao_id AND aluno_id = :aluno_id",
                    ['sessao_id' => $sessao_id, 'aluno_id' => $aluno['id']]
                );

                if (!$sessao) {
                    throw new Exception('Sessão não encontrada');
                }

                // Redirecionar para resultado
                $this->redirect("/exercicios/resultado?id={$sessao_id}&success=Exercício finalizado com sucesso!");
                return;
            }

            // POST - processar finalização
            if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
                $this->redirect('/exercicios?error=Token inválido');
            }

            $sessao_id = $_POST['execucao_id'] ?? '';
            $tempo_total = (int)($_POST['tempo_total'] ?? 0);

            if (empty($sessao_id)) {
                throw new Exception('ID da sessão é obrigatório');
            }

            // Verificar se a sessão pertence ao aluno
            $sessao = $this->db->fetch(
                "SELECT * FROM exercicios_sessoes WHERE id = :sessao_id AND aluno_id = :aluno_id",
                ['sessao_id' => $sessao_id, 'aluno_id' => $aluno['id']]
            );

            if (!$sessao) {
                throw new Exception('Sessão não encontrada');
            }

            // Calcular estatísticas finais
            $estatisticas = $this->db->fetch(
                "SELECT 
                    COUNT(*) as total_questoes,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as questoes_corretas,
                    AVG(tempo_resposta) as tempo_medio_por_questao
                 FROM exercicios_respostas 
                 WHERE sessao_id = :sessao_id",
                ['sessao_id' => $sessao_id]
            );

            $percentual_acerto = $estatisticas['total_questoes'] > 0 
                ? ($estatisticas['questoes_corretas'] / $estatisticas['total_questoes']) * 100 
                : 0;

            // Atualizar sessão
            $this->db->update(
                "UPDATE exercicios_sessoes 
                 SET finished_at = NOW(), 
                     tempo_gasto = :tempo_total
                 WHERE id = :sessao_id",
                [
                    'tempo_total' => $tempo_total,
                    'sessao_id' => $sessao_id
                ]
            );
            
            // Registrar uso diário de exercícios
            $this->db->insert(
                "INSERT INTO alunos_acoes_diarias (aluno_id, acao, created_at) 
                 VALUES (:aluno_id, 'exercicios', NOW())",
                ['aluno_id' => $aluno['id']]
            );

            // Salvar no histórico
            $this->db->insert(
                "INSERT INTO exercicios_historico 
                 (aluno_id, lista_id, sessao_id, data_execucao, tempo_total, questoes_corretas, questoes_total, percentual_acerto, status) 
                 VALUES (:aluno_id, :lista_id, :sessao_id, NOW(), :tempo_total, :questoes_corretas, :questoes_total, :percentual_acerto, 'finalizado')",
                [
                    'aluno_id' => $aluno['id'],
                    'lista_id' => $sessao['lista_id'],
                    'sessao_id' => $sessao_id,
                    'tempo_total' => $tempo_total,
                    'questoes_corretas' => $estatisticas['questoes_corretas'],
                    'questoes_total' => $estatisticas['total_questoes'],
                    'percentual_acerto' => $percentual_acerto
                ]
            );

            // Atualizar estatísticas do aluno
            $this->atualizarEstatisticasAluno($aluno['id'], $sessao['lista_id']);

            // Redirecionar para resultado
            $this->redirect("/exercicios/resultado?id={$sessao_id}&success=Exercício finalizado com sucesso!");

        } catch (Exception $e) {
            $this->redirect('/exercicios?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Exibe histórico de respostas de um exercício
     */
    public function historico()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new Exception('Acesso negado');
            }
            
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

            $lista_id = $_GET['lista_id'] ?? '';
            $sessao_id = $_GET['sessao_id'] ?? '';

            if (empty($lista_id) || empty($sessao_id)) {
                throw new Exception('Parâmetros obrigatórios não fornecidos');
            }

            // Buscar dados da lista
            $lista = $this->db->fetch(
                "SELECT * FROM listas_exercicios WHERE id = :lista_id",
                ['lista_id' => $lista_id]
            );

            if (!$lista) {
                throw new Exception('Lista de exercícios não encontrada');
            }

            // Buscar sessão
            $sessao = $this->db->fetch(
                "SELECT * FROM exercicios_sessoes WHERE id = :sessao_id AND aluno_id = :aluno_id",
                ['sessao_id' => $sessao_id, 'aluno_id' => $aluno['id']]
            );

            if (!$sessao) {
                throw new Exception('Sessão não encontrada');
            }

            // Buscar questões da lista
            $questoes = $this->db->fetchAll(
                "SELECT * FROM questoes WHERE lista_id = :lista_id AND ativo = 1 ORDER BY ordem",
                ['lista_id' => $lista_id]
            );

            // Buscar respostas da sessão
            $respostas = $this->db->fetchAll(
                "SELECT * FROM exercicios_respostas WHERE sessao_id = :sessao_id ORDER BY exercicio_id",
                ['sessao_id' => $sessao_id]
            );

            // Organizar respostas por questão
            $respostas_por_questao = [];
            foreach ($respostas as $resposta) {
                $respostas_por_questao[$resposta['exercicio_id']] = $resposta;
            }

            // Buscar histórico completo desta lista
            $historico_completo = $this->db->fetchAll(
                "SELECT * FROM exercicios_historico 
                 WHERE aluno_id = :aluno_id AND lista_id = :lista_id 
                 ORDER BY data_execucao DESC",
                ['aluno_id' => $aluno['id'], 'lista_id' => $lista_id]
            );

            $data = [
                'user' => $user,
                'aluno' => $aluno,
                'lista' => $lista,
                'sessao' => $sessao,
                'questoes' => $questoes,
                'respostas' => $respostas_por_questao,
                'historico_completo' => $historico_completo,
                'csrf_token' => $this->generateCsrfToken()
            ];

            $this->viewWithLayout('student', 'student/exercises/exercicio-historico', $data);

        } catch (Exception $e) {
            $this->redirect('/exercicios?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Exibe resultado de uma execução
     */
    public function resultado()
    {
        try {
            $user = $this->authManager->getUser();
            
            if ($user['tipo'] !== 'aluno') {
                throw new Exception('Acesso negado');
            }
            
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

            $sessao_id = $_GET['id'] ?? '';
            
            if (empty($sessao_id)) {
                throw new Exception('ID da sessão é obrigatório');
            }

            // Buscar sessão
            $sessao = $this->db->fetch(
                "SELECT se.*, le.titulo, le.materia, le.nivel_dificuldade
                 FROM exercicios_sessoes se
                 LEFT JOIN listas_exercicios le ON se.lista_id = le.id
                 WHERE se.id = :sessao_id AND se.aluno_id = :aluno_id",
                ['sessao_id' => $sessao_id, 'aluno_id' => $aluno['id']]
            );

            if (!$sessao) {
                throw new Exception('Sessão não encontrada');
            }

            // Buscar questões e respostas
            $questoes = $this->db->fetchAll(
                "SELECT q.*, re.resposta as resposta_escolhida, re.tempo_resposta as tempo_gasto, re.is_correct as acertou
                 FROM questoes q
                 LEFT JOIN exercicios_respostas re ON q.id = re.exercicio_id AND re.sessao_id = :sessao_id
                 WHERE q.lista_id = :lista_id AND q.ativo = 1
                 ORDER BY q.ordem",
                ['lista_id' => $sessao['lista_id'], 'sessao_id' => $sessao_id]
            );

            // Calcular estatísticas
            $estatisticas = $this->db->fetch(
                "SELECT 
                    COUNT(*) as total_questoes,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as questoes_corretas,
                    SUM(tempo_resposta) as tempo_total_respostas
                 FROM exercicios_respostas 
                 WHERE sessao_id = :sessao_id",
                ['sessao_id' => $sessao_id]
            );

            $percentual_acerto = $estatisticas['total_questoes'] > 0 
                ? ($estatisticas['questoes_corretas'] / $estatisticas['total_questoes']) * 100 
                : 0;

            // Preparar dados para a view (compatibilidade com execucao_exercicios)
            $execucao = [
                'id' => $sessao['id'],
                'lista_id' => $sessao['lista_id'],
                'titulo' => $sessao['titulo'],
                'materia' => $sessao['materia'],
                'serie' => '', // não existe na tabela listas_exercicios
                'nivel_dificuldade' => $sessao['nivel_dificuldade'],
                'data_fim' => $sessao['finished_at'],
                'tempo_total' => $sessao['tempo_gasto'] ?? $estatisticas['tempo_total_respostas'] ?? 0,
                'questoes_corretas' => $estatisticas['questoes_corretas'],
                'questoes_total' => $estatisticas['total_questoes'],
                'percentual_acerto' => $percentual_acerto
            ];

            $data = [
                'title' => 'Resultado - ' . $sessao['titulo'],
                'user' => $user,
                'execucao' => $execucao,
                'questoes' => $questoes,
                'aluno' => $aluno,
                'csrf_token' => $this->generateCsrfToken()
            ];

            $this->viewWithLayout('student', 'student/exercises/exercicio-resultado', $data);

        } catch (Exception $e) {
            $this->redirect('/exercicios?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Atualiza estatísticas do aluno
     */
    private function atualizarEstatisticasAluno($aluno_id, $lista_id)
    {
        try {
            // Buscar dados da lista
            $lista = $this->db->fetch(
                "SELECT materia FROM listas_exercicios WHERE id = :lista_id",
                ['lista_id' => $lista_id]
            );

            if (!$lista) return;

            // Calcular estatísticas para esta matéria/série
            $stats = $this->db->fetch(
                "SELECT 
                    COUNT(*) as total_exercicios,
                    SUM(questoes_corretas) as total_acertos,
                    SUM(tempo_total) as total_tempo,
                    AVG(percentual_acerto) as percentual_medio
                 FROM exercicios_historico he
                 LEFT JOIN listas_exercicios le ON he.lista_id = le.id
                 WHERE he.aluno_id = :aluno_id 
                 AND le.materia = :materia",
                [
                    'aluno_id' => $aluno_id,
                    'materia' => $lista['materia']
                ]
            );

            // Verificar se já existe estatística para esta matéria/série
            $estatistica_existente = $this->db->fetch(
                "SELECT id FROM exercicios_estatisticas_alunos 
                 WHERE aluno_id = :aluno_id AND materia = :materia AND serie = :serie",
                [
                    'aluno_id' => $aluno_id,
                    'materia' => $lista['materia'],
                    'serie' => '', // String vazia conforme usado no INSERT
                ]
            );

            if ($estatistica_existente) {
                // Atualizar estatística existente
                $this->db->update(
                    "UPDATE exercicios_estatisticas_alunos 
                     SET total_exercicios = :total_exercicios,
                         total_acertos = :total_acertos,
                         total_tempo = :total_tempo,
                         percentual_medio = :percentual_medio,
                         ultima_atividade = NOW()
                     WHERE id = :id",
                    [
                        'total_exercicios' => $stats['total_exercicios'],
                        'total_acertos' => $stats['total_acertos'],
                        'total_tempo' => $stats['total_tempo'],
                        'percentual_medio' => $stats['percentual_medio'],
                        'id' => $estatistica_existente['id']
                    ]
                );
            } else {
                // Criar nova estatística
                $this->db->insert(
                    "INSERT INTO exercicios_estatisticas_alunos 
                     (aluno_id, materia, serie, total_exercicios, total_acertos, total_tempo, percentual_medio, ultima_atividade) 
                     VALUES (:aluno_id, :materia, :serie, :total_exercicios, :total_acertos, :total_tempo, :percentual_medio, NOW())",
                    [
                        'aluno_id' => $aluno_id,
                        'materia' => $lista['materia'],
                        'serie' => '',
                        'total_exercicios' => $stats['total_exercicios'],
                        'total_acertos' => $stats['total_acertos'],
                        'total_tempo' => $stats['total_tempo'],
                        'percentual_medio' => $stats['percentual_medio']
                    ]
                );
            }
        } catch (Exception $e) {
            error_log("Erro ao atualizar estatísticas do aluno: " . $e->getMessage());
        }
    }
}
}
