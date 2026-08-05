<?php
/**
 * Controller para gerenciar blocos de conteúdo das jornadas
 */

require_once __DIR__ . '/../Core/BaseController.php';

if (!class_exists('ContentBlockController')) {
class ContentBlockController extends BaseController
{
    private $db;
    private $authManager;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->authManager = new AuthManager();
    }
    
    /**
     * Lista os blocos de uma jornada
     */
    public function index($jornadaId)
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'professor') {
                throw new Exception('Acesso negado');
            }
            
            // Busca a jornada
            $jornada = $this->db->fetch(
                "SELECT j.*, p.nome as professor_nome, t.nome as turma_nome, m.nome as materia_nome
                 FROM jornadas j
                 JOIN professores p ON j.professor_id = p.id
                 JOIN turmas t ON j.turma_id = t.id
                 JOIN jornadas_materias m ON j.materia_id = m.id
                 WHERE j.id = :jornada_id AND j.professor_id = :prof_id",
                ['jornada_id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            // Busca os blocos da jornada ordenados por ordem
            $blocos = $this->db->fetchAll(
                "SELECT bc.*, tb.nome as tipo_nome, tb.descricao as tipo_descricao, tb.icone, tb.cor
                 FROM jornadas_blocos_conteudo bc
                 JOIN jornadas_tipos_blocos tb ON bc.tipo_bloco_id = tb.id
                 WHERE bc.jornada_id = :jornada_id
                 ORDER BY bc.ordem ASC",
                ['jornada_id' => $jornadaId]
            );
            
            // Busca tipos de blocos disponíveis
            $tiposBlocos = $this->db->fetchAll(
                "SELECT * FROM jornadas_tipos_blocos WHERE ativo = 1 ORDER BY ordem_padrao ASC"
            );
            
            $data = [
                'title' => 'Gerenciar Blocos - ' . $jornada['titulo'] . ' - EducaTudo',
                'jornada' => $jornada,
                'blocos' => $blocos,
                'tipos_blocos' => $tiposBlocos,
                'csrf_token' => $this->generateCsrfToken(),
                'user' => $user,
                'current_page' => 'journeys'
            ];
            
            $this->viewWithLayout('professor', 'teacher/journeys/blocos-conteudo', $data);
            
        } catch (Exception $e) {
            error_log("Erro em BlocosConteudoController::index(): " . $e->getMessage());
            $this->redirect("/teacher/jornadas/{$jornadaId}?erro=" . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Adiciona um novo bloco à jornada
     */
    public function adicionarBloco($jornadaId)
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'professor') {
                throw new Exception('Acesso negado');
            }
            
            // Verifica se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT id FROM jornadas WHERE id = :jornada_id AND professor_id = :prof_id",
                ['jornada_id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            $tipoBlocoId = $_POST['tipo_bloco_id'] ?? null;
            $titulo = $_POST['titulo'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $tempoEstimado = $_POST['tempo_estimado'] ?? null;
            $obrigatorio = isset($_POST['obrigatorio']) ? 1 : 0;
            
            if (!$tipoBlocoId || !$titulo) {
                throw new Exception('Campos obrigatórios não preenchidos');
            }
            
            // Busca a próxima ordem
            $ultimaOrdem = $this->db->fetch(
                "SELECT MAX(ordem) as max_ordem FROM jornadas_blocos_conteudo WHERE jornada_id = :jornada_id",
                ['jornada_id' => $jornadaId]
            );
            
            $proximaOrdem = ($ultimaOrdem['max_ordem'] ?? 0) + 1;
            
            // Insere o novo bloco
            $blocoId = $this->db->insert(
                "INSERT INTO jornadas_blocos_conteudo 
                 (jornada_id, tipo_bloco_id, titulo, descricao, ordem, obrigatorio, tempo_estimado, status) 
                 VALUES (:jornada_id, :tipo_bloco_id, :titulo, :descricao, :ordem, :obrigatorio, :tempo_estimado, 'ativo')",
                [
                    'jornada_id' => $jornadaId,
                    'tipo_bloco_id' => $tipoBlocoId,
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                    'ordem' => $proximaOrdem,
                    'obrigatorio' => $obrigatorio,
                    'tempo_estimado' => $tempoEstimado
                ]
            );
            
            $this->json(['success' => true, 'bloco_id' => $blocoId]);
            
        } catch (Exception $e) {
            error_log("Erro em BlocosConteudoController::adicionarBloco(): " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Atualiza a ordem dos blocos (drag and drop)
     */
    public function atualizarOrdem($jornadaId)
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'professor') {
                throw new Exception('Acesso negado');
            }
            
            // Verifica se a jornada pertence ao professor
            $jornada = $this->db->fetch(
                "SELECT id FROM jornadas WHERE id = :jornada_id AND professor_id = :prof_id",
                ['jornada_id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$jornada) {
                throw new Exception('Jornada não encontrada');
            }
            
            $ordens = $_POST['ordens'] ?? [];
            
            if (empty($ordens)) {
                throw new Exception('Nenhuma ordem fornecida');
            }
            
            // Atualiza a ordem de cada bloco
            foreach ($ordens as $index => $blocoId) {
                $this->db->update(
                    "UPDATE jornadas_blocos_conteudo SET ordem = :ordem WHERE id = :bloco_id AND jornada_id = :jornada_id",
                    [
                        'ordem' => $index + 1,
                        'bloco_id' => $blocoId,
                        'jornada_id' => $jornadaId
                    ]
                );
            }
            
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            error_log("Erro em BlocosConteudoController::atualizarOrdem(): " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Edita um bloco existente
     */
    public function editarBloco($jornadaId, $blocoId)
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'professor') {
                throw new Exception('Acesso negado');
            }
            
            // Busca o bloco
            $bloco = $this->db->fetch(
                "SELECT bc.*, tb.nome as tipo_nome, tb.icone, tb.cor
                 FROM jornadas_blocos_conteudo bc
                 JOIN jornadas_tipos_blocos tb ON bc.tipo_bloco_id = tb.id
                 JOIN jornadas j ON bc.jornada_id = j.id
                 WHERE bc.id = :bloco_id AND bc.jornada_id = :jornada_id AND j.professor_id = :prof_id",
                ['bloco_id' => $blocoId, 'jornada_id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$bloco) {
                throw new Exception('Bloco não encontrado');
            }
            
            $titulo = $_POST['titulo'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $conteudo = $_POST['conteudo'] ?? '';
            $tempoEstimado = $_POST['tempo_estimado'] ?? null;
            $obrigatorio = isset($_POST['obrigatorio']) ? 1 : 0;
            // Campos de anexos e inputs são opcionais
            $anexos = $_POST['anexos'] ?? [];
            $inputs = $_POST['inputs'] ?? [];
            
            if (!$titulo) {
                throw new Exception('Título é obrigatório');
            }
            
            // Atualiza o bloco
            $this->db->update(
                "UPDATE jornadas_blocos_conteudo 
                 SET titulo = :titulo, descricao = :descricao, conteudo = :conteudo, 
                     tempo_estimado = :tempo_estimado, obrigatorio = :obrigatorio, updated_at = NOW()
                 WHERE id = :bloco_id",
                [
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                    'conteudo' => $conteudo,
                    'tempo_estimado' => $tempoEstimado,
                    'obrigatorio' => $obrigatorio,
                    'bloco_id' => $blocoId
                ]
            );
            
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            error_log("Erro em BlocosConteudoController::editarBloco(): " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Remove um bloco
     */
    public function removerBloco($jornadaId, $blocoId)
    {
        try {
            $user = $this->authManager->getUser();
            
            if (!$user || $user['tipo'] !== 'professor') {
                throw new Exception('Acesso negado');
            }
            
            // Verifica se o bloco pertence ao professor
            $bloco = $this->db->fetch(
                "SELECT bc.id FROM jornadas_blocos_conteudo bc
                 JOIN jornadas j ON bc.jornada_id = j.id
                 WHERE bc.id = :bloco_id AND bc.jornada_id = :jornada_id AND j.professor_id = :prof_id",
                ['bloco_id' => $blocoId, 'jornada_id' => $jornadaId, 'prof_id' => $user['id']]
            );
            
            if (!$bloco) {
                throw new Exception('Bloco não encontrado');
            }
            
            // Remove o bloco
            $this->db->delete(
                "DELETE FROM jornadas_blocos_conteudo WHERE id = :bloco_id",
                ['bloco_id' => $blocoId]
            );
            
            $this->json(['success' => true]);
            
        } catch (Exception $e) {
            error_log("Erro em BlocosConteudoController::removerBloco(): " . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
}
