<?php
/**
 * EducaTudo - Mural de Recados (Professor)
 * Professor cria recados para turma(s) ou todos.
 */

if (!class_exists('TeacherMuralController')) {
class TeacherMuralController extends BaseController
{
    private $authManager;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->authManager = new AuthManager();
        $this->db = Database::getInstance();
        if (!$this->authManager->isLoggedIn()) {
            $this->redirect('/');
        }
        $user = $this->authManager->getUser();
        if (($user['tipo'] ?? '') !== 'professor') {
            $this->redirectToCorrectDashboard($user['tipo']);
        }
    }

    private function getTurmasProfessor()
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT * FROM professores WHERE id = :id", ['id' => $user['id']]);
        if (!$professor) return [];
        $ids = json_decode($professor['turmas'] ?? '[]', true);
        if (!is_array($ids) || empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->db->fetchAll("SELECT id, nome FROM turmas WHERE id IN ($placeholders) ORDER BY nome", $ids);
    }

    /**
     * Lista recados do professor com filtros: Matéria, Entre datas.
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT id FROM professores WHERE id = :id", ['id' => $user['id']]);
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado.', 'error');
            $this->redirect(URL . '/professor/dashboard');
        }
        $filtro_materia = isset($_GET['materia_id']) ? (int)$_GET['materia_id'] : '';
        $filtro_data_de = trim($_GET['data_de'] ?? '');
        $filtro_data_ate = trim($_GET['data_ate'] ?? '');
        $where = ["r.autor_tipo = 'professor'", "r.autor_id = :autor_id"];
        $params = ['autor_id' => $professor['id']];
        if ($filtro_materia) {
            $where[] = "r.materia_id = :filtro_materia";
            $params['filtro_materia'] = $filtro_materia;
        }
        if ($filtro_data_de !== '') {
            $where[] = "DATE(r.data_publicacao) >= :data_de";
            $params['data_de'] = $filtro_data_de;
        }
        if ($filtro_data_ate !== '') {
            $where[] = "DATE(r.data_publicacao) <= :data_ate";
            $params['data_ate'] = $filtro_data_ate;
        }
        $recados = $this->db->fetchAll(
            "SELECT r.*,
                    (SELECT GROUP_CONCAT(t.nome) FROM mural_recados_turmas rt JOIN turmas t ON rt.turma_id = t.id WHERE rt.mural_recado_id = r.id) as turmas_nomes
             FROM mural_recados r
             WHERE " . implode(' AND ', $where) . "
             ORDER BY r.data_publicacao DESC",
            $params
        );
        $turmas = $this->getTurmasProfessor();
        $materias_opcoes = $this->getMateriasOpcoes();
        $data = [
            'title' => 'Mural de Recados - Professor',
            'user' => $user,
            'recados' => $recados,
            'turmas_opcoes' => $turmas,
            'materias_opcoes' => $materias_opcoes,
            'filtro_materia' => $filtro_materia,
            'filtro_data_de' => $filtro_data_de,
            'filtro_data_ate' => $filtro_data_ate,
            'current_page' => 'mural-recados',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('professor', 'teacher/mural-recados/index', $data);
    }

    private function getMateriasOpcoes()
    {
        try {
            return $this->db->fetchAll("SELECT id, nome FROM materias ORDER BY nome");
        } catch (\Throwable $e) {
        }
        try {
            return $this->db->fetchAll("SELECT id, nome FROM jornadas_materias ORDER BY nome");
        } catch (\Throwable $e) {
        }
        return [];
    }

    /**
     * Formulário de criar recado
     */
    public function criar()
    {
        $user = $this->authManager->getUser();
        $turmas = $this->getTurmasProfessor();
        $data = [
            'title' => 'Novo Recado - Mural',
            'user' => $user,
            'turmas_opcoes' => $turmas,
            'recado' => null,
            'current_page' => 'mural-recados',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('professor', 'teacher/mural-recados/form', $data);
    }

    /**
     * Salvar novo recado (POST)
     */
    public function salvar()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect(URL . '/professor/mural-recados');
        }
        $user = $this->authManager->getUser();
        $professor = $this->db->fetch("SELECT id FROM professores WHERE id = :id", ['id' => $user['id']]);
        if (!$professor) {
            $this->setFlashMessage('Professor não encontrado.', 'error');
            $this->redirect(URL . '/professor/mural-recados');
        }
        $titulo = trim($_POST['titulo'] ?? '');
        $conteudo = trim($_POST['conteudo'] ?? '');
        $enviar_para_todos = isset($_POST['enviar_para_todos']) && $_POST['enviar_para_todos'] === '1' ? 1 : 0;
        $turmas_ids = isset($_POST['turmas']) && is_array($_POST['turmas']) ? array_map('intval', array_filter($_POST['turmas'])) : [];
        if ($titulo === '') {
            $this->setFlashMessage('Título é obrigatório.', 'error');
            $this->redirect(URL . '/professor/mural-recados/criar');
        }
        if (!$enviar_para_todos && empty($turmas_ids)) {
            $this->setFlashMessage('Selecione ao menos uma turma ou marque "Enviar para todos".', 'error');
            $this->redirect(URL . '/professor/mural-recados/criar');
        }
        $data_sai = date('Y-m-d', strtotime('+30 days'));
        $id = $this->db->insert(
            "INSERT INTO mural_recados (titulo, conteudo, autor_tipo, autor_id, enviar_para_todos, data_sai_mural) VALUES (:titulo, :conteudo, 'professor', :autor_id, :enviar_para_todos, :data_sai_mural)",
            [
                'titulo' => $titulo,
                'conteudo' => $conteudo,
                'autor_id' => $professor['id'],
                'enviar_para_todos' => $enviar_para_todos,
                'data_sai_mural' => $data_sai,
            ]
        );
        if (!$enviar_para_todos && $id) {
            foreach ($turmas_ids as $tid) {
                $this->db->insert("INSERT INTO mural_recados_turmas (mural_recado_id, turma_id) VALUES (:rid, :tid)", ['rid' => $id, 'tid' => $tid]);
            }
        }
        $this->setFlashMessage('Recado publicado no mural.', 'success');
        $this->redirect(URL . '/professor/mural-recados');
    }

    /**
     * Editar recado (GET)
     */
    public function editar()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            $this->setFlashMessage('Recado não encontrado.', 'error');
            $this->redirect(URL . '/professor/mural-recados');
        }
        $user = $this->authManager->getUser();
        $recado = $this->db->fetch("SELECT * FROM mural_recados WHERE id = :id AND autor_tipo = 'professor' AND autor_id = :autor_id", ['id' => $id, 'autor_id' => $user['id']]);
        if (!$recado) {
            $this->setFlashMessage('Recado não encontrado.', 'error');
            $this->redirect(URL . '/professor/mural-recados');
        }
        $turmas_ids = $this->db->fetchAll("SELECT turma_id FROM mural_recados_turmas WHERE mural_recado_id = :id", ['id' => $id]);
        $recado['turmas_ids'] = array_column($turmas_ids, 'turma_id');
        $turmas = $this->getTurmasProfessor();
        $data = [
            'title' => 'Editar Recado - Mural',
            'user' => $user,
            'recado' => $recado,
            'turmas_opcoes' => $turmas,
            'current_page' => 'mural-recados',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('professor', 'teacher/mural-recados/form', $data);
    }

    /**
     * Atualizar recado (POST)
     */
    public function atualizar()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect(URL . '/professor/mural-recados');
        }
        $id = (int)($_POST['id'] ?? 0);
        $user = $this->authManager->getUser();
        $recado = $this->db->fetch("SELECT id FROM mural_recados WHERE id = :id AND autor_tipo = 'professor' AND autor_id = :autor_id", ['id' => $id, 'autor_id' => $user['id']]);
        if (!$recado) {
            $this->setFlashMessage('Recado não encontrado.', 'error');
            $this->redirect(URL . '/professor/mural-recados');
        }
        $titulo = trim($_POST['titulo'] ?? '');
        $conteudo = trim($_POST['conteudo'] ?? '');
        $enviar_para_todos = isset($_POST['enviar_para_todos']) && $_POST['enviar_para_todos'] === '1' ? 1 : 0;
        $turmas_ids = isset($_POST['turmas']) && is_array($_POST['turmas']) ? array_map('intval', array_filter($_POST['turmas'])) : [];
        if ($titulo === '') {
            $this->setFlashMessage('Título é obrigatório.', 'error');
            $this->redirect(URL . '/professor/mural-recados/editar?id=' . $id);
        }
        if (!$enviar_para_todos && empty($turmas_ids)) {
            $this->setFlashMessage('Selecione ao menos uma turma ou marque "Enviar para todos".', 'error');
            $this->redirect(URL . '/professor/mural-recados/editar?id=' . $id);
        }
        $data_sai = date('Y-m-d', strtotime('+30 days'));
        $this->db->query(
            "UPDATE mural_recados SET titulo = :titulo, conteudo = :conteudo, enviar_para_todos = :enviar_para_todos, data_sai_mural = :data_sai_mural WHERE id = :id",
            [
                'titulo' => $titulo,
                'conteudo' => $conteudo,
                'enviar_para_todos' => $enviar_para_todos,
                'data_sai_mural' => $data_sai,
                'id' => $id,
            ]
        );
        $this->db->query("DELETE FROM mural_recados_turmas WHERE mural_recado_id = :id", ['id' => $id]);
        if (!$enviar_para_todos) {
            foreach ($turmas_ids as $tid) {
                $this->db->insert("INSERT INTO mural_recados_turmas (mural_recado_id, turma_id) VALUES (:rid, :tid)", ['rid' => $id, 'tid' => $tid]);
            }
        }
        $this->setFlashMessage('Recado atualizado.', 'success');
        $this->redirect(URL . '/professor/mural-recados');
    }

    /**
     * Excluir recado (POST)
     */
    public function excluir()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $id = (int)($_POST['id'] ?? 0);
        $user = $this->authManager->getUser();
        $recado = $this->db->fetch("SELECT id FROM mural_recados WHERE id = :id AND autor_tipo = 'professor' AND autor_id = :autor_id", ['id' => $id, 'autor_id' => $user['id']]);
        if (!$recado) {
            $this->json(['error' => 'Recado não encontrado'], 404);
        }
        $this->db->query("DELETE FROM mural_recados WHERE id = :id", ['id' => $id]);
        $this->json(['success' => true]);
    }
}
}
