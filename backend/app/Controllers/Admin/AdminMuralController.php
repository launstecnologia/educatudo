<?php
/**
 * EducaTudo - Mural de Recados (Admin)
 * Admin cria recados para turma(s) ou todos.
 */

if (!class_exists('AdminMuralController')) {
class AdminMuralController extends BaseController
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
        $tipo = $user['tipo'] ?? '';
        if ($tipo !== 'admin' && $tipo !== 'admin_escola') {
            $this->redirectToCorrectDashboard($tipo);
        }
    }

    private function getTurmasAll()
    {
        return $this->db->fetchAll("SELECT id, nome FROM turmas ORDER BY nome");
    }

    /**
     * Lista todos os recados do mural (criados por professores e admin) com filtros: Professor, Matéria, Entre datas, Turma.
     */
    public function index()
    {
        $user = $this->authManager->getUser();
        $filtro_professor = isset($_GET['professor_id']) ? (int)$_GET['professor_id'] : '';
        $filtro_materia = isset($_GET['materia_id']) ? (int)$_GET['materia_id'] : '';
        $filtro_data_de = trim($_GET['data_de'] ?? '');
        $filtro_data_ate = trim($_GET['data_ate'] ?? '');
        $filtro_turma = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : '';
        $filtro_assunto = trim($_GET['assunto'] ?? '');
        $where = ['1=1'];
        $params = [];
        if ($filtro_professor) {
            $where[] = "r.autor_tipo = 'professor' AND r.autor_id = :filtro_professor";
            $params['filtro_professor'] = $filtro_professor;
        }
        if ($filtro_materia) {
            $where[] = "r.materia_id = :filtro_materia";
            $params['filtro_materia'] = $filtro_materia;
        }
        if ($filtro_assunto !== '') {
            $where[] = "(r.titulo LIKE :assunto OR r.conteudo LIKE :assunto2)";
            $params['assunto'] = '%' . $filtro_assunto . '%';
            $params['assunto2'] = '%' . $filtro_assunto . '%';
        }
        if ($filtro_data_de !== '') {
            $where[] = "DATE(r.data_publicacao) >= :data_de";
            $params['data_de'] = $filtro_data_de;
        }
        if ($filtro_data_ate !== '') {
            $where[] = "DATE(r.data_publicacao) <= :data_ate";
            $params['data_ate'] = $filtro_data_ate;
        }
        if ($filtro_turma) {
            $where[] = "(r.enviar_para_todos = 1 OR EXISTS (SELECT 1 FROM mural_recados_turmas rt WHERE rt.mural_recado_id = r.id AND rt.turma_id = :filtro_turma))";
            $params['filtro_turma'] = $filtro_turma;
        }
        $whereSql = implode(' AND ', $where);
        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $total = (int) $this->db->fetch("SELECT COUNT(*) as c FROM mural_recados r WHERE {$whereSql}", $params)['c'];
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $recados = $this->db->fetchAll(
            "SELECT r.*,
                    (SELECT GROUP_CONCAT(t.nome) FROM mural_recados_turmas rt JOIN turmas t ON rt.turma_id = t.id WHERE rt.mural_recado_id = r.id) as turmas_nomes,
                    CASE WHEN r.autor_tipo = 'professor' THEN (SELECT p.nome FROM professores p WHERE p.id = r.autor_id LIMIT 1) ELSE 'Admin' END as autor_nome
             FROM mural_recados r
             WHERE {$whereSql}
             ORDER BY r.data_publicacao DESC
             LIMIT " . (int) $perPage . " OFFSET " . (int) $offset,
            $params
        );
        $professores_opcoes = $this->db->fetchAll("SELECT id, nome FROM professores ORDER BY nome");
        $materias_opcoes = $this->getMateriasOpcoesMural();
        $data = [
            'title' => 'Mural de Recados - Admin',
            'page_title' => 'Mural de Recados',
            'user' => $user,
            'recados' => $recados,
            'turmas_opcoes' => $this->getTurmasAll(),
            'professores_opcoes' => $professores_opcoes,
            'materias_opcoes' => $materias_opcoes,
            'filtro_professor' => $filtro_professor,
            'filtro_materia' => $filtro_materia,
            'filtro_data_de' => $filtro_data_de,
            'filtro_data_ate' => $filtro_data_ate,
            'filtro_turma' => $filtro_turma,
            'filtro_assunto' => $filtro_assunto,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'current_page' => 'mural-recados',
            'base_url' => URL . '/admin/mural-recados',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('admin', 'admin/mural-recados/index', $data);
    }

    private function getMateriasOpcoesMural()
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

    public function criar()
    {
        $user = $this->authManager->getUser();
        $data = [
            'title' => 'Novo Recado - Mural',
            'page_title' => 'Novo Recado - Mural',
            'user' => $user,
            'turmas_opcoes' => $this->getTurmasAll(),
            'recado' => null,
            'current_page' => 'mural-recados',
            'base_url' => URL . '/admin/mural-recados',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('admin', 'admin/mural-recados/form', $data);
    }

    public function salvar()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect(URL . '/admin/mural-recados');
        }
        $user = $this->authManager->getUser();
        $titulo = trim($_POST['titulo'] ?? '');
        $conteudo = trim($_POST['conteudo'] ?? '');
        $enviar_para_todos = isset($_POST['enviar_para_todos']) && $_POST['enviar_para_todos'] === '1' ? 1 : 0;
        $turmas_ids = isset($_POST['turmas']) && is_array($_POST['turmas']) ? array_map('intval', array_filter($_POST['turmas'])) : [];
        if ($titulo === '') {
            $this->setFlashMessage('Título é obrigatório.', 'error');
            $this->redirect(URL . '/admin/mural-recados/criar');
        }
        if (!$enviar_para_todos && empty($turmas_ids)) {
            $this->setFlashMessage('Selecione ao menos uma turma ou marque "Enviar para todos".', 'error');
            $this->redirect(URL . '/admin/mural-recados/criar');
        }
        $data_sai = date('Y-m-d', strtotime('+30 days'));
        $id = $this->db->insert(
            "INSERT INTO mural_recados (titulo, conteudo, autor_tipo, autor_id, enviar_para_todos, data_sai_mural) VALUES (:titulo, :conteudo, 'admin', :autor_id, :enviar_para_todos, :data_sai_mural)",
            [
                'titulo' => $titulo,
                'conteudo' => $conteudo,
                'autor_id' => $user['id'],
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
        $this->redirect(URL . '/admin/mural-recados');
    }

    public function editar()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            $this->setFlashMessage('Recado não encontrado.', 'error');
            $this->redirect(URL . '/admin/mural-recados');
        }
        $user = $this->authManager->getUser();
        $recado = $this->db->fetch("SELECT * FROM mural_recados WHERE id = :id", ['id' => $id]);
        if (!$recado) {
            $this->setFlashMessage('Recado não encontrado.', 'error');
            $this->redirect(URL . '/admin/mural-recados');
        }
        $turmas_ids = $this->db->fetchAll("SELECT turma_id FROM mural_recados_turmas WHERE mural_recado_id = :id", ['id' => $id]);
        $recado['turmas_ids'] = array_column($turmas_ids, 'turma_id');
        $data = [
            'title' => 'Editar Recado - Mural',
            'page_title' => 'Editar Recado - Mural',
            'user' => $user,
            'recado' => $recado,
            'turmas_opcoes' => $this->getTurmasAll(),
            'current_page' => 'mural-recados',
            'base_url' => URL . '/admin/mural-recados',
            'csrf_token' => $this->generateCsrfToken(),
        ];
        $this->viewWithLayout('admin', 'admin/mural-recados/form', $data);
    }

    public function atualizar()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect(URL . '/admin/mural-recados');
        }
        $id = (int)($_POST['id'] ?? 0);
        $recado = $this->db->fetch("SELECT id FROM mural_recados WHERE id = :id", ['id' => $id]);
        if (!$recado) {
            $this->setFlashMessage('Recado não encontrado.', 'error');
            $this->redirect(URL . '/admin/mural-recados');
        }
        $titulo = trim($_POST['titulo'] ?? '');
        $conteudo = trim($_POST['conteudo'] ?? '');
        $enviar_para_todos = isset($_POST['enviar_para_todos']) && $_POST['enviar_para_todos'] === '1' ? 1 : 0;
        $turmas_ids = isset($_POST['turmas']) && is_array($_POST['turmas']) ? array_map('intval', array_filter($_POST['turmas'])) : [];
        if ($titulo === '') {
            $this->setFlashMessage('Título é obrigatório.', 'error');
            $this->redirect(URL . '/admin/mural-recados/editar?id=' . $id);
        }
        if (!$enviar_para_todos && empty($turmas_ids)) {
            $this->setFlashMessage('Selecione ao menos uma turma ou marque "Enviar para todos".', 'error');
            $this->redirect(URL . '/admin/mural-recados/editar?id=' . $id);
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
        $this->redirect(URL . '/admin/mural-recados');
    }

    /**
     * Tabelas do próprio módulo mural (FK em mural_recados); não contam como vínculo externo.
     */
    private static function muralRecadoTabelasInternasMuralRecadoId(): array
    {
        return ['mural_recados_turmas', 'mural_recados_anexos', 'mural_recados_vistos'];
    }

    /**
     * Lista nomes de tabelas (fora do módulo mural) que ainda referenciam este recado.
     * Cobre boletim, notas ou qualquer tabela futura com coluna mural_recado_id.
     */
    private function listarTabelasExternasComVinculoMuralRecado(int $muralRecadoId): array
    {
        if ($muralRecadoId <= 0) {
            return [];
        }
        $internas = self::muralRecadoTabelasInternasMuralRecadoId();
        try {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT TABLE_NAME AS t
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND COLUMN_NAME = 'mural_recado_id'"
            );
        } catch (\Throwable $e) {
            return [];
        }
        $bloqueia = [];
        foreach ($rows as $row) {
            $t = $row['t'] ?? '';
            if ($t === '' || !preg_match('/^[A-Za-z0-9_]+$/', $t)) {
                continue;
            }
            if (in_array($t, $internas, true)) {
                continue;
            }
            try {
                $c = (int) ($this->db->fetch(
                    "SELECT COUNT(*) AS c FROM `{$t}` WHERE mural_recado_id = :id",
                    ['id' => $muralRecadoId]
                )['c'] ?? 0);
            } catch (\Throwable $e) {
                continue;
            }
            if ($c > 0) {
                $bloqueia[] = $t;
            }
        }
        return $bloqueia;
    }

    /**
     * Exclusão via POST (JSON): exige senha do usuário logado e bloqueia se houver mural_recado_id em outras tabelas.
     */
    public function excluir()
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->json(['error' => 'Token inválido ou sessão expirada. Atualize a página e tente de novo.'], 400);
        }
        $id = (int)($_POST['id'] ?? 0);
        $senha = (string)($_POST['senha'] ?? '');
        $user = $this->authManager->getUser();
        if (!$user || !in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->json(['error' => 'Sem permissão.'], 403);
        }
        if ($senha === '') {
            $this->json(['error' => 'Informe sua senha para confirmar a exclusão.'], 422);
        }
        $usuarioId = (int)($user['id'] ?? 0);
        $rowUser = $this->db->fetch(
            'SELECT senha_hash FROM usuarios WHERE id = :id AND ativo = 1 LIMIT 1',
            ['id' => $usuarioId]
        );
        $hash = $rowUser['senha_hash'] ?? '';
        if ($hash === '' || !password_verify($senha, $hash)) {
            $this->json(['error' => 'Senha incorreta.'], 403);
        }
        $recado = $this->db->fetch(
            "SELECT id FROM mural_recados WHERE id = :id",
            ['id' => $id]
        );
        if (!$recado) {
            $this->json(['error' => 'Recado não encontrado.'], 404);
        }
        $externas = $this->listarTabelasExternasComVinculoMuralRecado($id);
        if (!empty($externas)) {
            $lista = implode(', ', $externas);
            $this->json([
                'error' => 'Não é possível excluir: este recado está vinculado a outros registros (por exemplo boletim, notas ou outro módulo). Remova os vínculos antes.',
                'tabelas' => $externas,
                'detalhe' => 'Tabelas com referência: ' . $lista,
            ], 409);
        }
        $this->db->query('DELETE FROM mural_recados WHERE id = :id', ['id' => $id]);
        $this->json(['success' => true, 'message' => 'Recado excluído.']);
    }
}
}
