<?php
/**
 * EducaTudo - ProvaProfessorController
 * Gerencia provas individuais dos professores
 */

require_once __DIR__ . '/../../Models/Exams/TeacherExam.php';
require_once __DIR__ . '/../../Models/Exams/Exam.php';
require_once __DIR__ . '/../../Models/Exams/ExamBlock.php';
require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';

class TeacherExamController extends BaseController
{
    private $authManager;
    private $db;
    private $provaProfessorModel;
    private $provaModel;
    private $blocoModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        $this->provaProfessorModel = new TeacherExam();
        $this->provaModel = new Exam();
        $this->blocoModel = new ExamBlock();
        
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
    }
    
    /**
     * Lista provas do professor (acesso professor)
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }
        
        $provas = $this->provaProfessorModel->findByProfessor($user['id']);
        
        $data = [
            'title' => 'Minhas Provas - EducaTudo',
            'user' => $user,
            'provas' => $provas,
            'current_page' => 'provas'
        ];
        
        $this->viewWithLayout('professor', 'teacher/provas-professor/index', $data);
    }
    
    /**
     * Gerenciar provas de um bloco (acesso coordenação)
     */
    public function gerenciar($blocoId)
    {
        $user = $this->authManager->getUser();
        
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }
        
        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }

        if (($bloco['formato_evento'] ?? 'online_questoes') === 'lancamento_nota') {
            $data = $this->montarDadosGerenciarLancamentoNotas((int) $blocoId, $user, $bloco);
            $this->viewWithLayout('admin', 'admin/exams/blocks/manage', $data);
            return;
        }
        
        // Busca provas enviadas para aprovação OU retornadas ao professor (rascunho com observação)
        // IMPORTANTE: p.status é renomeado para status_original para evitar conflito
        $provas = $this->db->fetchAll(
            "SELECT p.id, p.titulo, p.descricao, p.materia_id, p.professor_id, 
                    p.data_inicio, p.data_fim, p.tempo_limite, p.valor_total,
                    p.ativo, p.created_at, p.updated_at, p.deleted_at,
                    p.status as status_original,
                    p.liberada,
                    COALESCE(p.data_envio, p.created_at) as data_envio,
                    p.observacao_coordenacao,
                    p.observacao_coordenacao_data,
                    pr.nome as professor_nome,
                    m.nome as materia_nome,
                    (SELECT COUNT(*) FROM provas_questoes pq WHERE pq.prova_id = p.id) as numero_questoes,
                    CASE 
                        WHEN p.status = 'aguardando_aprovacao' THEN 'enviada'
                        WHEN p.status = 'aprovada' THEN 'aprovada'
                        WHEN p.status = 'rascunho' AND p.observacao_coordenacao IS NOT NULL AND TRIM(p.observacao_coordenacao) != '' THEN 'retornada'
                        WHEN p.status = 'rascunho' OR p.status = 'agendada' THEN 'em_andamento'
                        WHEN p.status = 'reprovada' THEN 'reprovada'
                        ELSE 'em_andamento'
                    END as status_professor
             FROM provas p
             INNER JOIN provas_blocos_vinculo pbp ON p.id = pbp.prova_id
             LEFT JOIN professores pr ON p.professor_id = pr.id
             LEFT JOIN materias m ON p.materia_id = m.id
             WHERE pbp.bloco_id = :bloco_id
             AND p.deleted_at IS NULL
             AND (
                 p.status IN ('enviada', 'aguardando_aprovacao', 'aprovada', 'reprovada', 'pendente', 'agendada')
                 OR (p.status = 'rascunho' AND p.observacao_coordenacao IS NOT NULL AND TRIM(p.observacao_coordenacao) != '')
             )
             ORDER BY m.nome ASC, pr.nome ASC",
            ['bloco_id' => $blocoId]
        );
        
        
        // Contagem por status (atualizada para os novos status)
        $contagem = [
            'em_andamento' => 0,
            'enviada' => 0,
            'nao_enviada' => 0,
            'aprovada' => 0,
            'reprovada' => 0,
            'retornada' => 0,
            'nao_avaliada' => 0,
            'aprovado' => 0,
            'concluido' => 0
        ];
        
        // Conta provas por status usando status_original
        foreach ($provas as $prova) {
            $statusOriginal = $prova['status_original'] ?? '';
            $liberada = isset($prova['liberada']) ? (int)$prova['liberada'] : 0;
            $observacao = trim($prova['observacao_coordenacao'] ?? '');
            $ehRetornada = ($statusOriginal === 'rascunho' && $observacao !== '');
            
            if ($ehRetornada) {
                $contagem['retornada']++;
            } elseif ($statusOriginal === 'aguardando_aprovacao') {
                $contagem['enviada']++;
                $contagem['nao_avaliada']++;
            } elseif ($statusOriginal === 'aprovada') {
                $contagem['aprovada']++;
                if ($liberada == 1) {
                    // Verifica se há alunos fazendo ou se todos terminaram
                    $alunosFazendo = $this->db->fetch(
                        "SELECT COUNT(*) as total FROM provas_realizacoes 
                         WHERE prova_id = :prova_id 
                         AND status = 'iniciado'",
                        ['prova_id' => $prova['id']]
                    );
                    $totalAlunos = $this->db->fetch(
                        "SELECT COUNT(DISTINCT aluno_id) as total FROM provas_realizacoes 
                         WHERE prova_id = :prova_id",
                        ['prova_id' => $prova['id']]
                    );
                    $alunosFinalizados = $this->db->fetch(
                        "SELECT COUNT(DISTINCT aluno_id) as total FROM provas_realizacoes 
                         WHERE prova_id = :prova_id 
                         AND status = 'finalizado'",
                        ['prova_id' => $prova['id']]
                    );
                    
                    if ($alunosFazendo && $alunosFazendo['total'] > 0) {
                        $contagem['em_andamento']++;
                    } elseif ($totalAlunos && $alunosFinalizados && 
                              $totalAlunos['total'] > 0 && 
                              $totalAlunos['total'] == $alunosFinalizados['total']) {
                        $contagem['concluido']++;
                    } else {
                        $contagem['aprovado']++;
                    }
                } else {
                    $contagem['aprovado']++;
                }
            } elseif ($statusOriginal === 'reprovada') {
                $contagem['reprovada']++;
            } elseif ($statusOriginal === 'rascunho' || $statusOriginal === 'agendada') {
                $contagem['em_andamento']++;
            } else {
                $contagem['em_andamento']++;
            }
        }
        
        // Busca professores do bloco para contar não enviadas
        $professoresBloco = $this->db->fetchAll(
            "SELECT DISTINCT pbp.professor_id, pbp.materia_id
             FROM provas_blocos_professores pbp
             WHERE pbp.bloco_id = :bloco_id",
            ['bloco_id' => $blocoId]
        );
        
        // Cria mapa de provas criadas
        $provasCriadas = [];
        foreach ($provas as $prova) {
            $key = $prova['professor_id'] . '_' . $prova['materia_id'];
            $provasCriadas[$key] = true;
        }
        
        // Conta provas não enviadas (professores que ainda não criaram prova)
        foreach ($professoresBloco as $profBloco) {
            $key = $profBloco['professor_id'] . '_' . $profBloco['materia_id'];
            if (!isset($provasCriadas[$key])) {
                $contagem['nao_enviada']++;
            }
        }
        
        // Agrupa por matéria
        $provasPorMateria = [];
        foreach ($provas as $prova) {
            $materiaNome = $prova['materia_nome'] ?? 'Sem matéria';
            if (!isset($provasPorMateria[$materiaNome])) {
                $provasPorMateria[$materiaNome] = [];
            }
            // Adiciona campos necessários para a view
            // A query agora retorna 'p.status as status_original' explicitamente
            $statusOriginal = $prova['status_original'] ?? '';
            
            // ============================================
            // LÓGICA SIMPLIFICADA - APENAS 4 STATUS
            // ============================================
            // 1. "Não avaliada" - Professor enviou para aprovação (status = 'aguardando_aprovacao')
            // 2. "Aprovado" - Coordenação aprovou (status = 'aprovada')
            // 3. "Em Andamento" - Aluno fazendo a prova no momento (status = 'aprovada' + liberada + aluno iniciado)
            // 4. "Concluído" - Todos os alunos finalizaram (status = 'aprovada' + liberada + todos finalizados)
            // ============================================
            
            // Busca status DIRETAMENTE do banco para garantir dados atualizados
            $provaBanco = $this->db->fetch(
                "SELECT status, liberada FROM provas WHERE id = :id",
                ['id' => $prova['id']]
            );
            
            if (!$provaBanco) {
                $statusExibicao = 'nao_avaliada';
            } else {
                $statusBanco = $provaBanco['status'] ?? '';
                $liberadaBanco = (int)($provaBanco['liberada'] ?? 0);
                
                // STATUS 1: Não avaliada
                if ($statusBanco === 'aguardando_aprovacao') {
                    $statusExibicao = 'nao_avaliada';
                }
                // STATUS 2, 3 ou 4: Aprovada
                elseif ($statusBanco === 'aprovada') {
                    // Se está liberada, verifica se há alunos fazendo ou se todos terminaram
                    if ($liberadaBanco == 1) {
                        // Verifica alunos fazendo (status = 'iniciado')
                        $alunosFazendo = $this->db->fetch(
                            "SELECT COUNT(*) as total FROM provas_realizacoes 
                             WHERE prova_id = :prova_id AND status = 'iniciado'",
                            ['prova_id' => $prova['id']]
                        );
                        
                        // Verifica se todos finalizaram
                        $totalAlunos = $this->db->fetch(
                            "SELECT COUNT(DISTINCT aluno_id) as total FROM provas_realizacoes 
                             WHERE prova_id = :prova_id",
                            ['prova_id' => $prova['id']]
                        );
                        $alunosFinalizados = $this->db->fetch(
                            "SELECT COUNT(DISTINCT aluno_id) as total FROM provas_realizacoes 
                             WHERE prova_id = :prova_id AND status = 'finalizado'",
                            ['prova_id' => $prova['id']]
                        );
                        
                        $total = (int)($totalAlunos['total'] ?? 0);
                        $fazendo = (int)($alunosFazendo['total'] ?? 0);
                        $finalizados = (int)($alunosFinalizados['total'] ?? 0);
                        
                        // STATUS 3: Em Andamento (há alunos fazendo)
                        if ($fazendo > 0) {
                            $statusExibicao = 'em_andamento';
                        }
                        // STATUS 4: Concluído (todos finalizaram)
                        elseif ($total > 0 && $total === $finalizados) {
                            $statusExibicao = 'concluido';
                        }
                        // STATUS 2: Aprovado (liberada mas ninguém começou ainda)
                        else {
                            $statusExibicao = 'aprovado';
                        }
                    } else {
                        // STATUS 2: Aprovado (mas não liberada ainda)
                        $statusExibicao = 'aprovado';
                    }
                }
                // STATUS: Prova excluída (reprovada)
                elseif ($statusBanco === 'reprovada') {
                    $statusExibicao = 'reprovada';
                }
                // STATUS: Pendente ou agendada (vinculada ao bloco, aguardando professor enviar à coordenação)
                elseif ($statusBanco === 'pendente' || $statusBanco === 'agendada') {
                    $statusExibicao = 'pendente';
                }
                // Outros status (rascunho com observação = retornada; demais = não avaliada)
                else {
                    $obs = trim($prova['observacao_coordenacao'] ?? '');
                    $statusExibicao = ($statusBanco === 'rascunho' && $obs !== '') ? 'retornada' : 'nao_avaliada';
                }
            }
            
            $provaView = [
                'id' => $prova['id'],
                'prova_id' => $prova['id'],
                'professor_id' => $prova['professor_id'] ?? null,
                'materia_id' => $prova['materia_id'] ?? null,
                'professor_nome' => $prova['professor_nome'] ?? 'Sem professor',
                'materia_nome' => $prova['materia_nome'] ?? 'Sem matéria',
                'status' => $statusExibicao, // Status calculado (nao_avaliada, aprovado, em_andamento, concluido, retornada)
                'status_original' => $provaBanco['status'] ?? $statusOriginal, // Status real do banco
                'liberada' => isset($prova['liberada']) ? (int)$prova['liberada'] : 0,
                'numero_questoes' => $prova['numero_questoes'] ?? 0,
                'data_envio' => $prova['data_envio'] ?? null,
                'observacao_coordenacao' => $prova['observacao_coordenacao'] ?? null,
                'travada' => ($statusOriginal === 'aprovada' || $statusOriginal === 'aguardando_aprovacao') ? 1 : 0
            ];
            $provasPorMateria[$materiaNome][] = $provaView;
        }
        
        // Adiciona matérias sem provas criadas
        foreach ($professoresBloco as $profBloco) {
            $key = $profBloco['professor_id'] . '_' . $profBloco['materia_id'];
            if (!isset($provasCriadas[$key])) {
                $materia = $this->db->fetch(
                    "SELECT nome FROM materias WHERE id = :id",
                    ['id' => $profBloco['materia_id']]
                );
                $professor = $this->db->fetch(
                    "SELECT nome FROM professores WHERE id = :id",
                    ['id' => $profBloco['professor_id']]
                );
                
                $materiaNome = $materia['nome'] ?? 'Sem matéria';
                if (!isset($provasPorMateria[$materiaNome])) {
                    $provasPorMateria[$materiaNome] = [];
                }
                
                $provasPorMateria[$materiaNome][] = [
                    'id' => null,
                    'prova_id' => null,
                    'professor_id' => $profBloco['professor_id'],
                    'materia_id' => $profBloco['materia_id'],
                    'professor_nome' => $professor['nome'] ?? 'Sem professor',
                    'materia_nome' => $materiaNome,
                    'status' => 'nao_enviada',
                    'numero_questoes' => 0,
                    'data_envio' => null,
                    'travada' => 0
                ];
            }
        }
        
        // Todas as provas foram enviadas (não há "nao_enviada" ou "em_andamento")
        $todasProvasEnviadas = ($contagem['nao_enviada'] == 0 && $contagem['em_andamento'] == 0);
        
        // Todas as provas do bloco estão aprovadas (pela coordenação)
        $todasProvasAprovadas = false;
        if (!empty($provas)) {
            $todasProvasAprovadas = true;
            foreach ($provas as $prova) {
                $statusOriginal = $prova['status_original'] ?? '';
                if ($statusOriginal !== 'aprovada') {
                    $todasProvasAprovadas = false;
                    break;
                }
            }
        }
        
        $statusBloco = $bloco['status'] ?? 'aguardando';
        // Se bloco ainda está "aguardando" e todas as provas já estão aprovadas, atualiza o status do bloco
        if ($statusBloco === 'aguardando' && $todasProvasEnviadas && $todasProvasAprovadas) {
            $this->db->query(
                "UPDATE provas_blocos SET status = 'aprovado', ativo = 1 WHERE id = :bloco_id",
                ['bloco_id' => $blocoId]
            );
            $bloco['status'] = 'aprovado';
            $statusBloco = 'aprovado';
        }
        
        // Botão "Aprovação Final": exibir quando todas enviadas E (há pendente OU todas aprovadas e bloco ainda aguardando)
        $temAprovacaoPendente = false;
        foreach ($provas as $prova) {
            $statusOriginal = $prova['status_original'] ?? '';
            if ($statusOriginal === 'aguardando_aprovacao') {
                $temAprovacaoPendente = true;
                break;
            }
        }
        $mostrarBotaoAprovacaoFinal = $todasProvasEnviadas && ($temAprovacaoPendente || ($todasProvasAprovadas && $statusBloco === 'aguardando'));

        // Filtro por status (Enviadas, Não Enviadas, Retornado Professor, Excluído, Todas)
        $filterStatus = trim($_GET['status'] ?? '');
        $statusFilterMap = [
            'enviada' => 'nao_avaliada',   // exibido como "Enviada"
            'nao_enviada' => 'nao_enviada',
            'retornada' => 'retornada',     // Retornado Professor
            'excluido' => 'reprovada',      // Excluído = prova excluída
        ];
        if ($filterStatus !== '' && isset($statusFilterMap[$filterStatus])) {
            $statusExibicaoFiltro = $statusFilterMap[$filterStatus];
            foreach ($provasPorMateria as $materiaNome => $lista) {
                $provasPorMateria[$materiaNome] = array_values(array_filter($lista, function ($p) use ($statusExibicaoFiltro) {
                    return ($p['status'] ?? '') === $statusExibicaoFiltro;
                }));
            }
            // Remove matérias sem itens após o filtro
            $provasPorMateria = array_filter($provasPorMateria, function ($lista) {
                return count($lista) > 0;
            });
        }
        
        // Contagem de provas canceladas no bloco (botão Cancelados)
        $totalCanceladas = 0;
        try {
            $rowCanceladas = $this->db->fetch(
                "SELECT COUNT(*) AS c
                 FROM provas_realizacoes pr
                 INNER JOIN provas_blocos_vinculo pbp ON pbp.prova_id = pr.prova_id AND pbp.bloco_id = :bloco_id
                 INNER JOIN provas p ON p.id = pr.prova_id AND p.deleted_at IS NULL
                 WHERE pr.status = 'cancelada'",
                ['bloco_id' => (int) $blocoId]
            );
            $totalCanceladas = (int) ($rowCanceladas['c'] ?? 0);
        } catch (Exception $e) {
            error_log('gerenciar: contagem canceladas: ' . $e->getMessage());
        }

        $data = [
            'title' => 'Gerenciar Provas - EducaTudo',
            'user' => $user,
            'bloco' => $bloco,
            'provas' => $provas,
            'provasPorMateria' => $provasPorMateria,
            'contagem' => $contagem,
            'mostrarBotaoAprovacaoFinal' => $mostrarBotaoAprovacaoFinal,
            'status_filtro' => $filterStatus,
            'total_canceladas' => $totalCanceladas,
            'current_page' => 'provas_blocos'
        ];
        
        $this->viewWithLayout('admin', 'admin/exams/blocks/manage', $data);
    }
    
    /**
     * Lista provas disponíveis para vincular ao bloco (professor/matéria que ainda não têm prova no bloco).
     * GET admin/provas/blocos/{id}/provas-disponiveis?professor_id=&materia_id=
     */
    public function provasDisponiveisParaVincular($blocoId)
    {
        $user = $this->authManager->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->json(['error' => 'Bloco não encontrado'], 404);
            return;
        }
        $professorId = (int)($_GET['professor_id'] ?? 0);
        $materiaId = (int)($_GET['materia_id'] ?? 0);
        if ($professorId <= 0 || $materiaId <= 0) {
            $this->json(['error' => 'professor_id e materia_id são obrigatórios'], 400);
            return;
        }
        $provas = $this->blocoModel->getProvasDisponiveisParaVincular($blocoId, $professorId, $materiaId);
        $this->json(['provas' => $provas]);
    }
    
    /**
     * Vincula uma prova ao bloco (recuperação de provas desvinculadas).
     * POST admin/provas/blocos/{id}/vincular body: { prova_id: number }
     */
    public function vincularProva($blocoId)
    {
        $user = $this->authManager->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 403);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $provaId = isset($input['prova_id']) ? (int)$input['prova_id'] : 0;
        if ($provaId <= 0) {
            $this->json(['success' => false, 'error' => 'prova_id é obrigatório']);
            return;
        }
        $result = $this->blocoModel->vincularProva($blocoId, $provaId);
        if ($result['success']) {
            $this->json(['success' => true, 'message' => 'Prova vinculada ao bloco.']);
            return;
        }
        $this->json(['success' => false, 'error' => $result['error'] ?? 'Erro ao vincular prova'], 400);
    }
    
    /**
     * Troca a prova vinculada por outra (mesmo professor/matéria).
     * POST admin/provas/blocos/{id}/trocar-prova body: { prova_atual_id: number, nova_prova_id: number }
     */
    public function trocarProva($blocoId)
    {
        $user = $this->authManager->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->json(['success' => false, 'error' => 'Não autorizado'], 403);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $provaAtualId = isset($input['prova_atual_id']) ? (int)$input['prova_atual_id'] : 0;
        $novaProvaId = isset($input['nova_prova_id']) ? (int)$input['nova_prova_id'] : 0;
        if ($provaAtualId <= 0 || $novaProvaId <= 0) {
            $this->json(['success' => false, 'error' => 'prova_atual_id e nova_prova_id são obrigatórios']);
            return;
        }
        $result = $this->blocoModel->trocarProva($blocoId, $provaAtualId, $novaProvaId);
        if ($result['success']) {
            $this->json(['success' => true, 'message' => 'Prova trocada com sucesso.']);
            return;
        }
        $this->json(['success' => false, 'error' => $result['error'] ?? 'Erro ao trocar prova'], 400);
    }
    
    /**
     * Aprova uma prova
     */
    public function aprovar($id)
    {
        $user = $this->authManager->getUser();
        
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            $provaProfessor = $this->provaProfessorModel->findById($id);
            
            if (!$provaProfessor) {
                throw new Exception('Prova não encontrada');
            }
            
            if ($provaProfessor['status'] !== 'enviada') {
                throw new Exception('Apenas provas enviadas podem ser aprovadas');
            }
            
            $this->provaProfessorModel->updateStatus($id, 'aprovada');

            // EducaInclui: ao aprovar a prova do professor, pré-gera as versões adaptadas
            // dos alunos com máscara significativa para esta prova, para já entrarem na
            // fila de aprovação da coordenação (/admin/inclusao/versoes).
            $provaIdReal = (int) ($provaProfessor['prova_id'] ?? 0);
            $blocoIdReal = (int) ($provaProfessor['bloco_id'] ?? 0);
            error_log("[EducaInclui] aprovar() prova_professor_id={$id} prova_id={$provaIdReal} bloco_id={$blocoIdReal}");
            if ($provaIdReal > 0 && $blocoIdReal > 0) {
                $this->pregerarVersoesAdaptadasBloco($blocoIdReal, [$provaIdReal]);
            }

            $this->json([
                'success' => true,
                'message' => 'Prova aprovada com sucesso'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao aprovar prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Reprova uma prova
     */
    public function reprovar($id)
    {
        $user = $this->authManager->getUser();
        
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            $provaProfessor = $this->provaProfessorModel->findById($id);
            
            if (!$provaProfessor) {
                throw new Exception('Prova não encontrada');
            }
            
            if ($provaProfessor['status'] !== 'enviada') {
                throw new Exception('Apenas provas enviadas podem ser reprovadas');
            }
            
            $this->provaProfessorModel->updateStatus($id, 'reprovada', ['travada' => 1]);
            
            $this->json([
                'success' => true,
                'message' => 'Prova reprovada'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao reprovar prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Professor envia sua prova
     */
    public function enviar($id)
    {
        $user = $this->authManager->getUser();
        
        if ($user['tipo'] !== 'professor') {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            $provaProfessor = $this->provaProfessorModel->findById($id);
            
            if (!$provaProfessor) {
                throw new Exception('Prova não encontrada');
            }
            
            if ($provaProfessor['professor_id'] != $user['id']) {
                throw new Exception('Você não tem permissão para enviar esta prova');
            }
            
            if ($provaProfessor['status'] !== 'em_andamento') {
                throw new Exception('Apenas provas em andamento podem ser enviadas');
            }
            
            if ($provaProfessor['travada']) {
                throw new Exception('Esta prova está travada e não pode ser enviada');
            }
            
            // Verifica se tem prova criada
            if (empty($provaProfessor['prova_id'])) {
                throw new Exception('Crie a prova antes de enviar');
            }
            
            // Verifica se a prova tem questões
            $questoes = $this->provaModel->getQuestoes($provaProfessor['prova_id']);
            if (empty($questoes)) {
                throw new Exception('A prova deve ter pelo menos uma questão');
            }
            
            $this->provaProfessorModel->updateStatus($id, 'enviada', [
                'data_envio' => date('Y-m-d H:i:s'),
                'travada' => 1
            ]);
            
            $this->json([
                'success' => true,
                'message' => 'Prova enviada com sucesso! Aguardando aprovação da coordenação.'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao enviar prova: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Verifica prazos expirados (pode ser chamado por cron)
     */
    public function verificarPrazos()
    {
        try {
            $atualizadas = $this->provaProfessorModel->verificarPrazosExpirados();
            
            return [
                'success' => true,
                'message' => "{$atualizadas} prova(s) atualizada(s)",
                'atualizadas' => $atualizadas
            ];
        } catch (Exception $e) {
            error_log("Erro ao verificar prazos: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Visualiza bloco completo (todas as provas juntas)
     */
    public function visualizarCompleto($blocoId)
    {
        $user = $this->authManager->getUser();
        
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }
        
        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        
        // Busca todas as provas do bloco com questões (exclui provas excluídas/reprovadas e soft-deleted)
        $provas = $this->db->fetchAll(
            "SELECT p.*,
                    pr.nome as professor_nome,
                    m.nome as materia_nome,
                    pbp.ordem
             FROM provas_blocos_vinculo pbp
             INNER JOIN provas p ON pbp.prova_id = p.id
             LEFT JOIN professores pr ON p.professor_id = pr.id
             LEFT JOIN materias m ON p.materia_id = m.id
             WHERE pbp.bloco_id = :bloco_id
             AND p.deleted_at IS NULL
             AND (p.status IS NULL OR p.status != 'reprovada')
             ORDER BY pbp.ordem ASC, m.nome ASC",
            ['bloco_id' => $blocoId]
        );
        
        // Para cada prova, busca questões
        foreach ($provas as &$prova) {
            $questoes = $this->provaModel->getQuestoes($prova['id']);
            foreach ($questoes as &$questao) {
                if ($questao['tipo'] === 'multipla_escolha') {
                    $questao['alternativas'] = $this->provaModel->getAlternativas($questao['id']);
                }
            }
            $prova['questoes'] = $questoes;
        }
        
        $data = [
            'title' => 'Visualizar Bloco Completo - EducaTudo',
            'user' => $user,
            'bloco' => $bloco,
            'provas' => $provas,
            'current_page' => 'provas_blocos',
            'additional_css' => '<link rel="stylesheet" href="' . URL . '/public/static/css/mathlive-static.css">',
            'additional_js' => '<script src="' . URL . '/public/static/js/mathlive.min.js"></script><script>document.addEventListener("DOMContentLoaded",function(){if(typeof MathLive!=="undefined"&&MathLive.renderMathInDocument)MathLive.renderMathInDocument();});</script>',
        ];
        
        $this->viewWithLayout('admin', 'admin/exams/blocks/view-full', $data);
    }
    
    /**
     * Aprovação final do bloco (libera para alunos)
     */
    public function aprovarFinal($blocoId)
    {
        $user = $this->authManager->getUser();
        
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->json(['error' => 'Não autorizado'], 403);
            return;
        }
        
        try {
            $bloco = $this->blocoModel->findById($blocoId);
            if (!$bloco) {
                throw new Exception('Bloco não encontrado');
            }
            
            // Busca provas aprovadas do bloco; se não houver, usa todas as provas do bloco (ex.: bloco duplicado em rascunho)
            $provasAprovadas = $this->db->fetchAll(
                "SELECT p.id, p.status
                 FROM provas_blocos_vinculo pbp
                 INNER JOIN provas p ON pbp.prova_id = p.id
                 WHERE pbp.bloco_id = :bloco_id
                 AND p.deleted_at IS NULL
                 ORDER BY pbp.ordem ASC",
                ['bloco_id' => $blocoId]
            );
            
            if (empty($provasAprovadas)) {
                throw new Exception('Nenhuma prova encontrada no bloco.');
            }
            
            $provaIds = array_column($provasAprovadas, 'id');
            $temApenasRascunho = true;
            foreach ($provasAprovadas as $p) {
                if (($p['status'] ?? '') === 'aprovada') {
                    $temApenasRascunho = false;
                    break;
                }
            }
            // Se todas estão em rascunho (ex.: bloco duplicado), aprova e libera
            if ($temApenasRascunho) {
                $placeholders = str_repeat('?,', count($provaIds) - 1) . '?';
                $this->db->query(
                    "UPDATE provas SET status = 'aprovada' WHERE id IN ($placeholders)",
                    $provaIds
                );
            } else {
                // Só libera as que já estão aprovadas
                $provasAprovadas = $this->db->fetchAll(
                    "SELECT p.id FROM provas_blocos_vinculo pbp INNER JOIN provas p ON pbp.prova_id = p.id
                     WHERE pbp.bloco_id = :bloco_id AND p.status = 'aprovada' AND p.deleted_at IS NULL",
                    ['bloco_id' => $blocoId]
                );
                $provaIds = array_column($provasAprovadas, 'id');
                if (empty($provaIds)) {
                    throw new Exception('Nenhuma prova aprovada encontrada no bloco.');
                }
            }
            
            // Libera as provas (liberada = 1, ativo = 1)
            $placeholders = str_repeat('?,', count($provaIds) - 1) . '?';
            
            $this->db->query(
                "UPDATE provas 
                 SET liberada = 1, ativo = 1 
                 WHERE id IN ($placeholders)",
                $provaIds
            );
            
            // Marca bloco como aprovado (admin ainda precisa "Liberar" para os alunos)
            $this->db->query(
                "UPDATE provas_blocos 
                 SET status = 'aprovado', ativo = 1 
                 WHERE id = :bloco_id",
                ['bloco_id' => $blocoId]
            );

            // EducaInclui: ao aprovar o bloco, pré-gera as versões adaptadas dos alunos
            // com máscara significativa para todas as provas do bloco, para já entrarem
            // na fila de aprovação da coordenação (/admin/inclusao/versoes).
            $this->pregerarVersoesAdaptadasBloco((int) $blocoId, array_map('intval', $provaIds));

            $this->json([
                'success' => true,
                'message' => 'Bloco aprovado com sucesso! Libere o bloco para que os alunos possam realizar as provas na data e horário programados.'
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao aprovar bloco final: " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * EducaInclui — pré-gera versões adaptadas (clones) para alunos com máscara
     * significativa em todas as provas do bloco, no momento da aprovação final.
     * As versões caem na fila /admin/inclusao/versoes para aprovação da coordenação
     * antes de serem entregues. A entrega (Opção B) faz a versão rodar com tempo
     * próprio dentro do bloco. Idempotente (ensureDraft não regenera) e tolerante a
     * falhas para nunca quebrar a aprovação do bloco.
     *
     * @param list<int> $provaIds
     */
    private function pregerarVersoesAdaptadasBloco(int $blocoId, array $provaIds): void
    {
        $provaIds = array_values(array_unique(array_filter(array_map('intval', $provaIds), static fn($v) => $v > 0)));
        if ($blocoId <= 0 || $provaIds === []) {
            error_log("[EducaInclui] pregerarBloco ABORT bloco_id={$blocoId} provaIds=" . json_encode($provaIds));
            return;
        }
        try {
            require_once __DIR__ . '/../../Services/MascaraResolver.php';
            require_once __DIR__ . '/../../Services/AssessmentVersionGenerator.php';
            error_log("[EducaInclui] pregerarBloco INICIO bloco_id={$blocoId} provaIds=" . json_encode($provaIds));

            // Turmas do bloco (provas_blocos_turmas + turma_id direto do bloco).
            $turmaIds = [];
            $turmaRows = $this->db->fetchAll(
                "SELECT turma_id FROM provas_blocos_turmas WHERE bloco_id = :bloco_id",
                ['bloco_id' => $blocoId]
            );
            foreach ($turmaRows as $r) {
                if (!empty($r['turma_id'])) {
                    $turmaIds[] = (int) $r['turma_id'];
                }
            }
            $blocoRow = $this->db->fetch("SELECT turma_id FROM provas_blocos WHERE id = :id", ['id' => $blocoId]);
            if (!empty($blocoRow['turma_id'])) {
                $turmaIds[] = (int) $blocoRow['turma_id'];
            }
            $turmaIds = array_values(array_unique($turmaIds));
            $blocoParaTodas = $turmaIds === []; // sem turmas vinculadas = bloco aberto a todos
            error_log("[EducaInclui] pregerarBloco turmas_bloco=" . json_encode($turmaIds) . " paraTodas=" . ($blocoParaTodas ? '1' : '0'));

            // Máscaras ativas e vigentes (poucos alunos): itera por elas, não por turma.
            $masks = $this->db->fetchAll(
                "SELECT aluno_id FROM mascaras_alunos
                 WHERE status = 'ativa'
                   AND (data_inicio IS NULL OR data_inicio <= CURDATE())
                   AND (data_fim IS NULL OR data_fim >= CURDATE())"
            );
            error_log("[EducaInclui] pregerarBloco mascaras_ativas=" . count($masks));
            if (empty($masks)) {
                return;
            }

            $gen = new AssessmentVersionGenerator();
            foreach ($masks as $m) {
                $alunoId = (int) ($m['aluno_id'] ?? 0);
                if ($alunoId <= 0) {
                    continue;
                }
                $mask = MascaraResolver::resolveForAluno($alunoId);
                $req = !empty($mask['active']) && MascaraResolver::requiresClone($mask['rules']);
                error_log("[EducaInclui] pregerarBloco aluno_id={$alunoId} ativa=" . (!empty($mask['active']) ? '1' : '0') . " requiresClone=" . ($req ? '1' : '0'));
                if (!$req) {
                    continue;
                }
                if (!$blocoParaTodas) {
                    $row = $this->db->fetch("SELECT turma_id FROM alunos WHERE id = :id", ['id' => $alunoId]);
                    $turmaAluno = (int) ($row['turma_id'] ?? 0);
                    if ($turmaAluno <= 0 || !in_array($turmaAluno, $turmaIds, true)) {
                        error_log("[EducaInclui] pregerarBloco aluno_id={$alunoId} PULADO turma_aluno={$turmaAluno} nao esta nas turmas do bloco");
                        continue;
                    }
                }
                foreach ($provaIds as $pid) {
                    $ver = $gen->ensureDraft($pid, $alunoId, (int) $mask['mascara_id'], $mask['rules']);
                    error_log("[EducaInclui] pregerarBloco ensureDraft prova_id={$pid} aluno_id={$alunoId} resultado=" . ($ver ? ('version_id=' . ($ver['id'] ?? '?')) : 'null'));
                }
            }
        } catch (\Throwable $e) {
            error_log('pregerarVersoesAdaptadasBloco: ' . $e->getMessage());
        }
    }

    /**
     * Resultados do bloco (admin) — redireciona para o dashboard novo; mantém relatório de notas no lançamento manual.
     */
    public function resultadosBlocoAdmin($blocoId)
    {
        $user = $this->authManager->getUser();
        
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }
        
        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }

        if (($bloco['formato_evento'] ?? 'online_questoes') === 'lancamento_nota') {
            $notasModel = new ExamBlockManualGrade();
            $linhas = $notasModel->fetchTodasNotasBlocoAdmin((int) $blocoId);
            $this->viewWithLayout('admin', 'admin/exams/blocks/results_lancamento_notas', [
                'title' => 'Relatório de notas — ' . ($bloco['titulo'] ?? 'Evento'),
                'user' => $user,
                'bloco' => $bloco,
                'notas_linhas' => $linhas,
                'current_page' => 'provas_blocos',
            ]);
            return;
        }

        $this->redirect('/admin/provas/blocos/' . (int) $blocoId . '/resultados-novos');
    }

    /**
     * Dashboard pedagógico de resultados do bloco.
     */
    public function resultadosBlocoAdminNovo($blocoId)
    {
        $user = $this->authManager->getUser();

        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }

        $blocoId = (int) $blocoId;
        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }

        if (($bloco['formato_evento'] ?? 'online_questoes') === 'lancamento_nota') {
            $this->setFlashMessage('Use o relatório de notas para eventos de lançamento manual.', 'info');
            $this->redirect('/admin/provas/blocos/' . $blocoId . '/resultados');
            return;
        }

        require_once __DIR__ . '/../../Services/ExamBlockResultsDashboardService.php';
        $service = new \App\Services\ExamBlockResultsDashboardService($this->db);
        $dashboard = $service->build($blocoId, $bloco);

        $this->viewWithLayout('admin', 'admin/exams/blocks/results_dashboard', [
            'title' => 'Resultados da Avaliação — ' . ($bloco['titulo'] ?? ''),
            'user' => $user,
            'bloco' => $bloco,
            'dashboard' => $dashboard,
            'current_page' => 'provas_blocos',
        ]);
    }
    
    /**
     * Lista de provas canceladas no bloco: alunos que precisam de liberação para refazer.
     */
    public function canceladasBlocoAdmin($blocoId)
    {
        $user = $this->authManager->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }
        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        $rows = $this->db->fetchAll(
            "SELECT pr.prova_id, pr.aluno_id, p.titulo as prova_titulo, m.nome as materia_nome,
                    a.nome as aluno_nome, a.ra as aluno_ra,
                    (SELECT COUNT(*) FROM provas_respostas prr
                     WHERE prr.prova_id = pr.prova_id AND prr.aluno_id = pr.aluno_id) AS respostas_count
             FROM provas_realizacoes pr
             INNER JOIN provas_blocos_vinculo pbp ON pbp.prova_id = pr.prova_id AND pbp.bloco_id = :bloco_id
             INNER JOIN provas p ON p.id = pr.prova_id AND p.deleted_at IS NULL
             LEFT JOIN materias m ON p.materia_id = m.id
             INNER JOIN alunos a ON a.id = pr.aluno_id
             WHERE pr.status = 'cancelada'
             ORDER BY a.nome ASC, m.nome ASC, p.titulo ASC",
            ['bloco_id' => $blocoId]
        );
        // Histórico de validações de nota deste bloco
        $historicoValidacoes = $this->provaModel->getHistoricoValidacoesBloco((int) $blocoId);

        $flash = $this->getFlashMessage();
        $data = [
            'title' => 'Provas canceladas - EducaTudo',
            'user' => $user,
            'bloco' => $bloco,
            'canceladas' => $rows,
            'historico_validacoes' => $historicoValidacoes,
            'current_page' => 'provas_blocos',
            'flash_message' => $flash['message'] ?? null,
            'flash_type' => $flash['type'] ?? 'info',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('admin', 'admin/exams/blocks/canceladas', $data);
    }

    /**
     * Libera nova tentativa para todas as provas canceladas do bloco.
     */
    public function liberarTentativasBlocoAdmin($blocoId)
    {
        $user = $this->authManager->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }

        $blocoId = (int) $blocoId;
        $redirect = '/admin/provas/blocos/' . $blocoId . '/canceladas';

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect($redirect);
            return;
        }

        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }

        try {
            $liberadas = $this->provaModel->liberarNovaTentativaBloco($blocoId);
        } catch (Exception $e) {
            $this->setFlashMessage('Não foi possível liberar as tentativas. Tente novamente.', 'error');
            $this->redirect($redirect);
            return;
        }

        if ($liberadas > 0) {
            $msg = $liberadas === 1
                ? 'Nova tentativa liberada para 1 prova cancelada. O aluno poderá realizá-la novamente.'
                : 'Nova tentativa liberada para ' . $liberadas . ' provas canceladas. Os alunos poderão realizá-las novamente.';
            $this->setFlashMessage($msg, 'success');
        } else {
            $this->setFlashMessage('Não havia provas canceladas para liberar.', 'error');
        }
        $this->redirect($redirect);
    }
    
    /**
     * Página do resultado de um aluno no bloco: lista provas do bloco com link para resultado de cada prova
     */
    public function resultadoAlunoBloco($blocoId, $alunoId)
    {
        $user = $this->authManager->getUser();
        
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }
        
        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        
        $aluno = $this->db->fetch("SELECT id, nome, ra FROM alunos WHERE id = :id", ['id' => $alunoId]);
        if (!$aluno) {
            $this->setFlashMessage('Aluno não encontrado', 'error');
            $this->redirect('/admin/provas/blocos/' . $blocoId . '/resultados-novos');
            return;
        }
        
        $provas = $this->db->fetchAll(
            "SELECT p.id, p.titulo, p.materia_id, m.nome as materia_nome
             FROM provas_blocos_vinculo pbp
             INNER JOIN provas p ON pbp.prova_id = p.id
             LEFT JOIN materias m ON p.materia_id = m.id
             WHERE pbp.bloco_id = :bloco_id AND p.deleted_at IS NULL
             ORDER BY pbp.ordem ASC, m.nome ASC",
            ['bloco_id' => $blocoId]
        );
        
        $realizacoes = [];
        if (!empty($provas)) {
            $provaIds = array_column($provas, 'id');
            $placeholders = implode(',', array_fill(0, count($provaIds), '?'));
            $params = $provaIds;
            $params[] = $alunoId;
            $rows = $this->db->fetchAll(
                "SELECT prova_id, status, nota FROM provas_realizacoes WHERE prova_id IN ($placeholders) AND aluno_id = ?",
                $params
            );
            foreach ($rows as $r) {
                $realizacoes[$r['prova_id']] = $r;
            }
        }
        
        $flash = $this->getFlashMessage();
        $data = [
            'title' => 'Resultado do Aluno no Bloco - EducaTudo',
            'user' => $user,
            'bloco' => $bloco,
            'aluno' => $aluno,
            'provas' => $provas,
            'realizacoes' => $realizacoes,
            'csrf_token' => $this->generateCsrfToken(),
            'current_page' => 'provas_blocos',
            'flash_message' => $flash['message'] ?? null,
            'flash_type' => $flash['type'] ?? 'info'
        ];
        
        $this->viewWithLayout('admin', 'admin/exams/blocks/student-result', $data);
    }

    /**
     * Reabre o bloco para um aluno: reabre apenas a última matéria finalizada
     * (a que estava em andamento quando o tempo acabou), para o aluno continuar só o que faltava.
     * As demais matérias já finalizadas permanecem finalizadas.
     */
    public function reabrirBlocoAluno($blocoId, $alunoId)
    {
        $user = $this->authManager->getUser();

        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }

        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }

        $aluno = $this->db->fetch("SELECT id FROM alunos WHERE id = :id", ['id' => $alunoId]);
        if (!$aluno) {
            $this->setFlashMessage('Aluno não encontrado', 'error');
            $this->redirect('/admin/provas/blocos/' . (int)$blocoId . '/resultados-novos');
            return;
        }

        // Apenas a realização finalizada mais recente (a matéria em que estava quando o tempo acabou)
        $realizacao = $this->db->fetch(
            "SELECT pr.id
             FROM provas_realizacoes pr
             INNER JOIN provas_blocos_vinculo pbp ON pbp.prova_id = pr.prova_id AND pbp.bloco_id = :bloco_id
             WHERE pr.aluno_id = :aluno_id AND pr.status = 'finalizado'
             ORDER BY pr.finalizado_em DESC
             LIMIT 1",
            ['bloco_id' => (int)$blocoId, 'aluno_id' => (int)$alunoId]
        );

        if ($realizacao) {
            $this->db->query(
                "UPDATE provas_realizacoes SET status = 'iniciado', finalizado_em = NULL, nota = NULL WHERE id = :id",
                ['id' => $realizacao['id']]
            );
            $this->db->query(
                "UPDATE provas_blocos SET gabarito_liberado = 0 WHERE id = :id AND deleted_at IS NULL",
                ['id' => (int)$blocoId]
            );
            $this->setFlashMessage('Prova reaberta. O aluno pode continuar apenas a matéria que estava em andamento.', 'success');
        } else {
            $this->setFlashMessage('Nenhuma realização finalizada encontrada para reabrir.', 'info');
        }

        $this->redirect('/admin/provas/blocos/' . (int)$blocoId . '/resultados-novos');
    }

    /**
     * Libera manualmente o gabarito/resultado do bloco para os alunos.
     */
    public function liberarGabaritoBloco($blocoId)
    {
        $user = $this->authManager->getUser();

        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }

        $blocoId = (int)$blocoId;
        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }

        if (!empty($bloco['gabarito_liberado'])) {
            $this->setFlashMessage('O gabarito deste bloco já foi liberado.', 'info');
            $this->redirect('/admin/provas/blocos/' . $blocoId . '/resultados-novos');
            return;
        }

        $this->db->query(
            "UPDATE provas_blocos
             SET gabarito_liberado = 1
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $blocoId]
        );

        $this->setFlashMessage('Gabarito liberado com sucesso para os alunos.', 'success');
        $this->redirect('/admin/provas/blocos/' . $blocoId . '/resultados-novos');
    }

    /**
     * Matriz de Desempenho do Bloco – tabela cruzada alunos x questões por matéria
     */
    public function relatorioAcertosBloco($blocoId)
    {
        $user = $this->authManager->getUser();

        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }

        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco) {
            $this->setFlashMessage('Bloco não encontrado', 'error');
            $this->redirect('/admin/provas');
            return;
        }

        // Provas do bloco ordenadas
        $provas = $this->db->fetchAll(
            "SELECT p.id, p.titulo, p.materia_id, p.valor_total, m.nome as materia_nome, pbp.ordem
             FROM provas_blocos_vinculo pbp
             INNER JOIN provas p ON pbp.prova_id = p.id
             LEFT JOIN materias m ON p.materia_id = m.id
             WHERE pbp.bloco_id = :bloco_id AND p.deleted_at IS NULL
             ORDER BY pbp.ordem ASC, m.nome ASC",
            ['bloco_id' => $blocoId]
        );

        if (empty($provas)) {
            $data = [
                'title' => 'Matriz de Desempenho - EducaTudo',
                'user' => $user,
                'bloco' => $bloco,
                'materias' => [],
                'alunos' => [],
                'totalQuestoes' => 0,
                'current_page' => 'provas_blocos'
            ];
            $this->viewWithLayout('admin', 'admin/exams/blocks/relatorio-acertos', $data);
            return;
        }

        $provaIds = array_column($provas, 'id');
        $placeholders = implode(',', array_fill(0, count($provaIds), '?'));

        // Questões de cada prova (com alternativas)
        $materias = [];
        $todasQuestoes = [];
        $questoesAlternativas = [];
        $totalQuestoes = 0;

        foreach ($provas as $prova) {
            $questoes = $this->provaModel->getQuestoes($prova['id']);
            foreach ($questoes as &$q) {
                $q['alternativas'] = $this->provaModel->getAlternativas($q['id']);
                $questoesAlternativas[$q['id']] = $q;
                $todasQuestoes[] = $q['id'];
            }
            unset($q);
            $totalQuestoes += count($questoes);
            $materias[] = [
                'prova_id' => $prova['id'],
                'materia_nome' => $prova['materia_nome'] ?? $prova['titulo'],
                'valor_total' => $prova['valor_total'] ?? 0,
                'questoes' => $questoes
            ];
        }

        // Todos os alunos das turmas do bloco (cobre múltiplas fontes de vínculo)
        // 1) provas_blocos_turmas
        // 2) provas_turmas das provas vinculadas ao bloco
        // 3) provas.turma_id das provas vinculadas ao bloco
        $alunos = $this->db->fetchAll(
            "SELECT DISTINCT a.id, a.nome, a.ra, t.nome as turma_nome
             FROM alunos a
             INNER JOIN turmas t ON a.turma_id = t.id
             WHERE a.ativo = 1
             AND a.turma_id IN (
                SELECT turma_id FROM provas_blocos_turmas WHERE bloco_id = :bloco_id_1
                UNION
                SELECT pt.turma_id
                FROM provas_blocos_vinculo pbv
                INNER JOIN provas_turmas pt ON pt.prova_id = pbv.prova_id
                WHERE pbv.bloco_id = :bloco_id_2
                UNION
                SELECT p.turma_id
                FROM provas_blocos_vinculo pbv
                INNER JOIN provas p ON p.id = pbv.prova_id
                WHERE pbv.bloco_id = :bloco_id_3
                AND p.turma_id IS NOT NULL
             )
             ORDER BY a.nome ASC",
            [
                'bloco_id_1' => $blocoId,
                'bloco_id_2' => $blocoId,
                'bloco_id_3' => $blocoId
            ]
        );

        // Fallback 1: turma_id direto no bloco
        if (empty($alunos) && !empty($bloco['turma_id'])) {
            $alunos = $this->db->fetchAll(
                "SELECT a.id, a.nome, a.ra, t.nome as turma_nome
                 FROM alunos a
                 INNER JOIN turmas t ON a.turma_id = t.id
                 WHERE a.turma_id = :turma_id
                 AND a.ativo = 1
                 ORDER BY a.nome ASC",
                ['turma_id' => $bloco['turma_id']]
            );
        }

        // Fallback 2: alunos que realmente já fizeram prova do bloco
        if (empty($alunos)) {
            $alunos = $this->db->fetchAll(
                "SELECT DISTINCT a.id, a.nome, a.ra, t.nome as turma_nome
                 FROM provas_blocos_vinculo pbv
                 INNER JOIN provas_realizacoes pr ON pr.prova_id = pbv.prova_id
                 INNER JOIN alunos a ON a.id = pr.aluno_id
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE pbv.bloco_id = :bloco_id
                 ORDER BY a.nome ASC",
                ['bloco_id' => $blocoId]
            );
        }

        // Realizações em batch
        $realizacoesRaw = $this->db->fetchAll(
            "SELECT pr.aluno_id, pr.prova_id, pr.status, pr.nota
             FROM provas_realizacoes pr
             WHERE pr.prova_id IN ($placeholders)",
            $provaIds
        );
        $realizacoesMap = [];
        foreach ($realizacoesRaw as $r) {
            $realizacoesMap[$r['aluno_id'] . '_' . $r['prova_id']] = $r;
        }

        // Respostas em batch
        $respostasRaw = $this->db->fetchAll(
            "SELECT r.aluno_id, r.questao_id, r.correta, r.alternativa_id, r.prova_id
             FROM provas_respostas r
             WHERE r.prova_id IN ($placeholders)",
            $provaIds
        );
        $respostasMap = [];
        foreach ($respostasRaw as $r) {
            $respostasMap[$r['aluno_id'] . '_' . $r['questao_id']] = $r;
        }

        // Estatísticas por questão (para linha de resumo)
        $estatsPorQuestao = [];
        foreach ($todasQuestoes as $qid) {
            $estatsPorQuestao[$qid] = ['acertos' => 0, 'total' => 0];
        }

        // Montar dados por aluno
        $alunosData = [];
        foreach ($alunos as $aluno) {
            $totalAcertos = 0;
            $notaTotal = 0;
            $fezAlgumaProva = false;
            $respostasAluno = [];

            foreach ($materias as $mat) {
                $provaId = $mat['prova_id'];
                $keyReal = $aluno['id'] . '_' . $provaId;
                $realizacao = $realizacoesMap[$keyReal] ?? null;

                if ($realizacao && $realizacao['status'] === 'finalizado') {
                    $fezAlgumaProva = true;
                    $notaTotal += floatval($realizacao['nota'] ?? 0);
                }

                foreach ($mat['questoes'] as $q) {
                    $keyResp = $aluno['id'] . '_' . $q['id'];
                    $resp = $respostasMap[$keyResp] ?? null;

                    if ($resp) {
                        $acertou = (int)$resp['correta'];
                        $respostasAluno[$q['id']] = [
                            'correta' => $acertou,
                            'alternativa_id' => $resp['alternativa_id']
                        ];
                        $totalAcertos += $acertou;
                        $estatsPorQuestao[$q['id']]['acertos'] += $acertou;
                        $estatsPorQuestao[$q['id']]['total'] += 1;
                    } else {
                        $respostasAluno[$q['id']] = null;
                    }
                }
            }

            $obs = '';
            if (!$fezAlgumaProva) {
                $obs = 'FALTA';
            }

            $alunosData[] = [
                'id' => $aluno['id'],
                'nome' => $aluno['nome'],
                'ra' => $aluno['ra'],
                'turma_nome' => $aluno['turma_nome'],
                'respostas' => $respostasAluno,
                'total_acertos' => $totalAcertos,
                'nota' => $notaTotal,
                'obs' => $obs,
                'fez_prova' => $fezAlgumaProva
            ];
        }

        $data = [
            'title' => 'Matriz de Desempenho - EducaTudo',
            'user' => $user,
            'bloco' => $bloco,
            'materias' => $materias,
            'alunos' => $alunosData,
            'totalQuestoes' => $totalQuestoes,
            'estatsPorQuestao' => $estatsPorQuestao,
            'questoesAlternativas' => $questoesAlternativas,
            'totalAlunosAtivos' => count($alunos),
            'current_page' => 'provas_blocos'
        ];

        $this->viewWithLayout('admin', 'admin/exams/blocks/relatorio-acertos', $data);
    }

    /**
     * Coordenação: lista notas lançadas por professor/matéria (evento em modo lançamento).
     */
    public function notasLancadasAdmin($blocoId)
    {
        $user = $this->authManager->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }
        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco || (($bloco['formato_evento'] ?? '') !== 'lancamento_nota')) {
            $this->setFlashMessage('Evento não encontrado ou não é lançamento de notas.', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        $professorId = (int) ($_GET['professor_id'] ?? 0);
        $materiaId = (int) ($_GET['materia_id'] ?? 0);
        if ($professorId <= 0 || $materiaId <= 0) {
            $this->setFlashMessage('Professor e matéria são obrigatórios.', 'error');
            $this->redirect('/admin/provas/blocos/' . (int) $blocoId . '/gerenciar');
            return;
        }
        $ok = false;
        foreach ($bloco['professores'] ?? [] as $pe) {
            if ((int) ($pe['professor_id'] ?? 0) === $professorId && (int) ($pe['materia_id'] ?? 0) === $materiaId) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            $this->setFlashMessage('Combinação professor/matéria inválida para este evento.', 'error');
            $this->redirect('/admin/provas/blocos/' . (int) $blocoId . '/gerenciar');
            return;
        }
        $notasModel = new ExamBlockManualGrade();
        $linhas = $notasModel->fetchNotasDetalheAdmin((int) $blocoId, $professorId, $materiaId);
        $profNome = $this->db->fetch('SELECT nome FROM professores WHERE id = :id', ['id' => $professorId]);
        $matNome = $this->db->fetch('SELECT nome FROM materias WHERE id = :id', ['id' => $materiaId]);

        $this->viewWithLayout('admin', 'admin/exams/blocks/manage_lancamento_notas_ver', [
            'title' => 'Notas lançadas — ' . ($bloco['titulo'] ?? 'Evento'),
            'user' => $user,
            'bloco' => $bloco,
            'professor_id' => $professorId,
            'materia_id' => $materiaId,
            'professor_nome' => $profNome['nome'] ?? '',
            'materia_nome' => $matNome['nome'] ?? '',
            'linhas' => $linhas,
            'current_page' => 'provas_blocos',
        ]);
    }

    /**
     * Coordenação: copia notas de outro evento interno para o evento bimestral atual.
     * A combinação professor/matéria deve existir nos dois eventos.
     */
    public function importarNotasInternas($blocoId)
    {
        $user = $this->authManager->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'], true)) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }

        $blocoId = (int) $blocoId;
        $retornarLancamento = !empty($_POST['retornar_lancamento']);
        $materiaIdFiltroRetorno = max(0, (int) ($_POST['materia_id_filtro'] ?? 0));
        $redirect = $retornarLancamento
            ? '/admin/provas/blocos/' . $blocoId . '/lancar-notas-coordenacao?materia_id=' . $materiaIdFiltroRetorno
            : '/admin/provas/blocos/' . $blocoId . '/gerenciar';
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página e tente novamente.', 'error');
            $this->redirect($redirect);
            return;
        }

        $destino = $this->blocoModel->findById($blocoId);
        if (!$destino || (($destino['formato_evento'] ?? '') !== 'lancamento_nota')) {
            $this->setFlashMessage('O evento de destino não aceita lançamento de notas internas.', 'error');
            $this->redirect('/admin/provas');
            return;
        }

        $fonteId = (int) ($_POST['fonte_bloco_id'] ?? 0);
        $professorId = (int) ($_POST['fonte_professor_id'] ?? 0);
        $materiaId = (int) ($_POST['fonte_materia_id'] ?? 0);
        $sobrescrever = !empty($_POST['sobrescrever']);
        if ($fonteId <= 0 || $fonteId === $blocoId || $professorId <= 0 || $materiaId <= 0) {
            $this->setFlashMessage('Selecione um evento e um professor/matéria válidos.', 'error');
            $this->redirect($redirect);
            return;
        }

        $fonte = $this->blocoModel->findById($fonteId);
        if (!$fonte || (($fonte['formato_evento'] ?? '') !== 'lancamento_nota')) {
            $this->setFlashMessage('O evento de origem não está disponível para importação.', 'error');
            $this->redirect($redirect);
            return;
        }

        $origemValida = false;
        foreach ($fonte['professores'] ?? [] as $combo) {
            if ((int) ($combo['professor_id'] ?? 0) === $professorId
                && (int) ($combo['materia_id'] ?? 0) === $materiaId) {
                $origemValida = true;
                break;
            }
        }

        $comboDestino = null;
        foreach ($this->combosLancamentoNotaBloco($destino) as $combo) {
            if ((int) $combo['professor_id'] === $professorId && (int) $combo['materia_id'] === $materiaId) {
                $comboDestino = $combo;
                break;
            }
        }
        if (!$origemValida || $comboDestino === null) {
            $this->setFlashMessage('Professor e matéria precisam estar vinculados tanto ao evento de origem quanto ao de destino.', 'error');
            $this->redirect($redirect);
            return;
        }

        $notasModel = new ExamBlockManualGrade();
        $notasOrigem = $notasModel->fetchNotasParaImportacao($fonteId, $professorId, $materiaId);
        if (empty($notasOrigem)) {
            $this->setFlashMessage('Não há notas preenchidas nessa origem.', 'warning');
            $this->redirect($redirect);
            return;
        }

        // O turma_id do destino pode ser diferente do registrado na origem; o aluno é a chave de encontro.
        $alunosDestino = $this->alunosAtivosPorTurmas($comboDestino['turma_ids']);
        $turmaDestinoPorAluno = [];
        foreach ($alunosDestino as $aluno) {
            $aid = (int) ($aluno['id'] ?? 0);
            $tid = (int) ($aluno['turma_id'] ?? 0);
            if ($aid > 0 && $tid > 0 && !isset($turmaDestinoPorAluno[$aid])) {
                $turmaDestinoPorAluno[$aid] = $tid;
            }
        }

        $existentes = $notasModel->fetchMap($blocoId, $professorId, $materiaId);
        $linhas = [];
        $ignoradasForaTurma = 0;
        $preservadas = 0;
        foreach ($notasOrigem as $notaOrigem) {
            $aid = (int) ($notaOrigem['aluno_id'] ?? 0);
            if ($aid <= 0 || !isset($turmaDestinoPorAluno[$aid])) {
                $ignoradasForaTurma++;
                continue;
            }
            $tid = $turmaDestinoPorAluno[$aid];
            $existente = $existentes[$tid . '_' . $aid]['nota'] ?? null;
            if (!$sobrescrever && $existente !== null && $existente !== '') {
                $preservadas++;
                continue;
            }
            $linhas[] = [
                'turma_id' => $tid,
                'aluno_id' => $aid,
                'nota' => (float) $notaOrigem['nota'],
                'observacao' => (string) ($notaOrigem['observacao'] ?? ''),
            ];
        }

        if (!empty($linhas)) {
            $this->db->beginTransaction();
            try {
                $notasModel->upsertLinhas($blocoId, $professorId, $materiaId, $linhas);
                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollback();
                }
                error_log('importarNotasInternas: ' . $e->getMessage());
                $this->setFlashMessage('Não foi possível importar as notas. Nenhuma alteração foi concluída.', 'error');
                $this->redirect($redirect);
                return;
            }
        }

        $partes = [count($linhas) . ' nota(s) importada(s)'];
        if ($preservadas > 0) {
            $partes[] = $preservadas . ' nota(s) existente(s) preservada(s)';
        }
        if ($ignoradasForaTurma > 0) {
            $partes[] = $ignoradasForaTurma . ' aluno(s) fora das turmas do destino ignorado(s)';
        }
        $this->setFlashMessage(implode('; ', $partes) . '.', empty($linhas) ? 'warning' : 'success');
        $this->redirect($redirect);
    }

    /**
     * Coordenação: formulário para lançar notas diretamente no evento (matéria específica ou todas).
     */
    public function lancarNotasCoordenacao($blocoId)
    {
        $user = $this->authManager->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }

        $blocoId = (int) $blocoId;
        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco || (($bloco['formato_evento'] ?? '') !== 'lancamento_nota')) {
            $this->setFlashMessage('Evento não encontrado ou não está em lançamento de notas.', 'error');
            $this->redirect('/admin/provas');
            return;
        }

        if (($bloco['configuracao_nota'] ?? '') !== 'coordenacao_calcula') {
            $this->setFlashMessage('Este evento está configurado para lançamento pelo professor.', 'error');
            $this->redirect('/admin/provas/blocos/' . $blocoId . '/gerenciar');
            return;
        }

        $notaUnicaTodasMaterias = !empty($bloco['nota_unica_todas_materias']);
        $materiaIdFiltro = (int) ($_GET['materia_id'] ?? 0);
        if ($notaUnicaTodasMaterias) {
            $materiaIdFiltro = 0;
        }
        $combos = $this->combosLancamentoNotaBloco($bloco, $materiaIdFiltro);

        $materiasFiltro = [];
        foreach ($bloco['professores'] ?? [] as $pe) {
            $mid = (int) ($pe['materia_id'] ?? 0);
            if ($mid <= 0 || isset($materiasFiltro[$mid])) {
                continue;
            }
            $materiasFiltro[$mid] = (string) ($pe['materia_nome'] ?? ('Matéria #' . $mid));
        }
        asort($materiasFiltro);

        require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';
        $notasModel = new ExamBlockManualGrade();
        $existentes = $notasModel->fetchTodasNotasBlocoAdmin($blocoId);
        $mapExistente = [];
        foreach ($existentes as $ln) {
            $k = (int) ($ln['professor_id'] ?? 0) . '_'
                . (int) ($ln['materia_id'] ?? 0) . '_'
                . (int) ($ln['turma_id'] ?? 0) . '_'
                . (int) ($ln['aluno_id'] ?? 0);
            $mapExistente[$k] = [
                'nota' => $ln['nota'],
                'observacao' => (string) ($ln['observacao'] ?? ''),
            ];
        }

        $linhas = [];
        foreach ($combos as $combo) {
            $alunos = $this->alunosAtivosPorTurmas($combo['turma_ids']);
            foreach ($alunos as $al) {
                $tid = (int) ($al['turma_id'] ?? 0);
                $aid = (int) ($al['id'] ?? 0);
                if ($tid <= 0 || $aid <= 0) {
                    continue;
                }
                $k = $combo['professor_id'] . '_' . $combo['materia_id'] . '_' . $tid . '_' . $aid;
                $ant = $mapExistente[$k] ?? ['nota' => null, 'observacao' => ''];
                $linhas[] = [
                    'professor_id' => $combo['professor_id'],
                    'professor_nome' => $combo['professor_nome'],
                    'materia_id' => $combo['materia_id'],
                    'materia_nome' => $combo['materia_nome'],
                    'turma_id' => $tid,
                    'turma_nome' => (string) ($al['turma_nome'] ?? ''),
                    'aluno_id' => $aid,
                    'aluno_nome' => (string) ($al['nome'] ?? ''),
                    'nota' => $ant['nota'],
                    'observacao' => $ant['observacao'],
                ];
            }
        }

        usort($linhas, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['materia_nome'] ?? ''), (string) ($b['materia_nome'] ?? ''));
            if ($cmp !== 0) return $cmp;
            $cmp = strcmp((string) ($a['professor_nome'] ?? ''), (string) ($b['professor_nome'] ?? ''));
            if ($cmp !== 0) return $cmp;
            $cmp = strcmp((string) ($a['turma_nome'] ?? ''), (string) ($b['turma_nome'] ?? ''));
            if ($cmp !== 0) return $cmp;
            return strcmp((string) ($a['aluno_nome'] ?? ''), (string) ($b['aluno_nome'] ?? ''));
        });

        if ($notaUnicaTodasMaterias) {
            $linhasUnicas = [];
            foreach ($linhas as $ln) {
                $tid = (int) ($ln['turma_id'] ?? 0);
                $aid = (int) ($ln['aluno_id'] ?? 0);
                if ($tid <= 0 || $aid <= 0) {
                    continue;
                }
                $key = $tid . '_' . $aid;
                if (!isset($linhasUnicas[$key])) {
                    $linhasUnicas[$key] = $ln;
                    continue;
                }
                if (($linhasUnicas[$key]['nota'] === null || $linhasUnicas[$key]['nota'] === '')
                    && ($ln['nota'] !== null && $ln['nota'] !== '')) {
                    $linhasUnicas[$key]['nota'] = $ln['nota'];
                }
                if (trim((string) ($linhasUnicas[$key]['observacao'] ?? '')) === ''
                    && trim((string) ($ln['observacao'] ?? '')) !== '') {
                    $linhasUnicas[$key]['observacao'] = (string) $ln['observacao'];
                }
            }
            $linhas = array_values($linhasUnicas);
            usort($linhas, static function (array $a, array $b): int {
                $cmp = strcmp((string) ($a['turma_nome'] ?? ''), (string) ($b['turma_nome'] ?? ''));
                if ($cmp !== 0) return $cmp;
                return strcmp((string) ($a['aluno_nome'] ?? ''), (string) ($b['aluno_nome'] ?? ''));
            });
        }

        $combosDestinoImportacao = [];
        foreach ($combos as $combo) {
            $pidImport = (int) ($combo['professor_id'] ?? 0);
            $midImport = (int) ($combo['materia_id'] ?? 0);
            if ($pidImport > 0 && $midImport > 0) {
                $combosDestinoImportacao[$pidImport . '_' . $midImport] = true;
            }
        }
        $fontesImportacao = array_values(array_filter(
            $notasModel->fetchFontesImportacao($blocoId),
            static function (array $fonte) use ($combosDestinoImportacao): bool {
                $key = (int) ($fonte['professor_id'] ?? 0) . '_' . (int) ($fonte['materia_id'] ?? 0);
                return !empty($combosDestinoImportacao[$key]);
            }
        ));

        $this->viewWithLayout('admin', 'admin/exams/blocks/lancar_notas_coordenacao', [
            'title' => 'Lançar notas (coordenação) — ' . ($bloco['titulo'] ?? 'Evento'),
            'user' => $user,
            'bloco' => $bloco,
            'linhas' => $linhas,
            'materias_filtro' => $materiasFiltro,
            'materia_id_filtro' => $materiaIdFiltro,
            'fontes_importacao_notas' => $fontesImportacao,
            'csrf_token' => $this->generateCsrfToken(),
            'flash' => $this->getFlashMessage(),
            'current_page' => 'provas_blocos',
        ]);
    }

    /**
     * Coordenação: salva notas lançadas diretamente no evento.
     */
    public function lancarNotasCoordenacaoSalvar($blocoId)
    {
        $user = $this->authManager->getUser();
        if (!in_array($user['tipo'], ['admin', 'admin_escola'])) {
            $this->redirectToCorrectDashboard($user['tipo']);
            return;
        }

        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Recarregue a página.', 'error');
            $this->redirect('/admin/provas');
            return;
        }

        $blocoId = (int) $blocoId;
        $bloco = $this->blocoModel->findById($blocoId);
        if (!$bloco || (($bloco['formato_evento'] ?? '') !== 'lancamento_nota')) {
            $this->setFlashMessage('Evento inválido.', 'error');
            $this->redirect('/admin/provas');
            return;
        }
        if (($bloco['configuracao_nota'] ?? '') !== 'coordenacao_calcula') {
            $this->setFlashMessage('Este evento está configurado para lançamento pelo professor.', 'error');
            $this->redirect('/admin/provas/blocos/' . $blocoId . '/gerenciar');
            return;
        }

        $notaUnicaTodasMaterias = !empty($bloco['nota_unica_todas_materias']);
        $materiaIdFiltro = (int) ($_POST['materia_id_filtro'] ?? 0);
        if ($notaUnicaTodasMaterias) {
            $materiaIdFiltro = 0;
        }
        $combos = $this->combosLancamentoNotaBloco($bloco, $materiaIdFiltro);

        require_once __DIR__ . '/../../Models/Exams/ExamBlockManualGrade.php';
        $notasModel = new ExamBlockManualGrade();
        if (!$notasModel->tableExists()) {
            $this->setFlashMessage('Tabela de notas ainda não existe. Execute a migração SQL no banco.', 'error');
            $this->redirect('/admin/provas/blocos/' . $blocoId . '/lancar-notas-coordenacao?materia_id=' . $materiaIdFiltro);
            return;
        }

        $notasPost = $_POST['notas'] ?? [];
        $obsPost = $_POST['observacoes'] ?? [];
        if (!is_array($notasPost)) {
            $notasPost = [];
        }
        if (!is_array($obsPost)) {
            $obsPost = [];
        }

        if ($notaUnicaTodasMaterias) {
            // Captura uma nota base por aluno (informada em qualquer matéria) e replica para todas.
            $mapBaseAluno = [];
            foreach ($combos as $combo) {
                $pid = (int) ($combo['professor_id'] ?? 0);
                $mid = (int) ($combo['materia_id'] ?? 0);
                if ($pid <= 0 || $mid <= 0) {
                    continue;
                }
                $alunos = $this->alunosAtivosPorTurmas($combo['turma_ids']);
                foreach ($alunos as $al) {
                    $tid = (int) ($al['turma_id'] ?? 0);
                    $aid = (int) ($al['id'] ?? 0);
                    if ($tid <= 0 || $aid <= 0) {
                        continue;
                    }
                    $valorRaw = $notasPost[$pid][$mid][$tid][$aid] ?? '';
                    $obs = isset($obsPost[$pid][$mid][$tid][$aid]) ? (string) $obsPost[$pid][$mid][$tid][$aid] : '';
                    $valorStr = is_string($valorRaw) ? trim(str_replace(',', '.', $valorRaw)) : '';
                    if ($valorStr === '' && trim($obs) === '') {
                        continue;
                    }
                    $notaVal = null;
                    if ($valorStr !== '') {
                        if (!is_numeric($valorStr)) {
                            $this->setFlashMessage('Nota inválida para um ou mais alunos.', 'error');
                            $this->redirect('/admin/provas/blocos/' . $blocoId . '/lancar-notas-coordenacao?materia_id=' . $materiaIdFiltro);
                            return;
                        }
                        $nf = (float) $valorStr;
                        if ($nf < 0 || $nf > 10) {
                            $this->setFlashMessage('Notas devem estar entre 0 e 10.', 'error');
                            $this->redirect('/admin/provas/blocos/' . $blocoId . '/lancar-notas-coordenacao?materia_id=' . $materiaIdFiltro);
                            return;
                        }
                        $notaVal = round($nf, 2);
                    }
                    $mapBaseAluno[$aid] = [
                        'nota' => $notaVal,
                        'observacao' => substr($obs, 0, 500),
                    ];
                }
            }

            foreach ($combos as $combo) {
                $pid = (int) ($combo['professor_id'] ?? 0);
                $mid = (int) ($combo['materia_id'] ?? 0);
                if ($pid <= 0 || $mid <= 0) {
                    continue;
                }
                $alunos = $this->alunosAtivosPorTurmas($combo['turma_ids']);
                $linhas = [];
                foreach ($alunos as $al) {
                    $tid = (int) ($al['turma_id'] ?? 0);
                    $aid = (int) ($al['id'] ?? 0);
                    if ($tid <= 0 || $aid <= 0 || !array_key_exists($aid, $mapBaseAluno)) {
                        continue;
                    }
                    $linhas[] = [
                        'turma_id' => $tid,
                        'aluno_id' => $aid,
                        'nota' => $mapBaseAluno[$aid]['nota'],
                        'observacao' => $mapBaseAluno[$aid]['observacao'],
                    ];
                }
                $notasModel->upsertLinhas($blocoId, $pid, $mid, $linhas);
            }

            $this->setFlashMessage('Notas salvas e replicadas para todas as matérias do evento.', 'success');
            $this->redirect('/admin/provas/blocos/' . $blocoId . '/lancar-notas-coordenacao?materia_id=' . $materiaIdFiltro);
            return;
        }

        foreach ($combos as $combo) {
            $pid = (int) ($combo['professor_id'] ?? 0);
            $mid = (int) ($combo['materia_id'] ?? 0);
            if ($pid <= 0 || $mid <= 0) {
                continue;
            }
            $alunos = $this->alunosAtivosPorTurmas($combo['turma_ids']);
            $linhas = [];
            foreach ($alunos as $al) {
                $tid = (int) ($al['turma_id'] ?? 0);
                $aid = (int) ($al['id'] ?? 0);
                if ($tid <= 0 || $aid <= 0) {
                    continue;
                }
                $valorRaw = $notasPost[$pid][$mid][$tid][$aid] ?? '';
                $obs = isset($obsPost[$pid][$mid][$tid][$aid]) ? (string) $obsPost[$pid][$mid][$tid][$aid] : '';
                $valorStr = is_string($valorRaw) ? trim(str_replace(',', '.', $valorRaw)) : '';
                $notaVal = null;
                if ($valorStr !== '') {
                    if (!is_numeric($valorStr)) {
                        $this->setFlashMessage('Nota inválida para um ou mais alunos.', 'error');
                        $this->redirect('/admin/provas/blocos/' . $blocoId . '/lancar-notas-coordenacao?materia_id=' . $materiaIdFiltro);
                        return;
                    }
                    $nf = (float) $valorStr;
                    if ($nf < 0 || $nf > 10) {
                        $this->setFlashMessage('Notas devem estar entre 0 e 10.', 'error');
                        $this->redirect('/admin/provas/blocos/' . $blocoId . '/lancar-notas-coordenacao?materia_id=' . $materiaIdFiltro);
                        return;
                    }
                    $notaVal = round($nf, 2);
                }
                $linhas[] = [
                    'turma_id' => $tid,
                    'aluno_id' => $aid,
                    'nota' => $notaVal,
                    'observacao' => substr($obs, 0, 500),
                ];
            }
            $notasModel->upsertLinhas($blocoId, $pid, $mid, $linhas);
        }

        $this->setFlashMessage('Notas salvas com sucesso.', 'success');
        $this->redirect('/admin/provas/blocos/' . $blocoId . '/lancar-notas-coordenacao?materia_id=' . $materiaIdFiltro);
    }

    /**
     * @return list<array{professor_id:int,professor_nome:string,materia_id:int,materia_nome:string,turma_ids:array<int,int>}>
     */
    private function combosLancamentoNotaBloco(array $bloco, int $materiaIdFiltro = 0): array
    {
        $combos = [];
        foreach ($bloco['professores'] ?? [] as $pe) {
            $pid = (int) ($pe['professor_id'] ?? 0);
            $mid = (int) ($pe['materia_id'] ?? 0);
            if ($pid <= 0 || $mid <= 0) {
                continue;
            }
            if ($materiaIdFiltro > 0 && $mid !== $materiaIdFiltro) {
                continue;
            }
            $turmaIds = [];
            foreach ($pe['turmas'] ?? [] as $t) {
                $tid = is_array($t) ? (int) ($t['id'] ?? 0) : (int) $t;
                if ($tid > 0) {
                    $turmaIds[$tid] = $tid;
                }
            }
            if (empty($turmaIds)) {
                continue;
            }
            $combos[] = [
                'professor_id' => $pid,
                'professor_nome' => (string) ($pe['professor_nome'] ?? ''),
                'materia_id' => $mid,
                'materia_nome' => (string) ($pe['materia_nome'] ?? ''),
                'turma_ids' => array_values($turmaIds),
            ];
        }
        return $combos;
    }

    /**
     * @param array<int, int> $turmaIds
     * @return list<array<string,mixed>>
     */
    private function alunosAtivosPorTurmas(array $turmaIds): array
    {
        $turmaIds = array_values(array_filter(array_map('intval', $turmaIds), static function (int $id): bool {
            return $id > 0;
        }));
        if (empty($turmaIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));

        $temMatricula = false;
        try {
            $temMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'") !== false;
        } catch (\Throwable $e) {
            $temMatricula = false;
        }

        if ($temMatricula) {
            // Inclui alunos com turma principal E alunos vinculados por matrícula
            // (turmas extra/paralelas). O turma_id retornado é sempre a turma do
            // combo (t.id), não a turma principal do cadastro do aluno.
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT a.id, a.nome, t.id AS turma_id, t.nome AS turma_nome
                 FROM turmas t
                 INNER JOIN alunos a ON (
                     a.turma_id = t.id
                     OR EXISTS (
                         SELECT 1 FROM matricula m
                         WHERE m.aluno_id = a.id
                           AND m.turma_id = t.id
                           AND m.status = 'ativa'
                           AND m.data_saida IS NULL
                     )
                 )
                 WHERE t.id IN ($placeholders)
                   AND (a.ativo = 1 OR a.ativo IS NULL)
                 ORDER BY t.nome ASC, a.nome ASC",
                $turmaIds
            );
            return is_array($rows) ? $rows : [];
        }

        $rows = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.turma_id, t.nome AS turma_nome
             FROM alunos a
             INNER JOIN turmas t ON t.id = a.turma_id
             WHERE a.turma_id IN ($placeholders)
               AND (a.ativo = 1 OR a.ativo IS NULL)
             ORDER BY t.nome ASC, a.nome ASC",
            $turmaIds
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Monta dados da tela "Gerenciar" para evento em lançamento de notas (sem fluxo de prova/questões).
     *
     * @return array<string, mixed>
     */
    private function montarDadosGerenciarLancamentoNotas(int $blocoId, array $user, array $bloco): array
    {
        $notasModel = new ExamBlockManualGrade();
        $agregados = $notasModel->fetchAgregadosPorBloco($blocoId);
        $profRows = $this->blocoModel->getProfessores($blocoId);

        $lancamentoPorMateria = [];
        $cntNaoIniciado = 0;
        $cntEmAndamento = 0;
        $cntConcluido = 0;
        $totalAbaixoSeis = 0;

        foreach ($profRows as $pr) {
            $pid = (int) ($pr['professor_id'] ?? 0);
            $mid = (int) ($pr['materia_id'] ?? 0);
            if ($pid <= 0 || $mid <= 0) {
                continue;
            }
            $turmaIds = array_values(array_filter(array_map(static function ($t) {
                return (int) ($t['id'] ?? 0);
            }, $pr['turmas'] ?? [])));

            $totalEsp = 0;
            if (!empty($turmaIds)) {
                // Conta alunos por turma incluindo vínculos por matrícula (turmas extra/paralelas).
                $totalEsp = count($this->alunosAtivosPorTurmas($turmaIds));
            }

            $key = $pid . '_' . $mid;
            $agg = $agregados[$key] ?? ['com_nota' => 0, 'media_nota' => null, 'abaixo_seis' => 0];
            $com = (int) $agg['com_nota'];
            $totalAbaixoSeis += (int) $agg['abaixo_seis'];

            if ($totalEsp <= 0) {
                $status = 'sem_alunos';
            } elseif ($com <= 0) {
                $status = 'nao_iniciado';
                $cntNaoIniciado++;
            } elseif ($com >= $totalEsp) {
                $status = 'concluido';
                $cntConcluido++;
            } else {
                $status = 'em_andamento';
                $cntEmAndamento++;
            }

            $matNome = $pr['materia_nome'] ?? 'Sem matéria';
            if (!isset($lancamentoPorMateria[$matNome])) {
                $lancamentoPorMateria[$matNome] = [];
            }
            $lancamentoPorMateria[$matNome][] = [
                'professor_nome' => $pr['professor_nome'] ?? '',
                'professor_id' => $pid,
                'materia_id' => $mid,
                'materia_nome' => $matNome,
                'total_esperado' => $totalEsp,
                'com_nota' => $com,
                'media_nota' => $agg['media_nota'],
                'abaixo_seis' => (int) $agg['abaixo_seis'],
                'status' => $status,
                'perc' => $totalEsp > 0 ? round(100 * min($com, $totalEsp) / $totalEsp, 1) : 0.0,
            ];
        }

        $contagem = [
            'ln_nao_iniciado' => $cntNaoIniciado,
            'ln_em_andamento' => $cntEmAndamento,
            'ln_concluido' => $cntConcluido,
            'ln_abaixo_seis' => $totalAbaixoSeis,
            'em_andamento' => 0,
            'enviada' => 0,
            'nao_enviada' => 0,
            'aprovada' => 0,
            'reprovada' => 0,
            'retornada' => 0,
            'nao_avaliada' => 0,
            'aprovado' => 0,
            'concluido' => 0,
        ];

        $combosDestino = [];
        foreach ($profRows as $pr) {
            $combosDestino[(int) ($pr['professor_id'] ?? 0) . '_' . (int) ($pr['materia_id'] ?? 0)] = true;
        }
        $fontesImportacao = array_values(array_filter(
            $notasModel->fetchFontesImportacao($blocoId),
            static function (array $fonte) use ($combosDestino): bool {
                $key = (int) ($fonte['professor_id'] ?? 0) . '_' . (int) ($fonte['materia_id'] ?? 0);
                return !empty($combosDestino[$key]);
            }
        ));
        $flash = $this->getFlashMessage();

        return [
            'title' => 'Gerenciar lançamento de notas — EducaTudo',
            'user' => $user,
            'bloco' => $bloco,
            'modo_lancamento_nota' => true,
            'lancamentoPorMateria' => $lancamentoPorMateria,
            'contagem' => $contagem,
            'provasPorMateria' => [],
            'provas' => [],
            'mostrarBotaoAprovacaoFinal' => false,
            'status_filtro' => '',
            'current_page' => 'provas_blocos',
            'coluna_visivel_portal_aluno' => $this->blocoModel->columnExistsOnBloco('visivel_no_portal_aluno'),
            'fontes_importacao_notas' => $fontesImportacao,
            'csrf_token_importacao' => $this->generateCsrfToken(),
            'flash_importacao_notas' => $flash,
        ];
    }
}
